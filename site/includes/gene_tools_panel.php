<?php
/**
 * gene_detail.php：「这个基因能拿去做哪些分析」面板。
 *
 * 审稿意见 Referee 2 major 1：基因页此前是死胡同 —— 看完注释/表达就没了，
 * 而站点其实有好几个可以直接吃一个基因号的分析工具，只是互相之间没有链接。
 * 这里把入口集中起来，并且**每一个都把基因预填好**，读者点进去就能跑。
 *
 * 每个入口的可行性都不一样，所以每一项都带一个状态标签，宁可说「这个基因不在
 * 序列表里」也不要给一个点开必然报错的链接：
 *
 *   - Primer Design：primer3.html 是本站原生的 primer3_core 前端（不是 iframe），
 *     参数走 $_GET，`run=1` 会让**服务端**在同一次请求里直接跑完并渲染结果，
 *     所以链接本身就是一次完整调用。它的模板按 `gene` / `transcript` 精确匹配
 *     <ABBR>_seq，命不中时会明确提示。
 *   - BLAST：blast.php 原本只认 POST（GET 会落进空输入分支，白跑一次 blastall
 *     还往 tmp/ 里写垃圾），本面板依赖的是 2026-09 给它加的 GET 引导：按 sp/gid
 *     取序列、预填表单。**刻意不自动提交** —— 一次比对要几十秒到两分钟，不能让
 *     任何爬到带参 URL 的东西把它触发起来。
 *   - Network heatmap：同样的原因，network_heatmap.php 现在也接受 $_GET。
 *     它按 <ABBR>_TPM.Gene 精确取行，所以这里传的是 TPM 表里那个写法，而不是
 *     页面上带下来的原号。
 *   - 同源家族 / 基因集富集：这两页本来就接受 GET（genefamily.php?q=、
 *     GSEA.php?gsea_in=），直接链过去即可。
 */

require_once __DIR__ . '/gene_panels_common.php';

if (!function_exists('cnido_gtl_seq_hit')) {
    /**
     * 这个基因在 <ABBR>_seq 里有没有序列（探针引物设计与 BLAST 都要用它做模板）。
     *
     * @return string 'yes' | 'no' | 'unknown'（查不动 / 表不存在时 unknown，
     *                调用方按 unknown 处理：仍然给链接，但不打「没找到」的标签）
     */
    function cnido_gtl_seq_hit($conn, $abbr, $gene)
    {
        if ($conn === null || !preg_match('/^[A-Za-z0-9_]+$/', (string)$abbr)) {
            return 'unknown';
        }
        $t = $abbr . '_seq';
        if (!cnido_gp_table_exists($conn, $t)) {
            return 'unknown';
        }
        foreach (cnido_gp_id_candidates($gene) as $c) {
            $e = mysqli_real_escape_string($conn, $c);
            /* 三列都要查。表里 gene / transcript / protein 装的常常是三种写法：
               XP_048580524.1 这一行是 gene=LOC116613690、transcript=rna-XM_048724567.1、
               protein=XP_048580524.1 —— 只查前两列时，站点号原文（就是 protein 列）
               反而查不到，页面显示「not in the sequence table」而序列明明在。
               序列表是否给这三列建了索引不确定，设个上限：查不动就说不知道，
               绝不当成「没有」。 */
            $q = @mysqli_query($conn, "SELECT /*+ MAX_EXECUTION_TIME(1500) */ 1 FROM `$t` "
                                    . "WHERE gene = '$e' OR transcript = '$e' OR protein = '$e' LIMIT 1");
            if ($q && mysqli_fetch_row($q)) {
                return 'yes';
            }
        }
        return 'no';
    }
}

