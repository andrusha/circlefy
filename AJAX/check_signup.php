<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');

$type = $_POST['type'];
$val = $_POST['val'];

if(isset($val)){
   	$check_function = new check_functions();
	if($type == 1)
	        $results = $check_function->check_uname($val);
	if($type == 2)
        	$results = $check_function->check_email($val);
        echo $results;
}


class check_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function check_uname($val){

                $val = $this->mysqli->real_escape_string($val);

		$check_query = "SELECT uname FROM login WHERE uname = '{$val}'";
                $check_results = $this->mysqli->query($check_query);
		if($check_results->fetch_assoc() > 0)
			return json_encode(array('available' => false));
		else
			return json_encode(array('available' => true));
	}

        function check_email($val){

                $val = $this->mysqli->real_escape_string($val);

		$check_query = "SELECT email FROM login WHERE email = '{$val}'";
                $check_results = $this->mysqli->query($check_query);
		if($check_results->fetch_assoc() > 0)
			return json_encode(array('available' => false));
		else
			return json_encode(array('available' => true));
	}

}
