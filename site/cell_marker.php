<?php
/**
 * cell_marker.php -- ranked marker genes per cell type, cross-linked.
 *
 * Referee 2 (Major #1) asks for the site's modules to cross-link, and
 * specifically for marker genes to link through to a feature plot and a violin
 * plot.  Every gene in the re-analysed datasets links to gene_exp.php, which
 * opens the viewer already coloured by that gene and showing its per-cell-type
 * distribution; every cell type links back to cell_atlas.php with that type
 * isolated.
 *
 * Two data sources, one page
 * --------------------------
 * The site has always carried marker tables for six species
 * (<ABBR>_cellmarker); we re-analysed two datasets into singlecell_markers.
 * This page reads both, because serving only the re-analysed two would drop
 * marker data for four species the site has published for years.  The two
 * sources are not equivalent, and the page does not pretend they are:
 *
 *   - singlecell_markers has a typed rank and a Wilcoxon score;
 *   - <ABBR>_cellmarker has neither, so the rank shown is derived by ordering
 *     the published log2 fold changes within each cell type, and the score
 *     column is left empty rather than faked.
 *
 * Only the re-analysed datasets have an expression matrix, so only their genes
 * can be plotted; for a published dataset the plot links are omitted and the
 * page says why instead of offering a link that cannot work.
 *
 * Deep link:  cell_marker.php?dataset=ID&cell_type=NAME&gene=NAME
 * where ID is a dataset_id or a published id (`pub:<ABBR>:<tissue>`).
 */
require_once __DIR__ . '/sc_common.php';

$conn = sc_conn();
$requested  = isset($_GET['dataset']) ? trim($_GET['dataset']) : '';
$cell_type  = isset($_GET['cell_type']) ? trim($_GET['cell_type']) : '';
$focus_gene = isset($_GET['gene']) ? trim($_GET['gene']) : '';

$atlas = sc_atlas($conn);
$published = sc_published_entries($conn);

/*
 * The site's menu and species portal link here as `?species=...`; honour it
 * before any default.  Without this a Hydra link opened Acropora's markers
 * under a Hydra URL, with nothing on the page saying so.
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
$spMany = ($sp !== null && !$spMiss && count($sp['ids']) > 1);

$pubEntry = null;
$dataset = '';
if ($spMiss) {
    // Left empty on purpose: the notice below renders instead of another
    // species' marker table.
} elseif (sc_is_published_id($requested) && isset($published[$requested])) {
    $dataset = $requested;
    $pubEntry = $published[$requested];
} else {
    $dataset = sc_default_dataset($conn, $requested);
}
$meta = ($pubEntry === null && $dataset !== '' && isset($atlas[$dataset]))
    ? $atlas[$dataset] : null;

/** Cell types as array(cell_type, count) -- count is cells, or markers for a
 *  published dataset, which has no per-cell counts to report. */
$types = array();
if ($pubEntry) {
    $types = sc_published_celltypes($conn, $pubEntry['abbr1'], $pubEntry['tissue']);
} elseif ($meta) {
    foreach (sc_celltypes($conn, $dataset) as $ct) {
        $types[] = array($ct['cell_type'], (int)$ct['n_cells']);
    }
}
$namedOnly = sc_named_only($types);
// An empty list is "named only" by vacuous truth, which is not what the page
// means by it, so require something to have been listed.
$namedOnly = ($types && $namedOnly);

/*
 * 基因号显示层：这张表里的 gene 列存的是**来源数据集自己的**号
 * （NV2.8285 / Amil_Amillepora19313 / g4335.t1 / Ocupat_v02_G022773…），
 * 站内其它页面用的是 <ABBR>_locus.mRNA 那一列。这里先备好对照表，渲染时
 * 站点号作主显示、原号进括号；映射不上的保持原号并标注 not mapped。
 *
 * 两条路各取所需：重分析的数据集读 singlecell_data/<DS>/gene_ids.json（sidecar，
 * 整份基因表都在里面）；已发表的数据集没有 genes.json，只能拿这一页要用到的号
 * 去 sc_gene_refseq 批量查。**数据层始终用原号**，映射只影响显示与检索。
 */
$gmap = $pubEntry ? array() : sc_gene_id_map($dataset);

