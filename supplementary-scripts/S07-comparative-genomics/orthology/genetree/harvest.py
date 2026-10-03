#!/usr/bin/env python3
"""Normalise the OrthoFinder gene trees into a shape the web page can use.

`Results_Sep14_1/Resolved_Gene_Trees` holds the trees OrthoFinder's gene-tree resolution
step wrote out (Sep14 itself was interrupted, so its Gene_Trees/ is empty and only the
resolved subset exists).  Those files are almost what we want, with two problems:

  * every tip label is the raw FASTA header, so it carries the protein description after
    the first space ("...XP_044179095.1 G-protein-signaling modulator 2-like [Acropora
    millepora]").  The description contains spaces and brackets, which is not valid Newick
    and would end up rendered as part of the tip name.
  * internal nodes are labelled n1, n2, ... -- not support values, just leftovers.

So: keep the first whitespace-delimited token of each tip label, drop internal labels, and
resolve the token back to (species, abbr1, gene).  The resolution rule is the same one
`../parse_headers2.py` uses and validates against all 5,655,975 FASTA headers -- match the
longest known species prefix, the next token is the abbr1, the remainder is the gene id.
Reused rather than reinvented because that parser is already known to be exact.

Three modes, because the source directory lives on a filesystem that is loaded hard enough
to make a serial pass take hours:

    harvest.py --scan          list (og, path) for the families we care about
    harvest.py --one OG        normalise one tree, writing trees/<OG>.nwk
    harvest.py --src DIR --suffix S   normalise a whole directory (used for FastTree output)

The parallel driver fans `--one` out with xargs; each worker writes only its own file.
"""
import os
import re
import sys
from collections import defaultdict

SEQDIR = ('/mnt/sda/jackie/cnidaria/0.tree/0.peps/OrthoFinder/'
          'Results_Sep14_1/Resolved_Gene_Trees')
PEPDIR = '/mnt/sda/jackie/cnidaria/0.tree/0.peps'
FAMS = '/mnt/sda/jackie/cnidaria/codex/orthology/work/out_v2/family.tsv'
WORK = os.path.dirname(os.path.abspath(__file__))
OUTDIR = os.path.join(WORK, 'trees')
LIST = os.path.join(WORK, 'tree_files.txt')
SUFFIX = '_tree.txt'

ABBR_OK = re.compile(r'^[A-Za-z][A-Za-z0-9]{0,19}$')
GENE_OK = re.compile(r'^[^\s:;(),]+$')


def build_species():
    """Longest-first species names, plus a first-token index to keep matching cheap."""
    species = sorted((f[:-4] for f in os.listdir(PEPDIR) if f.endswith('.pep')),
                     key=len, reverse=True)
    by_first = defaultdict(list)
    for s in species:
        by_first[s.split('_', 1)[0]].append(s)
    return species, by_first


SPECIES, BY_FIRST = build_species()


def parse_tip(label):
    """A tip's first token -> (species, abbr1, gene) or (None, None, None).

    Outgroup proteomes are named OUT_<Species> and their tips look like
    `OUT_Corticium_candelabrum_OUT_Corticium_candelabrum+XP_062498776.1`, so the same
    prefix rule resolves them once the OUT_ is stripped from the species side.
    """
    first = label.split('_', 1)[0]
    for s in BY_FIRST.get(first, ()):
        pre = s + '_'
        if not label.startswith(pre):
            continue
        rest = label[len(pre):]
        if s.startswith('OUT_'):
            # Outgroup headers are hand-built, not generated: the remainder reads
            # `OUT_Corticium_candelabrum+XP_062503408.1`, so the abbr is everything before
            # the `+` and the accession is what follows.  Splitting on the first `_` here
            # would take `OUT` as the abbr and swallow the species into the gene id.
            abbr, plus, gene = rest.partition('+')
            if not plus or not GENE_OK.match(gene):
                return None, None, None
            return s, abbr, gene
        i = rest.find('_')
        if i <= 0:
            return None, None, None
        abbr = rest[:i]
        gene = rest[i + 1:]
        if not GENE_OK.match(gene) or not ABBR_OK.match(abbr):
            return None, None, None
        return s, abbr, gene
    return None, None, None


