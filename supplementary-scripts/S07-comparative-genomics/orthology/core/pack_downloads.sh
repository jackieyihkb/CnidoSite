#!/bin/bash
# Stage the CCO downloadable files under download/, ready to rsync to the server.
#
# Nothing here is generated -- every file is a copy or a re-compression of an artefact
# that build_*.py already produced, so the downloads can never disagree with the tables
# the page reads.  Names match the hrefs in index_template.php; if you rename one, rename
# it there too.
#
# The supermatrices ship as tar.gz because each is useless without its partition file:
# a concatenated alignment whose columns are in orthogroup order is only interpretable
# alongside the ranges.
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"
OUT="$DIR/download"
rm -rf "$OUT"
mkdir -p "$OUT"

echo "== matrices and tables"
gzip -9 -c matrix_presence.tsv   > "$OUT/matrix_presence.tsv.gz"
gzip -9 -c matrix_copynumber.tsv > "$OUT/matrix_copynumber.tsv.gz"
gzip -9 -c matrix_singlecopy.tsv > "$OUT/matrix_singlecopy.tsv.gz"
gzip -9 -c core_og.tsv           > "$OUT/core_og.tsv.gz"
gzip -9 -c core_members.tsv      > "$OUT/core_members.tsv.gz"
gzip -9 -c og_scores.tsv         > "$OUT/og_scores.tsv.gz"
gzip -9 -c species.tsv           > "$OUT/species.tsv.gz"

echo "== sequences"
gzip -9 -c CCO_members.faa       > "$OUT/CCO_members.faa.gz"

echo "== supermatrices"
for t in strict core extended; do
  tar czf "$OUT/supermatrix_$t.tar.gz" -C supermatrix "$t.faa" "$t.partitions.txt"
done

# Dimensions of each supermatrix, so the page can state them without anyone
# hardcoding numbers that then drift.  The taxon counts are NOT nested across
# tiers and that surprises people -- see the note in index_template.php.
{
  echo "{"
  first=1
  for t in strict core extended; do
    taxa=$(grep -c '^>' "supermatrix/$t.faa")
    parts=$(grep -c . "supermatrix/$t.partitions.txt")
    cols=$(awk -F'=' '/=/{split($2,a,"-"); v=a[2]} END{print v}' "supermatrix/$t.partitions.txt")
    [ $first -eq 0 ] && echo ","
    first=0
    printf '  "%s": {"taxa": %s, "partitions": %s, "columns": %s}' "$t" "$taxa" "$parts" "$cols"
  done
  echo
  echo "}"
} > "$OUT/dimensions.json"
echo "   dimensions.json: $(tr -d '\n ' < "$OUT/dimensions.json")"

# FastTree is slow on the extended tier and may still be running; ship whichever
# trees are finished.  index_template.php checks for these with is_file(), so a
# tree that appears later shows up on the page with no edit.
echo "== sanity-check trees"
for t in strict core extended; do
  if [ -s "supermatrix/$t.tree" ]; then
    cp "supermatrix/$t.tree" "$OUT/$t.tree"
    echo "   $t.tree ($(wc -c < supermatrix/$t.tree) bytes)"
  else
    echo "   $t.tree -- not ready, skipped"
  fi
done

echo
echo "== staged in download/"
ls -lh "$OUT" | awk 'NR>1{printf "   %-32s %s\n", $9, $5}'
echo "   total: $(du -sh "$OUT" | cut -f1)"

# Sanity: every href the page advertises must exist, and nothing stale may linger.
echo
MISS=0
for f in $(grep -o '/core/download/[A-Za-z0-9_.]*' index_template.php | sed 's|/core/download/||' | sort -u); do
  if [ ! -s "$OUT/$f" ]; then echo "   MISSING: $f"; MISS=1; fi
done
[ "$MISS" -eq 0 ] && echo "== every advertised download is present"
exit $MISS
