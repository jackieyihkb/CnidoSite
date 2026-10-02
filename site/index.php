<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>CnidoSite</title>
<meta name="keywords" content="Cnidaria, database, deep ocean, multi-omics" />
<meta name="description" content="Comprehensive multi-omics resource for deep ocean cnidarian species" />
<link rel="icon" href="/favicon.ico" type="image/x-icon">
<link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script src="js/jquery-1.10.2.min.js"></script>
<link rel="stylesheet" href="/css/leaflet.css" type="text/css" media="screen,projection" />
<script language="javascript" type="text/javascript">
function clearText(field)
{
    if (field.defaultValue == field.value) field.value = '';
    else if (field.value == '') field.value = field.defaultValue;
}
</script>
<style>
.hero-image-container {
    text-align: center;
    padding: 30px 0;
    margin: 20px 0;
    width: 100%;
    <?php /* 关键：用 Flex 布局让图片垂直+水平居中 */ ?>
    display: flex;
    justify-content: center;
    align-items: center;
    <?php /* 可选：给容器足够的高度，避免内容挤压 */ ?>
    min-height: 400px; 
}

.main-banner-img {
    <?php /* 1. 基础适配：小屏幕下自动缩放 */ ?>
    width: 90%;
    height: auto;
    
    <?php /* 2. 核心修改：大屏幕下最大化显示（取消原有 max-width 限制） */ ?>
    max-width: 100%; /* 图片宽度不超过屏幕宽度 */
    max-height: 80vh; /* 高度不超过屏幕的 80%（防止撑满整个屏幕） */
    
    <?php /* 3. 视觉优化：保持圆角和阴影 */ ?>
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    
    <?php /* 4. 确保图片完整显示（不被裁剪） */ ?>
    object-fit: contain; 
}

<?php /*  hover 动效（可选） */ ?>
.main-banner-img:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

<?php /* 响应式调整（小屏幕下保持可用性） */ ?>
@media (max-width: 768px) {
    .hero-image-container {
        padding: 15px 0;
        margin: 10px 0;
        min-height: auto; /* 小屏幕下取消固定高度 */
    }
    .main-banner-img {
        max-width: 95%; /* 平板下留边 */
        max-height: 60vh; /* 降低高度占比 */
        border-radius: 8px;
    }
}

@media (max-width: 480px) {
    .main-banner-img {
        max-width: 98%; /* 手机下几乎全屏 */
        max-height: 50vh; /* 进一步降低高度占比 */
        border-radius: 6px;
    }
}

<?php /* ===== 系统发育树：点击查看大图 =====
   首页栏宽只有 610px，而 images/all-tree.png 本身是 5880x2880，
   直接内嵌等于丢弃了大部分分辨率，因此点击后在灯箱里按视口显示，
   还能再切到 100% 原始像素逐段查看。 */ ?>
.phylogeny-link {
    display: inline-block;
    max-width: 100%;
    line-height: 0; /* 去掉 <a> 内联布局留下的基线空隙 */
}
.phylogeny-thumb {
    max-width: 100%;
    height: auto;
    cursor: zoom-in;
    border-radius: 4px;
    transition: box-shadow 0.3s ease;
}
.phylogeny-thumb:hover {
    box-shadow: 0 0 0 3px rgba(51, 102, 153, 0.25);
}
.phylogeny-hint {
    font-size: 15px;
    color:#64748b;
    margin: 2px 0 0;
}
<?php /* 必须带 #column_w610 前缀：templatemo_style.css 里的 `#column_w610 p { padding-bottom: 20px }`
   是 ID 选择器，单靠 .phylogeny-hint 这个类选择器压不住它，
   这一行下面就会多出一大块空白。 */ ?>
#column_w610 .phylogeny-hint {
    padding-bottom: 0;
}

.tree-lightbox {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.92);
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 30px 20px 74px;
}
.tree-lightbox.show { display: flex; }

