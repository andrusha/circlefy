<?php
class openGraph {

	var $session;
	var $uid;
	var $me;
	var $loginUrl;
	var $logoutUrl;
	var $facebook;


	function openGraph(){
		require './modules/facebook/facebook.php';
		// Create our Application instance.
		$this->facebook = new Facebook(array(
		  'appId'  => FBAPPID,
		  'secret' => FBAPPSECRET,
		  'cookie' => true,
		));
        $extended_permissions = array(
            'ext_perm'=>'read_stream'
        );
		$this->session = $this->facebook->getSession();
		//var_dump($this->session);
		if(!is_null($this->session)){
			$me = null;
			if ($this->session) {
				try{
					$this->uid = $this->facebook->getUser();
					$this->me = $this->facebook->api('/me');
					$this->logoutUrl = $this->facebook->getLogoutUrl();
				}catch (FacebookApiException $e) {
					//error_log($e);
				}
			}else{
				$this->logoutUrl = $this->facebook->getLogoutUrl();
			}
		}else{
			$this->session = false;
			
		}
        $this->loginUrl = $this->facebook->getLoginUrl($extended_permissions);
        //var_dump($this->loginUrl);
        //exit;
	}

	public function getInterests(){
        
        
		if($this->session){
			$interests = $this->facebook->api('/me/interests');
            
            //var_dump($interests);
            //exit;
			$arr_int = array();
			foreach($this->me['work'] as $work){
				$arr_int[] = $work['employer']['name'];
			}
			foreach($this->me['education'] as $education){
				$arr_int[] = $education['school']['name'];
			}
			return $arr_int;
		}else{
			return false;
		}
	}
}