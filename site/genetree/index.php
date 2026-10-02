<?php
/* =========================================================================
 * CnidoSite — Gene Family Tree
 *
 * Sibling of /phylotree/ (the species tree): same phylotree.js + d3 viewer, same
 * look, same controls.  Where that page draws one tree over 153 species, this one
 * draws the gene tree of a single orthogroup, at /genetree/?family=OG0022972.
 *
 * Why a separate directory and not a page next to genefamily.php: phylotree.js
 * wants jQuery 3.2.1 and the gene-family pages load jQuery 1.10.2 for their
 * Highcharts includes.  Giving the viewer its own document avoids the two fighting.
 *
 * The tree itself comes from og_family_tree, where the Newick is stored gzipped and
 * base64-encoded (see work/genetree/pack_trees.py).  Families over 3000 tips are
 * served species-collapsed -- the largest has 35,546 tips across 144 species, which
 * is not something to hand to a browser -- and the full tree stays downloadable.
 *
 * Order matters at the bottom of this file: the footer is printed LAST, after the
 * viewer script.  History: the footer used to carry the mapmyvisitors widget as a
 * plain synchronous <script src>, which stopped the HTML parser until its host
 * answered -- with the viewer script after it the tree waited for a visitor-map
 * domain (measured 210 ms cut off, 921 ms slow to fail, 3100 ms delayed 3 s).
 * As of 2026-09-28 that widget is injected after `load` instead of parsed, so it no
 * longer blocks anything; the footer stays last regardless, and nothing below it
 * needs it.
 * ========================================================================= */

$TAX_FILE      = __DIR__ . '/../phylotree/data/taxonomy.json';
$SPECIES_URL   = '/speciesinfo.php';
$GENE_URL      = '/gene_detail.php';
$FAMILY_URL    = '/genefamily_result.php';
$COLLAPSE_OVER = 3000;   // keep in step with COLLAPSE_OVER in pack_trees.py
/* The OrthoFinder alignments, gzipped by work/genetree/pack_aln.sh.  Under data/ because
   Apache refuses to serve that tree (it holds the 53 GB of source proteomes) and because
   download/ is flat and only reachable through download_fun.php -- neither can host
   67,795 files.  This page streams them instead, one family at a time. */
$ALN_DIR       = '/var/www/html/CnidoSite/data/og_aln';

function gt_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Stream the records of a gzipped FASTA.  $on_record($header, $sequence) is called once
   per record, so neither pass over a 74 MB alignment has to hold more than one sequence.
   Passing a closure rather than returning an array keeps the memory flat for the biggest
   families, which are exactly the ones whose download matters most. */
function aln_stream($gz, $on_record) {
    $fh = @gzopen($gz, 'rb');
    if ($fh === false) { return false; }
    $hdr = null;
    $seq = '';
    while (!gzeof($fh)) {
        $line = fgets($fh);
        if ($line === false) { break; }
        $line = rtrim($line, "\r\n");
        if ($line === '') { continue; }
        if ($line[0] === '>') {
            if ($hdr !== null) { $on_record($hdr, $seq); }
            $hdr = substr($line, 1);
            $seq = '';
            continue;
        }
        $seq .= $line;
    }
    gzclose($fh);
    if ($hdr !== null) { $on_record($hdr, $seq); }
    return true;
}

$TAXA = array();
$__raw = @file_get_contents($TAX_FILE);
if ($__raw !== false) {
    $__j = json_decode($__raw, true);
    if (is_array($__j) && isset($__j['taxa']) && is_array($__j['taxa'])) { $TAXA = $__j['taxa']; }
}
/* Longest name first: a tip is "<species>_<abbr1>_<gene>" and the species part is the
   only variable-length piece, so the longest key that prefixes the tip is the species.
   Sorting once here keeps the per-tip scan dumb. */
$KEYS = array_keys($TAXA);
usort($KEYS, function ($a, $b) { return strlen($b) - strlen($a); });

/* A tip label -> the pieces the page needs, or all-null when it is not a gene tip.
   Outgroup tips are "OUT_<Species>_OUT_<Species>+<accession>": strip the OUT_ before
   matching, and split on the '+' rather than an underscore, because the "abbr" there
   contains underscores of its own. */
function gt_tip($label, $KEYS, $TAXA) {
    // PHP functions do not see file-scope variables.  Without this the two URLs are null
    // inside here and the built links come out as bare "?species=AMILL", which the browser
    // resolves against /genetree/ and lands on this page instead of the species entry.
    global $SPECIES_URL, $GENE_URL;
    $tok = strtok(trim($label), " \t");
    if ($tok === false || $tok === '') { return null; }
    $out = array('label' => $tok, 'sp' => '', 'abbr' => '', 'gene' => '',
                 'disp' => $tok, 'abbr1' => '', 'cls' => '', 'ord' => '', 'fam' => '',
                 'gen' => '', 'out' => false, 'url' => '', 'gurl' => '', 'coll' => 0);
    $t = $tok;
    if (strpos($t, 'OUT_') === 0) { $out['out'] = true; $t = substr($t, 4); }
    foreach ($KEYS as $k) {
        if (strpos($t, $k . '_') !== 0) { continue; }
        $rest = substr($t, strlen($k) + 1);
        if ($out['out']) {
            $p = strpos($rest, '+');
            if ($p === false) { break; }
            $out['sp'] = $k;
            $out['abbr'] = substr($rest, 0, $p);
            $out['gene'] = substr($rest, $p + 1);
        } else {
            $p = strpos($rest, '_');
            if ($p === false || $p === 0) { break; }
            $out['sp'] = $k;
            $out['abbr'] = substr($rest, 0, $p);
            $out['gene'] = substr($rest, $p + 1);
        }
        if (strpos($out['gene'], 'COLLAPSEDx') === 0) {
            $out['coll'] = (int)substr($out['gene'], 10);
            $out['gene'] = '';
        }
        if (isset($TAXA[$k])) {
            $x = $TAXA[$k];
            $out['disp']  = isset($x['display']) ? $x['display'] : $k;
            $out['abbr1'] = isset($x['abbr1']) ? $x['abbr1'] : '';
            $out['cls']   = isset($x['class']) ? $x['class'] : '';
            $out['ord']   = isset($x['order']) ? $x['order'] : '';
            $out['fam']   = isset($x['family']) ? $x['family'] : '';
            $out['gen']   = isset($x['genus']) ? $x['genus'] : '';
            if ($out['abbr1'] !== '' && empty($x['outgroup'])) {
                $out['url'] = $SPECIES_URL . '?species=' . urlencode($out['abbr1']);
            }
        }
        if ($out['gene'] !== '') {
            $out['gurl'] = $GENE_URL . '?gene=' . urlencode($out['gene']) .
                           '&species=' . urlencode($out['sp']);
        }
        return $out;
    }
    return $out;
}

/* ------------------------------------------------------------------ Newick helpers
 * Only needed for the per-species expansion endpoint below: the collapsed tree a big
 * family is drawn with keeps one tip per species, and expanding one of those tips means
 * cutting the same species back out of the full tree.  The pruning rule is deliberately
 * the mirror image of pack_trees.py's collapse(), so what you get by clicking is exactly
 * what that function folded away.
 */
function nwk_parse($s) {
    $pos = 0;
    return nwk_node($s, $pos);
}

function nwk_node($s, &$pos) {
    $len = strlen($s);
    $node = array('name' => getenv('CNIDO_MSR_DB_NAME') ?: 'jackie_db', 'len' => '', 'children' => array());
    if ($pos < $len && $s[$pos] === '(') {
        $pos++;
        while (true) {
            $node['children'][] = nwk_node($s, $pos);
            if ($pos < $len && $s[$pos] === ',') { $pos++; continue; }
            $pos++;                       // the ')'
            break;
        }
    }
    $st = $pos;
    while ($pos < $len && strpos('(),:;', $s[$pos]) === false) { $pos++; }
    $node['name'] = trim(substr($s, $st, $pos - $st), " \t'\"");
    if ($pos < $len && $s[$pos] === ':') {
        $pos++;
        $st = $pos;
        while ($pos < $len && strpos('(),;', $s[$pos]) === false) { $pos++; }
        $node['len'] = trim(substr($s, $st, $pos - $st));
    }
    return $node;
}

function nwk_render($n) {
    if (!empty($n['children'])) {
        $parts = array();
        foreach ($n['children'] as $c) { $parts[] = nwk_render($c); }
        return '(' . implode(',', $parts) . ')' . $n['name']
             . ($n['len'] !== '' ? ':' . $n['len'] : '');
    }
    return $n['name'] . ($n['len'] !== '' ? ':' . $n['len'] : '');
}

/* Keep only the leaves belonging to $want, collecting their tip metadata on the way. */
function nwk_keep_species($n, $want, $KEYS, $TAXA, &$tips) {
    if (empty($n['children'])) {
        $t = gt_tip($n['name'], $KEYS, $TAXA);
        if ($t === null || $t['sp'] !== $want || $t['coll'] > 0) { return null; }
        $tips[$n['name']] = $t;
        return $n;
    }
    $kids = array();
    foreach ($n['children'] as $c) {
        $k = nwk_keep_species($c, $want, $KEYS, $TAXA, $tips);
        if ($k !== null) { $kids[] = $k; }
    }
    if (!$kids) { return null; }
    $n['children'] = $kids;
    return $n;
}

/* Pruning leaves nodes with a single child behind; a cladogram should not show them. */
function nwk_suppress($n) {
    foreach ($n['children'] as $i => $c) { $n['children'][$i] = nwk_suppress($c); }
    while (count($n['children']) === 1) {
        $only = $n['children'][0];
        if ($n['len'] === '') { $n['len'] = $only['len']; }
        $n = $only;
    }
    return $n;
}

$family = isset($_GET['family']) ? trim($_GET['family']) : '';
/* Optional: the reader arrived from a gene page, wanting to see that gene and its
   orthologues.  Both are needed or neither is used -- the abbreviation is what makes the
   gene id unambiguous across the 153 proteomes. */
$qGene = isset($_GET['gene']) ? trim($_GET['gene']) : '';
$qAbbr = isset($_GET['abbr']) ? trim($_GET['abbr']) : '';
$fam = null;
$tree = null;
$nwk = '';          // what the page draws (collapsed when the family is too big)
$nwk_full = '';     // always the whole tree, for the download
$is_collapsed = false;
$TIPS = array();
$DUP = array();         // path key -> [support, type] on the DRAWN tree
$dup_resolved = false;  // did this family get OrthoFinder's duplication calling at all?
$qSiblings = array();   // the queried gene's same-species orthogroup members
$qSibTotal = 0;

