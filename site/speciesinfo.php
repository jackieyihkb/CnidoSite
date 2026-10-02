<?php
/* 覆盖矩阵的数据层与物种解析函数：必须在本文件顶部就引入。
   cnido_spcov_resolve() 用来把 ?species= 归一成规范 abbr1（见正文里的说明），
   cnido_coverage() 是页末卡片网格的数据来源。 */
require_once __DIR__ . '/includes/coverage.php';
require_once __DIR__ . '/includes/species_coverage_panel.php';
/* 页末「Data available for this species」那张卡片网格，与 species_portal.php 共用
   同一份实现（那一页仍是全站物种总览，两处必须给出同一份清单）。 */
require_once __DIR__ . '/includes/species_cards.php';

// ========== 安全改进①：输入校验 ==========
/* 过滤后的串用于拼 SQL 与输出；另留一份原始值专供物种解析 —— 上面的过滤会把
   空格剥掉，而拉丁学名（"Nematostella vectensis"）靠空格分词，用过滤后的串
   永远认不出来（详见 includes/species_coverage_panel.php 的 cnido_spcov_resolve）。 */
$species_raw = '';
$species = '';
if (isset($_GET['species'])) {
    $species_raw = trim((string)$_GET['species']);
} elseif (isset($_POST['species'])) {
    $species_raw = trim((string)$_POST['species']);
}
$species = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $species_raw);
if ($species === '') {
    $species = 'default'; // 或你设定的默认物种缩写
}

