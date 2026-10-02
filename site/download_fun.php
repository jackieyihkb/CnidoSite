<?php
/* =====================================================================
 * 文件下载处理
 *
 * 原实现存在三个问题（审稿意见 Referee 3 点 10 提到「请确认所有数据都能
 * 正常访问与下载」）：
 *   1. 路径穿越漏洞：fname 未做任何校验，
 *      download_fun.php?fname=../templatemo_style.css 可读出站内任意文件。
 *   2. 文件不存在时不报错：fopen 失败返回 false，客户端拿到的是
 *      HTTP 200 + 0 字节的空文件，使用者只会以为「下载坏了」。
 *   3. 用 fread() 一次性读入内存，几百 MB 的基因组会撞上 memory_limit
 *      导致响应被截断。
 * 现在改为：basename + realpath 白名单校验 -> 404 明确报错 -> 分块流式输出，
 * 并支持单段 Range 断点续传（见文件末尾的说明）。
 * ===================================================================== */
if (isset($_GET['download'])) {

    $base = realpath(__DIR__ . '/download');
    if ($base === false) {
        http_response_code(500);
        exit('Download directory is not available.');
    }

    // basename() 去掉任何目录成分，realpath() 再确认结果确实落在 download/ 内
    $name = basename(str_replace('\\', '/', trim((string)$_GET['fname'])));
    $path = realpath($base . DIRECTORY_SEPARATOR . $name);

    if ($name === '' || $path === false
        || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0
        || !is_file($path) || !is_readable($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit("File not found: " . $name . "\n\n"
           . "The requested file is not part of the CnidoSite download collection. "
           . "If you followed a link from within the site, please report it via the Contact page.");
    }

    // 关闭可能存在的输出缓冲，避免大文件把内存吃满
    while (ob_get_level() > 0) { ob_end_clean(); }

    /* 断点续传。集合里最大的单个文件 932 MB（Millepora_dichotoma.fa.gz），
       117 个文件超过 100 MB；原来一律回 Accept-Ranges: none，一个连接断掉就得
       从零重来，而 31 GB 的镜像里这是必然会发生的。只支持单段 Range
       （bytes=a-b / bytes=a- / bytes=-N），多段请求按整份返回，这是 RFC 允许的。 */
    $size = filesize($path);
    $start = 0; $end = $size - 1; $partial = false;
    if (isset($_SERVER['HTTP_RANGE'])
        && preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m)
        && ($m[1] !== '' || $m[2] !== '')) {
        if ($m[1] === '') {                              // bytes=-N：末尾 N 字节
            $n = (int)$m[2];
            if ($n > 0) { $start = max(0, $size - $n); $partial = true; }
        } else {
            $start = (int)$m[1];
            if ($m[2] !== '') { $end = min((int)$m[2], $size - 1); }
            $partial = true;
        }
        if ($partial && ($start > $end || $start >= $size)) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            header('Content-Type: text/plain; charset=utf-8');
            exit("Requested range not satisfiable: this file is " . $size . " bytes.\n");
        }
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    if ($partial) {
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        header('Content-Length: ' . ($end - $start + 1));
    } else {
        header('Content-Length: ' . $size);
    }

    // 流式输出，不整体载入内存；有 Range 时先 seek，再按剩余字节数分块发
    $fp = fopen($path, 'rb');
    if ($fp === false) {
        http_response_code(500);
        exit("Cannot open file: " . $name . "\n");
    }
    if ($start > 0) { fseek($fp, $start); }
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp)) {
        $chunk = fread($fp, (int) min(262144, $left));
        if ($chunk === false || $chunk === '') { break; }
        echo $chunk;
        $left -= strlen($chunk);
    }
    fclose($fp);
    exit;
}
