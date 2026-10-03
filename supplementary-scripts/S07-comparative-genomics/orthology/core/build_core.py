#!/usr/bin/env python3
"""Derive the CnidoSite core ortholog resource from the OrthoFinder result.

The reviewer's ask is a Cnidaria-specific core single-copy ortholog set plus a
species-by-gene presence/absence matrix -- the same job BUSCO does, but with a
lineage the authors actually have sampling for, and with the matrix exposed
rather than summarised into one number.

The definition here is deliberately tiered rather than a single cutoff, because
no single cutoff is right for every downstream use:

  occupancy  = fraction of a species set in which the orthogroup has >=1 gene
  single-copy = fraction of a species set in which it has exactly 1 gene

Two species sets are scored, both restricted to Cnidaria:

  hq90  BUSCO cnidaria_odb12 complete >= 90%   (60 species)
  hq80  BUSCO cnidaria_odb12 complete >= 80%  (103 species, every class present)

and the whole 148-species cnidarian set is scored as `all` for reference.

Copy number is counted as *distinct gene IDs* per (orthogroup, species).  That
matters: Alatina_alata and Calvadosia_cruxmelitensis have proteomes whose FASTA
headers repeat, so one gene ID lands in several orthogroups.  Counting sequences
instead of genes would both inflate the copy number of those species and let a
duplicated annotation be counted many times.
"""
import os
import sys
import collections

WORK = "/mnt/sda/jackie/cnidaria/codex/orthology/work"
CORE = os.path.join(WORK, "core")

# (tier name, min occupancy, min single-copy fraction) -- see header.
TIERS = [
    ("core95", 0.95, 0.95),
    ("core90", 0.90, 0.90),
    ("core80", 0.80, 0.80),
    ("present90", 0.90, 0.0),   # broad core: conserved even where duplicated
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


def main():
    sp = load_species()
    of_to_abbr = {}
    for r in sp:
        of_to_abbr[r["of_name"]] = r["abbr1"] or r["of_name"]
    sets = {
        "all":   [r["abbr1"] for r in sp if r["cnidarian"] == "1"],
        "hq90":  [r["abbr1"] for r in sp if r["busco90"] == "1"],
        "hq80":  [r["abbr1"] for r in sp if r["cnidarian"] == "1"
                  and r["pct_complete"] and float(r["pct_complete"]) >= 80],
    }
    print("species sets: " + ", ".join(
        "%s=%d" % (k, len(v)) for k, v in sets.items()))

    # ---- pass 1: distinct copy number per (og, species) --------------------
    counts = collections.defaultdict(dict)   # og -> {abbr1: n_genes}
    seen = set()
    n_rows = n_dup = n_unknown = 0
    for i, line in enumerate(open(os.path.join(WORK, "og_members_v2.tsv"))):
        if i == 0:
            continue
        og, of_name, abbr, gene = line.rstrip("\n").split("\t")
        n_rows += 1
        key = (og, abbr, gene)
        if key in seen:
            n_dup += 1
            continue
        seen.add(key)
        a = of_to_abbr.get(of_name)
        if a is None:
            n_unknown += 1
            continue
        d = counts[og]
        d[a] = d.get(a, 0) + 1
    del seen
    print("members: %d rows, %d exact duplicates dropped, %d unknown species"
          % (n_rows, n_dup, n_unknown))
    print("orthogroups with members: %d" % len(counts))

    # ---- pass 2: score every orthogroup against every species set ----------
    scored = {}
    for og, d in counts.items():
        rec = {}
        for name, members in sets.items():
            n = len(members)
            present = sum(1 for a in members if d.get(a, 0) >= 1)
            single = sum(1 for a in members if d.get(a, 0) == 1)
            multi = sum(1 for a in members if d.get(a, 0) > 1)
            rec[name] = dict(n_species=n, n_present=present, n_single=single,
                             n_multi=multi,
                             occ=present / n, sc=single / n)
        scored[og] = rec

    # a core orthogroup is judged on hq90; hq80 is reported alongside so users
    # can see what relaxing the genome-quality bar would buy them
    core = {}
    for og, rec in scored.items():
        tiers = [t for t, o, s in TIERS
                 if rec["hq90"]["occ"] >= o and rec["hq90"]["sc"] >= s]
        if tiers:
            core[og] = (rec, tiers)

    print("\ncore orthogroups (scored on hq90, %d species):" % len(sets["hq90"]))
    for t, o, s in TIERS:
        print("   %-10s occ>=%.0f%% sc>=%.0f%% : %d OGs"
              % (t, o * 100, s * 100,
                 sum(1 for _, tl in core.values() if t in tl)))
    strict = [og for og, (_, tl) in core.items() if "core90" in tl]
    print("   union (core90 or better): %d OGs" % len(strict))

    # ---- write ------------------------------------------------------------
    with open(os.path.join(CORE, "og_scores.tsv"), "w") as fh:
        fh.write("\t".join(["og"] + [
            "%s_%s" % (s, k)
            for s in ("all", "hq90", "hq80")
            for k in ("n_species", "n_present", "n_single", "n_multi", "occ", "sc")
        ]) + "\tcore_tiers\n")
        for og, rec in scored.items():
            row = [og]
            for s in ("all", "hq90", "hq80"):
                r = rec[s]
                row += [r["n_species"], r["n_present"], r["n_single"],
                        r["n_multi"], "%.4f" % r["occ"], "%.4f" % r["sc"]]
            row.append(",".join(core[og][1]) if og in core else "")
            fh.write("\t".join(str(x) for x in row) + "\n")
    print("\nwrote og_scores.tsv (%d orthogroups)" % len(scored))

    return core, counts


if __name__ == "__main__":
    main()
