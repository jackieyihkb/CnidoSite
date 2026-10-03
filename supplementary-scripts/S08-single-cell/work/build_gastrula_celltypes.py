#!/usr/bin/env python
"""Build the published cell -> cell-type table for NVECT_gastrula.

Where the labels come from
--------------------------
GSE200198 (Steger et al. 2022, Cell Reports 40(12):111370, "Single-cell
transcriptomics identifies conserved regulators of neuroglandular lineages";
Cole et al. 2024, Front Zool 21:8, the re-mapped atlas) ships counts only.  Its
GEO supplement is exactly five files -- a GTF, a RAW tar, and the alldata
cells/genes/matrix triple -- and `GSE200198_alldata.cells.csv.gz` is a
headerless single column of barcodes.  No cluster table, no barcode->type table,
nothing in SCP (SCP holds no Nematostella study at all) and nothing in Zenodo
but R scripts.

The study's own per-cell annotation is published in its UCSC Cell Browser:

    https://sea-anemone-atlas.cells.ucsc.edu/sea-anemone-atlas/all/meta.tsv

whose desc.json describes it as "Full dataset containing 55,042 cells from sea
anemone Nematostella vectensis after quality control filtering", submitted by
Alison G. Cole and Julia Steger (Technau lab, University of Vienna).  Its `Cell`
column is checked below against the deposited barcode list -- both membership and
order -- because that check is what makes this a join to *these* cells rather
than a same-study approximation.

Two columns carry the label (`IDs` and `Cluster`); they are asserted equal, so
there is one naming scheme here and not the two that Hydra's SA06 turned out to
hold.  Labels are copied verbatim -- no renaming, no merging, no prettifying:
`NPC` and `retractor muscle` stay as the authors wrote them.

Usage:
    python work/build_gastrula_celltypes.py
"""
import collections
import csv
import gzip
import io
import json
import os

ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"
META = os.path.join(ROOT, "work", "nvect_atlas", "sea_anemone_all.meta.tsv")
DEPOSIT = os.path.join(ROOT, "data", "raw", "GSE200198",
                       "GSE200198_alldata.cells.csv.gz")
OUTDIR = os.path.join(ROOT, "data", "raw", "GSE200198_prepared")
H5AD = os.path.join(ROOT, "data", "qc", "NVECT_gastrula.qc.h5ad")
URL = ("https://sea-anemone-atlas.cells.ucsc.edu/sea-anemone-atlas/"
       "all/meta.tsv")


def main():
    rows = list(csv.DictReader(open(META), delimiter="\t"))
    order = [r["Cell"].strip() for r in rows]
    ids = {r["Cell"].strip(): (r["IDs"] or "").strip() for r in rows}
    clus = {r["Cell"].strip(): (r["Cluster"] or "").strip() for r in rows}

    # The label columns must agree, or there are two naming schemes in play and
    # picking one would be a choice nobody published.
    diff = [c for c in ids if ids[c] != clus[c]]
    if diff:
        raise SystemExit(
            f"IDs and Cluster disagree for {len(diff)} cells, e.g. "
            f"{diff[0]}: IDs={ids[diff[0]]!r} Cluster={clus[diff[0]]!r}")

    # Aligned to the deposit: same cells, same order.
    with gzip.open(DEPOSIT, "rt") as fh:
        deposit = [ln.strip() for ln in fh if ln.strip()]
    if order != deposit:
        same_set = set(order) == set(deposit)
        raise SystemExit(
            f"atlas cells are not the deposited barcodes: {len(order)} vs "
            f"{len(deposit)}; same set but different order: {same_set}; "
            f"only in deposit: {len(set(deposit) - set(order))}")

    blank = [c for c in ids if not ids[c]]
    if blank:
        raise SystemExit(f"{len(blank)} cells carry no label, e.g. {blank[0]}")
    dirty = [c for c in ids if ids[c] != (rows[0]["IDs"] or "").strip()
             and ids[c] != ids[c].strip()]
    if dirty:
        raise SystemExit(f"{len(dirty)} labels had surrounding whitespace")

    os.makedirs(OUTDIR, exist_ok=True)
    table = os.path.join(OUTDIR, "GSE200198.cell_to_cts.csv.gz")
    # mtime=0: the same zero stamp the exporter uses, so re-running this does not
    # rewrite identical data with different bytes (DEPLOY.md, defect 8).
    with open(table, "wb") as raw:
        with gzip.GzipFile(fileobj=raw, mode="wb", mtime=0) as gz:
            with io.TextIOWrapper(gz, encoding="utf-8") as fh:
                fh.write("cellID\tcell_type\n")
                for cell in sorted(ids):
                    fh.write(f"{cell}\t{ids[cell]}\n")

    counts = collections.Counter(ids.values())
    paper = ("Steger et al. 2022, Cell Reports 40(12):111370, "
             "doi:10.1016/j.celrep.2022.111370, PMID 36130520; re-mapped in "
             "Cole et al. 2024, Front Zool 21:8, doi:10.1186/s12983-024-00529-z")
    prov = {
        "source": "UCSC Cell Browser, sea-anemone-atlas, dataset 'all' "
                  "(Steger/Cole, Technau lab, University of Vienna)",
        "url": URL,
        "paper": paper,
        "geo_series": "GSE200198; PRJNA823660; SRP367642",
        "n_cells": len(ids),
        "n_labels": len(counts),
        "labels": dict(sorted(counts.items())),
        "column_used": "IDs (asserted identical to Cluster)",
        "alignment_check": "Cell column equals GSE200198_alldata.cells.csv.gz "
                           "both as a set and in list order",
        "transform": "none -- labels verbatim; surrounding whitespace stripped",
    }
    provpath = os.path.join(OUTDIR, "GSE200198.cell_to_cts.provenance.json")
    json.dump(prov, open(provpath, "w"), indent=2)

    print(f"wrote {table}")
    print(f"  {len(ids)} labelled cells, {len(counts)} labels, "
          f"aligned to the deposit in order")
    for lab, n in counts.most_common():
        print(f"    {lab:<28} {n:6d}")
    print(f"wrote {provpath}")

    if os.path.exists(H5AD):
        import anndata as ad
        ours = list(ad.read_h5ad(H5AD, backed="r").obs["cell_id"].astype(str))
        hit = sum(1 for c in ours if c in ids)
        print(f"  our qc cells: {len(ours)}; labelled {hit} "
              f"({hit / len(ours):.1%}); unlabelled {len(ours) - hit}")
    else:
        print(f"  (no {H5AD} yet -- coverage check deferred to 03)")


if __name__ == "__main__":
    main()
