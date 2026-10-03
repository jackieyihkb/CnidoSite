#!/bin/sh
# CnidoSite — BUSCO completeness assessment
#
# Site statement (data_statistics.php:531):
#   BUSCO v5.8.2 and v6.0.0, `-l cnidaria_odb12 -m proteins`; for the one species that
#   has a genome but no annotation, `-l cnidaria_odb12 -m genome --miniprot`.
#
# Lineage datasets, from /home/jackie/busco_downloads/file_versions.tsv:
#   cnidaria_odb12   2026-05-22
#   metazoa_odb12    2026-05-22
#
# ---------------------------------------------------------------------------
# Recovered single-species runs — [RUN]
# ---------------------------------------------------------------------------
# (a) Genome mode. Source: /mnt/sda/jackie/cnidosite-work/pmult/busco.log
#     The command line itself was not echoed; the parameters are fully determined by the
#     log header:
#       busco.log:1  ***** Start a BUSCO v6.0.0 analysis
#       busco.log:3  Running genome mode
#       busco.log:6  Using local lineages directory .../cnidaria_odb12
#       busco.log:8  Running BUSCO using lineage dataset cnidaria_odb12 (eukaryota, 2026-05-22)
#     Input: pmult.fna.gz.   Loaded into the site by: php import_pmult_busco.php

# (b) Genome mode, from the assembly notebook (assembly.sh:89-91):
busco --list-datasets
busco --download cnidaria_odb12
busco -c 120 -i hifiasm_output_contig.fa -l cnidaria_odb12 -o busco_cnidaria_odb12 -m genome

# (c) Protein mode, from the same notebook (assembly.sh:248):
busco -c 120 -i hifiasm_output_contig.fa -l cnidaria_odb12 -o busco_cnidaria_odb12 -m protein
#   CAUTION: line 248 feeds a **genome FASTA** to `-m protein`. That is almost certainly a
#   copy-paste error in the notebook — the argument should be the protein FASTA produced by
#   gffread on line 243. Recorded as found; not silently corrected.

# ---------------------------------------------------------------------------
# The batch runs — [RUN]
# ---------------------------------------------------------------------------
# Completeness is assessed in two independent modes, each with its own batch.

# --- The protein-mode batch, one run per species ---
# Scores every deposited protein set against cnidaria_odb12.
# Source: BUSCO/run_busco.sh:18-32 (loop), :5-13 (config)
for file in ${INPUT_DIR}/*.pep; do
    filename=$(basename "$file" .pep)
    busco -i "$file" -l "$LINEAGE_DB" -o "${filename}_results" \
          -m "$MODE" -c "$THREADS" --out_path "$OUTPUT_DIR"
done
# LINEAGE_DB=cnidaria_odb12  MODE=protein  THREADS=120

# As echoed into one species' own log
# (1.BUSCO/busco_results/Thelohanellus_kitauei_results/logs/busco.log:1):
busco -i ./Thelohanellus_kitauei.pep -l cnidaria_odb12 -o Thelohanellus_kitauei_results \
      -m protein -c 120 --out_path busco_results

# The merged table. Source: BUSCO/merge_busco_results.py:21, :79
python3 merge_busco_results.py     # busco_results/*/run_cnidaria_odb12/full_table.tsv

# --- The genome-mode batch, 650 runs in all (325 assemblies x 2 lineages) ---
# Scores each assembly against both lineages using miniprot-based gene prediction.
# Source: busco_assembly/run_le.sh:127-129
export BUSCO_LINEAGE_SETS=/home/$USER/busco_assembly/lineages
busco -i "$WORK/in/$abbr.fna" -m genome \
      --lineage_dataset "$ld" --offline \
      -o "$tag" --out_path "$OUT" -c "$THREADS"
# $tag = <ABBR>__<lineage>; $OUT = /mnt/sdb/busco_assembly_out; $THREADS = 16

# The dispatcher is idempotent — a tag whose `short_summary*.json` exists, or that a live
# BUSCO process owns, is skipped — so the batch restarts safely. Concurrency was 40 runs x
# 16 threads under a 60 % CPU ceiling.
#
# Two assemblies (ALIUI, AIDSS) arrived with each record on a single unwrapped line, which
# failed BUSCO's reader with a Java heap error; both were re-wrapped without changing a base.
# Source: busco_assembly/logs/run_cnidaria_missing.sh (pre-step)
awk '/^>/{print; next} {for(i=1;i<=length($0);i+=60) print substr($0,i,60)}' in/<ABBR>.fna

# Only `short_summary*.json` is transferred; the table is written on $SITE by the site's own
# collector, which derives `high_quality = 1` when complete >= 90 %, duplicated <= 10 % and
# fragmented <= 5 %. Source: logs/collect_and_publish.sh:19-28
rsync -a --include='*/' --include='short_summary*.json' --exclude='*' \
  /mnt/sdb/busco_assembly_out/ $SITE:/home/$USER/cnidosite-work/busco/assembly/out/
ssh $SITE 'cd /home/$USER/cnidosite-work/busco/assembly || exit 1
  python3 collect_genome.py
  mysql -u$USER -p<REDACTED> cnidaria < genome_rows.sql'
# The upsert is INSERT ... ON DUPLICATE KEY UPDATE on (abbr1, lineage), so repeated passes
# are idempotent. 325 of 326 species carry an assembly and both columns; Coelastrea aspera
# has none in the catalogue or at NCBI, which is why its cells are empty.

# ---------------------------------------------------------------------------
# Points still to settle
# ---------------------------------------------------------------------------
# The site's tools table says the lineage is `cnidaria_odb12`, while the response letter
# (Response_to_Reviewers_r1.6, BUSCO passage) says `metazoa_odb12`. Both lineage
# directories are installed, and the genome-mode batch scores against both, so both are
# possible; only the authors can say which produced the published numbers.
#
# `busco --download` is currently broken on this host, so re-running requires the lineage
# directory to be supplied from /home/jackie/busco_downloads/ rather than fetched.

# ---------------------------------------------------------------------------
# How the site computes completeness from the BUSCO table — [not a pipeline]
# ---------------------------------------------------------------------------
# The stored `busco` table has one row per (species, BUSCO_ID) and cannot be read directly;
# completeness must be folded per species. This affects any re-analysis:
#
#   SELECT species, COUNT(DISTINCT BUSCO_ID) ...
#   ... FROM busco WHERE status = 'Complete' GROUP BY species
#
# cnidaria_odb12 contains 3,203 BUSCOs. See docs/DATA_OVERVIEW.md for the counts the site
# displays and how they reconcile (three different species sets are in play: 148 annotated,
# 149 with BUSCO rows, and the 60-genome `core_species.busco90` set).
