<?php
/* CALLS:
	edit_profile.js
*/
session_start();
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

                $uid = $_SESSION["uid"];
                $uname = $_COOKIE["uname"];
		$fname = $_POST['fname'];
		$lname = $_POST['lname'];
		$zip = $_POST['zip'];
		$state = $_POST['state'];
		$gender =  $_POST['gender'];
		$email = $_POST['email'];
		$lang = $_POST['lang'];
		$country = $_POST['country'];

                $uid = $this->mysqli->real_escape_string($uid);
                $zip = $this->mysqli->real_escape_string($zip);
                $fname = $this->mysqli->real_escape_string($fname);
                $lname = $this->mysqli->real_escape_string($lname);
                $email = $this->mysqli->real_escape_string($email);
                $gender = $this->mysqli->real_escape_string($gender);
                $state = $this->mysqli->real_escape_string($state);

		$update_you_query = <<<EOF
			UPDATE display_rel_profile AS t1
                        JOIN login AS t2
                        ON t1.uid = t2.uid
                        SET
				t2.fname="$fname",
				t2.lname="$lname",
				t1.zip="$zip",
				t1.state="$state",
				t1.gender="$gender",
				t1.language="$lang",
				t1.country="$country",
				t2.email="$email"
                        WHERE t1.uid ={$uid}
EOF;

                $you_results = $this->mysqli->query($update_you_query);
		$json_res = array('good' => 1);
		return $json_res;
	}
}
