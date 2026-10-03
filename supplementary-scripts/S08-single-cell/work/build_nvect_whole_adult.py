#!/usr/bin/env python
"""Build the NVECT whole-adult (GSE294388) interactive dataset.

WHY THIS IS BUILT FROM THE DEPOSIT RATHER THAN FROM READS
---------------------------------------------------------
The deposit is single-cell ATAC (ArchR).  The finest *per-cell* products -- the
per-cell GeneScoreMatrix and the peak matrix -- live in ArrowFiles that the
deposit does not ship (the repo's copies are symlinks to paths that are absent).
Aligning the 25 SRA runs again (129 GB) would not fix that: the peaks sit in the
same coordinate space as the local RefSeq genome, but self-computed gene activity
would come out in `LOC*` space, which bridges to this site's `NV2.*` Nematostella
space only 12.7% of the time (3,111 of 24,526) -- worse than the space the deposit
already uses.  So the alignment route buys no gene-space gain.

WHAT IS ACTUALLY AVAILABLE, AND WHAT IT IS
------------------------------------------
  * per-cell UMAP, 52,881 cells                (X_umap.cells.csv)
  * 16 published cell types per cell           (cellColData.tsv, `cell_type`)
  * cell -> SEACell map                        (SEACell_annotation.tsv)
  * 19,714 x 788 gene-activity scores          (GSE294388_Matrix-Gene-Scores-SEACell-FC.rds)

Per-cell activity is therefore the activity of the cell's own SEACell.  That is
METACELL-RESOLUTION ACTIVITY DRAWN ON PER-CELL COORDINATES -- every cell in a
SEACell carries that SEACell's vector -- and it is not a per-cell measurement.
Observed cells per SEACell: min 11, median 67, max 309.  The manifest says this
in `annotation_note`; it must not be presented as per-cell ATAC signal.

Markers are computed with the SEACell as the unit of observation (693 adult
SEACells), not the 51,866 cells, because the cells within a SEACell are copies
of one another and treating them as independent would inflate every p-value.
`normalize_total(1e4)`+`log1p` is row-wise, so the SEACell-level matrix and the
per-cell matrix normalise to the same values -- the ranking is exactly what the
feature plots show, only the sample size differs.
"""
import faulthandler
import json
import os
import time
from datetime import datetime, timezone

import anndata as ad
import numpy as np
import pandas as pd
import scanpy as sc

# The gather below allocates ~4 GiB and ran for minutes with no visible output
# when stdout was block-buffered to a file; dump the stack periodically so a
# stall is diagnosable instead of a mystery.
faulthandler.dump_traceback_later(180, repeat=True, exit=False)

W = "/mnt/sda/jackie/cnidaria/codex/singlecell/work"
ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"
OUT = os.path.join(ROOT, "data/annotated")

DATASET_ID = "NVECT_whole_adult"
SPECIES = "Nematostella vectensis"
SOURCE = dict(
    bioproject="PRJNA1249376", sra_study="SRP577940", geo_series="GSE294388",
    tissue_organ="whole-adult", stage="Adult tissues/organs",
    cells_reported="60000", cls="Hexacorallia",
)

t0 = time.time()
sc.settings.verbosity = 1


# ---------------------------------------------------------------- inputs ----
def load_inputs():
    cc = pd.read_csv(f"{W}/repo/cellColData.tsv", index_col=0, low_memory=False)
    # cellColData.tsv is comma-delimited despite the extension (od -c confirmed);
    # reading it as tab-delimited yields one giant key.
    ann = pd.read_csv(f"{W}/repo/SEACell_annotation.tsv", sep="\t")
    um = pd.read_csv(f"{W}/repo/X_umap.cells.csv", index_col=0)
    genes = open(f"{W}/genematrix_SEACell.genes.txt").read().split("\n")[:-1]
    cols = open(f"{W}/genematrix_SEACell.cols.txt").read().split("\n")[:-1]
    M = np.fromfile(f"{W}/genematrix_SEACell.bin", dtype="<f4").reshape(
        len(genes), len(cols), order="F")
    return cc, ann, um, genes, cols, M


