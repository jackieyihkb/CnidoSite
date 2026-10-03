#!/usr/bin/env bash
# migrate_to_nvme.sh -- move in-flight species scratch off the saturated HDD onto NVMe.
#
#   bash TE_pipeline/bin/migrate_to_nvme.sh --list          # who would move
#   bash TE_pipeline/bin/migrate_to_nvme.sh --dry-run       # print the plan, stop nothing
#   bash TE_pipeline/bin/migrate_to_nvme.sh                 # do it (stop -> copy -> restart)
#   NVME_JOBS=12 bash TE_pipeline/bin/migrate_to_nvme.sh     # wider copy fan-out
#   bash TE_pipeline/bin/migrate_to_nvme.sh --verify        # check the resumed rounds
#
# WHY
# ---
# The run is I/O-bound, not CPU-bound: of 768 cores under a full load of 61
# species, ~0.9 were busy and 52 processes sat in D state.  The whole bottleneck
# is RECON, the clustering step inside RepeatModeler's LINE module.  RECON is
# single-threaded by design and writes ~250k tiny (~1.6 kB) files per round per
# species, fsyncing each; /dev/sda sustains only ~186 such files/s across ALL
# species, and 0.77 TB/round at 12.8 MB/s is exactly the measured 19 h/round.
#
# /dev/nvme0n1p2 is the same host's root disk: 3.5 TB, ~1% utilised, r_await
# 0.31 ms vs the HDD's 148 ms.  On a RECON-shaped benchmark (300 x 64 kB files,
# each fsynced) the HDD managed 1.2 files/s and the NVMe 2026 files/s -- 1758x.
#
# The swap is a SYMLINK, so TE_WORK/<sp> keeps resolving and no other script
# changes.  A restarted species RESUMES its RepeatModeler rounds instead of
# starting over at round 1 because rmwrap/RepeatModeler adds -recoverDir (EDTA
# never passes it) -- verified end-to-end on Condylactis_gigantea, which came
# back up at "RepeatModeler Round # 5" with round-5.backup_1 holding the round it
# had been interrupted in.
#
# WHY IT IS FAST
# --------------
# Two things, both measured rather than assumed:
#
# 1. The churn lives in three directories RECON creates and then deletes itself
#    at the end of a round -- ele_def_res, ele_redef_res, edge_redef_res -- and
#    the -recoverDir block never reads them.  On Galaxea_fascicularis they are
#    298,569 of 316,468 files (94%, 1.25 GB of 3.0 GB); on Acropora_acuminata
#    252,561 of 260,982 (97%).  Excluding them costs nothing, because the
#    in-progress round they belong to is moved aside and re-run on resume anyway.
#
# 2. The copy is run in PARALLEL.  A single serial rsync got ~3 MB/s because the
#    HDD's queue is deep (aqu-sz 55, r_await 40-90 ms) and one stream cannot fill
#    it; five concurrent streams measured 99 MB/s aggregate, a 30x difference.
#    Copying serially would have taken longer than the run it was meant to speed
#    up.
#
# STOP EVERYTHING FIRST
# ---------------------
# The copy is contention-limited, not bandwidth-limited, so phase 1 stops every
# target species (and the watchdog/run_all supervisor, so nothing re-dispatches
# them mid-copy) before a byte moves, and phase 3 restarts the supervisor and
# lets it re-dispatch everything.  A species' in-flight round is discarded either
# way -- that happens on any stop -- and the pilot proved -recoverDir resumes
# from the last complete round rather than restarting the 5-round chain.
set -uo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

NVME_ROOT="${NVME_ROOT:-/home/$USER/te_work_nvme}"
LOG="$TE_PIPE/logs/migrate_nvme.log"
STATE="$TE_PIPE/logs/migrate_nvme.state.tsv"
JOBS="${NVME_JOBS:-8}"

