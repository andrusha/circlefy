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
	
		parent::__construct();
		
		$this->set($result,'users');
		
		if($_GET['logout'] && !$_POST['uname']){
			$logout_status = $this->login_class->log_out($_SESSION['uid']);
		
			switch($logout_status){
				case 'goodbye':
					$this->set('<font color="red">You are now logged out</font>','errors');
					break;		
			}
		}
		
		if(!$_GET['logout'] && $_COOKIE['auto_login'] && !$_SESSION['uid']){
			$bypass = $this->login_class->bypass_login();
			
			switch ($bypass){
				case 'bypassed':
					break;
					
				case 'fraud':
					$this->set("<font color='red'>Warning! You are trying to use a expired account session!</font>",'errors');
					break;
			}
		}
		
		if(!$bypass && !$_GET['logout'] && $_POST['uname']){
			$login_status = $this->login_class->validate_user();
			
			switch ($login_status){
				
				case 'short':
					$this->set('<font color="red">Your username you type is less then 2 chars</font>','errors');
					break;
					
				case 'success':
					$this->set("Congrats, You're logged in!",'errors1');
					break;
				
				case 'invalid':
					$this->set('<font color="red">Your username and/or password is invalid</font>','errors');
				
			}
		}
		
		if($_COOKIE['wasp_attack']){
			$this->set('Continue Signing Up!','signup');
			$this->set('<script type="text/javascript">first_run();</script>','second_step');
			$this->set('onclick="show_next_step(this); return false;">&nbsp; Next &gt;&gt; &nbsp;','step_one');
		} else { 
			$this->set('Sign Up!','signup');
			$this->set('onclick="check_all(this,1); return false;">&nbsp; Sign Up! &nbsp;','step_one'); 
		}
		
		if($_SESSION['uid']){
			$this->page_name = "homepage";
			$uid = $_SESSION['uid'];
			} else {
			$this->page_name = "homepage_loged_out";
		}

	

		if($uid){
	
		//This gets all users initial settings such as the groups he's in etc...
		//this is used for message_handle to check what groups he's in and also says
		//which groups he'll be able to filter off of
		$get_user_id_query = "SELECT t1.uname,t1.uid,t2.gid,t3.zip FROM login AS t1
					LEFT JOIN group_members AS t2
					ON t1.uid = t2.uid
					LEFT JOIN display_rel_profile AS t3
					ON t1.uid = t3.uid
					WHERE t1.uname='{$uname}' AND t2.status=1;";

		$get_user_id_result = $this->db_class_mysql->db->query($get_user_id_query);


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


		//START User Prefences
		$user_query = <<<EOF
                        SELECT t1.pic_100,t1.pic_36,t1.uname,t1.help FROM login AS t1
                        WHERE t1.uid={$uid}
EOF;

                 $this->db_class_mysql->set_query($user_query,'get_user',"This gets the user who is logged in in order to display it to the homepage next to 'Welcome xxxx'");
                                $user = $this->db_class_mysql->execute_query('get_user');
			while($res = $user->fetch_assoc() ){
			$global_uname = $res['uname'];
                        $this->set($res['uname'],'user');
                        $this->set($res['pic_36'],'user_pic');
			$this->set($res['help'],'help');
			}
		//START User Prefences

	
		
		//START rel setting creation
		//NOTE: This should be refactored, completely.
		$group_query = <<<EOF
		SELECT t2.gname,t1.gid FROM group_members AS t1
		JOIN groups AS t2 ON t2.gid=t1.gid
		WHERE t1.uid={$uid}
EOF;

                $this->db_class_mysql->set_query($group_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                $groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');
                $this->set($groups_you_are_in,'your_groups');

                $get_rel_query = 'SELECT * FROM rel_settings WHERE uid = '.$uid;

                $this->db_class_mysql->set_query($get_rel_query,'get_rel','This gets the users initial settings');

                $get_rel_result = $this->db_class_mysql->execute_query('get_rel');


                if($get_rel_result->num_rows > 0){

                while($get_rel_output = $get_rel_result->fetch_assoc()){
                        if($get_rel_output['gid'] != 0)
                                $groups_IN_res[$get_rel_output['rid']] .= $get_rel_output['gid'];
                }

                if($groups_IN_res){
                        //Build query for groups
                        foreach($groups_IN_res as $k => $v){
                                 $counting++;
                                if($counting !== 1){
                                        $group_list .= ','.$v;
                                } else {
                                        $group_list .= $v;
                                }
                        }
			$counting = 0;

                        $get_groups_query = <<<EOF
                        SELECT gid,gname FROM groups
                        WHERE gid IN ({$group_list})
EOF;

                        $this->db_class_mysql->set_query($get_groups_query,'get_rel_groups','This gets the groups that are then associated to an rid ( or relevacny id )');
                        $get_groups_res = $this->db_class_mysql->execute_query('get_rel_groups');

                        while($res = $get_groups_res->fetch_assoc()){
                                foreach($groups_IN_res as $k => $v){
                                        if($res['gid'] == $v)
                                                $group_output[$k] = $res['gname'];
                                }
                        }
                }

                $get_rel_result->data_seek(0);

                while($get_rel_output = $get_rel_result->fetch_assoc()){
                        $counter++;

                        if($get_rel_output['tags']){
                        $tags = "".$get_rel_output['tags'];
                        } else {
                        $tags = "Anything said in...";
	                }

                        if($get_rel_output['zip']){
                        $zip = "".$get_rel_output['zip'];
                        } else {
                        $zip = 'Any Location';
                        }

                        if($get_rel_output['enabled']){
                        $enable = 'green';
                        $enable_txt = 'Enabled';
                        $state = 1;
                        } else {
                        $enable = 'red';
                        $enable_txt = 'Disabled';
                        $state = 0;
                        }

                        if($get_rel_output['gid'] == 0){
                                $gid = 'All';
                        } else {
                                $gid = $group_output[$get_rel_output['rid']];
                        }

                        $rel_string = $keywords.$lang.$country.$zip;
                        $rid = $get_rel_output['rid'];
                        $name = stripslashes($get_rel_output['name']);
                        $display_name = $get_rel_output['name'];

                        $bg = $counter & 1;

                        if($bg == 1){ $bg = 'white'; } else { $bg = 'blue'; }

                        $html_results[$counter] = <<<EOF
                <tr class="rel_rid_{$bg}" id="rel_{$rid}">
                                        <td class="rel_name_number">{$counter}. {$name}</td>
                                        <td class="active_rel">{$tags}</td>
                                        <td class="active_loc">{$zip}</td>
                                        <td class="active_group">{$gid}</td>
                                        <td class="enable_{$enable} {$state}" id="state_{$rid}" onclick="update_enable({$rid},this.className[this.className.length-1]);">{$enable_txt}</td>
                                        <td class="delete_rel"><a href="#" onclick='del_rel({$rid});'>Delete</a></td>
                </tr>

EOF;
	
			$filter_search_list[$rid] = $display_name;
			$html_results_2[$counter] = <<<EOF
			<li class="toggle_list_el" id="tab_rel_{$rid}" onclick="show_info('tab_rel_{$rid}','{$display_name}');"><img  class="tab_bullet" id="tab_rel_{$rid}_bullet" src="images/icons/bullet_white.png" /> {$counter}. {$name}</li>
EOF;
                }
}
                $this->set($html_results,'get_rel_results');                                                                                                     
                $this->set($html_results_2,'get_rel_tab_names'); 
                $this->set($filter_search_list,'filter_search_list'); 
		/* END copy of rel_settings.php */

		//NEW GROUPS QUERY	
		$group_list_query = <<<EOF
			SELECT t2.symbol,t2.connected,t1.tapd,t1.inherit,t2.pic_100,t2.focus,t2.gname,t1.gid FROM group_members AS t1 JOIN groups AS t2 ON t2.gid=t1.gid WHERE t1.uid={$uid};
EOF;

		$this->db_class_mysql->set_query($group_list_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                                $groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');
                        $this->set($groups_you_are_in,'your_groups');
			//END rel setting creation



//START populate friends list
$query[0] = <<<EOF
SELECT t2.pic_100,t2.uid,t2.uname,t2.fname,t2.lname,t4_sub.chat_text AS last_chat FROM friends AS t1
LEFT JOIN
(
 SELECT uid,mid,chat_text FROM special_chat AS t4 ORDER BY mid DESC
) AS t4_sub ON t4_sub.uid = t1.fuid
JOIN login AS t2 ON t1.fuid = t2.uid
WHERE t1.uid = {$uid} GROUP BY t1.fuid;
EOF;


foreach($query as $k_query => $v_query){
                 //Reset $res encase next iteration is blank and the variable is cleared , this is because the output layer ( html_1 ) will hold $res if it's not cleared
                 $res = '';
                 $this->db_class_mysql->set_query($query[$k_query],'get_friends',"This gets the friends list");
                 $search_people = $this->db_class_mysql->execute_query('get_friends');

                                        if($search_people->num_rows > 0){
                                                while($search_res = $search_people->fetch_assoc()){
                                                        $counting++;
                                                        if($counting !== 1){
                                                                $ids .= ','.$search_res['uid'];
                                                                        } else {
                                                                $ids .= $search_res['uid'];
                                                                        }
                                                 }

                                                $group_query = "SELECT groups.gid,uid,gname FROM groups JOIN group_members ON groups.gid = group_members.gid WHERE uid IN ({$ids});";

                                                $this->db_class_mysql->set_query($group_query,'users_groups',"Finds all of the group a specific user is in");
                                                $search_groups = $this->db_class_mysql->execute_query('users_groups');

                                                $group_array = '';
                                                while($res = $search_groups->fetch_assoc()){
                                                        $gname = $res['gname'];
                                                        $gid = $res['gid'];
                                                        $group_array[$res['uid']][$gid] .= $gname;
                                                }
                                                $friend_query = "SELECT fuid FROM friends WHERE uid = {$uid} AND fuid IN({$ids}) GROUP BY fuid;"; //CHANGE
                                                $this->db_class_mysql->set_query($friend_query,'friend_query',"This tells you if they're you haved them tap or not so the correct state can be set upon the page loading");
                                                $friend_res = $this->db_class_mysql->execute_query('friend_query');

                                                if($friend_res->num_rows > 0){
                                                        while($res = $friend_res->fetch_assoc()){
                                                                $fuid = $res['fuid'];
                                                                $state_array[$fuid] = 1;
                                                        }
                                                } 
                                                //Reset data_seek
                                                $search_people->data_seek(0);

                                                while($search_res = $search_people->fetch_assoc()){
                                                        //color shifter
                                                        ++$count;
                                                        $color_offset = $count & 1;
                                                        if($color_offset){$color="friend_result";} else {$color="friend_result_blue";}
                                                        //end color shifter

                                                        if($search_res['last_chat'] == NULL){
                                                                $last_chat = '<span class="notalk"> This users has not chatt\'ed yet, get them to!<span>';
                                                        } else {
                                                                $last_chat = stripslashes($search_res['last_chat']);
                                                        }

                                                        if($state_array[$search_res['uid']] == 1){
                                                                $tap_msg = "Untap <img src='images/icons/connect.png' />";
                                                                $state = 1;
                                                        }else{
                                                                $tap_msg = "Tap";
                                                                $state = 0;
                                                        }
							$path = PROFILE_PIC_REL;
                                                        $html_network_top[$count] =
                                                        <<<EOF
                                                        <div class="{$color}">
                                                                        <div class="friend_result_name"><span class="friend_result_name_span">{$search_res['fname']}  {$search_res['lname']}</span></div>

                                                                        <div class="thumbnail_friend_result"> <span class="img_bit_container"><img class="bit_img" src="{$path}{$search_res['pic_100']}" alt='blank' /></span></div>

                                                                        <div class="friend_result_info">
                                                                                <ul class="friend_result_info_list">
                                                                                        <li><span class="style_bold">Username: </span>{$search_res['uname']} </li>
                                                                                        <li><span class="style_bold">Last chat: </span>{$last_chat}</li>
                                                                                </ul>

                                                                                <ul class="result_option_list">
                                                                                        <li><span class="friend_tap_box style_bold {$state}" id="tap_{$search_res['uid']}" onclick="tap({$search_res['uid']},this.className[this.className.length-1])">{$tap_msg}</span></li>
                                                                                </ul>


                                                                        </div>

                                                                </div>
EOF;

                                                /* START of specific information tabs */
                                                $check_friend_special_chat = <<<EOF
                                                SELECT uid,mid FROM  special_chat WHERE uid = {$search_res['uid']} ORDER BY mid DESC LIMIT 10;
EOF;

                                                $this->db_class_mysql->set_query($check_friend_special_chat,'check_friend_special_chat','Getting each individual bit_set for each friend');
                                                $check_friend_bits = $this->db_class_mysql->execute_query('check_friend_special_chat');

						$friend_search_data[$search_res['uid']] = $search_res['uname'];

						//STRIP THIS LINE BELOW
						$friend_tab_list[$search_res['uid']] = <<<EOF
		                                                <li class="toggle_list_el toggle_list_friend" id="friend_{$search_res['uid']}" onclick="show_info('friend_{$search_res['uid']}','{$search_res['uname']}')" ><img  class="tab_bullet" id="friend_{$search_res['uid']}_bullet" src="images/icons/bullet_white.png" /> <span>{$search_res['uname']}</span> ( {$search_res['fname']} )</li>
EOF;

						if($check_friend_bits->num_rows > 0){

$return_list = $this->get_unique_id_list($check_friend_bits);
$uid_list = $return_list['uid_list'];
$mid_list = $return_list['mid_list'];

$friend_bits_query = <<<EOF
(SELECT t4.mid as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
JOIN special_chat as t3 
ON t3.uid = t2.uid 
LEFT JOIN (
SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$_SESSION['uid']}
) AS t4
ON t4.mid = t3.cid
WHERE t2.uid IN ( {$uid_list} ) AND t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10)                             
UNION ALL
(SELECT null as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_time) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2 
JOIN chat as t3 ON t3.uid = t2.uid WHERE t3.cid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10) ;
EOF;

//START of creating HTML bits
$html_friend_bits[$search_res['uid']] = '<div class="sub_information" id="friend_'.$search_res['uid'].'_information">';
$html_friend_bits[$search_res['uid']] .= implode(' ',$this->bit_generator($friend_bits_query,'friends_ind')).'</div>';
//END of creating HTML bits

//exit;
//Clear $mid_list and $uid_list
} else {

$html_friend_bits[$search_res['uid']] = <<<EOF
        <div class="sub_information" id="friend_{$search_res['uid']}_information">
	"Nobody has said anything in the friend {$search_res['fname']} yet, say something to spark a conversation for everyone.";
        </div>
EOF;

}
$uid_list = '';
$mid_list = '';
						}

                                        } elseif($search_people->num_rows == 0 && $_POST['people_search_button']) {
                                                echo "The search returned no results, please search again";
                                        } else {
                                        }
                                         $this->set($html_network_top,'html_'.$k_query);
}

					//STRIP 1 LINE BELOW
					 $this->set($friend_tab_list,'network_tab_list');
					 
					 $this->set($friend_search_data,'friend_search_list');
					 $this->set($html_friend_bits,'individual_friend_bits');
//END populate friends list



	$friends_query = <<<EOF

	SELECT t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid,t1.fuid FROM friends as t1 
	JOIN login as t2 
	ON t2.uid = t1.fuid
	JOIN special_chat as t3
	ON t3.uid = t1.fuid
	WHERE t1.uid = {$uid}
	ORDER BY t3.cid DESC
	LIMIT 10;

EOF;

	$html_friends_bits = $this->bit_generator($friends_query,'friends_aggr');
	$this->set($html_friends_bits,'friends_bits');



/////////////////////////////////////////////////////////

/* FROM THIS POINT ON ALL CODE IS CLEAN */

//START GROUP AGGR
$counting=0;
$groups_you_are_in->data_seek(0);
$uid_list = '';
$mid_list = '';
$old_uid = '';
while($res = $groups_you_are_in->fetch_assoc() ){
        $gid = $res['gid'];
        $gname = $res['gname'];
        $symbol = $res['symbol'];
	$slashes_gname = addslashes(addslashes(addslashes($res['gname'])));
                                $counting++;
                               if($counting !== 1){
                                        /* This if statement is here to ensure a unique list */
                                        if($gname != $old_gname) { $gname_list .= " OR chat_text LIKE '%#".$slashes_gname."%' "; }
                                } else {
                                        $gname_list .= "'%#".$slashes_gname."%'";
                                }
                                $old_gname = $gname;

	$group_search_data[$gid] = $symbol;
	//STIP ONE LINE BELOW	
	$html_group_list[$gid] = <<<EOF
	<li class="toggle_list_el toggle_list_group" id="group_{$gid}" onclick="show_info('group_{$gid}','{$slasesh_gname2}')"><img class="tab_bullet" id="group_{$gid}_bullet" src="images/icons/bullet_white.png" /> <span>{$symbol}{$connected_img}</span></li>
EOF;
}
$counting=0;

        $slashes_gname = addslashes(addslashes(addslashes($res['gname'])));
        $get_groups_bits_query = <<<EOF
                SELECT mid,uid FROM special_chat WHERE chat_text LIKE {$gname_list} ORDER BY mid DESC LIMIT 10;
EOF;

        $this->db_class_mysql->set_query($get_groups_bits_query,'group_query_ALL',"This is a SPECIAL QUERY that is part of a group of queries - This is for group: ALL ");
        $groups_bits_results = $this->db_class_mysql->execute_query('group_query_ALL');

if($groups_bits_results->num_rows > 0){

$return_list = $this->get_unique_id_list($groups_bits_results);
$uid_list = $return_list['uid_list'];
$mid_list = $return_list['mid_list'];

$groups_query_bits_info = <<<EOF
(SELECT t4.mid as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
JOIN special_chat as t3 
ON t3.uid = t2.uid 
LEFT JOIN (
SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$_SESSION['uid']}
) AS t4
ON t4.mid = t3.cid
WHERE t2.uid IN ( {$uid_list} ) AND t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10) 
UNION ALL
(SELECT null as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_time) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2 
JOIN chat as t3 ON t3.uid = t2.uid WHERE t3.cid IN ( {$mid_list} ) ORDER BY t3.cid DESC) ;
EOF;

