<?php
/* Transcriptome Assembly -- per-species browser.
 *
 * Shows every predicted protein of one assembled transcriptome together with its
 * functional annotation (UniProt Swiss-Prot, Pfam, PANTHER, InterPro, GO, KEGG),
 * paginated and searchable.  Companion of trans_assembly.php (the overview).
 *
 * Search behaviour: the box accepts
 *   - a transcript or gene id            (e.g. NODE_10000_..., TRINITY_DN0_c0_g2)
 *   - an annotation accession            (GO:0005515, IPR019734, PF00069, PTHR15175, K12345)
 *   - an arbitrary keyword               (e.g. kinase, transcription factor)
 * Identifier lookups use the (abbr1, protein) / (abbr1, gene) indexes; free-text
 * lookups scan the annotation columns of the selected species only.
 */
require_once __DIR__ . '/includes/state.php';
/* 「六个来源里至少命中一个」的蛋白数（覆盖图最上面那条）。带 tmp/ 缓存，见该文件的注释。 */
require_once __DIR__ . '/includes/trans_annot.php';

if (!function_exists('cnido_num')) {
    function cnido_num($n) { return number_format((float)$n); }
}
function ts_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* mysqli bind_param() wants references, and PHP 7.4 rejects a plain value array. */
function ts_refs($arr) {
    $refs = array();
    foreach ($arr as $k => $v) { $refs[$k] = &$arr[$k]; }
    return $refs;
}
/* Run a prepared statement.  $args is a flat array whose first element is the
   mysqli type string ('' when there are no placeholders) and whose remaining
   elements are the values. */
function ts_exec_query($conn, $sql, $args) {
    $st = mysqli_prepare($conn, $sql);
    if (!$st) { return null; }
    if ($args && $args[0] !== '') {
        call_user_func_array(array($st, 'bind_param'), ts_refs($args));
    }
    if (!mysqli_stmt_execute($st)) { mysqli_stmt_close($st); return null; }
    $res = mysqli_stmt_get_result($st);
    mysqli_stmt_close($st);
    return $res;
}

$conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    die('The database could not be reached.');
}

/* ---------------------------------------------------------------------------
 * 本页列出的蛋白号，哪些在 gene_detail.php 那边**真有记录**
 *
 * trans_assembly 存的是加了翻译后缀的号（aacu_s0001.g1.t1.p1），而注释表
 * （<ABBR>_locus / _seq / _uniprot / _nr / _pfam / _ipr / _panther / _go / _KEGG）
 * 存的是 mRNA 级号（aacu_s0001.g1.t1），所以链接前必须去掉 `.pN`。
 * 2026-09-27 实测（每物种抽 100~400 行，逐号探这几张表）：12 个 Genome-derived
 * 物种里 9 个 100% 能对上、HECHI 96%、TRENI 90%、PHARR 0%；而 130 个 de novo
 * （Trinity / rnaSPAdes）物种**一个都对不上** —— 那些号是那次转录组组装自己的号，
 * 从来没有进过基因组注释表，所以那些物种一律不给链接（给了就是死链）。
 *
 * 判据不能用「gene_detail.php 返回 200」：它对任何号都会渲染出带小标题的骨架页
 * （Sequence / Pfam / InterPro …），只是各节写着 not available，于是随便什么号都
 * 「打得开」。所以这里直接按 gene_detail 真正会查的那九张表逐号探一次，一页一次
 * UNION 查询（50 行实测 15 ms）。
 *
 * @return array protein 号 => 送去 gene_detail 的基因号（只含真能查到的）
 */
function ts_gene_links($conn, $abbr, $rows) {
    $out = array();
    if (!$conn || !$rows) { return $out; }

    /* 1) 蛋白号 → 注释表用的 mRNA 级号 */
    $cand = array();
    foreach ($rows as $r) {
        $p = trim((string)$r['protein']);
        if ($p === '') { continue; }
        $cand[$p] = preg_replace('/\.p[0-9]+$/', '', $p);
    }
    if (!$cand) { return $out; }

    /* 2) 候选写法：cnido_gene_id_forms() 补上/去掉 `.1` 版本后缀。注释表与 _locus
          两处写法不一致时靠它兜住 —— 与 gene_detail.php 用的是同一个函数。 */
    $ids = array();
    foreach ($cand as $g) {
        foreach (cnido_gene_id_forms($g) as $v) { $ids[$v] = true; }
    }
    $ids = array_keys($ids);

    /* 3) 这个物种实际有哪些表（gene_detail.php 查的就是这九张）。表名里的 `_` 是
          LIKE 的通配符，必须转义，否则 AACUM\_% 会连 ABCUM_… 一起匹配。 */
    $pat  = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $abbr) . '\\_%';
    $have = array();
    $q = mysqli_query($conn, "SELECT table_name FROM information_schema.tables
                               WHERE table_schema = DATABASE() AND table_name LIKE '$pat'");
    while ($q && ($r = mysqli_fetch_row($q))) { $have[$r[0]] = true; }
    if (!$have) { return $out; }

    $sub = array();
    foreach (array('locus', 'seq', 'uniprot', 'nr', 'pfam', 'ipr', 'panther', 'go', 'KEGG') as $t) {
        $tbl = $abbr . '_' . $t;
        if (!isset($have[$tbl])) { continue; }
        $bt   = '`' . str_replace('`', '``', $tbl) . '`';
        $cols = ($t === 'locus') ? array('mRNA', 'gene') : array('gene');
        foreach ($cols as $c) { $sub[] = "SELECT `$c` AS x FROM $bt WHERE `$c` IN (%s)"; }
    }
    if (!$sub) { return $out; }

    /* 4) 一页一次查询：把本页用到的所有写法一起 IN 进去（按 200 个一批切开，
          一页最多 200 行 × 2 种写法，两批就够）。 */
    $found = array();
    foreach (array_chunk($ids, 200) as $chunk) {
        $in = array();
        foreach ($chunk as $v) { $in[] = "'" . mysqli_real_escape_string($conn, $v) . "'"; }
        $in    = implode(',', $in);
        $parts = array();
        foreach ($sub as $s) { $parts[] = sprintf($s, $in); }
        $q2 = mysqli_query($conn, 'SELECT DISTINCT x FROM (' . implode(' UNION ALL ', $parts) . ') z');
        while ($q2 && ($r = mysqli_fetch_row($q2))) { $found[$r[0]] = true; }
    }

    /* 5) 命中的才给链接，链接仍用第 1 步那个 mRNA 级号：即使表里存的是带 `.1`
          后缀的写法，gene_detail 自己也会试它的变体（同一个 cnido_gene_id_forms）。 */
    foreach ($cand as $p => $g) {
        if (isset($found[$g])) { $out[$p] = $g; continue; }
        foreach (cnido_gene_id_forms($g) as $v) {
            if (isset($found[$v])) { $out[$p] = $g; break; }
        }
    }
    return $out;
}

/* ---- module state (GET deep link > POST > session > default) ---- */
/* The filter checkboxes post name="f[]" (an array) and cnido_state expects a
   scalar, so normalise it first.  The hidden "fsub" field marks that the filter
   block was actually submitted: without it, unchecking every box would send no
   "f" at all and cnido_state would fall back to the session value, i.e. filters
   that cannot be cleared.  An explicit empty string clears them. */
if (isset($_GET['fsub'])) {
    if (!isset($_GET['f'])) {
        $_GET['f'] = '';
    } elseif (is_array($_GET['f'])) {
        $_GET['f'] = implode(',', array_map('strval', $_GET['f']));
    }
}
$__st = cnido_state('trans_asm', array(
    'species' => array('get' => 'species', 'default' => ''),
    'q'       => array('get' => 'q',       'default' => ''),
    'page'    => array('get' => 'page',    'default' => '1'),
    'per'     => array('get' => 'per',     'default' => '50'),
    'sort'    => array('get' => 'sort',    'default' => 'id'),
    'cls'     => array('get' => 'class',   'default' => ''),
    'f'       => array('get' => 'f',       'default' => ''),   // comma list: uniprot,pfam,...
    'fany'    => array('get' => 'fany',    'default' => ''),   // any | none：六来源的并集/补集
));

/* ---- species list (only species that actually have an assembly) ---- */
$list = array();       // abbr1 => Latin name
$classOf = array();    // abbr1 => class
$classList = array();
$q = mysqli_query($conn, "SELECT abbr1, species, `class` FROM trans_assembly_species ORDER BY species");
if ($q) {
    while ($r = mysqli_fetch_assoc($q)) {
        $list[$r['abbr1']] = $r['species'];
        $classOf[$r['abbr1']] = $r['class'];
        $classList[$r['class']] = true;
    }
}
$classList = array_keys($classList);
sort($classList);

$notice = '';
$species = $__st['species'];

/* resolve deep links that arrive as a Latin name or the underscore form */
if ($species !== '' && !isset($list[$species])) {
    $latin = cnido_latin_of($species);
    $found = '';
    foreach ($list as $a => $nm) { if (strcasecmp($nm, $latin) === 0 || strcasecmp($nm, $species) === 0) { $found = $a; break; } }
    if ($found === '') {
        $notice = 'No assembled transcriptome is registered for <b>' . ts_h($species) . '</b>.';
        $species = '';
    } else {
        $species = $found;
    }
}

