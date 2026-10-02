<?php
$family = isset($_GET['family']) ? $_GET['family'] : (isset($_POST['family']) ? $_POST['family'] : '');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Gene Family Search Results - CnidoSite</title>
<meta name="description" content="Members of the selected gene family, grouped by cnidarian species" />
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
<style>
<?php /* ---------- 家族页头（.fam-hero） ------------------------------------------
 * 这里原先用的是从 BUSCO 结果页抄来的 .busco-result-header + .species-badge
 * + 三颗 .gtree-btn 药丸。问题有两个：
 *   1. 那个琥珀色药丸里装的是一整句话（「no term is shared by every member —
 *      best support 51.2%」）。药丸形状（圆角 20px + 实心色 + 阴影）本身就是
 *      「可以点」的信号，结果最抢眼的元素不是链接，读者会去点它。
 *   2. 三个链接同形同尺寸，基因树只是其中最左边一颗，看不出主次 —— 而这一页
 *      绝大多数人是为看树来的（/genetree/ 每个家族都有，67795/67795）。
 * 改法：主操作只留一个实心大按钮，右对齐、占两行（副行写清里面是什么）；两个
 * 下载降级成描边小按钮，统一收到 "Downloads" 标签下；药丸里那句话挪到它真正
 * 描述的那张注释表头上；概述从整段散文改成一行事实（基因数/物种数/序列数/
 * 最佳支持度），散文里那半句解释支持度怎么算的话本来在注释表头也有，删掉。
 *
 * 另记一条别丢的背景：主操作打开的是 /genetree/ 这个**独立文档**，不是本页的
 * 一个面板 —— 树视图要 jQuery 3.2.1 + d3 + phylotree.js，而本页为它的 include
 * 载的是 jQuery 1.10.2，两者不能同处一个文档。所以它是 <a> 而不是就地展开，
 * 副行文字也不要写成「就在下面」那种承诺。
 * ------------------------------------------------------------------------- */ ?>
<?php /* 底色改成站内通用的白卡 + 1px #e2e8f0 描边。原来这里是深色渐变
   （#1e293b→#334155 + 阴影），是从 BUSCO 结果页抄来的模板，问题不在好看与否，
   而在于它是全站唯一一块深色：/core/ 已经因为同样的原因把深色 hero 去掉了
   （见 work/core/README.md「The page wears the site's chrome, not its own」），
   genefamily / busco / coverage_matrix 都是白底 .cm-* 与 .gridtable 卡片。深色只在
   这一个页面上出现，看上去像另一个站。改后：扁平、白底、无渐变、无阴影，和
   includes/coverage_matrix_view.php 的 .cm-* 词汇一致。

   【别在这条注释里让通配星号紧跟斜杠】原文把两个选择器写成「.cm-* / .gridtable」，
   中间那个斜杠是紧贴在前一个星号后面的（那个星号表示通配），这两个字符就连成了
   注释结束符，提前关掉了这条注释；剩下的半句被当成选择器，一路吃到下面 .fam-hero
   的大括号，于是 .fam-hero 整条规则被 CSS 解析器丢掉（卡片的白底、描边、内边距
   全没了），而源码、php -l、肉眼检查都看不出来 —— 只有把 CSSOM 里的规则列出来
   才看得见。注释里并列两个选择器请用「与」或空格隔开。 */ ?>
.fam-hero{position:relative;overflow:hidden;background:#fff;color:#1e293b;border:1px solid #e2e8f0;
    padding:20px 30px 20px 34px;border-radius:10px;margin-bottom:26px}
<?php /* 左侧渐变竖条，和 /genetree/ 的 .gt-hero 是同一条（两个页头是一套东西的两份副本：
   从家族页点进树页，页头不应该换一套配色/一套版式）。overflow:hidden 让竖条自己
   裁进圆角。 */ ?>
