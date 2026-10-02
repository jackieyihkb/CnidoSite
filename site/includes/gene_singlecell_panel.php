<?php
/**
 * gene_detail.php：单细胞表达水平面板。
 *
 * 审稿意见 Referee 2 major 1：本页此前与单细胞模块没有任何联系 —— 读者看完
 * RNA-seq 表达谱就断线了，并不知道同一个基因在单细胞图谱的哪些细胞类型里
 * 表达、可以点进去看小提琴图。
 *
 * 关键约束（决定了这个面板长什么样）：**服务端没有「基因 × 细胞类型」的均值
 * 矩阵**。每个数据集的表达量只以「每细胞一个值」的稀疏向量存在
 * （singlecell_data/<DS>/expression/<native_id>.bin.gz，CX1 格式），均值/中位数/
 * 小提琴密度全是浏览器里现算的。所以本面板不编造任何表达数值，只做三件事：
 *   1. 列出该物种有哪几个单细胞数据集；
 *   2. 说清这个基因在每个数据集里处在什么状态；
 *   3. 给出深链，由 viewer 去画图。
 *
 * ---- 基因号为什么要逐数据集解析 ----
 * 单细胞数据集用的是**来源数据集自己的**基因号，而且同一个物种内不同数据集的
 * 写法还不一样。以 Nematostella 为例：NVECT_bodywall 用 NCBI 的 LOC116601122，
 * NVECT_nervous 用 NV2g018050000.1，NVECT_gastrula 用 NVE10817，NVECT_tentacle
 * 用 NV2.23465。站点号（XP_…）到这些号之间的桥有两座：
 *
 *   · singlecell_data/<DS>/gene_ids.json —— 每个数据集一份 sidecar，键就是该
 *     数据集自己的号，值是站点号（见 includes/sc_gene_ids.php 顶部说明）；
 *   · sc_gene_refseq 表 —— 物种级的号对照，粗细不一但覆盖面更广
 *     （NVECT 的 LOC… 与 NV2g… 都在里面）。
 *
 * 两座桥都试，**再用数据集自己的数据核对一遍**（有没有 expression/*.bin.gz、
 * 在不在 singlecell_markers 里）。核对这一步不能省：物种级的对照可能给出的是
 * 另一个数据集的号（LOC116613690 属于 bodywall 那套写法，拿去 NVECT_nervous
 * 里查必然落空），不核对就会把「号没对上」显示成「这个基因不表达」。
 *
 * 三态显示是模块既有约定：能看 / 只是 marker / 对不上号。对不上时显示
 * unmapped，**绝不猜**（见 includes/sc_gene_ids.php 的硬约束 3）。
 */

require_once __DIR__ . '/gene_panels_common.php';
/* sc_gene_id_map() / sc_gene_scid() / sc_gene_refseq_ok()：站点号 <-> 数据集号的
   官方桥，单细胞四个页面走的是同一份实现，这里不另写一套。只取这一个文件，
   不 require 整份 sc_common.php —— 那是单细胞模块四个页面的公共前置，塞进
   gene_detail.php 会把一堆用不上的东西也拖进来（它自己 require 本文件）。 */
require_once __DIR__ . '/sc_gene_ids.php';

if (!function_exists('cnido_gsc_norm')) {
    /** 物种名归一，用于把 atlas 里的物种和本页的物种对上（拉丁名 / 下划线形式）。 */
    function cnido_gsc_norm($s)
    {
        return strtolower(preg_replace('/\s+/', ' ', trim(str_replace('_', ' ', (string)$s))));
    }
}

