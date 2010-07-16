<?php

class rss extends Base{

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
		$this->page_name = "rss";
	
		parent::__construct();
		
		
		$symbol = $_GET['symbol'];


//START get gid
$get_gid_query = <<<EOF
	SELECT GO.count,g.descr,g.gid,g.favicon,g.pic_100,g.gname,g.symbol,g.topbg,g.connected FROM groups AS g
	JOIN GROUP_ONLINE AS GO
	ON GO.gid = g.gid
	WHERE g.symbol = '{$symbol}' LIMIT 1;
EOF;
$this->db_class_mysql->set_query($get_gid_query,'get_gid',"This query gets a specific gid for the public group");
$gid_result = $this->db_class_mysql->execute_query('get_gid');

if(!$gid_result->num_rows)
	header( 'Location: http://tap.info?error=no_public_group' );

$res = $gid_result->fetch_assoc();
$gid = $res['gid'];
$gname = $res['gname'];
$type = $res['connected'];
$symbol = $res['symbol'];
$online_count = $res['count'];
$descr = $res['descr'];
$pic_100 = $res['pic_100'];
$favicon = $res['favicon'];
$topbg = $res['topbg'];

//Part of group?
$sc =  strpos('x,'.$_SESSION['gid'].',',','.$gid.',');
$joined = $sc ? True : False;
$descr = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]","<a href=\"\\0\" target=\"_blank\">\\0</a>", $descr);

$this->set($joined,'joined');
$this->set($pic_100,'group_pic_med');
$this->set($favicon,'favicon');
$this->set($gname,'gname');
$this->set($symbol,'symbol');
$this->set($type,'type');
$this->set($topbg,'topbg');
$this->set($gid,'gid');
$this->set($descr,'descr');
$this->set($online_count,'online_count');
if(!$gid)
	return False;
//END get gid

$get_member_count = <<<EOF
	SELECT COUNT(gm.uid) AS total_count FROM group_members AS gm WHERE gm.gid = {$gid}
EOF;


$this->db_class_mysql->set_query($get_member_count,'get_member_count',"This query gets a specific gid for the public group");
$count_results = $this->db_class_mysql->execute_query('get_member_count');
$res = $count_results->fetch_assoc();
$total_count = $res['total_count'];
$this->set($total_count,'total_count');

//START get admin list
$admin_list_query = <<<EOF
SELECT gm.admin,l.uname,l.pic_36,gm.uid FROM group_members AS gm 
JOIN login AS l
ON l.uid = gm.uid
WHERE gm.gid = {$gid} AND admin <> 0;
EOF;

$this->db_class_mysql->set_query($admin_list_query,'admin_list',"This gets the list of admins for a specific group");
$admin_list_results = $this->db_class_mysql->execute_query('admin_list');

$user_admin = false;
while($res = $admin_list_results->fetch_assoc() ){
	$uname = $res['uname'];
	$admin_uid = $res['uid'];
	$type = $res['admin'];
	$pic_36 = $res['pic_36'];

	switch($type){
		case 1:$type = 'Founder';break;
		case 2:$type = 'Admin';break;
	}
	
	$admin_data[] = array(
		'uname'	=> $uname,
		'small_pic' => $pic_36,
		'type' => $type
	);
	if($admin_uid == $_SESSION['uid'])
		$user_admin = true;
}
$this->set($admin_data,'admins');
$this->set($user_admin,'enable_admin');


//START get groups initial taps
$outside = "1,2";
$get_users_taps_query = $this->ind_group_filter($gid,$outside,null,null);
$this->db_class_mysql->set_query($get_users_taps_query,'public_users_taps',"This query gets a specific users tap's");
$users_bits_results = $this->db_class_mysql->execute_query('public_users_taps');


if($_SESSION['uid'])
	$logged_in_id = $_SESSION['uid'];
else
	$logged_in_id = 0;


if($users_bits_results->num_rows > 0){
	$return_list = $this->get_unique_id_list($users_bits_results);
	$uid_list = $return_list['uid_list'];
	$mid_list = $return_list['mid_list'];


	$users_query_bits_info = <<<EOF
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
SELECT good_inner.mid,good_inner.fuid FROM good AS good_inner WHERE good_inner.fuid = {$logged_in_id}
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


	$data_all_users_bits = $this->bit_generator($users_query_bits_info,'users_aggr');
	$this->set($data_all_users_bits,'user_bits');
	//END get groups initial taps
				}
