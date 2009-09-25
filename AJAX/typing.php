<?php
/* CALLS:
	homepage.phtml
*/
$cid = $_POST['cid'];
$action = "typing";
$response = null;

$fp = fsockopen("localhost", 3333, $errno, $errstr, 30);
$insert_string = '{"cid":"'.$cid.'","action":"'.$action.'","response":"'.$response.'"}'."\r\n";
fwrite($fp,$insert_string);
fclose($fp);
echo 1;
?>
