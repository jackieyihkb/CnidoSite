<?php
// ... 保留原有的 PHP 逻辑代码 ...
#######dealing with detail result#######
/* $qnum 来自 URL，会被拼进 ../tmp/<qnum>.detail 的路径，也会回显到页面和
   GSEAdetail.php?session=… 的链接里。只接受纯数字的作业号，避免路径穿越
   （?session=../../etc/passwd）与反射型 XSS。与 compute.php 的校验一致。 */
$qnum = isset($_GET['session']) ? (string)$_GET['session'] : '';
if (!preg_match('/^[0-9]{1,18}$/', $qnum)) {
    echo '<script>alert("Invalid job ID.");window.location="GSEA.php";</script>';
    exit;
}
$enrich_count = 0;
$hash = [];
$sort_tmp = [];

/* 这张表只列「通过本作业自己那个 cutoff」的集合，所以阈值必须先从 .conf 里读出来 ——
   原来这里写死 0.05，而汇总卡和表标题印的是 $paraList['cutoff']（作业自己的值）。
   于是 cutoff=0.01 的作业会印着「FDR < 0.01」，却把这支 0.05 的集合全列出来
   （实测 session 369539400：印 FDR < 0.01，列 25 个集合，其中 5 个 FDR 在 0.01–0.05 之间）。
   阈值只有一处真相，就是 .conf 文件；$__cut 同时喂给下面的筛选和 enrich_plot.php。 */
$__cut = 0.05;
if (file_exists("../tmp/$qnum.conf")) {
    foreach (file("../tmp/$qnum.conf") as $__l) {
        $__p = explode("\t", trim($__l));
        if (isset($__p[0], $__p[1]) && $__p[0] === 'cutoff' && is_numeric($__p[1])) {
            $__cut = (float)$__p[1];
        }
    }
}

if (file_exists("../tmp/$qnum.detail")) {
    $f = file("../tmp/$qnum.detail");
    foreach($f as $line){
        trim($line);
        $list = explode("\t", trim($line));
        if ($list[5] < $__cut) {
            $enrich_count++;
            $hash[$list[0]] = $line;
            $sort_tmp[$list[0]] = $list[5];
        }
    }
    fclose($f);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Gene Set Enrichment Results - CnidoSite</title>
<meta name="keywords" content="Cnidaria, gene set enrichment analysis, GSEA results, functional enrichment" />
<meta name="description" content="Results of gene set enrichment analysis showing significantly enriched pathways and functions" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script language="JavaScript" src="./func.js" type="text/javascript"></script>

<style>
<?php /* GSEA结果页面专用样式 - 不影响全局CSS */ ?>
.gsea-result-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.gsea-result-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.gsea-result-header p {
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

.network-view-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 18px 25px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
}

.network-view-content {
    padding: 20px;
    min-height: 600px;
}

<?php /* 摘要信息卡片美化 */ ?>
.summary-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 30px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
	text-align: center;
}

.summary-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
	justify-content: center;
}

.summary-title::before {
    content: "📊";
    font-size: 24px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.summary-item {
    background: #f8fafc;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center; 
    justify-content: center;
}

.summary-item:hover {
    background: #f0f9ff;
    border-color:#1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.summary-label {
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 8px;
	text-align: center;
}

.summary-value {
    font-size: 16px;
    color: #1e293b;
    font-weight: 500;
    word-break: break-word;
	 text-align: center;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 5px; 
}

.summary-value a {
    color:#1d4ed8;
    text-decoration: none;
    transition: all 0.3s ease;
}

.summary-value a:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.job-id {
    background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
    color: #5b21b6;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    margin-top: 5px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 15px;
}

<?php /* 表格容器美化 */ ?>
.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e2e8f0;
    margin: 30px 0px;
}

.table-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 18px 25px;
    font-size: 18px;
    font-weight: 600;
}

.table-scroll-container {
    max-height: 600px;
    overflow-y: auto;
	margin: 10px 20px;
}

<?php /* 基因集链接样式 */ ?>
.geneset-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.geneset-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

<?php /* 无结果提示美化 */ ?>
.no-results {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 4px solid #f59e0b;
    padding: 25px 30px;
    border-radius: 12px;
    margin: 30px 0;
    color: #92400e;
    line-height: 1.7;
    text-align: center;
}

.no-results-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.no-results h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
    color: #78350f;
}

