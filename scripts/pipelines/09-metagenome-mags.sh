#!/bin/sh
# CnidoSite — metagenome-assembled genomes (MAGs)
#
# The site serves 315 MAG rows (308 distinct assemblies). Only the **annotation** half of
# the pipeline is recoverable; assembly and binning happened elsewhere and left no commands
# on this server. See TO-BE-SUPPLIED.md items 14-16.

# ===========================================================================
# 0. What is NOT here
# ===========================================================================
# The following binaries do not exist anywhere on this machine, so no run of them can be
# evidenced here: megahit, metawrap, spades, metabat, concoct, maxbin, checkm, gtdbtk.
# `prokka 1.15.6` and `prodigal 2.6.3` are installed in the base environment but there is
# no project run recorded for either.

# ===========================================================================
# 1. KofamScan — [RUN]
# ===========================================================================
# Source: /mnt/sda/jackie/tools/mag_pipeline/run_kofam.sh:49-56
# Environment: `magkofam`
exec_annotation -f mapper -o "${OUT}" -p "$K/profiles" -k "$K/ko_list" \
    --cpu "$CPU" --tmp-dir "$TMP" "$IN"
# K number assignment is loaded into the site by load_kofam.php.

# ===========================================================================
# 2. InterProScan — [RUN]
# ===========================================================================
# Version: InterProScan 5.78-109.0
# Source: /mnt/sda/jackie/tools/mag_pipeline/run_iprscan_chunked.sh:67-68
interproscan.sh -i "$f" -f TSV -o "$out" -iprlookup -goterms -dp -cpu "$CPU"
# The driver splits the input into 10 chunks and runs them serially, so an interrupted run
# resumes by re-running the same command (already-complete chunks are skipped).
#
# THREE FAILURE MODES, all silent, all learned the hard way. Re-running this needs all three:
#   1. `-iprlookup -goterms -dp` — omitting any one of the three means **zero IPR entries are
#      loaded and no error is raised**;
#   2. the GO column (field 14) of the TSV carries a suffix that must be stripped; if it is
#      not, **zero GO entries are loaded**, again silently;
#   3. `interproscan.properties` is not a reliable guide to which analyses will run —
#      the site's dictionary build must pin InterPro **release 109** explicitly and must not
#      use `current_release`. `go_term.tsv` must include obsolete terms and alt_ids, or the
#      dictionary silently gains blanks (which render as empty columns, not as errors).
#
# Loading (both take --apply to actually write):
#   php load_iprscan.php / php load_kofam.php    # plus load_pilot.sh, 14_load_cytherea.sh
# Other pipelines in the same directory: 01-preprocess, Binning (unrecorded), and
# blastp/diamond steps used for the taxonomy rule.

# ===========================================================================
# 3. MAG quality (CheckM2) — [GAP]
# ===========================================================================
# The site's MAG pages show completeness and contamination. The only trace of how they were
# computed is a header comment in fill_mags_checkm.sql:4 recording
#     CheckM2 1.1.0, DIAMOND database uniref100.KO.1.dmnd
# — but the file itself contains only UPDATE statements. **The CheckM2 invocation is not on
# this server.** CheckM2 is not installed here either. [GAP]

# ===========================================================================
# 4. MAG taxonomy (GTDB-Tk) — [GAP]
# ===========================================================================
# Not installed, no run recorded. [GAP]

# ============================================================================
# 5. Metagenome reads — [NOT AN ANALYSIS DIRECTORY]
# ===========================================================================
# /mnt/sda/jackie/cnidaria_omics/metaG/ is a **download directory**, not an analysis tree:
# 1,582 entries, 1,580 accession directories, all `.fastq.gz`. `1.sh` and
# `aspera_bulk_download.sh` are Aspera command generators; `run`, `run1`, `run2` are
# accession lists. The only environment record is a qiime2 yml
# (`qiime2-amplicon-ubuntu-latest-conda.yml:443`, qiime2=2025.10.1, 16S amplicon) with no
# command attached to it. `cnidaria_omics/others/` is likewise a download directory
# (380 entries: 378 SRA directories plus an Aspera generator).

# ===========================================================================
# 6. Host resolution — the authoritative rule (site side, not a pipeline)
# ===========================================================================
# A MAG's host species is resolved through the coverage matrix function
# cnido_spcov_resolve, **not** through the `abbr` column. NCBI has annotated only 141 of the
# 315 MAGs; the rest carry annotations produced here.
#
# Counting caution: 7 `GCF_` rows in MAGs.php are RefSeq twins of `GCA_` rows with identical
# numbers — the same assembly under two accessions. The table holds 315 rows; the true count
# is 308. The site de-duplicates at the display layer only.
