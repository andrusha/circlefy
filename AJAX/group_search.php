<?php
session_start();
/* CALLS:
	search_group.js
*/
require('../config.php');

/* TO DO: 

Add focus to results
Add Location to results
*/

$gname = $_POST['gname'];
$focus = $_POST['focus'];
$location = $_POST['location'];
$offset = $_POST['offset'];

if(isset($_POST['gname'])){
   	$search_function = new search_functions();
        $results = $search_function->search_group($gname,$focus,$offset);
        echo $results;
}


class search_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function search_group($gname,$focus,$offset){
		if($focus)
		$focuses = explode(' ',$focus);
		if($focuses[0] != ''){
			$focus_list = "( AND ";
			foreach($focuses as $focus)
				$focus_list .= " 'gs.focus LIKE %$focus%' OR";
			$focus_list .= ") ";
			$focus_list = substr($focus_list,0,-3);
		}
		
		if(!$offset){
			$offset = 0;
		}
		$limit = 10;
		

                $uid = $_SESSION["uid"];

                $uid = $this->mysqli->real_escape_string($uid);
		
		if(strlen($gname) > 4)
			$name_gname = '%'.$gname;
		else 	$name_gname = $gname;

                $gname = $this->mysqli->real_escape_string($gname);
                $name_gname = $this->mysqli->real_escape_string($name_gname);

		//This query gets the group information based off of params supplied
		$group_query =  <<<EOF
SELECT SQL_CALC_FOUND_ROWS

ugm.gid,
gm.admin,count(gm.uid) AS size,
t2.symbol,t2.gname,t2.connected,t2.descr,t2.focus,t2.pic_100

FROM (
SELECT DISTINCT gs.gid FROM groups AS gs
WHERE gs.gname LIKE '{$name_gname}%' OR gs.symbol LIKE '{$gname}%' {$focus_list}
GROUP BY gs.gid
) AS ugm

LEFT JOIN group_members AS gm ON gm.gid= ugm.gid
LEFT JOIN groups AS t2 ON t2.gid = ugm.gid
GROUP BY ugm.gid ORDER BY t2.connected LIMIT {$limit} OFFSET {$offset};
EOF;
	//Add this to remove companies from showing up WHERE t2.connected !=2

                $group_results = $this->mysqli->query($group_query);
		
		if($group_results->num_rows)	
		 while($res = $group_results->fetch_assoc()){
			$gid = $res['gid'];
                        $sc =  strpos('x,'.$_SESSION['gid'].',',','.$gid.',');
			if($sc)
				$in = 1;
			else
				$in = 0;
			
			$pic = $res['pic_100'];
			$symbol = $res['symbol'];
			$descr = $res['descr'];
			$focus = $res['focus'];
			$gname = $res['gname'];
			$type = $res['connected'];
			$size = $res['size'];
			if($type)
				$official = "*";
			else    $official = "";

			$groups[$gid] = array(
				'in' => $in,
				'gid' => $gid,
				'gname' => $gname,
				'symbol' => $symbol,
				'pic' => $pic,
				'type' => $type,
				'size' => $size,
				'focus' => $focus,
                                'descr' => $descr,
				'official' => $official,
				'last_chat' => null,
				'last_chat'=> null,
				'count'=> 0
			);
			$gid_list .= $gid.',';
		}
			$gid_list = substr($gid_list,0,-1);

                        $group_message_count = <<<EOF
			SELECT COUNT(scm.gid) AS count,scm.gid,sc.chat_text AS last_chat,"Taso" AS last_uname FROM
			( 
				SELECT MAX(mid) as mid,gid FROM special_chat_meta AS iscm WHERE gid IN ( {$gid_list} )
				GROUP BY gid
				ORDER BY mid DESC
			)
			AS oscm	
			JOIN special_chat AS sc ON oscm.mid = sc.mid
			JOIN special_chat_meta AS scm ON oscm.gid = scm.gid
			GROUP BY scm.gid
EOF;

                        $message_count_results = $this->mysqli->query($group_message_count);
			if($message_count_results->num_rows)
                        while($res = $message_count_results->fetch_assoc() ) {
                                $count = $res['count'];
                                $gid = $res['gid'];
				$last_chat = $res['last_chat'];
				$last_uname = $res['last_uname'];

                                $groups[$gid]['count'] = $count;
				$groups[$gid]['last_chat'] = $last_chat;
				$groups[$gid]['last_uname'] = $last_uname;
                        }	

		//If groups were found send them back, else send back no results
		$count_results = $this->mysqli->query('SELECT found_rows() as count');
                $row_count = $count_results->fetch_assoc();
                $row_count = $row_count['count'];
                //If groups were found send them back, else send back no results
                if($groups)
                        return json_encode(array('group_results' => $groups, 'row_count' => $row_count));
		else
			return json_encode(array('group_results' => NULL));
	}

}
