<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Data Submit and Comments - CnidoSite</title>
<meta name="keywords" content=" database, data submission, feedback" />
<meta name="description" content="Submit your data and provide feedback to the team" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<?php /* 添加现代化字体和图标 */ ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
<?php /* 现代化表单样式 */ ?>

#column {
    padding: 40px;
}

<?php /* 表单容器美化 */ ?>
.submission-container {
    background: white;
    border-radius: 16px;
    padding: 35px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    margin-bottom: 40px;
}

.submission-container legend {
    font-family: 'Inter', sans-serif;
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
    border-bottom: 3px solid #3b82f6;
    padding-bottom: 12px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
}

.submission-container legend img {
    margin-right: 15px;
    border-radius: 8px;
}

<?php /* 表单样式优化 */ ?>
.form-table {
    width: 100%;
    max-width: 700px;
    margin: 0 auto;
}

.form-row {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    font-size: 15px;
    color: #334155;
    margin-bottom: 8px;
}

.form-label.required::after {
    content: " *";
    color: #ef4444;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 15px;
    color: #1e293b;
    transition: all 0.3s ease;
    background: white;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
}

<?php /* 按钮样式 */ ?>
.button-group {
    display: flex;
    gap: 15px;
    margin-top: 35px;
}

.btn-submit, .btn-reset {
    flex: 1;
    padding: 16px 30px;
    border: none;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-submit {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

.btn-reset {
    background: #f1f5f9;
    color: #475569;
    border: 2px solid #e2e8f0;
}

.btn-reset:hover {
    background: #e2e8f0;
    color: #334155;
}

<?php /* 评论区域 */ ?>

.comments-title {
    font-family: 'Inter', sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
}

.comments-title img {
    margin-right: 12px;
    border-radius: 6px;
}

<?php /* 响应式设计 */ ?>
@media (max-width: 768px) {
    #column {
        padding: 20px;
    }
    
    .submission-container, .comments-section {
        padding: 25px;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .form-input, .form-select, .form-textarea {
        padding: 12px 14px;
        <?php /* 上面是 width:100% 且没有 box-sizing —— 内容宽取满父元素之后，
           padding 14+16 和 border 2+2 还要再加出去，375px 屏上输入框比
           .form-row 宽 36px，整页被撑到 386。只在这个断点里改，
           桌面端（≥769）保持原样。 */ ?>
        box-sizing: border-box;
    }
}
</style>
</head>

<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<?php /* 导航栏保持不变 */ ?>
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
            <li><a href="#" class="current">Help</a>
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
<?php /* 数据提交表单 */ ?>
    <legend><img src="./images/header.jpg" height="45px" style="margin-bottom:-10px">&nbsp;<b>Data Submission</b></legend>
    <?php /* 与站内其它页面一致的页头引言（legend 下紧跟一段 paleo-intro）。 */ ?>
    <p class="paleo-intro">Use this form to tell us about a dataset you would like to see included in
    CnidoSite. Submissions are reviewed by the curators before anything is added; please include enough
    detail (species, data type, accessions or a download link) for us to trace the source. To report a
    problem with data already published here, or for anything else, please use
    <a href="/contact.php">Contact Us</a>.</p>


    <?php /* method 从 get 改成 post：这张表要填姓名、单位与邮箱，GET 会把这些内容
             连同数据说明一起放进地址栏、浏览器历史和服务器的 access log。
             submission.php 本来就同时读 GET 和 POST，接收端不用改。 */ ?>
    <form class="form-horizontal" role="form" method="post" action="submission.php">
        <div class="form-table">
            <div class="form-row">
                <label class="form-label required">Name</label>
                <input type="text" name="name" class="form-input" title="Your name" placeholder="e.g. Jane Doe" required>
            </div>
            
            <div class="form-row">
                <label class="form-label required">Position</label>
                <select name="position" class="form-select" required>
                    <option selected value="Faculty">Faculty</option>
                    <option>Postdoctoral Researcher</option>
                    <option>Graduate Student</option>
                    <option>Undergraduate Student</option>
                </select>
            </div>
            
            <div class="form-row">
                <label class="form-label required">Organization</label>
                <input type="text" name="organization" class="form-input" title="Your organisation" placeholder="e.g. HKUST" required>
            </div>
            
            <div class="form-row">
                <label class="form-label required">Email</label>
                <input type="email" name="email" class="form-input" title="Your email" placeholder="e.g. name@example.org" required>
            </div>
            
            <div class="form-row">
                <label class="form-label required">Species</label>
                <input type="text" name="species" class="form-input" title="Species name" placeholder="e.g. Nematostella vectensis" required>
            </div>
            
            <div class="form-row">
                <label class="form-label required">Data Type</label>
                <select name="data_information" class="form-select" required>
                    <option selected value="genome">Genome (fasta and GFF3, etc.)</option>
                    <option>RNAseq data (raw data, fasta or fastq, etc.)</option>
                    <option>Expression matrix</option>
                </select>
            </div>
            
            <div class="form-row">
                <label class="form-label required">Data Upload Link</label>
                <textarea name="link" class="form-textarea" title="Link to the data" placeholder="Provide data links, separated by commas" required></textarea>
            </div>
            
            <div class="form-row">
                <label class="form-label">Data Description</label>
                <textarea name="data_description" class="form-textarea" title="What the data contains" placeholder="Brief description of the uploaded data (optional)"></textarea>
            </div>
            
            <div class="button-group">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane" style="margin-right: 8px;"></i> Submit Data
                </button>
                <button type="reset" class="btn-reset">
                    <i class="fas fa-redo" style="margin-right: 8px;"></i> Reset Form
                </button>
            </div>
        </div>
    </form><br>


<?php /* 评论区域 */ ?>
    <legend><img src="./images/header.jpg" height="45px" style="margin-bottom:-10px">&nbsp;<b>Community Feedback</b></legend>

<div id="disqus_thread"></div>
<script>
    /**
    *  RECOMMENDED CONFIGURATION VARIABLES: EDIT AND UNCOMMENT THE SECTION BELOW TO INSERT DYNAMIC VALUES FROM YOUR PLATFORM OR CMS.
    *  LEARN WHY DEFINING THESE VARIABLES IS IMPORTANT: https://disqus.com/admin/universalcode/#configuration-variables    */
    /*
    var disqus_config = function () {
    this.page.url = PAGE_URL;  // Replace PAGE_URL with your page's canonical URL variable
    this.page.identifier = PAGE_IDENTIFIER; // Replace PAGE_IDENTIFIER with your page's unique identifier variable
    };
    */
    (function() { // DON'T EDIT BELOW THIS LINE
    var d = document, s = d.createElement('script');
    s.src = 'https://https-cnidosite-org.disqus.com/embed.js';
    s.setAttribute('data-timestamp', +new Date());
    (d.head || d.body).appendChild(s);
    })();
</script>
<noscript>Please enable JavaScript to view the <a href="https://disqus.com/?ref_noscript" target="_blank" rel="noopener noreferrer">comments powered by Disqus.</a></noscript>

</div>
</div>
</div>

<?php
    include "./Webpage_components.php";
    print $footer;
?>
<script id="dsq-count-scr" src="//https-cnidosite-org.disqus.com/count.js" async></script>
</body>
</html>
