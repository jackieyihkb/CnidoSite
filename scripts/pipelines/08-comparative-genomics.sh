#!/bin/sh
# CnidoSite — comparative genomics: orthology, species tree, gene trees, synteny
#
# ===========================================================================
# READ THIS FIRST
# ===========================================================================
# This is the weakest module for reproducibility, and it is worth being explicit about why
# rather than presenting the methods as if command lines existed.
#
# The trees, the orthogroups and the synteny maps were **built on another machine**. The
# evidence for that is negative but conclusive, and it was checked directly:
#
#   find /mnt/sda/jackie -maxdepth 4 \( -iname '*.iqtree' -o -iname '*.treefile' \
#        -o -iname '*.contree' -o -iname '*aln*.fa*' -o -iname '*.phy' -o -iname '*.nex' \)
#     -> zero results
#
#   · no OrthoFinder binary exists anywhere on this server;
#   · the only IQ-TREE binary is v3.1.3, inside an unrelated conda environment;
#   · the only phylogenetics command in ~/.bash_history is a re-rooting step,
#       python3 tools/reroot.py new.nwk data/cnidaria.nwk --outgroup-file og.txt --ladderize
#     and og.txt no longer exists;
#   · no R script, Snakemake file, or macrosyntR / Pansyn invocation exists for the
#     synteny module — only the viewer pages.
#
# The response letter says the same thing (Part C items 18(iv)/(v) and item 20), and
# marks these as the largest single gap in the reproducibility package. What follows is
# therefore split into: the parts that ARE recoverable, and the parts that need the
# authors (TO-BE-SUPPLIED.md items 1, 9, 10, 11, 12).

# ===========================================================================
# 1. OrthoFinder — [PROSE]
# ===========================================================================
# Site statement: OrthoFinder v2.5.5 (data_statistics.php, tools table).
# Letter statement (Response_to_Reviewers_r1.6, species-tree passage): 148 Cnidaria plus
# 5 outgroups, 100% occupancy required, the longest sequence per species retained for each
# orthogroup, yielding 70 orthogroups for the species tree.
# No command, no input file, no output directory on this server. [GAP]

# ===========================================================================
# 2. Species tree — [PROSE], the recipe as given in the response letter
# ===========================================================================
# The letter gives the IQ-TREE invocations verbatim. They are recorded here as the authors'
# statement, NOT as recovered commands — the 70-OG / 24,975-site supermatrix is not on this
# server either (the letter concedes this).
#
# Upstream chain as described: OrthoFinder v2.5.5 -> MAFFT v7.526 `--anysymbol`
#   -> BMGE v1.12 -> AMAS -> supermatrix of 24,975 sites -> IQ-TREE v3.1.2
#   (the letter says v3.1.2; the binary on this server is v3.1.3 — version to confirm)
#
# Partitioned model selection:
iqtree3 -s supermatrix.phy -st AA -p partition.nex -m MFP -bb 1000 --alrt 1000 --bnni
# PMSF heterogeneous model: build a guide tree under LG+G4 first, then
iqtree3 -s supermatrix.phy -st AA -m LG+C60+G -ft guide.treefile -bb 1000 --alrt 1000 --bnni
#
# Which of the two produced the published tree is not stated. [GAP]
# Tip count is 153 = 148 Cnidaria + 5 outgroups. Caution for anyone re-deriving it:
# Trachythela is not an outgroup.

# ===========================================================================
# 3. Core ortholog resource (/core/) — [PROSE]
# ===========================================================================
# Derived from the same OrthoFinder run under different thresholds, over the 60 genomes in
# `core_species.busco90`. Three tiers are served: 14 / 210 / 793 orthogroups (1,017 total).
# The tier trees are FastTree with `-nosupport` (fasttree 2.1.11 and 2.2.0 are both
# installed here).
#
# The script that exists locally, cco_stage/import_core.sh, is a **loading** script
# (LOAD DATA into the core_* tables). It contains no OrthoFinder or FastTree command. [GAP]
#
# Caution when reconciling counts: three different species sets circulate and they are not
# interchangeable — the 148 annotated species, the 60 in core_species.busco90, and the 15
# in the high_quality set. docs/DATA_OVERVIEW.md tabulates them.

# ===========================================================================
# 4. Gene trees (/genetree/) — [PROSE]
# ===========================================================================
# 67,795 gene families are served. Per the letter, alignments come from OrthoFinder's own
# MAFFT calls and 22,281 families have unrooted FastTree topologies.
# **No tree-building command is recorded on this server.** [GAP]

# ===========================================================================
# 5. Macrosynteny / microsynteny — [PROSE]
# ===========================================================================
# Software: macrosyntR (macrosynteny) and Pansyn (microsynteny).
# The data are **in a different database (`jackie_db`), not in `cnidaria`.**
#
# Method parameters, from the site pages and the letter — these are descriptions, not
# commands:
#   · Fisher exact test per chromosome pair, Benjamini-Hochberg corrected, q < 0.001;
#   · a minimum of 30 anchors per pair;
#   · linkage groups merged with igraph::cluster_fast_greedy.
#
# When summarising the result, note that the within-class correlation is reported as the
# **median** |rho| (0.835 within class vs 0.238 between classes). AVG() gives the wrong
# answer (0.689 / 0.301) and MySQL 8 has no PERCENTILE_CONT. [GAP for the commands]

# ===========================================================================
# 6. Divergence-time dating — [GAP]
# ===========================================================================
# Fossil calibrations are recorded (see 01-genome-assembly.sh section 9), but the dating
# software is never named. [GAP]
