#!/usr/bin/env python3
"""Put the published-style cell-type panel into the as-published section.

Runs ON THE HOST against /var/www/html/CnidoSite/cell_atlas.php.  The copy
there is edited concurrently by another session, so this refuses to write
unless the file is exactly the revision the anchors were derived from, and
refuses unless every anchor matches exactly once.  A partial or repeated
application is a corrupted public page, which is worse than not deploying.

The panel itself is drawn by pipeline/08_render_pubstyle_celltype.py into
`<dataset>/umap_celltype_pub.png`; this only puts it on the page.  Supersedes
work/superseded/patch_live_pubfig_card.v1-reanalysis-render.py, which pointed
the same slot at the re-analysis render -- the wrong figure, in the wrong
style, for a section that is supposed to hold the study's own panels.
"""
import hashlib
import sys

PATH = '/var/www/html/CnidoSite/cell_atlas.php'
EXPECTED_MD5 = '837b085244fccf0dac825b751f51d637'

# --- 1: the flags, before the published section ------------------------------
A1_ANCHOR = """  $pfLegend1 = $pfLegend2 = '';
  $pfNumbered = false;
"""

A1_INSERT = """  $pfLegend1 = $pfLegend2 = '';
  $pfNumbered = false;
  // Whether any panel this page actually draws names cell types -- a different
  // question from $pfNumbered's.  A numbered figure 1 with a named figure 2
  // beside it (OARBU, AMURI, the Nematostella nervous system) leaves the reader
  // holding names already, so the panel below would be the same message twice.
  // See $pfCtPub.
  $pfNamed = false;
  // The cell-type panel this site draws for the as-published section, and the
  // file it lives in.  For every dataset whose deposited figures do not name
  // types, the study's pair is a numbered cluster plot -- so a reader comparing
  // their grouping against this dataset's cell types has nothing to compare it
  // to.  This panel is that comparison: drawn in the deposited figures' own
  // layout by pipeline/08_render_pubstyle_celltype.py, from this dataset's
  // exported coordinates and labels.  It is CnidoSite's, and the picture and
  // the caption both say so.  Null when the renderer has not run for this
  // dataset, which is how the section degrades -- no panel, never a broken
  // <img>.
  $pfCtName = 'umap_celltype_pub.png';
  $pfCtPub = null;
"""

# --- 2: compute them, inside the published-figure block ----------------------
A2_ANCHOR = """      $pfNumbered = ($pfLegend1 === 'numbers'
                     || ($pfHas2 && !$pfSame && $pfLegend2 === 'numbers'));
"""

A2_INSERT = """      $pfNumbered = ($pfLegend1 === 'numbers'
                     || ($pfHas2 && !$pfSame && $pfLegend2 === 'numbers'));
      // After $pfNumbered: a panel that names its types removes the need for
      // the one below, whatever the other panel does.
      $pfNamed = ($pfLegend1 === 'names'
                  || ($pfHas2 && !$pfSame && $pfLegend2 === 'names'));
      // The dataset id is checked rather than trusted: it reaches the
      // filesystem below, and a `pub:` id (which carries a colon and is served
      // by its twin instead) must not.
      if (!$pfNamed && count($celltypes) > 0
              && preg_match('/^[A-Za-z0-9_.-]+$/', $dataset)
              && is_file(sc_data_dir() . '/' . $dataset . '/' . $pfCtName)) {
          $pfCtPub = sc_asset_base() . rawurlencode($dataset) . '/' . $pfCtName;
      }
"""

# --- 3: the card, as a third panel in the published grid ---------------------
A3_ANCHOR = """    </div>
    <?php endif; ?>
  </div>
  <?php /* Both files present and byte-identical: the tentacle case Referee 3
"""

A3_INSERT = """    </div>
    <?php endif; ?>
    <?php /* The panel the study did not deposit, in the layout they use.
             Headed as this site's render and captioned as ours: every other
             panel in this section is the authors' own file, and a panel that
             looked like one of theirs without saying so would undo the one
             thing that makes this section honest. */ ?>
    <?php if ($pfCtPub !== null): ?>
    <div class="sc-fig-card">
      <div class="sc-fig-head">UMAP — cell types, this site's render</div>
      <div class="sc-fig-body">
        <img src="<?php echo sc_h($pfCtPub); ?>"
             alt="UMAP of this dataset's cells, coloured by <?php echo $ctNamed ? "the source study's own names for them" : "this site's own annotation of them"; ?>, drawn by CnidoSite in the published figures' layout">
      </div>
      <p class="sc-note sc-note-wide" style="padding:0 12px 12px;">
        <?php echo $ctNamed
          ? "The source study's own names for this dataset's cells"
          : "CnidoSite's own annotation of this dataset's cells"; ?>, drawn in
        the published figures' layout but from this dataset's exported
        coordinates and labels — this site's render, not the publisher's
        figure.
        <?php if ($pfOff): ?>
        It is not a cell-type version of the panel beside it: that picture is of
        a different deposit; this panel is these cells.
        <?php endif; ?>
      </p>
    </div>
    <?php endif; ?>
  </div>
  <?php /* Both files present and byte-identical: the tentacle case Referee 3
"""

