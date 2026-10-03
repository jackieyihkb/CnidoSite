#!/usr/bin/env python
r"""Give the OARBU re-analysis cell types by transferring the study's own labels.

WHY A TRANSFER AND NOT AN INHERITANCE
-------------------------------------
The other re-analysed datasets inherit per-cell labels from a deposit.  OARBU has
no such deposit: PRJNA1122932 has no GEO series (NCBI GDS returns 0 hits), the two
single-cell runs are raw reads, and the only per-cell-type product the study
published is a marker table.  So the labels here are *inferred onto our own
clusters* from that table, which is why the record says `de_novo` and not
`published` -- a cluster assignment we made is not a label the authors handed us.

HOW THE MATCH WORKS
-------------------
For each published cell type the study lists its own marker genes with log2FC
(`OARBU_cellmarker`, 5,702 rows over 28 types).  Its gene space is `gNNNN` (1783
rows), `SYMBOL.N` (2953) and bare symbols (966); ours is Ensembl `ENSPTBG...`.
Stripping the `.\d+` suffix and matching on the name bridges 1,706 of them
(29.9%), i.e. 34-72 usable markers per type.  The `gNNNN` genes cannot be bridged
at all -- no mapping exists between that annotation and Ensembl's -- so the
study's unnamed, lineage-specific markers are invisible to this transfer.  That
limitation is stated in the manifest note rather than papered over.

A cluster's score for a type is the mean, over that type's mapped markers, of the
gene's expression in the cluster standardised across clusters.  Standardising
first is what stops one very high-abundance marker from deciding the answer on its
own.  Assigning the raw argmax would over-claim: `Neuron 8` and `Neuron 10` are
sibling subclusters the marker table separates only partly, so a thin margin is
reported as the bare family name (`Neuron`) and only a clear margin keeps the
subcluster label.

Usage
-----
    python oarbu_label_transfer.py --min-markers 5 --margin 0.25
"""
from __future__ import annotations

import argparse
import json
import os
import re
import time
from datetime import datetime, timezone

import anndata as ad
import numpy as np
import pandas as pd
import scanpy as sc

W = "/mnt/sda/jackie/cnidaria/codex/singlecell/work"
ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"
ID = "OARBU_symbiotic"
ANNOT = os.path.join(ROOT, "data/annotated")


def family(label: str) -> str:
    """`Neuron 10` -> `Neuron`; a name with no number is already a family."""
    return re.sub(r"\s+\d+$", "", label).strip()