$html_all_groups_bits = $this->bit_generator($groups_query_bits_info,'groups_aggr');
$this->set($html_all_groups_bits,'groups_bits');
//END GROUP AGGR



/* START GROUP IND */
$counting=0;
$groups_you_are_in->data_seek(0);
$uid_list = '';
$mid_list = '';
while($res = $groups_you_are_in->fetch_assoc() ){
	$gid = $res['gid'];
	$gname = $res['gname'];
	$slasesh_gname2 = addslashes($gname);
	$slashes_gname = addslashes(addslashes(addslashes($res['gname'])));
	$get_group_bits_query[$gid] = <<<EOF
		SELECT mid,uid FROM special_chat WHERE chat_text LIKE '%#{$slashes_gname}%' ORDER BY mid DESC LIMIT 10;
EOF;

	$this->db_class_mysql->set_query($get_group_bits_query[$gid],'group_query_'.$v,"This is a SPECIAL QUERY that is part of a group of queries - This is for group: {$v} ");
	$group_bits_results[$gid] = $this->db_class_mysql->execute_query('group_query_'.$v);

	//if($res['connected'] == 1){ $connected_img = ' &nbsp src="images/icons/.png" />'; } else { $connected_img = ''; } 


if($group_bits_results[$gid]->num_rows > 0){

	/* START You have to get all of the mid's first and store them in a variable for the IN clause about 14 lines down */
	$return_list = $this->get_unique_id_list($group_bits_results[$gid]);
	$uid_list = $return_list['uid_list'];
	$mid_list = $return_list['mid_list'];
	/* END */


	$group_query_bits_info = <<<EOF
	(SELECT t4.mid as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
	JOIN special_chat as t3
	ON t3.uid = t2.uid
	LEFT JOIN (
	SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$_SESSION['uid']}
	) AS t4
	ON t4.mid = t3.cid
	WHERE t2.uid IN ( {$uid_list} ) AND t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10)
	UNION ALL
	(SELECT null as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_time) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
	JOIN chat as t3 ON t3.uid = t2.uid WHERE t3.cid IN ( {$mid_list} ) ORDER BY t3.cid DESC) ;
