<?php
session_start();
/* CALLS:
	leave_group.js
*/
require('../config.php');

$gid = $_POST['gid'];

if(isset($_POST['gid'])){
   	$leave_function = new leave_functions();
        $results = $leave_function->leave_group($gid);
        echo $results;
}


class leave_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function leave_group($gid){

                $uid = $_SESSION["uid"];
                $uname = $_SESSION["uname"];

                $uid = $this->mysqli->real_escape_string($uid);
                $gid = $this->mysqli->real_escape_string($gid);

		$del_group_query = "DELETE FROM group_members WHERE uid = {$uid} AND gid = {$gid};";
		echo $del_group_query;
                $del_group_results = $this->mysqli->query($del_group_query);

		//hack
		$last_id = 1;	
		if($last_id > 0)
			return json_encode(array('success' => 1));
		return json_encode(array('success' => 0));
	}

}
