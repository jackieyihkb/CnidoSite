<?php
/* =====================================================================
 * Species Portal —— 单个物种的全部资源入口
 *
 * 审稿意见 Referee 1 major 1：
 *   "…enabling linked queries, synchronized visualization, and cross-referencing
 *    of datasets for combined analysis."
 * 审稿意见 Referee 2 major 1：
 *   "…when a species is selected in the Taxonomy module, users should be able to
 *    directly access all available genome, bulk transcriptome, single-cell,
 *    proteome, epigenome, metagenome, phenotype and palaeobiology resources for
 *    that species."
 *
 * 此前站点是「按模块组织」的：使用者想知道某个物种到底有什么数据，只能把
 * 十几个模块逐个点开，而且大多数模块的下拉框并不接受 URL 参数。本页把
 * 一个物种的 16 类资源集中到一页，并且每一格都带物种参数深链到对应模块。
 *
 * 数据可用性来自 includes/coverage.php（与 coverage_matrix.php 共用同一份
 * 1 小时缓存），因此本页不会因为逐物种查 1400+ 张表而变慢。
 * ===================================================================== */
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { die('Database connection failed.'); }
require_once __DIR__ . '/includes/coverage.php';
require_once __DIR__ . '/includes/modlinks.php';
/* 「有哪些数据」那张卡片网格与 speciesinfo.php 共用一份实现，见该文件里的说明。 */
require_once __DIR__ . '/includes/species_cards.php';

$cov     = cnido_coverage($conn);
$modules = cnido_modules();
$species = $cov['species'];
$data    = $cov['cov'];

/* ---- 参数：species 既接受 abbr1 短码，也接受拉丁名 / 下划线名 ---- */
$raw = isset($_GET['species']) ? trim($_GET['species'])
     : (isset($_POST['species']) ? trim($_POST['species']) : '');

$abbr = '';
$exact = false;
if ($raw !== '') {
    $needle = strtolower(str_replace('_', ' ', $raw));
    if (isset($species[$raw])) {
        $abbr = $raw; $exact = true;
    } else {
        foreach ($species as $k => $info) {
            if (strtolower($k) === strtolower($raw)
                || strtolower($info['species']) === $needle
                || strtolower(str_replace('_', ' ', $info['abbr'])) === $needle) {
                $abbr = $k; break;
            }
        }
        // 还找不到就退化为前缀匹配（「Acropora」这样的属名也能落到某个种）
        if ($abbr === '' && strlen($needle) >= 4) {
            foreach ($species as $k => $info) {
                if (strpos(strtolower($info['species']), $needle) === 0) { $abbr = $k; break; }
            }
        }
    }
}
/* 在套用默认物种之前先记下「到底解析出来没有」—— 否则 $notFound 只能靠
   $exact 判断，而 $exact 只在参数原样命中 abbr1 键时才为真，于是用拉丁学名或
   下划线形式正常打开的页面也会被报「不是本站物种」。 */
$resolved = ($abbr !== '');
if ($abbr === '' && !empty($species)) {
    // 没有任何参数时给一个默认物种，保证页面不是空的
    $abbr = isset($species['NVECT']) ? 'NVECT' : key($species);
}
$info = isset($species[$abbr]) ? $species[$abbr] : array('species' => '', 'abbr' => '', 'class' => '');
$notFound = ($raw !== '' && !$resolved);

/* ---- 该物种的附加元数据（基因组统计、分类、文献）---- */
$meta = array();
$q = mysqli_query($conn, "SELECT * FROM speciesinfo WHERE abbr = '" . mysqli_real_escape_string($conn, $abbr) . "' LIMIT 1");
if ($q) { $meta = mysqli_fetch_assoc($q) ?: array(); }

/* ---- 该物种有多少个模块有数据 ---- */
$have = array();
foreach ($modules as $m => $cfg) {
    if (!empty($data[$abbr][$m])) { $have[] = $m; }
}
$nHave = count($have);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title><?= htmlspecialchars($info['species']) ?> - CnidoSite</title>
<meta name="description" content="Data held in CnidoSite for <?= htmlspecialchars($info['species']) ?>: genome assembly and annotation, transcriptome, single-cell, proteome and phenotype. Coverage varies by species." />
<meta name="keywords" content="Cnidaria, <?= htmlspecialchars($info['species']) ?>, genome, transcriptome, single-cell" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<style>
<?php /* 标题条下面那行物种元信息。原来这块信息装在深色 hero 里，现在改成浅色一行，
   与 genomeinfo.php 等页面的观感一致。 */ ?>
