<?php
/* sample 表的 dev_stage / Treatment 两列的「修复 + 归类」，供 trans_data.php 之类的
 * 页面做「简易总结」式展示。数据层不动，只在显示层做。
 *
 * ── 一、℃ 污染 ────────────────────────────────────────────────────────────
 * Treatment 列有 1,971 行、396 个取值、115 个词形被一个「吞字」过程破坏：一段
 * 0~3 个字符、且以 c/C 结尾的片段被替换成了 U+2103 ℃。宽度不固定，所以没有可
 * 逆的规则 —— 只能逐个词形对照还原（下表）。注意 25℃/30℃/32℃ 这些是**真实的
 * 摄氏度**，必须原样保留，所以下表只收「含 ℃ 但不是温度」的受损词形，
 * 且以整词（含上下文）为键、长的先替换，避免 P℃ 抢在 P℃ebo-treated 前面。
 * 判据是同一批数据的兄弟取值（例：25uM U0126 与 U℃0379 同属 Hydra；
 * ℃climation 与 Ambient/High 同属 Astrangia；+℃FS 与 Antibiotics 同属
 * Nematostella 的菌群实验），不是猜词。
 * 仍未还原的只剩 ℃tld（Orbicella，1 个取值）——保留原样，归到 Other。
 *
 * ── 二、why 归类 ─────────────────────────────────────────────────────────
 * dev_stage 291 个取值、Treatment 1,551 个取值，绝大多数是自由文本。归成十来
 * 个桶只为了让用户一眼看出这批样本「是什么阶段、做了什么处理」；原始文本永远
 * 作为 detail 显示，所以没有信息丢失，也没有猜测。
 */

/** ℃ 受损词形 → 正确写法。键长的先替换。 */
function cnido_meta_repair_map() {
    static $MAP = array(
        /* ── 长键（含上下文，必须排在短键前） ── */
        'days℃lade℃'                 => 'days Clade C',
        'days℃lade'                  => 'days Clade',
        '℃MP2556-in℃ulation'         => 'CCMP2556-inoculation',
        'm℃roadriat℃um'              => 'microadriaticum',
        'subtent℃ular℃ut'            => 'subtentacular cut',
        'Germ-free℃onditions'        => 'Germ-free conditions',
        '12:12℃℃le'                  => '12:12 Cycle',
        'with℃urvib℃ter'             => 'with Curvibacter',
        '2\'3\'℃GAMP'                => '2\'3\'-cGAMP',
        '3\'3\'℃GAMP'                => '3\'3\'-cGAMP',
        '3\'3\'℃UA'                  => '3\'3\'-cUA',
        '4h_U℃hallenged'             => '4h_Unchallenged',
        'Antibiot℃s℃onstant'         => 'Antibiotics-Constant',
        '℃ol3mOrange2'               => 'Col3mOrange2',
        'P℃ebo-treated'              => 'Placebo-treated',
        'Settlement℃ue'              => 'Settlement cue',
        'Homeostat℃'                 => 'Homeostatic',
        'Aplan℃hytrium'              => 'Aplanochytrium',
        '℃climation'                 => 'Acclimation',
        'high℃o2'                    => 'high CO2',
        'cry℃halasin'                => 'cytochalasin',   // 防拼写变体
        '℃tld'                       => '℃tld',           // 未还原，原样保留
        /* ── 词干类 ── */
        'isoton℃'                    => 'isotonic',
        's℃rose'                    => 'sucrose',
        'repl℃ate'                   => 'replicate',
        'dupl℃ate'                   => 'duplicate',
        'aposymbiot℃'                => 'aposymbiotic',
        'apo-symbiot℃'               => 'apo-symbiotic',
        'symbiot℃'                   => 'symbiotic',
        '℃ropora'                    => 'Acropora',
        'Apo℃lone'                   => 'ApoClone',
        'Sym℃lone'                   => 'SymClone',
        'Antibiot℃s'                 => 'Antibiotics',
        'antibiot℃'                  => 'antibiotic',
        'sel℃tion'                   => 'selection',
        'sel℃ted'                    => 'selected',
        '℃RT14'                      => 'CRT14',
        'B℃illus'                    => 'Bacillus',
        'Mg℃a'                       => 'Mg/Ca',
        'alginolyt℃us'               => 'alginolyticus',
        'pl℃ed'                      => 'placed',
        '℃O2'                        => 'CO2',
        'in℃ulation'                 => 'inoculation',
        'inj℃tion'                   => 'injection',
        'Inj℃ted'                    => 'Injected',
        '℃m '                        => 'Cm ',
        '℃SIO'                       => 'CSIO',
        '℃CA)'                       => '(CCA)',
        '℃CA'                        => '(CCA',
        '℃ute'                       => 'Acute',
        'herb℃ides'                  => 'herbicides',
        'ppm℃O2'                     => 'ppmCO2',
        '℃FS'                        => 'CFS',
        'F℃S'                        => 'FCS',
        'Nv℃F'                       => 'NvTCF',
        'Z℃4'                        => 'Zic4',
        'U℃0379'                     => 'UC0379',
        'AT℃'                        => 'ATC',
        'HT℃'                        => 'HTC',
        '_℃_'                        => '_C_',
        'm℃k'                        => 'mock',
        'to℃h'                       => 'touch',
        'P℃'                         => 'PC',
        'ap℃al'                      => 'apical',
        'artif℃ial'                  => 'artificial',
        'ble℃hed'                    => 'bleached',
        'unble℃hed'                  => 'unbleached',
        'ca℃ium-free'                => 'calcium-free',
        'ca℃ium'                     => 'calcium',
        'chron℃'                     => 'chronic',
        'cyt℃halasin'                => 'cytochalasin',
        'gl℃ose'                     => 'glucose',
        'ind℃er'                     => 'inducer',
        'int℃t'                      => 'intact',
        'kn℃kdown'                   => 'knockdown',
        'mono-ass℃iated'             => 'mono-associated',
        'param℃oides'                => 'paramycoides',
        'pedu℃le'                    => 'peduncle',
        'sh℃k'                       => 'shock',
        'streptom℃in'                => 'streptomycin',
        'Co℃h℃ine'                   => 'Colchicine',
    );
    return $MAP;
}

