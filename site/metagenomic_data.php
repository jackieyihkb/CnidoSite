<?php
/* 审稿意见 Referee 2 点 10(iii)：Metagenome 页点 “View Samples” 没有反应。
 *
 * 根因：本页的翻页/每页条数链接会把当前物种写进 URL，而表单没有 action，
 * 于是 POST 回到「带旧 ?species=… 的同一个 URL」。原代码里 GET 分支写在 POST
 * 分支之后并覆盖 $_SESSION['species'] —— 用户换了物种提交，服务端又用 URL 里
 * 的旧物种把新选择覆盖回去，页面看起来「完全没反应」。
 *
 * 现在：仅在本次请求没有 POST 时 GET 才生效；会话键命名空间化，避免继承其它
 * 模块选的物种；物种下拉框改为服务端渲染，不再依赖 DynamicOptionList。
 */
require_once __DIR__ . '/includes/state.php';

/* 三列自由文本（tissue / dev / Treatment）在库里就存成空串，不是 '-'
   （metaG：dev 空 739/1,650、treatment 空 971/1,650、tissue 空 525/1,650）。
   原先直接印出来，表里就出现大片什么都没有的格子 —— 读者分不清「来源记录没写」
   与「这一格没渲染出来」。统一印一个淡色的破折号：这一项来源记录里没有给。
   记号与配色照抄站内既有的「样本元数据缺失」写法（includes/sample_meta.php 的
   cnido_meta_pill_html、样式在 cytoscape/trans_data.php:473 的 .mx-na）——
   那一页列的是同一批样品、同样的三列，两处必须长得一样。
   值本身过 htmlspecialchars：现在这些列里没有 & 或 <，所以是零改动，
   但以后有人往库里塞带符号的样本名时，不用再回来补。 */
function meta_cell($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') {
        return '<span style="color:#64748b" title="no value in the source record">—</span>';
    }
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$formSubmitted = isset($_POST['species']);
$__st = cnido_state('metagenome', array(
    'species' => array('get' => 'species', 'default' => 'Nematostella vectensis'),
));
$species = $__st['species'];
if ($formSubmitted) {                      // 表单提交优先，URL 里的旧值不再覆盖
    $species = $_POST['species'];
    $_SESSION['metagenome_species'] = $species;
}

/* Class 下拉框原来和物种列表毫无关系：三个选项是硬编码的，选完提交只被写进
   $_SESSION['metagenome_class']，既不筛物种列表，也不进任何查询的 WHERE ——
   物种列表始终是 metaG 表里的全部物种。现在它真的筛选物种列表，优先级与转录组、
   表观组页一致：本次请求指定了类群就以类群为准（物种不属于该类群时落到该类群的
   第一个物种，这正是联动下拉框本来会做的事）；没指定类群则反过来由物种推出类群，
   这样 ?species=Hydra+vulgaris 这类不带 class 的深链仍然能落到 Hydrozoa。 */
$__classGiven  = isset($_POST['class']) || isset($_GET['class']);
$selectedClass = isset($_POST['class']) ? $_POST['class']
               : (isset($_GET['class']) ? $_GET['class']
               : (isset($_SESSION['metagenome_class']) ? $_SESSION['metagenome_class'] : ''));
if ($__classGiven) { $_SESSION['metagenome_class'] = $selectedClass; }

$__allSpecies = array();        // Latin_name => Class，metaG 表里的全部物种
$__classSeen  = array();
$__cm = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if (!$__cm->connect_error && ($q = mysqli_query($__cm, "SELECT DISTINCT Species, Class FROM metaG ORDER BY Species"))) {
    while ($r = mysqli_fetch_row($q)) {
        $__allSpecies[$r[0]] = $r[1];
        if ($r[1] !== null && $r[1] !== '') { $__classSeen[$r[1]] = true; }
    }
}
if ($__cm && !$__cm->connect_error) { $__cm->close(); }
$__classes = cnido_classes_in(array_keys($__classSeen));

/* 候选列表存的是 metaG.Species 里的拉丁学名，而深链传进来的可能是短码或下划线
   形式。不归一化就会多出一个 “Nematostella_vectensis (unavailable)” 项 ——
   既与列表里同一物种的学名重复，又把物种代码显示给了读者。 */
if ($species !== '' && !isset($__allSpecies[$species])) {
    $__latin = cnido_latin_of($species);
    if (isset($__allSpecies[$__latin])) { $species = $__latin; }
}

