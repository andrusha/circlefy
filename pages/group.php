<?php

class group extends Base{

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
		$this->page_name = "group";
		$this->need_auth = 1;
		$this->need_db = 1;
		
	
		parent::__construct();
		
		$uid = $_SESSION['uid'];

		$gid = $_GET['group'];
		
//Create all of the queries for every task
		$group_status_query = <<<EOF
		SELECT t1.admin,t1.gid,t1.uid,t1.tapd,t1.inherit FROM group_members AS t1 WHERE t1.uid = {$uid} AND t1.gid = {$gid};
EOF;

/* OLD QUERY $last_chat_query


		 SELECT t1.uid,t1.uname,t1.fname,t1.lname,t4.chat_text AS last_chat FROM login AS t1 
		 JOIN group_members AS t3 ON t1.uid=t3.uid 
		 LEFT JOIN special_chat AS t4 ON t1.uid = t4.uid 
		 WHERE t3.gid={$gid} GROUP BY uid ;
	
*/
		 $last_chat_query = <<<EOF
		SELECT t1.uid,t1.uname,t1.fname,t1.lname,t4_sub.chat_text AS last_chat FROM login AS t1 JOIN group_members AS t3 ON t1.uid=t3.uid 
		JOIN (
		 SELECT t4.mid, t4.chat_text,t4.uid FROM special_chat AS t4 ORDER BY mid DESC 
		 ) AS t4_sub ON t4_sub.uid = t1.uid
		 WHERE t3.gid={$gid} GROUP BY uid;

		
EOF;

		$query_admins = <<<EOF
		 SELECT t2.admin,t1.pic_36,t1.uname FROM login as t1 JOIN group_members as t2 ON t1.uid = t2.uid WHERE t2.admin IN(1,2,3) AND t2.gid = {$gid} ORDER BY admin;
EOF;

		$get_rel_query = <<<EOF
		SELECT * FROM rel_settings WHERE gid = {$gid} AND uid = 0
EOF;

		$get_group_info = <<<EOF
		SELECT picture_path, private, invite_priv, invite_only, descr, focus, gname, pic_180 FROM groups WHERE gid = {$gid}
EOF;

//Set all of the queries
		$this->db_class_mysql->set_query($group_status_query,'get_group_status','This query lets you know if th user has the specific group tapd/inherited tags');
		$this->db_class_mysql->set_query($last_chat_query,'get_last_chat','This query gets a list of the admins for a specific group');
		$this->db_class_mysql->set_query($query_admins,'get_admins','This query gets a list of people who last chatted who are in the group ( however this might wnat to be modified tow/ some filters');
                $this->db_class_mysql->set_query($get_rel_query,'get_rel','This gets the users initial settings');
		$this->db_class_mysql->set_query($get_group_info,'get_group_info','This gets all of the basic information about the group ( picture, descr, focus, name, private / invite status )');

//Execute Each query
                $get_group_status_result = $this->db_class_mysql->execute_query('get_group_status');
                $get_last_chat_result = $this->db_class_mysql->execute_query('get_last_chat');
                $get_admins_result = $this->db_class_mysql->execute_query('get_admins');
                $get_rel_result = $this->db_class_mysql->execute_query('get_rel');
                $get_info_result = $this->db_class_mysql->execute_query('get_group_info');

//Process each query
		if($get_group_status_result->num_rows > 0){
			//If the program enters this loop that means the user IS apart of the group currently
			$joined = True;
			while($status_output = $get_group_status_result->fetch_assoc()){
			$admin_status = $status_output['admin'];
			$gid = $status_output['gid'];
				$tap_status = ( $status_output['tapd'] == 1 ) ? "enable" : "disable";
				$inherit_status = ( $status_output['inherit'] == 1 ) ? "enable" : "disable";
				$tap_display = ( $status_output['tapd'] == 1 ) ? "Yes" : "No";
				$inherit_display = ( $status_output['inherit'] == 1 ) ? "Yes" : "No";

			$html_status = <<<EOF
				 <ul id="group_status">
					<li><b>Click to change:</b></li>
					<li >Tap:
<span class="group_status_tapd_{$tap_status} {$status_output['tapd']}" id="group_tap"  onclick="update_enable_status('tapd',{$gid},this.className[this.className.length-1]);">{$tap_display}</span>
					</li>
					<li >Inherit Tags:
					<span class="group_status_inherit_{$inherit_status} {$status_output['inherit']}" id="group_inherit"  onclick="update_enable_status('inherit',{$gid},this.className[this.className.length-1]);">{$inherit_display}</span>
					</li>
				</ul>
EOF;

			}
		} else {
			//If the program enters this loop that means the user IS NOT apart of the group currently
			$joined = False;
			$admin_status = 0;
			$gid = substr($_SERVER['REQUEST_URI'],-1);
			$html_status = '<div onclick="join_group('.$gid.',this);" id="join_group_logo"><img src="/rewrite/images/join.gif" alt="join" /></div>';
			$gid = '';
		}
		$this->set($admin_status,'admin_status');
		$this->set($gid,'gid');
		$this->set($html_status,'html_status');

