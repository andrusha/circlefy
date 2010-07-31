<?php
/* CALLS:
	homepage.phtml
*/
$usage = <<<EOF
PARAMS

type - 
	Types:
	1 = IND Channel
	11 = AGGR Channels
	2 = IND People
	22 = AGGR Peoples
	3 = IND Filter
	33 = AGGR Filters
search - 
	optional if you provide a search term for the feed
outside - 
	Can be : 0,1,2 
flag  -
	flag to show personal responses you've tapped in a personal feed ( not used )
more -
	the more offset if you want a different set of the feed rather then the latest ( i.e. pagiation )
id -
	the id of the channel you want to call
EOF;
	
session_start();
require('../config.php');
require('../api.php');

if($cb_enable)
	$o_filter = $_GET['o_filter'];
else
	$o_filter = $_POST['o_filter'];

$o_filter = stripslashes($o_filter);
$o_filter_list = json_decode($o_filter);

$group_o_filter = array();

if($o_filter_list)
foreach($o_filter_list as $v){
        $o_filter_string = explode(":",$v);
	//id corresponding to group/person
	$id = $o_filter_string[3];
        //symbol type
        $type = $o_filter_string[2];
        //actual symbol
        $symbol = $o_filter_string[1];
        //meta data about symbol
        $name = $o_filter_string[0];

        if($type == 0 || $type == 1 || $type == 2)
                $group_o_filter[] = $id;
}
$o_filter = $group_o_filter;

/*
Types:
1 = IND Group 
11 = AGGR Groups
2 = IND People
22 = AGGR Peoples
3 = IND Filter
33 = AGGR Filters
*/
if($cb_enable){
	$type = $_GET['type'];
	$search = $_GET['search'];
	$outside = $_GET['outside'];
	$flag = $_GET['flag'];
	$more = $_GET['more'];
	$id = $_GET['id'];
} else {
	$type = $_POST['type'];
	$search = $_POST['search'];
	$outside = $_POST['outside'];
	$flag = $_POST['flag'];
	$more = $_POST['more'];
	$id = $_POST['id'];
}

if(isset($type)){
   	$filter_function = new filter_functions();
        $res = $filter_function->filter($type,$search,$outside,$o_filter,$id,$flag,$more);
	api_json_choose($res,$cb_enable);
} else {
	api_usage($usage);
}





