<?php
$_pluginInfo=array(
	'name'=>'Mynet.com',
	'version'=>'1.0.1',
	'description'=>"Get the contacts from an Mynet account",
	'base_version'=>'1.6.3',
	'type'=>'email',
	'check_url'=>'http://uyeler.mynet.com/login/?loginRequestingURL=http%3A%2F%2Feposta.mynet.com%2Findex%2Fmymail.html&formname=eposta'
	);
/**
 * Mynet Plugin
 * 
 * Imports user's contacts from Mynet
 * 
 * @author OpenInviter
 * @version 1.0.0
 */
class mynet extends OpenInviter_Base
{
	private $login_ok=false;
	public $showContacts=true;
	public $requirement='user';
	public $internalError=false;
	public $allowed_domains=false;
	
	public $debug_array=array('initial_get'=>'faultyUser',
			  				  'post_login'=>'mymail',
			  				  'url_adress'=>'adres',
			  				  'url_file'=>'adres',
			  				  'file_contacts'=>'Name'
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
	public function login($user, $pass)
		{
		$this->resetDebugger();
		$this->service='mynet';
		$this->service_user=$user;
		$this->service_password=$pass;
		if (!$this->init()) return false;
		
		$res = $this->get("http://uyeler.mynet.com/login/?loginRequestingURL=http%3A%2F%2Feposta.mynet.com%2Findex%2Fmymail.html&formname=eposta");
		if ($this->checkResponse('initial_get',$res))
			$this->updateDebugBuffer('initial_get',"http://uyeler.mynet.com/login/?loginRequestingURL=http%3A%2F%2Feposta.mynet.com%2Findex%2Fmymail.html&formname=eposta",'GET');
		else 
			{
			$this->updateDebugBuffer('initial_get',"http://uyeler.mynet.com/login/?loginRequestingURL=http%3A%2F%2Feposta.mynet.com%2Findex%2Fmymail.html&formname=eposta",'GET',false);
			$this->debugRequest();
			$this->stopPlugin();
			return false;	
			}
		
		$form_action="https://uyeler.mynet.com/index/uyegiris.html";
		$post_elements=array('nameofservice'=>'epost',
							 'pageURL'=>'http://uyeler.mynet.com/login/login.asp?loginRequestingURL=http%3A%2F%2Feposta.mynet.com%2Findex%2Fmymail.html&formname=eposta',
							 'faultCoun'=>'',
							 'faultyUser'=>'',
							 'loginRequestingURL'=>'http://eposta.mynet.com/index/mymail.html',
							 'rememberstate'=>2,
							 'username'=>$user,
							 'password'=>$pass,
							 'x'=>rand(1,50),
							 'y'=>rand(1,20),
							 'rememberstatep'=>2
							);
		$res=$this->post($form_action,$post_elements,true);
		if ($this->checkResponse('post_login',$res))
			$this->updateDebugBuffer('post_login',"{$form_action}",'POST',true,$post_elements);
		else 
			{
			$this->updateDebugBuffer('post_login',"{$form_action}",'POST',false,$post_elements);
			$this->debugRequest();
			$this->stopPlugin();
			return false;	
			}
		
		$res=$this->get("http://eposta.mynet.com/index/mymail.html",true);
		$base_url="http://".$this->getElementString($res,"var mySrvName = '","'").".mynet.com";
		if ($this->checkResponse('url_adress',$res))
			$this->updateDebugBuffer('url_adress',"http://eposta.mynet.com/index/mymail.html",'GET');
		else 
			{
			$this->updateDebugBuffer('url_adress',"http://eposta.mynet.com/index/mymail.html",'GET',false);
			$this->debugRequest();
			$this->stopPlugin();
			return false;	
			}
			
		$url_adressbook='http://adres.email'.$this->getElementString($res,'http://adres.email','"');
		$res=$this->get($url_adressbook);
		if ($this->checkResponse('url_file',$res))
			$this->updateDebugBuffer('url_file',$url_adressbook,'GET');
		else 
			{
			$this->updateDebugBuffer('url_file',$url_adressbook,'GET',false);
			$this->debugRequest();
			$this->stopPlugin();
			return false;	
			}
		$url_file_contacts="http://adres.email.mynet.com/Exim/ExportFileDownload.aspx?format=microsoft_csv";
		$this->login_ok=$url_file_contacts;
		file_put_contents($this->getLogoutPath(),$base_url);		
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
		if ($this->checkResponse('file_contacts',$res))
			$this->updateDebugBuffer('file_contacts',$url,'GET');
		else 
			{
			$this->updateDebugBuffer('file_contacts',$url,'GET',false);
			$this->debugRequest();
			$this->stopPlugin();
			return false;	
			}
				
		$temp=$this->parseCSV($res);
		$contacts=array();
		foreach ($temp as $values)
			{
			$name=$values[0].(empty($values[1])?'':(empty($values[0])?'':'-')."{$values[1]}");
			if (!empty($values[9]))
				$contacts[$values[9]]=(empty($name)?$values[9]:$name);
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
			$url_logout=file_get_contents($this->getLogoutPath())."/webmail/src/signout.php";
			$res=$this->get($url_logout,true);
			}
		$this->debugRequest();
		$this->resetDebugger();
		$this->stopPlugin();
		}
	}
?>