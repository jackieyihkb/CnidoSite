<?php
/* =====================================================================
 * 跨物种「功能注释检索」公共库
 *
 * 审稿意见 Referee 3 点 10(e)：
 *   "InterPro / Pfam / KEGG 检索只能输入基因 ID，不能按结构域编号
 *    (IPR016024 / PF12848 / K13752) 或结构域名称检索，而且一次只能查一个物种。"
 *
 * 站内每个物种的注释存放在独立表 <ABBR>_ipr / <ABBR>_pfam / <ABBR>_KEGG 中
 * （145 个已注释物种）。本库把这 145 张表用 UNION ALL 拼成一张逻辑表，
 * 使一次查询即可覆盖全部物种。
 *
 * ---------------------------------------------------------------------
 * 2026-09-22 性能改造（页面从最长 20 秒降到 0.1~0.6 秒）
 *
 * 老写法每张表的条件是 `(term = 'q' OR desc LIKE '%q%')`。OR 里有 LIKE
 * 就意味着索引完全用不上：不管查的是编号还是词，148 张表全部全表扫一遍，
 * 一页 3359 万行。实测 ipr 查一个不存在的词要 20.1 秒、pfam 查 PF12848
 * 要 5.1 秒（因为要先扫到 501 行才停）。
 *
 * 现在分两层：
 *
 *   1. 编号精确匹配走索引。148 张注释表都加了 ix_term (term(48))，
 *      `WHERE term = 'IPR016024'` 是索引查找，不是全表扫。
 *      所以第一趟只带等值条件（ds_conditions($db,$q,'id')），命中就返回。
 *
 *   2. 包含匹配（LIKE '%q%'）先问词表 ds_vocab：站内所有注释表的每一列
 *      有哪些"去重后的值"，以及每个值出现在哪些物种里。词表里一个都没
 *      出现，就确定没有命中，直接返回空 —— 这一步把 20 秒变成了 0.05 秒。
 *      词表说某个词只在 6 个物种里出现，就只扫那 6 张表，而不是 148 张。
 *      词表只是"缩小候选范围"，命中与否仍由真正的 LIKE 决定，所以语义
 *      和以前完全一致（词表宁可多给几张表，不会少给）。
 *
 * 词表由 includes/ds_vocab_refresh.php 生成（CLI，改了注释表之后要重跑）。
 * 词表不存在、或记录的表数与当前实际的表数不一致（说明注释表变过了），
 * ds_vocab_route() 返回 null，整条路径退回老的全表扫描 —— 慢，但结果一样。
 * ===================================================================== */

/** 各库的检索配置。term = 编号列；eq = 另外的精确匹配列；like = 需要包含匹配的列。
 *  like[0] 就是结果表 "Match" 那一列要显示的内容（见 ds_search_columns）。
 *
 *  term/eq/like 三处改列名时，记得同步重跑 includes/ds_vocab_refresh.php。 */
function ds_dbmaps()
{
    return array(
        'ipr' => array(
            'label'  => 'InterPro',
            'term'   => 'InterPro_term',
            'source' => 'Source',
            'url'    => 'https://www.ebi.ac.uk/interpro/entry/InterPro/',
            'hint'   => 'IPR016024 or Armadillo',
            'eq'     => array(),
            'like'   => array('Description'),
        ),
        'pfam' => array(
            'label'  => 'Pfam',
            'term'   => 'Pfam_accession',
            'source' => 'Source',
            'url'    => 'https://www.ebi.ac.uk/interpro/entry/pfam/',
            'hint'   => 'PF12848 or Fibrinogen_C',
            'eq'     => array(),
            'like'   => array('Description', 'Pfam_name'),
        ),
        'KEGG' => array(
            'label'  => 'KEGG pathway',
            'term'   => 'KO',
            'source' => 'Source',
            'url'    => 'https://www.genome.jp/entry/',
            'hint'   => 'K13752 or ko02000',
            // KO 列同时允许匹配 Pathway_ID（ko02000 这种），词表里两列都收了
            'eq'     => array('Pathway_ID'),
            'like'   => array('Enzymes'),
        ),
        'go' => array(
            'label'  => 'Gene Ontology',
            'term'   => 'GO_term',
            'source' => 'Source',
            'url'    => 'http://amigo.geneontology.org/amigo/term/',
            'hint'   => 'GO:0005615 or extracellular space',
            'eq'     => array(),
            'like'   => array('Description'),
        ),
        'panther' => array(
            'label'  => 'PANTHER',
            'term'   => 'id',
            'source' => 'method',
            'url'    => 'https://www.pantherdb.org/panther/family.do?clsAccession=',
            'hint'   => 'PTHR19143',
            'eq'     => array(),
            'like'   => array('anno'),
        ),
    );
}

