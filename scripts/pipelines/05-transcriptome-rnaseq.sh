#!/bin/sh
# CnidoSite — RNA-seq processing, expression matrices, co-expression
#
# Scope: everything behind the site's 31 `<ABBR>_TPM` expression tables and the
# expression panel on gene_detail.php.
#
# Working tree: /mnt/sda/jackie/cnidaria_omics/RNA-seq/   (380 subdirectories)
# Conda environment: `rna` at /home/jackie/miniconda3/envs/rna — **not on the default PATH**.
#
# A warning about the logs: the 236 MB RNA-seq/nohup.out is the log of an attempt that
# died on its first sample (`ERROR: Failed to open file: rawdara/SRR19977425.fastq.gz` —
# a typo for `rawdata/`, nohup.out:1). There is **no log of a successful full run**.
# The commands below are therefore taken from the generator scripts, and the versions
# from the installed binaries.

# ===========================================================================
# 1. Adapter / quality trimming — fastp
# ===========================================================================
# Version: fastp 0.23.4 — recorded per sample in fastp.json:3 and in the `command` field.
# Source: run.sh:9 (paired-end; representative of 1,007 PE lines), runrun.sh:1 (single-end)
#
# Paired-end [RUN]:
fastp -w 16 -i rawdata/SRR29005672_1.fastq.gz -I rawdata/SRR29005672_2.fastq.gz \
      -o ./results/trimmed/SRR29005672_trimmed_1.fastq.gz \
      -O ./results/trimmed/SRR29005672_trimmed_2.fastq.gz
#
# Single-end [RUN]:
fastp -w 16 -i rawdata/SRR19977425.fastq.gz -o ./results/trimmed/SRR19977425_trimmed.fastq.gz
#
# **No adapter or quality parameters were passed** — fastp defaults throughout. fastp's own
# JSON records the exact argv, e.g.:
#   "command": "fastp -w 16 -i rawdata/SRR12963484.fastq.gz -o ./results/trimmed/SRR12963484_trimmed.fastq.gz "
# Input: rawdata/*.fastq.gz   Output: results/trimmed/*_trimmed*.fastq.gz (2,709 files on disk)

# ---------------------------------------------------------------------------
# 1b. A second, different trimming template — [TEMPLATE], not used
# ---------------------------------------------------------------------------
# run_analysis.sh:16 (PE) / :19 (SE) uses Trimmomatic 0.40 instead of fastp, and differs in
# three more ways: `-threads 60` (not 16), it reads a sample list file named `sample.list`
# (the file actually on disk is named `sample`), and it feeds featureCounts rather than
# StringTie. run.pl:14/17 generates the same commands. It appears to be a superseded
# template:
trimmomatic PE -threads 60 -phred33 ${Run}_1.fastq.gz ${Run}_2.fastq.gz \
    ./results/trimmed/${Run}_trimmed_1.fastq.gz ./results/trimmed/${Run}_unpaired_1.fastq.gz \
    ./results/trimmed/${Run}_trimmed_2.fastq.gz ./results/trimmed/${Run}_unpaired_2.fastq.gz \
    ILLUMINACLIP:/home/jackie/miniconda3/envs/rna/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 \
    LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36

# ===========================================================================
# 2. Index build — HISAT2
# ===========================================================================
# Version: hisat2-align-s 2.2.2 (read from the binary; no script records it).
# Source: hisat2_build.sh (107 lines, one per species), line 1:
hisat2-build Acropora_austera.fa results/db/Acropora_austera
# Input: Ref/<Species>.fa   Output: results/db/<Species>.*.ht2
# Ref/ holds 148 genome FASTA and 145 GFF3; 107 species currently have indices (856 .ht2 files).
# **No --threads and no -p**: the index build was single-threaded.

# ===========================================================================
# 3. Alignment — HISAT2
# ===========================================================================
# Source: run.sh:10 / run1.sh:2 (PE), runrun.sh:2 and run.pl:18 (SE)
#
# Paired-end [RUN]:
hisat2 -x ./results/db/Acropora_hemprichii \
       -1 ./results/trimmed/SRR29005672_trimmed_1.fastq.gz \
       -2 ./results/trimmed/SRR29005672_trimmed_2.fastq.gz \
       -S ./results/aligned/SRR29005672.sam \
       --dta --rna-strandness RF --threads 30 2> results/aligned/SRR29005672_hisat2.log
#
# Single-end [RUN]:
hisat2 -x ./results/db/$a[1] -U ./results/trimmed/$a[5]_trimmed.fastq.gz \
       -S ./results/aligned/$a[5].sam \
       --dta --rna-strandness RF --threads 30 2> results/aligned/$a[5]_hisat2.log
#
# DISCREPANCY to settle: the single-end branch of run_analysis.sh:34 uses
# `--rna-strandness F`, while every other single-end line uses `RF`. One of the two was
# used for the released data. See TO-BE-SUPPLIED.md item 7.

