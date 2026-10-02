<?php 
if (isset($_GET['genelist']) ) {
    $genelist = $_GET['genelist'];
} else {
    $genelist = $_POST['genelist'];
}
if (isset($_GET['organism']) ) {
    $organism = $_GET['organism'];
} else {
    $organism = $_POST['organism'];
}
if (isset($_GET['networklist']) ) {
    $networklist = $_GET['networklist'];
} else {
    $networklist = $_POST['networklist'];
}
if (isset($_GET['red_low']) ) {
    $red_low = $_GET['red_low'];
} else {
    $red_low = $_POST['red_low'];
}
if (isset($_GET['red_high']) ) {
    $red_high = $_GET['red_high'];
} else {
    $red_high = $_POST['red_high'];
}
if (isset($_GET['blue_low']) ) {
    $blue_low = $_GET['blue_low'];
} else {
    $blue_low = $_POST['blue_low'];
}
if (isset($_GET['blue_high']) ) {
    $blue_high = $_GET['blue_high'];
} else {
    $blue_high = $_POST['blue_high'];
}

/* 解析用户输入。原来只按 "\r\n" 切行、只按 "\t" 切列，而本页两个 textarea 的示例
   写的都是空格分隔（GeneA 1.5 / GeneA GeneB），于是：
     - 只含 \n 的输入（从 tpm_ratio.php 的隐藏域送来、或用 LF 的编辑器写的文件）
       整块被当成一行 —— 只有第一个基因生效，其余静默丢掉，图上看不出少了东西；
     - 空格分隔的行 explode("\t") 只得到 1 段，$genes[1] 未定义 → 落到 else 分支，
       节点名变成整行文字（"GeneA 1.5"），而且颜色永远是绿的。
   改成三种换行都认，TAB 与连续空格都当分隔符。 */
function nxe_lines($s)
{
    $s = str_replace("\xEF\xBB\xBF", '', (string)$s);   // 去掉 UTF-8 BOM
    return array_values(array_unique(array_filter(preg_split('/\r\n|\r|\n/', $s), 'strlen')));
}
function nxe_fields($line)
{
    return preg_split('/[\t ]+/', trim($line));
}

if (trim($genelist) == "" || trim($networklist) == "") {
    echo "<script>alert(\"Please enter at least one gene.\");</script>";
    echo "<script>window.location =\"network_expression.php\";</script>";
    exit;   /* 没有 exit 的话下面照常跑完：用户先看到一张空图，然后才被弹回输入页 */
}

/* 共用的网络图工具（安全 JSON 编码 / 批量注释 / hover 卡片 / 导出）。
   悬停卡片要显示基因注释，而注释表名由物种决定，所以先解析物种。 */
require_once(__DIR__ . '/../includes/network_graph.php');

$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');

/* 物种解析。原来是 `SELECT * FROM abbr WHERE species = '$organism'` 再取
   $result2[2]：$organism 从 GET/POST 直接拼进 SQL；物种名对不上时 $result2[2]
   是空串，后面拼出来的表名全错、注释一条也取不到，页面还不报错。改用共用解析
   函数（转义 + 拉丁名/下划线名/abbr1 都认），失败就明确提示再退回输入页。 */
$ctx = cnido_net_ctx($conn, isset($organism) ? $organism : '');
if (!$ctx) {
    echo "<script>alert(" . cnido_net_js('Sorry, unknown species: ' . (string)$organism
         . '. Please pick a species from the list.') . ");</script>";
    echo "<script>window.location =\"network_expression.php\";</script>";
    exit;
}
$species = $ctx['abbr1'];
$latin   = $ctx['latin'];

$category = 'global';   /* 原来从未赋值，下面两处 echo 出来是空的（PHP notice） */

/* 这两行别删。array_push() 作用在未初始化的变量上不会顺手建数组：$node/$edge 会
   保持 null，边一条都写不进 edge$job.inc，而页面上不显错（web SAPI 不显示 warning），
   只表现为图里没有连线。 */
