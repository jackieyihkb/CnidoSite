<?php session_start(); ?>
<?php
/* ===========================================================================
 * Proteomic Analysis -- rebuilt for the CnidoSite revision.
 *
 * What changed and why (reviewer comments):
 *
 *  Referee 2 #5  The old page showed proteins as raw UniProt accessions from
 *                MaxQuant output, with no search parameters recorded anywhere.
 *                The page now reads a single generic table produced by a
 *                documented Comet + Percolator pipeline (see the dataset
 *                panel), and every dataset carries its full parameter set.
 *
 *  Referee 2 #10(i) The old footer printed the wrong label on this page.
 *                Now generated from the actual query.
 *
 *  Referee 2 #1  / Referee 3 #10(j)
 *                Proteins could not be connected to genes. Every row now
 *                carries a CnidoSite `gene_id` that links straight to
 *                gene_detail.php.
 *
 *  Referee 2 #2  Contaminants (cRAP, and previously MaxQuant `CON__` entries)
 *                were listed as if they were cnidarian proteins. They are now
 *                counted, reported separately, and hidden by default.
 *
 *  Referee 3 #10(i) The page-2 link was broken: pagination did not carry the
 *                species/dataset parameters. Every link is now built from the
 *                normalised query string.
 *
 *  Referee 3 #10(d) An empty result showed a bare mysqli error / blank table.
 *                Now an explicit explanatory notice is shown.
 *
 * Requires: 5.web/sql/proteomics_schema.sql loaded into the `cnidaria` DB.
 * =========================================================================== */

require_once __DIR__ . '/includes/state.php';
require_once __DIR__ . '/includes/proteomic_published.php';

$__st = cnido_state('proteomic', array(
    'dataset' => array('get' => 'dataset', 'default' => ''),
    'species' => array('get' => 'species', 'default' => ''),
    'contam'  => array('get' => 'contam',  'default' => '0'),
));

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

