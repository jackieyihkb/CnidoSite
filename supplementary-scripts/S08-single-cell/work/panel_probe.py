#!/usr/bin/env python
"""Probe: can the cnidarian marker panel name clusters if we first map the
dataset's own gene model IDs to gene symbols?

The chain under test is the one the site itself uses for gene display:

    sc_id  --gene_ids.json-->  XP_ accession  --<ABBR>_uniprot-->  GN= symbol

gene_ids.json is the sidecar the site ships for display; sc_gene_ids.php says
explicitly that it only affects display and must never be used to rewrite data
keys, which is fine here because this script does not touch any data file -- it
only asks what the ranked genes would look like to a symbol-based panel.

Read-only: prints a report, writes nothing.
"""
import argparse
import collections
import csv
import json
import os
import re
import sys

ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"
sys.path.insert(0, os.path.join(ROOT, "pipeline"))
ann = import_module("04_cluster_annotate") if False else None  # noqa: E402
# 04 starts with a digit, so import it by path.
import importlib.util

spec = importlib.util.spec_from_file_location(
    "clu", os.path.join(ROOT, "pipeline", "04_cluster_annotate.py"))
clu = importlib.util.module_from_spec(spec)
spec.loader.exec_module(clu)

GN = re.compile(r"\bGN=([^\s;]+)")


def load_symbols(abbr):
    """XP_ accession -> symbol, voted across ortholog rows.

    One accession carries several ortholog descriptions (Histone H2B from
    Drosophila, from human, ...), so the symbol is a vote.  Ties go to the name
    with fewer capitals, matching includes/gene_name_map.php: that is closer to
    NCBI's own lower-case spelling and to what a user types into the box.
    """
    votes = collections.defaultdict(collections.Counter)
    p = os.path.join(ROOT, "work", f"{abbr}_uniprot.tsv")
    with open(p) as fh:
        rd = csv.reader(fh, delimiter="\t")
        next(rd, None)
        for row in rd:
            if len(row) < 2:
                continue
            for m in GN.findall(row[1]):
                votes[row[0]][m] += 1
    out = {}
    for acc, c in votes.items():
        out[acc] = sorted(c.items(), key=lambda kv: (-kv[1], sum(map(str.isupper, kv[0]))))[0][0]
    return out


def load_nr(abbr):
    """XP_ -> the descriptive part of the NCBI-NR hit, for a lenient fallback."""
    out = {}
    p = os.path.join(ROOT, "work", f"{abbr}_nr.tsv")
    with open(p) as fh:
        rd = csv.reader(fh, delimiter="\t")
        next(rd, None)
        for row in rd:
            if len(row) < 2:
                continue
            d = row[1].split(" [")[0].strip()
            if d and not d.lower().startswith("predicted protein"):
                out.setdefault(row[0], d)
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dataset", required=True)
    ap.add_argument("--abbr", required=True)
    ap.add_argument("--markers", default="")
    a = ap.parse_args()

    side = json.load(open(f"{ROOT}/work/gene_ids/{a.dataset}.gene_ids.json"))
    gmap = side.get("map") or {}
    sym = load_symbols(a.abbr)
    nr = load_nr(a.abbr)
    print(f"sidecar {a.dataset}: {len(gmap)} sc_id -> accession, "
          f"generated {side.get('generated')}")
    print(f"{a.abbr}_uniprot: {len(sym)} accessions carry a GN= symbol")

    # sc_id -> symbol (strict) and -> lenient name
    strict, lenient = {}, {}
    for sc, v in gmap.items():
        acc = v[0] if isinstance(v, list) else v
        if acc in sym:
            strict[sc] = sym[acc]
        if acc in sym:
            lenient[sc] = sym[acc]
        elif acc in nr:
            lenient[sc] = nr[acc]
    print(f"  -> {len(strict)} sc_id get a symbol; {len(lenient)} get one leniently")

    mp = a.markers or f"{ROOT}/data/annotated/{a.dataset}.markers.tsv"
    rows = list(csv.DictReader(open(mp), delimiter="\t"))
    gcol = "gene" if "gene" in rows[0] else "names"
    ccol = "cell_type" if "cell_type" in rows[0] else "cluster"
    per = collections.defaultdict(list)
    for r in rows:
        per[r[ccol]].append(r[gcol])

    panel = clu.compile_panel()
    print(f"\n{'cluster':<12} {'ranked':>6} {'sym':>5} {'len':>5}  strict / lenient best label (hits)")
    tot_s = tot_l = 0
    for c in sorted(per, key=lambda x: (len(x), x)):
        genes = per[c]
        gs = [strict[g] for g in genes if g in strict]
        gl = [lenient[g] for g in genes if g in lenient]

        def best(names):
            b, hits = "unassigned", []
            for lab, rx in panel.items():
                h = [n for n in names if rx.search(n)]
                if len(h) > len(hits):
                    b, hits = lab, h
            return (b, hits) if len(hits) >= clu.MIN_MARKER_HITS else ("unassigned", hits)

        bs, hs = best(gs)
        bl, hl = best(gl)
        tot_s += bs != "unassigned"
        tot_l += bl != "unassigned"
        print(f"{c:<12} {len(genes):>6} {len(gs):>5} {len(gl):>5}  "
              f"{bs:<14}({len(hs)}) {bl:<14}({len(hl)})  {','.join(hs[:3])[:44]}")
    n = len(per)
    print(f"\nnamed: strict {tot_s}/{n}, lenient {tot_l}/{n}")


if __name__ == "__main__":
    main()
