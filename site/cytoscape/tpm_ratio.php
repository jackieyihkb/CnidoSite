<?php
/* =====================================================================
 * tpm_ratio.php —— 用本站的 <ABBR>_TPM 矩阵算「表达比值」，喂给 Dynamic Expression View
 *
 * 为什么需要这一页
 * ----------------
 * Dynamic Expression View 的第 3 步要求输入每个基因的表达比值，但全站没有任何
 * 页面给得出这个数 —— 用户照手册走到这一步就断了（审稿意见：不知道该怎么做）。
 * 而 CnidoSite 其实存着 31 个物种的 RNA-seq TPM 矩阵（<ABBR>_TPM，每张是
 * 「Gene + 样本列」的宽表），并且恰好就是有共表达网络的那同一批 31 个物种。
 *
 * 这一页做什么
 * ------------
 * 用户挑两组样本列，服务端算每个基因的 log2((A 组均值 + 1) / (B 组均值 + 1))，
 * 然后连基因对一起提交给 network.expression.php —— 不用复制粘贴。
 * 伪计数 +1 是因为 TPM 里零值很常见（以 LPERT 某一列为准，39457 个基因里
 * 7940 个是 0，占两成），不加伪计数取对数会得到 ±inf。
 *
 * 为什么不自动识别「条件」
 * ------------------------
 * 样本列名的后缀规范并不统一，自动分组会在部分物种上给出错误的分组：
 *   - TSTEP 干净（SRR14511800_Mesentery … 6 组织 × 3 重复）；
 *   - LPERT 有 24 种后缀、词数 1–8（SRR23025708_Polyp /
 *     SRR11359494_polyp_Colony1_sampled_2weeks_at_pH7_9）；
 *   - ACOER 是 whole_organism_T_A1ES 这样的实验代号；
 *   - HVIRI / SSIDE 只有运行号、没有后缀；
 *   - ADIGI / AGEMM / MCAPI / ASELA / EVERR 只有一种条件（比值无意义）；
 *   - ATENU 的尾巴是数字坐标。
 * 而且 sample 表的元数据接不上：它存的是 SRX/ERX/DRX 实验号，TPM 列是
 * SRR/ERR/DRR 运行号，1385 个列 0 个匹配，没有 SRX↔SRR 的对照表。
 * 所以这一页把列名原样列出来、由用户分组，不自作主张。
 *
 * 样本列的识别规则：列名以运行号前缀（SRR/ERR/DRR/SRX/ERX/DRX/CRR）开头。
 * 这条规则在 31 张表 1385 个列上验证过，非样本列只剩聚合列 avg/std（多数表）
 * 与 ave/atd（少数表）；它们不参与计算，但会在页面上列出来，不做隐藏。
 *
 * 每个 <ABBR>_TPM 的第一行是脏表头（Gene='Gene'），查询时按 Gene <> 'Gene' 跳过；
 * 只有 HVIRI_TPM 没有这一行。
 * ===================================================================== */
require_once __DIR__ . '/../includes/state.php';

/* 深链可能给拉丁名（Lophelia pertusa）、下划线名或 abbr1 短码；本页与
   network_expression.php 一样按拉丁名传递（提交给结果页的 organism 用它）。 */
