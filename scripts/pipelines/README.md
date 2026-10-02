# Bioinformatics pipelines behind CnidoSite

Reviewer comment #2 asked for the command lines of every bioinformatics analysis, in the
style of the supplementary text of Zhou et al. 2018. This directory is the machine-readable
half of that answer; the typeset half is the Word document *CnidoSite — reproducible
methods* submitted with the manuscript.

## How to read these files

**Nothing in this directory is a runnable pipeline.** Each file is a record of what was
actually executed, with the provenance of every line. Where a line could not be recovered it
is marked, never guessed. The four marks are:

| Mark | Meaning |
|---|---|
| `[RUN]` | A verbatim command line was recovered from a script, a log, or a SAM `@PG` header. Copied **exactly**, including typos. |
| `[TEMPLATE]` | A command exists in a generator script but is *not* what produced the released data (the run used a different template, or the line is commented out). Kept because it documents the intent. |
| `[PROSE]` | Only a method description exists (in the response letter or on the site). No command line is on disk. **Do not cite this as a command.** |
| `[GAP]` | Not recoverable from this server. Listed in [`TO-BE-SUPPLIED.md`](TO-BE-SUPPLIED.md). |

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
the `[RUN]` source wins.

## Index

| File | Module | What is recoverable |
|---|---|---|
| [`01-genome-assembly.sh`](01-genome-assembly.sh) | Genome assembly, Hi-C, polishing, mitogenome | Full command chain for one species |
| [`02-transposable-elements.sh`](02-transposable-elements.sh) | TE annotation (EDTA) | Pipeline recovered; ran on 3 of 145 species |
| [`03-gene-annotation.sh`](03-gene-annotation.sh) | BRAKER3 / PASA / ANNEVO / GeMoMa / EVM | BRAKER3, PASA, GeMoMa, EVM recovered; ANNEVO and Helixer are gaps |
| [`04-busco.sh`](04-busco.sh) | BUSCO completeness | Single-species runs recovered; the 148-species batch is a gap |
| [`05-transcriptome-rnaseq.sh`](05-transcriptome-rnaseq.sh) | RNA-seq → TPM, co-expression | RNA-seq chain recovered; network construction is a gap |
| [`06-epigenome.md`](06-epigenome.md) | ATAC / ChIP / DNase / WGBS | ATAC and WGBS recovered; ChIP/DHS re-call log lost |
| [`07-single-cell.md`](07-single-cell.md) | Single-cell ingest and re-analysis | Two of three families recovered |
| [`08-comparative-genomics.sh`](08-comparative-genomics.sh) | OrthoFinder, species tree, synteny | Mostly gaps — the trees were built off-box |
| [`09-metagenome-mags.sh`](09-metagenome-mags.sh) | MAG annotation | KofamScan + InterProScan recovered; assembly/binning are gaps |
| [`10-database-build.sh`](10-database-build.sh) | Loading the curated data into MySQL | Fully recovered |
| [`11-proteome.md`](11-proteome.md) | Proteomics re-analysis (Comet + Percolator) | Parameters recovered; the engine is not on this server |
| [`TO-BE-SUPPLIED.md`](TO-BE-SUPPLIED.md) | — | The 18 items only the authors can supply |
