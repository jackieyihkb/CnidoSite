<?php
if (isset($_GET['species']) ) {	$species = $_GET['species'];} else {$species = $_POST['species'];}
/* species 在站内有三种写法（拉丁双名 / abbr 表里的下划线内部名 / abbr1 短码），本页
   原来只认第一种：另两种查不到 abbr 行，$locus 退化成 "_locus"，于是不管区间对不对
   都弹「No gene model overlaps that interval」—— 把「号的写法没对上」说成了「这段
   区间里没有基因」。与 busco_result.php / gene_detail.php 一致，先归一成拉丁学名；
   cnido_latin_of() 查不到时原样返回，未知物种的行为与改前一致。 */
require_once __DIR__ . '/includes/state.php';
$species = cnido_latin_of($species);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Locus Search Results - CnidoSite</title>
<meta name="keywords" content="" />
<meta name="description" content="Transcripts overlapping a genomic interval: species, transcript ID, functional annotation, coordinates and strand, with a JBrowse link for each locus." />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Locus Search Results</b></legend>
<?php /* 页头引言。原来整页只有「找到 N 条」那一句，而且它写在「有结果」的分支里，
   没有结果时页面开头就是光秃秃一条标题，和站内其它页面的开场不一致。这里补一段
   与结果无关的固定说明，任何状态下开场都完整。 */ ?>
<?php /* 一行 = 一条预测转录本，不是基因。`<ABBR>_locus` 是 mRNA→gene 的映射表，
   同一个基因的多个剪接体会各占一行、坐标还完全相同 —— 实测查 NVECT 的
   NC_064034.1:560000-570000 得到的 5 行全是 LOC116613690 的 5 个 isoform。
   所以「5 条」不能读成「5 个基因」。站内 2026-09-29 已把覆盖矩阵那类按行计数的
   标签统一改成 Transcripts，这里照同一口径。 */ ?>
<p class="paleo-intro">Transcripts overlapping the genomic interval you submitted, listed with their
species, transcript ID, functional annotation, chromosome, start/end coordinates and strand. One row
is one predicted transcript, so a gene with several isoforms contributes more than one row and those
rows share the same coordinates. Use the JBrowse link on each row to open that locus in the genome
browser.</p>

