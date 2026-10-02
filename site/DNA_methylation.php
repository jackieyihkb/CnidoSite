<?php
/* 审稿意见 Referee 1 minor 1 & 3、Referee 2 major 1：
 *   "The species selector does not appear to work."
 * 三个根因在这里一次解决：
 *   1. 原来只读 $_POST —— 从别的模块（或物种门户）深链过来时，GET 参数被忽略，
 *      页面永远显示默认物种，看起来就是「选择器没反应」；
 *   2. 会话键 'species'/'hisType'/'hisMark' 是全站共用的，而这个页面存的是
 *      EDIAP 这样的缩写、别的模块存的是拉丁名，互相覆盖后谁都认不出来；
 *   3. 表单提交后 $_SESSION 只在本次请求里被读取，翻页/刷新又会退回默认值。
 * 现改用 cnido_state()：GET 深链 > POST 提交 > 会话 > 默认值，会话键按模块隔离。
 */
require_once __DIR__ . '/includes/state.php';

$formSubmitted = isset($_POST['species']) || isset($_POST['hisType'])
              || isset($_POST['hisMark']) || isset($_POST['gene']);

$__st = cnido_state('bs', array(
    'specie'  => array('get' => 'species', 'default' => 'NVECT'),
    'gene'    => array('get' => 'gene',    'default' => 'XP_048580524.1'),
    'hisType' => array('get' => 'hisType', 'default' => ''),
    'hisMark' => array('get' => 'hisMark', 'default' => 'Whole_adults'),
));
$specie  = $__st['specie'];
$gene    = $__st['gene'];
$hisType = $__st['hisType'];
$hisMark = $__st['hisMark'];

// 深链进来时也当作「已提交」，这样图表与结果区会直接渲染出来
if (!$formSubmitted && ($__st['specie__from'] === 'get' || $__st['hisMark__from'] === 'get')) {
    $formSubmitted = true;
}


// 初始化变量
$pic1 = '';
$pic2 = '';
$speciesFullName = ''; // 默认值

