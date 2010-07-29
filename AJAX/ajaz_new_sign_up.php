<?php
/* CALLS:
	sign_up.js
*/
require('../config.php');
require('../api.php');

session_start();

$sign_up_object = new ajaz_new_sign_up();

$flag = $_POST['signup_flag'];

/* These are the sign up variables that are associate 1:1 to the HTML form names and JavaScript variables */

$uname = $_POST['uname'];
$fname = $_POST['fname'];
$email = $_POST['email'];
$password = $_POST['pass'];
$lang = $_POST['lang'];
	$join_type = $_POST['joinType'];
	//$guest_uid = $_POST['guest_uid'];	// I already have a user, and a UID.

/*
if($join_type == 'group')
	$group = $_POST['joinValue'];
else
	$group =false;

if($join_type == 'user')
	$user = $_POST['joinValue'];
else
	$user =false;
 */

if($flag == 'normal' || $flag == 'signup_function();'){
	$sign_up_results = $sign_up_object->process_sign_up($uname,$fname,$email,$password,$lang,$group,$user);
	echo $sign_up_results;
}

class ajaz_new_sign_up{
	
		public $mysqli;
		private $results;
		private $uname;
		public $uid;
		private $lang;
		public $user;
		public $group;
	
	function __construct(){
		$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
	}
	
	
/* This is the start of the exeuction of 3 signup functions */
	
	/* Signup Function 1 (strips/check input, updates user info in db, calls create_im_hash() */
	function process_sign_up($uname,$fname,$email,$password,$lang,$group,$user){
			
		$uname = $this->mysqli->real_escape_string($uname);
		$fname = $this->mysqli->real_escape_string($fname);
		$email = $this->mysqli->real_escape_string($email);
		$password = $this->mysqli->real_escape_string($password);
		$lang = $this->mysqli->real_escape_string($lang);
		$group = $this->mysqli->real_escape_string($group);
		$user = $this->mysqli->real_escape_string($user);

		list($finame,$lname) = explode(' ',$fname);
	
		$password = md5($password);
		
		$this->uid = $_SESSION['uid'];
		$sign_up_query = "UPDATE login SET uname='{$uname}', email='{$email}', pass='{$password}', anon=0 WHERE uid='{$this->uid}';";
		$sign_up_results = $this->mysqli->query($sign_up_query);
		
		$this->fname = $fname;
		$this->uname = $uname;
		$this->email = $email;
		$this->lang = $lang;
		$this->user = $user;
		$this->group = $group;

		$_SESSION['uname'] = $this->uname;	
		$_SESSION['guest'] = 0;	
		//$_SESSION['uid'] = $this->uid;
		
		setcookie("GUEST_hash", "", time()-3600);
		setcookie("GUEST_uid", "", time()-3600);
		setcookie("GUEST_uname", "", time()-3600);

		$this->create_default_settings();
        $this->results = json_encode(array('success' => 1));
		//$this->results = json_encode($this->results);
		return $this->results;
	}
	
	private function create_default_settings(){
		// This is done in autoCreateUser now :)

		$fname = $this->fname;
		list($finame,$lname) = explode(' ',$fname);
		$comb1 = $fname;
		$comb2 = $finame;
		$comb3 = $lname;

		$this->populate_user();
/*		$this->create_filter('My Area','',94301,0);
		$this->create_filter('My Name',$comb1.','.$comb2.','.$comb3,0,0);
		$this->create_filter('Random Area','',10002,0);
		$this->create_filter('Random Interest','Party,Beer Pong,Partying',0,0);
		$this->create_filter('Random Interests 2','emergency,fire',0,0);
		$this->create_filter('Random Word','copyright',0,0);
		$this->create_filter('Random Word 2','girls',0,0);*/
/*
		$this->join_group(61022);
*/
//		$this->join_group(61033);

/*	
		if($this->group){
			$gid = $this->lookup_group();
			if($gid) $this->join_group2($gid);
		}

		if($this->user){
			$fid = $this->lookup_user();
		if($fid)
			$this->tap_friend2($fid);
		}
*/
		
		$this->send_welcome_mail();
	}

	
	public function lookup_user(){
                $user_query = "SELECT uid AS fid FROM login WHERE uname = '$this->user'";
                $fid_result = $this->mysqli->query($user_query);
		if($fid_result->num_rows){
			$res = $fid_result->fetch_assoc();
			$fid = $res['fid'];
		} else {
			$fid = false;
		}

		return $fid;
	}
	public function lookup_group(){
                $group_query = "SELECT gid FROM groups WHERE symbol = '$this->group'";
                $gid_result = $this->mysqli->query($group_query);
		if($gid_result->num_rows){
			$res = $gid_result->fetch_assoc();
			$gid = $res['gid'];
		} else {
			$gid = false;
		}

		return $gid;
	}