$node = array();
$edge = array();

/* 单次上限：与 network.list.php 一致（那个页面超过 10 直接弹窗退回）。本页原来没有
   任何上限，粘几百行会让 cytoscape 卡住浏览器，而用户不知道是自己输入太大造成的。 */
$nxeCap = 10;
$list1 = nxe_lines($genelist);
$list2 = nxe_lines($networklist);
$nxeDropped = 0;
if (count($list1) > $nxeCap) { $nxeDropped += count($list1) - $nxeCap; $list1 = array_slice($list1, 0, $nxeCap); }
if (count($list2) > $nxeCap) { $nxeDropped += count($list2) - $nxeCap; $list2 = array_slice($list2, 0, $nxeCap); }

$job = date("YMdHis");
$fp1 = fopen("../tmp/node$job.inc","w");

$nspec   = array();   /* 基因号 => 节点规格，天然去重 */
$nodeval = array();   /* 基因号 => 表达值，给悬停卡片 */

foreach ($list1 as $pairs) {
    $genes = nxe_fields($pairs);
    if (!isset($genes[1])) { continue; }   /* 只有基因名没有比值的行：跳过，不猜数值 */
    $gid = $genes[0];
    /* 颜色要写样式表里那几个选择器认的字面量（#FF2D2D / #66B3FF / #86B342）。
       原来写的是 'red' / 'blue'，跟选择器对不上，红蓝节点拿不到 font-size 规则，
       标签字号与绿色节点不一致。 */
    if($genes[1] >= $red_low && $genes[1] <= $red_high){
        $color = '#FF2D2D';
    }elseif($genes[1] >= $blue_low && $genes[1] <= $blue_high){
        $color = '#66B3FF';
    }else{
        $color = '#86B342';
    }
    $nodeval[$gid] = $genes[1];
    $nspec[$gid] = array(
        'id' => $gid, 'name' => $gid, 'weight' => 70,
        'color' => $color, 'shape' => 'octagon',
    );
}

foreach ($list2 as $pairs) {
    $genes = nxe_fields($pairs);
    if (!isset($genes[1])) { continue; }   /* 单个基因成不了边 */
    /* 边也走共用编码函数：$genes[0] / $genes[1] 是用户粘贴进来的文本，原来原样
       拼进单引号里（source: '$genes[0]'），只要里面有一个引号，整个
       cytoscape({...}) 就抛 SyntaxError，图直接白掉 —— 与注释里那个坑同一类。 */
    array_push($edge, cnido_net_edge_line(array(
        'source' => $genes[0], 'target' => $genes[1],
        'color' => '#dbdc92', 'strength' => 10,
    )));
    foreach ($genes as $gene) {
        /* 成员名单要收「所有出现在基因对里的基因」。这一步原来被放在下面的灰节点
           分支里，而那个分支只在基因有对、却没有表达值时才走到 —— 于是给了比值的
           基因全部 continue 掉，名单一个不收，$node 恒为空。下面
           「Further Analysis for Network Members」的两个表单用的就是这个名单，
           名单空掉等于那两个入口没有内容，而且不报错。 */
        if(!isset($hash[$gene])){
            $hash[$gene]=2;
            array_push($node,$gene);
        }
        if (isset($nspec[$gene])) { continue; }
        /* 网络列表里有、表达值列表里没有的基因，原来完全没有节点，而边照样引用
           它 —— cytoscape 遇到找不到 source/target 的边会直接丢掉，图看着比实际
           稀疏。两张列表是表单上两个独立 textarea（用户分别粘贴），不一致很常见。
           这类节点用灰色标出（样式表里本来就有 #ADADAD 选择器）。 */
        $nspec[$gene] = array(
            'id' => $gene, 'name' => $gene, 'weight' => 70,
            'color' => '#ADADAD', 'shape' => 'octagon',
        );
    }
}

