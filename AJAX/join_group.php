<?php
session_start();
/* CALLS:
	join_group.js
*/
require('../config.php');
require('../api.php');

$action = isset($_POST['action']) ? $_POST['action'] : 'join';
$obj = new join_functions();

switch ($action) {
    case 'join':
        $gid = intval($_POST['gid']);
        $results = $obj->join_group($gid);
        break;

    case 'bulk':
        $gids = $_POST['gids'];
        $results = $obj->bulk_join($gids);
        break;
}

echo json_encode($results);

class join_functions {
    
    public function bulk_join(array $gids) {
        $uid = intval($_SESSION['uid']);

        $user = new User($uid);
        $groups = array();
        foreach ($gids as $gid)
            $groups[] = new Group(intval($gid));

        $status = Group::bulkJoin($user, $groups);
        return array('good' => $status);
    }

    function join_group($gid) {
        $gid = intval($gid);
        $uid = intval($_SESSION["uid"]);
        $uname = $_SESSION["uname"];

        $user = new User($uid);
        $group = new Group($gid);
        $status = $group->join($user);

        if ($status) {
            $this->send_email($gid, $uname);
            return array('good' => 1);
        }
        return array('good' => 0);
    }

    private function send_email($gid, $uname) {
        $mysqli = new mysqli(D_ADDR, D_USER, D_PASS, D_DATABASE);

        $get_admins_query = <<<EOF
			SELECT g.gname,l.email FROM group_members AS gm
			JOIN groups AS g ON gm.gid = g.gid
			JOIN login AS l ON l.uid = gm.uid
			JOIN settings AS s ON s.uid = l.uid
			WHERE gm.admin > 0 AND gm.gid = {$gid} AND s.join_group = 1
EOF;

        $get_admins_results = $mysqli->query($get_admins_query);
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
