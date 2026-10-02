<?php
/**
 * includes/stats.php —— 全站统计数字的唯一来源。
 *
 * 背景（审稿意见 Referee 2 major 12）：
 *   审稿人发现首页与 Data Statistics 页给出的同一个量不一致（148 vs 145），
 *   而且站上若干数字（NR / UniProt / 表观修饰类型数 / 家族数等）跟数据库里
 *   实际的表对不上。根因是每个页面各自硬编码一份数字，写完就再没人核对。
 *
 * 解决办法：所有对外公布的统计量都在本文件里从数据库现算一遍，写入 JSON 缓存
 *   （默认 24 小时有效，与 includes/coverage.php 的做法一致），首页
 *   index.php 与 data_statistics.php 都只读这一份结果。这样两页在物理上
 *   不可能再给出不同的值；缓存过期后数字也会自己跟着数据库更新。
 *
 * 用法：
 *   require_once __DIR__ . '/includes/stats.php';
 *   $S = cnido_stats($conn);            // 取全部统计量（键 => 整数）
 *   echo cnido_num($S['tax_species']);  // 12,345 风格格式化
 *
 * 新增统计量时：在 cnido_stats_compute() 里算出来，并在 cnido_stat_defs() 里
 * 写清口径（定义会以 tooltip 形式显示在页面上，也用于回复审稿人时对齐措辞）。
 */

/* ------------------------------------------------------------------ */
/* 定义表：每个统计量的口径说明                                        */
/* ------------------------------------------------------------------ */

function cnido_stat_defs()
{
    return array(
        'tax_class'                   => 'Distinct classes in the CnidoSite taxonomy table (classfy).',
        'tax_order'                   => 'Distinct orders in the taxonomy table.',
        'tax_family'                  => 'Distinct families in the taxonomy table.',
        'tax_species'                 => 'Distinct species in the taxonomy table; equals the number of species in the catalogue.',
        'genome_catalogue'            => 'Species in the genome catalogue (rows of speciesinfo).',
        'genome_with_accession'       => 'Species whose speciesinfo record carries an INSDC genome assembly accession.',
        'genome_gene_models'          => 'Species for which CnidoSite ships a gene-model table (<ABBR>_locus, protein-coding gene coordinates).',
        'genome_functional_annotation'=> 'Species for which CnidoSite ships functional annotation tables (GO/InterPro/Pfam/PANTHER/KEGG). Every species with gene models also has functional annotation.',
        'jbrowse_species'             => 'Species with a browsable JBrowse instance (a per-species directory carrying trackList.json).',
        'jbrowse_tracks'              => 'Tracks declared in those per-species JBrowse trackList.json files.',
        'genes_protein_coding'        => 'Predicted transcripts (mRNA records) in the per-species gene-locus tables (<ABBR>_locus). Counted per transcript, so a gene with several isoforms contributes more than one; this is not the protein-coding gene count printed on the species pages.',
        'busco'                       => 'Rows in the BUSCO table: one row per BUSCO gene per species.',
        'transcriptome'               => 'Bulk transcriptomic datasets (rows of sample).',
        'singlecell'                  => 'Single-cell datasets (rows of singlecell).',
        'proteome'                    => 'Proteomic datasets (rows of proteome_data).',
        'proteins_quantified'         => 'Quantified proteins across the per-species proteomics result tables.',
        'epigenome'                   => 'Epigenomic datasets (rows of epigenome).',
        'epigenome_types'             => 'Distinct assay types in the epigenome table (Bisulfite-Seq, ATAC-seq, ChIP-Seq, miRNA-Seq, DNase-Seq).',
        'epigenome_peaks'             => 'Peaks called across the per-sample ATAC-seq, ChIP-seq and DNase-seq tables (one row = one peak). Bisulfite-Seq tables are NOT counted here: they hold per-cytosine methylation values, not peaks.',
        'epigenome_cpg'               => 'Cytosine/CpG sites with a methylation value across the per-sample Bisulfite-Seq tables (one row = one site), i.e. the depth of the WGBS data.',
        'metagenome'                  => 'Metagenomic datasets (rows of metaG).',
        'mags'                        => 'Metagenome-assembled genomes (rows of MAGs).',
        'phenotype'                   => 'Phenotype records (rows of phenotype) integrated from three curated trait databases: OCTD v2.2, WoRMS / Marine Species Traits and the Pelagic Species Trait Database v3.1. Identical source entries are folded into one row, so this is the number of distinct records, not of source rows.',
        'paleo'                       => 'Fossil records (rows of paleobiology).',
        'mitochondrial'               => 'Species with a mitochondrial genome record (mitochondrion gene features or mito_genome genome-level statistics). Counted as shown on the Mitogenomic Data page.',
        'coexpress_networks'          => 'Co-expression networks, each with a positive and a negative correlation table.',
        'coexpress_pairs'             => 'Gene pairs recorded across those co-expression tables (positive and negative combined).',
        'cell_types'                  => 'Distinct curated cell-type labels across the per-species cell marker tables.',
        'cell_markers'                => 'Curated cell marker records across the per-species cell marker tables.',
        'annotation_types'            => 'Per-genome functional annotation sources shipped for each annotated genome (GO, InterPro, Pfam, PANTHER, KEGG, NR, UniProt, transcription factors, ubiquitin families).',
        'go'                          => 'GO term assignments across all per-species <ABBR>_go tables.',
        'interpro'                    => 'InterPro assignments across all per-species <ABBR>_ipr tables.',
        'pfam'                        => 'Pfam domain assignments across all per-species <ABBR>_pfam tables.',
        'panther'                     => 'PANTHER assignments across all per-species <ABBR>_panther tables.',
        'kegg'                        => 'KEGG pathway assignments across all per-species <ABBR>_KEGG tables.',
        'nr'                          => 'NR database hits across all per-species <ABBR>_nr tables.',
        'uniprot'                     => 'UniProt hits across all per-species <ABBR>_uniprot tables.',
        'tf'                          => 'Transcription factor assignments.',
        'ubiquitin'                   => 'Ubiquitin-family assignments.',
        'genefamily'                  => 'Orthologous gene family assignments.',
        'genefamily_num'              => 'Orthologous gene families (distinct families, genefamily_num table).',
    );
}

