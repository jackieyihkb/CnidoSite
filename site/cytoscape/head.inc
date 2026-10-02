<?php
// ... 保留原有的 PHP 逻辑代码 ...

// 共表达网络页面共用的部分（安全 JSON 编码、批量注释、hover 卡片、导出）。
// 两个渲染器 network.list.php 与 network.expression.php 都用它，见该文件的说明。
$__cnido_net = __DIR__ . '/../includes/network_graph.php';
if (is_file($__cnido_net)) { require_once($__cnido_net); }
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Co-expression Network Results - CnidoSite</title>
<meta name="keywords" content="Cnidaria, co-expression network, gene interaction, Cytoscape, network analysis" />
<meta name="description" content="Co-expression network visualization and analysis results for your gene list" />

<!-- 样式文件 -->
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<link rel="stylesheet" type="text/css" href="/css/jquery.qtip.css">
<link href="./css/cytoscape.js-panzoom.css" rel="stylesheet" type="text/css" />
<link href="./css/font-awesome-4.0.3/css/font-awesome.css" rel="stylesheet" type="text/css" />

<!-- JavaScript 库 -->
<script src="./js/jquery.min.js"></script>
<script src="./js/cytoscape.min.js"></script>
<script src="./js/cola.js"></script>
<script src="./js/cytoscape-qtip.js"></script>
<script src="./js/jquery.qtip.js"></script>
<script src="./js/cytoscape-panzoom.js"></script>
<script src="./js/exporting.js"></script>
<script src="./jquery.table2excel.js"></script>

<style>
/* Network结果页面专用样式 - 不影响全局CSS */
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

/* 导出区域美化 */
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
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
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

/* 网络视图容器美化 */
.network-view-container {
    position: relative; 
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.network-view-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 18px 25px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
}

/* 调整网络内容区域的内边距，避免内容被工具栏遮挡 */
.network-view-content {
    position: relative;
    z-index: 1;
    background: white;
}

#cy {
    width: 100%;
    height: 600px; /* 根据实际需求调整高度 */
    background: white;
}

/* 网络图工具栏 */
.cy-toolbar {
    position: absolute;
    top: 15px;  /* 距离顶部距离 */
    right: 15px; /* 距离右侧距离 */
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    z-index: 1000; /* 确保在网络图之上 */
    align-items: center;
}

/* 工具栏选择框 */
.cy-export-select {
    width: 120px;
    padding: 6px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 14px;
    background: white;
    cursor: pointer;
}

/* 工具栏按钮 */
.cy-export-btn {
    width: 120px;
    padding: 8px 12px;
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.cy-export-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}


/* 图例美化 */
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
/* 进一步分析区域美化 */
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

/* 警告信息美化 */
.warning-message {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 4px solid #f59e0b;
    padding: 15px 20px;
    border-radius: 8px;
    margin: 20px 0;
    color: #92400e;
    font-size: 14px;
}

/* 响应式调整 */
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
}
</style>

<?php
/* hover 卡片样式 + 导出脚本。放 <head> 里是因为 <style> 在 body 里不符合
   XHTML 1.0 Transitional；脚本在这时定义也比等 DOM ready 更早，两个渲染器
   的导出下拉框（id 分别是 export / cyExport）都由它一处接管。 */
if (function_exists('cnido_net_tip_css')) { cnido_net_tip_css(); }
if (function_exists('cnido_net_export_js')) { cnido_net_export_js(); }
?>

</head>
<body>
<!-- 导航栏和其他内容保持不变 -->
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
		//'font-size': 'mapData(weight,15,70,12,25)',
		'border-width': 1,
		'border-color': 'data(faveColor)',
        'background-color': 'data(faveColor)',
		'background-opacity': 0.6,
        //'color': 'mapData(weight,20,60,black,red)'
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
        //'target-arrow-shape': 'triangle',
        //'source-arrow-shape': 'circle',
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
