<?php
/* CALLS:
    modal.js
*/ 

class ajax_facebook extends Base {
    protected $view_output = 'JSON';
    private $fb;

    public function __invoke() {
        $this->fb = new Facebook();
        $action = $_POST['action'];

        switch ($action) {
            case 'create':
                $uname = $_POST['uname'];
                $pass  = $_POST['pass'];
                $this->data = $this->create($uname, $pass);
                break;
            case 'check':
                $this->data = $this->checkWithInfo();
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

    private function create($uname, $pass) {
        $check = $this->check();
        if (!$check['success'])
            return $check;

        $this->fb->createWithFacebook($uname, $pass);

        return array('success' => true);
    }

    private function check() {
        if (!$this->fb->fuid)
            return array('success' => false, 'reason' => 'no_fb');

        if ($this->fb->exists())
            return array('success' => false, 'reason' => 'exists');

        return array('success' => true);
    }

    private function checkWithInfo() {
        $check = $this->check();
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
