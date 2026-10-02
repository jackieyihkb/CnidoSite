<?php
/**
 * gene_detail.php：表观修饰面板。
 *
 * 审稿意见 Referee 2 major 1：本页此前完全不提表观数据。这里回答三个问题 ——
 * 该物种有哪些表观数据、这个基因上有什么修饰、峰落在基因的哪个部位。
 *
 * 两类表观数据在这张页面上的待遇**必须不同**，因为它们的体量差两个数量级：
 *
 *   1. 峰表（<ABBR>_ATAC / _ChIP / _DHS，6 张，最大 NVECT_ChIP 43 万行 / 91 MB）
 *      可以直接按基因查，实测单基因一次全表扫描 ~0.3 秒，所以逐样本列出峰数
 *      与 peak_location 分布。仍加 MAX_EXECUTION_TIME 兜底，被中断时明说
 *      「没查到」而不是显示成「没有峰」——这两件事不能混。
 *
 *   2. 亚硫酸氢盐表（<ABBR>_BS_*，33 张，合计 114 GB，最大单表 14.5 GB）
 *      **绝不查**。这些表一个索引都没有，单基因 SELECT 就是一次全表扫描，
 *      实测最大的几张会被服务端直接中断（ERROR 3024）。本面板只从
 *      information_schema 读表名（零成本）列出「哪些样本有甲基化数据」，
 *      并深链到 DNA_methylation.php 由那一页自己去查。
 *      加索引是另一件事，磁盘只剩 34 GB，不能顺手做。
 *
 * 覆盖率矩阵里的「Epigenome」那一列同样只统计表的存在与行数，口径一致。
 */

require_once __DIR__ . '/gene_panels_common.php';

/** 本面板要试的峰表后缀 -> 展示名。顺序即展示顺序。 */
if (!defined('CNIDO_GEP_PEAKS')) {
    define('CNIDO_GEP_PEAKS', serialize(array(
        'ChIP' => 'ChIP-seq',
        'ATAC' => 'ATAC-seq',
        'DHS'  => 'DNase-seq (DHS)',
    )));
}

if (!function_exists('cnido_gep_peak_rows')) {
    /**
     * 一个基因在某张峰表里命中的峰，按 (样本, 峰位置) 汇总。
     *
     * @return array array('ok'=>bool, 'err'=>string, 'rows'=>array(sample => array(loc => n)))
     */
    function cnido_gep_peak_rows($conn, $table, $gene)
    {
        $out = array('ok' => true, 'err' => '', 'rows' => array());
        $cands = cnido_gp_id_candidates($gene);
        if (!$cands) {
            return $out;
        }
        /* 号可能有多种写法，拼成一个 IN 列表一次扫完，比逐个写法各扫一遍省。
           每个候选都独立转义，不把用户输入拼进 SQL 文本。 */
        $esc = array();
        foreach ($cands as $c) {
            $esc[] = "'" . mysqli_real_escape_string($conn, $c) . "'";
        }
        /* protein_id 上没有索引，这一步必然是全表扫描 —— 用优化器提示设一个
           上限，宁可拿不到结果，也不能让基因页卡在那里等。 */
        $sql = "SELECT /*+ MAX_EXECUTION_TIME(3000) */ Sample, peak_location, COUNT(*) AS n "
             . "FROM `$table` WHERE protein_id IN (" . implode(',', $esc) . ") "
             . "GROUP BY Sample, peak_location ORDER BY NULL";
        $q = @mysqli_query($conn, $sql);
        if (!$q) {
            $out['ok'] = false;
            $out['err'] = mysqli_error($conn);
            return $out;
        }
        while ($r = mysqli_fetch_row($q)) {
            $s = (string)$r[0];
            $loc = trim((string)$r[1]);
            if ($loc === '') { $loc = 'unannotated'; }
            if (!isset($out['rows'][$s])) { $out['rows'][$s] = array(); }
            $out['rows'][$s][$loc] = (int)$r[2];
        }
        return $out;
    }
}

