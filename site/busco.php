<?php
/* 审稿意见 Referee 2 major 1：物种门户（species_portal.php）以 ?species= 深链进本页，
 * 而本页的物种下拉框在服务端渲染出的 HTML 里一个 option 都没有 —— 选项由
 * DynamicOptionList 在浏览器里生成，该库的 printOptions() 在现代浏览器里是空操作，
 * 深链传进来的物种因而无处可选（"species selector does not work"）。
 * 现改为服务端渲染 option，取值统一走 cnido_state()：
 * GET 深链 > POST 提交 > 会话 > 默认值，会话键按模块命名空间隔离，
 * 不再与别的模块共用 $_SESSION['species']（那些模块存的是 abbr1 代码）。 */
require_once __DIR__ . '/includes/state.php';
/* 完整度面板的取数与样式 —— 下面的 <style> 块要用 cnido_busco_css() */
require_once __DIR__ . '/includes/busco_summary_view.php';

$__st = cnido_state('busco', array(
    'specie' => array('get' => 'species', 'default' => ''),
));
$specie = $__st['specie'];

/* 物种下拉框的候选名单从库里取：**凡是有 BUSCO 数据的物种**都在内。
 * 这里原先是一份照抄旧 JS 的手写数组，数据一变名单就烂，而且是双向地烂 ——
 * 详见 cnido_busco_species_list() 的注释。
 * $__classMap 是按类群顺序排好的「学名 => class」，键序就是下拉框的顺序，
 * $__list 取它的键即可。表单提交给 busco_result.php，该页按拉丁名取 abbr1，
 * 故 option 的 value 用拉丁名。 */
$__conn     = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
$__dbOk     = !$__conn->connect_error;
$__classMap = $__dbOk ? cnido_busco_species_list($__conn) : array();
$__list     = array_keys($__classMap);

/* 深链给的可能是 abbr1 代码（NVECT）或下划线名（Nematostella_vectensis），
 * 而本页下拉框和下游页面用的是拉丁名；不在列表里时先翻译，
 * 否则下拉框只会显示一个 (unavailable) 项。 */
if ($specie !== '' && !in_array($specie, $__list, true) && $__dbOk) {
    $__e = mysqli_real_escape_string($__conn, $specie);
    $__q = mysqli_query($__conn, "SELECT species FROM abbr WHERE abbr1 = '$__e' OR abbr = '$__e' OR species = '$__e' LIMIT 1");
    if ($__q) { $__r = mysqli_fetch_row($__q); if ($__r) { $specie = $__r[0]; } }
}

/* 深链给的物种不在本页候选列表里 —— 本页没有它的数据。按站内其它模块的一致做法，
   退回一个确实有数据的物种并在页面顶部说明，而不是留一个 "(unavailable)" 的选中项、
   让 class 下拉框去猜它属于哪个类群（Referee 2 major 1）。
   modlinks.php 的 "TFs / Ubs" 等链接对全部 326 个物种都会带 ?species= 过来，
   所以这条路径是常态而非边角情况 —— 而且名单现在只收有数据的物种，走到这里的
   机会只会更多（没有 BUSCO 数据的物种本来就该退回并说明）。
   类群映射同样取自 speciesinfo（cnido_busco_species_list()），不再按注释手写：
   原列表把 Aurelia coerulea、Aurelia sp. 4 排在 "// Hexactiniaria" 段落下，
   实为 Scyphozoa，于是类群下拉框与物种对不上；类群名统一用全站的 Hexacorallia
   （Referee 1 minor 1）。 */
$__notInList = '';
/* 站点默认物种。原来这里（含下面那段初始默认）一律取 $__list[0]，也就是类群顺序
   加学名排序排出来的第一个 —— 实际落到 Alatina alata，它在 busco_summary 里只有
   401 个 BUSCO（6.40% 完整度）、409 行基因级记录，打开页面几乎是一张空表；
   而 Nematostella vectensis 有 3,188 个 BUSCO（99.34%）、4,837 行，是这份名单里
   数据最全的之一。名单里没有它时才退回 $__list[0]（原行为）。 */
$__pref = in_array('Nematostella vectensis', $__list, true) ? 'Nematostella vectensis' : '';
if ($specie !== '' && !in_array($specie, $__list, true)) {
    $__notInList = $specie;
    $specie = ($__pref !== '') ? $__pref : (isset($__list[0]) ? $__list[0] : '');
}