EOF;


	//START of creating HTML bits
	$html_group_bits[$gid] = '<div class="sub_information" id="group_'.$gid.'_information">';
	$html_group_bits[$gid] .= implode(' ',$this->bit_generator($group_query_bits_info,'groups_ind')).'</div>';
	//END of creating HTML bits

} else {

	$html_group_bits[$gid] = <<<EOF
	<div class="sub_information" id="group_{$gid}_information">
EOF;
	$html_group_bits[$gid] .= "Nobody has said anything in the group <span class='no_group_act'>{$gname}</span> yet, say something to #{$gname} spark a conversation for everyone.";
	$html_group_bits[$gid] .= <<<EOF
	</div>
EOF;
}
		
	
}
} else { 
$groups_you_are_in->data_seek(0);
while($res = $groups_you_are_in->fetch_assoc() ){
	$gid = $res['gid'];
	$gname = $res['gname'];
	$slasesh_gname2 = addslashes($gname);
	$slashes_gname = addslashes(addslashes(addslashes($res['gname'])));

	$html_group_bits[$gid] = <<<EOF
	<div class="sub_information" id="group_{$gid}_information">
EOF;
	$html_group_bits[$gid] .= "Nobody has said anything in the group <span class='no_group_act'>{$gname}</span> yet, say something to spark a conversation for everyone.";
	$html_group_bits[$gid] .= <<<EOF
	</div>
EOF;
}

}
$this->set($html_group_bits,'group_bits');
//STRIP ONE LINE BELOW
$this->set($html_group_list,'group_tab_list');
$this->set($group_search_data,'group_search_list');
// END GROUP IND BITS



