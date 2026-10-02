<?php
/* =========================================================================
 * CnidoSite — Species Phylogenetic Tree  (重构版)
 * 对应审稿意见 Referee 1 (Species Tree) / Referee 3 (point 5)
 *   1. 按 纲/目/科 着色 + 图例
 *   2. 类群搜索、过滤、高亮、定位
 *   3. 分支折叠 / 展开
 *   4. 物种名点击跳转物种条目页
 *   5. 分分类层级提供子树（并可下载 Newick）
 *   6. 支持率 (SH-aLRT/UFBoot) 显示开关
 *   7. 定根说明 + 分类层级子树下载（Referee 3 point 5a-5c）
 * -------------------------------------------------------------------------
 * 物种条目页：本页树 tip 使用下划线拉丁名（与 abbr.abbr 一致），而物种条目页
 * speciesinfo.php 使用短代码 abbr1，因此统一通过 taxonomy.json 里的 abbr1 跳转。
 * ========================================================================= */
$SPECIES_URL = '/speciesinfo.php';   // 站点真实物种条目页
$NEWICK_URL  = 'data/cnidaria.nwk';
$TAX_URL     = 'data/taxonomy.json';
$TREES_DIR   = 'data/trees';         // 各分类层级子树 newick（由 tools/rebuild_tree_data.py 生成）
$SHOW_MONOPHYLY_NOTE = true;   // 非单系类群时是否显示说明（Referee 3 关注拓扑一致性）
$SHOW_ROOT_NOTE      = true;   // 定根说明（Referee 3 point 5b/5c）

function RANK_LABEL_PHP($r) {
    $m = array('phylum'=>'Phylum','class'=>'Class','order'=>'Order','family'=>'Family','genus'=>'Genus');
    return isset($m[$r]) ? $m[$r] : ucfirst($r);
}

