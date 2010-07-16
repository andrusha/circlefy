<?php
/* CALLS:
	homepage.phtml
*/
$usage = <<<EOF
Usage:
    gid: id of the group
    focus: new focus of the group value
    descr: new group description value
EOF;

session_start();
require('../config.php');
require('../api.php');


$gid = $_POST['gid'];
$descr = addslashes($_POST['descr']);
$focus = addslashes($_POST['focus']);
$email_suff = $_POST['email_suffix'];
$private = $_POST['private'];
$invite = $_POST['invite'];
$old_pic_name = $_POST['pic_hash_name'];
$old_fav_name = $_POST['fav_hash_name'];

if(isset($gid)){
    $group_function = new group_functions();
    $res = $group_function->create_group($gid,$descr,$focus,$email_suffix,$private,$invite,$old_pic_name,$old_fav_name);
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

	function check_if_admin($gid,$uid){
                $check_if_admin_query = <<<EOF
                SELECT uid FROM group_members WHERE gid = {$gid} AND uid = {$uid} AND admin > 0
EOF;
                $check_if_admin_results = $this->mysqli->query($check_if_admin_query);
                if($check_if_admin_results->num_rows){
                        return 1;
                }else{
                        return 0;
                }
        }


        function create_group($gid,$descr,$focus,$email_suffix,$private,$invite,$pic_hash_name,$fav_hash_name){

                $uid = $_SESSION["uid"];
                $uname = $_SESSION["uname"];

                $uid = $this->mysqli->real_escape_string($uid);
                $gid = $this->mysqli->real_escape_string($gid);

		        $is_admin = $this->check_if_admin($gid,$uid);
                if(!$is_admin){
                    return false;
                }

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


		$create_group_query = <<<EOF
		UPDATE groups SET
			gadmin = $gadmin,
			descr = "$descr",
			focus = "$focus",
			private = $private,
			invite_only = $invite_only,
			email_suffix = $email_suffix
		WHERE gid = $gid
EOF;

                $create_group_results = $this->mysqli->query($create_group_query);

		if($pic_hash_name){
		        $old_pics_query = <<<EOF
                        SELECT pic_36,pic_100 FROM groups WHERE gid = {$gid} LIMIT 1
EOF;
                        $old_pics_results = $this->mysqli->query($old_pics_query);
                        while($res = $old_pics_results->fetch_assoc() ){
                                $old_36 = $res['pic_36'];
                                $old_100 = $res['pic_100'];

                                if(strpos($old_36,'default')) $default_pics = 1;
                        }
                        $pic_100 = $pic_hash_name;
                        $pic_36 = $pic_hash_name;

                        $you_pic_query = "UPDATE groups SET pic_36 = '{$pic_36}', pic_100 = '{$pic_100}' WHERE gid = {$gid}";
                        $this->mysqli->query($you_pic_query);

                        if(!$default && $old_100 != '100h_default_group.gif'){
                                unlink(D_GROUP_PIC_PATH.'/'.$old_100);
                        }
		}

		if($fav_hash_name){
		        $old_pics_query = <<<EOF
                        SELECT favicon FROM groups WHERE gid = {$gid} LIMIT 1
EOF;
                        $old_pics_results = $this->mysqli->query($old_pics_query);
                        while($res = $old_pics_results->fetch_assoc() ){
                                $favicon = $res['favicon'];
                                if(strpos($favicon,'default')) $default_pics = 1;
                        }
                        $pic_101 = $pic_hash_name;

                        $you_pic_query = "UPDATE groups SET favicon = '{$fav_hash_name}' WHERE gid = {$gid}";
                        $this->mysqli->query($you_pic_query);

                        if(!$default_pics && $old_100 != '100h_default_group.gif'){
                                unlink(D_GROUP_PIC_PATH.'/'.$old_100);
                        }
		}

		if($this->mysqli->affected_rows)
			return array('success' => True,'pic' => False);

		return array('success' => False);

			
	}

}
