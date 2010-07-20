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


	class autoCreateUser extends Auth{
		private $db_test;
		private $db_class_mysql;
		private $Base_Class;
		private $debug_msgs;	// by hook
		
		private function debu($s) {
			$s = addslashes($s);	
			//echo "<script language='JavaScript'>console.log('" . date("H:i:s") . ": $s');</script>";
		}

		public function __toString(){
			return "autoCreateUser Class";
		}

		protected function __default(){	
		}

		public function __construct($db_class='') {
			$this->debu("Loading autoCreateUser module...");
			if($this->session_started){
				$this->debu("You are logged in! :)");
				return 0;
			}
			$this->debu("You are not logged in! :(");
			$this->db_class_mysql = $db_class;
			$this->bypass_login();
		}

		public function bypass_login(){
			$this->debu("Bypassing login...");

			//START CHECK 	
			//Use a special hash that will take the users IP address
			$myhash = $_COOKIE['NLIhash'];
			$this->debu("Loading cookies: " . $_COOKIE['NLIuname'] . " (" . $_COOKIE['NLItmpuid'] . ") Hash: " .$_COOKIE['NLIhash']);

			if(strlen($myhash) < 2){
				$this->debu(" - Inserting cookie...");
				$NLIuname = 'Guest'.rand(1,999999);

				$NLIipaddr	= $_SERVER['REMOTE_ADDR'];
	
				$RAWrand_hash = "bOFrcUh&$4)(@&cn".rand(1,999999)."hh0r38@(c.lz[";
				$NLIrand_hash = md5($RAWrand_hash);
	
				$this->debu(" - IP: $NLIipaddr");
				$this->debu(" - HASH: $RAWrand_hash      /      $NLIrand_hash");
				$this->debu(" - UNAME: $NLIuname");

				setcookie('NLIuname',$NLIuname,time()+36000);
				setcookie('NLIhash',$NLIrand_hash,time()+36000);

				$new_uid = $this->insertTempUser($NLIuname,$NLIrand_hash,$NLIipaddr);
				if ($new_uid != -1) {
					setcookie('NLItmpuid',$new_uid,time()+36000);					
				}
				$this->debu("Inserting cookies: $NLIuname ($new_uid) Hash: $NLIuname$NLIrand_hash");
				$_SESSION['uid'] = $new_uid;
				$_SESSION['uname'] = $NLIuname;
				$_SESSION['hash'] = $NLIrand_hash;
				$_SESSION['anon'] = 1;
			} else {
				// My tempuser already exists!
				$this->debu("Loading cookies: " . $_COOKIE['NLIuname'] . " (" . $_COOKIE['NLItmpuid'] . ") Hash: " .$_COOKIE['NLIhash']);
				$_SESSION['uid'] = $_COOKIE['NLItmpuid'];
				$_SESSION['uname'] = $_COOKIE['NLIuname'];
				$_SESSION['hash'] = $_COOKIE['NLIhash'];
				$_SESSION['anon'] = 1;

				return 0;
			}
		}

		public function insertTempUser($NLIuname,$NLIrand_hash,$NLIipaddr){
			$this->debu("Inserting new TempUser....");

			// Perhaps we should store the user IP
			// TODO: Put this SQL Logic in sql.php
			$stamp = time();
			//$micons = "INSERT INTO temp_users (`hash`,`uname`,`timestamp`, `ip`)";
			$micons = "INSERT INTO login (`hash`,`uname`,`last_login`, `ip`, `anon`, `email`)";		// I'll fill "email" with the hash
			$micons .= " VALUES ('$NLIrand_hash', '$NLIuname', CURRENT_TIMESTAMP, '$NLIipaddr', '1', '$NLIrand_hash');";
			$this->debu("SQL: ".$micons);
			
			$this->results = $this->db_class_mysql->db->query($micons);

			if ($this->db_class_mysql->db->affected_rows == 1) {
				$uid = $this->db_class_mysql->last_insert_id();
				$this->debu("*** TempUser inserted successfully! UID: [$uid]");
				
				// BUGFIX: Analog to Populate User in AJAX/ ajaz_sign_up.php
				$profile_query = "INSERT INTO profile(uid,language) values($uid,'English');";
				$this->db_class_mysql->db->query($profile_query);
				$settings_query = "INSERT INTO settings(uid) values($uid);";
				$this->db_class_mysql->db->query($settings_query);
				$notifications_query = "INSERT INTO notifications(uid) values($uid);";
				$this->db_class_mysql->db->query($notifications_query);
				$TEMP_ONLINE_query = "INSERT INTO TEMP_ONLINE(uid) values($uid);";
				$this->db_class_mysql->db->query($TEMP_ONLINE_query);

				return $uid;
			} else {
				return -1;
			}


		}

	}