class filter_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;
		private $more = False;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function filter($type,$search,$outside,$o_filter,$id,$flag,$more){
		if($more)
			$this->more = $more;

		if(!$outside)
			$outside="1,2";

		$type = $this->mysqli->real_escape_string($type);
		$id = $this->mysqli->real_escape_string($id);
		$search = $this->mysqli->real_escape_string($search);
		$outside = $this->mysqli->real_escape_string($outside);

		if($type == 50)
			$mysql_obj = $this->direct_filter($outside,$search,$o_filter);
		if($type == 11)
			$mysql_obj = $this->aggr_group_filter($outside,$search,$o_filter);
		if($type == 100)
			$mysql_obj = $this->public_filter($search);
		if($type == 99)
			$mysql_obj = $this->personal_filter($search,$flag,$id);
		if($type == 1)
			$mysql_obj = $this->ind_group_filter($id,$outside,$search,$o_filter);
		if($type == 2)
			echo "People Function";
		if($type == 3)
			echo "Filter Function";


		if($mysql_obj->num_rows > 0)
			$data = $this->create_filter($mysql_obj);	
		else
			return array('results' => False,'data' => False);
	
		return array('results' => True,'data'=> $data );
	}
	
	private function direct_filter($outside,$search,$o_filter){
		$uid = $_SESSION['uid'];
		$outside = NULL;
                if($search)
                        $search_sql =  "AND chat_text LIKE '%{$search}%'";

                        $get_groups_bits_query = <<<EOF
                        SELECT
                        scm.mid FROM special_chat_meta AS scm
                        JOIN special_chat AS scj
                        ON scj.mid = scm.mid
                        WHERE  scm.uid IN ( {$uid} ) AND connected is NULL
                        {$search_sql}
                        GROUP BY mid ORDER BY mid DESC LIMIT 10
EOF;

                $mysql_obj = $this->mysqli->query($get_groups_bits_query);
                return $mysql_obj;
	}

	private function personal_filter($search,$responses,$uid=null){
		if(!$uid)
			$uid = $_SESSION['uid'];

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

		$mysql_obj = $this->mysqli->query($personal_bits_query);
		return $mysql_obj;
	}

	private function public_filter($search){
		if($search)
			$search_sql =  "AND chat_text LIKE '%{$search}%'";

		$public_bits_query = <<<EOF
		SELECT mid,cid FROM special_chat
		WHERE uid NOT IN ( 63,75,175 )
		{$search_sql}
		ORDER BY cid DESC
		LIMIT 20
EOF;

		$mysql_obj = $this->mysqli->query($public_bits_query);
		return $mysql_obj;
	}

	private function aggr_group_filter($outside,$search,$o_filter){
		$gids = $_SESSION['gid'];
		$gids = explode(',',$gids);
		if($gids)
		foreach($gids as $gid)
			$gid_query_list .= $gid.',';
		$gid_query_list = substr($gid_query_list,0,-1);

		if($search)
			$search_sql =  "AND chat_text LIKE '%{$search}%'";

		if($o_filter){
                        foreach($o_filter as $o_gid)
                                $o_filter_list = $o_gid.',';

                        $o_filter_list = substr($o_filter_list,0,-1);

                        $get_groups_bits_query = <<<EOF
                        SELECT
                        scm.mid,scm.gid,scm.connected
                        FROM special_chat_meta AS scm, special_chat_meta AS scm2
                        JOIN special_chat AS scj
                        ON scj.mid = scm2.mid
                        WHERE scm.gid IN ({$gid_query_list}) AND scm.connected IN ({$outside}) AND scm2.mid = scm.mid
                        AND scm2.gid IN ({$o_filter_list})
			{$search_sql}
EOF;
		} else { 
	
			$get_groups_bits_query = <<<EOF
			SELECT
			scm.mid FROM special_chat_meta AS scm
			JOIN special_chat AS scj
			ON scj.mid = scm.mid 
			WHERE  gid IN ( {$gid_query_list} ) AND connected IN ({$outside})
			{$search_sql}
			GROUP BY mid ORDER BY mid DESC LIMIT 10
EOF;
		}
        	$mysql_obj = $this->mysqli->query($get_groups_bits_query);
		return $mysql_obj;
	}

	private function ind_group_filter($gid,$outside,$search,$o_filter) {
		if($this->more)
			$more = $this->more.',';
		if($search)
			$search_sql =  "AND chat_text LIKE '%{$search}%'";

		if($o_filter){
			foreach($o_filter as $o_gid)
				$o_filter_list = $o_gid.',';

			$o_filter_list = substr($o_filter_list,0,-1);

			$get_group_bits_query = <<<EOF
			SELECT
			scj.chat_text,scm.mid,scm.gid,scm.connected
			FROM special_chat_meta AS scm, special_chat_meta AS scm2

			JOIN special_chat AS scj
			ON scj.mid = scm2.mid 

			WHERE scm.gid = {$gid} AND scm.connected IN ({$outside}) AND scm2.mid = scm.mid
			AND scm2.gid IN ({$o_filter_list})
			{$search_sql}
			LIMIT {$more}10
EOF;

		} else { 
		
			$get_group_bits_query = <<<EOF
			SELECT
			scj.chat_text,scm.mid FROM special_chat_meta AS scm
			JOIN special_chat AS scj
			ON scj.mid = scm.mid 
			WHERE scm.gid = {$gid} AND scm.connected IN ({$outside}) 
		 	{$search_sql}	
			GROUP BY mid ORDER BY mid DESC LIMIT {$more}10
EOF;
		}
		
		$mysql_obj = $this->mysqli->query($get_group_bits_query);
		return $mysql_obj;
	}


	function create_filter($mysql_obj){
		$uid = $_SESSION['uid'];
		if(!$uid)
			$uid=0;

		$limit = '10';

		$return_list = $this->get_unique_id_list($mysql_obj);
		$mid_list = $return_list['mid_list'];

		$group_query_bits_info = <<<EOF
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
			SELECT good_inner.mid,good_inner.fuid FROM good AS good_inner WHERE good_inner.fuid = {$uid}
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

		$m_results = $this->mysqli->query($group_query_bits_info);
		/* Going to have to think about how to handle responses */
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
                                'gname' =>              $gname,
                                'symbol' =>              $symbol,
                                'connected'=>           $connected,
                                'viewer_count'=>     $viewer_count,
                                'user_online'=>     $user_online,
                                'last_resp'=>     null,
                                'resp_uname'=>    null,
                                'count'=>         0

				);
			}

		// START + Getting response data

			$response_count = <<<EOF
			SELECT COUNT(oc.cid) AS count,c.cid AS cid,oc.chat_text,c.uname FROM
			( SELECT  MAX(mid) AS mmid,chat_text,cid FROM chat WHERE cid IN ( {$mid_list}  ) 
			GROUP BY mid ORDER BY mid DESC)
			AS oc
			JOIN chat AS c ON oc.cid = c.cid AND oc.mmid = c.mid
			GROUP BY oc.cid;
EOF;
			$resp_count_results = $this->mysqli->query($response_count);
			if($resp_count_results){
			while($res = $resp_count_results->fetch_assoc()){
				$count = $res['count'];
                                $last_resp = $res['chat_text'];
                                $cid = $res['cid'];
				$resp_uname = $res['uname'];
                                $messages[$cid]['count'] = $count;
                                $messages[$cid]['last_resp'] = $last_resp;
                                $messages[$cid]['resp_uname'] = $resp_uname;
			}
			foreach($messages as $v)
				$pmessages[] = $v;
			} else {
				$pmessages = $messages;
			}
		// END + Getting response data
		return $pmessages;
}
	
	private function get_unique_id_list($mysql_object){
		while($res = $mysql_object->fetch_assoc() ){
		$mid = $res['mid'];
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
			'mid_list' => $mid_list
		);
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

		// dd second item if it's greater than 0
		if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
		    $print .= ($count2 == 1) ? ', 1 '.$name2 : ", $count2 {$name2}s";
		}
	    }
	    return $print;
	}


}
