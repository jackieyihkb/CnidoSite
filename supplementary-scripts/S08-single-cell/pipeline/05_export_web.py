#!/usr/bin/env python3
"""
05_export_web.py -- turn an annotated AnnData into the static assets consumed
by the CnidoSite interactive Cell Atlas viewer.

Why static assets rather than a live backend
--------------------------------------------
CnidoSite runs PHP + MySQL behind Apache.  The current Cell Atlas ships two
pre-rendered PNGs per tissue, which is what Referee 1 ("scRNA-seq data are
presented only as static, non-interactive images") and Referee 3 ("two
identical UMAP visualisations... the clusters are not identified by cell
type") objected to.  Shipping a Python service would need a new runtime on the
server; exporting the embedding and the marker panel as compact typed arrays
keeps the whole viewer client-side and needs nothing beyond `mod_headers`.

Asset layout, per dataset, under `singlecell_data/<DATASET_ID>/`
---------------------------------------------------------------
  manifest.json      dataset metadata, cell-type table, QC summary, provenance
  embedding.bin      float32[n_cells * 2]                UMAP coordinates
  cellmeta.bin       uint16[n_cells * 3]  cell_type / cluster / sample codes
  qc.bin             float32[n_cells * 3] n_counts / n_genes / pct_mt
  composition.json   cell_type x sample cell counts (for the bar chart)
  markers.json       ranked markers per cell type (drives the marker panel)
  genes.json         index of genes with an expression file, + dequant scale
  expression/<gene>.bin.gz   sparse per-cell expression, lazily fetched

All multi-byte values are little-endian, which is what every browser's
TypedArray view assumes on the platforms the site targets.

Usage
-----
    python 05_export_web.py --dataset AMILL_whole_adult \
        --annotated-dir ../data/annotated --outdir ../web/singlecell_data
    python 05_export_web.py --reindex      # rewrite the index, export nothing
"""

from __future__ import annotations

import argparse
import gzip
import json
import os
import re
import struct
import sys
import time
import urllib.parse
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd
import scipy.sparse as sp

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from lib import io as cnio  # noqa: E402

PIPELINE_VERSION = "cnidosite-sc-1.0.0"
EXPORT_VERSION = "1.0"

#: Top-N markers per cell type to ship as a loadable expression file.  The
#: viewer lazily fetches one file per gene, so this is the practical ceiling on
#: how many genes a user can colour a feature plot by.
N_MARKERS_PER_TYPE = 50

#: Cap on how many genes get an expression file, to bound file count per
#: dataset.  Marker panels across cell types overlap heavily, so the unique
#: count is normally far below n_types * N_MARKERS_PER_TYPE.
MAX_EXPORT_GENES = 3000

#: The directory name the assets live under on the site, and therefore the only
#: path an index entry may name.
#:
#: `_export_index.json` is fetched by `web/viewer/index.html` and is a public
#: file.  It used to carry `dir` as the build machine's own filesystem path
#: -- `/mnt/sda/.../pipeline/../web/singlecell_data/<dataset>` -- which
#: published the directory layout of a machine no reader can reach, and was
#: wrong for every reader even as a description of the site.  The index
#: describes where the files are *served* from, so it says that and nothing
#: about the machine that wrote it.
EXPORT_ROOT = "singlecell_data"


def published_dir(dataset_id: str) -> str:
    """Where `dataset_id`'s assets are served from, site-absolute.

    Deliberately not derived from the `--outdir` in use: the index is a
    published description of the deployed site, and an export staged into a
    temporary directory is still destined for this path.  The same shape the
    database records in `singlecell_atlas.asset_dir`, so the two agree.
    """
    return "/%s/%s/" % (EXPORT_ROOT, dataset_id)



def safe_name(gene: str) -> str:
    """Filesystem-safe gene identifier.

    Gene IDs carry slashes, colons and dots (e.g. `Amil_Amillepora00001`,
    `NV2T1g1234.t1`, `LOC130612030`).  Anything outside [A-Za-z0-9._-] is
    percent-escaped so the mapping stays reversible and two distinct genes can
    never collide, which a plain `re.sub(r'\\W','_')` would allow.
    """
    out = []
    for ch in str(gene):
        if re.match(r"[A-Za-z0-9._-]", ch):
            out.append(ch)
        else:
            out.append("%%%02X" % ord(ch))
    s = "".join(out)
    return s[:180]  # keep well under the 255-byte filename limit


