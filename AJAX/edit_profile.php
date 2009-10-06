<?php
/* CALLS:
	edit_profile.js
*/
session_start();
require('../config.php');


if(1){
	$profile_function = new profile_functions();
	$results = $profile_function->update_you();
	echo $results;
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
		$gender =  $_POST['gender'];
		$email = $_POST['email'];
		$lang = $_POST['lang'];
		$country = $_POST['country'];
		$state = $_POST['state'];
		$region = $_POST['region'];
		$town = $_POST['town'];
		$old_name = $_POST['old_name'];

                $uid = $this->mysqli->real_escape_string($uid);
                $zip = $this->mysqli->real_escape_string($zip);
                $fname = $this->mysqli->real_escape_string($fname);
                $lname = $this->mysqli->real_escape_string($lname);
                $email = $this->mysqli->real_escape_string($email);
                $gender = $this->mysqli->real_escape_string($gender);
                $country = $this->mysqli->real_escape_string($country);
                $state = $this->mysqli->real_escape_string($state);

		if(!$zip)
			$zip = 0;

		$update_you_query = <<<EOF
			UPDATE profile AS t1
                        JOIN login AS t2
                        ON t1.uid = t2.uid
                        SET
				t2.fname="$fname",
				t2.lname="$lname",
				t2.email="$email",
				t1.zip=$zip,
				t1.language="$lang",
				t1.country="$country",
				t1.state="$state",
				t1.region="$region",
				t1.town="$town"
                        WHERE t1.uid ={$uid}
EOF;

                $you_results = $this->mysqli->query($update_you_query);
		if($old_name){
                        $hash_filename =  md5($uid.'CjaCXo39c0..$@)(c'.$filename);
                        $pic_100 = '100h_'.$hash_filename.'.gif';

                        $old_name = PROFILE_PIC_PATH.'/'.$old_name;
                        $new_name = PROFILE_PIC_PATH.'/'.$pic_100;
                        rename($old_name,$new_name);

                        $you_pic_query = "UPDATE login SET pic_100 = '{$pic_100}' WHERE uid = {$uid}";
                        $this->mysqli->query($you_pic_query);
                        return json_encode(array('success' => True,'pic' => True));
                }

                if($this->mysqli->affected_rows)
                        return json_encode(array('success' => True,'pic' => False));

                return json_encode(array('success' => False));
	}
}
