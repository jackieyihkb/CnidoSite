<?php
if (isset($_GET['species'])) {
    $species = $_GET['species'];
} else {
    $species = $_POST['species'];
}
if (isset($_GET['gene'])) {
    $gene = $_GET['gene'];
} else {
    $gene = $_POST['gene'];
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

/* ========== 表头排序（服务端） ==========
 * 这张表是分页的（LIMIT $offset, $per_page），浏览器里排一页只是把这十几行换个次序，
 * 必须由服务器重发整段结果。原来的两条 SQL 连 ORDER BY 都没有 —— 行序由存储引擎
 * 决定，同一个查询两次执行都可能不同，翻页时同一行漏掉或重复是迟早的事；所以这里
 * 的排序既是新功能，也顺手把原来的不确定次序钉死了（每个键都带并列键）。
 * 白名单写死，$_GET 只用来查表。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array(
    'species' => cnido_sort_txt('species'),
    /* TE_id 有两种写法：15 个物种是零填充的 TE_00000001（字典序＝数值序），
       ASP1/ASP2/AIDSS 是 TE_homo_0（不填充）。按原串排，后三个物种的第一页会是
       TE_homo_0, TE_homo_1, TE_homo_10, TE_homo_100…，看着像排错了。
       三种写法都以 `_<数字>` 结尾（全库核过，没有例外），所以按尾段数值排，
       再用原串兜底 —— 前 15 个物种的次序不变。 */
    'te_id'   => array(cnido_sort_txt('TE_id')[0],
                       "CAST(SUBSTRING_INDEX(TE_id, '_', -1) AS UNSIGNED)",
                       'TE_id'),
    'scaffold'=> cnido_sort_txt('scaffold'),
    /* TE_start / TE_end 在库里是 text 装整数（483,185 行全部是纯数字，没有缺值），
       直接 ORDER BY 是按字典序排的 —— "1000" 会排在 "261" 前面。 */
    'start'   => cnido_sort_num('TE_start'),
    'end'     => cnido_sort_num('TE_end'),
    /* related_gene 的缺值写法不统一（页面自己那份 $te_void 列了 ''/NA/-/./None/unknown/null），
       一并算缺值排最后，免得一堆空占满第一页。这里只认 SQL 层的 NULL 和空串，
       其余写法在下面用 FIELD() 补。 */
    'gene'    => array(
        "(related_gene IS NULL OR related_gene = ''
          OR FIELD(related_gene, 'NA', '-', '.', 'None', 'unknown', 'null') > 0)",
        'related_gene',
    ),
    'region'  => cnido_sort_txt('region'),
    'te_type' => cnido_sort_txt('TE_type'),
);
/* 并列键用 TE_id：TE 表没有任何索引，也没有主键，但 TE_id 是 TE_00000001 这种定宽
   编号，同一物种内唯一，适合当兜底次序。 */
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'te_id', 'TE_id');

/* 九条翻页链接都是手拼的（species + gene + per_page + page），把排序并进这一份前缀，
   排完翻页排序才不会悄悄退回默认次序。 */
$__filterQs = 'species=' . urlencode($species) . '&gene=' . urlencode($gene);
$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'te_id');
$__baseQs   = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Transposable Elements in Genes - CnidoSite</title>
<meta name="keywords" content="Cnidaria, transposable elements, TEs, genome evolution, repetitive sequences" />
<meta name="description" content="Search results for transposable elements correlated with protein-coding genes" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery-1.10.2.min.js"></script>
<script src="js/highcharts.js"></script>
<script src="js/highcharts-more.js"></script>
<script src="js/exporting.js"></script>
<script src="js/sign.js" language="javascript"></script>
<script src="js/tooltip.js" type="text/javascript"></script>
<script src="js/highcharts-detail.js"></script>
<script src="js/modernizr.main.js"></script>

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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Transposable Elements in Genes</b></legend>
<p class="paleo-intro">Search results for transposable elements correlated with protein-coding genes in Cnidaria species.</p>

<?php
// 获取物种信息
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}

/* cnido_te_gene_proteins()：把 related_gene（基因级号）翻成蛋白（mRNA）级号。
   gene detail 页是按蛋白号取注释的，直接拿基因号去查会显示「no NCBI-NR hit」。 */
require_once __DIR__ . '/includes/state.php';

