<?php

class people extends Base{
	function __default(){}
	
	public function __toString(){
		return "Homepage Object";
	}
	
	function __construct() {
		$this->page_name = "people";
		$this->need_login = 1;
		$this->need_db = 1;
	
		parent::__construct();
		$uid = $_SESSION['uid'];

		$this->set($this->user->followingCount(),'tracked_count');
		$this->set($this->user->followersCount(),'track_count');

        $users = array();
		if(!$_GET['q'])
            $users = UsersList::getFollowing($this->user, true);
		else
            $users = UsersList::getFollowers($this->user, true);
        
        $this->set($users->getStats()
                         ->getRelations($this->user)
                         ->filter('info'),
                   'peoples');

        $this->set($_GET['q'], 'q');
    }
};
