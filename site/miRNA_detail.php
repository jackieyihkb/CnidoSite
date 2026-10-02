<?php
/* 审稿意见 Referee 3 点 10：下载列表里出现了服务器上并不存在的文件
 * （<sp>-all.bed / <sp>-all.gff），点了只会拿到一个 0 字节的「成功」下载。
 * 现在候选文件直接扫描 download/ 目录生成，磁盘上没有的不会再出现在下拉框里。 */
require_once __DIR__ . '/includes/state.php';

/* 热图色阶是连续的 蓝—白—红（两端 #0000ff/#ff0000，中间经过白）。
 * 原来每格一律 color:white，于是浅格里（#d2d2ff、#ffaeae 这类）数字对比度只有 1.2–1.8，
 * 等于看不见。深灰 #1e293b 也救不了中段（最好只有 4.44），但纯黑可以：全色阶扫一遍，
 * 「白/黑取对比更高者」最低 4.56，且不必改色阶本身（图表外观不变）。 */
if (!function_exists('mirna_heat_fg')) {
    function mirna_heat_fg($r, $g, $b)
    {
        $lin = function ($v) {
            $v = $v / 255;
            return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        };
        $L = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
        $withWhite = 1.05 / ($L + 0.05);        // 白字 (L=1.0)
        $withBlack = ($L + 0.05) / 0.05;        // 黑字 (L=0.0)
        return $withWhite >= $withBlack ? '#ffffff' : '#000000';
    }
}

/* 「没有这个值」的统一写法：灰色破折号 + 悬停说明。
 *
 * 库里的缺失值是字面量字符串 "None"，不是 NULL —— 全库 241 格：
 *   MiRBase_ID   78（asp/hvu/adi 三个物种是**整列**都是它，nve 只有 2 行）
 *   5p_accession 81 / 3p_accession 82（同样是三个物种整列 + nve 若干行）
 * 原样印出来，读者看到的是一大片写着 "None" 的格子 —— 既不像缺失、也不像有值，
 * 而像是这些 miRNA 叫 "None"。MirGeneDB 自己的页面上这两种情况分别写
 * "MiRBase ID: No" 和空着的登录号，也就是说上游确实没有，不是本站导入丢的
 * （已核对 https://www.mirgenedb.org/show/asp/Mir-10）。 */
if (!function_exists('mirna_na')) {
    function mirna_na($why)
    {
        return "<span class='mirna-na' title='"
             . htmlspecialchars($why, ENT_QUOTES, 'UTF-8') . "'>&ndash;</span>";
    }
}

/* 5p / 3p 登录号单元格：有登录号时给 MirGeneDB 该基因的 5p/3p 锚点链接，
   没有时落到上面的破折号（没有登录号时锚点本来就指不到东西）。 */
if (!function_exists('mirna_acc_cell')) {
    function mirna_acc_cell($value, $url)
    {
        $v = trim((string)$value);
        if ($v === '' || strcasecmp($v, 'None') === 0 || $v === '-') {
            return mirna_na('MirGeneDB records no accession for this arm of the hairpin');
        }
        return "<a href=\"" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
             . "\" target='_blank' class='mirna-link'>" . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . "</a>";
    }
}

/* 普通文本单元格（MiRBase ID、Family、Seed 这类）：值是缺失哨兵时给破折号，
   否则原样转义输出。注意不能因为「有的物种整列都缺」就把整列去掉 ——
   asp/hvu/adi 的 MiRBase ID 确实全缺，但那是这条记录的真实状态，
   读者需要看见「这里没有号」，而不是看不见这一列。 */