//START member count
$count_group_member_query = "SELECT COUNT(uid) AS member_count FROM group_members WHERE gid = {$gid}";
$this->db_class_mysql->set_query($count_group_member_query,'member_count',"This query gets a group member count");
$count_member_result = $this->db_class_mysql->execute_query('member_count');
$res = $count_member_result->fetch_assoc();
$member_count = $res['member_count'];
$this->set($member_count,'member_count');
//END member count

//START most popular members
$popular_members_query = <<<EOF
SELECT l.uname,l.pic_36,COUNT(sc.mid) as count FROM special_chat AS sc
JOIN special_chat_meta AS scm ON sc.mid = scm.mid
JOIN login AS l ON l.uid = sc.uid
WHERE scm.gid = {$gid}
GROUP BY sc.uid ORDER BY count DESC LIMIT 9;
EOF;
$this->db_class_mysql->set_query($popular_members_query,'popular_members',"This query gets a groups active members");
$popular_members_results = $this->db_class_mysql->execute_query('popular_members');
while($res = $popular_members_results->fetch_assoc()){
	$member = $res['uname'];
	$pic_36 = $res['pic_36'];
	$count = $res['count'];

	$popular_members_data[] = array(
	'member' => $member,
	'small_pic' => $pic_36,
	'count' => $count
	);
}
	$this->set($popular_members_data,'popular_members');
//END most popular members

//START get popular taps
$popular_taps_query = <<<EOF
SELECT sc.chat_text,COUNT(c.cid) AS count,scm.mid FROM chat AS c
JOIN special_chat_meta AS scm ON scm.mid = c.cid
JOIN special_chat AS sc ON sc.mid = scm.mid
WHERE scm.gid = {$gid}
GROUP BY c.cid ORDER BY count DESC LIMIT 5;
EOF;

$this->db_class_mysql->set_query($popular_taps_query,'popular_taps',"This query gets a groups popular taps");
$popular_taps_results = $this->db_class_mysql->execute_query('popular_taps');
if($popular_taps_results->num_rows)
while($res = $popular_taps_results->fetch_assoc()){
        $tap = $res['chat_text'];
	$cid = $res['mid'];
        $count = $res['count'];
	
	$tap = stripslashes($tap);

        $popular_taps_data[] = array(
        'tap' => $tap,
        'cid' => $cid,
        'count' => $count
        );
}
        $this->set($popular_taps_data,'popular_taps');
//END get popular taps

//START set the session uid for Orbited
$this->set($_SESSION['uid'],'pcid');
//END set the session uid for Orbited



}

private function ind_group_filter($gid,$outside,$search,$o_filter) {
                if($search)
                        $search_sql =  "AND chat_text LIKE '%{$search}%'";

                if($o_filter){
                        foreach($o_filter as $o_gid)
                                $o_filter_list = $o_gid.',';

                        $o_filter_list = substr($o_filter_list,0,-1);
                        //$o_filter_sql = " AND scm.gid IN({$o_filter_list})";

                        $get_group_bits_query = <<<EOF
                        SELECT
                        scj.chat_text,scm.mid,scm.gid,scm.connected
                        FROM special_chat_meta AS scm, special_chat_meta AS scm2

                        JOIN special_chat AS scj
                        ON scj.mid = scm2.mid

                        WHERE scm.gid = {$gid} AND scm.connected IN ({$outside}) AND scm2.mid = scm.mid
                        AND scm2.gid IN ({$o_filter_list})
                        {$search_sql}
EOF;

                } else {

                        $get_group_bits_query = <<<EOF
                        SELECT
                        scj.chat_text,scm.mid FROM special_chat_meta AS scm
                        JOIN special_chat AS scj
                        ON scj.mid = scm.mid
                        WHERE scm.gid = {$gid} AND scm.connected IN ({$outside})
                        {$search_sql}
                        GROUP BY mid ORDER BY mid DESC LIMIT 10
EOF;
                }

                return $get_group_bits_query;
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

	                        $chat_text = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]","<a href=\"\\0\" target=\"_blank\">\\0</a>", $chat_text);
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
                                'gid'=>           $gid,
                                'favicon'=>           $favicon,
                                'gname' =>              $gname,
                                'symbol' =>             $symbol,
                                'connected'=>           $connected,
                                'viewer_count'=>     $viewer_count,
                                'user_online'=>     $user_online,
                                'last_resp'=>     null,
                                'resp_uname'=>    null,
                                'count'=>         0

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