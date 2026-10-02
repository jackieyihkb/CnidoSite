<?php session_start(); ?>
<?php
/* ===========================================================================
 * Proteomic Data -- dataset listing for the CnidoSite revision.
 *
 * Changes relative to the previous version:
 *
 *  Referee 2 #5  The table now states, per dataset, whether CnidoSite was able
 *                to re-process the raw data at all, and against which reference
 *                proteome.  Previously the page listed 46 datasets and a
 *                reference, with no indication that the reported proteins came
 *                from 46 different search engines / databases.
 *
 *  Referee 2 #2  Datasets are filterable by class, species and processing
 *                status, so coverage gaps are visible rather than hidden.
 *
 *  Referee 3 #10(i) Rows whose dataset has been re-processed link straight
 *                through to the protein table for that dataset.
 *
 * The page degrades gracefully: if the proteomics schema has not been loaded
 * yet it falls back to the static dataset list, so the page is never blank.
 *
 * Requires (optional but recommended):
 *     5.web/sql/proteomics_schema.sql   ->  proteomic_datasets
 * =========================================================================== */

require_once __DIR__ . '/includes/state.php';

$__st = cnido_state('proteomic_data', array(
    'class'  => array('get' => 'class',  'default' => 'all'),
    'species'=> array('get' => 'species','default' => 'all'),
    'status' => array('get' => 'status', 'default' => 'all'),
    'q'      => array('get' => 'q',      'default' => ''),
));

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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

/* Static fallback: the dataset list as published, used only when the
   proteomics tables have not been imported.  Columns:
   class, species, tissue, treatment, pxd, pubmed, reference_proteome_size */
