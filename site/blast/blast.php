<?php
/* ---------------------------------------------------------------------
 * BLAST 数据库下拉框改为服务端渲染，并新增「全部数据库」选项。
 *
 * 原先 <select name="datalib"> 里只有一个 printOptions() 调用，选项由
 * js/DynamicOptionList.js 在 onLoad 时按一份硬编码的名单重建：名单不会随新增
 * 物种更新，脚本一旦失效下拉框就是空的，而且一次只能检索一个库。
 *
 * 现在直接从 blast/db/ 目录读出真实存在的库（.pdb 索引 = 蛋白库，
 * .ndb 索引 = 核苷酸库），并为每个程序族提供一个「全部数据库」选项 ——
 * 对应审稿意见 Referee 3 point 11(b)。legacy blastall 的 -d 接受以空格分隔的
 * 库列表，所以「全部」是一次调用完成，不需要循环几百次。
 *
 * 下拉框里显示的是拉丁学名：库文件名（Acropora_acuminata）只是数据键，
 * 物种缩写/下划线形式不对外呈现，cnido_latin() 负责翻译。
 *
 * 2026-09-21 补齐 + 修 bug：
 *   · 库比物种少 —— 蛋白 146/148、cds 144/148、transcript 143/148。
 *     蛋白缺的两个（Acropora_microphthalma、Porites_australiensis）源文件
 *     一直挂在 download/ 下可下载，只是当年没建索引，现已补建 → 148/148。
 *     核苷酸补不了：真正缺的是序列本身（见下）。
 *     建库脚本 /home/jackie/cnidosite-work/blast/build_blast_dbs.sh。
 *   · 「全部数据库」从上线起只搜了一个库（/usr/bin/blastall 包装脚本分词
 *     吃掉库列表），详见 blast_result.php 里那段注释。现在真的搜全部，
 *     代价是一次约 1–2 分钟，所以下面加了一句耗时提示。
 * --------------------------------------------------------------------- */
require_once __DIR__ . '/../includes/state.php';

$__pepDb = array(); $__nucDb = array();
foreach ((array)@scandir(__DIR__ . '/db') as $__f) {
    if (substr($__f, -4) === '.pdb')      { $__pepDb[] = substr($__f, 0, -4); }
    elseif (substr($__f, -4) === '.ndb')  { $__nucDb[] = substr($__f, 0, -4); }
}
sort($__pepDb); sort($__nucDb);
$__progDb = array(
    'blastp'  => array('list' => $__pepDb, 'all' => '__ALL_PEP__', 'kind' => 'protein'),
    'blastx'  => array('list' => $__pepDb, 'all' => '__ALL_PEP__', 'kind' => 'protein'),
    'blastn'  => array('list' => $__nucDb, 'all' => '__ALL_NUC__', 'kind' => 'nucleotide'),
    'tblastn' => array('list' => $__nucDb, 'all' => '__ALL_NUC__', 'kind' => 'nucleotide'),
    'tblastx' => array('list' => $__nucDb, 'all' => '__ALL_NUC__', 'kind' => 'nucleotide'),
);

/* ---------------------------------------------------------------------
 * GET 引导：?sp=<abbr1>&gid=<gene> —— 取该基因的序列，把查询框和库预填好。
 *
 * gene_detail.php 的「What you can do with this gene」面板链到这里，目的是让
 * 读者落在一个「已经填好、直接按 Run」的表单上，而不是空表单。
 *
 * 为什么**只预填、不自动提交**：一次比对 1–2 分钟（选「全部数据库」更久），
 * 而带参数的 URL 会被爬虫、链接预览和聊天工具抓过去 —— 一个 GET 就能烧掉
 * 一整轮 CPU。跑不跑由读者决定。
 *
 * 也刻意不在 blast_result.php 里加 $_GET 回退：那边没有 qnum 时会落进 else
 * 分支，把 program / query 全当空串，写出一个空的 tmp/<qnum>.seq 然后照样执行
 * blastall —— 白跑一次，还在 tmp/ 里留垃圾。
 *
 * 序列优先取 protein_seq（→ blastp + 该物种的 .pep 库），没有蛋白就退回
 * transcript_seq（→ blastn + .cds / .transcript 库）。库文件名是拉丁名下划线
 * 形式，要对着 blast/db/ 里真实存在的库校验过才预选，否则宁可保持默认。
 * ------------------------------------------------------------------ */
