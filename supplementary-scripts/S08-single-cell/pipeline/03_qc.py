#!/usr/bin/env python3
"""
03_qc.py -- per-dataset quality control for the CnidoSite single-cell module.

This replaces the single global filter described in the manuscript
(nCount_RNA 600-5000, Percent.mt 30-80%) which Referee 2 correctly identified
as both a typographical error and, more fundamentally, an inappropriate
strategy: one fixed UMI window cannot be right across 15 species, six tissue
classes, developmental series and at least three library chemistries.

Two modes are supported and recorded per dataset:

  published  -- reproduce the thresholds stated in the dataset's own
                publication, so the CnidoSite atlas stays comparable with the
                source study and with the cell-type labels we inherit from it.
  adaptive   -- no usable published threshold exists, so use per-sample
                MAD-based outlier detection (see lib/mito.mad_outlier_mask).
  absolute   -- the deposit is the *unfiltered* barcode matrix (empty droplets
                included), so the MAD band would be fitted to the empty mode
                and remove the real cells.  Absolute floors only, no MAD.

Every decision is written to `qc_stats.json` so the dataset-level table
demanded by Referee 2 (cells before/after filtering, library type,
mitochondrial distributions, doublet removal) is generated from the actual
run rather than transcribed.

A field that is a plan rather than a measurement is named as one:
`integration_declared` is the procedure this stage was told to use, because
integration itself happens in `04_cluster_annotate.py`, which records what it
actually did under `integration_method`.

Usage
-----
    python 03_qc.py --dataset AMILL_whole_adult \
        --input ../data/raw/GSE289546 --format mtx --outdir ../data/qc

    python 03_qc.py --all --outdir ../data/qc        # every configured dataset
"""

from __future__ import annotations

import argparse
import json
import os
import platform as _platform
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from lib import io as cnio          # noqa: E402
from lib.mito import find_mito_genes, mad_outlier_mask, qc_metrics  # noqa: E402
# The batch summary is merged by `dataset_id`, never overwritten with this run's
# datasets -- `--dataset X` is how this pipeline is normally driven, and X alone
# is not what `_qc_summary.json` is supposed to say.  See `lib/summary.py`.
from lib.summary import merge_summary  # noqa: E402

PIPELINE_VERSION = "cnidosite-sc-1.0.0"


# ---------------------------------------------------------------------------
# configuration
# ---------------------------------------------------------------------------
def load_thresholds(path: str) -> dict[str, dict]:
    """Read the per-dataset threshold table.

    Columns (blank => use the mode's default):
        dataset_id, mode, min_genes, min_counts, max_counts, max_pct_mt,
        nmads, min_cells, doublet_method, integration_method, source_note
    """
    if not os.path.exists(path):
        return {}
    df = pd.read_csv(path, sep="\t", dtype=str).fillna("")
    return {r["dataset_id"]: r.to_dict() for _, r in df.iterrows()}


def _num(d: dict, key: str, default=None):
    v = str(d.get(key, "")).strip()
    if v == "":
        return default
    try:
        return float(v)
    except ValueError:
        return default