def url_quote(disk_name: str) -> str:
    """The URL path for a file `safe_name` produced.

    Percent-escaping a name and then using it as a URL is a round trip through
    two different decoders.  `safe_name('g10034.t1|CEL2A_PIG')` is
    `g10034.t1%7CCEL2A_PIG` -- correct as a filename, but in a URL that `%7C`
    means `|`, so the server looks for the unescaped name and misses.  Quoting
    the escaped name re-escapes the `%` as `%25`, which decodes back to the
    filename that exists.  Only the `%`, and any character `safe_name` already
    left alone, are involved, so `unquote` of the result always returns the
    input -- which is the property the test pins.
    """
    return urllib.parse.quote(disk_name)


def write_expression(adata, gene: str, out_path: str) -> int:
    """Write one gene's per-cell expression as a compact sparse file.

    Format (gzip-compressed):
        magic   b'CX1'          3 bytes
        vmax    float32         log-normalised value mapped to quantisation 255
        n       uint32          number of expressing cells
        then n * (uint32 delta_index, uint8 quantised_value)

    Storing indices as gaps rather than absolute positions makes the stream
    very compressible (cells are in UMAP/metadata order, and expression is
    spatially autocorrelated), and quantising to 1 byte costs nothing visible
    in a feature plot while cutting the payload ~4x versus float32.
    """
    if gene not in adata.var_names:
        return 0
    idx = adata.var_names.get_loc(gene)
    col = adata.X[:, idx]
    col = col.toarray().ravel() if sp.issparse(col) else np.asarray(col).ravel()

    nz = np.flatnonzero(col > 0)
    if nz.size == 0:
        return 0
    vals = col[nz].astype(np.float32)
    vmax = float(vals.max()) or 1.0
    q = np.clip(np.round(vals / vmax * 255.0), 0, 255).astype(np.uint8)

    deltas = np.diff(nz, prepend=0).astype(np.uint32)
    body = np.empty(deltas.size * 5, dtype=np.uint8)
    body[0::5] = (deltas & 0xFF).astype(np.uint8)
    body[1::5] = ((deltas >> 8) & 0xFF).astype(np.uint8)
    body[2::5] = ((deltas >> 16) & 0xFF).astype(np.uint8)
    body[3::5] = ((deltas >> 24) & 0xFF).astype(np.uint8)
    body[4::5] = q

    os.makedirs(os.path.dirname(out_path), exist_ok=True)
    # mtime=0, not gzip.open(): the default stamps the header with the current
    # time, so re-exporting *unchanged* data rewrote all 8,686 files with
    # different bytes.  Measured 2026-09-20: an md5 comparison of two export
    # trees came back with 1,387 lines of diff, every one of them a file whose
    # payload was identical -- which is exactly the noise that hides a real
    # difference (five files that day: two genes.json and two manifest.json
    # still publishing the `%7C` URLs that 404, plus the index that counts
    # them).  A reproducible export makes "the two trees match" a check that
    # means something, and keeps rsync --checksum from re-sending 96 MB.
    # Nothing reads the stamp: the viewer gunzips with DecompressionStream,
    # which ignores it.  The FNAME field stays as gzip.open wrote it, so the
    # bytes still depend on the basename -- pass the same name to compare.
    with gzip.GzipFile(out_path, "wb", compresslevel=6, mtime=0) as fh:
        fh.write(b"CX1")
        fh.write(struct.pack("<fI", vmax, nz.size))
        fh.write(body.tobytes())
    return int(nz.size)


