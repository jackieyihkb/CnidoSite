<?php
/**
 * gene_detail.php：共表达网络面板。
 *
 * 审稿意见 Referee 2 major 1（「基因页讲不清这个基因还能做什么」）：
 * 本页此前完全不提共表达。这里给出该基因在网络里的伙伴数（正 / 负两个方向），
 * 并直接深链到 Cytoscape 网络分析页，基因已经填好。
 *
 * 数据来源：<ABBR>_coexpress_positive / <ABBR>_coexpress_negative，两张都是
 * 全 TEXT 列、只有 geneA / geneB 各一条 64 字符前缀索引的宽表。因此：
 *   - 计数走两次纯索引范围扫描（geneA=? 一次、geneB=? 一次）再相加 ——
 *     一对基因在表里只存一行、方向固定，两个方向的和就是去重后的伙伴数，
 *     比 UNION 去重便宜且实测数值一致；
 *   - 绝不 SELECT *，也绝不对 TEXT 列做 LIKE。
 * 索引前缀 64 字符远长于任何基因号，所以 `col = 'X'` 是走索引的。
 *
 * 深链为什么落在 network.list.php 而不是 network.php：network.php 的基因输入框
 * 没有任何预填路径（它的「Example」链接指向一个全站都不存在的函数），而
 * network.list.php 四个参数都优先读 $_GET，纯 URL 就能完整触发一次查询。
 */

require_once __DIR__ . '/gene_panels_common.php';

if (!function_exists('cnido_gcx_counts')) {
    /**
     * 一个基因的共表达伙伴数。
     *
     * @return array array('abbr'=>…, 'gene'=>实际命中的写法, 'pos'=>int|null,
     *               'neg'=>int|null, 'pos_top'=>array|null, 'neg_top'=>array|null)
     *               pos / neg 为 null 表示该方向没有表（物种没做过网络）；
     *               两个都是 null 表示这个物种根本没有共表达数据。
     */
    function cnido_gcx_counts($conn, $abbr, $gene)
    {
        $out = array('abbr' => $abbr, 'gene' => '', 'pos' => null, 'neg' => null,
                     'pos_top' => null, 'neg_top' => null);

        $tPos = $abbr . '_coexpress_positive';
        $tNeg = $abbr . '_coexpress_negative';
        $hasPos = cnido_gp_table_exists($conn, $tPos);
        $hasNeg = cnido_gp_table_exists($conn, $tNeg);
        if (!$hasPos && !$hasNeg) {
            return $out;
        }

        /* 试到第一个「查得到伙伴」的写法为止。全都查不到时保留第一候选，
           好让调用方能照着它去拼链接（查不到 ≠ 号写错了，也可能就是没伙伴）。 */
        $cands = cnido_gp_id_candidates($gene);
        $hit = '';
        foreach ($cands as $c) {
            $e = mysqli_real_escape_string($conn, $c);
            $n = 0;
            $ok = true;
            foreach (array($tPos, $tNeg) as $t) {
                if (!cnido_gp_table_exists($conn, $t)) {
                    continue;
                }
                foreach (array('geneA', 'geneB') as $col) {
                    $q = @mysqli_query($conn, "SELECT COUNT(*) FROM `$t` WHERE $col = '$e'");
                    if (!$q) { $ok = false; break 2; }
                    $n += (int)mysqli_fetch_row($q)[0];
                }
            }
            if ($ok && $n > 0) { $hit = $c; break; }
        }
        if ($hit === '') {
            $hit = isset($cands[0]) ? $cands[0] : trim((string)$gene);
        }
        $e = mysqli_real_escape_string($conn, $hit);
        $out['gene'] = $hit;

        foreach (array('positive' => 'pos', 'negative' => 'neg') as $kind => $key) {
            $t = $abbr . '_coexpress_' . $kind;
            if (!cnido_gp_table_exists($conn, $t)) {
                continue;
            }
            $n = 0;
            foreach (array('geneA', 'geneB') as $col) {
                $q = @mysqli_query($conn, "SELECT COUNT(*) FROM `$t` WHERE $col = '$e'");
                if ($q) { $n += (int)mysqli_fetch_row($q)[0]; }
            }
            $out[$key] = $n;

            /* 该方向相关性最强的一个伙伴。正相关取 pcc 降序、负相关取升序 ——
               pcc 是 text 列，按字符串排会把 '-0.72' 排到 '-0.12' 前面（负相关
               越强越靠后），必须先 CAST。这条与 includes/network_graph.php
               里那个排序 bug 是同一件事。 */
            if ($n > 0) {
                $dir = ($kind === 'positive') ? 'DESC' : 'ASC';
                $q = @mysqli_query($conn,
                    "SELECT partner, pcc FROM ("
                  . "  SELECT geneB AS partner, pcc FROM `$t` WHERE geneA = '$e'"
                  . "  UNION ALL"
                  . "  SELECT geneA AS partner, pcc FROM `$t` WHERE geneB = '$e'"
                  . ") u ORDER BY CAST(pcc AS DECIMAL(10,9)) $dir LIMIT 1");
                if ($q && ($r = mysqli_fetch_row($q))) {
                    $out[$key . '_top'] = array('partner' => (string)$r[0], 'pcc' => (string)$r[1]);
                }
            }
        }
        return $out;
    }
}

