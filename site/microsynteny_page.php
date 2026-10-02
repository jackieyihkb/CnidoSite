<?php
/* ---------------------------------------------------------------------------
 * microsynteny_page.php -- page chrome for the two Microsynteny Analysis pages.
 *
 * The head / navigation / footer markup is shared with the macrosynteny module
 * through macrosynteny_page.php: this site inlines its navigation into every
 * page, and adding a 79th copy just to change a title would guarantee it drifts.
 * msr_page_head() gained two optional arguments (keywords, extra <head> content)
 * for that reuse; its defaults keep the macrosynteny pages byte-identical.
 * ------------------------------------------------------------------------- */

require_once __DIR__ . '/macrosynteny_page.php';
require_once __DIR__ . '/macrosynteny_db.php';      /* msr_h() */

define('MTS_IMG_DIR', __DIR__ . '/images/microsynteny');
define('MTS_IMG_URL', '/images/microsynteny');

function mts_page_head($title, $desc) {
    $css = (int)@filemtime(__DIR__ . '/css/microsynteny.css');
    msr_page_head(
        $title,
        $desc,
        'Cnidaria, microsynteny, MCScanX, collinear blocks, chromosome, circos, '
        . 'dotplot, synteny conservation, genome evolution',
        '<link href="/css/microsynteny.css?v=' . $css . '" rel="stylesheet" type="text/css" />' . "\n"
    );
}

function mts_page_foot() {
    msr_page_foot();
}

/* The overview JSON, decoded once per request. */
function mts_overview() {
    static $ov = null;
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $p = __DIR__ . '/data/microsynteny/overview.json';
        if (is_readable($p)) {
            $ov = json_decode(file_get_contents($p), true);
        }
    }
    return $ov;
}

/* The full 144-species / 10,296-pair statistics, decoded once per request.
   Only the detail page needs it, and only when the pair is outside the
   chromosome-level set. */
function mts_pairs144() {
    static $p = null;
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $f = __DIR__ . '/data/microsynteny/pairs144.json';
        if (is_readable($f)) {
            $p = json_decode(file_get_contents($f), true);
        }
    }
    return $p;
}

/* Look a pair up in the 144-species table.  Returns the row (a=0, b=1,
   genes=2, collinear=3, pct=4, blocks=5, avg=6, median=7) or null. */
function mts_lookup144($x, $y) {
    $d = mts_pairs144();
    if (!$d || !isset($d['rows'])) { return null; }
    foreach ($d['rows'] as $r) {
        if (($r[0] === $x && $r[1] === $y) || ($r[0] === $y && $r[1] === $x)) { return $r; }
    }
    return null;
}

function mts_species_index() {
    $out = array();
    $ov = mts_overview();
    if ($ov && isset($ov['species'])) {
        foreach ($ov['species'] as $s) { $out[$s['code']] = $s; }
    }
    return $out;
}

/* Human-readable status, shared by both pages. */
function mts_status_text($s) {
    $map = array(
        'rendered'                    => 'chromosome-level blocks rendered',
        'no_detected_chromosome_block'=> 'no chromosome-level block detected',
        'no_detected_block'           => 'no conserved block detected',
    );
    if (isset($map[$s])) { return $map[$s]; }
    return str_replace('_', ' ', (string)$s);
}

/* URL of the figure directory for a pair, whichever rendering order exists. */
function mts_fig_dir($x, $y) {
    foreach (array($x . '_' . $y, $y . '_' . $x) as $cand) {
        if (is_dir(MTS_IMG_DIR . '/' . $cand)) { return $cand; }
    }
    return '';
}

/* ------------------------------------------------------------------ downloads
 * The collinearity data lives in download/microsynteny/ (flat, one file per
 * pair).  MCScanX stored each pair in the order it processed them, which is not
 * always the sorted order the pages use, so both orders are probed. */
define('MTS_DL_DIR', __DIR__ . '/download/microsynteny');
define('MTS_DL_URL', '/download/microsynteny');

function mts_dl_pair($x, $y) {
    foreach (array($x . '_' . $y, $y . '_' . $x) as $cand) {
        if (is_file(MTS_DL_DIR . '/pairs/' . $cand . '.collinearity')) { return $cand; }
    }
    return '';
}
function mts_fsize($bytes) {
    if ($bytes >= 1048576) { return number_format($bytes / 1048576, 1) . ' MB'; }
    if ($bytes >= 1024)    { return number_format($bytes / 1024, 0) . ' KB'; }
    return $bytes . ' B';
}
/* Size of a table, or '' when the file is not deployed. */
function mts_dl_table($name) {
    $p = MTS_DL_DIR . '/tables/' . $name;
    return is_file($p) ? mts_fsize(filesize($p)) : '';
}
