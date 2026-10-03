# NVECT_embryo (GSE302686): why it carries no cell-type names

Checked 2026-09-28. Conclusion first: **the source study published no per-cell or
per-cluster annotation in any retrievable form**, so under 不猜 the dataset stays
`cluster_only`. The rows below are the evidence, kept so this is not re-searched.

## What the dataset is

| | |
|---|---|
| GEO | GSE302686, BioProject PRJNA1291744, SRA SRP601047 |
| Samples | 3 libraries: 8 h, 10 h, 12 h post-fertilisation (wild type only) |
| Deposit | one file, `GSE302686_nv2DevInt.rds` (gzip RDS, 678 MB / 1.79 GB expanded); samples carry `NONE` |
| Paper | Haillot, Lebedeva, Steger, Genikhovich, Montenegro, Cole, Technau. "Segregation of endoderm and mesoderm germ layer identities in the diploblast *Nematostella vectensis*." Nat Commun 2025, doi:10.1038/s41467-025-63287-4, PMID 40858588, PMC12381260 |
| Local | 9,711 cells x 15,643 NV2.x genes after QC; 8h 4,275 / 10h 2,842 / 12h 2,594 |

Note the GEO series title is a longer variant of the paper title, which is why a
PubMed title search on it finds nothing.

## The deposited object carries no names (read directly, R 4.3.3 + Seurat 5.5.0)

`meta.data` columns, complete: `orig.ident`, `nCount_RNA`, `nFeature_RNA`,
`nCount_SCT`, `nFeature_SCT`, `S.Score`, `G2M.Score`, `Phase`, `CCDiff`,
`percent.cox1`, `rib`, `SCT_snn_res.0.2`, `seurat_clusters`. No `celltype`,
no germ-layer column. `Idents` are `"0".."6"` (7 clusters); `seurat_clusters`
holds 15 per-sample ids (`8h_0`, `12h_1`, …). A string scan of the decompressed
RDS finds only `seurat_clusters` — no `ectoderm`/`mesoderm`/`endoderm` anywhere.

All 9,711 of our cells are in the object, so a join would be clean — there is
simply nothing to join.

## Where labels were looked for, and what was there

- **GEO**: supplementary is the RDS alone. No barcode→type table, no cluster table.
- **The paper's text/supplements**: the only scRNA figure is Supplementary Fig. 1,
  a per-timepoint UMAP with marker expression overlaid. Its legend attaches
  identities to *genes*, never to clusters: ectodermal `apc`, `koza-like 1`;
  mesodermal `tbx19-like`, `pitx1-like`, `snailA`; endodermal `foxA`, `brachyury`.
  "cluster" appears 3× in the body text; none assigns an identity to a cluster
  number. Supplementary Table 1 is a primer table; the source-data spreadsheet has
  20 sheets, all in situ/qPCR/blot quantification, none about scRNA clusters.
- **The paper's own analysis script** (`github.com/technau/NemVecEndoderm`,
  `ScDevTimeSeries.R`, 5,178 bytes, read in full): QC, SCTransform, cell-cycle
  regression, merge, then `FeaturePlot_scCustom(..., split.by="orig.ident")`. **No
  `FindClusters`, no `FindNeighbors`, no annotation step at all.**
- **Broad SCP**: holds no Nematostella study. **cellxgene**: no cnidarian collection.
  **Zenodo**: R scripts only (`technau/NemVecEndoderm`-era zips).
- **Sibling series GSE307733** (same lab, ships labelled Seurat objects): its samples
  are 4/12/24/48 hpa aggregates, 24 hpf gastrula, and DMSO/LY treatments — **no
  8/10/12 hpf embryos**, so its published germ-layer labels (which the site already
  carries for `NVECT_gastruloid` and `NVECT_notch48h`) do not cover this dataset.
- **Partition mismatch**: the paper says its analysis *also used* 18 and 24 hpf data
  from refs 15/16, and never states a resolution or cluster count — so even a
  cluster-level key would not transfer to the deposited 8/10/12 hpf-only object.

