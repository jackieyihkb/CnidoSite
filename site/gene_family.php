<?php
/* 审稿意见 Referee 2 major 1：物种门户（species_portal.php）以 ?species= 深链进本页，
 * 而本页的物种下拉框在服务端渲染出的 HTML 里一个 option 都没有 —— 选项由
 * DynamicOptionList 在浏览器里生成，该库的 printOptions() 在现代浏览器里是空操作，
 * 深链传进来的物种因而无处可选（"species selector does not work"）。
 * 现改为服务端渲染 option，取值统一走 cnido_state()：
 * GET 深链 > POST 提交 > 会话 > 默认值，会话键按模块命名空间隔离，
 * 不再与别的模块共用 $_SESSION['species']（那些模块存的是 abbr1 代码）。 */
require_once __DIR__ . '/includes/state.php';

$__st = cnido_state('tfub', array(
    'specie' => array('get' => 'species', 'default' => ''),
));
$specie = $__st['specie'];

/* 本页原 JS 中的物种列表（按 class 分组，顺序照抄），改为 PHP 端渲染。
 * 表单提交给 family_member.php，该页按拉丁名查 abbr，故 option 的 value 用拉丁名。
 *
 * 这份名单现在只当**离线兜底**：真实名单从库里发现 —— tf 与 ubs 两张共享表里
 * 出现过的物种的并集。原来它是唯一来源，于是和库对不上，而且是对不上在三处：
 *   · Desmophyllum pertusum（有 847 行 TF）、Taxipathes sp. SY275-mao（58 行 TF）
 *     有数据却不在名单里，选不到，只能自己拼 URL；
 *   · 名单里的 Trachythela sp. YZ-2020 只有泛素数据、没有 TF 数据（它的 562 行
 *     在 ubs 里），于是「Trachythela + Transcription Factors Family」永远是一张
 *     空表；
 *   · 页首写死的「148 cnidarian species」也随名单一起偏。
 * 名单改成并集之后：tf 148 种、ubs 148 种、并集 149 种，其中 147 种两者都有，
 * Taxipathes 只有 TF、Trachythela 只有泛素 —— 空表只剩「这个物种确实没有这类
 * 数据」一种成因。兜底名单补上了缺的两个，并在注释里标出各自的来源。 */
$__fallback = array(
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
    "Dendrogyra cylindrus", "Dendrophyllia cribrosa", "Desmophyllum pertusum",
    "Lophelia pertusa", "Diadumene lineata",
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
    "Taxipathes sp. SY275-mao", "Tubastraea coccinea", "Turbinaria reniformis",
    // Hydrozoa
    "Bougainvillia cf. muscus", "Candelabrum cocksii", "Clytia hemisphaerica", "Hydra oligactis",
    "Hydra viridissima", "Hydra vulgaris", "Hydractinia echinata", "Hydractinia symbiolongicarpus",
    "Millepora alcicornis", "Millepora complanata", "Millepora dichotoma", "Nanomia septata",
    "Turritopsis dohrnii", "Turritopsis rubra",
    // Myxozoa
    "Henneguya salminicola", "Myxobolus honghuensis", "Myxobolus squamalis",
    "Thelohanellus kitauei",
    // Octocorallia
    "Callogorgia gracilis", "Chrysogorgia sp. JL179-B06", "Dendronephthya gigantea",
    "Eunicella cavolini", "Eunicella verrucosa", "Heliopora coerulea", "Hemicorallium imperiale",
    "Leptogorgia sarmentosa", "Muricea muricata", "Paragorgia papillata", "Paramuricea clavata",
    "Pteroeides griseum", "Trachythela sp. YZ-2020", "Xenia sp. Carnegie-2017",
    // Scyphozoa
    "Aurelia aurita", "Aurelia aurita complex sp. Pacific", "Cassiopea sp. PORT0000214",
    "Cassiopea xamachana", "Catostylus mosaicus", "Chrysaora quinquecirrha", "Mastigias papua",
    "Nemopilema nomurai", "Pelagia noctiluca", "Rhopilema esculentum", "Sanderia malayensis",
    // Staurozoa
    "Calvadosia cruxmelitensis", "Haliclystus octoradiatus",
);

/* 真实名单：tf 与 ubs 里出现过的物种的并集（两张表都是共享表，带 species 列）。
   与 TE.php 同一套做法 —— 库是权威，名单跟着数据走。 */
$__list = array();
$__conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if (!$__conn->connect_error) {
    $__q = mysqli_query($__conn,
        "SELECT species FROM tf UNION SELECT species FROM ubs ORDER BY species");
    while ($__q && ($__r = mysqli_fetch_row($__q))) {
        $__s = trim((string)$__r[0]);
        if ($__s !== '') { $__list[] = $__s; }
    }
    $__conn->close();
}
/* 库连不上时退回写死的名单，页面至少还能用。 */
if (!$__list) { $__list = $__fallback; }

