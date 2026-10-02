<?php
/* =====================================================================
 * 物种 × 数据类型 覆盖矩阵 —— 渲染层
 *
 * 审稿意见 Referee 2 major 2：
 *   "Please provide a species-by-data-type coverage matrix so that users can
 *    see at a glance which species have which data."
 * 审稿意见 Referee 2 major 1：
 *   "When a species is selected … directly access all available … resources
 *    for that species."
 *
 * 矩阵原来只在 coverage_matrix.php 上有一份。数据统计页
 * (data_statistics.php) 也要放同一张表，而且要求「占满整宽、表头横排、
 * 可按物种名和 class 排序、有数据就显示数据量并给可点的链接」。与其维护
 * 两份会各自漂移的 HTML，不如把取数 + 渲染集中在这里，两个页面共用：
 *   - coverage_matrix.php     独立页（带 TSV 导出），旧链接继续可用
 *   - data_statistics.php     嵌在 Software and Analytical Tools 之前的卡片里
 *
 * 数据来源是 includes/coverage.php（1 小时 JSON 缓存）。
 * ===================================================================== */

require_once __DIR__ . '/coverage.php';
require_once __DIR__ . '/modlinks.php';
require_once __DIR__ . '/state.php';

/**
 * 一段文字在浏览器里占多宽（px）—— 15px 的 Arial 系字体（Arial / Helvetica /
 * Liberation Sans 度量一致，是本页 font-family 里第一个「到处都可能有」的字）。
 *
 * 这一页的列宽是按「需要」比例分出来的，所以估宽直接决定表头会不会折行：
 * 估小的那一列就比自己的表头还窄。旧代码用的是「字符数 × 7.2px」，对宽字母的
 * 词能少算三成 —— 「Genome」按 Arial 量是 57.5px、旧估算只有 43.2px，于是它那一列
 * 分到 57.7px，放不下表头连 padding 要的 66.5px，折成「Genom / e」；反过来对窄字母
 * 的词又多算（「Transcripts」量 73.6px、估 79.2px），白占别人的宽度。这类误差累积
 * 起来就是 7 列折行（2026-09-30 在 Chromium 实测）。
 *
 * 表值是逐字符按 Chromium 实测标定的，18 个表头 + 各种计数文字复核过，误差 ≤1px
 * （最差的 JBrowse 58.2 vs 实测 57.5）。
 *
 * 两点让它只需要一张表：
 *   · 表头链接渲染的是 **400**，不是 th 上写的 600 —— templatemo_style.css 的
 *     a:link{font-weight:normal} 作用在 a 上，压过了继承来的 600。本页 CSS 里
 *     把 400 显式写了出来（见 table.cm thead th a），标定基准才不会被人改跑。
 *   · 计数徽章是 700，但里面的字全是数字和逗号，Arial 的数字粗细同宽
 *     （实测 "73,673" 在 400/500/700 下都是 45.9px），所以同一张表照样准。
 *
 * $scale 给 12px 的单位文字（Mitogenome 的 genes，是 15px 的 0.8 倍）用。
 */
