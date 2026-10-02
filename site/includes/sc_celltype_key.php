<?php
/**
 * "What does this cell-type label stand for?" -- the abbreviation key for the
 * one Cell Atlas dataset whose names are abbreviations.
 *
 * HVULG_siebert_atlas is the Hydra cell-type atlas of Siebert et al.  Its cell
 * types are published under the study's own short labels (`ecEp_SC1`, `i_nb3`,
 * `i_n_ec4`), and the page printed that list with nothing to read it against:
 * a reader could see that `i_nb3` holds 758 cells and still not know the cells
 * are nematoblasts of the interstitial lineage.  Every other dataset on the
 * atlas is named in words.
 *
 * The expansions are the study's, not this site's.  They come from the study's
 * own analysis scripts -- its abbreviation key in SA01, and the pair of
 * parallel vectors in SA06 that spell the same 41 labels out long -- and are
 * built into `data/sc_celltype_key.json` by
 * `/home/jackie/sc_ingest/build_sc_celltype_key.py`, which fails the build if
 * a label in the database is not in the study's own vectors.  Nothing here is
 * inferred from the label's spelling: `unassigned`, the one label the study
 * never defines, is printed as unresolved rather than guessed at.
 *
 * The expansion is read where the abbreviation is, not in a block of its own:
 * the viewer's Groups list names each cell type in the study's shorthand, so
 * it is the one place a reader meets `i_n_ec4` with no way to decode it.  The
 * key is therefore handed to the viewer as data -- one `brief` per label, to
 * sit beside that label's name in its own row, and one `title` per label for
 * the row's tooltip, which is where the full expansion and the study's own
 * caveat about the doublet-like categories are read.
 *
 * Data file shape:
 *   key          the study's abbreviation key, verbatim
 *   qualifiers   {db,id,mp,pd} -> the study's own expansion of each
 *   source       where the expansions were read from
 *   source_short the same, in the one line the viewer's list can carry
 *   labels[]     {label, study_name|null, english|null, brief, qualifier,
 *                 qualifier_means}
 *   unassigned_note   what the one undefined label is made of, measured on
 *                     this dataset's own assets (counts, libraries, spread)
 */

if (!defined('CNIDO_CTKEY_JSON')) {
    define('CNIDO_CTKEY_JSON', dirname(__DIR__) . '/data/sc_celltype_key.json');
}

/** The whole document, or an empty array when the file is missing -- which
 *  renders no key at all, the safe failure: the page keeps what it had. */
function cnido_ctkey_all() {
    static $doc = null;
    if ($doc !== null) { return $doc; }
    $doc = array();
    if (is_readable(CNIDO_CTKEY_JSON)) {
        $j = json_decode(file_get_contents(CNIDO_CTKEY_JSON), true);
        if (is_array($j) && !empty($j['labels'])) { $doc = $j; }
    }
    return $doc;
}

/** One dataset's key, or null when this dataset's labels are already words. */
function sc_celltype_key($dataset_id) {
    $doc = cnido_ctkey_all();
    return (isset($doc['dataset_id']) && $doc['dataset_id'] === $dataset_id)
        ? $doc : null;
}

/**
 * The key as the viewer needs it, or null when this dataset has no key.
 *
 * Returns array('labels' => array(label => array('brief', 'title')),
 *               'note'   => the footnote for the foot of the list)
 *
 * `brief` is the short expansion that fits beside the name in its row; the
 * abbreviation is already on screen there, so it does not repeat the lineage.
 * `title` is the row's tooltip: the full expansion, the study's own meaning of
 * a parenthesised qualifier, and -- for those qualifiers -- the study's own
 * warning that the three doublet-like categories cannot always be told apart.
 *
 * Every label of the dataset is covered: a label the key cannot expand would
 * otherwise be the one row in the list left looking deliberate.
 */
function sc_celltype_key_viewer($dataset_id) {
    $doc = sc_celltype_key($dataset_id);
    if ($doc === null) { return null; }

    $q = isset($doc['qualifiers']) ? $doc['qualifiers'] : array();
    $caveat = isset($doc['doublet_caveat']) ? $doc['doublet_caveat'] : '';
    $labels = array();
    foreach ($doc['labels'] as $r) {
        $t = $r['english'] !== null ? $r['english'] : '';
        if ($t === '') {
            /* The one label the study does not define.  The tooltip is where
               the measured facts about it are read, so it says what the row is
               rather than only what it is not. */
            $un = isset($doc['unassigned_note']) ? $doc['unassigned_note'] : array();
            if (!empty($un['n_cells'])) {
                $t = 'Not one of the names the study publishes, and a search of '
                   . 'all ' . number_format((int)$un['code_files_searched'])
                   . ' of its analysis scripts finds it nowhere. '
                   . number_format((int)$un['n_cells']) . ' cells ('
                   . $un['pct'] . '% of the dataset), '
                   . $un['top_library_share'] . '% of them from the two male '
                   . 'libraries ('
                   . implode(', ', array_column($un['top_libraries'], 'sample'))
                   . '), spread over ' . (int)$un['n_clusters_with'] . ' of the '
                   . (int)$un['n_clusters_total'] . ' clusters, and its top '
                   . 'marker genes are histones and ribosomal proteins -- no '
                   . 'cell state of its own. Shown under the name the data '
                   . 'carries.';
            }
        } elseif ($r['qualifier'] !== null && isset($q[$r['qualifier']])) {
            $t .= ' (' . $r['qualifier'] . ': ' . $q[$r['qualifier']] . ').';
            if ($caveat !== '') { $t .= ' ' . $caveat; }
        }
        $labels[$r['label']] = array('brief' => $r['brief'], 'title' => $t);
    }

    /* One line at the foot of the list, not a section of its own: where the
       expansions come from, and the one label they cannot cover.  The counts
       of that label are here rather than only in its tooltip because a reader
       who never hovers still has to know the row is undefined. */
    $src = isset($doc['source_short']) ? $doc['source_short'] : $doc['source'];
    $note = 'Expansions are ' . $src . '.';
    $un = isset($doc['unassigned_note']) ? $doc['unassigned_note'] : array();
    if (!empty($un['n_cells'])) {
        /* A literal em dash, not an entity: the viewer writes this through
           textContent, where "&mdash;" would be five characters of source. */
        $note .= ' unassigned is the one name the study never defines — '
               . number_format((int)$un['n_cells']) . ' cells ('
               . $un['pct'] . '%), ' . $un['top_library_share'] . '% of them '
               . 'from the two male libraries, over '
               . (int)$un['n_clusters_with'] . ' of the '
               . (int)$un['n_clusters_total'] . ' clusters.';
    }

    return array('labels' => $labels, 'note' => $note);
}

/** The key as a JSON literal for the viewer's options, or '' when this dataset
 *  has no key.  Hex-escaped because it is written inside a <script> block. */
function sc_celltype_key_json($dataset_id) {
    $v = sc_celltype_key_viewer($dataset_id);
    if ($v === null) { return ''; }
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
                            | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
