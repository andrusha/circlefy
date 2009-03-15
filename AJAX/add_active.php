<?php
session_start();
require('../config.php');
$uid = $_SESSION['uid'];
$mid = $_POST['add_active'];
if($mid){
	$add_obj = new add_functions();
	$add_obj->add_active($mid,$uid);
}

class add_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function add_active($mid,$uid){
		$add_active_query = "UPDATE active_convo SET active = 0 WHERE uid = $uid AND mid = $mid";
		$results = $this->mysqli->query($add_active_query);
	
		if($results->affected_rows != 1){
			$init_active_convo = "INSERT INTO active_convo(mid,uid,active) values({$mid},{$uid},1)";
	                $this->mysqli->query($init_active_convo);
		}
	}	
}
