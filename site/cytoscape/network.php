<?php
/* 审稿意见 Referee 2 major 1 / Referee 1 minor 1 & 3：
 * 物种门户深链过来（network.php?species=<拉丁名>）时本页原来完全忽略参数；
 * 而且物种 <select> 在服务端 HTML 里是空的 —— 候选由 DynamicOptionList 在
 * onLoad 时重建，printOptions("datalib") 指向的还是本页并不存在的下拉框名。
 * 现改为：cnido_state() 解析物种（network_* 命名空间）+ 由数据库生成候选。 */
require_once __DIR__ . '/../includes/state.php';

/* 深链里的物种可能是拉丁名（Nematostella vectensis）、下划线名或 abbr1 代码；
 * 本页 <select name="organism"> 与下游 network.list.php 都用拉丁名
 * （它按 abbr.species 查表拿 abbr1 再拼 <abbr1>_coexpress_positive）。 */
function nw_resolve_species($conn, $in)
{
    $in = trim((string)$in);
    if ($in === '') { return ''; }
    foreach (array($in, str_replace('_', ' ', $in)) as $cand) {
        $e = mysqli_real_escape_string($conn, $cand);
        $q = mysqli_query($conn, "SELECT species FROM abbr
                                  WHERE species = '$e' OR abbr = '$e' OR UPPER(abbr1) = UPPER('$e') LIMIT 1");
        if ($q && ($r = mysqli_fetch_row($q))) { return $r[0]; }
    }
    return '';
}

/* 类群名统一为全站用词 Hexacorallia（speciesinfo / classfy 里的写法）。
   本页历史上把它显示成 Hexactiniaria，与导航栏和 browse.php?class=… 不一致：
   页面上的类群名与别处对不上（Referee 1 minor 1），而且本页导航里的
   browse.php?class=Hexactiniaria 在 classfy 表里根本没有对应值，点了列不出物种。
   为兼容旧书签 / 旧链接，入参仍接受 Hexactiniaria，但只输出 Hexacorallia。 */
function nw_class_label($c) { return $c; }
function nw_canon_class($c) { return ($c === 'Hexactiniaria') ? 'Hexacorallia' : $c; }

$__st = cnido_state('network', array(
    'specie' => array('get' => 'species', 'default' => 'Nematostella vectensis'),
    'clazz'  => array('get' => 'class',   'default' => 'Hexacorallia'),
));
$specie      = $__st['specie'];
$specieFrom  = $__st['specie__from'];
$nwNotice    = '';
/* 请求值先归一化：旧的 ?class=Hexactiniaria 仍落到 Hexacorallia */
$nwClazz = nw_canon_class($__st['clazz']);
$nwClassOrder = array('Hexacorallia', 'Hydrozoa', 'Octocorallia', 'Scyphozoa');
$nwList      = array();   // 拉丁名 => class 标签
$nwByClass   = array();   // class 标签 => array(拉丁名)

/* 只有 <abbr1>_coexpress_positive 表存在**且表里有行**的物种才能做网络分析，
 * 候选列表由 information_schema + 逐表探行生成 —— 原来的硬编码列表与库里的数据
 * 并不完全一致，而只测「表在不在」会把空表也算成一个网络。 */
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if (!$conn->connect_error) {
    $q = mysqli_query($conn, "SELECT a.abbr1, a.species, COALESCE(si.Class, '') AS Class FROM abbr a
                              LEFT JOIN speciesinfo si ON si.abbr = a.abbr1
                              WHERE a.species IS NOT NULL AND a.species <> ''
                                AND EXISTS (SELECT 1 FROM information_schema.tables t
                                            WHERE t.table_schema = DATABASE()
                                              AND t.table_name = CONCAT(a.abbr1, '_coexpress_positive'))
                              ORDER BY a.species");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            /* 「表在不在」与「表里有没有行」是两个判据。AAURI1 / AAURI2（Aurelia aurita
               与 Aurelia aurita complex sp. Pacific）的四张表建了却一行都没有：只测表名
               会把它们算成两个网络，本页于是印「Networks exist for 31 species」，而覆盖
               矩阵 / data_statistics / 首页早就是 29 —— 同一件事两个数，翻两个页面就能
               看出来。判据与 includes/coverage.php、includes/stats.php 保持一致：
               任一方向有行才算一个网络。用 LIMIT 1 探行，不能用 COUNT(*) ——
               PCLAV 那张表 650 万行，COUNT(*) 在这个页面上慢到不可用。
               顺带修掉一处死路：这两页的下拉框原来把它们列成可选物种，选中后无论提交
               哪个基因都只会得到「No protein interacting with the submitted gene was
               found.」—— 那是关于**物种**的结论，却印成了关于**基因**的。 */
            $__hasRows = false;
            foreach (array('_coexpress_positive', '_coexpress_negative') as $__suf) {
                $__tbl = str_replace('`', '', $r['abbr1'] . $__suf);
                $__probe = @mysqli_query($conn, "SELECT 1 FROM `$__tbl` LIMIT 1");
                if ($__probe && mysqli_fetch_row($__probe)) { $__hasRows = true; break; }
            }
            if (!$__hasRows) { continue; }
            $cl = nw_class_label($r['Class']);
            $nwList[$r['species']] = $cl;
            $nwByClass[$cl][] = $r['species'];
        }
    }
}

