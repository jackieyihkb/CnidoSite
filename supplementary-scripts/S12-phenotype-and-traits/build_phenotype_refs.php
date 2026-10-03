<?php
/* =====================================================================
 * build_phenotype_refs.php —— 给 phenotype_v2 补上「这个 trait 是什么」和「这条
 * 记录出自哪篇文章」，并把第三家源库 Pelagic 的记录并进去。
 *
 * 建三样东西（都可重复执行，先删后插）：
 *   1. phenotype_trait_dict     —— trait 名字 → 类别 / 数据类型 / 单位 / 允许取值 /
 *                                  说明。OCTD 与 Pelagic 两家合一张表（source 区分）。
 *   2. phenotype_source_resource —— OCTD 的 816 条文献（作者/年份/标题/期刊/DOI）。
 *                                  主表的 source_resource_id 指向这里。
 *   3. phenotype_v2 里 Pelagic 的行 —— Pelagic 主表是【宽表】（一行一物种×生活史，
 *                                  59 列），这里长表化成一行一个 trait。
 *
 * 执行顺序：先 build_phenotype_v2.php（它会 DROP 重建整张表，把这些附加源冲掉），
 * 再跑本脚本。build_phenotype_v2.php 的文件头里也写了这一点。
 *
 * 用法：php build_phenotype_refs.php [--apply] [--octd-dir <Table_ids>]
 *                                  [--pelagic-dir <目录>]
 * ===================================================================== */

$opts = array('apply' => false,
              'octd-dir'    => '/tmp/octd_check/OctocoralTraits/Table_ids',
              'pelagic-dir' => '/tmp/pheno_src/pelagic');
for ($i = 1; $i < $argc; $i++) {
    $a = $argv[$i];
    if ($a === '--apply')            { $opts['apply'] = true; }
    elseif ($a === '--octd-dir')     { $opts['octd-dir'] = $argv[++$i]; }
    elseif ($a === '--pelagic-dir')  { $opts['pelagic-dir'] = $argv[++$i]; }
    else { fwrite(STDERR, "未知参数: {$a}\n"); exit(2); }
}

const OCTD_SRC    = 'OCTD v2.2';
const PELAGIC_SRC = 'Pelagic v3.1';

$src = @file_get_contents('/var/www/html/CnidoSite/includes/state.php');
preg_match("/\\\$conn = @new mysqli\('([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)'\)/", $src, $m);
$conn = @new mysqli($m[1], $m[2], $m[3], $m[4]);
if ($conn->connect_errno) { fwrite(STDERR, "连接失败 ({$conn->connect_errno})\n"); exit(2); }
$conn->set_charset('utf8mb4');
set_time_limit(0);

/** 读分隔符文件成关联数组。首字段前挂 UTF-8 BOM 时 fgetcsv 不认那对引号，
 *  所以必须【先剥 BOM、再 trim 引号】——反过来前导引号会活下来。 */
function rd($file, $delim = ',')
{
    $fh = fopen($file, 'r');
    if ($fh === false) { fwrite(STDERR, "打不开 {$file}\n"); exit(1); }
    $h = fgetcsv($fh, 0, $delim);
    $h[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$h[0]);
    $h = array_map(function ($x) { return trim((string)$x, '"'); }, $h);
    $rows = array();
    while (($r = fgetcsv($fh, 0, $delim)) !== false) {
        if (count($r) === count($h)) { $rows[] = array_combine($h, $r); }
        elseif (count($r) > count($h)) { $rows[] = array_combine($h, array_slice($r, 0, count($h))); }
    }
    fclose($fh);
    return array($h, $rows);
}

function ex($conn, $sql) { if ($conn->query($sql) === false) { fwrite(STDERR, "SQL 失败: {$conn->error}\n  {$sql}\n"); exit(1); } }
function esc($conn, $s) { return "'" . $conn->real_escape_string((string)$s) . "'"; }

/** 保留【数字下标】的读法。有些文件的列名本身就是一句说明（Pelagic 的字典里
 *  第 5 列列名是 "values; NA = searched but no data found, -9999 = not searched"），
 *  array_combine 之后就只剩那个长键，按 $d[4] 取不到任何东西 —— 会静默拿到空串。 */
function rd_num($file, $delim = ',')
{
    $fh = fopen($file, 'r');
    if ($fh === false) { fwrite(STDERR, "打不开 {$file}\n"); exit(1); }
    $h = fgetcsv($fh, 0, $delim);
    $rows = array();
    while (($r = fgetcsv($fh, 0, $delim)) !== false) { $rows[] = $r; }
    fclose($fh);
    return array($h, $rows);
}

