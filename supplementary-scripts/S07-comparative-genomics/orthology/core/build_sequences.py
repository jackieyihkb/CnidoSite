#!/usr/bin/env python3
"""Emit the core-ortholog sequences and a concatenated supermatrix.

Two artefacts, both built from OrthoFinder's own per-orthogroup FASTA and its
MAFFT alignment rather than by re-aligning anything:

  CCO_members.faa        every member of every core orthogroup, unaligned,
                         header rewritten to `>ABBR|gene` so the file can be
                         handed to any aligner without further parsing.
  supermatrix/<tier>.faa concatenated alignment over the species in which each
                         orthogroup is single-copy, with a RAxML-style
                         partitions file beside it.

Why reuse OrthoFinder's alignments: they already exist for all 1,017 core
orthogroups and were built from the full orthogroup (every copy, every
species).  Subsetting an existing alignment to one sequence per species leaves
the remaining columns correctly aligned; the only cleanup needed is dropping
columns that went all-gap, which `degap_columns` does.  Re-running MAFFT on the
subset would give a slightly different alignment for no gain.

Sequence-level filter, applied per orthogroup before concatenation: a sequence
shorter than half the median ungapped length of that orthogroup is dropped.
Fragmented gene models otherwise contribute long runs of gap to the supermatrix
and pull the taxon's placement around.  The same 0.5x-median rule is what
PhyloPyPruner and similar pipelines use.
"""
import os
import sys
import collections

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from common import HeaderMap, read_fasta, of_sequences_dir, of_msa_dir, ISO

WORK = "/mnt/sda/jackie/cnidaria/codex/orthology/work"
CORE = os.path.join(WORK, "core")
OUT = os.path.join(CORE, "supermatrix")

TIERS = [("strict", 0.90), ("core", 0.80), ("extended", 0.70)]
MIN_FRAC_OF_MEDIAN = 0.5
# an orthogroup needs this many single-copy taxa to be worth a partition
MIN_TAXA = 4
# drop alignment columns occupied in less than this fraction of the partition's
# single-copy taxa
MAX_COL_GAP = 0.5
# a taxon enters the supermatrix if it is single-copy in at least this fraction
# of the tier's partitions, counting the tier as at most REF_TIER_SIZE partitions
MIN_TAXON_FRAC = 0.5
# The bar must not rise as a tier grows, or "extended" -- the most inclusive tier
# -- would drop genomes that "core" keeps.  Expressed against the tier's own size
# it did exactly that: a taxon needed 112 genes in core but 508 in extended, and
# 10 genomes silently vanished from the larger tier.  The reference is the core
# tier's size, so the requirement is 112 genes everywhere; the min() below keeps
# it satisfiable for the 14-partition strict tier.  With this, strict and core
# are unchanged and extended properly contains core.
REF_TIER_SIZE = 224        # the core tier's orthogroup count


def load_core():
    """core_og.tsv -> {og: tiers}, plus the tier cutoffs that apply."""
    core = {}
    hdr = None
    for i, line in enumerate(open(os.path.join(CORE, "core_og.tsv"))):
        f = line.rstrip("\n").split("\t")
        if i == 0:
            hdr = f
            continue
        d = dict(zip(hdr, f))
        core[d["og"]] = (d["hq90_sc"], d["tiers"].split(","))
    return core


def load_copies(ogs):
    copies = collections.defaultdict(dict)
    for line in open(os.path.join(CORE, "copynumber_full.tsv")):
        og, a, n = line.rstrip("\n").split("\t")
        if og in ogs:
            copies[og][a] = int(n)
    return copies


def load_rep(ogs):
    rep = {}
    for line in open(os.path.join(CORE, "og_genes.tsv")):
        og, a, base, gene, n_iso = line.rstrip("\n").split("\t")
        if og in ogs:
            rep[(og, a)] = base
    return rep


def degap_columns(rows, max_gap=MAX_COL_GAP):
    """Drop alignment columns that are too gappy once the orthogroup has been
    subset to its single-copy species.

    A column that is all-gap carries nothing.  A column that is mostly gap is
    worse than nothing in a concatenated analysis: it is a site where a handful
    of taxa assert a homology the rest of the matrix cannot check.  Trimming at
    a gap fraction is the cheap half of what trimAl/Gblocks do, and unlike
    re-aligning it cannot change the remaining columns.
    """
    if not rows:
        return []
    ncol = len(rows[0][1])
    limit = max_gap * len(rows)
    keep = [c for c in range(ncol)
            if sum(1 for r in rows if r[1][c] not in "-.") >= limit]
    return [(h, "".join(s[c] for c in keep)) for h, s in rows]




