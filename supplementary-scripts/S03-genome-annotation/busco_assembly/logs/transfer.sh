#!/bin/bash
# Pull everything the assembly-mode BUSCO batch needs from the site host.
# Read-only on the site host: nothing there is modified.
set -uo pipefail
SRC="<SITE-ACCOUNT>@<SITE-HOST>:/home/jackie/cnidosite-work/busco"
W=/home/$USER/busco_assembly
cd "$W" || exit 1
mkdir -p "$W/in" "$W/out" "$W/logs" "$W/lineages"

echo "=== start $(date '+%F %T')"

# 1. input assemblies (166 GB) -- four parallel streams over disjoint file lists
ssh "<SITE-ACCOUNT>@<SITE-HOST>" 'ls /home/jackie/cnidosite-work/busco/assembly/in/*.fna' \
  | sed 's#.*/##' > logs/fna.list
echo "files to fetch: $(wc -l < logs/fna.list)"
split -n l/4 -d logs/fna.list logs/fna.part.
for p in logs/fna.part.*; do
  rsync -a --files-from="$p" "$SRC/assembly/in/" "$W/in/" \
    > "logs/rsync.$(basename "$p").log" 2>&1 &
done
wait
echo "=== assemblies fetched: $(ls "$W"/in/*.fna 2>/dev/null | wc -l) $(date '+%F %T')"

# 2. lineages (both are already unpacked on the site host; ~2 GB)
rsync -a "$SRC/lineages/lineages/cnidaria_odb12/" "$W/lineages/cnidaria_odb12/"
rsync -a "$SRC/lineage_check/dl/lineages/metazoa_odb12.2/" "$W/lineages/metazoa_odb12.2/"
echo "=== lineages fetched $(date '+%F %T')"

# 3. harness inputs: manifest + whatever BUSCO has already finished there,
#    so finished work is never re-run (run_genome.sh skips a tag with a summary)
rsync -a "$SRC/assembly/manifest.tsv" "$W/"
mkdir -p "$W/out"
rsync -a --include='*/' --include='short_summary*.json' --exclude='*' \
  "$SRC/assembly/out/" "$W/out/"
echo "=== seeded finished runs: $(ls "$W"/out/*/short_summary*.json 2>/dev/null | wc -l)"

echo "=== done $(date '+%F %T')"
