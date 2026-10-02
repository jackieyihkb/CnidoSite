<?php
/* ---------------------------------------------------------------------
 * 物种 / Class 下拉框改为服务端渲染，会话键按模块隔离。
 *
 * 原先本页读写全站共用的 $_SESSION['species']，而 MAGs 的值域是 MAGs.host
 * （宏基因组样品的宿主刺胞动物名，如 "Acropora cytherea"、"Fungia sp."、
 * "Goniastrea edwardsi/Porites lutea"），既不是 abbr1 短码也不是其它模块的
 * 拉丁名。于是在别的模块选完物种再进本页，下拉框会显示一个 MAGs 里根本不
 * 存在的物种、表格却是空的（Referee 1 minor 1、Referee 2 major 1）。
 * --------------------------------------------------------------------- */
require_once __DIR__ . '/includes/state.php';
/* 宿主 → 物种页的解析（cnido_spcov_resolve）取自覆盖矩阵，是站内判断
   「这个物种到底有没有物种页」的权威口径，speciesinfo.php 用的是同一个函数。
   本页原先改用 abbr 表查，而 abbr 只覆盖 30 个宿主里的 14 个 —— 见下方表格
   渲染处的说明。 */
require_once __DIR__ . '/includes/coverage.php';
require_once __DIR__ . '/includes/species_coverage_panel.php';

$__st = cnido_state('mags', array(
    'class'  => array('get' => 'class',   'default' => 'Hexacorallia'),
    'specie' => array('get' => 'species', 'default' => 'Acropora kenti'),
));
$class  = $__st['class'];
$specie = $__st['specie'];

/* 选项直接来自数据表，不再依赖 js/DynamicOptionList.js 里那份硬编码名单。
   $__hostClass 同时给出「宿主 → class」的映射：MAGs 表里一个宿主只属于一个
   class（当前 30 个宿主 / 3 个 class，无二义），所以可以由物种反推 class。 */
$__hostList  = array();
$__hostClass = array();
$__classList = array();
$__mc = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if (!$__mc->connect_error) {
    if ($q = mysqli_query($__mc, "SELECT host, `class` FROM MAGs GROUP BY host, `class` ORDER BY host")) {
        while ($r = mysqli_fetch_row($q)) {
            $__hostList[]       = $r[0];
            $__hostClass[$r[0]] = $r[1];
        }
    }
    if ($q = mysqli_query($__mc, "SELECT DISTINCT `class` FROM MAGs ORDER BY `class`")) {
        while ($r = mysqli_fetch_row($q)) { $__classList[] = $r[0]; }
    }
}

/* 按 class 取第一个宿主（下拉框里的顺序即 $__hostList 的顺序） */
if (!function_exists('cnido_mags_first_host')) {
    function cnido_mags_first_host($class, $hostList, $hostClass)
    {
        foreach ($hostList as $h) {
            if (isset($hostClass[$h]) && $hostClass[$h] === $class) { return $h; }
        }
        return '';
    }
}

/* 只指定了 class（例如按类群浏览）而没有指定物种时，服务端也要落到该 class 下
   的第一个宿主。否则表格显示的是另一个 class 的物种，而前端 magsFilterHosts()
   又会把下拉框切到本 class 的第一个宿主 —— 页面上下自相矛盾。 */
$__classExplicit = in_array($__st['class__from'], array('get', 'post'), true);
$__specieExplicit = in_array($__st['specie__from'], array('get', 'post'), true);

if ($__classExplicit && !$__specieExplicit && in_array($class, $__classList, true)) {
    $__pick = cnido_mags_first_host($class, $__hostList, $__hostClass);
    if ($__pick !== '') { $specie = $__pick; }
}

/* 回退、请求落空等情况都记在这里，页面下方统一提示；不含任何来自用户的 HTML */
$__magsNotice = array();

/* 请求的 class 在 MAGs 里根本没有宿主（如 Hydrozoa / Myxozoa）：
   说清楚，而不是默默换成默认类群。 */
if ($__classExplicit && $class !== '' && !in_array($class, $__classList, true)) {
    $__magsNotice[] = 'No MAG in the current release belongs to class '
                    . htmlspecialchars($class) . '.';
}

/* 请求的宿主没有 MAG 记录时说明清楚，而不是给一张空表。
   回退时优先落在同一 class 下，保持两个下拉框一致。 */
if ($specie !== '' && !in_array($specie, $__hostList, true)) {
    $__pick = cnido_mags_first_host($class, $__hostList, $__hostClass);
    if ($__pick === '') { $__pick = $__hostList ? $__hostList[0] : ''; }
    $__magsNotice[] = htmlspecialchars($specie) . ' has no MAG in the current release; showing '
                    . htmlspecialchars($__pick) . ' instead.';
    $specie = $__pick;
}

/* class 下拉框最终跟随选中的宿主（物种比 class 更具体，且映射唯一） */
if (isset($__hostClass[$specie])) { $class = $__hostClass[$specie]; }

