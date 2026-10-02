<?php
/* cnido_latin_loose()（库文件名 → 拉丁学名）与 cnido_species_map_seqs()
   （subject ID → 物种）都在这里。blast.php 已经 require 了同一份，两条路径
   显示的名字因此天然一致。 */
require_once __DIR__ . '/../includes/state.php';

// ========== 安全改进①：输入校验与白名单 ==========
function only_digits($v) { return preg_replace('/[^0-9]/', '', $v); }
function only_word($v) { return preg_replace('/[^a-zA-Z0-9_.-]/', '', $v); }
function only_alpha($v) { return preg_replace('/[^a-zA-Z]/', '', $v); }
function only_num($v) { return preg_replace('/[^0-9]/', '', $v); }
function only_evalue($v) { return preg_replace('/[^0-9eE.-]/', '', $v); }

/**
 * 「全部数据库」模式下实际要搜的库清单（`db/<名字>` 形式，已排序）。
 *
 * 名单一律从 blast/db/ 的索引文件现读（.pdb = 蛋白库、.ndb = 核苷酸库），
 * 不接受表单里的任何值 —— 它会被拼进命令行，是这条链路上唯一不能来自
 * 用户输入的东西。库数也从这里来，不写死，新增物种会自动跟上。
 */
function blast_all_db_list($db)
{
    $suf  = ($db === '__ALL_PEP__') ? '.pdb' : '.ndb';
    $list = array();
    foreach ((array)@scandir(__DIR__ . '/db') as $f) {
        if (substr($f, -4) === $suf) { $list[] = 'db/' . substr($f, 0, -4); }
    }
    sort($list);
    return $list;
}

/* GET 分支（?qnum=&db=）不跑 BLAST，这些变量不会被赋值；站内没有任何链接
   这么用，属历史遗留，但手改地址栏能走到，默认值保证渲染不报 Undefined。 */
$db = ''; $program = ''; $dbIsAll = false; $dbAllCount = 0; $blastRc = 0;

