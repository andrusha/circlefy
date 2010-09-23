<?php
/* CALLS:
	edit_group.js
*/
$usage = <<<EOF
USAGE:
Sending password instructions to owner
    email: email of the user is requesting change

Updating the password once you have the instructions
    pass: password
    repass: password confirmation
    hash: verification hash
EOF;

session_start();
require('../../config.php');
require('../../api.php');


$email = $_REQUEST['email'];
$pass = $_REQUEST['pass'];
$repass = $_REQUEST['repass'];
$hash = $_REQUEST['hash'];


$pr_function = new password_recovery_functions();
if($email){
    $res = $pr_function->send_password($email);
    api_json_choose($res,$cb_enable);
}else{
	if($pass and $repass and $hash){
		$res = $pr_function->changePass($pass, $repass, $hash);
		api_json_choose($res,$cb_enable);
	}else{
		api_usage($usage);
	}

}

class password_recovery_functions{

	private $mysqli;
	private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
	private $results;

	function __construct(){
		$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
	}

	function send_password($email){
		if($this->email_exists($email)){
			$this->send_instructions($email);
			return array('status' => 1, 'error' => 'We have sent you an email with instructions');
		}else{
			return array('status' => 0, 'error' => 'We havent found that address in our database');
		}
      }

	function send_instructions($email){
		$hash = md5(microtime() . "TAP-RULZ");
		$hash_query = <<<EOF
			UPDATE login SET email_hash = '{$hash}' WHERE email = '{$email}'
EOF;
		$hash_results = $this->mysqli->query($hash_query);
		$domain = DOMAIN;
		$msgBody = <<<EOF
To reset your password please go to <a href="{$domain}/password_recovery?hash={$hash}">This link</a>
EOF;
		$headers  = "From: tap.info@tap.info\r\n";
		$headers .= "Content-type: text/html\r\n";
		mail($email, "TAP.INFO - Your password information", $msgBody, $headers);
	}


	function email_exists($email){
		$check_if_mail_exist_query = <<<EOF
			SELECT email FROM login WHERE email = '{$email}'
EOF;
		$check_if_mail_exist_results = $this->mysqli->query($check_if_mail_exist_query);
		return $check_if_mail_exist_results->num_rows;
	}

	function changePass($pass, $repass, $hash){
		if($pass == $repass){
			$update_query = <<<EOF
			UPDATE login SET pass = md5('{$pass}') WHERE hash = '{$hash}'
EOF;
			$this->mysqli->query($update_query);
			return array('status' => 1, 'error' => 'Password Updated');
		}else{
			return array('status' => 0, 'error' => 'Password not match');
		}
	}
}
?>
