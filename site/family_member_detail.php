<?php
/* 入参兜底 + 物种标识归一。
   原写法直接读 $_POST[...]，只传 GET 时会报 Undefined index。
   另外 $species 传进来的是下划线形式（Nematostella_vectensis），而 abbr 表的
   species 列存的是带空格的学名，原来的精确匹配查不到，标题就渲染成
   "Ubiquitin Family in "（物种名空白），下游查 ubs/tf 表用的也是空名字。
   改为先经 cnido_latin_of() 归一，再按学名取 abbr 行，$result2 的形状不变。 */
require_once __DIR__ . '/includes/state.php';
$species  = isset($_GET['species'])     ? $_GET['species']     : (isset($_POST['species'])     ? $_POST['species']     : '');
$family   = isset($_GET['gene_family']) ? $_GET['gene_family'] : (isset($_POST['gene_family']) ? $_POST['gene_family'] : '');
$familyid = isset($_GET['family_id'])   ? $_GET['family_id']   : (isset($_POST['family_id'])   ? $_POST['family_id']   : '');
$species  = cnido_latin_of($species);
/* $familyid 全部来自地址栏，改前有两处直接把它当可信内容用：
   下面两个分支的 SQL 里是 `WHERE familyid='$familyid'`（未转义，一个引号即可改写
   查询），两个 <h3> 的面包屑里也是原样输出（反射型 XSS）。这里一次备好显示用的
   转义串；SQL 那边因为 $conn 要到各自分支里才建立，就地用 mysqli_real_escape_string
   转义。取值本身不变，正常家族名（UBC、RING…）渲染结果与改前逐字相同。 */
$familyidH = htmlspecialchars($familyid, ENT_QUOTES, 'UTF-8');

/* 这一页的 JBrowse 链接原来用一张写死的白名单挡着（AALAT / CXAMA / CCRUX 三个
   物种码），名单漏掉了 Taxipathes sp. SY275-mao —— 它没有短物种码，abbr.abbr1
   存的是长 slug Taxipathes_sp_SY275_mao，不在名单里，于是这一页给它发出一张
   ./jbrowse/Taxipathes_sp_SY275_mao/ 的链接，而那个目录不存在（404，JBrowse 页
   整页打不开）。名单法迟早还会漏，改成直接问文件系统：目录在就给链接。
   结果缓存一次，免得一行一次 stat。 */
