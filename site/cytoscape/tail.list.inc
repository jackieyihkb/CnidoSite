    ]
  },
  
  ready: function(){
    window.cy = this;
    
    // giddy up
  }
});

cy.panzoom({
	// options here...
});

// 节点悬停提示：基因号 + 注释 + gene_detail.php 链接。
// 实现在 includes/network_graph.php（cnido_net_tip_js），内容取自该页输出的
// CNIDO_NET_ANNO；旧代码在这里读节点自己的 annotation 字段，而字段本身没有
// 转义，含单引号的注释（如 5'-NUCLEOTIDASE）会把整个 cytoscape 初始化打断。
cnidoNetBindTips();

}); // on dom ready
// 导出（exportgraph / base64ToBlob）由 includes/network_graph.php 提供，
// 两个渲染器共用一份；原来这里的那份已在收拢时删除。
</script>
<style>
/* Network结果页面专用样式 - 不影响全局CSS */
.network-result-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.network-result-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.network-result-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.transcriptome-badge {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* 导出区域美化 */
.export-section {
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

.export-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

.export-select {
    padding: 12px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 15px;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 150px;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 20px;
}

.export-select:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.export-btn {
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

.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    background:linear-gradient(135deg, #065f46 0%, #064e3b 100%);
}

/* 网络视图容器美化 */
.network-view-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e2e8f0;
    margin: 30px 0;
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

#cy {
    width: 100%;
    height: 560px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

/* 图例美化 */
.legend-container {
    background: #f8fafc;
    padding: 20px 25px;
    border-radius: 8px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
}

.legend-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-title::before {
    content: "📊";
    font-size: 18px;
}

.legend-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #475569;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    flex-shrink: 0;
}

/* 表格美化 */
.table-container {
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
}

.table-scroll-container {
    max-height: 500px;
    overflow-y: auto;
	margin: 10px 20px;
}

/* 进一步分析区域美化 */
.analysis-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 25px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
}

.analysis-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.analysis-title::before {
    content: "🔬";
    font-size: 24px;
}

.analysis-forms {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
}

.analysis-form {
    background: #f8fafc;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.analysis-form textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    background: white;
    resize: vertical;
    margin-bottom: 15px;
    min-height: 100px;
}

.analysis-form textarea:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.analysis-btn {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
}

.analysis-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

/* 警告信息美化 */
.warning-message {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 4px solid #f59e0b;
    padding: 15px 20px;
    border-radius: 8px;
    margin: 20px 0;
    color: #92400e;
    font-size: 14px;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .network-result-header {
        padding: 20px;
    }
    
    .network-result-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .export-section {
        flex-direction: column;
        align-items: stretch;
    }
    
    .export-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .export-select, .export-btn {
        width: 100%;
    }
    
    .analysis-forms {
        grid-template-columns: 1fr;
    }
    
    .legend-grid {
        grid-template-columns: 1fr;
    }
}
</style>
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Co-expression Network Analysis</b></legend>
<p class="paleo-intro">Interactive visualization of gene co-expression networks, expression and functional enrichment analysis.</p>
<?php
$list1 = array();
$without_network = array();
$gene1 = "";
$list2 = "";
foreach($list as $gene) {
    if ($gene === '') { continue; }   /* 上面 \s+ → \n 的置换会在首尾/连续空白处留下空串 */
    if((!$hash1[$gene]) && (!$hash2[$gene])) {
        array_push($without_network, $gene);
    } else {
        array_push($list1, $gene);
    }
}
/* 原来这里两种情况都只写 $gene1 = "";，下面 if($gene1) 永远为假，警告一次也没印过。
   同时这些基因被丢出 $list1，既不进图也不进图题，用户看不出自己提交的基因少了。
   现在按原意把名单拼出来。全都没有网络时走不到这里（network.list.php 边数为 0
   会先弹窗跳回），所以至少有一个基因在网络里。 */
$gene1 = implode(', ', $without_network);
$list1 = array_unique($list1);
foreach($list1 as $gene2) {
    $list2 = $list2 . " " . $gene2;
}
?>

<!-- 导出区域 -->
<div class="export-section">
    <div style="font-size: 16px; font-weight: 600; color: #1e293b;">
        📊 Global Network of <i style="color:#047857;"><?php echo htmlspecialchars($list2); ?></i>
    </div>
    <div class="export-controls">
        <select id="export" class="export-select">
            <option value="jpg">JPG Image</option>
            <option value="png">PNG Image</option>
            <option value="json">JSON Data</option>
        </select>
        <button onclick="exportgraph()" class="export-btn">
            ⬇️ Export Graph
        </button>
    </div>
</div>

<?php if($gene1): ?>
<div class="warning-message">
    <?php /* 单复数分开写：原来只有一句 "Query genes without co-expression networks: X"，
             不说明后果。 */ ?>
    ⚠️ No co-expression network was found for <strong><?php echo htmlspecialchars($gene1); ?></strong>,
    <?php echo count($without_network) === 1 ? 'so this query gene is' : 'so these query genes are'; ?>
    not shown in the graph or the table below.
</div>
<?php endif; ?>

<!-- 网络视图容器 -->
<div class="network-view-container">
    <div class="network-view-header">
        <a name="locus">Co-expression Network View</a>
    </div>
    <div class="network-view-content">
        <div id="cy"></div>
        
        <!-- 图例 -->
        <div class="legend-container">
            <div class="legend-title">Network Legend</div>
            <div class="legend-grid">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #E1E100;"></div>
                    <span><strong>Yellow:</strong> Query proteins</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #86B342;"></div>
                    <span><strong>Green:</strong> Co-expressed partners</span>
                </div>
                <?php /* 这个模块里画出来的边只有共表达一种：'#EDA1ED' 与 '#6FB1FC'
                       只在 network.list.php 里按 'positive' / 'negative' 两个 kind 发出，
                       库里也没有任何 interaction / PPI 表。原来写「Own interaction +
                       positive co-expression」，等于告诉读者粉线还含有一层本模块没有的
                       交互证据；绿色节点的名字同理。按实际数据说。 */ ?>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #EDA1ED;"></div>
                    <span><strong>Pink line:</strong> Positive co-expression</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #6FB1FC;"></div>
                    <span><strong>Blue line:</strong> Negative co-expression</span>
                </div>
            </div>
            <div style="margin-top:12px;padding-top:10px;border-top:1px solid #e2e8f0;font-size:16px;color:#475569;line-height:1.7;">
                <strong>Node size</strong> reflects how many connections the gene has (hubs are drawn larger).
                <strong>Hover any node</strong> to see the annotation of the gene it stands for
                (PANTHER / InterPro / GO description, or NR when the others are absent) and a link to its gene page.
            </div>
        </div>
    </div>
</div>

<!-- 网络详情链接。原来这里多带一个 edge=<数组>：$edge 是本页要画的那串 cytoscape
     元素，echo 一个数组只会得到字符串 "Array"，network_detail.php 拿它 base64
     解出来是空的，于是「按选中基因过滤」这一步静默失效（恰好退化成显示全部边，
     所以看不出坏）。这个参数是给「只看某个基因的边」用的，本页要看的是整张网络，
     不传就对了。 -->
<div style="text-align: center; margin: 20px 0;">
    <a href="./network_detail.php?job=<?php echo urlencode($job); ?>&amp;species=<?php echo urlencode($species); ?>" class="gene-link" style="font-size: 18px;">
        📋 View Detailed Network Information
    </a>
</div>

<!-- 基因表格 -->
<div class="table-container">
    <div class="network-view-header">
        Co-expressed Genes of <i style="color:#047857;"><?php echo htmlspecialchars($list2); ?></i>
    </div>
<?php if (!empty($net_truncated)): ?>
    <div style="margin:8px 0 4px;padding:9px 13px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:16px;line-height:1.65;">
        <strong>Note:</strong> a hub gene can be co-expressed with thousands of genes, so each query gene is
        limited to its <b><?php echo (int)CNIDO_NET_TOP_K; ?></b> strongest partners (positive and negative
        counted separately). Both the networks above and the table below show only those &mdash; this is not
        the complete network of the query genes.
    </div>
<?php endif; ?>
    <div class="table-scroll-container">
        <table class="gridtable table2excel">
            <thead>
                <tr>
                    <th width="15%">Gene ID</th>
                    <th width="60%">Description</th>
                    <th>PCC</th>
                    <th>Relationship</th>
                </tr>
            </thead>
            <tbody>
<?php
trim($species);
$list_analysis_p = "";   /* 下面用 .= 累加，不先初始化会有 notice */
$list_analysis_n = "";
foreach($list as $gene)
{
	if((!isset($hash1[$gene])) && (!isset($hash2[$gene])) ){
	continue;
	}
	/* Description 与上面的网络图和悬停卡片同源：network.list.php 已经批量取过
	   cnido_net_annotations()，这里直接复用那份 $anno，回退顺序也一样
	   （panther → ipr → go → nr，见 cnido_net_summary()）。
	   原来这里只查 <ABBR>_panther，而 _panther 覆盖不全：表格印 "--"、卡片却有文字，
	   同一张页面上两处自相矛盾（实测 PCLAV 的 CAB4046353.1：101 格里 67 格是 "--"，
	   其中 66 格卡片是有描述的）。顺带删掉两条取回来从没用过的 _locus 查询。 */
	$desc = cnido_net_summary(isset($anno[$gene]) ? $anno[$gene] : array());
	echo "<tr style=\"font-weight:bold;color:#de742a\"><td align='center'><a href=\"../gene_detail.php?species=$organism&gene=$gene\">$gene</a></td><td>" . ($desc === '' ? '--' : htmlspecialchars($desc)) . "</td>";
	echo "<td align='center'>1</td><td align='center'>positive</td></tr>";
$list_analysis_p.="$gene\n";
if($positive == 1)
{
	$conn1 = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	/* 每个查询基因只列 top-K 个共表达伙伴。原来这里是「这个基因的全部伙伴」：
	   枢纽基因一个就能刷出 5803 行，而且**每一行**还要再查一次 _locus 和
	   _panther，10 个枢纽基因 ≈ 5.8 万行 × 2 次查询 —— 页面体积和查询数都由
	   数据里的枢纽决定，不由用户决定。
	   顺序也一并修了：原来 `ORDER BY PCC DESC` 作用在 text 列上，负向表的
	   '-0.72' 按文本比较是**大于** '-0.12' 的，所以负相关一直把最弱的排在最前。
	   现在一律走 cnido_net_partner_rows()，按数值排（正向取大、负向取小），
	   并且和上面网络图用同一份 top-K、同一个顺序，图和表不会互相矛盾。
	   每行那两条 _locus 查询取回来从没被用过，已随本轮改动删除；Description
	   也不再单查 _panther，改用上面批量取的 $anno。 */
	foreach (cnido_net_partner_rows($conn1, $pos, $gene, CNIDO_NET_TOP_K, true) as $result) {
		if (strcasecmp($result[0], $gene) === 0) { $partner = $result[1]; }
		elseif (strcasecmp($result[1], $gene) === 0) { $partner = $result[0]; }
		else { continue; }
	/* Description 同上：用 network.list.php 批量取好的那份 $anno，不再单查 _panther。 */
	$desc = cnido_net_summary(isset($anno[$partner]) ? $anno[$partner] : array());
	$pcc_format= sprintf("%.2f",$result[2]);
	echo "<tr><td align='center'><a href=\"../gene_detail.php?species=$organism&gene=$partner\">$partner</a></td><td>" . ($desc === '' ? '--' : htmlspecialchars($desc)) . "</td>";
	echo "<td align='center'>$pcc_format</td><td style=\"color:#EDA1ED\" align='center'>positive</td></tr>";
	$list_analysis_p="$list_analysis_p$partner\n";
	}
}
if($negative == 1)
{
	$conn1 = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
	/* 负向表：pcc 是负值（实测 SSIDE ∈ [-0.721, -0.123]），"强"是数值更小，
	   所以 cnido_net_partner_rows() 的第 5 个参数传 false（升序）。 */
	foreach (cnido_net_partner_rows($conn1, $neg, $gene, CNIDO_NET_TOP_K, false) as $result) {
		if (strcasecmp($result[0], $gene) === 0) { $partner = $result[1]; }
		elseif (strcasecmp($result[1], $gene) === 0) { $partner = $result[0]; }
		else { continue; }
	/* Description 同上：用 network.list.php 批量取好的那份 $anno，不再单查 _panther。 */
	$desc = cnido_net_summary(isset($anno[$partner]) ? $anno[$partner] : array());
	$pcc_format= sprintf("%.2f",$result[2]);
	echo "<tr><td align='center'><a href=\"../gene_detail.php?species=$organism&gene=$partner\">$partner</a></td><td>" . ($desc === '' ? '--' : htmlspecialchars($desc)) . "</td>";
	echo "<td align='center'>$pcc_format</td><td style=\"color:#6FB1FC\" align='center'>negative</td></tr>";
	$list_analysis_n="$list_analysis_n$partner\n";
	}
}
}
$list_analysis_p = $list_analysis_p . $list_analysis_n;
?>
            </tbody>
        </table>
    </div>
</div>

<!-- 导出Excel按钮 -->
<div style="display: flex; justify-content: flex-end; margin: 30px 0;">
    <button id="btn" class="export-btn">
        📥 Save as Excel File
    </button>
</div>

<!-- 进一步分析区域 -->
<div class="analysis-section">
    <div class="analysis-title">Further Analysis for Network Members</div>
    <div class="analysis-forms">
        <!-- 表达谱分析表单 -->
        <form action="./network_heatmap.php" target="_blank" method="post" class="analysis-form">
            <textarea name="queryList" readonly><?php echo htmlspecialchars($list_analysis_p); ?></textarea>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <input type="hidden" name="species" value="<?php echo htmlspecialchars($species); ?>">
            <input type="hidden" name="genelist" value="<?php echo htmlspecialchars($list_analysis_p); ?>">
            <button type="submit" class="analysis-btn">
                📈 Expression Profiling Analysis
            </button>
        </form>
        
        <!-- GSEA分析表单 -->
        <form name="GSEA" method="post" action="../GSEA/GSEA.php" target="_blank" enctype="multipart/form-data" class="analysis-form">
            <textarea name="gsea_in" readonly><?php echo htmlspecialchars($list_analysis_p); ?></textarea>
            <input type="hidden" name="species" value="<?php echo htmlspecialchars($species); ?>">
            <button type="submit" class="analysis-btn">
                🧬 Gene Sets Enrichment Analysis
            </button>
        </form>
    </div>
</div>

<?php
/* 交接给 Dynamic Expression View。
   手册 4.3 写着「网络分析得到的共表达基因对用于动态网络视图」，但结果页此前没有
   任何入口：用户只能自己从导航菜单找到那一页，而基因对在这个页面上从来没有以可
   复制的形式出现过（下方表格只有单基因行，边列表不在页面上）。那一步在实操中是
   断的 —— 正是审稿人说「不知道怎么用」的地方之一。

   $tmp_edge 每项是 "geneA\tgeneB\tpcc\tmr\t方向"，前两列即基因对。
   注意：不能只把基因对交给 network.expression.php。那一页要求两个输入都非空，
   只给基因对会被它的空输入检查弹回输入页。所以入口指向的是 tpm_ratio.php ——
   用户在那里挑样本、算出表达比值，由它把基因对和比值一起带过去。

   一次最多画 10 对（与 network.list.php 同一上限），所以这里给 |PCC| 最强的 10 对，
   并写明这是子集，免得用户以为全部交出去了。 */
$nxe_hand = array();
foreach ($tmp_edge as $nxe_line) {
    $nxe_f = explode("\t", trim($nxe_line));
    if (count($nxe_f) < 3) { continue; }
    $nxe_hand[] = array($nxe_f[0], $nxe_f[1], abs((float)$nxe_f[2]));
}
usort($nxe_hand, function ($a, $b) { return $b[2] <=> $a[2]; });
$nxe_hand_total = count($nxe_hand);
$nxe_hand = array_slice($nxe_hand, 0, 10);
$nxe_pairs_txt = '';
foreach ($nxe_hand as $nxe_p) { $nxe_pairs_txt .= $nxe_p[0] . ' ' . $nxe_p[1] . "\n"; }
?>
<div class="analysis-section">
    <div class="analysis-title">Next Step: Dynamic Expression View</div>
    <p style="font-size:16px;color:#475569;line-height:1.75;margin:0 0 16px">
        <b>What this does.</b> Everything above treats every gene the same way. Dynamic Expression View redraws
        this same network but colours each node by how much that gene changes between two conditions, so you can
        see which part of the network responds. It needs one input the network itself does not carry: a per-gene
        expression ratio.
    </p>
    <p style="font-size:16px;color:#475569;line-height:1.75;margin:0 0 18px">
        <b>Where the ratio comes from.</b> CnidoSite holds an RNA-seq expression matrix for this species, so the
        ratio can be built here rather than elsewhere: pick the samples for each side of your comparison and every
        gene gets log<sub>2</sub>((mean of group A + 1) / (mean of group B + 1)).
        <?php if ($nxe_hand_total > 10): ?>
        This network has <b><?= $nxe_hand_total ?></b> gene pairs and one run of Dynamic Expression View draws at most
        <b>10</b>, so the button below carries the <b>10 with the strongest |PCC|</b>.
        <?php else: ?>
        All <b><?= $nxe_hand_total ?></b> gene pair<?= $nxe_hand_total === 1 ? '' : 's' ?> of this network are carried over.
        <?php endif; ?>
    </p>
    <div class="analysis-forms">
        <form method="post" action="tpm_ratio.php" class="analysis-form">
            <div style="font-size:14px;font-weight:600;color:#1e293b;margin-bottom:10px">
                Gene pairs carried over (<?= count($nxe_hand) ?>)
            </div>
            <textarea name="networklist" readonly style="min-height:130px"><?= htmlspecialchars($nxe_pairs_txt) ?></textarea>
            <input type="hidden" name="organism" value="<?= htmlspecialchars($latin) ?>">
            <button type="submit" class="analysis-btn">
                🎨 Add expression values &rarr; Dynamic Expression View
            </button>
            <div style="font-size:16px;color:#64748b;line-height:1.7;margin-top:12px">
                Opens the ratio builder with these pairs already loaded. It computes the ratios, then hands both
                the pairs and the ratios to the network view in one step.
            </div>
        </form>
        <div class="analysis-form">
            <div style="font-size:14px;font-weight:600;color:#1e293b;margin-bottom:10px">
                Or take the pairs by hand
            </div>
            <textarea readonly onclick="this.select()" style="min-height:130px"><?= htmlspecialchars($nxe_pairs_txt) ?></textarea>
            <div style="font-size:16px;color:#64748b;line-height:1.7">
                Click to select all, then paste into step&nbsp;2 of
                <a href="network_expression.php">Dynamic Expression View</a> as
                <code>GeneA GeneB</code>, one pair per line. Its step&nbsp;3 still needs the expression ratios.
            </div>
        </div>
    </div>
</div>

</div>
<!-- 原来这里的 jQuery 是从一个第三方站点以 http:// 引入的，失败时再用本地文件
     兜底。那个站现在只剩 301 跳转；而且 http:// 的资源在 https:// 访问本站时会被
     浏览器当混合内容直接拦掉。本站本来就有本地 jQuery，直接用它，不再依赖第三方。 -->
<script src="../js/jquery-1.10.2.min.js"></script>
<script src="./jquery.table2excel.js"></script>
 <script type="text/javascript">
	$(function() {
		$("#btn").click(function(){
			$(".table2excel").table2excel({ // 不被导出的表格行的CSS class类
				exclude: ".noExl",	// 导出的Excel文档的名称
				name: "Excel Document Name",	// Excel文件的名称
				filename: "network_info",	//文件后缀名
				fileext: ".xlsx",	//是否排除导出图片
				exclude_img: true,	//是否排除导出超链接
				exclude_links: true,	//是否排除导出输入框中的内容
				exclude_inputs: false
			});
		}); 
	});
</script>
</div>
</div>

<?php
    include "../Webpage_components.php";
    print $footer;
?>
</body>
</html>