/* 注释一次性批量取（panther → ipr → go → nr，按该物种实际有的表取）。
   原来这个页面 annotation 写死成空串，悬停只显示「基因号 annotation: 」。 */
$anno = cnido_net_annotations($conn, $species, array_keys($nspec));

foreach ($nspec as $g => $spec) {
    $spec['annotation'] = cnido_net_summary(isset($anno[$g]) ? $anno[$g] : array());
    fwrite($fp1, cnido_net_node_line($spec));
}

$node = array_flip(array_flip($node)); 
$edge = array_flip(array_flip($edge)); 
$fp2 = fopen("../tmp/edge$job.inc","w"); 
foreach($edge as $value){ 
    fwrite($fp2,$value); 
} 

$fp0 = fopen("../tmp/$job.txt","w");
$ids = '';   /* 下面用 .= 累加，不先初始化会有 notice */
$list_analysis_p = '';
foreach($node as $id) {
    $id="$id\n";
    $ids.="$id &nbsp";
    fwrite($fp0,$id);
    $list_analysis_p.="$id";
}
fclose($fp0);
fclose($fp1);
fclose($fp2);
/* 原来这里有一句 trim($spe); —— $spe 从未赋值，纯属遗留，删掉 */
?>

<!DOCTYPE html>
<!-- Created using JS Bin Copyright (c) 2014 by maxkfranz (/aqupun/9/edit) Released under the MIT license: -->
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta name="description" content="[Visual style example]" />
<script src="./js/jquery.min.js"></script>
<script src="./js/cytoscape.min.js"></script>
<script src="./js/cola.js"></script>
<script src="./js/cytoscape-qtip.js"></script>
<script src="./js/jquery.qtip.js"></script>
<script src="./js/exporting.js"></script>
<meta charset=utf-8 />
<title>Coexpression Network - CnidoSite</title>
<meta name="keywords" content="" />
<meta name="description" content="" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<link rel="stylesheet" type="text/css" href="./css/jquery.qtip.css">
<link href="./css/cytoscape.js-panzoom.css" rel="stylesheet" type="text/css" />
<link href="./css/font-awesome-4.0.3/css/font-awesome.css" rel="stylesheet" type="text/css" />
<script src="./js/cytoscape-panzoom.js"></script>
<style type="text/css" id="jsbin-css">
<?php /* Network结果页面专用样式 - 不影响全局CSS */ ?>
.network-result-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.network-result-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.network-result-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.transcriptome-badge {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

<?php /* 导出区域美化 */ ?>
.export-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 20px 25px;
    margin: 25px 0;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.export-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

.export-select {
    padding: 12px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 15px;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 150px;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 20px;
}

.export-select:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.export-btn {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    background:linear-gradient(135deg, #065f46 0%, #064e3b 100%);
}

<?php /* 网络视图容器美化 - 关键修改 */ ?>
.network-view-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e2e8f0;
    margin: 30px 0;
    position: relative; /* 添加相对定位 */
}

.network-view-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 18px 25px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
}

.network-view-content {
    padding: 20px;
    min-height: 600px;
    position: relative; /* 添加相对定位 */
}

#cy {
    width: 100%;
    height: 600px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

<?php /* 修改后 */ ?>
.cy-toolbar {
    <?php /* 删除绝对定位，让它跟随文档流 */ ?>
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    padding: 10px 15px;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 1000;
}

.cy-export-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cy-export-select {
    padding: 10px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 120px;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 10px center;
    background-repeat: no-repeat;
    background-size: 16px;
}

.cy-export-select:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.cy-export-btn {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.cy-export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    background:linear-gradient(135deg, #065f46 0%, #064e3b 100%);
}

<?php /* 图例美化 */ ?>
.legend-container {
    background: #f8fafc;
    padding: 20px 25px;
    border-radius: 8px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
}

.legend-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-title::before {
    content: "📊";
    font-size: 18px;
}

.legend-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #475569;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    flex-shrink: 0;
}