# Disposable per-round scratch.  RepeatModeler deletes these itself
# (`unless ($DEBUG) { rm -rf ele_def_res ele_redef_res edge_redef_res }`) and the
# recovery block never reads them; `round-N.backup_M` is a round it already
# moved aside and also never reads.
EXCLUDES=(
  --exclude=ele_def_res/
  --exclude=ele_redef_res/
  --exclude=edge_redef_res/
  --exclude='round-*.backup_*'
)

logm() { printf '[%s] %s\n' "$(date '+%F %T')" "$*" >> "$LOG"; }

# Is an RM_* round actually in flight?  That is the only state worth migrating:
# the churn is RECON writing its tiny scratch inside the round directory it is
# currently working on, and a round is finished exactly when it has written
# consensi.fa.  Testing the species' *stage* instead (e.g. "is the LINE module
# finished") misclassifies species whose EDTA already completed -- Thelohanellus
# _kitauei ships a TEanno.gff3 yet still has an RM_* round with no consensi.fa.
recon_in_flight() {
  local sp="$1" line rm r
  line="$TE_WORK/$1/$1.renamed.fa.mod.EDTA.raw/LINE"
  for rm in "$line"/RM_*/; do
    [[ -d "$rm" && -s "$rm/rmod.log" ]] || continue
    r="$(grep -c '^RepeatModeler Round #' "$rm/rmod.log" 2>/dev/null)"
    (( r > 0 )) || continue
    [[ -s "$rm/round-$r/consensi.fa" ]] && continue   # that round is done
    printf '%s\t%s\t%s\n' "$sp" "${rm%/}" "$r"
    return 0
  done
  return 1
}

# EDTA finished for this species: annotation written, so there is no RECON churn
# left, and killing it mid-anno would throw away work that has no resume point.
edta_done() { [[ -s "$(sp_edta_gff "$1")" ]]; }

already_nvme() { [[ -L "$TE_WORK/$1" ]]; }

targets() {
  local sp
  species_list | while read -r sp; do
    [[ -d "$TE_WORK/$sp" ]] || continue
    already_nvme "$sp"      && continue
    edta_done    "$sp"      && continue
    recon_in_flight "$sp" >/dev/null || continue
    echo "$sp"
  done
}

# ---------------------------------------------------------------- process control
# Every match below is an EXACT whole-argument match or a path/cwd match.  Do NOT
# use `pgrep -f`/`pkill -f` here: a pattern that appears in this script's own
# command line makes the shell kill itself (measured twice, exit 144).
#
# The snapshot matters for speed.  Scanning /proc per species cost 50 SECONDS
# each (7968 processes on this shared box); for 59 species that is most of an
# hour spent reading /proc.  One pass, reused for every species, makes it free.
SNAP=""
snapshot_procs() {
  local p cl cwd
  SNAP="$(mktemp "${TE_TMP:-/tmp}/procsnap.XXXXXX")"
  for p in /proc/[0-9]*; do
    p="${p#/proc/}"
    [[ "$p" == "$$" || "$p" == "$PPID" ]] && continue
    cl="$(cat "/proc/$p/cmdline" 2>/dev/null | tr '\0' '\n')"
    [[ -n "$cl" ]] || continue
    case "$cl" in *migrate_to_nvme*) continue ;; esac
    cwd="$(readlink "/proc/$p/cwd" 2>/dev/null)"
    printf '%s\t%s\t%s\n' "$p" "$cwd" "$(printf '%s' "$cl" | tr '\n' '\037')" >> "$SNAP"
  done
}

# matches: exact argv token == species, OR cmdline contains <workdir>/,
# OR cwd is inside the workdir (RECON binaries carry no usable argv)
pids_of() {  # pids_of <species>   (reads $SNAP)
  awk -F'\t' -v sp="$1" -v d="$TE_WORK/$1" '
    { hit = 0
      n = split($3, a, "\037")
      for (i = 1; i <= n; i++) if (a[i] == sp) { hit = 1; break }
      if (!hit && index($3, d "/") > 0) hit = 1
      if (!hit && $2 != "" && ($2 == d || index($2, d "/") == 1)) hit = 1
      if (hit) print $1 }
  ' "$SNAP"
}

