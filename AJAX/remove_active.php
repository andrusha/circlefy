<?php
session_start();
require('../config.php');

$mid = $_POST['remove_active'];
$uid = $_SESSION['uid'];

if($mid){
	$remove_obj = new remove_functions();
	$remove_obj->remove_active($mid,$uid);
}

class remove_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function remove_active($mid,$uid){
		$remove_active_query = "UPDATE active_convo SET active = 0 WHERE uid = $uid AND mid = $mid";
		$this->mysqli->query($remove_active_query);
	}	
}
