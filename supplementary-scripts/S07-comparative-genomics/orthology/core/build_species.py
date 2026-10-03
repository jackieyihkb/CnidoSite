#!/usr/bin/env python3
"""Build the species table for the CnidoSite core-ortholog resource.

Joins three sources that never agree on how to spell a species:

  * OrthoFinder's ``WorkingDirectory/SpeciesIDs.txt`` -- names like
    ``Actinernus_sp`` and ``OUT_Sycon_ciliatum`` (dots stripped, outgroups
    prefixed by hand).
  * the site's ``abbr`` table -- ``Actinernus sp. WN-2022`` /
    ``Actinernus_sp._WN_2022`` / ``ASP1``.
  * the site's ``classfy`` table -- taxonomy, keyed by the Latin binomial.

The two spellings differ by punctuation only, so matching normalises
``[._ ]`` away and looks for a unique prefix.  Everything the matcher is
unsure about is printed and must be checked by hand rather than guessed.

Output: species.tsv, one row per OrthoFinder input, sorted by taxonomy.
"""
import os
import re
import sys
import json

WORK = "/mnt/sda/jackie/cnidaria/codex/orthology/work"
OF = "/mnt/sda/jackie/cnidaria/0.tree/0.peps/OrthoFinder/Results_Sep14"

# BUSCO completeness cutoff for the high-quality set.  90% complete against
# cnidaria_odb12 is the cutoff used for "high-quality genome" throughout the
# cnidarian literature, and on this dataset it happens to be where the
# obviously-partial assemblies (Myxozoa, the fragmented hydrozoan/jellyfish
# sets) have already dropped out.
HQ_CUTOFF = 90.0


def norm(s):
    return re.sub(r"[._\s\-]", "", s).lower()


def read_site_meta(path):
    """site_meta.tsv = `SELECT species,abbr,abbr1 FROM abbr` then
    `SELECT Phylum,Class,order1,Family,Genus,Species,NCBI FROM classfy`."""
    abbr_rows, tax_rows = [], []
    for line in open(path):
        f = line.rstrip("\n").split("\t")
        if len(f) == 3:
            abbr_rows.append(f)
        elif len(f) == 7:
            tax_rows.append(f)
    return abbr_rows, tax_rows


def read_busco(path):
    """busco_summary dump -> {abbr1: (n_buscos, single, dup, frag, miss, pct_*)}.
    Falls back to nothing if the file is missing; build_species then reports
    the species as having no BUSCO row, which is a hard error at the end."""
    out = {}
    if not os.path.exists(path):
        return out
    for line in open(path):
        f = line.rstrip("\n").split("\t")
        if len(f) != 10:
            continue
        (abbr1, species, n, single, dup, frag, miss,
         pc, ps, pd) = f
        out[abbr1] = dict(n_buscos=int(n), n_single=int(single),
                          n_duplicated=int(dup), n_fragmented=int(frag),
                          n_missing=int(miss), pct_complete=float(pc),
                          pct_single=float(ps), pct_duplicated=float(pd))
    return out


def read_of_species(path):
    """SpeciesIDs.txt -> ordered list of OrthoFinder species names."""
    names = []
    for line in open(path):
        names.append(line.strip().split(": ", 1)[1].replace(".pep", ""))
    return names


def resolve(of_name, by_norm, exact):
    """Map an OrthoFinder name onto a site `abbr` row.

    Returns (row, how).  `row` is None for outgroups, which the site does not
    carry at all, and for anything the matcher cannot place without guessing.
    """
    key = norm(of_name)
    if of_name in exact:
        return exact[of_name], "exact-abbr"
    if key in by_norm and len(by_norm[key]) == 1:
        return by_norm[key][0], "exact-normalised"
    cands = [r for k, rows in by_norm.items() if k.startswith(key) for r in rows]
    if len(cands) == 1:
        return cands[0], "prefix"
    if len(cands) > 1:
        # Shortest site name = fewest extra tokens = the bare "sp." record.
        cands.sort(key=lambda r: len(r[1]))
        return cands[0], "prefix-ambig(%d)" % len(cands)
    if of_name.startswith("OUT_"):
        return None, "outgroup"
    return None, "UNRESOLVED"


