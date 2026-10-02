<?php session_start(); ?>
<?php
/* 依赖下拉框（hisType/hisMark）现在由服务端渲染，而下面的状态解析原本写在
   表单之后的 <?php 块里，那时 <select> 早就输出完了。这里把同一段解析提前到
   文件开头（取值完全一致：cnido_state() 只读 GET/POST/SESSION，两份配置相同）。 */
/* 审稿意见 Referee 3 点 10(i)：分页第 2 页链接失效。
 * 根因之一是本页 session_start() 写在 17KB 的 HTML 之后（头部已发送，会话无法建立，
 * 选择无法保持），分页链接又完全不带 species/hisMark 参数。现在：
 *   - session_start() 提前到文件第一行（见文件开头）；
 *   - 当前选择通过 $qs 参数带进每一个分页链接。 */
/* 深链（?species=…&hisMark=…）优先，其次表单提交，再次会话，最后默认值；
   会话键按模块命名空间隔离，避免继承其它模块选的物种。 */
require_once __DIR__ . '/includes/state.php';
$__st = cnido_state('proteomic', array(
    'specie'  => array('get' => 'species', 'default' => 'EDIAP'),
    'hisType' => array('get' => 'hisType', 'default' => ''),
    'hisMark' => array('get' => 'hisMark', 'default' => 'Whole_anemone'),
));
$specie  = $__st['specie'];
$hisType = $__st['hisType'];
$hisMark = $__st['hisMark'];

/* ---------------------------------------------------------------------------
 * 物种参数归一：本页的「物种键」其实是数据表前缀（表名 = <键>_<组织>_proteomics）。
 *
 * 这套键是各数据集入库时定下的，与站内其它模块的用词并不一致：13 个物种里
 * EDIAP / NVECT / CCRUX / MHONG / TKITA 用 abbr1 短码，其余用下划线拉丁名。
 * Stylophora pistillata 两种写法都存在 —— abbr1 是 SPIST，表前缀却是
 * Stylophora_pistillata。
 *
 * 旧版下拉框提交的正是 abbr1「SPIST」，拼出来 SPIST_colony_proteomics 在库里
 * 并不存在，于是选中 Stylophora pistillata 只会得到一张空表，页面上还直接
 * 打出 mysqli 的 "Table 'cnidaria.SPIST_colony_proteomics' doesn't exist"
 * —— 审稿人点名的就是这个（Referee 3 点 10）。includes/modlinks.php 的深链
 * 也一律按 abbr1 传参，所以这条路径是常态，不是边角情况。
 *
 * 这里把 key / 拉丁名 / abbr / abbr1 四种写法都归一到真实存在的表前缀；
 * 认不出来时退回默认物种并给出提示，与 MAGs.php、mitdata.php 的处理一致。
 * --------------------------------------------------------------------------- */
$__proteomicSpecies = array(
    'Aiptasia_sp'               => 'Aiptasia sp.',
    'Antipathes_griggi'         => 'Antipathes griggi',
    'Buddenbrockia_plumatellae' => 'Buddenbrockia bryozoides',
    'CCRUX'                     => 'Calvadosia cruxmelitensis',
    'EDIAP'                     => 'Exaiptasia diaphana',
    'Myxobilatus_gasterostei'   => 'Myxobilatus gasterostei',
    'MHONG'                     => 'Myxobolus honghuensis',
    'Myxobolus_wulii'           => 'Myxobolus wulii',
    'NVECT'                     => 'Nematostella vectensis',
    'Polypodium_hydriforme'     => 'Polypodium hydriforme',
    'Stichopathes_sp'           => 'Stichopathes sp.',
    'Stylophora_pistillata'     => 'Stylophora pistillata',
    'TKITA'                     => 'Thelohanellus kitauei',
);

