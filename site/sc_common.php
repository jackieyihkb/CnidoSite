<?php
/**
 * sc_common.php -- shared bootstrap for the four Single-cell pages.
 *
 * Holds the database connection, the header/navigation block, and the small
 * helpers that map a row on sn_data.php to a viewer dataset.  Factored out so
 * the navigation cannot drift between sn_data.php, cell_atlas.php,
 * cell_marker.php and gene_exp.php.
 *
 * Degradation: every query against the new singlecell_* tables is guarded, so
 * the pages keep working (with the atlas links simply absent) on a database
 * that has not had singlecell_schema.sql loaded yet.  A missing feature is
 * better than a fatal error on a live site.
 */

/* 单细胞数据集的基因号 -> 站点基因号 对照表（见该文件顶部说明）。
   每个数据集有 singlecell_data/<DS>/gene_ids.json，已发表那几张表走
   sc_gene_refseq。缺文件/缺表时整段降级为「显示原号」，不报错。 */
require_once __DIR__ . '/includes/sc_gene_ids.php';

if (!function_exists('sc_h')) {

    /** HTML-escape for text and attribute contexts. */
    function sc_h($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    /** Connection, reusing one handle for the whole request. */
    function sc_conn()
    {
        static $conn = null;
        static $tried = false;
        if ($tried) {
            return $conn;
        }
        $tried = true;

        // PHP 8.1 made mysqli throw on connection failure instead of setting
        // connect_error, so the old `if ($conn->connect_error)` check was dead
        // code and a wrong password produced an uncaught fatal error rather
        // than the graceful "not published yet" message the pages expect.
        // Catch it, and also cope with the extension being absent entirely.
        if (!class_exists('mysqli')) {
            return null;
        }
        try {
            $c = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
        } catch (Throwable $e) {
            return null;
        }
        if ($c->connect_error) {
            return null;
        }
        $c->set_charset('utf8mb4');
        $conn = $c;
        return $conn;
    }

    /** True when a table exists, so optional features can be probed safely. */
    function sc_has_table($conn, $table)
    {
        if (!$conn) {
            return false;
        }
        $t = $conn->real_escape_string($table);
        $res = @$conn->query("SHOW TABLES LIKE '$t'");
        return $res && $res->num_rows > 0;
    }

    /** URL prefix under which the exported viewer assets are served. */
    function sc_asset_base()
    {
        return '/singlecell_data/';
    }

    /**
     * Filesystem directory holding the published atlas figures.
     *
     * Two candidates because the layout differs: on the site these pages sit in
     * the document root beside `images/`, while in the source tree they live in
     * `web/php/` beside `web/images/`.  Returning a non-existent path when
     * neither is found is deliberate -- callers test with is_dir()/is_file()
     * and fall back to the "no figure" branch rather than raising.
     */
    function sc_images_dir()
    {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }
        // Test seam: the render harness points this at a fixture tree so the
        // published-figure branches can be exercised without shipping sample
        // images inside the directory that gets deployed.
        $env = getenv('SC_IMAGES_DIR');
        if ($env !== false && $env !== '') {
            $dir = $env;
            return $dir;
        }
        $cands = array(__DIR__ . '/images', dirname(__DIR__) . '/images');
        foreach ($cands as $c) {
            if (is_dir($c)) {
                $dir = $c;
                return $dir;
            }
        }
        $dir = $cands[0];
        return $dir;
    }

    /** URL prefix for the published figures (relative, as the old page used). */
    function sc_images_url()
    {
        return 'images/';
    }

    /**
     * Filesystem directory holding the exported per-dataset assets.
     *
     * The same two candidates as sc_images_dir(), for the same reason: on the
     * site these pages sit in the document root beside `singlecell_data/`,
     * while in the source tree they live in `web/php/` beside
     * `web/singlecell_data/`.  A non-existent path is returned deliberately --
     * callers test with is_file() and fall back rather than raising.
     */
    function sc_data_dir()
    {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }
        // Test seam, as in sc_images_dir(): the render harness points this at a
        // fixture tree, so the figure branches are exercised without needing
        // the rendered PNGs inside the tree that gets deployed.
        $env = getenv('SC_DATA_DIR');
        if ($env !== false && $env !== '') {
            $dir = $env;
            return $dir;
        }
        $cands = array(__DIR__ . '/singlecell_data',
                       dirname(__DIR__) . '/singlecell_data');
        foreach ($cands as $c) {
            if (is_dir($c)) {
                $dir = $c;
                return $dir;
            }
        }
        $dir = $cands[0];
        return $dir;
    }

    /**
     * The UMAP figures this re-analysis produced for a dataset, if rendered.
     *
     * These are drawn by `pipeline/07_render_umap.py` from the same exported
     * files the viewer reads, so they show the clustering the page above them
     * shows.  Discovered on disk rather than read from a table, for the reason
     * the published figures are: the page must not promise a picture that was
     * never uploaded, and a dataset whose figures are missing must degrade to
     * "no figure" rather than to a broken <img>.
     *
     * The rule for which exist is the renderer's, not this function's: a
     * dataset whose labels are cluster numbers has one figure, not two, because
     * its "cell types" are its clusters and drawing both would put the same
     * picture on the page twice.
     *
     * @return array list of array('key' => 'celltype'|'cluster', 'file' =>, 'url' =>)
     */
    function sc_dataset_figures($dataset)
    {
        $out = array();
        if ($dataset === '' || preg_match('/[^A-Za-z0-9_.-]/', $dataset)) {
            return $out;
        }
        $dir = sc_data_dir() . '/' . $dataset;
        $figs = array(
            'celltype' => 'umap_celltype.png',
            'cluster'  => 'umap_cluster.png',
        );
        foreach ($figs as $key => $name) {
            if (is_file($dir . '/' . $name)) {
                $out[] = array(
                    'key'  => $key,
                    'file' => $name,
                    'url'  => sc_asset_base() . rawurlencode($dataset) . '/' . $name,
                );
            }
        }
        return $out;
    }

    /**
     * Which cell type each cluster of a dataset holds, from the dataset's own
     * exported per-cell labels.
     *
     * The viewer colours cells by cluster and by cell type out of
     * `cellmeta.bin`, so that file is the only authority on the pairing -- not a
     * table here and not a rule.  Reading it back is what lets a numbered group
     * carry a name.  For Nematostella whole-adult the clusters are the source
     * study's own (C1-C35, GSE294388 cellColData) and every one of them holds
     * exactly one of its 16 published cell types, so this returns the study's
     * own cluster-to-type key; for a dataset CnidoSite clustered itself it
     * returns which of the carried labels each Leiden cluster holds, and one
     * cluster can hold several.
     *
     * The layout is the exporter's fixed one, as the manifest documents it:
     * uint16, three per cell, (cell_type_idx, cluster_idx, sample_idx).  A wrong
     * dtype still yields a byte count that adds up -- the trap the single-cell
     * asset notes record -- so the guard is on the file length *and* on every
     * index being inside its label list.
     *
     * @return array|null null when the assets are missing or do not decode; the
     *                    caller must then say nothing about the pairing.
     */
    function sc_cluster_breakdown($dataset)
    {
        static $cache = array();
        if (array_key_exists($dataset, $cache)) {
            return $cache[$dataset];
        }
        $cache[$dataset] = null;
        if (!is_string($dataset) || $dataset === ''
            || preg_match('/[^A-Za-z0-9_.-]/', $dataset)) {
            return null;
        }
        $dir = sc_data_dir() . '/' . $dataset;
        $man = @file_get_contents($dir . '/manifest.json');
        $bin = @file_get_contents($dir . '/cellmeta.bin');
        if ($man === false || $bin === false) {
            return null;
        }
        $m = json_decode($man, true);
        if (!is_array($m) || !isset($m['clusters']) || !isset($m['cell_types'])
            || !isset($m['n_cells'])) {
            return null;
        }
        $clusters = array_values($m['clusters']);
        $types    = array_values($m['cell_types']);
        $n        = (int)$m['n_cells'];
        $nc       = count($clusters);
        $nt       = count($types);
        if ($n <= 0 || $nc <= 0 || $nt <= 0 || strlen($bin) < $n * 6) {
            return null;
        }
        $v = unpack('v*', substr($bin, 0, $n * 6));
        if (!is_array($v) || count($v) < $n * 3) {
            return null;
        }
        $count = array();
        $cells = array();
        for ($i = 0; $i < $n; $i++) {
            $ti = $v[$i * 3 + 1];
            $ci = $v[$i * 3 + 2];
            if ($ti >= $nt || $ci >= $nc) {
                continue;   // an index outside the label list: not this export
            }
            $lab = $clusters[$ci];
            $t   = $types[$ti];
            if (!isset($count[$lab])) {
                $count[$lab] = array();
                $cells[$lab] = 0;
            }
            $count[$lab][$t] = isset($count[$lab][$t]) ? $count[$lab][$t] + 1 : 1;
            $cells[$lab]++;
        }
        if (!$count) {
            return null;
        }
        /* Display order.  The exporter writes the labels in string order, so the
           list reads "0, 1, 10, 11, 2, ..." -- meaningless beside a cluster
           number.  Numeric and C-prefixed labels sort by their number; anything
           else keeps the manifest's order, and the original position is carried
           as the final comparison because usort() is not stable before PHP 8.0. */
        $order = array();
        foreach ($clusters as $i => $lab) {
            if (isset($count[$lab])) {
                $order[] = array($i, $lab);
            }
        }
        usort($order, function ($a, $b) {
            $na = preg_match('/^C?(\d+)$/i', $a[1], $ma);
            $nb = preg_match('/^C?(\d+)$/i', $b[1], $mb);
            if ($na && $nb && (int)$ma[1] !== (int)$mb[1]) {
                return (int)$ma[1] - (int)$mb[1];
            }
            if ($na !== $nb) {
                return $na ? -1 : 1;
            }
            return $a[0] - $b[0];
        });
        $labels = array();
        $mixed  = 0;
        foreach ($order as $o) {
            $lab = $o[1];
            arsort($count[$lab]);       // most cells first: the row's top type
            if (count($count[$lab]) > 1) {
                $mixed++;
            }
            $labels[] = $lab;
        }
        $out = array(
            'labels'  => $labels,
            'cells'   => $cells,
            'types'   => $count,
            'mixed'   => $mixed,
            'n_cells' => $n,
        );
        $cache[$dataset] = $out;
        return $out;
    }

    /**
     * The published per-tissue atlas figures the site already shipped.
     *
     * Discovered by scanning the images directory rather than read from a
     * table, for the same reason the previous page did it that way: one
     * `<ABBR>_<tissue>_UMAP_{1,2}.png` per tissue, with the bare
     * `<ABBR>_UMAP_{1,2}.png` reserved for "Whole adults".  Scanning means the
     * picker cannot drift from the files that actually exist.
     *
     * These are *published* figures -- the source study's own UMAPs.  They are
     * not re-analysed, carry no cell-type composition and cannot be recoloured,
     * so they are kept visually distinct from the interactive datasets.  All of
     * them are still built and returned; it is sc_dataset_picker() that decides
     * which to offer, and it withholds the ones a re-analysis now covers
     * (sc_interactive_twin()).
     *
     * Returns entries keyed by a synthetic id, `pub:<abbr1>:<tissue>`.
     */
    function sc_published_entries($conn)
    {
        static $entries = null;
        if ($entries !== null) {
            return $entries;
        }
        $entries = array();
        $dir = sc_images_dir();
        if (!is_dir($dir)) {
            return $entries;
        }
        $files = @scandir($dir);
        if (!$files) {
            return $entries;
        }
        $seen = array();
        foreach ($files as $f) {
            if (!preg_match('/^([A-Za-z0-9]+?)(?:_(.+?))?_UMAP_1\.png$/', $f, $m)) {
                continue;
            }
            $abbr1 = $m[1];
            $tissue = isset($m[2]) ? $m[2] : 'Whole adults';
            $key = $abbr1 . "\x1F" . $tissue;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            // `stem` is the filename minus `_UMAP_N` -- exactly what
            // singlecell_atlas.published_figure holds, so the two can be
            // compared directly.  Kept as its own key rather than re-derived at
            // each use, because deriving it by stripping a suffix is the kind
            // of thing that silently stops matching.
            $stem = $abbr1 . ($tissue === 'Whole adults' ? '' : '_' . $tissue);
            $entries['pub:' . $abbr1 . ':' . $tissue] = array(
                'abbr1'  => $abbr1,
                'tissue' => $tissue,
                'stem'   => $stem,
                'pic1'   => $stem . '_UMAP_1',
                'pic2'   => $stem . '_UMAP_2',
            );
        }
        // Species names come from the site's own abbr table; without it the
        // code is still usable as a label.
        $names = array();
        if (sc_has_table($conn, 'abbr')) {
            $res = @$conn->query("SELECT abbr1, species FROM abbr");
            if ($res) {
                while ($r = $res->fetch_row()) {
                    $names[$r[0]] = $r[1];
                }
            }
        }
        foreach ($entries as $id => $e) {
            $entries[$id]['species'] = isset($names[$e['abbr1']])
                ? $names[$e['abbr1']] : $e['abbr1'];
        }
        return $entries;
    }

    /**
     * What a published figure's own legend lists: 'names', 'numbers', or '' when
     * the site has not recorded it.  `$pic` is the picture stem -- `pic1`/`pic2`
     * as sc_published_entries() builds them, i.e. including the `_UMAP_N`.
     *
     * This is a property of the PNG that ships, so no query can answer it and no
     * rule can infer it.  `sc_published_celltypes()` reads the site's own
     * <ABBR>_cellmarker table, and that table names its groups even where the
     * figure does not: Oculina arbuscula's holds 28 named types while the first
     * figure shipped beside it is numbered 1-28.  The figure has to be looked
     * at, so each entry below was, read off the file the site serves, on
     * 2026-09-30.  Thirteen distinct files, because seven of the ten pairs are
     * byte-identical and only the first is shown:
     *
     *   names    AMILL_UMAP_1, OPATA_UMAP_1, SPIST_UMAP_1,
     *            AMURI_RegenerationStage_UMAP_2, OARBU_SymbioticState_UMAP_2
     *   numbers  AMURI_RegenerationStage_UMAP_1, NVECT_2monthAnimal_UMAP_1,
     *            NVECT_NervousSystem_UMAP_1, NVECT_NervousSystem_UMAP_2,
     *            NVECT_neoplasm_UMAP_1, NVECT_tentacle_UMAP_1,
     *            NVECT_WholeOrganism_UMAP_1, OARBU_SymbioticState_UMAP_1
     *
     * The key has to be the file and not the dataset stem: Acropora muricata
     * and Oculina arbuscula each ship one numbered figure and one named one,
     * and both are drawn.  Two figures that mislead are NVECT_WholeOrganism and
     * AMURI_RegenerationStage_UMAP_2, which print "UMAP by Cell Types" as a
     * title -- the first over a legend of 0-11, the second over names.
     *
     * '' is the honest answer for a file nobody has looked at, and callers must
     * then say nothing about the legend rather than assert what is in it -- the
     * rule `sc_qc_figure_reason()` follows for reasons.
     */
    function sc_pub_figure_legend($pic)
    {
        $known = array(
            'AMILL_UMAP_1'                   => 'names',
            'AMURI_RegenerationStage_UMAP_2' => 'names',
            'OARBU_SymbioticState_UMAP_2'    => 'names',
            'OPATA_UMAP_1'                   => 'names',
            'SPIST_UMAP_1'                   => 'names',
            'AMURI_RegenerationStage_UMAP_1' => 'numbers',
            'NVECT_2monthAnimal_UMAP_1'      => 'numbers',
            'NVECT_NervousSystem_UMAP_1'     => 'numbers',
            'NVECT_NervousSystem_UMAP_2'     => 'numbers',
            'NVECT_neoplasm_UMAP_1'          => 'numbers',
            'NVECT_tentacle_UMAP_1'          => 'numbers',
            'NVECT_WholeOrganism_UMAP_1'     => 'numbers',
            'OARBU_SymbioticState_UMAP_1'    => 'numbers',
        );
        $pic = trim((string)$pic);
        return isset($known[$pic]) ? $known[$pic] : '';
    }

    /**
     * How many groups a numbered published legend lists, or 0 when nobody has
     * counted them.
     *
     * `sc_pub_figure_legend()` says whether a legend numbers its groups; this
     * says how many, which is what lets the page hold a panel against the
     * dataset the panel is attached to.  "Its legend numbers 12 groups where
     * this dataset has 35 clusters in 16 cell types" is an argument the reader
     * can check against the picture, and it is the only kind that settles
     * whether the panel is a figure of *these* cells.
     *
     * Counted off the files this site serves, on 2026-10-02:
     *
     *   NVECT_*        the `<abbr>_cellmarker` table for that tissue, whose
     *                  cluster column runs 0-N and whose N+1 is the group count
     *   OARBU / AMURI  the swatch column of the PNG itself
     *
     * Only files `sc_pub_figure_legend()` calls 'numbers' appear here.  Anything
     * else is 0, and callers must then say nothing about the count.
     */
    function sc_pub_figure_groups($pic)
    {
        $known = array(
            'NVECT_WholeOrganism_UMAP_1'     => 12,
            'NVECT_2monthAnimal_UMAP_1'      => 13,
            'NVECT_neoplasm_UMAP_1'          => 13,
            'NVECT_tentacle_UMAP_1'          => 14,
            'NVECT_NervousSystem_UMAP_1'     => 17,
            'NVECT_NervousSystem_UMAP_2'     => 17,
            'AMURI_RegenerationStage_UMAP_1' => 13,
            'OARBU_SymbioticState_UMAP_1'    => 28,
        );
        $pic = trim((string)$pic);
        return isset($known[$pic]) ? (int)$known[$pic] : 0;
    }

    /**
     * True when a published panel is not a figure of the dataset it is attached
     * to -- the case the Nematostella whole-adult page has to own up to.
     *
     * `singlecell_atlas.published_figure` for that dataset points at the site's
     * earlier WholeOrganism panel, which numbers 12 groups while the dataset has
     * 35 clusters in 16 cell types.  Its numbers are therefore neither this
     * dataset's clusters nor its cell types.  The panel and its cluster table
     * (clusters 0-11, the older NV2.* identifiers) belong to an earlier deposit;
     * the 51,866 cells came from PRJNA1249376 / GSE294388, and
     * `singlecell_atlas_map` puts that older whole-organism row on NVECT_tissue,
     * which was never built.
     *
     * Written as a disagreement rather than as a list of stems, so the sentence
     * deletes itself if the figure is ever replaced by one drawn from these
     * cells: the panel has to disagree with *both* counts to qualify.
     */
    function sc_pub_figure_off_dataset($stem, $groups, $n_clusters, $n_types)
    {
        if (trim((string)$stem) !== 'NVECT_WholeOrganism') {
            return false;
        }
        $groups = (int)$groups;
        return ($groups > 0
                && $groups !== (int)$n_clusters
                && $groups !== (int)$n_types);
    }

    /**
     * Datasets whose "UMAP — as published (source study)" section is not drawn
     * at all, whatever the deposit offers.
     *
     * This is an editorial decision, not a derived one.  The section is drawn
     * when a deposit hands the reader both halves of the comparison (see
     * $pfShows in cell_atlas.php), and each of these five does -- NVECT_nervous
     * most plainly: two numbered panels of the study's nervous-system cells, 17
     * groups, beside this site's own annotation of the same cells.  jackie asked
     * on 2026-10-02 for the section to come off these five pages regardless, so
     * the page records the reason here rather than pretending a test failed.
     * The five are the Nematostella vectensis tissue pages; the section stays on
     * the other two deposits that earn it, AMURI_regen and OARBU_symbiotic.
     *
     * A list in code, not a NULL in `singlecell_atlas.published_figure`,
     * because that column answers a second question as well -- "which
     * interactive dataset is this static figure the published form of?" -- for
     * the published picker (sc_interactive_twin).  Clearing it would take each
     * figure's link to its own re-analysis off the picker too, which is not what
     * was asked for.  One page's section is one page's decision.
     *
     * The cluster-to-cell-type table is *not* part of what is withheld: it is
     * this site's reading of this dataset's own labels rather than part of the
     * study's deposit, so cell_atlas.php keeps it and drops only the line that
     * points at the panel above.  See the gate there.
     */
    function sc_pub_section_withheld($dataset)
    {
        static $withheld = array('NVECT_2month', 'NVECT_neoplasm',
                                 'NVECT_nervous', 'NVECT_tentacle',
                                 'NVECT_whole_adult');
        return in_array(trim((string)$dataset), $withheld, true);
    }

    /** True when an id refers to a published figure rather than a viewer dataset. */
    function sc_is_published_id($id)
    {
        return strncmp((string)$id, 'pub:', 4) === 0;
    }

    /**
     * Cell types for a published entry, from the site's own <ABBR>_cellmarker
     * table, as array(cell_type, n_markers) ordered by size.
     *
     * The table name is interpolated, so it is validated against a strict
     * character class first -- the abbr1 comes from a filename, but this is
     * still the one place a name reaches SQL without a placeholder.
     */
    function sc_published_celltypes($conn, $abbr1, $tissue)
    {
        if (!$conn) {
            return array();
        }
        $table = $abbr1 . '_cellmarker';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return array();
        }
        if (!sc_has_table($conn, $table)) {
            return array();
        }
        $out = array();
        $st = @$conn->prepare(
            "SELECT celltype, COUNT(*) FROM `$table` "
            . "WHERE tissue_dev = ? GROUP BY celltype ORDER BY COUNT(*) DESC");
        if (!$st) {
            return $out;
        }
        $st->bind_param('s', $tissue);
        $st->execute();
        $res = $st->get_result();
        if ($res) {
            while ($r = $res->fetch_row()) {
                $out[] = array($r[0], (int)$r[1]);
            }
        }
        $st->close();
        return $out;
    }

    /**
     * Marker genes for a published entry, mapped onto the viewer's schema.
     *
     * The site's own <ABBR>_cellmarker tables hold (tissue_dev, pct1, pct2,
     * log2FC, FDR, celltype, gene, symbol) as *text*, where the re-analysed
     * tables hold typed columns.  The mapping is:
     *
     *     celltype -> cell_type      gene  -> gene        symbol -> symbol
     *     log2FC   -> log2fc         FDR   -> padj        pct1   -> pct_in
     *
     * Two things have no counterpart and are returned as null rather than
     * invented: `score` (the published tables carry no test statistic) and
     * `rank`, which is derived here as the position within the cell type when
     * ordered by log2 fold change.  That is a real ranking of the published
     * values, but it is *not* the rank the source study used, so the page says
     * so rather than presenting it as equivalent.
     *
     * log2FC is text, so it must be CAST before ordering -- sorting it as a
     * string would put "9.3" above "10.2".
     */
    function sc_published_markers($conn, $abbr1, $tissue, $cell_type = '', $limit = 15)
    {
        $out = array();
        if (!$conn) {
            return $out;
        }
        $table = $abbr1 . '_cellmarker';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !sc_has_table($conn, $table)) {
            return $out;
        }
        $limit = max(1, (int)$limit);
        // The window orders by the cast, written out rather than named.
        // `log2FC` is a text column -- `text` on the live AMILL table -- so
        // `ORDER BY c.log2FC` sorts the string "9.5" above "10.2" and the rank
        // column ends up contradicting the number printed beside it.  Ordering
        // by the output alias `log2fc` does use the cast, because an
        // unqualified name in a window ORDER BY resolves against the select
        // list -- but that is one collision away from the same bug, since
        // qualifying it (as adding the JOIN's `c.` prefix did) silently puts
        // the text column back.  Spelling the cast out cannot be undone by
        // either, so the rank always matches the value shown.
        /* 这一列存的是**来源论文自己的**基因号（NV2.xxxxx / Amil_Amillepora##### /
           Ocupat_v02_G… / Spis_XP_…），和站内其它页面用的号对不上。LEFT JOIN
           sc_gene_refseq 取回站点号：页面上站点号作主显示、原号进括号，映射不上
           的（LEFT JOIN 后为 NULL）保持原号并在页面上标注「not mapped」。
           用 LEFT JOIN 而不是 INNER：没映射的基因也正是数据集里真实存在的基因，
           丢掉它们等于悄悄改小了 marker 表。 */
        $joined = sc_gene_refseq_ok($conn);
        $sql = "SELECT cell_type, gene, gene_refseq, symbol, log2fc, padj, pct_in, rn FROM ("
             . "  SELECT c.celltype AS cell_type, c.gene,"
             . "         " . ($joined ? "r.accession" : "NULL") . " AS gene_refseq,"
             . "         c.symbol,"
             . "         CAST(c.log2FC AS DECIMAL(12,4)) AS log2fc,"
             . "         c.FDR AS padj, CAST(c.pct1 AS DECIMAL(12,4)) AS pct_in,"
             . "         ROW_NUMBER() OVER (PARTITION BY c.celltype"
             . "              ORDER BY CAST(c.log2FC AS DECIMAL(12,4)) DESC,"
             . "                       c.gene) AS rn"
             . "    FROM `$table` c"
             . ($joined ? " LEFT JOIN sc_gene_refseq r ON r.abbr = ? AND r.sc_id = c.gene" : "")
             . "   WHERE c.tissue_dev = ?"
             . ($cell_type !== '' ? " AND c.celltype = ?" : "")
             . ") x WHERE rn <= $limit ORDER BY cell_type, rn";
        $st = @$conn->prepare($sql);
        if (!$st) {
            return $out;
        }
        /* 绑定顺序必须与 SQL 里 `?` 出现的顺序一致：JOIN 的 abbr 在最前，
           然后是 tissue_dev，最后才是可选的 celltype。 */
        $args = array();
        $types = '';
        if ($joined) {
            $args[] = (string)$abbr1;
            $types .= 's';
        }
        $args[] = (string)$tissue;
        $types .= 's';
        if ($cell_type !== '') {
            $args[] = (string)$cell_type;
            $types .= 's';
        }
        $st->bind_param($types, ...$args);
        $st->execute();
        $res = $st->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $out[] = array(
                    'cell_type' => $r['cell_type'],
                    'gene'      => $r['gene'],
                    'gene_refseq' => isset($r['gene_refseq']) ? (string)$r['gene_refseq'] : '',
                    'symbol'    => $r['symbol'],
                    'rank'      => (int)$r['rn'],
                    'log2fc'    => $r['log2fc'],
                    'padj'      => $r['padj'],
                    'pct_in'    => $r['pct_in'],
                    'score'     => null,   // not deposited by the source study
                );
            }
        }
        $st->close();
        return $out;
    }

    /**
     * True when every label is of the form "cluster N".
     *
     * Such a dataset has no cell-type annotation at all, and the page has to
     * say so rather than present numbered clusters as if they were cell types
     * -- Referee 3 point 10g asked precisely this of the Nematostella tentacle
     * figure, and getting it wrong is the mistake the viewer's manifest made.
     */
    function sc_named_only($celltypes)
    {
        foreach ($celltypes as $ct) {
            if (!preg_match('/^cluster\s*\d+$/i', $ct[0])) {
                return false;
            }
        }
        return true;
    }

    /**
     * dataset_id of the re-analysed dataset covering a published entry, or ''.
     *
     * Matched on the *figure*, not the species.  The species was the original
     * key and it was wrong: `singlecell_atlas` holds several datasets per
     * species (Nematostella has five), so a species lookup resolved to whichever
     * one happened to be assigned last and every Nematostella figure -- the 2
     * month animal, the neoplasm, the tentacle, the 24 hpf gastrula -- offered
     * "an interactive version of this dataset" pointing at the tentacle.  Two
     * of them pointed at datasets whose tissue does not appear in the figure at
     * all.  `published_figure` records the filename stem of the source study's
     * own UMAP, which is exactly what a published entry is built from, so one
     * stem has one meaning and cannot be ambiguous.
     *
     * Returns '' when the database predates the column -- an un-upgraded
     * install then shows every figure as static, which is the honest
     * degradation, rather than linking to the wrong dataset.
     */
    function sc_interactive_twin($conn, $entry)
    {
        static $byFigure = null;
        if ($byFigure === null) {
            $byFigure = array();
            foreach (sc_atlas($conn) as $id => $row) {
                // isset(), not a bare read: the column is NULL for every
                // dataset without a published figure, and trim(NULL) is a
                // deprecation on PHP 8 even though 7.4 quietly returns ''.
                $fig = isset($row['published_figure'])
                    ? trim($row['published_figure']) : '';
                if ($fig !== '') {
                    $byFigure[$fig] = $id;
                }
            }
        }
        $fig = isset($entry['stem']) ? trim($entry['stem']) : '';
        return ($fig !== '' && isset($byFigure[$fig])) ? $byFigure[$fig] : '';
    }

    /**
     * row number on sn_data.php  ->  dataset_id
     *
     * sn_data.php is driven by the pre-existing `singlecell` table, which has
     * no dataset_id column.  Meta/sra_metadata.json records which website row
     * each dataset was harvested from, so that number is the join key.
     */
    function sc_row_map($conn)
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = array();
        if (!sc_has_table($conn, 'singlecell_atlas_map')) {
            return $map;
        }
        $res = $conn->query("SELECT cnidosite_row, dataset_id FROM singlecell_atlas_map");
        if ($res) {
            while ($r = $res->fetch_row()) {
                $map[(int)$r[0]] = $r[1];
            }
        }
        return $map;
    }

    /** dataset_id -> atlas metadata row. */
    function sc_atlas($conn)
    {
        static $atlas = null;
        if ($atlas !== null) {
            return $atlas;
        }
        $atlas = array();
        if (!sc_has_table($conn, 'singlecell_atlas')) {
            return $atlas;
        }
        $res = $conn->query("SELECT * FROM singlecell_atlas");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                // Fill columns that a previously-installed schema may not have
                // yet.  The pages read these unconditionally, and shipping a
                // page that emits undefined-index warnings against an older
                // database is worse than shipping a blank cell.
                static $fields = array(
                    'bioproject', 'sra_study', 'geo_series', 'cell_type_source',
                    'reference_genome', 'genome_version', 'pipeline_version',
                    'annotation_provenance', 'updated_utc', 'asset_dir',
                    'published_figure', 'cluster_source', 'embedding_source',
                );
                foreach ($fields as $f) {
                    if (!array_key_exists($f, $r)) {
                        $r[$f] = '';
                    }
                }
                $atlas[$r['dataset_id']] = $r;
            }
        }
        return $atlas;
    }

    /** dataset_id -> QC row. */
    function sc_qc($conn, $dataset_id)
    {
        static $cache = array();
        if (isset($cache[$dataset_id])) {
            return $cache[$dataset_id];
        }
        $cache[$dataset_id] = null;
        if (!sc_has_table($conn, 'singlecell_qc')) {
            return null;
        }
        $st = $conn->prepare("SELECT * FROM singlecell_qc WHERE dataset_id = ?");
        $st->bind_param('s', $dataset_id);
        $st->execute();
        $res = $st->get_result();
        if ($res && $res->num_rows) {
            $cache[$dataset_id] = $res->fetch_assoc();
        }
        $st->close();
        return $cache[$dataset_id];
    }

    /**
     * Why a dataset was not re-analysed, as a sentence for the reader.
     *
     * `singlecell_qc.analysis_status` already records this, one row per
     * dataset, and it is the only place that knows: the values read
     * "not processed (count matrix not retrieved)", "not processed (deposit is
     * scATAC (peaks/bigWigs), not scRNA-seq)" and so on.  Until this helper
     * existed nothing on the site read the column, and sn_data.php printed one
     * hard-coded sentence -- "no count matrix in the public deposit" -- for
     * every unprocessed dataset, which is simply untrue for six of them.
     *
     * The parentheses nest ("... scATAC (peaks/bigWigs) ..."), so the detail is
     * cut from the first "(" to the LAST ")", not by a non-greedy regex that
     * would stop at the inner one.
     *
     * @param  array|null $qc sc_qc() 的一行，或 null（表里没有这个数据集）
     * @return string        一句以句号结尾的话
     */
    function sc_qc_unprocessed_reason($qc)
    {
        $status = ($qc !== null && isset($qc['analysis_status']))
                ? trim((string)$qc['analysis_status']) : '';
        if ($status === '') {
            return 'No filtering record has been deposited for this dataset, '
                 . 'so it could not be re-analysed.';
        }
        $open  = strpos($status, '(');
        $close = strrpos($status, ')');
        if ($open !== false && $close !== false && $close > $open + 1) {
            $detail = trim(substr($status, $open + 1, $close - $open - 1));
            if ($detail !== '') {
                /* 括号里是句子片段，首字母大写、末尾补句号，读起来才像话。 */
                return ucfirst($detail) . '.';
            }
        }
        return rtrim($status, '. ') . '.';
    }

    /** Cell types for a dataset, most abundant first. */
    function sc_celltypes($conn, $dataset_id)
    {
        if (!sc_has_table($conn, 'singlecell_celltype')) {
            return array();
        }
        $out = array();
        $st = $conn->prepare(
            "SELECT cell_type, n_cells, pct_cells FROM singlecell_celltype
              WHERE dataset_id = ? ORDER BY n_cells DESC"
        );
        $st->bind_param('s', $dataset_id);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $out[] = $r;
        }
        $st->close();
        return $out;
    }

    /**
     * The site header and navigation.
     *
     * $current is the basename of the calling page; it drives the `current`
     * class on the Single-cell menu item.
     */
    function sc_header($title, $current = '', $description = '')
    {
        ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title><?php echo sc_h($title); ?> - CnidoSite</title>
<meta name="keywords" content="" />
<meta name="description" content="<?php echo sc_h($description); ?>" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<link href="./viewer/cellatlas.css?v=<?php echo (int)@filemtime(__DIR__ . '/viewer/cellatlas.css'); ?>" rel="stylesheet" type="text/css" />
<link href="./sc_pages.css?v=<?php echo (int)@filemtime(__DIR__ . '/sc_pages.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
</head>

<?php /* No `onLoad="initDynamicOptionLists();"` here, and no DynamicOptionList.js.
       That library's printOptions() is a no-op in modern browsers, so the
       <select> is always empty in the server HTML; initDynamicOptionLists()
       then clears the <select> on load and rebuilds it from forValue()'s
       array, wiping the real options this module renders.  The module's three
       forms announce this in their own comments (cell_atlas.php,
       cell_marker.php, gene_exp.php) while this shared header still called it
       -- so on the live site, where the .js file exists, the selects were
       silently emptied instead of raising the ReferenceError a staged render
       shows.  Every <select> here is built server-side, so the call is pure
       loss. */ ?>
<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<div id="templatemo_menu_wrapper">
    <div id="templatemo_menu">
        <ul>
            <li><a href="/index.php">Home</a></li>
            <li><a href="#">Taxonomy</a>
                <ul>
                    <li><a href="/browse.php?class=all">All</a></li>
                    <li><a href="/browse.php?class=Cubozoa">Cubozoa</a></li>
                    <li><a href="/browse.php?class=Hexacorallia">Hexacorallia</a></li>
                    <li><a href="/browse.php?class=Octocorallia">Octocorallia</a></li>
                    <li><a href="/browse.php?class=Hydrozoa">Hydrozoa</a></li>
                    <li><a href="/browse.php?class=Myxozoa">Myxozoa</a></li>
                    <li><a href="/browse.php?class=Scyphozoa">Scyphozoa</a></li>
                    <li><a href="/browse.php?class=Staurozoa">Staurozoa</a></li>
                </ul>
            </li>
            <li><a href="/paleobiology.php">Paleobiology</a></li>
            <li><a href="#">Genome</a>
                <ul>
                    <li><a href="/genomeinfo.php">Genomic Data</a></li>
                    <li><a href="/search.php">Gene Search</a></li>
                    <li><a href="/busco.php">BUSCO Genes</a></li>
                    <li><a href="/TE.php">Transposable Elements</a></li>
                    <li><a href="/gene_family.php">TFs/Ubs</a></li>
                    <li><a href="/proteindomain.php">Protein Domain</a></li>
                    <li><a href="/domain_search.php">Functional Domain Search</a></li>
                    <li><a href="/go.php">Gene Ontology</a></li>
                    <li><a href="/interpro.php">InterPro</a></li>
                    <li><a href="/kegg.php">KEGG Pathway</a></li>
                    <li><a href="/genefamily.php">Gene Family</a></li>
                    <li><a href="/pan-geneset.php">Pan-geneset</a></li>
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li>
                    <li><a href="/microsynteny.php">Microsynteny Analysis</a></li>
                    <li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li>
                    <li><a href="/mitdata.php">Mitogenomic Data</a></li>
                </ul>
            <li><a href="#">Transcriptome</a>
                <ul>
                    <li><a href="/cytoscape/trans_data.php">Transcriptomic Data</a></li>
                    <li><a href="/trans_assembly.php">Transcriptome Assembly</a></li>
                    <li><a href="/cytoscape/network.php">Network Analysis</a></li>
                    <li><a href="/cytoscape/network_expression.php">Dynamic Expression View</a></li>
                </ul>
            <li><a href="#" class="current">Single-cell</a>
                <ul>
                    <li><a href="/sn_data.php"<?php if ($current === 'sn_data.php') echo ' class="current"'; ?>>Single-cell Data</a></li>
                    <li><a href="/cell_atlas.php"<?php if ($current === 'cell_atlas.php') echo ' class="current"'; ?>>Cell Atlas</a></li>
                    <li><a href="/cell_marker.php"<?php if ($current === 'cell_marker.php') echo ' class="current"'; ?>>Cell Marker</a></li>
                    <li><a href="/gene_exp.php"<?php if ($current === 'gene_exp.php') echo ' class="current"'; ?>>Gene Expression</a></li>
                </ul>
            </li>
            <li><a href="#">Proteome</a>
                <ul>
                    <li><a href="/proteomic_reprocessed.php">Proteomic Data</a></li>
                    <li><a href="/proteomic_reanalysis.php">Proteomic Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Epigenome</a>
                <ul>
                    <li><a href="/epigenomic_data.php">Epigenomic Data</a></li>
                    <li><a href="/DNA_methylation.php">DNA Methylation</a></li>
                    <li><a href="/miRNA_analysis.php">miRNA-seq Analysis</a></li>
                    <li><a href="/ATAC_analysis.php">ATAC-seq Analysis</a></li>
                    <li><a href="/DHS_analysis.php">DNase-seq Analysis</a></li>
                    <li><a href="/ChIP_analysis.php">ChIP-seq Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Metagenome</a>
                <ul>
                    <li><a href="/metagenomic_data.php">Metagenomic Data</a></li>
                    <li><a href="/MAGs.php">MAGs Catalog</a></li>
                </ul>
            </li>
            <li><a href="#">Phenotype</a><ul><li><a href="/phenotype.php?class=all">All</a></li><li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li><li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li><li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li><li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li><li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li></ul></li><li><a href="#">Tools</a>
                <ul>
                    <li><a href="/GSEA/GSEA.php">Gene Sets Analysis</a></li>
                    <li><a href="/blast/blast.php">BLAST</a></li>
                    <li><a href="/primer3plus/primer3.html">Primer Design</a></li>
                    <li><a href="/jbrowse.php">JBrowse</a></li>
                </ul>
            </li>
            <li><a href="/download.php">Download</a></li>
            <li><a href="#">Help</a>
                <ul>
                    <li><a href="/data_statistics.php">Statistics</a></li>
                    <li><a href="/tutorial.php">User Manual</a></li>
                    <li><a href="/submit_comments.php">Data Submit</a></li>
                    <li><a href="/contact.php" class="last">Contact Us</a></li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<div id="tempatemo_content_wrapper">
<div id="templatemo_content">
<div id="column">
        <?php
    }

    /** Page footer, keeping the site's existing Webpage_components.php. */
    function sc_footer()
    {
        echo '</div>' . "\n" . '</div>' . "\n" . '</div>' . "\n";
        if (file_exists(__DIR__ . '/Webpage_components.php')) {
            include __DIR__ . '/Webpage_components.php';
            print $footer;
        }
        echo "\n</body>\n</html>\n";
    }

    /**
     * 已发表图那一组的 tissue 是从图片文件名里切出来的机器词
     * （NVECT_WholeOrganism_UMAP_1.png → "WholeOrganism"），照原样印会在物种名
     * 后面露出一个驼峰串。这里只做展示层的整理：拆驼峰、拆数字与字母的交界、
     * 句首大写；整词含全大写缩写（DMSO 之类）的原样返回，免得把缩写改写掉。
     * **只用于显示** —— 查细胞类型与标记表用的那份键值不受影响。
     */
    /**
     * 下拉里 stage 那一段的写法。stage 自己带括号时（HVULG 是 "Adult (whole
     * animal)"，NVECT_embryo 是 "8, 10 and 12 h after fertilisation (deposit labels
     * 8h/10h/12h)"）再套一层括号会印成 "(Adult (whole animal))"，括号配不上对；
     * 这种情况改用中点分隔，标签其余部分不变。**中点必须写 U+00B7 本字** ——
     * 这串最后要过 sc_h()（htmlspecialchars），写 &middot; 会被再转义一次印成字面量。
     */
    function sc_stage_suffix($stage)
    {
        $stage = trim((string)$stage);
        if ($stage === '') { return ''; }
        return (strpos($stage, '(') !== false || strpos($stage, ')') !== false)
            ? " · " . $stage
            : " (" . $stage . ")";
    }

    function sc_pub_tissue($t)
    {
        $t = trim((string)$t);
        if ($t === '') { return $t; }
        if (preg_match('/[A-Z]{2,}/', $t)) { return $t; }
        $s = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $t);
        $s = preg_replace('/(?<=[0-9])(?=[a-zA-Z])/', ' ', $s);
        return ucfirst(strtolower($s));
    }

    /**
     * $a 的词是否全部出现在 $b 里（忽略大小写、标点与顺序）。用来判断两个字段
     * 是不是在说同一件事 —— 例如 ACOER_lifecycle 的 tissue_organ 是
     * "Whole life cycle (polyp, strobila, ephyra, medusa)"，stage 是
     * "polyp - strobila - ephyra - medusa"，两个都印出来就是连着两串括号。
     */
    function sc_text_repeats($a, $b)
    {
        $tok = function ($s) {
            $s = preg_replace('/[^a-z0-9]+/', ' ', strtolower((string)$s));
            return array_unique(array_filter(explode(' ', $s), 'strlen'));
        };
        $ta = $tok($a);
        return $ta ? (count(array_diff($ta, $tok($b))) === 0) : false;
    }

    /**
     * A dataset picker, shared by the atlas, marker and expression pages.
     *
     * $hide_twinned withholds the published figures that a re-analysis now
     * covers, so a dataset is named once rather than twice with two different
     * labels.  It defaults on, and cell_marker.php is the one caller that turns
     * it off: a published figure and a re-analysis are two views of the same
     * cells, but a published *marker table* is a separate deposit with no
     * re-analysed equivalent, so hiding its entry there would not fold content
     * in -- it would drop the only route to it.
     */
    function sc_dataset_picker($conn, $selected, $form_action, $extra_hidden = array(),
                               $include_published = false, $hide_twinned = true)
    {
        $atlas = sc_atlas($conn);
        $pub = $include_published ? sc_published_entries($conn) : array();
        if (!$atlas && !$pub) {
            return;
        }
        echo '<form method="get" action="' . sc_h($form_action) . '" class="sc-picker">';
        foreach ($extra_hidden as $k => $v) {
            echo '<input type="hidden" name="' . sc_h($k) . '" value="' . sc_h($v) . '">';
        }
        echo '<label for="dataset">Dataset</label>';
        echo '<select name="dataset" id="dataset" onchange="this.form.submit()">';
        if ($atlas) {
            echo '<optgroup label="Re-analysed — interactive viewer">';
            foreach ($atlas as $id => $row) {
                $label = $row['species'];
                if ($row['tissue_organ'] !== '') {
                    $label .= ' — ' . $row['tissue_organ'];
                }
                if ($row['stage'] !== '' && !sc_text_repeats($row['stage'], $row['tissue_organ'])) {
                    $label .= sc_stage_suffix($row['stage']);
                }
                $label .= ' [' . number_format((int)$row['n_cells_final']) . ' cells]';
                $sel = ($id === $selected) ? ' selected="selected"' : '';
                echo '<option value="' . sc_h($id) . '"' . $sel . '>' . sc_h($label) . '</option>';
            }
            echo '</optgroup>';
        }
        if ($pub) {
            // Kept in a separate group and labelled, so nobody reads a figure
            // from the source publication as something we recomputed.
            //
            // A figure that has been re-analysed is not offered here at all:
            // its entry in the group above *is* that dataset, and listing it
            // twice would ask the visitor to choose between two names for one
            // thing.  The published panels remain reachable -- the interactive
            // page shows them beside the re-analysis, which is where the
            // comparison belongs.  The exception is a figure that is selected
            // right now, which is kept so an incoming link to it does not leave
            // the select showing some other dataset as if it were the target.
            $rows = array();
            foreach ($pub as $id => $e) {
                if ($hide_twinned && $id !== $selected
                        && sc_interactive_twin($conn, $e) !== '') {
                    continue;
                }
                $rows[$id] = $e;
            }
            if ($rows) {
                echo '<optgroup label="As published — static figure">';
                foreach ($rows as $id => $e) {
                    $label = $e['species'] . ' — ' . sc_pub_tissue($e['tissue']);
                    $sel = ($id === $selected) ? ' selected="selected"' : '';
                    echo '<option value="' . sc_h($id) . '"' . $sel . '>' . sc_h($label) . '</option>';
                }
                echo '</optgroup>';
            }
        }
        echo '</select></form>';
    }

    /**
     * The correct default dataset: the requested one if it exists, else the
     * largest one that actually names its cell types.
     *
     * Ranking on size alone landed on the Hydra atlas, whose features are
     * transcript IDs with non-cnidarian BLAST suffixes -- the marker panel
     * named nothing there, so it ships as `cluster_only` with no cell types.
     * As the first thing a visitor sees that reads as a broken page.  A dataset
     * that can be labelled is the better default; size breaks the tie.
     */
    function sc_default_dataset($conn, $requested)
    {
        $atlas = sc_atlas($conn);
        if (!$atlas) {
            return '';
        }
        if ($requested !== '' && isset($atlas[$requested])) {
            return $requested;
        }
        /* 站点默认物种是 Nematostella vectensis，本模块跟着走：优先取该物种里
           细胞数最多、且有命名注释的数据集。缺了这一步，全局最大会是
           Aurelia coerulea 的 ACOER_lifecycle（63,230 个细胞），而 NVECT 里
           最大的 NVECT_whole_adult 有 51,866 个 —— 后者才是要展示的默认。
           本物种一个可用的都没有时，退回下面的全局逻辑（原行为）。 */
        $pref_species = 'Nematostella vectensis';
        $pref = '';
        $pref_n = -1;
        foreach ($atlas as $id => $row) {
            if ($row['species'] !== $pref_species) { continue; }
            if ($row['annotation_provenance'] === 'cluster_only') { continue; }
            $n = (int)$row['n_cells_final'];
            if ($n > $pref_n) {
                $pref_n = $n;
                $pref = $id;
            }
        }
        if ($pref !== '') {
            return $pref;
        }

        $best = '';
        $best_n = -1;
        $best_named = '';
        $best_named_n = -1;
        foreach ($atlas as $id => $row) {
            $n = (int)$row['n_cells_final'];
            $named = ($row['annotation_provenance'] !== 'cluster_only');
            if ($named && $n > $best_named_n) {
                $best_named_n = $n;
                $best_named = $id;
            }
            if ($n > $best_n) {
                $best_n = $n;
                $best = $id;
            }
        }
        return $best_named !== '' ? $best_named : $best;
    }

    /**
     * The site's species table, read once.
     *
     * @return array list of array('abbr'=>, 'abbr1'=>, 'species'=>)
     */
    function sc_species_rows($conn)
    {
        static $rows = null;
        if ($rows !== null) {
            return $rows;
        }
        $rows = array();
        if ($conn && sc_has_table($conn, 'abbr')) {
            $res = @$conn->query("SELECT abbr, abbr1, species FROM abbr");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $rows[] = array(
                        'abbr'    => isset($r['abbr']) ? (string)$r['abbr'] : '',
                        'abbr1'   => isset($r['abbr1']) ? (string)$r['abbr1'] : '',
                        'species' => isset($r['species']) ? (string)$r['species'] : '',
                    );
                }
            }
        }
        return $rows;
    }

    /**
     * Case- and punctuation-insensitive key for a species name or code.
     *
     * The site is inconsistent about how a species is written (`Nematostella
     * vectensis`, `Nematostella_vectensis`, `NVECT`, `N. vectensis`), and all
     * four reach these pages. Comparing keys rather than strings is what lets a
     * latin name from the species portal and a short code from the menu resolve
     * to the same species.
     */
    function sc_species_key($s)
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string)$s));
    }

    /**
     * The species the visitor arrived with, and everything this module has for
     * it.
     *
     * The site's own navigation has always linked into this module as
     * `?species=<abbr1>`: `includes/modlinks.php` sends
     * `cell_atlas.php?species=NVECT` (and the same for the marker, expression
     * and index pages), while the species portal sends the latin name.  These
     * pages read only `dataset=`, so every one of those links was ignored and
     * the page fell back to the default dataset -- clicking "Cell Atlas" under
     * Hydra opened Acropora, and nothing on the page said the species had been
     * dropped.
     *
     * That is worse than an error, so the two outcomes are returned apart:
     *
     *   null                  no `species=` parameter at all -- the caller's
     *                         normal default applies
     *   array('ids' => ...)   the parameter names a species; `ids` is empty when
     *                         the module holds nothing for it, and the caller
     *                         must then say so rather than open another
     *                         species' data.
     *
     * Accepts what the site's other modules accept: the five-letter abbr1 the
     * menu sends, the `abbr` code, or the latin binomial.
     */
    function sc_requested_species($conn)
    {
        if (!isset($_GET['species'])) {
            return null;
        }
        $raw = trim((string)$_GET['species']);
        if ($raw === '') {
            return null;
        }

        $want = sc_species_key($raw);
        $abbr1 = '';
        $species = '';
        foreach (sc_species_rows($conn) as $r) {
            if (sc_species_key($r['abbr1']) === $want
                    || sc_species_key($r['abbr']) === $want
                    || sc_species_key($r['species']) === $want) {
                $abbr1 = $r['abbr1'];
                $species = $r['species'];
                break;
            }
        }
        if ($abbr1 === '' && $species === '') {
            // Not in `abbr` (or that table is absent).  The parameter is still
            // usable: the published figures carry their own abbr1 in the
            // filename, so those can be matched against it directly.
            $abbr1 = strtoupper($raw);
            $species = $raw;
        }

        $ids = array();
        // Re-analysed datasets come first.  For a species that has both, the
        // interactive one is the better thing to open -- it is the one that
        // answers the referee point about static images.
        foreach (sc_atlas($conn) as $id => $row) {
            $key = sc_species_key($row['species']);
            if ($key !== '' && ($key === sc_species_key($species) || $key === $want)) {
                $ids[] = $id;
            }
        }
        foreach (sc_published_entries($conn) as $id => $e) {
            if (sc_species_key($e['abbr1']) === sc_species_key($abbr1)) {
                $ids[] = $id;
            }
        }
        return array('abbr1' => $abbr1, 'species' => $species, 'ids' => $ids);
    }

    /**
     * Which species this module actually holds data for, by short code.
     *
     * Built from the data -- the published figures on disk and the atlas table
     * -- rather than hardcoded, so the "we have nothing for that species"
     * notice cannot promise a species the module does not have, or omit one it
     * does.
     *
     * The `n` it returns counts published figures and re-analysed datasets
     * separately, so a tissue that has both counts twice.  No caller reads it
     * today; anything that starts to should count entries rather than add these
     * up, because since the twinned figures stopped being separate picker
     * options the two sets overlap by design.
     *
     * @return array abbr1 => array('species'=>, 'n'=>count of datasets)
     */
    function sc_species_index($conn)
    {
        static $index = null;
        if ($index !== null) {
            return $index;
        }
        $index = array();
        $codes = array();
        foreach (sc_species_rows($conn) as $r) {
            if ($r['species'] !== '') {
                $codes[sc_species_key($r['species'])] = $r['abbr1'];
            }
        }
        foreach (sc_published_entries($conn) as $e) {
            $k = $e['abbr1'];
            if (!isset($index[$k])) {
                $index[$k] = array('species' => $e['species'], 'n' => 0);
            }
            $index[$k]['n']++;
        }
        foreach (sc_atlas($conn) as $row) {
            // singlecell_atlas has no abbr1 column, so the short code is looked
            // up from the latin name; without it the name is its own key.
            $key = sc_species_key($row['species']);
            $k = isset($codes[$key]) ? $codes[$key] : $row['species'];
            if (!isset($index[$k])) {
                $index[$k] = array('species' => $row['species'], 'n' => 0);
            }
            $index[$k]['n']++;
        }
        ksort($index);
        return $index;
    }

    /**
     * "This module has nothing for that species" -- shown in place of another
     * species' data.
     *
     * Lists what the module does cover, each linking to itself by species so the
     * visitor is one click from somewhere useful, and points at the index for
     * the datasets that were not re-analysed here.
     */
    function sc_species_missing($conn, $species, $page)
    {
        echo '<div class="sc-callout sc-callout-warn">';
        echo '<b>No single-cell data for <i>' . sc_h($species) . '</i> in this module.</b>';
        echo '<div style="margin-top:6px">CnidoSite holds single-cell data for ';
        $parts = array();
        foreach (sc_species_index($conn) as $abbr1 => $e) {
            $parts[] = '<a href="' . sc_h($page . '?species=' . urlencode($abbr1)) . '">'
                     . sc_h($e['species']) . '</a>';
        }
        echo implode(', ', $parts) . '. The full dataset list, including the ones not '
           . 're-analysed here, is on <a href="sn_data.php">Single-cell Data</a>.';
        echo '</div></div>';
    }

    /**
     * Notice for a species that has published figures but no count matrix.
     *
     * Separate from sc_species_missing(): the species *is* covered, so telling
     * the visitor it is not would be false.  What it lacks is the matrix an
     * expression plot is computed from.
     */
    function sc_species_no_matrix($conn, $species, $marker_url)
    {
        // Counted, not written down.  This said "two" for as long as two was
        // the answer, and would have gone on saying it: the number lives in the
        // database, the sentence lived in the source, and nothing tied them
        // together.  A sentence that understates our coverage is not a harmless
        // staleness -- it is the one thing on the page a reader is meant to take
        // as the state of the resource.
        $n = count(sc_atlas($conn));
        $having = ($n > 0)
            ? 'CnidoSite has re-analysed ' . $n . ' of the deposited datasets'
            : 'CnidoSite has not re-analysed any of the deposited datasets yet';
        echo '<div class="sc-callout sc-callout-info">';
        echo '<b>No expression matrix for <i>' . sc_h($species) . '</i>.</b>';
        echo '<div style="margin-top:6px">' . $having
           . '; the rest, including this species, are published as the source '
           . 'study\'s own figures. A per-cell expression plot is computed from a count '
           . 'matrix, so there is nothing to draw here for this species &mdash; but its '
           . 'marker genes are listed on the <a href="' . sc_h($marker_url) . '">marker '
           . 'page</a>.</div></div>';
    }
}
?>
