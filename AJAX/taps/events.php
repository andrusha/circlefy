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
                $items = TapsList::fetchEvents($this->user, $page)->format()->asArrayAll();
                $this->data = array('success' => 1, 'events' => array_values($items));
                break;
        }
    }
};
