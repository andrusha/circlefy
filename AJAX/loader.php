<?php
/* CALLS:
	homepage.phtml
*/
$usage = <<<EOF
Usage:
scid: id of the subcategory to remove
EOF;

session_start();
require('../config.php');
require('../api.php');

$mid = intval($_POST['id_list']);
	     
if(isset($mid)){
    $loader_function = new loader_functions();
    $res = $loader_function->loader($mid);
    api_json_choose($res,$cb_enable);
}else{
    api_usage($usage);
}

class loader_functions {
    function loader($id){
		$data = $this->create_loader($id);	
		if($data)	
			return array('results' => True,'data'=> $data );
		else
			return array('results' => False,'data' => False);
	}

	function create_loader($mid) {
		$uid = $_SESSION['uid'];

        $taps = new Taps();
        $tap = $taps->getTap($mid);
		$responses = $taps->getResponses($mid);
        $last_resp = end($responses);
        $tap['responses'] = $responses;
        $tap['count'] = count($responses);
        $tap['resp_uname'] = $last_resp['uname'];
        $tap['last_resp'] = $last_resp['chat_text'];

		return array($tap);
    }
};
