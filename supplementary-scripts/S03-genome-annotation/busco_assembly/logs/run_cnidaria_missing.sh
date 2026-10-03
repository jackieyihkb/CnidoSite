#!/bin/bash
# Fast-track the cnidaria_odb12 run for the 5 assemblies added 2026-10-02 (AIDSS ALIUI PACUT
# PCOMP PXISH -- the six dashes on genomeinfo.php's first BUSCO column, of which the sixth,
# Coelastrea_aspera, has no genome in the store yet).
#
# Why a separate script: run_le.sh's job list is size-sorted, so these five sit at cnidaria
# positions 122-231, i.e. combined lines 244-462 of 650, behind ~18 not-yet-run metazoa
# genomes -- and while the machine is at/above the CPU ceiling the dispatcher advances *zero*
# list entries per brake cycle.  A ~5-minute cnidaria run would therefore wait hours.  This
# script runs only the cnidaria lineage, at most 2 jobs (2x16 threads ~ +4% machine) at a
# time, so the metazoa wave is left alone.
#
# Identical command line to run_le.sh's one(), so these are the same measurement and the
# usual collector publishes them.  Idempotent: a tag with a summary or a live busco is
# skipped, so it is safe to run from cron repeatedly and safe to race the dispatcher --
# whichever gets there first, the other sees a live busco (IN-FLIGHT) or a summary (SKIP).
W=/home/$USER/busco_assembly
OUT=/mnt/sdb/busco_assembly_out
export PATH=/home/$USER/.local/share/mamba/envs/busco6/bin:$PATH
export BUSCO_LINEAGE_SETS="$W/lineages"
# same BLAS pinning as run_le.sh: this env's numpy is OpenBLAS and would spawn a thread per core
export OMP_NUM_THREADS=1 OPENBLAS_NUM_THREADS=1 MKL_NUM_THREADS=1 NUMEXPR_NUM_THREADS=1 GOTO_NUM_THREADS=1
LD=$W/lineages/cnidaria_odb12
SHORT="ALIUI AIDSS PACUT PCOMP PXISH"
cd "$W" || exit 1

for abbr in $SHORT; do
  tag="${abbr}__cnidaria_odb12"
  ls "$OUT/$tag"/short_summary*.json >/dev/null 2>&1 && { echo "SKIP $tag"; continue; }
  pgrep -f -- "busco -i .* -o ${tag} " >/dev/null 2>&1 && { echo "IN-FLIGHT $tag"; continue; }
  rm -rf "$OUT/$tag"
  echo "=== $(date '+%F %T') START $tag"
  (
    busco -i "$W/in/$abbr.fna" -m genome --lineage_dataset "$LD" --offline \
          -o "$tag" --out_path "$OUT" -c 16 > "$W/logs/$tag.log" 2>&1
    rc=$?
    echo "=== $(date '+%F %T') END $tag rc=$rc"
    [ $rc -ne 0 ] && tail -3 "$W/logs/$tag.log"
    rm -rf "$OUT/$tag"/*/.bbtools_output "$OUT/$tag"/tmp 2>/dev/null
  ) &
  while [ "$(jobs -rp | wc -l)" -ge 2 ]; do sleep 5; done
done
wait

pending=0
for abbr in $SHORT; do
  if ls "$OUT/${abbr}__cnidaria_odb12"/short_summary*.json >/dev/null 2>&1; then
    echo "OK $abbr"
  else
    echo "PENDING $abbr"; pending=$((pending + 1))
  fi
done
[ "$pending" -eq 0 ] && echo "ALL 5 CNIDARIA RUNS PRESENT $(date '+%F %T')"
exit 0