function fmd_has_jbrowse($abbr)
{
    static $cache = array();
    $abbr = (string)$abbr;
    if ($abbr === '' || strpbrk($abbr, '/\\') !== false) { return false; }
    if (!isset($cache[$abbr])) { $cache[$abbr] = is_dir(__DIR__ . '/jbrowse/' . $abbr); }
    return $cache[$abbr];
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
<title>Family Members - CnidoSite</title>
<meta name="description" content="Members of one gene family in a cnidarian species, with gene identifiers and annotation links" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
</head>
<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<?php /* 导航栏保持不变 */ ?>
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

<?php
	$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	$query=mysqli_query($conn,"SELECT * FROM abbr WHERE species = '$species'");
	$result2=mysqli_fetch_row($query);
	$n=0;
/* abbr 里查不到这个物种时，下面每一处 $result2[...] 都会退化成空串，
   页面会印出「Ubiquitin Family in 」这样的半句话、表头也没了主词。宁可明说。 */
if(!$result2)
{
	echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Gene Family</legend>";
	echo "<p  class=\"paleo-intro\">No species called <b>" . htmlspecialchars($species) . "</b> is in the catalogue, so there is no family list to show. Pick a species from the list on the <a href=\"/gene_family.php\">Gene Family</a> page.</p>";
}
if($result2 && $family == "ubs" )
{
	echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Ubiquitin Family in <b><i>$result2[0]</i></b></legend>";
	echo "<p  class=\"paleo-intro\">Ubiquitin families were mainly identified through HMM searches of data from iUUCD.
	The <i>Family</i> column is the top-level class (E1, E2, E3, DUB, UBD, ULD) and <i>Subfamily</i> is the group
	inside it; the family ID you selected is the most specific of the three, and one ID can occur in more than one
	class &mdash; <i>UBC</i>, for example, is listed both as <i>E2 / UBC</i> and as <i>UBD / UBC-like</i>, and both
	are shown here.</p>";
	echo "<h3>Gene Family &rsaquo; Ubiquitin family &rsaquo; $familyidH</h3><div class=\"clr\"></div>";
	echo "<table  class=\"gridtable\">";
	/* 最后一列的数据是 ubs.source（第 10 列），全表 306,859 行**只有一个取值**
	   `iUUCD`，链接指向 iUUCD 自己的站点。列头原来写作 "Method"，但这一列里没有
	   任何方法名，只有来源库的名字；改成 "Source" 与 domain_search.php 等页同口径。
	   （识别方法那句「identified through HMM searches of data from iUUCD」已经在
	   上面的导语里说了，这里不再重复。） */
	echo "<tr align='center'><th width='15%'>Gene</th><th>Family</th><th width='10%'>Subfamily</th><th>E-value</th><th>Score</th><th width='30%'>Description</th><th>Source</th><th>JBrowse</th></tr>";
	$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	$query=mysqli_query($conn,"SELECT * from ubs WHERE familyid='" . mysqli_real_escape_string($conn, $familyid) . "' and species = '" . mysqli_real_escape_string($conn, $result2[0]) . "'");
	while($result=mysqli_fetch_row($query))
	{
		$tab=$result2[2]."_locus";
		$query3=mysqli_query($conn,"SELECT * FROM $tab WHERE mRNA = '$result[2]' || gene = '$result[2]'");
		$result3=mysqli_fetch_row($query3);
		$n+=1;
		$ipr=$result2[2]."_ipr";
		$query4=mysqli_query($conn,"SELECT * FROM $ipr WHERE gene = '$result[2]'");
		$result4=mysqli_fetch_row($query4);
		if(!$result[0])
		{
		/* 8 个表头列就配 8 个 td —— 原来这里有 9 个，等多加载一行 species 为空的行时会把整张表撑歪 */
		echo "<tr align='center'><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr>";}
		else{
		/* Description 列取的是 <ABBR>_ipr 的第 4 列（Description）。原来写 $result4[2]，
		   那是第 3 列 Type，取值只有 Domain/Family/Repeat 这类受控词，于是整列印的是
		   「类型」而不是描述（已验证：XP_001634403.1 应为 Autophagy protein Atg8
		   ubiquitin-like，实印 Family）。tf 分支的 $result4 来自 <ABBR>_nr，第 3 列
		   本来就是 description，不受影响。 */
		echo "<tr align='center'><td><a href=\"gene_detail.php?gene=$result[2]&species=$species\">$result[2]</a></td><td>$result[3]</td><td>$result[4]</td><td>$result[6]</td><td>$result[7]</td><td>$result4[3]</td><td><a href=\"https://iuucd.biocuckoo.org/index.php\"  target='_blank'>$result[9]</a></td>";
		if(!fmd_has_jbrowse($result2[2])){
			/* 没有 JBrowse 目录的物种：原来印一个不可点的 "JBrowse" 文字，
			   看着像链接坏了。按站内约定印「无值」的破折号并说明原因。 */
			echo "<td><span class=\"mx-na\" title=\"no genome browser track for this species\">&mdash;</span></td></tr>";
		}else{
			echo "<td><a href='./jbrowse/index.html?data=$result2[2]&gene=$result[2]'>JBrowse</a></td></tr>";
		}
		}
	}
	if($n == 0)
	{
		$has = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM ubs WHERE species = '" . mysqli_real_escape_string($conn, $result2[0]) . "'"));
		if($has && $has[0] > 0)
		{
			echo "<tr align='center'><td colspan='8'>No members of the <b>" . htmlspecialchars($familyid) . "</b> family are listed for <b><i>" . htmlspecialchars($result2[0]) . "</i></b>. Family identifiers are case-sensitive — check the name on the <a href=\"/gene_family.php\">Gene Family</a> page.</td></tr>";
		}
		else
		{
			echo "<tr align='center'><td colspan='8'><b><i>" . htmlspecialchars($result2[0]) . "</i></b> has no ubiquitin-family assignments in the database, so no family can be listed for it. Choose another family system or another species on the <a href=\"/gene_family.php\">Gene Family</a> page.</td></tr>";
		}
	}
	$height=$n*10;
	echo "</table><div class=\"clr\"></div>";
}
if($result2 && $family == "tf" )
{
	echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Transcription Factors Family in <b><i>$result2[0]</i></b></legend>";
	/* 原先这句把 `<?php echo htmlspecialchars($species); ?>` **写在双引号字符串里**，
	   PHP 只做变量插值、原样输出这段标签，浏览器再把它当 bogus comment 吞掉 ——
	   于是物种名消失，句子印成 "Transcription factors (TFs) of  are usually
	   classified..."。这里改用拼接，与 family_member.php 的同款句子逐字对齐。 */
	echo "<p  class=\"paleo-intro\">Transcription factors (TFs) of <strong>" . htmlspecialchars($species) . "</strong> are classified into families according to their conserved DBDs, following <a href=\"https://guolab.wchscu.cn/AnimalTFDB4//#/\" target=\"_blank\" rel=\"noopener noreferrer\">AnimalTFDB 4.0</a> and self-built HMM profiles.</p>";
	echo "<h3>Gene Family &rsaquo; TF family &rsaquo; $familyidH</h3><div class=\"clr\"></div>";
	echo "<table class=\"gridtable\">";
	echo "<tr align='center'><th width='12%'>Gene</th><th>Pfam/Self-build</th><th>DNA Binding Domain</th><th width='25%'>Full name</th><th width='30%'>Description</th><th>JBrowse</th></tr>";
	$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	$query=mysqli_query($conn,"SELECT * from tf WHERE Family='" . mysqli_real_escape_string($conn, $familyid) . "' and species ='" . mysqli_real_escape_string($conn, $result2[0]) . "'");
	$n=0;
	while($result=mysqli_fetch_row($query))
	{
		$tab=$result2[2]."_locus";
		$query3=mysqli_query($conn,"SELECT * FROM $tab WHERE mRNA = '$result[2]' || gene = '$result[2]'");
		$result3=mysqli_fetch_row($query3);
		$n+=1;
		$ipr=$result2[2]."_nr";
		$query4=mysqli_query($conn,"SELECT * FROM $ipr WHERE gene = '$result[2]'");
		$result4=mysqli_fetch_row($query4);
	if(preg_match('/\QPF\E/', $result[3])) {
		echo "<tr align='center'><td><a href=\"gene_detail.php?gene=$result[2]&species=$species\">$result[2]</a></td><td><a href=\"https://www.ebi.ac.uk/interpro/entry/pfam/$result[3]\">$result[3]</a></td><td>$result[5]</td><td>$result[6]</td><td>$result4[2]</td>";
		if(!fmd_has_jbrowse($result2[2])){
			/* 没有 JBrowse 目录的物种：原来印一个不可点的 "JBrowse" 文字，
			   看着像链接坏了。按站内约定印「无值」的破折号并说明原因。 */
			echo "<td><span class=\"mx-na\" title=\"no genome browser track for this species\">&mdash;</span></td></tr>";
		}else{
			/* 这里原来带 `&loc=$loc`，而 $loc 在本页从来没有被赋值过（tf 分支虽然
			   SELECT 了 *_locus 那一行到 $result3，却从没用它拼过位置）—— 拼出来就是
			   一个空的 `&loc=`。同一个文件里 tf 的另一条分支（非 PF 那支）和 ubs 分支
			   都不带 loc，JBrowse 照常打开；去掉这个空参数，三条分支写法一致。 */
			echo "<td><a href='./jbrowse/index.html?data=$result2[2]&gene=$result[2]'>JBrowse</a></td></tr>";
		}
	}else{
		echo "<tr align='center'><td><a href=\"gene_detail.php?gene=$result[2]&species=$species\">$result[2]</a></td><td>$result[3]</td><td>$result[5]</td><td>$result[6]</td><td>$result4[2]</td>";
		if(!fmd_has_jbrowse($result2[2])){
			/* 没有 JBrowse 目录的物种：原来印一个不可点的 "JBrowse" 文字，
			   看着像链接坏了。按站内约定印「无值」的破折号并说明原因。 */
			echo "<td><span class=\"mx-na\" title=\"no genome browser track for this species\">&mdash;</span></td></tr>";
		}else{
			echo "<td><a href='./jbrowse/index.html?data=$result2[2]&gene=$result[2]'>JBrowse</a></td></tr>";
		}
	}
	}
	if($n == 0)
	{
		$has = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM tf WHERE species = '" . mysqli_real_escape_string($conn, $result2[0]) . "'"));
		if($has && $has[0] > 0)
		{
			echo "<tr align='center'><td colspan='6'>No transcription factors of the <b>" . htmlspecialchars($familyid) . "</b> family are listed for <b><i>" . htmlspecialchars($result2[0]) . "</i></b>. Family identifiers are case-sensitive — check the name on the <a href=\"/gene_family.php\">Gene Family</a> page.</td></tr>";
		}
		else
		{
			echo "<tr align='center'><td colspan='6'><b><i>" . htmlspecialchars($result2[0]) . "</i></b> has no transcription-factor assignments in the database, so no family can be listed for it. Choose another family system or another species on the <a href=\"/gene_family.php\">Gene Family</a> page.</td></tr>";
		}
	}
	echo "</table>";
}
?>
<?php /* 页头只开了三层内容容器（#tempatemo_content_wrapper / #templatemo_content
   / #column），这里原来关五层，多出来的两个 </div> 落在 body 层被浏览器丢掉 ——
   页脚嵌得没错，但文件本身是坏的。顺带：最后一行原来写的是 `</head>` 而不是
   `</html>`（`<head>` 早在第 31 行就关掉了），等于每份响应都以一个多余的 </head>
   收尾。两处都按页头实际的层数改回来。 */ ?>
</div>
</div>
</div>


<?php
	include "Webpage_components.php";
	print $footer;
?>
</body>
</html>