$__preSeq = ''; $__preDb = ''; $__preProg = ''; $__preMsg = ''; $__preErr = '';
if (isset($_GET['sp']) && isset($_GET['gid'])) {
    $__sp  = trim((string)$_GET['sp']);
    $__gid = trim((string)$_GET['gid']);
    if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $__sp) || $__gid === '' || strlen($__gid) > 128) {
        $__preErr = 'The species or gene ID in this link is not in a form this page accepts.';
    } else {
        $__conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
        if (!$__conn || $__conn->connect_error) {
            $__preErr = 'Could not reach the sequence database to prefill the query.';
        } else {
            $__latin = '';
            $__e = $__conn->real_escape_string($__sp);
            if ($__r = $__conn->query("SELECT species FROM abbr WHERE abbr1 = '$__e' OR abbr = '$__e' LIMIT 1")) {
                if ($__row = $__r->fetch_row()) { $__latin = trim((string)$__row[0]); }
            }
            $__tbl = $__sp . '_seq';
            $__has = false;
            if ($__r = $__conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() "
                                    . "AND table_name = '" . $__conn->real_escape_string($__tbl) . "' LIMIT 1")) {
                $__has = (bool)$__r->fetch_row();
            }
            if (!$__has) {
                $__preErr = 'This species has no sequence table, so no query could be prefilled.';
            } else {
                $__in = array();
                foreach (cnido_gene_id_forms($__gid) as $__v) {
                    $__in[] = "'" . $__conn->real_escape_string($__v) . "'";
                }
                $__in = implode(',', $__in);
                $__q = $__conn->query("SELECT gene, protein, protein_seq, transcript, transcript_seq "
                                    . "FROM `$__tbl` WHERE gene IN ($__in) OR protein IN ($__in) LIMIT 1");
                $__hit = ($__q ? $__q->fetch_assoc() : null);
                if (!$__hit) {
                    $__preErr = 'Nothing matching "' . htmlspecialchars($__gid, ENT_QUOTES, 'UTF-8')
                              . '" was found in the ' . htmlspecialchars($__tbl, ENT_QUOTES, 'UTF-8') . ' table.';
                } else {
                    $__lab = preg_replace('/\s+/', '_', trim((string)$__hit['gene']));
                    if ($__lab === '') { $__lab = $__gid; }
                    $__prot     = trim((string)$__hit['protein_seq']);
                    $__nuc      = trim((string)$__hit['transcript_seq']);
                    $__latinKey = str_replace(' ', '_', $__latin);
                    if ($__prot !== '') {
                        $__preSeq  = '>' . $__lab . "\n" . wordwrap($__prot, 60, "\n", true);
                        $__preProg = 'blastp';
                        if ($__latinKey !== '' && in_array($__latinKey . '.pep', $__pepDb, true)) {
                            $__preDb = $__latinKey . '.pep';
                        }
                    } elseif ($__nuc !== '') {
                        $__preSeq  = '>' . $__lab . "\n" . wordwrap($__nuc, 60, "\n", true);
                        $__preProg = 'blastn';
                        foreach (array('.cds', '.transcript') as $__sfx) {
                            if ($__latinKey !== '' && in_array($__latinKey . $__sfx, $__nucDb, true)) {
                                $__preDb = $__latinKey . $__sfx;
                                break;
                            }
                        }
                    } else {
                        $__preErr = 'The sequence stored for ' . htmlspecialchars($__gid, ENT_QUOTES, 'UTF-8')
                                  . ' is empty in the ' . htmlspecialchars($__tbl, ENT_QUOTES, 'UTF-8') . ' table.';
                    }
                    if ($__preSeq !== '') {
                        $__preMsg = 'Query prefilled from <code>' . htmlspecialchars($__tbl, ENT_QUOTES, 'UTF-8')
                                  . '</code> &mdash; ' . htmlspecialchars($__lab, ENT_QUOTES, 'UTF-8')
                                  . ' (' . number_format(strlen($__preSeq)) . ' characters). Pick a database and press '
                                  /* 原来给的理由是「因为整库搜索不到一分钟就不自动开跑」——
                                     快恰恰是开跑的理由，不是不开跑的理由，这句读不通。真正
                                     的理由是别让一个深链替访客花掉服务器时间。 */
                                  . '<b>Start BLAST Search</b>: the search is not started automatically, so that opening this link never spends server time on a run you did not ask for.';
                    }
                }
            }
            $__conn->close();
        }
    }
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<title>BLAST Search - CnidoSite</title>

