#!/usr/bin/env python3
"""Splice the site's header/nav block into the core-ortholog page.

Same approach as work/genetree/build.py: the nav (header wrapper + menu) is hand-
maintained HTML that every CnidoSite page inlines rather than printing from a shared
include, so this lifts the block verbatim out of the deployed phylotree page and
pastes it into the template.  Because it is lifted rather than retyped, the nav here
can never drift from what the rest of the site serves.

One difference from the gene-tree page: this page adds a menu entry.  The insertion
is done on the spliced nav, with the anchor asserted to appear exactly once, so a
future edit to the dropdown fails loudly instead of silently dropping the entry.

Source of truth is php_orig/phylotree_index.php: a verbatim copy pulled off the server.

    python3 build.py            -> index.php
"""
import os
import sys

WORK = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(WORK, 'php_orig', 'phylotree_index.php')
TMPL = os.path.join(WORK, 'index_template.php')
OUT = os.path.join(WORK, 'index.php')

NAV_FIRST = '<div id="templatemo_header_wrapper">'
NAV_LAST = '<div id="tempatemo_content_wrapper">'
MARKER = '<!--@NAV@-->'

# Genomics dropdown: put Core Orthologs next to Species Tree, since the two answer
# adjacent questions (how are these species related / which genes back that up).
ANCHOR = '                    <li><a href="/phylotree/">Species Tree</a></li>\n'
ENTRY = '                    <li><a href="/core/">Core Orthologs</a></li>\n'

# The one line that prints the site footer.  Asserted below so it cannot go missing
# silently -- the nav block this script splices stops before the content wrapper, so
# it never carried the footer in the first place.
FOOTER = "include __DIR__ . '/../Webpage_components.php';"


def main():
    with open(SRC, encoding='utf-8') as fh:
        lines = fh.read().split('\n')

    try:
        start = next(i for i, l in enumerate(lines) if l.strip() == NAV_FIRST)
    except StopIteration:
        sys.exit('nav start not found in %s -- did the header markup change?' % SRC)
    try:
        stop = next(i for i, l in enumerate(lines) if l.startswith(NAV_LAST))
    except StopIteration:
        sys.exit('nav end not found in %s' % SRC)

    nav = lines[start:stop]
    while nav and not nav[-1].strip():
        nav.pop()
    nav = '\n'.join(nav) + '\n'
    print('nav block: %s lines %d-%d (%d bytes)'
          % (os.path.basename(SRC), start + 1, stop, len(nav)))

    n = nav.count(ANCHOR)
    if n != 1:
        sys.exit('expected exactly 1 Species Tree entry in the nav, found %d -- '
                 'the Genomics dropdown changed, update ANCHOR' % n)
    nav = nav.replace(ANCHOR, ANCHOR + ENTRY)
    print('added Core Orthologs entry after Species Tree')

    with open(TMPL, encoding='utf-8') as fh:
        tmpl = fh.read()
    if MARKER not in tmpl:
        sys.exit('marker %s missing from %s' % (MARKER, TMPL))

    out = tmpl.replace(MARKER, nav)

    # Structural check: the divs the nav is supposed to close must be opened by it,
    # and the new menu entry must actually be inside the dropdown it belongs to.
    for tag in ('templatemo_header_wrapper', 'templatemo_menu_wrapper'):
        if ('id="%s"' % tag) not in nav:
            sys.exit('nav is missing #%s -- refusing to ship a broken header' % tag)
    if out.count('href="/core/"') != 1:
        sys.exit('menu entry did not land cleanly (found %d)'
                 % out.count('href="/core/"'))
    print('nav structure ok')

    # The shared footer carries the affiliations and the visitor map.  Checked here
    # rather than left to the browser because getting it wrong is invisible from the
    # page's own source: the footer is printed by an include, so a page that has
    # dropped it still renders perfectly, just without the site's footer.
    if FOOTER not in out:
        sys.exit('the shared footer include is missing from the template -- '
                 'the page would ship with no affiliations and no visitor map')
    print('footer include present')

    with open(OUT, 'w', encoding='utf-8') as fh:
        fh.write(out)
    print('wrote %s (%d bytes)' % (OUT, os.path.getsize(OUT)))


if __name__ == '__main__':
    main()
