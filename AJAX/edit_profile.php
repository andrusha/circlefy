<?php

require('../config.php');

$type = $_POST['type'];

if($type == 'you'){
	$profile_function = new profile_functions();
	$results = $profile_function->update_you();
	echo json_encode($results);
}


class profile_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function update_you(){

                $uid = $_COOKIE["uid"];
                $uname = $_COOKIE["uname"];
		$fname = $_POST['fname'];
		$lname = $_POST['lname'];
		$zip = $_POST['zip'];
		$state = $_POST['state'];
		$gender =  $_POST['gender'];
		$email = $_POST['email'];

                $uid = $this->mysqli->real_escape_string($uid);
                $name = $this->mysqli->real_escape_string($name);
                $tags = $this->mysqli->real_escape_string($tags);
                $zipcode = $this->mysqli->real_escape_string($zipcode);

		$update_you_query = <<<EOF
			UPDATE display_rel_profile AS t1
                        JOIN login AS t2
                        ON t1.uid = t2.uid
                        JOIN profile AS t3
                        ON t1.uid = t3.uid
                        SET
				t3.fname="$fname",
				t3.lname="$lname",
				t1.zip="$zip",
				t1.state="$state",
				t1.gender="$gender",
				t2.email="$email"
                        WHERE t1.uid ={$uid}
EOF;


                $you_results = $this->mysqli->query($update_you_query);
		$json_res = array('good' => 1);
		return $json_res;
	}

	function del_channel($rid){
		$uid = $_COOKIE["uid"];
                $uname = $_COOKIE["uname"];

		$del_rel_query = "DELETE FROM rel_settings where rid = {$rid}";

		$del_rel_results = $this->mysqli->query($del_rel_query);
		
		//I had to make this quickly it menas it DELETED okay ...somewhat...	
		$good = json_encode(array('good' => 1));
		return $good;
	}
	
	function toggle_enable($rid,$state){
		$toggle_rel_query = "UPDATE rel_settings SET enabled = {$state} WHERE rid = {$rid}";
		$toggle_rel_results = $this->mysqli->query($toggle_rel_query);

		$good = json_encode(array('updated' => 1));
		return $good;
	}
}
