<?php
/* CALLS:
    modal.js
*/ 

class ajax_login extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
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
        if (!$uname || !$pass)
            return array('status' => 'NOT_REGISTERED');

        if (Auth::logIn($uname, $pass, false)->id !== null)
            return array('status' => 'REGISTERED');
        return array('status' => 'NOT_REGISTERED');
    }

    private function facebook() {
        $fb = new Facebook();
        $exists = $fb->exists();
        
        if ($exists) {
            Auth::logIn('', '', true);
            return array('status' => 'REGISTERED');
        } else {
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
