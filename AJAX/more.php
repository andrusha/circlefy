<?php
/* CALLS:
	homepage.phtml
*/
$usage = <<<EOF
	PARAMS:

	cid
EOF;

session_start();
require('../config.php');
require('../api.php');

if($cb_enable){
	$cid = $_GET['cid'];
	$uid = $_GET['uid'];
} else {
	$cid = $_POST['cid'];
	$uid = $_POST['uid'];
}


if(isset($cid)){
   	$more_function = new more_functions();
        $res = $more_function->more($cid,$uid);
	api_json_choose($res,$cb_enable);
}


class more_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function more($cid,$uid){

                $fuid = $_SESSION["uid"];
                $uname = $_SESSION["uname"];

                $uid = $this->mysqli->real_escape_string($uid);
                $gid = $this->mysqli->real_escape_string($gid);

		$create_rel_query = "INSERT INTO more(fuid,uid,mid) values({$fuid},{$uid},{$cid});";
                $create_rel_results = $this->mysqli->query($create_rel_query);
		$last_id = $this->mysqli->query($this->last_id);

                $last_id = $last_id->fetch_assoc();
                $last_id = $last_id['last_id'];
		if($last_id > 0)
			return array('more' => 1);
	}

}
