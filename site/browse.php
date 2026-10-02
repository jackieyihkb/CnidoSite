<?php
// ========== 修正输入处理：SQL 用原始值，HTML 用转义值 ==========
$raw_class = isset($_GET['class']) ? trim($_GET['class']) : (isset($_POST['class']) ? trim($_POST['class']) : 'all');

// 只允许字母（Class 名全是字母），防止意外注入
$class = preg_replace('/[^a-zA-Z]/', '', $raw_class);
if ($class === '') {
    $class = 'all';
}

// 专门用于 HTML 输出的转义版本
$class_html = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

/* ========== 搜索词 ==========
 * 页面上那个搜索框（2026-09-27 加）走的是普通的 GET 表单，与站内其它模块
 * （domain_search.php 的 q、这里的类群筛选、分页）一致：全部在服务端做，
 * 不依赖 JS，搜完的 URL 可以直接发给别人，一翻页条件也不会丢。
 *
 * 长度上限只是防呆：LIKE 的模式串太长的意义不大，而且会把 URL 拉得很长。
 * 按字符截，别按字节 —— 中文输入按字节切会切出半个字符，送进 mysqli 就是坏 UTF-8。
 */
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (function_exists('mb_substr')) {
    $q = mb_substr($q, 0, 100, 'UTF-8');
} elseif (strlen($q) > 100) {
    $q = substr($q, 0, 100);
}

require_once __DIR__ . '/includes/sort_head.php';

/* ========== 表头排序（服务端） ==========
 * 这张表是分页的（一页 10/20/50/100 行），浏览器里排只能把这十几行换个次序，
 * 看起来排好了其实整个结果集没动，所以排序必须由服务器做：?sort=<列键>&dir=asc|desc。
 * 白名单写死在这里，$_GET 里的值只用来查表 —— 不这么写就是注入口子。
 * 默认（class 列升序）必须与页面原来的 ORDER BY 逐字一致，老链接的行为才不变。 */
$__sortKeys = array(
    'phylum'  => 'Phylum',
    'class'   => '`Class`',
    'order'   => '`order1`',
    'family'  => 'Family',
    'genus'   => 'Genus',
    'species' => 'Species',
    /* 三个编号列在库里都是 text，而且位数不齐（GBIF 有 7 位也有 8 位、WoRMS 有 6 位
       也有 7 位、NCBI 4~7 位），直接 ORDER BY 是按字典序排的 —— 10000000 会排在
       9789416 前面，缺值写的 '-' 还会在升序时全跑到最前面。走 cnido_sort_num()
       两段式：缺值恒垫底，数字按数值排。 */
    'ncbi'    => cnido_sort_num('NCBI'),
    'worms'   => cnido_sort_num('Worms'),
    'gbif'    => cnido_sort_num('GBIF'),
);
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'class', array('`order1`', 'Species'));

/* 分页、每页条数、跳页三处共九条链接都要原样带上当前条件（class + q + per_page），
   否则「搜完翻到第 2 页」就退回了未筛选的全表。集中拼一次，省得九处各写一遍、
   漏掉一处。page 由各处自己给，所以不放进这个前缀里。
   排序也并进这一份：排完翻页排序得还在，否则翻到第 2 页就悄悄退回默认次序了。 */
$__filterQs = 'class=' . urlencode($class) . ($q !== '' ? '&q=' . urlencode($q) : '');
$__sortQs = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'class');
$__baseQs = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');
$__self   = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
$__qHtml  = htmlspecialchars($q, ENT_QUOTES, 'UTF-8');

// ========== 数据库连接 ==========
/* 连接原先在 <head> 之后的正文里才建立；下面的 meta description 现在也要写物种数，
   所以提前到输出之前建立。正文里那份已合并到这里 —— 全页只连一次。 */
require_once __DIR__ . '/includes/stats.php';
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}
/* 物种数现算。meta description 里原先写死「324 Cnidaria」，目录增长到 326 之后
   就一直没对过；正文里那句早已改成从 $total_records 取，只有这一处漏了，
   而它恰恰是搜索引擎和链接预览会读到的那个。 */
