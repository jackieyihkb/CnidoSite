<?php
/* 审稿意见 Referee 2 major 1：物种门户（species_portal.php）要能把「某物种的基因组资源」
 * 直接深链到本页，即 /genomeinfo.php?species=<abbr1|拉丁名>。
 * 本页是全物种列表（没有物种下拉框），此前完全不看请求里的物种，深链进来只会看到
 * 325 行表格，等于「跳过来了但找不到那个物种」。现在按传入物种过滤到该行，并给出
 * 「显示全部」的回退链接；取值走 cnido_state()，会话键 genomeinfo_specie 按模块隔离。 */
require_once __DIR__ . '/includes/state.php';
/* Data Coverage Matrix 的渲染层。本文件只定义函数（取数才需要连接），所以在
   <head> 之前引入是安全的 —— 页尾那张矩阵的 CSS 要在 <head> 里输出。 */
require_once __DIR__ . '/includes/coverage_matrix_view.php';

$__st = cnido_state('genomeinfo', array(
    'specie' => array('get' => 'species', 'default' => ''),
));
$specie = $__st['specie'];
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Species Overview - CnidoSite</title>
<meta name="keywords" content="" />
<meta name="description" content="Genome assemblies for cnidarian species: assembly level, and whether each species has gene models and functional annotation, with links to its species portal." />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<?php
/* 页尾那张覆盖矩阵的样式（includes/coverage_matrix_view.php 的 cnido_cm_css()，
   返回值自带 <style> 标签）。与 coverage_matrix.php、data_statistics.php 同一份，
   三处的外观与列宽不会再各自漂移。 */
echo cnido_cm_css();
?>
<style type="text/css">
<?php /* 搜索框 + 分页控件的样式。形制与 browse.php 那一份完全相同（同一套类名、
   同一组配色），两个页面都是「物种总表 + 搜索 + 分页」，不该各长一个样子。
   站内的分页/搜索样式都写在页面自己的 <style> 里，没有共用文件 —— 这是现状，
   本页照做，不另起一套。 */ ?>

/* 锚点：搜索或翻页后浏览器要停在表上而不是页面顶部。 */
#genome-list { scroll-margin-top: 12px; }

.pagination-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 20px;
    margin-top: 18px;
    border: 1px solid #e2e8f0;
}

.pagination-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 15px;
}

