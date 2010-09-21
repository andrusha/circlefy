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
        if ($_SESSION['user'])
            return $_SESSION['user'];

        //if user have cookies from previous session, log him in
        $user = Auth::bypass();

        if ($user === null)
            $user = Auth::createGuest();

        $_SESSION['user'] = $user;
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

            $user = new User($uid);
            $user->getInfo();

            if ($set_cookies) {
                Auth::setCookies($user->uid, $hash, $user->guest);
                $user->hash = $hash;
            }

            $_SESSION['user'] = $user;
            return $user; 
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
        Creates new guest-user and returns info about it

        @return User
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
                    INTO login (hash, uname, fname, last_login, ip, anon, email)
                  VALUES (#hash#, #uname#, 'guest', CURRENT_TIMESTAMP(), INET_ATON(#ip#), 1, #hash#)";
        $result = $db->query($query, array('hash' => $hash, 'uname' => $uname, 'ip' => $ip));
        
        if ($db->affected_rows != 1)
            return null;

        $uid = $db->insert_id;
        
        //Setting up default account
        $db->query("INSERT INTO profile (uid, language) VALUES (#uid#, 'English')", array('uid' => $uid));
        $db->query("INSERT INTO settings (uid) VALUES (#uid#)", array('uid' => $uid));
        $db->query("INSERT INTO notifications (uid) VALUES (#uid#)", array('uid' => $uid));
        $db->query("INSERT INTO TEMP_ONLINE (uid) VALUES (#uid#)", array('uid' => $uid));

        $user = new User($uid);
        $user->getInfo();
        Auth::setCookies($uid, $hash, true);

        return $user; 
    }

    /*
        Logged in user by his cookie hash

        @return User 
    */
    private static function bypass() {
        if (!$_COOKIE['auto_login'] || 
            !isset($_COOKIE['uid']) ||
            !isset($_COOKIE['rand_hash']))
            return null;

        $uid = intval($_COOKIE['uid']);
        $hash = $_COOKIE['rand_hash'];
        $user = new User($uid);
        $user->getInfo();

        if ($user->hash == $hash) {
            $new_hash = Auth::makeHash();
            Auth::setCookies($user->uid, $hash, $user->guest);

            return $user;
        }

        return null;
    }

    /*
        Sets all cookies & session variables needs to user after login
    */
    public static function setCookies($uid, $hash, $guest, $auto_login = true, $clear = true) {
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

        $_SESSION['uid'] = $uid;
        $_SESSION['guest'] = $guest;
        setcookie("uid", $uid, time()+36000000);
        setcookie("rand_hash", $hash, time()+36000000);
        setcookie("auto_login", $auto_login, time()+36000000);
    }

    /*
        Delete all user-related cookies
    */
    public static function clearCookies() {
        foreach (array('uid', 'guest') as $i)
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
