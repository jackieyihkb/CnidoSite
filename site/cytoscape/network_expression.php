<?php
/* 审稿意见 Referee 2 major 1 / Referee 1 minor 1 & 3：
 * 物种门户深链过来（network_expression.php?species=<拉丁名>）时本页原来完全
 * 忽略参数；物种 <select> 在服务端 HTML 里也是空的 —— 候选由 DynamicOptionList
 * 在 onLoad 时重建，而 printOptions("datalib") 指向的下拉框名在本页并不存在。
 * 现改为：cnido_state() 解析物种（networkexpr_* 命名空间）+ 由数据库生成候选。 */
require_once __DIR__ . '/../includes/state.php';

/* 深链里的物种可能是拉丁名（Nematostella vectensis）、下划线名或 abbr1 代码；
 * 本页 <select name="organism"> 与下游 network.expression.php 都用拉丁名
 * （它按 abbr.species 查表拿 abbr1 再取 <abbr1>_coexpress_positive）。 */
function nxe_resolve_species($conn, $in)
{
    $in = trim((string)$in);
    if ($in === '') { return ''; }
    foreach (array($in, str_replace('_', ' ', $in)) as $cand) {
        $e = mysqli_real_escape_string($conn, $cand);
        $q = mysqli_query($conn, "SELECT species FROM abbr
                                  WHERE species = '$e' OR abbr = '$e' OR UPPER(abbr1) = UPPER('$e') LIMIT 1");
        if ($q && ($r = mysqli_fetch_row($q))) { return $r[0]; }
    }
    return '';
}

/* 类群名统一为全站用词 Hexacorallia（speciesinfo / classfy 里的写法）。
   本页历史上把它显示成 Hexactiniaria，与导航栏和 browse.php?class=… 不一致：
   页面上的类群名与别处对不上（Referee 1 minor 1），而且本页导航里的
   browse.php?class=Hexactiniaria 在 classfy 表里根本没有对应值，点了列不出物种。
   为兼容旧书签 / 旧链接，入参仍接受 Hexactiniaria，但只输出 Hexacorallia。 */
function nxe_class_label($c) { return $c; }
function nxe_canon_class($c) { return ($c === 'Hexactiniaria') ? 'Hexacorallia' : $c; }

$__st = cnido_state('networkexpr', array(
    'specie' => array('get' => 'species', 'default' => 'Nematostella vectensis'),
    'clazz'  => array('get' => 'class',   'default' => 'Hexacorallia'),
));
$specie      = $__st['specie'];
$specieFrom  = $__st['specie__from'];
$nxeNotice   = '';
/* 请求值先归一化：旧的 ?class=Hexactiniaria 仍落到 Hexacorallia */
$nxeClazz = nxe_canon_class($__st['clazz']);
$nxeClassOrder = array('Hexacorallia', 'Hydrozoa', 'Octocorallia', 'Scyphozoa');
$nxeList     = array();   // 拉丁名 => class 标签
$nxeByClass  = array();   // class 标签 => array(拉丁名)

/* 只有 <abbr1>_coexpress_positive 表存在**且表里有行**的物种才能做网络分析，
 * 候选列表由 information_schema + 逐表探行生成 —— 原来的硬编码列表与库里的数据
 * 并不完全一致，而只测「表在不在」会把空表也算成一个网络。 */
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if (!$conn->connect_error) {
    $q = mysqli_query($conn, "SELECT a.abbr1, a.species, COALESCE(si.Class, '') AS Class FROM abbr a
                              LEFT JOIN speciesinfo si ON si.abbr = a.abbr1
                              WHERE a.species IS NOT NULL AND a.species <> ''
                                AND EXISTS (SELECT 1 FROM information_schema.tables t
                                            WHERE t.table_schema = DATABASE()
                                              AND t.table_name = CONCAT(a.abbr1, '_coexpress_positive'))
                              ORDER BY a.species");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            /* 与 cytoscape/network.php、includes/coverage.php、includes/stats.php 同一判据：
               AAURI1 / AAURI2 那四张表建了却一行都没有，只测表名会算成两个网络，本页就印
               「Networks exist for 31 species」，而站内其余界面都是 29。任一方向有行才算，
               用 LIMIT 1 探行（PCLAV 那张表 650 万行，COUNT(*) 不可用）。 */
            $__hasRows = false;
            foreach (array('_coexpress_positive', '_coexpress_negative') as $__suf) {
                $__tbl = str_replace('`', '', $r['abbr1'] . $__suf);
                $__probe = @mysqli_query($conn, "SELECT 1 FROM `$__tbl` LIMIT 1");
                if ($__probe && mysqli_fetch_row($__probe)) { $__hasRows = true; break; }
            }
            if (!$__hasRows) { continue; }
            $cl = nxe_class_label($r['Class']);
            $nxeList[$r['species']] = $cl;
            $nxeByClass[$cl][] = $r['species'];
        }
    }
}

