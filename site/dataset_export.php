<?php
/* =====================================================================
 * 只存在于数据库里的数据集的下载出口
 *
 * 背景：download.php 覆盖的是 download/ 目录里的文件（基因组、CDS、转录本、
 * 蛋白、GFF3、功能注释、基因家族、线粒体基因组、微共线性、转录组注释表）。
 * 但站内还有大量数据根本没有对应的文件 —— 它们只在 MySQL 里，页面上以表格
 * 呈现，读者看得见却拿不走。这些数据由这份白名单统一出口：
 *
 *   基因组/基因家族  busco、busco_summary、ubs、mito_genome
 *   转录组组装       trans_assembly_species、trans_assembly_go、trans_assembly_ko
 *   MAGs 注释        mags_catalogue、mag_annot、mag_go_terms、mag_interpro、
 *                    mag_kegg_terms、mag_pfam_hits、mag_panther_hits
 *   多组学           proteomic_datasets、proteomic_proteins、proteomic_peptides、
 *                    proteome_data、metaG、epigenome、singlecell_atlas、
 *                    singlecell_celltype、singlecell_markers、singlecell_qc、
 *                    singlecell、mirna_seq
 *   表型             phenotype、phenotype_trait_dict、phenotype_species_map、
 *                    phenotype_source_resource
 *
 * 它们的原始文件都在 data/ 下，而 data/ 是 `Require all denied` 的（那里混着
 * 224 GB 中间产物与证书链），所以「直接链文件」这条路走不通。这里改为从数据库
 * 现查现发：数据不会像预生成文件那样随数据库更新而变陈，也不需要在 docroot
 * 里再落一份可能过期的副本。
 *
 * 用法：/dataset_export.php?dataset=busco[&format=tsv|xlsx]
 *       /dataset_export.php?dataset=mag_interpro&mag=GCA_012267325.1
 *
 * 第二行的分组形式只对带 'filter' 描述的数据集有效（见 includes/downloads_topics.php），
 * 取值会先转义再拿去数据库核对存在性，核对不过返回 404。
 *
 * 为什么只输出 TSV 不给 JSON：TSV 是脚本直接能吃的形式（R / pandas 一行读入），
 * 而这几张表的字段数从 7 到 17 不等、行数从 33 到 54 万不等，逐行流式发出即可，
 * 不必把整表读进内存再编码。表头就是数据库列名，拿到就能对上。
 * ===================================================================== */

/* 数据集白名单定义在 includes/downloads.php —— download.php 上的下载页要用同一
   份清单（名称、是否给 xlsx），两处各写一份必然会漂移成「页面上列了 6 个数据集
   而这里只认 5 个」。表名与列名一律写死在白名单里，绝不从请求里取。 */
require_once __DIR__ . '/includes/downloads.php';
$DATASETS = cnido_dl_datasets();

$key = isset($_GET['dataset']) ? (string)$_GET['dataset'] : '';

/* 未知 key 一律拒绝（白名单之外没有别的路径）。 */
if (!isset($DATASETS[$key])) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Unknown dataset.\n\nAvailable:\n";
    foreach ($DATASETS as $k => $d) { echo '  ' . $k . "  - " . $d['title'] . "\n"; }
    exit;
}
$D = $DATASETS[$key];

/* MySQL 8 reserves `rank`, `release`, `groups`, ... as identifiers, so a few
   column names have to be quoted in the whitelist. The header row is data, not
   SQL: strip the quoting back out so the column is called "rank", not "`rank`". */
$header = ($D['header'] === null)
        ? array_map(function ($c) { return str_replace(array('`', '"'), '', $c); }, $D['cols'])
        : $D['header'];
$format = isset($_GET['format']) ? strtolower((string)$_GET['format']) : 'tsv';
if ($format !== 'xlsx') { $format = 'tsv'; }

if ($format === 'xlsx' && empty($D['xlsx'])) {
    /* 不是拒绝服务，而是这张表太大：xlsx 要先把整表读进内存才能算出各行的
       偏移量，54 万行的 busco 会直接把 PHP 的内存用光。TSV 是逐行流式发出的，
       同样的数据没有这个问题。 */
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo "This dataset is only available as TSV because it is too large to build as a "
       . "spreadsheet in memory.\nUse: dataset_export.php?dataset=" . $key . "&format=tsv\n";
    exit;
}

$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Database connection failed.\n";
    exit;
}
mysqli_set_charset($conn, 'utf8');

/* 可分组导出的数据集（例如只取某一个 MAG 的 InterPro 注释）带一个 filter 描述。
   参数名、列名、取值域都写死在 includes/downloads_topics.php 里，这里只负责：
   取参数 -> 转义 -> 到数据库里问一次「这个取值真的存在吗」-> 拼进 WHERE。
   取值先经 real_escape_string，再用 check 语句核对，所以请求里塞不进 SQL；
   核对而不是只做格式校验，是为了让拼错的取值返回 404 而不是一个只有表头的空文件。 */