/* ---------------------------------------------------------------------
 * 1. 两张字典表
 * ------------------------------------------------------------------- */
if ($opts['apply']) {
    ex($conn, "DROP TABLE IF EXISTS phenotype_trait_dict");
    ex($conn, "CREATE TABLE phenotype_trait_dict (
                 source varchar(40) NOT NULL DEFAULT '',
                 trait_name varchar(120) NOT NULL DEFAULT '',
                 trait_category varchar(60) NOT NULL DEFAULT '',
                 data_type varchar(24) NOT NULL DEFAULT '',
                 traitunit varchar(40) NOT NULL DEFAULT '',
                 allowed_values text,
                 trait_desc text,
                 UNIQUE KEY uq (source, trait_name)
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    ex($conn, "DROP TABLE IF EXISTS phenotype_source_resource");
    ex($conn, "CREATE TABLE phenotype_source_resource (
                 source varchar(40) NOT NULL DEFAULT '',
                 resource_id varchar(20) NOT NULL DEFAULT '',
                 authors text, year varchar(10) NOT NULL DEFAULT '',
                 title text, container text, doi varchar(160) NOT NULL DEFAULT '',
                 resource_type varchar(40) NOT NULL DEFAULT '',
                 UNIQUE KEY uq (source, resource_id)
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
}

/* ---- 1a. OCTD 的 trait 说明（trait_id.csv）---- */
list(, $traits) = rd($opts['octd-dir'] . '/trait_id.csv');
$nT = 0;
if ($opts['apply']) {
    $st = $conn->prepare("INSERT INTO phenotype_trait_dict (source, trait_name, trait_desc)
                          VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE trait_desc=VALUES(trait_desc)");
    foreach ($traits as $t) {
        $nm = trim((string)$t['trait_name']); $de = trim((string)$t['trait_description']);
        if ($nm === '') { continue; }
        $s = OCTD_SRC; $st->bind_param('sss', $s, $nm, $de); $st->execute(); $nT++;
    }
    $st->close();
    /* 类别与单位：从主表数据里现取（源库的 trait_category / standard_unit 列），
       比去猜 traitclass_id 可靠。一个 trait 可能横跨多个单位，取最常见的一个。 */
    ex($conn, "UPDATE phenotype_trait_dict d
               JOIN (SELECT trait_name, MIN(trait_category) c FROM phenotype_v2
                     WHERE source='" . OCTD_SRC . "' GROUP BY trait_name) x
                 ON x.trait_name = d.trait_name
               SET d.trait_category = x.c
               WHERE d.source='" . OCTD_SRC . "'");
    ex($conn, "UPDATE phenotype_trait_dict d
               JOIN (SELECT trait_name, SUBSTRING_INDEX(GROUP_CONCAT(traitunit ORDER BY n DESC), ',', 1) u
                     FROM (SELECT trait_name, traitunit, COUNT(*) n FROM phenotype_v2
                           WHERE source='" . OCTD_SRC . "' GROUP BY trait_name, traitunit) z
                     GROUP BY trait_name) y
                 ON y.trait_name = d.trait_name
               SET d.traitunit = y.u
               WHERE d.source='" . OCTD_SRC . "'");
}

/* ---- 1b. Pelagic 的字典（1metadata_*.csv）；同时得到列名→单位的映射 ----
   按数字下标读：第 5 列的列名本身就是一句说明，用列名取会静默拿到空串。
   单位从第 5 列的写法里推：`depth (m)` → m、`percent (%)` → %、
   `ratio (unitless)` → unitless、`kj/g` → 原文即单位、
   `1 = yes, 0 = no` → bin、`0-5` → unitless + 允许取值、
   余下的分类列（DD, LC, …）→ cat + 允许取值。 */
list($mh, $metaNum) = rd_num($opts['pelagic-dir'] . '/metadata.csv', "\t");
$pelUnit = array(); $pelMeta = array();
foreach ($metaNum as $r) {
    if (count($r) < 5) { continue; }
    $col = trim((string)$r[0]);
    if ($col === '') { continue; }
    $cat  = trim((string)$r[1]);
    $desc = trim((string)$r[2]);
    $dt   = trim((string)$r[3]);
    $vals = trim((string)$r[4]);
    if (stripos($vals, 'NA = searched') !== false) { $vals = ''; }

    $unit = ''; $allowed = '';
    if (stripos($vals, '1 = yes') !== false) {
        $unit = 'bin'; $allowed = '1 = yes, 0 = no';
    } elseif (preg_match('/\(([^)]*)\)/', $vals, $mm) === 1) {
        $unit = trim($mm[1]);
    } elseif ($dt === 'continuous' && preg_match('/^[0-9]+(\.[0-9]+)?\s*-\s*[0-9]+(\.[0-9]+)?$/', $vals) === 1) {
        $unit = 'unitless'; $allowed = $vals;
    } elseif ($dt === 'continuous') {
        $unit = $vals;                                  // `kj/g` 这种没括号的，原文就是单位
    } elseif ($dt === 'binomial') {
        $unit = 'bin'; $allowed = $vals;
    } else {
        $unit = 'cat'; $allowed = $vals;                // categorical / annotation
    }
    $pelUnit[$col] = $unit;
    $pelMeta[$col] = array('cat' => $cat, 'dt' => $dt, 'unit' => $unit,
                           'allowed' => $allowed, 'desc' => $desc);
}
$nP = 0;
if ($opts['apply']) {
    $st = $conn->prepare("INSERT INTO phenotype_trait_dict
                          (source, trait_name, trait_category, data_type, traitunit, allowed_values, trait_desc)
                          VALUES (?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE trait_category=VALUES(trait_category),
                            data_type=VALUES(data_type), traitunit=VALUES(traitunit),
                            allowed_values=VALUES(allowed_values), trait_desc=VALUES(trait_desc)");
    $skip = array('class', 'order', 'family', 'genus', 'sci_name', 'tax_level');
    foreach ($pelMeta as $col => $d) {
        if (in_array($col, $skip, true)) { continue; }
        $s = PELAGIC_SRC;
        $st->bind_param('sssssss', $s, $col, $d['cat'], $d['dt'], $d['unit'], $d['allowed'], $d['desc']);
        $st->execute(); $nP++;
    }
    $st->close();
}

/* 没有单位的连续列得人工过一眼，别默默留空 */
$noUnit = array();
foreach ($pelMeta as $col => $d) {
    if ($d['dt'] === 'continuous' && $d['unit'] === '' && !in_array($col, array('class','order','family','genus','sci_name','tax_level'), true)) {
        $noUnit[] = $col;
    }
}

/* ---- 1c. OCTD 的 816 条文献（resource_id.csv）---- */
list(, $res) = rd($opts['octd-dir'] . '/resource_id.csv');
$nR = 0;
if ($opts['apply']) {
    $st = $conn->prepare("INSERT INTO phenotype_source_resource
                          (source, resource_id, authors, year, title, container, doi, resource_type)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE authors=VALUES(authors), year=VALUES(year),
                            title=VALUES(title), container=VALUES(container), doi=VALUES(doi),
                            resource_type=VALUES(resource_type)");
    foreach ($res as $r) {
        $id = trim((string)$r['resource_id']);
        if ($id === '') { continue; }
        $s = OCTD_SRC;
        $au = trim((string)$r['author']); $yr = trim((string)$r['year']);
        $ti = trim((string)$r['title']);  $jo = trim((string)$r['resource_journal']);
        $doi = trim((string)$r['doi_ISBN']); $ty = trim((string)$r['resource_type']);
        $st->bind_param('ssssssss', $s, $id, $au, $yr, $ti, $jo, $doi, $ty);
        $st->execute(); $nR++;
    }
    $st->close();
}

/* ---------------------------------------------------------------------
 * 2. Pelagic 的记录：宽表 → 长表
 * ------------------------------------------------------------------- */
const PEL_CLASSES = array('scyphozoa', 'hydrozoa', 'cubozoa', 'anthozoa',
                          'octocorallia', 'hexacorallia', 'staurozoa', 'myxozoa');
list($ph, $prow) = rd($opts['pelagic-dir'] . '/master.csv', "\t");
$keyCols = array('class', 'order', 'family', 'genus', 'sci_name', 'tax_level');
$long = array(); $seen = array();
$skipped = array('NA' => 0, '-9999' => 0, '' => 0);
foreach ($prow as $r) {
    $cls = strtolower(trim((string)(isset($r['class']) ? $r['class'] : '')));
    if (!in_array($cls, PEL_CLASSES, true)) { continue; }
    $sp = trim((string)$r['sci_name']);
    if ($sp === '') { continue; }
    foreach ($r as $col => $v) {
        if (in_array($col, $keyCols, true)) { continue; }
        $v = trim((string)$v);
        if ($v === 'NA' || $v === '-9999' || $v === '') { $skipped[$v]++; continue; }
        $ctx = ($col === 'life_stage') ? '' : trim((string)$r['life_stage']);
        $unit = ($col === 'life_stage') ? 'cat' : (isset($pelUnit[$col]) ? $pelUnit[$col] : '');
        /* 生活史单独出一行，说明它是这一行的限定词而不是并列的 trait */
        $row = array('Class' => trim((string)$r['class']), 'species' => $sp,
                     'trait_name' => $col,
                     'trait_category' => ($col === 'life_stage' ? 'annotation'
                                          : (isset($pelMeta[$col]) ? $pelMeta[$col]['cat'] : '')),
                     'value' => $v, 'traitunit' => $unit,
                     'methodology' => '', 'value_type' => '', 'context' => $ctx);
        $k = md5(implode("\x1f", $row));
        if (isset($seen[$k])) { continue; }
        $seen[$k] = true;
        $long[] = $row;
    }
}

if ($opts['apply']) {
    ex($conn, "DELETE FROM phenotype_v2 WHERE source='" . PELAGIC_SRC . "'");
    $st = $conn->prepare("INSERT INTO phenotype_v2
        (Class, species, trait_name, trait_category, value, traitunit, region, latitude,
         longitude, methodology, value_type, context, source, source_record_id,
         source_resource_id, n_records)
        VALUES (?,?,?,?,?,?,'','','',?,?,?,?,'','',1)");
    foreach ($long as $r) {
        $s = PELAGIC_SRC;
        $st->bind_param('ssssssssss', $r['Class'], $r['species'], $r['trait_name'],
            $r['trait_category'], $r['value'], $r['traitunit'],
            $r['methodology'], $r['value_type'], $r['context'], $s);
        $st->execute();
    }
    $st->close();
}

/* ---------------------------------------------------------------------
 * 报告
 * ------------------------------------------------------------------- */
printf("OCTD trait 说明 %s 条 / Pelagic 字典 %s 条 / OCTD 文献 %s 条\n",
       number_format($nT), number_format($nP), number_format($nR));
printf("Pelagic 长表化 %s 行（宽表跳过的空值：NA %s / -9999 %s / 空 %s）\n",
       number_format(count($long)), number_format($skipped['NA']),
       number_format($skipped['-9999']), number_format($skipped['']));
if ($noUnit) { echo "** 没有单位的连续列（请人工确认）: " . implode(', ', $noUnit) . "\n"; }

if (!$opts['apply']) { echo "\n干跑，未写库。（--apply 才写）\n"; exit(0); }

echo "\n--- phenotype_v2 按来源 ---\n";
$q = $conn->query("SELECT source, COUNT(*) n, SUM(n_records) sr, COUNT(DISTINCT species) sp
                   FROM phenotype_v2 GROUP BY source ORDER BY n DESC");
while ($r = $q->fetch_row()) {
    printf("  %-24s %8s 行（源记录 %9s） 物种 %6s\n", $r[0],
           number_format((int)$r[1]), number_format((int)$r[2]), number_format((int)$r[3]));
}
echo "\n--- Pelagic 的物种 ---\n";
$q = $conn->query("SELECT Class, species, COUNT(*) n FROM phenotype_v2
                   WHERE source='" . PELAGIC_SRC . "' GROUP BY Class, species ORDER BY species");
while ($r = $q->fetch_row()) { printf("  %-12s %-28s %s 个 trait\n", $r[0], $r[1], $r[2]); }
echo "\n--- Pelagic 抽样（看单位与限定词对不对）---\n";
$q = $conn->query("SELECT species, trait_name, value, traitunit, trait_category, context
                   FROM phenotype_v2 WHERE source='" . PELAGIC_SRC . "'
                     AND species IN ('Aurelia aurita','Chrysaora fuscescens') LIMIT 12");
while ($r = $q->fetch_row()) {
    printf("  %-20s %-18s %-14s %-10s %-14s %s\n", $r[0], $r[1], substr((string)$r[2], 0, 14), $r[3], $r[4], $r[5]);
}
echo "\n--- 字典抽样 ---\n";
$q = $conn->query("SELECT source, trait_name, trait_category, data_type, traitunit, LEFT(COALESCE(trait_desc,''),44)
                   FROM phenotype_trait_dict WHERE trait_desc <> '' OR allowed_values <> '' LIMIT 6");
while ($r = $q->fetch_row()) { printf("  %-14s %-22s %-14s %-10s %-8s %s\n", $r[0], $r[1], $r[2], $r[3], $r[4], $r[5]); }
