#!/bin/sh
# CnidoSite — genome assembly, polishing, Hi-C and mitogenome
#
# ===========================================================================
# ASSEMBLY IS OUT OF SCOPE FOR CNIDOSITE. READ THIS BEFORE USING ANYTHING BELOW.
# ===========================================================================
#
# CnidoSite does not publish a de novo assembly of its own. The genome sequences it
# serves are taken from public INSDC/NCBI deposits, and no assembly command line is
# part of the provenance of any dataset the resource serves. This is stated in the
# supplementary text, §S1 ("Genome assembly is out of scope") and §S3.1:
#
#   "the assembly, polishing, Hi-C scaffolding and divergence-dating chains that exist
#    on our servers belong to a separate genome project and are not the provenance of
#    any dataset served here. No assembly command lines are therefore given."
#
# ---------------------------------------------------------------------------
# What is below, and why it is here
# ---------------------------------------------------------------------------
# Everything below belongs to **that separate genome project** — one hydrozoan
# ("jellyfish") genome — and **not** to CnidoSite. It is retained as reference material
# because it is the only complete assembly record on the working tree, and because the
# same tree supplies the BRAKER3 / PASA / GeMoMa / EVM chain that section 3 of
# `03-gene-annotation.sh` also has to place.
#
# Source: /mnt/sda/jackie/PASA/jellyfish_final/assembly.sh   (272 lines)
#
# Nothing in this file should be cited as a CnidoSite method.
#
# The authors state in that file (lines 3-5) that the pipeline was **not** frozen into a
# single workflow — parameters were chosen per species. Treat this file as the record of
# one run of another project, not as a workflow definition.
#
# Hardware note: the runs use `-t 120` / `-p 120` throughout; the host has 120 threads.
#
# ===========================================================================
# ---- EXTERNAL MATERIAL BEGINS — NOT CNIDOSITE PROVENANCE ------------------
# ===========================================================================

# ---------------------------------------------------------------------------
# 0. Environment
# ---------------------------------------------------------------------------
mamba create -n genome_assembly python=3.8
mamba activate genome_assembly
mamba install -c bioconda trimmomatic jellyfish seqtk
mamba install -c bioconda repeatmodeler
mamba install -c bioconda repeatmasker
mamba install -c bioconda flye canu spades          # not the assembler used here
mamba install -c bioconda pilon nextpolish2
mamba install -c bioconda juicer 3d-dna             # Hi-C
mamba install -c bioconda busco quast
mamba install -c bioconda augustus braker3 interproscan

# ---------------------------------------------------------------------------
# 1. Genome size and heterozygosity  (README lines 23-46)
# ---------------------------------------------------------------------------
jellyfish count -C -m 19 -s 5000000000 -t 120 -o reads.jf <(zcat jellyfish_GP.pass.fq.gz)
jellyfish histo -t 120 reads.jf > reads.histo
git clone https://github.com/schatzlab/genomescope.git
Rscript genomescope/genomescope.R reads.histo 19 50 test
# GenomeScope 1.0, k = 19: haploid length 707.9-708.3 Mb, heterozygosity 3.60%,
# repeat length 384.0-384.2 Mb, read error rate 0.380%.
# A second run at k = 21 is recorded in the same file (lines 37-46).

# ---------------------------------------------------------------------------
# 2. Assembly — hifiasm on ONT reads  (line 49-51)
# ---------------------------------------------------------------------------
hifiasm -o hifiasm_output -t 120 --ont jellyfish_GP.pass.fq.gz
awk '/^S/{print ">"$2; print $3}' hifiasm_output.bp.p_ctg.gfa > hifiasm_output_contig.fa
assembly-stats hifiasm_output_contig.fa
# Recorded result: sum 1,528,767,331 bp, n = 1010, N50 = 5,378,165, largest 22,446,141.

# ---------------------------------------------------------------------------
# 3. Purge haplotigs  (lines 63-79)
# ---------------------------------------------------------------------------
minimap2 -t 120 -x map-ont hifiasm_output_contig.fa jellyfish_GP.pass.fq.gz | gzip -c - > hifiasm_output_contig.paf.gz
pbcstat hifiasm_output_contig.paf.gz
calcuts PB.stat > cutoffs
hist_plot.py -c cutoffs PB.stat PB.cov.png

split_fa hifiasm_output_contig.fa > hifiasm_output_contig.fa.split
minimap2 -t 120 -x asm5 -DP hifiasm_output_contig.fa.split hifiasm_output_contig.fa.split | gzip -c - > hifiasm_output_contig.fa.split.self.paf.gz
purge_dups -2 -T cutoffs -c PB.base.cov hifiasm_output_contig.fa.split.self.paf.gz > dups.bed 2> purge_dups.log
get_seqs -e dups.bed hifiasm_output_contig.fa
mv purged.fa jellyfish_purged.fa
mv hap.fa jellyfish_haplotig.fa

# A stricter parameterisation is kept commented in the source (line 75); it was not
# the one used:
# purge_dups -2 -T cutoffs -c PB.base.cov ...paf.gz -f 0.6 -a 50 -b 100 -m 300 > dups1.bed

