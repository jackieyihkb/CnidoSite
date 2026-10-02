<?php
/* ===========================================================================
 * includes/network_graph.php
 *
 * 共表达网络（cytoscape）两个渲染器共用的部分：
 *
 *   Network Analysis        cytoscape/network.php  → network.list.php
 *                           = head.inc + tmp/node$job.inc + tmp/edge$job.inc
 *                             + tail.list.inc
 *   Dynamic Expression View cytoscape/network_expression.php → network.expression.php
 *
 * 两个页面各画一张 cytoscape 图，节点/边都由 PHP 拼成 JS 对象字面量写进
 * tmp/*.inc 再 include 进页面。以前这套拼接和 hover 提示在两边各抄了一份，
 * 于是出现了三类问题，本文件把它们收成一处：
 *
 * 1. **字符串没有转义，会让整张图白掉。** 节点行原样写成
 *
 *        { data: { ..., annotation: '<ABBR>_panther.anno' } }
 *
 *    _panther.anno 是 PANTHER/InterProScan 的蛋白描述，里面**本来就有单引号**：
 *    全站 361 万个 anno 值里有 9202 个含单引号，而且都是极常见的名字 ——
 *    `5'-NUCLEOTIDASE`、`3'-5' EXONLEASE`、`RNA 2',3'-CYCLIC
 *    PHOSPHODIESTERASE`。只要网络里出现一个这样的基因，单引号就把 JS 字符串
 *    截断，整个 cytoscape({...}) 抛 SyntaxError，**图画不出来**；而下面的节点
 *    表格是普通 HTML，照常渲染 —— 所以现象看着像「图是空的」而不是「页面坏了」。
 *    实测 LPERT 的 39 个含单引号基因**全部**会作为节点出现（MCAPI 33/33、
 *    SSIDE 44、HVULG 53）。现在所有值都过 cnido_net_js()（json_encode +
 *    JSON_HEX_TAG），单引号、双引号、`</script>` 都不会再破坏页面。
 *
 * 2. **hover 提示是空的。** 提示内容原来是
 *
 *        content: function(){ return this.id() + ' annotation: ' + g }
 *
 *    而 g 取自节点的 annotation 字段，Dynamic Expression View 那边这个字段
 *    写死成空串，于是悬停只显示「基因号 annotation: 」，冒号后面什么都没有。
 *    现在两个渲染器都用 cnido_net_annotations() 批量取注释，提示里给出基因号、
 *    描述、来源编号、InterPro/GO 条目和 gene_detail.php 链接。
 *
 * 3. **导出和注释查询各有一处坏掉的。** Dynamic Expression View 的导出函数里
 *    cy.jpg() 不存在、cy.json() 返回的是对象却被当 base64 字符串 split，导出
 *    JPG/JSON 一定失败（Network Analysis 那份后来被单独修好了，于是两份代码
 *    行为不一致）。另外 Network Analysis 取注释是**按节点逐个查**的
 *    （每个节点两次查询），节点上百时就是几百次查询 —— 现在一律批量 IN。
 *
 * 依赖：页面自己加载 cytoscape / jQuery / qtip（两个渲染器都已经加载了）。
 * 本文件只输出 CSS、一段 JSON 数据、一段提示与导出脚本，不引入任何库。
 * =========================================================================== */

/** 节点值里数字保留位数；表达式值（Dynamic Expression View 用） */
if (!defined('CNIDO_NET_MAX_NODES_FOR_ANNO')) {
    define('CNIDO_NET_MAX_NODES_FOR_ANNO', 4000);   // 注释批量查询的护栏
}

