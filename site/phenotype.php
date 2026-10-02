<?php
/* 审稿意见 Referee 2 major 1：
 * 本页此前只读 $_GET['class']，species_portal.php 深链过来的 ?species= 被完全忽略；
 * 而 $_SESSION['species'] 又是各模块共用的键（值域并不一致）。
 * 现改用 cnido_state()：GET（深链）> POST（表单）> 本模块会话 > 默认值。
 * 本页物种值域 = phenotype.species 里存放的拉丁名（如 Nematostella vectensis）。 */
require_once __DIR__ . '/includes/state.php';

$__st = cnido_state('phenotype', array(
    'class'  => array('get' => 'class',   'default' => 'all'),
    'specie' => array('get' => 'species', 'default' => ''),
));
$class  = $__st['class'];
$specie = $__st['specie'];

/* 「不过滤物种」也是一个合法状态，但 cnido_state() 把空值当成「未提供」会回落到会话，
 * 所以用 species=all 作哨兵，让页面上的 “Show all species” 能真正清掉过滤（并清会话）。 */
if (strcasecmp($specie, 'all') === 0) {
    $specie = '';
    unset($_SESSION['phenotype_specie']);
}

// 防止 XSS 攻击
$class  = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
$specie = htmlspecialchars($specie, ENT_QUOTES, 'UTF-8');

// ========== 数据库连接 ==========
/* 与 browse.php 同理：meta description 现在要写物种数，连接必须早于 <head> 输出。
   正文里那份连接已合并到这里 —— 全页只连一次。 */
require_once __DIR__ . '/includes/stats.php';
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}
/* meta description 里原先写死「324 Cnidaria」，且照抄的是分类页的措辞
   （"Taxonomic overview"）—— 本页是表型数据，不是分类浏览。两处一并改掉。 */
$cnido_cat_species = cnido_catalogue_species($conn);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Phenotype - CnidoSite</title>
<meta name="keywords" content="Cnidaria, phenotype, trait, Octocoral Trait Database, WoRMS, Pelagic Species Trait Database" />
<?php
/* 原来那句是「Phenotype records for N Cnidaria species, including bleaching traits
   and thermal tolerance, with cross-references to NCBI, WoRMS and GBIF」，三处都
   与实际不符：
     - 「including bleaching traits and thermal tolerance」：表里 bleaching 只有 18
       行、Water temperature 84 行，拿它当整页的卖点是夸大。
     - 「cross-references to NCBI, WoRMS and GBIF」：本表**没有任何** NCBI / WoRMS /
       GBIF 列（列只有 Class、species、trait_name、trait_category、value、traitunit、
       region、latitude、longitude、methodology）。
     - 「for N Cnidaria species」：N 是分类表的物种数（326），不是本表覆盖的物种数
       —— 表里有 3,948 个物种，其中只有 69 个属于本站分类表。
   改成只说得出处与用途，不写具体数字（数字会随数据更新变旧）。 */
?>
<meta name="description" content="Phenotypic trait records for Cnidaria, integrated from the Octocoral Trait Database, WoRMS Marine Species Traits and the Pelagic Species Trait Database, filterable by class and species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<style>
<?php /* 原有样式保持不变 */ ?>
.paleo-intro {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-left: 4px solid #059669;
    padding: 20px 25px;
    border-radius: 8px;
    margin-bottom: 30px;
    line-height: 1.7;
    color: #475569;
}

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

<?php /* 物种列现在是指向 phenotype_species.php 的链接。配色必须写成 a.类名 ——
   templatemo_style.css 的 a:link, a:visited 是 (0,1,1)，单类名 (0,1,0) 压不住
   （见 memory: cnidosite-link-color-specificity-trap）。 */ ?>
