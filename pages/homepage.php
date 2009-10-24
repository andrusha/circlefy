<?php

class homepage extends Base{

	protected $text;
	protected $top;
	
	function __default(){
	}
	
	public function __toString(){
		return "Homepage Object";
	}
	
	function __construct(){
				
		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->need_login = 1;
		$this->need_db = 1;
		$this->need_filter = 1;
		$this->input_debug_flag = 0;
	
		parent::__construct();
		
		$this->set($result,'users');
		
		if($_GET['logout'] && !$_POST['uname']){
			$logout_status = $this->login_class->log_out($_SESSION['uid']);
		
			switch($logout_status){
				case 'goodbye':
					$this->set('<font color="red">You are now logged out</font>','errors');
					break;		
			}
		}
		
		if(!$_GET['logout'] && $_COOKIE['auto_login'] && !$_SESSION['uid']){
			$bypass = $this->login_class->bypass_login();
			
			switch ($bypass){
				case 'bypassed':
					break;
					
				case 'fraud':
				//	$this->set("<font color='red'>Warning! You are trying to use a expired account session!</font>",'errors');
					break;
			}
		}
		
		if(!$bypass && !$_GET['logout'] && $_POST['uname']){
			$login_status = $this->login_class->validate_user();
			
			switch ($login_status){
				
				case 'short':
					$this->set('<font color="red">Your username you type is less then 2 chars</font>','errors');
					break;
					
				case 'success':
					$this->set("Congrats, You're logged in!",'errors1');
					break;
				
				case 'invalid':
					$this->set('<font color="red">Your username and/or password is invalid</font>','errors');
				
			}
		}
		
		if($_COOKIE['wasp_attack']){
			$this->set('Continue Signing Up!','signup');
			$this->set('<script type="text/javascript">first_run();</script>','second_step');
			$this->set('onclick="show_next_step(this); return false;">&nbsp; Next &gt;&gt; &nbsp;','step_one');
		} else { 
			$this->set('Sign Up!','signup');
			$this->set('onclick="check_all(this,1); return false;">&nbsp; Sign Up! &nbsp;','step_one'); 
		}
		
		if($_SESSION['uid']){
			$this->page_name = "new_homepage";
			$uid = $_SESSION['uid'];
			} else {
			$this->page_name = "new_logout";
			if($_GET['q'] == 'swineflu')
			$this->page_name = "swineflu";
		}

	

		if($uid){
		$uname = $_SESSION['uname'];	
		//This gets all users initial settings such as the groups he's in etc...
		//this is used for message_handle to check what groups he's in and also says
		//which groups he'll be able to filter off of
	
		//SECURITY ... I SHOULD at t2.status = 1 so that only members who are confirmed get updates	
		$get_user_id_query = "SELECT t1.uname,t1.uid,t2.gid,t3.zip FROM login AS t1
					LEFT JOIN group_members AS t2
					ON t1.uid = t2.uid
					LEFT JOIN profile AS t3
					ON t1.uid = t3.uid
					WHERE t1.uname='{$uname}'";
	
		$get_user_id_result = $this->db_class_mysql->db->query($get_user_id_query);

			//This creates the array that holds all the users gids
			while($res = $get_user_id_result->fetch_assoc()){
				$uid = $res['uid'];
				$uname = $res['uname'];
				$zip = $res['zip'];
				$gids[] .= $res['gid'];
			}
				if($gids)
					$gids =implode(',',$gids);
				else
					$gids = 'null';
				$_SESSION['gid'] = $gids;
				$_SESSION['zip'] = $zip;


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
                        $this->set($res['pic_36'],'user_pic');
			$this->set($res['help'],'help');
			}
		//START User Prefences


		//START get active conversations

		$ac_query = <<<EOF
		SELECT t1.mid,t3.uname,t1.uid,t2.uname,t2.chat_text,count(chat.cid) AS resp_count FROM active_convo as t1
		JOIN chat ON t1.mid = chat.cid
		JOIN special_chat AS t2
		ON t1.mid = t2.mid
		JOIN login AS t3
		ON t3.uid = t1.uid
		WHERE t1.uid = {$uid} AND t1.active = 1 GROUP BY chat.cid ORDER BY mid ASC;		
EOF;
		$this->db_class_mysql->set_query($ac_query,'active_convos',"This is a SPECIAL QUERY that is part of a active of queries - This is for active convos: ALL ");
	        $actives_bits_results = $this->db_class_mysql->execute_query('active_convos');

		if($actives_bits_results->num_rows)
		while($res = $actives_bits_results->fetch_assoc() ) {
			$mid = $res['mid'];
			$uid = $res['uid'];
			$uname = $res['uname'];
			$chat_text = $res['chat_text'];
			$resp_count = $res['resp_count'];

			$ac_output[] = array(
			'mid'	=>	$mid,
			'uid'	=>	$uid,
			'uname'	=>	$uname,
			'chat_text' =>	$chat_text,
			'resp_count' =>	$resp_count
			);
		}
		$this->set($ac_output,'active_convos');

		//END get active conversations	
	
		//START get last tap

		$last_tap_query = <<<EOF
		SELECT

		sc.mid as good_id,TAP_ON.count AS viewer_count,sc.special,UNIX_TIMESTAMP(sc.chat_timestamp) AS chat_timestamp,sc.cid,sc.chat_text,
		ln.uname,ln.fname,ln.lname,ln.pic_100,ln.pic_36,ln.uid

		FROM special_chat AS sc JOIN 
		(
			SELECT max(scji.mid) AS mid FROM special_chat AS scji 
			WHERE uid= {$uid} GROUP BY scji.mid
			ORDER BY scji.mid DESC LIMIT 1
		)
		AS scjo ON sc.mid = scjo.mid
		JOIN login AS ln ON sc.uid = ln.uid
		LEFT JOIN TAP_ONLINE AS TAP_ON
		ON sc.mid = TAP_ON.cid
		WHERE sc.uid = {$uid};
			
EOF;
		$data_last_tap = $this->bit_generator($last_tap_query,'last_tap');
		$this->set($data_last_tap,'last_tap');
		//END get last tap

	
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


//START GROUP AGGR
$counting=0;
$uid_list = '';
$mid_list = '';
$old_uid = '';
$gid_query_list = '';
foreach($my_groups_array as $res){
        $gid = $res['gid'];
        $gname = $res['gname'];
        $symbol = $res['symbol'];
        $slashes_gname = addslashes(addslashes(addslashes($res['gname'])));

                $gid_query_list.= $gid.',';

        $group_search_data[$gid] = $symbol;
        //STIP ONE LINE BELOW
        $html_group_list[$gid] = array(
		'gid' => $gid,
		'symbol' => $symbol,
		'gname' => $slashes_gname,
		'img' => $connected_img
	);

}
$gid_query_list = substr($gid_query_list,0,-1);

$counting=0;

	$outside = "2,0";
        $slashes_gname = addslashes(addslashes(addslashes($res['gname'])));
        $get_groups_bits_query = <<<EOF
		SELECT
		scm.mid FROM special_chat_meta AS scm
		WHERE gid IN ( {$gid_query_list} ) AND connected IN ( {$outside} )
		GROUP BY mid ORDER BY mid DESC LIMIT 10;
EOF;

        $this->db_class_mysql->set_query($get_groups_bits_query,'group_query_ALL',"This is a SPECIAL QUERY that is part of a group of queries - This is for group: ALL ");
        $groups_bits_results = $this->db_class_mysql->execute_query('group_query_ALL');

if($groups_bits_results->num_rows > 0){

	$return_list = $this->get_unique_id_list($groups_bits_results);
	$uid_list = $return_list['uid_list'];
	$mid_list = $return_list['mid_list'];

	$groups_query_bits_info = <<<EOF
SELECT t4.mid as good_id,TAP_ON.count AS viewer_count,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
JOIN special_chat as t3
ON t3.uid = t2.uid
LEFT JOIN (
SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$_SESSION['uid']}
) AS t4
ON t4.mid = t3.cid
LEFT JOIN TAP_ONLINE AS TAP_ON
ON t3.mid = TAP_ON.cid
WHERE t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10
EOF;

$data_all_groups_bits = $this->bit_generator($groups_query_bits_info,'groups_aggr');
$this->set($data_all_groups_bits,'groups_bits');
//END GROUP AGGR
}


/////////////////////////////////////////////////////////////////////////////
//START misc tasks - Including, getting max file id, creating channel id, etc
/////////////////////////////////////////////////////////////////////////////
$groups_you_are_in->data_seek(0);

$this->db_class_mysql->set_query('SELECT MAX(mid) AS mid FROM chat','max_mid_query',"Gets max mid for poller");
$max_mid_results = $this->db_class_mysql->execute_query('max_mid_query');
$res = $max_mid_results->fetch_assoc();
$max_mid = $res['mid'];
$this->set($max_mid,'max_mid');

$push_channel_id = $uid;
$this->set($push_channel_id,'pcid');


$this->db_class_mysql->set_query('UPDATE TEMP_ONLINE SET timeout = 0,cid = "'.$push_channel_id.'" WHERE uid = '.$uid.';','TEMP_ONLINE_UPDATE','UPDATES users TEMP_ONLINE status');
$TEMP_ONLINE_results = $this->db_class_mysql->execute_query('TEMP_ONLINE_UPDATE');

if(!$this->db_class_mysql->db->affected_rows){
	$gid_count = 0;
	while($res = $groups_you_are_in->fetch_assoc() ){
		$gid_string .= $res['gid'].',';
		$gid_count++;
	}
	$gid_string = substr($gid_string,0,-1);

	$this->db_class_mysql->set_query('INSERT INTO TEMP_ONLINE(uid,cid,gids) values('.$uid.',"'.$push_channel_id.'","'.$gid_string.'");','TEMP_ONLINE_INSERT','INSERTS users TEMP_ONLINE status');
	$TEMP_ONLINE_results = $this->db_class_mysql->execute_query('TEMP_ONLINE_INSERT');

	//START gid presence updateding
	$query_string = "UPDATE GROUP_ONLINE SET online = online+1 WHERE gid IN($gid_string)";
	$this->db_class_mysql->set_query($query_string,'GROUP_ONLINE_UPDATE','Updates group online presence');
	$GROUP_ONLINE_results = $this->db_class_mysql->execute_query('GROUP_ONLINE_UPDATE');


	if($this->db_class_mysql->db->affected_rows != $gid_count){
		$groups_you_are_in->data_seek(0);
		while($res = $groups_you_are_in->fetch_assoc() ){
			$gid = $res['gid'];
			$insert_string = "INSERT INTO GROUP_ONLINE(gid,online) values($gid,1);";
			$this->db_class_mysql->set_query($insert_string,'GROUP_ONLINE_INSERT','INSERTS users GROUP_ONLINE status');
			$GROUP_ONLINE_results = $this->db_class_mysql->execute_query('GROUP_ONLINE_INSERT');
		}
	}
	//END gid presence updating
}
$groups_you_are_in->data_seek(0);

//START initial user stuff
			if($_COOKIE['profile_edit'])
                        $init_notifications[] .=  <<<EOF
<li><img src="images/icons/error.png" /> <a href="profile_edit">Update your profile !</a></li>
EOF;
                        if($_COOKIE['rel_settings'])
                        $init_notifications[] .=  <<<EOF
                         <li><img src="images/icons/error.png" /> <a href="relevancy_settings">Edit your filters !</a></li>
EOF;
                        if($_COOKIE['groups'])
                        $init_notifications[] .=  <<<EOF
                         <li><img src="images/icons/error.png" /> <a href="groups">Join a connected group!</a></li>
EOF;

