<?php
/**
 * 重建「跨物种功能注释检索」的词表 ds_vocab。只能从命令行运行，不要挂到网页上。
 *
 * 用途：includes/domain_search.php 的 ds_search() 在包含匹配（LIKE '%词%'）之前
 * 先问这张词表，把 148 张注释表缩到"真的可能出现该词"的那几张。没有词表时
 * 查一个不存在的词要全扫 148 张表（实测 20.1 秒），有词表是 0.05 秒。
 * 详见 includes/domain_search.php 顶部说明。
 *
 * ds_vocab 里存的是「(库, 列, 去重后的值) -> 出现过这个值的物种短码列表」，
 * 只收 like 那几列（编号列走 ix_term 索引，不需要词表）。
 *
 * 手工执行（约 6~8 分钟，期间站点照常服务）：
 *   php -c /etc/php/7.4/apache2/php.ini /var/www/html/CnidoSite/includes/ds_vocab_refresh.php
 *
 * 只重建一个库、或只看状态：
 *   ... /ds_vocab_refresh.php --db=ipr
 *   ... /ds_vocab_refresh.php --check
 *
 * 什么时候要重跑：注释表增删了、或某个物种的注释被重新导入过。
 * 词表里记着生成时"有多少张表贡献成功"，和当前实际的表数不一致时
 * ds_vocab_route() 会自动放弃缩范围、退回全表扫描（结果一样，只是慢），
 * 所以词表旧了不会给出错的检索结果，最多是慢。
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("This script is CLI-only.\n");
}

require_once __DIR__ . '/domain_search.php';

$check = in_array('--check', $argv, true);
$only  = null;
foreach ($argv as $a) {
    if (strpos($a, '--db=') === 0) { $only = substr($a, 5); }
}

$conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    fwrite(STDERR, "DB connection failed: " . $conn->connect_error . "\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

$M = ds_dbmaps();
if ($only !== null && !isset($M[$only])) {
    fwrite(STDERR, "--db=$only is not one of: " . implode(', ', array_keys($M)) . "\n");
    exit(1);
}

/* value 列宽 512。词表靠 value 做去重，被截断就会把两个不同的值并成一个 ——
   多收几张表不影响结果，但如果 sql_mode 不是严格模式，截断是静默的，所以
   这里宁可先拦下来。见下面写库前对最长值的检查。 */
$VALUE_MAX = 512;

function ds_vocab_ensure($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS ds_vocab (
                db     varchar(16)  NOT NULL,
                col    varchar(32)  NOT NULL,
                value  varchar(512) NOT NULL,
                abbrs  text         NOT NULL,
                PRIMARY KEY (db, col, value)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!mysqli_query($conn, $sql)) {
        fwrite(STDERR, "Cannot create ds_vocab: " . mysqli_error($conn) . "\n");
        exit(1);
    }
}

