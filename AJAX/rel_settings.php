<?php
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");

require('../config.php');

$name = $_POST['name'];
$tags = $_POST['tags'];
$zipcode = $_POST['zipcode'];
$gid = $_POST['gid'];

//Used for delete and update
$rid = $_POST['rid'];

//used to keep track of state of enabled
$state = $_POST['state'];

if(isset($name)){
	$rel_function = new rel_functions();
	$results = $rel_function->create_filter($name,$tags,$zipcode,$gid);
	echo $results;
}

if(isset($_POST['delete'])){
	$rel_function = new rel_functions();
	$results = $rel_function->del_filter($rid);
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

        function create_filter($name,$tags,$zipcode,$gid){

                $uid = $_COOKIE["uid"];
                $uname = $_COOKIE["uname"];

                $uid = $this->mysqli->real_escape_string($uid);
                $name = $this->mysqli->real_escape_string($name);
                $tags = $this->mysqli->real_escape_string($tags);
                $zipcode = $this->mysqli->real_escape_string($zipcode);
                $group = $this->mysqli->real_escape_string($group);

		$create_rel_query = "INSERT INTO rel_settings(uid,name,tags,zip,gid) values('{$uid}','{$name}','{$tags}','{$zipcode}',{$gid});";
                $create_rel_results = $this->mysqli->query($create_rel_query);
		$last_id = $this->mysqli->query($this->last_id);

                $last_id = $last_id->fetch_assoc();
                $last_id = $last_id['last_id'];
	
		$tag_list = explode(',',$tags);
		foreach($tag_list as $tag){
			$tag = trim($tag);
			$AND_tags = explode('AND',$tag);
			$type = 0;
			if($AND_tags[1]){
				$type = count($AND_tags);
				foreach($AND_tags as $AND_tag){
					$AND_tag = trim($AND_tag);
					$optimize_query_AND = "INSERT INTO rel_settings_query(uid,rrid,tags,zip,gid,type) values('{$uid}',{$last_id},'{$AND_tag}','{$zipcode}',{$gid},{$type});";
					$this->mysqli->query($optimize_query_AND);
				
				}
			} else { 
				$optimize_query = "INSERT INTO rel_settings_query(uid,rrid,tags,zip,gid,type) values('{$uid}',{$last_id},'{$tag}','{$zipcode}',{$gid},{$type});";
				$this->mysqli->query($optimize_query);
			}
		}

		

		$get_rel_query = "SELECT rid,name,tags,language,country,state,zip,gid,groups FROM rel_settings where rid = {$last_id}";
		$get_rel_results = $this->mysqli->query($get_rel_query);
		$res = $get_rel_results->fetch_assoc();

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

		if($res['gid'] == 0){
                        $gid = 'All';
                } else {
                        $gid = 'Tied to a group!';
                }

		$name = $res['name'];
		$rid = $res['rid'];

		$rel_string = $keywords.$lang.$country.$zip;		

		$html = <<<EOF
       <tr class="rel_rid_blue" id="rel_{$rid}">
                                <td class="rel_name_number">New!. {$name}</td>
                                <td class="active_rel">{$tags}</td>
                                <td class="active_loc">{$zip}</td>
                                <td class="active_group">{$gid}</td>
                                <td class="enable_green 1" id="state_{$rid}" onclick="update_enable({$rid},this.className[this.className.length-1]);">Enabled</td>
                                <td class="delete_rel"><a href="#" onclick='del_rel({$rid});'>Delete</a></td>
        </tr>
EOF;
 
		$json_res = json_encode(array('html' => "$html"));
		return $json_res;
	}

	function del_filter($rid){
		$uid = $_COOKIE["uid"];
                $uname = $_COOKIE["uname"];

		$del_rel_query = "DELETE FROM rel_settings where rid = {$rid}";
		$del_rel_results = $this->mysqli->query($del_rel_query);
		$del_rel_query2 = "DELETE FROM rel_settings_query where rrid = {$rid}";
		$del_rel_results2 = $this->mysqli->query($del_rel_query2);
		
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
