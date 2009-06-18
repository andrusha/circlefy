<?php
/* CALLS:
	chat_window.js
*/
session_start();
require('../config.php');

$msg = $_POST['msg'];
$time = $_POST['time'];
$channel_id = $_POST['channel_id'];

if($msg){
	$chat_obj = new chat_functions();
	$chat_id = $chat_obj->create_channel($msg);
	echo $chat_id;
}

class chat_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function create_channel($msg){
			
		$uid = $_SESSION['uid'];
		$uname = $_SESSION['uname'];
		$addr = $_SERVER['REMOTE_ADDR'];

		$uid = $this->mysqli->real_escape_string($uid);	
		$msg = $this->mysqli->real_escape_string($msg);	
		$uname = $this->mysqli->real_escape_string($uname);

		// !!! This is where the filter code has to code, it will process the input of users and match and alert other users !!!
		$create_channel_query = "INSERT INTO channel(uid) values('{$uid}');";

		$create_channel_results = $this->mysqli->query($create_channel_query);
		$last_id = $this->mysqli->query($this->last_id);

		$last_id = $last_id->fetch_assoc();
		$last_id = $last_id['last_id'];

		$init_message_query = "INSERT INTO special_chat(cid,uid,uname,chat_text,ip) values('{$last_id}','{$uid}','{$uname}','{$msg}',INET_ATON('{$addr}'))";
		$this->mysqli->query($init_message_query);
		$last_id2 = $this->mysqli->query($this->last_id);
	
		$html_msg = $this->bit_generator($last_id);
		$time = time();

		//Dispatch Active Conversation
		$init_active_convo = "INSERT INTO active_convo(mid,uid,active) values({$last_id},{$uid},1)";
		$this->mysqli->query($init_active_convo);

		$msg_and_channel_id  = array('channel_id' => $last_id, 'time' => $time,'new_channel' => 'true','new_msg' => $html_msg);
		$msg_and_channel_id = json_encode($msg_and_channel_id);

		//Call and get symbols parsed
		$parsed_symbols = $this->lexical_parser($msg);

		//Collect all uids to send message to via different criteria(s)
		$group_uids = $this->group_matches($parsed_symbols['groups']);
		$direct_uids = $this->direct_matches($parsed_symbols['friends']);		
		$friend_uids = $this->friend_matches();
		if($parsed_symbols['building'] !== false)
			$building_uids = $this->building_matches();
		$filter_uids = $this->filter_matches($msg);

		//Dispatch all messages with associated reason
		$aggr_ids = $this->aggr_ids($group_uids['groups'],$direct_uids['direct'],$friend_uids['friend'],$filter_uids['filters'],$building_uids['building'],$last_id,$uid);
		