$filterSql = '';
$filterVal = '';
if (isset($D['filter'])) {
    $F = $D['filter'];
    $filterVal = isset($_GET[$F['param']]) ? (string)$_GET[$F['param']] : '';
    if ($filterVal !== '') {
        $esc  = mysqli_real_escape_string($conn, $filterVal);
        $chk  = mysqli_query($conn, sprintf($F['check'], $esc));
        $ok   = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) { mysqli_free_result($chk); }
        if (!$ok) {
            header('HTTP/1.1 404 Not Found');
            header('Content-Type: text/plain; charset=utf-8');
            echo "No such " . $F['noun'] . ": " . $filterVal . "\n\n"
               . "Download the whole dataset instead: dataset_export.php?dataset=" . $key . "\n";
            exit;
        }
        $filterSql = $F['column'] . " = '" . $esc . "'";
    }
}

$where = $D['where'];
if ($filterSql !== '') {
    $where = ($where !== '') ? '(' . $where . ') AND ' . $filterSql : $filterSql;
}

$sel = implode(', ', $D['cols']);
$sql = "SELECT " . $sel . " FROM " . $D['from']
     . ($where !== '' ? ' WHERE ' . $where : '');

/* TSV 的单元格里不能出现制表符与换行，否则列会错位。数据集里的 Description
   一类文本可能带换行，统一压成空格。NULL 输出空串（与页面上的空白一致），
   不要写成字符串 "NULL" —— R 会把它当成一个真实取值。 */
function cnido_ds_cell($v)
{
    if ($v === null) { return ''; }
    return str_replace(array("\t", "\r\n", "\r", "\n"), ' ', (string)$v);
}

/* 先拿 LIMIT 0 试一次。列名写错（MySQL 8 的保留字）时，正式那次查询会在表头
   已经发出去之后才失败，读者拿到的就是一个「只有表头 + # query failed」的文件，
   HTTP 还是 200 —— 这类坏导出没人会发现。试跑一次就能在发头之前把它变成 500。 */
$pre = mysqli_query($conn, $sql . ' LIMIT 0');
if (!$pre) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo "This export is misconfigured and returned nothing.\n\n";
    echo mysqli_error($conn) . "\n";
    exit;
}
mysqli_free_result($pre);

if ($format === 'tsv') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    header('Content-Disposition: attachment; filename="CnidoSite_' . $key . '.tsv"');
    header('X-Content-Type-Options: nosniff');
    /* 数据随数据库更新而变化，别让中间缓存留住旧内容。 */
    header('Cache-Control: no-store');

    echo implode("\t", $header) . "\n";
    /* MYSQLI_USE_RESULT：结果集留在服务器端逐行取，不把 54 万行一次性搬进
       PHP 内存。代价是取完之前这条连接上不能再发别的查询 —— 本脚本也不发。 */
    $res = mysqli_query($conn, $sql, MYSQLI_USE_RESULT);
    if (!$res) {
        echo "# query failed\n";
        exit;
    }
    $line = array();
    while ($row = mysqli_fetch_row($res)) {
        $line = array();
        foreach ($row as $v) { $line[] = cnido_ds_cell($v); }
        echo implode("\t", $line) . "\n";
    }
    mysqli_free_result($res);
    $conn->close();
    exit;
}

/* ---- xlsx：只给小表走这条路 ---- */
$rows = array();
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_row($res)) {
        foreach ($row as $i => $v) { $row[$i] = cnido_ds_cell($v); }
        $rows[] = $row;
    }
}
$conn->close();

require_once __DIR__ . '/includes/xlsx.php';
cnido_xlsx_send('CnidoSite_' . $key . '.xlsx', array(
    array(
        'name'        => cnido_xlsx_sheet_name($D['title']),
        'header'      => $header,
        'rows'        => $rows,
        'freeze_rows' => 1,
        'filter'      => true,
    ),
    /* 文件脱离网页之后，读者无从知道它是哪张表、某列是什么意思、有没有被裁剪。
       附一页说明，成本极低。 */
    array(
        'name'   => getenv('CNIDO_MSR_DB_NAME') ?: 'jackie_db',
        'header' => array('Field', 'Value'),
        'rows'   => array(
            array('Dataset',     $key),
            array('Title',       $D['title']),
            array('Rows',        count($rows)),
            array('Source table', $D['table']),
            array('Subset',      $filterVal !== '' ? $filterVal : 'whole table'),
            array('About',       $D['about']),
            array('Site',        'CnidoSite'),
        ),
        'widths' => array(18, 110),
    ),
));
