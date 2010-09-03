<?php


/*
All user operations, e.g login in, information
*/
class User extends BaseModel {
    private $loggedIn = false;
    private $session_started = false;

    public function __construct() {
        parent::__construct();
        if (!$this->session_started) {
            session_start();
            $this->serssion_started = true;
        }
    }

    /*
        Perform identification routine (not user/pass authorization)
        loggin in user again if guest/user
    */
    public function identify() {
        header('Cache-Control: no-store, no-cache, must-revalidate');
    
        //if user already logged in - do nothing
        if ($_SESSION['guest'] == 0 && isset($_SESSION['uid'])) {
            $this->loggedIn = true;
            return true;
        }

        //if user have cookies from previous session, log him in
        if ($this->bypassLogin()) {
            return true;
        }

        //if nothing helps, make him guest
        $this->makeGuest();
        return false;
    }

    /*
        Check if username & password is right
        and then logging user in
    */
    public function logIn($username, $password, $set_cookies = true, $auto_login = false, $with_facebook = false) {
        if (!$with_facebook) {
           $where = "uname = #uname# AND pass = MD5(#pass#)";
           $params = array('uname' => $username, 'pass' => $password);
        } else {
            $fb = new Facebook();
            $cookie = $fb->infoFromCookies();
            if (!$cookie)
                return false;

            $fbid = intval($cookie['uid']);
            $where = "fb_uid = #fbid#";
            $params = array('fbid' => $fbid);
        }

        $query = "SELECT uid, uname, fname
                    FROM login
                   WHERE {$where}
                     AND ban = 0
                   LIMIT 1";
        $result = $this->db->query($query, $params);

        if ($result->num_rows) {
            $this->loggedIn = true;

            $result = $result->fetch_assoc();
            $hash = $this->makeHash();
            if ($set_cookies)
                $this->setUserCookies(intval($result['uid']), $result['uname'], $result['fname'], $hash, $auto_login);

            return true;
        }
        return false;
    }

    public function logInWithFacebook() {
        return $this->logIn('', '', true, true, true);
    }

    /*
        Logged in user by his cookie hash
    */
    public function bypassLogin() {
        if (!$_COOKIE['auto_login'] || 
            !isset($_COOKIE['uid']) ||
            !isset($_COOKIE['rand_hash']) ||
            isset($_COOKIE['GUEST_uid']))
            return false;

        $uid = intval($_COOKIE['uid']);
        $hash = $this->db->real_escape_string($_COOKIE['rand_hash']);
        $query = "SELECT uid, uname, fname
                    FROM login
                   WHERE uid = #uid#
                     AND hash = #hash#
                   LIMIT 1";
        $result = $this->db->query($query, array('uid' => $uid, 'hash' => $hash));
        if ($result->num_rows) {
            $this->loggedIn = true;

            $result = $result->fetch_assoc();
            $hash = $this->makeHash();
            $this->setUserCookies(intval($result['uid']), $result['uname'], $result['fname'], $hash, true);

            return true;
        }
        return false;
    }

    /*
        Logs out user
    */
    public function logOut($uid) {
        $query = "UPDATE login
                     SET hash = ''
                   WHERE uid = #uid#";
        $this->db->query($query, array('uid' => $uid));
        $this->clearCookies();
        session_destroy();
    }

    /*
        Makes current user guest
    */
    public function makeGuest() {
        
        if ($_COOKIE['GUEST_uid'] && $_COOKIE['GUEST_hash']) {
            $uid = $_COOKIE['GUEST_uid'];
            $uname = $_COOKIE['GUEST_uname'];
            $hash = $_COOKIE['GUEST_hash'];
        } else {
            list($uid, $uname, $hash) = $this->createGuest();
            
            setcookie('GUEST_uid', $uid, time()+36000);					
            setcookie('GUEST_uname', $uname, time()+36000);
            setcookie('GUEST_hash', $hash, time()+36000);
        }
        $this->setUserCookies($uid, $uname, 'guest', $hash, false, false);
        $_SESSION['guest'] = 1;
    }

    /*
        Yeah, it returns max uid from login table
    */
    public function getMaxId() {
        $query = "SELECT MAX(uid)+1 AS max
                    FROM login";
        $result = $this->db->query($query)->fetch_assoc();
        return intval($result['max']);
    }