stop_supervisor() {  # reads $SNAP
  local pids p
  pids="$(awk -F'\t' '$3 ~ /watchdog\.sh|run_all\.py/ {print $1}' "$SNAP" | sort -u)"
  if [[ -z "$pids" ]]; then logm "  supervisor: none running"; return 0; fi
  logm "  supervisor: TERM $(echo "$pids" | tr '\n' ' ')"
  for p in $pids; do kill "$p" 2>/dev/null; done
  sleep 3
}

# ------------------------------------------------------------------------ copy
copy_species() {  # copy_species <sp>
  local sp="$1" src="$TE_WORK/$1" dst="$NVME_ROOT/$1" diff
  mkdir -p "$dst" || return 1
  if ! rsync -a --no-inc-recursive "${EXCLUDES[@]}" "$src/" "$dst/" >>"$LOG" 2>&1; then
    logm "  $sp: rsync FAILED"; return 1
  fi
  # Strongest cheap check: a second pass must report nothing to do.
  diff="$(rsync -an --itemize-changes "${EXCLUDES[@]}" "$src/" "$dst/" 2>>"$LOG")"
  if [[ -n "$diff" ]]; then
    logm "  $sp: VERIFY FAILED, $(echo "$diff" | wc -l) difference(s):"
    printf '%s\n' "$diff" | head -5 | sed 's/^/      /' >> "$LOG"
    return 1
  fi
  return 0
}

swap_species() {  # swap_species <sp>
  local sp="$1" src="$TE_WORK/$1" dst="$NVME_ROOT/$1" seen
  [[ -L "$src" ]] && { logm "  $sp: already a symlink, skipping swap"; return 0; }
  [[ -d "$src.moved_to_nvme" ]] && { logm "  $sp: rollback copy already exists -- not clobbering"; return 1; }
  mv "$src" "$src.moved_to_nvme" || return 1
  ln -s "$dst" "$src"            || { mv "$src.moved_to_nvme" "$src"; return 1; }
  seen="$(readlink -f "$src")"
  if [[ "$seen" != "$dst" ]]; then
    logm "  $sp: symlink resolves to $seen, expected $dst -- rolling back"
    rm -f "$src"; mv "$src.moved_to_nvme" "$src"; return 1
  fi
  return 0
}

# one worker: copy + verify + swap every species it is handed.
# Counters go to files rather than variables -- workers are separate processes,
# and a short `printf >> file` append is atomic, so this needs no locking.
OKF=""; BADF=""
worker() {
  local sp ok=0 bad=0
  for sp in "$@"; do
    if copy_species "$sp" && swap_species "$sp"; then
      ok=$((ok+1)); printf 'x\n' >> "$OKF"; logm "  $sp: OK"
    else
      bad=$((bad+1)); printf 'x\n' >> "$BADF"; logm "  $sp: FAILED -- left in place, restarts on the HDD"
    fi
  done
  logm "  worker done: $ok ok, $bad failed"
}

# -------------------------------------------------------------------- main
mode="${1:---run}"
case "$mode" in
  --verify)  exec bash "$TE_PIPE/bin/verify_nvme_resume.sh" ;;
