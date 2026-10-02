<?php
/* microsynteny.php -- Microsynteny Analysis overview.
 *
 * Modelled on deepoceanomics.org/microsynteny_analysis.php: a species x species
 * heatmap of pairwise microsynteny where every square opens the corresponding
 * pair's page.
 *
 * Two datasets are on offer:
 *   - the 22 species with chromosome-level assemblies, compared all against all
 *     here (231 pairs).  58 of those pairs produced per-pair circos/dotplot
 *     figures; the other 173 had no conserved block detected and open a
 *     numerical summary instead.  This drives the heatmap.
 *   - the whole analysis: 144 species / 10,296 pairs, gene-level statistics only
 *     (no chromosome figures).  Shown as a searchable table further down, loaded
 *     on demand because it is ~450 KB.
 */
require_once __DIR__ . '/microsynteny_page.php';

$ov = mts_overview();
$n_sp   = ($ov && isset($ov['species'])) ? count($ov['species']) : 0;
$n_pair = ($ov && isset($ov['summary']['pairs'])) ? $ov['summary']['pairs'] : 0;
$n_fig  = ($ov && isset($ov['summary']['pairs_with_figures'])) ? $ov['summary']['pairs_with_figures'] : 0;
$n_chrom    = ($ov && isset($ov['summary']['pairs_chromosome_figures'])) ? $ov['summary']['pairs_chromosome_figures'] : 0;
$n_scaffold = ($ov && isset($ov['summary']['pairs_scaffold_figures'])) ? $ov['summary']['pairs_scaffold_figures'] : 0;

/* classes represented, for the caption under the heatmap */
$classes = array();
if ($ov && isset($ov['species'])) {
    foreach ($ov['species'] as $s) {
        if ($s['class'] !== '') { $classes[$s['class']] = true; }
    }
}
ksort($classes);

mts_page_head(
    'Microsynteny Analysis',
    'Pairwise microsynteny across Cnidaria genomes: conserved gene blocks detected with '
    . 'MCScanX/Pansyn, shown as an interactive species-by-species heatmap with per-pair '
    . 'circos and dotplot figures.'
);
?>
<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Microsynteny Analysis</b></legend>

<p class="mts-lead">
    Microsynteny blocks are called with the Pansyn toolkit
    (<a href="https://pubmed.ncbi.nlm.nih.gov/38514839/" target="_blank" rel="noopener noreferrer">Yu et al. 2024</a>).
    The heatmap below covers the <b><?php echo number_format($n_sp); ?> species with
    chromosome-level assemblies</b>; each square is one species pair. <b>Hover</b> a square for
    its values, <b>click</b> it to open that pair's page.
    <?php /* 不是每对都有图：231 对里只有 $n_fig 对跑出了 circos/dotplot，其余的
             点开只有一张数值汇总表。以前的导语先说「点开就是 circos + dotplot」，
             再补一句「只有 70 对才有」——和同一页卡片上的 Pairs with figures
             自相矛盾，而且把限定条件放到了最后。现在把两件事并排说一次。 */ ?>
    The <b><?php echo number_format($n_fig); ?> pairs</b> with a detected conserved block carry a
    <b>circos plot and dotplot</b>; every other pair opens a numerical summary instead.
</p>

<?php if (!$ov): ?>
<p class="mts-warn">The microsynteny data files are not available on this server.</p>
<?php else: ?>

<div class="mts-cards">
    <div class="mts-card"><div class="k">Chromosome-level species</div><div class="v"><?php echo number_format($n_sp); ?></div><div class="u"><?php echo msr_h(implode(', ', array_keys($classes))); ?></div></div>
    <div class="mts-card"><div class="k">Pairs compared</div><div class="v"><?php echo number_format($n_pair); ?></div><div class="u">all against all</div></div>
    <div class="mts-card"><div class="k">Pairs with figures</div><div class="v"><?php echo number_format($n_fig); ?></div><div class="u"><?php echo number_format($n_chrom); ?> chromosome-level + <?php echo number_format($n_scaffold); ?> all-contig</div></div>
    <div class="mts-card"><div class="k">Full analysis</div><div class="v">144</div><div class="u">species, 10,296 pairs</div></div>
</div>

