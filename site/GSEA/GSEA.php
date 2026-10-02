<?php
if (isset($_GET['gsea_in'])) {
    $gsea_in = $_GET['gsea_in'];
} else {
    $gsea_in = $_POST['gsea_in'] ?? '';
}
?>
<?php
/* ---------------------------------------------------------------------
 * 物种下拉框改为服务端渲染。
 *
 * 原先 <select name="organism"> 里只有一个 <script>regionState.printOptions(...)
 * 调用，选项由 js/DynamicOptionList.js 在 body onLoad 时用一份硬编码的 2023 年
 * 物种名单重建 —— 既不随数据库更新，也在 JS 失效时留下一个空下拉框。
 * 这里直接从 abbr + speciesinfo 读出全部物种（当前 325 个），
 * Class 选择框只在前端做过滤，不再依赖旧库。
 * --------------------------------------------------------------------- */
$__conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
$__speciesClass = array();          // 拉丁名 => Class（值即 compute.php 期望的 abbr.species）
$__speciesAll   = 0;                // 库里有基因组的物种总数，用于提示文案
if (!$__conn->connect_error) {
    $__q = mysqli_query($__conn,
        "SELECT a.species, s.`Class`, a.abbr1 FROM abbr a
           LEFT JOIN speciesinfo s ON a.abbr1 = s.abbr
          WHERE a.species IS NOT NULL AND a.species <> ''
          ORDER BY s.`Class`, a.species");
    /* 只有 GSEA/database/<abbr1>_BP 存在的物种才能跑富集分析。
       此前下拉框列的是全部 325 个物种，选中没有基因集的物种时 compute.php 会
       一直轮询「Validating Your Request」永不返回 —— 用户看到的是死等。
       现在只列出真正可分析的 148 个，并在下方说明筛选依据。 */
    while ($__q && ($__r = mysqli_fetch_row($__q))) {
        $__speciesAll++;
        $__ab1 = (string)$__r[2];
        if ($__ab1 === '' || !is_file(__DIR__ . '/database/' . $__ab1 . '_BP')) { continue; }
        $__speciesClass[$__r[0]] = $__r[1];
    }
}
$__classes = array();
foreach ($__speciesClass as $__sp => $__cl) {
    if ($__cl !== null && $__cl !== '' && !in_array($__cl, $__classes, true)) { $__classes[] = $__cl; }
}
sort($__classes);
$__curSpecies = isset($__speciesClass['Nematostella vectensis'])
    ? 'Nematostella vectensis'
    : (count($__speciesClass) ? key($__speciesClass) : '');
$__curClass = ($__curSpecies !== '' && !empty($__speciesClass[$__curSpecies]))
    ? $__speciesClass[$__curSpecies] : (count($__classes) ? $__classes[0] : '');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Gene Set Enrichment Analysis - CnidoSite</title>
<meta name="keywords" content="Cnidaria, gene set enrichment analysis, GSEA, functional annotation, pathway analysis" />
<meta name="description" content="Gene Set Enrichment Analysis (GSEA) for functional interpretation of gene lists in Cnidaria species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script language="JavaScript" src="./func.js" type="text/javascript"></script>

<style>
<?php /* GSEA页面专用样式 - 不影响全局CSS */ ?>
.gsea-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.gsea-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.gsea-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.tools-badge {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

<?php /* 表单容器美化 */ ?>
.gsea-form-container {
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
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title::before {
    content: "⚙️";
    font-size: 24px;
}

<?php /* 表单行样式 */ ?>
.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 25px;
    margin-bottom: 25px;
    align-items: flex-start;
}

.form-group {
    flex: 1;
    min-width: 250px;
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
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

<?php /* 文本区域美化 */ ?>
.textarea-container {
    margin-bottom: 20px;
}

.textarea-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.example-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
}

.example-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.form-textarea {
    width: 100%;
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 15px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    background: #f8fafc;
    color: #1e293b;
    resize: vertical;
    transition: all 0.3s ease;
    min-height: 200px;
}

.form-textarea:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background: white;
}

<?php /* 复选框美化 */ ?>
.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.checkbox-item:hover {
    background: #f0f9ff;
    border-color:#1d4ed8;
}

.checkbox-input {
    width: 20px;
    height: 15px;
    accent-color:#1d4ed8;
    cursor: pointer;
}

.checkbox-label {
    font-size: 15px;
    color: #475569;
    cursor: pointer;
    font-weight: 500;
    flex: 1;
}

<?php /* 单选按钮美化 */ ?>
.radio-group {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 15px;
}

.radio-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.radio-item:hover {
    background: #f0f9ff;
    border-color:#1d4ed8;
}

.radio-input {
    width: 20px;
    height: 20px;
    accent-color:#1d4ed8;
    cursor: pointer;
}

.radio-label {
    font-size: 15px;
    color: #475569;
    cursor: pointer;
    font-weight: 500;
}

<?php /* 文件上传美化 */ ?>
.file-upload {
    margin-top: 15px;
}

.file-input-wrapper {
    position: relative;
    display: inline-block;
    width: 100%;
}

.file-input {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
}

.file-input-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px 20px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    color: #475569;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.file-input-label:hover {
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    border-color: #94a3b8;
    color: #475569;
}

