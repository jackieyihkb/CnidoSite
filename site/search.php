<?php
/* 审稿意见 Referee 2 major 1：物种门户（species_portal.php）以 ?species= 深链进本页，
 * 而本页原来的 4 个物种下拉框在服务端渲染出的 HTML 里一个 option 都没有 ——
 * 选项是 DynamicOptionList 在浏览器里生成的，该库的 printOptions() 在现代浏览器里
 * 是空操作，于是深链传进来的物种无处可选（"species selector does not work"）。
 * 现改为服务端渲染 option，取值统一走 cnido_state()：
 * GET 深链 > POST 提交 > 会话 > 默认值，会话键按模块命名空间隔离，
 * 不再与别的模块共用 $_SESSION['species']（那些模块存的是 abbr1 代码）。 */
require_once __DIR__ . '/includes/state.php';

$__st = cnido_state('search', array(
    'specie' => array('get' => 'species', 'default' => ''),
));
$specie = $__st['specie'];

/* 本页原 JS 中的物种列表（按 class 分组，顺序照抄），改为 PHP 端渲染。
 * 表单提交给 gene_detail.php / gene_locus_res.php / gene_function.php /
 * pfam_result.php，这些页面认的是拉丁名，故 option 的 value 用拉丁名。 */
$__list = array(
    // Cubozoa
    "Alatina alata", "Morbakka virulenta", "Tripedalia maipoensis",
    // Hexactiniaria
    "Acropora acuminata", "Acropora austera", "Acropora awi", "Acropora cervicornis",
    "Acropora cytherea", "Acropora digitifera", "Acropora echinata", "Acropora florida",
    "Acropora gemmifera", "Acropora hemprichii", "Acropora hyacinthus", "Acropora intermedia",
    "Acropora loripes", "Acropora microphthalma", "Acropora millepora", "Acropora muricata",
    "Acropora nasuta", "Acropora palmata", "Acropora pulchra", "Acropora selago",
    "Acropora spathulata", "Acropora tenuis", "Acropora yongei", "Actinernus sp. WN-2022",
    "Actinia equina", "Actinia mediterranea", "Actinia tenebrosa", "Actinoscyphia liui",
    "Actinostola sp. cb2023", "Alvinactis idsseensis sp. nov.", "Anthopleura xanthogrammica",
    "Astrangia poculata", "Astreopora myriophthalma", "Aurelia coerulea",
    "Aurelia sp. 4 Dawson et al 2005", "Blastomussa wellsi", "Catalaphyllia jardinei",
    "Cladopsammia gracilis", "Colpophyllia natans", "Condylactis gigantea", "Cyphastrea salae",
    "Dendrogyra cylindrus", "Dendrophyllia cribrosa", "Lophelia pertusa", "Diadumene lineata",
    "Duncanopsammia axifuga", "Echinopora horrida", "Edwardsia elegans", "Exaiptasia diaphana",
    "Fimbriaphyllia ancora", "Galaxea fascicularis", "Leptoseris scabra", "Madracis auretenra",
    "Madracis senaria", "Meandrina meandrites", "Metridium senile", "Micromussa lordhowensis",
    "Montipora cactus", "Montipora capitata", "Montipora capricornis", "Montipora efflorescens",
    "Montipora foliosa", "Montipora grisea", "Nematostella vectensis", "Oculina arbuscula",
    "Oculina patagonica", "Orbicella faveolata", "Orbicella franksi", "Pachyseris speciosa",
    "Palythoa mizigama", "Palythoa umbrosa", "Paracondylactis sinensis",
    "Paraphelliactis xishaensis sp. nov.", "Platygyra sinensis", "Plumapathes pennacea",
    "Pocillopora acuta", "Pocillopora damicornis", "Pocillopora meandrina",
    "Pocillopora verrucosa", "Podabacia crustacea", "Porites australiensis", "Porites compressa",
    "Porites cylindrica", "Porites divaricata", "Porites evermanni", "Porites harrisoni",
    "Porites lobata", "Porites lutea", "Porites rus", "Rhodactis osculifera", "Ricordea florida",
    "Scolanthus callimorphus", "Siderastrea radians", "Siderastrea siderea",
    "Stephanocoenia intersepta", "Stylophora pistillata", "Telmatactis stephensoni",
    "Tubastraea coccinea", "Turbinaria reniformis",
    // Hydrozoa
    "Bougainvillia cf. muscus", "Candelabrum cocksii", "Clytia hemisphaerica", "Hydra oligactis",
    "Hydra viridissima", "Hydra vulgaris", "Hydractinia echinata", "Hydractinia symbiolongicarpus",
    "Millepora alcicornis", "Millepora complanata", "Millepora dichotoma", "Nanomia septata",
    "Turritopsis dohrnii", "Turritopsis rubra",
    // Myxozoa
    "Henneguya salminicola", "Myxobolus honghuensis", "Myxobolus squamalis",
    "Thelohanellus kitauei",
    // Octocorallia
    "Antillogorgia americana", "Callogorgia gracilis", "Chrysogorgia sp. JL179-B06",
    "Dendronephthya gigantea", "Eunicella cavolini", "Eunicella verrucosa", "Heliopora coerulea",
    "Hemicorallium imperiale", "Leptogorgia sarmentosa", "Muricea muricata",
    "Paragorgia papillata", "Paramuricea clavata", "Pteroeides griseum", "Trachythela sp. YZ-2020",
    "Xenia sp. Carnegie-2017",
    // Scyphozoa
    "Aurelia aurita", "Aurelia aurita complex sp. Pacific", "Cassiopea sp. PORT0000214",
    "Cassiopea xamachana", "Catostylus mosaicus", "Chrysaora quinquecirrha", "Mastigias papua",
    "Nemopilema nomurai", "Pelagia noctiluca", "Rhopilema esculentum", "Sanderia malayensis",
    // Staurozoa
    "Calvadosia cruxmelitensis", "Haliclystus octoradiatus",
);