.tree-lightbox-stage {
    flex: 1 1 auto;
    width: 100%;
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.tree-lightbox img {
    <?php /* 用 100% 而不是 96vw：灯箱容器本身已有 30px/20px 的内边距，
       再按视口宽度算会把图撑出 stage，被 overflow:hidden 裁掉。 */ ?>
    max-width: 100%;
    max-height: 100%;
    border-radius: 6px;
    background: #fff;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    cursor: zoom-in;
}

<?php /* 放大到 100% 时改成可滚动的画布，靠滚动平移查看细部 */ ?>
.tree-lightbox.zoomed .tree-lightbox-stage {
    overflow: auto;
    align-items: flex-start;
    justify-content: flex-start;
}
.tree-lightbox.zoomed img {
    max-width: none;
    max-height: none;
    width: auto;
    border-radius: 0;
    cursor: zoom-out;
}

.tree-lightbox-close {
    position: absolute;
    top: 18px;
    right: 26px;
    color: #fff;
    font-size: 36px;
    line-height: 1;
    font-weight: 300;
    cursor: pointer;
}
.tree-lightbox-close:hover { color: #9ec5e8; }

.tree-lightbox-bar {
    position: absolute;
    bottom: 20px;
    left: 0;
    right: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 16px;
    color:#64748b;
    font-size: 15px;
}
.tree-lightbox-bar button {
    background: #336699;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 8px 16px;
    font-size: 15px;
    cursor: pointer;
}
.tree-lightbox-bar button:hover { background: #274f77; }
.tree-lightbox-bar a { color: #9ec5e8; }

@media (max-width: 768px) {
    .tree-lightbox { padding: 16px 8px 86px; }
    .tree-lightbox-close { top: 8px; right: 14px; }
    .tree-lightbox-bar { gap: 10px; }
}

<?php /* 打印时隐藏灯箱，只保留页面内的树 */ ?>
@media print {
    .tree-lightbox { display: none !important; }
}

<?php /* Recent Updates：每个版本一行 —— 「日期 · 版本号 — 一句话概括」 */ ?>
ul.df_list.ru-list { margin-bottom: 0; }
ul.df_list.ru-list li.ru-release { margin-bottom: 7px; line-height: 1.5; }
ul.df_list.ru-list li.ru-release:last-child { margin-bottom: 0; }
<?php /* .df_list span 在样式表里是 float:right（给统计栏的数字用），这里的概括文字要留在行内 */ ?>
ul.df_list.ru-list span.ru-sum { float: none; color: #555; }
.ru-more {
    margin: 10px 0 0 0;
    padding: 0 10px 0 25px;
    font-size: 16px;
    color: #666;
}
</style>
<script type="text/javascript" src="js/leaflet.js"></script>
</head>
<body>
<?php
	include "Webpage_components.php";
	print $header;

	/* 首页上所有统计数字都取自 includes/stats.php 这一份口径，
	   与 data_statistics.php 共用，避免两页再出现 148/145 这类互相矛盾的值
	   （审稿意见 Referee 2 major 12）。 */
	require_once __DIR__ . '/includes/stats.php';
	$__statConn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	$S = ($__statConn && !$__statConn->connect_error) ? cnido_stats($__statConn) : array();

	/* $S 为空只可能出现在「缓存文件缺失 且 数据库连不上」的情况。
	   这时不能把 0 当成统计量印出来（“Spans 0 species”比不显示更糟），
	   所以整段改成不带数字的表述，并在统计栏说明原因。 */
	$__hasStats = !empty($S);
	/* 用闭包而不是函数声明：测试脚本会在同一个进程里多次 include 本页，
	   顶层 function 声明第二次会 fatal（Cannot redeclare）。 */
	$__cnum = function ($k) use ($S) { return cnido_num(cnido_stat($S, $k)); };
?>

<div id="tempatemo_content_wrapper">
    <div id="templatemo_content">
        <?php /* 主图区域 - 增加适当边距 */ ?>
		        <?php /* 应用新样式的图片区域 */ ?>
        <div class="hero-image-container">
            <img src="images/mainpage.png" alt="CnidoSite Overview" class="main-banner-img">
        </div>
        <div id="content_panel">
            <div id="column_w610">
                <?php /* 数据库介绍 */ ?>
                <legend><img src="./images/header.jpg" height="45px" style="margin-bottom:-10px">&nbsp;<b>CnidoSite</b></legend>
                <div style="padding: 0 10px;">
                    <p style="text-align:justify; line-height: 1.8; color: #444; margin-bottom: 25px;">
                        The phylum Cnidaria&mdash;comprising corals, jellyfish, sea anemones, and hydroids&mdash;is a key player in marine ecosystems, yet these animals face unprecedented decline under climate change, most visibly through mass coral bleaching and disruptive jellyfish blooms. The ecological consequences are severe: collapse of reef habitats, loss of marine biodiversity, and disruption of coastal economies. Unravelling the molecular basis of coral thermal tolerance, environmental adaptation, and coral&ndash;algal symbiosis has therefore become a pressing global priority in marine environment and conservation research. At the same time, as one of the earliest-branching animal lineages, cnidarians are indispensable for understanding the evolution of multicellularity, the origin of animals, tissue organization, and developmental complexity. Progress in this field, however, has been hampered by the lack of centralized, high-quality multi-omics resources.
                    </p>
                    <p style="text-align:justify; line-height: 1.8; color: #444; margin-bottom: 25px;">
                        To bridge this gap, we present <b>CnidoSite</b>, the first comprehensive, analysis-enabled multi-omics platform dedicated to the phylum Cnidaria. <?php if ($__hasStats): ?>Spanning <?php echo $__cnum('tax_species');?> species across <?php echo $__cnum('tax_class');?> classes, <?php echo $__cnum('tax_order');?> orders, and <?php echo $__cnum('tax_family');?> families, CnidoSite integrates <?php echo $__cnum('genome_functional_annotation');?> annotated genome assemblies, <?php echo $__cnum('transcriptome');?> bulk transcriptomes, <?php echo $__cnum('singlecell');?> single-cell datasets, <?php echo $__cnum('proteome');?> proteomes, <?php echo $__cnum('epigenome');?> epigenomes, <?php echo $__cnum('metagenome');?> metagenomic samples, and <?php echo $__cnum('phenotype');?> phenotype records (traits integrated from three curated databases), complemented by<?php else: ?>CnidoSite integrates genome assemblies, bulk transcriptomes, single-cell datasets, proteomes, epigenomes, metagenomic samples and phenotype records (traits integrated from three curated databases), together with<?php endif; ?> advanced toolkits for functional and comparative analyses from a multidimensional perspective. Functional genomic resources—including genome assemblies, genome phylogenies, fossil entries, functional annotations, BUSCO genes, and single-cell and mitochondrial annotations—are also provided. The platform further features <?php if ($__hasStats): ?><?php echo $__cnum('coexpress_networks');?> co-expression networks<?php else: ?>co-expression networks<?php endif; ?> with cross-tissue expression views and a high-resolution single-cell atlas with curated cell markers, mapping developmental and stress-responsive landscapes.
                    </p>
                    <p style="text-align:justify; line-height: 1.8; color: #444;">
                        In summary, CnidoSite serves as a centralized, analysis-ready resource for addressing key scientific questions&mdash;including coral bleaching, symbiosis breakdown, jellyfish blooms, environmental adaptation, and evolution&mdash;that sit at the intersection of cnidarian biology, marine conservation, comparative genomics, and evolutionary biology. The website is freely accessible to all users, without login or registration.
                    </p>
                </div>
                
                <?php /* 系统发育树 */ ?>
                <legend style="margin-top: 40px;"><img src="./images/header.jpg" height="45px" style="margin-bottom:-10px">&nbsp;<b>Cnidaria Phylogeny</b></legend>
                <div style="text-align: center; padding: 15px; background-color: #f9f9f9; border-radius: 4px; margin: 0 10px;">
                    <?php /* 用 <a> 包一层：没有 JS 时退化成「新标签页打开原图」，浏览器自带缩放；
                         有 JS 时由 tree-lightbox 接管这次点击。 */ ?>
                    <a href="images/all-tree.png" id="cnidariaTreeLink" class="phylogeny-link" target="_blank" rel="noopener" title="Click to view the full-size tree"><img src="images/all-tree.png" alt="Cnidaria Phylogenetic Tree" class="phylogeny-thumb" /></a>
                    <p style="font-size: 16px; color: #666; margin-top: 10px; padding-bottom: 0; font-style: italic;">Phylogenetic relationships of cnidarian species included in the <b>CnidoSite</b> database.</p>
                    <p class="phylogeny-hint">Click the tree to view it full screen</p>
                </div>
                
                <?php /* 联系我们 */ ?>
                <legend style="margin-top: 40px;"><img src="./images/header.jpg" height="45px" style="margin-bottom:-10px">&nbsp;<b>Contact Us</b></legend>
                <div style="padding: 0 10px; background-color: #f8fafc; border-radius: 4px; margin: 0 10px; padding: 20px;">
                    <p style="text-align:justify; font-size: 16px; line-height: 1.7; color: #444; margin-bottom: 20px;">
                        For data inquiries, collaborations, or technical support, please contact our team:
                    </p>
                    <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
                        <div style="flex: 1; min-width: 200px; padding: 12px; background: white; border-radius: 4px; border-left: 3px solid #336699;">
                            <strong style="color: #336699;">Prof. Longjun Wu</strong><br>
                            <a href="mailto:longjunwu@ust.hk">longjunwu@ust.hk</a>
                        </div>
                        <div style="flex: 1; min-width: 200px; padding: 12px; background: white; border-radius: 4px; border-left: 3px solid #336699;">
                            <strong style="color: #336699;">Prof. Pei-yuan Qian</strong><br>
                            <a href="mailto:boqianpy@ust.hk">boqianpy@ust.hk</a>
                        </div>
                        <div style="flex: 1; min-width: 200px; padding: 12px; background: white; border-radius: 4px; border-left: 3px solid #336699;">
                            <strong style="color: #336699;">Dr. Jiajie She</strong><br>
                            <a href="mailto:jackieyihkb@ust.hk">jackieyihkb@ust.hk</a>
                        </div>
                    </div>
                    <p style="text-align:justify; font-size: 16px; line-height: 1.6; color: #555;">
                        Learn more about our research: 
                        <a href="https://longjunwulab.org/" target="_blank" style="color: #336699; font-weight: 500;" rel="noopener noreferrer">Longjun Wu's Lab</a> | 
                        <a href="https://qianlab.hkust.edu.hk/" target="_blank" style="color: #336699; font-weight: 500;" rel="noopener noreferrer">Pei-yuan Qian's Lab</a>
                    </p>
                </div>
            </div>
            
            <div id="column_w290">
                <?php /* 数据统计 */ ?>
                <legend><img src="./images/header.jpg" height="45px" style="margin-bottom:-10px">&nbsp;<b>Data Statistics</b></legend>
                <div style="padding: 10px; background-color: #f9f9f9; border-radius: 0 0 4px 4px;">
                    <?php if (!$__hasStats): ?>
                    <p style="color:#c00;font-size:15px;">Statistics are temporarily unavailable. Please try again later; for the current figures see <a href="./data_statistics.php">Data Statistics</a>.</p>
                    <?php else: ?>
                    <ul class="df_list">
                        <li>Class/Order/Family/Species <span><a href='./browse.php?class=all'><?php echo cnido_num($S['tax_class']);?>/<?php echo cnido_num($S['tax_order']);?>/<?php echo cnido_num($S['tax_family']);?>/<?php echo cnido_num($S['tax_species']);?></a></span></li>
						<li>Genomes with Gene Models <span><a href='./genomeinfo.php'><?php echo cnido_num($S['genome_gene_models']);?></a></span></li>
						<li>Genomes with Functional Annotation <span><a href='./genomeinfo.php'><?php echo cnido_num($S['genome_functional_annotation']);?></a></span></li>
                        <li>Transcriptomic Datasets <span><a href='./cytoscape/trans_data.php'><?php echo cnido_num($S['transcriptome']);?></a></span></li>
                        <li>Single-Cell Datasets <span><a href='./sn_data.php'><?php echo cnido_num($S['singlecell']);?></a></span></li>
                        <li>Proteomic Datasets <span><a href='./proteomic_data.php'><?php echo cnido_num($S['proteome']);?></a></span></li>
                        <li>Epigenomic Datasets <span><a href='./epigenomic_data.php'><?php echo cnido_num($S['epigenome']);?></a></span></li>
                        <li>Metagenomic Datasets <span><a href='./metagenomic_data.php'><?php echo cnido_num($S['metagenome']);?></a></span></li>
                        <li>Phenotype Records <span><a href='./phenotype.php?class=all'><?php echo cnido_num($S['phenotype']);?></a></span></li>
						<li>Species with a Mitochondrial Genome <span><a href='./mitdata.php'><?php echo cnido_num($S['mitochondrial']);?></a></span></li>
                        <li>Fossil Records <span><a href='./paleobiology.php'><?php echo cnido_num($S['paleo']);?></a></span></li>
                        <li>Protein-Coding Transcripts <span><?php echo cnido_num($S['genes_protein_coding']);?></span></li>
                        <li>BUSCO Genes <span><a href='./busco.php'><?php echo cnido_num($S['busco']);?></a></span></li>
                        <li>Co-Expressed Networks <span><?php echo cnido_num($S['coexpress_networks']);?></span></li>
						<li>Co-Expressed Gene Pairs <span><?php echo cnido_num($S['coexpress_pairs']);?></span></li>
                        <li>Cell Types <span><a href='./cell_marker.php'><?php echo cnido_num($S['cell_types']);?></a></span></li>
                        <li>Cell Markers <span><a href='./cell_marker.php'><?php echo cnido_num($S['cell_markers']);?></a></span></li>
                        <li>Quantified Proteins <span><a href='./proteomic_analysis.php'><?php echo cnido_num($S['proteins_quantified']);?></a></span></li>
                        <li>Epigenetic Modification Types <span><?php echo cnido_num($S['epigenome_types']);?></span></li>
                        <li>Epigenetic Modification Peaks <span><?php echo cnido_num($S['epigenome_peaks']);?></span></li>
                        <li>Metagenome-Assembled Genomes <span><a href='./MAGs.php'><?php echo cnido_num($S['mags']);?></a></span></li>
                        <li>Types of Gene Annotation <span><?php echo cnido_num($S['annotation_types']);?></span></li>
                        <li>NR Database <span><?php echo cnido_num($S['nr']);?></span></li>
                        <li>UniProt Database <span><?php echo cnido_num($S['uniprot']);?></span></li>
                        <li>KEGG Pathways <span><?php echo cnido_num($S['kegg']);?></span></li>
                        <li>Transcription Factors <span><?php echo cnido_num($S['tf']);?></span></li>
                        <li>Ubiquitin Family <span><?php echo cnido_num($S['ubiquitin']);?></span></li>
                        <li>Gene Ontology <span><?php echo cnido_num($S['go']);?></span></li>
                        <li>InterPro Annotation <span><?php echo cnido_num($S['interpro']);?></span></li>
                        <li>Pfam Domain <span><?php echo cnido_num($S['pfam']);?></span></li>
                        <li>PANTHER <span><?php echo cnido_num($S['panther']);?></span></li>
                        <?php /* 这个数是 genefamily 的**行数**（基因→家族的归属记录），
                                   不是家族数（家族数见 data_statistics.php，113,207）。
                                   includes/stats.php 的规范标签叫 'Gene family assignments'。 */ ?>
                        <li>Gene Family Assignments <span><a href='./genefamily.php'><?php echo cnido_num($S['genefamily']);?></a></span></li>
                    </ul>
                    <?php endif; ?>
                </div>
                
                <?php /* 相关链接 */ ?>
                <legend style="margin-top: 30px;"><img src="./images/header.jpg" height="45px" style="margin-bottom:-10px">&nbsp;<b>External Resources</b></legend>
                <div style="padding: 10px; background-color: #f9f9f9; border-radius: 0 0 4px 4px;">
                    <ul class="df_list">
                        <li><a href="https://academic.oup.com/nar/article/31/1/159/2401509" target='_blank' rel="noopener noreferrer">The Cnidarian Evolutionary Genomics Database</a></li>
                        <li><a href="https://www.aquariumofpacific.org/onlinelearningcenter/category/cnidarians" target='_blank' rel="noopener noreferrer">Cnidarians - Animal Database</a></li>
                        <li><a href="https://www.marinespecies.org/index.php" target='_blank' rel="noopener noreferrer">World Register of Marine Species (WoRMS)</a></li>
						<li><a href="https://obis.org/" target='_blank' rel="noopener noreferrer">Ocean Biodiversity Information System (OBIS)</a></li>
                        <li><a href="https://www.gbif.org/" target='_blank' rel="noopener noreferrer">Global Biodiversity Information Facility (GBIF)</a></li>
                        <li><a href="https://paleobiodb.org/#/" target='_blank' rel="noopener noreferrer">The Paleobiology Database (PBDB)</a></li>
                    </ul>
                </div>
                
                <?php /* 最新动态：每个版本一行，「日期 · 版本号 — 一句话概括」。内容全部来自
                     includes/release.php 的变更记录（全站唯一的事实来源）。首页只给总结，
                     逐条明细在 release.php 页面和 api.php 里，都读同一份 entries。
                     这块原来是自己手写的一份日期列表，没人跟着版本走，停在 2026-05-28 ——
                     r1.1 和 r1.2 两个版本在首页上完全没出现过；改成读同一份来源后不会再掉队。 */ ?>
                <legend style="margin-top: 30px;"><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Recent Updates</b></legend>
                <div style="padding: 10px; background-color: #f9f9f9; border-radius: 0 0 4px 4px;">
                    <ul class="df_list ru-list">
                        <?php foreach (cnido_changelog() as $__rel): ?>
                        <li class="ru-release"><strong><?php echo htmlspecialchars($__rel['date'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            &middot; <?php echo htmlspecialchars($__rel['version'], ENT_QUOTES, 'UTF-8'); ?>
                            &mdash; <span class="ru-sum"><?php echo htmlspecialchars($__rel['summary'], ENT_QUOTES, 'UTF-8'); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="ru-more">Full changelog, version numbers and the update schedule: <a href="./release.php">Release &amp; Changelog</a>.</p>
                </div>
            </div>
            
            <div class="cleaner"></div>
        </div>
    </div>
</div>

<?php /* 系统发育树大图灯箱 */ ?>
<div class="tree-lightbox" id="treeLightbox" role="dialog" aria-modal="true" aria-label="Cnidaria phylogenetic tree, full size">
    <span class="tree-lightbox-close" id="treeLightboxClose" title="Close (Esc)">&times;</span>
    <div class="tree-lightbox-stage" id="treeLightboxStage">
        <img src="images/all-tree.png" alt="Cnidaria Phylogenetic Tree" id="treeLightboxImg" />
    </div>
    <div class="tree-lightbox-bar">
        <button type="button" id="treeZoomToggle">Zoom to 100%</button>
        <a href="images/all-tree.png" target="_blank" rel="noopener">Open original image</a>
        <span>Esc or click outside to close</span>
    </div>
</div>

<script type="text/javascript">
(function () {
    "use strict";
    var link  = document.getElementById("cnidariaTreeLink");
    var box   = document.getElementById("treeLightbox");
    var stage = document.getElementById("treeLightboxStage");
    var lbImg = document.getElementById("treeLightboxImg");
    var close = document.getElementById("treeLightboxClose");
    var zoom  = document.getElementById("treeZoomToggle");
    if (!link || !box || !stage || !lbImg || !close || !zoom) return;

    function isZoomed() { return box.className.indexOf("zoomed") !== -1; }

    <?php /* 用 addEventListener 而不是 on* 属性：页面里其他脚本可能已经在用 document.onkeydown，
       直接赋值会把它们的处理函数覆盖掉。 */ ?>
    function on(el, type, fn) {
        if (el.addEventListener) el.addEventListener(type, fn, false);
        else if (el.attachEvent) el.attachEvent("on" + type, fn);
    }
    function setLabel(el, text) {
        if ("textContent" in el) el.textContent = text; else el.innerText = text;
    }
    function stop(e) {
        if (!e) return;
        if (e.preventDefault) e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
        e.returnValue = false;
    }

    <?php /* 100% 原始像素时图片远大于视口，交给 stage 的滚动条平移；回到适应窗口时复位。 */ ?>
    function setZoom(val) {
        box.className = val ? "tree-lightbox show zoomed" : "tree-lightbox show";
        setLabel(zoom, val ? "Fit to screen" : "Zoom to 100%");
        if (!val) { stage.scrollTop = 0; stage.scrollLeft = 0; }
    }

    function open() {
        box.className = "tree-lightbox show";
        setLabel(zoom, "Zoom to 100%");
        document.body.style.overflow = "hidden";
        if (close.focus) close.focus();
    }

    function hide() {
        box.className = "tree-lightbox";
        document.body.style.overflow = "";
        if (link.focus) link.focus();
    }

    on(link, "click", function (e) { stop(e); open(); });
    on(close, "click", function (e) { stop(e); hide(); });
    on(zoom, "click", function (e) { stop(e); setZoom(!isZoomed()); });
    on(lbImg, "click", function (e) { stop(e); setZoom(!isZoomed()); });

    <?php /* 点击图片以外的空白处关闭；放大状态下留白区用于滚动平移，不触发关闭。 */ ?>
    on(box, "click", function (e) {
        var t = e.target || e.srcElement;
        if (t === box || (t === stage && !isZoomed())) hide();
    });

    on(document, "keydown", function (e) {
        if (box.className.indexOf("show") === -1) return;
        var key = e.key || e.keyCode;
        if (key === "Escape" || key === 27) { hide(); stop(e); }
        else if ((key === " " || key === "Spacebar" || key === 32) && (e.target || e.srcElement) === lbImg) {
            setZoom(!isZoomed());
            stop(e);
        }
    });
})();
</script>

<?php
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
