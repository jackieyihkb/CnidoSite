<?php
/* =====================================================================
 * tmp/ 里两个文件的 HTTP 取回入口。
 *
 * 背景（2026-09-21）：
 *   tmp/ 是各分析模块的工作目录（GSEA、共表达网络按作业号把输入与中间
 *   结果写在这里），累计 2200 多个文件，Apache 原先开着目录浏览 —— 也就是
 *   说任何人的上传列表和结果都能被逐个下载。为此 tmp/.htaccess 加了
 *   `Require all denied` 整目录拒绝 HTTP。
 *
 *   那条封锁的理由是「tmp/ 只由服务端脚本按文件系统路径读取，不走 HTTP」，
 *   这话对绝大多数文件成立，但漏了两处**确实由浏览器直接取**的链接：
 *     · GSEA 结果页的「N genes」→ /tmp/<作业号>.query（用户自己提交的基因表）
 *     · 共表达网络详情页的 Download File → /tmp/tmp_edge<作业号>.inc
 *   封锁之后这两处变成 403「You don't have permission to access this
 *   resource.」，即用户报告的故障。
 *
 * 这个脚本就是给这两处开的、**逐个名字放行**的窄口：
 *   · 只认下面白名单里两种文件名，其余一律 404 —— 目录浏览与「猜号下载
 *     任意作业的任意文件」都仍然关着；
 *   · 白名单本身禁止路径分隔符与多余的点，杜绝 `../` 穿越；
 *   · 再做一次 realpath 包含性检查作为兜底。
 *
 * 为什么不是「把 .htaccess 删掉让链接复活」：那会把 2200 个文件连同目录
 * 列表一起重新公开，包括别人作业的上传内容。窄口与封锁并不冲突。
 *
 * 访问模型与结果页一致：GSEAresult.php / network_detail.php 本来就是凭作业号
 * 公开访问的，本脚本不放宽这一点（要真正私有化得另加令牌/会话，不在本次范围）。
 *
 * 用法：/tmp_download.php?f=<文件名>
 * ===================================================================== */

/* 允许取回的文件名 —— 每一条都必须整体匹配，不允许任何路径成分。
   1) GSEA 的查询基因表：<作业号>.query，作业号为纯数字（与 compute.php /
      GSEAresult.php 的校验同一口径）。
   2) 共表达网络的边表：tmp_edge<作业号>.inc，作业号限定字母数字。 */
$__tmp_dl_allow = array(
    '/^([0-9]{1,18})\.query$/'              => array('type' => 'text/plain; charset=utf-8',
                                                    'disp' => 'inline'),
    '/^tmp_edge([0-9A-Za-z]{1,32})\.inc$/'  => array('type' => 'text/plain; charset=utf-8',
                                                    'disp' => 'attachment'),
);

$name = isset($_GET['f']) ? (string)$_GET['f'] : '';

$hit = null;
foreach ($__tmp_dl_allow as $re => $meta) {
    if (preg_match($re, $name)) { $hit = $meta; break; }
}
if ($hit === null) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain; charset=utf-8');
    echo "No such downloadable file.\n";
    exit;
}

$dir  = __DIR__ . '/tmp';
$path = $dir . '/' . $name;

/* 兜底：解析后的真实路径必须仍落在 tmp/ 内且是普通文件。 */
$real = realpath($path);
$base = realpath($dir);
if ($real === false || $base === false || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0
    || !is_file($real)) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain; charset=utf-8');
    echo "No such downloadable file.\n";
    exit;
}

header('Content-Type: ' . $hit['type']);
/* 文件名来自已通过白名单的 $name，不含引号以外的特殊字符；这里再挡一层。 */
header('Content-Disposition: ' . $hit['disp'] . '; filename="' . addcslashes($name, "\"\\") . '"');
header('Content-Length: ' . filesize($real));
/* 内容是纯文本，明确禁止浏览器做类型嗅探，避免被当成 HTML 解释。 */
header('X-Content-Type-Options: nosniff');
/* 作业文件可能被同名重建，别让中间缓存留住旧内容。 */
header('Cache-Control: no-store');

readfile($real);
