<?php
if (isset($_GET['genelist']) ) {$genelist = $_GET['genelist'];} else {$genelist = $_POST['genelist'];}
if (isset($_GET['organism']) ) {$organism = $_GET['organism'];} else {$organism = $_POST['organism'];}

$amount=array();
/* 一个方向都没勾时表单里根本没有 group[]，原来会落到 $_POST['group'] 上取一个
   不存在的下标（Notice），这里补一个空数组的分支。 */
if (isset($_GET['group']) ) {$amount = $_GET['group'];}
elseif (isset($_POST['group'])) {$amount = $_POST['group'];}
else {$amount = array();}
if (isset($_GET['category']) ) {$category = $_GET['category'];} elseif (isset($_POST['category'])) {$category = $_POST['category'];} else {$category = '';}
if (!is_array($amount)) { $amount = array($amount); }

/* 共用的网络图工具（安全 JSON 编码 / 批量注释 / hover 卡片 / 导出）。
   下面解析物种、给节点取注释都要用它。 */
require_once(__DIR__ . '/../includes/network_graph.php');

if (trim($genelist)==""){
	echo "<script>alert(\"Sorry, you didn\'t input any gene, please try again!\");</script>";
	echo "<script>window.location =\"network.php\";</script>";
	exit();
}

$glist=preg_replace("/\s+/","\n",$genelist); 
$list=explode("\n",$glist);
$list=array_unique($list);


if(count($list)>10){
echo "<script>alert(\"More than 10 genes were submitted; please submit 10 or fewer.\");</script>";
	echo "<script>window.location =\"network.php\";</script>";
	exit();

}
$job = date("YMdHis");
$ids = '';   /* 下面用 .= 累加，不先初始化会有 notice */
$fp0 = fopen("../tmp/$job.txt","w");
foreach($list as $id)
{ 
  $id="$id\n";
  $ids.="$id &nbsp";
  fwrite($fp0,$id);
}
fclose($fp0);
/* 两个方向都没勾时下面两个抓取块一个都不跑，$edge 必然为空。先给 0，
   否则 $positive / $negative 是未定义变量，后面读它们会出 Notice。 */
$positive = 0; $negative = 0;
foreach($amount as $value)
{
  if($value == "positive"){
    $positive=1;
  }elseif($value == "negative"){
    $negative=1;
  }
}
/* 原来这里有一句 trim($spe); —— $spe 从未赋值，纯属遗留，删掉 */

##### Fetch PPI info from databases #####
$node = array();
$node1 = array();
$node2 = array();
$edge = array();
$tmp_edge = array();
/* 下面第一层邻居要做 top-K 截断，这里先复位「本页是否被截断」的标记
   （cnido_net_trunc_flag 是静态的，同一请求里只在这里复位一次）。 */
cnido_net_trunc_flag(false);

$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
/* 物种解析。原来直接取 abbr 表的第三列，一旦物种名对不上（少写、写成下划线
   形式、或没带参数），$species 就是空串，表名退化成 "_coexpress_positive"，
   查询直接失败，最后走到「没有互作蛋白」的提示 —— 原因完全看不出来。
   现在解析不出来就明确说一次再退回首页。 */
$ctx = cnido_net_ctx($conn, isset($organism) ? $organism : '');
if (!$ctx) {
	echo "<script>alert(" . cnido_net_js('Sorry, unknown species: ' . (string)$organism
	     . '. Please pick a species from the list.') . ");</script>";
	echo "<script>window.location =\"network.php\";</script>";
	exit();
}
$species = $ctx['abbr1'];
$latin   = $ctx['latin'];
$pos=$species."_coexpress_positive";
$neg=$species."_coexpress_negative";

