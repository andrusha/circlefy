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
		if(!$hash)
			header( 'Location: http://tap.info/groups?error=no_hash' );

		$check_update_query = 'UPDATE join_group_status SET status=1 WHERE hash="'.$hash.'" AND uid='.$uid;
		$this->db_class_mysql->set_query($check_update_query,'check_hash','This query updates the users status for his connected group');
		$result1 = $this->db_class_mysql->execute_query('check_hash');
		if($this->db_class_mysql->db->affected_rows){
			$this->set('Accepted','status');
			$update_group_members_query = 'UPDATE group_members SET status=1 WHERE uid='.$uid;
			$this->db_class_mysql->set_query($update_group_members_query,'update_group_members','This query updates the users status for his connected group [ different table, denormalization reasons ] ');
			$result3 = $this->db_class_mysql->execute_query('update_group_members');
		} else { 
			$this->set('Denied','status');
		}
	}
}
?>
