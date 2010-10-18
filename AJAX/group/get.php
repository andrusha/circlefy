<?php
/*
    CALLS:
        sidebar.js
*/
class ajax_get extends Base {
    protected $view_output = 'JSON';
    protected $need_login = false;

    function __invoke() {
        $type = $_POST['type'];
        $id   = intval($_POST['id']);

        switch ($type) {
            case 'byUser':
                $result = GroupsList::search('byUser', array('uid' => $id))->asArrayAll();
                break;
        }

        $this->data = array('success' => !empty($result), 'data' => $result);
    }
};
