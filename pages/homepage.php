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
		//$this->autoCreateUser = 1;
	
		parent::__construct();
		
		$this->set($result,'users');
		
		if($_GET['logout'] && !$_POST['uname']){
			$logout_status = $this->login_class->logOut($_SESSION['uid']);
            header("location: /");
		}
        
        if (!empty($_POST['uname']) && !empty($_POST['pass']) && empty($_POST['fb_login'])) {
           $this->login_class->logIn($_POST['uname'], $_POST['pass']);
           $this->set(true, 'ok_user');
        } else if ($_POST['fb_login'] == '1') {
           $this->login_class->logInWithFacebook();
           $this->set(true, 'ok_user');
        }

		// We'll always use this view:
			$this->page_name = "new_homepage";
			$uid = $_SESSION['uid'];
	
		$uname = $_SESSION['uname'];	
		//This gets all users initial settings such as the groups he's in etc...
		//this is used for message_handle to check what groups he's in and also says
		//which groups he'll be able to filter off of
	
		//SECURITY ... I SHOULD at t2.status = 1 so that only members who are confirmed get updates	
		$get_user_id_query = <<<EOF
		SELECT lo.uname,lo.uid,gMembers.gid,Prof.zip FROM login AS lo
		LEFT JOIN group_members AS gMembers
		ON lo.uid = gMembers.uid
		LEFT JOIN profile AS Prof
		ON lo.uid = Prof.uid
		WHERE lo.uname='{$uname}'";
EOF;
	
		$get_user_id_result = $this->db_class_mysql->db->query($get_user_id_query);


			//This creates the array that holds all the users gids
			if($get_user_id_result->num_rows)
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


        $userClass = new User(intval($_SESSION['uid']));
//      We actually make user online/offline in Server.py
//       $userClass->makeOnline();

        //Get user info
        $res = $userClass->getInfo($uid);
		$global_uname = $res['uname'];
        $this->set($res['uname'],'user');
        $this->set($res['small_pic'],'user_pic');
        $this->set($res['big_pic'],'user_pic_100');
		$this->set($res['help'],'help');
        $this->set($res['real_name'], 'real_name');

        $convosClass = new Convos();

        //Get active convos
        $active_convos = $convosClass->getActive($uid);
		$this->set($active_convos,'active_convos');
	
        $group_list = GroupsList::byUser($userClass, G_EXTENDED | G_ONLINE_COUNT | G_USERS_COUNT);

        $group_formatted = array();
        foreach ($group_list as $group) {
            $info = $group->info;
            $gid = $group->gid;
            $info['display_symbol'] = $info['symbol'];
            $info['online_count'] = $info['count'];
            $info['total_count'] = $info['members_count'];
            //stub, cuz actually unused
            $info['message_count'] = 65535;
            $info['tapd'] = 0;

            $group_formatted[$gid] = $info;
        }
        $this->set($group_formatted,'your_groups');	

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

    $taps = new Taps();
    if (!$_SESSION['guest']) {
        $params = array('#outside#' => '1, 2', '#uid#' => $uid);
        $filter = 'aggr_groups';
    } else {
        //show all public taps for guests
        $params = array('#outside#' => '1, 2');
        $filter = 'public';
    }
    $data = $taps->getFiltered($filter, $params);

    $this->set($data, 'groups_bits');


/////////////////////////////////////////////////////////////////////////////
//START misc tasks - Including, getting max file id, creating channel id, etc
/////////////////////////////////////////////////////////////////////////////

//START set the session uid for Orbited
$this->set($_SESSION['uid'],'pcid');
//END set the session uid for Orbited

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
}
