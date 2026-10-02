<?php
/* ---------------------------------------------------------------------------
 * Cross-links between the re-processed proteomes and the site's own tables.
 *
 * These are two complementary page sets, and neither may replace the other:
 *
 *   /proteomic_analysis.php   the original CnidoSite tables, keyed by data-table
 *                             prefix (EDIAP, NVECT, Stylophora_pistillata, ...)
 *   /proteomic_reprocessed.php  the rebuild, keyed by the Latin name in
 *   /proteomic_reanalysis.php   proteomic_datasets.species
 *   /proteomic_dataset.php
 *
 * The rebuild can only cover a species that has a reference proteome, and a
 * reference proteome is built from a transcriptome assembly. Nine species have
 * none, so no search space ever existed for them: they are absent from the
 * rebuild but present in the original tables. Replacing the original pages with
 * a redirect therefore deletes those species from the site -- tried on
 * 2026-09-26 and reverted the same day. See 5.web/INSTALL.md step 6.
 *
 * The two vocabularies do not overlap, so a link must translate. Only mappings
 * verified to return rows on BOTH sides are listed; everything else falls back
 * to the unfiltered entry page, because a species filter that matches nothing
 * renders a page that reads as "no data" rather than "wrong key".
 * ------------------------------------------------------------------------ */

/**
 * Species with no transcriptome assembly, hence no reference proteome, hence no
 * page in the rebuild. Hard-coded on purpose: this is a statement about which
 * assemblies exist, not something the proteomics tables can be asked.
 */
function cnido_proteomic_uncovered()
{
    return array(
        'Antipathes griggi',
        'Buddenbrockia plumatellae',
        'Calvadosia cruxmelitensis',
        'Myxobilatus gasterostei',
        'Polypodium hydriforme',
        'Stichopathes sp.',
        'Stylophora pistillata',
        'Breviolum minutum',        /* symbiont dataset */
        'Durusdinium trenchii',     /* symbiont dataset */
    );
}

/** Rebuild species (Latin name) => original table key, or '' if none. */
function cnido_proteomic_original_key($species)
{
    static $m = array(
        'Aiptasia sp.'          => 'Aiptasia_sp',
        'Exaiptasia diaphana'   => 'EDIAP',
        /* E. pallida and E. diaphana are one organism here (PROTEOME_ALIAS in
           cnido_common.py), and the site holds a single EDIAP table for both. */
        'Exaiptasia pallida'    => 'EDIAP',
        'Nematostella vectensis' => 'NVECT',
        /* One combined search space here, three separate tables over there. */
        'Myxobolus honghuensis; Thelohanellus kitauei; Myxobolus wulii' => 'MHONG',
    );
    $species = (string)$species;
    return isset($m[$species]) ? $m[$species] : '';
}

/** Original table key => rebuild species (Latin name), or '' if none. */
function cnido_proteomic_rebuild_species($key)
{
    static $m = array(
        'Aiptasia_sp'      => 'Aiptasia sp.',
        'EDIAP'            => 'Exaiptasia diaphana',
        'NVECT'            => 'Nematostella vectensis',
        'MHONG'            => 'Myxobolus honghuensis; Thelohanellus kitauei; Myxobolus wulii',
        'TKITA'            => 'Myxobolus honghuensis; Thelohanellus kitauei; Myxobolus wulii',
        'Myxobolus_wulii'  => 'Myxobolus honghuensis; Thelohanellus kitauei; Myxobolus wulii',
    );
    $key = (string)$key;
    return isset($m[$key]) ? $m[$key] : '';
}

/** The uncovered species as prose, with italics on the Latin names. */
function cnido_proteomic_uncovered_html()
{
    $out = array();
    foreach (cnido_proteomic_uncovered() as $s) {
        $out[] = preg_match('/ (sp\.|spp\.)$/', $s)
               ? '<i>' . htmlspecialchars(preg_replace('/ sp\.$/', '', $s),
                                          ENT_QUOTES, 'UTF-8') . '</i> sp.'
               : '<i>' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '</i>';
    }
    $last = array_pop($out);
    return implode(', ', $out) . ' and ' . $last;
}

/**
 * The note shown on the rebuild's pages.
 *
 * @param string $curSpecies  species currently in view, '' if none
 * @param string $class       the note class this page already uses
 */
function cnido_proteomic_note_rebuild($curSpecies = '', $class = 'pa-note')
{
    $key = cnido_proteomic_original_key($curSpecies);
    $h  = '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">';
    $h .= '<b>These pages do not cover every species.</b> '
        . 'Re-processing needs a reference proteome, and a reference proteome is built '
        . 'from a transcriptome assembly. None was available for '
        . cnido_proteomic_uncovered_html()
        . ', so no search space existed for them. Their data is in the original '
        . 'CnidoSite tables at <a href="/proteomic_analysis.php">Proteomic Analysis</a>. '
        . 'The two page sets are complementary &mdash; this one does not replace that one.';
    if ($key !== '') {
        $h .= '<div style="margin-top:4px"><i>'
            . htmlspecialchars($curSpecies, ENT_QUOTES, 'UTF-8')
            . '</i> is also in the original tables: <a href="/proteomic_analysis.php?species='
            . urlencode($key) . '">see its whole-proteome table</a>.</div>';
    }
    return $h . '</div>';
}

/**
 * The note shown on the original pages.
 *
 * @param string $specie      the page's current species key, '' if none
 * @param string $class       the note class this page already uses
 */
function cnido_proteomic_note_original($specie = '', $class = 'gd-notice gd-info')
{
    $species = cnido_proteomic_rebuild_species($specie);
    $h  = '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" style="margin:10px 0">';
    $h .= '<b>The re-processed proteomes are on separate pages.</b> '
        . 'A single documented pipeline (Comet + Percolator, 1% PSM-level FDR) was run '
        . 'over the PRIDE datasets in this catalogue, with per-dataset search parameters '
        . 'and the provenance of each search space; see '
        . '<a href="/proteomic_reprocessed.php">Proteomic Data (re-processed)</a>.';
    if ($species !== '') {
        $h .= '<div style="margin-top:4px"><i>'
            . htmlspecialchars($species, ENT_QUOTES, 'UTF-8')
            . '</i> is also in that set: <a href="/proteomic_reanalysis.php?species='
            . urlencode($species) . '">see its re-processed proteins</a>.</div>';
    }
    $h .= '<div style="margin-top:4px">This page keeps the original CnidoSite tables, which '
        . 'also cover nine species the re-processing does not (no transcriptome assembly '
        . 'was available for them).</div>';
    return $h . '</div>';
}
