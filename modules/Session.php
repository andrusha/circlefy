<?php
class Session {

	
	
	static private $session = NULL ;
	
	private function __construct($sessions){
		session_start();
		$this->session_started = 1;
	}
	
	static public function getInstance($sessions){	
		if($session == NULL){
			self::$session = new Session($sessions);
			return self::$session;
		}
	}
	
	public function destroy_session(){
		session_destroy();
	}
	
}
