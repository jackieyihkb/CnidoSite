#!/usr/bin/env python
"""Build the per-dataset marker panel for NVECT_embryo.

Why a per-dataset panel exists at all
-------------------------------------
`04_cluster_annotate.py` ships one global panel whose patterns are matched
against *gene symbols*, and NVECT_embryo's features are `NV2.<n>` gene model
IDs -- measured on the last run, 0 of 850 ranked features matched any of the 12
patterns, so the panel could not name a cluster even in principle.  Since the
deposit has no published labels either (see
`work/nvect_embryo_annotation_finding.md`), the dataset shipped as
`cluster_only`.

This panel is keyed by the IDs the deposit actually uses, so the same
machinery can see the markers.

Where the markers come from
---------------------------
Two published sources, both from the study itself:

1. `github.com/technau/NemVecEndoderm/nv2.func-04.04.23.tsv.gz` -- the lab's own
   functional annotation of the NV2 gene set, which supplies the NV2 -> symbol
   map.  The site's own tables have none: `NVECT_cellmarker.symbol` is `-` for
   all 10,794 NV2 genes it lists, which is why the mapping is taken from the
   study rather than from the site.
2. `ScDevTimeSeries.R` `manuMarkers` -- the 12 genes the paper plots, i.e. the
   identities it is willing to attach to this stage: ectodermal `apc`,
   mesodermal `tbx22-like`/`pitx1`/`snaila`, endodermal `foxa-1`/`brachyury`.

The symbol families are the canonical early-development ones (replication,
germ layers, neurogenic ectoderm, germline); each gene is listed below with the
symbol the lab's own table gives it, and the build asserts that the table says
exactly that, so a mistyped or mis-recalled ID fails here rather than becoming a
wrong label on a public page.

What this panel cannot do
-------------------------
It cannot turn this stage into named cell types.  At 8-12 hpf the germ layers
are still territories: measured against these markers, the study's own 7
clusters each hit 4-6 groups, and the paper says so itself ("at 8 hpf, only
clusters of mesodermal and ectodermal cells are clearly identifiable").  What
the panel can do is name the clusters a clear multi-gene signature does
identify -- the cycling population above all -- and `04` leaves the rest as
Leiden clusters.  That split is the honest result, not a shortfall of effort.

Usage:
    python work/build_embryo_panel.py
"""
import collections
import gzip
import json
import os

ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"
FUNC = os.path.join(ROOT, "work", "nvect_atlas", "nv2.func-04.04.23.tsv.gz")
H5AD = os.path.join(ROOT, "data", "qc", "NVECT_embryo.qc.h5ad")
OUTDIR = os.path.join(ROOT, "meta", "marker_panels")
PANEL = os.path.join(OUTDIR, "NVECT_embryo.tsv")
PROV = os.path.join(OUTDIR, "NVECT_embryo.provenance.json")

# label -> [(NV2 id, symbol the lab's table must give it), ...]
GROUPS: dict[str, list[tuple[str, str]]] = {
    "proliferating": [
        ("NV2.555", "mcm2-like"), ("NV2.6702", "mcm3-like"),
        ("NV2.5071", "mcm4-like"), ("NV2.20571", "mcm5a-like"),
        ("NV2.22694", "mcm7-like"), ("NV2.5344", "cdt1-like"),
        ("NV2.12141", "cdc6-like"), ("NV2.2169", "orc1-like"),
        ("NV2.4228", "tpx2-like"), ("NV2.25355", "aspm-like"),
        ("NV2.9785", "cdk1-like"), ("NV2.4162", "cyclin"),
        ("NV2.3357", "h2a-like-2"), ("NV2.241", "h4-like"),
        ("NV2.23522", "h1d-like"),
    ],
    "endoderm": [
        ("NV2.11441", "foxa-1"), ("NV2.11442", "foxa"),
        ("NV2.10624", "brachyury"), ("NV2.10922", "gsc"),
        ("NV2.2150", "gsc2-like"), ("NV2.22353", "mixl1-like"),
        ("NV2.8997", "mix23-like"),
    ],
    "mesoderm": [
        ("NV2.472", "snaila"), ("NV2.11657", "snailb"),
        ("NV2.10864", "twist"), ("NV2.15833", "tbx22-like"),
        ("NV2.16221", "tbx2"), ("NV2.3378", "pitx1"),
        ("NV2.23957", "mef2"),
    ],
    "ectoderm": [
        ("NV2.15303", "apc"), ("NV2.15306", "apc-like"),
        ("NV2.12726", "six3-6"), ("NV2.11140", "nkx2.2a"),
        ("NV2.18872", "emx3"), ("NV2.14640", "dlx1a"),
    ],
    "neural": [
        ("NV2.6608", "neurogenin1"), ("NV2.252", "elav1"),
        ("NV2.10441", "elav2"), ("NV2.4477", "soxb.2"),
        ("NV2.232", "foxq2c"), ("NV2.13108", "foxq2a"),
        ("NV2.2966", "pou4"), ("NV2.9665", "asha"),
        ("NV2.10382", "ashb"),
    ],
    "germline": [
        ("NV2.437", "nanos1"), ("NV2.17991", "nanos2"),
        ("NV2.16321", "piwi1"), ("NV2.15270", "piwi2"),
        ("NV2.8907", "piwi3"), ("NV2.574", "vasa2"),
        ("NV2.18156", "boule-like"),
    ],
}

