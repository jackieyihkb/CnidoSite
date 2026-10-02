<?php
/* =====================================================================
 * MAG 详情页 —— 从 MAGs.php 的目录表点进来的单个 MAG 着陆页
 *
 * 用户的诉求（2026-09-27）：
 *   「MAGs.php 这个界面，对于每个物种的基因组，用户点开可以看到详细的信息，
 *     如 cnidarians 物种的基因组一样，同时对该基因组的蛋白进行功能注释
 *     （包括 interpro、GO、KEGG 等），注释完在基因组详细界面提供注释信息
 *     （分页展示，有搜索框，一个基因一行）」
 *
 * 本页对应 `MAGs.AssemblyAccession`（315 行 315 个不同值，是这张表唯一可用
 * 的自然键）。页面的信息层与 speciesinfo.php 对齐，但有一处根本差别必须说清：
 *
 *   **这里的「基因组」是微生物的，不是刺胞动物的。**
 *   MAGs 表里 `Species` 是组装出来的微生物（如 Brevundimonas sp. A19_0），
 *   `host` 才是它来自哪种刺胞动物。所以宿主只是「来源」字段，不能像
 *   speciesinfo.php 那样把整页写成一个物种的资源汇总 —— 微生物本身不在
 *   覆盖矩阵里，也没有转录组 / 单细胞 / 表观组可点。
 *
 * 蛋白来源：NCBI 已经为该组装做好的注释蛋白（PGAP 或提交者自己的注释），
 * 不用我们预测基因。NCBI 只注释了约一半的 MAG，所以本页对「没有蛋白」的
 * MAG 有明确措辞，而不是显示一张空表。
 *
 * ---------------------------------------------------------------------
 * 注释表设计（与站内既有的 <ABBR>_ipr / <ABBR>_go / <ABBR>_KEGG 的列名一致）
 *
 * 站内其它模块是「一个物种一套表」（148 个物种 × 5 张），照搬到 MAG 上就是
 * 315 套 = 上千张表，无法管理。这里改成**共享表 + mag 列**：mag_interpro /
 * mag_go_terms / mag_kegg_terms / mag_pfam_hits / mag_panther_hits 各一张，
 * 用 `mag`（AssemblyAccession）分区。前四张来自 InterProScan 一次跑（GO 与
 * interpro 同源，Pfam/PANTHER 是同一份 TSV 里的 member DB 命中），
 * mag_kegg_terms 来自 KofamScan。
 * （表名为什么不能叫 mag_ipr / mag_go / mag_kegg / mag_pfam / mag_panther：
 *   见下面 $T 探测处的注释。）
 *
 * 另加 mag_annot 一张「每个蛋白一行」的基因清单（protein / description）。
 * 它是必需的：没有功能注释的蛋白也要占一行（「一个基因一行」是按蛋白组
 * 全集算的，不是按有命中的算）。
 *
 * 键列刻意用 varchar 而不是站内习惯的 text：TEXT 列只能建前缀索引，而这三张
 * 表在规模上会到百万行级（315 组装 × 数千蛋白 × 若干命中），前缀索引在这里
 * 不够用。列名与既有的 <ABBR>_* 保持一致，渲染代码可以照搬。
 *
 * 表由 Phase B 的注释流水线建立并灌数；本页在表不存在时给出「尚未注释」的
 * 明确状态，而不是报错或显示空表（同 gene_detail.php 的 $T 探测思路）。
 * ===================================================================== */

require_once __DIR__ . '/includes/state.php';
/* cnido_coverage() / cnido_spcov_resolve()：把宿主拉丁名归一成规范 abbr1，
   只有归一成功才给 speciesinfo.php 链接 —— MAGs.php 原来那版直接拼
   speciesinfo.php?species=<abbr1>，而 30 个宿主里有 16 个在 abbr 表里查不到，
   于是 242/315 个 MAG 的宿主链接指向 speciesinfo.php?species=（空参数）。
   见本文件末尾「宿主解析」那一段。 */
require_once __DIR__ . '/includes/coverage.php';
require_once __DIR__ . '/includes/species_coverage_panel.php';

/* ========== 输入 ========== */
/* acc：NCBI 组装号（GCA_017744255.1）。过滤成只允许字母数字与 . _ -，
   长度按 NCBI 现有最长组装号放宽到 64，再进 SQL 前另做一次转义。 */
$acc = '';
if (isset($_GET['acc'])) {
    $acc = preg_replace('/[^A-Za-z0-9_.\-]/', '', trim((string)$_GET['acc']));
    $acc = substr($acc, 0, 64);
}

/* q：搜索词。按字符截断（中文按字节切会切出半个字符）。 */
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$q = function_exists('mb_substr') ? mb_substr($q, 0, 100, 'UTF-8') : substr($q, 0, 100);

/* 分页：与站内其它结果页同一套取值（go_result.php / MAGs.php 的 10/20/50/100）。 */
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
$page     = isset($_GET['page']) ? intval($_GET['page']) : 1;
if (!in_array($per_page, array(10, 20, 50, 100), true)) { $per_page = 20; }
if ($page < 1) { $page = 1; }

/* ========== 表头排序状态 ==========
   蛋白表按服务端排：一页只有 20 行，整个 MAG 有几千个蛋白，浏览器里排只能排到
   当前这一页。
   自然次序是 ORDER BY a.protein，正好就是 Protein 列的升序，所以默认键直接
   设成 'protein' —— 初始时 Protein 表头显示 ↑，说的是实话（对比那些自然次序
   对应不到任何一列的页面：那边用一个不在表头里的隐藏默认键，全部箭头才是 ↕）。
   并列键：一个 MAG 内 a.protein 是主键的一半（PRIMARY KEY(mag, protein)），
   查询又被 a.mag = '…' 钉死，所以它本身就是全序，翻页不会重复或漏行。

   InterPro / GO / KEGG / Pfam / PANTHER 五列**不给箭头**：注释是「一个蛋白对多个
   条目」的一对多关系，排序键不唯一，箭头点下去只会在并列里重排、看着像坏了。
   表头只给 protein / desc 两列排序链接。 */
require_once __DIR__ . '/includes/sort_head.php';
$__sortKeys = array('protein' => 'a.protein', 'desc' => 'a.description');
list($__sort, $__dir, $__order) = cnido_sort_state($__sortKeys, 'protein', array('a.protein'));
$__orderSql = ($__order !== '' ? ' ORDER BY ' . $__order : '');

