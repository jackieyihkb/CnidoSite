<?php
/* =====================================================================
 * 缓存文件该放哪儿
 *
 * 站上两份缓存（coverage_cache.json、stats_cache.json）原本都写在 includes/ 里。
 * includes/ 属于部署用户（jackie），Apache 以 www-data 跑，只拿到 r-x ——
 * file_put_contents 一律失败，而调用方用的是 @ 抑制写法，失败得悄无声息：
 *
 *   - coverage.php 的缓存从来没有真正写过。它的 TTL 是 1 小时，设计上就该由
 *     网页自己刷新，可每次请求都写不进去，于是每一次打开含覆盖矩阵的页面
 *     （coverage_matrix.php、data_statistics.php、species_portal.php）
 *     都要重扫一遍 information_schema（1400+ 张表），实测约 6.7 秒。
 *   - stats_cache.json 幸运地还在，是 CLI 以 jackie 身份生成的；但它一旦被删，
 *     网页既读不到也算不动（一次全量统计约 79 秒），且重建后仍然写不回去。
 *
 * 这里给两份缓存找一个「web 进程真的写得进去」的目录。
 *
 * 排序原则不是「哪儿最私有」，而是**CLI 与 web 必须落在同一个文件上**：
 *   · includes/ 是 jackie:jackie 775，jackie 写得进去、www-data 写不进去。
 *     只有它被选中时，用 shell 重新生成的数据网页读不到 —— 两边各存一份。
 *   · /tmp 更糟：apache2.service 带 PrivateTmp=true，网页进程的 /tmp 是它自己的
 *     命名空间（/tmp/systemd-private-<hash>-apache2.service-<suffix> 下的 tmp/），
 *     从 shell 里既看不到也写不进去。
 *   · 站点的 tmp/ 是 777，两个身份都写得进去，所以是唯一能保证两边一致的位置。
 *     它位于文档根下，但 tmp/.htaccess 已整目录 deny，缓存不会被下载；内容又是
 *     页面上本来就公开的覆盖统计，留在那里没有额外暴露。
 *
 * 2026-09-21 新增 Pachycerianthus multiplicatus 时正是被这件事卡住：物种、分类、
 * 描述都已入库，CLI 生成的缓存也有 326 个物种，网页却一直显示 325 个，且新物种
 * 在覆盖矩阵里查不到 —— 网页用的是它 PrivateTmp 里那份按 TTL 自行续命的旧缓存，
 * 任何 CLI 侧的重新生成都到不了它手上。所以 tmp/ 必须排在前面，includes/ 只作
 * 兜底（tmp/ 不可写时才用）。
 *
 * 三个都写不了时返回空串，调用方照常工作，只是每次重新计算。
 * ===================================================================== */

/**
 * 返回一个可写的缓存目录；都不行时返回 ''。
 *
 * @param string $sub 可选子目录名（相对 includes/），用于把缓存与代码分开
 */
function cnido_cache_dir()
{
    static $dir = null;
    if ($dir !== null) { return $dir; }

    $cands = array(
        __DIR__ . '/../tmp',           // 站点自己的 tmp/（777，CLI 与 web 唯一共用的一份）
        __DIR__,                       // includes/：jackie 可写、www-data 不可写，仅兜底
        sys_get_temp_dir(),            // /tmp：再兜底。web 的 /tmp 是 PrivateTmp，CLI 看不到
    );
    foreach ($cands as $d) {
        if (is_string($d) && $d !== '' && is_dir($d) && is_writable($d)) {
            $dir = rtrim($d, '/');
            return $dir;
        }
    }
    $dir = '';
    return $dir;
}

/**
 * 缓存文件的完整路径；没有可写目录时返回 ''，调用方应据此跳过缓存。
 *
 * @param string $name 文件名，例如 'coverage_cache.json'
 */
function cnido_cache_path($name)
{
    /* 文件名由代码写死，但仍然挡一道目录分隔符，避免将来有人把变量拼进来 */
    $name = basename((string)$name);
    if ($name === '' || $name === '.' || $name === '..') { return ''; }
    $d = cnido_cache_dir();
    return $d === '' ? '' : $d . '/' . $name;
}
