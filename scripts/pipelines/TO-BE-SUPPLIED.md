# Command lines that are not on this server

The reviewer asked for the command line of every bioinformatic analysis. Everything that
could be recovered from the working directories, the run logs and the response letter is in
the other files in this directory, marked `[RUN]`.

The 18 items below could **not** be recovered. They are listed here rather than
reconstructed, because a plausible-looking command line that was never executed is worse
than an acknowledged gap. Each entry records what was searched and what evidence does
survive, so whoever supplies the command can check it against the same sources.

Ordering is by importance to the reviewer's request, not by module.

---

## Status: seven of the eighteen are now closed

This list was written from a search of one server. Seven entries have since been closed from
the material deposited under [`../../supplementary-scripts/`](../../supplementary-scripts/) —
the scripts were on the working tree all along, and it was the *invocation* that was missing,
not the command:

| # | Item | Closed by |
|---|---|---|
| 1 | Species tree | OrthoFinder, MAFFT, BMGE and the four IQ-TREE runs recovered from `S07-comparative-genomics/tree/`. **Only the AMAS concatenation and the `drop3`/`drop5` alignments remain open** |
| 2 | DHS `--nomodel --shift` | Closed by MACS2's own log banner: the published peaks *are* the `--nomodel --shift -100 --extsize 200` re-call |
| 3 | ChIP per-sample peak calls | Closed by the MACS2 log banners (`chip_gfix.sh`, `logs_bampe/`, `logs_broad/`) |
| 4 | TE commands | Closed: the served tables come from `TE_pipeline/`, which is deposited under `S04-transposable-elements/` |
| 5b | Proteomics | Closed by `S09-proteomics/2.pipeline/` — the Comet and Crux/Percolator commands survive |
| 13 | Co-expression networks | Closed by `S06-co-expression/` — construction scripts and edge thresholds recovered |
| 14 | Core orthologs, gene trees | Closed by `S07-comparative-genomics/orthology/` — the six-step pipeline and the FastTree command |

The remaining eleven stand. Five of them — MAG assembly/binning/taxonomy, miRNA-seq,
macro- and microsynteny, and the Paleobiology ingestion — have no script on any reachable
host at all, so no request to the authors can produce them; the other six are recorded below
with what survives. Items 1, 2, 3 and 4 are retained in full because the searches they record
are still the evidence for *why* the recovered command is the right one.

---

## The five that matter most

### 1. Species tree — MAFFT, BMGE, AMAS, OrthoFinder and both IQ-TREE runs

> **CLOSED, except AMAS.** The chain is recovered — see 08-comparative-genomics.sh §1–3.
> What remains genuinely missing from this item is the AMAS concatenation and the
> construction of the drop3 and drop5 alignments.

**Missing:** the exact commands for the whole chain, and the 70-orthogroup / 24,975-site
supermatrix they consumed.

**Searched:** `find /mnt/sda/jackie -maxdepth 4` for `*.iqtree`, `*.treefile`, `*.contree`,
`*aln*.fa*`, `*.phy`, `*.nex` → zero results; whole-machine search for an OrthoFinder
binary → zero results; `~/.bash_history` → the only phylogenetics command is a re-rooting
step (`tools/reroot.py new.nwk data/cnidaria.nwk --outgroup-file og.txt --ladderize`, and
`og.txt` no longer exists).

**Surviving evidence:** the response letter gives both IQ-TREE invocations (reproduced in
`08-comparative-genomics.sh`), and states the upstream chain and parameters. That is a
method description, not a run record. The 70-OG matrix is not on this server at all.

**Also to confirm:** the letter says IQ-TREE v3.1.2; the binary here is v3.1.3.

### 2. DNase-seq / DHS — the `--nomodel --shift` question

> **CLOSED.** MACS2 prints its invocation into the log banner of every run, so the re-call
> is a run record, not a template. The published peaks ARE the `--nomodel --shift -100
> --extsize 200` re-call — see `06-epigenome.md` §3.

**Missing:** the alignment command, the peak-calling command, and the per-sample re-call log.

**Why it matters:** the review turns on whether `--nomodel --shift` was used. There are two
contradictory records on disk:

* the response letter states the string `--nomodel --shift -100 --extsize 200` "appears
  nowhere in this site, in the analysis tool tree, or in any backup snapshot";
* `cnidosite_epigenome_recheck_2026-09-22.txt:41` records the DHS re-run as
  `new=5802 (--nomodel --shift -100 --extsize 200, d=200)`, against
  `old=8145 (default model, d=260)`;