/* $specie 的默认值是空串：服务端不会给任何 option 标 selected，浏览器就会自己
   选第一个 —— 于是「页面上显示的选择」「表单实际提交的值」「class 下拉框」
   三者可能互不一致。这里把它定下来；物种取站点默认（见上），它不在名单里时
   仍退回原先浏览器会选的那一个。 */
if ($specie === '') { $specie = ($__pref !== '') ? $__pref : (isset($__list[0]) ? $__list[0] : ''); }
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
<title>BUSCO Genes - CnidoSite</title>
<meta name="keywords" content="Cnidaria, BUSCO, universal single-copy orthologs, genome completeness" />
<meta name="description" content="Benchmarking Universal Single-Copy Orthologs (BUSCO) for assessing genome assembly completeness in Cnidaria species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<script LANGUAGE="JavaScript" src="js/cnido-species-filter.js" type="text/javascript"></script>

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
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
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

<?php /* ========== 搜索框 ==========
   配色与写法照 busco_result.php 的搜索框，只少了两样：这里嵌在 .bs-panel 这张
   白卡里，所以不再描边、不再投影 —— 卡片里再套一张卡片是纯噪音。 */ ?>
.bs-search {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 4px 0 14px;
}

.bs-search label {
    font-weight: 600;
    color: #475569;
}

.bs-search input[type=text] {
    flex: 1 1 320px;
    min-width: 200px;
    padding: 10px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
    transition: all 0.3s ease;
}

.bs-search input[type=text]:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.bs-search button {
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

.bs-search button:hover {
    background:#1e40af;
}

<?php /* 上色必须写成 a.类名：templatemo_style.css 的 a:link,a:visited{color:#1d4ed8}
   是 (0,1,1)，单类名 (0,1,0) 压不住。 */ ?>
a.bs-clear {
    color:#1d4ed8;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    padding: 6px 2px;
}

a.bs-clear:hover {
    text-decoration: underline;
}

<?php /* ========== 分页样式 - 与其他页面保持一致 ========== */ ?>
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
    background:#1e40af;
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

    .bs-search {
        flex-direction: column;
        align-items: stretch;
    }

    .bs-search input[type=text] {
        width: 100%;
        /* flex:0 0 auto 不能省：基础规则里的 flex:1 1 320px 在**竖排**容器里
           320 是"高度"基准（flex-basis 跟主轴走），只清 width 的话输入框会
           长成 320px 高，手机上就是搜索框底下一条大空白。 */
        flex: 0 0 auto;
    }
}
<?= cnido_busco_css() ?>
</style>

<script>
	<?php /* 审稿意见 Referee 2 major 1：物种下拉框原来由 DynamicOptionList 按 class 生成，
	 * printOptions() 在现代浏览器里是空操作；initDynamicOptionLists() 又会在 onLoad 时
	 * 清空重建下拉框（js/DynamicOptionList.js 里 child.options.length=0），
	 * 会抹掉服务端渲染好的 option。选项现已服务端渲染，故不再注册该组件。 */ ?>
</script>
</head>

<body onLoad="cnidoFilterAll();">
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>BUSCO Genes</b></legend>
<?php if ($__notInList !== ''): ?>
<div class="gd-notice gd-warn" style="margin:10px 0">
    CnidoSite has no data for <b><?= htmlspecialchars($__notInList) ?></b> in this module; showing <b><?= htmlspecialchars($specie) ?></b> instead. The species selector below lists every species this module covers.
</div>
<?php endif; ?>
<p  class="paleo-intro"><?php /* 原来写「identified for all cnidarian species」，而站点目录里有 326 个物种、
   有蛋白集因而能打分的只有 148 个（本页下面的完整度表就是这 148 行，busco 表里也只有 149 个
   abbr）。改成按实际口径说，不再声称覆盖全部。 */ ?>Benchmarking Universal Single-Copy Ortholog (BUSCO) genes were identified for the cnidarian species that have a deposited protein set &mdash; the completeness table below gives the count and the per-species scores. Select a class or species to see details such as the BUSCO ID, gene ID, status and length. Gene structure and sequences are shown in JBrowse.</p>