<style>
<?php /* BLAST页面专用样式 - 不影响全局CSS */ ?>
.blast-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.blast-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.blast-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.tools-badge {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

<?php /* 表单容器美化 */ ?>
.blast-form-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 35px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
}

.form-section {
    margin-bottom: 35px;
    padding-bottom: 25px;
    border-bottom: 1px solid #e2e8f0;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title::before {
    content: "⚙️";
    font-size: 24px;
}

<?php /* 表单行样式 */ ?>
.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
    align-items: center;
}

.form-group {
    flex: 1;
    min-width: 250px;
}

.form-label {
    display: block;
    font-size: 15px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
}

.form-select, .form-input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 15px;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s ease;
}

.form-select:focus, .form-input:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

<?php /* 「全部数据库」耗时提示 */ ?>
.blast-hint {
    margin: 12px 0 0 0;
    padding: 10px 14px;
    background: #f1f5f9;
    border-left: 3px solid #94a3b8;
    border-radius: 0 6px 6px 0;
    font-size: 16px;
    line-height: 1.6;
    color: #475569;
}

<?php /* 文本区域美化 */ ?>
.textarea-container {
    margin-bottom: 20px;
}

.textarea-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.example-link {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
}

.example-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.form-textarea {
    width: 100%;
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    background: #f8fafc;
    color: #1e293b;
    resize: vertical;
    transition: all 0.3s ease;
}

.form-textarea:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background: white;
}

<?php /* 文件上传美化 */ ?>
.file-upload {
    margin-top: 10px;
}

.file-input-wrapper {
    position: relative;
    display: inline-block;
    width: 100%;
}

.file-input {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
}

.file-input-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px 20px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    color: #475569;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.file-input-label:hover {
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    border-color: #94a3b8;
    color: #475569;
}

<?php /* 复选框美化 */ ?>
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.checkbox-input {
    width: 18px;
    height: 18px;
    accent-color:#1d4ed8;
}

.checkbox-label {
    font-size: 15px;
    color: #475569;
    cursor: pointer;
}

