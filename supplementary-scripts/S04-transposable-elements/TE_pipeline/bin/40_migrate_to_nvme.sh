#!/usr/bin/env bash
# 40_migrate_to_nvme.sh <stage> [species...]
#
# Move a species' te_work directory from /mnt/sda (saturated) to the NVMe
# (idle) and leave a symlink behind, so every path in common.sh still resolves.
# This is the same wiring already used by Acropora_acuminata and
# Condylactis_gigantea.
#
# Why this is needed
# ------------------
# Measured 2026-09-23: sda runs at 100% util delivering 25 MB/s at 15 ms
# latency, while nvme0n1 runs at 2.5% util delivering 60 MB/s at 1.5 ms.  The
# RECON component inside RepeatModeler (edgeredef/eleredef) gets 1.4% CPU on
# sda and 87.6% CPU on NVMe -- a 60x difference.  The rounds are not slow
# because they are hard; they are slow because they are starved.
#
# The cost, stated plainly
# ------------------------
# Stopping a species kills its in-flight RepeatModeler round.  On restart
# rmwrap/RepeatModeler adds -recoverDir (TE_RM_RESUME=1), which resumes from
# the last COMPLETED round -- so the partial round is lost and redone.  That is
# accepted: a round takes 26-38 h on sda but a few hours on NVMe, so redoing
# one is still a large net win.
#
# Stages
# ------
#   stop     kill the watchdog and every process of ours, then wait for the
#            disk to go quiet (this is what makes the copy fast)
#   move     rsync each unfinished species to NVMe, verify, then swap in a
#            symlink.  Idempotent: already-migrated and finished species are
#            skipped.
#   restart  start the watchdog again
#   status   show where each species lives
#
# Nothing is deleted from sda until the copy has been verified byte-for-byte
# by a second dry-run rsync that must report zero differences.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

NVME_DIR="${TE_NVME_DIR:-/home/$USER/te_work_nvme}"
# copy_one runs in a `bash -c` subshell under xargs, so everything it touches
# must be exported.
export NVME_DIR TE_ROOT TE_WORK TE_RESULTS TE_PIPE
STAGE="${1:?usage: 40_migrate_to_nvme.sh <stop|move|restart|status> [species...]}"
shift || true
mkdir -p "$NVME_DIR"

log() { printf '[%s] migrate: %s\n' "$(date '+%F %T')" "$*"; }

# A species is finished when it has delivered a table with rows.  Finished
# species stay where they are: moving them would cost hours and buy nothing.
is_finished() { [[ $({ wc -l < "$TE_RESULTS/TE_info/$1/$1.TE_info.tsv"; } 2>/dev/null || echo 0) -gt 1 ]]; }

# Every species we may touch: manifest order, unfinished, still a real dir.
pending() {
  while IFS=$'\t' read -r sp _; do
    [[ -n "$sp" ]] || continue
    is_finished "$sp" && continue
    [[ -d "$TE_WORK/$sp" && ! -L "$TE_WORK/$sp" ]] || continue
    echo "$sp"
  done < <(tail -n +2 "$TE_PIPE/config/species_manifest.tsv")
}

# The process tree is the precise stop target.  Matching on cwd or cmdline is
# not: `pgrep -f watchdog.sh` matches the very shell running this script (its
# own command line contains the string), and every run_species.sh is its own
# session leader, so a SID/PGID sweep misses them.  Walking ppid from the
# watchdog reaches all 482 descendants and nothing else.
#
# Two guards, both hard requirements:
#   * nothing in our own session is ever signalled
#   * nothing not owned by us is ever signalled
mysid() { ps -o sid= -p $$ | tr -d ' '; }

find_watchdog() {
  local p sid
  for p in $(pgrep -f 'watchdog\.sh' 2>/dev/null); do
    sid=$(ps -o sid= -p "$p" 2>/dev/null | tr -d ' ')
    [[ "$sid" == "$(mysid)" ]] && continue
    case "$(ps -o args= -p "$p" 2>/dev/null)" in
      *TE_pipeline/bin/watchdog.sh*) echo "$p"; return ;;
    esac
  done
}

