<?php
/* CALLS:
	homepage_group_status.js
	group_status.js
*/
require('../config.php');

//Used for delete and update
$uid = $_COOKIE['uid'];
$gid = $_POST['gid'];
$type = $_POST['type'];


//used to keep track of state of enabled
$state = $_POST['state'];

if(isset($_POST['update'])){
   	$rel_function = new rel_functions();
        $results = $rel_function->toggle_enable($gid,$uid,$state,$type);
        echo $results;
}

class rel_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function toggle_enable($gid,$uid,$state,$type){
		$toggle_rel_query = "UPDATE group_members SET {$type} = {$state} WHERE gid = {$gid} and uid = {$uid}";
		$toggle_rel_results = $this->mysqli->query($toggle_rel_query);

		$good = json_encode(array('updated' => 1));
		return $good;
	}
}
