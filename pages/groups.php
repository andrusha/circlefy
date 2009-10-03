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
			SELECT ugm.admin,ugm.gid,t2.descr,t2.gid,t2.gname,t2.connected,t2.focus,t2.pic_100,count(t1.uid) AS size
			FROM (
			  SELECT DISTINCT gid,admin
			  FROM group_members
			  WHERE uid={$uid}
			) AS ugm
			JOIN group_members AS t1 ON t1.gid=ugm.gid
			JOIN groups AS t2 ON t2.gid = ugm.gid 
			GROUP BY ugm.gid;
EOF;

			$group_list_query = <<<EOF
			SELECT t2.gname,t1.gid FROM group_members AS t1 JOIN groups AS t2 ON t2.gid=t1.gid WHERE t1.uid={$uid}
EOF;

			$group_random_pick = <<<EOF
			SELECT t1.gname,t1.gid,t1.focus,t1.descr,t1.pic_36 FROM groups AS t1 WHERE t1.connected = 0 ORDER BY gid DESC LIMIT 5;
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
                                $descr = $res['descr'];
                                $focus = $res['focus'];
                                $pic = $res['pic_36'];
                                $gname = $res['gname'];

                                $random_groups[] = array(
					'gid' => $gid,
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

                                $groups[] = array(
					'gid' => $gid,
					'gname' => $gname,
					'admin' => $admin,
					'pic' => $pic,
					'focus' => $focus,
					'type' => $type,
					'size' => $size,
					'focus' => $focus,
					'descr' => $descr,
					'official' => $official
				);
			}

			$this->set($groups,'group_results');
				

                        $this->db_class_mysql->set_query($group_list_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                	        $groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');
                        $this->set($groups_you_are_in,'your_groups');

				
	
				}
				
	}

?>
