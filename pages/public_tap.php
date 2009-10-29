<?php

class public_tap extends Base{

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
		$this->page_name = "public_tap";
	
		parent::__construct();
		
		$mid = $_GET['mid'];
		$this->set($mid,'cid');

		//START get uname ( this is so that we can do tap.info/tap/id and not tap.info/user/uname/tap_id
		$get_uname = <<<EOF
		SELECT l.uname FROM special_chat AS sc
		JOIN login AS l ON l.uid = sc.uid
		WHERE sc.mid = {$mid} LIMIT 1
EOF;
		$get_uname_result = $this->db_class_mysql->db->query($get_uname);
		$res = $get_uname_result->fetch_assoc();
		$uname = $res['uname'];
		if(!$uname)
			return False;
		//END get uname

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
                        SELECT t1.pic_100,t1.pic_36,t1.uname,t1.help FROM login AS t1
                        WHERE t1.uid={$uid}
EOF;

                 $this->db_class_mysql->set_query($user_query,'get_user',"This gets the user who is logged in in order to display it to the homepage next to 'Welcome xxxx'");
                                $user = $this->db_class_mysql->execute_query('get_user');
			while($res = $user->fetch_assoc() ){
			$global_uname = $res['uname'];
                        $this->set($res['uname'],'user');
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
			$symbol = explode('.',$symbol);
			if($symbol[1] != 'com' && $symbol[1] != 'edu') $add = ' '.$symbol[1];
			$display_symbol = ucwords($symbol[0].$add);
			$add = null;
			
			
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

		if($_SESSION['uid'])
                        $logged_in_id = $_SESSION['uid'];
                else
                        $logged_in_id = 0;

		//START get tap
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
		WHERE t3.mid IN ( {$mid} ) ORDER BY t3.cid DESC LIMIT 10
EOF;
		$data_all_users_bits = $this->bit_generator($users_query_bits_info,'users_aggr');
		$this->set($data_all_users_bits,'user_bits');
		//END get tap

		//START get responses
		$responses = $this->load_response($mid);
		$this->set($responses,'responses');
		//END get responses

		//START invovled
		$this->set($this->person,'invovled');
		//END invovled	

		//START set the session uid for Orbited
		$this->set($_SESSION['uid'],'pcid');
		//END set the session uid for Orbited
		}


private function load_response($cid){
                $resp_message_query = <<<EOF
		SELECT l.uid,c.uname,c.chat_text,UNIX_TIMESTAMP(c.chat_time) AS chat_time
		FROM chat AS c 
		JOIN login AS l ON l.uid = c.uid
		WHERE c.cid = {$cid};
EOF;
                $responses_data = $this->db_class_mysql->db->query($resp_message_query);
                if($responses_data->num_rows){
                while($res = $responses_data->fetch_assoc()){
                        $uname = $res['uname'];
			$uid = $res['uid'];
                        $chat_text = $res['chat_text'];
                        $chat_timestamp = $res['chat_time'];

			$count[$uname] = $count[$uname] + 1;
			$resp_count = $count[$uname];

			$this->person[$uname] = array(
			'uname' => $uname,
			'count' => $resp_count,
			'pic' => 'test'
			);
		
                        $responses[] = array(
                        'uname' =>              $uname,
                        'chat_text'=>           $chat_text,
                        'chat_time'=>           $chat_timestamp
                        );
                }
                        return $responses;
                } else {
                        return null;
                }
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
