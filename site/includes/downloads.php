<?php
/* =====================================================================
 * 下载文件清单 —— download.php 页面与 api.php?resource=downloads 共用
 *
 * 这段逻辑原本只写在 api.php 里（cnido_dl_tokens / cnido_dl_species /
 * cnido_dl_kind）。download.php 要补上「下载目录里有文件、页面上却没有链接」
 * 的那批文件（约 846 个：线粒体基因组序列、miRNA 序列等），也需要同一套
 * 「文件名 -> 物种」的解析规则。两份实现迟早会不一致 —— 页面说某个文件属于
 * 某个物种，接口说另一个 —— 所以抽到这里共用一份。
 *
 * 下载目录里有两套命名：
 *   · 拉丁名式：Acropora_acuminata.anno.gz（.fa/.cds/.transcript/.pep/.gff3/
 *     .anno/.genefamily 七种后缀，共 1027 个）
 *   · 短码式：  AACUM.fna / AACUM.gb / AACUM.gff3 / AACUM_cds.fna /
 *     AACUM_pep.faa（线粒体基因组及其派生序列，共 812 个）
 * 还有一小批谁也归不到物种的文件：4 个物种的 miRNA 序列、miRNA.csv 和
 * Pan-geneset_familydata.txt。
 *
 * 另外：下载目录的两套命名都不保证与 abbr 表完全一致（物种名被截短、多了
 * 一个句点），所以 cnido_dl_species() 先做「最长前缀 + 词边界」精确匹配，
 * 失败再退化为「唯一时才认」的宽松匹配，认不出就返回空串而不是猜。
 * ===================================================================== */

/* 下载页四组主题数据集（转录组组装 / MAGs 注释 / 多组学 / 表型）。它们和下面那六份
   共用同一份白名单，dataset_export.php 与 download.php 都从这里取。 */
require_once __DIR__ . '/downloads_topics.php';

/**
 * 由 abbr 表构造「文件名前缀 => 物种拉丁名」的查找表。
 * 同一物种在下载目录里可能用缩写（AACUM.fna）或拉丁名（Acropora_acuminata.anno.gz）。
 *
 * @return array array(查找表, 按长度倒序的键) —— 键的排序让最长前缀先匹配，
 *               否则 Acropora_acuminata 会被 Acropora 这样的短前缀抢走。
 */
if (!function_exists('cnido_dl_tokens')) {
    function cnido_dl_tokens($conn)
    {
        $tok = array();
        $q = mysqli_query($conn, "SELECT species, abbr, abbr1 FROM abbr");
        if ($q) {
            while ($r = mysqli_fetch_row($q)) {
                $u = str_replace(' ', '_', (string)$r[0]);
                $cands = array($r[0], $r[1], $r[2], $u, rtrim($u, '.'), rtrim((string)$r[0], '.'));
                foreach ($cands as $t) {
                    if ($t !== null && $t !== '' && !isset($tok[$t])) { $tok[$t] = (string)$r[0]; }
                }
            }
        }
        $keys = array_keys($tok);
        usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
        return array($tok, $keys);
    }
}

/**
 * 把下载文件名归到某个物种拉丁名，归不到就返回 ''。
 * 先按「最长前缀 + 词边界」精确匹配；不行再退化为「文件名 stem 是某个物种
 * token 的前缀，且这样的物种只有一个」——文件名里的物种名常被截短
 * （Actinernus_sp.anno.gz 对应 Actinernus sp. WN-2022），但只在唯一时才认。
 */
