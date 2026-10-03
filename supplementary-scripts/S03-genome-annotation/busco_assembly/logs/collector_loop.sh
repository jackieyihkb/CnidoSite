#!/bin/bash
# Publish finished runs to busco_assembly every 15 minutes while the batch runs, so
# genomeinfo.php's two BUSCO columns fill in as results land instead of only at the end.
# Stops only when both columns are genuinely complete for all 320 genomes.
#
# The flock is what stops these accumulating.  cron fires this every 13 min while runs
# remain, but the loop sleeps 900 s between passes, so a new instance used to start before
# the previous one had exited: by 2026-10-02 about 50 of them had piled up since midnight,
# all publishing on the same 15-min beat.  Each sleeping loop is a task in the same
# 512-task user slice the batch competes for, and the synchronised rsync+ssh+mysql passes
# spike it much higher than 50.  Watchdog.sh has guarded itself this way all along.
set -uo pipefail
W=/home/$USER/busco_assembly
OUT=/mnt/sdb/busco_assembly_out
exec 6>"$W/.collector.lock"
flock -n 6 || { echo "$(date '+%F %T') another collector is running"; exit 3; }
for i in $(seq 1 400); do
  bash "$W/logs/collect_and_publish.sh" >> "$W/logs/collect.log" 2>&1
  # Stop only when BOTH columns are complete for every genome.  The old test was
  # `grep -q "ALL GENOME RUNS DONE" run.log`, but that marker is written by ANY dispatcher
  # whose list drains -- including a cnidaria-only one, and on 2026-10-02 10:58:11 one with an
  # empty list ("ALL GENOME RUNS DONE ... finished=295/0").  It fired mid-batch and put every
  # later cron instance into publish-once-then-break mode: the site looked like it had reached
  # the end while 249 metazoa runs were still outstanding.  The summaries are the truth, and
  # the phase-1 instruction is that the cnidaria column fills first -- so require both.
  nd=$(ls "$OUT"/*__cnidaria_odb12/short_summary*.json 2>/dev/null | wc -l)
  md=$(ls "$OUT"/*__metazoa_odb12.2/short_summary*.json 2>/dev/null | wc -l)
  if [ "$nd" -ge 320 ] && [ "$md" -ge 320 ]; then
    echo "collector loop exit: both columns complete (cnidaria $nd, metazoa $md) $(date '+%F %T')" >> "$W/logs/collect.log"
    break
  fi
  sleep 900
done
echo "collector loop exit $(date '+%F %T')" >> "$W/logs/collect.log"
