<?php
/* cnido_latin() 等辅助函数定义在 includes/state.php 里。本页要显示物种拉丁学名
 * （缩写码只是数据表前缀，不对外呈现），因此必须引入该文件 —— 否则第 288 行
 * 调用 cnido_latin() 会抛 "Call to undefined function"，页面在 NR 注释那一行
 * 之后被静默截断（网页 SAPI 不显示错误），表现为「Gene ID 点进去是坏页」。 */
require_once __DIR__ . '/includes/state.php';

$species = isset($_GET['species']) ? $_GET['species'] : (isset($_POST['species']) ? $_POST['species'] : '');
$gene    = isset($_GET['gene'])    ? $_GET['gene']    : (isset($_POST['gene'])    ? $_POST['gene']    : '');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Gene Detail - CnidoSite</title>
<meta name="description" content="Annotation, RNA-seq expression, proteomics and co-expression data for a single cnidarian gene" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery-1.10.2.min.js"></script>
<script src="js/highcharts.js"></script>
<script src="js/highcharts-more.js"></script>
<script src="js/exporting.js"></script>

<script src="js/sign.js" language="javascript"></script>
<script src="js/tooltip.js" type="text/javascript"></script>
<script src="js/highcharts-detail.js"></script>
<script src="js/modernizr.main.js"></script>
</head>
<body>
<style>
    .iframeBox{
        border: 1px solid #888;
        position: relative;
        height: 300px;
        width: 98%;
        overflow: auto;
        margin: 0 10px;
    }
    iframe{
        position: absolute;
        left: 0;
        /*margin:-307px 0 0 -5px*/

    }
    <?php /* ---- Sequence 区块 ----------------------------------------------------
       折叠块默认隐藏，由 gd_seq_toggle() 翻开。原来这里的 <textarea> 写死
       width:1550px —— 屏幕窄于 1600px 时它比正文宽，整页出现横向滚动条；
       改成 width:100% + box-sizing，随卡片宽度走。
       写在本页 <style> 里而不是 templatemo_style.css：外部样式表只有
       Last-Modified、没有 Cache-Control，改了用户可能按启发式缓存几小时看不到。 */ ?>
    .gd-seqpane{ display: none; margin: 0 0 12px; }
    .gd-seqhead{
        font-size: 13px; color: #475569; margin: 8px 0 5px;
        padding: 5px 9px; background: #f8fafc;
        border: 1px solid #e2e8f0; border-bottom: 0;
        border-radius: 8px 8px 0 0;
    }
    .gd-seqcopy{ float: right; }
    .gd-seqtext{
        display: block; width: 100%; box-sizing: border-box;
        height: 150px; margin: 0; padding: 8px 10px;
        font-family: ui-monospace, Menlo, Consolas, monospace;
        font-size: 13px; line-height: 1.65; color: #1e293b;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 0 0 8px 8px;
        resize: vertical; overflow: auto;
        <?php /* 序列是一整行、没有空白字符：用 pre 的话这一行宽 48 万像素，只能靠横向
           滚动条看。pre-wrap + break-word 让它软换行铺满框宽；软换行不写入
           内容，Ctrl+A 复制拿到的仍是原始那一行，不影响下游使用。 */ ?>
        white-space: pre-wrap; overflow-wrap: break-word;
    }
    .gd-seqtext:focus{ outline: 2px solid #bfdbfe; outline-offset: -1px; }
    .gd-hint{
        font-size: 13px; color: #64748b; margin: 0 0 4px; line-height: 1.7;
    }
    <?php /* ---- iframe 下面那一行「More details in JBrowse」------------------------
       它是一个光秃秃的 <legend>，直接吃 templatemo_style.css 的
       legend{font:28px Arial, Helvetica, sans-serif} —— 于是和「Gene Structure」
       「Sequence」这些区块标题一样大、还是同一套 Arial，排在 iframe 底下看着就是
       又一个标题，而不是一个链接。降回与 .gd-notice、表格一致的 15px，字体跟着
       页面走（那条 legend 规则顺带把字体也换成了 Arial）。
       选择器必须带 legend：外部样式表那条是元素选择器，legend.gd-jb (0,1,1) 稳压
       它，写裸 .gd-jb 只是恰好也能赢（类 0,1,0 > 元素 0,0,1），别依赖这个。 */ ?>
    legend.gd-jb{
        font-family: inherit;
        font-size: 15px;
        line-height: 1.7;
        padding: 4px 12px;
    }
</style>
<script type="text/javascript">
function option_showhide(id,img){
	var thisImg = document.getElementById(img);
	if(document.getElementById){
		<?php /* 页面上没有 id=img 的图标时 thisImg 为 null，原来直接 .src= 会抛
		   TypeError（控制台报错，展开/收起虽然仍然生效但看着像坏了）。加个判空。 */ ?>
		if((document.getElementById(id).style.display == "block")){
			document.getElementById(id).style.display = 'none';
			if(thisImg){ thisImg.src="images/plus.png"; }
		}else{
			document.getElementById(id).style.display = 'block';
			if(thisImg){ thisImg.src="images/minus.png"; }
		}
	}else{
		if(document.layers){
			document.id.display = 
				(document.id.display == "block") ? 'none' : 'block';
		}else{
			document.all.id.style.display =
				(document.all.id.style.display == "block") ? 'none' : 'block';
		}
	}
}
function showhidediv(id){
  var sbtitle=document.getElementById(id);
  if(sbtitle){
     if(sbtitle.style.display=='block'){
     sbtitle.style.display='none';
     }else{
    sbtitle.style.display='block';
     }
  }
}
<?php /* Sequence 区块的展开 / 收起。折叠块默认 display:none（写在页面样式里），
   这里只翻转它并把链接文字跟着换掉 —— 原来只翻 display、链接永远写 "show"，
   展开后再看那个链接还是 "show"，用户以为没生效。 */ ?>
function gd_seq_toggle(i){
  var pane = document.getElementById('seq' + i);
  if(!pane){ return true; }            /* 没有折叠块就交给 href 自己跳 */
  var link = document.getElementById('seqt' + i);
  var open = (pane.style.display === 'block');
  pane.style.display = open ? 'none' : 'block';
  <?php /* 用实体而不是字面量箭头：整个文件是 UTF-8，但站内其余地方一律写 &darr; 这类
     实体，跟着写省得以后再纠结编码。innerHTML 会解析实体。 */ ?>
  if(link){ link.innerHTML = open ? 'show &darr;' : 'hide &uarr;'; }
  return false;
}
</script>
<script type="text/javascript">
    $(function(){
        $('#export').click(function(){
            var excelContent = $('#tablelist').html(); //获取表格内容
            $('input[name=excelContent]').val(excelContent);//赋值给表单
            $('#excelfromtable').submit();//表单提交，提交到php
        })
    })
</script>
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

<?php
/* =====================================================================
 * 审稿意见 Referee 3 点 10（a / b / d）与 Referee 2 major 1：
 *  - (b) 只给 gene ID、不给 species 时页面输出一堆无意义的 "-" 与 ":..."。
 *  - (a) 有物种但该物种没有 <ABBR>_seq / <ABBR>_locus 表时，序列框整片空白。
 *  - (d) 基因页与「基因家族(orthogroup)」之间没有任何交叉链接。
 * 下面统一解决：先解析物种 -> 再校验数据表是否存在 -> 每个区块按需给出
 * 明确的「数据不可用」说明，而不是静默输出空值。
 * ================================================================== */
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { error_log('CnidoSite: database connection failed'); die('The database is temporarily unavailable. Please try again in a moment.'); }

function gd_esc($conn, $v) { return mysqli_real_escape_string($conn, (string)$v); }
function gd_table_exists($conn, $t) {
    $t = gd_esc($conn, $t);
    $r = mysqli_query($conn, "SELECT 1 FROM information_schema.tables
                              WHERE table_schema = DATABASE() AND table_name = '$t' LIMIT 1");
    return ($r && mysqli_num_rows($r) > 0);
}
function gd_q1($conn, $sql) {           // 单行查询；表不存在/出错返回 null
    $r = mysqli_query($conn, $sql);
    if (!$r) { return null; }
    $row = mysqli_fetch_row($r);
    return $row ? $row : null;
}

$gene    = isset($gene) ? trim($gene) : '';
$species = isset($species) ? trim($species) : '';

/* --- 1. 解析物种：接受拉丁全名 / abbr / abbr1 三种写法 ------------------ */
$result2 = null;
if ($species !== '') {
    $s = gd_esc($conn, $species);
    $result2 = gd_q1($conn, "SELECT * FROM abbr WHERE species = '$s' OR abbr = '$s' OR abbr1 = '$s' LIMIT 1");
}

/* --- 2. 物种缺失时，用基因 ID 反查 ---------------------------------------
   主路径是拿候选写法去每张 <ABBR>_locus 表里探一次（cnido_species_by_gene），
   因为只有它给得出**完整**答案。原来只有 genefamily → busco 一条路，两个问题：
     (a) 覆盖太窄 —— busco 每个物种只存几千条命中基因（TSTEP 是 4,240 / 52,425），
         genefamily 只含参与直系同源分组的物种，于是绝大多数基因号在没有 species
         参数时一律「Gene not found」。BLAST 结果页给的链接恰恰不带 species。
     (b) 会挑错物种 —— 它取 `LIMIT 1`，而基因号跨物种撞车是真实的：
         FUN_000001-T1 同时存在于 Siderastrea siderea、Acropora pulchra 等 4 个物种，
         挑中谁完全是表扫描顺序决定的。挑错就是把别的物种的坐标、注释和表达量
         当成这个基因展示出去。
   genefamily/busco 降级为「_locus 里一个都没有时」的补充路径
   （有些物种只有 genefamily/busco 记录、没有 _locus 表）。 ---------------- */
$resolved_by = '';
if (!$result2 && $gene !== '') {
    $cands = cnido_species_by_gene($conn, $gene, 13);

    if (count($cands) > 1) {
        /* 撞名：必须让用户自己选，不能替他挑一个。 */
        $more = (count($cands) > 12);
        if ($more) { $cands = array_slice($cands, 0, 12); }
        echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Choose a species</legend>";
        echo "<div class='gd-notice gd-info'><b>Gene identifier <code>" . htmlspecialchars($gene)
           . "</code> occurs in " . ($more ? 'more than 12' : count($cands)) . " species.</b><br>"
           . "The same identifier is used by more than one deposited genome, and the records differ. "
           . "Pick the species you meant &mdash; the gene page, the JBrowse view and the expression data "
           . "all depend on it.</div>";
        echo "<ul class='gd-species-pick'>";
        foreach ($cands as $c) {
            $link = 'gene_detail.php?gene=' . urlencode($gene) . '&species=' . urlencode($c);
            echo "<li><a href=\"" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "\">"
               . htmlspecialchars(cnido_latin($c, $conn), ENT_QUOTES, 'UTF-8')
               . " <span class='paleo-code'>(" . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . ")</span></a></li>";
        }
        echo "</ul>";
        echo "<div class='gd-notice gd-info'>If you reached this page from a search result, "
           . "that result's own species link goes straight to the right page.</div>";
        /* 两个提前退出的分支（这里和下面「Gene not found」那处）原来只印 $footer 就
           </body>，没有关掉页头打开的三层内容容器（#column / #templatemo_content /
           #tempatemo_content_wrapper）。浏览器只好自己在 </body> 处闭合，页脚于是被
           嵌进 #column 里 —— 跟着正文栏的宽度和位置走，跟其它页面长得不一样。
           正常路径在文件末尾关掉了这三层。 */
        echo "</div>\n</div>\n</div>\n";
        include "Webpage_components.php";
        print $footer;
        echo "</body></html>";
        exit;
    }

    if (count($cands) === 1) {
        $a = gd_esc($conn, $cands[0]);
        $result2 = gd_q1($conn, "SELECT * FROM abbr WHERE abbr1 = '$a' OR abbr = '$a' LIMIT 1");
        if ($result2) { $resolved_by = 'locus'; }
    }

    if (!$result2) {
        /* _locus 里没有，但可能只在 genefamily/busco 里有记录。 */
        $g = cnido_gene_in($conn, $gene);
        $r = gd_q1($conn, "SELECT abbr FROM genefamily WHERE gene IN ($g) LIMIT 1");
        if (!$r) { $r = gd_q1($conn, "SELECT abbr FROM busco WHERE gene IN ($g) LIMIT 1"); }
        if ($r) {
            $a = gd_esc($conn, $r[0]);
            $result2 = gd_q1($conn, "SELECT * FROM abbr WHERE abbr1 = '$a' OR abbr = '$a' LIMIT 1");
            if ($result2) { $resolved_by = 'genefamily/busco'; }
        }
    }
}

/* --- 3. 仍然解析不出来：给出可操作的提示并停止，不再输出占位符 --------- */
if (!$result2) {
    echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Gene not found</legend>";
    echo "<div class='gd-notice gd-warn'>";
    /* 不带 ?gene= 直接访问本页时，原来的整句会渲染成 "No record for gene ." —— 句号
       前面空着。缺参数是「没给标识符」，不是「这个标识符查不到」，两种说法要分开。 */
    if ($gene === '') {
        echo "<b>No gene identifier was given.</b><br>";
    } else {
        echo "<b>No record for gene <code>" . htmlspecialchars($gene) . "</code>";
        if ($species !== '') { echo " in <i>" . htmlspecialchars($species) . "</i>"; }
        echo ".</b><br>";
    }
    $__n = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM abbr"));
    echo "CnidoSite holds records for " . (int)$__n[0] . " species, "
       . "but this identifier could not be matched to any of them. Please check the gene ID, or ";
    echo "<a href='/search.php'>search by species</a> to obtain a valid identifier. ";
    echo "Note that about half of the deposited assemblies are <b>assembly-only</b> and have no gene models &mdash; ";
    echo "<a href='/genomeinfo.php?filter=annotated'>see the annotated species list</a>.";
    echo "</div>";
    echo "</div>\n</div>\n</div>\n";   /* 关掉 #column / #templatemo_content / #tempatemo_content_wrapper，见上面那处的说明 */
    include "Webpage_components.php";
    print $footer;
    echo "</body></html>";
    exit;
}

$ABBR    = $result2[2];                       // 例如 AALAT
$species = $result2[0];                       // 规范化为拉丁全名
$spLatin = htmlspecialchars($species);
$spGene  = htmlspecialchars($gene);

echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Detailed information on " . $spGene . " in <b><i>" . $spLatin . "</i></b></legend>";
if ($resolved_by !== '') {
    echo "<div class='gd-notice gd-info'>Species inferred automatically from the gene identifier ("
       . htmlspecialchars($ABBR) . "). <a href='species_portal.php?species=" . urlencode($ABBR)
       . "'>Open the species portal &rarr;</a></div>";
}

/* --- 4. 逐表探测：哪些注释对当前物种确实存在 --------------------------- */
$T = array();
foreach (array('nr','locus','seq','uniprot','pfam','ipr','panther','go','KEGG') as $k) {
    $T[$k] = gd_table_exists($conn, $ABBR . '_' . $k);
}
$geneEsc = gd_esc($conn, $gene);
/* 同一个基因的两种写法（带 / 不带 `.1` 版本后缀）都必须能检出 —— 注释表用带后缀的
   mRNA 级 ID，_locus 用不带后缀的形式，147 个有注释的物种里 53 个两处不一致。
   下面所有按基因号取数的查询一律走 $geneIn，不再用 = 比较。
   判定与生成见 includes/state.php 的 cnido_gene_id_forms()。 */
$geneIn  = cnido_gene_in($conn, $gene);

$nrRow = $T['nr']      ? gd_q1($conn, "SELECT * FROM {$ABBR}_nr WHERE gene IN ($geneIn) LIMIT 1") : null;
$locRow= $T['locus']   ? gd_q1($conn, "SELECT * FROM {$ABBR}_locus WHERE mRNA IN ($geneIn) OR gene IN ($geneIn) LIMIT 1") : null;

$locTxt = ($locRow && isset($locRow[2]) && $locRow[2] !== '' && $locRow[2] !== null)
        ? htmlspecialchars($locRow[2] . ':' . $locRow[3] . '...' . $locRow[4])
        : '<span class="gd-na">not available for this species</span>';
$nrTxt  = ($nrRow && isset($nrRow[1]) && trim($nrRow[1]) !== '')
        ? '<a href="https://www.ncbi.nlm.nih.gov/protein/' . urlencode($nrRow[1]) . '" target="_blank">'
          . htmlspecialchars($nrRow[1]) . '</a>, ' . htmlspecialchars($nrRow[2])
        : '<span class="gd-na">no NCBI-NR hit recorded</span>';
echo "<p class=\"paleo-intro\">Genomic Location: <b>$locTxt</b><br>NR annotation: <b>$nrTxt</b>";
/* 物种一律显示拉丁学名 —— $ABBR 只是数据表前缀，不对外呈现。 */
echo "<br><span style='font-size:15px;color:#64748b'>Species <b><i>"
   . htmlspecialchars(cnido_latin($ABBR, $conn)) . "</i></b>"
   . " &middot; <a href='species_portal.php?species=" . urlencode($ABBR) . "'>all data for this species</a>"
   . " &middot; <a href='genefamily.php'>gene families</a></span></p>";
?>
<?php
/* -------- Gene structure (JBrowse) --------------------------------------
 * 没有 <ABBR>_locus 时 loc 是空的，之前会把 "data=ABBR&loc=:..." 这种畸形参数
 * 塞进 iframe，JBrowse 只能显示空白。这里显式降级为「该物种无定位信息」。 */
$hasJB = ($T['locus'] && $locRow && isset($locRow[2]) && $locRow[2] !== '' && $locRow[2] !== null);
if ($hasJB) {
    $jloc = htmlspecialchars($locRow[2] . ':' . $locRow[3] . '...' . $locRow[4]);
    echo "<br><legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Gene Structure</legend>";
    echo "<div class=\"iframeBox\"><iframe id=\"gbrowse\" width=\"100%\" height=\"800px\" src=\"./jbrowse/index.html?data="
       . htmlspecialchars($ABBR) . "&gene=" . urlencode($gene) . "&loc=" . urlencode($jloc) . "\"></iframe></div>";
    /* 站内页面（jbrowse 也是一个页面），按口径留当前窗口。 */
    echo "<legend class='gd-jb' align='center'><a href=\"./jbrowse/index.html?data=" . htmlspecialchars($ABBR)
       . "&gene=" . urlencode($gene) . "&loc=" . urlencode($jloc) . "\">More details in JBrowse</a></legend>";
}
?>
<!-------------------------------Sequences----------------------------------------------------------------->
<?php
echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Sequence</legend>";

$sequence = null;
if ($T['seq']) {
    /* 原来写的是 `gene = 'x' || transcript = 'x' || protein = 'x'`，靠 MySQL 把 `||`
       当逻辑 OR 才碰巧能跑；sql_mode 一旦含 PIPES_AS_CONCAT 就变成字符串拼接而静默
       查不到。这里改成显式 OR，并同时接受两种后缀写法。 */
    $sequence = gd_q1($conn, "SELECT * from {$ABBR}_seq WHERE gene IN ($geneIn) OR transcript IN ($geneIn) OR protein IN ($geneIn)");
}

if (!$T['seq']) {
    /* 145/325 个物种没有 <ABBR>_seq 表：原代码会渲染出三个空文本框 */
    echo "<div class='gd-notice gd-warn'><b>Sequence data are not available for <i>" . $spLatin . "</i>.</b><br>"
       . "Nucleotide and protein sequences are provided for the species whose genome annotation is complete "
       . "(see <a href='genomeinfo.php?filter=annotated'>annotated genomes</a>); for this species only the "
       . "assembly is archived. The functional annotation below is likewise unavailable.</div>";
} elseif (!$sequence) {
    echo "<div class='gd-notice gd-info'>No sequence record for <code>" . $spGene . "</code> in "
       . htmlspecialchars($ABBR) . " (the gene ID may belong to a different isoform naming scheme). "
       . "Try the <a href='search.php'>gene search</a>.</div>";
} else {
    /* 三行序列。列序见 <ABBR>_seq 的真实结构：
       gene(0) cds_seq(1) transcript(2) transcript_seq(3) protein(4) protein_seq(5)。
       ① 原实现把一个 <tbody> 塞进了 <td> 里（`<tr><td>链接<tbody>…</tbody></td></tr>`）——
       HTML 解析器会在 <td> 里遇到 <tbody> 时把外层提前闭合，于是三行变成三个裸链接、
       连表头都没有，序列框在浏览器里根本不在表里。② 折叠区原来写的是
       `display:none` 的 <tbody>，就算结构合法也画不出边框。这里改成「表 + 表下三个
       独立折叠块」，结构合法、无 JS 时退化成可滚动的锚点。 */
    $seqDefs = array(
        array('label' => 'CDS',        'name' => 0, 'seq' => 1, 'unit' => 'bp'),
        array('label' => 'Transcript', 'name' => 2, 'seq' => 3, 'unit' => 'nt'),
        array('label' => 'Protein',    'name' => 4, 'seq' => 5, 'unit' => 'aa'),
    );
    $seqRows = array();
    foreach ($seqDefs as $i => $d) {
        $sv = isset($sequence[$d['seq']]) ? $sequence[$d['seq']] : null;
        if ($sv === null || trim((string)$sv) === '' || trim((string)$sv) === '-') { continue; }
        /* 长度按去掉空白后的字符数算：库里的序列是 FASTA 那样折行的，直接 strlen
           会把换行也数成残基。 */
        $seqRows[$i] = array(
            'label' => $d['label'],
            'name'  => isset($sequence[$d['name']]) ? trim((string)$sequence[$d['name']]) : '',
            'seq'   => (string)$sv,
            'len'   => strlen(preg_replace('/\s+/', '', (string)$sv)),
            'unit'  => $d['unit'],
        );
    }

    if (!$seqRows) {
        echo "<div class='gd-notice gd-info'>The sequence record for this gene is present but empty.</div>";
    } else {
        /* data-no-sort：这张表固定三行（CDS / transcript / protein）且每行自带一个
           显示开关，表头点排序既没有意义、还会把 ↕ 印在 Show 上。 */
        echo "<table class=\"gridtable\" data-no-sort><tr><th width='14%'>Sequence</th><th>Identifier</th>"
           . "<th class='num' width='16%'>Length</th><th width='14%'>Show</th></tr>";
        foreach ($seqRows as $i => $r) {
            echo "<tr><td><b>" . htmlspecialchars($r['label']) . "</b></td>"
               . "<td>" . ($r['name'] !== '' ? htmlspecialchars($r['name'])
                                             : "<span class='gd-na'>not recorded</span>") . "</td>"
               . "<td class='num'>" . number_format($r['len']) . " " . $r['unit'] . "</td>"
               . "<td><a class=\"gp-a\" id=\"seqt$i\" href=\"#seq$i\""
               . " onclick=\"return gd_seq_toggle($i);\">show &darr;</a></td></tr>";
        }
        echo "</table>";

        foreach ($seqRows as $i => $r) {
            echo "<div class=\"gd-seqpane\" id=\"seq$i\">"
               . "<div class=\"gd-seqhead\"><b>" . htmlspecialchars($r['label']) . "</b>"
               . ($r['name'] !== '' ? " &middot; " . htmlspecialchars($r['name']) : '')
               . " &middot; " . number_format($r['len']) . " " . $r['unit']
               . " <a class=\"gp-a gd-seqcopy\" href=\"#seq$i\""
               . " onclick=\"return gd_seq_toggle($i);\">hide &uarr;</a></div>"
               . "<textarea class=\"gd-seqtext\" readonly=\"readonly\" spellcheck=\"false\""
               . " wrap=\"soft\">" . htmlspecialchars($r['seq']) . "</textarea></div>";
        }
        echo "<p class=\"gd-hint\">Long sequences are wrapped to fit the box for reading; the text itself is "
           . "unchanged, so selecting inside a box and copying gives the sequence exactly as stored. CDS and "
           . "transcript lengths are in nucleotides (bp / nt), protein in amino acids (aa).</p>";
        echo "<div class=\"clr\"></div>";
    }
}
?>

<?php
/* ------------------------------ UniProt ---------------------------------
 * 原实现把 $result1 同时当作「行数据」和「是否已打印」的标记：while 循环把结果集
 * 取空后，下面那次 fetch 必然返回 null，</table> 永远不会输出，导致后续所有区块
 * 都被嵌进这张表里（Referee 3 报告里的 DOM 错乱）。这里改用显式布尔标记。 */
if ($T['uniprot']) {
    $qU = mysqli_query($conn, "SELECT * FROM {$ABBR}_uniprot WHERE gene IN ($geneIn)");
    $rowsU = array();
    if ($qU) { while ($rU = mysqli_fetch_row($qU)) { $rowsU[] = $rU; } }
    echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;UniProt (Swiss-Prot top hit)</legend>";
    if (empty($rowsU)) {
        echo "<div class='gd-notice gd-info'>No Swiss-Prot hit above the reporting threshold for this gene.</div>";
    } else {
        echo "<table class=\"gridtable\" width='98%'><tr><th width='18%'>UniProt accession</th><th>Description</th></tr>";
        foreach ($rowsU as $rU) {
            echo "<tr align='center'><td><a href=\"https://www.uniprot.org/uniprotkb/" . urlencode($rU[1])
               . "\" target='_blank'>" . htmlspecialchars($rU[1]) . "</a></td><td>"
               . htmlspecialchars($rU[2]) . "</td></tr>";
        }
        echo "</table>";
    }
    echo "<div class=\"clr\"></div>";
}
?>
<?php
/* ----------- Orthogroup / gene family (审稿意见 Referee 3 点 10c) ----------
 * 审稿人指出：从基因页无法跳到它所属的 orthogroup。gene_detail 之前只查了
 * tf / ubs 两张表，完全没有查 genefamily。这里补上正交群列表，并带上 abbr
 * 过滤，避免落到 7000+ 行、几百页的整族列表。
 *
 * 2026-09-23：改查 og_family_member。原来查的 genefamily 是 104 个蛋白组那次跑的，
 * 而家族页 genefamily_result.php 和基因树页 /genetree/ 都已经读新的 og_family*
 * （153 个蛋白组）。两边 OG 编号同名但含义不同 —— 旧表 OG0000000 是 25,957 个基因，
 * 新表是 35,546 个 —— 所以"基因 -> 它的 orthogroup"点过去会落到同号但不同的家族。
 * og_family_member 上有 idx_abbr_gene(abbr, gene)，这个查询走索引。
 * 表头有重复的两个蛋白组（Alatina_alata / Calvadosia_cruxmelitensis）会让同一个
 * 基因 ID 落进多个 OG，所以这里不取 LIMIT 1，列出多条是真实情况而不是脏数据。 */
$ogRows = array();
if ($gene !== '') {
    $qOG = mysqli_query($conn, "SELECT DISTINCT og FROM og_family_member WHERE gene IN ($geneIn) AND abbr = '" . gd_esc($conn, $ABBR) . "' ORDER BY og");
    if ($qOG) { while ($r = mysqli_fetch_row($qOG)) { $ogRows[] = $r[0]; } }
}
$result_TF = null; $result_Ubs = null;
$rowsTF = array(); $rowsUbs = array();
$qTF = mysqli_query($conn, "SELECT * from tf WHERE gene IN ($geneIn) AND species = '" . gd_esc($conn, $species) . "'");
if ($qTF) { while ($r = mysqli_fetch_row($qTF)) { $rowsTF[] = $r; } }
$qUb = mysqli_query($conn, "SELECT * from ubs WHERE gene IN ($geneIn) AND species = '" . gd_esc($conn, $species) . "'");
if ($qUb) { while ($r = mysqli_fetch_row($qUb)) { $rowsUbs[] = $r; } }

if ($rowsTF || $rowsUbs || $ogRows) {
    echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Gene family</legend>";
    echo "<table class=\"gridtable\"><tr><th width='30%'>Family type</th><th>Membership / link</th></tr>";
    foreach ($ogRows as $og) {
        $ogU = urlencode($og);
        echo "<tr align='center'><td>Orthogroup (gene family)</td><td><a href=\"./genefamily_result.php?family="
           . $ogU . "&abbr=" . urlencode($ABBR) . "\">" . htmlspecialchars($og)
           /* 原来这里挂着「(this species only)」，是假的：genefamily_result.php 的
              abbr= 参数并不过滤物种，点进去就是整个直系同源组 —— OG0001885 一页
              431 个成员、99 个物种，而本站并没有「只列本物种」的那个视图。删掉。 */
           . "</a>"
           /* 审稿意见：再往上一层是基因树。带上 gene/abbr，树页会把查询的这个基因高亮，
            * 并在旁边列出它在族内的直系/旁系同源基因。 */
           . " &middot; <a href=\"./genetree/?family=" . $ogU . "&gene=" . urlencode($gene)
           . "&abbr=" . urlencode($ABBR) . "\">gene tree &amp; orthology</a></td></tr>";
    }
    foreach ($rowsTF as $r) {
        echo "<tr align='center'><td>Transcription factor family</td><td><a href=\"./family_member_detail.php?gene_family=tf&&species="
           . urlencode($r[0]) . "&&family_id=" . urlencode($r[4]) . "\">" . htmlspecialchars($r[4])
           . "</a> &middot; <a href=\"./family_member.php?family=tf&&species=" . urlencode($r[0])
           . "\">all TF in this species</a></td></tr>";
    }
    foreach ($rowsUbs as $r) {
        echo "<tr align='center'><td>Ubiquitin family</td><td><a href=\"./family_member_detail.php?gene_family=ubs&&species="
           . urlencode($r[0]) . "&&family_id=" . urlencode($r[5]) . "\">"
           . htmlspecialchars($r[3] . '|' . $r[4] . '|' . $r[5])
           . "</a> &middot; <a href=\"./family_member.php?family=ubs&&species=" . urlencode($r[0])
           . "\">all ubiquitin genes in this species</a></td></tr>";
    }
    echo "</table><br><div class=\"clr\"></div>";
}
?>
<!-------------------------------Pfam domain----------------------------------------------------------------->
<?php
/* 审稿意见 Referee 3 点 10(d)：之前表里只写 "-" 或 "--"，读者无法分辨
 * 「该基因没有这个结构域」与「该物种根本没有做这项注释」。下面三种情况
 * 分别给出不同措辞；同时把结构域 ID 做成可点击的搜索入口。 */
$annot_blocks = array(
    'pfam' => array(
        'title' => 'Pfam domain',
        'head'  => "<tr><th width='12%'>Pfam accession</th><th width='16%'>Pfam name</th><th style='width: 38%;'>Description</th><th width='12%'>Type</th><th width='12%'>Source</th></tr>",
        'link'  => 'https://www.ebi.ac.uk/interpro/entry/pfam/',
        'term'  => 1, 'src' => 5, 'url' => 6,
        'extra' => array(
            array('label' => 'Pfam name',   'idx' => 2, 'search' => 'pfam_name'),
            array('label' => 'Description', 'idx' => 3, 'search' => 'pfam_desc'),
            array('label' => 'Type',        'idx' => 4, 'search' => null),
        ),
    ),
    'ipr' => array(
        'title' => 'InterPro',
        'head'  => "<tr><th width='12%'>InterPro term</th><th width='14%'>Type</th><th style='width: 40%;'>Description</th><th width='14%'>Source</th></tr>",
        'link'  => 'https://www.ebi.ac.uk/interpro/entry/InterPro/',
        'term'  => 1, 'src' => 4, 'url' => 5,
        'extra' => array(
            array('label' => 'Type',        'idx' => 2, 'search' => null),
            array('label' => 'Description', 'idx' => 3, 'search' => 'ipr_desc'),
        ),
    ),
    'panther' => array(
        'title' => 'PANTHER',
        'head'  => "<tr><th width='14%'>PANTHER term</th><th style='width: 46%;'>Description</th><th width='16%'>Source</th></tr>",
        'link'  => 'https://www.pantherdb.org/panther/family.do?clsAccession=',
        'term'  => 1, 'src' => 3, 'url' => 4,
        'extra' => array(
            array('label' => 'Description', 'idx' => 2, 'search' => null),
        ),
    ),
    'go' => array(
        'title' => 'Gene Ontology',
        'head'  => "<tr><th width='15%'>GO term</th><th width='15%'>Category</th><th style='width: 40%;'>Description</th><th width='15%'>Source</th></tr>",
        'link'  => 'http://amigo.geneontology.org/amigo/term/',
        'term'  => 1, 'src' => 4, 'url' => 5,
        'extra' => array(
            array('label' => 'Category',    'idx' => 2, 'search' => null),
            array('label' => 'Description', 'idx' => 3, 'search' => 'go_desc'),
        ),
    ),
);

/* Referee 3 点 10(e)：结构域编号本身也做成超链接 —— 点一下即可看到
 * 「所有物种中带这个结构域的基因」，这正是审稿人想做的检索。 */
foreach ($annot_blocks as $key => $blk) {
    echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;" . $blk['title'] . "</legend>";
    if (!$T[$key]) {
        echo "<div class='gd-notice gd-warn'>" . $blk['title'] . " annotation has not been computed for <i>" . $spLatin
           . "</i> (its genome is assembly-only). See <a href='genomeinfo.php?filter=annotated'>annotated genomes</a>.</div>";
        continue;
    }
    $rows = array();
    $q = mysqli_query($conn, "SELECT DISTINCT * FROM {$ABBR}_{$key} WHERE gene IN ($geneIn)");
    if ($q) { while ($r = mysqli_fetch_row($q)) { $rows[] = $r; } }
    if (empty($rows)) {
        echo "<div class='gd-notice gd-info'>No " . $blk['title'] . " signature was recorded for <code>"
           . $spGene . "</code> in <i>" . $spLatin . "</i>.</div>";
        continue;
    }
    echo "<table class=\"gridtable\">" . $blk['head'];
    foreach ($rows as $r) {
        $term = htmlspecialchars($r[$blk['term']]);
        $acc  = urlencode($r[$blk['term']]);
        $srcI = $blk['src'];
        $srcTxt = htmlspecialchars($r[$srcI]);
        $srcUrl = (!empty($blk['url']) && !empty($r[$blk['url']]))
                ? "<a href=\"" . htmlspecialchars($r[$blk['url']]) . "\" target='_blank'>$srcTxt</a>" : $srcTxt;
        echo "<tr align='center'><td><a href=\"{$blk['link']}$acc\" target='_blank'>$term</a><br>"
           . "<span style='font-size:12px;white-space:nowrap'><a href='domain_search.php?db=$key&amp;q=" . $acc
           . "' title='Find every gene with this signature across all species'>all species &rarr;</a></span></td>";
        $n = count($blk['extra']);
        foreach ($blk['extra'] as $i => $ex) {
            $val = htmlspecialchars($r[$ex['idx']]);
            if ($i === $n - 1) { echo "<td>$val</td><td>" . $srcUrl . "</td>"; }
            else { echo "<td>$val</td>"; }
        }
        echo "</tr>";
    }
    echo "</table><div class=\"clr\"></div><br>";
}
echo "<div class='gd-notice gd-info'><b>Search by domain instead of by gene.</b> "
   . "Any accession above (InterPro, Pfam, PANTHER, GO, KEGG) can be used as a query on the "
   . "<a href='domain_search.php'>Functional Domain Search</a> page, which searches all "
   . mysqli_num_rows(mysqli_query($conn, "SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE '%\\_ipr'"))
   . " annotated genomes at once.</div>";
?>
<!------------------------------- KEGG ----------------------------------------------------------------->
<?php
echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;KEGG pathway</legend>";
if (!$T['KEGG']) {
    echo "<div class='gd-notice gd-warn'>KEGG annotation has not been computed for <i>" . $spLatin
       . "</i> (its genome is assembly-only).</div><div class=\"clr\"></div>";
} else {
    $qK = mysqli_query($conn, "SELECT DISTINCT * FROM {$ABBR}_KEGG WHERE gene IN ($geneIn) ORDER BY KO, Pathway_ID");
    $rowsK = array();
    if ($qK) { while ($r = mysqli_fetch_row($qK)) { $rowsK[] = $r; } }
    if (empty($rowsK)) {
        echo "<div class='gd-notice gd-info'>No KEGG orthology assignment for <code>" . $spGene . "</code>.</div>";
    } else {
        echo "<table class=\"gridtable\"><tr><th width='10%'>KO ID</th>"
           . "<th width='22%' title='KEGG&apos;s own definition of the ortholog: the gene abbreviation followed by its full name (for example H2B; histone H2B). It is not an enzyme classification &mdash; the EC number, where the ortholog has one, is the next column.'>KO definition</th>"
           . "<th width='20%'>Enzyme ID</th>"
           . "<th width='26%'>Pathway</th><th width='10%' title='KEGG map number. Some KO numbers are BRITE hierarchies rather than pathway maps (for example ko04131, ko03036); those open under KEGG BRITE.'>Map ID</th><th width='12%'>Source</th></tr>";
        foreach ($rowsK as $r) {
            $ko = htmlspecialchars($r[1]);
            echo "<tr align='center'>";
            echo "<td><a href=\"https://www.genome.jp/entry/$ko\" target=\"_blank\">$ko</a></td>";
            echo "<td>" . htmlspecialchars($r[2]) . "; " . htmlspecialchars($r[3]) . "</td>";
            $eid = trim((string)$r[4]);
            echo "<td>";
            if ($eid === '' || $eid === '-') { echo "<span class='gd-na'>&ndash;</span>"; }
            else {
                foreach (preg_split('/[\s,]+/', $eid) as $id) {
                    if ($id === '') { continue; }
                    if (strpos($id, '-') !== false) { echo htmlspecialchars($id) . "<br>"; }
                    else { echo "<a href=\"https://www.genome.jp/entry/$id\" target=\"_blank\">" . htmlspecialchars($id) . "</a><br>"; }
                }
            }
            echo "</td>";
            echo "<td>" . htmlspecialchars($r[5]) . "</td>";
            $mapid = trim((string)$r[6]);
            /* KO 号分两类，入口不同（BRITE 层次表走 /brite/，真·通路图走 /pathway/）：
               分派逻辑连同那份 54 项名单都在 includes/state.php 的
               cnido_kegg_map_url() 里，kegg_result.php / MAG_detail.php 用的是同一份
               —— 名单只有一处，免得两边走散。 */
            echo "<td>" . ($mapid === '' || $mapid === '-'
                    ? "<span class='gd-na'>&ndash;</span>"
                    : "<a href=\"" . cnido_kegg_map_url($mapid) . "\" target=\"_blank\">"
                      . htmlspecialchars($mapid) . "</a>") . "</td>";
            echo "<td>" . htmlspecialchars($r[7]) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "<br>";
    /* 跨物种按 KO / 通路号检索的入口是 domain_search.php，不是 kegg.php：kegg.php
       的表单一次只查一个物种、且只认基因 ID（它自己也这么写着，并把跨物种的查法
       指向同一个 domain_search.php），照原来那样指过去，读者到了那边照样查不了。 */
    echo "<div class='gd-notice gd-info'>Searching by KO or pathway ID across <i>all</i> species is available on the "
       . "<a href='domain_search.php?db=KEGG'>Functional Domain Search</a> page "
       . "(it accepts K13752 or <i>ko02000</i>).</div>";
}
echo "<div class=\"clr\"></div>";
?>
<?php
/* ---------------------------------------------------------------------
 * Expression pattern (RNA-seq)
 *
 * 以前这里只有 Lophelia pertusa 一个物种能看到表达谱：判断写死成
 * `if ($species == "Lophelia pertusa")`，110 个样本号、配色分组和 x 轴标签
 * 都以字面量抄在页面里，别的物种即使有 <ABBR>_TPM 表达矩阵也看不到任何东西。
 * 现在改成调用通用面板：凡是有表达矩阵的物种都会显示，样本只有一个时也是
 * 一根条，不做特殊处理。
 *
 * 面板在 includes/rnaseq_expression_panel.php。表达量取 <ABBR>_TPM 表，
 * 样本元数据取 data/rnaseq_samples.json（由 includes/rnaseq_meta_refresh.php
 * 从 RNA-seq 的 sample 表离线生成，页面不读数据盘）。索引缺失时面板照常
 * 工作，只是组织/处理等元数据少一些。
 * ------------------------------------------------------------------ */
/* ---------------------------------------------------------------------
 * 多组学分区。
 *
 * 六张面板原来是一个接一个直接输出的，页面上没有任何一处告诉读者「下面这些
 * 都属于组学数据、一共几块、哪一块有内容」。本页 8000px 高，读者得一路滚到底
 * 才知道有没有蛋白组。现在：分区标题 + 一行跳转条 + 六张面板。
 *
 * 跳转条必须印在面板之前，而「这一节有哪些面板」只有把面板渲染完才知道
 * （表达面板没有 TPM 矩阵时直接 return，单细胞面板没有数据集时不印表格），
 * 所以先把这一节的输出 ob_start() 起来：每张面板渲染时调用 cnido_gp_nav_add()
 * 登记自己，渲染完再一次性输出「标题 + 跳转条 + 缓冲内容」。没有登记的面板
 * 不会出现在条上，跳转条因此不会指向空锚点。
 *
 * 面板自带 id（rnaseq-expression / proteomics / coexpression / singlecell /
 * epigenome / tools），都带 .cn-anchor 让滚动留出余量。
 * ------------------------------------------------------------------ */
require_once __DIR__ . '/includes/gene_panels_common.php';   // 跳转条与卡片样式
require_once __DIR__ . '/includes/rnaseq_expression_panel.php';

ob_start();
render_rnaseq_expression_panel($gene, $species, $conn);

/* ---------------------------------------------------------------------
 * 审稿意见 Referee 2 major 1：本页此前到表达谱就结束了 —— 读者看不到这个基因
 * 还有哪些数据、也不知道能拿它去做什么分析。下面五张面板补上这一段。
 *
 * 安装方式与表达面板一致：一个文件 + 一行调用。每张面板自己判断该物种有没有
 * 对应数据，**没有数据时渲染一行说明而不是静默消失** —— 读者问的正是「有没有」，
 * 一片空白回答不了这个问题（唯一例外是蛋白组面板：它在没有证据时保持安静，
 * 因为它出现的前提是「有肽段被鉴定到」，这点与「该物种没做过蛋白组」不同）。
 *
 *   proteomics   —— 质谱鉴定到的肽段 / 覆盖度
 *   coexpress    —— 共表达伙伴数与网络分析深链
 *   singlecell   —— 单细胞图谱里能不能看、是哪些细胞类型的 marker
 *   epigenome    —— 峰落在基因哪个部位、有哪些甲基化样本
 *   tools        —— 引物设计 / BLAST / 表达热图 / 同源家族 / 基因集富集
 * ------------------------------------------------------------------ */
require_once __DIR__ . '/includes/gene_proteomics_panel.php';
render_gene_proteomics_panel($gene, $species, $conn);

require_once __DIR__ . '/includes/gene_coexpress_panel.php';
render_gene_coexpress_panel($gene, $species, $conn);

require_once __DIR__ . '/includes/gene_singlecell_panel.php';
render_gene_singlecell_panel($gene, $species, $conn);

require_once __DIR__ . '/includes/gene_epigenome_panel.php';
render_gene_epigenome_panel($gene, $species, $conn);

require_once __DIR__ . '/includes/gene_tools_panel.php';
render_gene_tools_panel($gene, $species, $conn);

/* 这一节渲染完了：条上该有哪些条目已经确定。输出顺序是
   标题 → 跳转条 → 缓冲的全部面板。 */
$omicsBody = ob_get_clean();
echo "<legend><img src=\"./images/header.jpg\" height=\"35px\" style=\"margin-bottom:-10px\">&nbsp;Multi-omics data for this gene</legend>";
echo "<p class=\"paleo-intro\">Everything CnidoSite holds for <b>" . $spGene . "</b> beyond its annotation: "
   . "transcriptome expression, co-expression, proteomic evidence, single-cell atlases and epigenetic marks. "
   . "Each panel states whether the species has that kind of data, and every panel ends with a button that "
   . "opens the matching viewer with this gene already entered.</p>";
echo cnido_gp_nav_html();
echo $omicsBody;
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
