<?php
/* Transcriptome Assembly -- overview of every species for which CnidoSite now
 * provides an assembled transcriptome, with the annotation depth that goes with it.
 *
 * Companion page trans_assembly_species.php browses the per-transcript annotation
 * of a single species (paginated + searchable).
 *
 * Data source: MySQL tables trans_assembly_species (220 rows, one per species)
 * and trans_assembly (one row per predicted protein).  They are loaded by
 * scripts/import_ts.sh from the assembly/annotation pipeline output.
 */
require_once __DIR__ . '/includes/state.php';

/* 站点其它模块统一的数字格式（千分位）。cnido_num() 不可用时退化为本地实现，
   保证页面在 includes/ 变动时不会白屏。 */
if (!function_exists('cnido_num')) {
    function cnido_num($n) { return number_format((float)$n); }
}
function ts_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { $conn = null; }

$species = array();
$dbError = '';
if ($conn) {
    $q = mysqli_query($conn, "SELECT abbr1, abbr, species, `class`, source, run,
                                     transcripts, bp, n50, longest, proteins, reps,
                                     n_uniprot, n_pfam, n_panther, n_interpro, n_go, n_kegg, notes
                              FROM trans_assembly_species ORDER BY `class`, species");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) { $species[] = $r; }
    } else {
        $dbError = 'The transcriptome assembly tables are not available yet.';
    }
} else {
    $dbError = 'The database could not be reached.';
}

/* ---- totals ---- */
$T = array('species'=>0,'transcripts'=>0,'bp'=>0,'proteins'=>0,'reps'=>0,
           'n_uniprot'=>0,'n_pfam'=>0,'n_panther'=>0,'n_interpro'=>0,'n_go'=>0,'n_kegg'=>0);
$byClass = array();
$bySource = array();
foreach ($species as $s) {
    $T['species']++;
    foreach (array('transcripts','bp','proteins','reps','n_uniprot','n_pfam','n_panther',
                   'n_interpro','n_go','n_kegg') as $k) { $T[$k] += (float)$s[$k]; }
    $byClass[$s['class']]  = (isset($byClass[$s['class']])  ? $byClass[$s['class']]  : 0) + 1;
    $bySource[$s['source']] = (isset($bySource[$s['source']]) ? $bySource[$s['source']] : 0) + 1;
}
ksort($byClass);
/* 物种卡片原来写「220 species with an assembled transcriptome」，但这 220 行里有 12 行
   source 是 Genome-derived —— 它们的转录本序列取自基因组注释，没有做过组装。
   按来源拆成两个数，卡片和页首说明都用它，免得把 12 个没组装的算进「已组装」。 */
$nGenome = isset($bySource['Genome-derived']) ? (int)$bySource['Genome-derived'] : 0;
$nDeNovo = (int)$T['species'] - $nGenome;
/* 本模块自己的物种表有 220 行，物种目录（speciesinfo）是另一套 —— 有 63 个物种
   在这里有组装、却没有目录条目。这正解释了 release 说明里「326 个物种中 157 个
   有组装」与页首「220 个物种」为什么对不上。数由库现算，不写死。 */
$nCatalogue = 0;
if ($conn && $species) {
    $codes = array();
    foreach ($species as $s) { $codes[] = "'" . mysqli_real_escape_string($conn, $s['abbr1']) . "'"; }
    $q = mysqli_query($conn, "SELECT COUNT(*) FROM speciesinfo WHERE abbr IN (" . implode(',', $codes) . ")");
    if ($q) { $r = mysqli_fetch_row($q); $nCatalogue = (int)$r[0]; }
}
/* 一个蛋白可能同时有多个来源的注释，所以这些数字不能相加当作「已注释蛋白数」；
   真正有注释的蛋白数用下面的 $anyHits 估计（取六者中最大的一个来源作为下限）。 */
$annTotal = $T['n_uniprot'] + $T['n_pfam'] + $T['n_panther'] + $T['n_interpro'] + $T['n_go'] + $T['n_kegg'];