/* 本页「已提交」的语义 = 有一个明确的宿主可以展示。
   原先 $formSubmitted 只看 $_POST，于是
     · 从物种门户 / 模块互链点进来的深链（GET ?species=…，见 species_portal.php
       与 includes/modlinks.php 的 "MAGs Catalog"）
     · 分页条 / 「Go to page」链接（同样是 GET）
   都会把 $specie 换成目标宿主，却仍然不渲染结果表与选择信息 —— 用户看到的
   就是「点了链接什么都没有」（Referee 1 minor 1、Referee 2 major 1）。
   现在只要最终有宿主可选（GET / POST / 会话 / 默认值任一来源）就渲染内容，
   只有库中一个宿主都没有时才退回纯表单。 */
$formSubmitted = ($specie !== '');

/* ========== 搜索框 ==========
 * 本页此前有一个 id=searchInput 的输入框，绑的是 jQuery 的 keyup 处理器：它过滤
 * 的只是**当前这一页**的 10 行（`$("#myTable tr:not(:first)")`），而下面的页码条
 * 仍旧报着全表的数。用户以为自己搜了整张表，其实只搜了一屏 —— 这比没有搜索框更坏。
 * 现在改成服务端 GET，并且串进 $__baseQs，翻页与排序都带着它。
 *
 * 搜索范围是**当前宿主**（页面本来就是这个模型）。但 315 个 MAG 里选错宿主就一条
 * 也搜不到，所以 scope=all 时把宿主条件摘掉、跨全部宿主搜，空结果处给了入口。 */
$q = cnido_search_term(isset($_GET['q']) ? $_GET['q'] : '');
$__allHosts = ($q !== '' && isset($_GET['scope']) && $_GET['scope'] === 'all');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>MAGs Catalog - CnidoSite</title>
<meta name="keywords" content="Cnidaria, metagenome, MAG, metagenome-assembled genome, microbial genome, coral microbiome" />
<meta name="description" content="308 metagenome-assembled genomes (MAGs) from 30 cnidarian hosts, with taxonomy, assembly statistics and functional annotation" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<?php /* 下拉框已改为服务端渲染（class 取自 MAGs.class，宿主取自 MAGs.host），
     不再需要 js/DynamicOptionList.js：该脚本的 initDynamicOptionLists() 会用
     硬编码名单清空并重建 <select>，正是「下拉框与结果对不上」的成因之一。 */ ?>
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

<?php /* 搜索框。原来这里是 .search-box（一个纯客户端过滤框，见页首说明），现在换成真正
   的服务端搜索表单 .mags-search。配色跟本页其它控件走同一个蓝（#1d4ed8）。 */ ?>
