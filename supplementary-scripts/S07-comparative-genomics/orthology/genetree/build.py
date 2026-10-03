#!/usr/bin/env python3
"""Splice the site's header/nav block into the gene-tree page.

The nav (header wrapper + menu) is 110 lines of hand-maintained HTML that every page on
CnidoSite carries a copy of -- phylotree/index.php inlines its own rather than printing a
$header variable.  Copying it a third time by hand guarantees it drifts the next time a
menu entry is added, so this lifts the block straight out of the deployed page and pastes
it into the template, the same way work/assemble.py handles the gene-family pages.

Source of truth is php_orig/phylotree_index.php: a verbatim copy pulled off the server, so
the nav here is exactly what the live site serves.

    python3 build.py            -> index.php

WARNING -- this script does NOT know about the page's header.  The deployed page carries
a `.gt-hero` block (the OG number, the four facts, the "Family page" button) that exists
in no build input: not in index_template.php, not in php_orig/, not in any CSS file here.
It was added straight to the deployed file on the server by a later session.  This script
still runs clean and still writes an index.php -- but that file has no header, so
deploying it silently deletes the whole block and the page starts at the tree controls.
Same shape as the missing footer on /core/.  Before building, diff your output against
index.live.php (the pulled source of truth) and reconcile the header first.
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

    # Everything between the header wrapper and the content wrapper, minus the trailing
    # blank line that separates them.
    nav = lines[start:stop]
    while nav and not nav[-1].strip():
        nav.pop()
    nav = '\n'.join(nav)
    print('nav block: %s lines %d-%d (%d bytes)'
          % (os.path.basename(SRC), start + 1, stop, len(nav)))

    with open(TMPL, encoding='utf-8') as fh:
        tmpl = fh.read()
    if MARKER not in tmpl:
        sys.exit('marker %s missing from %s' % (MARKER, TMPL))

    with open(OUT, 'w', encoding='utf-8') as fh:
        fh.write(tmpl.replace(MARKER, nav))
    print('wrote %s (%d bytes)' % (OUT, os.path.getsize(OUT)))

    # Cheap structural check: the two divs the nav closes must both be opened by it.
    for tag in ('templatemo_header_wrapper', 'templatemo_menu_wrapper'):
        if ('id="%s"' % tag) not in nav:
            sys.exit('nav is missing #%s -- refusing to ship a broken header' % tag)
    print('nav structure ok')


if __name__ == '__main__':
    main()