<?php
/* 完整度总表。
 *
 * 审稿意见 Referee 2 major 7 要的是「核心直系同源基因集」，而它的前提是
 * 先能说清哪些基因组的完整度足以入选。原先本页只是一个下拉框，任何完整度
 * 信息都要点进去才看得到，读者无从比较。这里把 busco_summary 摊开：
 * 每个物种的 C/S/D/F/M 与是否达到高质量阈值（C≥90%, D≤10%, F≤5%）。
 *
 * 分母是 cnidaria_odb12 的 3203 个 BUSCO，不是该物种的命中行数。 */
$__bsRows  = array();
$__bsClass = array();
$__bsCoreGenomes = 0;   /* 数据库不可用时保持 0，句子里不出现未定义变量 */
if ($__dbOk) {
    $__bsRows = cnido_busco_summary_all($__conn);
    /* 类群沿用本页下拉框那份映射（cnido_busco_species_list()，同样取自
       speciesinfo），保证与筛选器口径一致。那份映射覆盖 busco_summary 里
       全部带拉丁名的行，所以新登记的物种（如 Pachycerianthus multiplicatus）
       也拿得到类群，148 行没有一行落空。
       （曾有一类例外：6 个被截断的合并码 species 为空、映射里查不到，Class 一列
       留「—」。它们已于 2026-09-21 逐物种重跑拆开，现在表里没有 species 为空的
       行，这个「—」分支也就用不上了。） */
    foreach ($__bsRows as $__r) {
        if (isset($__classMap[$__r['species']])) { $__bsClass[$__r['abbr1']] = $__classMap[$__r['species']]; }
    }
    /* 核心直系同源资源（/core/）的规模：它用的是自己那份判据
       core_species.busco90（完整度 ≥90%，不约束重复率；覆盖全部 153 个已评分
       proteome），不是上面这份表的 high_quality。两个集合的规模不同，
       现算才不会再被当成同一个数。 */
    $__bsCoreGenomes = 0;
    if ($__q = mysqli_query($__conn, "SELECT COUNT(*) FROM core_species WHERE busco90=1")) {
        $__r = mysqli_fetch_row($__q); $__bsCoreGenomes = (int)$__r[0];
    }
    $__conn->close();
}
$__bsTotal = count($__bsRows);
$__bsHq    = 0;
/* $__bsHq 只是本表口径下的达标数（蛋白集已提交，且 C≥90%、D≤10%、F≤5%）。
   它 **不是** 核心直系同源资源的规模 —— 那个资源用的是 /core/ 自己的判据
   core_species.busco90（完整度 ≥90%、不约束重复率，覆盖全部 153 个已评分
   proteome），两个集合的成员与规模都不同。
   这里曾经写过一句「达标数与可用于核心资源的数恒等，一个数就够」，页面上因此
   出现过「资源由本集合里 14 个基因组构成」的说法 —— 判据错、数也错。两个数
   现在各自现算：本表的达标数在这里数，资源的规模见上面的 $__bsCoreGenomes。 */
foreach ($__bsRows as $__r) {
    if ((int)$__r['high_quality'] === 1) { $__bsHq++; }
}

/* ---------------------------------------------------------------------------
 * 搜索 + 分页
 *
 * 总表此前一次把 148 行全发出去：页面很长，想找某一个物种只能靠浏览器 Ctrl+F，
 * 而这张表恰恰是「哪些基因组够格进核心资源」的索引，读者常常是带着一个物种或
 * 一个类群来的。现在服务端搜索 + 分页，每页条数默认 25，另有 All 一次看全
 * （与 genomeinfo.php 的物种表同一套参数与观感，含 per_page=all 这一档）。
 *
 * 参数用 q / page / per_page：本页唯一的另一个表单（物种下拉框）是 POST 给
 * busco_result.php 的，GET 里只有 species 被 cnido_state() 占着，所以这三个名字
 * 在本页是空的。搜索词**不走会话**（同 genomeinfo 的理由：留在会话里会「一搜就
 * 再也清不掉」），只从 $_GET 来，渲染前一律 htmlspecialchars。
 *
 * 筛选与分页都做在服务端：这张表的行序本身就是结论（先达标的、组内按完整度
 * 降序，见 cnido_busco_summary_all()），交给 JS 在浏览器里切会多出一套需要
 * 单独维护的次序，翻页链接也就没法分享。
 * ------------------------------------------------------------------------- */