* separately, `analysis/3.ATAC/Exaiptasia_diaphana/1.sh:81-83` and
  `run_atac_pipeline.sh:81-83` contain an **active** MACS2 call using
  `--nomodel --shift -37 --extsize 73` (the Tn5 convention), while the corresponding
  blocks in the Hydra and Nematostella scripts are commented out.

**Searched:** `find /home/jackie /mnt/sda/jackie /var/www` for `*recall*`, `*re-call*`,
`*rerun*`, `*peakcall*`, `*macs*`. Only the two count summaries were found. The re-run tree
lives at `143.89.54.44:/mnt/sda/jackie/cnidaria/{5.ChIP,6.DHS}` and is not reachable here.

**Needed:** from that host, `5.ChIP/macs2*.sh`, `6.DHS/*.sh` and their run logs — plus a
decision on which record is correct.

### 3. ChIP-seq — the per-sample peak-calling commands

> **CLOSED.** Recovered from the MACS2 log banners (`chip_gfix.sh`,
> `logs_bampe/*_macs2.log`, `logs_broad/*_macs2.log`) — see `06-epigenome.md` §2.

**Missing:** the original commands and the 2026-09-22 re-call commands.

**Surviving evidence:** a 95-line count summary
(`/home/jackie/cnidosite_epigenome_rerun_2026-09-22.txt`) whose header states the old
convention (`-f BAM` everywhere; `-g 2.61e8` for every ChIP sample regardless of species)
and the new one (`-f BAMPE`; `--broad --broad-cutoff 0.1` for H3K27me3, H3K36me3, H4K20me1
and H3K4me1`), plus a 353 MB pre-re-run database snapshot
(`/home/jackie/backups-20260922/epigenome-reload/peak_tables_before.sql`).

**Note:** the `-g 2.61e8` and `-f BAM` values are read from the summary's header, not from
any command line. The re-call log is, per the letter itself, no longer on this machine.

### 4. Transposable elements — the commands behind the released data

> **CLOSED.** The served tables come from `TE_pipeline/`, deposited under
> `../../supplementary-scripts/S04-transposable-elements/` — see
> `02-transposable-elements.sh` §1. The pipeline described below is the *second*
> implementation, which did not produce them.

**Missing:** the RepeatModeler v2.0.7 / RepeatMasker v4.2.1 commands that produced the
3.7 M released TE records (580,259 of them AIDSS).

**Searched:** the whole `genome_TE/` tree and `assembly.sh`. The EDTA pipeline in
`cnidaria_omics/genome_TE/TE_pipeline/` is real and fully scripted, but it **completed on
only 3 of 145 species and all three were killed during the LINE stage**; its output
directory is empty. `assembly.sh:113-117` uses `-engine ncbi`, not `rmblast`, and
hard-codes the species string to *Clytia hemisphaerica*.

**Also relevant:** `te_anno.yml`, the frozen environment export that `00_setup_env.sh:36`
is supposed to write, does not exist — so even the EDTA runs cannot be reproduced to
sub-package version.

### 5. Transcriptome functional annotation — BLAST / DIAMOND / eggNOG

**Missing:** the commands that produced the per-species annotation packages
(`download/transcriptome_assembly/<Species>.tar.gz`, 229 packages, each with
`_annotation.tsv`, `_uniprot.tsv`, `_nr.tsv`, `_pfam.tsv`, `_panther.tsv`,
`_interpro.tsv`, `_go.tsv`, `_kegg.tsv`).

**Searched:** the RNA-seq tree, `others/`, `cnidosite-tools`, and every PHP file in the
docroot. The site's tools table lists only `InterProScan v.5.67-97.0 -f tsv -goterms -pa`.
No BLAST, DIAMOND, eggNOG or TransDecoder run is recorded for this module.

---

## The remaining items

(The heading said *thirteen* before the seven closures above. Eleven still stand; the three
rows marked **CLOSED** are kept so the searches they record remain visible.)

| # | Module | Missing | Surviving evidence |
|---|---|---|---|
| 5b | **Proteomics re-analysis** — **CLOSED**, see Status | only the per-dataset `comet.params` beyond the archived example | the commands are recovered in `11-proteome.md`, from the scripts in `S09-proteomics/2.pipeline/` |
| 6 | **Single-cell, family C** | the exporter that produced the 15 published datasets (`pipeline_version: cnidosite-sc-1.0.0`) | `sc_ingest/` holds only the ACOER and OARBU families; whole-disk search for a writer of `embedding.bin` / `cellmeta.bin` finds nothing else |
| 7 | **RNA-seq strand flag** | which single-end template was used: `--rna-strandness F` (`run_analysis.sh:34`) or `RF` (every other SE line) | both templates on disk; no successful run log exists |
| 8 | **Single-cell platform** | whether the module should be described as Seurat v4.4 / LogNormalize (site) or scanpy + harmonypy + scrublet (code on disk: `OARBU/step1_analyse.py`, Python 3.11, numpy 2.4.6) | both |
| 9 | **Assembly / binning of the 315 MAGs** | MEGAHIT / SPAdes / metaWRAP / MetaBAT / CONCOCT / MaxBin commands | none of those binaries exist on this server; `metaG/` is a download directory only |
| 10 | **MAG quality** | the CheckM2 1.1.0 invocation | `tools/mag_pipeline/fill_mags_checkm.sql:4` names CheckM2 1.1.0 and its DIAMOND database in a comment; the file contains only UPDATE statements |
| 11 | **MAG taxonomy** | GTDB-Tk command | not installed, no trace |
| 12 | **MAG ORF prediction** | prodigal / prokka commands | both installed in the base environment, neither has a project run |
| 13 | **Co-expression networks** — **CLOSED**, see Status | the negative-edge MR floor is inconsistent between drafts (`MR < 30` against `MR < 50`) and needs confirming against the delivered edge lists | recovered in `05-transcriptome-rnaseq.sh` §9, from `S06-co-expression/` |
| 14 | **Core ortholog / gene trees** — **CLOSED**, see Status | the driver invocations only; the scripts themselves survive | recovered in `08-comparative-genomics.sh` §4–5, from `S07-comparative-genomics/orthology/` |
| 15 | **Macro- and microsynteny** | the macrosyntR and Pansyn invocations | only the viewer pages (`macrosynteny.php`, `microsynteny*.php`, `js/macrosynteny.js`); no R script, no Snakemake file, no `macrosyntR`/`pansyn` string anywhere. The letter's parameters (Fisher exact, BH q < 0.001, 30-anchor minimum, `igraph::cluster_fast_greedy`) are descriptions |
| 16 | **Divergence-time dating** | the dating software and its command | fossil calibrations are recorded in `assembly.sh:264-273`; the tool is never named |
| 17 | **Hi-C scaffolding** | the juicer + 3D-DNA commands | `assembly.sh:4` names the tools, and the mamba line installs them; no invocation exists |
| 18 | **ANNEVO and Helixer** | ANNEVO: genome path, model file, output, thread count. Helixer: whether it was run at all | ANNEVO ran for real — `ANNEVO/nohup.out` ends with "The gene annotation took 27204.6 seconds" and produced `jellyfish_annevo.gff3` — but the driver prints no argv and `~/.bash_history` has only `cd ANNEVO/`. `Helixer/` is a clean v0.3.6-1-g7d5941e checkout with no log, no output, and one `ls` in history |
| 18b | **EVidenceModeler, ANNEVO-based run** | the EVM invocation using `PASA/annevo/weight.txt` | `assembly.sh:229` records a *different* weight set (BRAKER3 8 / GeMoMa 5 / PASA_threemethods 10); the ANNEVO weights (ANNEVO 10 / GeMoMa 6 / transdecoder 10) are in `weight.txt` with no command attached |

---

## Software versions never recorded anywhere

`fastqc`, `samtools` and `deepTools` for the ATAC module (the versions installed *now* are
not necessarily the versions used); UCSC `bedGraphToBigWig` for the WGBS module; AUGUSTUS
in the BRAKER3 container; the dating software; and the assembly/binning tools for the MAGs.
The site's tools table and the scripts agree on the rest.

---

## How to close these out

For items 1–5, 9–15 and 17 the fastest route is the working tree on the second host
(`143.89.54.44:/mnt/sda/jackie/`), which holds `5.ChIP/`, `6.DHS/` and the assembly and
synteny trees. For item 1 the supermatrix itself is also needed, not just the command.

For items 13 and 14 the authors will need to say where the construction ran, since no
server reachable from here holds it.

Once supplied, each command should be dropped into the matching file in this directory as
`[RUN]` with its `# from: <path>:<line>` provenance, and the corresponding row deleted from
the table above.
