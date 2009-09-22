<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');

$gname = addslashes($_POST['gname']);
$symbol = addslashes($_POST['symbol']);
$descr = addslashes($_POST['descr']);
$focus = addslashes($_POST['focus']);
$email_suff = $_POST['email_suffix'];
$private = $_POST['private'];
$invite = $_POST['invite'];
$old_name = $_POST['old_name'];

if(isset($gname)){
   	$group_function = new group_functions();
        $results = $group_function->create_group($gname,$symbol,$descr,$focus,$email_suffix,$private,$invite,$old_name);
        echo $results;
}


class group_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function create_group($gname,$symbol,$descr,$focus,$email_suffix,$private,$invite,$old_name){

                $uid = $_SESSION["uid"];
                $uname = $_SESSION["uname"];

                $uid = $this->mysqli->real_escape_string($uid);
                $gid = $this->mysqli->real_escape_string($gid);

		$gadmin = $uid;

		//All form settings     
		$gadmin = $uid;
		if($email_suffix != '')
		$email_suffix = addslashes($email_suffix);
		else 
		$email_suffix = 0;
		

		if(isset($private))
		$private = addslashes($private);
		else
		$private = 0;
		

		if(isset($invite))
		$invite_only = addslashes($invite);
		else
		$invite_only = 0;

/*

		//The pather where the users picture is stored
		if($_FILES['picture_path'] != ''){
			$file_name = $gname.'_'.$_FILES['picture_path']['name'];
			$root = ROOT;
			$new_path = '/htdocs'.$root.'pictures/'.$file_name;
			move_uploaded_file($_FILES['picture_path']['tmp_name'],$new_path);
			$new_path = addslashes($new_path);
		} else {
			$picture_path = D_GROUP_PIC_PATH;
		}
*/

		$create_group_query = <<<EOF
		INSERT INTO groups(gname,symbol,gadmin,descr,focus,private,invite_only,email_suffix) 
		values("$gname","$symbol",$gadmin,"$descr","$focus",$private,$invite_only,$email_suffix)
EOF;
                $create_group_results = $this->mysqli->query($create_group_query);
		

		$last_id = $this->mysqli->query($this->last_id);

                $last_id = $last_id->fetch_assoc();
                $last_id = $last_id['last_id'];

		$gid = $last_id;
		
		$create_my_group_query = <<<EOF
		INSERT INTO group_members(uid,gid,admin) values({$uid},{$gid},1)
EOF;
                $this->mysqli->query($create_my_group_query);
	
		$hash_filename =  md5($gid.'CjaCXo39c0..$@)(c'.$filename);
		$pic_100 = '100h_'.$hash_filename.'.gif';

		$old_name = D_GROUP_PIC_PATH.'/'.$old_name;
		$new_name = D_GROUP_PIC_PATH.'/'.$pic_100;
		rename($old_name,$new_name);

		if($gid > 0){
			$gr_pic_query = "UPDATE groups SET pic_100 = '{$pic_100}' WHERE gid = {$gid}";
			$this->mysqli->query($gr_pic_query);
			return json_encode(array('success' => True));
			}
		else
			return json_encode(array('success' => False));
			
	}

}