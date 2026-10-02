<?php
/* =====================================================================
 * phenotype_charts.php —— 表型数据总览（四张图）
 *
 * 为什么单开一页：phenotype.php 是十几万行的平表，phenotype_species.php 是一个
 * 物种的清单。用户要的第三件事是「站在用户角度，怎么可视化最好」—— 那需要一个
 * 把整张表压成几张图的地方。四张图对应四个问题：
 *
 *   1. 覆盖矩阵   哪些物种、哪几类性状被记录过（物种 × trait 类别热图）
 *   2. 来源属性   这些值是测出来的、算出来的，还是从高阶元继承的（堆叠条）
 *   3. 数值分布   真正有读数的那些 trait 长什么样（直方图，Highcharts）
 *   4. 地理散点   记录发生在哪里（Leaflet；只有 10% 的行有经纬度）
 *
 * 数据全部现查现算（整页 1.5 s 上下：覆盖矩阵 0.4 s、来源属性 0.5 s、编号行
 * 0.25 s、地图 0.13 s、每个直方图 0.08 s），不落额外缓存 —— 这一页的数字本来
 * 就该与 phenotype.php 同源，缓存一份就又多一个会过期的地方。
 *
 * 判定与配色一律走 includes/phenotype_traits.php（与另外两页共用一套），本页
 * 不自己写第二份「什么是继承」。图表的取值口径写在每个面板的说明里。
 *
 * ★ 诚实性约定（改这一页时必须一起改）：
 *   · 矩阵与堆叠条的计数单位是**折叠后的行数**（COUNT(*)），不是源记录数；
 *   · 直方图只画「一个 trait 一个单位」的子集，并且跳过整列一个值的 trait；
 *   · 地图只画有经纬度的行，**不**用 region 名去猜坐标 —— 猜出来的点是假数据。
 * ===================================================================== */

require_once __DIR__ . '/includes/state.php';
require_once __DIR__ . '/includes/phenotype_traits.php';
require_once __DIR__ . '/includes/coverage.php';   /* cnido_coverage()：本站分类表里哪些物种有表型数据 */

require_once __DIR__ . '/includes/stats.php';
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { error_log('CnidoSite: database connection failed'); die('The database is temporarily unavailable. Please try again in a moment.'); }
$conn->set_charset('utf8mb4');

/** 输出一律走这一个函数：region / methodology / trait_name / species 全是第三方
 *  自由文本，原样 echo 就是 XSS（列表页以前就是这么印的）。 */
function pc_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** 跑一条查询、收成二维数组。失败返回空数组并把错误留在 $__sqlerr 里给页脚印。 */
$__sqlerr = '';
function pc_q($conn, $sql)
{
    global $__sqlerr;
    $r = mysqli_query($conn, $sql);
    if ($r === false) { if ($__sqlerr === '') { $__sqlerr = mysqli_error($conn); } return array(); }
    $out = array();
    while ($x = mysqli_fetch_assoc($r)) { $out[] = $x; }
    return $out;
}

/* ---------------------------------------------------------------------
 * 0. 全表数字（与 phenotype.php 页脚同源：cnido_pheno_global_stats 的 1 小时缓存）
 * ------------------------------------------------------------------- */
$gs = cnido_pheno_global_stats($conn);

/* ---------------------------------------------------------------------
 * 1. 物种 × trait 类别 覆盖矩阵
 * ------------------------------------------------------------------- */
/* 一次 group by 拿全部格子（约一万行），再在 PHP 里取「记录最多的前 N 个物种」。
 * 不在 SQL 里 LIMIT：LIMIT 要在按物种汇总之后才谈得上（一个物种有好几行），
 * 直接 LIMIT 会截出半个物种。 */
