<?php
/* CALLS:
	homepage.phtml
*/
$usage = <<<EOF
Usage:

gname: name of the group
symbol: symbol of the group
focus: focus of the group
descr: description of the group
old_name: user uploaded picture path
EOF;

session_start();
require('../config.php');
require('../api.php');


$gname = addslashes($_POST['gname']);
$symbol = addslashes($_POST['symbol']);
$descr = addslashes($_POST['descr']);
$focus = addslashes($_POST['focus']);
$email_suff = $_POST['email_suffix'];
$private = $_POST['private'];
$old_name = $_POST['old_name'];
$invite = $_POST['invite'];
$country = $_POST['country'];
$state = $_POST['state'];
$region = $_POST['region'];
$town = $_POST['town'];


if(isset($gname)){
   	$group_function = new group_functions();
        $res = $group_function->create_group($gname,$symbol,$descr,$focus,$email_suffix,$private,$invite,$old_name,$country,$state,$region,$town);
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

        function create_group($gname,$symbol,$descr,$focus,$email_suffix,$private,$invite,$old_name,$country,$state,$region,$town){

            $uid = $_SESSION["uid"];
            $uname = $_SESSION["uname"];
            
		    if(!empty($uid)){
                $uid = $this->mysqli->real_escape_string($uid);
                //this line dont have sense
                $gid = $this->mysqli->real_escape_string($gid);
		        $gadmin = $uid;
            }
            
		    if(!$gadmin){
			    $gadmin = 0;
			    $descr = 'This is the default topic for a new group, click on manage group to change it!';
		    }

		    //All form settings     
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
		INSERT INTO groups(gname,symbol,gadmin,descr,focus,private,invite_only,email_suffix,country,state,region,town,created) 
		values("$gname","$symbol",$gadmin,"$descr","$focus",$private,$invite_only,$email_suffix,"$country","$state","$region","$town",NOW())
EOF;
        
        $create_group_results = $this->mysqli->query($create_group_query);
		$last_id = $this->mysqli->query($this->last_id);
        $last_id = $last_id->fetch_assoc();
        $last_id = $last_id['last_id'];
		$gid = $last_id;

		$GROUP_ONLINE_query = <<<EOF
		INSERT INTO GROUP_ONLINE(gid) values($gid)
EOF;
                $this->mysqli->query($GROUP_ONLINE_query);
	
		if($gadmin != 0){	
			$add_me_as_admin_query = <<<EOF
			INSERT INTO group_members(uid,gid,admin,status) values({$gadmin},{$gid},1,1)
EOF;
            $this->mysqli->query($add_me_as_admin_query);
		}

		$splice_pic = explode('_',$old_name);

		$hash_filename =  md5($gid.'CjaCXo39c0..$@)(c'.$filename);
		$pic_100 = 'med_'.$hash_filename.'.gif';
		$pic_36 = 'small_'.$hash_filename.'.gif';
		$favicon = 'fav_'.$splice_pic[1];

		$small_pic = explode('_',$old_name);
		$small_pic  = '36wh_'.$small_pic[1];

		$old_name2 =  D_GROUP_PIC_PATH.'/'.$small_pic;
		$new_name2 = D_GROUP_PIC_PATH.'/'.$pic_36;

		$old_name = D_GROUP_PIC_PATH.'/'.$old_name;
		$new_name = D_GROUP_PIC_PATH.'/'.$pic_100;

		if($gadmin !== 0){
		if($gid > 0){
                       @rename($old_name,$new_name);
                       @rename($old_name2,$new_name2);
                        $gr_pic_query = "UPDATE groups SET favicon = '{$favicon}', pic_36 = '{$pic_36}',pic_100 = '{$pic_100}' WHERE gid = {$gid}";
                        $this->mysqli->query($gr_pic_query);
			return array('success' => True);
			}
		else
			return array('success' => False);
		} else {	
			return array('success' => True);
		}
			
	}

}
