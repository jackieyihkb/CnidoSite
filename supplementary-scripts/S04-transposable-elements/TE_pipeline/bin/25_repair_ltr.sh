#!/usr/bin/env bash
# 25_repair_ltr.sh <species> [jobs]
#
# Recover a species whose LTR module deadlocked inside one of the scanners'
# Perl-ithread pools.  See the header of bin/ltr_piece.sh for the mechanism.
#
# This is a repair tool, not part of the normal path: a healthy species never
# needs it.  It is idempotent and resumable -- pieces already computed are
# skipped, and the combine step can be re-run at any time.
#
# What it does, in order:
#   1. makes sure the piece list is consistent with the pieces on disk
#   2. runs the missing pieces with xargs -P (plain processes, cannot deadlock)
#   3. calls the scanner's own "-next 1" mode, which is upstream's combine step,
#      so coordinates are remapped exactly as a healthy run would remap them
#   4. verifies the combine output before leaving it where EDTA will find it
#
# EDTA's own resume guards then make the rest of the pipeline work untouched:
# EDTA_raw.pl:420 skips LTRharvest when <genome>.harvest.combine.scn is non-empty
# and :428 skips LTR_FINDER when <genome>.finder.combine.scn is non-empty, so a
# restarted run goes straight to `cat ... > rawLTR.scn` + LTR_retriever.
#
# THE ONE RULE HERE: never leave a file where EDTA will trust it unless it has
# been verified.  EDTA's guard is `-s` (non-empty), so a header-only combine --
# which is what you get if the piece list is empty -- silently becomes "an LTR
# module that found nothing".  That is precisely how the other server's results
# were ruined (README 8(2): "179 intact LTR-RTs have found, but the pre-library
# file is empty").  So the combine output is removed before the run and removed
# again if verification fails; an absent file makes EDTA retry the scanner,
# which is a visible failure rather than a silent wrong answer.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sp="${1:?usage: 25_repair_ltr.sh <species> [jobs]}"
jobs="${2:-24}"
g="$sp.renamed.fa.mod"
ltr="$TE_WORK/$sp/$g.EDTA.raw/LTR"

# EDTA invokes both scanners with these (EDTA_raw.pl:427,434); the overlap is the
# scanner default and is not overridden.  cut.pl and the combine must agree on
# both, or every remapped coordinate would drift.
size=1000000
overlap=100000

activate_env "$sp" || exit 1

# Take the species lock for the whole repair, exactly as run_species.sh does.
# The repair rewrites files the scanners write, so it must not overlap a
# scheduled run -- and it must not be possible for the scheduler to slip a new
# chain in while it works.  Holding the lock for our own duration (rather than
# delegating to a separate holder process) means the lock cannot outlive or
# outlast the work: flock releases it when this script exits, either way.
mkdir -p "$TE_WORK/$sp"
exec 9>"$TE_WORK/$sp/.lock"
if ! flock -n 9; then
  log "$sp" "another job holds the lock — refusing to repair (exit 75)"
  exit 75
fi

[[ -d "$ltr" ]] || { log "$sp" "no LTR dir at $ltr"; exit 1; }
cd "$ltr" || exit 1

# Each scanner ships its own copy of cut.pl; use the matching one so the pieces
# and the list are exactly what that scanner would have made.
cut_for() {
  case "$1" in
    harvest) echo "$EDTA_ENV/share/LTR_HARVEST_parallel/bin/cut.pl" ;;
    finder)  echo "$EDTA_ENV/share/LTR_FINDER_parallel/bin/cut.pl" ;;
  esac
}

# ---------------------------------------------------------------- pieces ---
piece_count() { ls "$ltr/$g.$1" 2>/dev/null | grep -cE '_sub[0-9]+$'; }
list_count()  { wc -l < "$ltr/$g.list" 2>/dev/null || echo 0; }

