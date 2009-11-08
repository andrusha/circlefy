<?php

class public_user extends Base{

	protected $text;
	protected $top;
	
	function __default(){
	}
	
	public function __toString(){
		return "Public User Object";
	}
	
	function __construct(){
				
		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->need_login = 1;
		$this->need_db = 1;
		$this->need_filter = 1;
		$this->input_debug_flag = 0;
		$this->page_name = "public_user";
	
		parent::__construct();
		
		$uname = $_GET['public_uid'];;	
		//This gets all users initial settings such as the groups he's in etc...
		//SECURITY ... I SHOULD at t2.status = 1 so that only members who are confirmed get updates	
		$get_user_id_query = "SELECT t1.uname,t1.uid,t2.gid,t3.zip FROM login AS t1
					LEFT JOIN group_members AS t2
					ON t1.uid = t2.uid
					LEFT JOIN profile AS t3
					ON t1.uid = t3.uid
					WHERE t1.uname='{$uname}' LIMIT 1";
	
		$get_user_id_result = $this->db_class_mysql->db->query($get_user_id_query);

			//This creates the array that holds all the users gids
			if($get_user_id_result->num_rows){
				while($res = $get_user_id_result->fetch_assoc()){
					$uid = $res['uid'];
					$public_uid = $res['uid'];
					$uname = $res['uname'];
					$zip = $res['zip'];
				}
			}else{
				$this->set('no_user','no_user');
				return false;
			}

		//START User Prefences
		$user_query = <<<EOF
                        SELECT t1.pic_100,t1.pic_36,t1.uid,t1.uname,t1.help FROM login AS t1
                        WHERE t1.uid={$uid}
EOF;

                 $this->db_class_mysql->set_query($user_query,'get_user',"This gets the user who is logged in in order to display it to the homepage next to 'Welcome xxxx'");
                                $user = $this->db_class_mysql->execute_query('get_user');
			while($res = $user->fetch_assoc() ){
			$global_uname = $res['uname'];
                        $this->set($res['uname'],'user');
                        $this->set($res['uid'],'uid');
                        $this->set($res['pic_36'],'user_pic_small');
                        $this->set($res['pic_100'],'user_pic_med');
			$this->set($res['help'],'help');
			}
	
		//START group setting creation
		$group_list_query = <<<EOF
			SELECT COUNT(scm.gid) as message_count,t2.symbol,t2.connected,t1.tapd,t1.inherit,t2.pic_36,t2.gname,t1.gid
			FROM group_members AS t1
			JOIN groups AS t2 ON t2.gid=t1.gid
			JOIN special_chat_meta AS scm ON scm.gid=t1.gid
			WHERE t1.uid={$uid}
			GROUP BY scm.gid
EOF;

                $this->db_class_mysql->set_query($group_list_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                                $groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');

		if($groups_you_are_in->num_rows)
		while($res = $groups_you_are_in->fetch_assoc()){
			$gid = $res['gid'];
			$gname = $res['gname'];
			$pic_36 = $res['pic_36'];
			$symbol = $res['symbol'];
			$connected = $res['connected'];
			$tapd = $res['tapd'];
			$message_count = $res['message_count'];

			//Process
	/*	
			$symbol = explode('.',$symbol);
			if($symbol[1] != 'com' && $symbol[1] != 'edu') $add = ' '.$symbol[1];
			$display_symbol = ucwords($symbol[0].$add);
			$add = null;
	*/		
			
			$my_groups_array[] = array(
				'gid' => $gid,
					'gname' => $gname,
					'pic_36' => $pic_36,
					'symbol' => $symbol,
					'display_symbol' => $gname,
					'type' => $connected,
					'tapd' => $tapd,
					'message_count' => $message_count
				);
			}
			$this->set($my_groups_array,'your_groups');	


		//START get uses taps
		$get_users_taps_query = $this->personal_filter(null,null,$public_uid);

		$this->db_class_mysql->set_query($get_users_taps_query,'public_users_taps',"Gets a specific users tap's");
		$users_bits_results = $this->db_class_mysql->execute_query('public_users_taps');

		if($users_bits_results->num_rows > 0){

			$return_list = $this->get_unique_id_list($users_bits_results);
			$uid_list = $return_list['uid_list'];
			$mid_list = $return_list['mid_list'];
	
		if($_SESSION['uid'])
			$logged_in_id = $_SESSION['uid'];
		else
			$logged_in_id = 0;
			
		$users_query_bits_info = <<<EOF
		SELECT t4.mid as good_id,TAP_ON.count AS viewer_count,
		t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,
		t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
		JOIN special_chat as t3
		ON t3.uid = t2.uid
		LEFT JOIN (
		SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$logged_in_id}
		) AS t4
		ON t4.mid = t3.cid
		LEFT JOIN TAP_ONLINE AS TAP_ON
		ON t3.mid = TAP_ON.cid
		WHERE t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10
EOF;


		$data_all_users_bits = $this->bit_generator($users_query_bits_info,'users_aggr');
		$this->set($data_all_users_bits,'user_bits');
		}

		//START stats
		$count_messages = <<<EOF
		SELECT COUNT(*) AS message_count FROM special_chat WHERE uid = {$uid}
EOF;
		$this->db_class_mysql->set_query($count_messages,'count_messages','Counts amount of messages a user has for a stat');
		$count_results = $this->db_class_mysql->execute_query('count_messages');
		if($count_results->num_rows)
		$res = $count_results->fetch_assoc();
			$message_count = $res['message_count'];

		$count_resp = <<<EOF
		SELECT COUNT(*) AS resp_count FROM chat WHERE uid = {$uid}
EOF;

		$this->db_class_mysql->set_query($count_resp,'count_resp','Counts amount of responses a user has for a stat');
		$count_results = $this->db_class_mysql->execute_query('count_resp');
		if($count_results->num_rows)
		$res = $count_results->fetch_assoc();
			$resp_count = $res['resp_count'];

		$count_group = <<<EOF
		SELECT COUNT(*) AS group_count FROM group_members WHERE uid = {$uid}
EOF;
		$this->db_class_mysql->set_query($count_group,'count_group','Counts amount of groups a user has for a stat');
		$count_results = $this->db_class_mysql->execute_query('count_group');
		if($count_results->num_rows)
		$res = $count_results->fetch_assoc();
			$group_count = $res['group_count'];

		if(!$message_count)
			$message_count = 0;
		if(!$resp_count)
			$resp_count = 0;
		if(!$group_count)
			$group_count = 0;

		$stats = array(
			'message_count' => $message_count,
			'response_count' => $resp_count,
			'group_count' => $group_count
		);
		$this->set($stats,'stats');
		//END stats

		//START set the session uid for Orbited
                $this->set($_SESSION['uid'],'pcid');
                //END set the session uid for Orbited
	}

