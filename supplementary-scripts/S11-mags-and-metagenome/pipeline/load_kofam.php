<?php
/* =====================================================================
 * 把 KofamScan（exec_annotation）的输出灌进 mag_kegg_terms
 *
 * 表名不是 mag_kegg —— 见 load_iprscan.php 顶部与 MAG_detail.php 的注释：
 * 站内按「表名 = <ABBR>_<后缀>」枚举注释表，mag_kegg 会被当成某个物种的
 * KEGG 表（coverage.php 的 $tblBySuffix 后缀是 'kegg'，ds_species_list 用
 * '%\_KEGG' 且 information_schema 的表名比较不区分大小写）。
 *
 * KOfamScan 只给「蛋白 → KO」（`-f detail-tsv` 是 gene/KO/threshold/score/type
 * 五列，`-f mapper` 是两列），Abbreviation / Enzymes / Enzyme_ID / Pathway /
 * Pathway_ID 五列都要外部补，来自 ko_ref.tsv（由 build_ko_ref.py 生成）：
 *   Abbreviation = KEGG list/ko 名字里 '; ' 之前那半（基因符号）
 *   Enzymes      = '; ' 之后那半（KO 定义；站内这一列的列名与内容不符，是历史遗留）
 *   Enzyme_ID    = 定义里 [EC:...] 抽出的 EC 号
 *   Pathway/Pathway_ID = ko00001 BRITE 里该 KO 的**最后一个** C 节点
 *
 * 那个「最后一个 C 节点」的规则不是拍脑袋：拿站内 NVECT_KEGG 的 5968 个
 * KO 反推过（/tmp/magbuild/brite_rule2.py），它复现了 97.7%（按 ID 比对）的
 * 既有取值，是所有候选规则里最高的；其余 2.3% 是 KEGG 版本漂移（同一个 KO
 * 在 C 节点之间挪过位置）。另有 5 条 pathway 被 KEGG 改过名（如 ko04142
 * "Lysosome" → "Lysosome biogenesis"），这里存当前名。
 * 副作用要知道：KEGG 的 BRITE 分类里 K00844（己糖激酶）落在 ko04131
 * Membrane trafficking（KEGG 自己把它列在 mitophagy 那一块），所以 MBO 的
 * 己糖激酶会显示 Membrane trafficking 而不是 Glycolysis —— 这是 KEGG 自己的
 * 归类，不是我们的选择，但也说明这一列**不是**「主通路」。
 *
 * 用法：
 *   php load_kofam.php --mag <AssemblyAccession> --out <kofam.tsv> [--koref FILE] [--apply]
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
$out   = isset($args['out']) ? $args['out'] : '';
$koref = isset($args['koref']) ? $args['koref'] : '/mnt/sda/jackie/tools/kofam-db/ko_ref.tsv';
$apply = isset($args['apply']);
if ($mag === '' || $out === '' || !is_file($out)) {
    fwrite(STDERR, "usage: php load_kofam.php --mag <acc> --out <file.tsv> [--koref FILE] [--apply]\n");
    exit(2);
}
if (!is_file($koref)) {
    fwrite(STDERR, "ABORT: ko reference not found: $koref (run build_ko_ref.py)\n");
    exit(1);
}

/* ---------- KO 字典 ---------- */
$KO = array();
$fh = fopen($koref, 'r');
while (($l = fgets($fh)) !== false) {
    $f = explode("\t", rtrim($l, "\r\n"));
    if (count($f) >= 6 && preg_match('/^K\d{5}$/', $f[0])) {
        $KO[$f[0]] = array($f[1], $f[2], $f[3], $f[4], $f[5]);
    }
}
fclose($fh);
printf("ko_ref: %d KOs loaded from %s\n", count($KO), $koref);

/* ---------- 解析 KofamScan 输出 ---------- */
/* 兼容三种格式，靠「第 2 列是不是 K 号」判断，不赌具体 -f：
 *   mapper / mapper-tab : gene \t KO
 *   detail-tsv          : gene \t KO \t threshold \t score \t score_type
 * 行首 '#' 是 KOfamScan 的注释/表头。 */
$hits = array();          // "protein\0KO" => true
$proteins = array();
$nLine = 0; $nSkip = 0; $koUnknown = array();
$fh = fopen($out, 'r');
while (($line = fgets($fh)) !== false) {
    $line = rtrim($line, "\r\n");
    if ($line === '' || $line[0] === '#') { continue; }
    $nLine++;
    $c = preg_split('/\t+/', $line);
    if (count($c) < 2) { $nSkip++; continue; }
    $p  = trim($c[0]);
    $ko = trim($c[1]);
    if ($p === '' || !preg_match('/^K\d{5}$/', $ko)) { $nSkip++; continue; }
    $k = $p . "\0" . $ko;
    if (!isset($hits[$k])) { $hits[$k] = true; }
    $proteins[$p] = true;
    if (!isset($KO[$ko])) { $koUnknown[$ko] = true; }
}
fclose($fh);