function safe($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { error_log('CnidoSite: database connection failed: ' . $conn->connect_error); die('The database is temporarily unavailable. Please try again in a moment.'); }
$conn->set_charset('utf8mb4');

/* ========== 注释表存在性探测 ==========
   information_schema 一次问完，别对每张表试 SELECT（表不存在时 mysqli 会
   在 Web SAPI 上静默失败，探测结果与「表存在但没有行」分不开）。 */
/* 表名**必须**避开站内的保留后缀（`_ipr` / `_go` / `_KEGG` / `_pfam` /
   `_locus` / `_TPM` / `_TE` / `_ATAC` / `_ChIP` / `_DHS` / `_cellmarker` /
   `_proteomics` / `_panther`）。站内好几处按「表名 = <ABBR>_<后缀>」的模式
   枚举注释表，把 315 个 MAG 共用的共享表误当成某个物种的表：
     - includes/coverage.php 的 `$tblBySuffix` → 覆盖矩阵里多出一个幽灵物种
       「MAG」（实测 `cov['MAG'] = {'function': 1}`）
     - includes/domain_search.php 的 ds_species_list() → 功能域检索多出 `mag`
     - gene_detail.php 的「all N annotated genomes」计数会 +1
   所以五张表叫 mag_interpro / mag_go_terms / mag_kegg_terms / mag_pfam_hits /
   mag_panther_hits，不叫 mag_ipr / mag_go / mag_kegg / mag_pfam / mag_panther
   （后三个实测确实会污染覆盖矩阵缓存）。列名仍与站内 <ABBR>_* 逐字一致，
   渲染口径才能照搬 gene_detail.php。 */
$T = array('annot' => false, 'ipr' => false, 'go' => false, 'kegg' => false,
           'pfam' => false, 'panther' => false);
$__probe = "'mag_annot','mag_interpro','mag_go_terms','mag_kegg_terms',"
         . "'mag_pfam_hits','mag_panther_hits'";
if ($__r = mysqli_query($conn, "SELECT TABLE_NAME FROM information_schema.TABLES "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($__probe)")) {
    while ($__t = mysqli_fetch_row($__r)) {
        $__n = strtolower($__t[0]);
        if ($__n === 'mag_annot')      { $T['annot'] = true; }
        elseif ($__n === 'mag_interpro')  { $T['ipr']  = true; }
        elseif ($__n === 'mag_go_terms')  { $T['go']   = true; }
        elseif ($__n === 'mag_kegg_terms') { $T['kegg'] = true; }
        elseif ($__n === 'mag_pfam_hits')  { $T['pfam'] = true; }
        elseif ($__n === 'mag_panther_hits') { $T['panther'] = true; }
    }
}

/* ========== GO 命名空间的显示缩写 ==========
   mag_go_terms.Category 在库里只有三个值，且都是全称：Molecular Function /
   Biological Process / Cellular Component（全表去重确认过，没有别的写法）。
   注释表里每个 GO 号后面都跟一个 "(全称)"，一屏几十行时 "Molecular Function"
   把 GO 号挤到很右边，读起来是负担；MF / BP / CC 是这一行的通行写法
   （GSEA.php 的选项也写成 "BP: Biological Process"）。
   2026-09-28：表格里只印缩写，全称挪进 title 悬浮提示，信息不丢。
   这张映射同时给搜索用 —— 页面既然只印 MF，用户就会照 MF 搜，
   光靠 g.Category LIKE '%MF%' 是命不中 "Molecular Function" 的。 */
$GO_CAT_SHORT = array('Molecular Function' => 'MF',
                      'Biological Process' => 'BP',
                      'Cellular Component' => 'CC');

/* ========== 这一条 MAG 的目录信息 ========== */
$accEsc = mysqli_real_escape_string($conn, $acc);
$mag = null;
if ($acc !== '') {
    $__mq = mysqli_query($conn, "SELECT `class`, host, Species, TaxonID, AssemblyAccession, "
        . "AssemblyLevel, AssembledSize, Nrcontigs, ContigN50, GCPercent, CheckMcompleteness, "
        . "CheckMcontamination, pubmed, link FROM MAGs WHERE AssemblyAccession = '$accEsc' LIMIT 1");
    if ($__mq) { $mag = mysqli_fetch_assoc($__mq); }
}

/* ========== 宿主 → speciesinfo.php ==========
   宿主解析不出来时**不给链接**，只显示名字。MAGs.php 原来那版无条件拼
   speciesinfo.php?species=<abbr1>，abbr 表里没有的宿主就拼出空参数，
   点进去是一个空白物种页 —— 那不是「链路问题」而是数据缺口，
   页面应该照实说，而不是给一个点开是空页的链接。 */
$__hostRes = null;
if ($mag && isset($mag['host']) && $mag['host'] !== '') {
    $__hostRes = cnido_spcov_resolve(cnido_coverage($conn), $mag['host']);
}

/* ========== 注释行数 / 当前页数据 ==========
   搜索是服务端做的：结果集是整个 MAG 的蛋白组（数千行），浏览器里过滤
   只能筛出当前这一页的十几行，分页与「共 N 条」都会对不上。 */
$annot_total = 0;
$annot_all   = 0;      /* 不带搜索词时该 MAG 的蛋白总数 */
$total_pages = 0;
$page_rows   = array();
$sig         = array('ipr' => array(), 'go' => array(), 'kegg' => array(),
                     'pfam' => array(), 'panther' => array());
$searchOn    = ($q !== '');

if ($mag && $T['annot']) {
    /* LIKE 元字符先转义、再做 SQL 转义 —— 顺序不能反：
       反过来的话 mysqli_real_escape_string 加的反斜杠会被 addcslashes 再处理一遍。 */
    $likeEsc = mysqli_real_escape_string($conn, '%' . addcslashes($q, '%_\\') . '%');

    $w = array("a.mag = '$accEsc'");
    if ($searchOn) {
        /* 蛋白号、locus_tag、功能描述三列都要能搜 —— 表里都印出来了，用户手上
           可能是其中任何一个。注意 locus_tag 只有约 15% 的蛋白有（见建表脚本的
           说明），所以它只能作为补充，键仍然是蛋白号。 */
        $or = array("a.protein LIKE '$likeEsc'", "a.locus_tag LIKE '$likeEsc'", "a.description LIKE '$likeEsc'");
        if ($T['ipr'])  { $or[] = "EXISTS (SELECT 1 FROM mag_interpro i WHERE i.mag = a.mag AND i.protein = a.protein AND (i.InterPro_term LIKE '$likeEsc' OR i.Description LIKE '$likeEsc' OR i.Source LIKE '$likeEsc'))"; }
        if ($T['go']) {
            /* 用户照着表格里印的缩写搜（MF / BP / CC）时要能命中 Category 的全称。 */
            $__goOr = "g.GO_term LIKE '$likeEsc' OR g.Description LIKE '$likeEsc' OR g.Category LIKE '$likeEsc'";
            $__catFull = array_search(strtoupper(trim($q)), $GO_CAT_SHORT, true);
            if ($__catFull !== false) {
                $__goOr .= " OR g.Category LIKE '" . mysqli_real_escape_string($conn, '%' . $__catFull . '%') . "'";
            }
            $or[] = "EXISTS (SELECT 1 FROM mag_go_terms g WHERE g.mag = a.mag AND g.protein = a.protein AND ($__goOr))";
        }
        if ($T['kegg']) { $or[] = "EXISTS (SELECT 1 FROM mag_kegg_terms k WHERE k.mag = a.mag AND k.protein = a.protein AND (k.KO LIKE '$likeEsc' OR k.Abbreviation LIKE '$likeEsc' OR k.Pathway LIKE '$likeEsc'))"; }
        if ($T['pfam']) { $or[] = "EXISTS (SELECT 1 FROM mag_pfam_hits f WHERE f.mag = a.mag AND f.protein = a.protein AND (f.Pfam_accession LIKE '$likeEsc' OR f.Pfam_name LIKE '$likeEsc' OR f.Description LIKE '$likeEsc'))"; }
        if ($T['panther']) { $or[] = "EXISTS (SELECT 1 FROM mag_panther_hits p WHERE p.mag = a.mag AND p.protein = a.protein AND (p.id LIKE '$likeEsc' OR p.anno LIKE '$likeEsc'))"; }
        $w[] = '(' . implode(' OR ', $or) . ')';
    }
    $where = implode(' AND ', $w);

    /* 「这个 MAG 到底有没有注释数据」用不带搜索词的计数判断，
       否则搜一个不存在的词会被说成「没有注释」。 */
    if ($__c = mysqli_query($conn, "SELECT COUNT(*) FROM mag_annot WHERE mag = '$accEsc'")) {
        $__cr = mysqli_fetch_row($__c);
        $annot_all = intval($__cr[0]);
    }

    if ($annot_all > 0) {
        if ($__c = mysqli_query($conn, "SELECT COUNT(*) FROM mag_annot a WHERE $where")) {
            $__cr = mysqli_fetch_row($__c);
            $annot_total = intval($__cr[0]);
        }
        $total_pages = ($per_page > 0) ? (int)ceil($annot_total / $per_page) : 0;
        if ($total_pages > 0 && $page > $total_pages) { $page = $total_pages; }
        $offset = ($page - 1) * $per_page;

        if ($__r = mysqli_query($conn, "SELECT a.protein, a.locus_tag, a.description FROM mag_annot a "
                . "WHERE $where$__orderSql LIMIT $offset, $per_page")) {
            while ($__x = mysqli_fetch_assoc($__r)) { $page_rows[] = $__x; }
        }

        /* 当前页的命中一次取齐（而不是每个蛋白一条查询）。
           蛋白号按原样拼 IN —— MAG 的号没有站内那种 `.1` / 无后缀两种写法，
           不需要 cnido_gene_id_forms()。 */
        if ($page_rows) {
            $ids = array();
            foreach ($page_rows as $__x) { $ids[] = "'" . mysqli_real_escape_string($conn, $__x['protein']) . "'"; }
            $in = implode(',', $ids);
            $grab = array(
                'ipr'  => array('mag_interpro',   'SELECT protein, InterPro_term, Type, Description, Source, URL FROM mag_interpro   WHERE mag = \'%s\' AND protein IN (%s)'),
                'go'   => array('mag_go_terms',   'SELECT protein, GO_term, Category, Description, Source, URL FROM mag_go_terms   WHERE mag = \'%s\' AND protein IN (%s)'),
                'kegg' => array('mag_kegg_terms', 'SELECT protein, KO, Abbreviation, Enzymes, Enzyme_ID, Pathway, Pathway_ID, Source FROM mag_kegg_terms WHERE mag = \'%s\' AND protein IN (%s)'),
                'pfam' => array('mag_pfam_hits', 'SELECT protein, Pfam_accession, Pfam_name, Description, Type, Source, URL FROM mag_pfam_hits WHERE mag = \'%s\' AND protein IN (%s)'),
                'panther' => array('mag_panther_hits', 'SELECT protein, id, anno, method, url FROM mag_panther_hits WHERE mag = \'%s\' AND protein IN (%s)'),
            );
            foreach ($grab as $k => $cfg) {
                if (!$T[$k]) { continue; }
                $sql = sprintf($cfg[1], $accEsc, $in);
                if ($__r = mysqli_query($conn, $sql)) {
                    while ($__x = mysqli_fetch_row($__r)) { $sig[$k][$__x[0]][] = $__x; }
                }
            }
        }
    }
}

/* ========== 分页链接的基串 ==========
   GET 表单会**替换**整个查询串（不是追加），所以搜索框必须把 acc / per_page
   一起用 hidden 带过去；同理分页链接必须自己带上 q，否则一翻页搜索词就丢了。
   browse.php:29-34 是同一个坑的既有解法。 */
$__baseQs = 'acc=' . urlencode($acc);
if ($searchOn) { $__baseQs .= '&q=' . urlencode($q); }
/* 当前排序也带上：翻页链接、「每页 N 条」和表头链接都是从这个串拼的，
   翻到第几页、改成每页几条，排序都还在（默认态不附加，URL 与以前一样短）。 */
$__sortQs = cnido_sort_qs($__sort, $__dir, $__sortKeys, 'protein');
if ($__sortQs !== '') { $__baseQs .= '&' . $__sortQs; }
/* 表头链接专用：把 per_page 也带上，点一次表头不会把每页条数改回默认。 */
$__hdrQs = $__baseQs . '&per_page=' . intval($per_page);
$__self = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');

/* 注释表是「一个蛋白一行、一行里每类资源各占一格」的合并布局，所以表头是 5 个
   单元格（不是 gene_detail.php 那种每一类资源单独一张表、每张表 4-5 列的表头）。
   表头的链接口径与基因详情页一致：InterPro 走 EBI InterPro entry、GO 走 AmiGO、
   KO 走 kegg.jp/entry/，pathway 走 cnido_kegg_map_url()（这一列存的多是 BRITE
   节点，入口与通路图不同，原因见下面 KEGG 单元格的注释）。 */
$annot_cols = array(
    array('key' => 'ipr',     'title' => 'InterPro',      'w' => '15%', 'hint' => 'InterPro entry, type and description'),
    array('key' => 'go',      'title' => 'Gene Ontology', 'w' => '14%', 'hint' => 'GO term, category (MF / BP / CC) and description'),
    array('key' => 'kegg',    'title' => 'KEGG',          'w' => '14%', 'hint' => 'KO, abbreviation and pathway'),
    array('key' => 'pfam',    'title' => 'Pfam',          'w' => '14%', 'hint' => 'Pfam accession, name, type and description'),
    array('key' => 'panther', 'title' => 'PANTHER',       'w' => '14%', 'hint' => 'PANTHER family and description'),
);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>MAG Detail - CnidoSite</title>
<meta name="keywords" content="Cnidaria, metagenome, MAG, metagenome-assembled genome, bin, CheckM, functional annotation" />
<meta name="description" content="One metagenome-assembled genome (MAG) from a cnidarian host: taxonomy, assembly statistics, completeness and contamination, and functional annotation" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<style>
<?php /* 分页样式 - 与其他页面保持一致 */ ?>
.pagination-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 20px;
    margin-top: 30px;
    border: 1px solid #e2e8f0;
}

.pagination-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.total-records {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.per-page-selector {
    display: flex;
    align-items: center;
    gap: 10px;
}

.per-page-selector select {
    padding: 8px 15px;
    border-radius: 6px;
    border: 2px solid #e2e8f0;
    background: white;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.per-page-selector select:hover {
    border-color:#1d4ed8;
}

.pagination-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin: 25px 0;
    flex-wrap: wrap;
}

.page-btn {
    padding: 10px 16px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #475569;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s ease;
    min-width: 44px;
    text-align: center;
}

.page-btn:hover {
    background: #f0f9ff;
    border-color:#1d4ed8;
    color:#1d4ed8;
}

.page-btn.active {
    background:#1d4ed8;
    border-color:#1d4ed8;
    color: white;
    font-weight: 600;
}

.page-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.go-to-page {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
    justify-content: center;
}

.go-to-page input {
    width: 70px;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    text-align: center;
    font-size: 15px;
}

.go-to-page button {
    padding: 8px 16px;
    background:#1d4ed8;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.go-to-page button:hover {
    background: #1d4ed8;
}

.external-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.external-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.search-box {
    margin: 20px 0;
    display: flex;
    justify-content: flex-end;
}

.search-box input {
    padding: 10px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    width: 350px;
    font-size: 15px;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.selection-form {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 25px;
    margin: 20px 0;
    border: 1px solid #e2e8f0;
}

.selection-form table {
    width: 100%;
    max-width: 500px;
}

.selection-form td {
    padding: 15px 10px;
}

.selection-form select {
    width: 100%;
    padding: 12px 15px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.selection-form select:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.submit-btn {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}


.current-species {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    margin: 15px 0;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

<?php /* 选择信息显示样式 */ ?>
.species-selection-info {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
    .pagination-info {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .pagination-nav {
        gap: 5px;
    }
    
    .page-btn {
        padding: 8px 12px;
        min-width: 36px;
    }
    
    .search-box {
        justify-content: flex-start;
    }
    
    .search-box input {
        width: 100%;
    }
}
</style>
<style>
<?php /* ---------------------------------------------------------------------
 * 单个 MAG 详情页专用样式
 *
 * 两张表都挂着 gridtable（配色、边框、留白、斑马纹、悬停、链接全部由共用样式
 * 提供），这里只补布局：.mag-annot 五列宽窄悬殊（InterPro/GO/KEGG 三列要塞下
 * 不定长的条目列表，蛋白号那列只有十几个字符），需要按内容自适应，所以显式写
 * table-layout:auto 并在 td 上恢复 white-space:normal（共用样式早年带 pre-wrap，
 * 会把 PHP 源码里的换行渲染成真的换行；现在那条已删，这里是保险丝）。
 * --------------------------------------------------------------------- */ ?>
table.mag-kv{table-layout:auto}
table.mag-kv th{white-space:nowrap}
table.mag-kv td{white-space:normal;word-break:break-word}
table.mag-annot{
    table-layout:auto;
    white-space:normal;
    word-break:normal;
    font-size:15px;
}
table.mag-annot th{white-space:nowrap;vertical-align:middle}
table.mag-annot td{vertical-align:top;padding:7px 9px}
table.mag-annot td.mag-gene{white-space:nowrap;font-weight:600}
table.mag-annot td.mag-gene span.mag-prod{display:block;font-weight:400;font-size:12px;color:#64748b}
table.mag-annot td.mag-desc{max-width:340px;text-align:left} /* 描述是自由文本，居中难看（同样的意思由 td 上的 tal 类表达，这里再钉一道） */
table.mag-annot a{color:#1d4ed8;text-decoration:none}
table.mag-annot a:hover{text-decoration:underline}
table.mag-annot span.mag-type{color:#64748b;font-size:12px}
<?php /* mag-sub 是每个 InterPro/GO/Pfam/PANTHER 条目**下面的描述正文**（「NADH:quinone
   oxidoreductase/Mrp antiporter, transmembrane domain」这类），不是角标 —— 2026-09-27
   全站统一字号时它和 mag-type 的旧值同为 11.5px，被按值分档一起判成了「极小辅助文字」，
   落进 12px 下限档。但这一列是本页真正要读的内容，全页 4,685 字符，12px 明显偏小。
   2026-09-28 抬到 15px（内容层）。mag-type 的 (Domain)/(Family) 与 mag-prod 的
   locus tag 保持 12px：那两类确实是标签和标识符。 */ ?>
table.mag-annot span.mag-sub{display:block;color:#64748b;font-size:15px;line-height:1.45}
table.mag-annot span.mag-none{color:#b6c0cc}
<?php /* 标题区：MAG 的微生物名 + 宿主。与站内其它详情页的标题块同一套读法。 */ ?>
.mag-title{margin:6px 0 2px;font-size:20px;font-weight:700;color:#1e293b;line-height:1.35}
.mag-title i{color:#af3a40}
.mag-sub{margin:0 0 14px;font-size:15px;color:#475569}
.mag-crumb{font-size:15px;margin:0 0 12px}
.mag-crumb a{color:#1d4ed8;text-decoration:none}
.mag-crumb a:hover{text-decoration:underline}

<?php /* 搜索框：MAGs.php 的 .search-box 是 flex-end，本页要的是「标签 + 输入 + 按钮」
   一行左对齐，所以在 form.mag-search 上凑平特异性覆盖（页面 <style> 在样式表
   之后，胜出）。 */ ?>
form.mag-search{justify-content:flex-start;align-items:center;gap:10px;flex-wrap:wrap}
form.mag-search input[type=text]{flex:1 1 420px;width:auto;max-width:620px}
form.mag-search button{padding:10px 18px;border-radius:8px;border:none;background:#1d4ed8;
    color:#fff;font-size:15px;font-weight:600;cursor:pointer}
form.mag-search button:hover{background:#1e40af}
form.mag-search .mag-clear{font-size:15px;color:#64748b}
<?php /* 「共 N 条」那一行的措辞与分页信息条区分开：这里是注释结果集，不是 MAG 目录。 */ ?>
.mag-count{font-size:15px;color:#475569;margin:14px 0 0}
.mag-count b{color:#0c4a6e}
</style>
</head>

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
                    
                    
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li><li><a href="/microsynteny.php">Microsynteny Analysis</a></li><li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li><li><a href="/mitdata.php">Mitogenomic Data</a></li>
                </ul>
            <li><a href="#">Transcriptome</a>
                <ul>
                    <li><a href="/cytoscape/trans_data.php">Transcriptomic Data</a></li>
                    <li><a href="/trans_assembly.php">Transcriptome Assembly</a></li>
                    <li><a href="/cytoscape/network.php">Network Analysis</a></li>
                    <li><a href="/cytoscape/network_expression.php">Dynamic Expression View</a></li>
                </ul>
            <li><a href="#">Single-cell</a>
                <ul>
                    <li><a href="/sn_data.php">Single-cell Data</a></li>
                    <li><a href="/cell_atlas.php">Cell Atlas</a></li>
                    <li><a href="/cell_marker.php">Cell Marker</a></li>
                    <li><a href="/gene_exp.php">Gene Expression</a></li>
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
            <li><a href="#" class="current">Metagenome</a>
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
/* =====================================================================
 * 页面正文
 * ===================================================================== */

if ($mag === null):
    /* acc 缺失或不在 MAGs 表里。不 302 也不空白页 —— 说清是哪个号没找到，
       并给回目录的入口（用户很可能是从旧链接 / 手改 URL 进来的）。 */
    echo '<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Metagenome-Assembled Genome</b></legend>';
    echo '<div class="gd-notice gd-warn">'
       . ($acc === ''
            ? 'No assembly accession was given.'
            : 'No MAG with assembly accession <code>' . safe($acc) . '</code> is in the current release.')
       . ' Browse the <a href="MAGs.php">MAGs catalog</a> to pick one.</div>';
else:
    /* 返回目录：带上 class / species，用户回到的是自己原来那一页，
       而不是每次都被扔回默认的 Acropora cytherea。 */
    $back = 'MAGs.php?class=' . urlencode($mag['class']) . '&amp;species=' . urlencode($mag['host']);

    echo '<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Metagenome-Assembled Genome</b></legend>';
    echo '<p class="mag-crumb"><a href="' . $back . '">&larr; Back to the MAGs catalog</a></p>';

    /* 标题：微生物名在前（这才是「这个基因组是什么」），宿主在后。 */
    echo '<div class="mag-title"><i>' . safe($mag['Species']) . '</i></div>';
    echo '<p class="mag-sub">Metagenome-assembled genome <b>' . safe($mag['AssemblyAccession']) . '</b>'
       . ' recovered from the cnidarian host ';
    if ($__hostRes) {
        echo '<a href="speciesinfo.php?species=' . urlencode($__hostRes['abbr1']) . '"><b><i>'
           . safe($mag['host']) . '</i></b></a>';
    } else {
        /* 宿主在本站没有物种页（30 个宿主里 16 个如此，占 242/315 个 MAG）——
           这一句只在真的没有链接时才出现，有链接的时候不该拿它当噪音。 */
        echo '<b><i>' . safe($mag['host']) . '</i></b>'
           . ' <span style="color:#64748b">(this host has no species page in this release, so it is not linked)</span>';
    }
    echo '.</p>';

    echo '<div class="gd-notice gd-info">This page describes one metagenome-assembled genome and, where available, the '
       . 'functional annotation of its proteins. The proteins are the ones <b>NCBI</b> already predicted and annotated for '
       . 'this assembly (PGAP or submitter annotation) &mdash; they are not re-predicted here. Annotation covers the '
       . 'InterPro, Gene Ontology and KEGG resources.</div>';

    /* ---------------- 组装信息 ---------------- */
    /* 两张表而不是一张十二列的表：本页沿用 gridtable 的等分列宽，
       列太多会把每个格子挤到读不出来（同 MAGs.php 目录表的列划分）。 */
    echo '<table><tr><td><br></td></tr></table>';
    echo '<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;Assembly Information</legend>';
    echo '<table class="gridtable mag-kv">'
       . "<tr align='center'><th>Class of host</th><th>Host</th><th width='22%'>Microbial species</th>"
       . "<th width='11%'>NCBI Taxonomy ID</th><th width='11%'>Assembly Accession</th><th>Assembly Level</th></tr>";
    echo "<tr align='center'>"
       . '<td><a href="browse.php?class=' . urlencode($mag['class']) . '">' . safe($mag['class']) . '</a></td>'
       . '<td><b><i>' . ($__hostRes
            ? '<a href="speciesinfo.php?species=' . urlencode($__hostRes['abbr1']) . '">' . safe($mag['host']) . '</a>'
            : safe($mag['host'])) . '</i></b></td>'
       . '<td><i>' . safe($mag['Species']) . '</i></td>'
       . '<td>' . ($mag['TaxonID'] !== '' && $mag['TaxonID'] !== '-'
            ? '<a href="https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=' . urlencode($mag['TaxonID']) . '" target="_blank">' . safe($mag['TaxonID']) . '</a>'
            : '&mdash;') . '</td>'
       . '<td><a href="https://www.ncbi.nlm.nih.gov/datasets/genome/' . urlencode($mag['AssemblyAccession']) . '" target="_blank">' . safe($mag['AssemblyAccession']) . '</a></td>'
       . '<td>' . safe($mag['AssemblyLevel']) . '</td>'
       . "</tr></table><div class=\"clr\"></div>";

    echo '<table><tr><td><br></td></tr></table>';
    echo '<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;Assembly Statistics and Quality</legend>';
    echo '<table class="gridtable mag-kv">'
       . "<tr align='center'><th>Assembled Size (bp)</th><th>Nr. contigs</th><th>Contig N50 (bp)</th>"
       . "<th>GC Percent (%)</th><th width='13%'>CheckM completeness (%)</th>"
       . "<th width='13%'>CheckM contamination (%)</th><th width='16%'>Reference</th></tr>";
    echo "<tr align='center'>"
       . '<td>' . safe($mag['AssembledSize']) . '</td>'
       . '<td>' . safe($mag['Nrcontigs']) . '</td>'
       . '<td>' . safe($mag['ContigN50']) . '</td>'
       . '<td>' . safe($mag['GCPercent']) . '</td>'
       . '<td>' . safe($mag['CheckMcompleteness']) . '</td>'
       . '<td>' . safe($mag['CheckMcontamination']) . '</td>'
       . '<td>' . (!empty($mag['link'])
            ? '<a href="' . safe($mag['link']) . '" target="_blank">' . (!empty($mag['pubmed']) ? 'PMID ' . safe($mag['pubmed']) : 'Link') . '</a>'
            : (!empty($mag['pubmed']) ? 'PMID ' . safe($mag['pubmed']) : '&mdash;')) . '</td>'
       . "</tr></table><div class=\"clr\"></div>";

    /* ---------------- 功能注释 ---------------- */
    echo '<table><tr><td><br></td></tr></table>';
    echo '<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;Functional Annotation of the Proteins</legend>';

    if (!$T['annot']):
        /* 表都还没建 —— 注释流水线一次都没跑过。措辞要说得像「还没做」，
           而不是「这个 MAG 没有注释」（后者会被读成数据本身的性质）。 */
        echo '<div class="gd-notice gd-warn">Functional annotation for the MAGs has <b>not been computed yet</b>. '
           . 'The annotation pipeline (InterProScan for InterPro, Pfam, PANTHER and GO; KofamScan for KEGG) has not been run on the '
           . 'MAG protein sets, so no annotation table exists in this release. The assembly and quality statistics above '
           . 'are complete.</div>';
    elseif ($annot_all === 0):
        /* 表在、但这个 MAG 没有蛋白行。这既可能是注释还没跑到它，也可能是
           NCBI 本身就没有给这个组装做注释 —— 两种都如实说，不猜是哪一种。 */
        echo '<div class="gd-notice gd-warn">No protein annotation is available for <code>' . safe($mag['AssemblyAccession']) . '</code> yet. '
           . 'Either this genome has not been through the annotation pipeline, or NCBI provides no annotated protein set '
           . 'for this assembly (the pipeline annotates NCBI\'s existing proteins rather than predicting genes). '
           . 'See the <a href="https://www.ncbi.nlm.nih.gov/datasets/genome/' . urlencode($mag['AssemblyAccession']) . '" target="_blank">NCBI record</a> for the assembly itself.</div>';
    else:
        /* 搜索框：GET 表单会替换整个查询串，acc / per_page 必须用 hidden 带过去，
           否则一按回车就不知道自己看的是哪个 MAG 了。page 故意不带 —— 换搜索词
           应该回到第 1 页。 */
        echo '<form class="search-box mag-search" method="get" action="' . $__self . '">';
        echo '<input type="hidden" name="acc" value="' . safe($acc) . '">';
        echo '<input type="hidden" name="per_page" value="' . intval($per_page) . '">';
        /* 表单替换整个查询串，排序参数也得用 hidden 带过去，否则搜一次就被清掉。 */
        if ($__sortQs !== '') {
            echo '<input type="hidden" name="sort" value="' . safe($__sort) . '">';
            echo '<input type="hidden" name="dir" value="' . safe($__dir) . '">';
        }
        echo '<span style="font-size:15px;color:#475569">Search:</span>';
        echo '<input type="text" name="q" value="' . safe($q) . '" '
           . 'placeholder="Protein ID, InterPro / GO / KEGG term, or description&hellip;">';
        echo '<button type="submit">Search</button>';
        if ($searchOn) {
            echo '<a class="mag-clear" href="' . $__self . '?acc=' . urlencode($acc) . '&amp;per_page=' . intval($per_page) . '">Clear</a>';
        }
        echo '</form>';

        echo '<p class="mag-count">';
        if ($searchOn) {
            echo '<b>' . number_format($annot_total) . '</b> of <b>' . number_format($annot_all) . '</b> proteins match '
               . '<b>' . safe($q) . '</b>.';
        } else {
            echo '<b>' . number_format($annot_all) . '</b> annotated proteins in this genome. One protein per row; '
               . 'a protein with no signature in a resource shows &mdash; in that column.';
        }
        echo '</p>';

        if ($annot_total === 0) {
            echo '<div class="gd-notice gd-info">No protein in <code>' . safe($mag['AssemblyAccession']) . '</code> matches '
               . '<b>' . safe($q) . '</b>. Try a shorter term, or clear the search to see all '
               . number_format($annot_all) . ' proteins.</div>';
        } else {

        /* 表头 + 每行一个蛋白。整张表按「零空白」拼接输出：早年 templatemo_style.css 的
           table.gridtable 带 white-space:pre-wrap，PHP 源码里的缩进和换行会变成
           单元格里真的换行。那条 pre-wrap 现在已经在共用样式里删掉了，.mag-annot
           自己仍写着 white-space:normal —— 两重保险，别把拼接改成换行缩进。 */
        echo '<div class="table-container"><table class="gridtable mag-annot">';
        /* 前两列带排序链接；后面三列是空的注释列（见文件上半部分的说明），不给箭头。 */
        echo '<tr><th width="12%">' . cnido_sort_link('protein', 'Protein', $__sort, $__dir, $__hdrQs) . '</th>'
           . '<th width="17%" class="tal">' . cnido_sort_link('desc', 'Description', $__sort, $__dir, $__hdrQs) . '</th>';
        foreach ($annot_cols as $__col) {
            echo '<th width="' . $__col['w'] . '" title="' . safe($__col['hint']) . '">' . $__col['title'] . '</th>';
        }
        echo '</tr>';

        foreach ($page_rows as $r) {
            $g = $r['protein'];

            /* 蛋白号列：看起来像 NCBI 蛋白号才给链接（WP_/MBO/KAA 之类），
               否则只显示 —— 拼一个打不开的链接比不链接更糟。 */
            $plink = preg_match('/^[A-Z]{2,4}_?[0-9]{5,}(\.[0-9]+)?$/', $g)
                   ? '<a href="https://www.ncbi.nlm.nih.gov/protein/' . urlencode($g) . '" target="_blank">' . safe($g) . '</a>'
                   : safe($g);
            $cell = '<td class="mag-gene">' . $plink;
            /* locus_tag 只有约 15% 的蛋白有（建表脚本里有实测数），没有就不印空行。 */
            if (!empty($r['locus_tag'])) {
                $cell .= '<span class="mag-prod">' . safe($r['locus_tag']) . '</span>';
            }
            $cell .= '</td><td class="mag-desc tal">' . safe($r['description']) . '</td>';

            /* InterPro */
            $cell .= '<td>';
            if (empty($sig['ipr'][$g])) { $cell .= '<span class="mag-none">&mdash;</span>'; }
            else {
                $p = array();
                foreach ($sig['ipr'][$g] as $s) {
                    $t = safe($s[1]);
                    $a = urlencode($s[1]);
                    $one = ($a !== '') ? '<a href="https://www.ebi.ac.uk/interpro/entry/InterPro/' . $a . '" target="_blank">' . $t . '</a>' : $t;
                    if ($s[2] !== '' && $s[2] !== '-') { $one .= ' <span class="mag-type">(' . safe($s[2]) . ')</span>'; }
                    if ($s[3] !== '' && $s[3] !== '-') { $one .= '<span class="mag-sub">' . safe($s[3]) . '</span>'; }
                    $p[] = $one;
                }
                $cell .= implode('<br>', $p);
            }
            $cell .= '</td>';

            /* Gene Ontology */
            $cell .= '<td>';
            if (empty($sig['go'][$g])) { $cell .= '<span class="mag-none">&mdash;</span>'; }
            else {
                $p = array();
                foreach ($sig['go'][$g] as $s) {
                    $t = safe($s[1]);
                    $a = urlencode($s[1]);
                    $one = ($a !== '') ? '<a href="http://amigo.geneontology.org/amigo/term/' . $a . '" target="_blank">' . $t . '</a>' : $t;
                    /* 命名空间只印缩写（MF / BP / CC），全称放进 tooltip；
                       库里真出现映射外的值就原样印，不猜。 */
                    if ($s[2] !== '' && $s[2] !== '-') {
                        $__cat = isset($GO_CAT_SHORT[$s[2]]) ? $GO_CAT_SHORT[$s[2]] : $s[2];
                        $one .= ' <span class="mag-type"'
                              . ($__cat !== $s[2] ? ' title="' . safe($s[2]) . '"' : '')
                              . '>(' . safe($__cat) . ')</span>';
                    }
                    if ($s[3] !== '' && $s[3] !== '-') { $one .= '<span class="mag-sub">' . safe($s[3]) . '</span>'; }
                    $p[] = $one;
                }
                $cell .= implode('<br>', $p);
            }
            $cell .= '</td>';

            /* KEGG：KO 走 kegg.jp/entry/（K00006 这种号 /entry/ 是正确的），
               pathway 那一列是 ko#####，交给 cnido_kegg_map_url() 分派入口。 */
            $cell .= '<td>';
            if (empty($sig['kegg'][$g])) { $cell .= '<span class="mag-none">&mdash;</span>'; }
            else {
                $p = array();
                foreach ($sig['kegg'][$g] as $s) {
                    $one = '';
                    if ($s[1] !== '' && $s[1] !== '-') {
                        $one .= '<a href="https://www.kegg.jp/entry/' . urlencode($s[1]) . '" target="_blank">' . safe($s[1]) . '</a>';
                    }
                    if ($s[2] !== '' && $s[2] !== '-') {
                        $one .= ($one !== '' ? ' ' : '') . '<span class="mag-type">' . safe($s[2]) . '</span>';
                    }
                    /* Pathway / Pathway_ID 这一列存的是 KO 的 KEGG BRITE C 节点
                       （少数是通路图 ko00010 这种），两种 ID 长得一样都是 ko#####，
                       但 KEGG 把它们放在不同入口下：/pathway/ 只对通路图有效，
                       /brite/ 只对 BRITE 节点有效。
                       2026-09-27 这里曾据状态码判定「kegg.jp/entry/<id> 两种全 200」
                       并据此改用 /entry/ —— 那是看漏了：BRITE 节点在 /entry/ 下回的是
                       一张 527 字节的 "No such data was found." 空壳，状态码仍是 200，
                       所以链接点开是空页而不是报错（正是「只看状态码会漏」的那种）。
                       现在与 gene_detail.php、kegg_result.php 共用 includes/state.php 的
                       cnido_kegg_map_url()，由它按名单分派入口。 */
                    $pw = '';
                    if ($s[5] !== '' && $s[5] !== '-') {
                        $pw = ($s[6] !== '' && $s[6] !== '-')
                            ? '<a href="' . htmlspecialchars(cnido_kegg_map_url($s[6]), ENT_QUOTES, 'UTF-8') . '" target="_blank">' . safe($s[5]) . '</a>'
                            : safe($s[5]);
                    }
                    if ($pw !== '') { $one .= '<span class="mag-sub">' . $pw . '</span>'; }
                    $p[] = $one;
                }
                $cell .= implode('<br>', $p);
            }
            $cell .= '</td>';

            /* Pfam —— 行是 (protein, Pfam_accession, Pfam_name, Description, Type,
               Source, URL)，与站内 <ABBR>_pfam 同序。链接口径照抄 gene_detail.php:498
               的 https://www.ebi.ac.uk/interpro/entry/pfam/。
               Pfam_name / Type 是 load_iprscan.php 拿 ref/pfam_name.tsv 补的（TSV
               第 6 列只有 description，没有短名也不带 Domain/Family/Repeat）。 */
            $cell .= '<td>';
            if (empty($sig['pfam'][$g])) { $cell .= '<span class="mag-none">&mdash;</span>'; }
            else {
                $p = array();
                foreach ($sig['pfam'][$g] as $s) {
                    $one = '';
                    if ($s[1] !== '' && $s[1] !== '-') {
                        $one .= '<a href="https://www.ebi.ac.uk/interpro/entry/pfam/' . urlencode($s[1]) . '" target="_blank">' . safe($s[1]) . '</a>';
                    }
                    /* 短名用正文字色（不套 mag-type）：mag-type 在这个页面里表示
                       「括号里的类型」，两个都套同名 class 就分不清哪个是名字。
                       顺序照 gene_detail.php:497 的表头口径（号、名、类型、描述）。 */
                    if ($s[2] !== '' && $s[2] !== '-') {
                        $one .= ($one !== '' ? ' ' : '') . safe($s[2]);
                    }
                    if ($s[4] !== '' && $s[4] !== '-') {
                        $one .= ' <span class="mag-type">(' . safe($s[4]) . ')</span>';
                    }
                    if ($s[3] !== '' && $s[3] !== '-') {
                        $one .= '<span class="mag-sub">' . safe($s[3]) . '</span>';
                    }
                    $p[] = $one;
                }
                $cell .= implode('<br>', $p);
            }
            $cell .= '</td>';

            /* PANTHER —— 行是 (protein, id, anno, method, url)，anno 是全大写的家族名。
               链接口径照抄 gene_detail.php:519 的 pantherdb family.do?clsAccession=。 */
            $cell .= '<td>';
            if (empty($sig['panther'][$g])) { $cell .= '<span class="mag-none">&mdash;</span>'; }
            else {
                $p = array();
                foreach ($sig['panther'][$g] as $s) {
                    $one = '';
                    if ($s[1] !== '' && $s[1] !== '-') {
                        $one .= '<a href="https://www.pantherdb.org/panther/family.do?clsAccession=' . urlencode($s[1]) . '" target="_blank">' . safe($s[1]) . '</a>';
                    }
                    if ($s[2] !== '' && $s[2] !== '-') {
                        $one .= '<span class="mag-sub">' . safe($s[2]) . '</span>';
                    }
                    $p[] = $one;
                }
                $cell .= implode('<br>', $p);
            }
            $cell .= '</td>';

            echo '<tr>' . $cell . '</tr>';
        }
        echo '</table></div>';

        /* ---------------- 分页 ---------------- */
        /* 与站内结果页同一套类名与算法（.pagination-container / .page-btn /
           .go-to-page），窗口 $page±3 + 首末页 + 省略号。链接全部用 $__baseQs
           拼，搜索词才不会翻一页就丢。 */
        if ($total_pages > 1) {
            $qs = $__baseQs . '&amp;per_page=' . intval($per_page) . '&amp;page=';
            echo '<div class="pagination-container"><div class="pagination-info">';
            echo '<div class="total-records">&#128202; Proteins: ' . number_format($annot_total) . '</div>';
            echo '<div class="per-page-selector"><span>Show</span><select onchange="window.location.href=\''
               . $__self . '?' . $__baseQs . '&amp;page=1&amp;per_page=\'+this.value">';
            foreach (array(10, 20, 50, 100) as $o) {
                echo '<option value="' . $o . '"' . ($o == $per_page ? ' selected' : '') . '>' . $o . '</option>';
            }
            echo '</select><span>proteins per page</span></div></div>';

            echo '<div class="pagination-nav">';
            echo '<a href="' . $__self . '?' . $qs . '1" class="page-btn' . ($page == 1 ? ' disabled' : '') . '">&laquo; First</a>';
            echo '<a href="' . $__self . '?' . $qs . max(1, $page - 1) . '" class="page-btn' . ($page == 1 ? ' disabled' : '') . '">&lsaquo; Previous</a>';

            $start_page = max(1, $page - 3);
            $end_page   = min($total_pages, $page + 3);
            if ($start_page > 1) {
                echo '<a href="' . $__self . '?' . $qs . '1" class="page-btn">1</a>';
                if ($start_page > 2) { echo '<span class="page-btn disabled">...</span>'; }
            }
            for ($i = $start_page; $i <= $end_page; $i++) {
                echo '<a href="' . $__self . '?' . $qs . $i . '" class="page-btn' . ($i == $page ? ' active' : '') . '">' . $i . '</a>';
            }
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) { echo '<span class="page-btn disabled">...</span>'; }
                echo '<a href="' . $__self . '?' . $qs . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
            }

            echo '<a href="' . $__self . '?' . $qs . min($total_pages, $page + 1) . '" class="page-btn' . ($page == $total_pages ? ' disabled' : '') . '">Next &rsaquo;</a>';
            echo '<a href="' . $__self . '?' . $qs . $total_pages . '" class="page-btn' . ($page == $total_pages ? ' disabled' : '') . '">Last &raquo;</a>';
            echo '</div>';

            echo '<div class="go-to-page"><span>Go to page:</span>'
               . '<input type="number" id="gotoPage" min="1" max="' . $total_pages . '" value="' . $page . '">'
               . '<button onclick="window.location.href=\'' . $__self . '?' . $qs . '\'+document.getElementById(\'gotoPage\').value">Go</button>'
               . '<span>of ' . $total_pages . ' pages</span></div>';
            echo '</div>';
        }
        }   /* $annot_total > 0 */
    endif;
endif;

$conn->close();

?>

</div>
</div>
</div>

<?php
    include "./Webpage_components.php";
    print $footer;
?>
</body>
</html>