if (!empty($nwList)) {
    // 0) 深链可能给的是 abbr1 代码或下划线名，先统一成拉丁名（候选表用拉丁名）
    if (!isset($nwList[$specie]) && $specieFrom !== 'default') {
        $__lat = nw_resolve_species($conn, $specie);
        if ($__lat !== '') { $specie = $__lat; }
    }
    // 1) 若显式切换了 class（下拉框 onchange 会带 ?class= 重新加载）而物种不是显式
    //    深链指定的，则以 class 为准，取该 class 下的第一个物种
    if (in_array($nwClazz, $nwClassOrder, true) && !empty($nwByClass[$nwClazz])
        && $specieFrom !== 'get' && $specieFrom !== 'post'
        && (!isset($nwList[$specie]) || $nwList[$specie] !== $nwClazz)) {
        $specie = $nwByClass[$nwClazz][0];
    }
    // 2) 物种仍不可用（没有共表达网络，或名字不认识）：退回到一个真实物种并说明，
    //    不能让下拉框停留在请求值上而页面实际查的是别的物种（Referee 2）
    if (!isset($nwList[$specie])) {
        $fbKeys = array_keys($nwList);
        $fb = !empty($nwByClass['Hexacorallia']) ? $nwByClass['Hexacorallia'][0] : $fbKeys[0];
        if ($specieFrom !== 'default') {
            $nwNotice = 'No co-expression network is available for <b>' . htmlspecialchars($specie) . '</b> in '
                      . 'CnidoSite; showing <b>' . htmlspecialchars($fb) . '</b> instead. Networks exist for '
                      . count($nwList) . ' species — see the selector below, or the '
                      . '<a href="/species_portal.php?species=' . urlencode($specie) . '">species portal</a> '
                      . 'for what this species does provide.';
        }
        $specie = $fb;
    }
    $class = $nwList[$specie];
    // 与页面上实际显示的物种保持一致，避免下次进来又跳回默认物种
    $_SESSION['network_specie'] = $specie;
} else {
    $class = 'Hexacorallia';
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Network Analysis - CnidoSite</title>
<meta name="keywords" content="Cnidaria, co-expression network, gene interaction, transcriptome analysis" />
<meta name="description" content="Co-expression network analysis for Cnidaria species to uncover gene interactions and functional relationships" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="../js/func.js" type="text/javascript"></script>
<script>
<?php /* 审稿意见 Referee 1 minor 1 & 3：DynamicOptionList 的 printOptions() 在现代浏览器里
 * 是空操作，物种 <select> 在服务端 HTML 里永远是空的；而 initDynamicOptionLists()
 * 又会在 onLoad 时清空 <select> 后按这里注册的数组重建（js/DynamicOptionList.js:471），
 * 正好覆盖服务端渲染的候选项。本页候选现在由库中的共表达网络表生成，故不再注册。 */ ?>
</script>
<style>
<?php /* Network页面专用样式 - 不影响全局CSS */ ?>
.network-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.network-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.network-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.transcriptome-badge {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

<?php /* 表单容器美化 */ ?>
.network-form-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 35px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
}

.form-section {
    margin-bottom: 5px;
    padding-bottom: 5px;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 22px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}


<?php /* 表单行样式 */ ?>
.form-row {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 10px;
}

.form-select {
    width: 100%;
    max-width: 400px;
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

<?php /* 文本区域：左列输入框、右列例子说明（原先说明在输入框下方） */ ?>
.textarea-container {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 28px;
    margin-bottom: 20px;
}

<?php /* 634 = 输入框 600（max-width，content-box） + 15×2 padding + 2×2 border
   —— 用固定 flex 基准，标签和药丸按钮才能与输入框左右边缘对齐 */ ?>
.genelist-col {
    flex: 0 0 634px;
    max-width: 100%;
}

.example-actions {
    margin-bottom: 12px;
}

<?php /* Load example 做成药丸按钮：它是"动作"，要和右边那段"解释"一眼分得开。
   必须写成 a.example-link：站内 a:link 的特异性高于单类名，只写 .example-link 会被压成蓝色 */ ?>
a.example-link,
a.example-link:link,
a.example-link:visited {
    display: inline-block;
    padding: 8px 16px;
    border: 1px solid #10b981;
    border-radius: 20px;
    background: #f0fdf4;
    color: #047857;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
}

a.example-link:hover {
    background: #dcfce7;
    color: #065f46;
    text-decoration: none;
}

<?php /* 例子说明框（说明这是一份 ABC transporter 相关的基因列表） */ ?>
.example-note {
    flex: 1 1 380px;
    max-width: 700px;
    min-width: 0;
    padding: 16px 20px;
    background: #f0fdf4;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    font-size: 16px;
    line-height: 1.75;
    color: #14532d;
}

.example-note-title {
    font-size: 16px;
    font-weight: 700;
    color: #065f46;
    margin-bottom: 8px;
}

ul.example-gene-list {
    margin: 8px 0 10px;
    padding-left: 20px;
    list-style: disc;
}

ul.example-gene-list li {
    margin: 4px 0;
}

.example-note code {
    background: #dcfce7;
    padding: 1px 6px;
    border-radius: 4px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 13px;
    color: #065f46;
}

.example-gene {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 13px;
    font-weight: 600;
    color: #065f46;
    white-space: nowrap;
}

.example-status {
    display: block;
    margin-top: 8px;
    font-weight: 600;
    color: #b45309;
}

.form-textarea {
    width: 100%;
    max-width: 600px;
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 15px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    background: #f8fafc;
    color: #1e293b;
    resize: vertical;
    transition: all 0.3s ease;
    min-height: 120px;
}

.form-textarea:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    background: white;
}

<?php /* 复选框美化 */ ?>
.checkbox-group {
    display: flex;
    gap: 25px;
    margin-top: 15px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-input {
    width: 20px;
    height: 20px;
    accent-color:#047857;
    cursor: pointer;
}

.checkbox-label {
    font-size: 16px;
    color: #475569;
    cursor: pointer;
    font-weight: 500;
}

<?php /* 按钮美化 */ ?>
.button-group {
    display: flex;
    gap: 15px;
    margin-top: 30px;
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

<?php /* 描述文本美化 */ ?>
.description-text {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-left: 4px solid #10b981;
    padding: 20px 25px;
    border-radius: 8px;
    margin: 25px 0;
    color: #14532d;
    line-height: 1.7;
    font-size: 16px;
}

.description-text strong {
    color: #166534;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .network-header {
        padding: 20px;
    }
    
    .network-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .network-form-container {
        padding: 25px 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .checkbox-group {
        flex-direction: column;
        gap: 15px;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .form-select, .form-textarea {
        max-width: 100%;
    }

    <?php /* 窄屏：例子说明落回输入框下方 */ ?>
    .textarea-container {
        flex-direction: column;
        gap: 18px;
    }

    .genelist-col {
        flex: 1 1 auto;
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
                    
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li>                    
<li><a href="/microsynteny.php">Microsynteny Analysis</a></li>
<li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li>
<li><a href="/mitdata.php">Mitogenomic Data</a></li>
                </ul>
            <li><a href="#" class="current">Transcriptome</a>
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Co-expression Network Analysis</b></legend>
<p class="paleo-intro">Enter a gene list — <b>a differentially expressed set, a gene cluster, or a single gene</b> — and this module builds the co-expression network around it, using highly correlated gene pairs to suggest functions for otherwise unannotated genes.</p>

<?php if ($nwNotice !== ''): ?>
<div class="gd-notice gd-warn" style="margin:18px 0"><?= $nwNotice ?></div>
<?php endif; ?>

<?php /* 表单容器 */ ?>
<div class="network-form-container">
    <form name="atidsearch" method="post" action="network.list.php" onSubmit="return checkquery()" encType="multipart/form-data">
        
        <?php /* 物种选择 */ ?>
        <div class="form-section">
            <div class="section-title">1. Select Targeted Species</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Select Class</label>
					<select name="class" class="form-select"
					        onchange="window.location.href='network.php?class='+encodeURIComponent(this.value)">
						<?php
						/* 切换 class 用 GET 重新加载本页（表单本身 POST 到 network.list.php，
						 * 不能直接 submit），服务端据 ?class= 重建下面的物种列表 */
						if (!in_array($class, $nwClassOrder, true)) {
							echo '<option value="' . htmlspecialchars($class) . '" selected="selected">'
							   . htmlspecialchars($class) . ' (unavailable)</option>';
						}
						foreach ($nwClassOrder as $__c) {
							echo '<option value="' . $__c . '"' . ($__c === $class ? ' selected="selected"' : '') . '>'
							   . $__c . '</option>';
						}
						?>
					</select>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Species</label>
					<select name="organism" class="form-select">
						<?php /* 候选由数据库生成（原 printOptions("datalib") 指向的下拉框在本页并不存在） */ ?>
						<?= cnido_options(isset($nwByClass[$class]) ? $nwByClass[$class] : array(), $specie) ?>
					</select>
                </div>
            </div>
        </div>
        
        <?php /* 基因列表输入 */ ?>
        <div class="form-section">
            <div class="section-title">2. Input Interested Gene List</div>
            <div class="textarea-container">
                <div class="genelist-col">
                    <label class="form-label" for="nw-genelist">Gene IDs &mdash; one per line, or separated by spaces / commas</label>
                    <div class="example-actions">
                        <a href="javascript:void(0)" class="example-link" onClick="nwLoadAbcExample();return false;">&#9662;&nbsp;Load the ABC transporter example (3 genes)</a>
                    </div>
                    <textarea name="genelist" id="nw-genelist" class="form-textarea" placeholder="Enter gene IDs, one per line &mdash; e.g. XP_048588491.1&#10;XP_032238263.1&#10;XP_032239123.2"></textarea>
                    <span id="nw-example-status" class="example-status"></span>
                </div>
                <div class="example-note">
                    <div class="example-note-title">Example &mdash; an ABC transporter-related gene list</div>
                    These three genes are <b>ABC transporter genes</b> of <i>Nematostella vectensis</i>,
                    and each has co-expression partners in that species&rsquo; network:
                    <ul class="example-gene-list">
                        <li><span class="example-gene">XP_048588491.1</span> &mdash; ABC transporter type 1, transmembrane domain (52 partners)</li>
                        <li><span class="example-gene">XP_032238263.1</span> &mdash; ABC transporter-like, ATP-binding domain (46 partners)</li>
                        <li><span class="example-gene">XP_032239123.2</span> &mdash; ABC transporter type 1, transmembrane domain (44 partners)</li>
                    </ul>
                    This box takes <b>gene IDs</b> &mdash; the identifiers shown on each gene page. It does <b>not</b> take a
                    keyword such as <code>ABC transporter</code>: to search by gene name or function, use the
                    <a href="../search.php">gene/function search</a> instead.
                </div>
            </div>
        </div>
        <?php /* 共表达关系选择 */ ?>
        <div class="form-section">
            <div class="section-title">3. Select Co-expression Relationship</div>
            <div class="checkbox-group">
                <div class="checkbox-item">
                    <input type="checkbox" name="group[]" value="positive" id="positive" class="checkbox-input" checked>
                    <label for="positive" class="checkbox-label">Positive Co-expression</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="group[]" value="negative" id="negative" class="checkbox-input" checked>
                    <label for="negative" class="checkbox-label">Negative Co-expression</label>
                </div>
            </div>
            <div style="margin-top: 15px; font-size: 16px; color: #64748b;">
                <strong>Note:</strong> "Positive" represents positive co-expression relationship with query gene, "Negative" represents negative co-expression relationship with query gene.
            </div>
        </div>
        
        <input name="category" type="hidden" value="global">
        
        <?php /* 按钮 */ ?>
        <div class="button-group">
            <button type="submit" class="btn-submit">
                🚀 Start Network Analysis
            </button>
            <button type="reset" class="btn-reset" onClick="nwClearExampleStatus();">
                🔄 Reset Form
            </button>
        </div>
        
    </form>
</div>

<script>
<?php /* 例子的三个基因号只存在于 Nematostella vectensis 的共表达表里，所以点例子时必须把物种一起
   定好，否则用户会在别的物种上提交、得到空的网络。原先调用 js/func.js 的 assignValuelpe1()，
   它写 document.atidsearch.species —— 本页并没有这个元素（物种选择器叫 organism），于是它设完
   genelist 之后立刻抛错，物种和复选框都没被设置。这里改为本页自足的实现。
   2026-09-27：三个号由 Lophelia pertusa 的 OS493_* 换成 Nematostella vectensis 的
   XP_048588491.1 / XP_032238263.1 / XP_032239123.2（各是 ABC transporter，在本物种的
   共表达表里分别有 52 / 46 / 44 个伙伴）。换物种不是因为这页 LPERT 数据少 ——
   LPERT_coexpress_positive 有 38 万行，比 NVECT 的 20 万行还多，本页行数最多的其实是
   PCLAV（653 万行）—— 而是因为站点默认物种统一取 Nematostella vectensis。 */ ?>
function nwLoadAbcExample() {
    var form = document.forms['atidsearch'];
    if (!form) { return false; }

    var box = form.elements['genelist'];
    if (box) { box.value = 'XP_048588491.1\nXP_032238263.1\nXP_032239123.2'; }

    var pos = document.getElementById('positive');
    var neg = document.getElementById('negative');
    if (pos) { pos.checked = true; }
    if (neg) { neg.checked = true; }

    var sel = form.elements['organism'];
    var matched = false;
    if (sel) {
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === 'Nematostella vectensis') { sel.selectedIndex = i; matched = true; break; }
        }
    }

    var st = document.getElementById('nw-example-status');
    if (st) {
        st.style.color = matched ? '#047857' : '#b45309';
        st.innerHTML = matched
            ? 'Loaded: the 3 ABC transporter genes are now in the box, and Species is set to <i>Nematostella vectensis</i>. Press &ldquo;Start Network Analysis&rdquo;.'
            : 'Loaded: the 3 ABC transporter genes are now in the box. But the species selector is currently showing another class &mdash; set <b>Select Class</b> to <b>Hexacorallia</b> and choose <i>Nematostella vectensis</i>, otherwise the analysis will find no network.';
    }
    return false;
}

function nwClearExampleStatus() {
    var st = document.getElementById('nw-example-status');
    if (st) { st.innerHTML = ''; }
}
</script>

</div>
</div>
</div>

<?php
    include "../Webpage_components.php";
    print $footer;
?>
</body>
</html>
