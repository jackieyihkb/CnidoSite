<?php
/* =====================================================================
 * 线粒体基因组环形图（SVG）
 *
 * 背景：mitdata.php 原先只显示 images/MT/ 或 images/images/ 里预先画好的 PNG。
 * 那批图是**用别的工具离线画好**再放上来的，覆盖不全 —— MT 目录只有 147 张，
 * 而 mitochondrion 表里有 167 个物种，新并进来的物种一张图都没有。缺图时页面
 * 只能退回 default.png，读者看到的是一张与该物种无关的图。
 *
 * 既然 mitochondrion 表里每个基因的起止和链向都有，图就没必要依赖离线产物：
 * 这里直接从坐标画。好处是任何有特征行记录的物种都自动有图，且缩放不失真。
 * 已有的 PNG 仍然优先显示（那是人工绘制的，样式更讲究），SVG 作为其补充与兜底。
 * ===================================================================== */

/**
 * 把一组线粒体特征画成环形图。
 *
 * @param array  $feats   array(array('Name'=>..,'Type'=>..,'Start'=>..,'End'=>..,'Strand'=>..), ...)
 * @param int    $size    基因组长度（bp）；为空时取特征的最大 End
 * @param string $title   图下方说明
 * @return string         <svg>…</svg>；没有可用特征时返回空串
 */
