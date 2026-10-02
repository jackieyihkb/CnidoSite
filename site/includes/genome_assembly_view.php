<?php
/**
 * genome_assembly_view.php —— data_statistics.php 上「Genome Assemblies」卡片的渲染。
 *
 * 数据来自 genome_assembly 表（由 includes/genome_assembly_refresh.php 从 NCBI
 * Datasets API 灌入，536 行 / 320 物种）。这一块要回答的是 speciesinfo 答不了的问题：
 * 一个物种在 NCBI 上有几个组装版本，本站实际用的是哪一个。
 *
 * 一个物种画一行：左边是本站用的那个组装，右边列出 NCBI 上的其余版本。
 * 「本站用哪个」永远是列而不是需要比较出来的结论 —— 多版本物种最容易看错的就是这里。
 *
 * ── URL 参数为什么带 g 前缀 ───────────────────────────────────────────
 * 同一页上面的「覆盖矩阵」卡片已经占用了 page / q / per_page / class / mod /
 * min / sort 这一组参数（它就是在 data_statistics.php 上翻页的）。本表若直接用
 * page / q，翻本表的页会把矩阵的筛选悄悄清掉，反之亦然。所以本表一律用
 * gp（页码）/ gq（搜索词）/ gpp（每页行数），并且生成链接时把 $_GET 里其余
 * 参数原样带上。
 *
 * 与 includes/coverage_matrix_view.php 一样，渲染函数与页面分离。
 */

/* 分页表的服务端排序表头（sort_head.php）。排序参数是 gsort/gdir，理由同上 ——
   本页已有的那一组参数全带前缀。 */
require_once __DIR__ . '/sort_head.php';

