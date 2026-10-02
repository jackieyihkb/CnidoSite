<?php
/* 审稿意见 Referee 2 major 1：
 * 本页原来在「非 POST」请求里一律把物种硬编码成 LPERT 并 unset($_SESSION['species'])，
 * 于是 species_portal.php 之类的 ?species= 深链永远进不来；而 $_SESSION['species']
 * 还是各模块共用的键（值域并不一致，表观组等页存的是拉丁名）。
 * 现改用 cnido_state()：GET（深链）> POST（表单）> 本模块会话 > 默认值。 */
require_once __DIR__ . '/includes/state.php';

$__st = cnido_state('jbrowse', array(
    'specie' => array('get' => 'species', 'default' => 'NVECT'),
));
$species = $__st['specie'];

$__conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
$__abbrLatin = array();   // abbr1 => 拉丁名
$__latinAbbr = array();   // 拉丁名 => abbr1
if (!$__conn->connect_error) {
    if ($__q = mysqli_query($__conn, "SELECT abbr1, species FROM abbr")) {
        while ($__r = mysqli_fetch_row($__q)) {
            if ($__r[0] === null || $__r[0] === '') { continue; }
            $__abbrLatin[$__r[0]] = $__r[1];
            if (!isset($__latinAbbr[$__r[1]])) { $__latinAbbr[$__r[1]] = $__r[0]; }
        }
    }
}
/* 深链可能带的是拉丁名（其它模块的词汇），先折成 abbr1 短码再找目录。 */
if ($species !== '' && !isset($__abbrLatin[$species]) && isset($__latinAbbr[$species])) {
    $species = $__latinAbbr[$species];
}

/* jbrowse/ 下只有部分物种真正导出了浏览器目录（目录名 = abbr1 短码），
 * 传进来的物种若没有目录，iframe 只会加载到一个 404 —— 必须给出提示。 */
$__jbDirs = array();
foreach ((array)@scandir(__DIR__ . '/jbrowse') as $__d) {
    if ($__d === '.' || $__d === '..') { continue; }
    if (is_file(__DIR__ . '/jbrowse/' . $__d . '/trackList.json')
        || is_file(__DIR__ . '/jbrowse/' . $__d . '/1.conf')) { $__jbDirs[] = $__d; }
}
$__hasBrowser = in_array($species, $__jbDirs, true);

/* 下拉框里现有的候选值（与下面静态 <option> 一一对应，顺序相同）；
 * 只用来判断深链带来的物种是否需要补一项，避免 <select> 停在列表第一项、
 * 与 iframe 实际加载的物种对不上。 */
