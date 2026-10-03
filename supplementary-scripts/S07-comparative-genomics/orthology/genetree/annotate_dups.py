#!/usr/bin/env python3
"""Put OrthoFinder's duplication calls onto the trees we ship.

`Results_Sep14_1/Gene_Duplication_Events/Duplications.tsv` (6.7 GB, 2,283,346 rows) has one
row per duplication node of every resolved gene tree:

    Orthogroup  Species Tree Node  Gene Tree Node  Support  Type  Genes 1  Genes 2

`Type` is OrthoFinder's own classification -- Terminal means both sides of the node are the
same species (a within-species radiation, i.e. in-paralogues), Non-Terminal means the
duplication predates the split of the species on either side.  `Support` is the fraction of
species-tree nodes consistent with that call.

The `Gene Tree Node` column is the `nN` label the source trees still carry, but those labels
are stripped from the trees we ship (they mean nothing to a reader, and phylotree.js would
print them all over the figure).  So each duplication node is identified structurally
instead: the path from the root as a chain of child indices, "0.1.2".  Path keys survive
everything done to the tree afterwards -- renaming tips does not move nodes, and the
client-side species grafting keeps ancestors' child order, so a key still resolves after a
species clade has been expanded.

For the 177 families over COLLAPSE_OVER the page draws the *collapsed* tree, so their keys
are computed on the collapsed topology -- a duplication inside one species maps onto that
species' collapsed tip and is what makes the `COLLAPSEDxN` badge worth reading.  The
placement rule, and why it is the species' LCA rather than the source node's image, is
documented at the point of use in handle().

Output: load_dups.tsv, one row per family whose duplications could be called at all --
    og <TAB> n_dup <TAB> n_terminal <TAB> gzip+base64 JSON [[pathkey, support, type], ...]

A family whose OrthoFinder tree resolved and simply has no duplication gets an *empty*
array, not a missing row.  That distinction is the page's only way to tell "we looked and
there are none" from "this family has no resolved tree at all" (the 22,281 families we had
to build FastTree trees for ourselves, which are unrooted and carry no duplication
calls) -- and the two need different wording: one-to-one orthology either way, versus
"all members are orthologues or co-orthologues, we cannot tell which".  pack_trees writes
\\N for the families in neither group.

    python3 annotate_dups.py            # ~5 minutes, dominated by the 6.7 GB scan
"""
import base64
import gzip
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import harvest  # noqa: E402
import pack_trees  # noqa: E402

WORK = os.path.dirname(os.path.abspath(__file__))
OF = '/mnt/sda/jackie/cnidaria/0.tree/0.peps/OrthoFinder'
DUP = os.path.join(OF, 'Results_Sep14_1/Gene_Duplication_Events/Duplications.tsv')
SRC = os.path.join(OF, 'Results_Sep14_1/Resolved_Gene_Trees')
TREES = os.path.join(WORK, 'trees')
LOAD_TREES = os.path.join(WORK, 'load_trees.tsv')
OUT = os.path.join(WORK, 'load_dups.tsv')

TYPE = {'Terminal': 'T', 'Non-Terminal': 'N'}
STAT = dict(families=0, nodes=0, terminal=0, fallback=0, unmapped=0, no_source=0,
            collapsed=0, in_tip=0, exact=0, unplaced=0, clade_level=0, resolved_no_dup=0,
            keys=0)


def keys_of(root):
    """id(node) -> path key, for every node.  Root is '', its i-th child 'i', and so on."""
    out = {}
    stack = [(root, '')]
    while stack:
        n, k = stack.pop()
        out[id(n)] = k
        for i, c in enumerate(n['children']):
            stack.append((c, (k + '.' + str(i)) if k else str(i)))
    return out


def labelled(root):
    """(internal label -> node, tip name -> node)."""
    by_label, by_tip = {}, {}
    stack = [root]
    while stack:
        n = stack.pop()
        if n['children']:
            if n['name']:
                by_label[n['name']] = n
        else:
            by_tip[n['name']] = n
        stack.extend(n['children'])
    return by_label, by_tip


def species_under(root):
    """id(node) -> set of species beneath it, for every node.

    Also returns the node list: the collapse mutates the tree and drops subtrees, and a
    dropped node's id() can then be handed to a freshly allocated object, which would make
    every later lookup by id silently wrong.  Holding the nodes keeps that from happening.
    """
    out = {}
    nodes = []

    def walk(n):
        nodes.append(n)
        if not n['children']:
            s = {harvest.parse_tip(n['name'].split()[0])[0]}
        else:
            s = set()
            for c in n['children']:
                s |= walk(c)
        out[id(n)] = s
        return s

    walk(root)
    return out, nodes


def lca_key(keys):
    """Path key of the deepest node that is an ancestor of every key given.

    Two children of one parent differ in the last component, so the answer is simply the
    longest shared prefix of components -- no tree walk needed.
    """
    parts = min((k.split('.') for k in keys), key=len)
    for k in keys:
        p = k.split('.')
        i = 0
        while i < len(parts) and i < len(p) and p[i] == parts[i]:
            i += 1
        parts = parts[:i]
    return '.'.join(parts)


