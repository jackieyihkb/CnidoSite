<?php
/* =====================================================================
 * 物种 × 数据类型 可用性矩阵（coverage matrix）
 *
 * 审稿意见 Referee 2 major 2：
 *   "Please provide a species-by-data-type coverage matrix so that users can
 *    see at a glance which species have which data."
 * 审稿意见 Referee 2 major 1：
 *   "When a species is selected in the Taxonomy module, users should be able to
 *    directly access all available genome, bulk transcriptome, single-cell,
 *    proteome, epigenome, metagenome, phenotype and palaeobiology resources."
 *
 * 全站数据分散在 1400+ 张表里，逐个物种查会非常慢，所以这里一次性扫描
 * information_schema + 各元数据表，把结果缓存成 JSON（默认 1 小时过期），
 * species_portal.php 与 coverage_matrix.php 共用。
 * ===================================================================== */

/* 缓存结构变化时递增，避免读到旧版本的 JSON。
   v5：转录组由「有/没有」改记真实样本数（sample 表按物种计数），
       矩阵里不再是一个笼统的对勾。
   v6：基因集同样改记真实数量（<ABBR>_locus 行数 = 蛋白编码基因模型数）。
   v7：线粒体基因组改为 mitochondrion ∪ mito_genome 两张表判定 —— 只有基因组级
       记录、没有特征行的物种（6 个）此前 Mitogenome 格是空的。
   v8：v7 补记的存在性 1 与「真的只有 1 个线粒体基因」在缓存里长得一模一样，
       矩阵无法区分、会把前者也显示成「1 genes」。现在另用 flag 记账，
       渲染层对存在性格子显示对勾。
   v9：新增 transcriptome assembly 一类数据（trans_assembly_species 有行即有，
       矩阵里是对勾）。转录组由一列变成 Transcriptome 分组下的三列
       （Data / Assembly / Network），矩阵因此从 15 类变 16 类。 */
define('CNIDO_COVERAGE_VERSION', 9);

function cnido_coverage_cache_file()
{
    /* 放在 web 进程写得进去的目录里 —— includes/ 属于部署用户，Apache 写不了，
       原来这行导致缓存永远建不起来，每次请求都重扫整个 schema（约 6.7 秒）。 */
    require_once __DIR__ . '/cache.php';
    return cnido_cache_path('coverage_cache.json');
}

