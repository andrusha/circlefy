<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');

$cid = $_POST['cid'];

	$chat_obj = new chat_functions();
		$results = $chat_obj->load_response($response,$cid);
		echo $results;

class chat_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
		$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

		
	function load_response($msg,$cid){
                $resp_message_query = "SELECT c.uname,c.chat_text,UNIX_TIMESTAMP(c.chat_time) AS chat_time FROM chat AS c WHERE c.cid = {$cid}";
                $responses_data = $this->mysqli->query($resp_message_query);
		if($responses_data->num_rows){
		while($res = $responses_data->fetch_assoc()){
			$uname = $res['uname'];
			$chat_text = $res['chat_text'];
			$chat_timestamp = $res['chat_time'];

			$responses[] = array(
			'uname' =>		$uname,
			'chat_text'=>		$chat_text,
			'chat_time'=>		$chat_timestamp
			);
		}
			return json_encode(array('success' => 1,'responses' => $responses));
		} else {
			return json_encode(array('success' => 0,'responses' => null));
		}
	}
}
//END class