if ($formSubmitted && $specie && $hisMark) {
    // 生成图片路径
    $pic1 = $specie . "_BS_" . $hisMark . "_1";
    $pic2 = $specie . "_BS_" . $hisMark . "_2";
    $conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	$countQuery1 = $conn->query("SELECT species FROM abbr WHERE abbr1 = '$specie'");
	$result3 = $countQuery1->fetch_row();
	$speciesFullName = $result3[0];
} else {
    // 设置默认图片路径
    $pic1 = "NVECT_BS_Whole_adults_1";
    $pic2 = "NVECT_BS_Whole_adults_2";
	$hisMark = "Whole_adults";
	$gene = "XP_048580524.1";
	$speciesFullName = 'Nematostella vectensis'; // 默认值
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
<title>DNA Methylation Analysis - CnidoSite</title>
<meta name="keywords" content="Cnidaria, DNA methylation, bisulfite sequencing, CpG, mCG, mCHH, methylation motifs" />
<meta name="description" content="DNA methylation analysis for cnidarian species: CpG and non-CpG methylation levels, motif and genomic-element distributions per sample" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<?php
/* 这段级联选择原来由 DynamicOptionList.js 在浏览器里构建：<option> 不在 HTML 里，
   只有 <body onLoad> 跑完 JS 才有内容；而 initDynamicOptionLists() 会连物种下拉框
   一起清空重建，页面上显示的物种和服务器实际查询的物种可能不是同一个。
   现在把原来写死在 JS 里的这张表原样搬到 PHP，服务端渲染。 */
$__dolMap = array(
    'EDIAP' => array(
        'Adult tissues/organs' => array(
            'whole_animal_aposymbiotic_1',
            'whole_animal_aposymbiotic_2',
            'whole_animal_aposymbiotic_3',
            'whole_animal_aposymbiotic_4',
            'whole_animal_aposymbiotic_5',
            'whole_animal_aposymbiotic_6',
            'whole_animal_aposymbiotic_7',
            'whole_animal_aposymbiotic_8',
            'whole_animal_aposymbiotic_9',
            'whole_animal_symbiotic_1',
            'whole_animal_symbiotic_2',
            'whole_animal_symbiotic_3',
            'whole_animal_symbiotic_4',
            'whole_animal_symbiotic_5',
            'whole_animal_symbiotic_6',
            'whole_animal_symbiotic_7',
            'whole_animal_symbiotic_8',
            'whole_animal_symbiotic_9',
            'whole_animal_symbiotic_10',
            'whole_animal_symbiotic_11',
            'whole_animal_symbiotic_12',
            'whole_animal_symbiotic_13',
            'whole_animal_symbiotic_14',
        ),
    ),
    'NVECT' => array(
        'Adult tissues/organs' => array(
            'Whole_adults',
        ),
    ),
    'AMILL' => array(
        'Adult tissues/organs' => array(
            'planula',
        ),
    ),
    'ASELA' => array(
        'Adult tissues/organs' => array(
            'Coral_Fragment_Parent',
            'Larval_Pool_Offspring',
        ),
    ),
    'APALM' => array(
        'Adult tissues/organs' => array(
            'Polyp_Upperside_Control_1',
            'Polyp_Underside_Control_2',
            'Polyp_Underside_Control_3',
            'Polyp_Underside_Treatment_1',
            'Polyp_Upperside_Treatment_3',
            'Polyp_Upperside_Treatment_4',
        ),
    ),
);

/* 二级（Stages/Organs）候选：当前物种在表里的子键；物种不在表里时退化成全表的
   并集，免得服务端渲染出一个空的下拉框。 */
$__dolTypes = isset($__dolMap[$specie]) ? array_map('strval', array_keys($__dolMap[$specie])) : array();
if (!$__dolTypes) {
    foreach ($__dolMap as $__dolSub) {
        foreach (array_keys($__dolSub) as $__dolK) { $__dolTypes[] = (string)$__dolK; }
    }
    $__dolTypes = array_values(array_unique($__dolTypes));
}
/* 三级（Tissue/Organ）候选：当前（物种, 二级值）下的那一列值。没有 selected 的
   <select> 浏览器会默认选中第一项（老的 DOL 库也是这个规则），所以当前二级值不在
   候选里时就按第一项取，这样打开页面后 JS 重建的结果和这里完全一致。 */
$__dolTypeCur = in_array((string)$hisType, $__dolTypes, true) ? (string)$hisType : (count($__dolTypes) ? $__dolTypes[0] : '');
if (isset($__dolMap[$specie][$__dolTypeCur])) {
    $__dolMarks = array_map('strval', $__dolMap[$specie][$__dolTypeCur]);
} else {
    $__dolMarks = array();
    foreach ($__dolMap as $__dolSub) {
        if (isset($__dolSub[$__dolTypeCur])) {
            foreach ($__dolSub[$__dolTypeCur] as $__dolV) { $__dolMarks[] = (string)$__dolV; }
        }
    }
    $__dolMarks = array_values(array_unique($__dolMarks));
}
?>

<style>
<?php /* 现代单细胞模块样式 - 紫色主题 */ ?>
.expression-hero {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 50%, #5b21b6 100%);
    color: white;
    padding: 40px 30px;
    border-radius: 20px;
    margin: 30px 0;
    box-shadow: 0 20px 60px rgba(124, 58, 237, 0.25);
    position: relative;
    overflow: hidden;
}

.expression-hero::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
}

.expression-hero h1 {
    margin: 0 0 15px 0;
    font-size: 32px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    z-index: 1;
}

.expression-hero p {
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

<?php /* 表单卡片美化 */ ?>
.query-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    padding: 35px;
    margin: 40px 0;
    border: 1px solid #f0f0f0;
}

.query-title {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.query-title::before {
    content: "🔬";
    font-size: 28px;
}

.query-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    align-items: end;
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
}

.form-select, .form-input {
    width: 100%;
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
    border-color:#6d28d9;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.submit-btn {
    background:linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
    color: white;
    padding: 16px 30px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    height: fit-content;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
}

<?php /* 结果展示区域 */ ?>
.results-section {
    margin: 50px 0;
}

.results-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 18px 25px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
}

.results-header::before {
    content: "📊";
    font-size: 24px;
}

