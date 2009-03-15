<?php

require('../config.php');

$response = $_POST['response'];
$channel_id = $_POST['channel_id'];

	$chat_obj = new chat_functions();
		$results = $chat_obj->send_response($response,$channel_id);
		echo $results;

class chat_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

		
	function send_response($msg,$channel_id){
		$uid = $_COOKIE["uid"];
		$uname = $_COOKIE["uname"];
		
                $resp_message_query = "INSERT INTO chat(cid,uid,uname,chat_text) values('{$channel_id}','{$uid}','{$uname}','{$msg}')";
		echo $resp_message_query;
		//echo " INSERT INTO chat(cid,uid,uname,chat_text) values('{$channel_id}','{$uid}','{$uname}','{$msg}') ";
                $this->mysqli->query($resp_message_query);
	}
}
//END class