.total-records {
    background: linear-gradient(135deg, #065f46 0%, #064e3b 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

.per-page-selector { display: flex; align-items: center; gap: 10px; }

.per-page-selector select {
    padding: 8px 15px;
    border-radius: 6px;
    border: 2px solid #e2e8f0;
    background: white;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.per-page-selector select:hover { border-color: #047857; }

.pagination-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin: 8px 0 4px;
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

.page-btn:hover { background: #f0fdf4; border-color: #047857; color: #047857; }
.page-btn.active { background: #047857; border-color: #047857; color: white; font-weight: 600; }
.page-btn.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }

.go-to-page {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
    justify-content: center;
    font-size: 15px;
    color: #475569;
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
    background: #047857;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.go-to-page button:hover { background: #065f46; }

<?php /* 搜索框。形制照 domain_search.php 的 .ds-form（本站现有的「列表页 + 搜索框」的
   样子：浅底卡片、服务端 GET），配色换成本页分页控件的 emerald。 */ ?>
.tax-search {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 18px;
    margin: 0 0 14px;
}

.tax-search label { font-weight: 600; color: #065f46; font-size: 15px; }

.tax-search input[type=text] {
    flex: 1 1 320px;
    min-width: 220px;
    padding: 10px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 15px;
    font-family: inherit;
    background: white;
    transition: all 0.3s ease;
}

.tax-search input[type=text]:focus {
    outline: none;
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
}

.tax-search button {
    padding: 10px 24px;
    background: #047857;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.tax-search button:hover { background: #065f46; }

<?php /* 选择器要写成 a.tax-search-clear：外链样式表里的 a:link, a:visited {color:#1d4ed8}
   是 (0,1,1)，裸类名 (0,1,0) 压不住它，链接会变成蓝色。 */ ?>
a.tax-search-clear { color: #64748b; font-size: 15px; text-decoration: underline; }

.tax-q { color: #065f46; font-weight: 600; }
.tax-hit { margin: 0 0 14px; color: #334155; font-size: 15px; }
.tax-none { color: #64748b; padding: 16px 8px; }

/* BUSCO 列：C% 与它下面的 S/D 一行。cell 里的链接挂 gridtable 的
   (0,1,2) 蓝色，数值要按自己的状态着色，所以在 <span> 上显式给色。 */
.gi-busco-num { font-weight: 600; }
.gi-busco-sub { font-size: 13px; color: #64748b; }
/* 两个 BUSCO 表头的第二行：谱系名。压小、不抢主标题的注意力，但仍要一眼看清
   两列各是哪个数据集。 */
.gi-busco-th { font-weight: 400; font-size: 12px; color: #64748b; }

@media (max-width: 768px) {
    .pagination-info { flex-direction: column; align-items: flex-start; }
    .tax-search input[type=text] { flex: 1 1 100%; }
    .pagination-nav { gap: 5px; }
    .page-btn { padding: 8px 12px; min-width: 36px; }
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Species Overview</b></legend>
<?php
/* ---------------------------------------------------------------------
 * 审稿意见 Referee 1 major 2 / Referee 2 points 12-13：
 *  - "有些物种只有组装文件、没有注释文件" —— 之前本页没有任何标注。
 *  - "97 chromosome / 173 scaffold / 55 contig across 325 cnidarians，
 *    但数据库又说 148 assembled genomes" —— 口径不一致。
 * 本页现在显式区分「有注释」与「仅组装」，并提供过滤与口径说明。
 * ------------------------------------------------------------------ */
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { error_log('CnidoSite: database connection failed'); die('The database is temporarily unavailable. Please try again in a moment.'); }

$only = isset($_GET['filter']) ? $_GET['filter'] : 'all';   // all | annotated | assembly
if (!in_array($only, array('all', 'annotated', 'assembly'), true)) { $only = 'all'; }

/* 本页有两个分页/筛选的表（上面这张物种表、页尾的 Data Coverage Matrix），
   所以物种表的四个参数一律带 `g` 前缀：gq（搜索词）/ gp（页码）/ gpp（每页行数）
   / gsort·gdir（排序）。裸的 q / page / per_page / sort 归页尾的覆盖矩阵
   （includes/coverage_matrix_view.php 的 cnido_cm_prepare() 直接读它们，没有
   覆盖入口）。不分开的话两边会互相牵：点一下矩阵的「第 2 页」上面的物种表跟
   着跳到第 2 页，矩阵的 per_page=20 又会被下拉框的 25/50/100/all 改掉（`all`
   经 (int) 变成 0，矩阵静默退回它自己的 20）。同一个坑 data_statistics.php 上
   已经踩过并定下这套命名（它的 Genome Assemblies 卡片就是 gq/gp/gpp），
   includes/genome_assembly_view.php 的开头也写着这一段。 */

/* 搜索词。**不走 cnido_state()**：本页的 species 走会话，是为了让物种深链在
   翻页/换筛选时还跟着；搜索词不一样 —— 它由搜索框与每一条链接显式带着走，
   留在会话里反而会「一搜就再也清不掉」（cnido_state 的空值语义那条注释记过
   这个坑）。值只从 $_GET 来，渲染前一律 htmlspecialchars。 */
$q     = cnido_search_term(isset($_GET['gq']) ? $_GET['gq'] : '');
$qHtml = htmlspecialchars($q, ENT_QUOTES, 'UTF-8');

/* 每页多少行。除了数字还认 `all` —— 326 行的表，读者常常想一次看全（也方便
   浏览器里 Ctrl+F）。$per_page 是「一页几行」，$perAll 为真时整个结果集一次
   发出去，此时 $per_page 不参与计算。 */
$__ppRaw = isset($_GET['gpp']) ? strtolower(trim((string)$_GET['gpp'])) : '';
$perAll  = ($__ppRaw === 'all');
$per_page = ($__ppRaw === '' ? 25 : intval($__ppRaw));
if (!$perAll && $per_page <= 0) { $per_page = 25; }
$__ppChoices = array(25, 50, 100, 'all');

/* 把深链传进来的物种统一成拉丁名再比对：门户给的是 abbr1 代码（NVECT），
 * 而本页行的键是 speciesinfo.Latin_name（speciesinfo.abbr 也是代码，一并比对）。 */
$spLatin = '';
$spFilter = '';       // 非空表示本次只看这一个物种
$spHasRow = false;    // 该物种在本页是否真有记录
if ($specie !== '') {
    $__e = mysqli_real_escape_string($conn, $specie);
    $__q = mysqli_query($conn, "SELECT species FROM abbr WHERE abbr1 = '$__e' OR abbr = '$__e' OR species = '$__e' LIMIT 1");
    if ($__q) { $__r = mysqli_fetch_row($__q); if ($__r) { $spLatin = $__r[0]; } }
    if ($spLatin === '') { $spLatin = $specie; }   // 不在 abbr 表里则按原值比对，匹配不到就是 (unavailable)
    $spFilter = $spLatin;

    // 深链的物种在本页是否真有组装记录（用于给出提示文案）
    $__e2 = mysqli_real_escape_string($conn, $spFilter);
    $__q2 = mysqli_query($conn, "SELECT COUNT(*) FROM speciesinfo WHERE Latin_name = '$__e2' OR abbr = '$__e'");
    if ($__q2) { $__r2 = mysqli_fetch_row($__q2); $spHasRow = ($__r2[0] > 0); }
}

/* 口径与首页共用 includes/stats.php：以库里实际存在的表为准。
   不再读 speciesinfo.Protein_number / proteincoding_gene_number —— 那两列是手工维护的
   元数据，和实际存在的表在 52 个物种上对不上（29 个有 _locus 表却两列都是 '-'，
   23 个两列有数字却一张表都没有），本页此前因此把 52 行标签挂反，总数也少了 3。 */
require_once __DIR__ . '/includes/stats.php';
$__sets  = cnido_annotation_sets($conn);
$gmLatin = cnido_prefixes_to_latin($conn, $__sets['gene_models']);   // 145，有基因模型
$anLatin = cnido_prefixes_to_latin($conn, $__sets['annotation']);    // 148，有功能注释表

$stat = array('total' => 0, 'annotated' => 0, 'annot_only' => 0, 'assembly_only' => 0, 'levels' => array());
/* 查询句柄叫 $__q 而不是 $q：本站的搜索框一律把搜索词放在 $q 里（browse.php /
   metagenomic_data.php 都是），本页现在也有搜索框了。两者重名过一次 —— $q 被这里
   覆盖成 mysqli_result 之后，下面 `$q !== ''` 仍然成立，于是搜索词变成结果对象，
   urlencode() 静默给出 null、拼出 `&q=`，最终在 cnido_like_any 里烂掉整张页面
   （Web SAPI 上这种错是静默的，页面尾部直接断掉）。 */
$__q = mysqli_query($conn, "SELECT Assembl_level, Latin_name FROM speciesinfo");
while ($r = mysqli_fetch_row($__q)) {
    $stat['total']++;
    $lvl = trim($r[0] === null ? '' : $r[0]);
    if ($lvl !== '' && $lvl !== '-') { $stat['levels'][$lvl] = (isset($stat['levels'][$lvl]) ? $stat['levels'][$lvl] : 0) + 1; }
    $key = cnido_latin_key($r[1]);
    if (isset($gmLatin[$key]))     { $stat['annotated']++; }      // 有基因模型
    elseif (isset($anLatin[$key])) { $stat['annot_only']++; }     // 只有功能注释表，没有基因模型
    else                           { $stat['assembly_only']++; }  // 两者皆无
}
/* 「没有基因模型」= 上面后两类之和，页面的第三个过滤按钮用它。 */
$stat['no_gene_models'] = $stat['annot_only'] + $stat['assembly_only'];
ksort($stat['levels']);
$chrom = 0;
foreach ($stat['levels'] as $k => $v) { if (stripos($k, 'chromosome') !== false || stripos($k, 'complete') !== false) { $chrom += $v; } }

/* ---------------------------------------------------------------------
 * 表头排序（服务端）
 *
 * 这张表现在分页了，所以排序不能再交给 js/table-sort.js：它只在浏览器里重排
 * 当页那 20 行，翻到第 2 页看到的最小值可能比第 1 页的最大值还小 —— 看起来
 * 「排好了」，其实是错的（includes/sort_head.php 开头那段说的就是这个）。
 * 表头改成真链接 ?gsort=…&gdir=…（前缀见下），翻页链接原样带着走。
 *
 * 默认档 'default' 不在表头里：它就是本页原来的次序（类群按 cnido_class_order
 * 的站内顺序、类内按学名）。**分页之后默认次序必须是确定的** —— 不加 ORDER BY
 * 时行序由存储引擎给，跨 LIMIT 边界时同一行会在两页上都出现、或者一页都不出现。
 * 表头的类群列箭头因此显示为「未选中」，而不是谎称「现在是按我排的」。
 * ------------------------------------------------------------------ */
require_once __DIR__ . '/includes/sort_head.php';
$__classOrderExpr = 'FIELD(s.`Class`, ' . implode(', ', array_map(function ($c) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $c) . "'";
    }, cnido_class_order())) . ')';
$__sortKeys = array(
    'default' => $__classOrderExpr,
    'class'   => 's.`Class`',
    'species' => 's.Latin_name',
    /* NCBI_taxonomy_ID / Size / year / Pubmed_ID 都是 text 列（Size 带小数、
       PubMed 列里还混着 '-'），直接 ORDER BY 是按字典序排的：'1247.88' 会排在
       '445.68' 前面、缺值 '-' 会全跑到升序的最前面。三个构造函数各管一类，
       别混用（见 sort_head.php 里各自的说明）。 */
    'taxid'   => cnido_sort_num('s.NCBI_taxonomy_ID'),
    'size'    => cnido_sort_dec('s.Size'),
    'level'   => 's.Assembl_level',
    /* BUSCO 列在 busco_assembly 里（按物种 + 谱系 LEFT JOIN 进来），没跑的行是
       NULL —— cnido_sort_dec 让缺值不分升降一律垫底。 */
    'busco'   => cnido_sort_dec('ga.pct_complete'),
    /* 第二列有**自己的**排序键（busco2），不共用 busco：两列数值不同，共用一个键
       的话点哪一列表头都是在排同一列。 */
    'busco2'  => cnido_sort_dec('gm.pct_complete'),
    'year'    => cnido_sort_num('s.year'),
    'pubmed'  => cnido_sort_num('s.Pubmed_ID'),
);
/* 并列键：学名 + 物种码。单列排序配 LIMIT 时，并列行的边界上会重复或消失，
   所以每一档都必须并列到唯一。最后一个参数 'g' 是参数前缀 —— 本页页尾的覆盖
   矩阵也用 sort/dir（键是 class/name/score），共用裸名的话点这张表的表头会
   顺手把矩阵的排序重置回 class。 */
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', array('s.Latin_name', 's.abbr'), 'g');

/* ---------------------------------------------------------------------
 * 查询串：全页的链接都从这里拼
 *
 * 搜索框、三个过滤按钮、表头排序链接、翻页链接、清除链接，各拼一份是本页最
 * 容易出的错 —— 漏一个参数，点一下就把用户刚才的选择悄悄丢回默认（物种深链
 * 那一轮已经栽过一次：三个过滤按钮原样带着 &species=PMULT，点「All species
 * (326)」只出 1 行）。
 *
 * species= 一律**显式**出现（哪怕是空串）：cnido_state() 把「参数缺席」当作
 * 「读会话」，只在链接里省略 species 会让深链进来的物种在翻页时又冒出来。
 * ------------------------------------------------------------------ */
$__qQs    = ($q !== '' ? '&gq=' . urlencode($q) : '');
$__filtQs = 'species=' . urlencode($specie) . '&filter=' . urlencode($only) . $__qQs;
$__sortQs = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default', 'g');
$__ppQsText = ($perAll ? 'all' : (int)$per_page);
/* 不带 gpp 的那一份：每页条数下拉框要自己接上新的值，带上就是两个 gpp=。 */
$__noPpQs = $__filtQs . ($__sortQs !== '' ? '&' . $__sortQs : '');
$__ppQs   = $__filtQs . '&gpp=' . $__ppQsText;
$__baseQs = $__noPpQs . '&gpp=' . $__ppQsText;
/* 「显示全部物种」与三个过滤按钮用：物种位是显式空串（清除会话值），搜索词照旧
   带着，filter 由调用处各自接上（所以这里不带 filter，免得拼出两个 filter=）。 */
$__clearSpecieQs = 'species=' . $__qQs;
$__listAnchor = '#genome-list';
?>
<p class="paleo-intro">
    This module lists all <b><?php echo $stat['total']; ?></b> cnidarian species for which an assembly is recorded in
    CnidoSite. Of these, <b><?php echo $stat['annotated']; ?></b> have a gene-model table (protein-coding gene
    coordinates), which is what the gene-by-gene modules are built on. The remaining
    <b><?php echo $stat['no_gene_models']; ?></b> have no gene models:
    <b><?php echo $stat['assembly_only']; ?></b> are <b>assembly-only</b>, and
    <b><?php echo $stat['annot_only']; ?></b> carry functional annotation (GO/InterPro/Pfam/PANTHER/KEGG) but no
    gene-model table. They can still be searched in <a href="/domain_search.php">Functional Domain Search</a>,
    which reads the annotation tables directly, but they are not in <a href="/search.php">Gene Search</a>.
    All three states are marked below.
</p>

<div style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #3b82f6;border-radius:10px;padding:14px 18px;margin:0 0 16px;color:#334155;font-size:16px;line-height:1.75">
    <b>Assembly-level counts.</b>
    <?php $__l = array(); foreach ($stat['levels'] as $k => $v) { $__l[] = htmlspecialchars($k) . ' <b>' . $v . '</b>'; } echo implode(' &middot; ', $__l); ?>.
    Chromosome-level assemblies (chromosome + complete genome) therefore number <b><?php echo $chrom; ?></b>.
    <br>
    <b>Why do the counts differ between pages?</b> The assembly-level breakdown above describes every assembly
    deposited for the phylum (including assembly-only records). Modules that need gene models &mdash; annotation,
    orthology, expression, single-cell, proteomics &mdash; can only use the
    <b><?php echo $stat['annotated']; ?></b> genomes that have gene models, so those pages report a smaller denominator.
    Wherever a paper figure quotes a different total this is the reason, and the per-page denominator is now
    stated on the page itself.
</div>

<?php if ($specie !== ''): ?>
<div style="background:<?= $spHasRow ? '#f0f9ff' : '#fffbeb' ?>;border:1px solid <?= $spHasRow ? '#bae6fd' : '#fde68a' ?>;border-left:4px solid <?= $spHasRow ? '#0ea5e9' : '#f59e0b' ?>;border-radius:10px;padding:12px 18px;margin:0 0 16px;color:#334155;font-size:16px">
    <?php if ($spHasRow): ?>
        Showing the assembly record for <b><i><?= htmlspecialchars($spLatin) ?></i></b> only
        (deep link <code>species=<?= htmlspecialchars($specie) ?></code>).
    <?php else: ?>
        No assembly record for <b><i><?= htmlspecialchars($spLatin) ?></i></b> in CnidoSite yet
        (deep link <code>species=<?= htmlspecialchars($specie) ?></code>) &mdash; the table below is therefore empty.
    <?php endif; ?>
    <?php /* 这里必须显式带上空的 species=：cnido_state() 把「参数存在但为空串」
        当作清除会话值的写法，而只写 ?filter=all 时 species 参数缺席，会走
        会话回退，把 genomeinfo_specie 里记着的那个物种又捡回来 —— 链接于是
        「点了没反应」。搜索词与每页条数一并带着，别点一下就把搜索清掉。 */ ?>
    <a href="genomeinfo.php?<?= htmlspecialchars($__clearSpecieQs, ENT_QUOTES, 'UTF-8') ?>&amp;filter=<?= urlencode($only) ?>&amp;gpp=<?= $__ppQsText ?>"
       style="color:#0369a1">show all species</a>
</div>
<?php endif; ?>

<div style="margin:0 0 14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span style="font-weight:600;color:#475569;font-size:15px">Show:</span>
    <?php
    $filters = array(
        'all'        => 'All species (' . $stat['total'] . ')',
        'annotated'  => 'With gene models (' . $stat['annotated'] . ')',
        'assembly'   => 'Without gene models (' . $stat['no_gene_models'] . ')',
    );
    /* 这三个按钮切的是「看全库的哪一批」，标签上的 326/145/181 就是全库口径的条数，
       所以点它们必须把物种深链一起清掉：带上空的 species= 走 cnido_state() 的显式清除，
       和上面的 "show all species" 是同一条路。此前这三个按钮原样带着 &species=PMULT，
       深链进来后点 "All species (326)" 只会得到 1 行、"With gene models" 得到 0 行 ——
       标签和结果对不上，看着就像按钮坏了。

       搜索词反过来**要**留着：这三个按钮切的是「看全库的哪一批」，搜索是在这批里
       再筛一层，点一下就把搜索清掉会让人莫名其妙。两者叠加时条数由表上方的
       「N of 326 species match …」那句说明，标签上的 326/145/181 始终是全库口径。 */
    foreach ($filters as $k => $lbl) {
        $active = ($only === $k);
        $href = 'genomeinfo.php?' . htmlspecialchars($__clearSpecieQs, ENT_QUOTES, 'UTF-8')
              . '&amp;filter=' . urlencode($k)
              . '&amp;gpp=' . $__ppQsText
              . $__listAnchor;
        echo '<a href="' . $href . '" style="text-decoration:none;font-size:15px;padding:6px 14px;border-radius:20px;border:1px solid '
           . ($active ? '#2563eb' : '#e2e8f0') . ';background:' . ($active ? '#2563eb' : '#fff') . ';color:'
           . ($active ? '#fff' : '#334155') . ';font-weight:' . ($active ? '600' : '500') . '">' . htmlspecialchars($lbl) . '</a>';
    }
    ?>
</div>

<?php
/* =====================================================================
 * 列表的取数：搜索（SQL）→ 有无基因模型（PHP）→ 分页切片
 *
 * SQL 只做「搜索词」和「物种深链」两件事 —— 两者都能用 state.php 里那套统一的
 * LIKE 转义。「有没有基因模型」判不出来：那是「库里有没有 <ABBR>_locus 表」，
 * 不是某一列的值，所以那一层过滤仍在 PHP 里做（判据与上面 $stat 的统计完全同
 * 一份 $gmLatin/$anLatin，两处不会分叉）。326 行本来也是整表取回来的，代价没变。
 *
 * 取全量再切片还有一个好处：分页条上的数、搜索提示里的数、真正渲染的行数出自
 * 同一个数组，不会出现「计数说 12 条、表里却印了 10 条」。
 * ================================================================== */
$__where = array();
if ($q !== '') {
    /* 搜的列就是表格上看得见的那几列。列名写死在这里 —— cnido_like_any() 只负责
       转义词，不校验列名，请求里的东西不能进来。 */
    $__where[] = cnido_like_any($conn, $q, array(
        's.`Class`', 's.Latin_name', 's.abbr', 's.NCBI_taxonomy_ID',
        's.Assembl_level', 's.year', 's.Pubmed_ID',
    ));
}
if ($spFilter !== '') {
    /* 深链只留该物种那一行（拉丁名或 speciesinfo.abbr 命中即可）。与原来的
       strcasecmp 等价（库的排序规则是 _ai_ci，等值比较同样不分大小写），
       trim 保留 —— 学名带尾随空格时等值比较命不中，而原代码是先 trim 再比的。 */
    $__e2 = mysqli_real_escape_string($conn, $spFilter);
    $__e3 = mysqli_real_escape_string($conn, $specie);
    $__where[] = "(TRIM(s.Latin_name) = '$__e2' OR s.abbr = '$__e3')";
}
$__whereSql = $__where ? (' WHERE ' . implode(' AND ', $__where)) : '';

/* 两列 BUSCO 都来自 busco_assembly：**genome 模式**跑在组装序列上（物种自己那个
   accession），一列一个谱系数据集。键用 abbr1 = speciesinfo.abbr；接不上的行是
   NULL 而不是 0 —— 0 会被读成「完整度为零」，那是另一回事。

   为什么是 genome 模式而不是 busco_summary 那套 proteins 模式（这次返工的原因）：
   本页是 Genomic Data 页，读者问的是「这个组装有多完整」。busco_summary 是把
   **已提交的蛋白集**拿去搜的，量的是注释好到什么程度，而且只有 148 个物种有蛋白
   集 —— 326 行里 178 行永远填不上。组装是每个物种都有的东西，genome 模式才对得上
   这一页的语义。proteins 模式那套数仍然在（BUSCO Genes 模块 / /core/ 的阈值都建在
   它上面），只是不属于这一页。

   两列之间不可直接换算，因为两个效应方向相反：cnidaria 集的 marker 是 metazoa 集的
   3.4 倍，多出来的那些常在类群的部分分支里真的不存在（Missing）；而 metazoa 集更广，
   它的 marker 更容易断成 Fragmented。谁占上风逐物种不定。可比的是同一列上下两个
   物种，或同一行左右两个数构成的对照。

   列序：0..24 是 speciesinfo 自己的 25 列（老代码按下标取，别动）；25..33 是第一列
   的 9 列（主谱系 cnidaria_odb12），34..42 是第二列的 9 列（metazoa_odb12.2）。 */
$__buscoLineage1 = 'cnidaria_odb12';
$__buscoLineage2 = 'metazoa_odb12.2';
$__buscoCols = ', ga.pct_complete AS busco_c, ga.pct_single AS busco_s, ga.pct_duplicated AS busco_d,
                   ga.pct_fragmented AS busco_f, ga.pct_missing AS busco_m, ga.lineage AS busco_lineage,
                   ga.lineage_size AS busco_size, ga.high_quality AS busco_hq, ga.accession AS busco_acc';
$__buscoAltCols = ', gm.pct_complete AS busco2_c, gm.pct_single AS busco2_s, gm.pct_duplicated AS busco2_d,
                      gm.pct_fragmented AS busco2_f, gm.pct_missing AS busco2_m, gm.lineage AS busco2_lineage,
                      gm.lineage_size AS busco2_size, gm.high_quality AS busco2_hq, gm.n_buscos AS busco2_n';
$__lin1Esc = mysqli_real_escape_string($conn, $__buscoLineage1);
$__lin2Esc = mysqli_real_escape_string($conn, $__buscoLineage2);
$query = mysqli_query($conn,
    'SELECT s.*' . $__buscoCols . $__buscoAltCols . ' FROM speciesinfo s'
    /* 谱系条件写在 ON 里，不写 WHERE：busco_assembly 主键是 (abbr1, lineage)，只按
       abbr1 接会把一个物种变成两行；写成 WHERE 又会把没有这一套数的物种整行滤掉，
       LEFT JOIN 就白做了。 */
    . " LEFT JOIN busco_assembly ga ON ga.abbr1 = s.abbr AND ga.lineage = '$__lin1Esc'"
    . " LEFT JOIN busco_assembly gm ON gm.abbr1 = s.abbr AND gm.lineage = '$__lin2Esc'"
    . $__whereSql . ($__order !== '' ? ' ORDER BY ' . $__order : ''));

$rows = array();
while ($query && ($result = mysqli_fetch_row($query))) {
	$key   = cnido_latin_key($result[1]);
	$hasGM = isset($gmLatin[$key]);   // 有基因模型（_locus 表）
	$hasAN = isset($anLatin[$key]);   // 有功能注释表（GO/InterPro/Pfam/PANTHER/KEGG）
	if ($only === 'annotated' && !$hasGM) { continue; }
	if ($only === 'assembly'  &&  $hasGM) { continue; }

	/* BUSCO 一格两态：有这一套数值就画，没有就是 null（画破折号）。
	   没有的情况只有两种 —— 这个物种在 genome_assembly 里没有可用 accession（所以
	   没得跑），或者这一套还没跑完。两者在页面上是同一个记号，tooltip 里说清。 */
	$busco = null;
	if ($result[25] !== null && $result[25] !== '') {
		$busco = array(
			'c' => (float)$result[25], 's' => (float)$result[26],
			'd' => (float)$result[27], 'f' => (float)$result[28], 'm' => (float)$result[29],
			'lineage' => $result[30], 'size' => (int)$result[31], 'hq' => ((int)$result[32] === 1),
			'acc' => (string)$result[33],
		);
	}

	/* 第二列（metazoa_odb12.2），同样的 9 列，结构完全一致。 */
	$busco2 = null;
	if ($result[34] !== null && $result[34] !== '') {
		$busco2 = array(
			'c' => (float)$result[34], 's' => (float)$result[35],
			'd' => (float)$result[36], 'f' => (float)$result[37], 'm' => (float)$result[38],
			'lineage' => $result[39], 'size' => (int)$result[40], 'hq' => ((int)$result[41] === 1),
			'n' => (int)$result[42],
		);
	}
	$rows[] = array('r' => $result, 'gm' => $hasGM, 'an' => $hasAN, 'busco' => $busco, 'busco2' => $busco2);
}

/* ---------- 分页 ---------- */
$total = count($rows);
$page  = isset($_GET['gp']) ? intval($_GET['gp']) : 1;
if ($page <= 0) { $page = 1; }
$total_pages = $perAll ? ($total > 0 ? 1 : 0) : (int)ceil($total / $per_page);
if ($total_pages > 0 && $page > $total_pages) { $page = $total_pages; }
$offset   = $perAll ? 0 : ($page - 1) * $per_page;
$pageRows = $perAll ? $rows : array_slice($rows, $offset, $per_page);
?>

<div id="genome-list">

<?php /* 搜索框。刻意不放在「有结果」的分支里：一条没搜到时它也得在，否则用户除了
     浏览器后退没有别的路可走。GET 表单会用表单里的字段**替换**整个查询串，所以
     物种深链、过滤、每页条数、排序都得用 hidden 带过去，否则一搜就全退回默认。 */ ?>
<form class="tax-search" method="get" action="genomeinfo.php<?= $__listAnchor ?>">
    <input type="hidden" name="species" value="<?= htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="filter" value="<?= htmlspecialchars($only, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="gpp" value="<?= $__ppQsText ?>" />
    <?php if ($__sortQs !== ''): ?>
    <input type="hidden" name="gsort" value="<?= htmlspecialchars($__sort, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="gdir" value="<?= htmlspecialchars($__dir, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <label for="giQ">Search this list</label>
    <input type="text" id="giQ" name="gq" value="<?= $qHtml ?>" autocomplete="off"
           placeholder="species, abbreviation, class, taxonomy ID, assembly level, year or PubMed ID" />
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?>
    <?php /* 只清搜索词，其余一概不动 —— 按 $__baseQs 去掉 q 重拼，别手写一份。 */ ?>
    <a class="tax-search-clear" href="genomeinfo.php?species=<?= urlencode($specie) ?>&amp;filter=<?= urlencode($only) ?>&amp;gpp=<?= $__ppQsText ?><?= $__sortQs !== '' ? '&amp;' . htmlspecialchars($__sortQs, ENT_QUOTES, 'UTF-8') : '' ?>">Clear</a>
    <?php endif; ?>
</form>

<?php if ($q !== ''): ?>
<?php /* 命中数单独说一句：三个过滤按钮上的 326/145/181 是全库口径，搜索之后那几
     个数与表里的行数就对不上了，读者需要知道分母是什么。 */ ?>
<p class="tax-hit">
    <?php if ($total === 0): ?>
        <b>No</b> species<?= $only === 'all' ? '' : ' in this filter' ?> match <span class="tax-q"><?= $qHtml ?></span>.
        Searchable columns are species name, abbreviation, class, NCBI taxonomy ID,
        assembly level, release year and PubMed ID &mdash; try a shorter term.
        <a class="tax-search-clear" href="genomeinfo.php?species=<?= urlencode($specie) ?>&amp;filter=<?= urlencode($only) ?>&amp;gpp=<?= $__ppQsText ?><?= $__sortQs !== '' ? '&amp;' . htmlspecialchars($__sortQs, ENT_QUOTES, 'UTF-8') : '' ?>">Show all <?= (int)$stat['total'] ?> species</a>
    <?php else: ?>
        <b><?= (int)$total ?></b> of <b><?= (int)$stat['total'] ?></b> species<?= $only === 'all' ? '' : ' in this filter' ?>
        <?= $total === 1 ? 'matches' : 'match' ?> <span class="tax-q"><?= $qHtml ?></span>.
    <?php endif; ?>
</p>
<?php endif; ?>

<?php /* 两列 BUSCO 的口径说明，放在表格正上方：读者看到左右两个数不一样，第一反应
     是「哪个才是对的」—— 这一段回答的是「两个都对，差的是尺子」；另一件必须说清的
     是这里量的是**组装**，而 BUSCO Genes 模块量的是**蛋白集**，两者不是一个数。 */ ?>
<p class="paleo-intro" style="font-size:16px;color:#475569;margin:0 0 12px">
    <b>Two BUSCO columns, two yardsticks &mdash; both measured on the assembly</b>, in BUSCO
    <b>genome</b> mode (gene models predicted from the DNA, not taken from a deposited protein
    set) against <b>cnidaria_odb12</b> (3,203 markers) and <b>metazoa_odb12.2</b> (932).
    Only the lineage differs, so <b>the two numbers are not comparable left against right</b>:
    compare a species with itself down one column, or one column across species.
    Neither can be derived from the other &mdash; the cnidarian set's extra markers are often
    genuinely absent outside part of the phylum (they show up as <i>Missing</i>), while the
    metazoan set's broader markers come back <i>Fragmented</i> more often, and which effect wins
    varies by assembly. The <a href="/busco.php">BUSCO Genes</a> module measures something else
    again: proteins mode on the deposited protein set, for the 148 species that deposited one.
    Hover a value here for its S/D/F/M breakdown.
</p>

<div class="table-container">
<?php
	echo "<table class=\"gridtable\">";
	/* 表头。除了最后一列，每一列都是服务端排序链接（本页分页了，客户端排序只会
	   排当页那几行 —— 见 includes/sort_head.php 开头）。$__ppQs 里不能带 sort/dir，
	   否则和 cnido_sort_link 自己拼的会撞成两个 sort=。 */
	echo '<tr>';
	echo '<th width="8%">'  . cnido_sort_link('class',   'Class',              $__sort, $__dir, $__ppQs, 'g', $__listAnchor) . '</th>';
	echo '<th width="18%">' . cnido_sort_link('species', 'Species Latin Name', $__sort, $__dir, $__ppQs, 'g', $__listAnchor) . '</th>';
	echo '<th width="9%">' . cnido_sort_link('taxid',   'NCBI Taxonomy ID',   $__sort, $__dir, $__ppQs, 'g', $__listAnchor) . '</th>';
	echo '<th width="7%">'  . cnido_sort_link('size',    'Size (Mb)',          $__sort, $__dir, $__ppQs, 'g', $__listAnchor) . '</th>';
	echo '<th width="8%">' . cnido_sort_link('level',   'Assembly Level',     $__sort, $__dir, $__ppQs, 'g', $__listAnchor) . '</th>';
	/* 两个 BUSCO 列并排：**genome 模式**（组装序列），一列一个谱系数据集。谱系名写在
	   表头第二行 —— 只写 "BUSCO (C %)" 两遍，读者无从知道差在哪。两列各有各的排序键。 */
	echo '<th width="9%" title="Complete BUSCOs (single-copy + duplicated) as a share of the 3,203 BUSCOs of the cnidaria_odb12 lineage, from BUSCO genome mode run on the assembly itself (miniprot predicts the gene models, so this measures the assembly, not the annotation). Hover a value for the full S/D/F/M breakdown.">'
	   . cnido_sort_link('busco', 'BUSCO (C %)<br><span class="gi-busco-th">cnidaria_odb12</span>', $__sort, $__dir, $__ppQs, 'g', $__listAnchor) . '</th>';
	echo '<th width="9%" title="The same assembly scored against metazoa_odb12.2 (932 BUSCOs) instead of cnidaria_odb12 — the same genome-mode protocol, a different lineage dataset. The two are different yardsticks and neither can be derived from the other; the metazoan figure is not always the higher one. Hover a value for the full S/D/F/M breakdown.">'
	   . cnido_sort_link('busco2', 'BUSCO (C %)<br><span class="gi-busco-th">metazoa_odb12.2</span>', $__sort, $__dir, $__ppQs, 'g', $__listAnchor) . '</th>';
	echo '<th width="7%">'  . cnido_sort_link('year',    'Released Year',      $__sort, $__dir, $__ppQs, 'g', $__listAnchor) . '</th>';
	echo '<th width="7%">'  . cnido_sort_link('pubmed',  'Pubmed ID',          $__sort, $__dir, $__ppQs, 'g', $__listAnchor) . '</th>';
	/* 这一列是三种状态拼出来的，不是 speciesinfo 的某一列，所以不给排序链接。 */
	echo '<th width="18%">Gene annotation</th>';
	echo '</tr>';

	/* 空结果必须说出来，否则页面只剩表头，看起来像表格坏了。三种空各有各的原因，
	   措辞不同 —— 说「没有匹配搜索词的物种」而实际是过滤本身为空，是误导。 */
	if ($total === 0) {
		echo '<tr align="center"><td colspan="10" class="tax-none">';
		if ($q !== '') {
			echo 'No species match <b>' . $qHtml . '</b> in this view.';
		} elseif ($specie !== '') {
			echo 'No assembly record for <b><i>' . htmlspecialchars($spLatin) . '</i></b> in CnidoSite yet. '
			   . '<a href="genomeinfo.php?species=&amp;filter=all">Show all species</a>.';
		} else {
			echo 'No species in this filter. '
			   . '<a href="genomeinfo.php?species=&amp;filter=all">Show all species</a>.';
		}
		echo '</td></tr>';
	}

	foreach ($pageRows as $item) {
		$row = $item['r']; $hasGM = $item['gm']; $hasAN = $item['an']; $busco = $item['busco'];
		/* 第二列必须从 $item 取，不能用上面取数循环留下的 $busco2 —— 那样整张表会
		   印成最后一个物种的 metazoa 值（取数循环与渲染循环是两个独立的 foreach）。 */
		$busco2 = $item['busco2'];
		$result = $row;
		$sp  = htmlspecialchars($result[2]);
		$latin = htmlspecialchars($result[1]);
		echo "<tr align='center'>";
		echo "<td>" . htmlspecialchars($result[0]) . "</td>";
		echo "<td><a href=\"./speciesinfo.php?species=$sp\"><i>$latin</i></a></td>";
		if (trim($result[3]) === '-' || trim($result[3]) === '') {
			echo "<td>-</td>";
		} else {
			echo "<td><a href='https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=" . urlencode($result[3]) . "' target='_blank'>" . htmlspecialchars($result[3]) . "</a></td>";
		}
		/* speciesinfo.Size 是 text，且 326 行里有 15 行 ≥1000 Mb 却没有千分位（'1247.88'）。
		   是纯数字才格式化，'-' 之类的缺失值原样印。 */
		echo "<td>" . (is_numeric(trim($result[7])) ? htmlspecialchars(number_format((float)$result[7], 2)) : htmlspecialchars($result[7])) . "</td>";
		echo "<td>" . htmlspecialchars($result[4]) . "</td>";
		/* BUSCO 完整度（第一列，cnidaria_odb12）。两态，别把「没跑」印成 0：
		     · 有分：C% 加粗，下面一行 S/D；达标的用绿色（与 BUSCO 模块里的
		       high-quality 徽标同色）。悬停给出 F/M、谱系、以及**跑的是哪个组装**——
		       这一格量的是组装，不说清 accession 就无从核对。
		     · 没分：破折号 + 说明（没有可用 accession，或这一套还没跑完）。
		   这一格**不再链到 busco_result.php**：那个页面是 proteins 模式、cnidaria_odb12
		   的逐条命中，与这里的 genome 模式数值不是同一个量，点进去只会对不上。 */
		if (is_array($busco)) {
			$__ttl = 'C:' . number_format($busco['c'], 1) . '%  [S:' . number_format($busco['s'], 1)
			       . '%, D:' . number_format($busco['d'], 1) . '%]  F:' . number_format($busco['f'], 1)
			       . '%, M:' . number_format($busco['m'], 1) . '%' . "\n"
			       . 'BUSCO genome mode on the assembly'
			       . ($busco['acc'] !== '' ? ' ' . $busco['acc'] : '') . '; '
			       . number_format($busco['size']) . ' BUSCOs of the ' . $busco['lineage']
			       . ' lineage. '
			       . ($busco['hq']
			            ? 'Meets the high-quality thresholds (C >= 90%, D <= 10%, F <= 5%).'
			            : 'Does not meet all of the high-quality thresholds (C >= 90%, D <= 10%, F <= 5%).')
			       . "\nGene models predicted from the DNA with miniprot, not taken from a deposited protein set.";
			echo '<td title="' . htmlspecialchars($__ttl, ENT_QUOTES, 'UTF-8') . '">'
			   . '<span class="gi-busco-num" style="color:' . ($busco['hq'] ? '#1c7a3e' : '#0f172a') . '">'
			   . number_format($busco['c'], 1) . '%</span>'
			   . '<br><span class="gi-busco-sub">S ' . number_format($busco['s'], 1)
			   . ' &middot; D ' . number_format($busco['d'], 1) . '</span>'
			   . '</td>';
		} else {
			echo '<td><span style="color:#94a3b8" title="No genome-mode BUSCO score against cnidaria_odb12 for this species yet: either no usable NCBI assembly accession is recorded for it, or this dataset has not been run for it. The figure is measured on the assembly, so a species whose protein set is missing can still have one.">'
			   . '&mdash;</span></td>';
		}
		/* 第二列（metazoa_odb12.2）。**不给链接**：站内只有主谱系的结果页，
		   busco_result.php 展示的是 cnidaria_odb12 的逐条命中，从这一格点进去
		   会以为看的是 metazoa 的数。数值本身照第一列的规矩印：C% 加粗、下面
		   一行 S/D、达标绿色、悬停给 F/M 与谱系。 */
		/* 第二列（metazoa_odb12.2）。与第一列同一协议、同一个组装，只换谱系数据集；
		   同样**不给链接**（这一格没有对应的详情页）。 */
		if (is_array($busco2)) {
			$__ttl2 = 'C:' . number_format($busco2['c'], 1) . '%  [S:' . number_format($busco2['s'], 1)
			        . '%, D:' . number_format($busco2['d'], 1) . '%]  F:' . number_format($busco2['f'], 1)
			        . '%, M:' . number_format($busco2['m'], 1) . '%' . "\n"
			        . number_format($busco2['n']) . ' of the ' . number_format($busco2['size'])
			        . ' BUSCOs of the ' . $busco2['lineage'] . ' lineage are complete. '
			        . ($busco2['hq']
			             ? 'Meets the high-quality thresholds (C >= 90%, D <= 10%, F <= 5%).'
			             : 'Does not meet all of the high-quality thresholds (C >= 90%, D <= 10%, F <= 5%).')
			        . "\nSame assembly and same genome-mode protocol as the cnidaria_odb12 column;"
			        . " only the lineage dataset differs.";
			echo '<td title="' . htmlspecialchars($__ttl2, ENT_QUOTES, 'UTF-8') . '">'
			   . '<span class="gi-busco-num" style="color:' . ($busco2['hq'] ? '#1c7a3e' : '#0f172a') . '">'
			   . number_format($busco2['c'], 1) . '%</span>'
			   . '<br><span class="gi-busco-sub">S ' . number_format($busco2['s'], 1)
			   . ' &middot; D ' . number_format($busco2['d'], 1) . '</span>'
			   . '</td>';
		} else {
			echo '<td><span style="color:#94a3b8" title="No genome-mode BUSCO score against metazoa_odb12.2 for this species yet: either no usable NCBI assembly accession is recorded for it, or this dataset has not been run for it.">'
			   . '&mdash;</span></td>';
		}
		echo "<td>" . htmlspecialchars($result[8]) . "</td>";
		/* 这一列的表头是 Pubmed ID，但 speciesinfo.Pubmed_ID 里混进了两种非编号值：
		   '-'（库里表示缺失，99 行）和字面量 'Link'（18 行 —— 原文只给了链接、没写编号）。
		   原来直接把 $result[14] 当链接文字印，于是这 18 行在「Pubmed ID」下面印出 18 个
		   "Link"；PSINE2 那行 Pubmed_ID='-' 却带着真 URL，上面的 && 判不出来，印出一个
		   文字是 '-' 的活链接。现在：有链接就一定有可读的文字，没链接就退回编号或缺失记号。 */
		$__pmid = trim((string)$result[14]);
		$__purl = trim((string)$result[15]);
		if ($__purl === '' || $__purl === '-') {
			echo "<td>" . ($__pmid === '' || $__pmid === '-' ? "<span class='gd-na' title='no PubMed record is listed for this assembly'>&ndash;</span>" : htmlspecialchars($__pmid)) . "</td>";
		} else {
			$__pmtext = ($__pmid === '' || $__pmid === '-' || strcasecmp($__pmid, 'Link') === 0)
				? 'PubMed'
				: htmlspecialchars($__pmid);
			echo "<td><a href='" . htmlspecialchars($__purl) . "' target='_blank' rel='noopener'>" . $__pmtext . "</a></td>";
		}
		if ($hasGM) {
			/* 蛋白数/基因数只是 speciesinfo 里的展示字段，跟「有没有基因模型」无关；
			   缺失（'-'）时就不显示，别拿它当有无注释的判据。这两列本身还混着两种写法
		   ——「21,904」和「28443」（76 行带千分位、66 行不带），同一列的一半有逗号
		   一半没有，照着印就混排；按数值归一后再印，非数字的值不猜、原样保留。 */
			$cnt = '';
			if (($__pn = trim((string)$result[19])) !== '' && $__pn !== '-') { $cnt = htmlspecialchars(is_numeric(str_replace(',', '', $__pn)) ? number_format((int)str_replace(',', '', $__pn)) : $__pn) . ' proteins'; }
			elseif (($__gn = trim((string)$result[20])) !== '' && $__gn !== '-') { $cnt = htmlspecialchars(is_numeric(str_replace(',', '', $__gn)) ? number_format((int)str_replace(',', '', $__gn)) : $__gn) . ' genes'; }
			echo "<td><span style='color:#047857;font-weight:600'>full annotation</span>"
			   . ($cnt !== '' ? "<br><span style='font-size:15px;color:#64748b'>" . $cnt . "</span>" : "")
			   . "</td>";
		} elseif ($hasAN) {
			echo "<td><span style='color:#0369a1;font-weight:600'>annotation only</span><br>"
			   . "<span style='font-size:15px;color:#64748b' title='This species has GO/InterPro/Pfam/PANTHER/KEGG annotation tables but no gene model table, so it appears in those modules but not in Gene Search.'>no gene models</span></td>";
		} else {
			echo "<td><span style='color:#b45309;font-weight:600'>assembly only</span><br>"
			   . "<span style='font-size:15px;color:#64748b' title='This species has an assembly but no gene model table, so it is not included in the gene-level modules.'>not in gene-level modules</span></td>";
		}
		echo "</tr>";
	}
	echo "</table>";
?>
</div><!-- /.table-container -->

<?php /* 分页条。命中 0 条时整块不出：$total_pages 是 0，页码循环一次都不进，
     几个按钮和「of 0 pages」却照旧印出来、全部指向自己 —— 站内约定见
     browse.php / go_result.php。 */ ?>
<?php if ($total > 0): ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* 这个数只统计「当前筛选 + 搜索词」。全库是 326 行，搜索时可能只有
                 3 行 —— 标成 "Total" 会让读者拿它跟页首的 326 对照，以为少了 100 倍。
                 范围也写在这句里，省得再印一行。 */ ?>
            &#128202; <?= (int)$total ?> species in the current filter
            <?= ($perAll || $total_pages <= 1)
                ? '&middot; all rows shown'
                : '&middot; rows ' . (int)($offset + 1) . '&ndash;' . (int)min($offset + $per_page, $total) ?>
        </div>

        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='genomeinfo.php?<?= htmlspecialchars($__noPpQs, ENT_QUOTES, 'UTF-8') ?>&amp;gp=1&amp;gpp='+this.value+'<?= $__listAnchor ?>'">
                <?php
                foreach ($__ppChoices as $__ppc) {
                    $__v = ($__ppc === 'all') ? 'all' : (string)(int)$__ppc;
                    $__l = ($__ppc === 'all') ? 'All' : (string)(int)$__ppc;
                    $__sel = ((string)$__ppc === 'all') ? $perAll : (!$perAll && (int)$__ppc === (int)$per_page);
                    echo '<option value="' . $__v . '"' . ($__sel ? ' selected="selected"' : '') . '>' . $__l . '</option>';
                }
                ?>
            </select>
            <span>species per page</span>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-nav">
        <?php
        /* 链接一律带 $__baseQs（物种深链 / 过滤 / 搜索词 / 每页条数 / 排序），
           少带一个，翻一页就把用户的选择悄悄丢回默认。
           末尾的 #genome-list 不是装饰：本页上面还有一大段说明与三个过滤按钮，
           表格在首屏之外（1440×1000 下距页顶 578px），不带片段的话翻一页就回到
           页首，用户得自己再滚下来 —— 实测过一次。$__selfTail 跟在页码后面
           （$__selfHref 是以 &gp= 结尾的，页码由各条链接自己接）。 */
        $__selfHref = 'genomeinfo.php?' . htmlspecialchars($__baseQs, ENT_QUOTES, 'UTF-8') . '&amp;gp=';
        $__selfTail = $__listAnchor;
        ?>
        <a href="<?= $__selfHref ?>1<?= $__selfTail ?>" class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">&laquo; First</a>
        <a href="<?= $__selfHref ?><?= max(1, $page - 1) ?><?= $__selfTail ?>" class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">&lsaquo; Previous</a>
        <?php
        $start_page = max(1, $page - 3);
        $end_page   = min($total_pages, $page + 3);
        if ($start_page > 1) {
            echo '<a href="' . $__selfHref . '1' . $__selfTail . '" class="page-btn">1</a>';
            if ($start_page > 2) { echo '<span class="page-btn disabled">...</span>'; }
        }
        for ($i = $start_page; $i <= $end_page; $i++) {
            echo '<a href="' . $__selfHref . $i . $__selfTail . '" class="page-btn ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
        }
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) { echo '<span class="page-btn disabled">...</span>'; }
            echo '<a href="' . $__selfHref . $total_pages . $__selfTail . '" class="page-btn">' . $total_pages . '</a>';
        }
        ?>
        <a href="<?= $__selfHref ?><?= min($total_pages, $page + 1) ?><?= $__selfTail ?>" class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">Next &rsaquo;</a>
        <a href="<?= $__selfHref ?><?= $total_pages ?><?= $__selfTail ?>" class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">Last &raquo;</a>
    </div>

    <div class="go-to-page">
        <span>Go to page:</span>
        <input type="number" id="gotoPage" min="1" max="<?= (int)$total_pages ?>" value="<?= (int)$page ?>">
        <button onclick="window.location.href='genomeinfo.php?<?= htmlspecialchars($__baseQs, ENT_QUOTES, 'UTF-8') ?>&amp;gp='+document.getElementById('gotoPage').value+'<?= $__listAnchor ?>'">Go</button>
        <span>of <?= (int)$total_pages ?> pages</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</div><!-- /#genome-list -->

<!-------------------------------Data Coverage Matrix------------------------------------------>
<?php
/* ---------------------------------------------------------------------
 * 物种 × 数据类型 覆盖矩阵。
 *
 * 此前唯一的入口是 Taxonomy 导航子菜单里的一项，夹在 Cubozoa / Hydrozoa 这些
 * 类群链接之间 —— 可它并不是某一类群的数据，而是「全部物种各有哪些数据」的
 * 总览，放在类群列表里既找不到也容易被当成类群页。现在挪到本页最下方：读者在
 * 上面的总览表里发现某个物种缺基因模型时，紧接着就能在同一页看到全貌，并且
 * 每一格都能点进对应模块。
 *
 * 与 coverage_matrix.php、data_statistics.php 共用 includes/coverage_matrix_view.php，
 * 取数与渲染是同一份，三处不会各自漂移。每页 20 个物种、数字翻页、可按物种名
 * 搜索（含短码）。宿主页自己的查询参数（species / filter）用 keep 带着走 ——
 * 否则点一下「第 2 页」就会顺手把上面的物种过滤清掉。
 * ------------------------------------------------------------------ */
echo '<br /><legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;Data Coverage Matrix</legend>';
echo '<p class="paleo-intro">How many records of each data type CnidoSite holds for each species. '
   . 'Every populated cell links into that module with the species preselected, so the table doubles as an '
   . 'index into the site. Filter by species name, class, or data type. The same table also has '
   . '<a href="coverage_matrix.php">a page of its own</a>; the '
   . '<b>Download TSV</b> / <b>Download Excel</b> buttons export <b>whatever the filters select, not just '
   . 'the page on screen</b>.</p>';
echo '<div id="coverage-matrix"></div>';
echo cnido_cm_html($conn, array(
    'base'             => 'genomeinfo.php',
    'anchor'           => '#coverage-matrix',
    'per_page'         => 20,
    'per_page_choices' => array(20, 50, 100),
    'keep'             => array('species' => $specie, 'filter' => ($only !== 'all' ? $only : '')),
));
?>
</div>
</div>
</div>

<?php
	include "Webpage_components.php";
	print $footer;
?>
</body>
</html>