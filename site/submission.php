<?php
if (isset($_GET['name']) ) {$name = $_GET['name'];} else {$name = $_POST['name'];}
if (isset($_GET['position']) ) {$position = $_GET['position'];} else {$position = $_POST['position'];}
if (isset($_GET['organization']) ) {$organization = $_GET['organization'];} else {$organization = $_POST['organization'];}
if (isset($_GET['email']) ) {$email = $_GET['email'];} else {$email = $_POST['email'];}
if (isset($_GET['species']) ) {$spe = $_GET['species'];} else {$spe = $_POST['species'];}
if (isset($_GET['data_information']) ) {$data_information = $_GET['data_information'];} else {$data_information = $_POST['data_information'];}
if (isset($_GET['data_description']) ) {$data_description = $_GET['data_description'];} else {$data_description = $_POST['data_description'];}
if (isset($_GET['link']) ) {$link = $_GET['link'];} else {$link = $_POST['link'];}

if (trim($name)=="" || trim($position)=="" || trim($organization)=="" || trim($email)=="" || trim($spe)=="" || trim($data_information)=="" || trim($link)==""){
	echo "<script>alert(\"Please complete all required fields (*).\");</script>";
	echo "<script>window.location =\"submit_comments.php\";</script>";
	/* 没有 exit 的话，提示之后脚本继续往下走，照样写一个全空的 .txt 到 ./submit/，
	   再把「谢谢」页印出来。./submit/ 里那 5 份空模板就是这么来的。 */
	exit;
}

$job = date("YMdHis");
/* 写不进去时必须说「没记下」：原来不检查 fopen 的返回值，磁盘满或目录不可写时
   访客照样看到「谢谢，我们会尽快处理」，而 ./submit/ 里什么都没有。

   2026-09-29：写的目标从 ./submit/ 换成 tmp/。./submit/ 是 0755 jackie:jackie，
   而 apache 以 www-data 跑 —— fopen 一律 Permission denied，所以**这一页从来没有
   成功记录过任何一条提交**，确认页却一直印「已记录」。docroot 里 www-data 唯一写
   得进去的位置是 tmp/（HTTP 上 403，不外泄），与 contact 那条链的 submit.php 用
   同一个目标。文件名带 submission- 前缀，两者不会混。 */
$__dir  = __DIR__ . '/tmp';
$__file = $__dir . '/submission-' . $job . '.txt';
for ($__i = 2; $__i <= 200 && file_exists($__file); $__i++) {
	$__file = $__dir . '/submission-' . $job . '-' . $__i . '.txt';   /* 同一秒的第二次提交不覆盖第一次 */
}
$fp0 = @fopen($__file,"w");
$saved = ($fp0 !== false);
if(trim($data_description)==""){$data_description="";}else{$data_description="Data description: $data_description\n";}
$__ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
$info="Name: $name\nPosition: $position\nOrganization: $organization\nEmail: $email\nSpecies: $spe\nData information: $data_information\nLink to upload data: $link\n"
    . "Received: " . date("Y-m-d H:i:s") . "  IP: $__ip\n";
if($saved){
	/* 第 9 行读进来的 Data Description 原来算完就丢了 —— $info 里没有它，
	   于是访客填的说明一个字都没落盘。表单上那个字段是选填的，但填了就该存。 */
	fwrite($fp0,$info . $data_description);
	fclose($fp0);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Data Submission - CnidoSite</title>
<meta name="keywords" content="" />
<meta name="description" content="" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
</head>

<body>
<?php
	include "./Webpage_components.php";
	print $header;
?>
<div id="tempatemo_content_wrapper">
<div id="templatemo_content">
<div id="column">
<?php if ($saved): ?>
	<h3 align="center"><strong>Thank you &mdash; your submission has been received.</strong></h3>
	<br>
	<h3 align="center"><strong>We will notify you by email when the data you submitted is released publicly on CnidoSite.</strong></h3>
<?php else: ?>
	<h3 align="center"><strong>Your submission could not be recorded.</strong></h3>
	<br>
	<h3 align="center"><strong>Please send the same details to <a href="mailto:longjunwu@ust.hk">longjunwu@ust.hk</a> instead, so that nothing is lost.</strong></h3>
<?php endif; ?>
	<h3 align="center"><strong><a href="/submit_comments.php">click here to return</a></strong></h3>
</div>
</div>
</div>

<?php
	include "./Webpage_components.php";
	print $footer;
?>
</body>
</html>
