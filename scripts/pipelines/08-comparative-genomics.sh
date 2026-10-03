#!/bin/sh
# CnidoSite — comparative genomics: orthology, species tree, gene trees, core orthologs,
# synteny
#
# Orthology, the species tree, the gene-family pages and the core-ortholog resource all
# rest on one OrthoFinder run over the same 153-proteome input set: the cnidarian genomes
# plus five outgroup species whose FASTA headers carry an `OUT_` prefix. The prefix is
# load-bearing — several steps identify a species by the text before the first underscore.
#
# ===========================================================================
# WHAT IS RECOVERED AND WHAT IS NOT
# ===========================================================================
# Recovered: OrthoFinder, MAFFT, BMGE, the four IQ-TREE runs, the FastTree gene trees, the
# core-ortholog pipeline and its loaders. Scripts are in the supplementary-scripts deposit
# under `S07-comparative-genomics/`.
#
# Gaps: the AMAS concatenation and the construction of the `drop3`/`drop5` alignments; the
# 153-taxon x 24,975-column supermatrix itself; and every macrosynteny and microsynteny
# command. Those are listed in the supplementary text's Appendix and in
# [`TO-BE-SUPPLIED.md`](TO-BE-SUPPLIED.md).
#
# A caution on versions: the response letter says IQ-TREE v3.1.2 and the supplementary text
# follows it, but the binary on this server is v3.1.3. Worth confirming before publishing.

# ===========================================================================
# 1. Orthogroup inference — OrthoFinder   [RUN]
# ===========================================================================
# Source: /mnt/sda/jackie/cnidaria/0.tree/0.peps/orthofinder.sh:76-84
# (the wrapper's INPUT_DIR and thread variables are expanded here)
stdbuf -oL -eL orthofinder \
    -f /mnt/sda/jackie/cnidaria/0.tree/0.peps \
    -t 300 -a 64 -S diamond -M msa

# The wrapper also raises the open-file limit, which is required.
# Source: 0.tree/0.peps/orthofinder.sh:50-59
ulimit -n 10000

# ===========================================================================
# 2. Phylogenetic matrix — MAFFT, BMGE, AMAS   [RUN], except the last step
# ===========================================================================
# A concatenated amino-acid alignment is built in four steps, each applied per orthogroup
# under GNU parallel:
#   (1) keep orthogroups present in all 153 species, reduce to the longest sequence per
#       species, rewrite the FASTA header to the bare species name — `1_OGfilter_new.py`;
#   (2) align with MAFFT;  (3) trim with BMGE;  (4) concatenate with AMAS.

# --- 2.1 MAFFT ---
# Source: 0.tree/2_mafft_new.sh:14-19, :25-26
mafft --anysymbol --thread 4 <og>.fa > mafft_res_new/<og>.fa
find "$input_dir" -maxdepth 1 -type f -name "*.fa" -print0 | parallel -0 --halt soon,fail=1 -j 40 run_mafft {}

# `--anysymbol` is required: without it MAFFT aborts on the non-standard residue characters
# (X, *, U) present in some predicted proteomes.

# --- 2.2 BMGE ---
# Source: 0.tree/3_bmge_new.sh:11-18, :24-25
bmge -i <aln>.fa -t AA -of bmge_res_new/<aln>.fa
find "$input_dir" -maxdepth 1 -type f -name "*.fa" -print0 | parallel -0 --halt soon,fail=1 -j 80 run_bmge {}

# --- 2.3 AMAS concatenation --- [GAP]
# Produces the 153-taxon x 24,975-column matrix. **The command is not recorded**, and the
# matrix itself is not on this server. See TO-BE-SUPPLIED.md item 1.

# ===========================================================================
# 3. Species tree — four IQ-TREE 3.1.2 runs   [RUN]
# ===========================================================================
# Each command line below is copied from the log header that recorded it.

# --- 3.1 Initial unconstrained run ---
# Source: 0.tree/5_phylo_new/init.log:7   (run log; not in the deposit)
./iqtree3 -s all_OG_bmge_concat.phylip -st AA -m LG+G --bnni -T 120 --prefix init

# --- 3.2 Constrained ModelFinder run — the tree the site serves ---
# Source: the `Command:` line of the run log
#         0.tree/5_phylo_new/DeBiasse_genus_repaired_153.log   (not in the deposit)
./iqtree3 -s all_OG_bmge_concat.phylip -st AA -m MFP \
    -g DeBiasse_genus_repaired_153.constraint.tree \
    -bb 1000 --alrt 1000 --bnni -T 70 --prefix DeBiasse_genus_repaired_153

# `-g` applies a topological constraint — the published genus-level backbone, repaired and
# reduced to the 153 taxa of this matrix. `-bb 1000 --alrt 1000` produce the two support
# values shown on the page.

# --- 3.3 Edge-proportional partitioned analysis, one partition per orthogroup ---
# Source: 0.tree/5_phylo_new/run_drop5_partitioned.sh:12-19
./iqtree3 -s all_OG_bmge_concat_drop5.phylip -st AA -p all_OG_bmge_partitions.nex \
    -m MFP -bb 1000 --alrt 1000 --bnni -T 70 --prefix EdgeProp_MFP_drop5

# --- 3.4 PMSF analysis, in two stages ---
# Source: 0.tree/5_phylo_new/run_drop3_guide_pmsf.sh:17-22, :28-35
./iqtree3 -s all_OG_bmge_concat_drop3.phylip -st AA -m LG+G -T 120 --prefix LG_G_guide_drop3
./iqtree3 -s all_OG_bmge_concat_drop3.phylip -st AA -m LG+C60+G \
    -ft LG_G_guide_drop3.treefile -bb 1000 --alrt 1000 --bnni -T 120 \
    --prefix LG_C60_PMSF_G_drop3_newguide

