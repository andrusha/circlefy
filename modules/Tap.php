<?php

/*
    It's pretty much function library
    with all about Taps & Responses to them
*/
class Tap extends BaseModel {

    public static $fields = array('id', 'sender_id', 'text', 'time', 'group_id', 'reciever_id', 'media_id');

    public static $replyFields = array('id', 'message_id', 'user_id', 'text', 'time');

    public static $mediaFields = array('id', 'type', 'link');

    public static $mediaTypes  = array('youtube' => 1, 'vimeo' => 2, 'flickr' => 3);

    protected static $intFields = array('id', 'sender_id', 'time', 'group_id', 'reciever_id', 'media_id');

    protected static $addit = array('responses', 'group', 'sender', 'reciever', 'media', 'replies');

    /*
        Returns desiered tap (only one, if avaliable)

        $tap_id int
    */
    public static function byId($id, $group_info = true, $user_info = false, $last_resp = false) {
        $taps = TapsList::search('byId', array('id' => $id), ($group_info ? T_GROUP_INFO : 0) | T_USER_INFO | ($user_info ? T_USER_RECV : 0));
        if ($last_resp)
            $taps->lastResponses();
        return $taps->getFirst();
    }

    /*
        Creates new tap, return tap_id (cid/mid)

        @return int
    */
    private static function add(User $from, $text, Group $g = null, User $to = null) {
        $db = DB::getInstance();

        $id = $this->db->insert('message', 
            array('sender_id' => $from->id, 'text' => $text,
                  'group_id' => $g ? $g->id : null,
                  'reciever_id' => $to ? $to->id : null));

        return $id;
    }

    /*
        @return Tap
    */
    public static function toGroup(Group $group, User $user, $text) {
        return Tap::byId(Tap::add($user, $text, $group, null), true, false, true);
    }

    /*
        @return Tap
    */
    public static function toUser(User $from, User $to, $text) {
        return Tap::byId(Tap::add($from, $text, null, $to), true, true, true);
    }

    /*
        Deletes Tap
    */
    public function delete() {
        $this->db->startTransaction();

        try {
            $this->db->query("DELETE FROM conversations WHERE message_id = ".$this->id);
            $this->db->query("DELETE FROM reply         WHERE message_id = ".$this->id);
            $this->db->query("DELETE FROM message       WHERE id = ".$this->id);
        } catch (SQLException $e) {
            $this->db->rollback();
            return false;
        }

        $this->db->commit();
        return true;
    }

    /*
        Returns bool if last tap from user is duplicate or not
    */
    public static function checkDuplicate(User $u, $text) {
        $db = DB::getInstance();

        $query = "SELECT text 
                    FROM message
                   WHERE user_id = #uid#
                   ORDER
                      BY id DESC
                   LIMIT 1";
        $result = $db->query($query, array('uid' => $u->id));

        $dupe = false;
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $dupe = $result['text'] == $text;
        }

        return $dupe;
    }

    /*
        Returns responses to tap

        @return array
    */
    public function getResponses() {
        $fields   = FuncLib::addPrefix('r.', Tap::$replyFields);
        $fields   = array_merge($fields, FuncLib::addPrefix('u.', User::$fields));
        $fields   = implode(', ', array_unique($fields));

        $query = "
            SELECT {$fields}
              FROM reply r
            INNER
             JOIN user u
               ON u.id = r.user_id
            WHERE r.message_id = #tap_id#";

        $responses = array();
        $result = $this->db->query($query, array('tap_id' => $this->id));
        foreach (DB::getSeparator($result, array('u')) as $line) {
            $resp = $line['rest'];
            $resp['user'] = new User($line['u']);
            $responses[] = $resp;
        }
        
        return $responses;
    }

    public function responseDupe(User $user, $text) {
        $query = 'SELECT text
                    FROM reply 
                   WHERE message_id = #cid#
                     AND user_id = #uid#
                   ORDER
                      BY id DESC
                   LIMIT 1';
        $result = $this->db->query($query, array('cid' => $this->id, 'uid' => $user->id));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            if ($text == $result['text'])
                return true;
        }
        return false;
    }

    public function addResponse(User $user, $text) {
        return $this->db->insert('reply', array(
            'message_id' => $this->id, 'user_id' => $user->id,
            'text' => $text));
    }

    /*
        Make conversation (tap) active for specified user
    */
    public function makeActive(User $user, $status = 1) {
        $query = "INSERT 
                    INTO conversations (message_id, user_id, active)
                  VALUES (#mid#, #uid#, #status#)
                      ON DUPLICATE KEY
                  UPDATE active = #status#";
        return $this->db->query($query, array('uid' => $user->id,
                        'mid' => $this->id, 'status' => $status))
                    ->affected_rows == 1;
    }

    /*
        Returns if convo active or not
        (1 | 0)
    */
    public function getStatus(User $u) {
        $query = "
            SELECT active
              FROM conversations 
             WHERE user_id = #uid#
               AND message_id = #mid#
             LIMIT 1";
        $active = 0;
        $result = $this->db->query($query, array('uid' => $u->id, 'mid' => $this->id));
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
            SELECT ((m.user_id = #uid#) OR (gm.permission >= #perm#)) AS ch
              FROM message m
              LEFT
              JOIN group_members gm 
                ON gm.group_id = m.group_id AND gm.user_id = m.user_id
             WHERE m.id = #mid#";
        $perm = false;
        $res = $this->db->query($query, array('uid' => $u->id, 'mid' => $this->id,
                                              'perm' => Group::$permissions['moderator']));
        if ($res->num_rows) {
            $res = $res->fetch_assoc();
            $perm = intval($res['ch']) == 1;
        }

        return $perm;
    }
};
