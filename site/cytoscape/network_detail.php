<?php
/* ---------------------------------------------------------------------------
 * $hash 是 network.php 通过 URL 传过来的「已选中基因」集合，编码方式是
 * base64(serialize(array))。它完全由用户控制，所以反序列化有两个约束：
 *   1. allowed_classes => false —— 禁止在反序列化时实例化任何对象。否则攻击者
 *      可以构造任意类的对象（PHP 对象注入），配合站内其它代码可能造成更严重的
 *      后果；这里的数据本来就只是「基因 ID => 1」的数组，不需要对象。
 *   2. 只接受数组 —— 传进来字符串/标量时退回「全部基因」这个默认行为。
 * 解析失败（不是合法 base64、不是合法序列化串）时同样退回默认行为。
 * --------------------------------------------------------------------------- */
function cnido_edge_hash($raw)
{
    if (!is_string($raw) || $raw === '') { return array(); }
    $decoded = base64_decode($raw, true);
    if ($decoded === false || $decoded === '') { return array(); }
    $val = @unserialize($decoded, array('allowed_classes' => false));
    return is_array($val) ? $val : array();
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
<title>Co-expression Network Details - CnidoSite</title>
<meta name="keywords" content="Cnidaria, co-expression network, gene pairs, PCC, MR, transcriptome" />
<meta name="description" content="Detailed co-expression relationships between gene pairs in Cnidaria species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<?php /* 这两个 <script> 是相对路径，从 /cytoscape/ 解析到的是本目录下那套旧版
         func.js / jquery.min.js（147 KB + 95 KB），不是全站的 /js/func.js。旧的
         func.js 在 window.onload 里对 id=G1、G1_BP… 这些元素调 ClassNode.call()，
         本页没有这些元素，于是每次打开都抛 ReferenceError: G1 is not defined；
         而本页既没有表单也没有任何 on* 事件属性，两个文件都是纯负重，故删除。 */ ?>

<style>
<?php /* Network Detail页面专用样式 - 不影响全局CSS */ ?>
.network-detail-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.network-detail-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.network-detail-header p {
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

<?php /* 下载卡片美化 */ ?>
.download-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 20px 25px;
    margin: 25px 0;
    border: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.download-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.download-title {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.download-title::before {
    content: "📥";
    font-size: 20px;
}

.download-btn {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.download-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    background:linear-gradient(135deg, #065f46 0%, #064e3b 100%);
	color: white;
}

<?php /* 统计信息卡片 */ ?>
.stats-card {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-left: 4px solid #3b82f6;
    padding: 20px 25px;
    border-radius: 8px;
    margin: 25px 0;
    color: #1e40af;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stats-icon {
    font-size: 24px;
}

.stats-text {
    font-weight: 600;
}

<?php /* 表格容器美化 */ ?>
.results-table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e2e8f0;
    margin: 30px 0;
}

.table-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 18px 25px;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-header::before {
    content: "🧬";
    font-size: 20px;
}

<?php /* 表格：整段「表格美化」已删，改用 templatemo_style.css 里共用的
   table.gridtable 样式（与 core 的 table.cc 一致）。

   删掉的是这一页自己的蓝色渐变表头 + 大写字母 + 圆角 + 悬停放大：
     · 渐变表头（#1d4ed8→#1e40af）和别的页拉不齐，共用样式是浅灰表头；
     · th 的 text-transform/letter-spacing 只是装饰，和 core 不一致；
     · tbody tr:hover 上的 `!important` 会压过共用样式的悬停底色；
     · tr:hover 的 `transform: scale(1.01)` 让整行在悬停时放大，行与行的
       边界会互相盖住（表格行本来就不适合做 transform，它是 table-row
       的布局盒，缩放后与相邻行重叠）。
   斑马纹原来写在 tr 上，td 有不透明底色会盖住它，那两条一直是死的。 */ ?>
<?php /* 基因链接美化 */ ?>
.gene-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    padding: 4px 8px;
    border-radius: 4px;
    background:#f0f9ff;
}

.gene-link:hover {
    background: #dbeafe;
    color: #1d4ed8;
    text-decoration: underline;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .network-detail-header {
        padding: 20px;
    }
    
    .network-detail-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .download-card {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .results-table-container {
        margin-left: 15px;
        margin-right: 15px;
    }
    
    <?php /* 补 `table.` 前缀。原来写的是裸 `.gridtable` (0,1,0) 和 `.gridtable th`
       (0,1,1)，都低于 templatemo_style.css 里 `table.gridtable th/td` (0,1,2)
       ——媒体查询不改变特异性——所以从写下起就没生效过，小屏上表格一直是
       全站的 13.5px 和 7px/10px 留白。凑平特异性后就按这里的来。 */ ?>
    table.gridtable {
        font-size: 12px;
    }

    table.gridtable th, table.gridtable td {
        padding: 10px 8px;
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Detailed Co-expression Relationships</b></legend>
<p class="paleo-intro">Comprehensive analysis of co-expressed gene pairs and their correlation coefficients in Cnidaria species.</p>

<?php
/* $job 会被拼进 ../tmp/tmp_edge<job>.inc / tmp_node<job>.inc 这两个路径，
   而且 $fp2 是以 "w" 打开的 —— 如果放任它来自 URL，?job=../../../../...
   就成了任意的文件读写入口。所以只允许字母数字：斜杠、点、反斜杠、空字节
   都被去掉，路径穿越不可能成立。
   注意不能只留数字：作业号是 network.list.php 用 date("YMdHis") 生成的，
   形如 2026Sep17233325，中间三个字母是月份。原先只留数字会把它压成
   202617233325，于是 fopen 永远找不到 tmp_edge 文件，详情页对所有作业
   都报「结果已过期」。 */
$job     = isset($_GET['job'])     ? preg_replace('/[^0-9A-Za-z]/', '', (string)$_GET['job']) : '';
$species = isset($_GET['species']) ? (string)$_GET['species'] : '';

$fp1 = ($job !== '') ? @fopen("../tmp/tmp_edge$job.inc", "r") : false;
$fp2 = ($job !== '') ? @fopen("../tmp/tmp_node$job.inc", "w") : false;
/* 作业号为空、或临时文件已被清理：后面所有 feof()/fgets() 都会对着 false 报错，
   所以整段处理都挂在 $jobOk 上，页面改为给一句明确提示。 */
$jobOk = ($fp1 !== false);

$conn1 = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
$speciesEsc = mysqli_real_escape_string($conn1, $species);
$query = mysqli_query($conn1, "SELECT * FROM abbr WHERE abbr1 = '$speciesEsc'");
$result = $query ? mysqli_fetch_row($query) : null;

// 统计共表达关系数量
$edge_count = 0;
$node = array();

$hash = cnido_edge_hash(isset($_GET['edge']) ? $_GET['edge'] : '');
if (!$jobOk) {
    echo '<div class="gd-notice gd-warn" style="margin:20px 0">'
       . 'This co-expression result is no longer available: the job ID is missing or the '
       . 'temporary result files have been cleaned up. Please re-run the analysis from the '
       . '<a href="network.php">Network Analysis</a> page.</div>';
} elseif ($hash) {
    while (!feof($fp1)) {
        $line = fgets($fp1);
        $arr = explode("\t", $line);
        if ($hash[$arr[0]] && $hash[$arr[1]]) {
            $edge_count++;
            $node[$arr[0]] = 2;
            $node[$arr[1]] = 2;
        }
    }
} else {
    while (!feof($fp1)) {
        $line = fgets($fp1);
        $arr = explode("\t", $line);
        if ($arr[0]) {
            $edge_count++;
            $node[$arr[0]] = 2;
            $node[$arr[1]] = 2;
        }
    }
}

if ($jobOk) { rewind($fp1); } // 重置文件指针以便重新读取
?>

<?php /* 统计信息 */ ?>
<?php /* 这张卡原来不在 $jobOk 判断里，作业不存在时照样印
       「Found 0 co-expression relationships」—— 上一行刚说过结果不在了，
       下一行又给一个像模像样的统计数字，读者会当成「这个网络确实没有边」。
       失败页只留那句提示。 */ ?>
<?php if ($jobOk) { ?>
<div class="stats-card">
    <div class="stats-icon">📊</div>
    <div class="stats-text">
        Found <strong><?= $edge_count ?></strong> co-expression relationships<?php if (isset($result[0]) && $result[0] !== '') { ?> in <i><?= htmlspecialchars($result[0]) ?></i><?php } ?>
    </div>
</div>
<?php } ?>

<?php /* 下载卡片（结果文件不存在时不给死链） */ ?>
<?php if ($jobOk) { ?>
<div class="download-card">
    <div class="download-title">
        Download Co-expression Data
    </div>
    <?php /* tmp/ 整目录拒绝 HTTP（tmp/.htaccess），直链 403 —— 改走
           tmp_download.php 的按文件名白名单入口。
           新窗口：全站口径是「站内文件下载开新窗口」，这一处漏了 target ——
           点下去会顶掉当前的结果页，用户要按后退才能回来。 */ ?>
    <a href="/tmp_download.php?f=tmp_edge<?= $job ?>.inc" class="download-btn"
       target="_blank" rel="noopener">
        ⬇️ Download File
    </a>
</div>
<?php } ?>

<?php /* 结果表格容器 */ ?>
<?php /* 作业不存在时表体本来就是空的，但表头照样印出来，页面上就留下
       「有表头、没有一行」的空壳 —— 紧接在「结果已不在」的提示下面，
       看起来像表格坏了。整块跟着 $jobOk 一起出。 */ ?>
<?php if ($jobOk) { ?>
<div class="results-table-container">
    <table class="gridtable">
        <thead>
            <tr>
                <th><strong>Gene A</strong></th>
                <th><strong>Gene B</strong></th>
                <th title="Pearson correlation coefficient between the two genes&rsquo; expression profiles across the species&rsquo; RNA-seq samples &mdash; +1 perfectly together, 0 unrelated, &minus;1 perfectly opposite. Its sign is what decides whether a pair appears in the positive or the negative table."><strong>PCC</strong></th>
                <th title="Mutual rank &mdash; a rank-based score, so its scale does not move with the correlation value the way PCC does. Each gene&rsquo;s partners are ordered by correlation and the two positions are combined as MR = &radic;(rankA &times; rankB), where rankA is where gene B sits in gene A&rsquo;s ordered list and vice versa. A low MR means each gene is near the top of the other&rsquo;s list. MR is the more robust of the two when expression is noisy."><strong>MR</strong></th>
                <th><strong>Relationship</strong></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $hash = cnido_edge_hash(isset($_GET['edge']) ? $_GET['edge'] : '');
            if (!$jobOk) {
                // 结果文件不存在时上面已经给出提示，这里不再输出表格行
            } elseif ($hash) {
                rewind($fp1);
                while (!feof($fp1)) {
                    $line = fgets($fp1);
                    $arr = explode("\t", $line);
                    if ($hash[$arr[0]] && $hash[$arr[1]]) {
                        echo "<tr align='center'>";
                        echo "<td><a href=\"../gene_detail.php?species={$result[0]}&gene={$arr[0]}\" class=\"gene-link\">{$arr[0]}</a></td>";
                        echo "<td><a href=\"../gene_detail.php?species={$result[0]}&gene={$arr[1]}\" class=\"gene-link\">{$arr[1]}</a></td>";
                        echo "<td>{$arr[2]}</td>";
                        echo "<td>{$arr[3]}</td>";
                        echo "<td>{$arr[4]}</td>";
                        echo "</tr>";
                    }
                }
            } else {
                rewind($fp1);
                while (!feof($fp1)) {
                    $line = fgets($fp1);
                    $arr = explode("\t", $line);
                    if ($arr[0]) {
                        echo "<tr align='center'>";
                        echo "<td><a href=\"../gene_detail.php?species={$result[0]}&gene={$arr[0]}\" class=\"gene-link\">{$arr[0]}</a></td>";
                        echo "<td><a href=\"../gene_detail.php?species={$result[0]}&gene={$arr[1]}\" class=\"gene-link\">{$arr[1]}</a></td>";
                        echo "<td>{$arr[2]}</td>";
                        echo "<td>{$arr[3]}</td>";
                        echo "<td>{$arr[4]}</td>";
                        echo "</tr>";
                    }
                }
            }
            ?>
        </tbody>
    </table>
</div>
<?php } ?>

<?php
// 写入节点文件（结果文件不存在时 $fp1/$fp2 都是 false，不要碰）
if ($jobOk) {
    foreach ($node as $geneid => $age) {
        $gene = "$geneid\n";
        fwrite($fp2, $gene);
    }
    fclose($fp1);
    fclose($fp2);
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