//START direct messaged
$uid_list = '';
$mid_list = '';
$old_uid = '';
$get_directs_bits_query = <<<EOF
                SELECT mid,uid FROM special_chat WHERE chat_text LIKE '%@{$global_uname}%' ORDER BY mid DESC LIMIT 10;
EOF;

        $this->db_class_mysql->set_query($get_directs_bits_query,'direct_query_ALL',"This is a SPECIAL QUERY that is part of a direct of queries - This is for direct: ALL ");
        $directs_bits_results = $this->db_class_mysql->execute_query('direct_query_ALL');

if($directs_bits_results->num_rows > 0){
	$return_list = $this->get_unique_id_list($directs_bits_results);
	$uid_list = $return_list['uid_list'];
	$mid_list = $return_list['mid_list'];

	$directs_query_bits_info = <<<EOF
	(SELECT t4.mid as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
	JOIN special_chat as t3 
	ON t3.uid = t2.uid 
	LEFT JOIN (
	SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$_SESSION['uid']}
	) AS t4
	ON t4.mid = t3.cid
	WHERE t2.uid IN ( {$uid_list} ) AND t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10) 
	UNION ALL
	(SELECT null as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_time) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2 
	JOIN chat as t3 ON t3.uid = t2.uid WHERE t3.cid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10) ;
