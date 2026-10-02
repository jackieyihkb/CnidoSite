# CnidoSite — epigenome pipelines (ATAC / ChIP / DNase / WGBS)

> **Read this first.** This module carries the one substantive scientific dispute in the
> review, and the working record is incomplete in a way that matters. Two things are true
> at once:
>
> * the released peak calls were produced by a re-run on 2026-09-22 whose **per-sample
>   command lines are no longer on this server** — only two count summaries survive;
> * the response letter states (correctly) that `--nomodel --shift -100 --extsize 200`
>   "appears nowhere", while a run summary on the same disk records the DHS re-run as
>   having used exactly that.
>
> Both are documented below rather than smoothed over. Items 1-5 of
> [`TO-BE-SUPPLIED.md`](TO-BE-SUPPLIED.md) come from this module.

---

## 1. ATAC-seq

### 1.1 Raw data download — `[RUN]`

Aspera. Source `/mnt/sda/jackie/cnidaria_omics/ATAC/aspera_bulk_download.sh:13-24`;
sample output `download_ATAC.txt:1-3`; sample sheet `ATAC/run` (213 runs).

```sh
ascp -QT -l 500m -P33001 -k 1 -i $openssh \
  era-fasp@fasp.sra.ebi.ac.uk:vol1/fastq/$x/0$y/$id/ ./
```

Output: one directory per run, `ATAC/SRR10034524/SRR10034524.fastq.gz`.

### 1.2 Alignment — `[RUN]`

`bowtie2 v2.5.5` and `samtools` (no version recorded for ATAC at the time).
Sources: `analysis/3.ATAC/Exaiptasia_diaphana/run_atac_pipeline.sh` (single-end),
`.../Hydra_vulgaris/run_atac_pipeline.sh` (paired-end),
`.../Nematostella_vectensis/run_atac_pipeline.sh` (single-end). Line numbers agree:

```sh
conda activate atac_seq                                                        # line 4
fastqc -o fastqc_out *.fastq.gz > logs/fastqc.log 2>&1                          # line 19
# single-end:
bowtie2 -x genome_index -U ${sample}.fastq.gz -S ${sample}.sam -p 120 \
  --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/${sample}_align.log
# paired-end (Hydra):
bowtie2 -x genome_index -1 ${sample}_1.fastq.gz -2 ${sample}_2.fastq.gz -S ${sample}.sam \
  -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/${sample}_align.log
samtools view -@ 120 -bS ${sample}.sam | samtools sort -o bam/${sample}_sorted.bam   # line 40
samtools index bam/${sample}_sorted.bam                                             # line 43
```

Index build: line 14 in the Exaiptasia and Hydra scripts (no `--threads`); line 14 in the
Nematostella script says `--threads 120`.

### 1.3 Peak calling — MACS2 `v2.2.9.1`

Three species-specific command forms exist. **Only the Exaiptasia form is active.**

**(a) Exaiptasia diaphana — active, and it DOES use `--nomodel --shift`** `[RUN]`
Source: `1.sh:76-88`, identically `run_atac_pipeline.sh:76-88`.

```sh
macs2 callpeak -t $bam_files -n peaks/${group_name}_consensus \
  -f BAM -g 1.98e8 --nomodel --shift -37 --extsize 73 \
  -B --SPMR --keep-dup all -q 0.05 2> logs/${group_name}_macs2.log
```

(`run_atac_pipeline.sh:76` drops the `macs2` prefix on the continuation line; `1.sh:76`
has it. The shift value is −37/73 — the Tn5 nucleosome-free convention — **not** the
−100/200 discussed in the review.)

**(b) Hydra vulgaris** — `[RUN]`, one line per sample group. Source `Hydra_vulgaris/macs2.sh:1-24`:

```sh
macs2 callpeak -t bam/<sample>_sorted.bam [-c <control>_sorted.bam] \
  -n peaks/<group>_consensus -f BAM -g 8.81e8
```

**(c) Nematostella vectensis** — `[RUN]`, same shape. Source `Nematostella_vectensis/macs2.sh:1-17`:

```sh
macs2 callpeak -t bam/<sample>_sorted.bam -c bam/<control>_sorted.bam \
  -n peaks/<group>_consensus -f BAM -g 2.61e8
```