if($positive == 1)
{
	foreach($list as $gene)
	{
	$hash[$gene]=2;
	}
	/* 第一层邻居按每个查询基因 top-K 截断（见 includes/network_graph.php 的
	   CNIDO_NET_TOP_K 说明）。原来是
	       SELECT * FROM $pos WHERE geneA in (…) or geneB in (…)
	   完全没有上限：输入 10 个枢纽基因时一次拉回 57983 行，第二层再把这
	   5800 个 id 拼进 IN（110 KB 的 SQL），整页 5807 节点 / 115898 边 /
	   6.65 秒 / 29 MB。截断后同一页是 130 节点量级。
	   顺带修掉一个注入点：原写法把 textarea 里的基因号**未转义**拼进 IN，
	   现在由 cnido_net_partner_rows() 逐个数 mysqli_real_escape_string。 */
	$rows_p = cnido_net_first_level($conn, $pos, $list, CNIDO_NET_TOP_K, true);
	foreach ($rows_p as $result) {
			array_push($node,$result[1]);
			array_push($node,$result[0]);
			$hash1[$result[0]]=2;
			$hash1[$result[1]]=2;
			$element = cnido_net_edge_line(array('source' => $result[0], 'target' => $result[1], 'color' => '#EDA1ED', 'strength' => 10, 'kind' => 'positive'));    
			array_push($edge,$element);
			$tmp_value=$result[0]."\t".$result[1]."\t".$result[2]."\t".$result[3]."\t"."positive\n";
			array_push($tmp_edge,$tmp_value);
	
		}
	$node = array_flip(array_flip($node));
	$arraykey = "''";
	
	$hash=array_change_key_case($hash,CASE_UPPER); ###以大写字母返回数组的键
	foreach($node as $sub_gene){
		$sub_gene=strtoupper($sub_gene);  ###全部大写
		if(isset($hash[$sub_gene])){continue;}
		$arraykey=$arraykey.",'$sub_gene'";
	}
	$query2=mysqli_query($conn,"SELECT * FROM $pos WHERE geneA in ($arraykey) and geneB in ($arraykey)"); ##用and保证检索到的relationship一定在一级node之间，避免result的栈太大
	while ($result=mysqli_fetch_row($query2)){
			//if($hash[$result[1]] || $hash[$result[0]] ){continue;}  ##如果出现第一级的node,直接跳到下一循环
			/* 不满足条件时必须 continue：原来 array_push 写在 if 外面，于是每
			   一行都会把**上一轮**留下的 $element / $tmp_value 再推一遍 —— 第一行
			   推的是上面一级边的残留（strength 10），后面每行都重复上一条边。
			   结果是同一对基因之间出现两条平行边（粗细不同），表格里也有重复行。 */
			if(!($hash1[$result[0]] && $hash1[$result[1]])){ continue; }
			$element = cnido_net_edge_line(array('source' => $result[0], 'target' => $result[1], 'color' => '#EDA1ED', 'strength' => 5, 'kind' => 'positive'));
			$tmp_value=$result[0]."\t".$result[1]."\t".$result[2]."\t".$result[3]."\t"."positive\n";
			array_push($edge,$element);
			array_push($tmp_edge,$tmp_value);
	}

}
##negative
if($negative ==1)
{
	foreach($list as $gene)
	{
		$hash[$gene]=2;
	}
	/* 同正向分支：无上限的 OR 查询改成逐基因 top-K。负向表的 pcc 是负值
	   （实测 [-0.721, -0.123]），cnido_net_partner_rows() 会按 ASC 取，
	   也就是「最负相关」的排前面。 */
	$rows_n = cnido_net_first_level($conn, $neg, $list, CNIDO_NET_TOP_K, false);
	foreach ($rows_n as $result) {
			array_push($node1,$result[1]);
			array_push($node1,$result[0]);
			$hash2[$result[0]]=2;
			$hash2[$result[1]]=2;
			$element = cnido_net_edge_line(array('source' => $result[0], 'target' => $result[1], 'color' => '#6FB1FC', 'strength' => 10, 'kind' => 'negative'));    
			array_push($edge,$element);
			$tmp_value=$result[0]."\t".$result[1]."\t".$result[2]."\t".$result[3]."\t"."negative\n";
			array_push($tmp_edge,$tmp_value);
	}
	
	$node1 = array_flip(array_flip($node1));
	$arraykey1 = "''";
	
	$hash=array_change_key_case($hash,CASE_UPPER); ###以大写字母返回数组的键
	foreach($node1 as $sub_gene){
		$sub_gene=strtoupper($sub_gene);  ###全部大写
		if(isset($hash[$sub_gene])){continue;}
		$arraykey1=$arraykey1.",'$sub_gene'";
	}
	$query5=mysqli_query($conn,"SELECT * FROM $neg WHERE geneA in ($arraykey1) and geneB in ($arraykey1)");
	while ($result=mysqli_fetch_row($query5)){
			//if($hash[$result[1]] || $hash[$result[0]] ){continue;}  ##跳到下一循环
		if(!($hash2[$result[0]] && $hash2[$result[1]])){ continue; }   ##同上：不满足条件不能把上一轮的边再推一次
		$element = cnido_net_edge_line(array('source' => $result[0], 'target' => $result[1], 'color' => '#6FB1FC', 'strength' => 5, 'kind' => 'negative'));
		$tmp_value=$result[0]."\t".$result[1]."\t".$result[2]."\t".$result[3]."\t"."negative\n";
		array_push($edge,$element);
		array_push($tmp_edge,$tmp_value);
	}

}

