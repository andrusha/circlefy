<?php
include('openinviter.php');

$rm = $_SERVER['REQUEST_METHOD'];
$email = $_POST['email_box'];
$passowrd = $_POST['password_box'];
$provider = $_POST['provider_box'];
$session = $_POST['oi_session'];
$step = $_POST['step'];

$invite_obj = new invite_functions();
if ($provider != '') 
{
	if(isset($invite_obj->oi_services['email'][$provider]))
		$plugType='email';
	elseif(isset($invite_obj->oi_services['social'][$provider]))
		$plugType='social';
	else
		$plugType='';
}	else	$plugType='';

if (!empty($step))
	$invite_obj->step=$step;
else
	$invite_obj->step='get_contacts';

if($session)
	$invite_obj->session = $session;

if ($rm == 'POST')
	$results = $invite_obj->action();

if($invite_obj->err())
	echo json_encode(array('message' => $invite_obj->err(),'type'=>'error'));
elseif($invite_obj->ok())
	echo json_encode(array('message' => $invite_obj->ok(),'type'=>'success'));

if (!$invite_obj->done){
	$contents .= $invite_obj->not_done_actions($plugType);
}

if(!$invite_obj->err() && !$invite_obj->done)
echo json_encode(array('message'=>$contents,'type'=>'get_contacts'));

class invite_functions {
	private $err=array();
	private $ok=array();
	private $contacts=array();
	private $import_ok=false;
	public $done=false;
	public $session;

	public $step;

	private $inviter;
	public $oi_service;

	function __construct(){
		$this->inviter=new OpenInviter();
		$this->oi_services= $this->inviter->getPlugins();
	}

	public function err(){
		$err = $this->err;
		if (!empty($err)){
                foreach ($err as $key=>$error)
                        $contents.=$error;
                return $contents;
                }
	}
	
	public function ok(){
	$ok = $this->ok;
	if (!empty($ok))
		{
		foreach ($ok as $key=>$msg)
			$contents.="$msg";
		return $contents;
		}
	}

	function action(){

	if ($this->step=='get_contacts'){
		if (empty($_POST['email_box']))
			$this->err['email']="Email missing";
		if (empty($_POST['password_box']))
			$this->err['password']="Password missing";
		if (empty($_POST['provider_box']))
			$this->err['provider']="Provider missing";
		if (count($this->err)==0)
			{
			$this->inviter->startPlugin($_POST['provider_box']);
			$internal=$this->inviter->getInternalError();
			if ($internal)
				$this->err['inviter']=$internal;
			elseif (!$this->inviter->login($_POST['email_box'],$_POST['password_box']))
				{
				$internal=$this->inviter->getInternalError();
				$this->err['login']=( $internal ? $internal:"Login failed. Please check the email and password you have provided and try again later");
				}
			elseif (false===$this->contacts=$this->inviter->getMyContacts())
				$this->err['contacts']="Unable to get contacts.";
			else
				{
				$import_ok=true;
				$this->step='send_invites';
				$this->session =$this->inviter->plugin->getSessionID();
				$_POST['message_box']='';
				}
			}

	} elseif ($this->step=='send_invites') {
		if (empty($_POST['provider_box'])) $this->err['provider']='Provider missing';
		else
			{
			$this->inviter->startPlugin($_POST['provider_box']);
			$internal=$this->inviter->getInternalError();
			if ($internal) $this->err['internal']=$internal;
			else
				{
				if (empty($_POST['email_box'])) $this->err['inviter']='Inviter information missing';
				if (empty($this->session)) $this->err['session_id']='No active session';
//				if (empty($_POST['message_box'])) $this->err['message_body']='Message missing';
//				else $_POST['message_box']=strip_tags($_POST['message_box']);
				$selected_contacts=array();
				$message=array('subject'=>$this->inviter->settings['message_subject'],'body'=>$this->inviter->settings['message_body'],'attachment'=> $_POST['message_box']);
				if ($this->inviter->showContacts())
					{
					foreach ($_POST as $key=>$val)
						if (strpos($key,'check_')!==false)
							$selected_contacts[$_POST['email_'.$val]]=$_POST['name_'.$val];
						elseif (strpos($key,'email_')!==false)
							{
							$temp=explode('_',$key);$counter=$temp[1];
							if (is_numeric($temp[1])) $this->contacts[$val]=$_POST['name_'.$temp[1]];
							}
					if (count($selected_contacts)==0) $this->err['contacts']="You haven't selected any contacts to invite";
					}
				}
			}
		if (count($this->err)==0)
			{
			var_dump($selected_contacts);
			$sendMessage=$this->inviter->sendMessage($this->session,$message,$selected_contacts);
			$this->inviter->logout();
			if ($sendMessage===-1)
				{
				$message_footer="\r\n\r\n2009";
				$message_subject=$_POST['email_box'].$message['subject'];
				$message_body=$message['body'].$message['attachment'].$message_footer; 
				$headers="From: {$_POST['email_box']}";
				foreach ($selected_contacts as $email=>$name)
					mail($email,$message_subject,$message_body,$headers);
				$this->ok['mails']="Mails sent successfully";
				}
			elseif ($sendMessage===false)
				{
				$internal=$this->inviter->getInternalError();
				$this->err['internal']=($internal?$internal:"There were errors while sending your invites.<br>Please try again later!");
				}
			else $this->ok['internal']="Invites sent successfully!";
			$this->done=true;
			}
		}
	}

	function not_done_actions($plugType){
		if ($this->step=='send_invites'){
			if ($this->inviter->showContacts()){
			if (count($this->contacts)==0)
				$something = 1;//testing
			else
				{
				$odd=true;$counter=0;
				$contents .= "<input type='checkbox' onChange='toggleAll(this)' name='toggle_all' title='Select/Deselect all' checked>Invite";
				foreach ($this->contacts as $email=>$name)
					{
					$counter++;
						$contents .= "<input name='check_{$counter}' value='{$counter}' type='checkbox' class='thCheckbox' checked><input type='hidden' name='email_{$counter}' value='{$email}'><input type='hidden' name='name_{$counter}' value='{$name}'>{$name}";
					$odd=!$odd;
					}
					$contents .= "<span id='oi_session'>$this->session</span>";
				}
			}
		}
	return $contents;	
	}
}
?>