/* class filter: when set, restrict the species dropdown, and move off a species
   that falls outside the chosen class (same rule the site's other browsers use) */
$cls = $__st['cls'];
if ($cls !== '' && !in_array($cls, $classList, true)) { $cls = ''; }
if ($cls !== '' && $species !== '' && isset($classOf[$species]) && $classOf[$species] !== $cls) {
    $species = '';
}
if ($species === '') {
    /* 没选物种时原来取 $list 的第一个，也就是学名排序第一个 —— 实际落到
       Acropora abrotanoides。站点默认物种是 Nematostella vectensis，本表里它
       有 41,327 条预测蛋白（A. abrotanoides 有 80,422 条，数据更多，但物种这一项
       按站点约定统一取 NVECT；「数据最多」是给基因/功能例子用的规则）。
       它不在当前 class 筛选里时，才按原逻辑往下找。 */
    if (isset($list['NVECT']) && ($cls === '' || (isset($classOf['NVECT']) && $classOf['NVECT'] === $cls))) {
        $species = 'NVECT';
    }
}
if ($species === '') {
    foreach ($list as $a => $nm) {
        if ($cls === '' || (isset($classOf[$a]) && $classOf[$a] === $cls)) { $species = $a; break; }
    }
}
$latin = isset($list[$species]) ? $list[$species] : '';

$listForSelect = array();
foreach ($list as $a => $nm) {
    if ($cls === '' || (isset($classOf[$a]) && $classOf[$a] === $cls)) { $listForSelect[] = $a; }
}

/* ---- species summary ---- */
$meta = null;
$mr = ts_exec_query($conn, "SELECT * FROM trans_assembly_species WHERE abbr1 = ?", array('s', $species));
if ($mr) { $meta = mysqli_fetch_assoc($mr); }

/* 这一行是**基因组**来源还是转录组组装？只有 source='Genome-derived' 那 12 个物种
   （trans_assembly_species.notes 也写着 "transcripts derived from genome annotation,
   not de novo assembled"）的蛋白是基因组基因模型翻译出来的，其余 208 个都是
   RNA-seq 从头拼的。两者的编号体系完全不同：基因组来源的号就是基因组注释里的
   mRNA 号（所以能链到基因详情页），从头拼的号只存在于那次组装里。页面上必须说清，
   否则读者会以为这一页也是转录组组装的结果。 */
$srcRaw   = $meta ? trim((string)$meta['source']) : '';
$isGenome = ($srcRaw === 'Genome-derived');

/* ---- filters / search ---- */
$ALLOWED_F = array('uniprot', 'pfam', 'panther', 'interpro', 'go', 'kegg');
$active = array();
foreach (explode(',', (string)$__st['f']) as $f) {
    $f = strtolower(trim($f));
    if (in_array($f, $ALLOWED_F, true)) { $active[$f] = true; }
}
$active = array_keys($active);

/* 六来源的并集 / 补集。下面那排复选框是**交集**语义（勾 UniProt + Pfam = 两个都
   有的蛋白），而覆盖图最上面那条「Any of the six sources」是并集 —— 用它去点
   f=uniprot,pfam,panther,interpro,go,kegg 会得到「六个来源全中」的 10,044 个，
   而不是图上写的 29,261 个。所以并集单独给一个参数，两者的数字才对得上。
   fany=none 是它的补集：任何来源都没命中的那些蛋白（组装质量核查最常问的一个问题）。 */
$fany = strtolower(trim((string)$__st['fany']));
if ($fany !== 'any' && $fany !== 'none') { $fany = ''; }

$term = trim((string)$__st['q']);
if (strlen($term) > 120) { $term = substr($term, 0, 120); }

$where  = array('abbr1 = ?');
$params = array($species);
$types  = 's';

foreach ($active as $f) {
    $col = ($f === 'kegg') ? 'kegg' : $f;
    $where[] = "($col IS NOT NULL AND $col <> '')";
}
if ($fany !== '') {
    $or = array();
    foreach ($ALLOWED_F as $f) {
        $or[] = "(COALESCE($f, '') <> '')";
    }
    $where[] = ($fany === 'any' ? '(' : 'NOT (') . implode(' OR ', $or) . ')';
}

$searchMode = 'none';
if ($term !== '') {
    if (preg_match('/^GO:\d+$/i', $term))         { $searchMode = 'go'; }
    elseif (preg_match('/^IPR\d+$/i', $term))     { $searchMode = 'interpro'; }
    elseif (preg_match('/^PF\d+$/i', $term))      { $searchMode = 'pfam'; }
    elseif (preg_match('/^PTHR\d+/i', $term))     { $searchMode = 'panther'; }
    elseif (preg_match('/^K\d{5}$/i', $term))     { $searchMode = 'kegg'; }
    else                                          { $searchMode = 'free'; }

    $like = '%' . $term . '%';

    if ($searchMode === 'free') {
        /* Try an identifier lookup first -- it is index-backed and is what most
           users mean when they paste something that looks like an id.  Only if
           that finds nothing do we scan the annotation columns. */
        $sqlId = "SELECT COUNT(*) FROM trans_assembly FORCE INDEX (ix_abbr_id) WHERE abbr1 = ? AND (protein LIKE ? OR gene LIKE ?)";
        $pfx = $term . '%';
        $nId = 0;
        $ri = ts_exec_query($conn, $sqlId, array('sss', $species, $pfx, $pfx));
        if ($ri) { $row = mysqli_fetch_row($ri); $nId = (int)$row[0]; }
        if ($nId > 0) { $searchMode = 'id'; } else { $searchMode = 'text'; }
    }

    if ($searchMode === 'id') {
        $where[] = '(protein LIKE ? OR gene LIKE ?)';
        $params[] = $term . '%'; $params[] = $term . '%'; $types .= 'ss';
    } elseif (in_array($searchMode, $ALLOWED_F, true)) {
        $col = ($searchMode === 'kegg') ? 'kegg' : $searchMode;
        $where[] = "$col LIKE ?";
        $params[] = $like; $types .= 's';
    } else {
        $where[] = '(protein LIKE ? OR gene LIKE ? OR uniprot LIKE ? OR pfam LIKE ? OR panther LIKE ?
                     OR interpro LIKE ? OR go LIKE ? OR kegg LIKE ?
                     OR uniprot_desc LIKE ? OR pfam_desc LIKE ? OR panther_desc LIKE ? OR interpro_desc LIKE ?)';
        for ($i = 0; $i < 12; $i++) { $params[] = $like; }
        $types .= str_repeat('s', 12);
    }
}

$whereSql = implode(' AND ', $where);

/* ---- sorting ---- */
/* MySQL keeps choosing the PRIMARY key for "WHERE abbr1 = ? ORDER BY id LIMIT n"
   and estimates it will find n matching rows after ~900 rows, which is wildly
   wrong for the species that sit late in the table: Oculina_patagonica took 3.4 s
   that way and 0.00 s when ix_abbr_id is used.  ANALYZE TABLE does not change the
   choice, so pin the index that matches the requested order.  Every query here
   carries "abbr1 = ?", which is the leading column of both indexes, so the hint
   can never make the index unusable. */
$sort = $__st['sort'];
$orderBy = 'id';
$idxHint = 'FORCE INDEX (ix_abbr_id)';
if ($sort === 'protein')  { $orderBy = 'protein ASC'; $idxHint = 'FORCE INDEX (ix_prot)'; }
elseif ($sort === 'long') { $orderBy = 'plen DESC, id'; }
elseif ($sort === 'short'){ $orderBy = 'plen ASC, id'; }

/* ---- pagination ---- */
$per = (int)$__st['per'];
if (!in_array($per, array(25, 50, 100, 200), true)) { $per = 50; }
$page = (int)$__st['page'];
if ($page < 1) { $page = 1; }

$total = 0;
$res = ts_exec_query($conn, "SELECT COUNT(*) FROM trans_assembly FORCE INDEX (ix_abbr_id) WHERE $whereSql",
                     array_merge(array($types), $params));
if ($res) { $row = mysqli_fetch_row($res); $total = (int)$row[0]; }
$pages = ($total > 0) ? (int)ceil($total / $per) : 1;
if ($page > $pages) { $page = $pages; }
$offset = ($page - 1) * $per;

$rows = array();
if ($total > 0) {
    $sql = "SELECT protein, gene, plen, uniprot, uniprot_desc, pfam, pfam_desc,
                   panther, panther_desc, interpro, interpro_desc, go, kegg
            FROM trans_assembly $idxHint WHERE $whereSql ORDER BY $orderBy LIMIT ? OFFSET ?";
    $res = ts_exec_query($conn, $sql, array_merge(array($types . 'ii'), $params, array($per, $offset)));
    while ($res && ($r = mysqli_fetch_assoc($res))) { $rows[] = $r; }
}

/* 本页每一行能不能给 gene_detail 链接（判据与实测见 ts_gene_links 的注释）。 */
$gdLinks = ts_gene_links($conn, $species, $rows);

