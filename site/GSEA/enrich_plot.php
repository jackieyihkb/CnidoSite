<?php
/* ============================================================================
   enrich_plot.php — 富集结果图（可直接用于 SCI 投稿）

   由 GSEAresult.php 在「结果汇总卡片」与「结果表格」之间 include。
   单独访问本文件没有意义：它依赖 GSEAresult.php 已经准备好的变量，
   因此下面先做 isset 守卫，缺变量直接 return（页面其余部分不受影响）。

   依赖变量：$qnum（已校验为纯数字）、$hash（显著集名 => .detail 原始行）、
             $enrich_count、$lineNum（去冗余后的查询基因数）。

   设计要点（配色是实测选出来的，不是凭感觉挑的）：
     * 颜色只承担一个任务：FDR（连续量级）-> 单色蓝顺序色阶。
       分类归属由 y 轴标签前缀（BP / CC / ...）与分组底纹承担，
       所以没有一条信息只靠颜色传达，色盲读者同样可读。
     * 顺序色阶下限取参考调色板的 step 250（#86b6ef，白底 2.11:1），
       而非 step 100（1.32:1）—— 最浅的圆点也要看得见；
       圆点大小同时冗余编码重叠基因数。
     * 用 7 种色相区分 7 个分类是**不成立**的。实测（validate_palette.js，
       surface #ffffff，--pairs all）：参考调色板、Okabe-Ito、Dark2、Set2
       的任意 7 色子集都无法通过 —— 最差对色盲 ΔE 仅 3.2~11.0，
       Okabe-Ito 的 7 色还同时踩破明度带（#000000 L=0、#F0E442 L=0.902）
       与色度下限，#e87ba4↔#eb6834 的常色觉 ΔE 只有 12.9（低于 15 的硬底线）。
       因此「按分类着色」只在选中分类 <= 3 时开放：前 3 个色位
       （#2a78d6 / #eb6834 / #1baf7a）实测 all-pairs 通过，最差色盲 ΔE 9.2、
       常色觉 24.0。超过 3 个自动退回 FDR 着色，并在界面上说明原因。
     * 阈值固定 0.05：GSEAresult.php 第 22 行就是按 $list[5] < 0.05 筛 $hash 的，
       图必须与下方表格逐行一致，所以这里沿用 0.05，并在图注写明真实阈值
       （不跟随 $cutoff —— 那个变量只用于显示，与表格实际内容不符）。
     * 导出只做 SVG / PNG，全部在浏览器内生成。加载的 exporting.js 里虽然
       含有 export.highcharts.com 的 POST 路径（exportChart），但它只能由
       自带菜单触发，而导出按钮已关闭（exporting.enabled = false），
       所以页面不会向第三方发出任何请求。
   ============================================================================ */

if (!isset($qnum, $hash, $lineNum, $enrich_count) || $enrich_count === 0 || !is_array($hash) || !$hash) {
    return;
}

$epDir = __DIR__;
$epTmp = $epDir . '/../tmp';
/* 阈值跟随调用方（GSEAresult.php 从 .conf 读出的那个），别写死 0.05 ——
   否则 cutoff=0.01 的作业，表格按 0.01 筛、图却按 0.05 画，图注（下方 CUT）
   还会印一个和表格不符的数。调用方没给就退回表单默认值。 */
$epCut = (isset($cutoff) && is_numeric($cutoff)) ? (float)$cutoff : 0.05;
$epQn  = (int)$lineNum;                 /* 查询基因数，作 GeneRatio 的分母 */

/* ---- 这一 run 实际用的多重检验校正方法 ---------------------------------
   图注原来写死 "Fill is the Benjamini–Hochberg adjusted p-value"，但表单默认
   是 Yekutieli(BY)，选 bonferroni/holm/none 也照样印 BH —— 同一张图对不同
   的 run 说着同一句错话。方法就记在 tmp/<job>.conf 的 mt 行（compute.php
   渲染参数表时读的是同一处），这里照读；读不到就退回不点名方法的泛称。 */
$epMtNames = array(
    'BH'         => 'Benjamini&ndash;Hochberg',
    'BY'         => 'Benjamini&ndash;Yekutieli',
    'bonferroni' => 'Bonferroni',
    'hochberg'   => 'Hochberg',
    'hommel'     => 'Hommel',
    'holm'       => 'Holm',
);
$epMt = '';
$epConfF = $epTmp . '/' . (int)$qnum . '.conf';
if (is_file($epConfF) && ($epConfS = @file_get_contents($epConfF)) !== false) {
    foreach (explode("\n", $epConfS) as $epConfL) {
        $epConfP = explode("\t", trim($epConfL));
        if (isset($epConfP[0], $epConfP[1]) && trim($epConfP[0]) === 'mt') {
            $epMt = trim($epConfP[1]);
            break;
        }
    }
}
if ($epMt === 'none') {
    $epFillTxt = 'Fill is the raw p-value &mdash; no multiple-testing correction was applied &mdash; darker is more significant. ';
} elseif (isset($epMtNames[$epMt])) {
    $epFillTxt = 'Fill is the ' . $epMtNames[$epMt] . ' adjusted p-value (FDR) &mdash; darker is more significant. ';
} else {
    $epFillTxt = 'Fill is the multiple-testing-adjusted p-value (FDR) &mdash; darker is more significant. ';
}

/* ---- 分类全名（图注里用）---------------------------------------------- */
$epCatMeta = array(
    'BP'     => array('BP',     'GO biological process'),
    'CC'     => array('CC',     'GO cellular component'),
    'MF'     => array('MF',     'GO molecular function'),
    'UP'     => array('UP',     'ubiquitin & UBL families'),
    'TF'     => array('TF',     'transcription-factor domains'),
    'KEGG'   => array('KEGG',   'KEGG pathways / orthologs'),
    'DOMAIN' => array('DOMAIN', 'Pfam protein domains'),
);

