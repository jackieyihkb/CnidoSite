<?php
/* 把 NCBI 取回的线粒体基因组数据并入 CnidoSite。
 *
 * 两件事：
 *   1. 建 mito_genome 表 —— 每个物种一行「基因组级」统计（长度、GC、基因数、
 *      tRNA/rRNA 数、来源、检索日期）。原先站上只有基因级特征表 mitochondrion，
 *      页面上看不到基因组有多大、GC 多少。这些数字现在都能从 GenBank 记录直接得到。
 *   2. 把 mitochondrion 表里还没有的 18 个物种的特征行补进去。mitdata.php 的
 *      物种白名单就是 "SELECT DISTINCT species FROM mitochondrion"，补完即自动出现。
 *
 * 幂等：重复执行不会产生重复行（mito_genome 用 REPLACE，mitochondrion 先删后插）。
 * 用法：php -d extension=mysqlnd.so -d extension=mysqli.so import.php [--commit]
 *       不带 --commit 时只打印将要写入的内容，不落库。
 */
$commit = in_array('--commit', $argv, true);
$src = json_decode(file_get_contents(__DIR__ . '/mito_fetch.json'), true);
if (!is_array($src)) { fwrite(STDERR, "读取 mito_fetch.json 失败\n"); exit(1); }

$conn = new mysqli('localhost', 'jackie', '<REDACTED>', 'cnidaria');
if ($conn->connect_error) { fwrite(STDERR, "数据库连接失败: " . $conn->connect_error . "\n"); exit(1); }
$conn->set_charset('utf8mb4');

/* ---------- 1. mito_genome ---------- */
$ddl = "CREATE TABLE IF NOT EXISTS mito_genome (
    abbr1      VARCHAR(64)  NOT NULL,
    species    VARCHAR(190) NOT NULL,
    accession  VARCHAR(64)  NOT NULL,
    size_bp    INT          NULL,
    gc_percent DECIMAL(5,2) NULL,
    n_genes    INT          NULL,
    n_trna     INT          NULL,
    n_rrna     INT          NULL,
    source     VARCHAR(32)  NULL,
    retrieved  DATE         NULL,
    PRIMARY KEY (abbr1),
    KEY species (species)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($commit && !$conn->query($ddl)) { fwrite(STDERR, "建表失败: " . $conn->error . "\n"); exit(1); }

/* 物种名以 abbr 表为准，避免 GenBank 的写法与站内不一致 */
$nameOf = array();
if ($q = $conn->query("SELECT abbr1, species FROM abbr")) {
    while ($r = $q->fetch_row()) { $nameOf[$r[0]] = $r[0]; $nameOf[$r[0]] = $r[1]; }
}

$rows = array(); $newFeat = array();
foreach ($src as $ab => $v) {
    $sp = $v['species'];
    $src2 = (strpos($v['acc'], 'NC_') === 0 || strpos($v['acc'], 'NM_') === 0) ? 'NCBI RefSeq' : 'NCBI GenBank';
    $rows[] = array($ab, $sp, $v['acc'], $v['size'], $v['gc'], $v['n_gene'], $v['n_trna'], $v['n_rrna'], $src2, $v['retrieved']);
    if (!empty($v['new']) && !empty($v['features'])) {
        foreach ($v['features'] as $f) {
            $newFeat[] = array($sp, $ab, $v['acc'], $f['name'], $f['type'], $f['start'], $f['end'], $f['length'], $f['strand']);
        }
    }
}
/* 只补 mitochondrion 里完全没有的物种，不碰已有物种的行 */
$present = array();
if ($q = $conn->query("SELECT DISTINCT species FROM mitochondrion")) {
    while ($r = $q->fetch_row()) { $present[strtolower(trim($r[0]))] = true; }
}
$newFeat = array_values(array_filter($newFeat, function ($f) use ($present) {
    return !isset($present[strtolower(trim($f[0]))]);
}));
$newSpecies = array();
foreach ($newFeat as $f) { $newSpecies[$f[0]] = true; }

printf("mito_genome 行数: %d\n", count($rows));
printf("补充特征行: %d 行，覆盖 %d 个新物种\n", count($newFeat), count($newSpecies));
foreach (array_keys($newSpecies) as $s) { printf("   + %s\n", $s); }

if (!$commit) { echo "\n（未落库；加 --commit 执行）\n"; exit(0); }

$conn->begin_transaction();
try {
    $st = $conn->prepare("REPLACE INTO mito_genome
        (abbr1, species, accession, size_bp, gc_percent, n_genes, n_trna, n_rrna, source, retrieved)
        VALUES (?,?,?,?,?,?,?,?,?,?)");
    foreach ($rows as $r) {
        /* 类型串逐列对齐：abbr1/species/accession=s，size_bp=i，gc_percent=d，
           n_genes/n_trna/n_rrna=i，source/retrieved=s */
        $st->bind_param('sssidiisss', ...$r);
        $st->execute();
    }
    $st->close();

    $ins = $conn->prepare("INSERT INTO mitochondrion
        (species, abbr, accession, Name, Type, Start, End, Length, Strand) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($newFeat as $f) {
        $f = array_map('strval', $f);
        $ins->bind_param('sssssssss', ...$f);
        $ins->execute();
    }
    $ins->close();
    $conn->commit();
    echo "\n已写入。\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, "回滚：" . $e->getMessage() . "\n");
    exit(1);
}
