<?php
/*
    CALLS:
        sidebar.js
*/

class ajax_get extends Base {
    protected $view_output = 'JSON';
    protected $need_login = false;

    public function __invoke() {
        $id = intval($_POST['id']);
        $type = $_POST['type'];

        if (!in_array($type, array('following', 'followers', 'members')))
            return;

        $key = $type == 'members' ? 'gid' : 'id';
        $this->set(
            UsersList::search($type, array($key => $id))->asArrayAll(),
            'data');

        $this->set(true,  'success');
    }
};
