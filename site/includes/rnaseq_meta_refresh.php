<?php
/**
 * 生成 RNA-seq 样本元数据索引 data/rnaseq_samples.json。只能从命令行运行，
 * 不要挂到网页上。
 *
 * 为什么需要它
 * ------------
 * gene_detail.php 的「Expression pattern」面板要按物种的 <ABBR>_TPM 表画出每个
 * RNA-seq 样本的表达量。表里的列名本身就是样本名，形如
 *
 *     SRR7992468_polyp_endoderm_from_body_column_polyp
 *     ^^^^^^^^^^ run 号          ^^^^^^^^^^^^^^^^^^^^^^^ ^^^^^
 *                               tissue                  dev_stage
 *
 * 也就是说 1385 个样本里有 1335 个可以从列名直接读出组织和发育阶段。剩下的
 * （例如 MCAPI 的 48 列只有裸 run 号）以及列名里没有的处理/项目/研究编号，
 * 则需要外部元数据。这些元数据在
 *
 *     /mnt/sda/jackie/cnidaria_omics/RNA-seq/sample
 *
 * 这个制表符分隔的表里（1762 行，列与 cnidaria.sample 表一致：拉丁名、
 * 下划线拉丁名、BioProject、SRP 研究号、SRA experiment 号、**SRA run 号**、
 * Layout、tissue、dev_stage、Treatment）。关键是它带 run 号，而 cnidaria 库里
 * 那张 15642 行的 sample 表只有 experiment 号（SRX），没有 run 号 —— 而 _TPM
 * 表的列名用的正是 run 号。这张表就是两者之间的桥。
 *
 * 网页端不去读 /mnt（那是数据盘，网页进程未必读得到，也不该依赖），所以由这个
 * 脚本离线生成一份 run 号索引放到站点内，页面只读这一份 JSON。
 *
 * 手工执行：
 *   php -c /etc/php/7.4/apache2/php.ini /var/www/html/CnidoSite/includes/rnaseq_meta_refresh.php
 *
 * 加 --check 只做体检：报告当前索引的生成时间、条数，以及各个 <ABBR>_TPM 表的
 * 样本列有多少能在索引里找到，不重写文件。
 *
 * 加 --src=/别的/路径 可换源文件（默认见下面 RNASEQ_SAMPLE_TSV）。
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("This script is CLI-only.\n");
}

define('RNASEQ_SAMPLE_TSV', '/mnt/sda/jackie/cnidaria_omics/RNA-seq/sample');
define('RNASEQ_INDEX_VERSION', 1);

$check = in_array('--check', $argv, true);
$src = RNASEQ_SAMPLE_TSV;
foreach ($argv as $a) {
    if (strpos($a, '--src=') === 0) { $src = substr($a, 6); }
}

/* 站点根目录下的 data/（与 data/overview_pairs.json、phylotree/data 同一处） */
$out = dirname(__DIR__) . '/data/rnaseq_samples.json';

/* ------------------------------------------------------------------ */
/* --check：只读现有索引，报告覆盖率                                   */
/* ------------------------------------------------------------------ */
if ($check) {
    if (!is_readable($out)) {
        fwrite(STDERR, "还没有索引文件 $out。去掉 --check 跑一次即可生成。\n");
        exit(1);
    }
    $j = json_decode(file_get_contents($out), true);
    if (!is_array($j) || !isset($j['runs'])) {
        fwrite(STDERR, "索引文件损坏或格式不对: $out\n");
        exit(1);
    }
    printf("索引文件 : %s\n", $out);
    printf("生成时间 : %s (%d 秒前)\n",
        date('Y-m-d H:i:s', (int)$j['built']), time() - (int)$j['built']);
    printf("来源     : %s\n", isset($j['source']) ? $j['source'] : '(未记录)');
    printf("条目数   : %d 个 run\n\n", count($j['runs']));

    $conn = cnid_rnaseq_conn();
    rnaseq_meta_report($conn, $j['runs']);
    $conn->close();
    exit(0);
}

/* ------------------------------------------------------------------ */
/* 正式生成                                                            */
/* ------------------------------------------------------------------ */
$t0 = microtime(true);

