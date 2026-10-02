<?php
/**
 * 分页表的服务端排序表头。
 *
 * 为什么分页表不能交给 js/table-sort.js：服务端一页只给 25 行，浏览器里排只是把
 * 这 25 行换个次序，看起来「排好了」，其实是错的 —— 排第 2 页时看到的最小值可能
 * 比第 1 页的最大值还小。这类表必须让服务器重发整段结果，所以表头是一个真链接：
 * ？…&sort=<列键>&dir=asc|desc，翻页链接照原样带上这两个参数，翻到第几页排序都
 * 还在。客户端脚本按约定见到 th 里有链接就不碰这张表。
 *
 * 用法（三处，都在页面的 SQL 之前）：
 *
 *   require_once __DIR__ . '/includes/sort_head.php';      // 子目录里 ../includes/
 *   $__sortKeys = array(                                    // 键 => ORDER BY 表达式
 *       'taxon'  => 'Phylum',
 *       'class'  => '`Class`, `order1`',                    // 默认列可以带并列键
 *       'species'=> 'Species',
 *   );
 *   list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'class', 'Species');
 *   ...
 *   $__baseQs .= cnido_sort_qs($__sort, $__dir, $__sortKeys, 'class');  // 只在不默认时附加
 *   ... "SELECT ... ORDER BY " . $__order . " LIMIT ?, ?"
 *   ... <th><?php echo cnido_sort_link('species', 'Species', $__sort, $__dir, $__baseQs); ?></th>
 *
 * 三条硬规矩：
 *   1. ORDER BY 的表达式**只能**来自页面写死的白名单，$_GET 里的值仅用于查表 ——
 *      任何把用户串拼进 SQL 的写法都是注入口子。
 *   2. 白名单里的表达式必须自带并列键（或由第三个参数给）：只按一列排序 + LIMIT，
 *      同一批行在不同页的边界上会重复或消失（browse.php 的老注释里记过这个坑）。
 *   3. 排在 NULL 上的列（TE 的 related_gene 这类）不会报错，但 NULL 全挤在一端；
 *      值得的话在表达式里写 (col IS NULL), col。
 *
 * 白名单的每一项有两种写法：
 *   'phylum' => 'Phylum',                                  // 普通列，方向直接加在后面
 *   'gbif'   => array('(GBIF NOT REGEXP \'^[0-9]+$\')',    // 两段式，见下
 *                     'CAST(GBIF AS UNSIGNED)'),
 * 两段式用在「值是数字但列是 text」的编号列上（classfy 的 NCBI/Worms/GBIF 都是 text，
 * 位数还不齐 —— GBIF 有 7 位也有 8 位）。那种列直接 ORDER BY 是按字典序排的，
 * 10000000 会排在 9789416 前面；而且缺值写的是 '-'，字典序下 '-' 小于所有数字，
 * 升序时会全跑到最前面。既有的客户端脚本对缺值的约定是「不分升降一律排最后」，
 * 服务端照做：第一段是「是不是缺值」的标记，恒为升序（缺值永远垫底），
 * 方向只加在第二段上，正好也是 cnido_sort_state 的默认行为。
 * 常用的两段式有现成的构造函数 cnido_sort_num()（编号列）和 cnido_sort_txt()
 * （可空文字列），没必要每次手写。
 *
 * 还有一种情况：页面的自然次序（$default）不对应任何一列。例如
 * includes/genome_assembly_view.php 按物种编号 abbr1 分组分页，而表里显示的是拉丁
 * 学名，两者次序并不相同。这时给默认项起一个**不在表头里**的键（'default'），
 * $default 传它：页面初始状态是原来的次序、所有箭头都是未选中的 ↕，
 * 用户一点才真正按那列排 —— 而不是让某一列的箭头谎称「现在是按我排的」。
 *
 * 隐藏默认项的表达式写成 null 时，默认态**完全不生成 ORDER BY**（见 cnido_sort_state）。
 * 用在连隐藏键都找不到表达式的表上：paleobiology.php 既没有主键也没有任何一列能复现
 * 现在的行序，硬写一个表达式就等于借着「加排序按钮」把整页默认次序换掉。这一档保持
 * 原样，用户点了才排。
 *
 * 另有四个页面的行压根不经过 SQL 的 ORDER BY（按基因逐个查、PHP 拼成 $all_rows 再
 * array_slice），它们在切片前调 cnido_sort_rows()，表头与翻页链接的写法与 SQL 页完全
 * 相同。
 */

