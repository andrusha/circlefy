<?php
/* CALLS:
    login.js
*/ 
require('../config.php');
require('../api.php');
require('../modules/User.php');

define('FUCKED_UP', json_encode(array('status' => 'NOT_REGISTERED')));
define('SUCCESS', json_encode(array('status' => 'REGISTERED')));

$user = $_POST['user'];
$password = $_POST['pass'];

if (!$user || !$password) {
    echo FUCKED_UP;
    exit();
}

$class = new User();
if ($class->logIn($user, $password)) {
    echo SUCCESS;
} else {
    echo FUCKED_UP;
}

