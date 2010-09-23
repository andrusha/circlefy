<?php
class Postgress
{ 
	private static $instance = null;
	
	private function __construct()
	{
	}
	
	public function __destruct(){
	}
	
  static public function getInstance() 
	  { 
	  if ( self::$instance == null ) 
		  self::$instance = new PDO("mysql:host=localhost;port=3306;dbname=chatapist","taso","password");
		  return self::$instance; 
	  } 
}
