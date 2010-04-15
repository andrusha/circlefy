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
	$block_obj = new block_functions();
	$res = $block_obj->block_group($gid,$uid,$fuid,$status);
	echo $res;
}

class block_functions{

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

	function block_group($gid,$uid,$buid,$status){
		$admin = $this->check_if_admin($gid,$uid)
		if(!$admin)
			return False

		if($status)
			$block_query = "INSERT INTO block_group(buid,gid) values($buid,$gid) ";
		else
			$block_query = "DELETE FROM block_group WHERE buid = $buid AND gid = $gid";

		$block_result = $this->mysqli->query($block_query);
		
		if($status)
			$res = $this->mysqli->query($this->last_id);
		else
			$res = $this->mysqli->affected_row;
		
		if($res){
			$status = $status ? 0 : 1;
                        $clean_up_query = "UPDATE group_members SET block = $status WHERE gid = $gid AND uid = $buid";
                        $clean_up_result = $this->mysqli->query($clean_up_query);
			return True
		} else {
			return False
		}
	}
}
