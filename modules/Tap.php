<?php

/*
    It's pretty much function library
    with all about Taps & Responses to them
*/
class Tap extends BaseModel {

    public static $fields = array('id', 'sender_id', 'text', 'time', 'group_id', 'reciever_id', 'media_id');

    public static $replyFields = array('id', 'message_id', 'user_id', 'text', 'time');

    public static $mediaFields = array('id', 'type', 'link', 'code', 'title', 'description', 'thumbnail_url', 'fullimage_url');

    public static $mediaTypes  = array('youtube' => 1, 'vimeo' => 2, 'flickr' => 3);

    protected static $intFields = array('id', 'sender_id', 'time', 'group_id', 'reciever_id', 'media_id');

    protected static $addit = array('responses', 'group', 'sender', 'reciever', 'media', 'replies', 'involved');

    protected static $tableName = 'message';

    public function format() {
        if (isset($this->data['time'])) {
            $this->data['timestamp'] = strtotime($this->data['time']);
            $this->data['time'] = FuncLib::timeSince($this->data['timestamp']);
        }

        if (isset($this->data['text']))
            $this->data['text'] = FuncLib::linkify(stripslashes($this->data['text']));

        if (isset($this->data['responses']))
            if (isset($this->data['responses']['text']))
                $this->data['responses']['text'] = FuncLib::makePreview($this->data['responses']['text'], 40);
        
        if (isset($this->data['replies']))
            foreach ($this->data['replies'] as &$r) {
                $r['text'] = FuncLib::linkify($r['text']);
                $r['timestamp'] = is_int($r['time']) ? $r['time'] : strtotime($r['time']);
                $r['time'] = FuncLib::timeSince($r['timestamp']);
                if ($r['user'] instanceof User)
                    $r['user'] = $r['user']->asArray();
            }

        return $this;
    }

    /*
        Returns desiered tap (only one, if avaliable)

        $tap_id int
    */
    public static function byId($id, $group_info = true, $user_info = false, $last_resp = false, $media = false) {
        $taps = TapsList::search('byId', array('id' => $id), ($group_info ? T_GROUP_INFO : 0) | T_USER_INFO | ($user_info ? T_USER_RECV : 0) | ($media ? T_MEDIA : 0));
        if ($last_resp)
            $taps->lastResponses();
        return $taps->getFirst();
    }

    /*
        Creates new tap, return tap_id (cid/mid)

        @return int
    */
    private static function add(User $from, $text, $media = null, Group $g = null, User $to = null) {
        $db = DB::getInstance();

        if ($media) {
            // Remove the URL of the media published
            $text = str_replace($media['link'], '', $text);

            $media_id = $db->insert('media',
                array('type' => $media['type'], 
                      'link' => $media['link'], 
                      'code' => $media['code'],
                      'title' => $media['title'],
                      'description' => $media['description'],
                      'thumbnail_url' => $media['thumbnail_url'],
                      'fullimage_url' => $media['fullimage_url']));
        }
        
        
        $id = $db->insert('message', 
            array('sender_id' => $from->id, 'text' => $text,
                  'media_id' => $media_id ? $media_id : null,
                  'group_id' => $g ? $g->id : null,
                  'reciever_id' => $to ? $to->id : null));

        return $id;
    }

    /*
        @return Tap
    */
    public static function toGroup(Group $group, User $user, $text, $media) {
        return Tap::byId(Tap::add($user, $text, $media, $group, null), true, false, true);
    }

    /*
        @return Tap
    */
    public static function toUser(User $from, User $to, $text, $media) {
        return Tap::byId(Tap::add($from, $text, $media, null, $to), true, true, true);
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
                   WHERE sender_id = #uid#
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
    public function getReplies($asArray = false) {
        $fields   = FuncLib::addPrefix('r.', Tap::$replyFields);
        $fields   = array_merge($fields, FuncLib::addPrefix('u.', User::$fields));
        $fields   = implode(', ', array_unique($fields));

        $query = "
            SELECT {$fields}
              FROM reply r
            INNER
             JOIN user u
               ON u.id = r.user_id
            WHERE r.message_id = #tap_id#
            ORDER BY r.id ASC";

        $responses = array();
        $result = $this->db->query($query, array('tap_id' => $this->id));
        foreach (DB::getSeparator($result, array('u'), md5($query)) as $line) {
            $resp         = $line['rest'];
            $resp['user'] = $asArray ? $line['u'] : new User($line['u']);
            $responses[]  = $resp;
        }
        
        $this->data['replies'] = $responses;
        return $this;
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
        $id = $this->db->insert('reply', array(
            'message_id' => $this->id, 'user_id' => $user->id,
            'text' => $text));

        if (!isset($this->data['replies']))
            $this->data['replies'] = array();

        $this->data['replies'][] = array(
            'id'         => $id,
            'message_id' => $this->id,
            'user_id'    => $user->id,
            'text'       => $text,
            'time'       => time(),
            'user'       => $user);

        return $this;
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
