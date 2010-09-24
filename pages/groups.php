<?php
class groups extends Base{
	function __construct(){
		$this->view_output = "HTML";
		$this->page_name = "new_groups_manage";
		$this->need_db = 0;
		parent::__construct();

        $this->set(
            GroupsList::byUser($this->user, G_TAPS_COUNT | G_USERS_COUNT | G_RESPONSES_COUNT | G_EXTENDED)
                      ->filter('info'),
            'group_results');
    }
};
