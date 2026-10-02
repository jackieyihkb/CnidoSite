<?php session_start(); ?>
<?php
/* ===========================================================================
 * Proteomic Dataset -- per-dataset provenance and search parameters.
 *
 * This page exists because of Referee 2 comment 5 and Referee 2 comment 9.
 * The reviewers asked how the proteomic identifications were produced and
 * complained that the methods description, a single paragraph, did not explain
 * how 46 datasets processed by 46 different groups with different instruments,
 * enzymes, databases and FDR procedures could be presented in one resource as
 * if comparable.
 *
 * The answer implemented here is: do not describe them as comparable.  Each
 * dataset is re-processed with one uniform pipeline (Comet + Percolator) and
 * the page shows, side by side,
 *     (a) what the ORIGINAL study reported, as recorded from its publication
 *         and PRIDE metadata, and
 *     (b) what CnidoSite actually DID, with every search parameter.
 * Nothing on this page is inferred: the cnido_* columns are written by the
 * pipeline at run time (2.pipeline/04_run_search.py) and describe the search
 * that produced the numbers shown.
 *
 * Usage:  /proteomic_dataset.php?dataset=PXD009253
 * =========================================================================== */

require_once __DIR__ . '/includes/state.php';

$__st = cnido_state('proteomic_dataset', array(
    'dataset' => array('get' => 'dataset', 'default' => ''),
));

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nn($v) { return ($v === null || $v === '') ? '&ndash;' : h($v); }

/* Comet `search_enzyme_number` -> readable name.  Rendering every non-zero
   value as "trypsin" misdescribes the GluC digests (the coral skeletal series
   contains both) and would silently mislabel any enzyme added later. */
function cnido_enzyme_label($n) {
    static $m = array(
        '0' => 'none &mdash; endogenous peptides searched without cleavage',
        '1' => 'trypsin', '2' => 'trypsin/P', '3' => 'Lys-C', '4' => 'Lys-N',
        '5' => 'Arg-C', '6' => 'Asp-N', '7' => 'CNBr', '8' => 'Glu-C',
        '9' => 'pepsin A', '10' => 'chymotrypsin', '11' => 'no cleavage',
    );
    $k = (string)(int)$n;
    return isset($m[$k]) ? $m[$k] : ('enzyme ' . h($k));
}

$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}
$conn->set_charset('utf8mb4');

/* ---- helper: run a query, and remember if the server rejected it --------
   mysqli_query() returns false for SQL the server refuses -- a reserved word
   used as a column name, a column the schema does not have.  Every caller
   below tests the result with `while ($q && ...)`, so a rejected statement
   renders as an empty page instead of an error.  On a catalogue page that is
   worse than a crash: "0 datasets" is a claim about the data, and it is
   false.  Failures are recorded here and shown as a notice. */
if (!function_exists('cnido_q')) {
    $__sqlError = '';
    function cnido_q($conn, $sql) {
        global $__sqlError;
        $r = mysqli_query($conn, $sql);
        if ($r === false && $__sqlError === '') {
            $__sqlError = mysqli_error($conn);
        }
        return $r;
    }
}

