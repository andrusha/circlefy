<?php

class people extends Base{

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
		$this->page_name = "people";
		$this->need_login = 1;
		$this->need_db = 1;
	
		parent::__construct();
		$uid = $_SESSION['uid'];

		$this->db_class_mysql->set_query('SELECT t2.gname,t1.gid FROM group_members AS t1 JOIN groups AS t2 ON t2.gid=t1.gid WHERE t1.uid='.$uid,
                        'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                        $groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');

		$this->set($groups_you_are_in,'your_groups');

	
		$query[0] = <<<EOF
		SELECT t2.pic_100,t2.uid,t2.uname,t2.fname,t2.lname,t4_sub.chat_text AS last_chat FROM friends AS t1 
		LEFT JOIN 
		(
		 SELECT uid,mid,chat_text FROM special_chat AS t4 ORDER BY mid DESC
		) AS t4_sub ON t4_sub.uid = t1.fuid
		JOIN login AS t2 ON t1.fuid = t2.uid 
		WHERE t1.uid = {$uid} GROUP BY t1.fuid;
EOF;


                $query[1] = <<<EOF
		SELECT t2.pic_100,t2.uid,t2.uname,t2.fname,t2.lname,t4_sub.chat_text AS last_chat FROM friends AS t1 
		LEFT JOIN 
		(
		 SELECT uid,mid,chat_text FROM special_chat AS t4 ORDER BY mid DESC 
		) AS t4_sub ON t4_sub.uid = t1.uid
		JOIN login AS t2 ON t1.uid = t2.uid 
		WHERE t1.fuid = {$uid} GROUP BY t1.uid;
EOF;

foreach($query as $k_query => $v_query){
		 //Reset $res encase next iteration is blank and the variable is cleared , this is because the output layer ( html_1 ) will hold $res if it's not cleared
		 $res = '';
		 $this->db_class_mysql->set_query($query[$k_query],'find_users',"Updating a users alert settings on his profile");	
		 $search_people = $this->db_class_mysql->execute_query('find_users');
				 if($search_people->num_rows > 0){
                                                while($search_res = $search_people->fetch_assoc()){
                                                        $uid = $search_res['uid'];
                                                        $uname = $search_res['uname'];
                                                        $fname = $search_res['fname'];
                                                        $lname =  $search_res['lname'];
                                                        $pic_100 = $search_res['pic_100'];
							$last_chat = $search_res['last_chat'];

                                                        $friend_data[$uid] = array(
                                                        'uid' => $uid,
                                                        'uname' => $uname,
                                                        'fname' => $fname,
                                                        'lname' => $lname,
                                                        'pic_100' => $pic_100,
                                                        'last_chat' => $last_chat,
                                                        'groups' => null,
                                                        'friend' => null
                                                        );
                                                        $ids .= $uid.',';
                                                }
                                                        $ids = substr($ids,0,-1);

                                                $group_query = <<<EOF
                                                SELECT groups.gid,uid,gname,symbol FROM groups
                                                JOIN group_members ON groups.gid = group_members.gid
                                                WHERE uid IN ({$ids})
EOF;
                                                $this->db_class_mysql->set_query($group_query,'users_groups',"Finds all of the group a specific user is in");
                                                $search_groups = $this->db_class_mysql->execute_query('users_groups');
                                                while($res = $search_groups->fetch_assoc()){
                                                        $uid = $res['uid'];
							if($c[$uid])$c[$uid]++; else $c[$uid] = 1;
							if($c[$uid] > 3) continue;

                                                        $gname = $res['gname'];
                                                        $gid = $res['gid'];
                                                        $symbol = $res['symbol'];
                                                        $friend_data[$uid]['groups'][$gid] = array(
                                                        'gid' => $gid,
                                                        'gname' => $gname,
                                                        'symbol' => $symbol
                                                        );
                                                }
						$c = null;
                                                $friend_query = <<<EOF
                                                SELECT fuid FROM friends WHERE uid = {$uid} AND fuid IN({$ids});
EOF;
						$this->db_class_mysql->set_query($friend_query,'friend_query',"This tells you if they're you haved them tap or not so the correct state can be set upon the page loading");
                                                $friend_res = $this->db_class_mysql->execute_query('friend_query');

                                                if($friend_res->num_rows > 0)
                                                        while($res = $friend_res->fetch_assoc())
                                                                $friend_data[$res['fuid']]['friend'] = 1;	
					$this->set($friend_data,'people_'.$k_query);
					}
	}
	
}
}
?>
