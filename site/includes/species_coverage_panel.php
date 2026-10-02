<?php
/* =====================================================================
 * 单物种「有哪些数据 · 这些数据分别能点开哪个可视化界面」
 *
 * 审稿意见 Referee 2 major 1：
 *   "When a species is selected in the Taxonomy module, users should be able to
 *    directly access all available genome, bulk transcriptome, single-cell,
 *    proteome, epigenome, metagenome, phenotype and palaeobiology resources for
 *    that species."
 *
 * speciesinfo.php 是从 Taxonomy / Genome 菜单一路点进来的着陆页，此前只讲基因组
 * 汇编。读者想知道「这个物种到底还有没有转录组 / 单细胞 / 表观组」，只能自己把
 * 十几个模块逐个点开，而且多数模块的下拉框并不接受 URL 参数。
 *
 * 这里把 coverage 矩阵里属于该物种的那一行摊平成一张表：每行说明这一类数据有多少
 * 条记录（存在性判定的类别给对勾而不是「1」，见 cnido_module_count_is_meaningful），
 * 以及能点开的详情页 / 可视化页。
 *
 * 数据层与 coverage_matrix.php、species_portal.php 同源（includes/coverage.php 的
 * cnido_coverage()），所以三处的数字永远一致 —— 这正是把它做成共享 include 而不是
 * 在页面里另写一套 SQL 的原因。
 *
 * 深链的拼装全部交给 includes/modlinks.php：各模块接受物种参数的「值域」并不一致
 * （有的要 abbr1 短码，有的要拉丁名），那个差异不应该外泄到调用方。
 * ===================================================================== */

/**
 * 本面板的样式。由调用页在 <head> 里 echo 出来。
 *
 * 不做成独立 .css 文件是有意的：站内静态资源只有 Last-Modified、没有
 * Cache-Control，浏览器会按启发式缓存几个小时，改了样式用户可能看不到
 * （sc_pages.css 就是为此才挂 filemtime 破缓存的）。这是极少量规则，内联最省事。
 *
 * 表格外观跟站内其余数据表一致（浅灰表头带、发丝行分隔、无竖线，见
 * templatemo_style.css 的 table.gridtable，源头是 core 的 table.cc）。
 *
 * 仍然自带 .spc- 前缀的选择器，而不是直接挂 class="gridtable"：本表只有
 * 三列且宽窄悬殊，需要 width:max-content 让它按内容收窄，而共用规则是
 * width:100% 铺满内容栏。这是共用样式管不到的一处布局差异，不是绕开它。
 */
