<?php
/**
 * gene_literature_refresh.php —— 从 NCBI gene2pubmed 建「基因 ↔ 文献」关系表。
 *
 * 为什么不用 NCBI 网页现查：data_statistics.php 上要放一张能按基因号/基因名搜索、
 * 还能分页的文献表。现查意味着每次翻页都去请求 NCBI，既慢又会被限流；而 gene2pubmed
 * 是一份全量快照（275 MB），本地算一次就能一直用。
 *
 * ── 关键的一处判断：两篇「注释方法学」论文 ──────────────────────────
 * 刺胞动物的 607,917 条「基因-文献」里，有 566,904 条（93%）只来自两篇论文：
 *
 *   PMID 22301074  Manual GO annotation of predictive protein signatures:
 *                  the InterPro approach to GO curation (Database, 2012)
 *   PMID 30032202  TreeGrafter: phylogenetic tree-based annotation of proteins
 *                  with Gene Ontology terms (Bioinformatics, 2019)
 *
 * 这两篇不是「研究某个基因」的论文，而是**注释来源**：NCBI 把 InterPro / TreeGrafter
 * 给某个基因打上的 GO 注释，追认成了这两篇文献。把它们和真正的基因研究并列展示会
 * 误导读者（搜 wnt3 会搜出「InterPro 注释方法」）。所以照灌不误，但打上
 * is_annotation_method=1，网页默认不显示，由用户自己勾选才出现。
 * 剩下 41,013 条来自 325 篇真正的论文。
 *
 * ── 映射：NCBI GeneID → 站点的基因号 ────────────────────────────────
 * 站点的 <ABBR>_locus.gene 存的是各物种自己的基因号，和 NCBI GeneID 不是一个体系。
 * 按以下顺序试（pilot 实测命中率 55%–84%）：
 *   1. symbol        —— 例如 Hydra 的 hym-346、Nematostella 的 LOC116601119
 *   2. locus_tag     —— 例如 Exaiptasia 的 AC249_AIPGENE13184
 *   3. alternate_names / synonyms
 * 映不上不算失败：行照样入库，site_gene 留 NULL，网页按「未映射」显示。
 * 有 11 个物种在站点上根本没有 <ABBR>_locus 表（例如 CXAMA 只有注释表），
 * 这些物种的 map_source 记为 no_locus_table。
 *
 * 原始 JSON 缓存在 data/literature_cache/（data/ 已 403），重跑不重新请求。
 *
 * CLI only。默认 dry-run，加 --commit 才写库。
 *
 * 用法：
 *   PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d \
 *   php -c /etc/php/7.4/apache2/php.ini includes/gene_literature_refresh.php [选项]
 *
 *   --init            建表（幂等），首次跑要先加
 *   --commit          真正写库
 *   --refresh         忽略缓存重新请求 NCBI / PubMed
 *   --gene2pubmed=F   用本地文件替代默认的 /tmp/gene2pubmed.gz
 *   --limit=N         只处理前 N 个物种（调试）
 *   --taxid=NNN       只处理某个 taxid（调试）
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

/* 站点自己的基因名（注释表里的 GN= 符号 / NR 描述）与代表蛋白号（<ABBR>_locus.mRNA）。
   整表重建也必须写上这两列，否则重灌一次 site_name / site_mrna 就全空了 ——
   单独补这两列的脚本是 includes/gene_name_backfill.php。 */
require_once __DIR__ . '/gene_name_map.php';

/* ---------------------------------------------------------------- 配置 */

/* 这两篇是注释来源而不是基因研究，网页默认隐藏。理由见文件头。 */
const GL_ANNOTATION_PMIDS = array('22301074', '30032202');

