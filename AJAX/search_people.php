<?php
/*
    CALLS:
        people.js
*/

require('../config.php');
require('../api.php');

define('FUCKED_UP', json_encode(array('success' => false, 'results' => array())));

if ($uname) {
    $search = new search_people();
    echo $search->byUname($uname);
} else {
    echo FUCKED_UP;
}

class search_people {
    public function byUname($uname) {
        $user = new User(intval($_SESSION['uid']));
        $users = UsersList::friendsByUname($user, $uname)
                          ->getStats()
                          ->getRelations()
                          ->filter('info');

        return json_encode(array('success' => true, 'results' => $users));
	}
};