/**
 * 每个查询基因最多保留多少个共表达伙伴（top-K 截断）。
 *
 * 为什么必须有上限：共表达表里一个「枢纽」基因可以有上千个伙伴。SSIDE 的
 * FUN_000228-T1 有 5803 个，输入 10 个这样的基因时，Network Analysis 页面
 * 实测 **5807 节点 / 115,898 边 / 29.1 MB HTML、6.65 秒**。输入本来限 10 个
 * 基因，但结果没有任何上限，于是页面的体积由数据里的枢纽决定，不由用户决定。
 *
 * 取 100：枢纽基因的伙伴数中位数远小于此，正常基因集完全不受影响；只有枢纽
 * 会被截断，而 100 个伙伴已经足够看出它所在的模块。
 */
if (!defined('CNIDO_NET_TOP_K')) {
    define('CNIDO_NET_TOP_K', 100);
}

/**
 * 拼进 <script> 的 JSON。JSON_HEX_TAG 把 < > 变成 < >，避免数据里
 * 出现 `</script>` 把脚本提前闭合（注释文本来自外部数据库，不能当可信）。
 * 其余 HEX 选项把引号也转义掉，双重保险。
 */
function cnido_net_js($v)
{
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
                          | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/** HTML 转义（服务端拼 HTML 时用） */
function cnido_net_h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** 表名安全：abbr1 来自数据库，但拼进 SQL 前仍然校验一次 */
function cnido_net_abbr_ok($abbr)
{
    return is_string($abbr) && $abbr !== '' && preg_match('/^[A-Za-z0-9_.\-]+$/', $abbr);
}

/**
 * 物种解析：接受拉丁名 / 下划线名 / abbr1 代码，返回 array(latin, abbr, abbr1)。
 * 与 gene_detail.php 的取法一致（abbr 表只有三列：species, abbr, abbr1），
 * 页面用它建 <ABBR>_xxx 表名，也用它生成 gene_detail.php 链接。
 */
function cnido_net_ctx($conn, $token)
{
    $token = trim((string)$token);
    if ($token === '' || !($conn instanceof mysqli)) { return null; }
    $cand = array($token);
    if (strpos($token, ' ') !== false) { $cand[] = str_replace(' ', '_', $token); }
    if (strpos($token, '_') !== false) { $cand[] = str_replace('_', ' ', $token); }
    foreach ($cand as $c) {
        $e = mysqli_real_escape_string($conn, $c);
        $q = mysqli_query($conn, "SELECT species, abbr, abbr1 FROM abbr "
                               . "WHERE species = '$e' OR abbr = '$e' OR abbr1 = '$e' LIMIT 1");
        if ($q && ($r = mysqli_fetch_row($q))) {
            $out = array('latin' => trim((string)$r[0]),
                         'abbr'  => trim((string)$r[1]),
                         'abbr1' => trim((string)$r[2]));
            if (cnido_net_abbr_ok($out['abbr1'])) { return $out; }
        }
    }
    return null;
}

/** 表是否存在（information_schema 探一次，结果按表名缓存） */
function cnido_net_has_table($conn, $t)
{
    static $cache = array();
    if (isset($cache[$t])) { return $cache[$t]; }
    $e = mysqli_real_escape_string($conn, $t);
    $q = mysqli_query($conn, "SELECT 1 FROM information_schema.tables "
                           . "WHERE table_schema = DATABASE() AND table_name = '$e' LIMIT 1");
    $cache[$t] = ($q && mysqli_fetch_row($q)) ? true : false;
    return $cache[$t];
}

/** 把一组基因号转成 SQL 的 IN 列表（已转义） */
function cnido_net_in_list($conn, array $genes)
{
    $out = array();
    foreach ($genes as $g) { $out[] = "'" . mysqli_real_escape_string($conn, $g) . "'"; }
    return implode(',', $out);
}

/**
 * 一个查询基因的「top-K 共表达伙伴」，返回原表行（7 列，顺序同 SELECT *）：
 *   geneA, geneB, pcc, mr, level, rankB, rankA     —— 注意**全是 text 列**
 *
 * 伙伴关系在表里不分方向：查询基因 g 既可能出现在 geneA，也可能出现在 geneB。
 * 所以两个方向各取 $k 行：
 *   geneA = g  → 伙伴是 geneB，用 rankB（伙伴自己的名次）决胜
 *   geneB = g  → 伙伴是 geneA，用 rankA 决胜
 * 按「pair」去重后，再统一按强度排序返回，表格和网络图因此看到同一批伙伴、
 * 同一个顺序（两边不一致的话页面会自相矛盾）。
 *
 * ## 排序为什么必须带最后的基因号
 *
 * 这三列在数据里**大量并列**，实测 SSIDE 正向表：
 *   pcc = 1.000 的行占 36%（187284/519658）
 *   rankA = 1  的行占 28%（147850），rankB = 1 占 29%
 * 枢纽基因 FUN_000228-T1 的 5800 个伙伴里 rankA 只有 **13 个不同取值**。
 * 也就是说 `ORDER BY pcc/rank LIMIT 100` 取到的 100 行是从「一大片并列」里
 * 挑的，不加最后的基因号做决胜，MySQL 两次运行可能挑出不同的 100 行，
 * 同一个查询刷新两次给出不同的网络图。加上基因号，结果是确定的、可复现的。
 *
 * 代价要说清楚：**这三列实际上并不足以表达「最强的 100 个伙伴」**。并列太多，
 * 决胜主要靠基因号，所以拿到的是「并列区里基因号靠前的 100 个」，不是严格意义
 * 上相关性最高的 100 个。要真正按强度取舍，得让数据侧重算名次。
 *
 * ## pcc 的方向
 *
 * 正向表 pcc ∈ [0.900, 1]，负向表 pcc ∈ [-0.721, -0.123]（实测 SSIDE）。
 * 负相关的「强」是数值更小，所以负向表必须 ASC。原来 tail.list.inc 里写的是
 * `ORDER BY PCC DESC`，而 pcc 是 text 列 —— 文本比较下 '-0.72' > '-0.12'，
 * 负向表一直把**最弱**的负相关排在最前面。
 *
 * @param bool $positive 正向表传 true（pcc 取大），负向表传 false（pcc 取小）
 */
function cnido_net_partner_rows($conn, $tbl, $gene, $k, $positive)
{
    $rows = array();
    $gene = (string)$gene;
    if ($gene === '' || !preg_match('/^[A-Za-z0-9_]+$/', (string)$tbl)) { return $rows; }
    $k = (int)$k;
    if ($k < 1) { return $rows; }

    $g   = mysqli_real_escape_string($conn, $gene);
    $dir = $positive ? 'DESC' : 'ASC';

    /* pcc 是 text，必须 CAST 后再比较，否则 '0.9'/'1' 这类值按字典序排是错的 */
    $sql = "SELECT * FROM `$tbl` WHERE %s = '$g'
            ORDER BY CAST(pcc AS DECIMAL(10,9)) $dir, CAST(%s AS UNSIGNED) ASC,
                     CAST(%s AS UNSIGNED) ASC, %s ASC LIMIT $k";
    foreach (array(array('geneA', 'rankB', 'rankA', 'geneB'),      // 伙伴 = geneB
                   array('geneB', 'rankA', 'rankB', 'geneA')) as $c) {   // 伙伴 = geneA
        $q = mysqli_query($conn, sprintf($sql, $c[0], $c[1], $c[2], $c[3]));
        $n = 0;
        while ($q && ($r = mysqli_fetch_row($q))) {
            if (!is_array($r) || count($r) < 2) { continue; }
            $rows[$r[0] . "\t" . $r[1]] = $r;      // 按 pair 去重
            $n++;
        }
        if ($n >= $k) { cnido_net_trunc_flag(true); }   // 取满了 = 还有更多
    }

    /* 两个方向合并后按强度重排。pcc 用 floatval 比较（列是 text）。 */
    $sgn = $positive ? -1 : 1;
    uasort($rows, function ($a, $b) use ($gene, $sgn) {
        $c = $sgn * (floatval($a[2]) <=> floatval($b[2]));
        if ($c !== 0) { return $c; }
        $ra = cnido_net_partner_rank($a, $gene);
        $rb = cnido_net_partner_rank($b, $gene);
        if ($ra !== $rb) { return $ra <=> $rb; }
        return strcmp(cnido_net_partner_id($a, $gene), cnido_net_partner_id($b, $gene));
    });

    /* 截断是对**合并后的伙伴**做的，不是每个方向各留 $k 个。
       伙伴可能在 geneA 侧也可能在 geneB 侧（同一张表两个方向不分主次），
       若按方向各留 $k，一个基因最多会留下 2k 个伙伴 —— 用户要的是「每个基因
       100 个」。两个方向各取 $k 行是取并集 top-k 的充分条件（并集的前 k 名
       不可能有一侧贡献超过 k 个），所以先各取 $k 再合并排序取前 $k 是正确的。 */
    if (count($rows) > $k) {
        $rows = array_slice($rows, 0, $k, true);
        cnido_net_trunc_flag(true);            // 合并后还要砍 = 确实被截断了
    }
    return array_values($rows);
}

/** 行里「伙伴」那一侧的基因号（查询基因在另一侧）。
    用 strcasecmp 而不是 ===：MySQL 的 TEXT 列默认是不区分大小写的排序规则，
    用户把小写基因号粘进来时 `geneA = '$gene'` 照样命中，PHP 这边再按大小写
    严格比较就会把命中的行整行丢掉（表里少了这个伙伴，图上却还有这条边）。 */
function cnido_net_partner_id(array $r, $gene)
{
    return (strcasecmp((string)$r[0], (string)$gene) === 0) ? (string)$r[1] : (string)$r[0];
}

/** 行里伙伴自己的名次：伙伴是 geneB 用 rankB(5)，是 geneA 用 rankA(6) */
function cnido_net_partner_rank(array $r, $gene)
{
    $idx = (strcasecmp((string)$r[0], (string)$gene) === 0) ? 5 : 6;
    return isset($r[$idx]) ? (int)$r[$idx] : 0;
}

/**
 * 第一层：一组查询基因的直接伙伴，每个基因按 top-K 截断（见
 * cnido_net_partner_rows()），返回按 pair 去重的扁平行集，供网络图使用。
 *
 * **另外无条件补上查询基因两两之间的边**（最多 10×10 行）。这些边不能受
 * top-K 限制：用户提交的基因必须始终互相连在一起，否则「我提交的 3 个基因
 * 在图里互不相连」看起来就像数据缺失，而不是截断。
 */
function cnido_net_first_level($conn, $tbl, array $genes, $k, $positive)
{
    $out = array();
    $genes = array_values(array_unique(array_filter(array_map('strval', $genes), 'strlen')));
    if (!$genes) { return $out; }

    foreach ($genes as $g) {
        foreach (cnido_net_partner_rows($conn, $tbl, $g, $k, $positive) as $r) {
            $out[$r[0] . "\t" . $r[1]] = $r;
        }
    }

    if (preg_match('/^[A-Za-z0-9_]+$/', (string)$tbl)) {
        $in = cnido_net_in_list($conn, $genes);
        $q  = mysqli_query($conn, "SELECT * FROM `$tbl` WHERE geneA IN ($in) AND geneB IN ($in)");
        while ($q && ($r = mysqli_fetch_row($q))) {
            if (is_array($r) && count($r) >= 2) { $out[$r[0] . "\t" . $r[1]] = $r; }
        }
    }
    return array_values($out);
}

/**
 * 「这一页有没有被 top-K 截断」的标记。
 *
 * 不额外查库：某个方向取回的行数正好等于 $k，就说明那个基因的伙伴 ≥ k 个，
 * 被截了。页面据此提示用户「看到的不是完整网络」—— 不提示的话，用户会以为
 * 枢纽基因就只有 100 个伙伴。用静态变量而不是返回值，是为了不改
 * cnido_net_partner_rows() 的返回结构（表格那边直接 foreach 用）。
 *
 * 页面开头调 cnido_net_trunc_flag(false) 复位，末尾调 cnido_net_trunc_flag() 读。
 */
function cnido_net_trunc_flag($set = null)
{
    static $flag = false;
    if ($set !== null) { $flag = (bool)$set; }
    return $flag;
}

/**
 * 批量取基因注释。返回 array(gene => array(...))，字段：
 *
 *   pan / panid / panmethod   <ABBR>_panther 的 anno / id / method
 *   ipr[]                     <ABBR>_ipr 的 Description（最多 3 条）
 *   go[]                      <ABBR>_go 的 Description（最多 3 条）
 *   nr                        <ABBR>_nr 的 description
 *
 * 取哪个表看该物种有没有（各表覆盖不一致：31 个有共表达网络的物种里 _panther
 * /_ipr/_go 都是 31/31，_nr 只有 27/31），并按 500 个基因一批查，避免一条巨长
 * 的 IN 语句。**不按节点逐个查** —— 原实现每个节点查两次，节点上百就是几百次。
 */
function cnido_net_annotations($conn, $abbr, array $genes)
{
    $out = array();
    $genes = array_values(array_unique(array_filter(array_map('strval', $genes), 'strlen')));
    if (!$genes || !cnido_net_abbr_ok($abbr)) { return $out; }
    if (count($genes) > CNIDO_NET_MAX_NODES_FOR_ANNO) {
        $genes = array_slice($genes, 0, CNIDO_NET_MAX_NODES_FOR_ANNO);
    }
    foreach ($genes as $g) { $out[$g] = array(); }
    $chunks = array_chunk($genes, 500);

    /* 单值表：panther / nr —— 每个基因一行，第一次命中的胜出 */
    $single = array(
        'panther' => array('tbl' => 'panther', 'cols' => 'gene, id, anno, method',
                           'map' => array(1 => 'panid', 2 => 'pan', 3 => 'panmethod')),
        'nr'      => array('tbl' => 'nr', 'cols' => 'gene, description',
                           'map' => array(1 => 'nr')),
    );
    foreach ($single as $spec) {
        $t = $abbr . '_' . $spec['tbl'];
        if (!cnido_net_has_table($conn, $t)) { continue; }
        foreach ($chunks as $ch) {
            $q = mysqli_query($conn, "SELECT {$spec['cols']} FROM `$t` WHERE gene IN ("
                                   . cnido_net_in_list($conn, $ch) . ")");
            while ($q && ($r = mysqli_fetch_row($q))) {
                $g = (string)$r[0];
                if (!isset($out[$g])) { continue; }
                foreach ($spec['map'] as $idx => $key) {
                    $v = trim((string)(isset($r[$idx]) ? $r[$idx] : ''));
                    if ($v === '' || $v === '-' || $v === '--') { continue; }
                    if (!isset($out[$g][$key])) { $out[$g][$key] = $v; }
                }
            }
        }
    }

    /* 多值表：ipr / go —— 一个基因可有多条，各留最多 3 条 */
    foreach (array('ipr', 'go') as $tbl) {
        $t = $abbr . '_' . $tbl;
        if (!cnido_net_has_table($conn, $t)) { continue; }
        foreach ($chunks as $ch) {
            $q = mysqli_query($conn, "SELECT gene, Description FROM `$t` WHERE gene IN ("
                                   . cnido_net_in_list($conn, $ch) . ")");
            while ($q && ($r = mysqli_fetch_row($q))) {
                $g = (string)$r[0];
                $v = trim((string)$r[1]);
                if (!isset($out[$g]) || $v === '') { continue; }
                if (!isset($out[$g][$tbl])) { $out[$g][$tbl] = array(); }
                if (count($out[$g][$tbl]) < 3 && !in_array($v, $out[$g][$tbl], true)) {
                    $out[$g][$tbl][] = $v;
                }
            }
        }
    }
    return $out;
}

/**
 * 一行摘要，写进节点的 annotation 字段（也给不支持 JS 的场景兜底）。
 * 优先 PANTHER 描述，其次 InterPro，再退到 NR。
 */
function cnido_net_summary(array $a)
{
    if (!empty($a['pan']))   { return $a['pan']; }
    if (!empty($a['ipr'][0])) { return $a['ipr'][0]; }
    if (!empty($a['go'][0]))  { return $a['go'][0]; }
    if (!empty($a['nr']))     { return $a['nr']; }
    return '';
}

/**
 * 一个节点 -> 一行 JS。所有值走 cnido_net_js()，不再手工拼引号。
 * $n 支持：id、name、weight、color、shape、annotation、value（表达值）。
 */
function cnido_net_node_line(array $n)
{
    $data = array(
        'id'         => (string)$n['id'],
        'name'       => isset($n['name']) ? (string)$n['name'] : (string)$n['id'],
        'weight'     => isset($n['weight']) ? (float)$n['weight'] : 15,
        'faveColor'  => (string)$n['color'],
        'faveShape'  => (string)$n['shape'],
        'annotation' => isset($n['annotation']) ? (string)$n['annotation'] : '',
    );
    if (array_key_exists('value', $n) && $n['value'] !== null && $n['value'] !== '') {
        $data['value'] = (string)$n['value'];
    }
    if (isset($n['degree'])) { $data['degree'] = (int)$n['degree']; }
    return "\t\t{ data: " . cnido_net_js($data) . " },\n";
}

/**
 * 一条边 -> 一行 JS。$e 支持 source、target、color、strength、kind。
 * kind 用于提示里说明这条边是正相关还是负相关。
 */
function cnido_net_edge_line(array $e)
{
    $data = array(
        'source'    => (string)$e['source'],
        'target'    => (string)$e['target'],
        'faveColor' => (string)$e['color'],
        'strength'  => isset($e['strength']) ? (float)$e['strength'] : 10,
    );
    if (!empty($e['kind'])) { $data['kind'] = (string)$e['kind']; }
    return "\t\t{ data: " . cnido_net_js($data) . " },\n";
}

/**
 * 把注释表和一个节点->表达值的表交给页面 JS。$values 可省略
 * （Network Analysis 的节点没有单一表达值，值在边上）。
 */
function cnido_net_payload(array $anno, $latin, array $values = array())
{
    $clean = array();
    foreach ($anno as $g => $a) {
        if (!$a) { continue; }
        $clean[(string)$g] = $a;
    }
    echo '<script type="text/javascript">' . "\n" . '/*<![CDATA[*/' . "\n";
    echo 'var CNIDO_NET_ANNO = ' . cnido_net_js($clean) . ";\n";
    echo 'var CNIDO_NET_VAL  = ' . cnido_net_js($values) . ";\n";
    echo 'var CNIDO_NET_META = ' . cnido_net_js(array(
        'latin'    => (string)$latin,
        /* 与 tail.list.inc 里节点表格的链接写法保持一致（相对本目录） */
        'geneBase' => '../gene_detail.php?species=' . rawurlencode((string)$latin) . '&gene=',
    )) . ";\n";
    echo '/*]]>*/' . "\n" . '</script>' . "\n";
}

/** hover 卡片样式。qtip2 默认 max-width 280px，这里放宽并重排。 */
function cnido_net_tip_css()
{
    ?>
<style type="text/css">
.qtip-ngtip{max-width:360px}
.ngtip{font-size:15px;line-height:1.55;color:#1e293b;text-align:left}
.ngtip-id{font-weight:700;font-family:Menlo,Consolas,monospace;font-size:15px;color:#0f172a;word-break:break-all}
.ngtip-desc{font-weight:600;color:#0f172a;margin:2px 0 3px}
.ngtip-src{color:#475569;font-size:12px}
.ngtip-none{color:#64748b;font-size:12px;font-style:italic;margin:2px 0}
.ngtip-val{color:#1d4ed8;font-size:12px;margin-top:3px}
.ngtip-link{margin-top:6px;font-size:12px;border-top:1px solid #e2e8f0;padding-top:4px}
.ngtip-link a{color:#1d4ed8;text-decoration:none}
.ngtip-link a:hover{text-decoration:underline}
</style>
    <?php
}

/**
 * hover 提示：cytoscape 节点 → qTip2 卡片。
 *
 * 只定义 cnidoNetBindTips()，不在本文件里自动执行 —— 它必须在 cytoscape
 * 实例建好之后调用，所以由各渲染器在自己的 qtip 调用点调（Network Analysis
 * 在 tail.list.inc、Dynamic Expression View 在 network.expression.php），
 * 那两处都在 jQuery 的 dom-ready 回调里，cy 一定已经存在。
 *
 * 卡片内容现取自 CNIDO_NET_ANNO，不再依赖写死在节点对象里的那个字段
 * （旧实现在 Dynamic Expression View 里把它写成了空串，于是悬停只显示
 * 「基因号 annotation: 」，冒号后面什么都没有）。
 * position.viewport 保证卡片不跑出窗口；show.solo 避免同时冒出好几个。
 */
function cnido_net_tip_js()
{
    ?>
<script type="text/javascript">
/*<![CDATA[*/
function cnidoNetEsc(s) {
  return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}
function cnidoNetCard(id) {
  var A = (typeof CNIDO_NET_ANNO !== 'undefined' && CNIDO_NET_ANNO) ? CNIDO_NET_ANNO : {};
  var V = (typeof CNIDO_NET_VAL  !== 'undefined' && CNIDO_NET_VAL)  ? CNIDO_NET_VAL  : {};
  var M = (typeof CNIDO_NET_META !== 'undefined' && CNIDO_NET_META) ? CNIDO_NET_META : {};
  var esc = cnidoNetEsc;
  function joinl(x) { return (x && x.length) ? x.join('; ') : ''; }

  var a = A[id] || {};
  var h = '<div class="ngtip">';
  h += '<div class="ngtip-id">' + esc(id) + '</div>';
  var desc = a.pan || (a.ipr && a.ipr[0]) || (a.go && a.go[0]) || a.nr || '';
  if (desc) { h += '<div class="ngtip-desc">' + esc(desc) + '</div>'; }
  if (a.panid) { h += '<div class="ngtip-src">PANTHER ' + esc(a.panid) + '</div>'; }
  if (a.ipr && a.ipr.length) { h += '<div class="ngtip-src">InterPro: ' + esc(joinl(a.ipr)) + '</div>'; }
  if (a.go && a.go.length)   { h += '<div class="ngtip-src">GO: ' + esc(joinl(a.go)) + '</div>'; }
  if (!desc && !a.panid && !(a.ipr && a.ipr.length) && !(a.go && a.go.length)) {
    h += '<div class="ngtip-none">no functional annotation recorded for this gene</div>';
  }
  var v = V[id];
  if (v !== undefined && v !== null && v !== '') {
    h += '<div class="ngtip-val">expression value: <b>' + esc(v) + '</b></div>';
  }
  if (M.geneBase) {
    h += '<div class="ngtip-link"><a href="' + esc(M.geneBase) + encodeURIComponent(id) +
         '" target="_blank" rel="noopener noreferrer">open gene page &rarr;</a></div>';
  }
  h += '</div>';
  return h;
}
function cnidoNetBindTips() {
  if (typeof cy === 'undefined' || !cy || !cy.nodes) { return; }
  if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.qtip) { return; }
  cy.nodes().forEach(function (n) {
    n.qtip({
      content: { text: cnidoNetCard(n.id()) },
      position: { my: 'top center', at: 'bottom center', viewport: true },
      show: { delay: 120, solo: true },
      hide: { fixed: true, delay: 60 },
      style: { classes: 'qtip-bootstrap qtip-ngtip', tip: { width: 14, height: 8 } }
    });
  });
}
/*]]>*/
</script>
    <?php
}

/**
 * 导出图。原来两份各写一遍，Dynamic Expression View 那份是坏的：
 *   - cy.jpg() 在 cytoscape.js 里不存在（应为 cy.jpeg()），一定抛异常；
 *   - cy.json() 返回的是**对象**，代码却对它 split(',') 当 base64 解，
 *     导出 JSON 必然失败。
 * 这里统一成：cy.png()/cy.jpeg() 给的是 base64 data URI，转 Blob 下载；
 * cy.json() 走 JSON.stringify。下拉框 id 两个页面不同（export / cyExport），
 * 都兼容；取值优先看 option 的 value（jpg/png/json），拿不到再按下标兜底。
 */
function cnido_net_export_js()
{
    ?>
<script type="text/javascript">
/*<![CDATA[*/
function base64ToBlob(base64, mimeType) {
  var arr = base64.split(','), mime = arr[0].match(/:(.*?);/)[1] || mimeType;
  var bstr = atob(arr[1]), n = bstr.length, u8 = new Uint8Array(n);
  while (n--) { u8[n] = bstr.charCodeAt(n); }
  return new Blob([u8], { type: mime });
}
function cnidoNetDownload(blob, filename) {
  if (!blob) { return; }
  var a = document.createElement('a');
  a.download = filename;
  a.href = URL.createObjectURL(blob);
  a.click();
  setTimeout(function () { URL.revokeObjectURL(a.href); }, 100);
}
function exportgraph() {
  var sel = document.getElementById('export') || document.getElementById('cyExport');
  if (!sel) { alert('Export dropdown not found.'); return; }
  if (typeof cy === 'undefined' || !cy) {
    alert('Network graph is not loaded yet. Please wait.'); return;
  }
  var fmt = sel.value;
  if (!fmt) {                                  // 没有 value 时按下标兜底
    fmt = ['jpg', 'png', 'json'][sel.selectedIndex] || '';
  }
  fmt = String(fmt).toLowerCase();
  if (fmt === 'png') {
    cnidoNetDownload(base64ToBlob(cy.png(), 'image/png'), 'cytoscape.png');
  } else if (fmt === 'jpg' || fmt === 'jpeg') {
    cnidoNetDownload(base64ToBlob(cy.jpeg(), 'image/jpeg'), 'cytoscape.jpg');
  } else if (fmt === 'json') {
    cnidoNetDownload(new Blob([JSON.stringify(cy.json(), null, 2)],
                              { type: 'application/json' }), 'cytoscape.json');
  } else {
    alert('Unsupported export format.');
  }
}
/*]]>*/
</script>
    <?php
}

/**
 * 节点颜色图例。两个渲染器的用色不同（Network Analysis 按「查询基因/互作基因」
 * 分色，Dynamic Expression View 按表达阈值分色），所以由调用方给出色块。
 * $items: array(array('color' => '#rrggbb', 'text' => '...'))
 */
function cnido_net_legend(array $items)
{
    echo '<div class="legend-container">' . "\n"
       . '  <div class="legend-title">Network Legend</div>' . "\n"
       . '  <div class="legend-grid">' . "\n";
    foreach ($items as $it) {
        echo '    <div class="legend-item">'
           . '<div class="legend-color" style="background-color:' . cnido_net_h($it['color']) . '"></div>'
           . '<span>' . $it['text'] . '</span></div>' . "\n";
    }
    echo '  </div>' . "\n" . '</div>' . "\n";
}