# cut.pl opens the list with ">" -- it TRUNCATES it -- and then fills it in as it
# walks the genome.  Interrupting it therefore leaves a short or empty list that
# still looks like a file.  A list that disagrees with the pieces on disk is
# exactly the situation that produces a silently empty combine, so check it
# before every run rather than trusting it.
#
# cut.pl writes <seq>.list next to the sequence path it is handed, and the piece
# files into its cwd, so running it from a scratch dir with the real genome path
# rebuilds only the list and leaves the piece files alone.
regen_list() {  # regen_list <kind>
  local tmp; tmp=$(mktemp -d)
  ( cd "$tmp" && perl "$(cut_for "$1")" "$ltr/$g" -s -l "$size" -o "$overlap" ) \
      > "$TE_PIPE/logs/$sp.repair.cut.log" 2>&1
  local rc=$?
  rm -rf "$tmp"
  return $rc
}

prep_kind() {  # prep_kind <kind>
  local kind="$1" dir="$ltr/$g.$kind" np nl
  np=$(piece_count "$kind"); nl=$(list_count)

  if (( np == 0 )); then
    log "$sp" "$kind: no pieces on disk - creating them with cut.pl"
    mkdir -p "$dir"
    ( cd "$dir" && perl "$(cut_for "$kind")" "../$g" -s -l "$size" -o "$overlap" ) \
        > "$TE_PIPE/logs/$sp.repair.cut.log" 2>&1
  elif (( nl != np )); then
    log "$sp" "$kind: piece list has $nl entries but $np pieces exist - regenerating it"
    regen_list "$kind"
  fi

  np=$(piece_count "$kind"); nl=$(list_count)
  if (( np == 0 || nl != np )); then
    log "$sp" "$kind: FAILED - $nl list entries vs $np pieces on disk"
    return 1
  fi
  log "$sp" "$kind: $nl pieces, list consistent"
}

# A file whose last byte is not a newline was almost certainly cut short by a
# SIGKILL mid-write, so it is redone rather than trusted.  Note the test uses
# command substitution, which strips a trailing newline: empty means "ends
# cleanly", non-empty means "truncated".
trunc() { [[ -s "$1" ]] && [[ -n "$(tail -c1 "$1")" ]]; }

missing() {  # missing <kind>  -> pieces still needing work, on stdout
  local kind="$1" f
  while read -r piece; do
    f="$ltr/$g.$kind/$piece.$kind.scn"
    # -e, not -s: a piece with no LTRs legitimately produces an EMPTY .scn, and
    # upstream's own combine resumes with -e (LTR_HARVEST_parallel:227,
    # LTR_FINDER_parallel:234).  -s here would redo those pieces forever.
    if [[ -e "$f" ]] && ! trunc "$f"; then continue; fi
    printf '%s\n' "$piece"
  done < "$ltr/$g.list"
}

count() {  # count <kind>  -> pieces already done
  local kind="$1" n=0 piece
  while read -r piece; do
    [[ -e "$ltr/$g.$kind/$piece.$kind.scn" ]] && n=$((n + 1))
  done < "$ltr/$g.list"
  echo "$n"
}

run_pieces() {  # run_pieces <kind> <todo-file>
  local kind="$1" todo="$2" n
  n=$(wc -l < "$todo")
  if (( n == 0 )); then
    log "$sp" "$kind: nothing to do"
    return 0
  fi
  log "$sp" "$kind: running $n pieces, $jobs at a time (of $(list_count) in the list)"
  # -I{} forces one item per invocation, which is what we want; the worker is a
  # fresh process per piece, so there is no shared state and no thread to wedge.
  xargs -a "$todo" -P "$jobs" -I{} \
        bash "$TE_PIPE/bin/ltr_piece.sh" "$kind" "$ltr" "$g" {} \
    > "$TE_PIPE/logs/$sp.repair.$kind.log" 2>&1
  log "$sp" "$kind: workers done, $(count "$kind") pieces now present"
}

