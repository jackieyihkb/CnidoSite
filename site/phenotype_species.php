<?php
/* =====================================================================
 * phenotype_species.php —— 单个物种的全部表型记录
 *
 * 为什么要这一页（用户要求三）：phenotype.php 是一张十几万行的平表，按 Class
 * 排序、每页 10 条，想看「Corallium rubrum 到底测了些什么」只能靠翻页或者用
 * 浏览器的页内查找。这一页把同一批记录按**物种**取出来，按 trait 类别分组展示，
 * 给出这个小物种自己的统计，并把每条记录的两件事写在行里 ——
 * **它是怎么得到的**（实测 / 专家判断 / 推导 / 从高阶元继承）和**它出自哪一家**
 * （OCTD / WoRMS / Pelagic，可回链署名）。文献清单印在页脚，能一条条核。
 *
 * 与已有页面/构件的关系（不要重复实现）：
 *   phenotype.php           列表页；Species 列现在指向本页。
 *   includes/phenotype_traits.php
 *                           全部判定（类别词表归一、占位值、来源属性、来源署名、
 *                           trait 字典、文献）都在那里，本页只负责排版 ——
 *                           两个页面各写一份必然分叉。
 *   includes/speciesinfo.php 「Data available for this species」卡片网格里已有
 *                           Phenotype 一张卡，深链就指到本页（见 includes/modlinks.php）。
 *   includes/coverage.php    $cov['cov'][$abbr1]['phenotype'] 是**折叠后的行数**
 *                           （COUNT(*)），本页还有折叠前的源记录数（SUM(n_records)）
 *                           —— 卡片报 688，本页报「688 records / 944 source entries」，
 *                           两个数都写出来，别让它们互相冒充。
 *
 * 2026-09-27 之前本页刻意不画图（那时 98.3% 的 value 是源库编号，画出来是噪声）。
 * 数据已重建（见 includes/phenotype_traits.php 文件头），图表可以建了 —— 但那是
 * 单独的活（覆盖矩阵、实测/继承条形图、数值分布、经纬度散点），不在本页里做；
 * 本页的 $summary 与 $grouped 就是现成的数据源。
 * ===================================================================== */

require_once __DIR__ . '/includes/state.php';
require_once __DIR__ . '/includes/phenotype_traits.php';
require_once __DIR__ . '/includes/coverage.php';        /* cnido_coverage()：取 abbr1 / 类别，给回链用 */

/* 物种 token：?species= 收拉丁名（Corallium rubrum）、下划线形式
   （Corallium_rubrum）或 abbr1 短码（CCRUB），三种都能进得来 —— 站内
   speciesinfo / coverage_matrix / 覆盖卡片给的是 abbr1，手工构造 URL 的人
   通常给拉丁名。注意**不能**像 speciesinfo.php 那样先做 [^a-zA-Z0-9_\-\.]
   过滤：那个过滤会把拉丁名里的空格剥掉，「Nematostella vectensis」会变成
   「Nematostellavectensis」永远认不出来。这里只去首尾空白，交给
   cnido_pheno_species_of() 去归一，输出时再统一转义。 */
$token_raw = '';
if (isset($_GET['species']))      { $token_raw = trim((string)$_GET['species']); }
elseif (isset($_POST['species'])) { $token_raw = trim((string)$_POST['species']); }
/* 长度上限：物种名不会超过 120 字符，超了直接当没给，免得把超长串带进查询。 */
if (mb_strlen($token_raw, 'UTF-8') > 120) { $token_raw = ''; }

require_once __DIR__ . '/includes/stats.php';
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { error_log('CnidoSite: database connection failed'); die('The database is temporarily unavailable. Please try again in a moment.'); }
$conn->set_charset('utf8mb4');

/* 归位到 phenotype.species 里真实存在的写法。归不出来 = 这个物种在表型表里
   没有记录（不等于「物种不存在」，所以下面还要再问一次分类表）。 */
$latin = ($token_raw === '') ? '' : cnido_pheno_species_of($conn, $token_raw);

/* 回链要用的 abbr1。$cov 与 speciesinfo.php / coverage_matrix.php 共用同一份
   1 小时缓存（tmp/coverage_cache.json），所以三处的物种集合永远一致。 */
$cov = cnido_coverage($conn);

/* ---------------------------------------------------------------------
 * 拉丁名 -> abbr1 / 类别。学名写法在两张表里不一定完全一样，沿用
 * includes/coverage.php 的匹配次序（小写去空格的精确匹配，再退化为前缀匹配），
 * 这样本页认得的物种集合与卡片网格认得的完全一致。
 * ------------------------------------------------------------------- */
function cnido_ps_species_index($cov)
{
    $idx = array('exact' => array(), 'abbr' => array());
    if (!isset($cov['species'])) { return $idx; }
    foreach ($cov['species'] as $ab => $info) {
        $sp = isset($info['species']) ? strtolower(trim($info['species'])) : '';
        if ($sp !== '') { $idx['exact'][$sp] = array('abbr' => $ab, 'info' => $info); }
        $ab2 = isset($info['abbr']) ? strtolower(trim(str_replace('_', ' ', $info['abbr']))) : '';
        if ($ab2 !== '' && !isset($idx['exact'][$ab2])) {
            $idx['abbr'][$ab2] = array('abbr' => $ab, 'info' => $info);
        }
        /* abbr1 自己也是常用的入口，而且它有两种形态：多数物种是下划线学名
           （Corallium_rubrum），少数是短码（Alatina alata 是 AALAT）。
           短码不索引的话，$idx 里就只能靠「下划线学名」找到它，而
           Alatina_alata 与 AALAT 之间没有任何前缀关系 —— 于是「物种在库里，
           却没有表型记录」会被误报成「这个物种不在本站分类表里」。 */
        $code = strtolower(trim((string)$ab));
        if ($code !== '' && !isset($idx['exact'][$code]) && !isset($idx['abbr'][$code])) {
            $idx['abbr'][$code] = array('abbr' => $ab, 'info' => $info);
        }
    }
    return $idx;
}

/* 解析：先精确，再前缀（与 coverage.php 同一套退化规则）。 */
function cnido_ps_lookup($idx, $latin)
{
    $k = strtolower(trim((string)$latin));
    if ($k === '') { return null; }
    if (isset($idx['exact'][$k])) { return $idx['exact'][$k]; }
    if (isset($idx['abbr'][$k]))  { return $idx['abbr'][$k]; }
    foreach ($idx['exact'] as $nm => $hit) {
        if ($nm !== '' && (strpos($k, $nm) === 0 || strpos($nm, $k) === 0)) { return $hit; }
    }
    return null;
}