function cnido_cm_text_px($s, $scale = 1.0)
{
    static $w = array(
        'A' => 10.8, 'B' => 10.0, 'C' => 10.8, 'D' => 10.8, 'E' => 10.0,
        'F' => 9.2,  'G' => 11.7, 'H' => 10.8, 'I' => 4.2,  'J' => 8.3,
        'K' => 10.8, 'L' => 8.3,  'M' => 12.5, 'N' => 10.8, 'O' => 11.7,
        'P' => 10.0, 'Q' => 11.7, 'R' => 10.8, 'S' => 10.0, 'T' => 9.2,
        'U' => 10.8, 'V' => 10.0, 'W' => 14.2, 'X' => 10.0, 'Y' => 10.0,
        'Z' => 9.2,
        'a' => 8.3,  'b' => 8.3,  'c' => 7.5,  'd' => 8.3,  'e' => 8.3,
        'f' => 4.2,  'g' => 8.3,  'h' => 8.3,  'i' => 3.3,  'j' => 3.3,
        'k' => 7.5,  'l' => 3.3,  'm' => 12.5, 'n' => 8.3,  'o' => 8.3,
        'p' => 8.3,  'q' => 8.3,  'r' => 5.0,  's' => 7.5,  't' => 4.2,
        'u' => 8.3,  'v' => 7.5,  'w' => 10.8, 'x' => 7.5,  'y' => 7.5,
        'z' => 6.7,
        '0' => 8.3,  '1' => 8.3,  '2' => 8.3,  '3' => 8.3,  '4' => 8.3,
        '5' => 8.3,  '6' => 8.3,  '7' => 8.3,  '8' => 8.3,  '9' => 8.3,
        ' ' => 4.2,  ',' => 4.2,  '.' => 4.2,  '-' => 5.0,  '/' => 4.2,
        '(' => 5.0,  ')' => 5.0,  ':' => 4.2,  ';' => 4.2,  '_' => 8.3,
        "'" => 2.5,  '&' => 10.0, '+' => 8.3,  '%' => 10.4,
    );
    $px = 0.0;
    /* 表里没有的字（✓ 和 ·，都是表体的标记）按平均字宽算 —— 它们比任何表头都窄，
       不影响列宽，只为不让它们变成 0。 */
    foreach (preg_split('//u', (string)$s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $px += isset($w[$ch]) ? $w[$ch] : 8.3;
    }
    return $px * $scale;
}

/** 矩阵的 CSS。两个宿主页面各输出一次即可。 */
function cnido_cm_css()
{
    /* 覆盖矩阵：占满容器整宽，表头横排（不竖排旋转），小屏横向滚动 */
    /* min-width 由 cnido_cm_html() 现算后写在 <table> 的 style 上，这里不写 ——
   它是「每列正好拿到自己 need」的那个表宽，随数据变（needSum 会跟着计数长），
   写死会过期。见那边的注释，以及 cnido_cm_text_px()。 */
    /* 分组表头（Transcriptome）在第一行，横跨分组内的三列；第二行才是那三列各自的
   列名（Data / Assembly / Network）。未分组的列在第一行里 rowspan=2，所以列的
   对齐与以前完全一样，只是表头多了一层。
   两行都要 sticky，所以第二行的 top 必须是第一行的高度：写死会在别的字号/缩放
   下错位（body 的 line-height:1.7em 会继承进 th，行高不是字号的整数倍），
   改动的是 cnido_cm_html() 末尾那段量一次 --cm-head-h 的脚本 —— 与
   trans_assembly.php 的 --ts-head-h 是同一个做法。fallback 34px 只是脚本跑起来
   之前的估值。 */
    /* 排序三角用 CSS 边框画，不用 U+25BE 那个三角字符：它不少字体里没有，会渲染成
   缺字方块。而且必须挂在 a 上而不是 th 上 —— th 里的 a 是 display:block，
   挂在 th 上的话三角会掉到表头文字下面单独占一行。 */
    /* 列宽不在这里写死：Species / Class + 16 个模块列的宽度统一由 cnido_cm_html()
   按内容算好后以 <col style="width:…"> 输出，避免两处百分比各说各话。这里只管样式。 */
    /* 计数后面的单位（Mitogenome 的 genes）：比数字小一号、弱一点，让「23」仍然是
   一眼看到的主角。 */
    return <<<CSS
<style>
 
.cm-wrap{width:100%;overflow-x:auto;border:1px solid #e2e8f0;border-radius:10px;background:#fff}
 
table.cm{border-collapse:separate;border-spacing:0;width:100%;
  table-layout:fixed;font-size:15px;margin:0}
table.cm th,table.cm td{border-bottom:1px solid #eef2f7;border-right:1px solid #eef2f7;
  padding:5px 4px;text-align:center;overflow-wrap:break-word;word-wrap:break-word}
/* 表头：上下左右都居中（用户要求）。以前第一行是 vertical-align:top、第二行是
   bottom —— 那是单行表头时代的写法，表头变两行之后它让 15 个 rowspan=2 的列名
   （Species / Class + 13 个未分组的模块列）各自贴着自己那一行的边，跟组名
   Transcriptome、组内列名 Data / Assembly / Network 三种基线各走各的。
   列宽算准之后 16 个表头都是单行，middle 是唯一说得通的那种。
   nowrap 是保险：列宽按 cnido_cm_text_px() 实测算，真差 1px 时宁可让字压出格子
   1px（居中溢出，两边各 0.5px，看不出来），也别折成「Genom / e」。*/
table.cm thead th{background:#f1f5f9;position:sticky;top:0;z-index:2;font-weight:600;
  color:#334155;font-size:15px;line-height:1.3;vertical-align:middle;padding:7px 4px;
  white-space:nowrap}
/* font-weight:400 是显式钉住的：templatemo_style.css 的 a:link{font-weight:normal}
   本来就把表头链接压成了 400（th 上那个 600 传不进来），而列宽是按 400 的字宽算的
   —— 见 cnido_cm_text_px()。写出来是为了将来有人改了那条 a:link 时，表头不会悄悄
   变粗、把 16 列的宽度一起算错。 */
table.cm thead th a{text-decoration:none;color:inherit;display:block;font-weight:400}
table.cm thead th a:hover{color:#1d4ed8}
table.cm thead th.sorted{background:#e0e7ff;color:#1e293b}

table.cm thead th.cm-grp{background:#e8eef7;color:#1e293b;border-right:1px solid #dbe3ee}
table.cm thead tr.cm-col-row th{top:var(--cm-head-h,34px);z-index:3}

table.cm thead th.sorted a::after{content:"";display:inline-block;width:0;height:0;
  margin-left:5px;vertical-align:middle;border-left:4px solid transparent;
  border-right:4px solid transparent;border-top:5px solid currentColor}

/* Species 的表头也居中（用户要求「表头上下左右居中」）。表体那一列照旧左对齐 ——
   学名长短差得多，左对齐才看得出它们从哪开始，表头居中不影响这一点。
   padding 也拉平：cm-sp 那条规则原本给该格加了 10px 左 padding，在居中的表头上
   会把它往右推 3px。 */
table.cm thead th.cm-sp{text-align:center;padding-left:4px;padding-right:4px}
table.cm th.cm-sp,table.cm td.cm-sp{text-align:left;padding-left:10px}
table.cm td.cm-sp a{font-weight:600;text-decoration:none;color:#1d4ed8}
table.cm td.cm-sp a:hover{text-decoration:underline}
table.cm td.cm-cls{color:#64748b;font-size:15px}
table.cm tbody tr:nth-child(even) td{background:#f8fafc}
table.cm tbody tr:hover td{background:#f1f5f9}
table.cm td.yes{padding:4px 3px}
table.cm td.yes a{display:block;border-radius:5px;padding:3px 2px;font-weight:700;
  text-decoration:none;font-size:15px;line-height:1.25;
  background:#dcfce7;color:#15803d;font-variant-numeric:tabular-nums}
table.cm td.yes a.has-count{font-size:15px}
/* 计数也 nowrap：列宽按最长计数算过（"73,673"），真差 1px 时宁可压出格子 1px，
   也不能断成 "1,5" / "81" —— 那会被读成两个数。 */
table.cm td.yes a,table.cm td.yes>span{white-space:nowrap}

table.cm .cm-unit{font-size:12px;font-weight:500;opacity:.85;margin-left:1px}
table.cm td.yes a:hover{background:#15803d;color:#fff}
table.cm td.no{color:#64748b;font-size:15px}
.cm-note{font-size:15px;color:#64748b;margin-left:6px;font-weight:400}
.cm-filters{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;
  padding:12px 16px;margin:0 0 14px;line-height:2.4}
.cm-filters select,.cm-filters input[type=text],.cm-filters input[type=number]{
  padding:6px 9px;border:1px solid #cbd5e1;border-radius:7px;font-size:15px;
  background:#fff;margin-right:8px}
.cm-filters button{padding:7px 20px;border:none;border-radius:7px;background:#2563eb;
  color:#fff;font-size:15px;font-weight:600;cursor:pointer}
.cm-filters button:hover{background:#1d4ed8}
.cm-filters a.btn{padding:7px 16px;border:1px solid #cbd5e1;border-radius:7px;
  background:#fff;font-size:15px;text-decoration:none;color:#475569;margin-left:6px;
  display:inline-block}
.cm-stats{font-size:15px;color:#475569;margin:0 0 12px;line-height:1.9}
.cm-stats b{color:#1e293b}
.cm-chip{display:inline-block;padding:2px 10px;border-radius:12px;background:#f1f5f9;
  color:#334155;font-size:15px;margin:2px 4px 2px 0;text-decoration:none}
.cm-chip.on{background:#2563eb;color:#fff}
.cm-page{font-size:15px;margin:12px 0}
.cm-page a,.cm-page span{padding:4px 11px;border:1px solid #e2e8f0;border-radius:6px;
  margin-right:4px;text-decoration:none;color:#475569}
.cm-page span{background:#2563eb;border-color:#2563eb;color:#fff}
</style>
CSS;
}

/**
 * 取数 + 过滤 + 排序。
 *
 * @return array 紧凑数组，键：modules / species / data / modTotals / score /
 *               rows / total / pages / page / perPage / perAll / built / class /
 *               q / mod / min / sort
 */
function cnido_cm_prepare($conn, $opts = array())
{
    $perPageDefault = isset($opts['per_page']) ? (int)$opts['per_page'] : 100;

    $class = isset($_GET['class']) ? preg_replace('/[^A-Za-z]/', '', (string)$_GET['class']) : 'all';
    if ($class === '') { $class = 'all'; }
    $q   = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $mods = cnido_modules();
    $mod = (isset($_GET['mod']) && isset($mods[$_GET['mod']])) ? $_GET['mod'] : '';
    $min = isset($_GET['min']) ? max(0, (int)$_GET['min']) : 0;
    /* 默认按 class 分组、组内按学名字母序 —— 统计页上的矩阵是给人通读的，
       按数据完整度排会把类群打散，反而看不出「哪个类群缺哪类数据」。 */
    $sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'class';
    if (!in_array($sort, array('class', 'name', 'score'), true)) { $sort = 'class'; }
    /* 每页行数除了数字还认 `all`（整份筛选结果一次发出去，方便通读与浏览器
       Ctrl+F）。写法与 genomeinfo.php 的 gpp=all 一致 —— 同一站上「全部」只该
       有一种表示法。`all` 不是「一个很大的数」：真值要等 $total 算出来才知道，
       所以这里先记下意愿，perPage 留成默认值占位，下面算完 $total 再定。 */
    $__ppRaw = isset($_GET['per_page']) ? $_GET['per_page'] : '';
    $perAll  = is_string($__ppRaw) && strtolower(trim($__ppRaw)) === 'all';
    $perPage = $perAll ? $perPageDefault : (int)$__ppRaw;
    if (!$perAll && ($perPage <= 0 || $perPage > 400)) { $perPage = $perPageDefault; }
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

    $cov     = cnido_coverage($conn);
    $species = $cov['species'];
    $data    = $cov['cov'];
    /* 哪些格子里的值是「存在性占位」而不是计数（见 coverage.php 的 $flag）。
       老版本缓存里没有这个键，缺省成空数组即可。 */
    $flags   = isset($cov['flag']) ? $cov['flag'] : array();

    /* ---- 每个模块覆盖多少物种；每个物种有几类数据 ---- */
    $modTotals = array_fill_keys(array_keys($mods), 0);
    $score     = array();
    foreach ($species as $abbr => $info) {
        $n = 0;
        foreach ($mods as $m => $cfg) {
            if (!empty($data[$abbr][$m])) { $modTotals[$m]++; $n++; }
        }
        $score[$abbr] = $n;
    }

    /* ---- 过滤 ---- */
    $rows = array();
    foreach ($species as $abbr => $info) {
        if ($class !== 'all' && strcasecmp(trim($info['class']), $class) !== 0) { continue; }
        if ($mod !== '' && empty($data[$abbr][$mod])) { continue; }
        if ($score[$abbr] < $min) { continue; }
        if ($q !== '') {
            /* 短码仍然参与匹配（使用者手里常常是短码），只是不显示出来 */
            $hay = strtolower($abbr . ' ' . $info['species'] . ' ' . $info['abbr']);
            if (strpos($hay, strtolower($q)) === false) { continue; }
        }
        $rows[$abbr] = $info;
    }

    /* ---- 排序 ---- */
    if ($sort === 'name') {
        uasort($rows, function ($a, $b) { return strcasecmp($a['species'], $b['species']); });
    } elseif ($sort === 'score') {
        $keys = array_keys($rows);
        usort($keys, function ($x, $y) use ($score, $rows) {
            if ($score[$y] !== $score[$x]) { return $score[$y] - $score[$x]; }
            return strcasecmp($rows[$x]['species'], $rows[$y]['species']);
        });
        $sorted = array();
        foreach ($keys as $k) { $sorted[$k] = $rows[$k]; }
        $rows = $sorted;
    } else {
        uasort($rows, function ($a, $b) {
            $c = strcasecmp($a['class'], $b['class']);
            return $c !== 0 ? $c : strcasecmp($a['species'], $b['species']);
        });
    }

    $total = count($rows);
    if ($perAll) {
        /* 「全部」＝ 一页装下整份筛选结果：perPage 取行数（array_slice 拿到整份），
           pages 自然是 1，分页条也不画。$page 强制回 1 —— 地址里可能残留着上一
           次翻到的页码（例如 ?per_page=100&page=4 之后把下拉框换成 All），留着
           会让 array_slice 从第 61 行开始切，那时候表里只剩半截且看起来毫无异常。 */
        $perPage = max(1, $total);
        $pages   = 1;
        $page    = 1;
    } else {
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) { $page = $pages; }
    }

    return array(
        'modules'   => $mods,
        'species'   => $species,
        'data'      => $data,
        'flags'     => $flags,
        'modTotals' => $modTotals,
        'score'     => $score,
        'rows'      => $rows,
        'total'     => $total,
        'pages'     => $pages,
        'page'      => $page,
        'perPage'   => $perPage,
        /* 「一页装下全部」是用户的选择，URL 里要用 all 表达（不是它当场算出来的
           那个行数 —— 库里的物种数会变，写进链接就等于把当天的行数钉死了）。
           cnido_cm_url() 与下拉框的选中态都看这个键。 */
        'perAll'    => $perAll,
        /* 宿主页给的默认每页行数。cnido_cm_url() 要靠它决定 URL 里要不要省掉
           per_page —— 原来那里硬编码 100，嵌在 genomeinfo.php（20 行一页）时
           每翻一页都会把 per_page=20 弄丢、跳回 100 行。 */
        'perPageDefault' => $perPageDefault,
        'built'     => $cov['built'],
        'class'     => $class,
        'q'         => $q,
        'mod'       => $mod,
        'min'       => $min,
        'sort'      => $sort,
    );
}

/** 保留当前筛选条件的链接生成器（$base 是宿主页面，两个页面共用同一套参数）。 */
function cnido_cm_url($M, $over = array(), $opts = array())
{
    $base   = isset($opts['base'])   ? $opts['base']   : 'coverage_matrix.php';
    $anchor = isset($opts['anchor']) ? $opts['anchor'] : '';
    $perAll = !empty($M['perAll']);
    $keep = array(
        'class' => $M['class'], 'q' => $M['q'], 'mod' => $M['mod'], 'min' => $M['min'],
        'sort' => $M['sort'], 'per_page' => ($perAll ? 'all' : $M['perPage']), 'page' => $M['page'],
    );
    /* 宿主页自己的查询参数（genomeinfo.php 的 species / filter）。矩阵的翻页、
       排序、筛选都是本页跳转，不带上的话点一下「第 2 页」就顺手把宿主页的筛选
       清掉了。$over 在后、优先级更高，所以这些键不会盖掉矩阵自己的参数。 */
    if (!empty($opts['keep']) && is_array($opts['keep'])) {
        foreach ($opts['keep'] as $k => $v) {
            if ($v !== '' && $v !== null) { $keep[$k] = $v; }
        }
    }
    foreach ($over as $k => $v) { $keep[$k] = $v; }
    $keep = array_filter($keep, function ($v) { return $v !== '' && $v !== null && $v !== 0 && $v !== '0'; });
    /* 默认值不必出现在 URL 里，读起来干净 */
    if (isset($keep['sort']) && $keep['sort'] === 'class') { unset($keep['sort']); }
    if (isset($keep['class']) && $keep['class'] === 'all') { unset($keep['class']); }
    /* 默认值不必写进 URL；`all` 是用户明确选的，永远保留（它不是默认值的另一种
       写法，(int)'all' 会变成 0，混进下面这套比较里就会被当成「没选」）。 */
    $ppDef = isset($M['perPageDefault']) ? (int)$M['perPageDefault'] : 100;
    if (!$perAll && isset($keep['per_page']) && (int)$keep['per_page'] === $ppDef) { unset($keep['per_page']); }
    return $base . ($keep ? '?' . http_build_query($keep) : '') . $anchor;
}

/**
 * 导出的表头与数据行 —— TSV 与 XLSX 共用这一份，两种格式不会各说各话。
 *
 * 导出范围是**当前筛选后的全部物种**，与分页无关：审稿意见 Referee 2 major 9
 * 要的是可编程访问，只给当前这一页没有意义。排序也沿用页面上的排序。
 *
 * 返回值里保留**原始类型**（不预先格式化成字符串），这样 XLSX 能把计数写成
 * 数字格、TSV 也不会因为二次转换而变样：
 *   · 计数列（表头带 (n)）→ int；
 *   · 存在性列（表头带 (1/0)）→ 0 或 1，绝不是内部那个可能为 2、3 的累加值
 *     （genefamily 这类模块是三张表各加一次，直接吐出去会和表头自相矛盾）；
 *   · Mitogenome 那种「有基因组级记录、但没有基因数」的格子 → 字符串 'NA'。
 *     写 1 会被读成「1 个基因」，写 0 会被读成「没有数据」，两者都不对。
 *
 * @param  array $M cnido_cm_prepare() 的结果
 * @return array    array($header, $rows)
 *                  $header 每项 array('label' => 文字, 'num' => 是否数值列)
 *                  $rows   每项 array('species','class','cells','score')
 */
function cnido_cm_export_table($M)
{
    $header = array(
        array('label' => 'Latin_name', 'num' => false),
        array('label' => 'Class',      'num' => false),
    );
    foreach ($M['modules'] as $m => $cfg) {
        $header[] = array(
            'label' => $cfg['label'] . (cnido_module_count_is_meaningful($m) ? ' (n)' : ' (1/0)'),
            'num'   => true,
        );
    }
    $header[] = array('label' => 'total_data_types', 'num' => true);

    $rows = array();
    foreach ($M['rows'] as $abbr => $info) {
        $cells = array();
        foreach ($M['modules'] as $m => $cfg) {
            $v = isset($M['data'][$abbr][$m]) ? (int)$M['data'][$abbr][$m] : 0;
            if (cnido_module_count_is_meaningful($m)) {
                $cells[] = !empty($M['flags'][$abbr][$m]) ? 'NA' : $v;
            } else {
                $cells[] = $v > 0 ? 1 : 0;
            }
        }
        $rows[] = array(
            'species' => (string)$info['species'],
            'class'   => (string)$info['class'],
            'cells'   => $cells,
            'score'   => (int)$M['score'][$abbr],
        );
    }
    return array($header, $rows);
}

/**
 * 把导出表摊成「一行一个数组」的纯值形式，并把筛选条件写成给读者的说明。
 *
 * @param  array $M     cnido_cm_prepare() 的结果
 * @param  array $table cnido_cm_export_table() 的结果
 * @return array        array($header, $rows, $about)
 *                      $about 是「这次导出的是什么」的若干行说明，
 *                      下载下来的文件脱离网页后还能自证来源与口径。
 */
function cnido_cm_export_flat($M, $table)
{
    list($eh, $er) = $table;
    $header = array();
    foreach ($eh as $h) { $header[] = $h['label']; }

    $rows = array();
    foreach ($er as $r) {
        $row = array($r['species'], $r['class']);
        foreach ($r['cells'] as $c) { $row[] = $c; }
        $row[] = $r['score'];
        $rows[] = $row;
    }

    $about = array(
        array('CnidoSite — Data Coverage Matrix', ''),
        array('Generated', date('Y-m-d H:i')),
        array('Matrix rebuilt from the database', date('Y-m-d', $M['built'])),
        array('Species rows in this export', count($rows)),
        array('Class filter', $M['class'] === 'all' ? 'all classes' : $M['class']),
        array('Name filter', $M['q'] === '' ? 'none' : $M['q']),
        array('Requires data type', $M['mod'] === '' ? 'none' : $M['mod']),
        array('At least N data types', $M['min'] > 0 ? (string)(int)$M['min'] : 'none'),
        array('Sorted by', $M['sort']),
        array('', ''),
        array('Column semantics', ''),
        array('Columns marked (n)', 'how many records the database holds for that species in that module'),
        array('Columns marked (1/0)', '1 = the data type is available for that species, 0 = it is not'),
        array('NA in an (n) column', 'the data type is catalogued at genome level only (for example a mitochondrial '
            . 'genome known from its GenBank record with no gene annotation), so there is no gene count to report'),
        array('total_data_types', 'how many of the data types in this matrix that species has'),
        /* 网页上 Transcriptome 那一组是三列共用一个组名；导出是平表，没有分组这一层，
           所以在这里说明哪三列原本是一组，免得看文件的人不知道它们的关系。 */
        array('Column groups', 'On the web page the columns Bulk transcriptome, Transcriptome assembly and '
            . 'Co-expression network sit under one Transcriptome heading (Data / Assembly / Network). They are '
            . 'three different things, not three views of one, and they do not imply one another: a species can '
            . 'have RNA-seq samples without an assembled transcriptome, or the other way round'),
        /* 站点地址取 HTTP_HOST（与 release.php 同一写法），换域名或本地测试时
           不会写死成生产域名。 */
        array('Source', 'https://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'cnidosite.org')
            . '/coverage_matrix.php'),
    );
    return array($header, $rows, $about);
}

/**
 * 渲染整块矩阵（筛选器 + 表 + 分页 + 图例）。
 *
 * @param mysqli $conn
 * @param array  $opts  base / anchor / per_page / show_filters / show_title
 */
function cnido_cm_html($conn, $opts = array())
{
    $M       = cnido_cm_prepare($conn, $opts);
    $showF   = !isset($opts['show_filters']) || $opts['show_filters'];
    $modules = $M['modules'];
    $species = $M['species'];
    $data    = $M['data'];
    $flags   = $M['flags'];
    $rows    = $M['rows'];

    $slice = array_slice($rows, ($M['page'] - 1) * $M['perPage'], $M['perPage'], true);

    $U = function ($over = array()) use ($M, $opts) { return cnido_cm_url($M, $over, $opts); };
    $E = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

    $h = '';

    /* ---- 每个数据类型覆盖多少物种（点一下即按该类型过滤）---- */
    $h .= '<p class="cm-stats"><span style="font-weight:600;color:#1e293b;margin-right:10px">'
        . 'Species per data type:</span>';
    foreach ($modules as $m => $cfg) {
        $on = ($M['mod'] === $m) ? ' on' : '';
        $h .= '<a class="cm-chip' . $on . '" href="' . $E($U(array('mod' => ($M['mod'] === $m ? '' : $m), 'page' => ''))) . '"'
            . ' title="' . $E($cfg['desc']) . '">' . $E($cfg['short'])
            . ' <b>' . (int)$M['modTotals'][$m] . '</b></a>';
    }
    if ($M['mod'] !== '') {
        $h .= '<a class="cm-chip" href="' . $E($U(array('mod' => '', 'page' => ''))) . '">&times; clear</a>';
    }
    $h .= '</p>';

    /* ---- 筛选器 ---- */
    if ($showF) {
        $classes = array('all');
        foreach ($species as $i) { if ($i['class'] !== '') { $classes[$i['class']] = $i['class']; } }
        /* action 上要带 $anchor（宿主页那张卡片的 id）。GET 表单提交是整页跳转，
           浏览器回到页面顶部；不带锚点的话，在统计页上搜一次就被甩到页面最上面，
           用户看不到刚搜出来的表。片段只作用于浏览器，不会发给服务器。 */
        $h .= '<div class="cm-filters"><form method="get" action="'
            . $E((isset($opts['base']) ? $opts['base'] : 'coverage_matrix.php')
                 . (isset($opts['anchor']) ? $opts['anchor'] : '')) . '">';
        /* 宿主页自己的查询参数要跟着表单一起提交（genomeinfo.php 的 species /
           filter）。GET 表单会把 action 上原有的查询串整个丢掉，所以只能写成
           隐藏字段。 */
        if (!empty($opts['keep']) && is_array($opts['keep'])) {
            foreach ($opts['keep'] as $__k => $__v) {
                if ($__v !== '' && $__v !== null) {
                    $h .= '<input type="hidden" name="' . $E($__k) . '" value="' . $E($__v) . '" />';
                }
            }
        }
        $h .= '<label style="font-size:15px;color:#475569">Class</label> <select name="class">';
        foreach ($classes as $c) {
            $h .= '<option value="' . $E($c) . '"' . ($c === $M['class'] ? ' selected="selected"' : '') . '>'
                . ($c === 'all' ? 'All classes' : $E($c)) . '</option>';
        }
        $h .= '</select> '
            . '<input type="text" name="q" size="18" placeholder="species name" value="' . $E($M['q']) . '"> '
            . '<label style="font-size:15px;color:#475569">at least</label> '
            . '<input type="number" name="min" min="0" max="' . count($modules) . '" style="width:60px" value="'
            . ($M['min'] > 0 ? (int)$M['min'] : '') . '"> '
            . '<span style="font-size:15px;color:#475569">data types</span> '
            . '<label style="font-size:15px;color:#475569;margin-left:8px">sort by</label> <select name="sort">';
        foreach (array('class' => 'Class, then species name', 'name' => getenv('CNIDO_MSR_DB_NAME') ?: 'jackie_db', 'score' => 'Data completeness') as $k => $lbl) {
            $h .= '<option value="' . $k . '"' . ($k === $M['sort'] ? ' selected="selected"' : '') . '>' . $lbl . '</option>';
        }
        /* 宿主页可以指定「每页几行」的候选（genomeinfo.php 用 20，统计页也用 20）。
           候选里除了数字还可以写 'all'：326 行的表，读者常常想一次看全（也方便
           浏览器里 Ctrl+F）。生效中的行数一律出现在候选里，否则下拉框会显示成
           另一个数，与表下方的分页说明自相矛盾。
           数字与 All 分开排：sort() 拿 int 和字符串比是按 PHP 的混比规则来的，
           'all' 会随版本换位置，这里干脆把 All 钉在最后（数字升序在前）。 */
        $ppChoices = isset($opts['per_page_choices']) ? (array)$opts['per_page_choices'] : array(20, 50, 100, 'all');
        $ppNums    = array();
        $ppAll     = false;
        foreach ($ppChoices as $pp) {
            if (is_string($pp) && strtolower(trim($pp)) === 'all') { $ppAll = true; }
            else { $ppNums[] = (int)$pp; }
        }
        /* 生效中的数字行数若不在候选里就补进去（宿主页给了 per_page 却没给候选）。 */
        if (!$M['perAll'] && !in_array((int)$M['perPage'], $ppNums, true)) { $ppNums[] = (int)$M['perPage']; }
        $ppNums = array_values(array_unique($ppNums));
        sort($ppNums);
        $h .= '</select> '
            . '<label style="font-size:15px;color:#475569;margin-left:8px">rows</label> <select name="per_page">';
        foreach ($ppNums as $pp) {
            $h .= '<option value="' . (int)$pp . '"' . (!$M['perAll'] && $pp === (int)$M['perPage'] ? ' selected="selected"' : '') . '>' . (int)$pp . '</option>';
        }
        if ($ppAll) {
            $h .= '<option value="all"' . ($M['perAll'] ? ' selected="selected"' : '') . '>All</option>';
        }
        $h .= '</select> '
            . '<button type="submit">Apply</button> '
            . '<a class="btn" href="' . $E($U(array('class' => 'all', 'q' => '', 'mod' => '', 'min' => '', 'sort' => 'class', 'page' => ''))) . '">Reset</a> '
            . '<a class="btn" target="_blank" rel="noopener" href="' . $E(cnido_cm_url($M, array('format' => 'tsv', 'page' => ''), array('base' => 'coverage_matrix.php'))) . '">Download TSV</a> '
            /* 两个下载键的取数、筛选、排序完全共用，所以两份文件内容一致。
               链接的 base 写死 coverage_matrix.php：本表在 data_statistics.php
               上也嵌了一份，下载要落在能处理 format 的那一页。 */
            . '<a class="btn" target="_blank" rel="noopener" href="' . $E(cnido_cm_url($M, array('format' => 'xlsx', 'page' => ''), array('base' => 'coverage_matrix.php'))) . '">Download Excel (.xlsx)</a>'
            . '</form></div>';
    }

    /* ---- 「显示的是哪一段」---- */
    $h .= '<p class="cm-stats">Showing <b>' . (int)$M['total'] . '</b> species';
    if ($M['class'] !== 'all') { $h .= ' in <b>' . $E($M['class']) . '</b>'; }
    if ($M['q'] !== '') {
        /* 使用者可能输入短码或下划线形式；回显时一律换成拉丁学名，
           否则等于把物种代码印在页面上。 */
        $h .= ' matching <b><i>' . $E(cnido_latin_loose($M['q'])) . '</i></b>';
    }
    if ($M['min'] > 0) { $h .= ' with at least <b>' . (int)$M['min'] . '</b> data types'; }
    if ($M['mod'] !== '') { $h .= ' having <b>' . $E($modules[$M['mod']]['label']) . '</b>'; }
    if ($M['pages'] > 1) { $h .= ' &middot; page ' . (int)$M['page'] . ' of ' . (int)$M['pages']; }
    $h .= ' <span class="cm-note">(coverage rebuilt ' . date('Y-m-d', $M['built']) . ')</span></p>';

    if ($M['total'] === 0) {
        $h .= '<div class="gd-notice gd-info">No species matches this filter. '
            . '<a href="' . $E($U(array('class' => 'all', 'q' => '', 'mod' => '', 'min' => '', 'page' => ''))) . '">Clear the filters</a>'
            . ' to see all ' . count($species) . ' species.</div>';
        return $h;
    }

    /* ---- 表体 ---- */
    /* 列宽全部在这里定，CSS 里不再写 col 宽度 —— 免得两处百分比各说各话。
       Species / Class 两列定死百分比，余下 80.5% 按「需要」分给 16 个模块列。
       模块列的需要 = max(表头格, 该列最长计数格)，两种格子的 padding 不一样（见
       下面），所以分别算：像 TE 这样两个字母的表头就只要一点点宽度，省下的归
       Paleobiology / Metagenome 这些长表头。
       表头文字用的是**表头里真的印出来的那几个字**（分组内的列印 gshort：
       Data / Assembly / Network，不是 short 的全名），否则量的是屏幕上根本没有的
       那个字符串，列宽会白留。
       字宽逐字符查表（cnido_cm_text_px），与浏览器实测相差 1px 以内，所以按需要
       比例分完之后每列只留 2px 余量 —— 以前那个「+15%」是给「字符数 × 常数」的
       估算误差兜底的，估算准了就不必白留（也留不起：16 列各白留 15% 就是两列宽）。
       Class 从 6.5% 起是按最长的类名 Hexacorallia（实测 82.5px，连 padding 93.2px）
       定的，此前 6% 在窄表宽下正好差几像素。
       Species 列 13% 是够放 28 个字符的学名，326 个里约 11 个（3.4%）长名会折成
       两行 —— 它是唯一允许折行的列，16 个模块列的表头都排成一行。
       计数扫的是全量 $data 而不是当前页 $slice，否则翻页时列宽会跳。 */
    $spPct  = 13.0;                                 /* Species 列 */
    $clsPct = 6.5;                                  /* Class 列，放得下 Hexacorallia */
    $modPct = 100 - $spPct - $clsPct;               /* 余下这一段给 16 个模块列 */
    /* 表头单元格里印的字：分组内的列印组内短名（gshort），其余印 short。 */
    $headLabel = function ($cfg) {
        return (isset($cfg['gshort']) && $cfg['gshort'] !== '') ? $cfg['gshort'] : $cfg['short'];
    };
    /* 每一列真正需要的宽度，两种格子取大的那个：
       表头格 = 文字 + 左右 padding(4+4) + 右边框 1px；
       计数格 = 数字（+ 单位）+ 徽章 padding(2+2) + td.yes 的 padding(3+3) + 边框 1px。
       计数格那几个 padding 比表头格多 2px，所以两种格子分别加自己的开销，
       不能统一按表头的算 —— Data 列就是计数格比表头格宽的那一列。 */
    $need   = array();
    foreach ($modules as $m => $cfg) {
        $w = cnido_cm_text_px($headLabel($cfg)) + 9;
        if (cnido_module_count_is_meaningful($m)) {
            $unitPx = isset($cfg['unit'])      /* 单位是小一号的字，另有 1px margin-left */
                ? cnido_cm_text_px(' ' . $cfg['unit'], 12 / 15) + 1 : 0;
            foreach ($data as $mods) {
                if (isset($mods[$m])) {
                    /* 窄 1px 的后果不是「挤一点」而是数字折行 —— "1,581" 断成
                       "1,5" / "81" 会被读成两个数，所以这里按最长的那个计数算。
                       扫的是全量 $data 而不是当前页 $slice，否则翻页时列宽会跳。 */
                    $w = max($w, cnido_cm_text_px(number_format((int)$mods[$m])) + $unitPx + 11);
                }
            }
        }
        $need[$m] = $w + 2;             /* 留 2px 抗字体差异（字宽表本身准到 1px 内） */
    }
    /* 分组标题（Transcriptome）横跨它那几列，而 table-layout:fixed 下 colspan
       单元格撑不开列：字比所跨各列之和还宽时只会折行、把表头行顶高，进而让
       sticky 的偏移量（--cm-head-h，量的是第一行的高度）与第二行对不上。
       所以这里兜一道：某一组各列的需要之和放不下组名时，按比例把这一组撑宽。 */
    $groups = array();
    foreach ($modules as $m => $cfg) {
        if (!empty($cfg['group'])) { $groups[$cfg['group']][] = $m; }
    }
    foreach ($groups as $g => $ms) {
        $sum  = 0;
        foreach ($ms as $m) { $sum += $need[$m]; }
        $want = cnido_cm_text_px($g) + 9;           /* 组名那一格的开销同表头格 */
        if ($sum > 0 && $sum < $want) {
            $k = $want / $sum;
            foreach ($ms as $m) { $need[$m] *= $k; }
        }
    }
    /* 老代码在这里还给组内三列（Data / Assembly / Network）单独抬过「自己的词 ＋ 12」
       这个下限 —— 那时 need 里的表头字宽是按 7.2px/字符估的，比实测小两成，不抬
       Assembly 就会折成 "Assembl / y"。现在字宽是逐字符查表算的（见
       cnido_cm_text_px），这三个词的表头项本来就够，再抬一遍反而让 Transcriptome
       这组白占约 27px、别的列跟着变窄，所以整段去掉了。 */
    $needSum = array_sum($need);
    /* 最小表宽 = 每列正好拿到自己 need 的那个宽度，所以必须现算 —— 写死在 CSS 里
       的话，等哪天计数长了（needSum 变大）或表头改了名，那个死值会悄悄失效，
       表头又被压折，而这次修的正是这件事。
       两项取大：模块列那一段按 modPct 分，Class 列按 clsPct 分（Species 列是唯一
       允许折行的列，不参与）。表宽到底还是由容器定（.cm-wrap 的 width:100%），
       这只是下限；内容栏自己封顶在 1588px 左右，1920 视口下也不会更宽。 */
    $clsNeed = 0;
    foreach ($species as $__info) {
        $clsNeed = max($clsNeed, cnido_cm_text_px($__info['class']) + 11);
    }
    $cmMinW = (int)ceil(max($needSum / ($modPct / 100), $clsNeed / ($clsPct / 100)));

    $pct = function ($x) { return number_format($x, 3, '.', '') . '%'; };
    $h .= '<div class="cm-wrap"><table class="cm" style="min-width:' . $cmMinW . 'px">';
    $h .= '<colgroup><col style="width:' . $pct($spPct) . '"><col style="width:' . $pct($clsPct) . '">';
    foreach ($need as $w) { $h .= '<col style="width:' . $pct($modPct * $w / $needSum) . '">'; }
    $h .= '</colgroup>';

    /* 表头两行：第一行是分组名（Transcriptome，横跨它那三列）加上未分组的列
       （rowspan=2，所以未分组列的位置与单行表头时一模一样）；第二行是分组内各列
       自己的列名（Data / Assembly / Network）。
       分组必须连续 —— 同组的列挨在一起，这里才可能把它们合成一个 colspan 单元格。
       没有分组的列表（老缓存）不会多出第二行，那时也不写 rowspan，免得跨进行体。 */
    $keys = array_keys($modules);
    $nKey = count($keys);
    $rs   = !empty($groups) ? 2 : 1;
    /* 一个模块列的表头：点了就按该列筛选（再点一次取消）。$span 是它占的表头行数：
       未分组的列在第一行里就占了位，所以要跨两行；组内的列只出现在第二行。 */
    $modTh = function ($m, $cfg, $span) use ($M, $U, $E, $headLabel) {
        return '<th' . ($span > 1 ? ' rowspan="' . $span . '"' : '') . ' title="'
            . $E($cfg['label'] . ' — ' . $cfg['desc']) . '">'
            . '<a href="' . $E($U(array('mod' => ($M['mod'] === $m ? '' : $m), 'page' => ''))) . '">'
            . $E($headLabel($cfg)) . '</a></th>';
    };
    $h1 = '';
    $h2 = '';
    /* 物种名 / class 表头本身就是排序开关（用户要求「分物种名和 class 排序」）*/
    $h1 .= '<th' . ($rs > 1 ? ' rowspan="' . $rs . '"' : '') . ' class="cm-sp' . ($M['sort'] === 'name' ? ' sorted' : '') . '">'
        . '<a href="' . $E($U(array('sort' => 'name', 'page' => ''))) . '" title="Sort by species name">Species</a></th>';
    $h1 .= '<th' . ($rs > 1 ? ' rowspan="' . $rs . '"' : '') . ' class="' . ($M['sort'] === 'class' ? 'sorted' : '') . '">'
        . '<a href="' . $E($U(array('sort' => 'class', 'page' => ''))) . '" title="Sort by class, then species name">Class</a></th>';
    for ($i = 0; $i < $nKey; $i++) {
        $m   = $keys[$i];
        $cfg = $modules[$m];
        $grp = isset($cfg['group']) ? $cfg['group'] : '';
        if ($grp === '') { $h1 .= $modTh($m, $cfg, $rs); continue; }
        $j = $i;                                    /* 往后数出同组的这一段 */
        while ($j < $nKey && (isset($modules[$keys[$j]]['group']) ? $modules[$keys[$j]]['group'] : '') === $grp) { $j++; }
        $subs = array();
        for ($k = $i; $k < $j; $k++) { $subs[] = $headLabel($modules[$keys[$k]]); }
        $h1 .= '<th class="cm-grp" colspan="' . ($j - $i) . '" title="'
            . $E($grp . ' — ' . implode(', ', $subs)) . '">' . $E($grp) . '</th>';
        for ($k = $i; $k < $j; $k++) { $h2 .= $modTh($keys[$k], $modules[$keys[$k]], 1); }
        $i = $j - 1;
    }
    $h .= '<thead><tr class="cm-grp-row">' . $h1 . '</tr>';
    if ($h2 !== '') { $h .= '<tr class="cm-col-row">' . $h2 . '</tr>'; }
    $h .= '</thead><tbody>';

    foreach ($slice as $abbr => $info) {
        /* 物种名指向 speciesinfo.php（About / Basic Information / Genome Assembly
           Information / References + 该物种的数据清单），不再指向 species_portal.php：
           物种详情页才是访客从矩阵点进去要找的落点。 */
        $portal = 'speciesinfo.php?species=' . urlencode($abbr);
        $h .= '<tr>';
        /* 只显示拉丁学名（斜体）。物种短码只在页与页之间传参，不作为内容呈现。
           表格里的数据链接走当前窗口：本站口径是「站外 + 站内文件下载开新窗口，
           站内页面跳转留当前窗口」（见 index.php 里 Data Statistics 一节的同一处理），
           矩阵和 speciesinfo.php 都是站内页面。 */
        $h .= '<td class="cm-sp"><a href="' . $E($portal) . '"'
            . ' title="Open this species&rsquo; page"><i>'
            . $E($info['species']) . '</i></a></td>';
        $h .= '<td class="cm-cls">' . ($info['class'] !== '' ? $E($info['class']) : '&mdash;') . '</td>';

        foreach ($modules as $m => $cfg) {
            $n = isset($data[$abbr][$m]) ? (int)$data[$abbr][$m] : 0;
            if ($n <= 0) {
                $h .= '<td class="no" title="' . $E($info['species'] . ': no ' . $cfg['label'] . ' in this release') . '">&middot;</td>';
                continue;
            }
            $link = cnido_module_link($m, $abbr, $info);
            /* 计数列 vs 存在性列。$flag 记的是「这个 1 是存在性占位，不是计数」——
               目前只有 Mitogenome 的 6 个物种（只有基因组级记录、没有任何基因级
               注释行），它们和「真的只有 1 个线粒体基因」的 3 个物种在 $data 里
               都是 1，只有 flag 分得开。给前者印「1 genes」等于谎报一条基因。 */
            $placeholder = !empty($flags[$abbr][$m]);
            $real = cnido_module_count_is_meaningful($m) && !$placeholder;
            /* 带单位的列（现在只有 Mitogenome 的 genes）在数字后面跟一个小号单位，
               读者不必去查「23」到底是什么的计数。$num 是纯数字，$txt 是要输出到
               格子里的 HTML —— 两者分开：title 里必须用 $num，否则提示框里会出现
               转义过的 <span> 标签。 */
            $unit = isset($cfg['unit']) ? $cfg['unit'] : '';
            /* 单位是复数形式（genes），值为 1 时要写「1 gene」。只此一个单位，
               不为此加配置字段。 */
            if ($n === 1 && $unit === 'genes') { $unit = 'gene'; }
            if ($real) {
                $num = number_format($n);
                $txt = ($unit !== '') ? $num . ' <span class="cm-unit">' . $E($unit) . '</span>' : $num;
                $tip = $cfg['label'] . ' — ' . $num . ' '
                     . ($unit !== '' ? $unit : ('record' . ($n === 1 ? '' : 's')));
            } else {
                $txt = '&#10003;';
                $tip = $cfg['label'] . ' — ' . ($placeholder
                     ? 'present, but catalogued without a record count'
                     : 'available');
            }
            $h .= '<td class="yes">';
            if ($link !== '') {
                $h .= '<a class="' . ($real ? 'has-count' : '') . '" href="' . $E($link) . '"'
                    . ' title="' . $E($info['species'] . ': ' . $tip . ' — click to open this module for the species') . '">' . $txt . '</a>';
            } else {
                $h .= '<span class="' . ($real ? 'has-count' : '') . '" title="'
                    . $E($info['species'] . ': ' . $tip) . '">' . $txt . '</span>';
            }
            $h .= '</td>';
        }
        $h .= '</tr>';
    }
    $h .= '</tbody></table></div>';

    /* ---- 分页 ---- */
    if ($M['pages'] > 1) {
        $h .= '<div class="cm-page">';
        $from = max(1, $M['page'] - 2);
        $to   = min($M['pages'], $M['page'] + 2);
        if ($M['page'] > 1) { $h .= '<a href="' . $E($U(array('page' => $M['page'] - 1))) . '">&laquo; prev</a>'; }
        if ($from > 1)      { $h .= '<a href="' . $E($U(array('page' => 1))) . '">1</a>'; }
        if ($from > 2)      { $h .= '<span>&hellip;</span>'; }
        for ($i = $from; $i <= $to; $i++) {
            $h .= ($i === $M['page'])
                ? '<span>' . $i . '</span>'
                : '<a href="' . $E($U(array('page' => $i))) . '">' . $i . '</a>';
        }
        if ($to < $M['pages'] - 1) { $h .= '<span>&hellip;</span>'; }
        if ($to < $M['pages'])     { $h .= '<a href="' . $E($U(array('page' => $M['pages']))) . '">' . (int)$M['pages'] . '</a>'; }
        if ($M['page'] < $M['pages']){ $h .= '<a href="' . $E($U(array('page' => $M['page'] + 1))) . '">next &raquo;</a>'; }
        $h .= '</div>';
    }

    /* ---- 图例 ---- */
    /* 列名一律用表头里印的那个（$headLabel：分组内的列是 Data / Assembly /
       Network），读者是照着表头来找的，写 short 的全名反而对不上号。 */
    $realMods = array();
    $flagMods = array();
    foreach ($modules as $m => $cfg) {
        if (cnido_module_count_is_meaningful($m)) { $realMods[] = $headLabel($cfg); }
        else { $flagMods[] = $headLabel($cfg); }
    }
    /* ---- Transcriptome 分组那三列的一句话说明 ----
       三列并排共用一个组名，最容易被读成「同一件事的三个视角」，其实是三样东西。
       所以把三者的物种数各印一遍，并点明互不蕴含 —— 「有样本、没组装」和
       「有组装、没样本」的物种都真实存在（2026-09-27 实测：53 个 / 25 个）。
       数字全部现算：缓存每小时重建一次，写死迟早对不上。 */
    $tmData = (int)$M['modTotals']['transcriptome'];
    $tmAsm  = (int)$M['modTotals']['trans_assembly'];
    $tmBoth = 0;
    foreach ($species as $__ab => $__info) {
        if (!empty($data[$__ab]['transcriptome']) && !empty($data[$__ab]['trans_assembly'])) { $tmBoth++; }
    }
    $tmNote = '<br /><br /><b>The three <i>Transcriptome</i> columns are different things, not three views of one.</b> '
        . '<i>Data</i> = the RNA-seq sample set (<b>' . $tmData . '</b> species, counted in samples); '
        . '<i>Assembly</i> = an assembled transcriptome whose every predicted protein carries functional '
        . 'annotation (<b>' . $tmAsm . '</b>); <i>Network</i> = a co-expression network (<b>'
        . (int)$M['modTotals']['coexpression'] . '</b>). <b>They do not imply one another:</b> <b>'
        . (int)($tmData - $tmBoth) . '</b> species have RNA-seq samples but no assembled transcriptome held here, '
        . 'and <b>' . (int)($tmAsm - $tmBoth) . '</b> have an assembly but no sample records.';
    $h .= '<p class="paleo-intro" style="line-height:1.8">'
        . '<b>How to read a cell.</b> A <b>number</b> = the count of records of that data type held for the '
        . 'species — columns: ' . $E(implode(', ', $realMods)) . '. '
        . 'A <b>&#10003;</b> = present, but catalogued as a single resource rather than a set of countable '
        . 'records — columns: ' . $E(implode(', ', $flagMods)) . '. '
        . 'A <b>&middot;</b> = not available for that species in this release. '
        . 'In <i>Mitogenome</i>, <b>&#10003;</b> is the same idea at one cell: the GenBank record is held with '
        . 'no gene-level annotation, so there is no gene count (<code>NA</code> in the TSV). '
        . 'Every populated cell opens that module with the species preselected; a species name opens its '
        . '<b>species information page</b>.'
        . '<br /><br /><b><i>Genome</i> vs <i>Transcripts</i>.</b> <i>Genome</i> = an assembly record exists (size, '
        . 'scaffold/contig N50, BUSCO, accession); all <b>' . count($species) . '</b> species have one. '
        . '<i>Transcripts</i> = this database also carries the predicted gene set for that assembly, true for <b>'
        . (int)$M['modTotals']['annotation'] . '</b> species. Its number counts <b>predicted transcripts rather '
        . 'than genes</b> — a gene with several isoforms contributes more than one — so it is not the '
        . 'protein-coding gene count printed on the species page. A row with <i>Genome</i> but no '
        . '<i>Transcripts</i> means &ldquo;assembly catalogued here, not yet annotated in CnidoSite&rdquo;, '
        . '<b>not</b> &ldquo;data missing by mistake&rdquo;.'
        . $tmNote
        /* 这两条是正文里的下载链接，和上面那两个 btn 按钮指向同一个地址 ——
           按钮是 _blank、正文里这两条原先不是，同一页同一个 URL 两种行为。
           统一成新窗口：口径是「站内文件下载开新窗口」。响应头是
           Content-Disposition: attachment，两种开法都不会让用户离开矩阵页。 */
        . '<br /><br />Rebuilt from the live database every hour; the same cells and filters are available as <a target="_blank" rel="noopener" href="'
        . $E(cnido_cm_url($M, array('format' => 'tsv', 'page' => ''), array('base' => 'coverage_matrix.php')))
        . '">tab-separated text</a> or <a target="_blank" rel="noopener" href="'
        . $E(cnido_cm_url($M, array('format' => 'xlsx', 'page' => ''), array('base' => 'coverage_matrix.php')))
        . '">an Excel workbook</a>.'
        . '</p>';

    /* 两行表头都 sticky，第二行的 top 必须是第一行的高度。那个高度取决于字号与行高
       （body 的 line-height:1.7em 会继承进 th，不是字号的整数倍），写死会在别的缩放
       或字体下错位 —— 所以量一次写进 --cm-head-h，与 trans_assembly.php 的
       --ts-head-h 是同一个做法。全页只印一次（本函数理论上可被一页调用多次）；
       而 .cm-wrap 不纵向滚动时 sticky 本来也不起作用，这段是无害的。 */
    static $jsDone = false;
    if (!$jsDone) {
        $jsDone = true;
        $h .= '<script>(function(){function m(){var t=document.querySelector("table.cm");'
            . 'if(!t)return;var w=t.closest?t.closest(".cm-wrap"):t.parentNode;'
            . 'var r=t.tHead&&t.tHead.rows[0];if(!w||!r||!w.style)return;'
            . 'var h=r.getBoundingClientRect().height;if(h>0){w.style.setProperty("--cm-head-h",h+"px");}}'
            . 'm();window.addEventListener("resize",m);'
            . 'if(document.fonts&&document.fonts.ready){document.fonts.ready.then(m);}})();</script>';
    }

    return $h;
}