# --- 4: the section's own claim about whose cells these are ------------------
A4_ANCHOR = """    against them. They are <b>their</b> cells and <b>their</b> labels, not ours.
"""

A4_INSERT = """    against them. They are <b>their</b> cells and <b>their</b> labels, not ours.
    <?php if ($pfCtPub !== null): ?>
    One panel below is not theirs: the study published no cell-type figure for
    this dataset, so this site drew one and marked it as ours.
    <?php endif; ?>
"""

# --- 5: point at it where the published figure is another deposit's ----------
A5_ANCHOR = """    out under <b>Clusters and their cell types</b> below.
"""

A5_INSERT = """    out under <b>Clusters and their cell types</b> below.
    <?php if ($pfCtPub !== null): ?>
    The cell-type panel beside the figure is this dataset's own cells, drawn by
    this site rather than taken from that earlier deposit.
    <?php endif; ?>
"""

# --- 6: and where the study's own names exist but its figure does not use them
A6_ANCHOR = """    The study's own names for this dataset's cells are in the cell-type panel
    above and under <b>Cell types in this dataset</b> below; the numbers in the
    figure are not those names and cannot be read as them.
"""

# The clause about "the panel drawn beside this figure" is inside the echo, not
# beside it.  This branch is `$pfNumbered && $ctNamed`, which OARBU and the
# Oculina pub: entry both satisfy *and* are $pfNamed -- so they get this
# sentence and no panel.  Written as plain text first, it promised a panel that
# is not on those pages; the first deploy shipped exactly that and the sweep for
# the pointer strings caught it.  A sentence about a panel has to be conditioned
# on the panel.
A6_INSERT = """    The study's own names for this dataset's cells are applied in the cell-type
    panel above<?php echo $pfCtPub !== null ? ' and in the panel drawn beside this figure' : ''; ?>,
    and listed under <b>Cell types in this dataset</b> below; the numbers in the
    figure are not those names and cannot be read as them.
"""

# --- 7: and where the names are this site's own, not the study's -------------
A7_ANCHOR = """    at. The dataset's own grouping, and what each part of it is, are under
    <b>Clusters and their cell types</b> below.
"""

A7_INSERT = """    at. The dataset's own grouping, and what each part of it is, are under
    <b>Clusters and their cell types</b> below.
    <?php if ($pfCtPub !== null): ?>
    The panel beside the figure is this site's own annotation of these cells,
    drawn for comparison. It is marked as ours and is not a key the study
    published.
    <?php endif; ?>
"""

EDITS = [('1', A1_ANCHOR, A1_INSERT),
         ('2', A2_ANCHOR, A2_INSERT),
         ('3', A3_ANCHOR, A3_INSERT),
         ('4', A4_ANCHOR, A4_INSERT),
         ('5', A5_ANCHOR, A5_INSERT),
         ('6', A6_ANCHOR, A6_INSERT),
         ('7', A7_ANCHOR, A7_INSERT)]

MARKERS = ('$pfCtName = ', "UMAP — cell types, this site's render")


def main():
    with open(PATH, 'r', encoding='utf-8') as fh:
        src = fh.read()

    got = hashlib.md5(src.encode('utf-8')).hexdigest()
    if got != EXPECTED_MD5:
        sys.exit('ABORT: live cell_atlas.php is %s, expected %s -- the file '
                 'changed under us; re-derive the anchors.' % (got, EXPECTED_MD5))

    for marker in MARKERS:
        if src.count(marker):
            sys.exit('ABORT: %r already present -- already applied?' % marker)

    out = src
    for name, anchor, insert in EDITS:
        n = out.count(anchor)
        if n != 1:
            sys.exit('ABORT: anchor %s matches %d times, need exactly 1.'
                     % (name, n))
        out = out.replace(anchor, insert, 1)

    with open(PATH, 'w', encoding='utf-8') as fh:
        fh.write(out)
    print('applied %d edits' % len(EDITS))
    print('new md5 %s' % hashlib.md5(out.encode('utf-8')).hexdigest())


if __name__ == '__main__':
    main()
