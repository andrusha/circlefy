<?php
$_pluginInfo=array(
	'name'=>'Uk2',
	'version'=>'1.0.0',
	'description'=>"Get the contacts from a Uk2 account",
	'base_version'=>'1.6.5',
	'type'=>'email',
	'check_url'=>'http://mail.uk2.net/'
	);
/**
 * Uk2 Plugin
 * 
 * Imports user's contacts from Uk2 AddressBook
 * 
 * @author OpenInviter
 * @version 1.0.0
 */
class uk2 extends OpenInviter_Base
	{
	private $login_ok=false;
	public $showContacts=true;
	public $internalError=false;
	public $requirement='user';
	public $allowed_domains=false;
	protected $timeout=30;
	
	public $debug_array=array(
				'initial_get'=>'username',
				'login_post'=>'parse',
				'url_inbox'=>'parse',
				'contacts_file'=>'Email'
				
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
		$this->service='uk2';
		$this->service_user=$user;
		$this->service_password=$pass;
		if (!$this->init()) return false;
					
		$res=$this->get("http://mail.uk2.net/",true);
		if ($this->checkResponse("initial_get",$res))
			$this->updateDebugBuffer('initial_get',"http://mail.uk2.net/",'GET');
		else
			{
			$this->updateDebugBuffer('initial_get','http://mail.uk2.net/','GET',false);	
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		
		$form_action='http://mail.uk2.net/atmail.pl';
		$post_elements=array('Language'=>'english','username'=>$user,'pop3host'=>'uk2.net','password'=>$pass,'LoginType'=>'xul');
		$res=$this->post($form_action,$post_elements,false,true,false,array(),false,false);
		if ($this->checkResponse('login_post',$res))
			$this->updateDebugBuffer('login_post',$form_action,'POST',true,$post_elements);
		else
			{
			$this->updateDebugBuffer('login_post',$form_action,'POST',false,$post_elements);	
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		
		$url_redirect='http://mail.uk2.net/'.$this->getElementString($res,"href='","'");
		$res=$this->get($url_redirect,true);	 
		if ($this->checkResponse("url_inbox",$res))
			$this->updateDebugBuffer('url_inbox',$url_redirect,'GET');
		else
			{
			$this->updateDebugBuffer('url_inbox',$url_redirect,'GET',false);	
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		
		$url_export='http://mail.uk2.net/abook.pl?func=export&abookview=personal';
		$this->login_ok=$url_export;
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
		$res=$this->get($url);
		if ($this->checkResponse("contacts_file",$res))
			$this->updateDebugBuffer('contacts_file',$url,'GET');
		else
			{
			$this->updateDebugBuffer('contacts_file',$url,'GET',false);	
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		
		$temp=$this->parseCSV($res);
		$contacts=array();
		foreach ($temp as $values)
			{
			$name=$values['20'].(empty($values['8'])?'':(empty($values['20'])?'':'-')."{$values['8']}");
			if (!empty($values['3']))
				$contacts[$values['3']]=(empty($name)?$values['3']:$name);
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
		$res=$this->get('http://mail.uk2.net/util.pl?func=logout',true);
		$this->debugRequest();
		$this->resetDebugger();
		$this->stopPlugin();
		return true;	
		}
	
	}	

?>