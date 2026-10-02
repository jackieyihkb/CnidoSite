<?php
/**
 * gene_detail.php 上「这个基因还有哪些数据 / 能拿它做什么」四张面板共用的
 * 样式与小工具。
 *
 * 为什么单独一个文件：四张面板是四份独立实现（共表达网络、单细胞、表观修饰、
 * 分析工具入口），但它们在同一个页面上前后相邻，样式必须一致，否则会出现
 * 「每张卡片各自一种字体/边框」的老问题。这里只放 CSS 与三个取数小工具，
 * 不放任何一张面板的业务逻辑。
 *
 * 安装方式：面板文件自己 require 本文件，gene_detail.php 不需要单独引入。
 * CSS 由 cnido_gp_css() 输出，带 static 标记，四张面板同时在场时只出现一次。
 */

/* 复用 RNA-seq 面板已经调好的三件事：
     - cnido_rxs_h()            转义
     - cnido_rxs_conn()         取连接（失败返回 null，不抛）
     - cnido_rxs_abbr()         物种标识（拉丁名 / 下划线形式 / abbr1）-> abbr1 代码
     - cnido_rxs_table_exists() 表存在性探测
   这几个在 gene_detail.php 上必然已经加载（表达面板就在同一页），而且
   cnido_rxs_abbr() 的候选顺序、前缀兜底都是踩过坑改出来的，重写一份只会
   让两处慢慢漂开。 */
require_once __DIR__ . '/rnaseq_expression_panel.php';

