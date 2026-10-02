<?php
/* 入参兜底 + 物种标识归一。
   原来直接读 $_POST[...]（只传 GET 时报 Undefined index），而且 $species 是
   下划线形式（Nematostella_vectensis），abbr 表的 species 列存的是带空格的
   学名，精确匹配查不到 —— 标题就会显示成下划线形式的代码而不是拉丁学名，
   下面链到 family_member_detail.php 的 species 参数也跟着错。
   统一经 cnido_latin_of() 归一后再用。 */
require_once __DIR__ . '/includes/state.php';
$species = isset($_GET['species']) ? $_GET['species'] : (isset($_POST['species']) ? $_POST['species'] : '');
$family  = isset($_GET['family'])  ? $_GET['family']  : (isset($_POST['family'])  ? $_POST['family']  : '');
$species = cnido_latin_of($species);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Gene Family Members - CnidoSite</title>
<meta name="keywords" content="Cnidaria, gene family, transcription factors, ubiquitin family, genome analysis" />
<meta name="description" content="Members of gene families across Cnidaria species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="./jquery.min.js"></script>
<script src="./jquery.treeview.js" type="text/javascript"></script>
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<link rel="stylesheet" href="/jquery.treeview.css" />

<style>
<?php /* Gene Family Members页面专用样式 - 不影响全局CSS */ ?>
.family-member-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.family-member-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.family-member-header p {
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

<?php /* 结果标题美化 */ ?>
.result-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-left: 4px solid #10b981;
    padding: 20px 25px;
    border-radius: 8px;
    margin: 25px 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.result-icon {
    font-size: 32px;
}

.result-text {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
}

.result-subtitle {
    font-size: 16px;
    color: #64748b;
    margin-top: 5px;
}

<?php /* 树形菜单美化 */ ?>
.tree-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 25px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
}

.tree-controls {
    background: #f8fafc;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
    display: flex;
    gap: 15px;
}

.tree-controls a {
    color:#047857;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    padding: 8px 12px;
    border-radius: 6px;
    background: white;
    border: 1px solid #e2e8f0;
}

.tree-controls a:hover {
    background:#047857;
    color: white;
    border-color:#047857;
}

#tree {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    font-size: 15px;
    line-height: 1.6;
}

#tree li {
    margin: 8px 0;
}

#tree span {
    color: #1e293b;
    font-weight: 600;
}

#tree a {
    color:#047857;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    padding: 4px 8px;
    border-radius: 4px;
}

#tree a:hover {
    background: #f0fdf4;
    color:#047857;
    text-decoration: underline;
}

<?php /* TF表格美化 */ ?>
.tf-table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e2e8f0;
    margin: 30px 0;
}

.tf-table-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 18px 25px;
    font-size: 18px;
    font-weight: 600;
}

<?php /* 表本身挂 gridtable：表头、行分隔线、斑马纹、悬停、字号、链接配色全部由
   templatemo_style.css 的共用样式提供（与 core 的 table.cc 一致）。
   原来这张表是每格描一圈 1px #e2e8f0 的方框 + 每格单独 hover 变浅绿 + 绿色链接，
   是站里唯一一处这样的表：统一后删掉，悬停改成共用的整行高亮，链接走共用的蓝色。
   这里只留每格的内边距。 */ ?>
