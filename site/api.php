<?php
/* =====================================================================
 * CnidoSite machine-readable metadata interface
 *
 * 审稿意见 Referee 2 major 9：
 *   "Bulk download and programmatic access would also be highly desirable."
 *
 * 站内此前没有任何脚本化访问入口：所有元数据都只以 HTML 表格呈现，第三方
 * 想把 CnidoSite 的物种清单、数据覆盖情况或下载清单接进自己的流程，只能
 * 解析页面。本文件提供一组只读接口，同一份数据可输出 JSON 或 TSV。
 *
 *    api.php?resource=release            版本号、构建日期、更新周期、变更记录
 *    api.php?resource=stats              站点规模统计
 *    api.php?resource=modules            数据模块清单（key / 名称 / 详情页）
 *    api.php?resource=species            全部物种：拉丁名、缩写、类群
 *    api.php?resource=coverage           物种 × 模块 的数据量矩阵
 *    api.php?resource=downloads          下载文件清单（文件名、大小、下载地址）
 *
 * 参数：
 *    format=json|tsv   默认 json
 *    class=<类群>      仅 species / coverage / downloads，用于按类群过滤
 *
 * 设计上的取舍：
 *  · 只读。没有任何写操作，因此不需要 CSRF 保护或认证；也因此不暴露
 *    任何数据库结构，只输出经过整理的字段。
 *  · resource 是白名单，未知值一律拒绝，不拼接进任何查询。
 *  · class 只允许字母（类群名就是字母），进 SQL 前仍走 mysqli_real_escape_string。
 *  · 不加 rate limit：数据本身是公开的，加限制只会妨碍正当的批量抓取。
 *  · 无 resource 参数时输出一份 HTML 说明页，方便使用者直接在浏览器里试。
 * ===================================================================== */

require_once __DIR__ . '/includes/release.php';
require_once __DIR__ . '/includes/coverage.php';
require_once __DIR__ . '/includes/state.php';

$RESOURCES = array('release', 'stats', 'modules', 'species', 'coverage', 'downloads');

$resource = isset($_GET['resource']) ? trim((string)$_GET['resource']) : '';
$format   = isset($_GET['format'])   ? strtolower(trim((string)$_GET['format'])) : 'json';
$class    = isset($_GET['class'])    ? preg_replace('/[^A-Za-z]/', '', (string)$_GET['class']) : '';
/* 站内历史拼写 Hexactiniaria 一律归一为 Hexacorallia（与 cnido_class_canon 一致），
   否则按旧拼写过滤会静默返回 0 条，看起来像「这个类群没有数据」。 */
if ($class !== '') { $class = cnido_class_canon($class); }

if ($format !== 'tsv') { $format = 'json'; }

/* 未知 resource 一律拒绝（白名单之外没有别的路径）。 */
if ($resource !== '' && !in_array($resource, $RESOURCES, true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'error'     => 'Unknown resource.',
        'requested' => $resource,
        'available' => $RESOURCES,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------------------------------------------------------------
 * 文件清单用的辅助函数
 *
 * cnido_dl_tokens / cnido_dl_species / cnido_dl_kind 现在住在
 * includes/downloads.php —— download.php 页面要用同一套「文件名 -> 物种」
 * 的解析规则来补上页面里还没有链接的文件，两份实现必须一致。
 * ------------------------------------------------------------------- */
require_once __DIR__ . '/includes/downloads.php';

/* ---------------------------------------------------------------------
 * 组装数据
 * ------------------------------------------------------------------- */

$generated = gmdate('c');
$release   = cnido_release();

/* 只有确实需要数据库的资源才连接，说明页和 release 不需要。 */
$needDb = in_array($resource, array('stats', 'modules', 'species', 'coverage', 'downloads'), true);
$conn = null;
if ($needDb) {
    $conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    if ($conn->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => 'Database is temporarily unavailable.'), JSON_PRETTY_PRINT);
        exit;
    }
}