<?php
if (isset($_GET['position']) ) {	$position = $_GET['position'];} else {$position = $_POST['position'];}
if($position==""){
	echo "<script>alert(\"You did not enter a genomic region. Please try again.\");</script>";
	echo "<script>window.location =\"search.php\";</script>";
}
	$position1=explode(':',$position);
	$position2=explode('-',$position1[1]);
	/* 坐标串的格式是 "assembly:start-end"（本页示例给的是 NC_064045.1:2300000-2400000），
	   三段都来自用户输入。改前这三段是原样拼进 SQL 的 —— 物种名不转义也不归一、
	   组装号不转义、两个坐标直接当初数字扔进去，一个引号就能改写查询。这里统一
	   转成安全形式；坐标不是数字时当 0，与改前「MySQL 把非数字串当 0 比」一致。 */
	$asm      = isset($position1[0]) ? trim($position1[0]) : '';
	$posStart = isset($position2[0]) ? (int)$position2[0] : 0;
	$posEnd   = isset($position2[1]) ? (int)$position2[1] : 0;
	$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	$speciesEsc = mysqli_real_escape_string($conn, (string)$species);
	$asmEsc     = mysqli_real_escape_string($conn, $asm);
	$result2=mysqli_fetch_row(mysqli_query($conn,"SELECT * FROM abbr WHERE species = '$speciesEsc'"));
	$locus = $result2 ? $result2[2]. "_locus" : '';
	/* 「物种认识但没有坐标表」与「这段区间里没有基因」必须分开报。abbr 里有 326 个
	   物种，而建了 <abbr1>_locus 的只有 145 个；对另外那 181 个，下面的查询建表失败
	   （Web SAPI 上错误是静默的），$__n 停在 0，页面就弹「No transcript overlaps that
	   interval」—— 读者据此以为是自己坐标写错了，实际是**这个物种没有坐标数据**。
	   判据查 information_schema，不写死名单。 */
	$__hasLocus = false;
	if ($locus !== '') {
		$__lt = mysqli_real_escape_string($conn, $locus);
		$__lq = mysqli_query($conn, "SELECT 1 FROM information_schema.tables"
		                         . " WHERE table_schema = DATABASE() AND table_name = '$__lt' LIMIT 1");
		$__hasLocus = ($__lq && mysqli_fetch_row($__lq)) ? true : false;
	}
	/* 区间相交的判据。原来写的是
	     (start > A and start < B) or (end > A and end < B)
	   也就是「起点或终点落在查询区间内」。它会漏掉**把整个查询区间包住**的基因：
	   NC_064034.1 上 555,751–623,986 那个基因，查 560,000–570,000 时两端都在区间
	   之外，于是查不到，可这段区间明明整个落在该基因里面。正确的相交判据是
	   start <= B and end >= A。start/end 在库里是 text 列，写成 start+0 才会按
	   数值而不是按字符串比较。实测 NVECT 的 NC_064034.1 上 560,000–570,000：
	   改前 0 条，改后 5 条。 */
	$where = "assembly = '$asmEsc' AND start+0 <= $posEnd AND end+0 >= $posStart";
	$__n = 0;
	if ($locus !== '') {
		$query =mysqli_query($conn,"SELECT * FROM `$locus` WHERE $where");
		$__cnt =mysqli_fetch_row(mysqli_query($conn,"SELECT count(*) FROM `$locus` WHERE $where"));
		$__n = ($__cnt && isset($__cnt[0])) ? (int)$__cnt[0] : 0;
	}
	if ($locus === ''){
	/* 原来没有这一层：物种名不认识时 $result2 是空的，$locus 退化成 "_locus"，
	   查询必然失败、计数拿到 0，页面于是弹「No gene model overlaps that interval」
	   并跳回搜索页 —— 物种名写错和「这段区间里没有基因」是两回事，提示却一模一样。 */
	echo "<script>alert(\"Unknown species. Please choose a species from the Species list.\");</script>";
	echo "<script>window.location =\"search.php\";</script>";
	echo "<div  style=\"height: 450px; \"></div>";
	} elseif(!$__hasLocus){
	/* 物种认得，但库里没有它的坐标表。据实说明，别让读者以为是自己坐标写错了。 */
	$__spName = htmlspecialchars($result2[0], ENT_QUOTES, 'UTF-8');
	echo "<script>alert(\"CnidoSite has no gene-coordinate data for $__spName, so the position search cannot be used for this species. Its functional annotation is available from its species page and the Functional Domain Search, Gene Ontology, InterPro and KEGG modules.\");</script>";
	echo "<script>window.location =\"search.php\";</script>";
	echo "<div  style=\"height: 450px; \"></div>";
	} elseif($__n > 0){
	echo "<p  class=\"paleo-intro\">📊 Transcripts overlapping the interval: <b>$__n</b></p>";
	echo "<table class=\"gridtable\">";
	/* 这里原先还有一句 `$result1=mysqli_fetch_row($query);` —— 返回值既没被用、
	   又推进了游标，下面的 while 于是从**第二条**命中开始取，每次查询静默少显示
	   第一条：计数写 "We find 3 records" 而表里只有 2 行。已删。 */

	echo "<tr><th width='12%'>Species</th><th width='12%'>Transcript</th><th width='35%'>Annotation</th><th width='10%'>Chr</th><th>Start</th><th>End</th><th>Strand</th><th>JBrowse</th></tr>";
	while($result=mysqli_fetch_row($query))
	{
		$panther = $result2[2]. "_panther";
		$query1=mysqli_query($conn,"SELECT * FROM `$panther` WHERE gene = '$result[0]'");
		$result1=mysqli_fetch_row($query1);
		/* 这个基因不在 _panther 表里时 $result1 是 null，改前的 $result1[2] 会先
		   报一条 PHP 警告、再印出 "-"。 */
		$__a = ($result1 && isset($result1[2])) ? trim((string)$result1[2]) : '';
		if($__a == ""){
			$anno = "-";
		} else {
			$anno = $__a;
		}
		$loc=$result[2].":".$result[3]."...".$result[4];
		/* 同一行里物种名原来有两种写法：Species 列链接传的是 abbr1 短码
		   （speciesinfo.php?species=NVECT），Gene 列链接传的是地址栏里的原串。
		   两个目标页各自都能归一、都打得开，但一行里同一个物种写两样没有必要，
		   统一用归一后的拉丁学名，并给两个 target='_blank' 补上 rel='noopener'。 */
		$spUrl  = urlencode($species);
		$geneH  = htmlspecialchars($result[0], ENT_QUOTES, 'UTF-8');
		$geneU  = urlencode($result[0]);
		$annoH  = htmlspecialchars($anno, ENT_QUOTES, 'UTF-8');
		$chrH   = htmlspecialchars((string)$result[2], ENT_QUOTES, 'UTF-8');
		$jkU    = rawurlencode($result2[2]);
		$locU   = rawurlencode($loc);
		echo "<tr align='center'><td><a href=\"speciesinfo.php?species=$spUrl\">"
		   . htmlspecialchars($species, ENT_QUOTES, 'UTF-8')
		   . "</a></td><td><a href=\"./gene_detail.php?gene=$geneU&species=$spUrl\">$geneH</a></td><td>$annoH</td><td>$chrH</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td><a href='./jbrowse/index.html?data=$jkU&gene=$geneU&loc=$locU'>JBrowse</a></td></tr>";
	}
	echo "</table><br>";
	} else{
	echo "<script>alert(\"No transcript overlaps that interval. Please check the coordinates and try again.\");</script>";
	echo "<script>window.location =\"search.php\";</script>";
	echo "<div  style=\"height: 450px; \"></div>";
  } 
?>
</div>
</div>
</div>

<?php
	include "Webpage_components.php";
	print $footer;
?>
</body>
</html>
