<?php
session_start();
/* CALLS:
	leave_group.js
*/
$usage = <<<EOF
Usage:
gid: id of the group you want to leave
EOF;

require('../config.php');
require('../api.php');


$gid = $_POST['gid'];

if(isset($_POST['gid'])){
    $leave_function = new leave_functions();
    $res = $leave_function->leave_group($gid);   
    api_json_choose($res,$cb_enable);
}else{
    api_usage($usage);
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
                $del_group_results = $this->mysqli->query($del_group_query);

		//hack
		$last_id = 1;	
		if($last_id > 0)
			return array('success' => 1);
		return array('success' => 0);
	}

}