/* ---------------------------------------------------------------------------
 * 只保留「有基因注释」的物种。
 *
 * 上面这份列表是站内物种总表（148 个），其中 Alatina alata、Antillogorgia
 * americana、Cassiopea xamachana、Calvadosia cruxmelitensis 四个物种在库里
 * 没有 <abbr1>_locus 表 —— 本页的基因检索、位点检索、功能检索全部落在
 * gene_detail.php / gene_locus_res.php / gene_function.php 上，这些页面靠
 * _locus 取数据。把没有注释的物种列在下拉框里，用户选中后只会得到空结果，
 * 看起来像是本站查不到东西（审稿意见：物种选择器与数据对不上）。
 *
 * 判据直接查库（information_schema 里有没有 <abbr1>_locus），而不是写死名单：
 * 将来补了注释表，这里自动跟着变，不需要再改代码。
 * 同时反向补漏 —— 有注释但不在上面列表里的（Desmophyllum pertusum/DPERT，
 * 即原 Lophelia pertusa 的现用名）追加进来，保证 145 个有注释的物种一个不漏。
 * ------------------------------------------------------------------------- */
$__annotated = array();          // abbr1 => 拉丁名
$__conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if (!$__conn->connect_error) {
    $__q = mysqli_query($__conn,
        "SELECT a.abbr1, a.species FROM abbr a
          WHERE a.abbr1 IN (SELECT REPLACE(t.table_name, '_locus', '')
                              FROM information_schema.tables t
                             WHERE t.table_schema = DATABASE()
                               AND t.table_name LIKE '%\\_locus')");
    while ($__q && ($__r = mysqli_fetch_row($__q))) { $__annotated[$__r[0]] = $__r[1]; }
}
$__annotatedNames = array_flip($__annotated);   // 拉丁名 => abbr1

/* 过滤掉没有注释的物种 */
$__list = array_values(array_filter($__list, function ($sp) use ($__annotatedNames) {
    return isset($__annotatedNames[$sp]);
}));
/* 补上有注释但总表里漏掉的物种 */
foreach ($__annotated as $__a1 => $__sp) {
    if (!in_array($__sp, $__list, true)) { $__list[] = $__sp; }
}

/* 默认物种：Nematostella vectensis（模式生物，注释最完整，也是本站示例统一用的物种）。
   原先这里取 $__list[0]，而列表首位是 Alatina alata —— 该物种没有基因注释，
   一进页面就是个查不到东西的选择。 */
$__default = 'Nematostella vectensis';
if (!in_array($__default, $__list, true) && isset($__list[0])) { $__default = $__list[0]; }

/* 深链给的可能是 abbr1 代码（NVECT）或下划线名（Nematostella_vectensis），
 * 而本页下拉框和下游页面用的是拉丁名；不在列表里时先翻译，
 * 否则下拉框只会显示一个 (unavailable) 项。 */
if ($specie !== '' && !in_array($specie, $__list, true)) {
    if (!$__conn->connect_error) {
        $__e = mysqli_real_escape_string($__conn, $specie);
        $__q = mysqli_query($__conn, "SELECT species FROM abbr WHERE abbr1 = '$__e' OR abbr = '$__e' OR species = '$__e' LIMIT 1");
        if ($__q) { $__r = mysqli_fetch_row($__q); if ($__r) { $specie = $__r[0]; } }
    }
}
/* 类群映射取自 speciesinfo，而不是原先按注释手写的分组 —— 原列表把
   Aurelia coerulea、Aurelia sp. 4 排在 "// Hexactiniaria" 段落下，实为 Scyphozoa，
   于是类群下拉框与物种对不上。类群名统一用全站的 Hexacorallia（Referee 1 minor 1）。 */
$__classMap = cnido_species_class_map($__list, $__conn);
if ($__conn && !$__conn->connect_error) { $__conn->close(); }

/* 深链给的物种不在本页候选列表里 —— 本页没有它的数据。按站内其它模块的一致做法，
   退回一个确实有数据的物种并在页面顶部说明，而不是留一个 "(unavailable)" 的选中项、
   让 class 下拉框去猜它属于哪个类群（Referee 2 major 1）。
   modlinks.php 的 "TFs / Ubs" 等链接对全部 325 个物种都会带 ?species= 过来，
   所以这条路径是常态而非边角情况。 */
$__notInList = '';
if ($specie !== '' && !in_array($specie, $__list, true)) {
    $__notInList = $specie;
    $specie = $__default;
}

/* $specie 的默认值是空串：服务端不会给任何 option 标 selected，浏览器就会自己
   选第一个 —— 于是「页面上显示的选择」「表单实际提交的值」「class 下拉框」
   三者可能互不一致。这里把它定下来。 */
if ($specie === '') { $specie = $__default; }
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
<title>Gene Search - CnidoSite</title>
<meta name="keywords" content="Cnidaria, gene search, genomic data, locus search, function search" />
<meta name="description" content="Search genes, genomic loci, and functional annotations in Cnidaria species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<script LANGUAGE="JavaScript" src="js/cnido-species-filter.js" type="text/javascript"></script>

<script>
	<?php /* 审稿意见 Referee 2 major 1：本页物种下拉框原来由 DynamicOptionList 在浏览器里
	 * 按 class 生成，printOptions() 在现代浏览器里是空操作；而且 initDynamicOptionLists()
	 * 会在 onLoad 时清空并重建下拉框（js/DynamicOptionList.js 里 child.options.length=0），
	 * 会把服务端渲染好的 option 抹掉。选项现已服务端渲染，故不再注册该组件。 */ ?>
</script>

<style>
<?php /* 现代基因搜索页面样式 - 绿色主题 */ ?>
.search-hero {
    background: linear-gradient(135deg, #064e3b 0%, #166534 50%, #15803d 100%);
    color: white;
    padding: 40px 30px;
    border-radius: 20px;
    margin: 30px 0;
    box-shadow: 0 20px 60px rgba(16, 185, 129, 0.25);
    position: relative;
    overflow: hidden;
}

.search-hero::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
}

.search-hero h1 {
    margin: 0 0 15px 0;
    font-size: 32px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    z-index: 1;
}

.search-hero p {
    margin: 0;
    font-size: 18px;
    opacity: 0.95;
    line-height: 1.6;
    max-width: 800px;
    position: relative;
    z-index: 1;
}

.module-badge {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    padding: 10px 20px;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-top: 20px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    position: relative;
    z-index: 1;
}

<?php /* 搜索卡片美化 */ ?>
.search-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    padding: 35px;
    margin: 40px 0;
    border: 1px solid #f0f0f0;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f1f5f9;
}