.sp-idline{list-style:none;margin:0 0 16px;padding:0;font-size:15px;color:#475569}
.sp-idline li{display:inline-block;margin:0 18px 4px 0}
.sp-idline li i{font-style:italic}
.sp-idline a{color:#1d4ed8;text-decoration:none}
.sp-idline a:hover{text-decoration:underline}
.sp-sech{font-size:18px;color:#1e293b;margin:0 0 12px;font-weight:600}
.sp-switch{margin:0 0 18px;font-size:15px;color:#475569}
.sp-switch input{padding:8px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:15px;min-width:280px}
.sp-switch button{padding:8px 18px;border:none;border-radius:7px;background:#2563eb;color:#fff;
  font-size:15px;font-weight:600;cursor:pointer}
<?php /* 卡片网格的规则来自 includes/species_cards.php（与 speciesinfo.php 共用）。 */ ?>
<?php echo cnido_spcards_css(); ?>
<?php /* Assembly record：按主题分组的卡片。原来是一张 18 行的两列表，全宽铺开、值一律
   靠左、没有单位、缺失值显示成 '-'，读起来是一团。 */ ?>
<?php /* 正文区宽约 1590px（#tempatemo_content_wrapper 是固定 1650px），minmax 取 320px
   正好排成 4 列 —— 组装 / 基因组统计 / 注释 / 编号 一行放满，Reference 单独占一行。 */ ?>
.sp-panel{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
  gap:14px 30px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;
  padding:16px 18px;margin-bottom:20px}
.sp-grp.wide{grid-column:1/-1}
.sp-grp h3{margin:0 0 5px;font-size:13px;font-weight:700;color:#64748b}
.sp-row{display:flex;justify-content:space-between;align-items:baseline;gap:16px;
  padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:15px}
.sp-row:last-child{border-bottom:0}
.sp-row dt{color:#64748b}
.sp-row dd{margin:0;color:#1e293b;font-weight:600;text-align:right}
.sp-row dd a{color:#1d4ed8;text-decoration:none}
.sp-row dd a:hover{text-decoration:underline}
<?php /* 整行分组（只有 Reference）里装的是整句引文，标题最长 211 字符。右对齐时若折行，
   第二行会从右边起排，读不下去；所以整行分组内的行改成标签在上、正文在下、左对齐。
   228 条文献里有 54 条超过 130 字符，不能指望它总是一行放得下。 */ ?>
.sp-grp.wide .sp-row{display:block}
.sp-grp.wide .sp-row dd{margin-top:2px;text-align:left;font-weight:400;color:#334155;line-height:1.65}
<?php /* BUSCO 完整率：分段条 + 文字图例。色板取 dataviz 分类色前四位并按语义排序，
   已用该 skill 的 validate_palette.js 在 #ffffff 表面上验证 —— 明度带、色度下限、
   CVD 分离、常视力下限四项 PASS；对比度 WARN 按要求由下面可见的文字图例兜底。
   段间留 2px 表面色缝隙、两端 5px 圆角，不描边（描边会加上非数据的墨）。
   min-width:1px 是最小分段的可见性下限：库里最小的非零分段是 0.1%
   （Chironex_yamaguchii 的 D），放进约 280px 宽的一栏只有 0.28px，会整段看不见。
   这条下限最多把它撑到 1px（≈0.36%），精确值由图例给出。 */ ?>
.sp-busco{margin:2px 0 10px}
.sp-lede{font-size:15px;color:#64748b;margin-bottom:3px}
.sp-bar{display:flex;gap:2px;height:10px;margin-bottom:6px}
.sp-bar span{flex-basis:0;min-width:1px}
.sp-bar span:first-child{border-radius:5px 0 0 5px}
.sp-bar span:last-child{border-radius:0 5px 5px 0}
.sp-bar span:only-child{border-radius:5px}
.sp-key{display:flex;flex-wrap:wrap;gap:2px 14px;font-size:15px;color:#475569}
.sp-key span{display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.sp-key i{width:9px;height:9px;border-radius:2px;flex:0 0 auto}
.sp-key b{color:#1e293b;font-weight:600}
.sp-others{font-size:15px;line-height:2.4}
.sp-others a{display:inline-block;padding:3px 12px;border-radius:14px;background:#f1f5f9;color:#334155;
  text-decoration:none;margin:2px 5px 2px 0;font-size:15px}
.sp-others a:hover{background:#2563eb;color:#fff}
.sp-others a.on{background:#2563eb;color:#fff}
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
            <li><a href="#" class="current">Taxonomy</a>
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

<?php /* 页面开头统一成全站既有写法：legend + header.jpg 标题条，下面跟一段
     paleo-intro 说明（与 genomeinfo.php 的 Species Overview 一致）。
     原先这里是一整块深色渐变 hero（.sp-head > h1），和站内其它页面不是一个
     语言；同一个物种名在同一次浏览里同时以 h1 和 legend 两种面貌出现也重复。
     物种代码 / 分类 / NCBI Taxonomy 这些原本在 hero 里的信息没有丢，改成
     标题条下面一行浅色元信息。 */ ?>
<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Species Portal &mdash; <i><?= htmlspecialchars($info['species']) ?></i></b></legend>
<p class="paleo-intro">
    CnidoSite currently holds <b><?= $nHave ?></b> of the <b><?= count($modules) ?></b> data types it covers for
    <i><?= htmlspecialchars($info['species']) ?></i>. Each entry below links straight into the corresponding
    module already filtered to this species &mdash; except the two cross-species views,
    <a href="pan-geneset.php">Pan-geneset</a> and the <a href="genefamily.php">Orthogroup browser</a>, which
    are defined over the whole collection and open unfiltered. The greyed-out entries have no data for this
    species in the current release. Use the box underneath to open the same overview for any other species.
</p>

<?php if ($notFound): ?>
    <div class="gd-notice gd-warn">
        <b><?= htmlspecialchars($raw) ?></b> is not one of the <?= count($species) ?> species currently
        registered in CnidoSite. Showing <b><i><?= htmlspecialchars($info['species']) ?></i></b>
        instead. You can also browse the full
        <a href="coverage_matrix.php">coverage matrix</a> or search by name there.
    </div>
<?php endif; ?>

<?php /* 有两对物种是同一生物的两个名字（见 includes/species_cards.php 里的
    cnido_species_synonym()）。这两对共享同一个 NCBI Taxonomy ID，
    本页的「NCBI Taxonomy」外链会把它们指到同一条记录；不说清楚就像登重了。 */ ?>
<?php $__syn = cnido_species_synonym($abbr); ?>
<?php if ($__syn !== ''): ?>
    <div class="gd-notice gd-info" style="margin-bottom:14px"><?= $__syn ?></div>
<?php endif; ?>

<ul class="sp-idline">
    <?php if ($info['class'] !== ''): ?>
        <li>Class <i><?= htmlspecialchars($info['class']) ?></i></li>
    <?php endif; ?>
    <?php if (!empty($meta['NCBI_taxonomy_ID'])): ?>
        <li>NCBI Taxonomy
            <a href="https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=<?= urlencode($meta['NCBI_taxonomy_ID']) ?>"
               target="_blank"><?= htmlspecialchars($meta['NCBI_taxonomy_ID']) ?></a></li>
    <?php endif; ?>
    <li><a href="coverage_matrix.php?q=<?= urlencode($abbr) ?>">See this species in the coverage matrix &rarr;</a></li>
</ul>

<div class="sp-switch">
    <form method="get" action="species_portal.php">
        <label for="sp-input"><b>Open another species:</b></label>
        <input type="text" name="species" id="sp-input" list="sp-list"
               placeholder="Latin name, e.g. Nematostella vectensis"
               value="<?= htmlspecialchars($info['species']) ?>">
        <datalist id="sp-list">
            <?php foreach ($species as $k => $i): ?>
                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($i['species']) ?></option>
            <?php endforeach; ?>
        </datalist>
        <button type="submit">Open</button>
    </form>
</div>

<h2 class="sp-sech">Data available for this species</h2>

<?= cnido_spcards_html($cov, $abbr, $info) ?>

<?php if (!empty($meta)): ?>
<?php
/* ---- Assembly record 的取值与格式化 ------------------------------------------
 * speciesinfo 用字面量 '-' 表示「无数据」，genomeinfo.php / gene_detail.php /
 * speciesinfo.php 都按缺失处理。这张表原来用 !empty() 判断，于是 '-' 被当成真值：
 * PMULT 那行渲染出了 `<a href="-" target="_blank" rel="noopener noreferrer">[link]</a>` 这样一个死链，
 * Protein_number / proteincoding_gene_number / Number_of_chromosomes 的 '-' 也被
 * 当成正常值显示（分别有 184 / 184 / 217 行是 '-'）。
 * -------------------------------------------------------------------------- */
function sp_val($meta, $k) {
    $v = isset($meta[$k]) ? trim((string)$meta[$k]) : '';
    return ($v === '' || $v === '-') ? '' : $v;
}
function sp_num($v, $dec = 0) {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') { return ''; }   // 缺失就返回空串，交给 sp_row 跳过
    return number_format((float)$v, $dec);
}
/* 长度统一到 Mb / kb —— scaffold_N50 那样的裸数字（30381243）读起来太费劲 */
function sp_len($v) {
    $n = (float)$v;
    if ($n <= 0) { return ''; }
    if ($n >= 1000000) { return number_format($n / 1000000, 2) . ' Mb'; }
    if ($n >= 1000)    { return number_format($n / 1000, 1) . ' kb'; }
    return number_format($n) . ' bp';
}
/* BUSCO 串在 speciesinfo 里有三种写法：标准式（293 行）、只有完整率（31 行）、
   带空格与分号的变体（2 行）。统一去空白、把 ';' 当 ',' 再解析。
   （2026-09-21 拆开 6 个被截断的合并码时，其中 4 个物种原本只有完整率，随重跑
   结果一并升级成了标准式，故 289/35 变为 293/31。） */
function sp_busco($raw) {
    $s = trim((string)$raw);
    if ($s === '' || $s === '-') { return null; }
    $n = str_replace(array(' ', ';'), array('', ','), $s);
    if (preg_match('/^C:([0-9.]+)%\[S:([0-9.]+)%,D:([0-9.]+)%\],F:([0-9.]+)%,M:([0-9.]+)%$/', $n, $m)) {
        return array('S' => (float)$m[2], 'D' => (float)$m[3],
                     'F' => (float)$m[4], 'M' => (float)$m[5]);
    }
    if (preg_match('/^([0-9.]+)%$/', $n, $m)) {
        return array('C' => (float)$m[1]);   // 只有完整率，没有 S/D/F/M 拆分
    }
    return false;                            // 解析不了就原样显示，不猜
}
function sp_row($label, $value, $title = '') {
    if ($value === '') { return ''; }
    return '<div class="sp-row"><dt>' . htmlspecialchars($label) . '</dt><dd'
         . ($title !== '' ? ' title="' . htmlspecialchars($title) . '"' : '')
         . '>' . $value . '</dd></div>';
}
function sp_link($href, $text) {
    return '<a href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener noreferrer">'
         . htmlspecialchars($text) . '</a>';
}

/* 分组：组装 / 基因组统计 / 注释 / 编号 / 文献。空值直接不产生行，整组为空就不输出。 */
$sp_groups = array();

$g = '';
$g .= sp_row('Assembly name',  htmlspecialchars(sp_val($meta, 'Assembly_Name')));
$g .= sp_row('Assembly level', htmlspecialchars(sp_val($meta, 'Assembl_level')));
$acc = sp_val($meta, 'Genome_Assemble');
/* 这一列 326 行里有 321 行是 GCA/GCF 组装号（另外 5 行是 '-' 或空），原来表头写的是
   "Assembled by"（组装者）—— 标签与内容对不上，真正的组装机构在 Submitter 列。
   链接形式跟 site 内既有惯例一致（speciesinfo.php / MAGs.php 都用 datasets/genome/）。 */
$g .= sp_row('Assembly accession', $acc === '' ? '' :
      sp_link('https://www.ncbi.nlm.nih.gov/datasets/genome/' . urlencode($acc), $acc));
$g .= sp_row('Assembled by',   htmlspecialchars(sp_val($meta, 'Submitter')));
$g .= sp_row('Year',           htmlspecialchars(sp_val($meta, 'year')));
$sp_groups['Assembly'] = array($g, false);

$g = '';
$sz = sp_val($meta, 'Size');
$g .= sp_row('Genome size', $sz === '' ? '' : sp_num($sz, 2) . ' Mb');
$gc = sp_val($meta, 'GC_percent');
$g .= sp_row('GC content', $gc === '' ? '' : htmlspecialchars($gc) . '%');
$g .= sp_row('Scaffolds', sp_num(sp_val($meta, 'scaffolds_number')));
$g .= sp_row('Scaffold N50', sp_len(sp_val($meta, 'scaffold_N50')));
$g .= sp_row('Contigs', sp_num(sp_val($meta, 'Contig_number')));
$g .= sp_row('Contig N50', sp_len(sp_val($meta, 'contig_N50')));
$g .= sp_row('Chromosomes', sp_num(sp_val($meta, 'Number_of_chromosomes')));
$sp_groups['Genome statistics'] = array($g, false);

/* BUSCO 那一格做成 100% 分段条：完整（单拷贝 / 重复）、碎片、缺失。 */
$busco = sp_busco(sp_val($meta, 'BUSCO'));
$busco_html = '';
if ($busco === false) {
    $busco_html = '<div class="sp-busco"><div class="sp-lede">BUSCO</div>'
                . '<div style="font-size:15px;font-weight:600;color:#1e293b">'
                . htmlspecialchars(sp_val($meta, 'BUSCO')) . '</div></div>';
} elseif (is_array($busco)) {
    /* 色板：dataviz 分类色的前四个槽位，按语义排序（完整=蓝、重复=青、碎片=黄、缺失=红）。
       已用该 skill 的 validate_palette.js 在 #ffffff 表面上跑过 —— 明度带、色度下限、
       CVD 分离（最差相邻 ΔE 9.1 protan）、常视力下限（最差 20.8）四项 PASS；
       aqua/yellow 对白底的对比度是 WARN，校验器要求「可见标签或表格视图」兜底，
       所以下面每一段都带文字标签和数值，不靠颜色单独表意。 */
    if (isset($busco['C'])) {
        $segs = array(array('#2a78d6', 'Complete', $busco['C']),
                      array('#e2e8f0', 'Not complete', max(0, 100 - $busco['C'])));
        $aria = 'BUSCO completeness ' . $busco['C'] . ' percent, remainder not complete';
    } else {
        $segs = array(array('#2a78d6', 'Complete, single-copy', $busco['S']),
                      array('#1baf7a', 'Complete, duplicated',  $busco['D']),
                      array('#eda100', 'Fragmented',            $busco['F']),
                      array('#e34948', 'Missing',               $busco['M']));
        $aria = 'BUSCO: complete single-copy ' . $busco['S'] . ' percent, duplicated '
              . $busco['D'] . ' percent, fragmented ' . $busco['F'] . ' percent, missing '
              . $busco['M'] . ' percent';
    }
    $bar = '<div class="sp-bar" role="img" aria-label="' . htmlspecialchars($aria) . '">';
    foreach ($segs as $sg) {
        if ($sg[2] <= 0) { continue; }   // 0% 的段不画，免得留一条发丝般的色块
        $bar .= '<span style="background:' . $sg[0] . ';flex-grow:' . $sg[2] . '"></span>';
    }
    $bar .= '</div>';
    $key = '<div class="sp-key">';
    foreach ($segs as $sg) {
        $key .= '<span><i style="background:' . $sg[0] . '"></i>' . htmlspecialchars($sg[1])
              . ' <b>' . number_format($sg[2], 1) . '%</b></span>';
    }
    $key .= '</div>';
    $busco_html = '<div class="sp-busco"><div class="sp-lede">BUSCO</div>' . $bar . $key . '</div>';
}

if ($busco_html !== '') {
    $g = $busco_html;
    $g .= sp_row('Protein-coding genes', sp_num(sp_val($meta, 'proteincoding_gene_number')),
                 'Gene models in the deposited annotation');
    $g .= sp_row('Proteins', sp_num(sp_val($meta, 'Protein_number')),
                 'Protein sequences in the deposited annotation');
    $sp_groups['Annotation'] = array($g, false);
}

$g = '';
$wgs = sp_val($meta, 'WGS_accession');
$g .= sp_row('WGS accession', $wgs === '' ? '' :
      sp_link('https://www.ncbi.nlm.nih.gov/nuccore/' . urlencode($wgs), $wgs));
$bp = sp_val($meta, 'BioProject');
$g .= sp_row('BioProject', (preg_match('/^PRJ[A-Z]{2}\d+$/', $bp)) ?
      sp_link('https://www.ncbi.nlm.nih.gov/bioproject/' . urlencode($bp), $bp) : '');
$sp_groups['Identifiers'] = array($g, false);

/* 文献：speciesinfo 把「没有文献」也写成 '-'，原来因此输出 `- <i>-</i> <a href="-">[link]</a>`。 */
$g = '';
$title = sp_val($meta, 'Publication_Title');
if ($title !== '') {
    $v = htmlspecialchars($title);
    $jn = sp_val($meta, 'Publication_Journal');
    if ($jn !== '') { $v .= ' <i style="color:#64748b;font-weight:400">' . htmlspecialchars($jn) . '</i>'; }
    $url = sp_val($meta, 'paper_URL');
    $pm  = sp_val($meta, 'Pubmed_ID');
    if ($url !== '' && preg_match('#^https?://#i', $url)) {
        $v .= ' ' . sp_link($url, '[link]');
    } elseif ($pm !== '' && preg_match('/^\d+$/', $pm)) {
        $v .= ' ' . sp_link('https://pubmed.ncbi.nlm.nih.gov/' . urlencode($pm) . '/', '[PubMed]');
    }
    $g .= sp_row('Reference', $v);
    $sp_groups['Reference'] = array($g, true);
}

$sp_rendered = '';
foreach ($sp_groups as $gtitle => $gspec) {
    if (trim($gspec[0]) === '') { continue; }   // 整组无值就不输出
    $sp_rendered .= '<div class="sp-grp' . ($gspec[1] ? ' wide' : '') . '"><h3>'
                  . htmlspecialchars($gtitle) . '</h3>' . $gspec[0] . '</div>';
}
?>
<?php if ($sp_rendered !== ''): ?>
<h2 class="sp-sech">Assembly record</h2>
<div class="sp-panel"><?= $sp_rendered ?></div>
<?php endif; ?>
<?php endif; ?>

<?php if (count($species) > 1): ?>
<h2 style="font-size:18px;color:#1e293b;margin:0 0 8px">
    Other <?= htmlspecialchars($info['class'] !== '' ? $info['class'] : '') ?> species
</h2>
<div class="sp-others">
    <?php
    $n = 0;
    foreach ($species as $k => $i) {
        if ($info['class'] !== '' && strcasecmp($i['class'], $info['class']) !== 0) { continue; }
        /* 链接文字用拉丁学名（斜体），缩写只留在 URL 和 title 里。
           原来这里显示的是 NVECT / AALAT 这样的五字母代码 —— 站内到处是这种代码，
           使用者得先去别处查才知道是哪个物种（审稿意见：物种缩写名应更正为拉丁学名）。
           代码不能从 URL 里去掉：下游页面（speciesinfo / busco / gene_family …）
           认的就是缩写，改了几十个模块的深链会一起断。 */
        $__lbl = ($i['species'] !== '') ? $i['species'] : $k;
        if ($k === $abbr) {
            echo '<a class="on" href="#"><i>' . htmlspecialchars($__lbl) . '</i></a>';
        } else {
            /* title 属性也是给读者看的（鼠标悬停就弹出来），所以只放拉丁学名 ——
               原来拼了 “学名 (短码)”，等于把物种缩写可视化了一次。 */
            echo '<a href="species_portal.php?species=' . urlencode($k) . '" title="'
               . htmlspecialchars($__lbl) . '"><i>' . htmlspecialchars($__lbl) . '</i></a>';
        }
        if (++$n >= 80) { break; }
    }
    if ($info['class'] !== '') {
        echo '<br /><a href="coverage_matrix.php?class=' . urlencode($info['class'])
           . '" style="background:none;color:#1d4ed8;padding-left:0">See all '
           . htmlspecialchars($info['class']) . ' species in the coverage matrix &rarr;</a>';
    }
    ?>
</div>
<?php endif; ?>

<p class="paleo-intro" style="font-size:16px;margin-top:18px">
    Availability shown here is recomputed from the live database every hour
    (last built <?= date('Y-m-d H:i', $cov['built']) ?>). If a data type is listed as unavailable for
    <i><?= htmlspecialchars($info['species']) ?></i>, it has not been generated for this species in the
    current release &mdash; the
    <a href="coverage_matrix.php?q=<?= urlencode($abbr) ?>">coverage matrix</a> shows the equivalent row
    alongside every other species, and the
    <a href="data_statistics.php">statistics page</a> gives the site-wide totals.
</p>

</div>
</div>
</div>

<?php
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