def rewrite(raw):
    """Return (newick, tips, unresolved, duplicated) with labels cleaned up.

    Walks the string rather than parsing to a tree: we only need to rewrite labels, and a
    character walk cannot be confused by the punctuation-free labels these files carry.
    """
    out = []
    tips = []
    unresolved = []
    seen = {}
    duplicated = 0
    i = 0
    n = len(raw)
    while i < n:
        c = raw[i]
        if c == '(' or c == ',':
            out.append(c)
            i += 1
            j = i
            while j < n and raw[j] not in '(),:;':
                j += 1
            lab = raw[i:j].strip().strip("'\"")
            if lab:
                tok = lab.split()[0]
                sp, abbr, gene = parse_tip(tok)
                if sp is None:
                    unresolved.append(lab[:120])
                    out.append(tok)
                else:
                    tips.append((sp, abbr, gene))
                    # Two sequences can share one gene id (Alatina_alata and
                    # Calvadosia_cruxmelitensis ship duplicate headers), and Newick wants
                    # distinct tip names, so disambiguate the repeat.
                    k = seen.get(tok, 0)
                    seen[tok] = k + 1
                    if k:
                        duplicated += 1
                        out.append('%s#%d' % (tok, k + 1))
                    else:
                        out.append(tok)
            i = j
        elif c == ')':
            out.append(c)
            i += 1
            j = i
            while j < n and raw[j] not in '(),:;':
                j += 1
            # internal label (n1, n2, ...) carries no support value -- drop it
            i = j
        else:
            out.append(c)
            i += 1
    s = ''.join(out).strip()
    if not s.endswith(';'):
        s += ';'
    return s, tips, unresolved, duplicated


def families():
    with open(FAMS) as f:
        hdr = f.readline().rstrip('\n').split('\t')
        i = hdr.index('og')
        return [line.split('\t', i + 1)[i] for line in f]


def scan():
    famset = set(families())
    print('families: %d' % len(famset), flush=True)
    os.makedirs(OUTDIR, exist_ok=True)
    n = 0
    with open(LIST, 'w') as out, os.scandir(SEQDIR) as it:
        for e in it:
            if not e.name.endswith(SUFFIX):
                continue
            og = e.name[:-len(SUFFIX)]
            if og in famset:
                out.write('%s\t%s\n' % (og, e.path))
                n += 1
    print('listed: %d -> %s' % (n, LIST), flush=True)


def one(og):
    path = os.path.join(SEQDIR, og + SUFFIX)
    dst = os.path.join(OUTDIR, og + '.nwk')
    if os.path.exists(dst):
        return
    with open(path) as fh:
        raw = fh.read()
    nwk, tips, bad, dup = rewrite(raw)
    tmp = dst + '.tmp%d' % os.getpid()
    with open(tmp, 'w') as fh:
        fh.write(nwk)
    os.replace(tmp, dst)
    if bad:
        with open(os.path.join(WORK, 'unresolved.log'), 'a') as fh:
            for b in bad[:3]:
                fh.write('%s\t%s\n' % (og, b))


def sweep(src, suffix, write_missing):
    famset = set(families())
    print('families to cover: %d' % len(famset), flush=True)
    print('source           : %s (*%s)' % (src, suffix), flush=True)
    os.makedirs(OUTDIR, exist_ok=True)
    have = set()
    n_files = n_tips = n_out = n_dup = 0
    unresolved = []
    with os.scandir(src) as it:
        for e in it:
            if not e.name.endswith(suffix):
                continue
            og = e.name[:-len(suffix)]
            if og not in famset:
                continue
            with open(e.path) as fh:
                raw = fh.read()
            nwk, tips, bad, dup = rewrite(raw)
            with open(os.path.join(OUTDIR, og + '.nwk'), 'w') as fh:
                fh.write(nwk)
            have.add(og)
            n_files += 1
            n_tips += len(tips)
            n_out += sum(1 for t in tips if t[0].startswith('OUT_'))
            n_dup += dup
            unresolved.extend(bad[:3])
            if n_files % 2000 == 0:
                print('  ...%d trees' % n_files, flush=True)

    missing = sorted(famset - have)
    if write_missing:
        with open(os.path.join(WORK, 'missing.txt'), 'w') as fh:
            fh.write('\n'.join(missing) + ('\n' if missing else ''))

    print('trees written      : %d' % n_files)
    print('tips               : %d' % n_tips)
    print('  outgroup tips    : %d' % n_out)
    print('  duplicate labels : %d' % n_dup)
    print('unresolved labels  : %d' % len(unresolved))
    for u in unresolved[:10]:
        print('   ! %s' % u)
    print('missing (no tree)  : %d' % len(missing))


def main():
    args = sys.argv[1:]
    if not args:
        sweep(SEQDIR, SUFFIX, True)
        return
    while args:
        a = args.pop(0)
        if a == '--scan':
            scan()
        elif a == '--one':
            # xargs hands over a batch at a time: building the species index costs a
            # directory listing, which is not something to repeat 45,000 times on a
            # filesystem this loaded.
            for og in args:
                try:
                    one(og)
                except Exception as exc:  # one unreadable file must not sink the batch
                    with open(os.path.join(WORK, 'harvest_errors.log'), 'a') as fh:
                        fh.write('%s\t%s\n' % (og, exc))
            return
        elif a == '--src':
            sweep(args.pop(0), args.pop(0), False)
        else:
            raise SystemExit('unknown argument: %s' % a)


if __name__ == '__main__':
    main()
