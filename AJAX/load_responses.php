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
                $resp_message_query = "SELECT * FROM chat WHERE cid = {$cid}";
                $responses_data = $this->mysqli->query($resp_message_query);
		while($res = $responses_data->fetch_assoc()){
			$uname = $res['uname'];:
			$chat_text = $res['chat_text'];
			$chat_timestamp = $res['chat_timestamp'];

			$responses[] = array(
			'uname' =>		$uname,
			'chat_text'=>		$chat_text,
			'chat_timestamp'=>	$chat_timestamp
			);
		}
		return json_encode(array('success' => 1,'responses' => $responses));
	}
}
//END class
