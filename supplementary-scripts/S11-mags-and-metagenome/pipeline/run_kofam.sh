#!/bin/bash
# KofamScan over the Acropora cytherea pilot MAG proteins (59,830 sequences).
#
# Why KofamScan and not "KEGG from NCBI": the site's <ABBR>_KEGG tables were built
# with KofamScan, and the KEGG口径 (Abbreviation/Enzymes/Enzyme_ID/Pathway/
# Pathway_ID) was reverse-engineered from them -- see load_kofam.php. Reusing
# KofamScan keeps the two halves consistent.
#
# What it actually runs: KofamScan splits the profile set into individual HMMs and
# runs ONE hmmsearch per KO over the whole query, -T0 (report everything) with GNU
# parallel across --cpu workers; it then applies each KO's own score threshold from
# ko_list. That is 27,864 hmmsearch runs, so the cost scales with
# (n_profiles x n_residues) -- the protein set is 23 MB / ~21 M residues, so this
# is the single most expensive step in the pipeline.
#
# --tmp-dir is /tmp on purpose: /tmp is on the NVMe root SSD (1.1 T free), while
# the kofam DB and the rest of the pipeline live on /mnt/sda1, an HDD array that is
# 87% full and sits at high iowait. KofamScan writes 27,864 small tabular files.
#
# nice -n 10 because this box also serves the live site (mysqld regularly sits at
# 350% CPU) on the same 20 cores.
#
# Output format: `mapper` is gene<TAB>KO and, unlike `detail`, reports unannotated
# sequences by default -- so the output doubles as proof that every input protein
# was actually seen (load_kofam.php counts them).
#
# Usage: run_kofam.sh [cpu]        (default 8)
set -u
# exec_annotation is `#!/usr/bin/env ruby` and shells out to hmmsearch/parallel,
# all of which live in this conda env -- without this PATH it dies with
# "/usr/bin/env: 'ruby': No such file or directory" (exit 127).
export PATH=/home/jackie/miniconda3/envs/magkofam/bin:$PATH

CPU=${1:-8}
BASE=/mnt/sda/jackie/tools/mag_pipeline/work
K=/mnt/sda/jackie/tools/kofam-db
IN="$BASE/pilot_all.faa"
OUT="$BASE/pilot_kofam.tsv"      # persistent result, on /mnt/sda
TMP=/tmp/kofam_tmp               # NVMe scratch, matches the rationale above
LOG="$BASE/kofam_run.log"

mkdir -p "$TMP"
{
  echo "=== KofamScan start $(date '+%F %T')  cpu=$CPU ==="
  echo "input: $IN  ($(grep -c '^>' "$IN") sequences, $(stat -c %s "$IN") bytes)"
  echo "profiles: $(ls "$K"/profiles/*.hmm | wc -l) HMMs"
} >>"$LOG"

nice -n 10 /home/jackie/miniconda3/envs/magkofam/bin/exec_annotation \
  -f mapper \
  -o "$OUT" \
  -p "$K/profiles" \
  -k "$K/ko_list" \
  --cpu "$CPU" \
  --tmp-dir "$TMP" \
  "$IN" >>"$LOG" 2>&1
rc=$?

{
  echo "=== KofamScan exit=$rc  $(date '+%F %T') ==="
  [ -f "$OUT" ] && echo "output: $OUT  $(wc -l < "$OUT") lines"
} >>"$LOG"
exit $rc
