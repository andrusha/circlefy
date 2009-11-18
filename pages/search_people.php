<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class search_people extends Base{

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
		$this->page_name = "search_people";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();
		
		$uid = $_SESSION['uid'];
		//Match all user IDs with a specific ID, so you will have join the group_members table with the groups table
		//matching the groups_members table with the groups table based off gid, you need to return the name
			$get_users_groups_query = <<<EOF
			SELECT t2.gname,t2.symbol,t1.gid,t1.connected FROM group_members AS t1 
			JOIN groups AS t2 ON t2.gid=t1.gid 
			WHERE t1.uid={$uid};
EOF;
			$this->db_class_mysql->set_query($get_users_groups_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
			$groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');
			$this->set($groups_you_are_in,'your_groups');
		
		$search = $_GET['people_search_button'];
		$uname = $_GET['uname'];
		$fname = $_GET['fname'];
		$lname = $_GET['lname'];
		$zipcode = $_GET['zipcode'];
		$parts = explode(":",$_GET['group']);
		$group = $parts[0];
		$connected = $parts[1];
		if($search){
					//This is all dynamic query generation!! ( Hence the code looks a bit crazy ) 

		
					//if the last name is specified tag this on to the end of the query
					if($uname){ $query_array['uname'] = ' t1.uname = "'.$uname.'"'; }
					if($fname){ $query_array['fname'] = ' t1.fname = "'.$fname.'"'; }
					if($lname){ $query_array['lname'] = ' t1.lname ="'.$lname.'"'; }
					/*if($group){
						$join['group_q1'] = 't1.gname FROM groups AS t1 INNER JOIN group_members AS t2 ON t1.gid';
}*/
					if($zipcode) { 
						$join['zip_q1'] = 'JOIN profile AS t2 ON t2.uid = t1.uid';
						$query_array['zip_q2'] = ' t2.zip="'.$zipcode.'"';
					}
					if($group)  {
						// This can probably be furher optmized by removeing the second join and stating that t3.gid=# in SQL
						$join['group_q1'] = 'JOIN group_members AS t3 ON t1.uid=t3.uid';
						$query_array['group_q2'] = ' t3.gid="'.$group.'"';
					}
			
					//This part shows what the user last tap'd via the special_chat table	
					$join['last_chat'] = <<<EOF
					LEFT JOIN
			                (
			                 SELECT uid,mid,chat_text FROM special_chat AS t4 ORDER BY mid DESC
			                ) AS t4_sub ON t4_sub.uid = t1.uid
EOF;
					$query_array['last_chat'] = 'GROUP BY uid';			
			

					foreach($query_array as $k => $v){
						$counter++;
						if($counter != 1 && $k !== 'last_chat'){$v = " AND $v";}
						$query_array[$k] = $v;
					}
				

					
					$find_users_query = 
						<<<EOF
						SELECT 
						t1.pic_100,t1.uid,t1.uname,t1.fname,t1.lname,
						t4_sub.chat_text AS last_chat
						FROM login AS t1 {$join['zip_q1']} {$join['group_q1']} {$join['last_chat']}
						WHERE {$query_array['uname']}  {$query_array['fname']} {$query_array['lname']} {$query_array['zip_q2']} {$query_array['group_q2']} {$query_array['last_chat']}
EOF;
					$this->db_class_mysql->set_query($find_users_query,'find_users',"Updating a users alert settings on his profile");
					$search_people = $this->db_class_mysql->execute_query('find_users');

		 	                if($search_people->num_rows > 0){
						while($search_res = $search_people->fetch_assoc()){
							$uid = $search_res['uid'];
							$uname = $search_res['uname'];
							$fname = $search_res['fname'];
							$lname =  $search_res['lname'];
							$pic_100 = $search_res['pic_100'];	
							$last_chat = $search_res['last_chat'];
							
							if(!$last_chat)
								$last_chat = "*This user has not tap'd yet*";
							
							$search_data[$uid] = array(
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
						SELECT groups.gid,uid,gname FROM groups 
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
                                                        $search_data[$uid]['groups'][$gid] = array(
                                                        'gid' => $gid,
                                                        'gname' => $gname,
                                                        'symbol' => $symbol
                                                        );
						}
	
						$friend_query = <<<EOF
						SELECT fuid FROM friends WHERE uid = {$uid} AND fuid IN({$ids});
EOF;
						$this->db_class_mysql->set_query($friend_query,'friend_query',"This tells you if they're you haved them tap or not so the correct state can be set upon the page loading");
						$friend_res = $this->db_class_mysql->execute_query('friend_query');
						
						if($friend_res->num_rows > 0)
							while($res = $friend_res->fetch_assoc())
								$search_data[$res['fuid']]['friend'] = 1;

					$this->set($search_data,'search_data');
                			}
			}
	}

}
?>
