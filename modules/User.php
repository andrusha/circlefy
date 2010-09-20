<?php
/*
    All user operations, e.g login in, information
*/
class User extends BaseModel {
    private $uid = null;
    private $guest = false;

    private $loggedIn = false;
    private $session_started = false;

    /*
        @param array|id $info full info for cache or just uid
    */
    public function __construct($info) {
        parent::__construct(
            array('uid', 'uname', 'ip', 'addr', 'guest', 'info', 'fullInfo', 'stats'),
            array('info', 'fullInfo', 'stats'));

        if (!$this->session_started) {
            session_start();
            $this->serssion_started = true;
        }

        if ($info === null)
            throw new UserInfoException('You should specify id before using this class');
        else if (is_array($info)) {
            $this->uid = $info['uid'];
            $this->data = $info;
        } else
            $this->uid = intval($info);
    }

    public function setGuest($guest) {
        $this->guest = $guest;
    }

    public function setStats($stats) {
        $this->data['stats'] = $stats;
        //hacky, but useful
        $this->data['info']['stats'] = $stats;
    }

    public function setInfo($key, $val) {
        $this->data['info'][$key] = $val;
    }

    /*
        You can get:
        uid | uname | ip - addr | guest
        info | fullInfo | stats

        All info with get methods caching
    */
    public function __get($key) {
        $current = $this->uid == $_SESSION['uid'];

        switch ($key) {
            case 'uid':
                return $this->uid;

            case 'uname':
                if ($current)
                    return $_SESSION['uname'];
                else
                    return $this->info['uname'];

            case 'ip':
            case 'addr':
                if ($current)
                    return $_SERVER['REMOTE_ADDR'];
                else
                    return $this->info['ip'];

            case 'guest':
                return $this->guest;

            default:
                $name = 'get'.ucfirst($key);
                if (method_exists($this, $name)) {
                    if (!isset($this->data[$key]))
                        $this->data[$key] = $this->$name();

                    return $this->data[$key];
                }
        }

        throw new UserInfoException("Unknown data named '$key'");
    }

