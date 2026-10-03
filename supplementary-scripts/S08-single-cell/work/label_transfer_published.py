#!/usr/bin/env python
r"""Give a re-analysis cell types by transferring a published annotation.

WHY A TRANSFER, AND WHY IT SAYS `de_novo`
-----------------------------------------
These datasets have no per-cell label deposit: the count matrix is all the study
released.  What exists instead is a *published* annotation of the same species --
either the source study's own marker table (AMURI), or another dataset on this
site whose cell types came from its deposit (NVECT_bodywall).  Matching our own
Leiden clusters against that reference's marker genes is an inference we make,
not a label the authors handed us, so the record says `de_novo`, and the manifest
discloses the reference, the mapped fraction and the decision rule.  Same
contract as work/oarbu_label_transfer.py.

TWO SOURCES
-----------
`--abbr AMURI`      the reference is work/<abbr>_cellmarker.tsv, a published
                    marker table dumped from the live DB.  Gene ids are joined
                    after stripping `--norm` from both sides.
`--ref-dataset D`   the reference is another annotated dataset's markers.tsv.
                    Needed because the NVECT datasets are keyed by four mutually
                    disjoint id spaces (NV2.x / NV2g / NVE / LOC) while the one
                    dataset with a published adult annotation is in LOC space.

THE XP_ BRIDGE IS THE WHOLE PROBLEM
-----------------------------------
For the NVECT family the only namespace all four id spaces share is the RefSeq
protein accession: the site's `sc_gene_refseq` table maps 32,430 sc_ids (19,231
LOC + 5,683 NV2.x + 5,183 NV2g + 2,333 NVE) to XP_ accessions, and `NVECT_locus`
maps 32,370 XP_ back to LOC.  `--xp-bridge` keys both sides on that accession, so
a LOC-space marker in the reference meets an NV2.x gene in the target.  Without
it every cluster scores zero and silently becomes `Cluster N` -- which is exactly
the failure this script exists to fix.  So the bridge is explicit, the mapped
fraction is printed, and a bridge that maps almost nothing aborts rather than
writing cluster labels back.

SCORING
-------
A cluster's score for a type is the mean, over that type's mapped markers, of the
gene's expression in the cluster standardised ACROSS CLUSTERS.  Standardising
first stops one very high-abundance marker from deciding alone.  Three guards
against over-claiming, because a name on a public site has to be earned:
  * an absolute floor (`--min-score`), since a cluster sitting at the mean for
    every marker still has a winning margin over its runner-up;
  * a margin to the runner-up FAMILY (`--margin`) -- if two different cell types
    are tied the cluster stays `Cluster N`, because falling back to the winner's
    family name would not be conservative, it would just be picking the winner;
  * a margin between sibling SUBTYPES of the winning family, where the fallback
    to the family name (`Neural 3` -> `Neural`) is a real generalisation.

Usage
-----
    python label_transfer_published.py --dataset AMURI_regen --abbr AMURI \
        --norm 'evm\.(model|TU)\.'
    python label_transfer_published.py --dataset NVECT_tentacle \
        --ref-dataset NVECT_bodywall --xp-bridge
"""
from __future__ import annotations

import argparse
import json
import os
import re
import time
from datetime import datetime, timezone

import numpy as np
import pandas as pd
import scanpy as sc

W = "/mnt/sda/jackie/cnidaria/codex/singlecell/work"
ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"
ANNOT = os.path.join(ROOT, "data/annotated")


def family(label: str) -> str:
    """`Neuron 10` -> `Neuron`; `Unassigned cell cluster1` -> `Unassigned cell`."""
    return re.sub(r"\s*\d+$", "", re.sub(r"\s+\d+$", "", label)).strip() or label


def _nov(x) -> str:
    return re.sub(r"\.\d+$", "", str(x))


# Genes that report cell STATE rather than cell identity.  Matches UniProt's
# "Small ribosomal subunit protein eS8" phrasing as well as "ribosomal protein".
GENERIC_RE = re.compile(
    r"ribosom|histone|high mobility group|^HMG|mitochondrial|cytochrome"
    r"|NADH dehydrogenase|ATP synthase|elongation factor|initiation factor"
    r"|heat shock|ubiquitin|proteasom", re.I)


