<?php
// ============================================================================
//  CnidoSite - User Manual (HTML version)
//  Replaces the PDF-embed based manual with an accessible, searchable HTML manual.
//  Screenshots live in ./manual_images/
//  The original PDF remains available as a secondary download.
// ============================================================================

// Path used by the "Download PDF version" button. Change this to match the
// location of the PDF on your server if it differs (e.g. "./user_manual/UserManual.pdf").
$manualPdf = "/UserManual.pdf";   // 站点根目录下的 UserManual.pdf（./export.php 并不存在，原链接是 404）

// 版本号的唯一来源是 includes/release.php（页脚也读它）。页脚的「本文档描述的是哪个版本」
// 不要写死，否则 r1.3 发布后这里就开始撒谎。require_once 与 Webpage_components.php 里的
// 那一行是同一个文件，重复包含不会重复定义函数。
require_once __DIR__ . '/includes/release.php';
$manualRelease = cnido_release_string();

// PDF 的快照日期：让「PDF 可能落后于本页」这句话有据可依，而不是一句空话。
// @ 抑制不存在时的 warning（路径可通过 $manualPdf 改到别处），取不到就不显示日期。
$manualPdfDate = '';
if ($manualPdf !== '' && strpos($manualPdf, '..') === false) {
    $manualPdfTs = @filemtime(__DIR__ . $manualPdf);
    if ($manualPdfTs !== false) { $manualPdfDate = date('Y-m-d', $manualPdfTs); }
}

// 下载区的规模（文件数 / 总大小 / 覆盖物种数）现算，不写死。
// 写死的代价当场就付过了：这一句原写「1,870 files … 229 species」，而 PMULT 上线
// 后实际是 1,873 files / 230 species，页面开始撒谎。数字只有一个来源才不会再错，
// 所以这里调的是 download.php 与 api.php?resource=downloads 共用的那套函数
// （includes/downloads.php），而不是另算一遍。取不到就整句降级，不猜、不显示假数字。
$manualDlFiles = 0; $manualDlGB = 0; $manualDlSpecies = 0;
$manualDlDir = __DIR__ . '/download';
if (is_dir($manualDlDir)) {
    require_once __DIR__ . '/includes/downloads.php';
    $manualDlAll = cnido_dl_files($manualDlDir);
    $manualDlBytes = 0;
    foreach ($manualDlAll as $manualDlF) { $manualDlBytes += (int) @filesize($manualDlDir . '/' . $manualDlF); }
    $manualDlFiles = count($manualDlAll);
    $manualDlGB = (int) round($manualDlBytes / 1073741824);
    // 物种归属要查 abbr 表；连不上就只报文件数与大小（那两项纯文件系统，够用）
    $manualDlConn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    if ($manualDlConn instanceof mysqli && !$manualDlConn->connect_error) {
        list($manualDlTok, $manualDlKeys) = cnido_dl_tokens($manualDlConn);
        $manualDlSp = array();
        foreach ($manualDlAll as $manualDlF) {
            $manualDlS = cnido_dl_species($manualDlF, $manualDlTok, $manualDlKeys);
            if ($manualDlS !== '') { $manualDlSp[$manualDlS] = 1; }
        }
        $manualDlSpecies = count($manualDlSp);
        $manualDlConn->close();
    }
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
<title>User Manual - CnidoSite</title>
<meta name="keywords" content="CnidoSite, user manual, documentation, help guide, tutorial, Cnidaria, multi-omics" />
<meta name="description" content="Complete HTML user manual for the CnidoSite cnidarian multi-omics database" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<style>
/* ==========================================================================
   CnidoSite User Manual - scoped styles (manual-*)
   ========================================================================== */
.manual-hero {
    background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 45%, #2563eb 100%);
    color: #fff;
    padding: 38px 34px;
    border-radius: 14px;
    margin-bottom: 26px;
    box-shadow: 0 14px 40px rgba(29, 78, 216, 0.28);
    position: relative;
    overflow: hidden;
}
.manual-hero::after {
    content: "";
    position: absolute;
    top: -60%;
    right: -10%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, transparent 68%);
    border-radius: 50%;
}
.manual-hero h1 {
    margin: 0 0 12px 0;
    font-size: 32px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 1;
}
.manual-hero p {
    margin: 0;
    font-size: 16px;
    line-height: 1.65;
    max-width: 880px;
    opacity: 0.96;
    position: relative;
    z-index: 1;
}
.manual-hero .manual-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 18px 0 0 0;
    position: relative;
    z-index: 1;
}
.manual-badge {
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.32);
    border-radius: 50px;
    padding: 7px 16px;
    font-size: 15px;
    font-weight: 600;
    backdrop-filter: blur(6px);
}
.manual-hero-actions {
    margin-top: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    position: relative;
    z-index: 1;
}
.manual-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 20px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all .25s ease;
}
<?php /* 两个按钮都要写成 `a.类名`：templatemo_style.css 里的
   `a:link, a:visited{color:#1d4ed8}` 特异性 (0,1,1) 压过单类名 (0,1,0)，
   于是「⬇ Download PDF version」那颗按钮实际渲染成蓝字压在自己的半透明白底上
   （#0066cc on #3e569a = 1.26），几乎看不见。凑平特异性后靠本页 <style> 在
   样式表之后取胜。同时把 ghost 的半透明白底改成半透明**深蓝**底：
   白底叠加只会把 hero 提亮（最亮那端 #2563eb 上白字只剩 4.03），
   压深之后 8.02，白字稳稳可见。`<button>` 那颗不受 a:link 影响，保留原选择器。 */ ?>