$MATRIX_SPECIES = 48;
$mxRows = pc_q($conn, "SELECT species, trait_category, COUNT(*) n, SUM(n_records) sr
                       FROM phenotype GROUP BY species, trait_category");
$mxBySp = array();
foreach ($mxRows as $r) {
    $sp = (string)$r['species'];
    if ($sp === '') { continue; }
    $cat = cnido_pheno_category($r['trait_category']);   /* 归一：源的 Stoichometric → Stoichiometric */
    if ($cat === '') { $cat = '(no category)'; }
    if (!isset($mxBySp[$sp])) { $mxBySp[$sp] = array('n' => 0, 'sr' => 0, 'cats' => array(), 'catsr' => array()); }
    $mxBySp[$sp]['n']  += (int)$r['n'];
    $mxBySp[$sp]['sr'] += (int)$r['sr'];
    $mxBySp[$sp]['cats'][$cat]  = (isset($mxBySp[$sp]['cats'][$cat]) ? $mxBySp[$sp]['cats'][$cat] : 0) + (int)$r['n'];
    $mxBySp[$sp]['catsr'][$cat] = (isset($mxBySp[$sp]['catsr'][$cat]) ? $mxBySp[$sp]['catsr'][$cat] : 0) + (int)$r['sr'];
}
/* 排序：记录数降序 → 物种名升序（后者保证同分时的次序稳定，不会两次请求换顺序）。 */
uksort($mxBySp, function ($a, $b) use ($mxBySp) {
    if ($mxBySp[$a]['n'] !== $mxBySp[$b]['n']) { return $mxBySp[$b]['n'] - $mxBySp[$a]['n']; }
    return strcasecmp($a, $b);
});
/* 列：按全表记录数降序，第 1 列固定是 morphological 之外谁多谁靠前，不写死顺序。 */
$mxColTot = array();
foreach ($mxBySp as $sp => $d) {
    foreach ($d['cats'] as $cat => $n) {
        $mxColTot[$cat] = (isset($mxColTot[$cat]) ? $mxColTot[$cat] : 0) + $n;
    }
}
arsort($mxColTot);
$mxCols = array_keys($mxColTot);
$mxTop  = array_slice($mxBySp, 0, $MATRIX_SPECIES, true);

/* 颜色档：计数跨六个数量级（1 → 1969），线性渐变会把所有格子压成同一个浅色，
 * 所以按档取色（每档约 5 倍），并把档位写进图例，让读者知道深色=多。 */
function pc_heat($n)
{
    if ($n <= 0)   { return array('', '#f8fafc'); }
    if ($n <= 4)   { return array('#dcfce7', '#166534'); }
    if ($n <= 19)  { return array('#bbf7d0', '#14532d'); }
    if ($n <= 99)  { return array('#86efac', '#14532d'); }
    if ($n <= 499) { return array('#4ade80', '#052e16'); }
    return array('#22c55e', '#f0fdf4');
}

/* ---------------------------------------------------------------------
 * 2. 来源属性（实测 / 专家判断 / 推导 / 继承 / 未知）分 trait 类别与分 Class
 * ------------------------------------------------------------------- */
/* 判定要 methodology + value_type 两个字段一起看，而判定函数在 PHP 里，所以把
 * 这两个字段的**组合**取回来（几十行），在 PHP 里分类 —— 不要在 SQL 里用
 * CASE WHEN 重写一套判定，那必然与 includes/phenotype_traits.php 分叉。 */
$pvRows = pc_q($conn, "SELECT trait_category, Class, methodology, value_type,
                              COUNT(*) n, SUM(n_records) sr
                       FROM phenotype GROUP BY trait_category, Class, methodology, value_type");
$PROVS = array('measured', 'expert', 'derived', 'inherited', 'unknown');
$byCat = array(); $byCls = array();
foreach ($pvRows as $r) {
    $prov = cnido_pheno_provenance($r['methodology'], $r['value_type']);
    $cat  = cnido_pheno_category($r['trait_category']);
    if ($cat === '') { $cat = '(no category)'; }
    $cls  = trim((string)$r['Class']);
    if ($cls === '') { $cls = '(no class)'; }
    $n = (int)$r['n'];
    if (!isset($byCat[$cat])) { $byCat[$cat] = array_fill_keys($PROVS, 0); $byCat[$cat]['__n'] = 0; }
    if (!isset($byCls[$cls])) { $byCls[$cls] = array_fill_keys($PROVS, 0); $byCls[$cls]['__n'] = 0; }
    $byCat[$cat][$prov] += $n; $byCat[$cat]['__n'] += $n;
    $byCls[$cls][$prov] += $n; $byCls[$cls]['__n'] += $n;
}
uasort($byCat, function ($a, $b) { return $b['__n'] - $a['__n']; });
uasort($byCls, function ($a, $b) { return $b['__n'] - $a['__n']; });

/* ---------------------------------------------------------------------
 * 3. 数值型 trait 的分布（直方图）
 * ------------------------------------------------------------------- */
/* 选谁画，规则要能被读者复核，所以四条都印在图上：
 *   · value 整串是数字（^[0-9]+(\.[0-9]+)?$）—— 与 cnido_pheno_numeric() 同一套判据；
 *   · 单位不是 bin / cat（那是 0/1 标记和分类名，不是量）；
 *   · 至少 150 行、至少 8 个不同取值 —— 整列一个值的 trait（如
 *     Number of tentacles per polyp 3,540 行全是同一个数）画成直方图是骗人的；
 *   · 取行数最多的 6 个。 */
$NUM_MIN_ROWS = 150;
$NUM_MIN_DIST = 8;
$NUM_CHARTS   = 6;
$numCand = pc_q($conn, "SELECT trait_name, traitunit, COUNT(*) n, COUNT(DISTINCT value) dv,
                               MIN(CAST(value AS DECIMAL(24,6))) mn, MAX(CAST(value AS DECIMAL(24,6))) mx
                        FROM phenotype
                        WHERE value REGEXP '^[0-9]+(\\\\.[0-9]+)?\$'
                          AND traitunit NOT IN ('bin','cat','')
                        GROUP BY trait_name, traitunit
                        HAVING n >= " . (int)$NUM_MIN_ROWS . " AND dv >= " . (int)$NUM_MIN_DIST . "
                        ORDER BY n DESC LIMIT 40");
/* 只保留前 6 个**能画**的（范围退化为 0 的跳过：min=max 说明其实是一列常数）。 */
$numPick = array();
foreach ($numCand as $c) {
    if ((float)$c['mx'] <= (float)$c['mn']) { continue; }
    $numPick[] = $c;
    if (count($numPick) >= $NUM_CHARTS) { break; }
}
/* 每个被选中的 trait 取回原始值算分箱（几千行，很便宜）。分箱在 PHP 里做，
 * 不用 SQL 的 width_bucket：这里要在图上印出真实的分箱边界。 */
$NUM_BINS = 18;
foreach ($numPick as $i => $c) {
    $e = mysqli_real_escape_string($conn, (string)$c['trait_name']);
    $eu = mysqli_real_escape_string($conn, (string)$c['traitunit']);
    $vals = array();
    $q = mysqli_query($conn, "SELECT CAST(value AS DECIMAL(24,6)) v FROM phenotype
                              WHERE trait_name='$e' AND traitunit='$eu'
                                AND value REGEXP '^[0-9]+(\\\\.[0-9]+)?\$'");
    while ($q && ($x = mysqli_fetch_row($q))) { $vals[] = (float)$x[0]; }
    sort($vals);
    $n = count($vals);
    $mn = $n ? $vals[0] : 0.0; $mx = $n ? $vals[$n - 1] : 0.0;
    $w = ($n && $mx > $mn) ? ($mx - $mn) / $NUM_BINS : 1.0;
    $bins = array();
    for ($b = 0; $b < $NUM_BINS; $b++) {
        $bins[] = array('lo' => $mn + $b * $w, 'hi' => $mn + ($b + 1) * $w, 'n' => 0);
    }
    foreach ($vals as $v) {
        $b = (int)floor(($v - $mn) / $w);
        if ($b >= $NUM_BINS) { $b = $NUM_BINS - 1; }   /* 最大值落进最后一箱 */
        if ($b < 0) { $b = 0; }
        $bins[$b]['n']++;
    }
    $med = $n ? $vals[(int)floor($n / 2)] : 0.0;
    $numPick[$i]['bins'] = $bins; $numPick[$i]['mn'] = $mn; $numPick[$i]['mx'] = $mx;
    $numPick[$i]['med'] = $med; $numPick[$i]['n2'] = $n;
    unset($vals);
}
/* 单位写法：这一页把 Unit 原样印出来（Pelagic 用的是 U+02DA 的 ˚C，OCTD 用 °C，
 * 两家都没错 —— 归一成一种写法会让「单位是源库原文」这句话变成假话）。 */
$numSkipped = count($numCand) - count($numPick);

/* ---------------------------------------------------------------------
 * 4. 经纬度散点
 * ------------------------------------------------------------------- */
/* 按 (经纬度, 物种) 取，再在 PHP 里合成点：一个点上往往站着一个物种的上百条记录
 * （44.637/-55.564 一个物种 1,370 行），逐行画点会是同一坨叠几百次。 */
$mapRows = pc_q($conn, "SELECT ROUND(latitude,3) la, ROUND(longitude,3) lo, species,
                               MAX(Class) cls, COUNT(*) n, SUM(n_records) sr
                        FROM phenotype
                        WHERE TRIM(COALESCE(latitude,'')) NOT IN ('','-')
                          AND TRIM(COALESCE(longitude,'')) NOT IN ('','-')
                        GROUP BY la, lo, species");
$mapPts = array(); $mapSpecies = array(); $mapRowsN = 0; $mapSrcN = 0;
foreach ($mapRows as $r) {
    /* ROUND 后可能落到 -0.0 之类的写法，统一成字符串键。 */
    $k = rtrim(rtrim(number_format((float)$r['la'], 3, '.', ''), '0'), '.') . ','
       . rtrim(rtrim(number_format((float)$r['lo'], 3, '.', ''), '0'), '.');
    if (!isset($mapPts[$k])) {
        $mapPts[$k] = array('la' => (float)$r['la'], 'lo' => (float)$r['lo'],
                            'n' => 0, 'sr' => 0, 'cls' => array(), 'sp' => array());
    }
    $mapPts[$k]['n']  += (int)$r['n'];
    $mapPts[$k]['sr'] += (int)$r['sr'];
    $mapPts[$k]['sp'][(string)$r['species']] = (int)$r['n'];
    $mapPts[$k]['cls'][(string)$r['cls']] = (isset($mapPts[$k]['cls'][(string)$r['cls']])
        ? $mapPts[$k]['cls'][(string)$r['cls']] : 0) + (int)$r['n'];
    $mapSpecies[(string)$r['species']] = 1;
    $mapRowsN += (int)$r['n']; $mapSrcN += (int)$r['sr'];
}
$mapOut = array();
foreach ($mapPts as $k => $p) {
    arsort($p['sp']); arsort($p['cls']);
    $top = array_slice($p['sp'], 0, 3, true);
    $mapOut[] = array('la' => $p['la'], 'lo' => $p['lo'], 'n' => $p['n'], 'sr' => $p['sr'],
                      'sp' => count($p['sp']), 'cls' => array_key_first($p['cls']),
                      'top' => $top);
}
/* 点的颜色按**主要 Class**（该点上记录最多的那一类），并只给这五类配色。 */
function pc_cls_color($cls)
{
    static $m = array('Octocorallia' => '#0ea5e9', 'Hydrozoa' => '#f59e0b',
                      'Scyphozoa' => '#8b5cf6', 'Hexacorallia' => '#ef4444',
                      'Cubozoa' => '#10b981');
    return isset($m[$cls]) ? $m[$cls] : '#64748b';
}
$mapClsN = array();
foreach ($mapPts as $p) { arsort($p['cls']); $c = array_key_first($p['cls']); $mapClsN[$c] = (isset($mapClsN[$c]) ? $mapClsN[$c] : 0) + 1; }
arsort($mapClsN);

/* 地图 JSON：只带弹窗需要的字段（物种名最多三个），把体积压在两百 KB 以内。 */
$mapJson = json_encode($mapOut, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$mapMaxN = 0;
foreach ($mapOut as $p) { if ($p['n'] > $mapMaxN) { $mapMaxN = $p['n']; } }

/* ---------------------------------------------------------------------
 * 5. 「还缺什么数据」那一段的数字：全部现查，一个也不写死
 * ------------------------------------------------------------------- */
$gapCoordRows  = $mapRowsN;                        /* 有坐标的折叠行数 */
$gapCoordTotal = (int)$gs['tot'];                  /* 全表折叠行数 */
/* 年份/月份在源库里是**两个 trait**（不是每条记录一个字段），所以只能这样数。
   两条分开查：合起来写会让「78 个物种有年份」与「18 个物种有月份」混成一个数。 */
$gapYearQ  = pc_q($conn, "SELECT COUNT(*) n, COUNT(DISTINCT species) sp FROM phenotype WHERE trait_name = 'Year'");
$gapMonthQ = pc_q($conn, "SELECT COUNT(*) n FROM phenotype WHERE trait_name = 'Month'");
$gapYearN  = isset($gapYearQ[0]) ? (int)$gapYearQ[0]['n'] : 0;
$gapYearSp = isset($gapYearQ[0]) ? (int)$gapYearQ[0]['sp'] : 0;
$gapMonthN = isset($gapMonthQ[0]) ? (int)$gapMonthQ[0]['n'] : 0;
/* value_type 的字面分布 —— 「最小/最大/中点是分成三行存的」那句话的数字来源。
   这条与 cnido_pheno_global_stats() 里的分类是两件事：那个是按本站五档归类后的
   计数，这里是源库自己写的原文，用来支撑「范围被拆成多行」这个说法。 */
$gapVtQ = pc_q($conn, "SELECT value_type, COUNT(*) n FROM phenotype
                       WHERE value_type IN ('maximum','minimum','mid_range','median','mean','model_derived')
                       GROUP BY value_type");
$gapVt = array();
foreach ($gapVtQ as $r) { $gapVt[(string)$r['value_type']] = (int)$r['n']; }

/* 覆盖面的三个数：本站分类表里有多少物种、其中有多少在表型表里有记录、表型表里
 * 出现了几个「纲」。第三个数要说清「没有的那两个是哪些」—— 只说「只有 5 个纲」
 * 读者无从判断缺的是谁。
 * 交集用 $cov（与物种页、覆盖卡片同一份缓存）而不是拿拉丁名去 JOIN：两边的写法
 * 不一定逐字相同（下划线/空格），JOIN 出来的数会与卡片网格上的对勾对不上。 */
$cov = cnido_coverage($conn);
/* 键名是 'species'（abbr → array(species, abbr, class)），不是 'exact' —— 'exact'
   是 phenotype_species.php 里那份为了匹配学名写法而另建的索引，两者不是一回事。
   写错键取到空数组时 count() 是 0，页面上会印出「Of the 0 species in this
   catalogue」而不报任何错。 */
$catSpecies   = count(isset($cov['species']) ? $cov['species'] : array());
$covPhenoSp   = 0;
foreach ((array)(isset($cov['cov']) ? $cov['cov'] : array()) as $__ab => $__mods) {
    if (isset($__mods['phenotype']) && $__mods['phenotype'] > 0) { $covPhenoSp++; }
}
/* 分类表里的纲 vs 表型表里的纲 */
$catClasses = array(); $phClasses = array();
$q = mysqli_query($conn, "SELECT Class, COUNT(DISTINCT Species) sp FROM classfy
                          WHERE TRIM(COALESCE(Class,'')) <> '' GROUP BY Class ORDER BY sp DESC");
while ($q && ($r = mysqli_fetch_assoc($q))) { $catClasses[(string)$r['Class']] = (int)$r['sp']; }
$q = mysqli_query($conn, "SELECT DISTINCT Class FROM phenotype WHERE TRIM(COALESCE(Class,'')) <> ''");
while ($q && ($r = mysqli_fetch_row($q))) { $phClasses[(string)$r[0]] = 1; }
$missingClasses = array();
foreach ($catClasses as $c => $n) { if (!isset($phClasses[$c])) { $missingClasses[$c] = $n; } }
/* 每个物种的记录数分布（决定「大部分只有个位数」这句话能不能说） */
$spDist = array('lt10' => 0, 'n' => 0);
$q = mysqli_query($conn, "SELECT COUNT(*) FROM (SELECT species FROM phenotype
                                             GROUP BY species HAVING COUNT(*) < 10) z");
if ($q && ($r = mysqli_fetch_row($q))) { $spDist['lt10'] = (int)$r[0]; }
$q = mysqli_query($conn, "SELECT COUNT(*) FROM (SELECT species FROM phenotype GROUP BY species) z");
if ($q && ($r = mysqli_fetch_row($q))) { $spDist['n'] = (int)$r[0]; }
/* 源库里存在、但我们没有导入的列（数字来自 OCTD 主表本身，脚本 data/octd_unused_columns.json
 * 由 build_phenotype_v2.php 的伙伴脚本统计 —— 这里读不到就整段不印，不编数）。 */
$gapFile = '/var/www/cnidosite-tools/data/octd_unused_columns.json';
$gap = is_file($gapFile) ? json_decode((string)file_get_contents($gapFile), true) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta charset="utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Phenotype overview &mdash; five views of the trait data - CnidoSite</title>
<meta name="description" content="Coverage, provenance, distributions and geography of the cnidarian trait records integrated from the Octocoral Trait Database, WoRMS Marine Species Traits and the Pelagic Species Trait Database" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<link href="/css/leaflet.css" rel="stylesheet" type="text/css" />
<style type="text/css">
<?php /* 类名统一 pc- 前缀。本站的表格规则按类名分派（gridtable / sampletable …），
   没有裸的 table 规则，所以用自己的类名可以完全避开 gridtable 的
   table-layout:fixed + white-space:pre-wrap（后者会把源码缩进印成空行）。 */ ?>
.pc-wrap { max-width: 1590px; }
.pc-intro {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-left: 4px solid #059669; padding: 20px 25px; border-radius: 8px;
    margin-bottom: 24px; line-height: 1.7; color: #475569;
}
.pc-intro h2 { margin: 0 0 8px 0; font-size: 20px; color: #0f172a; }
.pc-crumb { margin: 0 0 14px 0; font-size: 15px; color: #64748b; }
<?php /* a:link/a:visited 的特异性是 (0,1,1)，单类名 (0,1,0) 压不住 —— 给 <a> 上色的
   规则必须写成 a.类名，否则颜色被站点统一的蓝色顶掉。 */ ?>
a.pc-x { color: #1d4ed8; text-decoration: none; }
a.pc-x:hover { text-decoration: underline; }

.pc-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    margin: 0 0 24px 0; overflow: hidden; box-shadow: 0 2px 12px rgba(15,23,42,.05);
}
.pc-card > h2 {
    margin: 0; padding: 14px 20px; font-size: 18px; color: #0f172a;
    background: #f1f5f9; border-bottom: 1px solid #e2e8f0;
}
.pc-card > h2 .pc-q { font-weight: 400; font-size: 15px; color: #64748b; }
.pc-body { padding: 18px 20px; }
.pc-cap {
    font-size: 15px; color: #64748b; line-height: 1.7; margin: 12px 0 0 0;
    border-top: 1px dashed #e2e8f0; padding-top: 10px;
}
.pc-cap b { color: #334155; }

<?php /* ---- 覆盖矩阵 ---- */ ?>
table.pc-mx { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 15px; }
table.pc-mx th, table.pc-mx td {
    border: 1px solid #f1f5f9; padding: 3px 5px; text-align: center;
    font-variant-numeric: tabular-nums; overflow: hidden;
}
<?php /* 表头 17 个类别 + 物种 + 合计，格子只有 70 多像素宽：字号压到 10px 并允许在词内
   换行（overflow-wrap），否则 Biomechanical 这种长词会把列撑开、把 species 列挤掉。 */ ?>
table.pc-mx th {
    background: #f8fafc; color: #334155; font-weight: 700; font-size: 12px;
    vertical-align: bottom; line-height: 1.25; overflow: visible;
    overflow-wrap: anywhere; word-break: break-word;
}
table.pc-mx th.pc-mx-sp, table.pc-mx td.pc-mx-sp {
    text-align: left; width: 15%; font-size: 15px; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
}
table.pc-mx td.pc-mx-sp { color: #0f172a; }
table.pc-mx td.pc-mx-sp a { color: #0f172a; text-decoration: none; }
table.pc-mx td.pc-mx-sp a:hover { text-decoration: underline; }
table.pc-mx td.pc-mx-tot, table.pc-mx th.pc-mx-tot { background: #f8fafc; font-weight: 700; color: #334155; }
table.pc-mx .pc-empty { color: #e2e8f0; }

<?php /* 窄屏：这张矩阵有 19 列（物种 + 17 个类别 + All），而 `table-layout:fixed` +
   `width:100%` 会让每列平分容器宽 —— 390px 屏上每列只剩 14px，单元格又是
   `overflow:hidden`，于是数字被从中间截掉（1,972 印成 "1,"）、表头挤成竖排的
   单个字母、物种列只剩 "Sar…"。桌面（>1200px）每列 70 多像素，正是设计时的
   样子，一行都不用改，所以下面这段只活在 ≤1200px，与全站 .gt-scroll 同一个断点。

   做法不是「给表格定宽让它溢出」（那会把页面撑破），而是包一层能横滑的壳、
   再给表一个下限宽度：物种列钉 210px（放得下 Sarcophyton trocheliophorum），
   其余 18 列各分到约 49px，三、四位数都印得全。样式与 js/rwd-tables.js 那套
   .gt-scroll 同源，只是这张表是 table-layout:fixed，不能照抄它的 max-content。 */ ?>
@media (max-width: 1200px) {
    .pc-mx-scroll {
        overflow-x: auto;
        /* 横滑到头时不要触发浏览器的「后退」手势 —— 全表横滑最常见的投诉。 */
        overscroll-behavior-x: contain;
    }
    /* 选择器要带 `.pc-mx-scroll >`(0,2,1)：上面 `table.pc-mx`(0,1,1) 的
       width:100% 本身压不过 min-width，但写成同一族选择器最不容易在以后
       被别的规则盖掉。 */
    .pc-mx-scroll > table.pc-mx {
        width: 100%;
        min-width: 1100px;
    }
    .pc-mx-scroll > table.pc-mx th.pc-mx-sp,
    .pc-mx-scroll > table.pc-mx td.pc-mx-sp {
        width: 210px;
    }
}

<?php /* ---- 堆叠条 ---- */ ?>
.pc-bar { display: flex; height: 20px; border-radius: 4px; overflow: hidden; background: #f1f5f9; }
.pc-bar span { display: block; height: 100%; }
.pc-bar span.pc-z { display: none; }
table.pc-pv { width: 100%; border-collapse: collapse; }
table.pc-pv td { padding: 5px 8px 5px 0; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
table.pc-pv td.pc-pv-l { width: 22%; font-size: 15px; color: #0f172a; }
table.pc-pv td.pc-pv-b { width: 58%; }
table.pc-pv td.pc-pv-n { width: 20%; font-size: 15px; color: #64748b; text-align: right;
                         font-variant-numeric: tabular-nums; white-space: nowrap; }
.pc-chip {
    display: inline-block; font-size: 12px; line-height: 1.6; padding: 1px 8px;
    border-radius: 999px; white-space: nowrap; border: 1px solid transparent; font-weight: 600;
}
a.pc-chip, span.pc-chip { text-decoration: none; }
a.pc-chip:hover { text-decoration: underline; }
.pc-legend { display: flex; flex-wrap: wrap; gap: 16px; margin: 0 0 14px 0; font-size: 15px; color: #475569; }

<?php /* ---- 直方图 ---- */ ?>
.pc-charts { display: flex; flex-wrap: wrap; gap: 18px; }
.pc-chart { width: calc(50% - 9px); min-width: 340px; border: 1px solid #eef2f7; border-radius: 8px; }
.pc-chart .pc-chart-h { padding: 10px 12px 0 12px; font-size: 15px; color: #0f172a; }
.pc-chart .pc-chart-h .pc-u { color: #64748b; font-weight: 400; }
<?php /* 图表下方的统计说明（「n = 3,537 · min 0 · median 580」）。是给人读的正文，不是图表
   内部的坐标轴文字（那些在 Highcharts 的 SVG 里，本轮不动）。2026-09-27 统一字号时它
   和角标同为 11.5px，被按值分档判进了 12px 下限档；2026-09-28 抬到 15px，与同页同类
   性质的 .pc-mapnote 对齐。 */ ?>
.pc-chart .pc-chart-m { padding: 0 12px; font-size: 15px; color: #64748b; }
.pc-chart .pc-plot { height: 240px; }

<?php /* ---- 地图 ---- */ ?>
#pc-map { height: 540px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; }
.pc-mapnote { font-size: 16px; color: #64748b; margin-top: 8px; line-height: 1.7; }
.pc-pop { font-size: 15px; line-height: 1.6; }
.pc-pop b { color: #0f172a; }

<?php /* ---- 来源清单 ---- */ ?>
table.pc-src { width: 100%; border-collapse: collapse; font-size: 15px; }
table.pc-src th {
    text-align: left; font-size: 13px;
    color: #64748b; font-weight: 700; padding: 0 10px 6px 0; border-bottom: 1px solid #e2e8f0;
}
table.pc-src th:nth-child(n+2) { text-align: right; }
table.pc-src td { padding: 7px 10px 7px 0; border-bottom: 1px solid #f1f5f9; color: #334155; }
table.pc-src td.pc-src-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
table.pc-src td.pc-src-n { color: #0f172a; }

<?php /* ---- 缺口清单 ---- */ ?>
.pc-gaps { margin: 0; padding-left: 20px; font-size: 15px; color: #334155; line-height: 1.75; }
.pc-gaps li { margin-bottom: 8px; }
.pc-num { font-variant-numeric: tabular-nums; font-weight: 700; color: #0f172a; }
.pc-sql { font-size: 15px; color: #b45309; }
.pc-note { border-radius: 8px; padding: 12px 16px; margin: 0 0 20px 0; font-size: 16px; line-height: 1.7; }
.pc-note-warn { background: #fffbeb; border: 1px solid #fde68a; color: #78350f; }
.pc-refs code, .pc-cap code { background: rgba(0,0,0,.05); padding: 1px 5px; border-radius: 3px; font-size: 15px; }
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
<div class="pc-wrap">

<p class="pc-crumb"><a class="pc-x" href="/phenotype.php?class=all">&larr; Phenotype table</a></p>

<div class="pc-intro">
    <h2>Phenotype data at a glance</h2>
    <p>Five views of the same records: <b>what has been described</b> for which species, <b>how each value was
    obtained</b>, <b>what the measurements look like</b> when they are numbers, <b>where</b> the records were
    made, and <b>what the data still cannot answer</b>. The underlying table holds <span class="pc-num"><?= number_format((int)$gs['tot']) ?></span> records
    (<span class="pc-num"><?= number_format((int)$gs['raw']) ?></span> source entries after folding identical
    ones) for <span class="pc-num"><?= number_format((int)$gs['species']) ?></span> species, integrated from
    three curated databases. Each row carries the single value its source recorded &mdash; not a range, not a set
    of replicates &mdash; and <span class="pc-num"><?= number_format((int)$gs['coded']) ?></span> rows still hold
    a source-database code rather than a reading. Entries that a source holds under names it treats as synonyms are
    counted once per name, so an entry can appear under two names in the totals below.</p>
    <p>Every chart below is computed from the table at page load, using the same provenance judgement as the
    <a class="pc-x" href="/phenotype.php?class=all">table view</a> and the
    <a class="pc-x" href="/phenotype_species.php?species=Corallium%20rubrum">per-species view</a>.</p>
</div>

<?php if ($__sqlerr !== ''): ?>
<div class="pc-note pc-note-warn">A query failed, so part of this page is missing: <span class="pc-sql"><?= pc_e($__sqlerr) ?></span></div>
<?php endif; ?>

<?php /* ================= 1. 覆盖矩阵 ================= */ ?>
<div class="pc-card">
    <h2>1. Which species have been described for which kinds of trait
        <span class="pc-q">&mdash; trait type coverage matrix, folded record counts</span></h2>
    <div class="pc-body">
        <?php if (!$mxTop): ?>
            <p>No data.</p>
        <?php else: ?>
        <div class="pc-mx-scroll">
        <table class="pc-mx" data-no-sort>
            <tr>
                <th class="pc-mx-sp" style="text-align:left;">Species</th>
                <?php foreach ($mxCols as $cat): ?>
                    <th title="<?= pc_e($cat) ?> — <?= number_format((int)$mxColTot[$cat]) ?> records in total">
                        <?= pc_e($cat) ?><br /><span style="font-weight:400;color:#94a3b8;"><?= number_format((int)$mxColTot[$cat]) ?></span>
                    </th>
                <?php endforeach; ?>
                <th class="pc-mx-tot">All</th>
            </tr>
            <?php foreach ($mxTop as $sp => $d): ?>
            <tr>
                <td class="pc-mx-sp" title="<?= pc_e($sp) ?>">
                    <a href="phenotype_species.php?species=<?= urlencode($sp) ?>"><i><?= pc_e($sp) ?></i></a>
                </td>
                <?php foreach ($mxCols as $cat):
                    $n = isset($d['cats'][$cat]) ? (int)$d['cats'][$cat] : 0;
                    list($bg, $fg) = pc_heat($n);
                    $ti = $sp . ' — ' . $cat . ': ' . number_format($n) . ' record' . ($n === 1 ? '' : 's');
                    if ($n > 0 && isset($d['catsr'][$cat])) { $ti .= ' (' . number_format((int)$d['catsr'][$cat]) . ' source entries)'; }
                ?>
                    <td<?= $bg !== '' ? ' style="background:' . $bg . ';color:' . $fg . '"' : '' ?>
                        <?= $n === 0 ? ' class="pc-empty"' : '' ?> title="<?= pc_e($ti) ?>"><?= $n === 0 ? '&middot;' : number_format($n) ?></td>
                <?php endforeach; ?>
                <td class="pc-mx-tot" title="<?= number_format((int)$d['n']) ?> records (<?= number_format((int)$d['sr']) ?> source entries)"><?= number_format((int)$d['n']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
        <p class="pc-cap">
            <b>Reading this.</b> The <?= count($mxTop) ?> species with the most records, out of
            <?= number_format(count($mxBySp)) ?> that have any record at all &mdash; and
            <?= number_format($spDist['lt10']) ?> of those have fewer than ten records each, which is why the
            remaining <?= number_format(count($mxBySp) - count($mxTop)) ?> are not shown: their rows would be one
            dot per trait type and would say nothing.
            Cell values are <b>folded records</b>, not source entries. Colour is banded, not linear &mdash; counts
            span three orders of magnitude, and a linear ramp would flatten every cell into the same pale green:
            <span class="pc-chip" style="background:#dcfce7;color:#166534">1&ndash;4</span>
            <span class="pc-chip" style="background:#bbf7d0;color:#14532d">5&ndash;19</span>
            <span class="pc-chip" style="background:#86efac;color:#14532d">20&ndash;99</span>
            <span class="pc-chip" style="background:#4ade80;color:#052e16">100&ndash;499</span>
            <span class="pc-chip" style="background:#22c55e;color:#f0fdf4">500+</span>
            <span class="pc-chip" style="background:#f8fafc;color:#94a3b8">&middot; none</span>.
            Trait types come from the source databases, with two exceptions, both of them ours.
            <b>Where a source records no type at all</b> &mdash; WoRMS files each trait by measurement type
            (<i>Life stage</i>, <i>Composition</i> &hellip;) and has no level above it &mdash; the record is placed in
            the closest type this site uses: skeleton and mineralogy traits under Biomechanical, depth and feeding
            traits under Ecological, and so on. That mapping is one table in the loader and is the only place the
            categories are decided. Spelling variants are also unified: Pelagic's lowercase <i>morphological</i> and
            OCTD's misspelling <i>Stoichometric</i> are folded into the spellings the other sources use. A dark
            column means that kind of trait is well recorded <b>for these species</b>, not across Cnidaria: the
            matrix is dominated by octocorals because that is where the records are. Click a species name for its
            full record list.
        </p>
        <?php endif; ?>
    </div>
</div>

<?php /* ================= 2. 来源属性 ================= */ ?>
<div class="pc-card">
    <h2>2. How each value was obtained <span class="pc-q">&mdash; measured, expert opinion, derived, inherited</span></h2>
    <div class="pc-body">
        <div class="pc-legend">
            <?php foreach ($PROVS as $p): $m = cnido_pheno_prov_meta($p); ?>
                <span><span class="pc-chip" style="background:<?= $m['bg'] ?>;color:<?= $m['fg'] ?>"><?= pc_e($m['label']) ?></span><?= pc_e($m['hint']) ?></span>
            <?php endforeach; ?>
            <span><span class="pc-chip" style="background:#f1f5f9;color:#64748b">not a value</span>no value: empty, or a source-database code in a row imported before 2026-09-27</span>
        </div>
        <?php
        /* 编号行单独成一档。判定走 cnido_pheno_coded_sql('source')（与另外两页同一个
           谓词），所以这一档的计数与页面上「One residue」那句永远一致。
           谓词里的来源那一段不能省：重建后 OCTD/WoRMS 的真读数（`Water depth 9 m`、
           `Colony height 29 cm`）会与字典里的编号撞键，只看 (单位,值) 会把它们从
           「实测」挪进这一档 —— 那就等于拿真读数当代码。 */
        $codedSql = cnido_pheno_coded_sql('source');
        $codedBy = array('cat' => array(), 'cls' => array(), 'n' => 0);
        if ($codedSql !== '') {
            $cq = pc_q($conn, "SELECT trait_category, Class, COUNT(*) n FROM phenotype
                               WHERE " . $codedSql . " OR TRIM(COALESCE(value,'')) = ''
                               GROUP BY trait_category, Class");
            foreach ($cq as $r) {
                $cat = cnido_pheno_category($r['trait_category']);
                if ($cat === '') { $cat = '(no category)'; }
                $cls = trim((string)$r['Class']); if ($cls === '') { $cls = '(no class)'; }
                $n = (int)$r['n'];
                $codedBy['cat'][$cat] = (isset($codedBy['cat'][$cat]) ? $codedBy['cat'][$cat] : 0) + $n;
                $codedBy['cls'][$cls] = (isset($codedBy['cls'][$cls]) ? $codedBy['cls'][$cls] : 0) + $n;
                $codedBy['n'] += $n;
            }
        }
        /* 把编号行从「实测」等档位里挪出来：一行 value 是编号、methodology 写着
           raw_value 的记录，如果留在 measured 里，就等于拿代码充当测量值。 */
        foreach (array('cat' => $codedBy['cat'], 'cls' => $codedBy['cls']) as $kind => $src) {
            $ref = ($kind === 'cat') ? $byCat : $byCls;
            foreach ($src as $key => $n) {
                if (!isset($ref[$key])) { continue; }
                /* 从最大的一档里扣（编号行的 methodology 都是空的 → 落在 measured），
                   扣不动就一分不扣：宁可少扣，也不要凭空造出负数。 */
                $big = 'measured'; $bign = -1;
                foreach ($PROVS as $p) { if ($ref[$key][$p] > $bign) { $bign = $ref[$key][$p]; $big = $p; } }
                $take = min($n, $ref[$key][$big]);
                $ref[$key][$big] -= $take;
                $ref[$key]['__nv'] = (isset($ref[$key]['__nv']) ? $ref[$key]['__nv'] : 0) + $take;
            }
            if ($kind === 'cat') { $byCat = $ref; } else { $byCls = $ref; }
        }
        /* 一行堆叠条：五档 + 编号档 */
        $pc_bar = function ($d, $total) use ($PROVS) {
            $out = '<div class="pc-bar">';
            foreach ($PROVS as $p) {
                $n = (int)$d[$p];
                if ($n <= 0) { continue; }
                $m = cnido_pheno_prov_meta($p);
                $pc = $total > 0 ? ($n * 100.0 / $total) : 0;
                $out .= '<span style="width:' . number_format($pc, 3, '.', '') . '%;background:' . $m['fg'] . '"'
                      . ' title="' . pc_e($m['label'] . ': ' . number_format($n) . ' records (' . number_format($pc, 1) . '%)') . '"></span>';
            }
            $nv = isset($d['__nv']) ? (int)$d['__nv'] : 0;
            if ($nv > 0) {
                $pc = $total > 0 ? ($nv * 100.0 / $total) : 0;
                $out .= '<span style="width:' . number_format($pc, 3, '.', '') . '%;background:#cbd5e1"'
                      . ' title="' . pc_e('not a value: ' . number_format($nv) . ' records') . '"></span>';
            }
            return $out . '</div>';
        };
        ?>
        <table class="pc-pv" data-no-sort>
            <?php foreach ($byCat as $cat => $d): $tot = (int)$d['__n']; ?>
            <tr>
                <td class="pc-pv-l"><?= pc_e($cat) ?></td>
                <td class="pc-pv-b"><?= $pc_bar($d, $tot) ?></td>
                <td class="pc-pv-n"><?= number_format($tot) ?> records</td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="pc-cap">
            <b>By trait type.</b> Bar length is the share of that type's records. Hover a segment for the count.
            The five grades &mdash; plus a sixth chip, <i>not a value</i>, for rows that carry no reading at all &mdash;
            are <b>our</b> reading of two fields the source databases fill in &mdash; the free-text
            <i>methodology</i> and the source's own <i>value type</i> (raw value, mean, mid-range, expert opinion,
            model output). The table view shows both the grade and the source's wording, so you can check any
            individual row. <b>Inherited</b> is the one to watch: those values describe a genus, family or class,
            and quoting them as measurements of the species on the row overstates what is known about it.
        </p>

        <table class="pc-pv" data-no-sort style="margin-top:22px;">
            <?php foreach ($byCls as $cls => $d): $tot = (int)$d['__n']; ?>
            <tr>
                <td class="pc-pv-l"><i><?= pc_e($cls) ?></i></td>
                <td class="pc-pv-b"><?= $pc_bar($d, $tot) ?></td>
                <td class="pc-pv-n"><?= number_format($tot) ?> records</td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="pc-cap">
            <b>By class.</b> The same records split taxonomically. This is the clearest picture of how uneven the
            data is: octocorals account for
            <?= number_format(isset($byCls['Octocorallia']) ? (int)$byCls['Octocorallia']['__n'] : 0) ?> of the
            <?= number_format((int)$gs['tot']) ?> records, so a cross-class comparison drawn from this table is
            usually a comparison of how much each class has been studied. Cubozoa and Hexacorallia appear with a
            handful of rows each &mdash; the honest reading is <b>under-recorded</b>, not traitless.
        </p>
    </div>
</div>

<?php /* ================= 3. 数值分布 ================= */ ?>
<div class="pc-card">
    <h2>3. What the measurements look like <span class="pc-q">&mdash; distributions of the countable traits</span></h2>
    <div class="pc-body">
        <?php if (!$numPick): ?>
            <p>No trait in the table has enough numeric values to plot a distribution.</p>
        <?php else: ?>
        <div class="pc-charts">
            <?php foreach ($numPick as $i => $c): ?>
            <div class="pc-chart">
                <div class="pc-chart-h">
                    <?= pc_e($c['trait_name']) ?>
                    <span class="pc-u">&mdash; <?= pc_e($c['traitunit'] === '' ? 'no unit' : $c['traitunit']) ?></span>
                </div>
                <div class="pc-chart-m">
                    n = <?= number_format((int)$c['n2']) ?> &middot;
                    min <?= pc_e(rtrim(rtrim(number_format((float)$c['mn'], 4, '.', ''), '0'), '.')) ?> &middot;
                    median <?= pc_e(rtrim(rtrim(number_format((float)$c['med'], 4, '.', ''), '0'), '.')) ?> &middot;
                    max <?= pc_e(rtrim(rtrim(number_format((float)$c['mx'], 4, '.', ''), '0'), '.')) ?>
                </div>
                <div class="pc-plot" id="pc-num-<?= (int)$i ?>"></div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="pc-cap">
            <b>Which traits are plotted, and why only these.</b> A trait is plotted when its values are entirely
            numeric, its unit is a real unit, it has at least <?= (int)$NUM_MIN_ROWS ?> numeric values and at least
            <?= (int)$NUM_MIN_DIST ?> distinct ones. The distinct-value condition is what keeps single-valued traits
            out &mdash; <i>Number of tentacles per polyp</i> has 3,540 numeric records but only one distinct value, and
            a histogram of a constant says nothing about the data.
            Traits are ranked by record count and the top <?= (int)$NUM_CHARTS ?> are shown
            <?= $numSkipped > 0 ? '(' . (int)$numSkipped . ' further qualifying trait' . ($numSkipped === 1 ? '' : 's') . ' not shown)' : '' ?>.
            Counts on the y-axis are <b>records</b>. Values carrying a source-database code are excluded, as are
            0/1 marker traits (<i>bin</i>) and free-text categories (<i>cat</i>). Units are printed exactly as the
            source wrote them, so <i>&#730;C</i> and <i>&deg;C</i> both appear across traits &mdash; they are the same
            unit, spelled differently by two databases.
        </p>
        <?php endif; ?>
    </div>
</div>

<?php /* ================= 4. 地图 ================= */ ?>
<div class="pc-card">
    <h2>4. Where the records were made <span class="pc-q">&mdash; georeferenced records only</span></h2>
    <div class="pc-body">
        <?php if (!$mapOut): ?>
            <p>No record in the table carries coordinates.</p>
        <?php else: ?>
        <div class="pc-legend">
            <?php foreach ($mapClsN as $cls => $n): ?>
                <span><span class="pc-chip" style="background:<?= pc_cls_color($cls) ?>;color:#fff"><?= pc_e($cls === '' ? 'unknown class' : $cls) ?></span><?= number_format((int)$n) ?> site<?= (int)$n === 1 ? '' : 's' ?></span>
            <?php endforeach; ?>
        </div>
        <div id="pc-map"></div>
        <p class="pc-mapnote">
            <b><?= number_format(count($mapOut)) ?></b> sites from
            <b><?= number_format($gapCoordRows) ?></b> records
            (<b><?= number_format($mapSrcN) ?></b> source entries) across
            <b><?= number_format(count($mapSpecies)) ?></b> species &mdash; that is
            <?= number_format($gapCoordRows * 100.0 / max(1, (int)$gs['tot']), 1) ?>% of the table. Records that name
            a region but carry no coordinates are <b>not</b> plotted: placing them at the centre of the region would
            invent a position. Marker colour is the class most recorded at that site, radius grows with the number
            of records, and clicking a marker lists the species recorded there.
        </p>
        <?php endif; ?>
    </div>
</div>

<?php /* ================= 5. 还缺什么 ================= */ ?>
<div class="pc-card">
    <h2>5. What this data still cannot answer <span class="pc-q">&mdash; the gaps, with the numbers behind them</span></h2>
    <div class="pc-body">
        <ul class="pc-gaps">
            <li><b>Position.</b> Only <span class="pc-num"><?= number_format($gapCoordRows) ?></span> of
                <span class="pc-num"><?= number_format((int)$gs['tot']) ?></span> records carry coordinates
                (<?= number_format($gapCoordRows * 100.0 / max(1, (int)$gs['tot']), 1) ?>%). For the rest we have a
                place name, which cannot be mapped, and often only a coarse one (&ldquo;Global estimate&rdquo;).
                Georeferencing the source records is the single change that would most improve this dataset.</li>
            <li><b>Time.</b> The collection year is not a property of a record &mdash; it is one trait among many:
                <span class="pc-num"><?= number_format($gapYearN) ?></span> records state a year
                (<?= number_format($gapYearSp) ?> species) and <span class="pc-num"><?= number_format($gapMonthN) ?></span>
                a month. So traits cannot be tracked through time, and seasonal comparisons are not possible.</li>
            <li><b>Uncertainty.</b> Every row holds a single number. Where the source measured several times, the
                count of replicates and the spread are absent from our table &mdash;
                <?php if (is_array($gap)): ?>
                    the source file records a replicate count for
                    <span class="pc-num"><?= number_format((int)$gap['replicates']) ?></span> rows and a precision
                    figure (<i>range</i>, <i>standard deviation</i>, <i>standard error</i>, <i>95% CI</i>) for
                    <span class="pc-num"><?= number_format((int)$gap['precision_upper']) ?></span> rows, and neither
                    column has been imported.
                <?php else: ?>
                    the source file has replicate-count and precision columns that have not been imported.
                <?php endif; ?>
                Without them, a value measured once and a mean of thirty measurements look identical here.</li>
            <li><b>Ranges.</b> The source databases store minima, maxima and mid-ranges as <i>separate rows</i>:
                <i>maximum</i> <?= number_format(isset($gapVt['maximum']) ? $gapVt['maximum'] : 0) ?>,
                <i>mid-range</i> <?= number_format(isset($gapVt['mid_range']) ? $gapVt['mid_range'] : 0) ?>,
                <i>mean</i> <?= number_format(isset($gapVt['mean']) ? $gapVt['mean'] : 0) ?>,
                <i>minimum</i> <?= number_format(isset($gapVt['minimum']) ? $gapVt['minimum'] : 0) ?>,
                <i>median</i> <?= number_format(isset($gapVt['median']) ? $gapVt['median'] : 0) ?> records. They can
                be paired per species and trait to recover real ranges, but that pairing has not been done, so a row
                on its own is a single number and not a range.</li>
            <li><b>Coverage.</b> <span class="pc-num"><?= number_format((int)$gs['species']) ?></span> species have at
                least one record, and <span class="pc-num"><?= number_format($spDist['lt10']) ?></span> of them
                (<?= number_format($spDist['lt10'] * 100.0 / max(1, $spDist['n']), 0) ?>%) have fewer than ten, so
                per-species trait profiles exist for very few animals.<?php /* 覆盖缓存坏掉时 $catSpecies 会是 0（连接失败就扫不出物种），
                那时「Of the 0 species in this catalogue」既是废话又像真数字 —— 宁可不印这句。 */ if ($catSpecies > 0 && $covPhenoSp > 0): ?>
                Of the <span class="pc-num"><?= number_format($catSpecies) ?></span> species in this catalogue,
                <span class="pc-num"><?= number_format($covPhenoSp) ?></span> are among them.<?php endif; ?>
                <?php if ($missingClasses): ?>
                    Taxonomically, <?= number_format(count($phClasses)) ?> of the
                    <?= number_format(count($catClasses)) ?> classes in the catalogue appear at all &mdash;
                    <?php $mc = array();
                    foreach ($missingClasses as $c => $n) { $mc[] = '<i>' . pc_e($c) . '</i> (' . $n . ' species)'; }
                    echo implode(' and ', $mc); ?> have no trait record whatever.
                <?php endif; ?>
                </li>
            <li><b>Codes.</b> <span class="pc-num"><?= number_format((int)$gs['coded']) ?></span> rows still hold a
                source-database code where a reading should be. They are marked <i>not a value</i> in the table and
                excluded from the charts above, but they remain a defect inherited from the earlier import.</li>
        </ul>
        <p class="pc-cap">
            <b>Where the numbers above come from.</b> Coordinates, year and code counts are counted from this table
            at page load; the replicate and precision counts come from
            <code>data/octd_unused_columns.json</code>, computed from the OCTD v2.2 release file
            <?= is_array($gap) && isset($gap['built']) ? 'on ' . pc_e(date('Y-m-d', (int)$gap['built'])) : 'by the build script' ?>
            (<?= is_array($gap) ? number_format((int)$gap['rows']) . ' source rows' : 'row count unavailable' ?>).
        </p>
    </div>
</div>

<?php /* ================= 6. 来源与署名 ================= */ ?>
<?php /* 三家源库都是 CC BY，署名回链是许可要求；而这一页把四家的数据摊在同一组图里，
       用户更需要知道每一家各占多少 —— phenotype.php 的页脚有同样的清单，但那是
       一个十几万行的表，看图的人不一定翻到那儿。 */ ?>
<div class="pc-card">
    <h2>Where these records come from <span class="pc-q">&mdash; the databases behind every chart above</span></h2>
    <div class="pc-body">
        <?php if ($gs['sources']): ?>
        <table class="pc-src">
            <tr>
                <th>Source database</th>
                <th>Records</th>
                <th>Source entries</th>
                <th>Species</th>
            </tr>
            <?php foreach ($gs['sources'] as $__s => $__d):
                $__m = cnido_pheno_source_meta($__s); ?>
            <tr>
                <td class="pc-src-n">
                    <?php if ($__m['url'] !== ''): ?>
                        <a class="pc-x" href="<?= pc_e($__m['url']) ?>" target="_blank" rel="noopener"><?= pc_e($__m['label']) ?></a>
                    <?php else: ?>
                        <?= pc_e($__m['label']) ?>
                    <?php endif; ?>
                </td>
                <td class="pc-src-num"><?= number_format((int)$__d['rows']) ?></td>
                <td class="pc-src-num"><?= number_format((int)$__d['raw']) ?></td>
                <td class="pc-src-num"><?= number_format((int)$__d['species']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="pc-cap">
            <b>Records</b> are the rows of the table above; <b>source entries</b> are what the database itself holds
            before identical entries are folded into one record. Each source database is cited by the records that
            came from it, and its own publications are listed on each species page under
            <i>Sources cited</i>. WoRMS / Marine Species Traits is published under
            <a class="pc-x" href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC&nbsp;BY&nbsp;4.0</a>
            and is mirrored here only for Cnidaria, with attribution and a link back to
            <a class="pc-x" href="https://www.marinespecies.org/" target="_blank" rel="noopener">marinespecies.org</a>.
            Rows whose source is not identified were imported before 2026-09-27 and are kept rather than dropped.
        </p>
        <?php else: ?>
        <p class="pc-cap">The per-source breakdown could not be read from the table on this request.</p>
        <?php endif; ?>
    </div>
</div>

</div>
</div>
</div>
</div>

<script src="/js/leaflet.js"></script>
<script src="/js/highcharts.js"></script>
<script type="text/javascript">
<?php if ($mapOut): ?>
<?php /* 地图数据。JSON 里已经 HTML 转义过物种名（pc_e），所以拼进弹窗的字符串是安全的：
   弹窗用 innerHTML 写，未转义的名字会破版甚至执行脚本。 */ ?>
var PC_POINTS = <?= $mapJson ?>;
var PC_MAXN = <?= (int)$mapMaxN ?>;
(function () {
    if (!document.getElementById('pc-map') || typeof L === 'undefined') { return; }
    var map = L.map('pc-map', { scrollWheelZoom: false }).setView([20, -40], 2);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 12,
        attribution: 'Base map &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    <?php /* 半径按面积而不是半径线性缩放：1370 条记录的点用线性半径会盖住半个大洋。 */ ?>
    var r = function (n) { return 3 + 14 * Math.sqrt(n / Math.max(1, PC_MAXN)); };
    var clsCol = { 'Octocorallia': '#0ea5e9', 'Hydrozoa': '#f59e0b', 'Scyphozoa': '#8b5cf6',
                   'Hexacorallia': '#ef4444', 'Cubozoa': '#10b981' };
    var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
    for (var i = 0; i < PC_POINTS.length; i++) {
        var p = PC_POINTS[i];
        var col = clsCol[p.cls] || '#64748b';
        var mk = L.circleMarker([p.la, p.lo], {
            radius: r(p.n), color: col, weight: 1, opacity: .9,
            fillColor: col, fillOpacity: .45
        }).addTo(map);
        var html = '<div class="pc-pop"><b>' + p.n + '</b> record' + (p.n === 1 ? '' : 's') +
                   ' (' + p.sr + ' source entries)<br />' +
                   '<b>' + p.sp + '</b> species at this site<br />' +
                   (p.cls ? esc(p.cls) + '<br />' : '');
        var names = [], first = null;
        for (var k in p.top) {
            if (!p.top.hasOwnProperty(k)) { continue; }
            if (first === null) { first = k; }
            names.push('<i>' + esc(k) + '</i> (' + p.top[k] + ')');
        }
        if (names.length) { html += 'Top species: ' + names.join(', '); }
        if (p.sp > names.length) { html += ' &hellip;'; }
        <?php /* 只给「记录最多的那个物种」一条深链。不给坐标搜索链接：列表页的 q 只搜
           trait/region/methodology 几列，拿坐标去搜永远零结果，那是骗点击。 */ ?>
        if (first !== null) {
            html += '<br /><a href="phenotype_species.php?species=' + encodeURIComponent(first) +
                    '">all records for ' + esc(first) + '</a>';
        }
        html += '</div>';
        mk.bindPopup(html, { maxWidth: 300 });
    }
})();
<?php endif; ?>

<?php if ($numPick): ?>
<?php /* 直方图。数据在服务端算好分箱（每箱的上下界与计数），JS 只负责画 ——
   浏览器端分箱会让「这一箱到底是 0.5–1.0 还是 0.4–0.9」取决于浮点写法。 */ ?>
<?php
/* 刻度文字的位数。
   分箱是等宽的，下界因此常是 266.6667 / 533.3333 这种循环小数 —— 原样印出来
   轴上有一半标签在重复同一串尾巴，旋转 60° 之后更是一团噪点。
   位数按**量级**定（≥100 整数、≥10 一位、≥1 两位、≥0.1 三位、更小四位），
   再去掉尾随的零：266.6667 → 267，4.2262 → 4.23，0.063 → 0.063。
   不能只按「互不相同」压到最小位数 —— 那样 1.5 会印成 2、0.063 会印成 0，
   把量级都抹掉了。同量级里万一撞车（同一张图两个刻度四舍五入到同一串），
   整张图再加一位小数，最多两次。
   tooltip 用同一串文字，免得悬停看到的数字和轴上的对不上。 */
$__tickFmt = function ($bins) {
    $vals = array_map(function ($b) { return (float)$b['lo']; }, $bins);
    $last = array();
    for ($extra = 0; $extra <= 2; $extra++) {
        $out = array();
        foreach ($vals as $v) {
            $a = abs($v);
            $d = $a >= 100 ? 0 : ($a >= 10 ? 1 : ($a >= 1 ? 2 : ($a >= 0.1 ? 3 : 4)));
            $s = number_format($v, $d + $extra, '.', '');
            if (strpos($s, '.') !== false) { $s = rtrim(rtrim($s, '0'), '.'); }
            $out[] = $s;
        }
        $last = $out;
        if (count(array_unique($out)) === count($out)) { break; }
    }
    return $last;
};
?>
(function () {
    if (typeof Highcharts === 'undefined') { return; }
    var series = <?= json_encode(array_map(function ($c) use ($__tickFmt) {
        return array('name' => $c['trait_name'] . ($c['traitunit'] === '' ? '' : ' (' . $c['traitunit'] . ')'),
                     'units' => $c['traitunit'],
                     'bins' => array_map(function ($b) { return round($b['lo'], 4); }, $c['bins']),
                     'labels' => $__tickFmt($c['bins']),
                     'counts' => array_map(function ($b) { return $b['n']; }, $c['bins']),
                     'med' => round((float)$c['med'], 4));
    }, $numPick), JSON_UNESCAPED_UNICODE) ?>;
    for (var i = 0; i < series.length; i++) {
        (function (s, idx) {
            var el = document.getElementById('pc-num-' + idx);
            if (!el) { return; }
            var cats = [];
            for (var b = 0; b < s.labels.length; b++) { cats.push(String(s.labels[b])); }
            Highcharts.chart(el, {
                chart: { type: 'column', height: 240, backgroundColor: 'transparent',
                         spacing: [8, 8, 4, 4] },
                title: { text: null },
                credits: { enabled: false },
                legend: { enabled: false },
                xAxis: { categories: cats, tickLength: 0,
                         <?php /* 18 个箱在半个内容宽里排不开：不 step 的话 Highcharts 会把
                            标签叠在一起变成一团灰。step:2 让它只印一半，箱数不变。 */ ?>
                         labels: { rotation: -60, step: 2, style: { fontSize: '9px', color: '#94a3b8' } },
                         title: { text: 'bin lower edge' + (s.units ? ' (' + s.units + ')' : ''),
                                  style: { fontSize: '10px', color: '#94a3b8' } } },
                yAxis: { min: 0, title: { text: 'records', style: { fontSize: '10px', color: '#94a3b8' } },
                         gridLineColor: '#f1f5f9',
                         labels: { style: { fontSize: '10px', color: '#94a3b8' } } },
                tooltip: { formatter: function () {
                    var lo = s.labels[this.point.index];
                    var hi = (this.point.index + 1 < s.labels.length) ? s.labels[this.point.index + 1] : null;
                    return '<b>' + this.y + '</b> records with value ' +
                           (hi === null ? '&ge; ' + lo : 'from ' + lo + ' to ' + hi);
                } },
                plotOptions: { column: { borderWidth: 0, color: '#0ea5e9', pointPadding: 0.02, groupPadding: 0.02 } },
                series: [{ data: s.counts, name: 'records' }]
            });
        })(series[i], i);
    }
})();
<?php endif; ?>
</script>

<?php
    include "Webpage_components.php";
    print $footer;
?>
</body>
</html>
