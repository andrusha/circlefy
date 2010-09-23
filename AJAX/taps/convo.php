<?php
/* CALLS:
	homepage.phtml
*/
$usage = <<<EOF
Usage:
scid: id of the subcategory to remove
EOF;

require('../../config.php');
require('../../api.php');

class loader extends Base {
    public function __construct() {
        $this->need_db = 0;
        $this->view_output = 'JSON';
        parent::__construct();
            
        $this->data = $this->loader(intval($_POST['id_list']));
    }

    function loader($id) {
        $tap = Tap::byId($mid);
		$responses  = $tap->responses;
        $tap->last  = end($responses);
        $tap->count = count($responses);
        $data = $tap->all;

        if($data)
			return array('results' => True,'data'=> $data);
        return array('results' => False,'data' => False);
	}
};

$something = new loader();