			$this->set($init_notifications,'init_notifications');
//END misc tasks - Including, getting max file id, spnning off process, etc
		} else { 

$search = $_GET['q'];
if($search)
	$search_sql =  "AND chat_text LIKE '%{$search}%'";

$logout_feed = <<<EOF
	SELECT t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
	JOIN special_chat as t3
	ON t3.uid = t2.uid AND t2.uid NOT IN ( 63,75,175 )
	{$search_sql}
	ORDER BY t3.cid DESC LIMIT 10
EOF;
$data_all_logout_bits = $this->bit_generator($logout_feed,'logout_aggr');
$this->set($data_all_logout_bits,'logout_bits');



}
	$trending_groups_query = <<<EOF
	SELECT ugm.gid,t2.gname,t2.pic_36,count(t1.uid) AS count FROM  
	( SELECT DISTINCT gid FROM group_members ORDER BY gid )
	AS ugm 
	JOIN group_members AS t1 ON t1.gid=ugm.gid
	JOIN groups AS t2 ON t2.gid = ugm.gid
	GROUP BY ugm.gid ORDER BY gid DESC, count DESC LIMIT 15;
EOF;
	$this->db_class_mysql->set_query($trending_groups_query,'trending_groups',"This query tells you the groups that are trending");
	$trending_groups_results = $this->db_class_mysql->execute_query('trending_groups');
	
	while($res = $trending_groups_results->fetch_assoc()){
		$gid = $res['gid'];
		$gname = $res['gname'];
		$pic_36 = $res['pic_36'];
		$count = $res['count'];
		
		$trending_group_data[] = array(
			'gid' => $gid,
			'gname' => $gname,
			'pic_36' => $pic_36,
			'count' => $count
		);
	
	}
	$this->set($trending_group_data,'trending_groups');

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