$__bsQ        = cnido_search_term(isset($_GET['q']) ? $_GET['q'] : '');
$__bsQHtml    = htmlspecialchars($__bsQ, ENT_QUOTES, 'UTF-8');
$__bsPpRaw    = isset($_GET['per_page']) ? strtolower(trim((string)$_GET['per_page'])) : '';
$__bsPerAll   = ($__bsPpRaw === 'all');
$__bsPpChoices = array(25, 50, 100);
$__bsPer      = ($__bsPpRaw === '' ? 25 : intval($__bsPpRaw));
/* 只认下拉框里那几档（外加 all）。放任意正整数进来的话（?per_page=999），下拉框
   会照旧显示 25 而表里是 999 行 —— 「选择器说的」与「实际渲染的」对不上。 */
if (!$__bsPerAll && !in_array($__bsPer, $__bsPpChoices, true)) { $__bsPer = 25; }

$__bsView  = cnido_busco_filter($__bsRows, $__bsClass, $__bsQ);
$__bsHits  = count($__bsView);
$__bsPages = ($__bsPerAll || $__bsHits === 0) ? ($__bsHits > 0 ? 1 : 0) : (int)ceil($__bsHits / $__bsPer);
$__bsPage  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
/* 页码越界（手改 URL、或筛选后页数变少）时收拢到最后一页，而不是发一张空表。
   命中 0 条时 $__bsPages 是 0，页码保持 1，下面的分页条整块不出。 */
if ($__bsPages > 0 && $__bsPage > $__bsPages) { $__bsPage = $__bsPages; }
$__bsOffset   = $__bsPerAll ? 0 : ($__bsPage - 1) * $__bsPer;
$__bsPageRows = $__bsPerAll ? $__bsView : array_slice($__bsView, $__bsOffset, $__bsPer);

/* 全页链接共用的查询串。species 一律**显式**带上：cnido_state() 把「参数缺席」
   当「读会话」，省略它会让翻页后的下拉框与 URL 说的不是同一件事（genomeinfo.php
   的物种表同此处理）。per_page 分两份：搜索框/「显示全部」用不带它的那份，
   分页链接用带它的那份，免得同一参数出现两遍。 */
$__bsQsBase    = 'species=' . urlencode($specie) . ($__bsQ !== '' ? '&q=' . urlencode($__bsQ) : '');
/* 「Clear」与「显示全部」用：只留物种，把搜索词去掉 —— 这两个链接存在的意义
   就是把 q 清掉，所以不能拿 $__bsQsBase 去拼。 */
$__bsClearQs   = 'species=' . urlencode($specie);
$__bsPpText    = ($__bsPerAll ? 'all' : (int)$__bsPer);
$__bsQs        = $__bsQsBase . '&per_page=' . $__bsPpText;
$__bsQsHtml    = htmlspecialchars($__bsQs, ENT_QUOTES, 'UTF-8');
$__bsQsBaseH   = htmlspecialchars($__bsQsBase, ENT_QUOTES, 'UTF-8');
$__bsClearQsH  = htmlspecialchars($__bsClearQs, ENT_QUOTES, 'UTF-8');
/* 表格所在的锚点。搜索 / 翻页 / 换每页条数都是整页跳转，不带片段会被浏览器甩回
   页首 —— 表格在首屏之外。 */