def load_xp_bridge():
    """Two dumps of the live DB, keyed on RefSeq protein accession.

    sc_gene_refseq (abbr='NVECT') gives sc_id -> accession, and NVECT_locus gives
    mRNA -> gene, i.e. accession -> LOC.  Both are the site's own tables, so the
    transfer rests on the same id correspondence the rest of the site displays.
    """
    sr = pd.read_csv(f"{W}/nvect_sc_refseq.tsv", sep="\t", header=None,
                     names=["sc_id", "acc", "locus", "source"])
    lo = pd.read_csv(f"{W}/nvect_locus.tsv", sep="\t", header=None,
                     names=["mRNA", "gene"])
    id2acc = {}
    for s, a in zip(sr["sc_id"], sr["acc"]):
        id2acc.setdefault(str(s), _nov(a))
    for m, g in zip(lo["mRNA"], lo["gene"]):
        id2acc.setdefault(str(m), _nov(m))
    stems = {_nov(a) for a in sr["acc"]} | {_nov(m) for m in lo["mRNA"]}

    def K(g):
        g = str(g)
        if g.startswith("Nvec_"):
            g = g[5:]
        if g in id2acc:
            return id2acc[g]
        m = re.search(r"X[MP]_(\d+)", g)   # vc1.1_XM_001629567.3 -> XP_001629567
        if m:
            k = "XP_" + m.group(1)
            return k if k in stems else None
        return None

    return K


