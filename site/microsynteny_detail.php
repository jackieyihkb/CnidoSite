<?php
/* microsynteny_detail.php -- one species pair.
 *
 * Modelled on deepoceanomics.org/microsynteny_analysis_detail.php?x=&y= : the
 * circos plot and the dotplot of the pair side by side, under a sentence naming
 * the two species.
 *
 * Only the pairs in which chromosome-level collinear blocks were actually
 * detected have figures (48 of the 231 pairs among the 22 chromosome-level
 * species).  The rest get their statistics and an explicit "no block detected"
 * note instead of an empty frame, and a pointer to the all-pairs table.
 */
require_once __DIR__ . '/microsynteny_page.php';

$x = isset($_GET['x']) ? preg_replace('/[^A-Za-z0-9_]/', '', (string)$_GET['x']) : '';
$y = isset($_GET['y']) ? preg_replace('/[^A-Za-z0-9_]/', '', (string)$_GET['y']) : '';
if ($x !== '' && $y !== '' && strcmp($x, $y) > 0) { $t = $x; $x = $y; $y = $t; }

$bycode = mts_species_index();
$ov = mts_overview();

$err = '';
$rec = null;      /* record from the chromosome-level set (has status + figures) */
$full = null;     /* row from the 144-species statistics                     */
$nameA = ''; $nameB = '';

if ($x === '' || $y === '') {
    $err = 'No species pair was given.';
} elseif ($x === $y) {
    $err = 'Please pick two different species.';
} else {
    $d144 = mts_pairs144();
    $names144 = ($d144 && isset($d144['species'])) ? $d144['species'] : array();
    if (!isset($names144[$x]) && !isset($bycode[$x])) {
        $err = 'Unknown species code "' . $x . '".';
    } elseif (!isset($names144[$y]) && !isset($bycode[$y])) {
        $err = 'Unknown species code "' . $y . '".';
    } else {
        /* Prefer the chromosome-level record: it carries the figure flag and a
           status we can explain.  Everything else comes from the 144-species
           statistics, so any of the 10,296 pairs resolves to a page. */
        if ($ov && isset($ov['pairs'][$x . '_' . $y])) {
            $rec = $ov['pairs'][$x . '_' . $y];
        }
        /* Read the two figure-set counts from the data so the note below cannot
           drift out of step if a pair is re-rendered. */
        $n_chrom_fig    = isset($ov['summary']['pairs_chromosome_figures'])
                        ? $ov['summary']['pairs_chromosome_figures'] : 0;
        $n_scaffold_fig = isset($ov['summary']['pairs_scaffold_figures'])
                        ? $ov['summary']['pairs_scaffold_figures'] : 0;
        $full = mts_lookup144($x, $y);
        if ($rec === null && $full === null) {
            $err = 'No microsynteny record was found for this pair.';
        }
        $nameA = isset($bycode[$x]) ? $bycode[$x]['name']
               : (isset($names144[$x]) ? $names144[$x] : $x);
        $nameB = isset($bycode[$y]) ? $bycode[$y]['name']
               : (isset($names144[$y]) ? $names144[$y] : $y);
    }
}

$figdir = ($rec !== null) ? mts_fig_dir($x, $y) : '';
$hasfig = ($figdir !== '');
$dlpair = mts_dl_pair($x, $y);          /* which order the MCScanX files use */

/* Which figure set this pair's plots come from.  'chromosome' = the submitted
   figures, drawn from the named chromosomes only.  'scaffold' = the all-pair run,
   drawn from every assembled contig; those pairs do have collinear blocks, they
   just have none on a named chromosome.  Saying which is which matters: the two
   look different and only one of them is in the submitted bundle. */
$figscope = ($rec !== null && isset($rec['figtype'])) ? $rec['figtype'] : '';
$figscopeLabel = ($figscope === 'chromosome') ? 'chromosome-level'
               : (($figscope === 'scaffold') ? 'all assembled contigs' : '');

$nx = $nameA !== '' ? $nameA : $x;
$ny = $nameB !== '' ? $nameB : $y;

$title = ($err === '') ? ($nx . ' vs ' . $ny) : 'Species pair';
/* 两个物种名都拿不到时（直接访问本页、没带参数）不要拼出
   "between  and : ..." 这种两个空洞的句子。 */
