<?php
/* CALLS:
	homepage.phtml
*/
$usage = <<<EOF
	PARAMS
	
	cid: the channel id of the current "tap" that you're trying to get responses from
EOF;
session_start();
require('../config.php');
require('../api.php');

if($cb_enable)
	$cid = $_GET['cid'];
else 
	$cid = $_POST['cid'];

if($cid){
$chat_obj = new chat_functions();
	$res = $chat_obj->load_response($cid);
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

		
	function load_response($cid){
                $resp_message_query = <<<EOF
		SELECT l.pic_36,c.uname,c.chat_text,UNIX_TIMESTAMP(c.chat_time) AS chat_time FROM chat AS c
		JOIN login AS l
		ON l.uid = c.uid
		WHERE c.cid = {$cid};
EOF;
                $responses_data = $this->mysqli->query($resp_message_query);
		if($responses_data->num_rows){
		while($res = $responses_data->fetch_assoc()){
			$uname = $res['uname'];
			$chat_text = $res['chat_text'];
			$chat_timestamp = $res['chat_time'];
			$pic_36 = $res['pic_36'];

			$responses[] = array(
			'uname' =>		$uname,
			'pic_small' =>		$pic_36,
			'chat_text'=>		$chat_text,
			'chat_time'=>		$chat_timestamp
			);
		}
			return array('success' => 1,'responses' => $responses);
		} else {
			return array('success' => 0,'responses' => null);
		}
	}
}
//END class