/** 人类可读标签，供页面与回复文档共用。 */
function cnido_stat_labels()
{
    return array(
        'tax_class' => 'Classes', 'tax_order' => 'Orders', 'tax_family' => 'Families',
        'tax_species' => 'Species', 'genome_catalogue' => 'Species in genome catalogue',
        'genome_with_accession' => 'Species with an assembly accession',
        'genome_gene_models' => 'Genomes with gene models',
        'genome_functional_annotation' => 'Genomes with functional annotation',
        'jbrowse_species' => 'Species in JBrowse', 'jbrowse_tracks' => 'Tracks in JBrowse',
        'genes_protein_coding' => 'Protein-coding transcripts', 'busco' => 'BUSCO genes',
        'transcriptome' => 'Transcriptomic datasets', 'singlecell' => 'Single-cell datasets',
        'proteome' => 'Proteomic datasets', 'proteins_quantified' => 'Quantified proteins',
        'epigenome' => 'Epigenomic datasets', 'epigenome_types' => 'Epigenetic modification types',
        'epigenome_peaks' => 'Epigenetic modification peaks',
        'epigenome_cpg' => 'Methylated cytosine sites', 'metagenome' => 'Metagenomic datasets',
        'mags' => 'Metagenome-assembled genomes', 'phenotype' => 'Phenotype records',
        /* 注意上面的 'phenotype' 计数是 COUNT(*)（折叠后的行数），不是源记录数
           （= SUM(n_records)）。源记录数只写在 phenotype.php 的页脚，不在这里 ——
           同一个数字印在两处、口径不同，读者对不上就会去怀疑是数据错了。 */
        'paleo' => 'Fossil records', 'mitochondrial' => 'Species with a mitochondrial genome',
        'coexpress_networks' => 'Co-expression networks', 'coexpress_pairs' => 'Co-expressed gene pairs',
        'cell_types' => 'Cell types', 'cell_markers' => 'Cell markers',
        'annotation_types' => 'Types of gene annotation',
        'go' => 'GO annotations', 'interpro' => 'InterPro annotations', 'pfam' => 'Pfam domains',
        'panther' => 'PANTHER annotations', 'kegg' => 'KEGG pathway annotations',
        'nr' => 'NR database hits', 'uniprot' => 'UniProt hits',
        'tf' => 'Transcription factors', 'ubiquitin' => 'Ubiquitin family',
        'genefamily' => 'Gene family assignments', 'genefamily_num' => 'Orthologous families',
    );
}

