<?php

	abstract class Base{

		protected $errors = array(); //Any errors will be reported here

		//These are variables that you should not change.
		protected $data = array();
		protected $page_name;

		//This is the stylesheet that your program will use, you can chose to change 
		//this style sheet on a per-page basis by setting $this->stylesheet on any page
		protected $stylesheet = "/main.css";

		//This variable lets you chose what type of output the view will show, you can chose to have HTML
		//XML, or JSON output based on 
		protected $view_output = "HTML";

		//These properties are flags
		//For example, if you need a database conncetion, in your page, set $this->need_db = 1 and it will load the DB class
		//and give you a database connection
		protected $need_db;
		protected $db_type = 'mysql';
		protected $db_class_mysql;
		protected $db_class_postgress;
		protected $need_direct_db;

		protected $need_auth = 0;
		protected $need_login = 0;
		protected $need_session;
		protected $h_name;
		protected $auth_class = NULL;
		protected $login_class;
		protected $bypass_flag;

		protected $autoCreateUser;
		protected $autoCreateUserObject = NULL;

		protected $need_encrypt = 'no';
		protected $need_filter = 0;
		protected $input_debug_flag = 0;


/*Here is a list of protected variables that child class set and get
such functions such as $db and other functions that need to be set and get
are included in this centralized function in order to instill encapsulation.
 */
		//The page has access to this variable incase you want to use mysqli/PDO directly and bypass the framework.

		//abstract function __default();

		public function __toString(){
			return "Base Class";
		}

		protected function __construct(){
			self::set($this->stylesheet,'stylesheet');
			self::set($this->view_output,'output');

			if($this->need_db == 1){
				$this->db_conn();
			}

			if($this->need_auth == 1){
				$this->auth();
			}

			if($this->need_login == 1){
				$this->login();
			}

			if($this->need_filter == 1){
				$this->db_filter();
			}

			$this->autoCreateUser = 1;	// We'll use autoCreate everywhere.
			if($this->autoCreateUser == 1){
				$this->auto_create();
			}

			// TPL variable ok_user -> "I am a registered user (not guest)"
			$logged = ((!empty($_SESSION['uid'])) && ($_SESSION['guest']!="1")) ? 1 : 0;
			$this->set($logged,'ok_user');
		}

		protected function db_conn(){
			$type = "db_class_".$this->db_type;
			//echo $type;
			$this->$type = DB::getInstance();
			$this->$type->Start_Connection($this->db_type);

			if($this->need_direct_db){
				$this->$type = $this->$type->Get_Connection($this->db_type);
				return $this->$type;
			} else {
				return $this->$type;
			}

		}

		protected function auth(){
			if($this->need_auth){
				$this->auth_class = new Auth($this->db_class_mysql);
			}
		}
		protected function auto_create(){
			require_once('autoCreateUser.php');
			$this->autoCreateUserObject = new autoCreateUser($this->db_class_mysql);
		}

		protected function db_filter(){
			foreach($_POST as $key => $val){
				$escaped_value = $this->db_class_mysql->db->real_escape_string($val);
				$_POST[$key] = $escaped_value;
			}
			if($this->input_debug_flag == 1){
				$this->input_degbug();
			}
		}

		protected function input_degbug(){
			foreach($_POST as $key => $val){
				echo $key." => ".$val."<br/>";
			}
		}

		protected function login(){
			$this->login_class = new Login_User($this->db_class_mysql);
			//var_dump($this->auth_class);
		}

		public function set($text,$var){
			$this->data[$var] = $text;
		}

		public function get(){
			//print_r($_SESSION);
			return $this->data;
		}

		public function page(){
			return $this->page_name;
		}
		

}
