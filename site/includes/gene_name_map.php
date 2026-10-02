<?php
/**
 * gene_name_map.php —— 站点自己的「基因名」从哪来。
 *
 * 数据库里**没有任何一列存着基因名**：145 张 <ABBR>_locus 全都只有
 * mRNA / gene / assembly / start / end / strand 六列（`gene` 是 LOC… 这类基因号，
 * HVULG 那种是 BRAKER 号）。真正带名字的是注释表：
 *
 *   <ABBR>_uniprot.description —— UniProt 记录的 GN= 字段，是真基因符号
 *                                 （"Protein Wnt-3a OS=… GN=Wnt3a PE=1 SV=1"）
 *   <ABBR>_nr.description      —— NCBI-NR 的蛋白描述
 *                                 （"protein Wnt-3a [Nematostella vectensis]"）
 *
 * ⚠ 这两张表的 `gene` 列装的是 **mRNA/蛋白号**（XP_…、KXJ…、RMX…），不是 locus 基因号。
 *   所以「基因号 → 名字」必须走 gene → mRNA → description 这条路（经 <ABBR>_locus
 *   的 mRNA 列）。照基因号直接去注释表里查是查不到的 —— gene_detail.php 现在就是这么
 *   查的，于是它对 NVECT 的 LOC5515782 报「no NCBI-NR hit recorded」，而库里明明有。
 *
 * **同一个 mRNA 列也就是「代表蛋白号」的来源**，而蛋白号才是有用的那个号：
 * gene_detail.php 的注释面板（NR/UniProt/Pfam/InterPro/GO/KEGG/表达/表观/网络）
 * 全都按蛋白号查，拿 locus 基因号去问，整页都是空的（实测 NVECT 的 LOC5500864：
 * NR、UniProt、Pfam、InterPro、GO、KEGG、表达、网络、表观一律没有；换成它的
 * XP_032225133.2 全部都有）。所以这里顺带给出每个基因的代表蛋白号，供页面显示、
 * 供搜索框按蛋白号查。
 *
 * 一个基因常对上好几个物种的同源蛋白，GN= 也就有好几个候选（WNT3A / Wnt3a / wnt3a），
 * 所以按出现次数投票；票数相同时取大写字母少的那个 —— 更接近 NCBI 的小写写法，
 * 也更接近用户在搜索框里会敲的样子（wnt3 能靠大小写不敏感的 LIKE 命中 Wnt3a）。
 * 名字是**注释来源**给的名字（最相近的已注释同源基因），不是这个物种自己的命名；
 * 库里没有名字的基因就返回空，绝不替它编一个。
 *
 * `genefamily.anno` 看着像第三条路（104 个物种、NR 式描述），但它的 Nr_proteins
 * 对不上 <ABBR>_locus.mRNA（实测 0 行，MSENI/HSYMB 都如此），所以不用。
 */

