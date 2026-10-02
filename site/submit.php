<?php
if (isset($_GET['name']) ) {$name = $_GET['name'];} else {$name = $_POST['name'];}
if (isset($_GET['email']) ) {$email = $_GET['email'];} else {$email = $_POST['email'];}
if (isset($_GET['subject']) ) {$subject = $_GET['subject'];} else {$subject = $_POST['subject'];}
if (isset($_GET['message']) ) {$message = $_GET['message'];} else {$message = $_POST['message'];}

if (trim($name)=="" || trim($email)=="" || trim($subject)=="" || trim($message)==""){
	echo "<script>alert(\"Please complete all required fields (*).\");</script>";
	echo "<script>window.location =\"contact.php\";</script>";
	/* 没有 exit 的话，上面只是「提示并跳走」，脚本仍会往下走：照样 fopen 写一个
	   七个字段全空的 .txt 到 ./submit/，再把「谢谢」页印出来。./submit/ 里那 5 份
	   空模板就是这么来的 —— 机器人直接 POST 本页，一秒钟能攒一堆。 */
	exit;
}

/* 2026-09-29：原来写 ./submit/，但那个目录是 0755 jackie:jackie，而 apache 以
   www-data 跑 —— fopen 一律 Permission denied，每一条留言都被静默丢掉，确认页
   却照印「已经收到」。整个 docroot 里 www-data 唯一写得进去的位置是 tmp/（HTTP
   上 403，不会外泄），所以写到 tmp/submit-<时间戳>.txt。
   不再往 tmp/ 里另开子目录：子目录会被建成 www-data:www-data 0755，jackie 不在
   www-data 组，连删都删不掉。文件直接落在 jackie 自己的 tmp/ 下，双方都能动。 */
$job = date("YMdHis");
$__dir = __DIR__ . '/tmp';
$__file = $__dir . '/submit-' . $job . '.txt';
for ($__i = 2; $__i <= 200 && file_exists($__file); $__i++) {
	$__file = $__dir . '/submit-' . $job . '-' . $__i . '.txt';   /* 同一秒的第二次提交不覆盖第一次 */
}
$__saved = false;
$__ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
$fp0 = @fopen($__file,"w");
if ($fp0) {
	$info = "Name: $name\nEmail: $email\nSubject: $subject\nMessage: $message\n"
	      . "Received: " . date("Y-m-d H:i:s") . "  IP: $__ip\n";
	$__saved = (fwrite($fp0,$info) !== false);
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
<title>Message sent - CnidoSite</title>
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
	<?php if ($__saved): ?>
	<h3 align="center"><strong>Thank you &mdash; your message has been recorded for the CnidoSite team.</strong></h3>
	<?php else: ?>
	<h3 align="center"><strong>Thank you &mdash; but we could not store your message on the server.</strong></h3>
	<?php endif; ?>
	<br>
	<h3 align="center"><strong>To be sure of a reply, please write to <a href="mailto:longjunwu@ust.hk">longjunwu@ust.hk</a>.</strong></h3>
	<h3 align="center"><strong><a href="/contact.php">click here to return</a></strong></h3>
</div>
</div>
</div>

<?php
	include "./Webpage_components.php";
	print $footer;
?>
</body>
</html>
