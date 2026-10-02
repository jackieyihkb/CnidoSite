# How each dataset in CnidoSite was produced

This file is the index. It maps **a table in the database** to **the command line that filled
it** and to **the software that ran**. The command records themselves are in
[`../scripts/pipelines/`](../scripts/pipelines/); the typeset version, submitted as
supplementary material, is the Word document *CnidoSite — reproducible methods*.

## How to read a command record

Nothing in `scripts/pipelines/` is a runnable pipeline. Each file records what was actually
executed, and marks every line with its provenance:

| Mark | Meaning |
|---|---|
| `[RUN]` | A verbatim command line recovered from a script, a log, or a SAM `@PG` header. Copied exactly, typos included, with a `# from: <path>:<line>` note. |
| `[TEMPLATE]` | The command exists in a generator script but is **not** what produced the released data — the run used a different template, or the line is commented out. |
| `[PROSE]` | Only a method description survives (in the response letter or on the site). **Not citable as a command.** |
| `[GAP]` | Not recoverable from this server. Itemised in [`../scripts/pipelines/TO-BE-SUPPLIED.md`](../scripts/pipelines/TO-BE-SUPPLIED.md). |

A `[TEMPLATE]` is never a `[RUN]`. Two modules produced different commands than their own
scripts claim, and in both cases that matters: see `06-epigenome.md` §3 (the DHS re-call, which
turns on the `--nomodel --shift` question) and §4 (Bismark, where the recovered command comes
from Bismark's own `@PG CL:` line and disagrees with the template).

---

## Dataset → table → command record

| # | Dataset | Database objects | Record | Verdict |
|---|---|---|---|---|
| 1 | Genome assembly, Hi-C scaffolding, polishing, mitogenome | `speciesinfo`, `data/genome_assembly_cache/`, `mito_genome`, `mitochondrion` | [`01-genome-assembly.sh`](../scripts/pipelines/01-genome-assembly.sh) | Recovered end-to-end for one species; Hi-C scaffolding is a gap (item 17) |
| 2 | Transposable elements | TE tables (~3.7 M records, 580,259 AIDSS) | [`02-transposable-elements.sh`](../scripts/pipelines/02-transposable-elements.sh) | **Released data is a gap** (item 4); the EDTA pipeline is recovered but completed on 3 of 145 species |
| 3 | Gene annotation | `trans_assembly` (13,111,410 rows), 145 `<ABBR>_locus` tables, `<ABBR>_go/_ipr/_nr/_pfam/_panther/_kegg/_uniprot` | [`03-gene-annotation.sh`](../scripts/pipelines/03-gene-annotation.sh) | BRAKER3, PASA, GeMoMa, EVM recovered; ANNEVO and Helixer are gaps (item 18) |
| 4 | BUSCO completeness | `busco`, `speciesinfo.BUSCO`, `core_species.busco90` | [`04-busco.sh`](../scripts/pipelines/04-busco.sh) | Single-species runs recovered; the 148-species batch is a gap |
| 5 | RNA-seq expression | 31 `<ABBR>_TPM` tables, `sample`, `trans_assembly_species`, `data/rnaseq_samples.json` | [`05-transcriptome-rnaseq.sh`](../scripts/pipelines/05-transcriptome-rnaseq.sh) | Chain recovered; the strand flag has two contradictory templates (item 7) |
| 6 | Co-expression networks | `<ABBR>_coexpress_positive` / `_negative` | [`05-transcriptome-rnaseq.sh`](../scripts/pipelines/05-transcriptome-rnaseq.sh) | **Gap** (item 13) — built off-box; only the released tables and the site's `CNIDO_NET_TOP_K=100` cap survive |
| 7 | Proteomics re-analysis | `proteomic_datasets` (`cnido_*` and `orig_*` columns), per-dataset peptide/protein tables | [`11-proteome.md`](../scripts/pipelines/11-proteome.md) | Parameter **values** recovered; every command is a gap (item 5b), and the engine is not installed on this host |
| 8 | ATAC-seq | peak tables, coverage BigWigs, `data/` sample inventory | [`06-epigenome.md`](../scripts/pipelines/06-epigenome.md) §1–2 | Recovered |
| 9 | ChIP-seq | peak tables for four histone marks | [`06-epigenome.md`](../scripts/pipelines/06-epigenome.md) §3 | **Re-call log lost** (item 3) — old and new conventions survive only as a count summary |
| 10 | DNase-seq / DHS | peak tables | [`06-epigenome.md`](../scripts/pipelines/06-epigenome.md) §3 | **Gap** (item 2), and it is the `--nomodel --shift` question the review turns on |
| 11 | WGBS methylation | 33 `<ABBR>_BS_*` tables (up to 93.6 M rows) | [`06-epigenome.md`](../scripts/pipelines/06-epigenome.md) §4 | Recovered from Bismark's own `@PG` line |
| 12 | Single-cell atlases | `singlecell` (33 entries), `singlecell_celltype`, `singlecell_atlas_map`, `singlecell_data/` | [`07-single-cell.md`](../scripts/pipelines/07-single-cell.md) | Two of three ingest families recovered; the third is a gap (item 6) |
| 13 | Core orthologs, gene trees | `core_og` (1,017), 67,795 families in `/genetree/` | [`08-comparative-genomics.sh`](../scripts/pipelines/08-comparative-genomics.sh) | **Mostly gaps** (item 14); the tier trees are FastTree `-nosupport` |
| 14 | Species tree | 153 tips (148 cnidarians + 5 outgroups) | [`08-comparative-genomics.sh`](../scripts/pipelines/08-comparative-genomics.sh) | **Gap** (item 1) — the 70-orthogroup / 24,975-site supermatrix is not on this server |
| 15 | Macro- and microsynteny | separate schema `jackie_db` | [`08-comparative-genomics.sh`](../scripts/pipelines/08-comparative-genomics.sh) | **Gap** (item 15) — only the viewer pages survive |
| 16 | MAGs and their annotation | `MAGs` (315 rows), `mag_annot`, `mag_interpro`, `mag_go_terms`, `mag_kegg_terms`, `mag_pfam_hits`, `mag_panther_hits` | [`09-metagenome-mags.sh`](../scripts/pipelines/09-metagenome-mags.sh) | KofamScan + InterProScan recovered; assembly, binning, CheckM2 and GTDB-Tk are gaps (items 9–12) |
| 17 | The database itself | all 1,541 tables | [`10-database-build.sh`](../scripts/pipelines/10-database-build.sh) | **Fully recovered** |

---

## Software and versions

Versions as recorded in the command records. Where two versions appear for one tool, both are
real — they are listed rather than reconciled, because the discrepancy itself is unresolved.

| Software | Version | Module |
|---|---|---|
| GenomeScope | 1.0 | assembly |
| hifiasm, purge_dups, NextPolish2, redundans | *not recorded* | assembly |
| QUAST, MitoZ, MitoFinder | *not recorded* | assembly |
| Trimmomatic | 0.40 | RNA-seq |
| fastp | 0.23.4 | RNA-seq |
| HISAT2, samtools | samtools 1.21 | RNA-seq |
| StringTie | 3.0.3 | RNA-seq |
| Trinity | *not recorded* | annotation |
| PASA | 2.5.3 | annotation |
| BRAKER3 | *container* | annotation |
| GeMoMa | *not recorded* | annotation |
| EVM | 2.1.0 | annotation |
| RepeatModeler | v2.0.7 **and** 2.0.8 | TE |
| RepeatMasker | v4.2.1 **and** 4.2.3 | TE |
| EDTA | v2.2.2 | TE |
| BUSCO | v5.8.2 **and** v6.0.0 | completeness |
| DIAMOND | 0.9.24.125 | annotation |
| InterProScan | 5.67-99 (site) **and** 5.78-109 (MAGs) | annotation, MAGs |
| KofamScan | *not recorded* | MAGs |
| CheckM2 | 1.1.0 | MAGs |
| MACS2 | v2.2.9.1 | epigenome |
| Bismark | v0.25.1 | WGBS |
| FastQC, samtools, deepTools, bedGraphToBigWig | **not recorded for the module that used them** | epigenome |
| Comet | 2026.01 | proteomics |
| Crux / Percolator | 4.2 | proteomics |
| OrthoFinder | v2.5.5 | comparative |
| MAFFT | v7.526 | comparative |
| BMGE | v1.12 | comparative |
| AMAS | *not recorded* | comparative |
| IQ-TREE | v3.1.2 (letter) **and** v3.1.3 (binary here) | comparative |
| FastTree | 2.1.11 | comparative |
| Seurat | v4.4 | single-cell |
| scanpy / harmonypy / scrublet | *code on disk, versions in its own environment* | single-cell |
| prodigal | 2.6.3 | MAGs |
| prokka | 1.15.6 | MAGs |

The site's own tools table agrees with this list apart from the entries marked *not recorded*.

---

## What is not reproducible, in one paragraph

Eighteen items could not be recovered and are listed with the searches that failed to find
them in [`../scripts/pipelines/TO-BE-SUPPLIED.md`](../scripts/pipelines/TO-BE-SUPPLIED.md).
The three that matter most: the **species tree** (the supermatrix is not on this server), the
**ChIP/DHS peak-calling commands** (the re-call log was on a different host and is gone — and
this is the module the review questions), and the **commands behind the released TE
annotation** (the EDTA pipeline on disk completed on 3 of 145 species, so it is not what
produced the 3.7 M released records). Those command lines are marked `[GAP]` rather than
reconstructed. A plausible-looking command that was never executed would be worse than an
acknowledged gap, because it would be reproducible in appearance only.

---

## Relationship to the other files

* [`DATA_OVERVIEW.md`](DATA_OVERVIEW.md) — what is *in* the database, with the SQL for every count.
* [`DEPLOYMENT.md`](DEPLOYMENT.md) — how to stand a local copy up, including the four
  environment variables that replace the credentials the archive strips.
* [`../scripts/pipelines/`](../scripts/pipelines/) — the command records indexed above.
* `../manifests/large-files.tsv` — every file over 1 MiB that is not in this repository, with
  its size and measured HTTP reachability.
