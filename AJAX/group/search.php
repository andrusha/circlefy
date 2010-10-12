<?php
/*
CALLS:
    search.js    
*/

class ajax_search extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $search = $_POST['search'];

        $this->set(
            GroupsList::search('like', array('search' => $search, 'limit' => 5))
                      ->asArrayAll(),
            'groups');
    }
};
