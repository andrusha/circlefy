<?php
/* CALLS:
    login.js
*/ 
require('../AJAX/ajaz_sign_up.php');
require('../api.php');

class irc_freenode extends ajaz_sign_up{ 

	public $uid = null;
	public $uid2 = 99;

	function __construct($irc_server,$irc_channel,$irc_nickname,$irc_pass,$finame,$lname,$email,$lang){
		set_time_limit(0);
		parent::__construct();
//		echo $irc_server,$irc_channel,$irc_nickname,$irc_pass;
		$this->server_host = $irc_server; 
		$this->server_port = 6667; 
		$this->server_chan = $irc_channel;
		$this->irc_nickname = $irc_nickname; 
		$this->irc_pass = $irc_pass;
	
		$this->uname = $this->irc_nickname;
		$this->password = $this->irc_pass;
		$this->fname = $finame;
		$this->lname = $lname;
		$this->email = $email;
		$this->lang = 'English';

//		$this->uid = 63;
			
		$this->sock = '';
	}

	public function check_users_exists(){
		$check_users_exists_query = <<<EOF
		SELECT uid FROM login WHERE uname = "$this->uname"
EOF;
		$check_users_exists_results = $this->mysqli->query($check_users_exists_query);

		if($check_users_exists_results->num_rows){
            $this->finished('REGISTERED');
            //    $this->connect();
        }else{
            $this->finished('SORRY');
		    //	$this->connect();
        }
	}

	private function connect(){
		$channel_access = array();

		if(empty($this->irc_nickname)){
			return False;
		} else {
		$listchan=False;
		    $this->sock = @fsockopen($this->server_host, $this->server_port, $this->errno, $this->errstr, 2); 
		    if($this->sock){
			$random_user = 'fhoahf'.rand(1,99999);
			  $this->SendCommand("PASS NOPASS\n\r");
			  $this->SendCommand("NICK $random_user\n\r"); 
			  $this->SendCommand("USER $this->irc_nickname USING PHP IRC\n\r");
			  $this->SendCommand("PRIVMSG nickserv :identify $this->irc_nickname $this->irc_pass\n\r");

			while(!feof($this->sock)){
			    $buf = fgets($this->sock, 1024); //get a line of data from the server 
			   // echo "[RECIVE] ".$buf; //display the recived data from the server 
		//	    if(strpos($buf, "422")) //422 last thing displayed after a successful connection after MOTD
		//		$this->SendCommand("JOIN $server_chan\n\r"); 

			    if(substr($buf, 0, 6) == "PING :") 
				$this->SendCommand("PONG :".substr($buf, 6)."\n\r"); 

			    if($emailed_checked && !$signed_up){
				#Nick name checks happenes at the database level and the IRC level
				#This assumes you've already checked your nickname somewhere else
				//echo "test";
				$this->process_sign_up($this->uname,$this->fname,$this->email,$this->password,$this->lang);
				$signed_up = 1;
				$uid2 = $this->uid;
				$this->uid2 = $uid2;
	//			$this->uid = parent::$uid;
				$this->SendCommand("PRIVMSG nickserv :listchans\n\r");
				}

			    if(strpos($buf, "You are now identified") && !$emailed_checked){
				$this->SendCommand("PRIVMSG nickserv :info $this->irc_nickname\r");
				}	

			    if(strpos($buf, "Email")){
				$parsed_email = explode(' ',$buf);
				$parsed_email = $parsed_email[10];

				if($parsed_email == 'noemail')
					$this->email = rand(1,999999);
				else
					$this->email = $parsed_email;
				$emailed_checked = 1;
			    }

			    if(strpos($buf, "Invalid password"))
				$this->finished('LOGIN_FAILED');

			    if(strpos($buf, "not a registered nickname") && $pass)
				$this->finished('NOT_REGISTERED');

			    if(strpos($buf, "not a registered nickname"))
				$pass = 1;
				

			    if(strpos($buf, "Access flag")){
				$a = explode('+',$buf);
				$b = explode(' ',$a[1]);

				$channel_access[] = array(
					'flags' => $b[0],
					'channel' => $b[2]
				);

				}
			
			    if(strpos($buf, "channel access matches for the nickname") && $this->uid2){
				$this->process_channels($channel_access,1);
				//$listchan=True;
				}
			}

			    flush(); 
			} 
		    } 
		} 

