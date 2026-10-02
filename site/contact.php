<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="renderer" content="webkit">
<title>Contact Us - CnidoSite</title>
<meta name="keywords" content="Cnidaria, database, contact, support" />
<meta name="description" content="Contact the team for support and inquiries" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script src="js/func.js?v=1790644208" type="text/javascript"></script>

<?php /* 添加Google Fonts */ ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<?php /* 添加Font Awesome图标 */ ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>

<?php /* 美化联系我们区域 */ ?>
#column {
    padding: 40px;
}

#column legend {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
    padding-bottom: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
}

#column legend img {
    margin-right: 15px;
    border-radius: 8px;
}

<?php /* 联系信息卡片 */ ?>
.contact-card {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    border-radius: 15px;
    padding: 30px;
    color: white;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.contact-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
}

.contact-item {
    display: flex;
    align-items: center;
    margin-bottom: 25px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.contact-item:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateX(10px);
}

.contact-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    font-size: 24px;
    color: white;
}

.contact-text h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
    font-weight: 600;
}

.contact-text p, .contact-text a {
    margin: 0;
    font-size: 16px;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
}

.contact-text a:hover {
    color: white;
    text-decoration: underline;
}

<?php /* 团队介绍区域 */ ?>
.team-section {
    background:#f8fafc;
    border-radius: 15px;
    padding: 30px;
    margin-top: 30px;
}

.team-section h3 {
    color: #2c3e50;
    margin-bottom: 20px;
    font-size: 22px;
    display: flex;
    align-items: center;
}

.team-section h3 i {
    margin-right: 10px;
    color:#1d4ed8;
}

<?php /* 团队网格容器：使用 Flexbox 并换行，适应多行排列 */ ?>
<?php /* 团队网格容器 */ ?>
.team-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 25px;
    justify-content: center;
}

<?php /* 单个成员卡片 */ ?>
.team-member {
    display: flex;
    flex-direction: column; /* 垂直排列：图片在上，文字在下 */
    align-items: center; /* 水平居中 */
    text-align: center; /* 文字居中 */
    flex: 1 1 300px; /* 响应式：最小宽度320px，自动分配剩余空间 */
    max-width: 350px; /* 最大宽度限制 */
    padding: 15px 10px;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    background: white;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.team-member:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

<?php /* 头像样式 */ ?>
.team-member img {
    width: 250px;
    height: 250px;
    border-radius: 50%; /* 圆形头像 */
    object-fit: cover;
    border: 4px solid #3498db; /* 蓝色边框 */
    margin-bottom: 20px; /* 图片与文字的间距 */
    box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
}

<?php /* 文字介绍容器 */ ?>
.member-info {
    display: flex;
    flex-direction: column;
    gap: 8px; /* 文字行之间的间距 */
    width: 100%;
}

<?php /* 名字样式 */ ?>
.member-info h4 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
}

<?php /* 职位样式 */ ?>
.member-info .position {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    color:#1d4ed8; /* 蓝色突出显示职位 */
    margin-top: 5px;
}

<?php /* 兴趣爱好样式 */ ?>
.member-info .interests {
    margin: 0;
    font-size: 15px;
    color: #666;
    line-height: 1.5;
    margin-top: 5px;
    padding: 0 10px; /* 左右内边距，避免文字太宽 */
}

<?php /* 邮箱样式 */ ?>
.member-info .email {
    margin: 0;
    font-size: 15px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #eee; /* 上边框分隔 */
}

.member-info .email a {
    color:#1d4ed8;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.member-info .email a:hover {
    color: #2980b9;
    text-decoration: underline;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .team-grid {
        gap: 20px;
    }
    
    .team-member {
        flex: 1 1 100%; /* 移动端占满宽度 */
        max-width: 100%;
        padding: 20px 15px;
    }
    
    .team-member img {
        width: 120px;
        height: 120px;
    }
}

<?php /* 动画效果 */ ?>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.contact-card, .team-section, .feedback-form {
    animation: fadeInUp 0.6s ease forwards;
}

.contact-card:nth-child(2) {
    animation-delay: 0.2s;
}

.team-section {
    animation-delay: 0.4s;
}

