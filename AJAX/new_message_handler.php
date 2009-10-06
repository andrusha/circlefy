<?php
/* CALLS:
	chat_window.js
*/
session_start();
require('../config.php');

$to_box = stripslashes($_POST['to_box']);
$to_list = json_decode($to_box);


$group_to = array();
$friend_to = array();
$keyword_to = array();

if($to_list)
foreach($to_list as $v){
	$to_string = explode(":",$v);
	//symbol type
	$type = $to_string[2];
	//actual symbol	
	$symbol = $to_string[1];
	//meta data about symbol
	$name = $to_string[0];

	if($type == 0 || $type == 1 || $type == 2)
		$group_to[] = $symbol;
	if($type == 99)
		$friend_to[] = $symbol;
	if($type == null) 
		$keyword_to[] = $symbol;
}

$my_gids = $_SESSION['gid'];
$my_zip = $_SESSION['zip'];
$msg = $_POST['msg'];


if($msg){
	$chat_obj = new chat_functions($group_to,$friend_to,$keyword_to,$my_gids,$my_zip);
	$chat_id = $chat_obj->create_channel($msg);
	echo $chat_id;
}

class chat_functions{

		public $group_to;
		public $friend_to;
		public $keyword_to;

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

		private $my_groups;
		private $my_zip;

		private $permissions = array();
		private $filter_group_queries;

		//START variables used to store meta-data
		private $meta_groups;
		private $meta_people;
		private $meta_keywords;
		//END variables used to store meta-data

		public $counter_data;
		

        function __construct($groups,$friends,$keywords,$my_gids,$my_zip){
				$this->group_to = $groups;
				$this->friend_to = $friends;
				$this->keyword_to = $keywords;
				$this->my_groups = $my_gids;
				$this->my_zip = $my_zip;

				//This sets initial group permissions
				$perm_list = explode(',',$this->my_groups);
				foreach($perm_list as $gid)
					$this->permissions[$gid] = "0,3";

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
		$init_message_query_ft = "INSERT INTO special_chat_fulltext(cid,uid,uname,chat_text,ip) values('{$last_id}','{$uid}','{$uname}','{$msg}',INET_ATON('{$addr}'))";
		$this->mysqli->query($init_message_query_ft);
	
		$html_msg = $this->bit_generator($last_id);
		$time = time();

		//Dispatch Active Conversation
		$init_active_convo = "INSERT INTO active_convo(mid,uid,active) values({$last_id},{$uid},1)";
		$this->mysqli->query($init_active_convo);


		//Call and get symbols parsed
		$parsed_symbols = $this->lexical_parser($msg);


		//Collect all uids to send message to via different criteria(s)
		$group_uids = $this->group_matches(array_merge($parsed_symbols['groups'],$this->group_to));
			$group_gids = $group_uids['gids'];
			$cgroup_gids = $group_uids['cgids'];
			$group_uids['groups'] = $group_uids['groups'];
		
		$direct_uids = $this->direct_matches($parsed_symbols['friends']);		
		$friend_uids = $this->friend_matches();

		//Calls the function to insert the meta data about the message into the specail_chat_meta table	
		$this->insert_meta_data($last_id);

		if($parsed_symbols['building'] !== false)
			$building_uids = $this->building_matches();
		$filter_uids = $this->filter_matches($msg,$sent_zip=$parsed_symbols['zips'],$static_zip=0,$gid_list=$group_gids,$cgid_list=$cgroup_gids);

		//Dispatch all messages with associated reason
		$aggr_ids = $this->aggr_ids($group_uids['groups'],$direct_uids['direct'],$friend_uids['friend'],$filter_uids['filters'],$building_uids['building'],$last_id,$uid);
	
		//Return information to user in JSON
		$msg_and_channel_id  = array('channel_id' => $last_id, 'time' => $time,'new_channel' => 'true','new_msg' => $html_msg, 'counter_data' => $this->counter_data);
		$msg_and_channel_id = json_encode($msg_and_channel_id);
		return $msg_and_channel_id;
	}
	//Returns the string without the first character
	static function remove_first_char($str){
		return substr($str, 1);
	}

	private function insert_meta_data($mid){
		if($this->meta_groups)
		foreach($this->meta_groups as $gid => $perm)
			$rows .= "($mid,$gid,$perm,NULL),";
		if($this->meta_people)
		foreach($this->meta_people as $uid)
			$rows .= "($mid,NULL,NULL,$uid),";
		$rows = substr($rows,0,-1);

		$init_meta_query = "INSERT INTO special_chat_meta(mid,gid,connected,uid) values $rows";
		
                $this->mysqli->query($init_meta_query);
	}

