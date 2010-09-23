<?php
/* CALLS:
    homepage.phtml
*/
$usage = <<<EOF
    PARAMS
    
    cid: the channel id of the current "tap" that you're trying to get responses from
EOF;

require('../../config.php');
require('../../api.php');

class chat extends Base {
    public function __construct() {
        $this->need_db = 0;
        $this->view_output = 'JSON';
        parent::__construct();

        $this->data = $this->load_response(intval($_POST['cid']));
    }

    private function load_response($cid){
        $tap = new Tap($cid);
        $responses = $tap->responses;
        if (count($responses))
            return array('success' => 1,'responses' => $responses);
        return array('success' => 0,'responses' => null);
    }
};

$smth = new chat();