def resolve_thresholds(cfg: dict, species: str) -> dict:
    """Turn a config row into a concrete, auditable threshold set."""
    mode = (cfg.get("mode") or "adaptive").strip().lower()

    if mode == "absolute":
        # For a deposit that ships the *unfiltered* barcode matrix -- every
        # barcode the sequencer saw, including empty droplets -- the MAD band
        # cannot be used, because the distribution is dominated by the empty
        # mode.  Measured on NVECT_2month (GSE253068): 10,000 barcodes of which
        # ~8,400 are empty (median 102 counts / 79 genes) against 1,614 real
        # cells at 500+ counts.  Five MADs above that median falls below the
        # real cells, so `adaptive` removed all 10,000 and the dataset looked
        # empty rather than unfiltered.  Absolute guards only, same floors as
        # the other modes.
        return {
            "mode": "absolute",
            "min_genes": _num(cfg, "min_genes", 200),
            "min_counts": _num(cfg, "min_counts", 500),
            "max_counts": _num(cfg, "max_counts", None),
            "max_pct_mt": _num(cfg, "max_pct_mt", 50.0),
            "nmads": None,
            "min_cells": _num(cfg, "min_cells", 3),
            "source": cfg.get("source_note", ""),
        }

    if mode == "published":
        return {
            "mode": "published",
            "min_genes": _num(cfg, "min_genes", 200),
            "min_counts": _num(cfg, "min_counts", 500),
            "max_counts": _num(cfg, "max_counts", None),
            "max_pct_mt": _num(cfg, "max_pct_mt", 20.0),
            "nmads": None,
            "min_cells": _num(cfg, "min_cells", 3),
            "source": cfg.get("source_note", ""),
        }

    # adaptive defaults.  max_pct_mt is intentionally generous here because the
    # MAD rule already removes the mitochondrial tail; the hard cap only guards
    # against obviously dead cells when an annotation has very few MT genes.
    return {
        "mode": "adaptive",
        "min_genes": _num(cfg, "min_genes", 200),
        "min_counts": _num(cfg, "min_counts", 500),
        "max_counts": _num(cfg, "max_counts", None),
        "max_pct_mt": _num(cfg, "max_pct_mt", 50.0),
        "nmads": _num(cfg, "nmads", 5.0),
        "min_cells": _num(cfg, "min_cells", 3),
        "source": cfg.get("source_note", ""),
    }


# ---------------------------------------------------------------------------
# gene / cell filtering
# ---------------------------------------------------------------------------
def filter_genes(adata, min_cells: float):
    """Drop genes detected in fewer than `min_cells` cells."""
    n_before = adata.n_vars
    import scanpy as sc
    sc.pp.filter_genes(adata, min_cells=int(min_cells))
    return n_before - adata.n_vars


def cell_qc_mask(adata, th: dict, mito_available: bool) -> tuple[np.ndarray, dict]:
    """Return (keep_mask, per-criterion removal counts).

    In adaptive mode the count/gene bounds and the mitochondrial bound are
    computed per sample; in published mode they are applied globally, exactly
    as the source study did.
    """
    obs = adata.obs
    n = adata.n_obs
    reasons = {k: 0 for k in ("low_genes", "low_counts", "high_counts", "high_mt", "mad_outlier")}
    keep = np.ones(n, dtype=bool)

    if th["mode"] == "adaptive":
        # per-sample MAD on the three distributions that actually vary between
        # libraries: library size, gene count and mitochondrial fraction.
        for col, side in (("n_counts", "both"), ("n_genes", "both"), ("pct_mt", "high")):
            if col not in obs:
                continue
            vals = obs[col].to_numpy(dtype=float)
            if col == "pct_mt" and not mito_available:
                continue
            outlier = np.zeros(n, dtype=bool)
            for s in obs["sample"].astype(str).unique():
                sel = (obs["sample"].astype(str) == s).to_numpy()
                outlier[sel] = mad_outlier_mask(vals[sel], nmads=th["nmads"], side=side)
            # Counted for EVERY band, not just n_counts.  The three bands catch
            # different cells, and a cell removed by the n_genes or pct_mt band
            # but never counted leaves `cells_removed_by_criterion` summing to
            # less than the number of cells that actually went -- which is what
            # the invariant below asserts cannot happen (NVECT_notch48h removed
            # 945 cells and attributed 940 before this was fixed).
            # `& keep` keeps the counters mutually exclusive, as the fixed
            # guards below do.
            reasons["mad_outlier"] += int((outlier & keep).sum())
            keep &= ~outlier

    # fixed guards, always applied
    if th["min_genes"] is not None:
        bad = (obs["n_genes"].to_numpy(dtype=float) < th["min_genes"])
        reasons["low_genes"] = int((bad & keep).sum()); keep &= ~bad
    if th["min_counts"] is not None:
        bad = (obs["n_counts"].to_numpy(dtype=float) < th["min_counts"])
        reasons["low_counts"] = int((bad & keep).sum()); keep &= ~bad
    if th["max_counts"] is not None:
        bad = (obs["n_counts"].to_numpy(dtype=float) > th["max_counts"])
        reasons["high_counts"] = int((bad & keep).sum()); keep &= ~bad
    if th["max_pct_mt"] is not None and mito_available:
        bad = (obs["pct_mt"].to_numpy(dtype=float) > th["max_pct_mt"])
        reasons["high_mt"] = int((bad & keep).sum()); keep &= ~bad

    return keep, reasons


