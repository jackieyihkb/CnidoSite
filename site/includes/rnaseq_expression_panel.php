<?php
/* ===========================================================================
 * includes/rnaseq_expression_panel.php
 *
 * gene_detail.php 上的「Expression pattern (RNA-seq)」面板：把某个基因在
 * 该物种所有 RNA-seq 样本里的表达量（TPM）和样本信息一起画出来。
 *
 * 这一版解决了三个问题
 * --------------------
 * 1. 以前只有 Lophelia pertusa 有表达谱。旧代码里写死了
 *
 *        if ($species == "Lophelia pertusa") { ... }
 *
 *    并且把 110 个样本号、每个样本的颜色、平均值的分组、连 x 轴标签都
 *    以三个巨大的字面量数组抄在页面里。别的物种即使有 <ABBR>_TPM 表也
 *    看不到任何表达信息。现在凡是有 <ABBR>_TPM 表的物种都会显示，共 31 个。
 *
 * 2. 旧图是「每个分组的均值 ± 标准差」的样条 + 误差棒，横过来画。分组多、
 *    样本少的时候看比不出来自几个样本，样本只有一两个时误差棒也没有意义。
 *    现在一律用横向条形图：**一根条就是一个样本**，样本只有一个时就只有
 *    一根条，不做任何特殊处理，也不会退化成空图。
 *
 * 3. 样本信息（组织、发育阶段、处理、BioProject/研究号）以前完全看不到。
 *    现在：条形按条件分组着色 + 图例，鼠标悬停给出该样本的全部元数据，
 *    下面还有一张可筛选的样本明细表（run 号链到 NCBI SRA）。
 *
 * 数据来源
 * --------
 * 表达量   ：MySQL `cnidaria` 库里的 <ABBR>_TPM 表（每个物种一张，行是
 *            transcript/mRNA 号，列是样本）。列名本身就是样本名，形如
 *            SRR7992468_polyp_endoderm_from_body_column_polyp —— 前一段是
 *            SRA run 号，后面是组织与发育阶段。
 * 样本元数据：data/rnaseq_samples.json，由 includes/rnaseq_meta_refresh.php
 *            从 /mnt/.../RNA-seq/sample 离线生成（带 run 号索引，页面不碰
 *            数据盘）。索引里没有的样本退回按列名解析；列名也没有的（例如
 *            MCAPI 的裸 run 号列）就只有 run 号可显示。
 *            索引缺失时面板照常工作，只是元数据少一些。
 *
 * 安装方式 —— 一个文件，一行调用
 * ------------------------------
 * 把本文件放到 includes/rnaseq_expression_panel.php，然后在 gene_detail.php
 * 里希望出现面板的位置加：
 *
 *     require_once __DIR__ . '/includes/rnaseq_expression_panel.php';
 *     render_rnaseq_expression_panel($gene, $species, $conn);
 *
 * $gene 与 $species 用 gene_detail.php 手上已有的变量即可（$species 传拉丁
 * 名、下划线形式或 5 位代码都行）。$conn 可选，不传就自己连。
 *
 * 依赖：页面需要已经加载 Highcharts（gene_detail.php 已经加载了
 * js/highcharts.js）。本面板不额外引入任何库。
 * =========================================================================== */

require_once __DIR__ . '/state.php';

/** 元数据索引路径（站点根 data/ 下，和 data/overview_pairs.json 同一处） */
if (!defined('CNIDO_RXS_INDEX')) {
    define('CNIDO_RXS_INDEX', dirname(__DIR__) . '/data/rnaseq_samples.json');
}

/* 分组着色用的分类色板。取自站点通用色（#1d4ed8/#2563eb/#3b82f6/#16a34a/
 * #94a3b8/#64748b）再加几个区分度够用的色相，按组数循环使用。 */
function cnido_rxs_palette()
{
    return array('#2563eb', '#16a34a', '#d97706', '#dc2626', '#7c3aed',
                 '#0891b2', '#65a30d', '#db2777', '#475569', '#ca8a04',
                 '#0d9488', '#9333ea');
}

