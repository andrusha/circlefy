<?php
/* CALLS:
	homepage.phtm
*/
$usage = <<<EOF
Usage:
scid: id of the subcategory to remove
EOF;

session_start();
require('../config.php');
require('../api.php');


$catagory = $_REQUEST['scid'];
$uid = $_SESSION['uid'];      

if($catagory){
    $remove_catagory = new remove_catagory();
    $gid = $remove_catagory->get_group_by_catagory($catagory);
    $remove_catagory->remove_catagory($catagory, $uid);
    $res = $remove_catagory->get_catagories($gid);
    api_json_choose($res,$cb_enable);
}else{
    api_usage($usage);
} 

class remove_catagory{
    private $mysqli;
    private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
    
    function __construct(){
        $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

	function remove_catagory($scid,$uid){
		$remove_catagory_query = "DELETE FROM sub_catagories where scid = $scid";
		$this->mysqli->query($remove_catagory_query);
	}

    function get_group_by_catagory($scid){
        $get_gid = <<<EOF
        SELECT gid FROM sub_catagories WHERE scid = "{$scid}"
EOF;
        $get_gid = $this->mysqli->query($get_gid);
        $gid = $get_gid->fetch_assoc();
        return $gid['gid'];
    }
    
    function get_catagories($gid){        
        $list_catagory_query = <<<EOF
            SELECT scid, catagory FROM sub_catagories WHERE gid='{$gid}'
EOF;
        $list_catagory_results = $this->mysqli->query($list_catagory_query) or die($this->mysqli->error());
        $catagory_list = array();
        while ($row = $list_catagory_results->fetch_assoc()) {
            $catagory_list[] = array( 
                'cname' => $row['catagory'], 
                'scid' => $row['scid']);
        }
        return array('good' => 1, 'catagorylist' => $catagory_list);
    }
}
?>
