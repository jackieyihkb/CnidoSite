<?php
/* 页脚要给出版本戳（审稿意见 Referee 2 major 9 要求站内可见数据库版本号）。
   版本元数据的唯一来源是 includes/release.php，与 release.php 页面、api.php
   接口共用，避免三处各写一个版本号而对不上。
   require_once + function_exists 双保险：本文件在个别目录下还有一份副本，
   被同时包含时不会重复声明函数。 */
require_once __DIR__ . '/includes/release.php';
$cnidoFooterVersion = cnido_release_string();

$header = <<<EOF
<div id="templatemo_header_wrapper">

	<div id="templatemo_header">
    
    	<div id="site_logo"></div>
    
    </div> <!-- end of header -->

</div> <!-- end of header wrapper -->

<div id="templatemo_menu_wrapper">   
    
    <div id="templatemo_menu">
        <ul>
            <li><a href="/index.php" class="current">Home</a></li>
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
					<li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li>
					<li><a href="/microsynteny.php">Microsynteny Analysis</a></li>
					<li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li>
					<li><a href="/mitdata.php">Mitogenomic Data</a></li>
				</ul>
			</li>
			<li><a href="#">Transcriptome</a>
				<ul>
					<li><a href="/cytoscape/trans_data.php">Transcriptomic Data</a></li>
					<li><a href="/trans_assembly.php">Transcriptome Assembly</a></li>
					<li><a href="/cytoscape/network.php">Network Analysis</a></li>
					<li><a href="/cytoscape/network_expression.php">Dynamic Expression View</a></li>
				</ul>
			</li>
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
			<li><a href="#">Phenotype</a>
				<ul>
					<li><a href="/phenotype.php?class=all">All</a></li>
					<li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li>
					<li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li>
					<li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li>
					<li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li>
					<li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li>
				</ul>
			</li>
			<li><a href="#">Tools</a>
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
	</div> <!-- end of menu -->
</div> <!-- end of menu wrapper -->
EOF;

/* 表头排序脚本（js/table-sort.js）：给数据表每一列加一个小箭头，点一下排序。
   挂页脚而不是页头：脚本在 body 末尾时表格已经解析完，不用等 DOMContentLoaded。
   （以前还得排在页脚那行 mapmyvisitors 外链之前躲开它——2026-09-28 起那个已改成
   load 之后再注入，页脚里不再有解析期的第三方脚本，这条顾虑消失了。）
   文件名带 filemtime 破缓存：站点静态资源只有 Last-Modified，浏览器会启发式缓存
   好几个小时，改了脚本用户可能看不到（sc_pages.css / cellatlas.css 同一套做法）。 */
$cnidoSortJsV = (int)@filemtime(__DIR__ . '/js/table-sort.js');

/* 窄屏导航的二级菜单开关（js/nav-dropdown.js）。挂页脚的理由和上面那个一样：
   页面末尾时导航已经解析完，不用等 DOMContentLoaded。93 个页面里导航是各自
   内联的一份 HTML，没有公共 header 可挂，而这 85 个页面都印这个 $footer，
   所以这里是唯一一处能一次覆盖全站的地方。 */
$cnidoNavJsV = (int)@filemtime(__DIR__ . '/js/nav-dropdown.js');

/* 访客地图（mapmyvisitors）。**不能**在解析期挂 <script src>：那行第三方外链会串行
		   发三个请求（map.js 732ms + widget_call_home.js 720ms + /ajax/map 694ms，约 2.1 秒），
		   同步会停住解析器，defer/async 也仍会拖住 load（标签页一直转圈）。改成 load 之后再
		   注入，实测 load 从 ~2.3s 降到 ~0.16s。min-height 预留地图高度，避免注入时页脚跳一下。
		   验证过：地图照常显示（250x123 世界地图 + 浏览量）、CLS 仍是 0。 */
$footer =  <<<EOF
<div class="guide">
	<div class="guide-wrap">

		<a href="javascript:window.scrollTo(0,0)" class="top" title="TOP"><span>TOP</span></a>
	</div>
</div>


<div id="templatemo_footer_wrapper">

	<div id="templatemo_footer">
        <div class="section_w920"><div style="float: left;">
		<br>
        Maintained by <a href="https://scholar.google.com.hk/citations?user=I4Rl5BMAAAAJ" target="_blank" rel="noopener noreferrer">Dr. Jiajie She</a> from <a href="https://longjunwulab.org/" target="_blank" rel="noopener noreferrer">Prof. Longjun Wu's Lab</a> & <a href="https://qianlab.hkust.edu.hk/" target="_blank" rel="noopener noreferrer"> Prof. Pei-yuan Qian's Lab</a> <br />Hong Kong Branch of the Southern Marine Science and Engineering Guangdong Laboratory (Guangzhou)<br /><a href="https://hkust.edu.hk/" target="_blank" rel="noopener noreferrer"> Hong Kong University of Science and Technology </a><br />Clear Water Bay, Kowloon, Hong Kong<br />&copy; 2026 All Rights Reserved.<br>
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

EOF;

$NoJobIDError = <<<EOF
<div class="title2"><img src="images/error.jpg" />Sorry, your job ID could not be found; it may have expired.</div>
EOF;
$BigFileError = <<<EOF
<div class="title2"><img src="images/error.jpg" />Sorry, the file size exceeds the limit allowed!</div>
EOF;
?>