	function send_welcome_mail(){
		$subject = "Welcome to tap!";
		$from = "From: tap.info\r\n";
		$body = <<<EOF
     Welcome to tap.info , with tap you'll be able to stay connected with people and information
you're interested in.  tap also allows you to 'tap' into specific groups of people by sending a message
to that group.  For example, if you want to send a message to everyone at Python, simply find the Python 
group via the autocompleter and people at Python will see that show up in their outside messages
tab.  There's many applications and uses for tap, espcially when it comes to community management, so
feel free to go wild using it!  Happy tapping!

-Team Tap
http://tap.info
EOF;
		$mail_val = mail($this->email,$subject,$body,$from);
	}

	function populate_user(){
                $profile_query = "INSERT INTO profile(uid,language) values($this->uid,'{$this->lang}');";
                $this->mysqli->query($profile_query);
                $settings_query = "INSERT INTO settings(uid) values($this->uid);";
                $this->mysqli->query($settings_query);
                $notifications_query = "INSERT INTO notifications(uid) values($this->uid);";
                $this->mysqli->query($notifications_query);
                $TEMP_ONLINE_query = "INSERT INTO TEMP_ONLINE(uid) values($this->uid);";
                $this->mysqli->query($TEMP_ONLINE_query);
	}

	function tap_friend($fid){
		$uname = $this->uname;
		$uid = $this->uid;		
                $friend_query = "INSERT INTO friends(fuid,uid) values('{$fid}','{$uid}');";
                $friend_results = $this->mysqli->query($friend_query);
        }

	function tap_friend2($fid,$state=1){

		$uname = $this->uname;
		$uid = $this->uid;		

                $fid = $this->mysqli->real_escape_string($fid);
                $state = $this->mysqli->real_escape_string($state);

                $friend_email_query = "SELECT l.email FROM login AS l WHERE l.uid = {$fid} AND l.private != 1 LIMIT 1";
                $friend_email_result = $this->mysqli->query($friend_email_query);
                $res = $friend_email_result->fetch_assoc();


                if($state == 1 && $friend_email_result->num_rows){
                        $friend_query = "INSERT INTO friends(uid,fuid,time_stamp) values('{$uid}','{$fid}',NOW());";
                        $to = $res['email'];
                        $subject = "{$uname} now has you on tap.";
                                $from = "From: tap.info\r\n";
                                $body = <<<EOF
{$uname} now has you on tap and will receive anyting you say!  Say something awesome!

-Team Tap
http://tap.info
EOF;

                        //Checks the person getting tracked settings before sending them an email
                        $email_check_query = <<<EOF
                        SELECT uid FROM settings WHERE track = 1 AND uid = {$fid}
EOF;
                        $email_check_query = $this->mysqli->query($email_check_query);
                        if($email_check_query->num_rows)
                                mail($to,$subject,$body,$from);
                } else {
                        $friend_query = "DELETE FROM friends WHERE fuid = '{$fid}' AND uid = '{$uid}';";
                }
                $friend_results = $this->mysqli->query($friend_query);
                        $results = json_encode(array('success' => 1));

                        return $results;
        }


	function join_group($gid){
		$uname = $this->uname;
		$uid = $this->uid;		

                $create_rel_query = "INSERT INTO group_members(uid,gid) values('{$uid}',{$gid});";
                $create_rel_results = $this->mysqli->query($create_rel_query);
                $last_id = $this->mysqli->query($this->last_id);
        }

