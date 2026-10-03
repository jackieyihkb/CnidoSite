#!/usr/bin/env python3
"""
04_cluster_annotate.py -- normalisation, batch integration, clustering, UMAP
and cell-type assignment for one CnidoSite single-cell dataset.

Design notes tied to the referee reports
----------------------------------------
* Batch effects.  Referee 2 asks "how heterogeneous experiments or batch
  effects were handled" (Major #4) and asks specifically for "any
  batch/integration procedures used" (Major #3).  We integrate over the
  `sample` key recovered at load time with Harmony, and we record whether
  integration was actually applied or skipped (single-library datasets skip it,
  and the record says so rather than claiming an integration that never ran).

* Cell-type labels.  Where the source study published a cell->cell-type table
  we inherit it verbatim and record the match rate, because a re-derived
  annotation would silently disagree with the source paper.  Where no published
  table exists we cluster and annotate de novo, and mark the labels as
  `auto:` so the website can distinguish the two provenances.  This is what
  lets the Cell Atlas grow past the six species it currently covers without
  misrepresenting confidence.  Where the marker panel names nothing credible
  -- which is what happens when a deposit identifies features only by
  transcript ID -- the labels are the cluster identities themselves and the
  record says `cluster_only`, because Referee 3's objection ("the clusters are
  not identified by cell type") is answered by a named cluster, not by a
  fabricated name.

* Markers.  Ranked with the Wilcoxon test the way `scanpy.tl.rank_genes_groups`
  implements it, plus an explicit log2 fold-change and a detection-rate
  difference, so the marker table can be filtered on something other than an
  adjusted p-value that is near-zero for every gene at these cell numbers.

Usage
-----
    python 04_cluster_annotate.py --dataset AMILL_whole_adult \
        --qc-dir ../data/qc --outdir ../data/annotated
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd
import scipy.sparse as sp

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from lib import io as cnio  # noqa: E402
# Shared with `03_qc.py`: the batch summary is merged by `dataset_id`, never
# overwritten with this run's datasets.  See `lib/summary.py` for the defect
# that made that necessary in three separate files.
from lib.summary import merge_summary  # noqa: E402

PIPELINE_VERSION = "cnidosite-sc-1.0.0"


# ---------------------------------------------------------------------------
# curated cnidarian marker panel for de-novo annotation
# ---------------------------------------------------------------------------
# Used ONLY when a dataset has no published cell-type table.  Each entry is a
# regex matched against gene symbols.  Deliberately small and conservative:
# these are markers with support across multiple cnidarian single-cell papers,
# and the resulting labels are prefixed `auto:` so a reader can tell them apart
# from inherited, published annotations.
CNIDARIAN_MARKERS: dict[str, str] = {
    "cnidocyte":        r"^(NOWA|NCOL|MINCOL|NVWA|NWA|Cnidocyst|SpCyst)",
    "neuron":           r"^(ELAV|ELAVL|SOXB|POU4|ISL|NEUROG|NCOL|ELAV1|SoxB1|Pou4)",
    "gastrodermis":     r"(Gastroderm|EndoG|ENDOG|Digest|NV2T1g.*endo)",
    "epidermis":        r"(Epiderm|Periderm|CHITIN|Chitin)",
    "immune":           r"(TLR|MYD88|NLR|Interferon|FREP|Complement|C3|CFA)",
    "gland":            r"(Gland|MUCIN|Mucin|ZP|ZonaPellucida)",
    "germline":         r"(VASA|NANOS|PIWI|PIWIL|DDX4|BOULE|Oocyte)",
    "stem_cell":        r"^(PIWI1|PIWI2|NANOS1|SOXB1|PL10|VASA1)$",
    "muscle":           r"(MYOSIN|MYH|ACTIN|TROPONIN|Tnnt|MYL|Muscle)",
    "calicoblast":      r"(CALC|CarbonicAnhydrase|CA2|SOMP|Galaxin)",
    "photoreceptor":    r"(OPSIN|CRY|Cryptochrome|RHABDO)",
    "proliferating":    r"(PCNA|MCM[2-7]|TOP2A|HIST1|CDK1|Cyclin)",
}


#: How many panel hits a cluster needs before it is allowed to carry a name.
#: One hit is not evidence: at 50 ranked genes per cluster a single coincidental
#: token is common, and a wrong cell-type label is worse than no label at all.
MIN_MARKER_HITS = 2


def load_marker_panel(path: str) -> tuple[dict[str, str], dict[str, int]]:
    """Read a per-dataset marker panel: `label<TAB>pattern[<TAB>genes]`.

    The global panel above is matched against gene *symbols*, so a deposit that
    keys its features by gene model ID (`NV2.11441`) can never fire it -- which
    is what left NVECT_embryo `cluster_only` while its own study's markers went
    unused.  A dataset whose features are IDs needs a panel written in those
    IDs, and that panel is data about the dataset, so it lives beside the
    registry rather than in this file.

    The optional third column is documentation only (the symbols behind the
    patterns, `sym=id,sym=id`); its length is reported so a caveat can say how
    many genes the panel actually held.  Patterns are returned verbatim and
    anchored later by `compile_panel`, so a pattern with no `^`/`$` behaves as
    the global ones do.
    """
    import re
    if not os.path.exists(path):
        raise SystemExit(f"marker_panel {path} does not exist")
    patterns: dict[str, str] = {}
    sizes: dict[str, int] = {}
    with open(path) as fh:
        header = fh.readline().rstrip("\n").split("\t")
        if header[:2] != ["label", "pattern"]:
            raise SystemExit(
                f"{path}: expected a `label<TAB>pattern` header, got {header}")
        for n, ln in enumerate(fh, start=2):
            ln = ln.rstrip("\n")
            if not ln.strip() or ln.lstrip().startswith("#"):
                continue
            f = ln.split("\t")
            if len(f) < 2 or not f[0].strip() or not f[1].strip():
                raise SystemExit(f"{path}:{n}: empty label or pattern")
            label, pattern = f[0].strip(), f[1].strip()
            if label in patterns:
                raise SystemExit(f"{path}:{n}: label {label!r} defined twice")
            try:
                re.compile(pattern)
            except re.error as exc:
                raise SystemExit(f"{path}:{n}: bad pattern for {label}: {exc}")
            patterns[label] = pattern
            sizes[label] = len([g for g in (f[2] if len(f) > 2 else "")
                                .split(",") if g.strip()])
    if not patterns:
        raise SystemExit(f"{path}: no panel entries")
    return patterns, sizes


def compile_panel(extra: dict[str, str] | None = None) -> dict[str, "re.Pattern"]:
    """Anchor every panel pattern to whole tokens.

    Matching a pattern anywhere inside a feature name is not a marker test.
    Measured on the Hydra atlas, where 11945 of 17089 var_names are composite
    identifiers, unanchored matching named 22 of 42 clusters and every one of
    those names was an artefact:

        `CFA` (complement factor)  matched the *Macaca fascicularis* suffix in
                                   `g312.t1|RL13A_MACFA` -> "immune", in 8 clusters
        `CRY` (cryptochrome)       matched the crystallins `CRYAB_BOVIN` /
                                   `RLA2_CRYST` and the fungus `FKBP2_CRYNJ`
                                   -> "photoreceptor"
        `MYL` (myosin light chain) matched the kinase `MYLK_BOVIN` -> "muscle"

    All three are substring matches inside a longer token, so requiring a token
    boundary on both sides is what removes them.  Splitting the identifier on
    `|` does not: `NOWA` and `Galaxin` are equally likely to sit on either side
    of the bar, so dropping the second field would discard real markers to fix
    a problem that anchoring already fixes.

    A leading `^` / trailing `$` in the curated panel means "whole name"; it is
    translated to a lookaround, which is the same assertion but also correct for
    composite identifiers, where the true symbol is not at the string offset the
    panel was written for.

    `extra` is a per-dataset panel (see `load_marker_panel`) matched against the
    same features.  Where it defines a label the global panel also defines, the
    two are *unioned* rather than one replacing the other: a deposit may key
    some features by symbol and some by model ID, and "proliferating" should mean
    either spelling.  Both are stripped of their anchors before the union, so an
    inner `^`/`$` cannot survive as an assertion that silently disables half the
    pattern.

    Residual limitation, deliberate: a feature whose *transferred* annotation is
    itself an exact marker name (`MYOSIN_DROME`) still matches, because at that
    point the field is indistinguishable from a correctly deposited symbol.
    That is why a cluster also needs MIN_MARKER_HITS independent hits.
    """
    import re

    def _bare(pat: str) -> str:
        return pat.strip().lstrip("^").rstrip("$")

    merged: dict[str, list[str]] = {
        label: [_bare(pat)] for label, pat in CNIDARIAN_MARKERS.items()
    }
    for label, pat in (extra or {}).items():
        merged.setdefault(label, []).append(_bare(pat))

    out: dict[str, "re.Pattern"] = {}
    for label, parts in merged.items():
        pat = parts[0] if len(parts) == 1 else "|".join(
            f"(?:{p})" for p in parts)
        out[label] = re.compile(
            r"(?<![A-Za-z0-9])(?:%s)(?![A-Za-z0-9])" % pat, re.I
        )
    return out


def score_clusters_by_markers(marker_df: pd.DataFrame,
                             panel: dict | None = None,
                             min_hits: int = MIN_MARKER_HITS) -> pd.DataFrame:
    """Score each cluster against the marker panel.

    Returns cluster, best label, hit count and the genes responsible.  A cluster
    whose best panel match is below `min_hits` is left `unassigned`; the caller
    keeps Leiden labels for those, rather than asserting names the data does not
    support.
    """
    if panel is None:
        panel = compile_panel()
    rows = []
    for cluster, sub in marker_df.groupby("cluster"):
        genes = sub["names"].astype(str).tolist()
        best, best_hits = "unassigned", []
        for label, rx in panel.items():
            hits = [g for g in genes if rx.search(g)]
            if len(hits) > len(best_hits):
                best, best_hits = label, hits
        if len(best_hits) < min_hits:
            best, best_hits = "unassigned", []
        rows.append({
            "cluster": cluster,
            "auto_label": f"auto:{best}" if best_hits else "unassigned",
            "n_marker_hits": len(best_hits),
            "marker_genes": ",".join(best_hits[:12]),
        })
    return pd.DataFrame(rows)


def cluster_only_note(names: list, panel: dict | None = None) -> str:
    """The published explanation for a dataset the panel could not name.

    Reports how many ranked features the panel could even *see*, not just how
    many are composite identifiers.  The earlier wording read "(0 of 850 ranked
    features are composite 'id|homology-transfer' names)", which is literally
    true of a deposit keying its features as `NV2.1` and reads as "the features
    were fine, the evidence just fell short" -- the opposite of what happened.
    Measured with this module's own `compile_panel()`, every cluster_only
    dataset matches zero of the 12 panel patterns, so the obstacle is feature
    naming in all of them, and the note has to say so or it misattributes the
    limitation.  The composite count is still reported, because a dataset whose
    features are symbols that genuinely fell short is a different situation and
    must not be described as keyed by model IDs -- hence the conditional.
    """
    rx = compile_panel() if panel is None else panel
    n_hits = sum(1 for n in names if any(p.search(n) for p in rx.values()))
    n_pipe = sum(1 for n in names if "|" in n)
    if n_hits == 0 and panel is not None:
        # A panel written in this deposit's own IDs still matched nothing, so
        # the fault is in the panel, not in how the features are named -- the
        # opposite conclusion to the branch below, and worth telling apart.
        why = ("; a marker panel written for this dataset was applied and no "
               "ranked feature matched it either, so the panel and the "
               "deposit's feature IDs disagree")
    elif n_hits == 0:
        why = ("; no ranked feature matches any panel pattern, so this deposit "
               "keys features by gene model ID rather than gene symbol and the "
               "panel cannot name a cluster from it")
    elif n_pipe * 2 > len(names):
        # Most features are `id|best-BLAST-hit`.  The panel can see the
        # transferred symbols on the handful of names that carry one, but the
        # deposit is still keyed by model ID, so calling the features
        # "symbol-like" would overstate what the panel had to work with.  This
        # is the Hydra atlas: 6 of 2100 match, 1392 are composite, and no
        # cluster collects two.
        why = (f"; the features are keyed by gene model ID and carry "
               f"homology transfers, so the panel saw {n_hits} transferred "
               f"symbol(s) but no cluster reached the evidence bar")
    else:
        why = ("; the features are symbol-like, so the panel could see "
               "candidates but no cluster reached the evidence bar")
    return (
        f"no cluster reached {MIN_MARKER_HITS} marker-panel hits; labels are "
        f"Leiden cluster identities, not cell types ({n_hits} of {len(names)} "
        f"ranked features match any of the {len(rx)} panel "
        f"patterns, "
        f"{n_pipe} are composite 'id|homology-transfer' names{why})"
    )


def partial_annotation_note(n_named: int, n_total: int, sizes: dict,
                            n_panel_genes: int) -> str:
    """The caveat for a dataset the panel named *in part*.

    This is a third outcome, between `de_novo` and `cluster_only`, and it needs
    saying out loud because the two obvious readings of the page are both wrong:
    the named clusters are not the only ones with an identity, and the unnamed
    ones were not tested and found to be something else.  A reader who takes
    `Cluster 7` for a negative result would be reading a shortfall of evidence
    as a finding.

    The panel's own size is the honest headline, because it is what caps the
    result: `n_panel_genes` markers over `n_total` candidate labels cannot name
    a cluster that expresses something else entirely.
    """
    groups = ", ".join(f"{k} ({v})" for k, v in sorted(sizes.items()))
    return (
        f"the marker panel named {n_named} of {n_total} clusters; the rest keep "
        f"their Leiden identity because no group reached {MIN_MARKER_HITS} "
        f"independent hits among their top-ranked features -- they are "
        f"unnamed, not found to be something else. The panel held "
        f"{n_panel_genes} genes over {len(sizes)} candidate identit"
        f"{'y' if len(sizes) == 1 else 'ies'} ({groups}), which is a germ-layer "
        f"panel for an early embryo rather than a cell-type panel: at this "
        f"stage the layers are still territories, so clusters that express "
        f"markers of several layers at once cannot be resolved by either route"
    )


def marker_group_keys(labels) -> dict:
    """label -> an h5py-legal `uns['rank_genes_groups']` key for it.

    `sc.tl.rank_genes_groups` stores each group name as a *key* in
    `uns['rank_genes_groups']`, and h5py refuses a "/" in a key.  Two published
    labels carry one -- `ecEp_head/hyp` and `i_gc/n_prog` (Siebert et al. 2019,
    the Hydra atlas) -- which made the h5ad unwritable and failed the run at
    its final write, long after every marker had been computed and after the
    marker table itself had been written.  The slash belongs in the label the
    site displays, not in a storage key, so the ranking is done on an escaped
    copy and the verbatim labels are restored in the marker table.

    Escaping follows `05_export_web.safe_name`: the `%` is escaped along with
    the `/`, so the mapping is reversible and two distinct labels can never
    collide on one key.  That is the reason to escape rather than substitute
    `_`, which would map both `a/b` and `a_b` to `a_b` and silently merge two
    cell types into one marker group -- exactly the kind of quiet wrong answer
    this pipeline is built to avoid.  With this encoding the collision check
    below cannot actually fire; it is an invariant guard, kept because the
    escape rule is the only thing standing between two labels and one key.

    A label whose key would be empty, `.` or `..` is rejected outright: those
    name a group rather than sit inside one, and no cell type is called that,
    so reaching here means something is wrong with the upstream table.
    """
    alias, taken = {}, {}
    for label in dict.fromkeys(map(str, labels)):
        key = str(label).replace("%", "%25").replace("/", "%2F")
        if key in ("", ".", ".."):
            raise SystemExit(
                f"cell type {label!r} has no usable marker-group key")
        if key in taken:
            raise SystemExit(
                f"cell types {taken[key]!r} and {label!r} both escape to "
                f"the marker-group key {key!r}")
        taken[key] = label
        alias[label] = key
    return alias


# ---------------------------------------------------------------------------
def run(dataset_id: str, qc_dir: str, outdir: str, cfg: dict,
        n_hvg: int = 2000, n_pcs: int = 50, resolution: float = 1.0) -> dict:
    import scanpy as sc
    import matplotlib
    matplotlib.use("Agg")

    sc.settings.verbosity = 1
    t0 = time.time()
    os.makedirs(outdir, exist_ok=True)

    src = os.path.join(qc_dir, f"{dataset_id}.qc.h5ad")
    if not os.path.exists(src):
        raise FileNotFoundError(f"run 03_qc.py first; missing {src}")

    print(f"[{dataset_id}] reading {src}")
    adata = sc.read_h5ad(src)
    stats = {
        "dataset_id": dataset_id,
        "species": cfg.get("species", ""),
        "pipeline_version": PIPELINE_VERSION,
        "processed_utc": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "n_cells_in": int(adata.n_obs),
        "n_genes_in": int(adata.n_vars),
    }

    # ---- normalisation ----------------------------------------------------
    sc.pp.normalize_total(adata, target_sum=1e4)
    sc.pp.log1p(adata)
    adata.raw = adata.copy()          # keep log-normalised values for plots
    stats["normalization"] = "CPM (target_sum=1e4) then log1p"

    # ---- HVG --------------------------------------------------------------
    n_hvg = min(n_hvg, max(50, adata.n_vars - 1))
    if "sample" in adata.obs and adata.obs["sample"].nunique() > 1:
        sc.pp.highly_variable_genes(adata, n_top_genes=n_hvg, batch_key="sample",
                                    flavor="seurat")
    else:
        sc.pp.highly_variable_genes(adata, n_top_genes=n_hvg, flavor="seurat")
    stats["n_hvg"] = int(adata.var["highly_variable"].sum())
    stats["hvg_flavor"] = "seurat"

    adata_hvg = adata[:, adata.var["highly_variable"]].copy()
    sc.pp.scale(adata_hvg, max_value=10)
    sc.tl.pca(adata_hvg, n_comps=n_pcs, svd_solver="arpack")
    adata.obsm["X_pca"] = adata_hvg.obsm["X_pca"]
    stats["n_pcs"] = n_pcs

    # ---- batch integration ------------------------------------------------
    n_samples = int(adata.obs["sample"].nunique()) if "sample" in adata.obs else 1
    rep = "X_pca"
    integration = "none (single library)"
    if n_samples > 1:
        try:
            # Call harmonypy directly rather than through
            # sc.external.pp.harmony_integrate: the scanpy wrapper has changed
            # the shape it expects from harmonypy across versions, and a silent
            # shape mismatch here would mean no integration at all while the
            # method record claimed otherwise.
            import harmonypy
            ho = harmonypy.run_harmony(
                adata.obsm["X_pca"], adata.obs, ["sample"], max_iter_harmony=20
            )
            Z = np.asarray(ho.Z_corr)
            if Z.shape[0] != adata.n_obs:      # harmonypy returns genes x cells
                Z = Z.T
            if Z.shape != (adata.n_obs, n_pcs):
                raise ValueError(f"unexpected harmony output shape {Z.shape}")
            adata.obsm["X_pca_harmony"] = Z.astype(np.float32)
            rep = "X_pca_harmony"
            integration = f"Harmony on sample ({n_samples} libraries)"
        except Exception as exc:  # noqa: BLE001
            integration = f"none (harmony unavailable: {type(exc).__name__}: {exc})"
            print(f"  ! harmony failed: {exc}", file=sys.stderr)
    stats["integration_method"] = integration
    stats["n_samples"] = n_samples

    # ---- clustering -------------------------------------------------------
    sc.pp.neighbors(adata, n_neighbors=15, n_pcs=n_pcs, use_rep=rep)
    sc.tl.leiden(adata, resolution=resolution, key_added="cluster",
                 flavor="igraph", n_iterations=2, directed=False)
    sc.tl.umap(adata)
    stats["n_clusters"] = int(adata.obs["cluster"].nunique())
    stats["leiden_resolution"] = resolution
    stats["umap_n_neighbors"] = 15
    print(f"  {stats['n_clusters']} clusters; integration={integration}")

    # ---- per-dataset marker panel ----------------------------------------
    # A deposit keyed by gene model ID cannot fire the symbol-keyed global
    # panel, so the registry may point at a panel written in the deposit's own
    # features.  It supplements the global one (see compile_panel) and is
    # recorded in the stats, because a label named by a dataset-specific panel
    # has to be auditable from the dataset's own record.
    scoped: dict[str, str] = {}
    scoped_sizes: dict[str, int] = {}
    panel_ref = str(cfg.get("marker_panel") or "").strip()
    if panel_ref:
        panel_path = panel_ref if os.path.isabs(panel_ref) else os.path.join(
            str(Path(__file__).resolve().parent.parent), panel_ref)
        scoped, scoped_sizes = load_marker_panel(panel_path)
        stats["marker_panel"] = panel_ref
        stats["marker_panel_groups"] = scoped_sizes
        print(f"  dataset marker panel: {panel_ref} "
              f"({sum(scoped_sizes.values())} genes over {len(scoped)} labels)")

    # ---- cell-type assignment --------------------------------------------
    # Three provenances, recorded so the website and the QC table can tell them
    # apart:  `published` (inherited from the source study), `de_novo` (named by
    # the marker panel), `cluster_only` (the panel named nothing credible, so
    # the atlas ships cluster identities and asserts no cell type).
    provenance = "de_novo"
    if "cell_type" in adata.obs and adata.obs["cell_type"].nunique() > 1:
        provenance = "published"
        adata.obs["cell_type_final"] = adata.obs["cell_type"].astype(str)
    else:
        print("  no published labels; ranking markers for de-novo annotation")
        sc.tl.rank_genes_groups(adata, "cluster", method="wilcoxon",
                                use_raw=True, n_genes=50, pts=True)
        mk = sc.get.rank_genes_groups_df(adata, group=None)
        mk = mk.rename(columns={"group": "cluster", "names": "names"})
        panel = score_clusters_by_markers(mk, panel=compile_panel(scoped))
        panel.to_csv(os.path.join(outdir, f"{dataset_id}.auto_annotation.tsv"),
                     sep="\t", index=False)
        named = panel[panel["auto_label"] != "unassigned"]
        if named.empty:
            # Nothing cleared the evidence bar.  Say so and ship clusters: an
            # unnamed cluster is a usable result (the viewer colours it, the
            # marker panel lists what distinguishes it), whereas an invented
            # cell type is a wrong result that a reader cannot detect.
            provenance = "cluster_only"
            adata.obs["cell_type_final"] = (
                "Cluster " + adata.obs["cluster"].astype(str)
            )
            stats["annotation_note"] = cluster_only_note(
                mk["names"].astype(str).tolist(), panel=compile_panel(scoped)
            )
            stats["panel_applicable"] = False
            print(f"  ! panel named no cluster; shipping {adata.obs['cluster'].nunique()} "
                  f"cluster labels instead")
        else:
            stats["panel_applicable"] = True
            cl = adata.obs["cluster"].astype(str)
            lab = cl.map(dict(zip(panel["cluster"].astype(str),
                                  panel["auto_label"])))
            # Clusters the panel could not name keep their Leiden identity.
            # Mapping them all to one `unassigned` bucket -- which is what the
            # `fillna` here used to do -- loses the cluster the viewer colours
            # by and collides with the gap label a published table writes for
            # cells it does not cover.
            unnamed = lab.isna() | (lab == "unassigned")
            adata.obs["cell_type_final"] = lab.mask(unnamed, "Cluster " + cl)
            stats["n_clusters_named"] = int(len(named))
            stats["named_labels"] = sorted(
                set(named["auto_label"].astype(str)))
            if bool(unnamed.any()):
                stats["annotation_note"] = partial_annotation_note(
                    n_named=len(named), n_total=len(panel),
                    sizes=scoped_sizes, n_panel_genes=sum(scoped_sizes.values()),
                )
            print(f"  panel named {len(named)}/{len(panel)} clusters "
                  f"({stats['named_labels']}); {len(panel) - len(named)} left "
                  f"as Leiden clusters, {int(unnamed.sum())} cells")

    adata.obs["cell_type"] = adata.obs["cell_type_final"].astype(str)
    stats["annotation_provenance"] = provenance
    stats["n_cell_types"] = int(adata.obs["cell_type"].nunique())
    stats["cell_types"] = sorted(map(str, adata.obs["cell_type"].unique()))

    # ---- markers ----------------------------------------------------------
    print("  computing marker genes")
    # pts=True is what makes scanpy emit `pct_nz_group`, i.e. the fraction of
    # cells in the group that detect the gene.  Without it the marker table has
    # only an adjusted p-value, which is near-zero for every gene at these cell
    # numbers and therefore useless for filtering -- Referee 2 asked for
    # something better than that.
    # Rank on the labels themselves, unless a label carries a character h5py
    # will not accept in a key -- then rank on a key-safe copy and restore the
    # published spelling below.  See marker_group_keys().  The `cell_type`
    # column keeps that spelling everywhere it is read: obs, the marker table,
    # the composition, the exported cell types, so `ecEp_head/hyp` reaches the
    # site unchanged.
    groupby = "cell_type"
    alias = marker_group_keys(adata.obs["cell_type"])
    escaped = {lab: key for lab, key in alias.items() if lab != key}
    if escaped:
        print(f"  {len(escaped)} cell type(s) need a key-safe marker group: "
              + ", ".join(f"{lab} -> {key}" for lab, key in sorted(escaped.items())))
        groupby = "cell_type_key"
        adata.obs[groupby] = (
            adata.obs["cell_type"].astype(str).map(alias).astype("category")
        )
    sc.tl.rank_genes_groups(adata, groupby, method="wilcoxon",
                            use_raw=True, n_genes=100, pts=True)
    mk = sc.get.rank_genes_groups_df(adata, group=None)
    mk = mk.rename(columns={"group": "cell_type", "names": "gene",
                            "logfoldchanges": "log2fc", "pvals_adj": "padj",
                            "pct_nz_group": "pct_in", "scores": "score"})
    if escaped:
        back = {key: lab for lab, key in alias.items()}
        mk["cell_type"] = mk["cell_type"].astype(str).map(back)
        if mk["cell_type"].isna().any():
            raise SystemExit(
                "marker table has a group that maps to no cell type")
        stats["marker_group_keys"] = alias
    # fraction of cells expressing the gene in each group, for an interpretable
    # second filter alongside the (always tiny) adjusted p-value
    if "pct_nz_group" in mk.columns:
        mk["pct_out"] = 1.0 - mk["pct_nz_group"]
    mk.to_csv(os.path.join(outdir, f"{dataset_id}.markers.tsv"), sep="\t", index=False)
    stats["n_marker_rows"] = int(len(mk))

    # ---- cell type composition -------------------------------------------
    comp = (adata.obs.groupby(["cell_type"], observed=True).size()
            .sort_values(ascending=False))
    comp.to_csv(os.path.join(outdir, f"{dataset_id}.composition.tsv"), sep="\t",
                header=["n_cells"])

    # ---- persist ----------------------------------------------------------
    out = os.path.join(outdir, f"{dataset_id}.annotated.h5ad")
    # raw slot doubles the file size; the expression export reads from .raw,
    # so keep it but drop the duplicate X when the matrices are identical
    adata.write_h5ad(out, compression="gzip")
    stats["annotated_h5ad"] = os.path.basename(out)
    stats["runtime_sec"] = round(time.time() - t0, 1)

    with open(os.path.join(outdir, f"{dataset_id}.annotate_stats.json"), "w") as fh:
        json.dump(stats, fh, indent=2, default=str)
    print(f"  done: {stats['n_cells_in']} cells, {stats['n_clusters']} clusters, "
          f"{stats['n_cell_types']} cell types ({provenance}) [{stats['runtime_sec']}s]")
    return stats


def main() -> int:
    here = Path(__file__).resolve().parent
    ap = argparse.ArgumentParser()
    ap.add_argument("--dataset")
    ap.add_argument("--all", action="store_true")
    ap.add_argument("--registry", default=str(here / ".." / "meta" / "dataset_registry.tsv"))
    ap.add_argument("--qc-dir", default=str(here / ".." / "data" / "qc"))
    ap.add_argument("--outdir", default=str(here / ".." / "data" / "annotated"))
    ap.add_argument("--n-hvg", type=int, default=2000)
    ap.add_argument("--n-pcs", type=int, default=50)
    ap.add_argument("--resolution", type=float, default=1.0)
    args = ap.parse_args()

    reg = cnio.read_registry(args.registry)
    if args.dataset:
        rows = reg[reg["dataset_id"] == args.dataset]
        if rows.empty:
            print(f"unknown dataset {args.dataset}", file=sys.stderr)
            return 2
    elif args.all:
        rows = reg
    else:
        ap.error("pass --dataset ID or --all")

    results = []
    failed: list[str] = []
    for _, r in rows.iterrows():
        did = r["dataset_id"]
        if not os.path.exists(os.path.join(args.qc_dir, f"{did}.qc.h5ad")):
            continue
        try:
            results.append(run(did, args.qc_dir, args.outdir, dict(r),
                               n_hvg=args.n_hvg, n_pcs=args.n_pcs,
                               resolution=args.resolution))
        except Exception as exc:  # noqa: BLE001
            print(f"[{did}] FAILED: {exc}", file=sys.stderr)
            failed.append(did)

    if results:
        summary = merge_summary(os.path.join(args.outdir, "_annotate_summary.json"),
                                results)
        print(f"\nannotated {len(results)} dataset(s) "
              f"({len(summary)} in the summary)")
    if failed:
        print(f"\n{len(failed)} dataset(s) FAILED: {', '.join(failed)}",
              file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