function cnido_mito_svg($feats, $size = 0, $title = '')
{
    if (!is_array($feats) || !$feats) { return ''; }

    /* 规整输入：坐标转成整数、链向归一化成 +1/-1，并丢掉画不出来的行 */
    $rows = array();
    $maxEnd = 0;
    foreach ($feats as $f) {
        $s = isset($f['Start']) ? (int)$f['Start'] : 0;
        $e = isset($f['End'])   ? (int)$f['End']   : 0;
        if ($s <= 0 || $e <= 0) { continue; }
        if ($e < $s) { $t = $s; $s = $e; $e = $t; }
        $st = isset($f['Strand']) ? trim((string)$f['Strand']) : '';
        $rows[] = array(
            'name'   => isset($f['Name']) ? trim((string)$f['Name']) : '',
            'type'   => isset($f['Type']) ? strtolower(trim((string)$f['Type'])) : 'gene',
            'start'  => $s,
            'end'    => $e,
            'strand' => ($st === '-' || $st === '-1') ? -1 : 1,
        );
        if ($e > $maxEnd) { $maxEnd = $e; }
    }
    if (!$rows) { return ''; }

    /* GenBank 记录里同一个 rRNA/tRNA 位点往往同时有一条 gene 特征和一条
       rRNA/tRNA 特征（坐标完全相同）。收进库就是两行，画出来两段弧重叠、
       图例上还会同时出现「基因」与「rRNA」两种颜色。这里按坐标去重：
       同一坐标既有通用 gene 行、又有更具体的 rrna/trna 行时，只保留后者
       —— 全库共 427 处、涉及 119 个物种，不去重的话多数图都会画重。 */
    $specific = array();
    foreach ($rows as $r) {
        if ($r['type'] === 'rrna' || $r['type'] === 'trna') {
            $specific[$r['start'] . '-' . $r['end']] = true;
        }
    }
    if ($specific) {
        $rows = array_values(array_filter($rows, function ($r) use ($specific) {
            return !($r['type'] === 'gene' && isset($specific[$r['start'] . '-' . $r['end']]));
        }));
    }
    if (!$rows) { return ''; }

    $size = (int)$size > 0 ? (int)$size : $maxEnd;
    if ($size <= 0) { return ''; }

    /* 画布与半径。外环放正链、内环放负链 —— 与常见的线粒体环形图一致，
       两条链上的基因不会互相压字。 */
    $W = 620; $H = 620; $cx = $W / 2; $cy = $H / 2;
    $rBackbone = 246;
    $rOut = 214;   // 正链基因的外缘
    $rIn  = 168;   // 负链基因的内缘
    $thick = 26;

    $colour = array(
        'gene' => '#2f6fb5',   // 蛋白编码基因
        'rrna' => '#c0392b',
        'trna' => '#2e8b57',
    );

    /* 角度换算：0 bp 在正上方，顺时针一圈 = 5'→3'。
       SVG 的 y 轴向下，所以 sin 取负号。 */
    $ang = function ($bp) use ($size) {
        return -M_PI / 2 + 2 * M_PI * ($bp / $size);
    };
    $pt = function ($a, $r) use ($cx, $cy) {
        return array($cx + $r * cos($a), $cy + $r * sin($a));
    };
    /* 画一段圆弧路径。跨度接近整圈时用整圆代替，否则 A 指令的起终点重合会画不出来。 */
    $arc = function ($a1, $a2, $r1, $r2) use ($pt) {
        $large = (abs($a2 - $a1) > M_PI) ? 1 : 0;
        $p1 = $pt($a1, $r1); $p2 = $pt($a2, $r1);
        $p3 = $pt($a2, $r2); $p4 = $pt($a1, $r2);
        return sprintf(
            'M%.2f,%.2f A%.2f,%.2f 0 %d 1 %.2f,%.2f L%.2f,%.2f A%.2f,%.2f 0 %d 0 %.2f,%.2f Z',
            $p1[0], $p1[1], $r1, $r1, $large, $p2[0], $p2[1],
            $p3[0], $p3[1], $r2, $r2, $large, $p4[0], $p4[1]
        );
    };

    $E = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $n = function ($v) { return number_format((float)$v); };

    $out = array();
    $out[] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H . '"'
           . ' width="100%" style="max-width:620px;height:auto" role="img"'
           . ' aria-label="Mitochondrial genome map">';
    $out[] = '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $rBackbone . '"'
           . ' fill="none" stroke="#d7e0ea" stroke-width="1"/>';

    /* 主刻度：每 1 kb 一个小刻度，5 kb 一个带标签的刻度 */
    $step = 1000;
    if ($size > 60000) { $step = 5000; }
    for ($bp = 0; $bp < $size; $bp += $step) {
        $a = $ang($bp);
        $big = ($bp % ($step * 5) === 0);
        $r1 = $rBackbone + 4;
        $r2 = $rBackbone + ($big ? 16 : 9);
        $p1 = $pt($a, $r1); $p2 = $pt($a, $r2);
        $out[] = sprintf('<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#9fb0c2" stroke-width="%d"/>',
            $p1[0], $p1[1], $p2[0], $p2[1], $big ? 1 : 1);
        if ($big) {
            $p3 = $pt($a, $rBackbone + 30);
            $out[] = sprintf('<text x="%.2f" y="%.2f" font-size="11" fill="#7a8a9a" text-anchor="middle" dominant-baseline="middle">%s kb</text>',
                $p3[0], $p3[1], $n($bp / 1000));
        }
    }

    /* 基因弧。先画短的后画长的，长得压倒短的时短基因仍可见 */
    usort($rows, function ($a, $b) {
        return ($a['end'] - $a['start']) - ($b['end'] - $b['start']);
    });
    foreach ($rows as $f) {
        $a1 = $ang($f['start'] - 1);
        $a2 = $ang($f['end']);
        $c  = isset($colour[$f['type']]) ? $colour[$f['type']] : '#8a8a8a';
        if ($f['strand'] > 0) { $r1 = $rOut; $r2 = $rOut - $thick; }
        else                  { $r1 = $rIn + $thick; $r2 = $rIn; }
        $tip = $f['name'] . '  ' . $n($f['start']) . '–' . $n($f['end'])
             . ' bp  (' . $n($f['end'] - $f['start'] + 1) . ' bp, ' . ($f['strand'] > 0 ? '+' : '−') . ')';
        $out[] = '<path d="' . $arc($a1, $a2, $r1, $r2) . '" fill="' . $c . '" opacity="0.88">'
               . '<title>' . $E($tip) . '</title></path>';
    }

    /* 基因名：只标蛋白编码基因与 rRNA；tRNA 太密，标了会糊成一片。
       文字沿半径方向排布，压在基因弧的外侧。 */
    foreach ($rows as $f) {
        if ($f['type'] === 'trna' || $f['name'] === '') { continue; }
        $mid = ($f['start'] + $f['end']) / 2;
        $a = $ang($mid);
        $deg = rad2deg($a);
        if ($f['strand'] > 0) { $r = $rOut - $thick - 8; $anchor = 'end'; $rot = $deg + 90; }
        else                  { $r = $rIn + $thick + 8; $anchor = 'start'; $rot = $deg - 90; }
        $p = $pt($a, $r);
        $out[] = sprintf(
            '<text x="%.2f" y="%.2f" font-size="11.5" font-weight="600" fill="#1f3b57"'
            . ' text-anchor="%s" dominant-baseline="middle"'
            . ' transform="rotate(%.1f %.2f %.2f)">%s</text>',
            $p[0], $p[1], $anchor, $rot, $p[0], $p[1], $E($f['name'])
        );
    }

    /* 圆心：基因组大小 */
    $out[] = sprintf('<text x="%d" y="%d" font-size="15" font-weight="700" fill="#1f3b57" text-anchor="middle">%s bp</text>',
        $cx, $cy - 6, $n($size));
    $out[] = sprintf('<text x="%d" y="%d" font-size="12" fill="#7a8a9a" text-anchor="middle">%s</text>',
        $cx, $cy + 16, $E($title));
    $out[] = '</svg>';
    return implode("\n", $out);
}

/** 图例：说明三种颜色各代表什么。与 cnido_mito_svg() 的配色保持一致。 */
function cnido_mito_svg_legend()
{
    $item = function ($c, $t) {
        return '<span style="display:inline-flex;align-items:center;gap:5px;margin-right:14px">'
             . '<span style="width:11px;height:11px;border-radius:2px;background:' . $c . '"></span>'
             . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</span>';
    };
    return '<div style="font-size:15px;color:#425466;margin:8px 0 0">'
         . $item('#2f6fb5', 'Protein-coding gene')
         . $item('#c0392b', 'rRNA')
         . $item('#2e8b57', 'tRNA')
         . '<span style="color:#64748b">· outer ring = + strand, inner ring = &minus; strand</span>'
         . '</div>';
}