$OPT = array(
    'commit'       => false,
    'refresh'      => false,
    'init'         => false,
    'limit'        => 0,
    'taxid'        => '',
    'gene2pubmed'  => '/tmp/gene2pubmed.gz',
    'sleep'        => 400000,   // µs，NCBI 无 API key 限 3 req/s
    'batch'        => 200,      // gene/id 接口每批的基因数（实测 200 个全部返回）
    'flush'        => 2000,     // 每积累多少行写一次库
);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--commit')                        { $OPT['commit']  = true; }
    elseif ($arg === '--refresh')                   { $OPT['refresh'] = true; }
    elseif ($arg === '--init')                      { $OPT['init']    = true; }
    elseif (preg_match('/^--limit=(\d+)$/', $arg, $m))  { $OPT['limit'] = (int) $m[1]; }
    elseif (preg_match('/^--taxid=(\d+)$/', $arg, $m))  { $OPT['taxid'] = $m[1]; }
    elseif (preg_match('/^--gene2pubmed=(.+)$/', $arg, $m)) { $OPT['gene2pubmed'] = $m[1]; }
    elseif ($arg === '--help' || $arg === '-h')     { gl_usage(); exit(0); }
    else { fwrite(STDERR, "未知参数: $arg\n"); gl_usage(); exit(2); }
}

