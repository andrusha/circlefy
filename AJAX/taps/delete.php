<?php
/*
CALLS:
    public.js
*/

require('../../config.php');
require('../../api.php');

class tap_deleter extends Base {
    public function __construct() {
        $this->need_db = false;
        $this->view_output = 'JSON';
        parent::__construct();

        $cid = intval($_POST['cid']);
        
        $tap = new Tap($cid);
        if ($tap->checkPermissions($this->user)) {
            $tap->delete();
            $this->data = array('successful' => 1);
        } else {
            $this->data = array('successful' => 0);
        }
    }

    /*
        Notify all online tappers from specified group,
        that tap is deleted
    */
    private function notifyAll(Tap $t) {
        $message = array('action' => 'tap.delete', 'data' => array('cid' => $tap->id), 'cid' => $cid);
        Comet::send('message', $message);
    }
};

$t = new tap_deleter();
