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
            return unserialize($_SESSION['user']);

        return Auth::createGuest();
    }

    public static function logOut() {
        session_destroy();
    }

    /*
        Check if username & password is right
        and then logging user in

        @return User
    */
    public static function logIn($username, $password, $with_facebook = false) {
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

            $user = new User(intval($result['uid']));
            $user->getInfo();

            Auth::setSession($user);

            return $user; 
        }

        return null;
    }

    /*
        @return User
    */
    public static function logInWithFacebook() {
        return Auth::logIn('', '', true);
    }

    /*
        Creates new guest-user and returns info about it

        @return User
    */
    private static function createGuest() {
        $db = DB::getInstance()->Start_Connection('mysql');

        $ip = $_SERVER['REMOTE_ADDR'];

        $query = "SELECT MAX(uid)+1 AS max
                    FROM login";
        $result = $db->query($query)->fetch_assoc();
        $uname = 'Guest'.$result['max'];

        $query = "INSERT
                    INTO login (uname, fname, last_login, ip, anon, email)
                  VALUES (#uname#, 'guest', CURRENT_TIMESTAMP(), INET_ATON(#ip#), 1, NULL)";
        $result = $db->query($query, array('uname' => $uname, 'ip' => $ip));
        
        if ($db->affected_rows != 1)
            throw new AuthException('Something went wrong in guest user creation');

        $uid = $db->insert_id;
        
        //Setting up default account
        $db->query("INSERT INTO profile (uid, language) VALUES (#uid#, 'English')", array('uid' => $uid));
        $db->query("INSERT INTO settings (uid) VALUES (#uid#)", array('uid' => $uid));
        $db->query("INSERT INTO notifications (uid) VALUES (#uid#)", array('uid' => $uid));
        $db->query("INSERT INTO TEMP_ONLINE (uid) VALUES (#uid#)", array('uid' => $uid));

        $user = new User($uid);
        $user->getInfo();
        Auth::setSession($user);

        return $user; 
    }

    /*
        Sets all cookies & session variables needs to user after login
    */
    public static function setSession(User $user) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $ip = $_SERVER['REMOTE_ADDR'];
        $query = "UPDATE login
                     SET last_login = CURRENT_TIMESTAMP(),
                         ip = INET_ATON(#ip#)
                   WHERE uid = #uid#
                   LIMIT 1";
        $db->query($query, array('ip' => $ip, 'uid' => $user->uid));
        
        $_SESSION['uid'] = $user->uid;
        $_SESSION['user'] = serialize($user);
    }
};
