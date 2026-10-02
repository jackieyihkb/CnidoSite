<?php
/**
 * cell_atlas.php -- interactive cell atlas.
 *
 * Replaces the previous implementation, which showed two pre-rendered PNGs per
 * tissue.  Referee 1 objected that "scRNA-seq data are presented only as
 * static, non-interactive images"; Referee 3 objected that for Nematostella
 * tentacle the two panels were identical ("two identical UMAP visualisations
 * with no information about the difference; the clusters are not identified by
 * cell type").  Both were literally true of the old page.
 *
 * The viewer is entirely client-side and reads pre-computed binary assets from
 * /singlecell_data/<dataset_id>/, so nothing new runs on the server.
 *
 * Deep links (also what cell_marker.php and gene_exp.php link into):
 *     cell_atlas.php?dataset=ID&type=<cell type>
 *     cell_atlas.php?dataset=ID&gene=<gene>&mode=gene
 */
require_once __DIR__ . '/sc_common.php';
/* HVULG_siebert_atlas publishes its cell types as abbreviations.  Its key is
   not printed as a section of this page: it goes to the viewer, which reads it
   beside the names it explains.  See includes/sc_celltype_key.php. */
require_once __DIR__ . '/includes/sc_celltype_key.php';

$conn = sc_conn();
$requested = isset($_GET['dataset']) ? trim($_GET['dataset']) : '';
$atlas = sc_atlas($conn);
$published = sc_published_entries($conn);

/*
 * A selection is one of two things, and the page has to know which before it
 * can draw anything: a dataset we re-analysed (interactive viewer), or one of
 * the source study's published figures (static PNG).  Only two datasets were
 * re-run, so most selections are the latter -- offering only the two would drop
 * four of the six species the atlas has always covered.
 */
/*
 * The site's own menu and the species portal link here as `?species=...`, not
 * `?dataset=...`.  Honour that before any default; sc_requested_species()
 * explains why the "no data for that species" case must not fall through.
 */
$sp = sc_requested_species($conn);
$spMiss = false;
if ($requested === '' && $sp !== null) {
    if ($sp['ids']) {
        $requested = $sp['ids'][0];
    } else {
        $spMiss = true;
    }
}
// A species can have several datasets (Nematostella vectensis has five).  The
// first is opened, but the page says so rather than letting the choice look
// like the only one.
$spMany = ($sp !== null && !$spMiss && count($sp['ids']) > 1);

$pubEntry = null;
if ($spMiss) {
    // Nothing for the requested species.  $dataset stays empty so the notice
    // below is what renders: falling through to sc_default_dataset() would
    // answer a Hydra link with Acropora and say nothing about it.
    $dataset = '';
} elseif (sc_is_published_id($requested) && isset($published[$requested])) {
    // A published figure whose dataset we have since re-analysed opens the
    // re-analysis, not the PNG.  The `pub:` id is a name for a dataset, and
    // that dataset is in the interactive section now; its page shows the
    // source study's own panels alongside ours, so nothing is lost by not
    // rendering a static page for it.  A figure with no re-analysis behind it
    // still renders as the static page below.
    $pubTwin = sc_interactive_twin($conn, $published[$requested]);
    if ($pubTwin !== '' && isset($atlas[$pubTwin])) {
        $dataset = $pubTwin;
    } else {
        $dataset = $requested;
        $pubEntry = $published[$requested];
    }
} else {
    $dataset = sc_default_dataset($conn, $requested);
    if ($dataset === '' && $published) {
        // No viewer data loaded at all -- still show something rather than
        // the "not published yet" notice.
        $keys = array_keys($published);
        $dataset = $keys[0];
        $pubEntry = $published[$dataset];
    } elseif ($dataset !== '' && isset($published[$dataset])) {
        // A dataset id can never collide with a `pub:` id; this only guards
        // against a caller passing a published key as `dataset`.
        $pubEntry = $published[$dataset];
    }
}
$meta = ($pubEntry === null && $dataset !== '' && isset($atlas[$dataset]))
    ? $atlas[$dataset] : null;
$qc = $meta ? sc_qc($conn, $dataset) : null;
$celltypes = $meta ? sc_celltypes($conn, $dataset) : array();

/* True when the names shown for this dataset are the source study's own, rather
 * than one this site assigned.  The published-figure section points a reader at
 * them when the published figure itself carries no names. */
$ctNamed = ($meta !== null && count($celltypes) > 0
            && isset($meta['annotation_provenance'])
            && $meta['annotation_provenance'] === 'published');

/* Is this atlas a re-analysis by CnidoSite, or the source study's own object
 * imported as it was published?
 *
 * The two look identical in the viewer -- same files, same contract -- so the
 * page has to say which one the reader is looking at: every other dataset here
 * has our UMAP coordinates and our Leiden clusters with the study's labels
 * transferred onto them, and calling that "the authors' figure" would be as
 * wrong as the reverse.  `embedding_source` records where the coordinates came
 * from; a dataset whose row predates the column reads as ours, which is what
 * those datasets are. */
$imported = $meta !== null && trim((string)(isset($meta['embedding_source'])
    ? $meta['embedding_source'] : '')) !== '';

sc_header('Cell Atlas', 'cell_atlas.php',
  'Interactive UMAP atlas of a cnidarian single-cell dataset: color cells by cell type, cluster, '
  . 'library, QC metric or gene, and click any cell to read its annotation.');
?>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Cell Atlas</b></legend>

<?php if ($spMany): ?>
  <div class="sc-note sc-note-wide" style="margin-top:12px;">
    Showing the first of <?php echo count($sp['ids']); ?> datasets held for
    <b><?php echo sc_h($sp['species']); ?></b>; the others are in the
    <b>Dataset</b> list below.
  </div>
<?php endif; ?>

<?php if ($spMiss): ?>

  <?php sc_species_missing($conn, $sp['species'], 'cell_atlas.php'); ?>
  <p class="sc-note sc-note-wide">Or open a dataset directly:</p>
  <?php sc_dataset_picker($conn, $dataset, 'cell_atlas.php', array(), true); ?>

