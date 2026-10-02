<?php
/* dbq.php — run one SELECT from the command line and print the result as TSV.
 *
 *   php scripts/setup/dbq.php "SELECT COUNT(*) FROM classfy"
 *   php scripts/setup/dbq.php -f query.sql
 *
 * The site has no shared connection function: every page constructs its own
 * mysqli handle. This helper exists so that the SQL quoted in docs/ can be
 * pasted and run without a MySQL client, and so that the numbers in
 * docs/DATA_OVERVIEW.md can be re-derived on a fresh local copy.
 *
 * Credentials are never written into this file and never printed. They come
 * from the environment, in the same four variables the site itself reads:
 *
 *   CNIDO_DB_HOST  CNIDO_DB_USER  CNIDO_DB_PASS  CNIDO_DB_NAME
 *
 * If those are unset, the helper falls back to reading them out of
 * site/includes/state.php, which is how the production host is configured.
 * Both spellings of that file's connection line are understood:
 *
 *   - the archived form, where each argument is `getenv('CNIDO_DB_...')`
 *     followed by a `?:` default; and
 *   - the pre-archive form, where all four arguments are quoted string
 *     literals. Parsing that is a fallback of last resort — it is exactly
 *     the construction this repository exists to move away from.
 *
 * The password is deliberately not validated against anything. If the values
 * are wrong, mysqli says so and this exits 2.
 */

$stateFile = null;
foreach (array(
    __DIR__ . '/../../site/includes/state.php',   // inside the repository
    '/var/www/html/CnidoSite/includes/state.php', // a production install
) as $cand) {
    if (is_readable($cand)) { $stateFile = $cand; break; }
}

/* Pull one connection parameter out of the environment, else out of the
 * state.php text. Returns null if neither source has it. */
function dbq_param($name, $fallback, $src)
{
    $v = getenv($name);
    if ($v !== false && $v !== '') {
        return $v;
    }
    if ($src === null) {
        return $fallback;
    }
    /* `getenv('CNIDO_DB_HOST') ?: 'localhost'` — take the default after the
     * ?: . Anchored on the variable name so the four cannot be confused. */
    if (preg_match("/getenv\(\s*'" . preg_quote($name, '/') . "'\s*\)\s*\?:\s*'([^']*)'/",
                   $src, $m)) {
        return $m[1];
    }
    return $fallback;
}

$src = $stateFile ? @file_get_contents($stateFile) : null;
if ($src === false) {
    $src = null;
}

$host = dbq_param('CNIDO_DB_HOST', null, $src);
$user = dbq_param('CNIDO_DB_USER', null, $src);
$pass = dbq_param('CNIDO_DB_PASS', null, $src);
$name = dbq_param('CNIDO_DB_NAME', null, $src);

/* Nothing in the environment and nothing usable in state.php: if state.php
 * still carries the literal form, use that. Kept last because parsing a
 * source file for a password is exactly what the archive removes. */
if ($src !== null && ($host === null || $user === null || $name === null)) {
    if (preg_match("/new\s+mysqli\s*\(\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*\)/",
                   $src, $m)) {
        if ($host === null) { $host = $m[1]; }
        if ($user === null) { $user = $m[2]; }
        if ($pass === null) { $pass = $m[3]; }
        if ($name === null) { $name = $m[4]; }
    }
}

if ($host === null || $user === null || $name === null) {
    fwrite(STDERR, "dbq: cannot determine the connection.\n"
        . "     Set CNIDO_DB_HOST / CNIDO_DB_USER / CNIDO_DB_PASS / CNIDO_DB_NAME,\n"
        . "     or point this at a site/includes/state.php that carries them.\n");
    exit(2);
}
if ($pass === null) {
    $pass = '';                 // an empty password is legitimate
}

$conn = @new mysqli($host, $user, $pass, $name);
if ($conn->connect_errno) {
    fwrite(STDERR, "dbq: connection failed (" . $conn->connect_errno . ")\n");
    exit(2);
}
$conn->set_charset('utf8mb4');

$sql = null;
if (isset($argv[1]) && $argv[1] === '-f') {
    $sql = isset($argv[2]) ? @file_get_contents($argv[2]) : false;
} elseif (isset($argv[1])) {
    $sql = $argv[1];
}
if ($sql === null || $sql === false || trim($sql) === '') {
    fwrite(STDERR, "usage: php dbq.php \"<SELECT ...>\"   |   php dbq.php -f <file.sql>\n");
    exit(2);
}

$res = $conn->query($sql);
if ($res === false) {
    fwrite(STDERR, "dbq: " . $conn->error . "\n");
    exit(1);
}

if ($res === true) {                    // not a result set
    echo "OK\n";
    exit(0);
}

$cols = array();
while ($f = $res->fetch_field()) {
    $cols[] = $f->name;
}
echo implode("\t", $cols), "\n";
while ($row = $res->fetch_row()) {
    $out = array();
    foreach ($row as $v) {
        $out[] = $v === null ? 'NULL' : str_replace(array("\t", "\n", "\r"), ' ', $v);
    }
    echo implode("\t", $out), "\n";
}