if(isset($_GET['qnum'])){
    $qnum = only_digits($_GET['qnum']);
    $db = isset($_GET['db']) ? only_word($_GET['db']) : '';
} else {
    ### read parameters
    $qnum = rand(10000, 99999);
    $program = only_alpha($_POST['program'] ?? '');
    $db = only_word($_POST['datalib'] ?? '');
    $query = trim($_POST['query'] ?? '');
    $evalue = only_evalue($_POST['evalue'] ?? '');
    $matrix = only_word($_POST['matrix'] ?? '');
    $descriptions = only_num($_POST['descriptions'] ?? '');
    $alignments = only_num($_POST['alignments'] ?? '');
    $ungapalign = $_POST['ungapalign'] ?? '';
    $overview = $_POST['overview'] ?? '';

    // 验证必要参数
    $allowed_programs = ['blastp','blastn','blastx','tblastn','tblastx'];
    if (!in_array($program, $allowed_programs, true)) { $program = 'blastp'; }
    $allowed_matrices = ['BLOSUM62','BLOSUM45','BLOSUM80','PAM30','PAM70','PAM250'];
    if (!in_array($matrix, $allowed_matrices, true)) { $matrix = 'BLOSUM62'; }

    $tmpfile = fopen("../tmp/$qnum.seq", 'w');
    if ($tmpfile) {
        fwrite($tmpfile, $query);
        fclose($tmpfile);
    }

    if (is_uploaded_file($_FILES['file']['tmp_name'])){
        if ($_FILES['file']['size'] > 5000000){
            fopen("../tmp/$qnum.BigFileError", 'w');
        } else {
            move_uploaded_file($_FILES['file']['tmp_name'], "../tmp/$qnum.seq");
        }
    }
    if ($ungapalign) {
        $val_one="F";
    } else {
        $val_one="T";
    }
    if ($overview) {
        $val_two=11;
    } else {
        $val_two=0;
    }

    /* 「全部数据库」（Referee 3 point 11(b)）：
       legacy blastall 的 -d 接受以空格分隔的库列表，所以一次调用就能覆盖
       某个程序族的全部库，不必循环几百次。库名从 blast/db/ 的索引文件读出，
       不接受任何来自表单的值，因此不会成为命令注入的入口。 */
    $dbIsAll   = ($db === '__ALL_PEP__' || $db === '__ALL_NUC__');
    $dbTarget  = "db/$db";
    $dbAllCount = 0;
    if ($dbIsAll) {
        $__list     = blast_all_db_list($db);
        $dbAllCount = count($__list);
        $dbTarget   = implode(' ', $__list);
    }

    /* 线程数（2026-09-27 加）—— 只在「全部数据库」时并行。
       单选一个物种本来就 1 秒上下，开线程只会白占核心；全库是 148 个库的
       顺序扫描，是唯一值得并行的地方。legacy_blast 把 legacy 的 `-a` 映射成
       BLAST+ 的 `-num_threads`（/usr/bin/legacy_blast 里 num_threads 的四处
       赋值取的都是 $opt_a），所以这里必须传 `-a`，传 `-num_threads` 它不认。
       取 4 是保守值：本机 20 核，但长期 load 20+，而每个并发用户都会各拿 4 条。 */
    $blastThreads = $dbIsAll ? 4 : 1;

    ### start blast — 安全改进②：escapeshellarg 防命令注入
    /* 这里**必须**调用 /usr/bin/legacy_blast 而不是 /usr/bin/blastall。
       两者看似等价（/usr/bin/blastall 就是 `exec legacy_blast blastall $@`），
       但那个包装脚本里的 $@ **没有加引号**，会做一次分词：上面用
       escapeshellarg() 精心拼成「一个带空格的参数」的库列表，到了脚本里被
       重新拆成 `-d db/第一个库` 加一堆位置参数，GetOptions 只取到第一个库、
       其余静默丢弃。后果是「全部数据库」从上线起就一直只搜一个库 ——
       排序后第一个是 Acropora_acuminata.pep，于是任何查询的结果都只有
       Acropora acuminata，查不到它的同源序列时还会显示
       "No Similar Sequences Found"，把一个假阴性说成科学结论。
       2026-09-21 实测：经 blastall 传 146 个库 → 54 条命中、全部 aacu；
       经 legacy_blast 同样参数 → 549 条命中、跨 245 个物种前缀。
       单选某个物种不受影响（库名里没有空格，拆不开），所以只有「全部」错。
       不要为了「看起来更标准」改回 /usr/bin/blastall。 */
    $cmd1 = "/usr/bin/legacy_blast blastall"
        . " -p " . escapeshellarg($program)
        . " -i " . escapeshellarg("../tmp/$qnum.seq")
        . " -d " . escapeshellarg($dbTarget)
        . " -a " . escapeshellarg($blastThreads)
        . " -e " . escapeshellarg($evalue)
        . " -F F"
        . " -m " . escapeshellarg($val_two)
        . " -g " . escapeshellarg($val_one)
        . " -v " . escapeshellarg($descriptions)
        . " -b " . escapeshellarg($alignments)
        . (($program === 'blastn') ? '' : " -M " . escapeshellarg($matrix))
        . " -m 8"
        . " -o " . escapeshellarg("../tmp/$qnum.tabular.txt");
    /* blastn 没有打分矩阵参数，传了 -M 会让 blastall 直接报
       "Unknown argument: matrix" 并中止 —— 这是 blastn / tblastn / tblastx
       三条链路此前一直拿不到结果的原因。下面记录退出码，好在结果页说明。 */
    $blastRc = 0;
    system($cmd1, $blastRc);
}

/* ---------------------------------------------------------------- 检索范围标注
 *
 * 结果页第一件事是说清「搜的是什么」，因为同一份表格在两种模式下含义不同：
 * 选了单个物种时每一条命中都属于它；选了「全部数据库」时每条命中的物种都
 * 不一样，必须逐条标出来。
 *
 * 单个物种：库文件名（Nematostella_vectensis.pep）只是数据键，对读者没有意义，
 * 显示拉丁学名。用的 cnido_latin_loose() 跟 blast.php 下拉框是同一个函数，
 * 页面上的名字因此和用户在下拉框里选的那个字字相同。
 *
 * 「全部数据库」：tabular 输出（-m 8）**不带库名**，所以物种只能拿 subject ID
 * 反查物种表，具体在下面表格那一段（cnido_species_map_seqs）。
 *
 * $db 在 GET 分支也要能显示，所以这里重新判定一次，不依赖 POST 分支的值。 */