/** 数据模块定义：key => 显示名 / 说明 / 详情页 */
function cnido_modules()
{
    return array(
        'genome'       => array('label' => 'Genome assembly', 'short' => 'Genome',        'page' => 'genomeinfo.php',        'desc' => 'Assembled genome (size, level, N50, BUSCO)'),
        'annotation'   => array('label' => 'Gene annotation', 'short' => 'Transcripts',        'page' => 'search.php',            'desc' => 'Predicted transcripts (mRNA records) with CDS and protein sequences. Counted per transcript, so a gene with several isoforms contributes more than one; this is not the protein-coding gene count shown on the species page.'),
        'function'     => array('label' => 'Functional annotation', 'short' => 'Function',  'page' => 'domain_search.php',     'desc' => 'InterPro, Pfam, PANTHER, GO and KEGG assignments'),
        'genefamily'   => array('label' => 'Gene family (orthogroup)', 'short' => 'Gene family','page' => 'genefamily.php',       'desc' => 'OrthoFinder orthogroups and TF / ubiquitin families'),
        /* 转录组那三列在矩阵里同属一个「Transcriptome」分组（见 coverage_matrix_view.php）：
           group 是分组名，gshort 是分组内那一窄列的表头 —— 组名横跨三列，
           下面各列分别叫 Data / Assembly / Network。short 仍然是「数据类」
           自己的名字，用在筛选胶囊、图例和物种页卡片上，那里没有分组上下文，
           只写 Data / Network 读者不知道指什么。 */
        'transcriptome'=> array('label' => 'Bulk transcriptome', 'short' => 'Transcriptome', 'group' => 'Transcriptome', 'gshort' => 'Data', 'page' => '/cytoscape/trans_data.php', 'desc' => 'RNA-seq samples with expression values (TPM)'),
        /* 转录组组装与上面的「转录组数据」是两样东西：前者是拼出来的转录本集
           （每个预测蛋白都带功能注释），后者是表达量样本。判定见
           cnido_build_coverage() 的 2d 段 —— 用 trans_assembly_species 而不是
           <ABBR>_TPM 或 sample，所以两列的数并不重合：326 个物种里 157 个有组装、
           185 个有样本，两者都有的只有 132 个。 */
        'trans_assembly' => array('label' => 'Transcriptome assembly', 'short' => 'Transcriptome assembly', 'group' => 'Transcriptome', 'gshort' => 'Assembly', 'page' => 'trans_assembly_species.php', 'desc' => 'Assembled transcriptome (de novo or genome-guided) with functional annotation of every predicted protein'),
        'coexpression' => array('label' => 'Co-expression network', 'short' => 'Co-expression', 'group' => 'Transcriptome', 'gshort' => 'Network', 'page' => '/cytoscape/network.php', 'desc' => 'Positive / negative co-expression edges'),
        'singlecell'   => array('label' => 'Single-cell', 'short' => 'Single-cell',            'page' => 'sn_data.php',           'desc' => 'scRNA-seq datasets, cell atlas and marker genes'),
        'proteome'     => array('label' => 'Proteome', 'short' => 'Proteome',               'page' => 'proteomic_data.php',    'desc' => 'Mass-spectrometry proteomics samples'),
        'epigenome'    => array('label' => 'Epigenome', 'short' => 'Epigenome',              'page' => 'epigenomic_data.php',   'desc' => 'DNA methylation, miRNA-seq, ATAC-seq, ChIP-seq'),
        'metagenome'   => array('label' => 'Metagenome', 'short' => 'Metagenome',             'page' => 'metagenomic_data.php',  'desc' => 'Host-associated metagenomic samples'),
        /* phenotype 的 desc 原先写的是「Trait measurements with geo-referencing」，
           而实测重建前这张表 98.3% 的行 value 列装的是源库 OCTD 的编号、不是读数 ——
           说成 measurements 会让物种页上的卡片把「944」读成「944 条测量」。
           2026-09-27 从三家源库（OCTD v2.2 / WoRMS / Pelagic v3.1）重建后，编号行
           只剩 75 条，desc 因此可以如实说这是 trait records，但仍要说清两件事：
           一条记录只有一个值（不是范围、不是重复测量），且有一部分值是「继承自
           上级分类单元」或「源库自己算的」，不能当成这个物种的实测。
           计数单位保持 rows：$cov 里的数就是**折叠后**的行数（内容相同的源记录
           已并成一行，源记录总数写在 phenotype.php 的页脚）。
           逐物种的可用条数不在卡片上现算（那要给每个物种扫一次表），点进
           phenotype_species.php 有完整分解。
           注意：desc 会被 cnido_spcards_html() 走一遍 htmlspecialchars，所以这里
           只能写纯文本，**不能写 &mdash; 之类的实体**（会被原样印成 "&mdash;"）。
           要破折号就用字符本身。 */
        'phenotype'    => array('label' => 'Phenotype', 'short' => 'Phenotype', 'count_word' => 'rows', 'page' => 'phenotype.php?class=all', 'desc' => 'Trait records from three curated databases — one value per record, about half of them inherited from a higher taxon rather than measured on the species'),
        'paleobiology'=> array('label' => 'Paleobiology', 'short' => 'Paleobiology',      'page' => 'paleobiology.php',      'desc' => 'Fossil occurrences and stratigraphic records'),
        'mitogenome'   => array('label' => 'Mitogenome', 'short' => 'Mitogenome', 'unit' => 'genes', 'page' => 'mitdata.php', 'desc' => 'Mitochondrial genome record and gene annotations'),
        'TE'           => array('label' => 'Transposable elements', 'short' => 'TE',  'page' => 'TE.php',                'desc' => 'Repeat annotation of assembled genomes'),
        'jbrowse'      => array('label' => 'Genome browser', 'short' => 'JBrowse',         'page' => 'jbrowse.php',           'desc' => 'Interactive JBrowse track for the assembly'),
    );
}