$__bsAnchor    = '#busco-list';
$__bsPagerBase = '/busco.php?' . $__bsQsHtml . '&amp;page=';
$__bsPpBase    = '/busco.php?' . $__bsQsBaseH . '&amp;page=1&amp;per_page=';
?>
<?php if ($__bsTotal): ?>
<div class="bs-panel" id="busco-list">
    <div class="bs-head">
        <span class="bs-title">Genome completeness across CnidoSite</span>
        <span class="bs-badge bs-badge-hq"><?= $__bsHq ?> high-quality</span>
        <span class="bs-badge bs-badge-no"><?= $__bsTotal ?> assessed</span>
    </div>
    <p class="bs-line">
        BUSCO completeness was re-tabulated for every species from the raw hit table, counting each
        BUSCO once per species rather than counting hit rows, and scored against the
        <b>cnidaria_odb12</b> lineage (3,203 BUSCOs).
        <b><?= $__bsHq ?></b> of <b><?= $__bsTotal ?></b> species meet the high-quality threshold
        (C &ge; 90&nbsp;%, D &le; 10&nbsp;%, F &le; 5&nbsp;%). The core ortholog resource is built from
        the <b><?= (int)$__bsCoreGenomes ?></b> genomes that reach 90&nbsp;% completeness against the
        same lineage &mdash; a looser cutoff than the one used here, and a different and larger set
        than these <b><?= $__bsHq ?></b>; the criterion and the tiers that follow from it are set out
        on the <a href="/core/">Core Orthologs</a> page.
    </p>

    <?php /* 搜索框。放在表格正上方（而不是页首），因为它作用于这张表；GET 表单会
         替换整个查询串，所以 species / 每页条数用 hidden 带过去，否则搜一次就把
         深链进来的物种与刚选的每页条数丢了。搜完回到第 1 页 —— 不传 page。
         action 上挂 #busco-list：不带锚点，提交后浏览器落在页首，用户得自己再
         滚下来（genomeinfo.php 的搜索框同此做法）。 */ ?>
    <form class="bs-search" method="get" action="/busco.php<?= $__bsAnchor ?>">
        <input type="hidden" name="species" value="<?= htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="per_page" value="<?= $__bsPpText ?>" />
        <label for="bsQ">Search species</label>
        <input type="text" id="bsQ" name="q" value="<?= $__bsQHtml ?>"
               placeholder="Latin name, species code or class (e.g. Acropora, NVECT, Hydrozoa)&hellip;" />
        <button type="submit">Search</button>
        <?php if ($__bsQ !== ''): ?>
        <a class="bs-clear" href="/busco.php?<?= $__bsClearQsH ?>&amp;per_page=<?= $__bsPpText ?><?= $__bsAnchor ?>">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($__bsQ !== '' && $__bsHits > 0): ?>
    <p class="bs-line"><b><?= $__bsHits ?></b> of <b><?= $__bsTotal ?></b> species match
        &ldquo;<b><?= $__bsQHtml ?></b>&rdquo;.</p>
    <?php endif; ?>

    <?php if ($__bsHits === 0): ?>
    <?php /* 搜了但一条没中：说清是「这个词没命中任何一行」，并给一条回到全表的
         路 —— 否则用户除了浏览器后退没有别的出口（busco_result.php 的搜索框
         同此考虑）。 */ ?>
    <p class="bs-warn">No species in this table matches &ldquo;<b><?= $__bsQHtml ?></b>&rdquo;.
        The search covers the species name, the species code and the class.
        <a href="/busco.php?<?= $__bsClearQsH ?>&amp;per_page=<?= $__bsPpText ?><?= $__bsAnchor ?>">Show all <?= $__bsTotal ?> species</a>.</p>
    <?php else: ?>
    <?= cnido_busco_table($__bsPageRows, $__bsClass) ?>
    <?php endif; ?>

    <p class="bs-note">
        <b>C</b> = complete (single-copy + duplicated), <b>S</b> = single-copy complete,
        <b>D</b> = duplicated, <b>F</b> = fragmented, <b>M</b> = missing; all as a percentage of the
        3,203 lineage BUSCOs. One row per species, and every row is a species with a deposited
        protein sequence set, scored on that set &mdash; so the rows are directly comparable with
        each other. Every species that has a gene-model table is listed here, together with the three
        species whose protein set is deposited without a gene-model table of its own, so the
        <b><?= $__bsTotal ?></b> species in this table are exactly the <b><?= $__bsTotal ?></b> species
        that have a deposited protein set.
        A species with no annotation at all has no protein set to score and cannot appear: its
        completeness is reported on its species page instead.
    </p>
</div>

<?php /* 分页条。命中 0 条时整块不出（$__bsPages 为 0，页码循环一次都不进），
     与 genomeinfo.php / browse.php 的约定一致。 */ ?>
