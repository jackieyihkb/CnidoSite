<?php
/* =====================================================================
 * 单细胞模块的基因号 -> 站点基因号 对照表
 *
 * 由来：单细胞数据集的基因号一律是**来源数据集自己的**写法 —— Nematostella
 * 的 NV2.8285 / NVE9490、Acropora 的 Amil_Amillepora19313、Hydra 的 g4335.t1、
 * Oculina 的 Ocupat_v02_G022773、Stylophora 的 Spis_XP_022803870_1。这些号在
 * 各自的论文里都对，但站内其它页面（gene_detail.php、注释表、表达量表）用的是
 * 各物种 <ABBR>_locus.mRNA 那一列 —— NCBI 的 XP_/LOC、GenBank 的 KAL/PFX，
 * 或者 Amil_V2.1 提交方自己的 ACROYT_G######。于是「从单细胞页复制一个号，
 * 粘到 gene_detail.php 查不到」——这才是真正的问题，不是单细胞的号写错了。
 *
 * 做法：给每个数据集生成一份 sidecar  singlecell_data/<DS>/gene_ids.json，
 * 把数据集自己的号映射到站点号。页面上站点号作主显示，原号放进括号/悬停；
 * 映射不上的（NVE 老模型、论文里的自由文本基因名、*_orphan_* 之类）保持原号，
 * 并明确标注「not mapped to RefSeq」。**不猜**——没有证据的一律留在未映射。
 *
 * 三条硬约束，改这段代码时别破坏：
 *
 * 1) expression/<gene>.bin.gz 的文件名用的是**原号**，viewer 也按原号查表。
 *    所以 sidecar 只影响显示与检索，绝不能拿去改数据键。sc_gene_display() 与
 *    sc_gene_scid() 的分工就是这个：前者管显示，后者把用户输入的任一种写法
 *    归约回原号，数据层永远只见原号。
 *
 * 2) 映射到的号必须能在 <ABBR>_locus.mRNA 里查到。生成时已经全量校验过
 *    （53,707 行，0 行落空），因为「复制过去能打开 gene_detail.php」正是这张
 *    表的全部意义。新增映射后要重跑校验。
 *
 * 3) 侧车文件缺失时整段静默降级：页面照旧显示原号，不报错、不显示半张表。
 *    数据集是分批上传的，sidecar 晚到一步不该让页面 500。
 * ===================================================================== */

/**
 * sc_gene_refseq 这张表在不在。（已发表那几张 marker 表没有 genes.json，
 * sidecar 没地方放，那条路只能走数据库；表可能因为还没重建而不存在。）
 *
 * 每次请求只问一次，并且失败也缓存 —— SHOW TABLES 不算便宜，而
 * cell_marker.php 会为每个 cell type 调一次。
 */
if (!function_exists('sc_gene_refseq_ok')) {
    function sc_gene_refseq_ok($conn)
    {
        static $ok = null;
        if ($ok === null) {
            $ok = false;
            if ($conn && function_exists('sc_has_table')) {
                $ok = sc_has_table($conn, 'sc_gene_refseq');
            }
        }
        return $ok;
    }
}

/**
 * 数据库里一次取回一批原号的站点号：array(sc_id => array(acc, locus, src))。
 *
 * 给「已发表数据集」那条路用（cell_marker.php 的 <ABBR>_cellmarker 分支），
 * 那边的 marker 是一次查一页、不是整份 genes.json，所以没有 sidecar。
 * 传进来的号只用于 IN (...) 查询，不拼进 SQL 文本。
 *
 * 返回值与 sc_gene_id_map() 的**形状一致**（sc_id => array('acc','locus','src')），
 * 这样调用方两条路都用 sc_gene_display()/sc_gene_scid()，不必各写一套。
 */