if (!function_exists('cnido_dl_species')) {
    function cnido_dl_species($fname, $tok, $keys)
    {
        foreach ($keys as $t) {
            if (strncmp($fname, $t, strlen($t)) !== 0) { continue; }
            $nxt = substr($fname, strlen($t), 1);
            if ($nxt === '' || $nxt === '.' || $nxt === '_' || $nxt === '-') { return $tok[$t]; }
        }
        $dot = strpos($fname, '.');
        $stem = ($dot === false) ? $fname : substr($fname, 0, $dot);
        if ($stem === '') { return ''; }
        $hits = array();
        foreach ($keys as $t) {
            if (strncmp($t, $stem, strlen($stem)) !== 0) { continue; }
            $nxt = substr($t, strlen($stem), 1);
            if ($nxt === '' || $nxt === '_' || $nxt === '.') { $hits[$tok[$t]] = true; }
        }
        return (count($hits) === 1) ? key($hits) : '';
    }
}

/** 下载文件的类型标签。 */
if (!function_exists('cnido_dl_kind')) {
    function cnido_dl_kind($fname)
    {
        $map = array(
            '_cds.fna'         => 'CDS sequences',
            '_pep.faa'         => 'Protein sequences',
            '.fna'             => 'Genome assembly',
            '.faa'             => 'Protein sequences',
            '.gff3'            => 'GFF3 annotation',
            '.gff'             => 'GFF annotation',
            '.gb'              => 'GenBank flat file',
            '.anno.gz'         => 'Functional annotation',
            '.genefamily.gz'   => 'Gene family assignment',
            '.transcript.gz'   => 'Transcript sequences',
            '.pep.gz'          => 'Protein sequences',
            '.cds.gz'          => 'CDS sequences',
            '.gff3.gz'         => 'GFF3 annotation',
            '.fa.gz'           => 'Genome assembly',
            '-mature.fas'      => 'miRNA mature sequences',
            '-pre.fas'         => 'miRNA precursor sequences',
            '-star.fas'        => 'miRNA star sequences',
            '-5p.fas'          => 'miRNA 5p arm',
            '-3p.fas'          => 'miRNA 3p arm',
            '-loop.fas'        => 'miRNA loop',
            '-tissueItems.txt' => 'miRNA tissue metadata',
            /* 两份不属于任何单一物种的汇总文件。按整名匹配（它们本身就是文件名），
               否则会落到兜底的 'Other'，在下载页上显示成一句没有信息量的 "Other"。 */
            'miRNA.csv'        => 'miRBase microRNA table (all species)',
            'Pan-geneset_familydata.txt' => 'Pan-geneset gene family data (all species)',
        );
        foreach ($map as $suf => $kind) {
            $n = strlen($suf);
            /* >= 而不是 >：上面两条「整名」条目（miRNA.csv 等）的文件名与后缀等长，
               用 > 会把它们排除掉，于是仍然落到兜底的 'Other'。 */
            if (strlen($fname) >= $n && substr($fname, -$n) === $suf) { return $kind; }
        }
        return 'Other';
    }
}

/**
 * 下载目录里的全部文件名，按自然序（数字按数值比较）排列。
 * 认不出目录时返回空数组 —— 调用方据此渲染「暂时取不到清单」，不要静默当成
 * 「一个文件都没有」。
 */
if (!function_exists('cnido_dl_files')) {
    function cnido_dl_files($dir)
    {
        $names = array();
        if (is_dir($dir) && ($dh = opendir($dir))) {
            while (($f = readdir($dh)) !== false) {
                if ($f === '.' || $f === '..' || !is_file($dir . '/' . $f)) { continue; }
                $names[] = $f;
            }
            closedir($dh);
        }
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return $names;
    }
}

/**
 * 一个下载文件的公开地址。
 *
 * 站内不直接链 /download/<文件名>，一律走 download_fun.php：它把 fname 归约成
 * 纯文件名、用 realpath 确认落在 download/ 之内，并分块流式发送（支持 Range，
 * 所以 curl -C - 能续传）。直接链目录里的文件会绕过这层检查，所以不要图省事
 * 写成相对路径。
 *
 * 2026-09-22：站点根 .htaccess 已加 Options -Indexes，download/ 的目录列表不再
 * 可取（403），但**文件本身仍可直接取**——Options -Indexes 只挡目录 URL。所以
 * 上面那条「绕过检查」的理由依旧成立：真正把访问收在 download_fun.php 里的，
 * 是这里的 URL 生成规则，不是 Apache 的配置。
 */
