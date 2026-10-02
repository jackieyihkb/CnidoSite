<?php
/* 支持物种门户 / 其它模块的 GET 深链（审稿意见 Referee 2 major 1）。
 * 会话键按模块隔离，避免继承表观组、单细胞等模块选的物种。 */
require_once __DIR__ . '/../includes/state.php';
/* dev_stage / Treatment 的 ℃ 修复与归类（只作用于显示层，数据库不动）。 */
require_once __DIR__ . '/../includes/sample_meta.php';
$__st = cnido_state('transcriptome', array(
    'specie' => array('get' => 'species', 'default' => 'Nematostella vectensis'),
    'class'  => array('get' => 'class',  'default' => ''),
));
$specie     = $__st['specie'];
$__classReq = $__st['class'];

/* 审稿意见 Referee 1 minor 1 & 3 / Referee 2 major 1：
 * 本页的物种下拉框在 HTML 里是空的，候选项由 DynamicOptionList 在 onLoad 时
 * 重建，且那份硬编码列表与 sample 表并不完全一致（218 个物种里有若干不在
 * 列表里，也有列表里有而库里没有的）。改为直接从 sample 表生成。 */
$__conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
$__allSpecies = array();        // Latin_name => Class，sample 表里的全部物种
$__classSeen  = array();
if (!$__conn->connect_error) {
    $__q = mysqli_query($__conn, "SELECT DISTINCT Latin_name, Class FROM sample
                                  WHERE Latin_name IS NOT NULL AND Latin_name <> ''
                                  ORDER BY Latin_name");
    if ($__q) {
        while ($__r = mysqli_fetch_row($__q)) {
            $__allSpecies[$__r[0]] = $__r[1];
            if ($__r[1] !== null && $__r[1] !== '') { $__classSeen[$__r[1]] = true; }
        }
    }
}
$__classes = cnido_classes_in(array_keys($__classSeen));

/* 类群下拉框原来只是一个「当前物种属于哪个类群」的只读显示：它的值是从物种推出
   来的（原来第 44 行的 $__class），既不参与 species 列表的生成，也不进任何查询
   的 WHERE。改选 Cubozoa 再点 View Samples，页面照旧显示原来那个物种 —— 控件
   在动，数据不动。现在它真的筛选物种列表。

   优先级按「用户刚动过的那个控件说了算」：
     · 传了合法类群（表单提交或 ?class=）→ 类群为准，物种列表只列该类群的物种；
       物种若不属于这个类群，落到该类群的第一个物种（这正是浏览器在联动下拉框
       里会做的事 —— 本页的类群框与物种框之间没有 JS 联动，提交时带上的是旧物种，
       所以必须由服务端把这一步补上，否则选类群等于没选）；
     · 没传类群 → 由物种推出类群，保留评审时修好的那条路径：?species=Hydra+vulgaris
       这样的旧书签仍然落到 Hydrozoa，不会被默认的 Hexacorallia 顶掉。 */
if (!isset($__allSpecies[$specie])) {
    /* sample 表按拉丁学名索引，深链传进来的可能是短码或下划线形式。先解析成学名，
       能对上就直接用，免得把 “Nematostella_vectensis” 这样的代码写进提示、又把一个
       其实有数据的物种当成「没有样本」退回去。 */
    $__latin = cnido_latin_of($specie);
    if ($__latin !== $specie && isset($__allSpecies[$__latin])) { $specie = $__latin; }
}

$__classExplicit = in_array($__classReq, $__classes, true);
$__class = $__classExplicit ? $__classReq
                           : (isset($__allSpecies[$specie]) ? $__allSpecies[$specie] : '');

/** 按当前类群取物种列表（类群为空 = 全部）。 */
$__speciesList = array();
foreach ($__allSpecies as $__sp => $__cl) {
    if ($__class === '' || $__cl === $__class) { $__speciesList[$__sp] = $__cl; }
}

$__speciesNotice = '';
if ($__classExplicit) {
    if (!isset($__speciesList[$specie]) && !empty($__speciesList)) {
        $__keys = array_keys($__speciesList);
        $specie = $__keys[0];
    }
} elseif (!empty($__allSpecies) && !isset($__allSpecies[$specie])) {
    /* 既没指定类群，物种又不在 sample 表里：明确提示并退回第一个可用物种 */
    $__keys = array_keys($__speciesList ? $__speciesList : $__allSpecies);
    $__fallback = $__keys[0];
    $__speciesNotice = 'No transcriptomic sample is registered for <b>' . htmlspecialchars($specie)
                     . '</b>; showing <b>' . htmlspecialchars($__fallback) . '</b> instead. '
                     . 'See the <a href="/species_portal.php?species=' . urlencode($specie)
                     . '">species portal</a> for what this species does provide.';
    $specie = $__fallback;
    /* 类群跟着退回后的物种走，免得类群框显示空白 */
    $__class = isset($__allSpecies[$specie]) ? $__allSpecies[$specie] : '';
    $__speciesList = array();
    foreach ($__allSpecies as $__sp => $__cl) {
        if ($__class === '' || $__cl === $__class) { $__speciesList[$__sp] = $__cl; }
    }
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
<title>Transcriptomic Data - CnidoSite</title>
<meta name="keywords" content="Cnidaria, transcriptome, RNA-seq, SRA, BioProject" />
<meta name="description" content="Transcriptomic data for 15,642 samples across different tissues and developmental stages" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="../js/func.js" type="text/javascript"></script>
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
    font-size: 14px;
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
    font-size: 14px;
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
    font-size: 14px;
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
    font-size: 14px;
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

<?php /* Class 与 Species 同一行显示（原先是两行的表格）。物种框比类群框宽，
   窄屏下整行自动换行；按钮用 align-items:flex-end 与下拉框底边对齐。 */ ?>
.selection-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 16px;
    max-width: 900px;
}

.selection-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.selection-field label {
    font-size: 14px;
    font-weight: 700;
    color: #334155;
}

.sf-class {
    flex: 0 1 200px;
    min-width: 160px;
}

.sf-species {
    flex: 1 1 340px;
    min-width: 240px;
}

.sf-submit {
    flex: 0 0 auto;
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
    
    .search-box {
        justify-content: flex-start;
    }
    
    .search-box input {
        width: 100%;
    }
}

<?php /* ── 样本元数据「简易总结」：分布卡 + 徽章 ─────────────────────────────
   gridtable 现在是 table-layout:auto（与 core 的 table.cc 一致），列宽不再由
   <th width> 定死，但 width 仍当「建议宽度」用，长文本照样能把列撑开。所以这里的
   文本仍旧自己截断：overflow:hidden + text-overflow，完整原文始终放在 title 上。
   徽章和 detail 也继续显式 nowrap（早年 gridtable 的 white-space:pre-wrap 已经删掉，
   这条现在是保险丝而不是必需品）。 */ ?>
.mx-panel {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin: 20px 0 4px;
}
<?php /* min-width:0 是必须的：.mx-card 是 grid 项，默认 min-width:auto 会把它
   卡在 min-content（342px，被 .mx-card-note 的 white-space:nowrap 撑着），
   上面那条 1fr 于是永远收不到 333，整页被顶到 401。
   宽屏下这张卡本来就比基础尺寸宽，不生效。 */ ?>
@media (max-width: 900px) {
  .mx-panel { grid-template-columns: 1fr; }
  .mx-panel > .mx-card { min-width: 0; }

  <?php /* .mx-card-note 是 white-space:nowrap，min-content 就是它整行 224px，
     加上 h3 和 12px 间距，295px 的卡片头放不下，整段溢出到卡片外 47px。
     窄屏让它换行，头本身也允许把说明挤到第二行。 */ ?>
  .mx-card-hd { flex-wrap: wrap; }
  .mx-card-hd h3 { min-width: 0; }
  .mx-card-note { white-space: normal; }
}

.mx-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 15px 18px 17px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, .05);
    <?php /* 纵向 flex 只为一件事：把底部的覆盖说明用 margin-top:auto 顶到卡片底部。
       两张卡的图例行数不一样（Treatment 有 8 个桶、Developmental stage 只有 4 个），
       不这么做的话两条虚线一高一低，并排看很明显。 */ ?>
    display: flex;
    flex-direction: column;
}
.mx-card-hd {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 12px;
}
.mx-card-hd h3 { margin: 0; font-size: 15px; font-weight: 700; color: #1e293b; }
.mx-card-note { font-size: 11.5px; color:#64748b; white-space: nowrap; }
.mx-empty { margin: 0; font-size: 13px; color:#64748b; }

.mx-bar {
    display: flex;
    height: 15px;
    border-radius: 8px;
    overflow: hidden;
    background: #f1f5f9;
    gap: 1px;
}
.mx-seg { min-width: 2px; }

.mx-legend {
    list-style: none;
    margin: 14px 0 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
    gap: 6px 16px;
}
.mx-legend li { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: #334155; }
.mx-legend i { flex: 0 0 auto; width: 9px; height: 9px; border-radius: 3px; }
.mx-lg-name { flex: 1 1 auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mx-lg-num { font-size: 12px; color: #64748b; font-variant-numeric: tabular-nums; }
.mx-lg-pct { min-width: 44px; text-align: right; font-size: 11.5px; color:#64748b; font-variant-numeric: tabular-nums; }

<?php /* 表格单元格里的徽章 + 具体值 */ ?>
.mx-pill {
    display: inline-block;
    padding: 2px 9px;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 600;
    line-height: 1.65;
    white-space: nowrap;
}
.mx-detail {
    display: block;
    max-width: 100%;
    margin-top: 3px;
    overflow: hidden;
    font-size: 11px;
    line-height: 1.35;
    color:#64748b;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mx-na { color:#64748b; }

<?php /* 卡片底部的覆盖说明：条只画有值的部分，缺失多少必须写在脸上 */ ?>
.mx-miss {
    margin: 12px 0 0;
    margin-top: auto;   /* 顶到卡片底部，两张卡的虚线对齐 */
    padding-top: 9px;
    border-top: 1px dashed #e2e8f0;
    font-size: 11.5px;
    color:#64748b;
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

<script>
    <?php /* Referee 1 minor 1 & 3：DynamicOptionList.printOptions() 在现代浏览器里是
     * 空操作，而 initDynamicOptionLists() 会在 onLoad 时把 <select> 清空后按
     * 这里的硬编码数组重建 —— 只会把服务端渲染出来的真实列表覆盖掉。
     * 本页物种列表现在由 sample 表生成，故不再注册该组件。 */ ?>
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
                    
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li>                    
<li><a href="/microsynteny.php">Microsynteny Analysis</a></li>
<li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li>
<li><a href="/mitdata.php">Mitogenomic Data</a></li>
                </ul>
            <li><a href="#" class="current">Transcriptome</a>
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Transcriptomic Samples</b></legend>
<p class="paleo-intro">
    A comprehensive collection of <strong>15,642 transcriptomic samples</strong> across different adult tissues and developmental stages. Select a species to explore its transcriptomic data.
</p>

<div class="gd-notice gd-info">
    <b>Looking for assembled transcriptomes?</b> The raw reads listed below are also available as
    assembled transcript sets with per-transcript functional annotation (UniProt, Pfam, PANTHER, InterPro, GO, KEGG)
    for 220 species &mdash; <?php /* 220 里有 12 个不是 de novo 拼出来的（来源是基因组注释），
    原句一律说成 "de novo assemblies"，与 /trans_assembly.php 页面上「208 assembled de novo …
    and 12 whose transcript set is taken from the genome annotation」自相矛盾。 */ ?>208
    assembled de novo from these reads and 12 whose transcript set is taken from the genome annotation
    instead &mdash; see <a href="/trans_assembly.php" style="color:#1d4ed8;font-weight:600;">Transcriptome Assembly</a>.
</div>

<?php if ($__speciesNotice !== ''): ?>
    <div class="gd-notice gd-info"><?= $__speciesNotice ?></div>
<?php endif; ?>

<?php
/* 类群 => 物种 的映射，给下面的联动脚本用。$__allSpecies 是「学名 => 类群」
   （取自 sample 表、按学名排序），这里按类群分组。组内顺序与服务端渲染
   $__speciesList 时遍历 $__allSpecies 的顺序一致，所以开不开 JS 看到的
   物种顺序都一样。 */
$__speciesByClass = array();
foreach ($__allSpecies as $__sp => $__cl) {
    if ($__cl === '' || $__cl === null) { continue; }
    $__speciesByClass[$__cl][] = $__sp;
}
?>
<?php /* 物种选择表单 */ ?>
<div class="selection-form">
    <form name="atidsearch" method="post" onSubmit="return checkquery()" encType="multipart/form-data">
        <div class="selection-row">
            <div class="selection-field sf-class">
                <label for="classSelect">Class</label>
                <select name="class" id="classSelect">
                    <?= cnido_options($__classes, $__class) ?>
                </select>
            </div>
            <div class="selection-field sf-species">
                <label for="speciesSelect">Species</label>
                <select name="species" id="speciesSelect">
                    <?= cnido_options(array_keys($__speciesList), $specie) ?>
                </select>
            </div>
            <div class="selection-field sf-submit">
                <input type="submit" value="View Samples" class="submit-btn"/>
            </div>
        </div>
    </form>
</div>

<script>
<?php /* Class 与 Species 联动：换类群立刻把物种框重建为该类群的物种（默认选第一个）。
   这里不自动提交 —— 使用者挑完物种再点 View Samples，换类群时页面不会突然跳走。
   服务端在提交时仍会校验两者是否匹配（物种不属于所选类群就退回该类群的第一个物种），
   所以即使浏览器禁用 JS，两个框也不会出现「类群和物种对不上」的情况。 */ ?>
(function () {
    var byClass = <?= json_encode($__speciesByClass, JSON_UNESCAPED_UNICODE) ?>;
    var classSel = document.getElementById('classSelect');
    var speciesSel = document.getElementById('speciesSelect');
    if (!classSel || !speciesSel) { return; }
    classSel.addEventListener('change', function () {
        var list = byClass[classSel.value] || [];
        speciesSel.innerHTML = '';
        for (var i = 0; i < list.length; i++) {
            var o = document.createElement('option');
            o.value = list[i];
            o.textContent = list[i];
            speciesSel.appendChild(o);
        }
        if (speciesSel.options.length) { speciesSel.selectedIndex = 0; }
    });
})();
</script>

<?php /* 搜索框 */ ?>
<div class="search-box">
    <input type="text" id="searchInput" placeholder="Search samples by Experiment, Tissue, Stage, or Treatment...">
</div>

<?php
// ========== 分页逻辑 ==========
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}

// 获取总记录数
$specieEsc = mysqli_real_escape_string($conn, $specie);
$count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM sample WHERE Latin_name = '$specieEsc'");
$count_result = mysqli_fetch_assoc($count_query);
$total_records = $count_result['total'];

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

/* 服务端排序。sample 表九列全是 text、整张表一个索引都没有，默认项写 null ＝
   不生成 ORDER BY，初始次序与加按钮前逐字节一致。
   每一列都给了箭头。Class 与 Species 两列的箭头点了看不出变化 —— WHERE 把物种钉成
   了查询参数那一个值（Class 又由物种决定），整页所有行都相同；但它们确实各是表里的
   一列，点了就是「按这一列排」，不是假按钮，只是当前筛选下退化。
   表头显示顺序和 SELECT * 的下标顺序并不一致（"Run" 一列取的是下标 4，也就是
   Experiment_Accession —— 那是 SRX 不是 SRR），$__sortIdx 按**列名**映射，不受影响。 */
require_once __DIR__ . '/../includes/sort_head.php';
$__sortKeys = array(
	'default' => null,
	'run'     => cnido_sort_txt('Experiment_Accession'),
	'class'   => cnido_sort_txt('`Class`'),
	'species' => cnido_sort_txt('Latin_name'),
	'project' => cnido_sort_txt('Project_ID'),
	'sra'     => cnido_sort_txt('Study_Accession'),
	'layout'  => cnido_sort_txt('Layout'),
	'tissue'  => cnido_sort_txt('tissue'),
	'dev'     => cnido_sort_txt('dev_stage'),
	'treat'   => cnido_sort_txt('Treatment'),
);
/* sample 表没有主键，Experiment_Accession 在同一个物种内也会重复（19 个物种如此），
   所以并列键要把九列全列上才能构成全序 —— 只按一列排 + LIMIT，同一批行会在翻页
   边界上重复或消失。 */
$__tie = array('Experiment_Accession', 'Study_Accession', 'Project_ID', 'Layout',
               'tissue', 'dev_stage', 'Treatment', 'Latin_name', '`Class`');
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', $__tie);
$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');
/* 当前筛选条件的查询串。翻页链接与「每页条数」选择器共用，下面排序参数也挂在它后面。 */
$__filterq = cnido_qs(array('class' => $__class, 'species' => $specie));

// 查询当前页数据
$query = mysqli_query($conn, "SELECT * FROM sample WHERE Latin_name = '$specieEsc'" . $__orderSql . " LIMIT $offset, $per_page");

/* 「简易总结」面板：当前物种**全部**样本的阶段/处理构成（不只当前页），
   数据直接来自 sample 表的 GROUP BY 计数，不做任何推断 —— 库里没写的就是
   Not specified，不会被算进任何一类。 */
$__sum = cnido_meta_summary($conn, $specie);
$__sumNote = function ($which) use ($__sum, $specie) {
    if (empty($__sum[$which]) || $__sum[$which]['total'] === 0) return '';
    return 'all ' . number_format($__sum[$which]['total']) . ' samples of '
         . htmlspecialchars($specie);
};
?>

<div class="mx-panel">
    <?= cnido_meta_panel_html($__sum, 'dev',   'Developmental stage', $__sumNote('dev')) ?>
    <?= cnido_meta_panel_html($__sum, 'treat', 'Treatment',           $__sumNote('treat')) ?>
</div>

<?php /* 表格容器 */ ?>
<div class="table-container">
    <table class="gridtable" id="myTable">
        <tr>
            <?php /* 表头原先写 "Run"，格子里印的却是 $result[4] = Experiment_Accession
                     （DRX/ERX/SRX 号，不是 SRR 的 Run 号）—— 上面 $__sortKeys 的注释早就
                     指出这一点，但表头一直没跟着改。Metagenomic Data 页同一列已经改成
                     "Experiment"，两页显示的是同一张 sample 表，标签必须一致。
                     排序键仍叫 'run'（不改成 'exp'）：改键名会让已存在的 ?sort=run 链接
                     静默落到默认次序上。 */ ?>
            <th><?php echo cnido_sort_link('run', 'Experiment', $__sort, $__dir, $__filterq); ?></th>
            <th><?php echo cnido_sort_link('class', 'Class', $__sort, $__dir, $__filterq); ?></th>
            <th><?php echo cnido_sort_link('species', 'Species', $__sort, $__dir, $__filterq); ?></th>
            <th><?php echo cnido_sort_link('project', 'BioProject', $__sort, $__dir, $__filterq); ?></th>
            <th><?php echo cnido_sort_link('sra', 'SRA', $__sort, $__dir, $__filterq); ?></th>
            <th><?php echo cnido_sort_link('layout', 'Layout', $__sort, $__dir, $__filterq); ?></th>
            <?php /* Tissue 是自由文本（最长 94 字），居中难看，显式左对齐。dev/treatment
                     两列走的是「徽章 + 小字」的紧凑写法，继续保持默认居中。 */ ?>
            <th class="tal"><?php echo cnido_sort_link('tissue', 'Tissue', $__sort, $__dir, $__filterq); ?></th>
            <th width="10%"><?php echo cnido_sort_link('dev', 'Developmental Stage', $__sort, $__dir, $__filterq); ?></th>
            <th width="20%"><?php echo cnido_sort_link('treat', 'Treatment', $__sort, $__dir, $__filterq); ?></th>
        </tr>
        <?php
		/* dev_stage / Treatment 两列改成「归类徽章 + 具体值」：徽章给出简易归类，
		   下面一行小字是修复后的原始取值（完整原文在 title 上）。原始文本仍然出现
		   在单元格里，所以上面那个纯前端的搜索框（按行 text() 过滤）照样能用。 */
		while ($result = mysqli_fetch_row($query)) {
			$__dRaw = $result[7] === null ? '' : $result[7];
			$__tRaw = $result[8] === null ? '' : $result[8];
			$__dPill = cnido_meta_pill_html(cnido_meta_dev($__dRaw),   cnido_meta_repair($__dRaw));
			$__tPill = cnido_meta_pill_html(cnido_meta_treat($__tRaw), cnido_meta_repair($__tRaw));
			/* Tissue 是同一张 sample 表的自由文本列，缺失在库里写成 '-'（1,239/15,642 行）。
			   原先原样印出来：同一行里 dev/treatment 缺失显示破折号，tissue 却是一个看着像
			   真值的连字符 —— 三种缺失、两种样子。这里补上，用同页 .mx-na 的写法。 */
			$__tisRaw = $result[6] === null ? '' : trim((string)$result[6]);
			$__tis = ($__tisRaw === '' || $__tisRaw === '-')
			       ? '<span class="mx-na" title="no value in the sample table">—</span>'
			       : htmlspecialchars($__tisRaw, ENT_QUOTES, 'UTF-8');
			echo "<tr align=\"center\"><td><a href=\"https://www.ncbi.nlm.nih.gov/sra/?term=$result[4]\" target=\"_blank\"><img src=\"../images/NCBI.png\" width=\"12px\" height=\"12px\">&nbsp;$result[4]</a></td><td>$result[0]</td><td>$result[1]</td><td><a href=\"https://www.ncbi.nlm.nih.gov/bioproject/$result[2]\" target=\"_blank\">$result[2]</a></td><td><a href=\"https://www.ncbi.nlm.nih.gov/Traces/study/?acc=$result[3]&o=acc_s%3Aa\" target=\"_blank\">$result[3]</a></td><td>$result[5]</td><td class=\"tal\">$__tis</td><td>$__dPill</td><td>$__tPill</td></tr>";
		}
		?>
    </table>
</div>

<?php
/* 翻页链接原来只带 per_page 与 page。物种筛选之所以还「看起来能用」，是因为
   cnido_state() 把选择写进了会话，下一页从会话里把它读回来 —— 于是同一个浏览
   器里开两个标签页、各看一个物种，翻页就会互相串到对方的物种上；把翻页后的
   URL 发给别人，打开的也永远是默认物种。筛选条件写进链接后，URL 自己就是完整
   的状态，不依赖会话。 */
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
            <?php /* $total_records 是**本物种**的行数（SELECT COUNT(*) FROM sample WHERE
                   Latin_name = '$specie'，见上面的 $count_query），而同一页的引言写的是
                   全站 15,642 个样本。原来标签写「Total Transcriptomic Samples」却在
                   Hydra 下印 1,256，读者会以为库里只有一千多个样本。标签里点明物种。 */ ?>
            📊 Transcriptomic samples for <i><?= htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') ?></i>: <?= number_format((int)$total_records) ?>
        </div>

        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='<?= $__self ?>?<?= $__baseQs ?><?= $__baseQs !== '' ? '&' : '' ?>per_page='+this.value+'&page=1'">
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
        <button onclick="window.location.href='<?= $__self ?>?<?= $__baseQs ?><?= $__baseQs !== '' ? '&' : '' ?>per_page=<?= (int)$per_page ?>&page='+document.getElementById('gotoPage').value">
            Go
        </button>
        <span>of <?= $total_pages ?> pages</span>
    </div>    <?php endif; /* $total_pages > 0 */ ?>

</div>

</div>
</div>
</div>

<script>
$(document).ready(function(){
    $("#searchInput").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#myTable tr:not(:first)").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});

// 确保选中的物种保持在下拉框中
document.addEventListener('DOMContentLoaded', function() {
    // 设置Class下拉框的选中值
    <?php /* 审稿意见 Referee 2 major 1「物种框总是显示上一次选的物种」：
     * 原实现在页面加载后用 localStorage 里的旧值覆盖服务端已经渲染好的选中项
     * （而且是 100ms 后覆盖，正好把服务端状态盖掉）。现在服务端状态优先：
     * 只有当 URL 和会话都没有指定物种、浏览器落到默认值时才咨询 localStorage，
     * 而且必须确认该值在当前 <select> 里真实存在。 */ ?>
    const sel = document.querySelector('select[name="species"]');
    const phpSelectedSpecies = <?= json_encode($specie) ?>;
    const fromServer = <?= json_encode($__st['specie__from']) ?>;

    if (sel && fromServer === 'default') {
        const savedSpecies = localStorage.getItem('selectedSpecies');
        if (savedSpecies && Array.prototype.some.call(sel.options, o => o.value === savedSpecies)) {
            sel.value = savedSpecies;
        }
    }
});

// 保存选择到localStorage（仅作为下次访问的默认值，不再覆盖服务端选择）
document.querySelector('form').addEventListener('submit', function() {
    const alg = document.querySelector('select[name="class"]');
    const sp  = document.querySelector('select[name="species"]');
    if (alg) { localStorage.setItem('selectedAlg', alg.value); }
    if (sp)  { localStorage.setItem('selectedSpecies', sp.value); }
});
</script>

<?php
    include "../Webpage_components.php";
    print $footer;
?>
</body>
</html>
