<?php

class profile extends Base{

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
		$this->page_name = "profile";
		$this->need_auth = 1;
		$this->need_db = 1;
		
	
		parent::__construct();
		
		$uid = $_SESSION['uid'];
		
		$friends_last_chat = <<<EOF
                SELECT t2.pic_100,t2.uid,t2.uname,t2.fname,t2.lname,t4_sub.chat_text AS last_chat FROM friends AS t1
                JOIN
                (
                 SELECT uid,mid,chat_text FROM special_chat AS t4 ORDER BY mid DESC
                ) AS t4_sub ON t4_sub.uid = t1.fuid
                JOIN login AS t2 ON t1.fuid = t2.uid
                WHERE t1.uid = {$uid} GROUP BY t1.fuid; 
EOF;
	
		$this->db_class_mysql->set_query($friends_last_chat,'get_last_chat','This query gets everything the users has on tap last said');
	
		$this->db_class_mysql->set_query('SELECT * FROM profile WHERE uid='.$uid.';','get_profile','This is getting the users profile contents');
		$this->db_class_mysql->set_query('SELECT * FROM display_rel_profile WHERE uid='.$uid.';','get_rel_profile','This is getting the users profile contents');
		$this->db_class_mysql->set_query('SELECT uname,pic_180 FROM login WHERE uid='.$uid.';','get_login','This is getting the users login contents');
		$this->db_class_mysql->set_query('SELECT rank FROM rankings WHERE uid='.$uid.';','get_ranking','This is getting the users ranking contents');
		$this->db_class_mysql->set_query('SELECT COUNT(*) AS count FROM friends WHERE uid='.$uid.';','get_friends','This is getting the users ranking contents');
		$this->db_class_mysql->set_query('SELECT COUNT(*) AS count FROM special_chat WHERE uid='.$uid.';','get_postings','This is getting the users ranking contents');
		$this->db_class_mysql->set_query('SELECT COUNT(*) AS count FROM chat WHERE uid='.$uid.';','get_chat','This is getting the users ranking contents');
		$this->db_class_mysql->set_query('SELECT t1.pic_36,t1.gname,t1.gid FROM groups AS t1 JOIN group_members ON t1.gid = group_members.gid WHERE uid ='.$uid.';','get_groups','This is getting the users ranking contents');
			$this->db_class_mysql->set_query('SELECT SQL_CALC_FOUND_ROWS rank,cat FROM rankings WHERE uid ='.$uid.';','get_rankings','This is getting the users ranking contents');
		

			$last_chat_results = $this->db_class_mysql->execute_query('get_last_chat');
			$profile_results = $this->db_class_mysql->execute_query('get_profile');
			$rel_profile_results = $this->db_class_mysql->execute_query('get_rel_profile');
			$login_results = $this->db_class_mysql->execute_query('get_login');
			$ranking_results = $this->db_class_mysql->execute_query('get_ranking');
			$friends_results = $this->db_class_mysql->execute_query('get_friends');
			$posting_results = $this->db_class_mysql->execute_query('get_postings');
			$chat_results = $this->db_class_mysql->execute_query('get_chat');
			$group_results = $this->db_class_mysql->execute_query('get_groups');
			$rankings_results = $this->db_class_mysql->execute_query('get_rankings');
			
				$profile_results = $profile_results->fetch_assoc();
				$rel_profile_results = $rel_profile_results->fetch_assoc();
				$login_results = $login_results->fetch_assoc();
				$ranking_results = $ranking_results->fetch_assoc();
				$friends_results = $friends_results->fetch_assoc();
				$posting_results = $posting_results->fetch_assoc();
				$chat_results = $chat_results->fetch_assoc();
				
		
				if($ranking_results['rank'] == '0' || NULL){
					$ranking_results['rank'] = '<span class="null_attr">No Ranking</span>';
				}
				
				if($friends_results['count'] <= 0){
					$friends_results['count'] = '<span class="null_attr">You have no friends</span>';
				}
				
				if($posting_results['count'] <= 0){
					$posting_results['count'] = '<span class="null_attr">You have not used tap, try it out!</span>';
				}
				
				if($chat_results['count'] <= 0){
					$chat_results['count'] = '<span class="null_attr">You have not chattap\'d</span>';
				}
				
		$this->set($last_chat_results,'last_chat');
		$this->set($profile_results,'profile');
		$this->set($rel_profile_results,'rel_profile');
		
		$this->set($login_results,'login');
		$this->set($ranking_results,'rank');
		$this->set($rankings_results,'rankings');
		
		$this->set($friends_results,'friends');
		$this->set($posting_results,'postings');
		$this->set($chat_results,'chat');
		$this->set($group_results,'groups');
		

	}

	function test(){

	}
	
}


?>
