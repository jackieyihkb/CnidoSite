#!/bin/bash
# How far along is the batch, per lineage, and what is running right now.
O=/mnt/sdb/busco_assembly_out
W=/home/$USER/busco_assembly
echo "=== $(date '+%F %T') ==="
for lin in metazoa_odb12.2 cnidaria_odb12; do
  n=$(ls "$O"/*__${lin}/short_summary*.json 2>/dev/null | wc -l)
  printf '%-18s %3d/320 done\n' "$lin" "$n"
done
echo "running: $(pgrep -fc '[b]usco6/bin/busco -i')  queued+done: $(wc -l < $W/logs/jobs.list)"
echo "stage of running jobs:"
for f in "$W"/logs/*.log; do
  case "$f" in *download*|*run.log|*jobs.list) continue ;; esac
  grep -oE "job\(s\) on [a-z_]+" "$f" 2>/dev/null | tail -1
done | sort | uniq -c | sort -rn | head -5
echo "recent completions:"
grep -h "END " "$W/logs/run.log" 2>/dev/null | tail -3
echo "mem: $(free -g | awk 'NR==2{print $3"GB used, "$7"GB avail"}')   load: $(uptime | sed 's/.*load average: //')"
