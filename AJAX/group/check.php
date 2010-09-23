<?php
/* CALLS:
	group_add.js

    PARAMS 
group_check = init flag to initiate the script
type = type of check you're doing ( name,symbol )
gname = name of group ( only applicable if `type == name` )
symbol = symbol of group ( only applicable if `type == symbol` )
*/
$usage = <<<EOF
Usage:
gname: name of the channel to check
group_check:   true if we are checking channel (always true in this case)
EOF;

session_start();
require('../../config.php');
require('../../api.php');


$check = $_REQUEST['group_check'];
$type = $_REQUEST['post'];
$gname = $_REQUEST['gname'];
$symbol = $_REQUEST['symbol'];

if($check){
	$instance = new group_functions();
		if($type == 'name')
		    $res = $instance->check_name($gname);
		if($type == 'symbol')
		    $res = $instance->check_symbol($symbol);
	    api_json_choose($res,$cb_enable);
}else{
    api_usage($usage);
}

class group_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }


	function check_symbol($symbol){
                $symbol = $this->mysqli->real_escape_string($symbol);
		$dupe_symbol_query = "SELECT gname,gid FROM groups WHERE symbol='{$symbol}' LIMIT 1";
		$dupe_res = $this->mysqli->query($dupe_symbol_query);
		if($dupe_res->num_rows > 0){
			$array_result = array();
			$dupe_res = $dupe_res->fetch_assoc();
			$gid = $dupe_res['gid'];
			$gname = $dupe_res['gname'];
			$array_result[] = array(
				'gid' => $gid,
				'gname' => $gname
			);
			$results = array("dupe" => True,"results" => $array_result);
		} else { 
			$results = array("dupe" => False);
		}
		return $results;
	}	

        function check_name($gname){
                $gname = $this->mysqli->real_escape_string($gname);

		$group_query = "SELECT gid,gname FROM groups WHERE gname LIKE '%{$gname}%' LIMIT 2;";
                $group_results = $this->mysqli->query($group_query);

		//If group results loosely
		if($group_results->num_rows > 0){
			$dupe_query = "SELECT gname FROM groups WHERE gname = '{$gname}' LIMIT 1;";
			$dupe_res = $this->mysqli->query($dupe_query);
			//If the exact name matches, return the exact name
			if($dupe_res->num_rows > 0){
				$results = 'This channel name has been taken';
			$results = array('dupe' => True, 'results' => $results);
			return $results;
			}
			//else show similar groups
	
			$array_result = array();
			while($res = $group_results->fetch_assoc()){
				$gname = $res['gname'];
				$gid = $res['gid'];

				$array_result[] = array(
					'gid' => $gid,
					'gname' => $gname
				);
			}
			$results = array('dupe' => False, 'results' => $array_result);
		} else {
			$results = array('dupe' => False, 'results' => null);
		}
		return $results;
	}

}	