if ($family !== '' && preg_match('/^OG[0-9]+$/', $family)) {
    $conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    if (!$conn->connect_error) {
        $conn->set_charset('utf8mb4');
        $f = $conn->real_escape_string($family);

        $q = mysqli_query($conn, "SELECT og, n_genes, n_seqs, n_species, best_source,
                                         best_term, best_name, best_desc, best_tier, best_pct
                                  FROM og_family WHERE og = '$f' LIMIT 1");
        if ($q && ($r = mysqli_fetch_assoc($q))) { $fam = $r; }

        $q = mysqli_query($conn, "SELECT n_tips, n_species, n_outgroup, tree_gz, tree_col_gz,
                                         n_dup, dup_gz
                                  FROM og_family_tree WHERE og = '$f' LIMIT 1");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            /* A collapsed tree is present only when the column holds real base64.  The
               load writes SQL NULL as \N, but a stray literal "NULL" would decode to
               nothing and take the whole page down with it, so test the shape rather than
               trusting the column to be clean. */
            $full = @gzdecode(@base64_decode($r['tree_gz'], true));
            if ($full !== false && $full !== '') { $nwk_full = $full; }

            $col = $r['tree_col_gz'];
            $has_col = ($col !== null && $col !== '' && $col !== 'NULL' && strlen($col) > 16);
            if ($has_col) {
                $plain = @gzdecode(@base64_decode($col, true));
                if ($plain !== false && $plain !== '') { $nwk = $plain; }
            }
            $is_collapsed = ($nwk !== '' && $nwk !== $nwk_full);
            if ($nwk === '') { $nwk = $nwk_full; }

            /* Duplication markers, already resolved to the topology drawn above (the
               collapsed one, for a big family) by work/genetree/annotate_dups.py.  An
               empty array means "the tree resolved and there is no duplication in it",
               which is a promise of one-to-one orthology; NULL means the family needed a
               FastTree tree of our own and has no duplication calling at all.  The page
               words those two very differently, so keep the distinction. */
            $dgz = $r['dup_gz'];
            if ($dgz !== null && $dgz !== '' && $dgz !== 'NULL' && strlen($dgz) > 16) {
                $plain = @gzdecode(@base64_decode($dgz, true));
                if ($plain !== false && $plain !== '') {
                    $d = json_decode($plain, true);
                    if (is_array($d)) { $DUP = $d; $dup_resolved = true; }
                }
            }
            $tree = $r;
        }

        /* The queried gene's own species, so the panel can list its paralogues without the
           browser having to hold every member of a 35,000-gene family.  OrthoFinder puts a
           species' genes in one orthogroup when they are in-paralogues of each other, so
           "same orthogroup, same species" is the definition, not a heuristic. */
        if ($qGene !== '' && $qAbbr !== '' && $tree) {
            $qa = $conn->real_escape_string($qAbbr);
            $qg = $conn->real_escape_string($qGene);
            $qq = mysqli_query($conn, "SELECT gene FROM og_family_member
                                       WHERE og = '$f' AND abbr = '$qa'
                                       ORDER BY gene LIMIT 400");
            if ($qq) { while ($x = mysqli_fetch_row($qq)) { $qSiblings[] = $x[0]; } }
            $qq = mysqli_query($conn, "SELECT COUNT(*) FROM og_family_member
                                       WHERE og = '$f' AND abbr = '$qa'");
            if ($qq && ($x = mysqli_fetch_row($qq))) { $qSibTotal = (int)$x[0]; }
        }
        $conn->close();
    }
}

/* Hand back one species' genes out of a collapsed family, so a COLLAPSEDxN tip can be
   opened in place instead of only being readable in the download.  Returns the subtree
   together with the tip metadata the page needs to colour and link the new leaves --
   that metadata is not in the page payload, because for a collapsed family the payload
   only describes the one representative per species. */
if (isset($_GET['expand'])) {
    header('Content-Type: application/json; charset=utf-8');
    $want = (string)$_GET['expand'];
    if ($nwk_full === '' || $want === '') {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(array('ok' => false, 'error' => 'no tree stored for this family'));
        exit;
    }
    $tips = array();
    $sub = nwk_keep_species(nwk_parse($nwk_full), $want, $KEYS, $TAXA, $tips);
    if ($sub === null || !$tips) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(array('ok' => false, 'error' => 'no genes of that species in this family'));
        exit;
    }
    $sub = nwk_suppress($sub);
    echo json_encode(array('ok' => true, 'species' => $want, 'n' => count($tips),
                           'newick' => nwk_render($sub) . ';', 'tips' => $tips));
    exit;
}

/* The drawn tree for a big family is species-collapsed, so serving it as the download
   would quietly hand back a reduced tree under a "full tree" label.  This endpoint always
   returns the untruncated Newick, which is what the button and the header badge promise.
   It has to run before any output so the headers can still be set. */
if (isset($_GET['download']) && $_GET['download'] === 'full') {
    if ($nwk_full === '') {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo "no tree stored for " . ($family === '' ? '(no family given)' : $family) . "\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $family . '_gene_tree.nwk"');
    header('Content-Length: ' . strlen($nwk_full));
    echo $nwk_full;
    exit;
}

/* The alignment and the sequences behind this family, out of data/og_aln/.
   Like the full-tree download above, this has to answer before anything is echoed so the
   headers can still be set.  $family has already been matched against /^OG[0-9]+$/, and it
   is the only thing that reaches the filesystem, so the endpoint cannot be walked out of
   its directory -- which matters, because data/ is not readable over HTTP and this is the
   one way in. */
if (isset($_GET['dl']) && $family !== '' && preg_match('/^OG[0-9]+$/', $family)) {
    $what = (string)$_GET['dl'];
    $gz = $ALN_DIR . '/' . $family . '.aln.fa.gz';
    if (($what === 'aln' || $what === 'seq') && !is_readable($gz)) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo "no alignment stored for $family\n";
        exit;
    }
    if ($what === 'aln') {
        // Straight from disk: gzipped FASTA, byte-identical to OrthoFinder's own file.
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $family . '_alignment.fa.gz"');
        header('Content-Length: ' . filesize($gz));
        readfile($gz);
        exit;
    }
    if ($what === 'seq') {
        /* The same sequences with every gap character removed -- what you want if you are
           going to realign them yourself or search them against something.  Gaps are
           dropped per sequence rather than by masking all-gap columns: a column that is a
           gap in one sequence and a residue in another is a column of the *alignment*, and
           "sequences" means no alignment at all. */
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $family . '_sequences.fa"');
        aln_stream($gz, function ($h, $s) {
            echo '>', $h, "\n", chunk_split(str_replace(array('-', '.'), '', $s), 60, "\n");
        });
        exit;
    }
}

if ($nwk !== '') {
    /* One entry per distinct tip, built here rather than shipping all 153 taxa. */
    if (preg_match_all('/[(,]([^(),:;]+)/', $nwk, $m)) {
        foreach (array_unique($m[1]) as $lab) {
            $lab = trim($lab);
            if ($lab === '') { continue; }
            $t = gt_tip($lab, $KEYS, $TAXA);
            if ($t !== null) { $TIPS[$t['label']] = $t; }
        }
    }
}