if (!function_exists('cnido_dl_url')) {
    function cnido_dl_url($fname)
    {
        return 'download_fun.php?download=download&fname=' . rawurlencode($fname);
    }
}

/**
 * 按类型后缀把一批文件名分组，保持给定的后缀顺序。
 *
 * @param array $names 文件名列表
 * @param array $sufs  后缀 => 分组键，例如 '.fna' => 'genome'；后缀按前缀匹配，
 *                     传入顺序即输出顺序（所以 '_cds.fna' 必须排在 '.fna' 前面）
 * @return array 分组键 => 文件名（每组取第一个匹配的文件）
 */
if (!function_exists('cnido_dl_pick')) {
    function cnido_dl_pick($names, $sufs)
    {
        $out = array();
        foreach ($sufs as $suf => $key) {
            if (isset($out[$key])) { continue; }
            $n = strlen($suf);
            foreach ($names as $f) {
                if (strlen($f) > $n && substr($f, -$n) === $suf) { $out[$key] = $f; break; }
            }
        }
        return $out;
    }
}

/**
 * 只存在于数据库里、没有文件可下载的数据集。
 *
 * download.php 要用它渲染下载页上的那一段，dataset_export.php 要用它决定
 * 「这个 key 合法吗、输出哪些列、能不能出 xlsx」。两处各写一份必然会漂移
 * ——页面列出 6 个数据集而导出脚本只认 5 个，或者反过来——所以定义在这里共用。
 *
 * 字段：
 *   title  给人看的名字
 *   about  文件脱离网页之后仍要让读者看懂的一句话说明（写进 xlsx 的说明页）
 *   table  数据库表名，仅用于说明
 *   from   SELECT 的 FROM 子句（含别名与 JOIN）
 *   cols   输出的列，可以是 SQL 表达式（如 b.BUSCO_ID）
 *   header 输出表头；null 表示与 cols 同名
 *   where  固定的过滤条件（是写死的字面量，不是用户输入）
 *   xlsx   是否允许 xlsx 导出
 *
 * 表名与列名一律写死在这里，绝不从请求里取 —— 请求只能选 key，拼不出 SQL。
 */