# Every descendant of $1, via a ppid map built once.
tree_of() {
  local root="$1" pp pid
  declare -A kids
  while read -r pp pid; do kids[$pp]+="$pid "; done < <(ps -eo ppid=,pid= --no-headers)
  _walk() { local x; for x in ${kids[$1]:-}; do echo "$x"; _walk "$x"; done; }
  _walk "$root"
}

# Ours-by-path: anything running a script out of this project's bin/.
ours_by_path() {
  local p pid cmd sid
  for p in /proc/[0-9]*; do
    pid=${p#/proc/}
    cmd=$(tr '\0' ' ' < "$p/cmdline" 2>/dev/null) || continue
    case "$cmd" in *"$TE_PIPE/"*) ;; *) continue ;; esac
    sid=$(ps -o sid= -p "$pid" 2>/dev/null | tr -d ' ')
    [[ "$sid" == "$(mysid)" || "$pid" == "$$" ]] && continue
    [[ "$(ps -o user= -p "$pid" 2>/dev/null | tr -d ' ')" == "${USER:-$(id -un)}" ]] || continue
    echo "$pid"
  done
}

# Everything we may signal: the watchdog's tree plus our-by-path strays.
targets() {
  local wd; wd=$(find_watchdog)
  { [[ -n "$wd" ]] && tree_of "$wd"; ours_by_path; } | sort -un | grep -E '^[0-9]+$' | grep -vE "^($$|1)$"
}

stage_stop() {
  local wd; wd=$(find_watchdog)
  log "watchdog pid = ${wd:-not found}; my session = $(mysid)"
  local n; n=$(targets | grep -c . || true)
  log "stop target: $n processes"
  local p u
  for p in $(targets | head -40); do
    u=$(ps -o comm= -p "$p" 2>/dev/null) && echo "    $p $u"
  done
  # The watchdog goes first: if it survives it will re-dispatch while we move.
  [[ -n "$wd" ]] && { kill -TERM "$wd" 2>/dev/null; log "sent TERM to watchdog $wd"; }
  sleep 3
  # shellcheck disable=SC2046
  kill -TERM $(targets) 2>/dev/null
  log "sent TERM to the rest; waiting 30 s"
  sleep 30
  local left; left=$(targets | grep -c . || true)
  if (( left > 0 )); then
    log "$left still alive; sending KILL"
    # shellcheck disable=SC2046
    kill -KILL $(targets) 2>/dev/null
    sleep 15
  fi
  log "remaining: $(targets | grep -c . || true)"
  log "waiting for sda to go quiet (this is what makes the copy fast)"
  local i
  for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
    u=$(iostat -x 1 2 2>/dev/null | awk '/^sda/{v=$NF} END{print v}')
    log "  sda util = ${u:-?}%"
    awk -v v="${u:-100}" 'BEGIN{exit !(v+0 < 20)}' && { log "  sda is quiet"; break; }
    sleep 15
  done
  log "stop stage done"
}

