<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Transcriptomic Heatmap - CnidoSite</title>
<meta name="keywords" content="Cnidaria, transcriptome, heatmap, gene expression, RNA-seq" />
<meta name="description" content="Interactive heatmap visualization of gene expression across transcriptomic samples" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/jquery.min.js"></script>
<script src="js/highcharts.js"></script>
<script src="js/heatmap.js"></script>
<script src="js/data.js"></script>
<script src="js/exporting.js"></script>

<style>
<?php /* Heatmap页面专用样式 - 不影响全局CSS */ ?>
.heatmap-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.heatmap-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.heatmap-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.transcriptome-badge {
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

<?php /* 介绍文本美化 */ ?>
.intro-card {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-left: 4px solid #3b82f6;
    padding: 25px 30px;
    border-radius: 12px;
    margin: 30px 0;
    color: #1e40af;
    line-height: 1.7;
    font-size: 16px;
}

.intro-card strong {
    color: #1e3a8a;
}

<?php /* 图表容器美化 */ ?>
.chart-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 25px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.chart-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 18px 25px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: -25px -25px 25px -25px;
}

.chart-header::before {
    content: "📊";
    font-size: 20px;
}

<?php /* 样本多的物种，图会比页面宽：套一层横向滚动，而不是把格子压扁到看不清。
   min-width:0 让 flex/grid 父级允许它自己被撑开。 */ ?>
.hm-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    min-width: 0;
    padding-bottom: 6px;
}

<?php /* 滚动条本身也做窄一点，别抢图的注意力 */ ?>
.hm-scroll::-webkit-scrollbar {
    height: 10px;
}
.hm-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 5px;
}
.hm-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 5px;
}
.hm-scroll::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

<?php /* 图下面的读图说明：左对齐、贴着图的宽度，颜色压到次要层级 */ ?>
.hm-caption {
    margin: 18px 0 0 0;
    font-size: 13px;
    line-height: 1.75;
    color: #475569;
    max-width: 1100px;
}
.hm-caption code {
    background: #f1f5f9;
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 12px;
    color: #334155;
}

<?php /* 统计信息美化 */ ?>
.stats-container {
    display: flex; /* 改用 Flex 布局以便整体居中 */
    justify-content: center; /* 让包含网格的整个区块居中对齐 */
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 30px 0;
    width: 100%;
    max-width: 1200px; /* 可选：限制网格的最大宽度，防止在超大屏幕上拉伸过宽 */
}

