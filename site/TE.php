<?php
/* 审稿意见 Referee 2 major 1：物种门户（species_portal.php）以 ?species= 深链进本页，
 * 而本页两个物种下拉框在服务端渲染出的 HTML 里一个 option 都没有 —— 选项由
 * DynamicOptionList 在浏览器里生成，该库的 printOptions() 在现代浏览器里是空操作，
 * 深链传进来的物种因而无处可选（"species selector does not work"）。
 * 现改为服务端渲染 option，取值统一走 cnido_state()：
 * GET 深链 > POST 提交 > 会话 > 默认值，会话键按模块命名空间隔离，
 * 不再与别的模块共用 $_SESSION['species']（那些模块存的是 abbr1 代码）。 */
require_once __DIR__ . '/includes/state.php';

$__st = cnido_state('te', array(
    /* 默认物种：Nematostella vectensis（有 NVECT_TE，21 万行）。原来留空，
       <select> 没有 selected 项时浏览器自动选第一项，而列表是按拉丁名排序的，
       落到的是某个 Acropora —— 与两个例子里的基因号都不是同一个物种。 */
    'specie' => array('get' => 'species', 'default' => 'Nematostella vectensis'),
));
$specie = $__st['specie'];

/* 本模块覆盖的物种 = 有 {abbr1}_TE 表的物种，从库里发现而不是写死。
 * 这些表由 TE 注释流程产出（TE_pipeline/bin/60_publish_to_site.sh），
 * 会随流程推进陆续新增：写死的列表每加一个物种就过期一次，而 coverage.php
 * 本来就是用 information_schema 扫描 TE 表的，做法一致。
 * 取拉丁名：两个表单分别提交给 TE_gene.php / TE_region.php，它们按拉丁名查
 * abbr，故 option 的 value 用拉丁名；species 与 species1 是同一个物种选择。 */
$__list = array();
$__conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if (!$__conn->connect_error) {
    $__q = mysqli_query($__conn,
        "SELECT a.species FROM abbr a
           JOIN information_schema.tables t
             ON t.table_schema = DATABASE()
            AND t.table_name = CONCAT(a.abbr1, '_TE')
          ORDER BY a.species");
    while ($__q && ($__r = mysqli_fetch_row($__q))) { $__list[] = $__r[0]; }
    $__conn->close();
}
/* 库连不上时退回上一版写死的三个物种，页面至少还能用。 */
if (!$__list) {
    $__list = array(
        "Actinernus sp. WN-2022", "Alvinactis idsseensis sp. nov.", "Actinostola sp. cb2023",
    );
}

/* 深链给的可能是 abbr1 代码（ASP1）或下划线名，而本页下拉框和下游页面用的是
 * 拉丁名；不在列表里时先翻译，否则下拉框只会显示一个 (unavailable) 项。 */
if ($specie !== '' && !in_array($specie, $__list, true)) {
    $__conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    if (!$__conn->connect_error) {
        $__e = mysqli_real_escape_string($__conn, $specie);
        $__q = mysqli_query($__conn, "SELECT species FROM abbr WHERE abbr1 = '$__e' OR abbr = '$__e' OR species = '$__e' LIMIT 1");
        if ($__q) { $__r = mysqli_fetch_row($__q); if ($__r) { $specie = $__r[0]; } }
        $__conn->close();
    }
}
/* 类群映射取自 speciesinfo，而不是原先按注释手写的分组 —— 原列表把
   Aurelia coerulea、Aurelia sp. 4 排在 "// Hexactiniaria" 段落下，实为 Scyphozoa，
   于是类群下拉框与物种对不上。类群名统一用全站的 Hexacorallia（Referee 1 minor 1）。 */
$__classMap = cnido_species_class_map($__list);

/* 深链给的物种不在本页候选列表里 —— 本页没有它的数据。按站内其它模块的一致做法，
   退回一个确实有数据的物种并在页面顶部说明，而不是留一个 "(unavailable)" 的选中项、
   让 class 下拉框去猜它属于哪个类群（Referee 2 major 1）。
   modlinks.php 的 "TFs / Ubs" 等链接对全部 325 个物种都会带 ?species= 过来，
   所以这条路径是常态而非边角情况。 */
$__notInList = '';
if ($specie !== '' && !in_array($specie, $__list, true)) {
    $__notInList = $specie;
    $specie = $__list[0];
}

/* $specie 的默认值是空串：服务端不会给任何 option 标 selected，浏览器就会自己
   选第一个 —— 于是「页面上显示的选择」「表单实际提交的值」「class 下拉框」
   三者可能互不一致。这里把它定下来，规则与原先浏览器的默认行为一致。 */
if ($specie === '' && isset($__list[0])) { $specie = $__list[0]; }
$__classes  = cnido_classes_in($__classMap);
/* class 下拉框默认跟随当前物种（物种比 class 更具体），没有物种时取第一个类群，
   这样页面加载时两个下拉框就是一致的。 */
$class = (isset($__classMap[$specie]) && $__classMap[$specie] !== '')
       ? $__classMap[$specie]
       : (isset($__classes[0]) ? $__classes[0] : '');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Transposable Elements - CnidoSite</title>
<meta name="keywords" content="Cnidaria, transposable elements, TEs, genome evolution, repetitive sequences" />
<meta name="description" content="Comprehensive database of transposable elements across cnidarians for genome evolution analysis" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<script LANGUAGE="JavaScript" src="js/cnido-species-filter.js" type="text/javascript"></script>

<style>
<?php /* TE页面专用样式 - 不影响全局CSS */ ?>
.te-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.te-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.te-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.genome-badge {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

<?php /* 介绍文本美化 */ ?>
.intro-text {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-left: 4px solid #10b981;
    padding: 20px 25px;
    border-radius: 8px;
    margin: 25px 0;
    color: #14532d;
    line-height: 1.7;
    font-size: 16px;
}

.intro-text strong {
    color: #166534;
}

<?php /* 表单容器美化 */ ?>
.te-form-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 30px;
    margin: 30px 0;
}

.te-form-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 30px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.te-form-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.form-card-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f5f9;
}