<?php if ($__bsHits > 0): ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* 这个数是「当前搜索词命中多少行」，不是全表的 148 —— 标成 Total
                 会让读者拿它跟面板上的 148 assessed 对照，以为少了数据。
                 范围也写在这句里，省得再印一行。 */ ?>
            &#128202; <?= (int)$__bsHits ?> species in the current filter
            <?= ($__bsPerAll || $__bsPages <= 1)
                ? '&middot; all rows shown'
                : '&middot; rows ' . (int)($__bsOffset + 1) . '&ndash;' . (int)min($__bsOffset + $__bsPer, $__bsHits) ?>
        </div>

        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='<?= $__bsPpBase ?>'+this.value+'<?= $__bsAnchor ?>'">
                <?php
                /* 下拉框比白名单多一档 All：它不走 intval，单独拼成 per_page=all。 */
                foreach (array_merge($__bsPpChoices, array('all')) as $__ppc) {
                    $__v   = ($__ppc === 'all') ? 'all' : (string)(int)$__ppc;
                    $__l   = ($__ppc === 'all') ? 'All' : (string)(int)$__ppc;
                    $__sel = ((string)$__ppc === 'all') ? $__bsPerAll : (!$__bsPerAll && (int)$__ppc === (int)$__bsPer);
                    echo '<option value="' . $__v . '"' . ($__sel ? ' selected="selected"' : '') . '>' . $__l . '</option>';
                }
                ?>
            </select>
            <span>species per page</span>
        </div>
    </div>

    <?php if ($__bsPages > 1): ?>
    <div class="pagination-nav">
        <?php
        /* 每条链接都带 $__bsPagerBase（species + 搜索词 + 每页条数），少带一个，
           翻一页就把用户的搜索词悄悄丢回全表。末页/首页的 Prev/Next 用 disabled
           样式挡住，不禁用 href —— 与站内其它页一致。 */
        ?>
        <a href="<?= $__bsPagerBase ?>1<?= $__bsAnchor ?>" class="page-btn <?= ($__bsPage == 1) ? 'disabled' : '' ?>">&laquo; First</a>
        <a href="<?= $__bsPagerBase ?><?= max(1, $__bsPage - 1) ?><?= $__bsAnchor ?>" class="page-btn <?= ($__bsPage == 1) ? 'disabled' : '' ?>">&lsaquo; Previous</a>
        <?php
        $__bsStart = max(1, $__bsPage - 3);
        $__bsEnd   = min($__bsPages, $__bsPage + 3);
        if ($__bsStart > 1) {
            echo '<a href="' . $__bsPagerBase . '1' . $__bsAnchor . '" class="page-btn">1</a>';
            if ($__bsStart > 2) { echo '<span class="page-btn disabled">...</span>'; }
        }
        for ($__i = $__bsStart; $__i <= $__bsEnd; $__i++) {
            echo '<a href="' . $__bsPagerBase . $__i . $__bsAnchor . '" class="page-btn '
               . ($__i == $__bsPage ? 'active' : '') . '">' . $__i . '</a>';
        }
        if ($__bsEnd < $__bsPages) {
            if ($__bsEnd < $__bsPages - 1) { echo '<span class="page-btn disabled">...</span>'; }
            echo '<a href="' . $__bsPagerBase . $__bsPages . $__bsAnchor . '" class="page-btn">' . $__bsPages . '</a>';
        }
        ?>
        <a href="<?= $__bsPagerBase ?><?= min($__bsPages, $__bsPage + 1) ?><?= $__bsAnchor ?>" class="page-btn <?= ($__bsPage == $__bsPages) ? 'disabled' : '' ?>">Next &rsaquo;</a>
        <a href="<?= $__bsPagerBase ?><?= $__bsPages ?><?= $__bsAnchor ?>" class="page-btn <?= ($__bsPage == $__bsPages) ? 'disabled' : '' ?>">Last &raquo;</a>
    </div>

    <div class="go-to-page">
        <span>Go to page:</span>
        <input type="number" id="gotoPage" min="1" max="<?= (int)$__bsPages ?>" value="<?= (int)$__bsPage ?>" />
        <button onclick="window.location.href='<?= $__bsPagerBase ?>'+document.getElementById('gotoPage').value+'<?= $__bsAnchor ?>'">Go</button>
        <span>of <?= (int)$__bsPages ?> pages</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php /* 表单容器 */ ?>
<div class="mirna-form-container">
    <div class="form-title">Select Species for BUSCO Analysis</div>
    
    <form name="search" method="post" action="busco_result.php" target="_blank" onSubmit="return checkquery()" encType="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Select Class</label>
            <?= cnido_class_select($__classes, $class, 'Class', '1', 'form-select') ?>
        </div>
        
        <div class="form-group">
            <label class="form-label">Select Species</label>
            <?= cnido_species_select($__list, $__classMap, $specie, 'species', '1', 'form-select') ?>
        </div>
        
        <div class="button-group">
            <button type="submit" class="btn-submit">
                🔍 View BUSCO Details
            </button>
            <button type="reset" class="btn-reset">
                ↺ Reset Selection
            </button>
        </div>
    </form>
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
