<?php
/* =========================================================================
 * CnidoSite — Core Orthologs (CCO)
 *
 * A Cnidaria-specific core single-copy ortholog resource derived from the same
 * OrthoFinder run the gene-family pages use, plus the species x gene
 * presence/absence matrix.  Where BUSCO answers "how complete is this genome
 * against a curated lineage", this answers "which genes are actually present,
 * and single-copy, across these genomes", and hands over the matrix so the
 * answer can be re-thresholded.
 *
 * Tiers are all scored on the 60 cnidarian genomes with BUSCO cnidaria_odb12
 * complete >= 90%.  A tier of "core" means the orthogroup is single-copy in
 * >= 80% of those 60.  See work/core/README.md for why those numbers.
 *
 * Tables: core_og, core_species, core_member (long-format matrix).
 * ========================================================================= */

/* 连接参数不再写在本文件里：从 includes/state.php 的 cnido_conn() 取。
   本站没有公共连库函数，连接参数曾被复制到各个页面（本文件就是其中之一），
   口令因此散落在源码各处；cnido_conn() 是唯一的一份。
   注意 core/ 是子目录，路径要带 ../
   2026-09-29：原来这里写的是 new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria')。 */
require_once __DIR__ . '/../includes/state.php';
$conn = cnido_conn();
if ($conn->connect_error) { die('Database connection failed.'); }
mysqli_set_charset($conn, 'utf8mb4');

function cc_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* The consensus annotation text for an orthogroup, or '' when there is nothing
 * worth showing.
 *
 * Description before name.  On this run `best_name` is populated for the
 * Pfam-sourced rows only and holds the internal Pfam identifier -- "UQ_con",
 * "Ank_2", "7tm_1" -- whereas `best_desc` holds the text a reader wants ("SH3
 * domain", "IMPORTIN BETA").  Preferring the name (as the orthogroup table used
 * to) showed the worse of the two.
 *
 * PANTHER labels 12 families here with the literal string "-" and no other
 * text.  That is an absent annotation, not a one-character one: a bare hyphen
 * in a column head reads as a missing value, so it is normalised to '' and the
 * caller falls back to the orthogroup id like any other unannotated family.
 */
function cc_anno_text($o) {
    $s = trim($o['best_desc']);
    if ($s === '') { $s = trim($o['best_name']); }
    $s = preg_replace('/\s+/', ' ', $s);
    if (strlen(preg_replace('/[^A-Za-z0-9]/', '', $s)) < 2) { return ''; }
    return $s;
}

/* A short form of that text for a matrix column head: cut on a word boundary
 * near $max, but never past halfway back, so a description whose first word is
 * very long still yields something.  Returns '' when there is no annotation, and
 * the caller shows the orthogroup id instead.
 *
 * No comma splitting.  It looked like a free win -- PANTHER puts ", MITOCHONDRIAL"
 * and ", MEMBER 3, LIKE-RELATED" after the useful part -- but the same character
 * is also a chemical locant, where the *tail* carries the meaning and the head
 * is a fragment: it turned "BETA-1,2-N-ACETYLGLUCOSAMINYLTRANSFERASE II" into
 * "BETA-1" and "RNA 2',3'-CYCLIC PHOSPHODIESTERASE" into "RNA 2'".  Plain
 * truncation loses the tail of those too, but it loses it visibly.
 */
function cc_short($o, $max = 20) {
    $s = cc_anno_text($o);
    if ($s === '') { return ''; }
    if (strlen($s) <= $max) { return $s; }
    $cut = substr($s, 0, $max);
    $sp  = strrpos($cut, ' ');
    if ($sp !== false && $sp >= (int)($max * 0.5)) { $cut = substr($cut, 0, $sp); }
    return rtrim($cut, " ,;:-") . "\xe2\x80\xa6";
}

/* The full source string for a label's tooltip: "OG0003068 — TEXT [panther:PTHR10527]". */
function cc_tooltip($og, $o) {
    $t = $og;
    $d = cc_anno_text($o);
    if ($d !== '') { $t .= ' — ' . $d; }
    if (trim($o['best_term']) !== '') {
        $t .= ' [' . trim($o['best_source']) . ':' . trim($o['best_term']) . ']';
    }
    return $t;
}

$TIER_LABEL = array(
    'strict'   => 'Strict',
    'core'     => 'Core',
    'extended' => 'Extended',
);
$TIER_CUT = array('strict' => 90, 'core' => 80, 'extended' => 70);

$tier = isset($_GET['tier']) && isset($TIER_LABEL[$_GET['tier']]) ? $_GET['tier'] : 'core';
$q    = isset($_GET['q']) ? trim($_GET['q']) : '';

/* Which tab is being shown.  Parsed here, before the queries, rather than down
   beside the markup where it used to live: each tab needs only some of what
   follows, and the queries below are not free -- the Overview's BUSCO
   occupancy recompute walks 250k busco rows and the matrix build reads 30k
   core_member rows, so every tab used to pay for both (measured 2026-09-30:
   /core/ took 2.0 s, of which 1.2 s was the BUSCO recompute alone, on tabs
   that never print it). */
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