def load_published_marker_table(abbr: str, norm: str):
    df = pd.read_csv(f"{W}/{abbr.lower()}_cellmarker.tsv", sep="\t")
    df.columns = [c.strip() for c in df.columns]
    gcol = "gene" if "gene" in df.columns else df.columns[0]
    df = df[["celltype", gcol]].rename(columns={gcol: "gene"}).dropna()
    df["gene"] = df["gene"].astype(str)
    if norm:
        df["gene"] = df["gene"].str.replace(norm, "", regex=True)
    return df


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--dataset", required=True)
    ap.add_argument("--abbr", default="", help="published marker table in the live DB")
    ap.add_argument("--ref-dataset", default="", help="another annotated dataset as reference")
    ap.add_argument("--desc-file", default="",
                    help="gene<TAB>description dump; markers matching the generic-state "
                         "pattern (ribosomal/histone/mitochondrial) are excluded from the "
                         "reference signature before scoring")
    ap.add_argument("--norm", default="", help="regex stripped from BOTH sides of the join")
    ap.add_argument("--norm-ours", default="", help="regex stripped from our gene ids only")
    ap.add_argument("--xp-bridge", action="store_true",
                    help="join on RefSeq protein accession via sc_gene_refseq + NVECT_locus")
    ap.add_argument("--ref-top", type=int, default=100, help="ranked markers per reference type")
    ap.add_argument("--min-markers", type=int, default=5)
    ap.add_argument("--margin", type=float, default=0.25)
    ap.add_argument("--min-score", type=float, default=0.25)
    ap.add_argument("--min-mapped", type=float, default=0.35)
    ap.add_argument("--note", default="", help="extra disclosure appended to annotation_note")
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()
    if bool(args.abbr) == bool(args.ref_dataset):
        raise SystemExit("give exactly one of --abbr / --ref-dataset")

    t0 = time.time()
    sc.settings.verbosity = 1
    h5 = os.path.join(ANNOT, f"{args.dataset}.annotated.h5ad")
    a = sc.read_h5ad(h5)
    print(f"[{args.dataset}] {a.shape} clusters={a.obs['cluster'].nunique()}", flush=True)
    ours = [str(x) for x in a.var_names]

    # ---- map reference markers onto this dataset's genes ------------------
    if args.ref_dataset:
        ref = args.ref_dataset
        mk = pd.read_csv(f"{ANNOT}/{ref}.markers.tsv", sep="\t")
        top = mk.groupby("cell_type", sort=False).head(args.ref_top)
        refrows = top[["cell_type", "gene"]].rename(columns={"cell_type": "celltype"})
        refrows["gene"] = refrows["gene"].astype(str)
        key = load_xp_bridge() if args.xp_bridge else (
            (lambda g: str(g).replace(args.norm, "") if args.norm else str(g)))
        ours_key = {}                      # XP_ accession -> target gene ids
        for g in ours:
            k = key(g)
            if k:
                ours_key.setdefault(k, []).append(g)
        print(f"  bridge: {len(ours)} target genes -> {len(ours_key)} reference-visible keys",
              flush=True)
        refrows["ours"] = refrows["gene"].map(key).map(
            lambda k: ours_key.get(k) if k else None)
        expl = refrows.explode("ours")
        kept = expl.dropna(subset=["ours"]).copy()
        # de-duplicate per (type, target gene): several reference ids can bridge
        # onto one target gene, and counting it twice would weight it twice.
        kept = kept.drop_duplicates(subset=["celltype", "ours"])
        n_ref_types = refrows["celltype"].nunique()
        denom = len(refrows)
        srcdesc = f"the published cell types of dataset {ref} on this site"
    else:
        refrows = load_published_marker_table(args.abbr, args.norm)
        # re.sub, not str.replace: --norm is a REGEX ('evm\.(model|TU)\.') and a
        # literal replace silently matches nothing, leaving our ids unnormalised
        # against an already-normalised reference and joining 0%.
        pat = args.norm_ours or args.norm
        our_norm = [re.sub(pat, "", str(x)) for x in ours] if pat else ours
        our_of = dict(zip(our_norm, ours))
        refrows["ours"] = refrows["gene"].map(our_of)
        kept = refrows.dropna(subset=["ours"]).drop_duplicates(subset=["celltype", "ours"]).copy()
        refrows["ours"] = refrows["gene"]
        n_ref_types = refrows["celltype"].nunique()
        denom = len(refrows)
        srcdesc = f"the published {args.abbr}_cellmarker table ({n_ref_types} cell types)"

    frac = len(kept) / max(denom, 1)
    print(f"  reference markers: {denom} rows / {n_ref_types} types; "
          f"joined {len(kept)} ({100*frac:.1f}%)", flush=True)
    if frac < args.min_mapped:
        raise SystemExit(
            f"only {100*frac:.1f}% of reference markers join this dataset "
            f"(floor {100*args.min_mapped:.0f}%) -- refusing to write cluster labels back")

    # ---- drop generic-state genes from the reference signature ------------
    # A marker list is not automatically a cell-identity list.  bodywall's
    # `Progenitor` is 83% ribosomal/histone/HMGB genes and `Epidermis` 49%, so
    # both score high for ANY cluster with busy translation, which is what made
    # Progenitor win 6 of 16 neoplasm clusters on margins of 0.03.  Ribosomal,
    # mitochondrial and histone genes are standard exclusions from marker panels
    # because they report cell state, not cell type.
    n_dropped = 0
    if args.desc_file and os.path.exists(args.desc_file):
        desc = {}
        with open(args.desc_file) as fh:
            for line in fh:
                k, _, v = line.partition("\t")
                desc[k] = v.strip()
        present = kept["gene"].astype(str).map(desc)
        drop = present.notna() & present.str.contains(GENERIC_RE, na=False)
        n_dropped = int(drop.sum())
        kept = kept[~drop]
        print(f"  dropped {n_dropped} generic-state markers "
              f"(ribosomal/histone/mitochondrial) from the reference", flush=True)

    by_type = {t: g["ours"].drop_duplicates().tolist() for t, g in kept.groupby("celltype")}
    for t in sorted(by_type):
        print(f"    {t:<28} {len(by_type[t]):>4} mapped markers", flush=True)

    # ---- cluster centroids ------------------------------------------------
    src = a.raw.to_adata() if a.raw is not None else a
    genes = sorted({g for gs in by_type.values() for g in gs if g in src.var_names})
    sub = src[:, genes]
    X = sub.X.toarray() if hasattr(sub.X, "toarray") else np.asarray(sub.X)
    cl = a.obs["cluster"].astype(str).to_numpy()
    clusters = sorted(pd.unique(cl), key=lambda s: (len(s), s))
    M = np.vstack([X[cl == c].mean(axis=0) for c in clusters])
    print(f"  centroid matrix: {M.shape[0]} clusters x {M.shape[1]} marker genes", flush=True)

    mu, sd = M.mean(axis=0), M.std(axis=0)
    Z = np.divide(M - mu, sd, out=np.zeros_like(M), where=sd > 1e-9)
    zpos = {g: i for i, g in enumerate(genes)}

    types = sorted(by_type)
    score = np.zeros((len(clusters), len(types)))
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
        k1, s1 = order[0], fam_score[i, order[0]]
        s2 = fam_score[i, order[1]] if len(order) > 1 else -np.inf
        f1 = fams[k1]
        jj = [j for j in fam_j[f1] if nmark[j] >= args.min_markers]
        jsort = sorted(jj, key=lambda j: -score[i, j])
        j1 = jsort[0]
        sub_gap = (score[i, j1] - score[i, jsort[1]]) if len(jsort) > 1 else np.inf
        n_cells = int((cl == c).sum())

        if not np.isfinite(s1) or s1 < args.min_score:
            lab, why = f"Cluster {c}", "score %.3f below floor %.2f" % (
                (s1 if np.isfinite(s1) else float("nan")), args.min_score)
        elif (s1 - s2) < args.margin:
            # Two DIFFERENT cell types are effectively tied.  Falling back to the
            # winner's family name here would not be conservative, it would just be
            # picking the winner -- and on the embryo run the median family margin
            # was 0.06, i.e. a coin flip.  Say so instead of naming it.
            lab, why = f"Cluster {c}", "family margin %.3f under %.2f (tie between %s and %s)" % (
                s1 - s2, args.margin, f1, fams[order[1]] if len(order) > 1 else "?")
        elif sub_gap < args.margin:
            # Sibling subtypes of the SAME family are too close to separate.  The
            # family name is a real, earned generalisation (Neural 1 vs Neural 3).
            lab, why = f1, "family only: subtype margin %.3f" % sub_gap
        else:
            lab, why = types[j1], "subtype: family margin %.3f, subtype margin %.3f" % (
                s1 - s2, sub_gap)
        labels.append(lab)
        rows.append(dict(cluster=c, n_cells=n_cells, assigned=lab, family=f1,
                         top_family_score=round(float(s1), 3),
                         runner_up_family=(fams[order[1]] if len(order) > 1 else ""),
                         family_margin=(round(float(s1 - s2), 3) if np.isfinite(s2) else None),
                         best_subtype=types[j1], subtype_score=round(float(score[i, j1]), 3),
                         subtype_margin=(round(float(sub_gap), 3) if np.isfinite(sub_gap) else None),
                         n_markers_type=int(nmark[j1]), why=why))

    audit = pd.DataFrame(rows)
    print(audit.to_string(index=False), flush=True)
    print("\n  assigned labels:", flush=True)
    print(pd.Series(labels).value_counts().to_string(), flush=True)
    n_left = sum(1 for x in labels if str(x).startswith("Cluster "))
    print(f"\n  {len(labels)-n_left}/{len(labels)} clusters named; {n_left} left as clusters",
          flush=True)

    audit.to_csv(os.path.join(ANNOT, f"{args.dataset}.label_transfer.tsv"), sep="\t", index=False)
    if args.dry_run:
        print("  dry run: h5ad not rewritten", flush=True)
        return 0

    lab_of = dict(zip(clusters, labels))
    # Keep the FIRST recorded label if this script is re-run: on a second pass
    # `cell_type` already holds the transferred names, so overwriting would
    # replace the original Leiden labels with our own output and destroy the
    # audit trail of what the clusters were called before.
    if "cluster_label_prev" in a.obs:
        prev = a.obs["cluster_label_prev"].astype(str).values
    else:
        prev = (a.obs["cell_type"].astype(str).values if "cell_type" in a.obs
                else np.repeat("", a.n_obs))
        a.obs["cluster_label_prev"] = prev
    n_before = int(pd.Series(prev).nunique())
    a.obs["cell_type"] = [lab_of[c] for c in cl]
    a.uns["label_transfer"] = {
        "source": srcdesc,
        "published_markers": int(denom),
        "mapped_markers": int(len(kept)),
        "mapped_fraction": round(frac, 4),
        "bridge": "RefSeq protein accession (sc_gene_refseq + NVECT_locus)" if args.xp_bridge
                  else (args.norm or args.norm_ours or "direct id match"),
        "min_markers": args.min_markers, "margin": args.margin, "min_score": args.min_score,
    }
    print(f"  cell types: {n_before} (leiden) -> {a.obs['cell_type'].nunique()}", flush=True)

    sc.tl.rank_genes_groups(a, "cell_type", method="wilcoxon", use_raw=True, n_genes=100, pts=True)
    mk = sc.get.rank_genes_groups_df(a, group=None)
    # `scores` -> `score` is not cosmetic: 05_export_web.py sorts on and reads
    # `score`, so omitting it makes the export die with KeyError: 'score'.
    mk = mk.rename(columns={"group": "cell_type", "names": "gene", "scores": "score",
                            "logfoldchanges": "log2fc",
                            "pvals": "pvals", "pvals_adj": "padj", "pct_nz_group": "pct_in",
                            "pct_nz_reference": "pct_nz_reference"})
    mk.to_csv(os.path.join(ANNOT, f"{args.dataset}.markers.tsv"), sep="\t", index=False)
    a.write_h5ad(h5, compression="gzip")

    st = os.path.join(ANNOT, f"{args.dataset}.annotate_stats.json")
    stats = json.load(open(st))
    note = (
        f"Cell types were TRANSFERRED, not inherited: the deposit ships a count matrix but no "
        f"per-cell labels, so these are our own Leiden clusters matched against {srcdesc}. "
        f"{len(kept)} of {denom} reference marker rows ({100*frac:.1f}%) join this dataset's gene "
        f"space"
        + (f" through the site's own LOC/NV2/NVE -> RefSeq accession correspondence"
           f" (sc_gene_refseq, NVECT_locus)" if args.xp_bridge else "")
        + (f" Markers reporting cell state rather than identity ({n_dropped} ribosomal/"
           f"histone/mitochondrial genes) were excluded from the reference signature "
           f"before scoring." if n_dropped else "")
        + f" A cluster is named only if its winning type scores at least {args.min_score} and "
        f"beats the best other cell type by at least {args.margin}; sibling subtypes within one "
        f"family are merged into the family name when they are too close to separate. Clusters "
        f"failing those tests -- including any where two different cell types are effectively "
        f"tied -- stay `Cluster N` rather than getting a name the evidence does not support."
    )
    if args.note:
        note += " " + args.note
    stats.update({
        "annotation_provenance": "de_novo",
        "annotation_method": f"published cell types of {args.ref_dataset or args.abbr} "
                             f"transferred onto our clusters by marker matching",
        "cell_types": sorted(a.obs["cell_type"].astype(str).unique().tolist()),
        "n_cell_types": int(a.obs["cell_type"].nunique()),
        "label_transfer_reference": args.ref_dataset or f"{args.abbr}_cellmarker",
        "label_transfer_mapped_markers": int(len(kept)),
        "label_transfer_reference_markers": int(denom),
        "label_transfer_mapped_fraction": round(frac, 4),
        "panel_applicable": None,
        "annotation_note": note,
        "label_transfer_utc": datetime.now(timezone.utc).isoformat(timespec="seconds"),
    })
    with open(st, "w") as fh:
        json.dump(stats, fh, indent=2)
    print(f"  wrote {h5} and annotate_stats.json ({time.time()-t0:.0f}s)", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
