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
    case 'check':
        $result = $obj->checkWithInfo($uid);
        break;
    case 'share':
        $message = $_POST['message'];
        $link = $_POST['link'];
        $name = $_POST['name'];
        $caption = $_POST['caption'];
        $result = $obj->share($message, $caption, $link, $name);
        break;
}

echo json_encode($result);

class facebook_ajax {
    private $fb;

    public function __construct() {
        $this->fb = new Facebook();
    }

    public function bind($uid) {
        $ok = $this->fb->bindToFacebook($uid);

        $check = $this->check($uid);
        if (!$check['success'])
            return $check;

        return array('success' => $ok);
    }

    public function check($uid) {
        if ($this->fb->bindedByUID($uid)) {
            return array('success' => false, 'reason' => 'already binded');
        }

        if ($this->fb->binded()) {
            return array('success' => false, 'reason' => 'binded by someone');
        }

        return array('success' => true);
    }

    public function checkWithInfo($uid) {
        $check = $this->check($uid);
        if (!$check['success'])
            return $check;

        $info = $this->fb->getInfoStraight();
        $data = array(
            'fb_uid' => $info['uid'],
            'uname' => $info['id'],
            'fname' => $info['first_name'],
            'lname' => $info['last_name']);

        return array('success' => true, 'data' => $data);
    }

    public function share($message, $caption, $link, $name) {
        $data = array('message' => $message, 'caption' => $caption, 'description' => '', 'link' => $link,
            'name' => $name, 'picture' => 'http://andrew.tap.info/images/flat/tap_square.png');
        $this->fb->postStatus($data);

        return array('success' => true);
    }
};

