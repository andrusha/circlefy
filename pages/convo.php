<?php

class page_convo extends Base {
    protected $need_login = true;

	function __invoke() {
        $id = intval($_GET['id']);

        $this->set(
            TapsList::search('byId', array('id' => $id), T_USER_INFO | T_GROUP_INFO )
                    ->replies()
                    ->involved()
                    ->format()
                    ->getFirst()
                    ->asArray(),
            'convo'); 
	}
};