if (!function_exists('cnido_gep_bs_marks')) {
    /**
     * 该物种的亚硫酸氢盐样本名。只读 information_schema，不碰数据表。
     *
     * 表名形如 <ABBR>_BS_<mark>，DNA_methylation.php 的 hisMark 参数要的正是
     * 去掉前缀后的 <mark>。
     */
    function cnido_gep_bs_marks($conn, $abbr)
    {
        $out = array();
        if ($conn === null || !preg_match('/^[A-Za-z0-9_]+$/', (string)$abbr)) {
            return $out;
        }
        $like = mysqli_real_escape_string($conn, $abbr . '\_BS\_%');
        $q = @mysqli_query($conn, "SELECT table_name FROM information_schema.tables "
                                . "WHERE table_schema = DATABASE() AND table_name LIKE '$like' "
                                . "ORDER BY table_name");
        $pre = $abbr . '_BS_';
        while ($q && ($r = mysqli_fetch_row($q))) {
            $t = (string)$r[0];
            if (strpos($t, $pre) === 0) {
                $out[] = substr($t, strlen($pre));
            }
        }
        return $out;
    }
}

if (!function_exists('cnido_gep_bs_url')) {
    /** DNA Methylation 页的深链。该页 2026-09 起优先读 GET，链接才真正生效。 */
    function cnido_gep_bs_url($abbr, $mark, $gene)
    {
        return '/DNA_methylation.php?species=' . urlencode($abbr)
             . '&hisMark=' . urlencode($mark)
             . '&gene=' . urlencode($gene);
    }
}

/* ---------------------------------------------------------------------------
 * 样本清单：这个物种在这张峰表里有**哪些**样本、每个样本一共多少峰。
 *
 * 为什么要一个预先算好的文件：峰表在 Sample 上没有索引（只有 protein_id 有），
 * GROUP BY Sample 是整表扫描 + 临时表 —— 实测 NVECT_ChIP 一次 1.4 秒，而按基因
 * 查只要 1 毫秒（走 idx_protein_id）。放在每次页面请求里等于给基因页加一秒多。
 * 所以清单由命令行生成（/var/www/cnidosite-tools/src/build_epigenome_samples.php），
 * 页面只读这份 JSON。同一张表里「这个基因有多少峰」仍然是现场查的，永远是最新的。
 *
 * 文件缺失时返回空数组，面板退化成「只列有峰的样本」的旧行为 —— 不会因为缺文件
 * 而报错或少印已经查到的东西（调用处对两者取并集）。
 * ------------------------------------------------------------------------- */
if (!function_exists('cnido_gep_inventory')) {
    function cnido_gep_inventory($table)
    {
        static $all = null;
        if ($all === null) {
            $all = array();
            $root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : '';
            $paths = array();
            if ($root !== '') { $paths[] = $root . '/data/epigenome_samples.json'; }
            $paths[] = dirname(__DIR__) . '/data/epigenome_samples.json';
            foreach ($paths as $p) {
                if (!@is_file($p)) { continue; }
                $j = json_decode((string)@file_get_contents($p), true);
                if (is_array($j) && isset($j['tables']) && is_array($j['tables'])) {
                    $all = $j['tables'];
                }
                break;
            }
        }
        return (isset($all[$table]['samples']) && is_array($all[$table]['samples']))
             ? $all[$table]['samples'] : array();
    }
}

if (!function_exists('cnido_gep_assay_url')) {
    /**
     * 某个样本在分析页上的深链。
     *
     * 三个分析页收的是同一对参数名（species / gene / hisType / hisMark）：
     *   · ChIP_analysis.php   —— Sample = hisType . '_' . hisMark，
     *                            但 hisType == 'pSMAD1/5' 时 Sample 就是 hisMark；
     *   · ATAC_analysis.php   —— $pic2 = $specie."_ATAC_".$hisMark，Sample 就是 hisMark，
     *   · DHS_analysis.php    —— 同上，$hisMark。
     * 后两个的 hisType 只是下拉框里的分组标签。
     *
     * 这个拆分**不在这里猜**：它由生成器从三个页面各自的 $__dolMap 反推后写进
     * JSON。清单里没有这个样本（例如表里有、下拉框里没有的那个 LD_CT_13h）时，
     * 只带物种和基因打开分析页，不编一个 hisMark 出来。
     */
    function cnido_gep_assay_url($page, $abbr, $gene, $sample)
    {
        $u = '/' . $page . '?species=' . urlencode($abbr) . '&gene=' . urlencode($gene);
        if (isset($sample['hisType']) && isset($sample['hisMark'])) {
            $u .= '&hisType=' . urlencode($sample['hisType'])
                . '&hisMark=' . urlencode($sample['hisMark']);
        }
        return $u;
    }
}

