<?php
session_start();
/* CALLS:
	search_group.js
*/
require('../config.php');

/* TO DO: 

Add focus to results
Add Location to results
*/

$gname = $_POST['gname'];
$focus = $_POST['focus'];
$location = $_POST['location'];

if(isset($_POST['gname'])){
   	$search_function = new search_functions();
        $results = $search_function->search_group($gname,$focus);
        echo $results;
}


class search_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function search_group($gname){

                $uid = $_SESSION["uid"];

                $uid = $this->mysqli->real_escape_string($uid);
                $gname = $this->mysqli->real_escape_string($gname);

		//This query gets the group information based off of params supplied
		$create_group_query =  <<<EOF
   SELECT ugm.admin,ugm.gid,t2.gid,t2.gname,t2.connected,t2.focus,t2.pic_100,count(ugm.uid) AS size
		FROM (
		SELECT DISTINCT gm.admin,gm.uid,gs.gid FROM groups AS gs
		LEFT JOIN group_members AS gm ON gm.gid= gs.gid
		WHERE gs.gname LIKE '%abound%'
		GROUP BY gs.gid
		) AS ugm
		LEFT JOIN group_members AS t1 ON t1.gid=ugm.gid
		LEFT JOIN groups AS t2 ON t2.gid = ugm.gid
        GROUP BY ugm.gid;
EOF;

                $create_group_results = $this->mysqli->query($create_group_query);

		 while($res = $group_results->fetch_assoc()){
			$pic = $res['pic_100'];
			$gname = $res['gname'];
			$type = $res['connected'];
			$size = $res['size'];
			if($type)
				$official = "*";
			else    $official = "";

			$groups[] = array(
				'gid' => $gid,
				'gname' => $gname,
				'pic' => $pic,
				'type' => $type,
				'size' => $size,
				'official' => $official
			);
		}
	
		//If groups were found send them back, else send back no results
		if($groups)
			return json_encode(array('group_results' => $groups));
		else
			return json_encode(array('group_results' => NULL));
	}

}