.results-container {
    background: white;
    border-radius: 0 0 16px 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    border: 1px solid #f0f0f0;
    border-top: none;
    padding: 30px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

<?php /* 新增：让 pic3 独占整行 */ ?>
.full-width {
    grid-column: span 2;
}

@media (max-width: 1024px) {
    .results-container {
        grid-template-columns: 1fr;
    }
    
    <?php /* 移动端变成单列时，取消跨列设置 */ ?>
    .full-width {
        grid-column: span 1;
    }
}

.plot-card {
    background:#f8fafc;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.plot-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
}

.plot-title {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 20px;
    text-align: center;
    padding-bottom: 15px;
    border-bottom: 1px solid #e2e8f0;
}

.plot-image {
    width: 100%;
    height: 600px;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.plot-image:hover {
    transform: scale(1.02);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

<?php /* 如果你不想影响 pic3 的高度，可以单独为前两张图写一个类 */ ?>
.same-size-img {
    height: 600px;
    object-fit: contain;
}

<?php /* 滚动容器美化 */ ?>
.scrollable-container {
    height: 650px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: white;
}

.scrollable-container::-webkit-scrollbar {
    width: 12px;
}

.scrollable-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 6px;
}

.scrollable-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 6px;
    border: 2px solid #f1f5f9;
}

.scrollable-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

<?php /* 选择信息显示样式 */ ?>
.selection-info {
    background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
    color: #5b21b6;
    padding: 20px 25px;
    border-radius: 12px;
    margin: 30px 0;
    border: 2px solid #c4b5fd;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    box-shadow: 0 4px 20px rgba(124, 58, 237, 0.1);
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.selection-info-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.selection-icon {
    background:#6d28d9;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.selection-details {
    font-size: 16px;
    color: #5b21b6;
}

.selection-details strong {
    font-weight: 700;
    color: #6d28d9;
}

.selection-badge {
    background: white;
    padding: 10px 20px;
    border-radius: 20px;
    font-size: 16px;
    font-weight: 700;
    color: #5b21b6;
    border: 2px solid #c4b5fd;
    display: flex;
    align-items: center;
    gap: 8px;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .expression-hero {
        padding: 30px 20px;
    }
    
    .expression-hero h1 {
        font-size: 24px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .query-card {
        padding: 25px 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .query-form {
        grid-template-columns: 1fr;
    }
    
    .results-container {
        padding: 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .plot-card {
        padding: 20px;
    }
    
    .selection-info {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    
    .selection-info-left {
        flex-direction: column;
        text-align: center;
    }
}

<?php /* ---------------------------------------------------------------------------
   最下方那张 methylation 表：桌面档不许把整页撑出去。完整推导见
   ChIP_analysis.php 的同名注释块 —— `th{white-space:nowrap}` 让表的 min-width
   ＝ Σ 表头宽度，而 `js/rwd-tables.js` 套的那层 `.gt-scroll` 能横滑的样式只写在
   `@media (max-width:1200px)` 里，宽屏上壳是死的，表直接从壳里溢到页面外。
   表头已折成两行；这里让桌面档的壳真的能横滑 + 两侧内边距 10px→8px。
   必须包在 `@media (min-width:1201px)` 里，否则 alreadyScrollable() 会让
   `rwd-tables.js` 跳过套壳，窄屏那套 width:max-content 横滑会被静默关掉。
   --------------------------------------------------------------------------- */ ?>
@media (min-width: 1201px) {
    .gt-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        overscroll-behavior-x: contain;
    }
    .gt-scroll table.gridtable th,
    .gt-scroll table.gridtable td {
        padding-left: 8px;
        padding-right: 8px;
    }
}
</style>
</head>

<body onLoad="cnidoDolCascade();">
<script>
<?php /* 级联下拉框：原来那个 1990 年代的 Netscape 级联库已经移除（也不再需要任何外部
   JS），这里用同一张表 $__dolMap 在浏览器里做级联：改上游时只重建下游，
   并且尽量保留用户已经选过的值。 */ ?>
var CNIDO_DOL = <?= json_encode($__dolMap) ?>;
function cnidoDolRebuild(sel, values, keep) {
    if (!sel) { return; }
    var prev = sel.value;
    sel.options.length = 0;
    for (var i = 0; i < values.length; i++) {
        var o = document.createElement('option');
        o.value = values[i];
        o.text  = values[i];
        sel.options.add(o);
    }
    var want = keep ? prev : null;
    for (var j = 0; j < sel.options.length; j++) {
        if (sel.options[j].value === want) { sel.selectedIndex = j; return; }
    }
    if (sel.options.length) { sel.selectedIndex = 0; }
}
function cnidoDolCascade() {
    var sp = document.querySelector('select[name="species"]');
    var ht = document.querySelector('select[name="hisType"]');
    var hm = document.querySelector('select[name="hisMark"]');
    if (!sp || !ht || !hm) { return; }
    var sub = CNIDO_DOL[sp.value] || {};
    var types = [];
    for (var k in sub) { if (sub.hasOwnProperty(k)) { types.push(k); } }
    cnidoDolRebuild(ht, types, true);
    var marks = sub[ht.value] || [];
    cnidoDolRebuild(hm, marks, true);
    sp.onchange = cnidoDolCascade;
    ht.onchange = cnidoDolCascade;
}
</script>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>DNA Methylation Analysis</b></legend>
<p class="paleo-intro">This module provides information on three methylation motif types and their associations with protein-coding genes. Submit a gene of interest to see its motif type, genomic location and the genes nearby.</p>

<?php /* 查询表单卡片 */ ?>
<div class="query-card">
    <form name="atidsearch" method="post" onSubmit="return checkquery()" encType="multipart/form-data" class="query-form">
        <div class="form-group">
            <label class="form-label">Species</label>
            <select name="species" class="form-select">
                <option value="EDIAP" <?= $specie == 'EDIAP' ? 'selected' : '' ?>>Exaiptasia diaphana</option>
                <option value="AMILL" <?= $specie == 'AMILL' ? 'selected' : '' ?>>Acropora millepora</option>
                <option value="APALM" <?= $specie == 'APALM' ? 'selected' : '' ?>>Acropora palmata</option>
                <option value="ASELA" <?= $specie == 'ASELA' ? 'selected' : '' ?>>Acropora selago</option>
                <option value="NVECT" <?= $specie == 'NVECT' ? 'selected' : '' ?>>Nematostella vectensis</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Stages/Organs</label>
            <select name="hisType" class="form-select">
                <?php foreach ($__dolTypes as $__dolTypesV): ?>
                    <option value="<?= htmlspecialchars($__dolTypesV) ?>"<?= ((string)$hisType === (string)$__dolTypesV) ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__dolTypesV) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Tissue/Organ</label>
            <select name="hisMark" class="form-select">
                <?php foreach ($__dolMarks as $__dolMarksV): ?>
                    <option value="<?= htmlspecialchars($__dolMarksV) ?>"<?= ((string)$hisMark === (string)$__dolMarksV) ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__dolMarksV) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Gene ID</label>
            <input type="text" name="gene" value="<?= htmlspecialchars($gene) ?>" class="form-input" placeholder="Enter gene ID (e.g., XP_048580524.1)">
        </div>
        
        <div class="form-group">
            <button type="submit" class="submit-btn">
                🔍 Submit Query
            </button>
        </div>
    </form>
</div>

<?php /* 只在表单提交后显示结果 */ ?>
<div class="results-section">
    <div class="results-header">
        Visualization for the distribution of Bisulfite-Seq in <i><?= htmlspecialchars($speciesFullName) ?></i> - <?= htmlspecialchars($hisMark) ?>
    </div>
    
    <div class="results-container">
        <!-- FeaturePlot -->
        <div class="plot-card">
            <div class="plot-title">The distribution of three methylation motif types in <i><?= htmlspecialchars($speciesFullName) ?></i></div>
            <img src="images/<?= htmlspecialchars($pic1) ?>.png" class="plot-image same-size-img">
        </div>
        
        <!-- VlnPlot -->
        <div class="plot-card">
            <?php /* 原来这一行是「…in different genomic elements in (Whole_adults) in Nematostella
                      vectensis」—— 三个 in 连排，读起来像没写完。改成地点在前、样本代号在括号里。 */ ?>
            <div class="plot-title">The distribution of three methylation motif types across different genomic elements in <i><?= htmlspecialchars($speciesFullName) ?></i> (<?= htmlspecialchars($hisMark) ?>)</div>
            <img src="images/<?= htmlspecialchars($pic2) ?>.png" class="plot-image same-size-img">
        </div>
    </div>
</div>

<?php if ($formSubmitted && $specie && $hisMark): ?>
<?php
    $conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	$tab = $specie . "_BS_" . $hisMark;

	/* 表名拼不出预处理占位符，只能用白名单卡住形状（<ABBR>_BS_<mark>）；
	   $gene 走转义。这两个值都来自 GET/POST/会话，拼进 SQL 前必须过一遍。 */
	$tabOk   = (bool)preg_match('/^[A-Za-z0-9_]+$/', $tab);
	$geneEsc = mysqli_real_escape_string($conn, $gene);

	/* 这 33 张亚硫酸氢盐表（合计 7.3 亿行 / 122 GB，最大单表 9359 万行）的索引是
	   2026-09-26 才开始建的，而且只建成了一部分：没有索引时 WHERE protein_id 就是
	   一次全表扫描，小表（最小 1234 行）毫秒级回来，最大的几张要好几分钟。没有上限
	   的话，每天两千多次访问会攒出几十条并发全表扫描，把 20 个核全占满 —— 整站就是
	   这样被打瘫的，只能靠 KILL 救回来。MAX_EXECUTION_TIME 是最后一道兜底：宁可
	   这一页说「查不到」，也不能让它拖垮整台机器。
	   见 includes/gene_epigenome_panel.php 的同一条说明。 */
	$bsCap = 5000;   // ms

	$total_records = 0;
	$bsTimedOut = false;
	$bsTooBig   = false;
	$bsIndexed  = false;

	/* 先零成本问一句 information_schema：这 33 张表的体量差五个数量级（最小
	   1234 行，最大 9359 万行）。行数超过上限的直接不试 —— 扫它必定超时，
	   让用户干等满 5 秒再被告知「太大」纯属白等，而且每条这样的请求都实打实
	   占满一个核 5 秒。TABLE_ROWS 是估算值，但在这台机器上实测与真实值一致，
	   用来做这个粗判断够用；万一它低估了，下面的 MAX_EXECUTION_TIME 仍然兜底。 */
	$bsMaxRows = 5000000;
	if ($tabOk) {
		$szQ = $conn->query("SELECT TABLE_ROWS FROM information_schema.TABLES
		                      WHERE TABLE_SCHEMA = DATABASE()
		                        AND TABLE_NAME = '" . mysqli_real_escape_string($conn, $tab) . "'");
		$szR = $szQ ? $szQ->fetch_row() : null;
		if (!$szR) {
			$tabOk = false;          // 表不存在，别再去扫
		} elseif ((int)$szR[0] > $bsMaxRows) {
			$bsTooBig = true;
		}
	}

	/* 行数上限只是「没有索引时」的替代判断。protein_id 上一旦有前缀索引，
	   WHERE protein_id = ? 就是一次索引查找，跟表里有几亿行无关，这时再拿行数
	   拦就是错的：已经有 22 张表建好了 idx_protein_id，页面却还是把用户挡在外面。
	   所以被行数拦住时再多问一句 information_schema.STATISTICS，有索引就放行 ——
	   同样零成本；没建完的表照旧按行数拦。 */
	if ($tabOk && $bsTooBig) {
		$ixQ = $conn->query("SELECT 1 FROM information_schema.STATISTICS
		                      WHERE TABLE_SCHEMA = DATABASE()
		                        AND TABLE_NAME = '" . mysqli_real_escape_string($conn, $tab) . "'
		                        AND INDEX_NAME = 'idx_protein_id' LIMIT 1");
		if ($ixQ && $ixQ->fetch_row()) {
			$bsIndexed = true;
			$bsTooBig  = false;
		}
	}

	if ($tabOk && !$bsTooBig) {
		$countQuery = $conn->query("SELECT /*+ MAX_EXECUTION_TIME($bsCap) */ COUNT(*) FROM `$tab` WHERE protein_id = '$geneEsc'");
		if ($countQuery) {
			$countResult = $countQuery->fetch_row();
			$total_records = (int)$countResult[0];
		} else {
			// 3024 = 被 MAX_EXECUTION_TIME 打断。要和「真的没有记录」分开说。
			$bsTimedOut = ((int)$conn->errno === 3024);
		}
	}
?>
<?php /* 只在表单提交后显示选择信息 */ ?>
<div class="selection-info">
    <div class="selection-info-left">
        <div class="selection-icon">🔬</div>
        <div>
            <div style="font-size: 18px; font-weight: 700; color: #6d28d9; margin-bottom: 5px;">
                Query Parameters
            </div>
            <div class="selection-details">
                <strong>Species:</strong> <i><?= htmlspecialchars($speciesFullName) ?></i> •
                <strong>Stage:</strong> <?= htmlspecialchars($hisType) ?> •
                <strong>Tissue:</strong> <?= htmlspecialchars($hisMark) ?> •
                <strong>Gene:</strong> <?= htmlspecialchars($gene) ?>
            </div>
        </div>
    </div>
    
    <div class="selection-badge">
        <span style="color:#6d28d9; font-size: 18px;">📊</span>
        <span><?php
            if (!$tabOk) {
                echo 'No such methylation dataset';
            } elseif ($bsTooBig) {
                echo 'This dataset is too large to search without an index';
            } elseif ($bsTimedOut) {
                echo $bsIndexed
                    ? 'Query timed out'
                    : 'Query timed out — this table is too large to scan without an index';
            } else {
                echo $total_records . ' methylation records found';
            }
        ?></span>
    </div>
</div>

<?php
	if (!$tabOk) {
		echo "<p class=\"no-selection\">No methylation dataset named <b>" . htmlspecialchars($tab) . "</b>.</p>";
	} elseif ($bsTooBig || $bsTimedOut) {
		/* 表太大 / COUNT 都超时了，再跑一次 SELECT * 只会更慢 —— 而且要往 PHP 里
		   缓冲整个结果集，这台机器的 swap 已经见底。直接不跑。 */
		echo "<p class=\"no-selection\">This gene's methylation records could not be fetched:"
		   . ($bsIndexed
		        ? " the lookup did not finish in time."
		        : " the table is too large to scan without an index on <code>protein_id</code>.")
		   . " Narrowing the tissue or choosing a smaller dataset will be faster.</p>";
	} else {
	echo "<table class=\"gridtable\">";
	/* 长表头写死两行断点，理由与 ChIP_analysis.php 完全一致（那边有完整推导）：
	   全站 `th{white-space:nowrap}` 下表的 min-width ＝ Σ 每段表头文字的宽度，
	   一行到底时桌面档容器装不下，最右几列被顶到页面外。
	   Description 列 10% → 18%：那一列装整句描述，太窄会把行高顶成两三倍。 */
	echo "<tr><th>Motif<br>type</th><th>Chr/Scaffold/<br>Contig</th><th>start</th><th>end</th><th>methylation<br>level</th><th width='8%'>peak<br>location</th><th title='Chromosome, scaffold or contig carrying the gene.'>gene<br>Chr</th><th>gene<br>Start</th><th>gene<br>End</th><th>gene<br>Length</th><th title='Signed distance from the transcription start site of this gene to the nearest edge of the peak; positive means the peak lies downstream of the TSS, negative upstream.'>distance<br>ToTSS</th><th width='8%'>Protein<br>id</th><th width='18%'>Description</th><th width='6%'>Visualization</th></tr>";
	$specieEsc = mysqli_real_escape_string($conn, $specie);
	$query1=mysqli_query($conn,"SELECT * FROM abbr WHERE abbr1 = '$specieEsc'");
	$result1=mysqli_fetch_row($query1);
	$query=mysqli_query($conn,"SELECT /*+ MAX_EXECUTION_TIME($bsCap) */ * FROM `$tab` WHERE protein_id = '$geneEsc'");
	while($query && ($result=mysqli_fetch_row($query)))
	{
	echo "<tr align='center'><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td><td>$result[10]</td><td><a href=\"./gene_detail.php?gene=$gene&species=$result1[0]\">$result[11]</a></td><td>$result[12]</td>";
	echo "<td><a href=\"./jbrowse/index.html?data=$specie&gene=$gene\">JBrowse</a></td></tr>";
	}
	echo "</table><br>";
	}
?>
<?php endif; ?>

<?php if ($formSubmitted && (!$specie || !$hisMark)): ?>
<?php /* 表单提交但没有选择完整参数时显示的消息 */ ?>
<div class="no-selection" style="text-align: center; padding: 40px; background: #f8fafc; border-radius: 12px; margin: 30px 0;">
    <div style="font-size: 48px; margin-bottom: 20px; color:#6d28d9;">🔍</div>
    <h3 style="color: #64748b; margin-bottom: 10px;">Please Select Complete Parameters</h3>
    <p style="color:#64748b;">Please select Species, Stages/Organs, Tissue/Organ, and Gene ID to view relevant information.</p>
</div>
<?php endif; ?>

</div>
</div>
</div>

<?php
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
