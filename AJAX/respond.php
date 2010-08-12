<?php
/* CALLS:
	homepage.phtml
*/
$usage = <<<EOF
cid: channel id

first: is this the first time a user has responded?

init_tapper: the person who intially tapped

response: the text of the response
EOF;

session_start();
require('../config.php');
require('../api.php');

if($cb_enable){
	$response = stripslashes($_GET['response']);
	$cid = $_GET['cid'];
	$first = $_GET['first'];
	$init_tapper = $_GET['init_tapper'];
} else {
	$response = stripslashes($_POST['response']);
	$cid = $_POST['cid'];
	$first = $_POST['first'];
	$init_tapper = $_POST['init_tapper'];
}

if($cid){
	$chat_obj = new chat_functions();
	$res = $chat_obj->send_response($response,$cid,$init_tapper,$first);
	api_json_choose($res,$cb_enable);
} else {
        api_usage($usage);
}


class chat_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
		$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function check_if_dupe($msg,$cid){
                $uid = $_SESSION['uid'];

                $check_channel_query = <<<EOF
                SELECT chat_text FROM chat WHERE cid = {$cid} AND uid = {$uid} ORDER BY mid desc LIMIT 1;
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

	 function add_active($mid,$uid){
                $add_active_query = "UPDATE active_convo SET active = 0 WHERE uid = $uid AND mid = $mid";
                $results = $this->mysqli->query($add_active_query);

                if($results->affected_rows != 1){
                        $init_active_convo = "INSERT INTO active_convo(mid,uid,active) values({$mid},{$uid},1)";
                        $this->mysqli->query($init_active_convo);
                }
        }

	function send_response($msg,$cid,$init_tapper,$first){
		$res = $this->check_if_dupe($msg,$cid);
        if($res)
            return array('dupe' => true);
	
		$uid = $_SESSION["uid"];
		$uname = $_SESSION["uname"];

		$this->add_active($mid,$uid);
	
		if($first || true){
			//This query can be moved to client-side once real-time presence of users is taken care of
			$user_online_query = <<<EOF
			SELECT uid FROM TEMP_ONLINE WHERE uid = {$init_tapper} AND online = 1
EOF;
			$user_online_reults = $this->mysqli->query($user_online_query);
			if(!$user_online_reults->num_rows || true){
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

        $this->notify_all($cid, $init_tapper);
		
        $small_pic = $_POST['small_pic'];
        $small_pic = 'small_'.$small_pic;

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

        $update_active = "UPDATE active_convo SET active = 1 WHERE mid = {$cid} AND uid={$init_tapper}";
        $this->mysqli->query($update_active);

		return array('success' => 1);
	}
	
	private function notify_all($cid, $init_tapper, $send_emails = false) {
		/*
		NOTE: NEED TO ADD IN THE JOIN FOR settings, to see if they actually are tracking it
		*/
		$notify_all_query = <<<EOF
		SELECT 
		ac.uid,	
		l.uname, l.fname,l.lname,l.email
		FROM active_convo AS ac
		JOIN login AS l
		ON ac.uid = l.uid
		WHERE ac.mid = {$cid} AND ac.active = 1
EOF;
        // AND ac.uid != {$init_tapper}
		$nofiy_all_res = $this->mysqli->query($notify_all_query);
var_dump($notify_all_query);
        $users = $results = array();
        while($res = $nofiy_all_res->fetch_assoc()) {
            $results[] = $res;
            $users[] = array('uid' => intval($res['uid']),
                             'uname' => $res['uname'],
                             'ureal_name' => $res['fname'] . ' ' . $res['lname']);
        }


        $fp = fsockopen("localhost", 3333, $errno, $errstr, 30);
        $insert_string = json_encode(array('cid' => intval($cid), 'action' => 'notify-convo-response', 'users' => $users));
        fwrite($fp, $insert_string."\r\n");
        fclose($fp);

		//For each person who is apart of the message or has the message active, send them an email
        if ($send_emails) {
            foreach($results as $res){
                $uname = $res['uname'];
                $fname = $res['fname'];
                $lname = $res['lname'];
                $email = $res['email'];
            
                $to = $email;
                $subject = "{$uname} has replied to your tap.";
                $from = "From: tap.info\r\n";
                $body = <<<EOF
{$uname} has responded to a tap you're tracking:

{$uname}: {$msg}

You can respond back in real-time at http://tap.info/tap/{$mid}

-Team Tap
http://tap.info
EOF;
                mail($to,$subject,$body,$from);
            }
        }
	}

	private function notify_user($init_tapper,$msg,$uname,$cid,$email){
		$update_noftify_query = "UPDATE notifications SET email_on_response = NOW() WHERE email_on_response < SUBTIME(NOW(),'0:01:00') AND uid = {$init_tapper}";
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