a.ph-sp { color: #047857; text-decoration: none; }
a.ph-sp:hover { text-decoration: underline; }

<?php /* ========== 搜索框 ==========
   配色跟本页的分页条走同一个绿（#047857），不在这张页面上引入第二种强调色。 */ ?>
.ph-search {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 15px 20px;
    margin: 0 0 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}
.ph-search label { font-weight: 600; color: #475569; }
.ph-search input[type=text] {
    flex: 1 1 320px;
    min-width: 200px;
    padding: 10px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
}
.ph-search input[type=text]:focus {
    outline: none;
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
}
.ph-search button {
    padding: 10px 22px;
    background: #047857;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}
.ph-search button:hover { background: #065f46; }
<?php /* 「清除」与空结果里的那两个提示链接都是 <a>：上色必须写成 a.类名 ——
   templatemo_style.css 的 a:link,a:visited{color:#1d4ed8} 是 (0,1,1)，单类名
   (0,1,0) 压不住（见 memory: cnidosite-link-color-specificity-trap）。 */ ?>
a.ph-clear {
    color: #047857;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    padding: 10px 6px;
}
a.ph-clear:hover { text-decoration: underline; }
.ph-hit {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 12px 18px;
    margin: 0 0 15px;
    color: #065f46;
    font-size: 15px;
}
.ph-empty {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 35px 25px;
    margin: 0 0 20px;
    text-align: center;
    color: #64748b;
}
.ph-empty h3 { color: #334155; margin-bottom: 8px; }
.ph-empty p { margin: 6px 0; }

<?php /* ========== 来源 / 来源属性两个小标签 ==========
   配色与单物种页完全一致，来源是同一处判定（includes/phenotype_traits.php 的
   cnido_pheno_prov_meta() 与 cnido_pheno_source_meta()）——两页各写一份配色必然分叉。
   来源标签是外链（三家的 CC BY 都要求署名回链），所以配色必须写成 a.ph-src：
   templatemo_style.css 的 a:link,a:visited{color:#1d4ed8} 是 (0,1,1)，单类名 (0,1,0)
   压不住（见 memory: cnidosite-link-color-specificity-trap）。 */ ?>
.ph-chip {
    display: inline-block; font-size: 12px; line-height: 1.6;
    border-radius: 999px; padding: 0 8px; white-space: nowrap;
}
a.ph-src, span.ph-src { text-decoration: none; }
a.ph-src:hover { text-decoration: underline; }
<?php /* 折叠计数：跟在值后面，小一号、灰一点，别让人把它读成值的一部分。 */ ?>
.ph-rep { color: #94a3b8; font-size: 12px; margin-left: 3px; white-space: nowrap; }
<?php /* 生活史等限定词（Pelagic 的行有，OCTD 一律没有）。它是这一行的限定语，
   不是并列的 trait，所以印在 Trait Name 后面当一个浅色小标记。 */ ?>
.ph-ctx {
    display: inline-block; font-size: 12px; background: #eef2ff; color: #3730a3;
    border-radius: 3px; padding: 0 5px; margin-left: 4px; white-space: nowrap;
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
            <li><a href="#">Metagenome</a>
                <ul>
                    <li><a href="/metagenomic_data.php">Metagenomic Data</a></li>
                    <li><a href="/MAGs.php">MAGs Catalog</a></li>
                </ul>
            </li>
            <li><a href="#"  class="current">Phenotype</a>
				<ul>
					<li><a href="/phenotype.php?class=all">All</a></li>
					<li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li>
					<li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li>
					<li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li>
					<li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li>
					<li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li>
				</ul>
			</li>
			<li><a href="#">Tools</a>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Phenotypic Information</b></legend>
<p  class="paleo-intro">In this module, we integrated phenotypic data from multiple sources, such as <a href ="https://www.nature.com/articles/s41597-024-04307-8" target="_blank" rel="noopener noreferrer">The Octocoral Trait Database</a>, <a href="https://www.marinespecies.org/imis.php?dasid=8131&doiid=742" target="_blank" rel="noopener noreferrer">Marine Species Traits from WoRMS</a> and <a href="https://pmc.ncbi.nlm.nih.gov/articles/PMC10786825/" target="_blank" rel="noopener noreferrer">The Pelagic Species Trait Database</a>.</p>

<?php
/* 数据库连接已在页面顶部建立（meta description 需要物种数），此处不再重复连接。 */

// ========== 分页逻辑 ==========
/* 深链若来自分类 / 基因组等模块，物种可能给的是 abbr1 短码（如 AAURA）或下划线
 * 形式（Nematostella_vectensis）而不是拉丁名。本页的正确值域是拉丁名，所以先按
 * 原样查一次，查不到再经 abbr 表折算，免得「传了有效物种却看到空结果」、
 * 或者把物种代码当成物种名显示在提示条里。 */
if ($specie !== '') {
    $__e = mysqli_real_escape_string($conn, $specie);
    $__n = 0;
    if ($__q = mysqli_query($conn, "SELECT COUNT(*) FROM phenotype WHERE species = '$__e'")) {
        $__r = mysqli_fetch_row($__q); $__n = (int)$__r[0];
    }
    if ($__n === 0) {
        $__latin = cnido_latin_of($specie, $conn);
        if ($__latin !== $specie) {
            $specie = htmlspecialchars($__latin, ENT_QUOTES, 'UTF-8');
        }
    }
}

/* 审稿意见 Referee 2 major 1：除类别之外还要按物种收窄结果集 ——
 * 从 taxonomy / 物种门户选中的物种应能直接看到它自己的表型记录。
 * 物种名一律先 mysqli_real_escape_string 再拼进 SQL，不直接使用请求数据。 */
$where = array();
if ($class !== '' && strcasecmp($class, 'all') !== 0) {
    $where[] = "Class = '" . mysqli_real_escape_string($conn, $class) . "'";
}
if ($specie !== '') {
    $where[] = "species = '" . mysqli_real_escape_string($conn, $specie) . "'";
}

/* ========== 搜索框 ==========
 * 这张表 13 万行、十几列几乎全是 text，所以 `LIKE '%词%'` 只能全表扫描。
 * 实测：一次匹配 ~0.17 s，加排序取一页 ~0.24 s（宽词命中 14 万行时 0.37 s），
 * 一次搜索总共约 0.4–0.55 s。可以接受，但**刻意不做「边打边查」**——每次按键
 * 两遍全表扫描会把服务器打回从前的慢。只在一个明确的提交动作后查一次。
 *
 * 2026-09-27 重建后表上确实有索引了（id 主键 + species/Class/traitunit/
 * trait_category/source 的前缀索引），但**一条都用不上**：前缀索引是给等值查询
 * 与分组用的，B-tree 对 `%词%` 完全无效。能帮忙的只有 FULLTEXT，而 FULLTEXT 是
 * 整词匹配（且 innodb_ft_min_token_size 默认 3），装上之后用户搜 "bleach" 就再也
 * 命中不了 "bleaching" —— 这张表每一列都是第三方自由文本，把搜索换成整词匹配是
 * 改得更差，不是更快。所以这里仍然刻意不加 FULLTEXT。
 *
 * 搜的列是这张表全部的文本列（Class/species/trait_name/trait_category/value/
 * traitunit/region/methodology）。latitude/longitude 是纯数字坐标，放进来只会让
 * 搜 "10" 命中一堆经纬度，排除掉。 */
$q = cnido_search_term(isset($_GET['q']) ? $_GET['q'] : '');
/* 不含搜索词的那一半条件单独留一份：只在「搜了但一条没中」时再拿它查一次总数，
   好把「这张表没有这类记录」和「有记录、但没一条含这个词」分开说 —— 这两种情况
   给用户看的话完全不同。多出来的那次全表扫描只在空结果时付。 */
$whereBase = $where;
if ($q !== '') {
    $where[] = cnido_like_any($conn, $q, array(
        '`Class`', 'species', 'trait_name', 'trait_category',
        'value', 'traitunit', 'region', 'methodology',
    ));
}
$where_base_sql = $whereBase ? (' WHERE ' . implode(' AND ', $whereBase)) : '';
$where_sql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* ========== 表头排序（服务端） ==========
 * 这张表是分页的，浏览器里排一页 10 行只是把这 10 行换个次序，第 2 页的最小值可能
 * 比第 1 页的最大值还小 —— 必须由服务器重发整段结果。表头是链接，翻页链接带着排序。
 *
 * 默认档不用 null：原来的 ORDER BY 是全表 10 列的完整次序（下面那段老注释解释了
 * 为什么非要 10 列），把它原样搬到这里当默认档，页面初始状态一个字节都不变。 */
require_once __DIR__ . '/includes/sort_head.php';
/* 默认档 = 人看得懂的展示次序（分类 → 物种 → 性状 …），末位接 id。
 * 2026-09-27 起必须带 id：重建后同一物种同一性状的值可以有多行，差别只在
 * value_type / context / source 上（例如 Corallium rubrum 的「Type of skeleton」
 * 一条 raw_value、一条 expert_opinion），而这十列全是 utf8mb4_0900_ai_ci 的
 * text —— 大小写不同的两行在这些列上比较是**相等**的，排不出全序。分页跨
 * LIMIT 边界时同一行会在两页都出现或都不出现。id 是自增主键，补上就是严格全序。
 *
 * 只加 id、**不**把那三列也塞进来：多一列排序键就多一份 filesort 的比较开销
 * （实测 3 列 → 1.3 s），而它们对「同一条记录在两次请求里落在同一页」毫无贡献 ——
 * id 一个人就够了。 */
$__defOrder = '`Class`, species, trait_name, trait_category, value, traitunit, '
            . 'region, latitude, longitude, methodology, id';
$__sortKeys = array(
    'default'     => $__defOrder,
    'class'       => cnido_sort_txt('`Class`'),
    'species'     => cnido_sort_txt('species'),
    'trait'       => cnido_sort_txt('trait_name'),
    'category'    => cnido_sort_txt('trait_category'),
    /* value 是 text 列但 98.5% 是数字（147875 整数 + 848 小数），剩下的 2323 条是
       自由文本（epipelagic、present、deep water…）。用 cnido_sort_dec 而不是纯字典序：
       字典序下 "10" 会排在 "2" 前面。非数字的那些一律当缺值沉底。 */
    'value'       => cnido_sort_dec('value'),
    'unit'        => cnido_sort_txt('traitunit'),
    'region'      => cnido_sort_txt('region'),
    /* 纬经度里真的有南纬西经（升序第一行实测 -77.9），缺值写的是 '-'（123052 行）。
       这里必须是 cnido_sort_dec —— cnido_sort_num 的 CAST(... AS UNSIGNED) 会把
       -33.9 变成 0，南半球的站点全被排到赤道的位置上。 */
    'latitude'    => cnido_sort_dec('latitude'),
    'longitude'   => cnido_sort_dec('longitude'),
    'methodology' => cnido_sort_txt('methodology'),
    /* 源库自己声明的取值性质（raw_value / expert_opinion / mean …）与来源库。
       两列都是短的定值字符串，字典序就够用。 */
    'obtained'    => cnido_sort_txt('value_type'),
    'source'      => cnido_sort_txt('source'),
    /* n_records 是 INT NOT NULL（折叠前的源记录数），可以直接排，不用两段式。 */
    'recs'        => 'n_records',
);
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', $__defOrder);

// 分页链接要带上当前物种与搜索词，否则翻页/换每页条数会把过滤条件丢掉
$__filterQs = 'class=' . urlencode($class)
            . ($specie !== '' ? '&species=' . urlencode($specie) : '')
            . ($q !== '' ? '&q=' . urlencode($q) : '');
$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
$qsP = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');
/* 「清除搜索」要回到**同一个视图**（class / 物种 / 排序 / 每页条数都留着），只把 q
   去掉 —— 直接跳回裸的 phenotype.php 会连用户选好的筛选一起丢。
   per_page 要到下面「分页参数」那段才解析出来，所以这里先只攒前几项，等 $per_page
   有了再拼成完整的清除链接（早拼会拼出 per_page=0）。 */
$__noQsBase = 'class=' . urlencode($class)
            . ($specie !== '' ? '&species=' . urlencode($specie) : '')
            . ($__sortQs !== '' ? '&' . $__sortQs : '');

// 获取总记录数
/* 两个数一起取：total 是**折叠后**的行数（内容完全相同的源记录已并成一行），
   raw 是折叠前的源记录数（= SUM(n_records)）。页面上两个都印 —— 只印 total 会
   显得源库只有这么多条，只印 raw 会把同一个值数成好几条不同的记录。
   页脚那行括号里的「N source entries」用的就是它（现查，重建后是 217,672）。 */
$total_records = 0;
$total_raw = 0;
$count_query = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(n_records) AS raw FROM phenotype" . $where_sql);
if ($count_query && ($count_result = mysqli_fetch_assoc($count_query))) {
    $total_records = (int)$count_result['total'];
    $total_raw     = (int)$count_result['raw'];
}

/* 搜了但一条没中时，再查一次「不带搜索词」的总数：下面要靠它把
   「这张表/这个筛选下根本没有记录」和「有 N 条记录、但没一条含这个词」分开说。 */
$total_base = $total_records;
if ($q !== '' && $total_records === 0) {
    $total_base = 0;
    if ($__cq = mysqli_query($conn, "SELECT COUNT(*) AS total FROM phenotype" . $where_base_sql)) {
        if ($__cr = mysqli_fetch_assoc($__cq)) { $total_base = (int)$__cr['total']; }
    }
}

/* ========== 全表数据的可用性统计（供页面上的说明用） ==========
   2026-09-27 重建后这里不再是「警告」而是**署名 + 口径说明**：Value 列已经是真读数
   （旧表 98.32% 装的是源库编号，导入时写错了列，见 includes/phenotype_traits.php
   文件头）。现在要说清的是三件事 —— 行是从哪几家来的、折叠了什么、每条值是测的
   还是算的/继承的。剩下 16 行仍带源库编号（出处查不到的 legacy 那批），真有命中
   时才提一句 —— 这个数别写死在文案里，页面上的「One residue」是现算的。
   统计在 includes/phenotype_traits.php 的 cnido_pheno_global_stats() 里，
   缓存 1 小时（冷 ~0.3 s、热 0 s），单物种页与后面的图表也读同一份 —— 两处各算
   一遍必然分叉。数字全部现算，重抓数据之后这段会自然跟着变，不需要改代码。 */
require_once __DIR__ . '/includes/phenotype_traits.php';
$ds = cnido_pheno_global_stats($conn);
$ds_usable = $ds['u_meas'] + $ds['u_deriv'] + $ds['u_inherit'] + $ds['u_expert'] + $ds['u_unknown'];

// 分页参数
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// 验证参数有效性
if ($per_page <= 0) $per_page = 10;
if ($page <= 0) $page = 1;

/* 现在 $per_page 才是可信的，补完「清除搜索」的链接（见上面 $__noQsBase 处的说明）。 */
$__noQs = $__noQsBase . '&per_page=' . (int)$per_page;

// 计算总页数
$total_pages = ceil($total_records / $per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}

// 计算偏移量
$offset = ($page - 1) * $per_page;

// ========== 查询当前页数据 ==========
/* 排序键必须是**全序**，否则翻页会重复或漏掉行：MySQL 对并列行不保证顺序，
   同样的查询在不同页之间可能给出不同的排列。内容列写在 $__defOrder 里（见上面的
   表头排序块），末位是自增主键 id —— 十几列 text 在 utf8mb4_0900_ai_ci 下比不出
   大小写差异，只有 id 能给全序。
   加了索引或换个排序键时，务必确认「同一页第 N 行在两次请求里是同一条记录」。

   列必须**逐个写出来**，不能用 SELECT *：表里第一列是重建时加的 id，用 * 会让
   下面 $row['Class'] 之类的取值全部错位一格。 */
$__cols = '`Class`, species, trait_name, trait_category, value, traitunit, region, '
        . 'latitude, longitude, methodology, value_type, context, source, n_records';
$order_sql = ' ORDER BY ' . $__order;
$query = mysqli_query($conn, "SELECT $__cols FROM phenotype" . $where_sql . $order_sql . " LIMIT $offset, $per_page");
?>

<?php /* 审稿意见 Referee 2 major 1：物种深链进来时，页面必须显示当前收窄到了哪个物种 */ ?>
<?php if ($specie !== ''): ?>
<div class="paleo-intro" style="border-left-color:#2563eb;background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);">
    <?php if ($total_records > 0): ?>
        Showing phenotypic records for <b><i><?= htmlspecialchars($specie) ?></i></b> only
        <?php if ($class !== '' && strcasecmp($class, 'all') !== 0): ?>
            (class <b><?= htmlspecialchars($class) ?></b>)
        <?php endif; ?>
        &mdash; <?= $total_records ?> record(s).
    <?php else: ?>
        No phenotypic records are available for <b><i><?= htmlspecialchars($specie) ?></i></b>
        in this module.
    <?php endif; ?>
    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?class=<?= urlencode($class) ?>&species=all">Show all species</a>,
    or open the <a href="phenotype_species.php?species=<?= urlencode($specie) ?>">grouped species view</a>
    (all of its records, by trait category, with each value labelled by how it was obtained),
    or the <a href="species_portal.php?species=<?= urlencode($specie) ?>">species portal</a>
    to see the resources that <i><?= htmlspecialchars($specie) ?></i> does have.
</div>
<?php endif; ?>

<?php /* 数据说明。放在表格之前：读者必须先知道 Value 列意味着什么、这些行是从哪来的，
     再去看表。2026-09-27 重建后这段是**署名 + 口径**，不再是警告（值已经是真读数）。 */ ?>
<?php if ($ds['tot'] > 0): ?>
<div class="paleo-intro" style="border-left-color:#059669;background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);">
    <b>Where these records come from, and what the Value column means</b>
    <?php
    /* 这段数字统计的是**整张表**，而表里显示的是当前筛选下的行。加了筛选还照原样
       印，读者会拿整表的比例去理解眼前这一屏。说明清楚口径。 */
    if (($class !== '' && strcasecmp($class, 'all') !== 0) || $specie !== '') {
        echo 'This paragraph describes the table as a whole, not only the rows of the current filter. ';
    }
    ?>
    The table holds <b><?= number_format((int)$ds['tot']) ?></b> records, integrated from
    <?php
    /* 来源逐家点名并回链。三家的许可都要求署名；WoRMS 的条款还明确不许把整库再分发，
       本站只取了刺胞动物这一部分、逐条记了来源号，署名回链是取用的条件。
       形如 “the Octocoral Trait Database (127,044 records)” 地用逗号连起来。 */
    $__srcs = array();
    foreach ((array)$ds['sources'] as $__sn => $__si) {
        $__sm = cnido_pheno_source_meta($__sn);
        $__nm = ($__sm['url'] !== '')
              ? '<a href="' . htmlspecialchars($__sm['url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($__sm['label'], ENT_QUOTES, 'UTF-8') . '</a>'
              : '<i>' . htmlspecialchars($__sm['label'], ENT_QUOTES, 'UTF-8') . '</i>';
        $__srcs[] = $__nm . ' (<b>' . number_format((int)$__si['rows']) . '</b> records, '
                  . number_format((int)$__si['species']) . ' species)';
    }
    if ($__srcs) {
        $__last = array_pop($__srcs);
        echo ($__srcs ? implode(', ', $__srcs) . ' and ' : '') . $__last . '.';
    }
    /* 折叠口径：源库里内容完全相同的多条记录被并成一行（页面上带 ×N）。 */
    if ((int)$ds['raw'] > (int)$ds['tot']) {
        echo ' Identical source entries were folded into a single row (the <b>&times;N</b> mark after a value '
           . 'is how many source entries it stands for): <b>' . number_format((int)$ds['raw']) . '</b> source '
           . 'entries in total.';
        /* 同物异名会让同一个源条目出现在两个物种名下（两边都要能查到），于是「源条目
           总数」比源库自己的记录数略大 —— 量级是千分之几，但不说清就是虚报。 */
        echo ' Where a source files one entry under two accepted names, it is counted under each name '
           . 'so that either name finds it.';
    }
    ?>
    Every value is a reading, a count or a category name from the source &mdash; not a
    database code.
    <?php
    /* 来源属性的分句只印非零项：某一类归零时照原样拼会印出「0 were …」这种读不通的
       句子。每一类都配一句人话解释它意味着什么，因为「inherited」「derived」不是
       用户一眼能懂的词。 */
    if ((int)$ds['real'] > 0) {
        $__prov = array();
        if ((int)$ds['u_meas']    > 0) { $__prov[] = '<b>' . number_format((int)$ds['u_meas']) . '</b> are measurements of the species named on the row'; }
        if ((int)$ds['u_expert']  > 0) { $__prov[] = '<b>' . number_format((int)$ds['u_expert']) . '</b> are <i>expert opinion</i> (assessed by a specialist rather than measured)'; }
        if ((int)$ds['u_deriv']   > 0) { $__prov[] = '<b>' . number_format((int)$ds['u_deriv']) . '</b> are derived or converted from other data'; }
        if ((int)$ds['u_inherit'] > 0) { $__prov[] = '<b>' . number_format((int)$ds['u_inherit']) . '</b> are <i>inherited from a higher taxon</i> &mdash; they belong to the genus, family or order, not to the species they are filed under'; }
        if ((int)$ds['u_unknown'] > 0) { $__prov[] = '<b>' . number_format((int)$ds['u_unknown']) . '</b> do not record how the value was obtained'; }
        if ($__prov) {
            $__last = array_pop($__prov);
            echo 'Of the <b>' . number_format((int)$ds['real']) . '</b> records that hold a value, '
               . ($__prov ? implode(', ', $__prov) . ' and ' : '') . $__last . '.';
        }
        echo ' The <b>Obtained by</b> column carries that label per record, and the '
           . '<b>Value</b> column carries the source database\'s own wording for it '
           . '(raw value, mean, expert opinion &hellip;).';
    }
    ?>
    <?php if ((int)$ds['coded'] > 0): ?>
    <?php
    /* 残留的编号行。重建后只剩 16 行（Veron 珊瑚名录那批），真有命中才说这一段。
       物种与纲来自 cnido_pheno_global_stats() 现查的 coded_sp / coded_classes ——
       重建前这段文案写的是「水母、水螅和石珊瑚」，重建后命中行全落在石珊瑚上，
       写死的名单必然过期。 */
    $__pctcoded = (int)$ds['coded'] * 100.0 / $ds['tot'];
    $__csp = array();
    foreach ((array)$ds['coded_sp'] as $__s => $__n) {
        $__csp[] = ($__s === '' ? 'species not named' : '<i>' . htmlspecialchars($__s, ENT_QUOTES, 'UTF-8') . '</i>')
                 . ' (' . (int)$__n . ')';
    }
    $__ccl = array();
    foreach ((array)$ds['coded_classes'] as $__c => $__n) {
        $__ccl[] = ($__c === '' ? 'class not recorded' : '<i>' . htmlspecialchars($__c, ENT_QUOTES, 'UTF-8') . '</i>');
    }
    /* 物种清单可能很长（重建前是 75 行散在好几个物种上），只印前三个，
       其余折成「and N other species」，免得这一段把页脚撑成一屏。
       $__csp 里的物种名已经转义过、并自带 <i> 标签，所以下面只能原样印，
       不能再走一次 htmlspecialchars（会印出 &lt;i&gt;）。 */
    $__cspTop = array_slice($__csp, 0, 3);
    $__cspMore = count($__csp) - count($__cspTop);
    $__cspTxt = implode(' and ', $__cspTop);
    if ($__cspMore > 0) { $__cspTxt .= ' and ' . $__cspMore . ' other species'; }
    ?>
    <span style="color:#b45309;">One residue:</span> <b><?= number_format((int)$ds['coded']) ?></b>
    (<?= number_format($__pctcoded, 2) ?>%) of them still carry a source database's own
    trait-identifier code instead of a value<?= $__cspTxt !== '' ? ' &mdash; ' . $__cspTxt : '' ?><?= $__ccl ? ', class ' . implode(', ', array_slice($__ccl, 0, 3)) : '' ?>.
    They come from imports made before <b>2026-09-27</b> whose source could not be
    identified. They are shown as stored and labelled <i>not a value</i>; everything
    else in the table was re-imported from the source files.
    <?php endif; ?>
    Click a species name to see its own records grouped by trait category, with the
    definition of each trait and the reference behind each value. To see the table as a
    whole rather than row by row &mdash; coverage, provenance, distributions and the map
    &mdash; open <a href="phenotype_charts.php">the phenotype overview</a>.
</div>
<?php endif; ?>

<?php /* ========== 搜索框 ==========
     这一页默认最多要翻 15,105 页，原先除了 class / 物种两个下拉外没有任何收窄手段，
     用户想找「某个性状」只能一页页翻。搜索是**服务端** GET：客户端过滤只能看见当前
     页那 10 行，而页码条仍然报着全表的数 —— 那才会真的误导人。
     GET 表单会替换掉整个查询串，所以 class / 物种 / 排序 / 每页条数全部用 hidden
     带过去，翻页与排序链接也走带 q 的 $qsP。 */ ?>
<form class="ph-search" method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="class" value="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>" />
    <?php if ($specie !== ''): ?>
    <input type="hidden" name="species" value="<?= htmlspecialchars($specie, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <?php if ($__sortQs !== ''): ?>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($__sort, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="dir" value="<?= htmlspecialchars($__dir, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <input type="hidden" name="per_page" value="<?= (int)$per_page ?>" />
    <label for="phQ">Search traits</label>
    <input type="text" id="phQ" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="bleached, Marine province, Calcareous sclerites&hellip;" />
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?>
    <a class="ph-clear" href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?<?= htmlspecialchars($__noQs, ENT_QUOTES, 'UTF-8') ?>">Clear</a>
    <?php endif; ?>
</form>

<?php
/* 命中数这一行只在搜索时出现：没搜索时下面那块「Rows in this view」已经报过同一个数，
   再印一遍等于同一句话写两次。这里报的是**当前 class/物种筛选下**的口径，不是全表
   —— 与上面那段数据可用性说明的整表口径刻意区分开。 */
if ($q !== '' && $total_records > 0): ?>
<div class="ph-hit">
    <?= $total_records ?> row(s) in this view match
    &ldquo;<b><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></b>&rdquo;.
    <a class="ph-clear" href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?<?= htmlspecialchars($__noQs, ENT_QUOTES, 'UTF-8') ?>">Clear</a>
</div>
<?php endif; ?>

<?php if ($q !== '' && $total_records === 0): ?>
<?php /* 搜了但一条没中。区分「这个筛选下本来就没有记录」和「有记录但没一条含这个词」——
     前者要让用户去改筛选，后者才是改搜索词。 */ ?>
<div class="ph-empty">
    <div style="font-size:40px;margin-bottom:12px;">🔍</div>
    <?php if ($total_base === 0): ?>
    <h3>No records in this view</h3>
    <p>This class/species filter holds no phenotypic records at all, so there is nothing to search.
       <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?class=all">Search the whole table instead</a>.</p>
    <?php else: ?>
    <h3>Nothing matches &ldquo;<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>&rdquo;</h3>
    <p>No row in this view (<?= $total_base ?> record(s)) contains that term. Searchable columns are
       class, species, trait name, trait type, value, trait unit, region and methodology &mdash; try a
       shorter or differently spelled term.</p>
    <p><a class="ph-clear" href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>?<?= htmlspecialchars($__noQs, ENT_QUOTES, 'UTF-8') ?>">Show all <?= $total_base ?> record(s) in this view</a></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php /* 表格容器 */ ?>
<div class="table-container" >
    <table class="gridtable">
        <tr style="text-align: center; vertical-align: middle;">
            <th width="5%"><?= cnido_sort_link('class',       'Class',       $__sort, $__dir, $__filterQs) ?></th>
            <th width="11%"><?= cnido_sort_link('species', 'Species', $__sort, $__dir, $__filterQs) ?></th>
            <th width="13%"><?= cnido_sort_link('trait', 'Trait Name', $__sort, $__dir, $__filterQs) ?></th>
            <th width="8%"><?= cnido_sort_link('category', 'Trait Type', $__sort, $__dir, $__filterQs) ?></th>
            <th width="9%"><?= cnido_sort_link('value',       'Value',       $__sort, $__dir, $__filterQs) ?></th>
            <th width="7%"><?= cnido_sort_link('unit',        'Trait Unit',  $__sort, $__dir, $__filterQs) ?></th>
            <th width="9%" class="tal"><?= cnido_sort_link('region', 'Region', $__sort, $__dir, $__filterQs) ?></th>
            <th width="6%"><?= cnido_sort_link('latitude',    'Latitude',    $__sort, $__dir, $__filterQs) ?></th>
            <th width="7%"><?= cnido_sort_link('longitude',   'Longitude',   $__sort, $__dir, $__filterQs) ?></th>
			<th width="12%" class="tal"><?= cnido_sort_link('methodology', 'Methodology', $__sort, $__dir, $__filterQs) ?></th>
			<th width="5%"><?= cnido_sort_link('source', 'Source', $__sort, $__dir, $__filterQs) ?></th>
			<th width="8%"><?= cnido_sort_link('obtained', 'Obtained by', $__sort, $__dir, $__filterQs) ?></th>
        </tr>
        <?php
        /* 原先这些格子是直接插进双引号字符串里的（"<td>$result[4]</td>"），整表没有
           一处转义。trait_name / region / methodology 全是第三方自由文本，只要有一个
           < 或 & 就会破版，有一个 <script> 就是 XSS。现在每一格都走 $e()。

           取行用 mysqli_fetch_assoc（不是 fetch_row）：列有十几个，位置最容易错位
           （重建时加的 id 就在第一列），按名字取就没有这个问题。

           2026-09-27 新增两列：
             Source       这一行出自哪一家（回链署名；WoRMS 的条款要求署名）
             Obtained by  这一行是测的 / 算的 / 继承的 / 专家判断的 —— 判定在
                          includes/phenotype_traits.php，两页共用一套。
           值后面那个 ×N 是**折叠计数**：源库里内容完全相同的记录有几条。它只出现在
           那 13,359 行上（180,652 行折叠自 217,672 条源记录），所以不做成单独一列。
           编号行（Obtained by 写 not a value）是那 16 行遗留的源库编号，不是读数，
           编号的含义用括号印在值旁边。
           （这几个数是 2026-09-27 重建后的实测值，会随数据重载变化；文档正文不写死，
           只有这条注释跟着改 —— 页面上的计数一律现查。） */
        $e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
		while ($row = mysqli_fetch_assoc($query))
		{
            $sm = cnido_pheno_source_meta($row['source']);
            $ph = cnido_pheno_is_placeholder($row['value'], $row['traitunit'], $row['source']);
            $pm = cnido_pheno_prov_meta(cnido_pheno_provenance($row['methodology'], $row['value_type']));
            $n  = max(1, (int)$row['n_records']);
            $u  = cnido_pheno_unit($row['traitunit']);

            echo '<tr align="center">'
               . '<td>' . $e($row['Class']) . '</td>'
               /* 链接过去用拉丁名（本页 species 列的值域），新页自己会归位到
                  表里真实存在的写法；拼短码或下划线形式它也能认。 */
               . '<td><a href="phenotype_species.php?species=' . urlencode((string)$row['species']) . '"><i>'
               . $e($row['species']) . '</i></a></td>'
               /* Trait Name 后面跟生活史等限定词（Pelagic 的行有）。 */
               . '<td>' . $e($row['trait_name'])
               . (trim((string)$row['context']) !== '' ? '<span class="ph-ctx">' . $e($row['context']) . '</span>' : '')
               . '</td>'
               . '<td>' . $e(cnido_pheno_category($row['trait_category'])) . '</td>'
               . '<td>' . ($row['value'] === '' ? '&mdash;' : $e($row['value']));
            if ($n > 1) { echo '<span class="ph-rep" title="identical entries in the source database folded into this row">&times;' . $n . '</span>'; }
            /* 遗留编号行把编号的含义印在值旁边（10 → Category）。这是从 OCTD 的
               standard_id 字典里查出来的，不是猜的；查不到就什么都不印。
               传 source：重建后的来源里 (单位,值) 命中字典只是巧合，不是编号。 */
            if ($ph) {
                $dn = cnido_pheno_coded_name($row['value'], $row['traitunit'], $row['source']);
                if ($dn !== '') { echo ' <span class="ph-rep">(' . $e($dn) . ')</span>'; }
            }
            echo '</td>'
               . '<td>' . ($u === '' ? '&mdash;' : $e($u)) . '</td>'
               . '<td class="tal">' . ($row['region'] === '' ? '&mdash;' : $e($row['region'])) . '</td>'
               . '<td>' . (trim((string)$row['latitude']) === '' ? '&mdash;' : $e($row['latitude'])) . '</td>'
               . '<td>' . (trim((string)$row['longitude']) === '' ? '&mdash;' : $e($row['longitude'])) . '</td>'
               . '<td class="tal">' . (trim((string)$row['methodology']) === '' ? '&mdash;' : $e($row['methodology'])) . '</td>';
            if ($sm['url'] !== '') {
                echo '<td><a class="ph-chip ph-src" href="' . $e($sm['url']) . '" target="_blank" rel="noopener noreferrer"'
                   . ' style="background:' . $sm['bg'] . ';color:' . $sm['fg'] . '" title="' . $e($sm['label']) . '">'
                   . $e($sm['short']) . '</a></td>';
            } else {
                echo '<td><span class="ph-chip ph-src" style="background:' . $sm['bg'] . ';color:' . $sm['fg'] . '" title="'
                   . $e($sm['label']) . '">' . $e($sm['short']) . '</span></td>';
            }
            if ($ph) {
                /* 编号行不能标 measured —— 一行写着 measured、值却是常数 10，
                   看起来像真测出来的。 */
                echo '<td><span class="ph-chip" style="background:#f1f5f9;color:#64748b" title="this row holds a source-database code, not a value">not a value</span></td>';
            } else {
                $vt = cnido_pheno_value_type_label($row['value_type']);
                /* 提示语里若有引号，必须让 $e() 去转义（ENT_QUOTES 会把 " 变成 &quot;），
                   自己写实体会被 $e() 二次转义成 &amp;quot; 印在页面上。 */
                $vtip = $pm['hint'] . ($vt !== '' ? ' — the source calls this "' . $vt . '"' : '');
                echo '<td><span class="ph-chip" style="background:' . $pm['bg'] . ';color:' . $pm['fg'] . '" title="'
                   . $e($vtip) . '">' . $e($pm['label']) . '</span></td>';
            }
            echo "</tr>\n";
		}
			?>
    </table>
</div>

<?php /* 分页导航 */ ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* 原先写的是「Total Phenotypic Information: N」，读者会把 N 当成
                     N 条表型测量；后来改成「Rows in this view」，又少说了源库里其实
                     有多少条。现在两个数都印：折叠后的条数 + 折叠前的源记录数。
                     两个数都只描述**当前筛选**，不是全表。 */ ?>
            📊 Records in this view: <?= number_format($total_records) ?><?php
                if ($total_raw > $total_records) { echo ' <span style="font-weight:400;opacity:.85">(' . number_format($total_raw) . ' source entries)</span>'; }
            ?>
        </div>
        
        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $qsP ?>&per_page='+this.value+'&page=1'">
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
        <?php /* 首页 */ ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $qsP ?>&per_page=<?= $per_page ?>&page=1" 
           class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">
            « First
        </a>
        
        <?php /* 上一页 */ ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $qsP ?>&per_page=<?= $per_page ?>&page=<?= max(1, $page-1) ?>" 
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
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $qsP . '&per_page=' . $per_page . '&page=1" class="page-btn">1</a>';
            if ($start_page > 2) {
                echo '<span class="page-btn disabled">...</span>';
            }
        }
        
        // 中间页码
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $qsP . '&per_page=' . $per_page . '&page=' . $i . '" class="page-btn ' . $active . '">' . $i . '</a>';
        }
        
        // 右侧省略号
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<span class="page-btn disabled">...</span>';
            }
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $qsP . '&per_page=' . $per_page . '&page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
        }
        ?>
        
        <?php /* 下一页 */ ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $qsP ?>&per_page=<?= $per_page ?>&page=<?= min($total_pages, $page+1) ?>" 
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Next ›
        </a>
        
        <?php /* 末页 */ ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?<?= $qsP ?>&per_page=<?= $per_page ?>&page=<?= $total_pages ?>" 
           class="page-btn <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            Last »
        </a>
    </div>
    
    <?php /* 跳转到指定页 */ ?>
    <div class="go-to-page">
        <span>Go to page:</span>
        <input type="number" id="gotoPage" min="1" max="<?= $total_pages ?>" value="<?= $page ?>">
        <button onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $qsP ?>&per_page=<?= $per_page ?>&page='+document.getElementById('gotoPage').value">
            Go
        </button>
        <span>of <?= $total_pages ?> pages</span>
    </div>    <?php endif; /* $total_pages > 0 */ ?>

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