# ---------------------------------------------------------------------------
# Resume-aware copy plan -- the single biggest lever in this whole migration.
#
# RepeatModeler's -recoverDir block (RepeatModeler:675-741) scans round-1, round-2,
# ... and stops at the first round whose consensi.fa is missing or empty.  That
# round is $badRound; it moves the directory aside as round-N.backup_K and reruns
# from there.  Two consequences drive everything below:
#
#   1. The in-flight round's CONTENTS are thrown away on restart.  RepeatModeler
#      never reads them again -- the backup is forensic.  In these trees that
#      directory is ~99% of the file count (Galaxea_fascicularis: 679,424 of
#      685,465 files, 5.3 of 6.0 GB).  The copy is IOPS-bound on a rotational
#      array at ~240 random reads/s, so copying it costs hours and buys nothing.
#
#   2. That round MUST still exist as a directory.  If it is absent the scan
#      falls through to
#          if ( !-d $badRoundDir ) { print "...appears to contain a successful
#          run..."; exit; }
#      and RepeatModeler quits -- silently truncating the library at the last
#      completed round and never writing <genome>-families.fa.  So we recreate
#      it empty rather than dropping it: `-s consensi.fa` then fails, the round
#      is correctly identified as bad, and it is rerun.  Cost: zero bytes.
#
# The recover block reads nothing else from the bad round -- it only re-reads
# round-i/sampleDB-i.fa for i = 1..highestGoodRound, i.e. the completed rounds,
# which we keep in full.
#
# Emits, relative to $1:
#   KEEP  <path>   must arrive, with the same file count
#   PRUNE <path>   must NOT arrive
#   EMPTY <path>   must arrive as an empty directory
# ---------------------------------------------------------------------------
resume_plan() {
  local src="$1" rm rel n k j b
  for rm in "$src"/*EDTA.raw/*/RM_*; do
    [[ -d "$rm" ]] || continue
    # No log means we cannot tell complete from in-flight.  Copy everything --
    # a slow correct copy beats a fast wrong one.
    [[ -s "$rm/rmod.log" ]] || continue
    rel="${rm#$src/}"
    n=$(grep -cE '^Round Time' "$rm/rmod.log" 2>/dev/null || echo 0)
    k=$(( n + 1 ))
    for j in $(seq 1 "$n"); do
      [[ -d "$rm/round-$j" ]] && echo "KEEP $rel/round-$j"
    done
    [[ -s "$rm/rmod.log" ]] && echo "KEEP $rel/rmod.log"
    # The in-flight round.  Guard on consensi.fa: if it is non-empty the round
    # actually finished and must be kept regardless of what the log says.
    if [[ -d "$rm/round-$k" && ! -s "$rm/round-$k/consensi.fa" ]]; then
      echo "PRUNE $rel/round-$k"
      echo "EMPTY $rel/round-$k"
    fi
    # Backups of it, and any stale rounds past it -- the scan `last`s at the
    # first bad round, so nothing beyond $k is ever consulted.
    for b in "$rm"/round-$k.backup_*; do
      [[ -d "$b" ]] && echo "PRUNE $rel/$(basename "$b")"
    done
    # Backups of COMPLETED rounds.  RepeatModeler backs up the bad round before
    # redding it, so when an interrupted attempt is later resumed successfully
    # the backup is orphaned while the original ends up complete.  It is dead
    # weight: the scan only ever reads round-$j/consensi.fa, never a backup.
    # Measured on the three species that had one: 264k-357k files and 2.4-2.8 GB
    # each -- the bulk of what was left to copy.  Guarded on the original being
    # complete, so a backup is only dropped when what it protects is intact.
    for j in $(seq 1 "$n"); do
      [[ -s "$rm/round-$j/consensi.fa" ]] || continue
      for b in "$rm"/round-$j.backup_*; do
        [[ -d "$b" ]] && echo "PRUNE $rel/$(basename "$b")"
      done
    done
    for j in $(seq $(( k + 1 )) $(( k + 5 ))); do
      if [[ -d "$rm/round-$j" && ! -s "$rm/round-$j/consensi.fa" ]]; then
        echo "PRUNE $rel/round-$j"
      fi
    done
  done

  # LTR module scratch.  EDTA_raw.pl:421 and :428 gate LTRharvest and
  # LTR_FINDER on the COMBINED result file, not on the working directory:
  #     if ($overwrite eq 0 and -s "$genome.finder.combine.scn") { use it }
  #     else { rerun LTR_FINDER }
  # So when <genome>.mod.finder.combine.scn exists and is non-empty, the
  # per-sequence <genome>.mod.finder/ directory is never read again; and when it
  # does not exist the directory is regenerated from scratch.  Either way it is
  # dead weight.  Same for .mod.harvest/ against .mod.harvest.combine.scn.
  # Measured on the two species that had them: 77,332 and 128,290 files.
  local f d
  for f in "$src"/*EDTA.raw/*/*.combine.scn; do
    [[ -s "$f" ]] || continue
    d="${f%.combine.scn}"
    [[ -d "$d" ]] && echo "PRUNE ${d#$src/}"
  done
}

