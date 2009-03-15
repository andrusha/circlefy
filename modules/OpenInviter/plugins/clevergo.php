<?php
$_pluginInfo=array(
	'name'=>'Clevergo',
	'version'=>'1.0.0',
	'description'=>"Get the contacts from a Clevergo account",
	'base_version'=>'1.6.5',
	'type'=>'email',
	'check_url'=>'http://www.clevergo.com/index.php'
	);
/**
 * Doramail.com Plugin
 * 
 * Imports user's contacts from Clevergo.com AddressBook
 * 
 * @author OpenInviter
 * @version 1.0.0
 */
class clevergo extends OpenInviter_Base
	{
	private $login_ok=false;
	public $showContacts=true;
	public $internalError=false;
	public $requirement='user';
	public $allowed_domains=false;
	

	public $debug_array=array(
				'initial_get'=>'email_local',
				'login_post'=>'sid',
				'contacts_file'=>'Firstname'
				
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
		$this->service='clevergo';
		$this->service_user=$user;
		$this->service_password=$pass;
		if (!$this->init()) return false;
					
		$res=$this->get("http://www.clevergo.com/index.php");
		if ($this->checkResponse("initial_get",$res))
			$this->updateDebugBuffer('initial_get',"http://www.doramail.com/scripts/common/index.main?signin=1&lang=us",'GET');
		else
			{
			$this->updateDebugBuffer('initial_get',"http://www.doramail.com/scripts/common/index.main?signin=1&lang=us",'GET',false);	
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		
		$form_action="http://www.clevergo.com/index.php?action=login";
		$post_elements=array('do'=>'login','email_local'=>$user,'email_domain'=>'clevergo.com','passwordMD5'=>md5($pass),'language'=>'english');
		$res=$this->post($form_action,$post_elements,true);
		if ($this->checkResponse('login_post',$res))
			$this->updateDebugBuffer('login_post',$form_action,'POST',true,$post_elements);
		else
			{
			$this->updateDebugBuffer('login_post',$form_action,'POST',false,$post_elements);	
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
			
		$sid=$this->getElementString($res,'email.php?sid=','"');
		$this->login_ok=$sid;
		file_put_contents($this->getLogoutPath(),$sid);
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
		else $sid=$this->login_ok;
		
		$form_action="http://www.clevergo.com/organizer.addressbook.php?action=exportAddressbook&sid={$sid}";
		$post_elements=array('lineBreakChar'=>'lf','sepChar'=>'comma','quoteChar'=>'double');
		$res=$this->post($form_action,$post_elements);
		if ($this->checkResponse("contacts_file",$res))
			$this->updateDebugBuffer('contacts_file',$form_action,'POST',true,$post_elements);
		else
			{
			$this->updateDebugBuffer('contacts_file',$form_action,'POST',false,$post_elements);	
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
			
		$temp=$this->parseCSV($res);
		$contacts=array();
		foreach ($temp as $values)
			{
			$name=$values['0'].(empty($values['1'])?'':(empty($values['0'])?'':'-')."{$values['1']}").(empty($values['3'])?'':" \"{$values['3']}\"").(empty($values['2'])?'':' '.$values['2']);
			if (!empty($values['9']))
				$contacts[$values['9']]=(empty($name)?$values['9']:$name);
			}
		foreach ($contacts as $email=>$name) if (!$this->isEmail($email)) unset($contacts[$email]);
		return $contacts;	
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
		if (file_exists($this->getLogoutPath()))
			{
			$sid=file_get_contents($this->getLogoutPath());	
			$res=$this->get("http://www.clevergo.com/start.php?sid={$sid}&action=logout",true);
			}	
		$this->debugRequest();
		$this->resetDebugger();
		$this->stopPlugin();
		return true;
		}
	}	

?>