if (!function_exists('cnido_gsc_datasets')) {
    /**
     * 该物种的单细胞数据集。表不存在 / 连不上库时返回空数组。
     *
     * singlecell_atlas 一共十几行，整表取回后在 PHP 里比对物种，比写一个
     * 带 LIKE 的查询更省事也更准（atlas.species 存的是拉丁名，大小写和
     * 下划线都不统一）。
     */
    function cnido_gsc_datasets($conn, $species)
    {
        if ($conn === null || !cnido_gp_table_exists($conn, 'singlecell_atlas')) {
            return array();
        }
        $want = cnido_gsc_norm($species);
        if ($want === '') {
            return array();
        }
        $out = array();
        $q = @mysqli_query($conn, "SELECT dataset_id, species, tissue_organ, stage, "
                                . "n_cells_final, n_cell_types FROM singlecell_atlas");
        while ($q && ($r = mysqli_fetch_assoc($q))) {
            if (cnido_gsc_norm($r['species']) === $want) {
                $out[] = $r;
            }
        }
        return $out;
    }
}

if (!function_exists('cnido_gsc_refseq_ids')) {
    /** sc_gene_refseq 里这个站点号对应的数据集号（物种级，可能为空）。 */
    function cnido_gsc_refseq_ids($conn, $abbr, $gene)
    {
        static $cache = array();
        $key = $abbr . "\t" . $gene;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $out = array();
        if ($conn === null || !preg_match('/^[A-Za-z0-9_]+$/', (string)$abbr)) {
            return $cache[$key] = $out;
        }
        /* 表在不在这件事自己判断，**不调用 sc_gene_refseq_ok()**：那个函数转发给
           sc_has_table()，而 sc_has_table() 定义在 46 KB 的 sc_common.php 里 ——
           本文件刻意只取 sc_gene_ids.php 这一个依赖（见文件顶部），于是
           function_exists('sc_has_table') 为假、sc_gene_refseq_ok() 恒返回 false，
           这条桥整个是死的（实测：10 个 NVECT 数据集全报 unmapped）。
           cnido_gp_table_exists() 查的是同一份 information_schema，且自带缓存。 */
        if (!cnido_gp_table_exists($conn, 'sc_gene_refseq')) {
            return $cache[$key] = $out;
        }
        $a = mysqli_real_escape_string($conn, $abbr);
        foreach (cnido_gp_id_candidates($gene) as $c) {
            $e = mysqli_real_escape_string($conn, $c);
            $q = @mysqli_query($conn, "SELECT sc_id FROM sc_gene_refseq "
                                    . "WHERE abbr = '$a' AND (accession = '$e' OR locus = '$e') LIMIT 5");
            while ($q && ($r = mysqli_fetch_row($q))) {
                $v = trim((string)$r[0]);
                if ($v !== '') { $out[$v] = true; }
            }
        }
        return $cache[$key] = array_keys($out);
    }
}

if (!function_exists('cnido_gsc_native')) {
    /**
     * 这个基因在某个数据集里的号。返回该数据集自己的号，或 ''。
     *
     * 顺序：数据集自己的 sidecar 优先（那是为这个数据集生成的，最准），
     * 再用物种级的 sc_gene_refseq 兜底。**返回前必须能被数据集自己的数据
     * 证实**，否则视为对不上号 —— 见文件顶部关于 LOC/NV2g 的说明。
     *
     * @return array array('native'=>号|'', 'source'=>'sidecar'|'refseq'|'', 'local'=>bool)
     *               local = 这个数据集自己有 ID 对照表；用来区分「对照表里没这个
     *               基因」和「根本没有对照表可查」，见调用处。
     */
    function cnido_gsc_native($conn, $abbr, $gene, $dataset)
    {
        $cands = array();
        $hasLocal = false;

        /* 1) 数据集自己的 sidecar：**这才是「数据集自己的号」的权威来源**，
              它的键就是该数据集 expression/ 里那些 .bin.gz 的文件名。 */
        $map = sc_gene_id_map($dataset);
        if ($map) {
            $hasLocal = true;
            $sc = sc_gene_scid($gene, $map);
            if ($sc !== '' && isset($map[$sc])) {
                $cands[$sc] = 'sidecar';
            }
        }

        /* 2) 物种级对照表：只在数据集自己没有对照表时才有价值。有 sidecar 时它
              给的是**别的数据集**的写法（NVECT 的 LOC… 对 NVECT_nervous 的
              NV2g… 毫无意义），拿它去凑只会把「号没对上」染成「不表达」。 */
        if (!$hasLocal && $abbr !== null) {
            foreach (cnido_gsc_refseq_ids($conn, $abbr, $gene) as $v) {
                if (!isset($cands[$v])) { $cands[$v] = 'refseq'; }
            }
        }

        /* 3) 用数据集自己的数据核实。先看表达向量文件（有它就一定能画），
              没有再看 marker 表（只是被标为 marker，viewer 画不出来）。 */
        foreach ($cands as $v => $src) {
            if (cnido_gsc_expr_file($dataset, $v)) {
                return array('native' => $v, 'source' => $src, 'local' => $hasLocal);
            }
        }
        foreach ($cands as $v => $src) {
            if (cnido_gsc_markers($conn, $dataset, $v)) {
                return array('native' => $v, 'source' => $src, 'local' => $hasLocal);
            }
        }
        /* 没核实上。'local' 交给调用方区分两种含义完全不同的「没有」。 */
        return array('native' => '', 'source' => '', 'local' => $hasLocal);
    }
}

