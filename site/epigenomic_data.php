<?php
/* =====================================================================
 * 表观组数据页
 *
 * 审稿意见 Referee 2 major 6 / 点 10(ii)：
 *   "On the Epigenomic Data page the 'Epigenome Type' dropdown only ever offers
 *    miRNA-seq."
 *
 * 根因（三处叠加）：
 *   1. 三个下拉框的候选项全部由硬编码的 DynamicOptionList 级联给出，而
 *      printOptions() 在现代浏览器里是空操作 —— <select> 里其实一个 option
 *      都没有，只有 JS 跑起来才会被填上。JS 一出问题，下拉框就是空的。
 *   2. 页面与其它模块共用 $_SESSION['species']，键值域还不同（这里存拉丁名，
 *      metagenomic/MAGs 等页存 'Acropora cervicornis' 这样的默认值）。从别的
 *      模块过来时会莫名继承一个物种 —— 而 Acropora cervicornis 与 Actinia
 *      equina 在库里恰好只有 miRNA-Seq，于是「类型」只剩一个选项。
 *   3. 只读 $_POST，从别处深链过来（GET）不生效。
 *
 * 现在：候选值全部按当前选择从数据库实时查出并在服务端渲染；
 *       会话键命名空间化；支持 GET 深链；类型与物种始终自洽。
 * ================================================================== */
require_once __DIR__ . '/includes/state.php';

/* 四列自由文本（tissue / dev / Treatment / Description）在库里就存成空串，
   不是 '-'（epigenome：Description 空 466/723、dev 空 404/723、Treatment 空 201/723、
   tissue 空 63/723）。原先直接印出来，表里就出现大片什么都没有的格子 ——
   读者分不清「来源记录没写」与「这一格没渲染出来」。统一印淡色破折号。
   记号与配色照抄站内既有的「样本元数据缺失」写法（includes/sample_meta.php 的
   cnido_meta_pill_html、样式在 cytoscape/trans_data.php:473 的 .mx-na），
   与 Metagenomic Data 页保持一致。
   值本身过 htmlspecialchars：现在这些列里没有 & 或 <，所以是零改动。 */
function epi_cell($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') {
        return '<span style="color:#64748b" title="no value in the source record">—</span>';
    }
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$__st = cnido_state('epigenome', array(
    'class'          => array('get' => 'class',          'default' => 'Hexacorallia'),
    'species'        => array('get' => 'species',        'default' => 'Nematostella vectensis'),
    'epigenome_type' => array('get' => 'epigenome_type', 'default' => ''),
));
$class          = $__st['class'];
$species        = $__st['species'];
$epigenome_type = $__st['epigenome_type'];

$__conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($__conn->connect_error) { error_log('CnidoSite: database connection failed'); die('The database is temporarily unavailable. Please try again in a moment.'); }

/* --- 候选项一律来自数据库：类别 / 物种 / 类型三者互相约束 --- */
$__classes = array();
if ($q = mysqli_query($__conn, "SELECT DISTINCT Class FROM epigenome ORDER BY Class")) {
    while ($r = mysqli_fetch_row($q)) { $__classes[] = $r[0]; }
}
if (!in_array($class, $__classes, true) && !empty($__classes)) { $class = $__classes[0]; }

$__speciesList = array();
if ($q = mysqli_query($__conn, "SELECT DISTINCT Species FROM epigenome WHERE Class = '"
        . mysqli_real_escape_string($__conn, $class) . "' ORDER BY Species")) {
    while ($r = mysqli_fetch_row($q)) { $__speciesList[] = $r[0]; }
}
/* 类群框与物种框之间没有 JS 联动：用户改完类群直接提交时，浏览器带上的是旧物种，
   于是「刚动过的类群」会被下面那段「以物种为准」的逻辑原样顶回去 —— 类群框看起来
   完全不起作用（POST class=Hydrozoa&species=Nematostella 会原封不动回到
   Hexacorallia）。所以先看本次请求有没有指定类群：指定了就以类群为准，把不属于
   新类群的物种落到该类群的第一个物种，这正是联动下拉框本来会做的事。 */
