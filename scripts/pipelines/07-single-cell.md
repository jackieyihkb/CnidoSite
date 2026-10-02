# CnidoSite — single-cell ingest and re-analysis

The ingest code lives in **`/home/jackie/sc_ingest/`** — outside the docroot and outside
`cnidosite-tools`, which is why it is documented here rather than shipped as a script
directory. Three families of datasets went through three different routes.

---

## 1. The binary asset contract

Every dataset the UMAP viewer reads is a set of files in `singlecell_data/<dataset>/`.
The format contract is written in the docstring of `step2_export.py:1-30`:

| file | dtype | shape | meaning |
|---|---|---|---|
| `embedding.bin` | float32 | n × 2 | UMAP coordinates |
| `cellmeta.bin` | uint16 | n × 3 | [cell type, cluster, sample] **indices** |
| `qc.bin` | uint16 | n × 3 | [nCount, nGene, pct_mt] |
| `genes.json` | — | — | gene order for the expression files |
| `expression/<gene>.bin.gz` | — | — | CX1 container: `"CX1"`, float32 vmax, uint32 count, then (uint32 delta, uint8 val) pairs on log1p(CP10K) |
| `markers.json`, `composition.json`, `manifest.json` | — | — | per-dataset metadata |

`cellmeta.bin` and `qc.bin` are **uint16**. Writing them as another integer type produces
files whose byte length still checks out — the size assertion passes and the mistake only
shows up when values are decoded. See `docs/DATA_OVERVIEW.md` for how to validate.

---

## 2. Family A — imported from a published object (ACOER, *Aurelia coerulea* lifecycle)

Source dataset GSE242144 / SRP458012. Five steps, run in order from `/home/jackie/sc_ingest/`:

```sh
python3 step1_scan.py           # scan the source object, build the cell × gene matrix
python3 step1b_wilcoxon.py      # per-cluster Wilcoxon marker tests
python3 step2_export.py         # write embedding/cellmeta/qc/expression binaries
python3 step2b_figures.py       # render the static figures
python3 step4_install_assets.py --apply     # copy into singlecell_data/ and index
```

`step4_install_assets.py` is the shared installer. Run without `--apply` it is a dry run;
`--stage DIR` points it at a different dataset (`step4_install_assets.py:27-28, 38-45`).

Input: `/home/jackie/sc_ingest/GSE242144/ucsc/{exprMatrix.tsv.gz, meta.tsv,
UMAP.coords.tsv.gz, dataset.json}`.
Output: `/var/www/html/CnidoSite/singlecell_data/` (a symlink to the data volume).
Run logs confirm execution: `step1.log`, `step1b.log`, `step2.log` — 63,230 cells ×
25,954 genes × 8 cell types from 16 libraries.

---

## 3. Family B — re-analysed from a Seurat object (OARBU, *Oculina arbuscula*)

Source SRP513328. The authors of the source study deposited a Seurat object; it was
exported to MatrixMarket and re-analysed in Python.

```sh
Rscript OARBU/dump.R            # readRDS("oculina_seurat.rds"); writeMM() to MatrixMarket
python3 OARBU/step1_analyse.py  # QC → normalise → HVG → PCA → Harmony → neighbours → Leiden → UMAP
python3 OARBU/step1b_stats.py
python3 OARBU/step2_export.py
python3 OARBU/step2b_figures.py
```

Every parameter is a named constant in `OARBU/step1_analyse.py:58-63`, called at `:161-235`:

| step | setting | line |
|---|---|---|
| QC | `MIN_CELLS=3`, `NMADS=5.0`, `MIN_GENES=200`, `MIN_COUNTS=500` | :58-63 |
| doublets | `scrublet.Scrublet(sub, expected_doublet_rate=0.06, random_state=0)` | :167 |
| HVG / PCA | `N_HVG=2000`, `N_PCS=30` | :58-63 |
| batch correction | `harmonypy.run_harmony(A.obsm["X_pca"], A.obs, ["library"], max_iter_harmony=20, random_state=0)` | :213-214 |
| neighbours | `sc.pp.neighbors(A, n_neighbors=15, use_rep="X_pca_harmony")` | :224 |
| clustering | `sc.tl.leiden(A, resolution=1.0, key_added="leiden", flavor="igraph")` | :225 |
| embedding | `sc.tl.umap(A, min_dist=0.5, spread=1.0, random_state=0)` | :233 |

The source object had already been filtered with scDblFinder by its own authors
(`step1_analyse.py:20-27`); scrublet was applied on top.

**Version discrepancy to settle.** The site's tools table describes the single-cell module
as **Seurat v4.4 / LogNormalize**, but the re-analysis on disk is Python throughout —
scanpy + harmonypy + scrublet, Python 3.11, numpy 2.4.6 (`OARBU/step1b.log:2-48`,
`OARBU/pip.log`). Both statements can be true of different datasets, but the site text
attributes Seurat to the module as a whole. See `TO-BE-SUPPLIED.md` item 8.

---

## 4. Family C — the other 15 published datasets — `[GAP]`

The `manifest.json` of each of those datasets records
`pipeline_version: cnidosite-sc-1.0.0`, but **the exporter that produced them is not on this
server**. A whole-disk search for a script writing `embedding.bin` / `cellmeta.bin` finds
only families A and B. See `TO-BE-SUPPLIED.md` item 6.

---

## 5. Gene-ID cross-reference (sidecar)

The site's gene IDs and each dataset's own gene IDs are different namespaces. The mapping
is built by reciprocal best hit (diamond) against each species' proteome and stored as
`singlecell_data/_gene_ids.tsv` → per-dataset `gene_ids.json`:

```sh
php includes/sc_gene_ids_build.php            # build
php includes/sc_gene_ids_build.php --check    # validate only
```

Evidence and rationale: `sc_gene_ids_build.php:9-13`, `includes/sc_gene_ids.php:4-28`.
Current state: 53,707 rows, 0 unmapped. Where a dataset gene has no counterpart in the
site's annotation, the data layer keeps the dataset's own ID and **never guesses** —
several datasets ship their own namespace (e.g. NV2.8285) and that is correct behaviour.

Four traps in building this mapping (all recorded in the site's own notes): the protein
FASTA may contain git conflict markers; the reverse database must be built from the query,
not the subject; TSV fields need embedded tabs stripped; and ID variants must be normalised
before matching.

---

## 6. Cell-type abbreviation dictionary

Only `HVULG_siebert_atlas` carries the source study's own abbreviations. The dictionary is
generated separately:

```sh
python3 /home/jackie/sc_ingest/build_sc_celltype_key.py --check
python3 /home/jackie/sc_ingest/build_sc_celltype_key.py --write
```

Output: `/var/www/html/CnidoSite/data/sc_celltype_key.json`, read at runtime by
`includes/sc_celltype_key.php`. Note that the term `unassigned`, which appears in the
released tables, is never defined in any of the 17 source scripts.

---

## 7. Site-side scripts that are display-only

`includes/gene_epigenome_panel.php`, `includes/gene_singlecell_panel.php`,
`includes/sc_celltype_key.php`, `includes/sc_gene_ids.php` and `includes/sc_gene_ids_build.php`
contain method prose and data contracts but **no command lines** — each was checked
individually. The ingest commands are all in `/home/jackie/sc_ingest/`, reproduced above.
