#!/usr/bin/env python
"""Regenerate NVECT_whole_adult.markers.tsv with real gene names.

The first build constructed the SEACell-level marker object without var_names,
so the marker table came out keyed by column index ("7405").  Every one of those
missed 05_export_web.py's `g in expr.var_names` filter, so the export shipped
markers.json pointing at genes that had no expression file and 0 expression
files on disk.  The h5ad itself is fine -- this recomputes only the markers,
from the same matrix, rather than rewriting 3.2 GB.
"""
import pandas as pd
import scanpy as sc
import anndata as ad
import numpy as np

ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"
ID = "NVECT_whole_adult"

a = ad.read_h5ad(f"{ROOT}/data/annotated/{ID}.annotated.h5ad")
print("h5ad:", a.shape, "| first genes:", list(a.var_names[:3]), flush=True)

# one row per SEACell, first cell of each -- same construction as the builder
sac_of_cell = pd.factorize(a.obs["seacell"].astype(str), sort=True)
codes = sac_of_cell[0]
rep = np.zeros(codes.max() + 1, dtype=np.int64)
seen = {}
for i, c in enumerate(codes):
    if c not in seen:
        seen[c] = i
rep = np.array([seen[i] for i in range(codes.max() + 1)])

sa = ad.AnnData(X=np.asarray(a.X[rep]))
sa.var_names = a.var_names
lab = a.obs["cell_type"].astype(str).to_numpy()[rep]
sa.obs["cell_type"] = pd.Categorical(lab)
print("marker units:", sa.shape[0], "types:", sa.obs["cell_type"].nunique(), flush=True)

sc.tl.rank_genes_groups(sa, "cell_type", method="wilcoxon", n_genes=100, pts=True)
mk = sc.get.rank_genes_groups_df(sa, group=None)
mk = mk.rename(columns={"group": "cell_type", "names": "gene",
                        "logfoldchanges": "log2fc", "pvals_adj": "padj",
                        "pct_nz_group": "pct_in", "scores": "score"})
mk.to_csv(f"{ROOT}/data/annotated/{ID}.markers.tsv", sep="\t", index=False)
print("marker rows:", len(mk), "| genes in var_names:",
      int(mk["gene"].isin(a.var_names).sum()), "| sample:", list(mk["gene"][:3]))