/** 重建缓存。$conn 必须是已连接的 mysqli。 */
function cnido_build_coverage($conn)
{
    $cov  = array();  // abbr1 => array(module => count/int)
    /* 值是 1、但含义其实是「有这个模块」而非「有 1 条记录」的格子：
       abbr1 => array(module => 1)。$cov 里那个 1 照旧保留（api.php、
       物种门户、「共有几类数据」的计数都按「非空即有」判断，不动它们），
       flag 只用来告诉渲染层「别把这个 1 当计数印出来」。 */
    $flag = array();

    /* 各来源给出的物种标识大小写不一（abbr1 有时是 AACUM 这样的短码，有时
     * 直接是 Acropora_cf_manni 这样的全名），统一按大写归一化后再落库，否则
     * 同一个物种会在 $cov 里出现两个键、矩阵里永远显示「无数据」。 */
    $canon = array();
    $add = function ($abbr, $mod, $n = 1) use (&$cov, &$canon) {
        $key = strtoupper(trim($abbr));
        if ($key === '') { return; }
        if (isset($canon[$key])) { $key = $canon[$key]; }
        if (!isset($cov[$key])) { $cov[$key] = array(); }
        if (!isset($cov[$key][$mod])) { $cov[$key][$mod] = 0; }
        $cov[$key][$mod] += $n;
    };
    /* 与 $add 相对：把值「置为」$n 而不是累加。用于「存在性标记先占了个 1、
       随后又查到真实计数」的场合，否则 1 会混进计数里。 */
    $put = function ($abbr, $mod, $n) use (&$cov, &$canon) {
        $key = strtoupper(trim($abbr));
        if ($key === '') { return; }
        if (isset($canon[$key])) { $key = $canon[$key]; }
        if (!isset($cov[$key])) { $cov[$key] = array(); }
        $cov[$key][$mod] = (int)$n;
    };

    /* ---- 1. 基因组 / 注释：从 abbr 表拿全部物种，再看表是否存在 ---- */
    $abbrRows = array();
    if ($q = mysqli_query($conn, "SELECT species, abbr, abbr1 FROM abbr")) {
        while ($r = mysqli_fetch_row($q)) {
            $abbrRows[$r[2]] = array('species' => $r[0], 'abbr' => $r[1], 'class' => '');
        }
    }
    // Class（Cubozoa / Hexacorallia / ...）只存在于 speciesinfo，键同样是 abbr1
    if ($q = mysqli_query($conn, "SELECT abbr, `Class`, Latin_name FROM speciesinfo")) {
        while ($r = mysqli_fetch_row($q)) {
            $code = trim($r[0]);
            if ($code === '') { continue; }
            if (!isset($abbrRows[$code])) {
                $abbrRows[$code] = array('species' => $r[2], 'abbr' => str_replace(' ', '_', $r[2]), 'class' => $r[1]);
            } else {
                $abbrRows[$code]['class'] = $r[1];
                if ($abbrRows[$code]['species'] === '') { $abbrRows[$code]['species'] = $r[2]; }
            }
        }
    }

    foreach (array_keys($abbrRows) as $__k) { $canon[strtoupper(trim($__k))] = $__k; }

    /* 后缀 => array(大写的表前缀 => 表的真实名字)。
       除了「有没有」，有些模块还要知道「有多少」，那就得拿真实表名去 COUNT ——
       MySQL 在 Linux 上表名区分大小写，用大写形式拼反而查不到表。 */
    $tblBySuffix = array();
    if ($q = mysqli_query($conn, "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()")) {
        while ($r = mysqli_fetch_row($q)) {
            if (preg_match('/^(.+)_([A-Za-z][A-Za-z0-9]*)$/', $r[0], $m)) {
                $tblBySuffix[$m[2]][strtoupper($m[1])] = $r[0];
            }
        }
    }
    $map = array(
        'locus'      => 'annotation',
        'ipr'        => 'function',
        'TPM'        => 'transcriptome',
        'TE'         => 'TE',
        'ATAC'       => 'ATAC',
        'ChIP'       => 'ChIP',
        'cellmarker' => 'singlecell',
        'proteomics' => 'proteome',
    );
    foreach ($map as $suffix => $mod) {
        foreach ((array)(isset($tblBySuffix[$suffix]) ? $tblBySuffix[$suffix] : array()) as $ab => $tbl) {
            /* 基因集是唯一一类能便宜地数清的：<ABBR>_locus 每张表一次 COUNT(*)，
               最大的（109k 行）也在 10 毫秒以内，145 张表总共不到 2 秒 —— 相对
               一小时才建一次的缓存可以忽略。矩阵里就能给出「这个物种有多少个
               蛋白编码基因模型」，而不是一个对勾。
               其余的（_ipr 39 万行、_TE 250 万行）整表 COUNT 要几十秒，不划算，
               仍然只记「有/没有」。 */
            if ($suffix === 'locus') {
                $n = 0;
                if ($q2 = mysqli_query($conn, "SELECT COUNT(*) FROM `" . str_replace('`', '', $tbl) . "`")) {
                    if ($r2 = mysqli_fetch_row($q2)) { $n = (int)$r2[0]; }
                }
                if ($n > 0) { $put($ab, $mod, $n); continue; }
            }
            $add($ab, $mod);
        }
    }
    /* 共表达网络表名是 <ABBR>_coexpress_positive / _negative —— 按最后一个下划线
     * 切分得到的前缀是 AAURI1_COEXPRESS，所以要把 _COEXPRESS 再剥掉。
     *
     * 表在 ≠ 有网络：AAURI1/AAURI2 的 _coexpress_positive/_negative 四张表是建了
     * 但一行都没有的，只测「表在不在」会把它俩算成有网络，页面于是印「Networks
     * exist for 31 species」，真数是 29（62 张表 ÷ 2 = 31，减掉这两个）。用
     * LIMIT 1 探一行就够判定空表 —— 这里不能用 COUNT(*)：PCLAV 那张表 650 万行，
     * 整个索引要扫一遍，62 张合计几千万行；LIMIT 1 是 O(1)。 */
    foreach (array('positive', 'negative') as $suf) {
        foreach ((array)(isset($tblBySuffix[$suf]) ? $tblBySuffix[$suf] : array()) as $pre => $tbl) {
            $ab = preg_replace('/_COEXPRESS$/', '', strtoupper($pre));
            if ($ab === '' || $ab === strtoupper($pre)) { continue; }
            $q2 = mysqli_query($conn, "SELECT 1 FROM `" . str_replace('`', '', $tbl) . "` LIMIT 1");
            if ($q2 && mysqli_fetch_row($q2)) { $add($ab, 'coexpression'); }
        }
    }
    // 有 locus 表 => 也有 seq/注释；jbrowse 目录
    $jbd = array();
    foreach ((array)@scandir(__DIR__ . '/../jbrowse') as $d) {
        if ($d !== '.' && $d !== '..' && is_dir(__DIR__ . '/../jbrowse/' . $d)) { $jbd[strtoupper($d)] = true; }
    }
    foreach (array_keys($jbd) as $ab) { $add($ab, 'jbrowse'); }

    /* ---- 2. 按物种名出现次数的元数据表 ---- */
    $bySpecies = array(
        'singlecell' => array('table' => 'singlecell', 'col' => 'Species', 'page_mod' => 'singlecell'),
        'epigenome'  => array('table' => 'epigenome',  'col' => 'Species', 'page_mod' => 'epigenome'),
        'metagenome' => array('table' => 'metaG',      'col' => 'Species', 'page_mod' => 'metagenome'),
        'phenotype'  => array('table' => 'phenotype',  'col' => 'species', 'page_mod' => 'phenotype'),
        'paleobiology' => array('table' => 'paleobiology', 'col' => 'species', 'page_mod' => 'paleobiology'),
        'mitogenome' => array('table' => 'mitochondrion', 'col' => 'species', 'page_mod' => 'mitogenome'),
    );
    $nameToAbbr = array();
    foreach ($abbrRows as $ab => $info) {
        $nameToAbbr[strtolower(trim($info['species']))] = $ab;
        $nameToAbbr[strtolower(trim(str_replace('_', ' ', $info['abbr'])))] = $ab;
    }
    /* ---- 2b. 转录组：sample 表按物种给出真实样本数 ----
       第 1 步只从 <ABBR>_TPM 表的存在性记了「有/没有」（值 1）。矩阵要求
       「该物种有的话就显示数据量多少」，而样本数在 sample 表里现成：
       Latin_name 分组的 COUNT(*) 就是该物种的 RNA-seq 样本数。这里用覆盖
       而不是累加，免得 presence 的 1 混进真实计数里。 */
    if ($q = mysqli_query($conn, "SELECT Latin_name, COUNT(*) FROM sample GROUP BY Latin_name")) {
        while ($r = mysqli_fetch_row($q)) {
            $key = strtolower(trim($r[0]));
            if ($key === '' || (int)$r[1] <= 0) { continue; }
            $ab = isset($nameToAbbr[$key]) ? $nameToAbbr[$key] : null;
            if ($ab === null) {
                foreach ($nameToAbbr as $nm => $a) {
                    if ($nm !== '' && (strpos($key, $nm) === 0 || strpos($nm, $key) === 0)) { $ab = $a; break; }
                }
            }
            if ($ab !== null) { $put($ab, 'transcriptome', (int)$r[1]); }
        }
    }

    foreach ($bySpecies as $mod => $cfg) {
        $q = mysqli_query($conn, "SELECT `{$cfg['col']}`, COUNT(*) FROM `{$cfg['table']}` GROUP BY `{$cfg['col']}`");
        while ($q && ($r = mysqli_fetch_row($q))) {
            $key = strtolower(trim($r[0]));
            if ($key === '') { continue; }
            $ab = isset($nameToAbbr[$key]) ? $nameToAbbr[$key] : null;
            if ($ab === null) {
                // 有些表用属名+种加词但写法不同，退化为前缀匹配
                foreach ($nameToAbbr as $nm => $a) {
                    if ($nm !== '' && (strpos($key, $nm) === 0 || strpos($nm, $key) === 0)) { $ab = $a; break; }
                }
            }
            if ($ab !== null) { $add($ab, $mod, (int)$r[1]); }
        }
    }

    /* ---- 2c. 线粒体基因组：mitochondrion 只是「基因级特征表」，不是全部 ----
       mito_genome 是基因组级统计表，两表口径不同、来源也不同（前者来自注释，
       后者来自 GenBank 记录）。有 6 个物种只有基因组级记录、一条特征行都没有：
       Anthopleura elegantissima、Dendrogyra cylindrus、Hydractinia echinata、
       Nanomia septata、Pachycerianthus multiplicatus、Siderastrea siderea。
       只按 mitochondrion 判定，这 6 个物种的 Mitogenome 格会显示成「没有」，
       但 mitdata.php 的物种白名单用的正是两张表的 UNION —— 同一条数据在模块页
       有、在矩阵里没有。这里补记一次存在性（值 1，与该表在别处「有即记 1」的
       用法一致）；已经算到真实特征数的物种绝不复写，避免 1 混进计数里。
       按 abbr1 精确匹配，这里的键与矩阵同源，不需要走学名模糊匹配。

       这个 1 必须同时记进 $flag：它和「真的只有 1 个线粒体基因」的物种
       （Morbakka virulenta、Chironex yamaguchii、Tamoya ohboya，mitochondrion
       各 1 行）在 $cov 里完全一样，只有 flag 能分开 —— 否则矩阵会给这 6 个
       没有任何基因注释的物种印上「1 genes」。 */
    if ($q = mysqli_query($conn, "SELECT abbr1 FROM mito_genome WHERE TRIM(COALESCE(abbr1,'')) <> ''")) {
        while ($r = mysqli_fetch_row($q)) {
            $key = strtoupper(trim($r[0]));
            if ($key === '') { continue; }
            if (isset($canon[$key])) { $key = $canon[$key]; }
            if (!isset($abbrRows[$key])) { continue; }
            if (!isset($cov[$key]['mitogenome'])) {
                $put($key, 'mitogenome', 1);
                $flag[$key]['mitogenome'] = 1;
            }
        }
    }

    /* ---- 2d. 转录组组装：trans_assembly_species 一行一个物种 ----
       表里有的就是「这个物种有一个组装好的转录组」，矩阵里显示对勾，不显示计数 ——
       所以记存在性（值 1），并且**不进 $flag**：$flag 是给「值本身另有含义、不能
       当计数印」的格子用的（见上面的 Mitogenome），而这一列的整列本来就是存在性
       列，cnido_module_count_is_meaningful() 不认为它可数，渲染层会自己印对勾。
       判定用 trans_assembly_species 而不是 <ABBR>_TPM 或 sample：
         · 它是这个模块自己的物种表（trans_assembly_species.php 的物种下拉就是它，
           收的也是 abbr1），一行一个物种，不必去数 1200 万行的 trans_assembly；
         · 它与「转录组样本数」不是一回事 —— 有组装没样本、有样本没组装都常见，
           两列并排正是为了让人看出这个差别。
       这里逐行对 $abbrRows 校验：表里 220 个物种中有 63 个（Acropora cf. manni、
       Aurelia sp. 3 之类）不在本站 326 个物种的名录里，它们不进矩阵。 */
    if ($q = mysqli_query($conn, "SELECT abbr1 FROM trans_assembly_species")) {
        while ($r = mysqli_fetch_row($q)) {
            $key = strtoupper(trim($r[0]));
            if ($key === '') { continue; }
            if (isset($canon[$key])) { $key = $canon[$key]; }
            if (!isset($abbrRows[$key])) { continue; }
            $add($key, 'trans_assembly');
        }
    }

    /* ---- 3. 蛋白质组：表名形如 <Something>_proteomics ---- */
    $protByAbbr = array();
    foreach (array_keys(isset($tblBySuffix['proteomics']) ? $tblBySuffix['proteomics'] : array()) as $prefix) {
        $protByAbbr[$prefix] = true;
    }
    foreach (array_keys($protByAbbr) as $prefix) {
        if (isset($abbrRows[$prefix])) { $add($prefix, 'proteome'); continue; }
        $low = strtolower($prefix);
        foreach ($nameToAbbr as $nm => $a) {
            if ($nm !== '' && strpos($low, str_replace(' ', '_', $nm)) === 0) { $add($a, 'proteome'); break; }
        }
    }
    if ($q = mysqli_query($conn, "SELECT species, COUNT(*) FROM proteome_data GROUP BY species")) {
        while ($r = mysqli_fetch_row($q)) {
            $key = strtolower(trim($r[0]));
            if (isset($nameToAbbr[$key])) { $add($nameToAbbr[$key], 'proteome', (int)$r[1]); }
        }
    }

    /* ---- 4. TF / 泛素家族（跨物种大表，带 species 列） ---- */
    foreach (array('tf' => 'genefamily', 'ubs' => 'genefamily') as $t => $mod) {
        if ($q = mysqli_query($conn, "SELECT DISTINCT species FROM `$t`")) {
            while ($r = mysqli_fetch_row($q)) {
                $key = strtolower(trim($r[0]));
                if (isset($nameToAbbr[$key])) { $add($nameToAbbr[$key], $mod); }
            }
        }
    }
    if ($q = mysqli_query($conn, "SELECT DISTINCT abbr FROM genefamily")) {
        while ($r = mysqli_fetch_row($q)) { $add($r[0], 'genefamily'); }
    }

    /* ---- 5. 基因组基本信息（speciesinfo）按拉丁名对齐 ---- */
    if ($q = mysqli_query($conn, "SELECT Latin_name, abbr FROM speciesinfo")) {
        while ($r = mysqli_fetch_row($q)) {
            $key = strtolower(trim($r[0]));
            if (isset($nameToAbbr[$key])) { $add($nameToAbbr[$key], 'genome'); }
        }
    }

    return array(
        'v'       => CNIDO_COVERAGE_VERSION,
        'built'   => time(),
        'species' => $abbrRows,
        'cov'     => $cov,
        'flag'    => $flag,
    );
}