if (!function_exists('sc_gene_refseq_map')) {
    function sc_gene_refseq_map($conn, $abbr, $sc_ids)
    {
        $out = array();
        if (!$conn || !$abbr || !$sc_ids || !sc_gene_refseq_ok($conn)) {
            return $out;
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$abbr)) {
            return $out;
        }
        /* 去重后分批 IN —— 一页 marker 最多几百行，但同一页会有重复基因。 */
        $ids = array();
        foreach ($sc_ids as $s) {
            $s = (string)$s;
            if ($s !== '') {
                $ids[$s] = true;
            }
        }
        $ids = array_keys($ids);
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT sc_id, accession FROM sc_gene_refseq WHERE abbr = ? AND sc_id IN ($ph)";
            $st = @$conn->prepare($sql);
            if (!$st) {
                return $out;
            }
            $types = 's' . str_repeat('s', count($chunk));
            $args = array_merge(array((string)$abbr), $chunk);
            $st->bind_param($types, ...$args);
            $st->execute();
            $res = $st->get_result();
            if ($res) {
                while ($r = $res->fetch_row()) {
                    $out[(string)$r[0]] = array(
                        'acc'   => (string)$r[1],
                        'locus' => '',
                        'src'   => 'sc_gene_refseq',
                    );
                }
            }
            $st->close();
        }
        return $out;
    }
}

/** sidecar 的绝对路径。数据集名来自白名单（sc_atlas），这里再挡一次目录穿越。 */
if (!function_exists('sc_gene_ids_path')) {
    function sc_gene_ids_path($dataset)
    {
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', (string)$dataset)) {
            return '';
        }
        return __DIR__ . '/../singlecell_data/' . $dataset . '/gene_ids.json';
    }
}

/**
 * 读某个数据集的对照表，返回 array(sc_id => array(acc, locus, src))。
 *
 * 读不到就返回空数组 —— 调用方据「空」走原号显示，而不是报错。
 * 同一次请求里只读一次：cell_marker.php 会为几千行 marker 反复问。
 */
if (!function_exists('sc_gene_id_map')) {
    function sc_gene_id_map($dataset)
    {
        static $cache = array();
        $key = (string)$dataset;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $out = array();
        $p = sc_gene_ids_path($dataset);
        if ($p !== '' && is_file($p)) {
            $raw = json_decode(file_get_contents($p), true);
            if (is_array($raw) && isset($raw['map']) && is_array($raw['map'])) {
                foreach ($raw['map'] as $sc => $v) {
                    if (is_array($v) && isset($v[0]) && $v[0] !== '') {
                        $out[(string)$sc] = array(
                            'acc'   => (string)$v[0],
                            'locus' => isset($v[1]) ? (string)$v[1] : '',
                            'src'   => isset($v[2]) ? (string)$v[2] : '',
                        );
                    }
                }
            }
        }
        $cache[$key] = $out;
        return $out;
    }
}

/** 该数据集有多少个基因、其中多少个映射上了。给页头那句统计用。 */
if (!function_exists('sc_gene_id_stats')) {
    function sc_gene_id_stats($dataset, $total)
    {
        $map = sc_gene_id_map($dataset);
        $n = 0;
        foreach ($map as $sc => $v) {
            unset($v);
            $n++;
        }
        return array('mapped' => min($n, (int)$total), 'total' => (int)$total);
    }
}

/**
 * 把用户输入的任一种写法归约回数据集自己的号（数据层的键）。
 *
 * 接受：原号本身、站点号（RefSeq/GenBank accession）、以及带版本号与否的
 * 写法（XP_048580524 / XP_048580524.1）。归不到就原样返回 —— 让上层照旧
 * 走「没有这个基因」的提示，而不是悄悄换成别的基因。
 */
