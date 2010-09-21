<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');
require('../api.php');

$cid = $_POST['cid'];
$response = $_POST['response'];
$user = unserialize($_SESSION['user']);

Comet::send('message', array('cid' => $cid, 'action' => 'typing', 'response' => $response, 'uname' => $user->uname));
echo 1;