.tf-table td {
    padding: 16px 12px;
    text-align: center;
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

.description-text a {
    color:#047857;
    text-decoration: none;
    font-weight: 600;
}

.description-text a:hover {
    text-decoration: underline;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .family-member-header {
        padding: 20px;
    }
    
    .family-member-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .tree-container {
        padding: 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .tree-controls {
        flex-direction: column;
        gap: 10px;
    }
    
    .tree-controls a {
        text-align: center;
    }
    
    .tf-table-container {
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .result-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>

<script>
$(function() {
    $("#tree").treeview({
        collapsed: false,
        animated: "medium",
        control: "#sidetreecontrol",
        persist: "location"
    });
});
</script>
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

<?php
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
$query = mysqli_query($conn, "SELECT * FROM abbr WHERE species = '$species'");
$result = mysqli_fetch_row($query);

if ($family == "ubs") {
?>
<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Ubiquitin Family in <i><?php echo htmlspecialchars($species); ?></i></b></legend>
<p  class="paleo-intro">Ubiquitin families were mainly identified through HMM searches of data from iUUCD.</p>

<?php
/* 下面那棵树是写死的完整家族表，与物种无关。物种一条泛素注释都没有时
   （例如 Taxipathes sp. SY275-mao，它只有转录因子），每个叶子点进去都是空表；
   树照印，但先在这里说清楚。 */
$__n_ubs = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM ubs WHERE species = '" . mysqli_real_escape_string($conn, $species) . "'"));
if ($__n_ubs && $__n_ubs[0] == 0) {
    echo '<p class="paleo-intro"><b><i>' . htmlspecialchars($species) . '</i></b> has no ubiquitin-family assignments in the database, so every family below would come back empty. '
       . 'Switch to <em>Transcription Factors Family</em> above, or pick another species on the <a href="/gene_family.php">Gene Family</a> page.</p>';
}
?>

<?php /* 树形菜单容器 */ ?>
<div class="tree-container">
    <div class="tree-controls" id="sidetreecontrol"><b>All ubiquitin families</b></div>
    <ul id="tree">
        <li><span><strong>E1</strong></span>
            <ul>
                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=ThiF">ThiF</a></li>
            </ul>
        </li>
        <li><span><strong>E2</strong></span>
            <ul>
                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UBC">UBC</a></li>
                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UEV">UEV</a></li>
            </ul>
        </li>
        <li><span><strong>DUB</strong></span>
            <ul>
                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UCH">UCH</a></li>
                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=USP">USP</a></li>
                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=OTU">OTU</a></li>
                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=Josephin">Josephin</a></li>
                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=JAMM">JAMM</a></li>
                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=ULP">ULP</a></li>
                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=MINDY">MINDy</a></li>
            </ul>
        </li>
        <li><span><strong>E3</strong></span>
            <ul>
                <li>E3 activity
                    <ul>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=HECT">HECT</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=DCUN1">DCUN1</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=N-domain">N-domain</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UBR-box">UBR-box</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=RBR">RBR</a></li>
                        <li>RING
                            <ul>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=RING">RING</a></li>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=U-box">U-box</a></li>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=PHD">PHD</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li>E3 adaptor
                    <ul>
                        <li>Cullin RING
                            <ul>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=Cullin">Cullin</a></li>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=F-box">F-box</a></li>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=SKP1">SKP1</a></li>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=BTB">BTB</a></li>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=CDC20">CDC20</a></li>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=DDB1">DDB1</a></li>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=DWD">DWD</a></li>
                                <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=SOCS">SOCS</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>
        </li>
        <li><span><strong>UBD</strong></span>
            <ul>
                <li>Alpha-Helix
                    <ul>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=CUE">CUE</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=GAT">GAT</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=MIU">MIU</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UBA">UBA</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UBAN">UBAN</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UBM">UBM</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UIM">UIM</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=VHS">VHS</a></li>
                    </ul>
                </li>
                <li>UBC-like
                    <ul>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UBC">UBC</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UEV">UEV</a></li>
                    </ul>
                </li>
                <li>ZnF
                    <ul>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=NZF">NZF</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UBZ">UBZ</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=ZnF_A20">ZnF_A20</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=ZnF_UBP">ZnF_UBP</a></li>
                    </ul>
                </li>
                <li>PH
                    <ul>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=GLUE">GLUE</a></li>
                    </ul>
                </li>
                <li>Other
                    <ul>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=SH3">SH3</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=Beta-prp">Beta-prp</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=CARD">CARD</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=Jab_MPN">Jab_MPN</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=PFU">PFU</a></li>
                    </ul>
                </li>
            </ul>
        </li>
        <li><span><strong>ULD</strong></span>
            <ul>
                <li>UBL
                    <ul>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=ATG8">ATG8</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=NEDD8">NEDD8</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=SUMO">SUMO</a></li>
                    </ul>
                </li>
                <li>UFD/UBQ
                    <ul>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UBQ_Other">UBQ_Other</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UBQ_PIM">UBQ_PIM</a></li>
                    </ul>
                </li>
                <li>UFD
                    <ul>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=DWNN">DWNN</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=PB1">PB1</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=RAWUL">RAWUL</a></li>
                        <li><a href="/family_member_detail.php?gene_family=ubs&amp;species=<?php echo urlencode($species);?>&amp;family_id=UBX">UBX</a></li>
                    </ul>
                </li>
            </ul>
        </li>
    </ul>
</div>

<?php
} elseif ($family == "tf") {
?>
<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Transcription Factors in <i><?php echo htmlspecialchars($species); ?></i></b></legend>
<p  class="paleo-intro">Transcription factors (TFs) of <strong><?php echo htmlspecialchars($species); ?></strong> are classified into families according to their conserved DBDs, following <a href="https://guolab.wchscu.cn/AnimalTFDB4//#/" target="_blank" rel="noopener noreferrer">AnimalTFDB 4.0</a> and self-built HMM profiles.</p>

<?php /* TF表格容器 */ ?>
<div class="tf-table-container">
    <div class="tf-table-header">
        Transcription Factor Families
    </div>
    
    <?php
    $family = array();
    $conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    $query1 = mysqli_query($conn, "SELECT Family FROM tf WHERE species='$species'");
    while ($result1 = mysqli_fetch_row($query1)) {
        $family["$result1[0]"]++;
    }
    
    if (!array_filter(array_keys($family), 'strlen')) {
        /* 这个物种一条 TF 注释都没有（例如 Trachythela sp. YZ-2020，它只有泛素家族）。
           原先这里印的是一个空表格壳子，什么也不解释。 */
        echo '<p class="paleo-intro">No transcription-factor families are annotated for <b><i>'
           . htmlspecialchars($species) . '</i></b>, so there is no family to list. '
           . 'Switch to <em>Ubiquitin Family</em> above, or pick another species on the '
           . '<a href="/gene_family.php">Gene Family</a> page.</p>';
    } else {
    echo '<table class="gridtable tf-table">';
    $i = 0;
    foreach ($family as $x => $x_value) {
        if ($x != "") {
            if ($i % 5 == 0) {
                echo "</tr><tr><td align=\"center\"><a href=\"./family_member_detail.php?gene_family=tf&amp;species=" . urlencode($species) . "&amp;family_id=" . urlencode($x) . "\">$x</a>($x_value)</td>";
            } else {
                echo "<td align=\"center\"><a href=\"./family_member_detail.php?gene_family=tf&amp;species=" . urlencode($species) . "&amp;family_id=" . urlencode($x) . "\">$x</a>($x_value)</td>";
            }
            $i++;
        }
    }
    foreach ($family as $x => $x_value) {
        if ($x == "") {
            if ($i % 5 == 0) {
                echo "</tr><tr><td align=\"center\"><a href=\"./family_member_detail.php?gene_family=tf&amp;species=" . urlencode($species) . "&amp;family_id=" . urlencode($x) . "\">$x</a>($x_value)</td>";
            }
            $i++;
        }
    }
    /* 补格数要按实际余数算。原先写死 2 个空 <td>，只有「末行恰好 3 格」时才对：
       家族数能被 5 整除的物种（Acropora millepora 65 个）末行被撑成 7 列；余 4 的
       物种则缺格、右边没有边框。$i 此时等于已输出的格数（tf 表里 Family 无空值，
       下面那个 $x=="" 的循环不会执行）。 */
    $pad = (5 - ($i % 5)) % 5;
    echo str_repeat("<td></td>", $pad) . "</tr></table>";
    }
    ?>
</div>
<?php
} else {
    /* 既不是 ubs 也不是 tf（含完全不传 gene_family 的情况）时，原先这个分支不存在，
       整页只剩页眉页脚。给一个二选一的入口。 */
    $__who = $species !== '' ? '<b><i>' . htmlspecialchars($species) . '</i></b>' : 'a species';
    echo '<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;Gene Family</legend>';
    echo '<p  class="paleo-intro">Choose a family system to browse for ' . $__who . ': '
       . '<a href="./family_member.php?family=tf&amp;species=' . urlencode($species) . '">Transcription Factors</a>, or '
       . '<a href="./family_member.php?family=ubs&amp;species=' . urlencode($species) . '">Ubiquitin Family</a>. '
       . 'You can also pick a different species on the <a href="/gene_family.php">Gene Family</a> page.</p>';
}
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
