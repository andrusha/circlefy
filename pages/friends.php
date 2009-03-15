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
		$this->page_name = "homepage";
		$this->need_login = 1;
		$this->need_db = 1;
	
		parent::__construct();
		
		$this->db_class_mysql->set_query('Select Sleep(3);','Query','This is for X');
		$this->db_class_mysql->set_query('Select * From User','Query4','This is for y',1);
		
		//$result = $this->db_class_mysql->execute_query('Query4')
	jfaoijfoifj	
		$uid = $_SESSION['uid'];
		
		$query = <<<EOF

		SELECT * FROM friends AS t1 i
		JOIN login AS t2 ON t1.uid = t2.uid 
		WHERE t1.uid = {$uid}
EOF;

		 $this->db_class_mysql->set_query($query,'find_users',"Updating a users alert settings on his profile");	
		 $search_people = $this->db_class_mysql->execute_query('find_users');

						while($search_res = $search_people->fetch_assoc()){
							//color shifter
							++$count;
							$color_offset = $count & 1;
                                                        if($color_offset){$color="friend_result";} else {$color="friend_result_blue";}
                                                        //end color shifter

                                                        if($search_res['last_chat'] == NULL){
                                                                $last_chat = '<span class="notalk"> This users has not chatt\'ed yet, get them to!<span>';
                                                        } else { 
                                                                $last_chat = $search_res['last_chat'];
                                                        }

                                                        if($state_array[$search_res['uid']] == 1){
                                                                $tap_msg = "Untap";
                                                                $state = 1;
                                                        }else{
                                                                $tap_msg = "Tap";
                                                                $state = 0;
                                                        }


                                                        $res[$count] =
                                                        <<<EOF
                                                        <div class="{$color}">
                                                                        <div class="friend_result_name"><span class="friend_result_name_span">{$search_res['fname']}  {$search_res['lname']}</span></div>

                                                                        <div class="thumbnail_friend_result">Test</div>

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
                                                                                                $res[$count] .= '<li><a href="/groups/">'.$v.'</a></li>';
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

                                        } elseif($search_people->num_rows == 0 && $_POST['people_search_button']) {
                                        } else {
                                        }
		
		

	}

	function test(){

	}
	
}
?>