/** 把 ℃ 受损词形还原成正确写法；温度类 ℃ 原样保留。 */
function cnido_meta_repair($s) {
    if ($s === null || $s === '' || strpos($s, '℃') === false) return $s;
    static $KEYS = null;
    if ($KEYS === null) {
        $KEYS = array_keys(cnido_meta_repair_map());
        usort($KEYS, function ($a, $b) { return strlen($b) - strlen($a); });
    }
    foreach ($KEYS as $k) {
        if (strpos($s, $k) !== false) { $s = str_replace($k, cnido_meta_repair_map()[$k], $s); }
    }
    return $s;
}

/** 桶 → 配色（浅底 + 深字 + 边）。 */
function cnido_meta_color($slug) {
    static $C = array(
        'gamete'   => array('#f5f3ff', '#6d28d9', '#ddd6fe'),
        'embryo'   => array('#fff7ed', '#c2410c', '#fed7aa'),
        'larva'    => array('#ecfdf5', '#047857', '#a7f3d0'),
        'polyp'    => array('#f0f9ff', '#0369a1', '#bae6fd'),
        'medusa'   => array('#fdf4ff', '#a21caf', '#f5d0fe'),
        'adult'    => array('#eef2ff', '#4338ca', '#c7d2fe'),
        'regen'    => array('#fff1f2', '#be123c', '#fecdd3'),
        'timed'    => array('#f8fafc', '#475569', '#e2e8f0'),
        'control'  => array('#f8fafc', '#475569', '#e2e8f0'),
        'thermal'  => array('#fff1f2', '#be123c', '#fecdd3'),
        'light'    => array('#fffbeb', '#b45309', '#fde68a'),
        'ph'       => array('#f0fdfa', '#0f766e', '#99f6e4'),
        'symbio'   => array('#ecfdf5', '#047857', '#a7f3d0'),
        'microbe'  => array('#f7fee7', '#4d7c0f', '#d9f99d'),
        'molecular'=> array('#f5f3ff', '#6d28d9', '#ddd6fe'),
        'chemical' => array('#f0f9ff', '#0369a1', '#bae6fd'),
        'handling' => array('#fff7ed', '#c2410c', '#fed7aa'),
        /* Other 用暖灰（stone），与 Control / Time-course 的冷灰（slate）分开：
           两者在图例里会同时出现，同一个灰会让人以为是同一桶。 */
        'other'    => array('#fafaf9', '#78716c', '#e7e5e4'),
        'na'       => array('#f8fafc', '#94a3b8', '#e2e8f0'),
    );
    return isset($C[$slug]) ? $C[$slug] : $C['other'];
}