$desc  = ($nx !== '' && $ny !== '')
       ? ('Chromosome-level microsynteny between ' . $nx . ' and ' . $ny
          . ': circos plot and dotplot of the collinear gene blocks detected by MCScanX.')
       : 'Chromosome-level microsynteny between two cnidarian species: circos plot and dotplot '
          . 'of the collinear gene blocks detected by MCScanX.';

mts_page_head($title . ' - Microsynteny Analysis', $desc);
?>
<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Microsynteny Analysis</b></legend>

<p class="mts-back"><a href="/microsynteny.php">&larr; Back to the microsynteny overview</a></p>

<?php if ($err !== ''): ?>

<p class="mts-warn"><?php echo msr_h($err); ?></p>
<p class="mts-note">
    Pick a pair from the <a href="/microsynteny.php">overview heatmap</a>, or search all
    10,296 comparisons in the <a href="/microsynteny.php#allpairs">144-species pair table</a>.
</p>

<?php else: ?>

<p class="mts-lead">
<?php if ($hasfig): ?>
    Microsynteny blocks between two species (<b><i><?php echo msr_h($nx); ?></i></b> and
    <b><i><?php echo msr_h($ny); ?></i></b>) are visualised as the figures below.
    Lines with different colour between the chromosomes from these two species indicate the
    distribution and correspondence of microsynteny genes.
<?php else: ?>
    Microsynteny comparison between two species (<b><i><?php echo msr_h($nx); ?></i></b> and
    <b><i><?php echo msr_h($ny); ?></i></b>). No microsynteny figure was produced for this pair,
    so the comparison is summarised numerically below.
<?php endif; ?>
</p>

<table class="gridtable mts-stat">
    <tr><th>Species A</th><td><i><?php echo msr_h($nx); ?></i> <span class="mts-code"><?php echo msr_h($x); ?></span></td></tr>
    <tr><th>Species B</th><td><i><?php echo msr_h($ny); ?></i> <span class="mts-code"><?php echo msr_h($y); ?></span></td></tr>
    <?php if ($rec !== null): ?>
    <tr><th>Microsynteny blocks</th><td><?php echo number_format($rec['blocks']); ?></td></tr>
    <tr><th>Collinear gene pairs</th><td><?php echo number_format($rec['gene_pairs']); ?></td></tr>
    <tr><th>Gene-pair density</th><td><?php echo number_format($rec['density'], 4); ?></td></tr>
    <tr><th>Status</th><td><?php echo msr_h(mts_status_text($rec['status'])); ?></td></tr>
    <?php endif; ?>
    <?php if ($full !== null): ?>
    <tr><th>Genes in the comparison</th><td><?php echo number_format($full[2]); ?></td></tr>
    <tr><th>Collinear genes</th><td><?php echo number_format($full[3]); ?>
        <span class="mts-code"><?php echo number_format($full[4], 2); ?>%</span></td></tr>
    <?php /* 这一格与上面的「Microsynteny blocks」是同一个量（都取自 blocks 字段，
           实测 231 对物种里 231 对相等、0 对不等），只是来源不同：上面来自
           overview.json 的图形记录，这一格来自 144 物种比较表的对应行。两个名字
           印同一个数，读者会以为是两个恰好相同的量，把来源写明。 */ ?>
    <tr><th>Conserved blocks (144-species run)</th><td><?php echo number_format($full[5]); ?></td></tr>
    <tr><th>Block score (mean / median)</th><td><?php
        echo $full[6] ? number_format($full[6], 1) : '&ndash;';
        echo ' / ';
        echo $full[7] ? number_format($full[7], 1) : '&ndash;';
    ?></td></tr>
    <?php endif; ?>
</table>

<?php if ($full === null): ?>
<p class="mts-note">
    This pair belongs to the chromosome-level set; its gene-level statistics across all
    144 analysed species are in the <a href="/microsynteny.php#allpairs">full pair table</a>.
</p>
<?php endif; ?>

