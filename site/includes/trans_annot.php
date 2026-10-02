<?php
/**
 * trans_annot.php —— 一个组装转录组里，**预测蛋白**有多少个拿到了各来源的功能注释。
 *
 * ⚠ 先说清一件事，它决定了这个文件为什么存在：
 *   trans_assembly_species 那张汇总表里的 n_uniprot / n_pfam / n_panther /
 *   n_interpro / n_go / n_kegg **不是蛋白质个数，是基因个数**。实测（2026-09-26）：
 *
 *     AALAT  reps=20,797 = COUNT(DISTINCT gene)；n_uniprot=9,831 =
 *            COUNT(DISTINCT gene) WHERE uniprot<>''；n_go、n_kegg 同样一一对上；
 *            NVECT、PDAMI 也各列全中。
 *
 *   而 trans_assembly 一张表是一行一个**预测蛋白**（同一基因的多条 isoform 各占一行，
 *   注释相同），页面上列的是蛋白、筛选的也是蛋白。两者差 2 倍左右：
 *   AALAT 有 UniProt 命中的蛋白是 20,855 个，而 n_uniprot 是 9,831。
 *   早先页面把 n_* 标成「proteins annotated」并拿它画覆盖图，于是图上 KEGG 那根条
 *   写着 6,028，而点下去筛出 13,489 条 —— 图和表说的不是一件事。
 *
 * 所以本文件用**和页面同样的单位**（蛋白/行）现算一遍：一次扫描，
 * 七个 SUM(CASE …) 一起出（六个来源 + 并集），分子分母因此必然对得上。
 * 并集（「至少命中一个来源」）没有现成的列，也正是使用者最先要问的那个数。
 *
 * 代价：一次扫描实测 39k 行的 AALAT 62ms、100k 行的 PDAMI 182ms，而整页渲染只要
 * 10ms，每次请求都算会拖慢一个数量级，所以按本站既有做法缓存到 tmp/
 * （CLI 与 web 唯一共用的可写目录，见 includes/cache.php 的长注释）：
 * 命中即返回，未命中就现算一次再落盘。TTL 给 7 天 —— 这些数只在转录组模块重灌时
 * 才变。缓存里带 V 版本号：值的结构变了（比如从单个整数改成数组）就整份作废，
 * 免得新代码读到旧结构。
 *
 * 拿不到（表没了、查询失败）返回 null，页面据此**不画这一块**，而不是印一个 0
 * —— 「没有任何蛋白有注释」和「不知道」是两件事。
 */

if (!function_exists('cnido_trans_annot_counts')) {

/** 缓存值结构版本；改了返回结构就 +1，旧缓存自动作废。 */
define('CNIDO_TRANS_ANNOT_V', 2);

/**
 * 一个组装里各来源注释到的**蛋白**数。
 *
 * @param  mysqli $conn
 * @param  string $abbr1 物种短码
 * @return array|null array('total','uniprot','pfam','panther','interpro','go','kegg','any')
 *                    全部是蛋白（行）数；查不到返回 null
 */
function cnido_trans_annot_counts($conn, $abbr1)
{
    static $memo = array();
    if (!$conn || !preg_match('/^[A-Za-z0-9_]{1,32}$/', (string) $abbr1)) {
        return null;
    }
    if (array_key_exists($abbr1, $memo)) {
        return $memo[$abbr1];
    }

    $ttl = 7 * 86400;
    /* 用 cnido_cache_path 而不是直接拼 tmp/：目录取的是站点约定的那个，
       不在这里再写一份「哪个目录可写」的知识。cache.php 自带 require 守卫。 */
    require_once __DIR__ . '/cache.php';
    $path = function_exists('cnido_cache_path') ? cnido_cache_path('trans_annot.json') : '';

    $all = array();
    if ($path !== '' && is_file($path)) {
        $j = json_decode((string) @file_get_contents($path), true);
        /* 整份缓存一个文件：物种两百多个，一个物种一项，比一个物种一个文件少两百次
           stat。带 built 是为了整体过期后再重算一批。 */
        if (is_array($j) && isset($j['v']) && $j['v'] === CNIDO_TRANS_ANNOT_V
            && isset($j['built']) && (time() - (int) $j['built']) < $ttl
            && isset($j['n']) && is_array($j['n'])) {
            $all = $j['n'];
        }
    }
    if (isset($all[$abbr1]) && is_array($all[$abbr1])) {
        return $memo[$abbr1] = $all[$abbr1];
    }

    $e = $conn->real_escape_string($abbr1);
    /* 判空用 COALESCE(col,'') <> ''：这几个注释列 NULL 和空串混着（没命中的分支留下
       空串，改过结构的行留下 NULL），写成 col <> '' 的话 NULL 行会因为结果是 NULL
       被悄悄排除，数出来的数偏小 —— 而页面上的筛选条件也是
       `IS NOT NULL AND col <> ''`，两处必须给出同一个集合。
       FORCE INDEX 和页面上的列表查询用同一个索引（abbr1 是前导列），
       否则优化器可能挑 PRIMARY 从头扫。 */
    $cols = array('uniprot', 'pfam', 'panther', 'interpro', 'go', 'kegg');
    $sel  = array('COUNT(*) AS total');
    $any  = array();
    foreach ($cols as $c) {
        $sel[] = "SUM(COALESCE($c, '') <> '') AS $c";
        $any[] = "COALESCE($c, '') <> ''";
    }
    $sel[] = 'SUM(' . implode(' OR ', $any) . ') AS any';

    $q = @$conn->query("SELECT " . implode(', ', $sel) . "
                          FROM trans_assembly FORCE INDEX (ix_abbr_id)
                         WHERE abbr1 = '$e'");
    if (!$q) {
        return $memo[$abbr1] = null;          // 查不动就不写缓存，也不印
    }
    $r = $q->fetch_assoc();
    $out = array();
    foreach (array_merge(array('total'), $cols, array('any')) as $k) {
        $out[$k] = (int) $r[$k];
    }

    $all[$abbr1] = $out;
    if ($path !== '') {
        $tmp = $path . '.tmp.' . getmypid();
        if (@file_put_contents($tmp, json_encode(array('v' => CNIDO_TRANS_ANNOT_V,
                                                       'built' => time(), 'n' => $all))) !== false) {
            @rename($tmp, $path);             // 先写临时文件再 rename，避免读到半截 JSON
        }
    }
    return $memo[$abbr1] = $out;
}

}   /* function_exists */