<?php /* 反馈表单 */ ?>
.feedback-form {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-top: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.feedback-form h3 {
    color: #2c3e50;
    margin-bottom: 20px;
    font-size: 22px;
    display: flex;
    align-items: center;
}

.feedback-form h3 i {
    margin-right: 10px;
    color: #e74c3c;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #2c3e50;
    font-weight: 500;
}

<?php /* 本页没有全局 box-sizing:border-box（templatemo_style.css 里的 bootstrap 段
   是残缺的，只带组件规则、没带 `*,::before,::after{box-sizing:border-box}`），而
   templatemo_style.css 的 .form-control 是完整 bootstrap 的那份、写着
   `height:calc(1.5em + .75rem + 2px)` —— 在 content-box 下那个 height 是**内容高**，
   于是每个输入框的实际高度 = 38 + 12*2 + 2*2 = 66px。宽度同理：width:100% 只算内容宽，
   再加上 30px 内边距和 4px 边框，整排输入框比白色卡片右边多出 4px，**右侧边框与圆角被
   画到卡片外面**（桌面端也一样，不只是手机）。
   两条一起改：box-sizing 收回内边距，height:auto 让高度回到"内容 + 内边距 + 边框"
   （16px 字 × 1.5 行高 + 24 + 4 = 52px，与设计时的 padding 意图一致）。
   光加 box-sizing 不加 height:auto 会被 bootstrap 那条 38px 钉死，再把文字挤扁。 */ ?>
.form-control {
    box-sizing: border-box;
    width: 100%;
    height: auto;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s ease;
    font-family: 'Poppins', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color:#1d4ed8;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}

.btn-submit {
    background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: white;
    border: none;
    padding: 15px 40px;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
}

.btn-submit i {
    margin-right: 8px;
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
<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Contact Us</b></legend>

<p  class="paleo-intro">
    For questions, comments or suggestions about the database or the research behind it, please contact us through any of the channels below.
</p>

<?php /* 团队介绍 */ ?>
<div class="team-section">
    <h3><i class="fas fa-users"></i> Our Team</h3>
    <p style="color: #666; margin-bottom: 20px;">Meet the dedicated researchers behind CnidoSite</p>
    
    <div class="team-grid"> 
        <?php /* 团队成员 1 */ ?>
        <div class="team-member">
            <img src="./images/wu.jpg" alt="Prof. Longjun Wu">
            <div class="member-info">
                <h4>Prof. Longjun Wu</h4>
                <p class="position">Assistant Professor</p>
                <p class="interests">Interests: Marine conservation, Evolutionary biology, Bioinformatics, and Marine multi-omics</p>
                <p class="email"><a href="mailto:longjunwu@ust.hk">longjunwu@ust.hk</a></p>
            </div>
        </div>
        <?php /* 团队成员 2 */ ?>
        <div class="team-member">
            <img src="./images/qian.jpg" alt="Prof. Pei-yuan Qian">
            <div class="member-info">
                <h4>Prof. Pei-yuan Qian</h4>
                <p class="position">Chair Professor</p>
                <p class="interests">Interests: Microbial ecology and chemical signaling in marine biofilms, deep-sea adaptation</p>
                <p class="email"><a href="mailto:boqianpy@ust.hk">boqianpy@ust.hk</a></p>
            </div>
        </div>
        
        <?php /* 团队成员 3 */ ?>
        <div class="team-member">
            <img src="./images/jackie.jpg" alt="Dr. Jiajie She">
            <div class="member-info">
                <h4>Dr. Jiajie She</h4>
                <p class="position">Postdoctoral Researcher</p>
                <p class="interests">Interests: Bioinformatics, Phylogenomics, Deep-Sea Adaptation and Evolution, and Epigenetics</p>
                <p class="email"><a href="mailto:jackieyihkb@ust.hk">jackieyihkb@ust.hk</a></p>
            </div>
        </div>

        <?php /* 团队成员 4 */ ?>
        <div class="team-member">
            <img src="./images/jiakai.jpg" alt="UG JiaKai FAN">
            <div class="member-info">
                <h4>UG JiaKai FAN</h4>
                <p class="position">Undergraduate Student</p>
                <p class="interests">Interests: Bioinformatics, RNA modifications, and multi-omics</p>
                <p class="email"><a href="mailto:Jiakai.Fan22@student.xjtlu.edu.cn">Jiakai.Fan22@student.xjtlu.edu.cn</a></p>
            </div>
        </div>
    </div>
</div>
<?php /* 反馈表单 */ ?>
<div class="feedback-form">
    <h3><i class="fas fa-paper-plane"></i> Send Us a Message</h3>
    <?php /* 这个表单的四个字段是 name / email / subject / message，收件方是 submit.php
             （它正是按这四个字段写的，写进 tmp/submit-<时间戳>.txt 后回一句确认并链回本页；
             2026-09-29 之前写的是 ./submit/，那个目录 www-data 无权写，留言全被丢掉）。
             2026-09-29 之前 action 指向 submit_comments.php —— 那是「数据提交」的表单页，
             页内**没有任何 $_POST 处理**，于是访客写下的留言被整个丢掉，只换回一张
             要他填数据提交表的表单。submit.php 一直存在、字段也对得上，只是没有入口。 */ ?>
    <form action="./submit.php" method="post">
        <div class="form-group">
            <label for="name">Your Name</label>
            <input type="text" id="name" name="name" class="form-control" placeholder="Enter your name" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
        </div>
        
        <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" class="form-control" placeholder="What is this regarding?" required>
        </div>
        
        <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" class="form-control" rows="5" placeholder="Your message here..." required></textarea>
        </div>
        
        <button type="submit" class="btn-submit">
            <i class="fas fa-paper-plane"></i> Send Message
        </button>
    </form>
</div>

</div>
</div>
</div>

<?php
    include "./Webpage_components.php";
    print $footer;
?>
</body>
</html>
<?php /* 这里原本有一行 Disqus 计数脚本：
   <script id="dsq-count-scr" src="//http-bioinfo-hk-cn-deepseadb.disqus.com/count.js" async></script>
   两个毛病：主机名多了一层 "http-" 前缀（协议相对写法 // 后面不该再带 scheme），
   解析出来是 https://http-bioinfo-hk-cn-deepseadb.disqus.com/count.js —— 该主机不
   存在，必然加载失败；而且本页从来没有 disqus_thread 容器，计数器本身也无处可显示。
   它是页面最后一行唯一的第三方请求，已删除。 */ ?>