// 读取子树清单（若存在）
$SUBTREES = array();
$__manifest = __DIR__ . '/' . $TREES_DIR . '/manifest.json';
if (is_readable($__manifest)) {
    $__m = json_decode(file_get_contents($__manifest), true);
    if (is_array($__m)) { $SUBTREES = $__m; }
}
?>
<!DOCTYPE html>
<html>
<head>
<script src="/js/rwd-tables.js" defer></script>
<title>Species Phylogenetic Tree - CnidoSite</title>
<meta name="description" content="Species-level phylogenetic tree of the cnidarian genomes held in CnidoSite" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="./font-awesome.css">
<link rel="stylesheet" href="./bootstrap-icons.min.css">
<script src="./jquery-3.2.1.min.js"></script>
<script src="./bootstrap.min.js"></script>
<script src="./d3.min.js"></script>
<script src="./bundle.js"></script>
<link href="./phylotree.css" rel="stylesheet" />
<style>
.note-box{background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #3b82f6;border-radius:10px;padding:14px 18px;margin:0 0 14px;color:#334155;font-size:16px;line-height:1.7}
.note-box b{color:#1e293b}
.note-box a{color:#2563eb;text-decoration:none}
.note-box a:hover{text-decoration:underline}
.subtree-group{margin-bottom:18px}
.subtree-group-title{font-size:15px;font-weight:700;color:#1e293b;margin-bottom:8px;display:flex;align-items:center;gap:8px}
.subtree-count{background:#e0e7ff;color:#3730a3;border-radius:20px;padding:1px 9px;font-size:12px;font-weight:700}
.subtree-chips{display:flex;flex-wrap:wrap;gap:8px}
.subtree-chip{display:inline-flex;align-items:stretch;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;background:#fff;transition:all .2s ease}
.subtree-chip:hover{border-color:#bfdbfe;box-shadow:0 2px 8px rgba(59,130,246,.15)}
.subtree-chip a{display:inline-flex;align-items:center;gap:6px;padding:5px 8px 5px 14px;font-size:13.5px;color:#334155;text-decoration:none}
.subtree-chip a:hover{color:#2563eb}
.subtree-chip a em{font-style:normal;font-size:11.5px;color:#64748b}
.subtree-view{border:none;border-left:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:12px;padding:0 11px;cursor:pointer;transition:all .2s ease}
.subtree-view:hover{background:#f0f9ff;color:#2563eb}
.tree-hero{background:linear-gradient(135deg,#1e3a8a 0%,#1e40af 50%,#1d4ed8 100%);color:#fff;padding:40px 30px;border-radius:20px;margin:30px 0;box-shadow:0 20px 60px rgba(30,64,175,.25);position:relative;overflow:hidden}
.tree-hero::before{content:"";position:absolute;top:-50%;right:-20%;width:400px;height:400px;background:radial-gradient(circle,rgba(255,255,255,.15) 0%,transparent 70%);border-radius:50%}
.tree-hero h1{margin:0 0 15px 0;font-size:32px;font-weight:800;display:flex;align-items:center;gap:15px;position:relative;z-index:1}
.tree-hero p{margin:0;font-size:18px;opacity:.95;line-height:1.6;max-width:900px;position:relative;z-index:1}
.module-badge{background:rgba(255,255,255,.2);backdrop-filter:blur(10px);padding:10px 20px;border-radius:50px;font-size:16px;font-weight:600;display:inline-flex;align-items:center;gap:10px;margin-top:20px;border:1px solid rgba(255,255,255,.3);position:relative;z-index:1}
.control-panel{background:#fff;border-radius:20px;box-shadow:0 10px 40px rgba(0,0,0,.08);padding:30px;margin:40px 0;border:1px solid #f0f0f0}
.control-header{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:20px;padding-bottom:20px;border-bottom:2px solid #f1f5f9}
.control-title{font-size:22px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:10px}
.control-title::before{content:"\1F333";font-size:26px}
.ctl-row{display:flex;flex-wrap:wrap;gap:14px;align-items:center;margin-bottom:14px}
.ctl-label{font-size:15px;font-weight:600;color:#475569;white-space:nowrap}
.modern-select{padding:10px 34px 10px 14px;border-radius:12px;border:2px solid #e2e8f0;background:#fff;font-size:15px;color:#1e293b;cursor:pointer;transition:all .3s ease;min-width:220px;appearance:none;background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");background-position:right 12px center;background-repeat:no-repeat;background-size:20px}
.modern-select:focus{outline:none;border-color:#1d4ed8;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.search-box{position:relative;display:flex;align-items:center}
.search-box input{padding:10px 14px 10px 38px;border-radius:12px;border:2px solid #e2e8f0;font-size:15px;width:320px;transition:all .3s ease}
.search-box input:focus{outline:none;border-color:#1d4ed8;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.search-box .fa-magnifier{position:absolute;left:13px;color:#64748b}
.btn-modern{padding:10px 16px;border-radius:12px;border:1px solid #e2e8f0;background:#fff;color:#334155;font-size:14px;font-weight:600;cursor:pointer;transition:all .25s ease;white-space:nowrap}
.btn-modern:hover{background:#f0f9ff;color:#2563eb;border-color:#bfdbfe;transform:translateY(-1px)}
.btn-modern.primary{background:linear-gradient(135deg,#1d4ed8,#1e40af);color:#fff;border-color:transparent}
.btn-modern.primary:hover{box-shadow:0 6px 18px rgba(59,130,246,.35);color:#fff}
.toolbar-modern{background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);border-radius:16px;padding:18px;border:1px solid #e2e8f0;display:flex;flex-wrap:wrap;gap:12px;align-items:center}
.tool-group{display:flex;background:#fff;border-radius:12px;padding:6px;box-shadow:0 4px 12px rgba(0,0,0,.05);border:1px solid #e2e8f0;gap:2px}
.tool-btn{min-width:44px;height:42px;padding:0 12px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:transparent;border:none;cursor:pointer;transition:all .3s ease;color:#64748b;font-size:14px;font-weight:600}
.tool-btn:hover{background:#f0f9ff;color:#1d4ed8;transform:translateY(-2px)}
.tool-btn.active{background:linear-gradient(135deg,#1d4ed8,#1e40af);color:#fff;box-shadow:0 4px 12px rgba(59,130,246,.3)}
.tool-btn img{width:22px;height:22px;object-fit:contain}
.legend{display:flex;flex-wrap:wrap;gap:8px;padding:14px 18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;margin-bottom:16px}
.legend-title{font-size:13px;font-weight:700;color:#64748b;width:100%;margin-bottom:2px}
.legend-item{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:20px;background:#fff;border:1px solid #e2e8f0;font-size:13px;color:#334155;cursor:pointer;transition:all .2s ease}
.legend-item:hover{border-color:#1d4ed8;transform:translateY(-1px)}
.legend-item .dot{width:12px;height:12px;border-radius:50%;flex:0 0 12px}
.legend-item.muted{opacity:.32}
.tree-container{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);overflow:hidden;border:1px solid #e2e8f0;margin:20px 0}
.tree-title-bar{background:linear-gradient(135deg,#f8fafc,#f1f5f9);padding:16px 22px;border-bottom:1px solid #e2e8f0;font-size:18px;font-weight:600;color:#1e293b;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
#tree_container{min-height:640px;background:#f8fafc;border-top:1px solid #e2e8f0;overflow:auto;padding:14px;position:relative}
<?php /* 与 JS 里的 TIP_FONT_PX 保持一致（真正生效的是 decorate() 写的内联字号，
   这里只是兜底，两个数别写成不一样的）。 */ ?>
#tree_container text{font-size:12px}
#tree_container .tip-link{cursor:pointer}
#tree_container .tip-link:hover{text-decoration:underline}
.stat-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px}
.stat-chip{background:#f0f9ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:6px 13px;border-radius:20px;font-size:13px;font-weight:600}
.stat-chip.muted{background:#f1f5f9;border-color:#e2e8f0;color:#475569;font-weight:500}
.info-panel{margin-top:16px;padding:16px 20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;font-size:15px;color:#334155;display:none}
.info-panel h4{margin:0 0 8px;font-size:16px;color:#0f172a}
.info-panel .muted{color:#64748b;font-size:13px}
.info-panel a{color:#2563eb;font-weight:600;text-decoration:none}
.info-panel a:hover{text-decoration:underline}
.breadcrumb-taxon{font-size:13px;color:#64748b;margin-bottom:6px}
.hint{font-size:16px;color:#64748b;margin-top:8px;line-height:1.6}
.footer-modern{background:linear-gradient(135deg,#f8fafc,#f1f5f9);border-radius:16px;padding:22px 26px;margin-top:30px;border:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px}
/* overflow-wrap：页脚里那串 phylotree/tools/rebuild_tree_data.py 是不可断行的
   长路径，窄屏（360px）单它就有 325px，会把整行顶出栏外。放得下时行为不变。 */
.footer-text{font-size:15px;color:#64748b;overflow-wrap:anywhere}
.footer-link{color:#1d4ed8;text-decoration:none;font-weight:600;display:flex;align-items:center;gap:8px;padding:8px 16px;background:#fff;border-radius:10px;border:1px solid #e2e8f0;transition:all .3s ease}
.footer-link:hover{background:#1d4ed8;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(59,130,246,.3)}
@media (max-width:768px){.tree-hero{padding:30px 20px}.tree-hero h1{font-size:24px}.control-panel{padding:22px 18px}.search-box input{width:100%}.modern-select{width:100%}}
</style>
</head>
<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<!-- Navigation (unchanged) -->
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
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li>
<li><a href="/microsynteny.php">Microsynteny Analysis</a></li>
<li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li>
<li><a href="/mitdata.php">Mitogenomic Data</a></li>
                </ul>
            </li>
            <li><a href="#">Transcriptome</a>
                <ul>
                    <li><a href="/cytoscape/trans_data.php">Transcriptomic Data</a></li>
                    <li><a href="/trans_assembly.php">Transcriptome Assembly</a></li>
                    <li><a href="/cytoscape/network.php">Network Analysis</a></li>
                    <li><a href="/cytoscape/network_expression.php">Dynamic Expression View</a></li>
                </ul>
            </li>
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
                <ul>
                    <li><a href="/phenotype.php?class=all">All</a></li>
                    <li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li>
                    <li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li>
                    <li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li>
                    <li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li>
                    <li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li>
                </ul>
            </li>
            <li><a href="#">Tools</a>
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
<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px"> <b>Cnidaria Phylogeny</b></legend>
<p class="paleo-intro">
Interactive visualization of evolutionary relationships among Cnidaria species. Branches and tips are
colour-coded by taxonomic rank; use the controls below to search, filter, collapse clades, isolate
subtrees at any taxonomic level, and jump to the corresponding species entry.
</p>

<div class="control-panel">
  <div class="control-header">
    <div class="control-title">Tree Visualization Controls</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn-modern" id="btnReset">Reset view</button>
      <button class="btn-modern" id="btnCollapseAll">Collapse all</button>
      <button class="btn-modern" id="btnExpandAll">Expand all</button>
    </div>
  </div>

  <?php /* 第 1 行：分类层级 / 着色 / 搜索 */ ?>
  <div class="ctl-row">
    <span class="ctl-label">Taxonomic level:</span>
    <select id="taxonSelect" class="modern-select"><option value="">— Whole phylum Cnidaria —</option></select>
    <span class="ctl-label">Colour by:</span>
    <select id="colorBy" class="modern-select">
      <option value="class">Class</option>
      <option value="order">Order</option>
      <option value="family">Family</option>
      <option value="genus">Genus</option>
      <option value="none">No colour</option>
    </select>
    <label class="ctl-label" style="font-weight:500;display:flex;align-items:center;gap:6px">
      <input type="checkbox" id="showSupport" checked> Support values
    </label>
  </div>

  <?php /* 第 2 行：搜索 */ ?>
  <div class="ctl-row">
    <span class="ctl-label">Search:</span>
    <div class="search-box">
      <span class="fa-magnifier">&#128269;</span>
      <input type="text" id="treeNode" placeholder="Species / genus / family / order … e.g. Nematostella" autocomplete="off">
    </div>
    <button class="btn-modern" id="btnPrev">&#8593; Prev</button>
    <button class="btn-modern" id="btnNext">&#8595; Next</button>
    <span class="ctl-label" id="matchCount" style="font-weight:700;color:#2563eb"></span>
    <button class="btn-modern" id="btnClearSearch">Clear</button>
  </div>

  <div class="toolbar-modern" role="toolbar" id="toolbar">
    <div class="tool-group">
      <button type="button" class="tool-btn" data-direction="vertical" data-amount="1" title="Expand vertical spacing"><img src="./images/1.jpg" alt=""></button>
      <button type="button" class="tool-btn" data-direction="vertical" data-amount="-1" title="Compress vertical spacing"><img src="./images/2.jpg" alt=""></button>
      <button type="button" class="tool-btn" data-direction="horizontal" data-amount="1" title="Expand horizontal spacing"><img src="./images/3.jpg" alt=""></button>
      <button type="button" class="tool-btn" data-direction="horizontal" data-amount="-1" title="Compress horizontal spacing"><img src="./images/4.jpg" alt=""></button>
    </div>
    <div class="tool-group">
      <button type="button" class="tool-btn" id="sort_ascending" title="Sort deepest clades to bottom"><img src="./images/5.jpg" alt=""></button>
      <button type="button" class="tool-btn" id="sort_descending" title="Sort deepest clades to top"><img src="./images/6.jpg" alt=""></button>
      <button type="button" class="tool-btn" id="sort_original" title="Restore original order"><img src="./images/7.jpg" alt=""></button>
    </div>
    <div class="tool-group">
      <button class="tool-btn active phylotree-layout-mode" data-mode="linear" title="Linear layout">Linear</button>
      <button class="tool-btn phylotree-layout-mode" data-mode="radial" title="Radial layout">Radial</button>
    </div>
    <div class="tool-group">
      <button class="tool-btn active phylotree-align-toggler" data-align="left" title="Align tips left"><img src="./images/9.jpg" alt=""></button>
      <button class="tool-btn phylotree-align-toggler" data-align="right" title="Align tips right"><img src="./images/10.jpg" alt=""></button>
    </div>
    <div class="tool-group">
      <button class="tool-btn" id="save_nwk" title="Download current subtree (Newick)">NWK</button>
      <button class="tool-btn" id="save_svg" title="Download SVG">SVG</button>
      <button class="tool-btn" id="save_png" title="Download PNG">PNG</button>
    </div>
  </div>
  <div class="hint">
    Tips: <b>click a tip</b> to open its species entry &middot; <b>click an internal node</b> to collapse / expand that clade
    &middot; <b>click a legend chip</b> to hide that group &middot; branch labels are SH-aLRT / UFBoot support (%).
  </div>
</div>

<div class="tree-container">
  <div class="tree-title-bar">
    <span id="treeTitle">Phylogenetic Tree — Cnidaria (all species)</span>
    <span id="statBar" class="stat-bar" style="margin:0"></span>
  </div>
  <div id="tree_container" class="tree-widget"></div>
</div>

<div id="monoNote" style="display:none;margin:0 0 14px;padding:12px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;color:#92400e;font-size:16px;line-height:1.6"></div>
<div class="legend" id="legend"><div class="legend-title">Taxonomic legend</div></div>
<div class="info-panel" id="infoPanel"></div>

<?php if ($SHOW_ROOT_NOTE): ?>
<div class="note-box" id="rootNote">
  <b>About the root of this tree.</b>
  The species tree was inferred from single-copy orthologues and is rooted on
  <b>non-cnidarian outgroups</b>: five sequenced genomes &mdash; the comb jelly
  <i>Bolinopsis microptera</i> (Ctenophora) and the sponges <i>Corticium candelabrum</i>,
  <i>Oscarella lobularis</i>, <i>Halichondria panicea</i> and <i>Sycon ciliatum</i>
  (Porifera) &mdash; sit on the branch opposite the 148 cnidarian species, so the position of the
  root follows from the data rather than being placed by hand. Re-rooting elsewhere changes only the
  direction in which the branches are read, not the topology; pick a taxon in <b>Taxonomic level</b>
  above and the tree tells you whether it is recovered as monophyletic under the current root. Branch labels are SH-aLRT / UFBoot support
  values (%); branch lengths (substitutions per site) are carried in the downloadable Newick files.
</div>
<?php endif; ?>

<div class="note-box" id="taxNote">
  <b>Taxonomy provenance.</b>
  Class / order / family / genus and the NCBI, WoRMS and GBIF identifiers shown here are read from the
  same curated taxonomy used by the rest of CnidoSite (WoRMS &mdash; Ahyong et al.,
  <i>WoRMS Editorial Board, 2025</i>; GBIF &mdash; <a href="https://www.gbif.org/citation-guidelines"
  target="_blank" rel="noopener noreferrer">GBIF citation guidelines</a>; NCBI Taxonomy), so the ranks shown on the tree match
  those on the species pages. Where a rank is not monophyletic on this topology the tree reports it
  explicitly rather than silently re-labelling the clade.
</div>

<?php if (!empty($SUBTREES)): ?>
<div class="control-panel" id="subtreePanel">
  <div class="control-header">
    <div class="control-title">Trees at individual taxonomic levels</div>
  </div>
  <p style="margin:0 0 14px;color:#475569;font-size:16px;line-height:1.7">
    Because CnidoSite covers the whole phylum, a single large tree is not enough for detailed
    exploration. The Newick files below are pruned views of the same 153-tip topology, one per
    class, order and family, and can be downloaded individually or explored in the viewer above
    (choosing a level in <i>Taxonomic level</i> produces the same subtree interactively). They cover
    the 148 cnidarian species; the 5 non-cnidarian outgroups belong to no rank subtree and appear
    only in the full tree.
    Taxa that are <b>not monophyletic</b> on this topology are listed in the notes above and are not
    offered as separate trees.
  </p>
  <?php foreach (array('class','order','family') as $lvl): if (empty($SUBTREES[$lvl])) continue; ?>
    <div class="subtree-group">
      <div class="subtree-group-title"><?php echo RANK_LABEL_PHP($lvl); ?>-level trees
        <span class="subtree-count"><?php echo count($SUBTREES[$lvl]); ?></span></div>
      <div class="subtree-chips">
        <?php foreach ($SUBTREES[$lvl] as $t): ?>
          <span class="subtree-chip">
            <a href="<?php echo htmlspecialchars($TREES_DIR . '/' . $t['file'], ENT_QUOTES); ?>"
               download target="_blank" rel="noopener" title="Download Newick (<?php echo (int)$t['ntips']; ?> tips)">
              <?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>
              <em><?php echo (int)$t['ntips']; ?></em>
            </a>
            <button type="button" class="subtree-view"
                    data-level="<?php echo htmlspecialchars($lvl, ENT_QUOTES); ?>"
                    data-taxon="<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>"
                    title="Show this subtree in the viewer">view</button>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="footer-modern">
  <div class="footer-text">
    Visualized using phylotree.js (Shank et al., <i>Bioinformatics</i> 2018) &middot;
    taxonomy from WoRMS, NCBI Taxonomy and GBIF &middot;
    tree data regenerated by <code>phylotree/tools/rebuild_tree_data.py</code>
  </div>
  <a href="https://github.com/veg/phylotree.js" target="_blank" class="footer-link" rel="noopener noreferrer"><span>&#128218;</span> View Documentation</a>
</div>

<div style="display:none" id="nwk_raw"></div>
</div>
</div>
</div>
</div>
<?php /* 页脚挪到文件末尾（见 </html> 前）：历史上它里面那行 mapmyvisitors 的 <script src>
        是同步外链，会挡住 HTML 解析，也会挡住 DOMContentLoaded。那个组件 2026-09-28 起
        改成 load 后注入、不再挡任何东西，页脚仍保持在最末。 */ ?>

<script>
<?php /* =======================================================================
 * 配置（由 PHP 注入）
 * ======================================================================= */ ?>
var SPECIES_URL = "<?php echo $SPECIES_URL; ?>";
var NEWICK_URL  = "<?php echo $NEWICK_URL; ?>";
var TAX_URL     = "<?php echo $TAX_URL; ?>";
var RANKS = ["class","order","family","genus"];
var RANK_LABEL = {phylum:"Phylum", class:"Class", order:"Order", family:"Family", genus:"Genus"};
var SHOW_MONO_NOTE = <?php echo $SHOW_MONOPHYLY_NOTE ? 'true':'false'; ?>;
var SHOW_ROOT_NOTE = <?php echo $SHOW_ROOT_NOTE ? 'true':'false'; ?>;

<?php /* 树上物种名标签的字号与颜色：全局唯一的一份定义，decorate() 每次渲染后按它
   覆盖。改这里就能整体改树上物种名的外观，不会出现「子树和全树不一样」。 */ ?>
var TIP_FONT_PX = 12;
var TIP_COLOR   = '#0f172a';

<?php /* 物种条目页使用 abbr1 短代码；taxonomy.json 中已带该字段。
   找不到 abbr1 时退回到 browse.php 的类群列表，避免产生死链。 */ ?>
function speciesUrl(tip){
  var t = state.tax[tip];
  if(t && t.abbr1){ return SPECIES_URL + '?species=' + encodeURIComponent(t.abbr1); }
  if(t && t.display){ return '/search.php?species=' + encodeURIComponent(t.display); }
  return '/browse.php?class=all';
}

<?php /* 非刺胞动物外群（外群用于定根，站点并不收录它们）。它们没有 abbr1，
   若照常走 speciesUrl 会落到 /search.php 一个必然搜不到的结果页上，所以单独
   判断：不自动打开新窗口，信息面板里改指 NCBI Taxonomy 的名称检索。 */ ?>
function isOutgroup(tip){
  var t = state.tax[tip];
  return !!(t && t.outgroup);
}
function outgroupUrl(tip){
  var t = state.tax[tip] || {};
  return 'https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?name=' +
    encodeURIComponent(t.display || tip);
}

<?php /* =======================================================================
 * Newick 解析 / 序列化
 * ======================================================================= */ ?>
var _uid = 0;
function parseNewick(s){
  s = String(s).replace(/\[[^\]]*\]/g,'').trim().replace(/;\s*$/,'');
  var pos = 0;
  function mk(){ return {id:++_uid,name:'',len:'',children:[],parent:null,collapsed:false}; }
  function node(){
    var n = mk();
    if(s.charAt(pos) === '('){
      pos++;
      for(;;){
        var c = node(); c.parent = n; n.children.push(c);
        if(pos >= s.length) break;
        var ch = s.charAt(pos);
        if(ch === ','){ pos++; continue; }
        if(ch === ')'){ pos++; break; }
        pos++;
      }
    }
    var st = pos;
    while(pos < s.length && "(),:;".indexOf(s.charAt(pos)) < 0) pos++;
    n.name = s.slice(st,pos).trim().replace(/^["']+|["']+$/g,'');
    if(s.charAt(pos) === ':'){
      pos++; st = pos;
      while(pos < s.length && "(),;".indexOf(s.charAt(pos)) < 0) pos++;
      n.len = s.slice(st,pos).trim();
    }
    return n;
  }
  return node();
}
function safeName(s){ return String(s==null?'':s).replace(/[\s,;:()\[\]'"]/g,'_'); }
function toNewick(n){
  if(n.collapsed && n.children.length) return safeName(collapseToken(n));
  if(n.children.length) return '(' + n.children.map(toNewick).join(',') + ')' + safeName(n.name||'');
  return safeName(n.name||'') + (n.len ? ':' + n.len : '');
}
function tipsOf(n,out){
  out = out || [];
  if(n.collapsed && n.children.length){ out.push(n); return out; }
  if(n.children.length){ for(var i=0;i<n.children.length;i++) tipsOf(n.children[i],out); }
  else out.push(n);
  return out;
}
function realTipsOf(n,out){
  out = out || [];
  if(n.children.length){ for(var i=0;i<n.children.length;i++) realTipsOf(n.children[i],out); }
  else out.push(n.name);
  return out;
}
function tipNamesOf(n){
  return tipsOf(n).map(function(t){
    if(t.collapsed && t.children.length) return collapseToken(t);
    return t.name;
  });
}
function cladeKey(names){ return names.slice().sort().join('\u0001'); }

<?php /* =======================================================================
 * 全局状态
 * ======================================================================= */ ?>
var state = {
  root:null, fullRoot:null, viewRoot:null, tree:null,
  tax:{}, palette:{}, hidden:{},
  colorBy:'class', showSupport:true,
  match:[], matchIdx:-1, focus:null,
  collapsedMap:{},   // token -> node
  collapsedLabel:{}  // token -> 显示名
};

<?php /* ---- 折叠节点的新名字（newick 安全）与其展示名 ---- */ ?>
function collapseToken(n){ return 'COLLAPSED_' + n.id; }
function consensusTaxon(n, rank){
  var names = realTipsOf(n), seen = {}, best = null, bc = 0, tot = 0;
  names.forEach(function(nm){
    var t = state.tax[nm]; if(!t) return;
    var v = t[rank]; if(!v || v === 'Unassigned') return;
    seen[v] = (seen[v]||0)+1; tot++;
    if(seen[v] > bc){ bc = seen[v]; best = v; }
  });
  if(!tot) return null;
  return (bc === tot) ? best : (best + ' (' + bc + '/' + tot + ')');
}
function collapseLabel(n){
  var c = null, rank = null;
  ['family','order','class'].forEach(function(r){
    if(c === null){ var v = consensusTaxon(n, r); if(v){ c = v; rank = r; } }
  });
  return '\u25B8 ' + (c || 'Clade') + ' \u00b7 ' + realTipsOf(n).length + ' tips';
}

<?php /* =======================================================================
 * 配色
 * ======================================================================= */ ?>
function hslColor(i){
  var h = (i * 137.508) % 360;
  var s = [66,60,72,54,64][i%5];
  var l = [42,55,34,60,48][i%5];
  return 'hsl(' + h.toFixed(1) + ',' + s + '%,' + l + '%)';
}
function buildPalettes(){
  RANKS.forEach(function(r){
    var vals = {}, list = [];
    for(var k in state.tax){
      var v = state.tax[k][r];
      if(v && !vals[v]){ vals[v] = 1; list.push(v); }
    }
    list.sort();
    state.palette[r] = {};
    list.forEach(function(v,i){ state.palette[r][v] = hslColor(i); });
  });
}
function colorOfTaxon(v){
  var r = state.colorBy;
  if(r === 'none' || !v) return '#94a3b8';
  var p = state.palette[r] || {};
  if(!p[v]) p[v] = hslColor(Object.keys(p).length);
  return p[v];
}

<?php /* =======================================================================
 * 索引：每个节点 -> 类群 / tip 集合
 * ======================================================================= */ ?>
function indexTree(){
  state.cladeMap = {};
  (function walk(n){
    n._tips  = tipNamesOf(n);              // 渲染出来的 tip（含折叠标记）-> 用于索引
    n._rtips = realTipsOf(n);              // 真实后代 -> 用于着色 / 搜索
    n._key   = cladeKey(n._tips);
    state.cladeMap[n._key] = n;
    var cons = {};
    RANKS.forEach(function(r){
      var seen = {}, best = null, bc = 0, tot = 0;
      n._rtips.forEach(function(nm){
        var t = state.tax[nm]; if(!t) return;
        var v = t[r]; if(!v || v === 'Unassigned') return;
        seen[v] = (seen[v]||0)+1; tot++;
        if(seen[v] > bc){ bc = seen[v]; best = v; }
      });
      cons[r] = (tot && bc === tot) ? best : null;
    });
    n._cons = cons;
    n.children.forEach(walk);
  })(state.viewRoot);
}

<?php /* =======================================================================
 * 渲染
 * ======================================================================= */ ?>
function render(){
  var nwk = toNewick(state.viewRoot) + ';';
  indexTree();
  try{ d3.select('#tree_container').html(''); }catch(e){ $('#tree_container').empty(); }

  state.tree = new phylotree.phylotree(nwk);
  state.tree.render({
    container: '#tree_container',
    'draw-size-bubbles': false,
    'left-right-spacing': 'fixed-step',
    'brush': false,
    'zoom': true,
    <?php /* 布局用 fixed-step（cladogram）：横向位置是节点深度，不是枝长，所以库按
       默认画出来的那条 scale bar 刻度其实是「第几层节点」（旧树 15、新树 22），
       却带着 2.00 / 4.00 这种小数标签，看着像 substitutions per site —— 会误导。
       真正的枝长在下载的 Newick 里。 */ ?>
    'show-scale': false,
    'internal-names': state.showSupport,
    'node-styler': nodeStyler,
    'edge-styler': edgeStyler
  });
  <?php /* phylotree.js 把 SVG 建在脱离文档的节点里（内部用 d3.create("svg")），
     render() 本身不会把它插进 container —— 库自己的 demo 也是在 render() 之后
     显式调用 display.show() 再塞回容器。缺了这一步 #tree_container 永远是空的，
     树画不出来，后面依赖 '#tree_container svg' 的导出/标注也全部落空。 */ ?>
  $(state.tree.display.container).empty();
  $(state.tree.display.container).html(state.tree.display.show());
  decorate();
  updateLegend();
  updateStats();
}

<?php /* 从 phylotree 传入的对象里取名字 / 子节点 */ ?>
function nmOf(d){
  if(!d) return null;
  if(d.name !== undefined && d.name !== null && d.name !== '') return d.name;
  if(d.data && d.data.name !== undefined) return d.data.name;
  return null;
}
function kidsOf(d){
  if(!d) return null;
  if(d.children && d.children.length) return d.children;
  if(d.data && d.data.children && d.data.children.length) return d.data.children;
  return null;
}
function collectTips(d,out){
  out = out || [];
  if(!d) return out;
  var k = kidsOf(d);
  if(k && k.length){ for(var i=0;i<k.length;i++) collectTips(k[i],out); }
  else { var nm = nmOf(d); if(nm) out.push(nm); }
  return out;
}
function nodeByData(d){
  var key = cladeKey(collectTips(d));
  return state.cladeMap[key] || null;
}
function isHiddenNode(n){
  var r = state.colorBy;
  if(r === 'none') return false;
  var v = n._cons ? n._cons[r] : null;
  if(!v) return false;
  return !!state.hidden[v];
}

<?php /* 把节点配色画到「节点标记（圆点）」上，同时把标签文字钉成深色。
   ---------------------------------------------------------------------
   phylotree.js 的 node-styler 拿到的 element 是整个节点的 <g>，不是圆点
   （库里 drawNode 末尾是 this.node_styler(container, node)，container 就是那个
   <g>）。而 SVG 的 fill 是继承属性，所以原来写的
       element.style('fill', color)
   本想染色的是圆点，实际却有两处不对：
     1. phylotree.css 里 `.node circle{fill:steelblue}` 直接命中 circle，
        优先级高于「从父级继承」，圆点一直是钢蓝色，类群配色根本没生效；
     2. 颜色顺着继承落到了 <text> 上，标签文字被涂成调色板里的浅色
        （hsl(h, 54-72%, 34-60%)），在白色底上几乎看不见 ——
        这就是「进化树上看不到物种名」的原因。
   这里显式给 circle 上色，并把 text 的 fill 固定为深色：无论类群配色多浅，
   名字都要读得出来。 */ ?>
function paintNode(element, fill, opts){
  opts = opts || {};
  var circles = element.selectAll('circle');
  circles.style('fill', fill)
         .style('stroke', opts.stroke || '#ffffff')
         .style('stroke-width', opts.strokeW || '1px');
  if(opts.r){ circles.attr('r', opts.r); }
  element.selectAll('text').style('fill', '#0f172a');
}

<?php /* 叶节点默认是光秃秃的：phylotree.js 只在 options["draw-size-bubbles"] 打开时
   才给叶节点画圆（那段代码是 if (this.options["draw-size-bubbles"]) {...}），
   而那个圆表达的是「数据量大小」，不是分类。结果就是类群配色无处可落 ——
   右上的 Taxonomic legend 列了一排名色圆点，树上却一个都看不到。
   这里给还没有圆点的节点补一个固定大小的标记，让配色真正画在节点上，
   legend 和树才对得上。（内部节点库已经画了点，不再重复添加。） */ ?>
function ensureMarker(element){
  if(element.selectAll('circle').size()) return;
  element.insert('circle', ':first-child').attr('r', 3.5);
}

function nodeStyler(element, data){
  try{
    var n = nodeByData(data);
    var nm = nmOf(data);
    var isCollapsed = n && n.collapsed && n.children.length;
    var collapsedTok = /^COLLAPSED_/.test(nm||'');
    var color = '#94a3b8';

    if(isCollapsed || collapsedTok){
      var lab = isCollapsed ? collapseLabel(n) : (state.collapsedLabel[nm] || nm);
      var v = isCollapsed ? (n._cons[state.colorBy] || null) : null;
      color = v ? colorOfTaxon(v) : '#64748b';
      state.collapsedLabel[collapseToken(n || {id:0})] = lab;
      paintNode(element, color, {stroke:'#334155', strokeW:'1.5px', r:7});
    } else {
      var t = state.tax[nm];
      if(t){
        color = colorOfTaxon(t[state.colorBy]);
        ensureMarker(element);
        paintNode(element, color);
        if(state.match.indexOf(nm) >= 0){
          paintNode(element, color, {stroke:'#dc2626', strokeW:'3.5px', r:8});
        }
        if(state.focus && state.focusRank === state.colorBy && t[state.colorBy] !== state.focus){
          element.style('opacity', 0.14);
        }
      } else {
        paintNode(element, '#cbd5e1');
        element.style('opacity', 0.5);
      }
    }
    if(n && isHiddenNode(n)) element.style('opacity', 0.12);

    element.style('cursor','pointer');
    element.on('click', function(){ onNodeClick(n, nm); });
    if(nm && state.tax[nm]) element.attr('class', function(){ return (this.getAttribute('class')||'') + ' tip-link'; });
  }catch(e){}
}
function edgeStyler(element, data){
  try{
    var tgt = (data && data.target) ? data.target : data;
    var n = nodeByData(tgt);
    var color = '#cbd5e1', w = '1.6px';
    if(n){
      var v = n._cons ? n._cons[state.colorBy] : null;
      if(v) color = colorOfTaxon(v);
      if(n._rtips && n._rtips.some(function(x){ return state.match.indexOf(x) >= 0; })
         && n._rtips.length <= Math.max(1, state.match.length * 3)){
        color = '#dc2626'; w = '3px';
      }
      if(isHiddenNode(n)) element.style('opacity', 0.12);
    }
    element.style('stroke', color).style('stroke-width', w);
  }catch(e){}
}

<?php /* 渲染后：把下划线名字换成可读名，并给折叠节点换显示名 */ ?>
function decorate(){
  d3.select('#tree_container').selectAll('text').each(function(){
    var sel = d3.select(this), txt = (sel.text()||'').trim();
    if(!txt) return;
    <?php /* 字号先统一钉死，管它是物种名还是支持率/折叠标签：不管当前显示的是完整
       大树还是某个分类层级的子树，也不管「Colour by」选了哪一层，字号都一致。
       phylotree.js 自己会给每个标签写 inline font-size，值取自
       min(font_size, scales[0])（src/render/nodes.js:53）——在当前配置下它恰好
       恒定，但一旦把 left-right-spacing 改成 fit-to-size，宽树就会被缩放，
       树越大字越小。内联样式优先于 CSS，所以这里覆盖成常量最稳。 */ ?>
    sel.style('font-size', TIP_FONT_PX + 'px');
    if(/^COLLAPSED_/.test(txt)){
      sel.text(state.collapsedLabel[txt] || txt.replace(/^COLLAPSED_/,''));
      sel.style('font-style','italic').style('font-weight','600');
    } else if(state.tax[txt]){
      <?php /* 物种名另外钉死颜色 —— 它只会被库/主题改成别的颜色，但灰一档（opacity）
         也是「看着颜色不一样」，那种情况在 nodeStyler 里按聚焦层级判断。 */ ?>
      sel.text(state.tax[txt].display).style('fill', TIP_COLOR);
    }
  });
}

<?php /* =======================================================================
 * 交互：点击节点
 * ======================================================================= */ ?>
function onNodeClick(n, nm){
  if(!n){ return; }
  if(n.collapsed && n.children.length){ n.collapsed = false; render(); return; }
  if(n.children.length){ n.collapsed = true; render(); return; }
  // tip
  var t = state.tax[nm];
  showInfo(nm, t);
  if(t && !isOutgroup(nm)) window.open(speciesUrl(nm), '_blank');
}
function showInfo(nm, t){
  var p = $('#infoPanel');
  if(!t){ p.hide(); return; }
  var ext = [];
  if(t.worms)    ext.push('<a href="' + t.worms + '" target="_blank" rel="noopener noreferrer">WoRMS</a>');
  if(t.ncbi_url) ext.push('<a href="' + t.ncbi_url + '" target="_blank" rel="noopener noreferrer">NCBI Taxonomy</a>');
  if(t.gbif_url) ext.push('<a href="' + t.gbif_url + '" target="_blank" rel="noopener noreferrer">GBIF</a>');
  var mainLink;
  if(t.outgroup){
    mainLink = '<a href="' + outgroupUrl(nm) + '" target="_blank" rel="noopener noreferrer">Look up in NCBI Taxonomy &rarr;</a>' +
      '<div class="breadcrumb-taxon" style="margin-top:6px">' +
      'Included as a non-cnidarian outgroup for rooting; not a CnidoSite species entry.</div>';
  } else {
    mainLink = '<a href="' + speciesUrl(nm) + '" target="_blank" rel="noopener noreferrer">' +
      'View species entry in CnidoSite &rarr;</a>' +
      (ext.length ? ' &nbsp;&middot;&nbsp; ' + ext.join(' &nbsp;&middot;&nbsp; ') : '');
  }
  p.html(
    '<h4>' + t.display + '</h4>' +
    '<div class="breadcrumb-taxon">' +
      [t.phylum, t.class, t.order, t.family].filter(Boolean).join(' &rsaquo; ') +
    '</div>' +
    '<div>' + mainLink + '</div>'
  ).slideDown(150);
}

<?php /* =======================================================================
 * 图例
 * ======================================================================= */ ?>
function updateLegend(){
  var box = $('#legend');
  box.find('.legend-item').remove();
  var r = state.colorBy;
  if(r === 'none'){ box.append('<div class="legend-item muted">Colouring disabled</div>'); return; }
  var counts = {};
  tipNamesOf(state.viewRoot).forEach(function(nm){
    var t = state.tax[nm]; if(!t) return;
    var v = t[r]; if(!v) return;
    counts[v] = (counts[v]||0)+1;
  });
  var keys = Object.keys(counts).sort(function(a,b){ return counts[b]-counts[a]; });
  <?php /* 外群的 class/order/family 都留空，上面按值计数会把它们漏掉，于是图上会出现
     几个灰色 tip 却没人在图例里解释。单独补一条。 */ ?>
  var nOut = 0;
  tipNamesOf(state.viewRoot).forEach(function(nm){ if(isOutgroup(nm)) nOut++; });
  if(nOut){
    box.append('<div class="legend-item muted">' +
      '<span class="dot" style="background:#94a3b8"></span>' +
      'non-cnidarian outgroup <b style="color:#64748b">(' + nOut + ')</b></div>');
  }
  if(keys.length > 60){
    box.append('<div class="legend-item muted">Showing top 60 of ' + keys.length + ' ' + r + 'es</div>');
    keys = keys.slice(0,60);
  }
  keys.forEach(function(v){
    var chip = $('<div class="legend-item' + (state.hidden[v] ? ' muted' : '') + '">' +
      '<span class="dot" style="background:' + colorOfTaxon(v) + '"></span>' +
      v + ' <b style="color:#64748b">(' + counts[v] + ')</b></div>');
    chip.on('click', function(){
      state.hidden[v] = !state.hidden[v];
      updateLegend(); render();
    });
    box.append(chip);
  });
}

<?php /* =======================================================================
 * 统计条
 * ======================================================================= */ ?>
function updateStats(){
  var names = tipNamesOf(state.viewRoot);
  var cls = {}, ord = {}, fam = {};
  var nSpecies = 0, nOut = 0;
  names.forEach(function(nm){
    var t = state.tax[nm]; if(!t) return;
    <?php /* 外群不是 CnidoSite 收录的物种，不能计进 "N species"（全树会读成 153，
       与站内其它页面的 148 对不上）；它们单独报一个数。 */ ?>
    if(t.outgroup){ nOut++; return; }
    nSpecies++;
    if(t.class) cls[t.class]=1; if(t.order) ord[t.order]=1; if(t.family) fam[t.family]=1;
  });
  $('#statBar').html(
    '<span class="stat-chip">' + nSpecies + ' species</span>' +
    '<span class="stat-chip">' + Object.keys(cls).length + ' classes</span>' +
    '<span class="stat-chip">' + Object.keys(ord).length + ' orders</span>' +
    '<span class="stat-chip">' + Object.keys(fam).length + ' families</span>' +
    (nOut ? '<span class="stat-chip muted">+ ' + nOut + ' outgroups</span>' : '')
  );
}

<?php /* =======================================================================
 * 分类层级选择（提供不同层级的子树）
 * ======================================================================= */ ?>
function buildTaxonSelect(){
  var sel = $('#taxonSelect');
  sel.find('optgroup').remove();
  RANKS.forEach(function(r, ri){
    var vals = {};
    <?php /* 外群跳过：它们带着 Porifera / Ctenophora 的属名（Bolinopsis、Sycon……），
       混进来只会让这个选择器多出 5 个点了会弹「只有 1 个物种」的选项。它选的是
       刺胞动物的分类层级，外群不属于这里。 */ ?>
    for(var k in state.tax){
      if(state.tax[k].outgroup) continue;
      var v = state.tax[k][r]; if(v) vals[v] = (vals[v]||0)+1;
    }
    var keys = Object.keys(vals).sort();
    if(!keys.length) return;
    var label = RANK_LABEL[r] || r;
    var g = $('<optgroup label="' + label + '"></optgroup>');
    keys.forEach(function(v){
      g.append($('<option></option>').val(r + ':' + v).text(v + '  (' + vals[v] + ')'));
    });
    sel.append(g);
  });
}
function isolateTaxon(rank, value){
  if(!rank || !value){
    state.viewRoot = state.fullRoot; state.focus = null; state.focusRank = null; state.mono = null;
  } else {
    var wanted = [];
    for(var k in state.tax){ if(state.tax[k][rank] === value) wanted.push(k); }
    if(!wanted.length) return;
    if(wanted.length < 2){ alert(value + ' has only one species in the current tree.'); return; }
    var set = {}; wanted.forEach(function(w){ set[w] = 1; });
    state.mono = { ok: isMonophyletic(wanted), name: value, n: wanted.length };
    var pruned = pruneTo(state.fullRoot, set);
    if(!pruned){ alert('Cannot build subtree for ' + value); return; }
    state.viewRoot = pruned;
    state.focus = value;
    <?php /* 连同「聚焦的是哪一层」一起记下。state.focus 存的是值（如 'Scyphozoa'），
       而下面 nodeStyler 要拿它跟 t[state.colorBy] 比 —— colorBy 一旦被换成别的
       层级（order/family），任何物种都不可能相等，整棵树会被压到 14% 不透明度，
       看着就像字体颜色变了。所以只有在 colorBy 还是同一个层级时才淡出。 */ ?>
    state.focusRank = rank;
  }
  state.match = []; state.matchIdx = -1; $('#matchCount').text('');
  updateTitle(); renderMonoNote(); render();
}
<?php /* 剪枝：保留 wanted 中的 tip，压缩单子链，返回新树（不改原树） */ ?>
function pruneTo(root, set){
  function rec(n){
    if(n.collapsed && n.children.length){
      var tk = collapseToken(n);
      return set[tk] ? {id:++_uid, name:tk, len:n.len, children:[], collapsed:false, parent:null} : null;
    }
    if(!n.children.length){
      return set[n.name] ? {id:++_uid, name:n.name, len:n.len, children:[], collapsed:false, parent:null} : null;
    }
    var kids = [];
    n.children.forEach(function(c){ var r = rec(c); if(r) kids.push(r); });
    if(!kids.length) return null;
    if(kids.length === 1) return kids[0];
    var m = {id:++_uid, name:n.name, len:n.len, children:kids, collapsed:false, parent:null};
    kids.forEach(function(k){ k.parent = m; });
    return m;
  }
  var r = rec(root);
  if(r && !r.children.length) return r;
  return r;
}
<?php /* 该类群在当前拓扑中是否单系 */ ?>
function isMonophyletic(wanted){
  var set = {}; wanted.forEach(function(w){ set[w] = 1; });
  var found = false;
  (function walk(n){
    if(found) return;
    var ns = tipNamesOf(n);
    if(ns.length === wanted.length && ns.every(function(x){ return set[x]; })){ found = true; return; }
    n.children.forEach(walk);
  })(state.fullRoot);
  return found;
}
function renderMonoNote(){
  if(!SHOW_MONO_NOTE){ $('#monoNote').hide(); return; }
  if(!state.mono || state.mono.ok){ $('#monoNote').hide(); return; }
  $('#monoNote').html('&#9432; <b>' + state.mono.name + '</b> is not recovered as monophyletic in the ' +
    'current topology (' + state.mono.n + ' species). The subtree below is pruned to its members, so it ' +
    'shows the species of this taxon as they fall on the tree rather than a clade.').show();
}
function updateTitle(){
  var v = $('#taxonSelect').val();
  var txt = 'Phylogenetic Tree \u2014 ';
  if(!v) txt += 'Cnidaria (all species)';
  else txt += v.split(':')[1] + ' (' + v.split(':')[0] + '-level subtree)';
  $('#treeTitle').text(txt);
}

<?php /* =======================================================================
 * 搜索
 * ======================================================================= */ ?>
function runSearch(q){
  q = (q||'').trim();
  if(!q){ state.match = []; state.matchIdx = -1; $('#matchCount').text(''); render(); return; }
  var re = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'), 'i');
  var hits = tipNamesOf(state.viewRoot).filter(function(nm){
    var t = state.tax[nm];
    return re.test(nm) || re.test(nm.replace(/_/g,' ')) ||
           (t && (re.test(t.class)||re.test(t.order)||re.test(t.family)||re.test(t.genus)));
  });
  state.match = hits; state.matchIdx = hits.length ? 0 : -1;
  $('#matchCount').text(hits.length ? (hits.length + ' match' + (hits.length>1?'es':'')) : 'no match');
  render();
  if(hits.length) scrollToMatch();
}
function scrollToMatch(){
  var nm = state.match[state.matchIdx]; if(!nm) return;
  var found = null;
  d3.select('#tree_container').selectAll('text').each(function(){
    if(d3.select(this).text() === (state.tax[nm] ? state.tax[nm].display : nm)) found = this;
  });
  if(found && found.scrollIntoView) found.scrollIntoView({block:'center', behavior:'smooth'});
}

<?php /* =======================================================================
 * 导出
 * ======================================================================= */ ?>
function downloadBlob(blob, name){
  var url = URL.createObjectURL(blob), a = document.createElement('a');
  a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
  setTimeout(function(){ URL.revokeObjectURL(url); }, 1500);
}
function exportNewick(){
  var nwk = toNewick(state.viewRoot) + ';';
  var nm = ($('#taxonSelect').val() || 'Cnidaria').replace(/:/g,'_');
  downloadBlob(new Blob([nwk], {type:'text/plain;charset=utf-8'}), 'cnidosite_' + nm + '.nwk');
}
function collectStyles(){
  var out = '';
  try{
    for(var i=0;i<document.styleSheets.length;i++){
      var rules;
      try{ rules = document.styleSheets[i].cssRules; }catch(e){ continue; }
      if(!rules) continue;
      for(var j=0;j<rules.length;j++){
        if(rules[j].selectorText && rules[j].selectorText.indexOf('>') === -1) out += rules[j].cssText + '\n';
      }
    }
  }catch(e){}
  return out;
}
function exportSVG(){
  var svg = d3.select('#tree_container svg').node();
  if(!svg){ alert('Nothing to export'); return; }
  var clone = svg.cloneNode(true);
  var st = document.createElement('style');
  st.setAttribute('type','text/css');
  st.textContent = collectStyles();
  clone.insertBefore(st, clone.firstChild);
  clone.setAttribute('version','1.1');
  clone.setAttribute('xmlns','http://www.w3.org/2000/svg');
  clone.setAttribute('xmlns:xlink','http://www.w3.org/1999/xlink');
  var src = new XMLSerializer().serializeToString(clone);
  var nm = ($('#taxonSelect').val() || 'Cnidaria').replace(/:/g,'_');
  downloadBlob(new Blob(['<?xml version="1.0" standalone="no"?>\n' + src],
    {type:'image/svg+xml;charset=utf-8'}), 'cnidosite_' + nm + '.svg');
}
function exportPNG(){
  var svg = d3.select('#tree_container svg').node();
  if(!svg){ alert('Nothing to export'); return; }
  var w = svg.clientWidth || svg.getBoundingClientRect().width || 1200;
  var h = svg.clientHeight || svg.getBoundingClientRect().height || 900;
  var clone = svg.cloneNode(true);
  var st = document.createElement('style');
  st.setAttribute('type','text/css');
  st.textContent = collectStyles();
  clone.insertBefore(st, clone.firstChild);
  clone.setAttribute('xmlns','http://www.w3.org/2000/svg');
  var src = new XMLSerializer().serializeToString(clone);
  var img = new Image();
  var nm = ($('#taxonSelect').val() || 'Cnidaria').replace(/:/g,'_');
  img.onload = function(){
    var scale = 2;
    var cv = document.createElement('canvas');
    cv.width = w*scale; cv.height = h*scale;
    var ctx = cv.getContext('2d');
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0,0,cv.width,cv.height);
    ctx.drawImage(img,0,0,cv.width,cv.height);
    cv.toBlob(function(b){ downloadBlob(b, 'cnidosite_' + nm + '.png'); });
  };
  img.onerror = function(){ alert('PNG export failed — please use SVG'); };
  img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(src);
}

<?php /* =======================================================================
 * 启动
 * ======================================================================= */ ?>
function ptBoot(){
  $.get(NEWICK_URL, function(nwk){
    $.getJSON(TAX_URL, function(tx){
      state.tax = tx.taxa || tx;
      state.fullRoot = parseNewick(nwk);
      state.viewRoot = state.fullRoot;
      buildPalettes();
      buildTaxonSelect();
      render();
    }).fail(function(){ alert('Cannot load ' + TAX_URL); });
  }, 'text').fail(function(){ alert('Cannot load ' + NEWICK_URL); });

  <?php /* ---- 控件 ---- */ ?>
  $('#colorBy').on('change', function(){
    state.colorBy = $(this).val(); state.hidden = {};
    updateLegend(); render();
  });
  $('#showSupport').on('change', function(){ state.showSupport = $(this).is(':checked'); render(); });
  $('#taxonSelect').on('change', function(){
    var v = $(this).val();
    if(!v){ state.viewRoot = state.fullRoot; state.focus = null; state.focusRank = null; state.match=[]; $('#matchCount').text(''); }
    else isolateTaxon(v.split(':')[0], v.split(':')[1]);
    updateTitle(); if(!v) render();
  });
  <?php /* 子树面板：点击 "view" 直接在看板里隔离该分类阶元 */ ?>
  $('#subtreePanel').on('click', '.subtree-view', function(){
    var lvl = $(this).data('level'), tax = $(this).data('taxon');
    $('#taxonSelect').val(lvl + ':' + tax);
    isolateTaxon(lvl, tax);
    $('html, body').animate({scrollTop: $('#tree_container').offset().top - 120}, 300);
  });

  $('#btnReset').on('click', function(){
    state.viewRoot = state.fullRoot; state.focus = null; state.focusRank = null; state.hidden = {};
    state.match = []; state.matchIdx = -1; state.colorBy = 'class'; state.mono = null; renderMonoNote();
    $('#taxonSelect').val(''); $('#colorBy').val('class'); $('#treeNode').val(''); $('#matchCount').text('');
    walkAll(state.fullRoot, function(n){ n.collapsed = false; });
    updateTitle(); render();
  });
  function walkAll(n, f){ f(n); n.children.forEach(function(c){ walkAll(c,f); }); }
  $('#btnCollapseAll').on('click', function(){
    walkAll(state.viewRoot, function(n){
      if(n.children.length && tipNamesOf(n).length > 1 && n !== state.viewRoot) n.collapsed = true;
    });
    render();
  });
  $('#btnExpandAll').on('click', function(){
    walkAll(state.viewRoot, function(n){ n.collapsed = false; }); render();
  });
  var tmr = null;
  $('#treeNode').on('input', function(){
    clearTimeout(tmr); var q = $(this).val();
    tmr = setTimeout(function(){ runSearch(q); }, 220);
  });
  $('#treeNode').on('keydown', function(e){ if(e.key === 'Enter'){ clearTimeout(tmr); runSearch($(this).val()); } });
  $('#btnNext').on('click', function(){
    if(!state.match.length) return;
    state.matchIdx = (state.matchIdx + 1) % state.match.length; scrollToMatch();
  });
  $('#btnPrev').on('click', function(){
    if(!state.match.length) return;
    state.matchIdx = (state.matchIdx - 1 + state.match.length) % state.match.length; scrollToMatch();
  });
  $('#btnClearSearch').on('click', function(){
    $('#treeNode').val(''); state.match = []; state.matchIdx = -1; $('#matchCount').text(''); render();
  });
  $('#save_nwk').on('click', exportNewick);
  $('#save_svg').on('click', exportSVG);
  $('#save_png').on('click', exportPNG);

  <?php /* ---- 布局按钮（沿用原逻辑） ---- */ ?>
  $('[data-direction]').on('click', function(){
    var which = $(this).data('direction') === 'vertical'
      ? state.tree.display.spacing_x.bind(state.tree.display)
      : state.tree.display.spacing_y.bind(state.tree.display);
    which(which() + (+$(this).data('amount'))).update();
  });
  $('.phylotree-layout-mode').on('click', function(){
    if(state.tree.display.radial() != ($(this).data('mode') === 'radial')){
      $('.phylotree-layout-mode').toggleClass('active');
      state.tree.display.radial(!state.tree.display.radial()).update();
    }
  });
  $('.phylotree-align-toggler').on('click', function(){
    var ba = $(this).data('align');
    if(state.tree.display.options.alignTips != ba){
      state.tree.display.alignTips(ba === 'right');
      $('.phylotree-align-toggler').toggleClass('active');
      state.tree.display.update();
    }
  });
  function sortNodes(asc){
    state.tree.resortChildren(function(a,b){
      return (b.height - a.height || b.value - a.value) * (asc ? 1 : -1);
    });
  }
  $('#sort_ascending').on('click', function(){ sortNodes(true); state.tree.display.update(); });
  $('#sort_descending').on('click', function(){ sortNodes(false); state.tree.display.update(); });
  $('#sort_original').on('click', function(){
    state.tree.resortChildren(function(a,b){ return a.data.original_child_order - b.data.original_child_order; });
  });
}

<?php /* 这一段启动不再包在 $(function(){...}) 里：这个 <script> 就排在 #tree_container 后面，
   走到这里时 DOM 已经建好了；包成 ready 只会反过来去等 DOMContentLoaded。当初这么改是
   因为页脚的 mapmyvisitors 同步外链会把 DOMContentLoaded 一起拖住（把它延迟 3 秒实测：
   DCL 1607 → 3839 ms，树是跟着页脚一起出来的）；那个组件 2026-09-28 起改成 load 后注入，
   这个约束没了，但直接调用仍然更快。万一以后这段脚本被挪到容器之前，
   下面的 getElementById 为假，会自动退回 ready。 */ ?>
if (document.getElementById('tree_container')) { ptBoot(); } else { $(ptBoot); }
</script>
<?php include "../Webpage_components.php"; print $footer; ?>
</body>
</html>

