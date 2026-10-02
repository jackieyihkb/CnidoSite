<?php
/**
 * sn_data.php -- the Single-cell Data index.
 *
 * Two changes against the previous version, both answering referees:
 *
 *  * an "Analyse" column linking each dataset into the interactive viewer
 *    (Referee 1: the atlas covered only 6 of the species listed here, and the
 *    single-cell data were presented as static images);
 *  * a "Quality control" column carrying each dataset's own filtering figures,
 *    and a link to the full per-dataset table (Referee 2, Major #3, who asked
 *    for cells before/after filtering, library type, mitochondrial
 *    distributions and doublet and integration procedures).
 *
 * Rows are matched to viewer datasets by their position on this page; see
 * sc_row_map() in sc_common.php for why.
 */
require_once __DIR__ . '/sc_common.php';
/* 搜索框用 includes/state.php 的 cnido_search_term()（全站列表页同一套写法：
   browse.php、MAGs.php、busco_result.php… 都是这三只函数）。**必须显式 require**：
   sc_common.php 不引 state.php，漏了这一行 php -l 不报错，而是在页面已经输出
   一半之后 "Call to undefined function" 500 —— 浏览器看到的是半张截断的页。 */
require_once __DIR__ . '/includes/state.php';

$conn = sc_conn();
$row_map = sc_row_map($conn);
$atlas = sc_atlas($conn);

/*
 * The site's shared menu links here as `?species=<abbr1>` (the "singlecell"
 * entry in includes/modlinks.php).  This page is the module's index of every
 * deposited dataset, so a species is a view of it and never a replacement: the
 * full list stays one click away, and a species the table has no row for falls
 * back to the whole table with a notice rather than to an empty one.
 */
$sp = sc_requested_species($conn);

/* 搜索词。归一化/截断统一走 state.php，写法与站内其它列表页一致。 */
$q = cnido_search_term(isset($_GET['q']) ? $_GET['q'] : '');
$qHtml = htmlspecialchars($q, ENT_QUOTES, 'UTF-8');

/* 搜索表单的目标，以及要带回表单里的当前视图。
   method="get" 的表单提交会**替换**整个查询串，所以物种视图必须以 hidden 字段
   带过去，否则一搜就退回全表。Clear 链接「只清搜索词、仍留在该物种视图里」靠的
   也是这一串。物种参数只由 sc_requested_species() 从 $_GET 读，没有会话状态，
   所以「不带 species=」就是干净的「不过滤物种」，不需要哨兵值。 */
$__self = 'sn_data.php';
$__speciesRaw = isset($_GET['species']) ? trim((string)$_GET['species']) : '';
$__viewQs = ($__speciesRaw !== '' ? 'species=' . urlencode($__speciesRaw) : '');

sc_header('Single-cell Data', 'sn_data.php',
  'Every single-cell RNA-seq dataset held in CnidoSite: source accession, tissue, stage and cell '
  . 'count, including the datasets that could not be re-processed and the reason why.');
?>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Single-cell Data</b></legend>
<p class="paleo-intro">This module lists the single-cell RNA-seq datasets held here.</p>

