<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class messeges_view extends Base{

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
		$this->page_name = "messeges_view";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();

		$this->db_class_mysql->set_query('Select Sleep(3);','Query','This is for X');
		$this->db_class_mysql->set_query('Select * From User','Query4','This is for y',1);

		//$result = $this->db_class_mysql->execute_query('Query4')

		$this->set($result,'users');

		//echo "1234 ---------".$this->auth_class->get_session('name');


		if($_GET['a'] && !$_POST['uname']){
		$this->login_class->log_out($_SESSION['uname']);
		}

		//if($_COOKIE['auto_login'])
		$bypass = $this->login_class->bypass_login();

		if(!$bypass && !$_GET['a']){
			$this->login_class->validate_user();
		}

	}

	function test(){

	}

}
?>