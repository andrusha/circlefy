<?php

class homepage extends Base {
    function __default() {}

	function __construct() {
		$this->need_login = 1;
		$this->need_db = 1;
        $this->page_name = "new_homepage";
	
		parent::__construct();

        /*
		$this->set(
            TapsList::getFiltered('active', array('#uid#' => $this->user->uid))
                    ->filter('all')
            , 'active_convos');
	
        
        $this->set(
            GroupsList::byUser($this->user, G_EXTENDED | G_ONLINE_COUNT | G_USERS_COUNT)->filter('info'),
            'your_groups');	

		$this->set(
            UsersList::getFollowing($this->user)->filter('info'),
            'your_friends');

		$this->set(
            UsersList::withPM($this->user)->filter('info'),
            'your_private');*/

        if (!$this->user->guest) {
            $params = array('#outside#' => '1, 2', '#uid#' => $this->user->uid);
            $filter = 'aggr_all';
            $data = TapsList::getFiltered($filter, $params, true, true)
                            ->lastResponses()
                            ->filter('all');
            $this->set('all', 'feed_type');
        }

        if ($this->user->guest || empty($data)) {
            //show all public taps for guests
            $params = array('#outside#' => '1, 2');
            $filter = 'public';  
            $data = TapsList::getFiltered($filter, $params, true, true)
                            ->lastResponses()
                            ->filter('all');
            $this->set('discover', 'feed_type');
        }

        $this->set($data, 'groups_bits');
	}

};