$payload = array(
    'family'    => $family,
    'newick'    => $nwk,
    'tips'      => $TIPS,
    'collapsed' => $is_collapsed,
    'nTips'     => $tree ? (int)$tree['n_tips'] : 0,
    'nSpecies'  => $tree ? (int)$tree['n_species'] : 0,
    'nOut'      => $tree ? (int)$tree['n_outgroup'] : 0,
    'collapseOver' => $COLLAPSE_OVER,
    'dup'       => $DUP,                    // [[pathkey, support, 'T'|'N'|'C'], ...]
    'dupResolved' => $dup_resolved,
    'gene'      => $qGene,
    'abbr'      => $qAbbr,
    'qGenes'    => $qSiblings,              // same-species members (incl. the query)
    'qGeneTotal' => $qSibTotal,
);
?>
<!DOCTYPE html>
<html>
<head>
<script src="/js/rwd-tables.js" defer></script>
<title><?php echo $family !== '' ? gt_h($family) . ' gene tree' : 'Gene tree'; ?> - CnidoSite</title>
<meta name="description" content="Interactive gene tree for a selected orthogroup, built from the cnidarian protein sequences held in CnidoSite" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="../phylotree/font-awesome.css">
<link rel="stylesheet" href="../phylotree/bootstrap-icons.min.css">
<script src="../phylotree/jquery-3.2.1.min.js"></script>
<script src="../phylotree/d3.min.js"></script>
<script src="../phylotree/bundle.js"></script>
<link href="../phylotree/phylotree.css" rel="stylesheet" />
<style>
.gt-note{background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #3b82f6;border-radius:10px;padding:14px 18px;margin:0 0 14px;color:#334155;font-size:16px;line-height:1.7}
.gt-note b{color:#1e293b}
.gt-note a{color:#2563eb;text-decoration:none}
.gt-note a:hover{text-decoration:underline}
<?php /* 树卡是这页的主视觉：投影比控件面板重一档、边框更深，让眼睛先落到树上。
   控件面板反过来压轻（见下面的 .control-panel），两块白卡不再互相抢。 */ ?>
.gt-card{background:#fff;border:1px solid #cbd5e1;border-radius:12px;
  box-shadow:0 10px 30px rgba(15,23,42,.10);margin:0;overflow:hidden}
<?php /* .gt-card-head / .gt-badges / .gt-badge(.gt-warn|.gt-ok|.gt-out) 随旧页头一起删掉了：
   现在全站只剩下面 .gt-hero 一套页头，statistics 行改由 .gt-facts 承担。 */ ?>

<?php /* ---------- 家族页头（.gt-hero） -------------------------------------------
 * 这一页原来有两块页头：一段 5 行的散文（paleo-intro），紧接着一张卡片又把
 * 家族编号、统计、徽章说一遍。同样的 OG 号出现两次，而树被压到 y≈984 —— 也就
 * 是首屏（常见视口 800～950px）完全看不到。这一页的主角是树，不是这些话。
 * 所以：两块并成一块（与 genefamily_result.php 的 .fam-hero 同一套外观，两个
 * 页面来回跳时不换语言），散文收进下面控件区的 <details>，统计改成一行事实。
 * 注意 a 的配色必须带 .gt-hero 前缀：templatemo_style.css 里 `a:link,a:visited
 * {color:#1d4ed8}` 是 (0,1,1)，裸类选择器压不住，实心按钮上的字会被染成链接蓝。
 *
 * 底色 2026-09-23 从深色渐变改成白卡（用户：「不要用深色，用其他的背景颜色」）。
 * 它和 .fam-hero 是同一块东西的两个副本，一起改才不出现"跳到家族页换了个配色"。
 * 注意 .gt-back 和 .gt-status-warn/.gt-status-ok 原本都是**为深底挑的**（半透明白
 * 描边、#fcd34d 亮黄、#86efac 亮绿）—— 底色一改白，亮黄亮绿直接糊在纸上看不见，
 * 必须同步换成 #92400e / #15803d，这四处是一处改动。
 * ------------------------------------------------------------------------- */ ?>
.gt-hero{position:relative;overflow:hidden;background:#fff;color:#1e293b;
  border:1px solid #e2e8f0;padding:18px 30px 16px 34px;border-radius:10px;margin:0 0 18px}
<?php /* 左侧渐变竖条：整块页头唯一的一处"色"。白卡上如果只有灰字灰线，它就只是正文的
   一块白底；4px 竖条是零成本的存在感，也给"这一页讲的是哪一个家族"一个锚点。
   overflow:hidden 让竖条自己裁进圆角，不用另外画角（圆角 10px，竖条宽 4px，
   裁完两端各收进一点点，看起来是嵌住的而不是贴上去的）。 */ ?>
.gt-hero::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;
  background:linear-gradient(180deg,#2563eb,#38bdf8)}
.gt-hero-top{display:flex;align-items:center;justify-content:space-between;gap:14px 30px;flex-wrap:wrap}
.gt-kicker{display:block;font-size:12.5px;font-weight:700;color:#64748b}
.gt-hero h1{margin:5px 0 0;font-size:32px;font-weight:700;line-height:1.15;
  letter-spacing:-.01em;color:#1e293b}
.gt-hero a.gt-back{display:inline-flex;align-items:center;gap:8px;flex:none;padding:9px 16px;
  border-radius:8px;font-size:13.5px;font-weight:600;color:#475569;text-decoration:none;
  background:#fff;border:1px solid #cbd5e1;
  transition:background .2s ease,border-color .2s ease,color .2s ease}
.gt-hero a.gt-back:hover{background:#f1f5f9;border-color:#94a3b8;color:#1e293b}
<?php /* 事实行与 h1 同一排：页头因此从 261px 降到约 190px，多出来的一屏高度全给树。
   （原来它是 h1 下面独立一行、带一条分隔线，站内两行 + 分隔线 = 62px。）
   margin-left:auto 把这一组推到最右、紧挨着 Family page 按钮 —— 原来的 space-between
   是把它摆在中间：左边 240px 空白、右边 400px 空白，两头都不挨着，看着像掉在那儿。
   组内每一项之间用 1px 竖线分隔（:first-child 不带线、:last-child 去掉右内边距），
   四组数字因此读成一条统计条，而不是四个各自为政的小块。 */ ?>
.gt-facts{display:flex;flex:1 1 auto;flex-wrap:wrap;align-items:flex-end;
  justify-content:space-between;margin:0 0 0 auto;padding:0 0 0 40px}
.gt-facts > div{padding:0 22px;border-left:1px solid #e2e8f0}
.gt-facts > div:first-child{border-left:0;padding-left:0}
.gt-facts > div:last-child{padding-right:0}
.gt-facts dt{font-size:12px;font-weight:600;color:#64748b;white-space:nowrap}
.gt-facts dd{margin:3px 0 0;font-size:19px;font-weight:700;line-height:1.1;
  color:#1e293b;font-variant-numeric:tabular-nums}
<?php /* 状态与注释共识合成第二排（.gt-meta），一行分隔线把它和上面那排数字分开：
     折叠状态 → 一个琥珀/绿的药丸；注释共识 → 右端一行出处。
   原来这两句都是白底上的灰字/棕字散文，左对齐、右边空 900px，读起来没有分量，
   而且和上面的数字之间没有任何分界，三行糊成一片。 */ ?>
.gt-meta{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;
  gap:10px 26px;margin:14px 0 0;padding-top:13px;border-top:1px solid #eef2f7}
.gt-status{display:inline-flex;align-items:center;gap:7px;margin:0;padding:5px 13px;
  border-radius:999px;font-size:12.5px;font-weight:600;line-height:1.55}
.gt-status-warn{color:#92400e;background:#fffbeb;border:1px solid #fde68a}
.gt-status-ok{color:#15803d;background:#f0fdf4;border:1px solid #bbf7d0}
<?php /* 链接沿用药丸自己的颜色（琥珀/绿）而不是蓝色：一个 12.5px 的药丸里塞两种色相会糊，
   下划线已经足够说明它是链接，加粗再压一档。 */ ?>
.gt-hero .gt-status a{color:inherit;font-weight:700;text-decoration:underline;
  text-underline-offset:2px}
.gt-hero .gt-status a:hover{text-decoration-thickness:2px}
.gt-consensus{display:flex;align-items:center;flex-wrap:wrap;gap:4px 9px;margin:0;
  font-size:13px;color:#475569}
.gt-consensus-lbl{font-size:12px;font-weight:600;color:#64748b}
.gt-consensus b{color:#1e293b;font-weight:700}
<?php /* 注释来源（PANTHER / Pfam / …）做成小灰片：它是"这句话谁说的"，不是结论本身。 */ ?>
.gt-consensus .gt-src{font-size:11px;font-weight:700;letter-spacing:.04em;color:#475569;
  background:#f1f5f9;border-radius:999px;padding:2px 9px}
.gt-consensus .gt-tier{color:#64748b}

<?php /* 用户反馈这一块「太紧凑」，2026-09-23 整体放松一档：面板内边距 16/22 → 24/28，
   标题到控件 12 → 20，行距从"每行自己 margin-bottom:10px"改成 grid 的
   row-gap:18px（两列时行间距才是一致的，原来行距和列距是两套数字），控件之间的
   gap 10 → 12，标签列 76 → 92px 且 nowrap（"Collapsed tips" 有 ~90px，比原来的
   76px 宽，会被折断到自己一行，于是那一格的按钮从第二行开始、和右边的 Orthology
   对不齐；nowrap 保证它再长也是整块错位而不是断行）。控件字号 13.5 → 14、图例
   药丸 padding 3/11 → 5/13。
   注意 .ctl-grid .ctl-row 的 margin-bottom 现在必须是 0 —— 行距由 row-gap 出，
   两边都留就会变成 28px 并且和列距对不上。 */ ?>
.control-panel{background:#fff;border:1px solid #eef2f7;border-radius:12px;
  box-shadow:none;padding:24px 28px;margin:0 0 18px}
.control-header{display:flex;align-items:center;gap:10px;margin-bottom:20px}
.control-title{font-size:13px;font-weight:700;color:#64748b}
<?php /* 控件排成两列。九行竖排要 434px，两列后约 270px —— 一个控件都没藏，只是不再
   把树往下推。宽的几行（Labels/Tip size 那行、Export、Download）用 .ctl-span
   横跨两列，免得在半栏里折行反而更高。 */ ?>
.ctl-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 40px;align-items:start}
.ctl-grid .ctl-row{margin-bottom:0}
.ctl-grid .ctl-span{grid-column:1/-1}
@media (max-width:1100px){.ctl-grid{grid-template-columns:1fr;gap:16px 0}}
.gt-help{margin:0}
.gt-help summary{cursor:pointer;padding:2px 0;font-size:13px;font-weight:600;color:#2563eb}
.gt-help p{margin:10px 0 0;font-size:13px;line-height:1.75;color:#475569}
.ctl-row{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-bottom:14px}
.ctl-label{font-size:13.5px;font-weight:600;color:#475569;min-width:92px;white-space:nowrap}
.modern-select{padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;color:#1e293b;background:#fff}
.search-box{display:flex;align-items:center;gap:6px;flex:1;min-width:240px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;background:#fff}
.search-box input{border:none;outline:none;padding:9px 0;font-size:14px;flex:1;background:transparent}
.btn-modern{padding:8px 15px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;font-size:14px;cursor:pointer;transition:all .15s}
.btn-modern:hover{border-color:#93c5fd;color:#1d4ed8;background:#f0f9ff}
.btn-modern.primary{background:#2563eb;border-color:#2563eb;color:#fff}
.btn-modern.primary:hover{background:#1d4ed8;color:#fff}
.gt-legend{display:flex;flex-wrap:wrap;gap:10px}
.gt-legend .item{display:inline-flex;align-items:center;gap:7px;font-size:13px;color:#334155;border:1px solid #e2e8f0;border-radius:20px;padding:5px 13px;cursor:pointer;background:#fff;user-select:none}
.gt-legend .item.muted{opacity:.4}
.gt-legend .dot{width:10px;height:10px;border-radius:50%;display:inline-block}
#tree_container{min-height:620px;overflow:auto;position:relative;background:#fff;border-top:1px solid #e2e8f0}
#tree_container text{font-size:12px;fill:#0f172a}
#tree_container .tip-link{cursor:pointer}
.info-panel{display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;margin:12px 0;font-size:13.5px;color:#334155;line-height:1.8}
.info-panel h4{margin:0 0 4px;font-size:15px;color:#1e293b}
.info-panel a{color:#2563eb;text-decoration:none}
.info-panel a:hover{text-decoration:underline}
.info-panel .bc{font-size:12.5px;color:#64748b}
.dup-note{background:#fef2f2;border-left:3px solid #dc2626;border-radius:4px;padding:7px 11px;margin:6px 0;font-size:16px;color:#7f1d1d}
.ortho-group{border-top:1px solid #e2e8f0;padding-top:7px;margin-top:7px}
.ortho-group b{color:#1e293b}
.gt-empty{padding:48px 22px;text-align:center;color:#64748b;font-size:16px}
@media (max-width:768px){.ctl-label{min-width:100%}.search-box{min-width:100%}}
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

<?php if ($fam === null): ?>

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px"> <b>Gene Family Tree</b></legend>
<div class="gt-card"><div class="gt-empty">
  <?php if ($family === ''): ?>
    No family given. Open a family from the
    <a href="<?php echo gt_h($FAMILY_URL); ?>">gene family browser</a> and use
    &ldquo;View gene tree&rdquo;.
  <?php else: ?>
    <b><?php echo gt_h($family); ?></b> is not a family in this release, or it has no tree.
    Back to the <a href="<?php echo gt_h($FAMILY_URL); ?>?family=<?php echo urlencode($family); ?>">family page</a>.
  <?php endif; ?>
</div></div>

<?php else: ?>

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px"> <b>Gene Family Tree</b></legend>

<div class="gt-hero">
  <div class="gt-hero-top">
    <div>
      <span class="gt-kicker">Gene tree</span>
      <h1><?php echo gt_h($fam['og']); ?></h1>
    </div>

    <dl class="gt-facts">
      <div><dt>Genes</dt><dd><?php echo number_format((int)$fam['n_genes']); ?></dd></div>
      <div><dt>Species</dt><dd><?php echo number_format((int)$fam['n_species']); ?></dd></div>
      <?php /* 折叠时 nTips 仍是"完整树"的 tip 数（图上画的是每物种一个 tip），
               所以标签跟着变，免得数字和图对不上。 */ ?>
      <div><dt><?php echo $is_collapsed ? 'Tips, full tree' : 'Tips in tree'; ?></dt><dd><?php echo number_format((int)$payload['nTips']); ?></dd></div>
      <?php if ((int)$payload['nOut'] > 0): ?>
        <div><dt>Outgroup tips</dt><dd><?php echo number_format((int)$payload['nOut']); ?></dd></div>
      <?php endif; ?>
    </dl>

    <a class="gt-back" href="<?php echo gt_h($FAMILY_URL); ?>?family=<?php echo urlencode($fam['og']); ?>">&#8592; Family page</a>
  </div>


  <div class="gt-meta">
    <?php if ($is_collapsed): ?>
      <p class="gt-status gt-status-warn">Collapsed to one tip per species &mdash; <?php
        echo number_format((int)$payload['nTips']); ?> tips is too many to draw at once.
        <?php /* 句末的句号去掉了：药丸是 inline-flex + gap:7px，文本/链接/句号会被
                 拆成三个 flex item 各自隔着 7px，"tree ." 中间就多出一个空格。 */ ?>
        <a href="/genetree/?family=<?php echo urlencode($fam['og']); ?>&amp;download=full" target="_blank" rel="noopener">Download the full tree</a></p>
    <?php else: ?>
      <p class="gt-status gt-status-ok">Full tree &mdash; every gene is its own tip.</p>
    <?php endif; ?>

    <?php /* 这页原来还有一行"（11,962 sequences）"。它和折叠状态、注释共识一起把
             页头顶到 295px，而它解释的是"为什么序列数比 gene 数多 12"—— 属于说明
             文字，跟下面那段散文一起收进 "How to read this tree"。 */ ?>

    <?php if ($fam['best_term'] !== ''): ?>
      <p class="gt-consensus"><span class="gt-consensus-lbl">Consensus function</span>
        <b><?php echo gt_h($fam['best_name'] !== '' ? $fam['best_name'] : ($fam['best_desc'] !== '' ? $fam['best_desc'] : $fam['best_term'])); ?></b>
        <span class="gt-src"><?php echo gt_h(strtoupper($fam['best_source'])); ?></span>
        <span class="gt-tier"><?php
          echo gt_h($fam['best_tier'] === 'all' ? 'agreed by every member' : number_format((float)$fam['best_pct'], 1) . '% of members'); ?></span></p>
    <?php endif; ?>
  </div>
</div>

<div class="control-panel">
  <div class="control-header"><span class="control-title">Tree controls</span></div>

  <div class="ctl-grid">


  <div class="ctl-row">
    <span class="ctl-label">Find</span>
    <span class="search-box">
      <input type="text" id="gtSearch" placeholder="species, abbreviation, or gene id">
    </span>
    <button class="btn-modern" id="gtPrev" type="button">&uarr;</button>
    <button class="btn-modern" id="gtNext" type="button">&darr;</button>
    <span style="font-size:12.5px;color:#64748b" id="gtMatchCount">0 matches</span>
  </div>

  <div class="ctl-row">
    <span class="ctl-label">Colour by</span>
    <select class="modern-select" id="gtColorBy">
      <option value="cls">Class</option>
      <option value="ord">Order</option>
      <option value="fam">Family</option>
      <option value="gen">Genus</option>
      <option value="none">None</option>
    </select>
    <span class="ctl-label" style="min-width:auto;margin-left:8px">Layout</span>
    <button class="btn-modern active" id="gtRect" type="button">Rectangular</button>
    <button class="btn-modern" id="gtRadial" type="button">Radial</button>
    <button class="btn-modern" id="gtAlign" type="button">Align tips</button>
  </div>

  <div class="ctl-row ctl-span">
    <span class="ctl-label">Labels</span>
    <select class="modern-select" id="gtLabels">
      <option value="species" selected>Species then gene</option>
      <option value="gene">Gene then species</option>
      <option value="abbr">Species abbreviation</option>
    </select>
    <span class="ctl-label" style="min-width:auto">Tip size</span>
    <input type="range" id="gtFont" min="7" max="20" value="12" style="width:130px">
    <span class="ctl-label" style="min-width:auto">Spacing</span>
    <button class="btn-modern" id="gtTighter" type="button">&minus;</button>
    <button class="btn-modern" id="gtLooser" type="button">+</button>
    <button class="btn-modern" id="gtReset" type="button">Reset view</button>
  </div>

  <?php if ($is_collapsed): ?>
  <div class="ctl-row">
    <span class="ctl-label">Collapsed tips</span>
    <button class="btn-modern" id="gtExpandAll" type="button">Expand all species</button>
    <button class="btn-modern" id="gtCollapseAll" type="button">Collapse all</button>
    <span style="font-size:12.5px;color:#64748b" id="gtExpandStatus"></span>
  </div>
  <?php endif; ?>

  <?php if (!empty($DUP)): ?>
  <div class="ctl-row">
    <span class="ctl-label">Orthology</span>
    <button class="btn-modern active" id="gtDupToggle" type="button">Duplications</button>
    <span style="font-size:12.5px;color:#64748b">
      <?php echo number_format((int)$tree['n_dup']); ?> duplication<?php
        echo ((int)$tree['n_dup'] === 1) ? '' : 's'; ?> in this family<?php
        if ($is_collapsed): ?>, each drawn on the nearest clade that still contains it<?php
        endif; ?>
    </span>
  </div>
  <?php endif; ?>

  <div class="ctl-row">
    <span class="ctl-label">Export</span>
    <button class="btn-modern primary" id="gtSaveNwk" type="button">Newick (full tree)</button>
    <button class="btn-modern" id="gtSaveSvg" type="button">SVG</button>
    <button class="btn-modern" id="gtSavePng" type="button">PNG</button>
  </div>

  <div class="ctl-row">
    <span class="ctl-label">Download</span>
    <a class="btn-modern" id="gtDlAln" target="_blank" rel="noopener" href="/genetree/?family=<?php echo urlencode($fam['og']); ?>&amp;dl=aln">Alignment (FASTA.gz)</a>
    <a class="btn-modern" id="gtDlSeq" target="_blank" rel="noopener" href="/genetree/?family=<?php echo urlencode($fam['og']); ?>&amp;dl=seq">Sequences (FASTA)</a>
  </div>

  <div class="gt-legend ctl-span" id="gtLegend"></div>

  <details class="gt-help ctl-span">
    <summary>How to read this tree</summary>
    <p>Gene tree of the orthogroup <b><?php echo gt_h($fam['og']); ?></b>, inferred by OrthoFinder
    from the alignment of its member proteins. Tips are individual genes, labelled by full
    scientific name and gene id &mdash; the <b>Labels</b> control switches to gene-then-species
    or to the short species codes, which fit more tips on screen; branches and tips are
    colour-coded by taxonomic rank.
    <?php if ($is_collapsed): ?>Families this large are drawn as one tip per species, labelled with the
    number of collapsed genes; click a tip for details and a link to the species page. The gene-level
    tree is in the download above.<?php else: ?>Click a tip for details and a link to the gene page.<?php endif; ?></p>
    <?php if ((int)$fam['n_seqs'] > (int)$fam['n_genes']): ?>
      <p>This family holds <b><?php echo number_format((int)$fam['n_seqs']); ?> member sequences</b> but
      <?php echo number_format((int)$fam['n_genes']); ?> member genes:
      <?php echo number_format((int)$fam['n_seqs'] - (int)$fam['n_genes']); ?> sequences share a gene
      identifier with another member of the same family, so they are counted once.</p>
    <?php endif; ?>
    <p>The <b>Download</b> buttons give the MAFFT alignment OrthoFinder used, and the same
    sequences ungapped.</p>
  </details>

  </div><!-- /.ctl-grid -->
</div>

<div class="gt-card">
  <div id="tree_container"></div>
</div>

<div class="info-panel" id="gtInfo"></div>

<?php endif; ?>

<div style="display:none" id="nwk_raw"></div>
</div></div></div></div>

<script>
var PAYLOAD = <?php echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script>
/* =======================================================================
 * Gene tree viewer.  Same library and the same two workarounds as the
 * species-tree page in /phylotree/ -- see the comments at showDetached()
 * and paintNode() below, both of which are the difference between a tree
 * and a blank container.
 * ======================================================================= */
var TIPS  = PAYLOAD.tips || {};
var NWK   = PAYLOAD.newick || '';
var RANKS = ['cls', 'ord', 'fam', 'gen'];
var RANK_LABEL = {cls: 'Class', ord: 'Order', fam: 'Family', gen: 'Genus'};

/* OrthoFinder's duplication nodes, keyed by their path key in the tree drawn below.
   Path keys are child-index chains ("0.1.2" = root's second child's third child) rather
   than node names, because the internal nN labels the source trees carry were stripped
   when the trees were normalised -- and because they survive everything done to the tree
   afterwards: renaming tips does not move nodes and grafting a species back in does not
   reorder its ancestors, so a key still resolves after an expand.
   type: T = a radiation inside one species (the marker sits on that species' collapsed
   tip), N = the duplication spans exactly the species drawn at that node, C = it spans a
   subset of them, so the marker sits on the nearest clade that still contains them all. */
var DUP = {};
(PAYLOAD.dup || []).forEach(function (d) { DUP[d[0]] = {sup: d[1], type: d[2]}; });
/* The queried gene, when the reader arrived from a gene page.  '' otherwise. */
var QGENE = PAYLOAD.gene || '', QABBR = PAYLOAD.abbr || '';

/* How a tip is labelled.  'species' = Genus_species_gene, 'gene' = gene_Genus_species,
   'abbr' = the old ABBR_gene.  See shortLabel() for why these are the only shapes. */
var LBL = 'species';

var state = {
  tree: null,
  palette: {},
  hidden: {},
  colorBy: 'cls',
  matches: [],
  idx: -1,
  fontPx: 12,
  showDup: true,     // when false, duplication markers stay out of the figure
  qLabel: '',        // short label of the queried gene, when it is a tip here
  qTip: '',          // ...or the collapsed tip standing in for its species
  /* Species whose COLLAPSEDxN tip has been opened, keyed by species name.  The value is
     the label of the tip it replaced, which is what we graft back onto. */
  expanded: {},
  subtreeNwk: {},    // species -> Newick of its genes, cached so a rebuild is offline
  subtreeTips: {}    // species -> {full label: tip info} for those genes
};

/* ---------------------------------------------------------------- colours */
function hsl(i) {
  var h = (i * 137.508) % 360;
  var s = [62, 70, 55, 75, 48][i % 5];
  var l = [46, 40, 52, 44, 56][i % 5];
  return 'hsl(' + h.toFixed(1) + ',' + s + '%,' + l + '%)';
}
function buildPalette() {
  RANKS.forEach(function (r) {
    var seen = {}, out = {};
    var vals = [];
    Object.keys(TIPS).forEach(function (k) {
      var v = TIPS[k][r] || '';
      if (v && !seen[v]) { seen[v] = 1; vals.push(v); }
    });
    vals.sort();
    vals.forEach(function (v, i) { out[v] = hsl(i); });
    state.palette[r] = out;
  });
}
function rankOf(name, rank) {
  var t = TIPS[name];
  return (t && t[rank]) ? t[rank] : '';
}
function colorFor(name) {
  if (state.colorBy === 'none') { return '#64748b'; }
  var v = rankOf(name, state.colorBy);
  if (!v) { return TIPS[name] && TIPS[name].out ? '#94a3b8' : '#64748b'; }
  return state.palette[state.colorBy][v] || '#64748b';
}

/* ---------------------------------------------------------------- newick */
/* A minimal parser, matching what phylotree.js itself accepts from these files:
   labels are punctuation-free, branch lengths are numeric, no quoted names. */
var _uid = 0;
function parseNewick(s) {
  s = String(s).replace(/\[[^\]]*\]/g, '').trim().replace(/;\s*$/, '');
  var pos = 0;
  function node() {
    var n = {id: ++_uid, name: '', len: '', children: [], parent: null, collapsed: false};
    if (s.charAt(pos) === '(') {
      pos++;
      for (;;) {
        var c = node(); c.parent = n; n.children.push(c);
        if (pos >= s.length) { break; }
        var ch = s.charAt(pos);
        if (ch === ',') { pos++; continue; }
        if (ch === ')') { pos++; break; }
        pos++;
      }
    }
    var st = pos;
    while (pos < s.length && '(),:;'.indexOf(s.charAt(pos)) < 0) { pos++; }
    n.name = s.slice(st, pos).trim();
    if (s.charAt(pos) === ':') {
      pos++; st = pos;
      while (pos < s.length && '(),;'.indexOf(s.charAt(pos)) < 0) { pos++; }
      n.len = s.slice(st, pos).trim();
    }
    return n;
  }
  return node();
}
function safeName(s) { return String(s == null ? '' : s).replace(/[\s,;:()\[\]'"]/g, '_'); }
function collapseToken(n) { return 'COLLAPSED_' + n.id; }
function toNewick(n) {
  if (n.collapsed && n.children.length) { return safeName(collapseToken(n)); }
  if (n.children.length) { return '(' + n.children.map(toNewick).join(',') + ')' + safeName(n.name || ''); }
  return safeName(n.name || '') + (n.len ? ':' + n.len : '');
}
function eachNode(n, fn) { fn(n); n.children.forEach(function (c) { eachNode(c, fn); }); }

/* What phylotree.js hands the stylers is its own node wrapper: sometimes the label is on
   the wrapper, sometimes one level down under .data.  Checking both is what the species
   page does, and getting it wrong means every node looks like an internal one (or the
   reverse), so no tip is ever coloured. */
function nmOf(d) {
  if (!d) { return null; }
  if (d.name !== undefined && d.name !== null && d.name !== '') { return d.name; }
  if (d.data && d.data.name !== undefined && d.data.name !== null && d.data.name !== '') { return d.data.name; }
  return null;
}
function kidsOf(d) {
  if (!d) { return null; }
  if (d.children && d.children.length) { return d.children; }
  if (d.data && d.data.children && d.data.children.length) { return d.data.children; }
  return null;
}
/* The library's own node object (nmOf checks two shapes because the stylers are handed a
   wrapper in some render paths); pathKeyOf needs the one that carries .parent/.children. */
function libNode(d) {
  if (!d) { return null; }
  if (d.children !== undefined && d.parent !== undefined) { return d; }
  if (d.data && d.data.children !== undefined && d.data.parent !== undefined) { return d.data; }
  return d;
}
/* Path key of a node in the tree as currently drawn: the chain of child indices from the
   root, which is the key the server computed the duplication markers under.  Only valid
   while the root is the one the page rendered -- hence the disabled reroot in render(). */
function pathKeyOf(d) {
  var n = libNode(d);
  var parts = [];
  while (n && n.parent && n.parent.children) {
    var i = n.parent.children.indexOf(n);
    if (i < 0) { return null; }
    parts.push(i);
    n = n.parent;
  }
  return parts.reverse().join('.');
}
/* Longest shared prefix of two path keys = the deepest node that is an ancestor of both.
   The root's own key is the empty string, so an empty result is still a valid key. */
function lcaKey(a, b) {
  var x = String(a).split('.'), y = String(b).split('.');
  var i = 0;
  while (i < x.length && i < y.length && x[i] === y[i]) { i++; }
  return x.slice(0, i).join('.');
}
/* Every tip of the drawn tree as [path key, short label, tip info].  Recomputed on demand:
   the tree is rebuilt from scratch on every expand/collapse, so a cached index is a bug. */
function tipIndex() {
  var out = [];
  (function walk(n, k) {
    if (!n.children.length) { out.push([k, n.name, TIPS[n.name] || null]); return; }
    for (var i = 0; i < n.children.length; i++) {
      walk(n.children[i], k === '' ? String(i) : k + '.' + i);
    }
  })(state.root, '');
  return out;
}
function keyOfTip(name) {
  var idx = tipIndex();
  for (var i = 0; i < idx.length; i++) { if (idx[i][1] === name) { return idx[i][0]; } }
  return null;
}
/* species key -> display name, for panels that group by species.  Taken from the tips
   themselves so an outgroup keeps the name the tree shows it under. */
function speciesNames() {
  var out = {};
  Object.keys(TIPS).forEach(function (k) {
    var t = TIPS[k];
    if (t.sp && !out[t.sp]) { out[t.sp] = t.disp; }
  });
  return out;
}

/* Rename every tip before rendering: the payload's own label is the full
   "Genus_species_ABBR_geneid", which is far too long to read at 12px, and doing this by
   rewriting the text nodes after render (the obvious approach) does not survive: every
   display.update() -- which the library itself calls on right-click collapse, reroot and
   hide -- re-renders the labels from the node data and puts the long names back.  The
   rendered text has to *be* the node name, too, because focusTip()/focusMatch() find a
   tip by comparing the on-screen text to the name it wants.

   Whatever the format, the label must stay a legal Newick token -- no spaces, commas,
   colons, brackets or quotes -- because the page re-serialises the tree for the .nwk,
   SVG and PNG exports and re-parses it on every collapse and expand.  That is why the
   Latin binomial is joined with underscores ("Nematostella_vectensis"), the standard
   convention for a species name in a Newick file, rather than keeping the space or
   wrapping the species in parentheses: parentheses are tree structure, not text.

   Three orders, because there is no single right one.  Species first groups equal
   prefixes when reading down a list of tips, gene first puts the distinguishing token
   where the eye lands, and the abbreviation is kept for families large enough that 149
   binomials do not fit on screen.  Outgroups lose their "OUT_" prefix here -- the flag
   that matters is t.out, which colours them grey and labels the legend, and the prefix
   only made the one label that is already a full binomial even longer. */
function shortLabel(full) {
  var t = TIPS[full];
  if (!t) { return safeName(full); }
  if (LBL === 'abbr') {
    if (t.coll > 0) { return safeName(t.abbr + '_COLLAPSEDx' + t.coll); }
    if (t.gene) { return safeName(t.abbr + '_' + t.gene); }
    return safeName(t.abbr);
  }
  var sp = safeName(t.disp || t.sp);
  if (t.coll > 0) {
    return LBL === 'gene' ? safeName('COLLAPSEDx' + t.coll + '_' + sp)
                          : safeName(sp + '_COLLAPSEDx' + t.coll);
  }
  if (!t.gene) { return sp; }
  return LBL === 'gene' ? safeName(t.gene + '_' + sp) : safeName(sp + '_' + t.gene);
}
function shortenTips(root) {
  var short = {}, used = {};
  eachNode(root, function (n) {
    if (n.children.length) { return; }
    var full = n.name;
    var s = shortLabel(full);
    /* Two tips must never come out with the same token: the map returned here is keyed
       by it, so a collision would silently hand one tip another species' colour, class
       and links.  Labels are assembled from parts, and a display name is not guaranteed
       unique by anything the page controls, so rather than trust that they never are,
       make the second one distinct.  (All 153 display names in the current taxonomy.json
       are distinct, so this does not fire today.) */
    if (used[s] !== undefined && used[s] !== full) {
      var i = 2;
      while (used[s + '_' + i] !== undefined) { i++; }
      s = s + '_' + i;
    }
    used[s] = full;
    n.name = s;
    if (TIPS[full]) { short[s] = TIPS[full]; }
    else { short[s] = {label: s, sp: '', abbr: s, gene: '', disp: s, abbr1: '', cls: '',
                       ord: '', fam: '', gen: '', out: false, url: '', gurl: '', coll: 0}; }
  });
  return short;
}

/* Swap a leaf for a subtree, keeping the branch length the collapsed tip had. */
function graft(root, leafName, sub) {
  function walk(n) {
    for (var i = 0; i < n.children.length; i++) {
      var c = n.children[i];
      if (!c.children.length && c.name === leafName) {
        sub.len = c.len;
        sub.parent = n;
        n.children[i] = sub;
        return true;
      }
      if (c.children.length && walk(c)) { return true; }
    }
    return false;
  }
  return walk(root);
}

/* Rebuild the tree from the pristine collapsed Newick and re-apply every open species.
   Rebuilding rather than mutating in place is what makes collapse work again afterwards:
   closing a species is then just dropping it from state.expanded, with no need to undo a
   graft or to remember the original leaf -- and the two operations cannot drift apart. */
function rebuild() {
  // Raw full-label map first: shortenTips() below reads it to name and describe leaves.
  TIPS = {};
  Object.keys(PAYLOAD.tips || {}).forEach(function (k) { TIPS[k] = PAYLOAD.tips[k]; });
  Object.keys(state.expanded).forEach(function (sp) {
    var t = state.subtreeTips[sp];
    if (t) { Object.keys(t).forEach(function (k) { TIPS[k] = t[k]; }); }
  });

  state.root = parseNewick(PAYLOAD.newick);
  Object.keys(state.expanded).forEach(function (sp) {
    var nw = state.subtreeNwk[sp];
    if (nw) { graft(state.root, state.expanded[sp], parseNewick(nw)); }
  });

  TIPS = shortenTips(state.root);
  findQuery();
  buildPalette();
  render();
  updateExpandStatus();
}

/* Locate the gene the reader arrived with, in whatever the tree currently shows.  It is a
   tip of its own -- unless this family is collapsed, where the best the tree can do is the
   COLLAPSEDxN tip standing in for its species; then state.qTip carries that tip instead and
   the panel says "one of these N genes" rather than pretending to point at one gene.
   Re-run from rebuild() so opening the species turns qTip into qLabel. */
function findQuery() {
  state.qLabel = ''; state.qTip = '';
  if (!QGENE || !QABBR) { return; }
  /* Matched on the tip's own abbr and gene rather than by rebuilding the label string:
     the label is a display choice now, so a name assembled here would stop matching the
     moment the reader switches format. */
  var coll = '';
  Object.keys(TIPS).forEach(function (k) {
    var t = TIPS[k];
    if (!t) { return; }
    if (t.abbr === QABBR && t.gene === QGENE) { state.qLabel = k; }
    if (t.coll > 0 && t.abbr === QABBR) { coll = k; }
  });
  if (!state.qLabel) { state.qTip = coll; }
}

function updateExpandStatus() {
  var el = document.getElementById('gtExpandStatus');
  if (!el) { return; }
  var n = Object.keys(state.expanded).length;
  if (!n) {
    el.textContent = PAYLOAD.collapsed
      ? 'click a ×N tip to open that species' : '';
    return;
  }
  var total = 0;
  var root = state.root;
  eachNode(root, function (x) { if (!x.children.length) { total++; } });
  el.textContent = n + ' species open — showing ' + total.toLocaleString() +
                   ' of ' + PAYLOAD.nTips.toLocaleString() + ' genes';
}

/* Fetch one species' genes and open them in place. */
function expandSpecies(sp, leafName) {
  if (state.expanded[sp]) { return; }
  var el = document.getElementById('gtExpandStatus');
  if (el) { el.textContent = 'loading ' + sp + '…'; }
  fetch('/genetree/?family=' + encodeURIComponent(PAYLOAD.family) +
        '&expand=' + encodeURIComponent(sp))
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (!j.ok) { throw new Error(j.error || 'no genes returned'); }
      state.subtreeNwk[sp] = j.newick;
      state.subtreeTips[sp] = j.tips;
      state.expanded[sp] = leafName;
      rebuild();
      expandStatusNote(sp, j.n);
    })
    .catch(function (e) {
      if (el) { el.textContent = 'could not open ' + sp + ': ' + e.message; }
    });
}

function expandStatusNote(sp, n) {
  var el = document.getElementById('gtExpandStatus');
  if (el) {
    el.textContent = 'opened ' + n + ' genes of ' + sp + ' — ' +
                     Object.keys(state.expanded).length + ' species open';
  }
}

function collapseSpecies(sp) {
  if (!state.expanded[sp]) { return; }
  delete state.expanded[sp];
  rebuild();
}

function expandAll() {
  var leaves = [];
  eachNode(state.root, function (n) {
    var t = n.children.length ? null : TIPS[n.name];
    // label, not name -- see the note in showInfo about grafting onto full labels.
    if (t && t.coll > 0) { leaves.push({sp: t.sp, name: t.label, n: t.coll}); }
  });
  if (!leaves.length) { return; }
  /* Every species at once means handing the browser the whole tree -- 35,563 tips for the
     largest family, which is the reason it was collapsed in the first place.  Ask before
     doing that; below the threshold it is a second or two and not worth a dialog. */
  if (PAYLOAD.nTips > 10000) {
    var msg = 'This will draw all ' + PAYLOAD.nTips.toLocaleString() +
              ' genes, one tip each.\n\n' +
              'That is a lot for a browser and may take a while or become hard to read.\n' +
              'You can download the full tree instead from the Newick button.\n\nContinue?';
    if (!window.confirm(msg)) { return; }
  }
  var el = document.getElementById('gtExpandStatus');
  var done = 0;
  var failed = [];
  // Sequential: each response is small, but firing 145 at once at a shared box is rude,
  // and the progress line is only meaningful if they complete in order.
  function next() {
    if (done >= leaves.length) {
      rebuild();
      if (el) {
        el.textContent = 'opened all ' + (leaves.length - failed.length) + ' species'
          + (failed.length ? ' (' + failed.length + ' failed)' : '');
      }
      return;
    }
    var L = leaves[done++];
    if (el) { el.textContent = 'opening ' + done + ' / ' + leaves.length + ' species…'; }
    fetch('/genetree/?family=' + encodeURIComponent(PAYLOAD.family) +
          '&expand=' + encodeURIComponent(L.sp))
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok) {
          state.subtreeNwk[L.sp] = j.newick;
          state.subtreeTips[L.sp] = j.tips;
          state.expanded[L.sp] = L.name;
        } else { failed.push(L.sp); }
      })
      .catch(function () { failed.push(L.sp); })
      .then(next);
  }
  next();
}

function collapseAll() {
  state.expanded = {};
  rebuild();
}

/* ---------------------------------------------------------------- render */
function render() {
  var nwk = toNewick(state.root) + ';';
  d3.select('#tree_container').html('');
  state.tree = new phylotree.phylotree(nwk);
  state.tree.render({
    container: '#tree_container',
    'draw-size-bubbles': false,
    'left-right-spacing': 'fixed-step',
    'brush': false,
    'zoom': true,
    'show-scale': false,
    'internal-names': false,
    /* Rerooting rebuilds the tree from a new root, which renumbers every path from it and
       would leave the duplication markers sitting on whatever nodes inherited their keys.
       Families that carry markers are the ones OrthoFinder already rooted, so there is
       nothing to gain by allowing it; the families where rerooting is genuinely useful are
       the unrooted FastTree ones, which never have markers. */
    'reroot': <?php echo $dup_resolved ? 'false' : 'true'; ?>,
    'node-styler': nodeStyler,
    'edge-styler': edgeStyler
  });
  /* phylotree.js builds its SVG detached (it calls d3.create("svg") internally) and
     render() never inserts it into the container -- the library's own demo calls
     display.show() afterwards too.  Without this the container stays empty and every
     later pass that looks for '#tree_container svg' (decorate, export) finds nothing. */
  $(state.tree.display.container).empty();
  $(state.tree.display.container).html(state.tree.display.show());
  decorate();
  drawLegend();
}

function nodeStyler(element, data) {
  try {
    var name = nmOf(data) || '';
    var kids = kidsOf(data);
    var info = TIPS[name];
    var key = state.showDup ? pathKeyOf(data) : null;
    var dup = key === null ? null : DUP[key];
    if (kids && kids.length && !info) {
      // internal node -- the library already drew its dot; leave the branch colour to
      // edgeStyler and keep its label legible.
      element.selectAll('circle').style('fill', '#cbd5e1').style('stroke', 'none');
      element.selectAll('text').style('fill', '#0f172a');
      if (dup) {
        // A node OrthoFinder called a duplication: the genes on either side of it are
        // paralogues of each other (or co-orthologues across species), which is the whole
        // point of drawing it.  Filled red, bigger than the ordinary grey dot.
        paintNode(element, '#dc2626', {r: 5.5});
        element.selectAll('circle').style('stroke', '#7f1d1d').style('stroke-width', '1px');
      }
      element.classed('tip-link', true);
      element.on('click', function () { showClade(data); });
      return;
    }
    var isHit = state.matches.indexOf(name) >= 0;
    var isQuery = (state.qLabel !== '' && name === state.qLabel) ||
                  (state.qTip !== '' && name === state.qTip);
    paintNode(element, info ? colorFor(name) : '#94a3b8', {r: isHit || isQuery ? 6.5 : 4});
    if (isQuery) {
      // The gene the reader came here for: gold, in the page's own accent colour, so it
      // stands out from both the rank colours and the duplication red.
      element.selectAll('circle').style('stroke', '#f59e0b').style('stroke-width', '3px');
    } else if (isHit) {
      element.selectAll('circle').style('stroke', '#dc2626').style('stroke-width', '3px');
    } else if (dup) {
      // A collapsed tip that is itself a duplication site (type T/C): every gene under it
      // is an in-paralogue of the others.
      element.selectAll('circle').style('stroke', '#dc2626').style('stroke-width', '2.5px');
    }
    var v = info ? (info[state.colorBy] || '') : '';
    if (v && state.hidden[v]) { element.style('opacity', 0.12); }
    if (info) {
      element.classed('tip-link', true);
      element.on('click', function () { showInfo(name); });
    }
  } catch (e) { /* one bad node must not abort the whole render pass */ }
}

function edgeStyler(element, data) {
  try {
    // Colour the branch *into* a duplication node red as well, so the marker reads as a
    // split even when the two child branches are the same colour.
    var dk = state.showDup ? pathKeyOf(data) : null;
    if (dk !== null && DUP[dk]) { element.style('stroke', '#dc2626').style('stroke-width', '2px'); return; }
    var v = rankOf(nmOf(data) || '', state.colorBy);
    if (v && state.hidden[v]) { element.style('stroke', '#e2e8f0'); return; }
    var c = (state.colorBy === 'none' || !v) ? '#94a3b8'
          : (state.palette[state.colorBy][v] || '#94a3b8');
    element.style('stroke', c).style('stroke-width', '1.4px');
  } catch (e) {}
}

/* phylotree.css ships `.node circle{fill:steelblue}` and SVG fill is inherited, so a
   plain element.style('fill', colour) recolours the labels instead of the dots -- which
   is why names vanish.  Set the circle explicitly and pin the text colour by hand. */
function paintNode(element, fill, opts) {
  opts = opts || {};
  var r = opts.r || 4;
  var circles = element.selectAll('circle');
  if (circles.empty() || !circles.size()) {
    // draw-size-bubbles is off, so leaves arrive with no dot at all -- without this the
    // rank colour has nowhere to land and the legend refers to nothing.  Insert it as
    // the first child so it sits under the label rather than over it.
    element.insert('circle', ':first-child').attr('r', r).attr('cx', 0).attr('cy', 0);
    circles = element.selectAll('circle');
  }
  circles.attr('r', r).style('fill', fill).style('stroke', 'none');
  element.selectAll('text').style('fill', '#0f172a');
}

/* Labels are already the short form (see shortenTips), so this only has to re-apply the
   font size -- which the library resets from its own option on every update(). */
function decorate() {
  d3.selectAll('#tree_container text').style('font-size', state.fontPx + 'px');
}

/* ---------------------------------------------------------------- legend */
function drawLegend() {
  var box = document.getElementById('gtLegend');
  if (!box) { return; }
  box.innerHTML = '';
  if (state.showDup && Object.keys(DUP).length) {
    var dd = document.createElement('span');
    dd.className = 'item';
    dd.innerHTML = '<span class="dot" style="background:#dc2626"></span>duplication';
    box.appendChild(dd);
  }
  if (QGENE && (state.qLabel || state.qTip)) {
    var dq = document.createElement('span');
    dq.className = 'item';
    dq.innerHTML = '<span class="dot" style="background:#f59e0b"></span>your gene';
    box.appendChild(dq);
  }
  if (state.colorBy === 'none') { return; }
  var pal = state.palette[state.colorBy] || {};
  Object.keys(pal).sort().forEach(function (v) {
    var spans = Object.keys(TIPS).filter(function (k) { return TIPS[k][state.colorBy] === v; }).length;
    var d = document.createElement('span');
    d.className = 'item' + (state.hidden[v] ? ' muted' : '');
    d.innerHTML = '<span class="dot" style="background:' + pal[v] + '"></span>' +
                  v.replace(/&/g, '&amp;').replace(/</g, '&lt;') +
                  ' <span style="color:#64748b">' + spans + '</span>';
    d.onclick = function () { state.hidden[v] = !state.hidden[v]; render(); };
    box.appendChild(d);
  });
  var nOut = Object.keys(TIPS).filter(function (k) { return TIPS[k].out; }).length;
  if (nOut) {
    var d = document.createElement('span');
    d.className = 'item muted';
    d.innerHTML = '<span class="dot" style="background:#94a3b8"></span>outgroup <span style="color:#64748b">' + nOut + '</span>';
    box.appendChild(d);
  }
}

/* ---------------------------------------------------------------- info */
/* Which species sit under a node, and how many of their genes each contributes. */
function speciesUnder(d, out) {
  out = out || {};
  var kids = kidsOf(d);
  if (kids && kids.length) {
    for (var i = 0; i < kids.length; i++) { speciesUnder(kids[i], out); }
    return out;
  }
  var nm = nmOf(d);
  var t = nm ? TIPS[nm] : null;
  if (t && t.sp) { out[t.sp] = (out[t.sp] || 0) + (t.coll > 0 ? t.coll : 1); }
  return out;
}

/* Summary for an internal node, with a way back out of any species opened inside it. */
function showClade(d) {
  var box = document.getElementById('gtInfo');
  if (!box) { return; }
  var sp = speciesUnder(d);
  var keys = Object.keys(sp);
  if (!keys.length) { box.style.display = 'none'; return; }
  var genes = 0;
  keys.forEach(function (k) { genes += sp[k]; });
  keys.sort(function (a, b) { return sp[b] - sp[a]; });
  var h = '<h4>Clade of ' + genes.toLocaleString() + ' genes from ' + keys.length +
          (keys.length === 1 ? ' species' : ' species') + '</h4>';
  var dk = state.showDup ? pathKeyOf(d) : null;
  var dm = dk === null ? null : DUP[dk];
  if (dm) { h += dupNote(dm, 'clade'); }
  h += '<div class="bc">' + keys.slice(0, 12).map(function (k) {
    var n = TIPS[Object.keys(TIPS).filter(function (x) { return TIPS[x].sp === k; })[0]];
    return esc(n ? n.disp : k) + ' (' + sp[k] + ')';
  }).join(' &middot; ') + (keys.length > 12 ? ' &hellip; +' + (keys.length - 12) + ' more' : '') + '</div>';
  var open = keys.filter(function (k) { return state.expanded[k]; });
  if (open.length) {
    h += '<div style="margin-top:8px">' + open.map(function (k) {
      return '<button class="btn-modern" data-collapse="' + esc(k) + '">Close ' + esc(k) + '</button>';
    }).join(' ') + '</div>';
  }
  box.innerHTML = h;
  box.style.display = 'block';
  Array.prototype.forEach.call(box.querySelectorAll('[data-collapse]'), function (b) {
    b.onclick = function () { collapseSpecies(this.getAttribute('data-collapse')); };
  });
}

/* Wording for a duplication marker.  Kept in one place because the three placement types
   mean genuinely different things and the reader has to be told which one they are
   looking at rather than being told "duplication" for all of them. */
function dupNote(dm, where) {
  var pct = Math.round((dm.sup || 0) * 100);
  var h = '<div class="dup-note">';
  if (dm.type === 'N') {
    h += '<b>Duplication node</b> (OrthoFinder support ' + pct + '%): the genes on either ' +
         'side are paralogues of each other' + (where === 'tip' ? '' : ', or co-orthologues ' +
         'where a species has genes on both sides') + '.';
  } else if (dm.type === 'T') {
    h += '<b>Duplication within one species</b> (support ' + pct + '%): every gene under ' +
         'this tip is an in-paralogue of the others.';
  } else {
    h += '<b>Duplication inside this clade</b> (support ' + pct + '%). This family is too ' +
         'large to draw in full, so the marker sits on the nearest clade that still ' +
         'contains all the genes the duplication spans — at least one pair of genes ' +
         'below it are paralogues or co-orthologues of each other.';
  }
  if (where === 'tip') {
    h += ' <span class="bc">Use the button below to open this species and see them.</span>';
  }
  return h + '</div>';
}

function showInfo(name) {
  var t = TIPS[name];
  var box = document.getElementById('gtInfo');
  if (!box) { return; }
  if (!t) { box.style.display = 'none'; return; }
  var bc = [t.cls, t.ord, t.fam, t.gen].filter(Boolean).join(' › ');
  var h = '<h4><i>' + esc(t.disp) + '</i> <span style="color:#64748b;font-weight:400">'
        + esc(t.abbr1 || (t.out ? 'outgroup' : '')) + '</span></h4>';
  if (bc) { h += '<div class="bc">' + esc(bc) + '</div>'; }
  var tk = state.showDup ? keyOfTip(name) : null;
  if (tk !== null && DUP[tk]) { h += dupNote(DUP[tk], 'tip'); }
  if (t.coll > 0 || state.expanded[t.sp]) {
    // A COLLAPSEDxN tip is one representative standing in for the rest of that species.
    // The genes it hides are still in the database, so offer to cut them back out of the
    // full tree rather than making the reader download it to see them.
    h += '<div>This tip stands for <b>' + t.coll + '</b> genes of <i>' + esc(t.disp) + '</i>.</div>';
    h += '<div style="margin-top:8px">';
    if (state.expanded[t.sp]) {
      h += '<button class="btn-modern" id="gtCollapseOne">Close these genes</button>';
    } else {
      h += '<button class="btn-modern primary" id="gtExpandOne">Show all ' + t.coll
         + ' genes</button>';
    }
    h += '</div>';
  } else if (t.gene) {
    h += '<div>Gene: <b>' + esc(t.gene) + '</b></div>';
  }
  if (t.gurl) { h += '<div><a href="' + t.gurl + '" target="_blank" rel="noopener noreferrer">Gene detail page &rarr;</a></div>'; }
  if (t.url)  { h += '<div><a href="' + t.url + '" target="_blank" rel="noopener noreferrer">Species entry &rarr;</a></div>'; }
  if (t.out) {
    // Taxonomy names are stored underscored (Sycon_ciliatum); NCBI only answers to the
    // spaced form, so a straight substitution is required here, not just a strip of OUT_.
    var binomial = t.sp.replace(/^OUT_/, '').replace(/_/g, ' ');
    h += '<div><a href="https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?name='
       + encodeURIComponent(binomial) + '" target="_blank" rel="noopener noreferrer">NCBI Taxonomy &rarr;</a></div>';
    h += '<div class="bc">Outgroup sequence, not a CnidoSite species.</div>';
  }
  box.innerHTML = h;
  box.style.display = 'block';
  var eb = document.getElementById('gtExpandOne');
  // t.label, not the displayed name: rebuild() grafts onto the pristine tree, whose
  // leaves still carry their full labels at that point.
  if (eb) { eb.onclick = function () { expandSpecies(t.sp, t.label); }; }
  var cb = document.getElementById('gtCollapseOne');
  if (cb) { cb.onclick = function () { collapseSpecies(t.sp); }; }
}
/* --------------------------------------------------- orthologues / paralogues */
/* Which of this orthogroup's genes are orthologues, co-orthologues or paralogues of one
   queried gene.

   The rule is OrthoFinder's own: walk down from the queried gene to the deepest node that
   is also an ancestor of the other gene; if that node is a duplication, the two genes
   descend from a gene duplication that happened *after* the two species split, so they
   are co-orthologues (many-to-many); if it is an ordinary speciation node they are
   one-to-one orthologues.  Same species + same orthogroup is the definition of an
   in-paralogue, so those come straight from the orthogroup membership.

   Everything here is read off the tree as drawn, which is why the collapsed families are
   caveated: a COLLAPSEDxN tip stands for many genes and the marker on it is a statement
   about the clade, so pairs involving one are reported at clade level, not gene level. */
function orthologuesOf(name) {
  var idx = tipIndex();
  var qk = null, qsp = '';
  for (var i = 0; i < idx.length; i++) {
    if (idx[i][1] === name && idx[i][2]) { qk = idx[i][0]; qsp = idx[i][2].sp || ''; }
  }
  var res = {qk: qk, qsp: qsp, ortho: {}, coortho: {}, nPara: 0, folded: 0};
  if (qk === null) { return res; }
  for (var j = 0; j < idx.length; j++) {
    var k = idx[j][0], sp = idx[j][2] ? (idx[j][2].sp || '') : '';
    if (k === qk || !idx[j][2]) { continue; }
    if (sp === qsp) { continue; }                 // same species: paralogues, listed apart
    /* A folded tip stands for every gene of its species in this orthogroup (coll), so it
       has to count as that many genes -- otherwise a 35,000-gene family reports "120
       orthologues" and means 120 species tips. */
    var w = idx[j][2].coll > 0 ? idx[j][2].coll : 1;
    if (idx[j][2].coll > 0) { res.folded++; }
    var l = lcaKey(qk, k);
    if (DUP[l]) { res.coortho[sp] = (res.coortho[sp] || 0) + w; }
    else { res.ortho[sp] = (res.ortho[sp] || 0) + w; }
  }
  return res;
}

/* The panel the reader gets when they arrive from a gene page: their gene, plus what it
   is orthologous to.  Falls back to the family-level statement when the gene is inside a
   collapsed tip, which is the common case for the biggest families. */
function showOrtho(name) {
  var box = document.getElementById('gtInfo');
  if (!box) { return; }
  var t = TIPS[name];
  var h = '<h4>Orthologues &amp; paralogues of <span style="font-family:monospace">'
        + esc(QGENE) + '</span></h4>';
  if (t) {
    h += '<div class="bc">' + esc(t.disp) + (t.abbr1 ? ' &middot; ' + esc(t.abbr1) : '')
       + ' &middot; <a href="' + (t.gurl || '#') + '">gene page</a></div>';
  }
  var o = orthologuesOf(name);
  var names = speciesNames();
  function group(title, map, note) {
    var sps = Object.keys(map).sort(function (a, b) { return map[b] - map[a]; });
    var total = 0;
    sps.forEach(function (s) { total += map[s]; });
    var g = '<div class="ortho-group"><b>' + title + ':</b> ' + total +
            ' gene' + (total === 1 ? '' : 's') + ' in ' + sps.length +
            ' species<div class="bc">' + sps.slice(0, 14).map(function (s) {
              return esc(names[s] || s.replace(/^OUT_/, '')) + ' (' + map[s] + ')';
            }).join(' &middot; ') + (sps.length > 14 ? ' &hellip; +' + (sps.length - 14) : '') +
            '</div>';
    if (note) { g += '<div class="bc">' + note + '</div>'; }
    return g + '</div>';
  }
  if (!PAYLOAD.dupResolved) {
    /* 22,281 families have no rooted tree from OrthoFinder; every one of them has three
       genes or fewer, so there is nothing to root and no duplication to call.  Say that
       rather than implying the family is unusually large. */
    h += '<div class="dup-note">No rooted gene tree was resolved for this family (' +
         PAYLOAD.nTips + (PAYLOAD.nTips === 1 ? ' gene' : ' genes') + '), so no duplication ' +
         'could be called and orthologues cannot be told apart from co-orthologues. ' +
         'Everything listed below is a <b>true homologue</b> of the queried gene; the ' +
         'same-species ones are its <b>paralogues</b> by orthogroup membership.</div>';
    h += group('Orthologues or co-orthologues', o.ortho);
  } else {
    /* Where a tip stands for a whole species (the drawn tree of a big family, or a species
       the reader has not opened), its genes are counted together and the split between the
       two groups is made for the clade -- say so, because the numbers then read larger than
       a gene-by-gene count would. */
    var fold = o.folded
      ? o.folded + ' species tip' + (o.folded === 1 ? '' : 's') + ' here stand for all of ' +
        'that species\' genes in this family, counted together and classified as a clade: ' +
        'open the species (or the whole family) for a gene-by-gene answer.'
      : '';
    h += group('Orthologues (one-to-one)', o.ortho, fold);
    h += group('Co-orthologues (many-to-many, split by a duplication)', o.coortho, fold);
  }
  /* Paralogues come from the orthogroup membership, not from the tree: OrthoFinder puts a
     species' genes in one orthogroup exactly when they are in-paralogues of each other,
     and this list is complete even when the tree has folded that species into one tip. */
  /* The count is the query's own species-mates, so subtract the query itself: qGeneTotal
     counts it, the reader is asking about the others.  (A species can have the queried gene
     listed more than once in a proteome that shipped duplicate headers, which is why the
     subtraction is on the reported total rather than on the filtered list.) */
  var other = Math.max(0, (PAYLOAD.qGeneTotal || 0) - 1);
  h += '<div class="ortho-group"><b>Paralogues (same species, same orthogroup):</b> '
     + other;
  if (PAYLOAD.qGenes && PAYLOAD.qGenes.length) {
    var others = PAYLOAD.qGenes.filter(function (g) { return g !== QGENE; });
    h += '<div class="bc">' + (others.length
        ? others.slice(0, 40).map(function (g) { return esc(g); }).join(' &middot; ')
          + (others.length > 40 ? ' &hellip; +' + (others.length - 40) : '')
        : 'no other gene of this species in this orthogroup')
      + '</div>';
  }
  h += '</div>';
  h += '<div class="bc" style="margin-top:6px">Duplication calls: OrthoFinder 2.5.5 ' +
       'species-overlap on the resolved gene trees, with the support value from ' +
       '<code>Duplications.tsv</code>. Orthogroup membership from the same run.</div>';
  box.innerHTML = h;
  box.style.display = 'block';
}

function esc(s) {
  return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                                 .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ---------------------------------------------------------------- search */
function runSearch(q) {
  state.matches = [];
  state.idx = -1;
  if (q) {
    var lower = q.toLowerCase();
    Object.keys(TIPS).forEach(function (k) {
      var t = TIPS[k];
      var hay = (k + ' ' + t.disp + ' ' + t.abbr1 + ' ' + t.abbr + ' ' + t.gene).toLowerCase();
      if (hay.indexOf(lower) >= 0) { state.matches.push(k); }
    });
  }
  var c = document.getElementById('gtMatchCount');
  if (c) { c.textContent = state.matches.length + (state.matches.length === 1 ? ' match' : ' matches'); }
  render();
  if (state.matches.length) { focusMatch(0); }
}
/* Scroll a tip into view and make its label stand out, exactly as a search hit does --
   used at load time to land the reader on the gene they clicked through from. */
function focusTip(name) {
  d3.selectAll('#tree_container text').each(function () {
    if ((d3.select(this).text() || '').trim() === name) {
      this.scrollIntoView({block: 'center', behavior: 'auto'});
      d3.select(this).style('font-weight', '700');
    }
  });
}
function focusMatch(i) {
  if (!state.matches.length) { return; }
  state.idx = (i + state.matches.length) % state.matches.length;
  var name = state.matches[state.idx];
  // Labels on screen are the short form, so the match name is the text to look for.
  d3.selectAll('#tree_container text').each(function () {
    if ((d3.select(this).text() || '').trim() === name) {
      this.scrollIntoView({block: 'center', behavior: 'smooth'});
      d3.select(this).style('font-weight', '700');
    }
  });
  showInfo(name);
}

/* ---------------------------------------------------------------- export */
function collectStyles() {
  var out = '';
  for (var i = 0; i < document.styleSheets.length; i++) {
    var rules;
    try { rules = document.styleSheets[i].cssRules; } catch (e) { continue; }
    if (!rules) { continue; }
    for (var j = 0; j < rules.length; j++) {
      if (rules[j].selectorText && rules[j].selectorText.indexOf('>') === -1) {
        out += rules[j].cssText + '\n';
      }
    }
  }
  return out;
}
function downloadBlob(blob, name) {
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url; a.download = name;
  document.body.appendChild(a); a.click();
  document.body.removeChild(a);
  setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
}
function exportSVG() {
  var svg = document.querySelector('#tree_container svg');
  if (!svg) { return; }
  var clone = svg.cloneNode(true);
  clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
  var st = document.createElementNS('http://www.w3.org/2000/svg', 'style');
  st.textContent = collectStyles();
  clone.insertBefore(st, clone.firstChild);
  downloadBlob(new Blob([new XMLSerializer().serializeToString(clone)],
                        {type: 'image/svg+xml;charset=utf-8'}), PAYLOAD.family + '_gene_tree.svg');
}
function exportPNG() {
  var svg = document.querySelector('#tree_container svg');
  if (!svg) { return; }
  var clone = svg.cloneNode(true);
  clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
  var st = document.createElementNS('http://www.w3.org/2000/svg', 'style');
  st.textContent = collectStyles();
  clone.insertBefore(st, clone.firstChild);
  var xml = new XMLSerializer().serializeToString(clone);
  var img = new Image();
  img.onload = function () {
    var c = document.createElement('canvas');
    c.width = img.width * 2; c.height = img.height * 2;
    var ctx = c.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(img, 0, 0, c.width, c.height);
    c.toBlob(function (b) { downloadBlob(b, PAYLOAD.family + '_gene_tree.png'); });
  };
  img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(xml)));
}

/* ---------------------------------------------------------------- boot */
function gtBoot() {
  if (!NWK) { return; }
  // rebuild() parses the pristine Newick, shortens the tips and renders; the palette, the
  // legend and the search all key off whatever tip names the tree ends up carrying.
  rebuild();

  /* Arrived from a gene page: land on that gene and open its orthology panel. */
  if (QGENE && QABBR) {
    var gl = state.qLabel || state.qTip;
    if (gl) { focusTip(gl); showOrtho(gl); }
    else {
      var bx = document.getElementById('gtInfo');
      if (bx) {
        bx.innerHTML = '<h4>Gene ' + esc(QGENE) + '</h4><div class="bc">This gene is not in ' +
          esc(PAYLOAD.family) + ', or its species is not in this release. ' +
          '<a href="' + esc(PAYLOAD.family ? '/genefamily_result.php?family=' +
          encodeURIComponent(PAYLOAD.family) : '/genefamily.php') + '">See the whole orthogroup &rarr;</a></div>';
        bx.style.display = 'block';
      }
    }
  }

  $('#gtExpandAll').on('click', expandAll);
  $('#gtCollapseAll').on('click', collapseAll);

  $('#gtDupToggle').on('click', function () {
    state.showDup = !state.showDup;
    $(this).toggleClass('active', state.showDup);
    render();
    if (QGENE && (state.qLabel || state.qTip)) { showOrtho(state.qLabel || state.qTip); }
  });

  var deb;
  $('#gtSearch').on('input', function () {
    clearTimeout(deb);
    var v = this.value;
    deb = setTimeout(function () { runSearch(v); }, 220);
  }).on('keydown', function (e) {
    if (e.key === 'Enter') { clearTimeout(deb); runSearch(this.value); }
  });
  $('#gtNext').on('click', function () { focusMatch(state.idx + 1); });
  $('#gtPrev').on('click', function () { focusMatch(state.idx - 1); });

  $('#gtColorBy').on('change', function () {
    state.colorBy = this.value;
    state.hidden = {};
    render();
  });

  /* rebuild() rather than render(): the label is the node name, and phylotree.js reads
     names once when it takes the Newick, so the tree has to be handed a new string.
     Expansion survives it -- state.expanded holds the *full* tip label, which is the
     same in every format (see expandSpecies), so open species stay open. */
  $('#gtLabels').on('change', function () {
    LBL = this.value;
    rebuild();
  });

  /* Every display change goes through here.  update() re-renders the labels from the
     library's own options, which resets the font size, so decorate() has to run again
     after it -- otherwise the first click on any layout button undoes the reader's Tip
     size.  (The label *text* needs no such repair: shortenTips() renamed the nodes before
     the tree was ever handed to the library, so update() re-renders the short form.) */
  function refreshDisplay() {
    state.tree.display.update();
    decorate();
  }

  $('#gtRect').on('click', function () {
    $(this).addClass('active'); $('#gtRadial').removeClass('active');
    state.tree.display.radial(false); refreshDisplay();
  });
  $('#gtRadial').on('click', function () {
    $(this).addClass('active'); $('#gtRect').removeClass('active');
    state.tree.display.radial(true); refreshDisplay();
  });
  $('#gtAlign').on('click', function () {
    var on = !$(this).hasClass('active');
    $(this).toggleClass('active', on);
    state.tree.display.alignTips(on); refreshDisplay();
  });

  $('#gtFont').on('input', function () {
    state.fontPx = parseInt(this.value, 10) || 12;
    decorate();
  });
  /* spacing_y is an accessor, not a setter: called with no argument it returns the
     current pitch (30 by default) and the library clamps it to [10,100].  Passing an
     absolute value would pin every row to that number of pixels -- and anything under
     10 is silently ignored, so a "tighter" button written that way does nothing at all.
     Nudge relative to whatever it currently is, the way the species page does. */
  function nudgeSpacing(delta) {
    state.tree.display.spacing_y(state.tree.display.spacing_y() + delta);
    refreshDisplay();
  }
  $('#gtTighter').on('click', function () { nudgeSpacing(-6); });
  $('#gtLooser').on('click', function () { nudgeSpacing(6); });
  $('#gtReset').on('click', function () {
    state.hidden = {}; state.matches = []; state.idx = -1;
    state.fontPx = 12;
    $('#gtSearch').val('');
    $('#gtFont').val(12);
    document.getElementById('gtMatchCount').textContent = '0 matches';
    $('#gtRadial').removeClass('active'); $('#gtRect').addClass('active');
    $('#gtAlign').removeClass('active');
    render();
    // 30 is the library's own default for spacing_y (fixed_width = [14, 30]).
    state.tree.display.spacing_y(30);
    refreshDisplay();
  });

  $('#gtSaveSvg').on('click', exportSVG);
  $('#gtSavePng').on('click', exportPNG);
  /* Fetched from the server rather than rebuilt here: for a family over the collapse
     threshold NWK is the species-collapsed tree, so serialising the on-screen tree would
     download a reduced version under a "full tree" label.  The endpoint always has the
     complete one. */
  $('#gtSaveNwk').on('click', function () {
    window.location = '/genetree/?family=' + encodeURIComponent(PAYLOAD.family) + '&download=full';
  });
}

<?php /* 上面已经包含了本页要用到的全部 DOM（控件、树容器、信息面板），所以不等
   DOMContentLoaded 就直接开画；万一以后这段脚本被挪到容器之前，再退回 ready。
   见文件顶部关于页脚那行同步 script 的说明。 */ ?>
if (document.getElementById('tree_container')) { gtBoot(); } else { $(gtBoot); }
</script>
<?php include "../Webpage_components.php"; print $footer; ?>
</body>
</html>
