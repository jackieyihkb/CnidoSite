<?php
/* 审稿意见 Referee 2 major 1：
 * 本页此前完全不接受物种参数，species_portal.php / 分类模块的 ?species= 深链
 * 点进来永远看到全部 46 条蛋白组数据集。现用 cnido_state() 解析物种：
 * GET（深链）> POST（表单）> 本模块会话 > 默认值（全部物种）。
 *
 * 值域：proteome_data.species 存的是拉丁名（如 Exaiptasia diaphana），
 * 而 includes/modlinks.php 给本页拼的深链用的是 abbr1 短码
 * （proteomic_data.php?species=NVECT），所以两种写法都要认 —— 先按原样匹配，
 * 匹配不上再经 abbr 表把短码折成拉丁名。 */
require_once __DIR__ . '/includes/state.php';

$__st = cnido_state('proteomicdata', array(
    'specie' => array('get' => 'species', 'default' => ''),
));
$specie = $__st['specie'];

$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 候选项 = 本表里真实存在的物种（服务端渲染，不依赖 JS）
$__speciesList = array();
if ($__q = mysqli_query($conn, "SELECT DISTINCT species FROM proteome_data ORDER BY species")) {
    while ($__r = mysqli_fetch_row($__q)) {
        if ($__r[0] !== null && $__r[0] !== '') { $__speciesList[] = $__r[0]; }
    }
}

/* 入参统一解析成拉丁学名再比对：深链可能传短码（NVECT，modlinks 的 proteome 链接）、
 * 下划线形式（Nematostella_vectensis，旧页面之间传的就是它）或拉丁学名本身。
 * 原来只查 “abbr1 = 入参 OR species = 入参”，下划线形式两种都不是 —— 查不中，
 * 下拉框于是多出一个 “Nematostella_vectensis (unavailable)” 项，与列表里同一
 * 物种的学名重复，还把物种代码露给了读者。cnido_latin_of() 三种写法都认。 */
if ($specie !== '' && !in_array($specie, $__speciesList, true)) {
    $specie = cnido_latin_of($specie, $conn);
}

/* 「全部物种」也是合法状态，但 cnido_state() 会把空值当成「未提供」而回落到会话，
 * 所以用 species=all 作哨兵，供下拉框的 “All species” 真正清除过滤（并清会话）。 */
if (strcasecmp($specie, 'all') === 0) {
    $specie = '';
    unset($_SESSION['proteomicdata_specie']);
}

// 物种名先 mysqli_real_escape_string 再拼进 SQL，不直接使用请求数据
$__where_sql = '';
if ($specie !== '') {
    $__where_sql = " WHERE species = '" . mysqli_real_escape_string($conn, $specie) . "'";
}
$__query = mysqli_query($conn, "SELECT * FROM proteome_data" . $__where_sql);
$__rows  = array();
if ($__query) { while ($__r = mysqli_fetch_row($__query)) { $__rows[] = $__r; } }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Proteomics Data</title>
<meta name="keywords" content="" />
<meta name="description" content="" />
<link href="/./templatemo_style.css" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js" type="text/javascript"></script>
</head>

<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<!--导航栏保持不变-->
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
            <li><a href="#">Genome</a>
                <ul>
                    <li><a href="/genomeinfo.php">Genomic Data</a></li>
                    <li><a href="/search.php">Gene Search</a></li>
                    <li><a href="/busco.php">BUSCO Gene</a></li>
                    <li><a href="/TE.php">Transposable Elements</a></li>
                    <li><a href="/gene_family.php">TFs/Ubs</a></li>
                    <li><a href="/proteindomain.php">Protein Domain</a></li>
                    <li><a href="/domain_search.php">Functional Domain Search</a></li>
                    <li><a href="/go.php">Gene Ontology</a></li>
                    <li><a href="/interpro.php">InterPro</a></li>
                    <li><a href="/kegg.php">KEGG Pathway</a></li>
                    <li><a href="/genefamily.php">Gene Family</a></li>
                    <li><a href="/pan-geneset.php">Pan-geneset</a></li>
                    
                    
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li><li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li><li><a href="/mitdata.php">Mitogenomic Data</a></li>
                </ul>
            <li><a href="#">Transcriptome</a>
                <ul>
                    <li><a href="/cytoscape/trans_data.php">Transcriptomic Data</a></li>
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
            <li><a href="#" class="current">Proteome</a>
                <ul>
                    <li><a href="/proteomic_data.php">Proteomic Data</a></li>
                    <li><a href="/proteomic_analysis.php">Proteomic Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Epigenome</a>
                <ul>
                    <li><a href="/epigenomic_data.php">Epigenomic Data</a></li>
                    <li><a href="/DNA_methylation.php">DNA Methylation</a></li>
                    <li><a href="/miRNA_analysis.php">MiRNA-seq Analysis</a></li>
                    <li><a href="/ATAC_analysis.php">ATAC-seq Analysis</a></li>
                    <li><a href="/DHS_analysis.php">DNase-seq Analysis</a></li>
                    <li><a href="/ChIP_analysis.php">ChIP-seq Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Metagenome</a>
                <ul>
                    <li><a href="/metagenomic_data.php">Metagenomics Data</a></li>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Proteomics Data</b></legend>
