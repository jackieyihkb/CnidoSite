#!/bin/bash
# Wait for the assembly transfer to finish, then restart the batch once with the
# full 320-genome queue.
#
# Why restart at all: the first dispatch was launched at 15:19 with only the 191
# genomes that had landed by then, and its job list is fixed at startup -- the
# remaining 129 would otherwise wait for it to drain, hours later.  Re-queueing
# costs only the few minutes the in-flight runs have burned (BUSCO cannot resume
# genome mode mid-run, and run_le.sh clears the tag dir it is about to redo), and
# finished tags are skipped, so the six summaries already collected survive.
set -uo pipefail
W=/home/$USER/busco_assembly
SITE="<SITE-ACCOUNT>@<SITE-HOST>"

# 1. wait for all 320 assemblies (the transfer writes them at ~110 MB/s)
for i in $(seq 1 120); do
  n=$(ls "$W"/in/*.fna 2>/dev/null | wc -l)
  [ "$n" -ge 320 ] && break
  sleep 30
done
echo "=== $(date '+%F %T') input has $(ls "$W"/in/*.fna | wc -l) assemblies"

# 2. stop the wave-1 dispatcher and its BUSCO children (bracketed patterns so this
#    script's own command line never matches)
pkill -f "[r]un_le.sh 4 96 900"
sleep 3
pkill -f "[b]usco6/bin/busco -i $W/in/"
pkill -f "[m]iniprot --trans"
sleep 5
echo "=== $(date '+%F %T') running busco procs left: $(pgrep -fc '[b]usco6/bin/busco -i' || echo 0)"

# 3. one queue, all 320 genomes, both lineages
cd "$W" && bash run_le.sh 4 96 900
