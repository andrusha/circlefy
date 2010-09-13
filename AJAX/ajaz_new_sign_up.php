<?php
/* CALLS:
	sign_up.js
*/
require('../config.php');
require('../api.php');

session_start();

$sign_up_object = new ajaz_new_sign_up();

$flag = $_POST['signup_flag'];

/* These are the sign up variables that are associate 1:1 to the HTML form names and JavaScript variables */

$uname = $_POST['uname'];
$fname = $_POST['fname'];
$lname = $_POST['lname'];
$email = $_POST['email'];
$pass = $_POST['pass'];
$facebook = $_POST['facebook'];

if($flag == 'normal' || $flag == 'signup_function();'){
	$sign_up_results = $sign_up_object->process_sign_up($uname,$fname,$lname,$email,$pass,$facebook);
	echo $sign_up_results;
}

class ajaz_new_sign_up{
/* This is the start of the exeuction of 3 signup functions */
	
	/* Signup Function 1 (strips/check input, updates user info in db, calls create_im_hash() */
	function process_sign_up($uname,$fname,$lname,$email,$pass,$facebook){
		$uid = $_SESSION['uid'];

        $user = new User($uid);
        $user->userificateGuest($uid, $uname, $fname, $lname, $email, $pass);
        if ($facebook) {
            $fb = new Facebook();
            $fb->bindToFacebook($user);
        }

        $this->send_welcome_mail();

		return json_encode(array('success' => 1));
	}
	
    function send_welcome_mail(){
		$subject = "Welcome to tap!";
		$from = "From: tap.info\r\n";
		$body = <<<EOF
     Welcome to tap.info , with tap you'll be able to stay connected with people and information
you're interested in.  tap also allows you to 'tap' into specific channels of people by sending a message
to that channel.  For example, if you want to send a message to everyone at Python, simply find the Python
channel via the autocompleter and people at Python will see that show up in their outside messages
tab.  There's many applications and uses for tap, espcially when it comes to community management, so
feel free to go wild using it!  Happy tapping!

-Team Tap
http://tap.info
EOF;
		$mail_val = mail($this->email,$subject,$body,$from);
	}
};