$meta  = array(
    'resource'  => $resource,
    'release'   => $release['version'],
    'build'     => $release['build'],
    'generated' => $generated,
);
$rows  = array();     // 数据行（关联数组），TSV 时取第一行的键作表头
$extra = array();     // 额外的顶层字段（stats 用）

/* ---- release ---- */
if ($resource === 'release') {
    $rows = array();
    foreach (cnido_changelog() as $rel) {
        foreach ($rel['entries'] as $e) {
            $rows[] = array(
                'version' => $rel['version'],
                'date'    => $rel['date'],
                'title'   => $rel['title'],
                'change'  => $e,
            );
        }
    }
    $extra = array(
        'name'      => $release['name'],
        'version'   => $release['version'],
        'previous'  => $release['previous'],
        'build'     => $release['build'],
        'first'     => $release['first'],
        'schedule'  => $release['schedule'],
        'doi'       => $release['doi'],
    );
}

/* ---- modules ---- */
if ($resource === 'modules') {
    foreach (cnido_modules() as $key => $cfg) {
        $rows[] = array(
            'module' => $key,
            'label'  => $cfg['label'],
            'short'  => $cfg['short'],
            'page'   => $cfg['page'],
            'desc'   => $cfg['desc'],
        );
    }
}

/* ---- 以下资源基于覆盖矩阵 ---- */
/* downloads 也要用：物种到类群的映射来自覆盖矩阵的 species 字段。 */
$cov = null;
if (in_array($resource, array('stats', 'species', 'coverage', 'downloads'), true)) {
    $cov = cnido_coverage($conn);
}

if ($resource === 'species') {
    foreach ($cov['species'] as $ab => $info) {
        if ($class !== '' && strcasecmp(trim($info['class']), $class) !== 0) { continue; }
        $rows[] = array(
            'abbr1'   => $ab,
            'species' => $info['species'],
            'abbr'    => $info['abbr'],
            'class'   => $info['class'],
        );
    }
}

if ($resource === 'coverage') {
    $modules = cnido_modules();
    foreach ($cov['species'] as $ab => $info) {
        if ($class !== '' && strcasecmp(trim($info['class']), $class) !== 0) { continue; }
        $row = array(
            'abbr1'   => $ab,
            'species' => $info['species'],
            'class'   => $info['class'],
        );
        foreach ($modules as $m => $cfg) {
            /* 有数据写记录条数，没有数据写 0 —— 0 与「缺失」在矩阵里同义。
               第三态：模块有、但没有可数的记录（coverage.php 的 $flag 标注的
               存在性占位，例如只有基因组级线粒体记录的物种）。写 1 会被读成
               「1 个基因」，写 0 会和「没有这个模块」混同，所以给 null。 */
            $row[$m] = !empty($cov['flag'][$ab][$m]) ? null
                     : (isset($cov['cov'][$ab][$m]) ? (int)$cov['cov'][$ab][$m] : 0);
        }
        $rows[] = $row;
    }
    $meta['note'] = 'One row per species; one column per data module. A number is the record count '
                  . 'held for that species and module, 0 means the module has no data for it, and '
                  . 'null means the module is present but has no record count of its own '
                  . '(currently only Mitogenome, for species catalogued from a genome-level record '
                  . 'without gene-level annotation). Columns that are presence-only by nature — the '
                  . 'ones the coverage matrix shows as a tick, such as Genome, Transcriptome '
                  . 'assembly or JBrowse — hold 1 for every species that has the resource, and that '
                  . '1 is not a record count. In TSV output null is written as NA.';
}