if (!empty($nxeList)) {
    // 0) 深链可能给的是 abbr1 代码或下划线名，先统一成拉丁名（候选表用拉丁名）
    if (!isset($nxeList[$specie]) && $specieFrom !== 'default') {
        $__lat = nxe_resolve_species($conn, $specie);
        if ($__lat !== '') { $specie = $__lat; }
    }
    // 1) 若显式切换了 class（下拉框 onchange 会带 ?class= 重新加载）而物种不是显式
    //    深链指定的，则以 class 为准，取该 class 下的第一个物种
    if (in_array($nxeClazz, $nxeClassOrder, true) && !empty($nxeByClass[$nxeClazz])
        && $specieFrom !== 'get' && $specieFrom !== 'post'
        && (!isset($nxeList[$specie]) || $nxeList[$specie] !== $nxeClazz)) {
        $specie = $nxeByClass[$nxeClazz][0];
    }
    // 2) 物种仍不可用（没有共表达网络，或名字不认识）：退回到一个真实物种并说明，
    //    不能让下拉框停留在请求值上而页面实际查的是别的物种（Referee 1 minor 3）
    if (!isset($nxeList[$specie])) {
        $fbKeys = array_keys($nxeList);
        $fb = !empty($nxeByClass['Hexacorallia']) ? $nxeByClass['Hexacorallia'][0] : $fbKeys[0];
        if ($specieFrom !== 'default') {
            $nxeNotice = 'No co-expression network is available for <b>' . htmlspecialchars($specie) . '</b> in '
                       . 'CnidoSite; showing <b>' . htmlspecialchars($fb) . '</b> instead. Networks exist for '
                       . count($nxeList) . ' species — see the selector below, or the '
                       . '<a href="/species_portal.php?species=' . urlencode($specie) . '">species portal</a> '
                       . 'for what this species does provide.';
        }
        $specie = $fb;
    }
    $class = $nxeList[$specie];
    // 与页面上实际显示的物种保持一致，避免下次进来又跳回默认物种
    $_SESSION['networkexpr_specie'] = $specie;
} else {
    $class = 'Hexacorallia';
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title>Dynamic Expression View - CnidoSite</title>
<meta name="keywords" content="Cnidaria, co-expression network, gene expression, dynamic visualization" />
<meta name="description" content="Dynamic co-expression network view with gene expression values across different conditions or developmental stages" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="../js/func.js" type="text/javascript"></script>
<script>
	<?php /* regionState（DynamicOptionList）已移除：initDynamicOptionLists() 在 body onLoad
	 * 时会把 <select> 清空重建（js/DynamicOptionList.js:471 的 child.options.length=0），
	 * 而 printOptions("datalib") 引用的下拉框名在本页并不存在，导致物种下拉框一直是
	 * 空的（Referee 1 minor 1）。候选改由 PHP 服务端渲染，见下方 <select name="organism">。 */ ?>
</script>
<style>
<?php /* Network页面专用样式 - 不影响全局CSS */ ?>
.network-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(30, 41, 59, 0.15);
}

.network-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.network-header p {
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

<?php /* 表单容器美化 */ ?>
.network-form-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 35px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
}

.form-section {
    margin-bottom: 5px;
    padding-bottom: 5px;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 22px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}


<?php /* 表单行样式 */ ?>
.form-row {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 10px;
}

.form-select {
    width: 100%;
    max-width: 400px;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 16px;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 20px;
}

.form-select:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
    color:#047857;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
}

.example-link:hover {
    color:#047857;
    text-decoration: underline;
}