<div class="mts-toolbar">
    <div class="mts-f">
        <label for="mtsMetric">Colour by</label>
        <select id="mtsMetric">
            <option value="density" selected="selected">Gene-pair density</option>
            <option value="blocks">Conserved blocks</option>
        </select>
    </div>
    <div class="mts-f">
        <label for="mtsSortAx">Order species</label>
        <select id="mtsSortAx">
            <option value="name" selected="selected">By name</option>
            <option value="class">By class</option>
        </select>
    </div>
    <div class="mts-hint-inline">Click any square to open that pair's page.</div>
</div>

<div id="mtsHeat" class="mts-heat"></div>
<div id="mtsHeatMsg" class="mts-heatmsg"></div>

<p class="mts-note" id="mtsLegendNote">
    The diagonal is a species against itself and is left blank. Darker blue means fewer
    shared collinear gene pairs, warmer orange means more. Squares with no detected block
    are pale. The gene-pair density is the number of collinear gene pairs between the two
    species divided by the number of protein-coding genes in the smaller gene set.
    <span class="mts-genus-note">On this screen the axes are labelled with genus names only;
    the full binomial is in the tooltip.</span>
</p>

<h3 class="mts-h3" id="allpairs">Pairwise microsynteny across all 144 analysed species</h3>
<p class="mts-lead">
    The heatmap above covers the 22 species whose assemblies are resolved to chromosomes,
    because only those can be drawn per chromosome. The full run compared
    <b>144 species</b> (10,296 pairs) at gene level; every pair's statistics are searchable
    below. Pairs that also have chromosome figures are linked from here.
</p>

<?php /* 这三行以前是 disabled 的，要先把「Load the 10,296-pair table」按下去才活过来：
       读者一上来在搜索框里打字，看到的是一个灰掉、没有解释的控件。现在控件一直是活的，
       任何一次输入都会把表拉下来（按钮留着，作为「我知道它有多大」的显式入口；
       拉完按钮自己消失）。 */ ?>
<div class="mts-toolbar">
    <div class="mts-f">
        <label for="apQ">Search species</label>
        <input type="text" id="apQ" placeholder="e.g. Nematostella, Hydra, S097" />
    </div>
    <div class="mts-f">
        <label for="apMin">Minimum % collinear genes</label>
        <input type="text" id="apMin" value="0" size="6" />
    </div>
    <div class="mts-f">
        <label for="apPer">Rows per page</label>
        <select id="apPer">
            <option value="25">25</option>
            <option value="50" selected="selected">50</option>
            <option value="100">100</option>
            <option value="200">200</option>
        </select>
    </div>
    <div class="mts-f">
        <label>&nbsp;</label>
        <button type="button" id="apLoad" class="mts-btn">Load the 10,296-pair table</button>
    </div>
</div>

<p class="mts-hint-inline" id="apCount"><?php
    $__ap_sz = @filesize(__DIR__ . '/data/microsynteny/pairs144.json');
    echo 'Not loaded yet &mdash; the table is ' . ($__ap_sz ? mts_fsize($__ap_sz) : 'large')
       . ', so it is fetched on demand. Filtering, sorting and paging all run in your browser.';
?></p>

<div class="mts-tablewrap" id="apWrap" style="display:none;">
    <table class="gridtable mts-table">
        <thead>
        <tr>
            <th data-k="0" class="sortable">Species A</th>
            <th data-k="1" class="sortable">Species B</th>
            <th data-k="2" class="sortable num">Genes</th>
            <th data-k="3" class="sortable num">Collinear genes</th>
            <th data-k="4" class="sortable num">% collinear</th>
            <th data-k="5" class="sortable num">Blocks</th>
            <th data-k="6" class="sortable num">Mean score</th>
            <th>Figures</th>
        </tr>
        </thead>
        <tbody id="apBody"></tbody>
    </table>
</div>
<div id="apPager" class="mts-pager" style="display:none;"></div>

<?php
/* Sizes are read from disk at render time so this listing cannot go stale.
   The files live outside the docroot-visible data/ tree on purpose; see
   download/microsynteny/download_README.txt for the formats. */
