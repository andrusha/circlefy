<?php
/* CALLS:
	homepage_tap_friend.js
	tap_friend.js
	invite.js
*/
require('../config.php');
require('../api.php');

session_start();

$fid = $_POST['fid'];
$state = $_POST['state'];

if($fid && isset($state)){
	$instance = new friend_functions();
	echo $instance->tap_friend($fid,$state);
}

class friend_functions{
    function tap_friend($fid, $state){
        $you = new User(intval($_SESSION['uid']));
        $friend = new User(intval($fid));

        $info = $you->info;
        $finfo = $friend->info;

		if($state == 1 && !$friend_info['private']){
            $you->follow($friend);
		} else {
            $you->unfollow($friend);
		}

        $results = json_encode(array('success' => 1));
        $this->notifyFriend($info, $you->uid, $friend->uid, $state);

        return $results;
	}

    private function notifyFriend($info, $uid, $fuid, $status) {
        $data = array('status' => $status, 'uname' => $info['uname'], 'ureal_name' => $info['real_name']);
        $message = array('action' => 'notify.follower', 'users' => array(intval($fuid)), 'data' => $data);

        Comet::send('message', $message);
    }

/*
TODO:

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
*/

};
