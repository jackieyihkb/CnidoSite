<?php
/* ---------------------------------------------------------------------------
 * 类群 / 物种下拉改为服务端渲染。
 *
 * 审稿意见 Referee 1 minor 1 & 3、Referee 2 major 1：
 *   "the species selector appears not to work"。
 * 本页原来的 <select name="species"> 是空的，里面只放了
 *   <script>regionState.printOptions("datalib");</script>
 * —— printOptions() 是 DynamicOptionList.js 里 Netscape 4 时代的方法，在现代
 * 浏览器里是空操作；物种列表实际由 <body onLoad> 的 initDynamicOptionLists()
 * 用写死在 JS 里的数组重建。后果：关掉 JavaScript / 脚本加载失败时下拉框是空的，
 * 表单提交出去的是空物种；页面也无法用 ?species= 深链过来。
 *
 * 改成服务端渲染后：
 *   ① 不依赖 JavaScript 也有完整选项；
 *   ② 支持 ?species=<拉丁名> 深链（与其它模块的 cnido_state 行为一致）；
 *   ③ 类群名与全站统一为 Hexacorallia（本页原来写作 Hexactiniaria，
 *      全站其他地方（browse.php、coverage_matrix.php 等）都用 Hexacorallia，
 *      同一个类群两个名字，用户会以为是两类生物）。
 *   ④ 物种列表直接来自数据库，不再出现 kegg.php 那样漏掉某个物种的硬编码数组。
 *   ⑤ 物种列表再收窄到真正有 <ABBR1>_go 表的 148 个物种。原来列的是 abbr 表的
 *      全部 326 个，其中 178 个（超过一半）选出来必然是一张空表，而空表的提示
 *      写着「没有找到注释」，读者会以为是自己的基因没有 GO 注释，而不是这个物种
 *      根本没做 GO 注释。表名单同样来自数据库（information_schema），所以④担心的
 *      「漏掉某个物种」不会因为这次收窄而回来；深链指向无注释物种时另给提示。
 *      与 proteindomain.php / kegg.php / interpro.php 同一套写法。
 * --------------------------------------------------------------------------- */
require_once __DIR__ . '/includes/state.php';

$__st = cnido_state('function', array(
    'species' => array('get' => 'species', 'default' => 'Nematostella vectensis'),
));
$__curSpecies = $__st['species'];

$__speciesList  = array();   // 拉丁名，按 (Class, 拉丁名) 排序
$__speciesClass = array();   // 拉丁名 => Class
$__classes      = array();   // 去重后的 Class 列表
$__conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');

/* 物种候选值只列真正有 <ABBR1>_go 表的物种。
 *
 * 原来这里取的是 abbr 表的全部 326 个物种，而其中只有 148 个有 go 表 ——
 * 剩下 178 个（超过一半）选中的结果是一张空表，而空表的提示写着「没有找到
 * 注释」，读者会以为是自己那个基因没有 Gene Ontology (GO) 注释，而不是这个物种根本没有做
 * 这项注释。本页说明框里写着 148，下拉框却给 326，两处自相矛盾。
 *
 * 表名单从 information_schema 读，不写死 —— 与 proteindomain.php /
 * DHS_analysis.php 同样的做法，将来补了注释的物种会自动出现在列表里，
 * 不需要改代码。 */