# ---------------------------------------------------------------------------
# doublets
# ---------------------------------------------------------------------------
def run_scrublet(adata, per_sample: bool = True, expected_rate: float | None = None):
    """Doublet detection with Scrublet, run per library.

    Returns (doublet_mask, method_note).  Falls back to the scanpy
    implementation when the standalone package is absent.
    """
    try:
        import scrublet as scr
    except ImportError:
        scr = None

    n = adata.n_obs
    mask = np.zeros(n, dtype=bool)

    groups = adata.obs["sample"].astype(str).unique() if per_sample else ["__all__"]
    for s in groups:
        sel = (adata.obs["sample"].astype(str) == s).to_numpy() if per_sample else np.ones(n, dtype=bool)
        sub = adata[sel]
        if sub.n_obs < 100:
            continue
        try:
            if scr is not None:
                sc_ = scr.Scrublet(sub.X, expected_doublet_rate=expected_rate or 0.06)
                # scrub_doublets returns (doublet_scores, predicted_doublets).
                # Taking the scores here would silently coerce every non-zero
                # float to True and discard the whole dataset, so bind by name.
                scores, predicted = sc_.scrub_doublets(verbose=False)
                if predicted is None:
                    raise RuntimeError("scrublet returned no prediction")
                mask[np.where(sel)[0]] = np.asarray(predicted, dtype=bool)
            else:  # pragma: no cover - scanpy fallback
                import scanpy as sc
                sc.pp.scrublet(sub, expected_doublet_rate=expected_rate or 0.06, verbose=False)
                col = "predicted_doublet"
                if col in sub.obs:
                    mask[np.where(sel)[0]] = sub.obs[col].to_numpy(dtype=bool)
        except Exception as exc:  # noqa: BLE001
            print(f"    ! scrublet failed for {s}: {exc}", file=sys.stderr)

    # A per-library doublet rate above 50% is not a biological result, it is a
    # failed run (degenerate simulation, near-empty matrix, threshold detection
    # collapse).  Refuse it rather than letting it empty the atlas, and say so
    # in the record so the number is never reported as if it were real.
    rate = float(mask.mean()) if n else 0.0
    if rate > 0.5:
        print(f"    ! doublet rate {rate:.1%} implausible; discarding doublet calls "
              f"for this dataset", file=sys.stderr)
        return np.zeros(n, dtype=bool), (
            f"Scrublet FAILED (implausible rate {rate:.1%}) - no doublets removed"
        )

    method = "Scrublet (per library)" if scr is not None else "scanpy.pp.scrublet (per library)"
    return mask, method


# ---------------------------------------------------------------------------
# main per-dataset routine
# ---------------------------------------------------------------------------
def describe(values) -> dict:
    v = pd.Series(values).replace([np.inf, -np.inf], np.nan).dropna().to_numpy(dtype=float)
    if v.size == 0:
        return {}
    return {
        "min": float(np.min(v)), "p5": float(np.percentile(v, 5)),
        "median": float(np.median(v)), "mean": float(np.mean(v)),
        "p95": float(np.percentile(v, 95)), "max": float(np.max(v)),
    }