if (!function_exists('cnido_ga_css')) {

/** 卡片额外样式。列宽由 <th width> 当建议宽度给（gridtable 现在是 table-layout:auto，
    与 core 的 table.cc 一致；auto 下 width 仍是「优先宽度」，所以这些百分比照旧管用）。 */
function cnido_ga_css()
{
    /* 全表统一字号：任何一格都不许自己放大缩小（用户明确要求）。gridtable 是
   table.gridtable (0,1,1)，这里写 table.gridtable.ga-tbl (0,2,1) 压过它。 */
    /* 第 2 列「本站用的组装」不加任何专属高亮，与其余列一样只吃斑马纹。
   历史：原来有 border-left:4px solid #16a34a 的左侧色条 + 浅绿底；色条落在第 1、2 列
   之间像一条贯穿全表的竖线，2026-09-28 用户要求去掉，随后又要求底色也别隔行不一样，
   于是连 td.ga-used 这个类一起删了。**别再给这格加底色**：斑马纹是 (0,2,4)，
   比它弱的选择器会被压掉，加了只会得到隔行深浅不一的效果。 */
    /* 对齐：表格默认居中，但这两列不行 ——
   第 2 列「本站用的组装」是号 + 说明，
   第 6 列「NCBI 上的其余版本」一个版本一行、每行带悬挂缩进（.ga-ver 的
   text-indent:-1.1em），居中之后悬挂缩进会整体错位。
   其余四列（学名、组装名、水平、大小）都是短值，保持居中。
   写成 nth-child 而不是逐格加类：列数固定且表头是函数生成的，加类要改签名。 */
    /* 拉丁学名一律斜体（学名是拉丁文，按惯例排斜体）；用户要求第一列不再出现短码 */
    /* 「NCBI 上的其余版本」：一个版本一个块（= 一行），块之间靠外边距分开。
   悬挂缩进是为了万一一行的内容太长折了行：折下来的部分会缩进，一眼能看出它
   还是同一个版本的续行，不会被误读成「又多了一个版本」。 */
    /* 「这一版比本站用的那一版组装水平更高」的标记。被标的一行在下面附一句说明：
   本站的基因模型是建在第二列那一版上的，换到更高水平的版本可能没有完整注释。 */
    return <<<CSS
<style>
 
table.gridtable.ga-tbl th,
table.gridtable.ga-tbl td { font-size:15px; line-height:1.55; }

 

 
table.gridtable.ga-tbl th:nth-child(2),
table.gridtable.ga-tbl td:nth-child(2),
table.gridtable.ga-tbl th:nth-child(6),
table.gridtable.ga-tbl td:nth-child(6) { text-align:left; }

 
.ga-latin { font-style:italic; }
.ga-acc   { font-family:"DejaVu Sans Mono",Consolas,monospace; }
.ga-other { line-height:1.55; }
.ga-none, .ga-warn { color:#b45309; }

 
.ga-ver { padding-left:1.1em; text-indent:-1.1em; }
.ga-ver + .ga-ver { margin-top:2px; }
.ga-meta { color:#475569; }

 
.ga-better {
    display:inline-block; background:#fffbeb; color:#92400e; border:1px solid #fde68a;
    font-weight:700; padding:0 5px; border-radius:8px; margin-left:4px;
}
.ga-better-note {
    display:block; margin-top:6px; padding:6px 8px; background:#fffbeb;
    border-left:3px solid #f59e0b; color:#92400e; text-align:left;
}
</style>
CSS;
}

/** 字节 → Mb（NCBI 报的是碱基数，按 1e6 算，不是 1MiB）。 */
function cnido_ga_mb($bytes)
{
    if ($bytes === null || $bytes === '' || !is_numeric($bytes) || $bytes <= 0) {
        return null;
    }
    $mb = $bytes / 1000000;
    return ($mb >= 1000 ? number_format($mb, 0) : number_format($mb, 1)) . ' Mb';
}

/** 组装水平：NCBI 给的是 Chromosome / Scaffold / Contig / Complete Genome。 */
function cnido_ga_level($lv)
{
    $lv = trim((string) $lv);
    return $lv === '' ? '&mdash;' : htmlspecialchars($lv, ENT_QUOTES, 'UTF-8');
}

/**
 * 组装水平的高低排序，用来判断「NCBI 上那一版是不是比本站用的这一版更好」。
 *
 * NCBI 自己的等级就是 Complete Genome > Chromosome > Scaffold > Contig，
 * 这是**唯一客观、可核对**的判据，所以只按它判，不猜别的（更新时间、测序平台之类
 * 都推不出「更好」）。判到就明说，判不到就不说。
 */
function cnido_ga_rank($lv)
{
    switch (strtolower(trim((string) $lv))) {
        case 'complete genome': return 4;
        case 'chromosome':      return 3;
        case 'scaffold':        return 2;
        case 'contig':          return 1;
    }
    return 0;
}

/**
 * 生成本表的链接：在 $_GET 的基础上覆盖若干参数。
 * 传 null 或不传表示「删掉这个参数」。其余页面的参数（覆盖矩阵那一组）原样保留。
 *
 * @param array  $over   要覆盖/删除的参数
 * @param string $anchor 末尾要挂的片段（宿主页那张卡片的 id，如 '#genome-assemblies'）。
 *                       不带的话，翻页/换每页条数都会把页面甩回最顶部。
 */
function cnido_ga_url($over = array(), $anchor = '')
{
    $keep = $_GET;
    foreach ($over as $k => $v) {
        if ($v === null || $v === '') {
            unset($keep[$k]);
        } else {
            $keep[$k] = $v;
        }
    }
    return 'data_statistics.php' . ($keep ? '?' . http_build_query($keep) : '') . $anchor;
}

/** 搜索框里要把「非本表」的参数以 hidden 带过去，否则提交一次就把上面的矩阵筛选清空了。 */
function cnido_ga_hidden()
{
    $h = '';
    foreach ($_GET as $k => $v) {
        if ($k === 'gp' || $k === 'gq' || $k === 'gpp' || is_array($v)) {
            continue;
        }
        $h .= "<input type='hidden' name='" . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . "' value='"
            . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . "' />";
    }
    return $h;
}

/**
 * 画整张卡片的内容（不含 .stats-card 外壳与标题，由页面提供）。
 *
 * @param mysqli $conn
 * @param array  $opts  per_page 默认值 / per_page_choices / anchor（宿主页卡片的 id）
 * @return string HTML
 */
function cnido_ga_html($conn, $opts = array())
{
    if (!$conn || $conn->connect_error) {
        return "<p style='color:#c00;'>The genome assembly catalogue is temporarily unavailable "
             . "(the database could not be reached). Please try again later.</p>";
    }

    $h = function ($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    };

    /* ---------------------------------------------------------- 参数 */
    $ppDefault = isset($opts['per_page']) ? (int) $opts['per_page'] : 50;
    $ppChoices = isset($opts['per_page_choices']) ? (array) $opts['per_page_choices'] : array(25, 50, 100, 200);

    /* 卡片在宿主页上的锚点。搜索/翻页/换每页条数都是整页跳转，不带锚点就会被浏览器
       甩回页面最顶部（用户 2026-09-26 报的问题）。所有链接与表单都挂上它。 */
    $anchor = isset($opts['anchor']) ? (string) $opts['anchor'] : '';
    $act    = 'data_statistics.php' . $anchor;

    $q       = isset($_GET['gq'])  ? trim((string) $_GET['gq']) : '';
    $perPage = isset($_GET['gpp']) ? (int) $_GET['gpp'] : $ppDefault;
    if (!in_array($perPage, $ppChoices, true)) {
        $perPage = $ppDefault;
    }
    $page = isset($_GET['gp']) ? max(1, (int) $_GET['gp']) : 1;

    /* ---------------------------------------------------------- 表头排序
       这张表一页 50 个**物种**（一个物种的全部组装跟在它后面），所以排序是物种层面
       的，必须做在分页子查询里 —— 在浏览器里排一页 50 行是错的。
       参数前缀用 g（gsort/gdir），因为 data_statistics.php 上还有第二张分页表
       （基因文献表用 l），两张表共用一个 $_GET['sort'] 会互相串台。
       键 'default' 故意不对应任何一列：页面原来的次序是 ORDER BY abbr1（物种短码），
       而表里印的是拉丁学名，两者次序并不一样（实测 320 个物种不等价）。所以初始
       状态让所有箭头都是未选中的 ↕，而不是让 Species 那一列的箭头谎称现在是按它排的。

       每个键都是「物种层面的聚合值」：本站用的那一版组装在 is_site_used=1 的那一行
       上（实测每个 abbr1 至多一行），MAX(CASE WHEN is_site_used=1 THEN x END) 就是
       那一行的值。 */
    $lvExpr = "MAX(CASE WHEN is_site_used = 1 THEN assembly_level END)";
    $gsortKeys = array(
        'default'   => 'abbr1',
        'species'   => 'MAX(species)',
        'accession' => cnido_sort_txt("MAX(CASE WHEN is_site_used = 1 THEN accession END)"),
        'name'      => cnido_sort_txt("MAX(CASE WHEN is_site_used = 1 THEN assembly_name END)"),
        /* 组装水平是有高低次序的（Complete Genome > Chromosome > Scaffold > Contig，
           与 cnido_ga_rank() 一致），按字母排没意义，改成 FIELD() 给的序号。
           第一段把「没有本站用的那一版」和「水平值不认识」都算缺值，恒垫底。 */
        'level'     => array(
            "($lvExpr IS NULL OR FIELD($lvExpr, 'Contig','Scaffold','Chromosome','Complete Genome') = 0)",
            "FIELD($lvExpr, 'Contig','Scaffold','Chromosome','Complete Genome')",
        ),
        'size'      => cnido_sort_num("MAX(CASE WHEN is_site_used = 1 THEN total_sequence_length END)"),
        /* 最后一列印的是「NCBI 上还有几个别的版本」，所以按版本数排。 */
        'versions'  => 'COUNT(*)',
    );
    list($gsort, $gdir, $gorder) = cnido_sort_state($gsortKeys, 'default', 'abbr1', 'g');

    /* ---------------------------------------------------------- 全表概览
       这一段不受搜索影响：它描述的是「这个目录整体有多大」，
       而下面的分页条描述的是「当前筛选出多少」。两者口径不同，分开说。 */
    $ov = @$conn->query(
        "SELECT COUNT(*) AS n_sp, SUM(n) AS n_asm, SUM(n > 1) AS n_multi,
                SUM(used = 0) AS n_nosite, SUM(supp) AS n_supp
           FROM (SELECT abbr1, COUNT(*) n, SUM(is_site_used) used,
                        SUM(assembly_status = 'suppressed' AND is_site_used = 1) supp
                   FROM genome_assembly GROUP BY abbr1) t"
    );
    if (!$ov) {
        /* 表还没建（脚本没跑过）时不要把 SQL 错误甩给访客 */
        return "<p style='color:#c00;'>The genome assembly catalogue is not available in this release.</p>";
    }
    $O = $ov->fetch_assoc();
    $nSp     = (int) $O['n_sp'];
    $nAsm    = (int) $O['n_asm'];
    $nMulti  = (int) $O['n_multi'];
    $nNoSite = (int) $O['n_nosite'];
    $nSuppr  = (int) $O['n_supp'];

    if ($nSp === 0) {
        return "<p style='color:#c00;'>The genome assembly catalogue is empty in this release.</p>";
    }

    /* ---------------------------------------------------------- 当前页
       先在物种层面筛选 + 分页，再把这个物种的全部组装取回来 —— 分页必须落在
       物种上，否则一个多版本物种会被拆到两页去。 */
    $where = '';
    if ($q !== '') {
        $like  = "'%" . $conn->real_escape_string($q) . "%'";
        $where = "WHERE (abbr1 LIKE $like OR species LIKE $like)";
    }

    $cnt = @$conn->query("SELECT COUNT(*) c FROM (SELECT abbr1 FROM genome_assembly
                            $where GROUP BY abbr1) t");
    if (!$cnt) {
        return "<p style='color:#c00;'>The genome assembly catalogue is not available in this release.</p>";
    }
    $total = (int) $cnt->fetch_assoc()['c'];
    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;

    /* 本页要显示哪几个物种、按什么次序 —— 由排序键决定，所以单独查一遍（$gorder
       是页面写死的白名单表达式，$perPage/$offset 都转过整数）。不能靠上面那句
       JOIN 顺带带出次序：JOIN 出来的行序由外层的 ORDER BY 决定，和子查询的
       ORDER BY 没有关系，MySQL 也不保证派生表保持原序。 */
    $qSel = @$conn->query(
        "SELECT abbr1 FROM genome_assembly $where GROUP BY abbr1
          ORDER BY $gorder LIMIT $perPage OFFSET $offset"
    );
    if (!$qSel) {
        return "<p style='color:#c00;'>The genome assembly catalogue is not available in this release.</p>";
    }
    $pageSpecies = array();
    while ($r = $qSel->fetch_row()) {
        $pageSpecies[] = (string) $r[0];
    }

    $bySpecies = array();
    if ($pageSpecies) {
        /* abbr1 是 varchar(64)，来自数据库但仍旧转义一遍再进 IN —— 拼接标识符/字面量
           的地方一律不留「它本来就是安全的」这种假设。 */
        $in = "'" . implode("','", array_map(array($conn, 'real_escape_string'), $pageSpecies)) . "'";
        $q2 = @$conn->query(
            "SELECT g.abbr1, g.species, g.accession, g.assembly_name, g.assembly_level,
                    g.assembly_status, g.assembly_type, g.total_sequence_length,
                    g.is_site_used, g.site_source
               FROM genome_assembly g
              WHERE g.abbr1 IN ($in)
              ORDER BY g.abbr1, g.is_site_used DESC, g.total_sequence_length DESC, g.accession"
        );
        if (!$q2) {
            return "<p style='color:#c00;'>The genome assembly catalogue is not available in this release.</p>";
        }
        /* 按物种聚合（同一物种的行连续，顺序由上面 ORDER BY 保证） */
        while ($r = $q2->fetch_assoc()) {
            $bySpecies[$r['abbr1']][] = $r;
        }
        /* 再把物种按排序键的次序摆好：IN 出来的行序是 abbr1 的次序。 */
        $pos = array_flip($pageSpecies);
        uksort($bySpecies, function ($a, $b) use ($pos) {
            return $pos[$a] - $pos[$b];
        });
    }

    /* ---------------------------------------------------------- 说明 */
    $out  = "<p class='paleo-intro' style='margin-top:0'>"
          . "Assembly level, size and accession for every cnidarian genome NCBI holds for this database's "
          . "species (from the NCBI Datasets API). The version <b>CnidoSite actually uses</b> is the column "
          . "headed <i>Assembly used by this site</i>; the other versions at NCBI are listed to its right, "
          . "one per line. Accessions link to their NCBI record."
          . "</p>";

    $out .= "<p class='paleo-intro' style='font-size:16px;color:#475569;margin:10px 0 14px'>"
          . "Species with an assembly record: <b>" . number_format($nSp) . "</b> &middot; "
          . "<b>" . number_format($nAsm) . "</b> assemblies &middot; "
          . "<b>" . number_format($nMulti) . "</b> species with more than one version"
          . ($nNoSite > 0 ? " &middot; <span class='ga-warn'>" . $nNoSite . " species whose recorded assembly "
              . "could not be matched to an NCBI record</span>" : "")
          . ($nSuppr > 0 ? " &middot; <span class='ga-warn'>" . $nSuppr . " of the assemblies in use are "
              . "marked <b>suppressed</b> at NCBI</span>" : "")
          . ".</p>";

    /* ---------------------------------------------------------- 搜索框 */
    $out .= "<form class='cnido-search' method='get' action='" . $h($act) . "'>"
          . cnido_ga_hidden()
          . "<input type='text' name='gq' value='" . $h($q) . "' "
          . "placeholder='Search by Latin name (e.g. Acropora, Hydra)' />"
          . "<button type='submit'>Search</button>"
          . ($q !== '' ? "<a class='clear' href='" . $h(cnido_ga_url(array('gq' => null, 'gp' => null), $anchor))
              . "'>clear</a>" : "")
          . "</form>";

    if ($total === 0) {
        $out .= "<p class='paleo-intro' style='color:#b45309'>No species match <b>" . $h($q) . "</b>.</p>";
        return $out;
    }

    /* ---------------------------------------------------------- 表 */
    $out .= "<table class=\"gridtable ga-tbl\">";
    /* 表头六列都是服务端排序的链接（gsort/gdir）。不带 gp —— 点了排序列就回到第 1 页，
       否则排完还停在第 5 页，看到的是新次序的中段。$tail 挂卡片锚点，不然整页跳转会
       被浏览器甩回页面最顶部。 */
    $sortTh = function ($key, $label) use ($gsort, $gdir, $anchor) {
        return cnido_sort_link($key, $label, $gsort, $gdir, '', 'g', $anchor);
    };
    $out .= "<tr>"
          . "<th width=\"17%\">" . $sortTh('species', 'Species') . "</th>"
          . "<th width=\"16%\">" . $sortTh('accession', 'Assembly used by this site') . "</th>"
          . "<th width=\"16%\">" . $sortTh('name', 'Assembly name') . "</th>"
          . "<th width=\"9%\">"  . $sortTh('level', 'Level') . "</th>"
          . "<th width=\"8%\">"  . $sortTh('size', 'Size') . "</th>"
          . "<th width=\"34%\">" . $sortTh('versions', 'Other versions at NCBI') . "</th>"
          . "</tr>";

    foreach ($bySpecies as $abbr1 => $rows) {
        $used   = null;
        $others = array();
        foreach ($rows as $r) {
            if ($used === null && (int) $r['is_site_used'] === 1) {
                $used = $r;
            } else {
                $others[] = $r;
            }
        }

        $latin = trim((string) $rows[0]['species']);

        /* 第一列：拉丁学名（斜体，不用短码）+ 物种详情页链接，两行、居中。
           短码（HVULG 这种）对访客没有意义，所以第一行只印学名；万一学名缺了，
           退回短码，至少不空着。
           链接指向 speciesinfo.php（About / Basic Information / Genome Assembly
           Information / References + 该物种的数据清单），不再指向 species_portal.php。 */
        $abbrCell = "<span class='ga-latin'>" . $h($latin !== '' ? $latin : $abbr1) . "</span>"
                  . "<br /><a href='./speciesinfo.php?species=" . urlencode($abbr1) . "'>species info</a>";

        /* 第二列：本站用的组装。不要「THIS SITE」徽章 —— 整格已经有浅绿底和左侧
           色条了，徽章是重复信息；用户要求这一列只居中显示 accession。 */
        if ($used === null) {
            $usedCell = "<span class='ga-none'>not matched to an NCBI record</span>"
                      . "<br />no accession NCBI returns for this species matches the assembly "
                      . "recorded here";
            $nameCell = "&mdash;";
            $lvlCell  = "&mdash;";
            $sizeCell = "&mdash;";
            $usedRank = 0;
        } else {
            $usedCell = "<span class='ga-acc'>"
                      . "<a href='https://www.ncbi.nlm.nih.gov/datasets/genome/"
                      . urlencode($used['accession']) . "/' target='_blank' rel='noopener noreferrer'>"
                      . $h($used['accession']) . "</a></span>";
            if ((string) $used['site_source'] === 'name') {
                /* accession 对不上、靠组装名认出来的 —— 说出来，别让读者以为是精确匹配 */
                $usedCell .= "<br /><span class='ga-warn'>matched by assembly name, not accession</span>";
            }
            if (strtolower((string) $used['assembly_status']) === 'suppressed') {
                $usedCell .= "<br /><span class='ga-warn'>NCBI status: suppressed</span>";
            }
            $nameCell = $h($used['assembly_name'] !== null && $used['assembly_name'] !== ''
                         ? $used['assembly_name'] : '—');
            $lvlCell  = cnido_ga_level($used['assembly_level']);
            $sz       = cnido_ga_mb($used['total_sequence_length']);
            $sizeCell = $sz !== null ? $sz : '&mdash;';
            $usedRank = cnido_ga_rank($used['assembly_level']);
        }

        /* 第六列：NCBI 上的其余版本。**一个版本一个 .ga-ver 块（= 一行）**，块的先后
           沿用 SQL 的排序（本站用的那版排头，其余按序列长度降序），不在显示层重排。
           比本站用的这一版**组装水平更高**的，行首打一个 HIGHER 徽章；整格末尾用一句话
           把徽章的含义说掉（更高水平＝没有完整基因注释），不再逐条解释什么是组装水平。 */
        if (!$others) {
            $otherCell = "<span class='ga-latin'>none &mdash; this is the only assembly at NCBI</span>";
        } else {
            $parts   = array();
            $nBetter = 0;
            foreach ($others as $o) {
                $lv   = trim((string) $o['assembly_level']);
                $sz   = cnido_ga_mb($o['total_sequence_length']);
                $bits = array();
                if ($lv !== '')   { $bits[] = $h($lv); }
                if ($sz !== null) { $bits[] = $sz; }
                /* 类型只在不是普通单倍型时才写 —— 否则几百行都写着 haploid，纯噪音 */
                $ty = strtolower((string) $o['assembly_type']);
                if ($ty !== '' && $ty !== 'haploid') {
                    $bits[] = $h(str_replace('_', ' ', $o['assembly_type']));
                }
                /* 徽章放在这一行的**最前面**：格宽 503px，版本行偶尔要折行，徽章若缀在
                   行尾会被折到下一行开头，看上去像是下一版的东西（实测 ASPAT 就是这样）。 */
                $line = '';
                $high = ($used !== null && cnido_ga_rank($o['assembly_level']) > $usedRank);
                if ($high) {
                    /* 徽章只写 HIGHER，不写 HIGHER LEVEL：格宽 498px，只有带徽章的行才
                       恰好放不下（实测 ASPAT 这类 510px），而徽章长 6 个字符就正好把整行
                       挤到第二行 —— 「一个版本一行」就断了。含义交给下面那句说明去定义。 */
                    $line .= "<span class='ga-better'>Higher</span> ";
                    $nBetter++;
                }
                $line .= "<a href='https://www.ncbi.nlm.nih.gov/datasets/genome/"
                      . urlencode($o['accession']) . "/' target='_blank' rel='noopener noreferrer' "
                      . "class='ga-acc'>" . $h($o['accession']) . "</a>";
                if ($bits) {
                    /* 括号换成 ·：一行里三段信息（水平 · 大小 · 类型），括号在等宽字体的
                       长串里看着像accession 的一部分。 */
                    $line .= " <span class='ga-meta'>" . implode(' &middot; ', $bits) . "</span>";
                }
                $parts[] = "<div class='ga-ver'>" . $line . "</div>";
            }
            $otherCell = "<div class='ga-other'>" . implode('', $parts) . "</div>";
            if ($nBetter > 0) {
                /* 一句话说清徽章的含义就够了：更高组装水平的版本没有完整基因注释，
                   所以本站用的是下面第二列那一版。原先这里写了四句（什么是组装水平、
                   为什么换过去要重新注释、注释可能不全），读者要的是结论不是推导。 */
                $otherCell .= "<div class='ga-better-note'>"
                            . "<b>HIGHER</b> = higher assembly level than the version in use, "
                            . "and no full gene annotation is available for it.</div>";
            }
        }

        $out .= "<tr align='center'>"
              . "<td>" . $abbrCell . "</td>"
              . "<td>" . $usedCell . "</td>"
              . "<td>" . $nameCell . "</td>"
              . "<td>" . $lvlCell . "</td>"
              . "<td>" . $sizeCell . "</td>"
              . "<td align='left'>" . $otherCell . "</td>"
              . "</tr>";
    }
    $out .= "</table>";

    /* ---------------------------------------------------------- 分页 */
    $from = $offset + 1;
    $to   = $offset + count($bySpecies);
    /* 一页里的物种数可能少于 perPage（筛选后正好取完），所以用实际取到的行数算 to */

    $out .= "<div class='pagination-container'>";
    $out .= "<div class='pagination-info'>"
          . "<div class='total-records'>" . number_format($total) . " species"
          . ($q !== '' ? " matching &ldquo;" . $h($q) . "&rdquo;" : "")
          . " &middot; showing " . number_format($from) . "&ndash;" . number_format($to) . "</div>"
          . "<div class='per-page-selector'><label for='gpp'>Rows per page</label>"
          . "<select id='gpp' onchange=\"location.href=this.value\">";
    foreach ($ppChoices as $c) {
        $u = cnido_ga_url(array('gpp' => $c, 'gp' => null), $anchor);
        $out .= "<option value='" . $h($u) . "'" . ($c === $perPage ? " selected='selected'" : "")
              . ">" . $c . "</option>";
    }
    $out .= "</select></div></div>";

    if ($pages > 1) {
        $out .= "<div class='pagination-nav'>";
        /* 首/末页的 Prev/Next 传 null（=从 URL 里删掉），不要传 0 或 pages+1 ——
           那两个值虽然按钮是 disabled 点不到，但链接仍然会被抓取和收录。 */
        $out .= "<a class='page-btn" . ($page <= 1 ? " disabled" : "") . "' href='"
              . $h(cnido_ga_url(array('gp' => $page > 1 ? $page - 1 : null), $anchor)) . "'>&laquo; Prev</a>";
        $win = 2;
        if ($page - $win > 1) {
            $out .= "<a class='page-btn' href='" . $h(cnido_ga_url(array('gp' => 1), $anchor)) . "'>1</a>";
            if ($page - $win > 2) {
                $out .= "<span class='page-btn disabled'>&hellip;</span>";
            }
        }
        for ($i = max(1, $page - $win); $i <= min($pages, $page + $win); $i++) {
            $out .= "<a class='page-btn" . ($i === $page ? " active" : "") . "' href='"
                  . $h(cnido_ga_url(array('gp' => $i), $anchor)) . "'>" . $i . "</a>";
        }
        if ($page + $win < $pages) {
            if ($page + $win < $pages - 1) {
                $out .= "<span class='page-btn disabled'>&hellip;</span>";
            }
            $out .= "<a class='page-btn' href='" . $h(cnido_ga_url(array('gp' => $pages), $anchor)) . "'>"
                  . $pages . "</a>";
        }
        $out .= "<a class='page-btn" . ($page >= $pages ? " disabled" : "") . "' href='"
              . $h(cnido_ga_url(array('gp' => $page < $pages ? $page + 1 : null), $anchor)) . "'>Next &raquo;</a>";
        $out .= "</div>";

        $out .= "<div class='go-to-page'>"
              . "<form method='get' action='" . $h($act) . "' style='display:flex;align-items:center;gap:10px;margin:0'>"
              . cnido_ga_hidden()
              . ($q !== '' ? "<input type='hidden' name='gq' value='" . $h($q) . "' />" : "")
              . "<input type='hidden' name='gpp' value='" . $perPage . "' />"
              . "<label for='gpgo'>Go to page</label>"
              . "<input id='gpgo' type='number' name='gp' min='1' max='" . $pages . "' value='" . $page . "' />"
              . "<button type='submit'>Go</button>"
              . "<span style='font-size:15px;color:#64748b'>of " . $pages . "</span>"
              . "</form></div>";
    }
    $out .= "</div>";

    return $out;
}

}   /* function_exists */