$cnido_cat_species = cnido_catalogue_species($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta charset="utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Taxonomy Overview - CnidoSite</title>
<meta name="keywords" content="Cnidaria, taxonomy, species classification, NCBI, WoRMS, GBIF" />
<meta name="description" content="Taxonomic overview of <?php echo (int)$cnido_cat_species; ?> Cnidaria organisms with cross-references to NCBI, WoRMS and GBIF" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<style>

<?php /* 新增分页样式 */ ?>
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
    background:linear-gradient(135deg, #065f46 0%, #064e3b 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
    border-color:#047857;
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
    background: #f0fdf4;
    border-color:#047857;
    color:#047857;
}

.page-btn.active {
    background:#047857;
    border-color:#047857;
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
    background:#047857;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.go-to-page button:hover {
    background: #047857;
}

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

.tax-search label {
    font-weight: 600;
    color: #065f46;
    font-size: 15px;
}

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
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
}

.tax-search button {
    padding: 10px 24px;
    background:#047857;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.tax-search button:hover {
    background: #065f46;
}

<?php /* 选择器要写成 a.tax-search-clear：外链样式表里的 a:link, a:visited {color:#1d4ed8}
   是 (0,1,1)，裸类名 (0,1,0) 压不住它，链接会变成蓝色。 */ ?>
a.tax-search-clear {
    color: #64748b;
    font-size: 15px;
    text-decoration: underline;
}

.tax-q {
    color: #065f46;
    font-weight: 600;
}

.tax-hit {
    margin: 0 0 14px;
    color: #334155;
    font-size: 15px;
}

.tax-none {
    color: #64748b;
    padding: 16px 8px;
}