function tpm_species_info($conn, $in)
{
    $in = trim((string)$in);
    if ($in === '') { return null; }
    foreach (array($in, str_replace('_', ' ', $in)) as $cand) {
        $e = mysqli_real_escape_string($conn, $cand);
        $q = mysqli_query($conn, "SELECT abbr1, species FROM abbr
                                  WHERE species = '$e' OR abbr = '$e'
                                     OR UPPER(abbr1) = UPPER('$e') LIMIT 1");
        if ($q && ($r = mysqli_fetch_row($q))) { return array($r[0], $r[1]); }
    }
    return null;
}

/* 有 <ABBR>_TPM 矩阵的物种。顺带查清同物种有没有共表达网络 —— 两个模块的数据
   恰好重合（31/31），但这里照实查，不写死「一定有」。 */
function tpm_species_list($conn)
{
    /* 先把库里的表名一次取回（约 1500 行，实测 0.02s），再在 PHP 里配对。
       原来用两个关联 EXISTS 子查询逐个 abbr 去问 information_schema，实测 1.9s ——
       而且这是每次打开页面都要付的钱，不是只付一次。 */
    $tables = array();
    $q = mysqli_query($conn, "SELECT table_name FROM information_schema.tables
                               WHERE table_schema = DATABASE()");
    if ($q) { while ($r = mysqli_fetch_row($q)) { $tables[$r[0]] = true; } }

    $out = array();
    $q = mysqli_query($conn, "SELECT abbr1, species FROM abbr
                               WHERE species IS NOT NULL AND species <> ''
                               ORDER BY species");
    if ($q) {
        while ($r = mysqli_fetch_row($q)) {
            if (!isset($tables[$r[0] . '_TPM'])) { continue; }
            /* cx 是「这个物种有没有共表达网络」，用来在下拉框里标注
               (no co-expression network)。原来只测表在不在，而两个 Aurelia
               （AAURI1 / AAURI2）的两张 _coexpress_positive / _negative 表
               是空表 —— 表在、一行都没有，于是这两个物种被当成有网络，
               标注不出现，选进去图是空的。改成探一行真数据（用 LIMIT 1，
               不能用 COUNT(*)：PCLAV 那张表有 650 万行）。 */
            $cx = false;
            foreach (array('_coexpress_positive', '_coexpress_negative') as $__suf) {
                $__t = $r[0] . $__suf;
                if (!isset($tables[$__t])) { continue; }
                $__p = @mysqli_query($conn, "SELECT 1 FROM `$__t` LIMIT 1");
                if ($__p && mysqli_fetch_row($__p)) { $cx = true; break; }
            }
            $out[$r[1]] = array('abbr1' => $r[0], 'cx' => $cx);
        }
    }
    return $out;
}

/* 一张 TPM 表的列：样本列（以运行号开头）与其余列（聚合列）。 */
function tpm_columns($conn, $abbr1)
{
    $samples = array(); $other = array();
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `" . $abbr1 . "_TPM`");
    if (!$q) { return array($samples, $other); }
    while ($r = mysqli_fetch_row($q)) {
        $col = $r[0];
        if ($col === 'Gene') { continue; }
        if (preg_match('/^(SRR|ERR|DRR|SRX|ERX|DRX|CRR)/i', $col)) { $samples[] = $col; }
        else { $other[] = $col; }
    }
    return array($samples, $other);
}

/* 首次进入时默认展示哪个物种。不按字母序取第一个 —— 那会落到 Acropora digitifera，
   它有 42 个样本列，但后缀全是 Coral_branch_adult，只有一种条件；用户打开看到的
   是一屏同名列表，反而更不知道该做什么，正是审稿意见说的那种情形。
   改成挑一个「分组天然清楚」的当示例：剥掉列名开头的运行号取后缀，数后缀里有几个
   是 ≥2 个重复的组；这样的组越多越适合当示例（TSTEP 是 6 组织 × 3 重复）。
   一次 information_schema 查询取回 31 张表的全部列，不逐表 SHOW COLUMNS。 */
function tpm_demo_species($conn, $speciesList)
{
    if (!$speciesList) { return ''; }
    /* 只取这 31 张表，不用 LIKE '%_TPM'（前者能走索引，后者扫全表） */
    $names = array();
    foreach ($speciesList as $meta) { $names[] = "'" . $meta['abbr1'] . "_TPM'"; }
    $cols = array();
    $q = mysqli_query($conn,
        "SELECT table_name, column_name FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name IN (" . implode(',', $names) . ")
            AND column_name <> 'Gene'
          ORDER BY table_name, ordinal_position");
    if ($q) {
        while ($r = mysqli_fetch_row($q)) {
            $t = substr($r[0], 0, -4);                       // 去掉 '_TPM'
            if (preg_match('/^(SRR|ERR|DRR|SRX|ERX|DRX|CRR)/i', $r[1])) {
                $cols[$t][] = preg_replace('/^(SRR|ERR|DRR|SRX|ERX|DRX|CRR)\d+_?/i', '', $r[1]);
            }
        }
    }
    /* 分两轮：先只看列名短到能在页面上读全的（≤24 字符，否则区分各组的尾巴
       会被截掉 —— LPERT 的 pH7_9 / pH7_6 就在末尾），一轮没有合适的再放宽。 */
    foreach (array(24, PHP_INT_MAX) as $lenCap) {
        $best = ''; $bestScore = -1; $bestLen = 0;
        foreach ($speciesList as $nm => $meta) {
            if (!$meta['cx']) { continue; }      // 没有共表达网络的物种当示例没意义
            if (empty($cols[$meta['abbr1']])) { continue; }
            $grp = array();
            foreach ($cols[$meta['abbr1']] as $suf) {
                if ($suf === '') { continue; }   // 裸运行号不构成条件名
                $grp[$suf] = (isset($grp[$suf]) ? $grp[$suf] : 0) + 1;
            }
            $nRep = 0; $maxLen = 0;
            foreach ($grp as $suf => $n) {
                if ($n >= 2) { $nRep++; }
                if (strlen($suf) > $maxLen) { $maxLen = strlen($suf); }
            }
            if ($nRep < 2 || $maxLen > $lenCap) { continue; }   // 至少要两组可比的重复
            /* 组多者优先；一样多时名字短的优先 */
            if ($nRep > $bestScore || ($nRep === $bestScore && $maxLen < $bestLen)) {
                $best = $nm; $bestScore = $nRep; $bestLen = $maxLen;
            }
        }
        if ($best !== '') { return $best; }
    }
    return '';
}

/* 基因列表的每一行只取第一列：用户可能粘的是「GeneA 1.5」这种带数值的行
   （结果页的 genelist 格式），这里只要基因名。 */
function tpm_gene_list($raw)
{
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', (string)$raw) as $line) {
        $line = trim($line);
        if ($line === '') { continue; }
        $parts = preg_split('/[\s,;]+/', $line);
        if ($parts[0] !== '') { $out[$parts[0]] = true; }
    }
    return array_keys($out);
}

/* 基因对列表专用（networklist）：每行是 "基因A 基因B"，两列都是基因名，都要收。
   tpm_gene_list() 只取每行第一个 token —— 那是给结果页的 "基因 数值" 格式设计的。
   拿它解析基因对只会收到每对的前一个基因，而返回值会变成 SQL 的 Gene IN (…)
   过滤条件，于是每对里第二个基因查不出来，在动态视图里没有比值、没有颜色，
   页面不报任何错。数值 token 排除掉，这样 "基因A 1.5" 这种行也不会把 1.5 当基因。 */
function tpm_pair_genes($raw)
{
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', (string)$raw) as $line) {
        $line = trim($line);
        if ($line === '') { continue; }
        foreach (preg_split('/[\s,;]+/', $line) as $t) {
            if ($t === '' || is_numeric($t)) { continue; }
            $out[$t] = true;
        }
    }
    return array_keys($out);
}

$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { die("database connection failed"); }

$__st = cnido_state('tpmratio', array(
    'specie' => array('get' => 'species', 'post' => 'organism', 'default' => ''),
));
$specie = $__st['specie'];

/* 从结果页带过来的基因对，原样透传给 network.expression.php —— 本页不解析它，
   只负责把它送到终点。 */
$tpmPairs   = isset($_POST['networklist']) ? (string)$_POST['networklist']
            : (isset($_GET['networklist'])  ? (string)$_GET['networklist'] : '');
/* 用户在本页 textarea 里给的基因/比值串 */
$tpmGenesIn = isset($_POST['genelist'])    ? (string)$_POST['genelist'] : '';

/* 阈值：与 Dynamic Expression View 表单的默认值一致，在这里定好直接出图 */
$thr = array('red_low' => '1', 'red_high' => '100', 'blue_low' => '-10', 'blue_high' => '-1');
foreach ($thr as $k => $v) {
    if (isset($_POST[$k]) && trim((string)$_POST[$k]) !== '') { $thr[$k] = trim((string)$_POST[$k]); }
}

$speciesList = tpm_species_list($conn);
$notice      = '';
$info        = null;
$samples     = array();
$otherCols   = array();

if ($specie !== '') {
    $info = tpm_species_info($conn, $specie);
    if ($info === null || !isset($speciesList[$info[1]])) {
        $notice = 'No TPM expression matrix is available for <b>' . htmlspecialchars($specie)
                . '</b> in CnidoSite, so no expression ratio can be computed here. Matrices exist for '
                . count($speciesList) . ' species — pick one below.';
        $info = null; $specie = '';
    }
} else {
    /* 没指定物种：挑一个分组最清楚的当示例 */
    $demo = tpm_demo_species($conn, $speciesList);
    if ($demo === '') {
        foreach ($speciesList as $nm => $meta) { if ($meta['cx']) { $demo = $nm; break; } }
    }
    if ($demo === '') { foreach ($speciesList as $nm => $meta) { $demo = $nm; break; } }
    if ($demo !== '') { $specie = $demo; $info = array($speciesList[$demo]['abbr1'], $demo); }
}
if ($info !== null) {
    list($samples, $otherCols) = tpm_columns($conn, $info[0]);
    if (!$samples) {
        $notice = 'The expression matrix for <b>' . htmlspecialchars($info[1])
                . '</b> has no sample columns, so no ratio can be computed.';
    }
}

/* 要算哪些基因：本页 textarea 优先，否则用结果页带来的基因对里的基因名。
   两个来源的格式不同，解析函数也不同：本页 textarea（genelist）是「基因 数值」，
   结果页带来的（networklist）是「基因A 基因B」，两列都要。 */
$genesWanted = tpm_gene_list($tpmGenesIn);
if (!$genesWanted) { $genesWanted = tpm_pair_genes($tpmPairs); }

/* ---- 计算 -------------------------------------------------------------- */
$groupA = (isset($_POST['grpA']) && is_array($_POST['grpA'])) ? $_POST['grpA'] : array();
$groupB = (isset($_POST['grpB']) && is_array($_POST['grpB'])) ? $_POST['grpB'] : array();
$computed = false; $rows = array(); $err = ''; $stats = array();
$geneCap = 500;
$A = array(); $B = array();   // 结果区要报「每组几列」，先给出默认值

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'compute') {
    /* 列名是用户输入，必须对着真实列单核对后才允许拼进 SQL —— 这是本页唯一把
       用户字符串放进标识符位置的地方，核对就是这里的注入防线。 */
    $valid = array_flip($samples);
    foreach ($groupA as $c) { if (isset($valid[$c])) { $A[$c] = true; } }
    foreach ($groupB as $c) { if (isset($valid[$c])) { $B[$c] = true; } }
    /* A 优先：同一列被两组都勾了，只按 A 算，并且要告诉用户 */
    $both = array_intersect_key($A, $B);
    foreach (array_keys($both) as $c) { unset($B[$c]); }

    if (!$A)      { $err = 'Tick at least one sample column in column <b>A</b>.'; }
    elseif (!$B)  { $err = 'Tick at least one sample column in column <b>B</b>.'; }
    elseif ($info === null) { $err = 'No expression matrix is selected.'; }
    else {
        $aCols = array_keys($A); $bCols = array_keys($B);
        $sel   = array_merge($aCols, $bCols);
        $quoted = array();
        foreach ($sel as $c) { $quoted[] = '`' . $c . '`'; }

        $sql = "SELECT Gene," . implode(',', $quoted) . " FROM `" . $info[0] . "_TPM`"
             . " WHERE Gene IS NOT NULL AND Gene <> '' AND Gene <> 'Gene'";
        if ($genesWanted) {
            /* 基因名来自用户输入 / 结果页，走转义 */
            $esc = array();
            foreach (array_slice($genesWanted, 0, $geneCap) as $g) {
                $esc[] = "'" . mysqli_real_escape_string($conn, $g) . "'";
            }
            $sql .= " AND Gene IN (" . implode(',', $esc) . ")";
        }
        $q = mysqli_query($conn, $sql);
        if (!$q) { $err = 'Query failed: ' . htmlspecialchars(mysqli_error($conn)); }
        else {
            $idxA = array(); $idxB = array();
            foreach ($aCols as $i => $c) { $idxA[] = $i + 1; }
            foreach ($bCols as $i => $c) { $idxB[] = count($aCols) + $i + 1; }
            $up = 0; $down = 0; $flat = 0;
            while ($r = mysqli_fetch_row($q)) {
                $sa = 0.0; foreach ($idxA as $i) { $sa += (float)$r[$i]; }
                $sb = 0.0; foreach ($idxB as $i) { $sb += (float)$r[$i]; }
                $ma = $sa / count($idxA);
                $mb = $sb / count($idxB);
                /* 不要写 log2()：本机的 PHP 构建没有这个函数（function_exists('log2')
                   为假，调用直接 Fatal error），log() 与 M_LN2 都有。 */
                $l2 = log(($ma + 1.0) / ($mb + 1.0)) / M_LN2;
                if ($l2 >= (float)$thr['red_low'])       { $up++; }
                elseif ($l2 <= (float)$thr['blue_high']) { $down++; }
                else                                     { $flat++; }
                $rows[] = array($r[0], $ma, $mb, $l2);
            }
            $computed = true;
            $stats = array('up' => $up, 'flat' => $flat, 'down' => $down,
                           'both' => count($both));
            /* 没给基因列表 = 全表扫描，行数可能上万 —— 按 |log2FC| 从大到小截断，
               免得把整个矩阵塞进页面；给了基因列表就不截断。 */
            if (!$genesWanted && count($rows) > 300) {
                usort($rows, function ($x, $y) { return abs($y[3]) <=> abs($x[3]); });
                $rows = array_slice($rows, 0, 300);
            }
        }
    }
}

/* 交给结果页的 genelist：gene<TAB>log2FC */
$handoff = '';
if ($computed) {
    foreach ($rows as $r) { $handoff .= $r[0] . "\t" . number_format($r[3], 3, '.', '') . "\n"; }
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Expression Ratio from TPM - CnidoSite</title>
<meta name="keywords" content="Cnidaria, TPM, expression ratio, log2 fold change, co-expression network" />
<meta name="description" content="Compute per-gene expression ratios from the CnidoSite RNA-seq TPM matrices, for the Dynamic Expression View" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="../js/func.js" type="text/javascript"></script>
<style>
<?php /* 表单类样式与 network_expression.php 保持一致（该页样式块是页面私有的，
   全局 CSS 里没有 .form-label / .form-select / .form-textarea） */ ?>
.network-form-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 30px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
}

.section-title {
    font-size: 22px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-label {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 10px;
}

.form-select {
    width: 100%;
    max-width: 400px;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 16px;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 20px;
}

.form-select:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.form-textarea {
    width: 100%;
    max-width: 660px;
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 15px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    background: #f8fafc;
    color: #1e293b;
    resize: vertical;
    transition: all 0.3s ease;
    min-height: 120px;
}

.form-textarea:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    background: white;
}

<?php /* ---- 本页专用，前缀 tr- ---- */ ?>
.tr-cols{max-height:360px;overflow:auto;border:1px solid #e2e8f0;border-radius:10px;max-width:820px}
.tr-cols table{width:100%;border-collapse:collapse}
.tr-cols th{position:sticky;top:0;background:#f8fafc;text-align:left;padding:9px 12px;
  border-bottom:1px solid #e2e8f0;font-size:14px;font-weight:600;color:#475569;z-index:1}
.tr-cols td{padding:5px 12px;border-bottom:1px solid #f1f5f9;font-size:12.5px;color:#334155;
  font-family:'Monaco','Menlo','Ubuntu Mono',monospace;max-width:700px;word-break:break-all}
.tr-cols td.pick{width:44px;text-align:center}
.tr-cols tr:hover td{background:#f8fafc}
<?php /* 运行号退到灰色小字，条件名用正常字号与墨色 —— 折行而不是省略号，
   因为区分各组的词常在末尾（LPERT 的 pH7_9 / pH7_6） */ ?>
.tr-name{line-height:1.6}
.tr-rid{color:#64748b;font-size:11.5px;margin-right:6px}
.tr-cond{color:#1e293b}
.tr-filter{width:100%;max-width:520px;padding:11px 15px;border:2px solid #e2e8f0;border-radius:10px;
  font-size:14px;color:#1e293b;margin-bottom:14px;background:#f8fafc}
.tr-filter:focus{outline:none;border-color:#047857;background:#fff}
<?php /* 数值本身穿墨色，身份由旁边的小色块承担（绿 #86B342 当文字太浅，读不动） */ ?>
.tr-dot{display:inline-block;width:8px;height:8px;border-radius:2px;margin-right:7px;
  vertical-align:middle}
.tr-agg{font-size:16px;color:#64748b;margin-top:10px}
.tr-agg code{background:#f1f5f9;border-radius:4px;padding:1px 6px;font-size:12.5px}
.tr-summary{display:flex;gap:2px;height:14px;margin:14px 0 10px;max-width:560px}
.tr-summary span{flex-basis:0;min-width:2px}
.tr-summary span:first-child{border-radius:7px 0 0 7px}
.tr-summary span:last-child{border-radius:0 7px 7px 0}
.tr-summary span:only-child{border-radius:7px}
.tr-key{display:flex;flex-wrap:wrap;gap:6px 22px;font-size:14px;color:#475569;margin-bottom:14px}
.tr-key span{display:inline-flex;align-items:center;gap:7px}
.tr-key i{width:11px;height:11px;border-radius:3px;flex:0 0 auto}
.tr-key b{color:#1e293b}
.tr-tbl{width:100%;border-collapse:collapse;font-size:13px}
.tr-tbl th{background:#f8fafc;text-align:left;padding:9px 12px;border-bottom:1px solid #e2e8f0;
  color:#475569;font-weight:600;position:sticky;top:0}
.tr-tbl td{padding:6px 12px;border-bottom:1px solid #f1f5f9;
  font-family:'Monaco','Menlo','Ubuntu Mono',monospace;font-size:12.5px;color:#334155}
.tr-num{text-align:right}
.tr-run{padding:13px 28px;border:none;border-radius:10px;background:linear-gradient(135deg,#047857,#065f46);
  color:#fff;font-size:16px;font-weight:600;cursor:pointer}
.tr-run:hover{background:linear-gradient(135deg,#065f46,#064e3b)}
.tr-go{padding:13px 28px;border:none;border-radius:10px;background:#2563eb;color:#fff;
  font-size:16px;font-weight:600;cursor:pointer;text-decoration:none}
.tr-go:hover{background:#1d4ed8}
.tr-hint{font-size:16px;color:#64748b;line-height:1.75}
.tr-hint code{background:#f1f5f9;border-radius:4px;padding:1px 6px;font-size:12.5px}
.tr-thr input{width:66px;padding:7px 9px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;
  text-align:center}
.tr-thr input:focus{outline:none;border-color:#047857}

@media (max-width: 768px) {
    .form-select, .form-textarea, .tr-cols { max-width: 100%; }
}
</style>
</head>

<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<?php /*导航栏保持不变*/ ?>
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
                    
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li>                    
<li><a href="/microsynteny.php">Microsynteny Analysis</a></li>
<li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li>
<li><a href="/mitdata.php">Mitogenomic Data</a></li>
                </ul>
            <li><a href="#" class="current">Transcriptome</a>
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Expression Ratio from TPM</b></legend>
<p class="paleo-intro">Computes the per-gene expression ratio that <b>Dynamic Expression View</b> asks for in its step&nbsp;3, from the RNA-seq matrix CnidoSite already holds for this species. Tick the samples for each side and every gene gets <b>log<sub>2</sub>&nbsp;((mean of group A + 1) / (mean of group B + 1))</b>. <b>Which samples form a condition is your call, not ours</b> &mdash; run accessions were deposited in many different naming styles, so guessing the grouping would be wrong for some species. Worked example: <a href="/tutorial.php#dynamic-expression">manual, section&nbsp;4.4</a>.</p>

<?php if ($notice !== ''): ?>
<div class="gd-notice gd-warn" style="margin:18px 0"><?= $notice ?></div>
<?php endif; ?>

<?php
/* 从 Network Analysis 结果页一路带过来的基因对。它此前只存在于两个 hidden 域里，
   用户在页面上完全看不到自己带了什么过来 —— 只知道「从网络页点了个按钮」，然后
   被要求分组、算比值，却不知道在给哪些基因算。这里把带过来的东西显式列出来。
   $tpmPairs 是 "基因A 基因B" 每行一对；$genesWanted 是其中解析出的不重复基因名。 */
$tpmPairN = 0;
foreach (preg_split('/\r\n|\r|\n/', $tpmPairs) as $__l) { if (trim($__l) !== '') { $tpmPairN++; } }
?>
<?php if ($tpmPairN > 0): ?>
<div class="gd-notice gd-info" style="margin:18px 0">
    Carried over from your network: <b><?= $tpmPairN ?></b> gene pair<?= $tpmPairN === 1 ? '' : 's' ?>
    (<?= count($genesWanted) ?> distinct gene<?= count($genesWanted) === 1 ? '' : 's' ?>).
    These pairs are kept through every step below and handed to Dynamic Expression View together with the
    ratios you compute here, so you do not have to retype them.
    <details style="margin-top:10px">
        <summary style="cursor:pointer">Show the pairs</summary>
        <textarea readonly onclick="this.select()"
                  style="width:100%;min-height:110px;margin-top:8px;padding:10px;border:1px solid #bae6fd;border-radius:8px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px"><?= htmlspecialchars($tpmPairs) ?></textarea>
    </details>
</div>
<?php endif; ?>

<?php /* 1. 物种（独立表单：换物种时要把 textarea 里已输入的内容一起带走） */ ?>
<div class="network-form-container">
    <div class="section-title">1. Species</div>
    <form method="post" action="tpm_ratio.php" id="trSpeciesForm">
        <label class="form-label" for="organism">Species with an expression matrix</label>
        <select name="organism" id="organism" class="form-select" onchange="trSwitch()">
            <?php foreach ($speciesList as $nm => $meta):
                $lbl = $nm . ($meta['cx'] ? '' : '   (no co-expression network)'); ?>
                <option value="<?= htmlspecialchars($nm) ?>"<?= ($info !== null && $info[1] === $nm) ? ' selected="selected"' : '' ?>><?= htmlspecialchars($lbl) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="networklist" value="<?= htmlspecialchars($tpmPairs) ?>">
        <input type="hidden" name="genelist" id="trGeneHold" value="<?= htmlspecialchars($tpmGenesIn) ?>">
    </form>
    <p class="tr-hint" style="margin:14px 0 0">
        <?= count($speciesList) ?> species have a TPM matrix in CnidoSite; only those can be used here.
        <?php if ($info !== null): ?>
        Currently showing <b><?= htmlspecialchars($info[1]) ?></b> (<code><?= htmlspecialchars($info[0]) ?>_TPM</code>).
        <?php endif; ?>
    </p>
</div>

<?php if ($info !== null && $samples): ?>
<form method="post" action="tpm_ratio.php" id="trComputeForm">
<input type="hidden" name="do" value="compute">
<input type="hidden" name="organism" value="<?= htmlspecialchars($info[1]) ?>">
<input type="hidden" name="networklist" value="<?= htmlspecialchars($tpmPairs) ?>">
<input type="hidden" name="red_low"   value="<?= htmlspecialchars($thr['red_low']) ?>">
<input type="hidden" name="red_high"  value="<?= htmlspecialchars($thr['red_high']) ?>">
<input type="hidden" name="blue_low"  value="<?= htmlspecialchars($thr['blue_low']) ?>">
<input type="hidden" name="blue_high" value="<?= htmlspecialchars($thr['blue_high']) ?>">

<?php /* 2. 分组 */ ?>
<div class="network-form-container">
    <div class="section-title">2. Group the samples</div>
    <p class="tr-hint" style="margin:0 0 16px">
        <code><?= htmlspecialchars($info[0]) ?>_TPM</code> has <b><?= count($samples) ?></b> sample columns.
        Tick <b>A</b> and <b>B</b> to put them on the two sides of one comparison &mdash; typically
        A&nbsp;=&nbsp;treated / stage of interest and B&nbsp;=&nbsp;control. The ratio is <b>A relative to B</b>,
        so a <b>positive</b> value means the gene is higher in group&nbsp;A.<br>
        Ticking all replicates of a condition is what makes the number meaningful: one column per side
        gives a single-sample comparison with no replication, and the network will still draw, but the
        ratio is then one run against one run.
    </p>

    <input type="text" id="trFilter" class="tr-filter"
           placeholder="Filter the sample list — type part of a name, e.g. polyp, pH7_9, Tentacles"
           oninput="trFilter(this.value)">

    <div class="tr-cols">
        <table>
            <thead><tr><th class="pick">A</th><th class="pick">B</th><th>Sample column</th></tr></thead>
            <tbody>
            <?php foreach ($samples as $c):
                /* 拆成「运行号 + 条件名」两段显示：条件名才是分组依据，字号给足；
                   运行号退到灰色小字。这样长名字也不必截断 —— 关键在于区分各组的那几个
                   词往往在末尾（LPERT 的 pH7_9 / pH7_6），截断正好把要点切掉。 */
                $rid = ''; $cond = $c;
                if (preg_match('/^((?:SRR|ERR|DRR|SRX|ERX|DRX|CRR)\d+)(?:_(.*))?$/i', $c, $m)) {
                    $rid = $m[1]; $cond = isset($m[2]) ? $m[2] : '';
                } ?>
                <tr><td class="pick"><input type="checkbox" name="grpA[]" value="<?= htmlspecialchars($c) ?>"
                        <?= in_array($c, $groupA, true) ? 'checked' : '' ?>></td>
                    <td class="pick"><input type="checkbox" name="grpB[]" value="<?= htmlspecialchars($c) ?>"
                        <?= in_array($c, $groupB, true) ? 'checked' : '' ?>></td>
                    <td class="tr-name" title="<?= htmlspecialchars($c) ?>"><span class="tr-rid"><?= htmlspecialchars($rid) ?></span><span class="tr-cond"><?= htmlspecialchars($cond !== '' ? $cond : $c) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="tr-hint" id="trCount" style="margin:12px 0 0"></p>
    <?php if ($otherCols): ?>
    <p class="tr-agg">
        Not selectable &mdash; these are pre-computed summary columns, not samples:
        <?php $o = array(); foreach ($otherCols as $c) { $o[] = '<code>' . htmlspecialchars($c) . '</code>'; } echo implode(' ', $o); ?>
    </p>
    <?php endif; ?>

    <div style="margin-top:26px">
        <label class="form-label" for="trGenes">Which genes?
            <span style="font-weight:400;color:#64748b">(optional)</span></label>
        <textarea name="genelist" id="trGenes" class="form-textarea"
                  placeholder="Leave empty to compute every gene in the matrix and show the 300 largest changes. Or paste a gene list, one gene per line — the gene list from your network result works as-is."><?= htmlspecialchars($tpmGenesIn) ?></textarea>
        <?php if ($genesWanted): ?>
        <p class="tr-hint" style="margin:10px 0 0">
            <?= count($genesWanted) ?> gene IDs read from the text above
            <?php if ($tpmGenesIn === '' && $tpmPairs !== ''): ?>
            &mdash; actually from the gene pairs handed over by your network result
            <?php endif; ?>.
            <?php if (count($genesWanted) > $geneCap): ?>
            <br><b style="color:#92400e">Only the first <?= $geneCap ?> are used</b>; the query is capped there.
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </div>

    <div style="margin-top:26px">
        <label class="form-label">Colour thresholds</label>
        <p class="tr-hint" style="margin:0 0 12px">
            These are the same four thresholds as in Dynamic Expression View. A gene is drawn
            <span style="color:#c81e1e;font-weight:600">red</span> if its ratio falls in the red band and
            <span style="color:#1d4ed8;font-weight:600">blue</span> if it falls in the blue band; everything else is green.
            Both bands are read as log<sub>2</sub> values, so the defaults mean
            &ldquo;at least 2-fold up&rdquo; and &ldquo;at least 2-fold down&rdquo;.
        </p>
        <div class="tr-thr">
            <input name="red_low"  value="<?= htmlspecialchars($thr['red_low']) ?>"> &le;
            <font color="red">Red</font> &le;
            <input name="red_high" value="<?= htmlspecialchars($thr['red_high']) ?>">
            &nbsp;&nbsp;&nbsp;
            <input name="blue_low"  value="<?= htmlspecialchars($thr['blue_low']) ?>"> &le;
            <font color="blue">Blue</font> &le;
            <input name="blue_high" value="<?= htmlspecialchars($thr['blue_high']) ?>">
        </div>
    </div>

    <?php if ($err !== ''): ?>
    <div class="gd-notice gd-warn" style="margin:22px 0 0"><?= $err ?></div>
    <?php endif; ?>

    <div style="margin-top:26px">
        <button type="submit" class="tr-run">Compute expression ratios</button>
    </div>
</div>
</form>
<?php endif; ?>

<?php if ($computed): ?>
<?php
    $nUp = $stats['up']; $nFlat = $stats['flat']; $nDown = $stats['down'];
    $nTot = max(1, $nUp + $nFlat + $nDown);
    /* 极窄的分段给 2px 下限，否则放不满 1px 会整段消失；精确值由图例给出 */
    $pc = function ($n) use ($nTot) { return $n > 0 ? max(2.0, $n / $nTot * 100) : 0; };
    $uf = $pc($nUp); $ff = $pc($nFlat); $df = $pc($nDown);

    /* 「要了哪些基因」和「矩阵里找到哪些基因」是两回事：上面的 SQL 只对前
       $geneCap 个基因做 Gene IN (…) 过滤，矩阵里没有的基因直接不出行，$rows
       因此只装了真正查到的。原来这里在 $genesWanted 非空时无条件印
       "Every requested gene was found."，只要有一个基因查不到就是假话。
       $nQueried 取 min(…, $geneCap) —— 超过上限的基因根本没进 SQL，算进
       「请求数」会把没查的基因说成没查到。 */
    $nQueried = min(count($genesWanted), $geneCap);
    $nFound   = count($rows);
    $nMissing = $nQueried - $nFound;
?>
<?php /* 3. 结果 */ ?>
<div class="network-form-container">
    <div class="section-title">3. Result</div>

    <?php if (!$rows): ?>
    <div class="gd-notice gd-warn" style="margin:0">
        No gene came back from <code><?= htmlspecialchars($info[0]) ?>_TPM</code>.
        <?php if ($genesWanted): ?>
        <?php if ($nQueried === 1): ?>The gene ID given above does not exist in this species&rsquo; matrix.<?php else: ?>
        None of the <b><?= $nQueried ?></b> gene IDs given above exists in this species&rsquo; matrix.<?php endif; ?>
        Gene IDs are species-specific, so this is what happens if the list came from another species &mdash;
        clear the gene list to compute the whole matrix instead.
        <?php else: ?>
        The matrix returned no rows at all.
        <?php endif; ?>
    </div>
    <?php else: ?>

    <p class="tr-hint" style="margin:0 0 4px">
        <b><?= $nFound ?></b> gene<?= $nFound === 1 ? '' : 's' ?> computed from
        group&nbsp;A = <?= count($A) ?> column<?= count($A) === 1 ? '' : 's' ?>,
        group&nbsp;B = <?= count($B) ?> column<?= count($B) === 1 ? '' : 's' ?>.
        <?php if ($genesWanted): ?>
        <?php if ($nMissing <= 0): ?>
        <?php if ($nQueried === 1): ?>The gene ID looked up was found.<?php else: ?>
        All <b><?= $nQueried ?></b> gene IDs looked up were found.<?php endif; ?>
        <?php else: ?>
        <?php if ($nQueried === 1): ?>The gene ID looked up is not in this species&rsquo; matrix.<?php else: ?>
        <b><?= $nFound ?></b> of the <b><?= $nQueried ?></b> gene IDs looked up were found;
        <b><?= $nMissing ?></b> <?= $nMissing === 1 ? 'is' : 'are' ?> not in this species&rsquo; matrix and
        <?= $nMissing === 1 ? 'was' : 'were' ?> left out.<?php endif; ?>
        <?php endif; ?>
        <?php else: ?>
        Whole-matrix run, so only the <?= count($rows) ?> largest changes are shown; the hand-over below carries exactly these.
        <?php endif; ?>
    </p>
    <div class="tr-summary">
        <?php if ($uf > 0): ?><span style="background:#FF2D2D;flex-grow:<?= $uf ?>"></span><?php endif; ?>
        <?php if ($ff > 0): ?><span style="background:#86B342;flex-grow:<?= $ff ?>"></span><?php endif; ?>
        <?php if ($df > 0): ?><span style="background:#66B3FF;flex-grow:<?= $df ?>"></span><?php endif; ?>
    </div>
    <div class="tr-key">
        <span><i style="background:#FF2D2D"></i>Up in A &nbsp;<b><?= $nUp ?></b>
            &nbsp;<span style="color:#64748b">(log<sub>2</sub> ratio &ge; <?= htmlspecialchars($thr['red_low']) ?>)</span></span>
        <span><i style="background:#86B342"></i>Neither band &nbsp;<b><?= $nFlat ?></b></span>
        <span><i style="background:#66B3FF"></i>Down in A &nbsp;<b><?= $nDown ?></b>
            &nbsp;<span style="color:#64748b">(log<sub>2</sub> ratio &le; <?= htmlspecialchars($thr['blue_high']) ?>)</span></span>
    </div>
    <p class="tr-hint" style="margin:0 0 6px">
        This is how the network nodes will be coloured once you hand the ratios over.
    </p>
    <?php if ($nUp === 0 && $nDown === 0): ?>
    <div class="gd-notice gd-info" style="margin:14px 0">
        Nothing reached either band, so every node would come out <b>green</b> in the network view.
        With the thresholds above, a gene has to change <b>twofold or more</b> to be
        <font color="red">red</font> (log<sub>2</sub> ratio &ge; <?= htmlspecialchars($thr['red_low']) ?>)
        or to <b>half or less</b> to be <font color="blue">blue</font>
        (log<sub>2</sub> ratio &le; <?= htmlspecialchars($thr['blue_high']) ?>).
        The log<sub>2</sub> ratio column below shows what they actually are; widen the thresholds and press
        <b>Compute</b> again if you want the smaller changes coloured too.
    </div>
    <?php endif; ?>
    <?php if ($stats['both'] > 0): ?>
    <div class="gd-notice gd-info" style="margin:14px 0">
        <?= $stats['both'] ?> column<?= $stats['both'] === 1 ? ' was' : 's were' ?> ticked in both A and B;
        each of those counted towards <b>A</b> only.
    </div>
    <?php endif; ?>

    <div style="max-height:340px;overflow:auto;margin-top:14px">
        <table class="tr-tbl">
            <thead><tr><th>Gene</th><th class="tr-num">Mean&nbsp;A</th><th class="tr-num">Mean&nbsp;B</th>
                <th class="tr-num">log<sub>2</sub>&nbsp;ratio</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($rows, 0, 60) as $r):
                $col = ($r[3] >= (float)$thr['red_low']) ? '#FF2D2D'
                     : (($r[3] <= (float)$thr['blue_high']) ? '#66B3FF' : '#86B342'); ?>
                <tr><td><?= htmlspecialchars($r[0]) ?></td>
                    <td class="tr-num"><?= number_format($r[1], 2) ?></td>
                    <td class="tr-num"><?= number_format($r[2], 2) ?></td>
                    <td class="tr-num"><span class="tr-dot" style="background:<?= $col ?>"></span><?= number_format($r[3], 3) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($rows) > 60): ?>
    <p class="tr-hint" style="margin:10px 0 0">Showing the first 60 of <?= count($rows) ?> rows. All of them are handed over below.</p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($rows): ?>
<?php /* 4. 交给结果页 */ ?>
<div class="network-form-container">
    <div class="section-title">4. Continue to the network view</div>
    <p class="tr-hint" style="margin:0 0 18px">
        This submits the table above to <b>Dynamic Expression View</b> in one step &mdash; the ratios as its
        step&nbsp;3 input, and the gene pairs from your network result as its step&nbsp;2 input.
        If you change the grouping or the thresholds, press <b>Compute</b> again first: the hand-over carries
        whatever the last computation produced.
    </p>

    <?php if ($tpmPairs === ''): ?>
    <div class="gd-notice gd-warn" style="margin:0 0 18px">
        <b>No gene pairs came with you.</b> Dynamic Expression View draws edges between gene pairs, so with
        none supplied it has nothing to connect and the graph would come out empty. Run
        <a href="network.php">Network Analysis</a> first and use the hand-over button on its result page,
        or paste your own pairs into its step&nbsp;2.
    </div>
    <?php endif; ?>

    <form method="post" action="network.expression.php" target="_blank" id="trGoForm"
          onSubmit="return trSyncThr()">
        <input type="hidden" name="organism"    value="<?= htmlspecialchars($info[1]) ?>">
        <input type="hidden" name="networklist" value="<?= htmlspecialchars($tpmPairs) ?>">
        <input type="hidden" name="genelist"    value="<?= htmlspecialchars($handoff) ?>">
        <input type="hidden" name="red_low"   id="goRedLow"   value="<?= htmlspecialchars($thr['red_low']) ?>">
        <input type="hidden" name="red_high"  id="goRedHigh"  value="<?= htmlspecialchars($thr['red_high']) ?>">
        <input type="hidden" name="blue_low"  id="goBlueLow"  value="<?= htmlspecialchars($thr['blue_low']) ?>">
        <input type="hidden" name="blue_high" id="goBlueHigh" value="<?= htmlspecialchars($thr['blue_high']) ?>">
        <button type="submit" class="tr-go" id="trGoBtn">Open Dynamic Expression View</button>
        <span class="tr-hint" style="margin-left:16px">Opens in a new tab, so this page stays here for a second run.</span>
    </form>

    <details style="margin-top:22px">
        <summary class="tr-hint" style="cursor:pointer;font-weight:600;color:#475569">
            Or copy the ratios by hand (gene &lt;TAB&gt; log<sub>2</sub> ratio)
        </summary>
        <textarea readonly class="form-textarea" style="margin-top:14px"><?= htmlspecialchars($handoff) ?></textarea>
    </details>
</div>
<?php endif; ?>
<?php endif; ?>

</div>
</div>
</div>

<script>
<?php /* 列名过滤：样本列可能有上百个，靠肉眼找太慢 */ ?>
function trFilter(q) {
    q = (q || '').toLowerCase();
    var rows = document.querySelectorAll('.tr-cols tbody tr');
    for (var i = 0; i < rows.length; i++) {
        var cell = rows[i].querySelector('.tr-name');
        var name = (cell.getAttribute('title') || cell.textContent).toLowerCase();
        rows[i].style.display = (q === '' || name.indexOf(q) !== -1) ? '' : 'none';
    }
}

<?php /* 换物种时把 textarea 里已经打好的基因列表一起带走（否则一换物种就白打了） */ ?>
function trSwitch() {
    var ta = document.querySelector('#trComputeForm textarea[name="genelist"]');
    var hold = document.getElementById('trGeneHold');
    if (ta && hold) { hold.value = ta.value; }
    document.getElementById('trSpeciesForm').submit();
}

<?php /* 交出去之前，把第 2 步里改过的阈值同步到第 4 步的表单（两个表单不共享状态） */ ?>
function trSyncThr() {
    var f = document.getElementById('trComputeForm');
    if (!f) { return true; }
    var map = {red_low: 'goRedLow', red_high: 'goRedHigh', blue_low: 'goBlueLow', blue_high: 'goBlueHigh'};
    for (var k in map) {
        var src = f.querySelector('input[name="' + k + '"]');
        var dst = document.getElementById(map[k]);
        if (src && dst && src.value !== '') { dst.value = src.value; }
    }
    return true;
}

<?php /* 勾选计数：让用户看清自己选了几列，避免只勾了一列就往下走 */ ?>
(function () {
    var box = document.getElementById('trComputeForm');
    var out = document.getElementById('trCount');
    if (!box || !out) { return; }
    function upd() {
        var a = box.querySelectorAll('input[name="grpA[]"]:checked').length;
        var b = box.querySelectorAll('input[name="grpB[]"]:checked').length;
        var msg = 'Selected: A = ' + a + ', B = ' + b + '.';
        if (a === 0 || b === 0) { msg += ' Both sides need at least one column.'; }
        else if (a === 1 || b === 1) { msg += ' One column on a side means a single run against a single run — no replication.'; }
        out.textContent = msg;
    }
    box.addEventListener('change', function (e) {
        if (e.target && e.target.name && e.target.name.indexOf('grp') === 0) { upd(); }
    });
    upd();
})();
</script>

<?php
    include "../Webpage_components.php";
    print $footer;
?>
</body>
</html>
