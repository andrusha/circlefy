<?php

class rss extends Base{
	function __construct(){
		$this->view_output = "HTML";
		$this->need_db = 0;
		$this->page_name = "rss";
	
		parent::__construct();
		
		$symbol = $_GET['symbol'];
        switch ($_GET['type']) {
            case 'group':
                $data_taps = TapsList::getFiltered('ind_group',
                    array('#outside#' => '1, 2',
                          '#gid#' => Group::fromSymbol($symbol)->gid))
                          ->filter('all');
                break;

            case 'user':
                $data_taps = TapsList::getFiltered('personal',
                    array('#outside#' => '1, 2',
                          '#uid#' => User::fromUname($symbol)->uid))
                          ->filter('all');
                break;

            case 'public':
                $data_taps = TapsList::getFiltered('public',
                    array('#outside#' => '1, 2'))
                          ->filter('all');
                break;
        }

        $this->set($data_taps,'user_bits');
    }
};