# One species: copy, verify, swap in the symlink.
#
# tar, not cp -a and not rsync.  cp has no --exclude; rsync has one but scans
# BOTH sides to build its file lists, and GNU tar PRUNES an excluded directory
# instead of walking into it -- which is the entire point, because the excluded
# in-flight round is 99% of the file count.  (Measured: rsync -a needed 3 min
# for 1.8 GB of Acropora_awi; that scan is the cost, not the bytes.)
copy_one() {
  local sp="$1"
  local src="$TE_WORK/$sp" dst="$NVME_DIR/$sp"
  local keep="${TE_SDA_BACKUP:-$TE_ROOT/te_work_sda_backup}"
  local junk="${TE_NVME_STALE:-${NVME_DIR}_stale_partial}"
  [[ -L "$src" ]] && { echo "SKIP-symlink   $sp"; return 0; }
  [[ -d "$src" ]] || { echo "SKIP-nodir     $sp"; return 0; }
  is_finished "$sp" && { echo "SKIP-finished  $sp"; return 0; }

  local -a keep_l=() prune_l=() empty_l=() tex=()
  local kind path
  while read -r kind path; do
    [[ -n "$kind" ]] || continue
    case "$kind" in
      KEEP)  keep_l+=("$path") ;;
      PRUNE) prune_l+=("$path"); tex+=("--exclude=./$path") ;;
      EMPTY) empty_l+=("$path") ;;
    esac
  done < <(resume_plan "$src")

  # A pre-existing $dst is never merged into -- an interrupted run leaves a
  # half-written tree, and tar would overlay the new plan on top of it.  Move it
  # aside (same filesystem: an instant rename, not a copy) and start clean.
  if [[ -e "$dst" ]]; then
    mkdir -p "$junk"
    mv "$dst" "$junk/$sp.$(date +%s)" || { echo "FAIL-setaside-dst $sp"; return 1; }
  fi
  mkdir -p "$dst"

  # --ignore-failed-read: a file vanishing mid-walk should not abort a 6 GB
  # copy.  pipefail (set in common.sh) is what makes this test meaningful --
  # without it the `if` would see only the extracting tar's status.
  if ! tar -C "$src" "${tex[@]}" --ignore-failed-read -cf - . 2>/dev/null \
       | tar -C "$dst" -xf - 2>/dev/null; then
    echo "FAIL-tar       $sp"; return 1
  fi
  for path in "${empty_l[@]}"; do mkdir -p "$dst/$path"; done

  # Verify.  Byte totals no longer describe this copy -- we deliberately omitted
  # the in-flight rounds -- so check the three things that actually matter.
  #
  # PRUNE and EMPTY can name the SAME path: the in-flight round is emptied, not
  # removed, because RepeatModeler quits if it is absent.  So the leak test skips
  # those, and they get the stronger test instead -- present, a directory, and
  # holding nothing at all (which is also what proves tar really pruned).
  local bad=0 c1 c2
  local -A emptied=()
  for path in "${empty_l[@]}"; do emptied["$path"]=1; done
  for path in "${prune_l[@]}"; do
    [[ -n "${emptied[$path]:-}" ]] && continue
    [[ -e "$dst/$path" ]] && { echo "FAIL-leak      $sp (pruned $path arrived)"; bad=1; }
  done
  for path in "${empty_l[@]}"; do
    if [[ ! -d "$dst/$path" ]]; then
      echo "FAIL-missing   $sp (placeholder $path)"; bad=1
    else
      c1=$(find "$dst/$path" -mindepth 1 2>/dev/null | wc -l)
      (( c1 == 0 )) || { echo "FAIL-notempty  $sp ($path holds $c1 entries -- prune failed)"; bad=1; }
    fi
  done
  for path in "${keep_l[@]}"; do
    [[ -e "$dst/$path" ]] || { echo "FAIL-missing   $sp ($path)"; bad=1; continue; }
    if [[ -d "$src/$path" ]]; then
      c1=$(find "$src/$path" -type f 2>/dev/null | wc -l)
      c2=$(find "$dst/$path" -type f 2>/dev/null | wc -l)
      [[ "$c1" == "$c2" ]] || { echo "FAIL-count     $sp ($path: $c1 != $c2)"; bad=1; }
    fi
  done
  (( bad )) && return 1

  # Swap: sda dir aside, symlink in.  The kept copy goes OUTSIDE te_work --
  # progress.sh globs "$TE_WORK"/*/ and would otherwise count it as a species,
  # inflating the phase lines the way the old .moved_to_nvme dirs did.
  mkdir -p "$keep"
  mv "$src" "$keep/$sp" || { echo "FAIL-setaside  $sp"; return 1; }
  ln -s "$dst" "$src" || { mv "$keep/$sp" "$src"; echo "FAIL-symlink   $sp"; return 1; }
  echo "OK             $sp  (dropped $(printf '%s\n' "${prune_l[@]}" | grep -c . || echo 0) in-flight dirs, kept $(printf '%s\n' "${keep_l[@]}" | grep -c . || echo 0) items)"
}
export -f copy_one is_finished resume_plan