function cnido_rxs_h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** 把 'NA' / 'N/A' / '-' / '' 之类统一成空串（与生成脚本同一套约定） */
function cnido_rxs_clean($v)
{
    $v = trim((string)$v);
    if ($v === '' || $v === '-' || $v === '--') { return ''; }
    if (in_array(strtolower($v), array('na', 'n/a', 'none', 'null', 'nan'), true)) { return ''; }
    return $v;
}

/**
 * 分组用的比较键：把只差大小写/标点/单复数的写法并到一起。
 * 元数据是人工填的，同一个条件在表里出现的写法并不统一 —— ATENE 的 17 个样本
 * 里就有 'Whole Organism'、'whole organism'、'whole'，AAURI1 里 'Tentacle' 与
 * 'Tentacles' 并存，LPERT 那批 pH 实验的列名又写成了 'Polyp' 和 'polyp'。各算
 * 一个条件会让「条件数」虚高、同一条件被拆成两种颜色，看着就像数据有问题。
 * 这里只归一比较键，显示仍然用源数据里的原写法。
 */
function cnido_rxs_key($s)
{
    $s = strtolower(trim((string)$s));
    $s = trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
    if ($s === '') { return ''; }
    $out = array();
    foreach (explode(' ', $s) as $w) {
        if ($w === '') { continue; }
        /* 末尾的复数 s 去掉（'weeks'->'week'、'tentacles'->'tentacle'，但不碰
           'ss' 结尾）。两边都过同一个函数，即使把某个词削过头也仍然一致。 */
        if (strlen($w) > 4 && substr($w, -1) === 's' && substr($w, -2) !== 'ss') {
            $w = substr($w, 0, -1);
        }
        $out[] = $w;
    }
    return implode(' ', $out);
}

/**
 * 把表达矩阵列名里带的那段描述收拾成条件名。元数据索引里没有的样本只能靠它，
 * 而这类描述是拼出来的、带着逐样本的记号：
 *
 *     SRR11359494_polyp_Colony1_sampled_2weeks_at_pH7_9
 *
 * 原样当组名，36 个「pH 7.9 下的 polyp」就会变成 18 个各含两个样本的组。这里
 * 只去掉**逐样本**的记号（第几号群体、是否 sampled、取样周数），处理条件一律
 * 保留 —— pH 7.6 / 7.9、诱导时长这些才是要比较的变量，砍掉它们就真丢信息了。
 */
function cnido_rxs_desc($d)
{
    /* 时长（'2weeks'、'4_5weeks'、'3days'）属于取样细节，整段去掉。注意两点：
       （1）不能锚 \b —— 下划线在正则里也是 \w，'sampled_2weeks' 中间没有词边界；
       （2）数字之间允许空格，因为调用前列名里的 '_' 已经换成空格，'4_5weeks'
           到这里是 '4 5weeks'，只吃 '5weeks' 会在组名里留下一个孤零零的 '4'。 */
    $d = preg_replace('/\d+(?:[._\s]\d+)*\s*(?:weeks?|days?|hrs?|hours?)/i', ' ', (string)$d);
    $d = str_replace('_', ' ', $d);
    $out = array();
    foreach (preg_split('/[^A-Za-z0-9.]+/', $d) as $w) {
        if ($w === '') { continue; }
        if (preg_match('/^(?:colony|rep|replicate|sample|sampled|individual|specimen)\d*$/i', $w)) { continue; }
        $out[] = $w;
    }
    return trim(implode(' ', $out));
}

function cnido_rxs_conn($conn = null)
{
    if ($conn instanceof mysqli) { return $conn; }
    $c = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    return $c->connect_error ? null : $c;
}

function cnido_rxs_table_exists($conn, $t)
{
    $t = mysqli_real_escape_string($conn, $t);
    $q = mysqli_query($conn, "SELECT 1 FROM information_schema.tables "
                           . "WHERE table_schema = DATABASE() AND table_name = '$t' LIMIT 1");
    return $q && mysqli_fetch_row($q);
}

/**
 * 把 gene_detail.php 传进来的物种标识解析成 abbr1 代码（5 位，同时也是
 * <ABBR>_TPM 的表前缀）。$species 可能是拉丁名、下划线形式，也可能已经是
 * 代码；gene 参数只在 $species 为空时用作兜底（形如 EDIAP_KXJ04192.1）。
 */
