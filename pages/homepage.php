<?php

class page_homepage extends Base {
    protected $need_login = true;

	function __invoke() {
        if ($this->user->guest)
            $this->guest();
        else
            $this->user();
	}

    private function user() {
        $this->set(
            GroupsList::search('byUser', array('uid' => $this->user->id, 'limit' => 16), G_LIMIT)
                      ->asArrayAll(),
            'circles');

        $this->set(
            TapsList::search('feed', array('uid' => $this->user->id), T_USER_INFO | T_USER_RECV | T_GROUP_INFO) 
                    ->lastResponses()
                    ->asArrayAll(),
            'feed');

        $this->set('Your', 'feed_name');
    }

    private function guest() {
        $this->set(
            TapsList::search('public', array(), T_USER_INFO | T_GROUP_INFO)
                    ->lastResponses()
                    ->asArrayAll(),
            'feed');

        $this->set('Global', 'feed_name');
    }
};
