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
			$this->db_class_mysql->set_query('SELECT t2.gname,t1.gid FROM group_members AS t1 JOIN groups AS t2 ON t2.gid=t1.gid WHERE t1.uid='.$uid,
					'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
					$groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');
					$this->set($groups_you_are_in,'your_groups');
		
		$search = $_POST['people_search_button'];
		$fname = $_POST['first_name'];
		$lname = $_POST['last_name'];
		$zipcode = $_POST['zipcode'];
		$group = $_POST['group'];
	
		
		
		if($search){
					//This is all dynamic query generation!! ( Hence the code looks a bit crazy ) 

		
					//if the last name is specified tag this on to the end of the query
					if($fname){ $query_array['fname'] = ' t1.fname = "'.$fname.'"'; }
					if($lname){ $query_array['lname'] = ' t1.lname ="'.$lname.'"'; }
					/*if($group){
						$join['group_q1'] = 't1.gname FROM groups AS t1 INNER JOIN group_members AS t2 ON t1.gid';
}*/
					if($zipcode) { 
						$join['zip_q1'] = 'JOIN display_rel_profile AS t2 ON t2.uid = t1.uid';
						$query_array['zip_q2'] = ' t2.zip="'.$zipcode.'"';
					}
					
					if($group != "NULL")  {
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

					
					$query = 
					
						<<<EOF
					
						SELECT t1.pic_100,t1.uid,t1.uname,t1.fname,t1.lname,t4_sub.chat_text AS last_chat
						FROM login AS t1 {$join['zip_q1']} {$join['group_q1']} {$join['last_chat']}
						WHERE {$query_array['fname']} {$query_array['lname']} {$query_array['zip_q2']} {$query_array['group_q2']} {$query_array['last_chat']}
						
EOF;
			
					$this->db_class_mysql->set_query($query,'find_users',"Updating a users alert settings on his profile");
					$search_people = $this->db_class_mysql->execute_query('find_users');

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
#						$group_query = "SELECT uid,gname FROM groups JOIN group_members ON groups.gid = group_members.gid WHERE uid IN ({$ids});";

						$this->db_class_mysql->set_query($group_query,'users_groups',"Finds all of the group a specific user is in");
						$search_groups = $this->db_class_mysql->execute_query('users_groups');
	
						while($res = $search_groups->fetch_assoc()){
							$gname = $res['gname'];
                                                        $gid = $res['gid'];
                                                        $group_array[$res['uid']][$gid] .= $gname;
						}

						$friend_query = "SELECT fuid FROM friends WHERE uid = {$uid} AND fuid IN({$ids});";
						$this->db_class_mysql->set_query($friend_query,'friend_query',"This tells you if they're you haved them tap or not so the correct state can be set upon the page loading");
						$friend_res = $this->db_class_mysql->execute_query('friend_query');
						
						if($friend_res->num_rows > 0){
							while($res = $friend_res->fetch_assoc()){
								$fuid = $res['fuid'];
								$state_array[$fuid] = 1;
							}					
						}

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
								$tap_msg = "<span class='tap_box'>Untap <img src='images/icons/connect.png' /></span>";
								$state = 1;
							}else{
								$tap_msg = "<span class='tap_box'>Tap <img src='images/icons/disconnect.png' /></span>";
								$state = 0;
							}
							
	
							$res[$count] =
							<<<EOF
							<div class="{$color}">
									<div class="friend_result_name"><span class="friend_result_name_span">{$search_res['fname']}  {$search_res['lname']}</span></div>

									<div class="thumbnail_friend_result"> <img id="edit_profile_picture" src="pictures/{$search_res['pic_100']}" alt='blank' /></div>

									<div class="friend_result_info">
										<ul class="result_info_list">
											<li><span class="style_bold">Username: </span>{$search_res['uname']} </li>
											<li><span class="style_bold">Last chat: </span>{$last_chat}</li>
										</ul>

										<ul class="result_info_list">
												<li><span class="style_bold">Groups:</span></li>
EOF;
										if(is_array($group_array[$search_res['uid']])){
											foreach($group_array[$search_res['uid']] as $v => $k){
                                                                                                $res[$count] .= '<li><a href="group/'.$v.'">'. $k.'</a></li>';
                                                                                        }
										} else {	
												$res[$count] .= "This member is in no groups, get them to join some!";
										}
							$res[$count] .=
							<<<EOF
										</ul>

										<ul class="result_option_list">
											<li><span class="style_bold {$state}" id="tap_{$search_res['uid']}" onclick="tap({$search_res['uid']},this.className[this.className.length-1])">{$tap_msg}</span></li>
										</ul>


									</div>

								</div>
EOF;
                                		}
					$this->set($res,'html');

                			} elseif($search_people->num_rows == 0 && $_POST['people_search_button']) {
			                        $res = '--- <span id="no_people_search_results">The search returned no results, please search again</span> ---';
						$this->set($res,'no_results');
			                } else {
                        			echo "Search for your friends!";
			                }
		}
	}

	function test(){

	}

}
?>