.form-textarea {
    width: 100%;
    max-width: 600px;
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 15px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    background: #f8fafc;
    color: #1e293b;
    resize: vertical;
    transition: all 0.3s ease;
    min-height: 120px;
}

.form-textarea:focus {
    outline: none;
    border-color:#047857;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    background: white;
}

<?php /* 复选框美化 */ ?>
.checkbox-group {
    display: flex;
    gap: 25px;
    margin-top: 15px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-input {
    width: 20px;
    height: 20px;
    accent-color:#047857;
    cursor: pointer;
}

.checkbox-label {
    font-size: 16px;
    color: #475569;
    cursor: pointer;
    font-weight: 500;
}

<?php /* 按钮美化 */ ?>
.button-group {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.btn-submit, .btn-reset {
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
    background:linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    background:linear-gradient(135deg, #065f46 0%, #064e3b 100%);
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

<?php /* 描述文本美化 */ ?>
.description-text {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-left: 4px solid #10b981;
    padding: 20px 25px;
    border-radius: 8px;
    margin: 25px 0;
    color: #14532d;
    line-height: 1.7;
    font-size: 16px;
}

.description-text strong {
    color: #166534;
}

<?php /* 响应式调整 */ ?>
@media (max-width: 768px) {
    .network-header {
        padding: 20px;
    }
    
    .network-header h1 {
        font-size: 22px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .network-form-container {
        padding: 25px 20px;
        margin-left: 15px;
        margin-right: 15px;
    }
    
    .checkbox-group {
        flex-direction: column;
        gap: 15px;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .form-select, .form-textarea {
        max-width: 100%;
    }
}
</style>

<script>
	<?php /* 例子的两份数据都取自库里的真数据，不是编的（2026-09-27 由 Lophelia pertusa
	   的 OS493_* 换成 Nematostella vectensis）：
	     · 网络对（networklist）= NVECT_coexpress_positive 里 pcc 最高的 75 对，
	       45 个基因全部来自「以 6 个 ABC transporter 为种子、按 pcc 向外扩」的集合；
	     · 表达值（genelist）= log2((TPM_144hpa 均值 + 1) / (TPM_0hpa 均值 + 1))，
	       取 NVECT_TPM 的 3 个 *_144hpa 列与 3 个 *_0hpa 列（再生 144h 对未切对照），
	       算法与 cytoscape/tpm_ratio.php 完全一致（含 +1 伪计数）。
	   重跑方式：/tmp/gen_nve_network_example.php（换物种时照抄那支脚本）。 */ ?>
	function NetworkR2()
	{
		var id="XP_048588491.1	-0.085550631\nXP_032238263.1	-0.820570663\nXP_032239123.2	-0.131465415\nXP_048586786.1	-0.26097951\nXP_001631193.2	0.4422876\nXP_032219573.2	-0.231734378\nXP_048576655.1	-0.271103752\nXP_032218428.1	0.482173066\nXP_032221486.2	0.103285142\nXP_048576807.1	0.07117777\nXP_048581659.1	0.056170338\nXP_048583155.1	0.398567853\nXP_048579782.1	0.320257799\nXP_032233535.2	-0.300406891\nXP_032238994.2	0.174322052\nXP_048586929.1	0.496428464\nXP_032242962.1	0.546274431\nXP_048579155.1	-0.041283088\nXP_048577749.1	0.033841828\nXP_048585778.1	-0.072698987\nXP_032233036.1	-0.291200411\nXP_032219089.1	0.321429862\nXP_048577526.1	0.095849176\nXP_048582833.1	-0.279934932\nXP_048576818.1	0.193884624\nXP_048584396.1	-0.371049255\nXP_032231668.2	0.191573233\nXP_001635681.1	-0.319407927\nXP_001631179.2	0.504472037\nXP_001632137.3	0.220766147\nXP_032225974.2	0.212388029\nXP_048589894.1	0.019763404\nXP_048582720.1	0.014019639\nXP_048576623.1	0.038641872\nXP_048583460.1	0.334552993\nXP_032239448.2	0.291711276\nXP_032239109.1	-0.656286806\nXP_048588411.1	-0.012909253\nXP_048588419.1	-0.014731915\nXP_032238563.1	0.471452399\nXP_048578039.1	-0.06974919\nXP_032237818.1	0.071747829\nXP_001622280.1	-0.035976761\nXP_032239932.2	0.547806011\nXP_001626458.2	0.00292594";
		document.Network_R2.genelist.value = id;
		var idn="XP_048588419.1	XP_048588411.1\nXP_001626458.2	XP_032237818.1\nXP_032238563.1	XP_032239932.2\nXP_032237818.1	XP_032239932.2\nXP_001626458.2	XP_032239932.2\nXP_032237818.1	XP_032238563.1\nXP_001626458.2	XP_032238563.1\nXP_048578039.1	XP_048588419.1\nXP_048578039.1	XP_048588411.1\nXP_032237818.1	XP_048588419.1\nXP_032237818.1	XP_048588411.1\nXP_001626458.2	XP_048588419.1\nXP_001626458.2	XP_048588411.1\nXP_048578039.1	XP_032237818.1\nXP_001626458.2	XP_048578039.1\nXP_048588411.1	XP_032239932.2\nXP_048588419.1	XP_032239932.2\nXP_048588411.1	XP_032238563.1\nXP_048588419.1	XP_032238563.1\nXP_048578039.1	XP_032239932.2\nXP_032237818.1	XP_001622280.1\nXP_032237818.1	XP_032239109.1\nXP_032239109.1	XP_032239932.2\nXP_032239109.1	XP_032238563.1\nXP_048578039.1	XP_032238563.1\nXP_001626458.2	XP_032239109.1\nXP_001622280.1	XP_032239932.2\nXP_001622280.1	XP_032239109.1\nXP_001626458.2	XP_001622280.1\nXP_001622280.1	XP_032238563.1\nXP_048576655.1	XP_048588491.1\nXP_032233535.2	XP_032233036.1\nXP_048588419.1	XP_032239109.1\nXP_048588411.1	XP_032239109.1\nXP_048588419.1	XP_001622280.1\nXP_048588411.1	XP_001622280.1\nXP_048576807.1	XP_032233535.2\nXP_032238263.1	XP_032239109.1\nXP_032218428.1	XP_048588491.1\nXP_048578039.1	XP_001622280.1\nXP_048578039.1	XP_032239109.1\nXP_048576655.1	XP_032221486.2\nXP_032221486.2	XP_048588491.1\nXP_032238263.1	XP_048588411.1\nXP_032218428.1	XP_001632137.3\nXP_032238263.1	XP_048588419.1\nXP_048576807.1	XP_032233036.1\nXP_048576655.1	XP_048581659.1\nXP_048576655.1	XP_048579782.1\nXP_048576623.1	XP_032239448.2\nXP_032238263.1	XP_032238563.1\nXP_048576807.1	XP_048588491.1\nXP_032238263.1	XP_048578039.1\nXP_032221486.2	XP_048581659.1\nXP_001631179.2	XP_032233036.1\nXP_032233535.2	XP_001631179.2\nXP_032238263.1	XP_032237818.1\nXP_032238263.1	XP_001622280.1\nXP_032221486.2	XP_048583155.1\nXP_048576818.1	XP_032233036.1\nXP_032231668.2	XP_032242962.1\nXP_048577749.1	XP_048577526.1\nXP_048588491.1	XP_048581659.1\nXP_032238263.1	XP_032239932.2\nXP_001631179.2	XP_048579782.1\nXP_001626458.2	XP_032238263.1\nXP_048588491.1	XP_048583155.1\nXP_048588491.1	XP_048579782.1\nXP_048588491.1	XP_032233535.2\nXP_032238994.2	XP_048588491.1\nXP_048576655.1	XP_048583155.1\nXP_032238994.2	XP_032239448.2\nXP_048576818.1	XP_048576807.1\nXP_048577526.1	XP_032221486.2\nXP_032221486.2	XP_048579782.1";
		document.Network_R2.networklist.value = idn;

		<?php /* 例子里的基因号只存在于 Nematostella vectensis 的共表达表里，点例子时
		   必须把物种一起定好，否则用户在别的物种上提交会得到空网络。原先只填两个
		   文本框、不碰物种选择器。 */ ?>
		var sel = document.Network_R2.elements['organism'];
		if (sel) {
			for (var i = 0; i < sel.options.length; i++) {
				if (sel.options[i].value === 'Nematostella vectensis') { sel.selectedIndex = i; break; }
			}
		}
	}
</script>
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

<legend><img src="../images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Dynamic Expression View</b></legend>
<p class="paleo-intro">Shows how a gene list's co-expression partners behave across conditions or developmental stages. <b>Run the co-expression analysis first</b> to obtain the gene pairs, then view their dynamic expression here.</p>

<?php if ($nxeNotice !== ''): ?>
<div class="gd-notice gd-warn" style="margin:18px 0"><?= $nxeNotice ?></div>
<?php endif; ?>

<?php /* 表单容器 */ ?>
<div class="network-form-container">
    <form action="network.expression.php" method="post" name="Network_R2" onSubmit="return checkquery()" encType="multipart/form-data">

        <?php /* 1. 选择目标物种 */ ?>
        <div class="form-section">
            <div class="section-title">1. Select Target Species</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Select Class</label>
					<select name="class" class="form-select"
					        onchange="window.location.href='network_expression.php?class='+encodeURIComponent(this.value)">
						<?php
						/* 切换 class 用 GET 重新加载本页（表单本身 POST 到 network.expression.php，
						 * 不能直接 submit），服务端据 ?class= 重建下面的物种列表 */
						if (!in_array($class, $nxeClassOrder, true)) {
							echo '<option value="' . htmlspecialchars($class) . '" selected="selected">'
							   . htmlspecialchars($class) . ' (unavailable)</option>';
						}
						foreach ($nxeClassOrder as $__c) {
							echo '<option value="' . $__c . '"' . ($__c === $class ? ' selected="selected"' : '') . '>'
							   . $__c . '</option>';
						}
						?>
					</select>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Species</label>
					<select name="organism" class="form-select">
						<?php /* 候选由数据库生成（原 printOptions("datalib") 指向的下拉框在本页并不存在） */ ?>
						<?= cnido_options(isset($nxeByClass[$class]) ? $nxeByClass[$class] : array(), $specie) ?>
					</select>
                </div>
            </div>
        </div>
        
        <?php /* 2. 输入共表达基因对 */ ?>
        <div class="form-section">
            <div class="section-title">2. Input Co-expressed Gene Pairs</div>
            <div class="textarea-container">
				<textarea name="networklist" class="form-textarea" placeholder="Enter gene names, e.g., GeneA GeneB&#10;GeneA GeneC&#10;GeneB GeneD"></textarea>&nbsp;&nbsp;&nbsp;Example: <a href="javascript:void(0)" onClick="NetworkR2()"><i>Nematostella vectensis</i></a>
            </div>
        </div>
        <?php /* 3. 输入基因表达比例 */ ?>
        <div class="form-section">
            <div class="section-title">3. Input Genes with Expression Ratio</div>
				<div class="textarea-container">
						<textarea name="genelist" class="form-textarea" placeholder="Enter gene expression values, e.g., GeneA 1.5&#10;GeneB -0.8&#10;GeneC 2.1"></textarea>&nbsp;&nbsp;&nbsp;Example: <a href="javascript:void(0)" onClick="NetworkR2()"><i>Nematostella vectensis</i></a>
				</div>
        </div>
        
        <?php /* 4. 选择基因表达比例阈值 */ ?>
        <div class="form-section">
            <div class="section-title">4. Select Threshold for Gene Expression Ratio</div>
				<table><tr><td>
				<input name = "red_low" style="width:50px;font-size:14px"  value="1">≤ <font color="red">Red</font> ≤<input name = "red_high" style="width:50px;font-size:14px"  value="100">&nbsp&nbsp&nbsp
				<input name = "blue_low" style="width:50px;font-size:14px"  value="-10">≤ <font color="blue">Blue</font> ≤<input name = "blue_high" style="width:50px;font-size:14px"  value="-1">
				</td></tr></table>
            <div style="margin-top: 15px; font-size: 16px; color: #64748b; font-style: italic;">
                <strong>Note:</strong> Red indicates upregulated genes, blue indicates downregulated genes.
            </div>
        </div>
        
        <?php /* 按钮 */ ?>
        <div class="button-group">
            <button type="submit" class="btn-submit">
                🚀 Submit Analysis
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