# --------------------------------------------------------------- combine ---
# Upstream's own serial combine.  -next 1 jumps straight to it (it is the same
# entry point the scanners' -verbose "resume from existing pieces" mode uses),
# and -threads must be >1 because that branch lives inside the else.
# -v keeps the piece dir so a failed combine can be retried.
combine() {  # combine <kind>
  local kind="$1" prog
  case "$kind" in
    harvest) prog="$EDTA_ENV/share/LTR_HARVEST_parallel/LTR_HARVEST_parallel"
             extra=(-size "$size" -overlap "$overlap" -time 300 -gt "$EDTA_ENV/bin/") ;;
    finder)  prog="$EDTA_ENV/share/LTR_FINDER_parallel/LTR_FINDER_parallel"
             extra=(-size "$size" -overlap "$overlap" -time 300 -harvest_out) ;;
  esac
  # Never let a stale or half-written file sit where EDTA would trust it.
  rm -f "$ltr/$g.$kind.combine.scn"
  log "$sp" "$kind: combining (upstream -next 1)"
  perl "$prog" -seq "$g" -next 1 -threads "$jobs" -verbose "${extra[@]}" \
       > "$TE_PIPE/logs/$sp.repair.$kind.combine.log" 2>&1
}

any_piece_has_predictions() {  # any_piece_has_predictions <kind>
  local kind="$1" pat
  # gt ltrharvest predictions start with the start coordinate; LTR_FINDER's with
  # "[".  -l -m1 stops each file at its first hit, and `head -1` stops the whole
  # scan at the first file with any prediction, so this is cheap in practice.
  case "$kind" in
    harvest) pat='^[0-9]' ;;
    finder)  pat='^\[' ;;
  esac
  [[ -n "$(grep -rl -m1 -E "$pat" "$ltr/$g.$kind" --include="*.$kind.scn" 2>/dev/null | head -1)" ]]
}

verify() {  # verify <kind>
  local kind="$1" out="$ltr/$g.$kind.combine.scn" bad n
  if [[ ! -s "$out" ]]; then
    log "$sp" "$kind: FAILED - $out missing or empty"
    return 1
  fi
  if ! head -1 "$out" | grep -q '^#LTR_'; then
    log "$sp" "$kind: FAILED - no header in $out"
    return 1
  fi
  # 12 whitespace-separated fields per prediction, as the header documents.  A
  # truncated piece output shows up here as a short line.
  read -r n bad < <(awk '!/^#/{n++; if (NF!=12) bad++} END{print n+0, bad+0}' "$out")
  if (( bad > 0 )); then
    log "$sp" "$kind: FAILED - $bad malformed line(s) out of $n in $out"
    return 1
  fi
  if (( n == 0 )); then
    # A combine with no predictions is legitimate ONLY when the pieces really
    # hold none.  An empty piece list produces the identical file, and EDTA
    # cannot tell the two apart -- so check, because "0 predictions" here is the
    # difference between "this genome has no LTRs" and "we silently lost them".
    if any_piece_has_predictions "$kind"; then
      log "$sp" "$kind: FAILED - combine is empty but pieces do contain predictions"
      return 1
    fi
    log "$sp" "$kind: OK - 0 predictions, and no piece holds any either"
    return 0
  fi
  log "$sp" "$kind: OK - $n predictions, $(stat -c%s "$out") bytes"
}

# ---------------------------------------------------------------- main ------
rc=0
for kind in harvest finder; do
  [[ -s "$ltr/$g.$kind.combine.scn" ]] && { log "$sp" "$kind: already combined"; continue; }
  # A piece dir absent before the scanner ever ran is normal (Catalaphyllia never
  # reached LTR_FINDER); prep_kind creates it.  A dir absent when the scanner
  # DID run means the combine already cleaned it up -- nothing to repair then.
  prep_kind "$kind" || { rc=1; continue; }

  log "$sp" "$kind: $(count "$kind")/$(list_count) pieces already done"
  todo="$(mktemp)"; missing "$kind" > "$todo"
  run_pieces "$kind" "$todo"
  rm -f "$todo"

  combine "$kind"
  if ! verify "$kind"; then
    # Leave no file EDTA would trust; an absent one makes it retry the scanner.
    rm -f "$ltr/$g.$kind.combine.scn"
    rc=1
  fi
done

log "$sp" "repair finished rc=$rc"
exit "$rc"