<?php
    // The dataset list comes from the pre-existing `singlecell` table.  If the
    // database is unreachable, say so rather than emitting a fatal error from
    // mysqli_query() below.
    if (!$conn) {
        echo "<div class=\"sc-empty\"><b>The database is not reachable.</b><br>"
           . "Check the connection settings in <code>sc_common.php</code>."
           . "</div>";
        sc_footer();
        return;
    }

    // Read the whole list first: whether the species filter yields any row has
    // to be known before the table starts printing, or the page would have to
    // retract an empty table it had already emitted.  The search box added
    // 2026-09-27 needs the same thing one step earlier still: whether the term
    // hits anything decides both what the notices say and whether a table is
    // printed at all.
    $rows_all = array();
    $res_all = mysqli_query($conn, "SELECT * from singlecell");
    if ($res_all) {
        while ($r_all = mysqli_fetch_row($res_all)) {
            $rows_all[] = $r_all;
        }
    }

    /* 拉丁名 -> abbr1，一次读完。这取代了下面打印循环里「每印一行查一次 abbr」
       的做法（33 行 = 33 次查询），语义相同：同一物种有多行时取第一行。
       它必须在过滤之前建好，因为搜索也要能按 abbr1 短码命中 —— 那是站内菜单
       自己发出去的写法（`sn_data.php?species=NVECT` / `cell_atlas.php?species=`），
       读者最可能照着敲的就是它。 */
    $abbr1_by_sp = array();
    $res_ab = mysqli_query($conn, "SELECT species, abbr1 FROM abbr");
    if ($res_ab) {
        while ($r_ab = mysqli_fetch_row($res_ab)) {
            $k = (string)$r_ab[0];
            if ($k !== '' && !isset($abbr1_by_sp[$k])) {
                $abbr1_by_sp[$k] = (string)$r_ab[1];
            }
        }
    }

    /* 这一行命中搜索词吗？用 stripos 而不是 SQL LIKE，是有意的：$row_no 必须对
       `singlecell` 表的**每一行**自增（sc_row_map() 按该行序把行映射到数据集
       id），过滤一旦写进 SQL 的 WHERE，活下来的行就被重新编号，整张表的
       Atlas / Markers / Full QC 链接会静默指向错的数据集。
       按字节比而不是按字符（stripos 只对 ASCII 做大小写折叠）：这里的物种名、
       登录号、阶段名本来就都是 ASCII。
       参与匹配的列：Phylum（显示为 Class）、Species、Project、Study、Emb、Stage、
       Ref。CellNumber 刻意不参与 —— 它是计数，子串匹配下搜「28」会把 1,280 和
       28,736 一视同仁地捞出来。 */
    $qHit = function ($row, $abbr1) use ($q) {
        if ($q === '') { return true; }
        foreach (array(0, 1, 2, 3, 4, 5, 7) as $c) {
            $v = isset($row[$c]) ? (string)$row[$c] : '';
            if ($v !== '' && stripos($v, $q) !== false) { return true; }
        }
        return ($abbr1 !== '' && stripos($abbr1, $q) !== false);
    };

    $n_sp = 0;        // 物种视图里有几行
    $hit_in_sp = 0;   // …其中命中搜索词的有几行
    $hit_all = 0;     // 全表里命中搜索词的行数（不看物种视图）
    foreach ($rows_all as $r_all) {
        $ab1 = isset($abbr1_by_sp[$r_all[1]]) ? $abbr1_by_sp[$r_all[1]] : '';
        $in_sp = ($sp !== null && sc_species_key($r_all[1]) === sc_species_key($sp['species']));
        $in_q = $qHit($r_all, $ab1);
        if ($in_sp) { $n_sp++; }
        if ($in_q) { $hit_all++; }
        if ($in_sp && $in_q) { $hit_in_sp++; }
    }
    $spNoRows = ($sp !== null && $n_sp === 0);
    $filterOn = ($sp !== null && !$spNoRows);

    /* 实际会印出来的命中行数。**必须分这两个数**：物种视图生效时，能印的只有
       该物种那一组；视图没生效时（没带 species、或带了但表里没有这个物种 ——
       后者本页的处理是回落到全表，见页首说明），能印的是全表。
       写成一个 $n_hit 并在循环里用 $in_sp 累加是错的：$sp === null 时 $in_sp
       恒为 false，于是「没带物种 + 搜索」永远算 0 命中 —— 表格被判成不印，
       而下面那个循环仍会照印各行，结果是几十个没有 <table> 包裹的 <tr>。 */
    $n_hit = $filterOn ? $hit_in_sp : $hit_all;

    /* 印不印这张表。命中为 0 时不印 —— 下面那段说明已经把话说完了，再印一张
       只剩表头的空表看起来更像页面坏了。没有搜索词时保持原样（即使一行都没有
       也照旧印表头），那是本页原来的行为，与搜索无关。
       注意 $spNoRows 且带搜索词时 $n_hit 是**全表**的命中数（见上），所以那句
       「该物种没有数据集」的提示只在全表也搜不到时才改口。 */
    $printTable = !($q !== '' && $n_hit === 0);

    if ($filterOn) {
        echo "<div class=\"sc-note\" style=\"margin:4px 0 12px;\">"
           . "Showing the " . $n_sp . " dataset" . ($n_sp === 1 ? '' : 's')
           . " recorded for <b>" . sc_h($sp['species']) . "</b>. "
           . "<a href=\"" . $__self . ($q !== '' ? '?q=' . urlencode($q) : '')
           . "\">Show every species</a>.</div>";
    } elseif ($spNoRows) {
        echo "<div class=\"sc-callout sc-callout-warn\">"
           . "<b>No dataset is listed for <i>" . sc_h($sp['species']) . "</i>.</b>"
           . "<div style=\"margin-top:6px\">No row in the single-cell table names that "
           . "species. "
           . ($printTable
                ? "The table below lists every species that does have one"
                  . ($q !== '' ? ", filtered to your search" : "") . "."
                : "No row in the table matches your search either.")
           . "</div></div>";
    }

    /* 搜索框。形制照站内「列表页 + 搜索框」的样子（browse.php 的 .tax-search、
       domain_search.php 的 .ds-form）：浅底卡片、服务端 GET。样式在
       sc_pages.css 的 .sn-search 里。 */
    echo '<form class="sn-search" method="get" action="' . $__self . '">';
    if ($__speciesRaw !== '') {
        echo '<input type="hidden" name="species" value="'
           . htmlspecialchars($__speciesRaw, ENT_QUOTES, 'UTF-8') . '" />';
    }
    echo '<label for="snQ">Search this list</label>'
       . '<input type="text" id="snQ" name="q" value="' . $qHtml . '" autocomplete="off"'
       . ' placeholder="class, species, species code, project, accession, stage or reference" />'
       . '<button type="submit">Search</button>';
    if ($q !== '') {
        echo '<a class="sn-search-clear" href="' . $__self
           . ($__viewQs !== '' ? '?' . $__viewQs : '') . '">Clear</a>';
    }
    echo '</form>';

    /* 命中说明。只报**匹配数**，不写成「N of M」—— 分母（这个视图一共几行）
       由上面那句「Showing the N datasets」交代，两句话各说一件事（站内约定，
       见 browse.php / gene_search_res.php）。 */
    if ($q !== '' && $n_hit > 0) {
        echo '<p class="sn-hit"><b>' . $n_hit . '</b> dataset' . ($n_hit === 1 ? '' : 's')
           . ' in ' . ($filterOn ? '<b>' . sc_h($sp['species']) . '</b>' : 'the whole list')
           . ($n_hit === 1 ? ' matches ' : ' match ')
           . '<span class="sn-q">' . $qHtml . '</span>.</p>';
    } elseif ($q !== '' && $n_hit === 0) {
        /* 命中 0。这里要把话说完整并给出路 —— 搜索范围可能只是当前物种视图，
           全表里也许有；让读者自己去改 URL 是最差的处理。 */
        echo '<p class="sn-none">No dataset'
           . ($filterOn ? ' in <i>' . sc_h($sp['species']) . '</i>' : '')
           . ' matches <b class="sn-q">' . $qHtml . '</b>.';
        if ($filterOn && $hit_all > 0) {
            echo ' <a class="sn-search-clear" href="' . $__self . '?q=' . urlencode($q)
               . '">Search all species instead</a>';
        } else {
            echo ' <a class="sn-search-clear" href="' . $__self
               . ($__viewQs !== '' ? '?' . $__viewQs : '') . '">Clear the search</a>';
        }
        echo '</p>';
    }

    if ($printTable) {
        echo "<table class=\"gridtable\">";
        echo "<tr>"
           . "<th width='6%'>Class</th>"
           . "<th width='12%'>Species</th>"
           . "<th>Project</th>"
           . "<th>Study Accession</th>"
           . "<th width='12%' title='Life stage or body form the cells were dissociated from, as named by the study, for example planula larva, polyp or whole adult. Free text, so wording differs between studies.'>Emb/Org</th>"
           . "<th title='Stage, condition or treatment the study reports for this sample, in the study&#39;s own words &mdash; not standardised between studies.'>Stage / condition</th>"
           /* 列头原来写 "Number of Cells"，而右边 QC 格里同一个概念那行写的是
              "Cells"（= 本站在重新处理之后的细胞数）。两个数常常不一样（这个库是
              研究自己报的、那个库是过滤后剩下的），两个几乎同名的标题摆在一行里，
              读者分不清哪个是哪个。左边这列是 $result[6] = singlecell.CellNumber，
              text 列、原样照抄研究自己的说法（"2,032"、"47,000"、"not available"），
              所以标题按「来源报的」写，并在 title 里把另一个数点明。 */
           . "<th width='6%' title='Number of cells as the study reports it (SRR/PRJ deposit figure, free text). The Quality Control column gives the number retained after this site re-processed the data; the two differ whenever filtering was applied.'>Cells (reported)</th>"
           /* 16%：QC 那一格现在是「标签 + 数值」两列的自适应网格，比原来那一行
              11px 的连排文字需要更多横向空间；从 Emb/Org 挪 1%。 */
           . "<th width='16%'>Quality Control</th>"
           . "<th width='9%'>Analyse</th>"
           . "<th>References</th></tr>";
    }

    /* 打印循环走内存里的 $rows_all，不再重查一次 singlecell —— 上面那次查询
       拿到的就是同一批行（33 行，一次查询 vs 两次）。 */
    foreach ($rows_all as $i => $result) {
        // $row_no 是这一行在 `singlecell` 表里的**行序号**（1 起），不是打印
        // 出来的第几行：sc_row_map() 按这个序号映射到数据集 id，所以物种过滤
        // 和搜索都不能让它少走一行 —— 这也正是搜索只能在 PHP 里逐行判、
        // 不能写成 SQL WHERE 的原因（见上面 $qHit 的说明）。
        $row_no = $i + 1;
        /* $printTable 也在这里判一次。它是 $n_hit 的函数，而 $n_hit 就是本循环
           会印出的行数，两者同源、正常情况下不会打架；但万一哪天条件改了、两者
           分了家，缺了这道闸就会印出一串没有 <table> 包裹的 <tr>（浏览器照渲染，
           很难看出是坏的）。宁可多这一行。 */
        if (!$printTable) {
            break;
        }
        if ($filterOn && sc_species_key($result[1]) !== sc_species_key($sp['species'])) {
            continue;
        }
        $abbr = isset($abbr1_by_sp[$result[1]]) ? $abbr1_by_sp[$result[1]] : '';
        if (!$qHit($result, $abbr)) {
            continue;
        }
        $dataset_id = isset($row_map[$row_no]) ? $row_map[$row_no] : '';
        $has_atlas = ($dataset_id !== '' && isset($atlas[$dataset_id]));

        echo "<tr align='center'>"
           . "<td><a href=\"./browse.php?class=$result[0]\">$result[0]</a></td>"
           . "<td><a href=\"speciesinfo.php?species=" . urlencode($abbr) . "\">$result[1]</a></td>"
           . "<td><a href='https://www.ncbi.nlm.nih.gov/bioproject/$result[2]' target='_blank'>$result[2]</a></td>"
           . "<td><a href='https://www.ncbi.nlm.nih.gov/sra/?term=$result[3]' target='_blank'>$result[3]</a></td>"
           . "<td>$result[4]</td><td>$result[5]</td>"
           /* CellNumber 是 text 列，9/33 行没有数字。除了 '' 和 '-'，还有一行直接写
              着字面量 "not available" —— 它和研究自己报的 "47,000" 摆在同一列里，
              会读成两种东西。三种都按「来源没给」印同一个记号。 */
           . "<td>" . ((trim((string)$result[6]) === '' || trim((string)$result[6]) === '-'
                        || strcasecmp(trim((string)$result[6]), 'not available') === 0)
                        ? "<span class='mx-na' title='The deposit does not state a cell count'>&mdash;</span>"
                        : htmlspecialchars($result[6], ENT_QUOTES, 'UTF-8')) . "</td>";

        // --- quality control ------------------------------------------------
        // 每个指标是一个 <div class='qc-line'>，不是一个 <span>。这一点是有意的：
        // 之前这里把五项都写成 <span>，靠 sc_pages.css 里的 display:grid 排版。
        // grid 一旦没生效（样式表没加载、被缓存的是旧版本、或者浏览器不吃这条），
        // 五个 span 就退化成一行连排的文字 ——
        //   "Cells28,736 → 25,164Retained87.6%Doublets0.04%Libraries2"
        // 一眼看不出哪个数字属于哪个标签。改成 div 之后，即使一个字节的 CSS 都没有，
        // 每个指标也各占一行、标签和数值相邻；CSS 只负责对齐和字重，不再负责分行。
        // 用 div 而不是 <br>：<br> 在 pre-wrap 的表格里和源码缩进混在一起更容易出岔子。
        // 另外整段不能有换行或连续空格 —— 宿主表格是 white-space:pre-wrap，
        // 源码缩进会被原样印成空白。
        if ($has_atlas) {
            $qc = sc_qc($conn, $dataset_id);
            if ($qc) {
                $lib = $qc['platform_class'];
                if ($lib === '' || $lib === 'unknown') {
                    $lib = '<i>library type not stated in the deposit</i>';
                }
                /* A dataset that was imported as the source study published it
                 * (singlecell_atlas.embedding_source) was not filtered here, so
                 * "before -> after" has nothing behind it: both numbers are the
                 * published object's one cell count, and printing it twice with
                 * an arrow reads as a 100% retention that nobody measured. */
                $imported = isset($atlas[$dataset_id]['embedding_source'])
                    && trim((string)$atlas[$dataset_id]['embedding_source']) !== '';
                $figs = '';
                if ($imported && $qc['n_cells_final'] !== null) {
                    $figs .= "<div class='qc-line'><span class='qc-k'>Cells</span>"
                           . "<span class='qc-v'>" . number_format((int)$qc['n_cells_final'])
                           . "</span></div>";
                } elseif ($qc['n_cells_raw'] !== null && $qc['n_cells_final'] !== null) {
                    $figs .= "<div class='qc-line'><span class='qc-k'>Cells</span>"
                           . "<span class='qc-v'>" . number_format((int)$qc['n_cells_raw'])
                           . " &rarr; " . number_format((int)$qc['n_cells_final']) . "</span></div>";
                }
                /* 判空要同时拦 NULL 和 ''：库里 singlecell_qc.pct_cells_retained
                   对 ACOER_lifecycle 是 NULL，而 `null !== ''` 为真，于是印出一行
                   只有 "Retained" 标签、数字空着的行。cell_atlas.php 的同一格用的
                   是 `!== null && !== ''`，两处口径现在一致。
                   已经整份按发表状态导入的行（$imported）再多一层：本站没有过滤
                   过任何细胞，所以这两个比例都不是这里测出来的数。ACOER 两列是
                   NULL，本来就印不出来；NVECT_whole_adult 存的是 100.0% / 0.00%，
                   印出来既和本页下面那段说明（「as published 的行没有 doublet
                   数字」）对不上，0.00% 又会被读成「测过，没有双细胞」——
                   那个数据集是 scATAC、压根没做双细胞判定。两行都按 $imported 拦掉。 */
                if (!$imported
                    && $qc['pct_cells_retained'] !== '' && $qc['pct_cells_retained'] !== null) {
                    $figs .= "<div class='qc-line'><span class='qc-k'>Retained</span>"
                           . "<span class='qc-v'>" . sc_h($qc['pct_cells_retained']) . "</span></div>";
                }
                // NULL as well as '' means "the deposit does not say" for the
                // datasets that only carry a published object
                if (!$imported
                    && $qc['doublet_rate'] !== '' && $qc['doublet_rate'] !== null
                    && $qc['n_doublets_removed'] !== null) {
                    $figs .= "<div class='qc-line'><span class='qc-k'>Doublets</span>"
                           . "<span class='qc-v'>" . sc_h($qc['doublet_rate']) . "</span></div>";
                }
                // 每个数据集是几份文库合并来的 —— 直接决定上面那些比例好不好读
                // （16 份文库合并得到 49,166 个细胞，和 1 份得到 49,166 个不是一件事）。
                if ($qc['n_samples'] !== null && (int)$qc['n_samples'] > 0) {
                    $figs .= "<div class='qc-line'><span class='qc-k'>Libraries</span>"
                           . "<span class='qc-v'>" . (int)$qc['n_samples'] . "</span></div>";
                }
                // mito_pct_* 四列目前全库为空（35 行全是 NULL），所以这里不再
                // 留 MT% 那一行；有值的那天它会自己出现。
                if ($qc['mito_pct_median_final'] !== null) {
                    $figs .= "<div class='qc-line'><span class='qc-k'>MT% median</span>"
                           . "<span class='qc-v'>" . sc_h($qc['mito_pct_median_final']) . "%</span></div>";
                }
                echo "<td class='qc-cell'>"
                   . "<div class='qc-head'><span class='sc-pill "
                   . ($imported ? "sc-pill-info" : "sc-pill-ok") . "'>"
                   . ($imported ? "as published" : "QC applied") . "</span>"
                   . "<span class='qc-lib'>" . $lib . "</span></div>"
                   . ($figs !== '' ? "<div class='qc-figs'>" . $figs . "</div>" : '')
                   . "<a class='qc-more' href='cell_atlas.php?dataset=" . urlencode($dataset_id)
                   . "'>Full QC &rarr;</a></td>";
            } else {
                echo "<td class='qc-cell'>"
                   . "<div class='qc-head'><span class='sc-pill sc-pill-warn'>QC record pending</span></div></td>";
            }
            echo "<td><a href='cell_atlas.php?dataset=" . urlencode($dataset_id) . "'>Atlas</a>"
               . " &middot; <a href='cell_marker.php?dataset=" . urlencode($dataset_id) . "'>Markers</a></td>";
        } else {
            // No count matrix retrieved, so there is nothing to analyse.  Say
            // why -- and say the *real* reason, taken from analysis_status.
            // This cell used to hard-code "no count matrix in the public
            // deposit" for every unprocessed row, which is wrong for 6 of the
            // 23: one deposit is scATAC rather than scRNA-seq, one holds
            // normalised values instead of counts, two ship per-sample
            // matrices that need merging first, and two are archives needing
            // extraction.  The referee asked for the filtering figures, so a
            // wrong reason for their absence is worse than no reason.
            echo "<td class='qc-cell'>"
               . "<div class='qc-head'><span class='sc-pill sc-pill-none'>not re-analysed</span></div>"
               . "<div class='qc-why'>" . sc_h(sc_qc_unprocessed_reason(sc_qc($conn, $dataset_id))) . "</div></td>"
               . "<td style='font-size:15px;color:#64748b;'>&ndash;</td>";
        }

        /* Ref 列有三种值：缺省 '-'、真 PMID，以及字面量 'Link'（6 行 —— 有文章的
           外部链接但没有登记 PMID，多为 preprint）。原样印出来读者看到的就是一个
           写着 "Link" 的蓝色链接，既不是作者也不是编号。这里把 'Link' 换成可读的
           "paper"，并把 URL 放进 title 便于悬停确认；真 PMID 仍原样显示。 */
        if ($result[7] == "-") {
            echo "<td><span class='mx-na' title='No reference recorded'>&mdash;</span></td>";
        } elseif (preg_match('/^\d+$/', trim((string)$result[7]))) {
            echo "<td><a href='$result[8]' target='_blank' rel='noopener'>$result[7]</a></td>";
        } else {
            echo "<td><a href='" . htmlspecialchars($result[8], ENT_QUOTES, 'UTF-8') . "' target='_blank'"
               . " rel='noopener' title='No PMID recorded; opens the source article'>paper</a></td>";
        }
        echo "</tr>";
    }
    if ($printTable) {
        echo "</table><br>";
    }
