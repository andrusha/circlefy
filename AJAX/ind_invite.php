<?php
/* CALLS:
	join_group.js
*/
session_start();
require('../config.php');

$uid = $_SESSION['uid'];
$email = $_POST['email'];
$type = $_POST['type'];
$gname = $_POST['gname'];
if(!$gname)
$gname = '';

if(isset($email) && isset($type)){
   	$invite_function = new invite_functions();
        $results = $invite_function->invite_ind($uid,$type,$email,$gname);
        echo $results;
}


class invite_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

		private $email;

		private $from = "invite@tap.info";
		private $to;
		private $subject;
		private $body;

        function __construct(){
		$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function invite_ind($uid,$type,$email,$gname){
                $uid = $this->mysqli->real_escape_string($uid);
                $type = $this->mysqli->real_escape_string($type);
                $this->email = $this->mysqli->real_escape_string($email);

		$res = $this->email_maker($uid);

		if($type == 1)
			$this->search_invite($res['uname'],$res['fname'],$res['lname']);
		if($type == 2)
			$this->group_invite($res['uname'],$res['fname'],$res['lname'],$gname);
		
		$mail_val = $this->mail_it();	
		if($mail_val == 1){
			return json_encode(array('good' => 1));
		} else {
			return json_encode(array('good' => 0));
		}
	}

	function email_maker($uid){
		$create_invite_query = "SELECT uname,fname,lname FROM login WHERE uid={$uid}";
                $create_invite_results = $this->mysqli->query($create_invite_query);
		$res = $create_invite_results->fetch_assoc();
		return $res;
	}

	function mail_it(){
		 $mail_val = mail($this->to,$this->subject,$this->body,$this->from);
		return $mail_val;
	}

	function search_invite($uname,$fname,$lname){
		$this->to =  $this->email;
		$this->subject = $fname.' '.$lname.' ( '.$uname.' ) Could not find you on Tap!  Check out tap to get experience something new.';
		$this->body = <<<EOF
		\t {$fname} invited you to tap.info so you can help influence what people are getting information about in a subject releavant to something you care about..  Check out tap.info allows for something very useful and unique.  F*ck Web 2.0
EOF;
	}

	function group_invite($uname,$fname,$lname,$gname){
		$this->to =  $this->email;
		$this->subject = $fname.' '.$lname.' ( '.$uname.' ) invited you to join the real-time group '.$gname.'  Get on tap to get experience true real-time connectivity, it\'s amazing. new.';
		$this->body = <<<EOF
		\t {$fname} invited you to tap.info to join the group {$gname} so you can help influence what people are getting information about in a subject releavant to something you care about..  Check out tap.info allows for something very useful and unique.  F*ck Web 2.0
EOF;
	}

}