function gl_usage()
{
    fwrite(STDERR, <<<TXT
用法: php includes/gene_literature_refresh.php [--init] [--commit] [--refresh]
                                              [--gene2pubmed=FILE] [--limit=N] [--taxid=NNN]

  --init            建表（幂等）
  --commit          真正写库（默认只看统计，不写）
  --refresh         忽略本地缓存，重新请求 NCBI / PubMed
  --gene2pubmed=F   用本地文件替代默认的 /tmp/gene2pubmed.gz
  --limit=N         只处理前 N 个物种
  --taxid=NNN       只处理指定 taxid

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

$CACHE_DIR = __DIR__ . '/../data/literature_cache';

/* ---------------------------------------------------------------- 建表 */

function gl_ddl_literature()
{
    return <<<SQL
CREATE TABLE IF NOT EXISTS `literature` (
  `pmid`                 varchar(12)  NOT NULL,
  `title`                varchar(512) DEFAULT NULL,
  `journal`              varchar(255) DEFAULT NULL,
  `pub_year`             smallint unsigned DEFAULT NULL,
  `first_author`         varchar(160) DEFAULT NULL,
  -- 该论文在刺胞动物里被挂靠的基因数与物种数。这是判断「这篇是不是泛泛的
  -- 数据集论文」最直接的依据，网页上也直接显示给用户。
  `n_genes`              int unsigned NOT NULL DEFAULT 0,
  `n_species`            int unsigned NOT NULL DEFAULT 0,
  `is_annotation_method` tinyint      NOT NULL DEFAULT 0,
  `fetched_at`           datetime     DEFAULT NULL,
  PRIMARY KEY (`pmid`),
  KEY `ix_year` (`pub_year`),
  KEY `ix_ann`  (`is_annotation_method`),
  KEY `ix_n`    (`n_genes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
}

function gl_ddl_gene_literature()
{
    return <<<SQL
CREATE TABLE IF NOT EXISTS `gene_literature` (
  -- 代理主键。翻页 ORDER BY id 就是自增顺序（灌库时按物种顺序写的），
  -- 不需要按 (abbr1, symbol) 排序 —— 那两个列只有前缀索引，排序必然要 filesort。
  `id`                   bigint unsigned NOT NULL AUTO_INCREMENT,
  `abbr1`                varchar(64)  NOT NULL,
  `species`              varchar(160) DEFAULT NULL,
  `taxid`                varchar(16)  DEFAULT NULL,
  `gene_id`              varchar(20)  NOT NULL,   -- NCBI GeneID，就是 gene2pubmed 给的那个
  `symbol`               varchar(120) DEFAULT NULL,
  `locus_tag`            varchar(120) DEFAULT NULL,
  `site_gene`            varchar(120) DEFAULT NULL,  -- 站点 <ABBR>_locus.gene；映不上为 NULL
  `site_name`            varchar(160) DEFAULT NULL,  -- 站点自己注释里的基因名；没有就 NULL（不猜）
  `site_mrna`            varchar(64)  DEFAULT NULL,  -- 代表蛋白号 <ABBR>_locus.mRNA；没有就 NULL
  `map_source`           varchar(20)  DEFAULT NULL,  -- symbol / locus_tag / alt / none / no_locus_table
  `pmid`                 varchar(12)  NOT NULL,
  `is_annotation_method` tinyint      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  -- 注释表里的基因号列按老规矩是 TEXT，索引必须给前缀长度；这里虽然建的是 varchar，
  -- 仍然保留前缀写法，和全站其它表保持一致。
  KEY `ix_site_gene` (`site_gene`(48)),
  KEY `ix_site_name` (`site_name`(48)),
  KEY `ix_site_mrna` (`site_mrna`(32)),
  KEY `ix_symbol`    (`symbol`(48)),
  KEY `ix_locus_tag` (`locus_tag`(48)),
  KEY `ix_gid`       (`gene_id`),
  KEY `ix_pmid`      (`pmid`),
  KEY `ix_abbr1`     (`abbr1`(24)),
  KEY `ix_ann`       (`is_annotation_method`),
  -- 翻页要「按 id 顺序、只看非注释行」，单列索引会退化成扫主键再过滤；
  -- 93% 的行都是那两篇注释论文，翻到第 800 页就得白扫 57 万行。
  -- 把过滤列放在排序列前面，整个 LIMIT/OFFSET 走索引，不用回表也不用排序。
  KEY `ix_ann_id`    (`is_annotation_method`, `id`),
  KEY `ix_abbr_ann`  (`abbr1`(24), `is_annotation_method`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
}

if ($OPT['init']) {
    foreach (array('literature' => gl_ddl_literature(),
                   'gene_literature' => gl_ddl_gene_literature()) as $t => $ddl) {
        if (!$conn->query($ddl)) {
            fwrite(STDERR, "建表 $t 失败: {$conn->error}\n");
            exit(1);
        }
        echo "$t 表就绪\n";
    }
}

foreach (array('literature', 'gene_literature') as $t) {
    $has = $conn->query("SELECT 1 FROM information_schema.tables
                          WHERE table_schema = DATABASE() AND table_name = '$t' LIMIT 1");
    if (!$has || $has->num_rows === 0) {
        fwrite(STDERR, "$t 表不存在，先加 --init\n");
        exit(1);
    }
}

if (!is_dir($CACHE_DIR) && !@mkdir($CACHE_DIR, 0775, true)) {
    fwrite(STDERR, "无法创建缓存目录: $CACHE_DIR\n");
    exit(1);
}

/* ---------------------------------------------------------------- HTTP */

/** 一次 GET；$httpCode 引用返回；失败返回 false。 */
function gl_http_get($url, &$httpCode)
{
    $httpCode = 0;
    $ctx = stream_context_create(array('http' => array(
        'method'        => 'GET',
        'timeout'       => 120,
        'ignore_errors' => true,
        'header'        => "User-Agent: CnidoSite-literature/1.0\r\nAccept: application/json\r\n",
    )));
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return false;   // 此时 $http_response_header 可能还留着上一次的值，别看它
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

/** 带缓存的 GET-JSON。失败返回 null。 */
function gl_json_cached($url, $cacheFile, $refresh, &$code)
{
    if (!$refresh && is_file($cacheFile)) {
        $c = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($c)) {
            $code = 200;
            return $c;
        }
    }
    $body = gl_http_get($url, $code);
    if ($body === false || $code !== 200) {
        return null;
    }
    $j = json_decode($body, true);
    if (!is_array($j)) {
        return null;
    }
    $tmp = $cacheFile . '.tmp.' . getmypid();
    file_put_contents($tmp, json_encode($j));
    @rename($tmp, $cacheFile);
    return $j;
}

/* ================================================================ 第 1 步
 * 读 gene2pubmed，按 speciesinfo 里的刺胞动物 taxid 过滤。
 * 格式是 #tax_id \t GeneID \t PubMed_ID，表头行以 # 开头。
 *
 * 数据结构是**按 (taxid,gene) 归并**的，不是把 60 万行原样存下来：
 *    $taxGenePmids["taxid\tgid"] = array(pmid, ...)      约 40 万个键
 *    $pmStat[pmid] = array('n'=>配对条数, 'taxa'=>array(taxid=>true))
 *    $byTaxGenes[taxid] = array(gid => true)
 * 之前写成 $pmid => taxid => gid 的三层嵌套，光那一份就要几百 MB；
 * 而且展开时会对每个基因遍历全部 327 篇文献做 isset，是 1.3 亿次无用判断。
 */

echo "== 第 1 步：读 gene2pubmed ==\n";

$sp = array();   // taxid => array(abbr1, species)
$q = $conn->query("SELECT s.NCBI_taxonomy_ID, s.Latin_name, a.abbr1
                     FROM speciesinfo s LEFT JOIN abbr a ON a.species = s.Latin_name");
if (!$q) {
    fwrite(STDERR, "读 speciesinfo 失败: {$conn->error}\n");
    exit(1);
}
while ($r = $q->fetch_assoc()) {
    $t = trim((string) $r['NCBI_taxonomy_ID']);
    if (!preg_match('/^\d+$/', $t)) {
        continue;
    }
    $sp[$t] = array(
        'abbr1'   => ($r['abbr1'] !== null && $r['abbr1'] !== '') ? $r['abbr1'] : $r['Latin_name'],
        'species' => $r['Latin_name'],
    );
}
printf("物种表候选 taxid: %d\n", count($sp));

if (!is_file($OPT['gene2pubmed'])) {
    fwrite(STDERR, "找不到 {$OPT['gene2pubmed']}\n"
        . "可从 https://ftp.ncbi.nlm.nih.gov/gene/DATA/gene2pubmed.gz 下载（约 275 MB）\n");
    exit(1);
}
$gz = @gzopen($OPT['gene2pubmed'], 'rb');
if (!$gz) {
    fwrite(STDERR, "打不开 {$OPT['gene2pubmed']}\n");
    exit(1);
}

$taxGenePmids = array();
$pmStat       = array();
$byTaxGenes   = array();
$nLines = 0;

while (($line = gzgets($gz, 4096)) !== false) {
    $nLines++;
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    $f = explode("\t", rtrim($line, "\r\n"));
    if (count($f) < 3 || !isset($sp[$f[0]])) {
        continue;
    }
    $t = $f[0]; $g = $f[1]; $p = $f[2];
    $taxGenePmids[$t . "\t" . $g][] = $p;
    $byTaxGenes[$t][$g] = true;
    if (!isset($pmStat[$p])) {
        $pmStat[$p] = array('n' => 0, 'taxa' => array());
    }
    $pmStat[$p]['n']++;
    $pmStat[$p]['taxa'][$t] = true;
}
gzclose($gz);

printf("扫描 %s 行 → 刺胞动物配对 %s 条 / 基因 %s 个 / 文献 %d 篇 / 物种 %d 个\n\n",
    number_format($nLines),
    number_format(array_sum(array_map('count', $taxGenePmids))),
    number_format(count($taxGenePmids)),
    count($pmStat), count($byTaxGenes));

if (!$taxGenePmids) {
    fwrite(STDERR, "过滤后没有数据，检查 gene2pubmed 文件与 speciesinfo 的 taxid\n");
    exit(1);
}

/* 调试用：限制物种 */
if ($OPT['taxid'] !== '') {
    if (!isset($byTaxGenes[$OPT['taxid']])) {
        fwrite(STDERR, "taxid {$OPT['taxid']} 没有文献基因\n");
        exit(1);
    }
    $byTaxGenes = array($OPT['taxid'] => $byTaxGenes[$OPT['taxid']]);
} elseif ($OPT['limit'] > 0) {
    $byTaxGenes = array_slice($byTaxGenes, 0, $OPT['limit'], true);
}
if ($OPT['taxid'] !== '' || $OPT['limit'] > 0) {
    /* 展开时会用 isset($taxGenePmids[$key]) 过滤，不需要额外裁剪那两张表 */
    echo "（调试模式：只处理 " . count($byTaxGenes) . " 个物种）\n\n";
}

/* ================================================================ 第 2 步
 * PubMed 元数据。一次 esummary 最多 200 个 id。
 */

echo "== 第 2 步：PubMed 元数据（" . count($pmStat) . " 篇）==\n";

$now   = date('Y-m-d H:i:s');
$pmids = array_keys($pmStat);
sort($pmids);

$meta = array();
for ($i = 0; $i < count($pmids); $i += 200) {
    $chunk = array_slice($pmids, $i, 200);
    $url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&retmode=json&id='
         . implode(',', $chunk);
    $cache = $CACHE_DIR . '/pubmed_' . $chunk[0] . '.json';
    $code = 0;
    $j = gl_json_cached($url, $cache, $OPT['refresh'], $code);
    if ($j === null) {
        fwrite(STDERR, "  取 PubMed 失败 (HTTP $code, 起始 {$chunk[0]})\n");
        exit(1);
    }
    $res = isset($j['result']) ? $j['result'] : array();
    foreach ($chunk as $p) {
        if (!isset($res[$p])) {
            continue;   // PubMed 里查不到（极少见）
        }
        $r  = $res[$p];
        $yr = 0;
        if (isset($r['pubdate']) && preg_match('/(\d{4})/', $r['pubdate'], $m)) {
            $yr = (int) $m[1];
        }
        $meta[$p] = array(
            'title'   => isset($r['title']) ? (string) $r['title'] : '',
            'journal' => isset($r['source']) ? (string) $r['source'] : '',
            'year'    => $yr,
            'author'  => isset($r['authors'][0]['name']) ? (string) $r['authors'][0]['name'] : '',
        );
    }
    printf("  %d/%d\r", min($i + 200, count($pmids)), count($pmids));
    if ($i + 200 < count($pmids)) {
        usleep($OPT['sleep']);
    }
}
echo "\n";
printf("拿到元数据 %d / %d 篇\n\n", count($meta), count($pmids));

/* ================================================================ 第 3 步
 * 每个物种：取 NCBI 基因元数据（分批、缓存），映射到站点基因号，边算边攒行。
 */

echo "== 第 3 步：基因元数据与映射 ==\n";

/** 站点某物种的 <ABBR>_locus 里的基因号集合；没有可用的表返回 null。 */
function gl_site_genes($conn, $abbr1)
{
    $tbl = $abbr1 . '_locus';
    $esc = $conn->real_escape_string($tbl);
    $has = $conn->query("SELECT 1 FROM information_schema.tables
                          WHERE table_schema = DATABASE() AND table_name = '$esc' LIMIT 1");
    if (!$has || $has->num_rows === 0) {
        return null;
    }
    /* 有些物种的 _locus 是个空壳：只有一行脏表头 'Gene_ID' 加一堆 'NA'
       （HSYMB 就是这样，整张表只有两个不同的值）。把它当成"有表但映不上"
       会让页面对用户撒谎 —— 那其实是"这个物种没有可用的基因号表"。
       所以把这些哨兵值滤掉，滤完为空就按"没有表"处理。 */
    static $SENTINEL = array('Gene_ID' => 1, 'gene' => 1, 'Gene' => 1, 'NA' => 1,
                             'N/A' => 1, '-' => 1, 'NULL' => 1, 'null' => 1);
    $out = array();
    $q = $conn->query("SELECT DISTINCT gene FROM `$tbl` WHERE gene IS NOT NULL AND gene <> ''");
    if (!$q) {
        return null;
    }
    while ($r = $q->fetch_row()) {
        if (isset($SENTINEL[$r[0]])) {
            continue;
        }
        $out[$r[0]] = true;
    }
    return $out ? $out : null;
}

/* 攒够一批就写库；dry-run 时只清空。 */
$pending = array();
$nWritten = 0;
function gl_flush($conn, &$pending, $target, $commit, &$nWritten)
{
    if (!$pending) {
        return;
    }
    if ($commit) {
        /* 必须是 NULL 而不是 ''。用 implode 拼引号会把 PHP 的 null 变成空串，
           于是「映不上」在库里长得像「映上了但值为空」，`IS NULL` 一条都查不到 ——
           页面上按物种统计未映射基因的那段就永远是 0。 */
        $lit = function ($v) use ($conn) {
            return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
        };
        $vals = array();
        foreach ($pending as $r) {
            $vals[] = '(' . implode(',', array_map($lit, $r)) . ')';
        }
        $sql = "INSERT INTO `$target`
                 (abbr1, species, taxid, gene_id, symbol, locus_tag, site_gene, site_name, site_mrna, map_source, pmid, is_annotation_method)
                VALUES " . implode(',', $vals);
        if (!$conn->query($sql)) {
            fwrite(STDERR, "\n  插入失败: {$conn->error}\n");
            exit(1);
        }
        $nWritten += count($pending);
    }
    $pending = array();
}

$nMapOk = 0; $nMapNo = 0; $nNoTable = 0;
$nGeneMeta = 0; $nGeneMiss = 0; $nEmited = 0;
$srcCount = array();

if ($OPT['commit']) {
    /* 整表重建：先灌 gene_literature_new，跑完再原子换名，页面不会看到半步状态 */
    $conn->query("DROP TABLE IF EXISTS `gene_literature_new`");
    $ddl = str_replace('`gene_literature`', '`gene_literature_new`', gl_ddl_gene_literature());
    if (!$conn->query($ddl)) {
        fwrite(STDERR, "建临时表失败: {$conn->error}\n");
        exit(1);
    }
}

foreach ($byTaxGenes as $taxid => $genes) {
    $abbr1   = $sp[$taxid]['abbr1'];
    $species = $sp[$taxid]['species'];
    $gids    = array_keys($genes);
    sort($gids);

    $siteGenes = gl_site_genes($conn, $abbr1);
    $hasTable  = ($siteGenes !== null);
    if (!$hasTable) {
        $nNoTable++;
    }
    /* 这个物种的「基因号 → 基因名」。名字来自注释表的 description，按 mRNA 号连过去，
       整物种一次建好（每个物种 0.2 秒左右，比逐行查快几个数量级）。没有 _locus 表就没戏。 */
    $siteNames = $hasTable ? cnido_gene_name_map($conn, $abbr1, $siteGenes) : array();

    /* NCBI 基因元数据，分批取（缓存键含 taxid 与批起始号，可断点续跑） */
    $gmeta = array();
    for ($i = 0; $i < count($gids); $i += $OPT['batch']) {
        $chunk = array_slice($gids, $i, $OPT['batch']);
        $url = 'https://api.ncbi.nlm.nih.gov/datasets/v2/gene/id/' . implode(',', $chunk)
             . '/dataset_report?page_size=1000';
        $cache = $CACHE_DIR . '/gene_' . $taxid . '_' . $chunk[0] . '.json';
        $code  = 0;
        $j     = null;
        /* 失败一次就等于丢掉这 200 个基因的元数据（ADIGI 那次就丢了 200 个，
           而且因为错误信息本身有 bug，看日志还看不出丢了东西）。传输类失败重试。 */
        for ($try = 1; $try <= 3; $try++) {
            $j = gl_json_cached($url, $cache, $OPT['refresh'], $code);
            if ($j !== null) {
                break;
            }
            if ($try < 3) {
                usleep(1500000);
            }
        }
        if ($j === null) {
            /* 注意：$code 后面要紧跟中文时必须加花括号。PHP 的简单插值把 \x80-\xff
               也算作变量名字符，写成 "$code（该批…" 会把「（该批…」整个吞进变量名，
               于是报 Undefined variable 且打印出来是空的。 */
            fwrite(STDERR, "  取基因元数据失败 taxid={$taxid} HTTP={$code} —— 该批跳过，其余继续\n");
            continue;
        }
        foreach ((isset($j['reports']) ? $j['reports'] : array()) as $rep) {
            if (!isset($rep['gene']['gene_id'])) {
                continue;
            }
            $g = $rep['gene'];
            $gmeta[(string) $g['gene_id']] = array(
                'symbol'    => isset($g['symbol']) ? (string) $g['symbol'] : '',
                'locus_tag' => isset($g['locus_tag']) ? (string) $g['locus_tag'] : '',
                'alt'       => array_merge(
                    isset($g['alternate_names']) ? (array) $g['alternate_names'] : array(),
                    isset($g['synonyms']) ? (array) $g['synonyms'] : array()
                ),
            );
        }
        if ($i + $OPT['batch'] < count($gids)) {
            usleep($OPT['sleep']);
        }
    }
    $nGeneMeta += count($gmeta);

    $nOk = 0;
    foreach ($gids as $gid) {
        $gm  = isset($gmeta[$gid]) ? $gmeta[$gid] : null;
        $sym = $gm !== null ? $gm['symbol'] : '';
        $lt  = $gm !== null ? $gm['locus_tag'] : '';
        $site = null;
        $src  = 'none';

        if (!$hasTable) {
            $src = 'no_locus_table';
        } elseif ($gm !== null) {
            if ($sym !== '' && isset($siteGenes[$sym])) {
                $site = $sym; $src = 'symbol';
            } elseif ($lt !== '' && isset($siteGenes[$lt])) {
                $site = $lt;  $src = 'locus_tag';
            } else {
                foreach ($gm['alt'] as $a) {
                    if ($a !== '' && isset($siteGenes[$a])) {
                        $site = $a; $src = 'alt'; break;
                    }
                }
            }
        }
        if ($gm === null) {
            $nGeneMiss++;
        }
        if ($site !== null) {
            $nMapOk++; $nOk++;
        } elseif ($hasTable) {
            $nMapNo++;
        }
        $srcCount[$src] = (isset($srcCount[$src]) ? $srcCount[$src] : 0) + 1;

        /* 这个基因挂靠的文献（直接查归并好的 (taxid,gene) → pmids） */
        $key = $taxid . "\t" . $gid;
        if (!isset($taxGenePmids[$key])) {
            continue;
        }
        /* 名字与代表蛋白号都从同一张表里取。空串一律存 NULL：没有就是没有，
           不要让 '' 变成一个能搜到的值（`site_name = ''` 之类）。 */
        $sname = ($site !== null && isset($siteNames[$site])
                  && $siteNames[$site]['name'] !== '') ? $siteNames[$site]['name'] : null;
        $smrna = ($site !== null && isset($siteNames[$site])
                  && $siteNames[$site]['mrna'] !== '') ? $siteNames[$site]['mrna'] : null;
        foreach ($taxGenePmids[$key] as $pmid) {
            $pending[] = array(
                $abbr1, $species, $taxid, $gid, $sym, $lt,
                $site, $sname, $smrna, $src, $pmid,
                in_array($pmid, GL_ANNOTATION_PMIDS, true) ? '1' : '0',
            );
            $nEmited++;
        }
        if (count($pending) >= $OPT['flush']) {
            gl_flush($conn, $pending, 'gene_literature_new', $OPT['commit'], $nWritten);
        }
    }

    printf("  %-22s 基因 %6s  元数据 %6s  映射 %6s (%.0f%%)%s\n",
        $abbr1, number_format(count($gids)), number_format(count($gmeta)),
        number_format($nOk), count($gids) ? 100 * $nOk / count($gids) : 0,
        $hasTable ? '' : '   [站点无 _locus 表]');
}

gl_flush($conn, $pending, 'gene_literature_new', $OPT['commit'], $nWritten);
echo "\n";

/* ---------------------------------------------------------------- 收尾 */

printf("基因元数据     : %s 条（其中 NCBI 查无此号 %s）\n",
    number_format($nGeneMeta), number_format($nGeneMiss));
printf("映射到站点基因 : %s   映不上(有表) %s   站点无表 %s\n",
    number_format($nMapOk), number_format($nMapNo), number_format($nNoTable));
echo  "映射方式分布   : ";
foreach ($srcCount as $k => $v) {
    printf("%s=%s  ", $k, number_format($v));
}
echo "\n";
printf("生成关系行数   : %s\n", number_format($nEmited));

if ($OPT['commit']) {
    /* 原子换名：站点只会看到旧表或新表。上次的 _prev 要先删，否则 RENAME 目标已存在会失败。 */
    if (!$conn->query("DROP TABLE IF EXISTS `gene_literature_prev`")
        || !$conn->query("RENAME TABLE `gene_literature` TO `gene_literature_prev`,
                                       `gene_literature_new` TO `gene_literature`")) {
        fwrite(STDERR, "换表失败: {$conn->error}\n");
        exit(1);
    }
    printf("已写入 gene_literature（%s 行），旧表保留为 gene_literature_prev\n", number_format($nWritten));

    /* literature 表整表重建（327 行，直接 REPLACE 即可，不必折腾临时表） */
    $conn->query("TRUNCATE TABLE `literature`");
    $vals = array();
    foreach ($meta as $p => $m) {
        $vals[] = "('" . implode("','", array_map(array($conn, 'real_escape_string'), array(
            $p, mb_substr($m['title'], 0, 500), mb_substr($m['journal'], 0, 250),
            (string) $m['year'], mb_substr($m['author'], 0, 150),
            (string) $pmStat[$p]['n'], (string) count($pmStat[$p]['taxa']),
            /* 必须 (string) 一下：$p 是 $meta 的**数组键**，而 PHP 会把纯数字的
               字符串键转成 int，于是 in_array(22301074, array('22301074',…), true)
               严格比较恒为 false —— literature 表的注释来源标记就全成了 0。
               （gene_literature 那边 $pmid 是数组**值**，一直是字符串，所以没这个毛病。） */
            in_array((string) $p, GL_ANNOTATION_PMIDS, true) ? '1' : '0', $now,
        ))) . "')";
    }
    foreach (array_chunk($vals, 200) as $c) {
        if (!$conn->query("INSERT INTO `literature`
                (pmid, title, journal, pub_year, first_author, n_genes, n_species,
                 is_annotation_method, fetched_at) VALUES " . implode(',', $c))) {
            fwrite(STDERR, "写 literature 失败: {$conn->error}\n");
            exit(1);
        }
    }
    printf("已写入 literature（%d 篇）\n", count($meta));

    /* 页面上的概览行（物种数 / 文献数 / 基因数）是带 24 小时 TTL 的缓存，
       换表后必须删掉，否则用户会看到「表已经换了、数字还是旧的」。 */
    require_once __DIR__ . '/cache.php';
    $ovPath = cnido_cache_path('gene_lit_overview.json');
    if ($ovPath !== '' && is_file($ovPath)) {
        @unlink($ovPath);
        echo "已清掉概览缓存\n";
    }
} else {
    echo "\n（dry-run，未写库。加 --commit 生效）\n";
}

$conn->close();