	public function process_channels($channels,$type){
		foreach($channels as $channel){
			if(strpos($channel['flags'],'o') || strpos($channel['flags'],'O') || strpos($channel['flags'],'F')){
				$chan = rtrim($channel['channel']);
				$chan = trim($chan,'#');
				$chan = trim($chan,'#');
				
				$insert_string_channels .= "'$chan',";
				$insert_string_full .= "($type,'$chan',$this->uid2),";
			}
		}
		$insert_string_full = substr($insert_string_full,0,-1);
		$insert_string_channels = substr($insert_string_channels,0,-1);
		$insert_string_full = "INSERT INTO irc_link(status,channel,uid) values $insert_string_full";
	//	$this->mysqli->query($insert_string_full);
		//echo $insert_string_full;

		$get_gids_query = <<<EOF
		SELECT gid FROM groups WHERE symbol IN ($insert_string_channels)
EOF;
		//echo $get_gids_query;
		$get_gids_results = $this->mysqli->query($get_gids_query);
		if(!$get_gids_results->num_rows)
			$this->finished('ADDED_NONE');

		while($res = $get_gids_results->fetch_assoc()){
			$gid = $res['gid'];
			$gid_list .= " (1,$gid,$this->uid2),";
		}
		$gid_list = substr($gid_list,0,-1);

		$link_group_query = <<<EOF
		INSERT INTO group_members(admin,gid,uid) values $gid_list
EOF;
		$this->mysqli->query($link_group_query);
		//echo $link_group_query;
	//	if(!$type)
		$this->finished('ADDED_ADDED');
	}

	public function finished($status){
		/*
		status messages:
		ADDED_ADDED = Registered IRC USER w/ channels
		ADDED_NONE = Registered IRC USER w/ no channels
		REGISTERED = User is already registered
		LOGIN_FAILED  = IRC User exists but failed pass
		NOT_REGISTERED = IRC does not exist
		*/
		$response = json_encode(array("status" => $status));
		echo $response;
		exit();
	}
/*
	private function isOp_channellist($user,$invite_type,$link){
		$op_users = array();
		$voice_op_users = array();
		$g_users = array();

	
#          	$this->SendCommand("PRIVMSG nickserv :listchans\n\r"); 

		if ('@'+$NICK_NAME == $user){
			$granted = 1;
		}

		if('@' == $user[0]){
			$user = substr($user,1);
			array_push($op_users,$user);
			array_push($voice_op_users,$user);
		}

		if('%' == $user[0]){
			$user = substr($user,1);
		}

		if('+' == $user[0]){
			$user = substr($user,1);
			array_push($voice_users,$user);
			array_push($voice_op_users,$user);
		}
		array_push($g_users,$user);

		if($granted){
		//invites all of the ops
			if($invite_type == 'invite_ops'){
				msgAll($voice_op_users);
			}
			if($invite_type == 'invite_voice_ops'){
				msgAll($op_users);
			}
			if($invite_type == 'invite_all'){
				msgAll($g_users);
			}

		if($link)
			create_link();

		}

	}
*/
	public function SendCommand ($cmd){
	    @fwrite($this->sock, $cmd, strlen($cmd)); //sends the command to the server 
//	    echo "[SEND] $cmd <br>"; //displays it on the screen 
	} 
}


$flag = 'irc';
if($flag == 'irc'){
    
	//echo "Logging in.....";
	$nick = "Yayzyzy";
	$password = "acid11";
	$nick = "Gla";
	$password = "hehehe";

	$fname = "Change my";
	$lname = "name";
	$email = rand(1,999999);

	$lang = "English";
	$server = 'irc.freenode.net';
	$channel = '';

	$nick = $_POST['user'];
	$password = $_POST['pass'];

	$irc = new irc_freenode($server,$channel,$nick,$password,$fname,$lname,$email,$lang);
	$irc->check_users_exists();	
}
?>
