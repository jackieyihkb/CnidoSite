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

$__st = cnido_state('chip', array(
    'specie'  => array('get' => 'species', 'default' => 'NVECT'),
    'gene'    => array('get' => 'gene',    'default' => 'XP_032239081.1'),
    'hisType' => array('get' => 'hisType', 'default' => 'H3K4me1'),
    'hisMark' => array('get' => 'hisMark', 'default' => 'planulae_4days'),
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
    $pic1 = $specie . "_ChIP";
    $pic2 = $specie . "_ChIP_" . $hisType. "_" . $hisMark;
    $conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	$countQuery1 = $conn->query("SELECT species FROM abbr WHERE abbr1 = '$specie'");
	$result3 = $countQuery1->fetch_row();
	$speciesFullName = $result3[0];
} else {
    // 设置默认图片路径
    $pic1 = "NVECT_ChIP";
    $pic2 = "NVECT_ChIP_H3K4me1_planulae_4days";
	$hisType = "H3K4me1";
	$hisMark = "planulae_4days";
	$gene = "XP_032239081.1";
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
<title>ChIP-seq Analysis - CnidoSite</title>
<meta name="keywords" content="Cnidaria, ChIP-seq, histone modification, chromatin immunoprecipitation, peak calling, MACS2" />
<meta name="description" content="ChIP-seq analysis for cnidarian species: histone-mark and transcription-factor peak sets, peak annotation and genome-wide profiles" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<?php
/* 这段级联选择原来由 DynamicOptionList.js 在浏览器里构建：<option> 不在 HTML 里，
   只有 <body onLoad> 跑完 JS 才有内容；而 initDynamicOptionLists() 会连物种下拉框
   一起清空重建，页面上显示的物种和服务器实际查询的物种可能不是同一个。
   现在把原来写死在 JS 里的这张表原样搬到 PHP，服务端渲染。 */
$__dolMap = array(
    'EDIAP' => array(
        'H3K27ac' => array(
            'symbiont',
        ),
        'H3K9ac' => array(
            'symbiont',
        ),
    ),
    'HVULG' => array(
        'H3K27ac' => array(
            'regenerating_head_0hr',
            'regenerating_head_4hr',
            'regenerating_head_6hr',
            'regenerating_head_24hr',
            'head',
            'wholeAnimal',
            'whole_polyp_24hr_DMSO_control',
        ),
        'H3K4me2' => array(
            'regenerating_head_0hr',
            'regenerating_head_4hr',
            'regenerating_head_6hr',
            'regenerating_head_24hr',
            'head',
            'wholeAnimal',
        ),
        'H3K4me3' => array(
            'regenerating_head_0hr',
            'regenerating_head_4hr',
            'regenerating_head_6hr',
            'regenerating_head_24hr',
            'head',
            'wholeAnimal',
            'whole_polyp_24hr_DMSO_control',
        ),
        'H3K9ac' => array(
            'whole_polyp_24hr_ALP_treatment',
            'whole_polyp_24hr_DMSO_control',
        ),
        'H4K20me1' => array(
            'regenerating_tips_0hrs',
            'regenerating_tips_12hrs',
            'regenerating_tips_24hrs',
            'regenerating_tips_8hrs',
            'whole_polyp_ALP_treated',
        ),
        'panH3_CNT' => array(
            'whole_polyp_24hr_DMSO_control',
        ),
    ),
    'NVECT' => array(
        'H3K27ac' => array(
            'planulae_4days',
            'gastrulae_24h',
            'Ncol3_negative',
            'Ncol3_positive',
            'Nep3_negative',
            'Nep3_positive',
        ),
        'H3K4me1' => array(
            'planulae_4days',
            'gastrulae_24h',
            'neuronal_ELAV_cells',
        ),
        'H3K4me2' => array(
            'polyps',
            'planulae_4days',
            'gastrulae_24h',
            'neuronal_ELAV_cells',
        ),
        'H3K4me3' => array(
            'polyps',
            'planulae_4days',
            'gastrulae_24h',
            'neuronal_ELAV_cells',
        ),
        'H3K36me3' => array(
            'polyps',
            'gastrulae_24h',
            'planulae_4days',
        ),
        'pSMAD1/5' => array(
            'late_gastrula',
            'late_planula_4d',
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
   最下方那张 15 列的 peak 表：桌面档不许把整页撑出去。
   全站 `table.gridtable th{white-space:nowrap}` 让表的 min-width ＝ Σ 表头宽度，
   本表一行到底时是 1715px，而 #column 只有 1398px（1440 视口）。`js/rwd-tables.js`
   会给它套一层 `.gt-scroll`，但那一层「能横滑」的样式写在
   `@media (max-width:1200px)` 里 —— 于是宽屏上这个壳是死的：表直接从壳里溢到
   页面外，document 宽度被撑到 1736px，右边的 Description / Visualization 整列
   跑到屏幕外。窄屏反而没事，坏掉的恰好是桌面档。
   表头已经写死两行断点（见下方 echo 表头那段注释），min-width 降到 1373px；
   这里再补三件事：
     1. 让桌面档那个壳真的能横滑 —— 表宽超出时在自己的框里滑，永远不出页面；
     2. overscroll-behavior-x:contain，横滑到头不触发浏览器「后退」手势；
     3. 15 列 × 两侧各减 2px ＝ 60px。全站标准是 10px，减到 8px 之后 1440 视口
        刚好放得下（1398 ≤ 1398）；只减 1px（9px）时还差 5px，会留下一根
        几乎滑不动的横滑条 + 最后一列表头被啃掉一角。
   必须包在 `@media (min-width:1201px)` 里：`rwd-tables.js` 的 alreadyScrollable()
   只要看到祖先的 overflow-x 是 auto/scroll 就跳过套壳，无条件加这条会静默关掉
   窄屏那套 width:max-content 横滑（与 MAGs.php 同一个坑）。
   实测（表宽/容器宽，表头恒为 2 行，document 宽度任何档都不溢出）：
     1920 1588/1588（贴合，行高 62）· 1600 1558/1558（贴合，行高 62）
     1440 1398/1398（贴合，行高 108）· 1425 1383/1383（贴合：模拟真机 15px 竖滚动条）
     1366 1373/1324（框内横滑 49px）· 1280 1373/1238（135px）· 1265（150px）
     ≤1200 走窄屏那套：表宽 1697 + 容器内横滑，页面宽度仍不溢出。
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>ChIP-seq Analysis</b></legend>
<p class="paleo-intro">This module provides the epigenetic modification landscape of the genome. Submit a gene of interest to see its peak position and the genes nearby.</p>

<?php /* 查询表单卡片 */ ?>
<div class="query-card">
    <form name="atidsearch" method="post" onSubmit="return checkquery()" encType="multipart/form-data" class="query-form">
        <div class="form-group">
            <label class="form-label">Species</label>
            <select name="species" class="form-select">
                <option value="EDIAP" <?= $specie == 'EDIAP' ? 'selected' : '' ?>>Exaiptasia diaphana</option>
                <option value="HVULG" <?= $specie == 'HVULG' ? 'selected' : '' ?>>Hydra vulgaris</option>
                <option value="NVECT" <?= $specie == 'NVECT' ? 'selected' : '' ?>>Nematostella vectensis</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Epigenetic Modification Type</label>
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
            <input type="text" name="gene" value="<?= htmlspecialchars($gene) ?>" class="form-input" placeholder="Enter gene ID (e.g., XP_032239081.1)">
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
        Visualization for the distribution of ChIP-seq in <i><?= htmlspecialchars($speciesFullName) ?></i> - <?= htmlspecialchars($hisMark) ?>
    </div>
    
    <div class="results-container">
        <!-- FeaturePlot -->
        <div class="plot-card">
            <div class="plot-title">The distribution of all ChIP-seq samples in <i><?= htmlspecialchars($speciesFullName) ?></i></div>
            <img src="images/<?= htmlspecialchars($pic1) ?>.png" class="plot-image same-size-img">
        </div>
        
        <!-- VlnPlot -->
        <div class="plot-card">
            <div class="plot-title">The distribution of the sample (<?= htmlspecialchars($hisMark) ?>) in <i><?= htmlspecialchars($speciesFullName) ?></i></div>
            <img src="images/<?= htmlspecialchars($pic2) ?>.png" class="plot-image same-size-img">
        </div>
    </div>
</div>

<?php if ($formSubmitted && $specie && $hisMark): ?>
<?php
    $conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	if($hisType == "pSMAD1/5"){
		$sample=$hisMark;
	}else{
		$sample=$hisType."_".$hisMark;
	}
	$tab = $specie . "_ChIP";
	$countQuery = $conn->query("SELECT COUNT(*) FROM $tab WHERE Sample = '$sample' and protein_id = '$gene'");
	$total_records = 0;
	if ($countQuery) {
		$countResult = $countQuery->fetch_row();
		$total_records = $countResult[0];
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
                <strong>Epigenetic Modification Type:</strong> <?= htmlspecialchars($hisType) ?> •
                <strong>Tissue:</strong> <?= htmlspecialchars($hisMark) ?> •
                <strong>Gene:</strong> <?= htmlspecialchars($gene) ?>
            </div>
        </div>
    </div>
    
    <div class="selection-badge">
        <span style="color:#6d28d9; font-size: 18px;">📊</span>
        <span><?= $total_records ?> peak records found</span>
    </div>
</div>

<?php
	/* 长表头一律写死两行断点，理由与 MAGs.php 同：全站 `table.gridtable th{white-space:nowrap}`
	   下，表的 min-width ＝ Σ 每段表头文字的宽度。本表 15 列，一行到底时 min-width 1715px，
	   而桌面档容器只有 1398px（1440 视口），右边的 Description / Visualization 直接被
	   顶到页面外（整页出现横向滚动条）。折成两行后 min-width 降到 1403px，1440 只剩 5px，
	   1600 以上完全放得下。断点按「该列数据有多宽」挑：第 1/8 列的数据 NC_064036.1 要 113px
	   本来就比表头宽，所以它们不是瓶颈，瓶颈是 geneStart/distanceToTSS/Protein id 这几条。
	   Description 列由 10% 提到 18%：这一列装的是整句蛋白描述，宽度不够时会在 125px 的
	   窄列里折成四行，把每行行高从 62px 顶到 108px（余量够的宽度才有用，1440 以下会被
	   min-content 下限压回去，不影响窄屏）。 */
	echo "<table class=\"gridtable\">";
	echo "<tr><th>Chr/Scaffold/<br>Contig</th><th>start</th><th>end</th><th>peak<br>name</th><th title='Peak summit reported by MACS2 for this peak.'>Summit</th><th title='Number of reads piled up at the peak summit.'>Pileup</th><th width='8%'>peak<br>location</th><th title='Chromosome, scaffold or contig carrying the gene.'>gene<br>Chr</th><th>gene<br>Start</th><th>gene<br>End</th><th>gene<br>Length</th><th title='Signed distance from the transcription start site of this gene to the nearest edge of the peak; positive means the peak lies downstream of the TSS, negative upstream.'>distance<br>ToTSS</th><th width='8%'>Protein<br>id</th><th width='18%'>Description</th><th width='6%'>Visualization</th></tr>";
	$query=mysqli_query($conn,"SELECT * FROM $tab WHERE Sample = '$sample' and protein_id = '$gene'");
	while($result=mysqli_fetch_row($query))
	{
	echo "<tr align='center'><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td><td>$result[10]</td><td>$result[11]</td><td>$result[12]</td><td><a href=\"./gene_detail.php?gene=$gene&species=$result3[0]\">$result[13]</a></td><td>$result[14]</td>";
	echo "<td><a href=\"./jbrowse/index.html?data=$specie&gene=$gene\">JBrowse</a></td></tr>";
	}
	echo "</table><br>";
?>
<?php endif; ?>

<?php if ($formSubmitted && (!$specie || !$hisMark)): ?>
<?php /* 表单提交但没有选择完整参数时显示的消息 */ ?>
<div class="no-selection" style="text-align: center; padding: 40px; background: #f8fafc; border-radius: 12px; margin: 30px 0;">
    <div style="font-size: 48px; margin-bottom: 20px; color:#6d28d9;">🔍</div>
    <h3 style="color: #64748b; margin-bottom: 10px;">Please Select Complete Parameters</h3>
    <p style="color:#64748b;">Please select Species, Epigenetic Modification Type, Tissue/Organ, and Gene ID to view relevant information.</p>
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