	function join_group2($gid){

		$uname = $this->uname;
		$uid = $this->uid;		

		$track_random = <<<EOF
		SELECT uid AS fid FROM group_members where gid = {$gid} AND admin = 0 ORDER BY RAND() LIMIT 4;
EOF;
                $find_rel_results = $this->mysqli->query($find_rel_query);

		while($res = $find_rel_results->fetch_assoc()){
			$fid = $res['fid'];
			$this->tap_friend2($fid);
		}
		


                //Set admin to 0 unless he was the creator of the group
                $admin = 0;

                $create_rel_query = "INSERT INTO group_members(uid,gid,admin) values('{$uid}',{$gid},{$admin});";
                $create_rel_results = $this->mysqli->query($create_rel_query);

                $last_id = $this->mysqli->query($this->last_id);

                $last_id = $last_id->fetch_assoc();
                $last_id = $last_id['last_id'];
                if($last_id > 0){
                        $get_admins_query = <<<EOF
                        SELECT g.gname,l.email FROM group_members AS gm
                        JOIN groups AS g ON gm.gid = g.gid
                        JOIN login AS l ON l.uid = gm.uid
                        JOIN settings AS s ON s.uid = l.uid
                        WHERE gm.admin > 0 AND gm.gid = {$gid} AND s.join_group = 1
EOF;

                        $get_admins_results = $this->mysqli->query($get_admins_query);
                        if($get_admins_results->num_rows)
                        while($res = $get_admins_results->fetch_assoc() ){
                                $to = $res['email'];
                                $gname = $res['gname'];

                                $subject = "$gname has a new member!";
                                $from = "From: tap.info\r\n";
                                $body = <<<EOF
Someone joined tap through your tap community page, terrific!  {$uname} joined your tap group at http://tap.info !  It seems others have joined as well, so keep the real-time collaberative tapping going.

Make sure you organize your community or group , so that everyone on tap will feel the communities greatness.

Feel free to invite more people and keep your good ( and popular ) community growing!

{$uname} ( and me! ) are glad to have you on tap. :)

-Team Tap
http://tap.info
EOF;
                                mail($to,$subject,$body,$from);
                        }
		}
	}


	//Whenever this function or rel_settings algo is updated , this needs to be updated
	function create_filter($name,$tags,$zipcode,$gid){
		$uname = $this->uname;
		$uid = $this->uid;		

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
	}


////////////////////////////////////////////
	
	/* Signup Function 2 (creates the IM hash that the associate the users IM w/ Chattap and that will use to add/remove screen names) */
	function create_im_hash($uid){	
		$rand_hash = "bOFUh&$4)(@&cn".rand(1,999999).rand(1,999999)."hh95939(*$#@(*lz[";
		$rand_hash = md5($rand_hash);
		$rand_hash = substr($rand_hash,4,24);
		
/*		$uid_hash = $uid.'95939(*$';
		$uid_hash = md5($uid_hash);
		$uid_hash = substr($uid_hash,4,22);*/
		
		$status = 'waiting';
		
		$create_im_query = "INSERT INTO im_status(uid,im_hash,im_status) values('{$uid}','{$rand_hash}','{$status}') ;";
		$create_im_results = $this->mysqli->query($create_im_query);
		
		$this->get_im_hash($uid,$rand_hash);
	}
	
	/* Signup Function 3 (gets his IM hash so that the user can see it on the 2nd step of the login) */
	function get_im_hash($uid,$rand_hash){
		$get_im_hash_query = "SELECT im_hash FROM im_status WHERE im_hash='{$rand_hash}' AND uid='{$uid}' AND im_status='waiting' LIMIT 1;";
		
		$get_im_hash_results = $this->mysqli->query($get_im_hash_query);
		
		$get_im_hash_results = $get_im_hash_results->fetch_assoc();
		
		$get_im_hash_results;
		$uid_array = array('wasp_attack' => $uid);
		$merged_array = $this->array_assoc_push($get_im_hash_results,$uid_array);
		
			$this->results = $merged_array;
	}

	function array_assoc_push(&$array){
		$args = func_get_args();
			foreach ($args as $v){
				if(is_array($v)){
					foreach ($v as $k2 => $v2){
						$array[$k2] = $v2;
					}
				}
			}
		return $array;
	}
	
/* This is the end of the exeuction of 3 signup functions */	
	
	
	function check_im_status($uid){
		
		$check_im_query = "SELECT uid FROM im_status WHERE uid ='{$uid}' and im_status='good';";
		$check_im_results = $this->mysqli->query($check_im_query);
		
		if($check_im_results->num_rows >= 1){
			$names = $this->get_screen_names($uid);
			
			$good = array('im_status' => 0);
			$good = json_encode($good);
			return $good;
			
		}	else {
			
			$bad = array('im_status' => 1);
			$bad = json_encode($bad);
			return $bad;
		}
	}
	
	function get_screen_names($uid){
		$get_names_query = "SELECT aim,msn,icq,irc,gtalk,yahoo FROM table_c WHERE uid ='{$uid}';";
		$get_names_results = $this->mysqli->query($get_names_query);
		return $check_im_results;
	}
}
?>