.card-icon {
    width: 50px;
    height: 50px;
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.card-title {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
}

.search-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    align-items: end;
}

@media (max-width: 768px) {
    .search-form {
        grid-template-columns: 1fr;
    }
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-select, .form-input {
    width: 100%;
	max-width: 400px;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
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

.form-input {
    background-image: none;
    cursor: text;
}

.form-select:focus, .form-input:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

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

<?php /* 第一个搜索框的三个示例（基因号 / 基因名 / 功能词）排成一行小标签 */ ?>
.gsr-examples {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 14px;
    margin-top: 10px;
}
.gsr-examples .example-link { margin-top: 0; }

.gsr-note {
    font-size: 16px;
    line-height: 1.6;
    color: #64748b;
    margin-top: 8px;
}

.button-group {
    display: flex;
    gap: 15px;
    margin-top: 20px;
    grid-column: span 2;
}

@media (max-width: 768px) {
    .button-group {
        grid-column: span 1;
        flex-direction: column;
    }
}

.submit-btn, .reset-btn {
    flex: 1;
    padding: 16px 30px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: none;
}

.submit-btn {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    background:linear-gradient(135deg, #065f46 0%, #064e3b 100%);
}

.reset-btn {
    background: #f8fafc;
    color: #64748b;
    border: 2px solid #e2e8f0;
}

.reset-btn:hover {
    background: #f1f5f9;
    color: #475569;
    border-color: #cbd5e1;
}

<?php /* 双列布局容器 */ ?>
.two-column-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    <?php /* 统一设置网格间距（左右和上下都是 40px） */ ?>
    gap: 40px; 
    margin: 40px 0;
}

@media (max-width: 1024px) {
    .two-column-container {
        grid-template-columns: 1fr;
        <?php /* 如果手机端想缩小间距，可以单独在这里覆盖 */ ?>
        gap: 24px; 
    }
}

<?php /* 字段集美化 */ ?>
.fieldset-modern {
    border: none;
    padding: 0;
    margin: 0;
}


.fieldset-modern legend {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    padding: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.fieldset-modern legend::before {
    content: "🔍";
    font-size: 20px;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .search-hero {
        padding: 30px 20px;
    }
    
    .search-hero h1 {
        font-size: 24px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .search-card {
        padding: 25px 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
}
</style>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Search</b></legend>
<?php if ($__notInList !== ''): ?>
<div class="gd-notice gd-warn" style="margin:10px 0">
    CnidoSite holds functional annotation for <b><?= htmlspecialchars($__notInList) ?></b> — UniProt and NCBI-NR descriptions, InterPro, Pfam, PANTHER, GO and KEGG assignments — but no gene-coordinate table for it, so the <b>Locus (position) search</b> below cannot run for this species; showing <b><?= htmlspecialchars($specie) ?></b> instead. The selector lists the <?= count($__list) ?> species that have a coordinate table, and the four searches here share that one selector. Annotation for <b><?= htmlspecialchars($__notInList) ?></b> is still browsable from its species page and the Functional Domain Search, Gene Ontology, InterPro and KEGG modules; genome, taxonomy and mitochondrial data are available from <a href="browse.php?class=all">Taxonomy</a> and the other modules.
</div>
<?php endif; ?>
<p  class="paleo-intro">Search genes, genomic loci, and functional annotations across Cnidaria species. Explore detailed gene information, protein domains, and biological functions.</p>

<?php /* 基因搜索卡片 */ ?>
<div class="search-card">
    <div class="card-header">
        <div class="card-icon">🧬</div>
        <div class="card-title">Gene & Locus Search</div>
    </div>
    
    <div class="two-column-container">
        <?php /* 基因ID搜索 */ ?>
        <fieldset class="fieldset-modern">
            <legend id="locus">Gene ID / Name / Function Search</legend>
            <?php /* 这一个框现在认三种输入，交给 gene_search_res.php 分流：
                 输入正好是基因号 -> 302 直接进 gene_detail.php（和改版前一样）；
                 否则在所选物种里按基因名和功能注释列出命中的基因。 */ ?>
            <form name="atidsearch" method="post" action="gene_search_res.php" target="_blank" onSubmit="return checkquery()" encType="multipart/form-data" class="search-form">
                <div class="form-group">
                    <label class="form-label">🏷️ Class</label>
                    <?= cnido_class_select($__classes, $class, 'class', '1', 'form-select') ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">🐚 Species</label>
                    <?= cnido_species_select($__list, $__classMap, $specie, 'species', '1', 'form-select') ?>
                </div>
                
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">🔬 Gene ID / Name / Function</label>
                    <?php /* name 保持 gene：js/func.js 的 checkquery() 只检查
                         genelist/gene/keyword 非空，改名会让校验失效。 */ ?>
                    <input type="text" name="gene" class="form-input" placeholder="Enter gene ID, name or keyword">
                    <div class="gsr-examples">
                        <a href="javascript:void(0)" onClick="assignValueNVECT()" class="example-link">
                            📋 Gene ID: XP_048581300.1
                        </a>
                        <a href="javascript:void(0)" onClick="document.atidsearch.gene.value='actin'" class="example-link">
                            📋 Gene name: actin
                        </a>
                        <a href="javascript:void(0)" onClick="document.atidsearch.gene.value='receptor'" class="example-link">
                            📋 Function: receptor
                        </a>
                    </div>
                    <div class="gsr-note">
                        A gene ID opens its detail page directly. A gene name or functional keyword
                        lists the matching genes <b>in the selected species</b> &mdash; gene names
                        come from UniProt / NCBI-NR annotation, functional keywords from
                        InterPro / GO.
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="submit-btn">
                        🔍 Search Gene
                    </button>
                    <button type="reset" class="reset-btn">
                        ↺ Reset Form
                    </button>
                </div>
            </form>
        </fieldset>
        
        <?php /* 基因座搜索 */ ?>
        <fieldset class="fieldset-modern">
            <legend id="locus-search">Genomic Locus Search</legend>
            <form name="search" method="post" action="gene_locus_res.php" target="_blank" onSubmit="return checkquery()" encType="multipart/form-data" class="search-form">
                <div class="form-group">
                    <label class="form-label">🏷️ Class</label>
                    <?= cnido_class_select($__classes, $class, 'class2', '2', 'form-select') ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">🐚 Species</label>
                    <?= cnido_species_select($__list, $__classMap, $specie, 'species', '2', 'form-select') ?>
                </div>
                
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">📍 Genomic Position</label>
                    <input type="text" name="position" class="form-input" placeholder="Enter genomic coordinates">
                    <a href="javascript:void(0)" onClick="assignValueNVECTposition()" class="example-link">
                        📋 Example: NC_064045.1:2300000-2400000
                    </a>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="submit-btn">
                        🔍 Search Locus
                    </button>
                    <button type="reset" class="reset-btn">
                        ↺ Reset Form
                    </button>
                </div>
            </form>
        </fieldset>
    </div>
</div>

<?php /* 功能搜索卡片 */ ?>
<div class="search-card">
    <div class="card-header">
        <div class="card-icon">🔬</div>
        <div class="card-title">Functional Annotation Search</div>
    </div>
    
    <div class="two-column-container">
        <?php /* 关键词搜索 */ ?>
        <fieldset class="fieldset-modern">
            <legend id="keyword-search">Keyword Search</legend>
            <form name="keywordsearch" method="post" action="gene_function.php" target="_blank" onSubmit="return checkquery()" encType="multipart/form-data" class="search-form">
                <div class="form-group">
                    <label class="form-label">🏷️ Class</label>
                    <?= cnido_class_select($__classes, $class, 'class1', '3', 'form-select') ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">🐚 Species</label>
                    <?= cnido_species_select($__list, $__classMap, $specie, 'species', '3', 'form-select') ?>
                </div>
                
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">🔑 Biological Keywords</label>
                    <input type="text" name="keyword" class="form-input" placeholder="Enter functional keywords">
                    <a href="javascript:void(0)" onClick="assignValuekeyword()" class="example-link">
                        📋 Example: EGF-like
                    </a>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="submit-btn">
                        🔍 Search Function
                    </button>
                    <button type="reset" class="reset-btn">
                        ↺ Reset Form
                    </button>
                </div>
            </form>
        </fieldset>
        
        <?php /* 蛋白域搜索 */ ?>
        <fieldset class="fieldset-modern">
            <legend id="domain-search">Protein Domain Search</legend>
            <form name="pfamsearch" method="post" action="pfam_result.php" target="_blank" onSubmit="return checkquery()" encType="multipart/form-data" class="search-form">
                <div class="form-group">
                    <label class="form-label">🏷️ Class</label>
                    <?= cnido_class_select($__classes, $class, 'class3', '4', 'form-select') ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">🐚 Species</label>
                    <?= cnido_species_select($__list, $__classMap, $specie, 'species', '4', 'form-select') ?>
                </div>
                
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">🧬 Protein Domain ID</label>
                    <input type="text" name="pfam" class="form-input" placeholder="Enter Pfam domain identifier">
                    <a href="javascript:void(0)" onClick="assignValuePfam()" class="example-link">
                        📋 Example: PF00008
                    </a>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="submit-btn">
                        🔍 Search Domain
                    </button>
                    <button type="reset" class="reset-btn">
                        ↺ Reset Form
                    </button>
                </div>
            </form>
        </fieldset>
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