/* Genome-derived 物种要不要把「号能链到基因详情页」写成一句通用的话？不能一概而论：
   12 个里 9 个的号 100% 对得上、HECHI 96%、TRENI 90%、PHARR 0%（实测见 ts_gene_links），
   所以整个物种的命中率要**现算**。只对 genome-derived 这 12 个物种算（抽 200 行，
   一次索引扫描 + 一次 UNION 探测，约 15 ms），页面上写出来的比例才有依据。
   $gdRate = array(抽样条数, 其中能链的条数)。 */
$gdRate = null;
if ($isGenome) {
    $smp = array();
    $sq  = ts_exec_query($conn, "SELECT protein FROM trans_assembly FORCE INDEX (ix_abbr_id)
                                  WHERE abbr1 = ? ORDER BY protein LIMIT 200", array('s', $species));
    while ($sq && ($r = mysqli_fetch_assoc($sq))) { $smp[] = $r; }
    if ($smp) { $gdRate = array(count($smp), count(ts_gene_links($conn, $species, $smp))); }
}
/* 本页第一行能链的号，给下面的说明文字当例子用（没有就给空串）。 */
$firstGd = '';
if ($gdLinks) { $__k = array_keys($gdLinks); $firstGd = $gdLinks[$__k[0]]; unset($__k); }

/* GO / KEGG function strings are not stored on the protein rows, so pull the
   ones referenced by this page out of the lookup tables (a couple of queries). */
$needGo = array(); $needKo = array();
foreach ($rows as $r) {
    foreach (ts_split($r['go'])   as $g) { $needGo[$g] = true; }
    foreach (ts_split($r['kegg']) as $g) { $needKo[$g] = true; }
}
$goDict = ts_dict($conn, 'trans_assembly_go', 'go_id', array_keys($needGo));
$koDict = ts_dict($conn, 'trans_assembly_ko', 'ko',    array_keys($needKo));

/* ---- helpers for display ---- */
function ts_split($s) {
    $s = trim((string)$s);
    if ($s === '') { return array(); }
    $out = array();
    foreach (explode(';', $s) as $x) { $x = trim($x); if ($x !== '') { $out[] = $x; } }
    return $out;
}
function ts_pairs($accs, $descs) {
    $a = ts_split($accs); $d = ts_split($descs);
    $out = array();
    foreach ($a as $i => $acc) { $out[] = array($acc, isset($d[$i]) ? $d[$i] : ''); }
    return $out;
}
/* (accession, description) pairs for one annotation column of one protein.
   UniProt / Pfam / PANTHER / InterPro keep their description next to the
   accession in the row itself; GO and KEGG names live in lookup tables. */
function ts_pairs_for($f, $r, $goDict, $koDict) {
    if ($f === 'go') {
        $out = array();
        foreach (ts_split($r['go']) as $g) {
            $out[] = array($g, isset($goDict[$g]) ? $goDict[$g] : '');
        }
        return $out;
    }
    if ($f === 'kegg') {
        $out = array();
        foreach (ts_split($r['kegg']) as $k) {
            $out[] = array($k, isset($koDict[$k]) ? $koDict[$k] : '');
        }
        return $out;
    }
    $dm = array('uniprot' => 'uniprot_desc', 'pfam' => 'pfam_desc',
                'panther' => 'panther_desc', 'interpro' => 'interpro_desc');
    return ts_pairs($r[$f], isset($dm[$f]) ? $r[$dm[$f]] : '');
}
/* Fetch name (and category) for a set of accessions from a small lookup table.
   Chunked because a page of 200 rows can reference a few thousand terms. */
function ts_dict($conn, $table, $col, $ids) {
    $out = array();
    $ids = array_values(array_unique(array_filter($ids, 'strlen')));
    if (!$ids) { return $out; }
    foreach (array_chunk($ids, 800) as $chunk) {
        $ph  = implode(',', array_fill(0, count($chunk), '?'));
        $res = ts_exec_query($conn,
            "SELECT `$col`, `name` FROM `$table` WHERE `$col` IN ($ph)",
            array_merge(array(str_repeat('s', count($chunk))), $chunk));
        while ($res && ($row = mysqli_fetch_row($res))) { $out[$row[0]] = $row[1]; }
    }
    return $out;
}
$TS_LINK = array(
    'uniprot'  => 'https://www.uniprot.org/uniprotkb/',
    'pfam'     => 'https://www.ebi.ac.uk/interpro/entry/pfam/',
    'panther'  => 'https://www.pantherdb.org/panther/family.do?clsAccession=',
    'interpro' => 'https://www.ebi.ac.uk/interpro/entry/InterPro/',
    'go'       => 'https://amigo.geneontology.org/amigo/term/',
    'kegg'     => 'https://www.kegg.jp/entry/',
);
$TS_LABEL = array('uniprot'=>'UniProt','pfam'=>'Pfam','panther'=>'PANTHER','interpro'=>'InterPro','go'=>'GO','kegg'=>'KEGG');

/* preserve the current query string when building links */
function ts_url($over = array()) {
    $p = $_GET;
    foreach ($over as $k => $v) { if ($v === null) { unset($p[$k]); } else { $p[$k] = $v; } }
    return 'trans_assembly_species.php?' . http_build_query($p);
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
<title><?php
    /* 基因组来源的物种不能挂「Transcriptome Assembly」这个名字 —— 标题、面包屑、
       页头三处说的是同一件事，必须一致（见上面 $isGenome 的注释）。 */
    $pageKind = $isGenome ? 'Genome-derived protein set' : 'Transcriptome Assembly';
    echo $latin !== '' ? ts_h($latin) . ' - ' . $pageKind : $pageKind;
?> - CnidoSite</title>
<meta name="description" content="<?php echo $isGenome
    ? 'Predicted proteins of ' . ts_h($latin) . ', derived from its genome assembly (gene models translated from the genome), not from a de novo transcriptome assembly.'
    : 'Annotated transcripts and predicted proteins of the assembled transcriptome of ' . ts_h($latin) . '.'; ?>" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<style>
<?php /* 页头用站内通行的 <legend> + images/header.jpg（83 个页面都这么写），不再自带
   一套深色 hero —— 这里原先的深色渐变页头是本站早期各页各写一份留下的，页头应该
   跟站内其它页面一样。 */ ?>
.ts-crumbs{font-size:15px;margin:0 0 12px 0;color:#64748b;}
.ts-crumbs a{color:#1d4ed8;text-decoration:none;}
.ts-crumbs a:hover{text-decoration:underline;}
<?php /* 「genome-derived」小标记：跟着物种名出现在面包屑里，一眼看出这一页不是转录组组装。
   用青绿（teal）而不是琥珀色 —— 琥珀色在本站一律是「数据有缺/有问题」的意思
   （.ts-note），而这里是**正常的另一种数据来源**，不该被读成警告。 */ ?>
.ts-gtag{display:inline-block;padding:1px 7px;border-radius:999px;font-size:12px;
    font-weight:600;color:#0f766e;background:#f0fdfa;border:1px solid #99f6e4;vertical-align:1px;}
<?php /* 基因组来源物种的顶部说明框。同样避开琥珀色（那是在说数据有毛病），
   用中性偏冷的石板色，与 .ts-notice（蓝，说「你被换到别的物种了」）也分开。 */ ?>
.ts-genome{background:#f8fafc;border:1px solid #cbd5e1;border-left:4px solid #0f766e;
    border-radius:8px;padding:12px 15px;font-size:16px;line-height:1.65;color:#334155;margin:0 0 16px 0;}
.ts-genome b{color:#0f172a;}
.ts-genome .prov{display:block;margin-top:6px;font-size:16px;color:#64748b;}
.ts-genome .go{display:inline-block;margin-top:7px;font-weight:600;}
<?php /* 表里能点进基因详情页的号。写成 a.ts-gd 而不是 .ts-gd：样式表里 a:link 是
   (0,1,1)，光写类名压不住，链接会变成默认蓝（本站已知的坑）。 */ ?>
a.ts-gd{color:#1d4ed8;text-decoration:none;border-bottom:1px dotted #93c5fd;}
a.ts-gd:hover{text-decoration:none;border-bottom-style:solid;background:#eff6ff;}

<?php /* ---- 装配概况：转录本 → 蛋白 → 有注释，一条链读下来 ----
   三个数字是层层收窄的关系（169,150 条转录本给出 38,920 个预测蛋白，其中 29,261
   个查得到注释），所以不做成三张并列的卡片 —— 并列会让人以为可以相加。 */ ?>
.ts-flow{display:flex;flex-wrap:wrap;align-items:stretch;gap:0;margin:0 0 14px 0;}
.ts-flow .step{flex:1 1 190px;min-width:170px;background:#fff;border:1px solid #e2e8f0;
    border-radius:10px;padding:11px 14px;}
.ts-flow .step .k{font-size:15px;color:#64748b;font-weight:600;}
.ts-flow .step .v{font-size:22px;font-weight:700;color:#1e293b;line-height:1.2;margin-top:3px;
    font-variant-numeric:tabular-nums;}
.ts-flow .step .u{font-size:15px;color:#64748b;margin-top:2px;}
.ts-flow .arrow{align-self:center;color:#94a3b8;font-size:18px;padding:0 9px;}
.ts-flow .step.key{border-color:#bfdbfe;background:#f0f9ff;}
.ts-flow .step.key .v{color:#1d4ed8;}
@media (max-width:820px){ .ts-flow .arrow{display:none;} }

<?php /* ---- 功能注释覆盖图 ----
   每一条是「这个来源注释到的蛋白占本组装预测蛋白总数的多少」。分母统一用
   $meta['proteins']：并列的八个数字读者要在脑子里做除法，一根条不用。
   整行做成链接，点下去就是把下表筛成这些蛋白（页面本来就支持 f[] 过滤）。
   a.cov-row 而不是 .cov-row 上色：样式表里 a:link 是 (0,1,1)，光写类名压不住。 */ ?>
.ts-cov{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px 18px;margin:0 0 20px 0;}
.ts-cov h2{margin:0 0 3px 0;font-size:15px;font-weight:700;color:#1e293b;}
.ts-cov .sub{font-size:16px;color:#64748b;margin:0 0 14px 0;line-height:1.6;}
a.cov-row{display:grid;grid-template-columns:190px 1fr 76px 62px;align-items:center;gap:12px;
    margin-bottom:7px;font-size:15px;text-decoration:none;border-radius:7px;padding:3px 6px;}
a.cov-row:hover{background:#f8fafc;}
a.cov-row .lab{color:#334155;font-weight:600;display:flex;align-items:center;gap:7px;}
a.cov-row .dot{width:9px;height:9px;border-radius:50%;flex:none;}
a.cov-row .track{background:#eef2f7;border-radius:6px;height:15px;overflow:hidden;}
<?php /* display:block 是必须的：.fill 是 <span>，作为 grid 项的 .track 会被块化，
   但它里面这个 span 不会 —— 行内元素上的 height:100% 无效，条就整根不见
   （表现是行、数字、百分比都在，只有条是空的）。 */ ?>
a.cov-row .fill{display:block;height:100%;border-radius:6px;min-width:3px;}
a.cov-row .n{text-align:right;color:#334155;font-variant-numeric:tabular-nums;}
a.cov-row .pct{text-align:right;color:#475569;font-variant-numeric:tabular-nums;font-weight:600;}
a.cov-row.any .lab,a.cov-row.any .n,a.cov-row.any .pct{color:#1d4ed8;font-weight:700;}
a.cov-row.any{border-bottom:1px solid #eef2f7;border-radius:7px 7px 0 0;margin-bottom:9px;padding-bottom:8px;}
a.cov-row.on{background:#eff6ff;}
a.cov-row.on .lab::after{content:"shown below";font-size:12px;font-weight:600;color:#1d4ed8;}

<?php /* 每行那条覆盖率小格子：190px 标签 + 1fr 条 + 76 + 62，加三道 12px 间距
   和行自身的内边距，最少要 376px 才排得下。375px 屏上栏只有 283，四列
   一起把它撑到 410。窄屏改成两行：标签|数量|百分比 一行，进度条独占
   下面一整行 —— 四样信息都还在，只是换了个排法。
   minmax(0,1fr) 而不是 1fr：1fr 的下限是 auto，长标签照样顶出去。

   这一块必须写在上面那些 a.cov-row 基础规则**之后**：媒体查询本身不加
   特异性，同是 (0,1,1) 时靠源序决胜，写在前面就是死代码（第一版就是这么
   静默失效的）。 */ ?>
@media (max-width:700px){
  a.cov-row{
    grid-template-columns:minmax(0,1fr) auto auto;
    grid-template-areas:"lab n pct" "track track track";
    row-gap:5px;
  }
  a.cov-row .lab{grid-area:lab;min-width:0;}
  a.cov-row .track{grid-area:track;}
  a.cov-row .n{grid-area:n;}
  a.cov-row .pct{grid-area:pct;}
}
.ts-cov .hint{font-size:16px;color:#64748b;margin:12px 0 0 0;line-height:1.6;}
.ts-cov .hint b{color:#334155;}
<?php /* 登记数与实际存量对不上时的那句提醒。少见（220 个物种里 8 个），但不说的话
   用户会拿汇总页上的 proteins 跟这里的百分号对，怎么算都算不平。 */ ?>
.ts-cov .gap{font-size:16px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;
             border-radius:8px;padding:8px 11px;margin:12px 0 0 0;line-height:1.6;}

.ts-form{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:15px 17px;margin-bottom:16px;}
.ts-form h2{margin:0 0 12px 0;font-size:15px;font-weight:700;color:#1e293b;}
.ts-row{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;}
.ts-row + .ts-row{margin-top:12px;}
.ts-f label{display:block;font-size:15px;color:#64748b;font-weight:600;margin-bottom:4px;}
.ts-f select,.ts-f input[type=text]{padding:8px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:15px;background:#fff;color:#1e293b;}
.ts-f input[type=text]{min-width:300px;}
.ts-btn{padding:8px 18px;border:1px solid #1d4ed8;background:#1d4ed8;color:#fff;border-radius:7px;font-size:15px;font-weight:600;cursor:pointer;}
.ts-btn:hover{background:#1e40af;}
.ts-btn.ghost{background:#fff;color:#334155;border-color:#cbd5e1;font-weight:500;}
.ts-btn.ghost:hover{background:#f1f5f9;}
.ts-chk{display:flex;flex-wrap:wrap;gap:14px;font-size:15px;color:#334155;}
.ts-chk label{display:flex;align-items:center;gap:5px;font-weight:500;cursor:pointer;}
.ts-summary{font-size:15px;color:#475569;margin:0 0 10px 0;}
.ts-summary b{color:#1e293b;}
.ts-wrap{overflow-x:auto;border:1px solid #e2e8f0;border-radius:10px;background:#fff;}
<?php /* 表本身挂 gridtable：表头底色、下边框、留白、行分隔线、字号、红色链接全部由
   templatemo_style.css 的共用样式提供（与 core 的 table.cc 一致）。
   原来这套是照着共用样式抄的近似值 —— 字号 13px、悬停写在 tr 上（td 有不透明
   底色会盖住它，那条一直是死的，现在由共用样式的 `tbody tr:hover td` 接管）。
   这里只留本页特有的：scroll 相关、展开行样式、以及本页的左对齐。
   所有选择器都补上 table.gridtable 和 tr，否则压不过共用样式（媒体查询也是这样，
   见文件末尾那段）。 */ ?>
table.ts-table{border-collapse:separate;border-spacing:0;}
table.gridtable.ts-table thead th{position:sticky;top:0;z-index:2;}
table.gridtable.ts-table tbody tr.main{cursor:pointer;}
<?php /* 本表每列装的是号、注释串、描述这类文字，统一后默认居中会读成一团 —— 整张表左对齐。
   数字列单独右对齐，所以那条必须写在这条**之后**（两者特异性相同，靠顺序分胜负）。 */ ?>
table.gridtable.ts-table tr th,
table.gridtable.ts-table tr td{text-align:left;}
table.gridtable.ts-table tr td.num{text-align:right;font-variant-numeric:tabular-nums;color:#334155;white-space:nowrap;}
<?php /* max-width 是跟着字号走的：这列的字号 2026-09-27 统一时由 12px 抬到 15px，
   宽度不跟着放，省略号就会多截掉一截 —— 实测截断格数从 49 涨到 100、最长截断
   从 17px 涨到 103px。330 × 15/12 ≈ 412，补回原来的可见字符数。 */ ?>
table.gridtable.ts-table td.id{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:15px;max-width:412px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
table.gridtable.ts-table td.ann{max-width:235px;}
table.gridtable.ts-table tr.detail td{background:#f8fafc;border-bottom:2px solid #e2e8f0;padding:12px 14px;}
<?php /* ---------------------------------------------------------------------------
   桌面档（≥1201px）：给每一列写死宽度，让表格一定装得进容器。

   病灶：表的自然宽度由「最宽的内容」决定，而这一页最宽的内容是那一列转录本号
   （`NODE_100001_length_328_cov_5.598566_g87532_i0.p1`，51 字符）—— `.id` 是
   `white-space:nowrap`，所以该列的最小宽度就是整串号的宽度，auto layout 再
   怎么排也压不下去。全站容器最宽 1588px，而表格实测 1853px：**任何视口下都装
   不下**（1440 屏容器 1398、1920 屏 1588，都溢出），右边的 InterPro / GO / KEGG
   三列直接被裁在 `.ts-wrap` 的圆角框外，只能横滑才看得见。

   auto layout 下 `max-width` 是治不了这个的：它只限制格子画多宽，不参与列宽
   计算，所以最小宽度仍由 nowrap 的那串号撑着。要真正压住只有 `table-layout:
   fixed` —— 这是 templatemo_style.css 自己写明的口子（「A page that genuinely
   needs equal columns can ask for them with table.gridtable.<class>{table-layout:
   fixed}」）。

   列宽是按「每列至少要放得下什么」定的，不是平均分：
     caret 2.3%      箭头
     ID   35.4%      51 字符的号（15px 等宽 ≈ 9.6px/字符 → 约 490px）
     len   7.3%      「Length (aa)」折成两行后的最长一行「Length」≈ 52px
     注释 ×6 9.1667% 角标最宽的是 GO:0005515 ≈ 86px（含内边距），描述本就钳在 2 行
   九个加起来正好 100%（fixed 下浏览器按这个比例分，不留 auto 列，否则那一列会
   被前面几列的「宽度 + 内边距」挤成 0 宽 —— 实测 KEGG 列归零、表头「KEGG」竖着
   折成 4 行、表头行 121px 高）。

   `box-sizing:border-box` 不是顺手写的，是这套百分比能成立的前提：table-cell 默
   认 content-box，`width:9.17%` 会**再加**上左右各 10px 内边距，九列合计就超出
   表宽，最后一列照样归零。border-box 之后 `width:9.17%` 就是这一列的总宽（内边
   距含在里面）。同一段 CSS 里 `calc(9.17% - 20px)` 试过，Chromium 在 fixed 布局
   下不认，整表退回等宽列（实测九列全变 155px）。

   min-width 是给 1201–1241 这几档留的：那几档容器只有 1160–1200，按百分比缩下
   去 `GO:0005515` 这种角标（86px）会被压出格子。压到 1200 就停，更窄时由
   `.ts-wrap` 的横向滚动兜底（是「能滑」，不是「被裁」）。1280 屏的容器是 1238，
   在 1200 之上 —— 所以常见笔记本宽度下不会出现横滑条。

   th 的 `white-space:normal` 必须开：全站 `table.gridtable th{white-space:nowrap}`
   下，比列还宽的表头会**溢出到邻列**（MAGs.php 记过这个坑）。这里最宽的表头
   「Transcript / protein」155px 远小于 500px 的列，只有「Length (aa)」会折成两行。

   td.id 的 max-width 在这一档让位给 fixed 算出来的列宽：写死 412px 会反过来把
   ID 列卡在 412，那 51 字符的号就又看不全了。

   全部收在 `@media (min-width:1201px)` 里，≤1200 一个字节都不变 —— 窄屏本来就
   走 `.ts-wrap` 横滑，改了反而会把那套顶掉。 */ ?>
@media (min-width:1201px){
  table.gridtable.ts-table{table-layout:fixed;width:100%;min-width:1200px;}
  table.gridtable.ts-table thead th{white-space:normal;box-sizing:border-box;}
  table.gridtable.ts-table td.id{max-width:none;}
  table.gridtable.ts-table thead th:nth-child(1){width:2.3%;}
  table.gridtable.ts-table thead th:nth-child(2){width:35.4%;}
  table.gridtable.ts-table thead th:nth-child(3){width:7.3%;}
  table.gridtable.ts-table thead th:nth-child(4),
  table.gridtable.ts-table thead th:nth-child(5),
  table.gridtable.ts-table thead th:nth-child(6),
  table.gridtable.ts-table thead th:nth-child(7),
  table.gridtable.ts-table thead th:nth-child(8),
  table.gridtable.ts-table thead th:nth-child(9){width:9.16667%;}
}
.ts-chip{display:inline-block;padding:1px 6px;margin:0 4px 3px 0;border-radius:5px;font-size:12px;font-family:ui-monospace,Menlo,Consolas,monospace;text-decoration:none;border:1px solid transparent;white-space:nowrap;}
.ts-chip.uniprot{background:#f0f9ff;color:#1d4ed8;border-color:#bfdbfe;}
.ts-chip.interpro{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe;}
.ts-chip.pfam{background:#f0fdf4;color:#0f766e;border-color:#99f6e4;}
.ts-chip.panther{background:#fffbeb;color:#b45309;border-color:#fde68a;}
.ts-chip.go{background:#f0fdf4;color:#15803d;border-color:#bbf7d0;}
.ts-chip.kegg{background:#fff1f2;color:#be123c;border-color:#fecdd3;}
.ts-chip:hover{filter:brightness(.95);text-decoration:underline;}
.ann-item{margin:0 0 7px 0;}
.ann-item:last-child{margin-bottom:0;}
<?php /* 注释条目的描述正文。2026-09-27 全站统一字号时它和 .ts-chip 角标的旧值同为 11.5px，
   被按值分档一起判成「极小辅助文字」落进 12px 下限档 —— 但这是描述正文，全页 9,094
   字符，是本页除了 ID 之外真正要读的内容。2026-09-28 抬到 15px。
   max-height 用的是 em 而不是 px，所以两行摘要的高度会跟着字号一起走，改字号不会
   少显示内容；-webkit-line-clamp:2 同理。 */ ?>
.ann-desc{font-size:15px;color:#64748b;line-height:1.35;margin-top:3px;overflow:hidden;
          max-height:2.7em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;}
.ts-more{font-size:12px;color:#64748b;font-family:inherit;cursor:help;}
.ts-detail-item{margin-bottom:3px;}
.ts-none{color:#64748b;}
.ts-dl{display:grid;grid-template-columns:118px 1fr;gap:4px 12px;font-size:15px;color:#334155;}
.ts-dl .t{color:#64748b;font-weight:600;}
.ts-dl .d{color:#64748b;}
.ts-note{background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:8px;padding:10px 13px;font-size:16px;line-height:1.6;margin:0 0 16px 0;}
.ts-notice{background:#f0f9ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:8px;padding:10px 13px;font-size:16px;margin:0 0 16px 0;}
<?php /* 翻页按钮照站内 go_result.php 那一套的观感（白底细边、悬停描蓝、当前页实心蓝），
   只把类名留在本页 —— 那一套的 CSS 每个用到分页的页面各自带一份，这是本站既有做法。
   选择器写成 a.ts-pg 而不是 .ts-pg：a:link 是 (0,1,1)，压不过就会变成链接蓝。 */ ?>
.ts-pager{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:16px 0 6px 0;font-size:15px;}
a.ts-pg,.ts-pager span{padding:7px 13px;border:1px solid #e2e8f0;border-radius:6px;
    text-decoration:none;color:#475569;background:#fff;min-width:20px;text-align:center;}
a.ts-pg:hover{background:#f0f9ff;border-color:#1d4ed8;color:#1d4ed8;}
.ts-pager span.cur{background:#1d4ed8;border-color:#1d4ed8;color:#fff;font-weight:600;}
.ts-pager span.dis{color:#94a3b8;background:#f8fafc;}
.ts-empty{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:38px 20px;text-align:center;color:#64748b;font-size:16px;}
.ts-meta{font-size:16px;color:#64748b;line-height:1.7;margin-top:16px;}
code.ts-k{background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:15px;}
</style>
</head>
<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<?php /* 导航栏保持不变 */ ?>
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
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li><li><a href="/microsynteny.php">Microsynteny Analysis</a></li><li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li><li><a href="/mitdata.php">Mitogenomic Data</a></li>
                </ul>
            <li><a href="#" class="current">Transcriptome</a>
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
<div id="column">

<p class="ts-crumbs"><a href="trans_assembly.php">Transcriptome Assembly</a> &nbsp;&rsaquo;&nbsp; <?php
    echo $latin !== '' ? '<em>' . ts_h($latin) . '</em>' : 'species browser';
    if ($isGenome) { echo ' <span class="ts-gtag">genome-derived</span>'; } ?></p>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b><?php
    echo $isGenome ? 'Genome-derived protein set' : 'Transcriptome Assembly';
    echo $latin !== '' ? ': <i>' . ts_h($latin) . '</i>' : ''; ?></b></legend>
<p class="paleo-intro"><?php
    if ($isGenome) {
        echo 'Predicted proteins of <i>' . ts_h($latin) . '</i> derived from its <b>genome assembly</b>, ';
        echo 'with their functional annotation. Search or browse every predicted protein below; ';
        echo 'click a row to see the full annotation, or an identifier to open its gene detail page.';
    } elseif ($meta) {
        echo 'Assembled transcriptome and predicted-protein annotation for this species. ';
        echo 'Search or browse every predicted protein below; click a row to see the full annotation.';
    } else {
        echo 'Browse the predicted proteins of an assembled transcriptome.';
    }
?></p>

<?php if ($notice !== ''): ?>
<div class="ts-notice"><?php echo $notice; ?> Showing the full species list instead.</div>
<?php endif; ?>

<?php if ($isGenome): ?>
<?php /* 这一页是基因组基因模型，不是 RNA-seq 拼出来的转录组 —— 说明必须显眼且说全：
       来源、和转录组组装的区别、编号为什么能链到基因详情页、以及去哪看基因组记录。
       这句「not de novo assembled」的原话是 trans_assembly_species.notes 里的，放在
       .prov 里保留原始出处，免得读者以为这是页面自己编的说法。 */ ?>
<div class="ts-genome">
    <b>This is a genome assembly, not a transcriptome assembly.</b>
    The proteins listed here were <b>predicted from the genome assembly</b> of
    <i><?php echo ts_h($latin); ?></i> &mdash; gene models called on the assembled genome and their
    coding sequences translated &mdash; not assembled de novo from RNA-seq reads. Like every other
    species whose <b>Assembly</b> column on the <a href="trans_assembly.php">overview page</a>
    reads <i>Genome-derived</i>, its identifiers <b>are</b> the mRNA identifiers of the genome
    annotation &mdash; so a transcript that is part of that annotation links to its
    <b>gene detail</b> page (sequence, gene structure, UniProt / Pfam / InterPro / PANTHER / GO /
    KEGG and, where present, NCBI NR hits).
    The pipeline note behind this row reads
    &ldquo;<?php echo ts_h($meta['notes']); ?>&rdquo;.
    <a class="go" href="speciesinfo.php?species=<?php echo urlencode($species); ?>">Genome assembly record for <?php echo ts_h($latin); ?> &rarr;</a>
</div>
<?php elseif ($meta && trim((string)$meta['notes']) !== ''): ?>
<div class="ts-note"><b>Data note.</b> <?php echo ts_h($meta['notes']); ?></div>
<?php endif; ?>

<?php
/* ---- 这个组装注释到什么程度 ----------------------------------------------
   这一块回答的是使用者打开这个页面时的第一个问题：这个物种的蛋白，我查得到注释吗？
   原先这里是八个并排的卡片（转录本 / 蛋白 / 六个来源各一张），全是裸数字：
   要知道「注释得全不全」得自己在脑子里做八次除法，而且第一张卡片（转录本数）
   和最后那张（KEGG 命中数）根本不是一个量纲上的东西，并排放着像是可以相加。

   所以改成：上面一条「转录本 → 预测蛋白 → 有注释的蛋白」的收窄链，下面每个来源
   一根条，分母统一是**本组装的预测蛋白数**（不是六个来源里最大的那个，也不是
   注释记录数）—— 「这个来源覆盖了蛋白组的百分之几」才是能直接下判断的数。
   条本身是指向下表的入口：点一根条 = 把下表筛成这些蛋白（页面本来就支持 f[] 过滤），
   图不是装饰，是查询的起点。

   ⚠ 每个来源的蛋白数不能取 trans_assembly_species 的 n_* 列：那几列数的是**基因**
   （= 代表序列，与同表的 reps 一列对得上），而本页列表和筛选的单位是**蛋白**
   （一个基因的多条 isoform 各占一行、注释相同），两者差 2 倍左右。混用会得到
   「条上写 6,028、点下去筛出 13,489 条」这种图与表各说各话的结果。
   七个（六个来源 + 并集）一起现算，见 includes/trans_annot.php。 */
$COV = array(
    'uniprot'  => array('UniProt Swiss-Prot', '#1d4ed8'),
    'pfam'     => array('Pfam',               '#0f766e'),
    'panther'  => array('PANTHER',            '#b45309'),
    'interpro' => array('InterPro',           '#7c3aed'),
    'go'       => array('Gene Ontology',      '#15803d'),
    'kegg'     => array('KEGG pathway',       '#be123c'),
);
$cnts  = cnido_trans_annot_counts($conn, $species);        // 拿不到是 null
$metaProt = (int) $meta['proteins'];
/* 分母一律用**现算的存量**（$cnts['total']），不是登记的那个 proteins：
   图上的每根条点下去就是一个列表查询，分子分母必须跟列表同一个集合，否则
   条上写着 6,028、点开 13,489（这个 bug 已经出过一次）。
   代价是跟汇总页登记的 proteins 对不上时会显得矛盾 —— 220 个物种里有 8 个
   对不上，其中 7 个是表里的记录**少于**登记数（Agaricia_lamarck 只装了
   1,212 / 159,167），所以下面要显式说明。 */
$denom = $cnts ? $cnts['total'] : $metaProt;
$anyN  = $cnts ? $cnts['any'] : null;
$anyPct = ($anyN !== null && $denom > 0) ? 100.0 * $anyN / $denom : 0;
/* reps 是登记表里「代表序列（去重后的基因）」数。它是按完整预测蛋白集算的，
   部分装载的物种会出现 reps > 实际行数这种不可能的关系，这时不印。 */
$reps  = (int) $meta['reps'];
$repsOk = ($reps > 0 && $reps <= $denom);
?>
<?php if ($meta): ?>
<div class="ts-cov">
    <h2>How much of this assembly is annotated</h2>
    <p class="sub">Every predicted protein of <em><?php echo ts_h($latin); ?></em> was searched against
       six annotation sources. The bars below are the share of this assembly&rsquo;s
       <b><?php echo cnido_num($denom); ?></b> predicted proteins that carry at least one hit from each source.</p>

    <div class="ts-flow">
        <div class="step">
            <div class="k">Transcripts</div>
            <div class="v"><?php echo cnido_num($meta['transcripts']); ?></div>
            <div class="u"><?php echo number_format($meta['bp']/1e6, 1); ?> Mb &middot; N50 <?php echo cnido_num($meta['n50']); ?> bp</div>
        </div>
        <div class="arrow" aria-hidden="true">&rarr;</div>
        <div class="step">
            <div class="k">Predicted proteins</div>
            <div class="v"><?php echo cnido_num($denom); ?></div>
            <div class="u"><?php echo $repsOk ? cnido_num($reps) . ' representative sequences' : ''; ?></div>
        </div>
        <?php if ($anyN !== null): ?>
        <div class="arrow" aria-hidden="true">&rarr;</div>
        <div class="step key">
            <div class="k">With functional annotation</div>
            <div class="v"><?php echo cnido_num($anyN); ?></div>
            <div class="u"><?php echo $denom > 0 ? number_format($anyPct, 1) . '% of the proteome' : ''; ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($anyN !== null): ?>
    <a class="cov-row any<?php echo $fany === 'any' ? ' on' : ''; ?>"
       href="<?php echo ts_h(ts_url(array('f' => '', 'fany' => 'any', 'fsub' => 1, 'page' => 1))); ?>"
       title="Show the <?php echo cnido_num($anyN); ?> proteins that carry at least one hit, from any source">
        <span class="lab"><span class="dot" style="background:#1d4ed8;"></span>Any of the six sources</span>
        <span class="track"><span class="fill" style="width:<?php echo number_format($anyPct, 2); ?>%;background:#1d4ed8;"></span></span>
        <span class="n"><?php echo cnido_num($anyN); ?></span>
        <span class="pct"><?php echo $denom > 0 ? number_format($anyPct, 1) . '%' : '&ndash;'; ?></span>
    </a>
    <?php endif; ?>

    <?php if ($cnts) { foreach ($COV as $f => $m):
        $n   = $cnts[$f];
        $pct = $denom > 0 ? 100.0 * $n / $denom : 0;
        $on  = in_array($f, $active, true);
    ?>
    <a class="cov-row<?php echo $on ? ' on' : ''; ?>"
       href="<?php echo ts_h(ts_url(array('f' => $f, 'fany' => '', 'fsub' => 1, 'page' => 1))); ?>"
       title="Show the <?php echo cnido_num($n); ?> proteins with a <?php echo ts_h($m[0]); ?> hit">
        <span class="lab"><span class="dot" style="background:<?php echo $m[1]; ?>;"></span><?php echo ts_h($m[0]); ?></span>
        <span class="track"><span class="fill" style="width:<?php echo number_format($pct, 2); ?>%;background:<?php echo $m[1]; ?>;"></span></span>
        <span class="n"><?php echo cnido_num($n); ?></span>
        <span class="pct"><?php echo $denom > 0 ? number_format($pct, 1) . '%' : '&ndash;'; ?></span>
    </a>
    <?php endforeach; } ?>

    <p class="hint">
        <b>Click a bar to list those proteins below.</b>
        A protein usually carries hits from several sources, so these shares overlap and do not add up to 100%.
        <?php if ($anyN !== null && $denom > 0 && $anyN < $denom): ?>
        The remaining <?php echo cnido_num($denom - $anyN); ?> predicted proteins
        (<?php echo number_format(100.0 * ($denom - $anyN) / $denom, 1); ?>%)
        <a href="<?php echo ts_h(ts_url(array('f' => '', 'fany' => 'none', 'fsub' => 1, 'page' => 1))); ?>">had no hit in any of the six sources</a>
        <?php /* 原来这句接着写「most are short open reading frames without a known
                 homologue」，两个毛病：
                 (1) 后半截是循环论证 —— 「六个来源都没命中」本身就是「没有已知同源物」
                     的定义，等于把前一句换个说法再说一遍；
                 (2) 前半截与数据不符。全库 trans_assembly 共 13,111,410 行，最短的
                     plen 是 84 aa，<100 aa 的只有 0.9%（120,727 行）—— 上游预测流程
                     早就把更短的丢掉了，所以「多数是短开放阅读框」对这个集合根本
                     说不通。真正量得出来的是「显著偏短」：抽 5 个组装做中位数对比，
                     无命中蛋白 132/151/205/207/255 aa，有命中蛋白
                     175/240/391/418/459 aa（APOCU/OPATA/CNATA/PDIVA/AHEMP，
                     都取 ROW_NUMBER 的精确中位数）—— 大约是后者的一半到四分之三。
                 故只保留量得出来的那一半，且用相对说法，不写死阈值。 */ ?>
        &mdash; they are on the whole shorter than the proteins that do carry a hit,
        with a median length about half to three-quarters as long.
        <?php endif; ?>
    </p>

    <?php /* 汇总页那几个登记数字（proteins/reps）来自上游统计，跟本库实际装了多少
             行不总是一致：220 个物种里 8 个不一致，7 个是**装少了**
             （Agaricia_lamarck 只有 1,212 行，登记 159,167）。图上的百分号是照
             本库的存量算的，不说清楚用户会拿汇总页的数字来对，怎么算都算不平。
             不一致就说明，差一行也说 —— 阈值是拍脑袋，事实不是。 */ ?>
    <?php if ($cnts && $metaProt > 0 && $cnts['total'] !== $metaProt): ?>
    <p class="gap">
        <b>Note on this assembly.</b>
        <?php if ($cnts['total'] < $metaProt): ?>
        <i><?php echo ts_h($latin); ?></i> is registered with <b><?php echo cnido_num($metaProt); ?></b>
        predicted proteins, but this database currently holds <b><?php echo cnido_num($cnts['total']); ?></b>
        protein records for it. The shares above are computed over those
        <?php echo cnido_num($cnts['total']); ?>, so they describe the part of the proteome that can be
        listed here, not the whole registered proteome.
        <?php else: ?>
        This database holds <b><?php echo cnido_num($cnts['total']); ?></b> protein records for
        <i><?php echo ts_h($latin); ?></i>, while the registered proteome size is
        <b><?php echo cnido_num($metaProt); ?></b>. The shares above are computed over the
        records this database holds.
        <?php endif; ?>
    </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<form class="ts-form" method="get" action="trans_assembly_species.php">
    <h2>Find a protein in this assembly</h2>
    <input type="hidden" name="fsub" value="1" />
    <div class="ts-row">
        <div class="ts-f">
            <label for="tsCls">Class</label>
            <select id="tsCls" name="class" onchange="this.form.submit()">
                <option value="">All classes</option>
                <?php foreach ($classList as $c): ?>
                <option value="<?php echo ts_h($c); ?>"<?php echo $c === $cls ? ' selected="selected"' : ''; ?>><?php echo ts_h($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ts-f">
            <?php /* id 不能叫 species：templatemo_style.css 里有一条给模板自己的 #species
                 缩略图用的规则（float:left;width:190px;height:190px），ID 选择器压得过
                 本页任何类名规则，于是这个下拉框一直是个 190×190 的方块，把整行挤歪。 */ ?>
            <label for="tsSpecies">Species <span style="font-weight:400;color:#64748b;">(<?php echo count($listForSelect); ?> with an assembly)</span></label>
            <select id="tsSpecies" name="species">
                <?php echo cnido_options($listForSelect, $species, false, $list); ?>
            </select>
        </div>
        <div class="ts-f">
            <label for="tsQ">Search this species</label>
            <input type="text" id="tsQ" name="q" value="<?php echo ts_h($term); ?>"
                   placeholder="transcript / gene id, GO:0005515, IPR019734, PF00069, kinase ..." />
        </div>
        <div class="ts-f">
            <label for="tsPer">Per page</label>
            <select id="tsPer" name="per">
                <?php foreach (array(25, 50, 100, 200) as $n): ?>
                <option value="<?php echo $n; ?>"<?php echo $n === $per ? ' selected="selected"' : ''; ?>><?php echo $n; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ts-f">
            <label for="tsSort">Sort by</label>
            <select id="tsSort" name="sort">
                <option value="id"<?php echo $sort === 'id' ? ' selected="selected"' : ''; ?>>Assembly order</option>
                <option value="protein"<?php echo $sort === 'protein' ? ' selected="selected"' : ''; ?>>Transcript ID</option>
                <option value="long"<?php echo $sort === 'long' ? ' selected="selected"' : ''; ?>>Longest protein first</option>
                <option value="short"<?php echo $sort === 'short' ? ' selected="selected"' : ''; ?>>Shortest protein first</option>
            </select>
        </div>
        <div><button class="ts-btn" type="submit">Search</button></div>
        <div><a class="ts-btn ghost" href="<?php echo ts_h(ts_url(array('q' => '', 'f' => '', 'fany' => '', 'fsub' => 1, 'page' => 1))); ?>" style="text-decoration:none;display:inline-block;">Clear</a></div>
    </div>
    <div class="ts-row">
        <div class="ts-f" style="flex:1;">
            <label>Show only proteins with</label>
            <div class="ts-chk">
                <?php foreach ($ALLOWED_F as $f): ?>
                <label><input type="checkbox" name="f[]" value="<?php echo $f; ?>"<?php echo in_array($f, $active, true) ? ' checked="checked"' : ''; ?> /> <?php echo ts_h($TS_LABEL[$f]); ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</form>

<p class="ts-summary">
<?php
$modeLab = array('id'=>'transcript / gene identifier','text'=>'keyword in the annotation',
                 'go'=>'GO term','interpro'=>'InterPro entry','pfam'=>'Pfam domain',
                 'panther'=>'PANTHER family','kegg'=>'KEGG orthologue','none'=>'');
if ($total === 0) {
    echo 'No proteins matched in <em>' . ts_h($latin) . '</em>.';
} else {
    echo 'Showing <b>' . cnido_num($offset + 1) . '&ndash;' . cnido_num(min($offset + $per, $total)) . '</b> of <b>'
       . cnido_num($total) . '</b> predicted proteins in <em>' . ts_h($latin) . '</em>';
    if ($term !== '') { echo ' matching <b>' . ts_h($term) . '</b> as a ' . ts_h($modeLab[$searchMode]) . ''; }
    if ($active) { echo ' (filtered to proteins with ' . ts_h(implode(', ', array_map(function ($x) use ($TS_LABEL) { return $TS_LABEL[$x]; }, $active))) . ')'; }
    if ($fany === 'any')  { echo ' (filtered to proteins with at least one hit, from any source)'; }
    if ($fany === 'none') { echo ' (filtered to proteins with no hit in any of the six sources)'; }
    echo '.';
}
?>
</p>

<?php if ($total === 0): ?>
<div class="ts-empty">
    <p style="margin:0 0 8px 0;">Nothing matched <?php echo $term !== '' ? '<b>' . ts_h($term) . '</b>' : 'the current filters'; ?>.</p>
    <p style="margin:0;font-size:16px;">Try a transcript id (e.g. <code class="ts-k">NODE_10000</code>), an accession
       (<code class="ts-k">GO:0005515</code>, <code class="ts-k">IPR019734</code>, <code class="ts-k">PF00069</code>,
       <code class="ts-k">PTHR15175</code>, <code class="ts-k">K12345</code>) or a plain keyword
       (e.g. <code class="ts-k">kinase</code>).</p>
</div>
<?php else: ?>
<div class="ts-wrap">
<table class="gridtable ts-table">
<thead>
<tr>
    <th>&nbsp;</th>
    <th>Transcript / protein</th>
    <th style="text-align:right;">Length (aa)</th>
    <th>UniProt</th>
    <th>Pfam</th>
    <th>PANTHER</th>
    <th>InterPro</th>
    <th>GO</th>
    <th>KEGG</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $i => $r): ?>
<tr class="main" onclick="tsToggle(<?php echo $i; ?>)">
    <td style="color:#64748b;font-size:12px;" title="Show full annotation" id="tsCar<?php echo $i; ?>">&#9656;</td>
    <td class="id" title="<?php echo ts_h($r['protein']); ?>"><?php
        /* 只在 gene_detail.php 那边真查得到这个号时才给链接（$gdLinks 的判据见
           ts_gene_links 的注释）：de novo 物种的号不在基因组注释表里，给了就是死链。
           onclick 里 stopPropagation 是必须的，否则点链接会顺带展开这一行。 */
        if (isset($gdLinks[$r['protein']])) {
            echo '<a class="ts-gd" href="gene_detail.php?gene=' . urlencode($gdLinks[$r['protein']])
               . '&amp;species=' . urlencode($species) . '" title="Open the gene detail page for '
               . ts_h($gdLinks[$r['protein']]) . '" onclick="event.stopPropagation()">'
               . ts_h($r['protein']) . '</a>';
        } else {
            echo ts_h($r['protein']);
        }
    ?></td>
    <td class="num"><?php echo cnido_num($r['plen']); ?></td>
    <?php
    /* Each entry takes two lines: the accession, then what that accession does.
       Show the first MAXANN entries and fold the rest into "+N more" (the tooltip
       lists them, and the expandable row shows everything). */
    $MAXANN = 2;
    foreach ($ALLOWED_F as $f) {
        $pairs = ts_pairs_for($f, $r, $goDict, $koDict);
        if (!$pairs) { echo '<td class="ann"><span class="ts-none">&ndash;</span></td>'; continue; }
        $show = array_slice($pairs, 0, $MAXANN);
        $rest = count($pairs) - count($show);
        echo '<td class="ann">';
        foreach ($show as $pr) {
            echo '<div class="ann-item">';
            echo '<a class="ts-chip ' . $f . '" href="' . ts_h($TS_LINK[$f] . urlencode($pr[0]))
               . '" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()">'
               . ts_h($pr[0]) . '</a>';
            if ($pr[1] !== '') {
                echo '<div class="ann-desc" title="' . ts_h($pr[1]) . '">' . ts_h($pr[1]) . '</div>';
            }
            echo '</div>';
        }
        if ($rest > 0) {
            $hidden = array();
            foreach (array_slice($pairs, $MAXANN) as $pr) {
                $hidden[] = $pr[0] . ($pr[1] !== '' ? ' ' . $pr[1] : '');
            }
            echo '<div class="ts-more" title="' . ts_h(implode(' | ', $hidden)) . '">+' . $rest . ' more</div>';
        }
        echo '</td>';
    }
    ?>
</tr>
<tr class="detail" id="tsDet<?php echo $i; ?>" style="display:none;">
    <td colspan="9">
        <div class="ts-dl">
            <div class="t">Transcript</div><div><?php
                /* 与表格里那一格同一个判据、同一个链接目标（能查到才给链接）。 */
                if (isset($gdLinks[$r['protein']])) {
                    echo '<a class="ts-gd" href="gene_detail.php?gene=' . urlencode($gdLinks[$r['protein']])
                       . '&amp;species=' . urlencode($species) . '">' . ts_h($r['protein']) . '</a>';
                } else {
                    echo ts_h($r['protein']);
                }
            ?> &nbsp;<span class="d">(<?php echo cnido_num($r['plen']); ?> aa)</span></div>
            <div class="t">Gene</div><div><?php
                if ($r['gene'] === '') {
                    echo '&ndash;';
                } else {
                    echo ts_h($r['gene']);
                    if (isset($gdLinks[$r['gene']]) && (!isset($r['protein']) || $r['gene'] !== $r['protein'])) {
                        echo ' &nbsp;<a class="ts-gd" href="gene_detail.php?gene=' . urlencode($gdLinks[$r['gene']])
                           . '&amp;species=' . urlencode($species) . '">gene detail &rarr;</a>';
                    }
                }
            ?></div>
            <?php
            /* same pairing as the table cell, but nothing is folded away here */
            foreach ($ALLOWED_F as $f):
                $pairs = ts_pairs_for($f, $r, $goDict, $koDict);
                if (!$pairs) { continue; }
            ?>
            <div class="t"><?php echo ts_h($TS_LABEL[$f]); ?></div>
            <div>
                <?php foreach ($pairs as $pr): ?>
                <div class="ts-detail-item">
                    <a class="ts-chip <?php echo $f; ?>" href="<?php echo ts_h($TS_LINK[$f] . urlencode($pr[0])); ?>" target="_blank" rel="noopener noreferrer"><?php echo ts_h($pr[0]); ?></a>
                    <?php if ($pr[1] !== ''): ?><span class="d"><?php echo ts_h($pr[1]); ?></span><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php
/* pagination: first / prev / window / next / last */
$win = 2;
$from = max(1, $page - $win);
$to   = min($pages, $page + $win);
?>
<div class="ts-pager">
    <?php if ($page > 1): ?>
        <a class="ts-pg" href="<?php echo ts_h(ts_url(array('page' => 1))); ?>">&laquo; First</a>
        <a class="ts-pg" href="<?php echo ts_h(ts_url(array('page' => $page - 1))); ?>">&lsaquo; Prev</a>
    <?php else: ?>
        <span class="dis">&laquo; First</span><span class="dis">&lsaquo; Prev</span>
    <?php endif; ?>
    <?php if ($from > 1): ?><span class="dis">...</span><?php endif; ?>
    <?php for ($p = $from; $p <= $to; $p++): ?>
        <?php if ($p === $page): ?><span class="cur"><?php echo $p; ?></span>
        <?php else: ?><a class="ts-pg" href="<?php echo ts_h(ts_url(array('page' => $p))); ?>"><?php echo $p; ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($to < $pages): ?><span class="dis">...</span><?php endif; ?>
    <?php if ($page < $pages): ?>
        <a class="ts-pg" href="<?php echo ts_h(ts_url(array('page' => $page + 1))); ?>">Next &rsaquo;</a>
        <a class="ts-pg" href="<?php echo ts_h(ts_url(array('page' => $pages))); ?>">Last &raquo;</a>
    <?php else: ?>
        <span class="dis">Next &rsaquo;</span><span class="dis">Last &raquo;</span>
    <?php endif; ?>
    <span style="border:0;color:#64748b;background:none;">page <?php echo $page; ?> of <?php echo cnido_num($pages); ?></span>
</div>
<?php endif; ?>

<?php if ($meta): ?>
<p class="ts-meta">
    <b>Assembly.</b> <?php echo ts_h($meta['source']); ?><?php if ($meta['run'] !== ''): ?>,
    from RNA-seq run <a href="https://www.ncbi.nlm.nih.gov/sra/?term=<?php echo urlencode($meta['run']); ?>" target="_blank" rel="noopener noreferrer"><?php echo ts_h($meta['run']); ?></a><?php endif; ?>.
    Longest transcript <?php echo cnido_num($meta['longest']); ?> bp.
    <?php if ($isGenome): ?>
    <?php /* Genome-derived 物种不能印下面那句 "come from N distinct genes"：它们的 reps 列
         就等于蛋白行数（12 个物种逐行核对过），而那 12 个里的 7 个确实一个基因有多条
         isoform 行（AACUM 25,022 行只对应 21,255 个基因模型），说成 N 个基因是假话；
         另外 5 个的基因号写法不同（NSEPT 是 ANN00001-RA、PMIZI 的 gene 列是 NA），
         现推也不可靠。所以这里只说「一行 = 一条预测转录本」这件确定的事。 */ ?>
    Each row below is one <b>predicted transcript of a genome gene model</b>; a model with several
    predicted isoforms contributes several rows, so the number of genes is lower than the number of
    rows. The 12 genome-derived species are marked as such in the <b>Assembly</b> column of the
    <a href="trans_assembly.php">overview page</a>.
    <?php elseif ($repsOk): ?> The
    <?php echo cnido_num($denom); ?> predicted proteins listed below come from
    <?php echo cnido_num($reps); ?> distinct genes &mdash; isoform rows of one gene carry the same
    annotation, which is why the <a href="trans_assembly.php">overview page</a> counts hits per gene
    and this page lists and filters them per protein.<?php endif; ?>
    <br />
    <b>Annotation.</b> DIAMOND vs UniProt Swiss-Prot &middot; InterProScan (Pfam, PANTHER, InterPro, GO) &middot; KOfamScan (KEGG).
    <?php if ($isGenome): ?>
    An identifier in the <b>Transcript / protein</b> column is a link when that transcript is part of
    the species&rsquo; genome annotation &mdash; checked one by one against the annotation tables, not
    assumed. <?php
        if ($gdRate !== null) {
            $n = $gdRate[0]; $k = $gdRate[1];
            if ($k === 0) {
                echo 'None of the ' . cnido_num($n) . ' sampled identifiers of this species resolve &mdash; '
                   . 'the identifiers deposited here are not the ones the genome annotation uses, so no '
                   . 'links are shown for this species. ';
            } elseif ($k === $n) {
                echo 'All ' . cnido_num($n) . ' sampled identifiers of this species resolve. ';
            } else {
                echo 'Of ' . cnido_num($n) . ' sampled identifiers of this species, <b>' . cnido_num($k)
                   . '</b> resolve; the other ' . cnido_num($n - $k) . ' are rows whose identifier is '
                   . 'absent from the annotation tables, and only the resolvable ones carry a link. ';
            }
        }
    ?><?php if ($firstGd !== ''): ?>A
    <a href="gene_detail.php?gene=<?php echo urlencode($firstGd); ?>&amp;species=<?php echo urlencode($species); ?>">gene
    detail page</a> adds the gene model, its neighbours in JBrowse, the sequences and the
    <b>NCBI NR</b> hit, none of which are in the table above. <?php endif; ?>
    Every identifier in the table is a <b>transcript</b> (mRNA) identifier: only the transcript behind
    each row was deposited, so the gene-level model identifier is not part of this table.
    <?php else: ?>
    NCBI NR was not searched in this release, so a protein with no hit above may still be a known gene.
    Identifiers are <b>not</b> linked to gene detail pages for this assembly: its identifiers come from
    this de novo transcriptome assembly and are not part of the species&rsquo; genome annotation, so a
    gene page would have nothing to show. Gene pages are available for the 12 genome-derived species.
    The gene identifier is part of the transcript identifier shown: drop the trailing
    <code class="ts-k">_i&lt;isoform&gt;.p&lt;protein&gt;</code> and what remains is the gene this row
    belongs to &mdash; the same string the search box matches as a gene id.
    <?php endif; ?>
</p>
<?php endif; ?>

</div>
</div>
</div>

<script type="text/javascript">
function tsToggle(i) {
    var d = document.getElementById('tsDet' + i);
    var c = document.getElementById('tsCar' + i);
    if (!d) { return; }
    var open = d.style.display !== 'none';
    d.style.display = open ? 'none' : '';
    if (c) { c.innerHTML = open ? '&#9656;' : '&#9662;'; }
}
</script>

<?php
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
