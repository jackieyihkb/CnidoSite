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
# 9. Co-expression networks — [GAP]
# ===========================================================================
# The site serves `<ABBR>_coexpress_positive` and `<ABBR>_coexpress_negative` tables. The
# only artefacts on this server are the finished tables (/home/jackie/staging-network/);
# **no network-construction script exists here**. A whole-machine grep for the parameters
# that the tables clearly encode (rankA / rankB / pcc / top-K) hits only the site's own PHP
# and third-party libraries.
#
# What is known about the construction, all of it reconstructed from the released tables
# and recorded in the response letter rather than from a script:
#   · edge strength = Pearson correlation coefficient (PCC);
#   · mutual rank MR = sqrt(rankA * rankB);
#   · a per-species top-K cap, CNIDO_NET_TOP_K = 100, applied on the site side;
#   · positive table ordered by PCC descending, negative table by PCC ascending.
# The per-species PCC floors and the merge rule are not recoverable. See
# TO-BE-SUPPLIED.md item 13.

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