if (!is_readable($src)) {
    fwrite(STDERR, "读不到源文件: $src\n");
    exit(1);
}

$conn = cnid_rnaseq_conn();

/* cnidaria.sample 表（15642 行、按 experiment 号）在 tissue / dev_stage /
 * Treatment 上比源文件全一些，所以先整表读进内存按 SRX 索引，逐条取非空值
 * 补进来。源文件里 dev_stage 常年是空的，这一补就有内容了。 */
$bySrx = array();
$q = mysqli_query($conn, "SELECT Experiment_Accession, Project_ID, Study_Accession, "
                       . "Layout, tissue, dev_stage, Treatment, Latin_name FROM sample");
if ($q) {
    while ($r = mysqli_fetch_row($q)) {
        $srx = trim((string)$r[0]);
        if ($srx === '') { continue; }
        $bySrx[$srx] = array(
            'project' => rnaseq_meta_clean($r[1]),
            'study'   => rnaseq_meta_clean($r[2]),
            'layout'  => rnaseq_meta_clean($r[3]),
            'tissue'  => rnaseq_meta_clean($r[4]),
            'stage'   => rnaseq_meta_clean($r[5]),
            'treat'   => rnaseq_meta_clean($r[6]),
            'latin'   => rnaseq_meta_clean($r[7]),
        );
    }
    printf("cnidaria.sample 读入 %d 个 experiment\n", count($bySrx));
} else {
    fwrite(STDERR, "警告: 查 cnidaria.sample 失败，只用品源文件里的字段。\n");
}

$fh = fopen($src, 'r');
if (!$fh) {
    fwrite(STDERR, "打不开源文件: $src\n");
    exit(1);
}

$runs = array();
$nLine = 0;
$nSkip = 0;
while (($line = fgets($fh)) !== false) {
    $nLine++;
    $f = explode("\t", rtrim($line, "\n"));
    if (count($f) < 8) { $nSkip++; continue; }
    $latin  = trim($f[0]);
    $run    = isset($f[5]) ? trim($f[5]) : '';
    $srx    = isset($f[4]) ? trim($f[4]) : '';
    if ($run === '' || !preg_match('/^[SED]RR\d+$/', $run)) { $nSkip++; continue; }
    if ($latin === '' || strcasecmp($latin, 'Latin_name') === 0) { $nSkip++; continue; }

    /* 源文件自带的值 */
    $rec = array(
        'latin'   => $latin,
        'x'       => $srx,
        'project' => rnaseq_meta_clean(isset($f[2]) ? $f[2] : ''),
        'study'   => rnaseq_meta_clean(isset($f[3]) ? $f[3] : ''),
        'layout'  => rnaseq_meta_clean(isset($f[6]) ? $f[6] : ''),
        'tissue'  => rnaseq_meta_clean(isset($f[7]) ? $f[7] : ''),
        'stage'   => rnaseq_meta_clean(isset($f[8]) ? $f[8] : ''),
        'treat'   => rnaseq_meta_clean(isset($f[9]) ? $f[9] : ''),
    );

    /* 用库里的 sample 表补空字段（不动已有值） */
    if ($srx !== '' && isset($bySrx[$srx])) {
        foreach ($bySrx[$srx] as $k => $v) {
            if ($v !== '' && $rec[$k] === '') { $rec[$k] = $v; }
        }
    }
    if ($rec['latin'] === '' && isset($bySrx[$srx]['latin'])) { $rec['latin'] = $bySrx[$srx]['latin']; }

    /* 空字段不写进 JSON，省体积 */
    $rec = array_filter($rec, function ($v) { return $v !== ''; });
    $runs[$run] = $rec;
}
fclose($fh);

if (!$runs) {
    fwrite(STDERR, "源文件里没解析出任何 run，未写文件。\n");
    exit(1);
}

$payload = array(
    'v'      => RNASEQ_INDEX_VERSION,
    'built'  => time(),
    'source' => $src,
    'note'   => 'run 号 -> RNA-seq 样本元数据索引，供 gene_detail.php 的表达面板使用；'
              . '由 includes/rnaseq_meta_refresh.php 生成，勿手改。',
    'runs'   => $runs,
);

