<?php
require('../config.php');
session_start();

$fid = $_POST['fid'];
$friend = $_POST['friend'];
$state = $_POST['state'];

if($friend){
	$instance = new friend_functions();
	$res = $instance->tap_friend($fid,$state);
	echo $res;
}

class friend_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function tap_friend($fid,$state){

  	        $uid = $_SESSION['uid'];
                $uname = $_SESSION['uname'];

                $uid = $this->mysqli->real_escape_string($uid);
                $fid = $this->mysqli->real_escape_string($fid);

		if($state == 1){
	                $friend_query = "INSERT INTO friends(fuid,uid) values('{$fid}','{$uid}');";
		} else {
			$friend_query = "DELETE FROM friends WHERE fuid = '{$fid}' AND uid = '{$uid}';";
		}

                $friend_results = $this->mysqli->query($friend_query);
		
		$results = json_encode(array('good' => 1));
		return $results;
	}

}	
