<?php
class group_edit extends Base {
	public function __construct() {
		$this->view_output = "HTML";
		$this->page_name = "new_group_edit";
		$this->need_db = 1;
		parent::__construct();

		$symbol = $_GET['channel'];

        $group = Group::extended($symbol);

		if($group === null)	
			header('Location: http://tap.info/channels?error=no_group');

		if(!$group->checkPermissions($this->user))
			header("Location: http://tap.info/channels?error=denied");

		$this->set($group->gid,  'gid');
		$this->set($group->info, 'group_info_result');
	}
};
