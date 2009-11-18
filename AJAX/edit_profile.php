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
		$about = $_POST['about'];
		$gender =  $_POST['gender'];
		$email = $_POST['email'];
		$lang = $_POST['lang'];
		$country = $_POST['country'];
		$state = $_POST['state'];
		$region = $_POST['region'];
		$town = $_POST['town'];
		$hash_name = $_POST['hash_name'];

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
				t1.about="$about",
				t1.town="$town"
                        WHERE t1.uid ={$uid}
EOF;

                $you_results = $this->mysqli->query($update_you_query);
		if($hash_name){
			$old_pics_query = <<<EOF
			SELECT pic_36,pic_100 FROM login WHERE uid = {$uid} LIMIT 1
EOF;
	                $old_pics_results = $this->mysqli->query($old_pics_query);
			while($res = $old_pics_results->fetch_assoc() ){
				$old_36 = $res['pic_36'];
				$old_100 = $res['pic_100'];

				if(strpos($old_36,'default')) $default_pics = 1;
			}
                        $pic_100 = '100h_'.$hash_name.'.gif';
                        $pic_36 = '36wh_'.$hash_name.'.gif';

                        $you_pic_query = "UPDATE login SET pic_36 = '{$pic_36}', pic_100 = '{$pic_100}' WHERE uid = {$uid}";
                        $this->mysqli->query($you_pic_query);
		
			if(!$default){	
				unlink(PROFILE_PIC_PATH.'/'.$old_36);
				unlink(PROFILE_PIC_PATH.'/'.$old_100);
			}
                }

                if($this->mysqli->affected_rows)
                        return json_encode(array('success' => True,'pic' => False));

                return json_encode(array('success' => False));
	}
}
