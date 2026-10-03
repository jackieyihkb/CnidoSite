#!/usr/bin/env python
"""Merge the two STARsolo runs of OARBU (SRP513328) into one count object.

WHY TWO RUNS ARE MERGED RATHER THAN CONCATENATED AT READ LEVEL
--------------------------------------------------------------
A 10x barcode is only unique within a library.  SRR29367137 and SRR29367138 are
two separate Chromium libraries of the same study, so the same barcode string
appears in both and almost certainly belongs to two different cells.  Merging at
the count level with a per-run barcode prefix -- and recording `sample` = run so
04_cluster_annotate integrates them with Harmony -- is what keeps them apart.

GENE NAMES
----------
STARsolo counted against the Ensembl `jaOcuArbu1.hap1.1` annotation, so var_names
come out as `ENSPTBG...`.  The study's own tables (and the site's published OARBU
marker table) use gene symbols -- `CTSB`, `EPDR1.1`, `GSTM3.3` -- with `gNNNN`
for the models their annotation never named.  The display name here is therefore
`Oarb_<symbol>` where a symbol exists, `Oarb_<ENSPTBG...>` otherwise, so the two
OARBU pages are comparable and the viewer's gene search works on names a reader
recognises.  The `Oarb_` prefix follows AMILL/SPIST/OPATA; it also does not break
the cnidarian marker panel, which anchors on `[A-Za-z0-9]` boundaries and `_` is
not one.

Symbols are not unique in this annotation (9,605 named rows carry 5,853 distinct
names), and duplicate var_names would collide in the export, which writes one
file per name.  Ties are broken by total counts: the highest-expressing model
keeps `Oarb_<symbol>`, the rest fall back to their stable `ENSPTBG` id.
"""
import gzip
import json
import os
import shutil
import sys

import anndata as ad
import numpy as np
import pandas as pd
import scanpy as sc
import scipy.sparse as sp

W = "/mnt/sda/jackie/cnidaria/codex/singlecell/work"
ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"
RUNS = ["SRR29367137", "SRR29367138"]
ID = "OARBU_symbiotic"
OUT = os.path.join(ROOT, "data/raw", ID)


def load_symbols():
    sym = {}
    with open(f"{W}/oarbu_gene_symbols.tsv") as fh:
        for ln in fh:
            p = ln.rstrip("\n").split("\t")
            if len(p) >= 2 and p[1].strip():
                sym[p[0]] = p[1].strip()
    print(f"  ensembl symbols: {len(sym)}", flush=True)
    return sym


def read_run(run):
    d = f"{W}/solo/{run}/Solo.out/Gene/filtered"
    if not os.path.isdir(d):
        raise SystemExit(f"missing {d} -- STARsolo has not finished {run}")
    # STARsolo writes the triplet UNCOMPRESSED, and this scanpy's read_10x_mtx
    # only probes for `matrix.mtx.gz` -- given the plain file it raises
    # "Did not find file .../matrix.mtx.gz" rather than falling back.  (The
    # pipeline's own load_10x_dir has the same latent break: it calls
    # read_10x_mtx whenever it sees canonical filenames.)  Gzipping is cheaper
    # than a second reader, and makes the directory canonical for everything
    # downstream.
    for base in ("matrix.mtx", "barcodes.tsv", "features.tsv"):
        src = os.path.join(d, base)
        if os.path.exists(src) and not os.path.exists(src + ".gz"):
            with open(src, "rb") as fi, gzip.open(src + ".gz", "wb") as fo:
                shutil.copyfileobj(fi, fo, 1 << 22)
    a = sc.read_10x_mtx(d, var_names="gene_ids", cache=False)
    a.obs_names = [f"{run}_{b}" for b in a.obs_names]
    a.obs["sample"] = run
    print(f"  {run}: {a.n_obs} barcodes x {a.n_vars} genes", flush=True)
    return a


def main():
    os.makedirs(OUT, exist_ok=True)
    parts = [read_run(r) for r in RUNS]

    # Same reference for both runs, so a gene-order mismatch would mean the
    # index changed between them -- that must stop the merge, not be aligned.
    g0 = list(parts[0].var_names)
    for a in parts[1:]:
        if list(a.var_names) != g0:
            raise SystemExit("gene order differs between runs; refusing to merge")
    print(f"  gene order identical across {len(parts)} runs", flush=True)

    a = ad.concat(parts, join="outer", merge="same") if len(parts) > 1 else parts[0]
    a.obs_names_make_unique()
    print(f"  merged: {a.n_obs} cells x {a.n_vars} genes", flush=True)
    print("  barcodes per run before filtering:",
          a.obs["sample"].value_counts().to_dict(), flush=True)

    X = a.X.tocsr()
    X.data = np.rint(X.data).astype(np.int32)
    totals = np.asarray(X.sum(axis=1)).ravel()

    # ---- display names ----------------------------------------------------
    sym = load_symbols()
    cand = pd.Series([sym.get(g, "") for g in a.var_names], index=a.var_names)
    named = cand[cand != ""]
    # a symbol may be claimed by several models; the biggest gets the symbol
    claim = {}
    if len(named):
        tot = pd.Series(np.asarray(X.sum(axis=0)).ravel(), index=a.var_names)
        rank = (pd.DataFrame({"sym": named, "tot": tot.loc[named.index]})
                .sort_values("tot", ascending=False))
        for s, g in zip(rank["sym"], rank.index):
            claim.setdefault(s, g)
    display = pd.Index([
        f"Oarb_{sym[g]}" if g in a.var_names and sym.get(g) and claim.get(sym[g]) == g
        else f"Oarb_{g}"
        for g in a.var_names
    ])
    n_sym = int(sum(1 for g, d in zip(a.var_names, display) if d == f"Oarb_{sym.get(g, '')}"))
    print(f"  display names: {n_sym} symbols, {a.n_vars - n_sym} ensembl ids", flush=True)

    a.var["gene_id"] = list(a.var_names)
    a.var["symbol"] = [sym.get(g, "") for g in a.var_names]
    a.var_names = display
    a.var["gene_symbol"] = list(display)

    keep = totals > 0
    a = a[keep].copy()
    print(f"  dropped {int((~keep).sum())} genes with zero counts -> {a.n_obs} x {a.n_vars}",
          flush=True)
    a.X = sp.csr_matrix(a.X)

    p = os.path.join(OUT, f"{ID}.h5ad")
    a.write_h5ad(p, compression="gzip")
    print(f"  wrote {p} ({os.path.getsize(p)/2**20:.0f} MiB)", flush=True)

    pd.DataFrame({
        "display_name": list(a.var_names),
        "gene_id": list(a.var["gene_id"]),
        "symbol": list(a.var["symbol"]),
    }).to_csv(f"{OUT}/{ID}.gene_names.tsv", sep="\t", index=False)

    rec = {
        "dataset_id": ID,
        "runs": RUNS,
        "n_cells": int(a.n_obs),
        "n_genes": int(a.n_vars),
        "barcodes_per_run": {k: int(v) for k, v in a.obs["sample"].value_counts().items()},
        "genes_named_by_symbol": n_sym,
        "genes_left_as_ensembl": int(a.n_vars - n_sym),
        "total_umi": float(totals[keep].sum()),
        "median_umi_before_qc": float(np.median(totals[keep])),
    }
    with open(f"{OUT}/{ID}.merge_stats.json", "w") as fh:
        json.dump(rec, fh, indent=2)
    print(json.dumps(rec, indent=2))


if __name__ == "__main__":
    sys.exit(main())
