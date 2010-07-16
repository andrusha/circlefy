<?php
/* CALLS:
	edit_group.js
*/
$usage = <<<EOF
USAGE:
Creating a new Category
    type: 'catagory'
    name: name of the category
    symbol: symbol of the category
       
Saving Changes to a category 
    type: 'editCatagory',
    name: new name for the category
    id: subcategory id
    symbol: symbol of the category
EOF;

session_start();
require('../config.php');
require('../api.php');


$type = $_REQUEST['type'];
$symbol = $_REQUEST['symbol'];
$name = $_REQUEST['name'];
$id = $_REQUEST['id'];

if($type){
    $group_function = new group_functions(); 
    if($type == 'catagory'){
        $group_function->update_catagory();
        $res = $group_function->get_category_list($symbol);
        api_json_choose($res,$cb_enable);
    }else if($type == 'editCatagory'){
        $group_function->editCatagoryName();
        $res = $group_function->get_category_list($symbol);
        api_json_choose($res,$cb_enable);
    }else{
        api_usage($usage);
    }
}





class group_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function check_if_admin($gid,$uid){
                $check_if_admin_query = <<<EOF
                SELECT uid FROM group_members WHERE gid = {$gid} AND uid = {$uid} AND admin > 0
EOF;
                //echo "QUERY: $check_if_admin_query\n";
                $check_if_admin_results = $this->mysqli->query($check_if_admin_query);
                if($check_if_admin_results->num_rows)
                        return 1;
                else
                        return 0;
        }

        function update_catagory(){
	
        //get the input
		$symbol = $_POST['symbol'];
		$catagory = $_POST['name'];
        //user id
		$uid = $_SESSION['uid'];
        //commented because param is not pre*set
        //$gname = $this->mysqli->real_escape_string($gname);
		
        //echo "Preparing to insert...\n";

        //get group id
		$get_gid = <<<EOF
		SELECT gid FROM groups WHERE symbol = "{$symbol}"
EOF;
        $get_gid = $this->mysqli->query($get_gid);
		$gid = $get_gid->fetch_assoc();
		$gid = $gid['gid'];

		//echo "GID: $gid\n";
        //echo "UID: $uid\n";
		$is_admin = $this->check_if_admin($gid,$uid);
		if(!$is_admin) return false;

		$new_catagory_query = <<<EOF
			INSERT INTO sub_catagories(gid,catagory)
			values({$gid},"{$catagory}")
EOF;
		//echo "QUERY: $new_catagory_query\n";
			$catagory_results = $this->mysqli->query($new_catagory_query);

	}

        function editCatagoryName(){
            $scid = $_POST['id'];
            $catagory = $_POST['name'];
            
            $update_catagories = <<<EOF
                UPDATE sub_catagories SET catagory = '{$catagory}' WHERE scid = '{$scid}'
EOF;
            //echo $update_catagories;
            //exit;
            $update_categories_result = $this->mysqli->query($update_catagories);
        }
        
        function get_category_list($symbol){
            // We get the updated catagory list:
            $get_categories_by_group = <<<EOF
        select sc.catagory, g.symbol, sc.scid, g.gid 
        from sub_catagories as sc 
        left join groups as g 
        on sc.gid = g.gid
        where g.symbol='{$symbol}';
EOF;
                $list_catagory_results = $this->mysqli->query($get_categories_by_group) or die($this->mysqli->error());
                $catagory_list = array();
                while ($row = $list_catagory_results->fetch_assoc()) {
                    $catagory_list[] = array( 
                        'cname' => $row['catagory'], 
                        'scid' => $row['scid']);
                }
            
            $json_res = array('good' => 1, 'catagorylist' => $catagory_list);
            return $json_res;
        }
                    
}
?>