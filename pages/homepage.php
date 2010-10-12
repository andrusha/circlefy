<?php

class page_homepage extends Base {
    protected $need_login = true;

	function __invoke() {
        if (isset($_GET['logout'])) {
            Auth::logOut();
            header('location: /');
            exit();
        }

        if ($this->user->guest)
            $this->guest();
        else
            $this->user();
	}

    private function user() {
        $this->set(
            GroupsList::search('byUser', array('uid' => $this->user->id, 'limit' => 16))
                      ->asArrayAll(),
            'circles');

        $this->set(
            TapsList::search('feed', array('uid' => $this->user->id), T_USER_INFO | T_USER_RECV | T_GROUP_INFO) 
                    ->lastResponses()
                    ->format()
                    ->asArrayAll(),
            'feed');

        $this->set('Your', 'feed_name');
    }

    private function guest() {
        $this->set(
            TapsList::search('public', array(), T_USER_INFO | T_GROUP_INFO)
                    ->lastResponses()
                    ->format()
                    ->asArrayAll(),
            'feed');

        $this->set('Global', 'feed_name');
    }
};