# The `drop3` and `drop5` alignments are the same concatenation with the three and five most
# incomplete taxa removed; **the commands that built them are not recorded** (Appendix).

# ===========================================================================
# 4. Gene trees — FastTree   [RUN]
# ===========================================================================
# 67,795 alignments are packed for the viewer, and 22,281 families additionally carry an
# unrooted FastTree topology.
# Source: orthology/genetree/run_fasttree.sh:32-45   (JOBS=32)
"$FT" -nosupport -quiet "$msa" > "$RAW/$og.nwk"

# Source: orthology/genetree/pack_aln.sh:34-40
xargs -a "$DIR/all_fams.txt" -P 24 -n 1 -I{} sh -c 'gzip -6 -c "$MSA/$1.fa" > "$OUT/$1.aln.fa.gz"' _ {}

# `-nosupport` is deliberate: the trees OrthoFinder itself wrote carry no support values,
# and adding them to only the filled-in half of the dataset would make the two halves
# inconsistent.

# ===========================================================================
# 5. Core ortholog resource   [RUN], [RECONSTRUCTED] for the invocation
# ===========================================================================
# A six-step Python pipeline over the same OrthoFinder result and the site's own BUSCO
# tables, run in order from codex/orthology/work/core/:
#
#   step  script                writes
#   ----  --------------------  ------------------------------------------------------
#    1    build_species.py      species.tsv — OrthoFinder names to site abbreviations plus
#                               taxonomy; sets busco90
#    2    build_copynumber.py   copynumber_full.tsv, og_genes.tsv
#    3    score.py              og_scores.tsv — occupancy and single-copy fraction
#    4    build_resource.py     core_og.tsv, core_members.tsv,
#                               matrix_{presence,copynumber,singlecopy}.tsv
#    5    build_sequences.py    CCO_members.faa,
#                               supermatrix/{strict,core,extended}.{faa,partitions.txt}
#    —    build_busco_check.py  the BUSCO comparison (reads the site tables)
#    —    tree_check.py         class-monophyly check of the FastTree trees
#
# The scripts survive; the invocations do not, so the driver line is reconstructed from
# them rather than quoted:
[RECONSTRUCTED] python3 '<script>.py'     # each step in the order above

# For each orthogroup, *occupancy* is the fraction of a species set in which it has at
# least one gene, and *single-copy* the fraction in which it has exactly one. Three
# cnidarian-only species sets are scored: `hq90` (60 genomes with BUSCO complete >= 90 %),
# `hq80` (103, every class represented) and `all` (148).
#
# Copy number counts distinct genes, not transcripts — a trailing `.tN` isoform suffix is
# stripped before counting, because 65,239 gene bases carry more than one isoform and
# counting those separately would make an isoform-rich single-copy gene look duplicated.
#
# The released tiers are cumulative: **14 / 224 / 1,017** orthogroups
# (strict / core / extended), spanning 136,050 member genes. The per-tier row counts in
# `core_og.tiers` are 14 / 210 / 793, which is the same partition read the other way —
# 14, 14+210, 14+210+793. Quote them cumulatively to match the site's three levels.
#
# Caution when reconciling counts: three different species sets circulate and they are not
# interchangeable — the 148 annotated species, the 60 in `core_species.busco90`, and the 15
# in the high_quality set. `docs/DATA_OVERVIEW.md` tabulates them.

# ===========================================================================
# 6. Table loads   [RUN]
# ===========================================================================
# Both the family and core-ortholog tables are loaded with LOAD DATA LOCAL INFILE into a
# new table and swapped in atomically, with a count guard that refuses the swap if the new
# table is short. Source: loaders/import.sh and loaders/import_core.sh
$MY --local-infile=1 -e "LOAD DATA LOCAL INFILE 'load_core_member.tsv' INTO TABLE core_member_new ..."
#   RENAME TABLE <pairs read from information_schema>;  # refuses if species<150, og<1000, mem<130000

# Two silent failure modes the scripts are written to avoid:
#   · MySQL refuses a multi-table RENAME if any source table is missing, so the swap pairs
#     are read from `information_schema` instead of named unconditionally;
#   · a failing `mysql` call must stop the script, so the `Using a password` warning is
#     filtered from stderr without swallowing the exit status.

# ===========================================================================
# 7. Macrosynteny and microsynteny — [GAP]
# ===========================================================================
# Computed with macrosyntR over 139 species, giving 9,591 species pairs and 395,668
# ortholog anchors. BUSCO single-copy orthologs are the cross-species anchors; each
# chromosome pair is tested with a Fisher exact test, significantly associated chromosomes
# are merged into conserved linkage groups by greedy modularity clustering
# (`igraph::cluster_fast_greedy`), and the result is shown as an interactive Oxford grid.
#
# **The command lines are not recoverable** — no macrosyntR package, script, log or
# environment exists on the reachable hosts, so this analysis ran elsewhere. The parameters
# above are descriptions from the site pages and the letter, not a run record.
# See TO-BE-SUPPLIED.md item 15.
#
# When summarising the result, note that the within-class correlation reported on the page
# is the **median** |rho| (0.835 within class versus 0.238 between classes). `AVG()` returns
# a different and misleading answer (0.689 / 0.301), and MySQL 8 has no PERCENTILE_CONT.

# ===========================================================================
# 8. Divergence-time dating — [GAP]
# ===========================================================================
# Fossil calibrations are recorded, but the dating software is never named.
# See TO-BE-SUPPLIED.md item 16.
