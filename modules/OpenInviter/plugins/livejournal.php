<?php
/*Import Friends from Livejournal
 * You can Post Messages using Livejournal system
 */
$_pluginInfo=array(
	'name'=>'Livejournal',
	'version'=>'1.0.0',
	'description'=>"Get the contacts from a Livejournal account",
	'base_version'=>'1.6.3',
	'type'=>'social',
	'check_url'=>'http://www.livejournal.com/mobile/'
	);
/**
 * Livejournal Plugin
 * 
 * Import user's contacts from Livejournal and Post  messages
 * using Livejournal's internal Posting  system
 * 
 * @author OpenInviter
 * @version 1.0.0
 */
class livejournal extends OpenInviter_Base
	{
	private $login_ok=false;
	public $showContacts=true;
	public $requirement='user';
	public $internalError=false;
	public $allowed_domains=false;
	
	public $debug_array=array(
				'initial_get'=>'user',
				'login_post'=>'post.bml',
				'get_friends'=>'lj:user',
				'url_send_message'=>'lj_form_auth',
				'send_message'=>'successfully'
				);
	
	/**
	 * Login function
	 * 
	 * Makes all the necessary requests to authenticate
	 * the current user to the server.
	 * 
	 * @param string $user The current user.
	 * @param string $pass The password for the current user.
	 * @return bool TRUE if the current user was authenticated successfully, FALSE otherwise.
	 */
	public function login($user,$pass)
		{
		$this->resetDebugger();
		$this->service='livejuornal';
		$this->service_user=$user;
		$this->service_password=$pass;
		if (!$this->init()) return false;

		$res=$this->get("http://www.livejournal.com/mobile/login.bml");
		if ($this->checkResponse("initial_get",$res))
			$this->updateDebugBuffer('initial_get',"http://www.livejournal.com/mobile/login.bml",'GET');
		else
			{
			$this->updateDebugBuffer('initial_get',"http://www.livejournal.com/mobile/login.bml",'GET',false);
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
			
		$form_action="http://www.livejournal.com/mobile/login.bml";
		$post_elements=array('user'=>$user,'password'=>$pass);
		$res=$this->post($form_action,$post_elements,true);
		if ($this->checkResponse("login_post",$res))
			$this->updateDebugBuffer('login_post',"{$form_action}",'POST',true,$post_elements);
		else
			{
			$this->updateDebugBuffer('login_post',"{$form_action}",'POST',false,$post_elements);
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		$url_post='http://www.livejournal.com/friends/edit.bml';
		$this->login_ok=$url_post;
		return true;
		}

	/**
	 * Get the current user's contacts
	 * 
	 * Makes all the necesarry requests to import
	 * the current user's contacts
	 * 
	 * @return mixed The array if contacts if importing was successful, FALSE otherwise.
	 */	
	public function getMyContacts()
		{
		if (!$this->login_ok)
			{
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		else $url=$this->login_ok;
		$res=$this->get($url,true);
		if ($this->checkResponse("get_friends",$res))
			$this->updateDebugBuffer('get_friends',$url,'GET');
		else
			{
			$this->updateDebugBuffer('get_friends',$url,'GET',false);
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		
		$contacts=array();
		$contacts_array=$this->getElementDOM($res,"//span[@class='ljuser']");
		if (!empty($contacts_array))
			foreach($contacts_array as $name) if (!empty($name)) $contacts[$name]=$name;
		unset($contacts['lj_maintenance']);unset($contacts['lj_spotlight']);unset($contacts['news']);
		if (isset($contacts[$this->service_user])) unset($contacts[$this->service_user]);
		return $contacts;
		}

	/**
	 * Send message to contacts
	 * 
	 * Sends a message to the contacts using
	 * the service's inernal messaging system
	 * 
	 * @param string $cookie_file The location of the cookies file for the current session
	 * @param string $message The message being sent to your contacts
	 * @param array $contacts An array of the contacts that will receive the message
	 * @return mixed FALSE on failure.
	 */
	public function sendMessage($session_id,$message,$contacts)
		{
		foreach($contacts as $name)
			{
			$form_action='http://www.livejournal.com/inbox/compose.bml';
			$res=$this->get($form_action);
			if ($this->checkResponse("url_send_message",$res))
				$this->updateDebugBuffer('url_send_message',$form_action,'GET');
			else
				{
				$this->updateDebugBuffer('url_send_message',$form_action,'GET',false);
				$this->debugRequest();
				$this->stopPlugin();
				return false;
				}
				
			$post_elements=array('lj_form_auth'=>$this->getElementString($res,'name="lj_form_auth" value="','"'),
								'msg_to'=>$name,
								'msg_subject'=>$message['subject'],
								'msg_body'=>$message['body'],
								'mode'=>'send',
								);
			$res=$this->post($form_action,$post_elements,true);
			if ($this->checkResponse("send_message",$res))
				$this->updateDebugBuffer('send_message',"{$form_action}",'POST',true,$post_elements);
			else
				{
				$this->updateDebugBuffer('send_message',"{$form_action}",'POST',false,$post_elements);
				$this->debugRequest();
				$this->stopPlugin();
				return false;
				}
			}
	
		}

	/**
	 * Terminate session
	 * 
	 * Terminates the current user's session,
	 * debugs the request and reset's the internal 
	 * debudder.
	 * 
	 * @return bool TRUE if the session was terminated successfully, FALSE otherwise.
	 */	
	public function logout()
		{
		if (!$this->checkSession()) return false;
		$res=$this->get("http://www.livejournal.com/logout.bml");
		$this->debugRequest();
		$this->resetDebugger();
		$this->stopPlugin();
		return true;	
		}
	}	

?>