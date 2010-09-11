<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');
require('../api.php');

$cid = $_POST['cid'];
$response = $_POST['response'];
$uname = $_SESSION['uname'];

Comet::send('message', array('cid' => $cid, 'action' => 'typing', 'response' => $response, 'uname' => $uname));
echo 1;
?>
