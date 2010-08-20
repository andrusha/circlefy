<?php
/* CALLS:
	homepage.phtml
*/
$usage = <<<EOF
Usage:
cid: id of the active combo
EOF;

session_start();
require('../config.php');
require('../sql.php');
require('../api.php');
require('../modules/Convos.php');

$uid = $_SESSION['uid'];
$mid = $_POST['cid'];

if (intval($mid)) {
	$convos = new Convos();
	$convos->makeActive($uid, $mid);
    $result = $convos->getActiveOne($uid, $mid);
	echo json_encode(array('successful' => 1, 'data' => $result));
} else {
    echo json_encode(array('successful' => 0, 'data' => array()));
}
