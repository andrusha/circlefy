<?php

/*
    It surely not for instaniation or inheritance
    some kind of namespace, use only for function calls
*/
abstract class Auth {

    /*
        Perform identification routine (not user/pass authorization)
        loggin in user again if guest/user

        @returns User
    */
    public static function identify() {
        session_start();
        header('Cache-Control: no-store, no-cache, must-revalidate');
    
        //if user already logged in - do nothing
        if ($_SESSION['guest'] == 0 && isset($_SESSION['uid'])) {
            $uid = intval($_SESSION['uid']);
            $user = new User($uid);
            $user->setGuest(false);
            return $user;
        }

        //if user have cookies from previous session, log him in
        if ($uid = Auth::bypass()) {
            $user = new User($uid);
            $guest = $_SESSION['guest'];
            $user->setGuest($guest);
            return $user;
        }

        $user = Auth::makeGuest();
        $user->setGuest(true);
        return $user;
    }

    /*
        Check if username & password is right
        and then logging user in

        @return User
    */
    public static function logIn($username, $password, $set_cookies = true, $auto_login = false, $with_facebook = false) {
        if (!$with_facebook) {
           $where = "uname = #uname# AND pass = MD5(#pass#)";
           $params = array('uname' => $username, 'pass' => $password);
        } else {
            $fb = new Facebook();
            if (!$fb->fuid)
                return null;

            $where = "fb_uid = #fbid#";
            $params = array('fbid' => $fb->fuid);
        }

        $db = DB::getInstance()->Start_Connection('mysql');

        $query = "SELECT uid, uname, fname
                    FROM login
                   WHERE {$where}
                     AND ban = 0
                   LIMIT 1";
        $result = $db->query($query, $params);

        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $hash = Auth::makeHash();
            $uid = intval($result['uid']);
            if ($set_cookies)
                Auth::setCookies($uid, $result['uname'], $result['fname'], $hash, $auto_login);

            return new User($uid);
        }

        return null;
    }

    /*
        @return User
    */
    public static function logInWithFacebook() {
        return Auth::logIn('', '', true, true, true);
    }

    /*
        Makes current user guest

        @return User
    */
    private static function makeGuest() {
        list($uid, $uname, $hash) = Auth::createGuest();
        Auth::setCookies($uid, $uname, 'guest', $hash, false, false);
        $_SESSION['guest'] = 1;

        return new User($uid);
    }

    /*
        Creates new guest-user and returns info about it

        @return array
    */
    private static function createGuest() {
        $db = DB::getInstance()->Start_Connection('mysql');

        $hash = Auth::makeHash();
        $ip = $_SERVER['REMOTE_ADDR'];

        $query = "SELECT MAX(uid)+1 AS max
                    FROM login";
        $result = $db->query($query)->fetch_assoc();
        $uname = 'Guest'.$result['max'];

        $query = "INSERT
                    INTO login (`hash`,`uname`,`last_login`, `ip`, `anon`, `email`)
                  VALUES (#hash#, #uname#, CURRENT_TIMESTAMP(), INET_ATON(#ip#), 1, #hash#)";
        $result = $db->query($query, array('hash' => $hash, 'uname' => $uname, 'ip' => $ip));
        
        if ($db->affected_rows != 1)
            return null;

        $uid = $db->insert_id;
        
        //Setting up default account
        $db->query("INSERT INTO profile (uid, language) VALUES (#uid#, 'English')", array('uid' => $uid));
        $db->query("INSERT INTO settings (uid) VALUES (#uid#)", array('uid' => $uid));
        $db->query("INSERT INTO notifications (uid) VALUES (#uid#)", array('uid' => $uid));
        $db->query("INSERT INTO TEMP_ONLINE (uid) VALUES (#uid#)", array('uid' => $uid));

        return array($uid, $uname, $hash);
    }

    /*
        Logged in user by his cookie hash

        @return int uid
    */
    private static function bypass() {
        if (!$_COOKIE['auto_login'] || 
            !isset($_COOKIE['uid']) ||
            !isset($_COOKIE['rand_hash']))
            return false;

        $db = DB::getInstance()->Start_Connection('mysql');

        $uid = intval($_COOKIE['uid']);
        $hash = $db->real_escape_string($_COOKIE['rand_hash']);
        $query = "SELECT uid, uname, fname
                    FROM login
                   WHERE uid = #uid#
                     AND hash = #hash#
                   LIMIT 1";
        $result = $db->query($query, array('uid' => $uid, 'hash' => $hash));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $hash = Auth::makeHash();
            $uid = intval($result['uid']);
            Auth::setCookies($uid, $result['uname'], $result['fname'], $hash, true);

            return $uid;
        }

        return null;
    }

    /*
        Sets all cookies & session variables needs to user after login
    */
    public static function setCookies($uid, $uname, $real_name, $hash, $auto_login, $clear = true) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $ip = $_SERVER['REMOTE_ADDR'];
        $query = "UPDATE login
                     SET last_login = CURRENT_TIMESTAMP(),
                         ip = INET_ATON(#ip#),
                         hash = #hash#
                   WHERE uid = #uid#
                   LIMIT 1";
        $db->query($query, array('ip' => $ip, 'hash' => $hash, 'uid' => $uid));
        
        if ($clear)
            Auth::clearCookies();

        $binded = Facebook::isBinded($uid);

        $_SESSION['facebook'] = $binded;
        $_SESSION['uid'] = $uid;
        $_SESSION['uname'] = $uname;
        $_SESSION['real_name'] = $real_name;
        setcookie("uid", $uid, time()+36000000);
        setcookie("rand_hash", $hash, time()+36000000);
        setcookie("auto_login", $auto_login, time()+36000000);
    }

    /*
        Delete all user-related cookies
    */
    public static function clearCookies() {
        foreach (array('uid', 'uname', 'real_name', 'guest') as $i)
            $_SESSION[$i] = NULL;

        foreach (array('uid', 'rand_hash', 'auto_login') as $i)
            setcookie($i, '', time() - 360000);
    }

    /*
        Returns purely random hash
    */
    public static function makeHash() {
        $abc = range('a', 'z');
        $rand_str = '';
        for ($i = 0; $i < 16; $i++)
            $rand_str .= array_rand($abc);

        return md5($rand_str);
    }

};