<?php if ($hasfig): ?>
<?php
/* The source figures are ~3150 px wide and their label text is only ~14-17 px tall
   in that canvas.  They are laid out two-up (circos | dotplot, see .mts-figs in
   microsynteny.css): at 1556 px column width each slot is ~771 px, so the labels
   land at ~3.6 px and the inline figure is a preview only -- a click opens the
   in-page viewer, which shows the untouched original at 100% with zoom and pan,
   and that is where the chromosome names become readable.  A 1600 px copy is
   served for the inline slot.  Below 1200 px the two stack back to full width. */
$u = MTS_IMG_URL . '/' . rawurlencode($figdir);
$figs = array(
    array('kind' => 'circos',  'title' => 'Circos plot &mdash; chromosome-level collinear blocks',
          'plain' => 'Circos plot - chromosome-level collinear blocks',
          'alt' => 'Circos plot of microsynteny between ' . $nx . ' and ' . $ny),
    array('kind' => 'dotplot', 'title' => 'Dotplot &mdash; collinear gene pairs',
          'plain' => 'Dotplot - collinear gene pairs',
          'alt' => 'Dotplot of microsynteny between ' . $nx . ' and ' . $ny),
);
?>
<div class="mts-figs">
<?php foreach ($figs as $f):
    $base  = $figdir . '_' . $f['kind'];
    $full  = MTS_IMG_DIR . '/' . $figdir . '/' . $base . '.png';
    $web   = MTS_IMG_DIR . '/' . $figdir . '/' . $base . '_web.png';
    if (!is_file($full)) { continue; }
    $dim = @getimagesize($full);
    $wh  = $dim ? $dim[0] . ' &times; ' . $dim[1] . ' px' : '';
    $inline = is_file($web) ? $base . '_web.png' : $base . '.png';
    /* Apache sends no Cache-Control for these files, so a browser is free to
       reuse its copy of the old figure after a re-render (this bit us when the
       circos labels were fixed: visitors kept seeing the previous image).  The
       mtime in the query string changes whenever the file does, which forces a
       fresh fetch and needs no server configuration. */
    $ver = @filemtime(is_file($web) ? $web : $full);
    $vq  = $ver ? '?v=' . $ver : '';
    $pdf = MTS_IMG_DIR . '/' . $figdir . '/' . $base . '.pdf';
    $pv  = @filemtime($pdf);
    $pvq = $pv ? '?v=' . $pv : '';
?>
    <figure class="mts-fig">
        <figcaption class="mts-figcap">
            <span class="mts-figtitle"><?php echo $f['title']; ?></span>
            <?php if ($figscopeLabel !== ''): ?><span class="mts-figscope <?php echo msr_h($figscope); ?>"><?php echo msr_h($figscopeLabel); ?></span><?php endif; ?>
            <?php if ($wh !== ''): ?><span class="mts-figmeta"><?php echo $wh; ?></span><?php endif; ?>
            <span class="mts-figacts">
                <a href="<?php echo $u . '/' . rawurlencode($base . '.png') . $vq; ?>" target="_blank" rel="noopener noreferrer">full resolution</a>
                <span class="sep">&middot;</span>
                <a href="<?php echo $u . '/' . rawurlencode($base . '.pdf') . $pvq; ?>" target="_blank" rel="noopener">PDF</a>
            </span>
        </figcaption>
        <button type="button" class="mts-figbtn"
                data-full="<?php echo $u . '/' . rawurlencode($base . '.png') . $vq; ?>"
                data-title="<?php echo msr_h($f['plain']); ?>"
                title="Click to inspect at 100% (zoom &amp; pan)">
            <img src="<?php echo $u . '/' . rawurlencode($inline) . $vq; ?>"
                 alt="<?php echo msr_h($f['alt']); ?>" />
            <span class="mts-figzoom">Click to zoom &amp; pan</span>
        </button>
    </figure>
<?php endforeach; ?>
</div>
<p class="mts-note">
    The figures are drawn at ~3150 px with small labels, so the two-up view above is only a
    preview; <b>click a figure to inspect it at 100%</b> with zoom and pan &mdash; that is
    where the chromosome names and the link colours become readable. The
    &ldquo;full resolution&rdquo; link opens the original PNG in a new tab, and the PDF is
    vector quality for print.
    <?php if ($figscope === 'scaffold'): ?>
    <br /><b>Figure scope:</b> this pair has collinear blocks but none of them falls on a
    named chromosome, so these plots are drawn from <b>all assembled contigs</b> rather than
    from chromosomes only.
    <?php endif; ?>
