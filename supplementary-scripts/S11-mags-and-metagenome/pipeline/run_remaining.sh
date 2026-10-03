#!/usr/bin/env bash
# Watcher: wait for the two long stages, then run the aggregation.
#
# Stage 03 (InterProScan) and stage 05 (Kofam) run for hours and are
# independent of each other.  This waits for both, retries stage 05 if any chunk
# came up short, and then runs stage 06 so the web payload is ready without
# anyone having to poll by hand.
#
# It deliberately does NOT retry stage 03: a MAG that fails InterProScan is a
# real result (empty or pathological input), and re-running it forever would
# stall the aggregation.  Failures are reported instead.
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
source pipeline/env.sh

log="$MAG_ROOT/work/run_remaining.log"
say() { echo "[run $(date +%H:%M:%S)] $*" | tee -a "$log"; }

say "watching for stage 03 and stage 05 to finish"

# --- stage 05 -----------------------------------------------------------------
# Match on the script path alone, not "bash pipeline/05_kegg.sh".  A driver
# started elsewhere on the box carries an absolute path in its cmdline
# ("bash /abs/path/pipeline/05_kegg.sh"), which the anchored form misses -- the
# watcher then declares the stage finished and starts a *second* driver, and the
# two of them race over the same chunks.
while pgrep -f 'pipeline/05_kegg\.sh' >/dev/null 2>&1; do sleep 60; done
say "stage 05 process ended"
for attempt in 1 2 3; do
    missing=0
    for f in "$MAG_ROOT"/work/kofam_chunks/chunk_*.faa; do
        [ -s "${f%.faa}.tbl" ] || missing=$((missing + 1))
    done
    [ "$missing" -eq 0 ] && break
    say "stage 05: $missing chunks missing, retry $attempt"
    bash "$MAG_ROOT/pipeline/05_kegg.sh" >> "$MAG_ROOT/work/05_kegg.nohup" 2>&1
done
say "stage 05 done: $(ls "$MAG_ROOT"/work/kofam_chunks/*.tbl 2>/dev/null | wc -l) chunks"

# --- stage 03 -----------------------------------------------------------------
while pgrep -f 'pipeline/03_interproscan\.sh' >/dev/null 2>&1; do sleep 60; done
say "stage 03 process ended"

n_ok=$(awk -F'\t' 'NR>1 && $5=="OK"' "$MAG_ROOT/work/ips_status.tsv" | wc -l)
n_bad=$(awk -F'\t' 'NR>1 && $5!="OK"' "$MAG_ROOT/work/ips_status.tsv" | wc -l)
say "stage 03 done: $n_ok OK, $n_bad not-OK (of 115)"
awk -F'\t' 'NR>1 && $5!="OK"{print "  not-OK: "$1" "$5}' "$MAG_ROOT/work/ips_status.tsv" | tee -a "$log"

# --- aggregate ------------------------------------------------------------------
say "running stage 06"
if python3 "$MAG_ROOT/pipeline/06_aggregate.py" >> "$log" 2>&1; then
    read -r n_p n_m <<< "$(python3 -c "
import json
d=json.load(open('$MAG_ROOT/web/data/mags_summary.json'))
print(sum(x['n_proteins'] for x in d), len(d))
")"
    say "stage 06 OK: $n_p proteins across $n_m MAGs"
    say "payload ready in web/data/ ($(du -sh "$MAG_ROOT/web/data" | cut -f1))"

    # Gate the payload before anyone deploys it.  Nothing downstream re-derives
    # the summary counts from the protein records, so if the two disagree the
    # page renders a coverage bar that does not match the table beneath it --
    # plausible enough to go unnoticed.
    if python3 "$MAG_ROOT/pipeline/07_verify.py" >> "$log" 2>&1; then
        say "stage 07 OK: payload is self-consistent"
    else
        say "stage 07 FAILED: payload is NOT self-consistent -- do not deploy"
    fi
else
    say "stage 06 FAILED -- see log above"
fi
say "all done"