    /*
        Creates new guest-user and returns info about it
    */
    private function createGuest() {
        $hash = $this->makeHash();
        $uname = 'Guest'.$this->getMaxId();
        $ip = $_SERVER['REMOTE_ADDR'];

        $query = "INSERT
                    INTO login (`hash`,`uname`,`last_login`, `ip`, `anon`, `email`)
                  VALUES (#hash#, #uname#, CURRENT_TIMESTAMP(), INET_ATON(#ip#), 1, #hash#)";
        $result = $this->db->query($query, array('hash' => $hash, 'uname' => $uname, 'ip' => $ip));
        
        if ($this->db->affected_rows != 1)
            return null;

        $uid = $this->db->insert_id;
        
        //Setting up default account
        $this->db->query("INSERT INTO profile (uid, language) VALUES (#uid#, 'English')", array('uid' => $uid));
        $this->db->query("INSERT INTO settings (uid) VALUES (#uid#)", array('uid' => $uid));
        $this->db->query("INSERT INTO notifications (uid) VALUES (#uid#)", array('uid' => $uid));
        $this->db->query("INSERT INTO TEMP_ONLINE (uid) VALUES (#uid#)", array('uid' => $uid));

        return array($uid, $uname, $hash);
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
            $hash = $this->makeHash();
            $this->setUserCookies($uid, $uname, $fname, $hash, true);

            return true;
        }

        return false;
    }

    /*
        Returns purely random hash
    */
    private function makeHash() {
        $abc = range('a', 'z');
        $rand_str = '';
        for ($i = 0; $i < 16; $i++)
            $rand_str .= array_rand($abc);

        return md5($rand_str);
    }

    /*
        Sets all cookies & session variables needs to user after login
    */
    private function setUserCookies($uid, $uname, $real_name, $hash, $auto_login, $clear = true) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $query = "UPDATE login
                     SET last_login = CURRENT_TIMESTAMP(),
                         ip = INET_ATON(#ip#),
                         hash = #hash#
                   WHERE uid = #uid#
                   LIMIT 1";
        $this->db->query($query, array('ip' => $ip, 'hash' => $hash, 'uid' => $uid));
        
        if ($clear)
            $this->clearCookies();

        $fb = new Facebook();
        $binded = $fb->bindedByUID($uid);

        $_SESSION['facebook'] = $binded;
        $_SESSION['uid'] = $uid;
        $_SESSION['uname'] = $uname;
        $_SESSION['real_name'] = $real_name;
        setcookie("uid", $uid, time()+36000000);
        setcookie("rand_hash", $hash, time()+36000000);
        setcookie("uname", $uname, time()+36000000);
        setcookie("auto_login", $auto_login, time()+36000000);
    }

    /*
        Delete all user-related cookies
    */
    public function clearCookies() {
        foreach (array('uid', 'uname', 'real_name', 'guest') as $i)
            $_SESSION[$i] = NULL;

        foreach (array('uid', 'rand_hash', 'uname', 'auto_login',
                       'GUEST_hash', 'GUEST_uid', 'GUEST_uname') as $i)
            setcookie($i, NULL, time() - 3600);
    }

    /*
        Returns user id by user name
    */
    public function uidFromUname($uname) {
        $query = "SELECT uid
                    FROM login
                   WHERE uname = #uname#
                   LIMIT 1";
        $uid = null;
        $result = $this->db->query($query, array('uname' => $uname));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $uid = intval($result['uid']);
        }
        return $uid;
    }

    /*
        Returns info about user
        userpics, username, help, real name, email, private settings
    */
    public function getInfo($uid) {
        $query = "SELECT uname, GET_REAL_NAME(fname, lname, uname) AS real_name,
                         pic_100 AS big_pic, pic_36 AS small_pic, help,
                         email, private
                    FROM login
                   WHERE uid = #uid#
                   LIMIT 1";
        $info = array();
        $result = $this->db->query($query, array('uid' => $uid));
        if ($result->num_rows)
            $info = $result->fetch_assoc();
        return $info;
    }

    /*
        User information from profile table too, like about, etc
    */
    public function getFullInfo($uid) {
        $query = "SELECT l.uname, GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name,
                         l.pic_100 AS big_pic, l.pic_36 AS small_pic, l.help,
                         p.about
                    FROM login l
                   INNER
                    JOIN profile p
                      ON p.uid = l.uid
                   WHERE l.uid = #uid#
                   LIMIT 1";
        $info = array();
        $result = $this->db->query($query, array('uid' => $uid));
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
    public function getStats($uid) {
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
        $result = $this->db->query($query, array('uid' => $uid));
        if ($result->num_rows)
            $stats = $result->fetch_assoc();
        return $stats;
    }
};
