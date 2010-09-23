<?php

class public_tap extends Base{
	
	function __construct(){
		$this->need_login = 1;
		$this->need_db = 0;
		$this->page_name = "public_tap";
		parent::__construct();
		
        $tap = Tap::byId(intval($_GET['mid']), true, true);

        if ($tap['private'] || empty($tap))
            if (($tap['uid'] != $this->user->uid && $tap['to_uid'] != $this->user->uid) || empty($tap)) {
                header('location: /');
                return;
            }

		$responses = $tap->responses;
        $tap->last = end($responses);
        $tap->count = count($responses);
        $involved = $this->makeInvolved($responses);

        $tapper = new User(intval($tap->uid));

		$this->set($tap->all, 'tap');
		$this->set($tap->id,'cid');
        $this->set($involved, 'involved');
		$this->set($this->user->uid,'uid');
		$this->set($tap->getStatus($this->user),'active_convo');
        $this->set($tapper->stats,'stats');
        $this->set($tapper->fullInfo, 'user');

        Action::log($this->user, 'tap', 'view', array('mid' => $tap->id));
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