## The one thing that does exist: the lab's own NV2 annotation

`github.com/technau/NemVecEndoderm/nv2.func-04.04.23.tsv.gz` (24,539 rows,
`geneID  nve  gene_short_name  cdsName  short_sprot  …`) is the lab's own
functional annotation of the NV2 gene set, and it names their 12 plotted markers
(`manuMarkers` in the script):

| NV2 | lab name | |
|---|---|---|
| NV2.11441 | foxa-1 | endodermal marker in the paper |
| NV2.10624 | brachyury | endodermal |
| NV2.472 | snaila | mesodermal |
| NV2.15833 | tbx22-like | mesodermal family (site DB calls the same gene TBX19-like) |
| NV2.15303 | apc | ectodermal |
| NV2.2150 | gsc2-like, NV2.234 hd071-nk-like, NV2.6608 neurogenin1, NV2.22508 erg, NV2.8483 fgfa1, NV2.9419 ada1b-like-7, NV2.10891 unnamed | not named in the legend |

This is a *gene* annotation, not a cell annotation — useful for a marker-based
de-novo attempt, and useful generally because it is an NV2→symbol map, which the
site's own tables do not have (`NVECT_cellmarker.symbol` is `-` for all 10,794 NV2
genes it lists).

## What would be needed to name cells here, and why it was not done

Any of these would be a *de-novo* label, not an inherited one, and each has a
problem the site's own rule (04: "a wrong cell-type label is worse than no label
at all") says not to paper over:

1. **Marker-based germ-layer naming** from the paper's own markers. Blocked on
   coverage: the paper names seven markers, and the ectodermal pair (`apc`,
   `koza-like 1`) is the weakest link — `apc` is a WNT-pathway gene expressed
   broadly, and the layers at 8–12 h are graded territories rather than discrete
   types (the paper itself: "at 8 hpf, only clusters of mesodermal and ectodermal
   cells are clearly identifiable"). One label per Leiden cluster, when clusters
   are largely timepoint-driven, would overstate what the data show.
2. **Transfer from the site's adult NV2.x datasets** (`NVECT_2month`, `_nervous`,
   `_tentacle`, …), which match the embryo's genes at 81–88% — technically above
   the 35% floor in `work/label_transfer_published.py`. Biologically wrong: an
   8–12 hpf embryo has no differentiated cnidocytes, neurons, gland cells or
   tentacle cells. Rejected on those grounds, not on the bridge.
3. **Transfer from the germ-layer-labelled sibling datasets** (`NVECT_gastruloid`,
   `NVECT_notch48h`) — the biologically closest vocabulary, but the gene bridge is
   only 20–22%, below the script's floor, and their cells are 24 h+ reaggregates.

If the site ever wants labels here, option 1 is the defensible one, but it needs a
decision from the site owner about acceptable inference, plus a per-dataset marker
panel (04 ships one global panel, matched against symbols, which NV2 ids never hit).

## A prior attempt, and what it shows

`data/annotated/NVECT_embryo.label_transfer.tsv` (2026-09-28 00:35) is the audit
from an earlier run of `work/label_transfer_published.py` against an adult NV2.x
reference. It is worth keeping for one reason: it shows the refusal came from the
**ambiguity rule, not from a thin gene bridge** — every cluster had 12–57 reference
markers mapped, and all 17 were declined on near-ties (e.g. cluster 14: Neural 0.939
vs Developing cnidocytes, family margin 0.019; cluster 8: Gastrodermis 0.68 vs
Secretory-Mucous, 0.101). No annotation was written (the h5ad is untouched, 9-20),
so the dataset is still exactly as 04 left it. The near-ties are themselves the
signal that an 8–12 hpf embryo does not contain the adult reference's cell types —
the clusters sit equidistant between adult identities, which is what a germ-layer
territory looks like to an adult-cell classifier.