// 安全输出函数
function safe($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
/** 只有纯整数字符串才加千分位，其余（'-'、空串、'12.5'）一律原样返回。
 *  speciesinfo 的统计列大量是 '-'，而 cnido_num() 会把非数字一律变成 0。 */
function cnido_si_num($v) {
    $v = trim((string)$v);
    return preg_match('/^[0-9]+$/', $v) ? number_format((float)$v) : $v;
}
/** BUSCO 串在 speciesinfo 里 295/296 行写成 "C:96.7%[S:94.4%,D:2.3%],F:1.4%,M:1.9%"
 *  —— 逗号后没有空格，整串挤成一团。这里只插空格，不动任何数字。
 *  （与 species_portal.php 的 sp_busco() 分工不同：那个要把串解析成结构。） */
function cnido_si_busco($v) {
    return preg_replace('/,(?=\S)/', ', ', (string)$v);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta charset="utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Species Information - CnidoSite</title>
<meta name="description" content="Genome assembly, annotation, BUSCO completeness and the data types available for one cnidarian species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<style type="text/css">
<?php
/* 只印卡片网格的样式（includes/species_cards.php）。本页原先还印过
   cnido_spcov_css()，那是「Data Coverage & Visualisation」那张表的样式，
   该表已从本页移除（清单改由页末卡片呈现），页面上再没有 .spc- 元素。 */
echo cnido_spcards_css();
?>
<?php /* 物种简介那一段（图片 + 文字并排）。桌面端（>700px）与改动前逐像素一致
   —— 原来这几个属性是写死在行内的，行内样式压过样式表，没法只加一条媒体查询
   就收窄，所以搬到类上。

   窄屏必须竖排：图上就占了 250px，390px 屏减掉左右留白只剩 348px，留给文字
   88px，而段落的 min-content（最长的那个词或 URL）有 157px —— flex 项缩不到
   min-content 以下，整行就把页面撑出 53px（实测 docScroll 443 > vw 390，
   手机上是整页可以左右晃）。 */ ?>
.si-intro { display: flex; align-items: center; }
.si-intro-fig { margin-right: 10px; }
@media (max-width: 700px) {
    .si-intro { display: block; }
    .si-intro-fig { margin-right: 0; margin-bottom: 8px; text-align: center; }
}
</style>
<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery-1.10.2.min.js"></script>
<script src="js/highcharts.js"></script>
<script src="js/highcharts-more.js"></script>
<script src="js/exporting.js"></script>
<script src="js/sign.js" language="javascript"></script>
<script src="js/tooltip.js" type="text/javascript"></script>
<script src="js/highcharts-detail.js"></script>
<script src="js/modernizr.main.js"></script>
</head>
<body>
<script type="text/javascript">
function option_showhide(id,img){
	var thisImg = document.getElementById(img);
	if(document.getElementById){
		<?php /* 页面上没有 id=img 的图标时 thisImg 为 null，原来直接 .src= 会抛
		   TypeError（控制台报错，展开/收起虽然仍然生效但看着像坏了）。加个判空。 */ ?>
		if((document.getElementById(id).style.display == "block")){
			document.getElementById(id).style.display = 'none';
			if(thisImg){ thisImg.src="images/plus.png"; }
		}else{
			document.getElementById(id).style.display = 'block';
			if(thisImg){ thisImg.src="images/minus.png"; }
		}
	}else{
		if(document.layers){
			document.id.display = 
				(document.id.display == "block") ? 'none' : 'block';
		}else{
			document.all.id.style.display =
				(document.all.id.style.display == "block") ? 'none' : 'block';
		}
	}
}
function showhidediv(id){
  var sbtitle=document.getElementById(id);
  if(sbtitle){
     if(sbtitle.style.display=='block'){
     sbtitle.style.display='none';
     }else{
    sbtitle.style.display='block';
     }
  }
}
</script>
<script type="text/javascript">
    $(function(){
        $('#export').click(function(){
            var excelContent = $('#tablelist').html();
            $('input[name=excelContent]').val(excelContent);
            $('#excelfromtable').submit();
        })
    })
</script>

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

<?php
// ========== 安全改进②：SQL 全部改预处理 ==========
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { error_log('CnidoSite: database connection failed'); die('The database is temporarily unavailable. Please try again in a moment.'); }

/* 下方 Data coverage 一节要的是覆盖矩阵里该物种的那一行，数据层与
   coverage_matrix.php / species_portal.php 共用同一份 1 小时缓存
   （tmp/coverage_cache.json，62 KB），因此三处的数字永远一致。
   顺带用它把 ?species= 归一成规范 abbr1：本页原来只按字面量匹配
   speciesinfo.abbr，传拉丁名或下划线名会得到一个所有表格都空白的页面。
   归一的结果一定取自 $cov['species'] 的键，不会把用户输入原样带下去。 */
$cov  = cnido_coverage($conn);
$spInfo = array('species' => '', 'abbr' => '', 'class' => '');
if ($species !== 'default') {
    $__res = cnido_spcov_resolve($cov, $species_raw);
    if ($__res !== null) { $species = $__res['abbr1']; $spInfo = $__res['info']; }
    unset($__res);
}

// 查询1: speciesinfo
$stmt = $conn->prepare("SELECT * FROM speciesinfo WHERE abbr = ?");
$stmt->bind_param("s", $species);
$stmt->execute();
$result = $stmt->get_result()->fetch_row();

// 查询2: description
$stmt1 = $conn->prepare("SELECT * FROM description WHERE abbr = ?");
$stmt1->bind_param("s", $species);
$stmt1->execute();
$result1 = $stmt1->get_result()->fetch_row();

/* 查不到物种时（裸访问、空参数、或拼错的名字）下面每个区块都印成空壳：
   About 的标题里没有名字、三张表全是空单元格，页面上没有一句话说明为什么。
   实测 /speciesinfo.php 与 ?species=Nematostella+vectinensis（拼错一个字母）
   都走到这里，读者看到的是一个「坏了」的页面。改为整体换成一条说明 + 去处，
   空表不再输出。$__spName 后面的 References 一节也用它。 */
$__spName   = ($result !== null) ? trim((string)($result[1] ?? '')) : '';
$__notfound = ($__spName === '');
?>

<?php
/* 审稿意见 Referee 2 major 1：本页原来只讲基因组汇编，从 Taxonomy 表点进来
 * 的人看不到该物种还有没有转录组 / 单细胞 / 蛋白组等数据，也不知道去哪找。
 * 现在这段只负责**指路**：该物种的数据清单就在本页页末（Data available for
 * this species，见 includes/species_cards.php），不再让读者为这件事跳走。 */
if ($__notfound) {
    echo '<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;Species Information</legend>';
    echo '<div class="gd-notice gd-info" style="margin-bottom:14px">'
       . '<b>No species matched.</b> '
       . ($species_raw !== ''
            ? 'CnidoSite has no record for <code>' . safe($species_raw) . '</code>. '
            : 'This page shows the assembly record of one species, and no species was given. ')
       . 'Both the scientific name and the abbreviation work &mdash; pick a species from the '
       . '<a href="./browse.php?class=all">taxonomy list</a> or the '
       . '<a href="./genomeinfo.php">species overview</a>, whose search box matches names, '
       . 'abbreviations, class and taxonomy ID.'
       . '</div>';
} else {
    /* 这句原本写死「325 species」，新增物种后就成了假话。物种数以 abbr 表为准
       （覆盖矩阵也是按它逐行展开的），每次现查，不再写死。 */
    $__nSpecies = 0;
    if ($__r = mysqli_query($conn, "SELECT COUNT(*) FROM abbr")) {
        $__nSpecies = (int)mysqli_fetch_row($__r)[0];
    }

    echo '<div class="gd-notice gd-info" style="margin-bottom:14px">'
       . 'This page covers the <b>genome assembly</b> of <i>' . safe($result[1] ?? '') . '</i>. '
       . 'Everything else this species has '
       . '(transcriptome, single-cell, proteome, epigenome, metagenome, phenotype, '
       . 'palaeobiology, gene families, JBrowse&hellip;) is listed in '
       . '<b>Data available for this species</b> at the bottom of this page, with a direct '
       . 'link to each one. The '
       . '<a href="./species_portal.php?species=' . urlencode($species) . '">Species Portal</a> '
       . 'shows the full assembly record and this species&rsquo; relatives, and the '
       . '<a href="./coverage_matrix.php?q=' . urlencode($species) . '">coverage matrix</a> '
       . 'compares it against all <b>' . (int)$__nSpecies . '</b> species.'
       . '</div>';
}
?>

<!-------------------------------Data coverage & visualisation------------------------------------------>
<?php
/* 这一节原先在这里（覆盖矩阵那行摊平成一张表 + 每个模块的深链）。它讲的其实
 * 是「该物种有哪些数据」，与页末的卡片网格是同一件事的两种画法，同一页出现两次
 * 会让人以为其中一个是别的物种的。整块已移除，清单统一由页末卡片呈现
 * （includes/species_cards.php，与 species_portal.php 同源）。 */
?>
<?php if (!$__notfound): /* 查不到物种时整段不印：这里每一个区块都要读 speciesinfo 那一行，没有它就是空表 */ ?>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;About <b><i><?php echo safe($result[1] ?? '');?></i></b></legend>
<?php
$species_name = $result[1] ?? '';
$intro_text = $result1[3] ?? '';

// 安全替换：只在纯文本层面做 str_replace，输出时整体转义
$formatted_intro = str_replace(
    safe($species_name),
    '<b><i>' . safe($species_name) . '</i></b>',
    safe($intro_text)
);

// 图片文件名安全校验：只允许字母数字下划线点号横线
$img_file = '';
if (!empty($result1[2]) && $result1[2] != '-') {
    $img_file = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $result1[2]);
}

if ($img_file !== '' && $img_file !== '-'){
    echo "<div class=\"si-intro\"><div class=\"si-intro-fig\"><img src='./images/images/" . safe($img_file) . "' width='250px' style='margin-bottom:-10px' onerror=\"this.src='./images/images/default.png'\"></div><p style='text-align: justify;' class=\"paleo-intro\">$formatted_intro</p></div>";
}else{
    echo "<p style='text-align: justify;' class=\"paleo-intro\">$formatted_intro</p>";
}

/* 有两对物种是同一生物的两个名字（见 includes/species_cards.php 里的
   cnido_species_synonym()）。这两对共享同一个 NCBI Taxonomy ID，下面「Basic
   Information」里的 NCBI Taxonomy ID 一行会把它们指到同一条记录；不说清楚就像登重了。 */
$__syn = cnido_species_synonym($species);
if ($__syn !== '') {
    echo '<div class="gd-notice gd-info" style="margin-bottom:14px">' . $__syn . '</div>';
}

// ========== Basic Information 表格 ==========
echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Basic Information</legend>";
echo "<table class=\"gridtable\">";
echo "<tr align='center'><th>Class</th><th width='12%'>Species</th><th width='9%'>NCBI Taxonomy ID</th><th>Assembly Level</th><th>GenBank Assembly</th><th>Assembly Name</th><th>Genome Size (Mb)</th><th>WGS Accession</th><th width='15%'>Submitter</th><th>BioProject</th></tr>";

// NCBI 链接：确保 ID 不为空才输出完整链接，避免空请求（消除 user_doc 3~6 的报错）
$ncbi_tax_id = $result[3] ?? '';
$datasets_id = $result[5] ?? '';
$nuccore_id  = $result[9] ?? '';
$bioproject_id = $result[11] ?? '';

echo "<tr align='center'>";
echo "<td>" . safe($result[0] ?? '') . "</td>";
echo "<td><b><i>" . safe($result[1] ?? '') . "</i></b></td>";
// NCBI Taxonomy
if ($ncbi_tax_id !== '' && $ncbi_tax_id !== '-') {
    echo "<td><a href='https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=" . safe(urlencode($ncbi_tax_id)) . "' target='_blank'>" . safe($ncbi_tax_id) . "</a></td>";
} else {
    echo "<td>-</td>";
}
echo "<td>" . safe($result[4] ?? '') . "</td>";
// GenBank Assembly (datasets)
if ($datasets_id !== '' && $datasets_id !== '-') {
    echo "<td><a href='https://www.ncbi.nlm.nih.gov/datasets/genome/" . safe(urlencode($datasets_id)) . "' target='_blank'>" . safe($datasets_id) . "</a></td>";
} else {
    echo "<td>-</td>";
}
echo "<td>" . safe($result[6] ?? '') . "</td>";
echo "<td>" . safe($result[7] ?? '') . "</td>";
// WGS/Nuccore
if ($nuccore_id !== '' && $nuccore_id !== '-') {
    echo "<td><a href='https://www.ncbi.nlm.nih.gov/nuccore/" . safe(urlencode($nuccore_id)) . "' target='_blank'>" . safe($nuccore_id) . "</a></td>";
} else {
    echo "<td>-</td>";
}
echo "<td>" . safe($result[10] ?? '') . "</td>";
// BioProject
if ($bioproject_id !== '' && $bioproject_id !== '-') {
    echo "<td><a href='https://www.ncbi.nlm.nih.gov/bioproject/" . safe(urlencode($bioproject_id)) . "' target='_blank'>" . safe($bioproject_id) . "</a></td>";
} else {
    echo "<td>-</td>";
}
echo "</tr>";
echo "</table><div class=\"clr\"></div>";
?>

<!-------------------------------Genome Assembly Information----------------------------------------------------------------->
<?php
echo "<table><tr><td><BR></td></tr></table>";
echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Genome Assembly Information</legend>";
echo "<table class=\"gridtable\">";
echo "<tr align='center' style='word-break: break-word'><th>Chromosome Number</th><th>Scaffold Number</th><th>Contig Number</th><th>Protein Number</th><th>Protein-Coding Gene Number</th><th>Contig N50 (bp)</th><th>Scaffold N50 (bp)</th><th>GC content (%)</th><th width='20%' title='C = complete (single-copy + duplicated), F = fragmented, M = missing, each as a share of the lineage BUSCOs. A few species carry only the complete percentage.'>BUSCO completeness</th></tr>";
echo "<tr align='center'>";
/* 下标 16-22 是纯计数/长度列，加千分位；23 是 GC（小数）、24 是 BUSCO 串，
   都不走 cnido_si_num()。 */
echo "<td>" . safe(cnido_si_num($result[16] ?? '')) . "</td>";
echo "<td>" . safe(cnido_si_num($result[17] ?? '')) . "</td>";
echo "<td>" . safe(cnido_si_num($result[18] ?? '')) . "</td>";
echo "<td>" . safe(cnido_si_num($result[19] ?? '')) . "</td>";
echo "<td>" . safe(cnido_si_num($result[20] ?? '')) . "</td>";
echo "<td>" . safe(cnido_si_num($result[21] ?? '')) . "</td>";
echo "<td>" . safe(cnido_si_num($result[22] ?? '')) . "</td>";
echo "<td>" . safe($result[23] ?? '') . "</td>";
echo "<td>" . safe(cnido_si_busco($result[24] ?? '')) . "</td>";
echo "</tr>";
echo "</table><div class=\"clr\"></div>";
?>

<!-------------------------------References----------------------------------------------------------------->
<?php
if(($result[12] ?? '-') != "-"){
echo "<br><legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;References</legend>";
echo "<table class=\"gridtable\">";
echo "<tr align='center'><th width='60%'>Title</th><th>Journal</th><th width='10%'>Pubmed ID</th></tr>";

// ========== 安全改进③：第三个查询也用预处理 ==========
$stmt_ref = $conn->prepare("SELECT * FROM speciesinfo WHERE abbr = ?");
$stmt_ref->bind_param("s", $species);
$stmt_ref->execute();
$ref_result = $stmt_ref->get_result()->fetch_row();

echo "<tr align='center'>";
echo "<td>" . safe($ref_result[12] ?? '') . "</td>";
echo "<td>" . safe($ref_result[13] ?? '') . "</td>";
// Pubmed 链接
$pubmed_link = $ref_result[15] ?? '';
$pubmed_id   = trim((string)($ref_result[14] ?? ''));
/* Pubmed_ID 这一列在库里是混装的：多数是真 PMID（纯数字），18 行存的是字符串
   "Link"（文章没进 PubMed，只有 paper_URL），1 行存 "-"。原样印出来，读者会在
   「Pubmed ID」表头下读到 "Link" 或 "-"。所以只有值长得像 PMID 时才印它，否则印
   "paper"（与 sn_data.php 同一写法）；没有 URL 时保持原值不动。 */
if ($pubmed_link !== '' && $pubmed_link !== '-') {
    $__pmLabel = preg_match('/^\d+$/', $pubmed_id) ? $pubmed_id : 'paper';
    echo "<td><a href='" . safe($pubmed_link) . "' target='_blank'>" . safe($__pmLabel) . "</a></td>";
} else {
    echo "<td>" . safe($pubmed_id) . "</td>";
}
echo "</tr>";
echo "</table><div class=\"clr\"></div>";
}
?>
<?php endif; /* !$__notfound：About / Basic Information / Genome Assembly / References 到此为止 */ ?>

<!-------------------------------Data available for this species------------------------------------------>
<?php
/* 页面的落点：About → Basic Information → Genome Assembly Information → References，
 * 最后是「这个物种到底有哪些数据」的卡片网格。这一块与 species_portal.php 印的是
 * 同一份东西（同一个 include、同一份 cnido_coverage() 缓存），加模块时两页一起变。 */
if ($spInfo['species'] !== '') {
    /* 前置 <br> 空一行，与上面 References 那块一致（它也是靠 <br> 跟上一张表隔开的）。 */
    echo '<br><legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;Data available for this species</legend>';
    echo cnido_spcards_html($cov, $species, $spInfo, 'speciesinfo.php');
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