<?php
session_start();
/* CALLS:
	join_group.js
*/
require('../config.php');
require('../api.php');


$gid = $_POST['gid'];

if (isset($_POST['gid'])) {
    $join_function = new join_functions();
    $results = $join_function->join_group($gid);
    echo $results;
}


class join_functions {

    private $mysqli;
    private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
    private $results;

    function __construct() {
        $this->mysqli = new mysqli(D_ADDR, D_USER, D_PASS, D_DATABASE);
    }

    function join_group($gid) {

        $uid = $_SESSION["uid"];
        $uname = $_SESSION["uname"];

        $uid = $this->mysqli->real_escape_string($uid);
        $gid = $this->mysqli->real_escape_string($gid);

        //Set admin to 0 unless he was the creator of the group
        $admin = 0;
        $is_admin_query = <<<EOF
		SELECT gadmin FROM groups WHERE gid = {$gid} LIMIT 1
EOF;
        $is_admin_results = $this->mysqli->query($is_admin_query);
        if ($is_admin_results->num_rows)
            while ($res = $is_admin_results->fetch_assoc()) {
                $gadmin = $res['gadmin'];
                if ($gadmin == $uid)
                    $admin = 1;
            }

        $create_rel_query = "INSERT INTO group_members(uid,gid,admin,status) values('{$uid}',{$gid},{$admin},'1');";
        $create_rel_results = $this->mysqli->query($create_rel_query);
        $last_id = $this->mysqli->query($this->last_id);

        $last_id = $last_id->fetch_assoc();
        $last_id = $last_id['last_id'];
        if ($last_id > 0) {
            $this->send_email($gid, $uname);
            return json_encode(array('good' => 1));
        }
        return json_encode(array('good' => 0));
    }

    private function send_email($gid, $uname) {
        $get_admins_query = <<<EOF
			SELECT g.gname,l.email FROM group_members AS gm
			JOIN groups AS g ON gm.gid = g.gid
			JOIN login AS l ON l.uid = gm.uid
			JOIN settings AS s ON s.uid = l.uid
			WHERE gm.admin > 0 AND gm.gid = {$gid} AND s.join_group = 1
EOF;

        $get_admins_results = $this->mysqli->query($get_admins_query);
        if ($get_admins_results->num_rows)
            while ($res = $get_admins_results->fetch_assoc()) {
                $to = $res['email'];
                $gname = $res['gname'];

                $subject = "Your tap channel $gname has a new member!";
                $from = "From: tap.info\r\n";
                $body = <<<EOF
{$uname} joined your tap channel at http://tap.info !  It seems others have joined as well, so keep the real-time collaberative tapping going.

Make sure you organize your community or channel, so that everyone on tap will feel the communities greatness.

Feel free to invite more people and keep your good ( and popular ) community growing!

{$uname} ( and me! ) are glad to have you on tap. :)

-Team Tap
http://tap.info
EOF;
                mail($to, $subject, $body, $from);
            }
    }

}