/** 单个库的检索列配置，附带把 like[0] 取名成 desc（结果表列头要用）。 */
function ds_search_columns($db)
{
    $M = ds_dbmaps();
    if (!isset($M[$db])) { return null; }
    $m = $M[$db];
    $m['desc'] = isset($m['like'][0]) ? $m['like'][0] : '';
    return $m;
}

/** 返回拥有 <ABBR>_<suffix> 表的全部 abbr（按字母序）。 */
function ds_species_list($conn, $suffix)
{
    static $cache = array();
    if (isset($cache[$suffix])) { return $cache[$suffix]; }

    /* 后缀相同的**非物种表**会混进这份名单：`trans_assembly_go` 就是这样一张
       （全库共用的 GO 名字字典，key 列叫 go_id，不叫 GO_term）。它一旦进了名单，
       下面 ds_search() 的 UNION ALL 里就多出一个引用不存在列的分支，那一整块
       （40 个物种）会整块失败并被静默跳过 —— 排在字母表末尾的 28 个物种
       （TDOHR…XSP）在 GO 检索里因此一行都出不来，而库里它们各有几百行。
       用 abbr 表（物种短码的权威名单）过一道，只留真正的物种。
       查询失败时名单为空，退回老行为，宁可多扫也不能把物种漏掉。 */
    static $species = null;
    if ($species === null) {
        $species = array();
        $q0 = mysqli_query($conn, "SELECT DISTINCT abbr1 FROM abbr");
        while ($q0 && ($r0 = mysqli_fetch_row($q0))) { $species[$r0[0]] = true; }
    }

    $out = array();
    $like = '%\\_' . mysqli_real_escape_string($conn, $suffix);
    $q = mysqli_query($conn, "SELECT table_name FROM information_schema.tables
                              WHERE table_schema = DATABASE() AND table_name LIKE '$like'");
    while ($q && ($r = mysqli_fetch_row($q))) {
        $ab = substr($r[0], 0, strlen($r[0]) - strlen($suffix) - 1);
        if ($species && !isset($species[$ab])) { continue; }
        $out[] = $ab;
    }
    sort($out);
    $cache[$suffix] = $out;
    return $out;
}

/** 该 abbr 是否真的有这张表（同时校验 abbr 只含合法字符，防止注入）。 */
function ds_valid_abbr($abbr)
{
    return (bool)preg_match('/^[A-Za-z0-9_.\-]+$/', $abbr);
}

/**
 * 把用户输入变成检索条件。返回 array('eq'=>array(...), 'like'=>array(...))，
 * 每一项是 array('col'=>列名, 'op'=>'='|'like', 'val'=>已转义的字符串)。
 *
 * $mode : 'id'   只要精确匹配（走 ix_term 索引）
 *         'text' 只要包含匹配（LIKE，要用词表缩范围）
 *
 * 注意 'val' 是已经 mysqli_real_escape_string 过的，拼 SQL 时不要再转义一次
 * —— 词表路由要用完全相同的字面量，转义两次会对不上。
 */
function ds_conditions($conn, $db, $q, $mode)
{
    $c = ds_search_columns($db);
    if ($c === null) { return array('eq' => array(), 'like' => array()); }
    $qq = mysqli_real_escape_string($conn, $q);

    $eq = array(); $like = array();
    if ($mode === 'id') {
        $eq[] = array('col' => $c['term'], 'op' => '=', 'val' => $qq);
        foreach ($c['eq'] as $col) { $eq[] = array('col' => $col, 'op' => '=', 'val' => $qq); }
    } else {
        // 老写法不转义 % 和 _，用户输入里的 % 一直当通配符用，这里保持原样
        foreach ($c['like'] as $col) { $like[] = array('col' => $col, 'op' => 'like', 'val' => $qq); }
    }
    return array('eq' => $eq, 'like' => $like);
}

/** 某一列某一值的词表路由。返回 abbr 数组，或 null（该列没进词表 → 无法缩范围）。 */
function ds_vocab_lookup($conn, $db, $c)
{
    $dbEsc  = mysqli_real_escape_string($conn, $db);
    $colEsc = mysqli_real_escape_string($conn, $c['col']);
    $val    = $c['val'];                      // 已在 ds_conditions 里转义过
    $w = ($c['op'] === '=') ? "value = '$val'" : "value LIKE '%$val%'";

    $q = mysqli_query($conn, "SELECT abbrs FROM ds_vocab
                              WHERE db = '$dbEsc' AND col = '$colEsc' AND $w");
    if (!$q) { return null; }                 // 词表不存在 → 退回全表扫描
    $out = array();
    while ($r = mysqli_fetch_row($q)) {
        foreach (explode(',', $r[0]) as $ab) { if ($ab !== '') { $out[$ab] = 1; } }
    }
    return array_keys($out);
}

/**
 * 用词表把候选物种缩到"真的可能命中"的那些。返回 null = 不要缩（退回老路径）。
 * 多个条件之间是 OR，所以候选是并集。
 *
 * 词表陈旧的判断：词表里记着生成时有多少张 <ABBR>_<db> 表，和现在实际的
 * 张数不一样就不敢用（新加的物种表不在词表里，会用错）。宁可慢，不能漏。
 */
function ds_vocab_route($conn, $db, $conds)
{
    $all = array_merge($conds['eq'], $conds['like']);
    if (empty($all)) { return null; }

    $meta = ds_vocab_meta($conn, $db);
    if ($meta === null) { return null; }                       // 还没生成词表
    if ((int)$meta['ntables'] !== count(ds_species_list($conn, $db))) { return null; }

    /* 词表只收 like 列（编号列走 ix_term 索引，不需要词表）。所以编号检索
       （'id' 那一趟）的条件列根本不在词表里 —— 这里必须先按 meta 里记的列名
       挡掉，否则"查了但这个词表里没有这一列"会被当成"这个词一个物种都没有"，
       128 个编号检索全部返回空。只有一列真在词表里、且确实没命中，才敢说没结果。 */
    $known = isset($meta['columns']) && is_array($meta['columns']) ? $meta['columns'] : array();
    foreach ($all as $c) {
        if (!in_array($c['col'], $known, true)) { return null; }
    }

    $set = array();
    foreach ($all as $c) {
        $sub = ds_vocab_lookup($conn, $db, $c);
        if ($sub === null) { return null; }                    // 词表读不了，不能缩
        foreach ($sub as $ab) { $set[$ab] = 1; }
    }
    return array_keys($set);
}

/** 词表里记录的生成信息（一行，db + col='__meta__'）。没有词表返回 null。 */
function ds_vocab_meta($conn, $db)
{
    static $cache = array();
    if (array_key_exists($db, $cache)) { return $cache[$db]; }

    $dbEsc = mysqli_real_escape_string($conn, $db);
    $q = @mysqli_query($conn, "SELECT abbrs FROM ds_vocab WHERE db = '$dbEsc' AND col = '__meta__' LIMIT 1");
    $cache[$db] = null;
    if ($q && ($r = mysqli_fetch_row($q))) {
        $m = json_decode($r[0], true);
        if (is_array($m) && isset($m['ntables'])) { $cache[$db] = $m; }
    }
    return $cache[$db];
}

/**
 * 跨物种检索。$conds 来自 ds_conditions()。
 * 返回 array(rows, speciesCount, truncated)
 *   rows[0] 是该行的物种 abbr，其余是注释表的原始列（顺序同 SELECT t.*）
 *   speciesCount 是这次检索覆盖的物种数（缩范围之前的值，页面上要显示）
 */
function ds_search($conn, $db, $conds, $limit = 500)
{
    $species = ds_species_list($conn, $db);
    if (!empty($_GET['sp']) && ds_valid_abbr($_GET['sp'])) {
        $species = array_intersect($species, array($_GET['sp']));
    }
    $nsp = count($species);

    // 词表缩范围：候选表少了，但要扫的每张表结果不变
    $route = ds_vocab_route($conn, $db, $conds);
    if ($route !== null) {
        $species = array_values(array_intersect($species, $route));
        if (empty($species)) { return array(array(), $nsp, false); }   // 词表确定无此词
    }

    $w = ds_where($conds);
    if ($w === '') { return array(array(), $nsp, false); }

    $parts = array();
    foreach ($species as $ab) {
        $abEsc = mysqli_real_escape_string($conn, $ab);
        $parts[] = "SELECT '$abEsc' AS abbr, t.* FROM `{$ab}_{$db}` t WHERE $w";
    }
    if (empty($parts)) { return array(array(), $nsp, false); }

    $rows = array(); $trunc = false;
    // 分批 UNION，避免超过 MySQL 的 JOIN/UNION 限制
    foreach (array_chunk($parts, 40) as $chunk) {
        $sql = implode("\nUNION ALL\n", $chunk) . "\nLIMIT " . ($limit + 1);
        $q = mysqli_query($conn, $sql);
        if (!$q) {
            /* 整块失败**不能**直接跳过：一块 40 个物种，跳过就是静默丢 40 个物种的
               结果（历史上 trans_assembly_go 让最后一块整个消失过）。逐表重试，
               哪张表有问题哪张落空，同块其它物种照常出结果。 */
            foreach ($chunk as $one) {
                $q1 = mysqli_query($conn, $one . "\nLIMIT " . ($limit + 1));
                if (!$q1) { continue; }
                while ($r = mysqli_fetch_row($q1)) { $rows[] = $r; }
                if (count($rows) > $limit) { $trunc = true; break 2; }
            }
            continue;
        }
        while ($r = mysqli_fetch_row($q)) { $rows[] = $r; }
        if (count($rows) > $limit) { $trunc = true; break; }
    }
    if (count($rows) > $limit) { $rows = array_slice($rows, 0, $limit); $trunc = true; }
    return array($rows, $nsp, $trunc);
}

/** 把条件数组拼成不带前导 WHERE 的 SQL。 */
function ds_where($conds)
{
    $or = array();
    foreach (array_merge($conds['eq'], $conds['like']) as $c) {
        $col = '`' . str_replace('`', '', $c['col']) . '`';
        $or[] = ($c['op'] === '=') ? "$col = '{$c['val']}'" : "$col LIKE '%{$c['val']}%'";
    }
    return empty($or) ? '' : '(' . implode(' OR ', $or) . ')';
}

/**
 * 页面用的两趟检索：先按编号精确查（走索引，快），没命中再退到包含匹配
 * （先问词表，词表说没有就直接返回空）。
 *
 * 为什么分两趟：老写法把 `term = 'q' OR desc LIKE '%q%'` 写在一个 OR 里，
 * 只要 OR 里有 LIKE，term 上的索引就用不上，148 张表照扫。分开之后编号
 * 检索是纯索引查找 —— 这正是审稿意见要的那种查法。
 *
 * 语义上唯一的差别：输入既是某条注释的编号、又出现在别的注释的描述里时，
 * 只返回编号命中的那些行（以前是两者并集）。这种交集实际不存在。
 */
function ds_search_two_pass($conn, $db, $q, $limit = 500)
{
    if (!ds_looks_like_accession($q)) {
        // 普通词（homeobox / extracellular space）：精确匹配那一趟必然 148 张表
        // 全白扫，直接跳过，只做包含匹配
        return ds_search($conn, $db, ds_conditions($conn, $db, $q, 'text'), $limit);
    }
    $r = ds_search($conn, $db, ds_conditions($conn, $db, $q, 'id'), $limit);
    if (!empty($r[0])) { return $r; }
    // 形状像编号但没有这个编号（比如 p53、Sox2 —— 既是编号形状又是描述词），
    // 兜底走一次包含匹配。有词表时这一趟通常只碰几张表，很快。
    return ds_search($conn, $db, ds_conditions($conn, $db, $q, 'text'), $limit);
}

/**
 * 输入像不像一个编号：IPR016024 / PF12848 / K13752 / GO:0005615 / PTHR19143 / ko02000。
 * 用来决定要不要跑那一趟"精确匹配"。判断错了不会出错 —— 只是多扫或少扫一趟，
 * 兜底逻辑保证两种形状最终都会试到包含匹配。
 */
function ds_looks_like_accession($q)
{
    return (bool)preg_match('/^[A-Za-z]{1,7}[:.]?[0-9][A-Za-z0-9._:-]*$/', trim($q));
}

/** 生成「当前检索条件 + 指定物种」的链接，用于结果表里的物种过滤。 */
function ds_species_link($abbr)
{
    $q = $_GET;
    $q['sp'] = $abbr;
    unset($q['page']);
    return '?' . http_build_query($q);
}

/** 把一段用户输入拆成基因 ID 列表（兼容换行/空格/逗号分隔）。 */
function ds_gene_list($raw)
{
    $out = array();
    foreach (preg_split('/[\s,;]+/', (string)$raw) as $g) {
        $g = trim($g);
        if ($g !== '') { $out[] = $g; }
    }
    return array_values(array_unique($out));
}