/* 读 $_GET['sort'] / $_GET['dir']，按白名单校验。
   $tie 是并列键（字符串或数组）：单个表达式排不出稳定次序，翻页跨 LIMIT 边界时
   同一行会在两页上都出现或都不出现，所以每个白名单表达式都配一个；已经在表达式
   里的那一个不再重复追加。
   $p 是参数前缀（默认空 = 用 sort / dir）。**同一个页面上有多张服务端排序的表时
   必须各不相同**（data_statistics.php 上是 'g' 和 'l'），否则两张表会共用一个
   $_GET['sort']，点一张表另一张跟着变。
   返回 array(列键, 'asc'|'desc', "表达式 asc|desc, 并列键") —— 第三项直接接在 WHERE 后面。 */
function cnido_sort_state($keys, $default, $tie = array(), $p = '')
{
    $asked = isset($_GET[$p . 'sort']) ? (string)$_GET[$p . 'sort'] : '';
    $fell  = false;
    if ($asked === '' || !array_key_exists($asked, $keys)) {
        /* 键不认识（手改的 URL、旧书签）就整个退回默认：**方向也一起退**。
           只退列不退方向的话，默认键是隐藏键的页面会静默倒序而所有箭头都还是
           未选中态 —— 表面上说「没排序」，实际反着排。 */
        $sort = array_key_exists($default, $keys) ? $default : key($keys);
        $fell = true;
    } else {
        $sort = $asked;
    }
    $dir = (!$fell && isset($_GET[$p . 'dir']) && strtolower((string)$_GET[$p . 'dir']) === 'desc')
         ? 'desc' : 'asc';
    $spec = $keys[$sort];
    /* 白名单项写成 null ＝ 这一档**不加 ORDER BY**。给那些自然次序压根不对应任何一列、
       又没法用表达式复现的页面用（paleobiology.php 的表没有主键也没有排序列，现在的
       行序是插入顺序）：默认态保持原样、六个箭头全是未选中的 ↕，用户点哪列才真正排。
       代价是默认态翻页仍依赖引擎给次序 —— 但那是页面本来就有的状态，不是这次引入的。 */
    if ($spec === null) {
        return array($sort, $dir, '');
    }
    /* 白名单项写成 array(恒升序的前缀, 接方向的那一段) 时，方向只加在后一段上。 */
    if (is_array($spec)) {
        $order = $spec[0] . ', ' . $spec[1] . ' ' . strtoupper($dir);
        $flat  = implode(' ', $spec);
    } else {
        $order = $spec . ' ' . strtoupper($dir);
        $flat  = $spec;
    }
    foreach ((array)$tie as $t) {
        if ($t !== '' && stripos($flat, $t) === false) {
            $order .= ', ' . $t;
        }
    }
    return array($sort, $dir, $order);
}

/* 编号列的两段式表达式：值不是纯数字的（'-'、空串、'N/A'）算缺值，恒升序垫底；
   数字部分用 CAST(... AS UNSIGNED) 按数值排。$expr 必须是页面写死的表达式（列名、
   聚合、CASE 都行）。 */
function cnido_sort_num($expr)
{
    return array('((' . $expr . ') IS NULL OR (' . $expr . ") NOT REGEXP '^[0-9]+$')",
                 'CAST(' . $expr . ' AS UNSIGNED)');
}

/* 允许缺值的文字列的两段式表达式：NULL 或空串恒垫底，其余按列自身的排序规则比较。 */
function cnido_sort_txt($expr)
{
    return array('((' . $expr . ") IS NULL OR (" . $expr . ") = '')", $expr);
}

/* 带小数/带符号的数值列（纬度经度、GC%、BUSCO Score/Length 这类）。
   与 cnido_sort_num() 的区别只在两点，但两点都要命：
     · 认小数（cnido_sort_num 的 '^[0-9]+$' 会把 59.5 判成缺值，整列被当成全缺值推到最后）；
     · 认负号（纬度经度里真的有南纬西经；CAST(... AS UNSIGNED) 会把 -33.9 变成 0，
       于是南半球的站点全被排到赤道的位置上）。
   所以能用哪个要看列，别混用。 */
function cnido_sort_dec($expr)
{
    return array('((' . $expr . ") IS NULL OR (" . $expr . ") NOT REGEXP '^-?[0-9]+(\\\\.[0-9]+)?\$')",
                 'CAST(' . $expr . ' AS DECIMAL(20,6))');
}

/* 排序表头里的那个链接。$baseQs 是页面自己那份「当前筛选条件」查询串（不含 page），
   只在列不是默认列、或方向是 desc 时才把 sort/dir 附上去，免得默认视图的 URL 变长。
   $tail 是查询串后面原样接上的部分，用来挂锚点（data_statistics.php 三张卡片都是
   整页跳转，不带 #anchor 会被浏览器甩回页面最顶部）。 */
