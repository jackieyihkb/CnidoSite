<?php
/**
 * gene_exp.php -- gene expression across the cells of a dataset.
 *
 * Referee 3 (comment 10h) reported that the second panel on this page was a
 * broken image.  The old page assembled both panels from pre-rendered PNGs
 * whose filenames were built by string concatenation, so a gene or cell type
 * with a name the file-naming scheme did not anticipate produced an <img> with
 * no file behind it.  Every panel is now drawn in the browser from the
 * dataset's exported files, so the page carries no <img> at all and a gene or
 * a cell type either has data or the page says so.
 *
 * The cell-type map beside the expression map is drawn, not the dataset's
 * exported umap_celltype.png, so that the two line up: that figure scales its
 * two axes independently to fill its box while the viewer fits them with one
 * uniform scale, so the same cluster lands in a different place in each.  See
 * the `sideMap` option in viewer/cellatlas.js.
 *
 * Deep link:  gene_exp.php?dataset=ID&gene=NAME
 */
require_once __DIR__ . '/sc_common.php';

$conn = sc_conn();
$dataset = isset($_GET['dataset']) ? trim($_GET['dataset']) : '';
$gene = isset($_GET['gene']) ? trim($_GET['gene']) : '';
$gene_input = $gene;      // 用户原样输入，改过的 $gene 只用于显示「他搜的是哪个」

$atlas = sc_atlas($conn);

/*
 * A `pub:` id names a dataset, and this page can only draw one that has a
 * count matrix behind it.  A figure we have since re-analysed resolves to the
 * re-analysis; one we have not resolves to '' and the default below applies,
 * exactly as it always has for a `pub:` id on this page -- the published
 * figures are offered here only through `?species=`, where
 * sc_species_no_matrix() explains why there is nothing to plot.
 */
if (sc_is_published_id($dataset)) {
    $pubAll = sc_published_entries($conn);
    $dataset = isset($pubAll[$dataset])
        ? sc_interactive_twin($conn, $pubAll[$dataset]) : '';
}

/*
 * A `?species=` link can only be honoured here when that species has a count
 * matrix: both plots on this page are computed from one, and for the species
 * that were not re-analysed the deposited artefact is a PNG with no matrix
 * behind it.  So the species resolves to a re-analysed dataset, and the two
 * ways that can fail are kept apart -- the module holds nothing for the
 * species, or it holds figures but no matrix -- because they call for different
 * sentences, and neither may fall back to another species' expression.
 */
$sp = sc_requested_species($conn);
$spMiss = false;
$spNoMatrix = false;
if ($dataset === '' && $sp !== null) {
    $spInteractive = array();
    foreach ($sp['ids'] as $id) {
        if (isset($atlas[$id])) {
            $spInteractive[] = $id;
        }
    }
    if ($spInteractive) {
        $dataset = $spInteractive[0];
    } elseif ($sp['ids']) {
        $spNoMatrix = true;
    } else {
        $spMiss = true;
    }
}
if (!$spMiss && !$spNoMatrix) {
    $dataset = sc_default_dataset($conn, $dataset);
}
$meta = ($dataset !== '' && isset($atlas[$dataset])) ? $atlas[$dataset] : null;

// The gene index is written by pipeline/05_export_web.py.  Read it server side
// so the search box can autocomplete and so we can tell the user honestly
// whether a gene has expression data, rather than letting the viewer fail to
// find it.
$gene_index = array();
$gene_set = array();
if ($meta) {
    $p = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . sc_asset_base() . $dataset . '/genes.json';
    if (!is_file($p)) {
        $p = __DIR__ . '/../singlecell_data/' . $dataset . '/genes.json';
    }
    if (is_file($p)) {
        $raw = json_decode(file_get_contents($p), true);
        if (is_array($raw) && isset($raw['genes'])) {
            $gene_index = $raw['genes'];
            foreach ($gene_index as $g) {
                $gene_set[$g['gene']] = $g;
            }
        }
    }
}
/*
 * 基因号对照表。genes.json 里的 `gene` 是来源数据集自己的号（NV2.8285、
 * g4335.t1…），站内其它页面用的是 <ABBR>_locus.mRNA 那一列。这里只做两件事：
 * 显示时站点号在前、原号进括号；`?gene=` 两种写法都收。**viewer 和数据文件
 * 仍然只认原号**，所以 $gene 在任何取数之前先归约回原号。
 */
