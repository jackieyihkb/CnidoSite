#!/usr/bin/env python3
"""Score every orthogroup against every candidate species set.

Reads copynumber_full.tsv (see build_copynumber.py) and emits og_scores.tsv
with, per orthogroup and per species set:

  n_species    size of the set
  n_present    species with >=1 gene
  n_single     species with exactly 1 gene
  n_multi      species with >1 gene
  occ          n_present / n_species
  sc           n_single / n_species

`sc` is the number that matters for a core single-copy resource: it is the
fraction of the set where the orthogroup is usable in a concatenated
phylogeny without any copy-number decision.  `occ` is what matters for
presence/absence.  Note sc >= x implies occ >= x, so the single-copy
threshold alone bounds both.

Run with `--ladder` to print the threshold table used to pick the published
tiers.
"""
import os
import sys
import collections

WORK = "/mnt/sda/jackie/cnidaria/codex/orthology/work"
CORE = os.path.join(WORK, "core")

# Species sets.  The BUSCO-completeness ones are nested by construction; the
# point of scoring several is that the choice of genome-quality bar is a
# scientific decision the reader should be able to second-guess.
SETS = [
    ("hq95", lambda r: r["cnidarian"] == "1" and r["pct_complete"]
     and float(r["pct_complete"]) >= 95),
    ("hq90", lambda r: r["cnidarian"] == "1" and r["pct_complete"]
     and float(r["pct_complete"]) >= 90),
    ("hq85", lambda r: r["cnidarian"] == "1" and r["pct_complete"]
     and float(r["pct_complete"]) >= 85),
    ("hq80", lambda r: r["cnidarian"] == "1" and r["pct_complete"]
     and float(r["pct_complete"]) >= 80),
    ("all", lambda r: r["cnidarian"] == "1"),
]


def load_species():
    rows, hdr = [], None
    for i, line in enumerate(open(os.path.join(CORE, "species.tsv"))):
        f = line.rstrip("\n").split("\t")
        if i == 0:
            hdr = f
            continue
        rows.append(dict(zip(hdr, f)))
    return rows


def load_copynumber():
    cn = collections.defaultdict(dict)
    for i, line in enumerate(open(os.path.join(CORE, "copynumber_full.tsv"))):
        if i == 0:
            continue
        og, abbr, n = line.rstrip("\n").split("\t")
        cn[og][abbr] = int(n)
    return cn


def main():
    sp = load_species()
    sets = {name: [r["abbr1"] for r in sp if pred(r)] for name, pred in SETS}
    print("species sets: " + ", ".join("%s=%d" % (k, len(v)) for k, v in sets.items()))
    for name, members in sets.items():
        cls = collections.Counter(
            r["class"] for r in sp if r["abbr1"] in set(members))
        print("   %-6s %s" % (name, dict(cls)))

    cn = load_copynumber()
    print("\northogroups with members: %d" % len(cn))

    scores = {}
    for og, d in cn.items():
        rec = {}
        for name, members in sets.items():
            n = len(members)
            present = single = multi = 0
            for a in members:
                c = d.get(a, 0)
                if c == 1:
                    single += 1
                    present += 1
                elif c > 1:
                    multi += 1
                    present += 1
            rec[name] = (n, present, single, multi)
        scores[og] = rec

    with open(os.path.join(CORE, "og_scores.tsv"), "w") as fh:
        cols = ["og"]
        for name, _ in SETS:
            cols += ["%s_%s" % (name, k)
                     for k in ("n_species", "n_present", "n_single", "n_multi", "occ", "sc")]
        fh.write("\t".join(cols) + "\n")
        for og in sorted(scores):
            row = [og]
            for name, _ in SETS:
                n, p, s, m = scores[og][name]
                row += [n, p, s, m, "%.4f" % (p / n), "%.4f" % (s / n)]
            fh.write("\t".join(str(x) for x in row) + "\n")
    print("wrote og_scores.tsv")

    if "--ladder" in sys.argv:
        for name, _ in SETS:
            n = len(sets[name])
            if n == 0:
                continue
            print("\n%s  (%d species) -- orthogroups single-copy in >=" % (name, n))
            for pct in (95, 90, 85, 80, 75, 70, 65, 60, 50):
                k = sum(1 for rec in scores.values()
                        if rec[name][2] >= pct / 100 * n)
                print("    %3d%% of species: %6d" % (pct, k))


if __name__ == "__main__":
    main()