/** 发育阶段：原始文本 → array(slug, label, detail)。 */
function cnido_meta_dev($raw) {
    $s = trim(cnido_meta_repair($raw));
    $t = strtolower($s);
    if ($t === '' || $t === '-' || $t === '--' || $t === 'na' || $t === 'n/a'
        || $t === 'not specified' || $t === 'unknown') {
        return array('na', 'Not specified', '');
    }
    /* 顺序即优先级：先「是什么」（配子→胚胎→幼虫→水母→成体→水螅体→再生），
       再「是什么时间点」。newly regenerated polyp 归 polyp，0hpa 归 regen。 */
    static $RULES = array(
        'gamete' => array('egg', 'unfertilized', 'oocyte', 'sperm', 'gamete', 'spermatogon',
                          'spermatocyte', 'spermatid'),
        'embryo' => array('embryo', 'cleavage', 'morula', 'blastula', 'gastrula', 'gastrulae',
                          'post-gastrula', 'sphere', 'donut'),
        'larva'  => array('planula', 'planulae', 'larva', 'larvae', 'ephyra', 'nauplius'),
        'medusa' => array('medusa', 'jellyfish'),
        'adult'  => array('adult', 'mature'),
        'polyp'  => array('polyp', 'coenosarc', 'colony', 'gastrozooid', 'gonozooid', 'stolon',
                          'hydranth', 'tentacle bud', 'strobila', 'juvenile', 'bud'),
        'regen'  => array('hpa', 'regenerat', 'amputat', 'wound', 'injur'),
    );
    static $LABEL = array('gamete' => 'Gamete', 'embryo' => 'Embryo', 'larva' => 'Larva',
                          'medusa' => 'Medusa', 'adult' => 'Adult', 'polyp' => 'Polyp',
                          'regen' => 'Regeneration', 'timed' => 'Time-course',
                          'other' => 'Other', 'na' => 'Not specified');
    $low = strtolower($s);
    foreach ($RULES as $slug => $words) {
        foreach ($words as $w) {
            if (strpos($low, $w) !== false && ($w !== 'bud' || preg_match('/\bbud\b/', $low))) {
                return array($slug, $LABEL[$slug], cnido_meta_brief($s));
            }
        }
    }
    /* 时间点判据不能用 \b：10DPI / day0 这种「数字直接贴字母」的写法取不到词边界。
       故改成「两侧必须不是字母」。 */
    if (preg_match('/(^|[^a-z])(hpf|dpf|mpf|dpi|hpa|days?|weeks?|wks?|months?|hours?|hrs?|mins?|minutes)([^a-z]|$)/i', $low)
        || preg_match('/day\s*\d/i', $low)
        || preg_match('/\d+(\.\d+)?\s*h\b/i', $low)) {
        return array('timed', $LABEL['timed'], cnido_meta_brief($s));
    }
    return array('other', $LABEL['other'], cnido_meta_brief($s));
}

