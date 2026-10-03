<?php
/* =====================================================================
 * build_phenotype_v2.php —— 从源库重建 phenotype 表（不改动现有的 phenotype）
 *
 * 背景：现有 cnidaria.phenotype 的 value 列装的是源库 OCTD 的 standard_id 编号，
 * 不是读数。导入时取错了列，OCTD 真正的 value 整列没进来 —— 151,046 行里
 * 148,510 行（98.32%）的 value 能在 106 行字典里逐字找到，数值型 trait 的读数
 * 也一并丢了。详见 includes/phenotype_traits.php 的文件头。
 *
 * 做法：把 Octocorallia 那 148,492 行按源文件顺序整块重建（value ← value，
 * traitunit ← standard_unit），另补三个新列（value_type / source /
 * source_record_id），并把【内容完全相同】的行折叠成一行、记 n_records。
 * 非 Octocorallia 的 2,554 行（水母/水螅/石珊瑚，真值）原样搬过来，标
 * source='legacy (unattributed)' —— 它们的出处至今没查到（trait 名是
 * bodyLengthMax / ashPDW / VD.bathypelagic 这种驼峰缩写，Pelagic 库里对不上）。
 *
 * 行级回灌不可行：本站没存 observation_id，内容键最好也只有 94.7% 唯一
 * （多重度最高 1,926），UPDATE...JOIN 会张冠李戴。只能按文件序整块重建。
 *
 * 去重放在 SQL 里做（PHP 只负责算 MD5 键），避免把 12 万行攒在内存里。
 * MD5 键在 PHP 里算，所以不经过 MySQL 的比较规则 —— µm(U+00B5) 与 μm(U+03BC)
 * 是两个单位、字节不同，绝不能靠 sort 规则去判。
 *
 * 用法：
 *   php build_phenotype_v2.php                     # 干跑：只读 CSV + 旧表，报数，不写库
 *   php build_phenotype_v2.php --apply              # 真建表（DROP 掉旧的 v2！）
 *   php build_phenotype_v2.php --report             # 只对已有的 v2 出对账报告
 *   可选：--csv <路径>  --keep-raw（保留中转表 phenotype_v2_raw）
 *
 * --apply 只碰 phenotype_v2 / phenotype_v2_raw，不碰 phenotype。
 *
 * 2026-09-27 已按 B 方案换表：`RENAME TABLE phenotype TO phenotype_old_20260927,
 * phenotype_v2 TO phenotype`。所以现在**主表就叫 phenotype**，而本脚本仍然
 * 建 phenotype_v2 —— 再跑一次 --apply 是往中转表里建，确认无误后要再执行一次
 * 同样的 RENAME 才会上线（旧的 phenotype 那时会覆盖掉今天那份快照，先想清楚）。
 *
 * 重建之后的完整管线（按这个顺序，缺一步表就不对）：
 *   1. build_phenotype_v2.php --apply            OCTD 整块重建 + 遗留行搬运
 *                                                + 折叠 + 类别规范化（3.5 步）
 *   2. fetch_worms_traits.php --load --apply --table phenotype_v2
 *                                                第四家（WoRMS）落中转表，
 *                                                类别在这一步就写对
 *   3. pheno_worms_verify.php / pheno_v2_verify.php   独立核对
 *   4. RENAME TABLE …
 *   5. 删 tmp/coverage_cache.json 与 tmp/phenotype_stats.json（两处缓存都按
 *      表内容算，换了表不删就是旧数）
 * ===================================================================== */

$opts = array('csv' => '/tmp/octd_check/OctocoralTraits/OctocoralTraits_v2_2.csv',
              'apply' => false, 'report' => false, 'keep-raw' => false);
for ($i = 1; $i < $argc; $i++) {
    $a = $argv[$i];
    if ($a === '--apply')       { $opts['apply'] = true; }
    elseif ($a === '--report')  { $opts['report'] = true; }
    elseif ($a === '--keep-raw'){ $opts['keep-raw'] = true; }
    elseif ($a === '--csv')     { $opts['csv'] = isset($argv[++$i]) ? $argv[$i] : ''; }
    else { fwrite(STDERR, "未知参数: {$a}\n"); exit(2); }
}
if (!is_file($opts['csv'])) { fwrite(STDERR, "找不到源文件: {$opts['csv']}\n"); exit(2); }

