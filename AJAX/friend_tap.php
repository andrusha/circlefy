<?php
/* CALLS:
	homepage_tap_friend.js
	tap_friend.js
	invite.js
*/
require('../config.php');
session_start();

$fid = $_POST['fid'];
$friend = $_POST['friend'];
$state = $_POST['state'];

if($friend){
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

                $uid = $this->mysqli->real_escape_string($uid);
                $fid = $this->mysqli->real_escape_string($fid);

		$friend_email_query = "SELECT l.email FROM login AS l WHERE l.uid = {$fid} LIMIT 1";
		$friend_email_result = $this->mysqli->query($friend_email_query);
		$res = $friend_email_result->fetch_assoc();

		if($state == 1){
	                $friend_query = "INSERT INTO friends(fuid,uid) values('{$fid}','{$uid}');";
			$to = $res['email'];
                        $subject = "{$uname} now has you on tap.";
                                $from = "From: tap.info\r\n";
                                $body = <<<EOF
{$uname} now has you on tap and will receive anyting you say!  Say something awesome!

-Team Tap
http://tap.info
EOF;
                                mail($to,$subject,$body,$from);
		} else {
			$friend_query = "DELETE FROM friends WHERE fuid = '{$fid}' AND uid = '{$uid}';";
		}

                $friend_results = $this->mysqli->query($friend_query);
		
		if($friend_results) {
			$results = json_encode(array('good' => 1));
			return $results;
		}
	}

}	
