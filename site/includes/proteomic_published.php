<?php
/**
 * The depositing publication's own results, for the proteomic datasets whose
 * CnidoSite re-analysis identified no protein at the 1% false-discovery rate.
 *
 * A reader who lands on one of those datasets is told why the table is empty --
 * which is correct and useless on its own.  The question actually asked is
 * "so is anything known about this sample?", and for these six deposits the
 * answer is yes: the authors reported their own search in the paper's tables.
 * That is what this card shows, attributed and kept visually separate, because
 * the two result sets are not interchangeable -- engine, search space and
 * significance threshold are the authors', so a row here cannot be read against
 * a row of the re-analysis.
 *
 * Data layer: `data/proteomic_published.json`, written by
 * `/var/www/cnidosite-tools/published/build_published.py` from copies of the
 * publications' supplementary files held next to that script.  It is a static
 * file rather than a table because nothing queries it and because the two
 * papers' result tables do not share a column set -- each dataset therefore
 * carries its own `columns` spec and this file renders it generically.
 *
 * Text protocol for that JSON, which the escaping below depends on:
 *   - `source`, `fraction`, `empty_note`, `other_fraction.text` are prose
 *     written by the generator and may carry HTML entities (&ldquo; etc.).
 *     They are printed as markup.
 *   - every other string is data transcribed from a supplementary file, or a
 *     literal written by the generator as plain text.  Those go through
 *     cnido_pub_e().
 */

if (!defined('CNIDO_PUBLISHED_JSON')) {
    define('CNIDO_PUBLISHED_JSON', dirname(__DIR__) . '/data/proteomic_published.json');
}

/** Escape a transcribed value.  Kept local so the module does not depend on the
 *  page's own helper being loaded first. */
function cnido_pub_e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** The whole document, or an empty array if the file is absent or unreadable.
 *  A missing file renders no card, which is the safe failure: the page keeps
 *  the explanation it already had. */
function cnido_published_all() {
    static $doc = null;
    if ($doc !== null) { return $doc; }
    $doc  = array();
    $file = CNIDO_PUBLISHED_JSON;
    if (is_readable($file)) {
        $j = json_decode(file_get_contents($file), true);
        if (is_array($j) && isset($j['datasets']) && is_array($j['datasets'])) {
            $doc = $j;
        }
    }
    return $doc;
}

/** One dataset's published result, or null when the publication reports nothing
 *  for it (or it is not one of the datasets covered here). */
function cnido_published_for($dataset_id) {
    $doc = cnido_published_all();
    return isset($doc['datasets'][$dataset_id]) ? $doc['datasets'][$dataset_id] : null;
}

/** A count as the publication prints it: thousands separated, but a small
 *  decimal left alone -- emPAI 0.97 must not become "1". */
function cnido_pub_num($v) {
    if ($v === null || $v === '') { return ''; }
    if (is_int($v)) { return number_format($v); }
    if (is_float($v)) {
        if (floor($v) == $v && abs($v) >= 1000) { return number_format($v); }
        return rtrim(rtrim(sprintf('%.2f', $v), '0'), '.');
    }
    return (string)$v;
}

/** One cell of a published table.
 *
 *  `kind` comes from the dataset's own column spec:
 *    score  the Mascot score, and the only cell that is shaded
 *    desc   the description -- left aligned and allowed to wrap, like the
 *           Description column of the re-analysis table
 *    num    a count, right-read but centred
 *    text   anything else, printed as it stands (e-values, database names)
 *
 *  The score shading is relative to the highest score *in this table* and is
 *  deliberately not a threshold: the papers use different significance cuts
 *  (p < 0.05 for the Glu-C sheets, p < 0.0066 for the trypsin one, a flat score
 *  cut-off of 56 for the bacteria-only search), so a fixed cut-off drawn across
 *  the rows would be an invention.  The footnote says which scale is in use.
 */
function cnido_pub_cell($col, $row, $maxscore) {
    $k = $col['k'];
    $v = isset($row[$k]) ? $row[$k] : null;
    if ($v === null || $v === '') {
        return '<td class="' . ($col['kind'] === 'desc' ? 'desc' : 'num')
             . '"><span class="pa-none">&mdash;</span></td>';
    }

    if ($col['kind'] === 'score') {
        /* 0.06 to 0.30 of an indigo wash: enough to order the rows at a glance,
           light enough that 13px black text stays legible on every step. */
        $frac = $maxscore > 0 ? min(1.0, (float)$v / $maxscore) : 0;
        $alpha = number_format(0.06 + 0.24 * $frac, 3);
        return '<td class="num pubscore" style="background:rgba(67,56,202,' . $alpha . ')">'
             . '<b>' . cnido_pub_num($v) . '</b></td>';
    }
    if ($col['kind'] === 'desc') {
        return '<td class="desc">' . cnido_pub_e($v) . '</td>';
    }
    if ($col['kind'] === 'num') {
        return '<td class="num">' . cnido_pub_e(cnido_pub_num($v)) . '</td>';
    }
    return '<td class="num">' . cnido_pub_e($v) . '</td>';
}