$src = @file_get_contents('/var/www/html/CnidoSite/includes/state.php');
if ($src === false ||
    !preg_match("/\\\$conn = @new mysqli\('([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)'\)/", $src, $m)) {
    fwrite(STDERR, "没能从 includes/state.php 里解析出连接参数\n"); exit(2);
}
$conn = @new mysqli($m[1], $m[2], $m[3], $m[4]);
if ($conn->connect_errno) { fwrite(STDERR, "连接失败 ({$conn->connect_errno})\n"); exit(2); }
$conn->set_charset('utf8mb4');
set_time_limit(0);

/** 源库这一块用的署名。页面会原样印出来做 CC BY 归属。 */
define('PHENO2_OCTD_SOURCE', 'OCTD v2.2');
define('PHENO2_LEGACY_SOURCE', 'legacy (unattributed)');

/* 类别名的规范化（大小写、源文件自己的错拼）与 WoRMS 的类别对照表都在这里，
   网页侧不用它 —— 库里存的就是规范过的值，页面只管印。 */
require_once __DIR__ . '/worms_trait_category.php';

/* 内容列 —— 决定「两行算同一行」的那几列（不含 source_record_id！同一观测
   在两个 trait 上各留一条是对的，但完全相同的行折叠）。
   source 参与哈希：不参与的话，遗留行里碰巧和 OCTD 逐字相同的一行会被合并成
   一条、来源说不清。分开计数才诚实。 */
/* 注：本脚本的 $contentCols 只是注释性质的清单 —— 折叠用的是 MD5 键
   （见 pheno2_hash()），列清单在 SQL 的 CONCAT_WS 里写死。context 是后加的
   列（Pelagic 的同一物种有幼体/成体多行，长表化后必须有限定词），OCTD 一律空。 */
$contentCols = array('Class', 'species', 'trait_name', 'trait_category', 'value',
                     'traitunit', 'region', 'latitude', 'longitude', 'methodology',
                     'value_type', 'context');

function pheno2_hash($source, $f)
{
    return md5($source . "\x1f" . implode("\x1f", $f));
}

/** 建中转表 / 目标表。中转表无索引（纯顺序写）；目标表按查法建前缀索引。 */
function pheno2_ddl($conn)
{
    $raw = "CREATE TABLE phenotype_v2_raw (
              Class text, species text, trait_name text, trait_category text, value text,
              traitunit text, region text, latitude text, longitude text, methodology text,
              value_type text, context varchar(60) NOT NULL DEFAULT '',
              source varchar(40), source_record_id varchar(64),
              source_resource_id varchar(32), row_hash char(32) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
    /* 目标表：列名与旧表逐字一致（十个旧列不动），避免全站读这张表的页面跟着改。
       TEXT 列建索引必须给前缀长度，否则 MySQL 直接报 1170。

       id 放在第一列、作自增主键：**折叠之后原来的十列排不出全序**。同一物种同一
       性状的值可以有两行，差别只在 value_type / context / source 上（例：Corallium
       rubrum 的「Type of skeleton」一条 raw_value、一条 expert_opinion），而这十列
       全是 utf8mb4_0900_ai_ci 的 text —— 大小写不同的两行在这些列上比较是相等的。
       分页跨 LIMIT 边界时同一行会在两页都出现、或者两页都不出现。页面层用 id 当
       最后一个排序键把它补成严格全序（phenotype.php / phenotype_species.php）。
       线上表 2026-09-27 已经用 ALTER 加过同一列，两边的表结构必须一致，否则
       RENAME 之后页面就查不到 id。 */
    $new = "CREATE TABLE phenotype_v2 (
              id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
              Class text, species text, trait_name text, trait_category text, value text,
              traitunit text, region text, latitude text, longitude text, methodology text,
              value_type text, context varchar(60) NOT NULL DEFAULT '',
              source varchar(40) NOT NULL DEFAULT '',
              source_record_id varchar(64) NOT NULL DEFAULT '',
              source_resource_id varchar(32) NOT NULL DEFAULT '',
              n_records int NOT NULL DEFAULT 1,
              INDEX ix_species (species(64)),
              INDEX ix_class (Class(32)),
              INDEX ix_unit (traitunit(32)),
              INDEX ix_cat (trait_category(32)),
              INDEX ix_source (source)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";

    foreach (array('DROP TABLE IF EXISTS phenotype_v2_raw', 'DROP TABLE IF EXISTS phenotype_v2', $raw, $new) as $sql) {
        if ($conn->query($sql) === false) { fwrite(STDERR, "DDL 失败: {$conn->error}\n"); exit(1); }
    }
}

