<?php

/*
    It's pretty much function library
    with all about Taps & Responses to them
*/
class Tap extends BaseModel {

    public static $fields = array('id', 'sender_id', 'text', 'time', 
        'group_id', 'reciever_id', 'media_id', 'modification_time', 
        'private', 'anonymous');

    public static $replyFields = array('id', 'message_id', 'user_id', 'text', 'time');

    protected static $intFields = array('id', 'sender_id', 'time', 'group_id', 'reciever_id', 'media_id',
        'modification_time', 'private', 'anonymous');

    protected static $addit = array('responses', 'group', 'sender', 'reciever', 
        'media', 'replies', 'involved', 'is_new','new_replies', 'type');

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
    private static function add(User $from, $text, $media = null, Group $g = null, User $to = null, $private = 0,
        $anonymous = 0) {
        $db = DB::getInstance();

        if ($media) {
            // Remove the URL of the media published
            $text = str_replace($media['link'], '', $text);
            $media_id = Media::create($media['mediatype'], 
                                      $media['link'], 
                                      $media['code'], 
                                      stripslashes($media['title']),
                                      stripslashes($media['description']),
                                      $media['thumbnail_url'],
                                      $media['fullimage_url']);
        }
        
        $id = $db->insert('message', 
            array('sender_id' => $from->id, 
                  'text' => $text,
                  'media_id' => $media_id ? $media_id : null,
                  'group_id' => $g ? $g->id : null,
                  'reciever_id' => $to ? $to->id : null,
                  'modification_time' => 'CURRENT_TIMESTAMP',
                  'private' => $private,
                  'anonymous' => $anonymous));


        return $id;
    }

    /*
        @return Tap
    */
    public static function toGroup(Group $group, User $user, $text, $media, $private = 0, $anon = 0) {
        $tap = Tap::byId(Tap::add($user, $text, $media, $group, null, $private, $anon),
                         true, false, true, (!empty($media) ? true : false));

        Comet::send('tap.new', $tap->format()->asArray());

        return $tap;
    }

    /*
        @return Tap
    */
    public static function toUser(User $from, User $to, $text, $media, $anon = 0) {
        $tap = Tap::byId(Tap::add($from, $text, $media, null, $to, 0, $anon), 
                        true, true, true, (!empty($media) ? true : false));

        Comet::send('tap.new', $tap->format()->asArray());

        return $tap;
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
            if ($this->media_id)
                $this->db->query("DELETE FROM media WHERE id = ".$this->media_id);

            $this->db->commit();
            Comet::send('tap.delete', array('id' => $this->id));
        } catch (SQLException $e) {
            $this->db->rollback();
            if (DEBUG)
                FirePHP::getInstance(true)->error($e);
            return false;
        }

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

        $this->data['id'] = $this->id;
        $this->data['replies'][] = array(
            'id'         => $id,
            'message_id' => $this->id,
            'user_id'    => $user->id,
            'text'       => $text,
            'time'       => time(),
            'user'       => $user);

        $this->format();
        end($this->data['replies']);

        Comet::send('response.new', current($this->data['replies']));
        
        $mentioned = self::parseMentions($text);
        foreach($mentioned as $m)
            Comet::send('mention.new', array('uid' => $m->id, 'mid' => $this->id, 'sid' => $user->id));

        return $this;
    }

    /*
        Parses all twitter-style @name mentions and returns a user list

        @return UsersList
    */
    private static function parseMentions($text) {
        $unames = array();
        preg_match_all('/@([a-z0-9-_.]+?)(?:\s|$)/im', $text, $unames, PREG_PATTERN_ORDER);
        if (DEBUG)
            FirePHP::getInstance(true)->log($unames, 'Mentioned unames');

        if (!empty($unames[1]))
            return UsersList::search('byUnames', array('unames' => $unames[1]), U_ID_ONLY);
        else
            return UsersList::makeEmpty();
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
        $result = $this->db->query($query, array(
            'uid' => $user->id, 'mid' => $this->id, 'status' => $status))
                           ->affected_rows == 1;
        Comet::send('convo.follow', array('message_id' => $this->id, 'user_id' => $user->id, 'status' => $status));
        return $result;
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

    public function detectLanguage($user_ip = '') {
        if (!$this->id || !$this->data['text'])
            return;

        $curl = new Curl();
        $result = $curl->get('https://ajax.googleapis.com/ajax/services/language/detect', 
            array('v' => '1.0', 'q' => $this->data['text'], 'key' => TRANSLATE_KEY, 'userip' => $user_ip));

        $result = json_decode($result, true);

        if (DEBUG)
            FirePHP::getInstance(true)->log($result, 'language');

        if ($result['responseStatus'] == 200) {
            $geo = static::langByIp($user_ip);

            if ($result['responseData']['isReliable'] || $result['responseData']['confidence'] >= 0.3)
                $lang = $result['responseData']['language'];
            else if ($geo)
                $lang = $geo;
            else
                $lang = 'en';

            $query = 'UPDATE message SET language = #lang# WHERE id = #id#';
            $this->db->query($query, array('lang' => $lang, 'id' => $this->id));
        }

        return $this;
    } 

    public static function langByIp($ip) {
        if (!$ip)
            return 'en';

        require_once(BASE_PATH."modules/geoip/geoipcity.inc");
        require_once(BASE_PATH."modules/geoip/geoipregionvars.php");

        $base = geoip_open(BASE_PATH."static/GeoIP.dat", GEOIP_STANDARD);
        $record = geoip_record_by_addr($base, $ip);
        geoip_close($base);

        if (DEBUG) 
            FirePHP::getInstance(true)->log($record, 'geoip');

        return $record->country_code;
    }
};
