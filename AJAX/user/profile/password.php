<?php
/* CALLS:
	edit_profile.js
*/
session_start();
require('../../config.php');
require('../../api.php');


$old_pass = $_POST['old_pass'];
$new_pass = $_POST['new_pass'];
if($new_pass){
	$profile_function = new profile_functions();
	$results = $profile_function->update_pass($old_pass,$new_pass);
	echo $results;
}


class profile_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function update_pass($old_pass,$new_pass){

                $uid = $_SESSION['uid'];
		$old_pass = md5($old_pass);
		$new_pass = md5($new_pass);
	
		
		$check_pass = "SELECT NULL FROM login WHERE pass = '$old_pass' AND uid=$uid";
		$check_results = $this->mysqli->query($check_pass);

		if($check_results->num_rows){
			$update_pass_query = <<<EOF
				UPDATE login AS t1
				SET
					t1.pass="$new_pass"
				WHERE t1.uid ={$uid}
EOF;

			$pass_results = $this->mysqli->query($update_pass_query);

			if($this->mysqli->affected_rows)
				return json_encode(array('success' => True));
		}


                return json_encode(array('success' => False));
	}
}
