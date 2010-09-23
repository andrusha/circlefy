<?php
/*
    CALLS:
        people.js
*/

require('../../config.php');
require('../../api.php');

class search extends Base {
    public function __construct() {
        $this->need_db = false;
        $this->view_output = 'JSON';
        parent::__construct();

        $uname = $_POST['uname'];
        $this->data = $this->byUname($uname);
    }

    private function byUname($uname) {
        $users = UsersList::friendsByUname($this->user, $uname)
                          ->getStats()
                          ->getRelations()
                          ->filter('info');

        return array('success' => true, 'results' => $users);
	}
};

$a = new search();
