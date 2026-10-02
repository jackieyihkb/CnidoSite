<?php
/* species 直接来自 URL，原先被原样拼进 "SELECT abbr1 FROM abbr WHERE species = '$species'"
   （?species=' UNION SELECT ... -- 即可读到任意表）。这里取值先兜住「没传」，
   输出前再按用途分别转义。 */
$species = isset($_GET['species']) ? $_GET['species']
         : (isset($_POST['species']) ? $_POST['species'] : '');

/* 物种参数先归一化成拉丁双名，再往下走。站内一直有三种写法（拉丁名 / abbr /
   abbr1 —— gene_detail.php 第 226 行的同名处理），这里原来只认拉丁名，于是：
     · 传 ?species=PMULT 或 ?species=Pachycerianthus_multiplicatus 一律落到
       「no record」分支，而 busco.php 的下拉框、species_portal.php 的深链
       给出的正是这两种写法；
     · 更糟的是「BUSCO hits recorded for」那一行照原样印出参数，于是页面上直接
       显示 "PMULT" —— 缩写码只是数据表前缀，不该出现在页面文字里。
   归一化一次，下面所有显示（页面主标题、完整度面板、命中计数行）都拿到学名。
   $__speciesResolved 记的是「确实解析到了一个有学名的物种」，页面主标题只在
   此时才带物种名：输入是个拼错的词时，标题不该替它背书。 */