/* ---- 取显著集本身（$hash 的值是 .detail 的原始行）---------------------- */
$epRows = array();
foreach ($hash as $epLine) {
    $epF = explode("\t", trim((string)$epLine));
    if (count($epF) < 6) { continue; }
    $epFdr = (float)$epF[5];
    if ($epFdr >= $epCut) { continue; }             /* 同上：与表格同源 */
    $epRows[$epF[0]] = array(
        'name' => $epF[0],
        'size' => (int)$epF[1],
        'desc' => $epF[2],
        'k'    => (int)$epF[3],
        'p'    => (float)$epF[4],
        'fdr'  => $epFdr,
    );
}
if (!$epRows) { return; }

/* ---- 类别归属：扫 database/<SPECIES>_<CAT> -----------------------------
   每个文件一行一个基因集，格式 <setName>\t<desc>\t<locus,...>。
   7 个文件合计约 4 MB / 1.5 万行，整份读进来太浪费，所以流式读取、
   只留下命中的那几十个名字（$epWant 最多几十个键）。
   类别名从文件名后缀解析并做白名单校验，避免拼出 ../ 之类的路径。   */
$epCatOf = array();
$epOrder = array();
$epCatFile = @file($epTmp . '/' . $qnum . '.category');
if ($epCatFile) {
    foreach ($epCatFile as $epLine) {
        $epTok = trim($epLine);
        if ($epTok === '' || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $epTok)) { continue; }
        if (!preg_match('/_(BP|CC|MF|UP|TF|KEGG|DOMAIN)$/', $epTok, $epM)) { continue; }
        $epCode = $epM[1];
        $epPath = $epDir . '/database/' . $epTok;
        if (!is_file($epPath) || !is_readable($epPath)) { continue; }
        if (!in_array($epCode, $epOrder, true)) { $epOrder[] = $epCode; }

        $epFh = @fopen($epPath, 'r');
        if (!$epFh) { continue; }
        while (($epRaw = fgets($epFh)) !== false) {
            $epT = strpos($epRaw, "\t");
            if ($epT === false) { continue; }
            $epNm = substr($epRaw, 0, $epT);
            if (!isset($epRows[$epNm]) || isset($epCatOf[$epNm])) { continue; }
            $epCatOf[$epNm] = $epCode;
            $epD = substr($epRaw, $epT + 1);
            $epD2 = strpos($epD, "\t");
            if ($epD2 !== false) { $epD = substr($epD, 0, $epD2); }
            $epRows[$epNm]['desc'] = trim($epD);    /* 文件里的描述更规范 */
        }
        fclose($epFh);
    }
}

/* ---- 组装绘图数据 ------------------------------------------------------
   标签取描述字段并去掉各家前缀，比 SET_NAME 的大写下划线好读得多：
     GO:0006412   translation,  GOslim:biological_process -> translation
     ko02000, ATP-binding cassette, ...                   -> ATP-binding cassette, ...
     PF00005(Domain)   ABC_tran, ABC transporter          -> ABC_tran, ABC transporter
     Ubiquitin Family,  ULD/UBL: ATG8                     -> 原样（已经可读） */
$epData = array();
$epCounts = array();
foreach ($epRows as $epNm => $epR) {
    $epCode = isset($epCatOf[$epNm]) ? $epCatOf[$epNm] : '';
    if ($epCode === '') { continue; }               /* 归属不明的不猜，直接不画 */
    $epD = $epR['desc'];
    if ($epCode === 'BP' || $epCode === 'CC' || $epCode === 'MF') {
        $epD = preg_replace('/^GO:\d+\s*/', '', $epD);
        $epD = preg_replace('/,\s*GOslim:.*$/', '', $epD);
    } elseif ($epCode === 'KEGG') {
        $epD = preg_replace('/^ko\d+\s*[,\s]\s*/', '', $epD);
    } elseif ($epCode === 'DOMAIN') {
        $epD = preg_replace('/^PF\d+\([^)]*\)\s*/', '', $epD);
    }
    $epD = trim(preg_replace('/[\s,]+$/', '', trim($epD)));
    if ($epD === '') {
        $epD = strtolower(str_replace('_', ' ', $epNm));   /* 描述缺失时退回集名 */
    }
    $epD = preg_replace('/\s+/', ' ', $epD);
    /* 轴标签长度上限 48 字符。这不是审美问题：Highcharts 的 Tick.handleOverflow()
       在标签超出可用宽度时会给它设一个 style.width，接着 buildText() 就按这个
       宽度折行；可用宽度约为图宽的 32%，所以在常见宽度下 48+9 字符刚好一行，
       窄屏最多折成两行，而行高按 30px 预留了整整两行（2×14px）的位置，
       因此折行也不会串行。放宽这个值会真的开始重叠。 */
    if (function_exists('mb_substr') && mb_strlen($epD, 'UTF-8') > 48) {
        $epD = mb_substr($epD, 0, 47, 'UTF-8') . '…';
    }
    $epRatio = ($epQn > 0) ? ($epR['k'] / $epQn) : 0.0;
    $epNl    = ($epR['fdr'] > 0) ? (-log($epR['fdr']) / M_LN10) : 300.0;

    $epData[] = array(
        'n' => $epNm,
        'l' => $epD,
        'c' => $epCode,
        'g' => $epR['size'],
        'k' => $epR['k'],
        'r' => round($epRatio, 5),
        'p' => $epR['p'],
        'f' => $epR['fdr'],
        'z' => round($epNl, 3),
    );
    if (!isset($epCounts[$epCode])) { $epCounts[$epCode] = 0; }
    $epCounts[$epCode]++;
}
if (!$epData) { return; }

