<?php
/*
CALLS:
    search.js    
*/

class ajax_search extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $search = $_POST['search']; //search term
        $type   = $_POST['type']; 

        if (!in_array($type, array('yourGroups', 'withUsers', 'like'))) {
            $this->data = array('success' => 0);
            return;
        }

        $search = '%'.str_replace(' ', '%', preg_replace('/\s{2,}/', '', $search)).'%';

        $this->set(
            GroupsList::search($type == 'yourGroups' ? 'byUserAndLike' : 'like', 
                               array('search' => $search, 'limit' => 5, 'uid' => $this->user->id), 
                               G_TAPS_COUNT | G_USERS_COUNT)
                    ->asArrayAll(),
            'groups');

        if ($type == 'withUsers') {
            $usersList = UsersList::search('like', array('search' => $search, 'limit' => 5))->asArrayAll();
            $this->set(array_values($usersList), 'users');
        }
    }
};