/* ---- headline numbers --------------------------------------------------- */
$n_species = $n_hq = $n_cnid = 0;
$r = mysqli_query($conn, "SELECT COUNT(*) n, SUM(busco90) hq, SUM(cnidarian) cn
                          FROM core_species");
if ($r && ($x = mysqli_fetch_assoc($r))) {
    $n_species = (int)$x['n']; $n_hq = (int)$x['hq']; $n_cnid = (int)$x['cn'];
}
$tier_counts = array();
$r = mysqli_query($conn, "SELECT tiers, COUNT(*) n FROM core_og GROUP BY tiers");
while ($r && ($x = mysqli_fetch_assoc($r))) {
    foreach (explode(',', $x['tiers']) as $t) {
        if ($t !== '') { $tier_counts[$t] = ($tier_counts[$t] ?? 0) + (int)$x['n']; }
    }
}
$n_members = 0;
$r = mysqli_query($conn, "SELECT COUNT(*) n FROM core_member");
if ($r && ($x = mysqli_fetch_assoc($r))) { $n_members = (int)$x['n']; }

/* BUSCO's own single-copy occupancy over the same 60 genomes, computed from
   the site's busco/busco_summary tables.  Recomputed here rather than stored
   so it can never drift from the numbers the BUSCO pages show.

   2026-09-29 修正：这里原本 JOIN busco_summary WHERE pct_complete>=90，那是
   **61** 个物种 —— 多出来的 PMULT 不在 core_species 里（busco90=0，本资源完全
   不含它），而下面 SUM 的阈值又是按 $n_hq=60 算的。分母 61、阈值 60，于是
   "100% 那一行"数出 1，页面就跟着写了 "only one ... in all 60"，而按这 60 个
   基因组老实算，答案是 0。改成直接 JOIN core_species.busco90。 */
$busco_row = array();
if ($tab === 'overview') {
    $r = mysqli_query($conn, "
    SELECT
      COUNT(*)                                                        AS n_groups,
      SUM(n_complete >= 1.00 * $n_hq)                                 AS ge100,
      SUM(n_complete >= 0.95 * $n_hq)                                 AS ge95,
      SUM(n_complete >= 0.90 * $n_hq)                                 AS ge90,
      SUM(n_complete >= 0.80 * $n_hq)                                 AS ge80
    FROM (
      SELECT b.BUSCO_ID, SUM(b.Status='Complete') AS n_complete
      FROM busco b JOIN core_species cs ON cs.abbr1 = b.abbr COLLATE utf8mb4_0900_ai_ci
      WHERE cs.busco90 = 1
      GROUP BY b.BUSCO_ID
    ) t");
    if ($r && ($x = mysqli_fetch_assoc($r))) { $busco_row = $x; }
}

/* 2026-09-29：这一段以前把重复率写死成 "20-43% duplicated BUSCOs"，而按任何
   一种分母都不是这个区间 —— 这 60 个基因组是 0.6-57.2%（均值 24.2%），全部 148
   个刺胞动物是 0.0-57.2%（均值 14.9%），98 个 Hexacorallia 是 0.3-50.4%（均值
   15.9%）；最高的一位还不是珊瑚而是水螅 *Hydractinia echinata*（57.2%）。照本页
   "recomputed here rather than stored" 的原则改成现算，免得再漂。 */
$busco_dup = array('mn' => null, 'mx' => null, 'av' => null);
if ($tab === 'overview') {
    $r = mysqli_query($conn, "
    SELECT MIN(pct_duplicated) AS mn, MAX(pct_duplicated) AS mx,
           AVG(pct_duplicated) AS av
    FROM core_species WHERE busco90 = 1");
    if ($r && ($x = mysqli_fetch_assoc($r))) { $busco_dup = $x; }
}

/* ---- orthogroup rows ---------------------------------------------------- */
$where = "tiers LIKE '%" . mysqli_real_escape_string($conn, $tier) . "%'";
if ($q !== '') {
    $qq = mysqli_real_escape_string($conn, $q);
    $where .= " AND (og LIKE '%$qq%' OR best_term LIKE '%$qq%'
              OR best_name LIKE '%$qq%' OR best_desc LIKE '%$qq%')";
}
$ogs = array();
if ($tab === 'ogs') {
    $r = mysqli_query($conn, "SELECT * FROM core_og WHERE $where
                          ORDER BY hq90_sc DESC, hq90_single DESC, og LIMIT 4000");
    while ($r && ($x = mysqli_fetch_assoc($r))) { $ogs[] = $x; }
}

/* ---- species ------------------------------------------------------------ */
/* The matrix and the species table are the only two readers; the Overview and
   Download tabs print neither. */
$species = array();
if ($tab === 'matrix' || $tab === 'species') {
    $r = mysqli_query($conn, "SELECT * FROM core_species
                          ORDER BY (class=''), class, family, genus, latin");
    while ($r && ($x = mysqli_fetch_assoc($r))) { $species[] = $x; }
}

/* ---- presence/absence matrix for the selected tier ----------------------
   Encoded as one digit per orthogroup per species, in the same order as
   $matrix_ogs / $species, so the grid can be built in the browser without
   155k table cells crossing the wire. */
$matrix_ogs = array();
/* Label and tooltip travel with the id so a column head can name the gene
   family instead of printing "OG0003068" 1,017 times.  Sent as [short, full,
   accession] per orthogroup; the id itself is the key and needs no repeating.
   The accession (PTHR10527, PF00018, GO:0005515 -- never longer than 10
   characters) is the third element because a slanted head on a 1,000-column
   tier shows the stable identifier rather than a description clipped to fit. */
$matrix_meta = array();
$matrix = array();
if ($tab === 'matrix') {
    $r = mysqli_query($conn, "SELECT og, best_source, best_term, best_name, best_desc
                          FROM core_og WHERE $where
                          ORDER BY hq90_sc DESC, hq90_single DESC, og LIMIT 2000");
    while ($r && ($x = mysqli_fetch_assoc($r))) {
        $matrix_ogs[] = $x['og'];
        $matrix_meta[$x['og']] = array(cc_short($x), cc_tooltip($x['og'], $x),
                                       trim($x['best_term']));
    }
    if ($matrix_ogs) {
        $in = "'" . implode("','", array_map(
            function ($s) use ($conn) { return mysqli_real_escape_string($conn, $s); },
            $matrix_ogs)) . "'";
        $by_og = array();
        $r = mysqli_query($conn, "SELECT og, abbr, n_copies FROM core_member
                              WHERE og IN ($in)");
        while ($r && ($x = mysqli_fetch_assoc($r))) {
            $by_og[$x['og']][$x['abbr']] = (int)$x['n_copies'];
        }
        foreach ($species as $s) {
            $row = '';
            foreach ($matrix_ogs as $og) {
                $c = $by_og[$og][$s['abbr1']] ?? 0;
                $row .= $c > 9 ? '9' : (string)$c;
            }
            $matrix[$s['abbr1']] = $row;
        }
    }
}

/* Supermatrix dimensions, written by pack_downloads.sh next to the archives so
   the page never states a size that has drifted from the file. */
$dims = array();
$dims_file = '/var/www/html/CnidoSite/core/download/dimensions.json';
if (is_readable($dims_file)) {
    $dims = json_decode(file_get_contents($dims_file), true);
    if (!is_array($dims)) { $dims = array(); }
}

/* $tab is parsed up beside $tier, before the queries -- see the note there. */
$TABS = array('overview' => 'Overview', 'ogs' => 'Core orthogroups',
              'matrix' => 'Presence / copy number', 'species' => 'Species',
              'download' => 'Download', 'methods' => 'Methods');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script src="/js/rwd-tables.js" defer></script>
<meta charset="utf-8">
<title>Core Orthologs - CnidoSite</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<style>
/* House style.  Same vocabulary as includes/coverage_matrix_view.php (.cm-*),
   which is the site's own coverage matrix: flat surfaces, 1px #e2e8f0 borders,
   10px radii, #f1f5f9 table headers, #2563eb accent, no gradients, no drop
   shadows.  Everything below sits inside the standard
   #tempatemo_content_wrapper > #templatemo_content > #column shell, so the page
   wears the same chrome as busco.php / genefamily.php / phylotree. */
.cc-stats{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 6px}
.cc-stat{display:inline-block;padding:6px 14px;border-radius:12px;background:#eef2f7;color:#334155;font-size:13px}
.cc-stat b{color:#1d4ed8;font-size:15px;margin-right:5px;font-variant-numeric:tabular-nums}
.cc-tabs{display:flex;flex-wrap:wrap;gap:4px;border-bottom:1px solid #e2e8f0;margin:18px 0}
.cc-tabs a{padding:9px 16px;font-size:14px;font-weight:600;color:#475569;text-decoration:none;border-radius:8px 8px 0 0;border:1px solid transparent;border-bottom:none}
.cc-tabs a:hover{background:#f1f5f9;color:#1d4ed8;text-decoration:none}
.cc-tabs a.on{background:#fff;border-color:#e2e8f0;border-bottom:1px solid #fff;color:#2563eb;margin-bottom:-1px}
.cc-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;margin-bottom:16px}
.cc-card h2{margin:0 0 12px;font-size:18px;color:#1e293b;padding-bottom:8px;border-bottom:1px solid #e2e8f0;font-weight:700}
.cc-card h3{margin:18px 0 10px;font-size:16px;color:#334155;font-weight:700}
.cc-card p,.cc-card li{font-size:16px;line-height:1.75;color:#334155}
table.cc{border-collapse:collapse;width:100%;font-size:13.5px}
table.cc th{background:#f1f5f9;text-align:left;padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#334155;font-weight:600;white-space:nowrap;cursor:pointer;user-select:none}
table.cc th.nos{cursor:default}
table.cc td{padding:7px 10px;border-bottom:1px solid #eef2f7;vertical-align:top}
table.cc tbody tr:nth-child(even) td{background:#fcfdff}
table.cc tr:hover td{background:#f1f5f9}
table.cc a{color:#1d4ed8;text-decoration:none}
table.cc a:hover{text-decoration:underline}
.cc-num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.cc-tier{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;letter-spacing:.3px}
.t-strict{background:#dcfce7;color:#166534}
.t-core{background:#dbeafe;color:#1e40af}
.t-extended{background:#fef3c7;color:#92400e}
.cc-bar{display:inline-block;height:9px;border-radius:5px;background:#2563eb;vertical-align:middle;min-width:2px}
.cc-bar.wrap{background:#e2e8f0;width:90px;display:inline-block}
.cc-filter{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;display:flex;flex-wrap:wrap;gap:14px;align-items:center;margin-bottom:14px}
.cc-filter input[type=text]{padding:7px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;background:#fff;min-width:240px}
.cc-filter label{font-size:13px;color:#475569;font-weight:600;white-space:nowrap}
.cc-btn{display:inline-block;padding:7px 14px;border-radius:7px;background:#2563eb;color:#fff !important;font-size:13px;font-weight:600;text-decoration:none;border:1px solid #2563eb;cursor:pointer}
.cc-btn:hover{background:#1d4ed8;border-color:#1d4ed8;text-decoration:none !important}
.cc-btn.alt{background:#fff;border-color:#cbd5e1;color:#475569 !important}
.cc-btn.alt:hover{background:#f1f5f9;border-color:#94a3b8}
.cc-note{background:#f8fafc;border-left:3px solid #9ca3af;padding:12px 16px;border-radius:0 8px 8px 0;font-size:16px;line-height:1.7;color:#1f2937;margin:14px 0}
.cc-note.warn{background:#fffbeb;border-left-color:#f59e0b;color:#78350f}
.cc-scroll{overflow:auto;max-height:72vh;border:1px solid #e2e8f0;border-radius:10px;background:#fff}
/* The matrix is the one table that is deliberately not height-capped.  It is the
   page's main deliverable and the strict tier alone is 153 rows, so capping it
   to an inner box showed a third of a tier and made the resource look truncated.
   It renders in full and the page scrolls.  Only the horizontal axis stays a
   scroll container, which is what keeps the species column pinned while panning
   across genes.
   Two declarations below are load-bearing, and the 2026-09-24 fix was exactly
   these two.  Both were missing and together they made the grid unreadable:

     table-layout:fixed   the gene labels are positioned absolutely (that is what
                          lets 1,017 of them share one header row), so they add no
                          intrinsic width to their column.  Under the default
                          auto layout the table therefore never overflowed: it
                          shrank 1,017 columns into the 1,546px container, about
                          1.2px each, and the matrix drew as a barcode with every
                          label piled onto its neighbour as a black smear.  The
                          colgroup widths -- which the code below computes
                          carefully -- were silently ignored.  With fixed they are
                          honoured, the table overflows, and the pan has something
                          to pan.
     line-height          the page inherits line-height:1.7em from the site
                          stylesheet, which computes to 27.2px.  Inherited as a
                          length it survived the 10px font and made every row 32px
                          tall instead of 14, so 153 rows came to 4,900px and the
                          rows read as loose stripes rather than one grid. */
.cc-scroll-mx{overflow-x:auto;overflow-y:hidden;border:1px solid #e2e8f0;border-radius:10px;background:#fff}
table.mx{table-layout:fixed;border-collapse:separate;border-spacing:0;font-size:10px;line-height:normal}
table.mx th{position:sticky;top:0;background:#f1f5f9;font-weight:600;color:#475569;white-space:nowrap;z-index:2;border-bottom:1px solid #e2e8f0;line-height:normal}
/* The corner is sticky on both axes: it carries the row-axis caption, and it
   has to stay put while the reader pans 1,000 columns to the right. */
table.mx th.nos{position:sticky;top:0;left:0;z-index:3;background:#f1f5f9;color:#64748b;font-size:10px;font-weight:700;text-align:left;padding:0 8px 0 6px;vertical-align:bottom}
table.mx th.nos span{display:block;padding-bottom:7px}
/* The corner cell carries the key to both axes; it is 281px wide, so this has to
   stay small and wrap inside the sticky cell rather than spill onto the grid. */
table.mx th.nos em{display:block;font-style:normal;font-weight:400;font-size:9.5px;line-height:1.4;color:#64748b;margin-bottom:5px;white-space:normal;max-width:265px}
table.mx th.nos em b{color:#166534}
table.mx td{padding:0;height:13px;line-height:13px;border-right:1px solid #fff;border-bottom:1px solid #fff;text-align:center;font-size:8px;color:#fff}
table.mx td.m0{background:#f1f5f9}
table.mx td.m1{background:#2563eb}
table.mx td.m2{background:#f59e0b}
table.mx td.m3{background:#dc2626}
table.mx td.sp{position:sticky;left:0;background:#fff;text-align:left;padding:0 8px 0 6px;font-size:11px;color:#1e293b;white-space:nowrap;z-index:1;border-right:1px solid #e2e8f0;overflow:hidden}
table.mx tr:hover td.sp{background:#f1f5f9}
/* Row label + the two annotations that make the vertical axis mean something:
   how many of the tier's orthogroups this proteome actually carries, as a bar
   and as a number.  Without them the row axis is 153 names with no scale. */
table.mx td.sp .nm{display:inline-block;overflow:hidden;text-overflow:ellipsis;vertical-align:bottom}
table.mx td.sp .nm b{color:#166534;font-weight:700}
table.mx td.sp .occ{display:inline-block;width:52px;height:7px;background:#eef2f7;border-radius:4px;vertical-align:1px;margin:0 5px 0 4px;overflow:hidden}
table.mx td.sp .occ i{display:block;height:7px;background:#2563eb}
table.mx td.sp .ocn{display:inline-block;width:36px;text-align:right;font-variant-numeric:tabular-nums;color:#475569;font-size:10px;vertical-align:bottom}
/* Class bands.  Rows arrive sorted by class, so the y axis has eight natural
   sections; a rule and a name at each boundary is what turns 153 names into a
   list a reader can navigate.  The name lives in the sticky cell so it stays
   visible while panning. */
/* The band has to be DARKER than the absent-cell grey (#f1f5f9), otherwise it
   reads as a gap in the grid instead of as a boundary between two classes. */
table.mx tr.gband td{background:#dde5ee;border-top:2px solid #94a3b8;border-bottom:1px solid #cbd5e1;height:15px;line-height:15px}
table.mx tr.gband td.sp{font-size:11px;font-weight:700;color:#334155;padding:0 8px 0 6px}
table.mx tr.gband td.sp span{font-weight:600;color:#64748b}
/* Crosshair.  A translucent inset fill is the cheapest way to tint a table cell
   without touching its background, and it is what ties a cell to its row label
   and its column head once the grid is far larger than the viewport. */
table.mx tr.hl td.sp{background:#dbeafe}
table.mx td.hlc{box-shadow:inset 0 0 0 99px rgba(15,23,42,.20)}
table.mx tr.hl td.hlc{box-shadow:inset 0 0 0 99px rgba(15,23,42,.34)}
table.mx th.hlc{background:#dbeafe;color:#1d4ed8}
/* Column-header labels.  Only the tier's gene count is bounded, not the width of
   its annotations, so the two cannot both be small -- hence three views:
     .vert   the label is set bottom-to-top in an 11px column; a 150px header
             fits about 26 characters, which covers almost every label here.
     .tilt   the label is set on a slant and the column is made about as wide as
             the label is long, so 14-120 columns read like ordinary text.
     .slant  the same slant, but for tiers of a thousand columns, where a column
             may not be 60px wide.  It does not need to be: labels that share an
             angle stay parallel, and two parallel lines only collide when the
             distance *perpendicular* to them is under a line height.  Columns
             14px apart give 14 x cos(22deg) = 13px of that, so every label can
             run its full length across its neighbours and the length costs
             header height instead of table width.  That is what makes the
             orthogroup id, its accession and a draft of the description fit in
             a column 14px wide.
   All three carry the full annotation in a tooltip, and in the readout. */
table.mx th.vert{writing-mode:vertical-rl;transform:rotate(180deg);max-height:150px;font-size:9.5px;padding:5px 2px;width:12px;text-align:left;vertical-align:bottom}
/* No `position` here on purpose: the `table.mx th` rule above makes the head
   sticky, and sticky is already a positioned value, so it stays the containing
   block for the absolutely-positioned label below.  Overriding it with
   position:relative would silently unstick the whole header row. */
table.mx th.tilt{height:175px;padding:0;vertical-align:bottom}
table.mx th.tilt>span{position:absolute;left:5px;bottom:6px;transform-origin:0 100%;transform:rotate(-68deg);white-space:nowrap;font-size:10px;line-height:1;color:#475569}
table.mx th.tilt>span.og,table.mx th.vert>span.og{color:#64748b;font-style:italic;font-weight:400}
/* 190px is what the label budget below is measured against: 180px of text
   leaning 22deg off vertical stands 167px tall, plus the 6px baseline.
   The head cell drops `position` -- and with it its own background -- because a
   label here is longer than the 14px column it belongs to and must be free to
   run across its neighbours.  A positioned cell paints its background after the
   *previous* cell's label, which would cut every label off after five
   characters; static cells paint before any positioned descendant, so the band
   moves to the row and the labels sit on top of it.  That is also why a label
   is hung off the row and not off its own cell: a static cell is not a
   containing block, so each label carries the x its column starts at. */
table.mx thead{position:sticky;top:0;z-index:2;background:#f1f5f9}
table.mx th.slant{height:190px;padding:0;vertical-align:bottom;position:static;background:none}
/* The `background:none` above kills the crosshair tint on the cell, so the
   tinted state has to be restated here or pointing at a column would light up
   every head but a slanted one. */
table.mx th.slant.hlc{background:#dbeafe}
table.mx th.slant>span{position:absolute;bottom:6px;transform-origin:0 100%;transform:rotate(-68deg);white-space:nowrap;font-size:9.5px;line-height:1.15;color:#475569;pointer-events:none}
table.mx th.slant>span .g{color:#64748b}                 /* the orthogroup id */
table.mx th.slant>span .t{color:#2563eb}                 /* its database accession */
table.mx th.slant>span.og{color:#64748b;font-style:italic}
/* The last label leans past the right edge of the table, so the grid ends on a
   blank column wide enough to hold it rather than being cut off mid-word when
   the pan reaches the end. */
table.mx th.padx,table.mx td.padx{border:none}
.cc-legend{display:flex;gap:18px;flex-wrap:wrap;font-size:12.5px;color:#475569;margin:10px 0}
.cc-legend i{display:inline-block;width:13px;height:13px;border-radius:3px;vertical-align:-2px;margin-right:5px}
/* Overview.  A 1,017-column grid is eight screens wide, so the detail table can
   show a reader a region but never the shape of the tier.  The canvas above it
   draws the same matrix at about 1px per column and 1.5px per row: the shape
   (which orthogroups are broadly conserved, which classes carry them) reads at
   a glance, and the outlined window says which part of it the table is showing. */
.mx-ov{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:11px 13px 9px;margin:0 0 10px}
.mx-ov h3{margin:0 0 3px;font-size:13.5px;color:#1e293b;font-weight:700;border:none;padding:0}
.mx-ov p{margin:0 0 7px;font-size:12px;color:#64748b;line-height:1.55}
.mx-ov canvas{display:block;cursor:crosshair}
.mx-ovk{display:flex;gap:6px 15px;flex-wrap:wrap;font-size:11.5px;color:#475569;margin-top:7px;line-height:1.5}
.mx-ovk i{display:inline-block;width:11px;height:11px;border-radius:3px;vertical-align:-1px;margin-right:4px}
/* Sticky so the readout is in view wherever the pointer is in a 2,100px table.
   Nothing above it in the page is sticky, so top:0 is free. */
.mx-read{position:sticky;top:0;z-index:6;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:7px 12px;font-size:12.5px;color:#334155;margin:0 0 10px;min-height:33px}
.mx-read b{color:#1d4ed8}
.mx-read .d{color:#64748b}
.mx-read .k{color:#64748b}
.cc-dl{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.cc-dl a{display:block;padding:14px 16px;border:1px solid #e2e8f0;border-radius:10px;text-decoration:none;background:#fff}
.cc-dl a:hover{border-color:#93c5fd;background:#f8fafc;text-decoration:none}
.cc-dl b{display:block;color:#1d4ed8;font-size:14px;margin-bottom:4px}
.cc-dl span{font-size:12px;color:#64748b;line-height:1.5}
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
                    <li><a href="/phylotree/">Species Tree</a></li>
                    <li><a href="/core/">Core Orthologs</a></li>
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Core Orthologs</b></legend>

<p class="paleo-intro">A Cnidaria-specific core single-copy ortholog resource (CCO), built from the
same <?php echo number_format($n_species); ?>-proteome OrthoFinder run that powers the gene-family
pages, with the species &times; orthogroup presence/absence matrix released alongside it. Where BUSCO
scores a genome against a curated lineage, this defines the core on CnidoSite's own taxonomic
sampling and lets you re-threshold it.</p>

<div class="cc-stats">
  <span class="cc-stat"><b><?php echo number_format($tier_counts['core'] ?? 0); ?></b>core orthogroups</span>
  <span class="cc-stat"><b><?php echo number_format($tier_counts['strict'] ?? 0); ?></b>strict tier</span>
  <span class="cc-stat"><b><?php echo number_format($tier_counts['extended'] ?? 0); ?></b>extended tier</span>
  <span class="cc-stat"><b><?php echo number_format($n_hq); ?></b>genomes at &ge;90% BUSCO</span>
  <span class="cc-stat"><b><?php echo number_format($n_species); ?></b>proteomes scored</span>
  <span class="cc-stat"><b><?php echo number_format($n_members); ?></b>member genes</span>
</div>

<div class="cc-tabs">
<?php foreach ($TABS as $k => $v): ?>
  <a href="?tab=<?php echo cc_h($k); ?>&tier=<?php echo cc_h($tier); ?>"
     class="<?php echo $tab === $k ? 'on' : ''; ?>"><?php echo cc_h($v); ?></a>
<?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
<div class="cc-card">
  <h2>What this resource is</h2>
  <p>For every orthogroup in the CnidoSite OrthoFinder run we report the fraction of genomes in
     which it is <b>present</b> and the fraction in which it is <b>single-copy</b>. An orthogroup
     enters a tier when its single-copy fraction among the high-quality genomes clears that tier's
     cutoff. The tiers are cumulative, so <span class="cc-tier t-strict">strict</span> is a subset
     of <span class="cc-tier t-core">core</span>, which is a subset of
     <span class="cc-tier t-extended">extended</span>.</p>
  <table class="cc" style="margin-top:14px">
    <tr><th class="nos">Tier</th><th class="nos">Single-copy in</th><th class="nos">Orthogroups</th>
        <th class="nos">Typical use</th></tr>
    <tr><td><span class="cc-tier t-strict">strict</span></td>
        <td>&ge;90% of the <?php echo $n_hq; ?> high-quality genomes</td>
        <td class="cc-num"><?php echo number_format($tier_counts['strict'] ?? 0); ?></td>
        <td>Highest-confidence markers; concatenation with almost no missing data.</td></tr>
    <tr><td><span class="cc-tier t-core">core</span></td>
        <td>&ge;80%</td>
        <td class="cc-num"><?php echo number_format($tier_counts['core'] ?? 0); ?></td>
        <td>The default marker set for phylogenomics and cross-species comparison.</td></tr>
    <tr><td><span class="cc-tier t-extended">extended</span></td>
        <td>&ge;70%</td>
        <td class="cc-num"><?php echo number_format($tier_counts['extended'] ?? 0); ?></td>
        <td>Maximum gene count; expect more missing data and more copy-number decisions.</td></tr>
  </table>
  <div class="cc-note">
    <b>Why tiers rather than one number.</b> No cutoff is right for every use, so the full
    per-orthogroup score table is downloadable and every table on this page can be re-filtered.
    The tier sizes are a property of cnidarian genomes, not of the pipeline: duplication is high
    and unevenly spread across these <?php echo (int)$n_hq; ?> genomes &mdash; the duplicated-BUSCO
    fraction runs from <?php echo number_format((float)$busco_dup['mn'], 1); ?>% to
    <?php echo number_format((float)$busco_dup['mx'], 1); ?>%, averaging
    <?php echo number_format((float)$busco_dup['av'], 1); ?>%, and the most duplicated of them is a
    hydrozoan, not a coral &mdash; so genuinely single-copy genes are rarer here than the BUSCO group
    count suggests.
  </div>
</div>

<div class="cc-card">
  <h2>How this compares with BUSCO</h2>
  <p>The same question asked of the curated <code>cnidaria_odb12</code> lineage, over the same
     <?php echo $n_hq; ?> high-quality genomes &mdash; a BUSCO group counted as single-copy only
     where BUSCO called it <i>Complete</i> (not <i>Duplicated</i>) in that genome:</p>
  <table class="cc" style="margin-top:12px">
    <tr><th class="nos">Single-copy in</th><th class="nos">BUSCO cnidaria_odb12</th>
        <th class="nos">CCO orthogroups</th></tr>
    <?php
      $rows = array(
        array('100% of genomes', 'ge100', null),
        array('&ge;95%', 'ge95', null),
        array('&ge;90%', 'ge90', 'strict'),
        array('&ge;80%', 'ge80', 'core'),
      );
      foreach ($rows as $rr):
        $busco_n = isset($busco_row[$rr[1]]) ? (int)$busco_row[$rr[1]] : 0;
    ?>
    <tr><td><?php echo $rr[0]; ?></td>
        <td class="cc-num"><?php echo number_format($busco_n); ?> of <?php echo number_format((int)($busco_row['n_groups'] ?? 0)); ?></td>
        <td class="cc-num"><?php
            if ($rr[1] === 'ge100') { echo 0; }
            elseif ($rr[2] && isset($tier_counts[$rr[2]])) { echo number_format($tier_counts[$rr[2]]); }
            else { echo '&mdash;'; }
        ?></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="cc-note warn">
    <?php /* 2026-09-29：上面那张表以前把分母写成 61（多算了 PMULT），ge100 才数出 1，
             这里就跟着写 "only one"。查询改成限定 core_species.busco90=1 之后 ge100 = 0，
             与表格第一行 "0 of 3,203" 一致 —— 也才是"这就是本资源的意义"该说的话。 */ ?>
    <b>This is the point of the resource.</b> Even the curated lineage is not universally
    single-copy across these genomes: <b>not one</b> of its <?php echo number_format((int)($busco_row['n_groups'] ?? 0)); ?>
    groups is single-copy in all <?php echo $n_hq; ?> high-quality genomes. The two sets are
    complementary rather than competing<?php /* 2026-09-29：这两个百分比是写死的，数字本身
      已核对无误（66% = 82/125；43% = 58,903/136,050，2026-09-29 由 48% 改正）。但因为不
      现算，表一长就会漂 —— 复核用：
        SELECT COUNT(*) FROM (SELECT b.BUSCO_ID FROM busco b JOIN core_species cs
          ON cs.abbr1=b.abbr COLLATE utf8mb4_0900_ai_ci AND cs.busco90=1
          WHERE b.Status='Complete' AND NOT EXISTS (SELECT 1 FROM busco d
            WHERE d.BUSCO_ID=b.BUSCO_ID AND d.abbr=b.abbr COLLATE utf8mb4_0900_ai_ci
            AND d.Status='Duplicated') GROUP BY b.BUSCO_ID HAVING COUNT(DISTINCT b.abbr)>=54) g
          WHERE EXISTS (SELECT 1 FROM busco b2 JOIN core_member m
            ON m.abbr=b2.abbr COLLATE utf8mb4_0900_ai_ci
            AND m.gene=b2.gene COLLATE utf8mb4_0900_ai_ci
            JOIN core_og o ON o.og=m.og WHERE b2.BUSCO_ID=g.BUSCO_ID);
        -- 43%：SELECT COUNT(*), SUM(EXISTS(SELECT 1 FROM busco b WHERE b.abbr=m.abbr
        --   COLLATE utf8mb4_0900_ai_ci AND m.gene=b.gene COLLATE utf8mb4_0900_ai_ci))
        --   FROM core_member m JOIN core_og o ON o.og=m.og; */ ?>
    &mdash; 66% of the BUSCO groups that reach &ge;90%
    single-copy do have members inside a CCO orthogroup, and 43% of CCO member genes carry a
    BUSCO assignment, so the CCO set is enriched for exactly the conserved genes BUSCO tracks.
  </div>
</div>

<div class="cc-card">
  <h2>Getting started</h2>
  <ul>
    <li><a href="?tab=ogs&tier=<?php echo cc_h($tier); ?>">Browse the core orthogroups</a> &mdash; search by
        annotation, filter by tier, and follow any row through to its gene family page and gene tree.</li>
    <li><a href="?tab=matrix&tier=<?php echo cc_h($tier); ?>">Inspect the presence/absence matrix</a> &mdash;
        every genome against every core orthogroup, coloured by copy number.</li>
    <li><a href="?tab=download&tier=<?php echo cc_h($tier); ?>">Download</a> the matrices, the core gene
        sequences, and ready-made concatenated supermatrices with partition files.</li>
  </ul>
</div>

<?php elseif ($tab === 'ogs'): ?>
<div class="cc-card">
  <h2>Core orthogroups &mdash; <?php echo cc_h($TIER_LABEL[$tier]); ?> tier</h2>
  <div class="cc-filter">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <input type="hidden" name="tab" value="ogs">
      <input type="hidden" name="tier" value="<?php echo cc_h($tier); ?>">
      <label>Tier</label>
      <?php foreach ($TIER_LABEL as $k => $v): ?>
        <a class="cc-btn <?php echo $k === $tier ? '' : 'alt'; ?>"
           href="?tab=ogs&tier=<?php echo cc_h($k); ?><?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>"><?php echo cc_h($v); ?>
           (<?php echo number_format($tier_counts[$k] ?? 0); ?>)</a>
      <?php endforeach; ?>
      <input type="text" name="q" value="<?php echo cc_h($q); ?>" placeholder="search OG, term, name, description">
      <button class="cc-btn" type="submit">Search</button>
    </form>
  </div>
  <?php if (!$ogs): ?>
    <p>No core orthogroups matched.</p>
  <?php else: ?>
  <div class="cc-scroll">
  <table class="cc" id="ogtable">
    <thead><tr>
      <th>Orthogroup</th><th>Tier</th>
      <th class="cc-num">Single-copy<br>(of <?php echo $n_hq; ?>)</th>
      <th class="cc-num">Single-copy<br>fraction</th>
      <th class="cc-num">Present<br>(of <?php echo $n_cnid; ?>)</th>
      <th class="cc-num">Outgroups<br>single-copy</th>
      <th>Best annotation</th>
    </tr></thead>
    <tbody>
    <?php foreach ($ogs as $o): ?>
      <tr>
        <td><a href="/genefamily_result.php?family=<?php echo cc_h($o['og']); ?>"><?php echo cc_h($o['og']); ?></a>
            &middot; <a href="/genetree/?family=<?php echo cc_h($o['og']); ?>" title="gene tree">tree</a></td>
        <td><?php
          foreach (explode(',', $o['tiers']) as $t) {
              if ($t !== '') {
                  echo '<span class="cc-tier t-' . cc_h($t) . '">' . cc_h($t) . '</span> ';
              }
          }
        ?></td>
        <td class="cc-num"><?php echo (int)$o['hq90_single']; ?> / <?php echo (int)$o['hq90_n']; ?></td>
        <td class="cc-num"><span class="cc-bar wrap"><span class="cc-bar"
             style="width:<?php echo round(90 * (float)$o['hq90_sc']); ?>px"></span></span>
             <?php echo number_format(100 * (float)$o['hq90_sc'], 1); ?>%</td>
        <td class="cc-num"><?php echo (int)$o['all_present']; ?> / <?php echo (int)$o['all_n']; ?></td>
        <td class="cc-num"><?php echo (int)$o['outgroup_single']; ?> / 5</td>
        <td><?php
          $lab = cc_anno_text($o);
          if ($lab === '') { $lab = '— (no consensus annotation)'; }
          echo cc_h($lab);
          if ($o['best_term'] !== '') {
              echo ' <span style="color:#64748b;font-size:11.5px">[' . cc_h($o['best_source']) . ':'
                   . cc_h($o['best_term']) . ']</span>';
          }
        ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p style="margin-top:10px;font-size:12.5px;color:#64748b">
    Showing <?php echo count($ogs); ?> of <?php echo number_format($tier_counts[$tier] ?? 0); ?>.
    Click a column header to sort.
  </p>
  <?php endif; ?>
</div>

<script>
/* Click-to-sort on the orthogroup table.  Numbers carry a data-v so that
   "12 / 60" sorts as 12 and not as the string. */
(function () {
  var t = document.getElementById('ogtable');
  if (!t) { return; }
  var ths = t.tHead.rows[0].cells, dir = {};
  for (var i = 0; i < ths.length; i++) {
    (function (idx) {
      ths[idx].addEventListener('click', function () {
        dir[idx] = !dir[idx];
        var tb = t.tBodies[0], rows = Array.prototype.slice.call(tb.rows);
        rows.sort(function (a, b) {
          var x = a.cells[idx].getAttribute('data-v') || a.cells[idx].textContent.trim();
          var y = b.cells[idx].getAttribute('data-v') || b.cells[idx].textContent.trim();
          var nx = parseFloat(x), ny = parseFloat(y);
          if (!isNaN(nx) && !isNaN(ny)) { return dir[idx] ? nx - ny : ny - nx; }
          return dir[idx] ? x.localeCompare(y) : y.localeCompare(x);
        });
        for (var k = 0; k < rows.length; k++) { tb.appendChild(rows[k]); }
      });
    })(i);
  }
})();
</script>

<?php elseif ($tab === 'matrix'): ?>
<div class="cc-card">
  <h2>Presence / copy number matrix &mdash; <?php echo cc_h($TIER_LABEL[$tier]); ?> tier</h2>
  <p>Rows are the <?php echo number_format($n_species); ?> proteomes in the OrthoFinder run, in
     class order and then by family, genus and species epithet, with the five non-cnidarian outgroups
     last (they carry no class); columns
     are the <?php echo number_format(count($matrix_ogs)); ?> orthogroups in this tier, ordered by
     single-copy fraction and labelled with their consensus annotation. Each column head carries
     the orthogroup id, the accession its annotation came from (PTHR&hellip; PANTHER,
     PF&hellip; Pfam, GO:&hellip;, KEGG) and as much of the description as the head has room for;
     the full text is in the tooltip and in the readout above the grid. The view switches between
     the three matrices published in the
     <a href="?tab=download&tier=<?php echo cc_h($tier); ?>">download</a> section; all three are
     derived from the same copy numbers, so they can never disagree with each other.</p>
  <div class="cc-filter">
    <?php foreach ($TIER_LABEL as $k => $v): ?>
      <a class="cc-btn <?php echo $k === $tier ? '' : 'alt'; ?>"
         href="?tab=matrix&tier=<?php echo cc_h($k); ?>"><?php echo cc_h($v); ?>
         (<?php echo number_format($tier_counts[$k] ?? 0); ?> genes)</a>
    <?php endforeach; ?>
    <label><input type="radio" name="mxmode" value="presence" checked> presence</label>
    <label><input type="radio" name="mxmode" value="copynumber"> copy number</label>
    <label><input type="radio" name="mxmode" value="singlecopy"> single-copy only</label>
    <label><input type="checkbox" id="mxhq" checked> all genomes (uncheck for BUSCO &ge;90% only)</label>
    <label>Gene labels
      <select id="mxhdr">
        <option value="slant">slanted &mdash; id + accession</option>
        <option value="tilt">slanted &mdash; roomy</option>
        <option value="vert">upright (narrowest)</option>
      </select></label>
    <label>Wheel
      <select id="mxwheel">
        <option value="y" selected>&#8597; down the page</option>
        <option value="x">&#8596; across genes</option>
      </select></label>
  </div>
  <div class="cc-legend" id="mxlegend"></div>
  <div class="mx-ov">
    <h3>Overview &mdash; the whole tier on one screen</h3>
    <p><?php echo number_format(count($matrix_ogs)); ?> orthogroups left to right &times;
       <?php echo number_format($n_species); ?> proteomes top to bottom, the same encoding as the
       table below, drawn at about 1px a column. The strip along the top of the grid is the share of
       proteomes that carry each orthogroup, so the tier's shape &mdash; which families are
       universally conserved, where it thins out &mdash; is visible at a glance. The outlined window
       is the part the table is showing; <b>click or drag anywhere on the grid to jump the table
       there</b>, or point at a cell to have the table read it out.</p>
    <canvas id="mxov"></canvas>
    <div class="mx-ovk" id="mxovk"></div>
  </div>
  <div class="mx-read" id="mxread"><span class="d">Point at a cell &mdash; in the overview above or
    in the table below &mdash; to read its proteome and its orthogroup here.</span></div>
  <div class="cc-scroll-mx" id="mxscroll"><div id="mxhost"></div></div>
  <p style="font-size:16px;color:#64748b;margin-top:8px">
    The full matrix is shown &mdash; every row and every gene. The page scrolls for the rest of the
    proteomes; the gene axis is the wide one, so the wheel is set to <b>&#8597; down the page</b> by
    default and <b>Shift</b> (or the scrollbar, or the overview) pans across genes. Pointing at any
    cell tints its row and its column and names both in the readout above. Hover any column head for
    the full annotation, its source, the orthogroup id and its occupancy; hover any row label for the
    genome's <code>abbr1</code> code, its class and its presence count.
    <b>Slanted</b> labels read more easily but take a column roughly as wide as the label is long
    &mdash; on the extended tier that is 45,000px of table. <b>Upright</b> sets the same labels
    bottom-to-top in a 12px column, about four times narrower; the table always opens with slanted
    labels (roomy up to 120 columns, dense above that), so pick upright by hand when you want the
    narrowest columns.
    <?php
      $n_anno = 0;
      foreach ($matrix_meta as $m) { if ($m[0] !== '') { $n_anno++; } }
    ?>
    <?php echo number_format($n_anno); ?> of <?php echo number_format(count($matrix_ogs)); ?>
    orthogroups in this tier have a consensus annotation; the rest show their id in grey.
  </p>
  <script>
  (function () {
    var OGS = <?php echo json_encode($matrix_ogs); ?>;
    var OGM = <?php echo json_encode($matrix_meta); ?>;
    var SP  = <?php echo json_encode(array_map(function ($s) {
        return array($s['abbr1'], $s['latin'], (int)$s['busco90'],
                     (int)$s['cnidarian'], $s['class']);
    }, $species)); ?>;
    var MX  = <?php echo json_encode($matrix); ?>;
    /* The grid draws a row for every proteome in core_species, but the tiers
       themselves are ranked over the busco90 subset only, so the corner caption
       has to say both numbers or "153 proteomes" reads as the tier's basis. */
    var NHQ = <?php echo (int)$n_hq; ?>;

    /* Row labels are the full scientific name; the abbr1 code moves to the
       tooltip, where the rest of the site uses it as the key anyway. */
    function esc(s) {
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function label(j) {
      var m = OGM[OGS[j]];
      return (m && m[0]) ? m[0] : OGS[j];
    }
    function term(j) {
      var m = OGM[OGS[j]];
      return (m && m[2]) ? m[2] : '';
    }
    /* ---- the wide tier's column head ---------------------------------------
       Three parts, in the order a reader needs them: which orthogroup (the id
       the rest of the site keys on), what it was annotated from (the accession
       -- PTHR10527, PF00018, GO:0005515), and as much of the description as is
       left over.  Only the last part is ever cut, and it is cut on a word
       boundary, because a head that ends mid-word reads as a rendering fault
       rather than as an abbreviation.  Widths are measured rather than counted:
       the descriptions mix upper and lower case and a character count is out by
       a third. */
    var SLANT_BUDGET = 180;      /* px of text a 190px head holds at -68deg */
    var hctx = document.createElement('canvas').getContext('2d');
    hctx.font = '9.5px ' + (window.getComputedStyle(document.body).fontFamily || 'sans-serif');
    function hw(s) { return hctx.measureText(s).width; }
    function hcut(s, room) {
      if (hw(s) <= room) { return s; }
      var lo = 0, hi = s.length, mid, cut, sp;
      while (lo < hi) {                       /* widest prefix that fits */
        mid = (lo + hi + 1) >> 1;
        if (hw(s.slice(0, mid)) <= room - hw('…')) { lo = mid; } else { hi = mid - 1; }
      }
      cut = s.slice(0, lo);
      sp = cut.lastIndexOf(' ');
      if (sp >= lo * 0.55) { cut = cut.slice(0, sp); }   /* prefer a whole word */
      return cut.replace(/[\s,;:\-]+$/, '') + '…';
    }
    /* The id and the accession are never dropped -- an unreadable column is
       worse than a short one -- so the description gets what is left, and gets
       dropped entirely when that is under about six characters. */
    function headParts(j) {
      var og = OGS[j], t = term(j), d = label(j);
      if (d === og) { d = ''; }
      var w = hw(og) + (t ? 4 + hw(t) : 0), room = SLANT_BUDGET - w - 5;
      if (d && room < 34) { d = ''; }
      else if (d) { d = hcut(d, room); }
      return { og: og, term: t, desc: d };
    }
    /* The one span the slant head carries, in three colours so the id, the
       accession and the description can be told apart at a glance.  The narrow
       heads keep the single-colour 20-character form the stylesheet sizes. */
    function headHTML(j) {
      if (HDR !== 'slant') {
        return '<span' + (OGM[OGS[j]] && OGM[OGS[j]][0] ? '' : ' class="og"') + '>' +
               esc(label(j)) + '</span>';
      }
      var p = headParts(j);
      return '<span' + (p.term || p.desc ? '' : ' class="og"') +
             ' style="left:' + (cum[j] + 1) + 'px">' +
             '<span class="g">' + esc(p.og) + '</span>' +
             (p.term ? ' <span class="t">' + esc(p.term) + '</span>' : '') +
             (p.desc ? ' <span class="d">' + esc(p.desc) + '</span>' : '') +
             '</span>';
    }
    function tipOf(j) {
      var m = OGM[OGS[j]];
      return (m && m[1]) ? m[1] : OGS[j];
    }
    /* A roomy slanted label needs a column as wide as its horizontal
       projection: |label| characters at ~6.2px -- the PANTHER descriptions are
       uppercase and uppercase is wide -- times sin(22deg), the slant the
       stylesheet sets.  9px of slack.
       Upright needs only the width of one line of 9.5px text, which the
       stylesheet pads to 12px.  A dense slanted label does not need its own
       length at all: parallel labels are separated by column width x cos(22deg)
       = 13px, which is a line of 9.5px text and change, so 14px is enough for
       any of them -- see the .slant note in the stylesheet. */
    function colw(j) {
      if (HDR === 'slant') { return 14; }
      if (HDR === 'vert')  { return 12; }
      return Math.max(13, Math.round(label(j).length * 6.2 * 0.375) + 9);
    }

    var LEGEND = {
      presence:   ['#f1f5f9|absent', '#2563eb|present'],
      copynumber: ['#f1f5f9|absent', '#2563eb|single copy — usable in a concatenation',
                   '#f59e0b|2 copies', '#dc2626|3 or more copies'],
      singlecopy: ['#f1f5f9|absent', '#2563eb|exactly one gene',
                   '#f59e0b|more than one — not usable as single-copy']
    };
    var FILL = { m0: '#f1f5f9', m1: '#2563eb', m2: '#f59e0b', m3: '#dc2626' };
    /* Cell class per mode.  The copy grid is sent once and the other two views
       are derived in the browser, so a switch cannot disagree with the file.
       The grid saturates at 9, so `m3` covers "3 or more". */
    function cls(mode, c) {
      if (mode === 'presence')   { return c > 0 ? 'm1' : 'm0'; }
      if (mode === 'singlecopy') { return c === 1 ? 'm1' : (c > 1 ? 'm2' : 'm0'); }
      return 'm' + (c > 2 ? 3 : c);
    }
    function copyText(c) {
      if (c === 0) { return 'absent'; }
      if (c === 1) { return '1 copy'; }
      if (c === 9) { return '9 or more copies'; }
      return c + ' copies';
    }
    function legend(mode) {
      document.getElementById('mxlegend').innerHTML = LEGEND[mode].map(function (s) {
        var p = s.split('|');
        return '<span><i style="background:' + p[0] +
               (p[0] === '#f1f5f9' ? ';border:1px solid #e2e8f0' : '') + '"></i>' + p[1] + '</span>';
      }).join('');
    }
    var host = document.getElementById('mxhost');
    var sc = document.getElementById('mxscroll');
    var readout = document.getElementById('mxread');
    /* A roomy slanted column costs its label's length, so that view is only the
       better one while the tier still roughly fits a screen: 120 columns is
       about 1,440px of table.  Past that the dense slant takes over -- it is
       14px a column, barely wider than the upright head, and unlike the upright
       head it can be read without turning the page sideways.  The select
       follows, and either can be picked by hand. */
    var HDR = OGS.length > 120 ? 'slant' : 'tilt';
    var WHEEL = 'y';                   /* 'x' = across genes, 'y' = down the page */

    /* A wheel has one delta to give, but the matrix has two axes of interest: it
       is taller than any viewport and the wide tiers are far wider than one.
       `mxwheel` picks which axis the wheel drives.  Down the page is the default
       because the page has to be scrolled far more often than the gene axis, and
       a table that swallowed the wheel would trap the reader in it; Shift always
       means the other axis.  A pan that cannot move any further is left
       unconsumed so the browser's own chaining takes over. */
    sc.addEventListener('wheel', function (e) {
      if (e.ctrlKey || e.deltaX) { return; }              /* pinch-zoom, trackpad pan */
      var axis = e.shiftKey ? (WHEEL === 'x' ? 'y' : 'x') : WHEEL;
      if (axis !== 'x') { return; }
      if (sc.scrollWidth <= sc.clientWidth + 1) { return; }
      var before = sc.scrollLeft;
      sc.scrollLeft = before + e.deltaY;
      if (sc.scrollLeft !== before) { e.preventDefault(); }
    }, { passive: false });
    /* ---------------------------------------------------------------- state */
    var rowsShown = [];        /* indices into SP, in the order drawn */
    var occ = [], occS = [];   /* per orthogroup: proteomes carrying it / single-copy in them */
    var presence = [], singleN = [];   /* per row: orthogroups present / single-copy */
    var colwArr = [], cum = [], tableW = 0;
    var rowEls = null, headRow = null;
    var hlRow = -1, hlCol = -1;

    /* The species column is sized to the widest name actually in it: no name is
       truncated, and no screen pays for width no name uses.  Measured with the
       canvas -- 153 spans would each need a layout pass -- and the 11px must
       match the `.nm` rule in the stylesheet. */
    var NAMEW = 210, SPW = 320;
    (function () {
      var c = document.createElement('canvas').getContext('2d');
      c.font = '11px ' + (window.getComputedStyle(document.body).fontFamily || 'sans-serif');
      var w = 0;
      for (var i = 0; i < SP.length; i++) {
        w = Math.max(w, c.measureText(SP[i][1] + (SP[i][2] ? ' *' : '')).width);
      }
      NAMEW = Math.round(Math.min(300, Math.max(170, w + 3)));
      SPW = 6 + NAMEW + 4 + 52 + 5 + 36 + 8;   /* pads, name, bar, count */
    })();

    function draw(mode, hqOnly) {
      var rows = [], i, j, r;
      for (i = 0; i < SP.length; i++) {
        if (hqOnly && (!SP[i][2] || !SP[i][3])) { continue; }
        rows.push(i);
      }
      rowsShown = rows;
      var n = rows.length, ng = OGS.length;

      /* One pass over the grid produces both margins: the per-orthogroup
         occupancy that annotates the column heads and the per-proteome presence
         count that annotates the rows.  155k charCodeAt calls, a few ms, once
         per mode or filter change. */
      occ = new Array(ng); occS = new Array(ng);
      presence = new Array(n); singleN = new Array(n);
      for (j = 0; j < ng; j++) { occ[j] = 0; occS[j] = 0; }
      for (r = 0; r < n; r++) {
        var s = MX[SP[rows[r]][0]] || '', on = 0, one = 0;
        for (j = 0; j < ng; j++) {
          var c = (s.charCodeAt(j) - 48) | 0;
          if (c > 0) { on++; occ[j]++; if (c === 1) { one++; occS[j]++; } }
        }
        presence[r] = on; singleN[r] = one;
      }

      /* Widths go in a colgroup so header and body cells of a column agree
         without repeating the width on all 1,000 cells beneath it, and the
         table's own width is set from their sum: with table-layout:fixed that
         sum is what makes the table overflow and the pan possible at all. */
      colwArr = []; tableW = SPW; cum = new Array(ng + 1); cum[0] = SPW;
      for (j = 0; j < ng; j++) {
        colwArr[j] = colw(j); tableW += colwArr[j]; cum[j + 1] = tableW;
      }
      /* A slanted head leans to the right of its own column, so the last one
         would be cut off by the edge of the scroll box.  One blank column of
         exactly that overhang keeps every label whole. */
      var OVER = HDR === 'slant' ? Math.round(SLANT_BUDGET * 0.375) + 6 : 0;
      tableW += OVER;

      var h = ['<table class="mx" style="width:' + tableW + 'px">',
               '<colgroup><col style="width:' + SPW + 'px">'];
      for (j = 0; j < ng; j++) { h.push('<col style="width:' + colwArr[j] + 'px">'); }
      if (OVER) { h.push('<col style="width:' + OVER + 'px">'); }
      h.push('</colgroup><thead><tr><th class="nos"><span>' + n + ' proteomes &darr;' +
             '<em>down = the row axis, top to bottom &middot; <b>*</b> = BUSCO &ge; 90%, ' +
             'the ' + NHQ + ' that define the tiers</em></span></th>');
      for (j = 0; j < ng; j++) {
        var tip = tipOf(j) + ' — present in ' + occ[j] + ' of ' + n +
                  ' proteomes, single-copy in ' + occS[j];
        h.push('<th class="' + HDR + '" title="' + esc(tip) + '">' +
               headHTML(j) + '</th>');
      }
      if (OVER) { h.push('<th class="padx"></th>'); }
      h.push('</tr></thead><tbody>');

      for (r = 0; r < n; r++) {
        var sp = SP[rows[r]];
        if (r === 0 || SP[rows[r - 1]][4] !== sp[4]) {
          var cnt = 0;
          for (i = r; i < n && SP[rows[i]][4] === sp[4]; i++) { cnt++; }
          h.push('<tr class="gband"><td class="sp">' +
                 esc(sp[4] === '' ? 'Outgroups (non-cnidarian)' : sp[4]) + ' <span>' + cnt +
                 ' proteome' + (cnt === 1 ? '' : 's') + '</span></td><td colspan="' +
                 (ng + (OVER ? 1 : 0)) + '"></td></tr>');
        }
        var row = MX[sp[0]] || '';
        h.push('<tr data-r="' + r + '"><td class="sp" title="' +
               esc(sp[0] + (sp[2] ? ' — BUSCO ≥ 90%' : '') + ' · ' +
                   (sp[4] === '' ? 'outgroup' : sp[4]) + ' · ' + presence[r] + ' of ' + ng +
                   ' orthogroups present, ' + singleN[r] + ' single-copy') + '">' +
               '<span class="nm" style="width:' + NAMEW + 'px">' + esc(sp[1]) +
               (sp[2] ? '<b>*</b>' : '') + '</span>' +
               '<span class="occ"><i style="width:' + Math.round(100 * presence[r] / Math.max(1, ng)) +
               '%"></i></span><span class="ocn">' + presence[r] + '</span></td>');
        for (j = 0; j < ng; j++) {
          h.push('<td class="' + cls(mode, (row.charCodeAt(j) - 48) | 0) + '"></td>');
        }
        if (OVER) { h.push('<td class="padx"></td>'); }
        h.push('</tr>');
      }
      h.push('</tbody></table>');
      host.innerHTML = h.join('');
      rowEls = host.querySelectorAll('tbody tr[data-r]');
      headRow = host.querySelector('thead tr');
      hlRow = -1; hlCol = -1;
      /* A redraw is a different table: keeping the old pan would drop the reader
         somewhere in the middle of a tier they have just switched away from. */
      sc.scrollLeft = 0;
    }

    /* ------------------------------------------------------------- crosshair
       With 1,000 columns and 153 rows, a cell on its own means nothing; what
       names it is its row label and its column head, and both are usually off
       screen.  Pointing at a cell tints its whole row and column and prints the
       pair in the readout, which is the only way to read a grid this size
       without counting.  Rows carry `data-r` because the body also holds the
       class-band rows, so sectionRowIndex would not address rowsShown. */
    function colPaint(j, on) {
      if (j < 0) { return; }
      for (var k = 0; k < rowEls.length; k++) {
        var cell = rowEls[k].cells[j + 1];
        if (cell) { cell.classList[on ? 'add' : 'remove']('hlc'); }
      }
      if (headRow && headRow.cells[j + 1]) {
        headRow.cells[j + 1].classList[on ? 'add' : 'remove']('hlc');
      }
    }
    function setHl(r, j) {
      if (r !== hlRow) {
        if (hlRow >= 0 && rowEls[hlRow]) { rowEls[hlRow].classList.remove('hl'); }
        hlRow = r;
        if (r >= 0 && rowEls[r]) { rowEls[r].classList.add('hl'); }
      }
      if (j !== hlCol) {
        colPaint(hlCol, false);
        hlCol = j;
        colPaint(j, true);
      }
    }
    /* Three sentences, because the row label, the column head and a cell each
       answer a different question.  The row and column forms exist so that
       pointing at a label -- which is what a reader does first -- never prints
       "undefined" for the half that is not there. */
    function spHead(r) {
      var sp = SP[rowsShown[r]];
      return '<b>' + esc(sp[1]) + '</b> <span class="k">(' + esc(sp[0]) +
             (sp[2] ? ', BUSCO ≥ 90%' : '') + ' · ' +
             esc(sp[4] === '' ? 'outgroup' : sp[4]) + ')</span>';
    }
    function ogHead(j) {
      var t = term(j);
      return '<b>' + esc(label(j)) + '</b> <span class="k">' + esc(OGS[j]) +
             (t ? ' · ' + esc(t) : '') + '</span>';
    }
    function sayRow(r) {
      var n = rowsShown.length, sp = SP[rowsShown[r]];
      readout.innerHTML = spHead(r) + ' <span class="k">· ' + presence[r] + ' of ' +
        OGS.length + ' orthogroups in this tier present, ' + singleN[r] +
        ' of them single-copy — the bar in its label. Point at a cell for the pair.</span>';
    }
    function sayCol(j) {
      readout.innerHTML = ogHead(j) + ' <span class="k">· present in ' + occ[j] + ' of ' +
        rowsShown.length + ' proteomes shown, single-copy in ' + occS[j] +
        '. The bar above the overview is this number for every column.</span>';
    }
    function sayCell(r, j, c) {
      readout.innerHTML = spHead(r) + ' × ' + ogHead(j) + ' — ' + copyText(c) +
        ' <span class="k">· ' + occ[j] + ' of ' + rowsShown.length +
        ' proteomes shown carry it, ' + occS[j] + ' single-copy</span>';
    }
    /* Both handlers go through the nearest cell, not through e.target: a label
       cell and a head cell are full of spans, and pointing at the text inside
       one would otherwise address the span and read out nothing. */
    host.addEventListener('mouseover', function (e) {
      var td = e.target && e.target.closest ? e.target.closest('td') : null;
      if (!td) { return; }
      var tr = td.parentNode, dr = tr.getAttribute ? tr.getAttribute('data-r') : null;
      var j = td.cellIndex - 1;
      if (j >= OGS.length) { return; }                /* the head's blank end column */
      if (dr === null) {
        if (tr.className === 'gband') { setHl(-1, -1); }   /* a class-band rule */
        return;
      }
      var r = +dr;
      if (j < 0) {                                  /* the species label itself */
        if (r === hlRow && hlCol === -1) { return; }
        setHl(r, -1);
        sayRow(r);
        return;
      }
      if (r === hlRow && j === hlCol) { return; }
      setHl(r, j);
      sayCell(r, j, ((MX[SP[rowsShown[r]][0]] || '').charCodeAt(j) - 48) | 0);
    });
    host.addEventListener('mouseleave', function () { setHl(-1, -1); });
    /* The column heads answer for their whole column -- which is the question a
       reader has when the tier is 1,017 genes wide and the label is the only
       part of the column on screen. */
    host.addEventListener('mouseover', function (e) {
      var th = e.target && e.target.closest ? e.target.closest('th') : null;
      if (!th || th.cellIndex < 1) { return; }
      var j = th.cellIndex - 1;
      if (j >= OGS.length) { return; }                /* the head's blank end column */
      if (j === hlCol) { return; }
      setHl(-1, j);
      sayCol(j);
    });

    /* -------------------------------------------------------------- overview
       Same data, same encoding, one pixel a column, so the shape of the tier is
       visible in one screen.  The static part is drawn once onto an offscreen
       canvas; a pan then only re-blits it and strokes the window, because the
       ~120,000 fillRects behind it are far too slow to redo per frame. */
    var ovc = document.getElementById('mxov');
    var ovk = document.getElementById('mxovk');
    var ovStill = document.createElement('canvas');
    var OVG = 78, OVT = 22, OVB = 18, OVW = 0, OVH = 0, OVX = 0, OVBODY = 0;
    var ovCols = [], ovScale = 1, ovDrag = false;
    var CCOL = { Cubozoa: '#7c3aed', Hexacorallia: '#2563eb', Hydrozoa: '#0891b2',
                 Myxozoa: '#dc2626', Octocorallia: '#ea580c', Scyphozoa: '#65a30d',
                 Staurozoa: '#db2777' };
    function klassCol(k) { return CCOL[k] || '#94a3b8'; }
    function klassName(k) { return k === '' ? 'outgroups' : k; }
    function ovFont() {
      return (window.getComputedStyle ? window.getComputedStyle(document.body).fontFamily : '') ||
             'sans-serif';
    }
    function ovY(r) { return OVT + Math.round(r * OVBODY / Math.max(1, rowsShown.length)); }

    function drawOverview(mode) {
      var n = rowsShown.length, ng = OGS.length;
      if (!n || !ng) { return; }
      OVW = Math.max(420, (ovc.parentNode.clientWidth || 900) - 26);
      OVBODY = Math.min(300, n * 2);
      OVH = OVT + OVBODY + OVB;
      OVX = OVG;
      var mw = OVW - OVX - 3;
      ovCols = new Array(ng);
      for (var j = 0; j < ng; j++) {
        var a = OVX + Math.round(j * mw / ng), b = OVX + Math.round((j + 1) * mw / ng);
        ovCols[j] = [a, b > a ? b : a + 1];
      }
      ovScale = window.devicePixelRatio || 1;
      ovc.width = Math.round(OVW * ovScale); ovc.height = Math.round(OVH * ovScale);
      ovc.style.width = OVW + 'px'; ovc.style.height = OVH + 'px';
      ovStill.width = ovc.width; ovStill.height = ovc.height;
      var g = ovStill.getContext('2d');
      g.setTransform(ovScale, 0, 0, ovScale, 0, 0);
      g.clearRect(0, 0, OVW, OVH);

      /* Absent cells are the panel's own background, so only the present ones
         are drawn -- one pass per fill, so fillStyle is set three times per mode
         rather than 150,000. */
      g.fillStyle = '#f8fafc';
      g.fillRect(OVX, OVT, mw, OVBODY);
      var order = mode === 'presence' ? ['m1'] :
                  (mode === 'singlecopy' ? ['m1', 'm2'] : ['m1', 'm2', 'm3']);
      for (var oi = 0; oi < order.length; oi++) {
        g.fillStyle = FILL[order[oi]];
        for (var r = 0; r < n; r++) {
          var s = MX[SP[rowsShown[r]][0]] || '', y = ovY(r), hh = ovY(r + 1) - y;
          for (var j2 = 0; j2 < ng; j2++) {
            if (cls(mode, (s.charCodeAt(j2) - 48) | 0) !== order[oi]) { continue; }
            g.fillRect(ovCols[j2][0], y, ovCols[j2][1] - ovCols[j2][0], hh);
          }
        }
      }

      /* The strip along the top: the share of the proteomes shown that carry
         each orthogroup.  The tier is ordered by single-copy fraction, so the
         strip is also a picture of how the tier was cut. */
      var stripH = OVT - 5;
      g.fillStyle = '#bfdbfe';
      for (j = 0; j < ng; j++) {
        var bh = Math.round(occ[j] / n * stripH);
        if (bh > 0) { g.fillRect(ovCols[j][0], OVT - 3 - bh, ovCols[j][1] - ovCols[j][0], bh); }
      }
      g.fillStyle = '#e2e8f0';
      g.fillRect(OVX, OVT - 2, mw, 1);
      g.font = '9px ' + ovFont();
      g.fillStyle = '#94a3b8'; g.textAlign = 'right'; g.textBaseline = 'alphabetic';
      g.fillText(String(n), OVX - 4, OVT - 5);
      g.fillText('0', OVX - 4, OVT - 1);

      /* The row axis, annotated where it can be: a colour per class down the
         left edge, named at the band's centre.  Bands thinner than a line of
         text push the following name down rather than overprint it, and the key
         under the canvas carries every class either way. */
      var lastY = -99, b = 0;
      g.textAlign = 'left'; g.textBaseline = 'middle';
      while (b < n) {
        var k = SP[rowsShown[b]][4], e = b;
        while (e < n && SP[rowsShown[e]][4] === k) { e++; }
        var y0 = ovY(b), y1 = ovY(e);
        g.fillStyle = klassCol(k);
        g.fillRect(0, y0, 5, Math.max(1, y1 - y0));
        var ly = Math.max((y0 + y1) / 2, lastY + 11);
        g.font = '9.5px ' + ovFont();
        g.fillStyle = '#475569';
        g.fillText(klassName(k) + ' ' + (e - b), 9, ly);
        lastY = ly;
        b = e;
      }
      ovKey();
      ovWindow();
    }

    function ovKey() {
      var seen = {}, order = [], r, k;
      for (r = 0; r < rowsShown.length; r++) {
        k = SP[rowsShown[r]][4];
        if (seen[k] === undefined) { seen[k] = 0; order.push(k); }
        seen[k]++;
      }
      var out = [];
      for (r = 0; r < order.length; r++) {
        out.push('<span><i style="background:' + klassCol(order[r]) + '"></i>' +
                 klassName(order[r]) + ' (' + seen[order[r]] + ')</span>');
      }
      out.push('<span><i style="background:#bfdbfe"></i>the strip above the grid is the share of ' +
               'the ' + rowsShown.length + ' proteomes shown that carry that orthogroup</span>');
      ovk.innerHTML = out.join('');
    }

    /* Table x -> column index.  cum[] holds each column's left edge, so the
       window in the overview is the real visible range of the real table even
       when the columns are of unequal width (which the slanted head makes them). */
    function ovColAt(tx) {
      var lo = 0, hi = cum.length - 2, mid;
      if (hi < 0 || tx <= cum[0]) { return 0; }
      while (lo < hi) {
        mid = (lo + hi + 1) >> 1;
        if (cum[mid] <= tx) { lo = mid; } else { hi = mid - 1; }
      }
      return lo;
    }
    function ovWindow() {
      if (!OVW || !rowsShown.length) { return; }
      var ctx = ovc.getContext('2d');
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.drawImage(ovStill, 0, 0);
      var ng = OGS.length, mw = OVW - OVX - 3;
      var a = ovColAt(sc.scrollLeft), b2 = ovColAt(sc.scrollLeft + sc.clientWidth);
      var x1 = OVX + a * mw / ng, x2 = OVX + (b2 + 1) * mw / ng;
      if (x2 - x1 > mw) { x2 = OVX + mw; }
      ctx.setTransform(ovScale, 0, 0, ovScale, 0, 0);
      ctx.fillStyle = 'rgba(15,23,42,.10)';
      ctx.fillRect(x1, OVT, x2 - x1, OVBODY);
      ctx.strokeStyle = '#0f172a'; ctx.lineWidth = 1;
      ctx.strokeRect(x1 + .5, OVT - .5, Math.max(2, x2 - x1) - 1, OVBODY + 1);
    }
    /* Click or drag anywhere on the grid to bring that column into the table. */
    function ovJump(e) {
      var b = ovc.getBoundingClientRect(), mw = OVW - OVX - 3;
      var j = Math.round((e.clientX - b.left - OVX) / mw * OGS.length - .5);
      j = Math.max(0, Math.min(OGS.length - 1, j));
      sc.scrollLeft = cum[j] + colwArr[j] / 2 - sc.clientWidth / 2;
    }
    function ovRead(e) {
      var b = ovc.getBoundingClientRect();
      var x = e.clientX - b.left, y = e.clientY - b.top, n = rowsShown.length, ng = OGS.length;
      if (!n || !ng || x < OVX || y < OVT || y > OVT + OVBODY) { return; }
      var r = Math.min(n - 1, Math.floor((y - OVT) * n / OVBODY));
      var j = Math.min(ng - 1, Math.floor((x - OVX) * ng / (OVW - OVX - 3)));
      sayCell(r, j, ((MX[SP[rowsShown[r]][0]] || '').charCodeAt(j) - 48) | 0);
    }
    ovc.addEventListener('mousedown', function (e) { ovDrag = true; ovJump(e); });
    ovc.addEventListener('mousemove', function (e) {
      if (ovDrag) { ovJump(e); }
      ovRead(e);
    });
    ovc.addEventListener('mouseup', function () { ovDrag = false; });
    ovc.addEventListener('mouseleave', function () { ovDrag = false; });
    sc.addEventListener('scroll', ovWindow);

    /* ----------------------------------------------------------------- wiring */
    function redraw() {
      var mode = document.querySelector('input[name=mxmode]:checked').value;
      legend(mode);
      draw(mode, !document.getElementById('mxhq').checked);
      drawOverview(mode);
    }
    var radios = document.querySelectorAll('input[name=mxmode]');
    for (var k = 0; k < radios.length; k++) { radios[k].addEventListener('change', redraw); }
    document.getElementById('mxhq').addEventListener('change', redraw);
    document.getElementById('mxhdr').value = HDR;
    document.getElementById('mxhdr').addEventListener('change', function () {
      HDR = this.value;
      draw(document.querySelector('input[name=mxmode]:checked').value,
           !document.getElementById('mxhq').checked);
    });
    document.getElementById('mxwheel').addEventListener('change', function () {
      WHEEL = this.value;
    });
    var rz;
    window.addEventListener('resize', function () {
      clearTimeout(rz);
      rz = setTimeout(function () {
        drawOverview(document.querySelector('input[name=mxmode]:checked').value);
      }, 150);
    });
    redraw();
  })();
  </script>
  <p style="font-size:16px;color:#64748b;margin-top:10px">
    <b>*</b> marks a genome at BUSCO complete &ge;90%. The matrices for every tier are in the
    <a href="?tab=download&tier=<?php echo cc_h($tier); ?>">download</a> section.
  </p>
</div>

<?php elseif ($tab === 'species'): ?>
<div class="cc-card">
  <h2>Species</h2>
  <p>BUSCO is <code>cnidaria_odb12</code> run on the same proteome. <i>CCO single-copy</i> counts the
     published core orthogroups in which that genome has exactly one gene &mdash; the number of
     columns it can actually contribute to a concatenated alignment.</p>
  <div class="cc-scroll">
  <table class="cc" id="sptable">
    <thead><tr>
      <th>Code</th><th>Species</th><th>Class</th>
      <th class="cc-num">BUSCO C%</th><th class="cc-num">BUSCO S%</th><th class="cc-num">BUSCO D%</th>
      <th class="cc-num">CCO present</th><th class="cc-num">CCO single-copy</th><th>Quality</th>
    </tr></thead>
    <tbody>
    <?php foreach ($species as $s):
      $hq = (int)$s['busco90']; $cn = (int)$s['cnidarian'];
      $occ = round(100 * ((int)$s['n_core_og'] / max(1, ($tier_counts['extended'] ?? 1))), 1);
      $sc  = round(100 * ((int)$s['n_core_single'] / max(1, ($tier_counts['extended'] ?? 1))), 1);
    ?>
      <tr>
        <td><?php if ($cn): ?><a href="/speciesinfo.php?species=<?php echo cc_h($s['abbr1']); ?>"><?php echo cc_h($s['abbr1']); ?></a><?php else: echo cc_h($s['abbr1']); endif; ?></td>
        <td><?php echo cc_h($s['latin']); ?></td>
        <td><?php echo cc_h($s['class'] !== '' ? $s['class'] : $s['phylum']); ?></td>
        <?php /* 判据是 n_buscos，不是 pct_* 的真假：core_species 把这三列存成
                 decimal(5,2) NOT NULL DEFAULT 0.00，没跑过 BUSCO 的 7 行（AALAT、CCRUX
                 和 5 个 OUT_ 外群）在库里的值是字符串 "0.00" —— 恒为真，原来那个
                 ?: 守卫是死代码，于是「资源没记录」被印成「实测 0.0」。
                 资源自己的 species.tsv.gz 在这 7 行留的是空。
                 只改显示：data-v 仍是数值 0，页面按列排序时这 7 行仍落在原来的位置 ——
                 若把 data-v 也掏空，比较器会退化成字符串比较（见下方 rows.sort），
                 排序行为跟着变，就不止是文案改动了。 */ ?>
        <td class="cc-num" data-v="<?php echo (float)$s['pct_complete']; ?>"><?php echo ((int)$s['n_buscos'] > 0) ? number_format((float)$s['pct_complete'], 1) : '&mdash;'; ?></td>
        <td class="cc-num"><?php echo ((int)$s['n_buscos'] > 0) ? number_format((float)$s['pct_single'], 1) : '&mdash;'; ?></td>
        <td class="cc-num"><?php echo ((int)$s['n_buscos'] > 0) ? number_format((float)$s['pct_duplicated'], 1) : '&mdash;'; ?></td>
        <td class="cc-num" data-v="<?php echo (int)$s['n_core_og']; ?>"><?php echo number_format((int)$s['n_core_og']); ?> <span style="color:#64748b">(<?php echo $occ; ?>%)</span></td>
        <td class="cc-num" data-v="<?php echo (int)$s['n_core_single']; ?>"><?php echo number_format((int)$s['n_core_single']); ?> <span style="color:#64748b">(<?php echo $sc; ?>%)</span></td>
        <td><?php
          if ($hq) { echo '<span class="cc-tier t-strict">BUSCO &ge;90%</span>'; }
          elseif (!$cn) { echo '<span class="cc-tier t-extended">outgroup</span>'; }
          else { echo '<span style="color:#64748b;font-size:12px">below &ge;90% BUSCO</span>'; }
        ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<script>
(function () {
  var t = document.getElementById('sptable');
  if (!t) { return; }
  var ths = t.tHead.rows[0].cells, dir = {};
  for (var i = 0; i < ths.length; i++) {
    (function (idx) {
      ths[idx].addEventListener('click', function () {
        dir[idx] = !dir[idx];
        var tb = t.tBodies[0], rows = Array.prototype.slice.call(tb.rows);
        rows.sort(function (a, b) {
          var x = a.cells[idx].getAttribute('data-v') || a.cells[idx].textContent.trim();
          var y = b.cells[idx].getAttribute('data-v') || b.cells[idx].textContent.trim();
          var nx = parseFloat(x), ny = parseFloat(y);
          if (!isNaN(nx) && !isNaN(ny)) { return dir[idx] ? nx - ny : ny - nx; }
          return dir[idx] ? x.localeCompare(y) : y.localeCompare(x);
        });
        for (var k = 0; k < rows.length; k++) { tb.appendChild(rows[k]); }
      });
    })(i);
  }
})();
</script>

<?php elseif ($tab === 'download'): ?>
<div class="cc-card">
  <h2>Download</h2>
  <p>Everything below is plain text or FASTA. The matrices are tab-separated with one row per
     genome. The first five columns are <code>abbr1</code>, <code>latin</code>, <code>phylum</code>,
     <code>class</code> and <code>busco90</code> (the &ge;90% BUSCO flag &mdash; <i>not</i> the
     site's stricter <code>busco_summary.high_quality</code>, see Methods); the remaining columns are one per core
     orthogroup, in the same order as the orthogroup column of <code>core_og.tsv</code>.</p>

  <h3>Matrices and tables</h3>
  <div class="cc-dl">
    <a target="_blank" rel="noopener" href="/core/download/matrix_presence.tsv.gz"><b>Presence / absence matrix</b>
       <span>1/0 for all <?php echo $n_species; ?> proteomes &times; all <?php echo number_format($tier_counts['extended'] ?? 0); ?> core orthogroups. TSV, gzipped.</span></a>
    <a target="_blank" rel="noopener" href="/core/download/matrix_copynumber.tsv.gz"><b>Copy-number matrix</b>
       <span>The same grid with the actual copy number (0, 1, 2, ...) instead of a flag.</span></a>
    <a target="_blank" rel="noopener" href="/core/download/matrix_singlecopy.tsv.gz"><b>Single-copy matrix</b>
       <span>1 exactly where the orthogroup is single-copy &mdash; ready to gate a concatenation.</span></a>
    <a target="_blank" rel="noopener" href="/core/download/core_og.tsv.gz"><b>Core orthogroup table</b>
       <span>Per orthogroup: occupancy in five species sets, tier flags, outgroup coverage, best annotation.</span></a>
    <a target="_blank" rel="noopener" href="/core/download/core_members.tsv.gz"><b>Core orthogroup members</b>
       <span>Long format &mdash; one row per orthogroup &times; genome &times; gene, with copy number. The matrix above, unpivoted.</span></a>
    <a target="_blank" rel="noopener" href="/core/download/og_scores.tsv.gz"><b>All 300,793 orthogroup scores</b>
       <span>The unfiltered score table. Re-threshold to any cutoff you like.</span></a>
    <a target="_blank" rel="noopener" href="/core/download/species.tsv.gz"><b>Species table</b>
       <span>Taxonomy, BUSCO cnidaria_odb12 completeness, high-quality flag, CCO coverage.</span></a>
  </div>

  <h3>Sequences</h3>
  <div class="cc-dl">
    <a target="_blank" rel="noopener" href="/core/download/CCO_members.faa.gz"><b>Core ortholog sequences</b>
       <span>Every member of every core orthogroup, unaligned. Headers are <code>&gt;CODE|gene OG_ID</code> &mdash; the orthogroup is the second whitespace-delimited token, not part of the pipe-delimited ID.</span></a>
    <a target="_blank" rel="noopener" href="/core/download/supermatrix_strict.tar.gz"><b>Supermatrix &mdash; strict</b>
       <span>Concatenated alignment plus <code>strict.partitions.txt</code> (<code>AA, OG = start-end</code>), in one archive.</span></a>
    <a target="_blank" rel="noopener" href="/core/download/supermatrix_core.tar.gz"><b>Supermatrix &mdash; core</b>
       <span>Concatenated alignment plus <code>core.partitions.txt</code>, in one archive.</span></a>
    <a target="_blank" rel="noopener" href="/core/download/supermatrix_extended.tar.gz"><b>Supermatrix &mdash; extended</b>
       <span>Concatenated alignment plus <code>extended.partitions.txt</code>, in one archive. 54 MB uncompressed.</span></a>
  </div>
  <?php if ($dims): ?>
  <table class="cc" style="margin:6px 0 14px;max-width:560px">
    <tr><th class="nos">Supermatrix</th><th class="nos cc-num">Genomes</th>
        <th class="nos cc-num">Partitions</th><th class="nos cc-num">Columns</th></tr>
    <?php foreach (array('strict', 'core', 'extended') as $t):
      if (!isset($dims[$t])) { continue; } $d = $dims[$t]; ?>
    <tr><td><span class="cc-tier t-<?php echo cc_h($t); ?>"><?php echo cc_h($t); ?></span></td>
        <td class="cc-num"><?php echo (int)$d['taxa']; ?></td>
        <td class="cc-num"><?php echo number_format((int)$d['partitions']); ?></td>
        <td class="cc-num"><?php echo number_format((int)$d['columns']); ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <div class="cc-note">
    The supermatrices reuse OrthoFinder's MAFFT alignments, subset to one sequence per genome per
    orthogroup, with columns kept only where at least half the partition's taxa have a residue, and
    sequences dropped below half the orthogroup's median length. They are starting points, not
    finished matrices: for a published tree, trim them further and pick a substitution model.
  </div>
  <div class="cc-note warn">
    <b>The gene sets are nested; the genome sets are not.</b> Strict &sub; core &sub; extended
    holds for the orthogroups, so every gene in strict is also in core. It does <i>not</i> hold for
    the genomes, which is why the table above is not monotonic in either direction. Each tier
    admits a genome that is represented in at least half of <i>that tier's</i> partitions, and the
    strict tier only has 14 partitions to be represented in &mdash; so a genome can clear strict
    and miss core. Pick a tier for its gene set, and read the table to see which genomes come with
    it.
  </div>

  <h3>Sanity-check trees</h3>
  <p>The unrooted FastTree (<code>-nosupport</code>) trees referred to under
     <a href="?tab=methods&tier=<?php echo cc_h($tier); ?>">Methods</a>, so the monophyly claims
     there can be checked directly. No branch support, no model selection, no trimming &mdash;
     these are usability evidence, not a phylogeny.</p>
  <div class="cc-dl">
    <?php
      $trees = array();
      foreach (array('strict', 'core', 'extended') as $t) {
          if (is_file("/var/www/html/CnidoSite/core/download/$t.tree")) { $trees[] = $t; }
      }
      if (!$trees) { echo '<p style="color:#64748b">Trees are still being built.</p>'; }
      foreach ($trees as $t) {
          echo '<a target="_blank" rel="noopener" href="/core/download/' . cc_h($t) . '.tree"><b>' . cc_h($t) . '.tree</b>'
             . ' <span>Newick, ' . cc_h($TIER_LABEL[$t]) . ' tier, one line.</span></a>';
      }
    ?>
  </div>
</div>

<?php else: ?>
<div class="cc-card">
  <h2>Methods</h2>

  <h3>Input</h3>
  <p>All <?php echo $n_species; ?> proteomes in the CnidoSite OrthoFinder run (148 cnidarians plus
     five non-cnidarian outgroups: four sponges and a ctenophore), clustered into 300,793
     orthogroups. Orthogroup membership is taken as OrthoFinder produced it; the only correction
     applied is that copy number counts <i>distinct genes</i>, collapsing multiple transcripts of
     one gene model (a trailing <code>.t1</code>, <code>.t2</code>, ...) into a single copy. That
     matters more than it sounds: without it a genome with two isoforms of a single-copy gene looks
     duplicated.</p>

  <h3>High-quality genomes</h3>
  <p>A cnidarian genome enters the high-quality set when BUSCO <code>cnidaria_odb12</code> reports
     &ge;90% complete against its proteome. That yields <b><?php echo $n_hq; ?></b> genomes
     (<?php
       $cl = array();
       $r2 = mysqli_query($conn, "SELECT class, COUNT(*) n FROM core_species
                                  WHERE busco90=1 GROUP BY class ORDER BY n DESC");
       while ($r2 && ($x = mysqli_fetch_assoc($r2))) { $cl[] = $x['n'] . ' ' . $x['class']; }
       echo cc_h(implode(', ', $cl));
     ?>). Orthogroups are scored against this set. The two outgroup phyla are scored separately and
     never counted as cnidarian.</p>
  <div class="cc-note warn">
    <b>Not the same as <code>busco_summary.high_quality</code>.</b> The site's own
    <code>high_quality</code> flag is a separate and much stricter set &mdash; 15 genomes, all
    &ge;91% &mdash; used elsewhere. This resource deliberately uses the 90% completeness cutoff
    instead and stores it in its own column, <code>core_species.busco90</code>, so that a query
    joining the two tables cannot silently pick up the wrong set. Of the 15 flagged genomes, 14
    are inside these <?php echo $n_hq; ?>; the exception is <i>Pachycerianthus multiplicatus</i>,
    which is not part of the OrthoFinder run at all and so is absent from this resource entirely.
  </div>

  <h3>Tiers</h3>
  <p>An orthogroup's <b>single-copy fraction</b> is the share of the high-quality genomes in which
     it has exactly one gene. An orthogroup enters a tier when that fraction reaches the tier's
     cutoff: <span class="cc-tier t-strict">strict</span> 90%,
     <span class="cc-tier t-core">core</span> 80%,
     <span class="cc-tier t-extended">extended</span> 70%. Presence is implied &mdash; a single-copy
     genome is a present genome &mdash; but presence across all <?php echo $n_cnid; ?> cnidarians is
     reported separately, because a gene can be core in the high-quality set and still be missing
     from the more fragmented assemblies.</p>

  <h3>Rooting</h3>
  <p>The five outgroups are the only way to root a matrix built from this resource, and they are
     single-copy in only part of each tier, so <code>core_og.tsv</code> carries an
     <code>outgroup_single</code> column. Filter on <code>outgroup_single &gt;= 3</code> to get the
     rootable subset.</p>

  <h3>Validation</h3>
  <p>Two checks are reported on the overview tab. Against BUSCO: the majority of the
     <code>cnidaria_odb12</code> groups that reach &ge;90% single-copy over the high-quality genomes
     have a member inside a CCO orthogroup, and a large share of CCO member genes carry a BUSCO
     assignment &mdash; the CCO set is enriched for the conserved genes BUSCO tracks, not a
     disjoint set.</p>
  <p>Against taxonomy: each supermatrix was run through FastTree (<code>-nosupport</code>) as a
     usability check, and the class-level clades were scored for monophyly. Cubozoa, Hydrozoa,
     Octocorallia and Scyphozoa come out monophyletic on all three tiers, and the five outgroup
     proteomes are monophyletic on the core tier (130 taxa). The strict tier (135 taxa) and the
     extended tier (144 taxa) additionally recover Myxozoa, but on those two the outgroups do
     <i>not</i> stay together. Hexacorallia is not recovered on any tier, and Staurozoa is
     represented by a single genome, so its placement means nothing. These are approximate,
     unrooted, support-free trees of a matrix that has not been trimmed or model-selected &mdash;
     read them as evidence that the resource is usable, never as a phylogeny. Deep relationships
     among the classes should not be taken from them. The trees themselves are in the download
     section.</p>

  <h3>Citing this</h3>
  <p>Please cite CnidoSite and the OrthoFinder run described on the
     <a href="/genefamily.php">gene family</a> pages. The tier cutoffs, BUSCO comparison and the
     presence/absence matrix are described in the manuscript's core-ortholog section.</p>
</div>
<?php endif; ?>

</div><!-- #column -->
</div><!-- #templatemo_content -->
</div><!-- #tempatemo_content_wrapper -->

<?php
/* The site footer: the lab and institutional affiliations, the release stamp and
   the visitor map.  It is printed from the shared component rather than retyped,
   which is how every other CnidoSite page does it, so it cannot drift.
   `include "../Webpage_components.php"` is what phylotree/index.php writes; the
   path resolves against the including file's own directory, and /core/ sits at
   the same depth as /phylotree/, so it lands on the same file.  That file uses
   __DIR__ internally, so it stays correct from a subdirectory.  The footer's
   styling (.guide, #templatemo_footer_wrapper, .section_w920, .cleaner) is all
   in /templatemo_style.css, which this page already links. */
include __DIR__ . '/../Webpage_components.php';
print $footer;
?>
</body>
</html>