EOF;

$html_all_directs_bits = $this->bit_generator($directs_query_bits_info,'directs_aggr');
}
$this->set($html_all_directs_bits,'direct_bits');	
// END direct BITS


//START active convo
$uid_list = '';
$mid_list = '';
$old_uid = '';
$get_actives_bits_query = <<<EOF
                SELECT t1.mid,t1.uid,t2.uname,t2.chat_text FROM active_convo as t1
		JOIN special_chat AS t2
		ON t1.mid = t2.mid
		WHERE t1.uid = {$uid} AND t1.active = 1 ORDER BY mid ASC;
EOF;

        $this->db_class_mysql->set_query($get_actives_bits_query,'active_convos',"This is a SPECIAL QUERY that is part of a active of queries - This is for active convos: ALL ");
        $actives_bits_results = $this->db_class_mysql->execute_query('active_convos');
if($actives_bits_results->num_rows > 0){

/*$this->db_class_mysql->set_query('SELECT FOUND_ROWS() as rows','row_count','Counting active rows');
$row_count = $this->db_class_mysql->execute_query('row_count');
$row_count = $row_count->fetch_assoc();
$row_count = $row_count['rows'];
$key_bit=$row_count;
*/
        $return_list = $this->get_unique_id_list($actives_bits_results);
        $uid_list = $return_list['uid_list'];
        $mid_list = $return_list['mid_list'];

        $actives_query_bits_info = <<<EOF
        (SELECT t4.mid as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
        JOIN special_chat as t3
        ON t3.uid = t2.uid
        LEFT JOIN (
        SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$_SESSION['uid']}
        ) AS t4
        ON t4.mid = t3.cid
        WHERE t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC)
        UNION ALL
        (SELECT null as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_time) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
        JOIN chat as t3 ON t3.uid = t2.uid WHERE t3.cid IN ( {$mid_list} ) ORDER BY t3.cid DESC) ;
EOF;

$html_all_actives_bits = $this->bit_generator($actives_query_bits_info,'actives_aggr');