?>
<p class="sc-note sc-note-wide">
  <b>Quality control.</b> Each dataset is filtered on its own terms rather than
  by one fixed UMI window: where the source publication states thresholds they
  are reproduced exactly, and where it does not, outliers are called
  per library by median absolute deviation. The
  <a href="cell_atlas.php">Cell Atlas</a> records the figures actually applied
  to every dataset, including cells before and after filtering and doublet
  rates. <b>Cells</b> reads <i>before filtering &rarr; after filtering</i>, and
  <b>Libraries</b> is how many libraries were integrated into that dataset, which
  is what makes its retention figure readable. A row marked
  <span class="sc-pill sc-pill-none">not re-analysed</span> is one whose count
  matrix could not be obtained from the public deposit; the reason is given in
  the cell, because the deposits differ &mdash; some hold only per-sample
  matrices, some hold normalised values rather than counts, and one is scATAC
  rather than scRNA-seq.
</p>
<p class="sc-note sc-note-wide">
  A row marked <span class="sc-pill sc-pill-info">as published</span> is a third
  case: the study deposited no count matrix that could be re-processed, but it
  did publish a finished atlas, and that object &mdash; coordinates, cell types
  and states &mdash; is imported here as it stands. Nothing in such a row was
  computed by this site, which is why <b>Cells</b> shows one number rather than
  a before-and-after, and no doublet figure: how many cells entered their
  pipeline and how many doublets they removed are not in the deposit, and zero
  would be a claim rather than a blank.
</p>

<?php sc_footer(); ?>