		return $msg_and_channel_id;
	}
	//Returns the string without the first character
	static function remove_first_char($str){
		return substr($str, 1);
	}

	//Parses out all and extracts all tokens into a clean, user-friendly associative array
	private function lexical_parser($msg){

			preg_match_all('/#[^,! ]+/i',$msg,$groups);
			preg_match_all('/\*[^,! ]+/i',$msg,$keywords);
			preg_match_all('/@[^,! ]+/i',$msg,$friends);
			$building = strpos($msg,'^^');

			$results['groups'] 	= array_map( array('chat_functions', 'remove_first_char'), $groups[0]);
			$results['keywords'] 	= array_map( array('chat_functions', 'remove_first_char'), $keywords[0]);
			$results['friends'] 	= array_map( array('chat_functions', 'remove_first_char'), $friends[0]);
			$results['building'] 	= $building;
			return $results;
	}

	//This function aggregates all uids from all match functions into one for the final insert
	private function aggr_ids($groups,$directs,$friends,$filters,$building,$cid,$fuid){
//		$start = microtime(true);

		/*
		FORMAT FOR TABLES ARE AS FOLLOWS:
		uid 	cid	 gid	rid	fuid	type

		types: group = 0 , friends = 1, direct = 2, filters = 3, fuid = 4, type = 5
		NOTE: 0 = default values
		*/
		if($groups != '')
			foreach($groups as $gid => $uids){
				foreach($uids as $uid){
					$insert_string .= "$uid $cid {$gid} 0 0 0\n";
				}
			}

		if($directs != '')
			foreach($directs as $uid){
				$insert_string .= "$uid $cid 0 0 0 2\n";
			}

		if($friends != '')
			foreach($friends as $uid){
				$insert_string .= "$uid $cid 0 0 $fuid 1\n";
			}
		
		if($building != '')
			foreach($building as $uid){
				$insert_string .= "$uid $cid 0 0 0 4\n";
			}

		if($filters != '')
			foreach($filters as $rid => $uid){
				$insert_string .= "$uid $cid 0 $rid 0 3\n";
			}
		
		for($i = 0;$i < 5;$i++){
			$fp = fopen("/var/data/flat/flat_$i", "a");
			if (flock($fp, LOCK_NB|LOCK_EX)){
				fwrite($fp,$insert_string);
				break;
			} else {
				continue;
			}
		}
		$cache_myself = "SELECT t2.cid,t2.chat_text,t1.uname,t1.pic_100,t1.fname,t1.lname,t1.uid AS fuid FROM login AS t1 JOIN special_chat AS t2 ON t1.uid = t2.uid WHERE t1.uid = {$fuid} AND t2.cid = {$cid} LIMIT 1";
		$res_to_cache = $this->mysqli->query($cache_myself);
		/*
		while($php_res = $res_to_cache->fetch_assoc()){
			$ubiq_res["row"] .= ;
		}
		$ubiq_res = json_encode($ubiq_res);
*/
		$ubiq_res = json_encode($res_to_cache->fetch_assoc());
		$memcache = new Memcache;
		$memcache->connect('127.0.0.1', 11211) or die ("Could not connect");
		$memcache->set("$cid",$ubiq_res, false, 30) or die ("Failed to save data at the server");
		//$end = microtime(true);echo $end - $start;
	}


	private function building_matches(){
		$addr = $_SERVER['REMOTE_ADDR'];
		$direct_query = "SELECT uid FROM login WHERE ip = INET_ATON('$addr');";
		$direct_results = $this->mysqli->query($direct_query);
		
		if($direct_results->num_rows > 0)
		while($res = $direct_results->fetch_assoc()){
                        $uid_list['building'][] .= $res['uid'];
                }
	return $uid_list;
	}

	//This function gets all the matches that have to do with sending a message directly to someon via the @ symbol ( i.e. @Taso22 )
	//$friends_array = an array of all friends you sent a message to directly
	private function direct_matches($friends_array){
		foreach($friends_array as $v){
                        $friend_list .= '"'.$v.'",';
                }
                $friends = rtrim($friend_list,',');

		$direct_query = "SELECT uid FROM login WHERE uname IN ( $friends );";
		$direct_results = $this->mysqli->query($direct_query);
		
		if($direct_results->num_rows > 0)
		while($res = $direct_results->fetch_assoc()){
                        $uid_list['direct'][] .= $res['uid'];
                }
	return $uid_list;
	}

	//This function gets all matches of the people who have you on tap ( or are 'following you' ) 
	private function friend_matches(){
		$friends_query = "SELECT fuid FROM friends WHERE uid = {$_SESSION['uid']}";
		$friends_results = $this->mysqli->query($friends_query);

		if($friends_results->num_rows > 0)
		while($res = $friends_results->fetch_assoc()){
			$uid_list['friend'][] = $res['fuid'];
		}	
	return $uid_list;
	}


	//This function gets all of the groups to send to based on your your direct message to them via the # symbol ( i.e. #Stanford )
	//$group_array = an array of groups you sent to
	private function group_matches($groups_array){

		foreach($groups_array as $v){
			$group_list .= '"'.$v.'",';
		}
		$groups = rtrim($group_list,',');

		$groups_query = <<<EOF
		(
		SELECT t1.gid AS gid,t2.uid AS uid FROM groups AS t1
		JOIN group_members AS t2
		ON t2.gid = t1.gid
		WHERE t1.gname IN ( $groups )
		) UNION ALL (
		SELECT t1.gid AS gid,t2.uid AS uid FROM connected_groups AS t1
                JOIN group_members AS t2
                ON t2.gid = t1.gid
                WHERE t1.domain IN ( $groups)
		)
EOF;

		$group_matches = $this->mysqli->query($groups_query);
		if($group_matches->num_rows > 0)
		while($res = $group_matches->fetch_assoc() ) {
			$uid_list['groups'][$res['gid']][] .= $res['uid'];
		}
	
	return $uid_list;
		
	}

	//$sent_zip = zipcode message is destine for
	//$static_zip = persons zipcode
	//$string = message
	private function filter_matches($string,$sent_zip=0,$static_zip=0){
	
		//START parsing to see who gets what
		$array =  explode(' ',$string);
		$temp = $array;

		//This parses out every single gramitical phrase in the message.  The phrases are then sent into the IN() clause
		//This method is A LOT faster then a full-text search and has many more perks. As each keyword is indexed. It's also much more real-time.
		while($array){
			while($temp){
				$final .= '"'.implode(' ',$temp).'",';
				array_pop($temp);
			}
			array_shift($array);
			$temp = $array;
			}

		$phrase_list = rtrim($final,',');
		$phrase_query = "SELECT rrid,tags,zip,gid,uid,enabled,type FROM rel_settings_query WHERE tags IN( ".$phrase_list." ) and enabled = 1;";
		$phrase_matches = $this->mysqli->query($phrase_query);

		//This processes the uid's while keeping AND/OR logic in mind
		//if a `type` is great then 0, that means it adheres to AND logic, and the counter needs to add up to the `type`
		//i.e. if you have a type of 2 there must be two matches, increasing the counter to two eventually and having it math
		//if the type is 0 that means it adheres to OR logic and only needs to be matched on once, and it automatically added in
		$type_counter=0;
		$old_rid=0;
		while($res = $phrase_matches->fetch_assoc() ) {
			$type = $res['type'];
			$rrid = $res['rrid'];
			$uid = $res['uid'];
			$zip = $res['zip'];
			//This line should be change if more control is zip control is wanted ( i.e. destination/origination )
			if($zip = 0 || $sent_zip == $zip || $static_zip == $zip){
				if($rrid != $old_rrid)
					$type_counter=0;
				if(!$type){
					$uid_list['filters'][$rrid] .= $uid;
				} else {
					$type_counter++;
					if($type == $type_counter){
						$uid_list['filters'][$rrid] .= $uid;
					} else { 
						$old_rrid = $rrid;
					}
				}
			}
		}
		return $uid_list;	
		//END parsing to see who gets what
	}

	//This generates the new bit HTML so that it can be displayed once you send your message
	//$mid = the new channel id ( will change, because mid/cid is confusing and it really is a new channel.. not message ) 
	private function bit_generator($mid){
			$query = <<<EOF
			SELECT t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
                        JOIN special_chat as t3
                        ON t3.uid = t2.uid
                        WHERE t3.mid = {$mid}
                        LIMIT 1;
EOF;
			$type = "self";
			$counter = 0;
			$bit_gen_results = $this->mysqli->query($query);

                        while($res = $bit_gen_results->fetch_assoc() ){
                             /*   $chat_timestamp = $this->time_since($res['chat_timestamp']);
                                $chat_timestamp = ($chat_timestamp == "0 minutes") ? "Seconds ago" : $chat_timestamp." ago";*/
				$chat_timestamp = "Now!";
                                $pic_36 = $res['pic_36'];
                                $uid = $res['uid'];
                                $color_counter++;
                                $chat_text = stripslashes($res['chat_text']);
                                $cid = $res['cid'];
                                $uname = $res['uname'];
                                $fname = $res['fname'];
                                $lname = $res['lname'];
                                $pic_100 = $res['pic_100'];
                                $special = $res['special'];
				$resp_time = time();

				$color_class = 'self_bit';

                                $final_html = <<<EOF
                                <div id="super_bit_{$cid}_{$type}_{$rand}">
<div class="bit {$color_class} {$cid}_bit" id="bit_{$cid}_{$type}_{$rand}">

        <span class="bit_img_container"><img class="bit_img" src="pictures/{$pic_100}" /></span>
        <span class="bit_text">
                <a href="profile">{$uname}</a>: {$chat_text}
        </span>
        <span class="bit_timestamp"><i>{$chat_timestamp}</i></span>
        <ul class="bits_lists_options">
                {$good}
                <li id="toggle_show_response_button" class="0" onclick="toggle_show_response('responses_{$cid}_{$type}_{$rand}',this,1)"><img src="images/icons/text_align_left.png" /> <span class="bits_lists_options_text">View Replies </span></li>
                <li class="0" onclick="toggle_show_response('respond_{$cid}_{$type}_{$rand}',this,0); toggle_show_response('responses_{$cid}_{$type}_{$rand}',document.getElementById('toggle_show_response_button'),0);"><img src="images/icons/comment.png" /> <span class="bits_lists_options_text">Respond </span></li>
        </ul>

</div>

<div class="respond_text_area_div" id="respond_{$cid}_{$type}_{$rand}">
<ul>
        <li><textarea class="textarea_response gray_text" id="textarea_response_{$cid}" onfocus="if (this.className[this.className.length-1] != '1') vanish_text('textarea_response',this);">Response..</textarea></li>
        <li><button>Send</button></li>
</ul>

</div>

        <ul class="bit_responses {$cid}_resp" id="responses_{$cid}_{$type}_{$rand}">
EOF;
				}
	return $final_html;
	}
	//END of function
}
//END of class

?>
