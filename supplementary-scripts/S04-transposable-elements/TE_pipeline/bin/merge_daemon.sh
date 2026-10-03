#!/usr/bin/env bash
# merge_daemon.sh - keep results/cnidaria_TE.db and results/all_species.TE_info.tsv.gz
# current while the pipeline is still running.
#
# Why this is not the watchdog's job
# ----------------------------------
# watchdog.sh only calls merge_now() at the END OF A ROUND, and a "round" is one
# run_all.py invocation -- which does not return until the entire queue has
# drained (run_all.py:261, `while todo or running:`).  So with the full 64-species
# queue that is DAYS away: the merged deliverables would not exist (or would stay
# frozen at whatever they held the last time a round happened to end) for the whole
# run.  That already bit us once -- the DB was reported as present when it was not,
# and had to be produced by hand.
#
# This daemon is deliberately independent of the watchdog: it is additive, it does
# not touch any running script, and merging is idempotent.  Concurrency with the
# watchdog's own merge_now() is handled by the lock inside 90_merge.py itself.
#
# Start it with:
#   nohup setsid bash TE_pipeline/bin/merge_daemon.sh \
#         >> TE_pipeline/logs/merge.log 2>&1 < /dev/null &
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

INTERVAL="${MERGE_INTERVAL:-300}"      # re-check every 5 min
FORCE_EVERY="${MERGE_FORCE_EVERY:-3600}"  # merge anyway at least hourly

mlog() { printf '[%s] merge_daemon: %s\n' "$(date '+%F %T')" "$*"; }

count_finished() {  # same definition the rest of the pipeline uses: the table exists
  ls "$TE_RESULTS"/TE_info/*/*.TE_info.tsv 2>/dev/null | wc -l
}

last=-1
last_forced=$(date +%s)

mlog "started (pid $$, interval ${INTERVAL}s, force every ${FORCE_EVERY}s)"
while :; do
  n=$(count_finished)
  now=$(date +%s)

  if (( n != last )); then
    mlog "finished species: $last -> $n, merging"
    if python3 "$TE_PIPE/bin/90_merge.py" >> "$TE_PIPE/logs/merge.log" 2>&1; then
      last=$n
      last_forced=$now
      mlog "merged $n species"
    else
      # Do not advance `last`: the next tick retries, so a transient failure
      # cannot silently skip a species that has already finished.
      mlog "merge FAILED for n=$n (will retry in ${INTERVAL}s)"
    fi
  elif (( now - last_forced >= FORCE_EVERY )); then
    # Refresh even without a change: cheap, and it keeps the DB's mtime a
    # reliable "is this deliverable live" signal.
    python3 "$TE_PIPE/bin/90_merge.py" >> "$TE_PIPE/logs/merge.log" 2>&1 \
      && mlog "periodic merge ok (n=$n)" \
      || mlog "periodic merge failed (n=$n)"
    last_forced=$now
  fi

  sleep "$INTERVAL"
done
