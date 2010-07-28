<?php
session_start();
/* CALLS:
	join_group.js
*/
require('../config.php');
require('../api.php');


$gid = $_POST['gid'];
$uid = $_POST['uid'];
$action = $_POST['action']; 

if (isset($_POST['gid'])) {
    $join_function = new accept_functions();
    
    if ($action == 'accept'){
        $results = $join_function->accept($uid, $gid);
    } else {
        $results = $join_function->decline($uid, $gid);
    }
    echo $results;
}


class accept_functions {

    private $mysqli;
    private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
    private $results;

    function __construct() {
        $this->mysqli = new mysqli(D_ADDR, D_USER, D_PASS, D_DATABASE);
    }

    public function accept($uid, $gid){
        $gid = $this->mysqli->real_escape_string($gid);
        $uid = $this->mysqli->real_escape_string($uid);

        $q = "UPDATE group_members SET status = 1 WHERE gid = {$gid} AND uid = {$uid}";

        $res = $this->mysqli->query($q);

        if ($res){
            $this->sendAcceptEmail($uid, $gid);
            return json_encode(array('good' => 1));
        } else {
            return json_encode(array('good' => 0));
        }
    }

    public function decline($uid, $gid){
        $gid = $this->mysqli->real_escape_string($gid);
        $uid = $this->mysqli->real_escape_string($uid);

        $q = "DELETE FROM group_members WHERE gid = {$gid} AND uid = {$uid}";

        $res = $this->mysqli->query($q);

        if ($res){
            $this->sendDeclineEmail($uid,$gid);
            return json_encode(array('good' => 1));
        } else {
            return json_encode(array('good' => 0));
        }
    }

    private function sendAcceptEmail($uid, $gid){
        $to = $this->getUserEmail($uid);

        if (!$to){
            return;
        }

        $gname = $this->getGroupName($gid);

        $subject = "Your requst to join group $gname accepted!";
        $from = "From: tap.info\r\n";
        $body = <<<EOF
Your requst to join group $gname accepted!

-Team Tap
http://tap.info
EOF;
        mail($to, $subject, $body, $from);
    }

    private function sendDeclineEmail($uid, $gid){
        $to = $this->getUserEmail($uid);

        if (!$to){
            return;
        }

        $gname = $this->getGroupName($gid);

        $subject = "Your requst to join group $gname declined.";
        $from = "From: tap.info\r\n";
        $body = <<<EOF
Your requst to join group $gname declined.

-Team Tap
http://tap.info
EOF;
        mail($to, $subject, $body, $from);
    }

    private function getUserEmail($uid){
        $q = "SELECT email FROM login WHERE uid = {$uid}";
        $res = $this->mysqli->query($q);
        $u = $res->fetch_assoc();

        return $u['email'];
    }

    private function getGroupName($gid){
        $q = "SELECT gname FROM group WHERE gid = {$gid}";
        $res = $this->mysqli->query($q);
        $g = $res->fetch_assoc();

        return $g['gname'];
    }

}
