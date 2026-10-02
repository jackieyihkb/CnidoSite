<?php
/* 三个参数都直接来自 URL：
     $qnum    -> ../tmp/<qnum>.detail         （路径）
     $species -> database/<species>.DetailInfo1（路径）以及
                 "SELECT * FROM abbr WHERE abbr1 = '$species'"（SQL）
     $geneset -> 与 DetailInfo1 里的字段比较、并作为 $overlap 的键
   都不做校验的话，?session=../../..&species=../.. 可以穿越目录，
   ?species=' UNION SELECT ... 可以注入。这里按各自的真实取值域收紧：
   作业号是纯数字，abbr1 是 [A-Za-z0-9_]（库里 325 条全部符合），
   基因集名是 [A-Z0-9_]（database/*.DetailInfo 的键）。 */
$qnum    = isset($_GET['session']) ? (string)$_GET['session'] : '';
$species = isset($_GET['species']) ? (string)$_GET['species'] : '';
$geneset = isset($_GET['geneset']) ? (string)$_GET['geneset'] : '';

if (!preg_match('/^[0-9]{1,18}$/', $qnum)
    || !preg_match('/^[A-Za-z0-9_]{1,40}$/', $species)
    || !preg_match('/^[A-Za-z0-9_]{1,200}$/', $geneset)) {
    echo '<script>alert("Invalid request.");window.location="GSEA.php";</script>';
    exit;
}

// 获取重叠基因信息
$overlap = [];
if (file_exists("../tmp/$qnum.detail")) {
    $f = file("../tmp/$qnum.detail");
    foreach($f as $line){
        list($a, $b, $c, $d, $e, $f_val, $g) = explode("\t", $line);
        $overlap[$a] = $g;
    }
}

// 获取基因集详细信息
$geneset_info = [];
if (file_exists("database/$species.DetailInfo1")) {
    $f = file("database/$species.DetailInfo1");
    foreach($f as $line){
        list($a, $b, $c, $d, $e, $f_val, $g, $h, $i, $j) = explode("\t", $line);
        if (strcmp($a, $geneset) == 0) {
            $geneset_info = [
                'name' => $a,
                'source' => $b,
                'species_name' => $c,
                'external_id' => $d,
                'contributor' => $f_val,
                'organization' => $g,
                'url' => $h,
                'brief_desc' => $i,
                'full_desc' => $j
            ];
            break;
        }
    }
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
<title>Gene Set Details - CnidoSite</title>
<meta name="keywords" content="Cnidaria, gene set enrichment, detailed information, pathway analysis" />
<meta name="description" content="Detailed information about enriched gene sets from GSEA analysis" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script language="JavaScript" src="./func.js" type="text/javascript"></script>

<style>
<?php /* GSEA详情页面专用样式 - 不影响全局CSS */ ?>
.gsea-detail-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.gsea-detail-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.gsea-detail-header p {
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

<?php /* 详情卡片美化 */ ?>
.detail-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 30px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
}

.detail-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f5f9;
}

.detail-title::before {
    content: "📋";
    font-size: 24px;
}

<?php /* 信息表格美化 */ ?>
.info-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
    margin-bottom: 10px;
}

.info-table tr {
    background: #f8fafc;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.info-table td {
    padding: 18px 20px;
    border: none;
    vertical-align: top;
}

.info-table td:first-child {
    width: 25%;
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    border-right: 1px solid #e2e8f0;
}

.info-table td:last-child {
    color: #1e293b;
    line-height: 1.6;
}

<?php /* 详情表里「来源没写」的记号。全局的 .gd-na（templatemo_style.css:1069）是 (0,1,0)，
   会被上面的 .info-table td:last-child (0,1,2) 压成正文色，所以在本页凑到 (0,2,1) 拿回来。 */ ?>
.info-table td .gd-na {
    color: #64748b;
    font-style: italic;
    font-weight: 400;
}

<?php /* 基因链接样式 */ ?>
.gene-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-block;
    margin: 0 8px 8px 0;
    padding: 6px 6px;
    background:#f0f9ff;
    border-radius: 6px;
    border: 1px solid #dbeafe;
}

.gene-link:hover {
    color: #1d4ed8;
    background: #dbeafe;
    border-color: #93c5fd;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.2);
}

<?php /* 外部链接样式 */ ?>
.external-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.external-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

<?php /* 重叠基因区域 */ ?>
.overlap-section {
    background: #f8fafc;
    border-radius: 10px;
    padding: 25px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
}

