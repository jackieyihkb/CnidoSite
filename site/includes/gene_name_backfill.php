<?php
/**
 * gene_name_backfill.php —— 给 gene_literature 补上「站点基因名」列 site_name
 * 与「代表蛋白号」列 site_mrna（脚本名保留：这两列本来就是同一次扫描算出来的）。
 *
 * data_statistics.php 的 Gene Literature 表一直把 NCBI 的 symbol 当基因名显示，
 * 而多数物种的 symbol 就是 LOC… 占位号，于是那一格印出来是 `LOC5515782` 两遍 ——
 * 库里的名字（wnt3、foxd3 这类）根本没露出来。名字真正的来源是各物种注释表的
 * description，取法见 includes/gene_name_map.php 的文件头。
 *
 * site_mrna 是那个基因的**代表蛋白号**（<ABBR>_locus.mRNA，取转录本最长的一条）。
 * 必须给出来，是因为 gene_detail.php 的注释面板全部按蛋白号查：拿 locus 基因号
 * 进详情页，NR / UniProt / Pfam / InterPro / GO / KEGG / 表达 / 表观 / 网络一律是空的
 * （实测 NVECT 的 LOC5500864），换成 XP_032225133.2 就全有。文献表里的基因要能点进
 * 有内容的页面，就得带上这个号；它同时也能被搜索框搜到。
 *
 * 单独做成一个脚本而不是只塞进 gene_literature_refresh.php：全量重灌要 30 分钟
 * 并且要联网下 gene2pubmed，而补这两列是纯本地计算（每物种 0.2 秒），已有的 60 万行
 * 不该为了这两列重下。
 *
 * 幂等：每次先把该物种的两列清成 NULL 再写，名字/蛋白号在库里消失也不会留下旧值。
 * 加了列和前缀索引之后，文献表的搜索框才搜得到基因名和蛋白号。
 *
 * CLI only。默认 dry-run（只统计），加 --commit 才写库。
 *
 * 用法：
 *   PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d \
 *   php -c /etc/php/7.4/apache2/php.ini includes/gene_name_backfill.php [选项]
 *
 *   --commit          真正写库（含建列/建索引）
 *   --species=ABBR    只处理某个物种（可逗号分隔多个）
 *   --limit=N         只处理前 N 个物种（调试）
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/gene_name_map.php';

$OPT = array('commit' => false, 'species' => '', 'limit' => 0);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--commit')                                    { $OPT['commit'] = true; }
    elseif (preg_match('/^--species=([A-Za-z0-9_,]+)$/', $arg, $m)) { $OPT['species'] = $m[1]; }
    elseif (preg_match('/^--limit=(\d+)$/', $arg, $m))          { $OPT['limit'] = (int) $m[1]; }
    elseif ($arg === '--help' || $arg === '-h')                 { gn_usage(); exit(0); }
    else { fwrite(STDERR, "未知参数: $arg\n"); gn_usage(); exit(2); }
}

function gn_usage()
{
    fwrite(STDERR, <<<TXT
用法: php includes/gene_name_backfill.php [--commit] [--species=ABBR[,ABBR]] [--limit=N]

  --commit         真正写库（默认只统计不写）
  --species=ABBR   只处理指定物种（逗号分隔）
  --limit=N        只处理前 N 个物种

TXT
    );
}

/* ---------------------------------------------------------------- 连接 */

$conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_errno) {
    fwrite(STDERR, "数据库连接失败: {$conn->connect_error}\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

/* ---------------------------------------------------------------- 列与索引

   site_name / site_mrna 就排在 site_gene 后面：三者是一组（基因号 + 它的名字 +
   它的代表蛋白号），读代码的人不该在几个位置之间来回找。索引给前缀长度，与全站
   其它 TEXT 基因号列一致。 */

$wantCols = array(
    /* 列名       类型与位置                                   索引名         索引前缀 */
    array('site_name', "varchar(160) DEFAULT NULL AFTER `site_gene`",  'ix_site_name', '`site_name`(48)'),
    array('site_mrna', "varchar(64)  DEFAULT NULL AFTER `site_name`",  'ix_site_mrna', '`site_mrna`(32)'),
);
foreach ($wantCols as $w) {
    list($col, $ddl, $ix, $ixddl) = $w;
    $hasCol = $conn->query("SELECT 1 FROM information_schema.columns
                             WHERE table_schema = DATABASE() AND table_name = 'gene_literature'
                               AND column_name = '$col' LIMIT 1");
    if (!$hasCol || $hasCol->num_rows === 0) {
        echo "gene_literature 还没有 $col 列";
        if (!$OPT['commit']) {
            echo "（dry-run，--commit 时会执行 ALTER TABLE）\n";
            continue;
        }
        echo "，正在加…\n";
        if (!$conn->query("ALTER TABLE `gene_literature`
                            ADD COLUMN `$col` $ddl,
                            ADD KEY `$ix` ($ixddl)")) {
            fwrite(STDERR, "ALTER 失败: {$conn->error}\n");
            exit(1);
        }
        echo "已加上 $col 列与 $ix 索引\n";
        continue;
    }
    $hasIx = $conn->query("SELECT 1 FROM information_schema.statistics
                            WHERE table_schema = DATABASE() AND table_name = 'gene_literature'
                              AND index_name = '$ix' LIMIT 1");
    if (!$hasIx || $hasIx->num_rows === 0) {
        echo "$col 列在，但没有 $ix 索引";
        if ($OPT['commit']) {
            $conn->query("ALTER TABLE `gene_literature` ADD KEY `$ix` ($ixddl)");
            echo "，已补上\n";
        } else {
            echo "（dry-run，--commit 时会补）\n";
        }
    }
}

/* ---------------------------------------------------------------- 取物种 */

$want = array();
if ($OPT['species'] !== '') {
    foreach (explode(',', $OPT['species']) as $s) {
        if ($s !== '') { $want[$s] = true; }
    }
}
$list = array();
$q = $conn->query("SELECT abbr1, COUNT(*) n, COUNT(DISTINCT site_gene) g
                     FROM gene_literature GROUP BY abbr1 ORDER BY n DESC");
while ($q && ($r = $q->fetch_assoc())) {
    if ($want && !isset($want[$r['abbr1']])) { continue; }
    $list[] = $r;
    if ($OPT['limit'] > 0 && count($list) >= $OPT['limit']) { break; }
}
if (!$list) {
    echo "gene_literature 里没有可处理的物种。\n";
    exit(0);
}

/* 名字的临时落脚点。用真表不用 TEMPORARY：下面是一条 UPDATE…JOIN，让 MySQL 自己
   挑驱动表；名字表很小（一个物种最多两三万行），配上索引一次 join 就写完了。 */
if ($OPT['commit']) {
    $conn->query("DROP TABLE IF EXISTS `_gene_name_tmp`");
    if (!$conn->query("CREATE TABLE `_gene_name_tmp` (
                          `abbr1` varchar(64) NOT NULL,
                          `gene`  varchar(120) NOT NULL,
                          `name`  varchar(160) NOT NULL,
                          `mrna`  varchar(64)  NOT NULL,
                          KEY `ix_gene` (`gene`(48)),
                          KEY `ix_abbr_gene` (`abbr1`(24), `gene`(48))
                       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci")) {
        fwrite(STDERR, "建临时表失败: {$conn->error}\n");
        exit(1);
    }
}

echo ($OPT['commit'] ? "== 写入 ==\n" : "== dry-run（只看统计，不写库）==\n");

$totRows = 0;
$totNamed = 0;
$totMrna = 0;
$totLink = 0;
foreach ($list as $sp) {
    $abbr1 = $sp['abbr1'];
    $t0 = microtime(true);

    /* 这个物种在文献表里出现过的站点基因号 —— 只给它们取名 */
    $only = array();
    $q = $conn->query("SELECT DISTINCT site_gene FROM gene_literature
                        WHERE abbr1 = '" . $conn->real_escape_string($abbr1) . "'
                          AND site_gene IS NOT NULL AND site_gene <> ''");
    while ($q && ($r = $q->fetch_row())) { $only[$r[0]] = true; }

    $map = $only ? cnido_gene_name_map($conn, $abbr1, $only) : array();

    $gn = 0;        // 名字里有多少来自 UniProt 的 GN= 符号（其余来自 NR 描述）
    $nName = 0;     // 有名字的基因数
    $nMrna = 0;     // 有代表蛋白号的基因数
    foreach ($map as $v) {
        if ($v['name'] !== '') {
            $nName++;
            if ($v['src'] === 'uniprot_gn') { $gn++; }
        }
        if ($v['mrna'] !== '') { $nMrna++; }
    }

    $updated = 0;
    if ($OPT['commit']) {
        $e = $conn->real_escape_string($abbr1);
        /* 先清后写：名字/蛋白号来源变了（比如注释表重灌）也不会留下旧值 */
        $conn->query("UPDATE gene_literature SET site_name = NULL, site_mrna = NULL
                       WHERE abbr1 = '$e'");
        if ($map) {
            $conn->query("DELETE FROM `_gene_name_tmp` WHERE abbr1 = '$e'");
            $vals = array();
            $lit  = function ($v) use ($conn) { return "'" . $conn->real_escape_string($v) . "'"; };
            foreach ($map as $g => $v) {
                $vals[] = '(' . $lit($abbr1) . ',' . $lit($g) . ',' . $lit($v['name'])
                        . ',' . $lit($v['mrna']) . ')';
                if (count($vals) >= 1000) {
                    $conn->query("INSERT INTO `_gene_name_tmp` (abbr1, gene, name, mrna) VALUES "
                                 . implode(',', $vals));
                    $vals = array();
                }
            }
            if ($vals) {
                $conn->query("INSERT INTO `_gene_name_tmp` (abbr1, gene, name, mrna) VALUES "
                             . implode(',', $vals));
            }
            /* NULLIF：临时表里两列都是 NOT NULL，空串在这里换回 NULL，
               免得搜索框的 `site_name = ''` 之类把「没有」当成一个值。 */
            if (!$conn->query("UPDATE gene_literature g JOIN `_gene_name_tmp` t
                                  ON t.abbr1 = g.abbr1 AND t.gene = g.site_gene
                                  SET g.site_name = NULLIF(t.name, ''),
                                      g.site_mrna = NULLIF(t.mrna, '')
                                WHERE g.abbr1 = '$e'")) {
                fwrite(STDERR, "  更新失败 ({$abbr1}): {$conn->error}\n");
            } else {
                $updated = $conn->affected_rows;
            }
        }
    }

    printf("  %-8s 文献里站点基因 %6s → 有名字 %6s (%3.0f%%，其中 GN= %6s)  有蛋白号 %6s (%3.0f%%)%s  [%.1fs]\n",
        $abbr1,
        number_format(count($only)),
        number_format($nName),
        count($only) ? 100 * $nName / count($only) : 0,
        number_format($gn),
        number_format($nMrna),
        count($only) ? 100 * $nMrna / count($only) : 0,
        $OPT['commit'] ? sprintf('  写入 %s 行', number_format($updated)) : '',
        microtime(true) - $t0);

    $totRows  += count($only);
    $totNamed += $nName;
    $totMrna  += $nMrna;
    $totLink  += $OPT['commit'] ? $updated : 0;
}

if ($OPT['commit']) {
    $conn->query("DROP TABLE IF EXISTS `_gene_name_tmp`");
}

echo "\n";
printf("合计：文献里的站点基因 %s 个 → 有名字 %s 个（%.0f%%）、有代表蛋白号 %s 个（%.0f%%）\n",
    number_format($totRows), number_format($totNamed),
    $totRows ? 100 * $totNamed / $totRows : 0,
    number_format($totMrna), $totRows ? 100 * $totMrna / $totRows : 0);
if ($OPT['commit']) {
    printf("已写入 %s 行；没有名字/蛋白号的基因对应列保持 NULL（页面只显示拿得到的东西）。\n",
        number_format($totLink));
    echo "提醒：搜索框的可搜字段变了（site_name / site_mrna 进了 cnido_gl_resolve），无需重建概览缓存。\n";
} else {
    echo "没写库。要写就加 --commit。\n";
}
