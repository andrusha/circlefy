<?php

/* CALLS:
	homepage.phtml
*/

session_start();
require('../config.php');
$gid = $_POST['gid'];
$uid = $_SESSION['uid'];
$fuid = $_POST['fuid'];
$status = $_POST['status'];

if($gid && $fuid && $status){
	$admin_obj = new admin_functions();
	$res = $admin_obj->admin_user($gid,$uid,$fuid,$status);
	echo $res;
}

class admin_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function check_if_admin($gid,$uid){
		$check_if_admin_query = <<<EOF
		SELECT uid FROM group_members WHERE gid = {$gid} AND uid = {$uid} AND admin > 0
EOF;
		$check_if_admin_results = $this->mysqli->query($check_if_admin_query); 
		if($check_if_admin_results->num_rows)
			return 1
		else
			return 0
	}

	function admin_user($gid,$uid,$fuid,$status){
		$admin = $this->check_if_admin($gid,$uid)
		if(!$admin)
			return False

		$admin_query = "UPDATE group_members SET admin = {$status} WHERE uid = $fuid AND gid = $gid";
		$admin_result = $this->mysqli->query($admin_query);
		
		if($admin_result->num_rows)
			return True
		else
			return False
	}
}