// 获取物种缩写和数据库名
/* species 在表单里是拉丁学名（`Acropora acuminata`），而地址栏深链常写成下划线
   形式（`Acropora_acuminata`）—— 全站其它页面两种写法都收，本页原来只认前者：
   后者查不到 abbr 行，$TE 退化成 "_TE"，于是搜索条件卡把内部名原样印出来、
   而结果一律是 0 条（实测 Acropora_acuminata + 一个确实有 184 条 TE 的基因号
   → 0 条）。先归一成学名，再转义进 SQL（原来 $species 是未转义拼进去的）。 */
$species    = cnido_latin_of($species);
$speciesEsc = mysqli_real_escape_string($conn, (string)$species);
$query2     = mysqli_query($conn, "SELECT * FROM abbr WHERE species = '$speciesEsc'");
$result2    = mysqli_fetch_row($query2);
/* abbr 的列序是 species / abbr / abbr1（0 / 1 / 2）。取 [1] 拿到的是**下划线内部名**
   （`Acropora_acuminata`），于是搜索条件卡印出内部名、而同一张表的 Species 列印的是
   正规学名，两处对不上。第 0 列才是学名。 */
$species_full_name = isset($result2[0]) ? $result2[0] : $species;
$TE = $result2 ? $result2[2] . '_TE' : '';
/* $TE 只是**按短码拼出来的表名**，表不一定真的存在：abbr 里有 PMULT 这一行，
   但 PMULT 只有基因组、没有 TE 注释表，于是 $TE = 'PMULT_TE' 非空、查询静默失败，
   页面走到「Sorry, no transposable elements were found matching your search
   criteria」那一支 —— 把「本站没做注释」说成「这个基因没有 TE」。
   以表是否真的存在为准（TE_region.php 同样处理）。 */
$__teKnown = !empty($result2);               // 物种是否在本站名录里（mysqli_fetch_row 无行时返回 NULL，不是 false）
if ($TE !== '' && !$__teKnown) { $TE = ''; }
if ($TE !== '') {
    $__chk = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $TE) . "'");
    if (!$__chk || !mysqli_num_rows($__chk)) { $TE = ''; }
}

/* $gene 直接来自 $_GET/$_POST，原先原样拼进 SQL —— 转义一遍再拼（顺带：这样也
   才敢在同一个字符串里接 ORDER BY）。% 和 _ 不转义，LIKE 的通配行为保持不变。 */
$geneEsc = mysqli_real_escape_string($conn, (string)$gene);

/* 搜索键的候选写法。
 *
 * related_gene 存的是**基因级**号（aacu_s0340.g1，TE 注释是在基因模型上做的），
 * 而从 gene_detail.php、BLAST 结果页、search.php 点进来的号是**蛋白（mRNA）级**
 * 号（aacu_s0340.g1.t1）。原来只做 `related_gene = '<原样输入>'`，于是用后者查
 * 出来的是「Sorry, no transposable elements were found matching your search
 * criteria」—— 实测 aacu_s0340.g1 有 184 条 TE，换成 .t1 就是 0 条，页面还斩钉截铁
 * 地说没有。这不是该基因没有 TE，是两种号的写法没对上（与 includes/state.php 里
 * cnido_te_gene_proteins() 记录的是同一类问题，方向相反：那边是把基因号翻成蛋白号
 * 去取注释，这边是把蛋白号翻回基因号来搜 TE）。
 *
 * 用 <ABBR>_locus 反查（mRNA → 同行的 gene），把候选写法并进 IN 列表；
 * _locus 里查不到的号（如 NVECT 的 TE 表引用了 _locus 没有的基因）仍按原样匹配，
 * 不会因为这次改动反而搜不到。 */
$__geneForms = array();
foreach (cnido_gene_id_forms($gene) as $v) { $__geneForms[$v] = true; }
if ($TE !== '' && $__geneForms) {
    $__esc0 = array();
    foreach (array_keys($__geneForms) as $v) {
        $__esc0[] = "'" . mysqli_real_escape_string($conn, $v) . "'";
    }
    $__q0 = mysqli_query($conn, "SELECT gene FROM `" . $result2[2] . "_locus`"
                              . " WHERE mRNA IN (" . implode(',', $__esc0) . ")");
    while ($__q0 && ($__r0 = mysqli_fetch_row($__q0))) {
        $__r0 = trim((string)$__r0[0]);
        if ($__r0 !== '') { $__geneForms[$__r0] = true; }
    }
}
$__geneIn = array();
foreach (array_keys($__geneForms) as $v) {
    $__geneIn[] = "'" . mysqli_real_escape_string($conn, $v) . "'";
}
$__geneWhere = $__geneIn
    ? 'related_gene IN (' . implode(',', $__geneIn) . ") OR TE_type LIKE '%$geneEsc%'"
    : "TE_type LIKE '%$geneEsc%'";

