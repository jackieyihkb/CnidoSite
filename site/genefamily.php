<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Gene Family - CnidoSite</title>
<meta name="description" content="Browse transcription-factor and ubiquitin gene families across the cnidarian genomes held in CnidoSite" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery-1.10.2.min.js"></script>
<script src="js/highcharts.js"></script>
<script src="js/highcharts-more.js"></script>
<script src="js/exporting.js"></script>

<script src="js/sign.js" language="javascript"></script>
<script src="js/tooltip.js" type="text/javascript"></script>
<script src="js/highcharts-detail.js"></script>
<script src="js/modernizr.main.js"></script>
<style>
<?php /* BUSCO结果页面专用样式 - 与其他页面保持一致 */ ?>
.busco-result-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.busco-result-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.busco-result-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.species-badge {
    background:linear-gradient(135deg, #b45309 0%, #92400e 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

<?php /* 分页样式 - 与其他页面保持一致 */ ?>
.pagination-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 20px;
    margin-top: 30px;
    border: 1px solid #e2e8f0;
}

.pagination-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.total-records {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.per-page-selector {
    display: flex;
    align-items: center;
    gap: 10px;
}

.per-page-selector select {
    padding: 8px 15px;
    border-radius: 6px;
    border: 2px solid #e2e8f0;
    background: white;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.per-page-selector select:hover {
    border-color:#b45309;
}

.pagination-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin: 25px 0;
    flex-wrap: wrap;
}

.page-btn {
    padding: 10px 16px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #475569;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s ease;
    min-width: 44px;
    text-align: center;
}

.page-btn:hover {
    background: #f0f9ff;
    border-color:#1d4ed8;
    color:#1d4ed8;
}

.page-btn.active {
    background:#1d4ed8;
    border-color:#1d4ed8;
    color: white;
    font-weight: 600;
}
.page-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.go-to-page {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
    justify-content: center;
}

.go-to-page input {
    width: 70px;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    text-align: center;
    font-size: 15px;
}

.go-to-page button {
    padding: 8px 16px;
    background:#1d4ed8;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.go-to-page button:hover {
    background: #1d4ed8;
}


<?php /* 表格美化 */ ?>

.table-scroll-container {
    max-height: 700px;
    overflow-y: auto;
    overflow-x: auto;
}

<?php /* 表头冷冻：滑轮滚动时列名那一行钉在盒子顶部。
   能生效的前提是**这个盒子自己就是滚动容器**（上面 max-height + overflow-y:auto）——
   表头 sticky 的参照物就是它。别把 max-height 去掉：auto 高度的盒子里 sticky 没有
   可粘的滚动盒，会静默失效（trans_assembly.php 那段注释记的是同一个坑）。
   两条写法上的讲究：
   1) `background-color` 必须显式再写一遍。共用样式 table.gridtable th 给的是 #f1f5f9，
      看着一样，但滚动时表头要盖住下面的行，**底色必须不透明且由自己保证** ——
      别指望继承来的值（哪天共用样式改成半透明或渐变就会透出行来）。
   2) 底线用 inset box-shadow 画，不能用 border-bottom：共用样式是
      `border-collapse: collapse`，**折叠模式下的边框属于表格、不跟着 sticky 走**，
      滚动后表头下面那条线会留在原地。inset 阴影画在 th 自己的盒内，跟着走。
      静态时两者位置重合，看起来还是一条线。
   z-index 压在数据格之上（td 没有定位，不会形成层叠上下文）。

   副作用一条，写下来免得下次有人对不上账：表头行从 tbody 挪进 thead 之后，
   共用样式的斑马纹 `tbody tr:nth-child(even) td` 就**整体错开一行** ——
   原来紧挨表头的第 1 个数据行是 even（#fcfdff），现在是 odd（白）。
   仍然是交替的，只是相位差一格；实测表头以下像素只差这一处颜色。
   要还原旧相位就得写 odd/even 两条反向覆盖，不值当。 */ ?>
table.gridtable thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background-color: #f1f5f9;
    box-shadow: inset 0 -1px 0 #e2e8f0;
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

.jbrowse-link {
    color:#b45309;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.jbrowse-link:hover {
    color: #92400e;
    text-decoration: underline;
}

<?php /* 状态标签 */ ?>
.status-complete {
    background:#047857;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-fragmented {
    background:#b45309;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-duplicated {
    background:#6d28d9;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-missing {
    background: #b91c1c;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

@media (max-width: 768px) {
    .busco-result-header {
        padding: 20px;
    }
    
    .busco-result-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .pagination-info {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .pagination-nav {
        gap: 5px;
    }
    
    .page-btn {
        padding: 8px 12px;
        min-width: 36px;
    }
}

/* ---------- gene family: search form + consensus annotation ---------- */
.gf-form{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin:18px 0}
.gf-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.gf-row input[type=text]{flex:1;min-width:260px;max-width:620px;padding:10px 14px;border:2px solid #e2e8f0;
    border-radius:8px;font-size:15px;background:#fff}
.gf-row input[type=text]:focus{outline:none;border-color:#1d4ed8}
.gf-row button{padding:10px 26px;border:none;border-radius:8px;background:#1d4ed8;color:#fff;font-size:15px;
    font-weight:600;cursor:pointer;transition:background .3s ease}
.gf-row button:hover{background:#1d4ed8}
<?php /* 必须写成 a.gf-clear：单类名 (0,1,0) 会被全站的 a:link (0,1,1) 压掉，
   实测 computed color 是 rgb(29,78,216) 的默认链接蓝，不是这里的灰。 */ ?>
a.gf-clear{color:#64748b;font-size:15px}
.gf-opts{margin-top:12px;font-size:15px;color:#475569}
<?php /* min-width:0 —— label 是 flex 子项，默认 min-width:auto，缩不到比 select 的
   min-content 更窄；而 <select> 的固有宽度由**最长的那个 option** 决定
   （"families with a consensus annotation" 撑到 335px），于是 291px 的栏里
   顶出 377。放开下限、再给 select 封顶，宽屏下两者都够不着、不生效。 */ ?>
.gf-opts label{display:inline-flex;align-items:center;gap:6px;min-width:0;max-width:100%}
.gf-opts select{padding:7px 10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;font-size:15px;cursor:pointer;min-width:0;max-width:100%}
<?php /* 2026-09-30：搜索框最终落在「📊 67,795 gene families」那一行，贴最右边。
   位置靠 .pagination-info 自带的 justify-content:space-between：药丸是第一个孩子、
   搜索框是第二个 → 宽屏自动一左一右顶到两头。它是盒子里唯一的 flex 子项，
   所以给固定宽度（不再拉满），间距由父级的 gap:15px 出。 */ ?>
.gf-search{display:flex;align-items:center;gap:10px;flex:0 0 auto;min-width:0;flex-wrap:nowrap}
<?php /* 搜索框已经不在 .gf-row 里了，输入框和按钮的外观得自己带一份
   （原来靠 .gf-row input[type=text] / .gf-row button 那两条继承）。 */ ?>
.gf-search input[type=text]{flex:0 0 auto;width:340px;max-width:100%;min-width:0;padding:10px 14px;
    border:2px solid #e2e8f0;border-radius:8px;font-size:15px;background:#fff}
.gf-search input[type=text]:focus{outline:none;border-color:#1d4ed8}
.gf-search button{padding:10px 26px;border:none;border-radius:8px;background:#1d4ed8;color:#fff;
    font-size:15px;font-weight:600;cursor:pointer;transition:background .3s ease;white-space:nowrap}
.gf-search button:hover{background:#1d4ed8}
.gf-filters{display:flex;align-items:center;gap:12px;flex-wrap:wrap;min-width:0}
<?php /* 窄屏：pagination-info 在 768px 那条规则里已经改成竖排 flex-start，
   搜索框落到药丸下面一行 —— 把它撑满整宽，别留一个 340px 的小框。 */ ?>
@media (max-width:768px){
    .gf-search{flex:1 1 auto;width:100%}
    .gf-search input[type=text]{flex:1 1 auto;width:auto}
}

.gfa-line{line-height:1.75;margin:2px 0}
.gfa-badge{display:inline-block;min-width:46px;padding:2px 8px;border-radius:10px;font-size:12px;
    font-weight:700;color:#fff;text-align:center}
.gfa-all{background:#047857}
.gfa-ge80{background:#0f766e}
.gfa-ge50{background:#b45309}
.gfa-none{background:#94a3b8}
/* 低于 50% 的弱证据：**空心**灰徽章，刻意比上面三个实心档位轻 —— 一眼能看出它不是共识。
   别把这一档并进 .gfa-none：那是「一条都没有」的占位，语义正相反。 */
.gfa-low{background:#f8fafc;color:#64748b;border:1px solid #cbd5e1}
.gfa-low-tag{color:#94a3b8;font-size:14px;white-space:nowrap}
.gfa-src{display:inline-block;padding:1px 7px;border-radius:5px;font-size:12px;font-weight:700;
    color:#fff;background:#64748b;white-space:nowrap}
.gfa-src-pfam{background:#7c3aed}
.gfa-src-panther{background:#0369a1}
.gfa-src-go{background:#15803d}
.gfa-src-kegg{background:#db2777}
.gfa-term{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:15px;color:#334155}
.gfa-desc{color:#1e293b}
.gfa-cat{color:#64748b;font-size:15px}
.gfa-sup{color:#64748b;font-size:15px;white-space:nowrap}
.gfa-more{font-size:15px;color:#64748b;margin-top:2px}
.gfa-hit{background:#f0f9ff;border-left:3px solid #3b82f6;padding-left:8px;border-radius:3px}

/* ---------- 注释来源小结 ---------- */
.gfa-sources{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px 12px 18px;margin:18px 0}
.gfa-sources-hd{font-size:16px;color:#1f2937;line-height:1.7;margin-bottom:12px}
<?php /* 用 grid 而不是 gridtable：只有四行、每行一个来源 + 三个数字 + 一句说明，
   做成表格在手机上 max-content 会到 925px，要横滑才读得完。这里 900px 以下收成一列。 */ ?>
<?php /* 中间那列写 max-content 而不是固定 300px：「17,011 families · 7,584 terms · 2,023 at
   100%」在 300px 里放不下，最后一个数会掉到下一行，四行的行高就参差不齐了。 */ ?>
.gfa-src-grid{display:grid;grid-template-columns:96px max-content 1fr;gap:8px 14px;align-items:baseline}
.gfa-src-cell{display:contents}
<?php /* display:contents 让三个 span 直接成为 grid 项，于是每行正好一个来源。
   徽章是 inline-block，进了 grid 会被拉满整列，必须 justify-self:start 顶回去。 */ ?>
.gfa-src-grid .gfa-src{justify-self:start}
.gfa-src-num{font-size:16px;color:#334155}
.gfa-src-why{font-size:16px;color:#1e293b}
.gfa-sources-ft{font-size:16px;color:#64748b;margin-top:10px}
@media (max-width:900px){
    /* 收成一列：来源徽章、数字、说明各占一行，靠 .gfa-src 上的 margin-top 分段。 */
    .gfa-src-grid{grid-template-columns:1fr;gap:2px}
    .gfa-src-grid .gfa-src{margin-top:8px}
    .gfa-src-why{margin-bottom:4px}
}
.gfa-annot-tag{color:#0e7490;font-size:14px;white-space:nowrap}

<?php /* 原先这里有一份 .gtree-btn 药丸（和 .species-badge 并排的「View gene tree」）：
   它是给家族结果页用的，样式却被复制到了这个搜索页里，本页从来没有对应的标记。
   2026-09-23 结果页的页头改成 .fam-hero 之后，那颗药丸在全站都不存在了，所以
   这一份删掉。结果页自己的样式在 genefamily_result.php 的 <style> 里。 */ ?>

</style>
</head>
<body>
<script type="text/javascript">
    $(function(){
        $('#export').click(function(){
            var excelContent = $('#tablelist').html(); //获取表格内容
            $('input[name=excelContent]').val(excelContent);//赋值给表单
            $('#excelfromtable').submit();//表单提交，提交到php
        })
    })
</script>
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
/* ---------------------------------------------------------------------------
 * genefamily.php  --  OrthoFinder gene-family browser with family-level
 *                     consensus functional annotation.
 *
 * Data source : OrthoFinder Results_Sep14 (148 cnidarian proteomes, plus one
 *               non-cnidarian outgroup bucket recorded as abbr='OUT' — 5
 *               proteomes: Bolinopsis microptera, Corticium candelabrum,
 *               Halichondria panicea, Oscarella lobularis, Sycon ciliatum).
 *               og_family_member therefore holds 149 distinct abbr, and
 *               'OUT' is the only one with no row in the abbr table.
 *               Tables og_family / og_family_term / og_family_member.
 *
 * Annotation rule: a term is reported for a family when it is carried by a
 *               large fraction of the family's member genes.  Tier "100%" means
 *               every member gene carries the term (the strict rule); 80% / 50%
 *               are the relaxed tiers.  Annotation evidence is taken from the
 *               per-species Pfam / PANTHER / GO / KEGG tables already loaded
 *               in this database.
 * ------------------------------------------------------------------------- */
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { die('Database connection failed.'); }
mysqli_set_charset($conn, 'utf8mb4');

function gfa_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* escape the LIKE metacharacters so a user typing "%" does not match everything */
function gfa_like($s) { return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $s); }

function gfa_src($s) {
    static $m = array('pfam' => 'Pfam', 'panther' => 'PANTHER', 'go' => 'GO', 'kegg' => 'KEGG');
    return isset($m[$s]) ? $m[$s] : strtoupper($s);
}

function gfa_badge($tier, $pct) {
    if ($tier === 'all')  return '<span class="gfa-badge gfa-all" title="Every member gene of this family carries this term">100%</span>';
    if ($tier === 'ge80') return '<span class="gfa-badge gfa-ge80" title="Carried by at least 80% of the member genes">' . round($pct) . '%</span>';
    if ($tier === 'ge50') return '<span class="gfa-badge gfa-ge50" title="Carried by at least 50% of the member genes">' . round($pct) . '%</span>';
    /* 'low' = 低于 50% 的弱证据。数字照印，但徽章是空心的：比上面三档轻，
       又比 'n/a' 有信息 —— 这一档存在的全部意义就是「要显示、但要能区分开」。 */
    if ($tier === 'low')  return '<span class="gfa-badge gfa-low" title="Carried by fewer than half of the member genes — evidence, but not a consensus of this family">' . round($pct) . '%</span>';
    return '<span class="gfa-badge gfa-none" title="No term reaches 50% support">n/a</span>';
}

/* render one term line:  [badge] SOURCE  accession  name -- description  (support/total) */
function gfa_term_line($t) {
    $name = trim((string)$t['term_name']) !== '' ? $t['term_name'] : '';
    $desc = trim((string)$t['term_desc']);
    /* 破折号必须写字面量 U+2014，不能写 &mdash; —— 整串随后要过 gfa_h()
       （htmlspecialchars），实体会被再转义一次，页面上直接印出 "&mdash;" 六个字符
       （Pfam 的 7tm_1 / 7 transmembrane receptor 就是这么坏的）。 */
    if ($name !== '' && $desc !== '' && stripos($desc, $name) === false) { $desc = $name . ' — ' . $desc; }
    elseif ($name !== '' && $desc === '') { $desc = $name; }
    $cat = trim((string)$t['category']) !== '' ? ' <span class="gfa-cat">[' . gfa_h($t['category']) . ']</span>' : '';
    return gfa_badge($t['tier'], $t['pct'])
         . ' <span class="gfa-src gfa-src-' . gfa_h($t['source']) . '">' . gfa_h(gfa_src($t['source'])) . '</span> '
         . '<span class="gfa-term">' . gfa_h($t['term']) . '</span>'
         . ($desc !== '' ? ' <span class="gfa-desc">' . gfa_h($desc) . $cat . '</span>' : $cat)
         . ' <span class="gfa-sup">' . (int)$t['support'] . '/' . (int)$t['n_genes'] . '</span>';
}

/* 同一个条目的「在有预测的成员里一致」写法：徽章仍是按全体成员算的档位（58% 就是
   58%），后面补一句它在有预测的成员里的表现。两个口径同屏并列，读者才不会把
   「100%」误读成「全体成员一致」—— 那正是 gfa_badge() 的 100% 的含义。 */
function gfa_annot_line($t) {
    return gfa_term_line($t)
         . ' <span class="gfa-annot-tag" title="Carried by every member gene that has a prediction of this type'
         . ' (at least two members have one)">&#10003; 100% of the '
         . number_format((int)$t['n_annot']) . ' annotated</span>';
}

/* 弱证据那一行（pct < 50）。徽章走 gfa_badge 的 'low' 档（空心），后面再明写一句
   「below the 50% cut-off」—— 与上面 ≥50% 的实心徽章分得清清楚楚；但 accession、
   名字、support/n_genes 一个不少，读者照样能对这个家族形成判断。 */
function gfa_low_line($t) {
    return gfa_term_line($t)
         . ' <span class="gfa-low-tag" title="Carried by fewer than half of this family\'s member genes,'
         . ' so it is not a consensus — shown because it is the strongest evidence the family has">'
         . 'below the 50% cut-off</span>';
}

/* og_family_term_low 是 build_og_family_term_low.php 离线建的（低于 50% 的条目）。
   它不在时页面必须退回老文案，不能假装「查过了、没有」—— 所以各处都先问这一句。
   用 `SELECT 1 ... LIMIT 1` 探活，比查 information_schema 便宜，探一次就记住。 */
function gfa_low_ready($conn) {
    static $ok = null;
    if ($ok === null) { $ok = (bool) @mysqli_query($conn, 'SELECT 1 FROM og_family_term_low LIMIT 1'); }
    return $ok;
}

/* ------------------------------------------------- 注释来源小结 ---------- */
/* 这一页的注释是由什么做的：四个来源各覆盖多少家族、多少个条目、多少条在全体成员里
   一致。og_family_term 有 11.4 万行，GROUP BY source 一次约 50–70 ms，而这两张表只在
   整库重导时才变 —— 按本站既有做法缓存到 tmp/（CLI 与 web 唯一共用的可写目录，
   见 includes/cache.php 里那段「为什么不放 includes/ 也不放 /tmp」）。
   重导过 og_family* 之后：删 tmp/gfa_sources.json，或把下面这个版本号加一。
   2026-09-30 V=2：payload 里的 fam 多了 weak_only / nothing 两个数（弱证据那一档），
   旧缓存也满足 isset() 那几条校验，不升版本号就会一直读到没有这两个键的旧文件、
   页面上那两个数恒为 0 —— 静默错，看不出来。 */
define('GFA_SRC_SUMMARY_V', 2);
function gfa_source_summary($conn) {
    require_once __DIR__ . '/includes/cache.php';
    $path = function_exists('cnido_cache_path') ? cnido_cache_path('gfa_sources.json') : '';
    $ttl  = 86400;
    if ($path !== '' && is_file($path)) {
        $j = json_decode((string) @file_get_contents($path), true);
        if (is_array($j) && isset($j['v']) && $j['v'] === GFA_SRC_SUMMARY_V
            && isset($j['built']) && (time() - (int)$j['built']) < $ttl
            && isset($j['src']) && is_array($j['src']) && isset($j['fam']) && is_array($j['fam'])) {
            return array($j['src'], $j['fam']);
        }
    }
    $src = array();
    $q = mysqli_query($conn, "SELECT source, COUNT(DISTINCT og) n_fam, COUNT(DISTINCT term) n_terms,
                                     SUM(tier = 'all') n_all
                              FROM og_family_term GROUP BY source");
    while ($q && ($r = mysqli_fetch_assoc($q))) { $src[$r['source']] = $r; }
    $fam = array('total' => 0, 'annot' => 0, 't100' => 0);
    $q = mysqli_query($conn, "SELECT COUNT(*) total, SUM(best_tier <> '') annot, SUM(best_tier = 'all') t100
                              FROM og_family");
    if ($q && ($r = mysqli_fetch_assoc($q))) { $fam = $r; }
    /* 弱证据那一档的家族数：无共识、但有低于 50% 的条目可显示（列表页给它们印空心徽章）。
       第 2 个数是「连一条弱证据都没有」—— 67,795 = annot + weak_only + nothing，
       三个数正好把全部家族分完，页面上那句话才说得圆。
       表不在时 gfa_low_ready() 为假，两个数都算 0，页面就不提这一档。 */
    $fam['weak_only'] = 0; $fam['nothing'] = 0;
    if (gfa_low_ready($conn)) {
        $q = mysqli_query($conn, "SELECT SUM(COALESCE(best_tier,'') = '' AND w.og IS NOT NULL) weak_only,
                                         SUM(COALESCE(best_tier,'') = '' AND w.og IS NULL) nothing
                                  FROM og_family f
                                  LEFT JOIN (SELECT DISTINCT og FROM og_family_term_low) w ON w.og = f.og");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            $fam['weak_only'] = (int)$r['weak_only'];
            $fam['nothing']   = (int)$r['nothing'];
        }
    }
    if ($path !== '') {
        @file_put_contents($path, json_encode(array(
            'v' => GFA_SRC_SUMMARY_V, 'built' => time(), 'src' => $src, 'fam' => $fam)));
    }
    return array($src, $fam);
}

/* ------------------------------------------------------------ request ---- */
$q        = isset($_GET['q']) ? trim($_GET['q']) : '';
/* scope 默认 'all'：67,795 个 orthogroup 全部列出，没有一致注释的照样出现在表里、
   只是那一格写「no term reaches 50%」。原来默认 'annotated'，进页面看到的是 23,302
   个有共识的家族，剩下的 44,493 个要自己去下拉框里找 —— 这正是「最好能显示所有
   gene family」要改的那件事。 */
$scope    = (isset($_GET['scope']) && $_GET['scope'] === 'annotated') ? 'annotated' : 'all';
$sort     = isset($_GET['sort']) ? $_GET['sort'] : 'og';
/* cons = 一致性档位。对「家族内部到底有多一致」再做一层收窄，与 scope 正交：
   scope 管「要不要连没有共识的家族一起列」，cons 管「有共识的里面要严到什么程度」。
     any    不加限制（默认，= 这次改动之前的行为）
     all    每个成员基因都带这个条目（严格一致）
     ge80   ≥80% 的成员基因带
     ge50   ≥50%（＝有共识的底线，与 scope=annotated 同义）
     annot  在**有该类型预测的那些成员**里一致（pct_annot=100%，且至少 2 个成员有
            预测，免得单个基因的孤证被当成「全体一致」）。
   annot 是把「按全体成员算只有 50–99%、但在有证据的成员里一个不差」的那些条目捞出来
   —— 结果页早就在印 pct_annot，列表页一直没有入口。
   注意 og_family_term **只存 ≥50% 的条目**（44,493 个无共识家族在那张表里一行都没有），
   所以 annot 只能收窄到「已有共识的 23,302 个」之内，不能把无共识的家族变出来。 */
$cons     = isset($_GET['cons']) ? (string)$_GET['cons'] : 'any';
$consAll  = array('any', 'all', 'ge80', 'ge50', 'annot');
/* below50 = 「有弱证据、但一条都没到 50%」。整档都靠离线表 og_family_term_low 撑着，
   表不在就不放进白名单 —— 否则换个表名这个下拉框会把页面打成空结果。 */
if (gfa_low_ready($conn)) { $consAll[] = 'below50'; }
if (!in_array($cons, $consAll, true)) { $cons = 'any'; }
/* per_page 走白名单，和 go_result.php / kegg_result.php / interpro_result.php 同一套写法。
   原来只做 `<= 0` 的下界，`?per_page=999999` 就真去渲染 999999 行 —— 下拉框只提供
   这几档，手敲的越界值应当落回默认，而不是照单执行。
   500 / 1000 是 2026-09-30 加的：默认视图改成全部 67,795 个之后，想看全貌不必翻 3,390 页。 */
$per_page_options = [10, 20, 50, 100, 500, 1000];
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
if (!in_array($per_page, $per_page_options)) { $per_page = 20; }
$page     = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page <= 0) $page = 1;

$cond = array();
if ($q !== '') {
    $cond[] = "search_text LIKE '%" . mysqli_real_escape_string($conn, gfa_like($q)) . "%'";
}
if ($scope === 'annotated') { $cond[] = "best_tier <> ''"; }
/* 子查询里的外层表名必须写全（og_family.og）：内层也用 og 这个名字，
   不限定就是 1052 ambiguous。 */
switch ($cons) {
    case 'all':   $cond[] = "best_tier = 'all'"; break;
    case 'ge80':  $cond[] = "best_tier IN ('all','ge80')"; break;
    case 'ge50':  $cond[] = "best_tier <> ''"; break;
    case 'annot': $cond[] = "EXISTS (SELECT 1 FROM og_family_term t WHERE t.og = og_family.og"
                          . " AND t.pct_annot >= 100 AND t.n_annot >= 2)"; break;
    /* below50 与上面几档相反：专挑**一条共识都没有、但有弱证据**的家族。
       外层表名要写全 og_family.og（同 annot 那条的理由：内外都叫 og）。 */
    case 'below50': $cond[] = "best_tier = '' AND EXISTS (SELECT 1 FROM og_family_term_low l"
                            . " WHERE l.og = og_family.og)"; break;
}
$where = $cond ? implode(' AND ', $cond) : '1=1';

/* ========== 表头排序（服务端） ==========
 * 这张表是分页的（LIMIT $offset, $per_page），浏览器里排一页 20 行是错的。
 *
 * 参数前缀用 'c'：这个页面**已经**有一个自己的 sort 参数（右上角那个下拉，
 * 值域 og/consistency/size），跟表头的列不是一回事，共用 $_GET['sort'] 会互踩。
 *
 * 默认档用 null（不生成 ORDER BY）：页面原来的次序来自上面那个下拉的 switch，
 * 表头这一套只在用户点了列之后接管 —— 没点就完全是原来的页面，一个字节都不变。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array(
    'default' => null,
    'og'      => cnido_sort_txt('og'),
    'genes'   => cnido_sort_num('n_genes'),
    'species' => cnido_sort_num('n_species'),
    /* best_term 是「全家族一致同意的那个功能」，没有一致项时是空串 ——
       cnido_sort_txt 让这些行沉底，否则空串会挤满第一页。 */
    'term'    => cnido_sort_txt('best_term'),
);
list($__cSort, $__cDir, $__cOrder) = cnido_sort_state($__sortKeys, 'default', 'og', 'c');
$__cSortQs = cnido_sort_qs($__cSort, $__cDir, $__sortKeys, 'default', 'c');

switch ($sort) {
    case 'consistency': $order = "FIELD(best_tier,'all','ge80','ge50',''), best_pct DESC, n_genes DESC"; break;
    case 'size':        $order = "n_genes DESC"; break;
    default:            $order = "og"; $sort = 'og';
}

$total_records = 0;
$res = mysqli_query($conn, "SELECT COUNT(*) FROM og_family WHERE $where");
if ($res) { $total_records = (int)mysqli_fetch_row($res)[0]; }
$total_pages = $per_page > 0 ? (int)ceil($total_records / $per_page) : 1;
if ($total_pages > 0 && $page > $total_pages) { $page = $total_pages; }
$offset = ($page - 1) * $per_page;

$rows = array();
$qr = mysqli_query($conn, "SELECT og, n_genes, n_seqs, n_species, best_source, best_term, best_name, best_desc,
                                  best_cat, best_support, best_pct, best_tier, n_terms, n_terms_all
                           FROM og_family WHERE $where ORDER BY " . ($__cOrder !== '' ? $__cOrder : $order) . " LIMIT $offset, $per_page");
if (!$qr) { echo "<p class='gd-notice gd-warn'>Gene families could not be queried.</p>"; }
while ($qr && ($r = mysqli_fetch_assoc($qr))) { $rows[] = $r; }

/* when searching, also pull the terms that actually matched so the hit is visible */
$matched = array();
if ($q !== '' && $rows) {
    $ogs = array();
    foreach ($rows as $r) { $ogs[] = "'" . mysqli_real_escape_string($conn, $r['og']) . "'"; }
    $like = "'%" . mysqli_real_escape_string($conn, gfa_like($q)) . "%'";
    $q2 = mysqli_query($conn, "SELECT og, source, term, term_name, term_desc, category, support, n_genes, pct, tier
                               FROM og_family_term
                               WHERE og IN (" . implode(',', $ogs) . ")
                                 AND (term LIKE $like OR term_name LIKE $like OR term_desc LIKE $like)
                               ORDER BY FIELD(tier,'all','ge80','ge50'), pct DESC, support DESC LIMIT 600");
    while ($q2 && ($r = mysqli_fetch_assoc($q2))) { $matched[$r['og']][] = $r; }
}

/* cons=annot 时，把本页每个家族「在有预测的成员里一致」的那个条目标出来。
   与上面的 $matched 同一套做法：只查本页这几十个 og（走 (og,source,term) 主键前缀），
   每个家族取 pct 最高的那一条 —— 即覆盖面最广、最值得看的那个一致项。
   用 n_annot>=2 而不是 support>=2：这里的分母是 n_annot，两者相等，但写 n_annot
   才和上面 WHERE 里的判据逐字一致。 */
$annot = array();
if ($cons === 'annot' && $rows) {
    $ogs = array();
    foreach ($rows as $r) { $ogs[] = "'" . mysqli_real_escape_string($conn, $r['og']) . "'"; }
    $q3 = mysqli_query($conn, "SELECT og, source, term, term_name, term_desc, category,
                                      support, n_genes, n_annot, pct, pct_annot, tier
                               FROM og_family_term
                               WHERE og IN (" . implode(',', $ogs) . ")
                                 AND pct_annot >= 100 AND n_annot >= 2
                               ORDER BY pct DESC, support DESC LIMIT 600");
    while ($q3 && ($r = mysqli_fetch_assoc($q3))) {
        if (!isset($annot[$r['og']])) { $annot[$r['og']] = $r; }
    }
}

/* 低于 50% 的弱条目：本页每个家族取**最接近共识**的那一条（pct 最高，并列时 support 大的
   优先）印在那一格，再单独查一个总数写「N weaker terms in total」。用户要的是「没有共识也
   不能只说 No」——所以哪怕只有一条 30% 的 Pfam 也要显示出来，同时用空心徽章和那句
   below the 50% cut-off 把它和共识区分开。
   一个大家族的弱条目可以上万条，全拉回来只为印一行是浪费 —— 每个家族只回一行。
   两次查询都走主键前缀 (og,source,term)，跟上面 $matched / $annot 同一套路。 */
$low = array(); $lowN = array();
if ($rows && gfa_low_ready($conn)) {
    $ogs = array();
    foreach ($rows as $r) { $ogs[] = "'" . mysqli_real_escape_string($conn, $r['og']) . "'"; }
    $in = implode(',', $ogs);
    /* 取「每个家族 pct 最高的一条」。**别用 ROW_NUMBER() 窗口函数**：它要把命中的
       每一行都拉出来做一次 filesort —— 默认视图按 og 排序、第一页正好是最大的那些家族
       （单家族弱条目能到 4,746 条），20 个家族实测 0.19 s，整页 0.036 → 0.225 s。
       改成 GROUP BY MAX(pct) 再去 JOIN 回原表：EXPLAIN 是 range + Using index
       （idx_og_pct 是覆盖索引，(og,pct) 前缀正好够），同样 20 个家族 0.023 s，快 8 倍。
       并列的 pct（同家族里不同 term 支撑数相同）会回多行，PHP 里取第一条 —— ORDER BY 按
       pct DESC, support DESC, source, term 排，和原来 rn=1 那条的排序键逐字一致，
       所以「显示哪一条」跟改动前完全相同，不是碰巧。 */
    $q4 = mysqli_query($conn, "SELECT l.og, l.source, l.term, l.term_name, l.term_desc, l.category,
                                      l.support, l.n_genes, l.n_species, l.pct
                               FROM og_family_term_low l
                               JOIN (SELECT og, MAX(pct) mx FROM og_family_term_low
                                     WHERE og IN ($in) GROUP BY og) m
                                 ON m.og = l.og AND m.mx = l.pct
                               ORDER BY l.og, l.pct DESC, l.support DESC, l.source, l.term");
    while ($q4 && ($r = mysqli_fetch_assoc($q4))) { $low[$r['og']] = $r + array('tier' => 'low'); }
    $q5 = mysqli_query($conn, "SELECT og, COUNT(*) c FROM og_family_term_low
                               WHERE og IN ($in) GROUP BY og");
    while ($q5 && ($r = mysqli_fetch_assoc($q5))) { $lowN[$r['og']] = (int)$r['c']; }
}

/* $keep 是**已经 HTML 转义**的（页面上是 <?= $keep ?> 直接进属性），$__filterQs 是
   没转义的（要交给 cnido_sort_link，它自己会 htmlspecialchars）。两者不能混用：
   把带 &amp; 的串喂给 cnido_sort_link 会变成 &amp;amp;，参数分隔符当场失效。 */
$__filterQs = 'q=' . urlencode($q) . '&scope=' . urlencode($scope)
            . '&cons=' . urlencode($cons) . '&sort=' . urlencode($sort);
$keep = str_replace('&', '&amp;',
    $__filterQs . '&per_page=' . $per_page . ($__cSortQs !== '' ? '&' . $__cSortQs : ''));

/* 「这些注释是用什么做出来的」那一块的数据（已缓存，见 gfa_source_summary()）。 */
list($gfaSrc, $gfaFam) = gfa_source_summary($conn);
?>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Gene Family</b></legend>

<p class="paleo-intro">
    Orthologous gene families (orthogroups) inferred by OrthoFinder across the cnidarian proteomes hosted here.
    The inference also included a small non-cnidarian outgroup (one ctenophore and four sponges), which appears
    in the member tables as <i>OUT</i> and is labelled there as an outgroup rather than a species.
    A family that has a consensus is annotated with the function that its <i>member genes agree on</i>: each Pfam
    domain, PANTHER family, GO term and KEGG orthology entry is counted across the family and reported with the
    fraction of member genes that carry it. <b>100%</b> means the whole family agrees (the strict consensus);
    <b>80%</b> and <b>50%</b> are the relaxed tiers.
    <?php /* 这一整句只有在 og_family_term_low 真在的时候才成立（它承诺「不会留白」）；
             表不在时页面走的是老文案，说这句话就是空头支票。 */ ?>
    <?php if (gfa_low_ready($conn)) { ?>
    A term carried by <i>fewer</i> than half of the member
    genes is <strong>not</strong> a consensus; when a family has nothing better, its strongest such term is
    still shown, in a <span class="gfa-badge gfa-low" style="font-size:12px">hollow badge</span> marked
    &ldquo;below the 50% cut-off&rdquo;, so that the family is never left blank.
    <?php } ?>
    <?php /* 这两句原来写「A family in which no term reaches the 50% tier has no consensus annotation at all;
             the list below shows it anyway, with an explicit "no term reaches 50% of the member genes"
             in place of a function」—— 2026-09-30 加了 og_family_term_low 之后「in place of a function」
             已经不准了：有弱证据的家族现在会显示最接近共识的那一条，只有**一条弱证据都没有**的
             才印那句没有任何条目。（更早还有一句「只有切到 all 才看得到」，随 scope 默认改 all 已删。） */ ?>
    <?php if (gfa_low_ready($conn)) { ?>
    Only a family in which no term at all is shared by two or more of its member genes shows no term
    whatsoever (the scope and consistency selectors below can narrow the list down to consensus families
    only, or to the ones whose best evidence stays below the 50% cut-off).
    <?php } else { ?>
    A family in which no term reaches the 50% tier has no consensus annotation at all; the list below shows
    it anyway, with an explicit &ldquo;no term reaches 50% of the member genes&rdquo; in place of a function.
    <?php } ?>
    Search below by function name, domain accession (e.g. <code>PF00069</code>), GO term
    (<code>GO:0006355</code>), KEGG orthology (<code>K02183</code>), or by family ID.
</p>

<?php
/* ---------------------------------------------------- 注释来源小结 ------- */
/* 四个来源各覆盖多少家族 / 多少个条目 / 多少条在全体成员里一致。
   顺序按页面正文里第一次提到它们的次序（Pfam、PANTHER、GO、KEGG），不按家族数。 */
$gfaSrcMeta = array(
    'pfam'    => array('Pfam',    'Protein domains and families (profile HMMs) &mdash; what the gene is built of.'),
    'panther' => array('PANTHER', 'Curated protein families and subfamilies &mdash; which named family the protein belongs to.'),
    'go'      => array('GO',      'Gene Ontology terms &mdash; molecular function, biological process, cellular component.'),
    'kegg'    => array('KEGG',    'KEGG orthology entries &mdash; the pathway-level reaction the protein takes part in.'),
);
?>
<div class="gfa-sources">
    <div class="gfa-sources-hd">
        Consensus annotation is built from four sources, each counted across the member genes of every
        orthogroup: of the <b><?= number_format((int)$gfaFam['total']) ?></b> families,
        <b><?= number_format((int)$gfaFam['annot']) ?></b> carry at least one term at the
        <b>50%</b> tier (&ge;50% of their member genes), and
        <b><?= number_format((int)$gfaFam['t100']) ?></b> carry a term that <b>every</b> member gene has.
        <?php /* 三分数：annot + weak_only + nothing = total。三者互斥，读者不必自己减。 */ ?>
        <?php if (gfa_low_ready($conn) && (int)$gfaFam['weak_only'] + (int)$gfaFam['nothing'] > 0) { ?>
            Of the rest, <b><?= number_format((int)$gfaFam['weak_only']) ?></b> have no term at that tier but
            do carry weaker evidence &mdash; below the 50% cut-off, shown with a hollow badge &mdash; and
            <b><?= number_format((int)$gfaFam['nothing']) ?></b> have no term shared by even two of their
            member genes.
        <?php } ?>
    </div>
    <?php /* 四个来源写成一列一块（grid），不是表格：这张「表」只有四行、五个短字段，
             做成 gridtable 在手机上 max-content 会撑到 925px，整块要横滑才看得完
             （2026-09-30 实测 375/768px 都是如此）。grid 在 900px 以下收成一列自然堆叠。
             「families」不是互斥计数 —— 一个家族常同时被两三个来源注释到，所以四个数
             相加大于上面那个 23,302，脚注里点明。 */ ?>
    <div class="gfa-src-grid">
        <?php foreach ($gfaSrcMeta as $key => $meta) {
            $s = isset($gfaSrc[$key]) ? $gfaSrc[$key] : array('n_fam' => 0, 'n_terms' => 0, 'n_all' => 0);
            echo "<div class='gfa-src-cell'>";
            echo "<span class='gfa-src gfa-src-" . gfa_h($key) . "'>" . gfa_h($meta[0]) . "</span>";
            echo "<span class='gfa-src-num'>" . number_format((int)$s['n_fam']) . " families &middot; "
               . number_format((int)$s['n_terms']) . " terms &middot; "
               . number_format((int)$s['n_all']) . " at 100%</span>";
            echo "<span class='gfa-src-why'>" . $meta[1] . "</span>";
            echo "</div>";
        } ?>
    </div>
    <div class="gfa-sources-ft">
        A family is counted under every source that annotates it, so these four counts overlap and add up
        to more than <?= number_format((int)$gfaFam['annot']) ?>.
    </div>
</div>

<p class="paleo-intro" style="font-size:16px;color:#475569">
    A family's gene count is the number of <i>distinct annotated genes</i>, counted per species. Two of the
    source proteomes (<i>Alatina alata</i> and <i>Calvadosia cruxmelitensis</i>) list one gene identifier on
    several different sequences; those extra sequences are shown separately as &ldquo;sequences&rdquo; where
    they occur, and are not counted twice towards the consensus below, because the underlying annotation is
    stored per gene identifier.
</p>

<form method="get" action="genefamily.php">
    <?php /* 2026-09-30：<form> 是纯外壳、不带版式；灰盒子（.gf-form）与白盒子
             （.pagination-container）是它的两个子块。之所以要把 </form> 一直拖到药丸那一行
             之后，是因为搜索框现在要和「📊 67,795 gene families」同一个白盒子、贴最右边 ——
             它必须仍在这个 form 里，否则提交时就带不上 scope/cons/sort/per_page。 */ ?>
    <div class="gf-form">
    <div class="gf-row gf-opts">
        <div class="gf-filters">
        <label>Show:
            <select name="scope" onchange="this.form.submit()">
                <option value="annotated" <?= $scope === 'annotated' ? 'selected' : '' ?>>families with a consensus annotation</option>
                <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>all families (incl. unannotated)</option>
            </select>
        </label>
        <label>Consistency:
            <select name="cons" onchange="this.form.submit()">
                <option value="any"   <?= $cons === 'any'   ? 'selected' : '' ?>>any</option>
                <option value="all"   <?= $cons === 'all'   ? 'selected' : '' ?>>&#10003; 100% of all member genes</option>
                <option value="ge80"  <?= $cons === 'ge80'  ? 'selected' : '' ?>>&ge;80% of all member genes</option>
                <option value="ge50"  <?= $cons === 'ge50'  ? 'selected' : '' ?>>&ge;50% of all member genes</option>
                <option value="annot" <?= $cons === 'annot' ? 'selected' : '' ?>>&#10003; 100% of the annotated members</option>
                <?php /* 表不在就不提供这一档，免得选了得到空结果（白名单那边也同步挡了） */ ?>
                <?php if (gfa_low_ready($conn)) { ?>
                <option value="below50" <?= $cons === 'below50' ? 'selected' : '' ?>>below the 50% cut-off (weaker evidence)</option>
                <?php } ?>
            </select>
        </label>
        <label>Sort by:
            <select name="sort" onchange="this.form.submit()">
                <option value="og" <?= $sort === 'og' ? 'selected' : '' ?>>family ID</option>
                <option value="consistency" <?= $sort === 'consistency' ? 'selected' : '' ?>>annotation consistency</option>
                <option value="size" <?= $sort === 'size' ? 'selected' : '' ?>>family size</option>
            </select>
        </label>
        <label>Per page:
            <select name="per_page" onchange="this.form.submit()">
                <?php /* 迭代 $per_page_options 而不是再写死一份：2026-09-30 白名单加了
                         500/1000，这里漏改的话 ?per_page=500 会「页面按 500 渲染、
                         下拉框却显示 10（没有任何 option 命中 selected，浏览器落回首项）」。 */
                foreach ($per_page_options as $o) {
                    echo "<option value='$o' " . ($o == $per_page ? 'selected' : '') . ">$o</option>";
                } ?>
            </select>
        </label>
        </div>
    </div>
    </div><!-- /.gf-form -->

<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            &#128202;
            <?php if ($q !== '') { ?>
                <?= number_format($total_records) ?> famil<?= $total_records == 1 ? 'y' : 'ies' ?> matching
                <strong><?= gfa_h($q) ?></strong>
            <?php } else {
                /* 数字旁边那句话要说清「这是被什么条件筛出来的」。cons 一旦不是 any，
                   原先那句固定的 'in total' 就是错的 —— 那时列出来的既不是全部，
                   也不只是「有共识的」。 */
                $consLabel = array(
                    'all'   => 'whose members all carry the same term',
                    'ge80'  => 'with a term carried by &ge;80% of their member genes',
                    'ge50'  => 'with a consensus annotation',
                    'annot' => 'annotated consistently wherever evidence exists',
                    'below50' => 'with no consensus, but with weaker evidence below the 50% cut-off',
                );
                $sub = ($cons !== 'any' && isset($consLabel[$cons])) ? $consLabel[$cons]
                     : ($scope === 'annotated' ? 'with a consensus annotation' : 'in total');
                ?>
                <?= number_format($total_records) ?> gene famil<?= $total_records == 1 ? 'y' : 'ies' ?> <?= $sub ?>
            <?php } ?>
        </div>
        <?php /* .pagination-info 是 justify-content:space-between —— 药丸是第一个孩子、
                 搜索框是第二个，宽屏自动一左一右顶到两头。窄屏那条 768px 的规则把它
                 改成竖排 flex-start，搜索框落到药丸下面。 */ ?>
        <div class="gf-search">
            <input type="text" name="q" value="<?= gfa_h($q) ?>" placeholder="Search a function, domain, GO term or family ID&hellip;">
            <button type="submit">Search</button>
            <?php if ($q !== '') { ?>
                <a class="gf-clear" href="genefamily.php">clear</a>
            <?php } ?>
        </div>
    </div>
</div>
</form>

<div class="table-container">
    <div class="table-scroll-container">
        <table class="gridtable">
            <thead>
            <tr>
                <th width="10%"><?= cnido_sort_link('og', 'Gene family', $__cSort, $__cDir, $__filterQs, 'c') ?></th>
                <th width="9%"><?= cnido_sort_link('genes', 'Genes', $__cSort, $__cDir, $__filterQs, 'c') ?></th>
                <th width="9%"><?= cnido_sort_link('species', 'Species', $__cSort, $__cDir, $__filterQs, 'c') ?></th>
                <th width="72%"><?= cnido_sort_link('term', 'Consensus function (agreed on by the family\'s member genes)', $__cSort, $__cDir, $__filterQs, 'c') ?></th>
            </tr>
            </thead>
            <?php foreach ($rows as $r) {
                $link = 'genefamily_result.php?family=' . urlencode($r['og']);
                echo "<tr align='center'>";
                echo "<td><a class='gene-link' href='" . gfa_h($link) . "'>" . gfa_h($r['og']) . "</a></td>";
                echo "<td>" . number_format((int)$r['n_genes']);
                if ((int)$r['n_seqs'] > (int)$r['n_genes']) {
                    echo "<br><span style='font-size:12px;color:#64748b'>"
                       . number_format((int)$r['n_seqs']) . " sequences</span>";
                }
                echo "</td>";
                echo "<td>" . (int)$r['n_species'] . "</td>";
                echo "<td style='text-align:left'>";

                $hits = isset($matched[$r['og']]) ? $matched[$r['og']] : array();
                if ($hits) {
                    /* the search-matched terms are the most relevant thing to show */
                    $shown = 0;
                    foreach ($hits as $t) {
                        if ($shown >= 4) break;
                        echo "<div class='gfa-line gfa-hit'>" . gfa_term_line($t) . "</div>";
                        $shown++;
                    }
                    if (count($hits) > $shown) {
                        echo "<div class='gfa-more'><a href='" . gfa_h($link) . "'>+" . (count($hits) - $shown)
                           . " more matching term(s) &rarr;</a></div>";
                    }
                } elseif ($cons === 'annot' && isset($annot[$r['og']])) {
                    /* 这一档列出来的理由就是这一条，所以它顶掉下面那条 best_* ——
                       否则读者看到的是「58%」，看不出这个家族为什么被筛出来。 */
                    echo "<div class='gfa-line'>" . gfa_annot_line($annot[$r['og']]) . "</div>";
                    if ((int)$r['n_terms'] > 1) {
                        echo "<div class='gfa-more'><a href='" . gfa_h($link) . "'>"
                           . (int)$r['n_terms'] . " consensus terms in total &rarr;</a></div>";
                    }
                } elseif ($r['best_tier'] !== '' && $r['best_tier'] !== null) {
                    echo "<div class='gfa-line'>" . gfa_term_line(array(
                        'tier' => $r['best_tier'], 'pct' => $r['best_pct'], 'source' => $r['best_source'],
                        'term' => $r['best_term'], 'term_name' => $r['best_name'], 'term_desc' => $r['best_desc'],
                        'category' => $r['best_cat'], 'support' => $r['best_support'], 'n_genes' => $r['n_genes'],
                    )) . "</div>";
                    if ((int)$r['n_terms'] > 1) {
                        echo "<div class='gfa-more'><a href='" . gfa_h($link) . "'>"
                           . (int)$r['n_terms'] . " consensus terms in total";
                        if ((int)$r['n_terms_all'] > 0) { echo " &middot; " . (int)$r['n_terms_all'] . " at 100%"; }
                        echo " &rarr;</a></div>";
                    }
                } else {
                    /* 没有 ≥50% 的共识。原来这一格只有一句 "no term reaches 50% of the member
                       genes"，用户什么也拿不到 —— 现在改成三态：
                         有弱证据  印最接近共识的那条（空心徽章 + below the 50% cut-off）
                         确实没有  没有任何条目的 support>=2，明说「两个以上成员基因共有的条目
                                   一条都没有」——这句话才是真的（见下）
                         数据缺    离线表不在，退回老文案，不能假装查过
                       "没有弱条目" 等价于 "没有任何条目 support>=2"：keep 的条件是
                       support>=2 且 2*support<n_genes，所以凡是 support>=2 又不到 50% 的都在
                       那张表里；而 ≥50% 的条目在这条分支上本来就不存在。反过来说，若某个家族
                       一条弱条目都没有，它就没有任何被两个以上成员基因共有的注释。
                       顺带一句：2*support<n_genes 且 support>=2 要求 n_genes>=5，所以 5 个基因
                       以下的家族永远到不了这里的第一态 —— 这是数据本身的下限，不是筛选失误。 */
                    if (isset($low[$r['og']])) {
                        echo "<div class='gfa-line'>" . gfa_low_line($low[$r['og']]) . "</div>";
                        $nLow = isset($lowN[$r['og']]) ? $lowN[$r['og']] : 0;
                        if ($nLow > 1) {
                            echo "<div class='gfa-more'><a href='" . gfa_h($link) . "'>"
                               . number_format($nLow) . " weaker term(s) below the 50% cut-off &rarr;</a></div>";
                        }
                    } elseif (gfa_low_ready($conn)) {
                        echo "<span class='gd-na'>no Pfam / PANTHER / GO / KEGG term is shared by two or "
                           . "more of its member genes</span>";
                    } else {
                        echo "<span class='gd-na'>no term reaches 50% of the member genes</span>";
                    }
                }
                echo "</td></tr>";
            }
            if (empty($rows)) {
                echo "<tr><td colspan='4' style='color:#64748b'>No gene family matches this query.</td></tr>";
            }
            ?>
        </table>
    </div>
</div>

<!-- pagination -->
    <?php if ($total_pages > 0): /* 命中 0 条时不渲染分页条：$total_pages
         是 0，页码循环一次都不进，几个按钮和「of 0 pages」却照旧印出来，
         全部指向自己。站内约定见 browse.php / go_result.php。 */ ?>
<div class="pagination-nav">
    <a href="?<?= $keep ?>&amp;page=1" class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">&laquo; First</a>
    <a href="?<?= $keep ?>&amp;page=<?= max(1, $page - 1) ?>" class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">&lsaquo; Previous</a>
    <?php
    $start_page = max(1, $page - 3);
    $end_page   = min($total_pages, $page + 3);
    if ($start_page > 1) {
        echo '<a href="?' . $keep . '&amp;page=1" class="page-btn">1</a>';
        if ($start_page > 2) { echo '<span class="page-btn disabled">...</span>'; }
    }
    for ($i = $start_page; $i <= $end_page; $i++) {
        echo '<a href="?' . $keep . '&amp;page=' . $i . '" class="page-btn ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
    }
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) { echo '<span class="page-btn disabled">...</span>'; }
        echo '<a href="?' . $keep . '&amp;page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
    }
    ?>
    <a href="?<?= $keep ?>&amp;page=<?= min($total_pages, $page + 1) ?>" class="page-btn <?= ($page >= $total_pages) ? 'disabled' : '' ?>">Next &rsaquo;</a>
    <a href="?<?= $keep ?>&amp;page=<?= $total_pages ?>" class="page-btn <?= ($page >= $total_pages) ? 'disabled' : '' ?>">Last &raquo;</a>
</div>

<div class="go-to-page">
    <span>Go to page:</span>
    <input type="number" id="gotoPage" min="1" max="<?= $total_pages ?>" value="<?= $page ?>">
    <button onclick="window.location.href='?<?= $keep ?>&amp;page='+document.getElementById('gotoPage').value">Go</button>
    <span>of <?= max(1, $total_pages) ?> pages</span>
</div>    <?php endif; /* $total_pages > 0 */ ?>


<?php $conn->close(); ?>

	</div>
	</div>
	</div>
	<?php
		include "Webpage_components.php";
		print $footer;
	?>
	</body>
	</html>