<?php /* 按钮美化 */ ?>
.button-group {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.btn-submit, .btn-reset {
    flex: 1;
    padding: 14px 30px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-submit {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
}

.btn-reset {
    background: #f8fafc;
    color: #64748b;
    border: 2px solid #e2e8f0;
}

.btn-reset:hover {
    background: #f1f5f9;
    color: #475569;
    border-color: #cbd5e1;
}

<?php /* 参数选项样式 */ ?>
.param-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.param-group {
    background: #f8fafc;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.param-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.param-title::before {
    content: "📊";
    font-size: 18px;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .blast-header {
        padding: 20px;
    }
    
    .blast-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .blast-form-container {
        padding: 25px 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .form-group {
        min-width: 100%;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .param-options {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
	<?php /* 程序（blastp/blastn/...）决定可用的库。选项已由 PHP 渲染，
	   这段只负责按程序族隐藏不匹配的库；脚本不执行时下拉框也不会是空的。 */ ?>
	function blastFilterDbs() {
		var f = document.forms['blast'];
		if (!f || !f['program'] || !f['datalib']) { return; }
		var prog = f['program'].options[f['program'].selectedIndex].value;
		var sel = f['datalib'], first = null;
		for (var i = 0; i < sel.options.length; i++) {
			var o = sel.options[i];
			var progs = (o.getAttribute('data-prog') || '').split(' ');
			var ok = (progs.indexOf(prog) !== -1);
			o.disabled = !ok;
			o.hidden = !ok;
			if (ok && first === null) { first = o; }
		}
		var cur = sel.options[sel.selectedIndex];
		if (first !== null && (!cur || cur.disabled)) { first.selected = true; }
	}
		  function prexample()
	  {
		<?php /* 例子换成 Nematostella vectensis 的 XP_048587815.1（homeobox 转录因子，
		   233 aa，NVECT_ipr 里挂着 5 条 InterPro 注释）。原来的 s0340.g1.t1 是
		   Acropora 的序列，本站默认物种已是 Nematostella vectensis，例子跟着换。
		   序列取自 BLAST 库 blast/db/Nematostella_vectensis.pep 里的真实记录
		   （blastdbcmd -entry all 按 XP_048587815.1 取出的那一条），点 Example
		   再提交会命中它自己，正好当自检用。 */ ?>
		var id = ">XP_048587815.1\n";
		id+= "MNKTTQIIDFHGILNEYRGSQRMRRVRRAVWSRGPLHTPSTRPPLVTCIRCTDCDESLDVKCYVREGRVFCPSDYFRRFGPKCASCNKDIQPSQMVHKVDSNVYHITCLSCVTCQQQLETRDEFFLLEDGKVICRNDYGDRDEDHIGIRLNTGLIAIDWHTTANSPGSDSSRSKRARTFISNDQLAFLKVAYASSPKTTLRDRERIAKETGLDMRVVQVWFQNRRAKDKRLSS";
        document.blast.query.value = id;
	  }
</script>
</head>
<body onLoad="blastFilterDbs();">
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
            <li><a href="#">Phenotype</a>
				<ul><li><a href="/phenotype.php?class=all">All</a></li>
					<li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li>
					<li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li>
					<li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li>
					<li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li>
					<li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li>
				</ul>
			</li>
            <li><a href="#" class="current">Tools</a>
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>BLAST Sequence Search</b></legend>
<p class="paleo-intro">Search for similar sequences in Cnidaria genomes and transcriptomes using BLAST algorithms.</p>

<?php /* 深链进来时（gene_detail.php 的工具面板）把预填结果说清楚：填了哪个号、
        取的是哪张表、为什么没有自动开跑。查不到时也明说，否则读者会以为是自己
        按错了。 */ ?>
<?php if ($__preErr !== '' || $__preMsg !== ''): ?>
<div style="background:<?= $__preErr !== '' ? '#fffbeb' : '#f0f9ff' ?>;border:1px solid <?= $__preErr !== '' ? '#fde68a' : '#bae6fd' ?>;border-left:4px solid <?= $__preErr !== '' ? '#f59e0b' : '#0ea5e9' ?>;border-radius:10px;padding:12px 16px;margin:0 0 14px;font-size:16px;line-height:1.7;color:#334155">
    <?php if ($__preErr !== ''): ?>
        <b>Could not prefill the query.</b> <?= $__preErr ?>
    <?php else: ?>
        <b>Sequence loaded.</b> <?= $__preMsg ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php /* 表单容器 */ ?>
<div class="blast-form-container">
    <form name="blast" action="blast_result.php" method="post" encType="multipart/form-data">
        
        <?php /* BLAST程序选择 */ ?>
        <div class="form-section">
            <div class="section-title">BLAST Program & Database</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">BLAST Program</label>
                    <select name="program" class="form-select" onchange="blastFilterDbs()">
                        <option value="blastp"<?= $__preProg === 'blastn' ? '' : ' selected="selected"' ?>>blastp (Protein-Protein)</option>
                        <option value="blastn"<?= $__preProg === 'blastn' ? ' selected="selected"' : '' ?>>blastn (Nucleotide-Nucleotide)</option>
                        <option value="blastx">blastx (Translated Nucleotide-Protein)</option>
                        <option value="tblastn">tblastn (Protein-Translated Nucleotide)</option>
                        <option value="tblastx">tblastx (Translated Nucleotide-Translated Nucleotide)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Search Database</label>
                    <select name="datalib" class="form-select" id="blast-datalib">
                        <?php
                        /* 三个核酸程序（blastn / tblastn / tblastx）用的是同一个
                           「全部核酸库」值 __ALL_NUC__，原来按程序各印一次，下拉框里
                           就有三条一模一样的 "* All 287 nucleotide databases"，而且它们的
                           value 也完全相同 —— 重复项没有任何意义。按 kind 归并成一条，
                           data-prog 收齐该 kind 下的全部程序名，JS 按程序过滤照旧。 */
                        $__allSeen = array();
                        foreach ($__progDb as $__pg => $__cfg):
                            if (isset($__allSeen[$__cfg['kind']])) { continue; }
                            $__allSeen[$__cfg['kind']] = true;
                            $__allProg = array();
                            foreach ($__progDb as $__pg2 => $__cfg2) {
                                if ($__cfg2['kind'] === $__cfg['kind']) { $__allProg[] = $__pg2; }
                            }
                        ?>
                            <option value="<?= htmlspecialchars($__cfg['all']) ?>" data-prog="<?= htmlspecialchars(implode(' ', $__allProg)) ?>"<?= (in_array('blastp', $__allProg, true) && $__preDb === '') || ($__preDb === $__cfg['all']) ? ' selected="selected"' : '' ?>>* All <?= count($__cfg['list']) ?> <?= htmlspecialchars($__cfg['kind']) ?> databases</option>
                        <?php endforeach; ?>
                        <?php foreach (array('protein' => $__pepDb, 'nucleotide' => $__nucDb) as $__kind => $__list):
                              $__progs = array();
                              foreach ($__progDb as $__pg => $__cfg) { if ($__cfg['kind'] === $__kind) { $__progs[] = $__pg; } } ?>
                            <?php foreach ($__list as $__dbn): ?>
                                <?php
                                /* $__dbn 是数据库键（下划线形式 + 序列类型后缀，如
                                   Acropora_acuminata.pep）。value 必须原样保留 —— 服务端
                                   按它去找 db/ 下的文件；显示给读者的则是拉丁学名。
                                   后缀先剥掉再查，否则整串查不到 abbr 表，又会退回显示文件名。 */
                                $__lbl = cnido_latin_loose(preg_replace('/\.[A-Za-z0-9]+$/', '', $__dbn));
                                /* 同一个物种在核酸库里有两套（.cds 与 .transcript）、在蛋白
                                   库里有一套（.pep），三者的拉丁名完全相同 —— 440 个选项里
                                   146 个标签被别人重复用掉，读者选完也看不出拿到的是蛋白、
                                   CDS 还是转录本。后缀剥掉之前先认类型，补在显示名后面。
                                   value 不动：服务端按它去 db/ 下找文件。 */
                                $__sfx = ($__dot = strrpos($__dbn, '.')) === false ? '' : strtolower(substr($__dbn, $__dot + 1));
                                $__typ = array('pep' => 'protein', 'cds' => 'CDS', 'transcript' => 'transcripts');
                                if (isset($__typ[$__sfx])) { $__lbl .= ' (' . $__typ[$__sfx] . ')'; }
                                ?>
                                <option value="<?= htmlspecialchars($__dbn) ?>" data-prog="<?= htmlspecialchars(implode(' ', $__progs)) ?>"<?= $__dbn === $__preDb ? ' selected="selected"' : '' ?>><?= htmlspecialchars($__lbl) ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php
            /* 「全部数据库」是慢路径 —— 一次要扫完全部库，实测约 100 秒
               （939 aa 的查询、evalue 10、-F F，146 个蛋白库）；单物种只要
               一两秒。这件事必须在下拉框旁边先说清楚，否则用户会以为页面
               卡死了。库数从 $__pepDb / $__nucDb 现算，不写死。 */
            ?>
            <p class="blast-hint">
                <b>*&nbsp;All&nbsp;databases</b> searches the entire collection in a single pass
                (<?= count($__pepDb) ?> protein or <?= count($__nucDb) ?> nucleotide databases) and
                typically takes well under a minute. Searching a single species returns in a second
                or two, so choose one when you already know which species you need.
            </p>
        </div>

        <?php /* 序列输入 */ ?>
        <div class="form-section">
            <div class="section-title">Query Sequence</div>
            <div class="textarea-container">
                <div class="textarea-header">
                    <span class="form-label">Enter sequence in <strong>FASTA format</strong></span>
                    <a href="javascript:void(0)" onClick="prexample()" class="example-link">📋 Load Example</a>
                </div>
                <textarea name="query" cols="80" rows="8" class="form-textarea" placeholder=">sequence_id&#10;METHIONINEALANINE..."><?= htmlspecialchars($__preSeq, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            
            <?php /* 文件上传 */ ?>
            <div class="file-upload">
                <label class="form-label">Or upload a FASTA file (max 5MB)</label>
                <div class="file-input-wrapper">
                    <input type="hidden" name="MAX_FILE_SIZE" value="5000000" />
                    <input type="file" name="file" class="file-input" accept=".fasta,.fa,.txt">
                    <div class="file-input-label">
                        <span>📁 Choose File</span>
                        <span>No file chosen</span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php /* 参数选项 */ ?>
        <div class="form-section">
            <div class="section-title">Search Parameters</div>
            <div class="param-options">
                <div class="param-group">
                    <div class="param-title">Expect Threshold</div>
                    <select name="evalue" class="form-select">
                        <option value="0.0001">0.0001 (Strict)</option>
                        <option value="0.01">0.01</option>
                        <option value="1">1</option>
                        <option selected="selected" value="10">10 (Default)</option>
                        <option value="100">100</option>
                        <option value="1000">1000 (Relaxed)</option>
                    </select>
                </div>
                
                <div class="param-group">
                    <div class="param-title">Scoring Matrix</div>
                    <select name="matrix" class="form-select">
                        <option value="PAM30">PAM30</option>
                        <option value="PAM70">PAM70</option>
                        <option value="BLOSUM80">BLOSUM80</option>
                        <option selected="selected" value="BLOSUM62">BLOSUM62 (Default)</option>
                        <option value="BLOSUM45">BLOSUM45</option>
                    </select>
                </div>
                
                <div class="param-group">
                    <div class="param-title">Alignment Options</div>
                    <div class="checkbox-group">
                        <input type="checkbox" value="ungap" name="ungapalign" id="ungapalign" class="checkbox-input">
                        <label for="ungapalign" class="checkbox-label">Perform ungapped alignment</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="overview" value="1" id="overview" class="checkbox-input" checked>
                        <label for="overview" class="checkbox-label">Include graphical overview</label>
                    </div>
                </div>
                
                <div class="param-group">
                    <div class="param-title">Results Display</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Descriptions</label>
                            <select name="descriptions" class="form-select">
                                <option value="0">0</option>
                                <option value="10">10</option>
                                <option value="50">50</option>
                                <option selected="selected" value="100">100 (Default)</option>
                                <option value="250">250</option>
                                <option value="500">500</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Alignments</label>
                            <select name="alignments" class="form-select">
                                <option value="0">0</option>
                                <option value="10">10</option>
                                <option selected="selected" value="50">50 (Default)</option>
                                <option value="100">100</option>
                                <option value="250">250</option>
                                <option value="500">500</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php /* 按钮 */ ?>
        <div class="button-group">
            <button type="submit" class="btn-submit">
                🚀 Start BLAST Search
            </button>
            <button type="reset" class="btn-reset">
                🔄 Reset Form
            </button>
        </div>
        
    </form>
</div>

</div>
</div>
</div>

<?php
    include "../Webpage_components.php";
    print $footer;
?>
</body>	
</html>