In both (b) and (c) the corresponding block of `run_atac_pipeline.sh:76-88` is **commented
out**, so these small `macs2.sh` files are the record of record.

Effective genome sizes used across the module: 2.61e8 *N. vectensis*, 8.81e8 *H. vulgaris*,
1.98e8 *E. diaphana*, 4.38e8 *A. digitifera*.

### 1.4 Coverage tracks — `[RUN]`

deepTools `bamCoverage` (3.5.6 in the current environment; not recorded at the time).
Source: `generate_bw.sh` (Ex :55-68, Nem :49-65, Hydra :62-75).

```sh
samtools merge -@ 120 "$merged_bam" $bam_files
samtools index -@ 120 "$merged_bam"
bamCoverage -b "$merged_bam" -o "bw_out/${...}.bw" \
  --binSize 15 --normalizeUsing RPKM -p 120 2> logs/${...}_bamCoverage.log
```

---

## 2. ChIP-seq

**The per-sample command lines do not exist on this server.** The `5.ChIP` working tree
lives on a different host — `/mnt/sda/jackie/cnidaria/5.ChIP` on 143.89.54.44 — and the
response letter itself concedes at one point that "the re-call log itself is no longer on
this machine".

What survives locally:

* a 95-line count summary, `/home/jackie/cnidosite_epigenome_rerun_2026-09-22.txt`, whose
  header records the old and new conventions (`rerun:1-6`):
  ```
  #   old = 5.ChIP/peaks + 3.ATAC/Hydra_vulgaris/peaks   (-f BAM everywhere; ChIP -g 2.61e8 everywhere)
  #   final = 5.ChIP/peaks_bampe | peaks_broad + 3.ATAC/Hydra_vulgaris/peaks_bampe
  #           (-f BAMPE ...; --broad --broad-cutoff 0.1 for H3K27me3/H3K36me3/H3K4me1/H4K20me1)
  ```
* a pre-re-run database snapshot, `/home/jackie/backups-20260922/epigenome-reload/peak_tables_before.sql`
  (353 MB).

**The published convention**, as stated on the site (`data_statistics.php:538-543`):

```sh
# ChIP-seq
macs2 callpeak -t <sample BAMs> -c <input/IgG control BAMs> -f BAMPE -g <effective genome size>
# broad marks only
macs2 callpeak ... --broad --broad-cutoff 0.1
```

The defects this re-run fixed are documented in the response letter: every ChIP sample had
been called with `-g 2.61e8` regardless of species, and paired-end data had been called
with `-f BAM`. See `TO-BE-SUPPLIED.md` item 1.

---

## 3. DNase-seq / DHS

Only two artefacts are on this server: a download log recording **Kingfisher v0.5.0**
(`DHS/kingfisher_ena_ftp.log:2`, followed by aria2c chunked downloads of two 36 GB
`DRR608907` FASTQ files), and one line in the DHS re-check summary
(`cnidosite_epigenome_recheck_2026-09-22.txt:41`):

```
# DNase (6.DHS) old=8145 (default model, d=260)  new=5802 (--nomodel --shift -100 --extsize 200, d=200)
```

That line is the **only** place on this disk where `--nomodel --shift -100 --extsize 200`
appears — and it records the value as having been *used* for the DHS re-call. The response
letter states the opposite (that the string appears nowhere in the site, the analysis tree,
or any backup). Both statements are quoted here because reconciling them requires the
authors: see `TO-BE-SUPPLIED.md` item 2.

The published convention for this assay (`data_statistics.php:542`):

```sh
macs2 callpeak -t <sample BAM> -f BAM -g 4.38e8
```

The aligner used for DHS is not recorded anywhere locally.

---

## 4. DNA methylation (WGBS / BS-seq)

All scripts are in `/mnt/sda/jackie/cnidaria_omics/analysis/4.BS/`.