/* 深链给的可能是 abbr1 代码（NVECT）或下划线名（Nematostella_vectensis），
 * 而本页下拉框和下游页面用的是拉丁名；不在列表里时先翻译，
 * 否则下拉框只会显示一个 (unavailable) 项。 */
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
/* 站点默认物种。原来这里（含下面那段初始默认）一律取 $__list[0]，也就是分组顺序
   里的第一个 —— 实际落到 Alatina alata（Cubozoa），而全站默认物种是
   Nematostella vectensis。本页 tf 表里 NVECT 有 222 行、Alatina alata 有 503 行，
   后者数据更多，但物种这一项按站点约定统一取 NVECT（「数据最多」是给基因/功能
   例子用的规则，不是换取默认物种的理由）。名单里没有 NVECT 时退回 $__list[0]。 */
$__pref = in_array('Nematostella vectensis', $__list, true) ? 'Nematostella vectensis' : '';
if ($specie !== '' && !in_array($specie, $__list, true)) {
    $__notInList = $specie;
    $specie = ($__pref !== '') ? $__pref : $__list[0];
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
<title>Transcription Factors/Ubiquitin Family - CnidoSite</title>
<meta name="keywords" content="Cnidaria, gene family, transcription factors, ubiquitin family, genome analysis" />
<meta name="description" content="Browse and search transcription factor and ubiquitin gene families across <?php echo count($__list); ?> Cnidaria species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<script LANGUAGE="JavaScript" src="js/cnido-species-filter.js" type="text/javascript"></script>

<style>
<?php /* Gene Family页面专用样式 - 不影响全局CSS */ ?>
.gene-family-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.gene-family-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.gene-family-header p {
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

<?php /* 表单容器美化 */ ?>
.gene-family-form-container {
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

.form-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 20px;
}

.form-label {
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    text-align: right;
    padding-right: 20px;
    width: 150px;
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
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

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

<?php /* 统计信息美化 */ ?>
.stats-container {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-left: 4px solid #10b981;
    padding: 20px 25px;
    border-radius: 8px;
    margin: 25px auto;
    max-width: 800px;
    color: #14532d;
    line-height: 1.7;
    text-align: center;
}

.stats-icon {
    font-size: 24px;
    margin-bottom: 10px;
}

.stats-text {
    font-size: 16px;
}

.stats-highlight {
    font-weight: 700;
    color: #166534;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .gene-family-header {
        padding: 20px;
    }
    
    .gene-family-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .gene-family-form-container {
        padding: 25px 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .form-table {
        border-spacing: 0 15px;
    }
    
    .form-label {
        text-align: left;
        padding-right: 0;
        width: 100%;
        display: block;
        margin-bottom: 8px;
    }
    
    .form-table tr {
        display: block;
        margin-bottom: 20px;
    }
    
    .form-table td {
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Transcription Factors/Ubiquitin Family</b></legend>
<?php if ($__notInList !== ''): ?>
<div class="gd-notice gd-warn" style="margin:10px 0">
    CnidoSite has no data for <b><?= htmlspecialchars($__notInList) ?></b> in this module; showing <b><?= htmlspecialchars($specie) ?></b> instead. The species selector below lists every species this module covers.
</div>
<?php endif; ?>
<p  class="paleo-intro">This module covers <span class="stats-highlight"><?php echo count($__list); ?> cnidarian species</span> across <?php echo count($__classes); ?> taxonomic classes. Select a class, species and gene-family type to browse the annotations.</p>

<?php /* 表单容器 */ ?>
<div class="gene-family-form-container">
    <div class="form-title">Select Gene Family Parameters</div>
    
    <form name="atidsearch" method="post" action="family_member.php" target="_blank" onSubmit="return checkquery()" encType="multipart/form-data">
        <table class="form-table">
            <tr>
                <td class="form-label">Class</td>
                <td>
                    <?= cnido_class_select($__classes, $class, 'class', '1', 'form-select') ?>
                </td>
            </tr>
            <tr>
                <td class="form-label">Species</td>
                <td>
                    <?= cnido_species_select($__list, $__classMap, $specie, 'species', '1', 'form-select') ?>
                </td>
            </tr>
            <tr>
                <td class="form-label">Gene Family</td>
                <td>
                    <select name="family" class="form-select">
                        <option selected value="tf">Transcription Factors Family</option>
                        <option value="ubs">Ubiquitin Family</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="button-group">
                        <button type="submit" class="btn-submit">
                            🔍 View Gene Family Members
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

<?php
    include "Webpage_components.php";
    print $footer;
?>
</body>
</html>