if (!function_exists('sc_gene_scid')) {
    function sc_gene_scid($input, $map)
    {
        $g = trim((string)$input);
        if ($g === '' || !$map) {
            return $g;
        }
        if (isset($map[$g])) {
            return $g;                       // 已经是原号
        }
        /* 比对时两边都去掉版本后缀：用户可能打 XP_001626598，而表里存的是
           XP_001626598.1。**两边都去**是关键 —— 只去输入那一侧的话，不带版本
           号的输入（去完等于自身）永远匹配不上。 */
        $probe = preg_replace('/\.\d+$/', '', $g);
        foreach ($map as $sc => $v) {
            if ($v['acc'] === $g) {
                return (string)$sc;
            }
            if (preg_replace('/\.\d+$/', '', $v['acc']) === $probe) {
                return (string)$sc;
            }
        }
        return $g;
    }
}

/**
 * 页面显示用的一对标签。
 *
 * 返回 array(sc, acc, mapped, known, primary, secondary)。
 *
 * **这里有三种状态，不是两种**，混起来就会把「我们没做」说成「没有对应」：
 *
 *   mapped=true           有这个基因的对照，主显示站点号
 *   mapped=false, known=true   这个数据集做过映射，但**这一个**没有可立的证据
 *                              —— 页面标 not mapped
 *   known=false           这个数据集**根本没有对照表**（AMURI/OARBU 这类只有已
 *                          发表表的，或 sidecar 还没生成的）—— 页面不作任何标注，
 *                          由页面另外一句话说明原因。给每一行都挂 not mapped 会
 *                          把整页说成 195 个号映射失败，而真相是我们一个都没试。
 */
if (!function_exists('sc_gene_display')) {
    function sc_gene_display($sc_id, $map, $map_known = null)
    {
        $sc_id = (string)$sc_id;
        if ($map_known === null) {
            $map_known = (count($map) > 0);
        }
        $mapped = isset($map[$sc_id]);
        return array(
            'sc'        => $sc_id,
            'acc'       => $mapped ? $map[$sc_id]['acc'] : '',
            'mapped'    => $mapped,
            'known'     => (bool)$map_known,
            'primary'   => $mapped ? $map[$sc_id]['acc'] : $sc_id,
            'secondary' => $mapped ? $sc_id : '',
        );
    }
}

/**
 * 一行基因的显示标签（不含链接，调用方自己包 <a>）。
 *
 *   mapped   -> XP_048577623.1 <span class="sc-src">(NV2.8285)</span>
 *   unmapped -> NVE9490 <abbr class="sc-unmapped">not mapped</abbr>
 *
 * 映射上时主显示是站点号、原号进括号并带 title 说明它是来源数据集的号；没映射
 * 上时保持原号并明确标注 —— 用户看的决策是「未映射的先放着，但页面上说清楚」，
 * 不猜、也不隐藏。
 */
if (!function_exists('sc_gene_label')) {
    function sc_gene_label($disp)
    {
        $h = sc_h($disp['primary']);
        if ($disp['mapped']) {
            return $h . ' <span class="sc-src" title="The identifier this dataset '
                 . 'uses internally (as in the source study). Data files and this '
                 . 'site\'s single-cell viewer are keyed by it; it does not resolve '
                 . 'elsewhere on the site.">(' . sc_h($disp['secondary']) . ')</span>';
        }
        /* 数据集压根没有对照表时，只显示原号 —— 不给角标。角标在这里是在断言
           「我们查过、查不到」，而这种情况我们没查过。 */
        if (empty($disp['known'])) {
            return $h;
        }
        return $h . ' ' . sc_gene_badge();
    }
}

/**
 * 未映射基因的角标。
 *
 * 用 <abbr title="…"> 而不是只给颜色：颜色对色盲读者无效，而且这三个字母
 * 要能解释自己。title 里把「为什么没映射」说清楚，免得读者以为是页面出错。
 */
if (!function_exists('sc_gene_badge')) {
    function sc_gene_badge()
    {
        return '<abbr class="sc-unmapped" title="This identifier is the source '
             . 'dataset\'s own gene ID. No RefSeq/GenBank accession could be '
             . 'assigned to it, so it keeps that ID and does not resolve outside '
             . 'this module.">not mapped</abbr>';
    }
}
