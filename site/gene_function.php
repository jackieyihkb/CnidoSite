<?php
if (isset($_GET['species']) ) {	$species = $_GET['species'];}
elseif (isset($_POST['species'])) {	$species = $_POST['species'];}
else {	$species = ''; }
/* species 在站内有三种写法：拉丁双名（Nematostella vectensis）、abbr 表里的下划线
   内部名（Nematostella_vectensis）、abbr1 短码（NVECT）。本页原来只认第一种 ——
   "SELECT * FROM abbr WHERE species = '...'" 对另两种写法查不到行，于是标题栏把
   传进来的原串印出来、结果一律是空表并显示「No … information found」：把「号的
   写法没对上」说成了「这个物种没有这项注释」。busco_result.php / gene_detail.php
   早已做了同一件事，这里补齐。cnido_latin_of() 查不到时原样返回，未知物种的行为
   与改前一致。 */
require_once __DIR__ . '/includes/state.php';
$species = cnido_latin_of($species);
$speciesHtml = htmlspecialchars($species, ENT_QUOTES, 'UTF-8');
$speciesUrl  = urlencode($species);
// 分页参数
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// 验证参数有效性
if ($per_page <= 0) $per_page = 20;
if ($page <= 0) $page = 1;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Keywords Search Results - CnidoSite</title>
<meta name="keywords" content="" />
<meta name="description" content="Keyword search over InterPro annotation: each matched gene with its InterPro entry ID and description." />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<style>
<?php /* BUSCO结果页面专用样式 - 与其他页面保持一致 */ ?>
.busco-result-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.busco-result-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.busco-result-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.species-badge {
    background:linear-gradient(135deg, #b45309 0%, #92400e 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

<?php /* 分页样式 - 与其他页面保持一致 */ ?>
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
    border-color:#b45309;
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

<?php /* 状态标签 */ ?>
.status-complete {
    background:#047857;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-fragmented {
    background:#b45309;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-duplicated {
    background:#6d28d9;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-missing {
    background: #b91c1c;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

@media (max-width: 768px) {
    .busco-result-header {
        padding: 20px;
    }
    
    .busco-result-header h1 {
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Keywords Search Results</b></legend>
<p  class="paleo-intro">InterPro annotation of each matched gene, with the InterPro entry ID and its description.</p>

<?php
if (isset($_GET['keyword']) ) {
    $keyword = $_GET['keyword'];
} elseif (isset($_POST['keyword'])) {
    $keyword = $_POST['keyword'];
} else {
    $keyword = '';
}
//$genefamily='TF';
//$group='MYB';
//print_r($group);
if($keyword==""){
	echo "<script>alert(\"You have not entered any keywords. Please try again.\");</script>";
	echo "<script>window.location =\"search.php\";</script>";

}
	$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	/* species / keyword 都直接来自 URL，原先被原样拼进三处 SQL
	   （?species=' OR 1=1 -- 或 keyword 里带引号即可改写查询），
	   取值先兜住「没传」，进 SQL 前一律转义。 */
	$speciesSql = mysqli_real_escape_string($conn, $species);
	$keywordSql = mysqli_real_escape_string($conn, $keyword);
	$query2=mysqli_query($conn,"SELECT * FROM abbr WHERE  species = '$speciesSql' ");
	$result2=mysqli_fetch_row($query2);
	/* 物种名不认识（或根本没传）时 $result2 是 null：$ipr 退化成 "_ipr"，两个查询都
	   建表失败，$total_records 印成空白，页面却弹「No record in this species matches
	   …」—— 把「没有这个物种」说成了「这个物种没有命中」，读者会去改关键词而问题
	   在物种。与 gene_locus_res.php 是同一类混淆。这里显式分成「不认识这个物种」
	   一态，下面所有查询都跳过。 */
	$__unknownSp = !$result2;
	if ($__unknownSp) {
		$ipr = '';
		$result1 = array(0);
		$total_records = 0;
	} else {
		$ipr = $result2[2]. "_ipr";
		$query1=mysqli_query($conn,"SELECT count(*) FROM $ipr where Description like '%$keywordSql%'");
		$result1=mysqli_fetch_row($query1);
		$total_records = $result1[0];
	}
	
	// 计算总页数
	$total_pages = ceil($total_records / $per_page);
	
	// 确保当前页不超过总页数
	if ($page > $total_pages && $total_pages > 0) {
		$page = $total_pages;
	}
	
	// 计算偏移量
	$offset = ($page - 1) * $per_page;
	
	/* 服务端排序。#ipr 表六列全是 text，没有天然序，默认项写 null ＝ 不生成
	   ORDER BY（初始次序与加按钮前一致）。Species 列不排：它是 abbr.abbr1，
	   整页只有查询参数这一个值；JBrowse 列是链接不是数据。 */
	require_once __DIR__ . '/includes/sort_head.php';
	$__sortKeys = array(
		'default' => null,
		'gene'    => cnido_sort_txt('gene'),
		'ipr'     => cnido_sort_txt('InterPro_term'),
		'desc'    => cnido_sort_txt('Description'),
	);
	$__tie = array('gene', 'InterPro_term', 'Description', 'Type', 'Source', 'URL');
	list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', $__tie);
	$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');
	$__filterQs = 'species=' . urlencode($species) . '&keyword=' . urlencode($keyword);
	$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
	$__baseQs   = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');
	/* 排序链接必须自己带上 per_page：分页条的每条链接都写 &per_page=$per_page，
	   只有排序链接原来只传 $__filterQs，于是「每页 100 条时点一下表头就变回 20 条」。
	   不能直接把 per_page 并进 $__filterQs —— $__baseQs 也用它，而分页链接会在后面
	   再拼一次 per_page，会出现同一参数两遍。这里单开一份给排序用。 */
	$__sortBaseQs = $__filterQs . '&per_page=' . (int)$per_page;

	// 查询当前页数据（物种不认识时 $ipr 是空的，别去建这张表）
	$query = $__unknownSp
	       ? false
	       : mysqli_query($conn,"SELECT * FROM $ipr where Description like '%$keywordSql%'" . $__orderSql . " LIMIT $offset, $per_page");
?>

<?php /* 分页导航 */ ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* 没有 species 参数时原来印成 "Total Search Records in : 0" —— 冒号前空着。
                     物种名不认识时也不能印：那会是一句「Foo bar 有 0 条记录」的断言，
                     而事实是本站根本没有这个物种。 */ ?>
            <?php if ($__unknownSp): ?>
            📊 Total Search Records: <span class="mx-na" title="no such species in CnidoSite">&mdash;</span>
            <?php else: ?>
            📊 Total Search Records<?php if (trim((string)$species) !== ''): ?> in <strong><i><?php echo htmlspecialchars($species); ?></i></strong><?php endif; ?>: <?= $total_records ?>
            <?php endif; ?>
        </div>
        
        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page='+this.value+'&page=1'">
                <?php
                $options = [10, 20, 50, 100];
                foreach ($options as $option) {
                    $selected = ($option == $per_page) ? 'selected' : '';
                    echo "<option value='$option' $selected>$option</option>";
                }
                ?>
            </select>
            <?php /* 每页切的是注释记录（一行 = 一个基因的一条 InterPro 注释），
                     不是基因；原来写 "genes per page"，与上面 "Total Search Records" 口径不一致。 */ ?>
            <span>records per page</span>
        </div>
    </div>
    
    <?php /* 表格容器 */ ?>
    <div class="table-container">
        <div class="table-scroll-container">
		
		<?php
		if($result1[0] > 0){
				echo "<table class=\"gridtable\">";
				echo "<tr><th>Species</th>"
				   . "<th>" . cnido_sort_link('gene', 'Gene', $__sort, $__dir, $__sortBaseQs) . "</th>"
				   . "<th>" . cnido_sort_link('ipr', 'InterPro ID', $__sort, $__dir, $__sortBaseQs) . "</th>"
				   . "<th width='45%'>" . cnido_sort_link('desc', 'Description', $__sort, $__dir, $__sortBaseQs) . "</th>"
				   . "<th>JBrowse</th></tr>";
		while($result=mysqli_fetch_row($query))
			{
				$locus = $result2[2]. "_locus";
				/* $result[*] 全部来自库，进 SQL / HTML 前分别转义；
				   库里有 Description 含 '<' 或 '&'，原样印会吃掉页面结构。 */
				$geneSql = mysqli_real_escape_string($conn, $result[0]);
				$query3=mysqli_query($conn,"SELECT * FROM $locus WHERE mRNA = '$geneSql' || gene = '$geneSql'");
				$result3=mysqli_fetch_row($query3);
				/* 物种没有 <abbr1>_locus 表、或这个基因不在表里时 $result3 是 null：
				   改前 $result3[2..4] 会先报三条 PHP 警告，再拼出 loc="::..." —— 一个
				   没有任何坐标的 JBrowse 视图（而该物种的 jbrowse/ 目录通常也不存在）。
				   没有坐标就不要再给按钮，照全站「没有值」的记号印破折号。 */
				if ($result3 && isset($result3[2], $result3[3], $result3[4]) && (string)$result3[2] !== '') {
					$loc  = $result3[2] . ":" . $result3[3] . "..." . $result3[4];
					$jbCell = "<a href='./jbrowse/index.html?data=" . urlencode($result2[2]) . "&amp;gene=" . urlencode($result[0]) . "&amp;loc=" . urlencode($loc) . "'>JBrowse</a>";
				} else {
					$jbCell = "<span class=\"mx-na\" title=\"no genomic coordinates for this gene\">&mdash;</span>";
				}
				$geneHtml = htmlspecialchars($result[0], ENT_QUOTES, 'UTF-8');
				$iprHtml  = htmlspecialchars($result[1], ENT_QUOTES, 'UTF-8');
				$descHtml = htmlspecialchars($result[3], ENT_QUOTES, 'UTF-8');
				echo "<tr align='center'>"
				   . "<td><a href=\"speciesinfo.php?species=" . urlencode($result2[2]) . "\">" . $speciesHtml . "</a></td>"
				   . "<td><a href=\"./gene_detail.php?gene=" . urlencode($result[0]) . "&amp;species=" . $speciesUrl . "\">" . $geneHtml . "</a></td>"
				   . "<td><a href=\"https://www.ebi.ac.uk/interpro/entry/InterPro/" . urlencode($result[1]) . "\" target='_blank'>" . $iprHtml . "</a></td>"
				   . "<td>" . $descHtml . "</td>"
				   . "<td>" . $jbCell . "</td></tr>";
			}
			echo "</table><br>";
			} else{
			/* 命中 0 条时原来只有 alert + 跳回 search.php：浏览器禁用 JS（或脚本被
			   拦）时访客看到的就只是上面那行「Total Search Records … 0」加一片空白，
			   页面本身一句解释都没有。这里先印一条站内提示（同 .gd-notice 的写法），
			   再保留原来的 alert + 跳转。
			   物种名不认识时不能印「这个物种没有命中」——那是在替一个本站没有的
			   物种背书。分开报。 */
			if ($__unknownSp) {
				$__spTxt = trim((string)$species);
				$__msg = ($__spTxt === '')
				       ? 'You did not choose a species. Please pick one from the Species list and search again.'
				       : 'CnidoSite does not hold annotation for "' . $__spTxt . '". Please pick a species from the Species list.';
				echo '<p class="gd-notice gd-warn" style="margin:24px 0">'
				   . htmlspecialchars($__msg, ENT_QUOTES, 'UTF-8')
				   . ' See <a href="/search.php">Gene Search</a> for the species CnidoSite covers.</p>';
				echo '<script>alert(' . json_encode($__msg) . ');window.location="search.php";</script>';
			} else {
				echo "<p class=\"gd-notice gd-warn\" style=\"margin:24px 0\">No record in this species matches <b>" . htmlspecialchars((string)$keyword, ENT_QUOTES, 'UTF-8') . "</b>. Check the spelling, or try the gene's own page from <a href=\"/search.php\">Gene Search</a>.</p>";
				echo "<script>alert(\"No gene matched that keyword. Please check the term and try again.\");</script>";
				echo "<script>window.location =\"search.php\";</script>";
			}
			echo "<div  style=\"height: 450px; \"></div>";
			}
				?>
        </div>
    </div>
    
    <?php /* 分页导航按钮。命中 0 条时不渲染：$total_pages 是 0，上面的页码循环一次
         都不进，但 First / Previous / Next / Last 四个按钮照旧印出来，全部指向
         自己（page=0）。站内约定见 busco_result.php / browse.php。 */ ?>
    <?php if ($total_pages > 0): ?>
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
    <?php endif; ?>
    
    <?php /* 跳转到指定页。命中 0 条时 $total_pages 是 0，这一块会印出
         min="1" max="0" 的输入框、「of 0 pages」和一个永远指向自己的 Go 按钮。
         站内约定是命中 0 条不渲染分页条（busco_result.php / browse.php 已如此）。 */ ?>
    <?php if ($total_pages > 0): ?>
    <div class="go-to-page">
        <span>Go to page:</span>
        <input type="number" id="gotoPage" min="1" max="<?= $total_pages ?>" value="<?= $page ?>">
        <button onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page='+document.getElementById('gotoPage').value">
            Go
        </button>
        <span>of <?= $total_pages ?> pages</span>
    </div>
    <?php endif; ?>
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