Versions: **Bismark v0.25.1** (`4.BS/nohup.out:4-8` — "Bisulfite Genome Indexer version
v0.25.1" — and `nohup.out:65633`: "Bismark methylation extractor version v0.25.1");
underlying **Bowtie 2 v2.5.5** (`alignment/SRR8346017_bismark.log:3`); Trimmomatic 0.40.

### 4.1 Index build — `[RUN]`

```sh
bismark_genome_preparation --parallel 120 --bowtie2 Ref/<species>/
```

### 4.2 Adapter trimming — `[TEMPLATE]`

Generator `BSseq_cnidaria_analysis.sh:24-29` (PE), `:45-49` (SE); sampled in `PE.sh:1`,
`SE.sh:1`. The adapter path points into **another user's** environment.

```sh
trimmomatic PE -threads 180 -phred33 rawdata/${run}_1.fastq.gz rawdata/${run}_2.fastq.gz \
  trimmed/${run}_R1_trimmed.fq.gz trimmed/${run}_R1_unpaired.fq.gz \
  trimmed/${run}_R2_trimmed.fq.gz trimmed/${run}_R2_unpaired.fq.gz \
  ILLUMINACLIP:/home/jiajie/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 \
  LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> ${run}_trimmomatic.log
```
(The SE form substitutes `TruSeq3-SE.fa`.)

### 4.3 Alignment — `[RUN]`, and it does NOT match the template

The authoritative source is Bismark's own `@PG` record, written into the SAM header and
retained in the log at `4.BS/nohup.out:65627` (also `:66567`):

```
@PG  ID:Bismark  VN:v0.25.1  CL:"bismark --bowtie2 -N 1 -L 20 --parallel 120 -o alignment \
     --prefix SRR042634_ Nematostella_vectensis/ -1 SRR042634_R1_trimmed.fq.gz -2 SRR042634_R2_trimmed.fq.gz"
```

Compare with what the template scripts say (`BSseq...:34-38`, `PE.sh:2`, `bismark.sh:3`):

| | template | actually run |
|---|---|---|
| genome directory | `Ref/<species>/bismark_index` | `Nematostella_vectensis/` |
| input path | `trimmed/${run}_R1_trimmed.fq.gz` | `SRR042634_R1_trimmed.fq.gz` (no `trimmed/`) |
| parallel | 180 | **120** |

Cite the `@PG CL:` form, not the template.

### 4.4 Deduplication — `[RUN]`

```sh
deduplicate_bismark -bam -p alignment/${run}_.${run}_R1_trimmed_bismark_bt2_pe.bam --output_dir duplicate
```
(`bismark.sh:5`, `SE.sh:3`, `PE.sh:3`)

### 4.5 Methylation extraction — `[RUN]`

```sh
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive \
  --bedGraph --count --CX_context --cytosine_report --buffer_size 20G \
  --genome_folder Ref/<species>/ -o report \
  duplicate/...deduplicated.bam
```
(`bismark.sh:8`, `SE.sh:4`, `PE.sh:4`; the generator variant at `BSseq...:64-67` uses
`--counts --report --buffer_size 10G`.)

### 4.6 bedGraph → BigWig — `[RUN]`

```sh
zcat report/${run}_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/${run}_sorted.bedGraph
bedGraphToBigWig report/${run}_sorted.bedGraph Ref/<species>/chrom.sizes bw/<label>.bw
```
(UCSC `bedGraphToBigWig`; version not recorded.)

### 4.7 Re-runnability: no

The pipeline runs in `/home/jiajie/.local/share/mamba/envs/bs-seq`, the environment of a
**different user** that does not exist on this server; `bismark`, `trimmomatic` and
`bedGraphToBigWig` are all absent from PATH. `analysis/4.BS/` is also a copy — the Bismark
logs name `/mnt/sda/jackie/cnidaria/4.BS` as the original location, and at least one sample
is missing its trimmed input (`alignment/SRR8346017_bismark.log` reports
`trimmed/SRR8346017_trimmed.fq.gz does not exist`).

The 33 `<ABBR>_BS_*` tables in the database are the methylation output of this pipeline;
the largest holds 93.6 M rows.

---

## 5. What the site publishes as the method

`data_statistics.php:538-543` is the canonical statement of the assay conventions
(reproduced in section 2 above). Two rows of that table are worth flagging to the authors:
the ATAC-seq row quotes `--nomodel`-free parameters while the Exaiptasia script actively
uses `--nomodel --shift -37 --extsize 73`; and the tool versions given there (bowtie2
v2.5.5, MACS2 v2.2.9.1) are correct but `fastqc`, `samtools` and `deepTools` versions are
absent.
