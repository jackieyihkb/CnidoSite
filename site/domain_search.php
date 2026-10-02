<?php
/* =====================================================================
 * Functional Domain Search —— 跨物种按「结构域」检索
 *
 * 审稿意见 Referee 3 点 10(e)：
 *   "The InterPro/Pfam/KEGG modules only accept gene IDs. A user cannot ask
 *    'which genes in the database contain domain X'. Please allow search by
 *    domain identifier or description."
 *
 * 实现：145 个已注释物种的注释表通过 UNION ALL 组成一张逻辑表，因此
 * 一次查询即可覆盖全部物种；同时支持编号（IPR016024 / PF12848 / K13752 /
 * GO:0005615 / PTHR19143）与自由文本（kinase、Armadillo）两种输入。
 * ================================================================== */
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { error_log('CnidoSite: database connection failed'); die('The database is temporarily unavailable. Please try again in a moment.'); }
require_once __DIR__ . '/includes/domain_search.php';
require_once __DIR__ . '/includes/state.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Functional Domain Search - CnidoSite</title>
<meta name="description" content="Search every annotated cnidarian genome by protein domain or free-text term &mdash; InterPro, Pfam, KEGG, GO and PANTHER accessions" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<style>
.ds-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 16px}
.ds-tabs a{padding:8px 18px;border-radius:22px;text-decoration:none;font-size:15px;font-weight:600;
  border:1px solid #e2e8f0;background:#fff;color:#475569}
.ds-tabs a.on{background:#2563eb;border-color:#2563eb;color:#fff}
.ds-form{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;margin-bottom:18px}
.ds-form input[type=text]{width:100%;max-width:520px;padding:10px 12px;border:1px solid #cbd5e1;
  border-radius:8px;font-size:15px;font-family:Consolas,Monaco,monospace}
.ds-form select{padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:15px;background:#fff}
.ds-form button{padding:10px 26px;border:none;border-radius:8px;background:#2563eb;color:#fff;
  font-size:15px;font-weight:600;cursor:pointer}
.ds-form button:hover{background:#1d4ed8}
.ds-ex{font-size:16px;color:#64748b;margin-top:10px}
.ds-ex code{background:#f1f5f9;color:#475569;border-radius:4px;padding:1px 6px;cursor:pointer}
</style>
</head>
<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<?php /*导航栏保持不变*/ ?>
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
            <li><a href="#" class="current">Genome</a>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Functional Domain Search</b></legend>
<p class="paleo-intro">
    Search every annotated genome in CnidoSite by <b>protein domain</b>, not just by gene ID.
    Enter an accession (<code>IPR016024</code>, <code>PF12848</code>, <code>K13752</code>,
    <code>GO:0005615</code>, <code>PTHR19143</code>) or a free-text term (<i>kinase</i>,
    <i>Armadillo</i>, <i>homeobox</i>). One query covers
    <b><?php echo count(ds_species_list($conn, 'ipr')); ?></b> annotated genomes.
</p>

<?php
/* 检索配置统一放在 includes/domain_search.php：词表生成脚本 ds_vocab_refresh.php
   用的是同一份定义，列名只有一处，不会两边对不上。这里补上结果表要用的列名。 */
$DBMAP = ds_dbmaps();
foreach ($DBMAP as $k => $v) {
    $c = ds_search_columns($k);
    $DBMAP[$k]['term'] = $c['term'];
    $DBMAP[$k]['desc'] = $c['desc'];
}

$db = isset($_GET['db']) && isset($DBMAP[$_GET['db']]) ? $_GET['db'] : 'ipr';
$q  = isset($_GET['q']) ? trim($_GET['q']) : '';
$sp = isset($_GET['sp']) && ds_valid_abbr($_GET['sp']) ? $_GET['sp'] : '';
$meta = $DBMAP[$db];

/* 物种一律显示拉丁学名，不把 <短码>_<db> 的表前缀当内容印给读者
   （审稿意见：物种缩写名应更正为拉丁学名）。
   option 的 value 仍然是短码：本页的检索靠它拼表名（UNION ALL 那一段），
   站内几十个模块的表名拼装和深链也建立在短码上，改 value 会一起断，
   所以这里只做显示层映射 —— includes/state.php 的 cnido_latin_map() 就是
   为这件事写的，kegg.php / interpro.php 等页面已在用。 */
$spList = ds_species_list($conn, $db);
$spName = cnido_latin_map($spList, $conn);
$nameOf = function ($ab) use ($spName) { return isset($spName[$ab]) ? $spName[$ab] : $ab; };
/* 下拉框改按学名字母序：$spList 原本是按短码排的，显示学名后那个顺序没意义。 */
usort($spList, function ($a, $b) use ($nameOf) { return strcasecmp($nameOf($a), $nameOf($b)); });
?>

<div class="ds-tabs">
    <?php foreach ($DBMAP as $k => $v) {
        $on = ($k === $db) ? ' class="on"' : '';
        echo '<a href="?db=' . urlencode($k) . ($q !== '' ? '&q=' . urlencode($q) : '') . '"' . $on . '>' . htmlspecialchars($v['label']) . '</a>';
    } ?>
</div>

<form class="ds-form" method="get" action="domain_search.php">
    <input type="hidden" name="db" value="<?php echo htmlspecialchars($db); ?>">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
               placeholder="<?php echo htmlspecialchars($meta['hint']); ?>">
        <select name="sp">
            <option value="">All species</option>
            <?php foreach ($spList as $ab) {
                echo '<option value="' . htmlspecialchars($ab) . '"' . ($ab === $sp ? ' selected' : '') . '>'
                   . htmlspecialchars($nameOf($ab)) . '</option>';
            } ?>
        </select>
        <button type="submit">Search</button>
    </div>
    <?php /* 例子原来只往输入框里填 q，不切 db —— 而编号属于哪一张表是固定的
             （IPR… 只在 InterPro 表、PF… 只在 Pfam 表、K… 只在 KEGG 表），
             所以在任一标签页上，四个例子里有三个点了必然零命中。改成直接带着
             db 跳转，点哪个都能出结果。 */ ?>
    <div class="ds-ex">
        Examples:
        <code onclick="location.href='?db=ipr&amp;q=IPR016024'">IPR016024</code>
        <code onclick="location.href='?db=pfam&amp;q=PF12848'">PF12848</code>
        <code onclick="location.href='?db=KEGG&amp;q=K13752'">K13752</code>
        <code onclick="location.href='?db=ipr&amp;q=homeobox'">homeobox</code>
        &nbsp;&middot;&nbsp; matching is exact on the identifier and partial on the description.
    </div>
</form>

<?php
if ($q === '') {
    /* 「every gene」要加上上限：ds_search_two_pass() 一次最多返回 500 行，
       结果页在本轮被截断时也会提示（见下面 $trunc 那句），空状态这句不能先
       把话说满。 */
    echo "<div class='gd-notice gd-info'>Enter a domain accession or description above. "
       . "Results list the genes carrying that signature &mdash; up to the first 500 rows, "
       . "with links to the gene page and to the genome browser.</div>";
} else {
    /* 两趟检索：先只按编号精确匹配（148 张表都走 ix_term 索引，是索引查找不是
       全表扫），没命中再退到包含匹配 —— 包含匹配先问词表 ds_vocab 把候选表缩到
       真的出现过该词的那几张，词表里一个都没有就直接返回空。
       老写法把 `term = 'q' OR desc LIKE '%q%'` 写在一个 OR 里，只要 OR 里有
       LIKE，term 上的索引就用不上，148 张表 3359 万行照扫：查一个不存在的词
       实测 20.1 秒，查 PF12848 5.1 秒。见 includes/domain_search.php 顶部说明。 */
    $t0 = microtime(true);
    list($rows, $nsp, $trunc) = ds_search_two_pass($conn, $db, $q, 500);
    $ms = round((microtime(true) - $t0) * 1000);

    echo "<p class='paleo-intro'>";
    if (empty($rows)) {
        echo "<b>No hit.</b> Nothing in the " . htmlspecialchars($meta['label'])
           . " annotation of the " . $nsp . " searched genomes matches <code>" . htmlspecialchars($q) . "</code>. "
           . "Try a shorter description term, or check the accession format "
           . "(" . htmlspecialchars($meta['hint']) . ")."
           . " &middot; " . $ms . " ms.";
    } else {
        /* $nsp 是**一共检索了多少张表**（该库里有的物种数，未加物种筛选时是全部），
           不是命中的物种数。原来写 "across 148 genomes"，读起来像 148 个基因组都
           贡献了结果。行里的第一列就是 abbr，就地数一下真正命中的物种数；
           结果被 500 行截断时只能说「至少」。 */
        $__hitSp = array();
        foreach ($rows as $__r) { if (isset($__r[0]) && $__r[0] !== '') { $__hitSp[$__r[0]] = true; } }
        echo "Found <b>" . count($rows) . ($trunc ? '+' : '') . "</b> gene&ndash;annotation pairs matching <code>"
           . htmlspecialchars($q) . "</code> in <b>" . ($trunc ? 'at least ' : '') . count($__hitSp)
           . "</b> of the <b>" . $nsp . "</b> searched genomes"
           . ($sp !== '' ? " (restricted to <b><i>" . htmlspecialchars($nameOf($sp)) . "</i></b>)" : "")
           . " &middot; " . $ms . " ms.";
        if ($trunc) { echo " <span style='color:#b45309'>Only the first 500 rows are shown &mdash; refine the query or pick a single species.</span>"; }
    }
    echo "</p>";

    if (!empty($rows)) {
        // 统计每个物种命中多少条，做成可点击的过滤条
        $per = array();
        foreach ($rows as $r) { $per[$r[0]] = (isset($per[$r[0]]) ? $per[$r[0]] : 0) + 1; }
        arsort($per);
        echo "<div style='margin:0 0 14px;line-height:2.1'>";
        echo "<span style='font-weight:600;color:#475569;margin-right:8px'>Species with hits:</span>";
        if ($sp !== '') { echo "<a href='?" . htmlspecialchars(http_build_query(array('db' => $db, 'q' => $q))) . "' style='margin-right:8px'>&larr; all species</a>"; }
        foreach ($per as $ab => $n) {
            echo "<a href='?" . htmlspecialchars(http_build_query(array('db' => $db, 'q' => $q, 'sp' => $ab)))
               . "' style='display:inline-block;margin:2px 5px 2px 0;padding:3px 12px;border-radius:14px;"
               . "background:" . ($ab === $sp ? '#2563eb' : '#eef2f7') . ";color:" . ($ab === $sp ? '#fff' : '#334155')
               . ";text-decoration:none;font-size:15px'>" . htmlspecialchars($nameOf($ab)) . " <b>" . $n . "</b></a>";
        }
        echo "</div>";

        /* Species 列留 16%：列里现在是完整学名（最长 35 字符，如
           "Paraphelliactis xishaensis sp. nov."），原来的 10% 只够放短码。
           省下的宽度从 term / Source / Browser 三列和自适应的 Match 列里出。 */
        /* 紧右边那一列装的是该库里参与文本匹配的自由文本字段：InterPro / GO 是
           Description、Pfam 是 Pfam_name、KEGG 是 Enzymes、PANTHER 是 anno。列头
           只写「Match」读者会当它是编号列（编号在左边那列），所以按库印字段本名。
           KEGG 的 Enzymes 存的是 KO 定义（「histone H2B」这样），不是酶分类，
           所以印「KO definition」而不是「Enzyme」—— 与 gene_detail.php /
           kegg_result.php 的表头一致。 */
        $__mlMap = array('Description' => 'Description', 'Pfam_name' => 'Pfam name',
                         'Enzymes'     => 'KO definition', 'anno'     => 'Annotation');
        $ml = isset($__mlMap[$meta['desc']]) ? $__mlMap[$meta['desc']] : 'Match';
        echo "<table class='gridtable'><tr><th width='16%'>Species</th><th width='14%'>Gene ID</th>"
           . "<th width='15%'>" . htmlspecialchars($meta['label']) . " term</th>"
           . "<th>" . htmlspecialchars($ml) . "</th><th width='9%'>Source</th><th width='8%'>Browser</th></tr>";

        /* 各物种注释表的列名基本一致，但 panther 用的是 id/anno/method，
         * 所以按 (abbr, db) 取一次列名并缓存，避免逐行查 information_schema。 */
        $nameCache = array();
        foreach ($rows as $r) {
            $abbr = $r[0];
            $cols = array_slice($r, 1);            // 去掉 UNION 加进去的 abbr 列
            $gene = $cols[0];
            if (!isset($nameCache[$abbr])) {
                $names = array();
                $qn = mysqli_query($conn, "SELECT * FROM `" . $abbr . '_' . $db . "` LIMIT 0");
                if ($qn) {
                    for ($i = 0; $i < mysqli_num_fields($qn); $i++) {
                        $f = mysqli_fetch_field_direct($qn, $i);
                        $names[$f->name] = $i;
                    }
                }
                $nameCache[$abbr] = $names;
            }
            $names = $nameCache[$abbr];
            $tv = isset($names[$meta['term']])   ? $cols[$names[$meta['term']]]   : '';
            $dv = isset($names[$meta['desc']])   ? $cols[$names[$meta['desc']]]   : '';
            $sv = isset($names[$meta['source']]) ? $cols[$names[$meta['source']]] : '';
            echo "<tr align='center'>";
            echo "<td><a href='species_portal.php?species=" . urlencode($abbr) . "'>"
               . "<i>" . htmlspecialchars($nameOf($abbr)) . "</i></a></td>";
            echo "<td><a href='gene_detail.php?gene=" . urlencode($gene) . "&amp;species=" . urlencode($abbr)
               . "'>" . htmlspecialchars($gene) . "</a></td>";
            echo "<td><a href='" . htmlspecialchars($meta['url'] . urlencode($tv)) . "' target='_blank'>"
               . htmlspecialchars($tv) . "</a></td>";
            echo "<td style='text-align:left'>" . htmlspecialchars($dv) . "</td>";
            echo "<td>" . htmlspecialchars($sv) . "</td>";
            echo "<td><a href='./jbrowse/index.html?data=" . urlencode($abbr) . "&amp;gene=" . urlencode($gene)
               . "'>JBrowse</a></td>";
            echo "</tr>";
        }
        echo "</table><br>";
    }
}
?>
</div>
</div>
</div>

<?php
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