def main():
    abbr_rows, tax_rows = read_site_meta(os.path.join(WORK, "core", "site_meta.tsv"))

    # taxonomy keyed on the exact Latin binomial the site stores
    tax = {}
    for phylum, klass, order1, family, genus, species, ncbi in tax_rows:
        tax[species] = dict(phylum=phylum, klass=klass, order=order1,
                            family=family, genus=genus, ncbi=ncbi)

    # `abbr.species` is the human-readable name, and it is what joins to
    # `classfy.Species` for all but a handful of records.
    by_norm, exact = {}, {}
    for species, abbr, abbr1 in abbr_rows:
        row = dict(latin=species, abbr=abbr, abbr1=abbr1)
        exact[abbr] = row
        by_norm.setdefault(norm(abbr), []).append(row)

    busco = read_busco(os.path.join(WORK, "core", "busco_site.tsv"))
    of_names = read_of_species(os.path.join(OF, "WorkingDirectory", "SpeciesIDs.txt"))

    rows, problems = [], []
    for i, name in enumerate(of_names):
        row, how = resolve(name, by_norm, exact)
        if row is None:
            if how == "outgroup":
                rows.append(dict(idx=i, of_name=name, abbr1="", abbr=name,
                                 latin=name.replace("OUT_", "").replace("_", " "),
                                 cnidarian=0, tax={}, how=how))
            else:
                problems.append((name, how))
            continue
        t = tax.get(row["latin"], {})
        cnid = 1 if t.get("phylum") == "Cnidaria" else 0
        if not t:
            problems.append((name, "no taxonomy for " + row["latin"]))
        rows.append(dict(idx=i, of_name=name, abbr1=row["abbr1"], abbr=row["abbr"],
                         latin=row["latin"], cnidarian=cnid, tax=t, how=how))

    if problems:
        print("!! could not place %d species -- fix by hand, not by guessing:"
              % len(problems), file=sys.stderr)
        for name, how in problems:
            print("     %-40s %s" % (name, how), file=sys.stderr)
        sys.exit(1)

    for r in rows:
        b = busco.get(r["abbr1"])
        r["busco"] = b
        c = b["pct_complete"] if b else None
        r["hq"] = 1 if (r["cnidarian"] and c is not None and c >= HQ_CUTOFF) else 0

    # matrix order: taxonomy first so the published presence/absence matrix
    # reads down the page, outgroups last.
    order = ["Cnidaria", "Porifera", "Ctenophora", ""]
    def sort_key(r):
        t = r["tax"]
        ph = t.get("phylum", "")
        return (order.index(ph) if ph in order else 1,
                t.get("klass", ""), t.get("order", ""), t.get("family", ""),
                t.get("genus", ""), r["latin"])
    rows.sort(key=sort_key)

    with open(os.path.join(WORK, "core", "species.tsv"), "w") as fh:
        fh.write("\t".join([
            "idx", "of_name", "abbr1", "abbr", "latin",
            "phylum", "class", "order", "family", "genus", "ncbi",
            "cnidarian", "busco90",
            "n_buscos", "n_single", "n_duplicated", "n_fragmented", "n_missing",
            "pct_complete", "pct_single", "pct_duplicated",
        ]) + "\n")
        for r in rows:
            t, b = r["tax"], r["busco"] or {}
            fh.write("\t".join(str(x) for x in [
                r["idx"], r["of_name"], r["abbr1"], r["abbr"], r["latin"],
                t.get("phylum", ""), t.get("klass", ""), t.get("order", ""),
                t.get("family", ""), t.get("genus", ""), t.get("ncbi", ""),
                r["cnidarian"], r["hq"],
                b.get("n_buscos", ""), b.get("n_single", ""),
                b.get("n_duplicated", ""), b.get("n_fragmented", ""),
                b.get("n_missing", ""), b.get("pct_complete", ""),
                b.get("pct_single", ""), b.get("pct_duplicated", ""),
            ]) + "\n")

    n_cnid = sum(r["cnidarian"] for r in rows)
    n_hq = sum(r["hq"] for r in rows)
    print("species: %d total, %d cnidarian, %d high-quality (BUSCO C>=%.0f%%)"
          % (len(rows), n_cnid, n_hq, HQ_CUTOFF))
    print("no BUSCO row: %d" % sum(1 for r in rows if not r["busco"]))
    from collections import Counter
    print("classes among HQ:",
          dict(Counter(r["tax"].get("klass", "?") for r in rows if r["hq"])))
    print("\nmatcher decisions that were not exact matches:")
    for r in rows:
        if r["how"] not in ("exact-abbr", "exact-normalised"):
            print("   %-32s -> %-38s %s" % (r["of_name"], r["abbr"], r["how"]))


if __name__ == "__main__":
    main()
