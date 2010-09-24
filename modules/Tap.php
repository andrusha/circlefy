<?php

/*
    It's pretty much function library
    with all about Taps & Responses to them
*/
class Tap extends BaseModel {
    protected $id = null;
    protected $allowed = array('id', 'cid', 'uid', 'gid', 'all', 'responses');
    protected $allowedArarys = array('responses');

    public function __construct($id = null) {
        parent::__construct();

        if (is_array($id)) {
            //typecasting & tap formatting
            array_walk($id, array($this, 'typeCast'),
                array('cid', 'uid', 'mid', 'user_online', 'gid',
                      'count', 'private', 'chat_timestamp', 'chat_timestamp_raw'));
            $this->data = Tap::formatTap($id);
            $this->id = $id['cid'];
        } else 
            $this->id = $id;
    }

    public function __get($key) {
        if ($key == 'id')
            return $this->id;
        elseif ($key == 'all')
            return $this->data;
        else {
            if (array_key_exists($key, $this->data))
                return $this->data[$key];

            $name = 'get'.ucfirst($key);
            if (method_exists($this, $name)) {
                $this->data[$key] = $this->$name();
                return $this->data[$key];
            }
        }
    }

    public function __set($key, $val) {
        $this->data[$key] = $val;
    }

    /*
        Formats time since & tap text 
    */
    public static function formatTap($tap) {
        $tap['chat_timestamp'] = FuncLib::timeSince($tap['chat_timestamp']);
        $tap['chat_text'] = FuncLib::linkify(stripslashes($tap['chat_text']));

        return $tap;
    }

    /*
        Returns desiered tap (only one, if avaliable)

        $tap_id int
    */
    public static function byId($id, $group_info = true, $user_info = false, $last_resp = false) {
        $taps = TapsList::getTaps(array($id), $group_info, $user_info);
        if ($last_resp)
            $taps->lastResponses();
        return $taps->getFirst();
    }

    /*
        Creates new tap, return tap_id (cid/mid)
    */
    private static function add(User $from, $gid, $touid, $text) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $db->startTransaction();

        $cid = 0;
        try {
            //requires to get proper cid (OBSOLETE)
            $query = "INSERT
                        INTO channel (uid)
                      VALUES (#uid#)";
            $db->query($query, array('uid' => $from->uid));

            $cid = $db->insert_id;

            //makes tap appears online (OBSOLETE)
            $query = "INSERT
                        INTO TAP_ONLINE (cid)
                      VALUES (#cid#)";
            $db->query($query, array('cid' => $cid));

            $query = "INSERT
                        INTO special_chat (cid, uid, uname, chat_text, ip)
                      VALUES (#cid#, #uid#, #uname#, #chat_text#, INET_ATON(#ip#))";
            $db->query($query, array('cid' => $cid, 'uid' => $from->uid, 'uname' => $from->uname,
                'chat_text' => $text, 'ip' => $from->addr));

            //fulltext table duplicates everything
            $db->query(str_replace('special_chat', 'special_chat_fulltext', $query),
                array('cid' => $cid, 'uid' => $from->uid, 'uname' => $from->uname, 'chat_text' => $text,
                    'ip' => $from->addr));

            $query = "INSERT
                        INTO special_chat_meta (mid, gid, connected, uid, private)
                       VALUE (#cid#, #gid#, 1, #uid#, #private#)";
            $db->query($query, array('cid' => $cid, 'gid' => $gid, 'uid' => $touid, 'private' => $touid !== null));
        } catch (SQLException $e) {
            $db->rollback();
            throw $e;
        }

        $db->commit();

        return $cid;
    }

    /*
        @return Tap
    */
    public static function toGroup(Group $group, User $user, $text) {
        return Tap::byId(Tap::add($user, $group->gid, null, $text), true, false, true);
    }

    /*
        @return Tap
    */
    public static function toUser(User $from, User $to, $text) {
        return Tap::byId(Tap::add($from, null, $to->uid, $text), true, true, true);
    }

    /*
        Deletes Tap
    */
    public function delete() {
        $this->db->startTransaction();

        try {
            $this->db->query("DELETE FROM special_chat WHERE mid = {$cid}");
            $this->db->query("DELETE FROM special_chat_meta WHERE mid = {$cid}");
            $this->db->query("DELETE FROM special_chat_fulltext WHERE mid = {$cid}");
            
            //delete responses
            $this->db->query("DELETE FROM chat WHERE cid = {$cid}");

            //online info for tap
            $this->db->query("DELETE FROM TAP_ONLINE WHERE cid = {$cid}");

            //old stuff about active convo
            $this->db->query("DELETE FROM active_convo WHERE mid = {$cid}");
            $this->db->query("DELETE FROM active_convo_old WHERE mid = {$cid}");

            //no one likes deleted taps :'(
            $this->db->query("DELETE FROM good WHERE mid = {$mid}");
        } catch (SQLException $e) {
            $this->db->rollback();
            return false;
        }

        $this->db->commit();
        return true;
    }

    /*
        Returns bool if tap is duplicate or not
    */
    public static function checkDuplicate(User $u, $text) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $query = "SELECT chat_text
                    FROM special_chat
                   WHERE uid = #uid#
                   ORDER
                      BY mid DESC
                   LIMIT 1";
        $result = $db->query($query, array('uid' => $u->uid));

        $dupe = false;
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $dupe = $result['chat_text'] == $text;
        }

        return $dupe;
    }

    /*
        Returns responses to tap
    */
    public function getResponses() {
        $query = "
            SELECT c.mid, c.uid, l.uname, l.pic_36 as small_pic,
                   GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name,
                   c.chat_text, UNIX_TIMESTAMP(c.chat_time) AS chat_time_raw,
                   UNIX_TIMESTAMP(c.chat_time) AS chat_time, c.anon
             FROM chat c
            INNER
             JOIN login l 
               ON l.uid = c.uid
            WHERE c.cid = #tap_id#";
        $responses = array();
        $result = $this->db->query($query, array('tap_id' => $this->id));
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $responses[] = Tap::formatTap($res);
        
        return $responses;
    }

    public function responseDupe(User $user, $text) {
        $query = 'SELECT chat_text
                    FROM chat
                   WHERE cid = #cid#
                     AND uid = #uid#
                   ORDER
                      BY mid DESC
                   LIMIT 1';
        $result = $this->db->query($query, array('cid' => $this->id, 'uid' => $user->uid));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            if ($text == $result['chat_text'])
                return true;
        }
        return false;
    }