if (!function_exists('cnido_proteomic_species_key')) {
    /**
     * 把请求里的物种参数归一到本页真实存在的表前缀。
     *
     * @param string $want    请求里的物种参数（键 / 拉丁名 / abbr / abbr1）
     * @param array  $species 本页支持的 键 => 拉丁名
     * @return string         归一后的键；无法识别时返回空串
     */
    function cnido_proteomic_species_key($want, $species)
    {
        $want = trim((string)$want);
        if ($want === '') { return ''; }
        foreach ($species as $key => $latin) {                 // 已经是表前缀，或是拉丁名
            if ($want === $key || strcasecmp($want, $latin) === 0) { return $key; }
        }
        // abbr / abbr1 短码：查一次 abbr 表换成表前缀（SPIST -> Stylophora_pistillata）
        $c = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
        if ($c && !$c->connect_error) {
            $e = mysqli_real_escape_string($c, $want);
            $q = mysqli_query($c,
                "SELECT abbr, abbr1 FROM abbr WHERE abbr = '$e' OR abbr1 = '$e' LIMIT 1");
            $r = $q ? mysqli_fetch_row($q) : null;
            $c->close();
            if ($r) {
                foreach ($r as $cand) {
                    if ($cand !== null && $cand !== '' && isset($species[$cand])) { return $cand; }
                }
            }
        }
        return '';
    }
}

$__spNote = '';
$__spInfo = '';
$__spKey  = cnido_proteomic_species_key($specie, $__proteomicSpecies);
if ($__spKey === '') {
    /* 深链可能给的是短码（CCRUX）—— 补上拉丁学名；原来给的就是拉丁名时不重复显示 */
    $__spShown = cnido_latin($specie);
    $__spNote = 'CnidoSite has no proteomic dataset for <b>' . htmlspecialchars($specie) . '</b>'
              . ($__spShown !== $specie ? ' (' . htmlspecialchars($__spShown) . ')' : '')
              . ' in the current release; showing <b>'
              . htmlspecialchars($__proteomicSpecies['EDIAP']) . '</b> instead. '
              . 'The species selector below lists every species this module covers.';
    $__spKey = 'EDIAP';
}
$specie = $__spKey;

/* 同物异名：页面按现行的接受名展示，但数据是这些项目发表时按当时的名字入库的。
   读者若拿页面上这个名字去 PRIDE/SRA 检索原文，会查不到那条数据，所以在这里
   点明两者的对应关系。只作物种级说明、整页渲染一次——该表有 2128 行，逐行加
   括注会把表格淹掉。键是数据表前缀，不能改成接受名（见上方 $__proteomicSpecies
   的说明），所以这里显式列一份 键 => 入库时旧名 的对照。 */
$__spSynonym = array(
    'Buddenbrockia_plumatellae' => 'Buddenbrockia plumatellae',
);
if (isset($__spSynonym[$specie])) {
    $__spInfo = 'Shown under its current accepted name <i>'
              . htmlspecialchars($__proteomicSpecies[$specie]) . '</i>. The source dataset was '
              . 'deposited as <i>' . htmlspecialchars($__spSynonym[$specie]) . '</i>, which is now '
              . 'regarded as a synonym of it — search the repositories under either name.';
}

/* $qs（分页链接要带的参数）在下面 hisType/hisMark 也归一之后才拼，
   否则翻页会把未归一的原值传回去。 */
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Proteomic Analysis</title>
<meta name="keywords" content="Cnidaria, single-cell, cell atlas, UMAP, cell type composition" />
<meta name="description" content="Interactive cell atlas visualization from single-cell and single-nucleus transcriptomes in Cnidaria species" />
<link href="/templatemo_style.css" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js" type="text/javascript"></script>

<style>
/* 分页样式 - 与其他页面保持一致 */
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
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
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
    border-color: #8b5cf6;
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
    border-color: #3b82f6;
    color: #3b82f6;
}