.mags-search {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 15px 20px;
    margin: 20px 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.mags-search label { font-weight: 600; color: #475569; }

.mags-search input[type=text] {
    flex: 1 1 320px;
    min-width: 200px;
    padding: 10px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
    transition: all 0.3s ease;
}

.mags-search input[type=text]:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.mags-search button {
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

.mags-search button:hover { background:#1e40af; }

<?php /* 上色必须写成 a.类名：templatemo_style.css 的 a:link,a:visited{color:#1d4ed8} 是
   (0,1,1)，单类名 (0,1,0) 压不住（见 memory: cnidosite-link-color-specificity-trap）。 */ ?>
a.mags-clear { color:#1d4ed8; text-decoration: none; font-size: 15px; font-weight: 500; padding: 6px 2px; }
a.mags-clear:hover { text-decoration: underline; }

.mags-scope { color: #64748b; font-size: 15px; }

.mags-hit {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 12px 18px;
    margin: 0 0 15px;
    color: #1e40af;
    font-size: 15px;
}

.mags-empty {
    text-align: center;
    padding: 40px;
    background: #f8fafc;
    border-radius: 12px;
    margin: 30px 0;
    color: #64748b;
}
.mags-empty h3 { color: #334155; margin-bottom: 10px; }
.mags-empty p { margin: 6px 0; }

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
    max-width: 500px;
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

<?php /* 选择信息显示样式 */ ?>
.species-selection-info {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
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
    
    .mags-search {
        flex-direction: column;
        align-items: stretch;
    }

    .mags-search input[type=text] {
        width: 100%;
        /* flex:0 0 auto 不能省：基础规则里的 flex:1 1 320px 在**竖排**容器里
           320 是"高度"基准（flex-basis 跟主轴走），只清 width 的话输入框会
           长成 320px 高，手机上就是搜索框底下一条大空白。 */
        flex: 0 0 auto;
    }
}

<?php /* ---------------------------------------------------------------------------
   表头：长的一律折成两行，不许出现第三行。

   病灶是 templatemo_style.css 的 `table.gridtable th{white-space:nowrap}`：12 个
   表头各自占满一行，表格的最小宽度就成了 12 段表头文字之和 —— 实测 1690px，
   而 .table-container 只有 1398px 且是全站规则的 overflow:hidden，于是右边的
   CheckM contamination (%) 和 Paper 在桌面端被**直接裁掉**（窄屏反而没事：
   ≤1200px 有 js/rwd-tables.js 套 .gt-scroll 横滑，被裁的恰好是 1201px 以上）。

   改法是给长表头**写死断点**（`<br>`，见下面 <th> 里的标签），并保留全站的
   nowrap。理由：只把 th 放成 white-space:normal，浏览器按列宽自己折，列一窄就
   折成三行（1366px 下实测 Assembled Size (bp) / Contig N50 (bp) / GC Percent (%)
   / 两个 CheckM 全是三行，表头行高 86px）；写死断点后每个表头最多两行，
   任何宽度下都不会出现第三行（nowrap 让每条断行**不能再被折**）。

   断行的位置按"该列数据有多宽"来选，不是按语义平均切：把长词放在第一行会让
   列的最小宽度由它决定（"Assembled Size<br>(bp)" 要 128px，而该列数据
   "3562629" 只要 80px）。所以写成 "Assembled<br>Size (bp)"、
   "Contig<br>N50 (bp)"、"GC<br>Percent (%)" —— 最长的一行都是短词。

   实测（表宽 / 容器宽，Acropora kenti，2026-09-27）：
     视口        1920   1600   1440   1366   1280   1265*
     折前(nowrap) 1690   1690   1690   1690   1690   1690   ← 全部溢出被裁
     折后         1586   1556   1396   1322   1236   1221   ← 都装得下
     * 1265 是 1280 屏的实际可视宽度（扣掉滚动条），也是这次收尾的那一档。

   下面那条内边距是最后 4px 的来源：12 列 × 全站标准 10px×2 = 240px，占掉
   容器 1238px 的 19%。两侧各减 1px（看不出来）换回 24px，1280 视口才从 +4
   （冒出一条横滑条）变成 -2。只在桌面档改，窄屏维持全站标准。

   唯一的例外仍是宿主名很长的物种（Goniastrea edwardsi/Porites lutea）：Host
   列**数据**本身就要 143px，1280 视口下还宽出 ~60px。那条由下面的 overflow-x
   兜底横滑（是"能滑"，不是"被裁"）。

   .table-container 兜底**只在桌面档（≥1201px）生效**：窄屏本来就归
   js/rwd-tables.js 管，而它会因为 alreadyScrollable() 看到祖先能横滑就
   **跳过**套壳 —— 不加媒体查询的话等于把窄屏那套 width:max-content + 桌面
   列宽横滑的写法关掉了。分工：≤1200 走 .gt-scroll，>1200 走这一条。
   --------------------------------------------------------------------------- */ ?>
@media (min-width: 1201px) {
    .table-container {
        overflow-x: auto;
        overflow-y: hidden;
        <?php /* 横滑到头不要触发浏览器的「后退」手势，与 .gt-scroll 同一条。 */ ?>
        overscroll-behavior-x: contain;
    }
    .table-container table.gridtable th,
    .table-container table.gridtable td { padding-left: 9px; padding-right: 9px; }
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
<?php /* Class 只做前端过滤；物种选项已由 PHP 渲染，脚本不执行也不会是空下拉框。 */ ?>
function magsFilterHosts() {
    var f = document.forms['atidsearch'];
    if (!f || !f['class'] || !f['species']) { return; }
    var want = f['class'].options[f['class'].selectedIndex].value;
    var org = f['species'], first = null;
    for (var i = 0; i < org.options.length; i++) {
        var o = org.options[i];
        var ok = (want === '' || o.getAttribute('data-class') === want);
        o.disabled = !ok;
        o.hidden = !ok;
        if (ok && first === null) { first = o; }
    }
    var cur = org.options[org.selectedIndex];
    if (first !== null && (!cur || cur.disabled)) { first.selected = true; }
}
</script>
</head>

<body onLoad="magsFilterHosts();">
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
            <li><a href="#" class="current">Metagenome</a>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Metagenome-Assembled Genomes (MAGs)</b></legend>
<p class="paleo-intro">This module presents a curated collection of microbial genomes (Metagenome-Assembled Genomes, MAGs) derived from diverse cnidarian hosts.</p>

<?php /* 物种选择表单 */ ?>
<div class="selection-form" >
    <?php /* 补上 action：原先没有 action，POST 会提交到当前 URL（连同残留的查询串），
         分页参数与深链参数容易互相覆盖。显式指向本页，GET 深链与 POST 提交行为一致。 */ ?>
    <form name="atidsearch" action="MAGs.php" method="post" onSubmit="return checkquery()" encType="multipart/form-data">
        <table>
            <tr>
                <td><b>Class</b></td>
                <td>
                    <select name="class" id="classSelect" onchange="magsFilterHosts()">
                        <?php foreach ($__classList as $__c): ?>
                            <option value="<?= htmlspecialchars($__c) ?>"<?= $__c === $class ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td><b>Species</b></td>
                <td>
                    <select name="species" id="speciesSelect">
                        <?php
                        /* 宿主与 Class 的对应关系已在页首取好（$__hostClass），
                           这里只负责渲染，data-class 供 magsFilterHosts() 前端过滤。 */
                        foreach ($__hostList as $__h):
                            $__hc = isset($__hostClass[$__h]) ? $__hostClass[$__h] : '';
                        ?>
                            <option value="<?= htmlspecialchars($__h) ?>" data-class="<?= htmlspecialchars($__hc) ?>"<?= $__h === $specie ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__h) ?></option>
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

<?php
// ========== 分页逻辑 ==========
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}

// 获取总记录数
/* $specie 来自 URL / 表单，拼进 SQL 前必须转义。 */
$specieEsc = mysqli_real_escape_string($conn, $specie);

/* ========== 表头排序（服务端） ==========
 * 这张表是分页的（LIMIT $offset, $per_page），而页面上除了结果表还有一张「选物种」
 * 的表单表 —— js/table-sort.js 判断「分页表」时看的是祖先容器里有几张 table，两张
 * 表就判不出来，于是这十行一直在浏览器里被重排（错的：第 2 页的最小值可能比第 1 页
 * 的最大值还小）。改成服务端表头链接，客户端脚本见到 data-sort-link 就整表跳过。
 *
 * 默认档用 null：原来的 SQL 没有 ORDER BY，行序由引擎决定，这里不主动换掉它 ——
 * 用户点了哪列才真正排。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array(
    'default'   => null,
    'class'     => cnido_sort_txt('`class`'),
    'host'      => cnido_sort_txt('host'),
    'species'   => cnido_sort_txt('Species'),
    'accession' => cnido_sort_txt('AssemblyAccession'),
    'level'     => cnido_sort_txt('AssemblyLevel'),
    /* 后面这几列在库里都是 text 装的数字：SIZE/contigs/N50 是纯整数，GC% 和两个
       CheckM 值有小数（GCPercent 有 153/315 行带小数，CheckM 两列有 200 多行是空串）。
       整数列走 cnido_sort_num（字典序下 "1000" 会排在 "261" 前面），带小数的走
       cnido_sort_dec —— 用错的话整列会被判成缺值沉底。 */
    'size'      => cnido_sort_num('AssembledSize'),
    'contigs'   => cnido_sort_num('Nrcontigs'),
    'n50'       => cnido_sort_num('ContigN50'),
    'gc'        => cnido_sort_dec('GCPercent'),
    'comp'      => cnido_sort_dec('CheckMcompleteness'),
    'contam'    => cnido_sort_dec('CheckMcontamination'),
    'paper'     => cnido_sort_txt('pubmed'),
);
/* 并列键用 AssemblyAccession：访问号在本表里唯一，翻页跨 LIMIT 边界才不会漏行。 */
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', 'AssemblyAccession');
$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');

$__filterQs = 'class=' . urlencode($class) . '&species=' . urlencode($specie)
            . ($q !== '' ? '&q=' . urlencode($q) : '')
            . ($__allHosts ? '&scope=all' : '');
$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
/* 九条翻页链接都是手拼的，原来把 class/species 写在 per_page 后面；现在统一用这份
   前缀（含排序与搜索词），排完翻页排序才不会悄悄退回默认次序。 */
$__baseQs   = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');

/* ========== 去重：GCA / GCF 是同一套组装的两个号 ==========
 * NCBI 把同一套组装同时挂在 GenBank（GCA_）和 RefSeq（GCF_）下，**数值部分相同的两个
 * 号就是同一个 assembly**。MAGs 表里有 7 个 GCF_ 行都有 GCA_ 孪生行，除访问号以外每列
 * 都一样（宿主、物种、TaxonID、组装级别、大小、contigs、N50、GC%、CheckM 全同），
 * 于是列表上看着就是「同一行印了两遍」——用户看到的就是这个。
 *
 * 只挡显示，不动库：MAGs 表仍 315 行，GCF_ 的 MAG_detail.php 页仍可按号直达（那 6 个
 * 访问号上挂的是 NCBI RefSeq 自己的注释，和本站跑在 GCA_ 上的那套不是同一批蛋白，
 * 删行会让那批注释失去入口）。保留 GCA_ 行：全站 123 套 GCA 注释 + 页面上原有的链接
 * 都指着它。
 *
 * 注意这条件必须同时进「计数」和「取数」两条 SQL，否则翻页会数出错位（$__where 两条
 * 共用），下面那两个补充计数也一样带。 */
$__dedupSql = "NOT (LEFT(AssemblyAccession, 4) = 'GCF_' AND EXISTS ("
            . "SELECT 1 FROM (SELECT AssemblyAccession FROM MAGs) `__gca` "
            . "WHERE `__gca`.AssemblyAccession = CONCAT('GCA_', SUBSTRING(MAGs.AssemblyAccession, 5))))";

/* 搜索条件。搜的是用户能看见的那几列：宿主、物种、组装号、组装级别、class。
   数值列（AssembledSize / N50 / GC% / CheckM）不进来 —— 往数字里搜 "10" 只会
   命中一堆毫不相干的组装。表只有 315 行且没有索引，全表扫描在这里不构成问题。 */
$__where = array($__dedupSql);
if (!$__allHosts) {
    $__where[] = "host = '$specieEsc'";
}
if ($q !== '') {
    $__where[] = cnido_like_any($conn, $q, array('`class`', 'host', 'Species', 'AssemblyAccession', 'AssemblyLevel'));
}
$__whereSql = $__where ? (' WHERE ' . implode(' AND ', $__where)) : '';

$count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM MAGs" . $__whereSql);
$count_result = mysqli_fetch_assoc($count_query);
$total_records = $count_result['total'];

/* 搜了但当前宿主下一条没中时，再查一次「不带搜索词、不带宿主」的总数，好告诉用户
   是「这个宿主没有 MAG」还是「有、但没一条含这个词」，以及别的宿主里有没有。 */
$__baseTotal = $total_records;
$__allHostHits = -1;
if ($q !== '' && $total_records == 0) {
    $__baseTotal = 0;
    if ($__cq = mysqli_query($conn, "SELECT COUNT(*) AS total FROM MAGs WHERE host = '$specieEsc' AND " . $__dedupSql)) {
        if ($__cr = mysqli_fetch_assoc($__cq)) { $__baseTotal = (int)$__cr['total']; }
    }
    if (!$__allHosts) {
        $__allHostHits = 0;
        if ($__cq = mysqli_query($conn, "SELECT COUNT(*) AS total FROM MAGs WHERE "
                . cnido_like_any($conn, $q, array('`class`', 'host', 'Species', 'AssemblyAccession', 'AssemblyLevel'))
                . ' AND ' . $__dedupSql)) {
            if ($__cr = mysqli_fetch_assoc($__cq)) { $__allHostHits = (int)$__cr['total']; }
        }
    }
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

// 查询当前页数据（条件与上面计数用的是同一份 $__whereSql，两处不会分叉）
if (!empty($specie) || $__allHosts) {
    $query = mysqli_query($conn, "SELECT * FROM MAGs" . $__whereSql . $__orderSql . " LIMIT $offset, $per_page");
} else {
    // 如果没有选择物种，可以查询所有或显示提示
    $query = mysqli_query($conn, "SELECT * FROM MAGs" . $__orderSql . " LIMIT $offset, $per_page");
}
?>

<?php if ($__magsNotice): ?>
<div class="gd-notice gd-warn" style="margin:10px 0"><?= implode(' ', $__magsNotice) ?></div>
<?php endif; ?>

<?php
/* 这里原本还有一个 $specie !== "Nematostella vectensis" 的硬编码排除项，
   用来避免为「没有 MAG 记录的物种」显示统计框。该情况现在由上面的
   $__hostList 回退 + $__magsNotice 统一处理，按宿主是否存在判断，
   不再按物种名写死。 */
?>
<?php if ($formSubmitted): ?>
<?php /* 显示当前选择信息 */ ?>
<div class="species-selection-info" style="
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    color: #0c4a6e;
    padding: 20px 25px;
    border-radius: 12px;
    margin: 30px 0;
    border: 2px solid #bae6fd;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    box-shadow: 0 4px 20px rgba(8, 145, 178, 0.1);
">
    <div style="display: flex; align-items: center; gap: 15px;">
        <div style="
            background:#0369a1;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        ">
            🔬
        </div>
        <div>
            <div style="font-size: 18px; font-weight: 700; color: #0369a1; margin-bottom: 5px;">
                Query Parameters
            </div>
            <div style="font-size: 16px; color: #0c4a6e;">
                <strong>Class:</strong> <?= htmlspecialchars($class) ?> •
                <strong>Species:</strong> <b><i><?= htmlspecialchars($specie) ?></i></b>
            </div>
        </div>
    </div>
    
    <div style="
        background: white;
        padding: 10px 20px;
        border-radius: 20px;
        font-size: 16px;
        font-weight: 700;
        color: #0c4a6e;
        border: 2px solid #bae6fd;
        display: flex;
        align-items: center;
        gap: 8px;
    ">
        <span style="color:#0369a1; font-size: 18px;">📊</span>
        <span><?= $total_records ?> metagenome-assembled genomes</span>
    </div>
</div>
<?php endif; ?>


<?php
/* 搜索框。刻意放在 $total_records 判断的**外面**：一条都没搜到时它也必须还在，
   否则用户除了浏览器后退没有别的路可走（原来那个客户端搜索框只长在有结果的分支里，
   一旦过滤成空就当页消失了）。 */
?>
<?php if ($formSubmitted): ?>
<form class="mags-search" method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="class" value="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="species" value="<?= htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="per_page" value="<?= (int)$per_page ?>" />
    <?php if ($__sortQs !== ''): ?>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($__sort, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="dir" value="<?= htmlspecialchars($__dir, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <?php if ($__allHosts): ?>
    <input type="hidden" name="scope" value="all" />
    <?php endif; ?>
    <label for="magsQ">Search MAGs</label>
    <input type="text" id="magsQ" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="assembly accession, species, assembly level&hellip;" />
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?>
    <a class="mags-clear" href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?class=<?= urlencode($class) ?>&amp;species=<?= urlencode($specie) ?>&amp;per_page=<?= (int)$per_page ?>">Clear</a>
    <?php endif; ?>
    <?php
    /* 搜的是当前宿主。跨宿主搜是另一档（scope=all），入口在空结果里 —— 放在这里当
       常驻开关的话，用户会以为「搜索」默认就该跨宿主，而表格上的宿主下拉就白选了。 */
    if (!$__allHosts && $q !== ''): ?>
    <span class="mags-scope">in <b><i><?= htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') ?></i></b>
        &middot; <a class="mags-clear" href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?class=<?= urlencode($class) ?>&amp;species=<?= urlencode($specie) ?>&amp;q=<?= urlencode($q) ?>&amp;scope=all">search all hosts</a></span>
    <?php elseif ($__allHosts): ?>
    <span class="mags-scope">across <b>all hosts</b></span>
    <?php endif; ?>
</form>

<?php if ($q !== '' && $total_records > 0): ?>
<div class="mags-hit">
    <?= $total_records ?> MAG(s) <?= $__allHosts ? 'across all hosts' : 'of <b><i>' . htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') . '</i></b>' ?>
    match &ldquo;<b><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></b>&rdquo;.
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($formSubmitted && $total_records > 0): ?>

<?php /* 表格容器 */ ?>
<div class="table-container">
    <table class="gridtable" id="myTable">
        <tr>
            <?php /* 长表头的 <br> 是**写死**的两行断点，不是让浏览器自己折：
                     配合全站的 th{white-space:nowrap}，每个表头最多两行、任何宽度
                     都不会出现第三行；断点位置按"该列数据有多宽"挑（见上面 <style>
                     里的说明）。comp/contam 两列的 <span title> 补回全称 ——
                     表头放不下 "CheckM completeness (%)"（要 148px 一列，两列就
                     把表撑出 1366px 的容器），缩写后是 99/108px。

                     Host 列**故意不写 width**（原来是 width="8%"）。表是
                     table-layout:auto + width:100%：标了 % 的列按 % 拿，剩下的
                     宽度归没标 % 的列分。Host 被 8% 钉死在 124-138px，而它右边
                     Class/Level/Size/contigs/N50/GC/Paper 这七个没标 % 的列各自
                     能拿走 110-170px（内容其实只要 80-90px）—— 宿主名于是**一律**
                     折成两行，20 个宿主里 19 个放不下。删掉这个 8% 后 Host 改为
                     按内容取宽。反直觉的是**其余列的 % 必须留着**：留着才有人跟
                     Host 抢剩余宽度，实测给 Host 的比"把所有 % 都删掉"还多
                     （1600 视口 Goniastrea 页 268px vs 243px）。

                     实测（Host 单元格折行的行数，2026-09-28）：
                       视口 1600               改前      改后
                         Goniastrea edwardsi…  75/75     0/75
                         Eunicella labiata     11/11     0/11
                         Aurelia sp. 4          2/2      0/2
                         全部 308 行            157        2
                     1366 及以下表已压到 min-content 下界，只在宿主名短的页面上
                     有效（Acropora kenti：1440 → 109/109 变 0/109；1280 改前改后
                     一样）。任何视口下总折行数、行高、横滑量都只减不增。
                     仍会折的只有 Aurelia sp. 3 sensu Dawson et al. (2005)
                     （要 302px，为它一列把表撑爆不值得）。 */ ?>
            <th><?= cnido_sort_link('class', 'Class', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('host', 'Host', $__sort, $__dir, $__filterQs) ?></th>
            <th width="13%"><?= cnido_sort_link('species', 'Species', $__sort, $__dir, $__filterQs) ?></th>
            <th width="8%"><?= cnido_sort_link('accession', 'Assembly<br>Accession', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('level', 'Assembly<br>Level', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('size', 'Assembled<br>Size (bp)', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('contigs', 'Nr.<br>contigs', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('n50', 'Contig<br>N50 (bp)', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('gc', 'GC<br>Percent (%)', $__sort, $__dir, $__filterQs) ?></th>
			<th width="8%"><?= cnido_sort_link('comp', '<span title="CheckM completeness (%)">CheckM<br>compl. (%)</span>', $__sort, $__dir, $__filterQs) ?></th>
			<th width="8%"><?= cnido_sort_link('contam', '<span title="CheckM contamination (%)">CheckM<br>contam. (%)</span>', $__sort, $__dir, $__filterQs) ?></th>
			<th><?= cnido_sort_link('paper', 'Paper', $__sort, $__dir, $__filterQs) ?></th>
        </tr>
        <?php
		/* ---------------------------------------------------------------
		 * 宿主 → 物种页的链接映射：在循环外一次算好。
		 *
		 * 原先循环体里每行发一条
		 *     SELECT * FROM abbr WHERE species = '<host>'
		 * 有两个问题：
		 *   1) N+1（一页 20 行 = 20 条额外查询），而且 host 直接拼进 SQL；
		 *   2) 更要紧的是 abbr 是手工维护的**短码表**，不是物种目录：它只覆盖 30 个
		 *      宿主里的 14 个。取不到时 $result1 是 null，拼出来的是
		 *      speciesinfo.php?species= —— 空参数，点开是一个空白物种页。
		 *      按 MAGs 表实算：16 个宿主取不到，涉及 242/315 个 MAG，其中包括
		 *      最大的两组（Acropora kenti 115 个、Goniastrea edwardsi/Porites lutea
		 *      75 个）。
		 * 改用 cnido_spcov_resolve()：它拿的是覆盖矩阵，也就是站内真正的物种
		 * 目录（speciesinfo.php 用的是同一个函数），能识别 abbr1、带下划线的写法
		 * 和拉丁学名。解析不出来就只显示宿主名、不给链接 —— 数据缺口照实呈现，
		 * 不给一个点开是空页的链接。
		 * --------------------------------------------------------------- */
		$__hostAbbr = array();
		$__cov = cnido_coverage($conn);
		foreach ($__hostList as $__h) {
			$__r = cnido_spcov_resolve($__cov, $__h);
			$__hostAbbr[$__h] = $__r ? $__r['abbr1'] : '';
		}

		while ($result = mysqli_fetch_row($query)){
			$__h  = isset($result[1]) ? $result[1] : '';
			$__ac = isset($result[4]) ? $result[4] : '';
			$__ab = isset($__hostAbbr[$__h]) ? $__hostAbbr[$__h] : '';
			/* 每个 MAG 的组装号指向新的本地详情页 MAG_detail.php；
			   NCBI 那条链作为次级入口保留在同一格里（原来它是这里唯一的目标）。 */
			echo "<tr align=\"center\">"
			   . "<td><a href=\"./browse.php?class=" . urlencode($result[0]) . "\">" . htmlspecialchars($result[0]) . "</a></td>"
			   . "<td><b><i>"
			   . ($__ab !== ''
			        ? "<a href=\"speciesinfo.php?species=" . urlencode($__ab) . "\">" . htmlspecialchars($__h) . "</a>"
			        : htmlspecialchars($__h))
			   . "</i></b></td>"
			   . "<td><a href=\"https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=" . urlencode($result[3]) . "\" target=\"_blank\">" . htmlspecialchars($result[2]) . "</a></td>"
			   . "<td><a href=\"MAG_detail.php?acc=" . urlencode($__ac) . "\" title=\"Open the detail page for this genome\">" . htmlspecialchars($__ac) . "</a>"
			   . "<br><span style=\"font-size:12px;white-space:nowrap\"><a href=\"https://www.ncbi.nlm.nih.gov/datasets/genome/" . urlencode($__ac) . "\" target=\"_blank\">NCBI &rarr;</a></span></td>"
			   . "<td>" . htmlspecialchars($result[5]) . "</td>"
			   . "<td>" . htmlspecialchars($result[6]) . "</td>"
			   . "<td>" . htmlspecialchars($result[7]) . "</td>"
			   . "<td>" . htmlspecialchars($result[8]) . "</td>"
			   . "<td>" . htmlspecialchars($result[9]) . "</td>"
			   . "<td>" . htmlspecialchars($result[10]) . "</td>"
			   . "<td>" . htmlspecialchars($result[11]) . "</td>"
			   . "<td><a href=\"" . htmlspecialchars($result[13]) . "\" target=\"_blank\">" . htmlspecialchars($result[12]) . "</a></td>"
			   . "</tr>";
		}
		?>
    </table>
</div>

<?php /* 分页导航 */ ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* 这个数是**当前宿主**（或跨宿主搜索结果）的条数，不是目录总数 ——
                     默认宿主 A. kenti 下是 109，切到 Octocorallia 会变成 2。原文写
                     "Total MAGs Number" 会让读者拿它当全量（全表 315 行、去重后 308）。
                     口径与下面那行 "N MAG(s) of <宿主>" 保持一致。 */ ?>
            📊 <?= $total_records ?> MAG(s)
            <?= $__allHosts ? 'across all hosts' : 'of <b><i>' . htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') . '</i></b>' ?>
        </div>
        
        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page='+this.value+'&page=1'">
                <?php
                $options = [10, 20, 50, 100];
                foreach ($options as $option) {
                    $selected = ($option == $per_page) ? 'selected' : '';
                    echo "<option value='$option' $selected>$option</option>";
                }
                ?>
            </select>
            <span>MAGs per page</span>
        </div>
    </div>
    
     <?php if ($total_pages > 0): /* 命中 0 条时不渲染分页条：$total_pages
         是 0，页码循环一次都不进，几个按钮和「of 0 pages」却照旧印出来，
         全部指向自己。站内约定见 browse.php / go_result.php。 */ ?>
<div class="pagination-nav">
        <?php /* 首页 */ ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=1" 
           class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">
            « First
        </a>
        
        <?php /* 上一页 */ ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=<?= max(1, $page-1) ?>" 
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
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $__baseQs . '&per_page=' . $per_page . '&page=1' . '" class="page-btn">1</a>';
            if ($start_page > 2) {
                echo '<span class="page-btn disabled">...</span>';
            }
        }
        
        // 中间页码
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $__baseQs . '&per_page=' . $per_page . '&page=' . $i . '" class="page-btn ' . $active . '">' . $i . '</a>';
        }
        
        // 右侧省略号
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<span class="page-btn disabled">...</span>';
            }
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $__baseQs . '&per_page=' . $per_page . '&page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
        }
        ?>
        
        <?php /* 下一页 */ ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=<?= min($total_pages, $page+1) ?>" 
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Next ›
        </a>
        
        <?php /* 末页 */ ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=<?= $total_pages ?>" 
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Last »
        </a>
    </div>
    
    <?php /* 跳转到指定页 */ ?>
    <div class="go-to-page">
        <span>Go to page:</span>
        <input type="number" id="gotoPage" min="1" max="<?= $total_pages ?>" value="<?= $page ?>">
        <button onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page='+document.getElementById('gotoPage').value+">
            Go
        </button>
        <span>of <?= $total_pages ?> pages</span>
    </div>    <?php endif; /* $total_pages > 0 */ ?>

</div>

<?php elseif ($formSubmitted && $total_records == 0): ?>
<?php if ($q !== ''): ?>
<?php /* 搜了但没中。三种情形给三种说法：「这个宿主本来就没有 MAG」「它有 N 个、但没一个
     含这个词」「本宿主没有、但别的宿主里有 M 个」—— 最后一种可以直接点过去。 */ ?>
<div class="mags-empty">
    <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
    <h3>Nothing matches &ldquo;<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>&rdquo;</h3>
    <?php if ($__allHosts): ?>
        <p>No MAG in the whole catalogue contains that term. Searched columns: host, species,
           assembly accession and assembly level.</p>
    <?php elseif ($__baseTotal == 0): ?>
        <p>The catalogue holds no MAG for <b><i><?= htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') ?></i></b>,
           so there is nothing to search. Pick another host above.</p>
    <?php else: ?>
        <p>None of the <?= (int)$__baseTotal ?> MAG(s) of
           <b><i><?= htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') ?></i></b> contains that term.</p>
        <?php if ($__allHostHits > 0): ?>
        <p><b><?= (int)$__allHostHits ?></b> MAG(s) of other hosts match it.</p>
        <p><a class="mags-clear" href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?class=<?= urlencode($class) ?>&amp;species=<?= urlencode($specie) ?>&amp;q=<?= urlencode($q) ?>&amp;scope=all">Search all hosts instead</a></p>
        <?php else: ?>
        <p>No MAG of any other host matches it either. Try a shorter term, or part of the
           assembly accession.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php else: ?>
<?php /* 筛选后没有任何行。原来这两句写的是 "No MetaGenomic Samples Found" /
         "No samples were found" —— 本页是 MAG 目录（标题 "MAGs Catalog"、区块标题
         "Metagenome-Assembled Genomes (MAGs)"），列的一行是一个 MAG 而不是一个
         宏基因组样本；上面那几支零结果文案也都写 "MAG(s)"。名词统一成 MAG。 */ ?>
<div class="no-results" style="text-align: center; padding: 40px; background: #f8fafc; border-radius: 12px; margin: 30px 0;">
    <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
    <h3 style="color: #64748b; margin-bottom: 10px;">No MAG Found</h3>
    <p style="color:#64748b;">No MAG in this catalogue matches the selected criteria. Please try different parameters.</p>
</div>
<?php endif; ?>
<?php endif; ?>

</div>
</div>
</div>

<script>
<?php /* 原来的 $("#searchInput").on("keyup", …) 已删除：它只过滤当前这一页的 10 行，而页码条
   仍报全表数 —— 见页首搜索框那段说明。搜索现在由服务端做（.mags-search 表单）。 */ ?>

// 确保选中的物种保持在下拉框中
document.addEventListener('DOMContentLoaded', function() {
    <?php /* 服务端选中值优先，只在本次请求没指定物种时才回退到 localStorage，
       且该值必须确实是本页的一个选项 —— 否则就会出现「换了物种之后方框里
       仍显示上一次的物种」（Referee 2 major 1）。 */ ?>
    const fromServer = <?= json_encode($__st['specie__from']) ?>;
    function pick(sel, val) {
        const el = document.querySelector(sel);
        if (!el || !val) { return; }
        const exists = Array.prototype.some.call(el.options, function (o) { return o.value === val; });
        if (exists) { el.value = val; }
    }
    if (fromServer === 'default') {
        pick('select[name="class"]', localStorage.getItem('selectedAlg'));
        pick('select[name="species"]', localStorage.getItem('selectedSpecies'));
    }
});

// 保存选择到localStorage
<?php /* 指名 atidsearch，不用 document.querySelector('form')：本页现在有两张表单（选择 +
   搜索），按「文档里第一张」取会在哪天调整了表单顺序时静默抓错，把搜索框的值当物种存
   进 localStorage。 */ ?>
document.forms['atidsearch'].addEventListener('submit', function() {
    const algValue = document.querySelector('select[name="class"]').value;
    const speciesValue = document.querySelector('select[name="species"]').value;
    localStorage.setItem('selectedAlg', algValue);
    localStorage.setItem('selectedSpecies', speciesValue);
});
</script>

<?php
    include "./Webpage_components.php";
    print $footer;
?>
</body>
</html>