if (!function_exists('render_gene_epigenome_panel')) {
    /**
     * @param string      $gene    基因号
     * @param string|null $species 物种（拉丁名优先）
     * @param mysqli|null $conn    现成连接
     */
    function render_gene_epigenome_panel($gene, $species = null, $conn = null)
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

        $peaks = unserialize(CNIDO_GEP_PEAKS);
        /* 每种峰表对应的分析页。行内深链和卡片底部的出口都用它，写一处。 */
        $PAGES = array(
            'ChIP' => 'ChIP_analysis.php',
            'ATAC' => 'ATAC_analysis.php',
            'DHS'  => 'DHS_analysis.php',
        );

        /* 先把存在的峰表找出来，没有的话整张卡片退化成一行提示。 */
        $have = array();
        foreach ($peaks as $suffix => $label) {
            $t = $abbr . '_' . $suffix;
            if (cnido_gp_table_exists($conn, $t)) {
                $have[$suffix] = $t;
            }
        }
        $bsMarks = cnido_gep_bs_marks($conn, $abbr);

        /* 跳转条的条目。峰表命中的样本数要等渲染时才数得出，所以先登记一个只报
           「有几种检测」的版本；渲染完会用真实数字覆盖它（同一个 id 后写的胜，
           见 cnido_gp_nav_add）。整张卡片都不出现的情形（既无峰表也无甲基化）
           不在这里判断 —— 那种情况下面直接 return，不留空卡片。 */
        cnido_gp_nav_add(
            'epigenome',
            'Epigenetic marks',
            $have ? count($have) . ' assay' . (count($have) === 1 ? '' : 's')
                  : ($bsMarks ? 'methylation only' : 'no data'),
            ($have || $bsMarks) ? '' : 'none'
        );

        cnido_gp_card_open(
            'Epigenetic marks',
            'Chromatin and DNA-methylation data covering this gene. <b>Every sample</b> of every assay is listed, '
            . 'with the number of peaks it has at this gene and the number of peaks it has in total, so a sample '
            . 'that does not cover the gene can be told apart from one that simply has little data. DNA methylation '
            . 'is listed as sample availability only, because those tables are queried by the '
            . '<a class="gp-a" href="/DNA_methylation.php">DNA Methylation</a> page itself.',
            '',
            'epigenome'
        );

        if (!$have && !$bsMarks) {
            echo '<p class="gp-none">No epigenomic data has been deposited for this species. The assays that exist '
               . 'across the site are described in the '
               . '<a class="gp-a" href="/epigenomic_data.php">Epigenomic Data</a> module.</p>';
            cnido_gp_card_close();
            return;
        }

        /* ---------------- 峰表 ---------------- */
        $nSamples = 0; $nHitSamples = 0;
        if ($have) {
            /* data-no-sort：Assay 那一列是**分组标签**，只有每组第一行印字（下面用
               $first 控制），其余行是空格。js/table-sort.js 一旦按 Sample / Peaks
               重排，标签就会跟着某一行跑到别的组里去，读者会以为那一行是 ChIP-seq。
               这和内存里「表体有 rowspan 就要 data-no-sort」是同一类问题。 */
            echo '<table class="gridtable gp-tbl" data-no-sort><tr><th>Assay</th><th>Sample</th>'
               . '<th>This gene</th><th>Region of the peak</th><th class="num">Peaks in sample</th>'
               . '<th>Open</th></tr>';

            $errors = array();
            /* 每个样本一行，**包括没有峰的样本**（用户 2026-09-28 的要求：每个数据都
               要能看见这个基因在它里面的情况）。样本清单来自预生成的
               data/epigenome_samples.json，与现场查到的命中样本取并集 —— 文件旧了
               也不会把有峰的样本藏掉，只是那个样本的「Peaks in sample」印不出来。 */
            $assayRows = array();   // 攒好再按 assay 打印，表头只出现一次

            foreach ($have as $suffix => $table) {
                $page  = isset($PAGES[$suffix]) ? $PAGES[$suffix] : '';
                $res   = cnido_gep_peak_rows($conn, $table, $gene);
                if (!$res['ok']) {
                    $errors[] = $peaks[$suffix] . ' (' . cnido_gp_h($res['err']) . ')';
                    continue;
                }
                $inv = cnido_gep_inventory($table);

                $rows = array();
                foreach ($res['rows'] as $sample => $locs) {
                    $rows[$sample] = array('sample' => $sample, 'locs' => $locs, 'tot' => null);
                }
                foreach ($inv as $s) {
                    if (!is_array($s) || !isset($s['sample'])) { continue; }
                    $nm = (string)$s['sample'];
                    if (!isset($rows[$nm])) {
                        $rows[$nm] = array('sample' => $nm, 'locs' => array(), 'tot' => (int)$s['n']);
                    } else {
                        $rows[$nm]['tot'] = (int)$s['n'];
                    }
                    if (isset($s['hisType'])) { $rows[$nm]['hisType'] = $s['hisType']; }
                    if (isset($s['hisMark'])) { $rows[$nm]['hisMark'] = $s['hisMark']; }
                }
                if (!$rows) { continue; }

                /* 排序：有峰的排前面（峰多的更前），其余按样本自身的峰数从多到少。
                   读者先看到「这个基因在哪里有信号」，再看到「哪些地方没有」。 */
                $list = array_values($rows);
                usort($list, function ($a, $b) {
                    $an = count($a['locs']); $bn = count($b['locs']);
                    if (($an > 0) !== ($bn > 0)) { return $an > 0 ? -1 : 1; }
                    if ($an !== $bn) { return $bn - $an; }
                    $at = ($a['tot'] === null) ? -1 : $a['tot'];
                    $bt = ($b['tot'] === null) ? -1 : $b['tot'];
                    if ($at !== $bt) { return $bt - $at; }
                    return strcmp($a['sample'], $b['sample']);
                });

                $first = true;
                foreach ($list as $d) {
                    $nSamples++;
                    $total = 0; $bits = array();
                    $locs = $d['locs'];
                    arsort($locs);
                    foreach ($locs as $loc => $n) {
                        $total += $n;
                        $bits[] = cnido_gp_h($loc) . ' <span style="color:#64748b">' . (int)$n . '</span>';
                    }
                    $hit = ($total > 0);
                    if ($hit) { $nHitSamples++; }

                    /* 这个基因在这个样本里的状态。两种「没有」不一样，要分开说：
                       样本自己有几千个峰而没有这个基因的峰，是一句有信息量的结论；
                       样本自己只有几十个峰，本来就不太可能覆盖到它。 */
                    if ($hit) {
                        $cell = '<span class="gp-pill gp-yes">' . number_format($total)
                              . ' peak' . ($total === 1 ? '' : 's') . '</span>';
                    } else {
                        /* title 是访客悬停时看到的文字，必须英文（原来这两条是中文）。 */
                        $cell = '<span class="gp-pill gp-no" title="'
                              . ($d['tot'] === null
                                    ? 'The peak count for this sample could not be read (the sample list is older than the peak table), but a gene-wise lookup returns no peak for this gene'
                                    : 'This sample has ' . number_format($d['tot']) . ' peaks, none of them at this gene')
                              . '">no peak</span>';
                    }

                    $link = cnido_gep_assay_url($page, $abbr, $gene, $d);

                    echo '<tr>';
                    echo '<td>' . ($first ? '<b>' . cnido_gp_h($peaks[$suffix]) . '</b>' : '') . '</td>';
                    /* 拆出来的 hisType · hisMark 只在**样本名本身看不出来**时才印：
                       ChIP 的 H3K4me2_polyps 已经把两段写在一起了，再印一遍是废话；
                       而 pSMAD1/5 的 late_planula_4d（Sample 就是 hisMark）和 ATAC 的
                       LD_CT_21h（hisType 只是分组标签）光看样本名看不出这两段是什么。 */
                    $showSplit = isset($d['hisType']) && isset($d['hisMark'])
                              && ($d['sample'] !== $d['hisType'] . '_' . $d['hisMark']);
                    echo '<td>' . cnido_gp_h($d['sample'])
                       . ($showSplit
                            ? '<br /><span style="color:#64748b;font-size:12px">' . cnido_gp_h($d['hisType'])
                              . ' &middot; ' . cnido_gp_h($d['hisMark']) . '</span>'
                            : '')
                       . '</td>';
                    echo '<td>' . $cell . '</td>';
                    echo '<td style="font-size:12px;line-height:1.7">'
                       . ($bits ? implode(' &middot; ', $bits) : '<span style="color:#64748b">&ndash;</span>')
                       . '</td>';
                    echo '<td class="num">'
                       . ($d['tot'] === null ? '<span style="color:#64748b">&ndash;</span>' : number_format($d['tot']))
                       . '</td>';
                    /* $link 是站内的分析页（见 cnido_gep_assay_url），按口径留当前窗口。 */
                    echo '<td><a class="gp-a" href="' . cnido_gp_h($link) . '">'
                       . ($hit ? 'open this sample' : 'open the assay') . ' &rarr;</a></td>';
                    echo '</tr>';
                    $first = false;
                }
            }
            echo '</table>';

            /* 表头一句总结：读者要的答案（这个基因在表观数据里有没有信号）应该在
               看表之前就拿到。 */
            echo '<p class="gp-none">'
               . ($nHitSamples > 0
                    ? 'This gene overlaps a called peak in <b>' . $nHitSamples . '</b> of the <b>'
                      . $nSamples . '</b> sample' . ($nSamples === 1 ? '' : 's') . ' listed above. '
                      . 'Samples without a peak are listed too, each with the number of peaks it has in '
                      . 'total, so an absence can be read against the depth of that sample.'
                    : 'No called peak overlaps this gene in any of the <b>' . $nSamples . '</b> sample'
                      . ($nSamples === 1 ? '' : 's') . ' of the ' . count($have) . ' assay'
                      . (count($have) === 1 ? '' : 's') . ' that cover this species. '
                      . 'Either the gene is not near an accessible or marked region in those samples, or '
                      . 'it is not represented in the peak caller&rsquo;s annotation. The column of sample '
                      . 'totals is what makes this a statement about the gene rather than about the data.')
               . '</p>';

            if ($errors) {
                /* 查失败与「没有峰」是两回事，必须分开说。 */
                echo '<p class="gp-none" style="color:#b45309">Could not be read within the time limit for: '
                   . implode('; ', $errors) . '. This is a query limitation, not evidence that the gene has no peak.</p>';
            }

            /* 到分析页的深链：物种 + 本基因都带上，点开就是这次查询。
               物种参数这三页都收 abbr1 短码（ATAC / DHS 还会把拉丁名归一成短码，
               ChIP 不会 —— 所以统一传 $abbr）。gene 传的是页面上这个号，
               与分析页 WHERE protein_id = ? 的列同一套写法（本面板取峰用的就是它）。
               行内的链接是「这个基因在这个样本里」，这里给的是「这个物种这个检测的
               全景」，两者都要。 */
            $links = array();
            foreach ($PAGES as $suffix => $page) {
                if (isset($have[$suffix])) {
                    $url = '/' . $page . '?species=' . urlencode($abbr)
                         . '&gene=' . urlencode($gene);
                    $links[] = cnido_gp_openbtn($url, 'Open in ' . $peaks[$suffix],
                        'sec',
                        'Open ' . $peaks[$suffix] . ' with this species and this gene already selected. '
                        . 'The viewer runs its own query, so it can take a moment.');
                }
            }
            if ($links) {
                echo '<div class="cn-openrow"><span class="cn-lbl">Open the full assay view:</span>'
                   . implode('', $links) . '</div>';
            }
        }

        /* 跳转条上那个点：绿=这个基因至少在某个样本里落在峰内；灰=这些检测都覆盖了
           这个物种、但没有一个样本在这个基因上有峰。样本数用一个能放进胶囊的说法：
           命中数/样本数 比「22 samples」有信息量得多 —— 后者看不出「有我/没我」。 */
        if ($have && $nSamples > 0) {
            cnido_gp_nav_add(
                'epigenome',
                'Epigenetic marks',
                $nHitSamples . '/' . $nSamples . ' samples',
                $nHitSamples > 0 ? 'yes' : 'no'
            );
        }

        /* ---------------- 亚硫酸氢盐 / DNA 甲基化 ---------------- */
        if ($bsMarks) {
            echo '<h4 style="margin:14px 0 6px;font-size:15px;color:#334155">DNA methylation (bisulphite samples)</h4>';
            /* data-no-sort：这张表只有样本名 + 一个「open」链接，排序没有意义，
               表头出现两个 ↕ 只是噪声。 */
            echo '<table class="gridtable gp-tbl" data-no-sort><tr><th>Sample</th>'
               . '<th class="num">Methylation profile</th></tr>';
            foreach ($bsMarks as $m) {
                echo '<tr><td>' . cnido_gp_h($m) . '</td><td class="num">'
                   . '<a class="gp-a" href="' . cnido_gp_h(cnido_gep_bs_url($abbr, $m, $gene)) . '">'
                   . 'open &rarr;</a></td></tr>';
            }
            echo '</table>';
            /* 这些表没有索引，那一页是按基因做全表扫描的。样本越多、表越大，等得越久 ——
               这件事必须提前说，否则用户会以为链接点坏了。 */
            echo '<p class="gp-none">The DNA Methylation page locates this gene by scanning the bisulphite table, '
               . 'which holds one row per cytosine. For the largest datasets that scan takes a while to return; '
               . 'it does not mean the link is broken.</p>';
        }

        cnido_gp_card_close();
    }
}