.page-btn.active {
    background: #3b82f6;
    border-color: #3b82f6;
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
    background: #3b82f6;
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
    color: #8b5cf6;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.external-link:hover {
    color: #7c3aed;
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
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

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
    max-width: 700px;
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
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.submit-btn {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
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

.current-selection {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
</style>

<?php
/* 这段级联选择原来由 DynamicOptionList.js 在浏览器里构建：<option> 不在 HTML 里，
   只有 <body onLoad> 跑完 JS 才有内容；而 initDynamicOptionLists() 会连物种下拉框
   一起清空重建，页面上显示的物种和服务器实际查询的物种可能不是同一个。
   现在把原来写死在 JS 里的这张表原样搬到 PHP，服务端渲染。 */
$__dolMap = array(
    'Aiptasia_sp' => array(
        'Adult tissues/organs' => array(
            'anemones',
        ),
    ),
    'Antipathes_griggi' => array(
        'Adult tissues/organs' => array(
            'Coral_skeleton',
        ),
    ),
    'Buddenbrockia_plumatellae' => array(
        'Adult tissues/organs' => array(
            'myxoworms',
        ),
    ),
    'CCRUX' => array(
        'Adult tissues/organs' => array(
            'tentacle',
        ),
    ),
    'EDIAP' => array(
        'Adult tissues/organs' => array(
            'Symbiont_Breviolum_minutum',
            'Symbiont_Durusdinium_trenchii',
            'Whole_anemone',
        ),
    ),
    'Myxobilatus_gasterostei' => array(
        'Adult tissues/organs' => array(
            'stickleback_kidney',
        ),
    ),
    'MHONG' => array(
        'Adult tissues/organs' => array(
            'Cysts',
            'Nematocysts',
        ),
    ),
    'Myxobolus_wulii' => array(
        'Adult tissues/organs' => array(
            'Cysts',
            'Nematocysts',
        ),
    ),
    'NVECT' => array(
        'Adult tissues/organs' => array(
            'Embryos',
            'polyps',
        ),
    ),
    'Polypodium_hydriforme' => array(
        'Adult tissues/organs' => array(
            'stolon',
        ),
    ),
    'Stichopathes_sp' => array(
        'Adult tissues/organs' => array(
            'Coral_skeleton',
        ),
    ),
    'Stylophora_pistillata' => array(
        'Adult tissues/organs' => array(
            'colony',
        ),
    ),
    'TKITA' => array(
        'Adult tissues/organs' => array(
            'Cysts',
            'Nematocysts',
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

/* 把归一后的值真正写回变量：原先 $__dolTypeCur 只用于算 $__dolMarks，$hisType
   本身仍是空串，于是渲染出来没有任何 option 带 selected，浏览器自选第一项 ——
   页面显示的选择、表单实际提交的值、服务器查询用的值三者可能互不相同。 */
$hisType = $__dolTypeCur;

/* 组织（Emb/Org）同样要归一。禁用 JS 时物种下拉框不会重建 hisMark 的候选，
   用户换了物种再提交，带上来的就是上一个物种的组织名 —— 表名于是拼不出来
   （Referee 3 点 10 的另一条路径）。这里按当前（物种, 二级值）的真实候选校验，
   不在候选里就退回第一项，并说明退回到了哪里。 */
$__hmNote = '';
$__hmAsked = in_array($__st['hisMark__from'], array('get', 'post'), true);
if ($hisMark !== '' && $__dolMarks && !in_array((string)$hisMark, $__dolMarks, true)) {
    /* 只有用户/链接明确点名了某个组织才提示。'Whole_anemone' 是本页的默认值，
       深链 ?species=Aiptasia_sp（modlinks.php 就是这样传的）并没有要求它，
       静默换成该物种真实存在的组织即可，不必每次都弹一条提示。 */
    if ($__hmAsked) {
        $__hmNote = 'No <b>' . htmlspecialchars($hisMark) . '</b> proteomic dataset for <b>'
                  . htmlspecialchars($__proteomicSpecies[$specie]) . '</b>; showing <b>'
                  . htmlspecialchars($__dolMarks[0]) . '</b> instead.';
    }
    $hisMark = $__dolMarks[0];
}
if ($hisMark === '' && $__dolMarks) { $hisMark = $__dolMarks[0]; }

// 分页时必须携带的参数（此处已是归一后的值），否则翻页会退回默认物种/组织
$qs = 'species=' . urlencode($specie) . '&hisType=' . urlencode($hisType) . '&hisMark=' . urlencode($hisMark);
?>
</head>

<body onLoad="cnidoDolCascade();">
<script>
/* 级联下拉框：原来那个 1990 年代的 Netscape 级联库已经移除（也不再需要任何外部
   JS），这里用同一张表 $__dolMap 在浏览器里做级联：改上游时只重建下游，
   并且尽量保留用户已经选过的值。 */
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

<!--导航栏保持不变-->
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
                    <li><a href="/busco.php">BUSCO Gene</a></li>
                    <li><a href="/TE.php">Transposable Elements</a></li>
                    <li><a href="/gene_family.php">TFs/Ubs</a></li>
                    <li><a href="/proteindomain.php">Protein Domain</a></li>
                    <li><a href="/domain_search.php">Functional Domain Search</a></li>
                    <li><a href="/go.php">Gene Ontology</a></li>
                    <li><a href="/interpro.php">InterPro</a></li>
                    <li><a href="/kegg.php">KEGG Pathway</a></li>
                    <li><a href="/genefamily.php">Gene Family</a></li>
                    <li><a href="/pan-geneset.php">Pan-geneset</a></li>
                    
                    
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li><li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li><li><a href="/mitdata.php">Mitogenomic Data</a></li>
                    
                </ul>
            <li><a href="#">Transcriptome</a>
                <ul>
                    <li><a href="/cytoscape/trans_data.php">Transcriptomic Data</a></li>
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
            <li><a href="#" class="current">Proteome</a>
                <ul>
                    <li><a href="/proteomic_data.php">Proteomic Data</a></li>
                    <li><a href="/proteomic_analysis.php">Proteomic Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Epigenome</a>
                <ul>
                    <li><a href="/epigenomic_data.php">Epigenomic Data</a></li>
                    <li><a href="/DNA_methylation.php">DNA Methylation</a></li>
                    <li><a href="/miRNA_analysis.php">MiRNA-seq Analysis</a></li>
                    <li><a href="/ATAC_analysis.php">ATAC-seq Analysis</a></li>
                    <li><a href="/DHS_analysis.php">DNase-seq Analysis</a></li>
                    <li><a href="/ChIP_analysis.php">ChIP-seq Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Metagenome</a>
                <ul>
                    <li><a href="/metagenomic_data.php">Metagenomics Data</a></li>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Proteomic Analysis</b></legend>
<p class="paleo-intro">In this module, we provide the systematic identification and quantification of proteins from proteomics datasets of adult tissues/organs.</p>

<?php /* 交叉链接，不是重定向：本页是 CnidoSite 自己的蛋白组表，其中 9 个物种因为
   拿不到转录组组装、没有搜索空间，从未进入 2026 的重处理，只存在于本页。两套页面
   互补，谁都不能替代谁 —— 2026-09-26 试过把本页重定向掉，当天回滚。映射见
   includes/proteomic_crosslinks.php，只收录双向都验证过能出数据的物种。 */
require_once __DIR__ . '/includes/proteomic_crosslinks.php';
echo cnido_proteomic_note_original($specie); ?>

<?php if ($__spNote !== '' || $__hmNote !== ''): ?>
<div class="gd-notice gd-warn" style="margin:10px 0">
    <?= $__spNote ?><?= ($__spNote !== '' && $__hmNote !== '') ? ' ' : '' ?><?= $__hmNote ?>
</div>
<?php endif; ?>

<?php if ($__spInfo !== ''): ?>
<div class="gd-notice gd-info" style="margin:10px 0"><?= $__spInfo ?></div>
<?php endif; ?>

<!-- 选择表单 -->
<div class="selection-form">
    <form name="epigenomeSearch" method="post" onSubmit="return checkquery()" encType="multipart/form-data">
        <table>
            <tr>
                <td><b>Species</b></td>
                <td>
                    <select name="species" id="classSelect">
                        <?php /* option 的 value 必须是数据表前缀（= $__proteomicSpecies 的键），
                                 不是 abbr1 短码：表名按 <键>_<组织>_proteomics 拼。 */ ?>
                        <?php foreach ($__proteomicSpecies as $__spK => $__spL): ?>
                            <option value="<?= htmlspecialchars($__spK) ?>"<?= ((string)$specie === (string)$__spK) ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__spL) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td><b>Stages:</b></td>
                <td>
                    <select name="hisType" id="speciesSelect">
                        <?php foreach ($__dolTypes as $__dolTypesV): ?>
                            <option value="<?= htmlspecialchars($__dolTypesV) ?>"<?= ((string)$hisType === (string)$__dolTypesV) ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__dolTypesV) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td><b>Emb/Org:</b></td>
                <td>
                    <select name="hisMark" id="epigenomeTypeSelect">
                        <?php foreach ($__dolMarks as $__dolMarksV): ?>
                            <option value="<?= htmlspecialchars($__dolMarksV) ?>"<?= ((string)$hisMark === (string)$__dolMarksV) ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__dolMarksV) ?></option>
                        <?php endforeach; ?>
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

<!-- 搜索框 -->
<div class="search-box">
    <input type="text" id="searchInput" placeholder="Search by protein, peptides, intensity...">
</div>

<?php
// ========== 分页逻辑 ==========
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

/* $tab 直接由 URL 参数拼成表名。除了「表可能不存在」，这里还是一条 SQL 注入口：
   ?hisMark=x WHERE 1=0 UNION SELECT ... -- 会被原样拼进 FROM 子句。先按标识符
   字符集过滤，再向库里核对这张表确实存在，两条一起把口子关掉。 */
$tab = $specie . "_" . $hisMark . "_proteomics";
$__tabOk = false;
if (preg_match('/^[A-Za-z0-9_]+$/', $tab)) {
    $__tq = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tab) . "'");
    $__tabOk = ($__tq && mysqli_num_rows($__tq) > 0);
}
if (!$__tabOk) {
    /* 上面已经按物种、组织归一过，走到这里说明数据集本身在库里就没有 ——
       给一条明确说明，而不是 mysqli 警告加一张没有表头的空表。 */
    $__tabNote = 'No <b>' . htmlspecialchars($hisMark) . '</b> proteomic dataset is available for <b>'
               . htmlspecialchars($__proteomicSpecies[$specie]) . '</b> in the current release.';
}

$total_records = 0;
if ($__tabOk) {
    $count_query  = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `$tab`");
    $count_result = $count_query ? mysqli_fetch_assoc($count_query) : null;
    $total_records = $count_result ? (int)$count_result['total'] : 0;
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
?>

<!-- 表格容器 -->
<div class="table-container">
    <table class="gridtable" id="myTable">
        <?php
		// 查询当前页数据（表不存在时不再发查询，下面单独给出说明行）
		$query = $__tabOk ? mysqli_query($conn, "SELECT * FROM `$tab` LIMIT $offset, $per_page") : false;
		$hisMark1 = str_replace('_', ' ', $hisMark);
		if(!$__tabOk){
			echo '<tr><td style="padding:16px;text-align:left">' . $__tabNote . '</td></tr>';
		}elseif($tab == "EDIAP_Whole_anemone_proteomics"){
			$spe="Exaiptasia diaphana";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>Q Value</th><th>Peptides</th><th>Unique Peptides</th><th>Coverage (%)</th><th>Mol. weight [kDa]</th><th>Score</th><th>Intensity</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td></tr>";
			}
		}elseif($tab == "Aiptasia_sp_anemones_proteomics"){
			$spe="Aiptasia sp.";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>Group</th><th>Protein Identification Probability</th><th>Unique Peptides</th><th>Unique Spectra</th><th>Total Spectra</th><th>Total Spectra Percent</th><th>Coverage</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td></tr>";
			}
		}elseif($tab == "Myxobilatus_gasterostei_stickleback_kidney_proteomics"){
			$spe="Myxobilatus gasterostei";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>-10lgP</th><th>Coverage (%)</th><th>Coverage (%) Sample</th><th>Area Sample</th><th>Peptides</th><th>Unique Peptides</th><th>Spec Sample</th><th>PTM</th><th>Avg. Mass</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td></tr>";
			}
		}elseif($tab == "CCRUX_tentacle_proteomics"){
			$spe="Calvadosia cruxmelitensis";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>-10lgP</th><th>Coverage (%)</th><th>Coverage (%) Sample</th><th>Area Sample</th><th>Peptides</th><th>Unique Peptides</th><th>Spec Sample</th><th>PTM</th><th>Avg. Mass</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td></tr>";
			}
		}elseif($tab == "Polypodium_hydriforme_stolon_proteomics"){
			$spe="Polypodium hydriforme";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>-10lgP</th><th>Coverage (%)</th><th>Coverage (%) Sample</th><th>Area Sample</th><th>Peptides</th><th>Unique Peptides</th><th>Spec Sample</th><th>PTM</th><th>Avg. Mass</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td></tr>";
			}
		}elseif($tab == "Buddenbrockia_plumatellae_myxoworms_proteomics"){
			$spe="Buddenbrockia bryozoides";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>-10lgP</th><th>Coverage (%)</th><th>Coverage (%) Sample</th><th>Area Sample</th><th>Peptides</th><th>Unique Peptides</th><th>Spec Sample</th><th>PTM</th><th>Avg. Mass</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td></tr>";
			}
		}elseif($tab == "EDIAP_Symbiont_Breviolum_minutum_proteomics" || $tab =="EDIAP_Symbiont_Durusdinium_trenchii_proteomics"){
			$spe="Exaiptasia diaphana";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>Q-value</th><th>Coverage (%)</th><th>Intensity</th><th>Peptides</th><th>Unique Peptides</th><th>Mol. weight [kDa]</th><th>Score</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td></tr>";
			}
		}elseif($tab == "NVECT_polyps_proteomics"){
			$spe="Nematostella vectensis";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>Modifications</th><th>Mass</th><th>Mass Fractional Part</th><th>Unique (Groups)</th><th>Unique (Proteins)</th><th>Acetyl (Protein N-term)</th><th>Oxidation (M)</th><th>Missed cleavages</th><th>Q-value</th><th>Score</th><th>Intensity</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td><td>$result[10]</td><td>$result[11]</td></tr>";
			}
		}elseif($tab == "NVECT_Embryos_proteomics"){
			$spe="Nematostella vectensis";
			echo " <tr><th width=\"12%\">Species</th><th>Tissue</th><th>Protein</th><th>q-value</th><th>Coverage [%]</th><th>Peptides</th><th>PSMs</th><th>Unique Peptides</th><th>MW [kDa]</th><th>calc. pI</th><th>Protein FDR Confidence</th><th>Modifications</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td></tr>";
			}
		}elseif($tab == "Stichopathes_sp_Coral_skeleton_proteomics"){
			$spe="Stichopathes sp.";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>Log(Probability)</th><th>Best Log(Probability)</th><th>Best score</th><th>Total Intensity</th><th>Spectra</th><th>Unique Peptides</th><th>Mod Peptides</th><th>Coverage (%)</th><th>AA in Proteins</th><th>Protein Number</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td><td>$result[10]</td></tr>";
			}
		}elseif($tab == "Antipathes_griggi_Coral_skeleton_proteomics"){
			$spe="Antipathes griggi";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>Log(Probability)</th><th>Best Log(Probability)</th><th>Best score</th><th>Total Intensity</th><th>Spectra</th><th>Unique Peptides</th><th>Mod Peptides</th><th>Coverage (%)</th><th>AA in Proteins</th><th>Protein Number</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td><td>$result[10]</td></tr>";
			}
		}elseif($tab == "MHONG_Cysts_proteomics" || $tab == "MHONG_Nematocysts_proteomics"){
			$spe="Myxobolus honghuensis";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>Peptides</th><th>Unique peptides</th><th>Best score</th><th>Coverage [%]</th><th>Mol. weight [kDa]</th><th>Q-value</th><th>Intensity</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td></tr>";
			}
		}elseif($tab == "Myxobolus_wulii_Cysts_proteomics" || $tab == "Myxobolus_wulii_Nematocysts_proteomics"){
			$spe="Myxobolus wulii";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>Peptides</th><th>Unique peptides</th><th>Best score</th><th>Coverage [%]</th><th>Mol. weight [kDa]</th><th>Q-value</th><th>Intensity</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td></tr>";
			}
		}elseif($tab == "TKITA_Cysts_proteomics" || $tab == "TKITA_Nematocysts_proteomics"){
			$spe="Thelohanellus kitauei";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>Peptides</th><th>Unique peptides</th><th>Best score</th><th>Coverage [%]</th><th>Mol. weight [kDa]</th><th>Q-value</th><th>Intensity</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td></tr>";
			}
		}elseif($tab == "Stylophora_pistillata_colony_proteomics"){
			$spe="Stylophora pistillata";
			echo " <tr><th width=\"15%\">Species</th><th>Tissue</th><th width=\"15%\">Protein</th><th>Log(Probability)</th><th>Best Log(Probability)</th><th>Best score</th><th>Total Intensity</th><th>Spectra</th><th>Unique Peptides</th><th>Mod Peptides</th><th>Coverage (%)</th><th>AA in Proteins</th><th>Protein Number</th></tr>";
			while ($result = mysqli_fetch_row($query))
			{
				echo "<tr align=\"center\"><td><i>$spe</i></td><td>$hisMark1</td><td>$result[0]</td><td>$result[1]</td><td>$result[2]</td><td>$result[3]</td><td>$result[4]</td><td>$result[5]</td><td>$result[6]</td><td>$result[7]</td><td>$result[8]</td><td>$result[9]</td><td>$result[10]</td></tr>";
			}
		}

        ?>
    </table>