$dir = dirname($out);
if (!is_dir($dir) || !is_writable($dir)) {
    fwrite(STDERR, "输出目录不可写: $dir\n");
    exit(1);
}
/* 先写临时文件再 rename —— 页面可能正好在读到一半的文件（与 coverage 缓存同一约定） */
$tmp = $out . '.' . getmypid() . '.tmp';
$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($tmp, $json) === false || !rename($tmp, $out)) {
    @unlink($tmp);
    fwrite(STDERR, "写文件失败: $out\n");
    exit(1);
}

printf("源文件 %s：%d 行，跳过 %d 行，解析出 %d 个 run\n", $src, $nLine, $nSkip, count($runs));
printf("已写出 %s (%.1f KB)\n", $out, filesize($out) / 1024);

rnaseq_meta_report($conn, $runs);
$conn->close();
printf("\n耗时 %.1f 秒\n", microtime(true) - $t0);
exit(0);


/* ==================================================================== */
/* 辅助函数                                                              */
/* ==================================================================== */

/**
 * 清掉源数据里表示「没有」的各种写法。
 * 'NA'/'N/A'/'None'/'nan' 在 SRA 导出里都出现过，和空串一样处理。
 */
function rnaseq_meta_clean($v)
{
    $v = trim((string)$v);
    if ($v === '' || $v === '-' || $v === '--') { return ''; }
    if (in_array(strtolower($v), array('na', 'n/a', 'none', 'null', 'nan'), true)) { return ''; }
    return $v;
}

function cnid_rnaseq_conn()
{
    $conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    if ($conn->connect_error) {
        fwrite(STDERR, "数据库连接失败: " . $conn->connect_error . "\n");
        exit(1);
    }
    return $conn;
}

/**
 * 报告：每个 <ABBR>_TPM 表的样本列里，有多少能在索引里找到元数据。
 * 这是这个脚本最该被人看到的一段输出 —— 找不到的那些会退回「从列名解析」，
 * 列名也没有的就只能显示裸 run 号。
 */
function rnaseq_meta_report($conn, array $runs)
{
    $tabs = array();
    $q = mysqli_query($conn, "SHOW TABLES LIKE '%\\_TPM'");
    if (!$q) {
        fwrite(STDERR, "查 _TPM 表失败\n");
        return;
    }
    while ($r = mysqli_fetch_row($q)) { $tabs[] = $r[0]; }
    sort($tabs);

    printf("\n%-14s %7s %7s %7s %7s\n", 'TPM 表', '样本列', '索引命中', '列名带组织', '两者皆无');
    $tt = $ti = $tn = $tm = 0;
    foreach ($tabs as $t) {
        $cols = array();
        $q2 = mysqli_query($conn, "SHOW COLUMNS FROM `$t`");
        if (!$q2) { continue; }
        while ($c = mysqli_fetch_row($q2)) {
            $name = $c[0];
            if (!preg_match('/^[SED]RR\d+/', $name)) { continue; }   // 跳过 Gene/avg/std
            $cols[] = $name;
        }
        $hit = $named = 0;
        foreach ($cols as $c) {
            preg_match('/^([SED]RR\d+)/', $c, $m);
            $run = $m[1];
            if (isset($runs[$run])) { $hit++; }
            if (preg_match('/^[SED]RR\d+_./', $c)) { $named++; }
        }
        $none = 0;
        foreach ($cols as $c) {
            preg_match('/^([SED]RR\d+)/', $c, $m);
            if (!isset($runs[$m[1]]) && !preg_match('/^[SED]RR\d+_./', $c)) { $none++; }
        }
        $tt += count($cols); $ti += $hit; $tn += $named; $tm += $none;
        printf("%-14s %7d %7d %7d %7d\n", $t, count($cols), $hit, $named, $none);
    }
    printf("%-14s %7d %7d %7d %7d\n", '合计', $tt, $ti, $tn, $tm);
    if ($tm > 0) {
        printf("\n提示: 有 %d 个样本列既不在索引里、列名也没带组织信息，页面上只会显示 run 号。\n", $tm);
    }
}