def process(dataset_id: str, paths: dict, outdir: str, cfg: dict,
            cell_type_table: str | None = None) -> dict:
    t0 = time.time()
    species = cfg.get("species", "")
    th = resolve_thresholds(cfg, species)

    print(f"[{dataset_id}] loading {paths['input']} ({paths.get('format') or 'auto'})")
    adata = cnio.load_counts(paths["input"], fmt=paths.get("format"))
    stats: dict = {
        "dataset_id": dataset_id,
        "species": species,
        "class": cfg.get("class", ""),
        "bioproject": cfg.get("bioproject", ""),
        "sra_study": cfg.get("sra_study", ""),
        "geo_series": cfg.get("geo_series", ""),
        "tissue_organ": cfg.get("tissue_organ", ""),
        "stage": cfg.get("stage", ""),
        "platform_class": cfg.get("platform_class", ""),
        "pipeline_version": PIPELINE_VERSION,
        "processed_utc": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "n_cells_raw": int(adata.n_obs),
        "n_genes_raw": int(adata.n_vars),
        "n_samples": int(adata.obs["sample"].nunique()),
        "samples": sorted(map(str, adata.obs["sample"].unique()))[:60],
        "doublet_method": cfg.get("doublet_method") or "Scrublet",
        # The declared plan, not a result -- and named so.  Nothing in this
        # stage can observe integration: it is `04_cluster_annotate.py` that
        # runs Harmony and records what it did, under `integration_method`.
        # This field used to be called that too, which made `data/qc/*.qc_stats.json`
        # claim `Harmony` even for the seven single-library datasets where
        # `04` recorded `none (single library)` -- the plan read as an
        # observation.  `doublet_method` above is different: `run_scrublet`
        # overwrites it with what actually ran, so it stays `..._method`.
        "integration_declared": cfg.get("integration_method") or "Harmony (per dataset)",
        "thresholds": th,
    }

    # ---- mitochondrial gene resolution -----------------------------------
    symbols = adata.var["gene_symbol"] if "gene_symbol" in adata.var else None
    descs = adata.var["description"] if "description" in adata.var else None
    mito = find_mito_genes(adata.var_names, symbols, descs, species=species)
    stats.update(mito.summary())
    print(f"  mito genes: {len(mito.genes)} ({mito.strategy}); filtering_available={mito.available}")

    # ---- QC metrics -------------------------------------------------------
    qc_metrics(adata, mito.genes)
    stats["mito_distribution_raw"] = describe(adata.obs["pct_mt"])
    stats["counts_distribution_raw"] = describe(adata.obs["n_counts"])
    stats["genes_distribution_raw"] = describe(adata.obs["n_genes"])

    # ---- gene filter ------------------------------------------------------
    n_genes_dropped = filter_genes(adata, th["min_cells"])
    stats["n_genes_dropped_low_detection"] = int(n_genes_dropped)
    stats["n_genes_retained"] = int(adata.n_vars)

    # ---- cell filter ------------------------------------------------------
    keep, reasons = cell_qc_mask(adata, th, mito.available)
    stats["cells_removed_by_criterion"] = reasons
    stats["n_cells_after_cell_qc"] = int(keep.sum())
    adata = adata[keep].copy()
    print(f"  after cell QC: {adata.n_obs} cells (removed {int((~keep).sum())})")

    # ---- doublets ---------------------------------------------------------
    if adata.n_obs > 0 and str(cfg.get("doublet_method", "")).lower() != "none":
        mask, method = run_scrublet(adata)
        stats["doublet_method"] = method
        stats["n_doublets_removed"] = int(mask.sum())
        stats["doublet_rate"] = round(float(mask.mean()), 4) if adata.n_obs else 0.0
        adata = adata[~mask].copy()
    else:
        stats["n_doublets_removed"] = 0
        stats["doublet_rate"] = 0.0

    stats["n_cells_final"] = int(adata.n_obs)
    stats["pct_cells_retained"] = (
        round(100.0 * adata.n_obs / stats["n_cells_raw"], 2) if stats["n_cells_raw"] else 0.0
    )
    stats["mito_distribution_final"] = describe(adata.obs["pct_mt"])
    stats["counts_distribution_final"] = describe(adata.obs["n_counts"])
    stats["cells_per_sample"] = (
        adata.obs["sample"].astype(str).value_counts().to_dict()
    )

    # ---- inherit published cell types where available ---------------------
    if cell_type_table and os.path.exists(cell_type_table):
        try:
            ct = cnio.read_cell_type_table(cell_type_table)
            ids = pd.Index(adata.obs["cell_id"].astype(str))
            mapped = ids.map(ct)
            hit = mapped.notna().mean()
            adata.obs["cell_type"] = mapped.fillna("unassigned").astype(str).values
            stats["published_annotation"] = {
                "source": os.path.basename(cell_type_table),
                "match_rate": round(float(hit), 4),
                "n_types": int(mapped.dropna().nunique()),
                "types": sorted(map(str, mapped.dropna().unique()))[:80],
            }
            print(f"  inherited published cell types for {hit:.1%} of cells")
        except Exception as exc:  # noqa: BLE001
            stats["published_annotation"] = {"error": str(exc)}

    # ---- persist ----------------------------------------------------------
    os.makedirs(outdir, exist_ok=True)
    h5 = os.path.join(outdir, f"{dataset_id}.qc.h5ad")
    adata.write_h5ad(h5, compression="gzip")
    stats["qc_h5ad"] = os.path.basename(h5)
    stats["runtime_sec"] = round(time.time() - t0, 1)
    stats["host"] = _platform.node()

    with open(os.path.join(outdir, f"{dataset_id}.qc_stats.json"), "w") as fh:
        json.dump(stats, fh, indent=2, default=str)

    print(f"  -> {adata.n_obs} cells x {adata.n_vars} genes  ({stats['runtime_sec']}s)")
    return stats


