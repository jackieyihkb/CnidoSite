# How each dataset in CnidoSite was produced

This file is the index. It maps **a table in the database** to **the command line that filled
it** and to **the software that ran**. The command records themselves are in
[`../scripts/pipelines/`](../scripts/pipelines/); the typeset version, submitted as
supplementary material, is the Word document **CnidoSite — Supplementary Methods**
(`CnidoSite_supplementary_methods.docx`).

The scripts those records cite are in [`../supplementary-scripts/`](../supplementary-scripts/),
laid out by supplementary-text section. A `# from: supplementary-scripts/…` path resolves
against that directory.

## How to read a command record

Nothing in `scripts/pipelines/` is a runnable pipeline. Each file records what was actually
executed, and marks every line with its provenance:

| Mark | Meaning | Supplementary text |
|---|---|---|
| `[RUN]` | A verbatim command line recovered from a script, a log, or a SAM `@PG` header. Copied exactly, typos included, with a `# from: <path>:<line>` note. | *(unmarked)* |
| `[TEMPLATE]` | The command exists in a generator script but is **not** what produced the released data — the run used a different template, or the line is commented out. | *(unmarked)*, with the caveat stated |
| `[PROSE]` | Only a method description survives (in the response letter or on the site). **Not citable as a command.** | `[UNVERIFIED — README only]` |
| `[GAP]` | Not recoverable from this server. Itemised in [`../scripts/pipelines/TO-BE-SUPPLIED.md`](../scripts/pipelines/TO-BE-SUPPLIED.md). | the Appendix |

