<?php
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");

require('../config.php');

$check = $_POST['group_check'];
$gname = $_POST['gname'];

if($check){
	$instance = new group_functions();
	$res = $instance->check_group($gname);
	echo $res;
}

class group_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function check_group($gname){

  	        $uid = $_COOKIE["uid"];
                $uname = $_COOKIE["uname"];

                $uid = $this->mysqli->real_escape_string($uid);
                $fid = $this->mysqli->real_escape_string($fid);

		$group_query = "SELECT gid,gname FROM groups WHERE gname LIKE '%{$gname}%' LIMIT 2;";
                $group_results = $this->mysqli->query($group_query);

		if($group_results->num_rows > 0){
			$dup_query = "SELECT gname FROM groups WHERE gname = '{$gname}' LIMIT 1;";
			$dup_res = $this->mysqli->query($dup_query);
			if($dup_res->num_rows > 0){
				$results = '<li id="duplicate_group" class="rel_add_group">Sorry, there is already a group with this name</li>';
				$results = json_encode(array('dup' => "$results","dup_init" => "true"));
				return $results;
			}
	
			$array_result = array();
			while($res = $group_results->fetch_assoc()){
				$html = '';
				$ul = '';
				$counter++;
				if($counter == 1){
				$ul = <<<EOF
				<span class="group_explinations">We found some similar groups you might want to join:</span>
EOF;
				}

				$html .= <<<EOF
				{$ul}<li class="rel_add_group">The '{$res['gname']}' group <a href=/rewrite/groups/{$res['gid']}>click here</a> to check it out</li>
EOF;
				$array_result[] = array($html);
			}
				$results = json_encode($array_result);
		} else {
			$results = json_encode(array('no_results' => 'null'));
		}
		return $results;
	}

}	