$gmap = $meta ? sc_gene_id_map($dataset) : array();
if ($gene !== '' && $gmap) {
    $gene = sc_gene_scid($gene, $gmap);
}
$gene_found = ($gene !== '' && isset($gene_set[$gene]));
ksort($gene_set);

/*
 * 右边那一栏由 viewer 自己画：同一份 embedding.bin、同一套视图变换，只是换成按
 * cell type 上色（sideMap 选项）。**不用数据集导出那张 umap_celltype.png**，因为
 * 那张图的两个轴是各自拉伸填满画框的，而 viewer 是等比的——同一批细胞在两张图上
 * 会落在不同位置，读者没法把一个区域从左边搬到右边（SPIST 实测纵向差 17.9%，
 * 每个数据集还不一样）。自己画就没有这个问题，顺带也没有了烤进图里的标题和页脚。
 */

sc_header('Gene Expression', 'gene_exp.php',
  'Single-cell expression of one gene in a chosen dataset: the UMAP colored by expression level, '
  . 'the same cells colored by cell type, and the distribution of that expression across cell types.');
?>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Single-cell Gene Expression</b></legend>

<?php if ($spMiss): ?>

  <?php sc_species_missing($conn, $sp['species'], 'gene_exp.php'); ?>

<?php elseif ($spNoMatrix): ?>

  <?php sc_species_no_matrix($conn, $sp['species'],
        'cell_marker.php?dataset=' . urlencode($sp['ids'][0])); ?>

<?php elseif (!$meta): ?>

  <div class="sc-empty">
    <b>No dataset is available yet.</b><br>
    Upload <code>web/singlecell_data/</code> and <code>web/viewer/</code>, then
    load the SQL in <code>sql/</code>. See <a href="cell_atlas.php">Cell Atlas</a>
    for details.
  </div>

