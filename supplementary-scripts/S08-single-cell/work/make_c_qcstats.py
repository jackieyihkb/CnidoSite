#!/usr/bin/env python
"""Write data/qc/NVECT_whole_adult.qc_stats.json.

06_aggregate_qc_table.py treats a dataset as processed only if this file exists
(see its `if not os.path.exists(qc_p)` branch), so without it the registry row
falls back to "not processed" even though the dataset is built and exported.

No cell-level QC was run here, and the record says so rather than inventing a
filter: the deposit is the authors' own pre-filtered object, so there are no
empty droplets to call and nothing was removed.  The distributions below are
measured from the object as deposited -- nFrags is a genuine per-cell ATAC
measure; the gene count is the number of genes with non-zero activity in the
cell's SEACell, which is why it is high (the matrix is 92.7% non-zero).
"""
import json
from datetime import datetime, timezone

import anndata as ad
import numpy as np

ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"
ID = "NVECT_whole_adult"

a = ad.read_h5ad(f"{ROOT}/data/annotated/{ID}.annotated.h5ad", backed="r")
obs = a.obs


def dist(x):
    x = np.asarray(x, dtype=float)
    return {
        "min": float(np.min(x)), "p5": float(np.percentile(x, 5)),
        "median": float(np.median(x)), "mean": float(np.mean(x)),
        "p95": float(np.percentile(x, 95)), "max": float(np.max(x)),
    }


counts, genes = dist(obs["n_counts"]), dist(obs["n_genes"])
per_sample = {str(k): int(v) for k, v in obs["sample"].value_counts().items()}
n = int(a.n_obs)
samples = sorted(per_sample)

rec = {
    "dataset_id": ID,
    "species": "Nematostella vectensis",
    "class": "Hexacorallia",
    "bioproject": "PRJNA1249376",
    "sra_study": "SRP577940",
    "geo_series": "GSE294388",
    "tissue_organ": "whole-adult",
    "stage": "Adult tissues/organs",
    "platform_class": "scATAC-seq (gene activity)",
    "pipeline_version": "cnidosite-sc-1.0.0",
    "processed_utc": datetime.now(timezone.utc).isoformat(timespec="seconds"),
    "n_cells_raw": n,
    "n_genes_raw": int(a.n_vars),
    "n_samples": len(samples),
    "samples": samples,
    "doublet_method": "none - authors' pre-filtered object; no doublet calling applied here",
    "thresholds": {
        "mode": "none",
        "source": (
            "No cell-level filtering was applied. The deposit is the study's own "
            "pre-called cell set, so there are no empty droplets for a UMI floor "
            "to remove and no doublet scores to threshold. Counts shown are nFrags."
        ),
    },
    "mito_genes_n": 0,
    "mito_genes": "",
    "mito_strategy": "none",
    "mito_filtering_available": False,
    "mito_distribution_raw": {},
    "mito_distribution_final": {},
    "counts_distribution_raw": counts,
    "counts_distribution_final": counts,
    "genes_distribution_raw": genes,
    "genes_distribution_final": genes,
    "n_genes_dropped_low_detection": 0,
    "n_genes_retained": int(a.n_vars),
    "cells_removed_by_criterion": {
        "low_genes": 0, "low_counts": 0, "high_counts": 0,
        "high_mt": 0, "mad_outlier": 0,
    },
    "n_cells_after_cell_qc": n,
    "n_doublets_removed": 0,
    "doublet_rate": 0.0,
    "n_cells_final": n,
    "pct_cells_retained": 100.0,
    "cells_per_sample": per_sample,
    "qc_h5ad": "",
    "runtime_sec": 0.0,
}
out = f"{ROOT}/data/qc/{ID}.qc_stats.json"
with open(out, "w") as fh:
    json.dump(rec, fh, indent=2, default=str)
print("wrote", out)
print("nFrags  : min %.0f median %.0f max %.0f" % (counts["min"], counts["median"], counts["max"]))
print("genes   : min %.0f median %.0f max %.0f" % (genes["min"], genes["median"], genes["max"]))
print("samples :", len(samples))