$__mts_tables = array(
    array('microsynteny_statistics_144species.txt',
          'Statistics for <b>all 10,296 pairs</b> across the 144 analysed species: genes compared, collinear genes, percentage, block numbers and block scores.'),
    array('pairwise_microsynteny_22species.tsv',
          'The 231 pairs among the 22 chromosome-level species: blocks, collinear gene pairs, gene-pair density.'),
    array('matrix_gene_pair_density_22species.tsv',
          'Gene-pair density as a symmetric 22 &times; 22 matrix (the heatmap data).'),
    array('matrix_block_count_22species.tsv',
          'Conserved block counts as a symmetric 22 &times; 22 matrix.'),
    array('pair_report_index_22species.tsv',
          'Per-pair status and figure paths for the 231 pairs.'),
    array('soft_core_clusters.tsv',
          '114 two-gene windows conserved across &ge; 10 non-reference species (S097-anchored).'),
    array('soft_core_PAV.tsv',
          'Presence/absence of each conserved window in each of the 144 species.'),
    array('support_distribution.tsv',
          'How many conserved windows are supported by <i>N</i> species.'),
    array('run_summary.tsv',
          'Parameters and totals of the widely-conserved analysis.'),
    array('species_code_map.tsv',
          'S-code to species mapping, with the input genome/protein/GFF paths.'),
    array('doo22_chromosome_whitelist.tsv',
          'Which contigs count as named chromosomes for the 22 species.'),
    array('extraction_audit.tsv',
          'How the 22-species subset was extracted (counts, expected pairs).'),
);
?>
<h3 class="mts-h3" id="downloads">Downloads &mdash; collinearity data</h3>
<p class="mts-lead">
    The underlying data, not just the figures. Each pair page links its own
    <code>&lt;pair&gt;.collinearity</code> (every collinear block and the gene pairs inside it)
    and <code>&lt;pair&gt;_microsyn_genes.links</code> (the link table behind the circos plot)
    &mdash; all 10,296 pairs have them.
</p>
<table class="gridtable mts-stat mts-dl">
<?php foreach ($__mts_tables as $__t): $__sz = mts_dl_table($__t[0]); if ($__sz === '') { continue; } ?>
    <tr>
        <th><a href="<?php echo MTS_DL_URL; ?>/tables/<?php echo rawurlencode($__t[0]); ?>" download target="_blank" rel="noopener"><?php echo $__t[0]; ?></a></th>
        <td><?php echo $__t[1]; ?></td>
        <td class="num"><?php echo $__sz; ?></td>
    </tr>
<?php endforeach; ?>
</table>
<p class="mts-note">
    Format notes for every file are in the
    <a href="<?php echo MTS_DL_URL; ?>/download_README.txt" target="_blank" rel="noopener">data README</a>. Collinearity was
    called with <b>MCScanX</b>; microsynteny blocks follow the <b>Pansyn</b> toolkit
    (<a href="https://pubmed.ncbi.nlm.nih.gov/38514839/" target="_blank" rel="noopener noreferrer">Yu et al. 2024</a>).
</p>
<p class="mts-note">
    <b>Figures.</b> Two sets of pre-drawn figures are shown. For
    <?php echo number_format($n_chrom); ?> pairs the circos and dotplot are drawn from the
    named chromosomes (the figures in the submitted bundle); for a further
    <?php echo number_format($n_scaffold); ?> pairs the collinear blocks fall only on
    scaffolds, so those plots are drawn from all assembled contigs and are labelled as such
    on the pair page. Every pair page also carries a &ldquo;click to zoom&rdquo; viewer,
    because the labels are small in a 3000-plus-pixel canvas.
    <br />
    <b>How this was produced.</b> Protein sets of the 144 species were searched all against all
    with DIAMOND, and collinear blocks were called with MCScanX; the chromosome-level figures
    for the 22 chromosome-resolved species were then drawn with Pansyn's circos and dotplot
    renderers. Two-gene windows conserved across &ge; 10 non-reference species relative to
    <i>Nematostella vectensis</i> (114 soft-core clusters) are part of the same analysis run.
    <br />
    Percent collinear genes is the share of the pair's genes that sit inside a collinear block
    (this is the metric comparable to the reference implementation); gene-pair density in the
    heatmap normalises collinear gene pairs by the smaller gene set instead.
</p>