$__speciesResolved = false;
if ($species !== '') {
    $__nc = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    if (!$__nc->connect_error) {
        $__nc->set_charset('utf8mb4');
        $__se = mysqli_real_escape_string($__nc, $species);
        $__nq = mysqli_query($__nc, "SELECT species FROM abbr
                                      WHERE species = '$__se' OR abbr = '$__se' OR abbr1 = '$__se'
                                      LIMIT 1");
        if ($__nq) {
            $__nr = mysqli_fetch_row($__nq);
            /* species 为空串时不要拿它去顶掉参数。abbr 里没有这种行（曾经只以空
               species 形式存在于 busco_summary 的 6 个合并码也已拆掉），所以这条
               分支不会触发；留着是因为把参数换成空串会让页面失去主语。 */
            if ($__nr && trim((string)$__nr[0]) !== '') {
                $species = $__nr[0];
                $__speciesResolved = true;
            }
        }
        $__nc->close();
    }
}
$speciesHtml = htmlspecialchars($species, ENT_QUOTES, 'UTF-8');
$speciesUrl  = urlencode($species);

/* 完整度面板的取数与样式。必须在这里 require：下面的 <style> 块要用到
   cnido_busco_css()，而那段 HTML 在文件里的位置早于后面的数据库段，
   放到后面 require 会「函数未定义」。 */
require_once __DIR__ . '/includes/busco_summary_view.php';

// 分页参数
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// 验证参数有效性
if ($per_page <= 0) $per_page = 20;
if ($page <= 0) $page = 1;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>BUSCO Gene Details - CnidoSite</title>
<meta name="keywords" content="Cnidaria, BUSCO results, genome completeness, single-copy orthologs" />
<meta name="description" content="Detailed BUSCO gene analysis results for selected Cnidaria species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<style>
<?php /* BUSCO结果页面专用样式 - 与其他页面保持一致 */ ?>
.busco-result-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.busco-result-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.busco-result-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.species-badge {
    background:linear-gradient(135deg, #b45309 0%, #92400e 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

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
    border-color:#b45309;
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


<?php /* 表格美化 */ ?>

.table-scroll-container {
    max-height: 700px;
    overflow-y: auto;
    overflow-x: auto;
}

<?php /* 链接样式 */ ?>
.gene-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.gene-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.jbrowse-link {
    color:#b45309;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.jbrowse-link:hover {
    color: #92400e;
    text-decoration: underline;
}

<?php /* 状态标签 */ ?>
.status-complete {
    background:#047857;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-fragmented {
    background:#b45309;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-duplicated {
    background:#6d28d9;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-missing {
    background: #b91c1c;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

@media (max-width: 768px) {
    .busco-result-header {
        padding: 20px;
    }
    
    .busco-result-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
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

    .bs-search {
        flex-direction: column;
        align-items: stretch;
    }

    .bs-search input[type=text] {
        width: 100%;
        /* flex:0 0 auto 不能省：基础规则里的 flex:1 1 320px 在**竖排**容器里
           320 是"高度"基准（flex-basis 跟主轴走），只清 width 的话输入框会
           长成 320px 高，手机上就是搜索框底下一条大空白。 */
        flex: 0 0 auto;
    }
}
<?php /* ========== 搜索框 ==========
   配色跟本页分页条走同一个蓝（#1d4ed8）。 */ ?>
.bs-search {
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

.bs-search label { font-weight: 600; color: #475569; }

.bs-search input[type=text] {
    flex: 1 1 320px;
    min-width: 200px;
    padding: 10px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
    transition: all 0.3s ease;
}

.bs-search input[type=text]:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.bs-search button {
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

.bs-search button:hover { background:#1e40af; }

<?php /* 上色必须写成 a.类名：templatemo_style.css 的 a:link,a:visited{color:#1d4ed8} 是
   (0,1,1)，单类名 (0,1,0) 压不住（见 memory: cnidosite-link-color-specificity-trap）。 */ ?>
a.bs-clear { color:#1d4ed8; text-decoration: none; font-size: 15px; font-weight: 500; padding: 6px 2px; }
a.bs-clear:hover { text-decoration: underline; }

.bs-hit {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 12px 18px;
    margin: 0 0 15px;
    color: #1e40af;
    font-size: 15px;
}

.bs-empty {
    text-align: center;
    padding: 40px;
    background: #f8fafc;
    border-radius: 12px;
    margin: 20px 0;
    color: #64748b;
}
.bs-empty h3 { color: #334155; margin-bottom: 10px; }
.bs-empty p { margin: 6px 0; }
<?= cnido_busco_css() ?>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>BUSCO Gene Details<?php
    /* 物种名放进页面主标题。原先它只以小字灰字出现在完整度面板里，读者打开页面
       一眼看不出这是哪个物种 —— 而本页是深链页，物种正是最需要一眼确认的信息。
       写法与 go_result.php / interpro_result.php 的主标题一致（"… in <i>物种</i>"）。
       只在解析到真实物种时才加，输入拼错时标题不替它背书。 */
    if ($__speciesResolved) { echo ' in <i>' . $speciesHtml . '</i>'; }
?></b></legend>
<p  class="paleo-intro">Detailed Benchmarking Universal Single-Copy Ortholog (BUSCO) analysis results for the selected species.</p>

<?php
// 数据库连接
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}

// 获取物种缩写
$speciesSql = mysqli_real_escape_string($conn, $species);
$result1 = mysqli_query($conn, "SELECT abbr1 FROM abbr WHERE species = '$speciesSql'");
$result_of_query1 = mysqli_fetch_row($result1);
/* 物种名在 abbr 里查不到时不要拿着空缩写去查 busco 表（会得到一张空表却看不出
   原因）。与「本物种没有 BUSCO 记录」一并按空结果处理，页面上给出说明。 */
$__speciesOk = (bool)$result_of_query1;
$abbr = $__speciesOk ? $result_of_query1[0] : '';
$abbrSql = mysqli_real_escape_string($conn, $abbr);

/* 完整度取自 busco_summary（按 (物种, BUSCO_ID) 归并过的口径），
   而不是 busco 表的命中行数。这里的第三个参数是合并码回退用的拉丁名 ——
   6 个被截断的合并码拆开后已无合并行，该回退不再触发。 */
require_once __DIR__ . '/includes/busco_summary_view.php';
$__bsum = $__speciesOk ? cnido_busco_summary_one($conn, $abbr, $species) : null;

/* 命中表按 abbr 查。若精确码在命中表里一行都没有、而 busco_summary 给的却是另
   一个码，说明这个物种只以合并码的形式存在过，回退过去，否则页面会显示
   "Total BUSCO Genes: 0"。合并码已拆，这条回退今天走不到。 */
$__hitAbbr = $abbr;
if ($__speciesOk) {
    $__c = mysqli_query($conn, "SELECT COUNT(*) FROM busco WHERE abbr = '$abbrSql'");
    $__n = $__c ? (int)mysqli_fetch_row($__c)[0] : 0;
    if ($__n === 0 && $__bsum && $__bsum['abbr1'] !== $abbr) {
        $__hitAbbr = $__bsum['abbr1'];
    }
}
$__hitAbbrSql = mysqli_real_escape_string($conn, $__hitAbbr);

/* 搜索用的 cnido_search_term() / cnido_like_any() 在 includes/state.php。
   本页原先只用 sort_head.php 的 cnido_sort_qs()，所以没引过 state.php ——
   不引的话这一行会以「Call to undefined function」500，而且页面头部已经
   输出到一半，浏览器看到的是半张截断的页面。 */
require_once __DIR__ . '/includes/state.php';

/* ========== 搜索框 ==========
 * 一个物种的 busco 命中行数在数千量级（整表 55 万），分成几十页，原先只能一页页翻。
 * 搜的列是表格上看得见的那四列文字：BUSCO ID、Status、Gene ID、Description。
 * Score / Length 是数值列，放进来只会让搜 "365" 命中一堆分数。
 *
 * 性能：本表只有 ix_gene(gene(64)) 一个**前缀**索引，而前缀索引对 `%词%` 完全用
 * 不上（能用上的只有 `词%`）；abbr 列更是没有索引，所以连 `WHERE abbr=…` 本身都是
 * 一次 55 万行全扫。实测（本表已全部在 buffer pool 里）：默认视图 COUNT+取页
 * 0.18+0.11 s，加上搜索条件后 0.23+0.22 s —— 绝对值可以接受，所以**没有**为它新增
 * 索引：空间换来的那点收益不值当，而本机磁盘此前已经因为批量加索引吃紧过。
 * 若哪天这张表涨到千万行，先加的应该是 abbr 上的索引，不是给文本列加 FULLTEXT。 */
$q = cnido_search_term(isset($_GET['q']) ? $_GET['q'] : '');
$__where = array("abbr = '$__hitAbbrSql'");
if ($q !== '') {
    $__where[] = cnido_like_any($conn, $q, array('BUSCO_ID', 'Status', 'gene', 'Description'));
}
$__whereSql = ' WHERE ' . implode(' AND ', $__where);

/* 基因详情页与 JBrowse 轨道都只在该物种真有基因注释时才存在。命中表里
   149 个物种码中有 145 个有 <ABBR>_locus 表，缺的 4 个是 AALAT、CCRUX、
   CXAMA、PMULT（只有组装、没有注释模型）—— 点进去只会看到「Gene not found」。
   这里先探一次目标是否存在，存在才生成
   链接，不存在就原样输出文本，避免一整页链接全部落空。
   （原先这里还列着 AAURI / ASP / BCF / CGRAC / CSP / PSINE 六个被截断的合并码，
   它们已于 2026-09-21 逐物种重跑拆开，命中表里不再有这些码。） */
$__linkGene = false;
$__linkJb   = false;
if ($__speciesOk && $__hitAbbr !== '') {
    $__lk = mysqli_real_escape_string($conn, $__hitAbbr . '_locus');
    $__lr = mysqli_query($conn, "SELECT 1 FROM information_schema.tables
                                 WHERE table_schema = DATABASE() AND table_name = '$__lk' LIMIT 1");
    $__linkGene = (bool)($__lr && mysqli_num_rows($__lr) > 0);
    $__linkJb   = is_dir(__DIR__ . '/jbrowse/' . $__hitAbbr);
}

// 获取总记录数（条件与下面取页的 SELECT 共用 $__whereSql，两处不会分叉）
$total_records = 0;
if ($__speciesOk) {
    $count_query = mysqli_query($conn, "SELECT COUNT(*) FROM busco" . $__whereSql);
    $count_result = $count_query ? mysqli_fetch_row($count_query) : null;
    $total_records = $count_result ? $count_result[0] : 0;
}

/* 搜了但一条没中时，再查一次「不带搜索词」的总数：好把「这个物种没有命中行」和
   「有 N 行、但没一行含这个词」分开说。只在空结果时付这一次扫描。 */
$__baseTotal = $total_records;
if ($q !== '' && $total_records == 0 && $__speciesOk) {
    $__baseTotal = 0;
    if ($__cq = mysqli_query($conn, "SELECT COUNT(*) FROM busco WHERE abbr = '$__hitAbbrSql'")) {
        if ($__cr = mysqli_fetch_row($__cq)) { $__baseTotal = (int)$__cr[0]; }
    }
}

// 计算总页数
$total_pages = ceil($total_records / $per_page);

// 确保当前页不超过总页数
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}

// 计算偏移量
$offset = ($page - 1) * $per_page;

/* ========== 表头排序（服务端） ==========
 * 这张表是分页的（LIMIT $offset, $per_page），而页面上还有一张完整度面板的表 ——
 * js/table-sort.js 判断「分页表」时看的是祖先容器里有几张 table，两张就判不出来，
 * 于是这 25 行一直在浏览器里被重排。改成服务端表头链接，客户端脚本见到
 * data-sort-link 就整表跳过。
 *
 * 默认档用 null：原来的 SQL 没有 ORDER BY，这里不主动换掉它。
 * 并列键 BUSCO_ID + gene 仍不唯一（实测有 76 行七列全同，是数据里真正的重复行），
 * 那种行翻页时可能重复出现 —— 这是数据本身的问题，不是排序键选得不好。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array(
    'default' => null,
    'busco'   => cnido_sort_txt('BUSCO_ID'),
    'status'  => cnido_sort_txt('Status'),
    /* Score / Length 在库里是 text，而且**总有小数位**（834.2 / 365.0）。
       用 cnido_sort_num 的 '^[0-9]+$' 会把整个 Length 列判成缺值全部沉底。 */
    'score'   => cnido_sort_dec('Score'),
    'length'  => cnido_sort_dec('Length'),
    'gene'    => cnido_sort_txt('gene'),
    'desc'    => cnido_sort_txt('Description'),
);
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default', array('BUSCO_ID', 'gene'));
$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');

$__filterQs = 'species=' . urlencode($species) . ($q !== '' ? '&q=' . urlencode($q) : '');
$__sortQs   = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
$__baseQs   = $__filterQs . ($__sortQs !== '' ? '&' . $__sortQs : '');

// 查询当前页数据（条件与上面计数共用 $__whereSql）
$query = $__speciesOk
       ? mysqli_query($conn, "SELECT * FROM busco" . $__whereSql . $__orderSql . " LIMIT $offset, $per_page")
       : false;

/* 搜索表单里的 hidden 排序字段：搜索框在表格之前，而 $__sortQs 服务于翻页链接，
   两份用途不同，不共用同一个变量。 */
$__sortQsForm = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
$__selfForm   = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
?>

<?php /* 完整度面板：S/D/F/M + 高质量判定。原先这里只有一个行数，读者无从判断
     这个基因组完整到什么程度，而这正是审稿意见 Referee 2 major 7 的前提。 */ ?>
<?php if ($__bsum): ?>
    <?= cnido_busco_panel($__bsum, $species) ?>
<?php elseif ($__speciesOk): ?>
    <p class="bs-warn">No BUSCO completeness summary is available for
        <b><?= $speciesHtml ?></b>. The species has no BUSCO result in this release.</p>
<?php endif; ?>

<?php if ($__speciesOk): ?>
<?php /* 搜索框。刻意不放在「有结果」的分支里：一条没搜到时它也得在，否则用户除了浏览器
     后退没有别的路可走。GET 表单会替换整个查询串，所以 species / 排序 / 每页条数
     全部用 hidden 带过去（翻页链接另有 $__baseQs）。 */ ?>
<form class="bs-search" method="get" action="<?= $__selfForm ?>">
    <input type="hidden" name="species" value="<?= $speciesHtml ?>" />
    <input type="hidden" name="per_page" value="<?= (int)$per_page ?>" />
    <?php if ($__sortQsForm !== ''): ?>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($__sort, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="dir" value="<?= htmlspecialchars($__dir, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <label for="bsQ">Search hits</label>
    <input type="text" id="bsQ" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
           <?php /* 这里原来还列了 Missing。busco 表的 Status 只有 Complete /
                    Duplicated / Fragmented 三种，一条 Missing 都没有（漏掉的 BUSCO
                    压根不写行），照字面去搜 "Missing" 只会得到「什么都没找到」，
                    像是搜索坏了。列真正存在的三种。 */ ?>
           placeholder="BUSCO ID, gene ID, Complete/Duplicated/Fragmented, description&hellip;" />
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?>
    <a class="bs-clear" href="<?= $__selfForm ?>?species=<?= urlencode($species) ?>&amp;per_page=<?= (int)$per_page ?>">Clear</a>
    <?php endif; ?>
</form>

<?php if ($q !== '' && $total_records > 0): ?>
<div class="bs-hit">
    <?= number_format($total_records) ?> hit row(s) of <b><i><?= $speciesHtml ?></i></b> match
    &ldquo;<b><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></b>&rdquo;.
</div>
<?php endif; ?>

<?php if ($q !== '' && $total_records == 0): ?>
<?php /* 搜了但没中。区分「这个物种没有命中行」与「有命中行、但没一行含这个词」。 */ ?>
<div class="bs-empty">
    <div style="font-size:48px;margin-bottom:16px;">🔍</div>
    <h3>Nothing matches &ldquo;<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>&rdquo;</h3>
    <?php if ($__baseTotal == 0): ?>
    <p>No BUSCO hit row is recorded for <b><i><?= $speciesHtml ?></i></b>, so there is nothing to
       search. See the <a href="busco.php">BUSCO search page</a> for the species that do have results.</p>
    <?php else: ?>
    <p>None of the <?= number_format($__baseTotal) ?> hit row(s) of
       <b><i><?= $speciesHtml ?></i></b> contains that term. Searchable columns are BUSCO ID,
       status, gene ID and description &mdash; try a shorter term, or a full BUSCO ID
       such as <code>63at6073</code>. Note that every BUSCO ID of a species ends in the
       same lineage suffix (<code>at6073</code> here), so searching just the suffix
       matches all of its rows.</p>
    <p><a class="bs-clear" href="<?= $__selfForm ?>?species=<?= urlencode($species) ?>&amp;per_page=<?= (int)$per_page ?>">Show all <?= number_format($__baseTotal) ?> hit row(s)</a></p>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; /* $__speciesOk */ ?>

<?php /* 分页导航 */ ?>
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            <?php /* 这里是 busco 表的命中行数，不是 BUSCO 数 —— 原先标成
                     "Total BUSCO Genes" 会被读成「基因数 / BUSCO 数」，
                     与上面的完整度面板对不上，也容易被当成完整度指标。 */ ?>
            BUSCO hits recorded<?php if (trim((string)$species) !== ''): ?> for <strong><i><?php echo htmlspecialchars($species); ?></i></strong><?php endif; ?>:
            <b><?= number_format($total_records) ?></b>
            <?php /* 这句压在 .total-records 的深蓝渐变上（白字 6.41），原来的
                     #64748b 在这块底上只有 1.9；#dbeafe 是 5.49，
                     仍然是「比主句弱、但读得清」的次级灰蓝。 */ ?>
            <span style="color:#dbeafe;font-weight:400"> (one row per gene hit, not per BUSCO)</span>
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
            <span>hits per page</span>
        </div>
    </div>
    
    <?php /* 表格容器 */ ?>
    <div class="table-container">
        <div class="table-scroll-container">
            <table class="gridtable">
                <tr>
                    <th><?= cnido_sort_link('busco', 'BUSCO ID', $__sort, $__dir, $__filterQs) ?></th>
                    <th><?= cnido_sort_link('status', 'Status', $__sort, $__dir, $__filterQs) ?></th>
                    <th><?= cnido_sort_link('score', 'Score', $__sort, $__dir, $__filterQs) ?></th>
                    <th><?= cnido_sort_link('length', 'Length', $__sort, $__dir, $__filterQs) ?></th>
                    <th><?= cnido_sort_link('gene', 'Gene ID', $__sort, $__dir, $__filterQs) ?></th>
                    <th width="24%" class="tal"><?= cnido_sort_link('desc', 'Description', $__sort, $__dir, $__filterQs) ?></th>
                    <th>Visualization</th>
                </tr>
                <?php
				if (!$__speciesOk) {
					/* 物种名在库里查不到：与其给一张空表，不如说清楚 */
					echo '<tr><td colspan="7" style="padding:16px;text-align:left">'
					   . ($speciesHtml !== ''
					        ? 'CnidoSite has no BUSCO record for <b>' . $speciesHtml . '</b>. Please pick a species from the '
					        : 'No species was selected. Please pick one from the ')
					   . '<a href="busco.php">BUSCO search page</a>.'
					   . '</td></tr>';
				}
				while ($row = mysqli_fetch_row($query))
				{
                  echo "<tr align=\"center\"><td>" . htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') . "</td><td>";
                            $status_class = '';
                            switch ($row[1]) {
                                case 'Complete': $status_class = 'status-complete'; break;
                                case 'Fragmented': $status_class = 'status-fragmented'; break;
                                case 'Duplicated': $status_class = 'status-duplicated'; break;
                                case 'Missing': $status_class = 'status-missing'; break;
                                default: $status_class = 'status-fragmented';
                            }
                   echo "<span class=\"$status_class\">" . htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8') . "</span></td>"
                      . "<td>" . htmlspecialchars($row[2], ENT_QUOTES, 'UTF-8') . "</td>"
                      . "<td>" . htmlspecialchars($row[3], ENT_QUOTES, 'UTF-8') . "</td>"
                      . "<td>" . ($__linkGene
                            ? "<a href=\"./gene_detail.php?gene=" . urlencode($row[4]) . "&amp;species=" . $speciesUrl . "\">" . htmlspecialchars($row[4], ENT_QUOTES, 'UTF-8') . "</a>"
                            : htmlspecialchars($row[4], ENT_QUOTES, 'UTF-8')) . "</td>"
                      . "<td class=\"tal\">" . htmlspecialchars($row[6], ENT_QUOTES, 'UTF-8') . "</td>"
                      . "<td>" . ($__linkJb
                            ? "<a href=\"./jbrowse/index.html?data=" . urlencode($row[5]) . "&amp;gene=" . urlencode($row[4]) . "\">JBrowse</a>"
                            : '<span title="This species has no gene annotation, so there is no genome-browser track for it.">&mdash;</span>') . "</td></tr>";
                }
				?>
            </table>
        </div>
    </div>
    
    <?php /* 分页导航按钮。一行都没有时（搜索没中、或该物种本就没有命中行）不渲染：
         这时 $total_pages 是 0，原来的按钮会算出 page=0 / page=1 两个都指向自己的
         死链接，加一个「Go to page: 0 of 0 pages」。 */ ?>
    <?php if ($total_records > 0): ?>
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
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $__baseQs . '&per_page=' . $per_page . '&page=1" class="page-btn">1</a>';
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
        <button onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $__baseQs ?>&per_page=<?= $per_page ?>&page='+document.getElementById('gotoPage').value">
            Go
        </button>
        <span>of <?= $total_pages ?> pages</span>
    </div>
    <?php endif; /* $total_records > 0 */ ?>
</div>

</div>
</div>
</div>

<?php
$conn->close();
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
