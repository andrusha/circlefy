<?php
/* CALLS:
	sign_up.js
*/
require('../../config.php');
require('../../api.php');

class sign_up extends Base {
    public function __construct() {
        $this->need_db = false;
        $this->view_output = 'JSON';
        parent::__construct();

        $uname = $_POST['uname'];
        $fname = $_POST['fname'];
        $lname = $_POST['lname'];
        $email = $_POST['email'];
        $pass  = $_POST['pass'];
        $facebook = $_POST['facebook'] == 'true' ? true : false;
       
        $this->data = $this->process($uname,$fname,$lname,$email,$pass,$facebook);
    }

	function process($uname,$fname,$lname,$email,$pass,$facebook) {
        $this->user->userificateGuest($uid, $uname, $fname, $lname, $email, $pass);
        if ($facebook) {
            $fb = new Facebook();
            $fb->bindToFacebook($this->user);
        }

        //Mail::send_welcome_mail();

		return array('success' => 1);
	}
};

$a = new sign_up();