/** The published table itself, built from the dataset's own column spec. */
function cnido_pub_table($pub) {
    $cols = $pub['columns'];
    $rows = $pub['rows'];
    $max  = isset($pub['max_score']) ? (float)$pub['max_score'] : 0;

    $out = '<table class="gridtable pa-pubtab">' . "\n<thead><tr>";
    foreach ($cols as $c) {
        $out .= '<th style="width:' . cnido_pub_e($c['w']) . '%">'
              . cnido_pub_e($c['label']) . '</th>';
    }
    $out .= "</tr></thead>\n<tbody>\n";
    foreach ($rows as $r) {
        $out .= '<tr>';
        foreach ($cols as $c) { $out .= cnido_pub_cell($c, $r, $max); }
        $out .= "</tr>\n";
    }
    $out .= "</tbody>\n</table>\n";
    return $out;
}

/** The search conditions, as the publication's own methods/supplementary block
 *  states them.  Rendered with the page's existing `.pa-grid`/`.pa-cell` idiom
 *  so this reads as the same kind of fact as the re-analysis parameters. */
function cnido_pub_searched($s) {
    $order = array(
        'engine'     => 'Search engine',
        'type'       => 'Search type',
        'instrument' => 'Instrument',
        'enzyme'     => 'Enzyme',
        'precursor'  => 'Precursor tolerance',
        'fragment'   => 'Fragment tolerance',
        'missed_cleavages' => 'Missed cleavages',
        'fixed'      => 'Fixed modification',
        'variable'   => 'Variable modifications',
        'significance' => 'Significance',
        'spectra'    => 'MS/MS spectra searched',
    );
    $out = '<div class="pa-grid pa-pub-srch">';
    foreach ($order as $k => $label) {
        if (!isset($s[$k]) || $s[$k] === '') { continue; }
        $v = $s[$k];
        if ($k === 'spectra' && is_numeric($v)) { $v = number_format((int)$v); }
        $out .= '<div class="pa-cell"><b>' . cnido_pub_e($label) . '</b><span>'
              . cnido_pub_e($v) . '</span></div>';
    }
    if (!empty($s['databases'])) {
        $out .= '<div class="pa-cell span"><b>Search space &mdash; '
              . count($s['databases']) . ' databases, as the publication lists them</b><span>'
              . cnido_pub_e(implode('; ', $s['databases'])) . '</span></div>';
    }
    return $out . '</div>';
}

/**
 * The whole card.
 *
 * $pxd  the dataset accession, for the heading
 * $pub  one dataset's entry from cnido_published_for()
 * $nRe  how many proteins the CnidoSite re-analysis reported (0 in every case
 *       this card is used for, but stated rather than assumed)
 */
