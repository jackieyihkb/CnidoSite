#!/usr/bin/env python3
"""og_members_v2.tsv -> copynumber_full.tsv + og_genes.tsv.

Every occupancy statistic in this directory comes from these two tables.

Three things the member table needs before it can be counted, none of which are
visible from the orthogroup FASTA headers alone:

1. **Isoforms.**  Most proteomes here carry several transcripts per gene model
   (``aacu_s0568.g3.t1``, ``.t2``, ...).  OrthoFinder clusters proteins, so two
   isoforms of one gene are two members of the orthogroup and the species looks
   duplicated.  Left alone this is the single largest source of false
   multi-copy calls -- Acropora_acuminata alone goes from 26,150 members to
   22,306 distinct genes.  Copy number is therefore counted per ``gene_base``,
   the ID with a trailing ``.tN`` removed.  Proteomes that spell IDs some other
   way (``ENSKKQP00000000570.1``, ``EGACTEQ4350007759-PA``) have no ``.tN`` and
   are untouched.  Stripping can only ever merge IDs that differ *solely* by a
   trailing ``.tN``, which is exactly the isoform case, so it cannot merge two
   genuinely different genes.

2. **Duplicate headers.**  Alatina_alata and Calvadosia_cruxmelitensis have
   proteomes whose FASTA headers repeat, so OrthoFinder was given the same ID
   many times and the same gene ID lands in several orthogroups.  That is a
   property of the input, not of the clustering; it is left alone here and the
   copy number stays honest.

3. **Exact duplicate member rows** (1,395) are dropped.

Outputs
-------
copynumber_full.tsv  og, abbr1, n_copies        -- one row per non-empty cell
og_genes.tsv         og, abbr1, gene_base, gene, n_isoforms
                     `gene` is the isoform kept as the representative; the
                     site's per-gene annotation tables are keyed on it.
"""
import os
import re
import collections

WORK = "/mnt/sda/jackie/cnidaria/codex/orthology/work"
CORE = os.path.join(WORK, "core")

ISO = re.compile(r"\.t\d+$")


def main():
    of_to_abbr = {}
    hdr = None
    for i, line in enumerate(open(os.path.join(CORE, "species.tsv"))):
        f = line.rstrip("\n").split("\t")
        if i == 0:
            hdr = f
            continue
        r = dict(zip(hdr, f))
        of_to_abbr[r["of_name"]] = r["abbr1"] or r["of_name"]

    # og -> abbr1 -> gene_base -> [isoform ids]
    genes = collections.defaultdict(lambda: collections.defaultdict(
        lambda: collections.defaultdict(list)))
    seen = set()
    n_dup = 0
    for i, line in enumerate(open(os.path.join(WORK, "og_members_v2.tsv"))):
        if i == 0:
            continue
        og, of_name, abbr, gene = line.rstrip("\n").split("\t")
        key = (og, abbr, gene)
        if key in seen:
            n_dup += 1
            continue
        seen.add(key)
        a = of_to_abbr[of_name]
        genes[og][a][ISO.sub("", gene)].append(gene)
    del seen

    n_cells = 0
    with open(os.path.join(CORE, "copynumber_full.tsv"), "w") as fh:
        fh.write("og\tabbr1\tn_copies\n")
        for og in sorted(genes):
            for a in sorted(genes[og]):
                fh.write("%s\t%s\t%d\n" % (og, a, len(genes[og][a])))
                n_cells += 1

    with open(os.path.join(CORE, "og_genes.tsv"), "w") as fh:
        fh.write("og\tabbr1\tgene_base\tgene\tn_isoforms\n")
        for og in sorted(genes):
            for a in sorted(genes[og]):
                for base, isos in sorted(genes[og][a].items()):
                    fh.write("%s\t%s\t%s\t%s\t%d\n"
                             % (og, a, base, isos[0], len(isos)))

    n_multi_iso = sum(1 for og in genes for a in genes[og] for b in genes[og][a]
                      if len(genes[og][a][b]) > 1)
    print("dropped %d duplicate member rows; %d gene bases carry >1 isoform"
          % (n_dup, n_multi_iso))
    print("wrote copynumber_full.tsv: %d orthogroups, %d (og, species) cells"
          % (len(genes), n_cells))


if __name__ == "__main__":
    main()
