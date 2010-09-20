<?php

class homepage extends Base {
    function __default() {}

	function __construct() {
		$this->need_login = 1;
		$this->need_db = 1;
        $this->page_name = "new_homepage";
	
		parent::__construct();

        //Get user info
        $info = $this->user->info;
        $this->set($this->user->uname, 'user');
        $this->set($info['small_pic'],'user_pic');
        $this->set($info['big_pic'],'user_pic_100');
		$this->set($info['help'],'help');
        $this->set($info['real_name'], 'real_name');

        $convosClass = new Convos();
        $active_convos = $convosClass->getActive($this->user->uid);
		$this->set($active_convos,'active_convos');
	
        $this->set(
            GroupsList::byUser($this->user, G_EXTENDED | G_ONLINE_COUNT | G_USERS_COUNT)->filter('info'),
            'your_groups');	

		$this->set(
            UsersList::getFollowing($this->user)->filter('info'),
            'your_friends');

		$this->set(
            UsersList::withPM($this->user)->filter('info'),
            'your_private');

        $taps = new Taps();
        if (!$this->user->guest) {
            $params = array('#outside#' => '1, 2', '#uid#' => $this->user->uid);
            $filter = 'aggr_groups';
            $data = $taps->getFiltered($filter, $params);
            $this->set('all', 'feed_type');
        }

        if ($this->user->guest || empty($data)) {
            //show all public taps for guests
            $params = array('#outside#' => '1, 2');
            $filter = 'public';  
            $data = $taps->getFiltered($filter, $params);
            $this->set('discover', 'feed_type');
        }

        $this->set($data, 'groups_bits');
	}

};