def lca_by_genes(root, gene_tokens, by_tip):
    """Deepest node whose subtree holds every one of `gene_tokens` (the Genes 1 column).

    Only for the handful of rows whose `nN` label is not in the tree -- the root label sits
    at the very end of the file (`)n0;`) where a naive scan misses it.  O(tree) per call, so
    it stays off the hot path deliberately.
    """
    want = set()
    for tok in gene_tokens:
        tok = tok.split()[0]
        node = by_tip.get(tok)
        if node is None and (tok + '#2') in by_tip:      # duplicate headers got suffixed
            node = by_tip[tok + '#2']
        if node is None:
            return None
        want.add(id(node))
    if not want:
        return None
    total = len(want)
    found = []

    def count(n):
        if not n['children']:
            return 1 if id(n) in want else 0
        c = 0
        for k in n['children']:
            c += count(k)
        if c == total and not found:
            found.append(n)
        return c

    count(root)
    return found[0] if found else None


def handle(og, group, genes, n_tips, out):
    src = os.path.join(SRC, og + '_tree.txt')
    shipped = os.path.join(TREES, og + '.nwk')
    if not os.path.exists(src) or not os.path.exists(shipped):
        STAT['no_source'] += 1
        return
    with open(src) as f:
        raw = f.read()
    with open(shipped) as f:
        shipped_nwk = f.read()
    # Cheap structural check: sanitising labels cannot change the shape, so the comma count
    # must match.  If it ever does not, every key would be silently wrong -- fail loudly.
    if shipped_nwk.count(',') != raw.count(','):
        sys.exit('%s: shipped tree is not the source tree' % og)

    root = pack_trees.parse(raw)
    by_label, by_tip = labelled(root)
    by_name = dict(by_tip)

    entries = []                       # (node, support, type)
    for lab, sup, typ in group:
        node = by_label.get(lab)
        if node is None:
            node = lca_by_genes(root, genes.get(lab, []), by_name)
            if node is not None:
                STAT['fallback'] += 1
        if node is None:
            STAT['unmapped'] += 1
            continue
        entries.append((node, sup, typ))

    drawn = shipped_nwk                     # the text the page will actually draw
    if n_tips > pack_trees.COLLAPSE_OVER:
        # A collapsed tree can only carry a duplication its one-tip-per-species topology still
        # shows.  Measured on OG0000001: of 15,674 duplication nodes, 11,431 are inside a single
        # species -- their whole clade is dropped, because that species is already drawn once,
        # elsewhere -- and of the 4,243 that span species only 97 collapse onto exactly the
        # species they span.  So each node is placed in one of three ways, and the type says
        # which:
        #   T  a radiation inside one species -> that species' collapsed tip
        #   N  the species the duplication spans collapse to a clade of exactly those species
        #   C  they collapse into a wider clade: mark the deepest node that still contains all
        #      of them.  That is a true statement about the clade ("a duplication happened
        #      within it"), and it is what keeps the big families from losing every
        #      cross-species duplication in the drawing.  The client renders N solid and C as a
        #      count, so nothing claims more precision than it has.
        #
        # Placing by LCA of the species themselves, rather than by asking where the source
        # node's image ended up, is deliberate: pruning a duplicate-species tip out of an
        # ancestor can leave that ancestor unifurcating, and collapse then walks *down* the
        # chain to a node whose clade is a strict subset of the duplication's.  The LCA
        # cannot do that -- it is by construction the smallest clade holding all of them.
        sp_under, keepalive = species_under(root)
        col_nwk = pack_trees.collapse_root(root)
        if col_nwk:
            drawn = col_nwk
            col_root = pack_trees.parse(col_nwk)
            col_key = keys_of(col_root)
            col_sp, keepalive = species_under(col_root)
            tip_key = {}                       # species -> key of its one collapsed tip
            key_sp = {}                        # key -> species under that node
            stack = [col_root]
            while stack:
                n = stack.pop()
                k = col_key[id(n)]
                key_sp[k] = col_sp[id(n)]
                if not n['children']:
                    sp = harvest.parse_tip(n['name'].split()[0])[0]
                    if sp:
                        tip_key[sp] = k
                stack.extend(n['children'])
            placed = []
            for node, sup, typ in entries:
                sp = sp_under[id(node)]
                if len(sp) == 1:                       # a radiation inside one species
                    k = tip_key.get(next(iter(sp)))
                    if k is not None:
                        placed.append((k, sup, 'T'))
                        STAT['in_tip'] += 1
                        continue
                else:
                    keys = [tip_key.get(s) for s in sp]
                    if all(keys):
                        k = lca_key(keys)
                        t = 'N' if key_sp[k] == sp else 'C'
                        placed.append((k, sup, t))
                        STAT['exact' if t == 'N' else 'clade_level'] += 1
                        continue
                STAT['unplaced'] += 1              # a species the collapsed tree does not draw
            entries = placed
            STAT['collapsed'] += 1
            assert keepalive
    else:
        # The page draws the shipped tree, which is the source tree with the long tip headers
        # shortened -- labels change, topology does not, so a node's key here is its key there.
        # (The comma-count check above is what makes that safe to assume.)
        k_of = keys_of(root)
        entries = [(k_of[id(n)], sup, t) for n, sup, t in entries]

    # Assert against the text the client will parse, not against the tree we started from:
    # a key that does not resolve there is a marker that silently never appears.
    valid = set(keys_of(pack_trees.parse(drawn)).values())
    for k, _, _ in entries:
        if k not in valid:
            sys.exit('%s: key %s does not resolve in the drawn tree' % (og, k))

    n_term = sum(1 for _, _, t in entries if t == 'T')
    n_placed = len(entries)          # duplication nodes, before the merge below folds keys

    # One entry per node, not per duplication.  Two duplication nodes land on the same key
    # whenever the collapse folds their clades together -- overwhelmingly so in the big
    # families: OG0000001's 15,674 duplications resolve to 171 distinct nodes.  Keeping all
    # of them would ship 15,674 triples for the client to fold back into 171 (a dict, so
    # last-one-wins) and would make the displayed support depend on file order, which is not
    # a property of the data.  So merge here: keep the strongest support, and let N (the
    # species the duplication spans collapse to exactly this clade) outrank C (it sits on a
    # wider clade), which is the only conflict the collapse can produce.
    merged = {}
    for k, sup, t in entries:
        e = merged.get(k)
        if e is None:
            merged[k] = [sup, t]
        else:
            e[0] = max(e[0], sup)
            if t == 'N':
                e[1] = 'N'
    entries = sorted([k, s, t] for k, (s, t) in merged.items())
    blob = base64.b64encode(gzip.compress(
        json.dumps([[k, round(s, 2), t] for k, s, t in entries]).encode(), 6)).decode('ascii')
    out.write('%s\t%d\t%d\t%s\n' % (og, n_placed, n_term, blob))
    STAT['families'] += 1
    STAT['nodes'] += n_placed         # what Duplications.tsv contributed, minus nothing
    STAT['keys'] += len(entries)      # what the client actually has to draw
    STAT['terminal'] += n_term


