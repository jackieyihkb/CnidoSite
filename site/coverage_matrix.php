<?php
/* =====================================================================
 * Data Coverage Matrix —— 物种 × 数据类型 可用性总览
 *
 * 审稿意见 Referee 2 major 2：
 *   "Please provide a species-by-data-type coverage matrix so that users can
 *    see at a glance which species have which data."
 * 审稿意见 Referee 2 major 1：
 *   "When a species is selected in the Taxonomy module, users should be able to
 *    directly access all available … resources for that species."
 *
 * 本页把所有物种 × 16 类数据的可用性放在一张表里：有数据的格子直接链到对应
 * 模块（并带上物种参数）并显示数据量，没有数据的格子明确留空 —— 使用者不必
 * 逐个模块点进去才发现「这个物种没有单细胞数据」。
 *
 * 表本身由 includes/coverage_matrix_view.php 渲染，本页与 data_statistics.php
 * 共用同一份实现：统计页上也要放这张表（在 Software and Analytical Tools 之前），
 * 两处各写一份 HTML 迟早会漂移。本页保留自己的入口地址与 TSV 导出。
 * 数据来源与 species_portal.php 共用 includes/coverage.php（带 1 小时缓存）。
 * ===================================================================== */
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { die('Database connection failed.'); }
require_once __DIR__ . '/includes/coverage_matrix_view.php';

/* 取数、过滤、排序、渲染都在 includes/coverage_matrix_view.php 里 ——
   data_statistics.php 嵌的是同一张表，两边不会再各自漂移。 */
$M = cnido_cm_prepare($conn);

/* ---- 导出（审稿意见 Referee 2 major 9：可编程访问）----
   导出用与页面完全相同的筛选与排序，所以共用 $M；行数据本身由
   cnido_cm_export_table() 提供，TSV 与 XLSX 因此逐格一致，不会各自漂移。
   计数列在表头里标 (n)，存在性列是 1/0，脚本拿到就能分辨两种语义。

   两种格式并存而不是互相取代：TSV 是脚本直接能吃的形式（R / pandas 一行读入），
   是 Referee 2 major 9 要的那个东西；XLSX 是给人看的形式（冻结表头、可筛选、
   数字是真数字而不是文本）。把 TSV 换掉等于把可编程访问一起拿掉。 */