<?php else: ?>

  <p class="sc-note sc-note-wide">
    <strong>Left:</strong> every cell coloured by this gene's expression.
    <strong>Right:</strong> the same cells coloured by cell type, on the same
    coordinates, so a cluster sits in the same place in both.
    <strong>Below:</strong> this gene's distribution in each cell type.
    Point at a cell in either map to read that cell's type and its expression.
  </p>

  <?php sc_dataset_picker($conn, $dataset, 'gene_exp.php',
        $gene !== '' ? array('gene' => $gene) : array()); ?>

  <form method="get" action="gene_exp.php" class="sc-search">
    <input type="hidden" name="dataset" value="<?php echo sc_h($dataset); ?>">
    <label for="gene"><b>Gene</b></label>
    <?php
    /* 输入框默认回填**站点号**（与下拉项、页面标题一致），而不是用户刚才敲的那串：
       敲 XP_… 时两者相同；敲 NV2.10 时回填 XP_001626598.1 —— 这正是「主显示改成
       站点号」的意思。敲错找不到基因时保持原样，好让他看见自己敲的是什么。 */
    $box = ($gene !== '' && isset($gene_set[$gene]))
         ? sc_gene_display($gene, $gmap)
         : null; ?>
    <input type="text" name="gene" id="gene" list="gene-list"
           value="<?php echo sc_h($box ? $box['primary'] : $gene_input); ?>"
           placeholder="e.g. XP_048577623.1, or this dataset's own ID">
    <datalist id="gene-list">
      <?php /* value 用站点号（提交回来的就是它，最省事），label 里带上原号 ——
             浏览器把 label 显示在下拉项上、并按 label 过滤，所以两种写法都能
             被搜到；即便某个浏览器忽略 label，value 仍是可解析的那个。 */ ?>
      <?php foreach ($gene_set as $name => $g):
          $d = sc_gene_display($name, $gmap); ?>
        <option value="<?php echo sc_h($d['primary']); ?>"
                label="<?php echo sc_h($d['mapped']
                        ? $d['primary'] . ' (' . $d['sc'] . ')'
                        : ($d['known'] ? $d['sc'] . ' - not mapped' : $d['sc'])); ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <button type="submit">Plot</button>
  </form>

  <?php if ($gene !== '' && !$gene_found): ?>
    <div class="sc-note sc-note-wide">
      <b>No expression data for &ldquo;<?php echo sc_h($gene_input); ?>&rdquo; in
      <?php echo sc_h($meta['species']); ?>.</b>
      <?php if ($gene !== $gene_input): ?>
        (Looked up as <code><?php echo sc_h($gene); ?></code>, this dataset's own
        ID for that accession.)<?php endif; ?>
      Only genes ranked as markers in this dataset have expression files
      (<?php echo number_format(count($gene_set)); ?> genes). Pick one from the
      list, or browse the
      <a href="cell_marker.php?dataset=<?php echo urlencode($dataset); ?>">marker table</a>.
    </div>
  <?php elseif (!$gene_found): ?>
    <div class="sc-note sc-note-wide">
      Enter a gene to plot. <?php echo number_format(count($gene_set)); ?> marker
      genes are available for this dataset; the box autocompletes as you type.
    </div>
  <?php endif; ?>

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
      dataUrl: "<?php echo sc_h(sc_asset_base() . $dataset); ?>/",
      datasetId: "<?php echo sc_h($dataset); ?>",
      // 表达图在左、同一批细胞的 cell-type 图在右、分布图通栏在下方
      violinBand: true,
      sideMap: true
    });
    viewer.load().then(function () {
      <?php if ($gene_found): ?>
      // gene first, then the rest of the URL state, so the expression
      // colouring wins over any categorical highlight the link carried
      viewer.setGene(<?php echo json_encode($gene); ?>).then(function () {
        viewer.applyUrlState();
      });
      <?php else: ?>
      viewer.applyUrlState();
      <?php endif; ?>
    }).catch(function (e) {
      status.textContent = "Could not load this dataset: " + e.message;
    });
  })();
  </script>

  <?php if ($gene_found): ?>
  <?php $g = $gene_set[$gene]; $disp = sc_gene_display($gene, $gmap); ?>
  <h3>About <?php echo sc_gene_label($disp); ?></h3>
  <table class="sc-table sc-about">
    <tr><th>Site gene ID</th>
        <td><?php if ($disp['mapped']): ?>
              <a href="gene_detail.php?gene=<?php echo urlencode($disp['acc']); ?>&species=<?php echo urlencode($meta['species']); ?>"
                 title="Open this gene's full annotation record"><?php echo sc_h($disp['acc']); ?></a>
            <?php elseif ($disp['known']): ?>
              <?php echo sc_h($disp['sc']); ?> <?php echo sc_gene_badge(); ?>
            <?php else: ?>
              <?php echo sc_h($disp['sc']); ?>
            <?php endif; ?></td></tr>
    <?php if ($disp['mapped']): ?>
    <tr><th>Source dataset ID</th>
        <td><code><?php echo sc_h($disp['sc']); ?></code>
            &mdash; the identifier used in this dataset's own files, and in the
            source study. The viewer keys its expression data by this ID.</td></tr>
    <?php elseif ($disp['known']): ?>
    <tr><th>Source dataset ID</th>
        <td><code><?php echo sc_h($disp['sc']); ?></code>
            &mdash; this dataset's own gene model. It could not be matched to an
            accession on the rest of this site, so it is kept as-is and does
            not resolve outside this module.</td></tr>
    <?php else: ?>
    <tr><th>Source dataset ID</th>
        <td><code><?php echo sc_h($disp['sc']); ?></code>
            &mdash; this dataset's own identifier. No accession cross-reference
            has been built for this dataset, so the gene is shown exactly as
            deposited.</td></tr>
    <?php endif; ?>
    <tr><th>Detected in</th>
        <td class="num"><?php echo number_format((int)$g['n_cells_expressing']); ?>
            cells (<?php echo number_format((float)$g['pct_expressing'], 2); ?>% of
            <?php echo number_format((int)$meta['n_cells_final']); ?>)</td></tr>
    <tr><th>Dataset</th>
        <td><?php echo sc_h($meta['species']); ?> —
            <?php echo sc_h($meta['tissue_organ']); ?>
            <?php if ($meta['stage'] !== '') echo '(' . sc_h($meta['stage']) . ')'; ?></td></tr>
    <tr><th>Source</th>
        <td><a href="https://www.ncbi.nlm.nih.gov/bioproject/<?php echo sc_h($meta['bioproject']); ?>"
               target="_blank"><?php echo sc_h($meta['bioproject']); ?></a></td></tr>
  </table>
  <p class="sc-note sc-note-wide">
    This gene's distribution in each cell type is in the violin panel below the
    map. To see which other genes mark the same cells, open the
    <a href="cell_marker.php?dataset=<?php echo urlencode($dataset); ?>&gene=<?php echo urlencode($gene); ?>">marker table</a>.
  </p>
  <?php endif; ?>

<?php endif; ?>

<?php sc_footer(); ?>
