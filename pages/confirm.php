<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class confirm extends Base{

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
		$this->page_name = "confirm";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();
	
		$uid = $_SESSION['uid'];
		$hash = $_GET['code'];

		$q1 = 'UPDATE join_group_status SET status=1 WHERE hash="'.$hash.'" AND uid='.$uid;
		$this->db_class_mysql->set_query($q1,'Query1','This query updates the users status for his connected group');
		$result1 = $this->db_class_mysql->execute_query('Query1');

		$ar = $this->db_class_mysql->db->affected_rows;
		if($ar == 1){
			$this->set('Accepted','status');
			$q2 = 'SELECT gid FROM join_group_status WHERE hash="'.$hash.'" AND uid='.$uid;
			$this->db_class_mysql->set_query($q2,'Query2','This query gets the gid of the connected group');
			$result2 = $this->db_class_mysql->execute_query('Query2');

			$q3 = 'UPDATE group_members SET status=1 WHERE uid='.$uid;
			$this->db_class_mysql->set_query($q3,'Query3','This query updates the users status for his connected group [ different table, denormalization reasons ] ');
			$result3 = $this->db_class_mysql->execute_query('Query3');
		} else { 
			$this->set('Denied','status');
		}


	}

	function test(){

	}

}
?>
