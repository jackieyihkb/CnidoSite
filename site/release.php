<?php
/* =====================================================================
 * Database Release & Changelog
 *
 * 审稿意见 Referee 2 major 9：
 *   "The website should also provide a database release or version number,
 *    last-update date, changelog and a clear update schedule."
 *
 * 版本号、构建日期、更新周期与变更记录的唯一事实来源是 includes/release.php；
 * 本页只负责呈现。页脚（Webpage_components.php）也引用同一个来源，因此页脚
 * 上的版本戳、本页顶部的版本号和 api.php 返回的版本号不可能互相矛盾。
 * ===================================================================== */
require_once __DIR__ . '/includes/release.php';

$R         = cnido_release();
$changelog = cnido_changelog();
$host      = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'cnidosite.org';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Database Release &amp; Changelog - CnidoSite <?= htmlspecialchars($R['version'], ENT_QUOTES, 'UTF-8') ?></title>
<meta name="keywords" content="CnidoSite, database release, version, changelog, update schedule, data provenance" />
<meta name="description" content="CnidoSite database release number, last-update date, changelog and update schedule, with bulk download and programmatic access instructions." />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<style>
.rel-wrap { padding-bottom: 40px; }
.rel-h3 { margin: 34px 0 12px; color: #1e293b; font-size: 20px;
          border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
.rel-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(15,23,42,.05); padding: 18px 22px; margin: 14px 0; }
.rel-current { border-left: 4px solid #3b82f6; }
.rel-row { display: flex; gap: 16px; padding: 8px 0; border-bottom: 1px dashed #eef2f7; }
.rel-row:last-child { border-bottom: none; }
.rel-key { flex: 0 0 210px; font-weight: 600; color: #475569; font-size: 15px; }
.rel-val { flex: 1 1 auto; color: #1e293b; font-size: 15px; line-height: 1.65; }
.rel-dim { color: #64748b; font-size: 15px; }
.rel-badge { display: inline-block; background:linear-gradient(135deg,#1d4ed8,#1e40af); color: #fff;
             padding: 5px 13px; border-radius: 20px; font-weight: 600; font-size: 15px; }
.rel-badge-old { background: #e2e8f0; color: #475569; }
.rel-relhead { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 4px; }
.rel-reldate { font-family: 'Monaco','Menlo','Ubuntu Mono',monospace; color: #64748b; font-size: 15px; }
.rel-reltitle { color: #334155; font-weight: 600; font-size: 15px; }
.rel-list { margin: 10px 0 0; padding-left: 22px; }
.rel-list li { color: #334155; line-height: 1.75; margin-bottom: 7px; font-size: 15px; }
<?php /* 表本身挂 gridtable：表头底色、下边框、留白、行分隔线、字号全部由
   templatemo_style.css 的共用样式提供（与 core 的 table.cc 一致）。
   原来这张表是每格描一圈 1px #e2e8f0 的方框、字号 14px —— 是站里少数几处
   还带完整网格线的表，统一成无边线的共用观感后删掉。
   这里只留外边距与左对齐：三列装的都是「想要什么 / 请求怎么写 / 备注」这类文字。 */ ?>
.rel-table { margin: 12px 0 6px; }
table.gridtable.rel-table tr th,
table.gridtable.rel-table tr td { text-align: left; line-height: 1.65; }
.rel-pre { background: #1e293b; color: #e2e8f0; padding: 14px 16px; border-radius: 8px;
           overflow-x: auto; font-size: 15px; line-height: 1.6; }
.rel-wrap code { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px;
                 padding: 2px 6px; font-family: 'Monaco','Menlo','Ubuntu Mono',monospace; font-size: 15px; }
@media (max-width: 768px) {
  .rel-row { flex-direction: column; gap: 4px; }
  .rel-key { flex: none; }
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
<div id="column" class="rel-wrap">

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Database Release &amp; Changelog</b></legend>
<p class="paleo-intro">
  CnidoSite is versioned. This page states which release you are using, when it was built, what
  changed in it, and when the next updates are due &mdash; so that any result you obtain here can be
  traced back to a specific version of the database.
</p>

<?php /* ================= 当前版本 ================= */ ?>
<div class="rel-card rel-current">
  <div class="rel-row">
    <div class="rel-key">Current release</div>
    <div class="rel-val"><span class="rel-badge">CnidoSite <?= htmlspecialchars($R['version'], ENT_QUOTES, 'UTF-8') ?></span></div>
  </div>
  <div class="rel-row">
    <div class="rel-key">Build / last-update date</div>
    <div class="rel-val"><b><?= htmlspecialchars($R['build'], ENT_QUOTES, 'UTF-8') ?></b>
      &nbsp;<span class="rel-dim">(all dates on this page are ISO&nbsp;8601, UTC)</span></div>
  </div>
  <div class="rel-row">
    <div class="rel-key">Previous release</div>
    <div class="rel-val"><?= htmlspecialchars($R['previous'], ENT_QUOTES, 'UTF-8') ?>
      &nbsp;<span class="rel-dim">&mdash; if you used CnidoSite before
      <?= htmlspecialchars($R['build'], ENT_QUOTES, 'UTF-8') ?>, you were on this or an earlier version</span></div>
  </div>
  <div class="rel-row">
    <div class="rel-key">First public release</div>
    <div class="rel-val"><?= htmlspecialchars($R['first'], ENT_QUOTES, 'UTF-8') ?></div>
  </div>
  <div class="rel-row">
    <div class="rel-key">Archived snapshot</div>
    <div class="rel-val"><?php if ($R['doi'] === 'NA'): ?>
        <span class="rel-dim">Not yet deposited in an external archive. The live release is
        self-describing through this page and through the
        <a target="_blank" rel="noopener" href="api.php?resource=release">version endpoint</a>.</span>
      <?php else: ?>
        <?= htmlspecialchars($R['doi'], ENT_QUOTES, 'UTF-8') ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="rel-row">
    <div class="rel-key">How to cite this release</div>
    <div class="rel-val">
      CnidoSite <?= htmlspecialchars($R['version'], ENT_QUOTES, 'UTF-8') ?>
      (build <?= htmlspecialchars($R['build'], ENT_QUOTES, 'UTF-8') ?>). Accessed
      <?= date('Y-m-d') ?>.
    </div>
  </div>
</div>

<?php /* ================= 更新周期 ================= */ ?>
<h3 class="rel-h3">Update schedule</h3>
<div class="rel-card">
  <p style="margin:0;line-height:1.8"><?= htmlspecialchars($R['schedule'], ENT_QUOTES, 'UTF-8') ?></p>
  <p style="margin:12px 0 0;line-height:1.8" class="rel-dim">
    Each release is identified by the version string above, which is also shown in the footer of
    every page of the site. The machine-readable form of this information is available at
    <a target="_blank" rel="noopener" href="api.php?resource=release"><code>api.php?resource=release</code></a>.
  </p>
</div>

<?php /* ================= 变更记录 ================= */ ?>
<h3 class="rel-h3">Changelog</h3>
<p class="rel-dim" style="margin-top:-4px">
  Newest first. Entries describe changes that are visible to a user of the site; internal
  refactoring is not listed.
</p>

<?php foreach ($changelog as $rel): ?>
<div class="rel-card rel-rel">
  <div class="rel-relhead">
    <span class="rel-badge <?= $rel['version'] === $R['version'] ? '' : 'rel-badge-old' ?>">
      <?= htmlspecialchars($rel['version'], ENT_QUOTES, 'UTF-8') ?></span>
    <span class="rel-reldate"><?= htmlspecialchars($rel['date'], ENT_QUOTES, 'UTF-8') ?></span>
    <span class="rel-reltitle"><?= htmlspecialchars($rel['title'], ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <ul class="rel-list">
    <?php foreach ($rel['entries'] as $e): ?>
    <li><?= cnido_rel_entry_html($e) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endforeach; ?>

<?php /* ================= 批量下载与脚本化访问 ================= */ ?>
<h3 class="rel-h3">Bulk download and programmatic access</h3>
<p>
  Every dataset behind these pages can be retrieved without using a browser. The site exposes a
  read-only metadata interface that returns JSON or tab-separated text, and a file manifest that
  lists the entire download collection so it can be fetched with a single loop.
</p>

<table class="gridtable rel-table">
  <tr>
    <th width="30%">What you want</th>
    <th width="34%">Request</th>
    <th>Notes</th>
  </tr>
  <tr>
    <td>Version, build date, full changelog</td>
    <td><a target="_blank" rel="noopener" href="api.php?resource=release"><code>api.php?resource=release</code></a></td>
    <td>Also available as <a target="_blank" rel="noopener" href="api.php?resource=release&amp;format=tsv">TSV</a>, one row per change.</td>
  </tr>
  <tr>
    <td>Every species with its class and abbreviation</td>
    <td><a target="_blank" rel="noopener" href="api.php?resource=species"><code>api.php?resource=species</code></a></td>
    <td><a target="_blank" rel="noopener" href="api.php?resource=species&amp;format=tsv">TSV</a>; add <code>&amp;class=Hydrozoa</code> to restrict to one class.</td>
  </tr>
  <tr>
    <td>Which species have which data</td>
    <td><a target="_blank" rel="noopener" href="api.php?resource=coverage"><code>api.php?resource=coverage</code></a></td>
    <td>The same matrix as the <a href="coverage_matrix.php">Data Coverage Matrix</a> page, as data rather than HTML.</td>
  </tr>
  <tr>
    <td>Site-wide totals</td>
    <td><a target="_blank" rel="noopener" href="api.php?resource=stats"><code>api.php?resource=stats</code></a></td>
    <td>Species count, per-module coverage, number of download files and total volume.</td>
  </tr>
  <tr>
    <td>The list of every downloadable file</td>
    <td><a target="_blank" rel="noopener" href="api.php?resource=downloads&amp;format=tsv"><code>api.php?resource=downloads&amp;format=tsv</code></a></td>
    <td>One row per file: name, species, class, data type, size in bytes and the direct download URL.</td>
  </tr>
</table>

<p style="margin-top:18px">To mirror the whole collection, take the manifest and follow it:</p>
<pre class="rel-pre">curl -s 'https://<?= htmlspecialchars($host, ENT_QUOTES, 'UTF-8') ?>/api.php?resource=downloads&amp;format=tsv' -o cnidosite-downloads.tsv
tail -n +2 cnidosite-downloads.tsv | awk -F'\t' '{print $1, $6}' | \
    xargs -n2 -P4 sh -c 'curl -sS -f -C - -o "$1" "$2"' _</pre>
<p class="rel-dim">
  Column 1 of the manifest is the file name and column 6 its download URL, so each file is saved
  under the name it has in the collection rather than under the name of the script that serves it.
  Files are streamed by <code>download_fun.php</code> rather than read into memory, and because that
  handler honours byte ranges, <code>curl -C -</code> resumes an interrupted transfer instead of
  restarting it — worth having, since the largest single file is 932&nbsp;MB. Re-running the loop
  over an already-complete collection is harmless. The same interface is documented, with all
  parameters, at <a target="_blank" rel="noopener" href="api.php">api.php</a>.
</p>

<?php /* ================= 数据来源 ================= */ ?>
<h3 class="rel-h3">Where the data and their provenance are documented</h3>
<table class="gridtable rel-table">
  <tr><th width="34%">Question</th><th>Where it is answered</th></tr>
  <tr>
    <td>Which sequences and annotations can I download, per species?</td>
    <td><a href="download.php">Download</a> &mdash; per-species table of genome, CDS, protein, GFF3,
        repeat and gene-family files, plus <code>api.php?resource=downloads</code> for the full list.</td>
  </tr>
  <tr>
    <td>Which software and parameters produced each dataset?</td>
    <td><a href="data_statistics.php">Statistics</a> &mdash; a Software and Analytical Tools table,
        per module, with tool versions and command-line parameters.</td>
  </tr>
  <tr>
    <td>Which species have which data types?</td>
    <td><a href="coverage_matrix.php">Data Coverage Matrix</a>, downloadable as tab-separated text.</td>
  </tr>
  <tr>
    <td>Taxonomy, assembly statistics and accession numbers</td>
    <td>Each species has a portal page reachable from <a href="browse.php?class=all">Taxonomy</a>,
        carrying its assembly level, size, N50, BUSCO summary and accession.</td>
  </tr>
</table>

<p class="rel-dim" style="margin-top:24px">
  Dataset-level provenance for the manuscript &mdash; original accession number, source publication,
  reference genome and genome version used for processing, processing pipeline version and date of
  incorporation for each incorporated dataset &mdash; is provided as a supplementary table with the
  paper. This page covers what the <i>website</i> itself makes visible.
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
