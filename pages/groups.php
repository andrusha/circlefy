<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class groups extends Base{

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
		$this->page_name = "new_groups_manage";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();
		
		$uid = $_SESSION['uid'];

		$get_group_query = <<<EOF
			SELECT
			ogm.admin,ogm.gid,
			g.descr,g.gid,g.symbol,g.gname,g.connected,g.focus,g.pic_100,count(t1.uid) AS size
			FROM (
			  SELECT DISTINCT gid,admin
			  FROM group_members
			  WHERE uid={$uid}
			) AS ogm
			JOIN group_members AS t1 ON t1.gid=ogm.gid
			JOIN groups AS g ON g.gid = ogm.gid 
			GROUP BY ogm.gid;
EOF;

			$group_list_query = <<<EOF
			SELECT
			g.gname,
			gm.gid
			FROM group_members AS gm
			JOIN groups AS g ON g.gid=gm.gid WHERE gm.uid={$uid}
EOF;

			$group_random_pick = <<<EOF
			SELECT
			g.gname,g.symbol,g.gid,g.focus,g.descr,g.pic_36
			FROM groups AS g WHERE g.connected = 0
			ORDER BY gid DESC LIMIT 5;
EOF;

	
			$connected_group_query = <<<EOF
			SELECT t3.email,t1.gid,t1.gname,t1.symbol,t2.uid,t2.status FROM group_members AS t2
			JOIN groups AS t1
			ON t1.gid = t2.gid
			JOIN join_group_status as t3
			ON t1.gid = t3.gid AND t2.uid = t3.uid
			WHERE t2.uid = {$uid} 
EOF;

			$geo_list_query = <<<EOF
			SELECT cn.abbr2,cn.name AS Country FROM country_translate AS cn WHERE cn.abbr2 != '-' LIMIT 249;
EOF;
			
			$this->db_class_mysql->set_query($geo_list_query,'geo_list_query','Populates Geo List');
			$geo_list_results = $this->db_class_mysql->execute_query('geo_list_query');
			while($res = $geo_list_results->fetch_assoc()){
			$abbr2 = $res['abbr2'];
			$name = $res['name'];
		
			$init_geo_data = array(
				'abbr2' =>	$abbr2,
				'name' =>	$name
			);

			}
			$this->set($init_geo_data,'init_geo_data');

			$this->db_class_mysql->set_query($connected_group_query,'get_connected_groups','This gets the status and attributes of all connected groups to display to the user');
				$connected_groups_results = $this->db_class_mysql->execute_query('get_connected_groups');
				$this->set($connected_groups_results,'connected_groups');
	
			$this->db_class_mysql->set_query($group_random_pick,'get_random_groups',"Getting random 'relevanct' groups");
			$rand_group_results = $this->db_class_mysql->execute_query('get_random_groups');

			while($res = $rand_group_results->fetch_assoc()){
				$gid = $res['gid'];
				$symbol= $res['symbol'];
                                $descr = $res['descr'];
                                $focus = $res['focus'];
                                $pic = $res['pic_36'];
                                $gname = $res['gname'];

                                $descr = stripslashes($descr);
                                $focus = stripslashes($focus);

                                $random_groups[] = array(
					'gid' => $gid,
					'symbol' => $symbol,
					'gname' => $gname,
					'pic' => $pic,
					'focus' => $focus,
					'descr' => $descr,
				);
			}
			$this->set($random_groups,'random_group_results');


			$this->db_class_mysql->set_query($get_group_query,'get_groups',"get_groups");
			$group_results = $this->db_class_mysql->execute_query('get_groups');

                        while($res = $group_results->fetch_assoc()){
				$gid = $res['gid'];
				$symbol= $res['symbol'];
				$descr = $res['descr'];
				$admin = $res['admin'];
                                $pic = $res['pic_100'];
                                $gname = $res['gname'];
                                $type = $res['connected'];
                                $size = $res['size'];
				$focus = $res['focus'];

                                if($type)
                                        $official = "*";
                                else    $official = "";
				$descr = stripslashes($descr);
                                $focus = stripslashes($focus);

                                $groups[$gid] = array(
					'gid' => $gid,
					'symbol' => $symbol,
					'gname' => $gname,
					'admin' => $admin,
					'pic' => $pic,
					'focus' => $focus,
					'type' => $type,
					'size' => $size,
					'focus' => $focus,
					'descr' => $descr,
					'official' => $official,
					'last_uname' => null,
					'last_chat'=> null,
					'count'	=> 0
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
			$this->db_class_mysql->set_query($group_message_count,'group_count',"Get's message count for groups");
			$message_count_results = $this->db_class_mysql->execute_query('group_count');
			
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
			$this->set($groups,'group_results');
				

                        $this->db_class_mysql->set_query($group_list_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                	        $groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');
                        $this->set($groups_you_are_in,'your_groups');

				
	
				}
				
	}

?>