$__fmt = isset($_GET['format']) ? $_GET['format'] : '';
if ($__fmt === 'tsv' || $__fmt === 'xlsx') {
    require_once __DIR__ . '/includes/xlsx.php';
    list($__eh, $__er, $__about) = cnido_cm_export_flat($M, cnido_cm_export_table($M));

    if ($__fmt === 'tsv') {
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: text/tab-separated-values; charset=utf-8');
        header('Content-Disposition: attachment; filename="CnidoSite_data_coverage_matrix.tsv"');
        echo implode("\t", $__eh) . "\n";
        foreach ($__er as $__row) { echo implode("\t", $__row) . "\n"; }
        exit;
    }

    /* 列宽：物种名和类群要宽，其余数据列窄。逐个算最宽内容也行，但表头
       （如 "Genome (1/0)"）往往比数字长，固定宽度反而更好读。 */
    $__widths = array(34, 16);
    for ($__i = 2; $__i < count($__eh); $__i++) { $__widths[] = 13; }
    cnido_xlsx_send('CnidoSite_data_coverage_matrix.xlsx', array(
        array(
            'name'        => getenv('CNIDO_MSR_DB_NAME') ?: 'jackie_db',
            'header'      => $__eh,
            'rows'        => $__er,
            'freeze_cols' => 2,
            'freeze_rows' => 1,
            'filter'      => true,
            'widths'      => $__widths,
        ),
        /* 文件脱离网页之后，读者无从知道它是不是被筛选过、NA 是什么意思。
           附一页说明，成本极低。 */
        array(
            'name'   => getenv('CNIDO_MSR_DB_NAME') ?: 'jackie_db',
            'header' => array('Field', 'Value'),
            'rows'   => $__about,
            'widths' => array(30, 96),
        ),
    ));
    exit;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Data Coverage Matrix - CnidoSite</title>
<meta name="keywords" content="Cnidaria, data coverage matrix, species by data type" />
<meta name="description" content="Species-by-data-type coverage matrix for all Cnidaria species in CnidoSite" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<?= cnido_cm_css() ?>
</head>
<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<?php /*导航栏保持不变*/ ?>
<div id="templatemo_menu_wrapper">
    <div id="templatemo_menu">
        <ul>
            <li><a href="/index.php">Home</a></li>
            <li><a href="#">Taxonomy</a>
                <ul>
                    <li><a href="/browse.php?class=all">All</a></li>
                    <li><a href="/browse.php?class=Cubozoa">Cubozoa</a></li>
                    <li><a href="/browse.php?class=Hexacorallia">Hexacorallia</a></li>
                    <li><a href="/browse.php?class=Octocorallia">Octocorallia</a></li>
                    <li><a href="/browse.php?class=Hydrozoa">Hydrozoa</a></li>
                    <li><a href="/browse.php?class=Myxozoa">Myxozoa</a></li>
                    <li><a href="/browse.php?class=Scyphozoa">Scyphozoa</a></li>
                    <li><a href="/browse.php?class=Staurozoa">Staurozoa</a></li>
                </ul>
            </li>
            <li><a href="/paleobiology.php">Paleobiology</a></li>
            <li><a href="#" class="current">Genome</a>
                <ul>
                    <li><a href="/genomeinfo.php">Genomic Data</a></li>
                    <li><a href="/search.php">Gene Search</a></li>
                    <li><a href="/busco.php">BUSCO Genes</a></li>
                    <li><a href="/TE.php">Transposable Elements</a></li>
                    <li><a href="/gene_family.php">TFs/Ubs</a></li>
                    <li><a href="/proteindomain.php">Protein Domain</a></li>
                    <li><a href="/domain_search.php">Functional Domain Search</a></li>
                    <li><a href="/go.php">Gene Ontology</a></li>
                    <li><a href="/interpro.php">InterPro</a></li>
                    <li><a href="/kegg.php">KEGG Pathway</a></li>
                    <li><a href="/genefamily.php">Gene Family</a></li>
                    <li><a href="/pan-geneset.php">Pan-geneset</a></li>
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li><li><a href="/microsynteny.php">Microsynteny Analysis</a></li><li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li><li><a href="/mitdata.php">Mitogenomic Data</a></li>
                </ul>
            <li><a href="#">Transcriptome</a>
                <ul>
                    <li><a href="/cytoscape/trans_data.php">Transcriptomic Data</a></li>
                    <li><a href="/trans_assembly.php">Transcriptome Assembly</a></li>
                    <li><a href="/cytoscape/network.php">Network Analysis</a></li>
                    <li><a href="/cytoscape/network_expression.php">Dynamic Expression View</a></li>
                </ul>
            <li><a href="#">Single-cell</a>
                <ul>
                    <li><a href="/sn_data.php">Single-cell Data</a></li>
                    <li><a href="/cell_atlas.php">Cell Atlas</a></li>
                    <li><a href="/cell_marker.php">Cell Marker</a></li>
                    <li><a href="/gene_exp.php">Gene Expression</a></li>
                </ul>
            </li>
            <li><a href="#">Proteome</a>
                <ul>
                    <li><a href="/proteomic_reprocessed.php">Proteomic Data</a></li>
                    <li><a href="/proteomic_reanalysis.php">Proteomic Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Epigenome</a>
                <ul>
                    <li><a href="/epigenomic_data.php">Epigenomic Data</a></li>
                    <li><a href="/DNA_methylation.php">DNA Methylation</a></li>
                    <li><a href="/miRNA_analysis.php">miRNA-seq Analysis</a></li>
                    <li><a href="/ATAC_analysis.php">ATAC-seq Analysis</a></li>
                    <li><a href="/DHS_analysis.php">DNase-seq Analysis</a></li>
                    <li><a href="/ChIP_analysis.php">ChIP-seq Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Metagenome</a>
                <ul>
                    <li><a href="/metagenomic_data.php">Metagenomic Data</a></li>
                    <li><a href="/MAGs.php">MAGs Catalog</a></li>
                </ul>
            </li>
            <li><a href="#">Phenotype</a><ul><li><a href="/phenotype.php?class=all">All</a></li><li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li><li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li><li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li><li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li><li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li></ul></li><li><a href="#">Tools</a>
                <ul>
                    <li><a href="/GSEA/GSEA.php">Gene Sets Analysis</a></li>
                    <li><a href="/blast/blast.php">BLAST</a></li>
                    <li><a href="/primer3plus/primer3.html">Primer Design</a></li>
                    <li><a href="/jbrowse.php">JBrowse</a></li>
                </ul>
            </li>
            <li><a href="/download.php">Download</a></li>
            <li><a href="#">Help</a>
                <ul>
                    <li><a href="/data_statistics.php">Statistics</a></li>
                    <li><a href="/tutorial.php">User Manual</a></li>
                    <li><a href="/submit_comments.php">Data Submit</a></li>
                    <li><a href="/contact.php" class="last">Contact Us</a></li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<div id="tempatemo_content_wrapper">
<div id="templatemo_content">
<div id="column">

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Data Coverage Matrix</b></legend>
<p class="paleo-intro">
    Which species have which data? The matrix below covers all <b><?= count(cnido_coverage($conn)['species']) ?></b> cnidarian
    species in CnidoSite &times; <b><?= count(cnido_modules()) ?></b> data types. Cells that carry data link straight to
    that module with the species already selected, and show how much data there is; a blank cell means the data type
    is genuinely not available for that species in this release. Click a species name to open its
    <b>species information page</b>, which gives its assembly details and lists every resource it
    has on one page. Sort by
    <a href="<?= htmlspecialchars(cnido_cm_url($M, array('sort' => 'name', 'page' => ''))) ?>">species name</a> or by
    <a href="<?= htmlspecialchars(cnido_cm_url($M, array('sort' => 'class', 'page' => ''))) ?>">class</a>.
</p>

<?= cnido_cm_html($conn) ?>

</div>
</div>
</div>

<?php
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
