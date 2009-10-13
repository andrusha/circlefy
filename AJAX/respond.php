<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');

$response = stripslashes($_POST['response']);
$cid = $_POST['cid'];

	$chat_obj = new chat_functions();
		$results = $chat_obj->send_response($response,$cid);
		echo $results;

class chat_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
		$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

		
	function send_response($msg,$cid){
		$uid = $_SESSION["uid"];
		$uname = $_SESSION["uname"];

			$action = "response";
			$response = $msg;
			$response = str_replace('"','\"',$response);

			$fp = fsockopen("localhost", 3333, $errno, $errstr, 30);
			$insert_string = '{"cid":"'.$cid.'","action":"'.$action.'","response":"'.$response.'","uname":"'.$uname.'"}'."\r\n";
			fwrite($fp,$insert_string);
			fclose($fp);

		$msg = addslashes($msg);
		$this->mysqli->real_escape_string($msg);
		$this->mysqli->real_escape_string($cid);
		
                $resp_message_query = "INSERT INTO chat(cid,uid,uname,chat_text) values('{$cid}','{$uid}','{$uname}','{$msg}')";
                $this->mysqli->query($resp_message_query);
		return json_encode(array('success' => 1));
	}
}
//END class
