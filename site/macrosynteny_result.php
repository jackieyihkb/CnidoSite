<?php
/* ---------------------------------------------------------------------------
 * macrosynteny_result.php -- 某一对物种的宏共线性结果（交互式 Oxford grid）
 *
 * 取数（全部在服务器端完成，前端只做画图，不依赖任何外部 JS 库）：
 *   1. macrosynteny_pair        该物种对的汇总统计
 *   2. macrosynteny_linkage     每条染色体对的 p / q / 显著性 / 连锁群字母 / 方向
 *   3. macrosynteny_anchor      按 busco_id 自连接得到两侧锚点坐标（= 图上的一个点）
 * 输出：页面内嵌一份 JSON，交给 js/macrosynteny.js 画成 SVG。
 *
 * 参数：species1（X 轴）、species2（Y 轴），GET/POST 均可；
 *       dl=linkage|anchors 时直接下载 TSV。
 * ------------------------------------------------------------------------- */

require_once __DIR__ . '/macrosynteny_db.php';
require_once __DIR__ . '/macrosynteny_page.php';

$sp1_req = isset($_REQUEST['species1']) ? trim($_REQUEST['species1']) : '';
$sp2_req = isset($_REQUEST['species2']) ? trim($_REQUEST['species2']) : '';
$dl      = isset($_REQUEST['dl']) ? $_REQUEST['dl'] : '';

$link = msr_db();
$err  = '';

