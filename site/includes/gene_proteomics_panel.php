<?php
/* ===========================================================================
 * includes/gene_proteomics_panel.php
 *
 * Embeddable panel that shows proteomic evidence for a single gene on
 * gene_detail.php.  This is the implementation of
 *
 *     Referee 2, comment 1   "gene-centric cross-links"
 *     Referee 3, comment 10(j) "proteomic data should be visible on gene pages"
 *
 * and of the user's own note that necessary and useful information -- such as
 * expression evidence -- should be visualised on the relevant page rather than
 * left for the user to find.  Before this panel existed the proteomics module
 * and the gene pages were two disconnected islands: a protein was reported as a
 * bare accession and there was no way to get from it to the gene, nor from the
 * gene to the evidence that its protein had actually been observed.
 *
 * ---------------------------------------------------------------------------
 * INSTALLATION -- one file, one line
 * ---------------------------------------------------------------------------
 * Copy this file to  includes/gene_proteomics_panel.php  on the server, then
 * add ONE line to gene_detail.php at the point where you want the panel to
 * appear, i.e. immediately after the existing annotation blocks:
 *
 *     require_once __DIR__ . '/includes/gene_proteomics_panel.php';
 *     render_gene_proteomics_panel($gene, $species);
 *
 * `$gene` and `$species` must be the variables gene_detail.php already holds
 * (the panel also accepts a prefixed accession and strips the prefix itself,
 * so passing `EDIAP_KXJ04192.1` works as well as `KXJ04192.1`).
 *
 * If an existing database connection is in scope, pass it as the third
 * argument to reuse it; otherwise the panel opens its own.
 *
 * ---------------------------------------------------------------------------
 * BEHAVIOUR
 * ---------------------------------------------------------------------------
 * The panel is deliberately silent when there is nothing to say:
 *   * proteomics tables absent        -> renders nothing
 *   * gene has no proteomic evidence  -> renders nothing
 * so adding the include line cannot break any existing gene page, and a gene
 * that has not been observed at the protein level looks exactly as it does
 * today.  When there IS evidence the panel shows, per dataset: the number of
 * unique peptides, PSMs, sequence coverage, best q-value and the actual peptide
 * sequences -- and links back to the dataset and to the full protein table, so
 * the cross-linking runs in both directions.
 * =========================================================================== */