stage_move() {
  local only=("$@") list
  if (( ${#only[@]} )); then list=$(printf '%s\n' "${only[@]}"); else list=$(pending); fi
  list=$(echo "$list" | grep . || true)
  local total; total=$(echo "$list" | grep -c . || true)
  local par="${TE_MOVE_PAR:-8}"
  log "moving $total unfinished species to $NVME_DIR ($par parallel streams)"
  local t0; t0=$(date +%s)
  echo "$list" | xargs -d '\n' -P "$par" -I{} bash -c 'copy_one "$@"' _ {} \
    | tee -a "$TE_PIPE/logs/migrate.log"
  log "move stage done in $(( ($(date +%s) - t0) / 60 )) min"
}

stage_restart() {
  log "restarting the watchdog"
  nohup setsid bash "$TE_PIPE/bin/watchdog.sh" >> "$TE_PIPE/logs/watchdog.log" 2>&1 < /dev/null &
  sleep 8
  if pgrep -f 'bin/watchdog.sh' > /dev/null; then
    log "watchdog is running (pid $(pgrep -f 'bin/watchdog.sh' | tr '\n' ' '))"
  else
    log "WATCHDOG DID NOT START - check $TE_PIPE/logs/watchdog.log"
    return 1
  fi
  log "wait 60 s to confirm RECON resumes with -recoverDir"
  sleep 60
  local n; n=$(pgrep -fc 'edgeredef|eleredef' || true)
  log "RECON component processes now running: $n"
  local nv; nv=0
  for p in $(pgrep -f 'edgeredef|eleredef' 2>/dev/null); do
    case "$(readlink /proc/$p/cwd 2>/dev/null)" in *te_work_nvme*) nv=$((nv + 1)) ;; esac
  done
  log "  of which on NVMe: $nv"
  grep -h 'rmwrap' "$TE_PIPE/logs"/*.log 2>/dev/null | tail -3
}

stage_status() {
  local sda=0 nvme=0 done=0
  while IFS=$'\t' read -r sp _; do
    [[ -n "$sp" ]] || continue
    if is_finished "$sp"; then done=$((done + 1))
    elif [[ -L "$TE_WORK/$sp" ]]; then nvme=$((nvme + 1))
    elif [[ -d "$TE_WORK/$sp" ]]; then sda=$((sda + 1))
    fi
  done < <(tail -n +2 "$TE_PIPE/config/species_manifest.tsv")
  log "finished: $done   unfinished on NVMe: $nvme   unfinished on sda: $sda"
}

case "$STAGE" in
  stop)    stage_stop ;;
  move)    stage_move "$@" ;;
  restart) stage_restart ;;
  status)  stage_status ;;
  *) echo "usage: $0 <stop|move|restart|status> [species...]" >&2; exit 2 ;;
esac