def export(dataset_id: str, annotated_dir: str, outdir: str,
           n_markers: int = N_MARKERS_PER_TYPE,
           registry_species: str = "", registry: dict | None = None) -> dict:
    import anndata as ad

    t0 = time.time()
    src = os.path.join(annotated_dir, f"{dataset_id}.annotated.h5ad")
    if not os.path.exists(src):
        raise FileNotFoundError(f"run 04_cluster_annotate.py first; missing {src}")

    print(f"[{dataset_id}] reading {src}")
    adata = ad.read_h5ad(src)
    n = adata.n_obs

    dest = os.path.join(outdir, dataset_id)
    os.makedirs(dest, exist_ok=True)

    # log-normalised values live in .raw; if absent, use X as-is
    expr = adata.raw.to_adata() if adata.raw is not None else adata
    if expr.n_vars != adata.n_vars:
        # .raw can carry a different gene set; restrict X to the intersection
        common = adata.var_names.intersection(expr.var_names)
        expr = expr[:, common]

    # ---- categorical encodings -------------------------------------------
    def encode(col: str) -> tuple[np.ndarray, list[str]]:
        cats = sorted(map(str, adata.obs[col].astype(str).unique()))
        lut = {c: i for i, c in enumerate(cats)}
        codes = adata.obs[col].astype(str).map(lut).to_numpy(dtype=np.uint16)
        return codes, cats

    ct_codes, ct_names = encode("cell_type")
    cl_codes, cl_names = encode("cluster")
    smp_codes, smp_names = encode("sample")

    # ---- embedding --------------------------------------------------------
    if "X_umap" not in adata.obsm:
        raise KeyError("annotated object has no X_umap; run 04 with UMAP enabled")
    umap = np.ascontiguousarray(adata.obsm["X_umap"][:, :2], dtype="<f4")
    umap.tofile(os.path.join(dest, "embedding.bin"))

    # ---- per-cell metadata ------------------------------------------------
    meta = np.empty((n, 3), dtype="<u2")
    meta[:, 0], meta[:, 1], meta[:, 2] = ct_codes, cl_codes, smp_codes
    meta.tofile(os.path.join(dest, "cellmeta.bin"))

    # ---- QC ---------------------------------------------------------------
    qc = np.zeros((n, 3), dtype="<f4")
    for j, key in enumerate(("n_counts", "n_genes", "pct_mt")):
        if key in adata.obs:
            qc[:, j] = np.nan_to_num(
                adata.obs[key].to_numpy(dtype=np.float32), nan=-1.0
            )
    qc.tofile(os.path.join(dest, "qc.bin"))

    # ---- composition ------------------------------------------------------
    comp = (adata.obs.groupby(["cell_type", "sample"], observed=True)
            .size().reset_index(name="n"))
    composition = {
        "cell_types": ct_names,
        "samples": smp_names,
        "counts": {
            ct: {s: int(comp[(comp.cell_type == ct) & (comp["sample"] == s)]["n"].sum())
                 for s in smp_names}
            for ct in ct_names
        },
        "totals": {ct: int((adata.obs.cell_type == ct).sum()) for ct in ct_names},
    }
    with open(os.path.join(dest, "composition.json"), "w") as fh:
        json.dump(composition, fh, separators=(",", ":"))

    # ---- markers ----------------------------------------------------------
    mpath = os.path.join(annotated_dir, f"{dataset_id}.markers.tsv")
    markers: dict[str, list[dict]] = {}
    panel: list[str] = []
    if os.path.exists(mpath):
        mk = pd.read_csv(mpath, sep="\t")
        mk = mk.sort_values(["cell_type", "score"], ascending=[True, False])
        for ct, sub in mk.groupby("cell_type", observed=True):
            top = sub.head(n_markers)
            markers[str(ct)] = [
                {
                    "gene": str(r["gene"]),
                    "log2fc": round(float(r.get("log2fc", 0) or 0), 3),
                    "padj": float(r.get("padj", 1) or 1),
                    "score": round(float(r.get("score", 0) or 0), 3),
                    "pct_in": round(float(r.get("pct_in", 0) or 0), 3)
                    if "pct_in" in r else None,
                }
                for _, r in top.iterrows()
            ]
            panel.extend(str(g) for g in top["gene"])
    with open(os.path.join(dest, "markers.json"), "w") as fh:
        json.dump(markers, fh, separators=(",", ":"))

    # ---- per-gene expression files ---------------------------------------
    panel = list(dict.fromkeys(panel))[:MAX_EXPORT_GENES]
    panel = [g for g in panel if g in expr.var_names]
    expr_dir = os.path.join(dest, "expression")
    gene_index = []
    print(f"  exporting expression for {len(panel)} marker genes")
    for i, g in enumerate(panel, 1):
        disk = safe_name(g) + ".bin.gz"
        p = os.path.join(expr_dir, disk)
        nnz = write_expression(expr, g, p)
        if nnz:
            gene_index.append({
                "gene": g,
                # `safe_name` escapes everything outside [A-Za-z0-9._-], so the
                # composite Hydra identifier `g10034.t1|CEL2A_PIG` is stored as
                # `g10034.t1%7CCEL2A_PIG.bin.gz`.  That string is a filename,
                # not a URL: `%` introduces an escape, so handing it to the
                # viewer unchanged did not round-trip -- the browser sent
                # `%7C`, Apache decoded it back to `|`, and asked for a file
                # that does not exist, so 684 of the atlas's 1017 expression
                # files served 404.  Quoting here means the URL decodes to the
                # name that is actually on disk, and the viewer's plain
                # `dataUrl + entry.file` concatenation keeps working.
                "file": "expression/" + url_quote(disk),
                "n_cells_expressing": nnz,
                "pct_expressing": round(100.0 * nnz / n, 2),
            })
        if i % 500 == 0:
            print(f"    {i}/{len(panel)}")
    with open(os.path.join(dest, "genes.json"), "w") as fh:
        json.dump({"genes": gene_index}, fh, separators=(",", ":"))

    # ---- manifest ---------------------------------------------------------
    # QC and annotation live in two separate records produced by two separate
    # stages; merge them so the manifest reflects what actually ran rather
    # than what the config asked for.
    qc_stats: dict = {}
    for p in (os.path.join(os.path.dirname(annotated_dir), "qc", f"{dataset_id}.qc_stats.json"),
              os.path.join(annotated_dir, f"{dataset_id}.annotate_stats.json")):
        if os.path.exists(p):
            with open(p) as fh:
                qc_stats.update(json.load(fh))

    # The annotation stage records how the labels were arrived at; trust its
    # record rather than re-deriving it here.  Deriving it from the label text
    # is what would mislabel a `cluster_only` dataset as `published`, because
    # "Cluster 7" does not start with "auto:".
    provenance = qc_stats.get("annotation_provenance") or (
        "de_novo" if any(c.startswith("auto:") for c in ct_names) else "published"
    )

    manifest = {
        "export_version": EXPORT_VERSION,
        "pipeline_version": PIPELINE_VERSION,
        "exported_utc": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "dataset_id": dataset_id,
        "species": str(adata.obs["species"].iloc[0]) if "species" in adata.obs else registry_species,
        "n_cells": int(n),
        "n_genes": int(adata.n_vars),
        "n_clusters": len(cl_names),
        "n_cell_types": len(ct_names),
        # promoted out of qc_summary: the viewer shows it as a headline tile
        "n_samples": int(qc_stats.get("n_samples") or len(smp_names)),
        "cell_types": ct_names,
        "clusters": cl_names,
        "samples": smp_names,
        "cellmeta_layout": ["cell_type_idx", "cluster_idx", "sample_idx"],
        "qc_layout": ["n_counts", "n_genes", "pct_mt"],
        "embedding": {"file": "embedding.bin", "dtype": "float32", "dims": 2},
        "annotation_provenance": provenance,
        # `cluster_only` means cell_types are Leiden cluster identities, so the
        # viewer must not offer a separate "Cell type" view that would imply a
        # naming the data does not support.
        "cell_type_mode": "cluster" if provenance == "cluster_only" else "named",
        "annotation_note": qc_stats.get("annotation_note", ""),
        "qc_summary": {
            k: qc_stats.get(k) for k in (
                "n_cells_raw", "n_cells_after_cell_qc", "n_cells_final",
                "n_doublets_removed", "doublet_rate", "doublet_method",
                "mito_genes_n", "mito_strategy", "mito_filtering_available",
                "cells_removed_by_criterion", "integration_method",
                "n_samples", "thresholds", "mito_distribution_raw",
                "mito_distribution_final", "platform_class",
                "bioproject", "sra_study", "geo_series", "tissue_organ", "stage",
                "pipeline_version", "processed_utc",
            ) if k in qc_stats
        },
        "source": {
            "bioproject": qc_stats.get("bioproject") or (registry or {}).get("bioproject", ""),
            "sra_study": qc_stats.get("sra_study") or (registry or {}).get("sra_study", ""),
            "geo_series": qc_stats.get("geo_series") or (registry or {}).get("geo_series", ""),
            "publication_pmid": qc_stats.get("pmid", ""),
            "n_cells_reported_by_source": (registry or {}).get("cells_reported", ""),
        },
        "class": qc_stats.get("class") or (registry or {}).get("class", ""),
    }
    with open(os.path.join(dest, "manifest.json"), "w") as fh:
        json.dump(manifest, fh, indent=1)

    size = sum(os.path.getsize(os.path.join(dp, f))
               for dp, _, fs in os.walk(dest) for f in fs)
    print(f"  -> {dest}  ({size/1e6:.1f} MB, {len(gene_index)} expression files, "
          f"{time.time()-t0:.1f}s)")
    return {"dataset_id": dataset_id, "dir": published_dir(dataset_id),
            "n_cells": int(n), "n_genes_exported": len(gene_index),
            "bytes": size}


