<?php
/* 审稿意见 Referee 2 major 1：
 * 本页此前完全不接受物种参数（只读 per_page / page），species_portal.php 的
 * ?species= 深链点进来看到的是全部 410 条化石记录，而不是该物种的记录。
 * 现用 cnido_state() 解析物种：GET（深链）> POST > 本模块会话 > 默认值（全部）；
 * 值域 = paleobiology.species 里存放的拉丁名（如 Acropora cervicornis）；
 * 会话键为 'paleo_specie'，与其它模块的会话键隔离，不会互相污染。 */
require_once __DIR__ . '/includes/state.php';

$__st = cnido_state('paleo', array(
    'specie' => array('get' => 'species', 'default' => ''),
));
$specie = $__st['specie'];

/* 「全部物种」也是合法状态，但 cnido_state() 会把空值当成「未提供」而回落到会话，
 * 所以用 species=all 作哨兵，供页面上的 “Show all species” 链接真正清除过滤。 */
if (strcasecmp($specie, 'all') === 0) {
    $specie = '';
    unset($_SESSION['paleo_specie']);
}
$specie = htmlspecialchars($specie, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Paleobiology - CnidoSite</title>
<meta name="keywords" content="Cnidaria, paleobiology, fossil records, PBDB" />
<meta name="description" content="Explore 410 fossil records of cnidarians from the Paleobiology Database" />
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

    .pb-search {
        flex-direction: column;
        align-items: stretch;
    }

    .pb-search input[type=text] {
        width: 100%;
    }
}

<?php /* ========== 搜索框 ==========
   配色跟本页的分页条/计数牌走同一个绿（#047857）。 */ ?>
