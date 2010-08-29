<?php

/*
    All facebook operations, login, create user,
    check user for already exists, etc
*/
class Facebook {
    private $db;

    public function __construct() {
       $this->db = new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

    /*
        Returns all information, provided to us by facebook _cookies_
        e.g. access_token, base_domain, expires, secret,
             session_key, sig, uid
    */
    public function infoFromCookies() {
        $args = array();
        parse_str(trim($_COOKIE['fbs_' . FBAPPID], '\\"'), $args);
        ksort($args);
        $payload = '';
        foreach ($args as $key => $value) 
            if ($key != 'sig')
                 $payload .= $key . '=' . $value;

        if (md5($payload . FBAPPSECRET) != $args['sig'])
            return null;

        return $args;
    }

    /*
        Check if user with provided facebook uid exists
    */
    public function userExists($fb_uid) {
        $query = "
            SELECT uid
              FROM login
             WHERE fb_uid = {$fb_uid}
             LIMIT 1";
        
        $result = $this->db->query($query);
        $exists = $result->num_rows == 1;

        return $exists;
    }
    
    /*
        Returns all user information provided to us
        by facenook oauth
        e.g. id, name, first_name, last_name, link, gender, locale
    */
    public function getUserInfo($uid, $access_token) {
        $info = json_decode(file_get_contents(
            'https://graph.facebook.com/'.$uid.'?wrap_access_token=' . urlencode($access_token)));
        return $info;
    }

    /*
        Fills user profile with info, we can fetch from
        facebook profile, e.g. gender, profile pic
    */
    public function bindToFacebook($uid) {
        $cookie = $this->infoFromCookies();
        if (!$cookie)
            return false;

        $info = $this->getUserInfo(intval($cookie['uid']), $cookie['access_token']);
        $fbid = intval($info->id);

        $pics_dir = PROFILE_PIC_PATH;
        $big_name = $pics_dir.$fbid.'.jpg';
        $big_url = 'http://graph.facebook.com/'.$fbid.'/picture?type=large';
        file_put_contents($big_name, file_get_contents($big_url));

        list($big_name, $i180, $i100, $i36) = Images::makeUserpics($fbid, $big_name, $pics_dir);

        $query = "
            UPDATE login
               SET fname = '{$info->first_name}',
                   lname = '{$info->last_name}',
                   fb_uid = {$fbid},
                   pic_full = '{$big_name}',
                   pic_180 = '{$i180}',
                   pic_100 = '{$i100}',
                   pic_36 = '{$i36}'
             WHERE uid = {$uid}
             LIMIT 1";
        $this->db->query($query);

        return $this->db->affected_rows == 1;
    }
};