def main():
    cc, ann, um, genes, cols, M = load_inputs()
    print(f"[{DATASET_ID}] deposit: {M.shape[0]} genes x {M.shape[1]} SEACells")

    # ---- adult cells ------------------------------------------------------
    adult = ann[ann["stage"] == "adult"].copy()
    adult["cell"] = adult["cell"].astype(str)
    adult_ids = set(adult["cell"])          # hoisted: rebuilding this inside the
    cells = [c for c in cc.index.astype(str) if c in adult_ids]   # loop is O(n^2)
    print(f"  adult cells: {len(cells)} (of {cc.shape[0]} in cellColData)", flush=True)

    # restrict to adult cells in cellColData order for a reproducible row order
    sub = cc.loc[cells]
    seacell_of = adult.drop_duplicates("cell").set_index("cell")["SEACell"].astype(str)
    cell_seacell = seacell_of.loc[cells].to_numpy()

    # ---- SEACell axis -----------------------------------------------------
    col_ix = {c: i for i, c in enumerate(cols)}
    sac_names = sorted(set(cell_seacell), key=lambda s: col_ix[s])
    sac_col = np.array([col_ix[s] for s in sac_names])
    sac_ix = {s: i for i, s in enumerate(sac_names)}     # dict, not list.index:
    sac_of_cell = np.array([sac_ix[s] for s in cell_seacell])   # list.index is O(n)
    Ms = M[:, sac_col]                                   # genes x adult SEACells
    print(f"  adult SEACells: {Ms.shape[1]}", flush=True)

    # ---- per-cell matrix = the cell's SEACell row -------------------------
    # dense, because the deposited matrix is 92.7% non-zero: CSR would cost
    # 8 bytes/nnz (~7.6 GB) against 4 bytes/entry (~4.1 GB) for the dense block.
    print("  gathering per-cell activity ...", flush=True)
    X = np.ascontiguousarray(Ms[:, sac_of_cell].T)        # cells x genes, float32
    print(f"    {X.shape} {X.nbytes/2**30:.2f} GiB", flush=True)

    # detected-gene count is a property of the SEACell, so count it once
    det_sac = (Ms > 0).sum(axis=0).astype(np.int32)
    n_genes = det_sac[sac_of_cell]

    # ---- observations -----------------------------------------------------
    obs = pd.DataFrame(index=pd.Index(cells, name=None))
    obs["sample"] = sub["Sample"].astype(str).to_numpy()
    obs["cluster"] = sub["Clusters"].astype(str).to_numpy()
    obs["cell_type"] = sub["cell_type"].astype(str).to_numpy()
    obs["n_counts"] = sub["nFrags"].to_numpy(dtype=np.float32)
    obs["n_genes"] = n_genes
    # scATAC has no mitochondrial fraction.  NaN here becomes -1 in qc.bin via
    # 05_export_web's nan_to_num, which is what the site already ships for the
    # other ATAC-derived dataset (NVECT_tentacle: mito_strategy "none").
    obs["pct_mt"] = np.nan
    obs["species"] = SPECIES
    obs["seacell"] = cell_seacell
    print(f"  cell types: {obs['cell_type'].nunique()}   samples: {obs['sample'].nunique()}"
          f"   clusters: {obs['cluster'].nunique()}")

    # ---- assemble ---------------------------------------------------------
    a = ad.AnnData(X=X, obs=obs)
    a.var_names = pd.Index(genes, name=None)
    a.obsm["X_umap"] = um.loc[cells, ["0", "1"]].to_numpy(dtype=np.float32)
    del X, Ms, M

    # Same normalisation as the pipeline (04_cluster_annotate.py:235-236).  No
    # `.raw` slot: X is not scaled afterwards here, so a copy would be a second
    # 4 GB of identical values.  05_export_web falls back to X when raw is absent.
    sc.pp.normalize_total(a, target_sum=1e4)
    sc.pp.log1p(a)
    print(f"  normalised: X max {a.X.max():.3f}")

    # ---- markers, with the SEACell as the unit ----------------------------
    # one row per adult SEACell, drawn from the same normalised values
    seen: dict[int, int] = {}
    for i, s in enumerate(sac_of_cell):
        if s not in seen:
            seen[s] = i
    rep = np.array([seen[i] for i in range(len(sac_names))])
    sa = ad.AnnData(X=np.asarray(a.X[rep]))
    # Without this the marker table comes out keyed by column index ("7405"),
    # every one of which then misses the expression export's var_names filter --
    # it silently exported 0 expression files.
    sa.var_names = a.var_names
    # A SEACell's group label is the majority of its members' *published* labels;
    # the per-cell obs keeps each cell's own label, which is what the viewer shows.
    ct_cell = sub["cell_type"].astype(str).to_numpy()
    sac_lab = (pd.Series(ct_cell).groupby(sac_of_cell)
               .agg(lambda s: s.value_counts().idxmax()).to_numpy())
    sa.obs["cell_type"] = pd.Categorical(sac_lab)
    print(f"  marker units: {sa.shape[0]} SEACells across {sa.obs['cell_type'].nunique()} types")
    print(sa.obs["cell_type"].value_counts().to_string())

    sc.tl.rank_genes_groups(sa, "cell_type", method="wilcoxon", n_genes=100, pts=True)
    mk = sc.get.rank_genes_groups_df(sa, group=None)
    mk = mk.rename(columns={"group": "cell_type", "names": "gene",
                            "logfoldchanges": "log2fc", "pvals_adj": "padj",
                            "pct_nz_group": "pct_in", "scores": "score"})
    print(f"  marker rows: {len(mk)}")

    # ---- persist ----------------------------------------------------------
    faulthandler.cancel_dump_traceback_later()
    os.makedirs(OUT, exist_ok=True)
    mk.to_csv(f"{OUT}/{DATASET_ID}.markers.tsv", sep="\t", index=False)
    (a.obs.groupby("cell_type", observed=True).size().sort_values(ascending=False)
     ).to_csv(f"{OUT}/{DATASET_ID}.composition.tsv", sep="\t", header=["n_cells"])

    h5 = f"{OUT}/{DATASET_ID}.annotated.h5ad"
    # lzf, not gzip: gzipping a 4 GB float block costs many minutes for a few
    # percent, and nothing here ships this file (only the derived web assets do).
    a.write_h5ad(h5, compression="lzf")
    print(f"  wrote {h5} ({os.path.getsize(h5)/2**30:.2f} GiB)")

    stats = {
        "dataset_id": DATASET_ID,
        "species": SPECIES,
        "pipeline_version": "cnidosite-sc-1.0.0",
        "processed_utc": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "n_cells_in": int(a.n_obs),
        "n_genes_in": int(a.n_vars),
        "normalization": "CPM (target_sum=1e4) then log1p",
        "integration_method": "none (single deposit; no per-cell batch correction)",
        "n_samples": int(obs["sample"].nunique()),
        "n_clusters": int(obs["cluster"].nunique()),
        "n_cell_types": int(obs["cell_type"].nunique()),
        "cell_types": sorted(obs["cell_type"].unique()),
        "n_marker_rows": int(len(mk)),
        "n_marker_units": int(sa.n_obs),
        "marker_unit": "SEACell",
        "annotation_provenance": "published",
        "annotation_note": (
            "Cell types are the authors' published labels (16 types, GSE294388 "
            "cellColData). Expression is gene-activity score, not RNA: per-cell "
            "values are the cell's own SEACell's activity, so all cells in a "
            "SEACell share one vector (metacell-resolution activity on per-cell "
            "coordinates; 11-309 cells per SEACell). Markers use the 693 adult "
            "SEACells as the unit of observation. Gene identifiers are the "
            "authors' Nvec_NVE*, not this site's NV2.*."
        ),
        "cells_per_seacell": {
            "min": int(pd.Series(cell_seacell).value_counts().min()),
            "median": int(pd.Series(cell_seacell).value_counts().median()),
            "max": int(pd.Series(cell_seacell).value_counts().max()),
        },
        "annotated_h5ad": os.path.basename(h5),
        "mito_strategy": "none",
        "mito_filtering_available": False,
        "platform_class": "scATAC-seq (gene activity)",
        "bioproject": SOURCE["bioproject"],
        "sra_study": SOURCE["sra_study"],
        "geo_series": SOURCE["geo_series"],
        "tissue_organ": SOURCE["tissue_organ"],
        "stage": SOURCE["stage"],
        "class": SOURCE["cls"],
        "runtime_sec": round(time.time() - t0, 1),
    }
    with open(f"{OUT}/{DATASET_ID}.annotate_stats.json", "w") as fh:
        json.dump(stats, fh, indent=2, default=str)
    print(f"  done [{stats['runtime_sec']}s]")


if __name__ == "__main__":
    main()
