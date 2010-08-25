<?php

class public_user extends Base{

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
		$this->page_name = "public_user";
	
		parent::__construct();
	

		//Security flaw, I need to change this, GET param should be in request object
		$uname = $_GET['public_uid'];

		//This gets all users initial settings such as the groups he's in etc...
		//SECURITY ... I SHOULD at t2.status = 1 so that only members who are confirmed get updates	
		$get_user_id_query = <<<EOF
		SELECT t1.uname,t1.uid,t1.private,t2.gid,t3.country,t3.zip FROM login AS t1
		LEFT JOIN group_members AS t2
		ON t1.uid = t2.uid
		LEFT JOIN profile AS t3
		ON t1.uid = t3.uid
		WHERE t1.uname='{$uname}' LIMIT 1;
EOF;
		
		$get_user_id_result = $this->db_class_mysql->db->query($get_user_id_query);

		//This creates the array that holds all the users gids
		//echo "Query: " . $get_user_id_query . "<br />Rows: " . ($get_user_id_result->num_rows);
		//echo "<br />RES: ".$get_user_id_result;
		if($get_user_id_result->num_rows){
			while($res = $get_user_id_result->fetch_assoc()){
				$uid = $res['uid'];
				$private = $res['private'];
				$public_uid = $res['uid'];
				$uname = $res['uname'];
				$country = $res['country'];
				$zip = $res['zip'];
			}

			//echo "OKIS";
		}else{
			//echo "<br /><br />MAL MAL MAL<br />";
			//$this->set('no_user','no_user');
			return false;
		}
			$this->set($private,'private');

        $friends = new Friends();
        $followers = $friends->followersCount($uid);
        $following = $friends->followingCount($uid);

        $status = $friends->following($_SESSION['uid'], $uid);
        $this->set((int)$status, 'tracked');

        $this->set($following,'tracked_count');
        $this->set($followers,'track_count');
		
		//START User Prefences
		$user_query = <<<EOF
		SELECT t2.online,t1.pic_100,t1.pic_36,t1.uid,t1.uname,p.about,p.country,t1.help FROM login AS t1
		LEFT JOIN profile AS p
		ON t1.uid = p.uid
		LEFT JOIN TEMP_ONLINE AS t2
		ON t2.uid = p.uid
		WHERE t1.uid={$uid}
EOF;
		$this->db_class_mysql->set_query($user_query,'get_user',"This gets the user who is logged in in order to display it to the homepage next to 'Welcome xxxx'");
		$user = $this->db_class_mysql->execute_query('get_user');
		while($res = $user->fetch_assoc() ){
			$global_uname = $res['uname'];
			$this->set($res['uname'],'user');
			$this->set($res['about'],'about');
			$this->set($res['uid'],'uid');
			$this->set($res['pic_36'],'user_pic_small');
			$this->set($res['pic_100'],'user_pic_med');
			$this->set($res['help'],'help');
			$this->set($res['country'],'country');
			$this->set($res['online'],'user_online');
		}
	
		//START group setting creation
		$group_list_query = <<<EOF
		SELECT COUNT(scm.gid) as message_count,t2.symbol,t2.connected,t1.tapd,t1.inherit,t2.pic_36,t2.favicon,t2.gname,t1.gid,t1.admin
		FROM group_members AS t1
		LEFT JOIN groups AS t2 ON t2.gid=t1.gid
		LEFT JOIN special_chat_meta AS scm ON scm.gid=t1.gid
		WHERE t1.uid={$uid}
		GROUP BY t2.gid
EOF;
                $this->db_class_mysql->set_query($group_list_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                                $groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');

		if($groups_you_are_in->num_rows)
		while($res = $groups_you_are_in->fetch_assoc()){
			$gid = $res['gid'];
			$gname = $res['gname'];
			$pic_36 = $res['pic_36'];
			$favicon = $res['favicon'];
			$symbol = $res['symbol'];
			$connected = $res['connected'];
			$tapd = $res['tapd'];
			$admin = $res['admin'];
			$message_count = $res['message_count'];

			//Process
			if(strlen($gname) > 25)
				$gname = substr($gname,0,25).'..'; 

			$my_groups_array[] = array(
					'gid' => $gid,
					'gname' => $gname,
					'pic_36' => $pic_36,
					'favicon' => $favicon,
					'symbol' => $symbol,
					'display_symbol' => $gname,
					'type' => $connected,
					'tapd' => $tapd,
					'admin' => $admin,
					'message_count' => $message_count
				);
			}
			$this->set($my_groups_array,'your_groups');	


        $taps = new Taps();

		$data_taps = $taps->getFiltered('personal', array('#uid#' => $uid, '#outside#' => '1, 2'));
		$this->set($data_taps, 'user_bits');

        $user = new User();
        $stats = $user->getStats($uid);

		$stats = array(
			'message_count' => $stats['taps'],
			'response_count' => $stats['responses'],
			'group_count' => $stats['groups']
		);
		$this->set($stats,'stats');
		//END stats

		//START set the session uid for Orbited
                $this->set($_SESSION['uid'],'pcid');
                //END set the session uid for Orbited
	}

}
?>
