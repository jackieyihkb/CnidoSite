<?php
/**
 * genome_assembly_refresh.php —— 从 NCBI Datasets API 拉取每个物种的「全部」基因组组装，
 * 写进 genome_assembly 表。
 *
 * 为什么要这张表：speciesinfo 每个物种只记一个组装（Genome_Assemble），但 NCBI 上不少
 * 物种有多个版本（单倍型、补洞版本、不同提交者）。data_statistics.php 的「Genome Assemblies」
 * 卡片要展示多版本并标出本站实际用的是哪一个，speciesinfo 给不了这个信息。
 *
 * 用哪个组装是**按 accession 判定**的：speciesinfo.Genome_Assemble 对上了就 is_site_used=1。
 * 不能只按 Assembly_Name 判 —— AAUST 的 GCA_964273405.1（alternate haplotype）和
 * GCA_964273435.1（haploid）名字完全一样，都叫 jaAcrAuse1.1。
 * accession 对不上时才退回按名字，且优先选非 alternate 的那一个（site_source='name'）。
 *
 * 原始 JSON 会缓存到 data/genome_assembly_cache/<taxid>.json（data/ 已 403，不外泄），
 * 重跑不会重新请求 NCBI；加 --refresh 才重新拉。
 *
 * CLI only。默认 dry-run，加 --commit 才写库。
 *
 * 用法：
 *   PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d \
 *   php -c /etc/php/7.4/apache2/php.ini includes/genome_assembly_refresh.php [选项]
 *
 *   --init        建表（幂等），首次跑要先加
 *   --commit      真正写库
 *   --refresh     忽略缓存，重新请求 NCBI
 *   --limit=N     只处理前 N 个物种（调试用）
 *   --taxid=NNN   只处理某个 taxid（调试用）
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

/* ------------------------------------------------------------------ 参数 */

$OPT = array(
    'commit'  => false,
    'refresh' => false,
    'init'    => false,
    'limit'   => 0,
    'taxid'   => '',
    'sleep'   => 400000,   // 每次请求之间的间隔（微秒）——NCBI 无 key 限 3 req/s
);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--commit')                        { $OPT['commit']  = true; }
    elseif ($arg === '--refresh')                   { $OPT['refresh'] = true; }
    elseif ($arg === '--init')                      { $OPT['init']    = true; }
    elseif (preg_match('/^--limit=(\d+)$/', $arg, $m)) { $OPT['limit'] = (int) $m[1]; }
    elseif (preg_match('/^--taxid=(\d+)$/', $arg, $m)) { $OPT['taxid'] = $m[1]; }
    elseif ($arg === '--help' || $arg === '-h')     { ga_usage(); exit(0); }
    else { fwrite(STDERR, "未知参数: $arg\n"); ga_usage(); exit(2); }
}

function ga_usage()
{
    fwrite(STDERR, <<<TXT
用法: php includes/genome_assembly_refresh.php [--init] [--commit] [--refresh] [--limit=N] [--taxid=NNN]

  --init        建表（幂等）
  --commit      真正写库（默认只看统计，不写）
  --refresh     忽略本地缓存，重新请求 NCBI
  --limit=N     只处理前 N 个物种
  --taxid=NNN   只处理指定 taxid

TXT
    );
}

/* ------------------------------------------------------------------ 连接 */