/** 读取缓存（过期或缺失时重建）。 */
function cnido_coverage($conn, $force = false)
{
    $f = cnido_coverage_cache_file();
    if ($f !== '' && !$force && is_file($f)) {
        $j = json_decode((string)@file_get_contents($f), true);
        if (is_array($j) && isset($j['built'], $j['v'])
            && $j['v'] === CNIDO_COVERAGE_VERSION
            && (time() - $j['built']) < 3600) { return $j; }
    }
    $j = cnido_build_coverage($conn);
    /* 只在新结果非空时写缓存。连接坏掉时每个 mysqli_query 都会返回 false，
       各表的 while 循环一次都不进，扫出来就是「一个物种都没有」的空矩阵 ——
       那正是调用方会原样拿去渲染（并显示「没有任何物种」）的东西。把它写进
       缓存等于让接下来一小时的全部请求都读到空表，所以宁可不写：下次请求
       重新扫一遍，连接恢复后自然就正常了。 */
    if ($f !== '' && !empty($j['species'])) {
        /* 先写临时文件再 rename：并发请求不会读到写了一半的 JSON。
           临时文件必须与目标同目录，rename 才是原子的。 */
        $tmp = $f . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode($j), LOCK_EX) !== false) {
            if (!@rename($tmp, $f)) { @unlink($tmp); }
        }
    }
    return $j;
}

/** 某物种有多少个模块有数据（用于「数据最全的物种」排序）。 */
function cnido_coverage_score($data, $abbr)
{
    $n = 0;
    if (isset($data['cov'][$abbr])) {
        foreach ($data['cov'][$abbr] as $mod => $cnt) { if ($cnt > 0) { $n++; } }
    }
    return $n;
}
