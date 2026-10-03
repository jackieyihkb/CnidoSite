<?php
/* =====================================================================
 * pheno_v2_verify.php —— 独立核对主表（只读）。表名 2026-09-27 换表后是
 * phenotype；脚本名里的 v2 是历史叫法，保留以免命令与文档对不上。
 *
 * 不复用 build 脚本的任何逻辑：直接从 OCTD 的 CSV 重算「某物种的
 * (trait, unit, value) 元组集合」，再跟表里同物种的元组集合比。
 * 集合相等才算过 —— 折叠行会让新表的行数少于源行数，但【去重后的元组数】
 * 必须一模一样，少一个就说明丢数据了。
 *
 * 用法：php pheno_v2_verify.php [物种名 …]
 * ===================================================================== */
$csv = '/tmp/octd_check/OctocoralTraits/OctocoralTraits_v2_2.csv';
$want = array_slice($argv, 1);
if (!$want) { $want = array('Corallium rubrum', 'Antillogorgia elisabethae', 'Aurelia aurita'); }
$wantSet = array();
foreach ($want as $w) { $wantSet[strtolower($w)] = $w; }

$src = @file_get_contents('/var/www/html/CnidoSite/includes/state.php');
preg_match("/\\\$conn = @new mysqli\('([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)'\)/", $src, $m);
$conn = @new mysqli($m[1], $m[2], $m[3], $m[4]);
if ($conn->connect_errno) { fwrite(STDERR, "连接失败\n"); exit(2); }
$conn->set_charset('utf8mb4');

$fh = fopen($csv, 'r');
$hdr = fgetcsv($fh, 0, ',');
$hdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$hdr[0]);
$hdr = array_map(function ($x) { return trim((string)$x, '"'); }, $hdr);
$ix = array_flip($hdr);
$cs = array();   // 小写物种名 => set(元组 => 次数)
while (($r = fgetcsv($fh, 0, ',')) !== false) {
    if (count($r) < 20) { continue; }
    $sp = strtolower(trim((string)$r[$ix['specie_name']]));
    if (!isset($wantSet[$sp])) { continue; }
    $t = implode("\x1f", array(trim((string)$r[$ix['trait_name']]),
                              trim((string)$r[$ix['trait_category']]),
                              trim((string)$r[$ix['standard_unit']]),
                              trim((string)$r[$ix['value']]),
                              trim((string)$r[$ix['location_name']]),
                              trim((string)$r[$ix['latitude']]),
                              trim((string)$r[$ix['longitude']]),
                              trim((string)$r[$ix['methodology_name']]),
                              trim((string)$r[$ix['value_type']])));
    if (!isset($cs[$sp])) { $cs[$sp] = array(); }
    $cs[$sp][$t] = (isset($cs[$sp][$t]) ? $cs[$sp][$t] : 0) + 1;
}
fclose($fh);

$fail = 0;
foreach ($wantSet as $low => $orig) {
    if (!isset($cs[$low])) { printf("%-28s 源库里没有这个物种，跳过\n", $orig); continue; }
    $srcRows = array_sum($cs[$low]);
    $srcTuples = count($cs[$low]);

    $q = $conn->query("SELECT trait_name, trait_category, traitunit, value, COALESCE(region,''),
                              COALESCE(latitude,''), COALESCE(longitude,''), COALESCE(methodology,''),
                              COALESCE(value_type,''), SUM(n_records)
                       FROM phenotype WHERE species='" . $conn->real_escape_string($orig) . "'
                         AND source='OCTD v2.2' GROUP BY 1,2,3,4,5,6,7,8,9");
    $dbTuple = array(); $dbRows = 0;
    while ($x = $q->fetch_row()) {
        $t = implode("\x1f", array_values(array_slice($x, 0, 9)));
        $dbTuple[$t] = (int)$x[9];
        $dbRows += (int)$x[9];
    }
    $missing = array_diff_key($cs[$low], $dbTuple);
    $extra   = array_diff_key($dbTuple, $cs[$low]);
    $ok = (!$missing && !$extra && $srcRows === $dbRows && $srcTuples === count($dbTuple));
    if (!$ok) { $fail++; }
    printf("%-28s 源 %6s 行/%5s 元组   表 %6s 行/%5s 元组   %s\n",
           $orig, number_format($srcRows), number_format($srcTuples),
           number_format($dbRows), number_format(count($dbTuple)), $ok ? '一致' : '**不一致**');
    foreach (array_slice($missing, 0, 3, true) as $t => $n) {
        printf("     源有表无 (x%s): %s\n", $n, str_replace("\x1f", ' | ', $t));
    }
    foreach (array_slice($extra, 0, 3, true) as $t => $n) {
        printf("     表有源无 (x%s): %s\n", $n, str_replace("\x1f", ' | ', $t));
    }
}

/* 折叠前后总账：n_records 之和必须等于源记录数，否则就是插漏/插重了。 */
foreach (array('OCTD v2.2' => 148492, 'legacy (unattributed)' => 2554) as $s => $expect) {
    $r = $conn->query("SELECT SUM(n_records) FROM phenotype WHERE source='" . $s . "'")->fetch_row();
    $got = (int)$r[0];
    printf("%-24s n_records 之和 %8s / 预期 %8s  %s\n", $s, number_format($got), number_format($expect),
           $got === $expect ? '对' : '**对不上**');
    if ($got !== $expect) { $fail++; }
}

echo $fail ? "\n有 {$fail} 项对不上。\n" : "\n全部一致。\n";
exit($fail ? 1 : 0);