	//Parses out all and extracts all tokens into a clean, user-friendly associative array
	private function lexical_parser($msg){

			preg_match_all('/#[^,! ]+/i',$msg,$groups);
			preg_match_all('/#(\d{5})/i',$msg,$zips);
			preg_match_all('/\*[^,! ]+/i',$msg,$keywords);
			preg_match_all('/@[^,! ]+/i',$msg,$friends);
			$building = strpos($msg,'^^');

			$results['groups'] 	= array_map( array('chat_functions', 'remove_first_char'), $groups[0]);
			$results['zips'] 	= array_map( array('chat_functions', 'remove_first_char'), $zips[0]);
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
		$nl = '\n';
		if($groups != '')
			foreach($groups as $gid => $uids){
				foreach($uids as $uid){
					$insert_string .= "$uid $cid {$gid} 0 0 0".$nl;
				}
			}

		if($directs != '')
			foreach($directs as $uid){
				$insert_string .= "$uid $cid 0 0 0 2".$nl;
			}

		if($friends != '')
			foreach($friends as $uid){
				$insert_string .= "$uid $cid 0 0 $fuid 1".$nl;
			}
		
		if($building != '')
			foreach($building as $uid){
				$insert_string .= "$uid $cid 0 0 0 4".$nl;
			}

		if($filters != '')
			foreach($filters as $rid => $uid){
				$insert_string .= "$uid $cid 0 $rid 0 3".$nl;
			}
	
		$fp = fsockopen("localhost", 3333, $errno, $errstr, 30);
			$insert_string = '{"msg":"'.$insert_string.'"}'."\r\n";
			fwrite($fp,$insert_string);
			fclose($fp);

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
	
		$x=0;	
		if($direct_results->num_rows > 0)
		while($res = $direct_results->fetch_assoc()){
			$this->counter_data['building'] = $x++;
                        $uid_list['building'][] .= $res['uid'];
                }

	return $uid_list;
	}

	//This function gets all the matches that have to do with sending a message directly to someon via the @ symbol ( i.e. @Taso22 )
	//$friends_array = an array of all friends you sent a message to directly
	private function direct_matches($friends_array){
		foreach($friends_array as $v)
                        $friend_list .= '"'.$v.'",';
		foreach($this->friend_to as $v)
                        $friend_list .= '"'.$v.'",';

		
                $friends = rtrim($friend_list,',');

		$direct_query = "SELECT uid,uname FROM login WHERE uname IN ( $friends );";
		$direct_results = $this->mysqli->query($direct_query);
	
		$x=0;	
		if($direct_results->num_rows > 0)
		while($res = $direct_results->fetch_assoc()){
			$this->counter_data['direct'][] = $res['uname'];
                        $uid_list['direct'][] .= $res['uid'];
			$this->meta_people[] .= $res['uid'];
                }
	return $uid_list;
	}

