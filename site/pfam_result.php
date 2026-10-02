<?php
/* species / pfam 都是直接从 URL 取的。原先 species 被原样拼进
   "SELECT * FROM abbr WHERE species = '$species'"，pfam 被原样拼进两处 WHERE，
   于是 ?species=' UNION SELECT ... -- 这类参数可以读到任意表。
   这里统一：取值先兜住「没传」，输出前按用途分别转义（SQL / HTML / URL）。 */
$species = isset($_GET['species']) ? $_GET['species']
         : (isset($_POST['species']) ? $_POST['species'] : '');
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
<title>Pfam Search Results - CnidoSite</title>
<meta name="keywords" content="" />
<meta name="description" content="Pfam domains assigned to each matched gene, with the Pfam accession, name and type." />
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Pfam Search Results</b></legend>
<p  class="paleo-intro">Pfam domains assigned to each matched gene, with the Pfam accession, name and type.</p>

<?php
if (isset($_GET['pfam']) ) {
    $pfam = $_GET['pfam'];
} elseif (isset($_POST['pfam'])) {
    $pfam = $_POST['pfam'];
} else {
    $pfam = '';
}

if($pfam==""){
	echo "<script>alert(\"You have not entered any Pfam accessions. Please try again.\");</script>";
	echo "<script>window.location =\"search.php\";</script>";
}
	$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	$speciesSql = mysqli_real_escape_string($conn, $species);
	$pfamSql    = mysqli_real_escape_string($conn, $pfam);
	$query2=mysqli_query($conn,"SELECT * FROM abbr WHERE  species = '$speciesSql' ");
	$result2=mysqli_fetch_row($query2);
	/* 物种名在 abbr 里查不到时不要硬拼表名（会得到 mysqli 报错 + 空表）。
	   与「没搜到结果」同样处理：给出说明，页面其余部分按空结果渲染。 */
	$__speciesOk = (bool)$result2;
	$tabpfam = $__speciesOk ? $result2[2] . "_pfam" : '';
	if ($__speciesOk) {
		$query1 = mysqli_query($conn, "SELECT COUNT(DISTINCT gene) FROM $tabpfam WHERE Pfam_accession = '$pfamSql'");
		$result1 = mysqli_fetch_row($query1);
		$total_records = $result1[0]; // 这才是正确的去重后总条数
	} else {
		$total_records = 0;
		$result1 = array(0);
	}
	
	// 计算总页数
	$total_pages = ceil($total_records / $per_page);
	
	// 确保当前页不超过总页数
	if ($page > $total_pages && $total_pages > 0) {
		$page = $total_pages;
	}
	
	// 计算偏移量
	$offset = ($page - 1) * $per_page;
	
	/* 服务端排序。pfam 表七列全是 text，本身没有天然序，默认项写 null ＝
	   不生成 ORDER BY，页面初始次序与加按钮之前逐字节一致，点了某列才真排。
	   Species 列不参与排序：它是 abbr.abbr1，整页只有一个值（就是查询参数本身），
	   排它没有任何可动的东西；JBrowse 列是链接不是数据。 */
	require_once __DIR__ . '/includes/sort_head.php';
	$__sortKeys = array(
		'default' => null,
		'gene'    => cnido_sort_txt('gene'),
		'pfam'    => cnido_sort_txt('Pfam_accession'),
		'name'    => cnido_sort_txt('Pfam_name'),
		'desc'    => cnido_sort_txt('Description'),
		'type'    => cnido_sort_txt('Type'),
	);
	/* 查询是 SELECT DISTINCT *：去重之后仍可能有好几行**显示内容完全相同**（只在
	   Source / URL 上不同，这两列不显示）。并列键必须把七列全列上，否则同一批行在
	   LIMIT 边界上会在两页都出现或都不出现。 */
	$__tie = array('gene', 'Pfam_accession', 'Pfam_name', 'Description', 'Type', 'Source', 'URL');
	list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', $__tie);
	$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');
	$__filterQs = 'species=' . urlencode($species) . '&pfam=' . urlencode($pfam);
	$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
	$__baseQs   = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');

	// 查询当前页数据
	$query = mysqli_query($conn, "SELECT DISTINCT * FROM $tabpfam WHERE Pfam_accession = '$pfamSql'" . $__orderSql . " LIMIT $offset, $per_page");
