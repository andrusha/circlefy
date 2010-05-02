<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');
require('../api.php');


$response = stripslashes($_POST['response']);
$cid = $_POST['cid'];
$first = $_POST['first'];
$init_tapper = $_POST['init_tapper'];

	$chat_obj = new chat_functions();
		$results = $chat_obj->send_response($response,$cid,$init_tapper,$first);
		echo $results;

class chat_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
		$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function check_if_dupe($msg){
                $uid = $_SESSION['uid'];

                $check_channel_query = <<<EOF
                SELECT chat_text FROM chat where uid = {$uid} ORDER BY mid desc LIMIT 1;
EOF;
                $check_channel_results = $this->mysqli->query($check_channel_query);
                while($res = $check_channel_results->fetch_assoc()){
                        $chat_text = $res['chat_text'];
                        $chat_text = stripslashes($chat_text);
                        $msg = stripslashes(stripslashes($msg));
                        if($msg == $chat_text)
                                $gtfo = true; else $gfto = false;
                }
                return $gtfo;
        }


		
	function send_response($msg,$cid,$init_tapper,$first){
		$res = $this->check_if_dupe($msg);
                if($res)
                        return json_encode(array('dupe' => true));
	
	
		$uid = $_SESSION["uid"];
		$uname = $_SESSION["uname"];
		if($first){
			//This query can be moved to client-side once real-time presence of users is taken care of
			$user_online_query = <<<EOF
			SELECT uid FROM TEMP_ONLINE WHERE uid = {$init_tapper} AND online = 1
EOF;
			$user_online_reults = $this->mysqli->query($user_online_query);
			if(!$user_online_reults->num_rows){
				$user_settings_query = <<<EOF
				SELECT s.email_on_response,l.email FROM settings AS s 
				JOIN login AS l ON s.uid = l.uid 
				WHERE s.uid = {$init_tapper} AND s.email_on_response = 1
EOF;
				$user_settings_results = $this->mysqli->query($user_settings_query);
				if($user_settings_results->num_rows){
					$res = $user_settings_results->fetch_assoc();
					$email = $res['email'];
					$this->notify_user($init_tapper,$msg,$uname,$cid,$email);	
				}
			}
		}
		
			$small_pic = $_POST['small_pic'];
			$small_pic = 'small_'.$small_pic;

			echo $small_pic.'HEHEHE';

	
			$action = "response";
			$response = $msg;
			$response = str_replace('"','\"',$response);

			$fp = fsockopen("localhost", 3333, $errno, $errstr, 30);
			$insert_string = '{"cid":"'.$cid.'","action":"'.$action.'","response":"'.$response.'","uname":"'.$uname.'","init_tapper":"'.$init_tapper.'","pic_small":"'.$small_pic.'"}'."\r\n";
			fwrite($fp,$insert_string);
			fclose($fp);

		$msg = addslashes($msg);
		$this->mysqli->real_escape_string($msg);
		$this->mysqli->real_escape_string($cid);
		
                $resp_message_query = "INSERT INTO chat(cid,uid,uname,chat_text) values('{$cid}','{$uid}','{$uname}','{$msg}')";
                $this->mysqli->query($resp_message_query);

			$update_active = "UPDATE active_convo SET active = 1 WHERE mid ={$cid} AND uid={$init_tapper}";
			$this->mysqli->query($update_active);

		return json_encode(array('success' => 1));
	}

	private function notify_user($init_tapper,$msg,$uname,$cid,$email){
		$update_noftify_query = "UPDATE notifications SET email_on_response = NOW() WHERE email_on_response < SUBTIME(NOW(),'1:00:00') AND uid = {$init_tapper}";
		$this->mysqli->query($update_noftify_query);
		if($this->mysqli->affected_rows){
				$to = $email;
				$subject = "{$uname} has replied to your tap.";
					$from = "From: tap.info\r\n";
					$body = <<<EOF
{$uname} has responded to your tap with the following:

{$uname}: {$msg}

You can respond back in real-time at http://tap.info/tap/{$cid}

-Team Tap
http://tap.info
EOF;
					mail($to,$subject,$body,$from);
			return True;
		} else { 
			return False;
		}
	}
}
//END class