    public function addResponse(User $user, $text) {
        $query = "
            INSERT
              INTO chat (cid, uid, uname, chat_text)
            VALUES (#cid#, #uid#, #uname#, #text#)";
        $this->db->query($query,
            array('cid' => $this->id, 'uid' => $user->uid,
                  'uname' => $user->uname, 'text' => $text));
    }

    /*
        Make conversation (tap) active for specified user
    */
    public function makeActive(User $user, $status = 1) {
        $query = "INSERT 
                    INTO active_convo (mid, uid, active)
                  VALUES (#mid#, #uid#, #status#)
                      ON DUPLICATE KEY
                  UPDATE active = #status#";
        $this->db->query($query, array('uid' => $user->uid,
            'mid' => $this->id, 'status' => $status));
        return $this->db->affected_rows == 1;
    }

    /*
        Returns if convo active or not
        (1 | 0)
    */
    public function getStatus(User $u) {
        $query = "
            SELECT active
              FROM active_convo
             WHERE uid = #uid#
               AND mid = #mid#
             LIMIT 1";
        $active = 0;
        $result = $this->db->query($query, array('uid' => $u->uid, 'mid' => $this->id));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $active = intval($result['active']);
        }

        return $active;
    }

    /*
        Is user tap owner or group admin
    */
    public function checkPermissions(User $u) {
        $query = "
            SELECT ((s.uid = #uid#) OR (g.admin = 1)) AS ch 
              FROM special_chat s
             INNER
              JOIN special_chat_meta sc
                ON sc.mid = s.mid
              LEFT
              JOIN group_members g 
                ON g.gid = sc.gid AND g.uid = s.uid
             WHERE s.mid = #mid#";
        $perm = false;
        $res = $this->db->query($query, array('uid' => $u->uid, 'mid' => $this->id));
        if ($res->num_rows) {
            $res = $res->fetch_assoc();
            $perm = intval($res['ch']) == 1;
        }

        return $perm;
    }

    /*
        Checks if user left any taps in group
    */
    public static function firstTapInGroup(Group $g, User $u) {
        $db = DB::getInstance()->Start_Connection('mysql');
        $query = "SELECT sc.metaid
                    FROM special_chat_meta sc
                   INNER
                    JOIN special_chat s
                      ON s.mid = sc.mid
                   WHERE sc.gid = #gid#
                     AND s.uid = #uid#
                   LIMIT 1";
        $result = $db->query($query, array('gid' => $g->gid, 'uid' => $u->uid));
        $check = $result->num_rows == 0;
        return $check;
    }
};