<?php elseif ($pubEntry):
    $pic1 = $pubEntry['pic1'];
    $pic2 = $pubEntry['pic2'];
    $f1 = sc_images_dir() . '/' . $pic1 . '.png';
    $f2 = sc_images_dir() . '/' . $pic2 . '.png';
    $has1 = is_file($f1);
    $has2 = is_file($f2);
    // Two files can both exist and hold the same picture.  Referee 3 point 10g
    // was exactly that: for the Nematostella tentacle the page drew two
    // identical UMAPs and never said so.  Comparing the bytes lets the page
    // state it plainly instead of implying a second, different view.
    $identical = ($has1 && $has2 && md5_file($f1) === md5_file($f2));
    $pubTypes = sc_published_celltypes($conn, $pubEntry['abbr1'], $pubEntry['tissue']);
    $pubNamedOnly = sc_named_only($pubTypes);
    $twin = sc_interactive_twin($conn, $pubEntry);
    $spName = $pubEntry['species'];
    $tissue = $pubEntry['tissue'];
?>

  <p class="sc-note sc-note-wide">
    This dataset is shown as the figure published with the source study. It is a
    <b>static image</b>: the cells behind it were not deposited in a form we
    could re-analyse, so this projection cannot be recoloured, subset or
    queried. Datasets we did re-analyse open in the interactive viewer instead
    — see <a href="sn_data.php">Single-cell Data</a> for which is which.
  </p>

  <?php sc_dataset_picker($conn, $dataset, 'cell_atlas.php', array(), true); ?>

  <?php if (!$has1): ?>

  <div class="sc-callout sc-callout-warn">
    <b>No atlas image is available for <i><?php echo sc_h($spName); ?></i> — <?php echo sc_h($tissue); ?>.</b>
    <div style="margin-top:6px">
      This dataset is listed in <a href="sn_data.php">Single-cell Data</a> but its
      UMAP embedding was not deposited in CnidoSite.
    </div>
  </div>

  <?php else: ?>

  <?php /* The headings name the figure's position, not its content.  Naming the
           content looked reasonable -- the site's filenames pair a
           cluster-coloured figure with a cell-type one -- but it is not
           reliably true: for NVECT_NervousSystem the second figure is titled "by
           Cell Types" while its legend is still the numeric cluster legend
           0..16, so describing it as coloured by annotated cell type would put a
           claim on the page that the figure itself contradicts.  What the page
           can verify is that two files were deposited and whether they are the
           same picture; what each picture shows is left to the picture. */ ?>
  <div class="sc-fig-grid">
    <div class="sc-fig-card">
      <div class="sc-fig-head">UMAP — figure 1, as published</div>
      <div class="sc-fig-body">
        <img src="<?php echo sc_h(sc_images_url() . $pic1); ?>.png"
             alt="The first UMAP figure published for <?php echo sc_h($spName); ?> (<?php echo sc_h($tissue); ?>)">
      </div>
    </div>

    <?php /* $identical alone is not enough to decide: when the second file is
             simply absent it is also false.  Requiring both conditions keeps a
             dataset with no second figure from rendering a broken <img>. */ ?>
    <?php if ($has2 && !$identical): ?>
    <div class="sc-fig-card">
      <div class="sc-fig-head">UMAP — figure 2, as published</div>
      <div class="sc-fig-body">
        <img src="<?php echo sc_h(sc_images_url() . $pic2); ?>.png"
             alt="The second UMAP figure published for <?php echo sc_h($spName); ?> (<?php echo sc_h($tissue); ?>)">
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($identical): ?>
  <div class="sc-callout sc-callout-info">
    <b>Only one UMAP is shown for this dataset.</b>
    For <i><?php echo sc_h($spName); ?></i> — <?php echo sc_h($tissue); ?>, the
    source study deposited two files, but they are byte-identical: the file
    previously displayed as a second panel is the same picture as the first, so
    it is no longer shown twice.
  </div>
  <?php elseif (!$has2): ?>
  <div class="sc-callout sc-callout-info">
    <b>Only one UMAP is shown for this dataset.</b>
    For <i><?php echo sc_h($spName); ?></i> — <?php echo sc_h($tissue); ?>, no
    second figure has been deposited in CnidoSite.
  </div>
  <?php endif; ?>

  <?php endif; /* $has1 */ ?>

  <?php if ($twin !== ''): ?>
  <div class="sc-callout sc-callout-info">
    <b>An interactive version of this dataset is available.</b>
    We re-analysed the same tissue and the viewer can recolour it by cell type,
    cluster, library and QC metric, or by the expression of a single gene.
    <a href="cell_atlas.php?dataset=<?php echo urlencode($twin); ?>">Open the interactive atlas &rarr;</a>
  </div>
  <?php endif; ?>

  <?php if ($pubTypes): ?>
  <h3>Cell types in this dataset</h3>
  <p class="sc-note sc-note-wide">
    <?php echo count($pubTypes); ?>
    <?php echo $pubNamedOnly ? 'clusters' : 'annotated cell types'; ?>,
    from the marker table <code><?php echo sc_h($pubEntry['abbr1']); ?>_cellmarker</code>.
    Click one to see its marker genes.
  </p>
  <div class="sc-chips">
    <?php foreach ($pubTypes as $ct):
        $link = 'cell_marker.php?dataset=' . urlencode($dataset)
              . '&cell_type=' . urlencode($ct[0]); ?>
      <a class="sc-chip" href="<?php echo sc_h($link); ?>"
         title="Open the marker genes for this cell type"><?php echo sc_h($ct[0]); ?><em><?php echo (int)$ct[1]; ?></em></a>
    <?php endforeach; ?>
  </div>
  <?php if ($pubNamedOnly): ?>
  <div class="sc-callout sc-callout-info">
    This dataset's clusters have not been assigned cell-type names in the source
    study, so they are listed as <i>cluster N</i>. The numbers in each chip are
    the marker genes recorded for it.
  </div>
  <?php endif; ?>
  <?php endif; ?>