$idx  = cnido_ps_species_index($cov);
$hit  = ($latin !== '') ? cnido_ps_lookup($idx, $latin) : null;
$abbr = ($hit !== null) ? $hit['abbr'] : '';
$cls  = ($hit !== null && isset($hit['info']['class'])) ? trim((string)$hit['info']['class']) : '';

/* 分组数据与小结。$grouped 为空数组时页面走「这个物种没有记录」分支。 */
$grouped = ($latin !== '') ? cnido_pheno_rows_by_trait($conn, $latin) : array();
$summary = cnido_pheno_summary($grouped);

/* trait 字典（这个 trait 是什么、允许取值有哪些）与文献清单。两张都是小表、
   各一次查询，只在渲染这一个物种时用得到。 */
$traitDict = cnido_pheno_trait_dict($conn);
$refs      = ($latin !== '') ? cnido_pheno_species_refs($conn, $latin) : array();

/* 表型表里的全部物种数（3,948），以及其中**属于本站分类表**的物种数。
 *
 * 后者必须现算，别拿 count($idx['exact']) 充数 —— 那是分类表自己的物种数
 * （326，整个 catalogue），不是「两个集合的交集」。实测交集是 69，两者差
 * 四倍多，写成 326 就是一句假话。
 *
 * 而 69 这个数**不用查库**：$cov['cov'][$abbr1]['phenotype'] 正是每个物种的
 * 记录数，> 0 的就是有数据的。这样算还有一个好处 —— 这里印出来的 69 与
 * includes/species_cards.php 卡片网格上打勾的物种**必然是同一批**，不会出现
 * 「这一页说 69 个、物种页却显示 70 个」。 */
$pheno_species_total = 0;
if ($q = mysqli_query($conn, "SELECT COUNT(DISTINCT species) FROM phenotype")) {
    $pheno_species_total = (int)mysqli_fetch_row($q)[0];
}
$cat_species_total = count($idx['exact']);
$cov_pheno_species = 0;
foreach ((array)$cov['cov'] as $__ab => $__mods) {
    if (isset($__mods['phenotype']) && $__mods['phenotype'] > 0) { $cov_pheno_species++; }
}

$title_sp = ($latin !== '') ? $latin : 'all species';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta charset="utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Phenotype &mdash; <?= htmlspecialchars($title_sp, ENT_QUOTES, 'UTF-8') ?> - CnidoSite</title>
<?php
/* 如实写，不照抄列表页那句「including bleaching traits and thermal tolerance」——
   表里 bleaching 只有 18 行、Water temperature 84 行，拿它当全页的描述是夸大。 */
?>
<meta name="description" content="Phenotypic trait records for <?= htmlspecialchars($title_sp, ENT_QUOTES, 'UTF-8') ?> from the Octocoral Trait Database, WoRMS Marine Species Traits and the Pelagic Species Trait Database, grouped by trait category and labelled by how each value was obtained" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<style type="text/css">
<?php /* 全部类名带 ps- 前缀。本站的表格规则是按类名分派的（table.gridtable /
   table.gridtable1 / table.sampletable），没有裸的 table 规则，所以用自己的类名
   可以完全避开 gridtable 那套 table-layout:fixed + white-space:pre-wrap —— 后者会
   把源码里的缩进当成真的空行印出来。 */ ?>