$actives_bits_results->data_seek(0);
$key_bit = 0;
while($res = $actives_bits_results->fetch_assoc()){


        $bit = $html_all_actives_bits[$key_bit];
	$key_bit++;
        $key = $res['mid'];
        $convo_uname = $res['uname'];
        $convo_text = stripslashes($res['chat_text']);
        $display = $convo_uname.': '.$convo_text;
        $display = substr($display,0,20);

	/*echo htmlspecialchars($bit);	
	echo '*************** key: '.$key.' - ';
	echo 'key_bit: '.$key_bit.' <br/>';
*/
        $active_bits_tabs[$key] .= <<<EOF
        \n<li class="toggle_list_el toggle_list_active" id="active_{$key}"  onclick="show_info('active_{$key}','{$convo_uname}...')">
		\n<img class="active_close_bullet" id="active_close_{$key}_bullet" src="images/icons/bullet_delete.png" />
		\n<img class="tab_bullet" id="active_{$key}_bullet" src="images/icons/bullet_white.png" />{$display}...</li>
EOF;
        $active_bits[$key] .= <<<EOF
        \n<div class="sub_information" id="active_{$key}_information">{$bit}</div>
EOF;
}

}
$this->set($active_bits_tabs,'active_bits_tabs');
$this->set($active_bits,'active_bits');
//END active convo






//START building messaged
$uid_list = '';
$mid_list = '';
$old_uid = '';
$addr = $_SERVER['REMOTE_ADDR'];
$get_buildings_bits_query = <<<EOF
                SELECT mid,uid FROM special_chat WHERE ip = INET_ATON('{$addr}') ORDER BY mid DESC LIMIT 10;
EOF;
        $this->db_class_mysql->set_query($get_buildings_bits_query,'building_query_ALL',"This is a SPECIAL QUERY that is part of a building of queries - This is for building: ALL ");
        $buildings_bits_results = $this->db_class_mysql->execute_query('building_query_ALL');

if($buildings_bits_results->num_rows > 0){
	$return_list = $this->get_unique_id_list($buildings_bits_results);
	$uid_list = $return_list['uid_list'];
	$mid_list = $return_list['mid_list'];

	$buildings_query_bits_info = <<<EOF
	(SELECT t4.mid as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
	JOIN special_chat as t3 
	ON t3.uid = t2.uid 
	LEFT JOIN (
	SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$_SESSION['uid']}
	) AS t4
	ON t4.mid = t3.cid
	WHERE t2.uid IN ( {$uid_list} ) AND t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10) 
	UNION ALL
	(SELECT null as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_time) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2 
	JOIN chat as t3 ON t3.uid = t2.uid WHERE t3.cid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10) ;
EOF;
	$html_all_buildings_bits = $this->bit_generator($buildings_query_bits_info,'buildings_aggr');
}
$this->set($html_all_buildings_bits,'building_bits');	
// END building BITS




//START rel AGGR
$get_users_rel_settings = 'SELECT rid,tags,zip,uid,gid,enabled FROM rel_settings WHERE uid = '.$uid;

$this->db_class_mysql->set_query($get_users_rel_settings,'users_rel_settings',"This query gets the users rel settings so that they can be parsed at a later point in time ");
$rel_settings_results = $this->db_class_mysql->execute_query('users_rel_settings');

if($rel_settings_results->num_rows > 0){


$counting = 0;
while($res = $rel_settings_results->fetch_assoc() ) {
	$zip = $res['zip'];
	$slashes_tags = addslashes($res['tags']);
	$rid = $res['rid'];
	$zip_string = '';
	if($zip != 0 ){
		$slashes_tags .= ','.$res['zip'];
	}

	$counting2=0;
	$split_tags = explode(',',$slashes_tags);
	$end_array = end($split_tags);
	$begin_array = reset($split_tags);
	$tags_list[$rid] = 'SELECT mid,uid FROM special_chat WHERE ';

	foreach($split_tags as $tags){
		$logic = "OR";
		if($tags == $end_array){
			$end_paren = ')';
			if($zip != 0){	
				$logic = "AND";
				$zip_string_now = "OR ".implode(" ",$tags_list_query);
				$zip_string_now .=  "AND zip=$zip )";
			}
			
		}

		if($begin_array == $tags) { $start_paren = '('; } 

		$counting++;
		if($counting !== 1){
			if($tags != $old_tags) { $tags_list_10[] .= "{$logic} {$start_paren} chat_text LIKE '%".$tags."%' {$end_paren} {$zip_string_now} "; }
		} else {
			$tags_list_10[] .= "( chat_text LIKE '%".$tags."%' {$end_paren}";
		}
			

		$counting2++;
		if($counting2 !== 1){
			/* This if statement is here to ensure a unique list */
			if($tags != $old_tags) {
				$tags_list[$rid] .= "{$logic} {$start_paren} chat_text LIKE '%".$tags."%'  {$end_paren} {$zip_string_now} "; 
				$tags_list_query[] .= "{$logic} {$start_paren} chat_text LIKE '%".$tags."%'  {$end_paren} {$zip_string_now}";
			}
		} else {
			$tags_list[$rid] .= "( chat_text LIKE '%".$tags."%' {$end_paren}";
			$tags_list_query[] .= "( chat_text LIKE '%".$tags."%' {$end_paren}";
		}
		$old_tags = 'ZXZDSHJZEBRA';
		$start_paren = '';
		$zip_string_now = '';
	}
		$tags_list_query = '';
		$end_paren = '';
	$tags_list[$rid] .= ' ORDER BY mid DESC LIMIT 10;';
}


$tags_list_10_string = implode($tags_list_10);

$rel_query_all = <<<EOF
SELECT mid,uid FROM special_chat WHERE {$tags_list_10_string} ORDER BY mid DESC LIMIT 10;
EOF;

	$this->db_class_mysql->set_query($rel_query_all,'rel_query_all',"This is query gets all of the relevant queries LIMIT 10 for filters");
	$rel_results_all = $this->db_class_mysql->execute_query('rel_query_all');

if($rel_results_all->num_rows > 0){

	/* START You have to get all of the mid's first and store them in a variable for the IN clause about 14 lines down */
	$return_list = $this->get_unique_id_list($rel_results_all);
	$uid_list = $return_list['uid_list'];
	$mid_list = $return_list['mid_list'];
	/* END */

	$rel_query_all_final = <<<EOF
        SELECT t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
        JOIN special_chat as t3
        ON t3.uid = t2.uid
        WHERE t2.uid IN
        ( {$uid_list} )
        AND t3.mid IN
        ( {$mid_list} )
        ORDER BY t3.cid DESC
        LIMIT 10;
EOF;

	$rel_query_all_final = <<<EOF
	(SELECT t4.mid as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
	JOIN special_chat as t3
	ON t3.uid = t2.uid
	LEFT JOIN (
	SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$_SESSION['uid']}
	) AS t4
	ON t4.mid = t3.cid
	WHERE t2.uid IN ( {$uid_list} ) AND t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10)
	UNION ALL
	(SELECT null as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_time) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
	JOIN chat as t3 ON t3.uid = t2.uid WHERE t3.cid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10) ;