$S1 = $S2 = null; $pair = null; $rows = array(); $anchors = array();
if (!$link) {
    $err = 'Database not connected: ' . msr_db_error();
} elseif ($sp1_req === '' || $sp2_req === '') {
    $err = 'Please select two species on the <a href="macrosynteny.php">Macrosynteny Analysis</a> page.';
} else {
    $S1 = msr_species_row($link, $sp1_req);
    $S2 = msr_species_row($link, $sp2_req);
    if (!$S1 || !$S2) {
        $err = 'Species not found in the database: ' . msr_h($sp1_req) . ' / ' . msr_h($sp2_req) . '.';
    } elseif ($S1['species_id'] === $S2['species_id']) {
        $err = 'Please select two different species.';
    } else {
        $A = $S1['species_id'];            // X 轴物种
        $B = $S2['species_id'];            // Y 轴物种
        $pid = msr_pair_id($A, $B);
        $pid_e = msr_esc($link, $pid);
        $A_e = msr_esc($link, $A);
        $B_e = msr_esc($link, $B);

        $r = msr_query($link, "SELECT * FROM macrosynteny_pair WHERE pair_id = '$pid_e'");
        if (!$r) {
            $err = 'No comparison available for this species pair. Likely reasons: the two species share fewer than '
                 . MSR_MIN_ANCH_PAIR . ' BUSCO single-copy anchors (heavily reduced or fragmented genomes, e.g. Myxozoa), '
                 . 'so the pair was not included in the all-vs-all run; or its comparison has not finished yet.';
        } else {
            $pair = $r[0];
            // 导向：把用户选的物种 1 放在 X 轴。macrosynteny_pair 里 sp1/sp2 是按字典序
            // 固定存的，用户可能选反了，这时要把两侧的染色体名和 order_sp1/order_sp2 都换过来。
            // （注意 macrosynteny_linkage 表里没有 sp1/sp2 两列，物种只存在 pair 表。）
            $swap = ($A !== $pair['sp1']);
            $rows = array();
            foreach (msr_query($link, "
                    SELECT sp1_chr, sp2_chr, n_anchors, pval, pval_adj,
                           is_significant, cluster, rho, orientation
                      FROM macrosynteny_linkage WHERE pair_id = '$pid_e'") as $rr) {
                $rows[] = array(
                    'chr_a' => $swap ? $rr['sp2_chr'] : $rr['sp1_chr'],
                    'chr_b' => $swap ? $rr['sp1_chr'] : $rr['sp2_chr'],
                    'n_anchors' => $rr['n_anchors'], 'pval' => $rr['pval'],
                    'pval_adj' => $rr['pval_adj'], 'is_significant' => $rr['is_significant'],
                    'cluster' => $rr['cluster'], 'rho' => $rr['rho'],
                    'orientation' => $rr['orientation']);
            }
            $anchors = msr_query($link, "
                SELECT a1.busco_id,
                       a1.seq_id AS seq1, a1.mid_pos AS mid1, a1.ord AS ord1, a1.gene_id AS gene1,
                       a2.seq_id AS seq2, a2.mid_pos AS mid2, a2.ord AS ord2, a2.gene_id AS gene2,
                       a1.gene_symbol AS symbol
                  FROM macrosynteny_anchor a1
                  JOIN macrosynteny_anchor a2 ON a1.busco_id = a2.busco_id
                 WHERE a1.species_id = '$A_e' AND a2.species_id = '$B_e'");
        }
    }
}

/* ------------------------------------------------ TSV 下载
 * 只有明确的 dl=linkage|anchors 才走下载分支；其它取值（含拼错）照常渲染页面，
 * 否则会返回一个内容为空的 200 响应。 */
if (($dl === 'linkage' || $dl === 'anchors') && $link && $pair) {
    $pid_e = msr_esc($link, $pair['pair_id']);
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    if ($dl === 'linkage') {
        header('Content-Disposition: attachment; filename="macrosynteny_'
               . preg_replace('/[^A-Za-z0-9_.-]/', '_', $pair['pair_id']) . '_linkage.tsv"');
        echo "# macrosyntR linkage groups: {$pair['sp1']} (sp1) vs {$pair['sp2']} (sp2)\n";
        echo "# sp1/sp2 follow the lexicographic order of pair_id, independent of the axis orientation chosen on the page\n";
        echo "# q-value threshold 0.001 (BH); cluster = greedy-modularity linkage group, 0 = none\n";
        echo "sp1_chr\tsp2_chr\tn_anchors\tpvalue\tqvalue\tsignificant\tcluster\trho\torientation\n";
        foreach (msr_query($link, "SELECT sp1_chr, sp2_chr, n_anchors, pval, pval_adj,
                                          is_significant, cluster, rho, orientation
                                     FROM macrosynteny_linkage WHERE pair_id = '$pid_e'
                                    ORDER BY is_significant DESC, n_anchors DESC") as $r) {
            echo implode("\t", array($r['sp1_chr'], $r['sp2_chr'], $r['n_anchors'],
                                     $r['pval'], $r['pval_adj'],
                                     $r['is_significant'] ? 'yes' : 'no',
                                     $r['cluster'], $r['rho'], $r['orientation'])) . "\n";
        }
    } elseif ($dl === 'anchors') {
        header('Content-Disposition: attachment; filename="macrosynteny_'
               . preg_replace('/[^A-Za-z0-9_.-]/', '_', $pair['pair_id']) . '_anchors.tsv"');
        echo "# macrosyntR ortholog anchors (BUSCO single-copy), coordinates are midpoint (bp)\n";
        /* 注意：锚点表按页面的轴顺序导出（sp1 列 = 用户选的物种1 = S1），
           而 pair_id 里的 sp1/sp2 是字典序，两者可能相反，所以标签必须用 S1/S2。 */
        echo "# columns: " . $S1['species_id'] . " (sp1) vs " . $S2['species_id'] . " (sp2)\n";
        echo "busco_id\tsp1\tsp1_chr\tsp1_mid\tsp1_ord\tsp1_gene\tsp2\tsp2_chr\tsp2_mid\tsp2_ord\tsp2_gene\tannotation\n";
        foreach (msr_query($link, "
                SELECT a1.busco_id, a1.seq_id s1c, a1.mid_pos m1, a1.ord o1, a1.gene_id g1,
                       a2.seq_id s2c, a2.mid_pos m2, a2.ord o2, a2.gene_id g2, a1.gene_symbol sy
                  FROM macrosynteny_anchor a1 JOIN macrosynteny_anchor a2 ON a1.busco_id = a2.busco_id
                 WHERE a1.species_id = '$A_e' AND a2.species_id = '$B_e'
                 ORDER BY a1.seq_id, a1.ord") as $r) {
            echo implode("\t", array($r['busco_id'], $S1['species_id'], $r['s1c'], $r['m1'], $r['o1'], $r['g1'],
                                     $S2['species_id'], $r['s2c'], $r['m2'], $r['o2'], $r['g2'], $r['sy'])) . "\n";
        }
    }
    exit;
}

/* ------------------------------------------------ 组装前端数据 */
function msr_order_chroms($order_text, $counts, $maxord) {
    /* 染色体在轴上的顺序：先用 macrosyntR 重排后的顺序（order_sp1/2），
       没被它列到的（超过绘图上限的碎片化 contig）按锚点数降序补齐 */
    $out = array(); $seen = array();
    foreach (array_filter(array_map('trim', explode(',', (string)$order_text))) as $c) {
        if (isset($counts[$c]) && !isset($seen[$c])) { $out[] = $c; $seen[$c] = 1; }
    }
    $rest = array();
    foreach ($counts as $c => $n) { if (!isset($seen[$c])) { $rest[$c] = $n; } }
    uksort($rest, function ($a, $b) use ($counts) {
        if ($counts[$a] !== $counts[$b]) { return $counts[$b] - $counts[$a]; }
        return strnatcasecmp($a, $b);
    });
    foreach ($rest as $c => $n) { $out[] = $c; }
    return $out;
}

$payload = null; $table_rows = array();
if ($pair && count($anchors)) {
    $cnt1 = array(); $cnt2 = array(); $max1 = array(); $max2 = array();
    foreach ($anchors as $a) {
        $c = $a['seq1']; $cnt1[$c] = isset($cnt1[$c]) ? $cnt1[$c] + 1 : 1;
        if (!isset($max1[$c]) || (int)$a['ord1'] > $max1[$c]) { $max1[$c] = (int)$a['ord1']; }
        $c = $a['seq2']; $cnt2[$c] = isset($cnt2[$c]) ? $cnt2[$c] + 1 : 1;
        if (!isset($max2[$c]) || (int)$a['ord2'] > $max2[$c]) { $max2[$c] = (int)$a['ord2']; }
    }
    $order1 = msr_order_chroms($swap ? $pair['order_sp2'] : $pair['order_sp1'], $cnt1, $max1);
    $order2 = msr_order_chroms($swap ? $pair['order_sp1'] : $pair['order_sp2'], $cnt2, $max2);
    $idx1 = array_flip($order1); $idx2 = array_flip($order2);

    $chrom1 = array(); $chrom2 = array();
    foreach ($order1 as $c) { $chrom1[] = array('name' => $c, 'n' => $cnt1[$c], 'max' => $max1[$c]); }
    foreach ($order2 as $c) { $chrom2[] = array('name' => $c, 'n' => $cnt2[$c], 'max' => $max2[$c]); }

    // 连锁群调色板（与 macrosyntR plot_chord_diagram 的默认 Ref_palette 一致）
    $palette = array('#89C5DA', '#DA5724', '#74D944', '#CE50CA', '#3F4921', '#C0717C', '#CBD588',
                     '#5F7FC7', '#673770', '#D3D93E', '#38333E', '#508578', '#D7C1B1', '#689030',
                     '#AD6F3B', '#CD9BCD', '#D14285', '#6DDE88', '#652926', '#7FDCC0', '#C84248',
                     '#8569D5', '#5E738F', '#D1A33D', '#8A7C64', '#599861');

    // 连线表 + 连锁群统计
    $cl_of_panel = array(); $clusters = array(); $link_rows = array();
    foreach ($rows as $r) {
        $cl = ($r['cluster'] === null || $r['cluster'] === '' || $r['cluster'] === 'NA') ? '0' : $r['cluster'];
        $cl_of_panel[$r['chr_a'] . "\x1f" . $r['chr_b']] = $cl;
        if ($cl !== '0') {
            if (!isset($clusters[$cl])) { $clusters[$cl] = array('id' => $cl, 'n' => 0, 'dots' => 0,
                                                                 'chroms1' => array(), 'chroms2' => array()); }
            $clusters[$cl]['n']++;
            $clusters[$cl]['chroms1'][$r['chr_a']] = 1;
            $clusters[$cl]['chroms2'][$r['chr_b']] = 1;
        }
        $link_rows[] = array(
            'c1' => $r['chr_a'], 'c2' => $r['chr_b'], 'n' => (int)$r['n_anchors'],
            'p'  => ($r['pval'] === null ? null : (float)$r['pval']),
            'q'  => ($r['pval_adj'] === null ? null : (float)$r['pval_adj']),
            'sig' => (int)$r['is_significant'], 'clust' => $cl,
            'rho' => ($r['rho'] === null ? null : (float)$r['rho']),
            'ori' => $r['orientation'],
        );
    }
    ksort($clusters);
    $cl_list = array(); $cl_index = array(); $ci = 0;
    foreach ($clusters as $cl => $info) {
        $info['color'] = $palette[$ci % count($palette)];
        $info['chroms1'] = array_keys($info['chroms1']);
        $info['chroms2'] = array_keys($info['chroms2']);
        $cl_list[] = $info; $cl_index[$cl] = $ci; $ci++;
    }

    // 锚点：dots[b] = [busco 序号, chr1 序号, ord1, mid1, chr2 序号, ord2, mid2]
    $buscos = array(); $b_index = array(); $gene1 = array(); $gene2 = array(); $symbol = array();
    $dots = array(); $used_cluster = array();
    foreach ($anchors as $a) {
        if (!isset($idx1[$a['seq1']]) || !isset($idx2[$a['seq2']])) { continue; }
        $bid = $a['busco_id'];
        if (!isset($b_index[$bid])) {
            $b_index[$bid] = count($buscos);
            $buscos[] = $bid; $gene1[] = (string)$a['gene1'];
            $gene2[] = (string)$a['gene2']; $symbol[] = (string)$a['symbol'];
        }
        $panel = $a['seq1'] . "\x1f" . $a['seq2'];
        $cl = isset($cl_of_panel[$panel]) ? $cl_of_panel[$panel] : '0';
        if ($cl !== '0') { $used_cluster[$cl] = 1; $clusters[$cl]['dots']++; }
        $dots[] = array($b_index[$bid], $idx1[$a['seq1']], (int)$a['ord1'], (int)$a['mid1'],
                        $idx2[$a['seq2']], (int)$a['ord2'], (int)$a['mid2'],
                        ($cl === '0' ? -1 : $cl_index[$cl]));
    }
    // 只保留图上真正出现过的连锁群，并按出现顺序重排颜色索引
    $keep = array(); $remap = array(); $k = 0;
    foreach ($cl_list as $info) {
        if (isset($used_cluster[$info['id']])) { $remap[$info['id']] = $k++; $keep[] = $info; }
    }
    foreach ($keep as &$info) {
        $info['dots'] = 0;
        foreach ($dots as $d) { if ($d[7] >= 0 && $cl_list[$d[7]]['id'] === $info['id']) { $info['dots']++; } }
    }
    unset($info);
    foreach ($dots as &$d) { if ($d[7] >= 0) { $d[7] = $remap[$cl_list[$d[7]]['id']]; } }
    unset($d);

    $payload = array(
        'sp1' => array('id' => $S1['species_id'], 'name' => $S1['display_name'],
                       'class' => $S1['class'], 'n_anchors' => (int)$S1['n_anchors'],
                       'n_contigs' => (int)$S1['n_contigs']),
        'sp2' => array('id' => $S2['species_id'], 'name' => $S2['display_name'],
                       'class' => $S2['class'], 'n_anchors' => (int)$S2['n_anchors'],
                       'n_contigs' => (int)$S2['n_contigs']),
        'pair' => array('pair_id' => $pair['pair_id'],
                        'n_anchors' => (int)$pair['n_anchors'],
                        'n_chr_pairs' => (int)$pair['n_chr_pairs'],
                        'n_sig_pairs' => (int)$pair['n_sig_pairs'],
                        'n_anchors_in_sig' => (int)$pair['n_anchors_in_sig'],
                        'frac_in_sig' => ($pair['frac_in_sig'] === null ? null : (float)$pair['frac_in_sig']),
                        'n_clusters' => (int)$pair['n_clusters'],
                        'median_rho' => ($pair['median_rho'] === null ? null : (float)$pair['median_rho'])),
        'chrom1' => $chrom1, 'chrom2' => $chrom2,
        'buscos' => $buscos, 'gene1' => $gene1, 'gene2' => $gene2, 'symbol' => $symbol,
        'dots' => $dots, 'clusters' => $keep, 'linkage' => $link_rows,
        'dl' => array('linkage' => 'macrosynteny_result.php?species1=' . urlencode($S1['display_name'])
                                  . '&species2=' . urlencode($S2['display_name']) . '&dl=linkage',
                      'anchors' => 'macrosynteny_result.php?species1=' . urlencode($S1['display_name'])
                                  . '&species2=' . urlencode($S2['display_name']) . '&dl=anchors'),
    );
    $table_rows = $link_rows;
    usort($table_rows, function ($a, $b) {
        if ($a['sig'] !== $b['sig']) { return $b['sig'] - $a['sig']; }
        return $b['n'] - $a['n'];
    });
}

$title = ($S1 && $S2) ? ($S1['display_name'] . ' vs ' . $S2['display_name']) : 'Macrosynteny Analysis';
/* 没有物种对时 $title 已经是 'Macrosynteny Analysis'，再拼一次 ' - Macrosynteny'
   会印成 "Macrosynteny Analysis - Macrosynteny - CnidoSite"。 */
msr_page_head(($S1 && $S2) ? ($title . ' - Macrosynteny') : $title,
              'Interactive Oxford grid of conserved linkage groups between two cnidarian species.');
?>
<script LANGUAGE="JavaScript" src="js/macrosynteny.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/macrosynteny.js'); ?>" type="text/javascript"></script>

<style type="text/css">
<?php /* 本页表格全部居中（表头与单元格一视同仁）。三条都要写、都要带 `tr`：共用样式给
   `table.gridtable tr td.num` 是 (0,2,3)（数字右对齐，对应 core 里 .cc-num 那一条），
   短写法压不过它 —— 页内 <style> 在链接样式表之后，特异性相同时靠顺序分胜负，
   所以这里凑到同一档 (0,2,3) 再靠顺序赢。与 macrosynteny.php 同一处理。 */ ?>
table.gridtable tr th,
table.gridtable tr td,
table.gridtable tr td.num {
    text-align: center;
}
</style>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Macrosynteny Analysis</b></legend>

<?php if ($err): ?>
<div class="msr-note warn"><?php echo $err; ?></div>
<p><a class="example-link" href="macrosynteny.php">&larr; Back to species selection</a></p>
<?php else: ?>

<p class="msr-page-title">
    <?php echo msr_h($S1['display_name']); ?> <span class="vs">vs</span> <?php echo msr_h($S2['display_name']); ?>
    <span style="font-size:15px;font-weight:600;color:#64748b;">
        (<?php echo msr_h($S1['class']); ?> &times; <?php echo msr_h($S2['class']); ?>)
    </span>
</p>
<p class="paleo-intro">
    Interactive Oxford grid of conserved linkage groups, over
    <?php echo number_format((int)$pair['n_anchors']); ?> BUSCO single-copy orthologs shared by the two
    species. X axis = <?php echo msr_h($S1['display_name']); ?>, Y axis = <?php echo msr_h($S2['display_name']); ?>;
    chromosomes are ordered as inferred by macrosyntR, not by their natural order.
    The plot opens <b>fitted to the window</b> and in <b>cell view</b>: one cell per chromosome pair, tinted by
    its linkage group and <b>labelled with its anchor count</b>, so the conserved blocks and their sizes are
    readable at a glance. Zoom in (<b>+</b>, <b>Ctrl</b>&nbsp;+&nbsp;mouse wheel &mdash;
    &#8984;&nbsp;+&nbsp;wheel on a Mac &mdash; or a double-click) and it switches to <b>anchor view</b>, one dot
    per anchor; <b>Fit</b> returns to the whole grid, and whatever you point at stays in place while zooming.
    Hover a cell for its chromosome pair, anchor count, significance and &rho;; click it to zoom into that
    block. Hover a dot for its BUSCO and gene, and click it to pin that panel to the figure.
</p>

<div style="margin:0 0 16px">
    <span class="msr-stat green"><?php echo number_format((int)$pair['n_sig_pairs']); ?> significant chromosome pair<?php echo (int)$pair['n_sig_pairs'] === 1 ? '' : 's'; ?></span>
    <span class="msr-stat"><?php echo (int)$pair['n_clusters']; ?> linkage group<?php echo (int)$pair['n_clusters'] === 1 ? '' : 's'; ?></span>
    <span class="msr-stat"><?php echo ($pair['frac_in_sig'] === null ? '—' : sprintf('%.1f%%', 100 * (float)$pair['frac_in_sig'])); ?> anchors in blocks</span>
    <span class="msr-stat">median &rho; = <?php echo ($pair['median_rho'] === null ? '—' : sprintf('%+.2f', (float)$pair['median_rho'])); ?></span>
</div>

<?php if ((int)$pair['n_sig_pairs'] === 0): ?>
<div class="msr-note warn">
    <b>No chromosome pair in this species pair reached the significance threshold</b> (Fisher exact test,
    q &lt; 0.001 after BH correction), so no linkage group could be inferred and every dot in the plot below
    is grey. Common reasons: <?php echo msr_h($S1['display_name']); ?> has
    <?php echo number_format((int)$S1['n_contigs']); ?> contigs/scaffolds and
    <?php echo msr_h($S2['display_name']); ?> has
    <?php echo number_format((int)$S2['n_contigs']); ?> &mdash; a fragmented assembly spreads the anchors over
    so many small contigs that each chromosome pair gets too few anchors for the test to have power. Such a
    result is informative in itself (it points to assembly quality or to genome rearrangement in that lineage);
    the anchor count and p-value of the chromosome pairs with enough anchors to be tested are listed in the
    table below &mdash; pairs sharing fewer than three anchors are treated as noise and are omitted from it.
</div>
<?php elseif ((int)$pair['n_clusters'] === 0): ?>
<div class="msr-note warn">
    <b>There are significant chromosome pairs, but no linkage group was inferred.</b>
    <?php /* 这个分支的 78 对里 n_sig_pairs 全都是 1，原来写成 "1 chromosome pairs" —— 单数形
             才是这一句实际唯一会出现的形态，加个别数判断。 */ ?>
    <?php $__nSig = (int)$pair['n_sig_pairs']; ?>
    This pair has <?php echo number_format($__nSig); ?> chromosome pair<?php echo $__nSig === 1 ? '' : 's'; ?> passing the
    significance test (q &lt; 0.001 after BH correction), yet no module is formed from
    <?php echo $__nSig === 1 ? 'it' : 'them'; ?>: macrosyntR's
    modularity clustering only builds communities from edges between significant chromosome pairs, so isolated
    significant pairs and pairs spanning communities are both recorded as <code>clus = 0</code> (no linkage
    group) and every dot in the plot below is grey. This indicates that only scattered homologous segments
    remain between the two genomes, without chromosome-scale blocks of conserved synteny.
</div>
<?php endif; ?>

<div class="msr-toolbar">
    <label>Color by</label>
    <select id="msr-colorby">
        <option value="cluster">linkage group (clust)</option>
        <option value="sig">significance</option>
        <option value="ori">orientation (collinear / inverted / unordered)</option>
        <option value="none">single color</option>
    </select>
    <label>Labels per axis</label>
    <select id="msr-topn" title="How many sequences on each axis are drawn and named individually, largest first. Every sequence is always plotted — the ones past this cut are merged into the &quot;other&quot; band at the end of the axis. Raise it to name more of them and shrink that band.">
        <option value="0">all</option>
        <option value="20" selected="selected">top 20</option>
        <option value="40">top 40</option>
        <option value="60">top 60</option>
    </select>
    <label>Zoom</label>
    <button type="button" class="btn-reset" id="msr-zoom-out" title="Zoom out (keeps the centre of the view)" style="flex:none;padding:6px 12px;font-size:15px;">−</button>
    <span id="msr-zoom-val" title="zoom relative to the fitted view (100% = the whole grid fits the window)" style="min-width:44px;text-align:center;">100%</span>
    <button type="button" class="btn-reset" id="msr-zoom-in" title="Zoom in (keeps the centre of the view)" style="flex:none;padding:6px 12px;font-size:15px;">+</button>
    <button type="button" class="btn-reset" id="msr-zoom-fit" title="Fit the whole grid in the view" style="flex:none;padding:6px 12px;font-size:15px;">Fit</button>
    <button type="button" class="btn-reset" id="msr-zoom-100" title="One anchor per pixel. With 'fill canvas' on the two axes are at different scales, so this lands exactly on 1:1 for the denser of the two (the status line under the grid gives both)." style="flex:none;padding:6px 12px;font-size:15px;">1:1</button>
    <button type="button" class="btn-reset" id="msr-full" title="Give the grid the whole browser window (Esc to leave)" style="flex:none;padding:6px 12px;font-size:15px;">⛶ Full screen</button>
    <label title="Scale each axis to the container on its own, so the grid fills the whole panel. Uncheck to force the same scale on both axes (which leaves the square grid floating in a wide panel with white space either side)."><input type="checkbox" id="msr-fill" checked="checked" /> fill canvas</label>
    <label><input type="checkbox" id="msr-shownonsig" checked="checked" /> show non-significant dots</label>
    <label><input type="checkbox" id="msr-showblocks" checked="checked" /> show linkage-group blocks</label>
    <a class="example-link" target="_blank" rel="noopener" href="<?php echo msr_h($payload['dl']['linkage']); ?>">⬇ linkage groups (TSV)</a>
    <a class="example-link" target="_blank" rel="noopener" href="<?php echo msr_h($payload['dl']['anchors']); ?>">⬇ anchor pairs (TSV)</a>
    <a class="example-link" href="macrosynteny_result.php?species1=<?php echo urlencode($S2['display_name']); ?>&amp;species2=<?php echo urlencode($S1['display_name']); ?>">⇄ swap axes</a>
</div>

<div class="msr-legend" id="msr-legend"></div>

<p class="msr-hint" style="margin:0 0 10px;">
    <b>Reading the grid:</b> it has two zoom levels, and switches between them on its own.
    In <b>cell view</b> each cell is one sequence pair (chromosome, scaffold or contig) — its tint is the
    linkage group, the number inside is how many anchors that pair shares, and a white cell means the two
    sequences have no anchor in common.
    Zoom in past ~5&nbsp;px&nbsp;per&nbsp;anchor and it becomes <b>anchor view</b>: one dot per anchor, which is
    where you look up an individual gene. Drag to pan; zoom with the <b>−</b>&thinsp;/&thinsp;<b>+</b> buttons
    in the corner of the figure (the same ones, with <b>⟲ Reset</b>, sit in the top right of the plot
    so they stay within reach once you are zoomed in),
    <b>Ctrl</b>&nbsp;+&nbsp;mouse wheel (<b>&#8984;</b>&nbsp;+&nbsp;wheel on a Mac) or a <b>double-click</b>
    &mdash; zooming keeps the point under the pointer in place, so whatever you aim at stays put.
    <b>Fit</b> shows the whole grid, <b>1:1</b> puts one anchor on one pixel and <b>Full screen</b> gives it the
    whole window. Hover a cell or a dot for details; <b>clicking a cell</b> zooms to that block,
    <b>clicking a dot</b> pins its panel open so its gene links can be clicked. <b>Esc</b> (or a click on empty
    space) closes a pinned panel.
</p>

<p class="msr-hint" style="margin:0 0 10px;">
    <b>Two things worth knowing about the axes.</b>
    <b>Fragmented assemblies:</b> some of these genomes are still in thousands of unplaced scaffolds, and a
    handful of that species&rsquo; scaffolds carry almost all of the anchors. The chromosomes, scaffolds or contigs
    that hold the anchors are drawn and named one by one; everything past <b>Labels per axis</b> is merged into the
    light band at the end of the axis, labelled <b>other</b> with how many sequences and anchors it holds. Nothing
    is thrown away &mdash; every anchor in that band is still plotted, and in cell view each sequence on the
    other axis gets one block showing how many anchors it shares with the whole band &mdash; but with
    <b>Fill canvas</b> on, the band is <b>squeezed to keep it from eating the axis</b>, so it is then
    <b>not to scale</b>. Raise <b>Labels per axis</b> to give more of it its own column.
    <b>Fill canvas</b> (on by default) scales each axis to the panel separately, which is what makes a square
    grid use a wide screen instead of sitting in the middle of it with white space either side; the price is
    that the two axes are then at different scales. <b>Uncheck it for the equal-scale version</b> &mdash; the
    grid then goes back to being drawn to scale on both axes (the band included), which is the honest picture
    for a pair of chromosome-level assemblies.
</p>

<div class="msr-plot" id="msr-plot">
<div class="msr-grid-wrap" id="msr-grid-wrap">
    <svg id="msr-svg" xmlns="http://www.w3.org/2000/svg"></svg>
</div>
<?php /* 图的右上角常驻缩放控制条。画区本身是 overflow:auto 的滚动区，所以它放在滚动区
     外面、按 .msr-plot 定位：滚到图中间、滚到右下角，它都还在右上角。
     三个按钮接的是工具条上同一批函数，读数与工具条同步（都在 render() 里更新）。
     全屏时容器变成整屏的 fixed，这一条也跟着改成窗口右上角（见 CSS 里的兄弟选择器）。 */ ?>
<div class="msr-zoomctl" id="msr-zoomctl" role="group" aria-label="Zoom the Oxford grid">
    <button type="button" class="msr-zbtn" id="msr-zc-out" title="Zoom out — the centre of the view stays put">−</button>
    <span class="msr-zval" id="msr-zc-val" title="Zoom relative to the fitted view (100% = the whole grid fits the panel)">100%</span>
    <button type="button" class="msr-zbtn" id="msr-zc-in" title="Zoom in — the centre of the view stays put">+</button>
    <button type="button" class="msr-zbtn msr-zreset" id="msr-zc-reset" title="Reset the view — back to the whole grid, fitted to the panel (100%). Same as Fit.">⟲ Reset</button>
</div>
</div>
<div class="msr-tooltip" id="msr-tooltip"></div>
<div class="msr-hint" id="msr-status" style="margin-top:8px;"></div>

<div class="msr-section-title">Chromosome pairs &mdash; significance and linkage groups</div>
<div class="msr-table-scroll">
<table class="gridtable" id="msr-linkage-table">
  <tr>
    <th><?php echo msr_h($S1['display_name']); ?> chr</th>
    <th><?php echo msr_h($S2['display_name']); ?> chr</th>
    <th title="number of anchors on this chromosome pair">Anchors</th>
    <th title="raw p-value of the Fisher exact test">p</th>
    <th title="q-value after BH correction">q (BH)</th>
    <th>Significant</th>
    <th title="linkage group letter; 0 = not in any linkage group">Linkage group</th>
    <th title="Spearman correlation of anchor order; its sign alone does NOT define the orientation — see the next column">&rho;</th>
    <th title="collinear / inverted require |rho| to reach the critical value for this pair's anchor count at two-sided p &lt; 0.001 (0.57 at 30 anchors, 0.32 at 100, 0.10 at 1000); otherwise the pair is 'unordered', i.e. significantly linked but with no determinable order. Blank when the pair is not significant.">Orientation</th>
  </tr>
<?php foreach ($table_rows as $r): ?>
  <tr data-c1="<?php echo msr_h($r['c1']); ?>" data-c2="<?php echo msr_h($r['c2']); ?>">
    <td><?php echo msr_h($r['c1']); ?></td>
    <td><?php echo msr_h($r['c2']); ?></td>
    <td class="num"><?php echo number_format($r['n']); ?></td>
    <?php /* Fisher 精确检验在这些高度保守的区块上会下溢成 0.0（macrosynteny_linkage
           里有 4,044 行 pval 与 pval_adj 同时是 0）。PHP 的 %e 遇到精确的 0 会印成
           「0.00e+0」—— 指数位不补零，是个坏掉的浮点写法，而旧代码走不到 <1e-4 分支
           之外的地方：0 走 %.2e，于是整列都是 0.00e+0。0 当作 p 值没有意义（真值只是
           小于双精度能表示的范围），所以这一类单独印成不等式并给出解释。 */ ?>
    <td class="num"><?php
        if ($r['p'] === null) { echo '—'; }
        elseif ((float)$r['p'] == 0.0) {
            echo '<span title="The Fisher exact test underflowed to zero in double precision, so the true p-value is below roughly 1e-300.">&lt;1e-300</span>';
        } elseif ($r['p'] < 1e-4) { echo sprintf('%.2e', $r['p']); }
        else { echo sprintf('%.4f', $r['p']); }
    ?></td>
    <td class="num"><?php
        if ($r['q'] === null) { echo '—'; }
        elseif ((float)$r['q'] == 0.0) {
            echo '<span title="The Benjamini-Hochberg adjusted value underflowed to zero in double precision, so the true q-value is below roughly 1e-300.">&lt;1e-300</span>';
        } elseif ($r['q'] < 1e-4) { echo sprintf('%.2e', $r['q']); }
        else { echo sprintf('%.4f', $r['q']); }
    ?></td>
    <td><?php echo $r['sig'] ? '<span class="msr-sig">yes</span>' : '<span class="msr-ns">no</span>'; ?></td>
    <td><?php echo $r['clust'] === '0' ? '<span class="msr-ns">0</span>' : '<b>' . msr_h($r['clust']) . '</b>'; ?></td>
    <td class="num"><?php echo $r['rho'] === null ? '—' : sprintf('%+.2f', $r['rho']); ?></td>
    <td><?php echo msr_h($r['ori'] === null ? '' : $r['ori']); ?></td>
  </tr>
<?php endforeach; ?>
</table>
</div>
<div class="msr-hint">
    Sorted by significant &rarr; anchor count, <?php echo count($table_rows); ?> rows in total; only chromosome
    pairs with &ge; 3 anchors are listed (pairs sharing 1&ndash;2 anchors are treated as noise, though their
    anchors are still drawn as grey dots above; pairs that are significant or already assigned to a linkage
    group are listed even with fewer anchors).
    Click a row to zoom to that chromosome block in the plot and highlight it.
</div>

<script type="text/javascript">
/*<![CDATA[*/
var MSR_DATA = <?php echo msr_json($payload); ?>;
MSR_VIEWER.init(MSR_DATA, 'msr-svg', 'msr-legend', 'msr-tooltip', 'msr-grid-wrap');
/*]]>*/
</script>

<?php endif; ?>
<?php msr_page_foot(); ?>