@media (max-width: 768px) {
    .pagination-info {
        flex-direction: column;
        align-items: flex-start;
    }

    .tax-search input[type=text] {
        flex: 1 1 100%;
    }
    
    .pagination-nav {
        gap: 5px;
    }
    
    .page-btn {
        padding: 8px 12px;
        min-width: 36px;
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
            <li><a href="#" class="current">Taxonomy</a>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Taxonomy Overview</b></legend>

<?php
/* 数据库连接已在页面顶部建立（meta description 需要物种数），此处不再重复连接。 */

// ========== 分页逻辑 ==========
/* 筛选条件一次拼好，计数与取数用同一份 —— 两处各写一遍迟早会对不上（表头说
   12 条、表里却列出 10 条那种）。 */
$__where = array();
$__args  = array();
if ($class !== 'all') {
    $__where[] = '`Class` = ?';
    $__args[]  = $class;
}
if ($q !== '') {
    /* 一次匹配六个分类阶元加三个外链编号：粘一个 NCBI / WoRMS / GBIF 号进来也
       应该能查到。% 和 _ 在 LIKE 里是通配符，必须转义 —— 否则用户输入一个 %
       就命中全表，看起来像搜索坏了。addcslashes 把 \ % _ 三个字符都加上反斜杠，
       正是 MySQL 的 LIKE 需要的写法。 */
    $__like = '%' . addcslashes($q, '%_\\') . '%';
    $__cols = array('Phylum', '`Class`', '`order1`', 'Family', 'Genus', 'Species',
                    'NCBI', 'Worms', 'GBIF');
    $__where[] = '(' . implode(' LIKE ? OR ', $__cols) . ' LIKE ?)';
    foreach ($__cols as $__c) { $__args[] = $__like; }
}
$__w = $__where ? (' WHERE ' . implode(' AND ', $__where)) : '';

/** 按固定条件取一个计数。$args 为空时不绑参数（无条件的 SELECT 不能 bind）。 */
$__countOne = function ($sql, array $a) use ($conn) {
    $st = $conn->prepare($sql);
    if (!$st) { return 0; }
    if ($a) { $st->bind_param(str_repeat('s', count($a)), ...$a); }
    $st->execute();
    $r = (int)$st->get_result()->fetch_row()[0];
    $st->close();
    return $r;
};

/* 两个数：命中数，以及「当前类群下共多少」—— 搜索时那句「12 of 326」要后者。
   后者只按类群算，与搜索词无关，所以单独查一遍，别拿命中数冒充。 */
$total_records = $__countOne('SELECT COUNT(*) FROM classfy' . $__w, $__args);
$__class_total = ($class === 'all')
    ? $cnido_cat_species
    : $__countOne('SELECT COUNT(*) FROM classfy WHERE `Class` = ?', array($class));

/* 这句原本写死「325 Cnidarians」，加一个物种就会过期。$total_records 就是当前
   列出的行数（不带筛选时等于全部物种数，带筛选时等于该类的物种数），直接用它，
   物种数变化时这句话自动跟着走。
   搜索时**不能**跟着变成命中数：命中 1 条会印成「the taxonomy coverage of 1
   Cnidarian is listed」，读起来像整个目录只有一个刺胞动物。命中数由紧跟其后的
   .tax-hit 那句给（「1 of 197 taxa in … match …」），这句只说「列的是哪个范围」。 */
if ($q === '') {
    if ($total_records === 0) {
        /* 只有类群名在库里不存在时才会走到这里（例如 class=Ceriantharia，或旧的拼法
           Hexactiniaria）。印「coverage of 0 Cnidarians」会让读者以为整个目录是空的。 */
        echo '<p  class="paleo-intro">In this module, the taxonomy coverage of the catalogue is '
           . 'listed; the class <b>' . $class_html . '</b> holds no species.</p>';
    } else {
        echo '<p  class="paleo-intro">In this module, the taxonomy coverage of <b>'
           . (int)$total_records . ((int)$total_records === 1 ? ' Cnidarian' : ' Cnidarians')
           . '</b> is listed.</p>';
    }
} else {
    echo '<p  class="paleo-intro">In this module, the taxonomy coverage of '
       . ($class === 'all' ? 'the catalogue' : 'the <b>' . $class_html . '</b> class')
       . ' is listed, filtered to your search.</p>';
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

// ========== 查询当前页数据 ==========
/* 排序加了 order1 和 Species 两个次级键：只写 ORDER BY Class 时，同一个类里
   各行的先后由存储引擎决定，翻页跨越 LIMIT 边界时同一行可能出现在两页上、
   也可能一行都不出现。级次定死之后页码才是可引用的。
   现在 $__order 由表头排序给出（cnido_sort_state），两个并列键仍然自动跟在后面。 */
$__st = $conn->prepare('SELECT * FROM classfy' . $__w
                     . ' ORDER BY ' . $__order . ' LIMIT ?, ?');
$__dargs = array_merge($__args, array($offset, $per_page));
$__st->bind_param(str_repeat('s', count($__args)) . 'ii', ...$__dargs);
$__st->execute();
$query = $__st->get_result();
?>

<?php /* 搜索框。method="get" 的表单会用表单数据**替换**整个查询串，所以当前的类群
     筛选和每页条数必须以 hidden 字段带过去，否则一搜就退回全表。 */ ?>
<form class="tax-search" method="get" action="<?= $__self ?>">
    <input type="hidden" name="class" value="<?= $class_html ?>" />
    <input type="hidden" name="per_page" value="<?= (int)$per_page ?>" />
    <?php /* 排序也带过去：表单一提交会替换整个查询串，不带就退回默认次序。
             默认列升序时不发这两个字段，URL 干净。 */ ?>
    <?php if ($__sortQs !== ''): ?>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($__sort, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="dir" value="<?= htmlspecialchars($__dir, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <label for="taxQ">Search this list</label>
    <input type="text" id="taxQ" name="q" value="<?= $__qHtml ?>" autocomplete="off"
           placeholder="species, genus, family, order, class or a taxon ID" />
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?>
    <a class="tax-search-clear" href="<?= $__self ?>?class=<?= urlencode($class) ?>&per_page=<?= (int)$per_page ?>&page=1">Clear</a>
    <?php endif; ?>
</form>

<?php
/* 搜索结果说明。命中数单独说一句，因为上面那句 paleo-intro 只说「列了多少条」，
   读者需要知道分母是当前类群、而不是整个目录。 */
if ($q !== '') {
    $__scope = ($class === 'all' ? 'the catalogue' : '<i>' . $class_html . '</i>');
    echo '<p class="tax-hit">';
    if ($total_records === 0) {
        echo '<b>No</b> taxon in ' . $__scope
           . ' matches <span class="tax-q">' . $__qHtml . '</span>.';
    } else {
        echo '<b>' . (int)$total_records . '</b> of <b>' . (int)$__class_total . '</b> taxa in '
           . $__scope . ($total_records === 1 ? ' matches ' : ' match ')
           . '<span class="tax-q">' . $__qHtml . '</span>.';
    }
    /* 类群里搜不到、但全库里可能有 —— 直接给一条出路，而不是让读者自己把类群改回 All。 */
    if ($total_records === 0 && $class !== 'all') {
        echo ' <a class="tax-search-clear" href="' . $__self . '?q=' . urlencode($q)
           . '&class=all&per_page=' . (int)$per_page . '&page=1">Search all '
           . (int)$cnido_cat_species . ' taxa instead</a>';
    }
    echo '</p>';
}
?>

<?php /* 表格容器 */ ?>
<div class="table-container" >
    <table class="gridtable">
        <tr style="text-align: center; vertical-align: middle;">
            <?php /* 每一列表头都是服务端排序的链接：js/table-sort.js 见到
                     <a data-sort-link> 就整表跳过，把排序让给服务器。
                     链接只带筛选条件（$__filterQs），当前排序由 cnido_sort_link
                     自己重新拼 —— 让 $__baseQs 进来会出现两个 sort=。 */ ?>
            <th><?= cnido_sort_link('phylum',  'Phylum',           $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('class',   'Class',            $__sort, $__dir, $__filterQs) ?></th>
            <th width="12%"><?= cnido_sort_link('order', 'Order',   $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('family',  'Family',           $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('genus',   'Genus',            $__sort, $__dir, $__filterQs) ?></th>
            <th width="25%"><?= cnido_sort_link('species', 'Species', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('ncbi',    'NCBI Taxonomy ID', $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('worms',   'WoRMS ID',         $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('gbif',    'GBIF ID',          $__sort, $__dir, $__filterQs) ?></th>
        </tr>
        <?php
        /* 空结果必须说出来。否则搜不到东西时页面只剩表头，看起来像表格坏了。
           一段文字直接拼成一行：table.gridtable 是 white-space:pre-wrap，
           源码里的换行和缩进会被当成真的空行渲染出来。 */
        if ($total_records === 0) {
            /* 还有一种没有搜索词的空：类群筛选本身就空（例如 class=Ceriantharia，
               Pachycerianthus 改正之后这个类在库里不存在）。那时说「没有匹配 q」是错的。 */
            echo '<tr align="center"><td colspan="9" class="tax-none">'
               . ($q === ''
                    ? 'No taxon is registered under this filter. <a href="' . $__self
                      . '?class=all&per_page=' . (int)$per_page . '&page=1">Show the whole catalogue</a>.'
                    : 'No taxon in this view matches <b>' . $__qHtml . '</b>. '
                      . ($class === 'all'
                            ? 'Try a shorter fragment of the name, or a genus or family name.'
                            : 'The class filter is still applied &mdash; try the link above to search the whole catalogue.'))
               . '</td></tr>';
        }
        while ($result = mysqli_fetch_row($query))
        {
            // 获取物种缩写信息
            $species_abbr = '-';
            if (!empty($result[5])) {
                $stmt_abbr = $conn->prepare("SELECT abbr1 FROM abbr WHERE species = ?");
                $stmt_abbr->bind_param("s", $result[5]);
                $stmt_abbr->execute();
                $abbr_result = $stmt_abbr->get_result()->fetch_row();
                if ($abbr_result) {
                    $species_abbr = $abbr_result[0];
                }
                $stmt_abbr->close();
            }
            
            // ========== 所有输出加 htmlspecialchars 防 XSS ==========
            $r0 = htmlspecialchars($result[0] ?? '', ENT_QUOTES, 'UTF-8');
            $r1 = htmlspecialchars($result[1] ?? '', ENT_QUOTES, 'UTF-8');
            $r2 = htmlspecialchars($result[2] ?? '', ENT_QUOTES, 'UTF-8');
            $r3 = htmlspecialchars($result[3] ?? '', ENT_QUOTES, 'UTF-8');
            $r4 = htmlspecialchars($result[4] ?? '', ENT_QUOTES, 'UTF-8');
            $r5 = htmlspecialchars($result[5] ?? '', ENT_QUOTES, 'UTF-8');
            $r6 = htmlspecialchars($result[6] ?? '', ENT_QUOTES, 'UTF-8');
            $r7 = htmlspecialchars($result[7] ?? '', ENT_QUOTES, 'UTF-8');
            $r8 = htmlspecialchars($result[8] ?? '', ENT_QUOTES, 'UTF-8');
            $species_abbr_html = htmlspecialchars($species_abbr, ENT_QUOTES, 'UTF-8');
            
            /* 审稿意见 Referee 2 major 1：在 Taxonomy 模块里选中物种后，应该能一站
             * 进入该物种的全部资源。物种名保留原来的 speciesinfo.php（基因组汇编
             * 详情），后面再给一个 Species Portal 入口（16 类数据的可用性总览）。 */
            $portal_link = ($species_abbr !== '-')
                ? "<br /><a href=\"./species_portal.php?species=" . urlencode($species_abbr) . "\" "
                  . "style=\"font-size:12px;color:#1d4ed8\" title=\"All data available for this species\">Species Portal</a>"
                : "";
            echo "<tr align=\"center\"><td>$r0</td><td>$r1</td><td>$r2</td><td>$r3</td><td>$r4</td><td><a href=\"./speciesinfo.php?species=$species_abbr_html\">$r5</a>$portal_link</td><td>";
              if ($result[6] != "-")
              {
               echo "<a href=\"https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=$r6\" target=\"_blank\" >$r6</a></td><td>";
              }else{
                echo "$r6</td><td>";
              }
              if ($result[7] != "-")
              {
               echo "<a href=\"https://www.marinespecies.org/aphia.php?p=taxdetails&id=$r7\" target=\"_blank\" >$r7</a></td><td>";
              }else{
                echo "$r7</td><td>";
              }
              if ($result[8] != "-")
              {
               echo "<a href=\"https://www.gbif.org/species/$r8\" target=\"_blank\" >$r8</a></td>";
              }else{
                echo "$r8</td>";
              }
            echo "</tr>";
        }
        ?>
    </table>
</div>

<?php /* 分页导航。
     命中 0 条时 $total_pages 是 0，整块不出：这与站内既有约定一致（命中 0 不渲染
     分页条），否则搜索无结果时页面会留一个写死 "of 0 pages"、按了没反应的控件
     （browse.php?class=all&q=zzzzqqqq 曾如此）。 */ ?>
<?php if ($total_pages > 0): ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            📊 Total Taxon Information: <?= $total_records ?>
        </div>
        
        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?<?= $__baseQs ?>&per_page='+this.value+'&page=1'">
                <?php
                $options = [10, 20, 50, 100];
                foreach ($options as $option) {
                    $selected = ($option == $per_page) ? 'selected' : '';
                    echo "<option value='$option' $selected>$option</option>";
                }
                ?>
            </select>
            <span>records per page</span>
        </div>
    </div>
    
    <div class="pagination-nav">
        <?php /* 首页 */ ?>
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=1" 
           class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">
            « First
        </a>
        
        <?php /* 上一页 */ ?>
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=<?= max(1, $page-1) ?>" 
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
            echo '<a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?' . $__baseQs . '&per_page=' . $per_page . '&page=1" class="page-btn">1</a>';
            if ($start_page > 2) {
                echo '<span class="page-btn disabled">...</span>';
            }
        }
        
        // 中间页码
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?' . $__baseQs . '&per_page=' . $per_page . '&page=' . $i . '" class="page-btn ' . $active . '">' . $i . '</a>';
        }
        
        // 右侧省略号
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<span class="page-btn disabled">...</span>';
            }
            echo '<a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?' . $__baseQs . '&per_page=' . $per_page . '&page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
        }
        ?>
        
        <?php /* 下一页 */ ?>
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=<?= min($total_pages, $page+1) ?>" 
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Next ›
        </a>
        
        <?php /* 末页 */ ?>
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page=<?= $total_pages ?>" 
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Last »
        </a>
    </div>
    
    <?php /* 跳转到指定页 */ ?>
    <div class="go-to-page">
        <span>Go to page:</span>
        <input type="number" id="gotoPage" min="1" max="<?= $total_pages ?>" value="<?= $page ?>">
        <button onclick="window.location.href='<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page='+document.getElementById('gotoPage').value">
            Go
        </button>
        <span>of <?= $total_pages ?> pages</span>
    </div>
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