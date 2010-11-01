<?php

class page_convo extends Base {
    protected $need_login = true;

	function __invoke() {
        $id = intval($_GET['id']);

        $tap = TapsList::search('byId', array('id' => $id), T_USER_INFO | T_GROUP_INFO | T_MEDIA)
                       ->replies()
                       ->involved()
                       ->format()
                       ->getFirst();


        $this->set($tap->asArray(), 'convo'); 
        $this->set($tap->getStatus($this->user), 'state');

        $this->set(
            array_flip(Group::$types),
            'types');

        $this->set(
            array_flip(Group::$auths),
            'auths');
	}
};
