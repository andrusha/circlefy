<?php

/* CALLS:
	homepage.phtml
*/

session_start();
require('../config.php');
$uid = $_SESSION['uid'];
$fuid = $_POST['fuid'];
$status = $_POST['status'];

if($fuid && $status){
	$block_obj = new block_functions();
	$res = $block_obj->block_user($uid,$fuid,$status);
	echo $res;
}

class block_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function block_user($uid,$buid,$status){
		if($status)
			$block_query = "INSERT INTO block_user(buid,uid) values($buid,$uid)";
		else
			$block_query = "DELETE FROM block_user WHERE buid = $buid AND uid = $uid";

		$block_result = $this->mysqli->query($block_query);
		
		if($status)
			$res = $this->mysqli->query($this->last_id);
		else
			$res = $this->mysqli->affected_row;
	
			
		if($res){
			$status = $status ? 0 : 1;
			$clean_up_query = "UPDATE friends SET block = $status WHERE fuid = $buid AND uid = $uid";
			$clean_up_result = $this->mysqli->query($clean_up_query);
			return True
		} else {
			return False
		}
	}
}
