<?php
/**
 * 刷新统计缓存。只能从命令行运行，不要挂到网页上。
 *
 * 用途：includes/stats.php 里的 cnido_stats() 在网页请求里只读缓存、不现算
 * （一次完整计算约 79 秒，见该文件顶部说明）。这份缓存由本脚本刷新。
 *
 * 手工执行：
 *   php -c /etc/php/7.4/apache2/php.ini /var/www/html/CnidoSite/includes/stats_refresh.php
 *
 * 挂 cron 每天跑一次（凌晨低峰）：
 *   17 3 * * *  php -c /etc/php/7.4/apache2/php.ini \
 *               /var/www/html/CnidoSite/includes/stats_refresh.php >> /var/log/cnidosite-stats.log 2>&1
 *
 * 加 --check 只做体检：打印当前缓存里的每个数字与生成时间，不重算。
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("This script is CLI-only.\n");
}

require_once __DIR__ . '/stats.php';

$check = in_array('--check', $argv, true);

$conn = @new mysqli('localhost', 'jackie', '<REDACTED>', 'cnidaria');
if ($conn->connect_error) {
    fwrite(STDERR, "DB connection failed: " . $conn->connect_error . "\n");
    exit(1);
}

if ($check) {
    $c = cnido_stats_read(cnido_stats_cache_file());
    if ($c === null) {
        echo "No statistics cache yet. Run without --check to build it.\n";
        exit(1);
    }
    $age = time() - $c['ts'];
    printf("cache generated : %s (%d s ago, TTL %d s)%s\n",
        $c['generated'], $age, cnido_stats_ttl(),
        $age >= cnido_stats_ttl() ? '  [STALE]' : '');
    $L = cnido_stat_labels();
    foreach ($c['stats'] as $k => $v) {
        printf("  %-32s %16s   %s\n", $k, number_format((float)$v),
            isset($L[$k]) ? $L[$k] : $k);
    }
    exit(0);
}

$t0 = microtime(true);
echo "recomputing statistics ... ";
$stats = cnido_stats_compute($conn);
$el = microtime(true) - $t0;

if ($stats === null) {
    fwrite(STDERR, "FAILED after " . round($el, 1) . "s (query error or a missing table).\n");
    fwrite(STDERR, "Existing cache, if any, was left untouched.\n");
    exit(1);
}

if (!cnido_stats_store(cnido_stats_cache_file(), $stats)) {
    fwrite(STDERR, "computed in " . round($el, 1) . "s but could not write "
        . cnido_stats_cache_file() . "\n");
    exit(1);
}

printf("done in %.1fs, %d values written to %s\n", $el, count($stats), cnido_stats_cache_file());