if (!function_exists('mirna_val_cell')) {
    function mirna_val_cell($value, $why)
    {
        $v = trim((string)$value);
        if ($v === '' || strcasecmp($v, 'None') === 0) {
            return mirna_na($why);
        }
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}

$species = isset($_GET['species']) ? $_GET['species']
         : (isset($_POST['species']) ? $_POST['species'] : '');
if (!preg_match('/^[A-Za-z0-9_]+$/', $species)) { $species = 'nve'; }   // 用于拼表名/文件名，限定字符集

// 扫描该物种实际存在的下载文件
$__files = array();
$__labels = array(
    'pre.fas'         => 'Precursor sequence',
    'mature.fas'      => 'Mature sequence',
    'star.fas'        => 'Star sequence',
    'loop.fas'        => 'Loop sequence',
    '5p.fas'          => '5p sequence',
    '3p.fas'          => '3p sequence',
    'tissueItems.txt' => 'Expression matrix',
);
$__dir = __DIR__ . '/download/';
foreach ((array)@scandir($__dir) as $f) {
    if (strpos($f, $species . '-') !== 0) { continue; }
    $suf = substr($f, strlen($species) + 1);
    $__files[] = array('file' => $f, 'label' => isset($__labels[$suf]) ? $__labels[$suf] : $suf);
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
<title>miRNA Details - CnidoSite</title>
<meta name="keywords" content="Cnidaria, miRNA, microRNA, detailed annotation, expression" />
<meta name="description" content="Detailed miRNA annotation and expression data for selected Cnidaria species" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<style>
<?php /* miRNA详情页面专用样式 - 不影响全局CSS */ ?>
.mirna-detail-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.mirna-detail-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.mirna-detail-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.species-badge {
    background:linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

<?php /* 下载区域美化 */ ?>
.download-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 20px 25px;
    margin: 25px 0;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.download-controls {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.file-selector {
    padding: 12px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 15px;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 200px;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 20px;
}

.file-selector:focus {
    outline: none;
    border-color:#6d28d9;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
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
}

<?php /* 表达矩阵颜色优化 */ ?>
.expression-cell {
    font-weight: 600;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.expression-cell:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

<?php /* 链接样式 */ ?>
.mirna-link {
    color:#6d28d9;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.mirna-link:hover {
    color: #7c3aed;
    text-decoration: underline;
}

<?php /* 库里没有登录号时的占位符（见 mirna_acc_cell()）。做成淡淡的灰，
   一眼能和旁边的真登录号区分开，而不是一个看起来像数据的字。 */ ?>
.mirna-na {
    color:#94a3b8;
    cursor: help;
}

<?php /* 表头上第二行的 SRA 登录号。有的研究同一个组织做了两份文库（pooled / single colony
   各重复一次），样本名一样、只有登录号能区分，所以它必须是表头的一部分而不是 title。 */ ?>
.mirna-srr {
    display: block;
    font-weight: 400;
    font-size: 11px;
    color:#64748b;
    letter-spacing: 0.02em;
}

<?php /* 表格下方关于表达量列的口径说明（与 tutorial.php 的 miRNA 那一节同一套说法）。 */ ?>
.mirna-note {
    margin: 14px 0 0;
    padding: 12px 16px;
    background: #f8fafc;
    border-left: 3px solid #94a3b8;
    border-radius: 6px;
    color: #475569;
    font-size: 16px;
    line-height: 1.7;
}

.mirna-note b { color: #1e293b; }

<?php /* 前三列（MirGeneDB ID / MiRBase ID / Family）的标签里都带连字符，而 gridtable 的
   `td` 是 word-wrap:break-word、`th` 是 nowrap，于是只有数据格会在连字符处断行：
   `Adi-Mir-2023-P1-v1` 拆成两行、`ADI-NOVEL-1` 拆成 `ADI-` / `NOVEL-` / `1` 三行、
   `nve-mir-2032a` 拆成两行，整张表一行高矮不齐。这三列的数据格改成不换行。
   表格本身已经有 .gt-scroll 横滑兜底（见本文件末尾那段），加宽不会把内容裁掉。 */ ?>
table.gridtable td:nth-child(1),
table.gridtable td:nth-child(2),
table.gridtable td:nth-child(3) {
    white-space: nowrap;
}

.jbrowse-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.jbrowse-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .mirna-detail-header {
        padding: 20px;
    }
    
    .mirna-detail-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .download-section {
        flex-direction: column;
        align-items: stretch;
    }
    
    .download-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .file-selector {
        min-width: 100%;
    }
}

<?php /* ---------------------------------------------------------------------------
   桌面档的横滑兜底 —— 2026-09-28 补

   症状：这张表 16 列，自然宽度 1680px（1440 视口下容器只有 1398px）。外层
   `.table-container` 带 `overflow:hidden`（templatemo_style.css:775，为圆角而加），
   于是**最右边的 primary-polyp 列被直接裁掉**，数字只露出半截，而且怎么拖都够不着
   —— 不是"要横滑才能看到"，是根本看不到。

   为什么原来没兜住：`.gt-scroll{overflow-x:auto}` 只写在 templatemo_style.css 的
   `@media (max-width:1200px)` 里，桌面档那条规则完全不生效；rwd-tables.js 虽然
   照常套了 .gt-scroll 壳，但在 ≥1201 时这个壳不滚，裁的还是外层。
   本页还有一个 `<div class="table-scroll-container">`（第 392 行），但本页**从未
   定义过这个类**（别的页面各自在自己的 <style> 里定义），所以它也是空转。

   必须包在 `@media (min-width:1201px)` 里：rwd-tables.js 的 alreadyScrollable()
   只要看到祖先的 overflow-x 是 auto/scroll 就跳过套壳，无条件加这条会静默关掉
   窄屏那套 width:max-content 横滑（与 ChIP_analysis.php / MAGs.php 同一个坑）。
   窄屏（≤1200）维持原样，不受影响。
   --------------------------------------------------------------------------- */ ?>
@media (min-width: 1201px) {
    .gt-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        overscroll-behavior-x: contain;
    }
}
</style>

<script>
function downloadFile() {
    var selectElement = document.getElementById("fileSelect");
    var selectedFile = selectElement.options[selectElement.selectedIndex].value;
    
    // Redirect to the selected file for download
    window.location = "download_fun.php?download=download&fname=" + selectedFile;
}
</script>
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
            <li><a href="#" class="current">Epigenome</a>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>miRNA Details</b></legend>
<p  class="paleo-intro">Comprehensive miRNA annotation including precursor, mature, star, loop sequences and tissue-specific expression profiles.</p>

<?php
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
/* $species 来自 URL/表单，两处都要转义（原来只有第一处转了）。 */
$speciesEsc = mysqli_real_escape_string($conn, (string)$species);
$query  = mysqli_query($conn, "SELECT * FROM mirna_metadata WHERE abbr = '$speciesEsc'");
$query1 = mysqli_query($conn, "SELECT count(*) FROM mirna_metadata WHERE abbr = '$speciesEsc'");
$result1 = mysqli_fetch_row($query1);

if ($result1[0] > 0) {
    $result = mysqli_fetch_row($query);
    /* $result 在下面每个物种的打印循环里会被覆盖（mysqli_data_seek 之后重新 fetch），
       所以物种名在这里先留一份给表格下方那段说明用。 */
    $__spName = $result[0];
    echo "<div class=\"species-badge\">🧬 Selected Species: <strong><i>$result[0]</i></strong> • " . $result1[0] . " miRNA records found</div>";
    ?>

    <?php /* 下载区域 */ ?>
    <div class="download-section">
        <div style="font-size: 16px; font-weight: 600; color: #1e293b;">
            📥 Download miRNA Data Files
        </div>
        <div class="download-controls">
            <select id="fileSelect" class="file-selector">
                <?php if (empty($__files)) { ?>
                    <option value="">No file available for this species</option>
                <?php } else {
                    foreach ($__files as $__f) {
                        echo '<option value="' . htmlspecialchars($__f['file']) . '">'
                           . htmlspecialchars($__f['label']) . '</option>';
                    }
                } ?>
            </select>
            <button class="download-btn" onClick="downloadFile()">
                ⬇️ Download Selected File
            </button>
        </div>
    </div>

    <?php /* 表格容器 */ ?>
    <div class="table-container">
        <div class="table-scroll-container">
            <table class="gridtable">
                <?php
                if ($species == "nve") {
                    echo "<tr><th width='6%'>MirGeneDB ID</th><th>MiRBase ID</th><th>Family</th><th>Seed</th><th>5p accession</th><th>3p accession</th><th width='6%'>Chromosome</th><th>Start</th><th>End</th><th>Strand</th><th width='3%'>adult-female</th><th width='3%'>adult-male</th><th width='3.5%'>blastula</th><th width='3.5%'>juvenile</th><th width='3.5%'>late-planula</th><th width='3.5%'>primary-polyp</th></tr>";
                    
                    mysqli_data_seek($query, 0);
                    while ($result = mysqli_fetch_row($query)) {
                        $mirna = explode('Nve-', $result[2]);
                        $minValue = "0";
                        $maxValue = "532843.72";
                        
                        $p1 = mirna_acc_cell($result[6], "https://www.mirgenedb.org/show/nve/{$mirna[1]}#5p");
                        $p2 = mirna_acc_cell($result[7], "https://www.mirgenedb.org/show/nve/{$mirna[1]}#3p");

                        echo "<tr align='center'><td><a href=\"https://www.mirgenedb.org/show/nve/{$mirna[1]}\" target='_blank' class='mirna-link'>{$result[2]}</a></td><td>" . mirna_val_cell($result[3], 'MirGeneDB records no miRBase identifier for this entry') . "</td><td>{$result[4]}</td><td>{$result[5]}</td><td>{$p1}</td><td>{$p2}</td><td>{$result[8]}</td><td>{$result[9]}</td><td>{$result[10]}</td><td>{$result[11]}</td>";
                        
                        for ($i = 12; $i <= 17; $i++) {
                            $value = $result[$i];
                            $normalized = ($maxValue - $minValue) ? ($value - $minValue) / ($maxValue - $minValue) : 0.5;
                            
                            if ($normalized < 0.5) {
                                $r = intval(255 * $normalized * 2);
                                $g = intval(255 * $normalized * 2);
                                $b = 255;
                            } else {
                                $r = 255;
                                $g = intval(255 * (1 - ($normalized - 0.5) * 2));
                                $b = intval(255 * (1 - ($normalized - 0.5) * 2));
                            }
                            
                            $color = sprintf("#%02x%02x%02x", $r, $g, $b);
                            echo "<td class=\"expression-cell\" style=\"background-color: {$color}; color: " . mirna_heat_fg($r, $g, $b) . "; font-size:12px;\">" . number_format($value, 0) . "</td>";
                        }
                        echo "</tr>";
                    }
                }
                
                if ($species == "asp") {
                    echo "<tr><th width='6%'>MirGeneDB ID</th><th>MiRBase ID</th><th>Family</th><th>Seed</th><th>5p accession</th><th>3p accession</th><th width='6%'>Chromosome</th><th>Start</th><th>End</th><th>Strand</th><th width='3%'>tentacle1</th><th width='3%'>tentacle2</th><th width='3.5%'>trunk1</th><th width='3.5%'>trunk2</th></tr>";
                    
                    mysqli_data_seek($query, 0);
                    while ($result = mysqli_fetch_row($query)) {
                        $mirna = explode('Asp-', $result[2]);
                        $minValue = "0";
                        $maxValue = "828347.2";
                        
                        echo "<tr align='center'><td><a href=\"https://www.mirgenedb.org/show/asp/{$mirna[1]}\" target='_blank' class='mirna-link'>{$result[2]}</a></td><td>" . mirna_val_cell($result[3], 'MirGeneDB records no miRBase identifier for this entry') . "</td><td>{$result[4]}</td><td>{$result[5]}</td><td>" . mirna_acc_cell($result[6], "https://www.mirgenedb.org/show/{$species}/{$mirna[1]}#5p") . "</td><td>" . mirna_acc_cell($result[7], "https://www.mirgenedb.org/show/{$species}/{$mirna[1]}#3p") . "</td><td>{$result[8]}</td><td>{$result[9]}</td><td>{$result[10]}</td><td>{$result[11]}</td>";
                        
                        for ($i = 12; $i <= 15; $i++) {
                            $value = $result[$i];
                            $normalized = ($maxValue - $minValue) ? ($value - $minValue) / ($maxValue - $minValue) : 0.5;
                            
                            if ($normalized < 0.5) {
                                $r = intval(255 * $normalized * 2);
                                $g = intval(255 * $normalized * 2);
                                $b = 255;
                            } else {
                                $r = 255;
                                $g = intval(255 * (1 - ($normalized - 0.5) * 2));
                                $b = intval(255 * (1 - ($normalized - 0.5) * 2));
                            }
                            
                            $color = sprintf("#%02x%02x%02x", $r, $g, $b);
                            echo "<td class=\"expression-cell\" style=\"background-color: {$color}; color: " . mirna_heat_fg($r, $g, $b) . "; font-size:12px;\">" . number_format($value, 0) . "</td>";
                        }
                        echo "</tr>";
                    }
                }
                
                if ($species == "hvu") {
                    echo "<tr><th width='6%'>MirGeneDB ID</th><th>MiRBase ID</th><th>Family</th><th>Seed</th><th>5p accession</th><th>3p accession</th><th width='6%'>Chromosome</th><th>Start</th><th>End</th><th>Strand</th><th width='5%'>foot-regenerating-head-2d</th><th width='5%'>foot-regenerating-head-3d</th><th width='5%'>foot-regenerating-head-3h</th><th width='3.5%'>uncut-set</th></tr>";
                    
                    mysqli_data_seek($query, 0);
                    while ($result = mysqli_fetch_row($query)) {
                        $mirna = explode('Hvu-', $result[2]);
                        $minValue = "0";
                        $maxValue = "138283.18";
                        
                        echo "<tr align='center'><td><a href=\"https://www.mirgenedb.org/show/hvu/{$mirna[1]}\" target='_blank' class='mirna-link'>{$result[2]}</a></td><td>" . mirna_val_cell($result[3], 'MirGeneDB records no miRBase identifier for this entry') . "</td><td>{$result[4]}</td><td>{$result[5]}</td><td>" . mirna_acc_cell($result[6], "https://www.mirgenedb.org/show/{$species}/{$mirna[1]}#5p") . "</td><td>" . mirna_acc_cell($result[7], "https://www.mirgenedb.org/show/{$species}/{$mirna[1]}#3p") . "</td><td>{$result[8]}</td><td>{$result[9]}</td><td>{$result[10]}</td><td>{$result[11]}</td>";
                        
                        for ($i = 12; $i <= 15; $i++) {
                            $value = $result[$i];
                            $normalized = ($maxValue - $minValue) ? ($value - $minValue) / ($maxValue - $minValue) : 0.5;
                            
                            if ($normalized < 0.5) {
                                $r = intval(255 * $normalized * 2);
                                $g = intval(255 * $normalized * 2);
                                $b = 255;
                            } else {
                                $r = 255;
                                $g = intval(255 * (1 - ($normalized - 0.5) * 2));
                                $b = intval(255 * (1 - ($normalized - 0.5) * 2));
                            }
                            
                            $color = sprintf("#%02x%02x%02x", $r, $g, $b);
                            echo "<td class=\"expression-cell\" style=\"background-color: {$color}; color: " . mirna_heat_fg($r, $g, $b) . "; font-size:12px;\">" . number_format($value, 0) . "</td>";
                        }
                        echo "</tr>";
                    }
                }
                
                if ($species == "adi") {
                    /* 表头名称取自 download/adi-tissueItems.txt 的列名（那份矩阵就是这 9 列，
                       顺序一致，见下一段循环 12..20）。原先 9 个表头里有 5 组重名 —— 同一个
                       "adult-24h-pooled-colony" 印两遍 —— 而两列的数值并不相同（是两个独立文库），
                       读者无法知道哪一列是哪一个样本。加 SRA 登录号区分。
                       拼写照抄源文件：pooled 的是 colonies（复数），single 的是 colony。 */
                    echo "<tr><th width='6%'>MirGeneDB ID</th><th>MiRBase ID</th><th>Family</th><th>Seed</th><th>5p accession</th><th>3p accession</th><th width='6%'>Chromosome</th><th>Start</th><th>End</th><th>Strand</th>"
                       . "<th width='7%'>adult-24h-pooled-colonies<span class='mirna-srr'>SRR5223641</span></th>"
                       . "<th width='7%'>adult-24h-pooled-colonies<span class='mirna-srr'>SRR5223642</span></th>"
                       . "<th width='7%'>adult-24h-single-colony<span class='mirna-srr'>SRR5223643</span></th>"
                       . "<th width='7%'>adult-24h-single-colony<span class='mirna-srr'>SRR5223644</span></th>"
                       . "<th width='7%'>adult-4h-pooled-colonies<span class='mirna-srr'>SRR5223645</span></th>"
                       . "<th width='7%'>adult-4h-pooled-colonies<span class='mirna-srr'>SRR5223646</span></th>"
                       . "<th width='7%'>adult-4h-single-colony<span class='mirna-srr'>SRR5223647</span></th>"
                       . "<th width='6%'>larvae-24h<span class='mirna-srr'>SRR5223649</span></th>"
                       . "<th width='6%'>larvae-24h<span class='mirna-srr'>SRR5270328</span></th></tr>";
                    
                    mysqli_data_seek($query, 0);
                    while ($result = mysqli_fetch_row($query)) {
                        $mirna = explode('Adi-', $result[2]);
                        $minValue = "0";
                        $maxValue = "348311.92";
                        
                        echo "<tr align='center'><td><a href=\"https://www.mirgenedb.org/show/adi/{$mirna[1]}\" target='_blank' class='mirna-link'>{$result[2]}</a></td><td>" . mirna_val_cell($result[3], 'MirGeneDB records no miRBase identifier for this entry') . "</td><td>{$result[4]}</td><td>{$result[5]}</td><td>" . mirna_acc_cell($result[6], "https://www.mirgenedb.org/show/{$species}/{$mirna[1]}#5p") . "</td><td>" . mirna_acc_cell($result[7], "https://www.mirgenedb.org/show/{$species}/{$mirna[1]}#3p") . "</td><td>{$result[8]}</td><td>{$result[9]}</td><td>{$result[10]}</td><td>{$result[11]}</td>";
                        
                        for ($i = 12; $i <= 20; $i++) {
                            $value = $result[$i];
                            $normalized = ($maxValue - $minValue) ? ($value - $minValue) / ($maxValue - $minValue) : 0.5;
                            
                            if ($normalized < 0.5) {
                                $r = intval(255 * $normalized * 2);
                                $g = intval(255 * $normalized * 2);
                                $b = 255;
                            } else {
                                $r = 255;
                                $g = intval(255 * (1 - ($normalized - 0.5) * 2));
                                $b = intval(255 * (1 - ($normalized - 0.5) * 2));
                            }
                            
                            $color = sprintf("#%02x%02x%02x", $r, $g, $b);
                            echo "<td class=\"expression-cell\" style=\"background-color: {$color}; color: " . mirna_heat_fg($r, $g, $b) . "; font-size:12px;\">" . number_format($value, 0) . "</td>";
                        }
                        echo "</tr>";
                    }
                }
                ?>
            </table>
        </div>
    </div>

    <?php /* 表达量列的说明。tutorial.php 的 miRNA 一节已经讲了口径，这里给的是
             看完表之后立刻需要知道的那三件事：列是这份研究的样本（不是通用的组织名）、
             同一组织可能有两份文库（表头第二行是登录号）、以及数值不可跨物种比。
             刻意不写具体单位：MirGeneDB 的 tissueItems.txt 只给数值不给单位说明，
             而这些值按列求和是 0.5–1.8 × 10^6，本身就排除了「每列 10^6 的 RPM」这一种读法。 */ ?>
    <p class="mirna-note">
      <b>About the expression columns.</b> The columns to the right of <i>Strand</i> are the
      samples of the study that profiled
      <i><?= htmlspecialchars((string)$__spName, ENT_QUOTES, 'UTF-8') ?></i>, so they differ from
      species to species &mdash; column 11 is a different sample depending on which species you
      selected.
      Where a sample name is repeated, the study made two libraries of the same tissue; the SRA
      accession on the second header line tells them apart, and the two columns hold different
      numbers. The values are that study&rsquo;s own expression values, shaded by colour against the
      largest value in this table, so they show <i>which microRNAs are prominent in a sample</i>
      rather than an absolute abundance, and they are <b>not comparable between species</b>.
      A dash means MirGeneDB records no value there &mdash; in the <i>MiRBase ID</i> column for
      entries miRBase does not list (for three of the four species, every entry), and in the
      <i>5p</i> or <i>3p</i> column for the arms of the hairpin that have no accession. Hover a
      dash to see which case it is.
    </p>

<?php
} else {
    echo "<script>alert(\"No miRNA matched that search. Please check the term and try again.\");</script>";
    echo "<script>window.location =\"miRNA_analysis.php\";</script>";
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
