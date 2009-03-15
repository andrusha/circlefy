<?php
class Cookie {
	
	static private $session = NULL ;
	
	private function __construct(){
	}
	
	static public function getInstance(){
		if($session == NULL){
			self::$session = new Cookie();
			return self::$session;
		}
	}
	
	public function destroy_session($user_name){
		$_COOKIE[$name] = NULL;
	}
}