<?php
/* CALLS:
	homepage_tap_friend.js
	tap_friend.js
	invite.js
*/
require('../config.php');
require('../api.php');
require('../modules/User.php');
require('../modules/Friends.php');

session_start();

$fid = $_POST['fid'];
$state = $_POST['state'];

if($fid && isset($state)){
	$instance = new friend_functions();
	echo $instance->tap_friend($fid,$state);
}

class friend_functions{

    private $mysqli;
    private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
    private $results;

    function __construct(){
        $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

    function tap_friend($fid,$state){

        $uid = $_SESSION['uid'];
        $uname = $_SESSION['uname'];

        $fid = $this->mysqli->real_escape_string($fid);
        $state = $this->mysqli->real_escape_string($state);

        $user = new User();
        $info = $user->getInfo($uid);
        $friend_info = $user->getInfo($fid);

        $friends = new Friends();

		if($state == 1 && !$friend_info['private']){
            $friends->follow($uid, $fid);

			$to = $friend_info['email'];
            $subject = "{$uname} now has you on tap.";
            $from = "From: tap.info\r\n";
            $body = <<<EOF
{$uname} now has you on tap and will receive anyting you say!  Say something awesome!

-Team Tap
http://tap.info
EOF;

			//Checks the person getting tracked settings before sending them an email
			$email_check_query = "SELECT uid FROM settings WHERE track = 1 AND uid = {$fid}";

            if($this->mysqli->query($email_check_query)->num_rows)	
                mail($to,$subject,$body,$from);
		} else {
            $friends->unfollow($uid, $fid);
		}

        $results = json_encode(array('success' => 1));

        $this->notifyFriend($info, $uid, $fid, $will_be_friends);

        return $results;
	}

    private function notifyFriend($info, $uid, $fuid, $status) {
        $data = array('status' => $status, 'uname' => $info['uname'], 'ureal_name' => $info['real_name']);
        $message = array('action' => 'notify.follower', 'users' => array(intval($fuid)), 'data' => $data);

        Comet::send('message', $message);
    }

}	
