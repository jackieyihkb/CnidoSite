<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Database Statistics - CnidoSite</title>
<meta name="keywords" content="Cnidaria, database statistics, data summary, bioinformatics resources" />
<meta name="description" content="Comprehensive statistics and software information for the database" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<?php
/* 物种 × 数据类型覆盖矩阵（旧地址是 coverage_matrix.php）。在 <head> 里就要
   拿到 cnido_cm_css()，所以 include 放在最前面，不能等到卡片那块再 require。 */
require_once __DIR__ . '/includes/coverage_matrix_view.php';
/* 「Genome Assemblies」卡片的渲染与样式（includes/genome_assembly_view.php）。
   样式要在 <head> 里就输出，所以和覆盖矩阵一样在这里 include。 */
require_once __DIR__ . '/includes/genome_assembly_view.php';
/* 「Gene Literature」卡片（includes/gene_literature_view.php），同样要在 <head> 里出样式。 */
require_once __DIR__ . '/includes/gene_literature_view.php';
?>
<?= cnido_cm_css() ?>
<?= cnido_ga_css() ?>
<?= cnido_gl_css() ?>

<style>
<?php /* Statistics页面专用样式 - 不影响全局CSS */ ?>
.stats-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.stats-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stats-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.help-badge {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

<?php /* 统计卡片美化 */ ?>
.stats-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 30px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
}

<?php /* 覆盖矩阵通栏。.stats-card 左右各 30px 内边距把矩阵压到 1498px，比这张表
   自己算出来的最小宽（1514px，见 includes/coverage_matrix_view.php 的 $cmMinW）
   还窄 16px，于是 1600 宽的屏幕上平白多出一条横向滚动条 —— 同一个矩阵在
   coverage_matrix.php 上是通栏的，同一档宽度不滚，两页看起来就不是同一个东西了。
   这里把矩阵的滚动容器反向外扩到卡片内边缘，让两页拿到同样的宽度；标题、说明、
   图例、筛选器和翻页仍留在内边距里。
   30px 必须和上面的 .stats-card padding 同步，移动端那条 @media 里另有 20px 的覆盖。 */ ?>
#coverage-matrix .cm-wrap {
    /* width:auto 是必须的：.cm-wrap 自己有 width:100%，显式宽度下负外边距只会
       把它向左平移 30px，宽度一点不变（量出来 wrap 从 x=52 到 x=22，仍是 1498）。
       改成 auto 之后块级元素才按「包含块 + 负边距」重新算宽。
       外扩到卡片内边缘后，矩阵框的左右描边正好和卡片边框重合（两条 1px 叠成一条
       深线）、10px 的圆角也贴在卡片 12px 的圆角上 —— 所以这两条边和圆角在这里去掉，
       上下两条细线留着，矩阵就成为卡片内的一条通栏表格。 */
    width: auto;
    margin-left: -30px;
    margin-right: -30px;
    border-left: 0;
    border-right: 0;
    border-radius: 0;
}