if (!function_exists('render_gene_tools_panel')) {
    /**
     * @param string      $gene    基因号
     * @param string|null $species 物种（拉丁名优先）
     * @param mysqli|null $conn    现成连接
     */
    function render_gene_tools_panel($gene, $species = null, $conn = null)
    {
        $gene = trim((string)$gene);
        if ($gene === '') {
            return;
        }
        $conn = cnido_rxs_conn($conn);
        $abbr = cnido_gp_abbr($species, $gene, $conn);

        cnido_gp_nav_add('tools', 'Analysis tools', '');

        cnido_gp_card_open(
            'What you can do with this gene',
            'Every tool below opens with this gene already entered, so you land on the analysis rather than on an '
            . 'empty form. Links open in a new tab.',
            '',
            'tools'
        );

        $items = array();

        if ($abbr !== null) {
            $hit = cnido_gtl_seq_hit($conn, $abbr, $gene);
            if ($hit === 'yes') {
                $seqPill = '<span class="gp-pill gp-yes">template found</span>';
            } elseif ($hit === 'no') {
                $seqPill = '<span class="gp-pill gp-warn">not in the sequence table</span>';
            } else {
                $seqPill = '<span class="gp-pill gp-no">sequence table not available</span>';
            }

            $items[] = array(
                'Primer design',
                '/primer3plus/primer3.html?sp=' . urlencode($abbr) . '&amp;gid=' . urlencode($gene) . '&amp;run=1',
                'Design PCR / qPCR primers on this gene&rsquo;s sequence with the published primer3 settings. '
                . 'Opens with the primers already computed.',
                $seqPill,
                ($hit !== 'unknown'),
            );

            $items[] = array(
                'BLAST',
                '/blast/blast.php?sp=' . urlencode($abbr) . '&amp;gid=' . urlencode($gene),
                'Search this gene&rsquo;s sequence against the CnidoSite BLAST databases. The query is filled in '
                . 'and the species&rsquo; database is preselected &mdash; press <b>Run</b> to start.',
                $seqPill,
                ($hit !== 'unknown'),
            );

            /* 网络热图按 <ABBR>_TPM.Gene 精确取行，传原号很可能取不到，所以
               先在服务端用与表达面板同一套解析把行找出来。 */
            $tpmGene = '';
            if (cnido_gp_table_exists($conn, $abbr . '_TPM')) {
                $row = cnido_rxs_row($conn, $abbr, $gene);
                if ($row !== null && isset($row[0]) && trim((string)$row[0]) !== '') {
                    $tpmGene = trim((string)$row[0]);
                }
            }
            $items[] = array(
                'Expression heatmap',
                ($tpmGene !== ''
                    ? '/cytoscape/network_heatmap.php?species=' . urlencode($abbr) . '&amp;genelist=' . urlencode($tpmGene)
                    : ''),
                'Draw this gene&rsquo;s expression as a heatmap across all RNA-seq samples, and compare it with '
                . 'the genes you add next to it.',
                ($tpmGene !== ''
                    ? '<span class="gp-pill gp-yes">ready</span>'
                    : '<span class="gp-pill gp-no">no expression matrix</span>'),
                ($tpmGene !== ''),
            );
        }

        $items[] = array(
            'Gene family / orthogroup',
            '/genefamily.php?q=' . urlencode($gene),
            'Look this gene up in the single-copy orthogroups built from the high-quality cnidarian genomes, and '
            . 'see which other species carry an orthologue.',
            '',
            true,
        );

        $items[] = array(
            'Gene set analysis',
            '/GSEA/GSEA.php?gsea_in=' . urlencode($gene),
            'Start a gene-set enrichment analysis with this gene as the seed list. Add more genes on that page '
            . 'before running it.',
            '',
            true,
        );

        echo '<table class="gridtable gp-tbl"><tr><th>Analysis</th><th>What it does</th><th>Status</th>'
           . '<th width="8%">Open</th></tr>';
        foreach ($items as $it) {
            list($label, $url, $desc, $pill, $ok) = $it;
            /* 最后一格 nowrap：不加的话 "open →" 会在箭头前断行，四行都变成
               「open」+ 单独一行箭头。 */
            echo '<tr><td><b>' . $label . '</b></td><td style="font-size:15px;line-height:1.6">' . $desc . '</td>'
               . '<td>' . $pill . '</td><td style="white-space:nowrap">';
            if ($ok && $url !== '') {
                /* $url 全是站内页（primer3.html / blast.php / 表达热图 / genefamily.php /
                   GSEA.php），按口径留当前窗口 —— 与顶部 Tools 菜单里同几个入口一致。 */
                echo '<a class="gp-a" href="' . $url . '">open &rarr;</a>';
            } else {
                echo '<span style="color:#64748b">&ndash;</span>';
            }
            echo '</td></tr>';
        }
        echo '</table>';

        if ($abbr === null) {
            echo '<p class="gp-none">The species could not be resolved, so the sequence-based tools are not offered. '
               . 'The family and gene-set searches above work on the gene ID alone.</p>';
        }

        cnido_gp_card_close();
    }
}