<?php /* 进一步分析区域美化 */ ?>
.analysis-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 25px;
    margin: 30px 0;
    border: 1px solid #e2e8f0;
}

.analysis-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.analysis-title::before {
    content: "🔬";
    font-size: 24px;
}

.analysis-forms {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
}

.analysis-form {
    background: #f8fafc;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.analysis-form textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    background: white;
    resize: vertical;
    margin-bottom: 15px;
    min-height: 100px;
}

.analysis-form textarea:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.analysis-btn {
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
}

.analysis-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

<?php /* 警告信息美化 */ ?>
.warning-message {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 4px solid #f59e0b;
    padding: 15px 20px;
    border-radius: 8px;
    margin: 20px 0;
    color: #92400e;
    font-size: 16px;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .network-result-header {
        padding: 20px;
    }
    
    .network-result-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .export-section {
        flex-direction: column;
        align-items: stretch;
    }
    
    .export-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .export-select, .export-btn {
        width: 100%;
    }
    
    .analysis-forms {
        grid-template-columns: 1fr;
    }
    
    .legend-grid {
        grid-template-columns: 1fr;
    }
    
    .cy-toolbar {
        top: 10px;
        right: 10px;
        left: 10px;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .cy-export-controls {
        width: 100%;
        justify-content: center;
    }
}
</style>
<?php
/* 悬停卡片样式 + 共用的导出脚本（exportgraph / base64ToBlob）。
   <style> 必须在 head 里才符合 XHTML 1.0 Transitional。 */
if (function_exists('cnido_net_tip_css'))   { cnido_net_tip_css(); }
if (function_exists('cnido_net_export_js')) { cnido_net_export_js(); }
?>
</head>
<body>

<script id="jsbin-javascript">
$(function(){ // on dom ready
    var cy = cytoscape({
        container: document.querySelector('#cy'),
        layout: {
            name: 'cola',
            padding: 15
        },
        style: cytoscape.stylesheet()
        .selector('node')
        .css({
            'shape': 'data(faveShape)',
            'width': 'mapData(weight,5,70,10,90)',
            'content': 'data(name)',
            'text-valign': 'center',
            'font-family': 'Calibri',
            'font-weight': 'bold',
            'border-width': 1,
            'border-color': 'data(faveColor)',
            'background-color': 'data(faveColor)',
            'background-opacity': 0.6,
        })
        .selector('node[faveColor = "#E1E100"]')
        .css({
            'font-size': 'mapData(weight,15,70,12,25)',
            'color': 'mapData(weight,20,60,black,red)'
        })
        .selector('node[faveColor = "#86B342"]')
        .css({
            'font-size': 'mapData(weight,15,70,12,25)',
            'color': 'mapData(weight,20,60,black,red)'
        })
        .selector('node[faveColor = "#FF2D2D"]')
        .css({
            'font-size': 'mapData(weight,15,70,12,25)',
            'color': 'mapData(weight,20,60,black,red)'
        })
        .selector('node[faveColor = "#66B3FF"]')
        .css({
            'font-size': 'mapData(weight,15,70,12,25)',
            'color': 'mapData(weight,20,60,black,red)'
        })
        .selector('node[faveColor = "#ADADAD"]')
        .css({
            'font-size': 'mapData(weight,15,70,12,25)',
            'color': 'grey'
        })
        .selector('edge')
        .css({
            'opacity': 0.666,
            'width': 'mapData(strength, 2, 10, 2, 4)',
            'line-color': 'data(faveColor)',
            'source-arrow-color': 'data(faveColor)',
            'target-arrow-color': 'data(faveColor)'
        })
        .selector('edge.questionable')
        .css({
            'line-style': 'dotted',
            'target-arrow-shape': 'diamond'
        })
        .selector('.faded')
        .css({
            'opacity': 0.25,
            'text-opacity': 0
        }),
        elements: {
            nodes: [
                <?php include("../tmp/node$job.inc"); echo "],\n edges: [\n"; include("../tmp/edge$job.inc"); ?>
            ]
        },
        ready: function(){
            window.cy = this;
            // giddy up
        }
    });
    
    cy.panzoom({
        // options here...
    });
    
    // 节点悬停提示：基因号 + 注释 + 表达值 + gene_detail.php 链接。
    // 实现在 includes/network_graph.php（cnido_net_tip_js），卡片内容取自本页
    // 上面输出的 CNIDO_NET_ANNO / CNIDO_NET_VAL。原来这里读节点自己的
    // annotation 字段，而这个字段被写死成空串，所以悬停只显示「基因号
    // annotation: 」，冒号后面什么都没有 —— 正是要修的那件事。
    cnidoNetBindTips();
}); // on dom ready
// 导出（exportgraph）由 includes/network_graph.php 提供：原来这里那份 cy.jpg()
// 方法名不存在（应为 cy.jpeg()）、cy.json() 返回对象却被当 base64 字符串 split，
// 所以 JPG 和 JSON 导出一定失败。已删除，改用共用版本。
</script>

<?php
/* 悬停卡片的数据和脚本。放在这里（脚本在 body 里是合法的）而不是 before-head：
   head 之后紧接着就是 cytoscape 的 elements 数组，中间不能插入任何 HTML。 */
cnido_net_payload($anno, $latin, $nodeval);
cnido_net_tip_js();
?>

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
            <li><a href="#" class="current">Transcriptome</a>
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Dynamic Network Expression View</b></legend>
<p class="paleo-intro">Dynamic visualization of gene co-expression networks, expression and functional enrichment analysis.</p>

<?php
/* 画出多少东西，页面上原来一句话都没有：输入被截断、或解析器把行丢掉，用户都无从
   察觉（只能看到图里少了点东西）。这三个数直接把「我提交的」和「画出来的」对上。 */
$nxeWithRatio = count($list1);
$nxePairs     = count($list2);
$nxeMembers   = count($node);
?>
<div class="gd-notice gd-info" style="margin:16px 0">
    Drawn from your input: <b><?= $nxeWithRatio ?></b> gene<?= $nxeWithRatio === 1 ? '' : 's' ?> with an expression ratio,
    <b><?= $nxePairs ?></b> gene pair<?= $nxePairs === 1 ? '' : 's' ?>
    (<?= $nxeMembers ?> distinct gene<?= $nxeMembers === 1 ? '' : 's' ?> appearing in them).
    A gene that has a ratio but appears in no pair is still drawn &mdash; as a lone node with no edge.
</div>
<?php if ($nxeDropped > 0): ?>
<div class="gd-notice gd-warn" style="margin:16px 0">
    <b><?= $nxeDropped ?> further line<?= $nxeDropped === 1 ? ' was' : 's were' ?> not used.</b>
    One run draws at most <?= $nxeCap ?> genes with ratios and <?= $nxeCap ?> gene pairs &mdash; the same limit
    Network Analysis applies. Narrow the list, or run it in batches.
</div>
<?php endif; ?>

<?php /* 导出区域 */ ?>
<div class="export-section">
    <div style="font-size: 16px; font-weight: 600; color: #1e293b;">
        📊 Dynamic Co-expression Network based on the submitted data
    </div>
    <div class="cy-toolbar">
        <select id="cyExport" class="cy-export-select">
            <option value="jpg">JPG Image</option>
            <option value="png">PNG Image</option>
            <option value="json">JSON Data</option>
        </select>
        <button onclick="exportgraph()" class="cy-export-btn">
            ⬇️ Export Graph
        </button>
    </div>
    
</div>

<?php /* 网络视图容器 */ ?>
<div class="network-view-container">
    <div class="network-view-header">
        <a name="locus">Dynamic Co-expression Network View</a>
    </div>
    
    <div class="network-view-content">
        <?php /* 新增：网络图工具栏（红框位置）- 在#cy上方右上角 */ ?>
        <div id="cy"></div>
        <?php /* 图例。原来只有一个 #E1E100 的色块，而图里没有任何节点是这个黄色（节点只有
             #FF2D2D / #86B342 / #66B3FF 三种），色块等于在说谎；而且它只报了阈值数字，
             没说「红是哪一边高」。现在三个色块与节点颜色一一对应，并写明方向。 */ ?>
        <div class="legend-container">
            <div class="legend-grid">
                <?php /* 上色判据是**闭区间**（见上面第 111/113 行的
                       `>= $red_low && <= $red_high` / `>= $blue_low && <= $blue_high`），
                       而图例原来只印了每个区间的一个端点，绿色那格还写死成
                       「blue_high < ratio < red_low」—— 比值超过 red_high 或低于
                       blue_low 时节点是绿的，按原图例却读不出来。四个端点全部印出。 */ ?>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #FF2D2D;"></div>
                    <span><b>Expression ratio from <?php echo $red_low;?> to <?php echo $red_high;?></b> &mdash;
                    higher in the group you entered as the numerator
                    (group&nbsp;A in <b>Expression Ratio from TPM</b>)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #66B3FF;"></div>
                    <span><b>Expression ratio from <?php echo $blue_low;?> to <?php echo $blue_high;?></b> &mdash;
                    lower in that same group</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #86B342;"></div>
                    <span><b>Outside both ranges</b> (ratio &lt; <?php echo $blue_low;?>,
                    <?php echo $blue_high;?> &lt; ratio &lt; <?php echo $red_low;?>, or ratio &gt; <?php echo $red_high;?>) &mdash;
                    the change falls outside the window you set, so the gene is drawn but not called up or down</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #ADADAD;"></div>
                    <span><b>Grey</b> &mdash; the gene appears in your network list but you gave no
                    expression value for it, so there is nothing to colour by. It is still drawn,
                    because edges pointing at a missing node would otherwise be dropped.</span>
                </div>
            </div>
            <div style="margin-top:12px;padding-top:10px;border-top:1px solid #e2e8f0;font-size:16px;color:#475569;line-height:1.7;">
                <strong>Hover any node</strong> to see the annotation of the gene it stands for
                (PANTHER / InterPro / GO description, or NR when the others are absent), its
                expression value, and a link to its gene page.
            </div>
        </div>
    </div>
</div>

<?php /* 进一步分析区域 */ ?>
<div class="analysis-section">
    <div class="analysis-title">Further Analysis for Network Members</div>
    <div class="analysis-forms">
        <?php /* 表达谱分析表单 */ ?>
        <form action="./network_heatmap.php" target="_blank" method="post" class="analysis-form">
            <textarea name="queryList" readonly><?php echo htmlspecialchars($list_analysis_p); ?></textarea>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <input type="hidden" name="species" value="<?php echo htmlspecialchars($species); ?>">
            <input type="hidden" name="genelist" value="<?php echo htmlspecialchars($list_analysis_p); ?>">
            <button type="submit" class="analysis-btn">
                📈 Expression Profiling Analysis
            </button>
        </form>
        
        <?php /* GSEA分析表单 */ ?>
        <form name="GSEA" method="post" action="../GSEA/GSEA.php" target="_blank" enctype="multipart/form-data" class="analysis-form">
            <textarea name="gsea_in" readonly><?php echo htmlspecialchars($list_analysis_p); ?></textarea>
            <input type="hidden" name="species" value="<?php echo htmlspecialchars($species); ?>">
            <button type="submit" class="analysis-btn">
                🧬 Gene Sets Enrichment Analysis
            </button>
        </form>
    </div>
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
