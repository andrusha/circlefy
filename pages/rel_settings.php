<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class rel_settings extends Base{

	protected $text;
	protected $top;

	function __default(){
	}

	public function __toString(){
		return "Relevancy Object";
	}

	function __construct(){

		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->page_name = "rel_settings";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();

		$uid = $_SESSION['uid'];
	
		$group_query = <<<EOF
                        SELECT t2.gname,t1.gid,t1.connected FROM group_members AS t1 
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
			if($get_rel_output['gid'] != 0){
					$groups_IN_res[$get_rel_output['rid']] .= $get_rel_output['gid'];
				$group_exists = 1;
				}
		}

		if($group_exists){
			//Build query for groups
			if($groups_IN_res){
			foreach($groups_IN_res as $k => $v){
				 $counting++;
				if($counting !== 1){
					$group_list .= ','.$v;
				} else { 
					$group_list .= $v;
				}
			}
			} else {
				$group_list = "NULL";
			}
			$counting = 0;


			$get_groups_query = <<<EOF
			SELECT gid,gname FROM groups
			WHERE gid IN ({$group_list}) 
EOF;

			$this->db_class_mysql->set_query($get_groups_query,'get_rel_groups','This gets the groups that are then associated to an rid ( or relevacny id )');
			$get_groups_res = $this->db_class_mysql->execute_query('get_rel_groups');

				
			while($res = $get_groups_res->fetch_assoc()){	
				if($group_list != "NULL")
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
		}
}
		$this->set($html_results,'get_rel_results');

	}

	function test(){


	}

}
/*

                        Catagory: <span class="style_bold">Food/Cooiahfoiafjoaifja oijfa oij oifa jaoi afoifa oioking</span> in state <span class="style_bold">NY</span> in zipcode <span class="style_bold">10522</span> in language <span class="style_bold">English</span> Keywords: <span class="style_bold">Maple, Syrup, Pancakes, mmm</span>
<span class="rel_rid" id="rel_{$rid}">
                                <span class="rel_name_number">{$counter}. {$name}</span>
                                <span class="active_rel">
                                {$rel_string}
                                </span>
                                <span class="delete_rel"><a href="#" onclick='del_rel({$rid});'>Delete</a></span>
                        </span>


*/
?>
