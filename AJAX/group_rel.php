<?php
/* CALLS:
	group_rel.js
*/
session_start();
require('../config.php');
require('../api.php');


$gid = $_POST['gid'];
$name = $_POST['name'];
$tags = $_POST['tags'];
$zipcode = $_POST['zipcode'];

//Used for delete and update
$rid = $_POST['rid'];

//used to keep track of state of enabled
$state = $_POST['state'];

if(isset($name)){
	$rel_function = new rel_functions();
	$results = $rel_function->create_channel($name,$tags,$zipcode,$gid);
	echo $results;
}

if(isset($_POST['delete'])){
	$rel_function = new rel_functions();
	$results = $rel_function->del_channel($rid);
	echo $results;
}

if(isset($_POST['update'])){
   	$rel_function = new rel_functions();
        $results = $rel_function->toggle_enable($rid,$state);
        echo $results;
}


class rel_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function create_channel($name,$tags,$zipcode,$gid){

                $uid = $_SESSION["uid"];
                $uname = $_SESSION["uname"];

                $uid = $this->mysqli->real_escape_string($uid);
                $name = $this->mysqli->real_escape_string($name);
                $tags = $this->mysqli->real_escape_string($tags);
                $zipcode = $this->mysqli->real_escape_string($zipcode);

		$create_rel_query = "INSERT INTO rel_settings(uid,gid,name,tags,zip) values('0',{$gid},'{$name}','{$tags}','{$zipcode}');";
                $create_rel_results = $this->mysqli->query($create_rel_query);
		$last_id = $this->mysqli->query($this->last_id);
                $last_id = $last_id->fetch_assoc();
                $last_id = $last_id['last_id'];

		$get_rel_query = "SELECT rid,name,tags,language,country,state,zip,groups FROM rel_settings where rid = {$last_id}";
		$get_rel_results = $this->mysqli->query($get_rel_query);
		$res = $get_rel_results->fetch_assoc();

	//print_r($res);
		
                if($res['tags']){
                $tags = $res['tags'];
                } else {
		$tags = "Anything said in..";
		}

                if($res['language']){
                $lang = "Language: ".$res['language'];
                }

                if($res['country']){
                $country = "Country: ".$res['country'];
                }

                if($res['zip']){
                $zip = "Zipcode: ".$res['zip'];
                } else {
		$zip = "Any Location";
		}
		
		$name = $res['name'];
		$rid = $res['rid'];

		$rel_string = $keywords.$lang.$country.$zip;		

		$html = <<<EOF
       <tr class="rel_rid_blue" id="rel_{$rid}">
                                <td class="rel_name_number">New!. {$name}</td>
                                <td class="active_rel">{$tags}</td>
                                <td class="active_loc">{$zip}</td>
                                <td class="active_group">This Channel!</td>
                                <td class="enable_green 1" id="state_{$rid}" onclick="update_enable({$rid},this.className[this.className.length-1]);">Enabled</td>
                                <td class="delete_rel"><a href="#" onclick='del_rel({$rid});'>Delete</a></td>
        </tr>
EOF;

		$json_res = json_encode(array('html' => "$html"));
		return $json_res;
	}


	function del_channel($rid){
		$uid = $_SESSION["uid"];
                $uname = $_SESSION["uname"];

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