 private function personal_filter($search,$responses,$public_uid){
                $uid = $public_uid;
                if($search)
                        $search_sql =  "AND chat_text LIKE '%{$search}%'";

                $personal_bits_query = <<<EOF
                SELECT mid FROM special_chat
                WHERE uid = {$uid}
                {$search_sql}
                ORDER BY cid DESC
                LIMIT 20
EOF;

                if($responses)
                $personal_bits_query = <<<EOF
                SELECT sc.mid FROM
                        ( SELECT cid FROM chat WHERE uid = {$uid} ) AS oc
                JOIN special_chat AS sc ON  sc.cid = oc.cid OR uid = {$uid}
                {$search_sql}
                GROUP BY sc.mid
                ORDER BY sc.mid DESC
                LIMIT 10;
EOF;

                return $personal_bits_query;
        }


private function bit_generator($query,$type){
	$this->db_class_mysql->set_query($query,'bit_gen_query',"This gets the initial lists of bits of type: {$type}");
	$m_results = $this->db_class_mysql->execute_query('bit_gen_query');
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
				$viewer_count = $res['viewer_count'];
                                $uid = $res['uid'];

                                //Process
				$chat_timestamp_raw = $chat_timestamp;
                                $chat_timestamp = $this->time_since($chat_timestamp);
                                $chat_timestamp = ($chat_timestamp == "0 minutes") ? "Seconds ago" : $chat_timestamp." ago";
                                $chat_text = stripslashes($chat_text);
				if($viewer_count)
					$viewer_count = $viewer_count-1;

                                //Additional
                                $rand = rand(1,999);


                                //Store
                                $messages[$cid] = array(
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
                                'viewer_count'=>     $viewer_count,
                                'last_resp'=>     null,
                                'resp_uname'=>    null,
				'count'=>	  0
                                );
				$mid_list .= $cid.',';
                        }
			//Don't do further processing for last tap
			$mid_list = substr($mid_list,0,-1);
		// START + Getting response data

