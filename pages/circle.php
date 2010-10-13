<?php

class page_circle extends Base {
    protected $need_login = true;

	function __invoke() {
        $symbol = $_GET['symbol'];

        //TODO: do something if group doesn't exists
        $group = Group::init($symbol);
        $group->descr = FuncLib::linkify($group->descr);
        $this->set($group->asArray(), 'circle');

        $this->set(
            UsersList::search('members', array('gid' => $group->id, 'limit' => 14))
                     ->asArrayAll(),
            'members');

        $this->set(
            TapsList::search('group', array('gid' => $group->id), T_USER_INFO)
                    ->lastResponses()
                    ->inject('group', $group)
                    ->format()
                    ->asArrayAll(),
            'feed');
	}
};