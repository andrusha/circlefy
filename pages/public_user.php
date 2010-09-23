<?php

class public_user extends Base{
	function __construct(){
		$this->need_login = 1;
		$this->need_db = 0;
		$this->page_name = "public_user";
	
		parent::__construct();

		$uname = $_GET['public_uid'];
        $private = isset($_GET['pm']);
        
        $this->set($private, 'pm');
    
        //user, who's page we viewing
        $user = User::fromUname($uname);

        $this->set($this->user->following($user), 'tracked');
        $this->set($user->followingCount(),'tracked_count');
        $this->set($user->followersCount(),'track_count');
		$this->set($user->stats, 'stats');

        $info = $user->getFullInfo(true);
        foreach (array('uname' => 'user', 'about' => 'about', 'uid' => 'uid',
            'small_pic' => 'user_pic_small', 'big_pic' => 'user_pic_med',
            'help' => 'help', 'country' => 'country', 'online' => 'user_online',
            'private' => 'private') as $from => $to)
            $this->set($info[$from], $to);
	
        if (!$private)
            $this->set(
                TapsList::getFiltered('personal', array('#uid#' => $user->uid, '#outside#' => '1, 2'))
                        ->lastResponses()
                        ->filter('all'), 
                'user_bits');
        else
            $this->set(
                TapsList::getFiltered('private', array('#from#' => $user->uid, '#to#' => $this->user->uid, '#outside#' => '1, 2'))
                        ->lastResponses()
                        ->filter('all'), 
                'user_bits');

        $this->set(
            GroupsList::byUser($user, G_EXTENDED | G_TAPS_COUNT)->filter('info'), 
            'your_groups');	

        $this->set(
            UsersList::getFollowing($user, true)
                     ->getStats()
                     ->getRelations($this->user)
                     ->filter('info'),
            'following');

        $this->set(
            UsersList::getFollowers($user, true)
                     ->getStats()
                     ->getRelations($this->user)
                     ->filter('info'),
            'followers');

        Action::log($this->user, 'user', 'view', array('uid' => $user->uid));
	}
};
