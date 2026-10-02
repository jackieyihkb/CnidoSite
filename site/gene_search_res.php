<?php
/**
 * gene_search_res.php -- 单物种的「基因号 / 基因名 / 功能关键词」统一搜索。
 *
 * search.php 的第一个搜索框（原「Gene ID Search」，只认基因号、把 gene= 直接交给
 * gene_detail.php）现在指向本页。用户在一个框里可以打三种东西：
 *
 *   1. 基因号      XP_032233839.1    -> 在 _locus 里精确命中就 302 直接跳
 *                                       gene_detail.php，和改版前的行为一模一样
 *   2. 基因名      wnt3              -> 命中 _uniprot 的 GN=WNT3A、_nr 的描述
 *   3. 功能关键词  Wnt signaling      -> 命中 _ipr / _go 的词条与描述
 *
 * 为什么「名字」和「功能」必须分头查：这两类信息在库里根本不在同一个地方。
 * `wnt3` 这种基因符号只出现在 _uniprot 的 `GN=` 字段（111/148 个物种有这张表）
 * 和 _nr 的描述里；`Wnt` 这种功能词在 _ipr / _go 里（148/148 全覆盖）。
 * og_family.search_text 那个全文索引只收 GO/Pfam/PANTHER 词、不含基因名 ——
 * 实测它查得到 wnt（85 个家族）却查不到 wnt3（0 个），所以不能拿它当统一入口。
 *
 * 性能（2026-09-26 整站被无界全表扫描打瘫之后，这里每条查询都必须有界）：
 *   - 一次只查一个物种。全库 148 个物种扫一遍就是又一次宕机。
 *   - 四个来源都是 `Description LIKE '%词%'`，前导通配符用不上索引，实测 NVECT
 *     （ipr 38 万行 + go 38 万行 + nr 9.5 万行 + uniprot 6.3 万行）合并查询 0.37 秒；
 *     这就是单物种的上限，最常见的词（"protein"）也在同一量级。
 *   - 每条分支都挂 MAX_EXECUTION_TIME，宁可这一页说超时，也不能拖垮机器。
 *
 * 拼 SQL 的三个坑：
 *   - `$q` 先转义成 SQL 字符串、**再**把 LIKE 的元字符 % 和 _ 转义掉；顺序反了会
 *     把反斜杠重复转义，用户打一个 % 就等于匹配全表。
 *   - 表名拼不出占位符，只能用白名单卡形状（^[A-Za-z0-9_]+$）。
 *   - 不要跨 `og_family*`（utf8mb4_unicode_ci）和注释表（utf8mb4_0900_ai_ci）做
 *     列对列 JOIN：直接报 1267 排序规则冲突；而加 COLLATE 之后索引会失效，
 *     实测从 0.07 秒变成扫 526 万行直到超时。所以同源家族是分开查、在 PHP 里拼的。
 */
require_once __DIR__ . '/includes/state.php';

function gsr_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ------------------------------------------------------------------ 输入 */

function gsr_in($key) {
    if (isset($_GET[$key]) && $_GET[$key] !== '')   { return $_GET[$key]; }
    if (isset($_POST[$key]) && $_POST[$key] !== '') { return $_POST[$key]; }
    return '';
}

$q         = trim(gsr_in('gene'));
if ($q === '') { $q = trim(gsr_in('q')); }        // 兼容 ?q=
$speciesIn = trim(gsr_in('species'));

$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, array(10, 20, 50, 100), true)) { $per_page = 20; }
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }

/* ------------------------------------------------------- 服务端排序状态 */

/* 三个箭头。'default' 是隐藏键（不在表头里）：页面的自然次序是「名称类命中优先」，
   对应不到任何一列，所以初始视图一行都不动、三个箭头都是未选中的 ↕。
   表达式里的列名一律写 u.（union 子查询的别名）—— 按 Family 排时下面会再 LEFT
   JOIN 一张也有 gene 列的表，裸写 gene / sym 会变成 ambiguous（1052）。
   'ev' 按 sym 排：这一列每条证据的开头就是来源名，sym 正好是这个来源的分类
   （UniProt / NCBI-NR ＝ 基因名描述类＝1 在前，InterPro / GO ＝ 功能词类＝0），
   也就是页面自己的显示次序所用的第一关键字。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array(
    'default' => 'MAX(u.sym) DESC, u.gene',
    'gene'    => 'u.gene',
    'family'  => 'MIN(f.og)',
    'ev'      => 'MAX(u.sym)',
);
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', array('u.gene'));

$__filterQs = 'gene=' . urlencode($q) . '&species=' . urlencode($speciesIn);
$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
$__baseQs   = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');

$MAXMS = 5000;                                     // 每条查询的上限（毫秒）

$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');

/* ------------------------------------------------- 物种：任意写法 -> abbr1 */