.ps-wrap { max-width: 1590px; }
.ps-intro {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-left: 4px solid #059669;
    padding: 20px 25px; border-radius: 8px; margin-bottom: 24px;
    line-height: 1.7; color: #475569;
}
.ps-crumb { margin: 0 0 14px 0; font-size: 15px; color: #64748b; }
<?php /* a:link, a:visited 的特异性是 (0,1,1)，单类名 (0,1,0) 压不住，链接配色必须写
   a.类名（见 memory: cnidosite-link-color-specificity-trap）。 */ ?>
a.ps-back { color: #047857; font-weight: 600; text-decoration: none; }
a.ps-back:hover { text-decoration: underline; }
a.ps-x { color: #1d4ed8; text-decoration: none; }
a.ps-x:hover { text-decoration: underline; }

<?php /* ---- 物种条头 ---- */ ?>
.ps-hero {
    background: linear-gradient(135deg, #065f46 0%, #064e3b 100%);
    color: #fff; border-radius: 10px; padding: 20px 26px; margin-bottom: 20px;
    display: flex; align-items: baseline; flex-wrap: wrap; gap: 14px;
}
.ps-hero h1 { margin: 0; font-size: 28px; font-weight: 600; letter-spacing: .2px; }
.ps-hero .ps-abbr {
    font-family: monospace; font-size: 15px; letter-spacing: 1px;
    background: rgba(255,255,255,.18); border-radius: 4px; padding: 3px 9px;
}
.ps-hero .ps-cls {
    font-size: 15px; background: #facc15; color: #422006;
    border-radius: 999px; padding: 3px 12px; font-weight: 600;
}

<?php /* ---- 统计条 ---- */ ?>
<?php /* 统计条：内容栏 1590px 内要放下 10 块。标签用最长的「traits with value」定标，
   宽度靠内容撑开，所以标签必须短 + 不换行，否则第 9、10 块会被挤到第二行单独
   占一行（实测就是这样）。 */ ?>
.ps-stats { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 20px 0; }
.ps-stat {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 10px 14px; flex: 0 0 auto; box-shadow: 0 2px 10px rgba(15,23,42,.05);
}
.ps-stat .ps-n { font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.25; }
.ps-stat .ps-l {
    font-size: 13px; color: #64748b; white-space: nowrap;
}
.ps-stat.is-ok   .ps-n { color: #15803d; }
.ps-stat.is-warn .ps-n { color: #b45309; }
.ps-stat.is-dim  .ps-n { color: #64748b; }

<?php /* ---- 提示块 ---- */ ?>
.ps-note {
    border-radius: 8px; padding: 14px 18px; margin: 0 0 20px 0;
    line-height: 1.7; font-size: 16px; color: #47543f;
}
.ps-note b { color: inherit; }
.ps-note-data { background: #fffbeb; border: 1px solid #fde68a; color: #78350f; }
.ps-note-info { background: #eff6ff; border: 1px solid #dbeafe; color: #1e3a5f; }
.ps-note code {
    background: rgba(0,0,0,.05); padding: 1px 5px; border-radius: 3px;
    font-size: 16px;
}

<?php /* ---- 学名现状（WoRMS 不把本站用的名字当成接受名时才有这一行） ---- */ ?>
.ps-taxon {
    background: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #94a3b8;
    border-radius: 6px; padding: 10px 16px; margin: 0 0 18px 0;
    line-height: 1.7; font-size: 16px; color: #475569;
}
.ps-taxon code {
    background: rgba(0,0,0,.05); padding: 1px 5px; border-radius: 3px;
    font-size: 16px;
}

<?php /* ---- 来源属性图例与小标签 ---- */ ?>
.ps-legend { display: flex; flex-wrap: wrap; gap: 16px; margin: 0 0 18px 0; font-size: 15px; color: #475569; }
.ps-legend span.ps-chip { margin-right: 6px; }
.ps-chip {
    display: inline-block; font-size: 12px; line-height: 1.6;
    padding: 1px 8px; border-radius: 999px; white-space: nowrap;
    border: 1px solid transparent; font-weight: 600;
}

<?php /* ---- 类别分节 ---- */ ?>
.ps-cat {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    margin: 0 0 22px 0; overflow: hidden; box-shadow: 0 2px 12px rgba(15,23,42,.05);
}
.ps-cat > h2 {
    margin: 0; padding: 13px 20px; font-size: 18px; color: #0f172a;
    background: #f1f5f9; border-bottom: 1px solid #e2e8f0;
    display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap;
}
.ps-cat > h2 .ps-catn { font-size: 15px; font-weight: 400; color: #64748b; }
.ps-cat > h2 { scroll-margin-top: 12px; }

<?php /* 类别索引：Corallium rubrum 一个物种就是 438 行、两万多像素，没有索引的话
   想找 Morphological 得滚过整个 Contextual。索引把「有哪些类别、各多少条」
   放在最前面，并且可以跳转。 */ ?>
.ps-idx {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 14px 20px; margin: 0 0 22px 0;
    display: flex; flex-wrap: wrap; gap: 8px 18px; align-items: baseline;
}
.ps-idx .ps-idx-t { font-size: 15px; color: #64748b; margin-right: 4px; }
.ps-idx a.ps-jump {
    font-size: 15px; color: #1d4ed8; text-decoration: none;
    border-bottom: 1px dotted #93c5fd; padding-bottom: 1px;
}
.ps-idx a.ps-jump:hover { border-bottom-style: solid; }
.ps-idx .ps-jump-n { color: #94a3b8; font-size: 15px; }

<?php /* 表本身挂 gridtable：表头底色、下边框、留白、行分隔线、字号全部由
   templatemo_style.css 的共用样式提供（与 core 的 table.cc 一致）。
   原来这里是照着旧样式抄的另一套观感：表头 #f8fafc + 全大写 + 字距、字号 13px、
   单元格下边框是 #f1f5f9 —— 统一后删掉。
   保留 table-layout:fixed：这张表靠 <colgroup> 定列宽，而且表体有 rowspan
   （Trait 一格横跨多行），必须写死列宽才排得齐。 */ ?>
table.ps-tbl { table-layout: fixed; }
table.ps-tbl tr:last-child td { border-bottom: none; }
table.ps-tbl td { overflow-wrap: anywhere; }
<?php /* 整张表左对齐：Trait / Methodology / Region / Obtained by / Source 几列都是文字，
   统一后默认居中会读成一团。数字列（ps-num）另有一条右对齐。 */ ?>
table.gridtable.ps-tbl tr th,
table.gridtable.ps-tbl tr td { text-align: left; }
table.ps-tbl td.ps-trait { font-weight: 600; color: #0f172a; }
table.ps-tbl td.ps-val   { font-family: monospace; }
table.gridtable.ps-tbl tr td.ps-num { text-align: right; color: #64748b; font-variant-numeric: tabular-nums; }
table.ps-tbl td.ps-coord { font-family: monospace; }
table.ps-tbl tr.ps-ph td { background: #fafafa; }
table.ps-tbl tr.ps-ph td.ps-val { color: #94a3b8; }
<?php /* 编号行上「这个编号是什么意思」的注解（10 → Category）。做在值旁边而不是
   单开一列：单开一列会让整张表多出一个 98% 的行都是空的列。 */ ?>
.ps-decode {
    display: block; margin-top: 3px; font-family: system-ui, sans-serif;
    font-size: 12px; color: #b45309; font-style: italic;
}
.ps-decode::before { content: "= "; }
.ps-rep {
    display: inline-block; margin-left: 6px; font-size: 12px; color: #64748b;
    background: #f1f5f9; border-radius: 999px; padding: 0 7px;
}
<?php /* 「整列一个值」的标记刻意做得不显眼：这个物种 23 个有值的 trait 里有 21 个
   都是单值，全都标成琥珀色警告会把真正变化的两个淹掉。它是个说明，不是告警。 */ ?>
.ps-flat {
    font-size: 12px; color: #94a3b8; background: #f8fafc;
    border: 1px solid #eef2f7; border-radius: 5px; padding: 1px 7px;
    display: inline-block; margin-top: 5px; font-weight: 400;
}

<?php /* trait 字典（源库自己的说明）。它比 trait 名字长得多，做成小字压在名字下面，
   并限高 —— 有的说明是一整段，铺开会把每个分类的表格拉得看不出节奏。 */ ?>
.ps-tdesc {
    font-size: 12px; font-weight: 400; color: #64748b; line-height: 1.55;
    margin-top: 4px; max-height: 5em; overflow: hidden;
}
.ps-tallow {
    font-size: 12px; font-weight: 400; color: #94a3b8; line-height: 1.5;
    margin-top: 3px; max-height: 3em; overflow: hidden;
}
<?php /* 生活史之类的限定词。浅色、不等于值本身，避免被读成「值是 adult」。 */ ?>
.ps-ctx {
    display: inline-block; font-family: system-ui, sans-serif; font-size: 12px;
    color: #6d28d9; background: #f5f3ff; border: 1px solid #ede9fe;
    border-radius: 4px; padding: 0 5px; margin-right: 4px; vertical-align: 1px;
}
<?php /* 源库自己给这个值的定性（raw_value / mean / expert_opinion …），照印。 */ ?>
.ps-vt { font-size: 12px; color: #94a3b8; margin-top: 3px; font-style: italic; }

<?php /* 来源库小标签是【外链】，所以配色必须写成 a.ps-src：a:link 的特异性 (0,1,1)
   高过单类名 (0,1,0)，只写 .ps-src 会被站点统一的蓝色压掉（白字变蓝字）。 */ ?>
a.ps-src, span.ps-src { text-decoration: none; }
a.ps-src:hover { text-decoration: underline; }
.ps-legend-src { border-top: 1px dashed #e2e8f0; padding-top: 12px; margin-top: -6px; }
.ps-legend-t { font-size: 15px; color: #64748b; margin-right: 14px; }

<?php /* ---- 引用文献 ---- */ ?>
.ps-refs {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 16px 20px; margin: 0 0 22px 0;
}
.ps-refs h2 { margin: 0 0 10px 0; font-size: 15px; color: #0f172a; }
.ps-refs ol { margin: 0; padding-left: 22px; }
.ps-refs li { font-size: 15px; color: #334155; line-height: 1.65; margin-bottom: 6px; }
.ps-refs .ps-ref-y { color: #64748b; }
.ps-refs .ps-ref-c { color: #64748b; font-style: italic; }
.ps-refs a.ps-ref-doi { color: #1d4ed8; text-decoration: none; }
.ps-refs a.ps-ref-doi:hover { text-decoration: underline; }

<?php /* ---- 无数据时的空态 ---- */ ?>
.ps-empty {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 32px 28px; text-align: center; color: #475569; line-height: 1.8;
}
.ps-empty .ps-big { font-size: 20px; color: #0f172a; font-weight: 600; display: block; margin-bottom: 8px; }

<?php /* ---- 物种清单（没有给 species 参数时） ---- */ ?>
.ps-pick { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 22px; margin-bottom: 22px; }
.ps-pick h2 { margin: 0 0 6px 0; font-size: 18px; color: #0f172a; }
.ps-pick .ps-sub { color: #64748b; font-size: 16px; margin: 0 0 16px 0; }
.ps-pick h3 {
    margin: 18px 0 8px 0; font-size: 15px; color: #334155;
    border-bottom: 1px solid #f1f5f9; padding-bottom: 5px;
}
.ps-pick ul { margin: 0; padding: 0; list-style: none; }
.ps-pick li { display: inline-block; width: 31%; min-width: 250px; padding: 5px 8px 5px 0; font-size: 15px; }
.ps-pick li .ps-cnt { color: #94a3b8; font-size: 15px; }
</style>
</head>
<body>
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
            <li><a href="#">Genome</a>
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
            <li><a href="#" class="current">Phenotype</a>
                <ul>
                    <li><a href="/phenotype.php?class=all">All</a></li>
                    <li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li>
                    <li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li>
                    <li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li>
                    <li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li>
                    <li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li>
                </ul>
            </li>
            <li><a href="#">Tools</a>
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
<div class="ps-wrap">

<?php
/* 输出一律走这一个函数。表里的 region / methodology / trait_name 全部是第三方
   自由文本，原样 echo 就是 XSS —— 列表页 phenotype.php 现在就是这么印的。 */
function ps_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---------------------------------------------------------------------
 * 这个名字在 WoRMS 眼里是什么 —— 有话说才返回一行 HTML，没话说返回 ''。
 *
 * phenotype_species_map 是 fetch_worms_traits.php --load 时 DROP+CREATE 重建的
 * 附属表（species → 有效名 / AphiaID / 状态），随时可能不存在（正在重建、或者被
 * 删掉）。所以查询失败与查不到一律返回 '' —— 页面上少一行说明，好过让用户吃一个
 * 数据库错误（mysqli 在 web SAPI 上失败是静默的，得自己看返回值）。
 *
 * 只印 WoRMS 自己给出的、能对着 AphiaID 核的两件事：
 *   · 它给这个名字解析出的名字与本站表里用的写法不同 → 印那个名字 + AphiaID；
 *   · 它给这条记录的状态不是 accepted → 印状态。
 * 刻意**不写**「接受名是 X」这种话：Alcyonium manusdiaboli 那条，WoRMS 自己把
 * valid_name 写成了海绵 Halichondria panicea（而表里这条记录的 class 是
 * Octocorallia）—— 照抄成「接受名」就是把源库的自相矛盾当事实印出来。
 * 写「places this name under X」是它真正做的事，源库怎么写就怎么转述。
 *
 * 页面上不再把记录改挂到有效名下：表里那一行就是源库写的那一行，改了名用户反而
 * 对不上源库（同样的话印在页脚）。
 * ------------------------------------------------------------------- */
function cnido_ps_worms_note($conn, $latin)
{
    $latin = trim((string)$latin);
    if ($latin === '') { return ''; }
    $esc = mysqli_real_escape_string($conn, $latin);
    /* @ 压掉「表不存在」的 warning：表没了是设计内的情形（整行不印），
       不该在页面上留一条 PHP 警告。 */
    $q = @mysqli_query($conn, "SELECT valid_name, aphia_id, status FROM phenotype_species_map
                               WHERE source = 'WoRMS (Marine Species Traits)' AND species = '$esc'
                               LIMIT 1");
    $row = $q ? mysqli_fetch_assoc($q) : null;
    if (!$row) { return ''; }

    $valid   = trim((string)$row['valid_name']);
    $status  = trim((string)$row['status']);
    $aphia   = trim((string)$row['aphia_id']);
    $differs = ($valid !== '' && strcasecmp($valid, $latin) !== 0);
    $bad     = ($status !== '' && strcasecmp($status, 'accepted') !== 0);
    if (!$differs && !$bad) { return ''; }

    /* AphiaID 只可能是数字，但仍然走 urlencode —— 拼进 URL 的东西不该有例外。 */
    $aph = '';
    if ($aphia !== '' && ctype_digit($aphia)) {
        $aph = ' (AphiaID <a class="ps-x" href="https://www.marinespecies.org/aphia.php?p=taxdetails&amp;id='
             . urlencode($aphia) . '" target="_blank" rel="noopener noreferrer">' . ps_e($aphia) . '</a>)';
    }
    if ($differs && $bad) {
        $say = 'does not accept the name used here &mdash; its status for this record is <code>'
             . ps_e($status) . '</code> &mdash; and places the name under <i>' . ps_e($valid) . '</i>' . $aph;
    } elseif ($differs) {
        $say = 'places this name under <i>' . ps_e($valid) . '</i>' . $aph;
    } else {
        $say = 'does not accept the name used here &mdash; its status for this record is <code>'
             . ps_e($status) . '</code>' . $aph;
    }
    return '<div class="ps-taxon"><b>WoRMS / Marine Species Traits</b> ' . $say
         . '. The records below are listed under the name the source databases used, as stored.</div>';
}

$self = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
$cat  = htmlspecialchars($token_raw, ENT_QUOTES, 'UTF-8');

echo '<p class="ps-crumb"><a class="ps-back" href="phenotype.php?class=all">&larr; All phenotypic records</a>';
if ($latin !== '' && $abbr !== '') {
    echo ' &nbsp;|&nbsp; <a class="ps-back" href="speciesinfo.php?species=' . urlencode($abbr)
       . '">All data for this species</a>';
}
echo '</p>';

/* =====================================================================
 * 分支一：给了 species 但归不到位 —— 这个人/这条链指着一个没有表型记录的物种
 * =================================================================== */
if ($token_raw !== '' && $latin === '') {
    echo '<div class="ps-empty">'
       . '<span class="ps-big">No phenotypic records for <i>' . $cat . '</i></span>';
    /* 在这里必须分清两件不同的事：①物种名根本不在本站分类表里（可能是拼错、
       可能是外界链接）；②物种在，只是表型表里没有它的记录。两者的下一步动作
       不一样 —— 前者去分类表里找，后者说明这是个数据缺口。 */
    $known = cnido_ps_lookup($idx, $token_raw);
    if ($known === null) {
        $lt = cnido_latin_of($token_raw, $conn);
        if ($lt !== '') { $known = cnido_ps_lookup($idx, $lt); }
    }
    if ($known !== null) {
        echo 'This species is in the CnidoSite catalogue, but the integrated trait databases '
           . 'contain no record for it &mdash; the phenotype table covers <b>' . (int)$pheno_species_total
           . '</b> species, of which only <b>' . (int)$cov_pheno_species . '</b> are species in this catalogue. '
           . 'See everything <i>' . $cat . '</i> does have on its '
           . '<a class="ps-x" href="speciesinfo.php?species=' . urlencode($known['abbr']) . '">species page</a>.';
    } else {
        echo 'This name is not in the CnidoSite catalogue, so it cannot have been linked to a '
           . 'catalogue entry. Check the spelling, or look it up in the '
           . '<a class="ps-x" href="browse.php?class=all">taxonomy table</a>.';
    }
    echo '</div>';
}
/* =====================================================================
 * 分支二：没给 species —— 给一份清单，否则本页只能靠深链进入（死胡同）
 * =================================================================== */
elseif ($latin === '') {
    echo '<div class="ps-intro">This page shows every phenotypic trait record held for one species, '
       . 'grouped by trait category. Choose a species below, or reach this page from any species name '
       . 'in the <a class="ps-x" href="phenotype.php?class=all">phenotype table</a>.</div>';

    /* 清单只列「表型表里有记录、且能对上分类表」的物种 —— 与卡片网格用的是同一份
       $cov，所以这里列出来的物种，在物种页上也一定显示有 Phenotype 数据。 */
    $list = array();
    /* 两个数一起取：n 是折叠后的条数（去重过的），sr 是源库里的原始条数。
       源库里内容完全相同的记录被折成一条，只印折叠后的数会显得数据比实际少。 */
    $q = mysqli_query($conn, "SELECT species, COUNT(*) n, SUM(n_records) sr
                                FROM phenotype GROUP BY species ORDER BY species");
    while ($q && ($r = mysqli_fetch_row($q))) {
        $lk = cnido_ps_lookup($idx, $r[0]);
        if ($lk === null) { continue; }
        $c = trim((string)$lk['info']['class']);
        if ($c === '') { $c = 'Other'; }
        if (!isset($list[$c])) { $list[$c] = array(); }
        $list[$c][] = array('latin' => $r[0], 'abbr' => $lk['abbr'], 'n' => (int)$r[1], 'src' => (int)$r[2]);
    }
    ksort($list);
    $nSp = 0;
    foreach ($list as $c => $rows) { $nSp += count($rows); }

    echo '<div class="ps-pick">'
       . '<h2>Catalogue species with phenotypic records</h2>'
       . '<p class="ps-sub">' . (int)$cov_pheno_species . ' of the ' . (int)$cat_species_total . ' species in the catalogue, '
       . 'out of ' . number_format((int)$pheno_species_total) . ' species that appear in the integrated trait databases. '
       . 'The list below follows the trait databases&rsquo; own species names, so a subspecies is listed '
       . 'alongside the species it belongs to. '
       . 'Each species shows its records and, in brackets, the source entries behind them &mdash; identical '
       . 'entries are held as one record with a repeat count.</p>';
    if ($nSp === 0) {
        echo '<p>No species could be matched to the phenotype table.</p>';
    }
    foreach ($list as $c => $rows) {
        echo '<h3>' . ps_e($c) . ' <span class="ps-cnt">(' . count($rows) . ')</span></h3><ul>';
        foreach ($rows as $row) {
            echo '<li><a class="ps-x" href="' . $self . '?species=' . urlencode($row['abbr']) . '">'
               . '<i>' . ps_e($row['latin']) . '</i></a> '
               . '<span class="ps-cnt">' . (int)$row['n'] . ' records'
               . ($row['src'] > $row['n'] ? ' (' . (int)$row['src'] . ' source entries)' : '') . '</span></li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}
/* =====================================================================
 * 分支三：正常渲染一个物种
 * =================================================================== */
else {
    echo '<div class="ps-hero"><h1><i>' . ps_e($latin) . '</i></h1>';
    if ($abbr !== '') { echo '<span class="ps-abbr">' . ps_e($abbr) . '</span>'; }
    if ($cls  !== '') { echo '<span class="ps-cls">' . ps_e($cls) . '</span>'; }
    echo '</div>';

    /* 学名现状那一行：WoRMS 不把本站用的这个名字当成接受名时才有（3,886 个名字里
       100 个）。放在条头下面、统计条上面 —— 它说的是「名字」，不是「这批记录」。 */
    echo cnido_ps_worms_note($conn, $latin);

    if ($summary['rows'] === 0) {
        /* 归位成功但一行都没有 —— 只可能是并发删除，防御性分支。 */
        echo '<div class="ps-empty"><span class="ps-big">No trait records</span>'
           . 'The phenotype table currently holds no record for this species.</div>';
    } else {
        /* ---- 统计条：条数 / 有值 / 来源属性 ---- */
        $ok  = (int)$summary['usable'];      // 带可用值的**记录数**
        $raw = (int)$summary['rows'];        // 折叠后的记录数
        $src = (int)$summary['raw'];         // 折叠前的源记录数
        echo '<div class="ps-stats">';
        echo '<div class="ps-stat" title="Records held for this species; identical source entries are folded into one record">'
           . '<div class="ps-n">' . $raw . '</div><div class="ps-l">records</div></div>';
        /* 折叠计数只有在真的折叠过时才多占一格（Keratoisis grayi 2,097 ← 14,069）。 */
        if ($src > $raw) {
            echo '<div class="ps-stat" title="Entries in the source databases, before identical ones were folded into a single record">'
               . '<div class="ps-n">' . $src . '</div><div class="ps-l">source entries</div></div>';
        }
        echo '<div class="ps-stat ' . ($ok > 0 ? 'is-ok' : 'is-dim') . '" title="Records whose value is a real reading, count or category">'
           . '<div class="ps-n">' . $ok . '</div><div class="ps-l">with a value</div></div>';
        /* 五类来源属性，零的不占格子（expert 是 2026-09-27 新分出来的一类）。 */
        foreach (array('measured', 'expert', 'derived', 'inherited', 'unknown') as $p) {
            $m = cnido_pheno_prov_meta($p);
            $n = (int)$summary[$p];
            if ($n === 0) { continue; }
            echo '<div class="ps-stat" title="' . ps_e($m['hint']) . '"><div class="ps-n" style="color:' . $m['fg'] . '">' . $n
               . '</div><div class="ps-l">' . ps_e($m['label']) . '</div></div>';
        }
        echo '<div class="ps-stat"><div class="ps-n">' . (int)$summary['traits_usable'] . ' / '
           . (int)$summary['traits'] . '</div><div class="ps-l">traits with value</div></div>';
        echo '<div class="ps-stat"><div class="ps-n">' . (int)$summary['categories']
           . '</div><div class="ps-l">categories</div></div>';
        /* regions / coordinates 数的是**全部记录**里的地域与坐标，与「with a value」
           的口径不同。两个格子的 tooltip 把这点写出来，免得读者把两个数看成一回事。 */
        echo '<div class="ps-stat" title="Distinct regions named across all records"><div class="ps-n">'
           . count($summary['regions']) . '</div><div class="ps-l">regions</div></div>';
        echo '<div class="ps-stat" title="Distinct latitude/longitude pairs across all records"><div class="ps-n">'
           . count($summary['coords']) . '</div><div class="ps-l">coordinates</div></div>';
        echo '</div>';

        /* ---- 数据现状说明 ----
           这一块在重抓数据落地后应当整体变小或消失；内容由 $summary 现算，
           不写死数字，所以重抓之后它会自己变准，不需要再改代码。

           先做一遍预扫：这个物种有值的 trait 里，有多少个**真的在变化**。
           一个 23 个 trait 里 21 个都只有一个取值的物种，值得一句话说清 ——
           否则读者会以为下面那张表是 23 组分布。 */
        $tvTotal = 0; $tvVary = 0;
        foreach ($grouped as $__trs) {
            foreach ($__trs as $__rows) {
                $__vals = array();
                foreach ($__rows as $__r) { if (!$__r['placeholder']) { $__vals[] = $__r['value']; } }
                if (!$__vals) { continue; }
                $tvTotal++;
                if (!cnido_pheno_is_flat($__vals)) { $tvVary++; }
            }
        }
        unset($__trs, $__rows, $__vals, $__r);

        $ph = (int)$summary['placeholder'];
        $cd = (int)$summary['coded'];
        if ($ph > 0 || $cd > 0 || $summary['unknown'] > 0 || $tvTotal > 0 || $summary['sources']) {
            /* 基调从 2026-09-27 起变了：以前这段是「警告，值全是编号」，
               现在是「说明这些数是怎么来的」。仍用中性底色而不是琥珀色。 */
            echo '<div class="ps-note ps-note-info"><b>About these numbers.</b> ';
            /* 来源逐家点名（回链在下面那行图例里）。CC BY 的三家都要求署名。 */
            if ($summary['sources']) {
                $__sl = array();
                foreach ($summary['sources'] as $__sn => $__sc) {
                    $__sm = cnido_pheno_source_meta($__sn);
                    $__sl[] = '<b>' . (int)$__sc . '</b> from ' . ps_e($__sm['label']);
                }
                $__last = array_pop($__sl);
                echo 'They come from ' . ($__sl ? implode(', ', $__sl) . ' and ' : '') . $__last . '. ';
            }
            if ($src > $raw) {
                echo 'Identical entries in the source databases are held as one record: the <b>&times;N</b> mark '
                   . 'after a value is how many source entries it stands for, and <b>' . $src . '</b> source entries '
                   . 'are behind the ' . $raw . ' records above. ';
            }
            if ($cd > 0) {
                /* 残留的编号行（重建后只剩 legacy 那批）。措辞是「可核对的事实」：
                   同一个单位下每行都是同一个编号。 */
                echo '<span style="color:#b45309;">' . $cd . ' of them still carry a source database\'s own '
                   . 'trait-identifier code instead of a value</span> (imports made before 2026-09-27 whose source '
                   . 'could not be identified). They are shown greyed with the code\'s meaning printed next to it '
                   . 'where it is known, and are excluded from the counts above. ';
            }
            if ($tvTotal > 0 && $tvVary < $tvTotal) {
                /* 只陈述「每条记录都是同一个值」这个**可直接核对的事实**，不替它下结论
                   说「所以这是该物种的属性」。 */
                echo 'Of the <b>' . $tvTotal . '</b> traits that carry a value for this species, only <b>'
                   . $tvVary . '</b> ' . ($tvVary === 1 ? 'takes more than one value' : 'take more than one value')
                   . '; the other ' . ($tvTotal - $tvVary) . ' repeat a single value in every record, so they '
                   . 'say nothing about variation within the species and should not be quoted as if they did. '
                   . 'Traits in that state are marked <i>same value in every record</i> below. ';
            }
            if ((int)$summary['inherited'] > 0) {
                echo '<b>' . (int)$summary['inherited'] . '</b> ' . ((int)$summary['inherited'] === 1 ? 'record is' : 'records are')
                   . ' not ' . ((int)$summary['inherited'] === 1 ? 'a measurement' : 'measurements') . ' of this species at '
                   . 'all: the source method records the value as <i>inherited</i> from a higher taxon '
                   . '(genus, family or order) because no species-level observation exists. ';
            }
            if ((int)$summary['unknown'] > 0) {
                echo '<b>' . (int)$summary['unknown'] . '</b> ' . ((int)$summary['unknown'] === 1 ? 'record does' : 'records do')
                   . ' not record how the value was obtained.';
            }
            echo '</div>';
        }

        /* 图例。五类来源属性 + 来源库。来源库那几个是外链（署名回链），
           所以配色写成 a.ps-src —— 见下面 CSS 里的说明。 */
        echo '<div class="ps-legend">';
        foreach (array('measured', 'expert', 'derived', 'inherited', 'unknown') as $p) {
            $m = cnido_pheno_prov_meta($p);
            echo '<span><span class="ps-chip" style="background:' . $m['bg'] . ';color:' . $m['fg'] . '">'
               . ps_e($m['label']) . '</span>' . ps_e($m['hint']) . '</span>';
        }
        echo '</div>';
        /* 来源库单独一行：它们是有链接的，混在图例里会被当成说明文字点不到。 */
        echo '<div class="ps-legend ps-legend-src"><span class="ps-legend-t">Sources:</span>';
        foreach ($summary['sources'] as $__sn => $__sc) {
            $__sm = cnido_pheno_source_meta($__sn);
            if ($__sm['url'] !== '') {
                echo '<a class="ps-chip ps-src" href="' . ps_e($__sm['url']) . '" target="_blank" rel="noopener noreferrer"'
                   . ' style="background:' . $__sm['bg'] . ';color:' . $__sm['fg'] . '">' . ps_e($__sm['short']) . '</a>';
            } else {
                echo '<span class="ps-chip ps-src" style="background:' . $__sm['bg'] . ';color:' . $__sm['fg'] . '">'
                   . ps_e($__sm['short']) . '</span>';
            }
            echo '<span class="ps-legend-t">' . ps_e($__sm['label']) . ' &mdash; ' . (int)$__sc . ' records</span>';
        }
        echo '</div>';
        unset($__sl, $__sn, $__sc, $__sm, $__last);

        /* ---- 按类别分块 ---- */
        /* 先算一次每类的行数，索引和分节标题都要用；顺便把锚点定死，
           免得标题里的字符（空格、斜杠）进 id。 */
        $catRows = array(); $catAnch = array();
        $used = array();
        foreach ($grouped as $category => $traits) {
            $n = 0;
            foreach ($traits as $__rows) { $n += count($__rows); }
            $catRows[$category] = $n;
            $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $category));
            $slug = trim($slug, '-');
            if ($slug === '') { $slug = 'cat'; }
            /* 归一之后仍有重名（或整类都是非 ASCII 字符）时后缀保底 */
            $base = $slug; $i = 2;
            while (isset($used[$slug])) { $slug = $base . '-' . $i++; }
            $used[$slug] = true;
            $catAnch[$category] = 'cat-' . $slug;
        }
        unset($__rows, $slug, $base, $i);

        if (count($grouped) > 1) {
            echo '<div class="ps-idx"><span class="ps-idx-t">Jump to</span>';
            foreach ($grouped as $category => $traits) {
                echo '<a class="ps-jump" href="#' . ps_e($catAnch[$category]) . '">' . ps_e($category)
                   . ' <span class="ps-jump-n">' . count($traits) . ' / ' . (int)$catRows[$category]
                   . '</span></a>';
            }
            echo '</div>';
        }

        foreach ($grouped as $category => $traits) {
            echo '<div class="ps-cat"><h2 id="' . ps_e($catAnch[$category]) . '">' . ps_e($category)
               . '<span class="ps-catn">' . count($traits) . (count($traits) === 1 ? ' trait' : ' traits')
               . ' &middot; ' . (int)$catRows[$category] . ((int)$catRows[$category] === 1 ? ' row' : ' rows')
               . '</span></h2>';
            /* data-no-sort 是必需的，不是保险：js/table-sort.js 会在页脚给每张表的
               表头加排序箭头，而本表的 Trait 列是**跨行合并**（rowspan）的 ——
               它按行重排时合并格不跟着走，实测点一下 Value 表头，前几行就脱离了
               「Axis presence」这类 trait 名，第一列直接变成值，等于把数据标错。
               那支脚本给单张表留的退出口就是这个属性（源码里 opt-out 分支）。
               平表视图 phenotype.php 有服务端排序，整表排序在那里做。 */
            echo '<table class="gridtable ps-tbl" data-no-sort><colgroup>'
               . '<col style="width:18%"><col style="width:12%"><col style="width:6%">'
               . '<col style="width:12%"><col style="width:11%"><col style="width:20%">'
               . '<col style="width:11%"><col style="width:10%">'
               . '</colgroup><tr>'
               . '<th>Trait</th><th>Value</th><th>Unit</th>'
               . '<th>Region</th><th>Coordinates</th><th>Methodology</th>'
               . '<th>Obtained by</th><th>Source</th></tr>';

            foreach ($traits as $trait => $rows) {
                /* 这个 trait 可用值的分布广度：只有 1 个不同取值（或同一个值占了
                   ≥95%）时，数据本身撑不起任何「分布」的说法，如实标出来。 */
                $vals = array();
                foreach ($rows as $r) { if (!$r['placeholder']) { $vals[] = $r['value']; } }
                $flat = ($vals && cnido_pheno_is_flat($vals)) ? count(array_unique($vals)) : 0;
                /* trait 字典（OCTD 的 trait_id.csv / Pelagic 的 metadata.csv）
                   给出「这个 trait 是什么」和它的允许取值。这是**源库自己的说明**，
                   不是本站的解释 —— 用户看到 'Axis presence' 不必去猜。 */
                $td = isset($traitDict[$trait]) ? $traitDict[$trait] : null;

                $first = true;
                foreach ($rows as $r) {
                    $pm = cnido_pheno_prov_meta($r['prov']);
                    $sm = ($r['source'] !== '' ? $r['src'] : null);
                    echo '<tr' . ($r['placeholder'] ? ' class="ps-ph"' : '') . '>';
                    if ($first) {
                        $span = count($rows);
                        echo '<td class="ps-trait" rowspan="' . $span . '">' . ps_e($trait);
                        if ($td && $td['desc'] !== '') {
                            /* 按**字符**截断（不是字节）：mb_substr 才不会被多字节
                               字符切出半个字。 */
                            $dsc = $td['desc'];
                            if (mb_strlen($dsc, 'UTF-8') > 260) { $dsc = mb_substr($dsc, 0, 257, 'UTF-8') . '…'; }
                            echo '<div class="ps-tdesc">' . ps_e($dsc) . '</div>';
                        }
                        if ($td && $td['allowed'] !== '') {
                            $alw = $td['allowed'];
                            if (mb_strlen($alw, 'UTF-8') > 150) { $alw = mb_substr($alw, 0, 147, 'UTF-8') . '…'; }
                            echo '<div class="ps-tallow">Allowed: ' . ps_e($alw) . '</div>';
                        }
                        if ($flat) {
                            echo '<div class="ps-flat">' . ($flat === 1
                                    ? 'same value in every record'
                                    : 'only ' . $flat . ' distinct values')
                               . '</div>';
                        }
                        echo '</td>';
                        $first = false;
                    }
                    echo '<td class="ps-val">';
                    /* 生活史等限定词（Pelagic 的行有）。它限定的是这一行，
                       不是并列的 trait，所以放在值前面当一个浅色小标记。 */
                    if ($r['context'] !== '') {
                        echo '<span class="ps-ctx" title="this record is limited to one life stage">'
                           . ps_e($r['context']) . '</span> ';
                    }
                    echo ($r['value'] === '' ? '&mdash;' : ps_e($r['value']));
                    /* ×N = 源库里内容完全相同的记录有几条（重建时折成一行）。 */
                    if ($r['n'] > 1) {
                        echo '<span class="ps-rep" title="identical entries in the source database folded into this row">&times;'
                           . (int)$r['n'] . '</span>';
                    }
                    /* 编号行把编号的含义直接印在值旁边（10 → Category）。这是从
                       OCTD 的 standard_id 字典里查出来的，不是猜的；查不到就什么都不印
                       —— 与其猜一个名字，不如让读者只看到一个裸编号。 */
                    if ($r['placeholder'] && $r['value'] !== '') {
                        $dn = cnido_pheno_coded_name($r['value'], $r['unit_raw'], $r['source']);
                        if ($dn !== '') { echo '<span class="ps-decode">' . ps_e($dn) . '</span>'; }
                    }
                    echo '</td>';
                    echo '<td>' . ($r['unit'] === '' ? '&mdash;' : ps_e($r['unit'])) . '</td>';
                    echo '<td>' . ($r['region'] === '' ? '&mdash;' : ps_e($r['region'])) . '</td>';
                    echo '<td class="ps-coord">' . ($r['coords'] === '' ? '&mdash;' : ps_e($r['coords'])) . '</td>';
                    echo '<td>' . ($r['method'] === '' ? '&mdash;' : ps_e($r['method'])) . '</td>';
                    /* 「Obtained by」列在编号行上写 not a value —— 否则一行标着
                       measured、值却是常数 10，看起来像真测出来的。
                       下面那行小字是**源库自己**给这个值的定性（raw_value / mean /
                       expert_opinion …），照印不翻译：它比我们的四分类更细。 */
                    if ($r['placeholder']) {
                        echo '<td><span class="ps-chip" style="background:#f1f5f9;color:#64748b">not a value</span></td>';
                    } else {
                        echo '<td><span class="ps-chip" style="background:' . $pm['bg'] . ';color:' . $pm['fg'] . '" title="'
                           . ps_e($pm['hint']) . '">' . ps_e($pm['label']) . '</span>';
                        if ($r['vt_label'] !== '') {
                            echo '<div class="ps-vt">value is ' . ps_e($r['vt_label']) . '</div>';
                        }
                        echo '</td>';
                    }
                    /* Source 列：回链署名。WoRMS 的条款要求署名，三家都是 CC BY。 */
                    if ($sm !== null && $sm['url'] !== '') {
                        echo '<td><a class="ps-chip ps-src" href="' . ps_e($sm['url'])
                           . '" target="_blank" rel="noopener noreferrer" style="background:' . $sm['bg']
                           . ';color:' . $sm['fg'] . '" title="' . ps_e($sm['label']) . '">' . ps_e($sm['short']) . '</a></td>';
                    } else {
                        $sm2 = ($sm !== null ? $sm : cnido_pheno_source_meta($r['source']));
                        echo '<td><span class="ps-chip ps-src" style="background:' . $sm2['bg'] . ';color:' . $sm2['fg']
                           . '" title="' . ps_e($sm2['label']) . '">' . ps_e($sm2['short']) . '</span></td>';
                    }
                    echo "</tr>\n";
                }
            }
            echo '</table></div>';
        }

        /* ---- 引用文献：这些记录出自哪儿 ----
           三家源库都是 CC BY，署名是许可要求、也是用户判断可信度的依据。
           一条文献对应源库里的一篇文章（OCTD 的 resource_id），主表每一行都带着
           它，所以这里的计数是「本文献支撑了这个物种的几条记录」。 */
        if ($refs) {
            $show = 30;
            echo '<div class="ps-refs"><h2>Sources cited for <i>' . ps_e($latin) . '</i></h2><ol>';
            foreach (array_slice($refs, 0, $show) as $rf) {
                $sm = cnido_pheno_source_meta($rf['source']);
                $au = trim((string)$rf['authors']);
                /* 作者字段有的只写了名、有的带年份，原文照印（截断按字符）。 */
                if (mb_strlen($au, 'UTF-8') > 120) { $au = mb_substr($au, 0, 117, 'UTF-8') . '…'; }
                echo '<li>';
                if ($au !== '') { echo ps_e($au) . ' '; }
                if (trim((string)$rf['year']) !== '') {
                    echo '<span class="ps-ref-y">(' . ps_e($rf['year']) . ')</span> ';
                }
                $ti = trim((string)$rf['title']);
                if ($ti !== '') { echo ps_e($ti) . '. '; }
                if (trim((string)$rf['container']) !== '') {
                    echo '<span class="ps-ref-c">' . ps_e($rf['container']) . '</span>. ';
                }
                $doi = trim((string)$rf['doi']);
                if ($doi !== '') {
                    /* DOI 有的在库里就写成 https://doi.org/… 的整串，别拼两次。 */
                    $href = (stripos($doi, 'http') === 0) ? $doi : 'https://doi.org/' . $doi;
                    echo '<a class="ps-ref-doi" href="' . ps_e($href)
                       . '" target="_blank" rel="noopener noreferrer">' . ps_e($doi) . '</a> ';
                }
                echo '<span class="ps-ref-y">&mdash; ' . (int)$rf['rows_'] . ' record'
                   . ((int)$rf['rows_'] === 1 ? '' : 's') . ' from ' . ps_e($sm['short']) . '</span>';
                echo '</li>';
            }
            echo '</ol>';
            if (count($refs) > $show) {
                echo '<div class="ps-ref-y">Showing the ' . $show . ' most recent of '
                   . count($refs) . ' cited sources; the rest are attached to the records in the '
                   . '<a class="ps-x" href="phenotype.php?class=all&amp;species=' . urlencode($latin)
                   . '">flat table view</a>.</div>';
            }
            echo '</div>';
        }

        /* ---- 页脚说明：这张小表在全局里的位置 ---- */
        echo '<div class="ps-note ps-note-info">Across all species, the integrated trait databases hold '
           . '<b>' . (int)$pheno_species_total . '</b> species; <b>' . (int)$cov_pheno_species . '</b> of them are species in '
           . 'this catalogue. Values labelled <i>inherited</i> belong to a higher taxon and should not be '
           . 'quoted as measurements of <i>' . ps_e($latin) . '</i>; values labelled <i>derived</i> were '
           . 'computed by the source database, not observed. Each row carries only the single value the '
           . 'source recorded &mdash; these are not ranges or replicates. The '
           . '<a class="ps-x" href="phenotype.php?class=all&amp;species=' . urlencode($latin) . '">flat table view</a> '
           . 'lists the same rows without grouping.</div>';
    }
}
?>
</div>
</div>
</div>
</div>

<?php
    include "Webpage_components.php";
    print $footer;
?>
</body>
</html>