def load_published_markers(sym2disp: dict[str, str]):
    df = pd.read_csv(f"{W}/oarbu_cellmarker.tsv", sep="\t", header=None,
                     names=["celltype", "gene", "log2fc", "pct1", "pct2", "fdr"])
    df["base"] = df["gene"].str.replace(r"\.\d+$", "", regex=True).str.upper()
    df["ours"] = df["base"].map(sym2disp)
    df["ours"] = df["ours"].fillna(df["gene"].map(sym2disp))
    kept = df.dropna(subset=["ours"]).copy()
    return df, kept


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--dataset", default=ID)
    ap.add_argument("--min-markers", type=int, default=5,
                    help="a type needs at least this many mapped markers to be a candidate")
    ap.add_argument("--margin", type=float, default=0.25,
                    help="score gap below which only the family name is claimed")
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    t0 = time.time()
    sc.settings.verbosity = 1
    h5 = os.path.join(ANNOT, f"{args.dataset}.annotated.h5ad")
    a = sc.read_h5ad(h5)
    print(f"[{args.dataset}] {a.shape}  clusters={a.obs['cluster'].nunique()}", flush=True)

    names = pd.read_csv(os.path.join(ROOT, "data/raw", args.dataset,
                                     f"{args.dataset}.gene_names.tsv"), sep="\t")
    sym2disp = {s.strip().upper(): d for s, d in zip(names["symbol"], names["display_name"])
                if isinstance(s, str) and s.strip()}
    print(f"  our symbol space: {len(sym2disp)}", flush=True)

    allrows, kept = load_published_markers(sym2disp)
    print(f"  published markers: {len(allrows)} rows, {kept['celltype'].nunique()} types, "
          f"{len(kept)} mapped ({100*len(kept)/len(allrows):.1f}%)", flush=True)

    by_type = {t: g["ours"].drop_duplicates().tolist()
               for t, g in kept.groupby("celltype")}

    # ---- cluster centroids on the log-normalised values -------------------
    src = a.raw.to_adata() if a.raw is not None else a
    genes = sorted({g for gs in by_type.values() for g in gs})
    genes = [g for g in genes if g in src.var_names]
    sub = src[:, genes]
    X = sub.X.toarray() if hasattr(sub.X, "toarray") else np.asarray(sub.X)
    cl = a.obs["cluster"].astype(str).to_numpy()
    clusters = sorted(pd.unique(cl))
    M = np.vstack([X[cl == c].mean(axis=0) for c in clusters])   # clusters x genes
    print(f"  centroid matrix: {M.shape[0]} clusters x {M.shape[1]} marker genes", flush=True)

    mu, sd = M.mean(axis=0), M.std(axis=0)
    Z = np.divide(M - mu, sd, out=np.zeros_like(M), where=sd > 1e-9)
    zpos = {g: i for i, g in enumerate(genes)}

    # ---- score ------------------------------------------------------------
    types = sorted(by_type)
    score = np.zeros((len(clusters), len(types)), dtype=float)
    nmark = np.zeros(len(types), dtype=int)
    for j, t in enumerate(types):
        idx = [zpos[g] for g in by_type[t] if g in zpos]
        nmark[j] = len(idx)
        if idx:
            score[:, j] = Z[:, idx].mean(axis=1)

    fams = sorted({family(t) for t in types})
    fam_j = {f: [j for j, t in enumerate(types) if family(t) == f] for f in fams}
    fam_score = np.full((len(clusters), len(fams)), -np.inf)
    for k, f in enumerate(fams):
        jj = [j for j in fam_j[f] if nmark[j] >= args.min_markers]
        if jj:
            fam_score[:, k] = score[:, jj].max(axis=1)

    rows, labels = [], []
    for i, c in enumerate(clusters):
        order = np.argsort(-fam_score[i])
        k1 = order[0]
        s1 = fam_score[i, k1]
        s2 = fam_score[i, order[1]] if len(order) > 1 else -np.inf
        f1 = fams[k1]
        jj = [j for j in fam_j[f1] if nmark[j] >= args.min_markers]
        jsort = sorted(jj, key=lambda j: -score[i, j])
        j1 = jsort[0]
        sub_gap = (score[i, j1] - score[i, jsort[1]]) if len(jsort) > 1 else np.inf
        n_cells = int((cl == c).sum())

        if not np.isfinite(s1) or s1 <= 0:
            lab = f"Cluster {c}"
            why = "no positive score"
        elif (s1 - s2) < args.margin or sub_gap < args.margin:
            lab = f1
            why = ("family only: runner-up family margin %.3f" % (s1 - s2)
                   if (s1 - s2) < args.margin else
                   "family only: best subtype margin %.3f" % sub_gap)
        else:
            lab = types[j1]
            why = "subtype: family margin %.3f, subtype margin %.3f" % (s1 - s2, sub_gap)
        labels.append(lab)
        rows.append(dict(cluster=c, n_cells=n_cells, assigned=lab,
                         family=f1, top_family_score=round(float(s1), 3),
                         runner_up_family=(fams[order[1]] if len(order) > 1 else ""),
                         family_margin=round(float(s1 - s2), 3) if np.isfinite(s2) else None,
                         best_subtype=types[j1], subtype_score=round(float(score[i, j1]), 3),
                         subtype_margin=(round(float(sub_gap), 3) if np.isfinite(sub_gap) else None),
                         n_markers_type=int(nmark[j1]), why=why))

    audit = pd.DataFrame(rows).sort_values("cluster")
    print(audit.to_string(index=False), flush=True)
    print("\n  assigned labels:")
    print(pd.Series(labels).value_counts().to_string(), flush=True)

    if args.dry_run:
        audit.to_csv(f"{W}/oarbu_label_transfer_dryrun.tsv", sep="\t", index=False)
        print("\n  dry run: h5ad not rewritten")
        return 0

    # ---- write back -------------------------------------------------------
    lab_of = dict(zip(clusters, labels))
    new = pd.Series([lab_of[c] for c in cl], index=a.obs_names)
    n_before = int(a.obs["cell_type"].nunique()) if "cell_type" in a.obs else 0
    # keep what 04 produced, so the record shows the transfer's input as well as
    # its output -- but read it BEFORE overwriting, or this captures the new labels
    prev = a.obs["cell_type"].astype(str).values if "cell_type" in a.obs else np.repeat("", a.n_obs)
    a.obs["cluster_label_prev"] = prev
    a.obs["cell_type"] = new.astype(str).values
    a.uns["label_transfer"] = {
        "source": "OARBU_cellmarker (live cnidaria DB, SymbioticState)",
        "mapped_markers": int(len(kept)),
        "published_markers": int(len(allrows)),
        "min_markers": args.min_markers,
        "margin": args.margin,
    }
    print(f"  cell types: {n_before} (leiden) -> {a.obs['cell_type'].nunique()}", flush=True)

    # markers must be recomputed: 04 ranked them against the cluster ids, and the
    # export reads this table, so leaving it would publish markers for labels that
    # no longer exist.
    sc.tl.rank_genes_groups(a, "cell_type", method="wilcoxon", use_raw=True,
                            n_genes=100, pts=True)
    mk = sc.get.rank_genes_groups_df(a, group=None)
    mk = mk.rename(columns={"group": "cell_type", "names": "gene",
                            "logfoldchanges": "log2fc", "pvals_adj": "padj",
                            "pct_nz_group": "pct_in", "scores": "score"})
    mk.to_csv(os.path.join(ANNOT, f"{args.dataset}.markers.tsv"), sep="\t", index=False)

    a.write_h5ad(h5, compression="lzf")
    audit.to_csv(os.path.join(ANNOT, f"{args.dataset}.label_transfer.tsv"),
                 sep="\t", index=False)

    sp = os.path.join(ANNOT, f"{args.dataset}.annotate_stats.json")
    stats = json.load(open(sp)) if os.path.exists(sp) else {}
    stats.update({
        "annotation_provenance": "de_novo",
        "n_cell_types": int(a.obs["cell_type"].nunique()),
        "cell_types": sorted(map(str, a.obs["cell_type"].unique())),
        "n_marker_rows": int(len(mk)),
        "annotation_method": "marker-overlap label transfer from the source study's own marker table",
        "annotation_note": (
            "Cell types were TRANSFERRED, not inherited: PRJNA1122932 has no GEO "
            "deposit and the only per-cell-type product the study published is a "
            "marker table, so these labels are our own clusters matched against "
            "the study's published markers (OARBU_cellmarker, 28 types). "
            f"{len(kept)} of {len(allrows)} of its markers could be bridged by gene "
            "symbol ({:.0f}%), which leaves 34-72 markers per type; the study's "
            "`gNNNN` genes have no Ensembl counterpart and are therefore not used. "
            "A cluster is named with the study's subcluster label only when the "
            "score margin over the runner-up exceeds {:.2f}; a thinner margin is "
            "reported as the bare family (`Neuron`, `Gastrodermis`, ...). Counts "
            "are our own STARsolo quantification against Ensembl jaOcuArbu1.hap1.1, "
            "not the study's matrix.".format(
                100 * len(kept) / len(allrows), args.margin)
        ),
        "label_transfer": a.uns["label_transfer"],
        "label_transfer_runtime_sec": round(time.time() - t0, 1),
        "processed_utc": datetime.now(timezone.utc).isoformat(timespec="seconds"),
    })
    with open(sp, "w") as fh:
        json.dump(stats, fh, indent=2, default=str)
    print(f"  wrote {h5}, markers.tsv, label_transfer.tsv, annotate_stats.json "
          f"[{time.time()-t0:.0f}s]")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
