<?php
session_start();
/* CALLS:
	join_group.js
*/
require('../config.php');

$gid = $_POST['gid'];

if(isset($_POST['gid'])){
   	$join_function = new join_functions();
        $results = $join_function->join_group($gid);
        echo $results;
}


class join_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function join_group($gid){

                $uid = $_SESSION["uid"];
                $uname = $_SESSION["uname"];

                $uid = $this->mysqli->real_escape_string($uid);
                $gid = $this->mysqli->real_escape_string($gid);

		$create_rel_query = "INSERT INTO group_members(uid,gid) values('{$uid}',{$gid});";
                $create_rel_results = $this->mysqli->query($create_rel_query);
		$last_id = $this->mysqli->query($this->last_id);

                $last_id = $last_id->fetch_assoc();
                $last_id = $last_id['last_id'];
		if($last_id > 0)
			return json_encode(array('good' => 1));
		return json_encode(array('good' => 0));
	}

}
