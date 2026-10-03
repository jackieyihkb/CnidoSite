#!/usr/bin/env python
"""Build the published cell -> cell-type table for HVULG_siebert_atlas.

Where the labels come from
--------------------------
GSE121617 (Siebert et al. 2019, Science, "Stem cell differentiation
trajectories in Hydra resolved at single-cell resolution") ships counts only --
`data/raw/GSE121617/` holds one file.  The paper's cell-type assignments live in
the Broad Single Cell Portal study SCP260, whose *cluster files* are public even
though the study and file endpoints now require a bearer token:

    /api/v1/studies/SCP260/clusters                      -> six cluster files
    /api/v1/studies/SCP260/clusters/Whole%20Genome%20Clustering

"Whole Genome Clustering" -- described there as "tSNE plot of SNN clustered
single cell data mapped to the Hydra genome 2.0 reference" -- is the one that
matches this dataset, which was built from
`GSE121617_Hydra_DS_genome_UMICounts.txt.gz`.  Its 41 labels are exactly the 41
of `current.cluster.ids <- as.character(0:40)` in the authors' own
`SA06_ClustGenome.Rmd`, so the two agree rather than merely overlap.  The
transcriptome cluster file is a different partition (42 labels, e.g. it splits
`i_smgc` into `i_smgc1`/`i_smgc2`) and is NOT used here.

The one transformation: SCP prefixes the genome-mapped cells with `G` so they
cannot collide with the transcriptome-mapped ones (`G01-D1_GCGCCCCATGAA`); the
deposited matrix names them without it (`01-D1_...`).  Stripping that prefix is
what makes the join work, and it is checked: with the prefix intact 0 of 25,438
cells match, without it 23,775 do, and 6 of the 1,663 that do not are cell IDs
the authors list by hand as excluded doublets in `SA06_ClustGenome.Rmd`.  Labels
are copied verbatim -- no renaming, no merging.

Usage:
    python work/build_hydra_celltypes.py
"""
import collections
import gzip
import io
import json
import os
import re

ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"
SCP = os.path.join(ROOT, "work", "scp", "SCP260_genome.json")
SA06 = os.path.join(ROOT, "work", "scp", "SA06_ClustGenome.Rmd")
OUTDIR = os.path.join(ROOT, "data", "raw", "GSE121617_prepared")
H5AD = os.path.join(ROOT, "data", "qc", "HVULG_siebert_atlas.qc.h5ad")


def authors_vectors():
    """The two parallel 41-entry name vectors from SA06, short and long.

    The file holds FOUR naming schemes over the same 41 clusters, and the first
    two are not interchangeable: the preprint scheme (Fig. S24) numbers neurons
    `i_nc1..i_nc8`, while everything from the "consistent neuron labels" comment
    onward uses `i_n_ec1..5` / `i_n_en1..3`.  SCP260 publishes the latter.  So
    each vector is selected by the comment that introduces it -- selecting by
    "which one matches SCP" would make the check below circular.
    """
    src = open(SA06).read()
    hits = list(re.finditer(r"cluster\.names <- c\((.*?)\)\n", src, re.S))
    # Each vector's own comment sits just above it, but "# update names in
    # Seurat object" and the plotting calls sit between that comment and the
    # vector, so the context is everything since the previous vector.
    labeled = []
    prev_end = 0
    for m in hits:
        labeled.append((src[prev_end:m.start()],
                        re.findall(r'"([^"]*)"', m.group(1))))
        prev_end = m.end()

    def pick(*needles):
        for context, vec in labeled:
            if all(n.lower() in context.lower() for n in needles):
                return vec
        raise SystemExit(f"no vector matching {needles}")

    short = pick("short labels")
    long_ = pick("long labels for Broad portal")
    assert len(short) == len(long_) == 41, (len(short), len(long_))
    return short, long_


def main():
    d = json.load(open(SCP))
    data = d["data"]
    scp = {}
    for c, lab in zip(data["cells"], data["annotations"]):
        key = c[1:] if c[:1] == "G" else c
        scp[key] = lab

    short, long_ = authors_vectors()
    got = sorted(set(scp.values()))
    if got != sorted(short):
        raise SystemExit("SCP labels are not the authors' 41-label vector:\n"
                         f"  only in SCP: {sorted(set(got) - set(short))}\n"
                         f"  only in SA06: {sorted(set(short) - set(got))}")

    os.makedirs(OUTDIR, exist_ok=True)
    table = os.path.join(OUTDIR, "GSE121617_Hydra.cell_to_cts.csv.gz")
    # mtime=0: the same zero stamp the exporter uses, so re-running this does
    # not rewrite identical data with different bytes (DEPLOY.md, defect 8).
    with open(table, "wb") as raw:
        with gzip.GzipFile(fileobj=raw, mode="wb", mtime=0) as gz:
            with io.TextIOWrapper(gz, encoding="utf-8") as fh:
                fh.write("cellID\tcell_type\n")
                for cell in sorted(scp):
                    fh.write(f"{cell}\t{scp[cell]}\n")

    # Coverage against the cells we actually kept, so the match rate the site
    # reports is known here first.
    import anndata as ad
    ours = list(ad.read_h5ad(H5AD, backed="r").obs_names)
    hit = sum(1 for c in ours if c in scp)
    print(f"wrote {table}")
    print(f"  {len(scp)} labelled cells, {len(set(scp.values()))} labels")
    print(f"  our cells: {len(ours)}; labelled {hit} ({hit / len(ours):.1%}); "
          f"unlabelled {len(ours) - hit}")

    # short -> long, for the record only: the site ships the verbatim short
    # labels, this file is how a reader decodes them.
    prov = os.path.join(OUTDIR, "GSE121617_Hydra.cell_to_cts.provenance.json")
    json.dump({
        "source": "Broad Single Cell Portal study SCP260, cluster file "
                  "'Whole Genome Clustering'",
        "cluster_file_id": d.get("clusterFileId"),
        "description": d.get("description"),
        "url": "https://singlecell.broadinstitute.org/single_cell/api/v1/"
               "studies/SCP260/clusters/Whole%20Genome%20Clustering",
        "paper": "Siebert et al. 2019, Science 365(6451), "
                 "doi:10.1126/science.aav9314; GSE121617",
        "n_cells": len(scp),
        "n_labels": len(set(scp.values())),
        "transform": "leading 'G' stripped from SCP cell names to match the "
                     "deposited matrix (G01-D1_X -> 01-D1_X); labels verbatim",
        "labels_verified_against": "SA06_ClustGenome.Rmd, "
                                   "current.cluster.ids <- as.character(0:40)",
        "long_names_for_reference": {s: l for s, l in zip(short, long_)},
    }, open(prov, "w"), indent=2)
    print(f"wrote {prov}")


if __name__ == "__main__":
    main()