/** 处理方式：原始文本 → array(slug, label, detail)。 */
function cnido_meta_treat($raw) {
    $s = trim(cnido_meta_repair($raw));
    $t = strtolower($s);
    if ($t === '' || $t === '-' || $t === '--' || $t === 'na' || $t === 'n/a'
        || $t === 'not specified' || $t === 'unknown') {
        return array('na', 'Not specified', '');
    }
    $low = strtolower($s);
    /* 单独一个温度值（25℃ / 30 / 32℃ treatment）才算温度处理；
       字符串里顺带出现温度的不算，否则 "Placebo-treated T2 25℃" 会被归成温度。 */
    if (preg_match('/^\s*\d+(\.\d+)?\s*℃?\s*(treatment|stress|psu)?\s*$/u', $s)
        || preg_match('/\b\d{2}\s*c\b/i', $s)          /* "28C, 3week" 这种写法 */
        || preg_match('/^\s*(high|low)\s*$/i', $s)) {   /* Astrangia 的 Ambient/High */
        return array('thermal', 'Temperature', cnido_meta_brief($s));
    }
    /* 剂量单位：5 uM azakenpaullone / 10mM Hydroxyurea —— 出现剂量基本就是加药 */
    if (preg_match('/\d+(\.\d+)?\s*(mm|um|µm|nm|ng\/ml|ug\/ml|µg\/ml|mg\/l)\b/u', $low)) {
        return array('chemical', 'Chemical / drug', cnido_meta_brief($s));
    }
    /* 顺序即优先级。symbio 排在 thermal 前：'stress aposymbiotic RNA pool day 21' 是
       共生实验不是温度实验；反过来 'high temperature' 里没有共生词，照样落在 thermal。 */
    static $RULES = array(
        'ph'        => array('ph', 'pco2', 'co2', 'acidif', 'alkalinity', 'psu', 'salinity',
                             'per 1000', 'hypox', 'anox', 'oxygen', 'ppm'),
        'symbio'    => array('symbiot', 'aposymbiot', 'apo-', 'bleach', 'clade', 'inocul',
                             'germ-free', 'axenic', 'symbiont', 'dinoflagell', 'algae', 'algal',
                             'mixed'),
        'thermal'   => array('heat', 'hot', 'cold', 'warm', 'thermal', 'temperature', 'temp',
                             'stress'),
        'light'     => array('dark', 'light', 'photoperiod', 'irradiance', 'uv', 'day:night',
                             'blue', 'white light', 'dim', 'l/d', '12:12'),
        'microbe'   => array('vibrio', 'bacillus', 'pseudoalteromonas', 'lps', 'disease', 'diseased',
                             'infect', 'challeng', 'pathogen', 'microb', 'bacteri', 'antibiot',
                             'immune', 'primed', 'naive', 'curvibacter', 'metabacillus', 'staphyloc',
                             'escherichia', 'alteromonas', 'strain', 'sp.', 'affected', 'lesion',
                             'healthy tissue', 'unaffected'),
        'molecular' => array('rnai', 'sirna', 'morpholino', 'overexpress', 'knock', 'mutant',
                             'transgen', 'gfp', 'morange', 'crispr', 'mrna', 'injection of',
                             'dominant negative', 'col3', 'shrna', 'wild-type'),
        'chemical'  => array('dmso', 'dapt', 'colchicine', 'cytochalasin', 'streptomycin', 'herbicide',
                             'glucose', 'sucrose', 'isotonic', 'calcium', 'mg/ca', 'cacl2', 'nacl',
                             'lithium', 'retinoic', 'inhibitor', 'u0126', 'uc0379', 'crt14', 'cgamp',
                             'cua', 'glw-amide', 'inducer', 'drug', 'copper', 'metal', 'nutrient',
                             'nitrate', 'phosphate', 'iron', 'vitamin', 'serum', 'fcs', 'cfs',
                             'supernatant', 'oil', 'dispersant', 'pesticide', 'antibody',
                             'hydroxyurea', 'hydroxurea'),
        'handling'  => array('settlement', 'metamorphos', 'amputat', 'injur', 'wound', 'regenerat',
                             'fed', 'feeding', 'starve', 'starvation', 'food', 'sediment', 'ammonium',
                             'touch', 'prawn', 'dissect', 'transplant', 'outplant', 'crustose coralline',
                             'cca', 'originated', 'placed', 'upperside', 'underside', 'shallow', 'deep'),
    );
    foreach ($RULES as $slug => $words) {
        foreach ($words as $w) {
            if (preg_match('/' . preg_quote($w, '/') . '/', $low)) {
                return array($slug, cnido_meta_treat_label($slug), cnido_meta_brief($s));
            }
        }
    }
    /* dd / ld / L-D 这类两字母光周期代号要单独特判：下划线是词字符，\b 在这里不管用 */
    if (preg_match('/(^|[^a-z0-9])(dd|ld|d:d|l:d)([^a-z0-9]|$)/', $low)) {
        return array('light', 'Light regime', cnido_meta_brief($s));
    }
    static $CTRL = array('control', 'untreated', 'ambient', 'baseline', 'naive', 'mock',
                         'no treatment', 'no antibiot', 'placebo', 'sea water', 'seawater',
                         'unexposed', 'initial', 'no light', 'kept in', 'wildtype');
    if (preg_match('/^\s*pre\s*$/i', $s)) { return array('control', 'Control', cnido_meta_brief($s)); }
    foreach ($CTRL as $w) {
        if (preg_match('/' . preg_quote($w, '/') . '/', $low)) {
            return array('control', 'Control', cnido_meta_brief($s));
        }
    }
    return array('other', 'Other', cnido_meta_brief($s));
}