.fam-hero::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;
    background:linear-gradient(180deg,#2563eb,#38bdf8)}
.fam-hero-top{display:flex;align-items:flex-start;justify-content:space-between;
    gap:26px;flex-wrap:wrap}
.fam-kicker{display:block;font-size:13px;font-weight:700;color:#64748b}
.fam-hero h1{margin:5px 0 0;font-size:36px;font-weight:700;line-height:1.15;
    letter-spacing:.01em}

<?php /* 唯一的主操作：实心、最大、带副行说明。
   选择器必须带上 .fam-hero 前缀：templatemo_style.css 里那条 `a:link,a:visited
   {color:#1d4ed8}` 是 (0,1,1)，光用 `.fam-tree` (0,1,0) 压不住 —— 压不住的表现就是
   白字被染成链接蓝，实心按钮上的对比度掉到 2:1 出头（这正是原来那两颗下载药丸
   看起来发灰的原因）。`.fam-hero a.fam-tree` 是 (0,2,1)，稳赢，且不靠加载顺序。 */ ?>
.fam-hero a.fam-tree{display:flex;align-items:center;gap:14px;flex:none;
    background:#2563eb;color:#fff;padding:13px 22px;border-radius:8px;
    text-decoration:none;transition:background .2s ease}
.fam-hero a.fam-tree:hover{background:#1d4ed8;color:#fff}
.fam-tree-ic{font-size:24px;line-height:1}
.fam-tree-tx{display:flex;flex-direction:column;gap:2px}
.fam-tree-t{font-size:16px;font-weight:700;line-height:1.2;color:#fff}
.fam-tree-s{font-size:15px;font-weight:500;color:rgba(255,255,255,.85)}

<?php /* 概述改成事实行：数字用 dd 承担层级，dt 是小的全大写标签。
   2026-09-24：四项由"左对齐 + 38px 间距"改成撑满的统计条（space-between + 项间 1px
   竖线），和 /genetree/ 的 .gt-facts 同一套 —— 原来四项挤在左边 40%，右边 60% 是空白，
   同一页里两个页头的数字排法不一样。标签也一起降到 10.5px/.08em，两边对得上。 */ ?>
.fam-facts{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;
    margin:20px 0 0;padding:16px 0 0;border-top:1px solid #e2e8f0}
.fam-fact{padding:0 22px;border-left:1px solid #e2e8f0}
.fam-fact:first-child{border-left:0;padding-left:0}
.fam-fact:last-child{padding-right:0}
.fam-fact dt{font-size:13px;font-weight:600;color:#64748b}
.fam-fact dd{margin:4px 0 0;font-size:22px;font-weight:700;line-height:1.15;color:#1e293b;
    font-variant-numeric:tabular-nums}

<?php /* 下载：描边样式，明确低于主操作一档。 */ ?>
.fam-dl{display:flex;align-items:center;flex-wrap:wrap;gap:10px 12px;margin-top:20px}
.fam-dl-lbl{font-size:13px;font-weight:600;color:#64748b;margin-right:2px}
<?php /* 同样要 .fam-hero 前缀压 a:link 的颜色，见上面 .fam-tree 的注释。 */ ?>
.fam-hero a.fam-dl-a{display:inline-flex;align-items:center;gap:8px;padding:8px 15px;border-radius:8px;
    font-size:15px;font-weight:600;color:#475569;text-decoration:none;
    background:#fff;border:1px solid #cbd5e1;
    transition:background .2s ease,border-color .2s ease,color .2s ease}
.fam-hero a.fam-dl-a:hover{background:#f1f5f9;border-color:#94a3b8;color:#1e293b}
.fam-note{margin:14px 0 0;font-size:15px;line-height:1.6;color:#64748b}

<?php /* 家族级结论（原来那颗琥珀药丸里的句子），放在它描述的那张表头上。 */ ?>
.fam-verdict{margin:0 0 12px;padding:9px 13px;font-size:16px;line-height:1.55;
    color:#92400e;background:#fffbeb;border-left:3px solid #f59e0b;border-radius:4px}
.fam-verdict-none{color:#475569;background:#f8fafc;border-left-color:#cbd5e1}

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
    border-color:#b45309;
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


<?php /* 表格美化 */ ?>

.table-scroll-container {
    max-height: 700px;
    overflow-y: auto;
    overflow-x: auto;
}

<?php /* 链接样式 */ ?>
.gene-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.gene-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.jbrowse-link {
    color:#b45309;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.jbrowse-link:hover {
    color: #92400e;
    text-decoration: underline;
}

<?php /* 状态标签 */ ?>
.status-complete {
    background:#047857;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-fragmented {
    background:#b45309;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-duplicated {
    background:#6d28d9;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

.status-missing {
    background: #b91c1c;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 600;
}

@media (max-width: 768px) {
    .fam-hero {
        padding: 20px;
    }

    .fam-hero h1 {
        font-size: 28px;
    }

    .fam-hero-top {
        gap: 18px;
    }

    <?php /* 窄屏上主操作铺满一行，别和标题挤在一起。 */ ?>
    .fam-tree {
        width: 100%;
        justify-content: center;
    }

    .fam-facts {
        gap: 14px 26px;
    }

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
}

/* ---------- gene family: search form + consensus annotation ---------- */
.gf-form{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin:18px 0}
.gf-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.gf-row input[type=text]{flex:1;min-width:260px;max-width:620px;padding:10px 14px;border:2px solid #e2e8f0;
    border-radius:8px;font-size:15px;background:#fff}
.gf-row input[type=text]:focus{outline:none;border-color:#1d4ed8}
.gf-row button{padding:10px 26px;border:none;border-radius:8px;background:#1d4ed8;color:#fff;font-size:15px;
    font-weight:600;cursor:pointer;transition:background .3s ease}
.gf-row button:hover{background:#1d4ed8}
.gf-clear{color:#64748b;font-size:15px}
.gf-opts{margin-top:12px;font-size:15px;color:#475569}
.gf-opts label{display:inline-flex;align-items:center;gap:6px;min-width:0;max-width:100%}
.gf-opts select{padding:7px 10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;font-size:15px;cursor:pointer;min-width:0;max-width:100%}

.gfa-line{line-height:1.75;margin:2px 0}
.gfa-badge{display:inline-block;min-width:46px;padding:2px 8px;border-radius:10px;font-size:12px;
    font-weight:700;color:#fff;text-align:center}
.gfa-all{background:#047857}
.gfa-ge80{background:#0f766e}
.gfa-ge50{background:#b45309}
.gfa-none{background:#94a3b8}
/* 低于 50% 的弱证据：空心灰徽章，与上面三个实心档位一眼可分（同 genefamily.php） */
.gfa-low{background:#f8fafc;color:#64748b;border:1px solid #cbd5e1}
/* 共识表与弱证据之间的那道分隔行 */
.gfr-low-hd{background:#f8fafc;color:#475569;text-align:left;font-size:15px;line-height:1.7}
.gfa-src{display:inline-block;padding:1px 7px;border-radius:5px;font-size:12px;font-weight:700;
    color:#fff;background:#64748b;white-space:nowrap}
.gfa-src-pfam{background:#7c3aed}
.gfa-src-panther{background:#0369a1}
.gfa-src-go{background:#15803d}
.gfa-src-kegg{background:#db2777}
.gfa-term{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:15px;color:#334155}
.gfa-desc{color:#1e293b}
.gfa-cat{color:#64748b;font-size:15px}
.gfa-sup{color:#64748b;font-size:15px;white-space:nowrap}
.gfa-more{font-size:15px;color:#64748b;margin-top:2px}
.gfa-hit{background:#f0f9ff;border-left:3px solid #3b82f6;padding-left:8px;border-radius:3px}


</style>
</head>
<body>
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
/* ---------------------------------------------------------------------------
 * genefamily_result.php  --  one gene family: consensus annotation + members.
 *
 * Consensus annotation comes from og_family_term, membership from
 * og_family_member, and the per-gene homology hits from the per-species
 * <abbr>_nr / <abbr>_uniprot tables (same source the gene pages use).
 * ------------------------------------------------------------------------- */
$family = isset($_GET['family']) ? trim($_GET['family']) : (isset($_POST['family']) ? trim($_POST['family']) : '');

function gfr_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function gfr_src($s) {
    static $m = array('pfam' => 'Pfam', 'panther' => 'PANTHER', 'go' => 'GO', 'kegg' => 'KEGG');
    return isset($m[$s]) ? $m[$s] : strtoupper($s);
}

function gfr_tier_label($t) {
    if ($t === 'all')  return array('100% consensus', 'gfa-all');
    if ($t === 'ge80') return array('&ge;80% support', 'gfa-ge80');
    if ($t === 'ge50') return array('&ge;50% support', 'gfa-ge50');
    /* 'low' = 低于 50% 的弱证据（og_family_term_low），空心徽章。 */
    if ($t === 'low')  return array('below 50%', 'gfa-low');
    return array('weak', 'gfa-none');
}

/* og_family_term_low 在不在（离线 build_og_family_term_low.php 建的）。
   不在时不能印「一条都没有」——那会把「没查过」说成「确实没有」。同 genefamily.php。 */
function gfr_low_ready($conn) {
    static $ok = null;
    if ($ok === null) { $ok = (bool) @mysqli_query($conn, 'SELECT 1 FROM og_family_term_low LIMIT 1'); }
    return $ok;
}

/* 一个家族最多印多少条弱证据。大家族能到几千条（那个家族有上万个成员基因），
   全印出来既压不动也没人读；这里只印最强的这些，下面明写总数。 */
define('GFR_LOW_SHOWN', 50);

/* 一条 7 列的结果行。共识条目和低于 50% 的弱条目共用这一份渲染 ——
   两处各写一遍迟早会漂移，而"能不能一眼区分两类"正是这个改动的全部要点。 */
function gfr_term_row($t) {
    list($lab, $cls) = gfr_tier_label($t['tier']);
    $name = trim((string)$t['term_name']);
    $desc = trim((string)$t['term_desc']);
    $txt  = $name !== '' ? $name : $desc;
    /* 同 genefamily.php：$txt 随后要过 gfr_h()，写 &mdash; 会被再转义成
       字面量印出来，只能写 U+2014 本字。 */
    if ($name !== '' && $desc !== '' && stripos($desc, $name) === false) { $txt = $name . ' — ' . $desc; }
    $html  = "<tr>";
    $html .= "<td align='center'><span class='gfa-src gfa-src-" . gfr_h($t['source']) . "'>"
           . gfr_h(gfr_src($t['source'])) . "</span></td>";
    $html .= "<td align='center'><span class='gfa-term'>" . gfr_h($t['term']) . "</span>";
    if (trim((string)$t['category']) !== '') {
        $html .= "<br><span class='gfa-cat'>" . gfr_h($t['category']) . "</span>";
    }
    $html .= "</td>";
    $html .= "<td style='text-align:left'>" . gfr_h($txt) . "</td>";
    $html .= "<td align='center'>" . (int)$t['support'] . " / " . number_format((int)$t['n_genes']) . "</td>";
    $html .= "<td align='center'>" . number_format((float)$t['pct'], 1) . "%</td>";
    $html .= "<td align='center'>" . number_format((float)$t['pct_annot'], 1)
           . "%<br><span style='font-size:12px;color:#64748b'>of "
           . number_format((int)$t['n_annot']) . "</span></td>";
    $html .= "<td align='center'><span class='gfa-badge $cls'>$lab</span></td>";
    $html .= "</tr>";
    return $html;
}

$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) { die('Database connection failed.'); }
mysqli_set_charset($conn, 'utf8mb4');

$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
$page     = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($per_page <= 0) $per_page = 20;
if ($page <= 0) $page = 1;
$abbr_filter = isset($_GET['abbr']) ? trim($_GET['abbr']) : '';

/* ------------------------------------------------------------ family row -- */
$fam = null;
if ($family !== '') {
    $q = mysqli_query($conn, "SELECT * FROM og_family WHERE og = '"
         . mysqli_real_escape_string($conn, $family) . "' LIMIT 1");
    if ($q) { $fam = mysqli_fetch_assoc($q); }
}

/* ------------------------------------------------------- consensus terms -- */
$terms = array();
if ($fam) {
    $q = mysqli_query($conn, "SELECT source, term, term_name, term_desc, category, support, n_genes, n_annot,
                                     pct, pct_annot, tier
                              FROM og_family_term WHERE og = '"
                              . mysqli_real_escape_string($conn, $family) . "'
                              ORDER BY FIELD(tier,'all','ge80','ge50'), FIELD(source,'panther','pfam','go','kegg'),
                                       pct DESC, support DESC");
    while ($q && ($r = mysqli_fetch_assoc($q))) { $terms[] = $r; }
}
$strict = array(); $relaxed = array();
foreach ($terms as $t) { if ($t['tier'] === 'all') $strict[] = $t; else $relaxed[] = $t; }

/* -------------------------------------------- below the 50% cut-off ------- */
/* 用户的要求：一致性低于 50% 的注释要和 ≥50% 的**区分开**，但不能只说一句 No ——
   那样用户对这个家族一无所知。所以这里把弱条目也查出来，跟共识共用一个表，
   只是排在后面、用空心徽章、并加一行分隔说明。
   排序：同一个家族里 n_genes 相同，pct 与 support 完全同序，写 pct DESC 即可
   （最接近共识的排最前，那正是最值得看的一条）。
   上限 GFR_LOW_SHOWN 条 + 一个总数，页面重量有界。 */
$weak = array(); $weakTotal = 0;
if ($fam && gfr_low_ready($conn)) {
    $ogEsc = mysqli_real_escape_string($conn, $family);
    $qw = mysqli_query($conn, "SELECT COUNT(*) FROM og_family_term_low WHERE og = '$ogEsc'");
    if ($qw) { $weakTotal = (int)mysqli_fetch_row($qw)[0]; }
    if ($weakTotal > 0) {
        $qw = mysqli_query($conn, "SELECT source, term, term_name, term_desc, category, support, n_genes,
                                          n_annot, n_species, pct, pct_annot, 'low' AS tier
                                   FROM og_family_term_low WHERE og = '$ogEsc'
                                   ORDER BY pct DESC, support DESC, FIELD(source,'panther','pfam','go','kegg'), term
                                   LIMIT " . GFR_LOW_SHOWN);
        while ($qw && ($r = mysqli_fetch_assoc($qw))) { $weak[] = $r; }
    }
}

/* ------------------------------------------------------------ members ----- */
/* 列名一律带表名：按 Species 排序时要 LEFT JOIN abbr，而那张表自己也有一个叫
   abbr 的列，不限定表名会报 1052 ambiguous。 */
$where = "og_family_member.og = '" . mysqli_real_escape_string($conn, $family) . "'";
if ($abbr_filter !== '') { $where .= " AND og_family_member.abbr = '" . mysqli_real_escape_string($conn, $abbr_filter) . "'"; }

/* ========== 表头排序（服务端） ==========
 * 成员表是分页的（LIMIT $offset, $per_page），上面那张「一致同意的功能」表不是 ——
 * 所以排序只挂在成员表的表头，另一张表仍旧走客户端脚本。
 * 参数前缀用 'c_' 而不是 'c'：本页已经有 family / abbr 两个参数，留个下划线好认。
 *
 * Species 这一列显示的是拉丁名，库里存的是短码（AACUM 这种），两者次序不一样。
 * 按拉丁名排要连 abbr 表，而 abbr 是 utf8mb4_0900_ai_ci、og_family_member 是
 * utf8mb4_unicode_ci，直接 JOIN 会报 1267（非法混合排序规则）—— 这也是本文件开头
 * 就写明「abbr 的物种名要单独查」的原因。必须显式 COLLATE。
 * 这个 JOIN 只在真的按 Species 排时才加：本表 529 万行没有 og 索引，本来就要全表
 * 扫描 + filesort（实测 11 ms → 64 ms），不该让所有人替这一列买单。 */
require_once __DIR__ . '/includes/sort_head.php';
$__tie = array('og_family_member.abbr', 'og_family_member.gene');
$__sortKeys = array(
    'default' => 'og_family_member.abbr, og_family_member.gene',
    'species' => cnido_sort_txt('og_family_member.abbr'),
    'gene'    => cnido_sort_txt('og_family_member.gene'),
    'nr'      => cnido_sort_txt('og_family_member.nr_id'),
    'nrdesc'  => cnido_sort_txt('og_family_member.nr_desc'),
    /* UniProt 那一格印的是 uni_id + 截断到 60 字的 uni_desc，所以排序键只有 uni_id，
       没有单独的 desc 档 —— 表头挂不上去的键留着只会被误以为能用。 */
    'uni'     => cnido_sort_txt('og_family_member.uni_id'),
);
list($__cSort, $__cDir, $__cOrder) = cnido_sort_state($__sortKeys, 'default', $__tie, 'c_');
$__cJoin = '';
if ($__cSort === 'species') {
    $__cJoin = ' LEFT JOIN abbr sp_abbr'
             . ' ON sp_abbr.abbr1 = og_family_member.abbr COLLATE utf8mb4_unicode_ci';
    $__sortKeys['species'] = cnido_sort_txt('sp_abbr.species');
    list($__cSort, $__cDir, $__cOrder) = cnido_sort_state($__sortKeys, 'default', $__tie, 'c_');
}
$__cSortQs = cnido_sort_qs($__cSort, $__cDir, $__sortKeys, 'default', 'c_');

$total_records = 0;
$q = mysqli_query($conn, "SELECT COUNT(*) FROM og_family_member WHERE $where");
if ($q) { $total_records = (int)mysqli_fetch_row($q)[0]; }
$total_pages = $per_page > 0 ? (int)ceil($total_records / $per_page) : 1;
if ($total_pages > 0 && $page > $total_pages) { $page = $total_pages; }
$offset = ($page - 1) * $per_page;

$pageRows = array(); $abbrs = array();
$q = mysqli_query($conn, "SELECT og_family_member.og, og_family_member.abbr, og_family_member.gene,
                                 og_family_member.nr_id, og_family_member.nr_desc,
                                 og_family_member.uni_id, og_family_member.uni_desc
                          FROM og_family_member$__cJoin WHERE $where
                          ORDER BY $__cOrder LIMIT $offset, $per_page");
while ($q && ($r = mysqli_fetch_row($q))) { $pageRows[] = $r; $abbrs[$r[1]] = true; }

/* species names */
$spCache = array();
foreach (array_keys($abbrs) as $ab) {
    $qS = mysqli_query($conn, "SELECT species FROM abbr WHERE abbr1 = '"
          . mysqli_real_escape_string($conn, $ab) . "' LIMIT 1");
    $rS = $qS ? mysqli_fetch_row($qS) : null;
    $spCache[$ab] = $rS && $rS[0] !== null ? $rS[0] : $ab;
}

/* only show the UniProt column when this family actually has Swiss-Prot hits */
$showUni = false;
$qU = mysqli_query($conn, "SELECT 1 FROM og_family_member
                           WHERE og = '" . mysqli_real_escape_string($conn, $family) . "'
                             AND uni_id IS NOT NULL LIMIT 1");
if ($qU && mysqli_num_rows($qU) > 0) { $showUni = true; }

/* $keep 是已经 HTML 转义的（页面上 <?= $keep ?> 直接进属性），$__filterQs 没有 ——
   要交给 cnido_sort_link，它自己会 htmlspecialchars，喂给它带 &amp; 的串会变成 &amp;amp;。 */
$__filterQs = 'family=' . urlencode($family)
            . ($abbr_filter !== '' ? '&abbr=' . urlencode($abbr_filter) : '');
$keep = str_replace('&', '&amp;', $__filterQs . ($__cSortQs !== '' ? '&' . $__cSortQs : ''));
?>

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Gene Family</b></legend>

<?php if (!$fam) { ?>
    <?php /* 不带 ?family= 打开本页时 $family 是空串，原来会印成
             「Gene family **  ** was not found in this release」——一个没有主语的句子。
             空串是「没给」，不是「给了但查不到」，两句分开说。 */ ?>
    <p class="gd-notice gd-warn">
        <?php if ($family === ''): ?>
        No gene family was given. Pick one from the
        <a href="genefamily.php">gene family browser</a>.
        <?php else: ?>
        Gene family <strong><?= gfr_h($family) ?></strong> was not found in this release.
        <a href="genefamily.php">Back to the gene family browser</a>.
        <?php endif; ?>
    </p>
<?php } else { ?>

<p class="paleo-intro">
    <a href="genefamily.php">&larr; Back to the gene family browser</a>
</p>

<div class="fam-hero">
    <?php
    /* 家族级的注释结论。原来它是页头里那颗琥珀药丸；药丸外形会让读者以为能点，
       所以整句挪到它真正描述的那张表头上（见下面的 .fam-verdict）。
       $n_rel 曾经在这里被算出来却从没被用过，一并去掉。 */
    $n_all  = (int)$fam['n_terms_all'];
    $n_any  = (int)$fam['n_terms'];
    $n_seq   = (int)$fam['n_seqs'];
    $n_gene  = (int)$fam['n_genes'];
    $best    = number_format((float)$fam['best_pct'], 1);
    /* 没有共识（best_* 都是空的）时，最值得说的那个数就落在弱证据那边。
       这两处文案原来只会说「没有注释/—」，加了 og_family_term_low 之后那是错的：
       家族还有弱条目可看。$weakBest 只在真查过、真有时才不是 null。 */
    $weakBest = ($n_any === 0 && $weak) ? number_format((float)$weak[0]['pct'], 1) : null;
    ?>
    <div class="fam-hero-top">
        <div>
            <span class="fam-kicker">Orthogroup</span>
            <h1><?= gfr_h($fam['og']) ?></h1>
        </div>
        <?php /* 主操作。副行只说树里有什么，不承诺「就在下面」—— 它开的是另一个文档。 */ ?>
        <a class="fam-tree" href="/genetree/?family=<?= urlencode($fam['og']) ?>"
           title="Open an interactive gene tree of this family's members">
            <span class="fam-tree-ic" aria-hidden="true">&#127795;</span>
            <span class="fam-tree-tx">
                <span class="fam-tree-t">View gene tree</span>
                <span class="fam-tree-s">Interactive tree, one tip per member sequence</span>
            </span>
        </a>
    </div>

    <?php
        /* OrthoFinder 那次运行带了 5 个**非刺胞动物**外群 —— Bolinopsis microptera
           （栉水母）、Corticium candelabrum / Halichondria panicea /
           Oscarella lobularis / Sycon ciliatum（海绵）—— 在 og_family_member 里
           统一记成 abbr='OUT'。它是全表唯一不在 abbr 表里的 abbr（85,455 行），
           所以上面 $spCache 的兜底会把它原样当成物种名印出来，而
           speciesinfo.php?species=OUT 是个空壳、jbrowse/OUT/ 是 404。
           $fam['n_species'] 把它算进了「Species」这一格，于是那个数对刺胞动物
           物种数是多算的。这里单独查一次本family有没有它。 */
        $__outAbbr = 'OUT';
        $__nOut = 0;
        $__qOut = mysqli_query($conn, "SELECT 1 FROM og_family_member
                                        WHERE og = '" . mysqli_real_escape_string($conn, $family)
                                     . "' AND abbr = '" . mysqli_real_escape_string($conn, $__outAbbr) . "' LIMIT 1");
        if ($__qOut && mysqli_fetch_row($__qOut)) { $__nOut = 1; }
    ?>

    <dl class="fam-facts">
        <div class="fam-fact"><dt>Member genes</dt><dd><?= number_format($n_gene) ?></dd></div>
        <div class="fam-fact"><dt>Species<?php if ($__nOut): ?> <span style="letter-spacing:0;font-weight:400">(cnidarian)</span><?php endif; ?></dt>
            <dd><?= (int)$fam['n_species'] - $__nOut ?><?php if ($__nOut): ?><span style="display:block;font-size:12px;font-weight:400;color:#64748b;letter-spacing:0;margin-top:2px">+ 1 non-cnidarian outgroup</span><?php endif; ?></dd></div>
        <div class="fam-fact"><dt>Sequences</dt><dd><?= number_format($n_seq) ?></dd></div>
        <div class="fam-fact">
            <dt><?= $n_all > 0 ? 'Agreed on by every member' : 'Best annotation support' ?></dt>
            <dd><?php
                if ($n_all > 0)      { echo $n_all . ($n_all === 1 ? ' term' : ' terms'); }
                elseif ($n_any > 0)  { echo $best . '%'; }
                elseif ($weakBest !== null) {
                    echo $weakBest . '% <span style="display:block;font-size:12px;font-weight:400;'
                       . 'color:#64748b;letter-spacing:0;margin-top:2px">below the 50% cut-off</span>';
                }
                else                 { echo '&mdash;'; }
            ?></dd>
        </div>
    </dl>

    <div class="fam-dl">
        <span class="fam-dl-lbl">Downloads</span>
        <a class="fam-dl-a" target="_blank" rel="noopener" href="/genetree/?family=<?= urlencode($fam['og']) ?>&amp;dl=aln"
           title="Download the MAFFT alignment of this family as gzipped FASTA">&#11015; Alignment (FASTA.gz)</a>
        <a class="fam-dl-a" target="_blank" rel="noopener" href="/genetree/?family=<?= urlencode($fam['og']) ?>&amp;dl=seq"
           title="Download the same alignment with gaps stripped, as unaligned FASTA">&#11015; Sequences (FASTA)</a>
    </div>

    <?php if ($n_seq > $n_gene) { ?>
    <p class="fam-note">
        <?= number_format($n_seq - $n_gene) ?> sequences in this family share a gene identifier with
        another member, so the <?= number_format($n_seq) ?> sequences above are
        <?= number_format($n_gene) ?> distinct genes &mdash; none is counted twice.
    </p>
    <?php } ?>
</div>

<!-- ---------------------------------------------- consensus annotation --- -->
<div class="table-container" style="margin-bottom:26px">
    <div style="padding:16px 20px 6px 20px">
        <h3 style="margin:0 0 10px 0;font-size:18px;color:#1e293b">Consensus functional annotation</h3>
        <?php /* 家族级结论，原来在页头那颗琥珀药丸里，挪到它描述的这张表头上。 */ ?>
        <div class="fam-verdict<?= ($n_all === 0 && $n_any === 0) ? ' fam-verdict-none' : '' ?>">
            <?php if ($n_all > 0) { ?>
                <b><?= $n_all ?> term<?= $n_all === 1 ? '' : 's' ?> agreed on by every member gene</b>
                &mdash; listed below with the number of members carrying each.
            <?php } elseif ($n_any > 0) { ?>
                <b>No term is shared by every member gene</b> &mdash; the best-supported annotation recorded
                for this family covers <?= $best ?>% of the <?= number_format($n_gene) ?> members.
            <?php } elseif ($weakBest !== null) { ?>
                <b>No consensus annotation</b> &mdash; no term is carried by half of the
                <?= number_format($n_gene) ?> member genes. The strongest term recorded for this family
                covers <?= $weakBest ?>%, so it is listed below the cut-off, marked as weaker evidence.
            <?php } else { ?>
                No consensus annotation is available for this family.
            <?php } ?>
        </div>
        <p style="margin:0;font-size:16px;color:#475569">
            <b>Support</b> counts the member genes carrying the term.
            <b>% of genes</b> is that count over all <?= number_format((int)$fam['n_genes']) ?> members &mdash;
            the strict reading of &ldquo;the whole family agrees&rdquo;.
            <b>% annotated</b> is the same count over only those members for which this database has a
            <?= '' ?>prediction of that type, which shows how uniform the evidence is where evidence exists.
        </p>
    </div>
    <div class="table-scroll-container" style="max-height:none">
        <table class="gridtable">
            <tr>
                <th width="9%">Source</th>
                <th width="11%">Term</th>
                <?php /* 名称/描述是自由文本，居中难看，左对齐（对应的 td 本来就是内联左对齐）。 */ ?>
                <th width="36%" class="tal">Name / description</th>
                <th width="9%">Support</th>
                <th width="9%">% of genes</th>
                <th width="9%">% annotated</th>
                <th width="13%">Consistency</th>
            </tr>
            <?php if (!$terms && !$weak) { ?>
                <tr><td colspan="7" style="color:#64748b;text-align:left">
                    <?php if (gfr_low_ready($conn)) { ?>
                        No Pfam / PANTHER / GO / KEGG term is shared by two or more of this family's member
                        genes, so it carries no function the family can be said to agree on at any level.
                        Small, fast-evolving families often end up here.
                    <?php } else { ?>
                        No Pfam / PANTHER / GO / KEGG term is carried by at least half of this family's member
                        genes. Large, fast-evolving families often end up here.
                    <?php } ?>
                </td></tr>
            <?php } ?>
            <?php foreach (array_merge($strict, $relaxed) as $t) { echo gfr_term_row($t); } ?>
            <?php if ($weak) {
                /* 分隔行：把两类清清楚楚地切开，读者不会把空心徽章误当共识。 */
                ?>
                <tr><td colspan="7" class="gfr-low-hd">
                    <b>Below the 50% cut-off.</b>
                    <?php if ($terms) { ?>
                        These terms are carried by fewer than half of the family's member genes, so they are
                        <b>not</b> a consensus &mdash; they are shown as the next-best evidence about what this
                        family does. The rows above are the reliable part.
                    <?php } else { ?>
                        No term reaches half of this family's member genes, so it has <b>no consensus
                        annotation</b>. These are the strongest terms it does carry, with the fraction of
                        member genes behind each one &mdash; evidence to read with care, but enough to tell
                        what the family is like.
                    <?php } ?>
                </td></tr>
                <?php foreach ($weak as $t) { echo gfr_term_row($t); } ?>
                <?php if ($weakTotal > count($weak)) { ?>
                    <tr><td colspan="7" class="gfr-low-hd">
                        Showing the strongest <?= number_format(count($weak)) ?> of
                        <b><?= number_format($weakTotal) ?></b> terms below the 50% cut-off.
                    </td></tr>
                <?php } ?>
            <?php } ?>
        </table>
    </div>
</div>

<!-- ------------------------------------------------------- members ------- -->
<div class="pagination-container">
    <div class="pagination-info">
        <div class="total-records">
            &#128202; Total members in <strong><i><?= gfr_h($family) ?></i></strong>: <?= number_format($total_records) ?>
            <?php if ($abbr_filter !== '') { ?>
                <span style="color:#0369a1">(filtered to <b><?= gfr_h($abbr_filter) ?></b>
                &middot; <a href="?family=<?= urlencode($family) ?>">show all species</a>)</span>
            <?php } ?>
        </div>
        <div class="per-page-selector">
            <span>Show:</span>
            <select onchange="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?<?= $keep ?>&amp;per_page='+this.value+'&amp;page=1'">
                <?php foreach (array(10, 20, 50, 100) as $o) {
                    echo "<option value='$o' " . ($o == $per_page ? 'selected' : '') . ">$o</option>";
                } ?>
            </select>
            <span>genes per page</span>
        </div>
    </div>
</div>

<div class="table-container">
    <p class="paleo-intro" style="font-size:16px;color:#475569">
        Column guide: <b>Top NCBI-NR hit</b> and <b>Top UniProt hit</b> are the closest characterised sequences
        found by homology search &mdash; they are <i>not</i> identifiers of the CnidoSite gene itself. Where a
        species has no Swiss-Prot hit above threshold the UniProt column is shown as &ndash;. Click a gene ID for
        its full annotation page.
    </p>
    <div class="table-scroll-container">
        <table class="gridtable">
            <tr>
                <th><?= cnido_sort_link('species', 'Species', $__cSort, $__cDir, $__filterQs, 'c_') ?></th>
                <th><?= cnido_sort_link('gene', 'Gene ID', $__cSort, $__cDir, $__filterQs, 'c_') ?></th>
                <th width="16%"><?= cnido_sort_link('nr', 'Top NCBI-NR hit<br><span style="font-weight:400;font-size:15px">(accession)</span>', $__cSort, $__cDir, $__filterQs, 'c_') ?></th>
                <?php /* 这两列装的都是「命中描述」型自由文本（NR 描述可能很长，UniProt 那格
                         是号 + 小字描述两行），居中会变成参差的居中块，显式左对齐。 */ ?>
                <th width="<?= $showUni ? '28%' : '44%' ?>" class="tal"><?= cnido_sort_link('nrdesc', 'Top NCBI-NR hit description<br><span style="font-weight:400;font-size:15px">(source organism)</span>', $__cSort, $__cDir, $__filterQs, 'c_') ?></th>
                <?php if ($showUni) { ?>
                <th width="20%" class="tal"><?= cnido_sort_link('uni', 'Top UniProt hit<br><span style="font-weight:400;font-size:15px">(Swiss-Prot)</span>', $__cSort, $__cDir, $__filterQs, 'c_') ?></th>
                <?php } ?>
                <th>Genome browser</th>
            </tr>
            <?php
            foreach ($pageRows as $row) {
                $spName = $spCache[$row[1]];
                /* 外群不是本站物种：物种页是空壳、JBrowse 目录不存在，
                   这两列给链接就是两个死链，改印外群标签和纯文本。 */
                $isOut = ($row[1] === $__outAbbr);
                $acc = (string)$row[3];
                $des = trim((string)$row[4]);
                if ($acc !== '' && strpos($des, $acc) === 0) { $des = ltrim(substr($des, strlen($acc))); }
                echo "<tr align='center'>";
                if ($isOut) {
                    echo "<td><i>" . gfr_h($row[1]) . "</i><br>"
                       . "<span style='font-size:13px;color:#64748b'>non-cnidarian outgroup</span></td>";
                    echo "<td>" . gfr_h($row[2]) . "</td>";
                } else {
                    echo "<td><a href=\"./speciesinfo.php?species=" . urlencode($row[1]) . "\"><i>"
                       . gfr_h($spName) . "</i></a></td>";
                    echo "<td><a href=\"gene_detail.php?gene=" . urlencode($row[2]) . "&species="
                       . urlencode($spName) . "\">" . gfr_h($row[2]) . "</a></td>";
                }
                echo "<td>" . ($acc === '' || $acc === '-'
                        ? '<span class="gd-na">none</span>'
                        : "<a href=\"https://www.ncbi.nlm.nih.gov/protein/" . urlencode($acc)
                          . "\" target='_blank'>" . gfr_h($acc) . "</a>") . "</td>";
                echo "<td style='text-align:left'>" . gfr_h($des) . "</td>";
                if ($showUni) {
                    $uid = (string)$row[5];
                    if ($uid !== '') {
                        echo "<td class=\"tal\"><a href=\"https://www.uniprot.org/uniprotkb/" . urlencode($uid)
                           . "\" target='_blank'>" . gfr_h($uid)
                           . "</a><br><span style='font-size:15px;color:#64748b'>"
                           . gfr_h(mb_substr((string)$row[6], 0, 60)) . "</span></td>";
                    } else {
                        echo "<td class=\"tal\"><span class='gd-na'>&ndash;</span></td>";
                    }
                }
                if ($isOut) {
                    echo "<td><span class='gd-na' title='no genomic coordinates: CnidoSite does not host a genome for this outgroup'>&ndash;</span></td>";
                } else {
                    echo "<td><a href=\"./jbrowse/index.html?data=" . urlencode($row[1]) . "&gene="
                       . urlencode($row[2]) . "\">JBrowse</a></td>";
                }
                echo "</tr>";
            }
            if (empty($pageRows)) {
                echo "<tr><td colspan='" . ($showUni ? 6 : 5) . "' style='color:#64748b'>No member of this family matches the current filter.</td></tr>";
            }
            ?>
        </table>
    </div>
</div>

    <?php if ($total_pages > 0): /* 命中 0 条时不渲染分页条：$total_pages
         是 0，页码循环一次都不进，几个按钮和「of 0 pages」却照旧印出来，
         全部指向自己。站内约定见 browse.php / go_result.php。 */ ?>
<div class="pagination-nav">
    <a href="?<?= $keep ?>&amp;per_page=<?= $per_page ?>&amp;page=1" class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">&laquo; First</a>
    <a href="?<?= $keep ?>&amp;per_page=<?= $per_page ?>&amp;page=<?= max(1, $page - 1) ?>" class="page-btn <?= ($page == 1) ? 'disabled' : '' ?>">&lsaquo; Previous</a>
    <?php
    $start_page = max(1, $page - 3);
    $end_page   = min($total_pages, $page + 3);
    if ($start_page > 1) {
        echo '<a href="?' . $keep . '&amp;per_page=' . $per_page . '&amp;page=1" class="page-btn">1</a>';
        if ($start_page > 2) { echo '<span class="page-btn disabled">...</span>'; }
    }
    for ($i = $start_page; $i <= $end_page; $i++) {
        echo '<a href="?' . $keep . '&amp;per_page=' . $per_page . '&amp;page=' . $i . '" class="page-btn '
           . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
    }
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) { echo '<span class="page-btn disabled">...</span>'; }
        echo '<a href="?' . $keep . '&amp;per_page=' . $per_page . '&amp;page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
    }
    ?>
    <a href="?<?= $keep ?>&amp;per_page=<?= $per_page ?>&amp;page=<?= min($total_pages, $page + 1) ?>" class="page-btn <?= ($page >= $total_pages) ? 'disabled' : '' ?>">Next &rsaquo;</a>
    <a href="?<?= $keep ?>&amp;per_page=<?= $per_page ?>&amp;page=<?= $total_pages ?>" class="page-btn <?= ($page >= $total_pages) ? 'disabled' : '' ?>">Last &raquo;</a>
</div>

<div class="go-to-page">
    <span>Go to page:</span>
    <input type="number" id="gotoPage" min="1" max="<?= max(1,$total_pages) ?>" value="<?= $page ?>">
    <button onclick="window.location.href='?<?= $keep ?>&amp;per_page=<?= $per_page ?>&amp;page='+document.getElementById('gotoPage').value">Go</button>
    <span>of <?= max(1, $total_pages) ?> pages</span>
</div>    <?php endif; /* $total_pages > 0 */ ?>


<?php } /* end if ($fam) */ ?>

</div>
</div>
</div>

<?php
$conn->close();
include "Webpage_components.php";
print $footer;
?>
</body>
</html>