def reindex(outdir: str) -> list[dict]:
    """Rebuild the index entries from an export tree already on disk.

    Every field is exactly what `export()` computes at the end of a run -- the
    cell count from `manifest.json`, the gene count from `genes.json`, the byte
    total from walking the directory -- so regenerating the index does not
    require re-exporting 8,686 files that have not changed.  That matters
    because a re-export rewrites everything (see the gzip note in DEPLOY.md),
    which is how an index-only fix would turn into a 96 MB diff.
    """
    out = []
    for name in sorted(os.listdir(outdir)):
        dest = os.path.join(outdir, name)
        if not os.path.isfile(os.path.join(dest, "manifest.json")):
            continue
        with open(os.path.join(dest, "manifest.json")) as fh:
            manifest = json.load(fh)
        genes_path = os.path.join(dest, "genes.json")
        n_genes = 0
        if os.path.isfile(genes_path):
            with open(genes_path) as fh:
                n_genes = len(json.load(fh).get("genes") or [])
        size = sum(os.path.getsize(os.path.join(dp, f))
                   for dp, _, fs in os.walk(dest) for f in fs)
        out.append({"dataset_id": manifest["dataset_id"],
                    "dir": published_dir(manifest["dataset_id"]),
                    "n_cells": int(manifest["n_cells"]),
                    "n_genes_exported": n_genes,
                    "bytes": size})
    return out


