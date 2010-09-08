<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class settings extends Base{

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
		$this->page_name = "about_me";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();

		$uid = $_SESSION['uid'];
		//Takes awayfist settings flag
		setcookie('profile_edit','',time()-360000);

			$submit = $_POST['submit'];
			if($submit){
				$join_group = $_POST['join_group'];
				$track = $_POST['track'];
				$autotrack = $_POST['autotrack'];
				$respond = $_POST['respond'];

				$update_settings_query = <<<EOF
				UPDATE settings AS s SET s.autotrack = {$autotrack}, s.join_group = {$join_group}, s.track = {$track},s.email_on_response = {$respond} WHERE s.uid = {$uid}
EOF;
				$this->db_class_mysql->set_query($update_settings_query,'update_settings','This query updates thes users settings');
				$update_settings_results = $this->db_class_mysql->execute_query('update_settings');
				$this->set(True,'settings_updated');
			}
			//START get settings
			$users_settings_query = <<<EOF
			SELECT s.autotrack,s.join_group,s.track,s.email_on_response FROM settings AS s WHERE s.uid = {$uid}
EOF;
			$this->db_class_mysql->set_query($users_settings_query,'user_settings','Counts amount of messages a user has for a stat');
                        $users_settings_results = $this->db_class_mysql->execute_query('user_settings');
	
			// FIXME: Why is this While here? We should have 1 settings row per user! (Ignacio.-)
			while($res = $users_settings_results->fetch_assoc()){
				$email_on_response = $res['email_on_response'];
				$track = $res['track'];
				$autotrack = $res['autotrack'];
				$join_group = $res['join_group'];
				$settings_data[] = array(
					'respond' => $email_on_response,
					'track' => $track,
					'autotrack' => $autotrack,
					'join_group' => $join_group
				);
			} 
			$this->set($settings_data,'settings');

			$count_messages = <<<EOF
			SELECT COUNT(*) AS message_count FROM special_chat WHERE uid = {$uid}
EOF;
			$this->db_class_mysql->set_query($count_messages,'count_messages','Counts amount of messages a user has for a stat');
			$count_results = $this->db_class_mysql->execute_query('count_messages');
			if($count_results->num_rows)
			$res = $count_results->fetch_assoc();
				$message_count = $res['message_count'];

			$count_resp = <<<EOF
			SELECT COUNT(*) AS resp_count FROM chat WHERE uid = {$uid}
EOF;

			$this->db_class_mysql->set_query($count_resp,'count_resp','Counts amount of responses a user has for a stat');
			$count_results = $this->db_class_mysql->execute_query('count_resp');
			if($count_results->num_rows)
			$res = $count_results->fetch_assoc();
                                $resp_count = $res['resp_count'];

			$count_group = <<<EOF
                        SELECT COUNT(*) AS group_count FROM group_members WHERE uid = {$uid}
EOF;
                        $this->db_class_mysql->set_query($count_group,'count_group','Counts amount of groups a user has for a stat');
                        $count_results = $this->db_class_mysql->execute_query('count_group');
			if($count_results->num_rows)
                        $res = $count_results->fetch_assoc();
                                $group_count = $res['group_count'];
	
			if(!$message_count)
				$message_count = 0;
			if(!$resp_count)
				$resp_count = 0;
			if(!$group_count)
				$group_count = 0;

			$stats = array(
			'message_count' => $message_count,
			'response_count' => $resp_count,
			'group_count' => $group_count
			);
			$this->set($stats,'stats');

			
				
	}
	
	
}
?>