/* ------------------------------------------------------------------ */
/* 缓存                                                                */
/* ------------------------------------------------------------------ */

function cnido_stats_cache_file()
{
    /* 权威副本仍然是 includes/stats_cache.json —— 它由 CLI 以部署用户身份生成，
       网页只读不写（一次全量统计约 79 秒，不该让访问者等）。所以只要那份还在，
       就用它。问题是它一旦被删，网页既读不到、重建完也写不回去（includes/ 对
       Apache 只读），此后每一次访问都要重算 79 秒。这种情况下改用可写目录，
       让页面能自己重建一次、把坑填上。 */
    $legacy = __DIR__ . '/stats_cache.json';
    if (is_readable($legacy)) { return $legacy; }
    require_once __DIR__ . '/cache.php';
    $alt = cnido_cache_path('stats_cache.json');
    return $alt !== '' ? $alt : $legacy;
}

/** 缓存有效期（秒）。默认一天，与 coverage.php 一致。 */
function cnido_stats_ttl()
{
    return 86400;
}

/* ------------------------------------------------------------------ */
/* 取数入口                                                            */
/* ------------------------------------------------------------------ */

/**
 * 返回全部统计量。
 *
 * 重要：网页请求里**不做**长计算。一次完整现算约 79 秒（epigenome_cpg 一项
 * 就要扫 7.87 亿个 CpG 位点），放在请求里会长时间占住一个 Apache worker，还可能撞上
 * php.ini 的 max_execution_time 直接 500。所以：
 *
 *   1. 缓存存在且在 24 小时内 —— 直接用；
 *   2. 缓存存在但已过期 —— 仍然先用旧值（数字略旧好过页面报错），
 *      同时在 data_statistics.php 上标注计算时间；
 *   3. 缓存压根不存在（刚部署、被删）—— 才在请求里现算一次。
 *
 * 日常刷新交给 CLI（可挂 cron，每天一次）：
 *   php /var/www/html/CnidoSite/includes/stats_refresh.php
 *
 * @param  mysqli $conn
 * @param  bool   $force 忽略缓存强制重算（只应在 CLI / 运维时用）
 * @return array  键 => int
 */
function cnido_stats($conn, $force = false)
{
    $file  = cnido_stats_cache_file();
    $cache = cnido_stats_read($file);

    if (!$force && $cache !== null) {
        /* 新鲜或过期都直接返回；刷新由 CLI 负责。 */
        return $cache['stats'];
    }

    $stats = cnido_stats_compute($conn);
    if ($stats === null) {
        /* 算不出来时宁可显示上一次的结果，也不要显示 0 或空白。 */
        return $cache !== null ? $cache['stats'] : array();
    }

    cnido_stats_store($file, $stats);
    return $stats;
}