def main():
    hm = HeaderMap(os.path.join(CORE, "species.tsv"))
    core = load_core()
    ogs = set(core)
    copies = load_copies(ogs)
    rep = load_rep(ogs)
    print("core orthogroups: %d" % len(ogs))

    members_path = os.path.join(CORE, "CCO_members.faa")
    n_members = 0
    # og -> list of (abbr1, gene_isoform) unaligned; used for the unaligned dump
    unaligned = collections.defaultdict(list)
    for og in sorted(ogs):
        p = os.path.join(of_sequences_dir(), og + ".fa")
        for h, s in read_fasta(p):
            a, gene = hm.parse(h)
            if a is None:
                continue
            unaligned[og].append((a, gene, s))
    with open(members_path, "w") as fh:
        for og in sorted(unaligned):
            for a, gene, s in unaligned[og]:
                fh.write(">%s|%s %s\n" % (a, gene, og))
                for i in range(0, len(s), 60):
                    fh.write(s[i:i + 60] + "\n")
                n_members += 1
    print("wrote CCO_members.faa: %d sequences over %d orthogroups"
          % (n_members, len(unaligned)))

    # ---- per-tier supermatrix -------------------------------------------
    os.makedirs(OUT, exist_ok=True)
    stats = {}
    for tier, cut in TIERS:
        tier_ogs = [og for og, (sc, tiers) in core.items() if tier in tiers]
        aln_rows = collections.defaultdict(list)   # abbr1 -> [(og, seq)]
        partitions = []
        pos = 0
        dropped_short = dropped_nomap = 0
        for og in sorted(tier_ogs):
            singles = {a for a, n in copies[og].items() if n == 1}
            if len(singles) < MIN_TAXA:
                continue
            msa = os.path.join(of_msa_dir(), og + ".fa")
            if not os.path.exists(msa):
                continue
            # one record per single-copy species, preferring the gene the
            # member table recorded
            chosen = {}
            for h, s in read_fasta(msa):
                a, gene = hm.parse(h)
                if a not in singles:
                    continue
                if ISO.sub("", gene) != rep.get((og, a)):
                    continue
                chosen[a] = s
            if len(chosen) < MIN_TAXA:
                continue
            lengths = sorted(len(s.replace("-", "").replace(".", ""))
                             for s in chosen.values())
            median = lengths[len(lengths) // 2]
            keep = {a: s for a, s in chosen.items()
                    if len(s.replace("-", "").replace(".", "")) >= MIN_FRAC_OF_MEDIAN * median
                    and median > 0}
            dropped_short += len(chosen) - len(keep)
            if len(keep) < MIN_TAXA:
                continue
            rows = degap_columns(sorted(keep.items()))
            width = len(rows[0][1])
            if width == 0:
                continue
            for a, s in rows:
                aln_rows[a].append((og, s))
            partitions.append((og, pos + 1, pos + width))
            pos += width

        if not partitions:
            print("tier %s: nothing passed the filters" % tier)
            continue

        # Keep taxa present in at least half the partitions -- except the
        # outgroups, which are always kept however sparse they are.  There are
        # only five of them, they are the only way to root the matrix, and
        # dropping them for incompleteness would be exactly the wrong call.
        min_genes = max(1, int(min(len(partitions), REF_TIER_SIZE) * MIN_TAXON_FRAC))
        outgroups = {a for a in aln_rows if a.startswith("OUT_")}
        taxa = sorted(a for a, v in aln_rows.items()
                      if len(v) >= min_genes or a in outgroups)
        dropped_taxa = len(aln_rows) - len(taxa)

        span = {og: (s, e) for og, s, e in partitions}
        fa = os.path.join(OUT, "%s.faa" % tier)
        with open(fa, "w") as fh:
            for a in taxa:
                row = ["-"] * pos
                for og, s in aln_rows[a]:
                    start, end = span[og]
                    row[start - 1:end] = s
                fh.write(">%s\n" % a)
                line = "".join(row)
                for i in range(0, len(line), 60):
                    fh.write(line[i:i + 60] + "\n")

        with open(os.path.join(OUT, "%s.partitions.txt" % tier), "w") as fh:
            for og, s, e in partitions:
                fh.write("AA, %s = %d-%d\n" % (og, s, e))

        stats[tier] = (len(taxa), len(partitions), pos)
        print("tier %-9s %3d taxa x %4d partitions = %d columns"
              "  (dropped %d short seqs, %d sparse taxa)"
              % (tier, len(taxa), len(partitions), pos, dropped_short, dropped_taxa))

    print("\nHeaderMap could not place %d headers" % hm.unresolved)
    return stats


if __name__ == "__main__":
    main()