</div>

<!-- 分页导航 -->
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            📊 Total proteomic records: <?= $total_records ?>
        </div>
        
        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?<?= $qs ?>&per_page='+this.value+'&page=1'">
                <?php
                $options = [10, 20, 50, 100];
                foreach ($options as $option) {
                    $selected = ($option == $per_page) ? 'selected' : '';
                    echo "<option value='$option' $selected>$option /page</option>";
                }
                ?>
            </select>
            <span>samples per page</span>
        </div>
    </div>
    
    <div class="pagination-nav">
        <!-- 首页 -->
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?<?= $qs ?>&per_page=<?= $per_page ?>&page=1" 
           class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">
            « First
        </a>
        
        <!-- 上一页 -->
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?<?= $qs ?>&per_page=<?= $per_page ?>&page=<?= max(1, $page-1) ?>" 
           class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">
            ‹ Previous
        </a>
        
        <!-- 页码 -->
        <?php
        // 显示当前页前后各3页
        $start_page = max(1, $page - 3);
        $end_page = min($total_pages, $page + 3);
        
        // 左侧省略号
        if ($start_page > 1) {
            echo '<a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '?' . $qs . '&per_page=' . $per_page . '&page=1" class="page-btn">1</a>';
            if ($start_page > 2) {
                echo '<span class="page-btn disabled">...</span>';
            }
        }
        
        // 中间页码
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '?' . $qs . '&per_page=' . $per_page . '&page=' . $i . '" class="page-btn ' . $active . '">' . $i . '</a>';
        }
        
        // 右侧省略号
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<span class="page-btn disabled">...</span>';
            }
            echo '<a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '?' . $qs . '&per_page=' . $per_page . '&page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
        }
        ?>
        
        <!-- 下一页 -->
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?<?= $qs ?>&per_page=<?= $per_page ?>&page=<?= min($total_pages, $page+1) ?>" 
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Next ›
        </a>
        
        <!-- 末页 -->
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?<?= $qs ?>&per_page=<?= $per_page ?>&page=<?= $total_pages ?>" 
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Last »
        </a>
    </div>
    
    <!-- 跳转到指定页 -->
    <div class="go-to-page">
        <span>Go to page:</span>
        <input type="number" id="gotoPage" min="1" max="<?= $total_pages ?>" value="<?= $page ?>">
        <button onclick="window.location.href='<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?<?= $qs ?>&per_page=<?= $per_page ?>&page='+document.getElementById('gotoPage').value">
            Go
        </button>
        <span>of <?= $total_pages ?> pages</span>
    </div>
</div>

</div>
</div>
</div>

<?php
    include "./Webpage_components.php";
    print $footer;
?>
</body>
</html>