/** 读缓存文件，返回 array('ts'=>int,'generated'=>string,'stats'=>array) 或 null。 */
function cnido_stats_read($file)
{
    if (!is_readable($file)) { return null; }
    $raw = @file_get_contents($file);
    if ($raw === false) { return null; }
    $c = json_decode($raw, true);
    if (!is_array($c) || !isset($c['stats']) || !is_array($c['stats'])) { return null; }
    return array(
        'ts'        => isset($c['ts']) ? (int)$c['ts'] : 0,
        'generated' => isset($c['generated']) ? $c['generated'] : '',
        'stats'     => $c['stats'],
    );
}

/** 缓存是否已超过 TTL（页面上可以据此提示「数字正在刷新」）。 */
function cnido_stats_is_stale()
{
    $c = cnido_stats_read(cnido_stats_cache_file());
    if ($c === null) { return true; }
    return (time() - $c['ts']) >= cnido_stats_ttl();
}

/** 原子写缓存：先写临时文件再 rename，避免并发请求读到写了一半的 JSON。 */
function cnido_stats_store($file, $stats)
{
    $payload = json_encode(array(
        'ts'      => time(),
        'date'    => date('Y-m-d H:i:s'),
        'generated' => date('Y-m-d H:i:s'),
        'stats'   => $stats,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) { return false; }

    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $payload, LOCK_EX) === false) { return false; }
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    return true;
}

/** 缓存的生成时间，供 release/运维页面显示。 */
function cnido_stats_generated()
{
    $file = cnido_stats_cache_file();
    if (!is_readable($file)) { return ''; }
    $c = json_decode((string)@file_get_contents($file), true);
    return (is_array($c) && isset($c['generated'])) ? $c['generated'] : '';
}

/* ------------------------------------------------------------------ */
/* 计算                                                                */
/* ------------------------------------------------------------------ */

/** 执行一条返回单个数字的查询；失败返回 null。 */
function cnido_stats_one($conn, $sql)
{
    $q = @mysqli_query($conn, $sql);
    if (!$q) { return null; }
    $r = mysqli_fetch_row($q);
    if (!$r || $r[0] === null) { return null; }
    return (int)$r[0];
}

/** 库中所有以 $suffix 结尾的表名。$suffix 传空串则返回全部表。 */
function cnido_stats_tables($conn, $suffix)
{
    $out = array();
    $q = @mysqli_query($conn, "SHOW TABLES");
    if (!$q) { return $out; }
    $n = strlen($suffix);
    while ($r = mysqli_fetch_row($q)) {
        $t = $r[0];
        if ($n === 0) { $out[] = $t; continue; }
        if (strlen($t) > $n && substr($t, -$n) === $suffix) { $out[] = $t; }
    }
    return $out;
}

/** 表名后缀 -> 物种缩写前缀集合。`mirna_seq` 这类非物种表会被调用方剔除。 */
function cnido_stats_prefixes($conn, $suffix)
{
    $out = array();
    foreach (cnido_stats_tables($conn, $suffix) as $t) {
        $out[substr($t, 0, -strlen($suffix))] = true;
    }
    return $out;
}

/**
 * 把一组表的 COUNT(*) 加总。用 UNION ALL 放在一条查询里，MySQL 会并行走表，
 * 比逐表 SELECT 快得多（9 类注释表合计约 19 秒，逐表要几分钟）。
 */
function cnido_stats_sum($conn, $tables)
{
    if (!$tables) { return 0; }
    $parts = array();
    foreach ($tables as $t) {
        $parts[] = "SELECT COUNT(*) n FROM `" . str_replace('`', '``', $t) . "`";
    }
    $n = cnido_stats_one($conn, "SELECT SUM(n) FROM (" . implode(' UNION ALL ', $parts) . ") z");
    return $n === null ? 0 : $n;
}