<?php elseif (!$meta): ?>

  <div class="sc-empty">
    <b>The interactive atlas has not been published yet.</b><br>
    Upload the contents of <code>web/singlecell_data/</code> and
    <code>web/viewer/</code> to the site root, then load
    <code>sql/singlecell_schema.sql</code> and <code>sql/load_singlecell.sql</code>
    into the <code>cnidaria</code> database. Datasets will appear here
    automatically.
  </div>

<?php else: ?>

  <p class="sc-note sc-note-wide">
    Every cell is one point. Drag to pan, scroll to zoom, shift-drag to select a
    region; click any group in the legend to isolate it. Use
    <b>Colour cells by</b> to switch between cell type, cluster, library,
    quality-control metric and the expression of a single gene.
  </p>

  <?php sc_dataset_picker($conn, $dataset, 'cell_atlas.php', array(), true); ?>

  <div id="atlas"></div>
  <div id="atlas-status" class="sc-note sc-note-wide"></div>

  <script src="./viewer/cellatlas.js?v=<?php echo (int)@filemtime(__DIR__ . '/viewer/cellatlas.js'); ?>"></script>
  <script type="text/javascript">
  (function () {
    "use strict";
    var mount = document.getElementById("atlas");
    var status = document.getElementById("atlas-status");
    window.addEventListener("error", function (e) {
      status.textContent = "Error: " + (e.message || "unknown");
    });
    var viewer = new CnidoAtlas({
      mount: mount,
      // absolute, so the URL is stable no matter which page hosts the viewer
      dataUrl: "<?php echo sc_h(sc_asset_base() . $dataset); ?>/",
      datasetId: "<?php echo sc_h($dataset); ?>",
      /* What each cell-type name stands for, when the dataset publishes them
         as abbreviations.  Handed to the viewer rather than printed above,
         because the Groups list is where a reader meets `i_n_ec4` and has
         nothing to decode it with.  '' for every other dataset. */
      typeKey: <?php echo sc_celltype_key_json($dataset) ?: 'null'; ?>
    });
    viewer.load().then(function () {
      viewer.applyUrlState();          // ?type=…&gene=…&mode=…
    }).catch(function (e) {
      status.textContent = "Could not load this atlas: " + e.message;
    });
  })();
  </script>

  <?php /* The re-analysis as a figure.  The viewer above is the same data, but a
           figure is what a reader can print, cite, or hold next to the source
           study's published panels -- and it is what makes the two sets of
           UMAPs on this page distinguishable: those are the study's, these are
           ours.  Rendered by pipeline/07_render_umap.py from the same exported
           files the viewer reads.  The whole block is skipped when the files
           have not been uploaded, so a dataset without figures degrades to no
           figure rather than to a broken <img>. */ ?>
  <?php $figs = sc_dataset_figures($dataset); ?>
  <?php if ($figs): ?>
  <h3 style="margin-top:26px;">UMAP — <?php echo $imported
        ? 'as published by the source study' : 'this re-analysis'; ?></h3>
  <p class="sc-note sc-note-wide">
    Drawn from the same exported coordinates and labels the interactive atlas
    above uses, so the figure and the atlas cannot disagree.
    <?php echo number_format((int)$meta['n_cells_final']); ?> cells.
    <?php if ($imported): ?>
    For this dataset those coordinates and labels are the source study's own,
    so this panel reproduces their published embedding rather than one computed
    here — see the provenance table below.
    <?php endif; ?>
  </p>
  <div class="sc-fig-grid">
    <?php foreach ($figs as $f):
        $isCT = ($f['key'] === 'celltype'); ?>
    <div class="sc-fig-card">
      <div class="sc-fig-head"><?php
        echo $isCT
          ? 'Coloured by cell type (' . (int)$meta['n_cell_types'] . ')'
          : ($imported
             ? 'Coloured by published cell state (' . (int)$meta['n_clusters'] . ')'
             : 'Coloured by Leiden cluster (' . (int)$meta['n_clusters'] . ')');
      ?></div>
      <div class="sc-fig-body">
        <img src="<?php echo sc_h($f['url']); ?>"
             alt="<?php echo sc_h(
                 ($imported
                    ? 'UMAP of ' . $dataset . ' as published by the source study'
                    : 'UMAP of the re-analysed ' . $dataset) . ', coloured by '
                 . ($isCT
                    ? 'annotated cell type, with a legend naming each type'
                    : ($imported
                       ? 'published cell state, each labelled with its number'
                       : 'Leiden cluster, each labelled with its number'))); ?>">
      </div>
      <?php /* A UMAP is an all-pairs form: any two types can end up adjacent,
               so past a handful of categories no palette separates them and
               colour has to stop being the carrier of identity.  Both panels
               therefore carry a second channel, and the captions say which. */ ?>
      <p class="sc-note sc-note-wide" style="padding:0 12px 12px;">
        <?php if ($isCT): ?>
        The legend in the panel names every type and its cell count.
        <?php if ((int)$meta['n_cell_types'] > 8): ?>
        With <?php echo (int)$meta['n_cell_types']; ?> types, colour alone no
        longer separates them — read the legend, not the hue.
        <?php endif; ?>
        <?php else: ?>
        Each cluster's number is drawn at the middle of the cluster it names, so
        the grouping can be read without relying on colour.
        <?php if ($imported): ?>
        These are the source study's own cell states, not a clustering run by
        CnidoSite.
        <?php elseif ($meta['annotation_provenance'] === 'cluster_only'): ?>
        These are Leiden clusters, not named cell types.
        <?php else: ?>
        These are the clusters the cell-type annotation was built on.
        <?php endif; ?>
        <?php endif; ?>
      </p>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
  /* The source study's own panels, on the same page as our re-analysis.
   *
   * These datasets used to be separate static entries in the picker.  They are
   * not any more -- the re-analysis is the entry -- but the published figure is
   * still the thing a reader may want to hold an interpretation against, and it
   * is the only view of this dataset that is the authors' own.  Dropping it
   * along with the picker option would have made "we re-analysed it" mean "we
   * replaced it", which is not what happened.
   *
   * Which figure belongs to this dataset is `singlecell_atlas.published_figure`
   * -- the filename stem -- so it is a lookup, not a guess by species.  A
   * dataset with none (most of them) skips this block entirely.
   */
  $pubFigStem = isset($meta['published_figure'])
      ? trim((string)$meta['published_figure']) : '';
  $pubFig = null;
  if ($pubFigStem !== '') {
      foreach ($published as $e) {
          if ($e['stem'] === $pubFigStem) {
              $pubFig = $e;
              break;
          }
      }
  }
  $pf1 = $pf2 = '';
  $pfHas1 = $pfHas2 = false;
  $pfSame = false;
  // Whether this section is drawn at all.  A published deposit earns a section
  // only when it hands the reader both halves of the comparison: a figure that
  // groups the cells, and a UMAP that says what the cells are.  A deposit that
  // is one named picture and nothing else (AMILL, OPATA, SPIST -- figure 1 and
  // figure 2 are the same article screenshot) leaves a reader with one image
  // and no cluster figure to read it against, and a lone numbered plot with no
  // cell-type panel beside it leaves them with numbers and no names: neither is
  // a section, so neither is shown.  Set below, once the panels actually drawn
  // are known; until then, nothing.
  $pfShows = false;
  // How many groups the numbered legend lists, and whether that count settles
  // that the panel is *not* a figure of this dataset.  Both stay false/0 for a
  // file nobody has counted, and the page then says nothing about the count --
  // see sc_pub_figure_groups() and sc_pub_figure_off_dataset().
  $pfGroups = 0;
  $pfOff = false;
  // What each figure's own legend lists ('names', 'numbers' or '' for
  // unknown), so this section can say which it is instead of claiming cell-type
  // names for a figure that has none.  Always the picture, never the dataset:
  // two datasets here ship one numbered figure and one named one.  See
  // sc_pub_figure_legend().
  $pfLegend1 = $pfLegend2 = '';
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
  if ($pubFig !== null) {
      $pf1 = $pubFig['pic1'];
      $pf2 = $pubFig['pic2'];
      $pfHas1 = is_file(sc_images_dir() . '/' . $pf1 . '.png');
      $pfHas2 = is_file(sc_images_dir() . '/' . $pf2 . '.png');
      $pfSame = ($pfHas1 && $pfHas2
                 && md5_file(sc_images_dir() . '/' . $pf1 . '.png')
                    === md5_file(sc_images_dir() . '/' . $pf2 . '.png'));
      // After $pfSame, because whether figure 2 is drawn at all is part of
      // whether its legend is one a reader will see.
      $pfLegend1 = sc_pub_figure_legend($pf1);
      $pfLegend2 = sc_pub_figure_legend($pf2);
      // True when a figure this page actually draws numbers its groups.
      $pfNumbered = ($pfLegend1 === 'numbers'
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
      // Both halves, and only then the section -- see the comment at $pfShows.
      // $pfNumbered and $pfNamed are about the panels drawn; $pfCtPub is the
      // panel this site draws when the study's own figures name nothing, and
      // counts here as the cell-type half because it is these cells.  A
      // dataset can also be withheld outright, which is an editorial decision
      // and not a property of its deposit -- see sc_pub_section_withheld().
      $pfShows = ($pfNumbered && ($pfNamed || $pfCtPub !== null)
                  && !sc_pub_section_withheld($dataset));
      // After $pfNumbered: a count is only worth holding against the dataset
      // when the page is actually showing a panel that numbers its groups.
      $pfGroups = sc_pub_figure_groups($pf1);
      $pfOff = ($pfNumbered && $meta !== null && sc_pub_figure_off_dataset(
          $pubFigStem, $pfGroups,
          isset($meta['n_clusters']) ? $meta['n_clusters'] : 0,
          isset($meta['n_cell_types']) ? $meta['n_cell_types'] : 0));
  }
  /* The cluster-to-cell-type key, read back out of this dataset's own exported
   * labels -- the file the atlas colours cells from -- rather than out of any
   * table kept here.  It is what lets a numbered panel's groups be named when
   * the study's picture does not name them, and null (no key, say nothing)
   * when the assets are missing or do not decode.  See sc_cluster_breakdown(). */
  $clusterKey = ($meta !== null) ? sc_cluster_breakdown($dataset) : null;
  ?>
  <?php if ($pubFig !== null && $pfHas1 && $pfShows): ?>
  <h3 style="margin-top:26px;">UMAP — as published (source study)</h3>
  <p class="sc-note sc-note-wide">
    <i><?php echo sc_h($pubFig['species']); ?></i> — <?php echo sc_h(sc_pub_tissue($pubFig['tissue'])); ?>.
    The source study's own figures, kept here so the
    <?php echo $imported ? 'atlas' : 're-analysis'; ?> above can be read
    against them. They are <b>their</b> cells and <b>their</b> labels, not ours.
    <?php if ($pfCtPub !== null): ?>
    One panel below is not theirs: the study published no cell-type figure for
    this dataset, so this site drew one and marked it as ours.
    <?php endif; ?>
    <?php if ($pfOff): ?>
    <b>This one is not drawn from the cells on this page.</b> Its legend numbers
    <?php echo (int)$pfGroups; ?> groups, where this dataset has
    <?php echo (int)$meta['n_clusters']; ?> clusters in
    <?php echo (int)$meta['n_cell_types']; ?> cell types — so its numbers are
    neither this dataset's clusters nor its cell types, and no cell type can be
    read off them. The picture goes with an earlier whole-organism deposit; the
    cells in this dataset came from a different one, and what they are is set
    out under <b>Cell types in this dataset</b> below.
    <?php if ($pfCtPub !== null): ?>
    The cell-type panel beside the figure is this dataset's own cells, drawn by
    this site rather than taken from that earlier deposit.
    <?php endif; ?>
    <?php elseif ($pfNumbered): ?>
    This one does not name them: its legend numbers the groups rather than
    naming cell types.<?php if ($pfGroups > 0): ?>
    It lists <?php echo (int)$pfGroups; ?> groups.<?php endif; ?>
    <?php if ($ctNamed): ?>
    The study's own names for this dataset's cells are applied in the cell-type
    panel above<?php echo $pfCtPub !== null ? ' and in the panel drawn beside this figure' : ''; ?>,
    and listed under <b>Cell types in this dataset</b> below; the numbers in the
    figure are not those names and cannot be read as them.
    <?php else: ?>
    CnidoSite holds no published key that turns these numbers into cell-type
    names for this dataset, so they are left as numbers here rather than guessed
    at. The dataset's own grouping, and what each part of it is, are under
    <b>Cell types in this dataset</b> below.
    <?php if ($pfCtPub !== null): ?>
    The panel beside the figure is this site's own annotation of these cells,
    drawn for comparison. It is marked as ours and is not a key the study
    published.
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
  </p>
  <div class="sc-fig-grid">
    <div class="sc-fig-card">
      <div class="sc-fig-head">UMAP — figure 1, as published</div>
      <div class="sc-fig-body">
        <img src="<?php echo sc_h(sc_images_url() . $pf1); ?>.png"
             alt="The first UMAP figure published for <?php echo sc_h($pubFig['species']); ?> (<?php echo sc_h(sc_pub_tissue($pubFig['tissue'])); ?>)">
      </div>
      <?php if ($pfLegend1 !== ''): ?>
      <p class="sc-note sc-note-wide" style="padding:0 12px 12px;">
        <?php if ($pfLegend1 === 'names'): ?>
        The legend names cell types, in the study's own words.
        <?php else: ?>
        The legend lists numbers, not cell-type names — the title printed
        inside the figure is the publisher's, not a description of the legend.
        <?php endif; ?>
      </p>
      <?php endif; ?>
    </div>
    <?php if ($pfHas2 && !$pfSame): ?>
    <div class="sc-fig-card">
      <div class="sc-fig-head">UMAP — figure 2, as published</div>
      <div class="sc-fig-body">
        <img src="<?php echo sc_h(sc_images_url() . $pf2); ?>.png"
             alt="The second UMAP figure published for <?php echo sc_h($pubFig['species']); ?> (<?php echo sc_h(sc_pub_tissue($pubFig['tissue'])); ?>)">
      </div>
      <?php if ($pfLegend2 !== ''): ?>
      <p class="sc-note sc-note-wide" style="padding:0 12px 12px;">
        <?php if ($pfLegend2 === 'names'): ?>
        The legend names cell types, in the study's own words.
        <?php else: ?>
        The legend lists numbers, not cell-type names — the title printed
        inside the figure is the publisher's, not a description of the legend.
        <?php endif; ?>
      </p>
      <?php endif; ?>
    </div>
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
           raised.  Saying it once, here, is cheaper than letting a reader
           notice two panels and wonder which is which. */ ?>
  <?php if ($pfSame): ?>
  <div class="sc-callout sc-callout-info">
    <b>The source study's two deposited figures are the same picture.</b>
    Both files exist and are byte-identical, so only one is shown.
  </div>
  <?php elseif (!$pfHas2): ?>
  <div class="sc-callout sc-callout-info">
    <b>Only one published UMAP is shown for this dataset.</b>
    No second figure has been deposited in CnidoSite, so there is no second
    panel to show.
  </div>
  <?php endif; ?>
  <?php /* Reached only when the file itself is absent ($pfShows can be false
           with the file present -- that is the section being left out, and it
           says nothing rather than reporting a figure that is not missing). */ ?>
  <?php elseif ($pubFig !== null && !$pfHas1): ?>
  <div class="sc-callout sc-callout-info">
    <b>The source study's published figure for this dataset is not on the site.</b>
    <code><?php echo sc_h($pf1); ?>.png</code> is recorded as the published
    figure for this dataset but is not in the images directory, so there is
    nothing to show here.
  </div>
  <?php endif; ?>

  <?php
  /* The cluster-to-cell-type key, folded into the cell-type table below.
   *
   * A published legend that numbers its groups leaves the reader holding twelve
   * or twenty-eight numbers with nothing to call them -- the complaint this
   * answers, and still does: the numbers are the cluster labels, and they now
   * sit on the row of the cell type they belong to.
   *
   * Two tables used to stand here: "Clusters and their cell types" (one row per
   * cluster, off the dataset's own exported labels) and "Cell types in this
   * dataset" (one row per type, off singlecell_celltype).  They were the same
   * cells counted two ways -- summing a cluster's per-type counts reproduces
   * that type's n_cells exactly, on all 17 datasets on the site -- so one
   * composition was shown twice, and the reader had to join the two in their
   * head to answer "which clusters is this type in".  One table now.
   *
   * The key is read out of the data rather than guessed at: sc_cluster_breakdown()
   * decodes cellmeta.bin, the same file the atlas above colours cells from, so
   * the table and the atlas cannot disagree.  Where the clusters are single-type
   * (Nematostella whole-adult: 35 clusters, one published type each) a cluster
   * carries one number; where they are not, the same cluster is listed under
   * each type it holds, with that type's cells in it, so a heterogeneous cluster
   * cannot read as a named one.
   *
   * $ckShow asks only whether the pairing decoded.  It used to be narrower --
   * the page also had to be showing a numbered published panel -- because the
   * column existed to name that panel's numbers and nothing else.  On every cell
   * atlas now (2026-10-02): which clusters a type sits in is what the row is
   * for, whether or not a panel above needs its numbers read.  All 17 datasets
   * decode a key, so the column is there on all 17.  A `cluster_only` dataset is
   * the one case still excluded: its "cell types" are the clusters themselves,
   * so the column would only repeat the row label back (no dataset is one today,
   * but the rule is what keeps a future one from being drawn wrongly).
   *
   * What came with the narrower condition was the sentence comparing the panel's
   * numbers with these clusters; that one is still guarded by $pfShows, so on a
   * page with no numbered panel it simply does not appear -- including a
   * withheld dataset (sc_pub_section_withheld), where the section above is gone
   * and there is nothing to point at.  The column itself stays there whatever
   * the figure does: it is this site's reading of the dataset's own exported
   * labels, not part of the study's figure. */
  $ckSrc = trim((string)(isset($meta['cluster_source']) ? $meta['cluster_source'] : ''));
  $ckShow = ($clusterKey !== null && $meta !== null
             && $meta['annotation_provenance'] !== 'cluster_only');
  $ckLabels = array();
  $ckMixed  = 0;
  $ckByType = array();
  if ($ckShow) {
      $ckLabels = $clusterKey['labels'];
      $ckMixed  = (int)$clusterKey['mixed'];
      /* 类型 -> array(cluster => 该类型在这个 cluster 的细胞数)。$ckLabels 出
         sc_cluster_breakdown() 时已按号码排好（C1、C2…），foreach 出来就是面板
         上从上往下的顺序，不必再排一次。 */
      foreach ($ckLabels as $lab) {
          foreach ($clusterKey['types'][$lab] as $t => $n) {
              $ckByType[$t][$lab] = $n;
          }
      }
  }
  ?>

  <h3 style="margin-top:26px;">Cell types in this dataset</h3>
  <p class="sc-note sc-note-wide">
    Click a cell type to isolate it in the atlas above.
    The bar beside each percentage is drawn to scale: a cell type at
    2.6&nbsp;% fills 2.6&nbsp;% of the track, so the rows can be ranked by eye
    without reading the numbers.
    <?php if ($ckShow): ?>
    The clusters beside each type are the ones carrying it, with that type's
    cells in each — read from this dataset's own exported labels, the same file
    the atlas is drawn from.
    <?php if ($ckMixed > 0): ?>
    <?php /* 全部 cluster 都混合时（HVULG、NVECT_bodywall）写「42 of the 42 here」
           是废话，改成另起一句。 */ ?>
    <?php if ($ckMixed === count($ckLabels)): ?>
    Every cluster here holds more than one type, so each is listed under all of
    the types it carries.
    <?php else: ?>
    Where a cluster holds more than one type — <?php echo $ckMixed; ?> of the
    <?php echo count($ckLabels); ?> here — it is listed under each of them.
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($ckSrc !== ''): ?>
    These are the source study's own clusters, not a clustering run by CnidoSite.
    <?php elseif ($meta['annotation_provenance'] === 'published'): ?>
    The clusters are CnidoSite's; the names are the source study's, carried over
    cell by cell onto them.
    <?php else: ?>
    The clusters are CnidoSite's and the names were derived here from marker
    panels — the provenance table below says what each one rests on.
    <?php endif; ?>
    <?php if ($pfShows && $pfGroups > 0 && $pfGroups !== count($ckLabels)): ?>
    <b>The numbers in the panel above are not these:</b> the published legend
    numbers <?php echo (int)$pfGroups; ?> groups where this dataset has
    <?php echo count($ckLabels); ?> clusters, so a number cannot be carried
    across from one list to the other.
    <?php endif; ?>
    <?php endif; ?>
  </p>

  <?php if ($celltypes):
      /* 合计行用的三个数先算出来：n_cells 逐行相加 == QC 里的 n_cells_final，
         pct 相加 == 100.00%（12 个数据集逐个核过，见 sn_data.php 的 QC 列）。
         所以这里能直接给 100%，不需要另取一次数。 */
      $sum_cells = 0;
      $sum_pct   = 0.0;
      foreach ($celltypes as $ct) {
          $sum_cells += (int)$ct['n_cells'];
          $sum_pct   += (float)$ct['pct_cells'];
      } ?>
  <div class="sc-table-wrap">
  <table class="sc-table sc-cells<?php echo $ckShow ? ' sc-clu' : ''; ?>">
    <tr>
      <th>Cell type</th>
      <th class="num">Cells</th>
      <th class="num pct">Percent</th>
      <?php if ($ckShow): ?><th>Clusters</th><?php endif; ?>
      <th>Markers</th>
    </tr>
    <?php foreach ($celltypes as $ct):
        $link = 'cell_atlas.php?dataset=' . urlencode($dataset)
              . '&type=' . urlencode($ct['cell_type']);
        $mk = 'cell_marker.php?dataset=' . urlencode($dataset)
            . '&cell_type=' . urlencode($ct['cell_type']);
        /* 条长就是百分比本身，夹在 0–100 之间防止脏数据把条画到格外面去。 */
        $bar = max(0.0, min(100.0, (float)$ct['pct_cells']));
        /* 这个类型在哪些 cluster 里，按号码从小到大。类型名两边写法逐个核过：
           singlecell_celltype 的名字与 cellmeta.bin 的标签 17 个数据集全部对得上，
           所以这里取不到就是真的没有，不是名字写法不同。 */
        $chips = ($ckShow && isset($ckByType[$ct['cell_type']]))
               ? $ckByType[$ct['cell_type']] : array(); ?>
    <tr>
      <td><a href="<?php echo sc_h($link); ?>"><?php echo sc_h($ct['cell_type']); ?></a></td>
      <td class="num"><?php echo number_format((int)$ct['n_cells']); ?></td>
      <td class="num pct"><div class="sc-share-wrap"><span class="sc-share"><i style="width:<?php
          echo number_format($bar, 3, '.', ''); ?>%"></i></span><span class="sc-share-v"><?php
          echo number_format((float)$ct['pct_cells'], 2); ?>%</span></div></td>
      <?php if ($ckShow): ?>
      <td class="sc-clu-cell"><?php if ($chips): ?><div class="sc-chips"><?php
          foreach ($chips as $clab => $cn): ?><span class="sc-chip clu" title="<?php
              echo sc_h($clab . ': ' . number_format($cn) . ' cells of this type'); ?>"><?php
              echo sc_h($clab); ?><em><?php echo number_format($cn); ?></em></span><?php
          endforeach; ?></div><?php else: ?><span class="mx-na" title="No cluster in this dataset carries this type">&mdash;</span><?php endif; ?></td>
      <?php endif; ?>
      <td><a href="<?php echo sc_h($mk); ?>">marker genes</a></td>
    </tr>
    <?php endforeach; ?>
    <tr class="sc-total">
      <td><?php echo count($celltypes); ?> cell type<?php echo count($celltypes) === 1 ? '' : 's'; ?></td>
      <td class="num"><?php echo number_format($sum_cells); ?></td>
      <td class="num pct"><div class="sc-share-wrap"><span class="sc-share"><i style="width:<?php
          echo number_format(max(0.0, min(100.0, $sum_pct)), 3, '.', ''); ?>%"></i></span><span class="sc-share-v"><?php
          echo number_format($sum_pct, 2); ?>%</span></div></td>
      <?php if ($ckShow): ?>
      <td><?php echo count($ckLabels); ?> cluster<?php echo count($ckLabels) === 1 ? '' : 's'; ?></td>
      <?php endif; ?>
      <td></td>
    </tr>
  </table>
  </div>
  <?php else: ?>
    <div class="sc-note sc-note-wide">Cell-type composition is not in the database yet.</div>
  <?php endif; ?>

  <h3>Quality control for this dataset</h3>
  <?php if ($qc): ?>
  <p class="sc-note sc-note-wide">
    <?php if ($imported): ?>
    This dataset was not filtered by CnidoSite: the cells shown above are the
    source study's published object, so the numbers below describe what their
    own pipeline kept, not a step taken here. See
    <?php else: ?>
    These are the figures actually applied to the cells shown above, not the
    values requested in a configuration file. Every dataset is filtered
    independently; see
    <?php endif; ?>
    <a href="sn_data.php">Single-cell Data</a> for the full table across all
    datasets.
  </p>
  <?php
  /* 面板分两层。数字块只放「能成为数字」的那几项（各级细胞数 + 保留率）；
     其余按主题进卡片，一行一条，标签定宽、取值独享余下的宽度。

     这一块原先是 `repeat(auto-fill, minmax(290px,1fr))` 的五行网格：每格
     180px 标签 + 107px 取值，于是「the study filtered its own object; … and the
     published atlas 63,230 cells, …」被折成十行两三个词的短句，行高被同排最高
     的那一格撑到 326px，旁边只写「16」的格子后面就留一大片空白 —— 21 条互不
     相同的事实读起来像一堵参差的墙。铺满宽度反而把每个取值压得更窄，是那次
     改成 1590px 之后最难看的一块。

     不画漏斗／箭头链：各级并不一定嵌套。NVECT_2month 上报 2,032、矩阵里却有
     10,000 个 barcode（源研究发表的对象和它自己存进去的矩阵不是一回事），画成
     A→B→C 会让人以为数字长回去了。数字块不加箭头，只按流程顺序排。

     哪个数字是哪个口径，照 PBDB 那套老规矩：只写数据库里真有的，缺的整条不印
     （空白不等于零），不补零。 */
  $qc_tiles = array();
  $qc_reported = $qc['n_cells_reported'];
  if ($qc_reported !== null && $qc_reported !== '' && is_numeric($qc_reported)) {
      $qc_reported = number_format((float)$qc_reported);   /* 原来这一格是唯一不分组
                                                              千分位的，和邻居并排很显眼 */
  }
  if ($qc_reported !== null && $qc_reported !== '') {
      $qc_tiles[] = array('Cells reported by source', $qc_reported, 'as published by the source study');
  }
  if ($qc['n_cells_raw'] !== null) {
      $qc_tiles[] = array('Cells before filtering', number_format((int)$qc['n_cells_raw']), 'barcodes in the deposit');
  }
  if ($qc['n_cells_after_cell_qc'] !== null) {
      /* 副标题原先写 "low-quality cells removed"，但这一格印的是**保留下来的**细胞数
         （n_cells_after_cell_qc）。OPATA_whole_adult 是 raw 29,723 → 21,794，
         去掉的只有 7,929；写成 "21,794 low-quality cells removed" 等于说错了。 */
      $qc_tiles[] = array('After cell filtering', number_format((int)$qc['n_cells_after_cell_qc']), 'cells kept after cell filtering');
  }
  if ($qc['n_cells_final'] !== null) {
      $qc_tiles[] = array('Cells after filtering', number_format((int)$qc['n_cells_final']), 'the cells shown above');
  }
  if ($qc['pct_cells_retained'] !== null && $qc['pct_cells_retained'] !== '') {
      $qc_tiles[] = array('Retained', $qc['pct_cells_retained'], 'of the cells before filtering');
  }

  /* The same four modes the viewer names, so the page and the atlas agree.
     A bare `$qc['threshold_mode']` fallback used to print the raw column,
     which put "absolute" and "none" in front of the reader as if they were
     descriptions. */
  $qc_strategy = $qc['threshold_mode'] === 'adaptive'
               ? 'Per-library MAD outlier detection (adaptive)'
               : ($qc['threshold_mode'] === 'absolute'
                  ? 'Fixed thresholds chosen for this deposit'
                  : ($qc['threshold_mode'] === 'published'
                     ? 'Thresholds reproduced from the source publication'
                     : ($qc['threshold_mode'] === 'imported'
                        ? 'As published — not re-derived by CnidoSite'
                        : ($qc['threshold_mode'] === 'none'
                           ? 'No cell-level filtering applied'
                           : $qc['threshold_mode']))));

  /* 双重率只跟着「移除了几个」印在括号里，不写成分母是哪些细胞：那个百分数和
     任何单一分母都对不上（AMILL 9/28,736 = 0.031%，库里记的是 0.04%），它是
     检测器自己给的比例，不是我们重算的。 */
  $qc_doublets = $qc['n_doublets_removed'] !== null
               ? number_format((int)$qc['n_doublets_removed'])
                 . ($qc['doublet_rate'] ? ' (' . $qc['doublet_rate'] . ')' : '')
               : '';

  /* Three provenances, not two.  A `cluster_only` dataset is one whose
     features are transcript IDs, so the marker panel could not name any
     cluster; the labels are Leiden cluster identities and calling that
     "assigned de novo from a marker panel" would claim a naming that never
     happened -- the same mistake the viewer's manifest used to make.
     Nested ternary rather than `match`: the production host runs PHP 7.4,
     where `match` is a parse error and would 500 the whole page. */
  $qc_annot = $meta['annotation_provenance'] === 'published'
            ? 'Inherited from the source publication'
            : ($meta['annotation_provenance'] === 'cluster_only'
               ? 'Not assigned - Leiden clusters only'
               : 'Assigned de novo from a cnidarian marker panel');

  /* `updated_utc` 是 ISO 串，原来原样印成 2026-09-26T16:21:00+00:00 —— 又长又
     不可断词，是那一块唯一真的冲出格子的取值。列名就是 UTC，所以用 gmdate
     而不是 date（本机时区是 Asia/Hong_Kong，date 会印成本地时间还挂着 UTC 的
     名）。解析不出来就原样印，不猜。 */
  $qc_processed = $meta['updated_utc'];
  if ($qc_processed !== null && $qc_processed !== '') {
      $qc_ts = strtotime($qc_processed);
      if ($qc_ts !== false) {
          $qc_processed = gmdate('j M Y, H:i', $qc_ts) . ' UTC';
      }
  }

  $qc_libtype = $qc['platform_class'];
  if (trim((string)$qc['platform_confidence']) !== '') {
      $qc_libtype = trim($qc_libtype . ' (' . $qc['platform_confidence'] . ' confidence)');
  }

  $qc_groups = array(
    'Where the data came from' => array(
      'Source BioProject'    => $meta['bioproject'],
      'SRA study'            => $meta['sra_study'],
      'GEO series'           => $meta['geo_series'],
      'Library type'         => $qc_libtype,
      'Libraries integrated' => $qc['n_samples'],
    ),
    'What was applied to the cells' => array(
      'Filtering strategy'   => $qc_strategy,
      'Thresholds applied'   => $qc['threshold_detail'],
      'Doublets removed'     => $qc_doublets,
      'Doublet method'       => $qc['doublet_method'],
      'Integration method'   => $qc['integration_method'],
      'Mitochondrial genes found' => $qc['mito_genes_n'] !== null
                                  ? $qc['mito_genes_n']
                                    . ((int)$qc['mito_filtering_available'] === 0
                                       ? ' — too few for a reliable MT% filter, so none was applied' : '')
                                  : '',
      'MT% median (before / after)' => ($qc['mito_pct_median_raw'] !== null
                                  ? $qc['mito_pct_median_raw'] . '% / ' . $qc['mito_pct_median_final'] . '%' : ''),
      'MT% p95 (before / after)' => ($qc['mito_pct_p95_raw'] !== null
                                  ? $qc['mito_pct_p95_raw'] . '% / ' . $qc['mito_pct_p95_final'] . '%' : ''),
    ),
    'Annotation and embedding' => array(
      'Cell-type annotation' => $qc_annot,
      'Cell-type labels from' => $meta['cell_type_source'],
      'UMAP coordinates from' => isset($meta['embedding_source']) ? $meta['embedding_source'] : '',
      'Cluster colouring'    => isset($meta['cluster_source']) ? $meta['cluster_source'] : '',
      'Clusters / cell types' => $meta['annotation_provenance'] === 'cluster_only'
                                  ? $meta['n_clusters'] . ' clusters (no cell types asserted)'
                                  : $meta['n_clusters'] . ' / ' . $meta['n_cell_types'],
    ),
    'Reference and pipeline' => array(
      'Reference genome'     => trim($meta['reference_genome'] . ' ' . $meta['genome_version']),
      'Pipeline version'     => $meta['pipeline_version'],
      'Processed'            => $qc_processed,
    ),
  );
  ?>

  <?php if ($qc_tiles): ?>
  <div class="sc-qc-tiles">
    <?php foreach ($qc_tiles as $t): ?>
    <div class="sc-qc-tile">
      <div class="sc-qc-tile-k"><?php echo sc_h($t[0]); ?></div>
      <div class="sc-qc-tile-v"><?php echo sc_h($t[1]); ?></div>
      <div class="sc-qc-tile-n"><?php echo sc_h($t[2]); ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="sc-qc-cards">
    <?php foreach ($qc_groups as $gname => $grows):
        /* 空值整条不印 —— 「留空」和「是 0」是两件事，补个 0 就成了一个没做过的
           测量（手册里 A blank is not a zero 那段）。整组都没值就整张卡片不印。 */
        $shown = array();
        foreach ($grows as $k => $v) {
            if ($v === null) continue;
            if (is_string($v)) { $v = trim($v); }
            if ($v === '') continue;
            $shown[$k] = $v;
        }
        if (!$shown) continue; ?>
    <div class="sc-qc-card">
      <h4><?php echo sc_h($gname); ?></h4>
      <div class="sc-qc-bd">
        <?php foreach ($shown as $k => $v): ?>
        <div class="sc-qc-row">
          <span class="sc-qc-k"><?php echo sc_h($k); ?></span>
          <span class="sc-qc-v"><?php echo sc_h($v); ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="sc-note sc-note-wide">Quality-control figures for this dataset are not in the database yet.</div>
  <?php endif; ?>

<?php endif; ?>

<?php sc_footer(); ?>
