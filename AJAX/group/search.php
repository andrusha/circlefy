<?php
/*
CALLS:
    search.js    
*/

class ajax_search extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $search = $_POST['search'];

        $search = '%'.str_replace(' ', '%', preg_replace('/\s{2,}/', '', $search)).'%';

        $this->set(
            GroupsList::search('like', array('search' => $search, 'limit' => 5), G_TAPS_COUNT | G_USERS_COUNT)
                      ->asArrayAll(),
            'groups');
    }
};