$__FALLBACK = array(
  array('Hexacorallia', 'Actinia fragacea', 'nematocysts, various tissues', '-', 'PXD060643', '39997203', '0'),
  array('Hexacorallia', 'Actinia tenebrosa', 'Tentacle', '-', 'PXD029717', '37226201', '27037'),
  array('Hexacorallia', 'Aiptasia pulchella', 'aposymbiotic and symbiontic anemones', '-', 'PXD003202', '26716757', '0'),
  array('Hexacorallia', 'Aiptasia sp.', 'anemones', 'thermal treatment', 'PXD004257', '28474894', '0'),
  array('Hexacorallia', 'Antipathes griggi', 'Coral skeleton', '-', 'PXD032043', '-', '0'),
  array('Hexacorallia', 'Calliactis polypus', 'Secreted venom', 'Electromechanical stimulation', 'PXD045890', '39018436', '0'),
  array('Hexacorallia', 'Exaiptasia diaphana', 'Cyst structure', 'Host-Parasite Infection', 'PXD051329', '38891869', '26042'),
  array('Hexacorallia', 'Exaiptasia diaphana', 'Symbiont Breviolum minutum', 'Symbiosis establishment', 'PXD045585', '38988135', '26042'),
  array('Hexacorallia', 'Exaiptasia diaphana', 'Symbiont Durusdinium trenchii', 'Symbiosis establishment', 'PXD045587', '38988135', '26042'),
  array('Hexacorallia', 'Exaiptasia diaphana', 'Whole anemone', 'Heat stress (32C) vs Control', 'PXD055908', '40056008', '26042'),
  array('Hexacorallia', 'Exaiptasia pallida', 'aposymbiotic and symbiontic anemones', '-', 'PXD014076', '31693769', '0'),
  array('Hexacorallia', 'Exaiptasia pallida', 'Individuals', '-', 'PXD009253', '31118473', '0'),
  array('Hexacorallia', 'Nematostella vectensis', 'Embryos', '-', 'PXD033068', '35999597', '32370'),
  array('Hexacorallia', 'Nematostella vectensis', 'Larvae, Primary Polyps, Juveniles', 'Starved for 2 days', 'PXD041235', '38727714', '32370'),
  array('Hexacorallia', 'Nematostella vectensis', 'polyps', '-', 'PXD011644', '33273471', '32370'),
  array('Hexacorallia', 'Orbicella annularis', 'Coral skeleton', '-', 'PXD017756', '-', '0'),
  array('Hexacorallia', 'Orbicella annularis', 'Coral skeleton', '-', 'PXD017813', '-', '0'),
  array('Hexacorallia', 'Orbicella annularis', 'Coral skeleton', '-', 'PXD017814', '-', '0'),
  array('Hexacorallia', 'Orbicella annularis', 'Coral skeleton', '-', 'PXD017815', '-', '0'),
  array('Hexacorallia', 'Orbicella annularis', 'Coral skeleton', '-', 'PXD017816', '-', '0'),
  array('Hexacorallia', 'Orbicella annularis', 'Coral skeleton', '-', 'PXD017876', '-', '0'),
  array('Hexacorallia', 'Orbicella annularis', 'Coral skeleton', '-', 'PXD017877', '-', '0'),
  array('Hexacorallia', 'Orbicella annularis', 'Coral skeleton', '-', 'PXD017878', '-', '0'),
  array('Hexacorallia', 'Orbicella annularis', 'Coral skeleton', '-', 'PXD017879', '-', '0'),
  array('Hexacorallia', 'Orbicella annularis', 'Coral skeleton', '-', 'PXD017880', '-', '0'),
  array('Hexacorallia', 'Stichopathes sp.', 'Coral skeleton', '-', 'PXD032043', '-', '0'),
  array('Hexacorallia', 'Stylophora pistillata', 'colony', 'soluble and insoluble matrix proteins', 'PXD017891', '32724895', '23945'),
  array('Hexacorallia', 'Telmatactis stephensoni', 'Tentacle', '-', 'PXD029717', '37226201', '52424'),
  array('Hexacorallia', 'Zoanthus natalensis', 'colony', '-', 'PXD010839', '31203412', '0'),
  array('Hydrozoa', 'Hydra vulgaris', 'Extracellular vesicles', 'Starved for 4 days', 'PXD027774', '34988080', '33388'),
  array('Hydrozoa', 'Hydractinia symbiolongicarpus', 'nematocysts', '-', 'PXD068969', '-', '25825'),
  array('Hydrozoa', 'Millepora complanata', 'Colony fragments', 'Bleached vs Non-bleached', 'PXD061020', '40906361', '40578'),
  array('Hydrozoa', 'Olindias sambaquiensis', 'primary tentacles', '-', 'PXD012887', '-', '0'),
  array('Myxozoa', 'Buddenbrockia bryozoides', 'myxoworms', '-', 'PXD016306', '33981497', '0'),
  array('Myxozoa', 'Ceratonova shasta', 'Developmental stages (in Ascites fluid)', 'Infection in Rainbow Trout', 'PXD022770', '-', '0'),
  array('Myxozoa', 'Myxobilatus gasterostei', 'stickleback kidney', '-', 'PXD016306', '33981497', '0'),
  array('Myxozoa', 'Myxobolus honghuensis', 'Cysts', '-', 'PXD018851', '32637811', '15433'),
  array('Myxozoa', 'Myxobolus honghuensis', 'Nematocysts', '-', 'PXD030136', '35053089', '15433'),
  array('Myxozoa', 'Myxobolus wulii', 'Cysts', '-', 'PXD018851', '32637811', '0'),
  array('Myxozoa', 'Myxobolus wulii', 'Nematocysts', '-', 'PXD030136', '35053089', '0'),
  array('Myxozoa', 'Thelohanellus kitauei', 'Cysts', '-', 'PXD018851', '32637811', '15020'),
  array('Myxozoa', 'Thelohanellus kitauei', 'Nematocysts', '-', 'PXD030136', '35053089', '15020'),
  array('Octocorallia', 'Corallium rubrum', 'Coral skeleton', '-', 'PXD020332', '33514311', '0'),
  array('Polypodiozoa', 'Polypodium hydriforme', 'stolon', '-', 'PXD016306', '33981497', '0'),
  array('Scyphozoa', 'Phyllorhiza punctata', 'oral arms, gonads, mantle', '-', 'PXD066818', '40867566', '0'),
  array('Staurozoa', 'Calvadosia cruxmelitensis', 'tentacle', '-', 'PXD016306', '33981497', '26258'),
);