$total_records = 0;
if ($TE !== '') {
    // 查询总记录数
    $count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `$TE` WHERE $__geneWhere");
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

/* 查询当前页数据。ORDER BY 必须在 LIMIT 之前 —— 分页是按「整段结果的第 offset 行起」
   切片的，次序不定就等于切片位置不定。 */
$result = false;
if ($TE !== '') {
    $result = mysqli_query($conn, "SELECT * FROM `$TE` WHERE $__geneWhere ORDER BY $__order LIMIT $offset, $per_page");
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
            <div class="param-label">Search Term</div>
            <div class="param-value"><?= htmlspecialchars($gene) ?></div>
        </div>
        <div class="param-item">
            <div class="param-label">Total Results</div>
            <div class="param-value"><?= $total_records ?> TE<?= $total_records == 1 ? '' : 's' ?> found</div>
        </div>
    </div>
</div>

<?php if (!$__teKnown): ?>
<?php /* 物种根本不在名录里（?species= 拼错或为空）—— 第三种情况，与「没做注释」
     和「这个基因没有」都不同。原来它落到下面那支，把读者输错的串原样印回去，
     得到「have not been annotated for ,」这种残缺句子（species 为空时连名字都
     没有）。 */ ?>
<?php $__spTxt = trim((string)$species); ?>
<div class="no-results">
    <div class="no-results-icon">🔍</div>
    <h3>Species Not Recognised</h3>
    <p><?= $__spTxt === ''
            ? 'No species was given in this link, so there is nothing to search here.'
            : '<b>' . htmlspecialchars($__spTxt) . '</b> is not one of the species currently in CnidoSite.' ?>
       The <a href="/TE.php">Transposable Elements</a> page lists every species that has an annotation.</p>
</div>
<?php elseif ($TE === ''): ?>
<?php /* 这个物种根本没有 TE 注释表 —— 与「这个基因没有 TE」是两回事，分开说。
     原来两种情况共用下面那段文案，读者分不清是「没做注释」还是「基因没有」。 */ ?>
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
    <p>Sorry, no transposable elements were found matching your search criteria.</p>
    <p>Try adjusting your search term or selecting a different species.</p>
</div>
<?php else: ?>

<?php /* 结果表格容器 */ ?>
<div class="results-container">
    <table class="gridtable">
        <thead>
            <tr>
                <?php /* 表头都是服务端排序链接（js/table-sort.js 见到 <a data-sort-link>
                         就整表跳过，交给服务器）。JBrowse 那一列没有数据，不给箭头。 */ ?>
                <th width="15%"><?= cnido_sort_link('species',  'Species',      $__sort, $__dir, $__filterQs) ?></th>
                <th><?= cnido_sort_link('te_id',    'TE ID',        $__sort, $__dir, $__filterQs) ?></th>
                <th><?= cnido_sort_link('scaffold', 'Scaffold',     $__sort, $__dir, $__filterQs) ?></th>
                <th><?= cnido_sort_link('start',    'Start',        $__sort, $__dir, $__filterQs) ?></th>
                <th><?= cnido_sort_link('end',      'End',          $__sort, $__dir, $__filterQs) ?></th>
                <th width="15%"><?= cnido_sort_link('gene', 'Related Gene', $__sort, $__dir, $__filterQs) ?></th>
                <th><?= cnido_sort_link('region',   'Distribution', $__sort, $__dir, $__filterQs) ?></th>
                <th width="15%"><?= cnido_sort_link('te_type', 'TE Type', $__sort, $__dir, $__filterQs) ?></th>
                <th>JBrowse</th>
            </tr>
        </thead>
        <tbody>
            <?php
            /* 先取齐这一页的行，好把 related_gene 一次性换成蛋白号
               （每页最多 100 行，一次查询 ~2 ms；逐行查会变成 100 次）。 */
            $te_rows = array();
            if ($result) { while ($r = mysqli_fetch_row($result)) { $te_rows[] = $r; } }
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
                📊 Transposable Elements Related to "<?= htmlspecialchars($gene) ?>": <?= $total_records ?> Records
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