.overlap-title {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.overlap-title::before {
    content: "🧬";
    font-size: 20px;
}

.gene-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .gsea-detail-header {
        padding: 20px;
    }
    
    .gsea-detail-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .detail-container {
        padding: 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .info-table tr {
        display: block;
        margin-bottom: 15px;
    }
    
    .info-table td {
        display: block;
        width: 100%;
    }
    
    .info-table td:first-child {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #e2e8f0;
        border-radius: 8px 8px 0 0;
    }
    
    .gene-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Details of Enriched Gene Sets</b></legend>
<p  class="paleo-intro">Detailed information about the enriched gene set from your GSEA analysis.</p>

<?php if (!empty($geneset_info)): ?>
<?php /* 详情卡片 */ ?>
<div class="detail-container">
    <div class="detail-title">
        Detailed Information of Gene Set: <span style="color:#1d4ed8;"><?= htmlspecialchars($geneset_info['name']) ?></span>
    </div>
    
    <table class="info-table">
        <tr>
            <td>Standard Gene Set Name</td>
            <td><?= htmlspecialchars($geneset_info['name']) ?></td>
        </tr>
        <tr>
            <td>Species</td>
            <td><i><?= htmlspecialchars($geneset_info['species_name']) ?></i></td>
        </tr>
        <tr>
            <td>Brief Description</td>
            <td><?= htmlspecialchars($geneset_info['brief_desc']) ?></td>
        </tr>
        <tr>
            <td>Full Description/Abstract</td>
            <td><?= htmlspecialchars($geneset_info['full_desc']) ?></td>
        </tr>
        <tr>
            <td>External Pathway ID/Pubmed ID</td>
            <td><?php
                /* 上游 gsea/update_Detail1.py 只给 GO 那几类写真的 GO:xxxxxxx，
                   TF/UP/DOMAIN/KEGG/Plant* 一律写死字符串 "NA"。原来这里把 NA 当内容
                   直接印出来，读起来像是「编号就叫 NA」。照本站「来源没写」的记号改印
                   灰色破折号。 */
                $__eid = trim((string)$geneset_info['external_id']);
                echo ($__eid === '' || strcasecmp($__eid, 'NA') === 0)
                    ? "<span class='gd-na' title='this type of gene set carries no external pathway or PubMed identifier'>&ndash;</span>"
                    : htmlspecialchars($__eid);
            ?></td>
        </tr>
        <tr>
            <td>Source</td>
            <td><?= htmlspecialchars($geneset_info['source']) ?></td>
        </tr>
        <?php
        /* .DetailInfo1 的第 6、7 列是两个独立字段（contributor、organization），但上游
           gsea/update_Detail1.py 把 author 和 affiliation 都赋成同一个常量
           'In-house prediction'，所以库里每一条记录这两列完全相同 —— 页面上就成了
           「Contributor/Author」和「Organization of Contributor」印出同一串字，看着像读错了列。
           实测 7 个物种 6.2 万行全部如此。两值相同时合并成一行，不再重复；
           哪天上游给出真的署名，两行会自动回来。 */
        $__ctb = trim((string)$geneset_info['contributor']);
        $__org = trim((string)$geneset_info['organization']);
        if ($__ctb !== '' && $__ctb === $__org): ?>
        <tr>
            <td>Contributor/Organization</td>
            <td><?= htmlspecialchars($__ctb) ?></td>
        </tr>
        <?php else: ?>
        <tr>
            <td>Contributor/Author</td>
            <td><?= htmlspecialchars($__ctb) ?></td>
        </tr>
        <tr>
            <td>Organization of Contributor</td>
            <td><?= htmlspecialchars($__org) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>External URL</td>
            <td>
                <?php
                /* 同上：没有外链时字段里是 "NA"。另外这里原来只判 != "NA" 就直接把值塞进
                   href，字段若为空串会印出 <a href="">，若是 javascript: 开头的串
                   htmlspecialchars 也拦不住（它不转义冒号）。改为只认 http/https 的绝对 URL，
                   其余一律按「没有外链」显示。 */
                $__url = trim((string)$geneset_info['url']);
                $__hasUrl = ($__url !== '' && strcasecmp($__url, 'NA') !== 0
                             && preg_match('#^https?://#i', $__url));
                if ($__hasUrl): ?>
                    <a href="<?= htmlspecialchars($__url) ?>" target="_blank" rel="noopener noreferrer" class="external-link">
                        🔗 <?= htmlspecialchars($__url) ?>
                    </a>
                <?php else: ?>
                    <span class="gd-na" title="the source record does not list an external URL">&ndash;</span>
                <?php endif; ?>
            </td>
        </tr>
        
        <?php if (isset($overlap[$geneset])): ?>
		
        <tr>
            <td>Overlap Members in Query</td>
            <td>
                <div class="overlap-section">
                    <div class="overlap-title">Genes from Your Query List</div>
                    <div class="gene-grid">
                        <?php
						$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
						$query = mysqli_query($conn, "SELECT * FROM abbr WHERE abbr1 = '$species'");
						$result = mysqli_fetch_row($query);
						
                        $str = str_replace(["[", "]", "'"], "", $overlap[$geneset]);
                        $arr = explode(", ", $str);
                        foreach($arr as $val){
                            $glist = preg_replace("/\s+/", "\n", $val);
                            $list = explode("\n", $glist);
                            $list = array_unique($list);
                            foreach ($list as $gene){
                                if (!empty(trim($gene))) {
                                    echo '<a href="/gene_detail.php?gene=' . urlencode(trim($gene)) . '&species=' . urlencode($result[0]) . '" class="gene-link">' . htmlspecialchars(trim($gene)) . '</a>';
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            </td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<?php else: ?>
<?php /* 无数据提示 */ ?>
<div class="detail-container">
    <div class="detail-title">Gene Set Not Found</div>
    <div style="text-align: center; padding: 40px 20px; color: #64748b;">
        <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
        <h3 style="color: #475569; margin-bottom: 10px;">No Information Available</h3>
        <p>The requested gene set details could not be found. Please check your parameters and try again.</p>
    </div>
</div>
<?php endif; ?>

</div>
</div>
</div>

<?php
include "../Webpage_components.php";
print $footer;
?>
</body>
</html>