/* ---------------------------------------------------------------- --check */
if ($check) {
    $q = @mysqli_query($conn, "SELECT db, col, abbrs FROM ds_vocab WHERE col = '__meta__' ORDER BY db");
    if (!$q) { echo "No ds_vocab table yet. Run without --check to build it.\n"; exit(1); }
    $n = 0;
    while ($r = mysqli_fetch_row($q)) {
        $m = json_decode($r[2], true);
        $live = count(ds_species_list($conn, $r[0]));
        printf("%-8s built %s  tables %d/%d %s\n", $r[0],
            is_array($m) && isset($m['built']) ? $m['built'] : '?',
            is_array($m) && isset($m['ntables']) ? $m['ntables'] : 0, $live,
            (is_array($m) && (int)$m['ntables'] === $live) ? '[in use]' : '[STALE - routing disabled, full scan]');
        $n++;
    }
    if ($n === 0) { echo "ds_vocab is empty.\n"; exit(1); }
    $s = mysqli_fetch_row(mysqli_query($conn,
        "SELECT ROUND(SUM(data_length+index_length)/1048576,1)
         FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ds_vocab'"));
    $n = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM ds_vocab"));
    printf("ds_vocab: %s rows, %s MB\n", number_format($n[0]), $s[0]);
    exit(0);
}

/* ---------------------------------------------------------------- build */
$mode = mysqli_fetch_row(mysqli_query($conn, "SELECT @@sql_mode"));
if (strpos($mode[0], 'STRICT_TRANS_TABLES') === false) {
    fwrite(STDERR, "sql_mode is not strict (" . $mode[0] . "); a too-long annotation value "
        . "would be truncated silently and corrupt the vocabulary. Aborting.\n");
    exit(1);
}

ds_vocab_ensure($conn);

$t0  = microtime(true);
$sum = 0;

foreach ($M as $db => $m) {
    if ($only !== null && $only !== $db) { continue; }

    $cols = array_values(array_unique($m['like']));   // 只需 like 列，编号列走索引
    $tabs = ds_species_list($conn, $db);
    echo "== $db : " . count($tabs) . " tables, columns " . implode(', ', $cols) . "\n";

    if (!mysqli_query($conn, "DELETE FROM ds_vocab WHERE db = '" . mysqli_real_escape_string($conn, $db) . "'")) {
        fwrite(STDERR, "DELETE failed: " . mysqli_error($conn) . "\n");
        exit(1);
    }

    $okTables = 0; $rows = 0; $badTables = array();
    foreach ($tabs as $i => $ab) {
        $abEsc = mysqli_real_escape_string($conn, $ab);
        $tableOk = true;
        foreach ($cols as $col) {
            $colEsc = '`' . str_replace('`', '', $col) . '`';
            // 先看最长值会不会被 value(512) 截断
            $mx = mysqli_fetch_row(mysqli_query($conn,
                "SELECT MAX(LENGTH($colEsc)) FROM `{$ab}_{$db}`"));
            if ($mx === false || ($mx[0] !== null && (int)$mx[0] > $VALUE_MAX)) {
                printf("   ! %s_%s.%s max length %s exceeds %d - skipped\n",
                    $ab, $db, $col, $mx === false ? '?' : $mx[0], $VALUE_MAX);
                $tableOk = false;
                continue;
            }
            $sql = "INSERT INTO ds_vocab (db, col, value, abbrs)
                    SELECT '$db', '" . mysqli_real_escape_string($conn, $col) . "', $colEsc, '$abEsc'
                    FROM `{$ab}_{$db}` GROUP BY $colEsc
                    ON DUPLICATE KEY UPDATE abbrs = CONCAT(abbrs, ',$abEsc')";
            if (!mysqli_query($conn, $sql)) {
                printf("   ! %s_%s.%s: %s\n", $ab, $db, $col, mysqli_error($conn));
                $tableOk = false;
                continue;
            }
            $rows += mysqli_affected_rows($conn);
        }
        if ($tableOk) { $okTables++; }
        else { $badTables[] = $ab; }
        if (($i + 1) % 20 === 0) {
            printf("   %3d/%3d tables  %.0f s\n", $i + 1, count($tabs), microtime(true) - $t0);
        }
    }

    $meta = json_encode(array(
        'ntables' => $okTables,                       // 贡献成功的表数 = 陈旧判断的基准
        'built'   => date('Y-m-d H:i'),
        'columns' => $cols,
    ));
    mysqli_query($conn, "INSERT INTO ds_vocab (db, col, value, abbrs)
                         VALUES ('" . mysqli_real_escape_string($conn, $db) . "', '__meta__', 'meta',
                                 '" . mysqli_real_escape_string($conn, $meta) . "')
                         ON DUPLICATE KEY UPDATE abbrs = VALUES(abbrs)");

    printf("   done: %d/%d tables contributed%s\n", $okTables, count($tabs),
        empty($badTables) ? '' : ', FAILED: ' . implode(', ', $badTables));
    $sum += $rows;
}

mysqli_query($conn, "ANALYZE TABLE ds_vocab");
$s = mysqli_fetch_row(mysqli_query($conn,
    "SELECT ROUND(SUM(data_length+index_length)/1048576,1)
     FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ds_vocab'"));
$n = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM ds_vocab"));
printf("\nds_vocab: %s rows, %s MB, built in %.0f s\n", number_format($n[0]), $s[0], microtime(true) - $t0);
