#!/bin/bash
# Four pilot runs to measure wall time and peak RSS before scaling the real batch:
#   AAWI (median genome, 414 MB) and Kudoa_iwatai (30 MB), each against both lineages.
# Same command line the batch uses.
export PATH=/home/$USER/.local/share/mamba/envs/busco6/bin:$PATH
W=/home/$USER/busco_assembly
OUT=/mnt/sdb/busco_assembly_out
cd "$W" || exit 1

one() {
  abbr=$1; lin=$2; ld=$3
  tag="${abbr}__${lin}"
  [ -f "$OUT/$tag"/short_summary*.json ] && { echo "SKIP $tag"; return; }
  rm -rf "$OUT/$tag"
  s=$(date +%s)
  echo "=== $(date '+%F %T') START $tag"
  busco -i "$W/in/$abbr.fna" -m genome --lineage_dataset "$ld" --offline \
        -o "$tag" --out_path "$OUT" -c 4 > "$W/logs/$tag.log" 2>&1
  rc=$?
  echo "=== $(date '+%F %T') END $tag rc=$rc elapsed=$(( $(date +%s) - s ))s"
  rm -rf "$OUT/$tag"/*/.bbtools_output "$OUT/$tag"/tmp 2>/dev/null
}
export -f one; export W OUT

one AAWI           cnidaria_odb12  "$W/lineages/cnidaria_odb12"  &
one AAWI           metazoa_odb12.2 "$W/lineages/metazoa_odb12.2" &
one Kudoa_iwatai   cnidaria_odb12  "$W/lineages/cnidaria_odb12"  &
one Kudoa_iwatai   metazoa_odb12.2 "$W/lineages/metazoa_odb12.2" &
wait
echo "PILOT DONE $(date '+%F %T')"