$abbr1 = '';
$latin = '';
if (!$conn->connect_error && $speciesIn !== '') {
    $spEsc = mysqli_real_escape_string($conn, $speciesIn);
    $rs = $conn->query("SELECT abbr1, species FROM abbr
                         WHERE species = '$spEsc' OR abbr = '$spEsc' OR abbr1 = '$spEsc'
                         LIMIT 1");
    if ($rs && ($r = $rs->fetch_row())) { $abbr1 = $r[0]; $latin = $r[1]; }
}

/* 表名要拼进 SQL，白名单正则卡住形状。abbr1 本来就来自 abbr 表，但「来自数据库」
   不等于「可信」—— 这里再确认一次（和 includes/domain_search.php 的
   ds_valid_abbr() 同一个意思）。 */
$abbrOk = ($abbr1 !== '' && preg_match('/^[A-Za-z0-9_]+$/', $abbr1));

/* ------------------------------------- 四个来源里，这个物种到底有哪几张表 */

$src = array('uniprot' => false, 'nr' => false, 'ipr' => false, 'go' => false);
if ($abbrOk) {
    $a1Esc = mysqli_real_escape_string($conn, $abbr1);
    $rs = $conn->query("SELECT table_name FROM information_schema.tables
                         WHERE table_schema = DATABASE()
                           AND table_name IN ('{$a1Esc}_uniprot','{$a1Esc}_nr',
                                              '{$a1Esc}_ipr','{$a1Esc}_go')");
    while ($rs && ($r = $rs->fetch_row())) {
        $sfx = substr($r[0], strlen($abbr1) + 1);
        if (isset($src[$sfx])) { $src[$sfx] = true; }
    }
}
$hasNameSrc = ($src['uniprot'] || $src['nr']);     // 能不能按基因名搜
$hasFuncSrc = ($src['ipr'] || $src['go']);         // 能不能按功能搜

/* --------------------------- 用户输入的两个转义（顺序不能反，见文件头注释） */

$qEsc  = mysqli_real_escape_string($conn, $q);
$qLike = str_replace(array('%', '_'), array('\\%', '\\_'), $qEsc);

/* --------------------------------------- 1. 精确命中基因号：跳详情页，不搜索 */

if ($abbrOk && $q !== '' && !$conn->connect_error) {
    /* 基因号在库里有两种写法（注释表存 `…15160.1`、_locus 存 `…15160`），
       所以两种都要试 —— cnido_gene_in() 就是干这个的。 */
    $rs = $conn->query("SELECT /*+ MAX_EXECUTION_TIME($MAXMS) */ mRNA
                          FROM `{$abbr1}_locus`
                         WHERE mRNA IN (" . cnido_gene_in($conn, $q) . ")
                            OR gene IN (" . cnido_gene_in($conn, $q) . ")
                         LIMIT 1");
    if ($rs && $rs->fetch_row()) {
        header('Location: gene_detail.php?gene=' . urlencode($q) . '&species=' . urlencode($latin));
        exit;
    }
}

/* ----------------------------------------------------------- 2. 合并搜索 */

/* 每条分支都是「这个物种某一类注释里，描述命中该词的行」。sym=1 表示命中的是
   基因名/描述（比功能词更具体，排序时优先）。 */
$BR = array();
if ($abbrOk && $q !== '') {
    if ($src['uniprot']) {
        $BR[] = "SELECT /*+ MAX_EXECUTION_TIME($MAXMS) */ gene, 1 AS sym,
                        CONCAT('UniProt: ', description) AS ev
                   FROM `{$abbr1}_uniprot` WHERE description LIKE '%$qLike%'";
    }
    if ($src['nr']) {
        $BR[] = "SELECT /*+ MAX_EXECUTION_TIME($MAXMS) */ gene, 1 AS sym,
                        CONCAT('NCBI-NR: ', description) AS ev
                   FROM `{$abbr1}_nr` WHERE description LIKE '%$qLike%'";
    }
    if ($src['ipr']) {
        $BR[] = "SELECT /*+ MAX_EXECUTION_TIME($MAXMS) */ gene, 0 AS sym,
                        CONCAT('InterPro ', InterPro_term, ': ', Description) AS ev
                   FROM `{$abbr1}_ipr` WHERE Description LIKE '%$qLike%'";
    }
    if ($src['go']) {
        $BR[] = "SELECT /*+ MAX_EXECUTION_TIME($MAXMS) */ gene, 0 AS sym,
                        CONCAT('GO ', GO_term, ': ', Description) AS ev
                   FROM `{$abbr1}_go` WHERE Description LIKE '%$qLike%'";
    }
}

$total    = 0;
$rows     = array();       // gene => array('sym'=>int, 'ev'=>array())
$timedOut = false;

if ($BR) {
    $union = implode("\nUNION ALL\n", $BR);

    /* 总数和当前页用一条查询出：COUNT(*) OVER () 在 GROUP BY 之后、LIMIT 之前
       求值，算的就是去重后的基因总数。拆成「先 COUNT(DISTINCT) 再分页」两条会把
       同样这四张表扫两遍 —— 实测 0.72 秒 vs 一条 0.37 秒。
       证据文字不在这里用 GROUP_CONCAT 取：group_concat_max_len 默认只有 1024
       字节，一个基因命中很多条时会被**静默截断**，所以证据是拿到本页这几个基因
       之后另查一次（见下）。 */
    /* 按 Family 排才加这条 LEFT JOIN：加了实测 272 ms → 350 ms，不该让不排它的人
       付这笔钱。COLLATE 必须写死 —— og_family_member 是 utf8mb4_unicode_ci、
       注释表是 utf8mb4_0900_ai_ci，列对列 JOIN 不加直接报 1267。
       LIMIT 之前的窗口函数算的是 GROUP BY 之后的组数，JOIN 进来的行被 GROUP BY
       吸收，所以 total_genes 仍然是基因数，不受影响。 */
    $__famJoin = ($__sort === 'family')
        ? " LEFT JOIN og_family_member f
                       ON f.gene = u.gene COLLATE utf8mb4_unicode_ci
                      AND f.abbr = '" . mysqli_real_escape_string($conn, $abbr1) . "'\n"
        : '';
    $__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');
    $sqlPage = function ($offset) use ($union, $per_page, $__famJoin, $__orderSql) {
        return "SELECT u.gene, MAX(u.sym) AS sym, COUNT(*) OVER () AS total_genes
                  FROM (\n$union\n) u$__famJoin
                 GROUP BY u.gene$__orderSql
                 LIMIT $offset, $per_page";
    };

    $rs = $conn->query($sqlPage(($page - 1) * $per_page));
    if (!$rs && (int)$conn->errno === 3024) { $timedOut = true; }
    while ($rs && ($r = $rs->fetch_row())) {
        $total = (int)$r[2];
        $rows[$r[0]] = array('sym' => (int)$r[1], 'ev' => array());
    }

    /* 页码越界（用户手改了 URL）：窗口函数只在有返回行时才给得出总数，这时退回
       第 1 页重查一次，把真实总数和首页拿回来。只在越界时多花这一条。 */
    if (!$timedOut && !$rows && $page > 1) {
        $page = 1;
        $rs = $conn->query($sqlPage(0));
        while ($rs && ($r = $rs->fetch_row())) {
            $total = (int)$r[2];
            $rows[$r[0]] = array('sym' => (int)$r[1], 'ev' => array());
        }
    }

    if ($rows) {
        /* 只给本页这 ≤100 个基因取证据，走 ix_gene，代价可以忽略。 */
        $inList = array();
        foreach (array_keys($rows) as $g) {
            $inList[] = "'" . mysqli_real_escape_string($conn, $g) . "'";
        }
        $in = implode(',', $inList);

        $EVID = array();
        if ($src['uniprot']) { $EVID[] = array(1, 'uniprot', 'description', 'UniProt'); }
        if ($src['nr'])      { $EVID[] = array(1, 'nr',      'description', 'NCBI-NR'); }
        if ($src['ipr'])     { $EVID[] = array(0, 'ipr',     'Description', 'InterPro'); }
        if ($src['go'])      { $EVID[] = array(0, 'go',      'Description', 'GO'); }
        foreach ($EVID as $e) {
            list($sym, $tb, $col, $label) = $e;
            $lbEsc = mysqli_real_escape_string($conn, $label);
            $rs2 = $conn->query("SELECT /*+ MAX_EXECUTION_TIME($MAXMS) */ gene,
                                        CONCAT('$lbEsc: ', $col) AS ev
                                   FROM `{$abbr1}_{$tb}`
                                  WHERE gene IN ($in) AND $col LIKE '%$qLike%'
                                  LIMIT 400");
            while ($rs2 && ($r2 = $rs2->fetch_row())) {
                if (isset($rows[$r2[0]])) {
                    $rows[$r2[0]]['ev'][] = array($sym, $r2[1]);
                }
            }
        }
    }
}

/* --------------------------------------------- 3. 同源家族（跨物种上下文） */

/* 分两步查、在 PHP 里拼，不做跨排序规则的 JOIN（见文件头注释）。
   第一步走 og_family_member 的 idx_abbr_gene，只取本页这几个基因。
   ORDER BY gene, og 是**必须的**：(abbr, gene) 在 og_family_member 里不唯一
   （库里 4758 个基因有不止一个 og，最多 19 个），下面「先到先得」取到的那个原本
   取决于引擎给行的次序 —— 既让显示值不稳定，也会和按 Family 排时的 MIN(f.og)
   对不上（排出来看着没排）。加了排序之后两边都取最小的那个 og。 */
$famOf = array();      // gene => og
$fam   = array();      // og   => row
if ($rows) {
    $a1Esc = mysqli_real_escape_string($conn, $abbr1);
    $rs = $conn->query("SELECT /*+ MAX_EXECUTION_TIME($MAXMS) */ gene, og
                          FROM og_family_member
                         WHERE abbr = '$a1Esc' AND gene IN ($in)
                         ORDER BY gene, og");
    $ogs = array();
    while ($rs && ($r = $rs->fetch_row())) {
        if (!isset($famOf[$r[0]])) { $famOf[$r[0]] = $r[1]; }
        $ogs[$r[1]] = true;
    }
    if ($ogs) {
        $ogIn = array();
        foreach (array_keys($ogs) as $o) {
            $ogIn[] = "'" . mysqli_real_escape_string($conn, $o) . "'";
        }
        $rs = $conn->query("SELECT /*+ MAX_EXECUTION_TIME($MAXMS) */ og, n_genes, n_species,
                                   best_source, best_term, best_desc
                              FROM og_family WHERE og IN (" . implode(',', $ogIn) . ")");
        while ($rs && ($r = $rs->fetch_row())) {
            $fam[$r[0]] = array('n_genes' => $r[1], 'n_species' => $r[2],
                                'source' => $r[3], 'term' => $r[4], 'desc' => $r[5]);
        }
    }
}

/* 翻页链接：把当前查询条件原样带上，否则翻页会丢掉关键词和物种。
   POST 进来的查询在这里转成 GET，所以翻页/改每页条数都不会丢条件。
   用 $__baseQs（查询条件 + 当前排序）拼，翻到第几页排序都还在。 */
$pageUrl = function ($p) use ($__baseQs, $per_page) {
    return 'gene_search_res.php?' . $__baseQs
         . '&per_page=' . $per_page . '&page=' . $p;
};

$totalPages = ($total > 0) ? (int)ceil($total / $per_page) : 0;

/* 这个物种实际搜了哪几张表 —— 空结果时要说清楚，别让用户以为是全站查不到。 */
$srcNames = array();
if ($src['uniprot']) { $srcNames[] = 'UniProt description'; }
if ($src['nr'])      { $srcNames[] = 'NCBI-NR description'; }
if ($src['ipr'])     { $srcNames[] = 'InterPro term'; }
if ($src['go'])      { $srcNames[] = 'GO term'; }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Gene Search Results - CnidoSite</title>
<meta name="keywords" content="Cnidaria, gene search, gene name, functional annotation" />
<meta name="description" content="Search genes by identifier, gene name or functional keyword within one species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<style>
<?php /* 命中证据：每个基因下面列它是在哪条注释里命中的 */ ?>
.gsr-ev { display: block; font-size: 12px; color: #475569; margin-top: 2px; }
.gsr-chip {
    display: inline-block; font-size: 12px; padding: 1px 7px; border-radius: 9px;
    background:#f0fdf4; color: #065f46; border: 1px solid #a7f3d0; margin-top: 2px;
    white-space: nowrap;
}
.gsr-fam { font-size: 12px; }
.gsr-fam .gsr-nsp { color: #64748b; font-size: 12px; display: block; }
.gsr-hint { font-size: 15px; color: #64748b; margin-top: 6px; }

<?php /* 分页样式 - 与 go_result.php / busco_result.php 保持一致（整块照抄，别改数值） */ ?>
.pagination-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 20px;
    margin-top: 30px;
    border: 1px solid #e2e8f0;
}

.pagination-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.total-records {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.per-page-selector {
    display: flex;
    align-items: center;
    gap: 10px;
}

.per-page-selector select {
    padding: 8px 15px;
    border-radius: 6px;
    border: 2px solid #e2e8f0;
    background: white;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.per-page-selector select:hover {
    border-color:#047857;
}

.pagination-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin: 25px 0;
    flex-wrap: wrap;
}

.page-btn {
    padding: 10px 16px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #475569;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s ease;
    min-width: 44px;
    text-align: center;
}

.page-btn:hover {
    background: #f0f9ff;
    border-color:#1d4ed8;
    color:#1d4ed8;
}

.page-btn.active {
    background:#1d4ed8;
    border-color:#1d4ed8;
    color: white;
    font-weight: 600;
}

.page-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.go-to-page {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
    justify-content: center;
}

.go-to-page input {
    width: 70px;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    text-align: center;
    font-size: 15px;
}

.go-to-page button {
    padding: 8px 16px;
    background:#1d4ed8;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.go-to-page button:hover {
    background: #1d4ed8;
}

<?php /* 表格美化 - 同 go_result.php */ ?>

.table-scroll-container {
    max-height: 700px;
    overflow-y: auto;
    overflow-x: auto;
}

<?php /* 链接样式 */ ?>
.gene-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.gene-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

<?php /* annotation 列保持左对齐：证据是一句句注释原文，居中会很难读。
   其余列由 <tr align="center"> 居中（和站内其它结果表一致）。 */ ?>
td.gsr-anno { text-align: left; }
td.gsr-anno .gsr-ev { padding-left: 2px; }
</style>
</head>

<body>
<?php /* 导航与 search.php 逐字一致（Genome 高亮）：本页是从那一页点进来的，
     用 Webpage_components.php 的 $header 会让 Home 高亮，和来路对不上。 */ ?>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<div id="templatemo_menu_wrapper">
    <div id="templatemo_menu">
        <ul>
            <li><a href="/index.php">Home</a></li>
            <li><a href="#">Taxonomy</a>
                <ul>
                    <li><a href="/browse.php?class=all">All</a></li>
                    <li><a href="/browse.php?class=Cubozoa">Cubozoa</a></li>
                    <li><a href="/browse.php?class=Hexacorallia">Hexacorallia</a></li>
                    <li><a href="/browse.php?class=Octocorallia">Octocorallia</a></li>
                    <li><a href="/browse.php?class=Hydrozoa">Hydrozoa</a></li>
                    <li><a href="/browse.php?class=Myxozoa">Myxozoa</a></li>
                    <li><a href="/browse.php?class=Scyphozoa">Scyphozoa</a></li>
                    <li><a href="/browse.php?class=Staurozoa">Staurozoa</a></li>
                </ul>
            </li>
            <li><a href="/paleobiology.php">Paleobiology</a></li>
            <li><a href="#" class="current">Genome</a>
                <ul>
                    <li><a href="/genomeinfo.php">Genomic Data</a></li>
                    <li><a href="/search.php">Gene Search</a></li>
                    <li><a href="/busco.php">BUSCO Genes</a></li>
                    <li><a href="/TE.php">Transposable Elements</a></li>
                    <li><a href="/gene_family.php">TFs/Ubs</a></li>
                    <li><a href="/proteindomain.php">Protein Domain</a></li>
                    <li><a href="/domain_search.php">Functional Domain Search</a></li>
                    <li><a href="/go.php">Gene Ontology</a></li>
                    <li><a href="/interpro.php">InterPro</a></li>
                    <li><a href="/kegg.php">KEGG Pathway</a></li>
                    <li><a href="/genefamily.php">Gene Family</a></li>
                    <li><a href="/pan-geneset.php">Pan-geneset</a></li>
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li><li><a href="/microsynteny.php">Microsynteny Analysis</a></li><li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li><li><a href="/mitdata.php">Mitogenomic Data</a></li>
                </ul>
            <li><a href="#">Transcriptome</a>
                <ul>
                    <li><a href="/cytoscape/trans_data.php">Transcriptomic Data</a></li>
                    <li><a href="/trans_assembly.php">Transcriptome Assembly</a></li>
                    <li><a href="/cytoscape/network.php">Network Analysis</a></li>
                    <li><a href="/cytoscape/network_expression.php">Dynamic Expression View</a></li>
                </ul>
            <li><a href="#">Single-cell</a>
                <ul>
                    <li><a href="/sn_data.php">Single-cell Data</a></li>
                    <li><a href="/cell_atlas.php">Cell Atlas</a></li>
                    <li><a href="/cell_marker.php">Cell Marker</a></li>
                    <li><a href="/gene_exp.php">Gene Expression</a></li>
                </ul>
            </li>
            <li><a href="#">Proteome</a>
                <ul>
                    <li><a href="/proteomic_reprocessed.php">Proteomic Data</a></li>
                    <li><a href="/proteomic_reanalysis.php">Proteomic Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Epigenome</a>
                <ul>
                    <li><a href="/epigenomic_data.php">Epigenomic Data</a></li>
                    <li><a href="/DNA_methylation.php">DNA Methylation</a></li>
                    <li><a href="/miRNA_analysis.php">miRNA-seq Analysis</a></li>
                    <li><a href="/ATAC_analysis.php">ATAC-seq Analysis</a></li>
                    <li><a href="/DHS_analysis.php">DNase-seq Analysis</a></li>
                    <li><a href="/ChIP_analysis.php">ChIP-seq Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Metagenome</a>
                <ul>
                    <li><a href="/metagenomic_data.php">Metagenomic Data</a></li>
                    <li><a href="/MAGs.php">MAGs Catalog</a></li>
                </ul>
            </li>
            <li><a href="#">Phenotype</a><ul><li><a href="/phenotype.php?class=all">All</a></li><li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li><li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li><li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li><li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li><li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li></ul></li><li><a href="#">Tools</a>
                <ul>
                    <li><a href="/GSEA/GSEA.php">Gene Sets Analysis</a></li>
                    <li><a href="/blast/blast.php">BLAST</a></li>
                    <li><a href="/primer3plus/primer3.html">Primer Design</a></li>
                    <li><a href="/jbrowse.php">JBrowse</a></li>
                </ul>
            </li>
            <li><a href="/download.php">Download</a></li>
            <li><a href="#">Help</a>
                <ul>
                    <li><a href="/data_statistics.php">Statistics</a></li>
                    <li><a href="/tutorial.php">User Manual</a></li>
                    <li><a href="/submit_comments.php">Data Submit</a></li>
                    <li><a href="/contact.php" class="last">Contact Us</a></li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<div id="tempatemo_content_wrapper">
<div id="templatemo_content">
<div id="column">

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Gene search<?php
  if ($q !== '') { echo ' for <i>' . gsr_h($q) . '</i>'; }
  if ($latin !== '') { echo ' in <i>' . gsr_h($latin) . '</i>'; }
?></b></legend>

<?php if ($conn->connect_error): ?>

  <div class="gd-notice gd-warn">Database connection failed.</div>

<?php elseif ($q === ''): ?>

  <div class="gd-notice gd-info">
    <b>Nothing to search for.</b><br>
    Go back to <a href="search.php">Gene Search</a> and type a gene ID
    (e.g. <code>XP_032233839.1</code>), a gene name (e.g. <code>wnt3</code>),
    or a functional keyword (e.g. <code>Wnt signaling</code>).
  </div>

<?php elseif (!$abbrOk): ?>

  <div class="gd-notice gd-warn">
    <b>Species not recognised.</b><br>
    <?php if ($speciesIn !== ''): ?>
      There is no species called <code><?php echo gsr_h($speciesIn); ?></code> in this database.
    <?php else: ?>
      Choose a species in the search form &mdash; this page searches one species at a time.
    <?php endif; ?>
  </div>

<?php elseif (!$hasNameSrc && !$hasFuncSrc): ?>

  <div class="gd-notice gd-warn">
    <b>No annotation to search for <i><?php echo gsr_h($latin); ?></i>.</b><br>
    This species has neither functional (InterPro/GO) nor description (NR/UniProt)
    annotation in the database.
  </div>

<?php else: ?>

  <p class="paleo-intro">
    Searching the <?php echo count($srcNames); ?> annotation source<?php echo count($srcNames) === 1 ? '' : 's'; ?>
    held for <i><?php echo gsr_h($latin); ?></i> (<?php echo gsr_h(implode(', ', $srcNames)); ?>).
    A gene ID opens its detail page directly; a gene name or functional keyword lists every
    gene in this species whose annotation contains it. The test is a plain substring match on
    the annotation text, so a short term also matches longer words and the name of the source
    organism &mdash; searching <code>actin</code>, for example, returns every gene whose
    description names <i>Actinia tenebrosa</i>. The <b>Matched in</b> column of each row shows
    the text that matched.
  </p>

  <?php if (!$hasNameSrc): ?>
    <div class="gd-notice gd-warn">
      <b>Gene-name search is not available for <i><?php echo gsr_h($latin); ?></i>.</b><br>
      This species has no UniProt or NCBI-NR annotation, so a gene symbol such as
      <code>wnt3</code> cannot be looked up here &mdash; only functional keywords
      (InterPro / GO) can be searched.
    </div>
  <?php endif; ?>

  <?php if ($timedOut): ?>

    <div class="gd-notice gd-warn">
      <b>The search timed out.</b><br>
      This term matches too many rows in <i><?php echo gsr_h($latin); ?></i> to finish
      within <?php echo (int)($MAXMS / 1000); ?> seconds. Try a more specific term.
    </div>

  <?php elseif ($total === 0): ?>

    <div class="gd-notice gd-info">
      <b>No match for &ldquo;<?php echo gsr_h($q); ?>&rdquo; in <i><?php echo gsr_h($latin); ?></i>.</b>
      <div class="gsr-hint">
        <?php if ($hasNameSrc): ?>
          Gene symbols are only findable where the deposited annotation used them &mdash;
          try a shorter form (<code>wnt</code> rather than <code>wnt3a</code>), or a
          functional keyword such as <code>Wnt signaling</code>.
        <?php else: ?>
          Try a different term, or pick a species that has description annotation.
        <?php endif; ?>
      </div>
    </div>

  <?php else: ?>

    <?php
    /* 「每页 N 条」下拉框的基址：只带查询条件 + 当前排序，per_page 和 page 由 JS
       拼在后面。$__baseQs 是 urlencode 出来的，所以基因名里的引号/& 不会破坏
       JS 字符串。 */
    $perPageBase = 'gene_search_res.php?' . $__baseQs . '&';
    /* 分页按钮的窗口：当前页 ±3，两侧各留省略号（和 go_result.php 同一套）。 */
    $startPage = max(1, $page - 3);
    $endPage   = min($totalPages, $page + 3);
    ?>

    <?php /* 分页 + 表格，整体结构与 go_result.php / busco_result.php 一致 */ ?>
    <div class="pagination-container">

      <div class="pagination-info">
        <div class="total-records">
          📊 Total genes matched in <?php echo gsr_h($latin); ?>: <?php echo number_format($total); ?>
        </div>

        <div class="per-page-selector">
          <span>Show:</span>
          <select onchange="window.location.href='<?php echo gsr_h($perPageBase); ?>per_page='+this.value+'&page=1'">
            <?php foreach (array(10, 20, 50, 100) as $option): ?>
              <option value="<?php echo $option; ?>" <?php echo $option === $per_page ? 'selected' : ''; ?>><?php echo $option; ?></option>
            <?php endforeach; ?>
          </select>
          <span>genes per page</span>
        </div>
      </div>

      <div class="table-container">
        <div class="table-scroll-container">
          <table class="gridtable">
<?php
/* 整张表必须紧凑输出：gridtable 带 `white-space: pre-wrap`，源码里的换行和缩进
   会被当成真的换行渲染 —— 每个 <span> 之间留一个换行，行高就会翻两三倍
   （实测一行 343px，去掉空白后是内容本身的高度）。所以下面全部用字符串拼、
   一个多余空格都不留。
   居中靠 <tr align="center">（站内结果表的写法，th 本来就默认居中）；
   最后一列加 class="gsr-anno"，用 CSS 单独压回左对齐 —— 证据是一句句注释原文，
   居中会很难读。 */
echo '<thead><tr align="center">'
   . '<th width="17%">' . cnido_sort_link('gene', 'Gene', $__sort, $__dir, $__filterQs) . '</th>'
   . '<th width="10%">' . cnido_sort_link('family', 'Family', $__sort, $__dir, $__filterQs) . '</th>'
   . '<th width="73%">'
     . cnido_sort_link('ev', 'Matched in <i>' . gsr_h($latin) . '</i>', $__sort, $__dir, $__filterQs)
   . '</th></tr></thead><tbody>';

foreach ($rows as $g => $info) {
    /* 证据排序：先「基因名/描述」类命中，再功能词类；同类按文字稳定排序，
       最多显示 3 条，其余折成计数。 */
    $ev = $info['ev'];
    usort($ev, function ($a, $b) {
        if ($a[0] !== $b[0]) { return $b[0] - $a[0]; }
        return strcmp($a[1], $b[1]);
    });
    $shown = array_slice($ev, 0, 3);
    $og    = isset($famOf[$g]) ? $famOf[$g] : '';

    /* 整条原样输出：来源已经写在字符串开头（"UniProt: …"、"GO GO:0016055: …"），
       按冒号切会把 GO 的编号切出来。 */
    $evHtml = '';
    foreach ($shown as $e) {
        $evHtml .= '<span class="gsr-ev">' . gsr_h($e[1]) . '</span>';
    }
    if (count($ev) > count($shown)) {
        $evHtml .= '<span class="gsr-chip">+' . (count($ev) - count($shown)) . ' more</span>';
    }
    if ($evHtml === '') { $evHtml = '<span class="gd-na">&mdash;</span>'; }

    if ($og !== '' && isset($fam[$og])) {
        $famHtml = '<a href="genefamily_result.php?family=' . urlencode($og)
                 . '&abbr=' . urlencode($abbr1) . '" title="Orthogroup '
                 . gsr_h($og) . '">' . gsr_h($og) . '</a>'
                 . '<span class="gsr-nsp">' . number_format((int)$fam[$og]['n_species'])
                 . ' species</span>';
    } elseif ($og !== '') {
        $famHtml = gsr_h($og);
    } else {
        $famHtml = '<span class="gd-na">&mdash;</span>';
    }

    echo '<tr align="center"><td><a class="gene-link" href="gene_detail.php?gene='
       . urlencode($g) . '&species=' . urlencode($latin) . '">' . gsr_h($g) . '</a></td>'
       . '<td class="gsr-fam">' . $famHtml . '</td>'
       . '<td class="gsr-anno">' . $evHtml . '</td></tr>';
}
echo '</tbody>';
?>
          </table>
        </div>
      </div>

      <?php /* 分页导航按钮 */ ?>
      <div class="pagination-nav">
        <a href="<?php echo gsr_h($pageUrl(1)); ?>"
           class="page-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
          &laquo; First
        </a>

        <a href="<?php echo gsr_h($pageUrl(max(1, $page - 1))); ?>"
           class="page-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
          &lsaquo; Previous
        </a>

        <?php if ($startPage > 1): ?>
          <a href="<?php echo gsr_h($pageUrl(1)); ?>" class="page-btn">1</a>
          <?php if ($startPage > 2): ?>
            <span class="page-btn disabled">...</span>
          <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
          <a href="<?php echo gsr_h($pageUrl($i)); ?>"
             class="page-btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>

        <?php if ($endPage < $totalPages): ?>
          <?php if ($endPage < $totalPages - 1): ?>
            <span class="page-btn disabled">...</span>
          <?php endif; ?>
          <a href="<?php echo gsr_h($pageUrl($totalPages)); ?>" class="page-btn"><?php echo $totalPages; ?></a>
        <?php endif; ?>

        <a href="<?php echo gsr_h($pageUrl(min($totalPages, $page + 1))); ?>"
           class="page-btn <?php echo $page == $totalPages ? 'disabled' : ''; ?>">
          Next &rsaquo;
        </a>

        <a href="<?php echo gsr_h($pageUrl($totalPages)); ?>"
           class="page-btn <?php echo $page == $totalPages ? 'disabled' : ''; ?>">
          Last &raquo;
        </a>
      </div>

      <?php /* 跳转到指定页 */ ?>
      <div class="go-to-page">
        <span>Go to page:</span>
        <input type="number" id="gotoPage" min="1" max="<?php echo $totalPages; ?>" value="<?php echo $page; ?>">
        <button onclick="window.location.href='<?php echo gsr_h($perPageBase); ?>per_page=<?php echo $per_page; ?>&page='+document.getElementById('gotoPage').value">
          Go
        </button>
        <span>of <?php echo $totalPages; ?> pages</span>
      </div>

    </div>

  <?php endif; ?>
<?php endif; ?>

</div>
</div>
</div>

<?php
if (!$conn->connect_error) { $conn->close(); }
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