/* 分类顺序按 .category 出现次序；没有显著集的分类也列出来（禁用状态），
   让用户一眼看到「该分类本来就没有显著结果」，而不是以为控件坏了。 */
$epCatList = array();
foreach ($epOrder as $epCode) {
    if (!isset($epCatMeta[$epCode])) { continue; }
    $epCatList[] = array(
        'code'  => $epCode,
        'name'  => $epCatMeta[$epCode][0],
        'desc'  => $epCatMeta[$epCode][1],
        'count' => isset($epCounts[$epCode]) ? $epCounts[$epCode] : 0,
    );
}
if (!$epCatList) { return; }

$epFlags   = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$epJson    = json_encode($epData, $epFlags);
$epCatJson = json_encode($epCatList, $epFlags);
?>
<style>
<?php /* 富集图专用样式，全部带 ep- 前缀，避免影响页面其它部分 */ ?>
.ep-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:22px 24px 16px;margin:26px 0;box-shadow:0 1px 3px rgba(15,23,42,.06);}
.ep-card h3{margin:0 0 4px;font-size:18px;color:#1e293b;font-weight:600;}
.ep-sub{margin:0 0 16px;font-size:13px;color:#64748b;line-height:1.6;}
.ep-controls{display:flex;flex-wrap:wrap;gap:18px;align-items:flex-start;padding:14px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;margin-bottom:14px;}
.ep-ctl{display:flex;flex-direction:column;gap:6px;}
.ep-ctl>span.ep-lbl{font-size:12px;font-weight:700;color:#64748b;}
.ep-checks{display:flex;flex-wrap:wrap;gap:6px 14px;max-width:560px;}
.ep-checks label{display:inline-flex;align-items:center;gap:5px;font-size:13px;color:#334155;cursor:pointer;white-space:nowrap;}
.ep-checks label.ep-off{color:#64748b;cursor:not-allowed;}
.ep-checks .ep-cnt{color:#64748b;font-size:12px;}
.ep-presets{display:flex;gap:6px;flex-wrap:wrap;margin-top:2px;}
.ep-presets button,.ep-exp button{font:inherit;font-size:12px;padding:4px 10px;border:1px solid #cbd5e1;background:#fff;border-radius:6px;color:#334155;cursor:pointer;}
.ep-presets button:hover,.ep-exp button:hover{border-color:#336699;color:#336699;}
.ep-presets button.ep-on{background:#336699;border-color:#336699;color:#fff;font-weight:600;}
.ep-presets button.ep-on:hover{color:#fff;}
.ep-exp{display:flex;gap:6px;}
.ep-exp button[disabled]{opacity:.45;cursor:not-allowed;}
.ep-ctl select{font:inherit;font-size:13px;padding:4px 8px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:#334155;}
.ep-note{font-size:16px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:7px;padding:7px 11px;margin-bottom:12px;display:none;line-height:1.55;}
.ep-chart{width:100%;background:#fff;}
.ep-empty{font-size:13px;color:#64748b;padding:34px 0;text-align:center;}
.ep-cap{font-size:12.5px;color:#64748b;line-height:1.65;margin-top:10px;border-top:1px solid #e2e8f0;padding-top:10px;}
.ep-cap b{color:#334155;font-weight:600;}
@media (max-width:900px){.ep-checks{max-width:100%;}.ep-controls{gap:14px;}}
</style>

<div class="ep-card">
    <h3>Enrichment Overview</h3>
    <p class="ep-sub">
        One figure per panel, sized for a manuscript. Choose a plot type, tick the categories you want,
        then export as vector <b>SVG</b> (editable in Illustrator / Inkscape) or high-resolution <b>PNG</b>.
        Every number behind every point is in the table below.
    </p>

    <div class="ep-controls">
        <div class="ep-ctl">
            <span class="ep-lbl">Plot type</span>
            <div class="ep-presets">
                <button type="button" id="ep-t-dot" class="ep-on">Dot plot</button>
                <button type="button" id="ep-t-bar">Bar plot</button>
            </div>
        </div>

        <div class="ep-ctl">
            <span class="ep-lbl">Categories</span>
            <div class="ep-checks" id="ep-cats"></div>
            <div class="ep-presets">
                <button type="button" data-ep-preset="go">GO only</button>
                <button type="button" data-ep-preset="kegg">KEGG only</button>
                <button type="button" data-ep-preset="all">All</button>
                <button type="button" data-ep-preset="none">Clear</button>
            </div>
        </div>

        <div class="ep-ctl">
            <span class="ep-lbl">Terms per category</span>
            <select id="ep-top">
                <option value="10">Top 10</option>
                <option value="20" selected>Top 20</option>
                <option value="30">Top 30</option>
                <option value="50">Top 50</option>
                <option value="0">All</option>
            </select>
        </div>

        <div class="ep-ctl">
            <span class="ep-lbl">Colour by</span>
            <select id="ep-by">
                <option value="fdr" selected>Adjusted p-value (FDR)</option>
                <option value="cat">Category</option>
            </select>
        </div>

        <div class="ep-ctl">
            <span class="ep-lbl">Export</span>
            <div class="ep-exp">
                <button type="button" id="ep-svg">SVG</button>
                <button type="button" id="ep-png">PNG (3&times;)</button>
            </div>
        </div>
    </div>

    <div class="ep-note" id="ep-note"></div>
    <div class="ep-chart" id="ep-chart"></div>
    <div class="ep-cap" id="ep-cap"></div>
</div>

<script type="text/javascript" src="/js/highcharts.js"></script>
<script type="text/javascript" src="/js/exporting.js"></script>
<script type="text/javascript">
/* global Highcharts */
(function () {
    'use strict';

    var DATA = <?= $epJson ?>;
    var CATS = <?= $epCatJson ?>;
    var QN   = <?= $epQn ?>;
    var CUT  = <?= json_encode((string)$epCut) ?>;

    <?php /* 顺序色阶：单色蓝，step 250 -> 700（取自项目参考调色板）。
       起点停在 step 250 而非 100，是因为 step 100（#cde2fb）白底仅 1.32:1，
       圆点会淡到看不见；step 250 是 2.11:1，配合可见标签与下方表格
       满足 relief 规则。 */ ?>
    var RAMP = ['#86b6ef', '#6da7ec', '#5598e7', '#3987e5', '#2a78d6',
                '#256abf', '#1c5cab', '#184f95', '#104281', '#0d366b'];
    <?php /* 分类着色只用前 3 个色位：实测只有它们能通过 all-pairs 色盲分离 */ ?>
    var CATCOL = ['#2a78d6', '#eb6834', '#1baf7a'];

    <?php /* MUTED 是坐标轴标签、tooltip 与注释文字（10.5–11px）。原来的 #898781 压在
       SURF=#ffffff 上只有 3.37，读不清；#6f6e69 是 4.85，仍是暖灰。
       同一套 INK/INK2 在 viewer/cellatlas.css 里也有一份，那边一并改了。 */ ?>
    var INK = '#0b0b0b', INK2 = '#52514e', MUTED = '#6f6e69';
    var GRID = '#e1e0d9', AXIS = '#c3c2b7', SURF = '#ffffff';
    var R_MIN = 5, R_MAX = 18;

    var elChart = document.getElementById('ep-chart');
    var elCap   = document.getElementById('ep-cap');
    var elNote  = document.getElementById('ep-note');
    var elSvg   = document.getElementById('ep-svg');
    var elPng   = document.getElementById('ep-png');

    var chart = null;
    var state = { plot: 'dot', top: 20, by: 'fdr', cats: {} };
    var i;
    for (i = 0; i < CATS.length; i++) { state.cats[CATS[i].code] = (CATS[i].count > 0); }

    <?php /* ---------- 小工具 ---------- */ ?>
    function mix(a, b, f) {
        var pa = parseInt(a.slice(1), 16), pb = parseInt(b.slice(1), 16);
        var r  = Math.round((pa >> 16 & 255) + ((pb >> 16 & 255) - (pa >> 16 & 255)) * f);
        var g  = Math.round((pa >> 8 & 255) + ((pb >> 8 & 255) - (pa >> 8 & 255)) * f);
        var bl = Math.round((pa & 255) + ((pb & 255) - (pa & 255)) * f);
        return 'rgb(' + r + ',' + g + ',' + bl + ')';
    }
    function rampColor(t) {
        if (!(t > 0)) { t = 0; }
        if (t > 1) { t = 1; }
        var p = t * (RAMP.length - 1), k = Math.floor(p);
        if (k >= RAMP.length - 1) { return RAMP[RAMP.length - 1]; }
        return mix(RAMP[k], RAMP[k + 1], p - k);
    }
    function nl10(x) { return Math.log(x) / Math.LN10; }

    <?php /* FDR 刻度：只保留落在当前色阶范围内的，最多 4 个。
       单位必须和 lo / hi 一致 —— lo、hi 是 -log10(FDR)（PHP 侧 $epNl，
       DATA 里的 z），所以候选值要先取负对数再比较。此前这里拿
       nl10(cand[j])（负值，0.05 -> -1.301）去和正的 lo/hi 比，条件恒不成立，
       色阶条于是画不出任何刻度数字，读者看不到值域。2026-09-22 用真实任务
       核对：lo=1.404 / hi=5.010，旧逻辑返回空数组。 */ ?>
    function fdrTicks(lo, hi) {
        var cand = [0.05, 0.02, 0.01, 0.005, 0.001, 1e-4, 1e-5, 1e-6, 1e-7,
                    1e-8, 1e-9, 1e-10, 1e-12];
        var inr = [], out = [], pick = [], seen = {}, j, n;
        for (j = 0; j < cand.length; j++) {
            var v = -nl10(cand[j]);
            if (v >= lo - 1e-9 && v <= hi + 1e-9) { inr.push(cand[j]); }
        }
        <?php /* 落进范围的整数值不足两个（整个任务都极显著，或值域本身很窄，中间
           正好没有 5/1/2 这类整数）：退回色阶条两个端点的**真实值**。宁可标
           1.3e-15 这样的实际数字，也不要留一片空白 —— 这条色阶条存在的意义
           就是让读者知道颜色对应什么 FDR 区间。 */ ?>
        if (inr.length < 2) {
            var ends = [Math.pow(10, -hi), Math.pow(10, -lo)];
            for (j = 0; j < inr.length; j++) { ends.splice(1, 0, inr[j]); }
            for (j = 0; j < ends.length; j++) {
                var k = fmtFdr(ends[j]);
                if (!seen[k]) { seen[k] = 1; out.push(ends[j]); }
            }
            return out;
        }
        if (inr.length <= 4) { return inr; }
        <?php /* 最多 4 个，但两端必须各占一个：这条色阶条的用处就是告诉读者值域，
           只取最小的几个会把最显著的一段留成空白，而那一段恰恰是读者要问的。 */ ?>
        n = inr.length;
        pick = [0, Math.round((n - 1) / 3), Math.round(2 * (n - 1) / 3), n - 1];
        for (j = 0; j < pick.length; j++) {
            if (!seen[pick[j]]) { seen[pick[j]] = 1; out.push(inr[pick[j]]); }
        }
        return out;
    }
    function fmtFdr(v) {
        if (v >= 0.01) { return String(+(+v).toFixed(3)); }
        var e = Math.floor(nl10(v)), m = v / Math.pow(10, e);
        return (+m.toFixed(1)) + 'e' + e;
    }
    function fmtP(v) { return (v < 1e-4) ? v.toExponential(1) : String(+(+v).toFixed(4)); }

    function niceTicks(max, want) {
        if (!(max > 0)) { return [0]; }
        var raw = max / want, mag = Math.pow(10, Math.floor(nl10(raw)));
        var nrm = raw / mag, step = (nrm <= 1 ? 1 : nrm <= 2 ? 2 : nrm <= 5 ? 5 : 10) * mag;
        var t = [], v;
        for (v = 0; v <= max + 1e-9; v += step) { t.push(+v.toFixed(10)); }
        return t;
    }

    <?php /* ---------- 选中 -> 行 ---------- */ ?>
    function buildRows() {
        var groups = [], rows = [], j, k;
        for (j = 0; j < CATS.length; j++) {
            var c = CATS[j];
            if (!state.cats[c.code] || c.count <= 0) { continue; }
            var g = [];
            for (k = 0; k < DATA.length; k++) { if (DATA[k].c === c.code) { g.push(DATA[k]); } }
            g.sort(function (a, b) { return a.f - b.f; });      /* 最显著在前 */
            if (state.top > 0) { g = g.slice(0, state.top); }
            if (!g.length) { continue; }
            groups.push({ code: c.code, name: c.name, desc: c.desc, n: g.length });
            rows = rows.concat(g);
        }
        return { rows: rows, groups: groups };
    }

    <?php /* ---------- 渲染 ---------- */ ?>
    function render() {
        var build = buildRows(), rows = build.rows, j;

        if (chart) { chart.destroy(); chart = null; }
        elNote.style.display = 'none';
        elSvg.disabled = true;
        elPng.disabled = true;

        if (typeof Highcharts === 'undefined') {
            elChart.innerHTML = '<div class="ep-empty">The chart library could not be loaded, ' +
                'so the figure is unavailable. The full results are in the table below.</div>';
            elCap.textContent = '';
            return;
        }
        if (!rows.length) {
            elChart.innerHTML = '<div class="ep-empty">No category selected — tick at least one ' +
                'category above, or press “All”.</div>';
            elCap.textContent = '';
            return;
        }
        elChart.innerHTML = '';

        <?php /* 3 个以上分类没法安全地用色相区分，自动退回 FDR 着色并说明原因 */ ?>
        var byCat = (state.by === 'cat' && build.groups.length <= 3);
        if (state.by === 'cat' && !byCat) {
            elNote.style.display = 'block';
            elNote.innerHTML = 'Category colours are used with up to 3 categories only. Beyond that, ' +
                'no palette keeps every pair of colours distinguishable for colour-blind readers ' +
                '(measured, not assumed), so the figure falls back to the FDR scale. Category identity ' +
                'is still carried by the label prefix and the row bands on the left.';
        }

        <?php /* 色阶范围跟着当前筛选走，这样每次都能用满整条色阶 */ ?>
        var lo = Infinity, hi = -Infinity, kmin = Infinity, kmax = -Infinity, rmax = 0;
        for (j = 0; j < rows.length; j++) {
            if (rows[j].z < lo) { lo = rows[j].z; }
            if (rows[j].z > hi) { hi = rows[j].z; }
            if (rows[j].k < kmin) { kmin = rows[j].k; }
            if (rows[j].k > kmax) { kmax = rows[j].k; }
            if (rows[j].r > rmax) { rmax = rows[j].r; }
        }
        if (!isFinite(lo)) { lo = 0; hi = 1; }
        if (hi - lo < 0.5) { hi = lo + 0.5; }
        if (kmax <= kmin) { kmax = kmin + 1; }

        function catIndex(code) {
            for (var m = 0; m < build.groups.length; m++) {
                if (build.groups[m].code === code) { return m; }
            }
            return 0;
        }
        function colorOf(d) {
            if (byCat) { return CATCOL[catIndex(d.c) % CATCOL.length]; }
            return rampColor((d.z - lo) / (hi - lo));
        }
        <?php /* 面积正比于基因数 -> 半径按 sqrt 缩放（线性的是面积，不是半径） */ ?>
        function radiusOf(k) {
            var t = (kmax > kmin) ? (k - kmin) / (kmax - kmin) : 1;
            return R_MIN + (R_MAX - R_MIN) * Math.sqrt(t);
        }

        <?php /* y 轴标签：分类代号 + 术语。分类用文字而不是颜色来表达，
           所以色盲读者、黑白打印稿都能分清 BP / KEGG / DOMAIN。 */ ?>
        var labels = [], bands = [], cursor = 0;
        for (j = 0; j < build.groups.length; j++) {
            var from = cursor;
            for (var m = 0; m < rows.length; m++) {
                if (rows[m].c === build.groups[j].code) {
                    labels.push(build.groups[j].name + ' · ' + rows[m].l);
                    cursor++;
                }
            }
            bands.push({
                from: from - 0.5, to: cursor - 0.5,
                color: (j % 2 === 0) ? 'rgba(11,11,11,0.035)' : 'rgba(11,11,11,0)'
            });
        }

        var isBar = (state.plot === 'bar');

        <?php /* 行高 30：容得下折成两行的标签（2 x 14px）而不串行，见上面 PHP 里的说明。
           高度下限由右侧图例栏决定，不是随手定的：绘图区之上约 100px（标题 +
           副标题 + spacingTop），之下约 70px（x 轴刻度 + 轴标题 + spacingBottom），
           中间要塞下色阶条（最多 200）+ 42px 间隔 + 大小图例（标题 + 最多三个
           圆，合计约 101px）。类别少的时候行高算出来的高度不够，图例会掉到画布
           外面——GO 单选只有 7 行时就是这样。bar 图不画大小图例，下限可以低些。 */ ?>
        var rowH = 30, railMin = isBar ? 0 : 430;
        var height = Math.min(2600, Math.max(320, railMin, 100 + labels.length * rowH));

        <?php /* 两种画法的数据结构不同：
             scatter —— x 是 GeneRatio，y 是类别序号（类别轴在 y）
             bar     —— x 是类别序号，y 是 GeneRatio（Highcharts 把 bar 反转为
                        「类别轴在 x」，这是 bar 系列的约定，不能和 scatter 互换） */ ?>
        var seriesData = [];
        for (j = 0; j < rows.length; j++) {
            var d = rows[j], col = colorOf(d);
            if (isBar) {
                seriesData.push({ x: j, y: d.r, color: col, name: d.l, d: d });
            } else {
                seriesData.push({
                    x: d.r, y: j, z: d.k, color: col, name: d.l, d: d,
                    marker: { radius: radiusOf(d.k), fillColor: col, lineColor: SURF, lineWidth: 1 }
                });
            }
        }

        var valAxis = {
            min: 0, max: rmax * 1.04, tickPositions: niceTicks(rmax, 4),
            title: {
                text: 'Gene ratio' + (QN > 0 ? '  (overlapping genes / ' + QN + ' query genes)' : ''),
                style: { fontSize: '12px', color: INK2, fontWeight: 'normal' }
            },
            gridLineColor: GRID, gridLineWidth: 1, gridLineDashStyle: 'Solid',
            lineColor: AXIS, tickColor: AXIS,
            labels: { style: { fontSize: '11px', color: MUTED } }
        };
        var catAxis = {
            categories: labels, reversed: true,
            title: { text: null },
            gridLineWidth: 0, lineColor: AXIS, tickColor: AXIS,
            labels: { style: { fontSize: '11.5px', color: INK }, x: -6 },
            plotBands: bands
        };

        <?php /* 图例内容先算好，交给 chart.events.load 去画 —— 见下面 drawLegends 的说明：
           getSVG() 会用一个**新的 chart 实例**重新渲染，只有挂在 options 里的
           load 事件才会在新实例上再跑一遍，图例才进得了导出的 SVG。
           marginRight 必须显式给够：图例是 renderer 直接画的，Highcharts
           算边距时并不知道它占了地方，留 16px 的话图例会被裁到画布外。 */ ?>
        var legendSpec = {
            byCat: byCat,
            lo: lo, hi: hi, ticks: fdrTicks(lo, hi),
            kmin: (isBar || kmax <= kmin) ? null : kmin,
            kmax: kmax,
            radiusOf: radiusOf,
            groups: build.groups,
            colours: CATCOL,
            gap: 18, width: 14
        };

        var cfg = {
            chart: {
                renderTo: 'ep-chart',
                type: isBar ? 'bar' : 'scatter',
                height: height,
                backgroundColor: SURF,
                marginRight: 152,
                spacingTop: 24, spacingBottom: 12, spacingLeft: 10,
                style: { fontFamily: 'system-ui, -apple-system, "Segoe UI", Arial, sans-serif' },
                animation: false,
                events: {
                    load: function () { drawLegends(this, legendSpec); }
                }
            },
            exporting: { enabled: false },      /* 关掉自带按钮：不向第三方 POST */
            title: {
                text: isBar ? 'Enriched gene sets — gene ratio' : 'Enriched gene sets',
                align: 'left', x: 0, margin: 34,
                style: { fontSize: '16px', fontWeight: '600', color: INK }
            },
            subtitle: {
                text: isBar
                    ? 'Bar length is the share of the query genes found in the set'
                    : 'Bubble area is the number of overlapping genes — fill is the adjusted p-value (FDR)',
                align: 'left', x: 0,
                style: { fontSize: '12px', color: INK2 }
            },
            credits: { enabled: false },
            legend: { enabled: false },
            xAxis: isBar ? catAxis : valAxis,
            yAxis: isBar ? valAxis : catAxis,
            tooltip: {
                useHTML: true, backgroundColor: 'rgba(255,255,255,0.97)',
                borderColor: AXIS, borderRadius: 7, shadow: false,
                style: { fontSize: '12px', color: INK },
                formatter: function () {
                    var d = this.point.d;
                    return '<b>' + d.l + '</b><br/>' +
                        '<span style="color:' + MUTED + '">' + d.n + ' (' + d.c + ')</span><br/>' +
                        'Overlap: <b>' + d.k + '</b> of ' + d.g + ' genes in the set<br/>' +
                        'Gene ratio: <b>' + (QN > 0 ? d.k + '/' + QN : 'n/a') + '</b><br/>' +
                        'P-value: <b>' + fmtP(d.p) + '</b><br/>' +
                        'FDR: <b>' + fmtP(d.f) + '</b>';
                }
            },
            plotOptions: {
                scatter: {
                    stickyTracking: false, turboThreshold: 0, lineWidth: 0,
                    states: { hover: { halo: { size: 6 } } }
                },
                bar: { borderWidth: 0, pointPadding: 0.14, groupPadding: 0.04, turboThreshold: 0 },
                series: { animation: false, enableMouseTracking: true }
            },
            series: [{
                type: isBar ? 'bar' : 'scatter',
                name: 'Gene sets',
                data: seriesData,
                color: byCat ? CATCOL[0] : rampColor(1)
            }]
        };

        <?php /* 图例不在这里画：改由 cfg.chart.events.load 触发，理由见 legendSpec 处的注释 */ ?>
        chart = new Highcharts.Chart(cfg);

        var catTxt = [];
        for (j = 0; j < build.groups.length; j++) {
            catTxt.push(build.groups[j].name + ' = ' + build.groups[j].desc +
                        ' (' + build.groups[j].n + ')');
        }
        elCap.innerHTML =
            '<b>' + rows.length + '</b> gene sets across <b>' + build.groups.length +
            '</b> categor' + (build.groups.length === 1 ? 'y' : 'ies') + ': ' +
            catTxt.join('; ') + '. ' +
            (state.top > 0 ? 'Top ' + state.top + ' per category, ranked by FDR. '
                           : 'All significant sets shown. ') +
            (byCat ? 'Fill encodes the category. '
                   : <?= json_encode($epFillTxt, $epFlags) ?>) +
            (isBar ? '' : 'Bubble area is the number of overlapping genes. ') +
            'Every set shown has FDR &lt; ' + CUT + ', and the same ' + rows.length +
            ' sets are listed in the table below — that table is also the text alternative to this figure.';

        elSvg.disabled = false;
        elPng.disabled = false;
    }

    <?php /* 图例统一入口。之所以挂在 chart.events.load 而不是在 render() 里直接画：
       Highcharts 的 getSVG() 会拿合并后的 options **新建一个 chart 实例**再序列化，
       在原实例上 renderer.rect() 画的东西根本不在那个新实例里 —— 图例会整个
       从导出的 SVG 中消失（导出文件里连 "FDR" 三个字都没有）。挂进 options 的
       load 事件才会在每个实例上都执行一次，导出文件里也就有了。
       注意这里必须用 this（当前实例），不能用外层闭包里的 chart 变量。 */ ?>
    function drawLegends(ch, spec) {
        var x = ch.plotLeft + ch.plotWidth + spec.gap;
        var top = ch.plotTop;
        <?php /* 图例栏自上而下是「FDR 标题 + 色阶条 + 42px 间隔 + 大小图例（标题 + 最多
           三个圆，整块约 101px）」，必须整条落在 plotHeight 里。上面 height 的
           railMin 已经保证常规情况下放得下，这里再按 plotHeight 压一次色阶条，
           免得字号/字体变化或别的改动让图例又悄悄掉出画布。 */ ?>
        var rail = (spec.kmin !== null && spec.kmax > spec.kmin) ? 143 : 0;
        var bodyH = Math.min(200, Math.max(64, ch.plotHeight - 10 - rail));
        var j;

        if (spec.byCat) {
            <?php /* 按分类着色时给分类图例（否则颜色没有出处） */ ?>
            ch.renderer.text('Category', x - 1, top - 8)
                .css({ fontSize: '11px', fontWeight: '600', color: INK2 }).add();
            var ly = top + 6;
            for (j = 0; j < spec.groups.length; j++) {
                ch.renderer.rect(x, ly, 11, 11)
                    .attr({ fill: spec.colours[j % spec.colours.length], stroke: 'none' }).add();
                ch.renderer.text(spec.groups[j].name, x + 17, ly + 9.5)
                    .css({ fontSize: '11px', color: INK2 }).add();
                ly += 17;
            }
        } else {
            drawColorBar(ch, spec, x, top, bodyH);
        }
        if (spec.kmin !== null && spec.kmax > spec.kmin) {
            drawSizeLegend(ch, spec, x, top + bodyH + 42);
        }
    }

    <?php /* 色阶条：竖条 + 刻度。用离散色块而不是 SVG 渐变，导出与跨浏览器都稳。 */ ?>
    function drawColorBar(ch, spec, x, y, h) {
        var lo = spec.lo, hi = spec.hi, ticks = spec.ticks;
        var w = spec.width, n = 60, j;
        ch.renderer.text('FDR', x - 1, y - 8)
            .css({ fontSize: '11px', fontWeight: '600', color: INK2 }).add();
        for (j = 0; j < n; j++) {
            var t = j / (n - 1);                       /* 0 = 顶部（最显著） */
            ch.renderer.rect(x, y + t * (h - h / n), w, h / n + 0.6)
                .attr({ fill: rampColor(1 - t), stroke: 'none' }).add();
        }
        ch.renderer.rect(x, y, w, h).attr({ fill: 'none', stroke: AXIS, 'stroke-width': 1 }).add();
        for (j = 0; j < ticks.length; j++) {
            <?php /* 与 fdrTicks 同一个单位约定：刻度值也要换成 -log10(FDR)，
               FDR 越小越显著、位置越靠上（pos 从 0 顶部到 1 底部）。 */ ?>
            var pos = 1 - (-nl10(ticks[j]) - lo) / (hi - lo);
            if (pos < -0.02 || pos > 1.02) { continue; }
            var yy = y + pos * h;
            ch.renderer.path(['M', x + w, yy, 'L', x + w + 4, yy])
                .attr({ stroke: AXIS, 'stroke-width': 1 }).add();
            ch.renderer.text(fmtFdr(ticks[j]), x + w + 7, yy + 4)
                .css({ fontSize: '10.5px', color: MUTED }).add();
        }
    }

    <?php /* 大小图例：最小 / 中位 / 最大三个圆，标出对应的重叠基因数。
       同样的 k 只画一次，所以 k 取值集中时圆圈会少于三个。 */ ?>
    function drawSizeLegend(ch, spec, x, y) {
        var ks = [spec.kmin, Math.round((spec.kmin + spec.kmax) / 2), spec.kmax];
        var cy = y + 18, seen = {}, j;
        ch.renderer.text('Overlapping genes', x - 1, y)
            .css({ fontSize: '11px', fontWeight: '600', color: INK2 }).add();
        for (j = 0; j < ks.length; j++) {
            if (seen[ks[j]]) { continue; }
            seen[ks[j]] = 1;
            var r = spec.radiusOf(ks[j]);
            cy += r;
            ch.renderer.circle(x + 18, cy, r)
                .attr({ fill: 'rgba(11,11,11,0.06)', stroke: INK2, 'stroke-width': 1 }).add();
            ch.renderer.text(String(ks[j]), x + 42, cy + 4)
                .css({ fontSize: '11px', color: INK2 }).add();
            cy += r + 5;
        }
    }

    <?php /* ---------- 控件 ---------- */ ?>
    var elCats = document.getElementById('ep-cats');
    if (elCats) {
        var mkBox = function (c) {
            var lab = document.createElement('label');
            lab.title = c.desc;
            if (c.count <= 0) { lab.className = 'ep-off'; }
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = c.code;
            cb.checked = c.count > 0;
            cb.disabled = c.count <= 0;
            lab.appendChild(cb);
            lab.appendChild(document.createTextNode(c.name + ' '));
            var sp = document.createElement('span');
            sp.className = 'ep-cnt';
            sp.textContent = '(' + c.count + ')';
            lab.appendChild(sp);
            elCats.appendChild(lab);
            cb.addEventListener('change', function () {
                state.cats[c.code] = cb.checked;
                render();
            });
        };
        for (i = 0; i < CATS.length; i++) { mkBox(CATS[i]); }
    }

    var presets = document.querySelectorAll('[data-ep-preset]');
    for (i = 0; i < presets.length; i++) {
        presets[i].addEventListener('click', function () {
            var p = this.getAttribute('data-ep-preset');
            var boxes = elCats ? elCats.getElementsByTagName('input') : [];
            for (var j = 0; j < boxes.length; j++) {
                var cb = boxes[j];
                if (cb.disabled) { continue; }
                var on = (p === 'all') ? true
                       : (p === 'none') ? false
                       : (p === 'go') ? (cb.value === 'BP' || cb.value === 'CC' || cb.value === 'MF')
                       : (p === 'kegg') ? (cb.value === 'KEGG')
                       : cb.checked;
                cb.checked = on;
                state.cats[cb.value] = on;
            }
            render();
        });
    }

    var btnDot = document.getElementById('ep-t-dot');
    var btnBar = document.getElementById('ep-t-bar');
    function setPlot(p) {
        state.plot = p;
        btnDot.className = (p === 'dot') ? 'ep-on' : '';
        btnBar.className = (p === 'bar') ? 'ep-on' : '';
        render();
    }
    if (btnDot) { btnDot.addEventListener('click', function () { setPlot('dot'); }); }
    if (btnBar) { btnBar.addEventListener('click', function () { setPlot('bar'); }); }

    var elTop = document.getElementById('ep-top');
    if (elTop) {
        elTop.addEventListener('change', function () {
            state.top = parseInt(this.value, 10) || 0;
            render();
        });
    }
    var elBy = document.getElementById('ep-by');
    if (elBy) {
        elBy.addEventListener('change', function () {
            state.by = this.value;
            render();
        });
    }

    <?php /* ---------- 导出（纯客户端，不发任何网络请求）---------- */ ?>
    function stamp() {
        var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; };
        return 'GSEA_' + <?= json_encode($qnum) ?> + '_' +
            d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '_' +
            p(d.getHours()) + p(d.getMinutes());
    }
    <?php /* 用 data: URL 而不是 Blob + URL.createObjectURL：导出体积只有几十 KB，
       data: 路径更短，也不依赖 createObjectURL（老浏览器没有这个 API，
       会静默失败）。失败时用 alert 说明，而不是点了没反应。 */ ?>
    function saveDataUrl(url, name, what) {
        try {
            var a = document.createElement('a');
            a.href = url;
            a.download = name;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        } catch (e) {
            window.alert('Could not save the ' + what + ' file. Please use a current version of ' +
                         'Chrome, Firefox, Edge or Safari, or take a screenshot instead.');
        }
    }
    <?php /* getSVG(extraOptions) 内部就是 merge(chart.options, extraOptions)，
       所以白底、字体这些投稿要求可以直接覆盖，不必改动画布。
       字体必须在 chart.style 这一层统一覆盖：只改 title / subtitle 的话，
       轴标签等文字仍会带上页面用的 system-ui，而 Illustrator / Inkscape
       解析不了 system-ui，落到哪种字体不可控。投稿统一用 Arial。 */ ?>
    function svgString() {
        var face = 'Arial, Helvetica, sans-serif';
        return chart.getSVG({
            chart: {
                backgroundColor: '#ffffff',
                style: { fontFamily: face }
            },
            title: { style: { fontFamily: face } },
            subtitle: { style: { fontFamily: face } },
            xAxis: { labels: { style: { fontFamily: face } }, title: { style: { fontFamily: face } } },
            yAxis: { labels: { style: { fontFamily: face } }, title: { style: { fontFamily: face } } }
        });
    }
    if (elSvg) {
        elSvg.addEventListener('click', function () {
            if (!chart) { return; }
            saveDataUrl('data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgString()),
                        stamp() + '.svg', 'SVG');
        });
    }
    if (elPng) {
        elPng.addEventListener('click', function () {
            if (!chart) { return; }
            var svg = svgString();
            var w = chart.chartWidth, h = chart.chartHeight, scale = 3;
            var img = new Image();
            img.onload = function () {
                var url = null;
                try {
                    var c = document.createElement('canvas');
                    c.width = Math.round(w * scale);
                    c.height = Math.round(h * scale);
                    var ctx = c.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, c.width, c.height);
                    ctx.drawImage(img, 0, 0, c.width, c.height);
                    url = c.toDataURL('image/png');
                } catch (e) { url = null; }
                if (url) {
                    saveDataUrl(url, stamp() + '_x3.png', 'PNG');
                } else {
                    <?php /* 位图化失败（例如 canvas 被标记为污染）时退化为存 SVG，至少不丢结果 */ ?>
                    saveDataUrl('data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg),
                                stamp() + '.svg', 'SVG');
                }
            };
            img.onerror = function () {
                saveDataUrl('data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg),
                            stamp() + '.svg', 'SVG');
            };
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
        });
    }

    render();
}());
</script>
