<?php
/* CALLS:
    login.js
*/ 
require_once('../config.php');
require_once('../api.php');

define('FUCKED_UP', json_encode(array('status' => 'NOT_REGISTERED')));
define('SUCCESS', json_encode(array('status' => 'REGISTERED')));

$type = $_POST['type'];

if ($type == 'user') {
    $user = $_POST['user'];
    $password = $_POST['pass'];

    if (!$user || !$password) {
        echo FUCKED_UP;
        exit();
    }

    $class = new User();
    if ($class->logIn($user, $password, false))
        echo SUCCESS;
    else
        echo FUCKED_UP;
} else if ($type == 'facebook') {
    $fb = new Facebook();

    $exists = $fb->exists();
    
    if ($exists)
        echo SUCCESS;
    else {
        $user_info = $fb->info;
        $data = array(
            'fb_uid' => $fb->fuid,
            'uname' => $user_info['id'],
            'fname' => $user_info['first_name'],
            'lname' => $user_info['last_name']);

        echo json_encode(array('status' => 'NOT_REGISTERED', 'data' => $data));
    }
}