/** 处理方式桶的中文名（英文站点用英文标签）。 */
function cnido_meta_treat_label($slug) {
    static $L = array('control' => 'Control', 'thermal' => 'Temperature', 'light' => 'Light regime',
                      'ph' => 'Water chemistry', 'symbio' => 'Symbiosis', 'microbe' => 'Microbial / immune',
                      'molecular' => 'Molecular / genetic', 'chemical' => 'Chemical / drug',
                      'handling' => 'Settlement / handling', 'other' => 'Other',
                      'na' => 'Not specified');
    return isset($L[$slug]) ? $L[$slug] : 'Other';
}

/** detail 太长就截断（完整原文进 title），避免把表格撑破。 */
function cnido_meta_brief($s, $max = 68) {
    $s = preg_replace('/\s+/u', ' ', trim($s));
    if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $max) {
        return mb_substr($s, 0, $max - 1, 'UTF-8') . '…';
    }
    if (!function_exists('mb_strlen') && strlen($s) > $max) { return substr($s, 0, $max - 1) . '…'; }
    return $s;
}

/**
 * 把 array(原始文本 => 计数) 归并成桶。
 * @return array 桶数组，按计数降序：array('slug','label','count','pct')
 */
function cnido_meta_bucketize($pairs, $fn) {
    $out = array();
    $tot = 0;
    foreach ($pairs as $raw => $n) {
        $n = (int)$n;
        $tot += $n;
        $b = call_user_func($fn, $raw);
        $slug = $b[0];
        if (!isset($out[$slug])) { $out[$slug] = array('slug' => $slug, 'label' => $b[1], 'count' => 0); }
        $out[$slug]['count'] += $n;
    }
    foreach ($out as &$r) { $r['pct'] = $tot ? round($r['count'] * 100 / $tot, 1) : 0; }
    unset($r);
    uasort($out, function ($a, $b) { return $b['count'] - $a['count']; });
    return array('total' => $tot, 'buckets' => array_values($out));
}

/**
 * 取某物种的 dev_stage / Treatment 汇总（全物种，不只当前页）。
 * 表很小（15,642 行）且 Latin_name 无索引，但一次 GROUP BY 只有十几毫秒。
 */
