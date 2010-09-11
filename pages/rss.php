<?php

class rss extends Base{

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
		$this->page_name = "rss";
	
		parent::__construct();
		
		
		$symbol = $_GET['symbol'];
        $taps = new Taps();

        if ($_GET['type'] == 'group') {
            $gid = Group::fromSymbol($symbol)->gid;

            $params = array('#outside#' => '1, 2', '#gid#' => $gid);
            $data_taps = $taps->getFiltered('ind_group', $params);
        } else if ($_GET['type'] == 'user') {
            $user = new User();
            $uid = $user->uidFromUname($symbol);

            $data_taps = $taps->getFiltered('personal', array('#outside#' => '1, 2', '#uid#' => $uid));
        } else if ($_GET['type'] == 'public') {
            $data_taps = $taps->getFiltered('public', array('#outside#' => '1, 2'));
        }

        $this->set($data_taps,'user_bits');

        //START set the session uid for Orbited
        $this->set($_SESSION['uid'],'pcid');
        //END set the session uid for Orbited
    }

};
