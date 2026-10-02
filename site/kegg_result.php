<?php
/* $genelist / $species 直接来自 URL 或表单：既要拼进 SQL，也会回显到页面上。
   取值时先兜住「参数没传」的情况（原来直接读 $_POST[...]，只传 GET 时会报
   Undefined index）；SQL 转义放在建立连接之后，见下。 */
$genelist = isset($_GET['genelist']) ? $_GET['genelist']
          : (isset($_POST['genelist']) ? $_POST['genelist'] : '');
$species  = isset($_GET['species'])  ? $_GET['species']
          : (isset($_POST['species'])  ? $_POST['species']  : '');
/* species 在站内有三种写法：拉丁双名（Nematostella vectensis）、abbr 表里的下划线
   内部名（Nematostella_vectensis）、abbr1 短码（NVECT）。本页原来只认第一种 ——
   "SELECT * FROM abbr WHERE species = '...'" 对另两种写法查不到行，于是标题栏把
   传进来的原串印出来、结果一律是空表并显示「No … information found」：把「号的
   写法没对上」说成了「这个物种没有这项注释」。busco_result.php / gene_detail.php
   早已做了同一件事，这里补齐。cnido_latin_of() 查不到时原样返回，未知物种的行为
   与改前一致。 */
require_once __DIR__ . '/includes/state.php';
$species = cnido_latin_of($species);

