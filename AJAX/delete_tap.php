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
        Check if user allowed to delete tap,
        it should be tap owner or channel admin

        returns array(true|false, gid);
    */
    private function checkPermissions($cid) {
        $permitted = false;
        $gid = -1;
        
        //check if user is tap owner
        //we gonna also fetch group (channel) id,
        //in case we'll notify users
        $result = $this->mysqli->query("
            SELECT s1.uid, s2.gid FROM special_chat s1
            INNER JOIN special_chat_meta s2 ON s1.mid = s2.mid
            WHERE s1.mid = {$cid}
            LIMIT 1");

        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            if ($this->uid == $result['uid']) //you can delete only your taps
                $permitted = true;

            $gid = intval($result['gid']);
        } else {
            //there is no such group
            return array($permitted, $gid);
        }

        //check if user is moderator
        $result = $this->mysqli->query("
            SELECT admin
              FROM group_members
             WHERE gid = {$gid}
               AND uid = {$this->uid}");
        
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            if ($result['admin'] == 1)
                $permitted = true;
        }

        return array($permitted, $gid);
    }

    /*
        Deletes Tap
        $cid - tap id
    */
    public function delete($cid) {
        list($permitted, $gid) = $this->checkPermissions($cid);
        if (!$permitted)
            return FUCKED_UP;

        if ($this->mysqli->query('START TRANSACTION') !== True)
            throw new Exception("Guys, we fucked up, transactions doesn't work 8(");

		$nice = true;
		$optional = true;

        //delete tap
        $nice = $nice && $this->mysqli->query("DELETE FROM special_chat WHERE mid = {$cid}");
        $nice = $nice && $this->mysqli->query("DELETE FROM special_chat_meta WHERE mid = {$cid}");
        $optional = $optional && $this->mysqli->query("DELETE FROM special_chat_fulltext WHERE mid = {$cid}");	// Not always
        
        //delete responses
        $optional = $optional && $this->mysqli->query("DELETE FROM chat WHERE cid = {$cid}");		// Not always!

        //online info for tap
        $nice = $nice && $this->mysqli->query("DELETE FROM TAP_ONLINE WHERE cid = {$cid}");

        //old stuff about active convo
        $optional = $optional && $this->mysqli->query("DELETE FROM active_convo WHERE mid = {$cid}");
        $optional = $optional && $this->mysqli->query("DELETE FROM active_convo_old WHERE mid = {$cid}");

        //no one likes deleted taps :'(
        $optional = $optional && $this->mysqli->query("DELETE FROM good WHERE mid = {$mid}");		// Not Always!

        if (!$nice) {
            //something went wrong
            $this->mysqli->query("ROLLBACK");
            return FUCKED_UP;
        }

        //everything ok
        $this->mysqli->query("COMMIT");

        $this->notifyAll($gid, $cid);

        return SUCCESS;
    }

    /*
        Notify all online tappers from specified group,
        that tap is deleted
    */
    private function notifyAll($gid, $cid) {
        $data = array('gid' => intval($gid), 'cid' => intval($cid));
        $message = json_encode(array('action' => 'tap.delete', 'data' => $data, 'cid' => $cid));

        $fp = fsockopen("localhost", 3333, $errno, $errstr, 30);
        fwrite($fp, $message."\r\n");
        fclose($fp);
    }
};

