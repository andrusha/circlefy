<?php
/* CALLS:
	sign_up.js
*/
require('../config.php');
session_start();

$sign_up_object = new ajaz_sign_up();

$flag = $_POST['signup_flag'];

/* These are the sign up variables that are associate 1:1 to the HTML form names and JavaScript variables */
$uname = $_POST['uname'];
$fname = $_POST['fname'];
$email = $_POST['email'];
$password = $_POST['pass'];
$lang = $_POST['lang'];

if($flag){
	$sign_up_results = $sign_up_object->process_sign_up($uname,$fname,$email,$password,$lang);
	echo $sign_up_results;
}

/* Obsolete
if($flag == "check_im_status();"){
	$get_im_hash_results = $sign_up_object->check_im_status($_SESSION['wasp_attack']);
	echo $get_im_hash_results;
}
*/
class ajaz_sign_up{
	
		private $mysqli;
		private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
		private $results;
		private $uname;
		private $uid;
		private $lang;
	
	function __construct(){
				$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
	}
	
	
/* This is the start of the exeuction of 3 signup functions */
	
	/* Signup Function 1 (strips/check input, inserts user info into db, calls create_im_hash() */
	function process_sign_up($uname,$fname,$email,$password,$lang){
			
		$uname = $this->mysqli->real_escape_string($uname);
		$fname = $this->mysqli->real_escape_string($fname);
		$email = $this->mysqli->real_escape_string($email);
		$password = $this->mysqli->real_escape_string($password);
		$lang = $this->mysqli->real_escape_string($lang);

		list($finame,$lname) = explode(' ',$fname);
		
		$password = md5($password);
		
		$sign_up_query = "INSERT INTO login(uname,fname,lname,email,pass) values('{$uname}','{$finame}','{$lname}','{$email}','{$password}');";
		$sign_up_results = $this->mysqli->query($sign_up_query);
		
		$last_id = $this->mysqli->query($this->last_id);
		$last_id = $last_id->fetch_assoc();
		$last_id = $last_id['last_id'];
		$this->fname = $fname;
		$this->uid = $last_id;
		$this->uname = $uname;
		$this->email = $email;
		$this->lang = $lang;
	

		$_SESSION['uname'] = $this->uname;	
		$_SESSION['uid'] = $this->uid;

		$this->create_default_settings();
		$this->results = json_encode($this->results);
		return $this->results;
	}
	
	private function create_default_settings(){
		$fname = $this->fname;
		list($finame,$lname) = explode(' ',$fname);
		$comb1 = $fname;
		$comb2 = $finame;
		$comb3 = $lname;

		$this->populate_profile();
/*		$this->create_filter('My Area','',94301,0);
		$this->create_filter('My Name',$comb1.','.$comb2.','.$comb3,0,0);
		$this->create_filter('Random Area','',10002,0);
		$this->create_filter('Random Interest','Party,Beer Pong,Partying',0,0);
		$this->create_filter('Random Interests 2','emergency,fire',0,0);
		$this->create_filter('Random Word','copyright',0,0);
		$this->create_filter('Random Word 2','girls',0,0);*/
		$this->join_group(61022);
		$this->join_group(61033);
		$this->send_welcome_mail();
		if($_POST['fid'])
			$this->tap_friend($_POST['fid']);
	}

	function send_welcome_mail(){
		$subject = "Welcome to tap!";
		$body = "Welcome to tap.info , with tap you'll be able to stay connected with people and information
		you're interested.  tap also allows you to 'tap' into specific groups of people by sending a message
		to that group.  For example, if you want to send a message to everyone at Harvard, simply find the Harvard
		group via the autocompleter and people at Harvard will see that show up in their outside messages
		tab.  There's many applications and uses for tap, espcially when it comes to community management, so
		feel free to go wild using it!  Happy tapping!

		-Team Tap
		";
		$from = 'tap';
		$mail_val = mail($this->email,$subject,$body,$from);
	}

	function populate_profile(){
                $profile_query = "INSERT INTO profile(uid,lang) values($this->uid,'{$this->lang}');";
                $this->mysqli->query($profile_query);
	}

	function tap_friend($fid){
		$uname = $this->uname;
		$uid = $this->uid;		

                $friend_query = "INSERT INTO friends(fuid,uid) values('{$fid}','{$uid}');";
                $friend_results = $this->mysqli->query($friend_query);
        }

	function join_group($gid){
		$uname = $this->uname;
		$uid = $this->uid;		

                $create_rel_query = "INSERT INTO group_members(uid,gid) values('{$uid}',{$gid});";
                $create_rel_results = $this->mysqli->query($create_rel_query);
                $last_id = $this->mysqli->query($this->last_id);
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
