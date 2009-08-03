<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class search_groups extends Base{

	protected $text;
	protected $top;

	function __default(){
	}

	public function __toString(){
		return "Homepage Object";
	}

	function __construct(){

		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->page_name = "search_groups";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();
		
		$uid = $_SESSION['uid'];
	
			
		$search_group_name = $_POST['search_group_name'];
		
		
		$search_group_query = "SELECT * FROM groups WHERE gname LIKE \"%".$search_group_name."%\" LIMIT 10";
		echo		$search_group_query;

		$this->db_class_mysql->set_query($search_group_query,'search_groups','This query is for searching the groups');
		$result = $this->db_class_mysql->execute_query('search_groups');

		$this->set($result,'search_groups');

	}

	function test(){

	}

}
?>