/* ---------------------------------------------------------------------
 * 1. 读 OCTD，逐行写进中转表
 * ------------------------------------------------------------------- */
function pheno2_load_octd($conn, $csv)
{
    $fh = fopen($csv, 'r');
    if ($fh === false) { fwrite(STDERR, "打不开 {$csv}\n"); exit(1); }

    /* 表头首字段前面挂着 UTF-8 BOM 时，fgetcsv 不认那对引号（enclosure 必须是
       字段的第一个字符），于是表头键会连引号一起留下。必须先剥 BOM 再 trim 引号，
       反过来操作前导引号会活下来 —— 在 Pelagic 的表上刚踩过。 */
    $hdr = fgetcsv($fh, 0, ',');
    $hdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$hdr[0]);
    $hdr = array_map(function ($x) { return trim((string)$x, '"'); }, $hdr);
    $ix = array_flip($hdr);

    $need = array('observation_id', 'specie_name', 'trait_name', 'trait_category',
                  'standard_unit', 'value', 'value_type', 'location_name',
                  'latitude', 'longitude', 'methodology_name', 'resource_id');
    foreach ($need as $c) {
        if (!isset($ix[$c])) { fwrite(STDERR, "源文件缺列: {$c}\n"); exit(1); }
    }

    $cols = 16;   // 15 个字段 + row_hash
    $sql  = "INSERT INTO phenotype_v2_raw (Class, species, trait_name, trait_category, value,
             traitunit, region, latitude, longitude, methodology, value_type, context, source,
             source_record_id, source_resource_id, row_hash) VALUES ";
    $tup  = '(' . implode(',', array_fill(0, $cols, '?')) . ')';
    $batch = array(); $n = 0; $line = 0;

    $flush = function () use ($conn, $sql, $tup, &$batch, &$n) {
        if (!$batch) { return; }
        $stmt = $conn->prepare($sql . implode(',', array_fill(0, count($batch), $tup)));
        if ($stmt === false) { fwrite(STDERR, "prepare 失败: {$conn->error}\n"); exit(1); }
        $vals = array();
        foreach ($batch as $row) { foreach ($row as $v) { $vals[] = $v; } }
        $stmt->bind_param(str_repeat('s', count($vals)), ...$vals);
        if (!$stmt->execute()) { fwrite(STDERR, "批量插入失败: {$stmt->error}\n"); exit(1); }
        $n += count($batch); $batch = array();
        $stmt->close();
    };

    $g = function ($r, $k) use ($ix) { return trim((string)(isset($r[$ix[$k]]) ? $r[$ix[$k]] : '')); };

    while (($r = fgetcsv($fh, 0, ',')) !== false) {
        if (count($r) < 20) { continue; }        // 尾部空行 / 残行
        $line++;
        $f = array(
            'Octocorallia',           // Class —— OCTD 全库就是八放珊瑚亚纲
            $g($r, 'specie_name'),    // species
            $g($r, 'trait_name'),
            $g($r, 'trait_category'),
            $g($r, 'value'),          // ★ 修的就是这一列（旧表这里是 standard_id）
            $g($r, 'standard_unit'),  // traitunit
            $g($r, 'location_name'),  // region
            $g($r, 'latitude'),
            $g($r, 'longitude'),
            $g($r, 'methodology_name'),
            $g($r, 'value_type'),
            '',                       // context —— OCTD 的行没有子记录限定词
        );
        $batch[] = array_merge($f, array(
            PHENO2_OCTD_SOURCE,
            $g($r, 'observation_id'),
            $g($r, 'resource_id'),
            pheno2_hash(PHENO2_OCTD_SOURCE, $f),
        ));
        if (count($batch) >= 200) { $flush(); }
    }
    $flush();
    fclose($fh);
    printf("  OCTD 读入 %s 行（源文件数据行 %s）\n", number_format($n), number_format($line));
    return $n;
}

/* ---------------------------------------------------------------------
 * 2. 非 Octocorallia 的旧行原样搬过来（水母 / 水螅 / 石珊瑚，共 2,554 行）
 * ------------------------------------------------------------------- */