A `[TEMPLATE]` is never a `[RUN]`. Two modules produced different commands than their own
scripts claim, and in both cases that matters: see `06-epigenome.md` §3 (the DHS re-call, which
turns on the `--nomodel --shift` question) and §4 (Bismark, where the recovered command comes
from Bismark's own `@PG CL:` line and disagrees with the template).

---

## Dataset → table → command record

| # | Dataset | Database objects | Record | Verdict |
|---|---|---|---|---|
| 1 | Genome assembly, Hi-C scaffolding, polishing, mitogenome | `speciesinfo`, `data/genome_assembly_cache/`, `mito_genome`, `mitochondrion` | [`01-genome-assembly.sh`](../scripts/pipelines/01-genome-assembly.sh) | **Not CnidoSite provenance.** Assembly is out of scope (supplementary text §S1); the sequences served are public INSDC/NCBI deposits. The chain in the record belongs to a separate genome project and is quarantined as such |
| 2 | Transposable elements | `*_TE` tables, one per species | [`02-transposable-elements.sh`](../scripts/pipelines/02-transposable-elements.sh) | Recovered (§S4). **The served tables come from `TE_pipeline/`, not the `genome_TE/` tree** — the latter assigns `region` by a different rule and completed for nothing |
| 3 | Gene annotation | `trans_assembly`, 145 `<ABBR>_locus` tables, `<ABBR>_go/_ipr/_nr/_pfam/_panther/_kegg/_uniprot` | [`03-gene-annotation.sh`](../scripts/pipelines/03-gene-annotation.sh) | Recovered (§S3.1–S3.2). The gene sets served are the **NCBI-submitted** annotations; Prodigal 2.6.3 supplies genes where NCBI ships no protein set (174 MAG assemblies). The BRAKER3/PASA/GeMoMa/EVM chain is a separate project |
| 4 | BUSCO completeness | `busco`, `speciesinfo.BUSCO`, `core_species.busco90` | [`04-busco.sh`](../scripts/pipelines/04-busco.sh) | Recovered (§S3.3) — both the protein-mode batch (one run per species) and the genome-mode batch (650 runs = 325 assemblies × 2 lineages) |
| 5 | RNA-seq expression | 31 `<ABBR>_TPM` tables, `sample`, `trans_assembly_species`, `data/rnaseq_samples.json` | [`05-transcriptome-rnaseq.sh`](../scripts/pipelines/05-transcriptome-rnaseq.sh) | Chain recovered (§S5); the single-end strand flag has two contradictory templates (item 7) |
| 5b | Transcript functional annotation | the 229 `<Species>.tar.gz` annotation packages | (no record) | **Gap** — the runs behind the 229 packages do not survive |
| 6 | Co-expression networks | `<ABBR>_coexpress_positive` / `_negative` | [`05-transcriptome-rnaseq.sh`](../scripts/pipelines/05-transcriptome-rnaseq.sh) §9 | Recovered (§S6) — TPM extraction, the per-sample cutoff, the WGCNA PCC/MR matrices and the ROC threshold selection, with the edge thresholds |
| 7 | Proteomics re-analysis | `proteomic_datasets` (`cnido_*` and `orig_*` columns), per-dataset peptide/protein tables | [`11-proteome.md`](../scripts/pipelines/11-proteome.md) | Recovered (§S9) — the Comet search command, the uniform parameter settings and the Crux/Percolator FDR step. Per-dataset `comet.params` beyond the archived example is a gap |
| 8 | ATAC-seq | peak tables, coverage BigWigs, `data/` sample inventory | [`06-epigenome.md`](../scripts/pipelines/06-epigenome.md) §1 | Recovered |
| 9 | ChIP-seq | peak tables for four histone marks | [`06-epigenome.md`](../scripts/pipelines/06-epigenome.md) §2 | Recovered from the MACS2 log banners (§S10); the per-sample driver scripts live on another host |
| 10 | DNase-seq / DHS | peak tables | [`06-epigenome.md`](../scripts/pipelines/06-epigenome.md) §3 | Recovered from the MACS2 log banner. The published peaks are the `--nomodel --shift -100 --extsize 200` re-call |
| 11 | WGBS methylation | 33 `<ABBR>_BS_*` tables (up to 93.6 M rows) | [`06-epigenome.md`](../scripts/pipelines/06-epigenome.md) §4 | Recovered from Bismark's own `@PG` line |
| 12 | miRNA-seq | 53 datasets reported by the site | (no record) | **Gap** — no miRNA pipeline, tool or file exists on any reachable host. The largest single gap in the epigenome module |
| 13 | Single-cell atlases | `singlecell` (33 entries), `singlecell_celltype`, `singlecell_atlas_map`, `singlecell_data/` | [`07-single-cell.md`](../scripts/pipelines/07-single-cell.md) | Two of three ingest families recovered (§S8); the exporter behind 15 published datasets is a gap (item 6) |
| 14 | Core orthologs, gene trees | `core_og` (1,017), 67,795 families in `/genetree/` | [`08-comparative-genomics.sh`](../scripts/pipelines/08-comparative-genomics.sh) §4–5 | Recovered (§S7) — the six-step pipeline and the FastTree `-nosupport` trees |
| 15 | Species tree | 153 tips (148 cnidarians + 5 outgroups) | [`08-comparative-genomics.sh`](../scripts/pipelines/08-comparative-genomics.sh) §1–3 | OrthoFinder, MAFFT, BMGE and all four IQ-TREE runs recovered (§S7); the AMAS concatenation and the `drop3`/`drop5` alignments are gaps (item 1) |
| 16 | Macro- and microsynteny | separate schema `jackie_db` | [`08-comparative-genomics.sh`](../scripts/pipelines/08-comparative-genomics.sh) §7 | **Gap** (item 15) — only the viewer pages survive |
| 17 | Phenotype and trait data | `phenotype`, `phenotype_v2`, `phenotype_refs` | [`10-database-build.sh`](../scripts/pipelines/10-database-build.sh) §2b | Recovered (§S12). The 410 fossil records behind `paleobiology.php` are a gap |
| 18 | Mitochondrial genomes | `mito_genome`, `mitochondrion` (173 species, 3,555 features) | [`10-database-build.sh`](../scripts/pipelines/10-database-build.sh) §2b | Recovered; records are imported from NCBI, not assembled here |
| 19 | MAGs and their annotation | `MAGs` (315 rows), `mag_annot`, `mag_interpro`, `mag_go_terms`, `mag_kegg_terms`, `mag_pfam_hits`, `mag_panther_hits` | [`09-metagenome-mags.sh`](../scripts/pipelines/09-metagenome-mags.sh) | KofamScan + InterProScan recovered; assembly, binning, CheckM2 and GTDB-Tk are gaps (items 9–12) |
| 20 | The database itself | all 1,541 tables + 2 views | [`10-database-build.sh`](../scripts/pipelines/10-database-build.sh) | **Fully recovered** |

---

## Software and versions

Versions as recorded in the command records. Where two versions appear for one tool, both are
real — they are listed rather than reconciled, because the discrepancy itself is unresolved.

The authoritative list, with the evidence for each version, is the **Software versions**
section of [`../supplementary-scripts/MANIFEST.md`](../supplementary-scripts/MANIFEST.md).
The table below is the same list restricted to the modules indexed above.

| Software | Version | Module |
|---|---|---|
| fastp | 0.23.4 | RNA-seq |
| HISAT2 | 2.2.2 | RNA-seq |
| samtools | 1.21 (htslib 1.23) RNA-seq; 1.23.1 epigenome | RNA-seq, epigenome |
| StringTie | 3.0.3 | RNA-seq |
| deepTools bamCoverage | 3.5.6 | RNA-seq, ATAC |
| R / Perl (co-expression) | *not recorded* (Perl 5.34.0) | co-expression |
| WGCNA, gplots, ROCR, pROC | *not recorded* | co-expression |
| BUSCO | 5.8.3 (protein mode) **and** 6.0.0 (genome mode) | completeness |
| Prodigal | 2.6.3 | annotation, MAGs |
| InterProScan | 5.78-109.0 (MAG and annotation pipeline) **and** 5.67-97.0 (site tool table) | annotation, MAGs |
| DIAMOND | v2 (`uniprot_sprot.dmnd`, release 226) | annotation, MAGs |
| HMMER3 hmmscan / hmmpress (Kofam) | *not recorded* | annotation, MAGs |
| EDTA | 2.2.2 | TE |
| RepeatModeler / RepeatMasker | 2.0.7 / 4.2.1 (inside EDTA) | TE |
| Python (TE pipeline) | 3.12.11 | TE |
| OrthoFinder | 3.1.0 | comparative |
| MAFFT | *not recorded* | comparative |
| BMGE | *not recorded* (1.12 defaults) | comparative |
| AMAS | *not recorded* | comparative |
| IQ-TREE | 3.1.2 | comparative |
| FastTree | *not recorded* | comparative |
| STAR (STARsolo mode) | 2.7.11b | single-cell |
| scanpy / anndata | 1.12.4 / 0.13.3.post0 | single-cell |
| scrublet / harmonypy | 0.2.3 / 2.0.2 | single-cell |
| leidenalg / igraph | 0.12.0 / 1.0.0 | single-cell |
| pandas / matplotlib | 3.0.6 / 3.11.2 | single-cell |
| fasterq-dump, Seurat, Matrix | *not recorded* | single-cell |
| Comet | 2026.01 rev. 1 (e4f767c) | proteomics |
| Crux (Percolator) | 4.2 (inner Percolator 3.06.nightly-2) | proteomics |
| ThermoRawFileParser | 2.0.0.dev | proteomics |
| Bismark | 0.25.1 | WGBS |
| Bowtie2 | 2.5.5 | WGBS, ATAC, ChIP, DNase |
| Trimmomatic | 0.40 | WGBS |
| FastQC | 0.12.1 | ATAC |
| MACS2 | 2.2.9.1 | ATAC, ChIP, DNase |
| UCSC bedGraphToBigWig | *not recorded* | WGBS |
| CheckM2 | 1.1.0 | MAGs |
| MySQL server | 8.0.46 | database |
| PHP / Apache | 7.4 / 2.4 | web resource |

**A note on the *not recorded* entries.** Several of these tools have an installed binary that
reports a version — MAFFT 7.526, BMGE 1.12, FastTree 2.1.11, OrthoFinder 2.5.5, InterProScan
5.67-99, DIAMOND 0.9.24.125. A binary's own version is not evidence of what a run used: none of
those numbers was printed into a log by the run that produced the released data, and in two
cases (OrthoFinder, DIAMOND) the installed version differs from the one the record supports.
They are therefore listed as *not recorded* rather than promoted to a version.

---

## Two counts that do not yet reconcile

Both are flagged rather than resolved, because settling either needs the production database:

* **Transposable elements.** The supplementary text records **58 species tables,
  46,286,391 elements** (§S15). The archive's own `DATA_OVERVIEW.md` records **~3.7 M TE
  records, 580,259 of them AIDSS**. These differ by more than a factor of ten and cannot both
  describe the same table. One of the two is measuring something else.
* **Predicted transcripts.** The supplementary text records **5,156,916**; `DATA_OVERVIEW.md`
  records **13,111,410 rows in `trans_assembly`**. Possibly different units (transcripts vs
  rows including isoforms), but that has not been established.

---

## What is not reproducible, in one paragraph

The items that could not be recovered are listed with the searches that failed to find them in
[`../scripts/pipelines/TO-BE-SUPPLIED.md`](../scripts/pipelines/TO-BE-SUPPLIED.md), and the
families with no command anywhere are named in the supplementary text's Appendix. The four that
matter most: **assembly, binning and taxonomy of the 315 MAGs**; **miRNA-seq**, where 53
datasets are reported and no pipeline, tool or file exists; **macrosynteny and microsynteny**;
and the **Paleobiology Database ingestion** behind the 410 fossil records. For those, the
analysis itself is absent from every reachable host rather than merely uncollected, so no
request to the authors can produce the commands. Alongside them, the **AMAS concatenation**
and the **`drop3`/`drop5` alignments** behind the species tree ran off-box and left no run
record. Those command lines are marked `[GAP]` rather than reconstructed — a plausible-looking
command that was never executed would be worse than an acknowledged gap, because it would be
reproducible in appearance only.

---

## Relationship to the other files

* [`DATA_OVERVIEW.md`](DATA_OVERVIEW.md) — what is *in* the database, with the SQL for every count.
* [`DEPLOYMENT.md`](DEPLOYMENT.md) — how to stand a local copy up, including the four
  environment variables that replace the credentials the archive strips.
* [`../scripts/pipelines/`](../scripts/pipelines/) — the command records indexed above.
* [`../supplementary-scripts/`](../supplementary-scripts/) — the analysis scripts those records
  cite, with a `MANIFEST.md` giving each file's size, SHA-256 prefix and the software versions
  recorded for each module.
* `../manifests/large-files.tsv` — every file over 1 MiB that is not in this repository, with
  its size and measured HTTP reachability.
