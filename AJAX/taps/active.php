<?php
/* CALLS:
	homepage.phtml
*/
$usage = <<<EOF
Usage:
cid: id of the active combo
EOF;

require('../../config.php');
require('../../api.php');

class active extends Base {
    public function __construct() {
        $this->need_db = false;
        $this->view_output = 'JSON';
        parent::__construct();

        $mid    = intval($_POST['cid']);
        $status = intval($_POST['status']);
        if ($status) {
            $tap = Tap::byId($mid);
            $tap->makeActive($this->user, $status);
            $this->data = array('successful' => 1, 'data' => $tap->all);
        } else {
            $tap = new Tap($mid);
            $tap->makeActive($this->user, $status);
            $this->data = array('successful' => 1);
        }
    }
};

$smth = new active();