.pb-search {
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

.pb-search label { font-weight: 600; color: #475569; }

.pb-search input[type=text] {
    flex: 1 1 320px;
    min-width: 200px;
    padding: 10px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
    transition: all 0.3s ease;
}

<?php /* 窄屏搜索框。这条 @media 必须放在**基础规则之后**：本页的响应式段写在
   文件上半部分（.pb-search 基础规则之前），同特异性下后者取胜，所以那里写的
   `width:100%` 是活的、`flex` 一动就会被这里盖掉 —— 而真正要改的恰恰是 flex。
   基础规则里的 flex:1 1 320px 在竖排容器里 320 是「高度」基准（flex-basis 跟
   主轴走），输入框会被撑成 320px 高，手机上就是搜索框底下一条大空白。 */ ?>
@media (max-width: 768px) {
    .pb-search input[type=text] {
        width: 100%;
        flex: 0 0 auto;
    }
}

.pb-search input[type=text]:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
}

.pb-search button {
    padding: 10px 22px;
    background:#047857;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.pb-search button:hover { background: #065f46; }

<?php /* 上色必须写成 a.类名：templatemo_style.css 的 a:link,a:visited{color:#1d4ed8} 是
   (0,1,1)，单类名 (0,1,0) 压不住（见 memory: cnidosite-link-color-specificity-trap）。 */ ?>
a.pb-clear { color:#047857; text-decoration: none; font-size: 15px; font-weight: 500; padding: 6px 2px; }
a.pb-clear:hover { text-decoration: underline; }

.pb-hit {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 8px;
    padding: 12px 18px;
    margin: 0 0 15px;
    color: #065f46;
    font-size: 15px;
}

.pb-nores {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 26px 24px;
    margin: 18px 0;
    color: #475569;
    font-size: 15px;
}
.pb-nores h3 { margin: 0 0 10px; font-size: 16px; color: #1e293b; }
.pb-nores p { margin: 8px 0; line-height: 1.7; }
.pb-nores code { background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 0 4px; }
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
            <li><a href="/paleobiology.php" class="current">Paleobiology</a></li>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Paleobiology</b></legend>
<p class="paleo-intro">This module summarises the <b>410 fossil records</b> held for the cnidarian taxa covered here, collected from <a href="https://paleobiodb.org/" target="_blank" rel="noopener noreferrer">the Paleobiology Database (PBDB)</a>.
    <?php /* 这 410 行是**每个分类单元一行**，不是 410 次化石产出，表里也只有六个分类阶元列、
             没有任何化石专属列（年代/产地/层位）。手册 3.2 已经把这点写明（"410 fossil
             records" means 410 taxa，一 taxa 一行），但模块页自己这句原来只说到
             "held for the cnidarian taxa"，读者扫一眼表头容易把行数当成化石条数。这里
             照手册的口径把「一行一个分类单元」说出来。 */ ?>
    <span style="color:#64748b">Each row is one taxon &mdash; this is not a count of fossil occurrences. Every rank that PBDB has a page for links to it (a few ranks of a few taxa have none).</span></p>

<?php
// ========== 分页逻辑 ==========
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}

// 获取总记录数
/* 深链若来自分类 / 基因组等模块，物种可能给的是 abbr1 短码（如 ACERV）而不是
 * 拉丁名。本页的正确值域是拉丁名，所以先按原样查一次，查不到再经 abbr 表折算，
 * 免得「传了有效物种却看到空结果」。 */
if ($specie !== '') {
    $__e = mysqli_real_escape_string($conn, $specie);
    $__n = 0;
    if ($__q = mysqli_query($conn, "SELECT COUNT(*) FROM paleobiology WHERE species = '$__e'")) {
        $__r = mysqli_fetch_row($__q); $__n = (int)$__r[0];
    }
    if ($__n === 0 && ($__q = mysqli_query($conn, "SELECT species FROM abbr WHERE abbr1 = '$__e' LIMIT 1"))) {
        if ($__r = mysqli_fetch_row($__q)) {
            $specie = htmlspecialchars($__r[0], ENT_QUOTES, 'UTF-8');
        }
    }
}

/* 审稿意见 Referee 2 major 1：物种深链时必须把结果集收窄到该物种。
 * 物种名先 mysqli_real_escape_string 再拼进 SQL，不直接使用请求数据。 */

/* ========== 分类搜索 ==========
 * 410 行、每页 10 行 ＝ 41 页，原先只能一页页翻着找某个属/科。搜索必须发生在 SQL 里：
 * 这张表是 LIMIT 分页的，在浏览器里筛只筛得到当前这 10 行。
 *
 * 搜的就是表格里看得见的那六栏（六个分类阶元），所以「看到什么就能搜什么」。
 * 410 行、没有任何索引也够快（全表扫描 0.4 ms 量级），不为它加索引。
 * 通配符转义与截断走 includes/state.php 的共用实现（addcslashes 先于
 * mysqli_real_escape_string，反斜杠顺序反了会让用户输入的 \ 转义下一个字符）。 */
$q = cnido_search_term(isset($_GET['q']) ? $_GET['q'] : '');

$where_sql = '';
if ($specie !== '') {
    $where_sql = " WHERE species = '" . mysqli_real_escape_string($conn, $specie) . "'";
}
if ($q !== '') {
    $where_sql .= ($where_sql === '' ? ' WHERE ' : ' AND ')
                . cnido_like_any($conn, $q,
                      array('Phylum', '`Class`', 'Order1', 'Family', 'Genus', 'species'));
}

/* ========== 表头排序（服务端） ==========
 * 这张表是分页的（LIMIT $offset, $per_page），浏览器里排一页只是把这十行换个次序，
 * 看起来「排好了」其实是错的 —— 第 2 页的最小值可能比第 1 页的最大值还小。
 * 所以表头是服务端链接（?sort=<列>&dir=asc|desc），翻页链接原样带着这两个参数。
 *
 * 默认档写成 null：这张表没有主键、也没有哪一列能复现现在的行序（410 行的次序是
 * 插入顺序，既不是按物种名也不是按分类），硬指定一列就等于把整页默认次序换掉。
 * 见 includes/sort_head.php 里 cnido_sort_state() 的说明。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array(
    'default' => null,
    'phylum'  => cnido_sort_txt('Phylum'),
    'class'   => cnido_sort_txt('`Class`'),
    'order'   => cnido_sort_txt('Order1'),
    'family'  => cnido_sort_txt('Family'),
    'genus'   => cnido_sort_txt('Genus'),
    'species' => cnido_sort_txt('species'),
);
/* 并列键用 species：只按 Phylum 排的话 410 行全是 Cnidaria，次序完全不定，
   翻页跨 LIMIT 边界时同一行会在两页上都出现或都不出现。 */
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', 'species');
$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');

// 分页链接要带上当前物种与搜索词，否则翻页会把过滤条件丢掉
$__filterQs = ($specie !== '' ? 'species=' . urlencode($specie) : '');
if ($q !== '') {
    $__filterQs .= ($__filterQs !== '' ? '&' : '') . 'q=' . urlencode($q);
}
$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
/* 表头链接只用筛选条件（cnido_sort_link 自己会接 sort/dir，带上 $qsP 就会出现两个 sort=）。
   翻页链接才用 $qsP —— 那里必须把排序一起带走。 */
$qsP = ($__filterQs !== '' ? $__filterQs . '&' : '')
     . ($__sortQs !== '' ? $__sortQs . '&' : '');

$total_records = 0;
$count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM paleobiology" . $where_sql);
if ($count_query && ($count_result = mysqli_fetch_assoc($count_query))) {
    $total_records = (int)$count_result['total'];
}

/* 同一视图下**不带搜索词**的行数：只在一条都没搜到时才问一次，用来区分
   「这个视图本来就没有化石记录」和「有记录、但没有一条含这个词」——两者要用户
   做的事完全不同。多查一次全表 COUNT 在 410 行上可以忽略，但仍然只在需要时才查。 */
$__baseTotal = null;
if ($total_records === 0 && $q !== '') {
    $__baseSql = ($specie !== '')
        ? " WHERE species = '" . mysqli_real_escape_string($conn, $specie) . "'"
        : '';
    $__bq = mysqli_query($conn, "SELECT COUNT(*) AS total FROM paleobiology" . $__baseSql);
    if ($__bq && ($__br = mysqli_fetch_assoc($__bq))) {
        $__baseTotal = (int)$__br['total'];
    }
}

// 分页参数
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10; // 默认每页10条
$page = isset($_GET['page']) ? intval($_GET['page']) : 1; // 当前页码

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

// 查询当前页的数据
$query = mysqli_query($conn, "SELECT * FROM paleobiology" . $where_sql . $__orderSql . " LIMIT $offset, $per_page");
?>

<?php /* 审稿意见 Referee 2 major 1：物种深链进来时，页面必须显示当前收窄到了哪个物种 */ ?>
<?php if ($specie !== ''): ?>
<p class="paleo-intro" style="border-left:4px solid #2563eb;background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);">
    <?php if ($total_records > 0): ?>
        Showing fossil records for <b><i><?= htmlspecialchars($specie) ?></i></b> only
        &mdash; <?= $total_records ?> record(s).
    <?php else: ?>
        No fossil records from the Paleobiology Database are available for
        <b><i><?= htmlspecialchars($specie) ?></i></b>.
    <?php endif; ?>
    <a href="./paleobiology.php?species=all">Show all taxa</a>,
    or open the <a href="species_portal.php?species=<?= urlencode($specie) ?>">species portal</a>
    to see the resources that <i><?= htmlspecialchars($specie) ?></i> does have.
</p>
<?php endif; ?>

<?php
/* ========== 搜索框 ==========
 * 放在表格正上方而不是页首：读者是在看这 10 行的时候想缩小范围。
 * GET 表单会**替换**整个查询串，所以物种与排序必须用 hidden 带过去 ——
 * 少带物种就会从「某个物种的记录」变回全部 410 条，而页面上看不出发生过什么。
 * 物种为空时显式发 species=all：cnido_state() 在 GET 为空时会回落到会话值，
 * 而 all 是本页自己约定的「清除」哨兵（见文件开头）。 */
$__selfPb = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
?>
<form class="pb-search" method="get" action="<?= $__selfPb ?>">
    <input type="hidden" name="species" value="<?= $specie !== '' ? htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') : 'all' ?>" />
    <input type="hidden" name="per_page" value="<?= (int)$per_page ?>" />
    <?php if ($__sortQs !== ''): ?>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($__sort, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="dir" value="<?= htmlspecialchars($__dir, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <label for="pbQ">Search taxa</label>
    <input type="text" id="pbQ" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="phylum, class, order, family, genus or species&hellip;" />
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?>
    <a class="pb-clear" href="<?= $__selfPb ?>?species=<?= $specie !== '' ? urlencode($specie) : 'all' ?>&amp;per_page=<?= (int)$per_page ?><?= $__sortQs !== '' ? '&amp;' . htmlspecialchars($__sortQs, ENT_QUOTES, 'UTF-8') : '' ?>">Clear</a>
    <?php endif; ?>
</form>

<?php if ($q !== '' && $total_records > 0): ?>
<div class="pb-hit">
    <?= number_format($total_records) ?> fossil record(s) match
    &ldquo;<b><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></b>&rdquo;<?= $specie !== '' ? ' for <b><i>' . htmlspecialchars($specie) . '</i></b>' : '' ?>.
</div>
<?php elseif ($q !== ''): ?>
<div class="pb-nores">
    <h3>Nothing matches &ldquo;<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>&rdquo;</h3>
    <?php if ($__baseTotal !== null && $__baseTotal === 0): ?>
    <p>There are no fossil records to search in this view&nbsp;&mdash;
       <?= $specie !== '' ? '<b><i>' . htmlspecialchars($specie) . '</i></b> has no record in the Paleobiology Database' : 'the table is empty' ?>,
       so nothing can match. Searchable are the six taxonomy ranks the table shows
       (phylum, class, order, family, genus, species).</p>
    <?php else: ?>
    <p><?php if ($__baseTotal !== null): ?>None of the <?= number_format($__baseTotal) ?>
       fossil record(s) in this view contains<?php else: ?>No fossil record in this view contains<?php endif; ?>
       that term. Searchable are the six taxonomy ranks the table shows
       (phylum, class, order, family, genus, species) &mdash; try a shorter term, such as
       <code>Acropora</code> or <code>Scleractinia</code>.</p>
    <?php if ($__baseTotal !== null): ?>
    <p><a class="pb-clear" href="<?= $__selfPb ?>?species=<?= $specie !== '' ? urlencode($specie) : 'all' ?>&amp;per_page=<?= (int)$per_page ?><?= $__sortQs !== '' ? '&amp;' . htmlspecialchars($__sortQs, ENT_QUOTES, 'UTF-8') : '' ?>">Show all <?= number_format($__baseTotal) ?> record(s)</a></p>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php /* 表格容器 */ ?>
<div class="table-container">
    <table class="gridtable">
        <tr>
            <th><?= cnido_sort_link('phylum',  'Phylum',  $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('class',   'Class',   $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('order',   'Order',   $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('family',  'Family',  $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('genus',   'Genus',   $__sort, $__dir, $__filterQs) ?></th>
            <th><?= cnido_sort_link('species', 'Species', $__sort, $__dir, $__filterQs) ?></th>
        </tr>
		<?php
		/* 表格里每一条链接都指向 PBDB 的 basicTaxonInfo / checkTaxonInfo 两个 classic 路由。
		 * 2026-09-26 起这两个路由对「不带 Referer 的请求」直接回 403 Forbidden
		 * （同一个 URL 随便带上一个 Referer 就是 200，与来源站无关，也与 txn: 前缀无关），
		 * 而这里原先写的是 rel="noopener noreferrer" —— noreferrer 恰好会把 Referer
		 * 整个抹掉，于是表格里 58 条链接点开全是 403，就是「每个 link 都打不开」的原因。
		 *
		 * 只把属性改成 rel="noopener" referrerpolicy="origin" 还不够：只要用户的浏览器、
		 * 扩展或代理把 Referer 抹掉，或者本地网络到 paleobiodb.org 不通，链接照样打不开，
		 * 而这些都不是服务器端能修的。所以改成两段式：链接先指向本站的 Taxon 卡片
		 * pbdb_taxon.php?id=<n>，由我们的服务器去调 PBDB 的 data1.2 数据 API
		 * （那条路由**不需要 Referer**，无 Referer 也回 200），把记录渲染在站内；
		 * 卡片里再给一个「View on PBDB」出口。这样「链接能不能打开」就不再取决于
		 * 用户浏览器的 Referer 策略了。
		 *
		 * $tx_href 从库里存的 PBDB URL 里取出 taxon_no 重建站内链接。六个链接栏目前
		 * 只有两种形状（第 1–5 栏 basicTaxonInfo?taxon_no=txn:N，第 6 栏
		 * checkTaxonInfo?taxon_no=N&is_real_user=1），都能解析出来；万一将来出现解析
		 * 不出来的 URL，就照原样外链（并保留 referrerpolicy），宁可外链也别把链接弄丢。
		 * 注意：页面上其它指向 PBDB 首页的链接不受影响，那里保留 noreferrer 没问题。 */
		$pbdb_a  = 'target="_blank" rel="noopener" referrerpolicy="origin"';
		$tx_href = function ($url) {
			if (preg_match('/taxon_no=(?:txn:)?([0-9]{1,12})/', (string)$url, $m)) {
				return '/pbdb_taxon.php?id=' . $m[1];
			}
			return (string)$url;
		};
		/* 解析出来的站内卡片是本站页面，按口径走当前窗口；只有解析不出 taxon_no、
		   只能原样外链的那几个才需要新窗口 + referrerpolicy（PBDB 无 Referer 回 403）。
		   注意 $pbdb_a 仍用于站外 URL，别删。 */
		$tx_a = function ($url) use ($pbdb_a) {
			return (strpos((string)$url, '/pbdb_taxon.php') === 0) ? '' : ' ' . $pbdb_a;
		};
		while ($result = mysqli_fetch_row($query)) {
			echo '<tr align="center">';

			// 第1欄
			echo '<td><a href="' . htmlspecialchars($tx_href($result[1]), ENT_QUOTES, 'UTF-8') . '"' . $tx_a($tx_href($result[1])) . '>' . $result[0] . '</a></td>';

			// 第2欄
			echo '<td><a href="' . htmlspecialchars($tx_href($result[3]), ENT_QUOTES, 'UTF-8') . '"' . $tx_a($tx_href($result[3])) . '>' . $result[2] . '</a></td>';

			// 第3欄：檢查 $result[5] 是否為 -
			if ($result[5] === '-') {
				echo '<td>' . $result[4] . '</td>';
			} else {
				echo '<td><a href="' . htmlspecialchars($tx_href($result[5]), ENT_QUOTES, 'UTF-8') . '"' . $tx_a($tx_href($result[5])) . '>' . $result[4] . '</a></td>';
			}

			// 第4欄：檢查 $result[7] 是否為 -
			if ($result[7] === '-') {
				echo '<td>' . $result[6] . '</td>';
			} else {
				echo '<td><a href="' . htmlspecialchars($tx_href($result[7]), ENT_QUOTES, 'UTF-8') . '"' . $tx_a($tx_href($result[7])) . '>' . $result[6] . '</a></td>';
			}

			// 第5欄
			echo '<td><a href="' . htmlspecialchars($tx_href($result[9]), ENT_QUOTES, 'UTF-8') . '"' . $tx_a($tx_href($result[9])) . '>' . $result[8] . '</a></td>';

			// 第6欄
			echo '<td><a href="' . htmlspecialchars($tx_href($result[11]), ENT_QUOTES, 'UTF-8') . '"' . $tx_a($tx_href($result[11])) . '>' . $result[10] . '</a></td>';

			echo '</tr>';
		}
		?>
    </table>
</div>

<?php /* 分页导航 */ ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* 有搜索时这个数就是匹配数，别写成「Total」：410 条记录里 0 条匹配，
                     印成 "Total Fossil Records: 0" 是把「没搜到」说成了「一条都没有」。 */ ?>
            📊 <?= $q !== '' ? 'Fossil Records Matching the Search' : 'Total Fossil Records' ?>: <?= $total_records ?>
        </div>
        
        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $qsP ?>per_page='+this.value+'&page=1'">
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
    
        <?php if ($total_pages > 0): /* 命中 0 条时不渲染分页条：$total_pages
         是 0，页码循环一次都不进，几个按钮和「of 0 pages」却照旧印出来，
         全部指向自己。站内约定见 browse.php / go_result.php。 */ ?>
<div class="pagination-nav">
        <?php /* 一条都没有时（搜索没中、或该物种本就没有记录）不渲染翻页按钮：
                 $total_pages 是 0，原先会算出 page=0 / page=1 两个指向自己的死链接，
                 外加一个「Go to page: 1 of 0 pages」。 */ ?>
        <?php if ($total_records > 0): ?>
        <?php /* 首页 */ ?>
        <a href="./paleobiology.php?<?= $qsP ?>per_page=<?= $per_page ?>&page=1"
           class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">
            « First
        </a>
        
        <?php /* 上一页 */ ?>
        <a href="./paleobiology.php?<?= $qsP ?>per_page=<?= $per_page ?>&page=<?= max(1, $page-1) ?>" 
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
            echo '<a href="./paleobiology.php?' . $qsP . 'per_page=' . $per_page . '&page=1" class="page-btn">1</a>';
            if ($start_page > 2) {
                echo '<span class="page-btn disabled">...</span>';
            }
        }
        
        // 中间页码
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<a href="./paleobiology.php?' . $qsP . 'per_page=' . $per_page . '&page=' . $i . '" class="page-btn ' . $active . '">' . $i . '</a>';
        }
        
        // 右侧省略号
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<span class="page-btn disabled">...</span>';
            }
            echo '<a href="./paleobiology.php?' . $qsP . 'per_page=' . $per_page . '&page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
        }
        ?>
        
        <?php /* 下一页 */ ?>
        <a href="./paleobiology.php?<?= $qsP ?>per_page=<?= $per_page ?>&page=<?= min($total_pages, $page+1) ?>" 
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Next ›
        </a>
        
        <?php /* 末页 */ ?>
        <a href="./paleobiology.php?<?= $qsP ?>per_page=<?= $per_page ?>&page=<?= $total_pages ?>" 
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Last »
        </a>
    </div>
    
    <?php /* 跳转到指定页 */ ?>
    <div class="go-to-page">
        <span>Go to page:</span>
        <input type="number" id="gotoPage" min="1" max="<?= $total_pages ?>" value="<?= $page ?>">
        <button onclick="window.location.href='./paleobiology.php?<?= $qsP ?>per_page=<?= $per_page ?>&page='+document.getElementById('gotoPage').value">
            Go
        </button>
        <span>of <?= $total_pages ?> pages</span>
    </div>    <?php endif; /* $total_pages > 0 */ ?>

    <?php endif; /* $total_records > 0 */ ?>
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