$__pfx = array();            // abbr1 => true
if (!$__conn->connect_error
    && ($__q0 = mysqli_query($__conn, "SELECT table_name FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name LIKE '%\\_go'"))) {
    while ($__r0 = mysqli_fetch_row($__q0)) { $__pfx[substr($__r0[0], 0, -3)] = true; }
}
if (!$__conn->connect_error) {
    $__q = mysqli_query($__conn,
        "SELECT a.abbr1, a.species, s.`Class` FROM abbr a
           LEFT JOIN speciesinfo s ON a.abbr1 = s.abbr
          WHERE a.species IS NOT NULL AND a.species <> ''
          ORDER BY s.`Class`, a.species");
    while ($__q && ($__r = mysqli_fetch_row($__q))) {
        if (!isset($__pfx[$__r[0]])) { continue; }   // 该物种没有 go 表
        $__sp = $__r[1];
        if (isset($__speciesClass[$__sp])) { continue; }
        $__speciesList[]          = $__sp;
        $__speciesClass[$__sp]    = $__r[2];
        if ($__r[2] !== null && $__r[2] !== '' && !in_array($__r[2], $__classes, true)) {
            $__classes[] = $__r[2];
        }
    }
}
sort($__classes);

/* ?species= 指向一个没有 go 表（或库里根本没有）的物种时退回默认物种，
   避免下拉框空白、与服务器的实际查询物种不一致。区别在于：这一次要说清楚 ——
   以前「这个物种没有做这项注释」和「这个基因没有命中」在页面上长得一模一样。
   注意 $__speciesClass 的值可能是 NULL（speciesinfo 里没有对应行），所以判断
   用 in_array 查候选列表，不能用 isset。 */
$__speciesNotice = '';
/* 深链传进来的可能是短码（NVECT）或下划线形式（Nematostella_vectensis），
   先解析成列表里的拉丁学名，免得把有数据的物种当成「没有注释」退回去。 */
if (!in_array($__curSpecies, $__speciesList, true)) {
    $__latin = cnido_latin_of($__curSpecies);
    if (in_array($__latin, $__speciesList, true)) { $__curSpecies = $__latin; }
}
if (!in_array($__curSpecies, $__speciesList, true)) {
    /* 提示里显示拉丁学名：深链可能是短码（PMULT），把代码写给读者看是站内
       一直纠正的一类问题。cnido_latin_of 查不到时原样返回。 */
    $__wanted     = cnido_latin_of($__curSpecies);
    if ($__wanted === '') { $__wanted = $__curSpecies; }
    $__curSpecies = 'Nematostella vectensis';
    if (!in_array($__curSpecies, $__speciesList, true) && !empty($__speciesList)) {
        $__curSpecies = $__speciesList[0];
    }
    if (strcasecmp($__wanted, $__curSpecies) !== 0) {
        $__speciesNotice = 'No Gene Ontology (GO) annotation is available for <b>'
                         . htmlspecialchars($__wanted) . '</b>; showing <b>'
                         . htmlspecialchars($__curSpecies) . '</b> instead. The selector below '
                         . 'lists all <b>' . count($__speciesList) . '</b> species that have GO '
                         . 'annotation.';
    }
}
$__curClass = isset($__speciesClass[$__curSpecies]) ? $__speciesClass[$__curSpecies] : '';

if (!function_exists('cnido_species_options')) {
    /** 渲染物种 <option>，带 data-class 供前端按类群过滤。 */
    function cnido_species_options($list, $classMap, $selected)
    {
        $h = '';
        foreach ($list as $sp) {
            $cls = isset($classMap[$sp]) ? $classMap[$sp] : '';
            $h .= '<option value="' . htmlspecialchars($sp, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"'
                . ($sp === $selected ? ' selected="selected"' : '') . '>'
                . htmlspecialchars($sp, ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
        }
        return $h;
    }
}

/* 类群变了以后，把不属于该类群的物种置灰并隐藏；若当前选中项被过滤掉，
   自动选第一个可见项。服务端已经渲染好完整列表，这里只是收窄显示范围。 */
$__filter_js = <<<'JS'
function filterSpeciesByClass() {
    var cls = document.querySelector('select[name="class"]');
    var org = document.querySelector('select[name="species"]');
    if (!cls || !org || !cls.options.length) { return; }
    var want = cls.options[cls.selectedIndex].value;
    var firstVisible = null;
    for (var i = 0; i < org.options.length; i++) {
        var o = org.options[i];
        var ok = (want === '' || o.getAttribute('data-class') === want);
        o.disabled = !ok;
        o.hidden   = !ok;
        if (ok && firstVisible === null) { firstVisible = o; }
    }
    var cur = org.options[org.selectedIndex];
    if (firstVisible !== null && (!cur || cur.disabled)) { firstVisible.selected = true; }
}
JS;
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Gene Ontology - CnidoSite</title>
<meta name="keywords" content="Cnidaria, gene ontology, GO terms, functional annotation" />
<meta name="description" content="Gene Ontology annotations for Cnidaria species, browsable by species and gene list" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>


<style>
<?php /* KEGG页面专用样式 - 不影响全局CSS */ ?>
.kegg-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.kegg-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.kegg-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.genome-badge {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

<?php /* 表单容器美化 */ ?>
.kegg-form-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 35px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.form-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-title::before {
    content: "🧬";
    font-size: 24px;
}

.form-group {
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

.form-textarea {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 16px;
    background: white;
    color: #1e293b;
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
    transition: all 0.3s ease;
}

.form-textarea:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.example-link {
    color:#047857;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
}

.example-link:hover {
    color:#047857;
    text-decoration: underline;
}

.button-group {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.btn-submit, .btn-reset {
    flex: 1;
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

<?php /* 介绍文本美化 */ ?>
.intro-card {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-left: 4px solid #10b981;
    padding: 20px 25px;
    border-radius: 12px;
    margin: 30px 0;
    color: #14532d;
    line-height: 1.7;
    font-size: 16px;
}

.intro-card strong {
    color: #166534;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .kegg-header {
        padding: 20px;
    }
    
    .kegg-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .kegg-form-container {
        padding: 25px 20px;
    }
    
    .button-group {
        flex-direction: column;
    }
}
</style>
</head>

<body onLoad="filterSpeciesByClass();">
<script>
<?php echo $__filter_js; ?></script>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Gene Ontology</b></legend>
<p  class="paleo-intro">Browse Gene Ontology annotations for a list of genes.</p>

<?php /* 表单容器 */ ?>
<div class="kegg-form-container">
<?php if ($__speciesNotice !== ''): ?>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #f59e0b;border-radius:10px;
                padding:12px 18px;margin:0 0 16px;color:#78350f;font-size:16px;line-height:1.7">
        <?= $__speciesNotice ?>
    </div>
<?php endif; ?>

    <div class="form-title">Input Gene List to Browse Gene Ontology</div>
    
    <form name="atidsearch" method="post" action="go_result.php" target="_blank" onSubmit="return checkquery()" encType="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Select Class</label>
            <select name="class" class="form-select" onchange="filterSpeciesByClass();">
<?php foreach ($__classes as $__c) { ?>
                <option value="<?= htmlspecialchars($__c, ENT_QUOTES, 'UTF-8') ?>"<?= ($__c === $__curClass) ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__c, ENT_QUOTES, 'UTF-8') ?></option>
<?php } ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Select Species</label>
            <select name="species" class="form-select">
<?= cnido_species_options($__speciesList, $__speciesClass, $__curSpecies) ?>            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Gene List</label>
            <textarea name="genelist" class="form-textarea" placeholder="Enter gene IDs, one per line or separated by spaces/comma"></textarea>
            <a href="javascript:void(0)" onClick="assignValuego()" class="example-link">
                📋 Example: Nematostella vectensis
            </a>
        </div>
        
        <div class="button-group">
            <button type="submit" class="btn-submit">
                🔍 Submit Query
            </button>
            <button type="reset" class="btn-reset">
                ↺ Reset Selection
            </button>
        </div>
    </form>
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