def main() -> int:
    here = Path(__file__).resolve().parent
    ap = argparse.ArgumentParser()
    ap.add_argument("--dataset", help="dataset_id from the registry")
    ap.add_argument("--all", action="store_true", help="process every configured dataset")
    ap.add_argument("--input", help="override input path")
    ap.add_argument("--format", help="10x | mtx | h5ad | rds")
    ap.add_argument("--cell-types", help="published cell->cell-type table")
    ap.add_argument("--registry", default=str(here / ".." / "meta" / "dataset_registry.tsv"))
    ap.add_argument("--thresholds", default=str(here / ".." / "meta" / "qc_thresholds.tsv"))
    ap.add_argument("--outdir", default=str(here / ".." / "data" / "qc"))
    args = ap.parse_args()

    reg = cnio.read_registry(args.registry)
    thresholds = load_thresholds(args.thresholds)

    # Registry paths are written relative to the project root (the parent of
    # this script's directory) so the table stays portable between machines.
    root = Path(__file__).resolve().parent.parent

    def _resolve(p: str) -> str:
        if not p:
            return p
        p = os.path.expanduser(str(p))
        return p if os.path.isabs(p) else str((root / p).resolve())

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
        cfg = dict(r)
        cfg.update(thresholds.get(did, {}))
        inp = _resolve(args.input or r.get("local_input", ""))
        if not inp:
            print(f"[{did}] no local input configured; skipping", file=sys.stderr)
            continue
        if not os.path.exists(inp):
            print(f"[{did}] input {inp} not present; skipping", file=sys.stderr)
            continue
        try:
            results.append(process(
                did,
                {"input": inp, "format": args.format or r.get("local_format") or None},
                _resolve(args.outdir), cfg,
                _resolve(args.cell_types) if args.cell_types else _resolve(r.get("cell_type_table", "")) or None,
            ))
        except Exception as exc:  # noqa: BLE001
            print(f"[{did}] FAILED: {exc}", file=sys.stderr)
            failed.append(did)

    if results:
        out = os.path.join(args.outdir, "_qc_summary.json")
        # Merged, not overwritten: this used to be `json.dump(results, fh)`, so
        # a one-dataset re-run left a one-dataset summary behind -- which is
        # exactly what `data/qc/_qc_summary.json` was holding when the bundle
        # was audited on 2026-09-20 (NVECT_embryo alone, eleven entries gone).
        summary = merge_summary(out, results)
        print(f"\nwrote {out}  ({len(results)} run, "
              f"{len(summary)} in the summary)")

    # A dataset that raised is a failure, not a skip.  Returning 0 here meant a
    # run whose every dataset had failed still reported success, and the next
    # stage then ran against whatever stale record happened to be on disk.  A
    # build script that cannot fail is worse than one that fails loudly.
    if failed:
        print(f"\n{len(failed)} dataset(s) FAILED: {', '.join(failed)}",
              file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