// 分页参数
$per_page_options = [10, 20, 50, 100];
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
if (!in_array($per_page, $per_page_options)) {
    $per_page = 20;
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

// 验证输入
if (trim($genelist) == "") {
    echo "<script>alert(\"Please enter at least one gene.\");</script>";
    echo "<script>window.location =\"kegg.php\";</script>";
    exit;
}

/* 分隔符：空白与逗号都算。输入框的 placeholder 和 checkquery() 一直写着「空格或
   逗号」，这里原来只切空白 —— 逗号连写的清单会被当成一个无效号（见 go_result.php）。 */
$glist = preg_replace("/[\s,]+/", "\n", $genelist);
$list = explode("\n", $glist);
$list = array_unique($list);
$list = array_filter($list);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>KEGG Pathway Results - CnidoSite</title>
<meta name="keywords" content="Cnidaria, KEGG pathway, metabolic pathways, genome annotation" />
<meta name="description" content="KEGG pathway analysis results for selected genes in Cnidaria species" />
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
<?php /* KEGG结果页面专用样式 - 与busco_result.php保持一致 */ ?>
.kegg-result-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.kegg-result-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.kegg-result-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.species-badge {
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

<?php /* 分页样式 - 与busco_result.php保持一致 */ ?>
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
    border-color:#047857;
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

<?php /* 表格美化 */ ?>

.table-scroll-container {
    max-height: 700px;
    overflow-y: auto;
    overflow-x: auto;
}

<?php /* 链接样式 */ ?>
.gene-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.gene-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.kegg-link {
    color:#047857;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.kegg-link:hover {
    color:#047857;
    text-decoration: underline;
}

.jbrowse-link {
    color:#b45309;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.jbrowse-link:hover {
    color: #92400e;
    text-decoration: underline;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .kegg-result-header {
        padding: 20px;
    }
    
    .kegg-result-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>KEGG Pathway Results</b></legend>
<p class="paleo-intro">KEGG pathway analysis results for selected genes in <b><i><?= htmlspecialchars($species, ENT_QUOTES, 'UTF-8') ?></i></b>.</p>

<?php
// 数据库连接
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}

/* $species 以及基因列表里的每个 $gene 都会被拼进 SQL，先转义再拼。 */
$speciesEsc = mysqli_real_escape_string($conn, (string)$species);

// 获取物种缩写
$query2 = mysqli_query($conn, "SELECT * FROM abbr WHERE species = '$speciesEsc'");
$result2 = mysqli_fetch_row($query2);
$db_prefix = $result2[2] ?? '';
$kegg_table = $db_prefix . "_KEGG";

// 收集所有行数据
$all_rows = [];
/* $nFound：$list 里真正查到注释的基因有几个。表头原来写「for N Genes」，N 直接取
   count($list) —— 那是**提交**的号数，号不存在也照算（见 go_result.php 同一处）。 */
$nFound = 0;
foreach ($list as $gene) {
    $geneEsc = mysqli_real_escape_string($conn, (string)$gene);
    $query = mysqli_query($conn, "SELECT * FROM $kegg_table WHERE gene = '$geneEsc'");
    if ($query) {
        $n = 0;
        while ($row = mysqli_fetch_row($query)) {
            $all_rows[] = $row;
            $n++;
        }
        if ($n > 0) { $nFound++; }
    }
}

/* 服务端排序。这一页的行不经过 SQL 的 ORDER BY：上面按 $list 里的基因逐个查、
   拼成 $all_rows，所以只能在 array_slice 切片**之前**用 PHP 自己排（见
   includes/sort_head.php 的 cnido_sort_rows）。不是切片之后对当前页排 —— 那样
   只是把这一页的 20 行换个次序，看起来排好了，实际每页各排各的。
   $__sortKeys 的值这里用不上（没有 SQL 可拼），写 null 即可，只用来校验 $_GET。
   Enzyme 那一列拼的是 Abbreviation（下标 2）与 Enzymes（下标 3）两栏：
   下标 3 非空时才用「; 」接在下标 2 后面（见下面的 $enzyme），所以排在第 2 列、
   用第 3 列当第一并列键，次序与显示一致。别把这两个名字写反 —— 表结构是
   gene / KO / Abbreviation / Enzymes / Enzyme_ID / Pathway / Pathway_ID / Source。
   其余的并列键（KO / Enzyme_ID / Pathway / Pathway_ID）依次比下去，构成全序。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array('default' => null, 'gene' => null, 'ko' => null, 'enzyme' => null,
                    'enzyme_id' => null, 'pathway' => null, 'map_id' => null);
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default');
cnido_sort_rows($all_rows, array('gene' => 0, 'ko' => 1, 'enzyme' => 2, 'enzyme_id' => 4,
                                 'pathway' => 5, 'map_id' => 6),
                $__sort, $__dir,
                array('gene', 'ko', 'enzyme', 'enzyme_id', 'pathway', 'map_id'));
$__filterQs = 'species=' . urlencode($species) . '&genelist=' . urlencode($genelist);
$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
$__baseQs   = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');

$total_records = count($all_rows);
$total_pages = ceil($total_records / $per_page);

// 确保当前页不超过总页数
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}

// 计算偏移量
$offset = ($page - 1) * $per_page;

// 获取当前页的数据
$current_page_rows = array_slice($all_rows, $offset, $per_page);
?>

<?php /* 分页导航 */ ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* $total_records 是「基因 × 通路」的注释行数，不是去重后的通路数
                     （一个通路被几个基因共用就计几次）：原标签写 "Total KEGG Pathways"，
                     数字会比下面表里真正出现的通路多。改成按行说。
                     提交数与命中数也分开印：只印提交数会把查不到的号也算成命中。 */ ?>
            <?php $__nSub = count($list); ?>
            📊 Total KEGG pathway annotations: <?= $total_records ?> &mdash; from <?= $nFound ?><?= $nFound === $__nSub ? '' : ' of ' . $__nSub ?> submitted gene ID<?= $__nSub == 1 ? '' : 's' ?>
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
            <span>pathways per page</span>
        </div>
    </div>
    
    <?php /* 表格容器 */ ?>
    <div class="table-container">
        <div class="table-scroll-container">
            <table class="gridtable">
                <thead>
                    <tr>
                        <th width="15%"><?php echo cnido_sort_link('gene', 'Gene', $__sort, $__dir, $__filterQs); ?></th>
                        <th><?php echo cnido_sort_link('ko', 'KO ID', $__sort, $__dir, $__filterQs); ?></th>
                        <?php /* Enzyme / Pathway 都是自由文本（KO 定义、通路名），居中难看，显式左对齐。
                                   「Enzyme」这个名字是错的：这一列是 Abbreviation 拼上 "; " 再拼
                                   Enzymes，印出来是「H2B; histone H2B」这样的 KO 定义，不是酶分类；
                                   真正的 EC 号在下一列，而 13,377 行里 9,082 行的 Enzyme_ID 是 '-'。
                                   排序键保持 'enzyme' 不动，只改读者看到的名字。 */ ?>
                        <th width="20%" class="tal" title="KEGG's own definition of the ortholog: the gene abbreviation followed by its full name (for example &quot;H2B; histone H2B&quot;). It is not an enzyme classification &mdash; the EC number, where the ortholog has one, is in the Enzyme ID column."><?php echo cnido_sort_link('enzyme', 'KO definition', $__sort, $__dir, $__filterQs); ?></th>
                        <th><?php echo cnido_sort_link('enzyme_id', 'Enzyme ID', $__sort, $__dir, $__filterQs); ?></th>
                        <th width="15%" class="tal"><?php echo cnido_sort_link('pathway', 'Pathway', $__sort, $__dir, $__filterQs); ?></th>
                        <th><?php echo cnido_sort_link('map_id', 'Map ID', $__sort, $__dir, $__filterQs); ?></th>
                        <?php /* Visualization 是每行一个 JBrowse 外链（列的值不是表里的数据），没有可排的次序，保持纯 th。 */ ?>
                        <th>Visualization</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (empty($current_page_rows)) {
                        echo "<tr align='center'><td colspan='7'>No KEGG pathway information found for the selected genes. This may be because this species has no KEGG annotation in this release, or because none of the submitted genes carries a KEGG ortholog or pathway.</td></tr>";
                    } else {
                        foreach ($current_page_rows as $row) {
                            $gene_id = $row[0] ?? '-';
                            $ko = $row[1] ?? '-';
                            $enzyme1 = $row[2] ?? '-';
                            $enzyme2 = $row[3] ?? '-';
                            $enzyme_id = $row[4] ?? '-';
                            $pathway = $row[5] ?? '-';
                            $map_id = $row[6] ?? '-';
                            
                            $enzyme = $enzyme1;
                            if ($enzyme2 != '-' && $enzyme2 != '') {
                                $enzyme .= "; " . $enzyme2;
                            }
                            
                            echo "<tr align='center'>";
                            echo "<td><a href=\"" . htmlspecialchars("gene_detail.php?gene=" . urlencode($gene_id) . "&species=" . urlencode($species), ENT_QUOTES, 'UTF-8') . "\" class=\"gene-link\">" . htmlspecialchars($gene_id, ENT_QUOTES, 'UTF-8') . "</a></td>";
                            echo "<td>";
                            if ($ko != '-') {
                                echo "<a href=\"http://www.genome.jp/dbget-bin/www_bget?ko:$ko\" target=\"_blank\" class=\"kegg-link\">$ko</a>";
                            } else {
                                echo "-";
                            }
                            echo "</td>";
                            echo "<td class=\"tal\">$enzyme</td>";
                            echo "<td>";
                            if ($enzyme_id != '-') {
                                $ids = explode(" ", $enzyme_id);
                                foreach ($ids as $id) {
                                    if (strpos($id, "-") !== false) {
                                        echo "$id<br>";
                                    } else {
                                        echo "<a href=\"http://www.genome.jp/dbget-bin/www_bget?$id\" target=\"_blank\" class=\"kegg-link\">$id</a><br>";
                                    }
                                }
                            } else {
                                echo "-";
                            }
                            echo "</td>";
                            echo "<td class=\"tal\">$pathway</td>";
                            echo "<td>";
                            if ($map_id != '-') {
                                /* 原来是 kegg-bin/show_brite?$map_id.keg —— .keg 是 BRITE 的写法，
                                   真·通路图（ko00010 这类，占多数）在那个入口下一律 404。分派逻辑
                                   与名单在 includes/state.php 的 cnido_kegg_map_url()。 */
                                echo "<a href=\"" . htmlspecialchars(cnido_kegg_map_url($map_id), ENT_QUOTES, 'UTF-8') . "\" target=\"_blank\" class=\"kegg-link\">" . htmlspecialchars($map_id, ENT_QUOTES, 'UTF-8') . "</a>";
                            } else {
                                echo "-";
                            }
                            echo "</td>";
                            echo "<td><a href='./jbrowse/index.html?data=$db_prefix&gene=$gene_id' class=\"jbrowse-link\">JBrowse</a></td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php /* 分页导航按钮 */ ?>
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
</div>
</div>

<?php
$conn->close();
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