/*
 * ?gene= 是用户输入，可能是原号，也可能是他刚从 gene_detail.php 复制的站点号
 * （XP_048577623.1 / 不带版本号都算）。归约回原号再往下走 —— 表格比对、以及
 * 后面拼给 viewer 的链接都只认真原号。
 */
if ($focus_gene !== '' && $gmap) {
    $focus_gene = sc_gene_scid($focus_gene, $gmap);
}

// Which cell type's markers does the focus gene belong to?  Used to highlight
// where the user arrived from, so following a link out and back is not a reset.
if (!$pubEntry && $focus_gene !== '' && $cell_type === ''
        && sc_has_table($conn, 'singlecell_markers')) {
    $st = $conn->prepare(
        "SELECT cell_type FROM singlecell_markers
          WHERE dataset_id = ? AND gene = ?
          ORDER BY `rank` LIMIT 1"
    );
    $st->bind_param('ss', $dataset, $focus_gene);
    $st->execute();
    $r = $st->get_result()->fetch_row();
    if ($r) {
        $cell_type = $r[0];
    }
    $st->close();
}

$markers = array();
if ($pubEntry) {
    $markers = sc_published_markers($conn, $pubEntry['abbr1'], $pubEntry['tissue'], $cell_type);
} elseif ($meta && sc_has_table($conn, 'singlecell_markers')) {
    if ($cell_type !== '') {
        $st = $conn->prepare(
            "SELECT cell_type, gene, `rank`, log2fc, padj, score, pct_in
               FROM singlecell_markers
              WHERE dataset_id = ? AND cell_type = ?
              ORDER BY `rank`"
        );
        $st->bind_param('ss', $dataset, $cell_type);
    } else {
        $st = $conn->prepare(
            "SELECT cell_type, gene, `rank`, log2fc, padj, score, pct_in
               FROM singlecell_markers
              WHERE dataset_id = ? AND `rank` <= 15
              ORDER BY cell_type, `rank`"
        );
        $st->bind_param('s', $dataset);
    }
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $markers[] = $row;
    }
    $st->close();
}

/* 已发表数据集没有 sidecar —— 拿这一页真正要用到的号去数据库批量查。
   晚于 $markers 取回，因为要的就是这批号。$gmap 为空时整页照旧显示原号。 */
if ($pubEntry && $markers) {
    $ids = array();
    foreach ($markers as $m) {
        $ids[] = $m['gene'];
    }
    $gmap = sc_gene_refseq_map($conn, $pubEntry['abbr1'], $ids);
    if ($focus_gene !== '') {
        $focus_gene = sc_gene_scid($focus_gene, $gmap);
    }
}
/* 表下方那句说明只在**确实有对照表**时出现。数据集还没生成 sidecar 时 $gmap 是
   空的，那时每一行都「未映射」，照着报「N 个号未映射」会把「这个数据集还没做
   映射」说成「这些基因映射不上」—— 两件事，句子必须分开。 */
$nUnmapped = 0;
$hasMap = (count($gmap) > 0);
if ($hasMap) {
    foreach ($markers as $m) {
        if (!isset($gmap[$m['gene']])) {
            $nUnmapped++;
        }
    }
}

sc_header('Cell Marker', 'cell_marker.php',
  'Marker genes for each cell type in a single-cell dataset: rank, log2 fold change, score and the '
  . 'percentage of cells detected, with a per-gene plot.');
?>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Cell Marker</b></legend>

<?php if ($spMany): ?>
  <div class="sc-note sc-note-wide" style="margin-top:12px;">
    Showing the first of <?php echo count($sp['ids']); ?> datasets held for
    <b><?php echo sc_h($sp['species']); ?></b>; the others are in the
    <b>Dataset</b> list below.
  </div>
<?php endif; ?>

<?php if ($spMiss): ?>

  <?php sc_species_missing($conn, $sp['species'], 'cell_marker.php'); ?>
  <p class="sc-note sc-note-wide">Or open a dataset directly:</p>
  <?php sc_dataset_picker($conn, $dataset, 'cell_marker.php', array(), true, false); ?>

