<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');

$gid = $_POST['gid'];
$descr = addslashes($_POST['descr']);
$focus = addslashes($_POST['focus']);
$email_suff = $_POST['email_suffix'];
$private = $_POST['private'];
$invite = $_POST['invite'];
$old_name = $_POST['old_name'];

if(isset($gid)){
   	$group_function = new group_functions();
        $results = $group_function->create_group($gid,$descr,$focus,$email_suffix,$private,$invite,$old_name);
        echo $results;
}


class group_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function create_group($gid,$descr,$focus,$email_suffix,$private,$invite,$old_name){

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

		if($old_name){
			$hash_filename =  md5($gid.'CjaCXo39c0..$@)(c'.$filename);
			$pic_100 = '100h_'.$hash_filename.'.gif';
			$pic_36 = '36wh_'.$hash_filename.'.gif';

			$small_pic = explode('_',$old_name);
			$small_pic  = '36wh_'.$small_pic[1];
			$old_name2 =  D_GROUP_PIC_PATH.'/'.$small_pic;
			$new_name2 = D_GROUP_PIC_PATH.'/'.$pic_36;

			$old_name = D_GROUP_PIC_PATH.'/'.$old_name;
			$new_name = D_GROUP_PIC_PATH.'/'.$pic_100;




			rename($old_name,$new_name);
			rename($old_name2,$new_name2);
			$gr_pic_query = "UPDATE groups SET pic_36 = '{$pic_36}',pic_100 = '{$pic_100}' WHERE gid = {$gid}";
			$this->mysqli->query($gr_pic_query);
			return json_encode(array('success' => True,'pic' => True));
		}

		if($this->mysqli->affected_rows)
			return json_encode(array('success' => True,'pic' => False));

		return json_encode(array('success' => False));

			
	}

}
