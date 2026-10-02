<?php
if(isset($_GET['session'])){
    $qnum = $_GET['session'];
    /* $qnum 来自 URL，会被拼进 ../tmp/<qnum>.* 的路径、也会回显到页面上，
       所以只接受纯数字的作业号，避免路径穿越与反射型 XSS。 */
    if (!preg_match('/^[0-9]{1,18}$/', (string)$qnum)) {
        echo '<script>alert("Invalid job ID.");window.location="GSEA.php";</script>';
        exit;
    }
}else{    
    $qnum = rand(100000000, 999999999);
    $list = trim(isset($_POST['queryList']) ? $_POST['queryList'] : '');
    $gene_set = (isset($_POST['geneSet']) && is_array($_POST['geneSet'])) ? $_POST['geneSet'] : array();
    if (isset($_GET['organism']) && $_GET['organism'] !== '') {
        $spe = $_GET['organism'];
    } elseif (isset($_POST['organism'])) {
        $spe = $_POST['organism'];
    } else {
        $spe = '';
    }
    $bgtype = $_POST['bgtype'];
    $testMethod = $_POST['testMethod'];
    $mt = $_POST['mt'];
    $cutoff = $_POST['cutoff'];
    
    $tmpfile = fopen("../tmp/$qnum.file", 'w');  # read query list
    fwrite($tmpfile, $list);
    
    if($list == ""){
        echo "<script>alert(\"You don't type any gene list, please check it and try again.\");</script>";
        echo "<script>window.location =\"GSEA.php\";</script>";
    }
    
    if (is_uploaded_file($_FILES['file']['tmp_name'])){   # here, query file is uploaded
        if ($_FILES['file']['size'] > 5000000){
            fopen("../tmp/$qnum.BigFileError", 'w');
        } else {
            move_uploaded_file($_FILES['file']['tmp_name'], "../tmp/$qnum.file");
        }
    }

    $conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    /* $spe 直接来自表单，必须先转义再拼进 SQL。 */
    $speEsc = mysqli_real_escape_string($conn, $spe);
    $query  = mysqli_query($conn, "SELECT * FROM abbr WHERE species = '$speEsc'");
    $result = $query ? mysqli_fetch_row($query) : null;
    if (!$result) {
        echo '<script>alert("Unknown species. Please pick a species from the list.");</script>';
        echo '<script>window.location ="GSEA.php";</script>';
        exit;
    }
    $species = $result[2];

    /* 该物种没有基因集文件时，GSEA.py 会在 open("database/<species>_BP") 处抛
       IOError 直接退出，既不写 .allDone 也不写 .NoResult，前端就会永远停在
       "Validating Your Request"。这里提前拦住并落一个明确的错误标记。
       下拉框已过滤，正常路径不会走到；这是对直接构造 POST 的兜底。 */
    if (!is_file(__DIR__ . "/database/{$species}_BP")) {
        fwrite(fopen("../tmp/$qnum.NoGeneSet", 'w'), "");
        $ipadr   = $_SERVER["REMOTE_ADDR"];
        $jobfile = fopen("job_record", 'a+');
        fwrite($jobfile, "$qnum\t$ipadr\tGSEA NoGeneSet\t" . date("Ymd-G:i:s") . "\n");
        fclose($jobfile);
        /* 跳过下面的建 .conf / 起进程，直接渲染页面 */
        $gene_set = array();
        $skipJob  = true;
    }

    if (empty($skipJob)) {
    $tmpconf = fopen("../tmp/$qnum.conf", 'w');   # read parameter
    $conf = "species\t$species\nbgtype\t$bgtype\ntestMethod\t$testMethod\nmt\t$mt\ncutoff\t$cutoff\n";
    fwrite($tmpconf, "$conf");
    
    foreach ($gene_set as $val){
        if ($val == "G1" || $val == "G2" || $val == "G3" || $val == "G4" || $val == "G5") {
            continue;
        } else {
            $grp_type = $species.'_'.$val;
            $fout = fopen("../tmp/$qnum.category", "a");
            fwrite($fout, "$grp_type\n");
            fclose($fout);
        }
    }
    
    if (file_exists("../tmp/$qnum.category")) {
        if ($bgtype == "suggested") {
            system("/usr/bin/python2 compute_precheck.py $qnum $species > ../tmp/$qnum.null &");
        } elseif ($bgtype == "customized") {
            $list2 = trim($_POST['bgList']);
            $tmpfile2 = fopen("../tmp/$qnum.bgfile", 'w');  # read background list
            fwrite($tmpfile2, $list2);
            if (is_uploaded_file($_FILES['bgfile']['tmp_name'])){   # here, background file is uploaded
                if ($_FILES['bgfile']['size'] > 5000000){
                    fopen("../tmp/$qnum.BigFileError", 'w');
                } else {
                    move_uploaded_file($_FILES['bgfile']['tmp_name'], "../tmp/$qnum.bgfile");
                }
            }
            system("/usr/bin/python2 compute_precheck.py $qnum $species > ../tmp/$qnum.null &");
        }
        $ipadr = $_SERVER["REMOTE_ADDR"];
        $jobfile = fopen("job_record", 'a+');
        $nowtime = date("Ymd-G:i:s");
        fwrite($jobfile, "$qnum\t$ipadr\tGSEA Analysis\t$nowtime\n");
    } else {
        $err = fopen("../tmp/$qnum.NoCategorySelected", "w");
        fwrite($err, "");
        fclose($err);
    }
    }   // end if (empty($skipJob))
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
if (file_exists("../tmp/$qnum.query")) {
    if (file_exists("../tmp/$qnum.allDone")){
        print "<meta http-equiv=\"REFRESH\" content=\"1; url=GSEAresult.php?session=$qnum\" />";
    } else {
        print "<meta http-equiv=\"REFRESH\" content=\"7; url=compute.php?session=$qnum\" />";
    }
} else {
    print "<meta http-equiv=\"REFRESH\" content=\"2; url=compute.php?session=$qnum\" />";
}
?>
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Gene Set Analysis - Processing - CnidoSite</title>
<meta name="keywords" content="Cnidaria, gene set enrichment analysis, GSEA, computational progress" />
<meta name="description" content="Processing your gene set enrichment analysis request" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />

<style>
<?php /* Compute页面专用样式 - 不影响全局CSS */ ?>
.compute-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.compute-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.compute-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.processing-badge {
    background:linear-gradient(135deg, #b45309 0%, #92400e 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

<?php /* 参数卡片美化 */ ?>
.params-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 30px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
}

.params-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.params-title::before {
    content: "⚙️";
    font-size: 24px;
}

.params-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 15px;
}

.params-table td {
    padding: 15px 20px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.params-label {
    font-weight: 600;
    color: #475569;
    width: 30%;
    font-size: 15px;
}

.params-value {
    color: #1e293b;
    font-size: 15px;
    word-break: break-word;
}

<?php /* 状态消息美化 */ ?>
.status-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 40px 30px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
    text-align: center;
}

.status-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.status-title {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 15px;
}

.status-description {
    font-size: 16px;
    color: #64748b;
    line-height: 1.7;
    max-width: 600px;
    margin: 0 auto;
}

<?php /* 进度条美化 */ ?>
.progress-container {
    margin: 30px 0;
    background: #f1f5f9;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #e2e8f0;
}

.progress-bar {
    height: 12px;
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    border-radius: 6px;
    width: 0%;
    transition: width 0.5s ease;
    margin-bottom: 15px;
}

.progress-text {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #475569;
    font-weight: 500;
}

<?php /* 错误状态 */ ?>
.error-status {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border-left: 4px solid #ef4444;
    padding: 25px 30px;
    border-radius: 12px;
    margin: 30px 0;
    color: #991b1b;
    text-align: center;
}

.success-status {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border-left: 4px solid #10b981;
    padding: 25px 30px;
    border-radius: 12px;
    margin: 30px 0;
    color: #065f46;
    text-align: center;
}

<?php /* 作业ID显示 */ ?>
.job-id {
    background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
    color: #5b21b6;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-block;
    margin: 15px 0;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 16px;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .compute-header {
        padding: 20px;
    }
    
    .compute-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .params-container {
        padding: 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .params-table td {
        display: block;
        width: 100%;
        margin-bottom: 10px;
    }
    
    .params-label {
        width: 100%;
        margin-bottom: 5px;
    }
    
    .status-container {
        padding: 30px 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
}

<?php /* 「← Choose another species」这颗按钮（只在「该物种没有基因集」那个分支里出现）
   本来落到 templatemo_style.css 里粘进来的 Bootstrap .btn-primary 上，而
   .btn-primary 的特异性 (0,1,0) 压不过 `a:link, a:visited{color:#1d4ed8}` (0,1,1)，
   于是按钮上的字是链接蓝压在 #007bff 上，对比度 1.40 —— 跟没写一样。
   加 `a.` 前缀凑平特异性，用站里其它主按钮的深蓝。 */ ?>
a.btn-primary {
    display: inline-block; padding: 9px 20px; border-radius: 8px;
    background: #1d4ed8; color: #fff; font-weight: 600;
    font-size: 14px; text-decoration: none;
}
a.btn-primary:hover { background: #1e40af; color: #fff; text-decoration: none; }
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
<p  class="paleo-intro">Your analysis is being processed. Please wait while we complete your request.</p>

<?php
/* NoGeneSet 分支不会写 .conf，file() 会返回 false 并告警；这里容错处理。 */
$fin = @file("../tmp/$qnum.conf");
foreach ((array)$fin as $line) {
    if (strpos($line, "\t") === false) { continue; }
    list($key, $value) = explode("\t", trim($line), 2);
    $para[$key] = $value;
}
$Species = $para['species'] ?? 'Unknown';
$cutoff = $para['cutoff'] ?? '0.05';
$testMethod = $para['testMethod'] ?? 'fisher';
$mt = $para['mt'] ?? 'BY';

if (!file_exists("../tmp/$qnum.query")) {
    if (file_exists("../tmp/$qnum.FormatError")) {
        echo '<div class="error-status">';
        echo '<div class="status-icon">❌</div>';
        echo '<div class="status-title">Format Error</div>';
        echo '<div class="status-description">There was an error with the format of your input file. Please check and try again.</div>';
        echo '</div>';
    } elseif (file_exists("../tmp/$qnum.NoCategorySelected")) {
        echo '<div class="error-status">';
        echo '<div class="status-icon">⚠️</div>';
        echo '<div class="status-title">No Category Selected</div>';
        echo '<div class="status-description">Please select at least one gene set category for analysis.</div>';
        echo '</div>';
    } elseif (file_exists("../tmp/$qnum.MaxLineError")) {
        echo '<div class="error-status">';
        echo '<div class="status-icon">📏</div>';
        echo '<div class="status-title">Too Many Genes</div>';
        echo '<div class="status-description">Your gene list exceeds the maximum allowed number of genes. Please reduce the size of your list.</div>';
        echo '</div>';
    } elseif (file_exists("../tmp/$qnum.NoGeneSet")) {
        echo '<div class="error-status">';
        echo '<div class="status-icon">🧬</div>';
        echo '<div class="status-title">No Gene Sets for This Species</div>';
        echo '<div class="status-description">Curated gene sets are not available for '
           . htmlspecialchars($Species) . ' in CnidoSite, so enrichment analysis cannot be run for it. '
           . 'Please go back and choose one of the species listed in the selector &mdash; only species with '
           . 'curated gene sets are offered there.</div>';
        echo '<div class="status-description" style="margin-top:10px">'
           . '<a class="btn-primary" href="GSEA.php">&#8592; Choose another species</a></div>';
        echo '</div>';
    } else {
        echo '<div class="status-container">';
        echo '<div class="status-icon">🔍</div>';
        echo '<div class="status-title">Validating Your Request</div>';
        echo '<div class="status-description">System has accepted your request. We are currently validating your input and preparing for analysis.</div>';
        echo '</div>';
        
        // 显示进度条
        echo '<div class="progress-container">';
        echo '<div class="progress-bar" id="progressBar"></div>';
        echo '<div class="progress-text">';
        echo '<span>Initializing...</span>';
        echo '<span>Step 1 of 4</span>';
        echo '</div>';
        echo '</div>';
    }
} elseif (file_exists("../tmp/$qnum.query")) {
    $lineNum = count(file("../tmp/$qnum.query"));
    
    echo '<div class="success-status">';
    echo '<div class="status-icon">✅</div>';
    echo '<div class="status-title">Request Submitted Successfully</div>';
    echo '<div class="status-description">Your analysis job has been queued and is being processed.</div>';
    echo '</div>';
    
    // 显示作业ID
    echo '<div style="text-align: center;">';
    echo '<div class="job-id">Job ID: ' . htmlspecialchars($qnum) . '</div>';
    echo '</div>';
    
    // 参数信息卡片
    echo '<div class="params-container">';
    echo '<div class="params-title">Analysis Parameters</div>';
    echo '<table class="params-table">';
    
    // 基因数量
    echo '<tr>';
    echo '<td class="params-label">Number of Genes</td>';
    echo '<td class="params-value">' . $lineNum . ' <span style="color: #64748b; font-size: 14px;">(after removing redundancy)</span></td>';
    echo '</tr>';
    
    // 物种
    echo '<tr>';
    echo '<td class="params-label">Species</td>';
    echo '<td class="params-value"><i>' . htmlspecialchars($Species) . '</i></td>';
    echo '</tr>';
    
    // 统计方法
    echo '<tr>';
    echo '<td class="params-label">Statistical Test Method</td>';
    echo '<td class="params-value">';
    if ($testMethod == 'fisher') {
        echo "Fisher Test";
    } elseif ($testMethod == 'dhyper') {
        echo "Hypergeometric Test";
    } elseif ($testMethod == 'chi2') {
        echo "Chi-square Test";
    }
    echo '</td>';
    echo '</tr>';
    
    // 多重检验校正
    echo '<tr>';
    echo '<td class="params-label">Multiple Testing Correction</td>';
    echo '<td class="params-value">';
    if ($mt == 'BY') {
        echo "Yekutieli (FDR under dependency)";
    } elseif ($mt == 'bonferroni') {
        echo "Bonferroni";
    } elseif ($mt == 'hochberg') {
        echo "Hochberg";
    } elseif ($mt == 'BH') {
        /* 'BH' 就是 Benjamini–Hochberg（compute.py 里 p.adjust(pv, mt) 直接收 mt），
           原来印成 "Hochberg (FDR)"，与上面那个真正的 'hochberg' 项、以及本页结果区
           自己写的 "Benjamini–Hochberg adjusted p-value" 都对不上 —— 同一个 run
           在两处被叫成两个名字。 */
        echo "Benjamini&ndash;Hochberg (FDR)";
    } elseif ($mt == 'hommel') {
        echo "Hommel";
    } elseif ($mt == 'holm') {
        echo "Holm";
    } elseif ($mt == 'none') {
        echo "No adjustment";
    }
    echo '</td>';
    echo '</tr>';
    
    // 基因集类别
    echo '<tr>';
    echo '<td class="params-label">Gene Set Categories</td>';
    echo '<td class="params-value">';
    if (file_exists("../tmp/$qnum.category")) {
        $f = file("../tmp/$qnum.category");
        foreach($f as $line){
            if (preg_match("/(.*)\_(.*)/", $line, $regs)) {
                echo '<span style="background: #ede9fe; color: #5b21b6; padding: 4px 8px; border-radius: 4px; margin: 0 5px 5px 0; display: inline-block; font-size: 13px;">' . htmlspecialchars($regs[2]) . '</span>';
            }
        }
    }
    echo '</td>';
    echo '</tr>';
    
    // 显著性阈值
    echo '<tr>';
    echo '<td class="params-label">Significance Cutoff</td>';
    echo '<td class="params-value">' . htmlspecialchars($cutoff) . '</td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</div>';
    
    // 处理状态
    if (file_exists("../tmp/$qnum.NoResult")) {
        echo '<div class="error-status">';
        echo '<div class="status-icon">🔍</div>';
        echo '<div class="status-title">No Enrichment Found</div>';
        echo '<div class="status-description">No significant enrichment was found for your gene list. Try adjusting your parameters or using a different gene set.</div>';
        echo '</div>';
    } else {
        echo '<div class="status-container">';
        if (file_exists("../tmp/$qnum.detail")) {
            echo '<div class="status-icon">🧬</div>';
            echo '<div class="status-title">Analyzing Enrichment</div>';
            echo '<div class="status-description">Generating enrichment analysis results. You may close this page and return later with your Job ID.</div>';
            
            // 进度条
            echo '<div class="progress-container">';
            echo '<div class="progress-bar" id="progressBar" style="width: 60%;"></div>';
            echo '<div class="progress-text">';
            echo '<span>Analyzing...</span>';
            echo '<span>Step 2 of 4</span>';
            echo '</div>';
            echo '</div>';
        }
        if (file_exists("../tmp/$qnum.sorted_detail")) {
            echo '<div class="status-icon">📊</div>';
            echo '<div class="status-title">Overlap Analysis</div>';
            echo '<div class="status-description">Completed overlap analysis between gene sets.</div>';
            
            // 进度条
            echo '<div class="progress-container">';
            echo '<div class="progress-bar" id="progressBar" style="width: 85%;"></div>';
            echo '<div class="progress-text">';
            echo '<span>Finalizing...</span>';
            echo '<span>Step 3 of 4</span>';
            echo '</div>';
            echo '</div>';
        }
        if (file_exists("../tmp/$qnum.allDone")) {
            echo '<div class="status-icon">🎉</div>';
            echo '<div class="status-title">Analysis Complete</div>';
            echo '<div class="status-description">All done! Redirecting to results page...</div>';
            
            // 进度条
            echo '<div class="progress-container">';
            echo '<div class="progress-bar" id="progressBar" style="width: 100%;"></div>';
            echo '<div class="progress-text">';
            echo '<span>Complete</span>';
            echo '<span>Step 4 of 4</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
}
?>

</div>
</div>
</div>

<?php
include "../Webpage_components.php";
print $footer;
?>
</body>
</html>