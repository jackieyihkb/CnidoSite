#!/usr/bin/env python3
"""Verify a loaded batch against the staged inputs it was loaded from.

Runs on the server, next to the staged batch:

    python3 12_verify_load.py dbload_ext

Why this exists: the six tables join on `protein`, so a load can succeed, report
a plausible row count and still be invisible on the page because the ids do not
match mag_annot.  Nothing in the loaders treats that as an error.  This
recomputes each MAG's per-table protein coverage straight from the staged files
-- the same files the loader read, but parsed here independently -- and compares
it against what is actually in MySQL, then checks every annotation row has a
mag_annot row behind it.

A row-count comparison alone would not catch much: mag_interpro holds one row per
InterPro entry per protein, so COUNT(*) is a number nobody can predict.  The
comparison is on COUNT(DISTINCT protein), which is set by the input and is
therefore checkable.
"""
import os
import subprocess
import sys
from collections import defaultdict

MYSQL = ["mysql", "-ujackie", "-p<REDACTED>", "cnidaria", "-N", "-B"]

TABLES = ["mag_annot", "mag_interpro", "mag_go_terms",
          "mag_kegg_terms", "mag_pfam_hits", "mag_panther_hits"]


def q(sql):
    r = subprocess.run(MYSQL + ["-e", sql], capture_output=True, text=True)
    if r.returncode != 0:
        raise RuntimeError(f"mysql failed: {r.stderr.strip()}")
    return [ln.split("\t") for ln in r.stdout.strip().split("\n") if ln]


def count_fasta(path):
    n = 0
    with open(path) as fh:
        for line in fh:
            if line.startswith(">"):
                n += 1
    return n


def parse_iprscan(path):
    """Re-derive per-source protein sets from the staged TSV.

    Columns the loader uses: 0 protein, 3 analysis, 4 signature acc,
    11 InterPro acc, 12 InterPro desc, 13 GO.  (Column 14, pathway, was dropped
    at staging and is not read by the loader either.)
    """
    sources = {"pfam": set(), "panther": set(), "interpro": set(), "go": set()}
    with open(path) as fh:
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) < 14 or not f[0]:
                continue
            pid = f[0]
            analysis, sig, ipr, go = f[3], f[4], f[11], f[13]
            if analysis == "Pfam" and sig != "-":
                sources["pfam"].add(pid)
            elif analysis == "PANTHER" and sig != "-":
                sources["panther"].add(pid)
            if ipr not in ("-", ""):
                sources["interpro"].add(pid)
            if go not in ("-", ""):
                sources["go"].add(pid)
    return sources


def parse_kofam(path):
    s = set()
    with open(path) as fh:
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) >= 2 and f[1].startswith("K"):
                s.add(f[0])
    return s


def main():
    stage = sys.argv[1] if len(sys.argv) > 1 else "dbload_ext"
    stage = os.path.abspath(stage)
    accs = sorted(f[:-4] for f in os.listdir(stage) if f.endswith(".faa"))
    if not accs:
        # A batch with no FASTAs (the cytherea one) has no mag_annot to verify;
        # fall back to the kofam inputs so the script is still usable there.
        accs = sorted(f[:-len(".kofam.tsv")] for f in os.listdir(stage)
                      if f.endswith(".kofam.tsv"))
    print(f"[verify] {len(accs)} MAGs in {os.path.basename(stage)}")

    # One query per table for the whole batch, not per MAG: 186 MAGs x 6 tables
    # as separate round trips is minutes of mysql startup for no benefit.
    in_list = ",".join(f"'{a}'" for a in accs)
    db = {}
    for t in TABLES:
        for mag, n in q(f"SELECT mag, COUNT(DISTINCT protein) FROM {t} "
                        f"WHERE mag IN ({in_list}) GROUP BY mag"):
            db.setdefault(t, {})[mag] = int(n)

    bad = defaultdict(list)
    n_checked = 0
    for acc in accs:
        faa = os.path.join(stage, f"{acc}.faa")
        tsv = os.path.join(stage, f"{acc}.iprscan.tsv")
        ko = os.path.join(stage, f"{acc}.kofam.tsv")

        expect = {}
        if os.path.exists(faa):
            expect["mag_annot"] = count_fasta(faa)
        if os.path.exists(tsv):
            src = parse_iprscan(tsv)
            expect["mag_interpro"] = len(src["interpro"])
            expect["mag_pfam_hits"] = len(src["pfam"])
            expect["mag_panther_hits"] = len(src["panther"])
            expect["mag_go_terms"] = len(src["go"])
        if os.path.exists(ko):
            expect["mag_kegg_terms"] = len(parse_kofam(ko))

        for t in TABLES:
            if t not in expect:
                continue
            n_checked += 1
            got = db.get(t, {}).get(acc, 0)
            if got != expect[t]:
                bad[t].append((acc, expect[t], got))

    for t in TABLES:
        n_mag = len(db.get(t, {}))
        tot = sum(db.get(t, {}).values())
        print(f"[verify] {t:<18} MAGs present {n_mag:>4}   "
              f"proteins covered {tot:>8,}")
    print(f"[verify] {n_checked} per-MAG comparisons")

    if bad:
        print("\n[verify] MISMATCHES")
        for t, rows in bad.items():
            print(f"  {t}: {len(rows)} MAGs")
            for acc, e, g in rows[:10]:
                print(f"    {acc}  staged={e}  db={g}")
        return 1

    # Orphan check: an annotation row whose protein is not in mag_annot is
    # invisible, because the page iterates mag_annot and looks the rest up.
    for t in TABLES[1:]:
        rows = q(f"SELECT COUNT(*) FROM {t} x "
                 f"WHERE x.mag IN ({in_list}) AND NOT EXISTS "
                 f"(SELECT 1 FROM mag_annot a WHERE a.mag=x.mag AND a.protein=x.protein)")
        n = int(rows[0][0]) if rows else 0
        print(f"[verify] {t:<18} orphan rows {n}")
        if n:
            print(f"[verify] FAIL: {t} has {n} rows with no mag_annot row")
            return 1

    print("[verify] OK: all six tables agree with the staged inputs")
    return 0


if __name__ == "__main__":
    sys.exit(main())
