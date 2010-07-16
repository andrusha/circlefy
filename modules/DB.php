<?php

//This class defines all of the database queries as well as what connection is to be used.
//The explination of how to set queries is listed below.  The way you set your database connector
//depends on the flag that either your Base class has or your page has.  

class DB{
	
	//There are 2 methods of setting queries:
	//  #1. One is by using the method set_query(); in each page to set $query_list dynamically for each page
	//	#2. Creating the array statically by doing $query_list['name_of_query'] = "SELECT * FROM user etc";
	//A static list populated everytime may be faster, but it may be a pain to maintain, or not, it depends on your prefernce.
	//static public $db;
	public $query_list = array();
	
	//protected $db_type = 'MySQL';
	protected $db_debug = 0;
	static private $count = 0;
	
	static private $instance;
	
	//This is your $db class, it defaults to mysqli, but you can change it with db-type
	//Use: $db->query("SELECT ..");
	public $db = NULL;
	
	function __default(){
	}
	
	private function __construct(){
	}

	
	static public function getInstance() 
		  { 
		  if ( self::$instance == null ) 
			  self::$instance = new DB(); 
			  return self::$instance;
		  }
		  
	 public function Start_Connection($type){
	 	
	 		switch($type){
			case 'mysql':
	 			$this->db = MySQL_Conn::getInstance();
	 		break;
	 		
			case 'postgress':
				$this->db = Postgress_Conn::getInstance();
			break;
	 	//var_dump($this->db);
			 }
	 }
	 
	 protected function Get_Connection($db_type){
		
		$this->db_type = $db_type;
		
		switch($this->db_type){
			case 'mysql':
				if($this->db){
					return $this->db;
				} else {
					echo "Error you have no database connection yet you declared a direct database connection";
				}
			break;
		}	
	}
	
	public function set_query($query,$name,$comment,$x=''){
		$comment_name = $name."_comment";
		$this->query_list[$name] = $query;
		$this->query_list[$comment_name] = $comment;
	}

	public function last_insert_id(){
		// Added by Hookdump 15 Jul 10 @ 15:00
		$result = $this->db->query("SELECT LAST_INSERT_ID() as LAST_ID");
		$last_id_row = $result->fetch_assoc();
		return $last_id_row['LAST_ID'];
	}
	
	public function execute_query($query_name){
		
	if(!$this->db_debug){
	$result = $this->db->query($this->query_list[$query_name]);
	return $result;
	}
	
	if($this->db_debug){
	self::$count++;
	$start = microtime(true);
		$result = $this->db->query($this->query_list[$query_name]);
		echo "<br/>-- Query #".self::$count.': - '.$this->query_list[$query_name].'<br/>-- Time: <b>';
		
	$end = microtime(true);
	$query_length = $end - $start;
	$query_length = $query_length;
	
	echo $query_length;
	echo "<br/>-- Comments:</b> ".$this->query_list[$query_name."_comment"];
	
	}
	
	if($query_length > .2)
	echo "- This query may be slowing you down	";
	
	echo '<br/>';
	
	return $result;
	
	}

	
	function __destruct(){	
	//$this->db->close();
	}
	
}
