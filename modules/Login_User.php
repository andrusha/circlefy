<?php
/*
Usage:

The following would log you out if $_GET['a'] was set:

		if($_GET['logout']){
		$this->auth_class->login_class->log_out($_SESSION['uname']);
		}
	
The Following would allow the user to automatically login if his automatic login flag was set:

		if($_COOKIE['auto_login'])
		$bypass = $this->auth_class->login_class->bypass_login();

		
The following will allow a user to authenticate as long as no logout or automatic login is detected:
		if(!$bypass && !$_GET['logout']){
			$this->auth_class->login_class->validate_user();
			
Class written by Taso Du Val
		}*/


class Login_User extends Auth{
	private $db_test;
	private $db_class_mysql;
	private $Base_Class;
	
	public function __toString(){
		return "Login_User Class";
	}
	
protected function __default(){	
}

public function __construct($db_class=''){
	
	
	if(!$this->session_started){
		session_start();
	}

/*
	//START maintance of expired logouts
	if(!$_SESSION['uid'] && !$_COOKIE['expire'])
		setcookie("expire",1,time()+360000);

	if($_COOKIE['expire'] && $_COOKIE['expire_2']){
		setcookie("expire",'',time()-36000);
		setcookie("expire_2",'',time()-36000);
	}

	if(!$_SESSION['uid'] && $_COOKIE['expire'] && !$_GET['fid']){
		setcookie("expire_2",1,time()+36000);
		header('location:http://'.$_SERVER['HTTP_HOST'].'/rewrite/');
		$flag=1;
	}
	//END maintance of expired logouts
*/	
	$this->db_class_mysql = $db_class;

}

public function bypass_login(){

	//START CHECK 	
	//Use a special hash that will take the users IP address
	$rand_hash = $_COOKIE['rand_hash'];
	
	//If the username for the hash is set, find
	if(isset($_COOKIE['uid'])){
	    $uid = $_COOKIE['uid'];
	    
	    //See if there was a match
	    $num_rows = $this->get_hash($uid,$rand_hash);
	    
	    //If there is a match, set the session to what the cookie is
	        if($num_rows == 1){
			$_SESSION['uid'] = $uid;
			$_SESSION['uname'] = $_COOKIE['uname']; //SLOPPY, needs to be fixed
	            return 'bypassed';
	        } else {
	    setcookie("auto_login",'',time()-36000);
	    setcookie("uid",'',time()-36000);
	    setcookie("rand_hash",'',time()-3600);
            setcookie("uname",'',time()-36000);
	            return 'fraud';
	        }  
	} //END CHECKss
}

public function validate_user(){
		
	if(!$_POST['uname'])
		return;
		
header('Cache-Control: no-store, no-cache, must-revalidate');
	
	//Creates a special unique hash
	$rand_hash = "bOFUh&$4)(@&cn".rand(1,999999)."hh0r38@(c.lz[";
	$rand_hash = md5($rand_hash);
	
	if(strlen($_POST['uname']) > 2){
		
		$uname = $_POST['uname'];
		$pass = $_POST['pass'];
		
		//This is optional incase the user wants to be automatically logged in each time, he can click the 'keep me logged in' box
		$auto_login = $_POST['auto_login'];
		

		
		$login_status = $this->login_user($uname,$pass,$rand_hash,$auto_login);
			$uid = $_SESSION['uid'];
			$uname = $_SESSION['uname'];
		
		//echo $login_status;
		
		if($login_status == 1){
		 	//Set users cookie in username, this cookie is used to match up the users IP, if they match
		    setcookie("uid",$uid,time()+36000000);
		    setcookie("rand_hash",$rand_hash,time()+36000000);
		    setcookie("uname",$uname,time()+36000000);
 
		    if ($_POST['auto_login'] == 'on') {
		    	setcookie("auto_login",$auto_login,time()+36000000);
		    } else {
		    	//this will clear the users cookie if they chose not to auto_login and they've prevoiusly chosen it
		    	setcookie("auto_login",'',time()-36000);
		    }
		    
		    //Set session for automatic login and usage throughout the lifetime
			
			return 'success';
		} else {
			return 'invalid';
		}
		
	} else {
		return 'short';
		}	
}
	

public function login_user($uname,$pass,$hash,$auto_login){
	
//	var_dump($this->db_test);
	
	$uname = $this->db_class_mysql->db->real_escape_string($uname);
	$pass = md5($pass);

	$time = time();
	$addr = $_SERVER['REMOTE_ADDR'];
	
	$this->results = $this->db_class_mysql->db->query("UPDATE login SET last_login=CURRENT_TIMESTAMP(),ip=INET_ATON('{$addr}') WHERE uname='{$uname}' AND pass='{$pass}'; ");


	
	if ($this->db_class_mysql->db->affected_rows  == 1) {
		
		// START SESSION UPDATE BASED ON HASH  	
				if(1){
			    //When the user logs in, update his hash to his current hash and username
			    $hash_update_query = "UPDATE login SET hash='{$hash}' WHERE uname='{$uname}';";
		
			    //echo $hash_update_query;
			   $hash_results = $this->db_class_mysql->db->query($hash_update_query);
			   
			   		//Get the users ID to use for the rest of the application
			   			$get_user_id_query = "SELECT t1.uname,t1.uid FROM login AS t1
									WHERE t1.uname='{$uname}'";

			   			$get_user_id_result = $this->db_class_mysql->db->query($get_user_id_query);

		
							while($res = $get_user_id_result->fetch_assoc()){
								$uid = $res['uid'];
								$uname = $res['uname'];
							}
				}
		// END SESSION UPDATE BASED ON HASH
			
		$_SESSION['uid'] = $uid;
		$_SESSION['uname'] = $uname;
		
			return 1;
	} else{
			return 0;
	}
}

    //START TO CHECK IF `IPHASH` and `username` match
public function get_hash($uid,$rand_hash){
        
        $hash_select = "SELECT hash,uid FROM login WHERE uid='{$uid}' and hash='{$rand_hash}'";
        
         $results_hash = $this->db_class_mysql->db->query($hash_select);
        
         $num_rows = $results_hash->num_rows;
    
        //return the number of rows selected so you you can enter the if statement on line 19
        return $num_rows;
        
}
    //END CHECK
    
    
public function log_out($uid){
		
	    //When the user logs out, update his hash to his current hash and username
	    $hash_update_query = "UPDATE login SET hash='' WHERE uid='{$uid}';";
	    
	    //echo $hash_update_query;
	    $hash_results = $this->db_class_mysql->db->query($hash_update_query);
	
		//START LOG OUT
	    $_SESSION['uid'] = "";
		$_SESSION['gid'] = "";
	    
	    //When you log out destroy the cookie so that it does not enter the if statement on line 14
	    setcookie("uid",'',time()-36000);
	    setcookie("uname",'',time()-36000);
	    setcookie("rand_hash",'',time()-3600);
	    	return  'goodbye';
	    
	//END LOG OUT
	}
}

?>