function cnido_rxs_abbr($species, $gene, $conn)
{
    $cand = array();
    $species = trim((string)$species);
    if ($species !== '') {
        $cand[] = $species;
        $cand[] = str_replace('_', ' ', $species);
    }
    if ($gene !== '' && preg_match('/^([A-Za-z0-9]{3,10})_/', $gene, $m)) {
        $cand[] = $m[1];                       // 形如 EDIAP_KXJ04192.1 的前缀
    }
    foreach ($cand as $c) {
        $e = mysqli_real_escape_string($conn, $c);
        $q = mysqli_query($conn, "SELECT abbr1, species FROM abbr "
                               . "WHERE abbr1 = '$e' OR abbr = '$e' OR species = '$e' LIMIT 1");
        if ($q && ($r = mysqli_fetch_row($q)) && trim($r[0]) !== '') {
            return array(trim($r[0]), trim($r[1]));
        }
    }
    return array(null, null);
}

/** 读元数据索引；缺失或损坏时返回空数组，面板退回按列名解析。 */
function cnido_rxs_meta()
{
    static $runs = null;
    if ($runs !== null) { return $runs; }
    $runs = array();
    if (@is_readable(CNIDO_RXS_INDEX)) {
        $j = json_decode(file_get_contents(CNIDO_RXS_INDEX), true);
        if (is_array($j) && isset($j['runs']) && is_array($j['runs'])) {
            $runs = $j['runs'];
        }
    }
    return $runs;
}

/**
 * 一个样本列名 -> 样本记录。
 * 列名形如 SRR7992468_polyp_endoderm_from_body_column_polyp。
 * 索引里有元数据就以索引为准（组织、发育阶段、处理分得清楚），否则用列名
 * 里剩下的那段当描述。
 */
function cnido_rxs_sample($col, array $meta)
{
    if (!preg_match('/^([SED]RR\d+)(?:_(.*))?$/', $col, $m)) {
        return null;
    }
    $run  = $m[1];
    $rest = isset($m[2]) ? trim(str_replace('_', ' ', $m[2])) : '';
    $rest = trim(preg_replace('/\s+/', ' ', $rest));

    $rec = array(
        'run' => $run, 'tissue' => '', 'stage' => '', 'treat' => '',
        'project' => '', 'study' => '', 'x' => '', 'layout' => '',
        'desc' => $rest, 'src' => 'name',
    );
    if (isset($meta[$run]) && is_array($meta[$run])) {
        $src = $meta[$run];
        $rec['src'] = 'meta';
        foreach (array('tissue', 'stage', 'treat', 'project', 'study', 'x', 'layout') as $k) {
            if (isset($src[$k]) && $src[$k] !== '') { $rec[$k] = cnido_rxs_clean($src[$k]); }
        }
    }
    /* 分组用的条件名：组织优先，其次发育阶段，再退到列名描述（收拾掉逐样本记号），
       最后 unannotated。有处理（treatment）时并进条件名里 —— 只看组织的话
       LPERT 的 64 个样本全是 'coral polyp'、NVECT 的 48 个全是同一段再生组织，
       各自只剩一组，而真正区分它们的是处理（油/分散剂、再生时间点）。 */
    if ($rec['tissue'] !== '')    { $base = $rec['tissue']; }
    elseif ($rec['stage'] !== '') { $base = $rec['stage']; }
    elseif ($rec['desc'] !== '')  { $base = cnido_rxs_desc($rec['desc']); }
    else                          { $base = ''; }

    if ($base !== '' && $rec['treat'] !== '') { $rec['group'] = $base . ' · ' . $rec['treat']; }
    elseif ($base !== '')                     { $rec['group'] = $base; }
    else                                      { $rec['group'] = $rec['treat']; }
    $rec['gkey'] = cnido_rxs_key($rec['group']);
    return $rec;
}

/**
 * 取 <ABBR>_TPM 表里该基因一行，返回 array('cols'=>列名, 'vals'=>值)。
 *
 * 候选写法有两重维度，必须交叉试，否则会出现「同一个基因换个写法表达量全变 0」：
 *   1. 带不带 ABBR_ 前缀 —— 不同来源的页面链接给的写法不一样
 *      （_locus.mRNA 是裸号，其它表有时带前缀）；
 *   2. 带不带 `.1` 版本后缀 —— TPM 矩阵与注释表一样存的是 mRNA 级 ID
 *      （HVIRI 的 TPM 表里是 BRAKERKREP00000015160.1），而 _locus 存不带后缀的
 *      形式。少了这一重，用 _locus 写法进来的用户会看到「该基因表达量全 0」，
 *      而实际上是号没对上。见 includes/state.php 的 cnido_gene_id_forms()。
 *
 * 逐个试而不是拼成一个 IN 查询：要保证「原样写法优先命中」，IN + LIMIT 1
 * 命中哪一行是不确定的。
 */