if (!function_exists('cnido_gcx_pcc')) {
    /**
     * 相关系数的显示写法：3 位小数。
     *
     * pcc 在库里是 text 列，存的是 0.866045884663039 这样的全精度串（计数查询
     * 用 CAST(pcc AS DECIMAL(10,9)) 排序，值本身没被截过）。直接印出来是一列
     * 15 位数字，既读不出量级也对不齐；显示 3 位、原值挂 title。
     */
    function cnido_gcx_pcc($v)
    {
        if ($v === null || trim((string)$v) === '') { return '-'; }
        return number_format((float)$v, 3);
    }
}

if (!function_exists('cnido_gcx_url')) {
    /** 网络分析页的深链：positive / negative 两个方向都勾上。 */
    function cnido_gcx_url($abbr, $gene)
    {
        return '/cytoscape/network.list.php?organism=' . urlencode($abbr)
             . '&genelist=' . urlencode($gene)
             . '&group%5B%5D=positive&group%5B%5D=negative';
    }
}

if (!function_exists('render_gene_coexpress_panel')) {
    /**
     * @param string      $gene    基因号（按页面上的原样写法传）
     * @param string|null $species 拉丁名 / 下划线形式 / abbr1
     * @param mysqli|null $conn    现成连接
     */
    function render_gene_coexpress_panel($gene, $species = null, $conn = null)
    {
        $gene = trim((string)$gene);
        if ($gene === '') {
            return;
        }
        $conn = cnido_rxs_conn($conn);
        $abbr = cnido_gp_abbr($species, $gene, $conn);
        if ($abbr === null) {
            return;
        }

        $c = cnido_gcx_counts($conn, $abbr, $gene);

        cnido_gp_nav_add(
            'coexpression',
            'Co-expression network',
            ($c['pos'] === null && $c['neg'] === null)
                ? 'not built'
                : number_format((int)$c['pos'] + (int)$c['neg']) . ' partners',
            /* 三态：这个物种有网络而基因有伙伴 = 绿；有网络但这个基因一个伙伴都没有
               = 灰（「不在里面」）；连网络都没建 = 整条弱化（cn-off），那种情况下面
               会印一句说明指向 Network Analysis 页。 */
            ($c['pos'] === null && $c['neg'] === null)
                ? 'none'
                : (((int)$c['pos'] + (int)$c['neg']) > 0 ? 'yes' : 'no')
        );

        cnido_gp_card_open(
            'Co-expression network',
            'Genes whose expression across the transcriptome samples of <i>' . cnido_gp_h($species)
            . '</i> tracks this one. Counts are over the whole network; the network view itself draws at '
            . 'most <b>100 partners per query gene</b> (<code>CNIDO_NET_TOP_K</code>), so a hub gene can '
            . 'show fewer edges than the number below.',
            '',
            'coexpression'
        );

        if ($c['pos'] === null && $c['neg'] === null) {
            echo '<p class="gp-none">No co-expression network has been built for this species, so this gene '
               . 'has no partners to show. Networks are available for the species listed on the '
               . '<a class="gp-a" href="/cytoscape/network.php">Network Analysis</a> page.</p>';
            cnido_gp_card_close();
            return;
        }

        echo '<table class="gridtable gp-tbl"><tr><th>Direction</th><th class="num">Partners</th>'
           . '<th>Most correlated partner</th><th class="num" title="Pearson correlation coefficient between the two genes&rsquo; expression profiles across the species&rsquo; RNA-seq samples &mdash; +1 perfectly together, 0 unrelated, &minus;1 perfectly opposite. Its sign is what decides whether a pair appears in the positive or the negative table.">PCC</th></tr>';

        $rows = array(
            array('positive', 'Positively correlated', 'pos', ''),
            array('negative', 'Negatively correlated', 'neg', 'neg'),
        );
        foreach ($rows as $r) {
            list($kind, $label, $key, $cls) = $r;
            if ($c[$key] === null) {
                echo '<tr><td>' . $label . '</td><td class="num">-</td>'
                   . '<td colspan="2" style="color:#64748b">no table for this species</td></tr>';
                continue;
            }
            $top = $c[$key . '_top'];
            echo '<tr><td>' . $label . '</td>'
               . '<td class="num">' . number_format($c[$key]) . '</td>'
               . '<td>' . ($top ? '<a class="gp-a" href="/gene_detail.php?gene=' . urlencode($top['partner'])
                                   . '&amp;species=' . urlencode($species) . '">'
                                   . cnido_gp_h($top['partner']) . '</a>'
                                : ($c[$key] > 0
                                    ? '<span style="color:#64748b">none above the reporting threshold</span>'
                                    : '<span style="color:#64748b">not in this network</span>'))
               . '</td>'
               /* pcc 是 text 列，库里存的是 0.866045884663039 这种全精度串。原样印出来
                  一列 15 位数字既读不了也对不齐；显示 3 位小数，原值放在 title 里。 */
               . '<td class="num">' . ($top
                    ? '<span title="' . cnido_gp_h($top['pcc']) . '">' . cnido_gcx_pcc($top['pcc']) . '</span>'
                    : '-') . '</td></tr>';
        }
        echo '</table>';

        /* 两个方向都是 0 是这个基因确实不在网络里（该物种的网络只覆盖表达矩阵里
           有足够变异的基因）。不说明的话，两个 "0 / not in this network" 看起来
           像面板坏了 —— 实测 NVECT 的 XP_048580524.1 就是这种情形，库里的计数
           确实是 0。 */
        if ((int)$c['pos'] === 0 && (int)$c['neg'] === 0) {
            echo '<p class="gp-none">This gene has no edge at all in the '
               . cnido_gp_h($species) . ' network, in either direction &mdash; it is not one of the genes the '
               . 'network was built from (the network covers genes with enough expression variation across the '
               . 'transcriptome samples). The counts above are a property of the network, not a failed lookup.</p>';
        }

        /* 计数用的基因写法可能与 URL 上带下来的不同（-T1 那一类），说清楚，
           否则用户点开网络分析页看到空图会以为是链接坏了。 */
        $note = '';
        if ($c['gene'] !== $gene) {
            $note = ' Partner counts were matched on <code>' . cnido_gp_h($c['gene'])
                  . '</code>, the spelling this network uses.';
        }

        echo '<p class="gp-none">'
           /* cnido_gcx_url 给的是站内的 network.list.php，按口径留当前窗口。 */
           . '<a class="cn-open" href="' . cnido_gp_h(cnido_gcx_url($abbr, $c['gene'])) . '">'
           . 'Open the co-expression network &rarr;</a></p>'
           . '<p class="gp-none" style="margin-top:8px">'
           . 'The network opens with this gene already entered and both directions selected. '
           . 'There you can add up to 9 more genes, switch between the positive and negative network, and '
           . 'export the edge list.' . $note . '</p>';

        cnido_gp_card_close();
    }
}