$__classGiven = in_array($__st['class__from'], array('get', 'post'), true);
if ($__classGiven && !in_array($species, $__speciesList, true) && !empty($__speciesList)) {
    $species = $__speciesList[0];
}
/* 物种不在当前类群里时，不要立刻回退到「该类第一个物种」——那会把
   ?species=Hydra vulgaris（Hydrozoa，但不带 class 的旧书签）静默显示成
   Acropora cervicornis，用户以为打开的是自己的物种。先查这个物种本来属于
   哪个类群，能查到就切过去；只有确实不在 epigenome 表里才回退。
   只在本次请求没有指定类群时走这条路径；指定了类群的已在上一步处理。 */
if (!$__classGiven && !in_array($species, $__speciesList, true) && !empty($__speciesList)) {
    $__spClass = '';
    if ($q = mysqli_query($__conn, "SELECT DISTINCT Class FROM epigenome WHERE Species = '"
            . mysqli_real_escape_string($__conn, $species) . "' LIMIT 1")) {
        $r = mysqli_fetch_row($q);
        if ($r) { $__spClass = $r[0]; }
    }
    if ($__spClass !== '' && in_array($__spClass, $__classes, true)) {
        $class = $__spClass;
        $__speciesList = array();
        if ($q = mysqli_query($__conn, "SELECT DISTINCT Species FROM epigenome WHERE Class = '"
                . mysqli_real_escape_string($__conn, $class) . "' ORDER BY Species")) {
            while ($r = mysqli_fetch_row($q)) { $__speciesList[] = $r[0]; }
        }
    }
    if (!in_array($species, $__speciesList, true) && !empty($__speciesList)) {
        $species = $__speciesList[0];
    }
}

$__typeList = array();
if ($q = mysqli_query($__conn, "SELECT DISTINCT Type FROM epigenome WHERE Species = '"
        . mysqli_real_escape_string($__conn, $species) . "' ORDER BY Type")) {
    while ($r = mysqli_fetch_row($q)) { $__typeList[] = $r[0]; }
}
if (!in_array($epigenome_type, $__typeList, true)) {
    // 类型与物种不匹配（例如从别的模块继承了物种、或刚刚切换了物种）：
    // 直接落到该物种真实存在的第一个类型，绝不再沿用旧的 miRNA-Seq。
    $epigenome_type = empty($__typeList) ? '' : $__typeList[0];
}
$_SESSION['epigenome_epigenome_type'] = $epigenome_type;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Epigenomic Data - CnidoSite</title>
<meta name="keywords" content="Cnidaria, epigenomics, ATAC-seq, ChIP-seq, Bisulfite-seq, miRNA-seq, DNA methylation" />
<meta name="description" content="Epigenomic data for Cnidaria species including ATAC-seq, ChIP-seq, DNase-seq, Bisulfite-seq and miRNA-seq" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<script src="./jquery.min.js"></script>

<style>
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
    border-color:#6d28d9;
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
    color:#6d28d9;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.external-link:hover {
    color: #7c3aed;
    text-decoration: underline;
}

<?php /* 搜索框。原来这里是 .search-box（纯客户端过滤，见搜索框那段说明），现在换成真正的
   服务端搜索表单 .epi-search。配色跟本页其它控件走同一个紫（#6d28d9）。 */ ?>
