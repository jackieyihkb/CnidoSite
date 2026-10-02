<?php
/* ---------------------------------------------------------------------------
 * macrosynteny.php -- 宏共线性分析（Macrosynteny Analysis）入口页
 *
 * 数据来自 macrosyntR 对全部物种的两两比较结果（MySQL: macrosynteny_* 四张表，
 * 由 macrosyntR/web/sql/load_data.sh 导入）。
 * 流程：DNA 层面无法直接比对 distant 物种，这里用 BUSCO(cnidaria_odb12) 单拷贝
 *       直系同源基因作锚点，对每条染色体对做 Fisher 精确检验，再用贪心模块度聚类
 *       把显著关联的染色体归并成连锁群（linkage group），最后画成 Oxford grid。
 * ------------------------------------------------------------------------- */

require_once __DIR__ . '/macrosynteny_db.php';
require_once __DIR__ . '/macrosynteny_page.php';

$link = msr_db();

/* 物种列表：优先读数据库，数据库不可用时退回 03_build_web_db.py 生成的静态列表 */
$species_rows = array();
$stats = array('n_sp' => 0, 'n_anch' => 0, 'n_pairs' => 0, 'n_sig' => 0, 'n_clusters' => 0);
if ($link) {
    /* 不再查 DISTINCT class：页面上的 Class 下拉已经去掉，类群分组改由
       msr_species_select() 用 <optgroup> 表达（原因见 macrosynteny_page.php 里那段注释）。 */
    $species_rows = msr_query($link, "SELECT display_name, species_id, class, n_anchors, n_contigs
                                       FROM macrosynteny_species ORDER BY class, display_name");
    $t = msr_query($link, "SELECT COUNT(*) n, COALESCE(SUM(n_anchors),0) a FROM macrosynteny_species");
    if ($t) { $stats['n_sp'] = (int)$t[0]['n']; $stats['n_anch'] = (int)$t[0]['a']; }
    $t = msr_query($link, "SELECT COUNT(*) n, COALESCE(SUM(n_sig_pairs),0) s, COALESCE(SUM(n_clusters),0) c
                             FROM macrosynteny_pair");
    if ($t) { $stats['n_pairs'] = (int)$t[0]['n']; $stats['n_sig'] = (int)$t[0]['s'];
              $stats['n_clusters'] = (int)$t[0]['c']; }
    $examples = msr_query($link, "SELECT pair_id, sp1, sp2, n_anchors, n_chr_pairs, n_sig_pairs,
                                         frac_in_sig, n_clusters, median_rho
                                    FROM macrosynteny_pair ORDER BY n_anchors_in_sig DESC LIMIT 12");
    $best_clust = msr_query($link, "SELECT pair_id, sp1, sp2, n_anchors, n_clusters, frac_in_sig
                                      FROM macrosynteny_pair WHERE n_clusters > 0
                                     ORDER BY n_clusters DESC LIMIT 6");
} else {
    include __DIR__ . '/macrosynteny_species.inc.php';
    if (isset($MSR_SPECIES)) {
        foreach ($MSR_SPECIES as $r) {
            $species_rows[] = array('display_name' => $r[0], 'species_id' => $r[1],
                                    'class' => $r[2], 'n_anchors' => $r[3], 'n_contigs' => 0);
        }
    }
    $examples = array(); $best_clust = array();
}

$sel1 = isset($_REQUEST['species1']) ? msr_display_name($_REQUEST['species1']) : '';
$sel2 = isset($_REQUEST['species2']) ? msr_display_name($_REQUEST['species2']) : '';

function msr_pct($x) { return ($x === null || $x === '') ? '—' : sprintf('%.1f%%', 100 * (float)$x); }

msr_page_head('Macrosynteny Analysis',
              'Genome-wide macrosynteny and conserved linkage groups across Cnidaria species, computed with macrosyntR from BUSCO single-copy ortholog anchors.');
?>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Macrosynteny Analysis</b></legend>

<style type="text/css">
<?php /* 本页两张表全部居中（表头与单元格一视同仁）。
   三条规则都要写、都要带 `tr`：共用样式给 `table.gridtable tr td.num` 是 (0,2,3)
   （数字右对齐，对应 core 里 .cc-num 那一条），短写法的 `table.gridtable td` (0,1,2)
   和 `table.gridtable td.num` (0,2,2) 都压不过它 —— 页内 <style> 在链接样式表之后，
   特异性相同时靠顺序分胜负，所以这里凑到同一档 (0,2,3) 再靠顺序赢。
   **只作用于本页**：结果页与总览页的表格不受影响。 */ ?>
table.gridtable tr th,
table.gridtable tr td,
table.gridtable tr td.num {
    text-align: center;
}
</style>

<p class="paleo-intro">
    Whole-genome synteny conservation between the cnidarian species whose assemblies are in the
    macrosyntR run &mdash; the 139 listed below. Conserved linkage
    groups (macrosynteny) are inferred from BUSCO single-copy orthologs used as cross-species anchor points:
    every pair of chromosomes is tested with a Fisher exact test, and significantly associated chromosomes are
    merged into linkage groups by greedy modularity clustering. Results are shown as an interactive
    <b>Oxford grid</b> &mdash; one dot per ortholog, colored by its linkage group.
    Both <b>within-class</b> and <b>between-class</b> pairs can be compared: the two dropdowns below list
    every species in the analysis, grouped by class.
</p>

<?php /* 三个数字来自数据库。数据库连不上时 $stats 全是 0，原来照样印出来 ——
   读者先看到「0 species / 0 species pairs computed」，再看到下面的「数据库没连上」，
   像是分析真的算出了零个结果。数据库不可用就整块不印，只留下面那条告警。 */ ?>
<?php if ($link): ?>
<div style="margin:0 0 16px">
    <span class="msr-stat"><?php echo number_format($stats['n_sp']); ?> species</span>
    <span class="msr-stat"><?php echo number_format($stats['n_pairs']); ?> species pairs computed</span>
    <span class="msr-stat green"><?php echo number_format($stats['n_anch']); ?> ortholog anchors</span>
</div>
<?php endif; ?>

<?php if (!$link): ?>
<?php /* 原来这里让读者去跑 web/sql/load_data.sh、web/sql/macrosynteny_schema.sql、
   web/sql/README.md —— 这三条路径在本服务器上都不存在（那是 macrosyntR 源码包里的
   目录，从未随站点发布）。改成说明现状，不再指路到不存在的文件。 */ ?>
<div class="msr-note warn">
    <b>The macrosynteny data are not available.</b><br />
    Reason: <?php echo msr_h(msr_db_error()); ?><br />
    The results are read from the <code>macrosynteny_*</code> tables of the CnidoSite database, which the
    server could not open just now. This is a server-side problem rather than a query problem &mdash; nothing
    you type below can bring the tables back, so please try again later or
    <a href="/contact.php">contact us</a>.
    The dropdowns below still work (they read a pre-built static species list), but the result page needs the database.
</div>
<?php endif; ?>

<div class="kegg-form-container">
    <div class="form-title">Select Two Species to Compare</div>
    <form name="macrosynteny" method="post" action="macrosynteny_result.php" onSubmit="return msrCheckPair();">
        <div class="msr-two-col">
            <div class="form-group">
                <label class="form-label">Species 1 (X axis)</label>
                <?php msr_species_select($species_rows, 'species1', $sel1); ?>
            </div>
            <div class="form-group">
                <label class="form-label">Species 2 (Y axis)</label>
                <?php msr_species_select($species_rows, 'species2', $sel2); ?>
            </div>
        </div>

        <div class="msr-hint">
            Both lists contain all species and are grouped by class (<i>Hexacorallia</i>, <i>Hydrozoa</i>,
            <i>Octocorallia</i>, &hellip;). Any two species may be compared, whether or not they belong to the
            same class &mdash; every one of the species pairs above has been computed.
            The comparison is undirected: which species goes on the X axis only flips the layout. When a pair
            has no significant linkage groups (for example the highly fragmented Myxozoa genomes), the result
            page says so instead of showing an empty plot.
        </div>

        <div class="button-group">
            <button type="submit" class="btn-submit">🔗 View Macrosynteny</button>
            <button type="reset" class="btn-reset">↺ Reset</button>
        </div>
        <a class="example-link" href="macrosynteny_overview.php">&rarr; Or view the all-pairs heatmap</a>
    </form>
</div>

<script type="text/javascript">
/* Validation: both species must be chosen and must differ (field names stay in sync with the page). */
function msrCheckPair() {
    var f = document.forms['macrosynteny'];
    var a = f.elements['species1'].value, b = f.elements['species2'].value;
    if (!a || !b) { alert('Please select two species first.'); return false; }
    if (a === b) { alert('Please select two different species (both sides are currently ' + a + ').'); return false; }
    return true;
}
</script>

<?php if (count($examples)): ?>
<div class="msr-section-title">Examples &mdash; the most complete comparisons</div>
<div class="msr-table-scroll">
<table class="gridtable">
  <tr>
    <th>Species 1</th><th>Species 2</th>
    <th title="BUSCO single-copy anchors shared by the two species">Anchors</th>
    <th title="chromosome pairs of the two species that share at least one anchor; only pairs with &gt;= 3 anchors are tested with the Fisher exact test and appear in the per-chromosome table of the detail page">Chr. pairs</th>
    <th title="significantly conserved chromosome pairs">Significant</th>
    <th title="fraction of anchors falling on significant chromosome pairs">Anchors in blocks</th>
    <th title="number of linkage groups">Linkage groups</th>
    <th title="median Spearman rho of anchor order across the significant pairs; its sign summarises the overall tendency (&gt;0 = anchors tend to keep the same order, &lt;0 = tend to be inverted), but the direction of any individual block is only called when that block's own |rho| reaches the critical value — see the help section below">Median &rho;</th>
    <th></th>
  </tr>
<?php foreach ($examples as $e): ?>
  <tr>
    <?php /* macrosynteny_pair.sp1/sp2 存的是下划线 id（Acropora_digitifera），不是展示名。
             本站别处一律印「Acropora digitifera」并斜体，这里照做；给结果页的链接参数
             仍然用原始 id。 */ ?>
    <td><i><?php echo msr_h(msr_display_name($e['sp1'])); ?></i></td>
    <td><i><?php echo msr_h(msr_display_name($e['sp2'])); ?></i></td>
    <td class="num"><?php echo number_format((int)$e['n_anchors']); ?></td>
    <td class="num"><?php echo number_format((int)$e['n_chr_pairs']); ?></td>
    <td class="num"><?php echo number_format((int)$e['n_sig_pairs']); ?></td>
    <td class="num"><?php echo msr_pct($e['frac_in_sig']); ?></td>
    <td class="num"><?php echo (int)$e['n_clusters']; ?></td>
    <td class="num"><?php echo ($e['median_rho'] === null || $e['median_rho'] === '') ? '—' : sprintf('%+.2f', (float)$e['median_rho']); ?></td>
    <td><a href="macrosynteny_result.php?species1=<?php echo urlencode($e['sp1']); ?>&amp;species2=<?php echo urlencode($e['sp2']); ?>">view&nbsp;&rarr;</a></td>
  </tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>

<?php if (count($best_clust)): ?>
<div class="msr-section-title">Species pairs with the richest linkage structure</div>
<div class="msr-table-scroll">
<table class="gridtable">
  <tr><th>Species 1</th><th>Species 2</th><th>Anchors</th><th>Linkage groups</th><th>Anchors in blocks</th><th></th></tr>
<?php foreach ($best_clust as $e): ?>
  <tr>
    <td><i><?php echo msr_h(msr_display_name($e['sp1'])); ?></i></td>
    <td><i><?php echo msr_h(msr_display_name($e['sp2'])); ?></i></td>
    <td class="num"><?php echo number_format((int)$e['n_anchors']); ?></td>
    <td class="num"><?php echo (int)$e['n_clusters']; ?></td>
    <td class="num"><?php echo msr_pct($e['frac_in_sig']); ?></td>
    <td><a href="macrosynteny_result.php?species1=<?php echo urlencode($e['sp1']); ?>&amp;species2=<?php echo urlencode($e['sp2']); ?>">view&nbsp;&rarr;</a></td>
  </tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>

<div class="msr-section-title">How to read the Oxford grid</div>
<div class="msr-help">
<ul>
  <li>The plot opens in <b>cell view</b>: <b>one cell</b> = one pair of chromosomes, tinted by its linkage group and labelled with its anchor count
      (a white cell = the two chromosomes share no anchor). Zooming in switches it to <b>anchor view</b>.</li>
  <li><b>One dot</b> = one pair of orthologs (the same BUSCO id, one copy in each species); its X/Y coordinates are the positions on the respective chromosomes.</li>
  <li><b>Dots strung along the diagonal</b> &rArr; that stretch of chromosome stayed collinear between the two species (conserved synteny).</li>
  <li><b>Color</b> = linkage group. A grey dot marks a chromosome pair that neither belongs to a linkage group nor is significant on its own.</li>
  <li><b>Box</b> = the chromosome block covered by a linkage group; all dots inside share one color, as inferred by macrosyntR's greedy modularity clustering on the significant association graph.</li>
  <li><b>&rho;</b>: Spearman correlation of the anchor order within one chromosome block. Its sign alone does <b>not</b> define a direction: a direction is
      only called when the correlation is itself significant, <i>i.e.</i> when |&rho;| reaches the critical value for that block's anchor count at a
      two-sided <i>p</i> &lt; 0.001 &mdash; <b>collinear</b> for positive &rho;, <b>inverted</b> for negative &rho;, and <b>unordered</b> otherwise
      (significantly linked, but the anchor order is too scrambled, or the block too anchor-poor, to establish one). Because that critical value depends
      on the anchor count (0.57 at 30 anchors, 0.32 at 100, 0.10 at 1,000), the same &rho; can be decisive in a large block and meaningless in a small
      one, and &rho; is only defined where a block carries at least five shared anchors. This is an extra display metric computed here on top of the
      macrosyntR output.</li>
  <li>The chromosome order on the axes is not the natural order but the order produced by macrosyntR's reordering: the largest linkage group first,
      then by anchor count per chromosome, so that homologous blocks end up adjacent.</li>
</ul>
</div>

<div class="msr-note">
    <b>Methods and citation.</b> The macrosynteny analysis was run with
    <a href="https://cran.r-project.org/package=macrosyntR" target="_blank" rel="noopener noreferrer">macrosyntR</a>: BUSCO
    <i>cnidaria_odb12</i> single-copy orthologs are used as anchors, every chromosome pair is tested with a
    Fisher exact test (significant at q &lt; 0.001 after BH correction), and linkage groups are then delineated
    on the significant association graph with <code>igraph::cluster_fast_greedy</code>.
    Please cite: El Hilali S., Copley R. R. <i>macrosyntR: Drawing automatically ordered Oxford Grids from standard genomic
    files in R.</i> bioRxiv (2023). doi:10.1101/2023.01.26.525673
</div>

<?php msr_page_foot(); ?>