/* ---- helper: does the table exist? (schema may not be loaded yet) -------- */
function cnido_tbl_exists($conn, $t) {
    $q = cnido_q($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
    return $q && mysqli_num_rows($q) > 0;
}
$__hasSchema = cnido_tbl_exists($conn, 'proteomic_proteins')
            && cnido_tbl_exists($conn, 'proteomic_datasets');

/* ---- normalise the dataset parameter ------------------------------------ */
$dataset = trim((string)$__st['dataset']);
/* A dataset asked for by name that does not exist is an error the page has to
   report.  It must not be allowed to fall through to the two empty-string
   paths below: an empty `$dataset` means "no dataset filter", which would list
   every dataset's proteins in a page the reader opened for one of them, and
   the default-view fallback would silently show a *different* dataset under
   the heading they asked for.  Both are the misattribution this page was
   rewritten to remove, so the unresolved request is remembered and reported. */
$__dsUnknown = '';
if ($__hasSchema && $dataset !== '') {
    $e = mysqli_real_escape_string($conn, $dataset);
    $r = cnido_q($conn, "SELECT dataset_id FROM proteomic_datasets
                              WHERE dataset_id = '$e' OR pxd = '$e' LIMIT 1");
    $row = $r ? mysqli_fetch_row($r) : null;
    if ($row) { $dataset = $row[0]; }
    else      { $__dsUnknown = $dataset; $dataset = ''; }
}

/* species filter, used by the deep links from the taxonomy / coverage pages */
$species = trim((string)$__st['species']);
$showContam = ((string)$__st['contam'] === '1');

/* ---- dataset list for the selector -------------------------------------- */
$__datasets = array();
if ($__hasSchema) {
    /* every column the provenance panel below renders must be selected here,
       otherwise the panel silently shows blanks */
    $q = cnido_q($conn,
        "SELECT dataset_id, pxd, species, taxon_class, tissue, n_proteins,
                n_peptides, n_psms, status, status_note,
                cnido_engine, cnido_enzyme, cnido_missed, cnido_precursor,
                cnido_fragment, cnido_fixed, cnido_variable, cnido_decoy,
                cnido_fdr_psm, cnido_quant, proteome_file,
                proteome_source, proteome_source_type, proteome_note,
                proteome_is_surrogate
           FROM proteomic_datasets
          ORDER BY taxon_class, species, dataset_id");
    while ($q && ($x = mysqli_fetch_assoc($q))) {
        $__datasets[] = $x;
    }
}
/* Default view only: shown when the reader expressed no preference.  An
   unresolved request must not land here (see $__dsUnknown above).
   The default has to honour the species filter.  Taking $__datasets[0] even
   when a species was requested paired the first dataset in the whole table
   (Actinia fragacea, PXD060643) with the requested species, and those two
   clauses cannot both hold, so every species deep link -- the ones this filter
   exists for -- opened on "No proteins match the current selection." while the
   selector showed a dataset from another species.  Pick the first dataset of
   the requested species instead; with no species requested the behaviour is
   unchanged.

   Prefer one that actually identified something.  Ordering is by dataset_id, so
   Orbicella annularis -- six identifications plus five acquisitions that are
   null by design (MED-FASP Glu-C, see their status notes) -- defaulted to an
   empty table even though five of its datasets carry proteins.  Only when no
   candidate has a protein does the first one stand, so a genuinely null species
   still opens on its dataset and its explanation. */
if ($dataset === '' && $__dsUnknown === '' && !empty($__datasets)) {
    $__fallback = '';
    foreach ($__datasets as $__d) {
        if ($species !== '' && $__d['species'] !== $species) { continue; }
        if ($__fallback === '') { $__fallback = $__d['dataset_id']; }
        if ((int)$__d['n_proteins'] > 0) { $__fallback = $__d['dataset_id']; break; }
    }
    $dataset = $__fallback;
}
/* A species that is not in the table at all leaves $dataset empty: say so
   rather than render an empty protein table, which reads as "this species has
   no proteins" instead of "this species is not here". */
$__spUnknown = ($species !== '' && $dataset === '' && $__dsUnknown === '');

/* current dataset row (for the provenance panel) */
$__cur = null;
foreach ($__datasets as $d) {
    if ($d['dataset_id'] === $dataset) { $__cur = $d; break; }
}

/* ---- the species' transcriptome assembly, if it has one ------------------
   Some datasets were searched against a *de novo* transcriptome assembly, so
   the identified "proteins" are Trinity transcripts that have no gene page and
   no NCBI record.  Those very transcripts are what the Transcriptome Assembly
   module holds, annotated against the same six sources this site uses
   everywhere (UniProt Swiss-Prot, Pfam, PANTHER, InterPro, GO, KEGG), so the
   annotation can be shown next to the identification.

   The join key is the **protein** id, not the gene id.  `p.protein_id` is
   `TRINITY_DN3513_c0_g1_i1.p2` -- the exact ORF that was searched, and it exists
   in `trans_assembly.protein` for 100% of the rows of every transcriptome
   dataset (verified 2026-09-26: Ceratonova shasta 2,239/2,239, Corallium rubrum
   108/108, Orbicella annularis 65/65 + 21/21 + 41/41 ...).  Joining on the gene
   instead multiplies every row by the number of that gene's isoforms and, worse,
   would attribute one isoform's annotation to another: the site's longest ORF
   for a gene is frequently a different isoform than the one identified here
   (e.g. Corallium TRINITY_DN10552_c0_g1: identified i1.p1, site representative
   i1.p2).  (abbr1, protein) is unique in trans_assembly -- 13,111,410 distinct
   pairs over 13,111,410 rows -- so the LEFT JOIN cannot duplicate a row.

   The species name is the link between the two modules: it is unique in
   trans_assembly_species, and every proteomic dataset's species either matches
   one exactly or matches none (checked against all 27 datasets -- no near
   misses, so no fuzzy matching is needed or wanted).

   Empty for a reference proteome (Nematostella vectensis, Exaiptasia diaphana,
   Hydra vulgaris have a transcriptome assembly as a *species*, but their
   proteomic datasets were searched against the NCBI proteome, whose XP_ ids are
   not in trans_assembly).  The check below is therefore data-driven: the
   annotation block and columns appear only when rows actually resolve, which is
   what keeps a page from promising annotation it cannot deliver.

   `$__annSp` is the species whose assembly holds the identified sequences, and
   it is *not* always the dataset's own species.  Two datasets were searched
   against a congener's transcriptome-derived proteome because their own species
   has no assembly at all (PXD060643 Actinia fragacea -> Actinia tenebrosa,
   PXD010839 Zoanthus natalensis -> Zoanthus sociatus); their identified
   sequences are the congener's Trinity contigs, and those very contigs are in
   `trans_assembly` under the congener's abbr1 -- 4,572 of 4,587 rows and 3,675
   of 3,685 respectively.  Keying the join on the deposited species therefore
   hid annotation that the site actually holds.

   The surrogate is read from `proteome_file`, the only column that names it:
   every transcriptome-derived search space is stored as
   `<dir>/transcriptome_proteins/<Genus_species>.rep.pep`, and the basename with
   its underscores turned back into spaces is the `species` value verbatim
   (checked against all five assemblies: Orbicella annularis, Corallium rubrum,
   Ceratonova shasta, Actinia tenebrosa, Zoanthus sociatus).  A reference
   proteome leaves the column NULL, so it falls through to the species lookup
   exactly as before -- the 7 datasets that already showed annotation resolve to
   the same abbr1 they did before this change, and the combined multi-species
   search spaces match neither branch and stay annotation-free. */
$__annAbbr    = '';
$__annSp      = '';
$__annForeign = false;
if ($__cur && cnido_tbl_exists($conn, 'trans_assembly_species')
           && cnido_tbl_exists($conn, 'trans_assembly')) {
    $__annCand = array();
    $__pf = isset($__cur['proteome_file']) ? trim((string)$__cur['proteome_file']) : '';
    if ($__pf !== '' && preg_match('~/transcriptome_proteins/([^/;]+)\.rep\.pep$~', $__pf, $__m)) {
        $__annCand[] = str_replace('_', ' ', $__m[1]);
    }
    /* The dataset's own species is the fallback, and stays the only candidate
       when `proteome_file` is NULL (every reference proteome). */
    $__annCand[] = $__cur['species'];
    foreach ($__annCand as $__cand) {
        $e = mysqli_real_escape_string($conn, $__cand);
        $ar = cnido_q($conn, "SELECT abbr1 FROM trans_assembly_species
                               WHERE species = '$e' LIMIT 1");
        if ($ar && ($ax = mysqli_fetch_row($ar)) && $ax[0] !== '') {
            $__annAbbr = $ax[0];
            $__annSp   = $__cand;
            break;
        }
    }
    $__annForeign = ($__annAbbr !== '' && $__annSp !== $__cur['species']);
}

/* ---- sort state --------------------------------------------------------- */

/* Every column of this table is sortable server-side.  It has to be the server:
   the page shows 25 of up to 5,013 rows, so sorting in the browser would just
   reorder the visible 25 while the arrow claimed the column was sorted.
   (Until now js/table-sort.js did exactly that here; it is off for this table
   now because every <th> carries a link.)

   'default' is a hidden key: the page's natural order is "most unique peptides,
   then most PSMs, then gene id", which no single arrow can express, so the
   initial view keeps that order and every arrow is the unselected ↕.

   Coverage and q-value are rendered through pa_cov() / pa_qval() (rounded), so
   the ORDER BY targets the underlying float/double columns.  Both are declared
   NULL-able even though neither holds a NULL today; the (col IS NULL) prefix is
   the site-wide "missing always last" rule, and it costs one constant sort key.
   It cannot be cnido_sort_dec() here: that helper decides "missing" with a
   regex on the column's text, and MySQL renders a small double like 6.78288e-05
   in exponent form, which the regex would read as missing.

   The six annotation keys are added only when the transcriptome LEFT JOIN is
   actually in the query -- asking for ?sort=uniprot on a dataset with no
   transcriptome assembly would otherwise be an unknown-column error. */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array(
    'default' => 'p.n_unique_peptides DESC, p.n_psms DESC, p.gene_id',
    'gene'    => 'p.gene_id',
    'protein' => 'p.protein_id',
    'psms'    => 'p.n_psms',
    'unique'  => 'p.n_unique_peptides',
    'cov'     => array('(p.coverage_pct IS NULL)', 'p.coverage_pct'),
    'len'     => 'p.length',
    'q'       => array('(p.best_q IS NULL)', 'p.best_q'),
    'desc'    => 'p.description',
);
if ($__annAbbr !== '') {
    foreach (array('uniprot', 'pfam', 'panther', 'interpro', 'go', 'kegg') as $__k) {
        $__sortKeys[$__k] = 't.' . $__k;
    }
}
/* Tie keys: gene_id is not unique (one gene, many proteins) and neither is
   protein_id across datasets, so the primary key closes the order -- without a
   strict total order the LIMIT boundary on page 2 repeats or drops rows. */
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'default',
                                                   array('p.protein_id', 'p.id'));
$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');

/* ---- the annotation LEFT JOIN ------------------------------------------- */
/* Built here rather than next to the listing query because the search box below
   has to know whether the annotation columns exist: they are on the page and a
   reader searches them, so the term has to match them too.  Built from
   $__annAbbr, which is already settled by now. */
$__annSel  = '';
$__annJoin = '';
if ($__annAbbr !== '') {
    $ae = mysqli_real_escape_string($conn, $__annAbbr);
    $__annJoin = " LEFT JOIN trans_assembly t FORCE INDEX (ix_prot)
                          ON t.abbr1 = '$ae' AND t.protein = p.protein_id";
    $__annSel  = ", t.uniprot, t.uniprot_desc, t.pfam, t.pfam_desc,
                   t.panther, t.panther_desc, t.interpro, t.interpro_desc,
                   t.go, t.kegg";
}

/* ---- pagination --------------------------------------------------------- */
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
if ($per_page <= 0 || $per_page > 500) { $per_page = 25; }
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page <= 0) { $page = 1; }

/* ---- the search term ---------------------------------------------------- */
/* The table is paged (25 of up to 5,033 rows), so the filter has to run in SQL:
   anything done in the browser would only ever see the 25 rows on screen.  Same
   idiom as the other tables in this release -- see includes/state.php for
   cnido_search_term() / cnido_like_any().
 *
 * Searched: the text the table prints.  Gene / protein id and the dataset's own
 * description always exist; the six transcriptome annotation columns are added
 * only when the join above is in the query, because a term cannot match a column
 * that is not being selected.
 *
 * Not searched: PSMs, unique peptides, coverage, length, q-value.  Those are
 * numbers -- "3" would match a third of the table (the same call made on the
 * phenotype table).  Sort by the column instead.
 *
 * Cost: proteomic_proteins is 38,324 rows and the dataset filter is indexed
 * (idx_dataset), so a page never scans more than ~5,033 of them; the LIKE is a
 * sub-millisecond addition on top.  No index is added: none could be used for
 * `%term%` anyway, and this table does not need one at this size. */
$q = cnido_search_term(isset($_GET['q']) ? $_GET['q'] : '');

/* ---- the query ---------------------------------------------------------- */
$where = array();
if ($__dsUnknown !== '') {
    /* matches nothing: the request named a dataset that is not here.  Without
       this the absent dataset filter would return every dataset's proteins. */
    $where[] = "1 = 0";
} elseif ($dataset !== '') {
    $where[] = "p.dataset_id = '" . mysqli_real_escape_string($conn, $dataset) . "'";
}
if ($species !== '') {
    $where[] = "d.species = '" . mysqli_real_escape_string($conn, $species) . "'";
}
if (!$showContam) {
    $where[] = "p.is_contaminant = 0";
}
if ($q !== '') {
    $cols = array('p.gene_id', 'p.protein_id', 'p.description');
    if ($__annJoin !== '') {
        /* Identifier and description of each annotation source, so a search for
           "GO:0003341" or "zinc finger" finds the rows that display them. */
        foreach (array('uniprot', 'uniprot_desc', 'pfam', 'pfam_desc',
                       'panther', 'panther_desc', 'interpro', 'interpro_desc',
                       'go', 'kegg') as $__c) {
            $cols[] = 't.' . $__c;
        }
    }
    $__qIdx  = count($where);   /* remembered so the base view can be $where minus it */
    $where[] = cnido_like_any($conn, $q, $cols);
}
$w = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_records = 0;
$n_contam      = 0;
$rows          = array();
/* Rows the same view holds *without* the search term.  Only asked for when the
   search came back empty, and only so the page can say which of the two things
   happened: nothing here to search, or nothing here matching the term.  Asking
   for it on every request would double the COUNT for a number nobody reads. */
$__baseTotal = null;

if ($__hasSchema) {
    /* The annotation join is here because the search clause can name t.* columns.
       It cannot change the count: `protein` is unique inside an abbr1 (checked:
       47,775 rows, 47,775 distinct proteins for EDIAP) and it is a LEFT JOIN, so
       every protein row survives exactly once. */
    $cq = cnido_q($conn,
        "SELECT COUNT(*) AS n FROM proteomic_proteins p
           JOIN proteomic_datasets d ON d.dataset_id = p.dataset_id
           $__annJoin
           $w");
    $total_records = ($cq && ($cr = mysqli_fetch_assoc($cq))) ? (int)$cr['n'] : 0;

    if ($total_records === 0 && $q !== '') {
        /* Drop the one clause the search added, at the index recorded above --
           comparing clause strings would misfire when two filters happen to be
           textually equal. */
        $bw = $where;
        unset($bw[$__qIdx]);
        $bws = $bw ? ('WHERE ' . implode(' AND ', $bw)) : '';
        $bq  = cnido_q($conn,
            "SELECT COUNT(*) AS n FROM proteomic_proteins p
               JOIN proteomic_datasets d ON d.dataset_id = p.dataset_id $bws");
        $__baseTotal = ($bq && ($br = mysqli_fetch_assoc($bq))) ? (int)$br['n'] : 0;
    }

    /* contaminants are always counted, so the page can state how many were
       withheld rather than silently dropping them.  Built separately from
       $where: reusing $where would either double the clause or, when no filter
       is active, emit a dangling "WHERE  AND ...". */
    $contWhere = array();
    if ($__dsUnknown !== '') {
        $contWhere[] = "1 = 0";
    } elseif ($dataset !== '') {
        $contWhere[] = "p.dataset_id = '" . mysqli_real_escape_string($conn, $dataset) . "'";
    }
    if ($species !== '') {
        $contWhere[] = "d.species = '" . mysqli_real_escape_string($conn, $species) . "'";
    }
    $contWhere[] = "p.is_contaminant = 1";
    $cw = 'WHERE ' . implode(' AND ', $contWhere);
    $ccq = cnido_q($conn,
        "SELECT COUNT(*) AS n FROM proteomic_proteins p
           JOIN proteomic_datasets d ON d.dataset_id = p.dataset_id $cw");
    $n_contam = ($ccq && ($ccr = mysqli_fetch_assoc($ccq))) ? (int)$ccr['n'] : 0;

    $total_pages = ($per_page > 0) ? (int)ceil($total_records / $per_page) : 1;
    if ($total_pages > 0 && $page > $total_pages) { $page = $total_pages; }
    $offset = ($page - 1) * $per_page;

    $rq = cnido_q($conn,
        "SELECT p.gene_id, p.protein_id, p.links_gene, p.n_psms, p.n_unique_peptides,
                p.coverage_pct, p.length, p.best_q, p.description, p.is_contaminant,
                d.species, d.dataset_id$__annSel
           FROM proteomic_proteins p
           JOIN proteomic_datasets d ON d.dataset_id = p.dataset_id
           $__annJoin
           $w
          $__orderSql
          LIMIT $offset, $per_page");
    while ($rq && ($x = mysqli_fetch_assoc($rq))) { $rows[] = $x; }

    /* ---- names for the GO and KEGG identifiers shown on this page ---------
       trans_assembly stores identifiers only in `go` and `kegg`, no paired
       description column -- unlike uniprot/pfam/panther/interpro.  Their names
       live in the module's two dictionaries (trans_assembly_go /
       trans_assembly_ko), which is also where the Transcriptome Assembly page
       resolves them.  Look up only the identifiers on this page: a page is 25
       rows, so a few hundred ids in two IN queries against indexed primary keys
       beats a join in the listing query, which could not use an index on the
       first id of a ';'-separated list anyway.
       A failure here is silent on purpose: the cell then shows the identifier
       without a second line, which is what the module itself would show. */
    $__dict = array('go' => array(), 'kegg' => array());
    if ($__annAbbr !== '' && $rows
        && cnido_tbl_exists($conn, 'trans_assembly_go')
        && cnido_tbl_exists($conn, 'trans_assembly_ko')) {
        $need = array('go' => array(), 'kegg' => array());
        foreach ($rows as $x) {
            foreach (array('go', 'kegg') as $c) {
                foreach (explode(';', (string)(isset($x[$c]) ? $x[$c] : '')) as $v) {
                    $v = trim($v);
                    if ($v !== '') { $need[$c][$v] = 1; }
                }
            }
        }
        $dictTbl = array('go'   => array('trans_assembly_go', 'go_id'),
                         'kegg' => array('trans_assembly_ko', 'ko'));
        foreach ($dictTbl as $c => $spec) {
            if (!$need[$c]) { continue; }
            $in = array();
            foreach (array_keys($need[$c]) as $v) {
                $in[] = "'" . mysqli_real_escape_string($conn, $v) . "'";
            }
            $dq = cnido_q($conn, "SELECT {$spec[1]} AS k, name AS v FROM {$spec[0]}
                                   WHERE {$spec[1]} IN (" . implode(',', $in) . ")");
            while ($dq && ($dr = mysqli_fetch_assoc($dq))) { $__dict[$c][$dr['k']] = $dr['v']; }
        }
    }

    /* ---- summary numbers for the panels above the table ------------------
       One pass over the same filtered set the table lists (not over a page of
       it), so every figure in the panels describes exactly what the table
       holds.  Two things come out of the one query:

         * how well each protein is supported (unique-peptide buckets).  The
           single-peptide share is the caveat a reader of a proteomics table
           needs first: on PXD041235 half of the proteins rest on one peptide.
         * how many of them carry annotation, per source, when the search space
           was a transcriptome assembly.  This doubles as the test for whether
           the annotation columns belong on the page at all: if no row resolves
           in trans_assembly there is nothing to show and nothing is promised.

       Only run for a named dataset: with no dataset selected the table spans
       every dataset, and "how well are these proteins supported" would be
       describing a mixture, not a dataset. */
    $__stat = null;
    if ($__hasSchema && $__cur && $dataset !== '') {
        $anyOr = array();
        foreach (array('uniprot', 'pfam', 'panther', 'interpro', 'go', 'kegg') as $c) {
            $anyOr[] = "COALESCE(t.$c, '') <> ''";
        }
        $annCols = $__annAbbr !== ''
            ? ", SUM(t.protein IS NOT NULL) AS ann_matched
               , SUM(COALESCE(t.uniprot, '')  <> '') AS ann_uniprot
               , SUM(COALESCE(t.pfam, '')     <> '') AS ann_pfam
               , SUM(COALESCE(t.panther, '')  <> '') AS ann_panther
               , SUM(COALESCE(t.interpro, '') <> '') AS ann_interpro
               , SUM(COALESCE(t.go, '')       <> '') AS ann_go
               , SUM(COALESCE(t.kegg, '')     <> '') AS ann_kegg
               , SUM(" . implode(' OR ', $anyOr) . ") AS ann_any"
            : "";
        $sq = cnido_q($conn,
            "SELECT COUNT(*) AS n
                  , SUM(p.n_unique_peptides = 1) AS pep1
                  , SUM(p.n_unique_peptides = 2) AS pep2
                  , SUM(p.n_unique_peptides BETWEEN 3 AND 4) AS pep3
                  , SUM(p.n_unique_peptides BETWEEN 5 AND 9) AS pep4
                  , SUM(p.n_unique_peptides >= 10) AS pep5
                  , MAX(p.n_unique_peptides) AS pepmax
                  , MAX(p.n_psms) AS psmmax
                  , MAX(p.coverage_pct) AS covmax
                  , SUM(COALESCE(p.description, '') <> '') AS n_desc
                  $annCols
             FROM proteomic_proteins p
             JOIN proteomic_datasets d ON d.dataset_id = p.dataset_id
             $__annJoin
             $w");
        if ($sq && ($sx = mysqli_fetch_assoc($sq))) { $__stat = $sx; }
    }
}
$total_pages = isset($total_pages) ? $total_pages : 1;

/* ---- query string carried into every link (fixes the broken pagination) -- */
/* The current sort rides along too: every pager link, the per-page selector and
   the header links are all built from this one string, so sorting survives
   paging.  It is only appended when it differs from the default (see
   cnido_sort_qs), which keeps the default URL as short as it was.
 *
 * The search term rides along for the same reason: without it, paging or
 * re-sorting a search result would silently drop the search and the reader would
 * be looking at a different set of rows than the header arrow claims. */
$__qQs = ($q !== '' ? '&q=' . urlencode($q) : '');
$__qsNoQ = 'dataset=' . urlencode($dataset)
    . '&species=' . urlencode($species)
    . '&contam=' . ($showContam ? '1' : '0');
$qs = $__qsNoQ . $__qQs;
$__sortQs = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'default');
if ($__sortQs !== '') { $qs .= '&' . $__sortQs; $__qsNoQ .= '&' . $__sortQs; }

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Comet `search_enzyme_number` -> readable name.  Rendering every non-zero value
   as "trypsin" misdescribes the GluC digests (the coral skeletal series has
   both) and would silently mislabel any enzyme added later. */
function cnido_enzyme_label($n) {
    static $m = array(
        '0' => 'none (endogenous peptides)',
        '1' => 'trypsin', '2' => 'trypsin/P', '3' => 'Lys-C', '4' => 'Lys-N',
        '5' => 'Arg-C', '6' => 'Asp-N', '7' => 'CNBr', '8' => 'Glu-C',
        '9' => 'pepsin A', '10' => 'chymotrypsin', '11' => 'no cleavage',
    );
    $k = (string)(int)$n;
    return isset($m[$k]) ? $m[$k] : ('enzyme ' . $k);
}

/* `proteomic_datasets.status` is a machine token written by the pipeline.  The
   provenance panel used to print it raw -- "no_identifications_at_fdr" reads as
   an internal error code rather than as the result it actually describes.  The
   token stays available in the cell's tooltip; the cell itself says what it
   means. */
function cnido_status_label($s) {
    static $m = array(
        'reprocessed'               => 'reprocessed — identifications at 1% FDR',
        'no_identifications_at_fdr' => 'reprocessed — no identification at 1% FDR',
        'psms_below_peptide_fdr'    => 'reprocessed — PSMs pass, no peptide reaches 1% FDR',
    );
    $k = (string)$s;
    return isset($m[$k]) ? $m[$k] : $k;
}

/* ---- helpers for the annotation columns --------------------------------- */

/* Same six sources, labels, colours and link targets as the Transcriptome
   Assembly module ($TS_LINK / $TS_LABEL in trans_assembly_species.php).  Kept
   deliberately identical: a reader who follows GO:0003341 from this page and
   from that one must land on the same entry, and the colour has to mean the
   same source on both. */
$PA_ANN = array(
    'uniprot'  => array('UniProt',  'u', 'https://www.uniprot.org/uniprotkb/'),
    'pfam'     => array('Pfam',     'f', 'https://www.ebi.ac.uk/interpro/entry/pfam/'),
    'panther'  => array('PANTHER',  'a', 'https://www.pantherdb.org/panther/family.do?clsAccession='),
    'interpro' => array('InterPro', 'i', 'https://www.ebi.ac.uk/interpro/entry/InterPro/'),
    'go'       => array('GO',       'g', 'https://amigo.geneontology.org/amigo/term/'),
    'kegg'     => array('KEGG',     'k', 'https://www.kegg.jp/entry/'),
);

/* A best q-value at 3 significant figures.  The raw Percolator values run from
   6.8e-5 to 1.0e-2 and print as up to ten characters ("0.0000678288"), which
   does not fit the column and buries the magnitude; three figures is how these
   are read everywhere else.  The unrounded value stays in the cell's title, so
   nothing is hidden -- only shortened. */
function pa_qval($q) {
    $s = sprintf('%.3g', (float)$q);
    return (strpos($s, 'e') === false) ? rtrim(rtrim($s, '0'), '.') : $s;
}

/* Coverage as at most one decimal: the column stores a float, and PHP prints
   52 for 52.0 but 5.1 for 5.1, so the column would mix 52 and 5.1 by accident. */
function pa_cov($v) {
    return rtrim(rtrim(number_format((float)$v, 1, '.', ''), '0'), '.');
}

/* One annotation cell, two lines: the identifier, then what it means.
 *
 *   PF12775 +5        <- first identifier, linked to its entry page; +N counts
 *   AAA_7                the others, all of them listed in the title
 *
 * The identifier gets the first line because it is the thing a reader copies,
 * cites and searches for; the old cell printed the descriptions only and left
 * the accession in the href, so a Pfam cell read "AAA_7; AAA_6" with no way to
 * see which accessions those were.  The description underneath is what makes
 * the identifier legible, so the two belong together and neither is optional.
 * With the column at ~140px the line can be cut short -- the title has the full
 * text of every hit, and an ellipsis says so, where a silently clipped word
 * would not.
 *
 * `$dict` carries the GO and KEGG names looked up for this page (trans_assembly
 * has no description column for those two sources).  Description and identifier
 * are paired by index; when the two lists disagree in length -- a different
 * separator upstream -- the pairing cannot be trusted and the cell shows the
 * identifiers alone rather than a description next to the wrong accession.
 */
function pa_ann_cell($src, $ids, $desc, $dict = array()) {
    global $PA_ANN;
    $m   = $PA_ANN[$src];
    $ids = array_values(array_filter(array_map('trim', explode(';', (string)$ids)), 'strlen'));
    if (!$ids) { return '<span class="pa-none">&ndash;</span>'; }
    $nm  = array_values(array_filter(array_map('trim', explode(';', (string)$desc)), 'strlen'));
    $pairOk = ($nm && count($nm) === count($ids));

    $meaning = function ($i) use ($ids, $nm, $pairOk, $dict) {
        if ($pairOk) { return $nm[$i]; }
        return isset($dict[$ids[$i]]) ? $dict[$ids[$i]] : '';
    };

    $pairs = array();
    foreach ($ids as $i => $id) {
        $d = $meaning($i);
        $pairs[] = ($d !== '') ? $id . ' (' . $d . ')' : $id;
    }
    $title = $m[0] . ': ' . implode(' | ', $pairs);

    $a = '<a class="pa-id ' . $m[1] . '" href="' . h($m[2] . urlencode($ids[0])) . '"'
       . ' target="_blank" rel="noopener noreferrer" title="' . h($title) . '">'
       . h($ids[0]) . '</a>';
    if (count($ids) > 1) {
        $a .= '<span class="pa-more" title="' . h($title) . '">+' . (count($ids) - 1) . '</span>';
    }
    $first = $meaning(0);
    if ($first !== '') {
        $a .= '<span class="pa-desc" title="' . h($first) . '">' . h($first) . '</span>';
    }
    return $a;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Proteomic Analysis - CnidoSite</title>
<meta name="description" content="Peptides and proteins identified by re-processing the raw mass-spectrometry data of every proteomic dataset through a single documented pipeline" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<style>
.pa-panel{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);
  padding:20px;margin:18px 0;border:1px solid #e2e8f0}
.pa-panel h3{margin:0 0 12px;font-size:16px;color:#1e293b}
.pa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}
.pa-cell{background:#f8fafc;border-radius:8px;padding:10px 12px;font-size:15px}
.pa-cell b{display:block;color:#64748b;font-weight:600;font-size:13px;margin-bottom:3px}
.pa-cell span{color:#0f172a;word-break:break-word}
.pa-total{background:linear-gradient(135deg,#1d4ed8,#1e40af);color:#fff;padding:10px 20px;
  border-radius:8px;font-weight:600;display:inline-block}
.pa-note{background:#fff7ed;border-left:4px solid #f59e0b;padding:12px 14px;
  border-radius:6px;margin:12px 0;font-size:16px;color:#78350f}
.pa-contam{background:#fef2f2;border-left:4px solid #ef4444;padding:10px 14px;
  border-radius:6px;margin:10px 0;font-size:16px;color:#7f1d1d}
/* An empty dataset states its result here instead of leaving three zeros and an
   empty table for the reader to interpret.  Same neutral grey as the panels, not
   a warning colour: an acquisition with no identification is a result, not a
   fault of the page. */
.pa-null{background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #94a3b8;
  border-radius:0 10px 10px 0;padding:14px 16px;margin:0 0 16px 0}
.pa-null-h{font-size:15px;font-weight:700;color:#1e293b;margin-bottom:6px}
.pa-null p{margin:7px 0 0 0;font-size:16px;color:#334155;line-height:1.65}
.pa-null .pa-null-note{font-size:16px;color:#475569;background:#fff;border:1px solid #e2e8f0;
  border-radius:8px;padding:10px 12px}
.pa-null .pa-null-sib a{font-weight:600;color:#1d4ed8}
/* ---- the publication's own result --------------------------------------- */
/* Shown under the explanation, and only where the re-analysis identified
   nothing.  It carries a stronger accent than the neutral grey of .pa-null
   because it is a different kind of claim -- someone else's numbers -- and the
   reader has to be able to tell at a glance which side of the page a figure
   belongs to.  The attribution block is inside the card rather than in a
   footnote for the same reason. */
.pa-pubcard{background:#fff;border:1px solid #ddd6fe;border-left:4px solid #6d28d9;
  border-radius:0 10px 10px 0;padding:16px 18px;margin:4px 0 0 0}
.pa-pub-h{font-size:15px;font-weight:700;color:#4c1d95;margin-bottom:8px}
.pa-pub-lead{margin:0 0 12px 0;font-size:15px;color:#334155;line-height:1.65}
.pa-pub-lead b{color:#4c1d95}
.pa-pub-att{background:#f5f3ff;border-radius:8px;padding:11px 13px;margin:0 0 12px 0}
.pa-pub-att .who{font-size:15px;color:#4c1d95;line-height:1.6;font-weight:600}
.pa-pub-att .cite{font-size:15px;color:#334155;margin-top:6px}
.pa-pub-att .links{font-size:15px;margin-top:6px}
/* three classes to match the specificity that already works for .pa-null-sib:
   the stylesheet's `a:link` rule sets its colour on (0,1,1). */
.pa-pubcard .pa-pub-att .links a{color:#6d28d9;font-weight:600;margin-right:14px}
.pa-pubcard .pa-pub-att .links a:hover{text-decoration:underline}
.pa-pub-meta{font-size:15px;color:#475569;margin:0 0 12px 0;line-height:1.6}
.pa-pub-meta b{color:#334155}
.pa-pub-note{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;
  padding:11px 13px;font-size:16px;color:#334155;line-height:1.65}
.pa-pub-foot{font-size:15px;color:#64748b;margin:10px 0 0 0;line-height:1.6}
.pa-pub-other{background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;
  padding:12px 14px;margin:14px 0 0 0}
.pa-pub-other .h{font-size:15px;font-weight:700;color:#6d28d9;margin-bottom:6px}
.pa-pub-other p{margin:0 0 10px 0;font-size:16px;color:#334155;line-height:1.6}
.pa-pub-other .verdict{margin:10px 0 0 0}
/* The published table is denser than the page's own: it is a record of what a
   paper printed, read against itself, not an index the reader navigates.  The
   `table.gridtable` prefix is required to tie with the stylesheet's rules. */
table.gridtable.pa-pubtab{font-size:15px;margin:0 0 4px 0}
table.gridtable.pa-pubtab th{font-size:15px;background:#f5f3ff;color:#4c1d95;
  padding:6px 4px}
table.gridtable.pa-pubtab td{font-size:15px;padding:5px 4px;line-height:1.45}
table.gridtable td.pubscore b{font-weight:700;color:#312e81}
.pa-pub-srch{margin-top:2px}
.pa-pub-srch .pa-cell.span{grid-column:1 / -1}
/* ---- identification panels --------------------------------------------- */
/* The chain PSMs -> peptides -> proteins, drawn the same way the Transcriptome
   Assembly page draws its own chain, so the two modules read alike. */
.pa-flow{display:flex;flex-wrap:wrap;align-items:stretch;gap:0;margin:0 0 16px 0}
.pa-flow .step{flex:1 1 170px;min-width:150px;background:#f8fafc;border:1px solid #e2e8f0;
  border-radius:10px;padding:11px 14px}
.pa-flow .step .k{font-size:13px;color:#64748b;font-weight:600}
.pa-flow .step .v{font-size:22px;font-weight:700;color:#1e293b;line-height:1.2;margin-top:3px;
  font-variant-numeric:tabular-nums}
.pa-flow .step .u{font-size:15px;color:#64748b;margin-top:2px;line-height:1.45}
.pa-flow .arrow{align-self:center;color:#94a3b8;font-size:18px;padding:0 9px}
.pa-flow .step.key{border-color:#bfdbfe;background:#f0f9ff}
.pa-flow .step.key .v{color:#1d4ed8}
@media (max-width:820px){ .pa-flow .arrow{display:none} }
.pa-sub{font-size:16px;color:#334155;font-weight:700;margin:16px 0 8px}
.pa-sub .hint{font-weight:400;color:#64748b;font-size:16px}
/* Bars are read, not clicked: every number they show is a count over exactly
   the rows the table below lists. */
.pa-bar{display:grid;grid-template-columns:150px 1fr 74px 60px;align-items:center;gap:12px;
  margin-bottom:7px;font-size:15px}
.pa-bar .lab{color:#334155;font-weight:600}
.pa-bar .track{background:#eef2f7;border-radius:6px;height:15px;overflow:hidden}
.pa-bar .fill{display:block;height:100%;border-radius:6px;min-width:3px}
.pa-bar .n,.pa-bar .pct{text-align:right;font-variant-numeric:tabular-nums;color:#334155}
.pa-bar .pct{color:#475569;font-weight:600}
.pa-bar.dim .lab,.pa-bar.dim .n,.pa-bar.dim .pct{color:#94a3b8}
/* 窄屏：150px + 74px + 60px 三列加间距要 320px，而面板内容盒只有 276px，
   1fr 的条被压成 0px、百分比顶出面板。改成两行：标签 + 计数 + 百分比一行，
   整宽的条一行。 */
@media (max-width:480px){
  .pa-bar{grid-template-columns:minmax(0,1fr) auto auto;gap:4px 8px}
  .pa-bar .lab{grid-column:1;grid-row:1}
  .pa-bar .n{grid-column:2;grid-row:1}
  .pa-bar .pct{grid-column:3;grid-row:1}
  .pa-bar .track{grid-column:1/-1;grid-row:2}
}
.pa-legend{font-size:16px;color:#64748b;margin:10px 0 0;line-height:1.6}
.pa-legend b{color:#334155}

/* ---- results table ------------------------------------------------------ */
/* Every cell the same size as the rest of the table, centred except the
   description.  The site's own `table.gridtable` rules are (0,1,1) and live in
   the linked stylesheet, so these have to repeat the `table.gridtable` part to
   tie on specificity and then win on document order -- a bare `.num` would lose
   to `table.gridtable td`. */
table.gridtable{font-size:15px}
table.gridtable th{font-size:15px;text-align:center;padding:7px 4px;vertical-align:bottom;
  line-height:1.4}
/* `white-space: normal` is not cosmetic here.  gridtable is `pre-wrap`
   site-wide, and the cells of this table are written across several source lines
   -- the literal newlines and indentation between `<td>` and its `<span>` were
   being rendered as blank lines, which is what made every row ~140px tall for
   one line of content.  A three-line description was then three lines plus five
   blanks.  Line-height likewise: body's `1.7em` resolves against body's 16px and
   is inherited as a fixed 27.2px, which at 13px text is twice what a data row
   wants. */
table.gridtable td{font-size:15px;text-align:center;vertical-align:middle;
  font-variant-numeric:tabular-nums;overflow:hidden;
  white-space:normal;line-height:1.5}
/* The quantification columns (PSMs / unique peptides / coverage / length /
   q-value) are centred like every other cell, and like `.tal` above the `tr`
   has to be in the selector: templatemo_style.css right-aligns numbers with
   `table.gridtable tr td.num` at (0,2,3), which beats a plain
   `table.gridtable td.num` (0,2,2) -- the short form was silently losing and
   the five columns came out right-aligned against the centre of their own
   headings.  Same (0,2,3) as the stylesheet's rule, so this one wins on
   document order because the page's <style> follows the <link>. */
table.gridtable td.num,
table.gridtable tr td.num{text-align:center}
/* The description column: left, not centred.  Written with the `tr` in the
   selector because every body row of this table is `<tr align="center">`, which
   makes `table.gridtable tr[align="center"] td` (0,2,3) and beats a plain
   `table.gridtable td.tal` (0,2,2) -- the short form was silently losing and the
   descriptions rendered centred, which is exactly what the class was added to
   avoid.  The global `.tal` rule in templatemo_style.css has the same (0,2,3)
   shape; this one comes later only to turn off the tabular figures that this
   page's own `table.gridtable td` sets for every cell. */
table.gridtable tr td.tal{font-variant-numeric:normal;line-height:1.5}
table.gridtable td.gene a{color:#1d4ed8;text-decoration:none;font-weight:600}
table.gridtable td.gene a:hover{text-decoration:underline}
table.gridtable td.gene .contig{color:#475569}
/* The protein id is a link into the Transcriptome Assembly module.  Weight 600 on
   the gene column and weight 400 here on purpose: both are internal links, so they
   share the blue, but a row that showed two bold ids would have no visual anchor.
   Selector drawn at (0,2,3) -- the `td` and the class are both needed to beat the
   stylesheet's `table.gridtable a` (0,1,2) and `a:link` (0,1,1), the trap the other
   pages of this release hit. */
table.gridtable td.prot a{color:#1d4ed8;text-decoration:none}
table.gridtable td.prot a:hover{text-decoration:underline}
/* The annotation cells are two lines by construction: the identifier on the
   first, what it means on the second.  The description wraps rather than being
   clipped.  These columns are 8.4% of a `table-layout: fixed` table, so a cell
   only has about 125px of text width -- roughly twenty characters -- and the
   previous `nowrap` + `overflow:hidden` + `ellipsis` therefore cut off nearly
   every description (the median is 28 characters).  `overflow-wrap: anywhere`
   is the safety net for text with nothing to break on: an InterPro free-text
   description runs to 1000+ characters and has no separators inside it. */
table.gridtable td.ann{line-height:1.45;padding:4px 3px;overflow:visible;
  overflow-wrap:anywhere}
.pa-id{display:inline-block;font-weight:600;text-decoration:none;
  overflow-wrap:anywhere}
.pa-id:hover{text-decoration:underline}
.pa-id.u{color:#1d4ed8} .pa-id.f{color:#0f766e} .pa-id.a{color:#b45309}
.pa-id.i{color:#6d28d9} .pa-id.g{color:#15803d} .pa-id.k{color:#be123c}
.pa-more{font-size:12px;color:#94a3b8;cursor:help;margin-left:3px}
<?php /* 13px, like every other cell in this table ("table字体显示一致"): the second
   line is content, not a caption, so it does not get a size of its own -- the
   colour and the position carry the hierarchy.  `+N` is the one exception: it
   is a count badge, and at 13px it would read as part of the identifier. */ ?>
.pa-desc{display:block;color:#475569;line-height:1.4;margin-top:1px;
  white-space:normal;overflow-wrap:anywhere;cursor:help}
.pa-none{color:#cbd5e1}
.pa-ann-key{font-size:15px;color:#64748b;margin:10px 0 0;line-height:1.7}
.pa-ann-key b{color:#334155}
.pa-ann-key span{font-weight:600;margin-right:3px}

.pa-pager{margin:18px 0;text-align:center}
.pa-pager a,.pa-pager span{padding:7px 12px;margin:0 2px;border:1px solid #e2e8f0;
  border-radius:6px;text-decoration:none;color:#475569;font-size:15px;background:#fff}
.pa-pager a:hover{border-color:#1d4ed8;color:#1d4ed8}
.pa-pager .on{background:#1d4ed8;color:#fff;border-color:#1d4ed8}

/* ---- search box above the table ----------------------------------------- */
.pa-search{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#fff;
  border:1px solid #e2e8f0;border-radius:12px;padding:15px 20px;margin:18px 0;
  box-shadow:0 4px 20px rgba(0,0,0,.08)}
.pa-search label{font-weight:600;color:#475569}
.pa-search input[type=text]{flex:1 1 320px;min-width:200px;padding:10px 15px;
  border:2px solid #e2e8f0;border-radius:8px;font-size:15px;transition:all .3s ease}
.pa-search input[type=text]:focus{outline:none;border-color:#1d4ed8;
  box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.pa-search button{padding:10px 22px;background:#1d4ed8;color:#fff;border:none;
  border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;transition:all .3s ease}
.pa-search button:hover{background:#1e40af}
/* Written as a.pa-clear on purpose: templatemo_style.css sets
   a:link,a:visited{color:#1d4ed8} at (0,1,1), which beats a bare class (0,1,0) --
   a plain .pa-clear link would come out in the stylesheet's blue, not this one
   (see the same fix on the other pages of this release). */
a.pa-clear{color:#1d4ed8;text-decoration:none;font-size:15px;font-weight:500;padding:6px 2px}
a.pa-clear:hover{text-decoration:underline}
.pa-hit{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 18px;
  margin:0 0 15px;color:#1e40af;font-size:15px}
/* Not .pa-none -- that name is already taken by the grey "None" inside a cell
   (.pa-none{color:#cbd5e1}), and reusing it would have painted this block's text
   nearly invisible. */
.pa-nores{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;
  padding:26px 24px;margin:18px 0;color:#475569;font-size:15px}
.pa-nores h3{margin:0 0 10px;font-size:16px;color:#1e293b}
.pa-nores p{margin:8px 0;line-height:1.7}
@media (max-width:820px){
  .pa-search{flex-direction:column;align-items:stretch}
  /* flex:0 0 auto 不能省：基础规则里的 flex:1 1 320px 在**竖排**容器里 320 是
     "高度"基准（flex-basis 跟主轴走），只清 width 的话输入框会长成 320px 高，
     手机上就是搜索框底下一条大空白。 */
  .pa-search input[type=text]{width:100%;flex:0 0 auto}
}
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Proteomic Analysis</b></legend>

<p class="paleo-intro">
Peptides and proteins identified by re-processing the raw mass-spectrometry data of
every proteomic dataset with a single documented pipeline
(<b>Comet 2026.01</b> plus cRAP contaminants, with <b>Percolator</b> target-decoy
FDR control). Each dataset was searched against the search space its own panel
names &mdash; the CnidoSite reference proteome where CnidoSite holds one for the
species (11 of the 27), otherwise a congener surrogate, a de novo transcriptome
assembly or a combined multi-species database (the remaining 16). Search parameters are
dataset-specific and are shown in full for each dataset; peptides were mapped onto
the searched proteome, so each row is one protein of that proteome. The Gene column
links to its CnidoSite gene page whenever the search space was the CnidoSite
reference proteome. Where it was a de novo transcriptome assembly the identified
transcripts have no gene page, and their UniProt, Pfam, PANTHER, InterPro, GO and
KEGG annotation is shown in the table instead.
</p>

<?php /* Cross-link to the site's own tables.  This page lists only the species that
   could be re-processed; the nine with no transcriptome assembly, hence no search
   space, are on /proteomic_analysis.php.  The two page sets are complementary, so
   neither may replace the other -- see includes/proteomic_crosslinks.php, and the
   note at 5.web/INSTALL.md step 6.  The per-species link is only emitted when the
   mapping is one of the verified ones; an unmapped species gets the general link
   rather than a filter that would render as "no data". */
require_once __DIR__ . '/includes/proteomic_crosslinks.php';
echo cnido_proteomic_note_rebuild($__cur ? $__cur['species'] : ''); ?>

<?php if ($__sqlError !== ''): ?>
<div class="pa-note" style="background:#fef2f2;border-left-color:#ef4444;color:#7f1d1d">
  <b>The database rejected one of this page's queries, so the table below is
  incomplete or empty.</b> This is a fault in the page, not an absence of data.
  The server said: <code><?= h($__sqlError) ?></code>
</div>
<?php endif; ?>

<?php if (!$__hasSchema): ?>
<div class="pa-note">
  <b>Proteomics tables are not loaded yet.</b> Import
  <code>4.results/load/proteomics_load.sql</code> into the <code>cnidaria</code>
  database to enable this module.
</div>
<?php else: ?>

<?php if ($__dsUnknown !== ''): ?>
<div class="pa-note">
  <b>No dataset matches &ldquo;<?= h($__dsUnknown) ?>&rdquo;.</b>
  Nothing is listed below, because showing another dataset here would attribute
  its proteins to the one you asked for. Choose a dataset from the list &mdash;
  datasets are named by their PRIDE accession, for example <code>PXD009253</code>.
</div>
<?php endif; ?>

<?php if ($__spUnknown): ?>
<div class="pa-note">
  <b>No dataset in this release is from <i><?= h($species) ?></i>.</b>
  The table below is empty rather than showing another species under this name.
  Clear the filter by choosing a dataset from the list.
</div>
<?php endif; ?>

<!-- ================= dataset selector ================= -->
<div class="pa-panel">
<form method="get" action="/proteomic_reanalysis.php">
  <table style="width:100%;max-width:820px">
    <tr>
      <td style="width:130px"><b>Dataset</b></td>
      <td>
        <select name="dataset" style="width:100%;padding:9px 11px;border:2px solid #e2e8f0;border-radius:8px">
          <?php /* Ten Orbicella annularis acquisitions share one species and one
                   tissue, so species + tissue cannot tell them apart -- and four of
                   them (the MED-FASP Glu-C fractions) resolve nothing at all, so
                   the list also has to say which entries are empty.  Name the
                   digest whenever it is not trypsin: it is the one field in the
                   record that distinguishes those ten. */
                foreach ($__datasets as $d): ?>
            <option value="<?= h($d['dataset_id']) ?>"<?= ($d['dataset_id'] === $dataset) ? ' selected="selected"' : '' ?>>
              <?= h($d['species']) ?> &mdash; <?= h($d['tissue']) ?><?php
                if (!in_array((int)$d['cnido_enzyme'], array(1, 2), true)) {
                    echo ' &mdash; ' . h(cnido_enzyme_label($d['cnido_enzyme']));
                }
                if ((int)$d['n_proteins'] === 0) { echo ' &mdash; no identifications'; } ?> (<?= h($d['pxd']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </td>
    </tr>
    <tr>
      <td><b>Contaminants</b></td>
      <td>
        <label style="font-size:15px">
          <input type="checkbox" name="contam" value="1"<?= $showContam ? ' checked="checked"' : '' ?>>
          include cRAP contaminants in the table
        </label>
      </td>
    </tr>
    <tr><td></td><td>
      <input type="submit" value="View Proteins" class="submit-btn"
             style="padding:10px 26px;background:linear-gradient(135deg,#1d4ed8,#1e40af);
                    color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer">
    </td></tr>
  </table>
</form>
</div>

<?php
/* The search space qualifies every number on this page, so it is stated before
   them.  This is the page that carries the protein table -- and the one a gene
   page links to -- so a qualified search space that appears only on
   proteomic_dataset.php is not stated where the numbers are actually read.
   Rendered for any dataset whose source type is not a plain reference proteome,
   and also for a reference proteome that still carries a note: that is a species
   deposited under a name the reference proteome does not use, which the reader
   cannot tell from the species cell alone. */
if ($__cur) {
    $__warns = array(
        'congener'      => 'Search space is a different species (congener surrogate)',
        'transcriptome' => 'Search space is a transcriptome assembly, not a reference proteome',
        'combined'      => 'Search space is a combined multi-species database',
    );
    $__ptype = isset($__cur['proteome_source_type']) ? $__cur['proteome_source_type'] : '';
    $__pnote = isset($__cur['proteome_note']) ? $__cur['proteome_note'] : '';
    $__warn  = isset($__warns[$__ptype]) ? $__warns[$__ptype]
             : ($__pnote !== ''
                ? "Search space: the deposited species name is not the reference proteome's"
                : '');
    if ($__warn !== '') { ?>
<div class="pa-note">
  <b><?= h($__warn) ?></b>
  <div style="margin-top:4px"><?= h($__pnote !== ''
        ? $__pnote
        : (isset($__cur['proteome_source']) ? $__cur['proteome_source'] : '')) ?></div>
</div>
<?php }
}
?>

<?php if ($__cur && $__stat):
      /* Every figure below is computed over the same filtered set the table
         lists, so the panels and the table cannot disagree. */
      $st       = $__stat;
      $nSet     = (int)$st['n'];
      $pctOf    = function ($v) use ($nSet) { return $nSet > 0 ? 100.0 * (int)$v / $nSet : 0; };
      $annMatch = $__annAbbr !== '' ? (int)$st['ann_matched'] : 0;
      /* Annotation shares are out of the proteins the assembly can account for,
         which is not $nSet once cRAP contaminants are displayed. */
      $pctAnn   = function ($v) use ($annMatch) { return $annMatch > 0 ? 100.0 * (int)$v / $annMatch : 0; };

      /* A dataset that resolved nothing at all is a result the page has to state,
         not an empty table it has to leave the reader to interpret.  Six of the
         27 datasets are in this state (PXD027774 for a data-quality reason of its
         own, five Orbicella annularis acquisitions of which four are the MED-FASP
         Glu-C fractions) and their status notes carry the explanation.  Sibling
         acquisitions of the same species that did identify proteins are the one
         thing a reader landing here actually wants next, and where the depositing
         publication reported its own result for this sample, that is the other
         (see the published-result card below). */
      $__null = ((int)$__cur['n_proteins'] === 0);
      $__sibs = array();
      if ($__null) {
          foreach ($__datasets as $d) {
              if ($d['species'] === $__cur['species']
                  && $d['dataset_id'] !== $__cur['dataset_id']
                  && (int)$d['n_proteins'] > 0) { $__sibs[] = $d; }
          }
      }
?>
<!-- ================= what this dataset identified ================= -->
<div class="pa-panel">
  <h3>What this dataset identified &mdash; <?= h($__cur['pxd']) ?></h3>

  <div class="pa-flow">
    <div class="step">
      <div class="k">PSMs</div>
      <div class="v"><?= number_format((int)$__cur['n_psms']) ?></div>
      <div class="u">peptide-spectrum matches at q &le; 0.01</div>
    </div>
    <div class="arrow" aria-hidden="true">&rarr;</div>
    <div class="step">
      <div class="k">Peptides</div>
      <div class="v"><?= number_format((int)$__cur['n_peptides']) ?></div>
      <div class="u">distinct peptide sequences</div>
    </div>
    <div class="arrow" aria-hidden="true">&rarr;</div>
    <div class="step key">
      <div class="k">Proteins</div>
      <div class="v"><?= number_format((int)$__cur['n_proteins']) ?></div>
      <div class="u">listed in the table below<?= $nSet !== (int)$__cur['n_proteins']
            ? ' (' . number_format($nSet) . ' match the current selection)' : '' ?></div>
    </div>
    <?php if ($annMatch > 0): ?>
    <div class="arrow" aria-hidden="true">&rarr;</div>
    <div class="step">
      <div class="k">Annotated</div>
      <div class="v"><?= number_format((int)$st['ann_any']) ?></div>
      <?php /* A share of the proteins that are *in* the assembly, not of every
               row listed: with contaminants shown the two differ, and this step
               is about annotation, which only the assembly can supply. */ ?>
      <div class="u"><?= number_format($annMatch > 0 ? 100.0 * (int)$st['ann_any'] / $annMatch : 0, 1) ?>%
        of the <?= number_format($annMatch) ?> in the assembly carry a hit in at least one source</div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($__null): ?>
  <!-- ===== empty result: say why, and where the rest of this species is ===== -->
  <div class="pa-null">
    <div class="pa-null-h">
      <?= (int)$__cur['n_psms'] > 0
            ? 'No protein could be reported from this dataset'
            : 'This dataset contains no identification at the 1% false-discovery threshold' ?>
    </div>
    <p>
    <?php if ((int)$__cur['n_psms'] > 0): ?>
      <?= number_format((int)$__cur['n_psms']) ?> peptide-spectrum match<?= (int)$__cur['n_psms'] == 1 ? '' : 'es' ?>
      passed the PSM-level threshold, but none survived at the peptide level, and a protein is only
      reported from a peptide that did. The table below is empty because there is nothing to report at
      this threshold &mdash; not because a filter is hiding rows.
    <?php else: ?>
      The raw file was searched in full, with the parameters listed below, and nothing in it reached a
      1% false-discovery rate: no peptide, so no protein. The table below is empty because the search
      found nothing at this threshold &mdash; not because a filter is hiding rows.
    <?php endif; ?>
    </p>
    <?php if (!empty($__cur['status_note'])): ?>
      <p class="pa-null-note"><?= h($__cur['status_note']) ?></p>
    <?php endif; ?>
    <?php if ($__sibs): ?>
      <p class="pa-null-sib">
        <?= count($__sibs) === 1 ? 'One other dataset' : number_format(count($__sibs)) . ' other datasets' ?>
        for <i><?= h($__cur['species']) ?></i> did identify proteins:
        <?php foreach ($__sibs as $i => $s): ?><?= $i > 0 ? ',' : '' ?>
          <a href="/proteomic_reanalysis.php?dataset=<?= h($s['dataset_id']) ?>"><?= h($s['pxd']) ?></a>
          (<?= number_format((int)$s['n_proteins']) ?> protein<?= (int)$s['n_proteins'] == 1 ? '' : 's' ?><?php
          /* the digest is what tells these acquisitions apart, so name it when it
             is not the ordinary trypsin one */
          if (!in_array((int)$s['cnido_enzyme'], array(1, 2), true)) {
              echo ', ' . h(cnido_enzyme_label($s['cnido_enzyme']));
          } ?>)<?php endforeach; ?>.
      </p>
    <?php endif; ?>
  </div>
  <?php
  /* The other half of the answer.  A reader told "nothing was identified" wants
     to know what the people who generated this sample did report, and for these
     six deposits the paper has an answer.  The card carries its own attribution
     and its own accent colour, so it can sit directly under the explanation
     without either being mistaken for the other.  A dataset whose publication
     also reports nothing gets a card saying so rather than no card at all --
     that is a fact about the sample, and it is what stops the empty table above
     from reading as "nobody has looked". */
  $__pubres = cnido_published_for($__cur['dataset_id']);
  if ($__pubres) {
      echo cnido_published_card($__cur['pxd'], $__pubres, (int)$__cur['n_proteins']);
  }
  ?>
  <?php else: ?>

  <div class="pa-sub">How well is each protein supported?
    <span class="hint">&mdash; unique peptides per protein; a protein found through one
    peptide rests on that peptide alone.</span>
  </div>
  <?php
  /* The unit lives in the heading, not in every label: five rows repeating
     "unique peptides" is noise, and the bars are read against each other. */
  $buckets = array(
      array('1',          (int)$st['pep1'], '#94a3b8'),
      array('2',          (int)$st['pep2'], '#64748b'),
      array('3&ndash;4',  (int)$st['pep3'], '#3b82f6'),
      array('5&ndash;9',  (int)$st['pep4'], '#1d4ed8'),
      array('10 or more', (int)$st['pep5'], '#1e3a8a'),
  );
  foreach ($buckets as $b):
      $p = $pctOf($b[1]);
  ?>
  <div class="pa-bar">
    <span class="lab"><?= $b[0] ?></span>
    <span class="track"><span class="fill" style="width:<?= number_format($p, 2) ?>%;background:<?= $b[2] ?>;"></span></span>
    <span class="n"><?= number_format($b[1]) ?></span>
    <span class="pct"><?= number_format($p, 1) ?>%</span>
  </div>
  <?php endforeach; ?>
  <p class="pa-legend">
    <b><?= number_format((int)$st['pep1']) ?></b> of the <?= number_format($nSet) ?> proteins listed here
    (<?= number_format($pctOf($st['pep1']), 1) ?>%) were identified by a single peptide, so their
    identification rests on one spectrum match;&nbsp;the median protein is supported by
    <?php
    /* the bucket the 50th percentile falls in, stated as a bucket rather than a
       made-up median: the underlying values are whole peptide counts and the
       table below shows them one by one. */
    $cum = 0; $med = '10 or more';
    foreach (array(array('1 unique peptide', (int)$st['pep1']),
                   array('2 unique peptides', (int)$st['pep2']),
                   array('3&ndash;4 unique peptides', (int)$st['pep3']),
                   array('5&ndash;9 unique peptides', (int)$st['pep4'])) as $b) {
        $cum += $b[1];
        if ($nSet > 0 && $cum >= $nSet / 2) { $med = $b[0]; break; }
    }
    echo $med;
    ?>. The deepest identification reaches <?= number_format((int)$st['pepmax']) ?> unique peptides
    (<?= number_format((int)$st['psmmax']) ?> PSMs on one protein).
  </p>

  <?php if ($annMatch > 0): ?>
  <div class="pa-sub">What are these proteins?
    <span class="hint">&mdash; the search space was the transcriptome assembly of
    <i><?= h($__annSp) ?></i>, so every identified transcript is annotated by the
    Transcriptome Assembly module.<?php if ($__annForeign): ?>
    <b>These are <?= h($__annSp) ?> transcripts, not <?= h($__cur['species']) ?> ones.</b>
    <?php endif; ?></span>
  </div>
  <?php
  $annKeys = array('uniprot', 'pfam', 'panther', 'interpro', 'go', 'kegg');
  foreach ($annKeys as $k):
      $n = (int)$st['ann_' . $k];
      $p = $pctAnn($n);
  ?>
  <div class="pa-bar">
    <span class="lab"><?= h($PA_ANN[$k][0]) ?></span>
    <span class="track"><span class="fill" style="width:<?= number_format($p, 2) ?>%;background:<?= array('uniprot' => '#1d4ed8', 'pfam' => '#0f766e', 'panther' => '#b45309', 'interpro' => '#7c3aed', 'go' => '#15803d', 'kegg' => '#be123c')[$k] ?>;"></span></span>
    <span class="n"><?= number_format($n) ?></span>
    <span class="pct"><?= number_format($p, 1) ?>%</span>
  </div>
  <?php endforeach; ?>
  <p class="pa-legend">
    <?php /* The two remainders are different things and are counted against
             different denominators: "no hit" is out of the proteins that *are*
             in the assembly ($annMatch, not $nSet -- with contaminants shown
             $nSet is larger), while "not in the assembly" is the rest.  Mixing
             them would call an unresolvable row a protein with no annotation. */ ?>
    <?= number_format($annMatch) ?> of the <?= number_format($nSet) ?> identified proteins are found in the
    transcriptome assembly of <i><?= h($__annSp) ?></i> and carry these annotations there.
    <?php /* The congener surrogate is the one case where the assembly is NOT the
             dataset's own species, and saying just the species name here would
             read as "Actinia fragacea has a transcriptome assembly", which is
             exactly what the warning panel above denies.  Named in full. */ ?>
    <?php if ($__annForeign): ?>
    <b>That is not the species this dataset was sampled from.</b> The search space was the
    transcriptome-derived proteome of the congener <i><?= h($__annSp) ?></i>, so every annotation
    below describes <i><?= h($__annSp) ?></i> transcripts &mdash; the sequences that were matched &mdash;
    and not a gene of <i><?= h($__cur['species']) ?></i>.
    <?php endif; ?>
    Of those <?= number_format($annMatch) ?>,
    <b><?= number_format((int)$st['ann_any']) ?></b> have a hit in at least one of the six sources and
    <b><?= number_format($annMatch - (int)$st['ann_any']) ?></b> have none &mdash; normal for short open
    reading frames, where a predicted protein can be real and still match nothing.
    <?php if ($nSet > $annMatch): ?>
    The other <?= number_format($nSet - $annMatch) ?> shown here do not resolve in that assembly at all,
    so no transcriptome annotation is shown for them.
    <?php endif; ?>
    Annotation is attached to the transcript in the
    <a href="/trans_assembly_species.php?species=<?= h($__annAbbr) ?>">transcriptome assembly of <i><?= h($__annSp) ?></i></a>,
    so it is the same annotation &mdash; and the same six sources &mdash; shown there.
  </p>
  <?php endif; ?>
  <?php endif; /* !$__null */ ?>
</div>
<?php endif; ?>

<?php if ($__cur): ?>
<!-- ================= provenance / parameters ================= -->
<div class="pa-panel">
  <h3>Dataset provenance &amp; search parameters &mdash; <?= h($__cur['pxd']) ?></h3>
  <div class="pa-grid">
    <div class="pa-cell"><b>Species</b><span><i><?= h($__cur['species']) ?></i></span></div>
    <div class="pa-cell"><b>Tissue</b><span><?= h($__cur['tissue']) ?></span></div>
    <div class="pa-cell"><b>PRIDE accession</b><span><?= h($__cur['pxd']) ?></span></div>
    <div class="pa-cell"><b>Status</b><span title="pipeline status: <?= h($__cur['status']) ?>"><?= h(cnido_status_label($__cur['status'])) ?></span></div>
    <div class="pa-cell"><b>Search engine</b><span><?= h($__cur['cnido_engine'] ?? 'Comet 2026.01') ?></span></div>
    <?php /* `proteome_file` is empty for every dataset in this release, so the
             old fallback labelled a congener or transcriptome search space as
             "CnidoSite reference proteome" -- the one claim this cell must not
             make wrongly.  Report `proteome_source`, which is generated from the
             sidecar stage 03 wrote next to the database actually searched. */ ?>
    <div class="pa-cell"><b>Search space</b><span><?= h(
        !empty($__cur['proteome_source']) ? $__cur['proteome_source']
        : (!empty($__cur['proteome_file']) ? $__cur['proteome_file']
        : 'CnidoSite reference proteome')) ?></span></div>
    <div class="pa-cell"><b>Enzyme</b><span><?= h(cnido_enzyme_label($__cur['cnido_enzyme'] ?? 1)) ?></span></div>
    <div class="pa-cell"><b>Missed cleavages</b><span><?= h($__cur['cnido_missed'] ?? '2') ?></span></div>
    <div class="pa-cell"><b>Precursor tolerance</b><span><?= h($__cur['cnido_precursor'] ?? '') ?></span></div>
    <div class="pa-cell"><b>Fragment tolerance</b><span><?= h($__cur['cnido_fragment'] ?? '') ?></span></div>
    <div class="pa-cell"><b>Fixed modification</b><span><?= h($__cur['cnido_fixed'] ?? '') ?></span></div>
    <div class="pa-cell"><b>Variable modification</b><span><?= h($__cur['cnido_variable'] ?? '') ?></span></div>
    <div class="pa-cell"><b>Decoy strategy</b><span><?= h($__cur['cnido_decoy'] ?? 'Comet internal reversed (1:1)') ?></span></div>
    <div class="pa-cell"><b>FDR (PSM)</b><span><?= h($__cur['cnido_fdr_psm'] ?? '0.01') ?></span></div>
    <div class="pa-cell"><b>Quantification</b><span><?= h($__cur['cnido_quant'] ?? 'spectral counting') ?></span></div>
    <?php /* The three result counts (proteins / peptides / PSMs) used to sit here
             as three more cells among the tolerances.  They are what the reader
             came for and they are not parameters, so they now lead the panel
             above as the identification chain. */ ?>
  </div>
  <?php /* Shown here only for datasets that did identify something.  For an empty
           one the panel above already prints the same note next to the explanation,
           and printing it twice would read as two different remarks. */
        $__sn    = trim((string)$__cur['status_note']);
        $__stype = (string)($__cur['proteome_source_type'] ?? '');
        if ($__stype !== 'reference' && stripos($__sn, 'reference proteome') !== false) { $__sn = ''; }
        if ($__sn !== '' && empty($__null)): ?>
    <div style="margin-top:10px;font-size:15px;color:#64748b"><?= h($__sn) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($n_contam > 0 && !$showContam): ?>
<div class="pa-contam">
  <?php /* &nbsp; 原先夹在词干和复数 s 之间，渲染成 "15 cRAP contaminant protein s"。
           改成整词判断。 */ ?>
  <b><?= $n_contam ?></b> cRAP contaminant <?= $n_contam == 1 ? 'protein' : 'proteins' ?>
  (keratins, trypsin, serum albumin &hellip;) were identified in this dataset and are
  <b>excluded</b> from the table below. Tick &ldquo;include cRAP contaminants&rdquo; to display them.
</div>
<?php endif; ?>

<!-- ================= results table ================= -->
<?php
/* The six annotation columns are added only when the identified proteins really
   are in the transcriptome assembly that was searched (see $__annAbbr / $__annSp
   -- for the two congener datasets that is the surrogate's assembly, not the
   deposited species').  A column that says "&ndash;" for every row would be a
   promise the data cannot keep, so the whole block is conditional rather than the
   cells. */
$__annOn = (isset($annMatch) && $annMatch > 0);

/* The Description column carries the searched proteome's own description of the
   protein.  For a transcriptome search space that is empty on every real row --
   a de novo predicted protein has no reference description -- and the only rows
   that ever have one are the cRAP contaminants, which are hidden by default.
   So the column is drawn only when something in the current selection would
   fill it: an empty column is width taken from the annotations the reader came
   for.  Tick "include cRAP contaminants" on such a dataset and it comes back,
   carrying their names.

   The test is not conditioned on $__annOn, because the two are independent:
   a reference-proteome dataset fills Description and has no annotation columns
   (PXD045585 and the other EDIAP sets), while a transcriptome dataset has the
   annotation columns and no descriptions at all.  Conditioning on $__annOn would
   have drawn the column -- 30% of the width, "&ndash;" on every row -- on the
   datasets this paragraph is about.  n_desc is part of the base SELECT, so it is
   there to be read whether or not the annotation columns were added. */
$__descOn = true;
if ($__stat && array_key_exists('n_desc', $__stat)) { $__descOn = ((int)$__stat['n_desc'] > 0); }
/* The seven data columns take 49.5%; what is left is split between the six
   annotation columns and (only when it has content) Description. */
$__annW  = $__descOn ? '6.4' : '8.4';    /* per annotation column */
$__nCols = 7 + ($__annOn ? 6 : 0) + ($__descOn ? 1 : 0);

/* ---- search box ---------------------------------------------------------
   Sits directly above the table rather than inside the dataset panel above: a
   reader who is looking at the rows wants to narrow *these* rows, and the panel
   at the top of the page is about choosing a dataset, a different question.

   A GET form replaces the whole query string, so dataset / species / contam /
   per-page / sort all ride along as hidden fields -- otherwise searching would
   silently widen the page from one dataset's proteins to every dataset's. */
$__selfPa = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
?>
<?php if ($__hasSchema): ?>
<form class="pa-search" method="get" action="<?= $__selfPa ?>">
  <input type="hidden" name="dataset" value="<?= h($dataset) ?>" />
  <input type="hidden" name="species" value="<?= h($species) ?>" />
  <input type="hidden" name="contam" value="<?= $showContam ? '1' : '0' ?>" />
  <input type="hidden" name="per_page" value="<?= (int)$per_page ?>" />
  <?php if ($__sortQs !== ''): ?>
  <input type="hidden" name="sort" value="<?= h($__sort) ?>" />
  <input type="hidden" name="dir" value="<?= h($__dir) ?>" />
  <?php endif; ?>
  <label for="paQ">Search proteins</label>
  <input type="text" id="paQ" name="q" value="<?= h($q) ?>"
         placeholder="<?= $__annJoin !== ''
             ? 'gene / protein id, UniProt, Pfam, Panther, InterPro, GO, KEGG, description&hellip;'
             : 'gene / protein id, description&hellip;' ?>" />
  <button type="submit">Search</button>
  <?php if ($q !== ''): ?>
  <a class="pa-clear" href="<?= $__selfPa ?>?<?= h($__qsNoQ) ?>&amp;per_page=<?= (int)$per_page ?>">Clear</a>
  <?php endif; ?>
</form>

<?php if ($q !== '' && $total_records > 0): ?>
<div class="pa-hit">
  <?= number_format($total_records) ?> protein(s) in this view match
  &ldquo;<b><?= h($q) ?></b>&rdquo;.
</div>
<?php elseif ($q !== ''): ?>
<!-- Searched and found nothing.  Say which of the two things happened: the view
     holds no proteins at all, or it holds some and none of them match. -->
<div class="pa-nores">
  <h3>Nothing matches &ldquo;<?= h($q) ?>&rdquo;</h3>
  <?php if ($__baseTotal !== null && $__baseTotal == 0): ?>
  <p>There are no proteins to search in this view<?= $dataset !== '' ? '' : ' &mdash; no dataset is selected' ?>,
     so the table below is empty whatever you type.</p>
  <?php else: ?>
  <p><?php if ($__baseTotal !== null): ?>None of the <?= number_format($__baseTotal) ?>
     protein(s) in this view contains<?php else: ?>No protein in this view contains<?php endif; ?>
     that term. Searchable are the gene and protein ids<?php if ($__annJoin !== ''): ?>,
     every annotation the table shows (UniProt, Pfam, Panther, InterPro, GO, KEGG)<?php endif; ?>
     and the description &mdash; not the numbers (PSMs, unique peptides, coverage,
     length, q-value), which are for sorting rather than searching.</p>
  <p><a class="pa-clear" href="<?= $__selfPa ?>?<?= h($__qsNoQ) ?>&amp;per_page=<?= (int)$per_page ?>">Clear the search</a></p>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; /* $__hasSchema */ ?>

<div class="table-container">
<table class="gridtable" id="myTable">
  <tr>
    <?php /* Widths in the annotated layout are set by the identifiers, which are
             the longest thing in the row: Trinity gene ids run to 22 characters
             and the ORF ids to 28 (measured over all seven transcriptome
             datasets), so 11% / 13% of the 1590px table fits both on one line.
             Splitting an identifier across two lines at an arbitrary character
             reads as if the id ended there. */ ?>
    <?php /* gridtable carries white-space:pre-wrap, so each <th> is kept on one line:
             a newline between the tags would render as a real one and push the
             heading off centre.
             Every column gets a sort link.  The heading's own explanatory title
             ("Peptide-spectrum matches" ...) stays where it was, on the <th>; the
             anchor's title takes precedence while the pointer is over the text,
             so the explanation is readable over the cell's padding rather than
             over the label.  Keeping the reader's explanation AND the click
             instruction would mean teaching cnido_sort_link a second title, which
             is a change to a helper ten pages share. */ ?>
    <th style="width:<?= $__annOn ? '11' : '14' ?>%"><?= cnido_sort_link('gene', 'Gene', $__sort, $__dir, $qs) ?></th>
    <th style="width:<?= $__annOn ? '13' : '12' ?>%"><?= cnido_sort_link('protein', 'Protein', $__sort, $__dir, $qs) ?></th>
    <th style="width:<?= $__annOn ? '4.5' : '8' ?>%" title="Peptide-spectrum matches"><?= cnido_sort_link('psms', 'PSMs', $__sort, $__dir, $qs) ?></th>
    <th style="width:<?= $__annOn ? '6.5' : '10' ?>%"><?= cnido_sort_link('unique', 'Unique peptides', $__sort, $__dir, $qs) ?></th>
    <th style="width:<?= $__annOn ? '5' : '8' ?>%" title="Share of the protein sequence covered by the identified peptides"><?= cnido_sort_link('cov', 'Coverage', $__sort, $__dir, $qs) ?></th>
    <th style="width:<?= $__annOn ? '4.5' : '8' ?>%" title="Protein length in amino acids"><?= cnido_sort_link('len', 'Length', $__sort, $__dir, $qs) ?></th>
    <th style="width:<?= $__annOn ? '5' : '10' ?>%" title="Best Percolator q-value of the peptides behind this protein"><?= cnido_sort_link('q', 'q-value', $__sort, $__dir, $qs) ?></th>
    <?php if ($__annOn): foreach (array('uniprot', 'pfam', 'panther', 'interpro', 'go', 'kegg') as $k): ?>
      <th style="width:<?= $__annW ?>%" title="<?= h($PA_ANN[$k][0]) ?> annotation of this transcript, from the Transcriptome Assembly module: the identifier on the first line, what it means on the second"><?= cnido_sort_link($k, h($PA_ANN[$k][0]), $__sort, $__dir, $qs) ?></th>
    <?php endforeach; endif; ?>
    <?php if ($__descOn): ?><th style="width:<?= $__annOn ? '12' : '30' ?>%"><?= cnido_sort_link('desc', 'Description', $__sort, $__dir, $qs) ?></th><?php endif; ?>
  </tr>
  <?php if (empty($rows)): ?>
    <tr><td colspan="<?= $__nCols ?>" style="padding:16px;text-align:left">
      <?php if (!empty($__null)): ?>
        <?php /* "No proteins match the current selection" was the wrong sentence
                 here: nothing is selected against, the dataset itself has no
                 identification to list.  The reason is in the panel above. */ ?>
        This dataset contains no identification at the 1% false-discovery threshold, so there is
        nothing to list. See the panel above for why.
      <?php else: ?>
        No proteins match the current selection.
        <?php if (!$showContam && $n_contam > 0): ?>
          (<?= $n_contam ?> contaminant protein<?= $n_contam == 1 ? '' : 's' ?> are hidden.)
        <?php endif; ?>
      <?php endif; ?>
    </td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr align="center">
      <td class="gene">
        <?php if (!empty($r['gene_id']) && (int)$r['links_gene'] === 1): ?>
          <?php /* fall back to the selected dataset's species: rows always carry
                   d.species from the JOIN, but the gene link must not break if a
                   future query is narrowed and drops that column. */ ?>
          <a href="/gene_detail.php?gene=<?= urlencode($r['gene_id']) ?>&species=<?= urlencode(isset($r['species']) ? $r['species'] : (isset($__cur['species']) ? $__cur['species'] : '')) ?>"
             title="Open the CnidoSite gene page for <?= h($r['gene_id']) ?>"><?= h($r['gene_id']) ?></a>
        <?php elseif (!empty($r['gene_id'])): ?>
          <?php /* Trinity contig: real, but no gene page exists for it.  Plain
                   text at the table's own size -- "table字体显示一致" -- with the
                   tooltip carrying the explanation the colour used to.  The tooltip
                   points at the columns to the right only when they are on the page;
                   it used to say so unconditionally, which was false on every
                   dataset whose assembly the site does not hold. */ ?>
          <span class="contig" title="Transcript-sequence ID from the <?= $__annOn ? h('de novo assembly of ' . $__annSp) : 'de novo assembly' ?>; it has no CnidoSite gene page<?= $__annOn ? ', but its annotation is in the columns to the right' : '' ?>"><?= h($r['gene_id']) ?></span>
        <?php else: ?>&ndash;<?php endif; ?>
      </td>
      <td class="prot">
        <?php /* The identified protein is an ORF of this assembly, and the module
                 that holds it is keyed by exactly this id, so the row can hand the
                 reader over to it.  Shown only when the annotation columns are
                 there, i.e. when the assembly really resolved -- on a reference
                 proteome there is no transcript page to open.  `q` on the target
                 page is an index-backed identifier lookup (ix_abbr_id, prefix
                 LIKE), which is the only handle the module exposes -- it has no
                 per-row deep link.  A prefix can in principle also catch a
                 sibling isoform (`...p1` matching `...p10`), so the handover was
                 measured rather than assumed: 30 rows across 5 datasets, every one
                 landing on a page holding exactly that id and nothing else, and on
                 the two largest sets the candidate count equals the row count
                 (4,572 for 4,572 proteins on PXD060643, 2,239 for 2,239 on
                 PXD022770).  Should a sibling ever appear it is the same
                 transcript's other ORF, so the landing page stays correct. */ ?>
        <?php if ($__annOn): ?>
          <a href="/trans_assembly_species.php?species=<?= h($__annAbbr) ?>&amp;q=<?= urlencode($r['protein_id']) ?>"
             title="Open this transcript in the <?= h($__annSp) ?> transcriptome assembly"><?= h($r['protein_id']) ?></a>
        <?php else: ?><?= h($r['protein_id']) ?><?php endif; ?>
      </td>
      <td class="num"><?= (int)$r['n_psms'] ?></td>
      <td class="num"><?= (int)$r['n_unique_peptides'] ?></td>
      <td class="num" title="<?= h(pa_cov($r['coverage_pct'])) ?>% of the sequence"><?= h(pa_cov($r['coverage_pct'])) ?></td>
      <td class="num"><?= (int)$r['length'] ?></td>
      <td class="num" title="<?= h($r['best_q']) ?>"><?= h(pa_qval($r['best_q'])) ?></td>
      <?php if ($__annOn): foreach (array('uniprot', 'pfam', 'panther', 'interpro', 'go', 'kegg') as $k): ?>
        <td class="ann"><?= pa_ann_cell($k,
              isset($r[$k]) ? $r[$k] : '',
              isset($r[$k . '_desc']) ? $r[$k . '_desc'] : '',
              isset($__dict[$k]) ? $__dict[$k] : array()) ?></td>
      <?php endforeach; endif; ?>
      <?php if ($__descOn): ?><td class="tal"><?= h($r['description']) ?></td><?php endif; ?>
    </tr>
  <?php endforeach; endif; ?>
</table>
</div>

<?php if ($__annOn): ?>
<p class="pa-ann-key">
  <b>Annotation columns</b> come from the
  <a href="/trans_assembly_species.php?species=<?= h($__annAbbr) ?>">transcriptome assembly of
  <i><?= h($__annSp) ?></i></a>, matched on the exact transcript identified here
  (<?= number_format($annMatch) ?> of <?= number_format((int)$total_records) ?> rows).
  <?php /* Congener surrogate: the assembly is a different species from the one the
           sample came from, and a reader who does not notice will read every
           accession below as a statement about the deposited species.  Said
           here, in the sentence that names the assembly, rather than in a
           footnote the eye skips. */ ?>
  <?php if ($__annForeign): ?>
  <b>Note the species:</b> <i><?= h($__annSp) ?></i> is not <i><?= h($__cur['species']) ?></i> &mdash;
  this dataset's own species has no assembly, so it was searched against the congener's, and these
  annotations describe the <i><?= h($__annSp) ?></i> transcripts that were matched.
  <?php endif; ?>
  Each cell shows the identifier on the first line &mdash; it links to that entry's page &mdash; and
  what the identifier means on the second; <b>+N</b> counts the further hits, and hovering any cell
  lists all of them with their descriptions.
  <?php /* GO and KEGG have no description column of their own in
           trans_assembly; their names are resolved from the module's two
           dictionaries, which is worth saying: the identifier alone is not
           self-explanatory and a reader should know where the name came from. */ ?>
  Names for the <b>GO</b> and <b>KEGG</b> identifiers come from the transcriptome module's own
  GO and KO term dictionaries.
  <span style="color:#1d4ed8">UniProt</span>
  <span style="color:#0f766e">Pfam</span>
  <span style="color:#b45309">PANTHER</span>
  <?php /* 色标必须和上面那条堆叠条同色：其余五个（#1d4ed8/#0f766e/#b45309/#15803d/#be123c）
           都逐字对得上，只有 InterPro 这里原来写 #6d28d9 —— 那是本页卡片与链接的强调
           紫，不是堆叠条里的 #7c3aed，也就是 trans_assembly.php 用的那个。 */ ?>
  <span style="color:#7c3aed">InterPro</span>
  <span style="color:#15803d">GO</span>
  <span style="color:#be123c">KEGG</span>
</p>
<?php endif; ?>

<!-- ================= pagination (now carries every parameter) ============ -->
<div class="pagination-container">
  <div class="pagination-info">
    <div class="pa-total">
      <?php /* "Total protein records: 0" next to an empty table is a claim about
               the dataset, and with a search active it is a false one: the
               dataset holds 5,013 proteins and none of them matched.  Name the
               number for what it is while a search is on. */ ?>
      <?= $q !== '' ? 'Protein records matching the search:' : 'Total protein records:' ?>
      <?= number_format((int)$total_records) ?>
      <?php if ($n_contam > 0): ?>
        <span style="font-weight:400;opacity:.85">&nbsp;(+<?= $n_contam ?> contaminant<?= $n_contam == 1 ? '' : 's' ?> hidden)</span>
      <?php endif; ?>
    </div>
    <div class="per-page-selector">
      <span>Show:</span>
      <select onchange="window.location.href='/proteomic_reanalysis.php?<?= h($qs) ?>&page=1&per_page='+this.value">
        <?php foreach (array(25, 50, 100, 200) as $o): ?>
          <option value="<?= $o ?>"<?= ($o == $per_page) ? ' selected="selected"' : '' ?>><?= $o ?></option>
        <?php endforeach; ?>
      </select>
      <span>proteins per page</span>
    </div>
  </div>

  <div class="pa-pager">
    <?php
    $base = '/proteomic_reanalysis.php?' . h($qs) . '&per_page=' . $per_page . '&page=';
    $sp = max(1, $page - 3);
    $ep = min($total_pages, $page + 3);
    if ($total_pages > 1):
    ?>
      <a href="<?= $base ?>1">&laquo; First</a>
      <a href="<?= $base . max(1, $page - 1) ?>">&lsaquo; Previous</a>
      <?php if ($sp > 1): ?>
        <a href="<?= $base ?>1">1</a><?php if ($sp > 2): ?><span>&hellip;</span><?php endif; ?>
      <?php endif; ?>
      <?php for ($i = $sp; $i <= $ep; $i++): ?>
        <?php if ($i == $page): ?><span class="on"><?= $i ?></span>
        <?php else: ?><a href="<?= $base . $i ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($ep < $total_pages): ?>
        <?php if ($ep < $total_pages - 1): ?><span>&hellip;</span><?php endif; ?>
        <a href="<?= $base . $total_pages ?>"><?= $total_pages ?></a>
      <?php endif; ?>
      <a href="<?= $base . min($total_pages, $page + 1) ?>">Next &rsaquo;</a>
      <a href="<?= $base . $total_pages ?>">Last &raquo;</a>
      <span style="border:none">of <?= $total_pages ?> pages</span>
    <?php else: ?>
      <span style="border:none">1 page</span>
    <?php endif; ?>
  </div>
</div>

<?php endif; /* hasSchema */ ?>

</div></div></div>

<?php
    include "./Webpage_components.php";
    print $footer;
?>
</body>
</html>
