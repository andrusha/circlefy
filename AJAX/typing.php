<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../api.php');

$cid = $_POST['cid'];
$response = $_POST['response'];
$uname = $_SESSION['uname'];
$action = "typing";

$fp = fsockopen("localhost", 3333, $errno, $errstr, 30);
$insert_string = '{"cid":"'.$cid.'","action":"'.$action.'","response":"'.$response.'","uname":"'.$uname.'"}'."\r\n";
fwrite($fp,$insert_string);
fclose($fp);
echo 1;
?>