/**
 * 基因组口径的两个物种集合，以「库里到底有没有那张表」为准。
 *
 * 这是 genomeinfo.php 与首页/Data Statistics 共用的唯一口径：
 *   gene_models = 有 <ABBR>_locus 表的物种（蛋白编码基因坐标）
 *   annotation  = 有 _go / _ipr / _pfam / _panther / _KEGG 任一表的物种
 * 且 gene_models ⊂ annotation（145 ⊂ 148）。
 *
 * 不要改用 speciesinfo.Protein_number / proteincoding_gene_number 那两列来判断：
 * 它们是手工维护的元数据，和库里实际存在的表在 52 个物种上对不上
 * （29 个有 _locus 表却两列都是 '-'；23 个两列有数字却一张表都没有），
 * genomeinfo.php 曾经因此把 52 行标签挂错。
 *
 * 「是不是物种」这条线由 abbr 表划定，不能只看表名后缀：库里有一张**共享**注释表
 * `trans_assembly_go`，它的表名同样以 `_go` 结尾，于是 `cnido_stats_prefixes()`
 * 会从它切出一个叫 `trans_assembly` 的假「物种前缀」。两处调用方的表现因此分叉：
 *   - genomeinfo.php 还要经 cnido_prefixes_to_latin() 映射回学名，假前缀映射不上，
 *     被静默丢掉 —— 印出来是 148（对的）；
 *   - includes/stats.php 直接 count() 前缀，于是首页与 Data Statistics 印出 149，
 *     和同一页覆盖矩阵里的 148、以及本函数自述的「145 ⊂ 148」自相矛盾。
 * 这里统一在源头按 abbr 过滤，两处调用方就只有一个口径。
 *
 * @return array 'gene_models' => (前缀 => true), 'annotation' => (前缀 => true)
 */
function cnido_annotation_sets($conn)
{
    $geneModels = cnido_stats_prefixes($conn, '_locus');
    $annotated  = cnido_stats_prefixes($conn, '_go');
    foreach (array('_ipr', '_pfam', '_panther', '_KEGG') as $sfx) {
        $annotated += cnido_stats_prefixes($conn, $sfx);
    }

    /* 只保留确实是物种的前缀（abbr.abbr1）。取不到 abbr 时原样返回，宁可数字旧
       也不要静默变成 0。 */
    $species = array();
    $q = @mysqli_query($conn, "SELECT abbr1 FROM abbr");
    if ($q) {
        while ($r = mysqli_fetch_row($q)) { $species[(string)$r[0]] = true; }
        if ($species) {
            $geneModels = array_intersect_key($geneModels, $species);
            $annotated  = array_intersect_key($annotated,  $species);
        }
    }

    return array('gene_models' => $geneModels, 'annotation' => $annotated);
}

/**
 * 把前缀（abbr1）映射成拉丁名。
 *
 * 只能走 abbr 表：speciesinfo.abbr 不能直接当表前缀用 —— 那一列一部分是
 * AALAT 这样的代码（有对应的表），一部分是 Carybdea_cf_marsupialis 这样的
 * 下划线拉丁名（没有对应的表），照着它拼表名会漏掉物种。
 *
 * @param  array $prefixes  前缀 => true
 * @return array 拉丁名 => true
 */
function cnido_prefixes_to_latin($conn, $prefixes)
{
    $out = array();
    if (!$prefixes) { return $out; }
    $q = @mysqli_query($conn, "SELECT abbr1, species FROM abbr");
    if (!$q) { return $out; }
    while ($r = mysqli_fetch_row($q)) {
        if (isset($prefixes[$r[0]])) { $out[cnido_latin_key($r[1])] = true; }
    }
    return $out;
}

/** 拉丁名的比对键：去空白 + 转小写，避免大小写/空格差异导致漏配。 */
function cnido_latin_key($s)
{
    return strtolower(trim((string)$s));
}

/**
 * 从数据库现算全部统计量。约 20–30 秒，只在缓存过期时执行。
 * 返回 null 表示连接不可用/查询失败，调用方会退回旧缓存。
 */
