<?php
$_pluginInfo=array(
	'name'=>'FastMail',
	'version'=>'1.0.2',
	'description'=>"Get the contacts from a FastMail account",
	'base_version'=>'1.6.3',
	'type'=>'email',
	'check_url'=>'http://www.fastmail.fm'
	);
/**
 * FastMail Plugin
 * 
 * Imports user's contacts from FastMail's AddressBook
 * 
 * @author OpenInviter
 * @version 1.0.0
 */
class fastmail extends OpenInviter_Base
	{
	private $login_ok=false;
	public $showContacts=true;
	public $requirement='email';
	public $internalError=false;
	public $allowed_domains=array('fastmail.fm');
	
	public $debug_array=array(
				'initial_get'=>'FLN-LoginMode',
				'post_login'=>'MailApp',
				'contacts_page'=>'MSignal_UA-Download*',
				'contacts_file'=>'Title',
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
		$this->service='fastmail';
		$this->service_user=$user;
		$this->service_password=$pass;
		if (!$this->init()) return false;
		
		$res=$this->get("http://www.fastmail.fm/");
		
		if ($this->checkResponse('initial_get',$res))
			$this->updateDebugBuffer('initial_get',"http://www.fastmail.fm/",'GET');
		else 
			{
			$this->updateDebugBuffer('initial_get',"http://www.fastmail.fm/",'GET',false);
			$this->debugRequest();
			$this->stopPlugin();
			return false;	
			}
		
		$form_action=$this->getElementString($res,'action="','"');
		$post_elements=array('MLS'=>'=LN-*',
							 'FLN-LoginMode'=>0,
							 'FLN-UserName'=>$user,
							 'FLN-Password'=>$pass,
							 'MSignal_LN-AU*'=>'Login',
							 'FLN-Security'=>0,
							 'FLN-ScreenSize'=>3,
							 'FLN-SessionTime'=>1800,
							 'FLN-NoCache'=>'on' 
							 
							);
		$res=$this->post($form_action,$post_elements,TRUE);
		if ($this->checkResponse('post_login',$res))
			$this->updateDebugBuffer('post_login',"{$form_action}",'POST',true,$post_elements);
		else 
			{
			$this->updateDebugBuffer('post_login',"{$form_action}",'POST',false,$post_elements);
			$this->debugRequest();
			$this->stopPlugin();
			return false;	
			}
		

		$url_adress_book=$this->getElementDOM($res,"//a[@class='HdrScrLnk']",'href');
		$url_adress="http://www.fastmail.fm".$url_adress_book[1];
		file_put_contents($this->getLogoutPath(),$url_adress);		
		$this->login_ok=$url_adress;
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
		
		$form_action=$this->getElementString($res,'action="','"');
		$post_elements=$this->getHiddenElements($res);$post_elements['MSignal_UA-*U-1']='Upload/Download';
		$res=$this->post($form_action,$post_elements,true);
		if ($this->checkResponse('contacts_page',$res))
			$this->updateDebugBuffer('contacts_page',"{$form_action}",'POST',true,$post_elements);
		else 
			{
			$this->updateDebugBuffer('contacts_page',"{$form_action}",'POST',false,$post_elements);
			$this->debugRequest();
			$this->stopPlugin();
			return false;	
			}
		
		$form_action=$this->getElementString($res,'action="','"');
		$post_elements=$this->getHiddenElements($res);$post_elements['FUA-DownloadFormat']='OL';$post_elements['MSignal_UA-Download*']='Download';
		$res=$this->post($form_action,$post_elements);
		if ($this->checkResponse('contacts_file',$res))
			$this->updateDebugBuffer('contacts_file',"{$form_action}",'POST',true,$post_elements);
		else 
			{
			$this->updateDebugBuffer('contacts_file',"{$form_action}",'POST',false,$post_elements);
			$this->debugRequest();
			$this->stopPlugin();
			return false;	
			}
		
		$temp=$this->parseCSV($res);
		$contacts=array();
		foreach ($temp as $values)
			{
			$name=$values[1].(empty($values[2])?'':(empty($values[1])?'':'-')."{$values[2]}").(empty($values[3])?'':" \"{$values[3]}\"");
			if (!empty($values[34]))
				$contacts[$values[34]]=(empty($name)?$values[34]:$name);
			if (!empty($values[35]))
				$contacts[$values[35]]=(empty($name)?$values[35]:$name);
			if (!empty($values[36]))
				$contacts[$values[36]]=(empty($name)?$values[36]:$name);
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
			$url=file_get_contents($this->getLogoutPath());
			//go to url adress book  url in order to make the logout
			$res=$this->get($url,true);
			$form_action=$this->getElementString($res,'action="','"');
			$post_elements=$this->getHiddenElements($res);
			$post_elements['MSignal_AD-LGO*C-1.N-1']='Logout';
			
			//get the post elements and make de logout
			$res=$this->post($form_action,$post_elements,true);
			}
		$this->debugRequest();
		$this->resetDebugger();
		$this->stopPlugin();
		return true;
		}
	
	}	
?>