if ($resource === 'stats') {
    $modules = cnido_modules();
    $perModule = array();
    foreach (array_keys($modules) as $m) { $perModule[$m] = 0; }
    foreach ($cov['species'] as $ab => $info) {
        foreach (array_keys($modules) as $m) {
            if (!empty($cov['cov'][$ab][$m])) { $perModule[$m]++; }
        }
    }

    /* 下载目录：文件数、总字节，以及能归到某个物种的文件覆盖了多少个物种。
       最后这个数才是「有多少物种真的能下载到序列」，与物种总数不是一回事。 */
    $files = 0; $bytes = 0;
    list($tok, $keys) = cnido_dl_tokens($conn);
    $spWithFiles = array();
    $dir = __DIR__ . '/download';
    if (is_dir($dir) && ($dh = opendir($dir))) {
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            if (!is_file($p)) { continue; }
            $files++; $bytes += filesize($p);
            $sp = cnido_dl_species($f, $tok, $keys);
            if ($sp !== '') { $spWithFiles[$sp] = true; }
        }
        closedir($dh);
    }

    /* 第一行是站点总量，后面每行一个数据模块。
       回看时注意：两段用的字段名必须能自证含义 —— 早先这里复用了
       data_modules / download_files 两个键分别表示「模块名」和「该模块的物种数」，
       同一个键在两段里含义不同，读起来会误解成模块名和文件数。 */
    $rows[] = array(
        'row_type'                    => 'totals',
        'species_total'               => count($cov['species']),
        'species_with_download_files' => count($spWithFiles),
        'data_module_count'           => count($modules),
        'download_file_count'         => $files,
        'download_bytes'              => $bytes,
        'download_gib'                => round($bytes / 1073741824, 2),
    );
    foreach ($perModule as $m => $n) {
        $rows[] = array(
            'row_type'          => 'module',
            'module'            => $m,
            'species_with_data' => $n,
        );
    }
    $meta['note'] = 'The first row (row_type "totals") holds site totals: species_total is every '
                  . 'species in the catalogue, species_with_download_files is how many of them have '
                  . 'at least one downloadable file, data_module_count is the number of data types '
                  . 'tracked, and download_file_count / download_bytes / download_gib describe the '
                  . 'download collection. Each following row (row_type "module") names one data '
                  . 'type in "module" and gives the number of species that have data for it in '
                  . '"species_with_data".';
}

/* ---- downloads ---- */
if ($resource === 'downloads') {
    list($tok, $keys) = cnido_dl_tokens($conn);

    /* 物种 -> 类群。覆盖矩阵里的 species 可能来自 abbr 表也可能来自 speciesinfo
       表（两处写法偶尔差一个句点），所以再建一份归一化索引兜底。 */
    $classOf = array();
    $classOfNorm = array();
    foreach ($cov['species'] as $ab => $info) {
        $classOf[$info['species']] = $info['class'];
        $nk = strtolower(rtrim(str_replace('_', ' ', trim($info['species'])), '. '));
        if ($nk !== '' && !isset($classOfNorm[$nk])) { $classOfNorm[$nk] = $info['class']; }
    }

    $dir = __DIR__ . '/download';
    $names = array();
    if (is_dir($dir) && ($dh = opendir($dir))) {
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..' || !is_file($dir . '/' . $f)) { continue; }
            $names[] = $f;
        }
        closedir($dh);
    }
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($names as $f) {
        $sp = cnido_dl_species($f, $tok, $keys);
        $cl = '';
        if ($sp !== '') {
            if (isset($classOf[$sp])) {
                $cl = $classOf[$sp];
            } else {
                $nk = strtolower(rtrim(str_replace('_', ' ', trim($sp)), '. '));
                if (isset($classOfNorm[$nk])) { $cl = $classOfNorm[$nk]; }
            }
        }
        if ($class !== '' && strcasecmp(trim($cl), $class) !== 0) { continue; }
        $rows[] = array(
            'file'    => $f,
            'species' => $sp,
            'class'   => $cl,
            'kind'    => cnido_dl_kind($f),
            'bytes'   => filesize($dir . '/' . $f),
            'url'     => 'https://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'cnidosite.org')
                       . '/download_fun.php?download=download&fname=' . rawurlencode($f),
        );
    }
    $meta['note'] = 'One row per downloadable file. "species" is resolved from the file-name '
                  . 'prefix and is empty where the file is not attributable to a single species '
                  . '(for example the miRNA and pan-geneset bundles). '
                  . 'Total rows: ' . count($rows) . '.';
}