PAPER_MARKERS = {
    "NV2.11441": "foxa-1", "NV2.10624": "brachyury", "NV2.472": "snaila",
    "NV2.15833": "tbx22-like", "NV2.15303": "apc", "NV2.2150": "gsc2-like",
    "NV2.234": "hd071-nk-like", "NV2.6608": "neurogenin1", "NV2.22508": "erg",
    "NV2.8483": "fgfa1", "NV2.9419": "ada1b-like-7", "NV2.10891": "",
    "NV2.25931": "cox1-like",
}


def main() -> int:
    sym = {}
    with gzip.open(FUNC, "rt") as fh:
        fh.readline()
        for ln in fh:
            f = ln.rstrip("\n").split("\t")
            if len(f) >= 3 and f[2].strip() not in ("", "-"):
                sym[f[0].strip()] = f[2].strip()

    # Every ID must be a feature of the matrix the panel will be matched
    # against, or the pattern can never fire and the label is decoration.
    import anndata as ad
    feats = set(map(str, ad.read_h5ad(H5AD, backed="r").var_names))

    # A marker the QC stage dropped cannot fire, so it is left out of the panel
    # rather than silently kept as a pattern that matches nothing.  The two ways
    # an ID can be missing are not the same thing and must not be conflated:
    # known to the lab's table but absent from the matrix means QC filtered it
    # (recorded, not fatal), whereas unknown to both means the ID is wrong
    # (fatal) -- which is the whole point of checking here instead of on the
    # website.
    seen: dict[str, str] = {}
    excluded: dict[str, str] = {}
    problems: list[str] = []
    kept: dict[str, list[tuple[str, str]]] = {}
    for label, genes in GROUPS.items():
        for gid, claimed in genes:
            got = sym.get(gid)
            if got is None:
                problems.append(f"{label}: {gid} is in no symbol table "
                                f"(not a gene of this study?)")
                continue
            if got.lower() != claimed.lower():
                problems.append(
                    f"{label}: {gid} is {got!r} in the lab table, not {claimed!r}")
                continue
            if gid in seen:
                problems.append(f"{gid} appears in both {seen[gid]} and {label}")
                continue
            seen[gid] = label
            if gid not in feats:
                excluded[f"{gid} ({got})"] = label
                continue
            kept.setdefault(label, []).append((gid, claimed))
    if problems:
        for p in problems:
            print("  ! " + p)
        raise SystemExit(f"{len(problems)} panel problem(s); nothing written")
    for name, label in sorted(excluded.items()):
        print(f"  - {name} dropped by QC; excluded from {label}")
    GROUPS.clear()
    GROUPS.update(kept)

    os.makedirs(OUTDIR, exist_ok=True)
    with open(PANEL, "w") as fh:
        fh.write("label\tpattern\tgenes\n")
        for label, genes in GROUPS.items():
            # Anchored alternation over the deposit's own IDs.  Exact matches
            # only: an ID is a whole token, and `NV2.5` is a prefix of
            # `NV2.555`, so a substring match here would name clusters off the
            # wrong gene.  compile_panel() strips the ^/$ and re-adds them as
            # lookarounds, so this survives that path unchanged.
            ids = [g for g, _ in genes]
            pattern = "^(" + "|".join(i.replace(".", r"\.") for i in ids) + ")$"
            note = ",".join(f"{s}={i}" for i, s in genes)
            fh.write(f"{label}\t{pattern}\t{note}\n")

    counts = collections.Counter(
        {lab: len(g) for lab, g in GROUPS.items()})
    n_panel = sum(counts.values())
    prov = {
        "dataset_id": "NVECT_embryo",
        "built_by": "work/build_embryo_panel.py",
        "purpose": ("name a cluster only where >=2 independent markers of one "
                    "group appear among its top-50 ranked features; 04 leaves "
                    "every other cluster as a Leiden cluster"),
        "symbol_source": "github.com/technau/NemVecEndoderm/"
                         "nv2.func-04.04.23.tsv.gz (the study's own NV2 "
                         "functional annotation; the site's tables hold no "
                         "NV2->symbol map)",
        "marker_source": ("ScDevTimeSeries.R `manuMarkers` (the 12 genes the "
                          "paper plots) plus the canonical early-development "
                          "families for this stage"),
        "paper": "Haillot et al. 2025, Nat Commun, "
                 "doi:10.1038/s41467-025-63287-4, PMID 40858588",
        "geo_series": "GSE302686; PRJNA1291744; SRP601047",
        "groups": dict(counts),
        "n_genes": n_panel,
        "excluded_by_qc": dict(sorted(excluded.items())),
        "n_paper_markers_in_panel": sum(1 for g in PAPER_MARKERS if g in seen),
        "validation": ("every ID asserted to carry exactly the claimed symbol "
                       "in the lab's table and to be a feature of "
                       "data/qc/NVECT_embryo.qc.h5ad; IDs the QC stage dropped "
                       "are recorded in excluded_by_qc rather than kept as "
                       "patterns that match nothing; no ID in two groups"),
        "limitation": ("this stage is germ-layer territories, not cell types: "
                       "the study's own 7 clusters each hit 4-6 groups and its "
                       "text says only mesodermal and ectodermal clusters are "
                       "clearly identifiable at 8 hpf"),
    }
    json.dump(prov, open(PROV, "w"), indent=2)

    print(f"wrote {PANEL}")
    for lab, n in counts.items():
        print(f"    {lab:<14} {n:2d} genes")
    print(f"  {len(seen)} genes total; "
          f"{prov['n_paper_markers_in_panel']} of the paper's 12 plotted "
          f"markers are in the panel")
    print(f"wrote {PROV}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
