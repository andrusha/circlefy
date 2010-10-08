<?php
class MySQL
{ 
	private static $instance = null;
	
	private function __construct() {}
	
	public function __destruct() {}
	
  static public function getInstance() 
	  { 
	  if ( self::$instance == null ) 
		  self::$instance = new mysqli('p:'.D_ADDR,D_USER,D_PASS,D_DATABASE); 
		  return self::$instance; 
	  } 
}