$__jbList = array(
    'AACUM','AAUST','AAWI','ACERV','ACYTH','ADIGI','AECHI','AFLOR','AGEMM','AHEMP','AHYAC','AINTE',
    'ALORI','AMICR','AMILL','AMURI','ANASU','APALM','APULC','ASELA','ASPAT','ATENU','AYONG','AEQUI',
    'AMEDI','ATENE','ALIUI','AIDSS','AXANT','APOCU','AMYRI','ACOER','BWELL','CCOCK','CJARD','CMOSA',
    'CQUIN','CHEMI','CNATA','CGIGA','CSALA','DCYLI','DGIGA','DCRIB','DPERT','DLINE','DAXIF','EHORR',
    'EELEG','ECAVO','EVERR','EDIAP','FANCO','GFASC','HOCTO','HCOER','HIMPE','HSALM','HOLIG','HVIRI',
    'HVULG','HECHI','HSYMB','LSARM','LSCAB','LPERT','MAURE','MSENA','MPAPU','MMEAN','MSENI','MLORD',
    'MALCI','MCOMP','MDICH','MCACT','MCAPI','MCAPR','MEFFL','MFOLI','MGRIS','MVIRU','MMURI','MHONG',
    'MSQUA','NSEPT','NVECT','NNOMU','OARBU','OPATA','OFAVE','OFRAN','PSPEC','PMIZI','PUMBR','PPAPI',
    'PCLAV','PXISH','PNOCT','PPENN','PACUT','PDAMI','PMEAN','PVERR','PCRUS','PAUST','PCOMP','PCYLI',
    'PDIVA','PEVER','PHARR','PLOBA','PLUTE','PRUS','PGRIS','ROSCU','RESCU','RFLOR','SMALA','SCALL',
    'SRADI','SSIDE','SINTE','SPIST','TSP','TSTEP','TKITA','TMAIP','TCOCC','TRENI','TDOHR','TRUBR',
    'XSP',
    /* 2026-09-29 补：jbrowse/ 下有这 12 个物种的浏览器目录（判据同上面的 $__jbDirs：
       有 trackList.json 或 1.conf），深链 jbrowse.php?species=<码> 一直能打开、
       也不带 "(unavailable)" 后缀，但这份手写名单和下面的静态 <option> 里没有它们，
       所以从下拉框里选不到 —— 下拉框 133 项、磁盘上 145 个目录，差的正好是这 12 个。 */
    'AAURI1','AAURI2','ASP1','ASP2','ASP3','BCFM','CGRAC1','CGRAC2','CSP1','CSP2','PSINE1','PSINE2',
);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>JBrowse Visualization - CnidoSite</title>
<meta name="keywords" content="Cnidaria, JBrowse, genome browser, visualization, genomic data" />
<meta name="description" content="Interactive genome browser for visualizing genomic features across Cnidaria species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<style>
<?php /* JBrowse页面专用样式 - 不影响全局CSS */ ?>
.jbrowse-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.jbrowse-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.jbrowse-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.tools-badge {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

<?php /* 选择表单美化 */ ?>
.selection-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 30px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.selection-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.selection-title::before {
    content: "🧬";
    font-size: 24px;
}

.selection-form {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: flex-end;
}

.form-group {
    flex: 1;
    min-width: 300px;
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
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

<?php /* 当前选择显示 */ ?>
.current-selection {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    border-left: 4px solid #3b82f6;
    padding: 20px 25px;
    border-radius: 8px;
    margin: 25px auto;
    max-width: 800px;
    color: #1e40af;
    line-height: 1.7;
}

.current-selection-icon {
    font-size: 24px;
    margin-bottom: 10px;
}

.current-selection-text {
    font-size: 16px;
}

.current-selection-highlight {
    font-weight: 700;
    color: #1e3a8a;
}

<?php /* 浏览器容器美化 */ ?>
.browser-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 25px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.browser-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 18px 25px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.browser-header::before {
    content: "🧭";
    font-size: 20px;
}

.browser-frame {
    width: 100%;
    height: 800px;
    border: none;
    border-radius: 0 0 12px 12px;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .jbrowse-header {
        padding: 20px;
    }
    
    .jbrowse-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .selection-container {
        padding: 25px 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .selection-form {
        flex-direction: column;
    }
    
    .form-group {
        min-width: 100%;
    }
    
    .browser-container {
        margin-left: 15px;
        margin-right: 15px;
        padding: 20px;
    }
    
    .browser-frame {
        height: 600px;
    }
    
    .current-selection {
        margin-left: 15px;
        margin-right: 15px;
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
            <li><a href="#">Metagenome</a>
                <ul>
                    <li><a href="/metagenomic_data.php">Metagenomic Data</a></li>
                    <li><a href="/MAGs.php">MAGs Catalog</a></li>
                </ul>
            </li>
			<li><a href="#">Phenotype</a>
				<ul>
					<li><a href="/phenotype.php?class=all">All</a></li>
					<li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li>
					<li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li>
					<li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li>
					<li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li>
					<li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li>
				</ul>
			</li>
            <li><a href="#" class="current">Tools</a>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>JBrowse Visualization</b></legend>
<p class="paleo-intro">Interactive genome browser for visualizing genomic features, gene structures, and annotations across Cnidarian species.</p>

<?php /* 物种选择表单 */ ?>
<div class="selection-container">
    <div class="selection-title">Select Species for Visualization</div>
    <form name="atidsearch" method="post" onSubmit="return checkquery()" encType="multipart/form-data" class="selection-form">
        <div class="form-group">
            <label class="form-label">Species</label>
            <select name="species" class="form-select" id="speciesSelect">
<?php
/* 深链带来的物种若不在候选列表里，先补一项（拉丁名能从 abbr 表查到就用它做标签），
 * 否则 <select> 会停在列表第一项，与 iframe 实际加载的物种对不上。 */
if ($species !== '' && !in_array($species, $__jbList, true)) {
    $__lbl = isset($__abbrLatin[$species]) ? $__abbrLatin[$species] : cnido_latin($species);
    echo '<option value="' . htmlspecialchars($species) . '" selected="selected">'
       . htmlspecialchars($__lbl) . ($__hasBrowser ? '' : ' (unavailable)') . "</option>\n";
}
?>
                <option value="AACUM" <?= ($species == "AACUM") ? 'selected' : '' ?>>Acropora acuminata</option>
                <option value="AAUST" <?= ($species == "AAUST") ? 'selected' : '' ?>>Acropora austera</option>
                <option value="AAWI" <?= ($species == "AAWI") ? 'selected' : '' ?>>Acropora awi</option>
                <option value="ACERV" <?= ($species == "ACERV") ? 'selected' : '' ?>>Acropora cervicornis</option>
                <option value="ACYTH" <?= ($species == "ACYTH") ? 'selected' : '' ?>>Acropora cytherea</option>
                <option value="ADIGI" <?= ($species == "ADIGI") ? 'selected' : '' ?>>Acropora digitifera</option>
                <option value="AECHI" <?= ($species == "AECHI") ? 'selected' : '' ?>>Acropora echinata</option>
                <option value="AFLOR" <?= ($species == "AFLOR") ? 'selected' : '' ?>>Acropora florida</option>
                <option value="AGEMM" <?= ($species == "AGEMM") ? 'selected' : '' ?>>Acropora gemmifera</option>
                <option value="AHEMP" <?= ($species == "AHEMP") ? 'selected' : '' ?>>Acropora hemprichii</option>
                <option value="AHYAC" <?= ($species == "AHYAC") ? 'selected' : '' ?>>Acropora hyacinthus</option>
                <option value="AINTE" <?= ($species == "AINTE") ? 'selected' : '' ?>>Acropora intermedia</option>
                <option value="ALORI" <?= ($species == "ALORI") ? 'selected' : '' ?>>Acropora loripes</option>
                <option value="AMICR" <?= ($species == "AMICR") ? 'selected' : '' ?>>Acropora microphthalma</option>
                <option value="AMILL" <?= ($species == "AMILL") ? 'selected' : '' ?>>Acropora millepora</option>
                <option value="AMURI" <?= ($species == "AMURI") ? 'selected' : '' ?>>Acropora muricata</option>
                <option value="ANASU" <?= ($species == "ANASU") ? 'selected' : '' ?>>Acropora nasuta</option>
                <option value="APALM" <?= ($species == "APALM") ? 'selected' : '' ?>>Acropora palmata</option>
                <option value="APULC" <?= ($species == "APULC") ? 'selected' : '' ?>>Acropora pulchra</option>
                <option value="ASELA" <?= ($species == "ASELA") ? 'selected' : '' ?>>Acropora selago</option>
                <option value="ASPAT" <?= ($species == "ASPAT") ? 'selected' : '' ?>>Acropora spathulata</option>
                <option value="ATENU" <?= ($species == "ATENU") ? 'selected' : '' ?>>Acropora tenuis</option>
                <option value="AYONG" <?= ($species == "AYONG") ? 'selected' : '' ?>>Acropora yongei</option>
                <option value="ASP1" <?= ($species == "ASP1") ? 'selected' : '' ?>>Actinernus sp. WN-2022</option>
                <option value="AEQUI" <?= ($species == "AEQUI") ? 'selected' : '' ?>>Actinia equina</option>
                <option value="AMEDI" <?= ($species == "AMEDI") ? 'selected' : '' ?>>Actinia mediterranea</option>
                <option value="ATENE" <?= ($species == "ATENE") ? 'selected' : '' ?>>Actinia tenebrosa</option>
                <option value="ALIUI" <?= ($species == "ALIUI") ? 'selected' : '' ?>>Actinoscyphia liui</option>
                <option value="ASP2" <?= ($species == "ASP2") ? 'selected' : '' ?>>Actinostola sp. cb2023</option>
                <option value="AIDSS" <?= ($species == "AIDSS") ? 'selected' : '' ?>>Alvinactis idsseensis sp. nov.</option>
                <option value="AXANT" <?= ($species == "AXANT") ? 'selected' : '' ?>>Anthopleura xanthogrammica</option>
                <option value="APOCU" <?= ($species == "APOCU") ? 'selected' : '' ?>>Astrangia poculata</option>
                <option value="AMYRI" <?= ($species == "AMYRI") ? 'selected' : '' ?>>Astreopora myriophthalma</option>
                <option value="AAURI1" <?= ($species == "AAURI1") ? 'selected' : '' ?>>Aurelia aurita</option>
                <option value="AAURI2" <?= ($species == "AAURI2") ? 'selected' : '' ?>>Aurelia aurita complex sp. Pacific</option>
                <option value="ACOER" <?= ($species == "ACOER") ? 'selected' : '' ?>>Aurelia coerulea</option>
                <option value="ASP3" <?= ($species == "ASP3") ? 'selected' : '' ?>>Aurelia sp. 4 Dawson et al 2005</option>
                <option value="BWELL" <?= ($species == "BWELL") ? 'selected' : '' ?>>Blastomussa wellsi</option>
                <option value="BCFM" <?= ($species == "BCFM") ? 'selected' : '' ?>>Bougainvillia cf. muscus</option>
                <option value="CGRAC1" <?= ($species == "CGRAC1") ? 'selected' : '' ?>>Callogorgia gracilis</option>
                <option value="CCOCK" <?= ($species == "CCOCK") ? 'selected' : '' ?>>Candelabrum cocksii</option>
                <option value="CSP2" <?= ($species == "CSP2") ? 'selected' : '' ?>>Cassiopea sp. PORT0000214</option>
                <option value="CJARD" <?= ($species == "CJARD") ? 'selected' : '' ?>>Catalaphyllia jardinei</option>
                <option value="CMOSA" <?= ($species == "CMOSA") ? 'selected' : '' ?>>Catostylus mosaicus</option>
                <option value="CQUIN" <?= ($species == "CQUIN") ? 'selected' : '' ?>>Chrysaora quinquecirrha</option>
                <option value="CSP1" <?= ($species == "CSP1") ? 'selected' : '' ?>>Chrysogorgia sp. JL179-B06</option>
                <option value="CGRAC2" <?= ($species == "CGRAC2") ? 'selected' : '' ?>>Cladopsammia gracilis</option>
                <option value="CHEMI" <?= ($species == "CHEMI") ? 'selected' : '' ?>>Clytia hemisphaerica</option>
                <option value="CNATA" <?= ($species == "CNATA") ? 'selected' : '' ?>>Colpophyllia natans</option>
                <option value="CGIGA" <?= ($species == "CGIGA") ? 'selected' : '' ?>>Condylactis gigantea</option>
                <option value="CSALA" <?= ($species == "CSALA") ? 'selected' : '' ?>>Cyphastrea salae</option>
                <option value="DCYLI" <?= ($species == "DCYLI") ? 'selected' : '' ?>>Dendrogyra cylindrus</option>
                <option value="DGIGA" <?= ($species == "DGIGA") ? 'selected' : '' ?>>Dendronephthya gigantea</option>
                <option value="DCRIB" <?= ($species == "DCRIB") ? 'selected' : '' ?>>Dendrophyllia cribrosa</option>
                <?php /* DPERT 的学名是 Desmophyllum pertusum（abbr 表：DPERT →
                         Desmophyllum pertusum，LPERT → Lophelia pertusa），这里原来抄成了
                         下一行 LPERT 的名字，于是下拉框里出现两个一模一样的
                         "Lophelia pertusa"，而 D. pertusum 一个入口都没有。按本列表的
                         排序位置也能看出原意是 Desmophyllum：它被排在 Dendrophyllia
                         cribrosa 与 Diadumene lineata 之间，正是 Den… < Des… < Dia… 的位置。
                         tutorial.php 已说明这两个名字是同物异名（NCBI TaxID 均为 174260），
                         所以两个入口都指向同一个基因组，只是索引层级不同。 */ ?>
                <option value="DPERT" <?= ($species == "DPERT") ? 'selected' : '' ?>>Desmophyllum pertusum</option>
                <option value="DLINE" <?= ($species == "DLINE") ? 'selected' : '' ?>>Diadumene lineata</option>
                <option value="DAXIF" <?= ($species == "DAXIF") ? 'selected' : '' ?>>Duncanopsammia axifuga</option>
                <option value="EHORR" <?= ($species == "EHORR") ? 'selected' : '' ?>>Echinopora horrida</option>
                <option value="EELEG" <?= ($species == "EELEG") ? 'selected' : '' ?>>Edwardsia elegans</option>
                <option value="ECAVO" <?= ($species == "ECAVO") ? 'selected' : '' ?>>Eunicella cavolini</option>
                <option value="EVERR" <?= ($species == "EVERR") ? 'selected' : '' ?>>Eunicella verrucosa</option>
                <option value="EDIAP" <?= ($species == "EDIAP") ? 'selected' : '' ?>>Exaiptasia diaphana</option>
                <option value="FANCO" <?= ($species == "FANCO") ? 'selected' : '' ?>>Fimbriaphyllia ancora</option>
                <option value="GFASC" <?= ($species == "GFASC") ? 'selected' : '' ?>>Galaxea fascicularis</option>
                <option value="HOCTO" <?= ($species == "HOCTO") ? 'selected' : '' ?>>Haliclystus octoradiatus</option>
                <option value="HCOER" <?= ($species == "HCOER") ? 'selected' : '' ?>>Heliopora coerulea</option>
                <option value="HIMPE" <?= ($species == "HIMPE") ? 'selected' : '' ?>>Hemicorallium imperiale</option>
                <option value="HSALM" <?= ($species == "HSALM") ? 'selected' : '' ?>>Henneguya salminicola</option>
                <option value="HOLIG" <?= ($species == "HOLIG") ? 'selected' : '' ?>>Hydra oligactis</option>
                <option value="HVIRI" <?= ($species == "HVIRI") ? 'selected' : '' ?>>Hydra viridissima</option>
                <option value="HVULG" <?= ($species == "HVULG") ? 'selected' : '' ?>>Hydra vulgaris</option>
                <option value="HECHI" <?= ($species == "HECHI") ? 'selected' : '' ?>>Hydractinia echinata</option>
                <option value="HSYMB" <?= ($species == "HSYMB") ? 'selected' : '' ?>>Hydractinia symbiolongicarpus</option>
                <option value="LSARM" <?= ($species == "LSARM") ? 'selected' : '' ?>>Leptogorgia sarmentosa</option>
                <option value="LSCAB" <?= ($species == "LSCAB") ? 'selected' : '' ?>>Leptoseris scabra</option>
                <option value="LPERT" <?= ($species == "LPERT") ? 'selected="selected"' : '' ?>>Lophelia pertusa</option>
                <option value="MAURE" <?= ($species == "MAURE") ? 'selected' : '' ?>>Madracis auretenra</option>
                <option value="MSENA" <?= ($species == "MSENA") ? 'selected' : '' ?>>Madracis senaria</option>
                <option value="MPAPU" <?= ($species == "MPAPU") ? 'selected' : '' ?>>Mastigias papua</option>
                <option value="MMEAN" <?= ($species == "MMEAN") ? 'selected' : '' ?>>Meandrina meandrites</option>
                <option value="MSENI" <?= ($species == "MSENI") ? 'selected' : '' ?>>Metridium senile</option>
                <option value="MLORD" <?= ($species == "MLORD") ? 'selected' : '' ?>>Micromussa lordhowensis</option>
                <option value="MALCI" <?= ($species == "MALCI") ? 'selected' : '' ?>>Millepora alcicornis</option>
                <option value="MCOMP" <?= ($species == "MCOMP") ? 'selected' : '' ?>>Millepora complanata</option>
                <option value="MDICH" <?= ($species == "MDICH") ? 'selected' : '' ?>>Millepora dichotoma</option>
                <option value="MCACT" <?= ($species == "MCACT") ? 'selected' : '' ?>>Montipora cactus</option>
                <option value="MCAPI" <?= ($species == "MCAPI") ? 'selected' : '' ?>>Montipora capitata</option>
                <option value="MCAPR" <?= ($species == "MCAPR") ? 'selected' : '' ?>>Montipora capricornis</option>
                <option value="MEFFL" <?= ($species == "MEFFL") ? 'selected' : '' ?>>Montipora efflorescens</option>
                <option value="MFOLI" <?= ($species == "MFOLI") ? 'selected' : '' ?>>Montipora foliosa</option>
                <option value="MGRIS" <?= ($species == "MGRIS") ? 'selected' : '' ?>>Montipora grisea</option>
                <option value="MVIRU" <?= ($species == "MVIRU") ? 'selected' : '' ?>>Morbakka virulenta</option>
                <option value="MMURI" <?= ($species == "MMURI") ? 'selected' : '' ?>>Muricea muricata</option>
                <option value="MHONG" <?= ($species == "MHONG") ? 'selected' : '' ?>>Myxobolus honghuensis</option>
                <option value="MSQUA" <?= ($species == "MSQUA") ? 'selected' : '' ?>>Myxobolus squamalis</option>
                <option value="NSEPT" <?= ($species == "NSEPT") ? 'selected' : '' ?>>Nanomia septata</option>
                <option value="NVECT" <?= ($species == "NVECT") ? 'selected' : '' ?>>Nematostella vectensis</option>
                <option value="NNOMU" <?= ($species == "NNOMU") ? 'selected' : '' ?>>Nemopilema nomurai</option>
                <option value="OARBU" <?= ($species == "OARBU") ? 'selected' : '' ?>>Oculina arbuscula</option>
                <option value="OPATA" <?= ($species == "OPATA") ? 'selected' : '' ?>>Oculina patagonica</option>
                <option value="OFAVE" <?= ($species == "OFAVE") ? 'selected' : '' ?>>Orbicella faveolata</option>
                <option value="OFRAN" <?= ($species == "OFRAN") ? 'selected' : '' ?>>Orbicella franksi</option>
                <option value="PSPEC" <?= ($species == "PSPEC") ? 'selected' : '' ?>>Pachyseris speciosa</option>
                <option value="PMIZI" <?= ($species == "PMIZI") ? 'selected' : '' ?>>Palythoa mizigama</option>
                <option value="PUMBR" <?= ($species == "PUMBR") ? 'selected' : '' ?>>Palythoa umbrosa</option>
                <option value="PSINE1" <?= ($species == "PSINE1") ? 'selected' : '' ?>>Paracondylactis sinensis</option>
                <option value="PPAPI" <?= ($species == "PPAPI") ? 'selected' : '' ?>>Paragorgia papillata</option>
                <option value="PCLAV" <?= ($species == "PCLAV") ? 'selected' : '' ?>>Paramuricea clavata</option>
                <option value="PXISH" <?= ($species == "PXISH") ? 'selected' : '' ?>>Paraphelliactis xishaensis sp. nov.</option>
                <option value="PNOCT" <?= ($species == "PNOCT") ? 'selected' : '' ?>>Pelagia noctiluca</option>
                <option value="PSINE2" <?= ($species == "PSINE2") ? 'selected' : '' ?>>Platygyra sinensis</option>
                <option value="PPENN" <?= ($species == "PPENN") ? 'selected' : '' ?>>Plumapathes pennacea</option>
                <option value="PACUT" <?= ($species == "PACUT") ? 'selected' : '' ?>>Pocillopora acuta</option>
                <option value="PDAMI" <?= ($species == "PDAMI") ? 'selected' : '' ?>>Pocillopora damicornis</option>
                <option value="PMEAN" <?= ($species == "PMEAN") ? 'selected' : '' ?>>Pocillopora meandrina</option>
                <option value="PVERR" <?= ($species == "PVERR") ? 'selected' : '' ?>>Pocillopora verrucosa</option>
                <option value="PCRUS" <?= ($species == "PCRUS") ? 'selected' : '' ?>>Podabacia crustacea</option>
                <option value="PAUST" <?= ($species == "PAUST") ? 'selected' : '' ?>>Porites australiensis</option>
                <option value="PCOMP" <?= ($species == "PCOMP") ? 'selected' : '' ?>>Porites compressa</option>
                <option value="PCYLI" <?= ($species == "PCYLI") ? 'selected' : '' ?>>Porites cylindrica</option>
                <option value="PDIVA" <?= ($species == "PDIVA") ? 'selected' : '' ?>>Porites divaricata</option>
                <option value="PEVER" <?= ($species == "PEVER") ? 'selected' : '' ?>>Porites evermanni</option>
                <option value="PHARR" <?= ($species == "PHARR") ? 'selected' : '' ?>>Porites harrisoni</option>
                <option value="PLOBA" <?= ($species == "PLOBA") ? 'selected' : '' ?>>Porites lobata</option>
                <option value="PLUTE" <?= ($species == "PLUTE") ? 'selected' : '' ?>>Porites lutea</option>
                <option value="PRUS" <?= ($species == "PRUS") ? 'selected' : '' ?>>Porites rus</option>
                <option value="PGRIS" <?= ($species == "PGRIS") ? 'selected' : '' ?>>Pteroeides griseum</option>
                <option value="ROSCU" <?= ($species == "ROSCU") ? 'selected' : '' ?>>Rhodactis osculifera</option>
                <option value="RESCU" <?= ($species == "RESCU") ? 'selected' : '' ?>>Rhopilema esculentum</option>
                <option value="RFLOR" <?= ($species == "RFLOR") ? 'selected' : '' ?>>Ricordea florida</option>
                <option value="SMALA" <?= ($species == "SMALA") ? 'selected' : '' ?>>Sanderia malayensis</option>
                <option value="SCALL" <?= ($species == "SCALL") ? 'selected' : '' ?>>Scolanthus callimorphus</option>
                <option value="SRADI" <?= ($species == "SRADI") ? 'selected' : '' ?>>Siderastrea radians</option>
                <option value="SSIDE" <?= ($species == "SSIDE") ? 'selected' : '' ?>>Siderastrea siderea</option>
                <option value="SINTE" <?= ($species == "SINTE") ? 'selected' : '' ?>>Stephanocoenia intersepta</option>
                <option value="SPIST" <?= ($species == "SPIST") ? 'selected' : '' ?>>Stylophora pistillata</option>
                <option value="TSTEP" <?= ($species == "TSTEP") ? 'selected' : '' ?>>Telmatactis stephensoni</option>
                <option value="TKITA" <?= ($species == "TKITA") ? 'selected' : '' ?>>Thelohanellus kitauei</option>
                <option value="TSP" <?= ($species == "TSP") ? 'selected' : '' ?>>Trachythela sp. YZ-2020</option>
                <option value="TMAIP" <?= ($species == "TMAIP") ? 'selected' : '' ?>>Tripedalia maipoensis</option>
                <option value="TCOCC" <?= ($species == "TCOCC") ? 'selected' : '' ?>>Tubastraea coccinea</option>
                <option value="TRENI" <?= ($species == "TRENI") ? 'selected' : '' ?>>Turbinaria reniformis</option>
                <option value="TDOHR" <?= ($species == "TDOHR") ? 'selected' : '' ?>>Turritopsis dohrnii</option>
                <option value="TRUBR" <?= ($species == "TRUBR") ? 'selected' : '' ?>>Turritopsis rubra</option>
                <option value="XSP" <?= ($species == "XSP") ? 'selected' : '' ?>>Xenia sp. Carnegie-2017</option>
            </select>
        </div>
    </form>
</div>


<?php /* 浏览器容器 */ ?>
<?php
/* 只有 jbrowse/ 下导出了浏览器目录的物种才能打开（目前 145 个 abbr1 短码），
 * 其余物种若深链进来，给一句明确的说明，而不是嵌一个 404 的 iframe。 */
if ($__hasBrowser) {
?>
<div class="browser-container">
    <div class="browser-header">
        JBrowse Visualization
    </div>
    <iframe id="gbrowse" class="browser-frame" src="./jbrowse/index.html?data=<?= htmlspecialchars($species) ?>"></iframe>
</div>
<?php
} elseif ($species !== '') {
?>
<div class="browser-container">
    <div class="browser-header">
        JBrowse Visualization
    </div>
    <div style="padding:20px 25px;color:#475569;line-height:1.7;">
        No JBrowse assembly has been exported for
        <b><?= htmlspecialchars(isset($__abbrLatin[$species]) ? $__abbrLatin[$species] : $species) ?></b>
        (<?= htmlspecialchars($species) ?>). The interactive genome browser is available for
        <b><?= count($__jbDirs) ?></b> species only &mdash; pick one from the list above, or use the
        <a href="species_portal.php?species=<?= urlencode(isset($__abbrLatin[$species]) ? $__abbrLatin[$species] : $species) ?>">species portal</a>
        to see what else is available for this species.
    </div>
</div>
<?php
}
?>
</div>
</div>
</div>

<script>
// 纯前端版本（无需PHP会话）
document.getElementById('speciesSelect').addEventListener('change', function() {
    const species = this.value;
    const frame = document.getElementById('gbrowse');

    // 1. 更新iframe源（该物种没有浏览器目录时上面没有 iframe，直接跳过）
    if (frame) { frame.src = `./jbrowse/index.html?data=${species}`; }

    // 2. 滚动到视图
    const box = document.querySelector('.browser-container');
    if (box) { box.scrollIntoView({ behavior: 'smooth' }); }
});
</script>

<?php
    include "Webpage_components.php";
    print $footer;
?>
</body>
</html>
