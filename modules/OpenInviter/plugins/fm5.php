<?php
$_pluginInfo=array(
	'name'=>'5Fm',
	'version'=>'1.0.0',
	'description'=>"Get the contacts from a 5fm.za.com account",
	'base_version'=>'1.6.5',
	'type'=>'email',
	'check_url'=>'http://www.5fm.za.com/'
	);
/**
 * 5FM.za.com Plugin
 * 
 * Imports user's contacts from 5FM.za.com AddressBook
 * 
 * @author OpenInviter
 * @version 1.0.0
 */
class fm5 extends OpenInviter_Base
	{
	private $login_ok=false;
	public $showContacts=true;
	public $internalError=false;
	public $requirement='user';
	public $allowed_domains=false;
	protected $timeout=30;
	
	public $debug_array=array(
				'initial_get'=>'secretkey',
				'login_post'=>'webmail',
				'url_export'=>'export',
				'contacts_file'=>','
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
		$this->service='5fm';
		$this->service_user=$user;
		$this->service_password=$pass;
		if (!$this->init()) return false;
					
		$res=$this->get("http://www.5fm.za.com/");
		if ($this->checkResponse("initial_get",$res))
			$this->updateDebugBuffer('initial_get',"http://www.5fm.za.com/",'GET');
		else
			{
			$this->updateDebugBuffer('initial_get',"http://www.5fm.za.com/",'GET',false);	
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		
		$form_action="http://webmail.5fm.za.com/src/redirect.php";
		$post_elements=array('login_domain'=>'5fm.za.com',
							'login_username'=>$user,
							'secretkey'=>$pass,
							'login'=>'login'
							 );
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
		$url_adressbook='http://webmail.5fm.za.com/src/addressbook.php';
		$this->login_ok=$url_adressbook;
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
		if ($this->checkResponse("url_export",$res))
			$this->updateDebugBuffer('url_export',$url,'GET');
		else
			{
			$this->updateDebugBuffer('url_export',$url,'GET',false);	
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}

		$form_action='http://webmail.5fm.za.com/plugins/abook_import_export/address_book_export.php';
		$res=$this->post($form_action,array());
		if ($this->checkResponse("contacts_file",$res))
			$this->updateDebugBuffer('contacts_file',$form_action,'POST',true,array());
		else
			{
			$this->updateDebugBuffer('contacts_file',$form_action,'POST',false,array());	
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
		$res=$this->get('http://webmail.5fm.za.com/src/signout.php',true);
		$this->debugRequest();
		$this->resetDebugger();
		$this->stopPlugin();
		return true;	
		}
	
	}	

?>