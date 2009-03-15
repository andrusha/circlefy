<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<title>AJAX File uploader</title>
		<script type="text/javascript" src="uploader.js" ></script>
			<style type="text/css">
			body{ font-family: verdana; font-size: 12px;}
			a{ font-family:verdana; font-size:12px;text-decoration:none; color:#000000;}
			a:hover{font-family:verdana;font-size: 12px;text-decoration:underline; color: #FF0000;}
			.link{ border:0px; padding:0px; margin:0px; text-decoration:none;}
			
		</style>
	</head>
	<body>
<?php
	// This is the folder where file are uploaded
	//$uploadDirectory = "testdir";    for security reasons,  hardcode the name of the directrory in imageupload.php
	require_once("AjaxFileUploader.inc.php");
	$ajaxFileUploader = new AjaxFileuploader($uploadDirectory="/pic/");	
	echo $ajaxFileUploader->showFileUploader('id1');
	echo $ajaxFileUploader->showFileUploader('id2');
	echo $ajaxFileUploader->showFileUploader('id4');
?>

</body>
</html>
