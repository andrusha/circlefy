<?php
/* CALLS:
	sign_up.js
	groups_manage.phtml
*/
require('../config.php');
session_start();

$email = $_POST['email'];
$parse = explode('@',$email);
$school = $parse[1];

if(isset($school)){
   	$join_function = new join_functions();
        $results = $join_function->join_group($school,$email);
        echo $results;
}


class join_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

		private $from = "auth@tap.info";
                private $to;
                private $subject;
                private $body;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function join_group($school,$email){
		$type = explode('.',$school);
		if(end($type) == 'edu')
			$type = 1;
		else
			$type = 2;

                $uid = $_SESSION['uid'];
                $uname = $_SESSION['uname'];

                $uid = $this->mysqli->real_escape_string($uid);
                $email = $this->mysqli->real_escape_string($email);
                $school = $this->mysqli->real_escape_string($school);
                $gid = $this->mysqli->real_escape_string($gid);
	
		$check_school = "SELECT gid,connected,gname FROM groups WHERE symbol LIKE '$school%' AND connected={$type};";
		$school_results = $this->mysqli->query($check_school);
		
		$res = $school_results->fetch_assoc();
		$gid = $res['gid'];
		$gname = $res['gname'];
		$connected = $res['connected'];
		$hash = md5(rand(1,94949).$gid.$gname.$uname.'C98c8i3*x0_hahQ');

		if($school_results->num_rows > 0){
			$create_group_member = "INSERT INTO group_members(uid,gid,connected,status) values('{$uid}',{$gid},{$connected},0);";
			$create_group_member = $this->mysqli->query($create_group_member);

			$create_join_group = "INSERT INTO join_group_status(uid,gid,email,status,hash) values('{$uid}',{$gid},'{$email}',0,'{$hash}');";
			$create_join_results = $this->mysqli->query($create_join_group);

			$last_id = $this->mysqli->query($this->last_id);
			$last_id = $last_id->fetch_assoc();
			$last_id = $last_id['last_id'];
			if($last_id > 0){
				$this->body = <<<EOF
				You've tried to join the connected group for {$school}.  Please click click the link below to active your connection:
					\n\n
				http://localhost.com/confirm/{$hash}
EOF;
				$this->subject = "Request to Confirm Your Connected Group for {$school}";
				$this->to = "tasoduv@gmail.com";
	
				mail($this->to,$this->subject,$this->body,$this->from);
				return json_encode(array('stat' => "<li class='pending_connect'><img src=\"images/icons/error.png\" /> An email has been sent to you @ <span class='style_bold'>$email</span> to join <span class='style_bold'>$gname</span>, please click the link in the email to join, thanks!</li>"));
				}
				return json_encode(array('stat' => '<span id="school_error"><img src=\"images/icons/error.png\" /> Error in processing school join request</span>'));
		}
				return json_encode(array('stat'=> "<span id=\"school_error\"><img src=\"images/icons/error.png\" /> No such school with domain $school</span>"));

	}
}
