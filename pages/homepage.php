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

		$pics = <<<EOF
		   select favicon as pic_36,symbol from groups where pic_36 != '36wh_default_group.gif' and connected = 3 order by rand() limit 42;
EOF;
		$pics_data = $this->db_class_mysql->db->query($pics);

		while($res = $pics_data->fetch_assoc()){
			$pic_36 = $res['pic_36'];
			$symbol = $res['symbol'];

			$pics_array[] = array(
				'pic_36' => $pic_36,
				'symbol' => $symbol
			);
		}
		$this->set($pics_array,'pics_array');
	
	

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
			
				//This is the admin list	
				if(substr($uid.',', ',63,75,1414,'))
				$_SESSION['admin'] = 1;
				else
				$_SESSION['admin'] = 0;


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
                        $this->set($res['pic_100'],'user_pic_100');
			$this->set($res['help'],'help');
			}
		//START User Prefences


		//START get active conversations

		$ac_query = <<<EOF
		SELECT t1.mid,t3.uname,t2.uid,t2.uname,t2.chat_text,t3.pic_36 AS small_pic, count(chat.cid) AS resp_count FROM active_convo as t1
		JOIN chat ON t1.mid = chat.cid
		JOIN special_chat AS t2
		ON t1.mid = t2.mid
		JOIN login AS t3
		ON t3.uid = t2.uid
		WHERE t1.uid = {$uid} AND t1.active = 1 GROUP BY chat.cid ORDER BY mid ASC;		