if ($__classGiven) {
    if (!in_array($selectedClass, $__classes, true)) {
        $selectedClass = empty($__classes) ? '' : $__classes[0];
    }
} else {
    $selectedClass = isset($__allSpecies[$species]) ? $__allSpecies[$species]
                   : (empty($__classes) ? '' : $__classes[0]);
}

$__speciesList = array();
foreach ($__allSpecies as $__sp => $__cl) {
    if ($__cl === $selectedClass) { $__speciesList[] = $__sp; }
}
/* 指定了类群而物种不属于它：落到该类群第一个物种 */
if ($__classGiven && !empty($__speciesList) && !in_array($species, $__speciesList, true)) {
    $species = $__speciesList[0];
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
<title>Metagenomic Data - CnidoSite</title>
<meta name="keywords" content="Cnidaria, metagenome, metagenomic samples, SRA, BioProject, coral microbiome" />
<meta name="description" content="Metagenomic samples associated with cnidarian hosts: project, experiment, host species and sequencing metadata" />
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

<?php /* 搜索框。原来这里是 .search-box（纯客户端过滤，见页首说明），现在换成真正的
   服务端搜索表单 .meta-search。配色跟本页其它控件走同一个蓝（#1d4ed8）。 */ ?>
.meta-search {
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

.meta-search label { font-weight: 600; color: #475569; }

.meta-search input[type=text] {
    flex: 1 1 320px;
    min-width: 200px;
    padding: 10px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
    transition: all 0.3s ease;
}

.meta-search input[type=text]:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.meta-search button {
    padding: 10px 22px;
    background:#1d4ed8;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.meta-search button:hover { background:#1e40af; }

<?php /* 上色必须写成 a.类名：templatemo_style.css 的 a:link,a:visited{color:#1d4ed8} 是
   (0,1,1)，单类名 (0,1,0) 压不住（见 memory: cnidosite-link-color-specificity-trap）。 */ ?>
a.meta-clear { color:#1d4ed8; text-decoration: none; font-size: 15px; font-weight: 500; padding: 6px 2px; }
a.meta-clear:hover { text-decoration: underline; }

.meta-hit {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 12px 18px;
    margin: 0 0 15px;
    color: #1e40af;
    font-size: 15px;
}

.meta-empty {
    text-align: center;
    padding: 40px;
    background: #f8fafc;
    border-radius: 12px;
    margin: 30px 0;
    color: #64748b;
}
.meta-empty h3 { color: #334155; margin-bottom: 10px; }
.meta-empty p { margin: 6px 0; }

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

.current-species {
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
    
    .meta-search {
        flex-direction: column;
        align-items: stretch;
    }

    .meta-search input[type=text] {
        width: 100%;
        /* flex:0 0 auto 不能省：基础规则里的 flex:1 1 320px 在**竖排**容器里
           320 是"高度"基准（flex-basis 跟主轴走），只清 width 的话输入框会
           长成 320px 高，手机上就是搜索框底下一条大空白。 */
        flex: 0 0 auto;
    }
}
</style>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var inputDiv = document.querySelector('.input');
        if (inputDiv) {
            inputDiv.style.clear = 'both';
            inputDiv.style.marginBottom = '5px';
            inputDiv.style.font = 'bold 15px Arial, Helvetica, sans-serif';
        }
    });
</script>

<?php /* 原先这里用 DynamicOptionList 硬编码了 Class -> 物种的级联。
     initDynamicOptionLists() 在 onLoad 时会清空物种下拉框再按这份 2023 年的
     名单重建，既会冲掉服务端渲染的选项、也会盖掉深链进来的物种
     （Referee 2 major 1）。物种候选值现在由 PHP 从 metaG 表查出渲染。 */ ?>
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
            <li><a href="#" class="current">Metagenome</a>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Metagenomic Samples</b></legend>
<p class="paleo-intro">This module lists the 1,650 metagenomic datasets (28 projects) held here.
   A dash (&mdash;) in <b>Tissue</b>, <b>Developmental Stage</b> or <b>Treatment</b> means the deposited record does not state that field
   &mdash; it is missing from the source, not from this page.</p>

<?php /* 物种选择表单 */ ?>
<div class="selection-form" >
    <form name="atidsearch" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" onSubmit="return checkquery()" encType="multipart/form-data">
        <table>
            <tr>
                <td><b>Class</b></td>
                <td>
                    <select name="class" id="classSelect">
                        <?= cnido_options($__classes, $selectedClass) ?>
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

/* $species 来自 URL / 表单，拼进 SQL 前必须转义。 */
$speciesEsc = mysqli_real_escape_string($conn, $species);

/* ========== 搜索框 ==========
 * 本页此前有一个 id=searchInput 的输入框，绑的是 jQuery 的 keyup 处理器：它过滤的
 * 只是**当前这一页**的 10 行（`$("#myTable tr:not(:first)")`），而下面的页码条仍报
 * 着该物种全部样品的数。用户以为搜了整张表，其实只搜了一屏 —— 比没有搜索框更坏。
 * 现在改成服务端 GET，并串进 $__baseQs，翻页与排序都带着它。
 *
 * 搜的列就是表格上看得见的那九列。Run 与 Experiment 都放进来：表头写的是 "Run"，
 * 格子里印的却是 Experiment（SRX 号）—— 这是本页原有的显示问题，不在本次范围内，
 * 但搜索不该跟着它一起只认一个：用户手上可能是 SRR 号，也可能是 SRX 号。 */
$q = cnido_search_term(isset($_GET['q']) ? $_GET['q'] : '');
$__where = array("Species = '$speciesEsc'");
if ($q !== '') {
    $__where[] = cnido_like_any($conn, $q, array(
        'Run', 'Experiment', '`Class`', 'Species', 'Project', 'Study',
        'Layout', 'tissue', 'dev', 'Treatment',
    ));
}
$__whereSql = ' WHERE ' . implode(' AND ', $__where);

// 获取总记录数
$count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM metaG" . $__whereSql);
$count_result = mysqli_fetch_assoc($count_query);
$total_records = $count_result['total'];

/* 搜了但一条没中时，再查一次「不带搜索词」的总数：好把「这个物种没有样品」和
   「有 N 个样品、但没一个含这个词」分开说 —— 这两种情况该改的东西不一样。 */
$__baseTotal = $total_records;
if ($q !== '' && $total_records == 0) {
    $__baseTotal = 0;
    if ($__cq = mysqli_query($conn, "SELECT COUNT(*) AS total FROM metaG WHERE Species = '$speciesEsc'")) {
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

/* ========== 表头排序（服务端） ==========
 * 这张表是分页的（LIMIT $offset, $per_page），而页面里除了结果表还有一张「选物种」
 * 的表单表 —— js/table-sort.js 判断「分页表」时看的是祖先容器里有几张 table，两张
 * 就判不出来，于是这十行一直在浏览器里被重排（第 2 页的最小值可能比第 1 页的最大值
 * 还小）。改成服务端表头链接，客户端脚本见到 data-sort-link 就整表跳过。
 *
 * 默认档用 null：原来的 SQL 没有 ORDER BY，这里不主动换掉它 —— 用户点了哪列才真正排。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array(
    'default' => null,
    /* 表头写的是 Run，格子里印的却是 $result[4] —— 那是 Experiment（SRX 号），
       Run 列（SRR 号）在本页根本没显示过。排序键必须跟着**显示的东西**走，
       否则点了 Run 表头，看到的那一列纹丝不动。 */
    'run'     => cnido_sort_txt('Experiment'),
    'class'   => cnido_sort_txt('`Class`'),
    'species' => cnido_sort_txt('Species'),
    /* 表头 BioProject / SRA 对应的列名是 Project / Study（不是同名的 Project/SRA）。 */
    'project' => cnido_sort_txt('Project'),
    'sra'     => cnido_sort_txt('Study'),
    'layout'  => cnido_sort_txt('Layout'),
    'tissue'  => cnido_sort_txt('tissue'),
    'dev'     => cnido_sort_txt('dev'),
    'treat'   => cnido_sort_txt('Treatment'),
);
/* 并列键用 Run：SRR 号在样品表里唯一，翻页跨 LIMIT 边界才不会漏行。 */
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', 'Run');
$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');

$__filterQs = 'species=' . urlencode($species) . ($q !== '' ? '&q=' . urlencode($q) : '');
$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
$__baseQs   = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');

// 查询当前页数据（条件与上面计数用的是同一份 $__whereSql，两处不会分叉）
$query = mysqli_query($conn, "SELECT * FROM metaG" . $__whereSql . $__orderSql . " LIMIT $offset, $per_page");
?>
<?php if (!empty($species)): ?>
<?php /* 搜索框。刻意不放在「有结果」的分支里：一条没搜到时它也得在，否则用户除了浏览器
     后退没有别的路可走。GET 表单会替换整个查询串，所以 species / 排序 / 每页条数
     都用 hidden 带过去。 */ ?>
<form class="meta-search" method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="species" value="<?= htmlspecialchars($species, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="per_page" value="<?= (int)$per_page ?>" />
    <?php if (isset($_GET['class'])): ?>
    <input type="hidden" name="class" value="<?= htmlspecialchars($_GET['class'], ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <?php if ($__sortQs !== ''): ?>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($__sort, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="dir" value="<?= htmlspecialchars($__dir, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <label for="metaQ">Search samples</label>
    <input type="text" id="metaQ" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="ERR/ERX accession, whole animal, bleached, Control&hellip;" />
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?>
    <a class="meta-clear" href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?species=<?= urlencode($species) ?>&amp;per_page=<?= (int)$per_page ?>">Clear</a>
    <?php endif; ?>
</form>

<?php if ($q !== '' && $total_records > 0): ?>
<div class="meta-hit">
    <?= $total_records ?> sample(s) of
    <b><i><?= htmlspecialchars($species, ENT_QUOTES, 'UTF-8') ?></i></b> match
    &ldquo;<b><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></b>&rdquo;.
</div>
<?php endif; ?>

<?php if ($q !== '' && $total_records == 0): ?>
<?php /* 搜了但没中。区分「这个物种没有样品」与「有样品、但没一个含这个词」。 */ ?>
<div class="meta-empty">
    <div style="font-size:48px;margin-bottom:16px;">🔍</div>
    <h3>Nothing matches &ldquo;<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>&rdquo;</h3>
    <?php if ($__baseTotal == 0): ?>
    <p>The catalogue holds no sample for <b><i><?= htmlspecialchars($species, ENT_QUOTES, 'UTF-8') ?></i></b>,
       so there is nothing to search. Pick another species above.</p>
    <?php else: ?>
    <p>None of the <?= (int)$__baseTotal ?> sample(s) of
       <b><i><?= htmlspecialchars($species, ENT_QUOTES, 'UTF-8') ?></i></b> contains that term.
       Searchable columns are run/experiment accession, BioProject, SRA study, layout,
       tissue, developmental stage and treatment &mdash; try a shorter term.</p>
    <p><a class="meta-clear" href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?species=<?= urlencode($species) ?>&amp;per_page=<?= (int)$per_page ?>">Show all <?= (int)$__baseTotal ?> sample(s)</a></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($total_records > 0): ?>
<?php /* 表格容器 */ ?>
<div class="table-container">
    <table class="gridtable" id="myTable">
        <tr>
            <?php /* 表头原先写 "Run"，格子里印的却是 $result[4] = Experiment（SRX 号），
                     整页从未显示过 Run（SRR 号）。表头改成与内容一致。 */ ?>
            <th><?= cnido_sort_link('run', 'Experiment', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('class', 'Class', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('species', 'Species', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('project', 'BioProject', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('sra', 'SRA', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('layout', 'Layout', $__sort, $__dir, $__filterQs) ?></th>
            <?php /* 这三列在库里是自由文本（tissue 最长 94 字、dev_stage 78 字、treatment 171 字），
                     居中会变成一团居中的锯齿，显式左对齐。其余列走共用样式的默认居中。 */ ?>
            <th class="tal"><?= cnido_sort_link('tissue', 'Tissue', $__sort, $__dir, $__filterQs) ?></th>
            <th width="10%" class="tal"><?= cnido_sort_link('dev', 'Developmental Stage', $__sort, $__dir, $__filterQs) ?></th>
            <th width="20%" class="tal"><?= cnido_sort_link('treat', 'Treatment', $__sort, $__dir, $__filterQs) ?></th>
        </tr>
        <?php
		while ($result = mysqli_fetch_row($query)){
			$query1 = mysqli_query($conn, "SELECT * FROM abbr where species = '$result[1]' ");
			$result1 = mysqli_fetch_row($query1);
			/* 最后三格（tissue / dev / treatment）走 meta_cell()：空值印淡色 &ndash;，
			   不再留一格什么都没有。见文件开头那段说明。 */
			echo "<tr align=\"center\"><td><a href=\"https://www.ncbi.nlm.nih.gov/sra/?term=$result[4]\" target=\"_blank\"><img src=\"../images/NCBI.png\" width=\"12px\" height=\"12px\">&nbsp;$result[4]</a></td><td><a href=\"./browse.php?class=$result[0]\">$result[0]</a></td><td><b><i><a href=\"speciesinfo.php?species=$result1[2]\">$result[1]</a></i></b></td><td><a href=\"https://www.ncbi.nlm.nih.gov/bioproject/$result[2]\" target=\"_blank\">$result[2]</a></td><td><a href=\"https://www.ncbi.nlm.nih.gov/Traces/study/?acc=$result[3]&o=acc_s%3Aa\" target=\"_blank\">$result[3]</a></td><td>$result[6]</td><td class=\"tal\">" . meta_cell($result[7]) . "</td><td class=\"tal\">" . meta_cell($result[8]) . "</td><td class=\"tal\">" . meta_cell($result[9]) . "</td></tr>";
		}
		?>
    </table>
</div>

<?php /* 分页导航 */ ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* 这个数只统计**当前筛选**（一个物种 + 搜索词）。全表是 1,650 行，
                     默认物种下可能只有 80 —— 标成 "Total" 会让读者拿它跟页首的
                     1,650 对照，以为少了 20 倍。与页内搜索提示的措辞统一。 */ ?>
            📊 <?= $total_records ?> metagenomic sample<?= $total_records == 1 ? '' : 's' ?>
            in the current filter
        </div>
        
        <div class="per-page-selector">
            <span>Show:</span>
            <?php /* ====== 修改3：对 species 进行 URL 编码 ====== */ ?>
            <select onchange="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page='+this.value+'&page=1'">
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
<?php endif; /* $total_records > 0 */ ?>
<?php endif; /* !empty($species) */ ?>

</div>
</div>
</div>

<script>
<?php /* 原来的 $("#searchInput").on("keyup", …) 已删除：它只过滤当前这一页的 10 行，而页码条
   仍报该物种全部样品的数 —— 见页首搜索框那段说明。搜索现在由服务端做（.meta-search）。 */ ?>

// 确保选中的物种保持在下拉框中
document.addEventListener('DOMContentLoaded', function() {
    <?php /* 服务端渲染的选中值优先。
       原先这里无条件用 localStorage 里的旧值覆盖下拉框，于是「换了物种提交之后
       方框里仍显示上一次选的物种」（Referee 2 major 1）。现在只有本次请求根本
       没有指定物种（服务端用的是默认值）时，才回退到 localStorage 的记忆值；
       而且该值必须确实存在于选项中才会被采用。 */ ?>
    const fromServer = <?= json_encode(isset($__st['species__from']) ? $__st['species__from'] : 'default') ?>;

    function has(sel, val) {
        const el = document.querySelector(sel);
        return !!(el && val) && Array.prototype.some.call(el.options, function (o) { return o.value === val; });
    }
    function pick(sel, val) {
        const el = document.querySelector(sel);
        if (el && val) { el.value = val; }
    }

    <?php /* 类群与物种必须一起恢复：物种列表现在由服务端按类群渲染，单独恢复类群会
       出现「类群框写着 Hydrozoa、物种框里却是 Hexacorallia 的物种」这种自相
       矛盾的画面（记住的物种不在当前列表里时尤其如此）。两个值都在当前选项里
       才一起套用，否则保留服务端的默认值。 */ ?>
    if (fromServer === 'default') {
        const alg = localStorage.getItem('selectedAlg');
        const sp  = localStorage.getItem('selectedSpecies');
        if (has('select[name="class"]', alg) && has('select[name="species"]', sp)) {
            pick('select[name="class"]', alg);
            pick('select[name="species"]', sp);
        }
    }
});

// 保存选择到localStorage
<?php /* 指名 atidsearch，不用 document.querySelector('form')：本页现在有两张表单（选物种 +
   搜索），按「文档里第一张」取会在哪天调整了表单顺序时静默抓错。 */ ?>
document.forms['atidsearch'].addEventListener('submit', function() {
    const algValue = document.querySelector('select[name="class"]').value;
    const speciesValue = document.querySelector('select[name="species"]').value;
    localStorage.setItem('selectedAlg', algValue);
    localStorage.setItem('selectedSpecies', speciesValue);
});
</script>

<?php
    include "./Webpage_components.php";
    print $footer;
?>
</body>
</html>