EOF;

$html_all_rel = $this->bit_generator($rel_query_all_final,'rel_aggr');
$this->set($html_all_rel,'rel_bits');	
//END rel AGGR


/* START rel IND */
	foreach($tags_list as $k => $query){
		$rid = $k;
		$uid_list = '';
		$mid_list = '';
		$counting = 0;
	
		$this->db_class_mysql->set_query($query,'rel_query_'.$k,"This is a SPECIAL SET of queries, getting each individual relevancy setting");
		$rel_results_individual = $this->db_class_mysql->execute_query('rel_query_'.$k);

		if($rel_results_individual->num_rows > 0 ) {

		        while($res = $rel_results_individual->fetch_assoc()) {
				$mid = $res['mid'];
				$uid = $res['uid'];
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

	$rel_query_individual_final = <<<EOF
	(SELECT t4.mid as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
	JOIN special_chat as t3
	ON t3.uid = t2.uid
	LEFT JOIN (
	SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$_SESSION['uid']}
	) AS t4
	ON t4.mid = t3.cid
	WHERE t2.uid IN ( {$uid_list} ) AND t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10)
	UNION ALL
	(SELECT null as good_id,t3.special,UNIX_TIMESTAMP(t3.chat_time) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
	JOIN chat as t3 ON t3.uid = t2.uid WHERE t3.cid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10) ;
EOF;

	//START of creating HTML bits
	$html_individual_rel_bits[$rid] = '<div class="sub_information" id="tab_rel_'.$rid.'_information">';
	$html_individual_rel_bits[$rid] .= implode(' ',$this->bit_generator($rel_query_individual_final,'rel_ind')).'</div>';
	//END of creating HTML bits


			} else {

			$html_individual_rel_bits[$rid] = <<<EOF
                                                <div class="sub_information fail" id="tab_rel_{$rid}_information">
						<img src="images/icons/error.png" /> This relevancy setting has no matches currently, if you would like, feel free to go <a href="relevancy_settings">here</a> in order to tweak your settings to match things currently comming in
i
						</div>
EOF;
			}
	}
	// END of foreach($taglist)
} else {
	foreach($tags_list as $k => $query){
	$rid = $k;
	$html_individual_rel_bits[$rid] = <<<EOF
							<div class="sub_information fail" id="tab_rel_{$rid}_information">
							<img src="images/icons/error.png" /> This relevancy setting has no matches currently, if you would like, feel free to go <a href="relevancy_settings">here</a> in order to tweak your settings to match things currently comming in
							</div>
EOF;
	}
}
	$this->set($html_individual_rel_bits,'individual_rel_bits');
}


/////////////////////////////////////////////////////////////////////////////
//START misc tasks - Including, getting max file id, spnning off process, etc
/////////////////////////////////////////////////////////////////////////////
$groups_you_are_in->data_seek(0);

$this->db_class_mysql->set_query('SELECT MAX(mid) AS mid FROM chat','max_mid_query',"Gets max mid for poller");
$max_mid_results = $this->db_class_mysql->execute_query('max_mid_query');
$res = $max_mid_results->fetch_assoc();
$max_mid = $res['mid'];
$this->set($max_mid,'max_mid');

$uid = $_SESSION['uid'];
$push_channel_id = rand(1,888).'Xic'.$uid.'R';
$this->set($push_channel_id,'pcid');