# ===========================================================================
# 4. SAM → sorted BAM  (run.sh:11-13, run.pl:20-22)
# ===========================================================================
samtools view -@ 30 -bS ./results/aligned/SRR29005672.sam | samtools sort -@ 30 -o ./results/aligned/SRR29005672_sorted.bam
samtools index ./results/aligned/SRR29005672_sorted.bam
rm results/aligned/SRR29005672.sam
# Version: samtools 1.21 (htslib 1.23), read from the binary.

# ===========================================================================
# 5. Quantification — StringTie  ← this is the source of the site's TPMs
# ===========================================================================
# Version: StringTie 3.0.3 (binary; not recorded in any script).
# Source: run.sh:14, runrun.sh:6, run.pl:23
stringtie -p 30 -e -B -G Ref/Acropora_hemprichii.gff3 \
    -o ./results/stringtie_assembly/SRR29005672.gtf -l SRR29005672 \
    ./results/aligned/SRR29005672_sorted.bam
# `-e -B -G <ref.gff3>` = reference-guided, emitting per-transcript tpm / FPKM / cov in the
# GTF attributes. No Trinity, no Cufflinks, no featureCounts was used for the released data.

# ===========================================================================
# 6. Coverage tracks — deepTools bamCoverage
# ===========================================================================
# Version: bamCoverage 3.5.6 (binary). Source: run.sh:15, runrun.sh:7, run.pl:28
bamCoverage --bam results/aligned/SRR29005672_sorted.bam \
    --outFileName results/bw/Acropora_hemprichii_SRR29005672_coral.bw \
    --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates \
    --numberOfProcessors max --verbose
# 1,665 .bw files on disk.

# ===========================================================================
# 7. Per-species expression matrix  ← the TPM tables
# ===========================================================================
# Source: results/stringtie_assembly/*.py — 32 small scripts, one per species.
# In 10.py:11 `EXPRESSION_METRIC = 'tpm'`; :36 reads the `tpm "…"` attribute from field 8
# of each `transcript` record; :43 sums transcript TPMs per `gene_id`; :52-53 writes
# <ABBR>_expressionmatrix.csv (some species emit .txt).
python3 10.py
# No arguments: the sample list is hard-coded inside each script.
# 29 `<ABBR>_expressionmatrix.txt` files are on disk. Metric = StringTie TPM, not FPKM.
# **No script on this server loads these matrices into the database** — the TPM tables were
# created by a step that is not here.

# ---------------------------------------------------------------------------
# 7b. featureCounts — [TEMPLATE] only, never run
# ---------------------------------------------------------------------------
# run_analysis.sh:87-90:
featureCounts -T 60 -a ${TARGET_SPECIES}.gff3 -o results/matrix/${TARGET_SPECIES}_featureCounts.txt -R BAM $BAM_LIST
# No results/matrix/ directory exists, and the `subread` package is not installed.

# ===========================================================================
# 8. Sample metadata bridge
# ===========================================================================
# RNA-seq/sample holds 1,762 tab-separated lines with the columns
#   latin / underscored latin / BioProject / SRP / SRX / SRR run / Layout / tissue /
#   dev_stage / Treatment
# matching the reader in includes/rnaseq_meta_refresh.php. That script is CLI-only and
# generates data/rnaseq_samples.json, which gene_detail.php's expression panel consumes:
php -c /etc/php/7.4/apache2/php.ini /var/www/html/CnidoSite/includes/rnaseq_meta_refresh.php
# `--check` reports the index timestamp, row count and per-table coverage without rebuilding.

# ===========================================================================
# 9. Co-expression networks — [RUN], [RECONSTRUCTED] for the invocations
# ===========================================================================
# Per-transcript TPM from the StringTie assemblies is collapsed to a gene-level matrix per
# species, filtered with a per-sample cutoff, and turned into an undirected network by
# all-vs-all Pearson correlation (PCC) and mutual rank (MR); edges are selected by
# thresholds fixed with ROC curves against known co-functional pairs. The resource serves
# 29 networks over 29 species.
#
# Scripts are deposited as supplementary-scripts/S06-co-expression/. The operative lines
# survive; the invocations do not, so the driver lines below are reconstructed from them.

# --- 9.1 TPM extraction and filtering ---
# Source: extract_TPM.pl:15, 20, 30-34, 69
#   open(my $fh, '<', 'gtf') or die "无法打开gtf.txt: $!";
#   my ($sample) = $filename =~ /bpl_([^"]+)_trimmed_stringtie.gtf/;
#   if( @a && $a[2] eq "transcript" && $a[8] =~ /TPM "([^"]+)";/){
#       my $tpm = $1;
#       my ($gene) = $a[8] =~ /gene_id "\d+:([^"]+)";/;
#       $tpm_data{$gene}{$sample} = $tpm;
#   my $tpm = $tpm_data{$gene_id}{$sample} || 0;
# [RECONSTRUCTED] perl extract_TPM.pl   # run in a directory containing the file `gtf`

