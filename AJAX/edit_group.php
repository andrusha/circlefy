<?php
/* CALLS:
	edit_group.js
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

		$gid = $_POST['gid'];
		$gname = $_POST['gname'];
		$focus = $_POST['focus'];
		$descr =  $_POST['descr'];

                $gid = $this->mysqli->real_escape_string($gid);
                $gname = $this->mysqli->real_escape_string($gname);
                $focus = $this->mysqli->real_escape_string($focus);
                $descr = $this->mysqli->real_escape_string($descr);

		$update_you_query = <<<EOF
			UPDATE groups AS t1
                        SET
				t1.gname="{$gname}",
				t1.focus="{$focus}",
				t1.descr="{$descr}"
                        WHERE t1.gid = {$gid};
EOF;
                $you_results = $this->mysqli->query($update_you_query);
		$json_res = array('good' => 1);
		return $json_res;
	}

}
?>
