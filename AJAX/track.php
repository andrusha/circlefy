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

		$friend_email_query = "SELECT l.email FROM login AS l WHERE l.uid = {$fid} AND l.private != 1 LIMIT 1";
		$friend_email_result = $this->mysqli->query($friend_email_query);
		$res = $friend_email_result->fetch_assoc();
        $will_be_friends = 0;

		if($state == 1 && $friend_email_result->num_rows){
            $will_be_friends = 1;

            $friend_query = "INSERT INTO friends(uid,fuid,time_stamp) values('{$uid}','{$fid}',NOW());";
			$to = $res['email'];
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
			$friend_query = "DELETE FROM friends WHERE fuid = '{$fid}' AND uid = '{$uid}';";
		}

        $friend_results = $this->mysqli->query($friend_query);
        $results = json_encode(array('success' => 1));

        $this->notifyFriend($uid, $fid, $will_be_friends);

        return $results;
	}

    private function notifyFriend($uid, $fuid, $status) {
        $query = "
            SELECT uname, CONCAT(fname, ' ', lname) AS real_name
              FROM login
             WHERE uid = {$uid}";
        $result = $this->mysqli->query($query)->fetch_assoc();
        $user = array('uname' => $result['uname'], 'ureal_name' => $result['real_name']);
        $message = json_encode(array('action' => 'notify-follower', 'uid' => intval($fuid),
                                     'status' => $status, 'follower' => $user));

        $fp = fsockopen("localhost", 3333, $errno, $errstr, 30);
        fwrite($fp, $message."\r\n");
        fclose($fp);
    }

}	