<?php /* 参数选项样式 */ ?>
.param-options {
    background: #f8fafc;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    margin-top: 15px;
}

.param-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}

.param-row:last-child {
    margin-bottom: 0;
}

.param-item {
    flex: 1;
    min-width: 200px;
}

<?php /* 按钮美化 */ ?>
.button-group {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    justify-content: center;
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
    min-width: 150px;
}

.btn-submit {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
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

<?php /* 隐藏元素 */ ?>
.hidden {
    display: none !important;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .gsea-header {
        padding: 20px;
    }
    
    .gsea-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .gsea-form-container {
        padding: 25px 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .form-group {
        min-width: 100%;
    }
    
    .checkbox-group {
        grid-template-columns: 1fr;
    }
    
    .radio-group {
        flex-direction: column;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .btn-submit, .btn-reset {
        width: 100%;
    }
}
</style>

<script>
function queryExample() {
    document.GSEA.queryList.value = "XP_048581300.1\nXP_048581301.1\nXP_048581302.1\nXP_048581303.1\nXP_048581304.1\nXP_048581305.1\nXP_048587736.1\nXP_048579264.1\nXP_048579265.1\nXP_048579266.1\nXP_048579267.1\nXP_048579268.1\nXP_048579269.1\nXP_048580320.1\nXP_048585027.1\nXP_048585028.1\nXP_048580321.1\nXP_048585029.1\nXP_032236268.2\nXP_048580322.1\nXP_048581306.1\nXP_048581307.1\nXP_048581308.1\nXP_048581309.1\nXP_048581310.1\nXP_048581311.1\nXP_048581312.1\nXP_048581313.1\nXP_048581314.1\nXP_048581315.1\nXP_048581316.1\nXP_032219988.2\nXP_048581317.1\nXP_048580461.1\nXP_032219990.2\nXP_032219991.2\nXP_048580306.1\nXP_048580486.1\nXP_001627512.3\nXP_048580487.1\nXP_048581788.1\nXP_048580488.1\nXP_032219992.2\nXP_048581438.1\nXP_048589446.1\n";
}
function bgsuggested() {
    document.getElementById('bgList').style.display = 'none';
    document.getElementById('bgExa').style.display = 'none';
    document.getElementById('upbg').style.display = 'none';
    document.getElementsByName('bgfile')[0].style.display = 'none';
}

function bgcustomized() {
    document.getElementById('bgList').style.display = 'block';
    document.getElementById('bgExa').style.display = 'block';
    document.getElementById('upbg').style.display = 'block';
    document.getElementsByName('bgfile')[0].style.display = 'block';
}

function option_showhide(id, pic) {
    var obj = document.getElementById(id);
    if (obj.style.display == 'none') {
        obj.style.display = 'block';
    } else {
        obj.style.display = 'none';
    }
}
</script>
<script>
	<?php /* Class 选择框只负责过滤；物种 <select> 已由 PHP 渲染出全部物种，
   所以即使脚本没有执行，下拉框也不会是空的。 */ ?>
function gseaFilterSpecies() {
    var f = document.forms['GSEA'];
    if (!f || !f['Class'] || !f['organism']) { return; }
    var cls = f['Class'];
    var org = f['organism'];
    var want = cls.options[cls.selectedIndex] ? cls.options[cls.selectedIndex].value : '';
    var firstVisible = null;
    for (var i = 0; i < org.options.length; i++) {
        var o = org.options[i];
        var ok = (want === '' || o.getAttribute('data-class') === want);
        o.disabled = !ok;
        o.hidden = !ok;
        if (ok && firstVisible === null) { firstVisible = o; }
    }
    var cur = org.options[org.selectedIndex];
    if (firstVisible !== null && (!cur || cur.disabled)) { firstVisible.selected = true; }
}
</script>
</head>

<body onLoad="gseaFilterSpecies();">
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
            <li><a href="#">Phenotype</a><ul><li><a href="/phenotype.php?class=all">All</a></li><li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li><li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li><li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li><li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li><li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li></ul></li><li><a href="#" class="current">Tools</a>
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Gene Set Enrichment Analysis</b></legend>
<p class="paleo-intro">
    Identify significantly enriched biological functions and pathways from your gene list using Gene Set Enrichment Analysis (GSEA).
</p>

<?php
if(isset($_POST['job'])){
    /* 这个 job 分支现在站内已经没有页面在用了（所有到 GSEA 的链接都只是导航，
       表单传的是 gsea_in），留着是为了手写 POST 仍然可用。两处一起收拾：
       1) $job 原来直接拼进文件路径，POST 一个 ../../../etc/passwd 就能读任意文件；
          时间戳形式的 job 只含数字和字母，先过滤掉其它字符。
       2) 节点行的格式在 2026-09-21 从 { data: { id: 'GENE', ... } }（单引号字面量）
          换成了 JSON（{ data: {"id":"GENE",...} }），原来按单引号 split 再取
          $arry[1] 的做法在新格式下取不到任何东西，这里改成两种都认。 */
    $job = preg_replace('/[^0-9A-Za-z]/', '', (string)$_POST['job']);
    $fp0 = @fopen("../tmp/node$job.inc", "r");
    if ($fp0) {
        while(!feof($fp0)) {
            $line = chop(fgets($fp0));
            if (trim($line) === '') { continue; }
            $g = '';
            if (preg_match('/"id"\s*:\s*"([^"]*)"/', $line, $m)) {
                $g = $m[1];                       /* 新的 JSON 节点行 */
            } else {
                $arry = explode("'", $line);
                $g = isset($arry[1]) ? $arry[1] : '';   /* 旧的单引号节点行 */
            }
            if ($g !== '') { $gsea_in .= $g . "\n"; }
        }
        fclose($fp0);
    }
}
?>

<?php /* 表单容器 */ ?>
<div class="gsea-form-container">
    <form name="GSEA" method="post" action="./compute.php" onSubmit="return checkscript()" encType="multipart/form-data">
        
        <?php /* 物种选择 */ ?>
        <div class="form-section">
            <div class="section-title">1. Select Target Species</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Select Class</label>
					<select name="Class" class="form-select" onchange="gseaFilterSpecies()">
<?php foreach ($__classes as $__c): ?>
                        <option value="<?= htmlspecialchars($__c) ?>"<?= $__c === $__curClass ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__c) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Species</label>
					<select name="organism" class="form-select" id="gsea-organism">
<?php foreach ($__speciesClass as $__sp => $__cl): ?>
                            <option value="<?= htmlspecialchars($__sp) ?>" data-class="<?= htmlspecialchars($__cl) ?>"<?= $__sp === $__curSpecies ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__sp) ?></option>
<?php endforeach; ?>
                        </select>
                        <div class="form-hint" style="font-size:16px;color:#64748b;margin-top:4px">
                            <?= count($__speciesClass) ?> of <?= $__speciesAll ?> species in CnidoSite have curated
                            gene sets and can be analysed here; the list follows the Class selected above.
                        </div>
                </div>
            </div>
        </div>
        <?php /* 基因集选择 */ ?>
        <div class="form-section">
            <div class="section-title">2. Choose Gene Sets for Analysis</div>
            <div class="checkbox-group">
                <div class="checkbox-item">
                    <input type="checkbox" name="geneSet[]" value="G1" id="G1" checked class="checkbox-input">
                    <label for="G1" class="checkbox-label">G1: Gene Ontology Gene Sets</label>
                </div>
				<div class="checkbox-item">
                    <input type="checkbox" name="geneSet[]" value="BP" id="G1_BP" checked class="checkbox-input">
                    <label for="G1_BP" class="checkbox-label">BP: Biological Process</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="geneSet[]" value="CC" id="G1_CC" checked class="checkbox-input">
                    <label for="G1_CC" class="checkbox-label">CC: Cellular Component</label>
                </div>
               <div class="checkbox-item">
                    <input type="checkbox" name="geneSet[]" value="MF" id="G1_MF" checked class="checkbox-input">
                    <label for="G1_MF" class="checkbox-label">MF: Molecular Function</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="geneSet[]" value="G2" id="G2" checked class="checkbox-input">
                    <label for="G2" class="checkbox-label">G2: Gene Family Based Gene Sets</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="geneSet[]" value="UP" id="G2_UP" checked class="checkbox-input">
                    <label for="G2_UP" class="checkbox-label">UBs: Ubiquitins</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="geneSet[]" value="TF" id="G2_TF" checked class="checkbox-input">
                    <label for="G2_TF" class="checkbox-label">TFs: Transcription Factors</label>
                </div><br>
                <div class="checkbox-item">
                    <input type="checkbox" name="geneSet[]" value="KEGG" id="G3_KEGG" checked class="checkbox-input">
                    <label for="G3_KEGG" class="checkbox-label">G3: KEGG Pathways</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="geneSet[]" value="DOMAIN" id="G4_DOMAIN" checked class="checkbox-input">
                    <label for="G4_DOMAIN" class="checkbox-label">G4: Protein Domain</label>
                </div>
            </div>
        </div>
        
        <?php /* 基因列表输入 */ ?>
        <div class="form-section">
            <div class="section-title">3. Submit Your Query Gene List</div>
            <div class="textarea-container">
                <div class="textarea-header">
                    <span class="form-label">Enter gene names (one per line)</span>
                    <a href="javascript:void(0)" onClick="queryExample()" class="example-link">📋 Load Example</a>
                </div>
                <textarea name="queryList" class="form-textarea" placeholder="gene1&#10;gene2&#10;gene3&#10;..."><?php echo htmlspecialchars($gsea_in); ?></textarea>
            </div>
            
            <?php /* 文件上传 */ ?>
            <div class="file-upload">
                <label class="form-label">Or upload a file (max 5MB)</label>
                <div class="file-input-wrapper">
                    <input type="hidden" name="MAX_FILE_SIZE" value="5000000" />
                    <input type="file" name="file" class="file-input" accept=".txt,.csv,.tsv">
                    <div class="file-input-label">
                        <span>📁 Choose File</span>
                        <span>No file chosen</span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php /* 背景选择 */ ?>
        <div class="form-section">
            <div class="section-title">4. Choose Background Genes</div>
            <div class="radio-group">
                <div class="radio-item">
                    <input type="radio" name="bgtype" value="suggested" id="suggested" checked onclick="bgsuggested()" class="radio-input">
                    <label for="suggested" class="radio-label">Suggested background (Whole genome level)</label>
                </div>
                <div class="radio-item">
                    <input type="radio" name="bgtype" value="customized" id="customized" onclick="bgcustomized()" class="radio-input">
                    <label for="customized" class="radio-label">Customized background</label>
                </div>
            </div>
            
            <div id="bgList" class="hidden">
                <div class="textarea-container" style="margin-top: 20px;">
                    <div class="textarea-header">
                        <span class="form-label">Enter custom background gene list</span>
                        <a href="/GSEA/gsea/customized_background.txt" target="_blank" class="example-link" rel="noopener noreferrer">📄 View Example</a>
                    </div>
                    <textarea name="bgList" class="form-textarea" placeholder="Background gene1&#10;Background gene2&#10;..."></textarea>
                </div>
                
                <div class="file-upload" style="margin-top: 15px;">
                    <label class="form-label">Or upload a background file (max 5MB)</label>
                    <div class="file-input-wrapper">
                        <input type="hidden" name="MAX_FILE_SIZE" value="5000000" />
                        <input type="file" name="bgfile" class="file-input" accept=".txt,.csv,.tsv">
                        <div class="file-input-label">
                            <span>📁 Choose Background File</span>
                            <span>No file chosen</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php /* 参数选项 */ ?>
        <div class="form-section">
            <div class="section-title">
                <a href="javascript:option_showhide('GSEA_option', 'pic');" style="color: inherit; text-decoration: none;">
                    5. Parameter Selection (Optional)
                </a>
            </div>
            <div id="GSEA_option" class="param-options hidden">
                <div class="param-row">
                    <div class="param-item">
                        <label class="form-label">Statistical Test Method</label>
                        <select name="testMethod" class="form-select">
                            <option value="fisher" selected>Fisher</option>
                            <option value="dhyper">Hypergeometric</option>
                            <option value="chi2">Chi-square</option>
                        </select>
                    </div>
                    <div class="param-item">
                        <label class="form-label">Multi-test Adjustment Method</label>
                        <select name="mt" class="form-select">
                            <option value="BY" selected>Yekutieli (FDR under dependency)</option>
                            <option value="bonferroni">Bonferroni</option>
                            <option value="hochberg">Hochberg</option>
                            <option value="BH">Benjamini&ndash;Hochberg (FDR)</option>
                            <option value="hommel">Hommel</option>
                            <option value="holm">Holm</option>
                            <option value="none">NOT adjust</option>
                        </select>
                    </div>
                </div>
                <div class="param-row">
                    <div class="param-item">
                        <label class="form-label">Significance Level</label>
                        <select name="cutoff" class="form-select">
                            <option value="0.05" selected>0.05</option>
                            <option value="0.1">0.1</option>
                            <option value="0.01">0.01</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <?php /* 按钮 */ ?>
        <div class="button-group">
            <button type="submit" class="btn-submit">
                🚀 Start Analysis
            </button>
            <button type="reset" class="btn-reset">
                🔄 Reset Form
            </button>
        </div>
        
    </form>
</div>

</div>
</div>
</div>

<?php
    include "../Webpage_components.php";
    print $footer;
?>
</body>
</html>