.card-title {
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

.card-title::before {
    content: "📊";
    font-size: 24px;
}

.card-title.software::before {
    content: "🛠️";
    font-size: 24px;
}

.card-title.genome::before {
    content: "🧬";
    font-size: 24px;
}

.card-title.lit::before {
    content: "📚";
    font-size: 24px;
}

<?php /* ------------------------------------------------------------------
   分页控件。下面这一套是从 go_result.php 原样搬过来的（templatemo_style.css
   里没有，每个用到分页的页面各自带一份，这是本站既有的做法）。
   一处改动：配色规则写成 a.page-btn 而不是 .page-btn —— 样式表里的
   a:link{color:#1d4ed8} 特异性 (0,1,1) 压得过 .page-btn (0,1,0)，光写类名的话
   普通页按钮会变成链接蓝，只有 .active 因为 (0,2,0) 才幸免。
   卡片里再套一个卡片不好看，所以把它的白底/阴影收掉。 */ ?>
.pagination-container { background:#fff; border-radius:12px; padding:20px; margin-top:22px; border:1px solid #e2e8f0; }
.stats-card .pagination-container { box-shadow:none; border:0; padding:0; margin-top:22px; }
.pagination-info { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:15px; }
.total-records { background:linear-gradient(135deg,#1d4ed8 0%,#1e40af 100%); color:#fff; padding:10px 20px; border-radius:8px; font-weight:600; box-shadow:0 4px 12px rgba(59,130,246,.3); }
.per-page-selector { display:flex; align-items:center; gap:10px; }
.per-page-selector select { padding:8px 15px; border-radius:6px; border:2px solid #e2e8f0; background:#fff; font-size:15px; cursor:pointer; }
.per-page-selector select:hover { border-color:#047857; }
.pagination-nav { display:flex; justify-content:center; align-items:center; gap:8px; margin:25px 0; flex-wrap:wrap; }
a.page-btn { padding:10px 16px; border-radius:6px; border:1px solid #e2e8f0; background:#fff; color:#475569; text-decoration:none; font-size:15px; font-weight:500; min-width:44px; text-align:center; display:inline-block; }
a.page-btn:hover { background:#f0f9ff; border-color:#1d4ed8; color:#1d4ed8; }
a.page-btn.active { background:#1d4ed8; border-color:#1d4ed8; color:#fff; font-weight:600; }
a.page-btn.disabled { opacity:.5; cursor:not-allowed; pointer-events:none; }
.go-to-page { display:flex; align-items:center; gap:10px; margin-top:15px; justify-content:center; }
.go-to-page input { width:70px; padding:8px 12px; border-radius:6px; border:1px solid #e2e8f0; text-align:center; font-size:15px; }
.go-to-page button { padding:8px 16px; background:#1d4ed8; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:500; }
.go-to-page button:hover { background:#1d4ed8; }

<?php /* 两个表各自的搜索框（服务端 GET，不依赖 JS） */ ?>
.cnido-search { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:0 0 16px; }
.cnido-search input[type=text] { padding:9px 14px; border:2px solid #e2e8f0; border-radius:8px; font-size:15px; min-width:260px; }
.cnido-search input[type=text]:focus { outline:none; border-color:#1d4ed8; }
.cnido-search button { padding:9px 20px; background:#1d4ed8; color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }
.cnido-search button:hover { background:#1d4ed8; }
.cnido-search a.clear { font-size:15px; color:#64748b; }

<?php /* 链接样式 */ ?>
.stats-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.stats-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

<?php /* 高亮数字 */ ?>
.highlight-number {
    font-weight: 700;
    color: #1e293b;
    font-size: 15px;
}

<?php /* 软件表格特殊样式。原来写的是裸 `.software-table` (0,1,1)，低于共用样式里的
   `table.gridtable td` (0,1,2)，本来就压不过；而且表格自身也一直没挂这个类名，
   两件事凑在一起，所以这些规则从来没生效过。下面补 `table.` 前缀，表格也加了类。 */ ?>
table.gridtable.software-table {
    font-size: 15px;
}

table.gridtable.software-table td {
    padding: 12px 10px;
}

table.gridtable.software-table a {
    color:#1d4ed8;
    word-break: break-all;
}

<?php /* Parameter / Links 两列是自由文本：Parameter 里 MACS2 那行有一整段说明，Links
   常常是两个 URL，居中后多行会变成参差的居中块。这两列显式左对齐；Module 和
   Software 保持居中（单元格上本来就写着 align="center"）。
   用 nth-child 而不是逐格加类：这张表每行固定 4 格，二十来行逐一加类只会更难读。 */ ?>
table.gridtable.software-table th:nth-child(3),
table.gridtable.software-table th:nth-child(4),
table.gridtable.software-table td:nth-child(3),
table.gridtable.software-table td:nth-child(4) {
    text-align: left;
}

<?php /* 统计汇总表是「标签 → 数值」两列。标签列（th）里有
   "GO / InterPro / Pfam / PANTHER / KEGG annotation" 这种长标签，居中会读成
   一堆参差的短行，左对齐；数值列继续居中（单元格上本来就写着 align='center'）。
   th 的 nowrap 也要放开，否则长标签会把列撑得很宽。 */ ?>
table.gridtable.ds-kv th {
    text-align: left;
    white-space: normal;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .stats-header {
        padding: 20px;
    }
    
    .stats-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .stats-card {
        padding: 20px;
        margin-left: 15px;
        margin-right: 15px;
    }

    <?php /* 矩阵通栏的反向外扩要跟着小屏的内边距走（见样式开头的
       #coverage-matrix .cm-wrap）：这里 .stats-card 是 20px 不是 30px。 */ ?>
    #coverage-matrix .cm-wrap {
        width: auto;
        margin-left: -20px;
        margin-right: -20px;
        border-left: 0;
        border-right: 0;
        border-radius: 0;
    }

    <?php /* 补 `table.` 前缀。原来写的是裸 `.gridtable` (0,1,0) 和 `.gridtable th`
       (0,1,1)，都低于 templatemo_style.css 里 `table.gridtable th/td` (0,1,2)
       ——媒体查询不改变特异性——所以从写下起就没生效过，小屏上表格一直是
       全站的 13.5px 和 7px/10px 留白。凑平特异性后就按这里的来。 */ ?>
    table.gridtable {
        font-size: 15px;
    }

    table.gridtable th, table.gridtable td {
        padding: 10px 8px;
    }
    
    table.gridtable.software-table {
        font-size: 12px;
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
            <li><a href="#" class="current">Help</a>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Database Statistics</b></legend>
<p  class="paleo-intro">Comprehensive overview of the data resources, annotations and analytical tools available in the CnidoSite database.</p>

<?php /* 数据库统计卡片 */ ?>
<div class="stats-card">
    <div class="card-title">Database Content Statistics</div>
    
<?php
	/* 本页所有数字与首页 index.php 取自同一处：includes/stats.php 的 cnido_stats()。
	   以前两页各写一份硬编码数字，于是出现了 148 vs 145、146 vs 78 这类同一个量
	   两个值的情况（审稿意见 Referee 2 major 12）。现在改口径只需要改一处。
	   每个数字后面用 <abbr title> 挂上口径定义，鼠标悬停即可看到「这个数到底数的是什么」。 */
	require_once __DIR__ . '/includes/stats.php';
	$__statConn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	$S = ($__statConn && !$__statConn->connect_error) ? cnido_stats($__statConn) : array();
	if (!$S) {
		echo "<p style='color:#c00;'>Statistics are temporarily unavailable "
		   . "(no cached snapshot and the database could not be reached). "
		   . "Please try again later.</p>";
	} else {
	$__defs = cnido_stat_defs();
	$__def  = function ($k) use ($__defs) {
		return isset($__defs[$k])
			? " title='" . htmlspecialchars($__defs[$k], ENT_QUOTES, 'UTF-8') . "'"
			: '';
	};

	echo "<table class=\"gridtable ds-kv\">";
	echo "<tr><th>Classes/Orders/Families/Species</th><td align='center'><a href='./browse.php?class=all'><abbr" . $__def('tax_class') . ">" . cnido_num($S['tax_class']) . "/" . cnido_num($S['tax_order']) . "/" . cnido_num($S['tax_family']) . "/" . cnido_num($S['tax_species']) . "</abbr></a></td></tr>";
	echo "<tr><th>Genomes with gene models</th><td align='center'><a href='./genomeinfo.php'><abbr" . $__def('genome_gene_models') . ">" . cnido_num($S['genome_gene_models']) . "</abbr></a></td></tr>";
	echo "<tr><th>Genomes with functional annotation</th><td align='center'><a href='./genomeinfo.php'><abbr" . $__def('genome_functional_annotation') . ">" . cnido_num($S['genome_functional_annotation']) . "</abbr></a></td></tr>";
	echo "<tr><th>Species in JBrowse / tracks</th><td align='center'><a href='./jbrowse.php'><abbr" . $__def('jbrowse_tracks') . ">" . cnido_num($S['jbrowse_species']) . "/" . cnido_num($S['jbrowse_tracks']) . "</abbr></a></td></tr>";
	echo "<tr><th>Paleobiological Records</th><td align='center'><a href='./paleobiology.php'><abbr" . $__def('paleo') . ">" . cnido_num($S['paleo']) . "</abbr></a></td></tr>";
	echo "<tr><th>BUSCO genes</th><td align='center'><a href='./busco.php'><abbr" . $__def('busco') . ">" . cnido_num($S['busco']) . "</abbr></a></td></tr>";
	echo "<tr><th>Protein-Coding Transcripts</th><td align='center'><a href='./search.php'><abbr" . $__def('genes_protein_coding') . ">" . cnido_num($S['genes_protein_coding']) . "</abbr></a></td></tr>";
	echo "<tr><th>Transcriptomic Datasets</th><td align='center'><a href='./cytoscape/trans_data.php'><abbr" . $__def('transcriptome') . ">" . cnido_num($S['transcriptome']) . "</abbr></a></td></tr>";
	echo "<tr><th>Single-cell RNA-seq Datasets</th><td align='center'><a href='./sn_data.php'><abbr" . $__def('singlecell') . ">" . cnido_num($S['singlecell']) . "</abbr></a></td></tr>";
	echo "<tr><th>Proteomic Datasets</th><td align='center'><a href='./proteomic_data.php'><abbr" . $__def('proteome') . ">" . cnido_num($S['proteome']) . "</abbr></a></td></tr>";
	echo "<tr><th>Quantified Proteins</th><td align='center'><a href='./proteomic_analysis.php'><abbr" . $__def('proteins_quantified') . ">" . cnido_num($S['proteins_quantified']) . "</abbr></a></td></tr>";
	echo "<tr><th>Epigenomic Datasets</th><td align='center'><a href='./epigenomic_data.php'><abbr" . $__def('epigenome') . ">" . cnido_num($S['epigenome']) . "</abbr></a></td></tr>";
	echo "<tr><th>Metagenomic Datasets</th><td align='center'><a href='./metagenomic_data.php'><abbr" . $__def('metagenome') . ">" . cnido_num($S['metagenome']) . "</abbr></a></td></tr>";
	echo "<tr><th>Phenotype Datasets</th><td align='center'><a href='./phenotype.php?class=all'><abbr" . $__def('phenotype') . ">" . cnido_num($S['phenotype']) . "</abbr></a></td></tr>";
	echo "<tr><th>Mitochondrial Genomes</th><td align='center'><a href='./mitdata.php'><abbr" . $__def('mitochondrial') . ">" . cnido_num($S['mitochondrial']) . "</abbr></a></td></tr>";
	echo "<tr><th>Co-Expressed Networks/Gene Pairs</th><td align='center'><a href='./cytoscape/network.php'><abbr" . $__def('coexpress_pairs') . ">" . cnido_num($S['coexpress_networks']) . "/" . cnido_num($S['coexpress_pairs']) . "</abbr></a></td></tr>";
	echo "<tr><th>Cell types</th><td align='center'><a href='./cell_marker.php'><abbr" . $__def('cell_types') . ">" . cnido_num($S['cell_types']) . "</abbr></a></td></tr>";
	echo "<tr><th>Cell markers</th><td align='center'><a href='./cell_marker.php'><abbr" . $__def('cell_markers') . ">" . cnido_num($S['cell_markers']) . "</abbr></a></td></tr>";
	echo "<tr><th>Epigenetic Modification Type/Peaks</th><td align='center'><abbr" . $__def('epigenome_types') . ">" . cnido_num($S['epigenome_types']) . "/" . cnido_num($S['epigenome_peaks']) . "</abbr></td></tr>";
	echo "<tr><th>Methylated Cytosine Sites (Bisulfite-Seq)</th><td align='center'><abbr" . $__def('epigenome_cpg') . ">" . cnido_num($S['epigenome_cpg']) . "</abbr></td></tr>";
	echo "<tr><th>Metagenome-Assembled Genomes</th><td align='center'><a href='./MAGs.php'><abbr" . $__def('mags') . ">" . cnido_num($S['mags']) . "</abbr></a></td></tr>";
	echo "<tr><th>Types of gene annotation</th><td align='center'><abbr" . $__def('annotation_types') . ">" . cnido_num($S['annotation_types']) . "</abbr></td></tr>";
	echo "<tr><th>NR / UniProt</th><td align='center'><abbr" . $__def('nr') . ">" . cnido_num($S['nr']) . "</abbr> / <abbr" . $__def('uniprot') . ">" . cnido_num($S['uniprot']) . "</abbr></td></tr>";
	echo "<tr><th>GO / InterPro / Pfam / PANTHER / KEGG annotation</th><td align='center'><abbr" . $__def('go') . ">" . cnido_num($S['go']) . "</abbr> / <abbr" . $__def('interpro') . ">" . cnido_num($S['interpro']) . "</abbr> / <abbr" . $__def('pfam') . ">" . cnido_num($S['pfam']) . "</abbr> / <abbr" . $__def('panther') . ">" . cnido_num($S['panther']) . "</abbr> / <abbr" . $__def('kegg') . ">" . cnido_num($S['kegg']) . "</abbr></td></tr>";
	echo "<tr><th>TFs/Ubiquitin families</th><td align='center'><a href='./gene_family.php'><abbr" . $__def('tf') . ">" . cnido_num($S['tf']) . "</abbr>/<abbr" . $__def('ubiquitin') . ">" . cnido_num($S['ubiquitin']) . "</abbr></a></td></tr>";
	/* $S['genefamily'] 是 genefamily 表的**行数**（3,621,064），即基因→家族的
	   归属记录数；真正的「家族数」是 $S['genefamily_num']（genefamily_num 表，
	   113,207）。原来这一行表头写「Orthologous Families」却印归属记录数，读者
	   会以为站里有三百六十万个直系同源家族 —— 而 includes/stats.php 自己的标签
	   表把这两个量分别叫 'Gene family assignments' 与 'Orthologous families'。
	   两个数一起印，顺带把口径写清楚。 */
	echo "<tr><th>Orthologous gene families</th><td align='center'><a href='./genefamily.php'><abbr" . $__def('genefamily') . ">" . cnido_num($S['genefamily']) . "</abbr> assignments in <abbr" . $__def('genefamily_num') . ">" . cnido_num($S['genefamily_num']) . "</abbr> families</a></td></tr>";
	echo "</table>";

	/* 说明数字的来源与新鲜度：这些值由 includes/stats.php 从数据库现算并缓存，
	   网页只读缓存（一次全量计算约 79 秒，见该文件顶部说明），
	   所以这里把计算时间标出来，并指回 Release 页的更新计划。 */
	$__gen = cnido_stats_generated();
	echo "<p class='paleo-intro' style='font-size:16px;color:#666;margin-top:8px;'>"
	   . "Counts are computed directly from the database"
	   . ($__gen !== '' ? " and were last refreshed on <b>" . htmlspecialchars($__gen, ENT_QUOTES, 'UTF-8') . "</b>" : "")
	   . ". Hover any figure for the exact definition of what it counts. See the "
	   . "<a href='./release.php'>release notes</a> for the database version and update schedule."
	   . "</p>";
	}
?>
<br><br><div class="clr"></div>
</div>

<?php /* 物种 × 数据类型覆盖矩阵（原 coverage_matrix.php 的内容，按要求移到
     Software and Analytical Tools 之前）
     id 是这个卡片的锚点：本卡内的翻页/排序/筛选链接与表单都带 #coverage-matrix
     （见 includes/coverage_matrix_view.php 的 anchor），否则搜一次就被甩回页面顶部，
     用户看不到刚搜出来的东西（2026-09-26 用户报的问题）。 */ ?>
<div class="stats-card" id="coverage-matrix">
    <div class="card-title">Species &times; Data-Type Coverage Matrix</div>

    <p class="paleo-intro" style="margin-top:0">
        Which species have which data? The matrix covers every cnidarian species in CnidoSite &times; every data type it
        holds, and the legend below the table explains the three cell symbols. The same table also lives at
        <a href="./coverage_matrix.php">coverage_matrix.php</a>, and is downloadable as tab-separated text.
    </p>

<?php
/* 卡片与表共用同一份渲染（includes/coverage_matrix_view.php），
   所以统计页与独立页永远一致。链接指向本页，翻页/排序不会跳出统计页。

   连接不可用时（上面 cnido_stats 已经报过一次错）干脆不画表：cnido_coverage()
   在数据库断掉时会把「扫不到任何东西」的空矩阵写进缓存，之后所有页面读到的
   都是空表，比少画一块严重得多。 */
if ($__statConn && !$__statConn->connect_error) {
    /* per_page：这一页的矩阵一页 20 个物种（本页别的表也是 20 行一页，同一页上
       两张表各走各的行数会让人以为哪边漏了数据）。候选里另有 All —— 326 行通读
       或 Ctrl+F 时要的。 */
    echo cnido_cm_html($__statConn, array(
        'base'             => 'data_statistics.php',
        'anchor'           => '#coverage-matrix',
        'per_page'         => 20,
        'per_page_choices' => array(20, 50, 100, 'all'),
    ));
} else {
    echo "<p style='color:#c00;'>The coverage matrix is temporarily unavailable "
       . "(the database could not be reached). Please try again later.</p>";
}
?>
</div>

<?php /* 基因组组装卡片（表 1）。放在 Software and Analytical Tools 之上。
     数据来自 genome_assembly 表，由 includes/genome_assembly_refresh.php 从 NCBI
     Datasets API 灌入 —— speciesinfo 每个物种只记一个组装，答不了「有几个版本、
     本站用哪个」。 */ ?>
<div class="stats-card" id="genome-assemblies">
    <div class="card-title genome">Genome Assemblies</div>
<?php
if ($__statConn && !$__statConn->connect_error) {
    /* anchor：本卡内的搜索/翻页/每页条数都落在卡片顶部，不再被甩回页面顶部。 */
    echo cnido_ga_html($__statConn, array('anchor' => '#genome-assemblies'));
} else {
    echo "<p style='color:#c00;'>The genome assembly catalogue is temporarily unavailable "
       . "(the database could not be reached). Please try again later.</p>";
}
?>
<br /><div class="clr"></div>
</div>

<?php /* 基因-文献卡片（表 2）。同样在 Software and Analytical Tools 之上。
     数据来自 gene_literature / literature 两张表，由
     includes/gene_literature_refresh.php 从 NCBI gene2pubmed 灌入。 */ ?>
<div class="stats-card" id="gene-literature">
    <div class="card-title lit">Gene Literature</div>
<?php
if ($__statConn && !$__statConn->connect_error) {
    /* anchor：同上，搜索/翻页都落在本卡顶部。 */
    echo cnido_gl_html($__statConn, array('anchor' => '#gene-literature'));
} else {
    echo "<p style='color:#c00;'>The gene literature index is temporarily unavailable "
       . "(the database could not be reached). Please try again later.</p>";
}
?>
<br /><div class="clr"></div>
</div>

<?php /* 软件信息卡片 */ ?>
<div class="stats-card">
    <div class="card-title software">Software and Analytical Tools</div>
 <table class="gridtable software-table"><tr><th>Module</th><th>Software</th><th>Parameter</th><th width="30%">Links</th></tr>
<tr><td align="center">Fossil Records</td><td align="center">PBDB</td><td align="center">default</td><td>https://paleobiodb.org/#/</td></tr>
<tr><td align="center">Gene Annotation</td><td align="center">InterProScan v.5.67-97.0</td><td align="center">-f tsv -goterms -pa</td><td>https://github.com/ebi-pf-team/interproscan</td></tr>
<tr><td align="center">BUSCO Genes</td><td align="center">BUSCO v.5.8.2, v.6.0.0 <span style="color:#666">(27 species re-scored)</span><br>BUSCO v.6.0.0 <span style="color:#666">(genome mode)</span></td><td align="center">-l cnidaria_odb12 -m proteins<br>-l cnidaria_odb12 -m genome --miniprot <span style="color:#666">(the one species with no annotation)</span></td><td>https://gitlab.com/ezlab/busco</td></tr>
<tr><td align="center">Transposable Elements</td><td align="center">RepeatModeler v.2.0.7<br>RepeatModeler v.4.2.1</td><td align="center">-engine rmblast -LTRStruct<br>-gff -e rmblast -nolow</td><td>https://github.com/Dfam-consortium/RepeatModeler<br>https://github.com/Dfam-consortium/RepeatMasker</td></tr>
<tr><td align="center">TFs/Ubs</td><td align="center">HMMER v.3.4</td><td align="center">cut-off: 0.0001</td><td>http://hmmer.org/</td></tr>
<tr><td align="center">Gene Family</td><td align="center">OrthoFinder v.2.5.5</td><td align="center">default</td><td>https://github.com/davidemms/OrthoFinder</td></tr>
<tr><td align="center">Pan-geneset</td><td align="center">Count</td><td align="center">Wagner_parsimony -gain 1 -max_paralogs 200<br>Posteriors -max_paralogs 200</td><td>https://www.iro.umontreal.ca/~csuros/gene_content/count.html</td></tr>
<tr><td align="center">Mitogenomic Datasets</td><td align="center">OGDRAW v.1.3.1</td><td align="center">default</td><td>https://chlorobox.mpimp-golm.mpg.de/OGDraw.html</td></tr>
<tr><td align="center">Proteomic Datasets</td><td align="center">Blastp</td><td align="center">default</td><td>https://blast.ncbi.nlm.nih.gov/Blast.cgi</td></tr>
<tr><td align="center">Epigenomic Datasets</td><td align="center">bowtie2 v.2.5.5<br>MACS2 v.2.2.9.1</td><td align="center">bowtie2: --very-sensitive --dovetail --no-mixed --no-discordant<br>
ChIP-seq: <code>callpeak -t &lt;sample BAMs&gt; -c &lt;input/IgG control BAMs&gt; -f BAMPE -g &lt;effective genome size&gt;</code> <span style="color:#666">(paired-end libraries; <code>-f BAM</code> where single-end)</span><br>
ChIP-seq, broad marks <span style="color:#666">(H3K27me3, H3K36me3, H3K4me1, H4K20me1)</span>: <code>callpeak -t &lt;sample BAMs&gt; -c &lt;input/IgG control BAMs&gt; -f BAMPE -g &lt;effective genome size&gt; --broad --broad-cutoff 0.1</code><br>
ATAC-seq: <code>callpeak -t &lt;sample BAMs&gt; -c &lt;control BAMs&gt; -f BAMPE -g &lt;effective genome size&gt;</code> <span style="color:#666">(paired-end libraries; <code>-f BAM</code> where single-end)</span><br>
DNase-seq: <code>callpeak -t &lt;sample BAM&gt; -f BAM -g 4.38e8</code><br>
<span style="color:#666">Peak model built from cross-correlation per dataset (no --nomodel / --shift / --extsize: the Tn5 shift correction is not applied, and is not applicable to ChIP-seq or DNase-seq). Effective genome size per species, e.g. 2.61e8 N. vectensis, 8.81e8 H. vulgaris, 1.98e8 E. diaphana, 4.38e8 A. digitifera.</span></td><td>https://github.com/macs3-project/MACS<br>https://bowtie-bio.sourceforge.net/bowtie2/</td></tr>
<tr><td align="center">Co-expression Network</td><td align="center">Cytoscape v3.10.3</td><td align="center">default</td><td>https://cytoscape.org/</td></tr>
<tr><td align="center">Single-cell Datasets</td><td align="center">Seurat v4.4</td><td align="center">LogNormalize</td><td>https://satijalab.org/seurat</td></tr>
<tr><td align="center">Species Tree</td><td align="center">phylotree</td><td align="center">default</td><td>https://www.phylotree.org/</td></tr>
<tr><td align="center">Primer Design</td><td align="center">primer3 v.2.6.1 <span style="color:#666">(self-hosted, via the Primer3Plus front end)</span></td><td align="center">Primer3Plus defaults with two site overrides: <code>PRIMER_THERMODYNAMIC_PARAMETERS_PATH</code> and <code>PRIMER_MISPRIMING_LIBRARY</code> point at this server</td><td>https://github.com/primer3-org/primer3</td></tr>
<tr><td align="center">Database Construction</td><td align="center">LAMP</td><td align="center">default</td><td>https://github.com/teddysun/lamp</td></tr>
</table>
</div>

</div>
</div>
</div>

<script type="text/javascript">
<?php /* 三张卡片（覆盖矩阵 / Genome Assemblies / Gene Literature）的搜索框、翻页、
   换每页条数、筛选都是整页 GET 跳转。跳转后要把窗口停在用户刚操作的那一块的
   开头，否则用户看到的是页面最顶部（矩阵那张大表 + 图表），搜索结果在 9000px
   之外 —— 2026-09-26 报的两个问题都是这个。

   链接和表单的 action 里都挂了对应卡片的 #片段，浏览器**通常**会据此滚动；
   但实测它靠不住，所以这里自己再滚一遍，不把这件事交给浏览器：
     · 地址里带的片段和当前文档相同时，有的浏览器不再滚（回到顶部的典型场景）；
     · 原生滚动要等解析器跑完，而页脚的访客地图 map.js 是同步外链，能把解析卡住
       好几秒（见 memory：页脚第三方脚本会阻塞解析器）——这几秒里用户看到的就是
       页面开头；
     · 滚完之后，上面的图表 / 图片才渲染出来会把内容顶下去，位置就漂了。
   所以：解析到这段就立刻滚一次，DOMContentLoaded / load 再各滚一次，之后还有
   几次延时校正。脚本写在页脚 include **之前** —— 页脚的 map.js 是同步外链，
   排到它后面就要白等几秒。用户自己滚过（滚轮 / 触摸 / 按键 / 拖滚动条）就不再
   打扰他。
   地址里没有片段时（分享或手打出来的裸深链，例如 ?lq=SLC5A11），按「参数名属于
   哪张卡片」推断目标。 */ ?>
(function () {
    var el = null, i, j, kv, raw, keys = {}, map;
    if (location.hash) {
        el = document.getElementById(location.hash.replace(/^#/, ''));
    }
    if (!el) {
        raw = location.search.replace(/^\?/, '').split('&');
        for (i = 0; i < raw.length; i++) {
            kv = raw[i].split('=');
            if (kv[0]) { keys[decodeURIComponent(kv[0])] = true; }
        }
        map = [
            [['gp', 'gq', 'gpp'], 'genome-assemblies'],
            [['lp', 'lq', 'lpp', 'lsp', 'lann', 'lexp'], 'gene-literature'],
            [['page', 'q', 'per_page', 'class', 'mod', 'min', 'sort'], 'coverage-matrix']
        ];
        for (i = 0; i < map.length && !el; i++) {
            for (j = 0; j < map[i][0].length; j++) {
                if (keys[map[i][0][j]]) { el = document.getElementById(map[i][1]); break; }
            }
        }
    }
    if (!el || !el.scrollIntoView) { return; }

    <?php /* 后退/前进时不要动 —— 那时用户要的是「回到我刚才读到的地方」，浏览器存下的
       滚动位置比「卡片开头」更准。只有新打开 / 刷新 / 表单跳转才强行定位。 */ ?>
    var navType = '';
    try {
        var ent = window.performance && performance.getEntriesByType
                ? performance.getEntriesByType('navigation') : null;
        if (ent && ent.length) { navType = ent[0].type; }
        else if (window.performance && performance.navigation) {
            navType = (performance.navigation.type === 2) ? 'back_forward' : '';
        }
    } catch (err) { navType = ''; }
    if (navType === 'back_forward') { return; }

    var taken = false;
    var mine  = function () { taken = true; };
    <?php /* 认「用户主动」的输入事件 */ ?>
    if (window.addEventListener) {
        window.addEventListener('wheel', mine, { passive: true });
        window.addEventListener('touchstart', mine, { passive: true });
        window.addEventListener('mousedown', mine);
        window.addEventListener('keydown', mine);
    }
    <?php /* 还要防一手拖滚动条：那不发 mousedown。判据是「滚动位置明显离开了我们
       放下的地方」，而不是「有没有 scroll 事件」—— 我们自己的滚动、浏览器自己
       的片段滚动都会发 scroll，按事件本身判会把两种都误当成用户。 */ ?>
    var want = null;
    if (window.addEventListener) {
        window.addEventListener('scroll', function () {
            if (want === null || taken) { return; }
            if (Math.abs(window.scrollY - want) > 40) { taken = true; }
            else { want = window.scrollY; }     /* 布局漂移带来的小变化，跟着走 */
        }, { passive: true });
    }
    var place = function () {
        if (taken) { return; }
        el.scrollIntoView();
        want = window.scrollY;
    };

    place();                                    /* 解析到这里时目标已经在 DOM 里 */
    if (document.readyState === 'loading' && document.addEventListener) {
        document.addEventListener('DOMContentLoaded', place);
    }
    if (window.addEventListener) { window.addEventListener('load', place); }
    <?php /* 之后再校正几次：上面的图表渲染完、字体和图片落位之后位置都可能变 */ ?>
    var delays = [300, 800, 1600, 3000, 5000], k;
    for (k = 0; k < delays.length; k++) { setTimeout(place, delays[k]); }
})();
</script>
<?php
    include "./Webpage_components.php";
    print $footer;
?>
</body>
</html>
