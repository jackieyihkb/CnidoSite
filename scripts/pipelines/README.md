# Bioinformatics pipelines behind CnidoSite

Reviewer comment #2 asked for the command lines of every bioinformatics analysis, in the
style of the supplementary text of Zhou et al. 2018. This directory is the machine-readable
half of that answer; the typeset half is the Word document **CnidoSite — Supplementary
Methods**, submitted with the manuscript as `CnidoSite_supplementary_methods.docx`.

The two halves describe the same analyses and cite the same evidence. Where a section of
the document carries more than the corresponding file here — because the document quotes a
script that is not in this repository — the document is the fuller record, and the section
reference in the index below says so.

## How to read these files

**Nothing in this directory is a runnable pipeline.** Each file is a record of what was
actually executed, with the provenance of every line. Where a line could not be recovered it
is marked, never guessed. The four marks are:

| Mark | Meaning | Equivalent in the supplementary text |
|---|---|---|
| `[RUN]` | A verbatim command line was recovered from a script, a log, or a SAM `@PG` header. Copied **exactly**, including typos. | *(unmarked)* |
| `[TEMPLATE]` | A command exists in a generator script but is *not* what produced the released data (the run used a different template, or the line is commented out). Kept because it documents the intent. | *(unmarked)*, with the caveat stated in the text |
| `[PROSE]` | Only a method description exists (in the response letter or on the site). No command line is on disk. **Do not cite this as a command.** | `[UNVERIFIED — README only]` |
| `[GAP]` | Not recoverable from this server. Listed in [`TO-BE-SUPPLIED.md`](TO-BE-SUPPLIED.md). | listed in the Appendix |

Every `[RUN]` command carries a `# from: <path>:<line>` note. Paths like
`/mnt/sda/jackie/...` are this group's working tree; they are recorded as they were, not
rewritten, so that the citation remains checkable.

## A caution that applies to the whole set

Two of the modules produced **different commands than their own scripts claim**:

* the DHS peak calling was re-run with `--nomodel --shift -100 --extsize 200`, which appears
  in no script — only in a run summary ([`06-epigenome.md`](06-epigenome.md) §3);
* Bismark was run with `--parallel 120` against a genome directory that the template script
  spells differently — the recovered command comes from Bismark's own `@PG CL:` line
  ([`06-epigenome.md`](06-epigenome.md) §4).

Both cases are exactly why `[TEMPLATE]` is not the same as `[RUN]`. When the two disagree,
the `[RUN]` source wins. This is the same rule the supplementary text applies, and both
cases are recorded there as well (§S10).

## A working tree that is *not* CnidoSite provenance

`/mnt/sda/jackie/PASA/jellyfish_final/` holds a complete assembly, polishing and structural
annotation chain — BRAKER3, PASA, GeMoMa, EVidenceModeler, ANNEVO — for **one jellyfish
genome of a separate project**. It is a well-documented chain and it is easy to mistake for
CnidoSite's own; the notebook's own `--species=nematostella_vectensis` flag makes it look
like it belongs here, though that flag selects an AUGUSTUS training profile and does not
name the assembly.

**It is not the provenance of any CnidoSite dataset.** The gene, transcript and protein
models CnidoSite serves are the NCBI-submitted annotations; where an assembly has no NCBI
protein set — most of the MAGs — genes are predicted with Prodigal 2.6.3. No BRAKER,
AUGUSTUS, MAKER or EVM invocation exists for the CnidoSite per-species annotations.
[`01-genome-assembly.sh`](01-genome-assembly.sh) and section 3 of
[`03-gene-annotation.sh`](03-gene-annotation.sh) record that chain **for reference only**,
marked as external; the supplementary text states the same exclusion in §S1 and §S3.2.

## Index

| File | Module | Supplementary text | What is recoverable |
|---|---|---|---|
| [`01-genome-assembly.sh`](01-genome-assembly.sh) | Genome assembly, Hi-C, polishing, mitogenome | **not covered** — §S1 excludes it | Full command chain for one species, **of a separate genome project**; not CnidoSite provenance |
| [`02-transposable-elements.sh`](02-transposable-elements.sh) | TE annotation (EDTA) | §S4 | The pipeline that produced the served tables (`TE_pipeline/`) is recovered; the second implementation in the working tree assigns `region` by a different rule and did not produce them |
| [`03-gene-annotation.sh`](03-gene-annotation.sh) | Gene models and functional annotation | §S3.1–S3.2 | NCBI-submitted gene sets; Prodigal for MAGs; InterProScan, DIAMOND and KofamScan recovered. The BRAKER3/PASA/GeMoMa/EVM chain is a separate project and is marked as such |
| [`04-busco.sh`](04-busco.sh) | BUSCO completeness | §S3.3 | Both modes recovered, including the protein-mode batch driver and the merger, and the genome-mode dispatcher |
| [`05-transcriptome-rnaseq.sh`](05-transcriptome-rnaseq.sh) | RNA-seq → TPM, co-expression | §S5, §S6 | RNA-seq chain recovered; the co-expression scripts (`extract_TPM.pl`, `FPKM_threshold.R`, `PCC_MR_by_WGCNA.R`, the ROC scripts) recovered, with the edge thresholds |
| [`06-epigenome.md`](06-epigenome.md) | ATAC / ChIP / DNase / WGBS | §S10 | ATAC and WGBS recovered; the ChIP and DHS re-calls recovered from the MACS2 log banners |
| [`07-single-cell.md`](07-single-cell.md) | Single-cell ingest and re-analysis | §S8 | Two of three families recovered; the exporter behind the 15 published datasets is a gap |
| [`08-comparative-genomics.sh`](08-comparative-genomics.sh) | OrthoFinder, species tree, gene trees | §S7 | OrthoFinder, MAFFT, BMGE and the four IQ-TREE runs recovered; the AMAS concatenation, the drop3/drop5 alignments and the macrosynteny commands are gaps |
| [`09-metagenome-mags.sh`](09-metagenome-mags.sh) | MAG annotation | §S11 | KofamScan + InterProScan recovered; assembly, binning and taxonomy are gaps |
| [`10-database-build.sh`](10-database-build.sh) | Loading the curated data into MySQL | §S13 | Fully recovered |
| [`11-proteome.md`](11-proteome.md) | Proteomics re-analysis (Comet + Percolator) | §S9 | The Comet and Crux/Percolator commands recovered, with the parameter settings |
| [`12-phenotype-traits.sh`](12-phenotype-traits.sh) | Phenotype, trait data and mitogenomes | §S12 | Recovered |
| [`TO-BE-SUPPLIED.md`](TO-BE-SUPPLIED.md) | — | Appendix | The items only the authors can supply |

Of these, **`01-genome-assembly.sh` is the one file whose contents are not CnidoSite
provenance**; it is retained because the chain is the only complete assembly record on the
working tree, and it is marked throughout so that it cannot be mistaken for this
resource's own methods.
