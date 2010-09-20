<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
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
		//$this->page_name = "signup"; 
		$this->page_name = "about_me";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();

		$uid = $_SESSION['uid'];
		if(!$uid)
			header( 'Location: http://mark.tap.info?error=no_login' );
		//Takes awayfist settings flag
		setcookie('profile_edit','',time()-360000);

		$geo_list_query = <<<EOF
                        SELECT cn.abbr2,cn.name AS Country FROM country_translate AS cn WHERE cn.abbr2 != '-' LIMIT 249;
EOF;


                        $geo_list_results = $this->db->query($geo_list_query);
                        while($res = $geo_list_results->fetch_assoc()){
				$abbr2 = strtolower($res['abbr2']);
				$name = $res['Country'];

				$init_geo_data[] = array(
					'abbr2' =>      $abbr2,
					'name' =>       $name
				);
                        }
                        $this->set($init_geo_data,'init_geo_data');

	
			$get_profile_query = <<<EOF
				SELECT 
				t1.about,t1.metric,t1.rs_status,t1.dob,t1.gender,t1.country,t1.region,t1.town,t1.state,t1.education,t1.language,t1.zip,t1.occupation,
				t5.email,t5.pic_100,t5.fname,t5.lname,t5.private,t5.anon
				FROM profile AS t1
				JOIN login AS t5
				ON t1.uid = t5.uid
				WHERE t1.uid={$uid}
EOF;


				$edit_profile_results = $this->db->query($get_profile_query);

				$res =  $edit_profile_results->fetch_assoc();
				$uname = $res['uname'];
				$pic_100 = $res['pic_100'];				
				$fname = $res['fname'];
				$lname = $res['lname'];
				$private = $res['private'];
				$email = $res['email'];
				$lang = $res['language'];
				$about = $res['about'];
				$country = $res['country'];
				$region = $res['region'];
				$town = $res['town'];
				$state = $res['state'];
				$zip = $res['zip'];
				$anon = $res['anon'];
				
	
				$edit_profile_results = array(
					'uname' => $uname,
					'pic_180' => $pic_100,
					'email' => $email,
					'fname' => $fname,
					'lname' => $lname,
					'private' => $private,
					'zip' => $zip,
					'lang' => $lang,
					'about' => $about,
					'country' => $country,
					'region' => $region,
					'state' => $state,
					'town' => $town,
					'anon' => $anon
				);

			$this->set($edit_profile_results,'edit_profile');

			$count_messages = <<<EOF
			SELECT COUNT(*) AS message_count FROM special_chat WHERE uid = {$uid}
EOF;
			$count_results = $this->db->query($count_messages);
			if($count_results->num_rows)
			$res = $count_results->fetch_assoc();
				$message_count = $res['message_count'];

			$count_resp = <<<EOF
			SELECT COUNT(*) AS resp_count FROM chat WHERE uid = {$uid}
EOF;

			$count_results = $this->db->query($count_resp);
			if($count_results->num_rows)
			$res = $count_results->fetch_assoc();
                                $resp_count = $res['resp_count'];

			$count_group = <<<EOF
                        SELECT COUNT(*) AS group_count FROM group_members WHERE uid = {$uid}
EOF;
                        $count_results = $this->db->query($count_group);
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