                if($get_rel_result->num_rows > 0){
			while($get_rel_output = $get_rel_result->fetch_assoc()){
				$counter++;
			
				//Translate all of the results
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

				$rel_string = $keywords.$lang.$country.$zip;
				$rid = $get_rel_output['rid'];
				$name = stripslashes($get_rel_output['name']);

				$bg = $counter & 1;

				if($bg == 1){ $bg = 'white'; } else { $bg = 'blue'; }

				if($admin_status)
				$delete = <<<EOF
					<td class="delete_rel"><a href="#" onclick='del_rel({$rid});'>Delete</a></td>
EOF;
				if($admin_status){
				$enable = <<<EOF
					<td class="enable_{$enable} {$state}" id="state_{$rid}" onclick="update_enable({$rid},this.className[this.className.length-1]);">{$enable_txt}</td>
EOF;
				} else {
				$enable = <<<EOF
					<td class="enable_{$enable} {$state}" id="state_{$rid}">{$enable_txt}</td>
EOF;
				}
						
				$html_results[$counter] = <<<EOF
			<tr class="rel_rid_{$bg}" id="rel_{$rid}">
						<td class="rel_name_number">{$counter}. {$name}</td>
						<td class="active_rel">{$tags}</td>
						<td class="active_loc">{$zip}</td>
						<td class="active_group">This Group!</td>
						{$enable}
						{$delete}
			</tr>

EOF;
			 }
		$this->set($html_results,'html_rel');
	}

	while($res = $get_last_chat_result->fetch_assoc()){
		$last_chat_html[] = <<<EOF
			<li> {$res['uname']}: {$res['last_chat']} </li>

EOF;
	}
	$this->set($last_chat_html,'html_last_chat');

	while($res = $get_admins_result->fetch_assoc()){
		$status = $res['admin'];
		switch($status){
			case 1:
				$status = "Group Owner";
				break;
			case 2:
				$status = "Group Admin";
				break;
			default:
				$status = "";
				break;
        	}

		
		$admin_html[] = <<<EOF
		<li ><img id="edit_profile_picture" src="../pictures/{$res['pic_36']}" alt='blank' /> <span class="admin_info">{$res['uname']} - {$status}</span></li>
EOF;
	}
	$this->set($admin_html,'html_admin');

	while($res = $get_info_result->fetch_assoc() ){
		$pic_180 = $res['pic_180'];
		$pic_path = $res['picture_path'];
		$priv = $res['invite_priv'];
		$private = $res['private'];
		$invite = $res['invite_only'];
		$rel_priv = $res['rel_priv'];
		$descr = $res['descr'];
		$focus = $res['focus'];
		$gname = $res['gname'];


		switch($rel_priv){
			case 0:
				$rel_priv="Anyone can change/add/remove relevancy settings";
				break;
			case 1:
				$rel_priv="Only administrators can change/add/remove relevancy settings";
				break;
		}

		switch($priv){
			case 0:
				$priv="Anyone can invite to this group";
				break;
			case 1:
				$priv="Only owners/admins can invite to this group";
				break;
		}
	
		switch($invite){
			case 0:
				$invite = "This group is not invite only";
				break;
			case 1:
				$invite = "This group is invite only";
				break;
		}

		switch($private){
			case 0:
				$private = "This group is not private";
				break;
			case 1:
				$private = "This group is private and will not show up in searches and/or the web";
				break;
		}

		$html_descr = <<<EOF
		                <li class="group_descr_list" id="group_rel_priv"><span class="style_bold">Relevancy Privileges: </span>{$rel_priv}</li>
		                <li class="group_descr_list" id="group_priv"><span class="style_bold">Invite Privileges: </span>{$priv}</li>
                                <li class="group_descr_list" id="group_invite"><span class="style_bold">Invite: </span>{$invite}</li>
                                <li class="group_descr_list" id="group_private"><span class="style_bold">Privacy: </span>{$private}</li>
EOF;
		$html_descr2 = <<<EOF
                                <li class="group_descr_list" id="group_focus"><span class="style_bold">Focus: </span>{$focus}</li>
                                <li class="group_descr_list" id="group_descr_list"><span class="style_bold">Description: </span>{$descr}</li>
EOF;
		}

		
		$this->set($pic_180,'pic_180');
		$this->set($gname,'group_name');
		$this->set($html_descr,'html_descr');
		$this->set($html_descr2,'html_descr2');

	}
}
?>
