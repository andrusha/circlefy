<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');
$o_filter = stripslashes($_POST['o_filter']);
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
$type = $_POST['type'];
$search = $_POST['search'];
$outside = $_POST['outside'];
$id = $_POST['id'];

if(isset($type)){
   	$filter_function = new filter_functions();
        $json = $filter_function->filter($type,$search,$outside,$o_filter,$id);
        echo $json;
}


class filter_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function filter($type,$search,$outside,$o_filter,$id){
		if(!$outside)
			$outside=2;

                $uid = $_SESSION['uid'];
                $uname = $_SESSION['uname'];

		$type = $this->mysqli->real_escape_string($type);
		$id = $this->mysqli->real_escape_string($id);
		$search = $this->mysqli->real_escape_string($search);
		$outside = $this->mysqli->real_escape_string($outside);

		if($type == 11)
			$mysql_obj = $this->aggr_group_filter($outside,$search,$o_filter);
		if($type == 1)
			$mysql_obj = $this->ind_group_filter($id,$outside,$search,$o_filter);
		if($type == 2)
			echo "People Function";
		if($type == 3)
			echo "Filter Function";

		if($mysql_obj->num_rows > 0)
			$data = $this->create_filter($mysql_obj);	
		else
			return json_encode(array('results' => False,'data' => False));
	
		return json_encode(array('results' => True,'data'=> $data ));
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
			scj.chat_text,scm.mid FROM special_chat_meta AS scm
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
		
		$mysql_obj = $this->mysqli->query($get_group_bits_query);
		return $mysql_obj;
	}


	function create_filter($mysql_obj){
		$uid = $_SESSION['uid'];

		$return_list = $this->get_unique_id_list($mysql_obj);
		$mid_list = $return_list['mid_list'];

		$group_query_bits_info = <<<EOF
		SELECT t4.mid,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
		JOIN special_chat as t3
		ON t3.uid = t2.uid
		LEFT JOIN (
		SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$uid}
		) AS t4
		ON t4.mid = t3.cid
		WHERE t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10
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
				$uid = $res['uid'];

				//Process
				$chat_timestamp_raw = $chat_timestamp;
				$chat_timestamp = $this->time_since($chat_timestamp);
				$chat_timestamp = ($chat_timestamp == "0 minutes") ? "Seconds ago" : $chat_timestamp." ago";
				$chat_text = stripslashes($chat_text);
			
				//Additional
				$rand = rand(1,999);

	
				//Store
				$messages[$cid] = array(
				'mid' => 	  $mid,
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
				'last_resp'=>	  null,
				'resp_uname'=>	  null,
				'count'=>	  0
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

		// add second item if it's greater than 0
		if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
		    $print .= ($count2 == 1) ? ', 1 '.$name2 : ", $count2 {$name2}s";
		}
	    }
	    return $print;
	}


}
