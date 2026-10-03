#!/usr/bin/env python3
"""Cross-check the CCO resource against the BUSCO analysis the site already runs.

Two questions, both of which the reviewers will ask:

1. How does a tiered, author-defined core compare with the curated
   cnidaria_odb12 lineage?  BUSCO also reports a single-copy-per-species count,
   so the two can be put on the same axis.  The answer is that BUSCO's own set
   is not universally single-copy either: on the 60 high-quality genomes not one
   of its 3,203 groups is single-copy in all 60, and only 125 are in >=90%.

2. Do the CCO orthogroups recover the BUSCO genes?  If the resource is sound,
   a large share of BUSCO complete single-copy hits should fall inside CCO
   orthogroups, and CCO membership should be enriched for them relative to
   orthogroups at large.

Inputs:  busco_hq90_counts.tsv  (per BUSCO group: Complete/Duplicated/Fragmented
                                 counts over the hq90 species, pulled from the
                                 site's `busco` x `busco_summary` tables)
         busco_genes.tsv        (abbr, gene, BUSCO_ID, Status -- one row per hit)
         species.tsv, copynumber_full.tsv, core_og.tsv
"""
import os
import csv
import collections

CORE = os.path.dirname(os.path.abspath(__file__))
HQ = "hq90"


def load_species():
    rows, hdr = [], None
    for i, line in enumerate(open(os.path.join(CORE, "species.tsv"))):
        f = line.rstrip("\n").split("\t")
        if i == 0:
            hdr = f
            continue
        rows.append(dict(zip(hdr, f)))
    return rows


def main():
    sp = load_species()
    hq = {r["abbr1"] for r in sp if r["busco90"] == "1"}
    allcnid = {r["abbr1"] for r in sp if r["cnidarian"] == "1"}

    # ---- 1. BUSCO's own single-copy occupancy over the same species --------
    bus = []
    for line in open(os.path.join(CORE, "busco_hq90_counts.tsv")):
        f = line.rstrip("\n").split("\t")
        if len(f) != 5:
            continue
        bus.append((f[0], int(f[1]), int(f[2]), int(f[3]), int(f[4])))
    n = len(hq)
    print("BUSCO cnidaria_odb12 over the %d high-quality genomes" % n)
    for cut in (1.0, 0.95, 0.90, 0.85, 0.80):
        print("   single-copy in >=%3.0f%%: %4d of %d groups"
              % (cut * 100, sum(1 for r in bus if r[1] >= cut * n), len(bus)))

    # ---- 2. how much of BUSCO the CCO orthogroups absorb -------------------
    core = {}
    for d in csv.DictReader(open(os.path.join(CORE, "core_og.tsv")), delimiter="\t"):
        core[d["og"]] = d["tiers"].split(",")

    # BUSCO hit -> the (abbr, gene) it was called on
    busco_gene = {}
    for line in open(os.path.join(CORE, "busco_genes.tsv")):
        a, gene, bid, status = line.rstrip("\n").split("\t")
        busco_gene[(a, gene)] = (bid, status)

    # member gene -> orthogroups, restricted to core orthogroups
    og_of = collections.defaultdict(set)
    member_genes = collections.Counter()      # abbr -> members in core OGs
    for line in open(os.path.join(CORE, "core_members.tsv")):
        og, a, gene, ncop = line.rstrip("\n").split("\t")
        if a not in allcnid:
            continue
        og_of[(a, gene)].add(og)
        member_genes[a] += 1

    in_core = collections.Counter()           # abbr -> core members that are BUSCO hits
    busco_in_core = collections.defaultdict(set)
    for key, ogset in og_of.items():
        hit = busco_gene.get(key)
        if hit:
            in_core[key[0]] += 1
            busco_in_core[hit[0]].add(key)

    print("\nBUSCO genes recovered inside core orthogroups")
    tot_hits = sum(1 for (a, g) in busco_gene if a in allcnid)
    tot_in = sum(in_core.values())
    print("   BUSCO hits on cnidarian genes:        %6d" % tot_hits)
    print("   of those, inside a core orthogroup:   %6d  (%.1f%%)"
          % (tot_in, 100.0 * tot_in / tot_hits if tot_hits else 0))
    print("   core-orthogroup members that are BUSCO hits: %6d of %6d (%.1f%%)"
          % (tot_in, sum(member_genes.values()),
             100.0 * tot_in / sum(member_genes.values())))

    placed = set(busco_in_core)
    print("\n   how many BUSCO groups the CCO resource reaches, by BUSCO's own"
          " stringency:")
    for cut in (1.0, 0.95, 0.9, 0.85, 0.8):
        tier = {r[0] for r in bus if r[1] >= cut * n}
        print("      single-copy in >=%3.0f%% of hq90: %4d BUSCOs, %4d (%.0f%%)"
              " have a member in a CCO orthogroup"
              % (cut * 100, len(tier), len(tier & placed),
                 100.0 * len(tier & placed) / len(tier) if tier else 0))


if __name__ == "__main__":
    main()