if (!function_exists('cnido_gsc_expr_file')) {
    /**
     * 该基因在这个数据集里有没有导出的表达向量文件。
     *
     * 这是「viewer 能不能画出来」的唯一可靠判据：genes.json 只索引已导出的
     * 基因（每个数据集通常几百到几千个），marker 表里的基因未必在里面。
     * 文件名用的是数据集自己的号（不含目录穿越字符）。
     */
    function cnido_gsc_expr_file($dataset, $native)
    {
        if ($dataset === '' || $native === '') {
            return false;
        }
        if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $dataset) || !preg_match('/^[A-Za-z0-9_.\-]+$/', $native)) {
            return false;
        }
        $root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : '';
        $cands = array();
        if ($root !== '') {
            $cands[] = $root . '/singlecell_data/' . $dataset . '/expression/' . $native . '.bin.gz';
        }
        $cands[] = dirname(__DIR__) . '/singlecell_data/' . $dataset . '/expression/' . $native . '.bin.gz';
        foreach ($cands as $p) {
            if (@is_file($p)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('cnido_gsc_markers')) {
    /** 该基因在某个数据集里被标为 marker 的细胞类型（按 rank 升序，最多 5 条）。 */
    function cnido_gsc_markers($conn, $dataset, $native)
    {
        static $cache = array();
        $key = $dataset . "\t" . $native;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $out = array();
        if ($conn === null || $native === '' || !cnido_gp_table_exists($conn, 'singlecell_markers')) {
            return $cache[$key] = $out;
        }
        $st = @$conn->prepare("SELECT cell_type, `rank`, log2fc, pct_in FROM singlecell_markers "
                            . "WHERE dataset_id = ? AND gene = ? ORDER BY `rank` LIMIT 5");
        if (!$st) {
            return $cache[$key] = $out;
        }
        $st->bind_param('ss', $dataset, $native);
        $st->execute();
        $res = $st->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $out[] = $r;
        }
        $st->close();
        return $cache[$key] = $out;
    }
}