	//This function gets all matches of the people who have you on tap ( or are 'following you' ) 
	private function friend_matches(){
		$friends_query = "SELECT uid FROM friends WHERE fuid = {$_SESSION['uid']}";
		$friends_results = $this->mysqli->query($friends_query);

		$x=1;
		if($friends_results->num_rows > 0)
		while($res = $friends_results->fetch_assoc()){
			$this->counter_data['friends'] = $x++;
			$uid_list['friend'][] = $res['uid'];
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
	
		$permission_query = <<<EOF
		SELECT t1.gname as gname,t1.gid AS gid,t1.symbol FROM groups AS t1
		WHERE symbol IN ( $groups ) GROUP BY symbol
EOF;

		$group_perm_matches = $this->mysqli->query($permission_query);

		$group_list = explode(',',$this->my_groups);

		//This is where the permissions are set per group
		//0 = Originating, 1 = Destin , 2 = Destin + Originating, 3 = Destin + Originating
		//Only people who are part of groups get all 0,1,2,3, everyone else gets 0,3	
		if($group_perm_matches->num_rows > 0)	
		while($res = $group_perm_matches->fetch_assoc()){
			$gid = $res['gid'];
	
			
			$sc =  strpos('x,'.$this->my_groups.',',','.$gid.',');
			if($sc != false){
					$this->meta_groups[$gid] = 2;
					$this->permissions[$gid] = "0,1,2,3";
					$in_groups .= $gid.',';
			} else { 
					$this->meta_groups[$gid] = 1;
					$this->permissions[$gid] = "1,3";
					$out_groups .= $gid.',';
			}
					$key = array_search($gid,$group_list);
					if($key !== false)
						unset($group_list[$key]);
		}
		$in_groups = substr($in_groups,0,-1);
		$out_groups = substr($out_groups,0,-1);

		//This is the array for group filterings
		$dynamic_query_list = array();
		//This is the array for filter filtering
		$filter_query_list = array();

		//The follow queries correspon to the 2 above arrays.  The first array ( for group filtering ) is for this method.  The second array ( for filter filtering is for the filters )
		//This is needed twice becasue the tables/data structures/processing are different
		foreach($group_list as $gid)
			$this->meta_groups[$gid] = 0;

		$groups_left = implode(',',$group_list);
		if($group_list != '' && $groups_left){
			$groups_left_query = "( t1.gid IN ( $groups_left ) AND t2.group_outside_state IN (0,3) )";
			$dynamic_query_list[] .= $groups_left_query;

			$filter_left_query = "( gid IN ( $groups_left ) AND group_outside_state IN (0,3) )";
			$filter_query_list[] .= $filter_left_query;
		}

		if($in_groups != ''){
			$in_groups_query = "( t1.gid IN ( $in_groups ) AND t2.group_outside_state IN(0,1,2,3) )";
			$dynamic_query_list[] .= $in_groups_query;

			$in_filter_query = "( gid IN ( $in_groups ) AND group_outside_state IN(0,1,2,3) )";
			$filter_query_list[] .= $in_filter_query;
		}

		if($out_groups != ''){
			$out_groups_query = "( t1.gid IN ( $out_groups ) AND t2.group_outside_state IN(1,3) )";
			$dynamic_query_list[] .= $out_groups_query;

			$out_filter_query = "( gid IN ( $out_groups ) AND group_outside_state IN(1,3) ) ";
			$filter_query_list[] .= $out_filter_query;
		}

		$all_group_queries = implode(" OR ",$dynamic_query_list);

		$filter_group_queries = implode(" OR ",$filter_query_list);

		//START this says states a filter tied to no group ( which would be a keyword or whatever ) 
		if($filter_group_queries != array() )
			$filter_group_queries .= " OR gid=0 ";
		else
			$filter_group_queries .= " gid=0 ";

		$this->filter_group_queries = $filter_group_queries;

		$groups_query = <<<EOF
		SELECT t1.gname as gname,t1.gid AS gid,t1.connected AS c,t2.uid AS uid FROM groups AS t1
		JOIN group_members AS t2
		ON t2.gid = t1.gid 
		WHERE 
		$all_group_queries
EOF;

		$group_matches = $this->mysqli->query($groups_query);

		if($group_matches->num_rows > 0){
		while($res = $group_matches->fetch_assoc() ) {
			$c = $res['c'];
		

			$uid_list['groups'][$res['gid']][] .= $res['uid'];
			if(!$c){
				$uid_list['gids'][] = $res['gid'];
				$g = 0;
			} else {
				$uid_list['cgids'][] = $res['gid'];
				$c = 1;
			}
				$this->counter_data['groups'][$res['gname']][0]++;
		}


		if($g)
		$uid_list['gids'] = array_unique($uid_list['gids']);
		if($c)
		$uid_list['cgids'] = array_unique($uid_list['cgids']);
		$g = 0;
		$c = 0;
		}

		if($group_perm_matches->num_rows > 0){
		$group_perm_matches->data_seek(0);
		while($res = $group_perm_matches->fetch_assoc() )
			if($this->counter_data['groups'][$res['gname']] == 0)
				$null_msg = "<span class='null_tap'>Nobody at {$res['symbol']} got your msg, join  the connected group {$res['symbol']}  to get information from/to there</span>";
				$this->counter_data['groups'][$null_msg] = '';

		}
	return $uid_list;
	}

	//$sent_zip = zipcode message is destine for
	//$static_zip = persons zipcode
	//$string = message
	private function filter_matches($string,$sent_zip=array(0),$static_zip=0,$gid_list='',$cgid_list=''){
		if($this->my_zip)
		$static_zip = $this->my_zip;
		
		//START parsing to see who gets what
		$array =  explode(' ',$string);
		$temp = $array;
	
	
		//This parses out every single gramitical phrase in the message.  The phrases are then sent into the IN() clause
		//This method is A LOT faster then a full-text search and has many more perks. As each keyword is indexed. It's also much more real-time.
		while($array){
			while($temp){
				if(!$flag)
				$count = count($temp);
				if($count < 8){
					$match = implode(' ',$temp);
					$total++;
					if($match != "")
					$final .= '"'.$match.'",';
				}
				$flag = 1;
				$count--;
				array_pop($temp);
			}
			$flag=0;
			array_shift($array);
			$temp = $array;
			}

		$phrase_list = rtrim($final,',');
	
		$zips = array();
		if($sent_zip[0] == 0){
			$zips[] .= $static_zip;
		} else {
			foreach($sent_zip as $zip){
				$zips[] .= $zip.",";
			}
			$zips[] .= $static_zip;
		}
	
		$zip_list = implode('',$zips);

		$gpm = $this->filter_group_queries;

		/*
		Data Processing via SQL

		IN() Clauses Represent:
			
		1) Tags + Zipcode(any) + Group(any)
		2) Zipcode
		3) Zipcode + Group
				
	
		*/

		$phrase_query = <<<EOF
		SELECT rrid,tags,zip,gid,uid,enabled,type FROM rel_settings_query WHERE
		(tags IN({$phrase_list}) AND zip IN({$zip_list},0) AND ( {$gpm} )  )
		OR
		(tags="" AND zip IN({$zip_list}) AND gid=0)
		OR
		(tags="" AND zip IN({$zip_list}) AND ( {$gpm} ) )
		AND enabled = 1;
EOF;

		$phrase_matches = $this->mysqli->query($phrase_query);

		//This processes the uid's while keeping AND/OR logic in mind
		//if a `type` is great then 0, that means it adheres to AND logic, and the counter needs to add up to the `type`
		//i.e. if you have a type of 2 there must be two matches, increasing the counter to two eventually and having it math
		//if the type is 0 that means it adheres to OR logic and only needs to be matched on once, and it automatically added in
		$type_counter=0;
		$old_rid=0;
		if($phrase_matches->num_rows > 0)
		while($res = $phrase_matches->fetch_assoc() ) {
			$type = $res['type'];
			$rrid = $res['rrid'];
			$uid = $res['uid'];
			$zip = $res['zip'];
			//This line should be change if more control is zip control is wanted ( i.e. destination/origination )
			
			if(1){ //This if(1) statement was different in earlier revisions, it might have to go back, I don't think so , but maybe.
				if($rrid != $old_rrid)
					$type_counter=0;
				if(!$type){
					$this->counter_data['other'][0]++;
					$uid_list['filters'][$rrid] .= $uid;
				} else {
					$type_counter++;
					if($type == $type_counter){
						$uid_list['filters'][$rrid] .= $uid;
						$this->counter_data['other'][0]++;
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
		$m_results = $this->mysqli->query($query);
		if($m_results->num_rows)
		while($res = $m_results->fetch_assoc()){
			//Setup
			$mid = $res['mid'];
			$special = $res['special'];
			$chat_timestamp = $res['chat_timestamp'];
			$cid = $res['cid'];
			$chat_text = $res['chat_text'];
			$uname = $res['uname'];
			$fname = $res['fname'];
			$lname  = $res['lname'];
			$pic_100 = $res['pic_100'];
			$pic_36 = $res['pic_36'];
			$uid = $res['uid'];

			//Process
			$chat_timestamp_raw = $chat_timestamp;
			$chat_timestamp = "Now!";
			$chat_text = stripslashes($chat_text);

			//Additional
			$rand = rand(1,999);

			//Store
			$messages[] = array(
			'mid' =>           $mid,
			'special'=>       $special,
			'chat_timestamp'=>$chat_timestamp,
			'chat_timestamp_raw'=>$chat_timestamp_raw,
			'cid'=>           $cid,
			'chat_text'=>     $chat_text,
			'uname'=>         $uname,
			'fname'=>         $fname,
			'lname'=>         $lname,
			'pic_100'=>       $pic_100,
			'pic_36'=>        $pic_36,
			'uid'=>           $uid,
			'last_resp'=>     null,
                        'resp_uname'=>    null,
                        'count'=>         0
			);
		}
	return $messages;
	}


	//END of function
}
//END of class

?>