if (!function_exists('cnido_gp_h')) {
    /** 文本/属性两用的转义。 */
    function cnido_gp_h($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cnido_gp_css')) {
    /**
     * 四张面板的公共样式。返回值自带 <style> 标签，只输出一次。
     *
     * 内联而不是挂到 templatemo_style.css：静态资源只有 Last-Modified、没有
     * Cache-Control，浏览器会按启发式规则缓存好几个小时，改了外部 CSS 用户
     * 可能根本看不到。配色与 rnaseq_expression_panel.php 的 .rxs-* 保持一致
     * （石板灰 + 蓝），两张卡片挨着才不像两个网站。
     */
    function cnido_gp_css()
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;
        /* ---------------------------------------------------------------------------
 * 卡片外壳：基因页上六个面板现在共用这一套。
 *
 * 从前有三种卡片 —— .gp-card（工具/共表达/单细胞/表观四张）、.rxs-panel（表达）、
 * .gpe-panel（蛋白组）：半径 10 与 12px 混用、内边距 14 与 16px 混用、标题一份
 * 700 字重两份不设、只有蛋白组那张带投影。它们在同一页上下相邻，读者看到的是
 * 三种卡片。现在外壳只在**这里**定义一次，另外两份已从各自的 render 里删掉。
 *
 * 为什么留内联而不用 templatemo_style.css：静态资源只有 Last-Modified、没有
 * Cache-Control，改外部 css 用户可能按启发式缓存好几小时看不到。内联在本文件
 * 里就要求它排在页面靠后 —— gene_detail.php 里表达/蛋白组面板先渲染、gp 面板
 * 后渲染，所以这些规则落在它们各自 <style> 的后面，特异性相同时也由本文件胜出。
 * ------------------------------------------------------------------------- */
        /* 三份实现里标题有 h3 也有 h4，一起管。 */
        /* 卡片副标题在三个文件里叫 .gp-sub / .rxs-sub / .gpe-sub，是同一个角色，
   原来一份 12px、两份 15px，统一到 13px：比正文小一档，但仍读得清。 */
        /* 面板里的表一律挂 gridtable：表头底色、行分隔线、斑马纹、悬停、链接配色、字号
   全部由 templatemo_style.css 的共用样式提供（与 core 的 table.cc 一致）。
   （这段注释一直写着「表本身挂 gridtable」，但五个面板的表格 markup 里只有
   class="gp-tbl"、gridtable 从来没挂上，于是这五张表既没有表头底色也没有行
   分隔线、字号还比全站表格大 1px。这次是把注释说的那件事真的做了。） */
        /* 面板里的表列少、几乎每列都是文字（"What it does"、"Most correlated partner"、
   "Tissue / stage"），挂上 gridtable 后这些列默认居中，所以整张表钉回左对齐；
   数字列再单独右对齐回来。
   .num 的选择器必须带上卡片类凑到 (0,3,3)：共用样式里是 table.gridtable tr td.num
   =(0,2,3)，而下面这条左对齐也是 (0,3,2)/(0,2,2) 量级，短写会输。 */
        /* 卡片里的说明段。原来 15px，比同位置的 .gp-sub（13px）还大，同一张卡里两段
   灰字一大一小。统一到 13px。 */
        /* ---------------------------------------------------------------------------
 * 「打开对应的组学视图」按钮。
 *
 * 每个组学面板底部有且只有一个，位置、配色、措辞一致 —— 读者学会一次就够了。
 * 原来这些链接各自埋在卡片最后一段灰字里（共表达那张写的是 "Open this gene in
 * Network Analysis →"，和它上下两段说明文字完全同款），读者根本看不出那是本页
 * 的出口。
 * ------------------------------------------------------------------------- */
        /* 选择器必须带 a —— templatemo_style.css 里的 `a:link` / `a:visited` 是 (0,1,1)，
   单类名的 .cn-open 只有 (0,1,0)，会被静默压掉：color:#fff 失效、按钮文字
   变成 a:link 的蓝色，蓝底蓝字，屏幕上就是一个没有字的空药丸。
   实测过：不带 a 前缀时按钮整块蓝色、一个字都看不见。 */
        /* 次要样式：给「点开要等一会儿」的出口用（DNA 甲基化那页是按基因全表扫描的），
   不要把它画得和即时跳转一样。 */
        /* 有数据但这次给不了直接出口时用的灰按钮（配一句说明，不假装可点）。 */
        /* 一个面板要给多个出口时（表观组有 ChIP / ATAC / DHS / 甲基化四页）排成一行。 */
        /* 组学分区标题 + 页内跳转条。 */
        /* 该组学模块这次没有数据：仍然给锚点（读者点得到「为什么没有」），但弱化。 */
        /* 跳转条同时当「一眼看全」的摘要用：每一条前面一个小圆点，绿=这份组学数据里
   有这个基因，灰=物种有这类数据但这个基因不在里面，无点=这次查不出来/不适用。
   只靠后面的灰字（"48 samples"）看不出「有我/没我」。 */
        /* 跳转目标不要贴在视口最上沿。 */
        return <<<'CSS'
<style type="text/css">
 
.gp-card,
.rxs-panel,
.gpe-panel{margin:16px 0 0;border:1px solid #e2e8f0;border-radius:12px;
  background:#fff;padding:16px 18px}

 
.gp-card > h3, .gp-card > h4,
.rxs-panel > h3, .rxs-panel > h4,
.gpe-panel > h3, .gpe-panel > h4{margin:0 0 4px;font-size:16px;color:#0f172a;
  font-weight:700;line-height:1.4}

 
.gp-card .gp-sub, .rxs-panel .rxs-sub, .gpe-panel .gpe-sub{
  font-size:13px;color:#64748b;margin:0 0 12px;line-height:1.7}
.gp-card .gp-sub code, .rxs-panel .rxs-sub code, .gpe-panel .gpe-sub code{
  background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:12px}

 
.gp-card table.gp-tbl,
.gpe-panel table.gpe-tbl{margin:0 0 10px;table-layout:auto}
.gp-card table.gp-tbl tr:last-child td,
.gpe-panel table.gpe-tbl tr:last-child td{border-bottom:0}

 
.gp-card table.gp-tbl tr th,
.gp-card table.gp-tbl tr td,
.gpe-panel table.gpe-tbl tr th,
.gpe-panel table.gpe-tbl tr td{text-align:left}
.gp-card table.gp-tbl tr th.num,
.gp-card table.gp-tbl tr td.num,
.gpe-panel table.gpe-tbl tr th.num,
.gpe-panel table.gpe-tbl tr td.num{text-align:right;white-space:nowrap}
.gp-pill{display:inline-block;padding:1px 7px;border-radius:9px;font-size:12px;font-weight:600;white-space:nowrap}
.gp-yes{background:#dcfce7;color:#166534}
.gp-no{background:#f1f5f9;color:#475569}
.gp-warn{background:#fef3c7;color:#92400e}
.gp-a{color:#1d4ed8;text-decoration:none}
.gp-a:hover{text-decoration:underline}

 
.gp-none{font-size:13px;color:#475569;margin:0;line-height:1.7}
.gp-bar{display:inline-block;height:8px;border-radius:4px;background:#1d4ed8;vertical-align:middle;min-width:1px}
.gp-bar.neg{background:#f97316}

 
 
a.cn-open{display:inline-block;margin:10px 0 0;padding:7px 14px;border-radius:8px;
  background:#1d4ed8;color:#fff;font-size:13px;font-weight:600;
  text-decoration:none;line-height:1.2;white-space:nowrap}
a.cn-open:hover{background:#1e40af;color:#fff;text-decoration:none}
 
a.cn-open.sec{background:#fff;color:#1d4ed8;border:1px solid #bfdbfe}
a.cn-open.sec:hover{background:#eff6ff;color:#1e40af}
 
a.cn-open.dis{background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0;cursor:default}
a.cn-open.dis:hover{background:#f1f5f9;color:#94a3b8}
 
.cn-openrow{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:10px 0 0}
.cn-openrow a.cn-open{margin:0}
.cn-openrow .cn-lbl{font-size:13px;color:#64748b;margin-right:2px}

 
.cn-sec{margin:30px 0 0;padding:0 0 8px;border-bottom:2px solid #e2e8f0;
  font-size:18px;font-weight:700;color:#0f172a;line-height:1.4}
.cn-sec-sub{margin:8px 0 0;font-size:13px;color:#64748b;line-height:1.7}
.cn-nav{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 0;padding:0;list-style:none}
.cn-nav a{display:inline-block;padding:6px 12px;border:1px solid #e2e8f0;border-radius:999px;
  background:#f8fafc;color:#334155;font-size:13px;text-decoration:none;white-space:nowrap}
.cn-nav a:hover{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
 
.cn-nav a.cn-off{color:#94a3b8;background:#fff}
.cn-nav a.cn-off:hover{background:#f8fafc;border-color:#e2e8f0;color:#64748b}
.cn-nav .n{color:#94a3b8;font-variant-numeric:tabular-nums}
 
.cn-dot{display:inline-block;width:7px;height:7px;border-radius:50%;
  margin-right:6px;vertical-align:1px;background:#cbd5e1}
.cn-dot-yes{background:#16a34a}
.cn-dot-no{background:#cbd5e1;box-shadow:inset 0 0 0 1px #94a3b8}
 
.cn-anchor{scroll-margin-top:14px}
</style>
CSS;
    }
}

if (!function_exists('cnido_gp_table_exists')) {
    /** information_schema 探测一张表是否存在（每次请求对同一张表只查一次）。 */
    function cnido_gp_table_exists($conn, $table)
    {
        static $cache = array();
        if ($conn === null || $table === '') {
            return false;
        }
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        $e = mysqli_real_escape_string($conn, $table);
        $q = @mysqli_query($conn, "SELECT 1 FROM information_schema.tables "
                                . "WHERE table_schema = DATABASE() AND table_name = '$e' LIMIT 1");
        return $cache[$table] = (bool)($q && mysqli_fetch_row($q));
    }
}

if (!function_exists('cnido_gp_abbr')) {
    /**
     * 物种标识 -> abbr1 代码（同时是全部表前缀）。取不到就返回 null，调用方
     * 静默退出 —— 拿不到前缀意味着后面每一张表都查不了。
     */
    function cnido_gp_abbr($species, $gene, $conn)
    {
        if ($conn === null) {
            return null;
        }
        list($abbr) = cnido_rxs_abbr($species, $gene, $conn);
        return ($abbr !== null && $abbr !== '') ? $abbr : null;
    }
}

if (!function_exists('cnido_gp_id_candidates')) {
    /**
     * 一个基因号在「按表查」时要试的写法。
     *
     * 站点里同一个基因至少有两种写法（注释表 `…15160.1`、_locus 表 `…15160`），
     * 共表达表用的是第三种：BRAKER 风格的 `OS493_001533-T1`。cnido_gene_id_forms()
     * 只管 `.1` 后缀，对 `-T1` 无能为力，所以在它后面补一个 `-T1` 候选。
     * 顺序即优先级：原样写法排在最前，命中即停。
     */
    function cnido_gp_id_candidates($gene)
    {
        $gene = trim((string)$gene);
        if ($gene === '') {
            return array();
        }
        $c = cnido_gene_id_forms($gene);
        if (substr($gene, -3) !== '-T1') {
            $c[] = $gene . '-T1';
        }
        return array_values(array_unique(array_filter($c, 'strlen')));
    }
}

if (!function_exists('cnido_gp_nav')) {
    /**
     * 组学分区跳转条的登记表。
     *
     * 为什么是「先渲染、后出条」：跳转条必须印在全部面板之前，但「这一节有哪些
     * 面板、各有多少条记录」只有把面板渲染完才知道 —— 表达面板在没有 TPM 矩阵时
     * 直接 return，单细胞面板在没有数据集时也只印一句话。所以 gene_detail.php
     * 把这一节 ob_start() 起来：面板渲染时调用 cnido_gp_nav_add() 登记自己，
     * 渲染完再把标题 + cnido_gp_nav_html() + 缓冲的内容一起输出。
     * 没登记的面板就不会出现在条上，跳转条因此不会指向空锚点。
     */
    function cnido_gp_nav_add($id, $label, $note = '', $state = '')
    {
        if (!isset($GLOBALS['cnido_gp_nav_items'])) { $GLOBALS['cnido_gp_nav_items'] = array(); }
        $GLOBALS['cnido_gp_nav_items'][$id] = array('label' => $label, 'note' => $note, 'state' => $state);
    }

    function cnido_gp_nav_dot($state)
    {
        /* title 是访客悬停时看到的文字，必须英文（原来这两条是中文，gene_detail
           整页会印出几十个中文 tooltip）。写法与本目录其它 title 一致：陈述句、
           首字母大写、句末不加句号。 */
        if ($state === 'yes') {
            return '<span class="cn-dot cn-dot-yes" title="This gene is present in this dataset"></span>';
        }
        if ($state === 'no') {
            return '<span class="cn-dot cn-dot-no" title="This species has this kind of data, but this gene is not in it"></span>';
        }
        return '';
    }

    function cnido_gp_nav_html()
    {
        $items = isset($GLOBALS['cnido_gp_nav_items']) ? $GLOBALS['cnido_gp_nav_items'] : array();
        if (!$items) { return ''; }
        $out = '<ul class="cn-nav">';
        foreach ($items as $id => $it) {
            $state = isset($it['state']) ? $it['state'] : '';
            $out .= '<li><a href="#' . cnido_gp_h($id) . '"'
                  . ($state === 'none' ? ' class="cn-off"' : '') . '>'
                  . cnido_gp_nav_dot($state) . $it['label'];
            if ($it['note'] !== '') {
                $out .= ' <span class="n">' . $it['note'] . '</span>';
            }
            $out .= '</a></li>';
        }
        return $out . '</ul>';
    }
}

if (!function_exists('cnido_gp_openbtn')) {
    /**
     * 「打开对应的组学视图」按钮。六个面板共用，保证措辞、配色、位置一致。
     *
     * @param string $url   目标地址（调用方自己 urlencode）
     * @param string $label 按钮文字（箭头由本函数补）
     * @param string $cls   '' | 'sec'（次要样式：点开要等的页面）| 'dis'（不可用）
     * @param string $title 悬停说明
     */
    function cnido_gp_openbtn($url, $label, $cls = '', $title = '')
    {
        /* 本站口径（jackie 2026-10-01）：站内页面跳转留当前窗口，站外与站内文件下载
           才开新窗口。这里六个调用点指的都是站内的分析页（ChIP/ATAC/DHS/甲基化、
           单细胞、蛋白组、共表达、转录组），所以不带 target。 */
        return '<a class="cn-open' . ($cls !== '' ? ' ' . $cls : '') . '" href="' . cnido_gp_h($url) . '"'
             . ($title !== '' ? ' title="' . cnido_gp_h($title) . '"' : '')
             . '>' . $label . ' &rarr;</a>';
    }
}

if (!function_exists('cnido_gp_card')) {
    /**
     * 卡片开 / 收。$extra 追加在标题后面（一般放状态小标签，已转义）。
     * $id 非空时给卡片一个锚点（页内跳转条用），并挂上 .cn-anchor 让滚动留点余量。
     */
    function cnido_gp_card_open($title, $sub = '', $extra = '', $id = '')
    {
        echo cnido_gp_css();
        echo '<div class="gp-card' . ($id !== '' ? ' cn-anchor' : '') . '"'
           . ($id !== '' ? ' id="' . cnido_gp_h($id) . '"' : '') . '>'
           . '<h4>' . $title . ($extra !== '' ? ' ' . $extra : '') . '</h4>';
        if ($sub !== '') {
            echo '<p class="gp-sub">' . $sub . '</p>';
        }
    }
    function cnido_gp_card_close()
    {
        echo '</div>';
    }
}
