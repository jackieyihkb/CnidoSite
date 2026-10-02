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
# The batch run over all 148 annotated species — [GAP]
# ---------------------------------------------------------------------------
# The site reports BUSCO for 148 species, but **no batch command exists on this server**.
# Only the two single-species runs above are recorded. See TO-BE-SUPPLIED.md item 3.
#
# There is a second, unresolved discrepancy in the same area: the site's tools table says
# the lineage is `cnidaria_odb12`, while the response letter (Response_to_Reviewers_r1.6,
# BUSCO passage) says `metazoa_odb12`. Both lineage directories are installed, so both are
# possible; only the authors can say which produced the published numbers.
#
# Third point to settle at the same time: `busco --download` is currently broken on this
# host, so re-running requires the lineage directory to be supplied from
# /home/jackie/busco_downloads/ rather than fetched.

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