function pheno2_load_legacy($conn)
{
    /* 只搬 Class 不是 Octocorallia 的。Octocorallia 那 148,492 行整块由 OCTD
       重建取代 —— 它们就是 value 装错列的那一批。 */
    $sql = "INSERT INTO phenotype_v2_raw
              (Class, species, trait_name, trait_category, value, traitunit, region,
               latitude, longitude, methodology, value_type, context, source, source_record_id,
               source_resource_id, row_hash)
            SELECT COALESCE(Class,''), COALESCE(species,''), COALESCE(trait_name,''),
                   COALESCE(trait_category,''), COALESCE(value,''), COALESCE(traitunit,''),
                   COALESCE(region,''), COALESCE(latitude,''), COALESCE(longitude,''),
                   COALESCE(methodology,''), '', '',
                   '" . PHENO2_LEGACY_SOURCE . "', '', '',
                   MD5(CONCAT('" . PHENO2_LEGACY_SOURCE . "', CHAR(31),
                              COALESCE(Class,''), CHAR(31), COALESCE(species,''), CHAR(31),
                              COALESCE(trait_name,''), CHAR(31), COALESCE(trait_category,''), CHAR(31),
                              COALESCE(value,''), CHAR(31), COALESCE(traitunit,''), CHAR(31),
                              COALESCE(region,''), CHAR(31), COALESCE(latitude,''), CHAR(31),
                              COALESCE(longitude,''), CHAR(31), COALESCE(methodology,''), CHAR(31),
                              '', CHAR(31), ''))
            FROM phenotype
            WHERE TRIM(COALESCE(Class,'')) <> 'Octocorallia'";
    if ($conn->query($sql) === false) { fwrite(STDERR, "搬遗留行失败: {$conn->error}\n"); exit(1); }
    return $conn->affected_rows;
}

/* ---------------------------------------------------------------------
 * 3. 折叠重复行 → 目标表
 * ------------------------------------------------------------------- */
function pheno2_dedupe($conn)
{
    /* ANY_VALUE()：同一 row_hash 内各列必然逐字相同，取谁都不影响结果，
       但 ONLY_FULL_GROUP_BY 要求显式写出来。
       MIN(NULLIF(source_record_id,''))：折叠后保留文件序里的第一条观测号，
       让用户还能回溯到源库；全为空时留空串。 */
    $sql = "INSERT INTO phenotype_v2
              (Class, species, trait_name, trait_category, value, traitunit, region,
               latitude, longitude, methodology, value_type, context, source, source_record_id,
               source_resource_id, n_records)
            SELECT ANY_VALUE(Class), ANY_VALUE(species), ANY_VALUE(trait_name),
                   ANY_VALUE(trait_category), ANY_VALUE(value), ANY_VALUE(traitunit),
                   ANY_VALUE(region), ANY_VALUE(latitude), ANY_VALUE(longitude),
                   ANY_VALUE(methodology), ANY_VALUE(value_type), ANY_VALUE(context),
                   ANY_VALUE(source),
                   COALESCE(MIN(NULLIF(source_record_id,'')), ''),
                   COALESCE(MIN(NULLIF(source_resource_id,'')), ''),
                   COUNT(*)
            FROM phenotype_v2_raw GROUP BY row_hash";
    if ($conn->query($sql) === false) { fwrite(STDERR, "折叠失败: {$conn->error}\n"); exit(1); }
    return $conn->affected_rows;
}

/* ---------------------------------------------------------------------
 * 3.5 类别名规范化
 *
 * 两处源文件自带的毛病，改的是 trait_category 的值、不动行：
 *   · OCTD 的 CSV 自己把 "Stoichiometric" 拼成 "Stoichometric"（11 行，
 *     v2.2 文件里错的 11 次、对的 0 次）—— 不规范化就会在矩阵里和遗留的
 *     555 行并排成两列。
 *   · Pelagic 用小写 'morphological'，OCTD/遗留用大写（367 vs 47,503）。
 *     MySQL 排序规则不区分大小写，两者本来就会在 GROUP BY 里合成一组，
 *     但**列名取的是先返回的那一行**，于是矩阵上的列名不确定。
 *
 * 放在折叠**之后**做：折叠键里含 trait_category，但同一来源的同一写法只会有
 * 一种拼法，跨来源的行 source 不同、本来就不在同一个折叠组里 —— 所以规范化
 * 不会让两行变成「本该折叠却没折叠」（pheno2_normalize_categories 会自检）。
 * ------------------------------------------------------------------- */
