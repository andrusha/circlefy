<?php
/* CALLS:
	edit_profile.js
*/
session_start();
require('../config.php');
require('../api.php');
//require('../sql.php');


if(1){
	$profile_function = new profile_functions();
	$results = $profile_function->update_you();
	echo $results;
}


class profile_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
				private $results;
				//private $sqlObj;

        function __construct(){
			$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
			//$this->sqlObj = new sql_object(); 
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
		$private = $_POST['private'];
		$hash_name = $_POST['hash_name'];
		$new_uname = $_POST['uname'];


                $uid = $this->mysqli->real_escape_string($uid);
                $zip = $this->mysqli->real_escape_string($zip);
                $fname = $this->mysqli->real_escape_string($fname);
                $lname = $this->mysqli->real_escape_string($lname);
                $email = $this->mysqli->real_escape_string($email);
                $gender = $this->mysqli->real_escape_string($gender);
                $country = $this->mysqli->real_escape_string($country);
                $private= $this->mysqli->real_escape_string($private);
				$state = $this->mysqli->real_escape_string($state);
				$new_uname = $this->mysqli->real_escape_string($new_uname);

		if(!$zip)
			$zip = 0;

		$anonCheck = 1;
		//$update_you_query = $this->sqlObj->updateProfile($fname,$flname,$email,$private,$zip,$lang,$country,state,$region,$about,$town,$uname,$anonCheck,$uid);

		$change_uname = "";	
		if ($anonCheck == "1") { 
			$change_uname = " t2.uname=\"$new_uname\", ";
			$_COOKIE["uname"] = $new_uname;
			$_SESSION["uname"] = $new_uname;
		}
		
		$update_you_query = <<<EOF
			UPDATE profile AS t1
                        JOIN login AS t2
                        ON t1.uid = t2.uid
                        SET
				t2.fname="$fname",
				t2.lname="$lname",
				t2.email="$email",
				t2.private=$private,
				$change_uname
				t1.zip=$zip,
				t1.language="$lang",
				t1.country="$country",
				t1.state="$state",
				t1.region="$region",
				t1.about="$about",
				t1.town="$town"
                        WHERE t1.uid ={$uid}
EOF;

		//echo $new_uname . " . " . $anonCheck;
		//echo $update_you_query;

                $you_results = $this->mysqli->query($update_you_query);
		if($hash_name){
			$old_pics_query = <<<EOF
			SELECT pic_36,pic_100 FROM login WHERE uid = {$uid} LIMIT 1
EOF;
	                $old_pics_results = $this->mysqli->query($old_pics_query);
			while($res = $old_pics_results->fetch_assoc() ){
				$old_36 = $res['pic_36'];
				$old_100 = $res['pic_100'];

				if(strpos($old_36,'default') || strpos($old_100,'default')) $default_pics = 1;
			}
                        $pic_100 = 'med_'.$hash_name.'.gif';
                        $pic_36 = 'small_'.$hash_name.'.gif';

				

                        $you_pic_query = "UPDATE login SET pic_36 = '{$pic_36}', pic_100 = '{$pic_100}' WHERE uid = {$uid}";

                        $this->mysqli->query($you_pic_query);
		
			if(!$default_pics){	
				unlink(PROFILE_PIC_PATH.'/'.$old_36);
				unlink(PROFILE_PIC_PATH.'/'.$old_100);
			}
                }

                if($this->mysqli->affected_rows)
                        return json_encode(array('success' => True,'pic' => False, 'new_name' => $new_uname, 'anonCheck' => $anonCheck));

                return json_encode(array('success' => False));
	}
}