if (!function_exists('cnido_gene_name_has_table')) {

/** 表在不在（同一个物种会被问很多次，结果记下来）。 */
function cnido_gene_name_has_table($conn, $table)
{
    static $memo = array();
    if (isset($memo[$table])) {
        return $memo[$table];
    }
    $e = $conn->real_escape_string($table);
    $q = @$conn->query("SELECT 1 FROM information_schema.tables
                         WHERE table_schema = DATABASE() AND table_name = '$e' LIMIT 1");
    return $memo[$table] = (bool) ($q && $q->num_rows > 0);
}

/**
 * 这个「名字」是不是等于没名字。
 *
 * 注释来源经常只给一个占位描述：NR 里 11,906 行的描述原文就是
 * `unnamed protein product [Pocillopora meandrina]`（最近同源蛋白自己也没名字），
 * 另有 `Hypothetical predicted protein`、`putative uncharacterized protein` 之类。
 * 这些印在括号里跟没印一样，还占着位置、让人以为有名字。
 *
 * 只按**开头**的占位词判定，不按包含：真名字里出现 "protein" 的太多了
 * （"craniofacial development protein 2-like"、"histone H2A"），一刀切会把它们误杀。
 */
function cnido_gene_name_is_junk($name)
{
    $d = trim((string) $name);
    if ($d === '' || $d === '-' || $d === '?' || $d === '.') {
        return true;
    }
    static $bad = array('uncharacterized', 'hypothetical', 'predicted protein',
                        'unnamed protein', 'putative uncharacterized', 'unknown protein',
                        'protein of unknown function', 'expressed protein',
                        'novel protein', 'no hit');
    foreach ($bad as $b) {
        if (strncasecmp($d, $b, strlen($b)) === 0) {
            return true;
        }
    }
    return false;
}

/** UniProt 描述里的 GN= 字段（"… GN=Wnt3a PE=1 SV=1" → "Wnt3a"）。 */
function cnido_gene_name_gn($desc)
{
    $p = stripos((string) $desc, 'GN=');
    if ($p === false) {
        return '';
    }
    $tok = strtok(substr((string) $desc, $p + 3), " \t");
    $tok = trim((string) $tok);
    /* 值里不该再出现等号；出现了说明取到的是下一个字段（"PE=1" 这类） */
    if ($tok === '' || strpos($tok, '=') !== false || cnido_gene_name_is_junk($tok)) {
        return '';
    }
    return $tok;
}

/**
 * NCBI-NR 描述 → 一个短名字；拿不到像名字的东西就返回空（不猜）。
 * "PREDICTED: Roundabout-like 2 isoform X1 [Exaiptasia diaphana]" → "Roundabout-like 2"
 */
function cnido_gene_name_nr($desc)
{
    $d = trim((string) $desc);
    $d = preg_replace('/^(PREDICTED|predicted)\s*:\s*/', '', $d);
    $d = preg_replace('/\s*\[[^\]]*\]\s*$/', '', $d);          // 末尾的 [Organism]
    $d = preg_replace('/\s+isoform\s+X?\d+\s*$/i', '', $d);
    $d = preg_replace('/,?\s*partial\s*$/i', '', $d);
    $d = trim((string) $d, " \t.");
    /* 占位描述（unnamed protein product、hypothetical… 见 cnido_gene_name_is_junk）
       等于没有名字，别拿来充数 —— 判据只有这一处，别在两处各写一份。 */
    return cnido_gene_name_is_junk($d) ? '' : $d;
}

/**
 * 一个基因的多个候选名字里挑一个：票数多的优先；票数相同取大写字母少的
 * （更接近 NCBI 的小写写法），再相同取短的，最后按字典序定死，保证可复现。
 */
function cnido_gene_name_pick($cands)
{
    $best = '';
    $bestKey = null;
    foreach ($cands as $name => $n) {
        $key = array((int) $n, -preg_match_all('/[A-Z]/', $name), -strlen($name));
        if ($bestKey === null || $key > $bestKey) {
            $bestKey = $key;
            $best    = $name;
        }
    }
    return $best;
}

/**
 * 某物种的「站点基因号 → 基因名 + 代表蛋白号」表。
 *
 * ⚠ 表里**包含所有能查到 mRNA 的基因**，不只是有名字的那些：没有名字的
 *   `name` 是空串（绝不编），没有 mRNA 的 `mrna` 是空串。
 *   要判断「有没有名字」请判 `name !== ''`，不要用 isset($map[$g])。
 *
 * @param mysqli     $conn
 * @param string     $abbr1 物种短码
 * @param array|null $only  只处理这些基因号（array(gene => true)）；null = 全部
 * @return array  gene => array('name' => string, 'src' => 'uniprot_gn'|'nr'|'',
 *                             'mrna' => string)
 */
function cnido_gene_name_map($conn, $abbr1, $only = null)
{
    static $SENTINEL = array('Gene_ID' => 1, 'gene' => 1, 'Gene' => 1, 'NA' => 1,
                             'N/A' => 1, '-' => 1, 'NULL' => 1, 'null' => 1);
    $out = array();
    if (!$conn || !preg_match('/^[A-Za-z0-9_]{1,32}$/', (string) $abbr1)) {
        return $out;
    }
    if (!cnido_gene_name_has_table($conn, $abbr1 . '_locus')) {
        return $out;
    }

    /* mRNA → 基因号，同时定下每个基因的代表蛋白号。
       注释表按 mRNA 号索引，所以 m2g 是唯一的桥；反着建索引（mRNA 做键）也是必须的：
       拿 6 万行注释去逐个 in_array 扫 2 万个基因会变成十几亿次比较。

       代表蛋白号取**基因组跨度最长的那条转录本**：一个基因常有多条 isoform
       （NVECT 文献里的 13,967 个基因里有 4,743 个如此），长度最长的那条就是 NCBI
       的 isoform X1，也正是名字（GN=）最常落在的那条（实测 82 个多 isoform 基因里
       81 个如此）。跨度相同就取号小的，保证每次跑出来一样。 */
    $m2g  = array();    // mRNA => array(基因号 => true)
    $best = array();    // 基因号 => array(跨度, mRNA)
    $q = @$conn->query("SELECT mRNA, gene, start, end FROM `{$abbr1}_locus`
                         WHERE mRNA IS NOT NULL AND mRNA <> ''");
    if (!$q) {
        return $out;
    }
    while ($r = $q->fetch_row()) {
        $g = (string) $r[1];
        $m = (string) $r[0];
        if ($g === '' || $m === '' || isset($SENTINEL[$g])) {
            continue;
        }
        if ($only !== null && !isset($only[$g])) {
            continue;
        }
        $m2g[$m][$g] = true;
        $span = (int) $r[3] - (int) $r[2];
        if (!isset($best[$g]) || $span > $best[$g][0]
            || ($span === $best[$g][0] && strcmp($m, $best[$g][1]) < 0)) {
            $best[$g] = array($span, $m);
        }
    }
    if (!$m2g) {
        return $out;
    }
    foreach ($best as $g => $b) {
        $out[$g] = array('name' => getenv('CNIDO_MSR_DB_NAME') ?: 'jackie_db', 'src' => '', 'mrna' => $b[1]);
    }

    $gn = array();      // gene => array(名字 => 票数)，来自 _uniprot 的 GN=
    if (cnido_gene_name_has_table($conn, $abbr1 . '_uniprot')) {
        $q = @$conn->query("SELECT gene, description FROM `{$abbr1}_uniprot`
                             WHERE description LIKE '%GN=%'");
        while ($q && ($r = $q->fetch_row())) {
            $m = (string) $r[0];
            if (!isset($m2g[$m])) {
                continue;
            }
            $n = cnido_gene_name_gn($r[1]);
            if ($n === '') {
                continue;
            }
            foreach ($m2g[$m] as $g => $_) {
                $gn[$g][$n] = (isset($gn[$g][$n]) ? $gn[$g][$n] : 0) + 1;
            }
        }
    }

    $nr = array();      // gene => array(名字 => 票数)，来自 _nr 的描述
    if (cnido_gene_name_has_table($conn, $abbr1 . '_nr')) {
        $q = @$conn->query("SELECT gene, description FROM `{$abbr1}_nr`");
        while ($q && ($r = $q->fetch_row())) {
            $m = (string) $r[0];
            if (!isset($m2g[$m])) {
                continue;
            }
            $n = cnido_gene_name_nr($r[1]);
            if ($n === '') {
                continue;
            }
            foreach ($m2g[$m] as $g => $_) {
                $nr[$g][$n] = (isset($nr[$g][$n]) ? $nr[$g][$n] : 0) + 1;
            }
        }
    }

    foreach ($gn as $g => $cands) {
        $n = cnido_gene_name_pick($cands);
        if ($n !== '' && isset($out[$g])) {
            $out[$g]['name'] = $n;
            $out[$g]['src']  = 'uniprot_gn';
        }
    }
    foreach ($nr as $g => $cands) {
        if (!isset($out[$g]) || $out[$g]['name'] !== '') {
            continue;       // 有真基因符号就不拿描述凑
        }
        $n = cnido_gene_name_pick($cands);
        if ($n !== '') {
            $out[$g]['name'] = $n;
            $out[$g]['src']  = 'nr';
        }
    }
    return $out;
}

}   /* function_exists */
