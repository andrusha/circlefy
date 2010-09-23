<?php
/* CALLS:
    settings.js
*/ 
require_once('../../config.php');
require_once('../../api.php');

class facebook_ajax extends Base {
    private $fb;

    public function __construct() {
        $this->need_db = false;
        $this->view_output = 'JSON';
        parent::__construct();

        $this->fb = new Facebook();
        $action = $_POST['action'];

        switch ($action) {
            case 'bind':
                $this->data = $this->bind($this->user);
                break;
            case 'check':
                $this->data = $this->checkWithInfo($this->user);
                break;
            case 'share':
                $message = $_POST['message'];
                $link    = $_POST['link'];
                $name    = $_POST['name'];
                $caption = $_POST['caption'];
                $this->data = $this->share($message, $caption, $link, $name);
                break;
        }
    }

    private function bind(User $user) {
        $ok = $this->fb->bindToFacebook($user);

        $check = $this->check($user);
        if (!$check['success'])
            return $check;

        return array('success' => $ok);
    }

    private function check(User $user) {
        if (Facebook::isBinded($user)) {
            return array('success' => false, 'reason' => 'already binded');
        }

        if ($this->fb->exists()) {
            return array('success' => false, 'reason' => 'binded by someone');
        }

        return array('success' => true);
    }

    private function checkWithInfo(User $user) {
        $check = $this->check($user);
        if (!$check['success'])
            return $check;

        $info = $this->fb->info;
        $data = array(
            'fb_uid' => $this->fb->fuid,
            'uname' => $info['id'],
            'fname' => $info['first_name'],
            'lname' => $info['last_name']);

        return array('success' => true, 'data' => $data);
    }

    private function share($message, $caption, $link, $name) {
        $data = array('message' => $message, 'caption' => $caption, 'description' => '', 'link' => $link,
            'name' => $name, 'picture' => 'http://andrew.tap.info/images/flat/tap_square.png');
        $this->fb->postStatus($data);

        return array('success' => true);
    }
};

$f = new facebook_ajax();
