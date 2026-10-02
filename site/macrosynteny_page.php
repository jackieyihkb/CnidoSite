<?php
/* ---------------------------------------------------------------------------
 * macrosynteny_page.php -- 三个宏共线性页面共用的页头 / 导航 / 页脚
 *
 * 导航内容与站点现有页面（如 busco.php）逐字一致，只在 Genome 下拉里新增一行
 * "Macrosynteny Analysis"：插在 Species Tree 与 Mitogenomic Data **之间**，
 * 与该页原有写法（两个条目同行、20 空格缩进）保持一致。
 *
 * 站点没有公共的 header include，导航是每个页面各自内联的一份 HTML，所以其余页面
 * 需要逐文件改 —— 用 web/nav_patch.py 批量插入（可重复执行、自动备份）：
 *     python3 nav_patch.py --root /var/www/html/CnidoSite --dry-run
 *     python3 nav_patch.py --root /var/www/html/CnidoSite --apply
 * ------------------------------------------------------------------------- */

function msr_page_head($title, $description, $keywords = null, $extra_head = '') {
    $t = msr_h($title);
    $d = msr_h($description);
    /* $keywords / $extra_head 是给同一套外壳的其它模块（Microsynteny Analysis）用的
       可选参数，默认值保持原有行为，宏共线性三个页面的调用一字未改。
       $extra_head 用来在 </head> 之前插入模块自己的样式表 —— 只能在 heredoc 外面
       拼好字符串再嵌进去，heredoc 里的 <?php ?> 不会被解析。 */
    $k = msr_h($keywords !== null ? $keywords
              : 'Cnidaria, macrosynteny, Oxford grid, linkage group, BUSCO, synteny conservation, genome evolution');
    /* 样式表的 mtime 要在 heredoc 外面先算好：heredoc 里的 <?php ?> 不会被解析，
       会原样输出到页面上（.css 只有 Last-Modified，浏览器启发式缓存能挂好几个小时，
       改了样式用户看不到）。与 sc_common.php 里 sc_pages.css 的做法一致。 */
    $css = (int)@filemtime(__DIR__ . '/css/macrosynteny.css');
    /* 同理：templatemo_style.css 的 mtime 也必须在 heredoc 外面算（本文件是全站
       唯一用 heredoc 拼 <head> 的地方，别处是普通的 <?php ?> 内联，照抄别处写法
       会原样打出 PHP 源码）。 */
    $tplCss = (int)@filemtime(__DIR__ . '/templatemo_style.css');
    echo <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>$t - CnidoSite</title>
<meta name="keywords" content="$k" />
<meta name="description" content="$d" />
<link href="/templatemo_style.css?v=$tplCss" rel="stylesheet" type="text/css" />
<link href="/css/macrosynteny.css?v=$css" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
$extra_head</head>

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
HTML;
}

