<?php

class public_tap extends Base{

	protected $text;
	protected $top;
	
	function __default(){
	}
	
	public function __toString(){
		return "Public User Object";
	}
	
	function __construct(){
				
		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->need_login = 1;
		$this->need_db = 1;
		$this->need_filter = 1;
		$this->input_debug_flag = 0;
		$this->page_name = "public_tap";
	
		parent::__construct();
		
		$mid = intval($_GET['mid']);
        $uid = intval($_SESSION['uid']);
    
        $taps = new Taps();
        $tap = $taps->getTap($mid);
		$responses = $taps->getResponses($mid);
        $last_resp = end($responses);
        $tap['responses'] = $responses;
        $tap['count'] = count($responses);
        $tap['resp_uname'] = $last_resp['uname'];
        $tap['last_resp'] = $last_resp['chat_text'];
        $involved = $this->makeInvolved($responses);

        $tapper_id = intval($tap['uid']);

        $user = new User();
        $stats = $user->getStats($tapper_id);
        $tapper = $user->getFullInfo($tapper_id);

        $convo = new Convos();
        $active = $convo->getStatus($uid, $mid);

		$this->set($tap, 'tap');
		$this->set($mid,'cid');
        $this->set($involved, 'involved');
		$this->set($uid,'uid');
		$this->set($uid,'pcid');
        $this->set($stats,'stats');
		$this->set($active,'active_convo');
        $this->set($tapper, 'user');
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