function pheno2_normalize_categories($conn)
{
    $n = 0;
    /* 分组必须区分大小写：默认排序规则会把 'Morphological' 与 'morphological'
       合成一组只返回其中一个写法，小写那批就漏掉了。写法上只能用
       CAST(… AS BINARY) + MIN()，`GROUP BY BINARY 列` 会撞 ONLY_FULL_GROUP_BY。 */
    $q = $conn->query("SELECT MIN(TRIM(COALESCE(trait_category,''))), COUNT(*)
                         FROM phenotype_v2 GROUP BY CAST(trait_category AS BINARY)");
    if ($q === false) { fwrite(STDERR, "读类别失败: {$conn->error}\n"); exit(1); }
    $todo = array();
    while ($r = $q->fetch_row()) {
        $old = (string)$r[0];
        $new = cnido_pheno_category_normalize($old);
        if ($new !== $old) { $todo[$old] = array($new, (int)$r[1]); }
    }
    if (!$todo) { echo "  类别名无需规范化\n"; return 0; }
    $st = $conn->prepare("UPDATE phenotype_v2 SET trait_category = ?
                           WHERE CAST(trait_category AS BINARY) = CAST(? AS BINARY)");
    if ($st === false) { fwrite(STDERR, "prepare 失败: {$conn->error}\n"); exit(1); }
    foreach ($todo as $old => $v) {
        $st->bind_param('ss', $v[0], $old);
        $st->execute();
        printf("  %-16s -> %-16s %6d 行\n", $old, $v[0], $v[1]);
        $n += $st->affected_rows;
    }
    $st->close();
    return $n;
}

/* ---------------------------------------------------------------------
 * 4. 对账报告
 * ------------------------------------------------------------------- */
function pheno2_q($conn, $sql)
{
    $r = $conn->query($sql);
    if ($r === false) { return array(array('ERR: ' . $conn->error)); }
    $out = array();
    while ($x = $r->fetch_row()) { $out[] = $x; }
    return $out;
}

function pheno2_report($conn)
{
    echo "\n================ 新表 phenotype_v2 对账 ================\n";

    foreach (pheno2_q($conn, "SELECT source, COUNT(*) n, SUM(n_records) src_rows,
                                     COUNT(DISTINCT species) sp
                              FROM phenotype_v2 GROUP BY source ORDER BY n DESC") as $r) {
        printf("  %-24s 折叠后 %8s 行  ← 源记录 %9s 行  物种 %6s\n",
               $r[0], number_format((int)$r[1]), number_format((int)$r[2]), number_format((int)$r[3]));
    }

    foreach (pheno2_q($conn, "SELECT COUNT(*) rows_, SUM(n_records) src, COUNT(DISTINCT species) sp,
                                     COUNT(DISTINCT trait_name) tn, COUNT(DISTINCT trait_category) tc
                              FROM phenotype_v2") as $r) {
        printf("  合计 %s 行（源记录 %s）／ 物种 %s ／ trait 名 %s ／ 类别 %s\n",
               number_format((int)$r[0]), number_format((int)$r[1]),
               number_format((int)$r[2]), number_format((int)$r[3]), number_format((int)$r[4]));
    }

    echo "\n  --- 折叠了多少 ---\n";
    foreach (pheno2_q($conn, "SELECT LEAST(n_records,5) k, COUNT(*) rows_ FROM phenotype_v2
                              GROUP BY k ORDER BY k") as $r) {
        $k = (int)$r[0];
        printf("  n_records=%s%s : %s 行\n", $k, $k === 5 ? ' 及以上' : '', number_format((int)$r[1]));
    }

    echo "\n  --- 单位下的取值数：旧表的「一个单位一个值」是否已消失 ---\n";
    $units = array('cat', 'bin', 'm', 'cm', 'units', 'mm');
    foreach (pheno2_q($conn, "SELECT traitunit, COUNT(*) n, COUNT(DISTINCT value) v
                              FROM phenotype_v2 WHERE source='" . PHENO2_OCTD_SOURCE . "'
                              GROUP BY traitunit ORDER BY n DESC LIMIT 8") as $r) {
        printf("  %-14s %8s 行 / %6s 个不同取值\n", $r[0], number_format((int)$r[1]), number_format((int)$r[2]));
    }

    echo "\n  --- 旧表 vs 新表：Corallium rubrum 前 12 条 ---\n";
    echo "  旧（value 是编号）:\n";
    foreach (pheno2_q($conn, "SELECT trait_name, value, traitunit FROM phenotype
                              WHERE species='Corallium rubrum' LIMIT 12") as $r) {
        printf("    %-28s | %-10s | %s\n", $r[0], $r[1], $r[2]);
    }
    echo "  新（真值）:\n";
    foreach (pheno2_q($conn, "SELECT trait_name, value, traitunit, n_records FROM phenotype_v2
                              WHERE species='Corallium rubrum' LIMIT 12") as $r) {
        printf("    %-28s | %-10s | %-6s | x%s\n",
               $r[0], mb_substr($r[1], 0, 60), $r[2], $r[3]);
    }

    echo "\n  --- value_type 分布（源库自带的来源字段） ---\n";
    foreach (pheno2_q($conn, "SELECT value_type, COUNT(*) n FROM phenotype_v2
                              WHERE source='" . PHENO2_OCTD_SOURCE . "'
                              GROUP BY value_type ORDER BY n DESC") as $r) {
        printf("  %-16s %8s\n", $r[0] === '' ? '(空)' : $r[0], number_format((int)$r[1]));
    }

    echo "\n  --- 遗留行里还有多少行 value 长得像编号 ---\n";
    require_once '/var/www/html/CnidoSite/includes/phenotype_traits.php';
    $coded = pheno2_q($conn, "SELECT COUNT(*) FROM phenotype_v2 WHERE " . cnido_pheno_coded_sql());
    printf("  像编号的行: %s\n", number_format((int)$coded[0][0]));
}

/* ---------------------------------------------------------------------
 * main
 * ------------------------------------------------------------------- */
if ($opts['report']) {
    pheno2_report($conn);
    exit(0);
}

if (!$opts['apply']) {
    echo "干跑（不写库）。检查源文件与表结构……\n";
    $fh = fopen($opts['csv'], 'r');
    $hdr = fgetcsv($fh, 0, ',');
    $hdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$hdr[0]);
    $hdr = array_map(function ($x) { return trim((string)$x, '"'); }, $hdr);
    fclose($fh);
    echo '  源文件 ' . number_format(filesize($opts['csv'])) . " 字节，列：" . implode(', ', array_slice($hdr, 0, 12)) . " …\n";
    foreach (array('observation_id', 'specie_name', 'trait_name', 'trait_category',
                   'standard_unit', 'value', 'value_type', 'location_name', 'latitude',
                   'longitude', 'methodology_name', 'resource_id') as $c) {
        printf("  需要列 %-18s %s\n", $c, in_array($c, $hdr, true) ? 'OK' : '**缺**');
    }
    foreach (pheno2_q($conn, "SELECT COALESCE(NULLIF(TRIM(Class),''),'(空)') c, COUNT(*) n
                              FROM phenotype GROUP BY c ORDER BY n DESC") as $r) {
        printf("  旧表 %-14s %s 行\n", $r[0], number_format((int)$r[1]));
    }
    echo "\n照此执行：Octocorallia 那批整块由 OCTD 重建，其余原样搬为遗留行，" .
         "内容相同的行折叠并记 n_records。\n--apply 才会写库（只建 phenotype_v2*，不碰 phenotype）。\n";
    exit(0);
}

echo "== 开始重建 ==\n";
pheno2_ddl($conn);
$n1 = pheno2_load_octd($conn, $opts['csv']);
$n2 = pheno2_load_legacy($conn);
printf("  遗留行搬入 %s 行\n", number_format($n2));
$n3 = pheno2_dedupe($conn);
printf("  折叠后写入 phenotype_v2 %s 行（中转表 %s 行）\n", number_format($n3), number_format($n1 + $n2));
pheno2_normalize_categories($conn);

if (!$opts['keep-raw']) {
    $conn->query('DROP TABLE IF EXISTS phenotype_v2_raw');
    echo "  中转表已删除（--keep-raw 可保留）\n";
}

pheno2_report($conn);
echo "\n完成。原表 phenotype 未改动；确认无误后切换：\n" .
     "  RENAME TABLE phenotype TO phenotype_old_20260927, phenotype_v2 TO phenotype;\n";