</p>

<div id="mtsViewer" class="mts-viewer" hidden="hidden">
    <div class="mts-viewer-bar">
        <span class="mts-viewer-title" id="mtsViewerTitle"></span>
        <span class="mts-viewer-tools">
            <button type="button" data-act="out" title="Zoom out">&minus;</button>
            <button type="button" data-act="fit" title="Fit to window">Fit</button>
            <button type="button" data-act="one" title="Actual pixels">100%</button>
            <button type="button" data-act="in" title="Zoom in">+</button>
            <span class="mts-viewer-zoom" id="mtsViewerZoom">100%</span>
            <a id="mtsViewerRaw" href="#" target="_blank" rel="noopener noreferrer">open original</a>
            <button type="button" data-act="close" class="mts-viewer-close">Close (Esc)</button>
        </span>
    </div>
    <div class="mts-viewer-stage" id="mtsViewerStage">
        <img id="mtsViewerImg" alt="" />
    </div>
</div>
<?php else: ?>
<p class="mts-note">
    <b><?php
        echo $rec !== null ? msr_h(mts_status_text($rec['status']))
                           : 'No figure is available for this pair';
    ?></b>, so there is no circos/dotplot to show. A figure is drawn only where the comparison
    detected at least one collinear block &mdash; that is
    <?php echo number_format($n_fig_all ?? 70); ?> of the 231 pairs among the 22 chromosome-level
    species (<?php echo (int)$n_chrom_fig; ?> drawn from named chromosomes,
    <?php echo (int)$n_scaffold_fig; ?> from all assembled contigs).
    The statistics above cover every pair; browse the rest in the
    <a href="/microsynteny.php#allpairs">144-species pair table</a>.
</p>
<?php endif; ?>

<?php if ($err === '' && $dlpair !== ''): ?>
<h3 class="mts-h3">Download the collinearity data for this pair</h3>
<table class="gridtable mts-stat mts-dl">
    <tr>
        <th><?php echo msr_h($dlpair); ?>.collinearity</th>
        <td>MCScanX output: every collinear block and the gene pairs inside it
            (<code>gene_A &harr; gene_B</code> with the alignment e-value).</td>
        <td class="num"><?php
            $f = MTS_DL_DIR . '/pairs/' . $dlpair . '.collinearity';
            echo is_file($f) ? mts_fsize(filesize($f)) : '';
        ?></td>
        <td><a href="<?php echo MTS_DL_URL . '/pairs/' . rawurlencode($dlpair . '.collinearity'); ?>" download>download</a></td>
    </tr>
    <?php
    $lf = MTS_DL_DIR . '/pairs/' . $dlpair . '_microsyn_genes.links';
    if (is_file($lf)):
    ?>
    <tr>
        <th><?php echo msr_h($dlpair); ?>_microsyn_genes.links</th>
        <td>The gene-pair link table used to draw the circos plot: contig, start, end for
            both sides of every link, plus the chromosome colour.</td>
        <td class="num"><?php echo mts_fsize(filesize($lf)); ?></td>
        <td><a href="<?php echo MTS_DL_URL . '/pairs/' . rawurlencode($dlpair . '_microsyn_genes.links'); ?>" download>download</a></td>
    </tr>
    <?php endif; ?>
</table>
<p class="mts-note">
    Both files describe the same collinear gene pairs; the <code>.collinearity</code> file is
    the primary MCScanX output, the <code>.links</code> file is its link-table form.
    See the <a href="/microsynteny.php#downloads">download section</a> of the overview page for
    the aggregate tables and for all 10,296 pairs at once.
</p>
<?php endif; ?>

<?php endif; ?>

<script type="text/javascript">/*<![CDATA[*/
/* In-page figure viewer.
 *
 * The circos/dotplot originals are ~3150 px with ~14-17 px labels: legible at
 * 100%, illegible at the size the page can afford for an overview.  Rather than
 * sending the reader to a new tab and losing the page, the figure opens here at
 * fit-to-window and can be zoomed (buttons or wheel) and dragged to pan.
 * The stage scrolls natively, so panning is just scrolling -- no transformed
 * coordinates to keep in sync. */