def main():
    # Optional arguments are for testing on an extract of the 6.7 GB file.
    dup_path = sys.argv[1] if len(sys.argv) > 1 else DUP
    out_path = sys.argv[2] if len(sys.argv) > 2 else OUT
    n_tips = {}
    with open(LOAD_TREES) as fh:
        for line in fh:
            f = line.split('\t', 2)
            n_tips[f[0]] = int(f[1])
    have = set(f[:-4] for f in os.listdir(TREES) if f.endswith('.nwk'))
    print('families to annotate: %d' % len(have), flush=True)

    cur, group, genes = None, [], {}
    seen = set()
    with open(dup_path) as fh, open(out_path, 'w') as out:
        fh.readline()                                   # header
        for i, line in enumerate(fh, 1):
            f = line.rstrip('\n').split('\t')
            og = f[0]
            if og != cur:
                if cur in have and group:
                    handle(cur, group, genes, n_tips[cur], out)
                    seen.add(cur)
                cur, group, genes = og, [], {}
                if og not in have:
                    continue
            elif og not in have:
                continue
            try:
                sup = float(f[3])
            except ValueError:
                sup = 0.0
            group.append((f[2], sup, TYPE.get(f[4], 'N')))
            genes[f[2]] = f[5].split(',')
            if i % 500000 == 0:
                print('  ...%d rows, %d families' % (i, STAT['families']), flush=True)
        if cur in have and group:
            handle(cur, group, genes, n_tips[cur], out)
            seen.add(cur)

        # Families whose tree resolved but which have no duplication at all.  Written as an
        # empty array so the page can promise one-to-one orthology instead of hedging; see
        # the note at the top.  This walks the whole family list rather than the dup file,
        # because the dup file is exactly where these families are *absent* from.
        empty = base64.b64encode(gzip.compress(b'[]', 6)).decode('ascii')
        for og in sorted(have - seen):
            if os.path.exists(os.path.join(SRC, og + '_tree.txt')):
                out.write('%s\t0\t0\t%s\n' % (og, empty))
                STAT['resolved_no_dup'] += 1

    print('families with duplications  : %d' % STAT['families'])
    print('resolved, no duplication     : %d' % STAT['resolved_no_dup'])
    print('duplication nodes written  : %d  (terminal %d, inside a collapsed tip %d)'
          % (STAT['nodes'], STAT['terminal'], STAT['in_tip']))
    print('distinct nodes marked      : %d' % STAT['keys'])
    print('placed by gene-set LCA     : %d' % STAT['fallback'])
    print('within-species -> species tip: %d' % STAT['in_tip'])
    print('to nearest surviving clade    : %d' % STAT['clade_level'])
    print('clade exactly the right species: %d' % STAT['exact'])
    print('unplaceable in collapsed tree : %d' % STAT['unplaced'])
    print('unmapped (skipped)         : %d' % STAT['unmapped'])
    print('collapsed families handled : %d' % STAT['collapsed'])
    print('source tree missing        : %d' % STAT['no_source'])
    print('wrote %s (%.1f MB)' % (out_path, os.path.getsize(out_path) / 1e6))


if __name__ == '__main__':
    main()