<p class="paleo-intro">In this module, a brief summary of 46 proteomic datasets across 7 classes and 28 species are listed as follows, including the information of tissue, treatment, data source and reference.</p>

<?php /* 交叉链接，不是重定向。本页是数据集目录（词汇表是 proteome_data.species，即拉丁
   名，进来时用 cnido_latin_of() 归一）；重处理页面用的是 proteomic_datasets.species。
   两个词汇表在这里足够接近，但仍然不把本页变量直接透传出去 —— 只有一条按表前缀索引的
   已验证映射（见 includes/proteomic_crosslinks.php），传拉丁名进去只会静默地不出链接。
   本页因此只给通用入口，逐物种链接放在 proteomic_analysis.php，那里才有表前缀。 */
require_once __DIR__ . '/includes/proteomic_crosslinks.php';
echo cnido_proteomic_note_original(''); ?>

<!-- 审稿意见 Referee 2 major 1：物种深链进来时，页面必须显示当前收窄到了哪个物种，
     并给一个能改回「全部物种」的入口。GET 提交以保证 URL 可分享/可深链。 -->
<form method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="margin:0 0 16px;">
    <label for="speciesSelect"><b>Species</b></label>
    <select name="species" id="speciesSelect" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;margin-left:8px;font-size:14px;">
        <option value="all">All species</option>
        <?= cnido_options($__speciesList, $specie) ?>
    </select>
    <input type="submit" value="View" style="padding:6px 14px;margin-left:6px;border:none;border-radius:6px;background:#3b82f6;color:#fff;font-weight:600;cursor:pointer;" />
</form>

<?php
if ($specie !== '' && count($__rows) === 0) {
    /* 该物种没有蛋白组数据集（例如短码存在但库里没有对应条目）：
     * 给一句明确的说明，而不是一张只有表头的空表。 */
    echo '<p class="paleo-intro" style="border-left:4px solid #2563eb;background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);">'
       . 'No proteomic datasets are available for <b><i>' . htmlspecialchars($specie) . '</i></b>. '
       . '<a href="./proteomic_data.php?species=all">Show all species</a>, or open the '
       . '<a href="species_portal.php?species=' . urlencode($specie) . '">species portal</a> '
       . 'to see the resources that <i>' . htmlspecialchars($specie) . '</i> does have.</p>';
} else {
    if ($specie !== '') {
        echo '<p class="paleo-intro" style="border-left:4px solid #2563eb;background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);">'
           . 'Showing proteomic datasets for <b><i>' . htmlspecialchars($specie) . '</i></b> only &mdash; '
           . count($__rows) . ' dataset(s). '
           . '<a href="./proteomic_data.php?species=all">Show all species</a></p>';
    }

    echo "<table class=\"gridtable\">";
    echo "<tr><th width='6%'>Class</th><th width='12%'>Species</th><th>Tissue</th><th>Treatment</th><th>Data Sources</th><th>	Reference</th></tr>";
    foreach ($__rows as $result)
    {
    echo "<tr align='center'><td><a href=\"./browse.php?class=$result[0]\">$result[0]</a></td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td><a href=\"https://www.ebi.ac.uk/pride/archive/projects/$result[4]\" target='_blank'>$result[4]</a></td>";
    if($result[5] == "-"){
        echo "<td>-</td>";
    }else{
        echo "<td><a href='$result[6]' target='_blank'>$result[5]</a></td>";
    }
    echo "</tr>";
    }
    echo "</table><br>";
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