    /*
        Returns user id by user name

        @param $uname string
        @returns User
    */
    public static function fromUname($uname) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $query = "SELECT uid
                    FROM login
                   WHERE uname = #uname#
                   LIMIT 1";
        $user = null;
        $result = $db->query($query, array('uname' => $uname));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $user = new User(intval($result['uid']));
        }
        return $user;
    }

    /*
        Logs out user
    */
    public function logOut() {
        $query = "UPDATE login
                     SET hash = ''
                   WHERE uid = #uid#";
        $this->db->query($query, array('uid' => $this-> uid));
        Auth::clearCookies();
        session_destroy();
    }

    /*
        This thing makes current guest a full-featured user
    */
    public function userificateGuest($uid, $uname, $fname, $lname, $email, $pass) {
        $query = "UPDATE login
                     SET uname = #uname#,
                         fname = #fname#,
                         lname = #lname#,
                         pass = MD5(#pass#),
                         email = #email#,
                         anon = 0
                   WHERE uid = #uid#
                   LIMIT 1";
        $this->db->query($query, array('uname' => $uname, 'fname' => $fname,
            'lname' => $lname, 'pass' => $pass, 'email' => $email, 'uid' => $uid));
        
        if ($this->db->affected_rows == 1) {
            $hash = Auth::makeHash();
            Auth::setCookies($uid, $uname, $fname, $hash, true);

            return true;
        }

        return false;
    }

    /*
        Returns info about user
        userpics, username, help, real name, email, private settings
    */
    public function getInfo() {
        $query = "SELECT uname, GET_REAL_NAME(fname, lname, uname) AS real_name,
                         pic_100 AS big_pic, pic_36 AS small_pic, help,
                         email, private, ip, uid
                    FROM login
                   WHERE uid = #uid#
                   LIMIT 1";
        $info = array();
        $result = $this->db->query($query, array('uid' => $this->uid));
        if ($result->num_rows)
            $info = $result->fetch_assoc();
        return $info;
    }

    /*
        User information from profile table too, like about, etc
    */
    public function getFullInfo($online = false) {
        $fields = $joins = '';
        if ($online) {
            $fields = ', tpo.online';
            $joins = 'LEFT JOIN TEMP_ONLINE tpo ON tpo.uid = l.uid';
        }

        $query = "SELECT l.uid, l.uname, GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name,
                         l.pic_100 AS big_pic, l.pic_36 AS small_pic, l.help, l.private,
                         p.about, p.country, p.zip{$fields}
                    FROM login l
                   INNER
                    JOIN profile p
                      ON p.uid = l.uid
                   {$joins}
                   WHERE l.uid = #uid#
                   LIMIT 1";
        $info = array();
        $result = $this->db->query($query, array('uid' => $this->uid));
        if ($result->num_rows)
            $info = $result->fetch_assoc();
        return $info;
    }

    /*
        Returns user statistics
        taps, responses & following channels count

        Taps & responses counts only for taps
        in public groups (connected 1, 2)

        $uid - user id
        
        array('taps' => , 'responses' => , 'groups' => )
    */
    public function getStats() {
        $query = "
         SELECT COUNT(i.mid) AS taps,
                SUM(i.count) AS responses,
                (
                    SELECT COUNT(g.uid) AS groups
                      FROM group_members AS g
                     WHERE g.uid = #uid#
                     GROUP
                        BY g.uid
                ) AS groups
           FROM (
                    SELECT s.mid AS mid,
                           COUNT(c.mid) AS count
                      FROM special_chat AS s
                     INNER
                      JOIN special_chat_meta scm
                        ON scm.mid = s.mid
                      LEFT
                      JOIN chat c
                        ON c.cid = s.mid
                     WHERE s.uid = #uid#
                       AND scm.connected IN (1, 2)
                     GROUP
                        BY (s.mid)
                ) AS i";

        $stats = array();
        $result = $this->db->query($query, array('uid' => $this->uid));
        if ($result->num_rows)
            $stats = $result->fetch_assoc();
        return $stats;
    }

    /*
        Makes current user online
    */
    public function makeOnline() {
        $query = "
            INSERT
              INTO TEMP_ONLINE (uid, online)
            VALUES (#uid#, 1)
                ON DUPLICATE KEY
            UPDATE online = 1";
        $this->db->query($query, array('uid' => $this->uid));

        return $this->db->affected_rows == 1;
    }

    /*
        Returns number of friends

        $youFollowing = true - you following
        $youFollowing = false - follows you

        $uid - your uid
    */
    private function friendsCount($youFollowing = true) {
        $identifier = $youFollowing ? 'uid' : 'fuid';
        $query = "
            SELECT COUNT(*) AS count
              FROM friends AS f
             WHERE f.{$identifier} = #uid#";
        $result = $this->db->query($query, array('uid' => $this->uid))->fetch_assoc();
        return intval($result['count']);
    }

    public function followingCount() {
        return $this->friendsCount(true);
    }

    public function followersCount() {
        return $this->friendsCount(false);
    }

    /*
        Follows user or list of users

        @param User|UsersList $friend 
    */    
    public function follow($friends) {
        if ($friends instanceof UsersList) {
            $values = '';
            foreach($friends as $friend)
                $values .= ",({$this->uid}, {$friend->uid}, NOW())";
            $values = substr($values, 1);
        } else if ($friends instanceof User) {
            $values = "({$this->uid}, {$friends->uid}, NOW())"; 
        }

        $query = "INSERT
                    INTO friends (uid, fuid, time_stamp)
                  VALUES {$values}";

        $okay = $this->db->query($query)->affected_rows >= 1;
        return $okay;
    }

    /*
        Unfollows user
    */
    public function unfollow(User $friend) {
        $query = "DELETE
                    FROM friends
                   WHERE fuid = #friend#
                     AND uid = #you#
                   LIMIT 1";
        $okay = $this->db->query($query, array('you' => $this->uid, 'friend' => $friend->uid))->affected_rows == 1;
        return $okay;
    }
    
    /*
        Check if user following you or not
    */
    public function following(User $friend) {
        $query = "SELECT fuid
                    FROM friends
                   WHERE fuid = #friend#
                     AND uid = #you#
                   LIMIT 1";
        $okay = $this->db->query($query, array('you' => $this->uid, 'friend' => $friend->uid))->num_rows == 1;
        return $okay;
    }
};