$ANN_META = array(
    'n_uniprot'  => array('UniProt (Swiss-Prot)', '#1d4ed8', 'https://www.uniprot.org/'),
    'n_interpro' => array('InterPro',             '#7c3aed', 'https://www.ebi.ac.uk/interpro/'),
    'n_pfam'     => array('Pfam',                 '#0f766e', 'https://www.ebi.ac.uk/interpro/entry/pfam/'),
    'n_panther'  => array('PANTHER',              '#b45309', 'https://www.pantherdb.org/'),
    'n_go'       => array('Gene Ontology',        '#15803d', 'https://geneontology.org/'),
    'n_kegg'     => array('KEGG pathway',         '#be123c', 'https://www.kegg.jp/'),
);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Transcriptome Assembly - CnidoSite</title>
<meta name="keywords" content="Cnidaria, transcriptome assembly, de novo assembly, rnaSPAdes, Trinity, functional annotation, GO, KEGG, InterPro" />
<meta name="description" content="Assembled transcriptomes for <?php echo count($species); ?> cnidarian species with per-transcript functional annotation." />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<style>
<?php /* 页头改用站内通行的 <legend> + images/header.jpg（83 个页面都这么写），
   本页原先那套深色 .ts-hero 已删；.ts-cards 留着，六张概况卡片还在用。 */ ?>
