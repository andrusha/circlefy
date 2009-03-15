<?php
$_pluginInfo=array(
	'name'=>'Popstarmail',
	'version'=>'1.0.0',
	'description'=>"Get the contacts from an Popstarmail account",
	'base_version'=>'1.6.5',
	'type'=>'email',
	'check_url'=>'http://super.popstarmail.org/'
	); 
/**
 * popstarmail Plugin
 * 
 * Imports user's contacts from popstarmail's AddressBook
 * 
 * @author OpenInviter
 * @version 1.0.0
 */	
class popstarmail extends OpenInviter_Base
{
	private $login_ok=false;
	protected $timeout=30;
	public $showContacts=true;
	public $requirement='email';
	public $allowed_domains=false;	
	public $debug_array=array(
				'initial_get'=>'show_frame',
				'login_post'=>'ob',
				'get_contacts'=>'showexport',
				'contacts_file'=>'Name'
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
		$this->service='popstarmail';
		$this->service_user=$user;
		$this->service_password=$pass;
		if (!$this->init()) return false;
		$res=$this->get("http://super.popstarmail.org/",true);
		if ($this->checkResponse('initial_get',$res))
			$this->updateDebugBuffer('initial_get',"http://super.popstarmail.org/",'GET');
		else
			{
			$this->updateDebugBuffer('initial_get',"http://super.popstarmail.org/",'GET',false);
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		
		$form_action="http://super.popstarmail.org/scripts/common/ss_main.cgi";
		$post_elements=array('show_frame'=>'Enter','action'=>'login','login'=>$user,'password'=>$pass,'x'=>rand(5,15),'y'=>rand(5,15));
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

		$sid=$this->getElementString($res,'ob=','"');
		$url_export="http://mymail.hk.popstarmail.org/scripts/addr/external.cgi?.ob={$sid}&gab=1";
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
		else $url = $this->login_ok;
		$res=$this->get($url);
		if ($this->checkResponse('get_contacts',$res))
			$this->updateDebugBuffer('get_contacts',"http://www.evite.com/loginRegForm?redirect=/pages/addrbook/contactList.jsp",'GET');
		else
			{
			$this->updateDebugBuffer('get_contacts',"http://www.evite.com/loginRegForm?redirect=/pages/addrbook/contactList.jsp",'GET',false);
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
			
		$form_action=$url;
		$post_elements=array('showexport'=>'showexport','action'=>'export','login'=>$this->service_user,'format'=>'csv');
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
			if (!empty($values['4']))
				$contacts[$values['4']]=(empty($name)?$values['4']:$name);
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
		$logout_url="http://mymail.hk.popstarmail.org/scripts/mail/Outblaze.mail?logout=1&.noframe=1&a=1&";
		$res = $this->get($logout_url,true);
		$this->debugRequest();
		$this->resetDebugger();
		$this->stopPlugin();
		return true;
		}
}
?>