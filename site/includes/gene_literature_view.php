<?php
/**
 * gene_literature_view.php —— data_statistics.php 上「Gene Literature」卡片的渲染。
 *
 * 数据来自 gene_literature / literature 两张表，由 includes/gene_literature_refresh.php
 * 从 NCBI gene2pubmed 灌入。这一块要回答的是：某个基因（按 NCBI 基因号或基因名查）
 * 在文献里被研究过没有，是哪几篇。
 *
 * ── 三态显示 ──────────────────────────────────────────────────────
 *   site_gene 有值   → 同时给出站点基因号，可点进 gene_detail.php
 *   site_gene 为空   → 只有 NCBI 的 symbol，注明「未映射到本站基因号」
 *   map_source = no_locus_table → 该物种在站点上根本没有 <ABBR>_locus 表
 * 映射不上绝不猜、也不隐藏 —— 行照样列出来，只是把「没映上」这件事说出来。
 * 实测映不上的主因是注释来源不同（例如 HVIRI 站点用 BRAKER 基因号，
 * NCBI 给的是 LOC 号，两者没有任何名字上的联系）。
 *
 * ── 每个基因印三个号 ───────────────────────────────────────────────
 *   `LOC5500864 (TRPM2) · XP_032225133.2`
 *   基因号（站点基因模型的身份） + 名字（site_name，见 gene_name_map.php） +
 *   代表蛋白号（site_mrna）。蛋白号必须印，因为 gene_detail.php 的注释面板全部按
 *   蛋白号查：拿 locus 基因号进详情页，NR / UniProt / Pfam / InterPro / GO / KEGG /
 *   表达 / 表观 / 网络一律是空的（实测 NVECT 的 LOC5500864），换成它的蛋白号就全有。
 *   所以蛋白号既是显示项，也是这一格里真正「点得进去」的那个链接。
 *   三样东西任何一个拿不到就少印一样，不猜、不补。
 *
 * ── URL 参数 ─────────────────────────────────────────────────────
 * lq（搜索词）/ lp（页码）/ lpp（每页行数）/ lsp（物种）/ lann（是否含注释来源记录）。
 * 用 l 前缀是因为同页的覆盖矩阵占了 page/q/per_page、上面的基因组表占了 gp/gq/gpp。
 */

