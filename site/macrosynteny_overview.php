<?php
/* ---------------------------------------------------------------------------
 * macrosynteny_overview.php -- 全部两两比较的概览热图
 *
 * 只读 web/data/overview_pairs.json（由 03_build_web_db.py 生成），
 * 因此这一页不依赖 MySQL，适合放在导航里当入口；点格子跳到单个物种对的结果页。
 * ------------------------------------------------------------------------- */

require_once __DIR__ . '/macrosynteny_db.php';
require_once __DIR__ . '/macrosynteny_page.php';

$json_path = __DIR__ . '/data/overview_pairs.json';
$ov = null;
if (is_readable($json_path)) {
    $ov = json_decode(file_get_contents($json_path), true);
}

$n_sp = ($ov && isset($ov['species'])) ? count($ov['species']) : 0;
$n_pair = ($ov && isset($ov['pairs'])) ? count($ov['pairs']) : 0;

/* 供表格用的显示名映射 + 排序用的原始 pair 行 */
$id2name = array(); $class_of = array();
$rows = array();
if ($ov) {
    foreach ($ov['species'] as $s) {
        $id2name[$s['id']] = $s['name'];
        $class_of[$s['id']] = $s['class'];
    }
    foreach ($ov['pairs'] as $p) {
        // pairs 行 = [pair_id, sp1_id, sp2_id, frac_in_sig, n_sig_pairs, n_clusters, n_anchors]
        $rows[] = array('id' => $p[0], 'a' => $p[1], 'b' => $p[2],
                        'frac' => $p[3], 'nsig' => $p[4], 'nclu' => $p[5], 'nanch' => $p[6]);
    }
}
function msr_top($rows, $key, $min_anchors, $limit) {
    $f = array();
    foreach ($rows as $r) { if ($r['nanch'] >= $min_anchors) { $f[] = $r; } }
    usort($f, function ($x, $y) use ($key) {
        if ($y[$key] == $x[$key]) { return $y['nanch'] - $x['nanch']; }
        return ($y[$key] < $x[$key]) ? -1 : 1;
    });
    return array_slice($f, 0, $limit);
}
$top_frac = msr_top($rows, 'frac', 200, 15);
$top_clu  = msr_top($rows, 'nclu', 200, 15);

msr_page_head('Macrosynteny Overview',
              'All-pairs macrosynteny overview across Cnidaria genomes: fraction of BUSCO ortholog anchors inside significant conserved chromosome blocks.');
?>
<script LANGUAGE="JavaScript" src="js/macrosynteny.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/macrosynteny.js'); ?>" type="text/javascript"></script>
<script type="text/javascript">/*<![CDATA[*/
var MSR_MIN_ANCH_PAIR = <?php echo (int)MSR_MIN_ANCH_PAIR; ?>;
/*]]>*/</script>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Macrosynteny Analysis</b></legend>

<style type="text/css">
<?php /* 本页下方两张表全部居中（表头与单元格一视同仁）。三条都要写、都要带 `tr`：
   共用样式给 `table.gridtable tr td.num` 是 (0,2,3)（数字右对齐，对应 core 里
   .cc-num 那一条），短写法压不过它 —— 页内 <style> 在链接样式表之后，特异性相同时
   靠顺序分胜负，所以这里凑到同一档 (0,2,3) 再靠顺序赢。
   与 macrosynteny.php / 结果页同一处理。
   只作用于本页：不写进 macrosynteny.css，免得影响另外两页。 */ ?>
table.gridtable tr th,
table.gridtable tr td,
table.gridtable tr td.num {
    text-align: center;
}
</style>

<p class="paleo-intro">
    Every species pair in CnidoSite, compared with macrosyntR on BUSCO single-copy ortholog anchors.
    One cell = one species pair; the color is the chosen statistic (default: the fraction of shared
    ortholog anchors that fall inside significant, conserved chromosome blocks). Click a cell to open
    the full Oxford grid for that pair. Cells cover <b>within-class</b> and <b>between-class</b> pairs alike.
</p>

<div style="margin:0 0 16px">
    <span class="msr-stat"><?php echo number_format($n_sp); ?> species</span>
    <span class="msr-stat green"><?php echo number_format($n_pair); ?> pairs computed</span>
    <a class="example-link" style="margin-top:0;margin-left:8px;" href="macrosynteny.php">⇄ Compare two species</a>
</div>

<?php if (!$ov || !$n_sp): ?>
<div class="msr-note warn">
    <b>Could not read <code>web/data/overview_pairs.json</code>.</b><br />
    Run <code>python3 03_build_web_db.py</code> first (it writes this file while merging the shard
    results). This page only needs that JSON, not MySQL.
</div>
<?php else: ?>