if (!function_exists('cnido_gpe_h')) {
    function cnido_gpe_h($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Strip a CnidoSite proteome prefix from an accession.
 *
 * The reference proteomes use headers of the form `<ABBR>_<accession>`
 * (e.g. EDIAP_KXJ04192.1) while gene_detail.php resolves the accession without
 * the prefix (KXJ04192.1).  Accept either form so the caller cannot get it
 * wrong.
 */
if (!function_exists('cnido_gpe_strip_prefix')) {
    function cnido_gpe_strip_prefix($gene) {
        $gene = trim((string)$gene);
        if ($gene === '' || strpos($gene, 'CRAP_') === 0) {
            return $gene;
        }
        /* Only strip when the prefix looks like a proteome abbreviation:
           uppercase letters/digits followed by an accession containing a dot.
           This avoids mangling accessions that legitimately contain '_'
           (e.g. `XP_001617561.3`, `maker-scaffold_12-augustus-...`). */
        if (preg_match('/^([A-Z][A-Z0-9]{2,9})_(.+)$/', $gene, $m)) {
            $rest = $m[2];
            if (preg_match('/^[A-Za-z]{2,4}_/', $rest) || strpos($rest, '.') !== false) {
                return $rest;
            }
        }
        return $gene;
    }
}

/**
 * Fetch the proteomic evidence rows for one gene.
 *
 * @return array list of associative rows, empty when there is no evidence or
 *               the proteomics tables have not been imported.
 */

/* cnido_gene_in()（两种基因号写法 -> SQL IN 列表）来自 state.php。 */
require_once __DIR__ . '/state.php';

/* cnido_gp_css() / cnido_gp_openbtn() 来自 gene_panels_common.php。
   本面板原本不依赖它，2026-09 起「这个基因没有肽段证据、但该物种有蛋白组」这条
   分支要给一个到分析页的按钮，按钮样式与全站六个面板共用一套，所以引入。
   引入顺序没问题：gene_panels_common 自己会 require rnaseq_expression_panel，
   而两个文件都是纯函数 + 幂等的 cnido_gp_css()。 */
require_once __DIR__ . '/gene_panels_common.php';

/* ---- helper: run a query, and remember if the server rejected it --------
   mysqli_query() returns false for SQL the server refuses -- a reserved word
   used as a column name, a column the schema does not have.  Every caller
   below tests the result with `while ($q && ...)`, so a rejected statement
   renders as an empty page instead of an error.  On a catalogue page that is
   worse than a crash: "0 datasets" is a claim about the data, and it is
   false.  Failures are recorded here and surfaced by the render function.

   这段原先定义在下方 `if (!($conn instanceof mysqli))` 块**内部**：调用方自己
   传了 $conn 时整块被跳过，函数从未定义，第一处 @cnido_q(...) 就变成
   「Call to undefined function」；web SAPI 的 display_errors 是 Off，页面于是
   被静默截断（白页），错误日志里才有线索。定义必须提到顶层。

   同时按名称加 gpe 前缀：proteomic_dataset.php、proteomic_reanalysis.php、
   proteomic_reprocessed.php 里各有一份同名的 cnido_q()（写的是它们自己的
   $__sqlError），靠 function_exists 互相顶掉时，本文件会读到别人的错误变量。 */
if (!function_exists('cnido_gpe_q')) {
    $GLOBALS['cnido_gpe_sql_error'] = '';
    function cnido_gpe_q($conn, $sql) {
        $r = mysqli_query($conn, $sql);
        if ($r === false && $GLOBALS['cnido_gpe_sql_error'] === '') {
            $GLOBALS['cnido_gpe_sql_error'] = mysqli_error($conn);
        }
        return $r;
    }
}

if (!function_exists('cnido_gpe_sql_error')) {
    /** 本次请求里第一条被服务端拒绝的语句的错误信息；没有则空串。 */
    function cnido_gpe_sql_error() {
        return isset($GLOBALS['cnido_gpe_sql_error']) ? $GLOBALS['cnido_gpe_sql_error'] : '';
    }
}

if (!function_exists('cnido_gpe_fmt_q')) {
    /**
     * q 值的显示写法。
     *
     * 库里存的是原始浮点，直接印出来同一列里位数不一（0.0000707064 八位、
     * 0.000121477 九位），列看着是歪的；而统计块原来用 number_format(...,4)，
     * 把 7.07e-5 印成 0.0001 —— 差 40%，对一个 FDR 值来说是把话说错了。
     * 统一六位小数：同一列对齐，且小到 1e-5 的 q 值仍然可读（0.000071）。
     */
    function cnido_gpe_fmt_q($v) {
        if ($v === null || $v === '' || !is_numeric($v)) { return '&ndash;'; }
        return number_format((float)$v, 6);
    }
}

if (!function_exists('cnido_gene_proteomic_evidence')) {
    function cnido_gene_proteomic_evidence($gene, $species = null, $conn = null) {
        $gene = cnido_gpe_strip_prefix($gene);
        if ($gene === '') {
            return array();
        }

        $own = false;
        if (!($conn instanceof mysqli)) {
            $conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
            if ($conn->connect_error) {
                return array();
            }
            $conn->set_charset('utf8mb4');
            $own = true;
        }

        /* the tables may simply not exist yet on this deployment */
        $chk = @cnido_gpe_q($conn, "SHOW TABLES LIKE 'proteomic_proteins'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($own) { $conn->close(); }
            return array();
        }

        $g = mysqli_real_escape_string($conn, $gene);
        /* gene_id 有两种存法（`…15160.1` 与 `…15160`）。只按原样匹配时，
           `KXJ17389.1` 能出结果而 `KXJ17389` 出 0 字节 —— 同一份数据，页面
           一半有、一半没有。走 cnido_gene_in() 扩展成 IN 列表。 */
        $gIn = cnido_gene_in($conn, $gene);
        $sql = "SELECT p.dataset_id, p.protein_id, p.n_psms, p.n_unique_peptides,
                       p.coverage_pct, p.length, p.best_q, p.description,
                       d.pxd, d.species, d.tissue, d.treatment, d.instrument,
                       d.cnido_engine, d.cnido_precursor, d.cnido_fragment,
                       d.cnido_fixed, d.cnido_variable, d.cnido_fdr_psm,
                       d.proteome_file, d.status
                  FROM proteomic_proteins p
                  JOIN proteomic_datasets d ON d.dataset_id = p.dataset_id
                 WHERE p.gene_id IN ($gIn) AND p.is_contaminant = 0";
        if ($species !== null && $species !== '') {
            $sql .= " AND d.species = '" . mysqli_real_escape_string($conn, $species) . "'";
        }
        $sql .= " ORDER BY p.n_unique_peptides DESC, p.n_psms DESC";

        $rows = array();
        $q = @cnido_gpe_q($conn, $sql);
        while ($q && ($x = mysqli_fetch_assoc($q))) {
            $rows[] = $x;
        }
        if ($own) { $conn->close(); }
        return $rows;
    }
}

/**
 * Peptides observed for one gene in one dataset (for the expandable detail).
 */
if (!function_exists('cnido_gene_peptides')) {
    function cnido_gene_peptides($gene, $dataset_id, $conn = null) {
        $gene = cnido_gpe_strip_prefix($gene);
        if ($gene === '' || $dataset_id === '') {
            return array();
        }
        $own = false;
        if (!($conn instanceof mysqli)) {
            $conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
            if ($conn->connect_error) { return array(); }
            $conn->set_charset('utf8mb4');
            $own = true;
        }
        $d = mysqli_real_escape_string($conn, $dataset_id);
        $out = array();
        $q = @cnido_gpe_q($conn,
            "SELECT peptide, q_value FROM proteomic_peptides
              WHERE gene_id IN (" . cnido_gene_in($conn, $gene) . ") AND dataset_id = '$d'
              ORDER BY q_value ASC LIMIT 200");
        while ($q && ($x = mysqli_fetch_assoc($q))) { $out[] = $x; }
        if ($own) { $conn->close(); }
        return $out;
    }
}

if (!function_exists('cnido_gpe_species_has_data')) {
    /**
     * 这个物种在 proteomic_datasets 里有没有数据集？返回命中时用的规范拉丁名，没有则 ''。
     *
     * 2026-09-28 起渲染路径**不再调用这个函数**：那时改成了把该物种的每一个数据集
     * 都列出来，于是「这个物种有没有蛋白组数据」由 cnido_gene_proteomic_datasets()
     * 是否返回空数组回答，不需要再单独问一次。留着是因为它是模块对外的稳定入口
     * （「这个物种有没有蛋白组」是个独立问题），删掉会让别的调用方失去一个已文档化
     * 的工具；但改渲染逻辑时不要以为它在链路上。
     *
     * 用途：基因页上「这个基因没有肽段证据」与「这个物种根本没做过蛋白组」是两回事。
     * 前者应该给一条到分析页的出口（读者想看的是这个物种的蛋白组全景），后者才该
     * 保持安静。原实现两种情况都 return 掉，页面上连「蛋白组」这三个字都不出现。
     *
     * species 必须是 proteomic_datasets.species 里那个精确串（拉丁名）。
     * gene_detail.php 传下来的正是 abbr 表第 0 列的拉丁全名，两边对得上。
     */
    function cnido_gpe_species_has_data($conn, $species)
    {
        $species = trim((string)$species);
        if ($species === '') { return ''; }

        $own = false;
        if (!($conn instanceof mysqli)) {
            $conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
            if ($conn->connect_error) { return ''; }
            $conn->set_charset('utf8mb4');
            $own = true;
        }
        $out = '';
        $chk = @cnido_gpe_q($conn, "SHOW TABLES LIKE 'proteomic_datasets'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $e = mysqli_real_escape_string($conn, $species);
            $q = @cnido_gpe_q($conn, "SELECT COUNT(*) FROM proteomic_datasets WHERE species = '$e'");
            if ($q && ($r = mysqli_fetch_row($q)) && (int)$r[0] > 0) { $out = $species; }
        }
        if ($own) { $conn->close(); }
        return $out;
    }
}

/* ---------------------------------------------------------------------------
 * 这个物种的**全部**重处理蛋白组数据集，一个数据集一行，并带上这个基因在各数据集里的状态。
 *
 * 与上面的 cnido_gene_proteomic_evidence() 只差 JOIN 的方向：那个是
 * proteomic_proteins JOIN proteomic_datasets（只能列出「有肽段」的数据集），
 * 这个是 proteomic_datasets LEFT JOIN proteomic_proteins（一个数据集都不少）。
 * 基因页要的是后者 —— 「这个基因在第 3 份数据里没有」这句话，只有在第 3 份数据
 * 也印在表上的时候才读得懂；只印命中的那几份，读者无从知道分母是几。
 *
 * 一个数据集最多出一行：(dataset_id, gene_id) 在本表内唯一（全表查过，0 例重复），
 * 而 cnido_gene_in() 扩展出的两种基因号写法也不会同时存在于同一个数据集里
 * （proteomic_proteins 里存的是哪种写法就是哪种，没有一份数据两种都存）。
 * 仍然加了 ORDER BY 把命中行排在最前，让「有」和「没有」一眼分得开。
 * ------------------------------------------------------------------------- */
if (!function_exists('cnido_gene_proteomic_datasets')) {
    /**
     * @return array 每个数据集一行；命中这个基因的行带 protein_id / n_unique_peptides /
     *               coverage_pct / best_q，未命中的行这几列为 null。
     *               物种没有蛋白组数据、表不存在或连接失败时返回空数组。
     */
    function cnido_gene_proteomic_datasets($gene, $species = null, $conn = null) {
        $gene = cnido_gpe_strip_prefix($gene);
        $species = trim((string)$species);
        if ($gene === '' || $species === '') {
            return array();
        }

        $own = false;
        if (!($conn instanceof mysqli)) {
            $conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
            if ($conn->connect_error) {
                return array();
            }
            $conn->set_charset('utf8mb4');
            $own = true;
        }

        if (!cnido_gp_table_exists($conn, 'proteomic_datasets')) {
            if ($own) { $conn->close(); }
            return array();
        }

        $gIn = cnido_gene_in($conn, $gene);
        $sp  = mysqli_real_escape_string($conn, $species);
        $sql = "SELECT d.dataset_id, d.pxd, d.species, d.tissue, d.treatment, d.instrument,
                       d.status, d.status_note,
                       d.n_psms AS ds_psms, d.n_peptides AS ds_peps, d.n_proteins AS ds_proteins,
                       d.cnido_engine, d.cnido_precursor, d.cnido_fragment,
                       d.cnido_fixed, d.cnido_variable, d.cnido_fdr_psm, d.proteome_file,
                       p.protein_id, p.n_psms, p.n_unique_peptides, p.coverage_pct,
                       p.best_q, p.description
                  FROM proteomic_datasets d
                  LEFT JOIN proteomic_proteins p
                         ON p.dataset_id = d.dataset_id
                        AND p.gene_id IN ($gIn)
                        AND p.is_contaminant = 0
                 WHERE d.species = '$sp'
                 ORDER BY (p.protein_id IS NULL), p.n_unique_peptides DESC, d.pxd";

        $rows = array();
        $q = @cnido_gpe_q($conn, $sql);
        while ($q && ($x = mysqli_fetch_assoc($q))) {
            $rows[] = $x;
        }
        if ($own) { $conn->close(); }
        return $rows;
    }
}

/**
 * Render the panel.
 *
 * 何时整块不出现：这个物种一份重处理蛋白组数据集都没有（proteomic_datasets 里
 * 查不到该物种），或者蛋白质组的表根本没导入。三百多个没做过蛋白组的物种页上
 * 不该多出一张说不了任何事的卡片。
 *
 * 什么时候一定出现：「这个物种有蛋白组、但这个基因没被鉴定到」。这是本面板最常
 * 见的情形（实测 Nematostella vectensis 的两个数据集、4,058 个蛋白里没有一个
 * 是 XP_048581300.1），也是 2026-09-28 用户要求里那句「每个数据都要有链接」最
 * 需要的场合 —— 只印一句"没有证据"、不给数据集清单，读者无法核实，也不知道
 * 分母是几。所以现在把该物种的每一个数据集都逐行列出来，命中排前、未命中排后，
 * 每行都带这个基因在该数据集里的状态和出口。
 *
 * @param string     $gene    accession, with or without the proteome prefix
 * @param string|null $species optional species filter (recommended)
 * @param mysqli|null $conn    optional existing connection
 */
if (!function_exists('render_gene_proteomics_panel')) {
    function render_gene_proteomics_panel($gene, $species = null, $conn = null) {
        /* 卡片外壳与按钮的样式。幂等，本页多个面板只出一份。 */
        echo cnido_gp_css();
        $datasets = cnido_gene_proteomic_datasets($gene, $species, $conn);

        if (empty($datasets)) {
            /* 查询失败与「这个物种确实没有蛋白组」是两件事，必须分开说 ——
               前者渲染成一张空卡片，读者读到的是关于数据的一句假话。 */
            $err = cnido_gpe_sql_error();
            if ($err !== '') {
                echo '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;'
                   . 'padding:14px 16px;margin:16px 0">'
                   . '<h4 style="margin:0 0 4px;font-size:15px;color:#92400e">Proteomic evidence</h4>'
                   . '<p style="font-size:16px;color:#92400e;margin:0;line-height:1.65">'
                   . 'The proteomics query could not be answered: <code>'
                   . htmlspecialchars($err, ENT_QUOTES, 'UTF-8')
                   . '</code>. This is a query failure, not evidence that the gene is absent from the proteomes.'
                   . '</p></div>';
            }
            /* 没有数据集可列 —— 这个物种没做过蛋白组，保持安静。
               （「有数据集但这个基因没被鉴定到」不在这里：那种情况下面会逐行印出来。） */
            return;
        }

        $h = 'cnido_gpe_h';
        $geneClean = cnido_gpe_strip_prefix($gene);
        $sp = (string)$datasets[0]['species'];   /* 全表同一物种（查询按精确种名过滤） */

        /* 命中的数据集：统计块和角标只数这些。未命中的行一样要印，但不进统计。 */
        $hits = array();
        foreach ($datasets as $r) {
            if (trim((string)$r['protein_id']) !== '') { $hits[] = $r; }
        }
        $nAll = count($datasets);
        $nHit = count($hits);

        $totPep = 0; $totPsm = 0; $maxCov = 0.0; $bestQ = null;
        foreach ($hits as $r) {
            $totPep += (int)$r['n_unique_peptides'];
            $totPsm += (int)$r['n_psms'];
            if ((float)$r['coverage_pct'] > $maxCov) { $maxCov = (float)$r['coverage_pct']; }
            if ($r['best_q'] !== null && $r['best_q'] !== '') {
                $q = (float)$r['best_q'];
                if ($bestQ === null || $q < $bestQ) { $bestQ = $q; }
            }
        }

        /* 跳转条上那个点：绿=这个基因在某份蛋白组里鉴定到了；灰=这个物种有蛋白组
           但这个基因不在里面（本页最常见的情形，必须让它在最上面就能看出来）。 */
        cnido_gp_nav_add(
            'proteomics',
            'Proteomic evidence',
            $nHit > 0
                ? $totPep . ' peptide' . ($totPep === 1 ? '' : 's')
                : $nAll . ' dataset' . ($nAll === 1 ? '' : 's') . ', no hit',
            $nHit > 0 ? 'yes' : 'no'
        );

        /* 「查这个基因」的落点，每一行都一样：该页的 q 是对 gene_id / protein_id /
           description 的子串检索（见 proteomic_reanalysis.php 的 cnido_like_any），
           命中的行点开就是这个基因，没命中的行点开该页会明说「这个视图里有蛋白，
           但没有一个匹配」（该页专门为这两种空结果写了文案）。 */
        $geneLink = '/proteomic_reanalysis.php?species=' . urlencode($sp)
                  . '&amp;q=' . urlencode($geneClean);
        ?>
<div class="gpe-panel cn-anchor" id="proteomics">
  <style>
  <?php /* 卡片外壳（边框/圆角/内边距/投影）与标题、副标题的排版已上移到
     includes/gene_panels_common.php 的 cnido_gp_css()，与其余五个面板共用一套。
     这里只留本面板自己的：统计块、覆盖度条、肽段折叠区、来源脚注。 */ ?>
  .gpe-stats{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 14px}
  .gpe-stat{flex:1 1 110px;background:#f8fafc;border-radius:8px;padding:9px 12px;text-align:center}
  .gpe-stat b{display:block;font-size:20px;color:#1d4ed8;line-height:1.25}
  .gpe-stat span{font-size:13px;color:#64748b}
  <?php /* 表本身挂 gridtable：表头、行分隔线、斑马纹、悬停、链接配色全部由
     templatemo_style.css 的共用样式提供（与 core 的 table.cc 一致）。
     原来这里自带的一整套是照着旧样式抄的近似值（悬停底色 #f8fafc 与共用样式的
     #f1f5f9 差一档、边框在上不在下、字号小 1px），统一到共用样式后删掉。 */ ?>
  .gpe-bar{background:#e2e8f0;border-radius:4px;height:7px;min-width:60px;position:relative;overflow:hidden}
  .gpe-bar i{position:absolute;left:0;top:0;bottom:0;background:linear-gradient(90deg,#60a5fa,#1d4ed8);
    border-radius:4px;display:block}
  .gpe-peps{background:#0f172a;color:#64748b;border-radius:7px;padding:9px 11px;margin:5px 0 0;
    font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;line-height:1.7;
    word-break:break-all;display:none}
  .gpe-peps.on{display:block}
  .gpe-toggle{font-size:12px;color:#1d4ed8;cursor:pointer;text-decoration:none;
    border-bottom:1px dotted #1d4ed8}
  .gpe-src{font-size:12px;color:#64748b;margin-top:12px;line-height:1.6}
  .gpe-src code{background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:12px}
  </style>

  <h3>Proteomic evidence</h3>
  <p class="gpe-sub">
    <?php if ($nHit > 0): ?>
      Peptides from this gene's protein product were identified in
      <b><?= $nHit ?></b> of the <b><?= $nAll ?></b> re-processed proteomic dataset<?= $nAll == 1 ? '' : 's' ?>
      covering <i><?= $h($sp) ?></i>, at a false discovery rate of q &le; 0.01 (Comet + Percolator).
      <?php if ($nHit < $nAll): ?>
        The remaining <?= $nAll - $nHit ?> are listed below with the reason this gene is not in them.
      <?php endif; ?>
    <?php else: ?>
      No peptide from this gene was identified in <b>any</b> of the
      <b><?= $nAll ?></b> re-processed proteomic dataset<?= $nAll == 1 ? '' : 's' ?>
      covering <i><?= $h($sp) ?></i> (q &le; 0.01, Comet + Percolator).
      Every dataset is listed below with its own result, so the statement can be checked
      dataset by dataset rather than taken on trust.
    <?php endif; ?>
  </p>

  <?php if ($nHit > 0): ?>
  <div class="gpe-stats">
    <div class="gpe-stat"><b><?= $totPep ?></b><span>Unique peptides</span></div>
    <div class="gpe-stat"><b><?= number_format($totPsm) ?></b><span title="Peptide-spectrum matches: one count for each spectrum that identified a peptide from this gene, so the same peptide sequence can contribute more than one. This is why PSMs are counted separately from unique peptides.">PSMs</span></div>
    <div class="gpe-stat"><b><?= number_format($maxCov, 1) ?>%</b><span>Max coverage</span></div>
    <div class="gpe-stat"><b><?= cnido_gpe_fmt_q($bestQ) ?></b><span>Best q-value</span></div>
    <div class="gpe-stat"><b><?= $nHit ?> / <?= $nAll ?></b><span>Datasets with a hit</span></div>
  </div>
  <?php endif; ?>

  <?php /* data-no-sort：命中的行后面紧跟一个 colspan=8 的隐藏展开行（肽段序列），
           两行是一对，展开区的 id 用的是数据行的序号 $i。js/table-sort.js 只会重排
           可见行，展开行留在原地 —— 排完序点「peptides」翻出来的是另一个数据集的
           肽段。这和「表体有 rowspan 就要 data-no-sort」是同一条规则。 */ ?>
  <table class="gridtable gpe-tbl" data-no-sort>
    <tr>
      <th style="width:10%">Dataset</th>
      <?php /* Tissue / condition 是自由文本（最长 94 字），居中难看，显式左对齐。 */ ?>
      <th class="tal">Tissue / condition</th>
      <?php /* Species 列删掉了：本表按精确种名过滤，整列都是同一个值，那一列的位置
               让给「This gene」——用户要的是「一眼看出这个基因在每份数据里的情况」。 */ ?>
      <th style="width:16%">This gene</th>
      <?php /* 数字列一律 .num（右对齐，全站表格的约定）；原来 Peptides / PSMs 的单元格
               写的是 align="center"，但共用样式里 `.gpe-panel table.gpe-tbl tr td`
               =(0,3,2) 会压掉表现属性，实际是左对齐 —— 表头也左，于是看上去"没对齐"
               只是因为列宽。改挂 .num，两边都右对齐。 */ ?>
      <th class="num" style="width:8%">Peptides</th>
      <th class="num" style="width:7%" title="Peptide-spectrum matches: one count for each spectrum that identified a peptide from this gene, so the same peptide sequence can contribute more than one. This is why PSMs are counted separately from unique peptides.">PSMs</th>
      <th style="width:13%">Coverage</th>
      <th class="num" style="width:9%">Best q</th>
      <?php /* 这一列装的是展开肽段序列的链接 / 到该数据集自己的清单，原来是个空 <th>。 */ ?>
      <th style="width:13%">Open</th>
    </tr>
    <?php foreach ($datasets as $i => $r):
        $hit    = (trim((string)$r['protein_id']) !== '');
        $cov    = (float)$r['coverage_pct'];
        $dsProt = (int)$r['ds_proteins'];
        /* 这个数据集自己一个蛋白都没鉴定出来（6 份是这样：4 份 MED-FASP 的 Glu-C
           残留组分 + PXD027774/PXD017756 这类没过 FDR 的）。此时「这个基因没被鉴定
           到」是一句没有信息量的话 —— 整个数据集都没有蛋白，得说清楚是数据集自己的
           结果，别让读者以为是在说这个基因。 */
        $dsEmpty = ($dsProt === 0);
        $dsLink  = '/proteomic_reanalysis.php?dataset=' . urlencode($r['dataset_id']);
    ?>
      <tr>
        <td><a href="/proteomic_dataset.php?dataset=<?= urlencode($r['dataset_id']) ?>"
               title="Full search parameters for this dataset"><?= $h($r['pxd']) ?></a></td>
        <td class="tal" style="font-size:12px"><?= $h($r['tissue']) ?>
          <?php if (!empty($r['treatment']) && $r['treatment'] !== '-'): ?>
            <br /><span style="color:#64748b"><?= $h($r['treatment']) ?></span>
          <?php endif; ?></td>
        <?php
        /* 这个基因在这份数据里的状态。三种情形要分得开，因为读者要据此决定下一步：
             · identified            —— 鉴定到了，点进去看这个基因；
             · not identified        —— 这份数据鉴定出了几百几千个蛋白，其中没有它；
             · no peptides called    —— 这份数据自己就没鉴定出任何蛋白（连肽段都没
                                        过 FDR），所以「这个基因在不在」无从谈起。
           原来这三种里只有第一种会出现在表上。 */
        /* title 是访客悬停时看到的文字，必须英文（这几条原来都是中文）。 */
        if ($hit) {
            $pill = '<span class="gp-pill gp-yes" title="Identified in this dataset: '
                  . (int)$r['n_unique_peptides'] . ' unique peptides, '
                  . (int)$r['n_psms'] . ' PSMs">identified</span>';
        } elseif ($dsEmpty) {
            $pill = '<span class="gp-pill gp-warn" title="This dataset identified no proteins at all, '
                  . 'so whether this gene is in it cannot be determined">no peptides called</span>';
        } else {
            $pill = '<span class="gp-pill gp-no" title="This dataset identified ' . number_format($dsProt)
                  . ' proteins, and this gene is not among them">not identified</span>';
        }
        ?>
        <td>
          <?php if ($hit): ?>
            <a class="gp-a" href="<?= $geneLink ?>"
               title="Open this gene on the Proteomic Analysis page"><?= $pill ?></a>
          <?php else: ?>
            <?= $pill ?>
          <?php endif; ?>
          <?php /* 每一行都给「查这个基因」的同一出口 —— 命中的行点开就是这个基因，
                  没命中的行点开该页会明说没有匹配。见 $geneLink 处说明。 */ ?>
          <div style="margin-top:4px;font-size:12px">
            <a class="gp-a" href="<?= $geneLink ?>">look this gene up &rarr;</a>
          </div>
        </td>
        <td class="num"><?= $hit ? (int)$r['n_unique_peptides'] : '<span style="color:#64748b">&ndash;</span>' ?></td>
        <td class="num"><?= $hit ? (int)$r['n_psms'] : '<span style="color:#64748b">&ndash;</span>' ?></td>
        <td>
          <?php if ($hit): ?>
            <div style="display:flex;align-items:center;gap:6px">
              <div class="gpe-bar"><i style="width:<?= min(100, $cov) ?>%"></i></div>
              <span style="font-size:12px;color:#475569"><?= $h($r['coverage_pct']) ?>%</span>
            </div>
          <?php else: ?>
            <span style="color:#64748b">&ndash;</span>
          <?php endif; ?>
        </td>
        <td class="num"><?= cnido_gpe_fmt_q($r['best_q']) ?></td>
        <td>
          <?php if ($hit): ?>
            <a class="gpe-toggle"
               onclick="var e=document.getElementById('gpe-p-<?= $i ?>');e.className=e.className=='gpe-peps on'?'gpe-peps':'gpe-peps on';return false;"
               href="#">peptides</a>
          <?php elseif ($dsEmpty): ?>
            <?php /* 这 6 份数据「鉴定为零」是它们**自己**的结论，PRIDE 上的原文结果
                     另有一份，proteomic_reanalysis.php 选中这份数据时会印出来。 */ ?>
            <a class="gp-a" href="<?= $dsLink ?>">published result &rarr;</a>
          <?php else: ?>
            <a class="gp-a" href="<?= $dsLink ?>">dataset proteins &rarr;</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php if ($hit): ?>
      <tr><td colspan="8" style="padding:0;border:none">
        <div class="gpe-peps" id="gpe-p-<?= $i ?>"><?php
          $peps = cnido_gene_peptides($geneClean, $r['dataset_id'], $conn);
          if (empty($peps)) {
              echo '<span style="color:#64748b">peptide sequences not loaded for this dataset '
                 . '(import proteomic_peptides.tsv to enable)</span>';
          } else {
              $out = array();
              foreach ($peps as $pp) {
                  $out[] = $h($pp['peptide']) . '  <span style="color:#64748b">q='
                         . $h($pp['q_value']) . '</span>';
              }
              echo implode("<br />\n", $out);
          }
        ?></div>
      </td></tr>
      <?php endif; ?>
    <?php endforeach; ?>
  </table>

  <?php /* 到蛋白组分析页的出口。q 传本基因 —— 该页的 q 是对 gene_id / protein_id /
           description 的子串检索（见 proteomic_reanalysis.php 的 cnido_like_any），
           所以点开就是这几行；species 用数据行里那个精确拉丁名（该页是精确匹配）。 */ ?>
  <div class="cn-openrow">
    <?= cnido_gp_openbtn('/proteomic_reanalysis.php?species=' . urlencode($sp)
          . '&q=' . urlencode($geneClean),
          $nHit > 0 ? 'Open this protein in Proteomic Analysis'
                    : 'Search ' . $h($sp) . ' proteins for this gene') ?>
    <?= cnido_gp_openbtn('/proteomic_reanalysis.php?species=' . urlencode($sp),
          'All proteins of ' . $h($sp), 'sec') ?>
  </div>

  <p class="gpe-src">
    <?php /* 参数脚注印的是命中行（没有命中就印第一份数据）—— 参数在数据集之间可能
             不同，所以同时提示去哪看完整出处。 */ ?>
    <?php $ref = $hits ? $hits[0] : $datasets[0]; ?>
    Search parameters for this evidence: <?= $h($ref['cnido_engine']) ?>,
    precursor <?= $h($ref['cnido_precursor']) ?>, fragment <?= $h($ref['cnido_fragment']) ?>,
    fixed <?= $h($ref['cnido_fixed']) ?>, variable <?= $h($ref['cnido_variable']) ?>,
    PSM-level q &le; <?= $h($ref['cnido_fdr_psm']) ?><?php
      /* proteome_file 没入库时是空串，空 <code> 会印出一个带底色的空白块，
         读起来像「against ___ .」缺了词。没有就不写这半句。 */
      if (trim((string)$ref['proteome_file']) !== ''): ?> against
    <code><?= $h($ref['proteome_file']) ?></code><?php endif; ?>.
    <?php if ($nAll > 1): ?>
      Parameters and searched proteome differ between datasets; open a dataset for its full provenance.
    <?php endif; ?>
  </p>

  <p class="gpe-src">
    <?php if ($nHit === 0): ?>
      A gene can be absent from the identified set because its protein was not expressed in the
      sampled tissue, was not digested into detectable peptides, or is not in the searched
      database &mdash; a null result here is not evidence that the gene is not translated.
    <?php else: ?>
      <b>not identified</b> means the dataset did identify proteins, but not this one;
      the number of unique peptides is a lower bound on how much of the protein was seen.
    <?php endif; ?>
    &nbsp;&middot;&nbsp;
    <a href="/proteomic_reanalysis.php?species=<?= urlencode($sp) ?>">all proteins for <?= $h($sp) ?></a>
  </p>
</div>
        <?php
    }
}
?>
