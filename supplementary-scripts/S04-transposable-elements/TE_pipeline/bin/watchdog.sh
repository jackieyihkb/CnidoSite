#!/usr/bin/env bash
# watchdog.sh - run the pipeline to completion, unattended, retrying failures.
#
#   nohup setsid bash TE_pipeline/bin/watchdog.sh \
#         >> TE_pipeline/logs/watchdog.log 2>&1 < /dev/null &
#
# run_all.py exits once its own queue drains, whatever the outcome, and it never
# retries a species that crashed.  Over a multi-day run that is the difference
# between "finishes" and "quietly stops at 41 of 64".  This wrapper re-invokes it
# until every species has a TE table, and gives up only after MAX_STALL rounds
# in a row that finish nothing (so a genuinely broken species cannot spin
# forever).  Each round is cheap: finished species are skipped entirely.
#
# Progress:  tail -f TE_pipeline/logs/watchdog.log
# Status:    python3 TE_pipeline/bin/run_all.py --status
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

MAX_STALL="${MAX_STALL:-6}"      # rounds with zero new species before giving up
ROUND_SLEEP="${ROUND_SLEEP:-60}"
# A scheduler round is now cheap: species already owned by another job are left
# queued instead of being spawned to fail, so polling every 2 min is enough and
# keeps the log readable over a multi-day run.
POLL="${POLL:-120}"

count_done() {
  # A table that exists but holds only its header is NOT done -- it is the exact
  # signature of the dead-TMPDIR anno failure.  Counting it as done would make
  # the watchdog stop retrying that species and report a number that hides the
  # loss, so require at least one data row.
  local n=0 sp f
  for sp in $(cut -f1 "$TE_PIPE/config/species_manifest.tsv" | tail -n +2); do
    f="$TE_RESULTS/TE_info/$sp/$sp.TE_info.tsv"
    [[ -s "$f" ]] || continue
    (( $(wc -l < "$f") > 1 )) && n=$((n + 1))
  done
  echo "$n"
}

count_total() {
  # species whose assembly is actually on disk (same rule run_all.py uses)
  local n=0 sp g
  while IFS=$'\t' read -r sp g _; do
    [[ -s "$TE_ROOT/$g" ]] && n=$((n + 1))
  done < <(tail -n +2 "$TE_PIPE/config/species_manifest.tsv")
  echo "$n"
}

# Resurrect a TMPDIR that has been deleted under a running chain.
#
# A running process cannot be re-env'd, so the only way to save a chain whose
# TMPDIR has been reaped is to recreate the directory at the exact path it holds.
# Without this, the failure is silent and lands at the very END of a species:
# `sort` fails inside the anno stage, the post-processing writes a 0-byte TE.fa,
# and the deliverable is an empty TE table that still counts as "finished"
# (measured: Nemopilema_nomurai, 2026-09-21 -- see common.sh for the full chain).
#
# Cheap: one /proc scan per round, no disk I/O unless a path is actually missing.
ensure_live_tmpdir() {
  local p path fixed=0
  for p in $(pgrep -f "$TE_PIPE/bin/|EDTA\.pl|EDTA_raw\.pl|RepeatModeler|rmblastn|repeatmasker" 2>/dev/null); do
    path=$(tr '\0' '\n' < "/proc/$p/environ" 2>/dev/null | sed -n 's/^TMPDIR=//p' | head -1)
    [[ -n "$path" ]] || continue
    # Only touch paths that live under a scratch root, never something a user set
    # deliberately to a real directory that merely happens to be absent.
    [[ "$path" == /tmp/* ]] || continue
    if [[ ! -d "$path" ]]; then
      mkdir -p "$path" 2>/dev/null && { fixed=$((fixed + 1)); log watchdog "recreated dead TMPDIR for pid $p: $path"; }
    fi
  done
  (( fixed > 0 )) && log watchdog "WARNING: recreated $fixed dead TMPDIR path(s); run 34_redo_anno.sh for any species whose anno already ran with one"
  return 0
}

# Also count species whose table exists but is EMPTY.  Such a species satisfies
# every "is it finished" test in this file and in run_all.py, so it would be
# reported as done while delivering nothing.  Surface it instead.
empty_tables() {
  local f sp
  for f in "$TE_RESULTS"/TE_info/*/*.TE_info.tsv; do
    [[ -e "$f" ]] || continue
    # header only == 0 data rows
    (( $(wc -l < "$f") <= 1 )) || continue
    sp=$(basename "$f" .TE_info.tsv); echo "$sp"
  done
}

# Rebuild the merged table + SQLite from whatever species have finished.
#
# 90_merge.py is idempotent, so merging early and often is free and it means the
# deliverable never depends on the last straggler.  That matters: the old flow
# merged only at 64/64 or not at all when it gave up, so one wedged species would
# have withheld the database for all the others.
merge_now() {
  if python3 "$TE_PIPE/bin/90_merge.py"; then
    log watchdog "merged $(count_done)/$(count_total) species into results/"
  else
    log watchdog "merge failed (will retry next round)"
  fi
}

main() {
total="$(count_total)"
log watchdog "watching $total species; logs: $TE_PIPE/logs/watchdog.log"

stall=0
while true; do
  ensure_live_tmpdir
  before="$(count_done)"
  if (( before >= total )); then
    log watchdog "all $total species done"
    merge_now
    break
  fi

  # Report empty deliverables loudly, and repair them: they are the one failure
  # that looks like success everywhere else in the pipeline.
  for sp in $(empty_tables); do
    log watchdog "EMPTY TE table (counted as unfinished): $sp"
    [[ -x "$TE_PIPE/bin/34_redo_anno.sh" ]] \
      && bash "$TE_PIPE/bin/34_redo_anno.sh" "$sp" >> "$TE_PIPE/logs/redo_anno.log" 2>&1 \
      && log watchdog "  -> re-ran the anno stage for $sp (see logs/redo_anno.log)"
  done

  log watchdog "round start: $before/$total finished — running the queue"
  python3 "$TE_PIPE/bin/run_all.py" --poll "$POLL"
  rc=$?
  after="$(count_done)"
  log watchdog "round done (rc=$rc): $before -> $after / $total"

  if (( after > before )); then
    stall=0
    merge_now
  else
    stall=$((stall + 1))
    log watchdog "no new species this round (stall $stall/$MAX_STALL)"
  fi

  if (( stall >= MAX_STALL )); then
    log watchdog "giving up after $MAX_STALL stalled rounds"
    python3 "$TE_PIPE/bin/run_all.py" --status
    log watchdog "species still lacking a TE table:"
    for sp in $(cut -f1 "$TE_PIPE/config/species_manifest.tsv" | tail -n +2); do
      [[ -s "$TE_RESULTS/TE_info/$sp/$sp.TE_info.tsv" ]] || log watchdog "  $sp"
    done
    # Merge what we have rather than throwing it away: a partial database is
    # useful, and the run can be resumed later for the stragglers.
    merge_now
    break
  fi

  sleep "$ROUND_SLEEP"
done
log watchdog "watchdog exiting"
}

# Guard against `source watchdog.sh`, which would otherwise start the loop in
# the caller's shell (and is a genuinely easy mistake to make).
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
  main "$@"
fi