.form-card-title::before {
    content: "🔍";
    font-size: 24px;
}

.form-card-title.region::before {
    content: "🧬";
    font-size: 24px;
}

<?php /* 表单样式 */ ?>
.te-form-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 20px;
}

.te-form-label {
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    text-align: right;
    padding-right: 20px;
    width: 150px;
}

.te-form-input {
    width: 93%;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 16px;
    background: white;
    color: #1e293b;
    transition: all 0.3s ease;
}

.te-form-input:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.te-form-select {
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

.te-form-select:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

<?php /* 示例链接美化 */ ?>
.example-link {
    color:#047857;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
}

.example-link:hover {
    color:#047857;
    text-decoration: underline;
}

<?php /* 按钮美化 */ ?>
.button-group {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    justify-content: center;
}

.btn-submit, .btn-reset {
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
    min-width: 140px;
}

.btn-submit {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    background:linear-gradient(135deg, #065f46 0%, #064e3b 100%);
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

<?php /* 响应式调整 */ ?>
@media (max-width: 1024px) {
    .te-form-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .te-header {
        padding: 20px;
    }
    
    .te-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .te-form-card {
        padding: 25px 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .te-form-table {
        border-spacing: 0 15px;
    }
    
    .te-form-label {
        text-align: left;
        padding-right: 0;
        width: 100%;
        display: block;
        margin-bottom: 8px;
    }
    
    .te-form-table tr {
        display: block;
        margin-bottom: 20px;
    }
    
    .te-form-table td {
        display: block;
        width: 100%;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .btn-submit, .btn-reset {
        width: 100%;
    }
}
</style>

<script>
    <?php /* 审稿意见 Referee 2 major 1：两个物种下拉框原来由 DynamicOptionList 生成，
     * printOptions() 在现代浏览器里是空操作；initDynamicOptionLists() 又会在 onLoad 时
     * 清空重建下拉框（js/DynamicOptionList.js 里 child.options.length=0），
     * 会抹掉服务端渲染好的 option。选项现已服务端渲染，故不再注册该组件。 */ ?>
    <?php /* 两个例子改成 Nematostella vectensis 的真数据（原来 Acti_000001-T1 /
       ScLC4GM_1 在本物种的 NVECT_TE 里一行都查不到）：
         · 基因：XP_048578953.1，本物种 TE 最多的那个基因的**蛋白级**号
           （uncharacterized protein LOC125560710，1,884 行）。原先写的是基因级的
           `gene-LOC125560710`，而库里 related_gene 存的是不带前缀的 LOC125560710 ——
           两者靠 cnido_gene_id_forms() 的前缀加减才碰得上；蛋白号则是 TE_gene.php
           明确支持的入参写法（经 <ABBR>_locus 的 mRNA → gene 反查落到同一行，
           ix_mrna 有索引），从 gene_detail / BLAST 结果页点过来的人手里拿的也正是
           这个号，例子照抄它就与真实入口一致。
         · 区间：NC_064038.1:1000-10000000，本物种 TE 最多的 scaffold，
           这个 10 Mb 窗口命中 9,287 行（原例子也是 10 Mb 窗口）。 */ ?>
    function assignValueOal() {
        document.getElementsByName("gene")[0].value = "XP_048578953.1";
    }

    function assignValueOalposition() {
        document.getElementsByName("position")[0].value = "NC_064038.1:1000-10000000";
    }
</script>
</head>

<body onLoad="cnidoFilterAll();">
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Transposable Elements</b></legend>
<?php if ($__notInList !== ''): ?>
<div class="gd-notice gd-warn" style="margin:10px 0">
    CnidoSite has no data for <b><?= htmlspecialchars($__notInList) ?></b> in this module; showing <b><?= htmlspecialchars($specie) ?></b> instead. The species selector below lists every species this module covers.
</div>
<?php endif; ?>
<p class="paleo-intro">Transposable element (TE) records linked to protein-coding genes. Search by <b>gene ID, TE subfamily type or genomic region</b>; each hit links to JBrowse for details.</p>

<?php /* 表单容器 */ ?>
<div class="te-form-container">
    <?php /* 按基因/TE类型搜索 */ ?>
    <div class="te-form-card">
        <div class="form-card-title">
            Search TEs by Gene or TE Type
        </div>
        
        <form name="atidsearch" method="post" action="TE_gene.php" target="_blank" onSubmit="return checkquery()" encType="multipart/form-data">
            <table class="te-form-table">
                <tr>
                    <td class="te-form-label">Class</td>
                    <td>
                        <?= cnido_class_select($__classes, $class, 'class', '1', 'te-form-select') ?>
                    </td>
                </tr>
                <tr>
                    <td class="te-form-label">Species</td>
                    <td>
                        <?= cnido_species_select($__list, $__classMap, $specie, 'species', '1', 'te-form-select') ?>
                    </td>
                </tr>
                <tr>
                    <?php /* 这个框收的是**基因号**（XP_048578953.1、aacu_s0340.g1）或
                         TE 类型名的一部分（Gypsy、LTR），不是基因名：输入 collagen
                         或 Nanog 这类功能名一律 0 条，而框里没有任何提示。标签与
                         例子写明它收什么。 */ ?>
                    <td class="te-form-label">Gene ID / TE Type</td>
                    <td>
                        <input type="text" name="gene" class="te-form-input"
                               placeholder="e.g. XP_048578953.1, or a TE type such as Gypsy">
                    </td>
                </tr>
				<tr><td></td><td><a href="javascript:void(0)" onClick="assignValueOal()" class="example-link">📋 Example: XP_048578953.1
                        </a></td></tr>
                <tr>
                    <td></td>
                    <td>
                        <div class="button-group">
                            <button type="submit" class="btn-submit">
                                🔍 Submit Search
                            </button>
                            <button type="reset" class="btn-reset">
                                🔄 Reset Form
                            </button>
                        </div>
                    </td>
                </tr>
            </table>
        </form>
    </div>
    
    <?php /* 按基因组区域搜索 */ ?>
    <div class="te-form-card">
        <div class="form-card-title region">
            Search TEs by Genomic Region
        </div>
        
        <form name="search" method="post" action="TE_region.php" target="_blank" onSubmit="return checkquery()" encType="multipart/form-data">
            <table class="te-form-table">
                <tr>
                    <td class="te-form-label">Class</td>
                    <td>
                        <?= cnido_class_select($__classes, $class, 'class1', '2', 'te-form-select') ?>
                    </td>
                </tr>
                <tr>
                    <td class="te-form-label">Species</td>
                    <td>
                        <?= cnido_species_select($__list, $__classMap, $specie, 'species1', '2', 'te-form-select') ?>
                    </td>
                </tr>
                <tr>
                    <td class="te-form-label">Genome Region</td>
                    <td>
                        <input type="text" name="position" class="te-form-input">
                    </td>
                </tr>
				<tr><td></td><td><a href="javascript:void(0)" onClick="assignValueOalposition()" class="example-link">📋 Example: NC_064038.1:1000-10000000</a></td></tr>
                <tr>
                    <td></td>
                    <td>
                        <div class="button-group">
                            <button type="submit" class="btn-submit">
                                🔍 Submit Search
                            </button>
                            <button type="reset" class="btn-reset">
                                🔄 Reset Form
                            </button>
                        </div>
                    </td>
                </tr>
            </table>
        </form>
    </div>
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
