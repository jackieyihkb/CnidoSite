#!/bin/bash
# Push finished BUSCO runs back to the site host and let its own collector write
# them into busco_assembly.
#
# Only short_summary*.json travels: that is all collect_genome.py reads, and it is
# what genomeinfo.php's two BUSCO columns are built from.  The site host keeps the
# authoritative manifest.tsv / collect_genome.py / genome_rows.sql, so the table is
# written by exactly the code path that already exists -- nothing about the format
# or the upsert changes.
#
# Safe to run repeatedly while the batch is still going: each pass re-collects
# whatever has finished and upserts (INSERT ... ON DUPLICATE KEY UPDATE).
set -uo pipefail
W=/home/$USER/busco_assembly
OUT=/mnt/sdb/busco_assembly_out
SITE="<SITE-ACCOUNT>@<SITE-HOST>:/home/jackie/cnidosite-work/busco/assembly"

echo "=== $(date '+%F %T') sync $(ls "$OUT"/*/short_summary*.json 2>/dev/null | wc -l) summaries"
rsync -a --include='*/' --include='short_summary*.json' --exclude='*' \
  "$OUT/" "$SITE/out/" || exit 1

ssh "<SITE-ACCOUNT>@<SITE-HOST>" 'cd /home/jackie/cnidosite-work/busco/assembly || exit 1
  python3 collect_genome.py
  mysql -ujackie -p<REDACTED> cnidaria < genome_rows.sql 2>&1 | grep -v "Using a password"
  echo "--- row counts now:"
  mysql -ujackie -p<REDACTED> cnidaria -N -e "SELECT lineage, COUNT(*) FROM busco_assembly GROUP BY lineage;" 2>/dev/null
  echo "--- high_quality:"
  mysql -ujackie -p<REDACTED> cnidaria -N -e "SELECT COUNT(*) FROM busco_assembly WHERE high_quality=1;" 2>/dev/null'
echo "=== done $(date '+%F %T')"
