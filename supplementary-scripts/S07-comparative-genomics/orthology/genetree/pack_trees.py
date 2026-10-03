#!/usr/bin/env python3
"""Pack the normalised gene trees into one TSV row per family for MySQL.

The Newick goes in gzipped and base64-encoded rather than as plain text.  Raw Newick for
67,795 families is roughly 0.9 GB, and the web server's root filesystem is at 97% with
65 GB free -- not somewhere to spend a gigabyte on something that compresses ~6x.  PHP
decodes it with gzdecode(base64_decode(...)), so the page code does not care.

Big families also get a second, species-collapsed tree.  OG0000000 has 35,546 tips across
144 species; drawn literally that is not a tree, it is a smear, and the browser would have
to lay out 35,546 SVG nodes.  Collapsing to one tip per species keeps the branching among
species and brings it down to 144 tips.  Doing it here rather than in PHP means the page
only has to choose a column.  The full tree stays downloadable, so nothing is lost.

Per-tip metadata is deliberately not stored: it is derivable from the tip label plus the
site's taxonomy.json, and duplicating it would be another few hundred MB.

Output columns: og, n_tips, n_species, n_outgroup, tree_gz, tree_col_gz, n_dup,
n_dup_terminal, dup_gz.  The last three come from annotate_dups.py, whose output is stapled
on at the end of main() -- so the order to run these in is pack, annotate, pack.
"""
import base64
import gzip
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import harvest  # noqa: E402  (needs the path tweak above)

WORK = os.path.dirname(os.path.abspath(__file__))
TREES = os.path.join(WORK, 'trees')
OUT = os.path.join(WORK, 'load_trees.tsv')
DUPS = os.path.join(WORK, 'load_dups.tsv')

COLLAPSE_OVER = 3000   # keep in step with $COLLAPSE_OVER in the page
MARK = 'COLLAPSEDx'    # gene field of a collapsed representative

LABEL = re.compile(r'[(,]([^(),:;]+)')


# --------------------------------------------------------------------------- parse
def parse(nwk):
    s = nwk.strip().rstrip(';')
    pos = 0

    def node():
        nonlocal pos
        n = {'children': [], 'name': '', 'len': 0.0}
        if s[pos] == '(':
            pos += 1
            while True:
                n['children'].append(node())
                if s[pos] == ',':
                    pos += 1
                    continue
                pos += 1          # ')'
                break
        start = pos
        while pos < len(s) and s[pos] not in '(),:;':
            pos += 1
        n['name'] = s[start:pos].strip().strip("'\"")
        if pos < len(s) and s[pos] == ':':
            pos += 1
            start = pos
            while pos < len(s) and s[pos] not in '(),;':
                pos += 1
            try:
                n['len'] = float(s[start:pos])
            except ValueError:
                n['len'] = 0.0
        return n

    return node()


def render(n):
    if n['children']:
        return '(' + ','.join(render(c) for c in n['children']) + ')' + n['name'] + \
               (':%g' % n['len'] if n['len'] else '')
    return n['name'] + (':%g' % n['len'] if n['len'] else '')


def species_of(node):
    if not node['name']:
        return None, None, None
    return harvest.parse_tip(node['name'].split()[0])


def collapse(nwk, want_map=False):
    """One tip per species, topology among species preserved."""
    return collapse_root(parse(nwk), want_map)


def collapse_root(root, want_map=False):
    """Collapse an already-parsed tree; see collapse().

    With want_map the caller also gets back where each original node ended up --
    `img[id(orig_node)] -> collapsed node` -- plus the collapsed tree's root.  annotate_dups.py
    needs that to place OrthoFinder's duplication nodes (named in the *source* tree) on the
    collapsed tree the page draws, and it is why this takes a root instead of a Newick
    string: the mapping's keys are `id()` of the caller's node objects, which a re-parse
    inside here would not be.  Sharing this function rather than reimplementing the collapse
    there is the point -- two collapses that drift by one rule would put the duplication
    markers on the wrong branches.
    """
    img = {}

    counts = {}
    keep = set()

    def mark(n):
        if n['children']:
            for c in n['children']:
                mark(c)
        else:
            sp, abbr, gene = species_of(n)
            if sp is None:
                keep.add(id(n))          # unparsed tip: leave it alone
            else:
                counts[sp] = counts.get(sp, 0) + 1
                if counts[sp] == 1:
                    keep.add(id(n))
    mark(root)

    def prune(n):
        if not n['children']:
            return n if id(n) in keep else None
        kids = [k for k in (prune(c) for c in n['children']) if k is not None]
        n['children'] = kids
        return n if kids else None

    root = prune(root)
    if root is None:
        return None

    def relabel(n):
        if n['children']:
            for c in n['children']:
                relabel(c)
        else:
            sp, abbr, gene = species_of(n)
            if sp is not None and counts.get(sp, 1) > 1 and gene:
                # Keep whatever came before the gene id and only swap the gene out.  The
                # label has to stay resolvable: building it from the species name instead
                # would drop the OUT_ marker that outgroup tips carry, and splitting on
                # the wrong separator would fold the abbr into the gene.
                n['name'] = n['name'][:-len(gene)] + MARK + str(counts[sp])
    relabel(root)

    def suppress(n):
        n['children'] = [suppress(c) for c in n['children']]
        m = n
        while len(m['children']) == 1:
            m = m['children'][0]
        if want_map:
            # n survived as m; m is already in img because children are done first, but a
            # node that was itself swallowed by its own chain reports the chain's end.
            img[id(n)] = img.get(id(m), m)
        return m

    out = suppress(root)
    if want_map:
        return render(out) + ';', img, out
    return render(out) + ';'