<div class="msr-toolbar">
    <label>Color by</label>
    <select id="msr-hm-metric">
        <option value="frac" selected="selected">anchors in significant blocks (frac_in_sig)</option>
        <option value="nsig">significant chromosome pairs</option>
        <option value="nclu">linkage groups</option>
        <option value="nanch">shared ortholog anchors</option>
    </select>
    <label>Cell size</label>
    <select id="msr-hm-size">
        <option value="6">6 px</option>
        <option value="10" selected="selected">10 px</option>
        <option value="16">16 px</option>
        <option value="24">24 px</option>
    </select>
    <label>Class</label>
    <select id="msr-hm-class"><option value="">all</option></select>
    <span id="msr-hm-status" style="color:#64748b;"></span>
</div>

<div class="msr-heat-legend">
    <span id="msr-hm-legend-label">0%</span>
    <span class="ramp" id="msr-hm-ramp"></span>
    <span id="msr-hm-legend-max">100%</span>
    <span style="color:#64748b;">(all <?php echo number_format(count($ov['pairs'] ?? array())); ?> species pairs in the analysis are scored: none falls below the <?php echo (int)MSR_MIN_ANCH_PAIR; ?>-anchor threshold, so no cell is left uncoloured)</span>
</div>

<div class="msr-heat-wrap" id="msr-hm-wrap">
    <svg id="msr-heatmap" xmlns="http://www.w3.org/2000/svg"></svg>
</div>
<div class="msr-tooltip" id="msr-hm-tip"></div>

<div class="msr-section-title">Best-conserved species pairs</div>
<div class="msr-table-scroll">
<table class="gridtable">
  <tr><th>Species 1</th><th>Species 2</th><th>Class</th><th>Anchors</th><th>Significant pairs</th><th>Anchors in blocks</th><th>Linkage groups</th><th></th></tr>
<?php foreach ($top_frac as $r): ?>
  <tr>
    <td><?php echo msr_h($id2name[$r['a']]); ?></td>
    <td><?php echo msr_h($id2name[$r['b']]); ?></td>
    <td><?php echo msr_h($class_of[$r['a']] === $class_of[$r['b']] ? $class_of[$r['a']] : $class_of[$r['a']] . ' / ' . $class_of[$r['b']]); ?></td>
    <td class="num"><?php echo number_format($r['nanch']); ?></td>
    <td class="num"><?php echo number_format($r['nsig']); ?></td>
    <td class="num"><?php echo $r['frac'] === null ? '—' : sprintf('%.1f%%', 100 * (float)$r['frac']); ?></td>
    <td class="num"><?php echo (int)$r['nclu']; ?></td>
    <td><a href="macrosynteny_result.php?species1=<?php echo urlencode($id2name[$r['a']]); ?>&amp;species2=<?php echo urlencode($id2name[$r['b']]); ?>">view&nbsp;&rarr;</a></td>
  </tr>
<?php endforeach; ?>
</table>
</div>
<div class="msr-hint">Sorted by the fraction of anchors inside significant blocks; only pairs sharing &ge; 200 anchors are counted (the fraction is unstable when anchors are few).</div>

<div class="msr-section-title">Richest linkage structure</div>
<div class="msr-table-scroll">
<table class="gridtable">
  <tr><th>Species 1</th><th>Species 2</th><th>Anchors</th><th>Linkage groups</th><th>Significant pairs</th><th>Anchors in blocks</th><th></th></tr>
<?php foreach ($top_clu as $r): ?>
  <tr>
    <td><?php echo msr_h($id2name[$r['a']]); ?></td>
    <td><?php echo msr_h($id2name[$r['b']]); ?></td>
    <td class="num"><?php echo number_format($r['nanch']); ?></td>
    <td class="num"><?php echo (int)$r['nclu']; ?></td>
    <td class="num"><?php echo number_format($r['nsig']); ?></td>
    <td class="num"><?php echo $r['frac'] === null ? '—' : sprintf('%.1f%%', 100 * (float)$r['frac']); ?></td>
    <td><a href="macrosynteny_result.php?species1=<?php echo urlencode($id2name[$r['a']]); ?>&amp;species2=<?php echo urlencode($id2name[$r['b']]); ?>">view&nbsp;&rarr;</a></td>
  </tr>
<?php endforeach; ?>
</table>
</div>

<div class="msr-note">
    <b>How to read this heatmap.</b> The diagonal is a species against itself and is left blank. The darker
    the cell, the more / larger conserved chromosome blocks there are between the two genomes: a high
    <code>frac_in_sig</code> means most orthologs still sit together in blocks on chromosomes (a conserved
    karyotype), whereas a pale cell with many anchors means the orthologs are scattered over many
    chromosomes/contigs &mdash; the signature of an actively rearranged genome or a fragmented assembly
    (for example the reduced genomes of parasitic Myxozoa). Classes are marked with different side bars so
    within-class and between-class comparisons are easy to tell apart. The numbers come from macrosyntR:
    a Fisher exact test per chromosome pair, with significant pairs merged into linkage groups by
    <code>igraph::cluster_fast_greedy</code>.
</div>

<script type="text/javascript">
/*<![CDATA[*/
MSR_HEAT.init(<?php echo msr_json($ov); ?>, 'msr-heatmap', 'msr-hm-tip', 'msr-hm-wrap');
/*]]>*/
</script>

<?php endif; ?>
<?php msr_page_foot(); ?>