/* 两个方向都跑完了，取值给下面的页面用（tail.list.inc 会提示用户）：
   有些枢纽基因的伙伴超过 CNIDO_NET_TOP_K，页面显示的不是完整网络。 */
$net_truncated = cnido_net_trunc_flag();

$nodeall=array_merge($node,$node1);
$nodeall = array_flip(array_flip($nodeall));
$edge = array_flip(array_flip($edge));
$tmp_edge = array_flip(array_flip($tmp_edge));

$edge_num=count($edge);
$node_num = count($nodeall);
shuffle($nodeall);
if ($edge_num == 0){
	/* 空结果有两种原因，不能都按「查不到」报：两个方向复选框一个都没勾时
	   根本没有发过查询，原来也报「没有找到与所提交基因互作的蛋白」，
	   把用户自己的输入问题说成了数据库里没有。 */
	if (!$positive && !$negative) {
		echo "<script>alert(\"No interaction type was selected: tick Positive and/or Negative, then submit again.\");</script>";
	} else {
		echo "<script>alert(\"No protein interacting with the submitted gene was found.\");</script>";
	}
	echo "<script>window.location =\"network.php\";</script>";
	exit();
}
###### Visualizing nodes&edges #######
$conn1 = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');

/* 注释一次性批量取（panther → ipr → go → nr，按物种实际有的表取）。 */
$anno = cnido_net_annotations($conn1, $species, $nodeall);

/* 度数：每个节点连了几条边，用于节点大小和悬停提示。 */
$deg = array();
foreach ($tmp_edge as $line) {
	$p = explode("\t", $line);
	if (count($p) < 2) { continue; }
	foreach (array($p[0], $p[1]) as $g) {
		$k = strtoupper($g);
		$deg[$k] = isset($deg[$k]) ? $deg[$k] + 1 : 1;
	}
}

/* 节点行。旧写法是
       { data: { ..., annotation: '$result1[2]' } }
   把 <ABBR>_panther.anno 原样插进单引号里。这个字段里**本来就有单引号**
   （全站 361 万个 anno 值里 9202 个含单引号：5'-NUCLEOTIDASE、3'-5'
   EXONUCLEASE、RNA 2',3'-CYCLIC PHOSPHODIESTERASE……），而且这些基因真的会
   出现在网络里（LPERT 39/39、MCAPI 33/33、SSIDE 44、HVULG 53）。一旦命中，
   单引号就把 JS 字符串截断，整个 cytoscape({...}) 抛 SyntaxError，**图完全画不
   出来**；下面的节点表格是普通 HTML，照常渲染，所以现象看着像「图是空的」。
   现在一律走 cnido_net_node_line()，内部用 json_encode 编码全部字段。

   另外这里改为按 $nodeall 建节点（不再用 _locus 过滤）：旧代码只给 _locus 里
   存在的基因建节点，不在那里的基因没有节点、但边照样引用它，cytoscape 会报
   「找不到 source 节点」；_locus 若对同一 mRNA 返回多行，还会写出重复的节点 id。 */
