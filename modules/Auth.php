<?php

class Auth{
	
	private $session;
	private $cookie;
	private $sessions = array();
	protected $session_started = 0;
	
	public function __toString(){
		return "Auth Object";
	}
	
	public function __construct($db_class='',$testing=''){
		$this->session = Session::getInstance($this->sessions);
	  	$this->cookie = Cookie::getInstance($user_name);
	  	$this->login_class = new Login_User($db_class,$testing);
	  	//var_dump($this);
	}
	
	public function set_session($name,$value){
		$sessions[$name] = $value;
		$_SESSION[$name] = $value;
	}
	
	public function get_session($name){
		return $_SESSION[$name];
	}
	
}