if (!function_exists('render_gene_singlecell_panel')) {
    /**
     * @param string      $gene    基因号
     * @param string|null $species 物种（拉丁名优先）
     * @param mysqli|null $conn    现成连接
     */
    function render_gene_singlecell_panel($gene, $species = null, $conn = null)
    {
        $gene = trim((string)$gene);
        if ($gene === '') {
            return;
        }
        $conn = cnido_rxs_conn($conn);

        $latin = trim((string)$species);
        if ($latin === '') {
            return;
        }
        $sets = cnido_gsc_datasets($conn, $latin);

        cnido_gp_card_open(
            'Single-cell expression',
            'Whether this gene can be visualised in the single-cell atlases of <i>' . cnido_gp_h($latin)
            . '</i>, and in which cell types it is a marker. Expression is stored per cell, so the '
            . '<b>violin plot</b> and the cell-type means are computed in the viewer &mdash; open a dataset to see them.',
            '',
            'singlecell'
        );

        if (!$sets) {
            cnido_gp_nav_add('singlecell', 'Single-cell expression', 'no dataset', 'none');
            echo '<p class="gp-none">No single-cell dataset has been published for this species. The atlases that '
               . 'do exist are listed in the <a class="gp-a" href="/cell_atlas.php">Cell Atlas</a>.</p>';
            cnido_gp_card_close();
            return;
        }

        $abbr = cnido_gp_abbr($species, $gene, $conn);
        $anyViewable = false;   // 至少一个数据集能把这个基因画出来
        $anyInDataset = false;  // 至少一个数据集自己的基因号表里有这个基因
        $anyAbsent = false;     // 至少一个数据集能**确定**地说「没有这个基因」


        echo '<table class="gridtable gp-tbl"><tr><th>Dataset</th><th>Tissue / stage</th><th class="num">Cells</th>'
           . '<th class="num">Cell types</th><th>This gene</th><th>Marker of</th></tr>';

        foreach ($sets as $d) {
            $ds = (string)$d['dataset_id'];
            $res = cnido_gsc_native($conn, $abbr, $gene, $ds);
            $native = $res['native'];


            $link = '';
            $linkLabel = '';
            $mkTxt = '<span style="color:#64748b">&ndash;</span>';

            if ($native === '' && !$res['local']) {
                /* 这个数据集**没有** ID 对照表（gene_ids.json 还没生成），物种级的
                   对照表也翻不出它的号。按模块约定「没对照表就不标」：不判断表达，
                   也不说不存在。 */
                /* title 是访客悬停时看到的文字，必须英文（这几条原来都是中文）。 */
                $pill = '<span class="gp-pill gp-no" title="This dataset has no gene-ID mapping table, so the site gene ID cannot be translated and no presence call is made">unmapped</span>';
            } elseif ($native === '') {
                /* 数据集自己的对照表里有几百个基因，这个不在其中 —— 这是一句
                   有依据的「不在这个数据集里」。 */
                $pill = '<span class="gp-pill gp-no" title="This gene is not in the dataset\'s own gene-ID mapping table">not in this dataset</span>';
                $anyAbsent = true;
            } else {
                $mk = cnido_gsc_markers($conn, $ds, $native);
                if (cnido_gsc_expr_file($ds, $native)) {
                    $anyViewable = true;
                    $pill = '<span class="gp-pill gp-yes">in the viewer</span>';
                    $link = '/gene_exp.php?dataset=' . urlencode($ds) . '&amp;gene=' . urlencode($native);
                    $linkLabel = 'open gene expression';
                } else {
                    $pill = '<span class="gp-pill gp-warn" title="Recorded in the marker table, but no per-cell expression vector was exported, so the viewer cannot plot this gene">marker only</span>';
                    if ($mk) {
                        $link = '/cell_marker.php?dataset=' . urlencode($ds) . '&amp;gene=' . urlencode($native);
                        $linkLabel = 'open marker table';
                    }
                }
                if ($mk) {
                    $bits = array();
                    foreach ($mk as $m) {
                        /* pct_in 在库里是 0-1 的小数（实测 0.104-1），页面要印百分数，
                           必须 ×100 —— cell_marker.php 也是这么换算的。 */
                        $bits[] = cnido_gp_h($m['cell_type'])
                                . ' <span style="color:#64748b">(' . cnido_gp_h($m['log2fc'])
                                . ', ' . number_format((float)$m['pct_in'] * 100, 1) . '%)</span>';
                    }
                    $mkTxt = implode('<br>', $bits);
                }
            }

            $stage = trim((string)$d['tissue_organ']);
            if (trim((string)$d['stage']) !== '') {
                $stage .= ($stage !== '' ? ' &middot; ' : '') . cnido_gp_h($d['stage']);
            }

            /* **每一行都必须有一个出口**（用户 2026-09-28 的要求：每个数据都要能点
               进去看这个基因在该数据集里的情况）。基因在这份数据里时点进去看表达/
               marker；不在这份数据里时，至少要能点进这个数据集自己看 —— 只印一句
               「not in this dataset」而没有链接，读者无法核实，也不知道下一步去哪。
               所以最后一档统一落到数据集查看器（不带 gene，那个号在这个数据集里
               本来就不存在，填进去只会得到一句「找不到」）。 */
            if ($link === '') {
                $link = '/gene_exp.php?dataset=' . urlencode($ds);
                $linkLabel = 'open the dataset';
            }
            if ($native !== '') { $anyInDataset = true; }

            /* 把 pill 本身做成链接，并在下面补一行动作词。原来是一条跨 6 列的独立
               行，行高由 Marker of 那列撑开，10 个数据集就多出 10 行大片留白。 */
            /* $link 是站内的 gene_exp.php，按口径留当前窗口。 */
            $cellGene = '<a class="gp-a" href="' . $link . '"'
                      . ' title="' . cnido_gp_h($linkLabel) . '">' . $pill . '</a>'
                      . '<div style="margin-top:4px;font-size:12px">'
                      . '<a class="gp-a" href="' . $link . '">'
                      . $linkLabel . ' &rarr;</a></div>';

            echo '<tr><td><b>' . cnido_gp_h($ds) . '</b></td>'
               . '<td>' . ($stage !== '' ? $stage : '<span style="color:#64748b">&ndash;</span>') . '</td>'
               . '<td class="num">' . number_format((int)$d['n_cells_final']) . '</td>'
               . '<td class="num">' . (int)$d['n_cell_types'] . '</td>'
               . '<td>' . $cellGene . '</td><td>' . $mkTxt . '</td></tr>';
        }
        echo '</table>';

        /* 跳转条上那个点是「一眼看全」用的：绿=这个基因至少在某个数据集里查得到。
           $anyInDataset 为假而 $anyViewable 也为假时还得区分「数据集自己就没有这个
           号」和「数据集没有号表、判不了」—— 后者不能报「没有」。 */
        cnido_gp_nav_add(
            'singlecell',
            'Single-cell expression',
            count($sets) . ' dataset' . (count($sets) === 1 ? '' : 's'),
            ($anyViewable || $anyInDataset) ? 'yes' : ($anyAbsent ? 'no' : '')
        );

        echo '<p class="gp-none">'
           . ($anyViewable
                ? 'Opening a dataset shows the UMAP with this gene coloured and the violin plot of its expression '
                . 'per cell type. '
                : '')
           . 'Every row links out: rows that hold this gene open its expression (or its marker table), the rows '
           . 'that do not open that dataset so you can look for it there yourself. '
           . 'A gene is <b>in the viewer</b> when the dataset ships a per-cell expression vector for it; '
           . '<b>marker only</b> means it is in the dataset&rsquo;s ranked marker table but no vector was exported, '
           . 'so the atlas cannot draw it; <b>not in this dataset</b> means the dataset&rsquo;s own gene-ID table '
           . 'does not list it, so no expression claim can be made for that dataset. '
           . '<b>unmapped</b> is deliberately weaker still: the dataset has no gene-ID table on CnidoSite yet, so '
           . 'nothing is claimed in either direction. '
           . 'Where markers are listed, the numbers are log2 fold change and the percentage of cells of that type '
           . 'in which the gene was detected.</p>';

        /* 到单细胞模块的出口。行里那些链接是「这个基因在某个数据集里」，这里给的是
           「这个物种的单细胞全景」，两者都要 —— 只有行内链接时，读者看完一篇表格
           不知道该往哪去。cell_atlas.php 收 abbr1 短码（见 includes/modlinks.php
           的 singlecell 子页表）。 */
        if ($abbr !== null && $abbr !== '') {
            echo '<div class="cn-openrow">'
               . cnido_gp_openbtn('/cell_atlas.php?species=' . urlencode($abbr),
                    'Open the Cell Atlas for ' . cnido_gp_h($latin))
               . cnido_gp_openbtn('/gene_exp.php?species=' . urlencode($abbr),
                    'Gene Expression browser', 'sec')
               . '</div>';
        }

        cnido_gp_card_close();
    }
}