printf("mag=%s\n", $mag);
printf("  lines read          %d   (skipped malformed: %d)\n", $nLine, $nSkip);
printf("  proteins with a KO  %d\n", count($proteins));
printf("  unique (protein,KO) %d   [KO not in ko_ref: %d]\n", count($hits), count($koUnknown));
if ($koUnknown) { fwrite(STDERR, "  NOTE: KO missing from ko_ref: " . implode(',', array_slice(array_keys($koUnknown), 0, 8)) . "\n"); }

if (!$apply) { echo "DRY RUN (nothing written; pass --apply)\n"; exit(0); }

/* ---------- 写库 ---------- */
$conn = new mysqli('localhost', 'jackie', '<REDACTED>', 'cnidaria');
if ($conn->connect_error) { fwrite(STDERR, "DB: " . $conn->connect_error . "\n"); exit(1); }
$conn->set_charset('utf8mb4');
$magEsc = mysqli_real_escape_string($conn, $mag);

$chk = $conn->query("SELECT COUNT(*) FROM mag_annot WHERE mag = '$magEsc'")->fetch_row();
if ((int)$chk[0] === 0) {
    fwrite(STDERR, "ABORT: mag_annot has no rows for $mag — run load_mag_annot.php first\n");
    exit(1);
}
printf("  mag_annot rows      %d (inventory present)\n", $chk[0]);

/* 先清掉这个 MAG 的旧命中再灌（只动这一个 mag）。必须清：只靠 UNIQUE 键 +
   INSERT IGNORE 的话，改对了之后重跑不会更新既有行，错误的旧行会一直在库里。 */
$old = $conn->query("SELECT COUNT(*) FROM mag_kegg_terms WHERE mag = '$magEsc'")->fetch_row();
if (!$conn->query("DELETE FROM mag_kegg_terms WHERE mag = '$magEsc'")) {
    fwrite(STDERR, "DELETE from mag_kegg_terms failed: " . $conn->error . "\n"); exit(1);
}
printf("  cleared mag_kegg_terms %d old rows\n", $old[0]);

$BATCH = 500;
$vals = array(); $w = 0;
$HEAD = "INSERT IGNORE INTO mag_kegg_terms (mag, protein, KO, Abbreviation, Enzymes, Enzyme_ID, Pathway, Pathway_ID, Source) VALUES ";
foreach ($hits as $k => $_) {
    $pos = strpos($k, "\0");
    $p  = substr($k, 0, $pos);
    $ko = substr($k, $pos + 1);
    $d  = $KO[$ko];   // [Abbreviation, Enzymes, Enzyme_ID, Pathway_ID, Pathway]
    /* ko_ref.tsv 的列序是 …/ecs/pid/pname，而表的列序是 …/Pathway/Pathway_ID/
       Source —— 后两列是**反的**，这里必须换过来写。写反了的症状是详情页的
       pathway 链接变成 kegg.jp/pathway/Membrane+trafficking（拿名字当 ID）。
       实测过：/tmp/magbuild/test_kofam.tsv。 */
    $vals[] = "('" . $magEsc . "','" . mysqli_real_escape_string($conn, $p) . "','" . $ko . "','"
            . mysqli_real_escape_string($conn, $d[0]) . "','"
            . mysqli_real_escape_string($conn, $d[1]) . "','"
            . mysqli_real_escape_string($conn, $d[2]) . "','"
            . mysqli_real_escape_string($conn, $d[4]) . "','"
            . mysqli_real_escape_string($conn, $d[3]) . "','KofamScan')";
    if (count($vals) >= $BATCH) {
        if (!$conn->query($HEAD . implode(',', $vals))) {
            fwrite(STDERR, "INSERT into mag_kegg_terms failed: " . $conn->error . "\n"); exit(1);
        }
        $w += count($vals); $vals = array();
    }
}
if ($vals) {
    if (!$conn->query($HEAD . implode(',', $vals))) {
        fwrite(STDERR, "INSERT into mag_kegg_terms failed: " . $conn->error . "\n"); exit(1);
    }
    $w += count($vals);
}
printf("  wrote mag_kegg_terms %d\n", $w);
$r = $conn->query("SELECT COUNT(*) FROM mag_kegg_terms WHERE mag = '$magEsc'")->fetch_row();
printf("  table now has %d rows for %s\n", $r[0], $mag);
$conn->close();
