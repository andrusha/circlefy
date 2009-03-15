<?php
/*
 * Created on Feb 10, 2009 by Vlad
 */
 
$_pluginInfo=array(
	'name'=>'Badoo',
	'version'=>'1.0.0',
	'description'=>"Get the contacts from a badoo.com account",
	'base_version'=>'1.6.7',
	'type'=>'social',
	'check_url'=>'http://www.badoo.com/'
	);
class badoo extends OpenInviter_Base
	{
	private $login_ok=false;
	public $showContacts=true;
	public $requirement='email';
	public $internalError=false;
	public $allowed_domains=false;
	protected $timeout=30;
	
	public $debug_array=array(
				'login_post'=>'http://badoo.com/signout',
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
		$this->service='badoo';
		$this->service_user=$user;
		$this->service_password=$pass;
	
		if (!$this->init()) return false;

		$res = $this->get('http://badoo.com/?lang_id=3',true);
		$url = $this->getElementString($res,'<a href="http://badoo.com/signin/','" class="sign_in">');
		$res = $this->get("http://badoo.com/signin/".$url,true);
		$post_elements=array();
		$post_elements['email']=$user;
		$post_elements['password']=$pass;
		$post_elements['post']='';
		$res = $this->post("http://badoo.com/signin/",$post_elements,true);
		if ($this->checkResponse("login_post",$res))
			$this->updateDebugBuffer('login_post',"http://www.kincafe.com/signin.fam",'POST');		
		else
			{
			$this->updateDebugBuffer('login_post',"http://www.kincafe.com/signin.fam",'POST',false);
			$this->debugRequest();
			$this->stopPlugin();
			return false;
			}
		$p = $this->getElementString($res, '<span class="dropdown_item_css">','</span>');
		$p = $this->getElementString($p, '<a href="', '"');
		$id = $this->getElementString($p,'badoo.com/','/');
		if (!is_numeric($id)) return false;
		else	$this->login_ok = $id;
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
		else $id=$this->login_ok;	
		$page = 1;
		$continue = true;
		$contacts=array();
		while($continue)
			{		
			$url = "http://badoo.com/{$id}/contacts/subscriptions.phtml?page={$page}";
			$res = $this->get($url,true);
			$doc=new DOMDocument();libxml_use_internal_errors(true);if (!empty($res)) $doc->loadHTML($res);libxml_use_internal_errors(false);
			$xpath=new DOMXPath($doc);$query="//p[@class='name']";$data=$xpath->query($query);
			$names = array();
			foreach($data as $node)
				{
				$td=$node->childNodes;
				$name = $td->item(1)->nodeValue;
				$names[] = $name;
				}
			$query="//a[@class='msgr-lnk old_messages']";$data=$xpath->query($query);
			$links = array();
			foreach ($data as $node)			$links[] = $node->getAttribute('href');
			if (count($names) != count($links)) return false;
			$c = count($names);
			for ($i=0;$i<$c;$i++) $contacts[$links[$i]] = $names[$i];
			if (stripos($res,'<strong>Next') === false) $continue = false;
			else $page++; 
			}
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
		foreach($contacts as $url=>$name)
			{
			$res = $this->get($url);
			$master_id = $this->getElementString($url,'http://badoo.com/','/'); 
			$post_url = "http://badoo.com/{$master_id}/contacts/ws-post.phtml";
			$post_elements = array();
			$post_elements = $this->getHiddenElements($res);
			$post_elements['flash'] = '1';
			$post_elements['message'] = $message['subject']."<br>".$message['body']."<br>".$message['attachment'];
			$res = $this->post($post_url,$post_elements,true);
			}
		return true;
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
		$res=$this->get("http://badoo.com/signout/");
		$this->debugRequest();
		$this->resetDebugger();
		$this->stopPlugin();
		return true;	
		}
	}	
?>
