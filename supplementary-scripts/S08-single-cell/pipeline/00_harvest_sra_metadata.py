#!/usr/bin/env python3
"""
00_harvest_sra_metadata.py
==========================
Harvest authoritative per-dataset metadata from NCBI SRA / GEO for every
single-cell dataset listed on CnidoSite (https://cnidosite.org/sn_data.php).

This directly answers Referee 2 (Major #3), which asks:

    "Third, please clarify whether all 33 datasets were generated using
     10x-compatible libraries. The authors should provide dataset-level QC
     statistics, including numbers of cells before and after filtering,
     library type, mitochondrial distributions, doublet-removal procedures
     and any batch/integration procedures used."

The library type / platform / construction protocol below come straight from
the submitters' SRA records, so they are citable and reproducible rather than
re-typed from the papers.

Outputs
-------
meta/sra_metadata.json   full structured record per dataset
meta/sra_metadata.tsv    flat table for the manuscript supplement

Usage
-----
    python 00_harvest_sra_metadata.py [--outdir ../meta]
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import time
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from collections import Counter

EUTILS = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/"

# ---------------------------------------------------------------------------
# The 33 datasets exactly as listed on https://cnidosite.org/sn_data.php
# (class, species, BioProject, SRA study, tissue/organ, stage, reported cells)
# ---------------------------------------------------------------------------
DATASETS = [
    # class, species, bioproject, study, tissue_organ, stage, reported_cells
    ("Hexacorallia", "Acropora millepora", "PRJNA1223412", "SRP563749", "Whole adults", "Adult tissues/organs", "28736"),
    ("Hexacorallia", "Acropora muricata", "PRJNA544778", "SRP199550", "Polyps", "regeneration", "17277"),
    ("Hexacorallia", "Montipora capricornis", "PRJNA544778", "SRP199550", "Polyps", "Adult tissues/organs", "11243"),
    ("Hexacorallia", "Montipora foliosa", "PRJNA544778", "SRP199550", "Polyps", "Adult tissues/organs", "19369"),
    ("Hexacorallia", "Nematostella vectensis", "PRJNA1063743", "SRP483247", "2 month animal", "Adult tissues/organs", "2032"),
    ("Hexacorallia", "Nematostella vectensis", "PRJNA1217498", "SRP560807", "body wall with mesenteries", "6-8 weeks adults", ""),
    ("Hexacorallia", "Nematostella vectensis", "PRJNA1249376", "SRP577940", "whole-adult", "Adult tissues/organs", "60000"),
    ("Hexacorallia", "Nematostella vectensis", "PRJNA1291744", "SRP601047", "embryo", "Developmental stages", ""),
    ("Hexacorallia", "Nematostella vectensis", "PRJNA1327231", "SRP619054", "aggregates", "Embryos treated", ""),
    ("Hexacorallia", "Nematostella vectensis", "PRJNA1331670", "SRP622332", "neoplasm", "Adult tissues/organs", ""),
    ("Hexacorallia", "Nematostella vectensis", "PRJNA1331670", "SRP622332", "tentacle", "Adult tissues/organs", ""),
    ("Hexacorallia", "Nematostella vectensis", "PRJNA645134", "SRP271116", "Bodywall, Mesentery, Pharynx, Tentacle", "Whole organism", "2032"),
    ("Hexacorallia", "Nematostella vectensis", "PRJNA823660", "SRP367642", "Blastula-gastrula intermediate", "Developmental stages", "55042"),
    ("Hexacorallia", "Nematostella vectensis", "PRJNA903823", "SRP408911", "Gastrula Cells", "nervous system", ""),
    ("Hexacorallia", "Oculina arbuscula", "PRJNA1122932", "SRP513328", "whole organism", "Symbiotic state", "6951"),
    ("Hexacorallia", "Oculina patagonica", "PRJNA1223412", "SRP563749", "Whole adults", "Adult tissues/organs", "29723"),
    ("Hexacorallia", "Orbicella faveolata", "PRJNA1180584", "SRP542438", "coral mucus and tissue", "bleaching stages", "12719"),
    ("Hexacorallia", "Pocillopora verrucosa", "PRJNA544778", "SRP199550", "Polyps", "Adult tissues/organs", "11493"),
    ("Hexacorallia", "Stylophora pistillata", "PRJNA1223412", "SRP563749", "Whole adults", "Adult tissues/organs", "15053"),
    ("Hydrozoa", "Clytia hemisphaerica", "PRJNA1220061", "SRP562097", "planula larva", "Adult tissues/organs", ""),
    ("Hydrozoa", "Hydra vulgaris", "PRJNA614611", "SRP253820", "Whole polyp", "Adult tissues/organs", "1152"),
    ("Hydrozoa", "Hydra vulgaris", "PRJNA614614", "SRP253822", "Whole polyp", "Adult tissues/organs", "1152"),
    ("Hydrozoa", "Hydractinia symbiolongicarpus", "PRJNA1124116", "SRP514108", "Whole body", "Adult tissues/organs", "199113"),
    ("Hydrozoa", "Hydractinia symbiolongicarpus", "PRJNA1263849", "SRP587019", "whole colony", "Adult tissues/organs", "47000"),
    ("Hydrozoa", "Hydractinia symbiolongicarpus", "PRJNA807936", "SRP360329", "whole colony", "Adult tissues/organs", ""),
    ("Hydrozoa", "Turritopsis rubra", "PRJNA1045549", "SRP474384", "whole animal", "medusa", "22245"),
    ("Scyphozoa", "Aurelia coerulea", "PRJNA1011858", "SRP458012", "gastrodermis", "Developmental stages", "10000"),
    ("Scyphozoa", "Aurelia coerulea", "PRJNA1014937", "SRP468849", "whole organism", "Developmental stages", ""),
    ("Scyphozoa", "Aurelia coerulea", "PRJNA1041967", "SRP472811", "ephrya", "oceanacid treatment", "42461"),
    ("Scyphozoa", "Aurelia coerulea", "PRJNA1042913", "SRP473863", "podocysts", "podocyst-forming polyps", "17978"),
    ("Scyphozoa", "Aurelia coerulea", "PRJNA1045549", "SRP474384", "whole animal", "medusa", "18936"),
    ("Scyphozoa", "Aurelia coerulea", "PRJNA1287281", "SRP598586", "Rhopalium", "Adult tissues/organs", ""),
    ("Scyphozoa", "Aurelia coerulea", "PRJNA884642", "SRP400888", "Whole adults", "Developmental stages", "45286"),
]


# ---------------------------------------------------------------------------
# NCBI helpers
# ---------------------------------------------------------------------------
def _fetch(url: str, tries: int = 4, pause: float = 3.0) -> str:
    last = None
    for attempt in range(tries):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "CnidoSite-QC/1.0"})
            with urllib.request.urlopen(req, timeout=60) as fh:
                return fh.read().decode("utf-8", "replace")
        except Exception as exc:  # noqa: BLE001 - network flake, retry
            last = exc
            time.sleep(pause * (attempt + 1))
    print(f"  ! fetch failed: {last}", file=sys.stderr)
    return ""


def esearch(db: str, term: str, retmax: int = 100) -> list[str]:
    url = (
        f"{EUTILS}esearch.fcgi?db={db}&term={urllib.parse.quote(term)}"
        f"&retmode=json&retmax={retmax}"
    )
    raw = _fetch(url)
    if not raw:
        return []
    try:
        return json.loads(raw)["esearchresult"].get("idlist", [])
    except Exception:  # noqa: BLE001
        return []


def efetch_sra(ids: list[str]) -> str:
    if not ids:
        return ""
    return _fetch(f"{EUTILS}efetch.fcgi?db=sra&id={','.join(ids)}&rettype=full&retmode=xml")


# ---------------------------------------------------------------------------
# Parsing
# ---------------------------------------------------------------------------
def _text(node, path: str) -> str:
    found = node.find(path) if node is not None else None
    return (found.text or "").strip() if found is not None and found.text else ""


def parse_experiment_packages(xml: str) -> list[dict]:
    """Pull the submitter-declared platform / library fields out of SRA XML."""
    if not xml.strip():
        return []
    try:
        root = ET.fromstring(xml)
    except ET.ParseError:
        return []

    records = []
    for pkg in root.iter("EXPERIMENT_PACKAGE"):
        exp = pkg.find("EXPERIMENT")
        study = pkg.find("STUDY")
        sample = pkg.find("SAMPLE")

        lib = exp.find("DESIGN/LIBRARY_DESCRIPTOR") if exp is not None else None
        platform_node = exp.find("PLATFORM") if exp is not None else None
        instrument = ""
        platform_name = ""
        if platform_node is not None:
            for child in platform_node:
                platform_name = child.tag
                instrument = _text(child, "INSTRUMENT_MODEL") or instrument

        rec = {
            "experiment": exp.get("accession", "") if exp is not None else "",
            "title": _text(exp, "TITLE"),
            "library_strategy": _text(lib, "LIBRARY_STRATEGY"),
            "library_source": _text(lib, "LIBRARY_SOURCE"),
            "library_selection": _text(lib, "LIBRARY_SELECTION"),
            "library_layout": (
                "PAIRED" if lib is not None and lib.find("LIBRARY_LAYOUT/PAIRED") is not None
                else "SINGLE" if lib is not None and lib.find("LIBRARY_LAYOUT/SINGLE") is not None
                else ""
            ),
            "library_construction_protocol": _text(lib, "LIBRARY_CONSTRUCTION_PROTOCOL"),
            "platform": platform_name,
            "instrument": instrument,
            "study_title": _text(study, "DESCRIPTOR/STUDY_TITLE"),
            "study_abstract": _text(study, "DESCRIPTOR/STUDY_ABSTRACT"),
            "center_name": study.get("center_name", "") if study is not None else "",
        }
        if sample is not None:
            rec["sample_taxon"] = _text(sample, "SAMPLE_NAME/SCIENTIFIC_NAME")
        records.append(rec)
    return records


# Library construction protocols are free text, so normalise them into a
# controlled vocabulary.  These regexes are deliberately conservative: an
# unrecognised protocol is reported as "unclassified" rather than guessed,
# because the manuscript claims a specific 10x/Cell Ranger pipeline.
PLATFORM_PATTERNS = [
    (r"10x|10X|chromium", "10x Genomics Chromium"),
    (r"clicktag|click tag|cell hashing", "10x Genomics Chromium + ClickTag hashing"),
    (r"drop-?seq", "Drop-seq"),
    (r"smart-?seq", "Smart-seq2"),
    (r"cel-?seq", "CEL-Seq2"),
    (r"microwell|bd rhapsody|rhapsody", "BD Rhapsody"),
    (r"split-?seq", "SPLiT-seq"),
    (r"singleron|gc-?seq", "Singleron GEXSCOPE"),
    (r"in-?drop", "inDrop"),
    (r"sci-?rna", "sci-RNA-seq"),
]


def classify_platform(protocol: str, instrument: str, study_title: str = "",
                      selections: str = "", ) -> tuple[str, str]:
    """Return (platform_class, confidence).

    Many submitters leave LIBRARY_CONSTRUCTION_PROTOCOL empty, so the protocol
    alone under-reports.  We fall back to the study title and to the library
    selection vocabulary, and we return a confidence so the manuscript table
    can distinguish "stated by submitter" from "inferred".

    Returns 'unknown' with confidence 'low' rather than guessing: the point of
    this table is to correct a manuscript claim, so a wrong label is worse than
    an explicit gap.
    """
    hay = f"{protocol} {instrument} {study_title} {selections}"
    hits = [label for pat, label in PLATFORM_PATTERNS if re.search(pat, hay, re.I)]
    if hits:
        conf = "high" if re.search(r"10x|10X|chromium|clicktag|split-?seq|drop-?seq|smart-?seq",
                                   protocol, re.I) else "medium"
        return "; ".join(dict.fromkeys(hits)), conf
    return "unknown", "low"


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--outdir", default=os.path.join(os.path.dirname(__file__), "..", "meta"))
    args = ap.parse_args()
    outdir = os.path.abspath(args.outdir)
    os.makedirs(outdir, exist_ok=True)

    # Collapse to unique SRA studies so we hit NCBI once per study, not per row.
    studies = {}
    for _, _, bioproj, study, _, _, _ in DATASETS:
        studies.setdefault(study, bioproj)

    per_study = {}
    for study, bioproj in sorted(studies.items()):
        print(f"[{study}] {bioproj}", flush=True)
        ids = esearch("sra", study, retmax=200)
        if not ids:
            # Some studies are only reachable through the BioProject record.
            ids = esearch("sra", bioproj, retmax=200)
        records = parse_experiment_packages(efetch_sra(ids))
        print(f"   {len(ids)} runs -> {len(records)} parsed", flush=True)

        instruments = Counter(r["instrument"] for r in records if r["instrument"])
        protocols = Counter(
            r["library_construction_protocol"] for r in records if r["library_construction_protocol"]
        )
        layouts = Counter(r["library_layout"] for r in records if r["library_layout"])
        sources = Counter(r["library_source"] for r in records if r["library_source"])
        selections = Counter(r["library_selection"] for r in records if r["library_selection"])

        proto_txt = " | ".join(protocols) if protocols else ""
        study_title = next((r["study_title"] for r in records if r["study_title"]), "")
        platform_class, platform_conf = classify_platform(
            proto_txt, " ".join(instruments), study_title, " ".join(selections)
        )
        per_study[study] = {
            "bioproject": bioproj,
            "n_runs": len(ids),
            "n_experiments": len(records),
            "instruments": dict(instruments),
            "library_layout": dict(layouts),
            "library_source": dict(sources),
            "library_selection": dict(selections),
            "construction_protocols": list(protocols),
            "platform_class": platform_class,
            "platform_confidence": platform_conf,
            "n_single_cell_runs": sum(
                n for k, n in sources.items() if "SINGLE CELL" in k.upper()
            ),
            "study_title": study_title,
            "study_abstract": next((r["study_abstract"] for r in records if r["study_abstract"]), ""),
            "center_name": next((r["center_name"] for r in records if r["center_name"]), ""),
            "taxa": sorted({r.get("sample_taxon", "") for r in records if r.get("sample_taxon")}),
        }
        time.sleep(0.5)

    # ---- explode back to the 33 CnidoSite rows -----------------------------
    rows = []
    for i, (cls, sp, bioproj, study, tissue, stage, cells) in enumerate(DATASETS, 1):
        s = per_study.get(study, {})
        rows.append(
            {
                "cnidosite_row": i,
                "class": cls,
                "species": sp,
                "bioproject": bioproj,
                "sra_study": study,
                "tissue_organ": tissue,
                "stage": stage,
                "cells_reported_website": cells,
                **{k: s.get(k, "") for k in (
                    "n_runs", "n_experiments", "n_single_cell_runs",
                    "platform_class", "platform_confidence", "instruments",
                    "library_layout", "library_source", "library_selection",
                    "construction_protocols", "study_title", "center_name", "taxa",
                )},
            }
        )

    with open(os.path.join(outdir, "sra_metadata.json"), "w") as fh:
        json.dump({"datasets": rows, "per_study": per_study}, fh, indent=2, default=str)

    # flat TSV
    cols = ["cnidosite_row", "class", "species", "bioproject", "sra_study", "tissue_organ",
            "stage", "cells_reported_website", "n_runs", "n_single_cell_runs",
            "platform_class", "platform_confidence", "instruments",
            "library_layout", "library_source", "library_selection"]
    with open(os.path.join(outdir, "sra_metadata.tsv"), "w") as fh:
        fh.write("\t".join(cols) + "\n")
        for r in rows:
            out = []
            for c in cols:
                v = r.get(c, "")
                if isinstance(v, dict):
                    v = ";".join(f"{k}x{n}" for k, n in v.items())
                elif isinstance(v, list):
                    v = "; ".join(map(str, v))
                out.append(str(v).replace("\t", " ").replace("\n", " "))
            fh.write("\t".join(out) + "\n")

    print(f"\nWrote {outdir}/sra_metadata.json and .tsv  ({len(rows)} dataset rows)")

    # ---- quick platform summary -------------------------------------------
    print("\n=== platform_class summary ===")
    for label, n in Counter(r["platform_class"] for r in rows).most_common():
        print(f"  {n:3d}  {label or '(none)'}")
    print("\n=== confidence summary ===")
    for label, n in Counter(r["platform_confidence"] for r in rows).most_common():
        print(f"  {n:3d}  {label}")
    print("\n=== datasets whose platform is NOT a 10x-family chemistry ===")
    for r in rows:
        pc = str(r.get("platform_class", ""))
        if pc and "10x" not in pc and "Chromium" not in pc:
            print(f"  {r['sra_study']}  {r['species'][:30]:32s} {pc}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
