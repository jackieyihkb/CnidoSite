<?php
/* ---------------------------------------------------------------------------
 * miRNA 页的物种下拉。
 *
 * 审查意见里「表观组只能选到 miRNA-seq」这条，落在这个页面上就是：它只有
 * 4 个写死的 <option>（adi / asp / hvu / nve），而这 4 个正好就是
 * mirna_metadata 表里全部有数据的物种 —— 写死的代价是，库里以后加了物种
 * 页面不会跟着变，而且这 4 个用的是三字母码（nve），与全站其它地方用的
 * abbr1（NVECT）不是一套，无法从物种门户深链过来。
 *
 * 这里改成从 mirna_metadata 读实际有数据的物种，服务端渲染；同时支持
 * ?species=<三字母码> 深链。mirna_metadata 里有一行 abbr='abbr'、
 * species='species' 的测试残留数据，按 abbr 过滤掉。
 *
 * 默认物种仍按最初的版本，取排在第一位的 Acropora digitifera（adi）。
 * --------------------------------------------------------------------------- */
require_once __DIR__ . '/includes/state.php';

/* 默认物种：Nematostella vectensis（三字母码 nve）。
   原始页面这个下拉是写死的四个 <option>，adi 排在第一位，这里一度跟着写成
   adi；但 mirna_metadata 里 nve 有 30 条记录、adi 只有 22 条，nve 是四个
   物种里数据最多的一个，也正是站点默认物种，故取 nve。 */
$__mirnaDefault = 'nve';
$__st = cnido_state('mirna', array(
    'species' => array('get' => 'species', 'default' => $__mirnaDefault),
));
$__curSpecies = $__st['species'];

$__mirnaSpecies = array();   // abbr => 拉丁名
$__conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if (!$__conn->connect_error) {
    $__q = mysqli_query($__conn,
        "SELECT abbr, MIN(species) AS sp FROM mirna_metadata
          WHERE abbr IS NOT NULL AND abbr <> '' AND abbr <> 'abbr'
          GROUP BY abbr ORDER BY MIN(species)");
    while ($__q && ($__r = mysqli_fetch_row($__q))) {
        $__mirnaSpecies[$__r[0]] = $__r[1];
    }
}
/* 深链过来的码不在库里时退回默认物种，避免下拉框空白、与服务器不一致。 */
if (!isset($__mirnaSpecies[$__curSpecies])) {
    $__curSpecies = $__mirnaDefault;
    if (!isset($__mirnaSpecies[$__curSpecies])) {
        reset($__mirnaSpecies);
        $__curSpecies = key($__mirnaSpecies);
        if ($__curSpecies === null) { $__curSpecies = ''; }
    }
}
$__mirnaCount = count($__mirnaSpecies);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>miRNA-seq Analysis - CnidoSite</title>
<meta name="keywords" content="Cnidaria, miRNA, microRNA, epigenomics, sequencing analysis" />
<meta name="description" content="Identify and analyze microRNA genes in Cnidaria species with comprehensive annotation data" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<style>
<?php /* miRNA分析页面专用样式 - 不影响全局CSS */ ?>
.mirna-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.mirna-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.mirna-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.species-stats {
    background:linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

<?php /* 表单容器美化 */ ?>
.mirna-form-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 35px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
    max-width: 600px;
}

.form-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-title::before {
    content: "🧬";
    font-size: 24px;
}

.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 10px;
}

.form-select {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 16px;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 20px;
}

.form-select:focus {
    outline: none;
    border-color:#6d28d9;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.form-select option {
    padding: 10px;
}

.button-group {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.btn-submit, .btn-reset {
    flex: 1;
    padding: 14px 30px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-submit {
    background:linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
}

.btn-reset {
    background: #f8fafc;
    color: #64748b;
    border: 2px solid #e2e8f0;
}

.btn-reset:hover {
    background: #f1f5f9;
    color: #475569;
    border-color: #cbd5e1;
}

<?php /* 物种信息卡片 */ ?>
.species-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.species-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.species-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.species-card h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.species-card p {
    margin: 0;
    font-size: 15px;
    color: #64748b;
    line-height: 1.5;
}

.species-code {
    background: #f1f5f9;
    color: #475569;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    font-family: monospace;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .mirna-header {
        padding: 20px;
    }
    
    .mirna-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .mirna-form-container {
        padding: 25px 20px;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .species-info {
        grid-template-columns: 1fr;
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>miRNA-seq Analysis</b></legend>
<p  class="paleo-intro">In this module, we provide the microRNAs identified in Cnidaria together with their annotation. Their precursor, star, mature, loop, 5p and 3p sequences and the corresponding annotation data can all be downloaded.</p>

<?php /* 表单容器 */ ?>
<div class="mirna-form-container">
    <div class="form-title">Select Species for miRNA Analysis</div>
    
    <form name="search" method="post" action="miRNA_detail.php" target="_blank" onSubmit="return checkquery()" encType="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Select Species</label>
            <select name="species" class="form-select">
<?php foreach ($__mirnaSpecies as $__code => $__latin) { ?>
                <option value="<?= htmlspecialchars($__code, ENT_QUOTES, 'UTF-8') ?>"<?= ($__code === $__curSpecies) ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__latin, ENT_QUOTES, 'UTF-8') ?></option>
<?php } ?>
            </select>
            <p class="paleo-intro" style="margin-top:8px">
                miRNA records are currently available for <b><?= (int)$__mirnaCount ?></b> species
                (listed above); more will be added as they are processed.
            </p>
        </div>
        
        <div class="button-group">
            <button type="submit" class="btn-submit">
                🔍 View miRNA Details
            </button>
            <button type="reset" class="btn-reset">
                ↺ Reset Selection
            </button>
        </div>
    </form>
</div>


<div class="cleaner"></div>
</div>
</div>
</div>

<?php
    include "Webpage_components.php";
    print $footer;
?>
</body>
</html>