.stat-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
    padding: 20px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    font-size: 28px;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: #64748b;
    font-weight: 500;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .heatmap-header {
        padding: 20px;
    }
    
    .heatmap-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .chart-container {
        padding: 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .stats-container {
        grid-template-columns: 1fr;
        margin-left: 15px;
        margin-right: 15px;
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Expression Heatmap</b></legend>
<p class="paleo-intro">Interactive visualization of gene expression patterns across transcriptomic samples from Cnidaria species.</p>

<?php
/* ---- 输入 ----------------------------------------------------------------
   三个值以前是 $_POST['...'] 直接取：$_POST 里没有这个 key 时得到 null，
   后面全当空字符串用，页面照样渲染完，只是图是空的 —— 用户分不清是自己没带
   参数、物种写错了，还是这批基因真的没有数据。现在取干净的值，下面再逐个校验。
   species 以前直接拼进 abbr 查询和 TPM 表名，gene 直接拼进 WHERE，都是注入点；
   这里一律先转义 / 先核对真实表名。

   2026-09-23：改为 $_GET 优先、$_POST 兜底。此前只认 POST，而下面两处报错
   文案（"add species=LPERT to the URL"）一直在教用户用 GET —— 照着做永远
   进不来，页面只会回一句「No species was given」。现在 GET 真的生效，
   gene_detail.php 的「What you can do with this gene」面板就是靠这条链过来。
   取参顺序与 proteindomain_result.php / interpro_result.php 一致。 */
$spe      = isset($_GET['species'])  ? trim((string)$_GET['species'])
          : (isset($_POST['species'])  ? trim((string)$_POST['species'])  : '');
$rawGenes = isset($_GET['genelist']) ? (string)$_GET['genelist']
          : (isset($_POST['genelist']) ? (string)$_POST['genelist']       : '');

$genelist = array();
foreach (preg_split('/\r\n|\r|\n/', $rawGenes) as $hmLine) {
    $hmLine = trim($hmLine);
    if ($hmLine !== '') { $genelist[] = $hmLine; }
}
$genelist = array_values(array_unique($genelist));

$hmErr = '';

$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { die('database connection failed'); }

/* 物种解析。abbr1 必须真的存在于 abbr 表，并且真的有 <ABBR>_TPM 表；两条都不满足
   就明确报一次，而不是让表名退化成 "_TPM"、查询失败、最后画一张空图。 */
$result1 = null;
$tab     = '';
$dataset = '';
if ($spe === '') {
    $hmErr = 'No species was given. Open this page from the <b>Expression Profiling Analysis</b> button on a '
           . 'co-expression result page, which passes the species and the gene list along, or add '
           . '<code>species=LPERT</code> (an abbreviation from the species list) to the URL.';
} else {
    $speEsc = mysqli_real_escape_string($conn, $spe);
    $query1 = mysqli_query($conn, "SELECT * FROM abbr WHERE abbr1 = '$speEsc'");
    $result1 = $query1 ? mysqli_fetch_row($query1) : null;

    $tab  = $spe . '_TPM';
    $chk  = mysqli_query($conn, "SELECT COUNT(*) FROM information_schema.tables
                                  WHERE table_schema = DATABASE()
                                    AND table_name = '" . mysqli_real_escape_string($conn, $tab) . "'");
    $hasTab = ($chk && (int)mysqli_fetch_row($chk)[0] > 0);

    if ($result1 === null) {
        $hmErr = 'Unknown species abbreviation <b>' . htmlspecialchars($spe) . '</b>.';
        $tab = '';
    } elseif (!$hasTab) {
        $hmErr = 'No expression matrix is available for <b>' . htmlspecialchars($result1[0]) . '</b> '
               . '(<code>' . htmlspecialchars($tab) . '</code> does not exist), so no heatmap can be drawn.';
        $tab = '';
    } else {
        $dataset = $spe . '_RNA_seq_all';
    }
}
$LPERT_RNA_seq_all=array("SRR11359494_polyp_Colony1_sampled_2weeks_at_pH7_9"=>"1,","SRR11359495_polyp_Colony1_sampled_2weeks_at_pH7_9"=>"2,","SRR11359496_polyp_Colony1_sampled_4_5weeks_at_pH7_9"=>"3,","SRR11359497_polyp_Colony1_sampled_4_5weeks_at_pH7_9"=>"4,","SRR11359498_polyp_Colony1_sampled_8_5weeks_at_pH7_9"=>"5,","SRR11359499_polyp_Colony1_sampled_8_5weeks_at_pH7_9"=>"6,","SRR11359500_polyp_Colony1_sampled_2weeks_at_pH7_6"=>"7,","SRR11359501_polyp_Colony1_sampled_2weeks_at_pH7_6"=>"8,","SRR11359502_polyp_Colony1_sampled_4_5weeks_at_pH7_6"=>"9,","SRR11359503_polyp_Colony1_sampled_4_5weeks_at_pH7_6"=>"10,","SRR11359504_polyp_Colony1_sampled_8_5weeks_at_pH7_6"=>"11,","SRR11359505_polyp_Colony1_sampled_8_5weeks_at_pH7_6"=>"12,","SRR11359506_polyp_Colony3_sampled_2weeks_at_pH7_9"=>"13,","SRR11359507_polyp_Colony3_sampled_2weeks_at_pH7_9"=>"14,","SRR11359508_polyp_Colony3_sampled_4_5weeks_at_pH7_9"=>"15,","SRR11359509_polyp_Colony3_sampled_4_5weeks_at_pH7_9"=>"16,","SRR11359510_polyp_Colony3_sampled_8_5weeks_at_pH7_9"=>"17,","SRR11359511_polyp_Colony3_sampled_8_5weeks_at_pH7_9"=>"18,","SRR11359512_polyp_Colony3_sampled_2weeks_at_pH7_6"=>"19,","SRR11359513_polyp_Colony3_sampled_2weeks_at_pH7_6"=>"20,","SRR11359514_polyp_Colony3_sampled_4_5weeks_at_pH7_6"=>"21,","SRR11359515_polyp_Colony3_sampled_4_5weeks_at_pH7_6"=>"22,","SRR11359516_polyp_Colony3_sampled_8_5weeks_at_pH7_6"=>"23,","SRR11359517_polyp_Colony3_sampled_8_5weeks_at_pH7_6"=>"24,","SRR11359518_polyp_Colony4_sampled_2weeks_at_pH7_9"=>"25,","SRR11359519_polyp_Colony4_sampled_2weeks_at_pH7_9"=>"26,","SRR11359520_polyp_Colony4_sampled_4_5weeks_at_pH7_9"=>"27,","SRR11359521_polyp_Colony4_sampled_4_5weeks_at_pH7_9"=>"28,","SRR11359522_polyp_Colony4_sampled_8_5weeks_at_pH7_9"=>"29,","SRR11359523_polyp_Colony4_sampled_8_5weeks_at_pH7_9"=>"30,","SRR11359524_polyp_Colony4_sampled_2weeks_at_pH7_6"=>"31,","SRR11359525_polyp_Colony4_sampled_2weeks_at_pH7_6"=>"32,","SRR11359526_polyp_Colony4_sampled_4_5weeks_at_pH7_6"=>"33,","SRR11359527_polyp_Colony4_sampled_4_5weeks_at_pH7_6"=>"34,","SRR11359528_polyp_Colony4_sampled_8_5weeks_at_pH7_6"=>"35,","SRR11359529_polyp_Colony4_sampled_8_5weeks_at_pH7_6"=>"36,","SRR16705942_coral_polyp_control_treatment"=>"37,","SRR16705943_coral_polyp_control_treatment"=>"38,","SRR16705944_coral_polyp_oil_and_dispersant_treatment"=>"39,","SRR16705945_coral_polyp_oil_and_dispersant_treatment"=>"40,","SRR16705946_coral_polyp_control_treatment"=>"41,","SRR16705947_coral_polyp_oil_and_dispersant_treatment"=>"42,","SRR16705948_coral_polyp_oil_and_dispersant_treatment"=>"43,","SRR16705949_coral_polyp_oil_treatment"=>"44,","SRR16705950_coral_polyp_oil_treatment"=>"45,","SRR16705951_coral_polyp_oil_treatment"=>"46,","SRR16705952_coral_polyp_oil_treatment"=>"47,","SRR16705953_coral_polyp_dispersant_treatment"=>"48,","SRR16705954_coral_polyp_dispersant_treatment"=>"49,","SRR16705955_coral_polyp_dispersant_treatment"=>"50,","SRR16705956_coral_polyp_dispersant_treatment"=>"51,","SRR16705957_coral_polyp_control_treatment"=>"52,","SRR16705958_coral_polyp_dispersant_treatment"=>"53,","SRR16705959_coral_polyp_dispersant_treatment"=>"54,","SRR16705960_coral_polyp_control_treatment"=>"55,","SRR16705961_coral_polyp_oil_treatment"=>"56,","SRR16705962_coral_polyp_oil_treatment"=>"57,","SRR16705963_coral_polyp_oil_treatment"=>"58,","SRR16705964_coral_polyp_oil_treatment"=>"59,","SRR16705965_coral_polyp_control_treatment"=>"60,","SRR16705966_coral_polyp_dispersant_treatment"=>"61,","SRR16705967_coral_polyp_dispersant_treatment"=>"62,","SRR16705968_coral_polyp_dispersant_treatment"=>"63,","SRR16705969_coral_polyp_dispersant_treatment"=>"64,","SRR16705970_coral_polyp_control_treatment"=>"65,","SRR16705971_coral_polyp_control_treatment"=>"66,","SRR16705972_coral_polyp_control_treatment"=>"67,","SRR16705973_coral_polyp_control_treatment"=>"68,","SRR16705974_coral_polyp_control_treatment"=>"69,","SRR16705975_coral_polyp_control_treatment"=>"70,","SRR16705976_coral_polyp_oil_and_dispersant_treatment"=>"71,","SRR16705977_coral_polyp_oil_and_dispersant_treatment"=>"72,","SRR16705978_coral_polyp_oil_and_dispersant_treatment"=>"73,","SRR16705979_coral_polyp_oil_and_dispersant_treatment"=>"74,","SRR16705980_coral_polyp_oil_treatment"=>"75,","SRR16705981_coral_polyp_oil_treatment"=>"76,","SRR16705982_coral_polyp_oil_and_dispersant_treatment"=>"77,","SRR16705983_coral_polyp_oil_treatment"=>"78,","SRR16705984_coral_polyp_oil_treatment"=>"79,","SRR16705985_coral_polyp_dispersant_treatment"=>"80,","SRR16705986_coral_polyp_dispersant_treatment"=>"81,","SRR16705987_coral_polyp_dispersant_treatment"=>"82,","SRR16705988_coral_polyp_dispersant_treatment"=>"83,","SRR16705989_coral_polyp_control_treatment"=>"84,","SRR16705990_coral_polyp_control_treatment"=>"85,","SRR16705991_coral_polyp_control_treatment"=>"86,","SRR16705992_coral_polyp_control_treatment"=>"87,","SRR16705993_coral_polyp_oil_and_dispersant_treatment"=>"88,","SRR16705994_coral_polyp_oil_and_dispersant_treatment"=>"89,","SRR16705995_coral_polyp_oil_and_dispersant_treatment"=>"90,","SRR16705996_coral_polyp_oil_and_dispersant_treatment"=>"91,","SRR16705997_coral_polyp_oil_and_dispersant_treatment"=>"92,","SRR16705998_coral_polyp_oil_treatment"=>"93,","SRR16705999_coral_polyp_oil_treatment"=>"94,","SRR16706000_coral_polyp_oil_treatment"=>"95,","SRR16706001_coral_polyp_oil_treatment"=>"96,","SRR16706002_coral_polyp_dispersant_treatment"=>"97,","SRR16706003_coral_polyp_dispersant_treatment"=>"98,","SRR16706004_coral_polyp_oil_and_dispersant_treatment"=>"99,","SRR16706005_coral_polyp_oil_and_dispersant_treatment"=>"100,","SRR23025708_Polyp"=>"101,","SRR23025709_Polyp"=>"102,","SRR23025710_Polyp"=>"103,","SRR23025711_Polyp"=>"104,","SRR23025712_Polyp"=>"105,","SRR23025713_Polyp"=>"106,","SRR7746666_polyp"=>"107,","SRR7746667_polyp"=>"108,","SRR7746668_polyp"=>"109,","SRR7746669_polyp"=>"110,");
$HVULG_RNA_seq_all=array("SRR36435264_Regenerating_foot_0hpa_u0126_treatment"=>"1,","SRR36435265_Regenerating_foot_1_5hpa_dmso_treatment"=>"2,","SRR36435266_Regenerating_foot_1_5hpa_dmso_treatment"=>"3,","SRR36435267_Regenerating_foot_1_5hpa_dmso_treatment"=>"4,","SRR36435268_Regenerating_head_12hpa_u0126_treatment"=>"5,","SRR36435269_Regenerating_head_12hpa_u0126_treatment"=>"6,","SRR36435270_Regenerating_head_12hpa_u0126_treatment"=>"7,","SRR36435271_Regenerating_head_12hpa_dmso_treatment"=>"8,","SRR36435272_Regenerating_head_12hpa_dmso_treatment"=>"9,","SRR36435273_Regenerating_head_12hpa_dmso_treatment"=>"10,","SRR36435274_Regenerating_head_8hpa_25uM_U0126_treatment"=>"11,","SRR36435275_Regenerating_head_8hpa_25uM_U0126_treatment"=>"12,","SRR36435276_Regenerating_head_8hpa_25uM_U0126_treatment"=>"13,","SRR36435277_Regenerating_head_8hpa_dmso_treatment"=>"14,","SRR36435278_Regenerating_foot_3hpa_dmso_treatment"=>"15,","SRR36435279_Regenerating_head_8hpa_dmso_treatment"=>"16,","SRR36435280_Regenerating_head_8hpa_dmso_treatment"=>"17,","SRR36435281_Regenerating_head_0hpa_25uM_U0126_treatment"=>"18,","SRR36435282_Regenerating_head_0hpa_25uM_U0126_treatment"=>"19,","SRR36435283_Regenerating_head_0hpa_25uM_U0126_treatment"=>"20,","SRR36435284_Regenerating_head_0hpa_dmso_treatment"=>"21,","SRR36435285_Regenerating_head_0hpa_dmso_treatment"=>"22,","SRR36435286_Regenerating_head_0hpa_dmso_treatment"=>"23,","SRR36435287_Whole_animal_12h_25uM_U0126_treatment"=>"24,","SRR36435288_Whole_animal_12h_25uM_U0126_treatment"=>"25,","SRR36435289_Regenerating_foot_3hpa_dmso_treatment"=>"26,","SRR36435290_Whole_animal_12h_25uM_U0126_treatment"=>"27,","SRR36435291_Whole_animal_12h_dmso_treatment"=>"28,","SRR36435292_Whole_animal_12h_dmso_treatment"=>"29,","SRR36435293_Whole_animal_12h_dmso_treatment"=>"30,","SRR36435294_Regenerating_head_1_5hpa_25uM_U0126_treatment"=>"31,","SRR36435295_Regenerating_head_1_5hpa_25uM_U0126_treatment"=>"32,","SRR36435296_Regenerating_head_1_5hpa_25uM_U0126_treatment"=>"33,","SRR36435297_Regenerating_head_3hpa_25uM_U0126_treatment"=>"34,","SRR36435298_Regenerating_head_3hpa_25uM_U0126_treatment"=>"35,","SRR36435299_Regenerating_head_3hpa_25uM_U0126_treatment"=>"36,","SRR36435300_Regenerating_foot_3hpa_dmso_treatment"=>"37,","SRR36435301_Regenerating_head_0hpa_25uM_U0126_treatment"=>"38,","SRR36435302_Regenerating_head_0hpa_25uM_U0126_treatment"=>"39,","SRR36435303_Regenerating_head_0hpa_25uM_U0126_treatment"=>"40,","SRR36435304_Regenerating_head_1_5hpa_dmso_treatment"=>"41,","SRR36435305_Regenerating_head_1_5hpa_dmso_treatment"=>"42,","SRR36435306_Regenerating_head_1_5hpa_dmso_treatment"=>"43,","SRR36435307_Regenerating_head_3hpa_dmso_treatment"=>"44,","SRR36435308_Regenerating_head_3hpa_dmso_treatment"=>"45,","SRR36435309_Regenerating_head_3hpa_dmso_treatment"=>"46,","SRR36435310_Regenerating_head_0hpa_dmso_treatment"=>"47,","SRR36435311_Regenerating_foot_0hpa_dmso_treatment"=>"48,","SRR36435312_Regenerating_head_0hpa_dmso_treatment"=>"49,","SRR36435313_Regenerating_head_0hpa_dmso_treatment"=>"50,","SRR36435314_Regenerating_foot_1_5hpa_25uM_U0126_treatment"=>"51,","SRR36435315_Regenerating_foot_1_5hpa_25uM_U0126_treatment"=>"52,","SRR36435316_Regenerating_foot_1_5hpa_25uM_U0126_treatment"=>"53,","SRR36435317_Regenerating_foot_3hpa_25uM_U0126_treatment"=>"54,","SRR36435318_Regenerating_foot_3hpa_25uM_U0126_treatment"=>"55,","SRR36435319_Regenerating_foot_3hpa_25uM_U0126_treatment"=>"56,","SRR36435320_Regenerating_foot_0hpa_25uM_U0126_treatment"=>"57,","SRR36435321_Regenerating_foot_0hpa_25uM_U0126_treatment"=>"58,","SRR36435322_Regenerating_foot_0hpa_dmso_treatment"=>"59,","SRR36435323_Regenerating_foot_0hpa_dmso_treatment"=>"60,");
$HVIRI_RNA_seq_all=array("DRR048593_aposymbioic_hydra_rep1_M9_strain"=>"1,","DRR048594_aposymbioic_hydra_rep2_M9_strain"=>"2,","DRR048595_symbioic_hydra_rep1_M9_strain"=>"3,","DRR048596_symbioic_hydra_rep2_M9_strain"=>"4,","ERR13389755"=>"5,","SRR10058802_Whole"=>"6,","SRR10058803_Whole"=>"7,","SRR10058804_Whole"=>"8,","SRR10058805_Whole"=>"9,","SRR10058806_Whole"=>"10,","SRR10058807_Whole"=>"11,","SRR21134050_whole_body"=>"12,","SRR21134051_whole_body"=>"13,","SRR21134052_whole_body"=>"14,","SRR21134053_whole_body"=>"15,","SRR21134054_whole_body"=>"16,","SRR21134055_whole_body"=>"17,","SRR21134056_whole_body"=>"18,","SRR21134057_whole_body"=>"19,","SRR21134058_whole_body"=>"20,","SRR21134059_whole_body"=>"21,","SRR21134060_whole_body"=>"22,","SRR21134061_whole_body"=>"23,","SRR21134062_whole_body"=>"24,","SRR21134063_whole_body"=>"25,","SRR21134064_whole_body"=>"26,","SRR21134065_whole_body"=>"27,","SRR21134066_whole_body"=>"28,","SRR21134067_whole_body"=>"29,");
$MCAPR_RNA_seq_all=array("SRR12710846_Polyps_OA3_day3"=>"1,","SRR12710847_Polyps_OA3_day3"=>"2,","SRR12710848_Polyps_OA3_day3"=>"3,","SRR12710855_Polyps_OA3_day9"=>"4,","SRR12710857_Polyps_OA3_day9"=>"5,","SRR12710858_Polyps_OA3_day9"=>"6,","SRR12786896_Polyps_OA3_day0"=>"7,","SRR12786897_Polyps_OA3_day0"=>"8,","SRR12786898_Polyps_OA3_day0"=>"9,","SRR12807381_Polyps_OA3_day0"=>"10,","SRR12849113_Polyps_OA3_day0"=>"11,","SRR12904781_Polyps"=>"12,","SRR12904782_Polyps"=>"13,","SRR12904783_Polyps"=>"14,","SRR12927880_Polyps_E3_day0"=>"15,","SRR12959182_Polyps_E3_day0"=>"16,","SRR12959183_Polyps_E3_day0"=>"17,","SRR12959184_Polyps_E3_day0"=>"18,","SRR12959188_Polyps_E3_day21"=>"19,","SRR12959189_Polyps_E3_day21"=>"20,","SRR12959190_Polyps_E3_day21"=>"21,","SRR12959201_Polyps_E3_day15"=>"22,","SRR12959202_Polyps_E3_day15"=>"23,","SRR12959203_Polyps_E3_day15"=>"24,","SRR12959214_Polyps_E3_day9"=>"25,","SRR12959215_Polyps_E3_day9"=>"26,","SRR12959216_Polyps_E3_day9"=>"27,","SRR12959227_Polyps_E3_day3"=>"28,","SRR12959229_Polyps_E3_day3"=>"29,","SRR12959230_Polyps_E3_day3"=>"30,","SRR12963484_Polyps_E3_day0"=>"31,","SRR27940191_Polyps"=>"32,","SRR27940192_Polyps"=>"33,","SRR27940193_Polyps"=>"34,","SRR9129316_Polyps"=>"35,","SRR9613519_Polyps"=>"36,");
$MCAPI_RNA_seq_all=array("SRR11452216_whole_organisms_fertilized_embryo_low_pH_treatment"=>"1,","SRR11452217_whole_organisms_fertilized_embryo_ambient_pH_treatment"=>"2,","SRR11452218_whole_organisms_fertilized_embryo_low_pH_treatment"=>"3,","SRR11452219_whole_organisms_fertilized_embryo_extra_low_pH_treatment_pH_treatment"=>"4,","SRR11452220_whole_organisms_fertilized_embryo_extra_low_pH_treatment_pH_treatment"=>"5,","SRR11452221_whole_organisms_planulae_low_pH_treatment"=>"6,","SRR11452222_whole_organisms_planulae_ambient_pH_treatment"=>"7,","SRR11452223_whole_organisms_planulae_low_pH_treatment"=>"8,","SRR11452224_whole_organisms_planulae_low_pH_treatment"=>"9,","SRR11452225_whole_organisms_planulae_extra_low_pH_treatment_pH_treatment"=>"10,","SRR11452226_whole_organisms_planulae_extra_low_pH_treatment_pH_treatment"=>"11,","SRR11452227_whole_organisms_planulae_extra_low_pH_treatment_pH_treatment"=>"12,","SRR11452228_whole_organisms_planulae_ambient_pH_treatment"=>"13,","SRR11452229_whole_organisms_fertilized_embryo_low_pH_treatment"=>"14,","SRR11452230_whole_organisms_planulae_ambient_pH_treatment"=>"15,","SRR11452231_whole_organisms_blastula_ambient_pH_treatment"=>"16,","SRR11452232_whole_organisms_blastula_ambient_pH_treatment"=>"17,","SRR11452233_whole_organisms_morula_ambient_pH_treatment"=>"18,","SRR11452234_whole_organisms_morula_ambient_pH_treatment"=>"19,","SRR11452235_whole_organisms_gastrula_extra_low_pH_treatment_pH_treatment"=>"20,","SRR11452236_whole_organisms_gastrula_low_pH_treatment"=>"21,","SRR11452237_whole_organisms_gastrula_ambient_pH_treatment"=>"22,","SRR11452238_whole_organisms_gastrula_low_pH_treatment"=>"23,","SRR11452239_whole_organisms_gastrula_extra_low_pH_treatment_pH_treatment"=>"24,","SRR11452240_whole_organisms_fertilized_embryo_ambient_pH_treatment"=>"25,","SRR11452241_whole_organisms_gastrula_low_pH_treatment"=>"26,","SRR11452242_whole_organisms_gastrula_ambient_pH_treatment"=>"27,","SRR11452243_whole_organisms_gastrula_ambient_pH_treatment"=>"28,","SRR11452244_whole_organisms_prawn_chip_extra_low_pH_treatment_pH_treatment"=>"29,","SRR11452245_whole_organisms_prawn_chip_low_pH_treatment"=>"30,","SRR11452246_whole_organisms_prawn_chip_ambient_pH_treatment"=>"31,","SRR11452247_whole_organisms_prawn_chip_low_pH_treatment"=>"32,","SRR11452248_whole_organisms_prawn_chip_extra_low_pH_treatment_pH_treatment"=>"33,","SRR11452249_whole_organisms_prawn_chip_low_pH_treatment"=>"34,","SRR11452250_whole_organisms_prawn_chip_ambient_pH_treatment"=>"35,","SRR11452251_whole_organisms_egg_ambient_pH_treatment"=>"36,","SRR11452252_whole_organisms_prawn_chip_ambient_pH_treatment"=>"37,","SRR11452253_whole_organisms_cleavage_low_pH_treatment"=>"38,","SRR11452254_whole_organisms_cleavage_ambient_pH_treatment"=>"39,","SRR11452255_whole_organisms_cleavage_low_pH_treatment"=>"40,","SRR11452256_whole_organisms_cleavage_extra_low_pH_treatment_pH_treatment"=>"41,","SRR11452257_whole_organisms_cleavage_extra_low_pH_treatment_pH_treatment"=>"42,","SRR11452258_whole_organisms_cleavage_low_pH_treatment"=>"43,","SRR11452259_whole_organisms_cleavage_ambient_pH_treatment"=>"44,","SRR11452260_whole_organisms_cleavage_ambient_pH_treatment"=>"45,","SRR11452261_whole_organisms_fertilized_embryo_extra_low_pH_treatment_pH_treatment"=>"46,","SRR11452262_whole_organisms_egg_ambient_pH_treatment"=>"47,","SRR11452263_whole_organisms_egg_ambient_pH_treatment"=>"48,");
$NVECT_RNA_seq_all=array("SRR6320836_whole_6w_aboral_regenerate_6w_96hpa"=>"1,","SRR6320837_whole_6w_aboral_regenerate_6w_96hpa"=>"2,","SRR6320838_whole_6w_aboral_regenerate_6w_144hpa"=>"3,","SRR6320839_whole_6w_aboral_regenerate_6w_0hpa"=>"4,","SRR6320840_whole_6w_aboral_regenerate_6w_0hpa"=>"5,","SRR6320841_whole_6w_aboral_regenerate_6w_144hpa"=>"6,","SRR6320842_whole_6w_aboral_regenerate_6w_2hpa"=>"7,","SRR6320843_whole_6w_aboral_regenerate_6w_uncut"=>"8,","SRR6320844_whole_6w_aboral_regenerate_6w_uncut"=>"9,","SRR6320845_whole_6w_aboral_regenerate_6w_uncut"=>"10,","SRR6320846_whole_6w_aboral_regenerate_6w_0hpa"=>"11,","SRR6320847_whole_6w_aboral_regenerate_6w_144hpa"=>"12,","SRR6320848_whole_6w_aboral_regenerate_6w_2hpa"=>"13,","SRR6320849_whole_6w_aboral_regenerate_6w_4hpa"=>"14,","SRR6320850_whole_6w_aboral_regenerate_6w_120hpa"=>"15,","SRR6320851_whole_6w_aboral_regenerate_6w_36hpa"=>"16,","SRR6320852_whole_6w_aboral_regenerate_6w_36hpa"=>"17,","SRR6320853_whole_6w_aboral_regenerate_6w_24hpa"=>"18,","SRR6320854_whole_6w_aboral_regenerate_6w_24hpa"=>"19,","SRR6320855_whole_6w_aboral_regenerate_6w_36hpa"=>"20,","SRR6320856_whole_6w_aboral_regenerate_6w_24hpa"=>"21,","SRR6320857_whole_6w_aboral_regenerate_6w_20hpa"=>"22,","SRR6320858_whole_6w_aboral_regenerate_6w_16hpa"=>"23,","SRR6320859_whole_6w_aboral_regenerate_6w_20hpa"=>"24,","SRR6320860_whole_6w_aboral_regenerate_6w_20hpa"=>"25,","SRR6320861_whole_6w_aboral_regenerate_6w_2hpa"=>"26,","SRR6320862_whole_6w_aboral_regenerate_6w_72hpa"=>"27,","SRR6320863_whole_6w_aboral_regenerate_6w_96hpa"=>"28,","SRR6320864_whole_6w_aboral_regenerate_6w_72hpa"=>"29,","SRR6320865_whole_6w_aboral_regenerate_6w_72hpa"=>"30,","SRR6320866_whole_6w_aboral_regenerate_6w_60hpa"=>"31,","SRR6320867_whole_6w_aboral_regenerate_6w_60hpa"=>"32,","SRR6320868_whole_6w_aboral_regenerate_6w_48hpa"=>"33,","SRR6320869_whole_6w_aboral_regenerate_6w_60hpa"=>"34,","SRR6320870_whole_6w_aboral_regenerate_6w_48hpa"=>"35,","SRR6320871_whole_6w_aboral_regenerate_6w_48hpa"=>"36,","SRR6320872_whole_6w_aboral_regenerate_6w_4hpa"=>"37,","SRR6320873_whole_6w_aboral_regenerate_6w_4hpa"=>"38,","SRR6320874_whole_6w_aboral_regenerate_6w_8hpa"=>"39,","SRR6320875_whole_6w_aboral_regenerate_6w_8hpa"=>"40,","SRR6320876_whole_6w_aboral_regenerate_6w_8hpa"=>"41,","SRR6320877_whole_6w_aboral_regenerate_6w_12hpa"=>"42,","SRR6320878_whole_6w_aboral_regenerate_6w_12hpa"=>"43,","SRR6320879_whole_6w_aboral_regenerate_6w_12hpa"=>"44,","SRR6320880_whole_6w_aboral_regenerate_6w_16hpa"=>"45,","SRR6320881_whole_6w_aboral_regenerate_6w_16hpa"=>"46,","SRR6320882_whole_6w_aboral_regenerate_6w_120hpa"=>"47,","SRR6320883_whole_6w_aboral_regenerate_6w_120hpa"=>"48,");
$MFOLI_RNA_seq_all=array("SRR12710845_Polyps_OA4_day3"=>"1,","SRR12710852_Polyps_OA4_day9"=>"2,","SRR12710853_Polyps_OA4_day9"=>"3,","SRR12710854_Polyps_OA4_day9"=>"4,","SRR12710865_Polyps_OA4_day3"=>"5,","SRR12710866_Polyps_OA4_day3"=>"6,","SRR12786895_Polyps_OA4_day0"=>"7,","SRR12786903_Polyps_OA4_day0"=>"8,","SRR12786904_Polyps_OA4_day0"=>"9,","SRR12807380_Polyps_OA4_day0"=>"10,","SRR12849112_Polyps_OA4_day0"=>"11,","SRR12904780_Polyps"=>"12,","SRR12904791_Polyps"=>"13,","SRR12904792_Polyps"=>"14,","SRR12927879_Polyps_E4_day0"=>"15,","SRR12959181_Polyps_E4_day0"=>"16,","SRR12959185_Polyps_E4_day21"=>"17,","SRR12959186_Polyps_E4_day21"=>"18,","SRR12959187_Polyps_E4_day21"=>"19,","SRR12959198_Polyps_E4_day15"=>"20,","SRR12959199_Polyps_E4_day15"=>"21,","SRR12959200_Polyps_E4_day15"=>"22,","SRR12959211_Polyps_E4_day9"=>"23,","SRR12959212_Polyps_E4_day9"=>"24,","SRR12959213_Polyps_E4_day9"=>"25,","SRR12959224_Polyps_E4_day3"=>"26,","SRR12959225_Polyps_E4_day3"=>"27,","SRR12959226_Polyps_E4_day3"=>"28,","SRR12959237_Polyps_E4_day0"=>"29,","SRR12959238_Polyps_E4_day0"=>"30,","SRR12963483_Polyps_E4_day0"=>"31,","SRR27940177_Polyps"=>"32,","SRR27940179_Polyps"=>"33,","SRR27940180_Polyps"=>"34,","SRR9129315_Polyps"=>"35,","SRR9613518_Polyps"=>"36,");
$PCLAV_RNA_seq_all=array("SRR19977425_apical_branchlet_Temperature_treatment_at_T25"=>"1,","SRR19977426_apical_branchlet_Temperature_treatment_at_T0"=>"2,","SRR19977427_apical_branchlet_Temperature_treatment_at_T0"=>"3,","SRR19977428_apical_branchlet_Temperature_treatment_at_T0"=>"4,","SRR19977432_apical_branchlet_Temperature_treatment_at_T25"=>"5,","SRR19977433_apical_branchlet_Control_at_T25"=>"6,","SRR19977434_apical_branchlet_Temperature_treatment_at_T25"=>"7,","SRR19977435_apical_branchlet_Temperature_treatment_at_T25"=>"8,","SRR19977436_apical_branchlet_Temperature_treatment_at_T0"=>"9,","SRR19977437_apical_branchlet_Temperature_treatment_at_T0"=>"10,","SRR19977438_apical_branchlet_Temperature_treatment_at_T0"=>"11,","SRR19977439_apical_branchlet_Control_at_T25"=>"12,","SRR19977440_apical_branchlet_Control_at_T25"=>"13,","SRR19977441_apical_branchlet_Control_at_T25"=>"14,","SRR19977442_apical_branchlet_Control_at_T0"=>"15,","SRR19977443_apical_branchlet_Control_at_T0"=>"16,","SRR19977444_apical_branchlet_Control_at_T25"=>"17,","SRR19977445_apical_branchlet_Control_at_T0"=>"18,","SRR19977446_apical_branchlet_Control_at_T0"=>"19,","SRR19977455_apical_branchlet_Control_at_T25"=>"20,","SRR19977463_apical_branchlet_Temperature_treatment_at_T25"=>"21,");
$OFAVE_RNA_seq_all=array("SRR22214401_holobiont_control_pH_Control_temp"=>"1,","SRR22214472_holobiont_low_pH_high_temp"=>"2,","SRR22214473_holobiont_low_pH_high_temp"=>"3,","SRR22214474_holobiont_low_pH_high_temp"=>"4,","SRR22214475_holobiont_low_pH_high_temp"=>"5,","SRR22214476_holobiont_low_pH_high_temp"=>"6,","SRR22214477_holobiont_low_pH_high_temp"=>"7,","SRR22214478_holobiont_low_pH_high_temp"=>"8,","SRR22214479_holobiont_low_pH_high_temp"=>"9,","SRR22214480_holobiont_low_pH_high_temp"=>"10,","SRR22214482_holobiont_low_pH_high_temp"=>"11,","SRR22214483_holobiont_low_pH_high_temp"=>"12,","SRR22214484_holobiont_low_pH_high_temp"=>"13,","SRR22214485_holobiont_low_pH_high_temp"=>"14,","SRR22214486_holobiont_low_pH_high_temp"=>"15,","SRR22214487_holobiont_low_pH_high_temp"=>"16,","SRR22214488_holobiont_low_pH_high_temp"=>"17,","SRR22214489_holobiont_low_pH_high_temp"=>"18,","SRR22214490_holobiont_low_pH_high_temp"=>"19,","SRR22214491_holobiont_low_pH_high_temp"=>"20,","SRR22214493_holobiont_low_pH_Control_temp"=>"21,","SRR22214494_holobiont_low_pH_Control_temp"=>"22,","SRR22214495_holobiont_low_pH_Control_temp"=>"23,","SRR22214496_holobiont_control_pH_high_temp"=>"24,","SRR22214497_holobiont_control_pH_high_temp"=>"25,","SRR22214498_holobiont_control_pH_high_temp"=>"26,","SRR22214499_holobiont_control_pH_high_temp"=>"27,","SRR22214500_holobiont_control_pH_high_temp"=>"28,","SRR22214501_holobiont_control_pH_high_temp"=>"29,","SRR22214502_holobiont_control_pH_high_temp"=>"30,","SRR22214503_holobiont_control_pH_Control_temp"=>"31,","SRR22214504_holobiont_control_pH_Control_temp"=>"32,","SRR22214505_holobiont_control_pH_Control_temp"=>"33,","SRR22214507_holobiont_control_pH_Control_temp"=>"34,","SRR22214508_holobiont_control_pH_Control_temp"=>"35,","SRR22214509_holobiont_control_pH_Control_temp"=>"36,","SRR22214510_holobiont_control_pH_Control_temp"=>"37,","SRR22214511_holobiont_control_pH_Control_temp"=>"38,","SRR22214512_holobiont_control_pH_Control_temp"=>"39,","SRR22214513_holobiont_control_pH_Control_temp"=>"40,","SRR22214514_holobiont_control_pH_Control_temp"=>"41,","SRR22214515_holobiont_control_pH_Control_temp"=>"42,","SRR22214516_holobiont_control_pH_Control_temp"=>"43,","SRR22214517_holobiont_low_pH_Control_temp"=>"44,","SRR22214518_holobiont_low_pH_Control_temp"=>"45,","SRR22214519_holobiont_low_pH_Control_temp"=>"46,","SRR22214520_holobiont_low_pH_Control_temp"=>"47,","SRR22214521_holobiont_low_pH_Control_temp"=>"48,","SRR22214522_holobiont_low_pH_Control_temp"=>"49,","SRR22214523_holobiont_low_pH_Control_temp"=>"50,","SRR22214525_holobiont_low_pH_Control_temp"=>"51,","SRR22214526_holobiont_low_pH_Control_temp"=>"52,","SRR22214527_holobiont_low_pH_Control_temp"=>"53,","SRR22214528_holobiont_low_pH_Control_temp"=>"54,","SRR22214529_holobiont_low_pH_Control_temp"=>"55,","SRR22214530_holobiont_low_pH_Control_temp"=>"56,","SRR22214531_holobiont_control_pH_high_temp"=>"57,","SRR22214532_holobiont_control_pH_high_temp"=>"58,","SRR22214533_holobiont_control_pH_high_temp"=>"59,","SRR22214534_holobiont_control_pH_high_temp"=>"60,","SRR22214536_holobiont_control_pH_high_temp"=>"61,","SRR22214537_holobiont_control_pH_high_temp"=>"62,","SRR22214538_holobiont_control_pH_high_temp"=>"63,","SRR22214539_holobiont_control_pH_high_temp"=>"64,","SRR22214540_holobiont_control_pH_high_temp"=>"65,","SRR22214541_holobiont_control_pH_high_temp"=>"66,","SRR22214542_holobiont_control_pH_high_temp"=>"67,","SRR22214543_holobiont_control_pH_high_temp"=>"68,","SRR22214544_holobiont_control_pH_high_temp"=>"69,","SRR22214545_holobiont_control_pH_high_temp"=>"70,");
$TSTEP_RNA_seq_all=array("SRR14511800_Mesentery"=>"1,","SRR14511801_Mesentery"=>"2,","SRR14511802_Club_tips"=>"3,","SRR14511803_Club_tips"=>"4,","SRR14511804_Mesentery"=>"5,","SRR14511805_Actinopharynx"=>"6,","SRR14511806_Actinopharynx"=>"7,","SRR14511807_Actinopharynx"=>"8,","SRR14511808_Tentacles"=>"9,","SRR14511809_Tentacles"=>"10,","SRR14511810_Tentacles"=>"11,","SRR14511811_Club_tips"=>"12,","SRR14511812_Pedal_disc"=>"13,","SRR14511813_Pedal_disc"=>"14,","SRR14511814_Pedal_disc"=>"15,","SRR14511815_Body_column"=>"16,","SRR14511816_Body_column"=>"17,","SRR14511817_Body_column"=>"18,");
$SSIDE_RNA_seq_all=array("ERR12861049"=>"1,","SRR12454619_live_coral_tissue_skeleton"=>"2,","SRR14295600_Whole_organism"=>"3,","SRR14295601_Whole_organism"=>"4,","SRR14295603_Whole_organism"=>"5,","SRR14295604_Whole_organism"=>"6,","SRR22214400_holobiont_control_pH_Control_temp"=>"7,","SRR22214402_holobiont_low_pH_high_temp"=>"8,","SRR22214403_holobiont_low_pH_high_temp"=>"9,","SRR22214404_holobiont_low_pH_high_temp"=>"10,","SRR22214405_holobiont_low_pH_high_temp"=>"11,","SRR22214406_holobiont_low_pH_high_temp"=>"12,","SRR22214407_holobiont_low_pH_high_temp"=>"13,","SRR22214408_holobiont_low_pH_high_temp"=>"14,","SRR22214409_holobiont_low_pH_high_temp"=>"15,","SRR22214410_holobiont_low_pH_high_temp"=>"16,","SRR22214411_holobiont_control_pH_Control_temp"=>"17,","SRR22214412_holobiont_low_pH_high_temp"=>"18,","SRR22214413_holobiont_low_pH_high_temp"=>"19,","SRR22214414_holobiont_low_pH_high_temp"=>"20,","SRR22214415_holobiont_low_pH_high_temp"=>"21,","SRR22214416_holobiont_low_pH_high_temp"=>"22,","SRR22214417_holobiont_low_pH_high_temp"=>"23,","SRR22214418_holobiont_low_pH_high_temp"=>"24,","SRR22214419_holobiont_low_pH_high_temp"=>"25,","SRR22214420_holobiont_low_pH_high_temp"=>"26,","SRR22214421_holobiont_low_pH_high_temp"=>"27,","SRR22214422_holobiont_control_pH_Control_temp"=>"28,","SRR22214423_holobiont_low_pH_high_temp"=>"29,","SRR22214424_holobiont_low_pH_Control_temp"=>"30,","SRR22214425_holobiont_low_pH_Control_temp"=>"31,","SRR22214426_holobiont_low_pH_Control_temp"=>"32,","SRR22214427_holobiont_low_pH_Control_temp"=>"33,","SRR22214428_holobiont_low_pH_Control_temp"=>"34,","SRR22214429_holobiont_low_pH_Control_temp"=>"35,","SRR22214430_holobiont_low_pH_Control_temp"=>"36,","SRR22214431_holobiont_low_pH_Control_temp"=>"37,","SRR22214432_holobiont_low_pH_Control_temp"=>"38,","SRR22214433_holobiont_control_pH_Control_temp"=>"39,","SRR22214434_holobiont_low_pH_Control_temp"=>"40,","SRR22214435_holobiont_low_pH_Control_temp"=>"41,","SRR22214436_holobiont_low_pH_Control_temp"=>"42,","SRR22214437_holobiont_low_pH_Control_temp"=>"43,","SRR22214438_holobiont_low_pH_Control_temp"=>"44,","SRR22214439_holobiont_low_pH_Control_temp"=>"45,","SRR22214440_holobiont_low_pH_Control_temp"=>"46,","SRR22214441_holobiont_low_pH_Control_temp"=>"47,","SRR22214442_holobiont_low_pH_Control_temp"=>"48,","SRR22214443_holobiont_low_pH_Control_temp"=>"49,","SRR22214444_holobiont_control_pH_Control_temp"=>"50,","SRR22214445_holobiont_low_pH_Control_temp"=>"51,","SRR22214446_holobiont_control_pH_high_temp"=>"52,","SRR22214447_holobiont_control_pH_high_temp"=>"53,","SRR22214448_holobiont_control_pH_high_temp"=>"54,","SRR22214449_holobiont_control_pH_high_temp"=>"55,","SRR22214450_holobiont_control_pH_high_temp"=>"56,","SRR22214451_holobiont_control_pH_high_temp"=>"57,","SRR22214452_holobiont_control_pH_high_temp"=>"58,","SRR22214453_holobiont_control_pH_high_temp"=>"59,","SRR22214454_holobiont_control_pH_high_temp"=>"60,","SRR22214455_holobiont_control_pH_Control_temp"=>"61,","SRR22214456_holobiont_control_pH_high_temp"=>"62,","SRR22214457_holobiont_control_pH_high_temp"=>"63,","SRR22214458_holobiont_control_pH_high_temp"=>"64,","SRR22214459_holobiont_control_pH_high_temp"=>"65,","SRR22214460_holobiont_control_pH_high_temp"=>"66,","SRR22214461_holobiont_control_pH_high_temp"=>"67,","SRR22214462_holobiont_control_pH_high_temp"=>"68,","SRR22214463_holobiont_control_pH_high_temp"=>"69,","SRR22214464_holobiont_control_pH_high_temp"=>"70,","SRR22214465_holobiont_control_pH_high_temp"=>"71,","SRR22214466_holobiont_control_pH_Control_temp"=>"72,","SRR22214467_holobiont_control_pH_high_temp"=>"73,","SRR22214468_holobiont_control_pH_Control_temp"=>"74,","SRR22214469_holobiont_control_pH_Control_temp"=>"75,","SRR22214470_holobiont_control_pH_Control_temp"=>"76,","SRR22214471_holobiont_control_pH_Control_temp"=>"77,","SRR22214481_holobiont_control_pH_Control_temp"=>"78,","SRR22214492_holobiont_control_pH_Control_temp"=>"79,","SRR22214506_holobiont_control_pH_Control_temp"=>"80,","SRR22214524_holobiont_control_pH_Control_temp"=>"81,","SRR22214535_holobiont_control_pH_Control_temp"=>"82,","SRR22214546_holobiont_control_pH_Control_temp"=>"83,","SRR22214547_holobiont_control_pH_Control_temp"=>"84,","SRR22214548_holobiont_control_pH_Control_temp"=>"85,");
$AAURI1_RNA_seq_all=array("SRR7992468_polyp_endoderm_from_body_column_polyp"=>"1,","SRR7992469_polyp_head_region_polyp"=>"2,","SRR7992480_bell_edge_without_ropalia_juvenile"=>"3,","SRR7992481_polyp_ectoderm_from_body_column_polyp"=>"4,","SRR7992482_complete_juvenile_juvenile"=>"5,","SRR7992483_13_ropalia_juvenile"=>"6,","SRR7992484_complete_polyp_polyp"=>"7,","SRR7992485_complete_strobila_strobila"=>"8,","SRR7992486_complete_polyp_polyp"=>"9,","SRR7992487_complete_polyp_polyp"=>"10,","SRR8090255_polyp_20h_induction_with_5M2MI_20C_polyp"=>"11,","SRR8090256_polyp_24h_induction_with_5M2MI_20C_polyp"=>"12,","SRR8090257_ropalia"=>"13,","SRR8090258_bell_edge"=>"14,","SRR8090259_strobila_heads_strobila"=>"15,","SRR8090260_strobila_segments_strobila"=>"16,","SRR8090261_polyp_not_induced_polyp"=>"17,","SRR8090262_polyp_not_induced_polyp"=>"18,","SRR8090263_polyp_24h_induction_with_5M2MI_polyp"=>"19,","SRR8090264_polyp_12h_induction_with_5M2MI_20C_polyp"=>"20,","SRR8090265_strobila_non_segmented_part_strobila"=>"21,","SRR8090266_3_juvenile_7mm_in_diameter"=>"22,");
$AAURI2_RNA_seq_all=array("SRR8040387_complete_polyp_induced_20h"=>"1,","SRR8040388_strobila_head"=>"2,","SRR8040389_complete_polyp_not_induced"=>"3,","SRR8040390_complete_polyp_induced_12h"=>"4,","SRR8040395_strobila_segments"=>"5,","SRR8040396_strobila_non_segmented_part"=>"6,","SRR8040397_polyp_endoderm_from_body_column"=>"7,","SRR8040398_polyp_head_region"=>"8,","SRR8040399_polyp_ectoderm_from_body_column"=>"9,","SRR8040400_jellyfish_mesoglea_cells"=>"10,","SRR8040401_jellyfish_bell_middle_part"=>"11,","SRR8040402_jellyfish_oral_arm"=>"12,","SRR8040403_jellyfish_bell_edge"=>"13,","SRR8040404_jellyfish_gastric_filaments"=>"14,","SRR8040405_jellyfish_mesoglea_cells"=>"15,","SRR8040406_jellyfish_striated_muscle_layer"=>"16,","SRR8040407_jellyfish_canal_system_endoderm"=>"17,","SRR8040408_complete_juvenile_jellyfish_2_5cm_in_diameter"=>"18,","SRR8040409_jellyfish_ectoderm_from_the_upper_side_of_the_bell"=>"19,","SRR8040410_strobila_foot"=>"20,","SRR8040411_complete_juvenile_jellyfish_1cm_in_diameter"=>"21,","SRR8089698_jellyfish_ectoderm_muscle_layer"=>"22,","SRR8089699_jellyfish_ectoderm_upper_bell_surface"=>"23,","SRR8089700_male_gonad"=>"24,","SRR8089701_planula_complete"=>"25,","SRR8089702_tentacles_distal_part"=>"26,","SRR8089703_bell_edge_ectoderm_and_canal"=>"27,","SRR8089704_mesoglea_cells"=>"28,","SRR8089705_endoderm_canal_system"=>"29,");
$AMILL_RNA_seq_all=array("SRR1929581_branch_adult"=>"1,","SRR1929582_branch_adult"=>"2,","SRR1929583_branch_adult"=>"3,","SRR1929584_branch_adult"=>"4,","SRR1929585_branch_adult"=>"5,","SRR1929586_branch_adult"=>"6,","SRR1929587_branch_adult"=>"7,","SRR1929588_branch_adult"=>"8,","SRR1929589_branch_adult"=>"9,","SRR1929590_branch_adult"=>"10,","SRR1929591_branch_adult"=>"11,","SRR1929592_branch_adult"=>"12,","SRR1929593_branch_adult"=>"13,","SRR1929594_branch_adult"=>"14,","SRR1929595_branch_adult"=>"15,","SRR1929596_branch_adult"=>"16,","SRR1929597_branch_adult"=>"17,","SRR1929598_branch_adult"=>"18,","SRR1929599_branch_adult"=>"19,","SRR1929600_branch_adult"=>"20,","SRR1929601_branch_adult"=>"21,","SRR1929602_branch_adult"=>"22,","SRR1929603_branch_adult"=>"23,","SRR1929604_branch_adult"=>"24,","SRR1929605_whole_larvae_adult"=>"25,","SRR1929606_whole_larvae_adult"=>"26,","SRR1929607_whole_larvae_adult"=>"27,","SRR1929608_whole_larvae_adult"=>"28,","SRR1929609_whole_larvae_adult"=>"29,","SRR1929610_whole_larvae_adult"=>"30,","SRR1929611_whole_larvae_adult"=>"31,","SRR1929612_whole_larvae_adult"=>"32,","SRR1929613_whole_larvae_adult"=>"33,","SRR1929614_whole_larvae_adult"=>"34,","SRR1929615_whole_larvae_adult"=>"35,","SRR1929616_whole_larvae_adult"=>"36,","SRR1929617_whole_larvae_adult"=>"37,","SRR1929618_whole_larvae_adult"=>"38,","SRR1929619_whole_larvae_adult"=>"39,","SRR1929620_whole_larvae_adult"=>"40,","SRR1929621_whole_larvae_adult"=>"41,","SRR1929622_whole_larvae_adult"=>"42,","SRR1929623_whole_larvae_adult"=>"43,","SRR1929624_whole_larvae_adult"=>"44,","SRR1929625_whole_larvae_adult"=>"45,","SRR1929626_whole_larvae_adult"=>"46,","SRR1929627_whole_larvae_adult"=>"47,","SRR1929628_whole_larvae_adult"=>"48,","SRR1929629_whole_larvae_adult"=>"49,","SRR1929630_whole_larvae_adult"=>"50,","SRR1929631_whole_larvae_adult"=>"51,","SRR1929632_whole_larvae_adult"=>"52,","SRR1929633_whole_larvae_adult"=>"53,","SRR1929634_whole_larvae_adult"=>"54,");
$AGEMM_RNA_seq_all=array("SRR2169558_branch_tip"=>"1,","SRR3169421_branch_tip"=>"2,","SRR3169422_branch_tip"=>"3,","SRR3169423_branch_tip"=>"4,","SRR3169425_branch_tip"=>"5,","SRR3169426_branch_tip"=>"6,","SRR3169518_branch_tip"=>"7,","SRR3169527_branch_tip"=>"8,","SRR3169528_branch_tip"=>"9,","SRR3169529_branch_tip"=>"10,","SRR3169530_branch_tip"=>"11,","SRR3169531_branch_tip"=>"12,","SRR3169532_branch_tip"=>"13,","SRR3169533_branch_tip"=>"14,","SRR3169534_branch_tip"=>"15,","SRR3169535_branch_tip"=>"16,","SRR3169536_branch_tip"=>"17,","SRR3169537_branch_tip"=>"18,","SRR3169538_branch_tip"=>"19,","SRR3169539_branch_tip"=>"20,","SRR3169540_branch_tip"=>"21,","SRR3169541_branch_tip"=>"22,","SRR3182410_branch_tip"=>"23,","SRR3182448_branch_tip"=>"24,","SRR3182557_branch_tip"=>"25,","SRR3182684_branch_tip"=>"26,","SRR3182685_branch_tip"=>"27,","SRR3182686_branch_tip"=>"28,","SRR3182775_branch_tip"=>"29,","SRR3182776_branch_tip"=>"30,","SRR3182777_branch_tip"=>"31,","SRR3182778_branch_tip"=>"32,","SRR3182779_branch_tip"=>"33,","SRR3182780_branch_tip"=>"34,","SRR3182781_branch_tip"=>"35,","SRR3182784_branch_tip"=>"36,","SRR3182785_branch_tip"=>"37,","SRR3182786_branch_tip"=>"38,","SRR3182787_branch_tip"=>"39,","SRR3182788_branch_tip"=>"40,","SRR3182789_branch_tip"=>"41,","SRR3182790_branch_tip"=>"42,","SRR3182791_branch_tip"=>"43,","SRR3182792_branch_tip"=>"44,","SRR3182793_branch_tip"=>"45,","SRR3182794_branch_tip"=>"46,","SRR3223317_branch_tip"=>"47,","SRR3223319_branch_tip"=>"48,");
$ADIGI_RNA_seq_all=array("SRR23047206_Coral_branch_adult"=>"1,","SRR23047207_Coral_branch_adult"=>"2,","SRR23047208_Coral_branch_adult"=>"3,","SRR23047209_Coral_branch_adult"=>"4,","SRR23047210_Coral_branch_adult"=>"5,","SRR23047211_Coral_branch_adult"=>"6,","SRR23047212_Coral_branch_adult"=>"7,","SRR23047213_Coral_branch_adult"=>"8,","SRR23047214_Coral_branch_adult"=>"9,","SRR23047215_Coral_branch_adult"=>"10,","SRR23047216_Coral_branch_adult"=>"11,","SRR23047217_Coral_branch_adult"=>"12,","SRR23047218_Coral_branch_adult"=>"13,","SRR23047219_Coral_branch_adult"=>"14,","SRR23047220_Coral_branch_adult"=>"15,","SRR23047221_Coral_branch_adult"=>"16,","SRR23047222_Coral_branch_adult"=>"17,","SRR23047223_Coral_branch_adult"=>"18,","SRR23047224_Coral_branch_adult"=>"19,","SRR23047225_Coral_branch_adult"=>"20,","SRR23047226_Coral_branch_adult"=>"21,","SRR23047227_Coral_branch_adult"=>"22,","SRR23047228_Coral_branch_adult"=>"23,","SRR23047229_Coral_branch_adult"=>"24,","SRR23047230_Coral_branch_adult"=>"25,","SRR23047231_Coral_branch_adult"=>"26,","SRR23047232_Coral_branch_adult"=>"27,","SRR23047233_Coral_branch_adult"=>"28,","SRR23047234_Coral_branch_adult"=>"29,","SRR23047235_Coral_branch_adult"=>"30,","SRR23047236_Coral_branch_adult"=>"31,","SRR23047237_Coral_branch_adult"=>"32,","SRR23047238_Coral_branch_adult"=>"33,","SRR23047239_Coral_branch_adult"=>"34,","SRR23047240_Coral_branch_adult"=>"35,","SRR23047241_Coral_branch_adult"=>"36,","SRR23047242_Coral_branch_adult"=>"37,","SRR23047243_Coral_branch_adult"=>"38,","SRR23047244_Coral_branch_adult"=>"39,");
$ACOER_RNA_seq_all=array("SRR25982246_whole_organism_T_A1ES"=>"1,","SRR25982247_whole_organism_T_C3ES"=>"2,","SRR25982248_whole_organism_T_C2ES"=>"3,","SRR25982249_whole_organism_T_C1ES"=>"4,","SRR25982250_whole_organism_T_A3P"=>"5,","SRR25982251_whole_organism_T_A2P"=>"6,","SRR25982252_whole_organism_T_A1P"=>"7,","SRR25982253_whole_organism_T_AAS3"=>"8,","SRR25982254_whole_organism_T_AAS2"=>"9,","SRR25982255_whole_organism_T_AAS1"=>"10,","SRR25982256_whole_organism_T_AES3"=>"11,","SRR25982257_whole_organism_T_AES2"=>"12,","SRR25982258_whole_organism_T_AES1"=>"13,","SRR25982259_whole_organism_T_AE3"=>"14,","SRR25982260_whole_organism_T_AE2"=>"15,","SRR25982261_whole_organism_T_AE1"=>"16,","SRR25982262_whole_organism_T_C3E"=>"17,","SRR25982263_whole_organism_T_C3P"=>"18,","SRR25982264_whole_organism_T_C2E"=>"19,","SRR25982265_whole_organism_T_C1E"=>"20,","SRR25982266_whole_organism_T_A3AS"=>"21,","SRR25982267_whole_organism_T_A2AS"=>"22,","SRR25982268_whole_organism_T_A1AS"=>"23,","SRR25982269_whole_organism_T_C3AS"=>"24,","SRR25982270_whole_organism_T_C2AS"=>"25,","SRR25982271_whole_organism_T_C1AS"=>"26,","SRR25982272_whole_organism_T_A3ES"=>"27,","SRR25982273_whole_organism_T_A2ES"=>"28,","SRR25982274_whole_organism_T_C2P"=>"29,","SRR25982275_whole_organism_T_C1P"=>"30,");
$AHYAC_RNA_seq_all=array("SRR4029951_animal_tissue_AH06_community"=>"1,","SRR4029952_animal_tissue_AH06_community"=>"2,","SRR4029953_animal_tissue_AH06_community"=>"3,","SRR4029954_animal_tissue_AH06_community"=>"4,","SRR4029955_animal_tissue_AH06_community"=>"5,","SRR4029956_animal_tissue_AH06_community"=>"6,","SRR4029957_animal_tissue_AH06_community"=>"7,","SRR4029958_animal_tissue_AH06_community"=>"8,","SRR4029959_animal_tissue_AH06_community"=>"9,","SRR4029960_animal_tissue_AH75_community"=>"10,","SRR4029961_animal_tissue_AH75_community"=>"11,","SRR4029962_animal_tissue_AH75_community"=>"12,","SRR4029963_animal_tissue_AH06_community"=>"13,","SRR4029964_animal_tissue_AH75_community"=>"14,","SRR4029965_animal_tissue_AH75_community"=>"15,","SRR4029966_animal_tissue_AH75_community"=>"16,","SRR4029967_animal_tissue_AH75_community"=>"17,","SRR4029968_animal_tissue_AH75_community"=>"18,","SRR4029969_animal_tissue_AH75_community"=>"19,","SRR4029970_animal_tissue_AH75_community"=>"20,","SRR4029971_animal_tissue_AH75_community"=>"21,","SRR4029972_animal_tissue_AH75_community"=>"22,","SRR4029973_animal_tissue_AH75_community"=>"23,","SRR4029974_animal_tissue_AH06_community"=>"24,","SRR4029975_animal_tissue_AH75_community"=>"25,","SRR4029976_animal_tissue_AH75_community"=>"26,","SRR4029977_animal_tissue_AH75_community"=>"27,","SRR4029978_animal_tissue_AH75_community"=>"28,","SRR4029979_animal_tissue_AH88_community"=>"29,","SRR4029980_animal_tissue_AH88_community"=>"30,","SRR4029981_animal_tissue_AH88_community"=>"31,","SRR4029982_animal_tissue_AH88_community"=>"32,","SRR4029983_animal_tissue_AH88_community"=>"33,","SRR4029984_animal_tissue_AH88_community"=>"34,","SRR4029985_animal_tissue_AH06_community"=>"35,","SRR4029986_animal_tissue_AH88_community"=>"36,","SRR4029987_animal_tissue_AH88_community"=>"37,","SRR4029988_animal_tissue_AH88_community"=>"38,","SRR4029989_animal_tissue_AH88_community"=>"39,","SRR4029990_animal_tissue_AH88_community"=>"40,","SRR4029991_animal_tissue_AH88_community"=>"41,","SRR4029992_animal_tissue_AH88_community"=>"42,","SRR4029993_animal_tissue_AH88_community"=>"43,","SRR4029994_animal_tissue_AH88_community"=>"44,","SRR4029995_animal_tissue_AH88_community"=>"45,","SRR4029996_animal_tissue_AH06_community"=>"46,","SRR4029997_animal_tissue_AH88_community"=>"47,","SRR4029998_animal_tissue_AH06_community"=>"48,","SRR4029999_animal_tissue_AH06_community"=>"49,","SRR4030000_animal_tissue_AH06_community"=>"50,","SRR4030001_animal_tissue_AH06_community"=>"51,");
$ATENE_RNA_seq_all=array("SRR2437124_whole_organism"=>"1,","SRR3193284_whole"=>"2,","SRR3193648_whole_organism"=>"3,","SRR3206038_whole_organism"=>"4,","SRR3207346_whole_organisim"=>"5,","SRR3210696_whole_organism"=>"6,","SRR3216075_whole"=>"7,","SRR4677488_Mesentery"=>"8,","SRR4677492_Mesentery"=>"9,","SRR4677495_Tentacle"=>"10,","SRR4677502_Tentacle"=>"11,","SRR4677507_Acrorhagi"=>"12,","SRR4677512_Acrorhagi"=>"13,","SRR4677515_Acrorhagi"=>"14,","SRR4677518_Mesentery"=>"15,","SRR4677522_Tentacle"=>"16,","SRR4696535_Whole_Organism"=>"17,","SRR6282389_Tentacles"=>"18,");
$APOCU_RNA_seq_all=array("SRR10674706_whole_organism_heat_control"=>"1,","SRR10674707_whole_organism_heat_control"=>"2,","SRR10674708_whole_organism_heat_control"=>"3,","SRR10674709_whole_organism_heat_challenge"=>"4,","SRR10674710_whole_organism_heat_challenge"=>"5,","SRR10674711_whole_organism_cold_challenge"=>"6,","SRR10674712_whole_organism_cold_challenge"=>"7,","SRR10674713_whole_organism_cold_challenge"=>"8,","SRR10674714_whole_organism_cold_control"=>"9,","SRR10674715_whole_organism_cold_control"=>"10,","SRR10674716_whole_organism_heat_challenge"=>"11,","SRR10674717_whole_organism_heat_control"=>"12,","SRR10674718_whole_organism_heat_control"=>"13,","SRR10674719_whole_organism_heat_challenge"=>"14,","SRR10674720_whole_organism_heat_control"=>"15,","SRR10674721_whole_organism_heat_control"=>"16,","SRR10674722_whole_organism_cold_challenge"=>"17,","SRR10674723_whole_organism_cold_challenge"=>"18,","SRR10674724_whole_organism_cold_control"=>"19,","SRR10674725_whole_organism_heat_control"=>"20,","SRR10674726_whole_organism_heat_challenge"=>"21,","SRR10674727_whole_organism_heat_challenge"=>"22,","SRR10674728_whole_organism_heat_challenge"=>"23,","SRR10674729_whole_organism_heat_challenge"=>"24,","SRR10674730_whole_organism_cold_challenge"=>"25,","SRR10674731_whole_organism_heat_control"=>"26,","SRR10674732_whole_organism_cold_challenge"=>"27,","SRR10674733_whole_organism_cold_control"=>"28,","SRR10674734_whole_organism_cold_control"=>"29,","SRR10674735_whole_organism_cold_control"=>"30,","SRR10674736_whole_organism_cold_control"=>"31,","SRR10674737_whole_organism_cold_control"=>"32,","SRR10674738_whole_organism_cold_challenge"=>"33,","SRR10674739_whole_organism_cold_control"=>"34,","SRR10674740_whole_organism_cold_challenge"=>"35,","SRR10674741_whole_organism_heat_control"=>"36,","SRR10674742_whole_organism_heat_challenge"=>"37,","SRR10674743_whole_organism_cold_control"=>"38,","SRR10674744_whole_organism_cold_challenge"=>"39,","SRR10674745_whole_organism_cold_challenge"=>"40,","SRR10674746_whole_organism_cold_control"=>"41,","SRR10674747_whole_organism_cold_control"=>"42,","SRR10674748_whole_organism_cold_control"=>"43,","SRR10674749_whole_organism_cold_challenge"=>"44,","SRR10674750_whole_organism_cold_control"=>"45,","SRR10674751_whole_organism_heat_challenge"=>"46,","SRR10674752_whole_organism_heat_control"=>"47,","SRR10674753_whole_organism_heat_control"=>"48,","SRR10674754_whole_organism_heat_challenge"=>"49,");
$APALM_RNA_seq_all=array("SRR8800026_all_coral_tissue_exposed"=>"1,","SRR8800027_all_coral_tissue_exposed"=>"2,","SRR8800028_all_coral_tissue_exposed"=>"3,","SRR8800029_all_coral_tissue_exposed"=>"4,","SRR8800030_all_coral_tissue_baseline"=>"5,","SRR8800031_all_coral_tissue_baseline"=>"6,","SRR8800032_all_coral_tissue_baseline"=>"7,","SRR8800033_all_coral_tissue_exposed"=>"8,","SRR8800034_all_coral_tissue_exposed"=>"9,","SRR8800035_all_coral_tissue_baseline"=>"10,","SRR8800036_all_coral_tissue_exposed"=>"11,","SRR8800037_all_coral_tissue_baseline"=>"12,","SRR8800038_all_coral_tissue_exposed"=>"13,","SRR8800039_all_coral_tissue_exposed"=>"14,","SRR8800040_all_coral_tissue_exposed"=>"15,","SRR8800041_all_coral_tissue_baseline"=>"16,","SRR8800042_all_coral_tissue_exposed"=>"17,","SRR8800043_all_coral_tissue_baseline"=>"18,","SRR8800044_all_coral_tissue_exposed"=>"19,","SRR8800045_all_coral_tissue_exposed"=>"20,","SRR8800046_all_coral_tissue_baseline"=>"21,","SRR8800047_all_coral_tissue_exposed"=>"22,","SRR8800048_all_coral_tissue_baseline"=>"23,","SRR8800049_all_coral_tissue_baseline"=>"24,","SRR8800050_all_coral_tissue_baseline"=>"25,","SRR8800051_all_coral_tissue_exposed"=>"26,","SRR8800052_all_coral_tissue_baseline"=>"27,","SRR8800053_all_coral_tissue_exposed"=>"28,","SRR8800054_all_coral_tissue_baseline"=>"29,","SRR8800055_all_coral_tissue_baseline"=>"30,","SRR8800056_all_coral_tissue_exposed"=>"31,","SRR8800057_all_coral_tissue_baseline"=>"32,","SRR8800058_all_coral_tissue_exposed"=>"33,","SRR8800059_all_coral_tissue_baseline"=>"34,","SRR8800060_all_coral_tissue_exposed"=>"35,","SRR8800061_all_coral_tissue_exposed"=>"36,","SRR8800062_all_coral_tissue_exposed"=>"37,","SRR8800063_all_coral_tissue_exposed"=>"38,","SRR8800064_all_coral_tissue_baseline"=>"39,","SRR8800065_all_coral_tissue_exposed"=>"40,","SRR8800066_all_coral_tissue_exposed"=>"41,","SRR8800067_all_coral_tissue_baseline"=>"42,","SRR8800068_all_coral_tissue_exposed"=>"43,","SRR8800069_all_coral_tissue_baseline"=>"44,","SRR8800070_all_coral_tissue_baseline"=>"45,","SRR8800071_all_coral_tissue_exposed"=>"46,","SRR8800072_all_coral_tissue_baseline"=>"47,","SRR8800073_all_coral_tissue_exposed"=>"48,","SRR8800074_all_coral_tissue_baseline"=>"49,","SRR8800075_all_coral_tissue_exposed"=>"50,","SRR8800076_all_coral_tissue_baseline"=>"51,","SRR8800077_all_coral_tissue_exposed"=>"52,","SRR8800078_all_coral_tissue_baseline"=>"53,","SRR8800079_all_coral_tissue_exposed"=>"54,","SRR8800080_all_coral_tissue_exposed"=>"55,","SRR8800081_all_coral_tissue_baseline"=>"56,","SRR8800082_all_coral_tissue_baseline"=>"57,","SRR8800083_all_coral_tissue_exposed"=>"58,","SRR8800084_all_coral_tissue_baseline"=>"59,","SRR8800085_all_coral_tissue_baseline"=>"60,","SRR8800086_all_coral_tissue_exposed"=>"61,","SRR8800087_all_coral_tissue_exposed"=>"62,","SRR8800088_all_coral_tissue_exposed"=>"63,","SRR8800089_all_coral_tissue_exposed"=>"64,","SRR8800090_all_coral_tissue_baseline"=>"65,","SRR8800091_all_coral_tissue_exposed"=>"66,","SRR8800092_all_coral_tissue_exposed"=>"67,","SRR8800093_all_coral_tissue_exposed"=>"68,","SRR8800094_all_coral_tissue_exposed"=>"69,","SRR8800095_all_coral_tissue_exposed"=>"70,","SRR8800096_all_coral_tissue_baseline"=>"71,","SRR8800097_all_coral_tissue_exposed"=>"72,","SRR8800098_all_coral_tissue_baseline"=>"73,","SRR8800099_all_coral_tissue_exposed"=>"74,","SRR8800100_all_coral_tissue_exposed"=>"75,","SRR8800101_all_coral_tissue_baseline"=>"76,","SRR8800102_all_coral_tissue_baseline"=>"77,","SRR8800103_all_coral_tissue_baseline"=>"78,","SRR8800104_all_coral_tissue_baseline"=>"79,","SRR8800105_all_coral_tissue_exposed"=>"80,","SRR8800106_all_coral_tissue_baseline"=>"81,","SRR8800107_all_coral_tissue_exposed"=>"82,","SRR8800108_all_coral_tissue_baseline"=>"83,","SRR8800109_all_coral_tissue_baseline"=>"84,");
$AMURI_RNA_seq_all=array("SRR12710849_Polyps_OA_2_day3"=>"1,","SRR12710850_Polyps_OA_2_day3"=>"2,","SRR12710851_Polyps_OA_2_day3"=>"3,","SRR12710859_Polyps_OA_2_day9"=>"4,","SRR12710860_Polyps_OA_2_day9"=>"5,","SRR12710861_Polyps_OA_2_day9"=>"6,","SRR12786899_Polyps_OA_2_day0"=>"7,","SRR12786900_Polyps_OA_2_day0"=>"8,","SRR12786901_Polyps_OA_2_day0"=>"9,","SRR12807382_Polyps_OA_2_day0"=>"10,","SRR12904784_Polyps"=>"11,","SRR12904785_Polyps"=>"12,","SRR12904786_Polyps"=>"13,","SRR12927881_Polyps_E2_day0"=>"14,","SRR12959191_polyps_E2_day21"=>"15,","SRR12959192_Polyps_E2_day21"=>"16,","SRR12959193_Polyps_E2_day21"=>"17,","SRR12959195_Polyps_E2_day0"=>"18,","SRR12959204_Polyps_E2_day15"=>"19,","SRR12959205_Polyps_E2_day15"=>"20,","SRR12959206_Polyps_E2_day0"=>"21,","SRR12959207_Polyps_E2_day15"=>"22,","SRR12959217_Polyps_E2_day0"=>"23,","SRR12959218_Polyps_E2_day9"=>"24,","SRR12959219_Polyps_E2_day9"=>"25,","SRR12959220_Polyps_E2_day9"=>"26,","SRR12959231_Polyps_E2_day3"=>"27,","SRR12959232_Polyps_E2_day3"=>"28,","SRR12959233_Polyps_E2_day3"=>"29,","SRR12995717_Severed_branch_regeneration_day0"=>"30,","SRR12996627_Severed_branch_regeneration_day0"=>"31,","SRR12996628_Severed_branch_regeneration_High_gene_expression"=>"32,","SRR27868158_Polyps_regeneration_day6"=>"33,","SRR27868159_Polyps_regeneration_day6"=>"34,","SRR27868160_Polyps_regeneration_day6"=>"35,","SRR27868165_Polyps_regeneration_day3"=>"36,","SRR27868174_Polyps_regeneration_day39"=>"37,","SRR27868175_Polyps_regeneration_day39"=>"38,","SRR27868176_Polyps_regeneration_day3"=>"39,","SRR27868177_Polyps_regeneration_day39"=>"40,","SRR27868178_Polyps_regeneration_day36"=>"41,","SRR27868179_Polyps_regeneration_day36"=>"42,","SRR27868180_Polyps_regeneration_day36"=>"43,","SRR27868181_Polyps_regeneration_day33"=>"44,","SRR27868182_Polyps_regeneration_day33"=>"45,","SRR27868183_Polyps_regeneration_day33"=>"46,","SRR27868184_Polyps_regeneration_day30"=>"47,","SRR27868185_Polyps_regeneration_day30"=>"48,","SRR27868186_Polyps_regeneration_day30"=>"49,","SRR27868187_Polyps_regeneration_day3"=>"50,","SRR27868188_Polyps_regeneration_day27"=>"51,","SRR27868189_Polyps_regeneration_day27"=>"52,","SRR27868190_Polyps_regeneration_day27"=>"53,","SRR27868191_Polyps_regeneration_day24"=>"54,","SRR27868192_Polyps_regeneration_day24"=>"55,","SRR27868193_Polyps_regeneration_day24"=>"56,","SRR27868194_Polyps_regeneration_day21"=>"57,","SRR27868195_Polyps_regeneration_day21"=>"58,","SRR27868196_Polyps_regeneration_day21"=>"59,","SRR27868197_Polyps_regeneration_day18"=>"60,","SRR27868198_Polyps_regeneration_day0"=>"61,","SRR27868199_Polyps_regeneration_day18"=>"62,","SRR27868200_Polyps_regeneration_day18"=>"63,","SRR27868201_Polyps_regeneration_day15"=>"64,","SRR27868202_Polyps_regeneration_day15"=>"65,","SRR27868203_Polyps_regeneration_day15"=>"66,","SRR27868204_Polyps_regeneration_day12"=>"67,","SRR27868205_Polyps_regeneration_day12"=>"68,","SRR27868206_Polyps_regeneration_day12"=>"69,","SRR27868207_Polyps_regeneration_day9"=>"70,","SRR27868208_Polyps_regeneration_day9"=>"71,","SRR27868209_Polyps_regeneration_day0"=>"72,","SRR27868210_Polyps_regeneration_day0"=>"73,","SRR27940222_Polyps"=>"74,","SRR27940224_Polyps"=>"75,","SRR27940225_Polyps"=>"76,","SRR27940226_Polyps"=>"77,","SRR27940227_Polyps"=>"78,","SRR27940228_Polyps"=>"79,","SRR27940229_Polyps"=>"80,","SRR9613488_Polyps"=>"81,","SRR9613516_Polyps"=>"82,");
$CHEMI_RNA_seq_all=array("ERR2816230_Early_gastrula"=>"1,","ERR2816231_Early_gastrula"=>"2,","ERR2816232_Planula_24hpf"=>"3,","ERR2816233_Planula_24hpf"=>"4,","ERR2816234_Planula_48hpf"=>"5,","ERR2816235_Planula_48hpf"=>"6,","ERR2816236_Planula_72hpf"=>"7,","ERR2816237_Planula_72hpf"=>"8,","ERR2816238_Primary_polyp"=>"9,","ERR2816239_Primary_polyp"=>"10,","ERR2816240_Gastrozooid_female"=>"11,","ERR2816241_Gastrozooid_female"=>"12,","ERR2816242_Gonozooid_female"=>"13,","ERR2816243_Gonozooid_female"=>"14,","ERR2816244_Stolon_female"=>"15,","ERR2816245_Stolon_female"=>"16,","ERR2816246_Baby_medusa_female"=>"17,","ERR2816247_Baby_medusa_female"=>"18,","ERR2816248_Mature_medusa_female"=>"19,","ERR2816249_Mature_medusa_female"=>"20,","ERR2816250_Mature_medusa_male"=>"21,","ERR2816251_Mature_medusa_male"=>"22,","ERR2862244_Mixed_MF"=>"23,","ERR2862245_Mature_medusa_MF"=>"24,","ERR3299471_medusa_Experiment_Condition_B1_female_strain_Z4B"=>"25,","ERR3299472_medusa_Experiment_Condition_B1_female_strain_Z4B"=>"26,","ERR3299473_medusa_Experiment_Condition_B2_female_strain_Z4B"=>"27,","ERR3299474_medusa_Experiment_Condition_B2_female_strain_Z4B"=>"28,","ERR3299475_medusa_Experiment_Condition_A1_female_strain_Z4B"=>"29,","ERR3299476_medusa_Experiment_Condition_A1_female_strain_Z4B"=>"30,","ERR3299477_medusa_Experiment_Condition_A1_female_strain_Z4B"=>"31,","ERR3299478_medusa_Experiment_Condition_A1_female_strain_Z4B"=>"32,","ERR3299479_medusa_Experiment_Condition_A2_female_strain_Z4B"=>"33,","ERR3299480_medusa_Experiment_Condition_A2_female_strain_Z4B"=>"34,","ERR3299481_medusa_Experiment_Condition_A2_female_strain_Z4B"=>"35,","ERR3299482_medusa_Experiment_Condition_A2_female_strain_Z4B"=>"36,","ERR3299483_medusa_Experiment_Condition_A3_female_strain_Z4B"=>"37,","ERR3299484_medusa_Experiment_Condition_A3_female_strain_Z4B"=>"38,","ERR3299485_medusa_Experiment_Condition_A3_female_strain_Z4B"=>"39,","ERR3299486_medusa_Experiment_Condition_A3_female_strain_Z4B"=>"40,");
$ATENU_RNA_seq_all=array("DRR550233_whole_tissue_of_branch_fragment_adult_BC_2"=>"1,","DRR550234_whole_tissue_of_branch_fragment_adult_BC_3"=>"2,","DRR550235_whole_tissue_of_branch_fragment_adult_BC_4"=>"3,","DRR550236_whole_tissue_of_branch_fragment_adult_BC_5"=>"4,","DRR550237_whole_tissue_of_branch_fragment_adult_BC_6"=>"5,","DRR550238_whole_tissue_of_branch_fragment_adult_SC_1"=>"6,","DRR550239_whole_tissue_of_branch_fragment_adult_SC_2"=>"7,","DRR550241_whole_tissue_of_branch_fragment_adult_SC_4"=>"8,","DRR550242_whole_tissue_of_branch_fragment_adult_SC_5"=>"9,","DRR550243_whole_tissue_of_branch_fragment_adult_SC_6"=>"10,","DRR550244_whole_tissue_of_branch_fragment_adult_BP_3_0_38_1"=>"11,","DRR550245_whole_tissue_of_branch_fragment_adult_BP_3_0_38_2"=>"12,","DRR550246_whole_tissue_of_branch_fragment_adult_BP_3_0_38_3"=>"13,","DRR550247_whole_tissue_of_branch_fragment_adult_BP_3_0_38_4"=>"14,","DRR550248_whole_tissue_of_branch_fragment_adult_BP_3_0_38_5"=>"15,","DRR550249_whole_tissue_of_branch_fragment_adult_BP_3_0_38_6"=>"16,","DRR550250_whole_tissue_of_branch_fragment_adult_BP_3_0_77_1"=>"17,","DRR550251_whole_tissue_of_branch_fragment_adult_BP_3_0_77_2"=>"18,","DRR550252_whole_tissue_of_branch_fragment_adult_BP_3_0_77_3"=>"19,","DRR550253_whole_tissue_of_branch_fragment_adult_BP_3_0_77_4"=>"20,","DRR550254_whole_tissue_of_branch_fragment_adult_BP_3_0_77_5"=>"21,","DRR550255_whole_tissue_of_branch_fragment_adult_BP_3_0_77_6"=>"22,","DRR550256_whole_tissue_of_branch_fragment_adult_BP_3_1_5_1"=>"23,","DRR550257_whole_tissue_of_branch_fragment_adult_BP_3_1_5_2"=>"24,","DRR550258_whole_tissue_of_branch_fragment_adult_BP_3_1_5_3"=>"25,","DRR550259_whole_tissue_of_branch_fragment_adult_BP_3_1_5_4"=>"26,","DRR550260_whole_tissue_of_branch_fragment_adult_BP_3_1_5_5"=>"27,","DRR550261_whole_tissue_of_branch_fragment_adult_BP_3_1_5_6"=>"28,","DRR550262_whole_tissue_of_branch_fragment_adult_BP_3_2_7_1"=>"29,","DRR550263_whole_tissue_of_branch_fragment_adult_BP_3_2_7_2"=>"30,","DRR550264_whole_tissue_of_branch_fragment_adult_BP_3_2_7_3"=>"31,","DRR550265_whole_tissue_of_branch_fragment_adult_BP_3_2_7_4"=>"32,","DRR550266_whole_tissue_of_branch_fragment_adult_BP_3_2_7_5"=>"33,","DRR550267_whole_tissue_of_branch_fragment_adult_BP_3_2_7_6"=>"34,","DRR550268_whole_tissue_of_branch_fragment_adult_Heat_1"=>"35,","DRR550269_whole_tissue_of_branch_fragment_adult_Heat_2"=>"36,","DRR550270_whole_tissue_of_branch_fragment_adult_Heat_3"=>"37,","DRR550271_whole_tissue_of_branch_fragment_adult_Heat_4"=>"38,","DRR550272_whole_tissue_of_branch_fragment_adult_Heat_5"=>"39,","DRR550273_whole_tissue_of_branch_fragment_adult_Heat_6"=>"40,");
$ASELA_RNA_seq_all=array("SRR14308004_coral_larvae"=>"1,","SRR14308005_coral_larvae"=>"2,","SRR14308006_coral_larvae"=>"3,","SRR14308007_coral_larvae"=>"4,","SRR14308008_coral_larvae"=>"5,","SRR14308009_coral_larvae"=>"6,","SRR14308010_coral_larvae"=>"7,","SRR14308011_coral_larvae"=>"8,","SRR14308012_coral_larvae"=>"9,","SRR14308013_coral_larvae"=>"10,","SRR14308014_coral_larvae"=>"11,","SRR14308015_coral_larvae"=>"12,","SRR14308016_coral_larvae"=>"13,","SRR14308017_coral_larvae"=>"14,","SRR14308018_coral_larvae"=>"15,","SRR14308019_coral_larvae"=>"16,","SRR14308020_coral_larvae"=>"17,","SRR14308021_coral_larvae"=>"18,","SRR14308022_coral_larvae"=>"19,","SRR14308023_coral_larvae"=>"20,","SRR14308024_coral_larvae"=>"21,","SRR14308025_coral_larvae"=>"22,","SRR14308026_coral_larvae"=>"23,","SRR14308027_coral_larvae"=>"24,");
$EVERR_RNA_seq_all=array("ERR11252240_whole_tissue_polyp"=>"1,","ERR11252242_whole_tissue_polyp"=>"2,","ERR11252243_whole_tissue_polyp"=>"3,","ERR11252244_whole_tissue_polyp"=>"4,","ERR11252245_whole_tissue_polyp"=>"5,","ERR11252246_whole_tissue_polyp"=>"6,","ERR11252248_whole_tissue_polyp"=>"7,","ERR11252249_whole_tissue_polyp"=>"8,","ERR11252250_whole_tissue_polyp"=>"9,","ERR11252251_whole_tissue_polyp"=>"10,","ERR11252252_whole_tissue_polyp"=>"11,","ERR11252253_whole_tissue_polyp"=>"12,","ERR11252254_whole_tissue_polyp"=>"13,","ERR11252255_whole_tissue_polyp"=>"14,","ERR11252256_whole_tissue_polyp"=>"15,","ERR11252257_whole_tissue_polyp"=>"16,","ERR11252258_whole_tissue_polyp"=>"17,","ERR11252259_whole_tissue_polyp"=>"18,","ERR11252260_whole_tissue_polyp"=>"19,","ERR11252261_whole_tissue_polyp"=>"20,","ERR11252262_whole_tissue_polyp"=>"21,");
$GFASC_RNA_seq_all=array("SRR27118466_coral_holosome_30oc_treatment"=>"1,","SRR27118468_coral_holosome_30oc_treatment"=>"2,","SRR27118469_coral_holosome_Prometryn_herbicidess_treatment"=>"3,","SRR27118472_coral_holosome_Prometryn_herbicidess_and_30oc_treatment"=>"4,","SRR27118475_coral_holosome_Prometryn_herbicidess_and_30oc_treatment"=>"5,","SRR27118476_coral_holosome_30oc_treatment"=>"6,","SRR27118477_coral_holosome_30oc_treatment"=>"7,","SRR27118478_coral_holosome_30oc_treatment"=>"8,","SRR27118479_coral_holosome_30oc_treatment"=>"9,","SRR27118480_coral_holosome_Control"=>"10,","SRR27118481_coral_holosome_Prometryn_herbicidess_treatment"=>"11,","SRR27118482_coral_holosome_Prometryn_herbicidess_treatment"=>"12,","SRR27118483_coral_holosome_Prometryn_herbicidess_treatment"=>"13,","SRR27118484_coral_holosome_Prometryn_herbicidess_treatment"=>"14,","SRR27118485_coral_holosome_Control"=>"15,","SRR27118486_coral_holosome_Control"=>"16,","SRR27118487_coral_holosome_Control"=>"17,","SRR27118488_coral_holosome_Control"=>"18,","SRR27118489_coral_holosome_Prometryn_herbicidess_and_30oc_treatment"=>"19,","SRR27118490_coral_holosome_Prometryn_herbicidess_and_30oc_treatment"=>"20,","SRR27118491_coral_holosome_Control"=>"21,","SRR27118492_coral_holosome_Control"=>"22,");
$FANCO_RNA_seq_all=array("DRR235372_ovaries_oocytes_with_cytoplasmic_polarization"=>"1,","DRR235374_ovaries_oocytes_with_cytoplasmic_polarization"=>"2,","DRR235375_ovaries_oocytes_126_200_um_in_diameter_female"=>"3,","DRR235376_ovaries_oocytes_126_200_um_in_diameter_female"=>"4,","DRR235377_ovaries_oocytes_126_200_um_in_diameter_female"=>"5,","DRR235378_ovaries_oocytes_201_275_um_in_diameter_female"=>"6,","DRR235379_ovaries_oocytes_201_275_um_in_diameter_female"=>"7,","DRR235380_ovaries_oocytes_201_275_um_in_diameter_female"=>"8,","DRR235381_276_um_in_diameter_and_GVBD_female"=>"9,","DRR235382_276_um_in_diameter_and_GVBD_female"=>"10,","DRR235383_276_um_in_diameter_and_GVBD_female"=>"11,","DRR235384_testes_spermatogonia_male"=>"12,","DRR235385_testes_spermatogonia_male"=>"13,","DRR235386_testes_spermatogonia_male"=>"14,","DRR235387_testes_spermatogonia_and_primary_spermatocytes_male"=>"15,","DRR235389_testes_spermatogonia_and_primary_spermatocytes_male"=>"16,","DRR397929_Tentacles"=>"17,","DRR397944_Mouth_and_pharynx"=>"18,");
$EDIAP_RNA_seq_all=array("SRR6202203_whole_animal_aposymbiotic_A3_Run2_L5"=>"1,","SRR6202204_whole_animal_aposymbiotic_A3_Run2_L6"=>"2,","SRR6202205_whole_animal_aposymbiotic_A1_Run2_L5"=>"3,","SRR6202206_whole_animal_aposymbiotic_A1_Run2_L6"=>"4,","SRR6202207_whole_animal_aposymbiotic_A1_Run2_L7"=>"5,","SRR6202208_whole_animal_aposymbiotic_A1_Run2_L8"=>"6,","SRR6202209_whole_animal_aposymbiotic_A2_Run2_L5"=>"7,","SRR6202210_whole_animal_aposymbiotic_A2_Run2_L6"=>"8,","SRR6202211_whole_animal_aposymbiotic_A2_Run2_L7"=>"9,","SRR6202212_whole_animal_aposymbiotic_A2_Run2_L8"=>"10,","SRR6202233_whole_animal_aposymbiotic_A5_Run2_L6"=>"11,","SRR6202234_whole_animal_aposymbiotic_A5_Run2_L5"=>"12,","SRR6202235_whole_animal_aposymbiotic_A4_Run2_L8"=>"13,","SRR6202236_whole_animal_aposymbiotic_A4_Run2_L7"=>"14,","SRR6202237_whole_animal_aposymbiotic_A4_Run2_L6"=>"15,","SRR6202238_whole_animal_aposymbiotic_A4_Run2_L5"=>"16,","SRR6202239_whole_animal_aposymbiotic_A3_Run2_L8"=>"17,","SRR6202240_whole_animal_aposymbiotic_A3_Run2_L7"=>"18,","SRR6202241_whole_animal_aposymbiotic_A5_Run2_L8"=>"19,","SRR6202242_whole_animal_aposymbiotic_A5_Run2_L7"=>"20,","SRR6202254_whole_animal_aposymbiotic_A4_Run1_L5"=>"21,","SRR6202255_whole_animal_aposymbiotic_A4_Run1_L6"=>"22,","SRR6202256_whole_animal_aposymbiotic_A3_Run1_L5"=>"23,","SRR6202257_whole_animal_aposymbiotic_A3_Run1_L6"=>"24,","SRR6202258_whole_animal_aposymbiotic_A6_Run1_L5"=>"25,","SRR6202259_whole_animal_aposymbiotic_A6_Run1_L6"=>"26,","SRR6202260_whole_animal_aposymbiotic_A5_Run1_L5"=>"27,","SRR6202261_whole_animal_aposymbiotic_A5_Run1_L6"=>"28,","SRR6202262_whole_animal_symbiotic_S1_Run1_L5"=>"29,","SRR6202263_whole_animal_symbiotic_S1_Run1_L6"=>"30,","SRR6202276_whole_animal_symbiotic_S2_Run1_L6"=>"31,","SRR6202277_whole_animal_symbiotic_S2_Run1_L5"=>"32,","SRR6202278_whole_animal_symbiotic_S3_Run1_L6"=>"33,","SRR6202279_whole_animal_symbiotic_S3_Run1_L5"=>"34,","SRR6202280_whole_animal_symbiotic_S4_Run1_L6"=>"35,","SRR6202281_whole_animal_symbiotic_S4_Run1_L5"=>"36,","SRR6202282_whole_animal_symbiotic_S5_Run1_L6"=>"37,","SRR6202283_whole_animal_symbiotic_S5_Run1_L5"=>"38,","SRR6202284_whole_animal_symbiotic_S6_Run1_L6"=>"39,","SRR6202285_whole_animal_symbiotic_S6_Run1_L5"=>"40,","SRR6202303_whole_animal_symbiotic_S6_Run2_L5"=>"41,","SRR6202304_whole_animal_symbiotic_S6_Run2_L6"=>"42,","SRR6202305_whole_animal_symbiotic_S6_Run2_L7"=>"43,","SRR6202306_whole_animal_symbiotic_S6_Run2_L8"=>"44,","SRR6202307_whole_animal_symbiotic_S5_Run2_L5"=>"45,","SRR6202308_whole_animal_symbiotic_S5_Run2_L6"=>"46,","SRR6202309_whole_animal_symbiotic_S5_Run2_L7"=>"47,","SRR6202310_whole_animal_symbiotic_S5_Run2_L8"=>"48,","SRR6202337_whole_animal_symbiotic_S2_Run2_L5"=>"49,","SRR6202338_whole_animal_symbiotic_S2_Run2_L6"=>"50,","SRR6202339_whole_animal_symbiotic_S1_Run2_L7"=>"51,","SRR6202340_whole_animal_symbiotic_S1_Run2_L8"=>"52,","SRR6202341_whole_animal_symbiotic_S1_Run2_L5"=>"53,","SRR6202342_whole_animal_symbiotic_S1_Run2_L6"=>"54,","SRR6202343_whole_animal_aposymbiotic_A6_Run2_L7"=>"55,","SRR6202344_whole_animal_aposymbiotic_A6_Run2_L8"=>"56,","SRR6202345_whole_animal_aposymbiotic_A6_Run2_L5"=>"57,","SRR6202346_whole_animal_aposymbiotic_A6_Run2_L6"=>"58,","SRR6202347_whole_animal_symbiotic_S4_Run2_L8"=>"59,","SRR6202348_whole_animal_symbiotic_S4_Run2_L7"=>"60,","SRR6202349_whole_animal_symbiotic_S3_Run2_L8"=>"61,","SRR6202350_whole_animal_symbiotic_S3_Run2_L7"=>"62,","SRR6202351_whole_animal_symbiotic_S4_Run2_L6"=>"63,","SRR6202352_whole_animal_symbiotic_S4_Run2_L5"=>"64,","SRR6202353_whole_animal_symbiotic_S2_Run2_L8"=>"65,","SRR6202354_whole_animal_symbiotic_S2_Run2_L7"=>"66,","SRR6202355_whole_animal_symbiotic_S3_Run2_L6"=>"67,","SRR6202356_whole_animal_symbiotic_S3_Run2_L5"=>"68,","SRR6202357_whole_animal_aposymbiotic_A2_Run1_L6"=>"69,","SRR6202358_whole_animal_aposymbiotic_A2_Run1_L5"=>"70,","SRR6202363_whole_animal_aposymbiotic_A1_Run1_L6"=>"71,","SRR6202364_whole_animal_aposymbiotic_A1_Run1_L5"=>"72,");
$HSYMB_RNA_seq_all=array("SRR24482133_Whole_embryo_6_hpf"=>"1,","SRR24482134_Whole_embryo_5_hpf"=>"2,","SRR24482135_Whole_embryo_4_hpf"=>"3,","SRR24482136_Whole_embryo_4_hpf"=>"4,","SRR24482137_Whole_embryo_3_hpf"=>"5,","SRR24482138_Whole_embryo_3_hpf"=>"6,","SRR24482139_Whole_embryo_2_hpf"=>"7,","SRR24482140_Whole_embryo_2_hpf"=>"8,","SRR24482141_Whole_embryo_1_hpf"=>"9,","SRR24482142_Whole_embryo_30_mpf"=>"10,","SRR24482143_Whole_embryo_Unfertilized_egg"=>"11,","SRR24482144_Whole_embryo_Unfertilized_egg"=>"12,","SRR24482145_Whole_embryo_Unfertilized_egg"=>"13,","SRR24482146_Whole_embryo_7_hpf_Triptolide_20_uM"=>"14,","SRR24482147_Whole_embryo_7_hpf_Triptolide_20_uM"=>"15,","SRR24482148_Whole_embryo_7_hpf_DMSO_0_5percent"=>"16,","SRR24482149_Whole_embryo_7_hpf_Triptolide_20_uM"=>"17,","SRR24482150_Whole_embryo_7_hpf_Triptolide_20_uM"=>"18,","SRR24482151_Whole_embryo_7_hpf_DMSO_0_5percent"=>"19,","SRR24482152_Whole_embryo_7_hpf_DMSO_0_5percent"=>"20,","SRR24482153_Whole_embryo_7_hpf"=>"21,","SRR24482154_Whole_embryo_7_hpf_DMSO_0_5percent"=>"22,","SRR24482155_Whole_embryo_72_hpf"=>"23,","SRR24482156_Whole_embryo_72_hpf"=>"24,","SRR24482157_Whole_embryo_48_hpf"=>"25,","SRR24482158_Whole_embryo_48_hpf"=>"26,","SRR24482159_Whole_embryo_24_hpf"=>"27,","SRR24482160_Whole_embryo_24_hpf"=>"28,","SRR24482161_Whole_embryo_7_hpf"=>"29,","SRR24482162_Whole_embryo_7_hpf"=>"30,","SRR24482163_Whole_embryo_6_hpf"=>"31,","SRR24482165_Whole_embryo_4_hpf"=>"32,","SRR24482166_Whole_embryo_4_hpf"=>"33,","SRR24482167_Whole_embryo_3_hpf"=>"34,","SRR24482168_Whole_embryo_3_hpf"=>"35,","SRR24482169_Whole_embryo_2_hpf"=>"36,","SRR24482170_Whole_embryo_2_hpf"=>"37,","SRR24482171_Whole_embryo_1_hpf"=>"38,","SRR24482172_Whole_embryo_30_mpf"=>"39,","SRR24482173_Whole_embryo_Unfertilized_egg"=>"40,","SRR24482174_Whole_embryo_Unfertilized_egg"=>"41,","SRR24482175_Whole_embryo_Unfertilized_egg"=>"42,","SRR24482176_Whole_embryo_1_hpf"=>"43,","SRR24482177_Whole_embryo_1_hpf"=>"44,");
$HCOER_RNA_seq_all=array("SRR12578063_polyp_and_skeleton_28C_24hr"=>"1,","SRR12578064_polyp_and_skeleton_31C_24hr"=>"2,","SRR12578065_polyp_and_skeleton_31C_3week"=>"3,","SRR12578066_polyp_and_skeleton_31C_3week"=>"4,","SRR12578067_polyp_and_skeleton_28C_3week"=>"5,","SRR12578068_polyp_and_skeleton_31C_3week"=>"6,","SRR12587798_polyp_and_skeleton_26C_3week"=>"7,","SRR12587799_polyp_and_skeleton_31C_3week"=>"8,","SRR12587800_polyp_and_skeleton_31C_3week"=>"9,","SRR12587801_polyp_and_skeleton_26C_3week"=>"10,","SRR12587802_polyp_and_skeleton_28C_3week"=>"11,","SRR12587803_polyp_and_skeleton_28C_3week"=>"12,","SRR12587804_polyp_and_skeleton_31C_3week"=>"13,","SRR12587805_polyp_and_skeleton_26C_3week"=>"14,","SRR12587806_polyp_and_skeleton_26C_3week"=>"15,","SRR12587807_polyp_and_skeleton_28C_3week"=>"16,","SRR12587808_polyp_and_skeleton_28C_3week"=>"17,","SRR5949848_polyp_and_skeleton_31C_24hr"=>"18,","SRR5949849_polyp_and_skeleton_28C_3week"=>"19,","SRR5949850_polyp_and_skeleton_28C_24hr"=>"20,","ERR6178387_Whole_coral_Molecular_and_mineral_responses"=>"21,","ERR6178388_Whole_coral_Molecular_and_mineral_responses"=>"22,","ERR6178389_Whole_coral_Molecular_and_mineral_responses"=>"23,","ERR6178770_Whole_coral_Molecular_and_mineral_responses"=>"24,","ERR6178771_Whole_coral_Molecular_and_mineral_responses"=>"25,","ERR6178772_Whole_coral_Molecular_and_mineral_responses"=>"26,","ERR6178773_Whole_coral_Molecular_and_mineral_responses"=>"27,","ERR6178774_Whole_coral_Molecular_and_mineral_responses"=>"28,","ERR6178775_Whole_coral_Molecular_and_mineral_responses"=>"29,","ERR6178776_Whole_coral_Molecular_and_mineral_responses"=>"30,","ERR6178777_Whole_coral_Molecular_and_mineral_responses"=>"31,","ERR6178778_Whole_coral_Molecular_and_mineral_responses"=>"32,","ERR6178779_Whole_coral_Molecular_and_mineral_responses"=>"33,","ERR6178780_Whole_coral_Molecular_and_mineral_responses"=>"34,","ERR6178781_Whole_coral_Molecular_and_mineral_responses"=>"35,","ERR6178782_Whole_coral_Molecular_and_mineral_responses"=>"36,","ERR6178783_Whole_coral_Molecular_and_mineral_responses"=>"37,");

/* ---- 横轴的列与标签 ------------------------------------------------------
   $<ABBR>_RNA_seq_all 的键就是横轴标签，值是它包含的 TPM 列号（"1,2,3,"）。
   所以键的数量就是横轴列数、也就是样本数 —— 原来这里另有一份 31 条的
   if($tab=="X_TPM"){$total_samples=N;} 手工表，和数组键是同一份数据的第二个副本，
   两边一旦对不上，统计卡片就会报一个跟图上列数不同的数字。这里直接从键推出来。
   （31 个物种的值都逐一对过 information_schema，与旧表完全一致。） */
$hmLabels    = array();   // 完整标签，进 tooltip
$hmShort     = array();   // 短标签（run 号），进横轴
$hmShortOk   = true;      // 短标签是否互不重复
$total_samples = 0;

if ($dataset !== '' && isset(${$dataset}) && is_array(${$dataset})) {
    foreach (array_keys(${$dataset}) as $hmK) {
        $hmK = (string)$hmK;
        $hmLabels[] = $hmK;
        /* 去掉第一个下划线之后的条件描述，只留 run 号：SRR11359494。
           1385 个标签全部以 run 号开头，且在各自物种内唯一，所以短标签不会重名。
           完整标签在 tooltip 里给，条件信息不会丢。 */
        $hmS = (strpos($hmK, '_') !== false) ? substr($hmK, 0, strpos($hmK, '_')) : $hmK;
        $hmShort[] = $hmS;
    }
    $total_samples = count($hmLabels);
    $hmShortOk = (count(array_unique($hmShort)) === count($hmShort));
}

/* 图配置里的三个数组走 json_encode，而不是拼字符串。基因名和样本名都来自数据库，
   拼引号等于把数据当代码写；JSON_HEX_* 再把 < > & ' " 转成 \uXXXX，免得某个标签里
   出现 </script> 就把整段脚本截断。 */
$hmJsonFlags  = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$hmShortJson  = json_encode($hmShort, $hmJsonFlags);
$hmLabelsJson = json_encode($hmLabels, $hmJsonFlags);
/* $hmGenesJson（纵轴标签）要等数据循环跑完，见下面 —— std=0 的基因要在标签上标出来。 */

/* TPM 表的列布局：Gene + n 个样本 + avg + std（31 张表逐一核对过）。
   旧代码把这组下标写成 31 条 if($tab==...) 分支（$result[111]/$result[112] …），
   现在按样本数算出来。布局对不上就不算 z-score，避免拿错列当均值/标准差。 */
$iAvg = $total_samples + 1;
$iStd = $total_samples + 2;
$hmLayoutOk = false;
if ($tab !== '') {
    $cq = mysqli_query($conn, "SHOW COLUMNS FROM `" . str_replace('`', '', $tab) . "`");
    $hmCols = array();
    if ($cq) { while ($cr = mysqli_fetch_row($cq)) { $hmCols[] = $cr[0]; } }
    $hmLayoutOk = (count($hmCols) === $total_samples + 3
                   && strtolower($hmCols[0]) === 'gene');
    if (!$hmLayoutOk) {
        $hmErr = 'The layout of <code>' . htmlspecialchars($tab) . '</code> is not the expected '
               . '<code>Gene + N samples + avg + std</code>, so the z-scores cannot be computed safely.';
    }
}

/* 图高：按行给固定高度，而不是 count*30+200 再兜底 900。
   原来 1 个基因也要 900px 高，一行占满整屏，下面全是空白；
   基因多了又只给 30px/行，行标签挤在一起。 */
$hmRowH = 26;
$height = 120 + count($genelist) * $hmRowH;
if ($height < 260)  { $height = 260; }
if ($height > 2200) { $height = 2200; }

$total_genes = count($genelist);
?>

<?php
/* ---- 数据 ---------------------------------------------------------------
   每个格子 = 一个横轴标签 × 一个基因：
     格值 = 该标签所含各列 TPM 的均值；z = (格值 − 该基因全样本均值) / 全样本标准差。
   三处改动：
     1) 原来每个基因查一次 SELECT，基因名直接拼进 WHERE；现在先转义。
     2) 原来 std==0（该基因在所有样本里毫无变化，z 没有定义）时写 -1，图上就画成
        一条淡蓝的「下调」——一个凭空造出来的数值。现在这一行不画，改成在纵轴标签上
        写明成因。（31 张 TPM 表都查过，atd 从没出现过 0，所以这一支现实中不会触发；
        留着是因为 (float) 转换也可能把空值变成 0.0。）
     3) 循环按 $genelist 顺序走，$n 是基因序号、$i 是横轴序号，所以某个基因查不到时
        只在它那一行留空，其余行的位置不会错位。查不到的标 (not found) —— 这是实际
        会发生的那种空行，多半是基因列表来自别的物种。 */
$csv      = "Tissue,Gene,Zscore\n";
$n        = 0;
$hmFound  = 0;
$hmBlank  = 0;
$hmZmax   = 0.0;
$hmMissing = array();
$hmFlat   = array();   // std==0 的基因，纵轴标签上要标出来

if ($tab !== '' && $hmLayoutOk && $dataset !== '' && isset(${$dataset}) && is_array(${$dataset})) {
    foreach ($genelist as $gene) {
        $geneEsc = mysqli_real_escape_string($conn, $gene);
        $query = mysqli_query($conn, "SELECT * FROM `" . $tab . "` WHERE Gene = '$geneEsc'");
        $got = false;
        if ($query) {
            while ($result = mysqli_fetch_row($query)) {
                $got = true;
                $i = 0;
                foreach (${$dataset} as $hmIdxStr) {
                    /* 值是 "1," 或 "1,2,3," 这样的列号表，末尾那个逗号是格式的一部分，
                       所以按非空 token 的个数算，而不是 count(explode(...))-1 —— 少了
                       逗号的话后者会少算一列。 */
                    $hmColsOf = array();
                    foreach (explode(",", $hmIdxStr) as $hmTok) {
                        if ($hmTok !== '') { $hmColsOf[] = (int)$hmTok; }
                    }
                    if (!$hmColsOf) { $i++; continue; }

                    $sum = 0.0;
                    foreach ($hmColsOf as $hmCol) {
                        $sum += isset($result[$hmCol]) ? (float)$result[$hmCol] : 0.0;
                    }
                    $j = $sum / count($hmColsOf);

                    $m = isset($result[$iStd]) ? (float)$result[$iStd] : 0.0;   // 全样本标准差
                    $p = isset($result[$iAvg]) ? (float)$result[$iAvg] : 0.0;   // 全样本均值

                    if ($m == 0.0) {
                        $hmBlank++;            /* z 没有定义：不画这个格子 */
                        $hmFlat[$gene] = true;
                    } else {
                        $z = ($j - $p) / $m;
                        if (abs($z) > $hmZmax) { $hmZmax = abs($z); }
                        $csv .= $i . ',' . $n . ',' . sprintf('%.2f', $z) . "\n";
                    }
                    $i++;
                }
            }
        }
        if ($got) { $hmFound++; } else { $hmMissing[] = $gene; }
        $n++;
    }
}

/* 纵轴标签。空行有两种成因，都在标签上直接写明，不让读者猜：
     std==0  → 整行没有方差，z 无定义
     查不到   → 这个基因不在本物种的 TPM 表里（通常是基因列表来自别的物种） */
$hmMissingSet = array_flip($hmMissing);
$hmAxisGenes = array();
foreach ($genelist as $gene) {
    if (isset($hmFlat[$gene])) {
        $hmAxisGenes[] = $gene . '  (no variance)';
    } elseif (isset($hmMissingSet[$gene])) {
        $hmAxisGenes[] = $gene . '  (not found)';
    } else {
        $hmAxisGenes[] = $gene;
    }
}
$hmGenesJson = json_encode($hmAxisGenes, $hmJsonFlags);

/* 色标范围。原来写死 min:-5 / max:5，而组均值的 z-score 绝大多数落在 ±2 以内 ——
   整张图都挤在色标中间那一小段，看上去几乎全白。这里按本次实际数据取对称范围，
   向上取到 0.5 的整数倍，并夹在 [2, 5] 之间：下限 2 免得几个离群点把整图压平，
   上限 5 免得色标被拉得没有分辨率。 */
$hmZlim = 2.0;
if ($hmZmax > 0) { $hmZlim = min(5.0, max(2.0, ceil($hmZmax * 2) / 2)); }
?>

<?php if ($hmErr !== ''): ?>
<div class="gd-notice gd-warn" style="margin:24px 0"><?= $hmErr ?></div>
<?php elseif ($total_genes === 0): ?>
<div class="gd-notice gd-warn" style="margin:24px 0">
    No gene list was given, so there is nothing to plot. Open this page from the
    <b>Expression Profiling Analysis</b> button on a co-expression result page, or add
    <?php /* 例子原来给的是 XP_068716620.1，而这个号只存在于 MCAPR_TPM，
           照上一句提示配上 species=LPERT 必然报「哪个库里都没有」—— 两条提示互相打架。
           换成 LPERT 自己的号（OS493_…），并把「两个参数要同属一个物种」写明白。 */ ?>
    <code>&amp;genelist=OS493_000001-T1</code> to the URL (one gene ID per line; the IDs must belong to
    the species you passed, since gene IDs are species-specific).
</div>
<?php elseif ($hmFound === 0): ?>
<div class="gd-notice gd-warn" style="margin:24px 0">
    None of the <b><?= $total_genes ?></b> gene ID<?= $total_genes === 1 ? '' : 's' ?> given exists in
    <code><?= htmlspecialchars($tab) ?></code>. Gene IDs are species-specific, so this is what happens
    when the list came from another species &mdash; check that the species matches the list.
</div>
<?php endif; ?>

<?php if ($hmFound > 0): ?>
<?php /* 统计信息 */ ?>
<div class="stats-container">
	<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">🧬</div>
        <div class="stat-number"><?= $hmFound ?></div>
        <div class="stat-label">Genes Plotted<?= $hmFound < $total_genes ? ' of ' . $total_genes . ' requested' : '' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-number"><?= $total_samples ?></div>
        <div class="stat-label">Samples (columns)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-number"><?= $total_samples * $hmFound ?></div>
        <div class="stat-label">Grid Cells<?= $hmBlank > 0 ? ' &middot; ' . $hmBlank . ' blank' : '' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🔬</div>
        <div class="stat-number"><i><?= htmlspecialchars($result1 ? $result1[0] : $spe) ?></i></div>
        <div class="stat-label">Species</div>
    </div>
	</div>
</div>

<?php
/* 图宽。横轴短标签（run 号）大约 11 个字符，竖排后每列至少要 ~20px 才不挤；
   样本多的时候整张图会比页面宽，所以外面套一个横向滚动容器，而不是把格子压扁。 */
$hmChartW = $total_samples * 20 + 210;
$hmScroll = ($hmChartW > 1420);
if ($hmChartW < 1100) { $hmChartW = 1100; }
?>
<?php /* 图表容器 */ ?>
<div class="chart-container">
    <div class="chart-header">
        Expression Profiles Heatmap
    </div>

    <div class="hm-scroll">
        <div id="containerA" style="height: <?= $height ?>px; <?= $hmScroll ? 'width: ' . $hmChartW . 'px;' : 'width: 100%;' ?>"></div>
    </div>

    <p class="hm-caption">
        Each row is one gene, each column one RNA-seq sample, and each cell the mean expression of
        that sample&rsquo;s group expressed as a <b>z-score across the row</b>:
        <span style="color:#b2182b;font-weight:600">red</span> means higher than that gene&rsquo;s own average,
        <span style="color:#2166ac;font-weight:600">blue</span> lower.
        The scale is per row, so colours compare samples <em>within</em> one gene &mdash; reading a red cell
        in one row against a red cell in another tells you nothing about which gene is more abundant.
        Hover any cell for the full sample name and the underlying z-score.
        <?php if ($hmScroll): ?>
        This species has <?= $total_samples ?> samples, so the figure is wider than the page &mdash;
        scroll it sideways (or drag the bar under the figure) to reach the later samples.
        <?php endif; ?>
        <?php if ($hmMissing): ?>
        A blank row means that gene ID is not in <code><?= htmlspecialchars($tab) ?></code> &mdash;
        gene IDs are species-specific, so this usually means the list came from another species.
        Missing here: <?= htmlspecialchars(implode(', ', array_slice($hmMissing, 0, 8))) ?><?= count($hmMissing) > 8 ? ' and ' . (count($hmMissing) - 8) . ' more' : '' ?>.
        <?php endif; ?>
    </p>

    <pre id="csv" style="display: none"><?= $csv ?></pre>
</div>

<?php endif; /* $hmFound > 0 */ ?>

</div>
</div>
</div>

<?php /* 页脚挪到 </html> 前：历史上它里面 mapmyvisitors 那行 <script src> 是同步外链，会挡住
        HTML 解析，也会挡住 DOMContentLoaded，下面那段画图的脚本（包在 $(function(){...})
       里）就得等这个第三方域名答复才开始画。那个组件 2026-09-28 起改成 load 后注入、不再
       挡任何东西，但页脚仍保持在最末。 */ ?>

<?php if ($hmFound > 0): /* 没有图就不要把整段配置发出去：没匹配到基因时 #containerA 和
   #csv 都不存在，这段脚本会在 document.getElementById('csv').innerHTML 上抛错。 */ ?>
<script type="text/javascript">
function hmBoot() {

    /**
     * This plugin extends Highcharts in two ways:
     * - Use HTML5 canvas instead of SVG for rendering of the heatmap squares. Canvas
     *   outperforms SVG when it comes to thousands of single shapes.
     * - Add a K-D-tree to find the nearest point on mouse move. Since we no longer have SVG shapes
     *   to capture mouseovers, we need another way of detecting hover points for the tooltip.
     */
    (function (H) {
        var Series = H.Series,
            each = H.each,
            wrap = H.wrap,
            seriesTypes = H.seriesTypes;

        /**
         * Create a hidden canvas to draw the graph on. The contents is later copied over 
         * to an SVG image element.
         */
        Series.prototype.getContext = function () {
            if (!this.canvas) {
                this.canvas = document.createElement('canvas');
                this.canvas.setAttribute('width', this.chart.chartWidth);
                this.canvas.setAttribute('height', this.chart.chartHeight);
                this.image = this.chart.renderer.image('', 0, 0, this.chart.chartWidth, this.chart.chartHeight).add(this.group);
                this.ctx = this.canvas.getContext('2d');
            }
            return this.ctx;
        };

        /** 
         * Draw the canvas image inside an SVG image
         */ 
        Series.prototype.canvasToSVG = function () {
            this.image.attr({ href: this.canvas.toDataURL('image/png') });
        };

        /**
         * Wrap the drawPoints method to draw the points in canvas instead of the slower SVG,
         * that requires one shape each point.
         */
        H.wrap(H.seriesTypes.heatmap.prototype, 'drawPoints', function (proceed) {

            var ctx = this.getContext();
            
            if (ctx) {

                // draw the columns
                each(this.points, function (point) {
                    var plotY = point.plotY,
                        shapeArgs;

                    if (plotY !== undefined && !isNaN(plotY) && point.y !== null) {
                        shapeArgs = point.shapeArgs;

                        ctx.fillStyle = point.pointAttr[''].fill;

                        <?php /* 格子之间留 0.5px 的缝，样本多的时候才看得出每一列是独立的。
                           格子本身够宽才缩 —— 窄格子再缩就只剩噪声了。
                           series.borderWidth 对这层 canvas 渲染不起作用，只能改矩形。 */ ?>
                        var gx = (shapeArgs.width  > 4 ? 0.5 : 0),
                            gy = (shapeArgs.height > 4 ? 0.5 : 0);
                        ctx.fillRect(shapeArgs.x + gx,
                                     shapeArgs.y + gy,
                                     Math.max(0.5, shapeArgs.width  - 2 * gx),
                                     Math.max(0.5, shapeArgs.height - 2 * gy));
                    }
                });

                this.canvasToSVG();

            } else {
                this.chart.showLoading("Your browser doesn't support HTML5 canvas, <br>please use a modern browser");

                // Uncomment this to provide low-level (slow) support in oldIE. It will cause script errors on
                // charts with more than a few thousand points.
                //proceed.call(this);
            }
        });
        H.seriesTypes.heatmap.prototype.directTouch = false; // Use k-d-tree
    }(Highcharts));


    var start;

    <?php /* 横轴 categories 用的是短标签（run 号），完整样本名在这里另存一份，顺序一致，
       tooltip 靠这个下标取全名，见下面的 formatter。 */ ?>
    var HM_SAMPLES = <?= $hmLabelsJson ?>;

    $('#containerA').highcharts({

        data: {
            csv: document.getElementById('csv').innerHTML,
            parsed: function () {
                start = +new Date();
            }
        },

        chart: {
            type: 'heatmap',
            marginTop: 64,
            marginBottom: 96,
            marginRight: 130,
            backgroundColor: 'transparent',
			style: {
            fontFamily: 'Verdana, sans-serif'
			},
            <?php /* 画布渲染的 heatmap 不会自己留空隙，格子靠下面 series 里的描边分开。 */ ?>
            spacingBottom: 0
        },


        <?php /* 标题左对齐、贴着图的左边，比原来居中 + x:40 的挪法稳；
           subtitle 交代这张图的读法（行内 z-score），省得读者以为颜色能跨行比。 */ ?>
        title: {
            text: 'Expression Profiles Heatmap',
            style : {
                'fontSize' : '19px',
                'fontWeight': '600',
                'color': '#1e293b',
                'fontFamily': 'Verdana, sans-serif'
            },
            align: 'left',
            x: 0
        },

        subtitle: {
            text: 'Row-wise z-score of mean TPM across <?= $total_samples ?> samples — '
                + 'colour compares samples within one gene, not one gene against another',
            style : {
                'fontSize' : '12px',
                'color': '#64748b',
                'fontFamily': 'Verdana, sans-serif'
            },
            align: 'left',
            x: 0,
            y: 34
        },


        xAxis: {
            categories: <?= $hmShortJson ?>,
            title: null,
            labels : {
                style : {
                    'fontSize' : '10px',
                    'color': '#475569'
                },
                <?php /* 横轴标签是 run 号。竖排后每列只要 ~20px 就放得下，110 个样本也不会糊成一片；
                   完整的样本条件在 tooltip 里，见 HM_SAMPLES。 */ ?>
                rotation: -90,
                align: 'right'
            },
            lineColor: '#cbd5e1',
            tickLength: 4
        },

        yAxis: {
            categories: <?= $hmGenesJson ?>,
            title: null,
            labels : {
                style : {
                    'fontSize' : '12px',
                    'color': '#334155'
                }
            }
        },

        <?php /* 发散色标（ColorBrewer RdBu-11）：两端的亮度各自单调，中点 #f7f7f7 正好中性，
           所以「更深 = 更极端」在两条臂上都成立。旧色标在 0.5 处放的是近白色、0.25 处放
           中蓝，最小值和中间色都不是同一族的深浅关系，值大值小看不出方向。
           范围按本次数据取对称区间 $hmZlim，而不是写死 ±5。 */ ?>
        colorAxis: {
            stops: [
                [0.0, '#053061'], [0.1, '#2166ac'], [0.2, '#4393c3'],
                [0.3, '#92c5de'], [0.4, '#d1e5f0'], [0.5, '#f7f7f7'],
                [0.6, '#fddbc7'], [0.7, '#f4a582'], [0.8, '#d6604d'],
                [0.9, '#b2182b'], [1.0, '#67001f']
            ],
            min: <?= -$hmZlim ?>,
            max: <?= $hmZlim ?>,
            startOnTick: false,
            endOnTick: false,
            labels: {
                format: '{value}'
            }
        },

        legend: {
            align: 'right',
            layout: 'vertical',
            margin: 0,
            verticalAlign: 'top',
            y: 25,
            symbolHeight: <?= max(180, min(380, $height - 150)) ?>,
            title: {
                text: 'z-score',
                style: {
                    'fontSize': '11px',
                    'fontWeight': '600',
                    'color': '#475569'
                }
            }
        },

        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.92)',
            borderWidth: 0,
            borderRadius: 6,
            shadow: false,
            style: {
                color: '#f1f5f9',
                fontSize: '12px'
            },
            formatter: function () {
                <?php /* 横轴是短标签，这里换成完整样本名；HM_SAMPLES 与 categories 同序生成。 */ ?>
                var full = (typeof HM_SAMPLES !== 'undefined' && HM_SAMPLES[this.point.x]) || this.point.x;
                var z = this.point.value;
                var dir = z > 0 ? 'higher' : (z < 0 ? 'lower' : 'equal');
                return '<b>' + full + '</b><br/>'
                     + 'Gene: <i>' + this.series.yAxis.categories[this.point.y] + '</i><br/>'
                     + 'z = <b>' + z + '</b> &mdash; ' + Math.abs(z).toFixed(1)
                     + '&nbsp;SD ' + dir + ' than this gene’s average across all samples';
            }
        },
        
        series: [{
            borderWidth: 0,
            turboThreshold: Number.MAX_VALUE
        }]

    });
    console.log('Rendered in ' + (new Date() - start) + ' ms');
}

<?php /* 不再包 $(function(){...})：这段 <script> 就排在 #containerA 和 #csv 后面，走到这里
   两个节点都在了；包成 ready 只是让它去等 DOMContentLoaded，而页脚那个同步外链会把
   DOMContentLoaded 拖住（把它延迟 3 秒实测：DCL 850 → 3848 ms，图跟着页脚一起出来）。
   下面的判断是为"万一以后脚本被挪到容器之前"留的退路。 */ ?>
if (document.getElementById('containerA')) { hmBoot(); } else { $(hmBoot); }
</script>
<?php endif; /* $hmFound > 0 */ ?>
<?php
    include "../Webpage_components.php";
    print $footer;
?>
</body>
</html>
