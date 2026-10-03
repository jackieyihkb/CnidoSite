#!/usr/bin/env python3
"""Sanity-check a supermatrix tree against the taxonomy.

This is not a phylogenetic result, it is a smoke test: if the CCO resource is
sound, the shallow, well-established groups (the cnidarian classes) should come
out monophyletic even under a fast approximate tree.  Deep relationships among
the classes are exactly what a 100-1,000 gene matrix is meant to be used to
argue about, so no failure there is treated as an error.
"""
import os
import re
import sys
import collections

CORE = os.path.dirname(os.path.abspath(__file__))


def load_tree(path):
    """Minimal Newick reader.  Tokens are leaves, parens and commas; anything
    between a ')' and the next ',' or ')' is a label/branch length and is
    skipped, which is the bit that is easy to get wrong."""
    s = open(path).read().strip().rstrip(";")
    if not s:
        return None      # FastTree is still writing this one; caller skips it
    toks = re.findall(r"[(),]|[^(),]+", s)
    pos = [0]

    def parse():
        if toks[pos[0]] == "(":
            pos[0] += 1
            children = []
            while True:
                children.append(parse())
                if toks[pos[0]] == ",":
                    pos[0] += 1
                    continue
                if toks[pos[0]] == ")":
                    pos[0] += 1
                    break
                raise ValueError("unexpected token %r" % toks[pos[0]])
            if pos[0] < len(toks) and toks[pos[0]] not in (",", ")"):
                pos[0] += 1
            return children
        tok = toks[pos[0]]
        pos[0] += 1
        return tok.split(":")[0]

    return parse()


def tips(node):
    if isinstance(node, str):
        return [node]
    out = []
    for c in node:
        out += tips(c)
    return out


def mrca(node, group):
    """Tip set of the deepest node containing all of `group`, or None."""
    if isinstance(node, str):
        return None
    ts = set(tips(node))
    if group <= ts:
        for c in node:
            r = mrca(c, group)
            if r is not None:
                return r
        return ts
    return None


def main():
    hdr, sp = None, {}
    for i, line in enumerate(open(os.path.join(CORE, "species.tsv"))):
        f = line.rstrip("\n").split("\t")
        if i == 0:
            hdr = f
            continue
        d = dict(zip(hdr, f))
        sp[d["abbr1"] or d["of_name"]] = d

    for tier in ("strict", "core", "extended"):
        p = os.path.join(CORE, "supermatrix", tier + ".tree")
        if not os.path.exists(p):
            continue
        tree = load_tree(p)
        if tree is None:
            print("== %s: empty -- FastTree still running, skipped" % tier)
            continue
        present = set(tips(tree))
        groups = collections.defaultdict(set)
        for a, d in sp.items():
            if a in present:
                groups[d["class"] or "OUTGROUP"].add(a)
        print("== %s: %d taxa, %d groups" % (tier, len(present), len(groups)))
        for name, members in sorted(groups.items()):
            ts = mrca(tree, members)
            if ts is None:
                verdict = "not recovered"
            elif len(ts) == len(members):
                verdict = "monophyletic"
            else:
                verdict = "MRCA spans %d taxa" % len(ts)
            print("     %-13s n=%-3d %s" % (name, len(members), verdict))
        if isinstance(tree, list):
            for i, c in enumerate(tree):
                print("     root child %d: %d taxa" % (i, len(set(tips(c)))))


if __name__ == "__main__":
    main()
