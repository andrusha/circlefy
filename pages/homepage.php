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


        $userClass = new User();

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
	
	
		//START group setting creation
		$group_list_query = <<<EOF
			SELECT COUNT(scm.gid) as message_count,GROUP_ON.count,t2.descr AS topic,t2.symbol,t2.connected,t1.tapd,t1.inherit,t2.pic_36,t2.favicon,t2.gname,t1.gid,t1.admin
			FROM group_members AS t1
			JOIN groups AS t2 ON t2.gid=t1.gid
			LEFT JOIN GROUP_ONLINE AS GROUP_ON ON GROUP_ON.gid=t1.gid
			LEFT JOIN special_chat_meta AS scm ON scm.gid=t1.gid
			WHERE t1.uid={$uid}
			GROUP BY scm.gid
EOF;
                $this->db_class_mysql->set_query($group_list_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                                $groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');



		while($res = $groups_you_are_in->fetch_assoc()){
			$gid = $res['gid'];
			$gname = $res['gname'];
			$pic_36 = $res['pic_36'];
			$symbol = $res['symbol'];
			$connected = $res['connected'];
			$admin = $res['admin'];
			$topic = $res['topic'];
			$tapd = $res['tapd'];
			$online_count = $res['count'];
			$favicon = $res['favicon'];
			$message_count = $res['message_count'];

			$real_symbol[0] = $symbol;
			$symbol = explode('.',$symbol);
			if($symbol[1] != 'com' && $symbol[1] != 'edu') $add = ' '.$symbol[1];
			$topic = stripslashes($topic);
			$display_symbol = ucwords($symbol[0].$add);
			$add = null;
			
			
			$my_groups_array[$gid] = array(
				'gid' => $gid,
				'gname' => $gname,
				'topic' => $topic,
				'pic_36' => $pic_36,
				'symbol' => $real_symbol,
				'display_symbol' => $gname,
				'favicon' => $favicon,
				'type' => $connected,
				'online_count' => $online_count,
				'tapd' => $tapd,
				'admin' => $admin,
				'message_count' => $message_count,
				'total_count' => null,
			);
			$gid_list .= $gid.',';
		}
		$gid_list = substr($gid_list,0,-1);

		$group_member_count_query = <<<EOF
		SELECT COUNT(uid) AS member_count,gid,uid FROM group_members AS gm WHERE gid IN ({$gid_list}) GROUP BY gid;
EOF;

                $this->db_class_mysql->set_query($group_member_count_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                $group_count_res = $this->db_class_mysql->execute_query('get_users_groups');

		if($group_count_res->num_rows)
		while($res = $group_count_res->fetch_assoc()){
			$count = $res['member_count'];
			$gid = $res['gid'];
			$my_groups_array[$gid]['total_count'] = $count;
		}
                $this->set($my_groups_array,'your_groups');	

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
$groups_you_are_in->data_seek(0);

//START set the session uid for Orbited
$this->set($_SESSION['uid'],'pcid');
//END set the session uid for Orbited


$update_temp_online_query = <<<EOF
	UPDATE TEMP_ONLINE SET timeout = 0,cid = "$push_channel_id" WHERE uid = $uid
EOF;

$this->db_class_mysql->set_query($update_temp_online_query,'TEMP_ONLINE_UPDATE','UPDATES users TEMP_ONLINE status');
$TEMP_ONLINE_results = $this->db_class_mysql->execute_query('TEMP_ONLINE_UPDATE');

if(!$this->db_class_mysql->db->affected_rows){
	$gid_count = 0;
	while($res = $groups_you_are_in->fetch_assoc() ){
		$gid_string .= $res['gid'].',';
		$gid_count++;
	}
	$gid_string = substr($gid_string,0,-1);

	$insert_temp_online_query = <<<EOF
	INSERT INTO TEMP_ONLINE(uid,cid,gids) values($uid,"$push_channel_id","$gid_string");
EOF;
	$this->db_class_mysql->set_query($insert_temp_online_query,'TEMP_ONLINE_INSERT','INSERTS users TEMP_ONLINE status');
	$TEMP_ONLINE_results = $this->db_class_mysql->execute_query('TEMP_ONLINE_INSERT');

	//START gid presence updateding
	$query_string = "UPDATE GROUP_ONLINE SET online = online+1 WHERE gid IN($gid_string)";
	$this->db_class_mysql->set_query($query_string,'GROUP_ONLINE_UPDATE','Updates group online presence');
	$GROUP_ONLINE_results = $this->db_class_mysql->execute_query('GROUP_ONLINE_UPDATE');


	if($this->db_class_mysql->db->affected_rows != $gid_count){
		$groups_you_are_in->data_seek(0);
		while($res = $groups_you_are_in->fetch_assoc() ){
			$gid = $res['gid'];
			$insert_string = "INSERT INTO GROUP_ONLINE(gid,online) values($gid,1);";
			$this->db_class_mysql->set_query($insert_string,'GROUP_ONLINE_INSERT','INSERTS users GROUP_ONLINE status');
			$GROUP_ONLINE_results = $this->db_class_mysql->execute_query('GROUP_ONLINE_INSERT');
		}
	}
	//END gid presence updating
}
$groups_you_are_in->data_seek(0);

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

	function checkIfGroupExists($groupName){
		$check_group_query = <<<EOF
			select gname from groups where gname = '{$groupName}'
EOF;
		$this->db_class_mysql->set_query($check_group_query,'check_group_query',"Chck if exists group");
		$check_group_exists = $this->db_class_mysql->execute_query('check_group_query');
		//$check_group_exists = $this->mysqli->query($check_group_query);
		return ($check_group_exists->num_rows > 0);

	}
    
    function checkIfSymbolExists($symbol){
        $check_symbol_query = <<<EOF
            select symbol from groups where symbol = '{$symbol}'
EOF;
        $this->db_class_mysql->set_query($check_symbol_query,'check_symbol_query',"Chck if exists symbol");
        $check_symbol_exists = $this->db_class_mysql->execute_query('check_symbol_query');
        return ($check_symbol_exists->num_rows > 0);

    }

	function create_group($gname,$symbol,$descr,$focus,$email_suffix,$private,$invite,$old_name,$country,$state,$region,$town){
		$uid = $_SESSION["uid"];
		$uname = $_SESSION["uname"];

		$create_group_query = <<<EOF
		INSERT INTO groups(gname,symbol,gadmin,descr,focus,private,invite_only,email_suffix,country,state,region,town,created)
		values("$gname","$symbol",$gadmin,"$descr","$focus",$private,$invite_only,$email_suffix,"$country","$state","$region","$town",NOW())
EOF;
	  $create_group_results = $this->mysqli->query($create_group_query);
	  $last_id = $this->mysqli->query($this->last_id);
	  $last_id = $last_id->fetch_assoc();
        $last_id = $last_id['last_id'];
		$gid = $last_id;

		$GROUP_ONLINE_query = <<<EOF
		INSERT INTO GROUP_ONLINE(gid) values($gid)
EOF;
                $this->mysqli->query($GROUP_ONLINE_query);

		if($gadmin != 0){
			$add_me_as_admin_query = <<<EOF
			INSERT INTO group_members(uid,gid,admin) values({$gadmin},{$gid},1)
EOF;
            $this->mysqli->query($add_me_as_admin_query);
		}

	}
    
    function createSymbol($string, $existingSymbols=array()){
        
        //Cleaning
        $string = strtolower($string);
        $allowedChars = "abcdefghijklmnopqrstuvwxyz_ ";
        $exploded_allowed = str_split($allowedChars);
        $exploded = str_split($string);
        foreach($exploded as $key=>$value):
            if(in_array($value, $exploded_allowed)){
                $cleanString .= $value;
            }
        endforeach;
            
        //Sacar espacios
        $string_exploded = explode(" ", $cleanString);
        
        //si tiene una sola palabra, usar la palabra como simbolo
        if(count($string_exploded) == 1){
            $retArray[] = $cleanString;
        //Sino fabricar acronimo
        }else{
            foreach($string_exploded as $key=>$value):    
                $acronym .= $value{0};
            endforeach;
            $retArray[] = $acronym;
            //underscored
            $retArray[] =  implode("_", $string_exploded);
        }
        
        foreach($retArray as $key=>$posibleSymbol){
            if(!in_array($posibleSymbol, $existingSymbols)){
                $found = true;
                return $posibleSymbol;
            }    
        }
        if(!$found){
            return implode("_", $string_exploded) . rand(1000000, 9999999);
        }
    }

}
?>