.manual-btn-light, a.manual-btn-light { background: #fff; color: #1d4ed8; }
.manual-btn-light:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.18); }
.manual-btn-ghost, a.manual-btn-ghost { background: rgba(10,22,60,0.34); color: #fff; border: 1px solid rgba(255,255,255,0.55); }
.manual-btn-ghost:hover { background: rgba(10,22,60,0.48); }

/* Search bar */
.manual-searchbar {
    display: flex;
    /* 窄屏放不下时换行，而不是把 Clear 按钮顶出栏外（360px 下顶到 370）；
       390px 起本来就一行放得下，布局不变。 */
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 14px;
    margin-bottom: 22px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.05);
}
.manual-searchbar .icon { font-size: 18px; color:#64748b; }
.manual-searchbar input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 15px;
    color: #1e293b;
    background: transparent;
    min-width: 120px;
    /* 15px 字号的输入框裸高只有 19px，手机上（字段宽 190）要瞄一条 19px 高的
       缝才能点中。上下各加 4px，行高由旁边 38px 的按钮决定，整条搜索栏不高。 */
    padding: 4px 0;
}
.manual-searchbar button {
    border: none;
    background: #1d4ed8;
    color: #fff;
    font-weight: 600;
    font-size: 15px;
    padding: 9px 18px;
    border-radius: 9px;
    cursor: pointer;
    transition: background .2s;
}
.manual-searchbar button:hover { background: #1e40af; }
.manual-searchbar .manual-search-clear {
    background: #f1f5f9;
    color: #475569;
    font-weight: 500;
}
.manual-searchbar .manual-search-clear:hover { background: #e2e8f0; }
.manual-search-status { font-size: 15px; color: #64748b; margin-bottom: 16px; display: none; }
.manual-search-status.show { display: block; }
.manual-search-status b { color: #1d4ed8; }

/* Layout */
.manual-layout {
    display: flex;
    align-items: flex-start;
    gap: 26px;
}
.manual-sidebar {
    flex: 0 0 280px;
    max-width: 280px;
    position: sticky;
    top: 16px;
    align-self: flex-start;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px 14px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    box-shadow: 0 4px 18px rgba(0,0,0,0.05);
}
.manual-sidebar h3 {
    margin: 0 0 12px 0;
    font-size: 15px;
    color: #64748b;
    font-weight: 700;
    padding: 0 8px;
}
.manual-toc { list-style: none; margin: 0; padding: 0; }
.manual-toc > li { margin-bottom: 2px; }
.manual-toc a {
    display: block;
    padding: 7px 10px;
    border-radius: 8px;
    font-size: 15px;
    color: #334155;
    text-decoration: none;
    transition: background .18s, color .18s;
    line-height: 1.35;
}
.manual-toc a:hover { background:#f0f9ff; color: #1d4ed8; }
.manual-toc a.active { background: #dbeafe; color: #1d4ed8; font-weight: 700; }
.manual-toc .manual-toc-chapter > a { font-weight: 700; color: #0f172a; }
.manual-toc .manual-toc-sub { list-style: none; margin: 2px 0 6px 0; padding: 0 0 0 12px; }
.manual-toc .manual-toc-sub a { font-size: 15px; color: #475569; padding: 5px 10px; }

.manual-main { flex: 1 1 auto; min-width: 0; }

<?php /* 正文里的 <code> 装的是 URL 和区间号这类不能断的词（最长的是
   browse.php?class=Hexacorallia&q=cerianth 和 NC_064035.1:18240000-18260000）。
   inline 元素没有 overflow-wrap 就只能整个溢出行外——375px 上最长那条把页面
   撑到 476px。anywhere 而不是 break-word：只有 anywhere 会同时把元素的
   min-content 宽度也降下来，断词才真的阻止溢出。 */ ?>
.manual-main code { overflow-wrap: anywhere; }

/* Chapters & sections */
.manual-chapter { margin-bottom: 34px; }
.manual-chapter-head {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-left: 5px solid #1d4ed8;
    margin-bottom: 18px;
}
.manual-chapter-num {
    flex: 0 0 auto;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 18px;
}
.manual-chapter-head h2 { margin: 0; font-size: 22px; color: #0f172a; }
.manual-chapter-head .manual-chapter-sub { margin: 3px 0 0 0; font-size: 16px; color: #64748b; font-weight: 400; }

.manual-section {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px 24px;
    margin-bottom: 18px;
    box-shadow: 0 3px 14px rgba(0,0,0,0.04);
    scroll-margin-top: 16px;
}
.manual-section.hidden { display: none; }
.manual-section h3 {
    margin: 0 0 12px 0;
    font-size: 18px;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 9px;
}
.manual-section h3::before {
    content: "";
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #2563eb;
    display: inline-block;
    flex: 0 0 auto;
}
.manual-section p { font-size: 16px; line-height: 1.7; color: #334155; margin: 0 0 12px 0; }
<?php /* 小节内的分目标题。原先是逐处写的行内样式，现统一成类，改版式只改一处。 */ ?>
.manual-subhead { font-size: 16px; font-weight: 700; color: #0f172a; margin: 20px 0 8px 0; }
<?php /* 中性说明块，用于「这条控件实际不筛数据」一类需要讲清楚但不该像警报的事。 */ ?>
.manual-note {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #94a3b8;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 16px;
    color: #475569;
    margin: 14px 0 0 0;
}
.manual-note strong { color: #334155; }
.manual-section ul, .manual-section ol { font-size: 16px; line-height: 1.7; color: #334155; margin: 0 0 14px 0; padding-left: 22px; }
.manual-section li { margin-bottom: 6px; }
.manual-section .manual-tip {
    background:#f0f9ff;
    border-left: 4px solid #2563eb;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 16px;
    color: #1d4ed8;
    margin: 14px 0 0 0;
}
.manual-section .manual-tip strong { color: #1d4ed8; }

/* Screenshots */
.manual-figures {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin: 18px 0 4px 0;
}
.manual-figure {
    flex: 1 1 340px;
    max-width: 100%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px;
    box-sizing: border-box;
}
.manual-figure img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #fff;
    cursor: zoom-in;
    transition: box-shadow .2s, transform .2s;
}
.manual-figure img:hover { box-shadow: 0 6px 20px rgba(29,78,216,0.22); transform: translateY(-2px); }
<?php /* 「图待补」占位样式。2026-09-27 起手册的 67 个图位全部换成真实截图
   （见 manual_images/），页面上已经没有 .manual-shot-pending 元素了。
   这套样式保留，是为了将来新增章节时还能先把编号和题注摆出来、再补图，
   而不是让浏览器显示破图。 */ ?>
.manual-figure .manual-shot-pending {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 170px;
    padding: 20px 16px;
    border: 1px dashed #cbd5e1;
    border-radius: 6px;
    background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 12px, #eef2f7 12px, #eef2f7 24px);
    text-align: center;
}
.manual-shot-pending .msp-tag {
    font-size: 13px;
    font-weight: 700;
    color:#64748b;
}
.manual-shot-pending .msp-what { font-size: 15px; color: #475569; line-height: 1.5; }
.manual-figure figcaption {
    font-size: 15px;
    color: #475569;
    line-height: 1.5;
    margin-top: 8px;
    padding: 0 2px;
}
.manual-figure .manual-fig-label {
    display: inline-block;
    font-size: 12px;
    font-weight: 700;
    color: #1d4ed8;
    background: #dbeafe;
    border-radius: 5px;
    padding: 2px 7px;
    margin-bottom: 6px;
}

/* Lightbox */
.manual-lightbox {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.9);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 30px;
    cursor: zoom-out;
}
.manual-lightbox.show { display: flex; }
.manual-lightbox img {
    max-width: 96%;
    max-height: 92%;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    background: #fff;
}
.manual-lightbox .manual-lightbox-close {
    position: absolute;
    top: 18px;
    right: 26px;
    color: #fff;
    font-size: 36px;
    line-height: 1;
    cursor: pointer;
    font-weight: 300;
}

/* Back to top */
.manual-top {
    position: fixed;
    right: 24px;
    bottom: 24px;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    color: #fff;
    border: none;
    cursor: pointer;
    font-size: 20px;
    box-shadow: 0 8px 22px rgba(29,78,216,0.4);
    display: none;
    z-index: 900;
    transition: transform .2s;
}
.manual-top.show { display: block; }
.manual-top:hover { transform: translateY(-3px); }

.manual-footnote {
    margin-top: 30px;
    padding: 16px 20px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    font-size: 16px;
    color: #475569;
    line-height: 1.6;
}
.manual-footnote a { color: #1d4ed8; font-weight: 600; }

/* Responsive */
@media (max-width: 900px) {
    <?php /* align-items 要显式改回 stretch：上面 .manual-layout 是 flex-start，
       竖排之后它管的是**宽度**，两个子项于是都退化成 shrink-to-fit ——
       侧栏 100% 拿到 333 却因 content-box 再加上 28 内边距 + 2 边框变成
       363，主栏按内容撑成 497，双双溢出 333 的栏。 */ ?>
    .manual-layout { flex-direction: column; align-items: stretch; }
    <?php /* width:100% 是**内容宽**，不 border-box 就会比容器多出内边距那 28px */ ?>
    .manual-sidebar { position: static; max-width: 100%; flex: 1 1 auto; width: 100%;
                      max-height: none; box-sizing: border-box; }
    .manual-hero { padding: 26px 20px; }
    .manual-hero h1 { font-size: 24px; }
}

/* Print */
@media print {
    #templatemo_header_wrapper, #templatemo_menu_wrapper, #templatemo_footer_wrapper,
    .manual-sidebar, .manual-searchbar, .manual-hero-actions, .manual-top,
    .manual-search-status, .manual-lightbox { display: none !important; }
    .manual-hero { box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .manual-section { box-shadow: none; break-inside: avoid; page-break-inside: avoid; }
    .manual-chapter { break-before: auto; }
    .manual-figure { flex: 1 1 45%; }
    body { background: #fff; }
}
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

<div class="manual-hero">
    <h1>📘 CnidoSite User Manual</h1>
    <p>A complete, searchable guide to browsing, searching and analysing multi-omics data for Cnidaria. This manual is provided as an interactive web page so that every chapter can be read directly in the browser, indexed by search engines, and kept up to date with the database.</p>
    <div class="manual-badges">
        <span class="manual-badge">🧭 8 chapters</span>
        <span class="manual-badge">🧬 40 sections</span>
        <span class="manual-badge">🖼️ 67 figures</span>
        <span class="manual-badge">🖨️ Print friendly</span>
    </div>
    <div class="manual-hero-actions">
        <a class="manual-btn manual-btn-light" href="#ch1">▶ Start reading</a>
        <a class="manual-btn manual-btn-ghost" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($manualPdf, ENT_QUOTES); ?>">⬇ Download PDF version</a>
        <button class="manual-btn manual-btn-ghost" onclick="window.print()">🖨 Print this manual</button>
    </div>
</div>

<!-- In-page search -->
<div class="manual-searchbar">
    <span class="icon">🔍</span>
    <input type="text" id="manualSearch" placeholder="Search the manual (e.g. BUSCO, co-expression, UMAP, BLAST, download)..." />
    <button type="button" id="manualSearchGo">Search</button>
    <button type="button" class="manual-search-clear" id="manualSearchClear">Clear</button>
</div>
<div class="manual-search-status" id="manualSearchStatus"></div>

<div class="manual-layout">

    <!-- ================= Sidebar TOC ================= -->
    <aside class="manual-sidebar">
        <h3>Contents</h3>
        <ul class="manual-toc" id="manualTOC">
            <li class="manual-toc-chapter"><a href="#ch1">1. Home</a>
                <ul class="manual-toc-sub">
                    <li><a href="#home">1.1 Home page</a></li>
                </ul>
            </li>
            <li class="manual-toc-chapter"><a href="#ch2">2. Data Browsing</a>
                <ul class="manual-toc-sub">
                    <li><a href="#taxonomy">2.1 Taxonomy</a></li>
                    <li><a href="#paleobiology">2.2 Paleobiology</a></li>
                    <li><a href="#genomic">2.3 Genomic Data</a></li>
                    <li><a href="#coverage-matrix">2.4 Data Coverage Matrix</a></li>
                    <li><a href="#species-portal">2.5 Species Portal</a></li>
                </ul>
            </li>
            <li class="manual-toc-chapter"><a href="#ch3">3. Gene and Function Search</a>
                <ul class="manual-toc-sub">
                    <li><a href="#gene-search">3.1 Gene Search</a></li>
                    <li><a href="#busco">3.2 BUSCO Genes</a></li>
                    <li><a href="#te">3.3 Transposable Elements</a></li>
                    <li><a href="#tfs">3.4 TFs/Ubs</a></li>
                    <li><a href="#protein-domain">3.5 Protein Domain</a></li>
                    <li><a href="#gene-family">3.6 Gene Family</a></li>
                    <li><a href="#pangenome">3.7 Pan-geneset</a></li>
                    <li><a href="#domain-search">3.8 Functional Domain Search</a></li>
                    <li><a href="#go">3.9 Gene Ontology</a></li>
                    <li><a href="#interpro">3.10 InterPro</a></li>
                    <li><a href="#kegg">3.11 KEGG Pathway</a></li>
                </ul>
            </li>
            <li class="manual-toc-chapter"><a href="#ch4">4. Multi-Omics Analysis and Visualization</a>
                <ul class="manual-toc-sub">
                    <li><a href="#species-tree">4.1 Species Tree</a></li>
                    <li><a href="#transcriptomic">4.2 Transcriptomic Data</a></li>
                    <li><a href="#coexpression">4.3 Network Analysis</a></li>
                    <li><a href="#dynamic-expression">4.4 Dynamic Expression View</a></li>
                    <li><a href="#cell-atlas">4.5 Cell Atlas</a></li>
                    <li><a href="#cell-markers">4.6 Cell Marker</a></li>
                    <li><a href="#singlecell-gene">4.7 Gene Expression</a></li>
                    <li><a href="#proteomic">4.8 Proteomic Analysis</a></li>
                    <li><a href="#epigenomic">4.9 Epigenomic Analysis</a></li>
                    <li><a href="#mags">4.10 Metagenome-Assembled Genomes</a></li>
                    <li><a href="#phenotype">4.11 Phenotypic Information</a></li>
                    <li><a href="#mitogenomic">4.12 Mitogenomic Data</a></li>
                    <li><a href="#macrosynteny">4.13 Macrosynteny Analysis</a></li>
                    <li><a href="#proteomic-reanalysis">4.14 Proteomic Re-analysis</a></li>
                </ul>
            </li>
            <li class="manual-toc-chapter"><a href="#ch5">5. Analysis Tools</a>
                <ul class="manual-toc-sub">
                    <li><a href="#gsea">5.1 Gene Set Enrichment Analysis</a></li>
                    <li><a href="#blast">5.2 BLAST Alignment</a></li>
                    <li><a href="#primer">5.3 Primer Design</a></li>
                    <li><a href="#jbrowse">5.4 JBrowse Visualization</a></li>
                </ul>
            </li>
            <li class="manual-toc-chapter"><a href="#ch6">6. Data Download</a>
                <ul class="manual-toc-sub">
                    <li><a href="#download">6.1 Download</a></li>
                </ul>
            </li>
            <li class="manual-toc-chapter"><a href="#ch7">7. CnidoSite Data</a>
                <ul class="manual-toc-sub">
                    <li><a href="#statistics">7.1 Statistics</a></li>
                    <li><a href="#release">7.2 Release, Versioning and Programmatic Access</a></li>
                </ul>
            </li>
            <li class="manual-toc-chapter"><a href="#ch8">8. Data Submit &amp; Contact Us</a>
                <ul class="manual-toc-sub">
                    <li><a href="#submit">8.1 Data Submit</a></li>
                    <li><a href="#contact">8.2 Contact Us</a></li>
                </ul>
            </li>
        </ul>
    </aside>

    <!-- ================= Main content ================= -->
    <main class="manual-main">

    <!-- ============ Chapter 1 ============ -->
    <section class="manual-chapter" id="ch1">
        <div class="manual-chapter-head">
            <div class="manual-chapter-num">1</div>
            <div>
                <h2>Home</h2>
                <p class="manual-chapter-sub">Overview of the database and quick access to the main resources.</p>
            </div>
        </div>

        <article class="manual-section" id="home" data-title="Home page database introduction phylogeny statistics resources news">
            <h3>1.1 Home page</h3>
            <p>The Home page is the entry point to CnidoSite and gives a complete overview of the database. From here you can jump directly to each omics module, read the latest updates and find the resources associated with the project.</p>
            <ul>
                <li><strong>Detailed introduction</strong> to the CnidoSite database and the cnidarian species it covers.</li>
                <li><strong>Phylogeny overview</strong> &mdash; a cnidarian tree over the <strong>148 species</strong> that have a molecular phylogeny in this release. Note that the tree and the catalogue cover different sets: the catalogue holds <strong>326 species</strong>, of which 148 are placed on the tree. Click the tree to open it full screen, and press <em>Zoom to 100%</em> to read individual tip labels at the resolution of the original image. The full interactive tree, per-group subtrees and the underlying newick files are in the <a href="#species-tree">Species Tree</a> module.</li>
                <li><strong>Summary of the data composition</strong> of the CnidoSite database (class, order, family and species counts, plus datasets per omics layer).</li>
                <li><strong>Associated weblinks</strong> to external resources.</li>
                <li><strong>News and updates</strong> reporting recent releases and newly added datasets, drawn from the changelog on the <a href="/release.php">Release &amp; Changelog</a> page.</li>
                <li><strong>Contact information</strong> for the CnidoSite team.</li>
            </ul>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 1.1</span>
                    <img src="./manual_images/home.png" alt="CnidoSite home page" data-caption="CnidoSite home page: database introduction, data statistics, phylogeny overview, external resources, recent updates and contact." loading="lazy" decoding="async" />
                    <figcaption>Home page of CnidoSite showing the database introduction, the data statistics panel, a phylogeny overview and the latest news.</figcaption>
                </figure>
            </div>
            <div class="manual-tip"><strong>Tip:</strong> The data statistics panel on the Home page is refreshed together with the database, so it always reflects the current content.</div>
            <div class="manual-tip"><strong>Not sure where to start?</strong> To find out which species has which data before opening a module, use the <a href="#coverage-matrix">Data Coverage Matrix</a> (2.4), and to see everything held for one species on a single page use its <a href="#species-portal">Species Portal</a> (2.5).</div>
        </article>
    </section>

    <!-- ============ Chapter 2 ============ -->
    <section class="manual-chapter" id="ch2">
        <div class="manual-chapter-head">
            <div class="manual-chapter-num">2</div>
            <div>
                <h2>Data Browsing</h2>
                <p class="manual-chapter-sub">Browse taxonomy, fossil records and genome assemblies.</p>
            </div>
        </div>

        <article class="manual-section" id="taxonomy" data-title="taxonomy browse species class order family phylum NCBI WoRMS GBIF Hexacorallia Octocorallia Hydrozoa Cubozoa Scyphozoa Staurozoa Myxozoa Ceriantharia pagination per_page deep link">
            <h3>2.1 Taxonomy</h3>
            <p><strong>What it does.</strong> Lists every cnidarian species registered in CnidoSite &mdash; <strong>326</strong> in this release &mdash; one row per species, with its full Linnaean path and its cross-references to three external authorities. It is the complete inventory of the catalogue: a species appears here whether or not it has a genome, gene models or anything else, so this is the page to check first when you need to know whether a name is held in CnidoSite at all.</p>
            <p><strong>Why you would use it.</strong> To obtain the identifier the rest of the site keys on; to confirm the current classification of a taxon before citing it; to jump from a name to its NCBI Taxonomy, WoRMS and GBIF records; or to enumerate every member of one class. Note the denominator: all 326 are catalogued, but only <strong>145</strong> of them have gene models, so a row here is a statement about registration, not about the volume of data behind it.</p>
            <p class="manual-subhead">The controls</p>
            <ul>
                <li><strong>Class</strong> &mdash; the class filters are the <em>Taxonomy</em> drop-down in the navigation bar at the top of the page, not a control in the page body: <em>All</em> (326 species), <em>Hexacorallia</em> (197), <em>Octocorallia</em> (39), <em>Hydrozoa</em> (36), <em>Scyphozoa</em> (27), <em>Myxozoa</em> (17), <em>Cubozoa</em> (7) and <em>Staurozoa</em> (3). Each is a genuine server-side filter and writes the class into the URL (<code>browse.php?class=Hydrozoa</code>), so any filtered view can be bookmarked. Matching is case-insensitive, and a value that is not a class name returns an empty table rather than an error.</li>
                <li><strong>Search</strong> &mdash; the <em>Search this list</em> box above the table narrows the rows to those containing a text fragment. It is plain server-side substring matching, so it needs no JavaScript, and it looks in <em>all nine columns</em> case-insensitively: <code>hydra</code> gives 5 taxa, <code>cerianth</code> gives the single ceriantharian, and a WoRMS AphiaID such as <code>101013</code> gives the species that carries it. It searches only what the table stores &mdash; the full path down to the species, nothing in between &mdash; so <code>Cnidaria</code> matches all 326 rows, but a rank the table never holds is not findable at all: <code>Anthozoa</code> (subphylum) and <code>Medusozoa</code> both return nothing. The term travels in the URL as <code>q</code> and composes with the class filter, so <code>browse.php?class=Hexacorallia&amp;q=cerianth</code> reports &ldquo;1 of 197&rdquo;; the <em>Show</em>, paging and <em>Go to page</em> controls all carry it, so a search can be bookmarked and paged. <code>%</code> and <code>_</code> are matched literally, not as wildcards. When a search inside one class finds nothing, the page offers a one-click link that repeats it over the whole catalogue.</li>
                <li><strong>Show</strong> &mdash; rows per page, from the selector below the table: 10, 20, 50 or 100. The value travels in the URL as <code>per_page</code>; the selector is only a convenience, and any number is accepted, so <code>per_page=326</code> gives the whole catalogue on one page.</li>
                <li><strong>Paging</strong> &mdash; <em>First / Previous / Next / Last</em> and the <em>Go to page</em> box, both carried in the URL as <code>page</code> alongside the class, the search term and the page size.</li>
                <li><strong>Not available</strong> &mdash; there is no sort control and no column picker, and the rows are not grouped by rank: every row repeats its Phylum, Class, Order, Family and Genus, so a species is found by its own name or by its path, never by its ancestry.</li>
            </ul>
            <p class="manual-subhead">The results table</p>
            <ul>
                <li><strong>Phylum, Class, Order, Family, Genus</strong> &mdash; plain text. In this table only the <em>Species</em> column is a link.</li>
                <li><strong>Species</strong> &mdash; the accepted Latin name, and the one clickable cell in the row: it opens <em>Species Information</em> (assembly statistics and the reference publication) in a new tab. A second line beneath the name, <em>Species Portal</em>, opens the <a href="#species-portal">Species Portal</a> (2.5) for that species.</li>
                <li><strong>NCBI Taxonomy ID, WoRMS ID, GBIF ID</strong> &mdash; each links to the corresponding external record in a new tab. A <code>-</code> is printed as plain text and means the identifier is <em>absent</em>: currently 2 species have no NCBI Taxonomy ID, 32 no WoRMS ID and 39 no GBIF ID.</li>
            </ul>
            <p>Rows are sorted by <em>Class</em>, then <em>Order</em>, then <em>Species</em> name, which is why the classes appear as contiguous blocks. No two species in the catalogue share a name, so that sequence is a total order: the same view returns the same rows in the same order on every visit, and a page number is a reproducible citation as long as you quote the class filter, the search term and the page size with it. Registering a species or moving one between classes still shifts everything after it.</p>
            <div class="manual-note"><strong>Two things to know before quoting this page.</strong> (1) The sentence above the search box is generated from whatever the current view holds: with no filter it reads &ldquo;the taxonomy coverage of <b>326</b> Cnidarians is listed&rdquo;, under the <em>Hexacorallia</em> filter &ldquo;&hellip; of <b>197</b> Cnidarians&hellip;&rdquo; &mdash; the size of the view you are looking at, never the size of the catalogue &mdash; and under a search it names the scope instead of counting, because the count of hits is given in full by the line beneath the search box (&ldquo;<b>5</b> of <b>326</b> taxa &hellip; match <i>hydra</i>&rdquo;). (2) The 326 rows are 326 names, not 326 organisms: two organisms are each catalogued twice, and both pairs therefore carry the <em>same</em> NCBI Taxonomy ID in the table. <i>Desmophyllum pertusum</i> and <i>Lophelia pertusa</i> are one species under two names, sharing ID 174260 &mdash; WoRMS records <i>Lophelia pertusa</i> as a <em>superseded combination</em> of <i>Desmophyllum pertusum</i>, and the two rows also carry the same assembly, GCA_029204205.1. <i>Actinoscyphia liui</i> and <i>Actinoscyphia</i> sp. m1220 are one still-undescribed species under its manuscript name and NCBI's provisional name, sharing ID 2928332, the same assembly GCA_041296415.1 and the same BioProject PRJNA817838. Neither pair is an error to be reported: the site keeps both names because the literature uses both.</div>
            <div class="manual-tip"><strong><em>Ceriantharia</em> is not a class on this site.</strong> The tube-dwelling ceriantharian <i>Pachycerianthus multiplicatus</i> is listed under <em>Hexacorallia</em>, with <em>Ceriantharia</em> in its <em>Order</em> column. Taxonomic authorities disagree about the group &mdash; WoRMS, the record this page links to for the species, treats Ceriantharia as an <em>order</em> inside the class Hexacorallia, whereas NCBI ranks it as a <em>subclass</em> alongside Hexacorallia and Octocorallia &mdash; and the site follows WoRMS, so the catalogue holds <strong>seven</strong> classes and the drop-down lists all seven. A link of the form <code>browse.php?class=Ceriantharia</code>, offered in earlier releases, therefore returns an empty table: reach the species from <em>All</em>, from the <em>Hexacorallia</em> filter, or on its own page. Also avoid the historical spelling <em>Hexactiniaria</em> here: this is the one taxonomy view that does not map it to <em>Hexacorallia</em>, so <code>?class=Hexactiniaria</code> returns an empty table.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 2.1</span>
                    <img src="./manual_images/taxonomy.png" alt="Taxonomy overview table" data-caption="Taxonomy module: cnidarian species with Phylum/Class/Order/Family/Genus/Species and cross-references to NCBI, WoRMS and GBIF." loading="lazy" decoding="async" />
                    <figcaption>Taxonomy overview. The <em>Species</em> column opens the detailed page for that taxon, and the three identifier columns open the external records. <em>(The species count in this screenshot is the one at the time it was taken; the module itself always shows the current release.)</em></figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="paleobiology" data-title="paleobiology fossil records PBDB paleobiology database taxon card taxon page stratigraphic range geological time scale deep link species per_page pagination">
            <h3>2.2 Paleobiology</h3>
            <p><strong>What it does.</strong> Lists the cnidarian taxa for which the <a href="https://paleobiodb.org/" target="_blank" rel="noopener noreferrer">Paleobiology Database</a> (PBDB) holds a fossil record &mdash; <strong>410 taxa</strong> in this release &mdash; giving the taxonomic path of each and, for every rank, a link to that taxon's record. It is the palaeontological counterpart of <a href="#taxonomy">Taxonomy</a> (2.1): the same Linnaean columns, drawn from the fossil record instead of the genome catalogue.</p>
            <p><strong>Why you would use it.</strong> To check whether a taxon has a fossil record at all; to obtain the PBDB identifier for a name before querying PBDB itself; or to see which species of the genome catalogue also have a fossil history. Check the overlap before relying on it: only <strong>71</strong> of these 410 taxa are also in the CnidoSite catalogue, and 255 of the 326 catalogue species have no row here &mdash; so &ldquo;no fossil records&rdquo; for a species you are working on is the normal case, not a failure.</p>
            <p class="manual-subhead">The controls</p>
            <ul>
                <li><strong>Species</strong> &mdash; normally reached from elsewhere on the site rather than typed. The URL parameter <code>species=</code> narrows the table to one taxon, and the value may be the Latin name (<code>Acropora cervicornis</code>), the underscore form, or the five-letter code (<code>ACERV</code>); <code>species=all</code> clears the filter. The selection is kept in your session, so the page goes on showing it until you clear it or follow a plain link.</li>
                <li><strong>Show</strong> &mdash; rows per page, from the selector below the table: 10, 20, 50 or 100, carried in the URL as <code>per_page</code> and preserved as you page.</li>
                <li><strong>Paging</strong> &mdash; <em>First / Previous / Next / Last</em> and the <em>Go to page</em> box, carried as <code>page</code> and preserving any species filter.</li>
                <li><strong>Not available</strong> &mdash; there is no filter by class, geological period, age, formation or locality, and no search box and no sort control. A parameter added to the URL in the hope of one is ignored.</li>
            </ul>
            <p class="manual-subhead">The results table</p>
            <ul>
                <li><strong>Phylum, Class, Order, Family, Genus, Species</strong> &mdash; all six columns are taxonomy, and every populated cell is a link, opening in a new tab. Where no link is recorded the name is printed as plain text instead: 4 rows for <em>Order</em> (3 distinct orders) and 15 for <em>Family</em> &mdash; 9 of those 15 record no family at all, and the other 6 name one of 4 families for which PBDB has no page.</li>
                <li><strong>The taxon card</strong> &mdash; any of those links opens a CnidoSite page summarising that one PBDB record: its rank, its parent taxon, whether it is still living, its first and last appearance with the bounding ages in Ma, the number of fossil occurrences recorded, how many taxa it contains, and the reference its name was entered from. The range is drawn rather than only tabulated: a bar runs along a geological time scale, dark green where fossils confirm the range, pale green for the part of each end whose age is only known to within an interval, and hatched where an extinct taxon has no record at all, with the bounding ages ticked off in Ma. A breadcrumb over the title shows where the taxon sits, taken from our own paleobiology table rather than from PBDB, and each rank on it can be clicked. The card is built by our own server from the PBDB data API, so it opens even when PBDB is unreachable from your network &mdash; the table used to link straight out to PBDB, and such links failed for anyone whose browser or network stripped the request's <em>Referer</em>. A <strong>View on PBDB</strong> button still goes on to the full record for the complete classification, collection list and references; <strong>Back to Paleobiology</strong> returns here. For a <em>Species</em> cell the card also offers a link back to that species' rows in this table.</li>
                <li>There is <em>no</em> fossil-specific column: no geological age or stage, no formation, locality, abundance, collection number or reference. Those live behind the taxon card on each cell &mdash; CnidoSite carries the taxonomy, and the card and PBDB carry the palaeontology.</li>
                <li>The <em>Species</em> cell does not link to the CnidoSite species page. To reach the rest of a species' data, use the filter banner described below, the <a href="#coverage-matrix">Data Coverage Matrix</a> (2.4) or the <a href="#species-portal">Species Portal</a> (2.5).</li>
            </ul>
            <div class="manual-note"><strong>Two numbers on the taxon card that read differently from what you would expect.</strong> <em>Fossil occurrences</em> counts the occurrences recorded for the taxon <em>or any of its subtaxa</em>, so the phylum <em>Cnidaria</em> shows a five-figure total &mdash; that is the whole group, not the phylum rank on its own; <em>Taxa in this group</em> likewise includes the taxon itself, so a species shows 1. Bounding ages are in Ma (millions of years before present), written early bound &ndash; late bound, and <code>0 Ma</code> means the present day. These totals come from PBDB and change as PBDB is updated, independently of a CnidoSite release.</div>
            <div class="manual-note"><strong>&ldquo;410 fossil records&rdquo; means 410 taxa.</strong> The table holds exactly one row per taxon, not one row per fossil occurrence, so the figure measures taxonomic breadth and not how much fossil material has been described. The fossil classes present are <em>Octocorallia</em>, <em>Hexacorallia</em> and <em>Hydrozoa</em> only &mdash; no Cubozoa, Myxozoa, Scyphozoa or Staurozoa. This table groups by class, so the ceriantharian <i>Pachycerianthus multiplicatus</i> would appear under <em>Hexacorallia</em> if it had a fossil record, which it does not.</div>
            <div class="manual-tip"><strong>The list has no sort order, so a page number is not reproducible.</strong> The query carries no <code>ORDER BY</code>, so a taxon can move between pages from one visit to the next; cite a taxon name, never &ldquo;page 3&rdquo;. When a species filter is active, a banner above the table shows either the matching record or an explicit &ldquo;no fossil records &hellip; are available&rdquo; message, and in both cases offers a link to that species' portal so you can see what the species does have.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 2.2</span>
                    <img src="./manual_images/paleobiology.png" alt="Paleobiology fossil records table: 410 cnidarian taxa in six taxonomic columns, each rank linked to its PBDB page where one exists" data-caption="Paleobiology module: 410 fossil taxa collected from the Paleobiology Database (PBDB); each rank in a row links to its PBDB page where PBDB has one." loading="lazy" decoding="async" />
                    <?php /* 原来写的是 "one link per rank" / "Every rank in a row links to a taxon card" ——
                             实测不是每个阶元都有链接：Order 与 Family 在首页 10 行里各只有 9 行带链接
                             （PBDB 对某些分类单元的那个阶元没有独立页面）。改成「有页面才链」。 */ ?>
                    <figcaption>Paleobiology module listing the 410 taxa with a fossil record in the Paleobiology Database. Each rank that PBDB has a page for links to a taxon card.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="genomic" data-title="genomic data genome assembly BUSCO completeness gene number literature species overview without gene models 181 annotation only assembly only">
            <h3>2.3 Genomic Data</h3>
            <p><strong>What it does.</strong> The Species Overview table holds one row per assembled cnidarian genome &mdash; <strong>326</strong> in this release &mdash; giving the assembly's level, size and release year, its NCBI Taxonomy ID, the reference publication, and a statement of how far it has been annotated here. It is the only page that presents the assembly metadata for the whole catalogue at once, and the page on which you decide which genome to use as a reference.</p>
            <p><strong>Why you would use it.</strong> To choose a well-assembled reference genome before a downstream analysis, filtering to the 145 genomes that have gene models; to obtain the taxonomy identifier and the publication to cite for an assembly; or to check whether a species has data before opening a gene-level module. A species labelled <em>assembly only</em> returns nothing in any gene-level module &mdash; Gene Search, BUSCO, TFs/Ubs and the genome browser alike. An <em>annotation only</em> species has no gene models here, so Gene Search and the genome browser return nothing for it either, but the functional-annotation modules do list it, and so do BUSCO and TFs/Ubs, which work from its deposited protein set rather than from a gene-model table. This page is where that is visible up front.</p>
            <p class="manual-subhead">The controls</p>
            <ul>
                <li><strong>All species (326)</strong>, <strong>With gene models (145)</strong>, <strong>Without gene models (181)</strong> &mdash; three filter buttons above the table. <em>Without gene models</em> groups the <em>assembly only</em> and <em>annotation only</em> species together (178 + 3); see the note below on why it is not labelled 178. The three counts are live and match the number of rows each button returns.</li>
                <li><strong>Species deep link</strong> &mdash; the URL parameter <code>species=</code> narrows the table to a single species and accepts the Latin name, the underscore form or the five-letter code (<code>NVECT</code>). An amber banner names the species being shown and offers <em>show all species</em> to clear it; an unrecognised value gives an empty table and the banner says so. The selection is remembered in your session, so a later bare visit to the module keeps it &mdash; the filter buttons clear it.</li>
                <li><strong>Not available</strong> &mdash; no sort control, no search box, no class filter and no paging: the table always renders every species matching the current filter, and the <em>Class</em> column is display-only.</li>
            </ul>
            <p class="manual-subhead">The results table</p>
            <ul>
                <li><strong>Class</strong> &mdash; a plain text label; <strong>Species Latin Name</strong> &mdash; opens <em>Species Information</em> in a new tab.</li>
                <li><strong>NCBI Taxonomy ID</strong> &mdash; linked to the NCBI Taxonomy Browser. Every one of the 326 rows has a value.</li>
                <li><strong>Size (Mb), Assembly Level, Released Year</strong> &mdash; the assembly statistics. The levels present are chromosome 97, complete genome 1, contig 55 and scaffold 173, so <strong>98</strong> assemblies are chromosome-level.</li>
                <li><strong>Pubmed ID</strong> &mdash; the publication recorded for the assembly. 99 rows print a dash because no PubMed ID is recorded, which is not an error.</li>
                <li><strong>Gene annotation</strong> &mdash; the status label described below and, for a fully annotated species, the protein-coding gene count. 29 of the 145 annotated rows print no count because the source metadata has none; the label is still valid.</li>
            </ul>
            <p class="manual-subhead">How the annotation status is decided</p>
            <p>Each row carries one of three labels, and the label is derived from <em>what the database actually holds</em> for that species, not from a hand-maintained metadata field:</p>
            <ul>
                <li><strong>Full annotation</strong> &mdash; the species has a predicted gene set here, so it appears in Gene Search, BUSCO, TFs/Ubs, the genome browser and every gene-level module. Currently <strong>145 species</strong>.</li>
                <li><strong>Annotation only</strong> &mdash; the species has functional annotation (GO, InterPro, Pfam, PANTHER, KEGG) but no predicted gene set, so it appears in those modules &mdash; and, from its deposited protein set, in BUSCO and TFs/Ubs &mdash; but not in Gene Search or the genome browser. Currently <strong>3 species</strong>: <i>Alatina alata</i>, <i>Calvadosia cruxmelitensis</i> and <i>Cassiopea xamachana</i>.</li>
                <li><strong>Assembly only</strong> &mdash; neither a predicted gene set nor a functional annotation table: the assembly record is all there is, with its genome size, assembly level, accession and literature. Currently <strong>178 species</strong>.</li>
            </ul>
            <p><strong>The third button counts more species than the &ldquo;assembly only&rdquo; rows.</strong> It is labelled <em>Without gene models</em> &mdash; currently <strong>181</strong>, not 178 &mdash; precisely because it groups the last two statuses above (178 + 3). The 3 <em>annotation only</em> species belong in that group: they genuinely have no gene models, so they are absent from Gene Search. But they can still be queried in GO, InterPro, Pfam, PANTHER and KEGG, so presenting those 181 as &ldquo;assembly only&rdquo; would be false. The filter is defined by the <em>absence of a gene-model table</em>; the row label describes what an individual species <em>does</em> have. Read the row label, not the button, when you want to know what a species can be used for.</p>
            <div class="manual-note">All <strong>326</strong> species have an assembly record, so the <em>Genome</em> column of the <a href="#coverage-matrix">Data Coverage Matrix</a> is set for every row; <em>Transcripts</em> is set for 145. (The number in that column counts predicted transcripts, one per mRNA, so a gene with several isoforms contributes more than one.) A row with a genome but no gene models means &ldquo;the assembly is catalogued here, but it has not been annotated in CnidoSite&rdquo;, not that data are missing by mistake. The same applies to a missing gene count: the three <em>annotation only</em> species do have a protein count in the source metadata, but it is not displayed because the count cannot be tied to a gene set held here.</div>
            <div class="manual-tip"><strong>The publication column can lead outside PubMed.</strong> The number printed is a PubMed ID, but the link behind it points at whatever URL is recorded for that assembly &mdash; for a large share of species that is the publisher's DOI rather than a PubMed page. The number and the page you land on therefore do not always correspond; to reach the PubMed record itself, search the number on PubMed rather than following the link.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 2.3</span>
                    <img src="./manual_images/genomic_data.png" alt="Genomic data / species overview" data-caption="Genomic Data module: assembly statistics, annotation status, gene number and references for each species." loading="lazy" decoding="async" />
                    <figcaption>Genomic Data module summarising assembly statistics and the associated literature for each species, with the annotation status marked on every row.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="coverage-matrix" data-title="data coverage matrix availability which species have which data download TSV xlsx">
            <h3>2.4 Data Coverage Matrix</h3>
            <p><strong>What it does.</strong> Answers the question &ldquo;which species have which data?&rdquo; before you open a module. It is one table of every species in the catalogue against every data type the site covers &mdash; 326 rows by 16 columns &mdash; with a link from each populated cell straight into the corresponding module, already filtered to that species. Three of the columns belong to one heading: the bulk transcriptome, the transcriptome assembly and the co-expression network sit together under <em>Transcriptome</em>, labelled <em>Data</em>, <em>Assembly</em> and <em>Network</em>.</p>
            <p><strong>Where to find it.</strong> At the foot of <a href="genomeinfo.php">Genomic Data</a> (2.3), 20 species to a page with numbered pages; there it sits directly under the species overview, so a species that looks short of data in the table above can be checked against the full grid without leaving the page. The same table also stands alone at <code>coverage_matrix.php</code>, and appears again on <a href="data_statistics.php">Statistics</a> under Help. All three render from one implementation, so they never disagree.</p>
            <p><strong>Why you would use it.</strong> To choose a species that actually carries the data your analysis needs, rather than discovering after a search that it does not; to see at a glance how complete one species is; or to compare classes on coverage. Use it together with <a href="#genomic">Genomic Data</a> (2.3), which explains why the denominator differs between gene-level and assembly-level modules, and with the <a href="#species-portal">Species Portal</a> (2.5) when you want the same facts for a single species with its assembly record.</p>
            <ol>
                <li>Narrow the list with the filter row: <em>Class</em>, a species <em>name</em> box, and <em>at least N data types</em> (0&ndash;16) to keep only species that are well covered.</li>
                <li>Click a chip in the <em>Species per data type</em> row to keep only the species that have that data type; click it again, or use <em>clear</em>, to remove the filter.</li>
                <li>Sort by class, by species name, or by completeness, and set the page size &mdash; 20, 50 or 100 rows in the copy embedded in <a href="genomeinfo.php">Genomic Data</a>, 50, 100 or 200 on the standalone page.</li>
                <li>Click a species name to open its <a href="#species-portal">Species Portal</a> (2.5) in a new tab.</li>
            </ol>
            <p><strong>How to read a cell.</strong> A <strong>number</strong> is the count of records held for that species; a <strong>tick</strong> means the data are present but are catalogued as a single resource rather than a countable set; a <strong>dot</strong> means the data type is genuinely not available for that species in this release. A tick in <em>Mitogenome</em> is a special case &mdash; it marks a species whose mitochondrial genome is catalogued from a genome-level record that has no per-gene annotation; those cells are written <code>NA</code> in the TSV export.</p>
            <p><strong>The three <em>Transcriptome</em> columns.</strong> They are independent of one another and do not imply one another. <em>Data</em> is a count of RNA-seq samples; <em>Assembly</em> is a tick for a species that has an assembled transcriptome, whose predicted proteins carry functional annotation; <em>Network</em> is a tick for a species with a co-expression network. A species can have samples without an assembly, or an assembly without sample records, and the two figures differ accordingly &mdash; the legend under the table states how many species fall in each case.</p>
            <ul>
                <li><strong>Download TSV</strong> &mdash; the current filter and sort applied, all matching species rather than just the visible page. Countable columns are headed <code>(n)</code> and presence-only columns <code>(1/0)</code>.</li>
                <li><strong>Download Excel (.xlsx)</strong> &mdash; the same matrix plus a second sheet, <em>About this export</em>, recording the filters, the sort order and the meaning of each column, so a downloaded file can be interpreted later.</li>
            </ul>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 2.4</span>
                    <img src="./manual_images/coverage_matrix.png" alt="Data Coverage Matrix: the Species per data type chip row listing each data type with its species count, above a filter row (Class, species name, at least N data types, sort, rows per page, Apply, Reset, Download TSV, Download Excel), above the matrix of 326 species against the data types" data-caption="Data Coverage Matrix — the chip row with each data type and its species count, the filter and export row, and the matrix itself. Countable cells hold a number, presence-only cells a tick and absent data a dot; every populated cell links into its module with the species already selected." loading="lazy" decoding="async" />
                    <figcaption>Data Coverage Matrix: every species against every data type, with a link from each populated cell into the module. The chip row doubles as a filter &mdash; clicking a chip keeps only the species that have that data type.</figcaption>
                </figure>
            </div>
            <div class="manual-tip"><strong>Tip:</strong> The matrix is rebuilt from the live database every hour, so it can lag a data import by up to an hour. The same information is available to scripts through <code>api.php?resource=coverage</code> (see <a href="#release">7.2</a>).</div>
        </article>

        <article class="manual-section" id="species-portal" data-title="species portal overview all data types one species assembly record other species">
            <h3>2.5 Species Portal</h3>
            <p><strong>What it does.</strong> Every species name in the catalogue&rsquo;s own modules opens a portal page that gathers, on a single screen, everything CnidoSite holds for that species &mdash; the 16 data types with their record counts, the full assembly record, the reference publication &mdash; and links into each module already filtered to that species.</p>
            <p><strong>Why you would use it.</strong> It is the page to open first when you start working on a species: one screen tells you what is available and what is not, so you can plan an analysis without opening one module at a time to find out. It is also the fastest way to reach a module already filtered &mdash; the <a href="#coverage-matrix">Data Coverage Matrix</a> (2.4) shows the same availability across all species, and this page shows it in full for one.</p>
            <ul>
                <li>The opening line states how many of the <strong>16</strong> data types are held for this species.</li>
                <li><strong>Data available for this species</strong> &mdash; one card per data type, showing its status (<em>available</em> with a record count, or <em>not available</em>) and a link that opens the module for this species. Cards that have data also list sub-pages, such as <em>Cell Atlas / Cell Marker / Gene Expression</em> under Single-cell.</li>
                <li><strong>Assembly record</strong> &mdash; assembly name, level, accession (linked to NCBI), who assembled it and when; genome statistics (size, GC, scaffolds, N50, contigs, chromosomes); a BUSCO completeness bar with its legend and the protein-coding gene and protein counts; the WGS accession and BioProject; and the reference publication with its PubMed link.</li>
                <li><strong>Other species in the same class</strong> &mdash; quick links to related species, and a link to see the whole class in the coverage matrix.</li>
                <li>Use <em>Open another species</em> to switch species by Latin name; the box autocompletes over the whole catalogue.</li>
            </ul>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 2.5</span>
                    <img src="./manual_images/species_portal.png" alt="Species portal" data-caption="A species portal: the data types held for one species, each linking into its module, above the full assembly record." loading="lazy" decoding="async" />
                    <figcaption>A species portal: the data types held for one species, each linking into its module, above the full assembly record.</figcaption>
                </figure>
            </div>
            <div class="manual-tip"><strong>Tip:</strong> Availability here is recomputed every hour from the live database. If a data type is listed as unavailable for a species, it has not been generated for that species in the current release &mdash; the coverage matrix shows the same fact alongside every other species.</div>
        </article>
    </section>

    <!-- ============ Chapter 3 ============ -->
    <section class="manual-chapter" id="ch3">
        <div class="manual-chapter-head">
            <div class="manual-chapter-num">3</div>
            <div>
                <h2>Gene and Function Search</h2>
                <p class="manual-chapter-sub">Retrieve genes by identifier, region, keyword or domain, and explore gene families.</p>
            </div>
        </div>

        <article class="manual-section" id="gene-search" data-title="gene search gene id locus genomic region coordinate keyword function InterPro Pfam domain gene detail page species selector 145">
            <h3>3.1 Gene Search</h3>
            <p><strong>What it does.</strong> The main entry point for querying genes. It is a launcher page holding four separate search forms, each of which opens its own result page in a new tab: by <strong>gene ID</strong>, by <strong>genomic region</strong>, by <strong>functional keyword</strong> and by <strong>protein domain</strong>. Behind them sits the gene detail page, which gathers everything CnidoSite holds for one gene.</p>
            <p><strong>Why you would use it.</strong> When you have one identifier in hand and want its record and annotation; when you know a coordinate range rather than a gene name; when you want every gene whose annotation mentions a term; or as the starting point for the other gene-level modules, since the gene detail page is the hub that links on to the gene family, the genome browser and the species portal.</p>
            <p class="manual-subhead">The controls</p>
            <ul>
                <li><strong>Class</strong> &mdash; present on all four forms, but it is a <em>display</em> control only: it narrows which species appear in the species list and does nothing on the server. The species selector is the value actually submitted, so the class you pick has no effect on the result once a species is chosen.</li>
                <li><strong>Species</strong> &mdash; the real restriction, on every form. The selector lists the <strong>145 species that have annotated gene sets</strong>, grouped by class and shown with Latin names. Defaults to <i>Nematostella vectensis</i>. A species outside that set cannot be searched here: the module says so and falls back to the default rather than returning an empty result.</li>
                <li><strong>The four inputs</strong> &mdash; one identifier each, no batch input and no file upload: a <em>gene ID</em> (e.g. <code>XP_032233839.1</code>); a <em>genomic region</em> written as <code>assembly:start-end</code> (e.g. <code>NC_064035.1:18240000-18260000</code>); a <em>keyword</em> matched as a substring of the InterPro description (e.g. <code>ABC transporter</code>); or a <em>Pfam accession</em>, matched exactly (e.g. <code>PF12848</code>). Each form has an <em>Example</em> link that fills in a working value.</li>
            </ul>
            <p class="manual-subhead">The results</p>
            <ul>
                <li><strong>Gene ID</strong> opens the gene detail page: genomic location and NR annotation, a JBrowse view of the gene structure, the CDS, transcript and protein sequences, UniProt, gene family membership, and tables of Pfam, InterPro, PANTHER, GO and KEGG annotations, each linked to the external database. This page is the one to cite for a single gene.</li>
                <li><strong>Genomic region</strong> lists <em>Species, Gene, Annotation, Chr, Start, End, Strand</em> and a JBrowse link per row &mdash; and it shows every hit on one page with no paging.</li>
                <li><strong>Keyword</strong> lists <em>Species, Gene, InterPro ID, Annotation</em> and a JBrowse link.</li>
                <li><strong>Protein domain</strong> lists <em>Species, Gene, Pfam accession, Pfam name, Description, Type</em> and a JBrowse link.</li>
            </ul>
            <div class="manual-note"><strong>The region search is not a true overlap test &mdash; check a borderline hit before concluding it is absent.</strong> It returns a feature only when the feature's <em>start</em> or <em>end</em> falls strictly inside the interval you typed. Two consequences: a gene that <em>spans</em> your whole interval &mdash; starting before it and ending after it &mdash; is <strong>not returned</strong>; and the boundaries are exclusive, so querying a gene's own exact start and end coordinates returns nothing even though the gene is there. Query a slightly wider window than the feature you are looking for.</div>
            <div class="manual-tip"><strong>Two counts that are not what they look like.</strong> The keyword result header reports a number of <em>records</em>, not genes: a gene is listed once per matching InterPro entry, so <code>ABC transporter</code> in <i>N. vectensis</i> reports 1,489 records for only 147 distinct genes. And the Pfam form matches the accession exactly, without handling version suffixes &mdash; enter the bare accession. When a search returns nothing the module shows an alert and reopens the form; note that the wording of that alert (&ldquo;based on the function you typed&rdquo;) is reused for the region and domain forms too.</div>
            <div class="manual-note">The result pages carry a generic browser title, so several open tabs are hard to tell apart &mdash; the species and the query are stated in the page heading inside each one. None of the four result pages offers a download; to take a result away, use the <a href="#domain-search">Functional Domain Search</a> (3.8) for cross-species domain queries, or the <a href="#coverage-matrix">Data Coverage Matrix</a> (2.4) and <a href="download.php">Download</a> modules for bulk data.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.1</span>
                    <img src="./manual_images/gene_search_1.png" alt="Gene search interface" data-caption="Gene Search: Gene ID search and Genomic region search forms with class/species selectors." loading="lazy" decoding="async" />
                    <figcaption>Gene Search interface with the Gene ID and Genomic region search forms.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.2</span>
                    <img src="./manual_images/keyword_search_1.png" alt="Keyword and protein domain search" data-caption="Keyword search and Protein domain search forms." loading="lazy" decoding="async" />
                    <figcaption>Keyword (Gene function) search and Protein domain search forms.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.3</span>
                    <img src="./manual_images/gene_search_2.png" alt="Gene search form details" data-caption="Input fields for gene identifier and genomic coordinates." loading="lazy" decoding="async" />
                    <figcaption>Input fields for a gene identifier or a genomic coordinate range.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.4</span>
                    <img src="./manual_images/gene_search_3.png" alt="Gene search results" data-caption="Gene search results listing genes found within the queried locus." loading="lazy" decoding="async" />
                    <figcaption>Search results listing the genes located within the queried locus.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.5</span>
                    <img src="./manual_images/keyword_search_2.png" alt="Keyword search results" data-caption="Keyword search results with functional (InterPro) annotations." loading="lazy" decoding="async" />
                    <figcaption>Keyword search results with their associated functional (InterPro) annotations.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.6</span>
                    <img src="./manual_images/keyword_search_3.png" alt="Protein domain search results" data-caption="Protein domain (Pfam) search results." loading="lazy" decoding="async" />
                    <figcaption>Protein domain (Pfam) search results for the queried domain.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="busco" data-title="BUSCO gene completeness status score length jbrowse">
            <h3>3.2 BUSCO Genes</h3>
            <p><strong>What it does.</strong> Reports Benchmarking Universal Single-Copy Orthologs (BUSCO) results &mdash; the standard measure of how complete a genome assembly is &mdash; and lets you inspect the individual BUSCO matches. Every species is scored against the <strong>cnidaria_odb12</strong> lineage, which contains <strong>3,203</strong> BUSCOs, so the percentages are directly comparable between species. The page opens on a comparison table for all assessed species and continues into the hit list for the one you pick.</p>
            <p><strong>Why you would use it.</strong> To choose between candidate genomes on assembly quality rather than on size or recency; to check whether a gene you are looking for is missing from a species because the assembly is incomplete; or to find the gene model behind a particular conserved ortholog. The high-quality threshold used here is C &ge; 90&nbsp;%, D &le; 10&nbsp;% and F &le; 5&nbsp;%, and <strong>14</strong> of the <strong>148</strong> assessed species meet it.</p>
            <p class="manual-subhead">The controls</p>
            <ul>
                <li><strong>Class</strong> &mdash; a display control only: it narrows the species list and does not restrict the query. The species selector is what is submitted.</li>
                <li><strong>Species</strong> &mdash; the single query key. It accepts the Latin name, the underscore form or the five-letter code, and the selector covers the <strong>148 species with a deposited protein set</strong>.</li>
                <li><strong>View BUSCO Details</strong> opens the hit list in a new tab. On that page the results are paged at 20 rows by default, with 10, 20, 50 and 100 offered and a <em>Go to page</em> box; a species can contribute several thousand hits, so the page size is worth raising before reading a large species.</li>
                <li><strong>Not available</strong> &mdash; there is no export or download on either page, and no way to search the hit list by gene ID or description.</li>
            </ul>
            <p class="manual-subhead">The two tables</p>
            <ul>
                <li><strong>The comparison table</strong> has <strong>one row per species</strong> and ranks genomes against each other. Columns: <em>Species, Class, Lineage, C (%), S (%), D (%), F (%), M (%), Quality</em> and a link into that species' details. <em>C</em> is complete (single-copy + duplicated), <em>S</em> single-copy, <em>D</em> duplicated, <em>F</em> fragmented and <em>M</em> missing, each as a percentage of the 3,203 lineage BUSCOs.</li>
                <li><strong>The hit list</strong> has one row per BUSCO match for the chosen species. Columns: <em>BUSCO ID, Status, Score, Length, Gene ID, Description</em> and a <em>Visualization</em> link into the genome browser; the status is shown as a coloured badge for <em>Complete</em>, <em>Duplicated</em> or <em>Fragmented</em>. Above it, the species' own completeness panel repeats C, S, D, F and M as a stacked bar.</li>
                <li>Where a species has no gene annotation, there is no gene model to link to and no browser track to open: the <em>Gene ID</em> cell is then plain text &mdash; either a gene name that cannot be followed, or the genomic location of the match, for example <code>OZ480266.1:12414812-12440356(+)</code> &mdash; and <em>Visualization</em> shows a dash with a tooltip explaining why.</li>
            </ul>
            <div class="manual-note"><strong>The hit list is not a completeness measure.</strong> Its counter reads &ldquo;BUSCO hits recorded for <i>species</i>: N (one row per gene hit, not per BUSCO)&rdquo;, and the qualification matters: one BUSCO can be matched by several gene models, so a species with 4,837 hits does not have 4,837 of the 3,203 BUSCOs. Read completeness from the <em>percentage</em> panel and the comparison table, never from the hit count. Two further differences between the two views are easy to trip over: the panel's <em>C</em> is single-copy <em>plus</em> duplicated, while the table's <em>Complete</em> badge means single-copy only; and <em>Missing</em> can never appear as a row in the hit list, because a BUSCO with no match has nothing to list &mdash; even though the panel reports an <em>M</em> percentage. The <em>Length</em> column also does not hold the protein length its name suggests.</div>
            <div class="manual-note"><strong>Which species are in which view.</strong> Only species scored against a <em>deposited protein set</em> enter the comparison table and the selector &mdash; that is what the <em>assessed</em> count refers to. That is now <strong>148</strong> species: all <strong>145</strong> that have a gene-model table here, plus the three that carry a deposited protein set but no gene-model table of their own. It is still far fewer than the 326 species in the catalogue, because a species with no annotation at all has nothing to score: one of those, <i>Pachycerianthus multiplicatus</i>, was nevertheless assessed in <em>genome mode</em>, from genes predicted during the assessment, and is kept out of the table because its figure is an optimistic upper bound taken on a different ruler. Every species that has a protein set is now assessed, so the 148 species with a deposited protein set and the 148 rows in the table are the same set. The last two, <i>Alatina alata</i> (C&nbsp;=&nbsp;6.4&nbsp;%) and <i>Calvadosia cruxmelitensis</i> (C&nbsp;=&nbsp;40.5&nbsp;%), were scored on 2026-09-23. An explanatory box on the result page covers each of these three cases: the genome-mode assessment above; nine species re-run on 2026-09-18 after a redundant protein set was corrected; and four Myxozoa added on 2026-09-23 whose low completeness (C = 3.6&ndash;7.6&nbsp;%) reflects the extreme genome reduction of that group rather than an assembly problem. Each box states whether the figures are comparable with the rest. Completeness for species absent from this module is reported on their <a href="#species-portal">Species Portal</a> page instead.</div>
            <div class="manual-tip"><strong>Watch the species you think you are looking at.</strong> <i>Pachycerianthus multiplicatus</i> is the one species handled inconsistently: it is absent from both the selector and the comparison table, so you cannot browse to it. Reach its entry page some other way and the page says outright that the module has no data for that species and shows a different one instead. Its own result page is complete, however, and is reachable directly at <code>busco_result.php?species=PMULT</code> &mdash; the assessment there was scored in genome mode, which is why it is kept out of the comparison. The entry page opens on <i>Nematostella vectensis</i>, so check the species named above the table before reading a result you did not navigate to yourself.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.7</span>
                    <img src="./manual_images/busco_1.png" alt="BUSCO species selector" data-caption="BUSCO module: select class and species to view BUSCO genes." loading="lazy" decoding="async" />
                    <figcaption>Select a class and species to view its BUSCO genes.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.8</span>
                    <img src="./manual_images/busco_2.png" alt="BUSCO gene details table" data-caption="BUSCO gene details: BUSCO ID, status, score, length, gene ID and JBrowse link." loading="lazy" decoding="async" />
                    <figcaption>BUSCO gene details table with status, score, length, gene ID and JBrowse visualization.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="te" data-title="transposable elements TE transposon retrotransposon LTR Copia Gypsy LINE SINE DNA transposon subfamily TE type copy number insertion locus scaffold start end related gene intergenic region gene region intron exon promoter UTR gene body genomic region interval search JBrowse Hexacorallia Scyphozoa Myxozoa Actinernus Actinostola Alvinactis idsseensis Nematostella Montipora Siderastrea Aurelia Acropora">
            <h3>3.3 Transposable Elements</h3>
            <p><strong>What it does.</strong> Lists the transposable elements (TEs) that sit in or beside protein-coding genes — one row per TE copy, with its scaffold coordinates, the gene it is associated with, where the copy sits relative to the gene model, and its TE type/subfamily — and provides a second search that turns the question around and lists everything inside a genomic interval. The module covers <strong>18 species</strong> — 11 Hexacorallia, 4 Scyphozoa and 3 Myxozoa — holding <strong>10,971,397</strong> TE copies between them, from 2,286 in <i>Thelohanellus kitauei</i> to 2,597,421 in <em>Actinernus</em> sp. WN-2022; the full list is in the note below. TE calls are not available for the other 308 species in the catalogue, and the species selector lists exactly these 18.</p>
            <p><strong>Why you would use it.</strong> To check whether a gene of interest has a TE insertion near it, which is the usual first question when a gene family has expanded (see <a href="#gene-family">3.6 Gene Family</a> and <a href="#pangenome">3.7 Pan-geneset</a>) or when two copies of a duplicated gene behave differently. The region mode answers the complementary question — what is lying around this locus — for an interval you took from the genome browser or from <a href="#genomic">2.3 Genomic Data</a>. Every row carries a JBrowse link, so a candidate can be inspected in its genomic context without leaving the site.</p>
            <p class="manual-subhead">The controls</p>
            <p>Both forms sit on the same page and are submitted independently; each opens its result in a new tab.</p>
            <ol>
                <li><strong>Search TEs by Gene or TE Type</strong> — <em>Species</em> chooses the dataset; <em>Gene/TE Type</em> is matched against <strong>both</strong> the related gene ID and the TE type at once, so one box serves both kinds of query. Every count is a count <em>within the selected species</em>, and the same term against another species gives a different number. It accepts an exact gene ID (<code>Acti_000001-T1</code> returns 115 rows in <em>Actinernus</em> sp. WN-2022) and it accepts a type as a <em>substring</em>: in that same species <code>LTR_retrotransposon</code> returns 884,122 rows, of which the narrower <code>Copia_LTR_retrotransposon</code> accounts for 8,396. A partial type matches everything containing it, and there is no switch to ask for a gene only or a type only. The form's own example, <code>gene-LOC125560710</code> in <em>Nematostella vectensis</em>, is the most TE-laden gene of that species and returns 1,884 rows.</li>
                <li><strong>Search TEs by Genomic Region</strong> — <em>Species</em> plus <em>Genome Region</em> in the form <code>Scaffold:start-end</code>. The interval is used as given: a ten-megabase window over scaffold <code>ScLC4GM_1</code> in <em>Actinernus</em> sp. WN-2022 (<code>ScLC4GM_1:1000-10000000</code>, the search shown in Fig. 3.11) returns 350 rows, and the region form's own example, <code>NC_064038.1:1000-10000000</code> in <em>Nematostella vectensis</em>, returns 9,287. The same window against a species that has no TE table returns 0 rather than an error, so a species the module does not cover looks the same as a region with no TEs. And the test is the same start-or-end test as the region box of the <a href="#gene-search">Gene Search</a> (3.1), so a copy that spans the whole window is not returned.</li>
            </ol>
            <p>Both forms also carry a <em>Class</em> drop-down. It is not a query parameter — the query is built from the species alone — but on this page it does reach the result, because the 18 covered species span three classes. Choosing a class hides the species of the other two, and if the species you had selected is one of them, the page quietly moves the selection to the first species of the class you picked. Treat the class box as a coarse species selector here, not as decoration.</p>
            <p class="manual-subhead">The results table</p>
            <p>Columns are the same in both modes: <code>Species</code>, <code>TE ID</code>, <code>Scaffold</code>, <code>Start</code>, <code>End</code>, <code>Related Gene</code>, <code>Distribution</code>, <code>TE Type</code>, <code>JBrowse</code>. A card above the table repeats the species and the search term and reports the hit count as <code>N TE(s) found</code>.</p>
            <ul>
                <li><code>Distribution</code> is not a score: it is the stored region column and says where the copy sits relative to the gene model. It is the quickest way to separate insertions that interrupt a gene from those that merely sit near one — but how much it can tell you depends on the species, because the value set is not the same everywhere (see the note on it below).</li>
                <li><code>Related Gene</code> links to that gene's detail page, <code>Species</code> links to the species info page, and <code>JBrowse</code> opens the local genome browser centred on the TE copy.</li>
                <li>Paging is 10 rows by default, with 10 / 20 / 50 / 100 offered; a value outside that list quietly falls back to 10. First / Previous / numbered / Next / Last are provided along with a "Go to page" box.</li>
                <li>Both result pages are addressable, so a search can be bookmarked or cited: <code>TE_gene.php?species=&amp;gene=&amp;per_page=&amp;page=</code> and <code>TE_region.php?species1=&amp;position=&amp;per_page=&amp;page=</code>. Note that the region form's species parameter is <code>species1</code>, not <code>species</code> — the two forms do not share a parameter name.</li>
            </ul>
            <div class="manual-note"><strong>The <em>Distribution</em> vocabulary is not the same in every species.</strong> Fifteen of the 18 species label the position finely — <em>intergenic region</em>, <em>intron</em>, <em>exon</em>, <em>promoter</em>, <em>gene body</em>, <em>5&#8242; UTR</em>, <em>3&#8242; UTR</em> — and the other three, <i>Actinernus</i> sp. WN-2022, <i>Actinostola</i> sp. cb2023 and <i>Alvinactis idsseensis</i> sp. nov., carry only the coarse pair <em>gene region</em> / <em>intergenic region</em>, so for those three the column can say that a copy interrupts a gene but not which part of it. Two of the fifteen (<i>Acropora palmata</i> and <i>Montipora grisea</i>) store the finer values with a redundant &ldquo;region&rdquo; (<em>intron region</em>); the table shortens those to <em>intron</em> and keeps the stored string in the cell's tooltip, so the same value is not shown as two different ones. Sorting, filtering and the underlying data all use the stored string unchanged.</div>
            <div class="manual-note"><strong>The 18 species with TE calls, and how many copies each holds.</strong> Hexacorallia — <i>Actinernus</i> sp. WN-2022 2,597,421; <i>Montipora grisea</i> 1,581,725; <i>Siderastrea siderea</i> 1,074,455; <i>Echinopora horrida</i> 897,543; <i>Actinoscyphia liui</i> 860,650; <i>Actinostola</i> sp. cb2023 587,994; <i>Alvinactis idsseensis</i> sp. nov. 580,258; <i>Acropora acuminata</i> 483,185; <i>Condylactis gigantea</i> 347,121; <i>Acropora palmata</i> 334,080; <i>Nematostella vectensis</i> 214,494. Scyphozoa — <i>Aurelia coerulea</i> 1,010,892; <i>Sanderia malayensis</i> 141,455; <i>Catostylus mosaicus</i> 124,296; <i>Nemopilema nomurai</i> 76,253. Myxozoa — <i>Henneguya salminicola</i> 37,183; <i>Myxobolus squamalis</i> 20,106; <i>Thelohanellus kitauei</i> 2,286. Counts are rows in the species' TE table, so they include the copies that carry no related gene. More species are added as the TE annotation pipeline reaches them, so this list grows between releases.</div>
            <div class="manual-tip"><strong>A species code in the URL fails silently.</strong> Both result pages are addressable, but they look the species up by its Latin name. A URL built with the code instead (<code>species=ASP1</code>) does not raise an error — the table lookup finds nothing and the page reports the ordinary &ldquo;No Transposable Elements Found&rdquo;, which is indistinguishable from a genuine zero-hit search. Always write the Latin name when you build a result URL by hand. Both result pages name the species in full Latin form; the gene/TE-type page used to print the internal <em>Actinernus_sp._WN-2022</em> form, which is fixed.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.9</span>
                    <img src="./manual_images/te_1.png" alt="Transposable elements search forms: by gene ID or TE type, and by genomic region" data-caption="Transposable Elements module: the two search forms — by gene/TE type, and by genomic region — each with a Class, Species and query box." loading="lazy" decoding="async" />
                    <figcaption>The two TE search forms. The gene/TE-type box matches a gene ID and a type substring with the same field; the region form takes a <code>Scaffold:start-end</code> interval.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.10</span>
                    <img src="./manual_images/te_2.png" alt="TE search result by gene, showing TE ID, scaffold coordinates, related gene, distribution and TE type" data-caption="TE results for a gene or TE type: one row per TE copy with locus, related gene, distribution (gene region / intergenic region) and TE type." loading="lazy" decoding="async" />
                    <figcaption>A gene/TE-type search. Each copy has its own row; <code>Distribution</code> distinguishes insertions inside a gene from intergenic ones.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.11</span>
                    <img src="./manual_images/te_3.png" alt="TE search result for a genomic region interval" data-caption="TE results for a genomic region: everything annotated inside the given Scaffold:start-end interval." loading="lazy" decoding="async" />
                    <figcaption>A genomic-region search, listing every annotated TE inside the interval.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="tfs" data-title="TFs Ubs transcription factors ubiquitin ubiquitin-like gene family AnimalTFDB iUUCD family member statistics E-value score subfamily DNA binding domain DBD HECT RING U-box Cullin F-box tree classification">
            <h3>3.4 TFs/Ubs</h3>
            <p><strong>What it does.</strong> Browses the transcription-factor and the ubiquitin / ubiquitin-like gene families of a single species, and then the members of one family of your choosing. Both family systems live behind one form: <em>Transcription Factors Family</em> (the default) and <em>Ubiquitin Family</em>. Transcription factors are classified from their conserved DNA-binding domains following AnimalTFDB 4.0 with self-built HMM profiles; ubiquitin families follow iUUCD. The module is backed by 96,854 TF assignments and 306,859 ubiquitin assignments, covering <strong>149 species</strong> across 7 classes — Hexacorallia 99, Hydrozoa 14, Octocorallia 14, Scyphozoa 13, Myxozoa 4, Cubozoa 3 and Staurozoa 2.</p>
            <p><strong>Why you would use it.</strong> To start from a family rather than from a gene: choose a species and a family system, pick the family from the classification, and read its member list, where every gene links on to its full annotation page. This is also the fastest way to compare family sizes between species, since each family is labelled with its member count for the species you selected. It complements <a href="#te">3.3 Transposable Elements</a> (which genes sit near a TE) and <a href="#protein-domain">3.5 Protein Domain</a> (which domains one gene carries) with the family-level view; the phylum-wide counterpart of the same problem is <a href="#gene-family">3.6 Gene Family</a>.</p>
            <p class="manual-subhead">The controls</p>
            <ol>
                <li><strong>Class</strong> — narrows the species list in the browser only. It is not sent to the query and cannot affect a result &mdash; the same pattern as the Class drop-down in the other gene-list modules, though not in the Taxonomy module (2.1), where the class filter does reach the query.</li>
                <li><strong>Species</strong> — really chooses the dataset. The drop-down offers every species the module has data for; the note below gives the counts.</li>
                <li><strong>Gene Family</strong> — <em>Transcription Factors Family</em> (preselected) or <em>Ubiquitin Family</em>. This switches both the classification shown first and the columns of the member table.</li>
            </ol>
            <p class="manual-subhead">What comes back</p>
            <p>The first result page carries no table; what it renders depends on the family system.</p>
            <ul>
                <li><strong>Ubiquitin Family</strong> shows the classification itself as an expandable tree rooted at "All ubiquitin families", covering the enzymatic tiers E1 / E2 / E3 / DUB and the adaptor, UBD, ULD and UFD branches beneath them. The tree is hand-written rather than derived from the data, which leaves two quirks: <code>UBC</code> and <code>UEV</code> each appear in two places (under E2 and again under UBD → UBC-like), and the family <code>UMI</code>, which does have member genes, has no branch and therefore cannot be reached from the tree at all.</li>
                <li><strong>Transcription Factors</strong> shows a link grid, five families per row, each cell reading <code>Family(count)</code> with the number of member genes in that species. <em>Nematostella vectensis</em>, for example, has 9 TF families and 222 member rows in total.</li>
            </ul>
            <p>The member table that follows has different columns for the two systems:</p>
            <ul>
                <li><strong>Ubiquitin</strong> — <code>Gene</code>, <code>Family</code>, <code>Subfamily</code>, <code>E-value</code>, <code>Score</code>, <code>Description</code>, <code>Method</code>, <code>JBrowse</code>. <code>Method</code> links to iUUCD, the source of the assignments; <code>Gene</code> links to the gene page.</li>
                <li><strong>Transcription factor</strong> — <code>Gene</code>, <code>Pfam/Self-build</code>, <code>DNA Binding Domain</code>, <code>Full name</code>, <code>Description</code>, <code>JBrowse</code>. The <code>Pfam/Self-build</code> cell links to the InterPro entry when the profile is a Pfam accession; self-built profiles are plain text because no public entry exists for them.</li>
            </ul>
            <p>A breadcrumb above the table shows the path you took (<code>Gene Family &rsaquo; TF family &rsaquo; {family}</code>). <strong>There is no pagination anywhere in this module</strong> — every family link and every member row is rendered, so a large ubiquitin family is a single long page. The pages are plain links, so <code>family_member.php?species=&amp;family=tf|ubs</code> and <code>family_member_detail.php?gene_family=tf|ubs&amp;species=&amp;family_id=</code> can be shared directly.</p>
            <div class="manual-note"><strong>Which species have which family system.</strong> The selector lists the <strong>149</strong> species that have data in this module, and the count in the introduction above the form is that same list, read from the database rather than typed in. The two family systems do not cover exactly the same set: <strong>148</strong> species have transcription-factor assignments, <strong>148</strong> have ubiquitin assignments, and <strong>147</strong> have both. <i>Taxipathes</i> sp. SY275-mao has transcription factors only, and <i>Trachythela</i> sp. YZ-2020 has ubiquitins only — so choosing that species together with <em>Transcription Factors Family</em> returns an empty table, and the table now says so rather than showing a bare header row.</div>
            <div class="manual-tip">An empty result here explains itself. A family table that shows a message instead of rows means one of two things: the species has no data for the family system you chose (see the note above), or the <code>family_id</code> does not match exactly — the identifiers are case-sensitive, e.g. <code>TF_bZIP</code>, <code>GCNF-like</code>, <code>DACH</code>, <code>ESR-like</code>. The family list that precedes this page behaves the same way: a species with nothing of that kind gets a sentence rather than a blank table, and a missing or unrecognised <code>gene_family</code> value lands on a page that offers the two systems to choose from. Also, for the three species that have annotations but no genome-browser index (<em>Alatina alata</em>, <em>Calvadosia cruxmelitensis</em>, <em>Cassiopea xamachana</em>) <code>JBrowse</code> appears as plain grey text instead of a link.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.12</span>
                    <img src="./manual_images/tf_1.png" alt="TFs/Ubs gene family search form: choose a class, a species and the family system (transcription factors or ubiquitin)" data-caption="TFs/Ubs entry form — Class narrows the species list in the browser only; Species and Gene Family are the two controls that reach the query." loading="lazy" decoding="async" />
                    <figcaption>The entry form. Only <em>Species</em> and <em>Gene Family</em> are sent to the query; <em>Class</em> merely filters the species list in the browser.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.13</span>
                    <img src="./manual_images/tf_2.png" alt="Statistics of the different gene family types with their member counts" data-caption="The classification of the selected family system — an expandable ubiquitin tree or a transcription-factor family grid — with the member count for the chosen species." loading="lazy" decoding="async" />
                    <figcaption>The first result: the family classification with per-family member counts for the selected species. Ubiquitin families come back as a tree, transcription factors as a link grid.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.14</span>
                    <img src="./manual_images/tf_3.png" alt="Family member information for one gene family: gene, family, subfamily, E-value, score, description, method and JBrowse" data-caption="Member information for one family. Ubiquitin members are listed by family/subfamily with E-value and score; TF members by Pfam or self-built profile and DNA-binding domain." loading="lazy" decoding="async" />
                    <figcaption>The member table for one family. Its columns depend on the family system you chose, and every gene links on to its full annotation page.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="protein-domain" data-title="protein domain Pfam gene list paste gene identifier accession Pfam name description type domain repeat one species at a time functional domain search cross-reference">
            <h3>3.5 Protein Domain</h3>
            <p><strong>What it does.</strong> Returns the Pfam domains of a list of genes within one species. You paste gene identifiers, choose the species, and get one row per gene–domain pair with the Pfam accession, the domain name, its description and whether the match is a domain or a repeat. The source is the per-species Pfam annotation table: <strong>7,135,337 rows across 148 species</strong>, every one of them populated. The species drop-down is built from the annotation tables that actually exist, so it lists exactly the 148 species that can answer; a species outside that list is refused with an explicit banner rather than silently returning nothing.</p>
            <p><strong>Why you would use it.</strong> To annotate a shortlist: take the genes from <a href="#gene-search">3.1 Gene Search</a>, or a family from <a href="#tfs">3.4 TFs/Ubs</a>, and ask what their products are. If the question runs the other way — "which of the annotated genomes contain domain X" — do not loop over species here; use <a href="#domain-search">3.8 Functional Domain Search</a>, which searches all of them in one query. The landing page links straight to it.</p>
            <p class="manual-subhead">The controls</p>
            <ol>
                <li><strong>Select Class</strong> — narrows the species list in the browser only; it is not sent to the query.</li>
                <li><strong>Select Species</strong> — the species whose annotation table is searched. Exactly one at a time.</li>
                <li><strong>Gene List</strong> — free text. Identifiers may be separated by newlines, spaces, tabs or commas; the input is split on any run of those and the pieces are de-duplicated, so a comma-joined list works exactly as the placeholder says it does: <code>XP_048581300.1,XP_048581301.1</code> returns the same rows as the two IDs on separate lines.</li>
            </ol>
            <p class="manual-subhead">The results table</p>
            <p>Columns: <code>Gene</code>, <code>Pfam accession</code>, <code>Pfam name</code>, <code>Description</code>, <code>Type</code> and <code>JBrowse</code>. A summary line above the table reads <code>Total protein-domain annotations: {M} &mdash; from {X} of {N} submitted gene IDs</code>: <strong>M counts the domain rows returned</strong> (one gene&ndash;domain pair each), and <strong>X is how many of the N identifiers you submitted were actually found</strong> in that species' annotation table. The two numbers measure different things, and when X is smaller than N the missing ones are identifiers that do not exist in the selected species &mdash; not genes that lack domains. <code>Gene</code> links to the gene page, the accession links to the InterPro entry for that Pfam family, and <code>JBrowse</code> opens the genome browser at the gene. Paging is 20 rows by default with 10 / 20 / 50 / 100 available, and a result can be shared as a URL: <code>proteindomain_result.php?species=&amp;genelist=&amp;per_page=&amp;page=</code>.</p>
            <div class="manual-note">Matching is exact and per species: identifiers are compared exactly as stored, so a list of <em>Nematostella</em> accessions pasted while a different species is selected returns 0 rows with no hint about the cause — which is why the "Example" link sets the species selector to <i>Nematostella vectensis</i> as well as filling the text area. Change the species after using it and the pasted list no longer matches the query. The empty result message hedges ("this may be because the species has no Pfam annotation, or because none of the submitted genes carries a Pfam domain"), but only the second case can occur here: all 148 selectable species have annotation, and one without it never reaches the form.</div>
            <div class="manual-tip">For three of the 148 species — <em>Alatina alata</em>, <em>Calvadosia cruxmelitensis</em> and <em>Cassiopea xamachana</em> — the domain annotation exists but no gene-coordinate index does. The <code>JBrowse</code> link is still rendered, with an empty position parameter, and opens the browser without a location. The domains in the table are unaffected; use the gene page instead for those three.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.15</span>
                    <img src="./manual_images/protein_domain_1.png" alt="Protein domain search form: select a class and a species, then paste a list of gene identifiers" data-caption="Protein Domain entry form — one species at a time plus a pasted gene list. Identifiers may be separated by newlines, spaces, tabs or commas." loading="lazy" decoding="async" />
                    <figcaption>The entry form. One species at a time; paste the gene list with the identifiers separated by newlines, spaces, tabs or commas.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.16</span>
                    <img src="./manual_images/protein_domain_2.png" alt="Protein domain results: Pfam accession, Pfam name, description and type for each gene" data-caption="Protein Domain results — one row per gene–domain pair with Pfam accession, name, description and type, and a summary of how many domains were found for how many genes." loading="lazy" decoding="async" />
                    <figcaption>The result table. The summary gives the domain rows returned and how many of the submitted identifiers were found, so the two numbers measure different things.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="gene-family" data-title="gene family orthogroup OrthoFinder consensus annotation PANTHER GO KEGG Pfam support consistency tier 100 80 50 members species filter orthogroup browser NCBI-NR UniProt">
            <h3>3.6 Gene Family</h3>
            <p><strong>What it does.</strong> Browses the <strong>67,795 orthogroups</strong> inferred by OrthoFinder across the cnidarian proteomes, and reports for each one the function that its member genes agree on. Every Pfam domain, PANTHER family, GO term and KEGG orthology entry carried by the members is counted and reported with the fraction of members that carry it, in three consensus tiers: <strong>100%</strong> (every member agrees — the strict reading), <strong>80%</strong> and <strong>50%</strong>. That yields 114,427 consensus terms — GO 62,086, Pfam 27,205, PANTHER 19,143 and KEGG 5,993 — distributed over <strong>23,302 annotated families</strong>; the remaining 44,493 families have no consensus annotation and stay hidden unless you ask for them.</p>
            <p><strong>Why you would use it.</strong> To put an identity on a gene that nothing else describes: where a gene product has no informative name but its whole orthogroup agrees on a domain, the family's consensus is the strongest evidence available, and this is the only page that reports it with an explicit support fraction. It is also the natural way to move between a family and its genes across species — every member row leads to the gene page — and the species filter on a family's member list answers "which lineages have this family at all".</p>
            <p class="manual-subhead">The controls</p>
            <ol>
                <li><strong>Search box</strong> — matches the family ID, the term accession and the term name, so <code>PF00069</code> (260 families), <code>GO:0006355</code> (499) and <code>OG0000001</code> (1) all work. The description text of a term is <em>not</em> indexed, so a phrase that appears only in a description can miss families whose displayed text contains it. One caveat on the page's own example: <code>K05111</code> is quoted in the introduction but returns 0 families, because that accession is not in the dataset — real KEGG accessions such as <code>K02183</code> return 14.</li>
                <li><strong>Show</strong> — <em>families with a consensus annotation</em> (default, 23,302) or <em>all families</em> (67,795). Two of the three largest families in the dataset, including the largest (OG0000000, 35,546 genes in 144 species), are unannotated, which is why the default listing begins at OG0000001.</li>
                <li><strong>Sort by</strong> — family ID, annotation consistency or family size. Family size correlates strongly with the family ID in this dataset, so the first pages of the "size" ordering look identical to the ID ordering; the two only diverge deep into the list.</li>
                <li><strong>Per page</strong> — 10 / 20 / 50 / 100, default 20. Unlike the other modules the value is not checked against that list, and a larger number is honoured.</li>
            </ol>
            <p class="manual-subhead">The results tables</p>
            <p>The browser lists <code>Gene family</code>, <code>Genes</code>, <code>Species</code> and the <code>Consensus function</code> the family's members agree on, with a line above reporting how many families the current view contains.</p>
            <p>Opening a family gives two tables. The first is the consensus annotation: <code>Source</code>, <code>Term</code>, <code>Name / description</code>, <code>Support</code>, <code>% of genes</code>, <code>% annotated</code>, <code>Consistency</code>. Here <code>Support</code> is the number of member genes carrying the term; <code>% of genes</code> is that count over <em>all</em> members — the strict reading of "the whole family agrees"; and <code>% annotated</code> is the same count over only those members for which that kind of prediction exists, which is the fairer measure when annotation is uneven. The second table lists the members: <code>Species</code>, <code>Gene ID</code>, the top NCBI-NR hit (accession, plus description and source organism) and — only when at least one member of that family has one — the top UniProt/Swiss-Prot hit. Both hit columns are the closest characterised sequences found by homology search, <strong>not</strong> identifiers of the CnidoSite gene. The member list can be filtered to a single species, and every row links through to the gene page and the genome browser.</p>
            <div class="manual-note"><strong>One entry in the member table is not a species.</strong> The OrthoFinder run included a ctenophore outgroup, imported under the label <code>OUT</code>: 85,455 genes in 9,452 families, rendered in the <code>Species</code> column as the literal string "OUT" and linking to an empty species page. It is counted in every family's species total as well, so the largest families report up to 149 species where 148 are cnidarians. Read <code>OUT</code> as the outgroup, not as a species in the catalogue.</div>
            <div class="manual-tip">Gene and sequence counts differ slightly, and the page explains why: two source proteomes, <em>Alatina alata</em> and <em>Calvadosia cruxmelitensis</em>, list one gene identifier on several sequences. Those extra sequences are shown separately and are not counted twice towards the consensus, because the annotation is stored per gene identifier — 109 families are affected, 1,362 sequences in all. There is no export from this module: the download button the page's own script looks for was never rendered. To take the data away, use <a href="#pangenome">3.7 Pan-geneset</a> or the <a href="download.php">Download</a> module.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.17</span>
                    <img src="./manual_images/gene_family_1.png" alt="Gene family browser: family ID, gene count, species count and the consensus function agreed on by the family's members" data-caption="The orthogroup browser. The default view lists only the 23,302 families that carry a consensus annotation; two of the three largest families in the dataset are unannotated — the largest and the third largest — and appear only under Show: all families." loading="lazy" decoding="async" />
                    <figcaption>The orthogroup browser. Each row is one family with its size, its species count and the function its members agree on.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.18</span>
                    <img src="./manual_images/gene_family_2.png" alt="Detailed member information within one gene family: consensus annotation table and the member list with NCBI-NR and UniProt hits" data-caption="One family in detail — the consensus annotation with support and consistency, then the member list with the closest NCBI-NR and UniProt hits and a genome-browser link per gene." loading="lazy" decoding="async" />
                    <figcaption>One family in detail: the consensus annotation table above, the member list below. The hit columns are homologous sequences found elsewhere, not identifiers of the CnidoSite genes.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="pangenome" data-title="pan-geneset pangenome phylogenetic tree gene family membership expansion contraction orthogroup Count toolkit OrthoFinder download">
            <h3>3.7 Pan-geneset</h3>
            <p><strong>What it does.</strong> Presents the pan-genome analysis of the phylum: a phylogenetic tree of the species in the analysis, annotated with the gene family gains, expansions and losses inferred along each branch, and a single downloadable text file holding the whole result. Gene families are counted with the <a href="https://www.iro.umontreal.ca/~csuros/gene_content/count.html" target="_blank" rel="noopener">Count</a> toolkit, and the tree is built from the single-copy genes identified by OrthoFinder.</p>
            <p><strong>Why you would use it.</strong> To ask questions about gene family evolution rather than about individual genes &mdash; which families expanded in a lineage, how many genes were gained or lost on the branch leading to a group, or which orthogroup a gene belongs to across all species. The <a href="#gene-family">Gene Family</a> module (3.6) browses the same orthogroups one family at a time, with the consensus function of each family's members; this module is the phylum-wide view, and it is the only place the gain/loss figures are available.</p>
            <p class="manual-subhead">The controls</p>
            <ul>
                <li><strong>Download</strong> &mdash; the only control on the page. It fetches <code>Pan-geneset_familydata.txt</code>, a tab-separated text file of about <strong>64&nbsp;MB</strong> (63,902,806 bytes) that contains no sequence data; it is meant for local analysis rather than for reading in the browser.</li>
                <li><strong>The tree figure</strong> is a static image, not an interactive browser: you cannot pan, zoom, click a node or look up a branch. To read a numerical value off the tree, use the file.</li>
            </ul>
            <div class="manual-note"><strong>This page shows nothing else, and it does not query the database.</strong> The module is a fixed figure plus a download &mdash; there is no species selector, no search box, no filter, no result table and no page state to remember. Everything the module contains is inside the downloaded file.</div>
            <div class="manual-note"><strong>Reading the downloaded file.</strong> Every one of its 104,416 lines begins with a <code>#</code> character, and it holds three different blocks. The first is gene family membership: 6 <code>#|</code> provenance records written by the Count toolkit (tool version, host, command line and arguments), then a <code># FAMILY</code> header line naming the taxon columns, then one <code># FAMILY</code> line per orthogroup &mdash; <strong>103,819</strong> of them. The second is a per-taxon summary that is easy to miss because it sits between the two blocks you are expecting: a <code># PRESENT</code> header (<code>node, genes, families, single-member families</code>) and one <code># PRESENT</code> line for each of the 294 taxa, giving that taxon&rsquo;s gene count, its family count and how many of those families have a single member. The third is the tree analysis: a <code># CHANGE</code> header, then one line per internal node and one per species giving gene and family gains, duplications, expansions and losses, ending in a <code># CHANGE&nbsp;total</code> line for the whole tree. Many parsers therefore read the entire file as comments and return an empty table:
                <ul>
                    <li>R: <code>read.delim(&quot;Pan-geneset_familydata.txt&quot;, comment.char = &quot;&quot;)</code> &mdash; an empty <code>comment.char</code>, not the default. A space there is not the same thing: it would make R cut each line at its first space, and this file separates columns with tabs but writes species names with spaces inside them.</li>
                    <li>pandas: <code>pd.read_csv(..., sep=&quot;\t&quot;, comment=&quot;#&quot;)</code> drops the leading <code>#</code> and gives a usable table; keep the <code>#|</code> provenance lines separately if you need to cite the run.</li>
                    <li>Command line: <code>grep -v '^#|'</code> keeps the header and data lines and drops only the provenance block.</li>
                </ul>
                There is no CSV version of this file.</div>
            <div class="manual-tip"><strong>The blocks do not all cover the same taxa.</strong> The <code># FAMILY</code> membership matrix has <strong>294</strong> taxon columns &mdash; the 148 species plus 146 internal nodes &mdash; and the <code># PRESENT</code> block carries a row for each of those same 294 taxa, so those two join directly. The <code># CHANGE</code> block is the one that differs: it has rows for the 148 species and 145 internal nodes, one node fewer than the other two. A taxon can therefore appear as a column of the membership matrix without having a gain/loss row, so do not cross-reference the blocks as if they covered the same set without checking first. The <code># CHANGE total</code> line reports 427,699 gene gains, 127,306 family gains, 652,572 gene duplications, 257,120 family expansions and 103,819 families for the analysed tree.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.19</span>
                    <img src="./manual_images/pangenome.png" alt="Pan-geneset" data-caption="Pan-geneset module with the species tree and gene family gain/expansion/contraction results." loading="lazy" decoding="async" />
                    <figcaption>Pan-geneset module with the species tree annotated with gene family gain, expansion and contraction results.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="domain-search" data-title="functional domain search InterPro Pfam KEGG GO PANTHER accession description across all genomes chip truncation 500 wildcard">
            <h3>3.8 Functional Domain Search</h3>
            <p><strong>What it does.</strong> The modules above answer &ldquo;what is annotated on <em>this</em> gene?&rdquo;. Functional Domain Search answers the opposite question: <strong>which genes in CnidoSite carry signature X?</strong> It searches the annotation tables of every annotated genome at once &mdash; <strong>148 genomes</strong>, the same set for all five vocabularies &mdash; so it works without knowing a gene ID, and it is the only module that can start from a domain.</p>
            <p><strong>Why you would use it.</strong> To find the members of a protein family across the phylum when all you have is an accession or a family name; to check whether a signature you are interested in is present in cnidarians at all, and in which classes; or to collect a cross-species gene set for a domain before comparing it with your own sequences. If instead you already have a gene list and want its annotations, use <a href="#protein-domain">Protein Domain</a> (3.5) or the per-vocabulary <a href="#go">GO</a> (3.9), <a href="#interpro">InterPro</a> (3.10) and <a href="#kegg">KEGG</a> (3.11) modules, which are organised per gene.</p>
            <p class="manual-subhead">The controls</p>
            <ol>
                <li><strong>The five tabs</strong> choose the annotation source: <em>InterPro</em>, <em>Pfam</em>, <em>KEGG pathway</em>, <em>Gene Ontology</em> or <em>PANTHER</em>. Switching tab keeps your search term but <em>drops the species restriction</em>, so re-select the species if you were working in one genome.</li>
                <li><strong>The search box</strong> takes either an accession or free text. Identifiers are matched <strong>exactly</strong> and descriptions <strong>partially</strong> (case-insensitively), so <code>IPR016024</code>, <code>PF12848</code>, <code>K13752</code>, <code>GO:0005615</code>, <code>PTHR19143</code>, <i>kinase</i>, <i>Armadillo</i> and <i>homeobox</i> all work. Per vocabulary the accepted forms are: InterPro term; Pfam accession or domain name; KEGG orthology ID <em>or</em> pathway map ID such as <code>ko02000</code>; GO term; PANTHER family accession. The four <em>Examples</em> chips fill the box for you.</li>
                <li><strong>The species selector</strong> narrows the search to one of the 148 annotated genomes, or leaves it at <em>All species</em>. Its value is the five-letter code, so <code>sp=NVECT</code> in the URL reproduces the restriction.</li>
                <li><strong>Search</strong> submits. All three values are carried in the URL (<code>db</code>, <code>q</code>, <code>sp</code>), so any result is bookmarkable and citable, and the query time is printed after every search.</li>
            </ol>
            <p class="manual-subhead">The results table</p>
            <ul>
                <li><strong>Species</strong> &mdash; the Latin name, linked to that species' portal.</li>
                <li><strong>Gene ID</strong> &mdash; linked to the gene page in the same genome.</li>
                <li><strong>&lt;vocabulary&gt; term</strong> &mdash; the InterPro / Pfam / KEGG / GO / PANTHER identifier, linked to the external database (InterPro, KEGG, AmiGO or PANTHER).</li>
                <li><strong>Match</strong> &mdash; the annotation description. Note that this is always the description column, even when the hit was made on a different field: searching Pfam by the domain <em>name</em> shows the free-text description here, which will not contain the string you typed.</li>
                <li><strong>Source</strong> and <strong>Browser</strong> &mdash; the annotation pipeline, and a link that opens the gene at its locus in the genome browser.</li>
                <li><strong>The chip row</strong> above the table lists the species that contributed hits, with a count each; clicking one restricts the result to that species, and <em>all species</em> undoes it.</li>
            </ul>
            <div class="manual-note"><strong>At most 500 rows are returned, and the chip row only describes those 500.</strong> When a search matches more, the page reports it as <code>500+</code> and warns that only the first 500 rows are shown. The species chips are computed from the returned rows, so under truncation they <em>under-report</em>: a species whose hits fall beyond the cut-off does not appear, and the count beside a species that does appear is only its count within those 500 rows. A common Pfam family can genuinely be present in far more species than the chip row suggests &mdash; narrow the query, or pick a species, rather than reading the chips as the taxonomic distribution.</div>
            <div class="manual-tip"><strong>Two details that change what a search finds.</strong> Rows are <em>gene&ndash;annotation pairs</em>, so a gene carrying the same signature twice is listed twice and the reported count is not a gene count. And <code>%</code> and <code>_</code> typed into the free-text box are live wildcards, not literal characters &mdash; a search for <code>Fibrinogen_C</code> also matches <code>FibrinogenXC</code>. Only the identifier and description columns are searched; other fields in the annotation tables cannot be reached from here.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.20</span>
                    <img src="./manual_images/domain_search.png" alt="Functional Domain Search" data-caption="Functional Domain Search: one query across all 148 annotated genomes, with a chip per species carrying hits." loading="lazy" decoding="async" />
                    <figcaption>Functional Domain Search: one query across all 148 annotated genomes, with a chip per species carrying hits.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="go" data-title="gene ontology GO term biological process molecular function cellular component gene list">
            <h3>3.9 Gene Ontology</h3>
            <p><strong>What it does.</strong> Takes a list of gene IDs from one species and returns their Gene Ontology annotations: one row per gene&ndash;GO term pair, giving the term, the ontology it belongs to (<em>Biological Process</em>, <em>Molecular Function</em> or <em>Cellular Component</em>), its description and a genome-browser link for the gene. The GO content is the largest of the three functional vocabularies here &mdash; about 34.4 million annotation rows over 3.0 million distinct genes, an average of roughly 11 terms per gene.</p>
            <p><strong>Why you would use it.</strong> When you already have a gene list &mdash; from a co-expression cluster, a differential-expression result or your own filtering &mdash; and want to know what those genes do in ontology terms; or when you want the GO category of one gene, which the gene detail page also shows but without the ability to handle a list. For asking the inverse question (&ldquo;which genes carry GO:0005615?&rdquo;) use the <em>Gene Ontology</em> tab of the <a href="#domain-search">Functional Domain Search</a> (3.8), and for testing whether a category is over-represented in your list rather than merely present, use <a href="#gsea">Gene Set Enrichment Analysis</a> (5.1).</p>
            <p class="manual-subhead">The controls</p>
            <ol>
                <li><strong>Class</strong> &mdash; a display control only. It narrows the species list; the species selector is the value actually submitted.</li>
                <li><strong>Species</strong> &mdash; the query key. The selector lists all <strong>326</strong> species in the catalogue, grouped by class and shown with Latin names, but only <strong>148</strong> have a GO table; see the note below.</li>
                <li><strong>Gene List</strong> &mdash; the IDs, one or many. The <em>Example</em> link fills in a working list from <i>Nematostella vectensis</i>.</li>
                <li><strong>Submit Query</strong> &mdash; results open in a new tab, paged at 20 rows with 10, 20, 50 and 100 offered and a <em>Go to page</em> box.</li>
            </ol>
            <p class="manual-subhead">The results table</p>
            <p><em>Gene</em> (linked to the gene detail page), <em>GO terms</em> (linked to the term's AmiGO page), <em>Category</em> (one of Biological Process, Molecular Function, Cellular Component), <em>Description</em>, and <em>JBrowse</em>. Rows appear in the order you submitted the genes, so the table is <em>not</em> grouped by gene or by category &mdash; if you need the rows gathered, copy the table into a spreadsheet and sort it there, or submit one gene at a time. The counter above the table gives the number of annotation rows found and how many of the identifiers you submitted were actually found in that species, so an empty result can be told apart from a mistyped list.</p>
            <div class="manual-note"><strong>An empty result has two possible meanings.</strong> The GO tables exist for 148 of the 326 species in the catalogue, but the species selector lists the whole catalogue. If the species you chose has no GO annotation, the search reports nothing found for every gene &mdash; which is easy to mistake for &ldquo;none of my genes are annotated&rdquo;. The two cases are not distinguished on the page. Check the species first in the <a href="#coverage-matrix">Data Coverage Matrix</a> (2.4) or on its <a href="#species-portal">Species Portal</a> (2.5); if function annotation is not marked available there, no GO result is possible for that species. Coverage is uneven among the annotated genomes: of the 197 Hexacorallia, 98 have GO data, against 14 of the 39 Octocorallia and 4 of the 17 Myxozoa. A species with no annotation at all &mdash; <i>Pachycerianthus multiplicatus</i>, for example, which was assessed for completeness on its assembly alone &mdash; cannot appear in this module at any coverage.</div>
            <div class="manual-tip"><strong>Separate the gene IDs with spaces, tabs, new lines or commas &mdash; the box splits on any run of those, and duplicates are dropped.</strong> Two further limits are worth knowing: the whole list is carried in the URL of the result link, so a few hundred IDs works but roughly 450 or more exceeds what the server accepts and the request is rejected outright; and the species you select here is remembered and shared with the <a href="#interpro">InterPro</a> (3.10) and <a href="#kegg">KEGG</a> (3.11) modules, which use the same session value &mdash; convenient when working through one genome, confusing if you changed it in another tab. Results carry no export button.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.21</span>
                    <img src="./manual_images/go_result.png" alt="Gene Ontology result table" data-caption="Gene Ontology results: one row per gene&ndash;GO term pair, with the ontology category and description." loading="lazy" decoding="async" />
                    <figcaption>Gene Ontology results: one row per gene&ndash;GO term pair, with the ontology category and description.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="interpro" data-title="InterPro annotation protein family domain gene list signature">
            <h3>3.10 InterPro</h3>
            <p><strong>What it does.</strong> Takes a list of gene IDs from one species and returns their InterPro signatures: one row per gene&ndash;signature pair, giving the InterPro entry, the type of signature it comes from (family, domain, repeat, site, homologous superfamily), its description and a genome-browser link. InterPro integrates several member databases, so this is the broadest of the protein-signature views here &mdash; about 34.1 million annotation rows over 3.8 million distinct genes, roughly 9 signatures per gene.</p>
            <p><strong>Why you would use it.</strong> To characterise a gene list at the level of protein families and domains, which is usually what a reviewer or a reader wants next after a set of gene IDs; or to look up the integrative InterPro entry for a gene whose Pfam match alone is uninformative, since one InterPro entry may combine several member-database signatures. The <a href="#protein-domain">Protein Domain</a> module (3.5) reports Pfam hits, which are a subset; the <a href="#domain-search">Functional Domain Search</a> (3.8) answers the reverse question across all genomes.</p>
            <p class="manual-subhead">The controls</p>
            <ol>
                <li><strong>Class</strong> &mdash; a display control only; it filters the species list, not the query.</li>
                <li><strong>Species</strong> &mdash; the query key. As in <a href="#go">Gene Ontology</a> (3.9) the selector lists the whole catalogue of 326 species while the annotation tables exist for <strong>148</strong> of them, so confirm the species has function annotation before treating an empty table as a negative result.</li>
                <li><strong>Gene List</strong> &mdash; the IDs, separated by spaces, tabs, new lines or commas. The <em>Example</em> link fills in a working list from <i>Nematostella vectensis</i> and selects that species.</li>
                <li><strong>Submit Query</strong> &mdash; results open in a new tab, paged at 20 rows by default with 10, 20, 50 and 100 offered.</li>
            </ol>
            <p class="manual-subhead">The results table</p>
            <p><em>Gene</em> (linked to the gene detail page), <em>InterPro term</em> (linked to the entry page at EBI), <em>Type</em>, <em>Description</em> and <em>JBrowse</em>. Rows come back in the order the genes were submitted and are not grouped, so a gene with many signatures is scattered through the table rather than collected in one block. A blue box at the top of the form links straight to the InterPro tab of the Functional Domain Search, which is the way to invert the query.</p>
            <div class="manual-tip"><strong>The same input rules as the GO module apply.</strong> Separate the gene IDs with whitespace or commas; the counter above the result table also reports how many of the identifiers were found. The list travels in the result URL, so a few hundred IDs is comfortable but around 450 or more exceeds the URL length the server accepts. The species selection is shared with the <a href="#go">Gene Ontology</a> (3.9) and <a href="#kegg">KEGG</a> (3.11) modules through one session value, and there is no export button on the result page.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.22</span>
                    <img src="./manual_images/interpro_result.png" alt="InterPro annotation result table" data-caption="InterPro results: one row per gene&ndash;signature pair, with the signature type and description." loading="lazy" decoding="async" />
                    <figcaption>InterPro results: one row per gene&ndash;signature pair, with the signature type and description.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="kegg" data-title="KEGG pathway KO ID enzyme pathway map gene list">
            <h3>3.11 KEGG Pathway</h3>
            <p><strong>What it does.</strong> Takes a list of gene IDs from one species and maps them onto KEGG pathways: one row per gene&ndash;pathway pair, giving the KO identifier, the enzyme, its EC number, the pathway name and its map ID, plus a link into the genome browser. KEGG is the smallest of the three functional vocabularies here &mdash; about 1.3 million rows over 1.29 million genes, close to one KO per gene, so a result table is much shorter than the GO or InterPro equivalent for the same list.</p>
            <p><strong>Why you would use it.</strong> To place a gene list in a metabolic or signalling context rather than a structural one &mdash; which pathways the genes participate in, and which enzymes they correspond to. It is the natural companion to <a href="#go">Gene Ontology</a> (3.9) for the same list, and the input to <a href="#gsea">Gene Set Enrichment Analysis</a> (5.1) when you want to test whether a pathway is over-represented rather than merely present. To search by KO or pathway ID instead of by gene &mdash; every gene annotated with <code>K13752</code> or <code>ko02000</code> &mdash; use the <em>KEGG pathway</em> tab of the <a href="#domain-search">Functional Domain Search</a> (3.8).</p>
            <p class="manual-subhead">The controls</p>
            <ol>
                <li><strong>Class</strong> &mdash; a display control only; it filters the species list, not the query.</li>
                <li><strong>Species</strong> &mdash; the query key. The selector lists all 326 species, but KEGG tables exist for the same <strong>148</strong> annotated genomes as GO and InterPro, so the warning about an empty result applies here too.</li>
                <li><strong>Gene List</strong> &mdash; IDs separated by spaces, tabs, new lines or commas. The <em>Example</em> link fills in a list of <i>Nematostella vectensis</i> identifiers and selects that species, so it can be submitted as-is.</li>
                <li><strong>Submit Query</strong> &mdash; results open in a new tab, paged at 20 rows by default with 10, 20, 50 and 100 offered.</li>
            </ol>
            <p class="manual-subhead">The results table</p>
            <ul>
                <li><strong>Gene</strong> &mdash; linked to the gene detail page.</li>
                <li><strong>KO ID</strong> &mdash; the KEGG Orthology identifier, linked to its KEGG record.</li>
                <li><strong>KO definition</strong> &mdash; KEGG's own name for the ortholog: the gene symbol followed by its full name (for example <code>EPHB2, ERK, DRT; Eph receptor B2</code>). It is <em>not</em> an enzyme classification &mdash; the EC number, where the ortholog has one, is the next column, and most rows have none (9,082 of 13,377 in <i>Nematostella vectensis</i>).</li>
                <li><strong>Enzyme ID</strong> &mdash; the EC number, linked to its KEGG record; EC numbers containing a dash are printed as plain text rather than linked.</li>
                <li><strong>Pathway</strong> and <strong>Map ID</strong> &mdash; the pathway name, and its identifier linked to KEGG. KEGG files these identifiers under two different entries, and the link follows the right one: a pathway map such as <code>ko00010</code> (glycolysis) opens the coloured pathway diagram, while a BRITE hierarchy such as <code>ko03036</code> (chromosome and associated proteins) opens that hierarchy's listing, which is a table rather than a picture.</li>
                <li><strong>Visualization</strong> &mdash; opens the gene in the genome browser.</li>
            </ul>
            <div class="manual-tip"><strong>The Example link fills the gene box and selects the species its identifiers belong to.</strong> Its IDs are <i>Nematostella vectensis</i> accessions and the species selector is set to that species at the same time, so the example submits as-is; change the species afterwards and the pasted list no longer matches the query. The rest of the input rules are the same as for GO and InterPro: identifiers separated by whitespace or commas; a list of roughly 450 IDs or more exceeds the URL length the server accepts; the species selection is shared with <a href="#go">Gene Ontology</a> (3.9) and <a href="#interpro">InterPro</a> (3.10) through one session value; and there is no export button on the result page.</div>
            <div class="manual-note">Unlike the GO and InterPro tables, the KEGG query does not de-duplicate its output, so a gene with a repeated annotation can appear on two identical rows. Count distinct genes rather than rows when the table is long.</div>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 3.23</span>
                    <img src="./manual_images/kegg_result.png" alt="KEGG pathway result table" data-caption="KEGG pathway results: KO ID, enzyme, EC number, pathway and map ID for each gene." loading="lazy" decoding="async" />
                    <figcaption>KEGG pathway results: KO ID, enzyme, EC number, pathway and map ID for each gene.</figcaption>
                </figure>
            </div>
        </article>
    </section>

    <!-- ============ Chapter 4 ============ -->
    <section class="manual-chapter" id="ch4">
        <div class="manual-chapter-head">
            <div class="manual-chapter-num">4</div>
            <div>
                <h2>Multi-Omics Analysis and Visualization</h2>
                <p class="manual-chapter-sub">Phylogeny, transcriptomes, single-cell, proteomics, epigenomics, metagenomics and phenotypes.</p>
            </div>
        </div>

        <article class="manual-section" id="species-tree" data-title="species tree phylogeny linear radial layout species tree">
            <h3>4.1 Species Tree</h3>

<p><strong>What it does.</strong> It draws the phylogeny of every cnidarian species CnidoSite holds &mdash; 148 of
them &mdash; as an interactive tree you can search, fold, re-colour and export. The topology is inferred from
<strong>single-copy orthologues</strong>, the genes present exactly once in every genome compared, because those
are the only ones whose history tracks the species rather than the gene family. The tree is rooted on five
non-cnidarian outgroups &mdash; the ctenophore <em>Bolinopsis microptera</em> and the sponges <em>Corticium
candelabrum</em>, <em>Oscarella lobularis</em>, <em>Halichondria panicea</em> and <em>Sycon ciliatum</em> &mdash;
which is what fixes the root, and therefore the direction of every change shown along the branches.</p>

<p><strong>Why you would use it.</strong> A tree page is usually consulted for one of three things. To check
whether a group is <em>monophyletic</em> before writing &ldquo;the family X&hellip;&rdquo; in a paper. To see where
a species of interest sits relative to the model cnidarians. Or to pick a sensible set of species for a
comparative analysis &mdash; the downloadable subtrees make that a one-click operation rather than a manual
pruning job.</p>

<p class="manual-subhead">The controls</p>
<ul>
    <li><strong>Taxonomic level.</strong> Defaults to <em>Whole phylum Cnidaria</em>. Choosing a class, order or
        family loads that subtree into the viewer, and each entry shows its tip count beside the name. The subtrees
        are pruned views of one topology, not separately inferred trees, so a relationship you read off a subtree
        is the same one the full tree shows.</li>
    <li><strong>Colour by.</strong> Class (the default), Order, Family, Genus, or no colour &mdash; the same
        topology coloured by different ranks. Useful when the clade you care about is a family that spans several
        colours.</li>
    <li><strong>Support values.</strong> On by default. Branch labels are SH-aLRT and UFBoot support percentages,
        two complementary measures of how well the data support that branch; a branch that is low on both is one
        the data do not resolve confidently.</li>
    <li><strong>Search.</strong> Type a species, genus, family or order name and step through the matches with
        &uarr; Prev / &darr; Next; the viewer centres each hit as you go.</li>
    <li><strong>Layout and spacing.</strong> Linear (the default) or radial; tips aligned left or right; vertical
        and horizontal spacing expanded or compressed; clades sorted deepest-first, shallowest-first, or restored
        to the original order. All of this is presentation only &mdash; none of it changes the topology.</li>
    <li><strong>Collapse all / Expand all / Reset view.</strong> For working with a large tree at a readable
        scale: fold away the clades you are not asking about, then open the one you are.</li>
</ul>

<p class="manual-subhead">What you get, and how to reuse it</p>
<ul>
    <li>The interactive tree, an information panel for the hovered or selected node, and a taxonomic legend.</li>
    <li>Branch lengths are substitutions per site and are carried in the downloadable Newick file, so an exported
        tree can be re-analysed rather than only looked at.</li>
    <li>Export buttons <strong>NWK</strong> (the current subtree as Newick), <strong>SVG</strong> and
        <strong>PNG</strong>. Every subtree in the taxonomic-level panel has its own Newick download too, which is
        the quickest way to get, say, all Octocorallia into another program.</li>
    <li>Clicking any tip opens that species&rsquo; page, so the tree doubles as a way into the rest of the
        database.</li>
</ul>

<div class="manual-tip"><strong>Read the non-monophyly note before quoting a rank.</strong> Where a
traditionally recognised rank does not come out as a single branch, the page says so explicitly instead of
quietly drawing it as if it did, and such taxa are not offered as separate downloadable subtrees. If the group you
are writing about is one of them, the tree is telling you the current data do not support treating it as a clean
unit &mdash; worth knowing before a reviewer says so.</div>

<div class="manual-note"><strong>Why this page counts 148 species.</strong> The tree is built from genomes with a
comparable single-copy orthologue set. CnidoSite registers more species than that, but a species without a gene
set cannot contribute orthologues and so is not on the tree. The same distinction explains the different totals on
the <a href="/genomeinfo.php">Genomic Data</a> page, which counts every registered species. Neither number is
wrong; they count different things.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.1</span>
                    <img src="./manual_images/tree_species.png" alt="Species tree" data-caption="Interactive species tree with linear and radial layouts and the full set of display controls." loading="lazy" decoding="async" />
                    <figcaption>Interactive species tree with linear and radial layouts and the full set of display controls.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="transcriptomic" data-title="transcriptomic data samples tissue stage treatment run bioproject SRA">
            <h3>4.2 Transcriptomic Data</h3>

<p><strong>What it does.</strong> This module is the sample inventory behind every other expression module on the
site. For one species at a time it lists every RNA-seq run CnidoSite holds, with the tissue, developmental stage
and treatment each run came from, and links each run out to its NCBI record. It shows no expression values itself:
it tells you what the expression matrices behind <a href="#coexpression">4.3</a> and the ratio builder in
<a href="#dynamic-expression">4.4</a> were computed <em>from</em>.</p>

<p><strong>Why you would use it.</strong> Two reasons, mostly. <em>Provenance</em> &mdash; before you read a
co-expression network or an expression ratio, you want to know which samples produced it and how those samples
were treated. And <em>sample selection</em> &mdash; the <a href="/cytoscape/tpm_ratio.php">Expression Ratio from
TPM</a> builder asks you to decide which columns belong to group A and which to group B, and this is the page
where you find out what those columns actually are. A column identified only by a run accession is
uninterpretable until you have read its tissue and stage here.</p>

<p class="manual-subhead">The controls</p>
<ol>
    <li><strong>Class.</strong> Records which class you are browsing. The species list is not filtered by it and
        always contains every species that has samples, so you can pick a species directly without touching this
        box.</li>
    <li><strong>Species.</strong> Every species with at least one deposited run. Deep links from a species page
        arrive here with the species already selected.</li>
    <li><strong>View Samples.</strong> Runs the query.</li>
</ol>

<div class="manual-note"><strong>If the species has no samples.</strong> Rather than an empty page, the module
falls back to the first species alphabetically and tells you which one it is showing instead, with a link to that
species&rsquo; portal page &mdash; where you can see what the species <em>does</em> provide, if anything.</div>

<p class="manual-subhead">The results table</p>
<p>Nine columns, one row per run: <strong>Experiment</strong>, <strong>Class</strong>, <strong>Species</strong>,
<strong>BioProject</strong>, <strong>SRA</strong>, <strong>Layout</strong>, <strong>Tissue</strong>,
<strong>Developmental Stage</strong> and <strong>Treatment</strong>. The first column was labelled
<em>Run</em> until the mismatch was pointed out; what it actually holds is an experiment accession, so it now
says so. The two accessions are worth telling apart, because even with the labels corrected it is easy to reach
for the wrong one:</p>
<ul>
    <li><strong>Experiment</strong> carries the accession for one sequencing library (SRX / ERX / DRX) and links
        to that library&rsquo;s NCBI SRA record. This is the identifier to use when you need a single sample.</li>
    <li><strong>SRA</strong> carries the <em>study</em> accession (SRP&hellip;) &mdash; the project the library
        belongs to &mdash; and links to the study page, where its sibling runs are listed.</li>
    <li><strong>BioProject</strong> links to the umbrella project record.</li>
</ul>
<p><strong>Layout</strong> is the sequencing layout (single- or paired-end). <strong>Tissue</strong>,
<strong>Developmental Stage</strong> and <strong>Treatment</strong> are reproduced exactly as the depositor
described them, which is why one species can show several spellings of what is biologically the same condition:
they are not normalised, and this manual will not pretend otherwise. Where the depositor said nothing, the cell
shows a dash rather than an empty box &mdash; for these three fields that is common, and a dash means the source
record is silent, not that the page failed to load it.</p>

<p class="manual-subhead">Working with a long list</p>
<ul>
    <li>A <strong>total-sample counter</strong> above the table reports how many runs the current release holds.
        It is read from the database rather than hardcoded, so it moves with the release.</li>
    <li>The <strong>search box</strong> filters the rows on screen by run accession, tissue, stage or treatment. It
        filters only what is already displayed, so narrow to the species you want first and search within it.</li>
    <li><strong>Pagination</strong> shows 10 runs per page by default, with 20, 50 or 100 selectable; the pager
        offers first, previous, numbered, next and last, plus a jump-to-page box.</li>
</ul>

<div class="manual-tip"><strong>Read the sample list before trusting a ratio.</strong> When you group columns in
the ratio builder, this table is the evidence for the grouping. A comparison is only as meaningful as the runs
behind it: a group assembled from a single library, or from libraries with different layouts or treatments, will
produce a ratio that looks like a result but is really an artefact of how the data were deposited. Check here
first.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.2</span>
                    <img src="./manual_images/trans_form.png" alt="Transcriptomic sample selector" data-caption="Select a class and species to view its transcriptomic samples." loading="lazy" decoding="async" />
                    <figcaption>Select a class and species to view its transcriptomic samples.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.3</span>
                    <img src="./manual_images/trans_table.png" alt="Transcriptomic sample details" data-caption="Detailed sample information including tissue, developmental stage and treatment." loading="lazy" decoding="async" />
                    <figcaption>Detailed sample information including tissue, developmental stage and treatment.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="coexpression" data-title="network analysis co-expression network gene list positive negative PCC Pearson correlation coefficient MR mutual rank expression profiling enrichment">
            <h3>4.3 Network Analysis &mdash; Co-expression Network</h3>

            <p><strong>What it does.</strong> You give this module a short list of genes you already care about. It returns
            every gene in the same species whose expression rises and falls together with them across the RNA-seq samples
            CnidoSite holds for that species. Genes that move together tend to be switched on by the same conditions and
            often belong to the same pathway, so a co-expression network is a way of seeing what company a gene keeps &mdash;
            and, for a gene with no annotation, of guessing what it does from the neighbours it keeps.</p>

            <p><strong>Why you would use it.</strong> It is normally the <em>second</em> step of an analysis, after you have
            a list of genes from somewhere else on the site: a marker list from
            <a href="/cell_marker.php">Cell Marker</a>, one orthogroup from
            <a href="/genefamily.php">Gene Family</a>, a set of BLAST hits, or simply gene IDs you picked up in the search
            box. Alone, such a list is just names. Run through this module, it becomes a picture of which of those genes
            are wired to each other, and which additional genes they pull in.</p>

            <p class="manual-subhead">The three inputs</p>
            <ol>
                <li><strong>Species.</strong> The network is built per species, and gene IDs are species-specific &mdash; an
                    ID from one species will not be found in another. The species list offers only those species for which a
                    co-expression network has been built, and the <em>Class</em> box above it narrows that list, so a species
                    you know is in the database may still be absent here. If a run comes back with &ldquo;no protein
                    interacting with your submitted gene&rdquo;, check the species first; if you arrived from a species page
                    whose species has no network, the page says which species it is showing instead.</li>
                <li><strong>Gene list.</strong> One gene ID per line. <strong>A single run accepts at most 10 genes</strong>;
                    a longer list is rejected rather than silently trimmed, so split a large list into batches of 10. Duplicate
                    IDs are counted once, but the list is split on whitespace, so a trailing blank line counts as an extra
                    entry &mdash; a legitimate 10-gene paste ending in a newline can be refused as 11. If a list of exactly ten
                    is rejected, remove the empty last line. The genes you enter are the <em>query</em> genes, and they are what
                    the network is grown outwards from.</li>
                <li><strong>Relationship.</strong> Tick <em>Positive</em>, <em>Negative</em>, or both.
                    <em>Positive</em> means the two genes go up and down together; <em>negative</em> means one rises while
                    the other falls. You can tick both; each is drawn in its own colour. Positive pairs are far more
                    numerous in the database, so ticking both usually gives a positive-dominated graph.</li>
            </ol>

            <p class="manual-subhead">How the network is built and drawn</p>
            <p>The module starts from your query genes, collects their co-expressed partners, then adds the pairs that
            connect those partners to each other. That gives two kinds of edge, and the drawing distinguishes them:</p>

            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;margin:0 0 14px">
                <svg viewBox="0 0 470 170" width="100%" style="max-width:470px;height:auto" role="img"
                     aria-label="Legend: a yellow ellipse is your query gene, a green octagon is a co-expressed partner, a thick pink line is a positive relationship direct to a query gene, a thin pink line is a positive relationship between two partners, and the blue lines are the negative equivalents.">
                    <ellipse cx="30" cy="22" rx="18" ry="13" fill="#E1E100" stroke="#a3a300" stroke-width="1.5"></ellipse>
                    <text x="58" y="27" font-size="12.5" fill="#334155">Query gene &mdash; one of the genes you entered (always the same size)</text>
                    <polygon points="40.16,62.21 34.21,68.16 25.79,68.16 19.84,62.21 19.84,53.79 25.79,47.84 34.21,47.84 40.16,53.79" fill="#86B342" stroke="#5c7d2c" stroke-width="1.5"></polygon>
                    <text x="58" y="63" font-size="12.5" fill="#334155">Co-expressed partner &mdash; pulled in by the search (bigger = more connections)</text>
                    <line x1="14" y1="92" x2="46" y2="92" stroke="#EDA1ED" stroke-width="6" stroke-linecap="round"></line>
                    <text x="58" y="96" font-size="12.5" fill="#334155">Positive, directly with a query gene (thick)</text>
                    <line x1="14" y1="113" x2="46" y2="113" stroke="#EDA1ED" stroke-width="2.5" stroke-linecap="round"></line>
                    <text x="58" y="117" font-size="12.5" fill="#334155">Positive, between two partners (thin)</text>
                    <line x1="14" y1="134" x2="46" y2="134" stroke="#6FB1FC" stroke-width="6" stroke-linecap="round"></line>
                    <text x="58" y="138" font-size="12.5" fill="#334155">Negative, directly with a query gene (thick)</text>
                    <line x1="14" y1="155" x2="46" y2="155" stroke="#6FB1FC" stroke-width="2.5" stroke-linecap="round"></line>
                    <text x="58" y="159" font-size="12.5" fill="#334155">Negative, between two partners (thin)</text>
                </svg>
            </div>

            <p>Hovering a node opens a card with that gene&rsquo;s functional annotation and how many edges it has. The graph
            is interactive: drag nodes, zoom, and use the export button to save the figure. The legend printed on the page
            names the same elements more briefly &mdash; <em>Yellow: Query proteins</em>, <em>Green: Co-expressed
            partners</em>, <em>Pink line: Positive co-expression</em>, <em>Blue line: Negative co-expression</em> &mdash;
            and, unlike the diagram above, it does not distinguish the thick query-to-partner edges from the thin
            partner-to-partner ones.</p>

            <p class="manual-subhead">The two numbers in the result table</p>
            <p>Each co-expressed pair is reported with two different scores. They answer different questions, and it is
            worth knowing which one you are reading:</p>
            <ul>
                <li><strong>PCC</strong> &mdash; the <strong>Pearson correlation coefficient</strong> between the two
                    genes&rsquo; expression profiles across the species&rsquo; RNA-seq samples, from +1 (perfectly
                    together) through 0 (unrelated) to &minus;1 (perfectly opposite). This is the actual measure of
                    co-expression, and its sign is what separates the positive table from the negative one.</li>
                <li><strong>MR</strong> &mdash; the <strong>mutual rank</strong>, a rank-based score, so its scale does not
                    move with the correlation value the way PCC does. Each gene&rsquo;s partners are ordered by correlation, and the mutual rank
                    of a pair combines the two positions: <code>MR = &radic;(rankA &times; rankB)</code>, where
                    <code>rankA</code> is where gene B sits in gene A&rsquo;s ordered list, and vice versa. A low MR means
                    each gene is near the top of the other&rsquo;s list, which is strong evidence of a specific
                    relationship. MR is the more robust of the two when expression is noisy, which is why it is reported
                    alongside PCC rather than replaced by it.</li>
            </ul>
            <div class="manual-tip"><strong>What to expect in the table.</strong> Across the whole database the positive
            tables contain pairs with PCC from <strong>0.354 to 1.000</strong>, and the negative tables pairs with PCC from
            <strong>&minus;0.982 to &minus;0.089</strong>. The two are therefore not symmetric: a positive hit is guaranteed
            to be a fairly strong correlation, whereas a weak negative pair (say &minus;0.1) can be present as well. If you
            are ranking a mixed result, rank on <strong>MR</strong> or on the absolute PCC, not on the raw signed PCC.</div>
            <div class="manual-tip"><strong>Not recorded, so not documented.</strong> The database also stores a
            <code>level</code> field (L1 / L2 / top) that is not a function of either PCC or MR and is not shown on any
            page. It is left out of this manual rather than given a meaning it does not have.</div>

            <p class="manual-subhead">What you get, and what to do next</p>
            <ul>
                <li>The <strong>network graph</strong>, and beneath it a <strong>table of co-expressed genes</strong> with the
                    columns <em>Gene ID</em>, <em>Description</em>, <em>PCC</em> and <em>Relationship</em>. Note that this table
                    does <strong>not</strong> carry MR; the mutual rank appears on the separate <em>View Detailed Network
                    Information</em> page that the result page links to, which is where the full relationship table lives.</li>
                    <li><strong>Exports.</strong> The graph can be saved as <em>JPG</em>, <em>PNG</em> or <em>JSON</em> from the
                    export bar, and the co-expressed gene table as an Excel file &mdash; useful when the point of the run was the
                    gene list rather than the picture.</li>
                    <li>Each query gene is also listed in the table as a row with <em>PCC</em>&nbsp;=&nbsp;1 and relationship
                    <em>positive</em>, whichever relationship boxes you ticked. It is a reference row, not a result: a gene is
                    perfectly correlated with itself.</li>
                <li><strong>Expression profiling</strong> and <strong>gene set enrichment analysis</strong> for the network
                    members, launched from the two forms at the bottom of the page. Both take the member gene list with
                    them, so there is nothing to retype.</li>
                <li><strong>Next Step: Dynamic Expression View</strong> &mdash; the same network again, but with each node
                    coloured by how much that gene changes between two conditions you choose. The gene pairs are carried
                    across for you; see <a href="#dynamic-expression">section 4.4</a>.</li>
            </ul>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.4</span>
                    <img src="./manual_images/net_form.png" alt="Co-expression input form" data-caption="Co-expression input form: choose species, input a gene list and select the relationship type." loading="lazy" decoding="async" />
                    <figcaption>Co-expression input form: choose species, input a gene list and select the relationship type.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.5</span>
                    <img src="./manual_images/net_graph.png" alt="Co-expression network graph" data-caption="The resulting co-expression network graph." loading="lazy" decoding="async" />
                    <figcaption>The resulting co-expression network graph.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.6</span>
                    <img src="./manual_images/net_table.png" alt="Co-expressed genes table" data-caption="Detailed information of the co-expressed genes, including PCC and relationship." loading="lazy" decoding="async" />
                    <figcaption>Detailed information of the co-expressed genes, including PCC and relationship.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.7</span>
                    <img src="./manual_images/net_pairs.png" alt="Co-expressed gene pairs" data-caption="Co-expressed gene pairs used for the dynamic network view." loading="lazy" decoding="async" />
                    <figcaption>Co-expressed gene pairs used for the dynamic network view.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.8</span>
                    <img src="./manual_images/net_expr.png" alt="Expression profiling analysis" data-caption="Expression profiling analysis of the co-expressed genes." loading="lazy" decoding="async" />
                    <figcaption>Expression profiling analysis of the co-expressed genes.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.9</span>
                    <img src="./manual_images/net_gsea.png" alt="Gene set enrichment analysis" data-caption="Gene set enrichment analysis of the co-expressed genes." loading="lazy" decoding="async" />
                    <figcaption>Gene set enrichment analysis of the co-expressed genes.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="dynamic-expression" data-title="dynamic expression view network upregulated downregulated threshold expression ratio TPM group comparison">
            <h3>4.4 Dynamic Expression View</h3>

            <p><strong>What it does.</strong> Section <a href="#coexpression">4.3</a> gives you a network, but every node in
            it is drawn the same way: the graph tells you which genes are connected, not which of them are doing anything
            in the situation you are studying. This module redraws the <em>same</em> network with each node coloured by how
            much that gene changes between two conditions that <em>you</em> define. The wiring stays fixed; the colour is
            the new layer. The point is to see whether a responding set of genes sits together in the network &mdash; if the
            red nodes form a connected cluster rather than being scattered, the response has a coherent module behind it.</p>

            <p><strong>Why it needs an extra input.</strong> The network on its own cannot supply this. Co-expression is
            computed across all the samples a species has, so it describes an average relationship, not a change between
            two conditions. To colour the network, the module needs one number per gene: an <em>expression ratio</em>
            describing that gene in condition A relative to condition B. That number is not stored anywhere on the site as
            such, so it has to be computed &mdash; which is what the ratio builder below does, from the species&rsquo;
            own expression matrix.</p>

            <div class="manual-tip"><strong>Shortcut, and what it actually does.</strong> The hand-off runs through the
            <a href="/cytoscape/tpm_ratio.php">Expression Ratio from TPM</a> page, not directly: the co-expression result page
            posts the gene pairs to that builder, which keeps them while you choose the two groups, computes the ratios, and
            then posts pairs, ratios and thresholds here together. One run of the builder carries at most
            <strong>10 pairs</strong> &mdash; the same limit this page applies &mdash; so when a network is larger, the result
            page selects the 10 pairs with the strongest |PCC| and says so. Step&nbsp;2 is filled in only on that path: opening
            this page from the menu, or from a species page, leaves it empty.</div>

            <p class="manual-subhead">The four inputs, in order</p>
            <ol>
                <li><strong>Select Target Species.</strong> Must be the same species the pairs came from &mdash; gene IDs
                    are species-specific, and pairs from another species will simply not match.</li>
                <li><strong>Input Co-expressed Gene Pairs.</strong> One pair per line, the two gene IDs separated by a
                    space or a tab (<code>GeneA GeneB</code>). These are the edges of the network. A pair that names a gene
                    with no ratio is still drawn, but that gene will be grey rather than red, blue or green. One run draws
                    at most <strong>10 pairs</strong>, so a larger network has to be split into several runs. Unlike the gene
                    list in <a href="#coexpression">4.3</a>, an over-long list here is <em>trimmed</em> to the first 10 lines
                    rather than refused &mdash; and the page tells you how many lines it did not use, so the trim is never
                    silent. If you came from the result page, its 10 strongest pairs are carried over automatically.</li>
                <li><strong>Input Genes with Expression Ratio.</strong> One gene per line, the gene ID then its ratio
                    (<code>GeneA &nbsp; 1.5</code>). This is what sets the colour. A line with a gene name but no number is
                    skipped rather than guessed at. Like the pairs, this list is capped at 10 entries per run.</li>
                <li><strong>Select Threshold for Gene Expression Ratio.</strong> Four numbers defining two bands &mdash; a red
                    band and a blue band &mdash; that decide where the colours change over. They default to
                    <code>1</code> and <code>100</code> for red, and <code>&minus;10</code> and <code>&minus;1</code> for blue.
                    See below, including what happens to a ratio that falls outside both.</li>
            </ol>

            <p class="manual-subhead">Where the expression ratio comes from</p>
            <p>CnidoSite stores a <strong>TPM</strong> (transcripts per million) expression matrix for each species that has
            RNA-seq data. The helper page <a href="/cytoscape/tpm_ratio.php"><strong>Expression Ratio from TPM</strong></a>
            turns that matrix into the ratio this module wants:</p>
            <p style="text-align:center;font-size:16px;color:#0f172a;background:#f0f9ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin:0 0 12px">
                ratio&nbsp;=&nbsp;log<sub>2</sub> ( (mean TPM in group&nbsp;A + 1) / (mean TPM in group&nbsp;B + 1) )
            </p>
            <ul>
                <li>You choose which samples belong to <strong>group A</strong> and which to <strong>group B</strong>. That
                    choice <em>is</em> the biological question &mdash; for example A = a treatment or a developmental stage,
                    B = the corresponding control &mdash; and it is deliberately left to you, because the RNA-seq runs were
                    deposited under many different naming conventions and any automatic grouping would be wrong for some
                    species. The builder shows every sample column and lets you tick the two sides.</li>
                <li><strong>Tick all the replicates</strong> of a condition. Ticking a single column per side gives a
                    one-sample-per-group comparison with no replication: the network will still draw, but the ratio is then
                    driven by whichever replicate you happened to pick.</li>
                <li><strong>The &ldquo;+1&rdquo; is a pseudocount</strong>, and it is not optional. In these matrices a
                    substantial fraction of TPM values are exactly zero, and in a group where a gene is not expressed at all
                    the mean can be 0 &mdash; without the pseudocount the ratio would be a division by zero or an infinite
                    log. Adding 1 to both means puts a floor under the value. The consequence to remember is that the ratio
                    is a stabilised measure, not a raw fold change, and it is most trustworthy for genes that are actually
                    expressed on at least one side.</li>
                <li>A <strong>positive</strong> ratio means the gene is higher in group A; a <strong>negative</strong> one
                    means higher in group B. A ratio of +1 is a doubling in A relative to B; &minus;1 is a halving.</li>
                <li>The builder hands the pairs <em>and</em> the ratios to this module in one action, so you do not retype
                    anything. If you prefer to work by hand, it also displays the gene-and-ratio list in a form you can copy
                    and paste into step&nbsp;3.</li>
            </ul>

            <p class="manual-subhead">Reading the thresholds and the colours</p>
            <p>The four numbers define two bands on the ratio scale. A gene takes a colour by which band it falls into, and
            a gene in neither band is left green:</p>

            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;margin:0 0 14px">
                <svg viewBox="0 0 470 116" width="100%" style="max-width:470px;height:auto" role="img"
                     aria-label="Node colours: red means the ratio is at or above the red lower threshold, blue means at or below the blue upper threshold, green means it falls in neither band, grey means no ratio was supplied for that gene.">
                    <polygon points="40.16,34.21 34.21,40.16 25.79,40.16 19.84,34.21 19.84,25.79 25.79,19.84 34.21,19.84 40.16,25.79" fill="#FF2D2D" stroke="#a31212" stroke-width="1.5"></polygon>
                    <text x="58" y="28" font-size="12.5" fill="#334155">Red &mdash; log<tspan font-size="9" dy="3">2</tspan><tspan dy="-3"> ratio at or above the red lower threshold</tspan></text>
                    <text x="58" y="43" font-size="12.5" fill="#64748b">Higher in group A than in group B</text>
                    <polygon points="40.16,71.21 34.21,77.16 25.79,77.16 19.84,71.21 19.84,62.79 25.79,56.84 34.21,56.84 40.16,62.79" fill="#66B3FF" stroke="#2b6cb0" stroke-width="1.5"></polygon>
                    <text x="58" y="65" font-size="12.5" fill="#334155">Blue &mdash; at or below the blue upper threshold</text>
                    <text x="58" y="80" font-size="12.5" fill="#64748b">Lower in group A than in group B</text>
                    <polygon points="40.16,108.21 34.21,114.16 25.79,114.16 19.84,108.21 19.84,99.79 25.79,93.84 34.21,93.84 40.16,99.79" fill="#86B342" stroke="#5c7d2c" stroke-width="1.5"></polygon>
                    <text x="58" y="107" font-size="12.5" fill="#334155">Green &mdash; inside neither band: real but not large enough to colour</text>
                </svg>
            </div>

            <p>A gene named in a pair but missing from the ratio list is drawn as a <strong>grey octagon</strong> with no
            colour: it is kept so the network stays connected, but nothing is claimed about its expression. Seeing grey
            nodes is the signal that the ratio list and the pair list do not cover the same genes.</p>

            <p><strong>The bands are closed, so a large enough ratio stops being red.</strong> A gene is red only if its ratio
            lies between the red lower and red upper values, and blue only if it lies between the blue lower and blue upper
            values. With the defaults that means a ratio above <code>100</code>, or below <code>&minus;10</code>, is drawn
            <strong>green</strong> &mdash; the same colour as a gene whose change was too small to call. In practice such a
            gene is usually one that is silent on one side, so the pseudocount drives the ratio far out; widen the upper red
            bound (or lower the blue lower bound) if you want those genes coloured rather than left green. When a run looks
            as though it has lost its most dramatic genes, this is why.</p>

            <p><strong>Choosing the thresholds.</strong> The defaults are +1 and &minus;1, i.e. a twofold change in either
            direction &mdash; a conventional cut-off, and a strict one. If nothing reaches either band, <em>every</em> node
            comes out green and the graph looks uninformative even though the ratios are real; the ratio builder shows the
            band counts after each run precisely so you can see this before handing over, and widen the thresholds if the
            changes you care about are smaller. The two bands are independent, so the red and blue cut-offs can be set
            asymmetrically if your comparison calls for it.</p>

            <p class="manual-subhead">What you get, and what to do next</p>
            <ul>
                <li>The <strong>coloured dynamic network</strong>, with a summary line above it stating how many genes carried a
                    ratio, how many pairs were used and how many distinct genes those pairs involve, plus a second line if any
                    input lines were dropped. This page deliberately carries <em>no</em> tables: the graph, the legend and the
                    two analysis forms are the whole page, so a coloured set you want as a list should be taken from the
                    co-expression result page in <a href="#coexpression">4.3</a> instead.</li>
                <li><strong>Expression profiling</strong> and <strong>gene set enrichment analysis</strong> for the network
                    members, reached from the same page, which is where a co-expressed cluster starts to acquire a
                    biological name.</li>
                <li>The coloured set is usually the input to the next question: take the genes that came out red (or blue),
                    put them back into <a href="#coexpression">4.3</a> as a new query list, or into
                    <a href="/GSEA/GSEA.php">Gene Set Enrichment Analysis</a>, and ask what they have in common.</li>
            </ul>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.10</span>
                    <img src="./manual_images/dyn_form.png" alt="Dynamic expression input form" data-caption="The four inputs: species, co-expressed gene pairs, genes with their expression ratios, and the colour thresholds. The ratios themselves are built on the Expression Ratio from TPM page." loading="lazy" decoding="async" />
                    <figcaption>The four inputs: species, co-expressed gene pairs, genes with their expression ratios, and the colour thresholds. The ratios themselves are built on the <a href="/cytoscape/tpm_ratio.php">Expression Ratio from TPM</a> page.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.11</span>
                    <img src="./manual_images/dyn_graph.png" alt="Dynamic network view" data-caption="Dynamic network view: nodes coloured by expression ratio (red high in group A, blue low, green in neither band), on the fixed co-expression wiring." loading="lazy" decoding="async" />
                    <figcaption>Dynamic network view: nodes coloured by expression ratio (red high in group A, blue low, green in neither band), on the fixed co-expression wiring.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="cell-atlas" data-title="cell atlas UMAP species stages tissues cell types replicates">
            <h3>4.5 Cell Atlas</h3>

<p><strong>What it does.</strong> It shows one single-cell dataset as a UMAP &mdash; every cell placed by its
transcriptome, so that cells with similar expression land near each other &mdash; and lets you colour that map by
cell type, by cluster, by which library the cell came from, by a quality-control measure, or by the expression of
a gene you name. It is the page you use to ask &ldquo;does this dataset actually contain the cell type I am
interested in, and is that cell type a clean group?&rdquo;.</p>

<p><strong>Why it matters that some datasets behave differently.</strong> CnidoSite holds the deposited datasets,
but it did not produce all of them. Some were <strong>re-analysed</strong>: the count matrix was retrieved and the
cells were filtered, clustered and annotated here, which is what makes an interactive map possible. The rest are
shown <strong>as published</strong> &mdash; the source study&rsquo;s own figure. The two look completely different,
deliberately, and the dataset list separates them into two labelled groups so the source study&rsquo;s figure is
never mistaken for something recomputed here.</p>

<div class="manual-note"><strong>Start from the catalogue, not from this page.</strong> The single-cell menu opens
with <strong>Single-cell Data</strong>, one row per deposited dataset &mdash; class, species, project, study
accession, the organ or embryo stage, the number of cells, a quality-control summary (cells before and after
filtering), a link into the analysis page and the reference. Use it to see what exists before choosing a dataset
here, and note that a dataset can appear in the catalogue without having been re-analysed: those are the
<em>as published</em> entries in the list below.</div>

<p class="manual-subhead">Choosing a dataset</p>
<p>The <strong>Dataset</strong> list has two groups:</p>
<ul>
    <li><strong>Re-analysed &mdash; interactive viewer.</strong> Labelled
        <code>Species &mdash; tissue (stage) [N cells]</code>, with tissue and stage omitted where the deposit did
        not state them, and where the stage text carries a bracketed aside of its own it is set off by a
        middot instead &mdash; <code>Whole animals &middot; Adult (whole animal)</code> &mdash; so that two pairs
        of brackets never nest. These open the interactive map described below.</li>
    <li><strong>As published &mdash; static figure.</strong> Labelled <code>Species &mdash; tissue</code>. These
        open the source study&rsquo;s figure, which cannot be recoloured, subset or queried, because it is an
        image.</li>
</ul>
<p>The list carries your current selection across: switching dataset on the marker or expression page keeps the
cell type or gene you had chosen.</p>

<p class="manual-subhead">The interactive viewer</p>
<ul>
    <li><strong>Colour cells by.</strong> Cell type, Cluster, Library / sample, QC: nCount (UMI), QC: nGene,
        QC: mitochondrial %, or Gene expression. For datasets whose clusters were never given cell-type names the
        first two collapse into a single <em>clusters</em> entry, because there is nothing to choose between.</li>
    <li><strong>Gene box and Plot.</strong> Appears once you select <em>Gene expression</em>. The box
        autocompletes from the genes available for that dataset. Nearby you will find <em>Highlight one group
        (recommended)</em>, on by default, which draws the selected group in colour against grey &mdash; with tens
        of thousands of overlapping cells, a single highlighted group is far easier to read than a dozen colours
        interleaved.</li>
    <li><strong>Navigating the map.</strong> Drag to pan, scroll to zoom, shift-drag to select a region. Selecting
        dims the cells outside the selection rather than removing them, so you keep the shape of the whole map
        while you look at part of it. Clicking a row in the legend isolates that group. <em>Reset view</em> clears
        the selection.</li>
    <li><strong>Hovering a cell</strong> gives its cell type, its cluster and library, its UMI and gene counts and
        its mitochondrial percentage, plus the value of the gene currently plotted.</li>
    <li><strong>The three lower panels</strong> are the same data in a readable form: <em>Groups</em>, the legend
        with each group&rsquo;s cell count and percentage; <em>Composition</em>, the same proportions as bars; and
        <em>Expression by cell type</em>, which becomes the gene&rsquo;s distribution once a gene is plotted. That
        last panel shows at most the top 14 cell types by mean expression &mdash; with more than fourteen cell
        types, a violin each becomes unreadable.</li>
</ul>

<p class="manual-subhead">The tables and the QC panel</p>
<ul>
    <li>The <strong>cell-type table</strong> lists each cell type with its cell count, its percentage (with a bar
        drawn to scale) and how many marker genes are recorded for it, and ends with a bold total row. Both the
        cell-type name and the marker count are links: into the atlas filtered to that type, and into its marker
        table.</li>
    <li><strong>UMAP &mdash; this re-analysis</strong> shows the same map as a static image coloured by cell type
        and by Leiden cluster, for use in a figure or a slide where the interactive canvas is no good.</li>
    <li><strong>Quality control and provenance</strong> is a collapsible panel listing what was actually done to
        this dataset: the source BioProject and study, the library type and how confident that assignment is, how
        many libraries were integrated, the cell counts before and after filtering, how many doublets were removed
        and by what method, the filtering strategy and thresholds, the median and 95th-percentile mitochondrial
        percentage before and after, the integration method, and how the cell-type labels were obtained &mdash;
        inherited from the publication, assigned here from a cnidarian marker panel, or not assigned at all. Rows
        with no value are omitted rather than shown blank.</li>
</ul>

<div class="manual-note"><strong>The published branch.</strong> When a dataset is shown as published you get the
source study&rsquo;s figure (one or two, depending on what was deposited), a callout explaining why only one is
shown if that is the case, and chips for the cell types recorded for that dataset, each showing how many marker
genes it has and linking into the marker table. Where an interactive re-analysis of the same dataset exists, a
link to it is offered. The figure is the authors&rsquo; own image: check the publication before reusing it.</div>

<div class="manual-tip"><strong>Datasets that have not been re-analysed are listed, not hidden.</strong> The
<a href="/sn_data.php">Single-cell Data</a> page carries every deposit, and for the ones that were not
re-analysed it states the reason rather than leaving a blank &mdash; count matrix not retrievable, counts not
deposited, normalised values only, scATAC rather than scRNA-seq, and so on. A dataset missing from the
interactive list is therefore not necessarily a poor dataset; often it is one whose deposited files do not permit
re-clustering.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.12</span>
                    <img src="./manual_images/atlas_sel.png" alt="Cell atlas selector" data-caption="Choose the species, developmental stage and tissue." loading="lazy" decoding="async" />
                    <figcaption>Choose the species, developmental stage and tissue.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.13</span>
                    <img src="./manual_images/atlas_umap.png" alt="Cell atlas UMAP plots" data-caption="UMAP plots of the cell atlas, coloured by cluster and by cell type." loading="lazy" decoding="async" />
                    <figcaption>UMAP plots of the cell atlas, coloured by cluster and by cell type.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="cell-markers" data-title="cell markers top 15 gene markers cluster id log2FC FDR">
            <h3>4.6 Cell Marker</h3>

<p><strong>What it does.</strong> For a dataset and a cell type it lists the genes that are most specifically
expressed in that cell type relative to all the other cells &mdash; the markers that define it. This is the page
to open when you want to know what a cluster <em>is</em>, or when you need a gene to plot on the atlas and want
one you already know is characteristic.</p>

<p><strong>Why you would use it.</strong> Three uses. Checking an annotation: if a cluster was called
&ldquo;neuron-like&rdquo;, its marker list is the evidence. Finding a gene: the top marker for a cell type is
usually the best gene to plot on the atlas to show that type clearly. And getting a gene list to carry somewhere
else &mdash; markers from one cell type are a natural query list for the co-expression network in
<a href="#coexpression">4.3</a>.</p>

<p class="manual-subhead">Choosing what to look at</p>
<ul>
    <li><strong>Cell-type chips.</strong> An <em>all</em> chip followed by one chip per cell type. The number
        beside a chip does not always mean the same thing: for a re-analysed dataset it is the number of
        <em>cells</em>, and for a published dataset it is the number of <em>marker genes</em>. Where the chips read
        <code>cluster 1</code>, <code>cluster 2</code> and so on, the clusters were never assigned cell-type names
        &mdash; the page says so explicitly rather than inventing labels.</li>
    <li><strong>Dataset.</strong> The same picker as on the <a href="#cell-atlas">Cell Atlas</a> page, covering
        both re-analysed and published datasets, and it keeps your current cell type when you switch.</li>
</ul>

<p class="manual-subhead">The results table</p>
<p>The columns depend on where the dataset came from, and the difference is worth understanding before comparing
two datasets:</p>
<ul>
    <li><strong>Re-analysed datasets:</strong> <em>Cell type</em>, <em>Rank</em>, <em>Gene</em>,
        <em>log<sub>2</sub> FC</em>, <em>Score</em>, <em>% detected</em> and a <em>Plot</em> column.</li>
    <li><strong>Published datasets:</strong> <em>Cell type</em>, <em>Rank</em>, <em>Gene</em>, <em>Symbol</em>,
        <em>log<sub>2</sub> FC</em> and <em>% detected</em>. No Score and no Plot column &mdash; the published
        tables carry no test statistic, so the column is omitted rather than filled with something that looks
        like one, and their expression cannot be plotted because there is no count matrix behind them.</li>
</ul>
<p><strong>Score</strong> is the Wilcoxon rank-sum statistic; <strong>% detected</strong> is the percentage of
cells in that cell type in which the gene was detected at all. The gene name links to its expression page, and
the <em>Plot</em> column offers two views: <em>feature</em>, the gene on the UMAP, and <em>violin</em>, its
distribution across cell types.</p>
<p>Arriving from a gene page with a gene in hand sets the cell type to whichever one that gene ranks highest in,
and highlights its row &mdash; so a link can drop you straight onto the relevant marker.</p>

<div class="manual-tip"><strong>Filter on fold change and detection, not on significance.</strong> In a dataset of
tens of thousands of cells the adjusted p-value is minute for almost every gene, so a ranking by significance
alone is not informative. Read <strong>log<sub>2</sub> FC</strong> together with <strong>% detected</strong>: a
large fold change detected in a small fraction of cells is a different claim from a moderate one detected in most
of them. Showing all cell types lists the top 15 markers each; selecting a single cell type lifts that limit and
lists every ranked row.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.14</span>
                    <img src="./manual_images/cell_marker.png" alt="Cell markers results" data-caption="Top 15 markers and the detailed marker statistics for the selected cell type." loading="lazy" decoding="async" />
                    <figcaption>Top 15 markers and the detailed marker statistics for the selected cell type.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="singlecell-gene" data-title="single-cell gene expression UMAP violin plot cell types">
            <h3>4.7 Gene Expression (Single-cell)</h3>

<p><strong>What it does.</strong> It answers the most direct question in single-cell work &mdash; <em>which cells
express my gene?</em> &mdash; by colouring every cell on the UMAP by the gene&rsquo;s expression, and then showing
that expression broken down by cell type.</p>

<p><strong>Why you would use it.</strong> A marker table tells you a gene is specific to a cell type on average.
This page shows you the cells themselves: whether expression is confined to one clean group or smeared across
several, whether it looks like a gradient, and how many cells in each type express it. It is also the honest way
to check a claim before making it &mdash; averaging hides heterogeneity, and a plot does not.</p>

<p class="manual-subhead">The inputs</p>
<ol>
    <li><strong>Dataset.</strong> Re-analysed datasets only &mdash; a per-cell expression plot is computed from a
        count matrix, so a dataset deposited as a published figure has nothing to draw from. If the species you
        are interested in has only published figures, the page says so and points you to its marker table
        instead.</li>
    <li><strong>Gene.</strong> A gene ID, with autocompletion drawn from the genes that have expression files for
        that dataset, and a placeholder suggesting you take an ID from the marker table. This is the one real
        constraint: <strong>only genes ranked as markers in that dataset have expression files</strong>, typically
        several hundred to about a thousand per dataset, and the page states the exact number for the dataset you
        have open. A gene that is not on that list is not &ldquo;not expressed&rdquo;; it simply has not been
        exported for plotting.</li>
    <li><strong>Plot.</strong> Draws it.</li>
</ol>

<p class="manual-subhead">Reading the plot</p>
<ul>
    <li>Each cell is coloured by the gene&rsquo;s expression on a <strong>single-hue sequential ramp</strong>, from
        a pale floor up to full colour, so intensity can be compared across cells without the reader having to
        decode a rainbow. Cells that do not express the gene are drawn in a distinct neutral colour rather than at
        the bottom of the ramp &mdash; &ldquo;not expressed&rdquo; and &ldquo;expressed a little&rdquo; must not look
        the same.</li>
    <li>The distribution panel is retitled with the gene and shows one distribution per cell type, again limited
        to the top 14 cell types by mean expression for legibility, with the percentage of cells in that type that
        express the gene printed alongside.</li>
    <li>Below the viewer, an <strong>About &lt;gene&gt;</strong> table records how many cells it was detected in
        (with the percentage), which dataset that count came from, and a link to the source study&rsquo;s
        BioProject.</li>
</ul>

<div class="manual-tip"><strong>What this page cannot tell you.</strong> It shows expression in one dataset at a
time &mdash; it is not a cross-species comparison, and a gene ID from another species will not match. For a
cross-condition or cross-species view, use the bulk expression modules instead: the co-expression network in
<a href="#coexpression">4.3</a> and the dynamic expression view in <a href="#dynamic-expression">4.4</a>.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.15</span>
                    <img src="./manual_images/geneexp_form.png" alt="Single-cell gene expression query" data-caption="Query gene expression by species, stage, tissue and gene ID." loading="lazy" decoding="async" />
                    <figcaption>Query gene expression by species, stage, tissue and gene ID.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.16</span>
                    <img src="./manual_images/singlecell_gene_2.png" alt="UMAP of gene expression" data-caption="UMAP plot for gene expression across cell types." loading="lazy" decoding="async" />
                    <figcaption>UMAP plot for gene expression across cell types.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.17</span>
                    <img src="./manual_images/singlecell_gene_3.png" alt="Violin plot of gene expression" data-caption="Violin plot for gene expression across cell types." loading="lazy" decoding="async" />
                    <figcaption>Violin plot for gene expression across cell types.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="proteomic" data-title="proteomic analysis protein abundance q-value coverage peptides PSMs">
            <h3>4.8 Proteomic Analysis</h3>

<p><strong>What it does.</strong> The proteome menu holds two pages that answer two different questions.
<strong>Proteomic Data</strong> is the catalogue: which proteomic datasets exist, for which species and tissue,
and where the raw data were deposited. <strong>Proteomic Analysis</strong> is the measurement end: for one
dataset, which proteins were identified and how confidently. Start from the catalogue to find out whether a
species has been studied at all, then move to the analysis page to read the results.</p>

<p class="manual-subhead">Proteomic Data &mdash; the catalogue</p>
<ul>
    <li><strong>Species.</strong> Choose one species, or <em>All species</em> to see everything. Deep links from a
        species page arrive with the species already selected, and the page tells you when it is showing a
        filtered view and offers a link back to the full list.</li>
    <li><strong>View.</strong> Runs the filter.</li>
    <li><strong>The table</strong> has one row per dataset: <em>Class</em>, <em>Species</em>, <em>Tissue</em>,
        <em>Treatment</em>, <em>Data Sources</em> and <em>Reference</em>. <em>Data Sources</em> links to the
        deposit at PRIDE, which is where the raw spectra live; <em>Reference</em> links to the publication.</li>
</ul>
<p>This page states no measurements, and that is the point of it &mdash; it is the provenance record. If a
species has no row here, no proteomic data are held for it.</p>

<p class="manual-subhead">Proteomic Analysis &mdash; the measurements</p>
<ol>
    <li><strong>Species.</strong> Only species with a processed dataset appear. This list is narrower than the
        catalogue&rsquo;s: a dataset can be catalogued as deposited without having been reprocessed into a protein
        table here.</li>
    <li><strong>Stages.</strong> In the current release this is <em>Adult tissues/organs</em> for every species
        &mdash; the datasets are adult tissue proteomes. The control is present because the module is built to
        take developmental series as they are added.</li>
    <li><strong>Emb/Org.</strong> The sample: a tissue, an organ, or a separated partner such as the symbiont
        fraction. The list depends on the species you chose, and the page corrects the sample automatically if the
        one carried over does not exist for the new species.</li>
    <li><strong>View Samples.</strong> Loads the protein table.</li>
</ol>

<div class="manual-note"><strong>The columns are not the same for every dataset.</strong> Each dataset was
searched with the software its own study used, so its table carries that software&rsquo;s columns &mdash;
q-value, coverage, peptides, unique peptides, PSMs, intensity, score, mass, modifications, and so on &mdash; and
no single column list describes them all. What is common to every dataset is <em>Species</em>, <em>Tissue</em> and
<em>Protein</em>; the quantification and confidence columns that follow differ. Read the header of the table you
have in front of you rather than assuming a column means the same thing it meant in another dataset, and note
that a q-value and a posterior error probability are related but not identical measures.</div>

<p>A free-text box above the table filters the rows on screen by protein, peptide or intensity, and pagination
shows 10 rows per page by default with 20, 50 or 100 selectable. Where the chosen combination has no dataset, the
table says so in words instead of raising a database error.</p>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.18</span>
                    <img src="./manual_images/proteomic.png" alt="Proteomic analysis" data-caption="Proteomic Analysis: select species/stage/organ and review identified proteins with q-value, coverage, peptides and PSMs." loading="lazy" decoding="async" />
                    <figcaption>Select species, stage and organ, then review the identified proteins with their quantification statistics.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="epigenomic" data-title="epigenomic analysis ATAC-seq ChIP-seq feature distribution methylation">
            <h3>4.9 Epigenomic Analysis</h3>

<p><strong>What it does.</strong> The epigenome menu covers two layers of the same subject. <strong>Epigenomic
Data</strong> is the overview: every epigenomic sample held, of every assay type, with its public accessions.
The five assay pages &mdash; <strong>DNA Methylation</strong>, <strong>ATAC-seq</strong>, <strong>DNase-seq</strong>,
<strong>ChIP-seq</strong> and <strong>miRNA-seq</strong> &mdash; are gene-centric: you give a gene, and they return
the regulatory features recorded at its locus.</p>

<p><strong>Why the split matters.</strong> The overview answers &ldquo;what has been done in this species?&rdquo;
The assay pages answer &ldquo;what is happening at my gene?&rdquo; They cover different species sets: an assay is
only queryable for the species whose data have been processed into a searchable table, and that set is narrower
than the overview. Each assay page names the species it currently covers in its own species list &mdash; and the
overview is the place to look if you want to know whether an assay exists for a species at all.</p>

<p class="manual-subhead">Epigenomic Data &mdash; the overview</p>
<ol>
    <li><strong>Class</strong>, then <strong>Species</strong>, then <strong>Epigenome Type.</strong> The three are
        linked: the species list follows the class, the type list follows the species, and choosing a value that
        does not exist for the current selection corrects the selection rather than returning nothing.</li>
    <li><strong>View Samples.</strong> Loads the sample table.</li>
</ol>
<p>The table gives one row per sample: <em>Class</em>, <em>Species</em>, <em>Type</em>, <em>Project</em>,
<em>Study</em>, <em>Experiment</em>, <em>Tissue</em>, <em>Dev Stage</em>, <em>Treatment</em> and
<em>Description</em>. The assay type is shown as a colour-coded badge, and the accession columns link out to NCBI
so a sample can be traced back to its deposit. A total-sample counter sits above the table, and the page size is
selectable from 10 to 100. The last four columns reproduce what the depositor wrote, and where the deposit says
nothing about one of them the cell shows a dash: for <em>Description</em> that is the case for most samples, so a
column of dashes there means the source records are terse, not that the field went missing on the way in.</p>

<div class="manual-note"><strong>Re-apply your filter after turning a page.</strong> The per-page selector keeps
your class, species and assay type, but the page-number links themselves return to the default selection. If you
are stepping through a long result set, raise the page size rather than paging, or re-select the species after
each page.</div>

<p class="manual-subhead">The five assay pages</p>
<p>All five work the same way, with two differences worth knowing. You choose a species, then a sample (for
ChIP-seq, a histone modification and then a sample), then type a gene ID; the page returns the features recorded
near that gene, each with its coordinates, its signal, and the distance from the feature to the gene&rsquo;s
transcription start site. The gene links through to its own page and every row offers a link into the genome
browser, so a peak can be inspected in its sequence context.</p>
<ul>
    <li><strong>DNA Methylation</strong> returns methylation records per motif type, with the methylation level,
        the peak location, the gene&rsquo;s coordinates and length, and the distance to the TSS &mdash; the same
        layout as the peak pages, but reporting a level rather than a count.</li>
    <li><strong>ATAC-seq</strong> and <strong>ChIP-seq</strong> return open-chromatin and histone-mark peaks
        respectively, and share a column set. <strong>DNase-seq</strong> returns the same kind of peak table for
        DNase hypersensitive sites, for the one species whose data have been processed.</li>
    <li><strong>miRNA-seq</strong> is the exception: it is not gene-centric and takes no gene ID. It is also split
        across two pages. On <strong>miRNA-seq Analysis</strong> you choose one species and press <em>View miRNA
        Details</em>; the page states how many species currently have miRNA records, and in this release that is
        four. <strong>MiRNA Details</strong> then opens in a new tab with that species&rsquo; annotated microRNAs:
        MirGeneDB and miRBase identifiers, family, seed, the 5p and 3p accessions, chromosome, start, end and
        strand, followed by expression values. It is also the only page in this group offering downloads &mdash;
        precursor, mature, star, loop, 5p and 3p sequences as FASTA files, plus an expression matrix where one
        exists.</li>
</ul>

<div class="manual-note"><strong>The miRNA expression columns are not the same columns for every
species.</strong> Each species was profiled in its own study, so the cells to the right of <em>Strand</em> are
named after <em>that</em> study&rsquo;s samples &mdash; a developmental series for one species, tentacle and trunk
fragments for another, a regeneration time course for a third. Column&nbsp;11 therefore means something different
depending on the species you selected. The values are the study&rsquo;s own expression values, shaded by colour
against a fixed maximum for that species, so they are <em>not</em> comparable between species: read them as
&ldquo;which microRNAs are prominent in this sample&rdquo;, not as a cross-species abundance scale.</div>

<div class="manual-tip"><strong>Two plots are shown before you query.</strong> Every assay page displays its
distribution plots immediately, using a default species and sample, so the page is never blank. Those plots show
the shape of the dataset &mdash; how the features are distributed across the genome and across samples &mdash; and
they change when you submit a query. Read them before the table: they tell you whether the peak count you are
about to see is typical for that sample or an outlier.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.19</span>
                    <img src="./manual_images/epigenomic.png" alt="Epigenomic analysis" data-caption="Epigenomic Analysis: query by species/stage/organ and view the feature distribution across samples." loading="lazy" decoding="async" />
                    <figcaption>Query by species, stage and organ, then view the feature distribution across samples.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="mags" data-title="metagenome-assembled genomes MAGs contig N50 checkM completeness contamination">
            <h3>4.10 Metagenome-Assembled Genomes</h3>

<p><strong>What it does.</strong> Metagenomics here means sequencing everything in a sample at once, host and
microbes together, and then trying to reconstruct individual microbial genomes from the mixture. Those
reconstructions are <strong>MAGs</strong> &mdash; metagenome-assembled genomes. This module lists the MAGs
recovered from cnidarian-associated samples and reports how complete and how contaminated each one is, which is
the only way to judge whether it can be used for anything.</p>

<p><strong>Why you would use it.</strong> Cnidarians live with microbial partners, and a MAG is often the only
genome available for a symbiont or a gut microbe that cannot be cultured. Before using one, though, you need to
know what you have: a MAG is a draft, assembled from a mixed sample, and it may be missing genes or contain
sequence from more than one organism.</p>

<p class="manual-subhead">The controls</p>
<ol>
    <li><strong>Class</strong> and <strong>Species</strong> &mdash; the species here is the <em>host</em> the
        sample came from, not the organism the MAG represents. The two lists constrain each other: choosing a
        class selects its first host, and choosing a host moves the class to match it.</li>
    <li><strong>View Samples.</strong> Loads the MAGs.</li>
</ol>
<p>A search box filters the rows on screen by assembly accession, species or assembly level, and pagination shows 10 MAGs
per page by default, with 20, 50 or 100 selectable.</p>

<p class="manual-subhead">The results table, column by column</p>
<ul>
    <li><em>Class</em> and <em>Host</em> &mdash; the cnidarian the sample was taken from. <em>Species</em> is a
        different column and means something else: the taxonomic assignment of the <em>MAG itself</em>, linking to
        its NCBI Taxonomy record. Reading the two as the same thing is the easiest mistake to make on this
        page.</li>
    <li><em>Assembly Accession</em> and <em>Assembly Level</em> &mdash; the MAG&rsquo;s own identifier and how far
        the assembly was taken (scaffold, contig, and so on). The accession links to NCBI Datasets.</li>
    <li><em>Assembled Size (bp)</em>, <em>Nr.contigs</em> and <em>Contig N50 (bp)</em> &mdash; how large the
        assembly is and how fragmented. N50 is the length at which half the assembly is in contigs of that size or
        longer: a high N50 with few contigs means a more contiguous reconstruction, and many small contigs means a
        fragmented one.</li>
    <li><em>GC Percent (%)</em> &mdash; a rough fingerprint of the organism, and a useful sanity check. An
        unusually skewed GC content in a MAG that is also highly contaminated suggests the assembly has pulled in
        sequence from more than one organism.</li>
    <li><em>CheckM completeness (%)</em> and <em>CheckM contamination (%)</em> &mdash; the two numbers that decide
        whether a MAG is usable. CheckM counts how many of a set of genes that should be present exactly once in a
        bacterial or archaeal genome are actually present: <strong>completeness</strong> is the percentage found,
        and <strong>contamination</strong> is the percentage found more than once, which is the signature of
        sequence from a second organism having been assembled in.</li>
    <li><em>Paper</em> &mdash; the study the MAG was published in.</li>
</ul>

<div class="manual-tip"><strong>How to read completeness and contamination together.</strong> The widely used
convention is that a MAG above <strong>90% completeness</strong> with under <strong>5% contamination</strong> is
high quality, and one above 50% completeness with under 10% contamination is medium quality. Treat these as
guidance rather than a verdict: a 95%-complete MAG with 8% contamination is <em>not</em> a better genome than an
85%-complete one with 1% contamination &mdash; the first is more complete but has foreign sequence mixed in, and
downstream analyses such as phylogenetics will be misled by it. For anything quantitative, choose the cleaner
genome, not the fuller one.</div>

<div class="manual-note"><strong>The samples behind the MAGs.</strong> The <a href="/metagenomic_data.php">
Metagenomic Data</a> page lists the sequencing experiments themselves &mdash; one row per experiment, with its
tissue, developmental stage and treatment, and links to the NCBI deposit. Use it to see what was sequenced before
assembly, and to find the accession of a sample whose MAG you are reading here. The three free-text fields are
reproduced as deposited; a dash in one of them means the deposit does not state it, which for treatment is true of
most of the runs listed.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.20</span>
                    <img src="./manual_images/mags.png" alt="Metagenome-assembled genomes" data-caption="MAGs module: assembly accession, contig N50 and CheckM completeness/contamination statistics." loading="lazy" decoding="async" />
                    <figcaption>MAGs module listing the assembled genomes with their quality statistics.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="phenotype" data-title="phenotypic information trait ecological morphological value region">
            <h3>4.11 Phenotypic Information</h3>

<p><strong>What it does.</strong> It collects the recorded traits of cnidarian taxa &mdash; ecological and
morphological &mdash; into one searchable table, integrating the Octocoral Trait Database, the WoRMS Marine
Species Traits database and the Pelagic Species Trait Database. Each row is one trait recorded for one taxon,
with its value, the region it was recorded in, and the methodology behind it.</p>

<p><strong>Why you would use it.</strong> A trait table is what turns a genome into a biological question. If you
are asking why a lineage lost a gene, or whether a structural feature tracks an ecological strategy, the trait
record is the other half of the comparison. Because the source databases are cited per record, this page is also
usable as a pointer back to the original trait databases.</p>

<p class="manual-subhead">Getting to the records</p>
<p>The page carries a search box of its own, <strong>Search traits</strong>. It matches the text you type
case-insensitively against the class, species, trait name, trait category, value, unit, region and methodology
columns at once, combines with whichever class or species filter you arrived with, and travels in the URL as
<code>q</code>, so a search can be bookmarked or shared. It is a plain server-side filter and works with
JavaScript disabled. The count it reports when it hits is the number of rows matching <em>within the current
filter</em>, not in the whole table. The <em>Clear</em> link beside it removes the term and keeps the filter.
Besides searching, there are two ways to reach a set of records:</p>
<ul>
    <li>From the <strong>Phenotype menu</strong>, which offers <em>All</em> and then one entry per class
        (Cubozoa, Hexacorallia, Octocorallia, Hydrozoa, Scyphozoa). Choosing a class browses everything recorded
        for it.</li>
    <li>From a <strong>species page</strong>, which arrives here with the species already selected. When a filter is
        active the page says so, tells you how many records it found, and offers a link back to the unfiltered
        view &mdash; so a filtered page can never be mistaken for the whole table.</li>
</ul>

<p class="manual-subhead">The results table</p>
<p>Twelve columns: <em>Class</em>, <em>Species</em>, <em>Trait Name</em>, <em>Trait Type</em>, <em>Value</em>,
<em>Trait Unit</em>, <em>Region</em>, <em>Latitude</em>, <em>Longitude</em>, <em>Methodology</em>,
<em>Source</em> and <em>Obtained by</em>. Read
<em>Trait Type</em> first &mdash; it separates ecological records from morphological ones, and the two are not
usually wanted at the same time.</p>
<ul>
    <li><em>Value</em> and <em>Trait Unit</em> belong together: a bare number is meaningless without its unit,
        and units are recorded as the source database gave them, so the same measurement can appear in different
        units in different rows.</li>
    <li><em>Region</em> with <em>Latitude</em> and <em>Longitude</em> locates the record geographically. Latitude
        and longitude are frequently empty even when a region is named &mdash; regional records are often reported
        without coordinates.</li>
    <li><em>Methodology</em> is the provenance of the measurement as the source database worded it, and is the
        column to quote when a trait value matters to an argument.</li>
    <li><em>Obtained by</em> is the site's reading of that provenance, in five grades. <em>Measured</em> means the
        source reports an observation; <em>derived</em> means the source computed it; <em>expert</em> means it is
        an expert judgement; <em>inherited</em> means the value belongs to a higher taxon (a genus or family
        trait) and must not be quoted as a measurement of the species in the row; <em>unknown</em> means the
        source says nothing. Hovering the label shows the source's own wording &mdash; for example a value the
        source calls <code>mean</code> or <code>mid_range</code>.</li>
    <li><em>Source</em> names the contributing database and links to it. Each database is cited per record, so
        the table doubles as a pointer back to the original trait databases.</li>
</ul>
<p>Each row carries the single value its source recorded &mdash; not a range, not a set of replicates. Where the
source database held several identical entries they are folded into one row and the value is followed by
<code>&times;N</code>, meaning <em>N</em> identical entries. A few rows still hold a source-database code rather
than a reading; these are marked <em>not a value</em> in the <em>Obtained by</em> column and the code's meaning is
printed in brackets next to it.</p>
<p>Record counters sit above the table: the number of records shown, and in brackets the number of source rows
they came from. Pagination shows 10 records per page by default, with 20, 50 or 100 selectable, and your class
and species filters are carried across pages.</p>

<p class="manual-subhead">One species at a time</p>
<p>Every species name in the table links to a per-species view, which groups that species' records by trait type
and prints each trait's definition and allowed values as the source database gave them, the source each record
came from, and the literature cited for it. That page is the one to open first when you are checking what is
known about a particular species; the table on this page is for comparing across species.</p>

<p class="manual-subhead">The overview charts</p>
<p>A companion page, <a href="/phenotype_charts.php">Phenotype overview</a>, answers the questions a row-by-row
table cannot: which species have been described for which kinds of trait (a coverage matrix), how each value was
obtained (a stacked bar of measured / computed / expert / inherited), what the numeric traits actually look like
(histograms), and where the records were made (a map). It also lists, with the numbers behind each point, what
the data still cannot answer &mdash; most importantly that only about a tenth of the records carry coordinates,
and that the collection year is stored as one trait among many rather than as a property of the record.</p>

<div class="manual-tip"><strong>Check the coverage before drawing a conclusion.</strong> Trait records are
distributed very unevenly across Cnidaria &mdash; the great majority come from the octocoral trait database, and
several classes have only a handful of records. A comparison across classes is therefore often a comparison of
how much each class has been studied rather than a biological finding. Where a class has few records, the honest
reading is that the trait is under-recorded, not that it is absent.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.21</span>
                    <img src="./manual_images/phenotype.png" alt="Phenotypic information" data-caption="Phenotypic Information: trait name, type, value, region and methodology for each taxon." loading="lazy" decoding="async" />
                    <figcaption>Phenotypic trait records with trait type, value, region and methodology.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="mitogenomic" data-title="mitogenomic data mitochondrial genome map annotation tRNA rRNA CDS download genbank">
            <h3>4.12 Mitogenomic Data</h3>

<p><strong>What it does.</strong> It shows the mitochondrial genome of a species: a circular map of the
mitochondrion, the genome-level statistics, and the full table of annotated features &mdash; protein-coding genes,
tRNAs, rRNAs &mdash; with coordinates and strand, plus the sequence files to download.</p>

<p><strong>Why you would use it.</strong> Mitochondrial genomes are small, strictly maternally inherited and easy to
assemble, so they are the standard marker for species identification, for barcoding and for phylogenetic
placement. In CnidoSite they also cover far more species than the nuclear assemblies do, which makes them the
practical way to check that a species is represented at all.</p>

<p class="manual-subhead">Getting to the data</p>
<ol>
    <li><strong>Select Class</strong>, then <strong>Select Species</strong>, then <em>View mitochondrial
        Details</em>. Only species that have a mitochondrial record appear in the species list, so a species you
        cannot find here has no mitochondrion in this release.</li>
    <li>Deep links from a species page carry the species in; if that species has no mitochondrial record the page
        says so and falls back to one that does, rather than showing you an empty result &mdash; it also states how
        many species do have one.</li>
</ol>

<p class="manual-subhead">The detail card</p>
<ul>
    <li><em>Organism</em> links to the species page, and <em>Accession Number</em> to the NCBI nucleotide record
        the data were taken from.</li>
    <li><strong>Genome-level summary</strong> &mdash; genome size, GC content, the number of protein-coding, tRNA
        and rRNA genes, and the <em>Source</em> of the record with the date it was retrieved, so the numbers can be
        traced back. Where a record is not annotated the counts are shown as a dash rather than <code>0</code>,
        because &ldquo;no genes recorded&rdquo; and &ldquo;no gene count&rdquo; are different statements.</li>
    <li><strong>The map</strong> is the published circular figure where one exists; otherwise it is drawn from the
        feature coordinates of the record. For a species whose record has no per-gene annotation at all, no map is
        drawn and the page explains why &mdash; a generic placeholder image is <em>not</em> shown, since it would
        be mistaken for that species&rsquo; own genome.</li>
    <li><strong>The feature table</strong> has one row per feature: <em>Name</em>, <em>Type</em>, <em>Start</em>,
        <em>End</em>, <em>Length</em> and <em>Strand</em>, with a sticky header so the columns stay labelled while
        you scroll a large mitochondrion.</li>
    <li><strong>Download</strong> &mdash; choose the file type from the list (<em>genome</em>, <em>genbank</em>,
        <em>cds</em>, <em>pep</em>, <em>gff3</em>) and press <em>Download</em>; only the file types that exist for
        that species are offered.</li>
</ul>

<div class="manual-note"><strong>Some species hold genome-level data only.</strong> For those, the page shows the
size, GC content and gene counts from the NCBI record but states plainly that no annotated coordinates exist,
and offers neither a gene table nor per-gene files. You may still want that species for its mitochondrial size or
base composition &mdash; you simply cannot get gene-level annotation from it here. The <em>Mitogenome</em> column
of the <a href="#coverage-matrix">Data Coverage Matrix</a> (2.4) marks the same distinction.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.22</span>
                    <img src="./manual_images/mito_card.png" alt="Mitogenomic Data detail card" data-caption="Mitochondrial detail: circular map and genome summary on the left, the feature table and downloads on the right." loading="lazy" decoding="async" />
                    <figcaption>Mitochondrial detail: circular map and genome summary on the left, the feature table and downloads on the right.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="macrosynteny" data-title="macrosynteny synteny conservation chromosome linkage group Oxford grid BUSCO anchors Fisher">
            <h3>4.13 Macrosynteny Analysis</h3>

<p><strong>What it does.</strong> It compares the chromosomes of <em>two</em> cnidarian species and shows how far
their large-scale gene order has been conserved. Conserved linkage groups &mdash; macrosynteny &mdash; are
inferred from BUSCO single-copy orthologs used as cross-species anchor points: every pair of chromosomes is tested
with a Fisher exact test, and significantly associated chromosomes are merged into linkage groups. The result is an
interactive <strong>Oxford grid</strong>, one dot per shared ortholog, coloured by its linkage group.</p>

<p><strong>Why you would use it.</strong> Gene content alone does not show whether a genome has been rearranged.
Comparing chromosome-scale order between a coral and an anemone, or between two corals, is how you tell a real
fusion, fission or inversion from an assembly artefact &mdash; and it is the evidence behind any claim that a
karyotype is ancestral or derived.</p>

<p class="manual-subhead">Choosing a pair</p>
<ol>
    <li>Pick <strong>Species 1 (X axis)</strong> and <strong>Species 2 (Y axis)</strong>. Both lists hold every
        species in the analysis and are grouped by class, so within-class and between-class comparisons are both
        possible; the header line states how many species and how many pairs have been computed. The comparison is
        undirected &mdash; swapping the two only flips the layout.</li>
    <li>Press <em>View Macrosynteny</em>. Choosing the same species twice, or only one, is rejected before the
        query runs.</li>
    <li>Alternatively, open the <strong>all-pairs heatmap</strong> from the link under the form: one cell per
        species pair, coloured by the statistic you choose (by default the fraction of shared anchors that fall
        inside significant conserved blocks), with the best-conserved pairs listed as a table underneath. Clicking
        a cell opens the same Oxford grid for that pair.</li>
</ol>

<p class="manual-subhead">Reading the result</p>
<ul>
    <li><strong>The grid.</strong> One dot per BUSCO single-copy ortholog shared by the two species, positioned by
        the chromosome it sits on in each. A dense diagonal or block structure is conserved synteny; dots scattered
        across the whole square mean the two chromosomes are not conserved relative to each other. Chromosomes are
        ordered as inferred by the analysis, not by their natural numbering &mdash; chromosome 5 here is not
        necessarily chromosome 5 in the assembly.</li>
    <li><strong>Colour by</strong> linkage group, by significance, or by orientation (&rho;), and set a single
        colour if you only want the pattern of dots. <em>Labels per axis</em> decides how many chromosomes get a
        name written beside them (the top 20, 40 or 60 by anchor count, or all of them). It does not remove
        anything from the grid: every chromosome of both assemblies is always plotted, and those without a name of
        their own are simply covered by the single <em>other</em> label. On a fragmented assembly &mdash; hundreds
        or thousands of contigs &mdash; that roll-up is what keeps the axis readable, so use the labels, the
        row-click zoom and the hover panel to identify a specific contig rather than expanding the axis.</li>
    <li><strong>Moving around a grid you cannot read at a glance.</strong> The grid opens fitted to the window,
        and at that size the anchor dots merge into bands &mdash; it shows the pattern, not individual orthologs.
        To inspect a region: <em>drag</em> the plot to pan; zoom with the <em>&minus;</em>&thinsp;/&thinsp;<em>+</em>
        buttons, with <strong>Ctrl</strong>&nbsp;+&nbsp;mouse wheel (<strong>&#8984;</strong>&nbsp;+&nbsp;wheel on
        a Mac) or by <strong>double-clicking</strong>. Zooming is anchored &mdash; the point under the pointer stays
        under the pointer, so you can aim at a block and enlarge it rather than chasing it. The readout gives the
        current magnification (<em>Fit</em> = the whole grid, <em>100%</em> = full size) and <em>Full screen</em>
        gives the grid the whole window. From about 4&times; the dots separate from one another;        clicking a row of the chromosome-pair table zooms straight to that block, which is the quickest way to
        look at a specific pair. Zooming stops at 12&times; (one anchor = 12 screen pixels), where the dots along a
        conserved block are far enough apart to be counted individually.</li>
    <li><strong>What is in a dot.</strong> Hover any dot for its BUSCO ID, both genes with their chromosome
        positions, and the significance and &rho; of the chromosome pair it belongs to. Click it to <em>pin</em>
        that panel in place &mdash; only then do the gene links inside it become clickable and open the gene pages
        &mdash; and press <em>Esc</em> or click empty space to release it.</li>
    <li><strong>The summary line</strong> gives the number of shared anchors, the number of significant chromosome
        pairs, the number of linkage groups, the percentage of anchors that fall in blocks, and the median
        Spearman &rho; of anchor order: a positive median means the block is overall collinear, a negative one that
        it is overall inverted.</li>
    <li><strong>The chromosome-pair table</strong> lists every tested pair with its anchor count, raw
        <em>p</em>, Benjamini&ndash;Hochberg adjusted <em>q</em>, whether it is significant, its linkage group,
        &rho; and its <em>Orientation</em> (<em>collinear</em> or <em>inverted</em>). Non-significant pairs are
        listed too, so you can see what was tested and not merely what passed.</li>
    <li><strong>Downloads</strong> &mdash; the linkage groups and the underlying anchor pairs, as TSV.</li>
</ul>

<div class="manual-note"><strong>An empty result is a result.</strong> Where a pair has no significant linkage
groups &mdash; the highly fragmented Myxozoa genomes are the usual case &mdash; the page says so instead of
drawing a blank grid, and the heatmap leaves the pair light grey when fewer than 30 anchors were shared, which is
too few to test. Myxozoa genomes are reduced and rearranged to the point that BUSCO anchors are too sparse to
recover chromosome-scale order, so read &ldquo;nothing recovered&rdquo; as a property of the genomes, not as a
failure of the query.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.23</span>
                    <img src="./manual_images/msr_grid.png" alt="Macrosynteny Oxford grid" data-caption="An Oxford grid: one dot per shared BUSCO ortholog, coloured by linkage group, with the significance table underneath." loading="lazy" decoding="async" />
                    <figcaption>An Oxford grid: one dot per shared BUSCO ortholog, coloured by linkage group, with the significance table underneath.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.24</span>
                    <img src="./manual_images/msr_heat.png" alt="Macrosynteny all-pairs heatmap" data-caption="The all-pairs heatmap: one cell per species pair, colouring the chosen conservation statistic." loading="lazy" decoding="async" />
                    <figcaption>The all-pairs heatmap: one cell per species pair, colouring the chosen conservation statistic.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="proteomic-reanalysis" data-title="proteomic re-processing reanalysis Comet Percolator FDR reproducibility original study parameters PRIDE">
            <h3>4.14 Proteomic Re-analysis</h3>

<p><strong>What it does.</strong> The proteomic datasets in CnidoSite were not all searched with the same
software. Left as published, one dataset&rsquo;s &ldquo;q-value&rdquo;, &ldquo;coverage&rdquo; and
&ldquo;peptide count&rdquo; would mean something different in each of them, and none of the protein lists would
link to a CnidoSite gene. This module answers that: the raw files are retrieved from
<a href="https://www.ebi.ac.uk/pride/archive/" target="_blank" rel="noopener">PRIDE</a> and, wherever a CnidoSite
reference proteome exists for the species, searched again through one documented pipeline
(<strong>Comet 2026.01 + Percolator</strong>, 1% PSM-level FDR) so that identifications are comparable across
datasets and every protein maps to a gene page.</p>

<p><strong>Why you would use it.</strong> Three reasons, and the pages are organised around them. To see how many
datasets could be reprocessed at all and what stopped the rest; to compare what the original study reported with
what this server applied; and to reproduce the search yourself from the commands the pipeline actually ran.</p>

<p class="manual-subhead">The dataset list and the status vocabulary</p>
<p>The list has one row per dataset &mdash; class, species, tissue, treatment, data source, reference, a
<em>Status</em>, and the number of gene-linked proteins in the current release &mdash; with links to the dataset
details and to its proteins. The summary strip above counts datasets, re-processed datasets, gene-linked proteins,
and each failure mode. Only statuses that occur are shown, so a release with none of a given problem does not
carry a permanent zero. The <em>Status</em> column is the honest part of the module, and it distinguishes:</p>
<ul>
    <li><em>Re-processed</em> &mdash; searched against the CnidoSite reference proteome and gene-mapped.</li>
    <li><em>Searched, no IDs at 1% FDR</em> &mdash; the search ran and nothing passed the threshold. This is a
        real negative result, not a missing run: it usually means the deposited spectra come from a sample whose
        proteome is too distant from any CnidoSite reference.</li>
    <li><em>PSMs only, no peptides at 1%</em> &mdash; spectra matched, but nothing survived at the peptide level,
        so no protein list is reported.</li>
    <li><em>No reference proteome</em> &mdash; no CnidoSite genome for that species, so there is nothing to search
        against. The dataset is still catalogued.</li>
    <li><em>Queued</em> &mdash; not yet processed.</li>
    <li><em>Original DB required</em> &mdash; the study used a sequence database that cannot be substituted, so
        re-processing would not be a like-for-like comparison.</li>
</ul>

<p class="manual-subhead">Inside a dataset page</p>
<ul>
    <li><strong>Header</strong> &mdash; species, class, tissue and treatment, the instrument, the PRIDE project and
        the publication, and how many raw files were used.</li>
    <li><strong>Original study vs CnidoSite re-processing</strong> &mdash; a two-column comparison row by row:
        search engine, sequence database, enzyme, precursor and fragment tolerance, fixed and variable
        modifications, decoy strategy, false discovery rate, quantification and contaminant handling. Where the
        original study did not state a parameter the cell says so rather than guessing, and where CnidoSite chose
        one the value is given. This is the table to cite when a reviewer asks whether your comparison is
        legitimate.</li>
    <li><strong>Outcome of re-processing</strong> &mdash; PSMs, peptides and gene-linked proteins at
        q&nbsp;&le;&nbsp;0.01, plus the number of contaminants identified and excluded, and the pipeline version
        and release the dataset was incorporated in.</li>
    <li><strong>Reproducing this dataset</strong> &mdash; the pipeline as the six commands that were run, from
        fetching the peak lists to loading the tables. The parameter file written by the search stage is the one
        shown in the comparison table above, so the two agree by construction.</li>
    <li><strong>Most strongly supported proteins</strong> &mdash; gene and protein identifiers, unique peptides,
        PSMs, coverage, length, best q-value and description, with each gene linked to its page.</li>
    <li>The <strong>proteins</strong> link opens the full protein list for the dataset, paginated, where the
        peptide-level evidence behind each protein can be inspected.</li>
</ul>

<div class="manual-note"><strong>Re-analysis is not the same as the original result, and does not replace
it.</strong> A number here was produced by this server&rsquo;s pipeline on this release&rsquo;s reference proteome.
It will differ from the published count, sometimes substantially, and the difference is informative: a lower count
usually means a stricter, uniform threshold rather than a worse search. Cite the original study for the biology and
this module for comparability and for the gene-level links.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.25</span>
                    <img src="./manual_images/prot_list.png" alt="Proteomic dataset list and status counts" data-caption="The dataset list: one row per dataset with its status, gene-linked protein count and links to details and proteins." loading="lazy" decoding="async" />
                    <figcaption>The dataset list: one row per dataset with its status, gene-linked protein count and links to details and proteins.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 4.26</span>
                    <img src="./manual_images/prot_dataset.png" alt="Original study versus CnidoSite re-processing" data-caption="Inside a dataset: the parameter-by-parameter comparison between the original study and the CnidoSite re-processing, with the commands that reproduce it." loading="lazy" decoding="async" />
                    <figcaption>Inside a dataset: the parameter-by-parameter comparison between the original study and the CnidoSite re-processing, with the commands that reproduce it.</figcaption>
                </figure>
            </div>
        </article>
    </section>

    <!-- ============ Chapter 5 ============ -->
    <section class="manual-chapter" id="ch5">
        <div class="manual-chapter-head">
            <div class="manual-chapter-num">5</div>
            <div>
                <h2>Analysis Tools</h2>
                <p class="manual-chapter-sub">Enrichment analysis, BLAST, primer design and genome browsing.</p>
            </div>
        </div>

        <article class="manual-section" id="gsea" data-title="gene sets enrichment analysis GSEA GO KEGG background gene list DEGs">
            <h3>5.1 Gene Set Enrichment Analysis</h3>
            <p>The page names itself <em>Gene Set Enrichment Analysis</em>; the entry that reaches it in the Tools menu is the shorter <em>Gene Sets Analysis</em>. Both refer to GSEA. The tool tests a query gene list for enrichment against the gene sets you choose, using a background gene list that you supply.</p>
            <ol>
                <li>Choose the target species.</li>
                <li>Choose the gene sets to test &mdash; Gene Ontology, gene family, KEGG or protein domain.</li>
                <li>Input your query gene list, such as a DEG list or a co-expressed gene list.</li>
                <li>Choose the background genes &mdash; the whole genome, or your own gene list or background
                    file &mdash; and adjust the parameters if the defaults do not suit your data.</li>
                <li>Start the analysis.</li>
            </ol>
            <p>The output reports detailed information about the significantly enriched gene sets. The results page opens with a summary card (job ID, the categories you selected, the number of query genes after redundancy removal, and the significance cut-off), then an <em>enrichment figure</em>, then the table of significant sets with their p-value and FDR.</p>
            <p><strong>Reading the enrichment figure.</strong> One row is one gene set, grouped by category; the row label carries the category code (BP, CC, MF, UP, TF, KEGG, DOMAIN) so the grouping survives greyscale printing. By default the fill of each point is the Benjamini&ndash;Hochberg adjusted p-value (FDR) on a single-hue scale, and the <em>value range of that scale is printed beside the colour bar on the right</em>: the top of the bar is the most significant end and the numbers mark round FDR values inside the displayed range, so you can read any shade off the bar without going back to the table. If the sets are so significant that no round value falls inside the range, the two ends of the range are printed instead, with their exact values.</p>
            <p><strong>The controls</strong> sit above the figure and it redraws as you change them, FDR labels included: <em>Plot type</em> (dot plot or bar plot), <em>Categories</em> (a checkbox per category, with its number of terms, plus <em>GO only</em> / <em>KEGG only</em> / <em>All</em> / <em>Clear</em> shortcuts), <em>Terms per category</em> (top 10, 20, 30, 50 or all) and <em>Colour by</em> (adjusted p-value, the default, or category). Two design consequences are worth knowing. Choosing <em>Category</em> colouring replaces the FDR colour bar with a category legend &mdash; that is intended, not a missing scale &mdash; and it only applies while three or fewer categories are selected: with more than three the figure falls back to the FDR scale on its own, because no palette keeps every pair of colours distinguishable for colour-blind readers, and the page says so when it happens. The figure also exports, through its own <em>Export</em> buttons, to SVG or PNG for a manuscript; the export runs entirely in your browser and sends nothing to a third party.</p>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 5.1</span>
                    <img src="./manual_images/gsea.png" alt="Gene set enrichment analysis" data-caption="GSEA tool: choose species, gene sets and background, input a gene list and start the analysis." loading="lazy" decoding="async" />
                    <figcaption>GSEA tool: choose the species, gene sets and background, input a gene list and start the analysis.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="blast" data-title="BLAST alignment blastp blastn blastx tblastn tblastx fasta sequence search results evalue matrix all databases whole collection">
            <h3>5.2 BLAST Alignment</h3>
            <p>The BLAST tool searches your own sequences against the CnidoSite sequence databases, so a gene you have cloned, an assembly you have made or a primer you are checking can be placed against every species held here.</p>

            <p class="manual-subhead">The form</p>
            <ol>
                <li><strong>BLAST Program</strong> &mdash; <em>blastp</em> (protein against protein), <em>blastn</em>
                    (nucleotide against nucleotide), <em>blastx</em> (translated nucleotide against protein),
                    <em>tblastn</em> (protein against translated nucleotide) or <em>tblastx</em> (translated nucleotide against translated nucleotide). The program decides
                    which databases are offered: protein databases for the protein queries, nucleotide ones for the
                    nucleotide queries.</li>
                <li><strong>Search Database</strong> &mdash; <strong>*&nbsp;All databases</strong> for the whole
                    collection (the default selection, and the right way to start with a sequence whose origin you
                    do not know), or a single species by its Latin name once you do. The list is built from the
                    databases that actually exist on the server &mdash; currently <strong>148 protein</strong> and
                    <strong>287 nucleotide</strong> databases, one or more per species &mdash; so what you can choose
                    is what can really be searched, and the count shown on the page is never stale.</li>
                <li><strong>Query Sequence</strong> &mdash; paste FASTA, or upload a file up to 5&nbsp;MB. More than
                    one record is allowed in a single search. <em>Load Example</em> fills in a working sequence.</li>
                <li><strong>Parameters</strong> &mdash; the expected threshold (<em>E-value</em>, default 10; lower is
                    stricter), the scoring matrix (default BLOSUM62 for protein searches), ungapped alignment, and
                    whether to include the graphical overview. <em>Descriptions</em> and <em>Alignments</em> set how
                    many hits are reported (defaults 100 and 50).</li>
            </ol>

            <div class="manual-note"><strong>A whole-collection search normally takes one to two minutes, and that
            is expected.</strong> <em>All databases</em> searches every database in a single pass rather than the
            one you guessed, and it answers the question a single-species search cannot: whether your sequence
            occurs anywhere in the phylum, and in which species. Wait for it &mdash; the run is working, not
            stalled. Do not resubmit the form or reload while it is going, because that starts a second search and
            you end up waiting twice. A single-species search still returns in a second or two, so switch to it
            when you already know which species you need; the answer is then the same one the whole-collection
            search would have given for that species.</div>

            <p class="manual-subhead">Reading the results</p>
            <p>Each hit is one row: <em>Query ID</em> and <em>Subject ID</em> (the matched database sequence), the
            <em>Species</em> it belongs to (a column shown when <strong>All databases</strong> is searched),
            <em>Alignment Length</em>, <em>MisMatches</em>, the <em>Query Start/End</em>
            and <em>Subject Start/End</em> coordinates, <em>% Identity</em>, <em>E-value</em> and <em>Bit Score</em>
            &mdash; with the graphical overview above the table when that option is on. Read the E-value together
            with the identity and the alignment length: a short 100%-identical hit inside a repeat or low-complexity
            region is usually not a homolog, whereas a long alignment at 30&ndash;40% identity spanning the whole
            length of a protein usually is.</p>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 5.2</span>
                    <img src="./manual_images/blast_1.png" alt="BLAST search form" data-caption="BLAST tool: choose program and database, input a FASTA sequence and set the search parameters." loading="lazy" decoding="async" />
                    <figcaption>BLAST tool: choose the program and database, input a FASTA sequence and set the search parameters.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 5.3</span>
                    <img src="./manual_images/blast_2.png" alt="BLAST results" data-caption="BLAST results table with alignment statistics." loading="lazy" decoding="async" />
                    <figcaption>BLAST results table with the alignment statistics for each hit.</figcaption>
                </figure>
            </div>

            <div class="manual-tip"><strong>Other ways to find a sequence you already have.</strong> If you know the
            gene identifier, <a href="#gene-search">Gene Search</a> (3.1) is exact and instant. If you want the genes
            that share a protein domain rather than the sequences that resemble yours, use the
            <a href="#domain-search">Functional Domain Search</a> (3.8).</div>
        </article>

        <article class="manual-section" id="primer" data-title="primer design primer3 forward reverse Tm GC product size PCR template local">
            <h3>5.3 Primer Design</h3>
            <p>The Primer Design tool designs PCR primers from a DNA sequence. It runs <strong>primer3 2.6.1 on this
            server</strong> &mdash; the same engine as the familiar Primer3 web tools, but executed locally, so
            <em>the sequence you paste is never sent to a third-party site</em>. That matters for unpublished or
            restricted sequences, and it means the tool keeps working when an external service does not.</p>

            <p class="manual-subhead">The form</p>
            <ol>
                <li>Paste the template sequence, or choose a species and a gene / transcript ID. <strong>If the gene
                    ID resolves, its sequence is used and the pasted sequence is ignored.</strong> There is no
                    &ldquo;included region&rdquo; field: primer3 searches the whole template, so if you want the
                    product confined to one stretch, paste just that stretch.</li>
                <li>Adjust the primer constraints if the defaults do not suit your template: primer length, melting
                    temperature, GC content, maximum self-complementarity, and the salt and concentration conditions
                    used for the Tm calculation. The defaults are the standard ones and are appropriate for most
                    cnidarian templates. If you need a hydrolysis probe, tick <em>Internal oligo (probe)</em>:
                    primer3 will then design one inside the amplicon, and it is drawn on the map below.</li>
                <li>Submit. The result lists candidate primer pairs, giving the Tm, GC content and penalty of each
                    primer, and the size and position of the product in the template.</li>
            </ol>

            <p><strong>The primer map.</strong> Below the table, the best-scoring pair is also drawn: the whole
            template as a bar with the product band and both primers at their true positions, and under it the
            amplicon enlarged, where each primer occupies its own block with its sequence inside, an arrow showing
            which way it runs, and the probe if one was picked. When the template is much longer than the product
            &mdash; a 12 kb transcript, say &mdash; the template track cannot show the whole thing without shrinking
            the product and both primers to a few pixels, so it draws a <strong>1,500 bp window</strong> centred on
            the product instead, and the thin bar above it marks where that window sits in the whole template.
            Every scale on the map is in template coordinates either way, so the ruler under the template track
            always tells you the real position. The bp scale is printed under each track, and because the two tracks
            cover different stretches they are <em>not</em> at the same scale. Hover any block for its exact
            coordinates, and read Tm, length and GC off the cards underneath &mdash; for a long product the primer
            blocks are too narrow to hold their sequences, so the cards are where to read them. The map shows
            position and orientation; the sequence string printed below it is what you copy when ordering.</p>

            <div class="manual-note"><strong>Check the pair before you order it.</strong> A low penalty means primer3
            satisfied your constraints; it does not mean the pair will amplify. Confirm the product spans what you
            intended, remember that a template taken from the genome may contain an intron that a cDNA template does
            not, and run both primers against the species&rsquo; nucleotide database in <a href="#blast">BLAST</a>
            (5.2) to see whether they match anywhere else. primer3 optimises against the single sequence you gave it:
            it knows nothing about the rest of the genome, and nothing about variation between individuals.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 5.4</span>
                    <img src="./manual_images/primer_1.png" alt="The upstream Primer3Plus input form, shown for reference only" data-caption="The upstream Primer3Plus input form (shown for reference). It is not this page: this site&rsquo;s form has no excluded / target / included region fields." loading="lazy" decoding="async" />
                    <figcaption>The upstream Primer3Plus input form, shown for reference. <strong>This is not the form on this site</strong>: our page takes a template (pasted, or fetched from a species and gene ID) and primer constraints, and has no excluded / target / included region fields.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 5.5</span>
                    <img src="./manual_images/primer_2.png" alt="Primer design results" data-caption="Primer Design results: forward and reverse primers with Tm, GC% and product size." loading="lazy" decoding="async" />
                    <figcaption>Primer Design results: forward and reverse primers with Tm, GC% and product size.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="jbrowse" data-title="JBrowse visualization genome browser tracks protein coding genes reference genome RNA-seq">
            <h3>5.4 JBrowse Visualization</h3>
            <p>The JBrowse Visualization tool opens the genome browser for a selected species. Available tracks include the reference genome, the protein-coding genes and the RNA-seq coverage tracks (bigWig), allowing you to inspect genomic features in their sequence context.</p>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 5.6</span>
                    <img src="./manual_images/jbrowse.png" alt="JBrowse visualization" data-caption="JBrowse genome browser with selectable tracks (reference genome, protein-coding genes, RNA-seq coverage)." loading="lazy" decoding="async" />
                    <figcaption>JBrowse genome browser with the available annotation tracks.</figcaption>
                </figure>
            </div>
        </article>
    </section>

    <!-- ============ Chapter 6 ============ -->
    <section class="manual-chapter" id="ch6">
        <div class="manual-chapter-head">
            <div class="manual-chapter-num">6</div>
            <div>
                <h2>Data Download</h2>
                <p class="manual-chapter-sub">Download genomes, sequences, annotations and gene families.</p>
            </div>
        </div>

        <article class="manual-section" id="download" data-title="download genome CDS transcript protein GFF3 annotation gene family bulk manifest">
            <h3>6.1 Download</h3>
            <p>The Download page is the bulk-download entry point: one row per species, grouped by class and order, with a <em>Download</em> button for each file type that species has. Everything is compressed and served as-is, so this page is meant for feeding your own pipelines rather than for reading in the browser.</p>

            <p class="manual-subhead">The seven file types</p>
            <p>Under <strong>Sequences And GFF3</strong>:</p>
            <ul>
                <li><strong>Genome</strong> (<code>.fa.gz</code>) &mdash; the assembly in FASTA, one record per chromosome or scaffold, with the accession and assembly name in the header line.</li>
                <li><strong>CDS</strong> (<code>.cds.gz</code>) &mdash; coding sequences, one record per gene.</li>
                <li><strong>Transcript</strong> (<code>.transcript.gz</code>) &mdash; transcript sequences, including non-coding transcripts where the annotation defines them.</li>
                <li><strong>Protein</strong> (<code>.pep.gz</code>) &mdash; the predicted protein set, with the description in the header. This is the same set the BUSCO assessment and the BLAST protein databases are built from.</li>
                <li><strong>GFF3</strong> (<code>.gff3.gz</code>) &mdash; gene, transcript and exon coordinates, with the identifiers used in the CDS, transcript and protein files, so the four can be combined without renaming anything.</li>
            </ul>
            <p>Under <strong>Annotation</strong>:</p>
            <ul>
                <li><strong>Basic annotation</strong> (<code>.anno.gz</code>) &mdash; the functional annotation of every gene, in one file with a section per source, each introduced by a <code>#&nbsp;===&nbsp;&hellip;&nbsp;ANNOTATION&nbsp;===</code> header: KEGG, GO, InterPro, NR, PANTHER, Pfam and UniProt. Grep the section you need rather than loading the whole file.</li>
                <li><strong>Gene Family</strong> (<code>.genefamily.gz</code>) &mdash; the transcription factor and ubiquitin-like family assignment of every gene, one row per gene with its family and the domain architecture behind the call. This is the file behind the <a href="#tfs">TFs/Ubs</a> module (3.4).</li>
            </ul>

            <p class="manual-subhead">What the table tells you before you download</p>
            <ul>
                <li>A <strong>button</strong> means that file exists for that species. A <strong>blank cell</strong> means it does not, and that is information rather than an error: the species may be catalogued at assembly level, or its annotation may not define that sequence type. In this release <strong>148 species</strong> have a row in the table; every one of them has a genome, a protein set, a functional annotation and a gene family file, while CDS, transcript and GFF3 exist for the <strong>145</strong> whose annotation was released with them.</li>
            </ul>

            <p class="manual-subhead">Beyond the per-species table</p>
            <p>The collection holds more than the table above covers, and the page is explicit about what and why rather than leaving the rest unreachable:</p>
            <ul>
                <li><strong>Other files in the download directory</strong> &mdash; the rest of the directory, listed with its file sizes. These are named after short species codes rather than full Latin names, which is why they never fitted the per-species table: the mitochondrial genome and its derived CDS and protein sets for <strong>168 species</strong> (the GenBank record, the GFF3 annotation, and the sequences extracted from it), the microRNA sequence and genome-coordinate files for the four species in the <a href="#epigenomic">Epigenomic</a> module's miRNA pages, the combined miRBase table, and the pan-geneset family data behind <a href="#pangenome">3.7</a>.</li>
                <li><strong>Datasets available only from the database</strong> &mdash; four tables that are displayed elsewhere on the site but have no file in the directory, exported on demand as TSV (and, for the small ones, XLSX): BUSCO gene-level results, the BUSCO completeness summary, Ubs family assignments and mitochondrial genome metadata. Exporting them live means the download cannot go stale behind the database.</li>
            </ul>

            <div class="manual-tip"><strong>Tip:</strong> to fetch the collection from a script, use the file manifest instead of scraping this page &mdash; <code>api.php?resource=downloads&amp;format=tsv</code> returns every file name, size and download URL as tab-separated text (see <a href="#release">7.2</a>). It covers all <strong>1,873</strong> files, the same set the page links, because the page and the manifest are generated from one shared file-name-to-species mapping rather than maintained separately.</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 6.1</span>
                    <img src="./manual_images/download.png" alt="Download page" data-caption="Download page: per-species download links for genome, CDS, transcript, protein, GFF3 and annotation files." loading="lazy" decoding="async" />
                    <figcaption>Download page with per-species links for genomes, sequences, annotations and gene families.</figcaption>
                </figure>
            </div>
        </article>
    </section>

    <!-- ============ Chapter 7 ============ -->
    <section class="manual-chapter" id="ch7">
        <div class="manual-chapter-head">
            <div class="manual-chapter-num">7</div>
            <div>
                <h2>CnidoSite Data</h2>
                <p class="manual-chapter-sub">Statistics describing the content of the database and the analysis software used.</p>
            </div>
        </div>

        <article class="manual-section" id="statistics" data-title="statistics data composition software tools summary parameters versions">
            <h3>7.1 Statistics</h3>
            <p>The Statistics page summarises the composition of the CnidoSite data and lists the software used for the data analysis.</p>
            <ul>
                <li><strong>Database Content Statistics</strong> — the number of classes, orders, families and species, together with the counts for the assembled genomes, orthologous families and each omics dataset. The figures are read from the database when the page is built, so they always describe the release you are looking at; a species added since the last release appears in them immediately.</li>
                <li><strong>Software and Analytical Tools</strong> — the module, software, parameters and reference links for each analysis step (for example annotation, orthology inference, single-cell and genome-browser tools). This is the table to cite when a method needs to be described: it records the tool version and the parameters actually used, not the defaults.</li>
            </ul>
            <p>Two numbers on this page are worth reading with care, because they count different things. The <em>species</em> total is the catalogue — everything with an assembly record, including species held at genome level only. The per-module counts are record counts, so a module with no data for a species is recorded as <code>0</code> in that column rather than left blank. The <a href="#coverage-matrix">Data Coverage Matrix</a> (2.4) breaks the same figures down species by species.</p>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 7.1</span>
                    <img src="./manual_images/statistics_1.png" alt="Database statistics" data-caption="Statistics: summary of the data composition of CnidoSite." loading="lazy" decoding="async" />
                    <figcaption>Summary of the data composition of CnidoSite.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 7.2</span>
                    <img src="./manual_images/statistics_2.png" alt="Software and tools" data-caption="Statistics: software and analytical tools with parameters and links." loading="lazy" decoding="async" />
                    <figcaption>Software and analytical tools used, with their parameters and reference links.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="release" data-title="release version changelog update schedule api programmatic access json tsv citation r1.5">
            <h3>7.2 Release, Versioning and Programmatic Access</h3>

<p><strong>What it does.</strong> The <a href="/release.php">Release</a> page states which version of the database
you are using, when it was built, how often it is updated, and everything that changed in each release. The
<code>api.php</code> interface publishes the same metadata in machine-readable form, so you can check the version
from a script instead of reading a page.</p>

<p><strong>Why it matters.</strong> A database that grows is a database whose numbers change. Reporting
&ldquo;326 species&rdquo; or &ldquo;145 annotated genomes&rdquo; without saying which release you counted them in
makes a result impossible to reproduce. Quote the release number and date &mdash; they appear in the footer of
every page, in the form <em>Database release r1.5 (2026-09-27)</em> &mdash; both parts change with each
release, so quote them as you read them rather than from a copy of this manual.</p>

<p class="manual-subhead">The release page</p>
<ul>
    <li><strong>Current release</strong> — the version number, its build date, the previous version (so you can
        see how far behind your copy is), the date of the first public release, and the archival DOI where one
        exists.</li>
    <li><strong>Update schedule</strong> — content releases are scheduled twice a year, in June and December;
        corrective releases (bug fixes, security fixes and metadata corrections) are deployed as soon as they are
        verified, each with its own date.</li>
    <li><strong>Changelog</strong> — every release with its date and a list of the changes a user can observe.
        Recent versions are described in more detail than older ones. Read it before re-running an analysis whose
        result you recorded earlier: a changed count is usually a documented correction rather than a new
        dataset.</li>
    <li><strong>Where the data and their provenance are documented</strong> — a short table mapping common questions (which files can I
        download; which software produced a dataset; which species have which data types; where are the assembly
        accessions) to the page or interface that answers them.</li>
</ul>

<p class="manual-subhead">Programmatic access</p>
<p>Every published table is also available as JSON or TSV through <code>api.php</code>, which needs no key and
carries no rate limit &mdash; the data are public and the interface is read-only.</p>
<ul>
    <li><code>api.php?resource=release</code> &mdash; version, build date, previous version, schedule and the full
        changelog.</li>
    <li><code>api.php?resource=stats</code> &mdash; site-wide totals and the per-module species counts.</li>
    <li><code>api.php?resource=modules</code> &mdash; the data modules: key, label, the page that serves them and a
        one-line description.</li>
    <li><code>api.php?resource=species</code> &mdash; every species with its abbreviation, Latin name and
        class.</li>
    <li><code>api.php?resource=coverage</code> &mdash; the species-by-module matrix: one row per species, one
        column per data module. A number is the record count, <code>0</code> means the module has no data for that
        species, and <code>null</code> (written <code>NA</code> in TSV) means the module is present but has no
        count of its own &mdash; currently only <em>Mitogenome</em>, for species catalogued from a genome-level
        record with no gene-level annotation.</li>
    <li><code>api.php?resource=downloads</code> &mdash; the file manifest: name, species, class, kind, size and
        download URL for every file in the download area (<?php if ($manualDlFiles > 0) { ?><?= number_format($manualDlFiles) ?> files,
        <?= $manualDlGB ?>&nbsp;GB<?php if ($manualDlSpecies > 0) { ?> across <?= number_format($manualDlSpecies) ?> species<?php } ?> &mdash;
        <?php } ?>broader than the seven-per-species table on the <a href="#download">Download</a> page, since it also
        includes the mitochondrial-genome, miRNA and pan-geneset files listed in the <a href="#download">Download</a>
        chapter (6.1)).</li>

</ul>
<p>Two parameters apply throughout: <code>format=json|tsv</code> (default JSON) and <code>class=&lt;name&gt;</code>
to filter <code>species</code>, <code>coverage</code> and <code>downloads</code> by taxonomic class. Calling
<code>api.php</code> with no resource gives a short HTML page listing the resources, so you can explore it
directly in a browser.</p>

<div class="manual-note"><strong>Examples.</strong>
    <ul>
        <li>Check the version from a script:<br />
            <code>curl -s 'https://cnidosite.org/api.php?resource=release' | grep -m1 '"release"'</code></li>
        <li>List the species of one class:<br />
            <code>curl -s 'https://cnidosite.org/api.php?resource=species&amp;class=Hydrozoa&amp;format=tsv'</code></li>
        <li>Fetch the whole download manifest:<br />
            <code>curl -s 'https://cnidosite.org/api.php?resource=downloads&amp;format=tsv' &gt; manifest.tsv</code></li>
        <li>Mirror every file in it, saving each under its own name and resuming whatever an
            earlier run left half-downloaded (column 1 of the manifest is the file name, column 6
            its URL):<br />
            <code>tail -n +2 manifest.tsv | awk -F'\t' '{print $1, $6}' | xargs -n2 -P4 sh -c 'curl -sS -f -C - -o "$1" "$2"' _</code></li>
    </ul>
    Be considerate with the last one: it transfers tens of gigabytes, and a few parallel connections
    (<code>-P4</code> above) are friendlier to the server than a hundred.
</div>

            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 7.3</span>
                    <img src="./manual_images/release.png" alt="Release and changelog page" data-caption="The release page: current version and build date, the update schedule, and the changelog for every release." loading="lazy" decoding="async" />
                    <figcaption>The release page: current version and build date, the update schedule, and the changelog for every release.</figcaption>
                </figure>
            </div>
        </article>
    </section>

    <!-- ============ Chapter 8 ============ -->
    <section class="manual-chapter" id="ch8">
        <div class="manual-chapter-head">
            <div class="manual-chapter-num">8</div>
            <div>
                <h2>Data Submit &amp; Contact Us</h2>
                <p class="manual-chapter-sub">Submit data and get in touch with the CnidoSite team.</p>
            </div>
        </div>

        <article class="manual-section" id="submit" data-title="data submit submission form species data type contact feedback comments">
            <h3>8.1 Data Submit</h3>
            <p>Use the Data Submit page to contribute cnidarian data to CnidoSite. Fill in the required fields (name, position, organization, email, species, data type and a data upload link) and, optionally, a description of the dataset, then submit the form.</p>
            <p>If you have any suggestions or comments, please do not hesitate to leave them in the <em>Community Feedback</em> section on the same page.</p>
            <div class="manual-figures">
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 8.1</span>
                    <img src="./manual_images/submit_1.png" alt="Data submit form" data-caption="Data Submit: required contact and dataset fields for submitting data to CnidoSite." loading="lazy" decoding="async" />
                    <figcaption>Data Submit form with the required contact and dataset fields.</figcaption>
                </figure>
                <figure class="manual-figure">
                    <span class="manual-fig-label">Fig. 8.2</span>
                    <img src="./manual_images/submit_2.png" alt="Feedback comments" data-caption="Feedback section for suggestions and comments." loading="lazy" decoding="async" />
                    <figcaption>Feedback section where you can leave suggestions and comments.</figcaption>
                </figure>
            </div>
        </article>

        <article class="manual-section" id="contact" data-title="contact us team email researchers">
            <h3>8.2 Contact Us</h3>
            <p>For further information, collaboration or questions about CnidoSite, please contact the team through the <a href="/contact.php">Contact Us</a> page. Contact details are also listed on the Home page.</p>
            <div class="manual-tip"><strong>Need more help?</strong> You can also download the original PDF version of this manual using the button at the top of this page.</div>
        </article>
    </section>

    <div class="manual-footnote">
        This manual describes <strong>CnidoSite release <?php echo htmlspecialchars($manualRelease, ENT_QUOTES, 'UTF-8'); ?></strong>, the release shown in the footer of every page. It is maintained as an HTML page and is updated with the site, so it always describes the release you are looking at; the PDF version is regenerated less often and may describe an earlier one<?php
        if ($manualPdfDate !== '') {
            echo ' (the copy currently offered was generated on ' . htmlspecialchars($manualPdfDate, ENT_QUOTES, 'UTF-8') . ')';
        }
    ?> &mdash; where the two disagree, this page is correct. Screen counts quoted here are the ones current when the section was written, and every page named as their source shows the live figure. For data submissions, corrections or questions, please use the <a href="/contact.php">Contact Us</a> page.
    </div>

    </main>
</div>

<button class="manual-top" id="manualTop" title="Back to top">↑</button>

<div class="manual-lightbox" id="manualLightbox">
    <span class="manual-lightbox-close">&times;</span>
    <?php /* 不写 src=""：空 src 会被解析成「当前页面的地址」，浏览器据此再发一次
       整页请求（本页 HTML 有 160 KB）。图片由下面的点击脚本填进来，够用了。 */ ?>
    <img alt="Enlarged screenshot" id="manualLightboxImg" />
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript">
(function () {
    "use strict";

    // ---------- Smooth scroll for in-page anchors ----------
    function scrollToSection(id) {
        var el = document.getElementById(id);
        if (!el) return;
        var y = el.getBoundingClientRect().top + window.pageYOffset - 16;
        window.scrollTo({ top: y, behavior: "smooth" });
    }
    document.addEventListener("click", function (e) {
        var a = e.target.closest ? e.target.closest('a[href^="#"]') : null;
        if (!a) return;
        var id = a.getAttribute("href").slice(1);
        if (!id) return;
        e.preventDefault();
        scrollToSection(id);
        history.replaceState(null, "", "#" + id);
    });

    // ---------- Scroll-spy: highlight active TOC entry ----------
    var tocLinks = document.querySelectorAll("#manualTOC a");
    var targets = [];
    tocLinks.forEach(function (a) {
        var id = a.getAttribute("href").slice(1);
        var el = document.getElementById(id);
        if (el) targets.push({ link: a, el: el });
    });
    function onScrollSpy() {
        var pos = window.pageYOffset + 120;
        // last target whose top is above the scroll position = current section
        var best = targets.length ? targets[0] : null;
        for (var i = 0; i < targets.length; i++) {
            if (targets[i].el.offsetTop <= pos) best = targets[i];
        }
        tocLinks.forEach(function (l) { l.classList.remove("active"); });
        if (best) best.link.classList.add("active");
    }
    var ticking = false;
    window.addEventListener("scroll", function () {
        if (!ticking) {
            window.requestAnimationFrame(function () { onScrollSpy(); ticking = false; });
            ticking = true;
        }
    });
    window.addEventListener("resize", onScrollSpy);
    onScrollSpy();

    // ---------- In-page search / filter ----------
    var searchInput = document.getElementById("manualSearch");
    var statusEl = document.getElementById("manualSearchStatus");
    var sections = Array.prototype.slice.call(document.querySelectorAll(".manual-section"));
    var chapters = Array.prototype.slice.call(document.querySelectorAll(".manual-chapter"));

    sections.forEach(function (s) {
        // Pre-build a searchable haystack (title + text + caption + alt)
        var parts = [s.getAttribute("data-title") || "", s.textContent || ""];
        s.querySelectorAll("img").forEach(function (img) {
            parts.push(img.getAttribute("alt") || "", img.getAttribute("data-caption") || "");
        });
        s._haystack = parts.join(" ").toLowerCase();
    });
    chapters.forEach(function (c) {
        c._haystack = c.textContent.toLowerCase();
    });

    function runSearch() {
        var q = (searchInput.value || "").trim().toLowerCase();
        if (!q) { clearSearch(); return; }
        var hits = 0;
        sections.forEach(function (s) {
            var match = s._haystack.indexOf(q) !== -1;
            s.classList.toggle("hidden", !match);
            if (match) hits++;
        });
        // hide whole chapters that have no visible section
        chapters.forEach(function (c) {
            var anyVisible = c.querySelector(".manual-section:not(.hidden)");
            c.style.display = anyVisible ? "" : "none";
        });
        statusEl.classList.add("show");
        if (hits === 0) {
            statusEl.innerHTML = 'No section matched <b>' + escapeHtml(q) + '</b>. Try another keyword (for example <b>gene</b>, <b>cell</b>, <b>tree</b>).';
        } else {
            statusEl.innerHTML = 'Found <b>' + hits + '</b> section(s) matching <b>' + escapeHtml(q) + '</b>. Click a result header to jump there.';
        }
    }

    function clearSearch() {
        searchInput.value = "";
        sections.forEach(function (s) { s.classList.remove("hidden"); });
        chapters.forEach(function (c) { c.style.display = ""; });
        statusEl.classList.remove("show");
        statusEl.innerHTML = "";
    }

    function escapeHtml(str) {
        return str.replace(/[&<>"']/g, function (m) {
            return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[m];
        });
    }

    document.getElementById("manualSearchGo").addEventListener("click", runSearch);
    document.getElementById("manualSearchClear").addEventListener("click", clearSearch);
    searchInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter") { e.preventDefault(); runSearch(); }
    });
    // live filter (debounced)
    var dt;
    searchInput.addEventListener("input", function () {
        clearTimeout(dt);
        dt = setTimeout(runSearch, 250);
    });

    // ---------- Lightbox ----------
    var lightbox = document.getElementById("manualLightbox");
    var lightboxImg = document.getElementById("manualLightboxImg");
    document.querySelectorAll(".manual-figure img").forEach(function (img) {
        img.addEventListener("click", function () {
            lightboxImg.src = img.src;
            lightboxImg.alt = img.alt;
            lightbox.classList.add("show");
        });
    });
    function closeLightbox() {
        lightbox.classList.remove("show");
        /* 清空用 removeAttribute 而不是 src="" —— 后者会让浏览器把当前页面
           再取一遍（空 src 解析成文档地址）。 */
        lightboxImg.removeAttribute("src");
    }
    lightbox.addEventListener("click", closeLightbox);
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") closeLightbox();
    });

    // ---------- Back to top ----------
    var topBtn = document.getElementById("manualTop");
    window.addEventListener("scroll", function () {
        if (window.pageYOffset > 500) topBtn.classList.add("show");
        else topBtn.classList.remove("show");
    });
    topBtn.addEventListener("click", function () {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });

    // ---------- Deep link on load ----------
    if (location.hash) {
        var id = location.hash.slice(1);
        setTimeout(function () { scrollToSection(id); }, 120);
    }
})();
</script>

</div>
</div>
</div>

<?php
    include "Webpage_components.php";
    print $footer;
?>
</body>
</html>