$fp1 = fopen("../tmp/node$job.inc","w");
foreach($nodeall as $g)
{
	$is_query = isset($hash[strtoupper($g)]);
	$d = isset($deg[strtoupper($g)]) ? $deg[strtoupper($g)] : 0;
	/* 查询基因仍是大黄椭圆；互作基因是绿八边形，大小随度数略增 —— 原来所有
	   互作节点都是 weight 15（同一个尺寸），枢纽和只有一个连接的叶子长得一样，
	   图一密就看不出结构。 */
	fwrite($fp1, cnido_net_node_line(array(
		'id'         => $g,
		'name'       => $g,
		'weight'     => $is_query ? 70 : (15 + min($d, 20)),
		'color'      => $is_query ? '#E1E100' : '#86B342',
		'shape'      => $is_query ? 'ellipse' : 'octagon',
		'annotation' => cnido_net_summary(isset($anno[$g]) ? $anno[$g] : array()),
		'degree'     => $d,
	)));
}


//for($i=0;$i<$node_num;$i++)
//{
//	$query=mysqli_query("SELECT * FROM orthology_annotation WHERE gene = '$nodeall[$i]'");
//    $result=mysqli_fetch_row($query);	
//	if ($hash[$nodeall[$i]]){
//		$element = "\t\t{ data: { id: '$nodeall[$i]', name: '$nodeall[$i]', weight: 70, faveColor: '#E1E100', faveShape: 'ellipse', annotation: '$result[3]' } },\n";
//		fwrite($fp1,$element);	
//	}else{
//		$element = "\t\t{ data: { id: '$nodeall[$i]', name: '$nodeall[$i]', weight: 15, faveColor: '#86B342', faveShape: 'octagon', annotation: '$result[3]' } },\n";
//		fwrite($fp1,$element);
//	}
//}

$fp2 = fopen("../tmp/edge$job.inc","w");
foreach($edge as $value){
	fwrite($fp2,$value);
}

$fp3 = fopen("../tmp/tmp_edge$job.inc","w");
foreach($tmp_edge as $value){
	fwrite($fp3,$value);
}

$fp4 = fopen("../tmp/tmp_allnode$job.inc","w");
foreach($nodeall as $value){
	fwrite($fp4,$value."\n");
}

fclose($fp1);
fclose($fp2);
fclose($fp3);
fclose($fp4);
$gene="<br>$ids";
?>

<?php
//if($category == "global")
//{
	include("head.inc");
	include("../tmp/node$job.inc");
	echo "],\n edges: [\n";
	include("../tmp/edge$job.inc");
	include("tail.list.inc");

	/* 悬停卡片的数据和脚本。tail.list.inc 里调用的 cnidoNetBindTips() 是在
	   jQuery 的 dom-ready 回调里执行的，而所有内联脚本都会在那之前解析完，
	   所以放在 tail.list.inc 之后数据也已经就位。 */
	cnido_net_payload($anno, $latin);
	cnido_net_tip_js();

//if($category=="tissue")
//{
// if($tissue=$_POST['tissue'] or $tissue=$_GET['tissue']);
// if($fpkm_cut=$_POST['fpkm_cut'] or $fpkm_cut=$_GET['fpkm_cut']);
// print "<meta http-equiv=\"REFRESH\" content=\"1; url=tissue_specific.php?job=$job&species=$spe&tissue=$tissue&fpkm_cut=$fpkm_cut\" />";
//
//}
//if($category=="stress")
//{
// $tissue=$_POST['tissue1'];
// $stress=$_POST['stress1'];
// $time=$_POST['time'];
// print "<meta http-equiv=\"REFRESH\" content=\"1; url=stress_change.php?job=$job&species=$spe&tissue=$tissue&stress=$stress&time=$time\" />";
//}
?>