if (!function_exists('cnido_dl_datasets')) {
    function cnido_dl_datasets()
    {
        return array(

            'busco' => array(
                'title'  => 'BUSCO gene-level results',
                'about'  => 'One row per BUSCO hit per species. "species" is resolved from the '
                          . 'run code in the abbr column. The source file\'s header line had been '
                          . 'imported as a data row; it has since been deleted from the table, and '
                          . 'the filter below is kept only as a guard against a future re-import.',
                'table'  => 'busco',
                'from'   => 'busco b LEFT JOIN abbr a ON a.abbr1 = b.abbr',
                'cols'   => array('b.BUSCO_ID', 'b.Status', 'b.Score', 'b.Length', 'b.gene',
                                  'a.species', 'b.Description'),
                'header' => array('BUSCO_ID', 'Status', 'Score', 'Length', 'gene',
                                  'species', 'Description'),
                /* 源文件 data/merged_busco_results_with_desc_final.tsv 的第一行是表头，
                   导入时被当成数据存了进来（BUSCO_ID='BUSCO_ID'，abbr='物种缩写'）。
                   该行已于 2026-09-23 从 busco 表里删除，所以本条件现在筛不掉任何东西 ——
                   保留它是因为它同时兜住了那个对不上任何物种的孤儿代码，且万一将来又从
                   同一份 TSV 重导一次，它能继续挡着。除此之外不做任何过滤。 */
                'where'  => "b.BUSCO_ID <> 'BUSCO_ID'",
                'xlsx'   => false,
            ),

            'busco_summary' => array(
                'title'  => 'BUSCO completeness summary',
                /* n_buscos 一列必须说清，否则它读起来像"该物种有 3203 个 BUSCO"。
                   它是**有命中的**BUSCO 数（即 lineage_size − n_missing，也等于
                   n_single+n_duplicated+n_fragmented）；分母是 lineage_size。
                   2026-09-23 之前，重跑导入的那 28 行在这列里存的是分母 3203 而非
                   回收数，同一列于是有两个含义 —— 已统一为回收数。 */
                'about'  => 'One row per species: BUSCO counts and percentages for the '
                          . 'cnidaria_odb12 lineage. Percentages are exactly as stored. '
                          . 'lineage_size is the number of BUSCOs in the lineage (3,203) and is '
                          . 'the denominator of every percentage; n_buscos is the number '
                          . 'recovered, i.e. those with at least one hit, so '
                          . 'n_buscos + n_missing = lineage_size and n_buscos = n_single + '
                          . 'n_duplicated + n_fragmented.',
                'table'  => 'busco_summary',
                'from'   => 'busco_summary',
                'cols'   => array('abbr1', 'species', 'lineage', 'lineage_size', 'n_buscos',
                                  'n_single', 'n_duplicated', 'n_fragmented', 'n_missing',
                                  'pct_complete', 'pct_single', 'pct_duplicated',
                                  'pct_fragmented', 'pct_missing', 'high_quality',
                                  'ambiguous', 'protein_set'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'ubs' => array(
                'title'  => 'Ubs gene family assignments',
                'about'  => 'One row per gene assigned to a ubiquitin / ubiquitin-like (Ubs) '
                          . 'family, with family, subfamily and the source database.',
                'table'  => 'ubs',
                'from'   => 'ubs',
                'cols'   => array('species', 'abbr', 'gene', 'family', 'subfamily',
                                  'familyid', 'evalue', 'score', 'descr', 'source'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
            ),

            'epigenome' => array(
                'group'  => 'omics',
                'title'  => 'Epigenome sample metadata',
                'about'  => 'One row per sequencing run behind the Epigenome module '
                          . '(ATAC-seq, DNase-seq, ChIP-seq, DNA methylation, miRNA-seq).',
                'table'  => 'epigenome',
                'from'   => 'epigenome',
                'cols'   => array('Class', 'Species', 'Type', 'Project', 'Study', 'Experiment',
                                  'Run', 'tissue', 'dev', 'Treatment', 'Description'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'mito_genome' => array(
                'title'  => 'Mitochondrial genome metadata',
                'about'  => 'One row per species with a mitochondrial genome record. The '
                          . 'sequences themselves are in the Download module.',
                'table'  => 'mito_genome',
                'from'   => 'mito_genome',
                'cols'   => array('abbr1', 'species', 'accession', 'size_bp', 'gc_percent',
                                  'n_genes', 'n_trna', 'n_rrna', 'source', 'retrieved'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'singlecell' => array(
                'group'  => 'omics',
                'title'  => 'Single-cell dataset metadata',
                'about'  => 'One row per published single-cell dataset. The per-cell '
                          . 'expression matrices are in singlecell_data/.',
                'table'  => 'singlecell',
                'from'   => 'singlecell',
                'cols'   => array('Phylum', 'Species', 'Project', 'Study', 'Emb', 'Stage',
                                  'CellNumber', 'Ref', 'Link'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),
        ) + cnido_dl_datasets_topic();
    }
}

/** 数据集的 SQL 计数语句。busco 要排除源文件被误导入的表头行。 */
if (!function_exists('cnido_dl_dataset_count_sql')) {
    function cnido_dl_dataset_count_sql($key)
    {
        if ($key === 'busco') {
            return "SELECT COUNT(*) FROM busco WHERE BUSCO_ID <> 'BUSCO_ID'";
        }
        $all = cnido_dl_datasets();
        if (!isset($all[$key])) { return ''; }
        return 'SELECT COUNT(*) FROM `' . $all[$key]['table'] . '`';
    }
}