esac
shift 2>/dev/null || true
if (( $# )); then
  TARGETS=("$@")
else
  mapfile -t TARGETS < <(targets)
fi
if [[ "$mode" == "--list" ]]; then printf '  %s\n' "${TARGETS[@]}"; exit 0; fi

logm "=== migrate_to_nvme: ${#TARGETS[@]} species, mode=$mode, jobs=$JOBS, NVMe free: $(df -h "$NVME_ROOT" | awk 'NR==2{print $4}') ==="

if [[ "$mode" == "--dry-run" ]]; then
  printf '  %s\n' "${TARGETS[@]}" >> "$LOG"
  logm "dry run: nothing stopped.  Re-run without --dry-run to migrate."
  exit 0
fi
(( ${#TARGETS[@]} )) || { logm "no targets -- nothing to do"; exit 0; }

mkdir -p "$NVME_ROOT"
: > "$STATE"
trap 'rm -f "$SNAP"' EXIT

logm "--- phase 1: stop supervisor + ${#TARGETS[@]} species ---"
snapshot_procs
stop_supervisor
for sp in "${TARGETS[@]}"; do
  pids="$(pids_of "$sp" | sort -u)"
  [[ -n "$pids" ]] || { logm "  $sp: nothing running"; continue; }
  logm "  $sp: TERM $(echo "$pids" | tr '\n' ' ')"
  for p in $pids; do kill "$p" 2>/dev/null; done
done
sleep 20
snapshot_procs
hard=0
for sp in "${TARGETS[@]}"; do
  pids="$(pids_of "$sp" | sort -u)"
  [[ -n "$pids" ]] || continue
  hard=$((hard+1))
  logm "  $sp: SIGKILL leftovers $(echo "$pids" | tr '\n' ' ')"
  for p in $pids; do kill -9 "$p" 2>/dev/null; done
done
sleep 10
snapshot_procs
still=()
for sp in "${TARGETS[@]}"; do
  pids="$(pids_of "$sp" | sort -u)"
  [[ -n "$pids" ]] && still+=("$sp")
done
logm "  phase 1 done: $hard species needed SIGKILL; ${#still[@]} still alive ${still[*]:-}"

# Record the round each species must come back on, BEFORE the parallel phase, so
# the state file is written by one process instead of racing between workers.
for sp in "${TARGETS[@]}"; do
  IFS=$'\t' read -r _ rm_dir cur < <(recon_in_flight "$sp")
  best=0
  for k in 6 5 4 3 2 1; do
    [[ -s "$rm_dir/round-$k/consensi.fa" ]] && { best=$k; break; }
  done
  printf '%s\t%s\t%s\n' "$sp" "${rm_dir:-none}" "$best" >> "$STATE"
done

logm "--- phase 2: copy/verify/swap, $JOBS parallel ---"
OKF="$(mktemp)"; BADF="$(mktemp)"; trap 'rm -f "$SNAP" "$OKF" "$BADF"' EXIT
# Round-robin the targets so no worker gets all the big genomes.
declare -a CHUNK
for i in "${!TARGETS[@]}"; do
  k=$(( i % JOBS ))
  CHUNK[$k]="${CHUNK[$k]:-}${TARGETS[$i]}"$'\n'
done
for k in "${!CHUNK[@]}"; do
  mapfile -t part < <(printf '%s' "${CHUNK[$k]}")
  (( ${#part[@]} )) || continue
  worker "${part[@]}" &
done
wait
ok=$(wc -l < "$OKF"); bad=$(wc -l < "$BADF")
logm "--- phase 2 done: $ok ok, $bad failed ---"

logm "--- phase 3: restart supervisor (it re-dispatches every species) ---"
nohup setsid bash "$TE_PIPE/bin/watchdog.sh" >> "$TE_PIPE/logs/watchdog.log" 2>&1 </dev/null &
disown 2>/dev/null
sleep 5
if pgrep -f 'bin/watchdog[.]sh' >/dev/null; then
  logm "  watchdog running: $(pgrep -f 'bin/watchdog[.]sh' | tr '\n' ' ')"
else
  logm "  WARNING: watchdog did not start"
fi
logm "=== migration complete: $ok/${#TARGETS[@]} species on NVMe ==="
logm "    verify resumes with: bash $TE_PIPE/bin/verify_nvme_resume.sh"