.epi-search {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 15px 20px;
    margin: 20px 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.epi-search label { font-weight: 600; color: #475569; }

.epi-search input[type=text] {
    flex: 1 1 320px;
    min-width: 200px;
    padding: 10px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
    transition: all 0.3s ease;
}

.epi-search input[type=text]:focus {
    outline: none;
    border-color:#6d28d9;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.epi-search button {
    padding: 10px 22px;
    background:#6d28d9;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.epi-search button:hover { background:#5b21b6; }

<?php /* 上色必须写成 a.类名：templatemo_style.css 的 a:link,a:visited{color:#1d4ed8} 是
   (0,1,1)，单类名 (0,1,0) 压不住（见 memory: cnidosite-link-color-specificity-trap）。 */ ?>
a.epi-clear { color:#6d28d9; text-decoration: none; font-size: 15px; font-weight: 500; padding: 6px 2px; }
a.epi-clear:hover { text-decoration: underline; }

.epi-hit {
    background: #f5f3ff;
    border: 1px solid #ddd6fe;
    border-radius: 8px;
    padding: 12px 18px;
    margin: 0 0 15px;
    color: #5b21b6;
    font-size: 15px;
}

.epi-empty {
    text-align: center;
    padding: 40px;
    background: #f8fafc;
    border-radius: 12px;
    margin: 30px 0;
    color: #64748b;
}
.epi-empty h3 { color: #334155; margin-bottom: 10px; }
.epi-empty p { margin: 6px 0; }

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
    max-width: 700px;
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
    border-color:#6d28d9;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.submit-btn {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

.current-selection {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    margin: 15px 0;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

@media (max-width: 768px) {
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
    
    .epi-search {
        flex-direction: column;
        align-items: stretch;
    }

    .epi-search input[type=text] {
        width: 100%;
        /* flex:0 0 auto 不能省：基础规则里的 flex:1 1 320px 在**竖排**容器里
           320 是"高度"基准（flex-basis 跟主轴走），只清 width 的话输入框会
           长成 320px 高，手机上就是搜索框底下一条大空白。 */
        flex: 0 0 auto;
    }
}
</style>

<script>
	<?php /* 物种 / 类型 的候选值现在由服务端直接从 epigenome 表查出并渲染
	 * （见页面顶部），不再依赖这组硬编码级联。原先这里只给 Acropora
	 * cervicornis / Actinia equina 配了 miRNA-Seq，一旦页面从别的模块
	 * 继承了这两个物种，类型下拉框就只剩 miRNA-Seq 一个选项。
	 *
	 * DynamicOptionList 对象也已删除：initDynamicOptionLists() 在 onLoad 时会
	 * 清空子下拉框再按硬编码数组重建，会把这里服务端渲染好的
	 * species / epigenome_type 选项整体冲掉。 */ ?>
	
</script>
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
            <li><a href="#">Genome</a>
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
            <li><a href="#" class="current">Epigenome</a>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Epigenomic Data</b></legend>
<p class="paleo-intro">
    A comprehensive collection of <strong>epigenomic data</strong> across different Cnidaria species. Select class, species and epigenome type to explore available datasets.
    A dash (&mdash;) in <b>Tissue</b>, <b>Dev Stage</b>, <b>Treatment</b> or <b>Description</b> means the deposited record does not state
    that field &mdash; it is missing from the source, not from this page.
</p>

<?php /* 选择表单 */ ?>
<div class="selection-form">
    <form name="epigenomeSearch" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" onSubmit="return checkquery()" encType="multipart/form-data">
        <table>
            <tr>
                <td><b>Class</b></td>
                <td>
                    <select name="class" id="classSelect">
                        <?= cnido_options($__classes, $class) ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td><b>Species</b></td>
                <td>
                    <select name="species" id="speciesSelect">
                        <?= cnido_options($__speciesList, $species) ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td><b>Epigenome Type</b></td>
                <td>
                    <select name="epigenome_type" id="epigenomeTypeSelect">
                        <?= cnido_options($__typeList, $epigenome_type) ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <input type="submit" value="View Samples" class="submit-btn"/>
                </td>
            </tr>
        </table>
    </form>
</div>

<?php
// ========== 分页逻辑 ==========
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}

// 构建查询条件
$where_conditions = [];
if ($class) {
    $where_conditions[] = "Class = '" . mysqli_real_escape_string($__conn, $class) . "'";
}
if ($species) {
    $where_conditions[] = "Species = '" . mysqli_real_escape_string($__conn, $species) . "'";
}
if ($epigenome_type) {
    $where_conditions[] = "Type = '" . mysqli_real_escape_string($__conn, $epigenome_type) . "'";
}

/* ========== 搜索框 ==========
 * 本页此前有一个 id=searchInput 的输入框，绑的是 jQuery 的 keyup 处理器：它过滤的
 * 只是**当前这一页**的 10 行（`$("#myTable tr:not(:first)")`），而下面的页码条仍报
 * 着当前筛选下的全部样品数。用户以为搜了整张表，其实只搜了一屏 —— 比没有搜索框更坏。
 * 现在改成服务端 GET，并串进 $__filterq，翻页与排序都带着它。
 *
 * 搜的列就是表格上看得见的那十列，外加 Run（下标 6，页面上不显示但它在库里是唯一
 * 逐行不同的一列）—— 用户手上拿到的往往正是 Run 号，搜不到会以为是数据没有。 */
$q = cnido_search_term(isset($_GET['q']) ? $_GET['q'] : '');
/* 不含搜索词的那一半条件单独留一份：只在「搜了但一条没中」时再拿它查一次总数，
   好把「这个筛选下本来就没有样品」和「有样品、但没一个含这个词」分开说。 */
$__base_conditions = $where_conditions;
if ($q !== '') {
    $where_conditions[] = cnido_like_any($conn, $q, array(
        '`Class`', 'Species', 'Type', 'Project', 'Study', 'Experiment', 'Run',
        'tissue', 'dev', 'Treatment', 'Description',
    ));
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}
$__where_base_clause = $__base_conditions ? ('WHERE ' . implode(' AND ', $__base_conditions)) : '';

// 获取总记录数
$count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM epigenome $where_clause");
$count_result = mysqli_fetch_assoc($count_query);
$total_records = $count_result['total'];

/* 搜了但一条没中时，再查一次「不带搜索词」的总数：下面靠它区分上面说的两种情况。 */
$__baseTotal = $total_records;
if ($q !== '' && $total_records == 0) {
    $__baseTotal = 0;
    if ($__cq = mysqli_query($conn, "SELECT COUNT(*) AS total FROM epigenome $__where_base_clause")) {
        if ($__cr = mysqli_fetch_assoc($__cq)) { $__baseTotal = (int)$__cr['total']; }
    }
}

// 分页参数
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// 验证参数有效性
if ($per_page <= 0) $per_page = 10;
if ($page <= 0) $page = 1;

// 计算总页数
$total_pages = ceil($total_records / $per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}

// 计算偏移量
$offset = ($page - 1) * $per_page;

/* 服务端排序。epigenome 表十一列全是 text、也没有索引，默认项写 null ＝ 不生成
   ORDER BY，初始次序与加按钮前逐字节一致（这张表的自然顺序本来就没有含义）。
   十个显示列（Run 不显示）全部给了箭头。Class / Species / Type 三列的箭头点了看不
   出变化 —— 上面三个筛选下拉把这三列各自钉成了同一个值，每行都相同；但它们确实各是
   表里的一列，点了就是「按这一列排」，不是假按钮，只是当前筛选下退化成恒等。
   Run 列（下标 6）页面上不显示，但它是这张表里唯一逐行不同的列（723 行 723 个不同的
   Run），拿它当并列键，ORDER BY 就构成全序 —— 只按一列排 + LIMIT，同一批行会在翻页
   边界上重复或消失。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array(
	'default' => null,
	'class'   => cnido_sort_txt('`Class`'),
	'species' => cnido_sort_txt('Species'),
	'type'    => cnido_sort_txt('Type'),
	'project' => cnido_sort_txt('Project'),
	'study'   => cnido_sort_txt('Study'),
	'exp'     => cnido_sort_txt('Experiment'),
	'tissue'  => cnido_sort_txt('tissue'),
	'dev'     => cnido_sort_txt('dev'),
	'treat'   => cnido_sort_txt('Treatment'),
	'desc'    => cnido_sort_txt('Description'),
);
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', array('Run'));
$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');
/* 当前筛选条件的查询串。翻页链接与「每页条数」选择器共用，下面排序参数也挂在它后面。 */
$__filterq = cnido_qs(array('class' => $class, 'species' => $species, 'epigenome_type' => $epigenome_type, 'q' => $q));

// 查询当前页数据
$query = mysqli_query($conn, "SELECT * FROM epigenome $where_clause" . $__orderSql . " LIMIT $offset, $per_page");

/* 搜索表单里的 hidden 排序字段。这份要在这里算：$__sortQs 在表格之后才拼（它服务于
   翻页链接），而搜索框在表格之前，两处用途不同不要共用同一个变量。 */
$__sortQsForm = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
$__selfForm   = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
?>

<?php /* 搜索框。刻意不放在「有结果」的分支里：一条没搜到时它也得在，否则用户除了浏览器
     后退没有别的路可走。GET 表单会替换整个查询串，所以三个筛选下拉 / 排序 / 每页
     条数全部用 hidden 带过去。 */ ?>
<form class="epi-search" method="get" action="<?= $__selfForm ?>">
    <input type="hidden" name="class" value="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="species" value="<?= htmlspecialchars($species, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="epigenome_type" value="<?= htmlspecialchars($epigenome_type, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="per_page" value="<?= (int)$per_page ?>" />
    <?php if ($__sortQsForm !== ''): ?>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($__sort, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="dir" value="<?= htmlspecialchars($__dir, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <label for="epiQ">Search samples</label>
    <input type="text" id="epiQ" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="Project, Study, tissue, treatment&hellip;" />
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?>
    <a class="epi-clear" href="<?= $__selfForm ?>?<?= htmlspecialchars(cnido_qs(array('class' => $class, 'species' => $species, 'epigenome_type' => $epigenome_type)) . '&per_page=' . (int)$per_page, ENT_QUOTES, 'UTF-8') ?>">Clear</a>
    <?php endif; ?>
</form>

<?php if ($q !== '' && $total_records > 0): ?>
<div class="epi-hit">
    <?= $total_records ?> sample(s) in the current filter match
    &ldquo;<b><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></b>&rdquo;.
</div>
<?php endif; ?>

<?php if ($q !== '' && $total_records == 0): ?>
<?php /* 搜了但没中。区分「这个筛选下没有样品」与「有样品、但没一个含这个词」。 */ ?>
<div class="epi-empty">
    <div style="font-size:48px;margin-bottom:16px;">🔍</div>
    <h3>Nothing matches &ldquo;<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>&rdquo;</h3>
    <?php if ($__baseTotal == 0): ?>
    <p>The current class / species / type filter holds no epigenomic sample at all, so there is
       nothing to search. Widen the filter above.</p>
    <?php else: ?>
    <p>None of the <?= (int)$__baseTotal ?> sample(s) in the current filter contains that term.
       Searchable columns are class, species, type, project, study, experiment, run, tissue,
       developmental stage, treatment and description &mdash; try a shorter term.</p>
    <p><a class="epi-clear" href="<?= $__selfForm ?>?<?= htmlspecialchars(cnido_qs(array('class' => $class, 'species' => $species, 'epigenome_type' => $epigenome_type)) . '&per_page=' . (int)$per_page, ENT_QUOTES, 'UTF-8') ?>">Show all <?= (int)$__baseTotal ?> sample(s)</a></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($total_records > 0): ?>
<?php /* 表格容器 */ ?>
<div class="table-container">
    <table class="gridtable" id="myTable">
        <tr>
            <th><?php echo cnido_sort_link('class', 'Class', $__sort, $__dir, $__filterq); ?></th>
            <th width="15%"><?php echo cnido_sort_link('species', 'Species', $__sort, $__dir, $__filterq); ?></th>
            <th><?php echo cnido_sort_link('type', 'Type', $__sort, $__dir, $__filterq); ?></th>
            <th><?php echo cnido_sort_link('project', 'Project', $__sort, $__dir, $__filterq); ?></th>
            <th><?php echo cnido_sort_link('study', 'Study', $__sort, $__dir, $__filterq); ?></th>
            <th><?php echo cnido_sort_link('exp', 'Experiment', $__sort, $__dir, $__filterq); ?></th>
            <?php /* 这四列是自由文本（tissue/dev/treatment 在 sample 表里最长 94/78/171 字），
                     居中会变成一团居中的锯齿，显式左对齐。Project/Study/Experiment 是
                     NCBI 号，短而齐，继续走默认居中。 */ ?>
            <th class="tal"><?php echo cnido_sort_link('tissue', 'Tissue', $__sort, $__dir, $__filterq); ?></th>
            <th class="tal"><?php echo cnido_sort_link('dev', 'Dev Stage', $__sort, $__dir, $__filterq); ?></th>
            <th class="tal"><?php echo cnido_sort_link('treat', 'Treatment', $__sort, $__dir, $__filterq); ?></th>
            <th class="tal"><?php echo cnido_sort_link('desc', 'Description', $__sort, $__dir, $__filterq); ?></th>
        </tr>
        <?php 
		while ($result = mysqli_fetch_row($query))
		{
			$query1 = mysqli_query($conn, "SELECT * FROM abbr where species = '$result[1]' ");
			$result1 = mysqli_fetch_row($query1);
			echo "<tr align=\"center\"><td><a href=\"./browse.php?class=$result[0]\">$result[0]</a></td><td><b><i><a href=\"speciesinfo.php?species=$result1[2]\">$result[1]</a></i></b></td><td>";
                    $type_color = "";
                    switch ($result[2]) {
                        case 'ATAC-seq': $type_color = '#1d4ed8'; break;
                        case 'ChIP-Seq': $type_color = '#047857'; break;
                        case 'DNase-Seq': $type_color = '#b45309'; break;
                        case 'Bisulfite-Seq': $type_color = '#6d28d9'; break;
                        case 'miRNA-Seq': $type_color = '#b91c1c'; break;
                        default: $type_color = '#6b7280';
                    }
			echo "<span style=\"background: $type_color; color: white; padding: 4px 8px; border-radius: 4px; font-size: 15px; font-weight: 600;\">$result[2]</span></td><td>";
			if ($result[3])
			{
             echo "<a href=\"https://www.ncbi.nlm.nih.gov/bioproject/$result[3]\" target=\"_blank\">$result[3]</a></td><td>";
			}else{
				echo "$result[3]</td><td>";
			}
			if ($result[4])
			{
             /* 原先指向 https://www.ncbi.nlm.nih.gov/studies/SRP… —— 这个路径在 NCBI 上
                一律 404（SRA 的 studies 页面早就换掉了）。同一串登录号在 SRA 检索里
                是有效的：/sra/?term=SRP101436 返回 200 并直接落到该 study。 */
             echo "<a href=\"https://www.ncbi.nlm.nih.gov/sra/?term=" . urlencode($result[4]) . "\" target=\"_blank\" rel=\"noopener\">$result[4]</a></td><td>";
			}else{
				echo "$result[4]</td><td>";
			}
			if ($result[5])
			{
             echo "<a href=\"https://www.ncbi.nlm.nih.gov/sra/?term=$result[5]\" target=\"_blank\"><img src=\"./images/NCBI.png\" width=\"12px\" height=\"12px\">&nbsp;$result[5]</a></td><td class=\"tal\">";
			}else{
				echo "$result[5]</td><td class=\"tal\">";
			}
			/* 最后四格（tissue / dev / treatment / description）走 epi_cell()：
			   空值印淡色 &ndash;，不再留一格什么都没有。见文件开头那段说明。 */
			echo epi_cell($result[7]) . "</td><td class=\"tal\">" . epi_cell($result[8]) . "</td><td class=\"tal\">" . epi_cell($result[9]) . "</td><td class=\"tal\">" . epi_cell($result[10]) . "</td></tr>";
		}
        ?>
    </table>
</div>

<?php
/* 翻页链接原先只带 per_page 与 page，把筛选条件（类群、物种、类型）整个丢掉了：
   在一个筛选结果里翻到第 2 页，回来的却是默认筛选下的第 2 页，看起来就像筛选被
   清空了。同一块里的「每页条数」选择器本来就用 cnido_qs() 拼了全量参数，翻页链接
   是漏掉的那一半。这里把当前筛选拼成一份基础查询串，所有翻页链接都从它派生，
   以后再加筛选条件只要改这一处。 */
$__sortQs  = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
$__baseQs  = $__filterq . ($__sortQs !== '' ? '&' . $__sortQs : '');
$__self    = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
$__pageUrl = function ($p) use ($__baseQs, $per_page, $__self) {
    return $__self . '?' . ($__baseQs !== '' ? $__baseQs . '&' : '')
         . 'per_page=' . (int)$per_page . '&page=' . (int)$p;
};
?>
<?php /* 分页导航 */ ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* 同 metagenomic_data.php：这个数只统计当前筛选（一个物种 + 搜索词），
                     全表是 723。标成 "Total" 会与页首的全量数字对不上。 */ ?>
            📊 <?= $total_records ?> epigenomic sample<?= $total_records == 1 ? '' : 's' ?>
            in the current filter
        </div>

        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='<?= $__self ?>?<?= $__baseQs ?>&per_page='+this.value+'&page=1'">
                <?php
                $options = [10, 20, 50, 100];
                foreach ($options as $option) {
                    $selected = ($option == $per_page) ? 'selected' : '';
                    echo "<option value='$option' $selected>$option</option>";
                }
                ?>
            </select>
            <span>samples per page</span>
        </div>
    </div>
    
        <?php if ($total_pages > 0): /* 命中 0 条时不渲染分页条：$total_pages
         是 0，页码循环一次都不进，几个按钮和「of 0 pages」却照旧印出来，
         全部指向自己。站内约定见 browse.php / go_result.php。 */ ?>
<div class="pagination-nav">
        <?php /* 首页 */ ?>
        <a href="<?= $__pageUrl(1) ?>"
           class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">
            « First
        </a>

        <?php /* 上一页 */ ?>
        <a href="<?= $__pageUrl(max(1, $page - 1)) ?>"
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
            echo '<a href="' . $__pageUrl(1) . '" class="page-btn">1</a>';
            if ($start_page > 2) {
                echo '<span class="page-btn disabled">...</span>';
            }
        }

        // 中间页码
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<a href="' . $__pageUrl($i) . '" class="page-btn ' . $active . '">' . $i . '</a>';
        }

        // 右侧省略号
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<span class="page-btn disabled">...</span>';
            }
            echo '<a href="' . $__pageUrl($total_pages) . '" class="page-btn">' . $total_pages . '</a>';
        }
        ?>

        <?php /* 下一页 */ ?>
        <a href="<?= $__pageUrl(min($total_pages, $page + 1)) ?>"
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Next ›
        </a>

        <?php /* 末页 */ ?>
        <a href="<?= $__pageUrl($total_pages) ?>"
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Last »
        </a>
    </div>
    
    <?php /* 跳转到指定页 */ ?>
    <div class="go-to-page">
        <span>Go to page:</span>
        <input type="number" id="gotoPage" min="1" max="<?= $total_pages ?>" value="<?= $page ?>">
        <button onclick="window.location.href='<?= $__self ?>?<?= $__filterq ?><?= $__filterq !== '' ? '&' : '' ?>per_page=<?= (int)$per_page ?>&page='+document.getElementById('gotoPage').value">
            Go
        </button>
        <span>of <?= $total_pages ?> pages</span>
    </div>    <?php endif; /* $total_pages > 0 */ ?>

</div>
<?php endif; /* $total_records > 0 */ ?>

</div>
</div>
</div>

<script>
<?php /* 原来的 $("#searchInput").on("keyup", …) 已删除：它只过滤当前这一页的 10 行，而页码条
   仍报当前筛选下的全部样品数 —— 见搜索框那段说明。搜索现在由服务端做（.epi-search）。 */ ?>

// 确保选中的值保持在下拉框中
document.addEventListener('DOMContentLoaded', function() {
    // 设置Class下拉框的选中值
    const savedClass = '<?= addslashes($class) ?>';
    const savedSpecies = '<?= addslashes($species) ?>';
    const savedEpigenomeType = '<?= addslashes($epigenome_type) ?>';
    
    if (savedClass) {
        document.querySelector('select[name="class"]').value = savedClass;
    }
    
    // 动态设置Species和Epigenome Type下拉框的选中值
    setTimeout(function() {
        if (savedSpecies) {
            document.querySelector('select[name="species"]').value = savedSpecies;
        }
        if (savedEpigenomeType) {
            document.querySelector('select[name="epigenome_type"]').value = savedEpigenomeType;
        }
    }, 100); // 延迟100ms确保动态选项已加载
});

// 保存选择到localStorage
document.querySelector('form').addEventListener('submit', function() {
    const classValue = document.querySelector('select[name="class"]').value;
    const speciesValue = document.querySelector('select[name="species"]').value;
    const epigenomeTypeValue = document.querySelector('select[name="epigenome_type"]').value;
    
    localStorage.setItem('selectedClass', classValue);
    localStorage.setItem('selectedSpecies', speciesValue);
    localStorage.setItem('selectedEpigenomeType', epigenomeTypeValue);
});
</script>

<?php
    include "./Webpage_components.php";
    print $footer;
?>
</body>
</html>