<?php elseif (!$pubEntry && !$meta): ?>

  <div class="sc-empty">
    <b>No marker data is available yet.</b><br>
    Load <code>sql/singlecell_schema.sql</code> and
    <code>sql/load_singlecell.sql</code> into the <code>cnidaria</code> database.
    See <a href="cell_atlas.php">Cell Atlas</a>.
  </div>

<?php else: ?>

  <?php if ($pubEntry):
      // Whether this figure has since been re-analysed changes what the
      // sentence may claim.  It used to say "this dataset was not re-analysed"
      // unconditionally, which was true when the only re-runs were Acropora
      // millepora and Hydra; as more deposits were re-run it silently became
      // false on the pages of the datasets it was most likely to be read on.
      $mkTwin = sc_interactive_twin($conn, $pubEntry); ?>
  <p class="sc-note sc-note-wide">
    Marker genes for <b><?php echo sc_h($pubEntry['species']); ?></b> —
    <?php echo sc_h(sc_pub_tissue($pubEntry['tissue'])); ?>, as deposited with the source study.
    These are the study's own markers, ranked by the published
    <b>log2 fold change</b> within each cell type; no test statistic was
    deposited with them.
    <?php if ($mkTwin !== ''): ?>
    This dataset has since been re-analysed, and that re-analysis computes its
    own markers, with test statistics and expression plots, on
    <a href="cell_marker.php?dataset=<?php echo sc_h(urlencode($mkTwin)); ?>">its
    own page</a>. The table below is the published one and is kept separate
    from it on purpose: the two are different deposits, not two rankings of the
    same one.
    <?php else: ?>
    This dataset was not re-analysed, so its expression cannot be plotted here:
    the deposit behind the figure is an image, not a count matrix. Re-analysed
    datasets are marked as such in <a href="sn_data.php">Single-cell Data</a>
    and do offer plots.
    <?php endif; ?>
  </p>
  <?php else: ?>
  <p class="sc-note sc-note-wide">
    Marker genes are ranked by the Wilcoxon rank-sum test within each cell type,
    against all other cells; the <b>Score</b> column is that test's statistic.
    Because the adjusted p-value is minute for almost every gene once a dataset
    runs to tens of thousands of cells, it is used only to order the table and is
    not shown as a column, so the table is better filtered on
    <b>log2 fold change</b> and <b>percent detected</b>.
    Click any gene to plot its expression; click any cell type to isolate it in
    the atlas.
  </p>
  <?php endif; ?>

  <?php sc_dataset_picker($conn, $dataset, 'cell_marker.php',
        $cell_type !== '' ? array('cell_type' => $cell_type) : array(),
        true, false /* published entries stay listed; see the parameter's note */); ?>

  <?php if ($types): ?>
  <div class="sc-chips">
    <span class="sc-chips-label">Cell type:</span>
    <a class="sc-chip<?php echo $cell_type === '' ? ' on' : ''; ?>"
       href="cell_marker.php?dataset=<?php echo urlencode($dataset); ?>">all</a>
    <?php foreach ($types as $ct):
        $on = ($ct[0] === $cell_type); ?>
      <?php if ($on): ?>
        <span class="sc-chip on"><?php echo sc_h($ct[0]); ?><em><?php echo (int)$ct[1]; ?></em></span>
      <?php else: ?>
        <a class="sc-chip" href="cell_marker.php?dataset=<?php echo urlencode($dataset); ?>&cell_type=<?php echo urlencode($ct[0]); ?>"
           title="Show only this cell type"><?php echo sc_h($ct[0]); ?><em><?php echo (int)$ct[1]; ?></em></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php if ($namedOnly): ?>
  <div class="sc-callout sc-callout-info">
    This dataset's clusters have not been assigned cell-type names in the source
    study, so they are listed as <i>cluster N</i>. The number beside each is how
    many marker genes are recorded for it.
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($focus_gene !== ''): ?>
  <p class="sc-note sc-note-wide">
    Showing the cell type that <b><?php echo sc_h($focus_gene); ?></b> is ranked
    highest in.
  </p>
  <?php endif; ?>

  <?php if (!$markers): ?>
    <?php
    // An empty table has three causes and they need different sentences.  The
    // one that matters is the third: a link naming a cell type this dataset
    // does not have -- easy to hit by carrying a name over from the source
    // study's nomenclature, or from a published figure, to a dataset we
    // re-annotated.  Answering that with "no marker genes are stored for this
    // dataset yet", directly beneath a list of thirty cell types that do have
    // markers, reads as a broken page rather than as a wrong link.
    $known = array();
    foreach ($types as $ct) {
        $known[] = $ct[0];
    }
    $unknown = ($cell_type !== '' && $known && !in_array($cell_type, $known, true));
    ?>
    <div class="sc-note sc-note-wide">
      <?php if ($unknown): ?>
        There is no cell type called <b><?php echo sc_h($cell_type); ?></b> in
        this dataset. Pick one from the list above, or
        <a href="cell_marker.php?dataset=<?php echo urlencode($dataset); ?>">show all cell types</a>.
      <?php else: ?>
        <?php echo $pubEntry
            ? 'No marker genes are recorded for this tissue.'
            : 'No marker genes are stored for this dataset yet.'; ?>
      <?php endif; ?>
    </div>
  <?php else: ?>
  <div class="sc-table-wrap">
  <table class="sc-table">
    <tr>
      <th>Cell type</th>
      <th class="num">Rank</th>
      <th>Gene</th>
      <?php if ($pubEntry): ?><th>Symbol</th><?php endif; ?>
      <th class="num">log2 FC</th>
      <?php /* 这一列是 singlecell_markers.score，即 Wilcoxon 秩和检验的统计量本身
             （见文件头「a typed rank and a Wilcoxon score」）。页面上没有任何一句
             话说明它是什么，53.7 这样的值既像百分比又像 p 值；published 数据集没有
             这一列（该字段为 null，印成「–」）。补一个 title，措辞与 sn_data.php 的
             表头 tooltip 一致。 */ ?>
      <?php if (!$pubEntry): ?><th class="num" title="Wilcoxon rank-sum score of this gene in this cell type against all other cells &mdash; the statistic the ranking is based on. Published marker sets have no score, and the column is omitted.">Score</th><?php endif; ?>
      <th class="num">% detected</th>
      <?php if (!$pubEntry): ?><th>Plot</th><?php endif; ?>
    </tr>
    <?php foreach ($markers as $m):
        $g = $m['gene'];
        $is_focus = ($focus_gene !== '' && $g === $focus_gene);
        $atlas_link = 'cell_atlas.php?dataset=' . urlencode($dataset)
                    . '&type=' . urlencode($m['cell_type']);
        // Only a re-analysed dataset has an expression matrix behind it, so
        // only there can a gene be plotted.
        $gene_link = $pubEntry ? '' :
              ('gene_exp.php?dataset=' . urlencode($dataset) . '&gene=' . urlencode($g));
        // 「violin」必须开在 viewer 上。它原来指向本页（cell_marker.php），而本页
        // 只有 marker 表、没有任何画图代码，所以点了以后只是重新载入同一页——链接
        // 的 title 说「expression by cell type in the viewer」，viewer 却根本没打开。
        // viewer 支持 ?gene=&type=：gene 给地图上色并画出「Expression of <gene> by
        // cell type」面板，type 顺带选中该细胞类型（切回分类着色时高亮还在）。
        $mk_link = $pubEntry ? '' :
              ('gene_exp.php?dataset=' . urlencode($dataset)
                 . '&gene=' . urlencode($g)
                 . '&type=' . urlencode($m['cell_type']));
        // 站点号作主显示、原号进括号；映射不上则原号 + not mapped 角标。
        // 链接里仍然带**原号**：viewer 的数据文件就是按原号命名的，而 gene_exp.php
        // 两种写法都收（会在那边归一），所以链接不依赖映射是否成功。
        $disp = sc_gene_display($g, $gmap); ?>
    <tr<?php if ($is_focus) echo ' style="background:#eaf2fd;"'; ?>>
      <td><a href="<?php echo sc_h($atlas_link); ?>"><?php echo sc_h($m['cell_type']); ?></a></td>
      <td class="num"><?php echo (int)$m['rank']; ?></td>
      <td><?php if ($gene_link !== ''): ?><a href="<?php echo sc_h($gene_link); ?>"><?php echo sc_gene_label($disp); ?></a><?php else: ?><?php echo sc_gene_label($disp); ?><?php endif; ?></td>
      <?php if ($pubEntry): ?>
      <td><?php echo sc_h(isset($m['symbol']) && $m['symbol'] !== '' ? $m['symbol'] : '–'); ?></td>
      <?php endif; ?>
      <td class="num"><?php echo $m['log2fc'] === null ? '–' : number_format((float)$m['log2fc'], 2); ?></td>
      <?php if (!$pubEntry): ?>
      <td class="num"><?php echo $m['score'] === null ? '–' : number_format((float)$m['score'], 1); ?></td>
      <?php endif; ?>
      <td class="num"><?php echo $m['pct_in'] === null ? '–' : number_format((float)$m['pct_in'] * 100, 1) . '%'; ?></td>
      <?php if (!$pubEntry): ?>
      <td>
        <a href="<?php echo sc_h($gene_link); ?>">feature</a> &middot;
        <a href="<?php echo sc_h($mk_link); ?>" title="expression by cell type in the viewer">violin</a>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php if ($hasMap):
      /* 这句说明两个作用：说清括号里那串是什么，以及为什么有些行没有括号。
         前者不懂就会以为是别名，后者不懂就会以为是页面出错。 */
      $target = $pubEntry
          ? 'the accession in the site\'s <code>' . sc_h($pubEntry['abbr1'])
            . '_locus.mRNA</code> column'
          : 'the accession the rest of this site uses for <b>'
            . sc_h($meta['species']) . '</b>'; ?>
  <p class="sc-note sc-note-wide">
    Where an identifier could be established, the gene is shown as
    <?php echo $target; ?>, with <b>the source dataset's own ID in parentheses</b>
    (hover for why). Those IDs are the keys the
    underlying data files use, so they are what the links here carry.
    <?php if ($nUnmapped > 0): ?>
    <?php echo number_format($nUnmapped); ?> of the
    <?php echo number_format(count($markers)); ?> identifiers listed on this page
    carry a <b>not mapped</b> mark: they are the source study's own gene models,
    and no accession could be <em>justified</em> for them. They are left as they
    are rather than given a best guess.
    <?php endif; ?>
  </p>
  <?php else: ?>
    <?php
    /* 一句话都不能省的另一种情形：这个数据集一个号都没映射上。原因有两种，而且
       必须分开说 —— 「我们还没做」和「做了但没有可用的对应关系」是两回事，用
       同一句话会各错一半。AMURI / OARBU 是后者：它们只有已发表的 marker 表，
       没有可下载的来源蛋白组，而且号（evm.model.Chr…、EPDR1.1、g1804）来自
       站点根本不收录的那版注释，没有能落到实处的一一对应。 */
    $reanalysed = !$pubEntry; ?>
  <p class="sc-note sc-note-wide">
    <?php if ($reanalysed): ?>
      The accession map for this dataset has not been built, so genes are shown
      with the identifiers their own data files use.
    <?php else: ?>
      Every gene in this table is shown with <b>the source study's own
      identifier</b>. No accession cross-reference is available for them: they
      come from an annotation that the rest of this site does not carry, so
      there is nothing to map them onto. They are shown as deposited rather
      than matched to a best guess.
      <?php /* The reason there is no map is the annotation, not the absence of
              a re-run, so this sentence cannot be the place that says whether
              one happened.  It did say "this dataset was not re-analysed", and
              that is false for AMILL and AMURI now: a dataset can have been
              re-run and still leave this table unmapped, because the
              re-analysis has its own gene identifiers and does not supply the
              cross-reference the published one lacks.  When there is a
              re-analysis, name it and say why it does not help here -- which
              is also the one true thing to offer a reader who came for the
              published table. */ ?>
      <?php if ($mkTwin !== ''): ?>
      This dataset has since been re-analysed:
      <a href="cell_marker.php?dataset=<?php echo sc_h(urlencode($mkTwin)); ?>">its
      own page</a> ranks genes by a test statistic and plots their expression.
      That re-analysis is a separate deposit with its own gene identifiers, so
      it does not supply the cross-reference this table lacks.
      <?php else: ?>
      This dataset was not re-analysed, so there is no second deposit here that
      could supply one.
      <?php endif; ?>
    <?php endif; ?>
  </p>
  <?php endif; ?>
  <?php endif; ?>

<?php endif; ?>

<?php sc_footer(); ?>
