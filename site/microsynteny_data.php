<?php
/* microsynteny_data.php -- JSON feed for the Microsynteny Analysis pages.
 *
 * The site's data/ directory is deliberately not served over HTTP
 * (data/.htaccess: "Require all denied"), so the JSON that the browser needs is
 * handed out by this endpoint instead -- same arrangement as the reference
 * implementation's heatmap.php.
 *
 *   ?set=overview  the 22 chromosome-level species: two matrices + pair records
 *   ?set=pairs144  all 10,296 pairs across the 144 analysed species
 */
header('Content-Type: application/json; charset=utf-8');

$sets = array(
    'overview' => 'overview.json',
    'pairs144' => 'pairs144.json',
);
$key = isset($_GET['set']) ? (string)$_GET['set'] : '';
if (!isset($sets[$key])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array('error' => 'unknown set'));
    exit;
}
$path = __DIR__ . '/data/microsynteny/' . $sets[$key];
if (!is_readable($path)) {
    header('HTTP/1.1 503 Service Unavailable');
    echo json_encode(array('error' => 'data not available'));
    exit;
}

/* The files only change when the analysis is re-imported, so let the browser
   cache them and revalidate with the file mtime. */
$mtime = filemtime($path);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Cache-Control: public, max-age=86400');
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
    && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}
header('Content-Length: ' . filesize($path));
readfile($path);
