<?php
/* CALLS:
    settings.js
*/ 
require_once('../config.php');
require_once('../api.php');

$action = $_POST['action'];
$uid = intval($_SESSION['uid']);

$obj = new facebook_ajax();

switch ($action) {
    case 'bind':
        $result = $obj->bind($uid);
        break;
}

echo json_encode($result);

class facebook_ajax {
    public function bind($uid) {
        $fb = new Facebook();

        if ($fb->bindedByUID($uid)) {
            return array('success' => false, 'reason' => 'already binded');
        }

        if ($fb->binded()) {
            return array('success' => false, 'reason' => 'binded by someone');
        }

        $ok = $fb->bindToFacebook($uid);
        return array('success' => $ok);
    }
};