?>

<?php /* 分页导航 */ ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* 没有 species 参数时原来印成 "Total Search Records in : 0"。 */ ?>
            📊 Total Search Records<?php if (trim((string)$species) !== ''): ?> in <strong><i><?php echo htmlspecialchars($species); ?></i></strong><?php endif; ?>: <?= $total_records ?>
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
            <span>genes per page</span>
        </div>
    </div>
    
    <?php /* 表格容器 */ ?>
    <div class="table-container">
        <div class="table-scroll-container">
		
		<?php
		if(!$__speciesOk){
			/* 没选物种时 $speciesHtml 是空串，原句会渲染成 "has no Pfam annotation for ." */
			echo '<div class="gd-notice gd-warn" style="margin:10px 0">'
			   . ($speciesHtml !== ''
			        ? 'CnidoSite has no Pfam annotation for <b>' . $speciesHtml . '</b>. Please pick a species from the '
			        : 'No species was selected. Please pick one from the ')
			   . '<a href="proteindomain.php">Pfam search page</a>.</div>';
			echo "<div style=\"height: 450px;\"></div>";
			} elseif($result1[0] > 0){
				echo "<table class=\"gridtable\">";
				echo "<tr><th width='12%'>Species</th>"
				   . "<th width='12%'>" . cnido_sort_link('gene', 'Gene', $__sort, $__dir, $__filterQs) . "</th>"
				   . "<th>" . cnido_sort_link('pfam', 'Pfam accession', $__sort, $__dir, $__filterQs) . "</th>"
				   . "<th width='10%'>" . cnido_sort_link('name', 'Pfam name', $__sort, $__dir, $__filterQs) . "</th>"
				   . "<th>" . cnido_sort_link('desc', 'Description', $__sort, $__dir, $__filterQs) . "</th>"
				   . "<th>" . cnido_sort_link('type', 'Type', $__sort, $__dir, $__filterQs) . "</th>"
				   . "<th>JBrowse</th></tr>";
		while($result=mysqli_fetch_row($query))
			{
				$locus = $result2[2]. "_locus";
				$geneSql = mysqli_real_escape_string($conn, $result[0]);
				$query3=mysqli_query($conn,"SELECT * FROM $locus WHERE mRNA = '$geneSql' || gene = '$geneSql'");
				$result3=mysqli_fetch_row($query3);
				$loc=$result3[2].":".$result3[3]."...".$result3[4];
				/* $species 与 $result2[2] 都来自请求/数据库，进 HTML 属性前转义；
				   顺带修掉这里原本嵌套成 <a href="<a href="... 的坏链接。 */
				echo "<tr align='center'><td><a href=\"speciesinfo.php?species=" . urlencode($result2[2]) . "\">" . $speciesHtml . "</a></td>"
				   . "<td><a href=\"./gene_detail.php?gene=" . urlencode($result[0]) . "&amp;species=" . $speciesUrl . "\">" . htmlspecialchars($result[0], ENT_QUOTES, 'UTF-8') . "</a></td>"
				   . "<td><a href=\"https://www.ebi.ac.uk/interpro/entry/pfam/" . urlencode($result[1]) . "\" target='_blank'>" . htmlspecialchars($result[1], ENT_QUOTES, 'UTF-8') . "</a></td>"
				   . "<td>" . htmlspecialchars($result[2], ENT_QUOTES, 'UTF-8') . "</td>"
				   . "<td>" . htmlspecialchars($result[3], ENT_QUOTES, 'UTF-8') . "</td>"
				   . "<td>" . htmlspecialchars($result[4], ENT_QUOTES, 'UTF-8') . "</td>"
				   . "<td><a href='./jbrowse/index.html?data=" . urlencode($result2[2]) . "&amp;gene=" . urlencode($result[0]) . "&amp;loc=" . urlencode($loc) . "'>JBrowse</a></td></tr>";
			}
			echo "</table><br>";
			} else{
			echo "<script>alert(\"No gene matched that keyword. Please check the term and try again.\");</script>";
			echo "<script>window.location =\"search.php\";</script>";
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
