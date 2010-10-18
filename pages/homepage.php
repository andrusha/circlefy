<?php

class page_homepage extends Base {
    protected $need_login = true;

	function __invoke() {
        if (isset($_GET['logout'])) {
            Auth::logOut();
            header('location: /');
            exit();
        } elseif (isset($_GET['killyourself'])) {
            Auth::logOut();
            $this->user->delete();
            header('location: /');
            exit();
        }

        $this->set(
            GroupsList::search('byUser', array('uid' => $this->user->id, 'limit' => 16))
                      ->asArrayAll(),
            'circles');

        if (!$this->user->guest) {
            $feed = TapsList::search('feed', array('uid' => $this->user->id), T_USER_INFO | T_USER_RECV | T_GROUP_INFO | T_INSIDE) 
                    ->lastResponses()
                    ->format()
                    ->asArrayAll();
            $this->set(
                GroupsList::search('byUser', array('uid' => $this->user->id), G_ONLY_ID)->filter('id'),
                'comet_groups');
            $this->set('feed', 'feed_type');
            $this->set('Your Feed', 'feed_name');
        } else
            $this->set('Discover', 'feed_name');

        if (empty($feed) || $this->user->guest) {
            $feed = TapsList::search('public', array(), T_USER_INFO | T_GROUP_INFO)
                    ->lastResponses()
                    ->format()
                    ->asArrayAll();
            $this->set('public', 'feed_type');
        }
        
        $this->set($feed, 'feed');

	}
};