# Source: FPKM_threshold.R:2, 14, 16, 23, 24, 27, 30, 33, 36
#   fpkm <- read.table("Csq_expression_matrix.txt", header=TRUE, sep="\t")
#   b <- sort(a[a != 0])
#   c[i] <- b[round(0.05 * length(b))]           # 5th percentile of non-zero values
#   mean_c <- mean(c, na.rm=TRUE)
#   cutoff <- mean_c + 3 * sd(c, na.rm=TRUE)
#   data[data < cutoff] <- 0
#   new <- data[rowSums(data) != 0, ]
#   new[new < cutoff] <- cutoff
# [RECONSTRUCTED] Rscript FPKM_threshold.R
# The per-sample cutoff is therefore the mean of the 5th percentiles of the non-zero values
# plus three standard deviations; values below it are set to zero and surviving rows are
# floored at the cutoff.

# --- 9.2 Network construction --- [RUN], [RECONSTRUCTED] for the invocation
# PCC and MR are computed with the WGCNA package; MR is the geometric mean of the two
# genes' descending-PCC ranks. Source: PCC_MR_by_WGCNA.R:6, 8-12, 15, 17, 24-31, 43-48, 61-62
#   library(WGCNA)
#   enableWGCNAThreads()
#   fpkm <- read.table("bpl_expression_matrix_no0.txt", head=T, sep="\t", row.names=1)
#   datExpr = as.data.frame(t(fpkm[,1:dim(fpkm)[2]]))
#   pccMat = adjacency(datExpr, power = 1, type="sign")
#   diag(pccMat) <- 2
#   for(i in 1:n){ pccRankMat[i,] <- rank(-pccMat[i,], ties.method="min") }
#      a <- pccRankMat[x,j]-1 ; b <- pccRankMat[j,x]-1
#      MRpos <- sqrt(a*b)
#      c <- n-a ; d <- n-b ; MRneg <- sqrt(c*d)
#   mc <- getOption("mc.cores",5) ; mclapply(2:n, funMR, mc.cores=mc)
# [RECONSTRUCTED] Rscript PCC_MR_by_WGCNA.R

# An alternative all-vs-all PCC script (`pcc.pl`, Perl `Statistics::Basic`) is recorded but
# **did not run** — the module is not installed on the machine and its log shows the load
# failing. The delivered networks come from the WGCNA path above.

# --- 9.3 Threshold selection ---
# Edge labels are generated for known co-functional pairs (two genes sharing at least one
# GO term) and the PCC and MR grids are scored by ROC.
# Source: PCC_ROC.R:4-10, 15; MR_ROC.R:4-8, 15, 23
#   go.data6 = read.delim("data_ROC_in6_PCC");  pred.go6 = prediction(go.data6[,1], go.data6[,2])
#   pref.go6 = performance(pred.go6, "tpr", "fpr");  auc.go6 = performance(pred.go6,"auc")@y.values
#   plot.roc(go.data6[,2], go.data6[,1], print.thres=FALSE, col="red")
# [RECONSTRUCTED] Rscript PCC_ROC.R ; Rscript MR_ROC.R

# Recorded outcomes: PCC AUCs 0.6573 (0.6), 0.6806 (0.7), 0.6667 (0.8), 0.6426 (0.9);
# MR AUCs 0.7806 (20), 0.7909 (30), 0.7885 (40), 0.7811 (50), 0.7914 (100).
#
# The final edge lists are selected at **PCC >= 0.75 and MR < 30** (positive; 241,360
# edges) and **PCC <= -0.5 and MR < 30** (negative; 68,959 edges) — both counts verified
# against the delivered files.
#
# NOTE, to confirm before publishing: an earlier draft of this record gave the negative
# floor as MR < 50 rather than MR < 30. Both drafts report the same 68,959 negative edges.
# The delivered edge lists settle it; check them against the threshold before quoting it.

# --- 9.4 Site-side parameters ---
# These are applied by the website, not by the construction scripts, and a re-implementation
# must observe them:
#   · a per-species top-K cap of 100 (CNIDO_NET_TOP_K = 100);
#   · the positive table is ordered by PCC descending, the negative table by PCC ascending;
#   · `rankA` and `rankB` are mutual-rank positions and must not be used to order or to
#     weight results.

# ===========================================================================
# 10. Functional annotation of transcripts — [GAP]
# ===========================================================================
# The per-species annotation packages exist as release artefacts
# (download/transcriptome_assembly/<Species>.tar.gz, 229 packages, each containing
# *_annotation.tsv, _uniprot.tsv, _nr.tsv, _pfam.tsv, _panther.tsv, _interpro.tsv,
# _go.tsv, _kegg.tsv), but **no command that produced them is on this server**. The site's
# tools table lists only `InterProScan v.5.67-97.0 -f tsv -goterms -pa`
# (data_statistics.php:530); no BLAST, DIAMOND, eggNOG or TransDecoder run is recorded for
# this module. See TO-BE-SUPPLIED.md item 4.
