<?php
/* =====================================================================
 * 把 InterProScan 的 TSV 灌进 mag_interpro / mag_go_terms /
 * mag_pfam_hits / mag_panther_hits
 *
 * 表名不是 mag_ipr / mag_go —— 站内好几处按「表名 = <ABBR>_<后缀>」的模式枚举
 * 注释表（includes/coverage.php 的 $tblBySuffix、includes/domain_search.php 的
 * ds_species_list()），叫 mag_ipr/mag_go 会让 315 个 MAG 的共享表被当成某个
 * 物种的表，覆盖矩阵里会多出一个幽灵物种「MAG」。详见 MAG_detail.php 里的注释。
 *
 * InterProScan 的 TSV 是 15 列（1-based）：
 *   1 protein  2 md5  3 len  4 analysis  5 sig_acc  6 sig_desc  7 start  8 stop
 *   9 score  10 status  11 date  12 InterPro_acc  13 InterPro_desc  14 GO  15 pathway
 * 其中 12/13/14 只在该行真的匹配到 InterPro 条目时才有值（12 为 '-' 表示只有
 * member DB 命中、没有 InterPro 整合条目）。
 *
 * 三处字典要外部补（见 build_annot_ref.py）：
 *   - InterPro 条目的 Type（Domain/Family/Repeat/…）TSV 里没有
 *   - GO 的 Category 与名称 TSV 里只有号
 *   - Pfam 的 short name 与 Type：TSV 第 6 列只有 description，没有 `LRR_8`
 *     这种短名，也没有 Domain/Family/Repeat 的分类 → ref/pfam_name.tsv
 *
 * **Pfam / PANTHER 也各存一份**：站内的功能词汇不止 ipr/go/KEGG —— 148 个物种
 * 都有 `_pfam` 与 `_panther` 表。这两类命中本来就在同一份 TSV 里（第 4 列是
 * 'Pfam' / 'PANTHER'），不存就是白跑。口径与站内一致：存**全部 member DB 命中**，
 * 不按「有没有整合进 InterPro 条目」过滤（实测 chunk_00 里 Pfam 有 276 行、
 * PANTHER 有 1,310 行的第 12 列是 '-'，过滤掉就少注释）。
 * 表名带 `_hits` 后缀：`_pfam` / `_panther` 都在保留后缀表上，叫 mag_pfam /
 * mag_panther 会再造一个幽灵物种。
 *
 * **按 (mag, protein, term) 去重**：站内 148 个物种的表是「一个 member DB 命中
 * 一行」，同一个 IPR 重复 5 行很常见（NVECT_ipr 39 万行），消费端一律
 * SELECT DISTINCT。MAG 这边不去重的话 315 个组装会白涨几千万行，而且本页是
 * 逐行渲染的、不去重就会在单元格里重复显示同一个条目。表上已建 UNIQUE 键，
 * 这里用 INSERT IGNORE，重复跑同一份输入是幂等的。
 *
 * 用法：
 *   php load_iprscan.php --mag <AssemblyAccession> --tsv <iprscan.tsv> [--apply]
 * 不带 --apply 只统计不写库。
 * ===================================================================== */
ini_set('memory_limit', '1024M');

