#!/bin/bash
# Gzip the OrthoFinder alignments for our families, ready to upload.
#
# The corpus is Results_Sep14/MultipleSequenceAlignments/OG*.fa: 6.1 GB of FASTA over
# 115,762 files, of which the 67,795 in our family list are ours.  Raw that does not fit
# anywhere -- the server's root filesystem has 45 GB free and the site's own data/ tree is
# already 53 GB -- but gzipped it comes to roughly 0.85 GB, which does.
#
# Where these end up: /var/www/html/CnidoSite/data/og_aln/.  Not download/: that directory
# is flat and served byte-for-byte by download_fun.php, which only ever hands out a
# top-level name, so 67,795 files in it would be both a directory-listing hazard and
# unreachable.  data/ is already `Require all denied` and holds the source .pep files, and
# the viewer streams out of it through ?dl=aln / ?dl=seq, which is the same shape as the
# existing ?download=full endpoint.
#
#   bash pack_aln.sh      # ~20 minutes, then rsync ./aln to data/og_aln/
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
OF=/mnt/sda/jackie/cnidaria/0.tree/0.peps/OrthoFinder
MSA="$OF/Results_Sep14/MultipleSequenceAlignments"
OUT="$DIR/aln"
JOBS=24          # the box has 768 cores; 24 gzips is a rounding error on its load

mkdir -p "$OUT"
: > "$DIR/aln_missing.txt"

# -6 rather than -9: on FASTA the extra level buys about 2% for several times the CPU, and
# there are 67,795 files to get through.
#
# Each file is packed by its own process, so parallelism cannot corrupt anything -- one
# gzip writes exactly one output file.  (This is emphatically not true of the parallel
# grep this project tried once for header extraction; see parse_headers2.py.)
export MSA OUT
xargs -a "$DIR/all_fams.txt" -P "$JOBS" -n 1 -I{} sh -c '
  src="$MSA/$1.fa"
  if [ -s "$src" ]; then
    gzip -6 -c "$src" > "$OUT/$1.aln.fa.gz"
  else
    echo "$1" >> "'"$DIR"'/aln_missing.txt"      # nothing to pack; the page says so
  fi' _ {}

have=$(ls "$OUT" | wc -l)
miss=$(wc -l < "$DIR/aln_missing.txt")
size=$(du -sm "$OUT" | cut -f1)
echo "packed      : $have files, ${size} MB"
echo "no MSA      : $miss families"
# A short run means the loop died partway (full disk, interrupted ssh); uploading a partial
# corpus would leave families whose download link 404s on the live site.
if [ "$have" -lt 67000 ]; then
  echo "ABORT: only $have of ~67795 alignments packed -- do not upload this." >&2
  exit 1
fi
echo "ok -- rsync $OUT to the server's CnidoSite/data/og_aln/"