if (!function_exists('cnido_gl_css')) {

/* 概览缓存要落到 tmp/（CLI 与 web 唯一共用的可写目录）。别的模块通常已经 require
   过它，但这里不依赖调用方的加载顺序。 */
require_once __DIR__ . '/cache.php';

/* 只为 cnido_gene_name_is_junk()：NCBI 的 symbol 里也有 "unnamed protein product"
   这种占位名，判定必须和灌 site_name 时用的是同一份规则，不能各写一份。 */
require_once __DIR__ . '/gene_name_map.php';

/** 卡片额外样式。 */
function cnido_gl_css()
{
    /* 全表统一字号。gridtable 是 table.gridtable (0,1,1)，这里写
   table.gridtable.gl-tbl (0,2,1) 压过它，格子里的东西不再各自定字号。 */
    /* 对齐：表格默认居中，但这两列不行 ——
   第 1 列 Publication 是「标题 + 出处小字」两行，第 3 列 Genes 是一串基因 token，
   居中之后都会变成参差的居中块。第 2 列 Species 只有一排短学名，保持居中。 */
    /* 学名一律斜体（学名是拉丁文，按惯例排斜体） */
    /* 基因格里每个基因一个 token，靠 inline-block 自然换行（该列左对齐，见上）。
   token 内部**允许换行**：加了蛋白号之后一个 token 变成
   「LOC5500864 (TRPM2) · XP_032225133.2」，整体 nowrap 会把这一列撑得过宽。
   改成基因号 / 名字 / 蛋白号各自 nowrap —— 每个号本身不可断，
   但蛋白号可以整体挪到下一行。 */
    /* 代表蛋白号：同一个 token 里第二个号，用青色 + 单类名 a:link 凑平特异性压过
   .gl-gene a（两者都是 (0,1,1)，靠 .gl-prot a:link 的 (0,2,1) 才稳赢）。
   写得比基因号细，是因为基因号才是这个基因的身份，蛋白号只是「进详情页的钥匙」。 */
    /* 一篇论文挂到多个物种时，基因列里给每个物种一行小标题（学名 + 该物种的基因）。
   必须是 display:block —— 小标题自己独占一行，它前后的基因各自另起一行；
   早先用的「块级 0 高换行标记 + inline 小标题」把小标题挤在前一组基因同一行的末尾，
   读起来像是某个基因的名字。 */
    /* 徽章：数据集级论文与注释来源论文，一眼要能分辨，否则会误读论文的规模 */
    return <<<CSS
<style>
 
table.gridtable.gl-tbl th,
table.gridtable.gl-tbl td { font-size:15px; line-height:1.6; }

 
table.gridtable.gl-tbl th:nth-child(1),
table.gridtable.gl-tbl td:nth-child(1),
table.gridtable.gl-tbl th:nth-child(3),
table.gridtable.gl-tbl td:nth-child(3) { text-align:left; }

 
.gl-latin { font-style:italic; font-weight:600; }

.gl-title { color:#1e293b; font-weight:600; }
.gl-meta  { color:#64748b; }
.gl-nomap { color:#94a3b8; }            /* 映不上站点基因号的，灰掉但不隐藏 */
.gl-nm    { color:#64748b; }            /* 基因名，跟在站点基因号后面的括号里 */
.gl-sep   { color:#cbd5e1; }            /* 基因号与它的蛋白号之间的分隔点 */

 
.gl-gene { display:inline-block; margin:0 7px 5px 0; white-space:normal; }
.gl-gene > a, .gl-nm, .gl-prot, .gl-nomap, .gl-more { white-space:nowrap; }
.gl-gene a { font-family:"DejaVu Sans Mono",Consolas,monospace; font-weight:700; }
.gl-gene-plain { color:#94a3b8; font-family:"DejaVu Sans Mono",Consolas,monospace; }

 
.gl-prot a:link, .gl-prot a:visited { color:#0f766e; font-weight:600; text-decoration:none; }
.gl-prot a:hover { text-decoration:underline; }
.gl-more a { font-weight:700; }

 
.gl-sphead { display:block; font-style:italic; font-weight:600;
             color:#334155; margin:3px 0 2px; }
.gl-sphead a:link, .gl-sphead a:visited { color:#334155; text-decoration:none; }
.gl-sphead a:hover { text-decoration:underline; }

 
.gl-wide { display:inline-block; background:#fef3c7; color:#92400e; border:1px solid #fde68a;
           font-size:12px; font-weight:700; padding:0 5px; border-radius:8px; margin-left:4px; }
.gl-ann  { display:inline-block; background:#e0e7ff; color:#3730a3; font-size:12px;
           font-weight:700; padding:0 5px; border-radius:8px; margin-left:4px; }
.gl-chk  { display:flex; align-items:center; gap:7px; color:#475569; }
.gl-note { color:#475569; }
.gl-hint { color:#92400e; background:#fffbeb; border-left:4px solid #f59e0b;
           padding:10px 14px; margin:0 0 14px; }
</style>
CSS;
}

/**
 * 本表的链接生成器：在 $_GET 上覆盖若干参数，null/'' 表示删除，其余页面的参数保留。
 *
 * @param array  $over   要覆盖/删除的参数
 * @param string $anchor 末尾要挂的片段（宿主页那张卡片的 id，如 '#gene-literature'）。
 *                       不带的话，翻页/换每页条数都会把页面甩回最顶部。
 */
function cnido_gl_url($over = array(), $anchor = '')
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

/** 搜索表单里把非本表的参数以 hidden 带过去，否则一提交就把别的卡片的筛选清空了。 */
function cnido_gl_hidden($skip = array('lq', 'lp', 'lpp', 'lsp', 'lann', 'lexp'))
{
    $h = '';
    foreach ($_GET as $k => $v) {
        if (in_array($k, $skip, true) || is_array($v)) {
            continue;
        }
        $h .= "<input type='hidden' name='" . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . "' value='"
            . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . "' />";
    }
    return $h;
}

/**
 * 把用户输入的一个词解析成一组 NCBI GeneID。
 *
 * 先精确匹配（gene_id / symbol / site_gene / site_name / site_mrna / locus_tag 各有
 * 索引，用 UNION 让每个分支各走各的索引），一个都没命中再退化成前缀匹配（基因名常常
 * 记不全）。蛋白号（XP_…、KXJ…）也是可搜的 —— 用户手上常常只有蛋白号，而详情页正是
 * 按蛋白号查的。
 * 不要写成 `WHERE a=.. OR b=.. OR c=..` —— 那样 OR 会让 MySQL 放弃索引全表扫 60 万行。
 *
 * @return array 命中的 GeneID 列表（已去重，最多 $lim 个）
 */
function cnido_gl_resolve($conn, $q, $lim = 500, $hasName = true, $hasMrna = true)
{
    $e = $conn->real_escape_string($q);
    $mk = function ($where) use ($conn, $lim) {
        $q = @$conn->query("SELECT DISTINCT gene_id FROM gene_literature WHERE $where LIMIT $lim");
        $out = array();
        if ($q) {
            while ($r = $q->fetch_row()) {
                $out[] = $r[0];
            }
        }
        return $out;
    };
    $ids = $mk("gene_id   = '$e'");
    if (!$ids) { $ids = $mk("symbol    = '$e'"); }
    if (!$ids) { $ids = $mk("site_gene = '$e'"); }
    if (!$ids && $hasName) { $ids = $mk("site_name = '$e'"); }
    if (!$ids && $hasMrna) { $ids = $mk("site_mrna = '$e'"); }
    if (!$ids) { $ids = $mk("locus_tag = '$e'"); }
    if (!$ids && $hasName) { $ids = $mk("site_name LIKE '$e%'"); }
    if (!$ids) { $ids = $mk("symbol    LIKE '$e%'"); }
    if (!$ids) { $ids = $mk("site_gene LIKE '$e%'"); }
    if (!$ids && $hasMrna) { $ids = $mk("site_mrna LIKE '$e%'"); }
    if (!$ids) { $ids = $mk("gene_id   LIKE '$e%'"); }
    return $ids;
}

/**
 * 概览数字（总行数 / 非注释行数 / 文献数 / 基因数 / 物种数）。
 *
 * 这几个数要 COUNT(DISTINCT …) 扫全表 60 万行，实测 0.85 秒 —— 而它们只在灌库
 * 时才变。所以按本站既有做法缓存到 tmp/（CLI 与 web 唯一共用的可写目录，见
 * includes/cache.php 的长注释）：这里读、gene_literature_refresh.php 换表后删。
 * 缓存不在或过期就现算一次，最坏退化成原来那么慢，不会出错。
 */
function cnido_gl_overview($conn)
{
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    $ttl  = 86400;
    $path = function_exists('cnido_cache_path') ? cnido_cache_path('gene_lit_overview.json') : '';
    if ($path !== '' && is_file($path)) {
        $j = json_decode((string) @file_get_contents($path), true);
        /* r_gene 也要求存在：早先版本写下的缓存没有这一项，读进来会得到空字符串 */
        if (is_array($j) && isset($j['built'], $j['n_all'], $j['r_gene'])
            && (time() - (int) $j['built']) < $ttl) {
            $memo = $j;
            return $memo;
        }
    }
    /* 两套数都要：默认视图隐藏注释来源记录，就不能拿「全表的基因数」去配
       「筛选后的文献数」—— 那样会报出 399,179 个基因，而其中绝大多数只在
       InterPro 那篇里出现过一次。CASE WHEN 让两套数来自同一次全表扫描。 */
    $O = @$conn->query(
        "SELECT COUNT(*) AS n_all, SUM(is_annotation_method = 0) AS n_res,
                COUNT(DISTINCT pmid) AS n_pm, COUNT(DISTINCT gene_id) AS n_gene,
                COUNT(DISTINCT abbr1) AS n_sp,
                COUNT(DISTINCT CASE WHEN is_annotation_method = 0 THEN pmid  END) AS r_pm,
                COUNT(DISTINCT CASE WHEN is_annotation_method = 0 THEN gene_id END) AS r_gene,
                COUNT(DISTINCT CASE WHEN is_annotation_method = 0 THEN abbr1 END) AS r_sp
           FROM gene_literature"
    );
    if (!$O) {
        return null;
    }
    $j = $O->fetch_assoc();
    $j['built'] = time();
    if ($path !== '') {
        $tmp = $path . '.tmp.' . getmypid();
        @file_put_contents($tmp, json_encode($j));
        @rename($tmp, $path);   // 先写临时文件再 rename，避免半截 JSON 被读到
    }
    $memo = $j;
    return $memo;
}

/**
 * 画整张卡片的内容（不含 .stats-card 外壳与标题，由页面提供）。
 *
 * **一行 = 一篇文献。** 一篇文献挂多个基因，所以「Genes」那一格列的是这一页每篇
 * 论文涉及到的基因：能映到站点基因模型的就是 `<站点基因号>（<基因名>）`，站点
 * 基因号可点进 gene_detail.php；映不上的照样列出来并标成灰色，绝不猜、不隐藏。
 * 一页一次把需要的基因查回来再按 pmid 分组，不要每篇论文查一次。
 *
 * @param mysqli $conn
 * @param array  $opts  per_page / per_page_choices / gene_cap
 * @return string HTML
 */
function cnido_gl_html($conn, $opts = array())
{
    if (!$conn || $conn->connect_error) {
        return "<p style='color:#c00;'>The gene literature index is temporarily unavailable "
             . "(the database could not be reached). Please try again later.</p>";
    }
    $h = function ($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    };
    $esc = function ($s) use ($conn) {
        return $conn->real_escape_string((string) $s);
    };

    $ppDefault = isset($opts['per_page']) ? (int) $opts['per_page'] : 50;
    $ppChoices = isset($opts['per_page_choices']) ? (array) $opts['per_page_choices'] : array(25, 50, 100, 200);
    /* 一篇论文最多先列这么多基因，其余折叠。数据集级论文能挂一万多个基因，
       不封顶的话一格就是几 MB 的链接，页面直接没法看。 */
    $geneCap = isset($opts['gene_cap']) ? (int) $opts['gene_cap'] : 40;

    /* 卡片在宿主页上的锚点。搜索/翻页/换每页条数都是整页跳转，不带锚点就会被浏览器
       甩回页面最顶部（用户 2026-09-26 报的问题）。所有链接与表单都挂上它。 */
    $anchor = isset($opts['anchor']) ? (string) $opts['anchor'] : '';
    $act    = 'data_statistics.php' . $anchor;

    $q    = isset($_GET['lq'])  ? trim((string) $_GET['lq']) : '';
    $lsp  = isset($_GET['lsp']) ? trim((string) $_GET['lsp']) : '';
    $lann = (isset($_GET['lann']) && $_GET['lann'] === '1') ? 1 : 0;
    $lexp = isset($_GET['lexp']) ? trim((string) $_GET['lexp']) : '';
    if ($lexp !== '' && !preg_match('/^\d{1,12}$/', $lexp)) {
        $lexp = '';          // PMID 只可能是数字，别把它拼进 SQL
    }

    $perPage = isset($_GET['lpp']) ? (int) $_GET['lpp'] : $ppDefault;
    if (!in_array($perPage, $ppChoices, true)) {
        $perPage = $ppDefault;
    }
    $page = isset($_GET['lp']) ? max(1, (int) $_GET['lp']) : 1;

    /* 表还没建时不要抛 SQL 错 */
    $chk = @$conn->query("SELECT 1 FROM literature LIMIT 1");
    if (!$chk) {
        return "<p style='color:#c00;'>The gene literature index is not available in this release.</p>";
    }

    /* site_name（站点自己的基因名）/ site_mrna（代表蛋白号）是后加的列，由
       includes/gene_name_backfill.php 灌。先各探测一次：列还没加上时页面照常工作，
       只是不显示那一项 —— 否则这个页面与灌库脚本的部署顺序就成了隐式的强依赖，
       先上线页面就整张卡片挂掉。 */
    $hasName = false;
    $hasMrna = false;
    $cq = @$conn->query("SELECT column_name FROM information_schema.columns
                          WHERE table_schema = DATABASE() AND table_name = 'gene_literature'
                            AND column_name IN ('site_name', 'site_mrna')");
    if ($cq) {
        while ($cr = $cq->fetch_row()) {
            if ($cr[0] === 'site_name') { $hasName = true; }
            if ($cr[0] === 'site_mrna') { $hasMrna = true; }
        }
    }

    /* ---------------------------------------------------------- 概览（不受筛选影响） */
    $ov = cnido_gl_overview($conn);
    if ($ov === null) {
        return "<p style='color:#c00;'>The gene literature index is not available in this release.</p>";
    }

    /* ---------------------------------------------------------- 物种下拉（只列有真实文献的） */
    $spList = array();
    $qs = @$conn->query("SELECT abbr1, MAX(species) sp FROM gene_literature
                          WHERE is_annotation_method = 0 GROUP BY abbr1 ORDER BY abbr1");
    if ($qs) {
        while ($r = $qs->fetch_assoc()) {
            $spList[$r['abbr1']] = $r['sp'];
        }
    }

    /* ---------------------------------------------------------- 说明 */
    $out  = "<p class='paleo-intro' style='margin-top:0'>"
          . "Which papers study which genes? Associations come from NCBI's <b>gene2pubmed</b> index, "
          . "restricted to this database's species. <b>One row is one paper</b>; the rightmost column lists "
          . "its linked genes, each given as the <b>CnidoSite gene ID</b>, the gene name in brackets, and "
          . "&mdash; after a dot &mdash; the <b>protein ID</b>. <b>The gene name is this database's own</b> "
          . "&mdash; the symbol on the UniProt record (or the NCBI-NR protein description) that the "
          . "annotation matched the gene to, so it names the closest annotated relative rather than an "
          . "official name for the species. A placeholder description (an &ldquo;unnamed protein "
          . "product&rdquo;, say) counts as no name: the ID is then shown on its own, <b>never invented or "
          . "left empty</b>. A gene that could not be matched to a gene model here shows the NCBI name only, "
          . "in grey &mdash; <b>stated, not guessed</b>."
          . "<br /><span class='gl-meta'>The protein ID is the one to follow for the full picture: the gene "
          . "detail page presents its annotation (NR, UniProt, Pfam, InterPro, GO, KEGG, expression, "
          . "epigenome, network) under that ID. Search by gene ID, gene name, protein ID, paper title or "
          . "PMID.</span>"
          . "</p>";

    /* 两套数各配各的：默认视图用 r_*（不含注释来源），勾上 lann 才用全表数。
       混着用会报出「41,013 条链接覆盖 399,179 个基因」这种自相矛盾的句子。 */
    $kLinks = $lann ? 'n_all' : 'n_res';
    $kPm    = $lann ? 'n_pm'  : 'r_pm';
    $kGene  = $lann ? 'n_gene': 'r_gene';
    $kSp    = $lann ? 'n_sp'  : 'r_sp';
    $nHide  = (int) $ov['n_all'] - (int) $ov['n_res'];

    $out .= "<p class='paleo-intro' style='color:#475569;margin:10px 0 14px'>"
          . "<b>" . number_format((int) $ov[$kPm]) . "</b> papers, linked to "
          . "<b>" . number_format((int) $ov[$kGene]) . "</b> genes in "
          . "<b>" . number_format((int) $ov[$kSp]) . "</b> species &mdash; "
          . "<b>" . number_format((int) $ov[$kLinks]) . "</b> gene&ndash;paper links in total"
          . ($lann
              ? ". <b>Including</b> the " . number_format($nHide)
                . " records that point at annotation-provenance papers (InterPro / TreeGrafter)."
              : ". A further " . number_format($nHide)
                . " links point at <b>annotation-provenance</b> papers rather than gene studies and are "
                . "hidden &mdash; tick the box below to include them.")
          . "</p>";

    /* ---------------------------------------------------------- 搜索 / 筛选 */
    $out .= "<form class='cnido-search' method='get' action='" . $h($act) . "'>"
          . cnido_gl_hidden()
          . "<input type='text' name='lq' value='" . $h($q) . "' "
          . "placeholder='Gene ID, name or protein ID, paper title, or PMID (e.g. LOC116601119, wnt3, XP_032225133.2, hym-346)' />"
          . "<select name='lsp' style='padding:9px 12px;border:2px solid #e2e8f0;border-radius:8px;'>"
          . "<option value=''>All species</option>";
    foreach ($spList as $a => $sp) {
        /* 只显示 gene_literature.species 里的学名，不要退回 $a。$a 是 abbr1，
           本表里有 10 个物种（Anemonia_viridis、Briareum_asbestinum、Pocillopora_grandis
           等）存的 abbr1 就是「下划线版学名」，而 species 列才是带空格的正确写法。
           原先的条件是 strcasecmp(str_replace(' ','_',$sp), $a) !== 0 时用 $sp ——
           对多数物种两者不同（$a 是 NVECT 这类短码）所以碰巧对，可这 10 个物种恰好
           「只差下划线和空格」，判定相等，于是落到 $a 分支，下拉框里就并排出现
           "Anemonia viridis" 和 "Anemonia_viridis" 两种写法。value 仍然是 $a（筛选
           用的就是这个键），只有显示文本改用学名。 */
        $latin = ($sp !== '') ? $sp : $a;
        $out .= "<option value='" . $h($a) . "'" . ($a === $lsp ? " selected='selected'" : "") . ">"
              . $h($latin) . "</option>";
    }
    $out .= "</select>"
          . "<button type='submit'>Search</button>"
          . ($q !== '' || $lsp !== '' || $lann || $lexp !== ''
              ? "<a class='clear' href='" . $h(cnido_gl_url(array('lq' => null, 'lsp' => null,
                    'lann' => null, 'lp' => null, 'lexp' => null), $anchor)) . "'>clear all</a>" : "")
          . "<label class='gl-chk' style='margin-left:8px'>"
          . "<input type='checkbox' name='lann' value='1'" . ($lann ? " checked='checked'" : "")
          . " onchange='this.form.submit()' /> include annotation-provenance records</label>"
          . "</form>";

    /* ---------------------------------------------------------- 解析搜索词
       搜不到基因名不再直接返回 —— 还要拿它去比论文标题和 PMID。 */
    $idFilter = array();
    if ($q !== '') {
        $idFilter = cnido_gl_resolve($conn, $q, 500, $hasName, $hasMrna);
    }

    /* 筛选条件写成构造函数而不是一个数组：下面那句「命中都被默认过滤掉了」的提示
       需要**同一套条件、只把注释来源那一层去掉**再数一遍。早先是用字符串匹配把
       含 'is_annotation_method' 的条目删掉，结果把基因 EXISTS 子查询整个删了 ——
       提示于是报出全表 327 篇，而不是那个基因真正挂的那 1 篇。 */
    $buildWhere = function ($ann) use ($esc, $lsp, $lexp, $q, $idFilter) {
        $sub = $ann ? '' : " AND gx.is_annotation_method = 0";
        $w = array();
        if (!$ann) { $w[] = "l.is_annotation_method = 0"; }
        if ($lsp !== '') {
            $w[] = "EXISTS (SELECT 1 FROM gene_literature gx WHERE gx.pmid = l.pmid
                        AND gx.abbr1 = '" . $esc($lsp) . "'" . $sub . ")";
        }
        if ($lexp !== '') {
            $w[] = "l.pmid = '" . $esc($lexp) . "'";
        }
        if ($q !== '') {
            $or = array();
            if ($idFilter) {
                $or[] = "EXISTS (SELECT 1 FROM gene_literature gx WHERE gx.pmid = l.pmid
                            AND gx.gene_id IN ('"
                      . implode("','", array_map($esc, $idFilter)) . "')" . $sub . ")";
            }
            $or[] = "l.title LIKE '%" . $esc($q) . "%'";
            $or[] = "l.pmid = '" . $esc($q) . "'";
            $w[] = '(' . implode(' OR ', $or) . ')';
        }
        return $w;
    };
    $where = $buildWhere($lann);
    $W = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $cnt = @$conn->query("SELECT COUNT(*) c FROM literature l $W");
    if (!$cnt) {
        return "<p style='color:#c00;'>The gene literature index is not available in this release.</p>";
    }
    $total = (int) $cnt->fetch_assoc()['c'];

    /* 搜到的基因，文献全被默认过滤掉了 —— 只显示「0 papers」会让人以为这个基因没有
       文献。真实存在：LOC116601119 唯一的挂靠文献就是 InterPro 那篇。 */
    $hintShown = false;
    if ($total === 0 && ($idFilter || $lsp !== '') && !$lann) {
        $w2 = $buildWhere(1);          // 同一套条件，只把注释来源那一层拿掉
        $W2 = $w2 ? ('WHERE ' . implode(' AND ', $w2)) : '';
        $c2 = @$conn->query("SELECT COUNT(*) c FROM literature l $W2");
        if ($c2 && ($n2 = (int) $c2->fetch_assoc()['c']) > 0) {
            $out .= "<p class='gl-hint'>"
                  . ($idFilter
                        ? "This gene appears in <b>" . number_format($n2) . "</b> paper"
                          . ($n2 === 1 ? '' : 's') . ", but "
                          . ($n2 === 1 ? 'it points' : 'they all point')
                        : "<b>" . $h($lsp) . "</b> appears in <b>" . number_format($n2) . "</b> paper"
                          . ($n2 === 1 ? '' : 's') . ", but they all point")
                  . " at annotation-provenance records (InterPro / TreeGrafter), which are hidden by default. "
                  . "<a href='" . $h(cnido_gl_url(array('lann' => '1', 'lp' => null), $anchor)) . "'>Show them</a>.</p>";
            $hintShown = true;
        }
    }
    /* 一个基因都没命中，但标题/PMID 命中了 —— 说清楚命中的是标题，不是基因 */
    if ($q !== '' && !$idFilter && $total > 0) {
        $out .= "<p class='gl-hint'>No gene in the index carries the ID or name <b>" . $h($q) . "</b>; "
              . "the papers below match on title or PMID instead. (A gene only has a name here where this "
              . "database's annotation recorded one, so a gene that is genuinely unstudied may still be "
              . "listed under its ID alone.)</p>";
    }

    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;

    /* ---------------------------------------------------------- 排序
       参数前缀用 l（lsort / ldir）：同一页上还有覆盖矩阵和基因组装配两张表，各自
       都要有自己的命名空间（见本文件开头那句注释）。
       现在的次序「年份倒序、同年按基因数倒序、再按 pmid」不对应表头里的任何一列
       （表头给的是 Publication / Species / Genes 三列），所以它当一个**不在表头里**
       的隐藏默认项：初始状态还是原来的次序、三个箭头全是未选中的 ↕，用户点了哪列
       才真正按那列排 —— 而不是让 Publication 的箭头谎称「现在是按我排的」。
       l.pmid 是主键，任何一档配上它都是全序，翻页不会重复或漏行。 */
    require_once __DIR__ . '/sort_head.php';
    $glSortKeys = array(
        'default' => 'l.pub_year DESC, l.n_genes DESC, l.pmid',
        'pub'     => cnido_sort_num('l.pub_year'),
        'species' => 'l.n_species',
        'genes'   => 'l.n_genes',
    );
    list($glSort, $glDir, $glOrder) =
        cnido_sort_state($glSortKeys, 'default', array('l.pmid'), 'l');
    $glOrderSql = ($glOrder !== '' ? ' ORDER BY ' . $glOrder : '');

    /* ---------------------------------------------------------- 当前页（一行一篇文献） */
    $q2 = @$conn->query(
        "SELECT l.pmid, l.title, l.journal, l.pub_year, l.first_author,
                l.n_genes, l.n_species, l.is_annotation_method
           FROM literature l
           $W" . $glOrderSql . "
          LIMIT $perPage OFFSET $offset"
    );
    if (!$q2) {
        return "<p style='color:#c00;'>The gene literature index is not available in this release.</p>";
    }
    $list = array();
    while ($r = $q2->fetch_assoc()) {
        $list[] = $r;
    }
    $nShown = count($list);

    /* ---------------------------------------------------------- 这一页每篇论文挂的基因
       一次查回来再按 pmid → abbr1 分组，不要每篇查一次。排序把能映到站点基因号的
       排在前面 —— 那是用户真正点得进去的；映不上的也不丢，排在后面。 */
    $genes = array();
    if ($list) {
        $pm = array();
        foreach ($list as $r) { $pm[] = "'" . $esc($r['pmid']) . "'"; }
        $gq = @$conn->query(
            "SELECT pmid, abbr1, species, gene_id, symbol, site_gene, map_source, "
                    . ($hasName ? "site_name" : "NULL AS site_name") . ", "
                    . ($hasMrna ? "site_mrna" : "NULL AS site_mrna") . "
               FROM gene_literature
              WHERE pmid IN (" . implode(',', $pm) . ")"
              . ($lann ? '' : " AND is_annotation_method = 0") . "
              ORDER BY pmid, abbr1, (site_gene IS NULL), site_gene, symbol, gene_id"
        );
        if ($gq) {
            while ($g = $gq->fetch_assoc()) {
                $genes[$g['pmid']][$g['abbr1']][] = $g;
            }
        }
    }

    /* ---------------------------------------------------------- 表 */
    $out .= "<table class=\"gridtable gl-tbl\">";
    /* 表头链接的基准查询串：把 $_GET 整个带上（同一页还有覆盖矩阵与基因组装配两张表，
       不带就会把它们的筛选清掉），只摘掉 lsort / ldir（由 cnido_sort_link 自己加）与
       lp（换了排序就该回到第 1 页）。
       翻页与换每页条数的链接不用管：它们走 cnido_gl_url()，那个函数本来就把 $_GET
       整体带上，lsort / ldir 会自动跟着走。 */
    $glBase = $_GET;
    unset($glBase['lsort'], $glBase['ldir'], $glBase['lp']);
    $glBaseQs = http_build_query($glBase);   /* arg_separator.output = '&'，不是实体 */
    $out .= "<tr>"
          . "<th width=\"40%\">" . cnido_sort_link('pub', 'Publication', $glSort, $glDir, $glBaseQs, 'l', $anchor) . "</th>"
          . "<th width=\"18%\">" . cnido_sort_link('species', 'Species', $glSort, $glDir, $glBaseQs, 'l', $anchor) . "</th>"
          . "<th width=\"42%\">" . cnido_sort_link('genes', 'Genes', $glSort, $glDir, $glBaseQs, 'l', $anchor) . "</th>"
          . "</tr>";

    foreach ($list as $r) {
        $pmid = (string) $r['pmid'];

        /* --- 文献列 --- */
        $title = (string) $r['title'];
        $pub = "<span class='gl-title'>" . ($title !== '' ? $h($title) : '<i>title unavailable</i>')
             . "</span>";
        $bits = array();
        if ((string) $r['journal'] !== '')     { $bits[] = $h($r['journal']); }
        if ((string) $r['pub_year'] !== '')    { $bits[] = "<b>" . $h($r['pub_year']) . "</b>"; }
        if ((string) $r['first_author'] !== ''){ $bits[] = $h($r['first_author']) . ' et al.'; }
        $bits[] = "<a href='https://pubmed.ncbi.nlm.nih.gov/" . urlencode($pmid)
                . "/' target='_blank' rel='noopener noreferrer'>PMID " . $h($pmid) . "</a>";
        $pub .= "<br /><span class='gl-meta'>" . implode(' &middot; ', $bits) . "</span>";
        if ((int) $r['is_annotation_method'] === 1) {
            $pub .= "<span class='gl-ann'>Annotation source</span>";
        }
        if ((int) $r['n_genes'] >= 1000) {
            /* 一篇论文在一千多个基因上都出现，说明它是数据集级/注释级的论文，
               不是针对某一个基因的研究。这一点必须让读者一眼看到。 */
            $pub .= "<span class='gl-wide'>Dataset-wide</span>";
        }

        /* --- 物种列：学名（斜体）+ 该论文在这个物种下挂了多少个基因 --- */
        $gset   = isset($genes[$pmid]) ? $genes[$pmid] : array();
        $spCell = '';
        if (!$gset) {
            $spCell = "<span class='gl-meta'>&mdash;</span>";
        } else {
            $lines = array();
            foreach ($gset as $a => $glist) {
                $latin = '';
                foreach ($glist as $g) {
                    if (trim((string) $g['species']) !== '') { $latin = trim((string) $g['species']); break; }
                }
                if ($latin === '') { $latin = (string) $a; }
                $lines[] = "<span class='gl-latin'><a href='./speciesinfo.php?species=" . urlencode($a)
                         . "'>" . $h($latin) . "</a></span>"
                         . " <span class='gl-meta'>(" . number_format(count($glist))
                         . " gene" . (count($glist) === 1 ? '' : 's') . ")</span>";
            }
            $spCell = implode("<br />", $lines);
        }

        /* --- 基因列：站点基因号（基因名）---
           映到站点基因模型 → 站点基因号可点，后面跟上基因名；
           映不上          → 只印基因名，灰色，鼠标悬停说明为什么没有站点基因号。 */
        $cap   = ($lexp !== '' && $lexp === $pmid) ? PHP_INT_MAX : $geneCap;
        $toks  = array();
        $seen  = 0;
        $nGene = 0;
        foreach ($gset as $a => $glist) { $nGene += count($glist); }
        $multi = count($gset) > 1;

        foreach ($gset as $a => $glist) {
            if ($multi) {
                $latin = '';
                foreach ($glist as $g) {
                    if (trim((string) $g['species']) !== '') { $latin = trim((string) $g['species']); break; }
                }
                /* 小标题自己就是块级元素（.gl-sphead），独占一行，不需要额外的换行标记 */
                $toks[] = "<span class='gl-sphead'><a href='./speciesinfo.php?species=" . urlencode($a)
                        . "'>"
                        . $h($latin !== '' ? $latin : $a) . "</a></span>";
            }
            foreach ($glist as $g) {
                if ($seen >= $cap) { break 2; }
                $sg  = (string) $g['site_gene'];
                $nm  = (string) $g['symbol'];
                $sn  = (string) $g['site_name'];
                if ($sg !== '') {
                    $t = "<a href='./gene_detail.php?gene=" . urlencode($sg)
                       . "&amp;species=" . urlencode($a) . "'>"
                       . $h($sg) . "</a>";
                    /* 名字优先用站点自己的（注释表里的基因符号 / 蛋白描述），拿不到才退回
                       NCBI 的 symbol —— 多数物种的 symbol 就是基因号本身（LOC…），与基因号
                       相同时印出来是同一串字符写两遍，纯噪音。名字来自哪里要说清楚。
                       （title 里不能出现单引号：它本身是单引号包的属性，一个撇号就会截断它。
                       下面这句原来写的是 database's / species'，浏览器只当属性到那里为止。） */
                    if ($sn !== '' && $sn !== $sg) {
                        $t .= " <span class='gl-nm' title='gene name carried by the annotation of "
                            . "this database for this gene (closest annotated relative), not the "
                            . "official nomenclature of the species'>(" . $h($sn) . ")</span>";
                    } elseif ($nm !== '' && $nm !== $sg && !cnido_gene_name_is_junk($nm)) {
                        /* NCBI 的 symbol 里也有 "unnamed protein product" 这种占位名，
                           和注释来源的占位描述一样，印出来跟没印一样。 */
                        $t .= " <span class='gl-nm' title='NCBI gene symbol'>(" . $h($nm) . ")</span>";
                    }
                    /* 代表蛋白号。详情页的注释面板（NR / UniProt / Pfam / InterPro / GO /
                       KEGG / 表达 / 表观 / 网络）全部按蛋白号查，拿 locus 基因号去问是空的
                       （实测 NVECT 的 LOC5500864 整页都空，换成 XP_032225133.2 全有），
                       所以这个号必须露出来，而且点它才进得去有内容的那一页。
                       基因号与它去掉结尾 .N 后相同就不重复印（有些物种的 locus 号本来就是
                       转录本号）。 */
                    $mr = $hasMrna ? trim((string) $g['site_mrna']) : '';
                    if ($mr !== '' && $mr !== $sg && preg_replace('/\.\d+$/', '', $mr) !== $sg) {
                        $t .= " <span class='gl-sep'>&middot;</span>"
                            . " <span class='gl-prot'><a href='./gene_detail.php?gene=" . urlencode($mr)
                            . "&amp;species=" . urlencode($a) . "'"
                            . " title='protein record of this gene in this database (the longest of its "
                            . "transcripts where a gene has several). The gene detail page presents its "
                            . "annotation keyed by this ID, not by the gene ID.'>"
                            . $h($mr) . "</a></span>";
                    }
                } else {
                    $why = ((string) $g['map_source'] === 'no_locus_table')
                         ? 'this species has no gene-model table in this database'
                         : 'no CnidoSite gene ID could be matched to this NCBI gene';
                    /* 占位名（unnamed protein product…）不印，退回 NCBI GeneID */
                    $show = ($nm !== '' && !cnido_gene_name_is_junk($nm)) ? $nm : ('GeneID ' . $g['gene_id']);
                    $t = "<span class='gl-nomap' title='" . $h($why) . "'>" . $h($show) . "</span>";
                }
                $toks[] = $t;
                $seen++;
            }
        }

        $geneCell = '';
        if (!$toks) {
            $geneCell = "<span class='gl-meta'>&mdash;</span>";
        } else {
            $buf = array();
            foreach ($toks as $t) {
                /* 物种小标题（多物种时的分组头）自己带块级样式，不再套基因 token 的框 */
                if (strpos($t, "<span class='gl-sphead'") === 0) { $buf[] = $t; continue; }
                /* 基因号：能点进详情页的用 .gl-gene，映不上站点基因号的灰掉 */
                $buf[] = strpos($t, '<a ') === 0
                       ? "<span class='gl-gene'>" . $t . "</span>"
                       : "<span class='gl-gene gl-gene-plain'>" . $t . "</span>";
            }
            $geneCell = implode('', $buf);
            if ($seen < $nGene) {
                $geneCell .= "<span class='gl-gene gl-more'>"
                           . "<a href='" . $h(cnido_gl_url(array('lexp' => $pmid, 'lp' => null), $anchor)) . "'>"
                           . "show all " . number_format($nGene) . "</a> &mdash; "
                           . number_format($nGene - $seen) . " more</span>";
            } elseif ($lexp !== '' && $lexp === $pmid && $nGene > $geneCap) {
                $geneCell .= "<span class='gl-gene gl-more'>"
                           . "<a href='" . $h(cnido_gl_url(array('lexp' => null), $anchor)) . "'>collapse</a></span>";
            }
        }

        $out .= "<tr align='center'>"
              . "<td>" . $pub . "</td>"
              . "<td>" . $spCell . "</td>"
              . "<td>" . $geneCell . "</td>"
              . "</tr>";
    }
    $out .= "</table>";

    /* 上面那条琥珀色提示已经把「为什么是 0」说清楚了，这里别再叠一句泛泛的 No papers */
    if ($nShown === 0 && !$hintShown) {
        $out .= "<p class='paleo-intro' style='color:#b45309'>No papers match the current filters.</p>";
    }

    /* 物种映射与命名的覆盖情况：只在按物种筛选时给出，避免平时占地方。
       两件事要分开说：(1) 有些 NCBI 基因号在这个库的基因模型里根本找不到（映不上，
       连基因号都没有）；(2) 映上了的基因里只有一部分在注释表里有名字（GN= 符号或
       NR 描述）。把 (2) 说清楚，用户才不会以为「没名字 = 这个基因不存在」。 */
    if ($lsp !== '') {
        /* 两种「没有站点基因号」的写法都要认：老数据存的是空串，2026-09-26 起
           灌库写 NULL。只判 IS NULL 会让这段说明静默消失。 */
        $nm = @$conn->query("SELECT COUNT(DISTINCT gene_id) c FROM gene_literature
                              WHERE abbr1 = '" . $esc($lsp) . "'
                                AND (site_gene IS NULL OR site_gene = '')
                                AND map_source <> 'no_locus_table'");
        if ($nm && ($c = (int) $nm->fetch_assoc()['c']) > 0) {
            $out .= "<p class='gl-note' style='margin-top:10px'>"
                  . number_format($c) . " genes of <b>" . $h($lsp) . "</b> appear in the literature under an "
                  . "NCBI identifier that could not be matched to this database's gene models, so neither a "
                  . "CnidoSite gene ID nor a name can be shown for them. This reflects a difference in "
                  . "annotation source, not missing data.</p>";
        }
        if ($hasName || $hasMrna) {
            /* 两个 CASE 按列在不在拼 —— 少一列时那句 SQL 会整条报错，这段说明就静默没了 */
            $nsel = $hasName
                  ? "COUNT(DISTINCT CASE WHEN site_name IS NOT NULL AND site_name <> ''
                                         THEN site_gene END) n" : "0 n";
            $msel = $hasMrna
                  ? "COUNT(DISTINCT CASE WHEN site_mrna IS NOT NULL AND site_mrna <> ''
                                         THEN site_gene END) m" : "0 m";
            $nn = @$conn->query("SELECT COUNT(DISTINCT site_gene) g, $nsel, $msel
                                   FROM gene_literature
                                  WHERE abbr1 = '" . $esc($lsp) . "'
                                    AND site_gene IS NOT NULL AND site_gene <> ''");
            if ($nn && ($r2 = $nn->fetch_assoc()) && (int) $r2['g'] > 0) {
                $g2 = (int) $r2['g'];
                $n2 = (int) $r2['n'];
                $m2 = (int) $r2['m'];
                if ($hasMrna) {
                    /* 蛋白号是详情页真正认的号，所以先报它 —— 有名字没蛋白号的基因，
                       点进去反而是空页。 */
                    $out .= "<p class='gl-note' style='margin-top:6px'>Of the <b>" . number_format($g2)
                          . "</b> CnidoSite genes of <b>" . $h($lsp) . "</b> that appear in the literature, <b>"
                          . number_format($m2) . "</b> (" . round(100 * $m2 / $g2) . "%) could be tied to a "
                          . "protein record of this database and are listed with that protein ID as well as "
                          . "the gene ID &mdash; follow the protein ID for the annotation panels.</p>";
                }
                if ($hasName) {
                    $out .= "<p class='gl-note' style='margin-top:6px'>Of those same <b>" . number_format($g2)
                          . "</b> genes, <b>"
                          . number_format($n2) . "</b> (" . round(100 * $n2 / $g2) . "%) carry a name from this "
                          . "database's own annotation &mdash; the gene symbol of the closest annotated relative "
                          . "in UniProt, or failing that the protein description from NCBI-NR. The remaining "
                          . number_format($g2 - $n2) . " have no such annotation record and are listed by gene ID "
                          . "only; the name is not guessed.</p>";
                }
            }
        }
    }

    /* ---------------------------------------------------------- 分页 */
    $from = $offset + 1;
    $to   = $offset + $nShown;

    $out .= "<div class='pagination-container'>";
    $out .= "<div class='pagination-info'>"
          . "<div class='total-records'>" . number_format($total) . " paper" . ($total === 1 ? '' : 's')
          . " &middot; showing " . number_format($from) . "&ndash;" . number_format($to) . "</div>"
          . "<div class='per-page-selector'><label for='lpp'>Rows per page</label>"
          . "<select id='lpp' onchange=\"location.href=this.value\">";
    foreach ($ppChoices as $c) {
        $out .= "<option value='" . $h(cnido_gl_url(array('lpp' => $c, 'lp' => null), $anchor)) . "'"
              . ($c === $perPage ? " selected='selected'" : "") . ">" . $c . "</option>";
    }
    $out .= "</select></div></div>";

    if ($pages > 1) {
        $out .= "<div class='pagination-nav'>";
        /* 首/末页的 Prev/Next 传 null（=从 URL 里删掉），不要传 0 或 pages+1 ——
           那两个值虽然按钮是 disabled 点不到，但链接仍然会被抓取和收录。 */
        $out .= "<a class='page-btn" . ($page <= 1 ? " disabled" : "") . "' href='"
              . $h(cnido_gl_url(array('lp' => $page > 1 ? $page - 1 : null), $anchor)) . "'>&laquo; Prev</a>";
        $win = 2;
        if ($page - $win > 1) {
            $out .= "<a class='page-btn' href='" . $h(cnido_gl_url(array('lp' => 1), $anchor)) . "'>1</a>";
            if ($page - $win > 2) {
                $out .= "<span class='page-btn disabled'>&hellip;</span>";
            }
        }
        for ($i = max(1, $page - $win); $i <= min($pages, $page + $win); $i++) {
            $out .= "<a class='page-btn" . ($i === $page ? " active" : "") . "' href='"
                  . $h(cnido_gl_url(array('lp' => $i), $anchor)) . "'>" . $i . "</a>";
        }
        if ($page + $win < $pages) {
            if ($page + $win < $pages - 1) {
                $out .= "<span class='page-btn disabled'>&hellip;</span>";
            }
            $out .= "<a class='page-btn' href='" . $h(cnido_gl_url(array('lp' => $pages), $anchor)) . "'>"
                  . $pages . "</a>";
        }
        $out .= "<a class='page-btn" . ($page >= $pages ? " disabled" : "") . "' href='"
              . $h(cnido_gl_url(array('lp' => $page < $pages ? $page + 1 : null), $anchor)) . "'>Next &raquo;</a>";
        $out .= "</div>";

        $out .= "<div class='go-to-page'>"
              . "<form method='get' action='" . $h($act) . "' style='display:flex;align-items:center;gap:10px;margin:0'>"
              . cnido_gl_hidden()
              . ($q !== '' ? "<input type='hidden' name='lq' value='" . $h($q) . "' />" : "")
              . ($lsp !== '' ? "<input type='hidden' name='lsp' value='" . $h($lsp) . "' />" : "")
              . ($lann ? "<input type='hidden' name='lann' value='1' />" : "")
              . "<input type='hidden' name='lpp' value='" . $perPage . "' />"
              . "<label for='lpgo'>Go to page</label>"
              . "<input id='lpgo' type='number' name='lp' min='1' max='" . $pages . "' value='" . $page . "' />"
              . "<button type='submit'>Go</button>"
              . "<span style='color:#64748b'>of " . $pages . "</span>"
              . "</form></div>";
    }
    $out .= "</div>";

    return $out;
}

}   /* function_exists */
