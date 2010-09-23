<?php
/* CALLS:
	homepage_tap_friend.js
	tap_friend.js
	invite.js
*/
require('../../config.php');
require('../../api.php');

session_start();

$gid = $_POST['gid'];
$type = $_POST['type'];
$online_only = $_POST['online_only'];

if(isset($gid) && isset($type)){
	// if($type == 0)	
	// $search_uname=null;
	$search_uname = ($type == 0) ? null : $_POST['search'];

	$instance = new grouplist_functions();
	echo $instance->get_userlist($gid,$type,$search_uname,$online_only);
}

class grouplist_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
		$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function get_userlist($gid,$type,$search_uname,$online_only){

  	        $uid = $_SESSION['uid'];
                $uname = $_SESSION['uname'];

		if($online_only != 'false')
			$online_only = 'AND o.online = 1';
		else
			$online_only = '';

		
                $gid = $this->mysqli->real_escape_string($gid);

		if($type == 0)
		$userlist_query = <<<EOF
		SELECT l.uname,l.pic_36 AS small_pic,o.online,gm.admin,gm.gid FROM group_members AS gm
		JOIN login AS l
		ON l.uid = gm.uid
		LEFT JOIN TEMP_ONLINE AS o
		ON o.uid = gm.uid
		WHERE gm.gid = {$gid} {$online_only}
EOF;
		if($type == 1)
		$userlist_query = <<<EOF
		SELECT l.uname,l.pic_36 AS small_pic,o.online,gm.admin,gm.gid FROM group_members AS gm
		JOIN login AS l
		ON l.uid = gm.uid
		LEFT JOIN TEMP_ONLINE AS o
		ON o.uid = gm.uid
		WHERE gm.gid = {$gid} AND l.uname LIKE '{$search_uname}%' {$online_only}
EOF;


                $userlist_results = $this->mysqli->query($userlist_query);
		while($res = $userlist_results->fetch_assoc()){
			$uname = $res['uname'];
			$small_pic = $res['small_pic'];
			$admin = $res['admin'];
			$online = $res['online'];

			$results[] = array(
				'uname' => $uname,
				'admin' => $admin,
				'small_pic' => $small_pic,
				'online' => $online,
			);
		}
			$results = json_encode(array('grouplist' => $results));
			return $results;
	}

}	