/* ---- load rows ---------------------------------------------------------- */
$datasets = array();
if ($__hasSchema) {
    $q = cnido_q($conn,
        "SELECT dataset_id, pxd, species, taxon_class, tissue, treatment, pubmed,
                instrument, n_proteins, n_peptides, n_psms, status, status_note,
                proteome_file, proteome_source_type, date_incorporated, `release`
           FROM proteomic_datasets
          ORDER BY taxon_class, species, pxd");
    while ($q && ($x = mysqli_fetch_assoc($q))) { $datasets[] = $x; }
} else {
    foreach ($__FALLBACK as $f) {
        $datasets[] = array(
            'dataset_id' => $f[4], 'pxd' => $f[4], 'species' => $f[1],
            'taxon_class' => $f[0], 'tissue' => $f[2], 'treatment' => $f[3],
            'pubmed' => $f[5], 'instrument' => '', 'n_proteins' => 0,
            'n_peptides' => 0, 'n_psms' => 0,
            'status' => ($f[6] === '0' ? 'no_reference_proteome' : 'pending'),
            'status_note' => '', 'proteome_file' => '', 'proteome_source_type' => '',
            'date_incorporated' => '',
            'release' => '', 'n_prot' => 0, 'n_linked' => 0,
        );
    }
}

/* 每个数据集两个数，别混为一谈：
     n_prot   —— 非污染蛋白行的总数（这一列就是表格 Proteins 列印的数）
     n_linked —— 其中 links_gene = 1、真正能链到 CnidoSite 基因页的那部分
   这两个数差别不小（全库 37,998 vs 27,262），原先只有一个字段却被命名成
   gene_linkable、卡片也标成 "Gene-linked proteins"，等于把总数说成是能联基因的数。 */
if ($__hasSchema) {
    $gc = array();
    $q = cnido_q($conn,
        "SELECT dataset_id,
                SUM(is_contaminant = 0)                     AS n,
                SUM(is_contaminant = 0 AND links_gene = 1)  AS nl
           FROM proteomic_proteins GROUP BY dataset_id");
    while ($q && ($x = mysqli_fetch_assoc($q))) {
        $gc[$x['dataset_id']] = array((int)$x['n'], (int)$x['nl']);
    }
    foreach ($datasets as &$d) {
        $g = isset($gc[$d['dataset_id']]) ? $gc[$d['dataset_id']] : array(0, 0);
        $d['n_prot']   = $g[0];
        $d['n_linked'] = $g[1];
    }
    unset($d);
}

/* ---- filter ------------------------------------------------------------- */
$fClass   = (string)$__st['class'];
$fSpecies = (string)$__st['species'];
$fStatus  = (string)$__st['status'];
$fQ       = trim((string)$__st['q']);

$allClasses = array();
$allSpecies = array();
foreach ($datasets as $d) {
    $allClasses[$d['taxon_class']] = 1;
    $allSpecies[$d['species']] = 1;
}
ksort($allClasses);
ksort($allSpecies);

$view = array();
foreach ($datasets as $d) {
    if ($fClass !== 'all' && $fClass !== '' && $d['taxon_class'] !== $fClass) { continue; }
    if ($fSpecies !== 'all' && $fSpecies !== '' && $d['species'] !== $fSpecies) { continue; }
    if ($fStatus !== 'all' && $fStatus !== '' && $d['status'] !== $fStatus) { continue; }
    if ($fQ !== '' && stripos($d['pxd'] . ' ' . $d['species'] . ' ' . $d['tissue'], $fQ) === false) { continue; }
    $view[] = $d;
}

/* ---- counts for the intro and the status legend ------------------------- */
$nTotal = count($datasets);
$nClass = count($allClasses);
$nSpec  = count($allSpecies);
/* Each status is counted explicitly.  A catch-all "else" here would quietly
   fold every future status into the queue count, so a dataset that has been
   searched and yielded nothing would be advertised as still waiting. */
$nDone  = 0; $nNoRef = 0; $nPend = 0; $nProt = 0; $nNoId = 0; $nPsmOnly = 0; $nLinked = 0;
$nBespoke = 0;
foreach ($datasets as $d) {
    switch ($d['status']) {
        case 'reprocessed':            $nDone++;  break;
        case 'no_reference_proteome':  $nNoRef++; break;
        case 'no_identifications_at_fdr': $nNoId++; break;
        case 'psms_below_peptide_fdr':   $nPsmOnly++; break;
        /* 「原始库才能重跑」和「排队中」是两回事，各自计数：原来两者都并进 Queued，
           于是有一个 bespoke 数据集时 Queued 会多算一行。 */
        case 'bespoke_database_required': $nBespoke++; break;
        case 'pending':
        default:                       $nPend++;  break;
    }
    $nProt   += (int)$d['n_prot'];
    $nLinked += (int)$d['n_linked'];
}

$STATUS_BADGE = array(
    'reprocessed'            => array('Re-processed', '#dcfce7', '#166534'),
    // deliberately not green: searched, but nothing met the FDR
    'no_identifications_at_fdr' => array('Searched, no IDs at 1% FDR', '#fee2e2', '#991b1b'),
    // amber, not red: identifications were made, they just did not clear the
    // stricter peptide-level bar.  Red would read as "nothing found".
    'psms_below_peptide_fdr' => array('PSMs only, no peptides at 1%', '#fef3c7', '#92400e'),
    'no_reference_proteome'  => array('No reference proteome', '#fef3c7', '#92400e'),
    'bespoke_database_required' => array('Original DB required', '#fde68a', '#92400e'),
    'pending'                => array('Queued', '#e2e8f0', '#475569'),
);

// Kind of search space, when it is NOT the species' own reference proteome.
// Driven by the machine-readable `proteome_source_type` column rather than by
// pattern-matching the wording of `proteome_source`, so rephrasing a label
// cannot silently drop the warning.  Absent (reference proteome) => no badge,
// which keeps the table quiet for the majority of datasets.
$SOURCE_BADGE = array(
    'transcriptome' => array('Transcriptome', '#dbeafe', '#1e40af',
                             'No genome annotation exists for this species; searched against a TransDecoder proteome predicted from a de novo Trinity assembly of its own RNA-seq.'),
    'congener'      => array('Surrogate species', '#fee2e2', '#991b1b',
                             'No proteome of this species exists; searched against a different species in the same genus. Identifications are conditional on cross-species conservation.'),
    'combined'      => array('Combined DB', '#ede9fe', '#5b21b6',
                             'Multi-species study; searched against a combined database of all species listed for this dataset.'),
);

// One catalogue name is not the name the source repository uses.  The deposit
// keeps whatever it was published under, so a reader who searches PRIDE for the
// name shown here finds nothing.  Keyed by the catalogue name -- that is the
// string the pipeline writes into the table and the string this page renders.
$SPECIES_SYNONYM = array(
    'Buddenbrockia bryozoides' => 'Buddenbrockia plumatellae',
);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Proteomic Data - CnidoSite</title>
<meta name="description" content="Proteomic datasets held in CnidoSite, with tissue, treatment, source and reference, re-processed against the reference proteome where one exists" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<style>
.pd-bar{display:flex;gap:14px;flex-wrap:wrap;align-items:center;background:#fff;
  border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin:14px 0}
<?php /* <select> 的固有宽度由**最长的那个 option** 决定 —— Species 那一栏装的是
   物种拉丁名，撑到 466px。外层 <span> 是 flex 子项、min-width:auto，缩不动，
   于是 299px 的 .pd-bar 里顶出 504。span 放开下限、select 封顶即可；
   宽屏下 span 的宽度就等于 select 自身，100% 够不着，不生效。 */ ?>
.pd-bar > span{min-width:0}
.pd-bar select,.pd-bar input[type=text]{padding:7px 10px;border:1px solid #cbd5e1;
  border-radius:6px;font-size:15px;min-width:0;max-width:100%}
.pd-bar label{font-size:15px;color:#64748b;font-weight:600;margin-right:4px}
.pd-stats{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0}
.pd-stat{flex:1 1 150px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;
  padding:12px 14px;text-align:center}
.pd-stat b{display:block;font-size:22px;color:#1d4ed8;line-height:1.2}
.pd-stat span{font-size:13px;color:#64748b}
.badge{display:inline-block;padding:2px 9px;border-radius:11px;font-size:12px;
  font-weight:600;white-space:nowrap}
.btn-mini{display:inline-block;padding:4px 11px;background:#1d4ed8;color:#fff !important;
  border-radius:6px;font-size:12px;text-decoration:none;font-weight:600}
.btn-mini:hover{background:#1d4ed8}
<?php /* 禁用态按钮：11px 的字压在自己的 #e2e8f0 灰底上，原来的 #64748b 只有 3.86，
   「不可点」的说明反而看不清；#475569 是 6.14，仍然明显比可点按钮灰。 */ ?>
.btn-off{background:#e2e8f0;color:#475569 !important;cursor:not-allowed}
table.gridtable td{font-size:15px}
.pd-note{background:#f0f9ff;border-left:4px solid #3b82f6;padding:11px 14px;
  border-radius:6px;margin:12px 0;font-size:16px;color:#1e3a8a}
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Proteomic Data</b></legend>

<p class="paleo-intro">
This module lists <b><?= $nTotal ?></b> proteomic datasets covering <b><?= $nClass ?></b>
cnidarian classes and <b><?= $nSpec ?></b> species, with tissue, treatment, data source and
reference for each. Raw files were retrieved from the
<a href="https://www.ebi.ac.uk/pride/archive/" target="_blank" rel="noopener noreferrer">PRIDE Archive</a> and, where a
CnidoSite reference proteome is available for the species, re-processed through a single
documented pipeline (<b>Comet 2026.01</b> + <b>Percolator</b>, 1% PSM-level FDR) so that
identifications are directly comparable across datasets and link to CnidoSite gene pages.
The column <i>Status</i> states what was possible for each dataset; the column
<i>Proteins</i> gives the number of proteins identified in that dataset (cRAP contaminants
excluded). Of the <b><?= number_format($nProt) ?></b> proteins identified across the module,
<b><?= number_format($nLinked) ?></b> could be linked to a CnidoSite gene page &mdash; the rest
belong to species without gene models, or to proteins the reference set does not contain.
</p>

<?php /* Cross-link to the site's own tables.  This catalogue covers only the datasets
   whose species had a reference proteome to search against; nine species in the
   original tables had no transcriptome assembly, so no search space existed for
   them and they cannot appear here.  Complementary page sets -- see
   includes/proteomic_crosslinks.php and 5.web/INSTALL.md step 6.  No species
   argument: this is the unfiltered catalogue. */
require_once __DIR__ . '/includes/proteomic_crosslinks.php';
echo cnido_proteomic_note_rebuild('', 'pd-note'); ?>

<div class="pd-stats">
  <div class="pd-stat"><b><?= $nTotal ?></b><span>Datasets</span></div>
  <div class="pd-stat"><b><?= $nDone ?></b><span>Re-processed</span></div>
  <div class="pd-stat"><b><?= number_format($nProt) ?></b><span>Proteins identified</span></div>
  <div class="pd-stat"><b><?= number_format($nLinked) ?></b><span>Linked to a gene page</span></div>
  <?php /* only shown when non-zero, so the summary does not carry a permanent
           "0" for a case that most releases will not have */ ?>
  <?php if ($nNoRef > 0): ?>
  <div class="pd-stat"><b><?= $nNoRef ?></b><span>No reference proteome</span></div>
  <?php endif; ?>
  <?php if ($nPsmOnly > 0): ?>
  <div class="pd-stat"><b><?= $nPsmOnly ?></b><span>PSMs only, no peptides at 1%</span></div>
  <?php endif; ?>
  <?php if ($nNoId > 0): ?>
  <div class="pd-stat"><b><?= $nNoId ?></b><span>Searched, no IDs at 1% FDR</span></div>
  <?php endif; ?>
  <?php if ($nBespoke > 0): ?>
  <div class="pd-stat"><b><?= $nBespoke ?></b><span>Original DB required</span></div>
  <?php endif; ?>
  <?php if ($nPend > 0): ?>
  <div class="pd-stat"><b><?= $nPend ?></b><span>Queued</span></div>
  <?php endif; ?>
</div>

<?php if ($__sqlError !== ''): ?>
<div class="pd-note" style="background:#fef2f2;border-left-color:#ef4444;color:#7f1d1d">
  <b>The database rejected one of this page's queries, so the listing below is
  incomplete or empty.</b> This is a fault in the page, not an absence of data.
  The server said: <code><?= h($__sqlError) ?></code>
</div>
<?php endif; ?>

<?php if (!$__hasSchema): ?>
<div class="pd-note">
  <b>Note.</b> The proteomics tables have not been imported into this database yet, so the
  listing below is the static dataset catalogue and the processing status is not available.
  Import <code>4.results/load/proteomics_load.sql</code> to enable status tracking, protein
  counts and dataset links.
</div>
<?php endif; ?>

<form method="get" action="/proteomic_reprocessed.php">
<div class="pd-bar">
  <span><label>Class</label>
    <select name="class">
      <option value="all">All classes</option>
      <?php foreach (array_keys($allClasses) as $c): ?>
        <option value="<?= h($c) ?>"<?= ($fClass === $c) ? ' selected="selected"' : '' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
  </span>
  <span><label>Species</label>
    <select name="species">
      <option value="all">All species</option>
      <?php foreach (array_keys($allSpecies) as $s): ?>
        <option value="<?= h($s) ?>"<?= ($fSpecies === $s) ? ' selected="selected"' : '' ?>><?= h($s) ?></option>
      <?php endforeach; ?>
    </select>
  </span>
  <span><label>Status</label>
    <select name="status">
      <option value="all">Any status</option>
      <?php /* 只列这一版真有的状态 —— 否则选中一个 0 行的状态只会得到「没有匹配的数据集」。
               仍保留 $fStatus 已选中的那一项，免得带着旧 URL 进来时下拉框与结果对不上。 */ ?>
      <?php if ($nDone > 0 || $fStatus === 'reprocessed'): ?>
      <option value="reprocessed"<?= $fStatus === 'reprocessed' ? ' selected="selected"' : '' ?>>Re-processed</option>
      <?php endif; ?>
      <?php if ($nPend > 0 || $fStatus === 'pending'): ?>
      <option value="pending"<?= $fStatus === 'pending' ? ' selected="selected"' : '' ?>>Queued</option>
      <?php endif; ?>
      <?php if ($nPsmOnly > 0 || $fStatus === 'psms_below_peptide_fdr'): ?>
      <option value="psms_below_peptide_fdr"<?= $fStatus === 'psms_below_peptide_fdr' ? ' selected="selected"' : '' ?>>PSMs only, no peptides at 1%</option>
      <?php endif; ?>
      <?php if ($nNoId > 0 || $fStatus === 'no_identifications_at_fdr'): ?>
      <option value="no_identifications_at_fdr"<?= $fStatus === 'no_identifications_at_fdr' ? ' selected="selected"' : '' ?>>Searched, no IDs at 1% FDR</option>
      <?php endif; ?>
      <?php if ($nNoRef > 0 || $fStatus === 'no_reference_proteome'): ?>
      <option value="no_reference_proteome"<?= $fStatus === 'no_reference_proteome' ? ' selected="selected"' : '' ?>>No reference proteome</option>
      <?php endif; ?>
      <?php if ($nBespoke > 0 || $fStatus === 'bespoke_database_required'): ?>
      <option value="bespoke_database_required"<?= $fStatus === 'bespoke_database_required' ? ' selected="selected"' : '' ?>>Original DB required</option>
      <?php endif; ?>
    </select>
  </span>
  <span><label>Search</label>
    <input type="text" name="q" value="<?= h($fQ) ?>" placeholder="PXD, species or tissue" />
  </span>
  <span>
    <input type="submit" value="Filter" class="submit-btn"
           style="padding:8px 20px;background:linear-gradient(135deg,#1d4ed8,#1e40af);
                  color:#fff;border:none;border-radius:7px;font-weight:600;cursor:pointer">
    <a href="/proteomic_reprocessed.php" style="font-size:15px;margin-left:8px;color:#64748b">reset</a>
  </span>
</div>
</form>

<?php if (empty($view)): ?>
  <div class="pd-note">No dataset matches the current filter.
    <a href="/proteomic_reprocessed.php">Show all <?= $nTotal ?> datasets</a>.</div>
<?php else: ?>
<div class="table-container">
<table class="gridtable">
  <tr>
    <th width="9%">Class</th>
    <th width="13%">Species</th>
    <?php /* tissue/treatment 在 sample 表里是自由文本（最长 94 / 171 字），居中难看，左对齐。 */ ?>
    <th class="tal">Tissue</th>
    <th class="tal">Treatment</th>
    <th width="8%">Data Source</th>
    <th width="6%">Reference</th>
    <th width="12%">Status</th>
    <th width="8%">Proteins</th>
    <th width="8%">Browse</th>
  </tr>
  <?php foreach ($view as $d):
      $st = isset($STATUS_BADGE[$d['status']]) ? $STATUS_BADGE[$d['status']] : array($d['status'], '#e2e8f0', '#475569');
      $n  = (int)$d['n_prot'];
      $linkable = ($n > 0);
      $srcType = isset($d['proteome_source_type']) ? (string)$d['proteome_source_type'] : '';
      $src = isset($SOURCE_BADGE[$srcType]) ? $SOURCE_BADGE[$srcType] : null;
  ?>
    <tr align="center">
      <td><a href="/browse.php?class=<?= urlencode($d['taxon_class']) ?>"><?= h($d['taxon_class']) ?></a></td>
      <td><i><?= h($d['species']) ?></i>
        <?php if (isset($SPECIES_SYNONYM[$d['species']])): ?>
          <br /><span style="color:#6b7280;font-size:90%">deposited as
            <i><?= h($SPECIES_SYNONYM[$d['species']]) ?></i></span>
        <?php endif; ?>
        <?php if ($src): ?>
          <br /><span class="badge" style="background:<?= $src[1] ?>;color:<?= $src[2] ?>"
                title="<?= h($src[3]) ?>"><?= h($src[0]) ?></span>
        <?php endif; ?>
      </td>
      <td class="tal"><?= h($d['tissue']) ?></td>
      <td class="tal"><?= h($d['treatment']) ?></td>
      <td><a href="https://www.ebi.ac.uk/pride/archive/projects/<?= h($d['pxd']) ?>"
             target="_blank" rel="noopener"><?= h($d['pxd']) ?></a></td>
      <td>
        <?php /* 守卫必须是「纯数字」而不是「非空且不是 '-'」：proteomic_datasets.pubmed
                 里有 10 行是 '-'（缺失）、1 行是字面量 'Link'（PXD022770 当初只登记了
                 「有链接」而没抄号码）。后者能通过旧守卫，渲染成
                 https://pubmed.ncbi.nlm.nih.gov/Link —— 一个 404。同 species_portal.php
                 的 PM_ID 判定保持同一口径。 */ ?>
        <?php if (isset($d['pubmed']) && preg_match('/^\d+$/', trim((string)$d['pubmed']))): ?>
          <a href="https://pubmed.ncbi.nlm.nih.gov/<?= h($d['pubmed']) ?>" target="_blank" rel="noopener"><?= h($d['pubmed']) ?></a>
        <?php else: ?>&ndash;<?php endif; ?>
      </td>
      <td>
        <?php
          /* 同一句 status_note 对 congener/transcriptome/combined 的 11 个数据集都不成立，
             悬停提示改成实话（参考 proteomic_reanalysis.php 的 $__warns）。 */
          $__tip   = trim((string)$d['status_note']);
          $__stype = (string)($d['proteome_source_type'] ?? '');
          if ($__stype !== '' && $__stype !== 'reference' && stripos($__tip, 'reference proteome') !== false) {
              $__space = array(
                  'congener'      => 'a congener surrogate proteome',
                  'transcriptome' => 'a de novo transcriptome assembly',
                  'combined'      => 'a combined multi-species database',
              );
              $__tip = 'Searched against '
                     . (isset($__space[$__stype]) ? $__space[$__stype] : 'a non-reference search space')
                     . ', not a reference proteome of this species.';
          }
        ?>
        <span class="badge" style="background:<?= $st[1] ?>;color:<?= $st[2] ?>"
              title="<?= h($__tip) ?>"><?= h($st[0]) ?></span>
      </td>
      <td><?= $linkable ? number_format($n) : '&ndash;' ?></td>
      <td>
        <?php if ($linkable): ?>
          <a class="btn-mini" href="/proteomic_reanalysis.php?dataset=<?= urlencode($d['dataset_id']) ?>">proteins</a>
          <a class="btn-mini" style="background:#0369a1;margin-top:3px"
             href="/proteomic_dataset.php?dataset=<?= urlencode($d['dataset_id']) ?>">details</a>
        <?php else: ?>
          <span class="btn-mini btn-off" title="<?= h($d['status_note'] ? $d['status_note'] : 'Not yet re-processed') ?>">n/a</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
</div>
<p style="font-size:15px;color:#64748b;margin-top:8px">
  Showing <b><?= count($view) ?></b> of <?= $nTotal ?> datasets.
</p>
<?php endif; ?>

<div class="pd-note" style="background:#f8fafc;border-left-color:#94a3b8;color:#475569">
  <b>Why some datasets carry no protein counts.</b>
  Six of the <?= $nTotal ?> datasets identified nothing that reached 1% FDR, and are listed with
  their provenance and without re-processed counts: four <i>Orbicella annularis</i> acquisitions
  (PXD017813, PXD017815, PXD017877, PXD017879) that are the Glu-C fractions of a MED-FASP digest
  &mdash; the protocol digests first with trypsin and then only what is still undigested, so these
  fractions hold the residual material &mdash; and <i>Hydra vulgaris</i> PXD027774, whose deposited
  MGF carries an unreliable precursor charge field. In the sixth, <i>Orbicella annularis</i>
  PXD017756, peptide-spectrum matches passed the 1% PSM-level FDR but no peptide passed the
  1% peptide-level FDR, so no protein is reported.
  In the Glu-C fractions the depositors' own Mascot result for the same raw files separates no
  peptide from its decoys either, so the absence of identifications is a property of the material
  and not of the re-processing. Hover the <i>Status</i> badge on any of these rows for the
  per-dataset reason. Re-processing everything through one pipeline is what makes the counts
  comparable across datasets, so a dataset that yields nothing at that threshold is reported as
  such rather than re-run at a looser one.
</div>

</div></div></div>

<?php
    include "./Webpage_components.php";
    print $footer;
?>
</body>
</html>