# --------------------------------------------------------------------------- stats
def stats(nwk):
    """(n_tips, n_species, n_outgroup).  Commas equal leaves minus one for any tree in
    which every internal node has at least two children -- which Newick guarantees -- so
    the leaf count is exact without a second pass over the parentheses."""
    n_tips = nwk.count(',') + 1
    species = set()
    out = 0
    for lab in LABEL.findall(nwk):
        sp, abbr, gene = harvest.parse_tip(lab.split()[0]) if lab.strip() else (None, None, None)
        if sp is None:
            continue
        species.add(sp)
        if sp.startswith('OUT_'):
            out += 1
    return n_tips, len(species), out


def pack(raw):
    return base64.b64encode(gzip.compress(raw, 6)).decode('ascii')


def merge_dups():
    """Append the duplication columns to load_trees.tsv, from what annotate_dups.py wrote.

    Two separate passes rather than one: annotate_dups.py needs n_tips (to know which
    families the page collapses, and therefore which topology its path keys have to be
    computed on), and n_tips is only known once the trees are packed.  So the pipeline is
    pack -> annotate -> pack again, and the second pack only has to staple 43,048 short
    rows onto the file -- no re-gzipping of 0.9 GB of Newick.

    Families with no duplication row get 0 / \\N, which is what the page tests.
    """
    if not os.path.exists(DUPS):
        print('no %s -- n_dup left at 0 for every family' % os.path.basename(DUPS))
        return
    dup = {}
    with open(DUPS) as fh:
        for line in fh:
            f = line.rstrip('\n').split('\t')
            dup[f[0]] = (f[1], f[2], f[3])
    tmp = OUT + '.tmp'
    rows = with_dup = 0
    with open(OUT) as fh, open(tmp, 'w') as out:
        for line in fh:
            # Always rebuild from the first six columns, so this is idempotent: running it
            # again on an already-merged file replaces the three columns instead of
            # tacking on a second set of them.
            f = line.rstrip('\n').split('\t')
            og = f[0]
            d = dup.get(og)
            if d:
                out.write('%s\t%s\t%s\t%s\n' % ('\t'.join(f[:6]), d[0], d[1], d[2]))
                with_dup += 1
            else:
                out.write('%s\t0\t0\t\\N\n' % '\t'.join(f[:6]))
            rows += 1
    os.replace(tmp, OUT)
    print('merged duplications  : %d families (of %d rows)' % (with_dup, rows))


def main():
    # --merge-only re-staples the duplication columns without re-gzipping the trees, which
    # is what you want after re-running annotate_dups.py (its output changed, the trees did
    # not).
    if len(sys.argv) > 1 and sys.argv[1] == '--merge-only':
        merge_dups()
        return
    names = sorted(f[:-4] for f in os.listdir(TREES) if f.endswith('.nwk'))
    print('trees to pack: %d' % len(names), flush=True)

    total = collapsed = 0
    with open(OUT, 'w') as out:
        for i, name in enumerate(names, 1):
            with open(os.path.join(TREES, name + '.nwk'), 'rb') as fh:
                raw = fh.read().strip()
            if not raw:
                continue
            text = raw.decode('utf-8', 'replace')
            n_tips, n_sp, n_out = stats(text)
            # \N, not the word NULL: LOAD DATA reads an unquoted `NULL` as the four-letter
            # string, which is not NULL at all -- `tree_col_gz IS NOT NULL` then matches
            # every row and the page tries to base64-decode the text "NULL" for all 67,795
            # families instead of the 177 that actually have a collapsed tree.
            col = '\\N'
            if n_tips > COLLAPSE_OVER:
                c = collapse(text)
                if c:
                    col = pack(c.encode())
                    collapsed += 1
            blob = pack(raw)
            total += len(blob) + (0 if col == '\\N' else len(col))
            out.write('%s\t%d\t%d\t%d\t%s\t%s\n' % (name, n_tips, n_sp, n_out, blob, col))
            if i % 5000 == 0:
                print('  ...%d  (%.1f MB packed, %d collapsed)' % (i, total / 1e6, collapsed),
                      flush=True)

    print('rows             : %d' % len(names))
    print('collapsed trees  : %d (over %d tips)' % (collapsed, COLLAPSE_OVER))
    print('packed           : %.1f MB' % (total / 1e6))
    merge_dups()


if __name__ == '__main__':
    main()