function msr_page_foot() {
    /* 版本戳从 includes/release.php 现取，不再把 "r1.1 (2026-09-16)" 抄在这段
       HTML 里 —— 抄死的话每次发版这个页脚都会落后于站内其它页脚。 */
    require_once __DIR__ . '/includes/release.php';
    $cnidoFooterVersion = cnido_release_string();
    /* 表头排序脚本：与 Webpage_components.php 的页脚同一份（这页自己写了一份页脚）。
       （以前要排在 mapmyvisitors 外链之前躲开它，2026-09-28 起那个改成 load 后注入，
       页脚里不再有解析期第三方脚本，这条顾虑消失了。） */
    $cnidoSortJsV = (int)@filemtime(__DIR__ . '/js/table-sort.js');
    /* 窄屏导航的二级菜单开关：这页的导航也是自己内联的一份，同样要挂。 */
    $cnidoNavJsV = (int)@filemtime(__DIR__ . '/js/nav-dropdown.js');
    /* 访客地图：与 Webpage_components.php 同一份做法——load 之后再注入，不在解析期挂
		   第三方 <script src>（详见那边的注释）。 */
    echo <<<HTML
</div>
</div>
</div>

<div class="guide">
	<div class="guide-wrap">
		<a href="javascript:window.scrollTo(0,0)" class="top" title="TOP"><span>TOP</span></a>
	</div>
</div>

<div id="templatemo_footer_wrapper">
	<div id="templatemo_footer">
        <div class="section_w920"><div style="float: left;">
		<br>
        Maintained by <a href="https://scholar.google.com.hk/citations?user=I4Rl5BMAAAAJ" target="_blank" rel="noopener noreferrer">Dr. Jiajie She</a> from <a href="https://longjunwulab.org/" target="_blank" rel="noopener noreferrer">Prof. Longjun Wu's Lab</a> &amp; <a href="https://qianlab.hkust.edu.hk/" target="_blank" rel="noopener noreferrer"> Prof. Pei-yuan Qian's Lab</a> <br />Hong Kong Branch of the Southern Marine Science and Engineering Guangdong Laboratory (Guangzhou)<br /><a href="https://hkust.edu.hk/" target="_blank" rel="noopener noreferrer"> Hong Kong University of Science and Technology </a><br />Clear Water Bay, Kowloon, Hong Kong<br />&copy; 2026 All Rights Reserved.<br>
		<span style="font-size:15px;color:#dbeafe;">Database release <b>{$cnidoFooterVersion}</b> &mdash; <a href="/release.php" style="text-decoration:underline;">release notes &amp; changelog</a></span><br></div>
		<div style="float: right;"><a href="https://www.gmlab.ac.cn/home/" target="_blank" rel="noopener noreferrer"><br><img src="/images/logo_eng_B.png" width="135px" alt="GML logo"/></a><a href="https://www.gmlab.ac.cn/en/" target="_blank" rel="noopener noreferrer"><img src="/images/GML.png" width="150px" alt="GML logo"/></a><br></div>
        </div>
		<script type="text/javascript" src="/js/table-sort.js?v={$cnidoSortJsV}"></script>
		<script type="text/javascript" src="/js/nav-dropdown.js?v={$cnidoNavJsV}"></script>
		<div style="float: right;"><br>
			<div style="display:grid; place-items:center;">
			<div id="cnidoVisitorMapSlot" style="width:250px;min-height:155px;overflow:hidden;display:flex;align-items:center;justify-content:center;"></div></div>
		<br></div>
		<script type="text/javascript">
		 
		(function(){
			function cnidoVisitorMap(){
				var slot=document.getElementById('cnidoVisitorMapSlot');
				if(!slot||slot.firstChild) return;
				var s=document.createElement('script');
				s.type='text/javascript'; s.id='mapmyvisitors'; s.async=true;
				s.src='//mapmyvisitors.com/map.js?d=xKVmDMUukHd1cIC82xNN0uIH7ye6u6UXNC0WRn5A5Ic&cl=ffffff&w=a';
				slot.appendChild(s);
			}
			function cnidoVisitorGo(){
				if(window.requestIdleCallback) requestIdleCallback(cnidoVisitorMap,{timeout:2000});
				else cnidoVisitorMap();
			}
			if(document.readyState==='complete') cnidoVisitorGo();
			else window.addEventListener('load',cnidoVisitorGo);
		})();
		</script>
        <div class="cleaner"></div>
    </div> <!-- end of footer -->
</div>
</body>
</html>
HTML;
}

/* 物种下拉框：**全部**物种都在，按 class 用 <optgroup> 分组。
 *
 * 这里刻意不做「先选 Class、再按 class 收窄物种」的联动（站内 go.php / kegg.php
 * 那种 filterSpeciesByClass 模式）。本页一次要选**两个**物种，只有一个 Class 下拉
 * 时它管不了两侧：任一侧改 Class 都会把另一侧已经选好的物种顶掉，结果就是跨 class
 * 的物种对根本选不出来 —— 而库里 9591 对（= C(139,2)，含 4893 对跨 class）全都
 * 算好了，且每一对都有 ≥111 个锚点，没有任何一对是缺的。
 * 分组信息改用 <optgroup> 表达：纯 HTML，不依赖 JS，也就不会顶掉任何已选项。
 *
 * $rows：含 display_name / class 的行数组；$selected：当前选中的显示名。 */
function msr_species_select($rows, $name, $selected = '') {
    $groups = array();
    foreach ((array)$rows as $r) {
        $disp = is_array($r) ? $r['display_name'] : $r[0];
        $cls  = is_array($r) ? $r['class'] : $r[2];
        if ($cls === null || trim((string)$cls) === '') { $cls = 'Unclassified'; }
        $groups[$cls][] = $disp;
    }
    ksort($groups);

    echo '<select name="' . msr_h($name) . '" class="form-select">' . "\n";
    if (!count($groups)) {
        echo '<option value="">(species table not available)</option>' . "\n";
    }
    foreach ($groups as $cls => $names) {
        echo '<optgroup label="' . msr_h($cls) . '">' . "\n";
        foreach ($names as $disp) {
            $sel = ($disp === $selected) ? ' selected="selected"' : '';
            echo '<option value="' . msr_h($disp) . '"' . $sel . '>' . msr_h($disp) . '</option>' . "\n";
        }
        echo '</optgroup>' . "\n";
    }
    echo '</select>' . "\n";
}
?>