function cnido_tbl_exists($conn, $t) {
    $q = cnido_q($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
    return $q && mysqli_num_rows($q) > 0;
}
$__hasSchema = cnido_tbl_exists($conn, 'proteomic_datasets');

$dataset = trim((string)$__st['dataset']);
$ds = null;
if ($__hasSchema && $dataset !== '') {
    $e = mysqli_real_escape_string($conn, $dataset);
    $q = cnido_q($conn, "SELECT * FROM proteomic_datasets
                               WHERE dataset_id = '$e' OR pxd = '$e' LIMIT 1");
    $ds = $q ? mysqli_fetch_assoc($q) : null;
}
/* Only pick a default when the caller asked for no particular dataset.  If a
   dataset WAS named but does not exist (stale bookmark, typo), fall through to
   the "no dataset matches" notice -- silently showing a different dataset
   would misattribute every number on the page. */
if ($ds === null && $__hasSchema && $dataset === '') {
    $q = cnido_q($conn, "SELECT * FROM proteomic_datasets
                               WHERE status = 'reprocessed'
                               ORDER BY taxon_class, species LIMIT 1");
    $ds = $q ? mysqli_fetch_assoc($q) : null;
}

/* top proteins, for the preview table */
$prots = array();
if ($ds) {
    $e = mysqli_real_escape_string($conn, $ds['dataset_id']);
    $q = cnido_q($conn, "SELECT gene_id, protein_id, n_psms, n_unique_peptides,
                                     coverage_pct, length, best_q, description
                                FROM proteomic_proteins
                               WHERE dataset_id = '$e' AND is_contaminant = 0
                               ORDER BY n_unique_peptides DESC, n_psms DESC LIMIT 15");
    while ($q && ($x = mysqli_fetch_assoc($q))) { $prots[] = $x; }
}

/* contaminant count, reported rather than hidden */
$nContam = 0;
if ($ds) {
    $e = mysqli_real_escape_string($conn, $ds['dataset_id']);
    $q = cnido_q($conn, "SELECT COUNT(*) AS n FROM proteomic_proteins
                               WHERE dataset_id = '$e' AND is_contaminant = 1");
    $nContam = ($q && ($x = mysqli_fetch_assoc($q))) ? (int)$x['n'] : 0;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Proteomic Dataset <?= h($ds ? $ds['pxd'] : '') ?> - CnidoSite</title>
<meta name="description" content="Samples, identified proteins and the study's own reported results for one deposited proteomic dataset" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<style>
.ds-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;
  margin:16px 0;box-shadow:0 3px 14px rgba(0,0,0,.06)}
.ds-wrap h3{margin:0 0 12px;font-size:15px;color:#1e293b;border-bottom:1px solid #f1f5f9;padding-bottom:8px}
.ds-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:9px}
.ds-cell{background:#f8fafc;border-radius:8px;padding:9px 12px;font-size:15px}
.ds-cell b{display:block;color:#64748b;font-weight:600;font-size:13px;margin-bottom:2px}
.ds-cell span{color:#0f172a;word-break:break-word}
<?php /* 三列对照表（参数 / 原文报的 / 本站实际用的）。字号、表头底色、留白、行分隔线、
   悬停全部交给共用的 table.gridtable（与 core 的 table.cc 一致）—— 原来这里是照
   着旧样式抄的近似值（悬停 #f8fafc 差一档、边框在上不在下），现在删掉。
   保留下来的两条都带实际含义：两列表头的底色（黄=原文、绿=本站）和参数列的灰字。 */ ?>
table.gridtable.cmp th.orig{background:#fef9c3;color:#713f12}
table.gridtable.cmp th.cnido{background:#dcfce7;color:#14532d}
table.gridtable.cmp td.k{color:#64748b;font-weight:600;width:22%}
<?php /* 三列装的都是文字（参数名、检索设置、序列库文件名），统一后默认居中会读成一团，
   整张表钉回左对齐。写成带 tr 的选择器，免得被行上可能出现的 align="center" 反压。 */ ?>
table.gridtable.cmp tr th,
table.gridtable.cmp tr td{text-align:left}
.ds-total{background:linear-gradient(135deg,#1d4ed8,#1e40af);color:#fff;padding:10px 20px;
  border-radius:8px;font-weight:600;display:inline-block;margin:6px 0}
.ds-note{background:#fff7ed;border-left:4px solid #f59e0b;padding:12px 15px;border-radius:6px;
  margin:12px 0;font-size:16px;color:#78350f}
.ds-ok{background:#f0fdf4;border-left:4px solid #22c55e;padding:12px 15px;border-radius:6px;
  margin:12px 0;font-size:16px;color:#14532d}
/* provenance warning: louder than .ds-note, because it qualifies every number
   on the page rather than describing a processing outcome */
.ds-warn{background:#fef2f2;border-left:4px solid #dc2626;padding:12px 15px;border-radius:6px;
  margin:0;font-size:16px;color:#7f1d1d;line-height:1.55}
.ds-warn b{color:#991b1b}
.cmd{background:#0f172a;color:#e2e8f0;padding:12px 14px;border-radius:8px;
  font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;line-height:1.65;
  overflow-x:auto;white-space:pre}
</style>
</head>
<body>
<div id="templatemo_header_wrapper"><div id="templatemo_header"><div id="site_logo"></div></div></div>

<div id="templatemo_menu_wrapper"><div id="templatemo_menu">
<ul>
  <li><a href="/index.php">Home</a></li>
  <li><a href="#">Taxonomy</a><ul>
    <li><a href="/browse.php?class=all">All</a></li>
    <li><a href="/browse.php?class=Cubozoa">Cubozoa</a></li>
    <li><a href="/browse.php?class=Hexacorallia">Hexacorallia</a></li>
    <li><a href="/browse.php?class=Octocorallia">Octocorallia</a></li>
    <li><a href="/browse.php?class=Hydrozoa">Hydrozoa</a></li>
    <li><a href="/browse.php?class=Myxozoa">Myxozoa</a></li>
    <li><a href="/browse.php?class=Scyphozoa">Scyphozoa</a></li>
    <li><a href="/browse.php?class=Staurozoa">Staurozoa</a></li>
  </ul></li>
  <li><a href="/paleobiology.php">Paleobiology</a></li>
  <li><a href="#">Genome</a><ul>
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
  </ul></li>
  <li><a href="#">Transcriptome</a><ul>
    <li><a href="/cytoscape/trans_data.php">Transcriptomic Data</a></li>
    <li><a href="/trans_assembly.php">Transcriptome Assembly</a></li>
    <li><a href="/cytoscape/network.php">Network Analysis</a></li>
    <li><a href="/cytoscape/network_expression.php">Dynamic Expression View</a></li>
  </ul></li>
  <li><a href="#">Single-cell</a><ul>
    <li><a href="/sn_data.php">Single-cell Data</a></li>
    <li><a href="/cell_atlas.php">Cell Atlas</a></li>
    <li><a href="/cell_marker.php">Cell Marker</a></li>
    <li><a href="/gene_exp.php">Gene Expression</a></li>
  </ul></li>
  <li><a href="#" class="current">Proteome</a><ul>
    <li><a href="/proteomic_reprocessed.php">Proteomic Data</a></li>
    <li><a href="/proteomic_reanalysis.php">Proteomic Analysis</a></li>
  </ul></li>
  <li><a href="#">Epigenome</a><ul>
    <li><a href="/epigenomic_data.php">Epigenomic Data</a></li>
    <li><a href="/DNA_methylation.php">DNA Methylation</a></li>
    <li><a href="/miRNA_analysis.php">miRNA-seq Analysis</a></li>
    <li><a href="/ATAC_analysis.php">ATAC-seq Analysis</a></li>
    <li><a href="/DHS_analysis.php">DNase-seq Analysis</a></li>
    <li><a href="/ChIP_analysis.php">ChIP-seq Analysis</a></li>
  </ul></li>
  <li><a href="#">Metagenome</a><ul>
    <li><a href="/metagenomic_data.php">Metagenomic Data</a></li>
    <li><a href="/MAGs.php">MAGs Catalog</a></li>
  </ul></li>
  <li><a href="#">Phenotype</a><ul>
    <li><a href="/phenotype.php?class=all">All</a></li>
    <li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li>
    <li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li>
    <li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li>
    <li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li>
    <li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li>
  </ul></li>
  <li><a href="#">Tools</a><ul>
    <li><a href="/GSEA/GSEA.php">Gene Sets Analysis</a></li>
    <li><a href="/blast/blast.php">BLAST</a></li>
    <li><a href="/primer3plus/primer3.html">Primer Design</a></li>
    <li><a href="/jbrowse.php">JBrowse</a></li>
  </ul></li>
  <li><a href="/download.php">Download</a></li>
  <li><a href="#">Help</a><ul>
    <li><a href="/data_statistics.php">Statistics</a></li>
    <li><a href="/tutorial.php">User Manual</a></li>
    <li><a href="/submit_comments.php">Data Submit</a></li>
    <li><a href="/contact.php" class="last">Contact Us</a></li>
  </ul></li>
</ul>
</div></div>

<div id="tempatemo_content_wrapper"><div id="templatemo_content"><div id="column">

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Proteomic Dataset Details</b></legend>

<?php if ($__sqlError !== ''): ?>
  <div class="ds-note" style="background:#fef2f2;border-left-color:#ef4444;color:#7f1d1d">
    <b>The database rejected one of this page's queries, so the details below
    are incomplete or missing.</b> This is a fault in the page, not an absence
    of data. The server said: <code><?= h($__sqlError) ?></code></div>
<?php endif; ?>

<?php if (!$__hasSchema): ?>
  <div class="ds-note">The proteomics tables have not been imported. Load
    <code>4.results/load/proteomics_load.sql</code> to enable this page.</div>

<?php elseif ($ds === null): ?>
  <div class="ds-note">No dataset matches that accession.
    <a href="/proteomic_reprocessed.php">Back to the dataset list</a>.</div>

<?php else: ?>

<p class="paleo-intro">
  <a href="/proteomic_reprocessed.php">&laquo; All proteomic datasets</a> &nbsp;|&nbsp;
  <a href="/proteomic_reanalysis.php?dataset=<?= urlencode($ds['dataset_id']) ?>">Browse the
  <?php /* 同一个 n_proteins，同一页下方表头印的是 number_format 后的「4,572」，
           这里却是裸的「4572」；统计行里其它数字也都带千分位。 */ ?>
  <?= number_format((int)$ds['n_proteins']) ?> identified proteins &raquo;</a>
</p>

<?php /* Cross-link to the site's own tables; see includes/proteomic_crosslinks.php
   and 5.web/INSTALL.md step 6.  Placed inside the $ds branch, so it appears on a
   real dataset page and not on the "no dataset matches that accession" notice. */
require_once __DIR__ . '/includes/proteomic_crosslinks.php';
echo cnido_proteomic_note_rebuild($ds['species'], 'ds-note'); ?>

<!-- ============ identity ============ -->
<div class="ds-wrap">
  <h3><?= h($ds['pxd']) ?> &mdash; <i><?= h($ds['species']) ?></i> &mdash; <?= h($ds['tissue']) ?></h3>
  <div class="ds-grid">
    <div class="ds-cell"><b>Species</b><span><i><?= h($ds['species']) ?></i></span></div>
    <div class="ds-cell"><b>Class</b><span><?= nn($ds['taxon_class']) ?></span></div>
    <div class="ds-cell"><b>Tissue / sample</b><span><?= nn($ds['tissue']) ?></span></div>
    <div class="ds-cell"><b>Treatment / condition</b><span><?= nn($ds['treatment']) ?></span></div>
    <div class="ds-cell"><b>Instrument</b><span><?= nn($ds['instrument']) ?></span></div>
    <div class="ds-cell"><b>PRIDE project</b><span>
      <a href="https://www.ebi.ac.uk/pride/archive/projects/<?= h($ds['pxd']) ?>"
         target="_blank" rel="noopener"><?= h($ds['pxd']) ?></a></span></div>
    <div class="ds-cell"><b>Publication</b><span>
      <?php /* 见 proteomic_reprocessed.php 同处：库里 pubmed 除了 '-'（缺失）还有
               字面量 'Link'，旧守卫放它过去就会渲染 https://…/Link（404）。 */ ?>
      <?php if (isset($ds['pubmed']) && preg_match('/^\d+$/', trim((string)$ds['pubmed']))): ?>
        <a href="https://pubmed.ncbi.nlm.nih.gov/<?= h($ds['pubmed']) ?>" target="_blank"
           rel="noopener">PMID <?= h($ds['pubmed']) ?></a>
      <?php else: ?>&ndash;<?php endif; ?></span></div>
    <div class="ds-cell"><b>Raw files</b><span><?= nn($ds['file_count']) ?></span></div>
  </div>
</div>

<!-- ============ provenance warning ============ -->
<?php
/* A reviewer complaint was that the site did not say where its search space came
   from.  When the search space is NOT the dataset species' own reference
   proteome, say so before the numbers, not in a footnote after them. */
$pnote = isset($ds['proteome_note']) ? $ds['proteome_note'] : '';
$ptype = isset($ds['proteome_source_type']) ? $ds['proteome_source_type'] : '';
$warns = array(
    'congener'      => 'Search space is a different species (congener surrogate)',
    'transcriptome' => 'Search space is a transcriptome assembly, not a reference proteome',
    'combined'      => 'Search space is a combined multi-species database',
);
/* A reference proteome normally needs no warning.  But a dataset can be
   deposited under a species name that no reference proteome carries -- the
   Aiptasia pulchella / Aiptasia sp. deposits resolve to the Exaiptasia diaphana
   reference (see PROTEOME_ALIAS in 2.pipeline/cnido_common.py) -- and then the
   species cell and the search space do not look like the same organism.  A note
   there is the only thing that says so, so render it instead of dropping it:
   gating on `$warns` alone silently discarded it. */
$warn = isset($warns[$ptype]) ? $warns[$ptype]
      : ($pnote !== '' ? "Search space: the deposited species name is not the reference proteome's" : '');
if ($warn !== ''): ?>
<div class="ds-wrap">
  <div class="ds-warn">
    <b><?= h($warn) ?></b>
    <div style="margin-top:4px"><?= h($pnote !== '' ? $pnote : $ds['proteome_source']) ?></div>
  </div>
</div>
<?php endif; ?>
<?php
/* proteome_file 是搜索空间在服务器上的**绝对路径**，例如
   /mnt/sdb/jackie/cnidaria_omics/transcriptome_proteins/Actinia_tenebrosa.rep.pep。
   原样印出来等于把服务器目录结构发给访客，而读者用得上的只有文件名本身 ——
   这个搜索空间是什么、来自哪个物种，下面一行（$pnote / proteome_source）已经说清楚了。
   combined 数据集是两个文件，路径以 "; " 相连，两个都留。 */
$__pf = trim((string)$ds['proteome_file']);
$__pfDisp = '';
if ($__pf !== '') {
    $__pfParts = array();
    foreach (preg_split('/\s*;\s*/', $__pf) as $__pfOne) {
        $__pfOne = trim($__pfOne);
        if ($__pfOne === '') { continue; }
        $__pfParts[] = basename(str_replace('\\', '/', $__pfOne));
    }
    $__pfDisp = implode(' + ', $__pfParts);
}
?>

<!-- ============ the comparison the reviewers asked for ============ -->
<div class="ds-wrap">
  <h3>Original study vs CnidoSite re-processing</h3>
  <table class="gridtable cmp">
    <tr>
      <th style="width:22%">Parameter</th>
      <th class="orig">As reported by the original study</th>
      <th class="cnido">As applied by CnidoSite</th>
    </tr>
    <tr><td class="k">Search engine</td>
        <td><?= nn($ds['orig_engine']) ?></td>
        <td><b><?= nn($ds['cnido_engine']) ?></b> + Percolator (Crux 4.2)</td></tr>
    <tr><td class="k">Sequence database</td>
        <td><?= nn($ds['orig_database']) ?></td>
        <td><?= nn($__pfDisp) ?><br />
            <?php /* 「Surrogate: transcriptome-derived proteome of the congener …」这行是说明正文，
                 不是角标。2026-09-27 统一字号时它和角标同为 11.5px，被按值分档判进了 12px
                 下限档；2026-09-28 抬到 15px（内容层）。只改字号，颜色不变。 */ ?>
            <span style="color:#64748b;font-size:15px"><?= nn($ds['proteome_source']) ?></span></td></tr>
    <tr><td class="k">Enzyme</td>
        <td>&ndash; <span style="color:#64748b">(not restated)</span></td>
        <td><?= cnido_enzyme_label($ds['cnido_enzyme']) ?><?php
              /* cleavage-site count and missed cleavages are meaningless when the
                 search is enzyme-free (0) or has no cut sites at all (11) */
              if ((int)$ds['cnido_enzyme'] !== 0 && (int)$ds['cnido_enzyme'] !== 11): ?>
              (fully enzymatic, <?= h($ds['cnido_termini']) ?> termini)
              &nbsp;&middot;&nbsp; up to <?= nn($ds['cnido_missed']) ?> missed cleavages<?php endif; ?></td></tr>
    <tr><td class="k">Precursor tolerance</td>
        <td><?= nn($ds['orig_precursor']) ?></td>
        <td><?= nn($ds['cnido_precursor']) ?></td></tr>
    <tr><td class="k">Fragment tolerance</td>
        <td><?= nn($ds['orig_fragment']) ?></td>
        <td><?= nn($ds['cnido_fragment']) ?></td></tr>
    <tr><td class="k">Fixed modification</td>
        <td>&ndash;</td>
        <td><?= nn($ds['cnido_fixed']) ?> (carbamidomethyl cysteine)</td></tr>
    <tr><td class="k">Variable modification</td>
        <td>&ndash;</td>
        <td><?= nn($ds['cnido_variable']) ?> (methionine oxidation)</td></tr>
    <tr><td class="k">Decoy strategy</td>
        <td>&ndash;</td>
        <td><?= nn($ds['cnido_decoy']) ?></td></tr>
    <tr><td class="k">False discovery rate</td>
        <td><?= nn($ds['orig_fdr']) ?></td>
        <td><b>q &le; <?= nn($ds['cnido_fdr_psm']) ?></b> at the PSM level
            (Percolator q-values, target-decoy);
            protein level q &le; <?= nn($ds['cnido_fdr_prot']) ?></td></tr>
    <tr><td class="k">Quantification</td>
        <td>&ndash;</td>
        <td><?= nn($ds['cnido_quant']) ?></td></tr>
    <tr><td class="k">Contaminants</td>
        <td>&ndash;</td>
        <td>cRAP appended and <b>flagged</b>; <?= $nContam ?> contaminant
            protein<?= $nContam == 1 ? '' : 's' ?> identified and excluded from the
            reported protein list</td></tr>
    <tr><td class="k">Gene mapping</td>
        <td>&ndash;</td>
        <?php if (in_array(isset($ds['proteome_source_type']) ? $ds['proteome_source_type'] : '',
                           array('transcriptome', 'congener'), true)): ?>
        <td>Peptides are reported against <b>transcript sequences</b>. This species has
            no CnidoSite gene models, so no protein links to a gene page.</td>
        <?php elseif ((isset($ds['proteome_source_type']) ? $ds['proteome_source_type'] : '') === 'combined'): ?>
        <td>Combined search space: peptides are mapped to gene models of whichever
            species in the deposit they match, and each protein links to that gene page.</td>
        <?php else: ?>
        <td>Peptides mapped to CnidoSite gene models; each protein links to its gene page</td>
        <?php endif; ?></tr>
  </table>
</div>

<!-- ============ outcome ============ -->
<div class="ds-wrap">
  <h3>Outcome of re-processing</h3>
  <?php if ($ds['status'] === 'reprocessed'): ?>
    <?php
    /* status_note 是数据列，11 个 reprocessed 数据集里有 9 个拿到的是同一句
       「reprocessed against the CnidoSite reference proteome」，而这 9 个的搜索空间分别是
       congener(2) / transcriptome(7) / combined(2) —— 只有 reference 那两个说对了。
       正确的说法已经印在页面顶部那条 provenance 警告里（"Search space is ..."），
       这里再说一遍就成了假话，所以搜索空间不是参考蛋白组时把这句去掉。 */
    $__sn = trim((string)$ds['status_note']);
    if ($ptype !== 'reference' && stripos($__sn, 'reference proteome') !== false) { $__sn = ''; }
    ?>
    <div class="ds-ok"><b>Re-processed successfully.</b><?= $__sn !== '' ? ' ' . nn($__sn) : '' ?></div>
  <?php else: ?>
    <div class="ds-note"><b><?= h(str_replace('_', ' ', $ds['status'])) ?>.</b>
      <?= nn($ds['status_note']) ?></div>
  <?php endif; ?>
  <table class="gridtable cmp" style="margin-top:10px">
    <tr>
      <th>PSMs at q&le;0.01</th><th>Peptides at q&le;0.01</th>
      <?php /* 这个数是 n_proteins（非污染蛋白总数），不是「能联到基因页」的数：
               有些物种没有基因模型，links_gene 全为 0，但蛋白照样鉴定出来了。
               标成 Gene-linked 会把总数说小成 0 该有的样子。 */ ?>
      <th>Proteins identified</th><th>Contaminants (excluded)</th>
    </tr>
    <tr align="center">
      <td><?= number_format((int)$ds['n_psms']) ?></td>
      <td><?= number_format((int)$ds['n_peptides']) ?></td>
      <td><?= number_format((int)$ds['n_proteins']) ?></td>
      <td><?= number_format($nContam) ?></td>
    </tr>
  </table>
  <p style="font-size:16px;color:#64748b;margin-top:10px">
    Pipeline version <b><?= nn($ds['pipeline_version']) ?></b> &middot;
    <?php /* 这里的 release 是蛋白组模块自己的版本号（库里 27 行都是 1.1），
               不是站点版本 —— 页脚印的是 Database release r1.5。同一个词指两个
               东西会让读者以为版本号打架。 */ ?>
    Proteomic data release <b><?= nn($ds['release']) ?></b> &middot;
    incorporated <b><?= nn($ds['date_incorporated']) ?></b>
  </p>
</div>

<!-- ============ reproducibility ============ -->
<div class="ds-wrap">
  <h3>Reproducing this dataset</h3>
  <p style="font-size:16px;color:#475569;margin:0 0 10px">
    Every stage is a single command; the parameter file written by stage 2 is the one
    shown in the table above.
  </p>
<div class="cmd">python3 2.pipeline/02_fetch_pride.py <?= h($ds['pxd']) ?>          # retrieve peak lists from PRIDE
python3 2.pipeline/03_build_search_db.py "<?= h($ds['species']) ?>"   # proteome + cRAP
python3 2.pipeline/04_run_search.py <?= h($ds['pxd']) ?>              # writes comet.params, runs Comet
python3 2.pipeline/05_fdr_percolator.py <?= h($ds['pxd']) ?>          # Percolator, q &le; 0.01
python3 2.pipeline/06_map_to_genes.py <?= h($ds['pxd']) ?>            # peptides -> CnidoSite genes
python3 2.pipeline/07_build_tables.py --release <?= h($ds['release'] ? $ds['release'] : '1.1') ?>   # load tables + SQL</div>
</div>

<?php if (!empty($prots)): ?>
<!-- ============ preview of top proteins ============ -->
<div class="ds-wrap">
  <h3>Most strongly supported proteins in this dataset</h3>
  <table class="gridtable cmp">
    <tr>
      <th style="width:14%">Gene</th><th style="width:12%">Protein</th>
      <th>Unique peptides</th><th>PSMs</th><th>Coverage&nbsp;(%)</th>
      <th>Length</th><th>Best q</th><th>Description</th>
    </tr>
    <?php foreach ($prots as $p): ?>
      <tr>
        <td><?php if (!empty($p['gene_id']) && (int)$p['links_gene'] === 1): ?>
          <a href="/gene_detail.php?gene=<?= urlencode($p['gene_id']) ?>&species=<?= urlencode($ds['species']) ?>"><?= h($p['gene_id']) ?></a>
        <?php elseif (!empty($p['gene_id'])): ?>
          <?php /* Trinity contig from a transcriptome assembly: real, but no gene
                   page exists for it, so show it as text rather than a dead link. */ ?>
          <span title="transcript-sequence ID from the de novo assembly; not a CnidoSite gene page"
                style="color:#475569;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px"><?= h($p['gene_id']) ?></span>
        <?php else: ?>&ndash;<?php endif; ?></td>
        <td><?= h($p['protein_id']) ?></td>
        <td align="center"><?= (int)$p['n_unique_peptides'] ?></td>
        <td align="center"><?= (int)$p['n_psms'] ?></td>
        <td align="center"><?= h($p['coverage_pct']) ?></td>
        <td align="center"><?= (int)$p['length'] ?></td>
        <td align="center"><?= h($p['best_q']) ?></td>
        <td style="font-size:12px"><?= h($p['description']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p style="margin-top:10px">
    <a class="submit-btn" style="display:inline-block;padding:9px 22px;background:linear-gradient(135deg,#1d4ed8,#1e40af);color:#fff;border-radius:8px;text-decoration:none;font-weight:600"
       href="/proteomic_reanalysis.php?dataset=<?= urlencode($ds['dataset_id']) ?>">
       View all <?= number_format((int)$ds['n_proteins']) ?> proteins &raquo;</a>
  </p>
</div>
<?php endif; ?>

<?php endif; /* hasSchema && ds */ ?>

</div></div></div>

<?php
    include "./Webpage_components.php";
    print $footer;
?>
</body>
</html>
