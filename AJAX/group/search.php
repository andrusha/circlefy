<?php
/*
CALLS:
    search.js    
*/

class ajax_search extends Base {
    protected $view_output = 'JSON';
    //protected $need_login = false;

    public function __invoke() {
        $search = $_POST['search'];
        $type = (!empty($_POST['byUser'])) ? 'byUserAndLike' : 'like';

        $search = '%'.str_replace(' ', '%', preg_replace('/\s{2,}/', '', $search)).'%';

        $this->set(
            GroupsList::search($type, array('search' => $search, 'limit' => 5, 'uid' => $user->id), G_TAPS_COUNT | G_USERS_COUNT)
                    ->asArrayAll(),
            'groups');
        if ($type == 'like') {
            // TODO: fix the keys in asArrayAll response
            $usersList = UsersList::search('like', array('search' => $search, 'limit' => 5, 'id' => $user->id))->asArrayAll();
            $users = array();
            if (!empty($usersList))
                foreach ($usersList as $user)
                    $users[] = $user;
            $this->set($users, 'users');
        }
    }
};
