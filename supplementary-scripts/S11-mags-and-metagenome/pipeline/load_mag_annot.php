<?php
/* =====================================================================
 * 把 NCBI 的蛋白组 FASTA 灌进 mag_annot（一个蛋白一行）
 *
 * 表结构见 /tmp/magbuild/mk_tables2.php 的说明：键是**蛋白号**（不是 locus_tag，
 * 因为只有约 15% 的 header 带 locus_tag）。
 *
 * header 形态（Acropora cytherea 的 14 个 MAG 实测，59,830 条）：
 *   >MBO9500080.1 hypothetical protein J7346_00005, partial [Brevundimonas sp. A19_0]
 *   >MBO9395042.1 SDR family oxidoreductase
 * 即  >{蛋白号} {功能描述}[ {locus_tag}][, partial] [{微生物名}]
 *
 * 用法：
 *   php load_mag_annot.php --mag <AssemblyAccession> --faa <file.faa> [--apply]
 * 不带 --apply 只统计不写库（写盘必须压在 --apply 后面，见 memory:
 * cnidosite-regex-sweeps-need-gating）。
 * ===================================================================== */
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
$faa   = isset($args['faa']) ? $args['faa'] : '';
$apply = isset($args['apply']);
if ($mag === '' || $faa === '' || !is_file($faa)) {
    fwrite(STDERR, "usage: php load_mag_annot.php --mag <acc> --faa <file.faa> [--apply]\n");
    exit(2);
}

/* ---------- 解析 FASTA header ---------- */
$rows = array();
$fh = fopen($faa, 'r');
while (($line = fgets($fh)) !== false) {
    if ($line === '' || $line[0] !== '>') { continue; }
    $line = rtrim($line, "\r\n");
    $sp = strpos($line, ' ');
    if ($sp === false) { $acc = substr($line, 1); $rest = ''; }
    else { $acc = substr($line, 1, $sp - 1); $rest = trim(substr($line, $sp + 1)); }

    /* 末尾的 [微生物名] 去掉（它等于 MAGs.Species，不属功能描述） */
    $rest = preg_replace('/\s*\[[^\]]*\]\s*$/', '', $rest);

    /* locus_tag：形如 J7346_00005 的独立 token */
    $locus = '';
    if (preg_match('/(?:^|\s)([A-Za-z][A-Za-z0-9]*_\d+)(?=$|\s|,)/', $rest, $m, PREG_OFFSET_CAPTURE)) {
        $locus = $m[1][0];
        $rest = trim(substr($rest, 0, $m[1][1]) . substr($rest, $m[1][1] + strlen($locus)));
    }
    /* 去掉残留的 ", partial" / "partial" */
    $rest = preg_replace('/,?\s*partial\s*$/', '', $rest);
    $desc = trim($rest, " ,\t");

    if ($acc === '') { continue; }
    $rows[] = array($acc, $locus, $desc);
}
fclose($fh);

$n = count($rows);
$withLocus = 0; $dupe = array(); $seen = array();
foreach ($rows as $r) {
    if ($r[1] !== '') { $withLocus++; }
    if (isset($seen[$r[0]])) { $dupe[$r[0]] = true; }
    $seen[$r[0]] = true;
}
printf("mag=%s  proteins=%d  with_locus_tag=%d (%.1f%%)  duplicate_accessions=%d\n",
       $mag, $n, $withLocus, $n ? 100.0 * $withLocus / $n : 0, count($dupe));
if ($dupe) { fwrite(STDERR, "ABORT: duplicated protein accessions: " . implode(',', array_slice(array_keys($dupe), 0, 5)) . "\n"); exit(1); }

if (!$apply) { echo "DRY RUN (nothing written; pass --apply)\n"; exit(0); }

/* ---------- 写库 ---------- */
$conn = new mysqli('localhost', 'jackie', '<REDACTED>', 'cnidaria');
if ($conn->connect_error) { fwrite(STDERR, "DB: " . $conn->connect_error . "\n"); exit(1); }
$conn->set_charset('utf8mb4');

$magEsc = mysqli_real_escape_string($conn, $mag);
if (!$conn->query("DELETE FROM mag_annot WHERE mag = '$magEsc'")) {
    fwrite(STDERR, "DELETE failed: " . $conn->error . "\n"); exit(1);
}

$BATCH = 500;
$done = 0;
for ($i = 0; $i < $n; $i += $BATCH) {
    $vals = array();
    for ($j = $i; $j < min($i + $BATCH, $n); $j++) {
        $r = $rows[$j];
        $vals[] = "('" . $magEsc . "','"
                . mysqli_real_escape_string($conn, $r[0]) . "','"
                . mysqli_real_escape_string($conn, $r[1]) . "','"
                . mysqli_real_escape_string($conn, $r[2]) . "')";
    }
    $sql = "INSERT INTO mag_annot (mag, protein, locus_tag, description) VALUES " . implode(',', $vals);
    if (!$conn->query($sql)) { fwrite(STDERR, "INSERT failed at $i: " . $conn->error . "\n"); exit(1); }
    $done += count($vals);
}
$r = $conn->query("SELECT COUNT(*) FROM mag_annot WHERE mag = '$magEsc'")->fetch_row();
printf("WROTE %d rows; table now has %d rows for %s\n", $done, $r[0], $mag);
$conn->close();