.ts-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(168px,1fr));gap:14px;margin:0 0 22px 0;}
.ts-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;box-shadow:0 2px 8px rgba(15,23,42,.05);}
.ts-card .k{font-size:15px;color:#64748b;font-weight:600;}
.ts-card .v{font-size:24px;font-weight:700;color:#1e293b;margin-top:6px;line-height:1.15;}
.ts-card .u{font-size:15px;color:#64748b;margin-top:2px;}
.ts-panel{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px 18px;margin-bottom:22px;}
.ts-panel h3{margin:0 0 12px 0;font-size:15px;font-weight:700;color:#1e293b;}
<?php /* 第三列 96px 放不下「4,057,857 (39.6%)」，会折成两行把行高撑开；这一格给足。 */ ?>
.ts-bar{display:grid;grid-template-columns:170px 1fr 142px;align-items:center;gap:12px;margin-bottom:9px;font-size:15px;}
.ts-bar .lab{color:#334155;font-weight:600;}
.ts-bar .track{background:#e2e8f0;border-radius:6px;height:16px;overflow:hidden;}
.ts-bar .fill{height:100%;border-radius:6px;}
.ts-bar .num{text-align:right;color:#475569;font-variant-numeric:tabular-nums;}
<?php /* 窄屏改成两行：标签与数字一行、进度条整宽一行。360px 视口下面板内容盒
         只有 280px，而 170px + 142px 两列加间距至少 336px，1fr 的条会被压成
         0px 宽、数字顶出面板（面板右边缘 339，数字顶到 376）。 */ ?>
@media (max-width:480px){
  .ts-bar{grid-template-columns:minmax(0,1fr) auto;gap:3px 10px;}
  .ts-bar .lab{grid-column:1;grid-row:1;}
  .ts-bar .num{grid-column:2;grid-row:1;}
  .ts-bar .track{grid-column:1/-1;grid-row:2;}
}
.ts-controls{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:12px;}
.ts-controls label{display:block;font-size:15px;color:#64748b;font-weight:600;margin-bottom:4px;}
.ts-controls select,.ts-controls input[type=text]{padding:7px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:15px;background:#fff;color:#1e293b;}
.ts-controls input[type=text]{min-width:230px;}
.ts-controls .btn{padding:7px 14px;border:1px solid #cbd5e1;background:#fff;border-radius:7px;font-size:15px;cursor:pointer;color:#334155;}
.ts-controls .btn:hover{background:#f1f5f9;}
.ts-hint{font-size:16px;color:#64748b;margin:0 0 14px 0;}
<?php /* max-height 不是为了好看，是两件必须的事：
   1) 220 行 × ~48px = 10,695px 高，横向滚动条落在**整张表的最底部**，等于够不着 ——
      最右边的 Browse 列（内容是一条 View 链接）一旦被裁掉就再也看不到。
      给了上限后 overflow:auto 才真的产生横竖两条滚动条，都在盒子边上。
   2) 表头写了 position:sticky;top:0，但 auto 高度的盒子里根本滚不起来，它一直
      是失效的。有上限之后 sticky 才有个可粘的滚动容器。 */ ?>
.ts-wrap{overflow:auto;max-height:76vh;border:1px solid #e2e8f0;border-radius:10px;background:#fff;}
<?php /* 表本身挂 gridtable：表头底色、表头下边框、留白、行分隔线、字号全部由共用样式
   提供（与 core 的 table.cc 一致）。原来这一套是照着共用样式抄的近似值 ——
   字号 13px（并集样式是 13.5px）、悬停写在 tr 上（td 有不透明底色会盖住它，
   那条一直是死的，现在由共用样式的 `tbody tr:hover td` 接管）。
   这里只留本页特有的：scroll 容器、两行吸附表头、nowrap、以及数字列继续居中。 */ ?>
table.ts-table{border-collapse:separate;border-spacing:0;}
<?php /* 左右内边距仍用 7px 而不是共用的 10px：15 列各省 6px = 90px。7px 是留给别的
   字体与浏览器缩放的余量，和下面 ≥1201px 那段定宽一起负责把 15 列塞进容器。
   （这里原来写「1680 视口下实测整表 max-content 1476px，都在容器以内」——
   2026-09-30 实测自然宽已经涨到 1740px，那句话不成立了，见下一段。） */ ?>
<?php /* 居中：与站内其它表格一致（共用样式的默认就是居中）。数字列在别处是右对齐
   （td.num），这张表按页内约定继续居中，靠 tabular-nums 保持等宽、列内仍然对得齐。
   这几个选择器都带 table.gridtable 且带 tr，压得过共用样式里的 td.num（0,2,3）。 */ ?>
table.gridtable.ts-table tr th,table.gridtable.ts-table tr td{padding:8px 7px;white-space:nowrap;}
<?php /* 桌面档：把 15 列钉进容器。

   为什么必须钉：表体 220 行 × 15 列、th 和 td 全是 white-space:nowrap，2026-09-30
   实测自然宽 1740px；而站内容器最宽 1588px，1440 屏只有 1396px。于是最右边的
   GO / KEGG / Browse 三列被裁在 .ts-wrap 之外，而 .ts-wrap 的横滚条落在 76vh 盒子
   的最底下 —— 用户得先滚到表格底部才够得着，等于看不见。这不是窄屏问题：1920 屏
   一样裁（1588 装 1740）。

   做法与 trans_assembly_species.php 一致：table-layout:fixed + <colgroup> 定列宽，
   十五个百分比加起来正好 100%（差一点点都会把最后一列挤没）。列宽写在 <col> 上而不是
   写在 th 上，是因为第二行表头的那六列（UniProt…KEGG）在第一行里**没有单元格**
   —— 它们被 colspan=6 的「Genes with annotation」盖着，而 fixed 布局只认第一行。

   配套的四条：
   · 表头允许折行：Length (Mb) / N50 (bp) / RNA-seq run / Gene Ontology / KEGG pathway
     折成两行，几列各省 30px 上下；表头整块因此 81px → 104px。
   · 必须同时写 overflow-wrap:normal —— 共用样式 table.gridtable 自带
     word-wrap:break-word，不撤销的话窄一两个像素就会把单词**从中间**咬断
     （「Transcripts」排成「Transcript / s」、「InterPro」排成「InterPr / o」），
     这种断词比溢出更难读。撤销之后宽度只差一两像素时是溢出到邻格的 7px 内边距里，
     看不出来。
   · Species 列 13.32%（1440 屏约 186px）：220 个学名里 34 个比这宽、会折成两行
     （最长的是 Aurelia sp. 3 sensu Dawson et al. (2005)，271px），换来其余 14 列
     全部单行 —— 全表只有这一列折行。再宽就只能从数字列里抠，数字会互相咬住。
   · min-width:1396px 就是上面那套百分比的设计宽度：比这更窄时宁可让 .ts-wrap 横滚
     （1366 屏滚 74px、1280 屏滚 160px），也不让百分比继续缩 —— 缩下去最先咬住的是
     Class 的「Hexacorallia」和 N50 的「1,780」这种「按当前宽度刚好放下」的单元格。
     1440 屏及以上完全装下、零横滚。

   实测（Chromium，2026-09-30）：1440 表宽 1396 = 容器 1396，1600 / 1920 也装得下；
   15 列零裁切、表头零断词。≤1200 一个字节没变：那时 <col> 上没有宽度、媒体查询
   不生效，照旧 auto 布局 + 横滚。 */ ?>
@media (min-width:1201px){
  table.gridtable.ts-table{table-layout:fixed;width:100%;min-width:1396px;}
  table.gridtable.ts-table thead th{white-space:normal;overflow-wrap:normal;box-sizing:border-box;}
  table.gridtable.ts-table col:nth-child(1){width:6.9484%;}    /* Class */
  table.gridtable.ts-table col:nth-child(2){width:13.3238%;}   /* Species：会折行 */
  table.gridtable.ts-table col:nth-child(3){width:13.6103%;}   /* Assembly */
  table.gridtable.ts-table col:nth-child(4){width:8.0946%;}    /* RNA-seq run */
  table.gridtable.ts-table col:nth-child(5){width:6.9484%;}    /* Transcripts */
  table.gridtable.ts-table col:nth-child(6){width:4.7278%;}    /* Length (Mb) */
  table.gridtable.ts-table col:nth-child(7){width:3.7249%;}    /* N50 (bp) */
  table.gridtable.ts-table col:nth-child(8){width:5.3725%;}    /* Proteins */
  table.gridtable.ts-table col:nth-child(9){width:4.9427%;}    /* UniProt */
  table.gridtable.ts-table col:nth-child(10){width:5.1576%;}   /* InterPro */
  table.gridtable.ts-table col:nth-child(11){width:4.9427%;}   /* Pfam */
  table.gridtable.ts-table col:nth-child(12){width:6.1605%;}   /* PANTHER */
  table.gridtable.ts-table col:nth-child(13){width:5.7307%;}   /* Gene Ontology */
  table.gridtable.ts-table col:nth-child(14){width:5.3725%;}   /* KEGG pathway */
  table.gridtable.ts-table col:nth-child(15){width:4.9427%;}   /* Browse */
  table.gridtable.ts-table tr td.sp{white-space:normal;}
}
table.ts-table thead th{position:sticky;top:0;z-index:3;cursor:pointer;user-select:none;}
<?php /* 表头是两行：第一行有 8 个 rowspan=2 的列名 + colspan=6 的「Genes with annotation」，
   第二行是 6 个注释来源 + Browse。两行都 top:0 的话第二行会盖住第一行（各自的吸附
   位置是独立的），所以第二行必须下移一个表头行高；那个高度由页内 JS 量出来写进
   --ts-head-h，不写死像素（模板 body{line-height:1.7em} 会继承进 th，行高不是
   font-size 的整数倍，写死会在别的缩放/字体下错位）。z-index 第二行要更高，
   因为 rowspan 的单元格在 0~2 行高度上与它重叠。 */ ?>
table.ts-table thead tr.grp+tr.grp th{top:var(--ts-head-h,44px);z-index:4;}
<?php /* 第一行的列名要贴顶：rowspan 单元格高两行，居中会把文字送进第二行的吸附区间里被盖住 */ ?>
table.ts-table thead tr:first-child th{vertical-align:top;}
table.ts-table thead tr.grp th{cursor:default;}
table.ts-table thead th.grp{text-align:center;border-left:1px solid #e2e8f0;}
table.gridtable.ts-table tr td.num{text-align:center;font-variant-numeric:tabular-nums;color:#334155;}
table.ts-table td.sp{font-style:italic;white-space:nowrap;}
table.ts-table td.zero{color:#64748b;}
table.ts-table a.sp-link{color:#1d4ed8;font-weight:600;text-decoration:none;font-style:italic;}
table.ts-table a.sp-link:hover{text-decoration:underline;}
.ts-tag{display:inline-block;padding:1px 7px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid transparent;}
.ts-tag.de-novo{background:#f0f9ff;color:#1d4ed8;border-color:#bfdbfe;}
.ts-tag.genome{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe;}
.ts-tag.existing{background:#f1f5f9;color:#475569;border-color:#e2e8f0;}
.ts-sort::after{content:"\2195";color:#64748b;margin-left:5px;font-size:12px;}
.ts-sort.asc::after{content:"\2191";color:#1d4ed8;}
.ts-sort.desc::after{content:"\2193";color:#1d4ed8;}
.ts-note{font-size:16px;color:#64748b;line-height:1.7;margin-top:18px;}
.ts-count{font-size:15px;color:#475569;margin:0 0 8px 0;}
</style>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Transcriptome Assembly</b></legend>
<p class="paleo-intro">Assembled transcriptomes and their functional annotation for
   <b><?php echo cnido_num($T['species']); ?></b> cnidarian species &mdash;
   <b><?php echo cnido_num($nDeNovo); ?></b> assembled de novo from the RNA-seq runs that were
   previously only linked as raw reads in
   <a href="/cytoscape/trans_data.php">Transcriptomic Data</a>, and
   <b><?php echo cnido_num($nGenome); ?></b> whose transcript set is taken from the genome
   <?php /* 控件本身写的 label 是 "Assembly"（下方 <label for="fSource">），原句却说
            "Source filter" —— 页面上没有叫这个名字的东西。按控件的实际名字写。 */ ?>
   annotation rather than from an assembly (marked <i>Genome-derived</i> in the Assembly filter).
   All of them are translated and annotated here, and every predicted protein can be browsed and
   searched per species. <b><?php echo cnido_num($nCatalogue); ?></b> of them are also species in
   the Taxonomy catalogue; the other <b><?php echo cnido_num($T['species'] - $nCatalogue); ?></b>
   hold a transcriptome assembly here but have no catalogue entry.
   <?php /* 原句是「this page counts more species than the coverage matrix does」——
           方向说反了：覆盖矩阵一行一个目录物种、共 326 行，本页 220 行。
           真正成立的说法是「本页列出的、有组装的物种更多」：矩阵里没有非目录物种的行，
           所以它印不出这 63 个（实测 speciesinfo 326 行、trans_assembly_species 220 行、
           交集 157）。不带数字断言矩阵那一列是多少，只断言它列不出这些人。 */ ?>
   The <a href="/coverage_matrix.php">coverage matrix</a> has one row per catalogue species, so it
   has no row at all for those <b><?php echo cnido_num($T['species'] - $nCatalogue); ?></b> and
   shows fewer species with an assembled transcriptome than this page does.</p>

<?php if ($dbError !== ''): ?>
<p style="color:#c00;"><?php echo ts_h($dbError); ?></p>
<?php else: ?>

<div class="ts-cards">
    <div class="ts-card"><div class="k">Species</div><div class="v"><?php echo cnido_num($T['species']); ?></div><div class="u"><?php echo cnido_num($nDeNovo); ?> assembled de novo, <?php echo cnido_num($nGenome); ?> from the genome annotation</div></div>
    <div class="ts-card"><div class="k">Transcripts</div><div class="v"><?php echo cnido_num($T['transcripts']); ?></div><div class="u">assembled sequences</div></div>
    <div class="ts-card"><div class="k">Total length</div><div class="v"><?php echo number_format($T['bp']/1e9, 1); ?> Gb</div><div class="u"><?php echo cnido_num($T['bp']); ?> bp</div></div>
    <div class="ts-card"><div class="k">Predicted proteins</div><div class="v"><?php echo cnido_num($T['proteins']); ?></div><div class="u">ORFs in the transcripts</div></div>
    <div class="ts-card"><div class="k">Representative set</div><div class="v"><?php echo cnido_num($T['reps']); ?></div><div class="u">longest ORF per gene</div></div>
    <div class="ts-card"><div class="k">Gene&ndash;source hits</div><div class="v"><?php echo cnido_num($annTotal); ?></div><div class="u">one per gene per source</div></div>
</div>

<div class="ts-panel">
    <h3>Functional annotation depth</h3>
    <?php
    /* 每个来源的数来自 trans_assembly_species 的 n_* 列，那几列数的是**基因**
       （= 代表序列，与同表的 reps 一列逐物种对得上：AALAT 20,797、NVECT 25,047、
       PDAMI 72,376 全部吻合），不是蛋白。所以
         · 分母用 $T['reps']（全部代表序列），不再用「六个来源里最大的那个」当 100%
           —— 那个基准是编出来的，读者没法解释 100% 那根条代表什么；
         · 文案里的单位一律写 representative sequences / genes，不写 proteins。
       本页是按物种汇总的，逐蛋白重算一遍要扫 1,311 万行，代价不成比例；
       某个物种自己的页面上有按蛋白算的覆盖图（includes/trans_annot.php）。 */
    $denomReps = $T['reps'] > 0 ? $T['reps'] : 1;
    foreach ($ANN_META as $k => $m):
        $pct = 100.0 * $T[$k] / $denomReps;
    ?>
    <div class="ts-bar">
        <div class="lab"><a href="<?php echo ts_h($m[2]); ?>" target="_blank" rel="noopener noreferrer" style="color:#334155;text-decoration:none;"><?php echo ts_h($m[0]); ?></a></div>
        <div class="track"><div class="fill" style="width:<?php echo number_format(min(100, $pct),1); ?>%;background:<?php echo $m[1]; ?>;"></div></div>
        <div class="num"><?php echo cnido_num($T[$k]); ?> <span style="color:#64748b;">(<?php echo number_format($pct,1); ?>%)</span></div>
    </div>
    <?php endforeach; ?>
    <p class="ts-hint" style="margin:10px 0 0 0;">
        Share of the <b><?php echo cnido_num($T['reps']); ?></b> predicted genes across all
        <b><?php echo cnido_num($T['species']); ?></b> species (counted once each, on its longest ORF)
        that carry at least one hit from each source. A gene usually has hits from several sources,
        so these shares overlap and do not add up to 100%.
    </p>
</div>

<div class="ts-controls">
    <div>
        <label for="fClass">Class</label>
        <select id="fClass" onchange="tsFilter()">
            <option value="">All (<?php echo count($species); ?>)</option>
            <?php foreach ($byClass as $c => $n): ?>
            <option value="<?php echo ts_h($c); ?>"><?php echo ts_h($c); ?> (<?php echo $n; ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="fSource">Assembly</label>
        <select id="fSource" onchange="tsFilter()">
            <option value="">All</option>
            <?php foreach ($bySource as $c => $n): ?>
            <option value="<?php echo ts_h($c); ?>"><?php echo ts_h($c); ?> (<?php echo $n; ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="fText">Search species</label>
        <input type="text" id="fText" placeholder="e.g. Nematostella, Acropora, Hydra" oninput="tsFilter()" />
    </div>
    <div><button class="btn" type="button" onclick="tsReset()">Reset</button></div>
</div>
<p class="ts-count" id="tsCount"></p>
<p class="ts-hint">Click a column header to sort. Click a species name to browse its annotated proteins.</p>

<div class="ts-wrap">
<table class="gridtable ts-table" id="tsTable">
<?php /* 15 个空 <col>，桌面档（≥1201px）由上面那段 CSS 给它们定宽，配合
   table-layout:fixed 把 15 列钉进容器。≤1200 时 <col> 上没有宽度、规则也不生效，
   auto 布局与横滚行为与改动前完全一致。列的顺序 = thead 第一行的 8 列 +
   第二行的 6 个注释来源 + Browse，共 15。 */ ?>
<colgroup><col><col><col><col><col><col><col><col><col><col><col><col><col><col><col></colgroup>
<thead>
<tr class="grp">
    <th rowspan="2" class="ts-sort" data-k="class">Class</th>
    <th rowspan="2" class="ts-sort" data-k="species">Species</th>
    <th rowspan="2" class="ts-sort" data-k="source">Assembly</th>
    <th rowspan="2" class="ts-sort" data-k="run">RNA-seq run</th>
    <th rowspan="2" class="ts-sort" data-k="transcripts">Transcripts</th>
    <th rowspan="2" class="ts-sort" data-k="length">Length (Mb)</th>
    <th rowspan="2" class="ts-sort" data-k="n50" title="Transcript N50: the transcript length at which half the assembled bases lie in transcripts of that length or longer. Not the genome-assembly N50.">N50 (bp)</th>
    <th rowspan="2" class="ts-sort" data-k="proteins">Proteins</th>
    <?php /* 这六列数的是**基因**（每个基因一条代表序列），不是蛋白：见本页
         「Functional annotation depth」面板里的注释。表头写 Proteins 会让人以为
         可以和第 8 列的 Proteins 相除。 */ ?>
    <th colspan="6" class="grp" title="Counted per gene (one representative sequence per gene), not per protein &mdash; so these six columns are not comparable with the Proteins column, which counts predicted proteins.">Genes with annotation</th>
    <?php /* 这里原先还有一个 <th rowspan="2">&nbsp;</th>。它看着像「占个位」，
         实际是 bug：第一行 8 个 rowspan=2 + colspan=6 只覆盖到第 14 列，那个空
         格于是落在第 15 列（表头里），而第二行的 Browse 被挤到第 16 列；数据行
         只有 15 个 td，最后一格（View 链接）落在第 15 列 —— 于是表头里多出一列
         全空、Browse 表头下面一格数据都没有，放链接的那格反倒没有表头。删掉它，
         Browse 表头就落回第 15 列，与 View 链接同列。删后全表 15 列，与 tbody
         的 15 个 td 一致。 */ ?>
</tr>
<tr class="grp">
    <?php foreach ($ANN_META as $k => $m): ?>
    <th class="ts-sort grp" data-k="<?php echo ts_h($k); ?>" title="<?php echo ts_h($m[0]); ?>"><?php echo ts_h(preg_replace('/\s*\(.*\)/', '', $m[0])); ?></th>
    <?php endforeach; ?>
    <th class="grp" style="font-weight:600;">Browse</th>
</tr>
</thead>
<tbody>
<?php foreach ($species as $s): ?>
<tr data-class="<?php echo ts_h($s['class']); ?>"
    data-source="<?php echo ts_h($s['source']); ?>"
    data-text="<?php echo ts_h(strtolower($s['species'] . ' ' . $s['abbr1'] . ' ' . $s['run'])); ?>">
    <td><?php echo ts_h($s['class']); ?></td>
    <td class="sp"><a class="sp-link" href="trans_assembly_species.php?species=<?php echo urlencode($s['abbr1']); ?>"><?php echo ts_h($s['species']); ?></a></td>
    <td><span class="ts-tag <?php echo (strpos($s['source'],'Genome') === 0) ? 'genome' : ((strpos($s['source'],'existing') !== false) ? 'existing' : 'de-novo'); ?>"><?php echo ts_h($s['source']); ?></span></td>
    <?php /* 12 行 source=Genome-derived 的转录本直接取自基因组注释，没有做过组装，
             因此也没有 RNA-seq run。空着会被读成「这一格坏了」；改用与本页注释计数列
             同样的淡色 &ndash; 表示「这一项对这条记录不存在」，并在 title 里说明原因。 */ ?>
    <td<?php if ($s['run'] === '') echo ' class="zero" title="Genome-derived: transcripts taken from the genome annotation, so there is no RNA-seq run behind this assembly."'; ?>><?php if ($s['run'] !== ''): ?><a href="https://www.ncbi.nlm.nih.gov/sra/?term=<?php echo urlencode($s['run']); ?>" target="_blank" rel="noopener noreferrer" style="color:#1d4ed8;"><?php echo ts_h($s['run']); ?></a><?php else: ?>&ndash;<?php endif; ?></td>
    <td class="num"><?php echo cnido_num($s['transcripts']); ?></td>
    <td class="num"><?php echo number_format($s['bp']/1e6, 1); ?></td>
    <td class="num"><?php echo cnido_num($s['n50']); ?></td>
    <?php /* 基因数（reps）只放进 title，不印成第二行：表头点击排序用的是
         cells[idx].textContent.replace(/[^0-9.\-]/g,'')，两行数字会被拼成一个
         数字（38,920 和 20,797 变成 3892020797），这一列的排序就废了。 */ ?>
    <td class="num" title="<?php echo cnido_num($s['proteins']); ?> predicted proteins, <?php echo cnido_num($s['reps']); ?> genes (representative sequences)"><?php echo cnido_num($s['proteins']); ?></td>
    <?php foreach ($ANN_META as $k => $m): $v = (int)$s[$k]; ?>
    <td class="num<?php echo $v === 0 ? ' zero' : ''; ?>"><?php echo $v === 0 ? '&ndash;' : cnido_num($v); ?></td>
    <?php endforeach; ?>
    <td><a href="trans_assembly_species.php?species=<?php echo urlencode($s['abbr1']); ?>" style="color:#1d4ed8;font-weight:600;">View</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<p class="ts-note">
    <b>How these were produced.</b> For every species with public RNA-seq, the largest single run was assembled
    de novo with <b>rnaSPAdes</b> (Trinity was used for earlier species). Reads were capped at 20&nbsp;M pairs per
    species. Open reading frames were predicted with <b>TransDecoder</b> and annotated by
    <b>DIAMOND</b> against UniProt Swiss-Prot, <b>InterProScan</b> (Pfam/PANTHER, with InterPro and GO
    cross-references) and <b>KOfamScan</b> against KEGG. Species whose public RNA-seq was unusable are kept here
    with a note on their row &mdash; see the <code>Notes</code> in the per-species page.
    <br />
    Homology search against NCBI <b>NR</b> was not run for this release.
    <br />
    A dash (&ndash;) marks a cell that has no value for that row: in <b>RNA-seq run</b> the assembly came from the
    genome annotation rather than from a sequencing run, and in the six annotation columns no representative
    sequence (gene) of that species hit that database.
</p>

<?php endif; ?>

</div>
</div>
</div>

<script type="text/javascript">
var tsState = { key: null, dir: 1 };
function tsRows() {
    return Array.prototype.slice.call(document.querySelectorAll('#tsTable tbody tr'));
}
function tsFilter() {
    var c = document.getElementById('fClass').value;
    var s = document.getElementById('fSource').value;
    var t = document.getElementById('fText').value.trim().toLowerCase();
    var shown = 0;
    tsRows().forEach(function (tr) {
        var ok = (!c || tr.getAttribute('data-class') === c)
              && (!s || tr.getAttribute('data-source') === s)
              && (!t || tr.getAttribute('data-text').indexOf(t) !== -1);
        tr.style.display = ok ? '' : 'none';
        if (ok) { shown++; }
    });
    document.getElementById('tsCount').textContent = shown + ' of ' + tsRows().length + ' species shown';
}
function tsReset() {
    document.getElementById('fClass').value = '';
    document.getElementById('fSource').value = '';
    document.getElementById('fText').value = '';
    tsFilter();
}
<?php /* 排序：按列取单元格文本；数值列用去掉千分位后的数字比较（表格里是 1,234 这种格式，
   字符串比较会得到 "9,999" > "10,000" 的错误结果）。 */ ?>
function tsSort(key, dir) {
    var body = document.querySelector('#tsTable tbody');
    var rows = tsRows();
    <?php /* 第 9-12 列的顺序照 $ANN_META 走，是 n_uniprot, n_interpro, n_pfam, n_panther；
       原来这里把 pfam/panther/interpro 写成了 9/10/11，于是点 Pfam 排的是 InterPro、
       点 PANTHER 排的是 Pfam、点 InterPro 排的是 PANTHER —— 三列互相错位一格。 */ ?>
    var idx = { class: 0, species: 1, source: 2, run: 3, transcripts: 4, length: 5, n50: 6, proteins: 7,
                n_uniprot: 8, n_interpro: 9, n_pfam: 10, n_panther: 11, n_go: 12, n_kegg: 13 }[key];
    if (idx === undefined) { return; }
    rows.sort(function (a, b) {
        var x = a.cells[idx].textContent.trim();
        var y = b.cells[idx].textContent.trim();
        var nx = parseFloat(x.replace(/[^0-9.\-]/g, ''));
        var ny = parseFloat(y.replace(/[^0-9.\-]/g, ''));
        var numeric = !isNaN(nx) && !isNaN(ny) && x !== '' && y !== '' && idx >= 4;
        if (numeric) { return (nx - ny) * dir; }
        return x.toLowerCase().localeCompare(y.toLowerCase()) * dir;
    });
    rows.forEach(function (tr) { body.appendChild(tr); });
}
document.querySelectorAll('#tsTable thead th.ts-sort').forEach(function (th) {
    th.addEventListener('click', function () {
        var k = th.getAttribute('data-k');
        tsState.dir = (tsState.key === k) ? -tsState.dir : 1;
        tsState.key = k;
        document.querySelectorAll('#tsTable thead th.ts-sort').forEach(function (o) {
            o.classList.remove('asc'); o.classList.remove('desc');
        });
        th.classList.add(tsState.dir === 1 ? 'asc' : 'desc');
        tsSort(k, tsState.dir);
    });
});
tsFilter();

<?php /* 把第一行表头的实际行高写进 --ts-head-h，供 CSS 里第二行表头的 sticky 偏移用。
   量的是 thead 第一行（不是整块表头）：rowspan=2 的单元格虽然横跨两行，行本身
   的高度仍是一行的。字体或窗口变化后行高会变，所以 resize 和字体就绪时各量一次。 */ ?>
function tsHeadOffset() {
    var tr = document.querySelector('#tsTable thead tr');
    var wrap = document.querySelector('.ts-wrap');
    if (!tr || !wrap) { return; }
    var h = Math.round(tr.getBoundingClientRect().height);
    if (h > 0) { wrap.style.setProperty('--ts-head-h', h + 'px'); }
}
tsHeadOffset();
window.addEventListener('resize', tsHeadOffset);
if (document.fonts && document.fonts.ready) { document.fonts.ready.then(tsHeadOffset); }
</script>

<?php
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