<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.2/echarts.min.js" type="text/javascript"></script>
<script type="text/javascript">/*<![CDATA[*/
/* ---------------------------------------------------------------- heatmap --
 * Mirrors the reference implementation: an ECharts heatmap of the pairwise
 * matrix, one clickable square per pair, opening the pair's own page.
 * The matrix in overview.json is symmetric and ordered as `order`; the "by class"
 * ordering re-indexes it client-side rather than shipping a second matrix.
 *
 * The chart is sized from its container, not from the window.  The desktop layout
 * reserves 200 px for the row labels and 165 px for the colour bar; on a phone the
 * content column is only ~350 px, so at a 390 px viewport the 22-column grid came
 * out 16 px wide -- 0.73 px per square, i.e. no heatmap at all.  Below 900 px the
 * axes switch to genus names (unique across these 22 species, so nothing becomes
 * ambiguous; the full name with its authority stays in the tooltip) and the colour
 * bar moves under the plot, which keeps the squares ~12 px. */
var MTS = null, mtsChart = null, mtsOrder = [], mtsCompact = null;

var MTS_COMPACT_MAX = 900;      /* container width below which the compact axes kick in */

function mtsEsc(s) {
    return String(s).replace(/[&<>"]/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
}

function mtsOrderFor(mode) {
    var arr = MTS.species.slice();
    if (mode === 'class') {
        arr.sort(function (a, b) {
            if (a['class'] !== b['class']) { return a['class'] < b['class'] ? -1 : 1; }
            return a.name.toLowerCase() < b.name.toLowerCase() ? -1 : 1;
        });
    }
    return arr.map(function (s) { return s.code; });
}

function mtsAxisName(s, compact) {
    return compact ? String(s.name).split(' ')[0] : s.name;
}

function mtsSetHeight(h) {
    document.getElementById('mtsHeat').style.height = h ? (h + 'px') : '';
}

/* The compact layout owns the container height as well as the grid: it makes the
   squares roughly square, so the plot is as tall as it is wide and no taller.
   `longest` is the character count of the longest axis label -- the x labels are
   rotated 90 degrees, so they cost height rather than width and that is what the
   top margin has to cover. */
function mtsCompactGeometry(w, longest) {
    var left = 80, right = 18, fs = 9.5, bottom = 74;
    var top = Math.round(longest * fs * 0.58) + 16;
    var gw = Math.max(120, w - left - right);
    var gh = Math.min(gw, 700);
    return { left: left, right: right, top: top, gw: gw, gh: gh, fs: fs, h: top + gh + bottom };
}

function mtsDraw() {
    var metric = document.getElementById('mtsMetric').value;
    var mode   = document.getElementById('mtsSortAx').value;
    mtsOrder   = mtsOrderFor(mode);

    var orig = {};
    MTS.order.forEach(function (c, i) { orig[c] = i; });
    var spec = MTS.metrics[metric];

    var data = [], maxv = 0;
    for (var i = 0; i < mtsOrder.length; i++) {
        for (var j = 0; j < mtsOrder.length; j++) {
            if (i === j) { continue; }                       /* self-comparison: leave blank */
            var v = spec.matrix[orig[mtsOrder[i]]][orig[mtsOrder[j]]];
            if (v > maxv) { maxv = v; }
            data.push([j, i, v]);
        }
    }

    var box = document.getElementById('mtsHeat');
    mtsCompact = box.clientWidth > 0 && box.clientWidth < MTS_COMPACT_MAX;

    var labels = mtsOrder.map(function (c) { return mtsAxisName(MTS.sp[c], mtsCompact); });
    var longest = 0;
    labels.forEach(function (t) { if (t.length > longest) { longest = t.length; } });

    var geo = mtsCompact ? mtsCompactGeometry(box.clientWidth, longest) : null;
    if (geo) { mtsSetHeight(geo.h); } else { mtsSetHeight(0); }
    /* The legend note only mentions the shortened labels when they are shortened. */
    document.getElementById('mtsLegendNote').className =
        'mts-note' + (mtsCompact ? ' mts-compact-note' : '');
    var decimals = spec.decimals;

    if (!mtsChart) { mtsChart = echarts.init(box); }

    mtsChart.setOption({
        animation: false,
        tooltip: {
            formatter: function (p) {
                var a = mtsOrder[p.value[1]], b = mtsOrder[p.value[0]];
                var rec = MTS.pairs[a + '_' + b] || {};
                return '<b>' + mtsEsc(MTS.sp[a].name) + '</b> &harr; <b>' + mtsEsc(MTS.sp[b].name) + '</b><br />'
                     + mtsEsc(spec.label) + ': <b>' + Number(p.value[2]).toFixed(decimals) + '</b><br />'
                     + 'Conserved blocks: ' + (rec.blocks !== undefined ? rec.blocks : '&ndash;') + '<br />'
                     + 'Collinear gene pairs: ' + (rec.gene_pairs !== undefined ? rec.gene_pairs : '&ndash;') + '<br />'
                     + '<span style="color:#6b7280">' + (rec.fig ? 'click for circos + dotplot' : 'no chromosome-level block detected') + '</span>';
            }
        },
        /* right: 165 reserves a column for the colour bar.  ECharts anchors the
           visualMap to the right edge and draws its text to the LEFT of the bar
           (~110 px for "Gene-pair density" plus the 0/max numbers), but the grid
           does not account for that, so at right: 105 the numbers sat on top of
           the heatmap. */
        grid: mtsCompact
            ? { left: geo.left, right: geo.right, top: geo.top, height: geo.gh }
            : { left: 200, right: 165, top: 165, bottom: 30 },
        xAxis: {
            type: 'category', data: labels, position: 'top',
            axisLabel: mtsCompact ? {
                /* vertical labels cost height but almost no width, which is the
                   axis the compact layout is short of.  No align/verticalAlign
                   override: ECharts' defaults for a top axis are what keep the
                   rotated text above the grid instead of inside it. */
                rotate: 90, fontSize: geo.fs, interval: 0, color: '#1f2937',
                fontFamily: 'Arial, sans-serif', fontWeight: 'bold'
            } : {
                rotate: 315, fontSize: 11, interval: 0, color: '#1f2937',
                fontFamily: 'Arial, sans-serif', fontWeight: 'bold',
                verticalAlign: 'middle', overflow: 'break', width: 150
            },
            axisTick: { show: false }
        },
        yAxis: {
            type: 'category', data: labels, inverse: true,
            axisLabel: mtsCompact ? {
                fontSize: geo.fs, interval: 0, color: '#1f2937',
                fontFamily: 'Arial, sans-serif', fontWeight: 'bold',
                width: geo.left - 10, overflow: 'truncate'
            } : {
                fontSize: 11, interval: 0, color: '#1f2937',
                fontFamily: 'Arial, sans-serif', fontWeight: 'bold', width: 190, overflow: 'break'
            },
            axisTick: { show: false }
        },
        visualMap: mtsCompact ? {
            /* No `text` here: a horizontal colour bar draws its label to the right
               of the bar, and on a 350 px container "Gene-pair density" ran off the
               edge.  The metric is already named by the "Colour by" select directly
               above the chart and by the note below it. */
            type: 'continuous', min: 0, max: maxv, calculable: true, precision: decimals,
            orient: 'horizontal', left: 'center', bottom: 2,
            itemWidth: 13, itemHeight: 170,
            textStyle: { fontSize: 11, color: '#1f2937', fontWeight: 'bold' },
            inRange: { color: ['#2E79B2', '#EDF8C0', '#E35632'] }
        } : {
            type: 'continuous', min: 0, max: maxv, calculable: true, precision: decimals,
            /* nudged left so the label, which is centred on the bar, stays
               inside the container instead of being clipped at the right edge */
            orient: 'vertical', right: 8, top: '20%',
            text: [spec.label], textStyle: { fontSize: 11, color: '#1f2937', fontWeight: 'bold' },
            inRange: { color: ['#2E79B2', '#EDF8C0', '#E35632'] }
        },
        series: [{
            type: 'heatmap', data: data, encode: { x: 0, y: 1 },
            itemStyle: { borderColor: '#ffffff', borderWidth: 0.5, cursor: 'pointer' },
            emphasis: { itemStyle: { shadowBlur: 10, shadowColor: 'rgba(0,0,0,0.45)' } },
            progressive: 4000
        }]
    }, true);

    mtsChart.off('click');
    mtsChart.on('click', function (p) {
        var a = mtsOrder[p.value[1]], b = mtsOrder[p.value[0]];
        if (a === b) { return; }
        window.open('microsynteny_detail.php?x=' + encodeURIComponent(a)
                  + '&y=' + encodeURIComponent(b), '_blank');
    });
    /* leave the diagonal blank rather than painting a zero-valued cell */
    mtsChart.setOption({ series: [{ data: data }] });
}

/* ------------------------------------------------------- full 144-species --
 * ~450 KB, so it is fetched only when the reader asks for it. Filtering,
 * sorting and paging all happen in the browser over the loaded rows. */
var P144 = null;
var ap = { q: '', min: 0, page: 1, per: 50, k: 4, dir: -1, rows: [] };

function apName(code) { return (P144 && P144.species[code]) || code; }

function apFiltered() {
    var q = ap.q.trim().toLowerCase(), min = ap.min;
    var out = ap.rows.filter(function (r) {
        if (r[4] < min) { return false; }
        if (!q) { return true; }
        return (r[0] + ' ' + r[1] + ' ' + apName(r[0]) + ' ' + apName(r[1])).toLowerCase().indexOf(q) !== -1;
    });
    var k = ap.k, dir = ap.dir;
    out.sort(function (x, y) {
        var a = (k <= 1) ? String(x[k]).toLowerCase() : x[k];
        var b = (k <= 1) ? String(y[k]).toLowerCase() : y[k];
        if (a === b) { return x[4] < y[4] ? 1 : -1; }
        return (a < b ? -1 : 1) * dir;
    });
    return out;
}

function apRender() {
    var rows = apFiltered();
    var pages = Math.max(1, Math.ceil(rows.length / ap.per));
    if (ap.page > pages) { ap.page = pages; }
    var from = (ap.page - 1) * ap.per, to = Math.min(from + ap.per, rows.length);

    var html = '';
    for (var i = from; i < to; i++) {
        var r = rows[i];
        var key = r[0] + '_' + r[1];
        var rec = (MTS && MTS.pairs[key]) ? MTS.pairs[key] : null;
        /* 以前 rec 存在但 rec.fig 是 false 时印的是一个不带链接的 "none"：这一格的
           配对就在 22 个染色体级物种里，没检出保守块，点开详情页本来是有数值汇总的，
           反而是 22 个之外的配对有链接。现在一律给链接，只是措辞不同。 */
        var url = 'microsynteny_detail.php?x=' + encodeURIComponent(r[0]) + '&y=' + encodeURIComponent(r[1]);
        var figs = '<a href="' + url + '" target="_blank" rel="noopener noreferrer">'
                 + ((rec && rec.fig) ? 'circos + dotplot' : 'statistics') + '</a>';
        html += '<tr>'
              + '<td><i>' + mtsEsc(apName(r[0])) + '</i> <span class="dim">' + mtsEsc(r[0]) + '</span></td>'
              + '<td><i>' + mtsEsc(apName(r[1])) + '</i> <span class="dim">' + mtsEsc(r[1]) + '</span></td>'
              + '<td class="num">' + r[2].toLocaleString() + '</td>'
              + '<td class="num">' + r[3].toLocaleString() + '</td>'
              + '<td class="num">' + r[4].toFixed(2) + '</td>'
              + '<td class="num">' + r[5].toLocaleString() + '</td>'
              + '<td class="num">' + (r[6] ? r[6].toFixed(1) : '&ndash;') + '</td>'
              + '<td>' + figs + '</td>'
              + '</tr>';
    }
    document.getElementById('apBody').innerHTML = html || '<tr><td colspan="8" class="dim" style="padding:18px;text-align:center;">No pair matches the current filter.</td></tr>';

    document.getElementById('apCount').innerHTML = rows.length.toLocaleString()
        + ' pair' + (rows.length === 1 ? '' : 's') + ' matched'
        + (rows.length ? ', showing ' + (from + 1).toLocaleString() + '&ndash;' + to.toLocaleString() : '')
        + ' of the ' + ap.rows.length.toLocaleString() + ' compared.';

    var h = '<span class="dim">page ' + ap.page + ' of ' + pages + '</span> ';
    var win = 2, p0 = Math.max(1, ap.page - win), p1 = Math.min(pages, ap.page + win);
    function pg(p, label) {
        return '<a href="javascript:void(0)" data-p="' + p + '">' + label + '</a>';
    }
    if (ap.page > 1) { h += pg(1, '&laquo; first') + pg(ap.page - 1, '&lsaquo; prev'); }
    if (p0 > 1) { h += '<span class="dim">&hellip;</span>'; }
    for (var p = p0; p <= p1; p++) {
        h += (p === ap.page) ? '<span class="cur">' + p + '</span>' : pg(p, p);
    }
    if (p1 < pages) { h += '<span class="dim">&hellip;</span>'; }
    if (ap.page < pages) { h += pg(ap.page + 1, 'next &rsaquo;') + pg(pages, 'last &raquo;'); }
    var pager = document.getElementById('apPager');
    /* 命中 0 时不留一条「page 1 of 1」的光杆分页条 —— 站里其它搜索框同一约定。 */
    pager.innerHTML = rows.length ? h : '';
    pager.style.display = rows.length ? '' : 'none';
    Array.prototype.forEach.call(pager.querySelectorAll('a[data-p]'), function (a) {
        a.addEventListener('click', function () {
            ap.page = parseInt(a.getAttribute('data-p'), 10) || 1;
            apRender();
            document.getElementById('allpairs').scrollIntoView();
        });
    });
}

var apBusy = false, apFailed = false;

/* ~460 KB, so the table is only fetched when the reader asks for it -- but
   "asking" now includes touching any of the controls above, not just the button. */
function apEnsureLoaded() {
    if (P144 || apBusy) { return; }
    apBusy = true;
    var btn = document.getElementById('apLoad');
    btn.disabled = true;
    btn.textContent = 'Loading\u2026';
    document.getElementById('apCount').textContent = 'Loading the pair table\u2026';
    fetch('microsynteny_data.php?set=pairs144')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            P144 = d;
            ap.rows = d.rows;
            apBusy = false;
            btn.style.display = 'none';
            document.getElementById('apWrap').style.display = '';
            document.getElementById('apPager').style.display = '';
            apRender();
        })
        .catch(function () {
            apBusy = false;
            apFailed = true;
            btn.disabled = false;
            btn.textContent = 'Load failed \u2014 click to retry';
            document.getElementById('apCount').textContent = 'The pair table could not be loaded.';
        });
}

