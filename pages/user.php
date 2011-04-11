<?php

class page_user extends Base {
    protected $need_login = true;

	function __invoke() {
        $uname = $_GET['uname'];

        $user = User::init($uname);

        if (!$user || $user->id == null) {
            header('Location: /');
            exit();
        }

        $user->stats = $user->getStats();
        Events::readUserEvent($this->user, $user);

        $this->set($user->asArray(), 'user');

        $this->set(
            GroupsList::search('byUser', array('uid' => $user->id, 'limit' => 10))
                      ->asArrayAll(),
            'circles');
        
        $this->set(
            TapsList::search('friend', array('uid' => $user->id, 'you' => $this->user->id), T_GROUP_INFO | T_MEDIA)
                    ->lastResponses()
                    ->inject('sender', $user)
                    ->format()
                    ->asArrayAll(),
            'feed');

        $this->set(
            UsersList::search('followers', array('id' => $user->id, 'limit' => 14))
                     ->asArrayAll(),
            'followers');

        $this->set(
            UsersList::search('following', array('id' => $user->id, 'limit' => 14))
                     ->asArrayAll(),
            'following');

        $this->set(
            $this->user->following($user),
            'state');
	}
};