/* ---------------------------------------------------------------------
 * 输出
 * ------------------------------------------------------------------- */

$meta['count'] = count($rows);

if ($resource !== '') {

    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=300');

    if ($format === 'tsv') {
        header('Content-Type: text/tab-separated-values; charset=utf-8');
        header('Content-Disposition: attachment; filename="cnidosite-' . $resource . '.tsv"');
        if ($rows) {
            /* 表头取所有行的键的并集，而不是只取第一行 —— stats 资源里总量行
               （row_type=totals）与模块行（row_type=module）的字段并不相同，
               只按第一行出表头会把模块行的 module / species_with_data 整列丢掉。 */
            $head = array();
            foreach ($rows as $r) {
                foreach (array_keys($r) as $k) {
                    if (!in_array($k, $head, true)) { $head[] = $k; }
                }
            }
            echo implode("\t", $head) . "\n";
            foreach ($rows as $r) {
                $cells = array();
                foreach ($head as $h) {
                    /* TSV 里不能出现制表符和换行，替换成空格；缺值的列留空。
                       值为 null（「有该模块、但没有计数」）写 NA 而不是留空 ——
                       留空和「这一行没有这个字段」长得一样，而 0 又已经被
                       「没有这个模块」占用，NA 是唯一说得清的写法。 */
                    $cells[] = (array_key_exists($h, $r) && $r[$h] === null) ? 'NA'
                        : str_replace(array("\t", "\r", "\n"), ' ', isset($r[$h]) ? (string)$r[$h] : '');
                }
                echo implode("\t", $cells) . "\n";
            }
        }
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    $payload = array_merge($meta, $extra, array('data' => $rows));
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------------------------------------------------------------
 * 没有 resource：输出 HTML 说明页，方便在浏览器里直接看有哪些接口
 * ------------------------------------------------------------------- */

$siteHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'cnidosite.org';
$base = 'https://' . $siteHost . '/api.php';
$examples = array(
    array('release',   'Version number, build date, update schedule and the full changelog'),
    array('stats',     'Site-wide totals: species, genomes, modules, download volume'),
    array('modules',   'The 16 data modules, their short names and their landing pages'),
    array('species',   'Every species in the catalogue with its abbreviation and class'),
    array('coverage',  'Species-by-module matrix of record counts'),
    array('downloads', 'One row per downloadable file, with size and download URL'),
);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Programmatic Access - CnidoSite</title>
<meta name="description" content="Read-only, machine-readable access to the CnidoSite catalogue metadata &mdash; JSON or tab-separated, no key or registration required" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<style>
.api-wrap { padding: 10px 0 40px; }
.api-wrap h3 { margin: 26px 0 10px; color: #1e293b; }
.api-wrap table { width: 100%; border-collapse: collapse; margin: 10px 0 20px; }
.api-wrap th { background: #f1f5f9; color: #334155; text-align: left; padding: 10px 12px;
               border: 1px solid #e2e8f0; font-size: 15px; }
.api-wrap td { padding: 10px 12px; border: 1px solid #e2e8f0; font-size: 15px; vertical-align: top; }
.api-wrap code { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px;
                 padding: 2px 6px; font-family: 'Monaco','Menlo','Ubuntu Mono',monospace; font-size: 15px; }
.api-wrap pre { background: #1e293b; color: #e2e8f0; padding: 14px 16px; border-radius: 8px;
                overflow-x: auto; font-size: 15px; line-height: 1.55; }
.api-note { background: #f8fafc; border-left: 4px solid #3b82f6; padding: 14px 18px;
            border-radius: 6px; color: #334155; line-height: 1.7; margin: 14px 0; }
</style>
</head>
<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header"><div id="site_logo"></div></div>
</div>

<div id="tempatemo_content_wrapper">
<div id="templatemo_content">
<div id="column" class="api-wrap">

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Programmatic Access</b></legend>
<p class="paleo-intro">
  Machine-readable access to the CnidoSite catalogue metadata. Every endpoint below is read-only,
  needs no key or registration, and can be returned as JSON or as tab-separated text.
</p>

<div class="api-note">
  <b>Current release:</b> CnidoSite <?= htmlspecialchars($release['version'], ENT_QUOTES, 'UTF-8') ?>
  (<?= htmlspecialchars($release['build'], ENT_QUOTES, 'UTF-8') ?>).
  See <a href="release.php">Database Release &amp; Changelog</a> for the version history and the
  update schedule.
</div>

<h3>Endpoints</h3>
<table>
  <tr><th width="34%">Request</th><th>Returns</th></tr>
  <?php foreach ($examples as $ex): ?>
  <tr>
    <td><a target="_blank" rel="noopener" href="api.php?resource=<?= htmlspecialchars($ex[0], ENT_QUOTES, 'UTF-8') ?>"><code>api.php?resource=<?= htmlspecialchars($ex[0], ENT_QUOTES, 'UTF-8') ?></code></a></td>
    <td><?= htmlspecialchars($ex[1], ENT_QUOTES, 'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<h3>Parameters</h3>
<table>
  <tr><th width="22%">Parameter</th><th width="16%">Values</th><th>Meaning</th></tr>
  <tr><td><code>resource</code></td><td>see above</td><td>Which dataset to return. Omitting it shows this page.</td></tr>
  <tr><td><code>format</code></td><td><code>json</code> (default), <code>tsv</code></td><td>Output encoding. <code>tsv</code> is sent as a file download.</td></tr>
  <tr><td><code>class</code></td><td>e.g. <code>Hydrozoa</code></td><td>Restrict <code>species</code>, <code>coverage</code> and <code>downloads</code> to one taxonomic class.</td></tr>
</table>

<div class="api-note">
  <b>Reading <code>resource=coverage</code>.</b> A number is the count of records held for that
  species and module; <code>0</code> means the module has no data for that species.
  <code>null</code> in JSON (<code>NA</code> in TSV) is the third case: the module is present but has
  no record count of its own &mdash; currently only <i>Mitogenome</i>, where the mitochondrial genome
  is catalogued from a genome-level record without gene-level annotation. Presence-only columns return
  <code>1</code> for every species that has the resource, without that number being a record count;
  these are the columns the coverage matrix shows as a tick rather than a figure, among them
  <i>Genome</i>, <i>Transcriptome assembly</i> and <i>JBrowse</i>.
</div>

<h3>Examples</h3>
<pre># every species with its class, as tab-separated text
curl -O '<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>?resource=species&amp;format=tsv'

# just the Hydrozoa, as JSON
curl '<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>?resource=coverage&amp;class=Hydrozoa'

# the full download manifest: column 1 is the file name, column 6 its download URL
curl '<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>?resource=downloads&amp;format=tsv' &gt; manifest.tsv

# then fetch every file it lists -- each under its own name, four at a time,
# resuming any file an earlier run left half-downloaded
tail -n +2 manifest.tsv | awk -F'\t' '{print $1, $6}' | \
    xargs -n2 -P4 sh -c 'curl -sS -f -C - -o "$1" "$2"' _</pre>

<div class="api-note">
  The <code>downloads</code> endpoint is the machine-readable equivalent of the
  <a href="download.php">Download</a> page: it lists every per-species file in the collection
  together with its size and its direct download URL, so the 31&nbsp;GB these files add up to can be
  retrieved with a single loop instead of one button press per file. It lists files at the top level
  of the collection only — the transcriptome-assembly archives under
  <code>download/transcriptome_assembly/</code> (5.0&nbsp;GB) are offered on the
  <a href="download.php">Download</a> page but are not part of this manifest. Files are served by
  <code>download_fun.php</code>, which streams them without loading them into memory and honours
  byte ranges, so <code>curl -C -</code> will resume an interrupted transfer rather than restart
  it — worth knowing, because the largest single-species file is 933&nbsp;MB.
</div>

</div>
</div>
</div>

<?php
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
