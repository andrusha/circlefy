<?php
class pending_members extends Base {
    public function __construct() {
        $this->view_output = "HTML";
        $this->page_name = "pending_members";
        $this->need_db = 1;
        parent::__construct();

        $this->mysqli = $this->db_class_mysql->db;

        $symbol = $_GET['channel'];
        $group = Group::fromSymbol($symbol);

		if($group === null)	
			header('Location: http://tap.info/channels?error=no_group');

		if(!$group->checkPermissions($this->user))
			header("Location: http://tap.info/channels?error=denied");

        $this->set(
            UsersList::members($group, 'requested')->filter('info'),
            'members');
    }
}