function cnido_rxs_row($conn, $abbr, $gene)
{
    $cands  = cnido_gene_id_forms($gene);
    $stripped = preg_replace('/^' . preg_quote($abbr, '/') . '_/', '', $gene);
    if ($stripped !== $gene && $stripped !== '') {
        $cands = array_merge($cands, cnido_gene_id_forms($stripped));
    }
    foreach (array_unique($cands) as $g) {
        $e = mysqli_real_escape_string($conn, $g);
        $q = mysqli_query($conn, "SELECT * FROM `{$abbr}_TPM` WHERE Gene = '$e' LIMIT 1");
        if ($q && ($r = mysqli_fetch_row($q))) { return $r; }
    }
    return null;
}

/**
 * 主入口：渲染表达面板。
 *
 * @param string $gene    基因/mRNA 号
 * @param string $species 拉丁名、下划线形式或 abbr1 代码
 * @param mysqli $conn    可选的现成连接
 */
function render_rnaseq_expression_panel($gene, $species = null, $conn = null)
{
    $gene = trim((string)$gene);
    if ($gene === '') { return; }

    $conn = cnido_rxs_conn($conn);
    if ($conn === null) { return; }                       // 连不上库就不显示，不报错

    list($abbr, $latin) = cnido_rxs_abbr($species, $gene, $conn);
    if ($abbr === null) { return; }

    /* 该物种没有 RNA-seq 表达矩阵：静默。这是「本来就没有」，不是缺陷。 */
    if (!cnido_rxs_table_exists($conn, $abbr . '_TPM')) { return; }

    $row = cnido_rxs_row($conn, $abbr, $gene);

    /* 列出样本列。列名以 run 号开头的才算样本，'Gene' / 'avg' / 'std'
       （有的表拼成 'ave' / 'atd'）一律跳过。 */
    $cols = array();
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `{$abbr}_TPM`");
    if (!$q) { return; }
    while ($c = mysqli_fetch_row($q)) {
        if (preg_match('/^[SED]RR\d+/', $c[0])) { $cols[] = $c[0]; }
    }
    if (!$cols) { return; }

    $meta = cnido_rxs_meta();

    /* ---- 组装样本：值 + 元数据 ---- */
    $samples = array();
    foreach ($cols as $i => $col) {
        $s = cnido_rxs_sample($col, $meta);
        if ($s === null) { continue; }
        $raw = ($row !== null && isset($row[$i + 1])) ? trim((string)$row[$i + 1]) : '';
        if ($raw === '' || $raw === '-' || !is_numeric($raw)) { $raw = 0.0; }
        $s['tpm'] = (float)$raw;
        $samples[] = $s;
    }
    if (!$samples) { return; }

    $n        = count($samples);
    $detected = 0;
    $tpmMax   = 0.0;
    $tpmSum   = 0.0;
    foreach ($samples as $s) {
        if ($s['tpm'] > 0) { $detected++; }
        if ($s['tpm'] > $tpmMax) { $tpmMax = $s['tpm']; }
        $tpmSum += $s['tpm'];
    }
    $tpmMean = $n ? $tpmSum / $n : 0.0;

    /* ---- 分组统计（按条件汇总，给上面的小结表用） ----
       数组键是归一后的比较键 $gkey，显示用该组第一次出现的原写法 $label：
       'Polyp' 与 'polyp' 合成一组，但组名不会被改成小写。 */
    $groups = array();
    foreach ($samples as $s) {
        $k = $s['gkey'];
        if (!isset($groups[$k])) {
            $groups[$k] = array('n' => 0, 'sum' => 0.0, 'max' => 0.0, 'pos' => 0,
                                'label' => $s['group'] !== '' ? $s['group'] : 'unannotated');
        }
        $groups[$k]['n']++;
        $groups[$k]['sum'] += $s['tpm'];
        if ($s['tpm'] > $groups[$k]['max']) { $groups[$k]['max'] = $s['tpm']; }
        if ($s['tpm'] > 0) { $groups[$k]['pos']++; }
    }
    uasort($groups, function ($a, $b) { return $b['n'] - $a['n']; });

    /* ---- 排序：先按组（组按样本数降序），组内按 TPM 降序 ---- */
    $gorder = array();
    $gi = 0;
    foreach ($groups as $k => $_) { $gorder[$k] = $gi++; }
    usort($samples, function ($a, $b) use ($gorder) {
        $ga = $gorder[$a['gkey']];
        $gb = $gorder[$b['gkey']];
        if ($ga !== $gb) { return $ga - $gb; }
        if ($a['tpm'] == $b['tpm']) { return strcmp($a['run'], $b['run']); }
        return ($a['tpm'] < $b['tpm']) ? 1 : -1;          // TPM 降序
    });

    /* ---- 组 -> 颜色 ---- */
    $pal = cnido_rxs_palette();
    $gcolor = array();
    $k = 0;
    foreach ($groups as $g => $_) { $gcolor[$g] = $pal[$k % count($pal)]; $k++; }

    /* ---- 给 JS 的数据 ---- */
    $chartRows = array();
    foreach ($samples as $s) {
        $g = $s['gkey'];
        $bits = array();
        if ($s['tissue'] !== '')  { $bits[] = $s['tissue']; }
        if ($s['stage'] !== '')   { $bits[] = $s['stage']; }
        if ($s['treat'] !== '')   { $bits[] = $s['treat']; }
        if (!$bits && $s['desc'] !== '') { $bits[] = $s['desc']; }
        $chartRows[] = array(
            'run'   => $s['run'],
            'tpm'   => round($s['tpm'], 4),
            'group' => $groups[$g]['label'],
            'color' => $gcolor[$g],
            'info'  => implode(' &middot; ', $bits),
            'study' => $s['study'] !== '' ? $s['study'] : $s['project'],
            'x'     => $s['x'],
        );
    }
    $payload = array(
        'gene'    => $gene,
        'species' => $latin,
        'rows'    => $chartRows,
        'unit'    => 'TPM',
    );

    $chartH = max(180, $n * 20 + 90);        // 每根条 20px
    $boxH   = min($chartH, 560);             // 容器高度封顶，超出滚动

    /* 登记进 gene_detail.php 的组学跳转条。本面板可以单独使用（不经过
       gene_panels_common），所以先问一句函数在不在。 */
    if (function_exists('cnido_gp_nav_add')) {
        /* 状态恒为 yes：本面板在上面就已经 return 掉了没有样本的情形
           （if (!$samples) return），能走到这里就说明这个基因有表达值。
           跳转条上的绿点是「这份组学里有这个基因」的意思，见 cnido_gp_nav_dot。 */
        cnido_gp_nav_add('rnaseq-expression', 'Expression (RNA-seq)',
            $n . ' sample' . ($n === 1 ? '' : 's'), 'yes');
    }
    ?>
<div class="rxs-panel cn-anchor" id="rnaseq-expression">
<style type="text/css">
<?php /* 卡片外壳（边框/圆角/内边距）、标题 .rxs-panel h3 与副标题 .rxs-sub 的排版，
   2026-09 起统一由 includes/gene_panels_common.php 的 cnido_gp_css() 提供 ——
   本页六张卡片原来分属三套实现（.gp-card / .rxs-panel / .gpe-panel），半径与
   内边距各不相同，同一屏里能看到三种卡片。这里只留 .rxs-* 自己的内部元素。 */ ?>
.rxs-stats{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 14px}
.rxs-stat{flex:1 1 110px;background:#f8fafc;border-radius:8px;padding:9px 12px;text-align:center}
.rxs-stat b{display:block;font-size:20px;color:#1d4ed8;line-height:1.25}
.rxs-stat span{font-size:13px;color:#64748b}
<?php /* 本面板的两张表直接挂 gridtable：表头浅灰底 + 细下边框、行分隔线、斑马纹、
   悬停高亮、蓝色链接全部由 templatemo_style.css 的共用样式提供（与 core 的
   table.cc 一致）。原来这里自带的一整套（#f1f5f9 表头、#f1f5f9 上边框、
   #f8fafc 悬停、12.5px 字号）本来就是照着共用样式抄的近似值，抄得还不一样
   （悬停底色差一档、边框在上不在下、字号小 1px）—— 删掉，只留本面板特有的。 */ ?>
table.rxs-tbl td.num,table.rxs-tbl th.num{text-align:right;font-variant-numeric:tabular-nums}
.rxs-bar{background:#e2e8f0;border-radius:4px;height:7px;min-width:60px;position:relative;overflow:hidden}
.rxs-bar i{position:absolute;left:0;top:0;bottom:0;background:linear-gradient(90deg,#60a5fa,#1d4ed8)}
.rxs-chartbox{max-height:<?= $boxH ?>px;overflow-y:auto;overflow-x:hidden;border:1px solid #f1f5f9;border-radius:8px;padding:2px 4px}
<?php /* 样本明细表的折叠控件。用原生 <details>/<summary> 而不是 onclick 的 <a>：
   前者不依赖页面里的 showhidediv()（本面板被 gene_detail.php 以外的页面复用时
   那个函数并不存在），键盘可直接展开，而且自带开/合的状态提示。 */ ?>
details.rxs-details{margin:8px 0 0;border:1px solid #e2e8f0;border-radius:8px;background:#fff}
details.rxs-details > summary{font-size:15px;color:#1d4ed8;cursor:pointer;padding:7px 11px;
  list-style:none;font-weight:600}
details.rxs-details > summary::-webkit-details-marker{display:none}
details.rxs-details > summary:before{content:"\25B8";display:inline-block;width:12px;
  color:#93c5fd;font-weight:400}
details.rxs-details[open] > summary:before{content:"\25BE"}
details.rxs-details > summary:hover{background:#f8fafc;border-radius:8px}
details.rxs-details > summary:focus-visible{outline:2px solid #93c5fd;outline-offset:-2px}
.rxs-detailbody{padding:0 11px 11px}
.rxs-filter{font-size:15px;padding:5px 9px;border:1px solid #e2e8f0;border-radius:7px;width:240px;margin:0 0 8px}
.rxs-src{font-size:12px;color:#64748b;margin-top:12px;line-height:1.6}
.rxs-src code{background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:12px}
.rxs-dot{display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:6px;vertical-align:middle}
</style>

<h3>Expression pattern (RNA-seq)</h3>
<p class="rxs-sub">
  Transcript abundance of <b><?= cnido_rxs_h($gene) ?></b> across
  <b><?= $n ?></b> RNA-seq sample<?= $n === 1 ? '' : 's' ?>
  of <b><i><?= cnido_rxs_h($latin) ?></i></b>.
  <?php if ($row === null): ?>
    This gene has no row in the species' RNA-seq expression matrix, so every value below
    is shown as zero &mdash; the matrix simply does not cover this transcript.
  <?php endif; ?>
  Values are TPM (transcripts per million) from the StringTie quantification; one bar is one
  sample, grouped and coloured by condition, sorted by expression within each group.
</p>

<div class="rxs-stats">
  <div class="rxs-stat"><b><?= $n ?></b><span>Samples</span></div>
  <div class="rxs-stat"><b><?= $detected ?></b><span>TPM &gt; 0</span></div>
  <div class="rxs-stat"><b><?= count($groups) ?></b><span>Conditions</span></div>
  <div class="rxs-stat"><b><?= number_format($tpmMax, 1) ?></b><span>Max TPM</span></div>
  <div class="rxs-stat"><b><?= number_format($tpmMean, 1) ?></b><span>Mean TPM</span></div>
</div>

<h4 style="margin:14px 0 6px;font-size:15px;color:#334155">By condition</h4>
<?php /* 有的数据集每个样本就是一个独立实验臂（ACOER 的 30 个 T_ 处理码、ATENU 的
       BC_2/SC_2 之类），这时"按条件汇总"退化成逐样本罗列 —— 表格不算错，但先说
       一句，免得看着像分组没生效。 */ ?>
<?php if ($n > 1 && count($groups) === $n): ?>
<p style="font-size:16px;color:#64748b;margin:0 0 8px;line-height:1.6">
  Every sample in this dataset carries its own condition &mdash; the source metadata gives each run
  a distinct treatment label &mdash; so this table is effectively a sample list. The per-sample
  chart below is the informative view here.
</p>
<?php endif; ?>
<table class="gridtable rxs-tbl" style="margin-bottom:16px">
  <tr>
    <th>Condition</th><th class="num">Samples</th><th class="num">TPM &gt; 0</th>
    <th class="num">Mean TPM</th><th class="num">Max TPM</th><th style="width:34%">Mean, relative to max</th>
  </tr>
  <?php $gmax = 0.0; foreach ($groups as $g => $v) { $m = $v['sum'] / max(1, $v['n']); if ($m > $gmax) { $gmax = $m; } }
        foreach ($groups as $g => $v):
          $m = $v['n'] ? $v['sum'] / $v['n'] : 0.0;
          $w = $gmax > 0 ? min(100, 100 * $m / $gmax) : 0; ?>
  <tr>
    <td><span class="rxs-dot" style="background:<?= cnido_rxs_h($gcolor[$g]) ?>"></span><?= cnido_rxs_h($v['label']) ?></td>
    <td class="num"><?= $v['n'] ?></td>
    <td class="num"><?= $v['pos'] ?></td>
    <td class="num"><?= number_format($m, 2) ?></td>
    <td class="num"><?= number_format($v['max'], 2) ?></td>
    <td><div class="rxs-bar"><i style="width:<?= number_format($w, 2) ?>%"></i></div></td>
  </tr>
  <?php endforeach; ?>
</table>

<h4 style="margin:0 0 6px;font-size:15px;color:#334155">
  Per sample
  <span style="font-weight:400;color:#64748b;font-size:12px">
    &middot; hover a bar for the full sample record
  </span>
</h4>

<?php
/* 样本明细表原来在图表**下方**，用一个 onclick 的 <a> 切换 display。切换本身是
   生效的，但图表容器高 560px（.rxs-chartbox 封顶），表格展开后的位置始终落在
   视口之外 —— 用户点一下屏幕没有任何变化，只能报「不起作用」。
   改成两处：① 换成原生 <details>/<summary>，不再依赖页面级的 showhidediv() 与
   内联 onclick（本面板被别的页面复用时那个函数并不存在），键盘也能展开；
   ② 整块挪到图表**之前**。折叠时视线路径与原来一致（标题 → 图），展开时表格
   就出现在刚点过的控件正下方，不会跑到屏幕外。
   summary 里带样本数：这是读者决定要不要展开的依据。 */
?>
<details class="rxs-details">
  <summary>Show the sample table (<?= count($samples) ?> samples)</summary>
  <div class="rxs-detailbody">
  <input type="text" class="rxs-filter" id="rxs-filter" placeholder="filter samples (run, tissue, treatment...)">
  <div style="max-height:420px;overflow:auto">
  <table class="gridtable rxs-tbl" id="rxs-sample-table">
    <tr>
      <th>SRA run</th><th>Condition</th>
      <?php /* Tissue / Developmental stage / Treatment 是样本表里的自由文本（最长 94 / 78 / 171 字），
               居中难看，显式左对齐；Study 是 NCBI 号，短而齐，保持默认居中。 */ ?>
      <th class="tal">Tissue</th><th class="tal">Developmental stage</th>
      <th class="tal">Treatment</th><th>Study</th><th class="num">TPM</th>
    </tr>
    <?php foreach ($samples as $s):
            $g = $s['gkey']; ?>
    <tr>
      <td><a href="https://www.ncbi.nlm.nih.gov/sra/?term=<?= urlencode($s['run']) ?>" target="_blank" rel="noopener"><?= cnido_rxs_h($s['run']) ?></a></td>
      <td><span class="rxs-dot" style="background:<?= cnido_rxs_h($gcolor[$g]) ?>"></span><?= cnido_rxs_h($groups[$g]['label']) ?></td>
      <td class="tal"><?= $s['tissue'] !== '' ? cnido_rxs_h($s['tissue']) : '<span class="gd-na">not recorded</span>' ?></td>
      <td class="tal"><?= $s['stage'] !== '' ? cnido_rxs_h($s['stage']) : '<span class="gd-na">not recorded</span>' ?></td>
      <td class="tal"><?= $s['treat'] !== '' ? cnido_rxs_h($s['treat']) : '<span class="gd-na">not recorded</span>' ?></td>
      <td><?= $s['study'] !== '' ? cnido_rxs_h($s['study']) : ($s['project'] !== '' ? cnido_rxs_h($s['project']) : '<span class="gd-na">-</span>') ?></td>
      <td class="num"><?= number_format($s['tpm'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  </div>
</details>

<div class="rxs-chartbox">
  <div id="rxs-chart" style="width:100%;height:<?= $chartH ?>px"></div>
</div>

<p class="rxs-src">
  Source: CnidoSite RNA-seq expression matrices (<code><?= cnido_rxs_h($abbr) ?>_TPM</code>,
  StringTie quantification over <?= $n ?> run<?= $n === 1 ? '' : 's' ?>), joined to SRA sample
  metadata. Samples whose tissue/treatment is not recorded in the source metadata are grouped
  by the descriptor carried in the expression matrix itself.
</p>

<?php /* 到转录组模块的出口。本面板把每个样本的 TPM 直接印在页面上（用户要的
      「情况就放在 gene detail」这一半已经做到了），但读完数值仍要有地方去：
      Dynamic Expression View 能按自己的基因集重画，Transcriptome 页是物种的
      转录组全景。六个面板里只有这一张原来没有出口按钮。 */
if (function_exists('cnido_gp_openbtn')): ?>
<div class="cn-openrow">
  <?= cnido_gp_openbtn('/cytoscape/network_expression.php?species=' . urlencode($abbr),
        'Dynamic expression view',
        'sec',
        'Pick this and other genes and redraw the expression profile in the network module.') ?>
  <?= cnido_gp_openbtn('/trans_assembly_species.php?species=' . urlencode($abbr),
        'Transcriptome of ' . cnido_rxs_h($abbr), 'sec') ?>
</div>
<?php endif; ?>
</div>

<script type="text/javascript">
/*<![CDATA[*/
var CNIDO_RXS = <?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
(function () {
  if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.highcharts) { return; }
  var D = CNIDO_RXS;
  var cats = [], data = [], colors = [];
  for (var i = 0; i < D.rows.length; i++) {
    cats.push(D.rows[i].run);
    data.push({ y: D.rows[i].tpm, color: D.rows[i].color });
    colors.push(D.rows[i].color);
  }
  jQuery(function () {
    jQuery('#rxs-chart').highcharts({
      chart: { type: 'bar', backgroundColor: '#ffffff', spacingBottom: 30 },
      title: { text: null },
      xAxis: {
        categories: cats,
        title: { text: null },
        labels: { style: { fontSize: '10px', color: '#475569' } },
        lineWidth: 0, tickWidth: 0
      },
      yAxis: {
        min: 0,
        title: { text: 'Gene expression value (TPM)', style: { fontSize: '11px', color: '#64748b' } },
        gridLineColor: '#f1f5f9'
      },
      legend: { enabled: false },
      credits: { enabled: false },
      exporting: { enabled: false },
      tooltip: {
        useHTML: true,
        formatter: function () {
          var r = D.rows[this.point.index];
          var h = '<b>' + r.run + '</b><br/>';
          if (r.info)  { h += r.info + '<br/>'; }
          if (r.study) { h += 'Study: ' + r.study + '<br/>'; }
          h += '<b>' + Highcharts.numberFormat(r.tpm, 2) + ' TPM</b>';
          return h;
        }
      },
      plotOptions: { bar: { borderWidth: 0, groupPadding: 0.02, pointPadding: 0.06 } },
      series: [{ name: D.gene, data: data }]
    });

    <?php /* 样本明细表的即时筛选：沿用站点其它表格页的 keyup 过滤写法 */ ?>
    var f = document.getElementById('rxs-filter');
    if (f) {
      f.onkeyup = function () {
        var v = this.value.toLowerCase();
        var rows = document.getElementById('rxs-sample-table').getElementsByTagName('tr');
        for (var i = 1; i < rows.length; i++) {
          rows[i].style.display = rows[i].innerHTML.toLowerCase().indexOf(v) > -1 ? '' : 'none';
        }
      };
    }
  });
})();
/*]]>*/
</script>
<?php
}
