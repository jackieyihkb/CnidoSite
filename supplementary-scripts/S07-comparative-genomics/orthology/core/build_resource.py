#!/usr/bin/env python3
"""Turn og_scores.tsv into the published CnidoSite core-ortholog resource.

Writes, into core/:

  core_og.tsv           one row per published core orthogroup: occupancy in
                        every species set, the tier flags it earns, and the
                        site's best functional annotation.
  matrix_copynumber.tsv species x core-orthogroup copy number (0,1,2,...)
  matrix_presence.tsv   species x core-orthogroup, 1/0
  matrix_singlecopy.tsv species x core-orthogroup, 1 where exactly one copy
  core_members.tsv      og, abbr1, gene -- the representative gene per cell

The matrices carry every one of the 153 inputs, outgroups included, because a
presence/absence matrix is most useful with an outgroup in it even though the
outgroups play no part in deciding what is "core".  `busco90` and
`cnidarian` are columns of species.tsv, not filters applied here.

Tiers are all scored on hq90 (the 60 cnidarian genomes with BUSCO cnidaria_odb12
complete >= 90%).  See README.md for why that set and those cutoffs.
"""
import os
import csv
import collections

WORK = "/mnt/sda/jackie/cnidaria/codex/orthology/work"
CORE = os.path.join(WORK, "core")

# Published tiers.  The cutoff is on the single-copy fraction within hq90;
# `occ` is implied by it (single-copy is a subset of present) and is reported
# anyway so the two can be read side by side.
TIERS = [
    ("strict", 0.90),
    ("core", 0.80),
    ("extended", 0.70),
]
SCORE_SET = "hq90"


def load_species():
    rows, hdr = [], None
    for i, line in enumerate(open(os.path.join(CORE, "species.tsv"))):
        f = line.rstrip("\n").split("\t")
        if i == 0:
            hdr = f
            continue
        rows.append(dict(zip(hdr, f)))
    return rows


def load_scores():
    out, hdr = {}, None
    for i, line in enumerate(open(os.path.join(CORE, "og_scores.tsv"))):
        f = line.rstrip("\n").split("\t")
        if i == 0:
            hdr = f
            continue
        out[f[0]] = dict(zip(hdr, f))
    return out


def load_anno():
    anno = {}
    for line in open(os.path.join(CORE, "og_family_anno.tsv")):
        f = line.rstrip("\n").split("\t")
        anno[f[0]] = dict(n_genes=f[1], n_species=f[2], source=f[3], term=f[4],
                          name=f[5], desc=f[6], cat=f[7], support=f[8],
                          pct=f[9], tier=f[10], n_terms=f[11])
    return anno


def main():
    sp = load_species()
    species = [r["abbr1"] or r["of_name"] for r in sp]
    scores = load_scores()
    anno = load_anno()

    core = {}
    for og, rec in scores.items():
        sc = float(rec["%s_sc" % SCORE_SET])
        earned = [t for t, cut in TIERS if sc >= cut]
        if earned:
            core[og] = (rec, earned)

    # Outgroup coverage is reported per orthogroup because the five outgroups
    # are the only way to root a tree built from this matrix, and they are
    # single-copy in only a subset of the core orthogroups (13/14 at the strict
    # tier, 149/224 at the core tier).  Without this column a user has no way
    # to know which subset is rootable.
    outgroups = [r["abbr1"] or r["of_name"] for r in sp if r["cnidarian"] != "1"]

    core_ogs = sorted(core)
    ogset = set(core_ogs)
    copies = collections.defaultdict(dict)     # og -> abbr1 -> n
    rep = {}                                   # (og, abbr1) -> gene
    for line in open(os.path.join(CORE, "copynumber_full.tsv")):
        og, a, n = line.rstrip("\n").split("\t")
        if og in ogset:
            copies[og][a] = int(n)
    for line in open(os.path.join(CORE, "og_genes.tsv")):
        og, a, base, gene, n_iso = line.rstrip("\n").split("\t")
        if og in ogset:
            rep[(og, a)] = gene

    print("core orthogroups: %d" % len(core))
    for t, cut in TIERS:
        n = sum(1 for _, e in core.values() if t in e)
        print("   %-9s single-copy in >=%d%% of %s : %d"
              % (t, cut * 100, SCORE_SET, n))

    # ---- core_og.tsv -----------------------------------------------------
    sets = ["hq95", "hq90", "hq85", "hq80", "all"]
    with open(os.path.join(CORE, "core_og.tsv"), "w", newline="") as fh:
        w = csv.writer(fh, delimiter="\t")
        w.writerow(["og"] + ["%s_%s" % (s, k) for s in sets
                             for k in ("n_species", "n_present", "n_single", "occ", "sc")]
                   + ["tiers", "og_n_members", "outgroup_present",
                      "outgroup_single", "best_source",
                      "best_term", "best_name", "best_desc", "best_cat",
                      "best_support", "best_tier", "n_terms"])
        for og in sorted(core):
            rec, earned = core[og]
            row = [og]
            for s in sets:
                row += [rec["%s_n_species" % s], rec["%s_n_present" % s],
                        rec["%s_n_single" % s], rec["%s_occ" % s],
                        rec["%s_sc" % s]]
            a = anno.get(og, {})
            row += [",".join(earned), a.get("n_genes", ""),
                    sum(1 for x in outgroups if copies[og].get(x, 0) >= 1),
                    sum(1 for x in outgroups if copies[og].get(x, 0) == 1)]
            row += [a.get("source", ""),
                    a.get("term", ""), a.get("name", ""), a.get("desc", ""),
                    a.get("cat", ""), a.get("support", ""), a.get("tier", ""),
                    a.get("n_terms", "")]
            w.writerow(row)

    # ---- members + matrices ---------------------------------------------
    with open(os.path.join(CORE, "core_members.tsv"), "w") as fh:
        fh.write("og\tabbr1\tgene\tn_copies\n")
        for og in core_ogs:
            for a in species:
                if a in copies[og]:
                    fh.write("%s\t%s\t%s\t%d\n"
                             % (og, a, rep.get((og, a), ""), copies[og][a]))

    def matrix(path, fn):
        with open(os.path.join(CORE, path), "w") as fh:
            fh.write("abbr1\tlatin\tphylum\tclass\tbusco90\t"
                     + "\t".join(core_ogs) + "\n")
            for r in sp:
                a = r["abbr1"] or r["of_name"]
                vals = [str(fn(copies[og].get(a, 0))) for og in core_ogs]
                fh.write("\t".join([a, r["latin"], r["phylum"], r["class"],
                                    r["busco90"]] + vals) + "\n")

    matrix("matrix_copynumber.tsv", lambda n: n)
    matrix("matrix_presence.tsv", lambda n: 1 if n else 0)
    matrix("matrix_singlecopy.tsv", lambda n: 1 if n == 1 else 0)
    print("wrote core_og.tsv, core_members.tsv and 3 matrices "
          "(%d species x %d orthogroups)" % (len(species), len(core_ogs)))
    print("tier sizes: " + ", ".join(
        "%s=%d" % (t, sum(1 for _, e in core.values() if t in e))
        for t, _ in TIERS))


if __name__ == "__main__":
    main()