$args = array();
$av = array_slice($argv, 1);
for ($i = 0; $i < count($av); $i++) {
    $a = $av[$i];
    if (!preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { continue; }
    if (isset($m[2])) { $args[$m[1]] = $m[2]; }
    elseif (isset($av[$i + 1]) && substr($av[$i + 1], 0, 2) !== '--') { $args[$m[1]] = $av[++$i]; }
    else { $args[$m[1]] = true; }
}
$mag   = isset($args['mag']) ? $args['mag'] : '';
$tsv   = isset($args['tsv']) ? $args['tsv'] : '';
$ref   = isset($args['ref']) ? rtrim($args['ref'], '/') : __DIR__ . '/ref';
$apply = isset($args['apply']);
if ($mag === '' || $tsv === '' || !is_file($tsv)) {
    fwrite(STDERR, "usage: php load_iprscan.php --mag <acc> --tsv <file.tsv> [--ref DIR] [--apply]\n");
    exit(2);
}

/* ---------- 字典 ---------- */
function load_map($path, $ncol)
{
    $out = array();
    if (!is_file($path)) { fwrite(STDERR, "WARN: missing dictionary $path\n"); return $out; }
    $fh = fopen($path, 'r');
    while (($l = fgets($fh)) !== false) {
        $f = explode("\t", rtrim($l, "\r\n"));
        if (count($f) >= $ncol) { $out[$f[0]] = array_slice($f, 1, $ncol - 1); }
    }
    fclose($fh);
    return $out;
}
$IPR = load_map($ref . '/ipr_type.tsv', 3);   // IPR  -> [Type, Name]
$GO  = load_map($ref . '/go_term.tsv',  3);   // GO   -> [Category, Name]
$PF  = load_map($ref . '/pfam_name.tsv', 4);  // PF   -> [short name, Description, Type]

/* ---------- 解析 TSV ---------- */
$IPR_SRC = 'Interproscan';
$IPR_URL = 'https://interproscan-docs.readthedocs.io/en/v5/';

$ipr = array();   // "protein\0IPR" => array(ipr, desc)
$go  = array();   // "protein\0GO"  => array(go)
$pf  = array();   // "protein\0PF"  => array(pf, desc)
$pt  = array();   // "protein\0PTHR"=> array(pthr, anno)
$proteins = array();
$nLine = 0; $nSkip = 0; $nIprRow = 0; $nGoRow = 0; $nPfRow = 0; $nPtRow = 0;
$iprUnknown = array(); $goUnknown = array(); $pfUnknown = array();
$noIprProteins = 0;

$fh = fopen($tsv, 'r');
while (($line = fgets($fh)) !== false) {
    $line = rtrim($line, "\r\n");
    if ($line === '') { continue; }
    if ($line[0] === '#') { continue; }        // 注释/表头
    $nLine++;
    $c = explode("\t", $line);
    if (count($c) < 13) { $nSkip++; continue; }   // 连 InterPro 列都没有 → 不是 15 列格式
    $p = $c[0];
    if ($p === '') { $nSkip++; continue; }
    $proteins[$p] = true;

    /* InterPro 整合条目 */
    $acc = isset($c[11]) ? trim($c[11]) : '';
    if (preg_match('/^IPR\d{6}$/', $acc)) {
        $k = $p . "\0" . $acc;
        if (!isset($ipr[$k])) {
            $ipr[$k] = array($acc, isset($c[12]) ? trim($c[12]) : '');
            $nIprRow++;
        }
        if (!isset($IPR[$acc])) { $iprUnknown[$acc] = true; }
    }
    /* GO（第 14 列，| 分隔，可能为空）
       **必须先剥掉号后面的 (InterPro) / (PANTHER) 断言来源**：5.78 的 TSV 写出器
       固定拼成 `getIdentifier() + GoXrefSources.toString()`，那个 toString 就是
       "(InterPro)" / "(PANTHER)" / "(InterPro,PANTHER)"（javap 反编译实见），
       不是哪个开关没设对，关不掉。不剥的话正则一条都匹配不上 —— 实测 chunk_00
       里 17,085 行有 GO 却全部被丢，库里 GO 会是**零行**而且不报错。 */
    if (isset($c[13]) && $c[13] !== '' && $c[13] !== '-') {
        foreach (explode('|', $c[13]) as $g) {
            $g = trim($g);
            $q = strpos($g, '(');
            if ($q !== false) { $g = substr($g, 0, $q); }
            $g = trim($g);
            if (!preg_match('/^GO:\d{7}$/', $g)) { continue; }
            $k = $p . "\0" . $g;
            if (!isset($go[$k])) { $go[$k] = array($g); $nGoRow++; }
            if (!isset($GO[$g])) { $goUnknown[$g] = true; }
        }
    }
    /* Pfam / PANTHER member DB 命中（第 4 列是 analysis 名）—— 这两类不依赖
       InterPro 整合，第 12 列为 '-' 时照样要存。 */
    $an = isset($c[3]) ? trim($c[3]) : '';
    $sg = isset($c[4]) ? trim($c[4]) : '';
    $sd = isset($c[5]) ? trim($c[5]) : '';
    if ($an === 'Pfam' && preg_match('/^PF\d{5}$/', $sg)) {
        $k = $p . "\0" . $sg;
        if (!isset($pf[$k])) { $pf[$k] = array($sg, $sd); $nPfRow++; }
        if (!isset($PF[$sg])) { $pfUnknown[$sg] = true; }
    } elseif ($an === 'PANTHER' && preg_match('/^PTHR\d{5}$/', $sg)) {
        $k = $p . "\0" . $sg;
        if (!isset($pt[$k])) { $pt[$k] = array($sg, $sd); $nPtRow++; }
    }
}
fclose($fh);

/* 有多少蛋白一个 InterPro 整合条目都没有（这在 MAG 里很常见：大量 hypothetical
   protein）—— 它们仍然要有一行 gene，只是三列签名是「—」。 */
$withIpr = array();
foreach (array_keys($ipr) as $k) { $withIpr[substr($k, 0, strpos($k, "\0"))] = true; }
$noIprProteins = count($proteins) - count($withIpr);

printf("mag=%s\n", $mag);
printf("  tsv lines read      %d   (skipped malformed: %d)\n", $nLine, $nSkip);
printf("  proteins in tsv     %d   (no InterPro entry at all: %d)\n", count($proteins), $noIprProteins);
printf("  InterPro rows       %d   unique (protein,IPR)   [unknown IPR in dict: %d]\n", $nIprRow, count($iprUnknown));
printf("  GO rows             %d   unique (protein,GO)    [unknown GO in dict: %d]\n", $nGoRow, count($goUnknown));
printf("  Pfam rows           %d   unique (protein,PF)    [PF not in dict: %d]\n", $nPfRow, count($pfUnknown));
printf("  PANTHER rows        %d   unique (protein,PTHR)\n", $nPtRow);
if ($iprUnknown) { fwrite(STDERR, "  NOTE: IPR not in entry.list (version skew): " . implode(',', array_slice(array_keys($iprUnknown), 0, 8)) . "\n"); }
if ($goUnknown)  { fwrite(STDERR, "  NOTE: GO not in go-basic.obo (obsolete/alt id): " . implode(',', array_slice(array_keys($goUnknown), 0, 8)) . "\n"); }
if ($pfUnknown)  { fwrite(STDERR, "  NOTE: PF not in pfam_a.dat (version skew): " . implode(',', array_slice(array_keys($pfUnknown), 0, 8)) . "\n"); }

if (!$apply) { echo "DRY RUN (nothing written; pass --apply)\n"; exit(0); }

/* ---------- 写库 ---------- */
$conn = new mysqli('localhost', 'jackie', '<REDACTED>', 'cnidaria');
if ($conn->connect_error) { fwrite(STDERR, "DB: " . $conn->connect_error . "\n"); exit(1); }
$conn->set_charset('utf8mb4');
$magEsc = mysqli_real_escape_string($conn, $mag);

/* 先确认这个 MAG 的基因清单已经灌过 —— 没有 mag_annot 行的话，注释行在详情页
   是看不见的（页面以 mag_annot 为主表），那灌了也白灌。 */
$chk = $conn->query("SELECT COUNT(*) FROM mag_annot WHERE mag = '$magEsc'")->fetch_row();
if ((int)$chk[0] === 0) {
    fwrite(STDERR, "ABORT: mag_annot has no rows for $mag — run load_mag_annot.php first\n");
    exit(1);
}
printf("  mag_annot rows      %d (inventory present)\n", $chk[0]);

/* 先清掉这个 MAG 的旧签名再灌 —— 只动这一个 mag，别的 MAG 不受影响。
   必须清：只靠 UNIQUE 键 + INSERT IGNORE 的话，改对了之后重跑不会更新既有行
   （唯一键命中就跳过了），错误的旧行会一直留在库里。实测踩过：
   Pathway/Pathway_ID 写反了以后重跑，页面仍旧是错的。 */
foreach (array('mag_interpro', 'mag_go_terms', 'mag_pfam_hits', 'mag_panther_hits') as $t) {
    $old = $conn->query("SELECT COUNT(*) FROM $t WHERE mag = '$magEsc'")->fetch_row();
    if (!$conn->query("DELETE FROM $t WHERE mag = '$magEsc'")) {
        fwrite(STDERR, "DELETE from $t failed: " . $conn->error . "\n"); exit(1);
    }
    printf("  cleared %-14s %d old rows\n", $t, $old[0]);
}

$BATCH = 500;
function flush_rows($conn, $sql_head, $vals, $table)
{
    if (!$vals) { return 0; }
    $sql = $sql_head . implode(',', $vals);
    if (!$conn->query($sql)) {
        fwrite(STDERR, "INSERT into $table failed: " . $conn->error . "\n");
        exit(1);
    }
    return count($vals);
}

/* InterPro */
$w = 0; $vals = array();
foreach ($ipr as $k => $v) {
    $pos = strpos($k, "\0");
    $p = substr($k, 0, $pos); $acc = $v[0];
    $type = isset($IPR[$acc]) ? $IPR[$acc][0] : '';
    $vals[] = "('" . $magEsc . "','" . mysqli_real_escape_string($conn, $p) . "','"
            . mysqli_real_escape_string($conn, $acc) . "','"
            . mysqli_real_escape_string($conn, $type) . "','"
            . mysqli_real_escape_string($conn, $v[1]) . "','"
            . $IPR_SRC . "','" . $IPR_URL . "')";
    if (count($vals) >= $BATCH) {
        $w += flush_rows($conn, "INSERT IGNORE INTO mag_interpro (mag, protein, InterPro_term, Type, Description, Source, URL) VALUES ", $vals, 'mag_interpro');
        $vals = array();
    }
}
$w += flush_rows($conn, "INSERT IGNORE INTO mag_interpro (mag, protein, InterPro_term, Type, Description, Source, URL) VALUES ", $vals, 'mag_interpro');
printf("  wrote mag_interpro  %d\n", $w);

/* GO */
$w = 0; $vals = array();
foreach ($go as $k => $v) {
    $pos = strpos($k, "\0");
    $p = substr($k, 0, $pos); $g = $v[0];
    $cat = isset($GO[$g]) ? $GO[$g][0] : '';
    $nm  = isset($GO[$g]) ? $GO[$g][1] : '';
    $vals[] = "('" . $magEsc . "','" . mysqli_real_escape_string($conn, $p) . "','"
            . mysqli_real_escape_string($conn, $g) . "','"
            . mysqli_real_escape_string($conn, $cat) . "','"
            . mysqli_real_escape_string($conn, $nm) . "','"
            . $IPR_SRC . "','" . $IPR_URL . "')";
    if (count($vals) >= $BATCH) {
        $w += flush_rows($conn, "INSERT IGNORE INTO mag_go_terms (mag, protein, GO_term, Category, Description, Source, URL) VALUES ", $vals, 'mag_go_terms');
        $vals = array();
    }
}
$w += flush_rows($conn, "INSERT IGNORE INTO mag_go_terms (mag, protein, GO_term, Category, Description, Source, URL) VALUES ", $vals, 'mag_go_terms');
printf("  wrote mag_go_terms  %d\n", $w);

/* Pfam —— Pfam_name / Type 从字典补（TSV 第 6 列只有 description）。
   查不到字典时 Pfam_name/Type 留空、description 仍照存：宁可少两列也别丢命中。 */
$w = 0; $vals = array();
foreach ($pf as $k => $v) {
    $pos = strpos($k, "\0");
    $p = substr($k, 0, $pos); $acc = $v[0];
    $nm   = isset($PF[$acc]) ? $PF[$acc][0] : '';
    $desc = isset($PF[$acc]) ? $PF[$acc][1] : $v[1];
    $type = isset($PF[$acc]) ? $PF[$acc][2] : '';
    $vals[] = "('" . $magEsc . "','" . mysqli_real_escape_string($conn, $p) . "','"
            . mysqli_real_escape_string($conn, $acc) . "','"
            . mysqli_real_escape_string($conn, $nm) . "','"
            . mysqli_real_escape_string($conn, $desc) . "','"
            . mysqli_real_escape_string($conn, $type) . "','"
            . $IPR_SRC . "','" . $IPR_URL . "')";
    if (count($vals) >= $BATCH) {
        $w += flush_rows($conn, "INSERT IGNORE INTO mag_pfam_hits (mag, protein, Pfam_accession, Pfam_name, Description, Type, Source, URL) VALUES ", $vals, 'mag_pfam_hits');
        $vals = array();
    }
}
$w += flush_rows($conn, "INSERT IGNORE INTO mag_pfam_hits (mag, protein, Pfam_accession, Pfam_name, Description, Type, Source, URL) VALUES ", $vals, 'mag_pfam_hits');
printf("  wrote mag_pfam_hits %d\n", $w);

/* PANTHER —— `anno` 是 TSV 第 6 列（全大写的家族名）。站内 NVECT_panther 在
   没名字时存的是 '-'，这里保持一致（空值写 '-'，别留空串）。 */
$w = 0; $vals = array();
foreach ($pt as $k => $v) {
    $pos = strpos($k, "\0");
    $p = substr($k, 0, $pos); $acc = $v[0];
    $anno = ($v[1] === '') ? '-' : $v[1];
    $vals[] = "('" . $magEsc . "','" . mysqli_real_escape_string($conn, $p) . "','"
            . mysqli_real_escape_string($conn, $acc) . "','"
            . mysqli_real_escape_string($conn, $anno) . "','"
            . $IPR_SRC . "','" . $IPR_URL . "')";
    if (count($vals) >= $BATCH) {
        $w += flush_rows($conn, "INSERT IGNORE INTO mag_panther_hits (mag, protein, id, anno, method, url) VALUES ", $vals, 'mag_panther_hits');
        $vals = array();
    }
}
$w += flush_rows($conn, "INSERT IGNORE INTO mag_panther_hits (mag, protein, id, anno, method, url) VALUES ", $vals, 'mag_panther_hits');
printf("  wrote mag_panther_hits %d\n", $w);

foreach (array('mag_interpro', 'mag_go_terms', 'mag_pfam_hits', 'mag_panther_hits') as $t) {
    $r = $conn->query("SELECT COUNT(*) FROM $t WHERE mag = '$magEsc'")->fetch_row();
    printf("  table now has %d rows for %s in %s\n", $r[0], $mag, $t);
}
$conn->close();