$dbIsAll = ($db === '__ALL_PEP__' || $db === '__ALL_NUC__');
$dbLatin = '';
$dbLabel = $db;
if ($dbIsAll) {
    if (!$dbAllCount) { $dbAllCount = count(blast_all_db_list($db)); }
    $dbLabel = 'All ' . (int)$dbAllCount . ' ' . ($db === '__ALL_PEP__' ? 'protein' : 'nucleotide') . ' databases';
} elseif ($db !== '') {
    $dbLatin = cnido_latin_loose(preg_replace('/\.[A-Za-z0-9]+$/', '', $db));
    if ($dbLatin !== '') { $dbLabel = $dbLatin; }
}

// 安全输出函数
function safe($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta charset="utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>BLAST Results - CnidoSite</title>
<meta name="keywords" content="Cnidaria, BLAST results, sequence alignment, homology search" />
<meta name="description" content="BLAST search results showing sequence similarities and alignments" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />

<style>
<?php /* BLAST结果页面专用样式 - 不影响全局CSS */ ?>
.blast-result-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.blast-result-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.blast-result-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.query-badge {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 16px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    <?php /* 这个徽章是 inline-flex，三行 "Query ID / Program / Database" 里
       最后一行最长时整块 357px，比 333px 的栏多出 3px —— 只有 3px，
       但它足以在手机上拉出一条横向滚动条，让整页能左右拖动。
       封顶 + 允许换行，宽屏下够不着，不生效。 */ ?>
    max-width: 100%;
    box-sizing: border-box;
    flex-wrap: wrap;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

<?php /* 表格容器的白卡片样式已提到 templatemo_style.css 的 .table-container，
   全站一份（本页原来也抄了一份逐字相同的）。这里只留本页特有的滚动区，
   以及下面 media query 里收窄页边距的那条。 */ ?>
.table-scroll-container {
    max-height: 700px;
    overflow-y: auto;
    overflow-x: auto;
}


<?php /* 数值列不许折行。
   `table.gridtable` 带 `word-wrap:break-word`（本意是让超长登录号自己断开，
   别把表撑破），于是 `4.71e-175` 这种 9 个字符的 token 也会被从中间劈开。
   加了 Species 列之后 E-value 列从 91px 被挤到 83px，正好越过临界点，
   首行就变成两行高、整行 62px（其余行 38px）—— 一个数字被拆成两行。
   这些列本来就是短 token，不折行只是让表更宽，而容器本来就横向滚动。
   Subject ID 不在此列：登录号确实可能很长，那一种该断就断。 */ ?>
table.gridtable td.blast-num {
    white-space: nowrap;
}

<?php /* 「全部数据库」模式下的 Species 列。
   一个号只属于一个物种是常态；列出多个是因为号跨物种撞名，而引擎的 tabular
   输出不带库名 —— 不能只显示一个。这些名字比其它列长，整列不换行，让
   .table-scroll-container 横向滚动，而不是把每个双名切成两半。
   行高必须在本元素上写死：body 的 line-height 会继承进 td（见全站表格统一那轮）。 */ ?>
.blast-sp-one,
.blast-sp-multi {
    white-space: nowrap;
}
<?php /* 必须带上 table.gridtable 才压得住 `table.gridtable td{line-height:1.55}`
   （0,1,2）—— 单个类名是 (0,1,0)，一声不响地输掉，行高就还是 1.55。
   多物种那格有两到六行，行高差一点点，整行高度差一倍。 */ ?>
table.gridtable td.blast-sp-multi {
    font-size: 13px;
    line-height: 1.35;
}
.blast-sp-na {
    color: #94a3b8;
    cursor: help;
}

<?php /* 链接样式 */ ?>
.gene-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.gene-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

<?php /* 无结果提示美化 */ ?>
.no-results {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 4px solid #f59e0b;
    padding: 25px 30px;
    border-radius: 12px;
    margin: 30px auto;
    max-width: 800px;
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

<?php /* 错误信息美化 */ ?>
.error-message {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border-left: 4px solid #ef4444;
    padding: 25px 30px;
    border-radius: 12px;
    margin: 30px auto;
    max-width: 800px;
    color: #991b1b;
    line-height: 1.7;
    text-align: center;
}

<?php /* 统计信息 */ ?>
.result-stats {
    display: flex;
    gap: 20px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.stat-item {
    background: white;
    padding: 15px 25px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.stat-icon {
    font-size: 24px;
}

.stat-content h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #64748b;
    font-weight: 600;
}

.stat-content p {
    margin: 0;
    font-size: 18px;
    color: #1e293b;
    font-weight: 700;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .blast-result-header {
        padding: 20px;
    }
    
    .blast-result-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .table-container {
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .result-stats {
        flex-direction: column;
    }
    
    .stat-item {
        width: 100%;
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
            <li><a href="#">Phenotype</a>
                <ul><li><a href="/phenotype.php?class=all">All</a></li><li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li><li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li><li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li><li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li><li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li></ul>
            </li>
            <li><a href="#" class="current">Tools</a>
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>BLAST Search Results</b></legend>
<p class="paleo-intro">Sequence similarity search results for your query against the Cnidaria database.</p>


<?php /* 美化后的标题区域 */ ?>
    <div class="query-badge">
        🔍 Query ID: <strong><?= safe($qnum) ?></strong> • Program: <strong><?= safe($program) ?></strong> • Database: <strong><?= safe($dbLabel) ?></strong>
    </div>

<?php
/* 显示结果
 *
 * 引擎是否失败由退出码决定，与输出文件是否存在无关：blastall 用 -o 打开
 * 输出文件后才开始跑，所以失败时常常留下一个 0 字节文件（或一段报错文字），
 * file_exists() 仍为真。原实现把「看退出码」的分支放在「文件不存在」里面，
 * 于是这类失败会掉进「No Similar Sequences Found」——把引擎出错说成没有命中，
 * 用户拿到的是一条听起来正常、实际上错误的结论。
 * （现场证据：blast/tmp 下 48 个 .tabular.txt 里有 12 个是 0 字节。）
 */
$__tabPath     = "../tmp/$qnum.tabular.txt";
$__engineFailed = (isset($blastRc) && $blastRc !== 0);
$__bigFile      = file_exists("../tmp/$qnum.BigFileError");

if (!$__engineFailed && file_exists($__tabPath)) {
    $f = file($__tabPath);
    $filesize = abs(filesize($__tabPath));

    /* 说明检索范围 —— 放在命中/无命中两个分支之前，这样「一条都没命中」时
       用户也知道到底搜了哪些库。 */
    ?>
    <div class="gd-notice gd-info" style="margin:10px 0">
    <?php if ($dbIsAll): ?>
        Searched <b>all <?= (int)$dbAllCount ?> <?= $db === '__ALL_PEP__' ? 'protein' : 'nucleotide' ?> databases</b>
        (<?= safe($program) ?>). The search spans every species, so each hit carries its own
        species &mdash; shown in the <b>Species</b> column below, resolved from the subject ID.
        Where the same ID is used by more than one species, every candidate is listed, because
        BLAST's tabular output does not record which database a hit came from.
    <?php else: ?>
        Searched the <b><?= safe($dbLatin !== '' ? $dbLatin : $db) ?></b>
        <?= substr($db, -4) === '.pep' ? 'protein' : 'nucleotide' ?>
        database <code><?= safe($db) ?></code>. Every hit comes from this species.
    <?php endif; ?>
    </div>
    <?php
    if ($filesize > 0) {
        // 统计结果数量
        $result_count = count($f);

        // 提取数据库物种信息（「全部数据库」模式下没有单一物种）
        $conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
        $result_species = null;
        if (!$dbIsAll && $conn && !$conn->connect_error) {
            $spe = explode(".", $db);
            // ========== 安全改进③：SQL 预处理 ==========
            $stmt_db = $conn->prepare("SELECT * FROM abbr WHERE abbr = ?");
            $stmt_db->bind_param("s", $spe[0]);
            $stmt_db->execute();
            $query_db = $stmt_db->get_result();
            $result_species = $query_db->fetch_row();
        }

        /* 「全部数据库」时每一条命中的物种都不一样，而 tabular 输出里没有库名，
           只能拿 subject ID 反查 _locus / _seq（见 cnido_species_map_seqs）。
           先扫一遍把所有号收齐、一次问完，而不是在渲染循环里逐条查 —— 逐条是
           每条一次 145 表 UNION，一页就白加好几秒。 */
        $spMap  = array();
        $spMore = 0;                       // 超出解析上限没查的号数
        if ($dbIsAll) {
            $__ids = array();
            foreach ($f as $__line) {
                $__c = explode("\t", $__line);
                if (count($__c) >= 12 && $__c[1] !== '') { $__ids[$__c[1]] = true; }
            }
            $__ids = array_keys($__ids);
            /* 上限只为防病态输入（多序列查询 × -b 500）。真到这一步页面上已经有
               上万行、本来就没法看，但宁可少标一列，也不能让查询时间不可控。 */
            $__cap = 3000;
            $spMore = max(0, count($__ids) - $__cap);
            $spMap  = cnido_species_map_seqs($conn, array_slice($__ids, 0, $__cap));
        }
        ?>

        <?php /* 表格容器 */ ?>
        <div class="table-container">
            <div class="table-scroll-container">
                <table class="gridtable">
                    <tr>
                        <th>Query ID</th>
                        <th>Subject ID</th>
                        <?php if ($dbIsAll): ?><th>Species</th><?php endif; ?>
                        <th>Alignment<br>Length</th>
                        <th>Mis<br>Matches</th>
                        <th>Query<br>Start</th>
                        <th>Query<br>End</th>
                        <th>Subject<br>Start</th>
                        <th>Subject<br>End</th>
                        <th>% Identity</th>
                        <th>E-value</th>
                        <th>Bit<br>Score</th>
                    </tr>
                    <?php
                    foreach($f as $line){
                        /* 只解析合法的 tabular 行（12 列）。引擎若把一段报错文字写进了
                           输出文件，或文件里有空行，原来会解析出未定义下标并渲染出
                           一整行垃圾；直接跳过更诚实，也会让「实际命中数」与显示的行数
                           一致（$result_count 仍按文件行数统计，见上方提示）。 */
                        $__cols = explode("\t", $line);
                        if (count($__cols) < 12) { continue; }
                        list($Q_id, $S_id, $ident, $alig_leng, $mismatch, $gap_open, $q_start, $q_end, $s_start, $s_end, $evalue_res, $bit_score) = $__cols;
                        $gene1 = array();
                        $gene = substr(strstr($S_id, '_'), 1);
                        $gene1 = explode(".", $S_id);
                        $gene2 = $gene1[0].".".$gene1[1];

                        /* 全库检索时不同命中的物种不同，数据库名给不出物种，只能靠
                           反查结果（$spMap）。唯一命中时把 species 一并拼进链接，
                           读者点进去直接是该物种的基因页；多个物种共用这个号时
                           **不拼** species —— gene_detail.php 会列出候选让读者自己
                           选。挑一个就是把别的物种的坐标和注释当成这个基因展示。 */
                        $spNames = array();
                        if ($dbIsAll) {
                            foreach (isset($spMap[$S_id]) ? $spMap[$S_id] : array() as $__ab) {
                                $__ln = cnido_latin($__ab, $conn);
                                if ($__ln !== '') { $spNames[] = $__ln; }
                            }
                            $link = "../gene_detail.php?gene=" . urlencode($S_id);
                            if (count($spNames) === 1) {
                                $link .= "&species=" . urlencode($spNames[0]);
                            }
                        } elseif($db == "TAIR10.pep" or $db == "TAIR10.cds") {
                            // ========== 安全改进④：urlencode + 整体转义 ==========
                            $tair_sid = urlencode($S_id);
                            $link = "http://www.arabidopsis.org/servlets/Search?type=general&search_action=detail&method=1&show_obsolete=F&name={$tair_sid}&sub_type=gene&SEARCH_EXACT=4&SEARCH_CONTAINS=1";
                        } else {
                            $species_param = urlencode($result_species[0] ?? '');
                            $sid_param = urlencode($S_id);
                            $link = "../gene_detail.php?species={$species_param}&gene={$sid_param}";
                        }
                        echo "<tr align='center'>";
                        echo "<td>" . safe($Q_id) . "</td>";
                        echo "<td><a href=\"" . safe($link) . "\" class=\"gene-link\" target=\"_blank\">" . safe($S_id) . "</a></td>";
                        if ($dbIsAll) {
                            if (!$spNames) {
                                echo "<td class='blast-sp-na' title=\"This subject ID was not found in any species table, so its species could not be determined. Open the hit to resolve it.\">&ndash;</td>";
                            } elseif (count($spNames) === 1) {
                                echo "<td class='blast-sp-one'>" . safe($spNames[0]) . "</td>";
                            } else {
                                /* 号跨物种撞名是真实存在的（FUN_002499-T1 在四个物种里都有）。
                                   全列出来，并说清为什么不能只有一个 —— 引擎的 tabular 输出
                                   不带库名，无从判断这一条命中到底来自哪个库。 */
                                echo "<td class='blast-sp-multi' title=\"This subject ID occurs in "
                                   . count($spNames) . " species; the tabular output does not record which database the hit came from.\">"
                                   . implode('<br>', array_map('safe', $spNames)) . "</td>";
                            }
                        }
                        echo "<td class='blast-num'>" . safe($alig_leng) . "</td>";
                        echo "<td class='blast-num'>" . safe($mismatch) . "</td>";
                        echo "<td class='blast-num'>" . safe($q_start) . "</td>";
                        echo "<td class='blast-num'>" . safe($q_end) . "</td>";
                        echo "<td class='blast-num'>" . safe($s_start) . "</td>";
                        echo "<td class='blast-num'>" . safe($s_end) . "</td>";
                        echo "<td class='blast-num'>" . safe($ident) . "%</td>";
                        echo "<td class='blast-num'>" . safe($evalue_res) . "</td>";
                        echo "<td class='blast-num'>" . safe($bit_score) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </table>
            </div>
        </div>
        <?php
        /* 反查有上限（$__cap），超出的号在 Species 列留空 —— 与其静默留白，
           不如说清是哪一列被截了。正常用（-b ≤ 500、单条查询）够不着这里。 */
        if ($spMore > 0) { ?>
            <p class="blast-hint">Species were resolved for the first <?= (int)$__cap ?> distinct
               subject IDs; the remaining <?= (int)$spMore ?> are shown as &ldquo;&ndash;&rdquo;
               to keep this lookup bounded.</p>
        <?php } ?>
        <?php
    } else {
        // 无结果的情况
        ?>
        <div class="no-results">
            <div class="no-results-icon">🔍</div>
            <h3>No Similar Sequences Found</h3>
            <p>The query sequence did not retrieve any similar sequences in the database. Try adjusting your search parameters or using a different sequence.</p>
        </div>
        <?php
    }
} else {
    /* 走到这里有两种原因，必须分开说：
         · $__engineFailed —— BLAST 进程本身失败（退出码非 0），无论输出文件在不在；
         · 否则是查询根本没准备好（上传失败 / 文件超过 5MB / 输入不是序列）。 */
    ?>
    <div class="error-message">
        <div class="no-results-icon">❌</div>
        <?php if ($__engineFailed): ?>
            <h3>BLAST Search Failed</h3>
            <p>The BLAST engine could not complete this search (it exited with status
               <b><?= (int)$blastRc ?></b>), so no result is available. This is a failure of the
               search itself, not a statement that your sequence has no matches.</p>
            <p>Check that the selected program (<b><?= safe($program) ?></b>) is appropriate for the
               selected database, and that the query is a valid
               <?= ($program === 'blastn' || $program === 'tblastx') ? 'nucleotide' : 'protein' ?>
               sequence. If the problem persists, please report it through the
               <a href="../contact.php">Contact page</a> quoting your Query ID above.</p>
        <?php else: ?>
            <h3>Query Processing Failed</h3>
            <?php if ($__bigFile): ?>
                <p>Your uploaded file exceeds the 5&nbsp;MB limit, so the search was not run.
                   Please submit a shorter sequence, or paste it as text instead of uploading a file.</p>
            <?php else: ?>
                <p>The query could not be read: the upload failed, or no sequence was submitted.
                   Please check that your file is a valid FASTA or plain sequence under
                   5&nbsp;MB, and try again.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
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