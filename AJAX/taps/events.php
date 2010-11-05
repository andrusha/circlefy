<?php
/*
    CALLS:
        notification.js
*/

class ajax_events extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $action = $_POST['action'];

        switch($action) {
            case 'all_read':
                TapsList::deleteAllEvents($this->user);
                $this->data = array('success' => 1);
                break;

            case 'fetch':
                $page = intval($_POST['page']);
                $items = TapsList::search('events', array('uid' => $this->user->id, 'row_count' => 5,
                                          'start_from' => $page*5), T_USER_INFO | T_GROUP_INFO | T_LIMIT)
                                 ->format()->asArrayAll();
                $this->data = array('success' => 1, 'events' => array_values($items));
                break;
        }
    }
};
