<?php

class public_tap extends Base{
	function __default(){}
	
	public function __toString(){
		return "Public User Object";
	}
	
	function __construct(){
		$this->need_login = 1;
		$this->need_db = 1;
		$this->page_name = "public_tap";
	
		parent::__construct();
		
		$mid = intval($_GET['mid']);
    
        $taps = new Taps();
        $tap = $taps->getTap($mid, true, true);

        if ($tap['private'] || empty($tap))
            if (($tap['uid'] != $this->user->uid && $tap['to_uid'] != $this->user->uid) || empty($tap)) {
                $this->set(array(), 'tap');
                $this->set(array(), 'involved');
                header('location: /');
                return;
            }


		$responses = $taps->getResponses($mid);
        $last_resp = end($responses);
        $tap['responses'] = $responses;
        $tap['count'] = count($responses);
        $tap['resp_uname'] = $last_resp['uname'];
        $tap['last_resp'] = $last_resp['chat_text'];
        $involved = $this->makeInvolved($responses);

        $tapper = new User(intval($tap['uid']));

        $convo = new Convos();
        $active = $convo->getStatus($this->user->uid, $mid);

		$this->set($tap, 'tap');
		$this->set($mid,'cid');
        $this->set($involved, 'involved');
		$this->set($this->user->uid,'uid');
		$this->set($active,'active_convo');
        $this->set($tapper->stats,'stats');
        $this->set($tapper->fullInfo, 'user');

        Action::log($this->user, 'tap', 'view', array('mid' => $mid));
    }
    
    /*
        Returns a list of involved in conversation persons
        from list of responses

        (uid, uname, real_name, small_pic, responses count)
    */
    private function makeInvolved($responses) {
        $persons = array();
        foreach($responses as $resp) {
            $uid = intval($resp['uid']);
            if (!isset($persons[$uid])) {
                $info = array_intersect_key($resp, 
                    array_flip(array('uid', 'uname', 'real_name', 'small_pic')));
                $persons[$uid] = $info;
                $persons[$uid]['count'] = 1;
            } else {
                $persons[$uid]['count'] += 1;
            }
        }

        return $persons;
    }

};