$conn = @new mysqli('localhost', 'jackie', '<REDACTED>', 'cnidaria');
if ($conn->connect_errno) {
    fwrite(STDERR, "数据库连接失败: {$conn->connect_error}\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

$CACHE_DIR = __DIR__ . '/../data/genome_assembly_cache';
$API       = 'https://api.ncbi.nlm.nih.gov/datasets/v2/genome/taxon/%s/dataset_report?page_size=1000';

/* ------------------------------------------------------------------ 建表 */

function ga_ddl()
{
    return <<<SQL
CREATE TABLE IF NOT EXISTS `genome_assembly` (
  -- abbr1 要够宽：没有 5 字母短码的物种，abbr.abbr1 存的就是下划线全名（最长见过 30 字符）
  `abbr1`                  varchar(64)  NOT NULL,
  `abbr`                   varchar(96)  DEFAULT NULL,
  `species`                varchar(160) DEFAULT NULL,
  `taxid`                  varchar(16)  DEFAULT NULL,
  `accession`              varchar(32)  NOT NULL,
  `current_accession`      varchar(32)  DEFAULT NULL,
  `source_database`        varchar(16)  DEFAULT NULL,
  `organism_name`          varchar(200) DEFAULT NULL,
  `assembly_name`          varchar(255) DEFAULT NULL,
  `assembly_level`         varchar(24)  DEFAULT NULL,
  `assembly_status`        varchar(24)  DEFAULT NULL,
  `assembly_type`          varchar(40)  DEFAULT NULL,
  `diploid_role`           varchar(40)  DEFAULT NULL,
  `refseq_category`        varchar(40)  DEFAULT NULL,
  `assembly_method`        varchar(255) DEFAULT NULL,
  `sequencing_tech`        varchar(255) DEFAULT NULL,
  `total_sequence_length`  bigint unsigned DEFAULT NULL,
  `total_ungapped_length`  bigint unsigned DEFAULT NULL,
  `number_of_contigs`      int unsigned    DEFAULT NULL,
  `number_of_scaffolds`    int unsigned    DEFAULT NULL,
  `number_of_component_sequences` int unsigned DEFAULT NULL,
  `total_number_of_chromosomes` int unsigned DEFAULT NULL,
  `contig_n50`             bigint unsigned DEFAULT NULL,
  `scaffold_n50`           bigint unsigned DEFAULT NULL,
  `gc_percent`             decimal(5,2)    DEFAULT NULL,
  `genome_coverage`        decimal(8,2)    DEFAULT NULL,
  `bioproject_accession`   varchar(24)  DEFAULT NULL,
  `release_date`           date         DEFAULT NULL,
  `submitter`              varchar(255) DEFAULT NULL,
  `n_organelles`           int unsigned DEFAULT NULL,
  `is_site_used`           tinyint      NOT NULL DEFAULT 0,
  `site_source`            varchar(16)  DEFAULT NULL,
  `fetched_at`             datetime     DEFAULT NULL,
  PRIMARY KEY (`abbr1`,`accession`),
  KEY `ix_acc`  (`accession`),
  KEY `ix_site` (`is_site_used`),
  KEY `ix_tax`  (`taxid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
}

if ($OPT['init']) {
    if (!$conn->query(ga_ddl())) {
        fwrite(STDERR, "建表失败: {$conn->error}\n");
        exit(1);
    }
    echo "genome_assembly 表就绪\n";
}

$has = $conn->query("SELECT 1 FROM information_schema.tables
                      WHERE table_schema = DATABASE() AND table_name = 'genome_assembly' LIMIT 1");
if (!$has || $has->num_rows === 0) {
    fwrite(STDERR, "genome_assembly 表不存在，先加 --init\n");
    exit(1);
}

if (!is_dir($CACHE_DIR) && !@mkdir($CACHE_DIR, 0775, true)) {
    fwrite(STDERR, "无法创建缓存目录: $CACHE_DIR\n");
    exit(1);
}

/* ------------------------------------------------------------------ 取物种表
 * 注意：不能 join 在 speciesinfo.abbr 上 —— 那一列是混用的（150 行是 AALAT 这种短码，
 * 176 行是 Nematostella_vectensis 这种下划线全名）。唯一可靠的 join 键是 Latin_name = species。
 */
$species = array();
$q = $conn->query(
    "SELECT s.Latin_name, s.abbr, s.NCBI_taxonomy_ID, s.Genome_Assemble, s.Assembly_Name, a.abbr1
       FROM speciesinfo s
       LEFT JOIN abbr a ON a.species = s.Latin_name
      ORDER BY a.abbr1, s.Latin_name"
);
if (!$q) {
    fwrite(STDERR, "读 speciesinfo 失败: {$conn->error}\n");
    exit(1);
}
while ($r = $q->fetch_assoc()) {
    $species[] = $r;
}
if ($OPT['taxid'] !== '') {
    $species = array_values(array_filter($species, function ($s) use ($OPT) {
        return trim($s['NCBI_taxonomy_ID']) === $OPT['taxid'];
    }));
}
if ($OPT['limit'] > 0) {
    $species = array_slice($species, 0, $OPT['limit']);
}
if (!$species) {
    fwrite(STDERR, "没有匹配的物种\n");
    exit(1);
}

printf("物种数: %d   模式: %s   缓存: %s\n\n",
    count($species),
    $OPT['commit'] ? 'COMMIT' : 'dry-run',
    $OPT['refresh'] ? '忽略' : '启用');

/* ------------------------------------------------------------------ 工具函数 */

/** 一次 GET；$httpCode 由引用返回，失败返回 false。 */
function ga_http_get($url, &$httpCode)
{
    $httpCode = 0;
    $ctx = stream_context_create(array('http' => array(
        'method'        => 'GET',
        'timeout'       => 90,
        'ignore_errors' => true,
        'header'        => "User-Agent: CnidoSite-genome-catalogue/1.0\r\nAccept: application/json\r\n",
    )));
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return false;   // 注意：此时不要去看 $http_response_header，它可能残留上一次的值
    }
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $httpCode = (int) $m[1];
            }
        }
    }
    return $body;
}

/** 拉一个 taxid 的全部组装（自动翻页）。返回 reports 数组，失败返回 null。 */
function ga_fetch_taxon($taxid, $api, $sleepUs)
{
    $reports = array();
    $token   = '';
    $pages   = 0;

    do {
        $url = sprintf($api, $taxid);
        if ($token !== '') {
            $url .= '&page_token=' . urlencode($token);
        }
        $code = 0;
        $body = ga_http_get($url, $code);

        if ($body === false) {
            // 传送失败不缓存，下次重试
            fwrite(STDERR, "    传输失败 (taxid=$taxid page=$pages)\n");
            return null;
        }
        if ($code === 404) {
            return array();            // 该 taxid 在 NCBI 上没有任何组装 —— 是可以缓存的结果
        }
        if ($code !== 200) {
            fwrite(STDERR, "    HTTP $code (taxid=$taxid page=$pages)\n");
            return null;
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            fwrite(STDERR, "    JSON 解析失败 (taxid=$taxid)\n");
            return null;
        }
        if (!empty($json['reports'])) {
            foreach ($json['reports'] as $rep) {
                $reports[] = $rep;
            }
        }
        $token = isset($json['next_page_token']) ? (string) $json['next_page_token'] : '';
        $pages++;
        if ($token !== '') {
            usleep($sleepUs);
        }
    } while ($token !== '' && $pages < 50);

    return $reports;
}

/** 从一份 report 里抽出要存的字段。 */
function ga_flatten($rep, $sp, $now)
{
    $ai = isset($rep['assembly_info'])  ? $rep['assembly_info']  : array();
    $st = isset($rep['assembly_stats']) ? $rep['assembly_stats'] : array();

    $pick = function ($arr, $key) {
        return (isset($arr[$key]) && $arr[$key] !== '' && $arr[$key] !== null) ? $arr[$key] : null;
    };
    $int = function ($v) {
        return ($v === null || $v === '' || !is_numeric($v)) ? null : (int) $v;
    };
    $date = function ($v) {
        return (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) ? $v : null;
    };

    // source_database 是枚举名 SOURCE_DATABASE_GENBANK / SOURCE_DATABASE_REFSEQ，
    // 直接存会撑爆 varchar(16)，转成人看的写法
    $src = $pick($rep, 'source_database');
    if ($src !== null) {
        $src = ucfirst(strtolower(str_replace('SOURCE_DATABASE_', '', $src)));
    }

    return array(
        'accession'                   => (string) $rep['accession'],
        'current_accession'           => $pick($rep, 'current_accession'),
        'source_database'             => $src,
        'organism_name'               => isset($rep['organism']['organism_name']) ? $rep['organism']['organism_name'] : null,
        'assembly_name'               => $pick($ai, 'assembly_name'),
        'assembly_level'              => $pick($ai, 'assembly_level'),
        'assembly_status'             => $pick($ai, 'assembly_status'),
        'assembly_type'               => $pick($ai, 'assembly_type'),
        'diploid_role'                => $pick($ai, 'diploid_role'),
        'refseq_category'             => $pick($ai, 'refseq_category'),
        'assembly_method'             => $pick($ai, 'assembly_method'),
        'sequencing_tech'             => $pick($ai, 'sequencing_tech'),
        'total_sequence_length'       => $int($pick($st, 'total_sequence_length')),
        'total_ungapped_length'       => $int($pick($st, 'total_ungapped_length')),
        'number_of_contigs'           => $int($pick($st, 'number_of_contigs')),
        'number_of_scaffolds'         => $int($pick($st, 'number_of_scaffolds')),
        'number_of_component_sequences' => $int($pick($st, 'number_of_component_sequences')),
        'total_number_of_chromosomes' => $int($pick($st, 'total_number_of_chromosomes')),
        'contig_n50'                  => $int($pick($st, 'contig_n50')),
        'scaffold_n50'                => $int($pick($st, 'scaffold_n50')),
        'gc_percent'                  => is_numeric($pick($st, 'gc_percent')) ? $pick($st, 'gc_percent') : null,
        'genome_coverage'             => is_numeric($pick($st, 'genome_coverage')) ? $pick($st, 'genome_coverage') : null,
        'bioproject_accession'        => $pick($ai, 'bioproject_accession'),
        'release_date'                => $date($pick($ai, 'release_date')),
        'submitter'                   => $pick($ai, 'submitter'),
        'n_organelles'                => $int($pick($st, 'number_of_organelles')),
        'fetched_at'                  => $now,
    );
}

/** 判断哪一个组装是本站实际在用的。返回 array(0|1, source)。 */
function ga_mark_site_used($rows, $siteAcc, $siteName)
{
    $siteAcc  = strtoupper(trim((string) $siteAcc));
    $siteName = trim((string) $siteName);

    // 1) 先按 accession 精确匹配 —— 唯一可靠的判据
    if ($siteAcc !== '' && $siteAcc !== '-' && $siteAcc !== 'NA') {
        foreach ($rows as $i => $r) {
            if (strtoupper(trim($r['accession'])) === $siteAcc) {
                return array($i, 'accession');
            }
        }
    }

    // 2) accession 对不上才退回按名字；同名时优先非 alternate 的那个
    if ($siteName !== '' && $siteName !== '-') {
        $cands = array();
        foreach ($rows as $i => $r) {
            if (trim((string) $r['assembly_name']) === $siteName) {
                $cands[] = $i;
            }
        }
        if (count($cands) === 1) {
            return array($cands[0], 'name');
        }
        if (count($cands) > 1) {
            foreach ($cands as $i) {
                $t = strtolower((string) $rows[$i]['assembly_type']);
                if (strpos($t, 'alternate') === false) {
                    return array($i, 'name');
                }
            }
            return array($cands[0], 'name');
        }
    }

    return array(-1, null);
}

/* ------------------------------------------------------------------ 主循环 */

$now = date('Y-m-d H:i:s');

$nSpecies  = 0;
$nRows     = 0;
$nNoData   = 0;
$nFailed   = 0;
$nSiteMark = 0;
$multi     = array();   // 多组装的物种
$noSiteUse = array();   // 有组装但认不出本站用哪个的物种

if ($OPT['commit']) {
    // 整表重建：先建临时表再原子换名，页面不会看到空表
    $conn->query("DROP TABLE IF EXISTS `genome_assembly_new`");
    $ddl = str_replace('`genome_assembly`', '`genome_assembly_new`', ga_ddl());
    if (!$conn->query($ddl)) {
        fwrite(STDERR, "建临时表失败: {$conn->error}\n");
        exit(1);
    }
}

$ins = null;
if ($OPT['commit']) {
    $ins = $conn->prepare(
        "INSERT INTO `genome_assembly_new`
         (abbr1, abbr, species, taxid, accession, current_accession, source_database, organism_name,
          assembly_name, assembly_level, assembly_status, assembly_type, diploid_role, refseq_category,
          assembly_method, sequencing_tech, total_sequence_length, total_ungapped_length,
          number_of_contigs, number_of_scaffolds, number_of_component_sequences,
          total_number_of_chromosomes, contig_n50, scaffold_n50, gc_percent, genome_coverage,
          bioproject_accession, release_date, submitter, n_organelles, is_site_used, site_source, fetched_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    if (!$ins) {
        fwrite(STDERR, "预处理失败: {$conn->error}\n");
        exit(1);
    }
}

foreach ($species as $sp) {
    $taxid    = trim((string) $sp['NCBI_taxonomy_ID']);
    $latin    = $sp['Latin_name'];
    $abbr1    = $sp['abbr1'] !== null ? $sp['abbr1'] : $sp['abbr'];
    $siteAcc  = $sp['Genome_Assemble'];
    $siteName = $sp['Assembly_Name'];
    $nSpecies++;

    if (!preg_match('/^\d+$/', $taxid)) {
        printf("%-22s  跳过：taxid 非法 (%s)\n", $abbr1, $taxid);
        $nFailed++;
        continue;
    }

    $cacheFile = $CACHE_DIR . '/' . $taxid . '.json';
    $reports   = null;

    if (!$OPT['refresh'] && is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['reports'])) {
            $reports = $cached['reports'];
        }
    }

    if ($reports === null) {
        $reports = ga_fetch_taxon($taxid, $API, $OPT['sleep']);
        if ($reports === null) {
            $nFailed++;
            printf("%-22s  ✗ 拉取失败\n", $abbr1);
            usleep($OPT['sleep']);
            continue;
        }
        // 写缓存：先写临时文件再 rename，避免半截文件被下次读到
        $tmp = $cacheFile . '.tmp.' . getmypid();
        file_put_contents($tmp, json_encode(array('fetched' => $now, 'reports' => $reports)));
        @rename($tmp, $cacheFile);
        usleep($OPT['sleep']);
    }

    if (!$reports) {
        $nNoData++;
        printf("%-22s  — NCBI 上无组装\n", $abbr1);
        continue;
    }

    // 展开
    $rows = array();
    foreach ($reports as $rep) {
        if (empty($rep['accession'])) {
            continue;
        }
        $rows[] = ga_flatten($rep, $sp, $now);
    }
    if (!$rows) {
        $nNoData++;
        printf("%-22s  — NCBI 上无组装\n", $abbr1);
        continue;
    }

    list($siteIdx, $siteSrc) = ga_mark_site_used($rows, $siteAcc, $siteName);
    if ($siteIdx >= 0) {
        $nSiteMark++;
    } else {
        $noSiteUse[] = $abbr1;
    }
    if (count($rows) > 1) {
        $multi[] = $abbr1 . '(' . count($rows) . ')';
    }

    $nRows += count($rows);
    printf("%-22s  %2d 个组装%s\n",
        $abbr1,
        count($rows),
        $siteIdx >= 0 ? '   本站用: ' . $rows[$siteIdx]['accession'] . " [{$siteSrc}]" : '   ⚠ 认不出本站用哪个');

    if ($OPT['commit']) {
        foreach ($rows as $i => $r) {
            $isUsed = ($i === $siteIdx) ? 1 : 0;
            $src    = ($i === $siteIdx) ? $siteSrc : null;
            $ins->bind_param(
                // 16×s 8×i 5×s 2×i 2×s = 33，必须与列数一致（不一致 mysqli 会直接报错）
                'ssssssssssssssssiiiiiiiisssssiiss',
                $abbr1, $sp['abbr'], $latin, $taxid,
                $r['accession'], $r['current_accession'], $r['source_database'], $r['organism_name'],
                $r['assembly_name'], $r['assembly_level'], $r['assembly_status'],
                $r['assembly_type'], $r['diploid_role'], $r['refseq_category'],
                $r['assembly_method'], $r['sequencing_tech'],
                $r['total_sequence_length'], $r['total_ungapped_length'],
                $r['number_of_contigs'], $r['number_of_scaffolds'],
                $r['number_of_component_sequences'], $r['total_number_of_chromosomes'],
                $r['contig_n50'], $r['scaffold_n50'],
                $r['gc_percent'], $r['genome_coverage'],
                $r['bioproject_accession'], $r['release_date'], $r['submitter'],
                $r['n_organelles'], $isUsed, $src, $r['fetched_at']
            );
            if (!$ins->execute()) {
                fwrite(STDERR, "    插入失败 {$r['accession']}: {$ins->error}\n");
            }
        }
    }
}

/* ------------------------------------------------------------------ 收尾 */

if ($OPT['commit']) {
    $ins->close();
    // 原子换名：站点只会看到旧表或新表，不会看到缺表的瞬间。
    // 上一次的 _prev 要先删掉，否则 RENAME 的目标已存在会直接失败。
    $conn->query("DROP TABLE IF EXISTS `genome_assembly_prev`");
    if (!$conn->query("RENAME TABLE `genome_assembly` TO `genome_assembly_prev`, `genome_assembly_new` TO `genome_assembly`")) {
        fwrite(STDERR, "换表失败: {$conn->error}\n");
        exit(1);
    }
    echo "\n已换表，旧表保留为 genome_assembly_prev\n";
}

echo "\n";
echo "物种数        : $nSpecies\n";
echo "组装行数      : $nRows\n";
echo "标出本站组装  : $nSiteMark\n";
echo "NCBI 无组装   : $nNoData\n";
echo "拉取失败      : $nFailed\n";
if ($multi) {
    printf("多组装物种    : %d 个 —— %s%s\n", count($multi), implode(', ', array_slice($multi, 0, 15)),
        count($multi) > 15 ? ' …' : '');
}
if ($noSiteUse) {
    printf("认不出本站组装: %d 个 —— %s%s\n", count($noSiteUse), implode(', ', array_slice($noSiteUse, 0, 15)),
        count($noSiteUse) > 15 ? ' …' : '');
}
if (!$OPT['commit']) {
    echo "\n（dry-run，未写库。加 --commit 生效）\n";
}

$conn->close();
