<?php
class MySQL_Conn
{ 
	private static $instance = null;
	//protected $mysql;
	
	private function __construct()
	{	//$this->mysql = new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE); 
	}
	
	public function __destruct(){
	}
	
  static public function getInstance() 
	  { 
	  if ( self::$instance == null ) 
		  self::$instance = new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE); 
		  return self::$instance; 
	  } 
}