<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class password_recovery extends Base{

	protected $text;
	protected $top;

	function __default(){
	}

	public function __toString(){
		return "Password Recovery Object";
	}

	function __construct(){

		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->page_name = "password_recovery";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();


		if(isset($_GET['hash'])){
			if($_GET['hash']!= ""){
				if($this->valid_hash($_GET['hash'])){
					$this->set('yes','recovery_password_form');
				}else{
					$this->set('Hash not valid','recovery_password_form_msg');
				}
			}else{
				$this->set('No cannot be empty','recovery_password_form_msg');
			}
		}else{
			$this->set('No hash set','recovery_password_form_msg');
		}
	}

	function valid_hash($hash){
		$valid_hash_query =
<<<EOF
		SELECT email_hash
		FROM login
		WHERE email_hash = '{$hash}'
EOF;
		//echo $valid_hash_query;
		$this->db_class_mysql->set_query($valid_hash_query,'valid_hash',"Validating hash for password recovery");
		$valid_hash_result = $this->db_class_mysql->execute_query('valid_hash');
		return ($valid_hash_result->num_rows > 0);
	}

}
?>