(function () {
    var box   = document.getElementById('mtsViewer');
    if (!box) { return; }
    var stage = document.getElementById('mtsViewerStage');
    var img   = document.getElementById('mtsViewerImg');
    var title = document.getElementById('mtsViewerTitle');
    var zoomL = document.getElementById('mtsViewerZoom');
    var raw   = document.getElementById('mtsViewerRaw');
    var scale = 1, fitScale = 1;

    function apply(s, keepCentre) {
        var prevW = stage.scrollWidth || 1;
        var cx = (stage.scrollLeft + stage.clientWidth / 2) / prevW;
        var cy = (stage.scrollTop + stage.clientHeight / 2) / (stage.scrollHeight || 1);
        scale = Math.max(0.05, Math.min(8, s));
        img.style.width = Math.round(img.naturalWidth * scale) + 'px';
        img.style.height = 'auto';
        zoomL.textContent = Math.round(scale * 100) + '%';
        if (keepCentre) {
            stage.scrollLeft = cx * stage.scrollWidth - stage.clientWidth / 2;
            stage.scrollTop  = cy * stage.scrollHeight - stage.clientHeight / 2;
        }
    }
    function fit() {
        var pad = 16;
        fitScale = Math.min((stage.clientWidth - pad) / img.naturalWidth,
                            (stage.clientHeight - pad) / img.naturalHeight);
        apply(fitScale, false);
        stage.scrollLeft = (stage.scrollWidth - stage.clientWidth) / 2;
        stage.scrollTop  = (stage.scrollHeight - stage.clientHeight) / 2;
    }
    function open(url, t) {
        title.textContent = t || '';
        raw.href = url;
        box.hidden = false;
        document.body.style.overflow = 'hidden';
        img.onload = function () { fit(); };
        img.src = url;
        if (img.complete && img.naturalWidth) { fit(); }
    }
    function close() {
        box.hidden = true;
        document.body.style.overflow = '';
        img.src = '';
    }

    Array.prototype.forEach.call(document.querySelectorAll('.mts-figbtn'), function (b) {
        b.addEventListener('click', function () {
            open(b.getAttribute('data-full'), b.getAttribute('data-title'));
        });
    });
    Array.prototype.forEach.call(box.querySelectorAll('[data-act]'), function (b) {
        b.addEventListener('click', function () {
            var a = b.getAttribute('data-act');
            if (a === 'close') { close(); }
            else if (a === 'fit') { fit(); }
            else if (a === 'one') { apply(1, true); }
            else if (a === 'in') { apply(scale * 1.25, true); }
            else if (a === 'out') { apply(scale / 1.25, true); }
        });
    });
    document.addEventListener('keydown', function (e) {
        if (box.hidden) { return; }
        if (e.key === 'Escape') { close(); }
        else if (e.key === '+' || e.key === '=') { apply(scale * 1.25, true); }
        else if (e.key === '-') { apply(scale / 1.25, true); }
        else if (e.key === '0') { fit(); }
        else if (e.key === '1') { apply(1, true); }
    });
    stage.addEventListener('wheel', function (e) {
        if (!e.ctrlKey && !e.metaKey) { return; }      /* plain wheel still scrolls */
        e.preventDefault();
        apply(scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15), true);
    }, { passive: false });
    /* drag anywhere on the stage to pan */
    var dragging = false, sx = 0, sy = 0, sl = 0, st = 0;
    stage.addEventListener('mousedown', function (e) {
        dragging = true; sx = e.clientX; sy = e.clientY;
        sl = stage.scrollLeft; st = stage.scrollTop;
        stage.classList.add('grabbing');
        e.preventDefault();
    });
    document.addEventListener('mousemove', function (e) {
        if (!dragging) { return; }
        stage.scrollLeft = sl - (e.clientX - sx);
        stage.scrollTop  = st - (e.clientY - sy);
    });
    document.addEventListener('mouseup', function () {
        dragging = false; stage.classList.remove('grabbing');
    });
    box.addEventListener('click', function (e) {
        if (e.target === box || e.target === stage) { close(); }
    });
})();
/*]]>*/</script>

<?php
mts_page_foot();