# ---------------------------------------------------------------------------
# 4. Contiguity QC  (lines 81-86)
# ---------------------------------------------------------------------------
quast -o quast_results_low    hifiasm_output_contig.fa -t 120 -m 500
quast -o quast_results_medium hifiasm_output_contig.fa -t 120 -m 1000
quast -o quast_results_high   hifiasm_output_contig.fa -t 120 -m 5000
# Recorded: N50 = 5,379,941, L50 = 88, auN = 6,620,011.3, total length 1,529,588,764 bp, GC 31.53%.

# ---------------------------------------------------------------------------
# 5. Second-round polishing — NextPolish2  (lines 93-103)
# ---------------------------------------------------------------------------
bwa index ../2.purge_dups/jellyfish_purged.fa
bwa mem ../2.purge_dups/jellyfish_purged.fa ../250424/Gene_trimmed_R1.fq.gz ../250424/Gene_trimmed_R2.fq.gz
samtools view -Sb aligned_reads.sam > aligned_reads.bam
samtools sort aligned_reads.bam -o aligned_reads.sorted.bam

minimap2 -t 120 -x map-ont jellyfish_purged.fa ../jellyfish_GP.pass.fq.gz | samtools sort -@ 40 -m 4G -o ont.map.sort.bam
samtools index ont.map.sort.bam
yak count -o k21.yak -k 21 -b 37 <(zcat ../250424/Gene_trimmed_R*.fq.gz) <(zcat ../250424/Gene_trimmed_R*.fq.gz)
yak count -o k31.yak -k 31 -b 37 <(zcat ../250424/Gene_trimmed_R*.fq.gz) <(zcat ../250424/Gene_trimmed_R*.fq.gz)
nextPolish2 -t 120 ont.map.sort.bam jellyfish_purged.fa k21.yak k31.yak > jellyfish_purged_np2.fa

# ---------------------------------------------------------------------------
# 6. Second-round de-duplication — Redundans  (line 106)
# ---------------------------------------------------------------------------
../tools/redundans/redundans.py -v -t 120 -i ../250424/Gene_trimmed_R*.fq.gz \
    -l ../jellyfish_GP.pass.fq.gz -f jellyfish_purged_np2.fa \
    -o run_short_long_populatescaffold --minimap2scaffold --populateScaffolds

# ---------------------------------------------------------------------------
# 7. Hi-C scaffolding  [PROSE]
# ---------------------------------------------------------------------------
# Line 4 of the source says "juicer + 3D-DNA" was used, and juicer/3d-dna are installed
# by the mamba line above, but **no juicer or 3D-DNA invocation is recorded anywhere on
# this server**. See TO-BE-SUPPLIED.md item 5.

# ---------------------------------------------------------------------------
# 8. Mitochondrial genome  (lines 256-261)
# ---------------------------------------------------------------------------
mamba create -n mitoz_env python=3.7
mamba activate mitoz_env
mamba install -c bioconda mitoz
mitoz all --thread 120 \
    --fq1 data/BC2025030387-BGI-DNA-1samples/rawdata/Gene/Gene_R1.fq.gz \
    --fq2 data/BC2025030387-BGI-DNA-1samples/rawdata/Gene/Gene_R2.fq.gz \
    --outprefix mitoz_output --workdir mitoz_workdir --requiring_taxa Leptothecata
mitofinder --megahit -j Wulab_jellyfish_Turritopsis_dohrnii \
    -1 mitoz_workdir/clean_data/mitoz_output.clean_R1.fq.gz \
    -2 mitoz_workdir/clean_data/mitoz_output.clean_R2.fq.gz \
    -r Turritopsis_dohrnii.gb -o 5 -p 120
# The reference .gb must carry its own gene annotation; MitoFinder's output numbering
# depends on it. Same taxon restriction pattern applies to the other species.
#
# The site holds 173 mitochondrial genomes with 3,555 annotated features
# (tables `mito_genome` and `mitochondrion`; see docs/DATA_OVERVIEW.md).
# Whether every one of the 173 came through this exact command is not recorded.

# ---------------------------------------------------------------------------
# 9. Divergence-time calibration  (lines 264-273)
# ---------------------------------------------------------------------------
# Fossil calibrations hard-coded in the source, as (clade, ingroup, outgroup, Ma, fossil):
#   Anthozoa      Acropora / Nematostella    420    kilbuchophyllids
#   Arthropoda    Drosophila / Limulus       514    Yicaris
#   Protostoma    Lottia / Drosophila        550.25 Kimberella
#   Chordata      Homo / Branchiostoma       518    Haikouella
#   Cnidaria      Aurelia / Nematostella     529    Olivooides
#   Medusozoa     Aurelia / Hydra            505    fossil medusas
#   Spiralia      Capitella / Lottia         532    Aldanella
# Nematostella/Exaiptasia divergence was constrained to 250-480 Mya.
# The dating software itself is not named in the source. [GAP]
