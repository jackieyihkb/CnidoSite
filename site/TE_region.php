<?php
if (isset($_GET['species1'])) {
    $species1 = $_GET['species1'];
} else {
    $species1 = $_POST['species1'];
}
if (isset($_GET['position'])) {
    $position = $_GET['position'];
} else {
    $position = $_POST['position'];
}

// 获取每页显示数量，默认为10
$per_page_options = [10, 20, 50, 100];
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
if (!in_array($per_page, $per_page_options)) {
    $per_page = 10;
}

// 获取当前页码
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

// 验证位置参数
if (empty($position)) {
    echo "<script>alert(\"You did not enter a genomic region. Please try again.\");</script>";
    echo "<script>window.location =\"TE.php\";</script>";
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
<title>Transposable Elements in Genomic Regions - CnidoSite</title>
<meta name="keywords" content="Cnidaria, transposable elements, TEs, genome evolution, repetitive sequences" />
<meta name="description" content="Search results for transposable elements in genomic regions" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>


<style>
<?php /* TE结果页面专用样式 - 不影响全局CSS */ ?>
.te-result-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.te-result-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.te-result-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.genome-badge {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

<?php /* 搜索参数卡片 */ ?>
.search-params {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 25px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
}

.params-title {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.params-title::before {
    content: "🔍";
    font-size: 20px;
}

.params-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.param-item {
    background: #f8fafc;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.param-label {
    font-size: 15px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 5px;
}

.param-value {
    font-size: 16px;
    color: #1e293b;
    font-weight: 500;
}

<?php /* 结果表格容器 */ ?>
.results-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e2e8f0;
    margin: 30px 0;
}

.results-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 18px 25px;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.results-count {
    background:#047857;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

<?php /* 链接样式 */ ?>
.gene-link {
    color:#047857;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.gene-link:hover {
    color:#047857;
    text-decoration: underline;
}

.jbrowse-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.jbrowse-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

<?php /* 分页导航美化 - 绿色主题（参照browse.php） */ ?>
.pagination-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 20px;
    margin-top: 30px;
    border: 1px solid #e2e8f0;
}

.pagination-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.total-records {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.per-page-selector {
    display: flex;
    align-items: center;
    gap: 10px;
}

.per-page-selector select {
    padding: 8px 15px;
    border-radius: 6px;
    border: 2px solid #e2e8f0;
    background: white;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.per-page-selector select:hover {
    border-color:#1d4ed8;
}


.pagination-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin: 25px 0;
    flex-wrap: wrap;
}

.page-btn {
    padding: 10px 16px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #475569;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s ease;
    min-width: 44px;
    text-align: center;
}

.page-btn:hover {
    background: #f0f9ff;
    border-color:#1d4ed8;
    color:#1d4ed8;
}

.page-btn.active {
    background:#1d4ed8;
    border-color:#1d4ed8;
    color: white;
    font-weight: 600;
}

.page-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.go-to-page {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
    justify-content: center;
}

.go-to-page input {
    width: 70px;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    text-align: center;
    font-size: 15px;
}

.go-to-page button {
    padding: 8px 16px;
    background:#1d4ed8;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.go-to-page button:hover {
    background: #1d4ed8;
}

.external-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.external-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.search-box {
    margin: 20px 0;
    display: flex;
    justify-content: flex-end;
}

.search-box input {
    padding: 10px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    width: 350px;
    font-size: 15px;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.selection-form {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 25px;
    margin: 20px 0;
    border: 1px solid #e2e8f0;
}

.selection-form table {
    width: 100%;
    max-width: 500px;
}

.selection-form td {
    padding: 15px 10px;
}

.selection-form select {
    width: 100%;
    padding: 12px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.selection-form select:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

<?php /* 无结果提示 */ ?>
.no-results {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 4px solid #f59e0b;
    padding: 25px 30px;
    border-radius: 12px;
    margin: 30px 0;
    color: #92400e;
    line-height: 1.7;
    text-align: center;
}

.no-results-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.no-results h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
    color: #78350f;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .te-result-header {
        padding: 20px;
    }
    
    .te-result-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .search-params {
        padding: 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .params-grid {
        grid-template-columns: 1fr;
    }
    
    .results-container {
        margin-left: 15px;
        margin-right: 15px;
    }
    
    <?php /* 补 `table.` 前缀。原来写的是裸 `.gridtable` (0,1,0) 和 `.gridtable th`
       (0,1,1)，都低于 templatemo_style.css 里 `table.gridtable th/td` (0,1,2)
       ——媒体查询不改变特异性——所以从写下起就没生效过，小屏上表格一直是
       全站的 13.5px 和 7px/10px 留白。凑平特异性后就按这里的来。 */ ?>
    table.gridtable {
        font-size: 15px;
    }

    table.gridtable th, table.gridtable td {
        padding: 10px 8px;
    }
    
    .pagination-info {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .pagination-nav {
        gap: 5px;
    }
    
    .page-btn {
        padding: 8px 12px;
        min-width: 36px;
    }
}
</style>
</head>

<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Transposable Elements in Genomic Regions</b></legend>
<p class="paleo-intro">Search results for transposable elements in genomic regions of cnidarians.</p>

<?php
// 解析位置参数
$position_parts = explode(':', $position);
if (count($position_parts) != 2) {
    echo "<script>alert(\"Invalid position format. Please use format like 'Scaffold:start-end'.\");</script>";
    echo "<script>window.location =\"TE.php\";</script>";
    exit;
}

$scaffold = $position_parts[0];
$range_parts = explode('-', $position_parts[1]);
if (count($range_parts) != 2) {
    echo "<script>alert(\"Invalid range format. Please use format like 'Scaffold:start-end'.\");</script>";
    echo "<script>window.location =\"TE.php\";</script>";
    exit;
}

$start_pos = intval($range_parts[0]);
$end_pos = intval($range_parts[1]);

// 获取物种信息
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}

/* cnido_te_gene_proteins()：把 related_gene（基因级号）翻成蛋白（mRNA）级号。
   gene detail 页是按蛋白号取注释的，直接拿基因号去查会显示「no NCBI-NR hit」。 */
require_once __DIR__ . '/includes/state.php';

/* species1 与 scaffold 都来自 URL，原先被原样拼进下面三处 SQL
   （?species1=' OR 1=1 -- 之类可以改写查询）。进 SQL 前一律转义；
   $start_pos / $end_pos 上面已经过 intval()，本身就是数字。 */
/* species1 在表单里是拉丁学名（`Acropora acuminata`），而地址栏深链常写成下划线
   形式（`Acropora_acuminata`）—— 全站其它页面两种写法都收，本页原来只认前者：
   后者查不到 abbr 行，$TE 退化成 "_TE"，于是搜索条件卡把内部名原样印出来、
   而结果一律是 0 条（实测 sc0001551_pilon:1-4690 在 Acropora acuminata 下是 7 条，
   换成下划线写法就是 0 条，页面还说「没有找到 TE」）。与 TE_gene.php 同一处理。 */
$species1    = cnido_latin_of($species1);
$species1Sql = mysqli_real_escape_string($conn, $species1);
$scaffoldSql = mysqli_real_escape_string($conn, $scaffold);
$query2 = mysqli_query($conn, "SELECT * FROM abbr WHERE species = '$species1Sql'");
$result2 = mysqli_fetch_row($query2);
/* abbr 的列序是 species / abbr / abbr1（0 / 1 / 2），第 0 列才是学名 —— 原来搜索
   条件卡印的是 URL 里传进来的原串，下划线写法会把它印成内部名。 */
$species_full_name = isset($result2[0]) ? $result2[0] : $species1;
$TE = $result2 ? $result2[2] . '_TE' : '';
/* $TE 只是**按短码拼出来的表名**，表不一定真的存在：abbr 里有 PMULT 这一行，
   但 PMULT 只有基因组、没有 TE 注释表，于是 $TE = 'PMULT_TE' 非空、查询静默失败，
   页面走到「Sorry, no transposable elements were found in the specified genomic
   region」那一支 —— 把「本站没做注释」说成「这段区间没有 TE」。
   以表是否真的存在为准（TE_gene.php 同样处理）。 */
$__teKnown = !empty($result2);               // 物种是否在本站名录里（mysqli_fetch_row 无行时返回 NULL，不是 false）
if ($TE !== '' && !$__teKnown) { $TE = ''; }
if ($TE !== '') {
    $__chk = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $TE) . "'");
    if (!$__chk || !mysqli_num_rows($__chk)) { $TE = ''; }
}

// 查询总记录数
$total_records = 0;
if ($TE !== '') {
    $count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `$TE` WHERE Scaffold = '$scaffoldSql' AND species = '$species1Sql' AND ((TE_Start > $start_pos AND TE_Start < $end_pos) OR (TE_End > $start_pos AND TE_End < $end_pos))");
    if ($count_result = mysqli_fetch_assoc($count_query)) {
        $total_records = $count_result['total'];
    }
}

// 计算总页数
$total_pages = ceil($total_records / $per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}

// 计算偏移量
$offset = ($page - 1) * $per_page;

/* 服务端排序。TE 表没有主键、也没有索引，默认项写 null ＝ 不加 ORDER BY，
   初始次序与加按钮前完全一致（这张表的自然顺序本来就没有含义）。
   每一列都给了箭头（JBrowse 列除外，那是链接不是数据）。Species 与 Scaffold 两列
   的箭头点了看不出变化 —— WHERE 里已经把它们钉成查询参数那一个值，整页所有行都相同；
   但它们确实各是表里的一列，点了就是「按这一列排」，不是假按钮，只是当前查询下退化。
   TE_start / TE_end 存的是 text 形式的整数，用 cnido_sort_num 按数值排 ——
   直接 ORDER BY 是字典序，'9999' 会排在 '10000' 后面。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array(
	'default' => null,
	'sp'      => cnido_sort_txt('species'),
	'scaffold'=> cnido_sort_txt('Scaffold'),
	/* TE_id 有两种写法：多数物种零填充（TE_00000001，字典序＝数值序），
	   ASP1/ASP2/AIDSS 是 TE_homo_0（不填充），按原串排会得到 0, 1, 10, 100…。
	   都以下划线接数字结尾，故按尾段数值排，再用原串兜底。 */
	'te'      => array(cnido_sort_txt('TE_id')[0],
	                   "CAST(SUBSTRING_INDEX(TE_id, '_', -1) AS UNSIGNED)",
	                   'TE_id'),
	'start'   => cnido_sort_num('TE_start'),
	'end'     => cnido_sort_num('TE_end'),
	'gene'    => cnido_sort_txt('related_gene'),
	'region'  => cnido_sort_txt('region'),
	'type'    => cnido_sort_txt('TE_type'),
);
$__tie = array('TE_id', 'TE_start', 'TE_end', 'scaffold', 'species');
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', $__tie);
$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');
$__filterQs = 'species1=' . urlencode($species1) . '&position=' . urlencode($position);
$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
$__baseQs   = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');

// 查询当前页数据
$query = false;
if ($TE !== '') {
    $query = mysqli_query($conn, "SELECT * FROM `$TE` WHERE Scaffold = '$scaffoldSql' AND species = '$species1Sql' AND ((TE_Start > $start_pos AND TE_Start < $end_pos) OR (TE_End > $start_pos AND TE_End < $end_pos))" . $__orderSql . " LIMIT $offset, $per_page");
}
?>

<?php /* 搜索参数卡片 */ ?>
<div class="search-params">
    <div class="params-title">Search Parameters</div>
    <div class="params-grid">
        <div class="param-item">
            <div class="param-label">Species</div>
            <div class="param-value"><i><?= htmlspecialchars($species_full_name) ?></i></div>
        </div>
        <div class="param-item">
            <div class="param-label">Genomic Region</div>
            <div class="param-value"><?= htmlspecialchars($position) ?></div>
        </div>
        <div class="param-item">
            <div class="param-label">Total Results</div>
            <div class="param-value"><?= $total_records ?> TE<?= $total_records == 1 ? '' : 's' ?> found</div>
        </div>
    </div>
</div>

<?php if (!$__teKnown): ?>
<?php /* 物种根本不在名录里（?species= 拼错或为空）—— 第三种情况，与「没做注释」
     和「这段区间没有」都不同。原来它落到下面那支，把读者输错的串原样印回去，
     得到「have not been annotated for ,」这种残缺句子（species 为空时连名字都
     没有）。 */ ?>
<?php $__spTxt = trim((string)$species1); ?>
<div class="no-results">
    <div class="no-results-icon">🔍</div>
    <h3>Species Not Recognised</h3>
    <p><?= $__spTxt === ''
            ? 'No species was given in this link, so there is nothing to search here.'
            : '<b>' . htmlspecialchars($__spTxt) . '</b> is not one of the species currently in CnidoSite.' ?>
       The <a href="/TE.php">Transposable Elements</a> page lists every species that has an annotation.</p>
</div>
<?php elseif ($TE === ''): ?>
<?php /* 这个物种根本没有 TE 注释表 —— 与「这段区间里没有 TE」是两回事，分开说。
     原来两种情况共用下面那段文案，读者分不清是「没做注释」还是「这段没有」。 */ ?>
<div class="no-results">
    <div class="no-results-icon">🔍</div>
    <h3>No Transposable Elements Found</h3>
    <p>Transposable elements have not been annotated for <b><i><?= htmlspecialchars($species_full_name) ?></i></b>, so there is nothing to search here. The <a href="/TE.php">Transposable Elements</a> page lists the species that have an annotation.</p>
</div>
<?php elseif ($total_records == 0): ?>
<?php /* 无结果提示 */ ?>
<div class="no-results">
    <div class="no-results-icon">🔍</div>
    <h3>No Transposable Elements Found</h3>
    <p>Sorry, no transposable elements were found in the specified genomic region.</p>
    <p>Try adjusting the region coordinates or selecting a different species.</p>
</div>
<?php else: ?>

<?php /* 结果表格容器 */ ?>
<div class="results-container">
    <table class="gridtable">
        <thead>
            <tr>
                <th><?php echo cnido_sort_link('sp', 'Species', $__sort, $__dir, $__filterQs); ?></th>
                <th><?php echo cnido_sort_link('te', 'TE ID', $__sort, $__dir, $__filterQs); ?></th>
                <th><?php echo cnido_sort_link('scaffold', 'Scaffold', $__sort, $__dir, $__filterQs); ?></th>
                <th><?php echo cnido_sort_link('start', 'Start', $__sort, $__dir, $__filterQs); ?></th>
                <th><?php echo cnido_sort_link('end', 'End', $__sort, $__dir, $__filterQs); ?></th>
                <th width="15%"><?php echo cnido_sort_link('gene', 'Related Gene', $__sort, $__dir, $__filterQs); ?></th>
                <th><?php echo cnido_sort_link('region', 'Distribution', $__sort, $__dir, $__filterQs); ?></th>
                <th><?php echo cnido_sort_link('type', 'TE Type', $__sort, $__dir, $__filterQs); ?></th>
                <th>JBrowse</th>
            </tr>
        </thead>
        <tbody>
            <?php
            /* 先取齐这一页的行，好把 related_gene 一次性换成蛋白号
               （每页最多 100 行，一次查询 ~2 ms；逐行查会变成 100 次）。 */
            $te_rows = array();
            if ($query) { while ($r = mysqli_fetch_row($query)) { $te_rows[] = $r; } }
            $te_col = array();
            foreach ($te_rows as $r) { $te_col[] = $r[5]; }
            $te_pro = cnido_te_gene_proteins($conn, $result2[2], $te_col);
            $te_void = array('', 'NA', '-', '.', 'None', 'unknown', 'null');

            foreach ($te_rows as $result_of_query) {
                $loc = $result_of_query[2] . ":" . $result_of_query[3] . ".." . $result_of_query[4];
                $gid = trim((string)$result_of_query[5]);                       // TE 表里的基因级号
                $pid = isset($te_pro[$gid]) ? $te_pro[$gid] : cnido_gene_id_canon($gid);  // 蛋白（mRNA）级号；解析不到就用规范化基因号
                $gtitle = ($pid !== $gid) ? ' title="' . htmlspecialchars($gid, ENT_QUOTES) . '"' : '';
                echo "<tr align='center'>";
                echo "<td><a href=\"speciesinfo.php?species={$result2[2]}\" class=\"gene-link\">" . htmlspecialchars($result_of_query[0]) . "</a></td>";
                echo "<td>" . htmlspecialchars($result_of_query[1]) . "</td>";
                echo "<td>" . htmlspecialchars($result_of_query[2]) . "</td>";
                echo "<td>" . htmlspecialchars($result_of_query[3]) . "</td>";
                echo "<td>" . htmlspecialchars($result_of_query[4]) . "</td>";
                if (in_array($gid, $te_void, true)) {
                    /* 这个 TE 没有关联基因，原来会生成一个 gene= 为空的链接 */
                    echo "<td>" . htmlspecialchars($result_of_query[5]) . "</td>";
                } else {
                    echo "<td><a href=\"gene_detail.php?gene=" . urlencode($pid) . "&amp;species=" . urlencode($result2[2]) . "\""
                       . " class=\"gene-link\"{$gtitle}>" . htmlspecialchars($pid) . "</a></td>";
                }
                echo "<td>" . cnido_te_region_label($result_of_query[6]) . "</td>";
                echo "<td>" . htmlspecialchars($result_of_query[7]) . "</td>";
                echo "<td><a href=\"./jbrowse/index.html?data={$result2[2]}&loc={$loc}\" class=\"jbrowse-link\">JBrowse</a></td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
    
    <?php /* 分页导航 - 绿色主题（参照browse.php） */ ?>
    <div class="pagination-container">
        <div class="pagination-info">
            <div class="total-records">
                📊 Transposable Elements Related to "<?= htmlspecialchars($position) ?>": <?= $total_records ?> Records
            </div>
            
            <div class="per-page-selector">
                <span>Show:</span>
                <select onchange="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page='+this.value+'&page=1'">
                    <?php
                    foreach ($per_page_options as $option) {
                        $selected = ($option == $per_page) ? 'selected' : '';
                        echo "<option value='$option' $selected>$option</option>";
                    }
                    ?>
                </select>
                <span>records per page</span>
            </div>
        </div>
        
            <?php if ($total_pages > 0): /* 命中 0 条时不渲染分页条：$total_pages
         是 0，页码循环一次都不进，几个按钮和「of 0 pages」却照旧印出来，
         全部指向自己。站内约定见 browse.php / go_result.php。 */ ?>
<div class="pagination-nav">
            <?php /* 首页 */ ?>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=1" 
               class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">
                « First
            </a>
            
            <?php /* 上一页 */ ?>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=<?= max(1, $page-1) ?>" 
               class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">
                ‹ Previous
            </a>
            
            <?php /* 页码 */ ?>
            <?php
            // 显示当前页前后各3页
            $start_page = max(1, $page - 3);
            $end_page = min($total_pages, $page + 3);
            
            // 左侧省略号
            if ($start_page > 1) {
                echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $__baseQs . '&per_page=' . $per_page . '&page=1" class="page-btn">1</a>';
                if ($start_page > 2) {
                    echo '<span class="page-btn disabled">...</span>';
                }
            }
            
            // 中间页码
            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = ($i == $page) ? 'active' : '';
                echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $__baseQs . '&per_page=' . $per_page . '&page=' . $i . '" class="page-btn ' . $active . '">' . $i . '</a>';
            }
            
            // 右侧省略号
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="page-btn disabled">...</span>';
                }
                echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $__baseQs . '&per_page=' . $per_page . '&page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
            }
            ?>
            
            <?php /* 下一页 */ ?>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=<?= min($total_pages, $page+1) ?>" 
               class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
                Next ›
            </a>
            
            <?php /* 末页 */ ?>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=<?= $total_pages ?>" 
               class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
                Last »
            </a>
        </div>
        
        <?php /* 跳转到指定页 */ ?>
        <div class="go-to-page">
            <span>Go to page:</span>
            <input type="number" id="gotoPage" min="1" max="<?= $total_pages ?>" value="<?= $page ?>">
            <button onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page='+document.getElementById('gotoPage').value">
                Go
            </button>
            <span>of <?= $total_pages ?> pages</span>
        </div>    <?php endif; /* $total_pages > 0 */ ?>

    </div>
</div>
<?php endif; ?>

</div>
</div>
</div>

<?php
    include "Webpage_components.php";
    print $footer;
?>
</body>
</html>