/* 表还没到位时控件也要能用：先把过滤条件记下来，等表到了 apRender() 自己会带上。
   加载失败后不再自动重试，否则每敲一个字符就再发一次请求。 */
function apControlChanged() {
    if (P144) { apRender(); }
    else if (!apFailed) { apEnsureLoaded(); }
}

document.getElementById('apLoad').addEventListener('click', function () {
    apFailed = false;
    apEnsureLoaded();
});

document.getElementById('apQ').addEventListener('input', function () { ap.q = this.value; ap.page = 1; apControlChanged(); });
document.getElementById('apMin').addEventListener('input', function () {
    ap.min = parseFloat(this.value) || 0; ap.page = 1; apControlChanged();
});
document.getElementById('apPer').addEventListener('change', function () {
    ap.per = parseInt(this.value, 10) || 50; ap.page = 1; apControlChanged();
});
Array.prototype.forEach.call(document.querySelectorAll('#apWrap th.sortable'), function (th) {
    th.addEventListener('click', function () {
        var k = parseInt(th.getAttribute('data-k'), 10);
        if (ap.k === k) { ap.dir = -ap.dir; } else { ap.k = k; ap.dir = (k <= 1) ? 1 : -1; }
        Array.prototype.forEach.call(document.querySelectorAll('#apWrap th.sortable'), function (o) {
            o.classList.remove('asc'); o.classList.remove('desc');
        });
        th.classList.add(ap.dir === 1 ? 'asc' : 'desc');
        ap.page = 1;
        apRender();
    });
});

/* --------------------------------------------------------------- bootstrap */
(function () {
    function boot() {
        fetch('microsynteny_data.php?set=overview')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                MTS = d;
                MTS.sp = {};
                d.species.forEach(function (s) { MTS.sp[s.code] = s; });
                document.getElementById('mtsMetric').addEventListener('change', mtsDraw);
                document.getElementById('mtsSortAx').addEventListener('change', mtsDraw);
                mtsDraw();
                /* Crossing the compact breakpoint changes the axes and the container
                   height, so that resize needs a full redraw; a resize inside one
                   mode only needs the canvas rescaled. */
                window.addEventListener('resize', function () {
                    if (!mtsChart) { return; }
                    var box = document.getElementById('mtsHeat');
                    var compact = box.clientWidth > 0 && box.clientWidth < MTS_COMPACT_MAX;
                    if (compact !== mtsCompact) { mtsDraw(); } else { mtsChart.resize(); }
                });
            })
            .catch(function () {
                document.getElementById('mtsHeatMsg').textContent = 'The microsynteny data could not be loaded.';
            });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else { boot(); }
})();
/*]]>*/</script>

<?php mts_page_foot(); ?>
