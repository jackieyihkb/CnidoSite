#!/bin/sh
# CnidoSite — building and refreshing the database
#
# This is the "script that can be used to create the database locally" the reviewer asked
# for. It has two halves: the CLI jobs that load curated data into MySQL, and the
# web-serving side that reads it back out.
#
# Every PHP command below is run with the **Apache** php.ini, not the CLI one — the CLI
# php.ini on the production host is broken, and several of these scripts depend on
# extensions (mysqli, mbstring, gd) that are only configured there:
#
#   PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d \
#     php -c /etc/php/7.4/apache2/php.ini <script>
#
# The credentials come from the environment (see docs/DEPLOYMENT.md), so export the four
# CNIDO_DB_* variables before running any of these.

PHP="PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d php -c /etc/php/7.4/apache2/php.ini"

# ===========================================================================
# 0. A note on the ordering and on `tmp/`
# ===========================================================================
# Several of these jobs write into the database and several of them write JSON caches into
# site/tmp/, which is the only directory the web user can write and the only place CLI and
# web share state. **After rebuilding a cache-backed table, delete the corresponding file
# in tmp/**, or the pages keep serving the old numbers. `tmp/coverage_cache.json` is the
# usual culprit; it is invalidated whenever a table is added, dropped or renamed, because
# the coverage matrix caches the *set of tables per species*.
#
# The refresh jobs are idempotent and every one of them accepts a read-only `--check`.

# ===========================================================================
# 1. Core refresh jobs (site/includes/)
# ===========================================================================
# Statistics cache for the data_statistics.php overview cards. Scheduled in production at
# 17:03 daily:
$PHP /var/www/html/CnidoSite/includes/stats_refresh.php
$PHP /var/www/html/CnidoSite/includes/stats_refresh.php --check

# Genome assembly inventory, pulled per species from the NCBI Datasets API. Modes:
# --init / --commit / --refresh, plus --limit=N and --taxid=NNN for debugging.
$PHP /var/www/html/CnidoSite/includes/genome_assembly_refresh.php
$PHP /var/www/html/CnidoSite/includes/genome_assembly_refresh.php --limit=5

# Gene <-> literature relations, from NCBI gene2pubmed.
$PHP /var/www/html/CnidoSite/includes/gene_literature_refresh.php

# Backfill the site's own gene names onto the gene_literature table (the NCBI `symbol`
# column is not the name the site displays; the mapping is defined in
# includes/gene_name_map.php).
$PHP /var/www/html/CnidoSite/includes/gene_name_backfill.php

# RNA-seq sample metadata index -> data/rnaseq_samples.json, consumed by the expression
# panel on gene_detail.php. Re-run after adding any <ABBR>_TPM table.
$PHP /var/www/html/CnidoSite/includes/rnaseq_meta_refresh.php

# Domain-search vocabulary, which narrows a search across 148 annotation tables to the few
# that actually contain the term. `--db=ipr` selects one vocabulary.
$PHP /var/www/html/CnidoSite/includes/ds_vocab_refresh.php
$PHP /var/www/html/CnidoSite/includes/ds_vocab_refresh.php --check

# Single-cell gene-ID sidecar (see 07-single-cell.md section 5).
$PHP /var/www/html/CnidoSite/includes/sc_gene_ids_build.php

# ===========================================================================
# 2. One-off builders (/var/www/cnidosite-tools/src/)
# ===========================================================================
# These built the tables that are not refreshed on a schedule. Each is a standalone CLI
# script; several have their own --check.
$PHP /var/www/cnidosite-tools/src/build_epigenome_samples.php        # epigenome sample inventory
$PHP /var/www/cnidosite-tools/src/build_transcriptome_index_stats.php
$PHP /var/www/cnidosite-tools/src/build_download_static_rows.php     # download.php section rows
$PHP /var/www/cnidosite-tools/src/build_og_family_term_low.php       # weak-evidence gene-family consensus
$PHP /var/www/cnidosite-tools/src/build_phenotype_v2.php             # phenotype tables (rebuilt)
$PHP /var/www/cnidosite-tools/src/build_phenotype_refs.php
$PHP /var/www/cnidosite-tools/src/build_pheno_standard_ids.php
$PHP /var/www/cnidosite-tools/src/fetch_worms_traits.php             # WoRMS traits
$PHP /var/www/cnidosite-tools/src/fill_worms_category.php
$PHP /var/www/cnidosite-tools/src/octd_unused_columns.php            # OCTD column audit

# `/var/www/cnidosite-tools/src/` also contains the page-splice helpers
# (splice_download_*.php) and a Python fixing/audit toolbox (unify_*.py, fix_*.py,
# audit_*.py, check_links.py, fontscale.py, shot_pages.py). Those operate on the source
# tree, not on the database; they are not part of rebuilding a copy of the resource.

# ===========================================================================
# 3. Running SQL from the command line
# ===========================================================================
# There is no shared connection helper in the site: each page constructs its own mysqli.
# For ad-hoc queries, dbq.php reads the connection parameters out of the environment — or,
# failing that, out of includes/state.php at runtime — and never prints the password.
# The archived copy of that helper is scripts/setup/dbq.php; the path below is the one it
# is invoked by on the production host:
$PHP /var/www/cnidosite-tools/src/dbq.php "SELECT COUNT(*) FROM trans_assembly"
# in this repository:  php scripts/setup/dbq.php "SELECT COUNT(*) FROM trans_assembly"

# ===========================================================================
# 4. Schema and dump
# ===========================================================================
# The schema shipped in this repository was produced with:
mysqldump --no-data --skip-comments --skip-add-drop-table --single-transaction \
    --routines --triggers --events cnidaria > schema/cnidaria-schema.sql
# 1,541 CREATE TABLE statements. The DEFINER clauses that --routines/--triggers/--events add
# to the two views were rewritten to DEFINER=CURRENT_USER so the dump carries no account
# name; it also means the views are created under whatever user you import as.
#
# A full data dump is deliberately NOT in this repository: ~178 GB. To rebuild, import the
# schema and then reload the curated tables; the large per-species annotation dumps are
# rebuildable intermediates, not inputs (see docs/DATA_OVERVIEW.md).

# ===========================================================================
# 5. Operational rules learned the hard way
# ===========================================================================
# · **Create tables with an explicit COLLATE.** MySQL 8 defaults a bare
#   `CHARACTER SET utf8mb4` to utf8mb4_0900_ai_ci, which cannot be joined against the
#   utf8mb4_unicode_ci `og_*` and `core_*` tables. The error is "Illegal mix of collations",
#   and any `2>/dev/null` in the calling code turns it into a silent empty result set.
# · **Add indexes one ALTER at a time.** Three concurrent ALTERs on the same table took
#   109 s; run serially the same change took 4.2 s.
# · **Gene-ID columns are TEXT and need prefix indexes** — `(geneA(64))`. `data_length` in
#   information_schema is a stale estimate and cannot be used to judge index coverage.
# · **Never name a new table `<something>_<known-suffix>`** unless it really is that
#   species' table: the site's coverage matrix classifies tables by suffix, so a stray name
#   is silently attributed to a species. Delete tmp/coverage_cache.json afterwards.
# · **Newly ingested BUSCO data must be folded per species** before it is displayed; the
#   `busco` table is one row per (species, BUSCO_ID).
