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
        if ($_SESSION['uid'] !== null)
            return User::init(intval($_SESSION['uid']));

        return new User(null); //Auth::createGuest(); //haha, fuck guests
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

            $where = "fb_id = #fbid#";
            $params = array('fbid' => $fb->fuid);
        }

        $db = DB::getInstance();

        $query = "SELECT id
                    FROM user 
                   WHERE {$where}
                     AND type <> #type#
                   LIMIT 1";
        $params['type'] = User::$types['banned'];
        $result = $db->query($query, $params);

        if ($result->num_rows) {
            $result = $result->fetch_assoc();

            $user = User::init(intval($result['uid']));

            Auth::setSession($user);

            return $user; 
        }

        return new User(null);
    }

    /*
        Sets all cookies & session variables needs to user after login
    */
    public static function setSession(User $user) {
        session_start();
        $db = DB::getInstance();

        $ip = $_SERVER['REMOTE_ADDR'];
        $query = "UPDATE user 
                     SET last_login = CURRENT_TIMESTAMP(),
                         ip = INET_ATON(#ip#)
                   WHERE id = #uid#
                   LIMIT 1";
        $db->query($query, array('ip' => $ip, 'uid' => $user->id));
        
        $_SESSION['uid'] = $user->id;
    }
};
