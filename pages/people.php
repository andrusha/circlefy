<?php

class people extends Base{
	function __default(){
	}
	
	public function __toString(){
		return "Homepage Object";
	}
	
	function __construct() {
	
		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->page_name = "people";
		$this->need_login = 1;
		$this->need_db = 1;
	
		parent::__construct();
		$uid = $_SESSION['uid'];

        $peoples = new Friends();
        $user = new User();

		$this->set($peoples->followingCount($uid),'tracked_count');
		$this->set($peoples->followersCount($uid),'track_count');

        $users = array();
		if(!$_GET['q'])	
            $users = $peoples->getFollowing($uid);
		else
            $users = $peoples->getFollowers($uid);

        foreach($users as $fuid => $user_info) {
            $friend = array_intersect_key($user_info, 
                array_flip(array('uname', 'fname', 'lname', 'pic_100', 'last_chat')));
            $friend['fuid'] = $fuid;
            $friend['stats'] = $user->getStats($fuid);
            $friend['friend'] = 1;
            if (!$friend['last_chat'])
                $friend['last_chat'] = "*This user has not tap'd yet*;";

            $friend_data[$fuid] = $friend;
        }

        $this->set($friend_data,'peoples');
    }
};