EOF;

		$this->db_class_mysql->set_query($ac_query,'active_convos',"This is a SPECIAL QUERY that is part of a active of queries - This is for active convos: ALL ");
	        $actives_bits_results = $this->db_class_mysql->execute_query('active_convos');

		if($actives_bits_results->num_rows)
		while($res = $actives_bits_results->fetch_assoc() ) {
			$mid = $res['mid'];
			$ac_uid = $res['uid'];
			$small_pic = $res['small_pic'];
			$uname = $res['uname'];
			$chat_text = $res['chat_text'];
			$resp_count = $res['resp_count'];

			$ac_output[] = array(
			'mid'	=>	$mid,
			'uid'	=>	$ac_uid,
			'uname'	=>	$uname,
			'small_pic' => $small_pic,
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
			SELECT COUNT(scm.gid) as message_count,GROUP_ON.count,t2.descr AS topic,t2.symbol,t2.connected,t1.tapd,t1.inherit,t2.pic_36,t2.favicon,t2.gname,t1.gid,t1.admin
			FROM group_members AS t1
			JOIN groups AS t2 ON t2.gid=t1.gid
			LEFT JOIN GROUP_ONLINE AS GROUP_ON ON GROUP_ON.gid=t1.gid
			LEFT JOIN special_chat_meta AS scm ON scm.gid=t1.gid
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
			$admin = $res['admin'];
			$topic = $res['topic'];
			$tapd = $res['tapd'];
			$online_count = $res['count'];
			$favicon = $res['favicon'];
			$message_count = $res['message_count'];

			//Process
/* server side linkify homepage
			$string = $topic;
                        $topic = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]","<a href=\"\\0\">\\0</a>", $string);
*/
			$real_symbol[0] = $symbol;
			$symbol = explode('.',$symbol);
			if($symbol[1] != 'com' && $symbol[1] != 'edu') $add = ' '.$symbol[1];
			$topic = stripslashes($topic);
			$display_symbol = ucwords($symbol[0].$add);
			$add = null;
			
			
			$my_groups_array[$gid] = array(
				'gid' => $gid,
				'gname' => $gname,
				'topic' => $topic,
				'pic_36' => $pic_36,
				'symbol' => $real_symbol,
				'display_symbol' => $gname,
				'favicon' => $favicon,
				'type' => $connected,
				'online_count' => $online_count,
				'tapd' => $tapd,
				'admin' => $admin,
				'message_count' => $message_count,
				'total_count' => null,
			);
			$gid_list .= $gid.',';
		}
		$gid_list = substr($gid_list,0,-1);

		$group_member_count_query = <<<EOF
		SELECT COUNT(uid) AS member_count,gid,uid FROM group_members AS gm WHERE gid IN ({$gid_list}) GROUP BY gid;
EOF;

                $this->db_class_mysql->set_query($group_member_count_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                $group_count_res = $this->db_class_mysql->execute_query('get_users_groups');

		if($group_count_res->num_rows)
		while($res = $group_count_res->fetch_assoc()){
			$count = $res['member_count'];
			$gid = $res['gid'];
			$my_groups_array[$gid]['total_count'] = $count;
		}
                $this->set($my_groups_array,'your_groups');	

		$people_query = <<<EOF
		SELECT f.fuid,l.uname,l.pic_36 AS small_pic,l.fname,l.lname FROM friends AS f 
		JOIN login AS l
		ON f.fuid = l.uid
		WHERE f.uid = {$uid};
EOF;
                $this->db_class_mysql->set_query($people_query,'people_query',"This gets the initial lists of users people so he can search within his friends");
                $people_res = $this->db_class_mysql->execute_query('people_query');
		
		if($people_res->num_rows)
		while($res = $people_res->fetch_assoc()){
			$friend_uname= $res['uname'];
			$friend_fname= $res['fname'];
			$friend_lname= $res['lname'];
			$friend_uid  = $res['fuid'];
			$friend_pic  = $res['small_pic'];

			$friends_array[] = array(
				'friend_uid' => $friend_uid,
				'friend_pic' => $friend_pic,
				'friend_uname'=>$friend_uname,
				'friend_fname'=>$friend_fname,
				'friend_lname'=>$friend_lname
			);

		}
		$this->set($friends_array,'your_friends');

//START GROUP AGGR
$counting=0;
$uid_list = '';
$mid_list = '';
$old_uid = '';
$gid_query_list = '';
if($group_count_res->num_rows)
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

	$outside = "1,2";
        $slashes_gname = addslashes(addslashes(addslashes($res['gname'])));
        $get_groups_bits_query = <<<EOF
		SELECT
		scm.gid,scm.connected,scm.mid FROM special_chat_meta AS scm
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
SELECT 
	good.mid as good_id,
	TEMP_ON.online AS user_online,
	TAP_ON.count AS viewer_count,
	sc.special,UNIX_TIMESTAMP(sc.chat_timestamp) AS chat_timestamp,sc.cid,sc.chat_text,
	l.uname,l.fname,l.lname,l.pic_100,l.pic_36,l.uid,
	g.favicon,g.gname,g.symbol,
	scm.gid,scm.connected
FROM login AS l
JOIN special_chat as sc
ON sc.uid = l.uid
LEFT JOIN (
SELECT good_inner.mid,good_inner.fuid FROM good AS good_inner WHERE good_inner.fuid = {$_SESSION['uid']}
) AS good
ON good.mid = sc.cid
LEFT JOIN TAP_ONLINE AS TAP_ON
ON sc.mid = TAP_ON.cid
LEFT JOIN TEMP_ONLINE AS TEMP_ON
ON sc.uid = TEMP_ON.uid

LEFT JOIN special_chat_meta AS scm
ON scm.mid = sc.cid

JOIN groups AS g
ON scm.gid = g.gid

WHERE sc.mid IN ( {$mid_list} ) AND ( scm.connected = 1 OR scm.connected = 2 )

ORDER BY sc.cid DESC LIMIT 10
EOF;

//echo $groups_query_bits_info;

$data_all_groups_bits = $this->bit_generator($groups_query_bits_info,'groups_aggr');
$this->set($data_all_groups_bits,'groups_bits');
//END GROUP AGGR
}


/////////////////////////////////////////////////////////////////////////////
//START misc tasks - Including, getting max file id, creating channel id, etc
/////////////////////////////////////////////////////////////////////////////
$groups_you_are_in->data_seek(0);

//START set the session uid for Orbited
$this->set($_SESSION['uid'],'pcid');
//END set the session uid for Orbited

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
	SELECT ugm.gid,t2.symbol,t2.gname,t2.pic_36,count(t1.uid) AS count FROM  
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
		$symbol = $res['symbol'];
		$pic_36 = $res['pic_36'];
		$count = $res['count'];
		
		$trending_group_data[] = array(
			'gid' => $gid,
			'gname' => $gname,
			'symbol' => $symbol,
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
				$user_online = $res['user_online'];
                                $uid = $res['uid'];
				$gid = $res['gid'];
				$favicon = $res['favicon'];
				$gname = $res['gname'];
				$symbol = $res['symbol'];
				$connected = $res['connected'];


                                //Process
				$chat_timestamp_raw = $chat_timestamp;
                                $chat_timestamp = $this->time_since($chat_timestamp);
                                $chat_timestamp = ($chat_timestamp == "0 minutes") ? "Seconds ago" : $chat_timestamp." ago";
                                $chat_text = stripslashes($chat_text);
				$chat_text = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]","<a href=\"\\0\">\\0</a>", $chat_text);
				if($viewer_count)
					$viewer_count = $viewer_count;

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
                                'gid'=>           $gid,
                                'favicon'=>           $favicon,
				'gname' =>		$gname,
				'symbol' =>		$symbol,
                                'connected'=>           $connected,
                                'viewer_count'=>     $viewer_count,
                                'user_online'=>     $user_online,
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
