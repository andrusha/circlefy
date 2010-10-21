<?php

class page_group_edit extends Base {
    protected $need_login = true;

	function __invoke() {
        $symbol = $_GET['symbol'];

        $circle = GroupsList::search('bySymbol', array('symbol' => $symbol))->getFirst();

        $this->set($circle->asArray(), 'circle');

        $this->set(array_flip(Group::$types), 'types');
        $this->set(array_flip(Group::$auths), 'auths');
       
        $this->set(
            UsersList::search('members', array('gid' => $circle->id), U_PENDING)->asArrayAll(),
            'pending');
	}
};
