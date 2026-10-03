#!/bin/bash
# InterProScan 5.78-109.0 over the Acropora cytherea pilot MAG proteins.
#
# Flags that are NOT optional (all three were nearly missed):
#   -iprlookup   without it TSV column 12/13 (InterPro accession / description)
#                is empty -- you get member-DB matches with no InterPro entries,
#                and load_iprscan.php would store nothing at all.
#   -goterms     supplies column 14 (GO). It IMPLIES -iprlookup.
#   -dp          disable the precalculated-match lookup service, i.e. run every
#                analysis locally against the 20 GB of models we installed. Left
#                off, InterProScan calls EBI for precomputed matches, which is a
#                network dependency we cannot use for 59,830 proteins.
#   -f TSV       the default is TSV,XML,GFF3; XML for 60k proteins is enormous.
#
# Analyses: default is ALL. Everything that does not map to an InterPro entry
# (Coils, Phobius, SignalP, TMHMM, MobiDBLite, ...) contributes rows whose
# columns 12-14 are '-', which load_iprscan.php drops -- so running them costs
# time without changing the database. Restricting via the optional 2nd argument
# is possible, but only to the IPR-contributing set, or IPR/GO coverage drops.
#
# Heap: the shipped interproscan.sh hardcoded -Xmx15G; lowered to 8G in place
# (see the comment there) because this box also serves the live site.
#
# Usage: run_iprscan.sh [cpu] [appl]
#        run_iprscan.sh 6                     # all analyses
#        run_iprscan.sh 6 Pfam,Gene3D,...     # restricted set
set -u
CPU=${1:-8}
APPL=${2:-}

BASE=/mnt/sda/jackie/tools/mag_pipeline/work
IPR=/mnt/sda/jackie/tools/iprscan/interproscan-5.78-109.0
IN="$BASE/pilot_all.faa"
OUT="$BASE/pilot_iprscan.tsv"
LOG="$BASE/iprscan_run.log"

# interproscan.sh resolves java via `type -p java` and requires >= 11.
export PATH=/home/jackie/miniconda3/bin:$PATH

ARGS=(-i "$IN" -f TSV -o "$OUT" -iprlookup -goterms -dp -cpu "$CPU")
[ -n "$APPL" ] && ARGS+=(-appl "$APPL")

{
  echo "=== InterProScan start $(date '+%F %T')  cpu=$CPU  appl=${APPL:-ALL} ==="
  echo "input: $IN ($(grep -c '^>' "$IN") sequences)"
  echo "java: $(java -version 2>&1 | head -1)"
} >>"$LOG"

nice -n 10 "$IPR/interproscan.sh" "${ARGS[@]}" >>"$LOG" 2>&1
rc=$?

{
  echo "=== InterProScan exit=$rc  $(date '+%F %T') ==="
  [ -f "$OUT" ] && echo "output: $OUT  $(wc -l < "$OUT") lines"
} >>"$LOG"
exit $rc
