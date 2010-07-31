<?php
/* CALLS:
	join_group.js
*/
$usage = <<<EOF

name: persons name

email: the email of the person you're emailing

type: 0 for direct, pass it 1 for couldn't find person in searc, 2 is channel invites which have been removed

msg : the message of the email

EOF;
session_start();
require('../config.php');
require('../api.php');

if($cb_enable){
	$uid = $_SESSION['uid'];
	$email = $_GET['email'];
	$msg = $_GET['msg'];
	$name = $_GET['name'];
	$type = $_GET['type'];
	$gname = $_GET['gname'];
} else {
	$uid = $_SESSION['uid'];
	$msg = $_POST['msg'];
	$name = $_POST['name'];
	$email = $_POST['email'];
	$type = $_POST['type'];
	$gname = $_POST['gname'];
}
if(!$gname)
	$gname = '';

if(!$msg)
	$msg = false;
if(!$name)
	$name = false;

if(isset($email) && isset($type)){
   	$invite_function = new invite_functions();
        $res = $invite_function->invite_ind($uid,$type,$email,$gname,$msg,$name);
	api_json_choose($res,$cb_enable);
} else { 
	api_usage($res);
}


class invite_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

		private $email;

		private $from = "From: tap.info\r\n";
		private $to;
		private $subject;
		private $body;

        function __construct(){
		$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function invite_ind($uid,$type,$email,$gname,$msg,$name){
                $uid = $this->mysqli->real_escape_string($uid);
                $type = $this->mysqli->real_escape_string($type);
                $msg = $this->mysqli->real_escape_string($msg);
                $name = $this->mysqli->real_escape_string($name);
                $this->email = $this->mysqli->real_escape_string($email);

		$res = $this->email_maker($uid);

		if($type == 0)
			$this->direct_invite($res['uname'],$res['fname'],$res['lname'],$msg,$name);
		if($type == 1)
			$this->search_invite($res['uname'],$res['fname'],$res['lname'],$msg);
		if($type == 2)
			$this->group_invite($res['uname'],$res['fname'],$res['lname'],$gname);
		
		$mail_val = $this->mail_it();	
		if($mail_val == 1){
			return array('success' => True);
		} else {
			return array('success' => False);
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

	function direct_invite($uname,$fname,$lname,$msg,$name){
		$this->to =  $this->email;
		$this->subject = <<<EOF
{$name} , $fname $lname ( $uname ) has invited you to tap!
EOF;
		if($msg)
		$this->body = <<<EOF
	{$fname} invited you to tap.info so you can discuss and chat about in a subject releavant to something you care about..  Check out tap.info allows for something very useful and unique.

-Team Tap
EOF;
		else
		$this->body = <<<EOF
		{$msg}

http://tap.info
EOF;
	}

	function search_invite($uname,$fname,$lname,$msg){
		$this->to =  $this->email;
		$this->subject = $fname.' '.$lname.' ( '.$uname.' ) Could not find you on Tap!  Check out tap to get experience something new.';
		if($msg)
		$this->body = <<<EOF
	{$fname} invited you to tap.info so you can discuss and chat about in a subject releavant to something you care about..  Check out tap.info allows for something very useful and unique.

-Team Tap
EOF;
		else
		$this->body = <<<EOF
		{$msg}

http://tap.info
EOF;
	}

	function group_invite($uname,$fname,$lname,$gname){
		$this->to =  $this->email;
		$this->subject = <<<EOF
{$name} , $fname $lname ( $uname ) has invited you to tap!
EOF;
		$this->body = <<<EOF
	{$fname} invited you to tap.info to join the channel {$gname} so you can help influence what people are getting information about in a subject releavant to something you care about..  Check out tap.info allows for something very useful and unique.
-
-Team Tap
EOF;
	}

}
