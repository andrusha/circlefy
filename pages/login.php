<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class login extends Base{

	protected $text;
	protected $top;

	function __default(){
	}

	public function __toString(){
		return "Login Object";
	}

	function __construct(){

		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->page_name = "login";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();

		$uid = $_SESSION['uid'];
		//$_SESSION['guest']=0;
		//Takes awayfist settings flag
		setcookie('profile_edit','',time()-360000);
				
	}
	
	
}
?>
