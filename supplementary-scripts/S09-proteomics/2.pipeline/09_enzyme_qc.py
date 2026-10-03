#!/usr/bin/env python3
"""
09_enzyme_qc.py
---------------
Check that each dataset's identified peptides actually look like they were cut
with the enzyme we say we used.

Why this exists
~~~~~~~~~~~~~~~
Search parameters describe the *deposited* file, but a file's name is not
evidence about its contents.  We were caught by this: a locally staged copy of
PXD017813 was searched with trypsin specificity and returned 146 confident
peptides -- trypsin autolysis products and keratins -- while the deposition it
was standing in for is a Glu-C digest.  Those peptides were real identifications
of the wrong thing.

Enzyme specificity leaves a fingerprint in the results.  A tryptic digest gives
peptides ending in K or R (unless the next residue is P); a Glu-C digest gives
peptides ending in D or E; Lys-C gives K.  If the declared enzyme is right, the
matching residues dominate the C-termini.  If a file is not what it claims, they
do not, and no amount of parameter bookkeeping would reveal it.

This is a warning, not a gate: the threshold is deliberately loose (see
MIN_FRACTION) because semi-specific and non-specific searches, and peptides
ending at a proline-blocked site, legitimately dilute the signal.  Its job is to
catch a gross mismatch, which is exactly the failure mode that is otherwise
invisible.

Usage:
    python3 09_enzyme_qc.py                 # every dataset in 4.results/
    python3 09_enzyme_qc.py PXD017813 ...   # named datasets
Exit status is 1 if any dataset fails, so it can be wired into a release check.
"""
from __future__ import annotations

import argparse
import csv
import sys
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RESULTS = ROOT / "4.results"
META = ROOT / "1.metadata"

# Comet's [COMET_ENZYME_INFO] numbering, restricted to the enzymes the catalogue
# actually uses, mapped to the C-terminal residues the enzyme should produce.
# Comet's enzyme table gives the residues cleavage occurs *after*.
ENZYME_CTERM = {
    "1": ("Trypsin", set("KR")),
    "2": ("Trypsin/P", set("KR")),
    "3": ("Lys_C", set("K")),
    "5": ("Arg_C", set("R")),
    "8": ("Glu_C", set("DE")),
    "10": ("Chymotrypsin", set("FWYL")),
}

# Below this share of peptides ending on a residue the enzyme should produce, the
# file is not what the parameters claim.  0.5 is far below what a genuine digest
# gives (trypsin on PXD017813: 0.99) and far above what a mismatch gives
# (Glu-C-specificity results carry no signal from a tryptic file, and vice
# versa), so it separates the two cases without flagging legitimate
# semi-specific searches.
MIN_FRACTION = 0.50


def read_tsv(p: Path):
    if not p.exists():
        return []
    with p.open() as fh:
        lines = [l for l in fh if not l.startswith("#") and l.strip()]
    return list(csv.DictReader(lines, delimiter="\t"))


def cterm(peptide: str) -> str:
    """Last residue of `X.PEPTIDE.Y`, ignoring modifications and the flanks."""
    p = (peptide or "").strip()
    if "." in p:
        parts = p.split(".")
        if len(parts) >= 2:
            p = parts[-2]
    p = "".join(c for c in p if c.isalpha() and c.isupper())
    return p[-1] if p else ""


def check(pxd: str, params: dict) -> tuple[bool, str]:
    peps = read_tsv(RESULTS / pxd / "peptides.tsv")
    if not peps:
        return True, "no peptides to check"

    p = params.get(pxd, {})
    num = (p.get("cnido_enzyme") or "").strip()
    name, expected = ENZYME_CTERM.get(num, (None, None))
    if not expected:
        return True, f"enzyme {num!r} not covered by this check"

    counts = Counter(cterm(r.get("peptide", "")) for r in peps)
    total = sum(counts.values())
    if not total:
        return True, "no peptides to check"
    frac = sum(n for r, n in counts.items() if r in expected) / total

    top = ", ".join(f"{r}:{n}" for r, n in counts.most_common(4))
    detail = f"{name} predicts {''.join(sorted(expected))} at the C-terminus; {frac:.0%} do ({top})"
    return frac >= MIN_FRACTION, detail


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("pxds", nargs="*")
    a = ap.parse_args()

    params = {r["pxd"]: r for r in read_tsv(META / "search_params.tsv") if r.get("pxd")}
    pxds = a.pxds or sorted(d.name for d in RESULTS.glob("PXD*") if d.is_dir())

    bad = 0
    for pxd in pxds:
        ok, detail = check(pxd, params)
        print(f"  {'ok  ' if ok else 'FAIL'}  {pxd:12} {detail}")
        bad += 0 if ok else 1

    print()
    print(f"{len(pxds) - bad} ok, {bad} failed" if pxds else "nothing to check")
    return 1 if bad else 0


if __name__ == "__main__":
    raise SystemExit(main())