.no-results p {
    margin: 0;
    font-size: 16px;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .gsea-result-header {
        padding: 20px;
    }
    
    .gsea-result-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .summary-container {
        padding: 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .table-container {
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .table-scroll-container {
        max-height: 400px;
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Gene Set Enrichment Results</b></legend>
<p  class="paleo-intro">Significantly enriched biological functions and pathways identified from your gene list.</p>

<?php
// 获取参数信息
$f = file("../tmp/$qnum.conf");
$paraList = [];
foreach($f as $line){
    list($para, $val) = explode("\t", trim($line));
    $paraList[$para] = $val;
}
$species = $paraList['species'] ?? 'Unknown';
$cutoff = $paraList['cutoff'] ?? (string)$__cut;   /* 与上面筛选用的是同一个数 */

// 获取基因数量
$lineNum = count(file("../tmp/$qnum.query"));
$ReduLineNum = count(file("../tmp/$qnum.redu.query"));
?>

<?php /* 摘要信息卡片 */ ?>
<div class="summary-container">
    <div class="summary-title">Basic Computing Summary</div>
    <div class="summary-grid">
        <div class="summary-item">
            <div class="summary-label">Job ID (available within 3 months)</div>
            <div class="summary-value">
                <div class="job-id"><?= $qnum ?></div>
            </div>
        </div>
        
        <div class="summary-item">
            <div class="summary-label">Categories Selected</div>
            <div class="summary-value">
                <?php
                $f = file("../tmp/$qnum.category");
                foreach($f as $line){
                    if (preg_match("/(.*)\_(.*)/", $line, $regs)) {
                        echo '<span style="background: #ede9fe; color: #5b21b6; padding: 4px 4px; border-radius: 4px; margin: 0 5px 5px 0; display: inline-block; font-size: 13px;">' . htmlspecialchars($regs[2]) . '</span>';
                    }
                }
                ?>
            </div>
        </div>
        
        <div class="summary-item">
            <div class="summary-label">Query Genes After Redundancy</div>
            <div class="summary-value">
                <?php /* 不能直接链到 /tmp/<作业号>.query：tmp/ 已整目录拒绝 HTTP
                       访问（tmp/.htaccess），直链会变成 403。改走 tmp_download.php
                       这个按文件名白名单放行的窄口。 */ ?>
                <a href="/tmp_download.php?f=<?= $qnum ?>.query" target="_blank"><?= $lineNum ?> genes</a>
            </div>
        </div>
        
        <div class="summary-item">
            <div class="summary-label">Significance Cutoff</div>
            <div class="summary-value">FDR < <?= htmlspecialchars($cutoff) ?></div>
        </div>
    </div>
</div>

<?php /* 富集结果图：可导出为投稿用的 SVG / PNG。数据与下面的表格同源（同一批 FDR<0.05 的集）。 */ ?>
<?php include __DIR__ . '/enrich_plot.php'; ?>

<?php /* 结果表格 */ ?>
<div class="table-container">
    <div class="network-view-header">
        Significantly Enriched Gene Sets (FDR < <?= htmlspecialchars($cutoff) ?>)
    </div>
    
    <?php if ($enrich_count == 0): ?>
        <div class="no-results">
            <div class="no-results-icon">🔍</div>
            <h3>No Significant Enrichment Found</h3>
            <p>Sorry, no gene sets met the significance cutoff (FDR < <?= htmlspecialchars($cutoff) ?>).</p>
            <p>Try adjusting the cutoff value or using a different gene set.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll-container">
            <table class="gridtable">
                <thead>
                    <tr>
                        <th width="20%">Gene Set Name (NO. Genes)</th>
                        <?php /* Description 是自由文本，居中难看，显式左对齐。 */ ?>
                        <th width="35%" class="tal">Description</th>
                        <th width="6%">Overlap Genes</th>
                        <th width="6%">p-value</th>
                        <th width="7%">FDR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    asort($sort_tmp);
                    foreach($sort_tmp as $module => $fdr) {
                        $value = $hash[$module];
                        $list = explode("\t", $value);
                        echo "<tr>";
                        echo "<td><a href=\"GSEAdetail.php?session=" . urlencode($qnum) . "&amp;species=" . urlencode($species) . "&amp;geneset=" . urlencode($module) . "\" class=\"geneset-link\">" . htmlspecialchars($module, ENT_QUOTES, 'UTF-8') . "</a> (" . htmlspecialchars($list[1], ENT_QUOTES, 'UTF-8') . ")</td>";
                        echo "<td class=\"tal\">" . htmlspecialchars($list[2]) . "</td>";
                        echo "<td align=\"center\">" . htmlspecialchars($list[3]) . "</td>";
                        $pvalue = sprintf("%1\$.2e", $list[4]);
                        $fdr_formatted = sprintf("%1\$.3e", $list[5]);
                        echo "<td align=\"center\">" . $pvalue . "</td>";
                        echo "<td align=\"center\">" . $fdr_formatted . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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