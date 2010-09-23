<?php
/* CALLS:
    login.js
*/ 
require_once('../../config.php');
require_once('../../api.php');

define('FUCKED_UP', array('status' => 'NOT_REGISTERED'));
define('SUCCESS', array('status' => 'REGISTERED'));

class login extends Base {
    public function __construct() {
        $this->need_db = false;
        $this->view_output = 'JSON';
        parent::__construct();

        switch ($_POST['type']) {
            case 'user':
                $user = $_POST['user'];
                $pass = $_POST['pass'];
                $this->data = $this->normal($user, $pass);
                break;

            case 'facebook':
                $this->data = $this->facebook();
                break;
        }
    }

    private function normal($uname, $pass) {
        if (!$user || !$pass)
            return FUCKED_UP;

        if (Auth::logIn($user, $password, false) !== null)
            return SUCCESS;
        return FUCKED_UP;
    }

    private function facebook() {
        $fb = new Facebook();
        $exists = $fb->exists();
        
        if ($exists)
            return SUCCESS;
        else {
            $user_info = $fb->info;
            $data = array(
                'fb_uid' => $fb->fuid,
                'uname' => $user_info['id'],
                'fname' => $user_info['first_name'],
                'lname' => $user_info['last_name']);

            return array('status' => 'NOT_REGISTERED', 'data' => $data);
        }
    }
};

$a = new login();
