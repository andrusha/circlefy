<?php
/*
CALLS:
    public.js
*/

require('../config.php');
require('../api.php');

define('SUCCESS', json_encode(array('successful' => 1)));
define('FUCKED_UP', json_encode(array('successful' => 0)));

session_start();

$uid = $_SESSION['uid'];
$cid = $_POST['cid'];

if (intval($uid) && intval($cid)) {
    $instance = new tap_deleter(intval($uid));
    echo $instance->delete(intval($cid));
} else {
    echo FUCKED_UP;
}

class tap_deleter {
    private $uid;
    private $mysqli;

    public function __construct($uid) {
        $this->uid = $uid;
        $this->mysqli = new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

    /*
        Deletes Tap
        $cid - tap id
    */
    public function delete($cid) {
        //We gonna also fetch group (channel) id,
        //in case we'll notify users
        $result = $this->mysqli->query("
            SELECT s1.uid, s2.gid FROM special_chat s1
            INNER JOIN special_chat_meta s2 ON s1.mid = s2.mid
            WHERE s1.mid = {$cid}
            LIMIT 1");

        if (!$result->num_rows) //there isn't tap with this uid
            return FUCKED_UP;

        $result = $result->fetch_assoc();
        if ($this->uid != $result['uid']) //you can delete only your taps
            return  FUCKED_UP;

        $gid = intval($result['gid']);

        if ($this->mysqli->query('START TRANSACTION') !== True)
            throw new Exception("Guys, we fucked up, transactions doesn't work 8(");

        $nice = true;

        //delete tap
        $nice &&= $this->mysqli->query("DELETE FROM special_chat WHERE mid = {$cid}");
        $nice &&= $this->mysqli->query("DELETE FROM special_chat_fulltext WHERE mid = {$cid}");
        $nice &&= $this->mysqli->query("DELETE FROM special_chat_meta WHERE mid = {$cid}");
        
        //delete responses
        $nice &&= $this->mysqli->query("DELETE FROM chat WHERE cid = {$cid}");

        //online info for tap
        $nice &&= $this->mysqli->query("DELETE FROM TAP_ONLINE WHERE cid = {$cid}");

        //old stuff about active convo
        $nice &&= $this->mysqli->query("DELETE FROM active_convo WHERE mid = {$cid}");
        $nice &&= $this->mysqli->query("DELETE FROM active_convo_old WHERE mid = {$cid}");

        //no one likes deleted taps :'(
        $nice &&= $this->mysqli->query("DELETE FROM good WHERE mid = {$mid}");

        if (!$nice) {
            //something went wrong
            $this->mysqli->query("ROLLBACK");
            return FUCKED_UP;
        }

        //everything ok
//        $this->mysqli->query("COMMIT");

        $this->notifyAll($gid, $cid);

        return SUCCESS;
    }

    /*
        Notify all online tappers from specified group,
        that tap is deleted
    */
    private function notifyAll($gid, $cid) {
        $query = "
            SELECT g.uid
              FROM group_members g
             INNER
              JOIN TEMP_ONLINE t
                ON t.uid = g.uid
             WHERE t.online <> 0
               AND g.gid = {$gid}";

        $users = array();
        $result = $this->mysqli->query($query);
        while ($res = $result->fetch_assoc()) {
            $users[] = intval($res['uid']);
        }

        $data = array('gid' => intval($gid), 'cid' => intval($cid));
        $message = json_encode(array('action' => 'tap.delete', 'data' => $data,
                                     'users' => $users));

        $fp = fsockopen("localhost", 3333, $errno, $errstr, 30);
        fwrite($fp, $message."\r\n");
        fclose($fp);
    }
};