function cnido_spcov_css()
{
    return <<<CSS
.spc-lede{font-size:15px;color:#475569;line-height:1.7;margin:0 0 12px;max-width:110ch}
.spc-sum{display:flex;align-items:center;gap:12px;margin:0 0 12px;font-size:15px;color:#475569}
.spc-sum b{color:#1e293b}
.spc-sum-bar{flex:0 0 220px;height:9px;background:#f1f5f9;border-radius:5px;overflow:hidden}
.spc-sum-bar i{display:block;height:100%;background:linear-gradient(90deg,#8fbce6 0%,#5b93cf 100%)}
table.spc-table{border-collapse:collapse;width:max-content;max-width:100%;margin:0 0 18px;font-size:15px;line-height:1.55;color:#1e293b}
table.spc-table th,table.spc-table td{border:0;border-bottom:1px solid #eef2f7;padding:7px 10px;text-align:left;vertical-align:top}
table.spc-table th{background:#f1f5f9;color:#334155;font-weight:600;white-space:nowrap;border-bottom:1px solid #e2e8f0}
table.spc-table tbody tr:nth-child(even) td{background:#fcfdff}
table.spc-table tbody tr:hover td{background:#f1f5f9}
table.spc-table td.spc-type{white-space:nowrap;font-weight:600;color:#1e293b}
table.spc-table td.spc-n{white-space:nowrap;color:#15803d;font-variant-numeric:tabular-nums;text-align:center}
table.spc-table tr.spc-no td.spc-type{font-weight:400;color:#64748b}
table.spc-table tr.spc-no td.spc-n{color:#b6c0cc}
table.spc-table tr.spc-no td.spc-open{color:#b6c0cc}
table.spc-table a{color:#1d4ed8;text-decoration:none}
table.spc-table a:hover{text-decoration:underline}
.spc-subs{display:inline-block;font-size:15px;color:#64748b;margin-left:8px}
.spc-subs a{color:#4b72b8}
.spc-hint{font-size:15px;color:#64748b;margin:-6px 0 20px}
CSS;
}

/**
 * 渲染清单主体（一张两列的表：数据类 / 有多少条 + 深链）。
 *
 * **目前没有任何页面调用它**：speciesinfo.php 上原本用它渲染「Data Coverage &
 * Visualisation」，该节已按用户要求移除，清单改由 includes/species_cards.php 的
 * 卡片网格呈现（卡片与这张表是同一件事的两种画法，同一页出现两次会让人以为是
 * 两个物种）。函数与 cnido_spcov_css() 一并留着，未删 —— 它们仍然可用，也免得
 * 需要时再写一遍。本文件现在被真正用到的是 cnido_spcov_resolve()。
 *
 * @param array  $cov       cnido_coverage() 的返回值
 * @param string $abbr      规范 abbr1
 * @param array  $info      array('species' => 拉丁名, 'abbr' => 下划线名, 'class' => ...)
 * @param string $self_page 调用页自己的文件名；指向它的子链接会被略去（本页上
 *                          「Full assembly record → speciesinfo.php」是死循环）
 * @return string HTML
 */
function cnido_spcov_html($cov, $abbr, $info, $self_page = '')
{
    require_once __DIR__ . '/coverage.php';
    require_once __DIR__ . '/modlinks.php';

    $modules = cnido_modules();
    $data = isset($cov['cov'][$abbr])  ? $cov['cov'][$abbr]  : array();
    /* 存在性占位（值为 1、含义只是「有这个模块」）的格子，见 coverage.php 的说明。
       它和「真的只有 1 条记录」在 $cov 里长得一样，只有 flag 分得开。 */
    $flag = isset($cov['flag'][$abbr]) ? $cov['flag'][$abbr] : array();

    $ok = array();   // 有数据的行
    $no = array();   // 没有数据的行
    foreach ($modules as $m => $cfg) {
        $n   = isset($data[$m]) ? (int)$data[$m] : 0;
        $has = ($n > 0);

        if (!$has) {
            $no[] = array('mod' => $m, 'cfg' => $cfg);
            continue;
        }

        if (isset($flag[$m]) || !cnido_module_count_is_meaningful($m)) {
            $label = '&#10003; available';
        } else {
            $label = number_format($n) . ' record' . ($n === 1 ? '' : 's');
        }

        /* 详情页 + 子页。指向本页自己的链接不要（点回自己没有任何信息量）。 */
        $links = array();
        $main  = cnido_module_link($m, $abbr, $info);
        if ($main !== '' && !cnido_spcov_is_self($main, $self_page)) {
            $links[] = '<a href="' . htmlspecialchars($main) . '" target="_blank" rel="noopener">'
                     . htmlspecialchars($cfg['short']) . ' &rarr;</a>';
        }
        $subs = array();
        foreach (cnido_module_subpages($m, $abbr, $info) as $lbl => $u) {
            if (cnido_spcov_is_self($u, $self_page)) { continue; }
            $subs[] = '<a href="' . htmlspecialchars($u) . '" target="_blank" rel="noopener">'
                    . htmlspecialchars($lbl) . '</a>';
        }
        $ok[] = array('mod' => $m, 'cfg' => $cfg, 'n' => $label,
                      'links' => $links, 'subs' => $subs);
    }

    $nHave = count($ok);
    $nAll  = count($modules);
    $pct   = $nAll > 0 ? round(100 * $nHave / $nAll) : 0;

    $h  = '<p class="spc-lede">CnidoSite holds <b>' . $nHave . '</b> of the <b>' . $nAll
        . '</b> data types it covers for <i>' . htmlspecialchars($info['species']) . '</i>. '
        . 'Each available entry links straight into the corresponding module or viewer, already '
        . 'filtered to this species; the greyed-out entries have no data for it in the current '
        . 'release. The full species &times; data-type grid is in the '
        . '<a href="coverage_matrix.php?q=' . urlencode($abbr) . '">coverage matrix</a>.</p>'
        . '<div class="spc-sum"><span><b>' . $nHave . '</b> of <b>' . $nAll
        . '</b> data types available</span><span class="spc-sum-bar"><i style="width:' . $pct
        . '%"></i></span></div>';

    $h .= '<table class="spc-table">'
        . '<tr><th>Data type</th><th>Records</th><th>Open</th></tr>';

    foreach ($ok as $r) {
        $h .= '<tr><td class="spc-type">' . htmlspecialchars($r['cfg']['label']) . '</td>'
            . '<td class="spc-n">' . $r['n'] . '</td>'
            . '<td class="spc-open">' . implode(' ', $r['links']);
        if (!empty($r['subs'])) {
            $h .= '<span class="spc-subs">' . implode(' &middot; ', $r['subs']) . '</span>';
        }
        $h .= '</td></tr>';
    }
    foreach ($no as $r) {
        $h .= '<tr class="spc-no"><td class="spc-type">' . htmlspecialchars($r['cfg']['label']) . '</td>'
            . '<td class="spc-n">&mdash;</td><td class="spc-open">Not available</td></tr>';
    }
    $h .= '</table>';

    $h .= '<p class="spc-hint">Links open in a new window. '
        . htmlspecialchars($info['species']) . ' &middot; '
        . htmlspecialchars($abbr) . '</p>';

    return $h;
}

/**
 * 把页面收到的物种参数解析成规范 abbr1。
 *
 * 调用方给进来的串不必先做字符过滤：本函数只把它与已知键 / 已知学名做比较，
 * 返回值一定取自 $cov['species'] 的键，不会把输入原样带回。正因如此，带空格的
 * 拉丁学名（"Nematostella vectensis"）也能认出来 —— 调用页的输入过滤会把空格
 * 剥掉，那种串在这里是认不出的，必须用解析前的原始值来调。
 *
 * @return array|null array('abbr1' => ..., 'info' => ...)；认不出返回 null
 */
function cnido_spcov_resolve($cov, $raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') { return null; }
    $sp = isset($cov['species']) ? $cov['species'] : array();

    if (isset($sp[$raw])) { return array('abbr1' => $raw, 'info' => $sp[$raw]); }

    $needle = strtolower(str_replace('_', ' ', $raw));
    foreach ($sp as $k => $info) {
        if (strtolower($k) === strtolower($raw)
            || strtolower($info['species']) === $needle
            || strtolower(str_replace('_', ' ', $info['abbr'])) === $needle) {
            return array('abbr1' => $k, 'info' => $info);
        }
    }
    /* 只给了属名时退化为前缀匹配（species_portal.php 同此口径）。短于 4 个字符
       的前缀不做 —— 「Hy」这种会误命中一大片。 */
    if (strlen($needle) >= 4) {
        foreach ($sp as $k => $info) {
            if (strpos(strtolower($info['species']), $needle) === 0) {
                return array('abbr1' => $k, 'info' => $info);
            }
        }
    }
    return null;
}

/** $url 是否指向调用页自己（用于跳过自指链接）。 */
function cnido_spcov_is_self($url, $self_page)
{
    if ($self_page === '') { return false; }
    $path = parse_url($url, PHP_URL_PATH);
    if ($path === null || $path === false) { $path = $url; }
    return (basename($path) === $self_page);
}