function cnido_published_card($pxd, $pub, $nRe = 0) {
    $p     = $pub['publication'];
    $n     = count($pub['rows']);
    $empty = ($n === 0);

    $out = '<div class="pa-pubcard">' . "\n";
    $out .= '  <div class="pa-pub-h">Published result &mdash; '
          . cnido_pub_e($pub['sample']) . '</div>' . "\n";

    /* Lead: what the reader came here to find out, in one sentence, before any
       of the provenance. */
    $out .= '  <p class="pa-pub-lead">';
    if ($empty) {
        $out .= 'The publication behind this deposit did not report a protein for this '
              . 'particular sample either &mdash; so the empty table above is not a gap that '
              . 'a different search would have filled.';
    } else {
        $out .= 'The publication behind this deposit did report identifications for this '
              . 'same sample: <b>' . cnido_pub_num($n) . ($n === 1 ? ' protein' : ' proteins')
              . '</b>, listed below as the authors published ' . ($n === 1 ? 'it' : 'them')
              . '. CnidoSite\'s re-analysis of the deposited raw file reported '
              . ((int)$nRe === 0 ? 'none' : cnido_pub_num((int)$nRe)) . '.';
    }
    $out .= "</p>\n";

    /* The attribution.  This is the one thing that must not be missable: these
       rows are the authors', searched in the authors' own search space, and the
       numbers are not comparable with the re-analysis figures.  The empty case
       needs the opposite sentence -- there is nothing of the authors' to
       attribute, and the paragraph below would contradict the lead. */
    $out .= '  <div class="pa-pub-att">' . "\n";
    $out .= '    <div class="who">';
    if ($empty) {
        $out .= 'This card states what the publication does and does not cover for this '
              . 'sample. It is not a result of the CnidoSite re-analysis, and it adds no '
              . 'identification to the panel above.';
    } else {
        $out .= 'These are the authors\' own identifications, not a CnidoSite re-analysis. '
              . 'The search engine, the search space and the significance threshold are '
              . 'theirs, so the counts below are not comparable row for row with the '
              . 'figures in the panel above.';
    }
    $out .= '</div>' . "\n";
    $out .= '    <div class="cite">' . cnido_pub_e($p['citation']) . '</div>' . "\n";
    $out .= '    <div class="links">';
    if (!empty($p['pubmed'])) {
        $out .= '<a href="https://pubmed.ncbi.nlm.nih.gov/' . cnido_pub_e($p['pubmed'])
              . '/" target="_blank" rel="noopener noreferrer">PMID ' . cnido_pub_e($p['pubmed']) . '</a>';
    }
    if (!empty($p['pmcid'])) {
        $out .= '<a href="https://pmc.ncbi.nlm.nih.gov/articles/' . cnido_pub_e($p['pmcid'])
              . '/" target="_blank" rel="noopener noreferrer">' . cnido_pub_e($p['pmcid']) . '</a>';
    }
    if (!empty($p['doi'])) {
        $out .= '<a href="https://doi.org/' . cnido_pub_e($p['doi'])
              . '" target="_blank" rel="noopener noreferrer">doi:' . cnido_pub_e($p['doi']) . '</a>';
    }
    $out .= "</div>\n  </div>\n";

    /* Which fraction of which sample, and how it was matched to this dataset --
       without that the reader has to take the pairing on faith.  The label says
       "the figures" only when there are figures. */
    $out .= '  <div class="pa-pub-meta"><b>'
          . ($empty ? 'Sample this statement covers' : 'Sample the figures belong to')
          . '</b> '
          . '<span class="s">' . cnido_pub_e($pub['sample']) . '</span> '
          . '&mdash; ' . cnido_pub_e($pub['fraction'])
          . ', ' . cnido_pub_e($pub['digest']) . ' digest. '
          . 'Transcribed from ' . $pub['source'] . '.</div>' . "\n";

    if ($empty) {
        if (!empty($pub['empty_note'])) {
            $out .= '  <div class="pa-pub-note">' . $pub['empty_note'] . "</div>\n";
        }
    } else {
        $out .= cnido_pub_table($pub);
        /* The footnote names only the columns this particular table has: the two
           publications do not print the same ones, and explaining an emPAI column
           to a reader looking at the fossil table would be an error. */
        $has = array();
        foreach ($pub['columns'] as $c) { $has[$c['k']] = true; }
        $notes = array();
        if (isset($has['matches'], $has['sig_matches'], $has['peptides'])) {
            $notes[] = '<i>matches</i> and <i>significant matches</i> count peptide matches and '
                     . '<i>peptides</i> the distinct sequences behind them';
        }
        if (isset($has['empai'])) {
            $notes[] = 'emPAI is the abundance index Mascot derives from them';
        }
        if (isset($has['eval'])) {
            $notes[] = 'the e-value is the one the publication prints beside its Blast2GO '
                     . 'description, not a peptide-match score';
        }
        $foot = 'Column meanings are the publication\'s own';
        if ($notes) { $foot .= ': ' . implode('; ', $notes); }
        $foot .= '. Shading scales the Mascot score against the highest score in this table ('
               . cnido_pub_num((int)$pub['max_score']) . '); it is not a significance cut-off.';
        $out .= '  <p class="pa-pub-foot">' . $foot . "</p>\n";
    }

    /* The authors' second, non-coral search over the same spectra.  Shown
       separately because it answers a different question -- "is any of this a
       contaminant?" -- and because its result is a negative one worth printing. */
    if (!empty($pub['other_fraction'])) {
        $of = $pub['other_fraction'];
        $out .= '  <div class="pa-pub-other">' . "\n";
        $out .= '    <div class="h">The same spectra, searched against other taxa only</div>' . "\n";
        $out .= '    <p>' . $of['text'] . "</p>\n";
        $out .= cnido_pub_table(array('columns' => $of['columns'], 'rows' => $of['rows'],
                                      'max_score' => max(array_map(function ($r) {
                                          return isset($r['score']) ? (int)$r['score'] : 0;
                                      }, $of['rows']))));
        foreach ($of['rows'] as $r) {
            if (isset($r['coral']) && $r['coral'] !== '') {
                $out .= '    <p class="verdict">The authors\' own verdict on this row: '
                      . '<b>peptides match coral peptides: ' . cnido_pub_e($r['coral'])
                      . '</b> &mdash; the hit is not coral protein, which is why the '
                      . 'coral-database search above reports nothing for these spectra.'
                      . "</p>\n";
            }
        }
        $out .= "  </div>\n";
    }

    /* The parameters the published search ran under. */
    if (!empty($pub['searched'])) {
        $out .= '  <div class="pa-sub">How the publication searched them'
              . ' <span class="hint">&mdash; the authors\' parameters, for comparison with '
              . 'the CnidoSite parameters further down this page.</span></div>' . "\n";
        $out .= cnido_pub_searched($pub['searched']);
    }

    $out .= "</div>\n";
    return $out;
}