function cnido_sort_link($key, $label, $cur, $dir, $baseQs, $p = '', $tail = '')
{
    $isCur = ($key === $cur);
    $next = ($isCur && $dir === 'asc') ? 'desc' : 'asc';
    $extra = urlencode($p . 'sort') . '=' . urlencode($key)
           . '&' . urlencode($p . 'dir') . '=' . $next;
    $qs = $baseQs === '' ? $extra : $baseQs . '&' . $extra;
    $self = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
    $href = $self . '?' . htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') . $tail;
    if (!$isCur) {
        $arrow = '&nbsp;<span style="color:#9aa7b4;">&#8597;</span>';
        $title = 'Sort by this column';
    } elseif ($dir === 'asc') {
        $arrow = '&nbsp;<span style="color:#1d4ed8;">&#8593;</span>';
        $title = 'Sorted ascending &mdash; click for descending';
    } else {
        $arrow = '&nbsp;<span style="color:#1d4ed8;">&#8595;</span>';
        $title = 'Sorted descending &mdash; click for ascending';
    }
    return cnido_sort_link_css() . '<a class="cnido-sort-link" data-sort-link="1" href="' . $href
         . '" title="' . $title . '">' . $label . $arrow . '</a>';
    /* 表头文字不能变成蓝色：外链样式表里的 a:link,a:visited{color:#1d4ed8} 是 (0,1,1)，
       压过裸类名 (0,1,0)，所以这条必须写成 a.cnido-sort-link —— 与 coverage matrix
       表头链接同一个写法（th a{color:inherit} + hover 变蓝）。不写在
       templatemo_style.css 里是因为那个文件裸链、没有 filemtime 破缓存，
       改了对老访客要过几个小时才生效；这里放在 <style> 里跟着页面走。 */
}

/* 上面那条样式。整页只印一次（一个页面里九列表头会调九次）。 */
function cnido_sort_link_css()
{
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    return '<style type="text/css">'
         . 'a.cnido-sort-link,a.cnido-sort-link:visited{color:inherit;text-decoration:none;}'
         . 'a.cnido-sort-link:hover{color:#1d4ed8;text-decoration:underline;}'
         . '</style>';
}

/* PHP 侧排序：有些页面的行**不是** SQL 直接给的 —— go_result.php / interpro_result.php /
   kegg_result.php / proteindomain_result.php 都是「按基因逐个查、把结果拼进 $all_rows，
   再 array_slice 切页」。这种页面没有能塞 ORDER BY 的 SELECT，只能在切片之前自己排。

   $idx 是「列键 => 该列在 $row 里的下标」；'default' 不要放进去（不在 $idx 里 ＝ 这一档
   一行都不动，页面回到加按钮之前的原次序）。$tie 是并列时依次比较的列键，理由与 SQL 侧
   完全相同，但这里更要紧：**usort 在 PHP 7 里是不稳定的**（PHP 8 才开始稳定），比较器
   一旦返回 0，并列的行顺序就是随机的 —— 翻页跨 slice 边界时同一行会在两页都出现或都
   不出现。所以比较器最后拿整行兜底，构成严格全序。

   缺值（NULL / 空串）恒垫底，与 cnido_sort_txt() 在 SQL 侧的约定一致。

   与 SQL 侧唯一的行为差异：这里 $dir 连并列键一起反向（SQL 侧的并列键恒升序）。两边
   都是全序、翻页都不会重复漏行，只是 desc 时并列段的次序不同 —— 并列段本来就无所谓
   次序，不值得为它多写一段代码。 */
function cnido_sort_rows(&$rows, $idx, $cur, $dir, $tie = array())
{
    if (!isset($idx[$cur])) { return; }
    $chain = array($cur);
    foreach ((array)$tie as $t) {
        if ($t !== $cur && isset($idx[$t])) { $chain[] = $t; }
    }
    $sign = ($dir === 'desc') ? -1 : 1;
    usort($rows, function ($a, $b) use ($chain, $idx, $sign) {
        foreach ($chain as $k) {
            $i = $idx[$k];
            $x = isset($a[$i]) ? (string)$a[$i] : '';
            $y = isset($b[$i]) ? (string)$b[$i] : '';
            $mx = ($x === '');
            $my = ($y === '');
            if ($mx !== $my) { return $mx ? 1 : -1; }
            /* 库里的排序规则是 utf8mb4_0900_ai_ci（大小写不敏感），strcasecmp 更贴近。 */
            $c = strcasecmp($x, $y);
            if ($c !== 0) { return $sign * $c; }
        }
        return $sign * strcmp(
            implode("\x1f", array_map('strval', $a)),
            implode("\x1f", array_map('strval', $b))
        );
    });
}

/* 把当前排序并进页面已有的「翻页/筛选」查询串。默认列且升序时不附加（URL 干净）。 */
function cnido_sort_qs($sort, $dir, $keys, $default, $p = '')
{
    if ($sort === $default && $dir === 'asc') {
        return '';
    }
    return urlencode($p . 'sort') . '=' . urlencode($sort)
         . '&' . urlencode($p . 'dir') . '=' . urlencode($dir);
}