function cnido_stats_compute($conn)
{
    if (!($conn instanceof mysqli) || $conn->connect_error) { return null; }

    /* ---- 分类阶元 ---- */
    $tax = cnido_stats_one($conn,
        "SELECT COUNT(DISTINCT Class) FROM classfy");
    $ord = cnido_stats_one($conn,
        "SELECT COUNT(DISTINCT `order1`) FROM classfy");
    $fam = cnido_stats_one($conn,
        "SELECT COUNT(DISTINCT Family) FROM classfy");
    $spe = cnido_stats_one($conn,
        "SELECT COUNT(DISTINCT Species) FROM classfy");
    if ($tax === null || $ord === null || $fam === null || $spe === null) { return null; }

    /* ---- 基因组口径 ----
       gene models = 有 <ABBR>_locus 表的物种（蛋白编码基因坐标）
       functional annotation = 有 <ABBR>_go 等注释表的物种
       两者是包含关系：gene models ⊂ functional annotation。
       口径与 genomeinfo.php 共用 cnido_annotation_sets()，两页不会再各算一套。 */
    $__sets     = cnido_annotation_sets($conn);
    $geneModels = $__sets['gene_models'];
    $annotated  = $__sets['annotation'];

    /* ---- JBrowse：以存在 trackList.json 的物种目录为准 ---- */
    $jbSpecies = 0;
    $jbTracks  = 0;
    $jbBase    = dirname(__DIR__) . '/jbrowse';
    if (is_dir($jbBase)) {
        foreach ((array)@scandir($jbBase) as $d) {
            if ($d === '.' || $d === '..') { continue; }
            $tl = $jbBase . '/' . $d . '/trackList.json';
            if (!is_file($tl)) { continue; }
            $jbSpecies++;
            $j = json_decode((string)@file_get_contents($tl), true);
            if (is_array($j) && isset($j['tracks']) && is_array($j['tracks'])) {
                $jbTracks += count($j['tracks']);
            }
        }
    }

    /* ---- 逐表加总的注释量 ---- */
    $nr      = cnido_stats_sum($conn, cnido_stats_tables($conn, '_nr'));
    $uniprot = cnido_stats_sum($conn, cnido_stats_tables($conn, '_uniprot'));
    $go      = cnido_stats_sum($conn, cnido_stats_tables($conn, '_go'));
    $interpro= cnido_stats_sum($conn, cnido_stats_tables($conn, '_ipr'));
    $pfam    = cnido_stats_sum($conn, cnido_stats_tables($conn, '_pfam'));
    $panther = cnido_stats_sum($conn, cnido_stats_tables($conn, '_panther'));
    $kegg    = cnido_stats_sum($conn, cnido_stats_tables($conn, '_KEGG'));
    $locus   = cnido_stats_sum($conn, cnido_stats_tables($conn, '_locus'));

    /* ---- 表观修饰峰（ATAC / ChIP / DNase）与甲基化位点（Bisulfite）----
       口径两个坑，2026-09-22 修正：

       1. 一张表是不是「峰表」要看**测定类型**，不能只看后缀。海蜇的 ATAC 表叫
          Hydra_ATAC_mid_body / _multitissue / _regenerating_head（后缀是组织名），
          旧的 `substr($t, -5) === '_ATAC'` 判定把这三张表整批漏掉，
          少算 1,164,306 个峰。
       2. Bisulfite-Seq 的 <ABBR>_BS_* 表**不是峰表**：一行是一个胞嘧啶位点
          （列为 Type/seqnames/start/meth_level/annotation/...）。旧口径把它们
          当峰加进来，让这个数字虚高约 440 倍（7.87 亿 vs 真实 177 万），
          也是整个统计里唯一一项要跑 79 秒的原因。CpG 位点数单独作为
          epigenome_cpg 报告，不再冒充「peaks」。 */
    $peak = array();
    $cpg  = array();
    foreach (cnido_stats_tables($conn, '') as $t) {
        if (strpos($t, '_BS_') !== false) { $cpg[] = $t; continue; }
        if (stripos($t, 'ATAC') !== false || stripos($t, 'ChIP') !== false
            || stripos($t, 'DHS') !== false) {
            $peak[] = $t;
        }
    }
    $peaks = cnido_stats_sum($conn, $peak);
    $cpgSites = cnido_stats_sum($conn, $cpg);

    /* ---- 共表达：正/负相关表各 31 张 ---- */
    $co = array_merge(
        cnido_stats_tables($conn, '_coexpress_positive'),
        cnido_stats_tables($conn, '_coexpress_negative')
    );
    $coPairs = cnido_stats_sum($conn, $co);   // 空表贡献 0 行，所以这个数本来就是对的

    /* 网络的**个数**不能数表的张数：31 张正表里 AAURI1 / AAURI2 那两张（连同各自的
       负向表）建了却一行都没有。只测「表在不在」会把它俩算成两个网络，于是首页印
       「31 co-expression networks」、数据统计印「Co-Expressed Networks 31」，而
       覆盖矩阵（includes/coverage.php 已按「表在**且**有行」计）印 29 —— 同一件事
       两个数，一对页面就能看出来。这里按覆盖矩阵同一判据数：一个物种的任一方向有行
       才算一个网络。用 LIMIT 1 探行，不能用 COUNT(*)：PCLAV 那张表 650 万行。 */
    $coNetworks = 0;
    foreach (cnido_stats_tables($conn, '_coexpress_positive') as $t) {
        $pre = substr($t, 0, strlen($t) - strlen('_coexpress_positive'));
        foreach (array('_coexpress_positive', '_coexpress_negative') as $suf) {
            $q2 = @mysqli_query($conn, "SELECT 1 FROM `" . str_replace('`', '', $pre . $suf) . "` LIMIT 1");
            if ($q2 && mysqli_fetch_row($q2)) { $coNetworks++; break; }
        }
    }

    /* ---- 单细胞标记 ---- */
    $markers = cnido_stats_sum($conn, cnido_stats_tables($conn, '_cellmarker'));
    $cellTypes = 0;
    $mk = cnido_stats_tables($conn, '_cellmarker');
    if ($mk) {
        $parts = array();
        foreach ($mk as $t) { $parts[] = "SELECT celltype v FROM `" . str_replace('`', '``', $t) . "`"; }
        $cellTypes = cnido_stats_one($conn, "SELECT COUNT(DISTINCT v) FROM (" . implode(' UNION ', $parts) . ") z");
        $cellTypes = $cellTypes === null ? 0 : $cellTypes;
    }

    /* ---- 蛋白组 ---- */
    $proteomics = cnido_stats_sum($conn, cnido_stats_tables($conn, '_proteomics'));

    /* ---- 简单计数 ---- */
    $out = array(
        'tax_class'    => $tax,
        'tax_order'    => $ord,
        'tax_family'   => $fam,
        'tax_species'  => $spe,

        'genome_catalogue' => cnido_stats_one($conn, "SELECT COUNT(*) FROM speciesinfo"),
        'genome_with_accession' => cnido_stats_one($conn,
            "SELECT COUNT(*) FROM speciesinfo WHERE TRIM(COALESCE(`Genome_Assemble`,'')) NOT IN ('','-','NA')"),
        'genome_gene_models' => count($geneModels),
        'genome_functional_annotation' => count($annotated),

        'jbrowse_species' => $jbSpecies,
        'jbrowse_tracks'  => $jbTracks,

        'genes_protein_coding' => $locus,
        'busco'                => cnido_stats_one($conn, "SELECT COUNT(*) FROM busco"),

        'transcriptome' => cnido_stats_one($conn, "SELECT COUNT(*) FROM sample"),
        'singlecell'    => cnido_stats_one($conn, "SELECT COUNT(*) FROM singlecell"),
        'proteome'      => cnido_stats_one($conn, "SELECT COUNT(*) FROM proteome_data"),
        'proteins_quantified' => $proteomics,

        'epigenome'       => cnido_stats_one($conn, "SELECT COUNT(*) FROM epigenome"),
        'epigenome_types' => cnido_stats_one($conn, "SELECT COUNT(DISTINCT Type) FROM epigenome"),
        'epigenome_peaks' => $peaks,
        'epigenome_cpg'   => $cpgSites,

        'metagenome' => cnido_stats_one($conn, "SELECT COUNT(*) FROM metaG"),
        'mags'       => cnido_stats_one($conn, "SELECT COUNT(*) FROM MAGs"),
        'phenotype'  => cnido_stats_one($conn, "SELECT COUNT(*) FROM phenotype"),
        'paleo'      => cnido_stats_one($conn, "SELECT COUNT(*) FROM paleobiology"),

        /* 两个表取并集：mitochondrion 是逐基因特征表，mito_genome 是基因组级统计表，
           少数物种只有其一。只数 mitochondrion 会漏掉 mitdata.php 页面上确实能选到、
           也确实有数据的那几个物种，两个页面的数字就对不上了。 */
        'mitochondrial' => cnido_stats_one($conn,
            "SELECT COUNT(*) FROM (
                SELECT species FROM mitochondrion WHERE TRIM(COALESCE(species,'')) <> ''
                UNION
                SELECT species FROM mito_genome WHERE TRIM(COALESCE(species,'')) <> ''
             ) t"),

        'coexpress_networks' => $coNetworks,
        'coexpress_pairs'    => $coPairs,

        'cell_types'   => $cellTypes,
        'cell_markers' => $markers,

        'annotation_types' => 9,

        'go'         => $go,
        'interpro'   => $interpro,
        'pfam'       => $pfam,
        'panther'    => $panther,
        'kegg'       => $kegg,
        'nr'         => $nr,
        'uniprot'    => $uniprot,

        'tf'         => cnido_stats_one($conn, "SELECT COUNT(*) FROM tf"),
        'ubiquitin'  => cnido_stats_one($conn, "SELECT COUNT(*) FROM ubs"),
        'genefamily' => cnido_stats_one($conn, "SELECT COUNT(*) FROM genefamily"),
        'genefamily_num' => cnido_stats_one($conn, "SELECT COUNT(*) FROM genefamily_num"),
    );

    /* 任何一项为 null（表不存在 / 查询失败）都当作计算失败，避免把 0 写进缓存。 */
    foreach ($out as $v) {
        if ($v === null) { return null; }
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* 显示辅助                                                            */
/* ------------------------------------------------------------------ */

/** 12,345 风格。 */
function cnido_num($n)
{
    return number_format((float)$n);
}

/** 取单个统计量，缺失时返回 0（页面不会因为某个键写错就崩掉）。 */
function cnido_stat($stats, $key)
{
    return isset($stats[$key]) ? (int)$stats[$key] : 0;
}

/**
 * 物种目录里的物种数（classfy），供「把物种数写进文案」的地方调用 ——
 * 主要是 meta description 这类搜索引擎和链接预览会读到、而页面上看不见的位置。
 *
 * 为什么不用 cnido_stats()：那份缓存缺失时会在页面请求里现算全部 40 个统计量
 * （CLI 跑一次约 79 秒），meta 标签不值得承担这个延迟。这里只发一条 COUNT，
 * 成本可忽略，而且值不会像写死的数字那样过期 —— 站内几处 meta description
 * 曾写死「324 Cnidaria」，目录长到 326 之后就一直错着。
 *
 * 口径与 cnido_stat_defs() 里的 tax_species 一致（COUNT(DISTINCT Species)），
 * 所以页面文案不会与 Data Statistics 页公布的物种数互相矛盾。
 */
function cnido_catalogue_species($conn)
{
    if (!$conn) { return 0; }
    $q = mysqli_query($conn, "SELECT COUNT(DISTINCT Species) FROM classfy");
    if (!$q) { return 0; }
    $r = mysqli_fetch_row($q);
    return $r ? (int)$r[0] : 0;
}