def write_index(outdir: str, entries: list[dict], replace: bool) -> int:
    """Write `_export_index.json`, merged with what is already there.

    Merged, not replaced, unless the caller knows better.  `--dataset X` used to
    write an index holding only X, so exporting one more dataset erased every
    other dataset's record from a file that ships with the module -- the index
    described the last run rather than the directory.  Entries for the datasets
    just exported are refreshed; the rest are carried over as they were.  A
    reindex has walked the directory itself, so it replaces.
    """
    path = os.path.join(outdir, "_export_index.json")
    merged = {}
    if not replace and os.path.exists(path):
        try:
            for e in json.load(open(path)):
                if isinstance(e, dict) and e.get("dataset_id"):
                    merged[e["dataset_id"]] = e
        except (ValueError, OSError):
            print(f"  ({path} unreadable; rebuilding it from this run)",
                  file=sys.stderr)
    for e in entries:
        merged[e["dataset_id"]] = e
    with open(path, "w") as fh:
        json.dump([merged[k] for k in sorted(merged)], fh, indent=2)
    return len(merged)


def main() -> int:
    here = Path(__file__).resolve().parent
    ap = argparse.ArgumentParser()
    ap.add_argument("--dataset")
    ap.add_argument("--all", action="store_true")
    ap.add_argument("--registry", default=str(here / ".." / "meta" / "dataset_registry.tsv"))
    ap.add_argument("--annotated-dir", default=str(here / ".." / "data" / "annotated"))
    ap.add_argument("--outdir", default=str(here / ".." / "web" / "singlecell_data"))
    ap.add_argument("--n-markers", type=int, default=N_MARKERS_PER_TYPE)
    ap.add_argument("--reindex", action="store_true",
                    help="rewrite _export_index.json from the export tree "
                         "already in --outdir; exports nothing")
    args = ap.parse_args()

    if args.reindex:
        entries = reindex(args.outdir)
        if not entries:
            print(f"no datasets found under {args.outdir}", file=sys.stderr)
            return 1
        n = write_index(args.outdir, entries, replace=True)
        print(f"reindexed {n} datasets from {args.outdir}")
        return 0

    reg = cnio.read_registry(args.registry)
    reg_by_id = {r["dataset_id"]: r.to_dict() for _, r in reg.iterrows()}
    if args.dataset:
        ids = [args.dataset]
    elif args.all:
        ids = list(reg["dataset_id"])
    else:
        ap.error("pass --dataset ID, --all, or --reindex")

    out = []
    failed: list[str] = []
    for did in ids:
        if not os.path.exists(os.path.join(args.annotated_dir, f"{did}.annotated.h5ad")):
            continue
        entry = reg_by_id.get(did, {})
        try:
            out.append(export(did, args.annotated_dir, args.outdir, args.n_markers,
                              registry_species=entry.get("species", ""),
                              registry=entry))
        except Exception as exc:  # noqa: BLE001
            print(f"[{did}] FAILED: {exc}", file=sys.stderr)
            failed.append(did)

    if out:
        n = write_index(args.outdir, out, replace=False)
        print(f"\nexported {len(out)} datasets ({n} in the index)")
    if failed:
        print(f"\n{len(failed)} dataset(s) FAILED: {', '.join(failed)}",
              file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