$this->db_class_mysql->set_query('UPDATE TEMP_ONLINE SET timeout = 0,cid = "'.$push_channel_id.'" WHERE uid = '.$uid.';','TEMP_ONLINE_UPDATE','UPDATES users TEMP_ONLINE status');
$TEMP_ONLINE_results = $this->db_class_mysql->execute_query('TEMP_ONLINE_UPDATE');
$this->db_class_mysql->set_query('INSERT INTO TEMP_ONLINE(uid,cid) values('.$uid.',"'.$push_channel_id.'");','TEMP_ONLINE_INSERT','INSERTS users TEMP_ONLINE status');
$TEMP_ONLINE_results = $this->db_class_mysql->execute_query('TEMP_ONLINE_INSERT');


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
		}

	}


private function bit_generator($query,$type){
//	echo $query."\n"."\n"."<br/>"."<br/>";
	$pic_path = PROFILE_PIC_REL;
	$counter = 0;
	$this->db_class_mysql->set_query($query,'bit_gen_query',"This gets the initial lists of bits of type: {$type}");
	$bit_gen_results = $this->db_class_mysql->execute_query('bit_gen_query');

			$num_rows = $bit_gen_results->num_rows;
                        while($res = $bit_gen_results->fetch_assoc() )
					if(!$res['special'])
					$resp_count[$res['cid']][0] = $resp_count[$res['cid']][0] + 1;

			$bit_gen_results->data_seek(0);
                        while($res = $bit_gen_results->fetch_assoc() ){
				$num_rows--;
				$chat_timestamp = $this->time_since($res['chat_timestamp']);
				$chat_timestamp = ($chat_timestamp == "0 minutes") ? "Seconds ago" : $chat_timestamp." ago";
                                $pic_36 = $res['pic_36'];
				$uid = $res['uid'];
                                $color_counter++;
                                $chat_text = stripslashes($res['chat_text']);
                                $cid = $res['cid'];
                                $uname = $res['uname'];
                                $fname = $res['fname'];
                                $lname = $res['lname'];
                                $pic_100 = $res['pic_100'];
				$special = $res['special'];
				$good_id = $res['good_id'];
				$rand = rand(1,999);

				if(!$good_id){
					$good = <<<EOF
<li class="0 good" id="good_{$cid}_{$type}" onclick="good(this,'{$cid}','{$uid}','{$_SESSION['uid']}','{$type}');"><img src="images/icons/thumb_up.png" /> <span class="bits_lists_options_text"></span></li>
EOF;
				} else {
					$good = <<<EOF
<li class="0" id="good_{$cid}_{$type}" ><img src="images/icons/accept.png" /></li>
EOF;
				}

				if(!$special){
					$resp_html[$cid][] .= <<<EOF
					<li class="responses"><img class="response_img" src="{$pic_path}{$pic_36}" /><span class="response_text">{$uname}: {$chat_text}</span></li>
EOF;
				}

				if($special){
	                                if($color_counter & 1){ $color_class = "blue"; } else { $color_class = "red"; }

				$resp_msg = '';
				if($resp_count[$cid][0] > 0)
					$resp_msg = $resp_count[$cid][0]." Replies";

                                $final_html[$cid] = <<<EOF
<div id="super_bit_{$cid}_{$type}_{$rand}">
<div class="bit {$color_class} {$cid}_bit" id="bit_{$cid}_{$type}_{$rand}">

        <span class="bit_img_container"><img class="bit_img" src="{$pic_path}{$pic_100}" /></span>
        <span class="bit_text">
                <a href="profile">{$uname}</a> {$chat_text}
        </span>
	<span class="bit_timestamp"><i>{$chat_timestamp}</i></span>
	<ul class="bits_lists_options">
		<li><span class="{$cid}_resp_notify resp_notify">{$resp_msg}</span></li>
		{$good}
		<li class="0 good" onclick="toggle_show_response('_{$cid}_{$type}_{$rand}',this);"><img src="images/icons/comment.png" /> <span class="bits_lists_options_text"></span></li>
	</ul>

</div>

<div class="respond_text_area_div" id="respond_{$cid}_{$type}_{$rand}">
<ul>
	<li><textarea class="textarea_response gray_text" id="textarea_response_{$cid}" onfocus="if (this.className[this.className.length-1] != '1') vanish_text('textarea_response',this);">Response..</textarea></li>
	<li><button>Send</button></li>
</ul>

</div>

        <ul class="bit_responses {$cid}_resp" id="responses_{$cid}_{$type}_{$rand}">
EOF;
				}
if($num_rows <= 0){
	foreach($final_html as $k => $v){
		if(is_array($resp_html[$k])){
		foreach($resp_html[$k] as $k2 => $v2){
			$final_html[$k] .=  $v2;
		}
		} else { 
		$final_html[$k] .= <<<EOF
	<li class="responses">No replies...yet</li>
EOF;
		}


		$resp_mid_max = 'NEED';
		$final_html[$k] .= <<<EOF
	</ul>
	</div>
	<span class="response_mid_max" id="time_{$k}">{$resp_mid_max}</span>
EOF;
		$bit_gen_html_results[] .= $final_html[$k];
		}
}
}
return $bit_gen_html_results;

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

private function get_unique_id_list($mysql_object){
	while($res = $mysql_object->fetch_assoc() ){
	$mid = $res['mid'];
	$uid = $res['uid'];
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
	'mid_list' => $mid_list,
	'uid_list' => $uid_list
	);
}

}
?>