                        $response_count = <<<EOF
			SELECT COUNT(oc.cid) AS count,c.cid AS cid,oc.chat_text,c.uname FROM
                        ( SELECT  MAX(mid) AS mmid,chat_text,cid FROM chat WHERE cid IN ( {$mid_list}  )
                        GROUP BY mid ORDER BY mid DESC)
                        AS oc
                        JOIN chat AS c ON oc.cid = c.cid AND oc.mmid = c.mid
                        GROUP BY oc.cid;
EOF;

			$this->db_class_mysql->set_query($response_count,'response_count',"Get's all the response count for each tap");
			$resp_count_results = $this->db_class_mysql->execute_query('response_count');
			if($resp_count_results->num_rows > 0){
                        while($res = $resp_count_results->fetch_assoc()){
                                $count = $res['count'];
				$last_resp = $res['chat_text'];
				$resp_uname = $res['uname'];
                                $cid = $res['cid'];
                                $messages[$cid]['count'] = $count;
                                $messages[$cid]['last_resp'] = $last_resp;
                                $messages[$cid]['resp_uname'] = $resp_uname;
                        }

                        foreach($messages as $v)
                                $pmessages[] = $v;
                // END + Getting response data
	                return $pmessages;
			} else {
			if($messages)
                        foreach($messages as $v)
                                $pmessages[] = $v;
			
			return $pmessages; }
}

private function time_since($original) {
    // array of time period chunks
    $chunks = array(
        array(60 * 60 * 24 * 365 , 'year'),
        array(60 * 60 * 24 * 30 , 'month'),
        array(60 * 60 * 24 * 7, 'week'),
        array(60 * 60 * 24 , 'day'),
        array(60 * 60 , 'hour'),
        array(60 , 'minute'),
    );
    
    $today = time(); /* Current unix time  */
    $since = $today - $original;
    
    // $j saves performing the count function each time around the loop
    for ($i = 0, $j = count($chunks); $i < $j; $i++) {
        
        $seconds = $chunks[$i][0];
        $name = $chunks[$i][1];
        
        // finding the biggest chunk (if the chunk fits, break)
        if (($count = floor($since / $seconds)) != 0) {
            // DEBUG print "<!-- It's $name -->\n";
            break;
        }
    }
    
    $print = ($count == 1) ? '1 '.$name : "$count {$name}s";
    
    if ($i + 1 < $j) {
        // now getting the second item
        $seconds2 = $chunks[$i + 1][0];
        $name2 = $chunks[$i + 1][1];
        
        // add second item if it's greater than 0
        if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
            $print .= ($count2 == 1) ? ', 1 '.$name2 : ", $count2 {$name2}s";
        }
    }
    return $print;
}

private function get_unique_id_list($mysql_object){
	while($res = $mysql_object->fetch_assoc() ){
	$mid = $res['mid'];
	$uid = $res['uid'];
		$counting++;
		if($counting !== 1){
			$mid_list .= ','.$mid;
			/* This if statement is here to ensure a unique list */
			if($uid != $old_uid) { $uid_list .= ','.$uid; }
		} else {
			$mid_list .= $mid;
			$uid_list .= $uid;
		}
		$old_uid = $uid;
	}
	return
	$return_list = array( 
	'mid_list' => $mid_list,
	'uid_list' => $uid_list
	);
}

}
?>