function cnido_meta_summary($conn, $species) {
    $sp = mysqli_real_escape_string($conn, $species);
    $out = array('dev' => null, 'treat' => null);
    $q = mysqli_query($conn, "SELECT dev_stage, COUNT(*) FROM sample
                              WHERE Latin_name = '$sp' GROUP BY dev_stage");
    if ($q) {
        $pairs = array();
        while ($r = mysqli_fetch_row($q)) { $pairs[$r[0] === null ? '' : $r[0]] = $r[1]; }
        $out['dev'] = cnido_meta_bucketize($pairs, 'cnido_meta_dev');
    }
    $q = mysqli_query($conn, "SELECT Treatment, COUNT(*) FROM sample
                              WHERE Latin_name = '$sp' GROUP BY Treatment");
    if ($q) {
        $pairs = array();
        while ($r = mysqli_fetch_row($q)) { $pairs[$r[0] === null ? '' : $r[0]] = $r[1]; }
        $out['treat'] = cnido_meta_bucketize($pairs, 'cnido_meta_treat');
    }
    return $out;
}

/**
 * 渲染一个「分布卡」：标题 + 堆叠条 + 图例。$which = 'dev' | 'treat'
 *
 * 「没有值」的那一桶**不进堆叠条**，只在标题右边标出条数。原因：源数据里
 * dev_stage 有 13,398/15,642 行、Treatment 有 9,265/15,642 行就是 '-'（库里
 * 真没有，且没有更全的来源可补：上游 sample TSV 只 1,762 行、对得上键的里面
 * 也只能再补 135/172 条）。若把它画进条里，很多物种的条会是一整条灰的，
 * 既看不出这批样本做了什么，也谈不上好看。所以条只画**有值的那部分**并按
 * 其自身归一化，缺失数在标题旁以 "N of M 无值" 的形式明说，不隐藏。
 */
function cnido_meta_panel_html($sum, $which, $title, $subtitle) {
    if (empty($sum[$which]) || $sum[$which]['total'] === 0) {
        return '<div class="mx-card"><h3>' . htmlspecialchars($title) . '</h3>'
             . '<p class="mx-empty">No samples for this species.</p></div>';
    }
    $tot = $sum[$which]['total'];
    $bks = array();
    $na  = 0;
    foreach ($sum[$which]['buckets'] as $b) {
        if ($b['count'] <= 0) continue;
        if ($b['slug'] === 'na') { $na += $b['count']; continue; }
        $bks[] = $b;
    }
    $known = $tot - $na;                       /* 有值的样本数 = 归一化基数 */

    $h  = '<div class="mx-card"><div class="mx-card-hd"><h3>' . htmlspecialchars($title) . '</h3>'
        . '<span class="mx-card-note">' . htmlspecialchars($subtitle) . '</span></div>';

    if ($known <= 0) {
        return $h . '<p class="mx-empty">All ' . number_format($tot)
             . ' samples have no value for this field in the sample table.</p></div>';
    }

    $h .= '<div class="mx-bar">';
    foreach ($bks as $b) {
        $c   = cnido_meta_color($b['slug']);
        $pct = round($b['count'] * 100 / $known, 1);
        $h .= '<span class="mx-seg" style="flex:' . $b['count'] . ';background:' . $c[1] . '"'
            . ' title="' . htmlspecialchars($b['label'] . ': ' . $b['count'] . ' of '
                                 . $known . ' with a value (' . number_format($pct, 1) . '%)')
            . '"></span>';
    }
    $h .= '</div><ul class="mx-legend">';
    foreach ($bks as $b) {
        $c   = cnido_meta_color($b['slug']);
        $pct = round($b['count'] * 100 / $known, 1);
        $h .= '<li><i style="background:' . $c[1] . '"></i><span class="mx-lg-name">'
            . htmlspecialchars($b['label']) . '</span><span class="mx-lg-num">'
            /* round() 返回 float，3.0 直接串接会印成「3%」，而同一张图例里其它行都是
               一位小数（24.1% / 4.7%），同一个量两种精度。统一到一位小数。 */
            . number_format($b['count']) . '</span><span class="mx-lg-pct">' . number_format($pct, 1) . '%</span></li>';
    }
    $h .= '</ul>';
    $h .= '<p class="mx-miss">' . number_format($known) . ' of ' . number_format($tot)
        . ' samples have a value' . ($na > 0
            ? '; ' . number_format($na) . ($na === 1 ? ' is' : ' are')
              . ' empty in the sample table.' : '.') . '</p>';
    $h .= '</div>';
    return $h;
}

/** 渲染一个单元格徽章。$b = cnido_meta_dev()/cnido_meta_treat() 的返回。 */
function cnido_meta_pill_html($b, $rawFull) {
    $slug = $b[0];
    $c = cnido_meta_color($slug);
    if ($slug === 'na') {
        return '<span class="mx-na" title="no value in the sample table">—</span>';
    }
    $title = $rawFull === '' ? $b[1] : $rawFull;
    $h = '<span class="mx-pill" style="background:' . $c[0] . ';color:' . $c[1]
       . ';border-color:' . $c[2] . '" title="' . htmlspecialchars($title) . '">'
       . htmlspecialchars($b[1]) . '</span>';
    if ($b[2] !== '' && $b[2] !== $b[1]) {
        $h .= '<span class="mx-detail" title="' . htmlspecialchars($title) . '">'
            . htmlspecialchars($b[2]) . '</span>';
    }
    return $h;
}
