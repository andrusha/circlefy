<?php

/*
    All facebook operations, login, create user,
    check user for already exists, etc
*/
class Facebook extends BaseModel {
    private $parsed_cookie = null;
    private $fetched_info = array();

    public function __construct() {
        parent::__construct();
    }

    /*
        Returns all information, provided to us by facebook _cookies_
        e.g. access_token, base_domain, expires, secret,
             session_key, sig, uid
    */
    public function infoFromCookies() {
        if ($this->parsed_cookie)
            return $this->parsed_cookie;

        $args = array();
        parse_str(trim($_COOKIE['fbs_' . FBAPPID], '\\"'), $args);
        ksort($args);
        $payload = '';
        foreach ($args as $key => $value) 
            if ($key != 'sig')
                 $payload .= $key . '=' . $value;

        if (md5($payload . FBAPPSECRET) != $args['sig'])
            return null;

        $this->parsed_cookie = $args;

        return $args;
    }

    /*
        Check if user with provided facebook uid exists
    */
    public function userExists($fb_uid) {
        $query = "
            SELECT uid
              FROM login
             WHERE fb_uid = #fb_uid#
             LIMIT 1";
        
        $result = $this->db->query($query, array('fb_uid' => $fb_uid));
        $exists = $result->num_rows == 1;

        return $exists;
    }

    /*
        Checks if current FB account is binded
    */
    public function binded() {
        $info = $this->getInfoStraight();
        $fbid = intval($info->id);
    
        return $this->userExists($fbid);
    }

    /*
        Checks if account is already binded
    */
    public function bindedByUID($uid) {
        $query = "
            SELECT fb_uid
              FROM login
             WHERE uid = #uid#
             LIMIT 1";
        
        $binded = false;
        $result = $this->db->query($query, array('uid' => $uid));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $binded = $result['fb_uid'] != 0;
        }

        return $binded;
    }
    
    /*
        Returns all user information provided to us
        by facenook oauth
        e.g. id, name, first_name, last_name, link, gender, locale
    */
    public function getUserInfo($uid, $access_token) {
        if (isset($this->fetched_info[$uid]))
            return $this->fetched_info[$uid];

        $url = 'https://graph.facebook.com/'.$uid.'?wrap_access_token=' . urlencode($access_token);
        $info = json_decode(file_get_contents($url), true);

        $this->fetched_info[$uid] = $info;
        return $info;
    }

    /*
        Returns a list of user friend,
        each friend is assoc array (name, id)
    */
    public function getUserFriends($uid, $access_token) {
        $url = 'https://graph.facebook.com/'.$uid.'/friends?access_token='.urlencode($access_token);
        $info = json_decode(file_get_contents($url), true);
        return $info['data'];
    }

    /*
        Returns info about user signed by cookies
    */
    public function getInfoStraight() {
        $cookie = $this->infoFromCookies();
        if (!$cookie)
            return false;

        return array_merge($cookie, $this->getUserInfo(intval($cookie['uid']), $cookie['access_token']));
    }

    /*
        Fills user profile with info, we can fetch from
        facebook profile, e.g. gender, profile pic
    */
    public function bindToFacebook($uid) {
        $info = $this->getInfoStraight();
        if (!$info)
            return false;

        $fbid = intval($info['id']);

        //download & resize userpics
        $pics_dir = PROFILE_PIC_PATH;
        $big_name = $pics_dir.$fbid.'.jpg';
        $big_url = 'http://graph.facebook.com/'.$fbid.'/picture?type=large';
        file_put_contents($big_name, file_get_contents($big_url));

        list($big_name, $i180, $i100, $i36) = Images::makeUserpics($fbid, $big_name, $pics_dir);

        //update user info
        $query = "
            UPDATE login
               SET fname = #fname#,
                   lname = #lname#,
                   fb_uid = #fb_uid#,
                   pic_full = #big_name#,
                   pic_180 = #i180#,
                   pic_100 = #i100#,
                   pic_36 = #i36#
             WHERE uid = #uid#
             LIMIT 1";
        $this->db->query($query, array('fname' => $info['first_name'], 'lname' => $info['last_name'],
            'fb_uid' => $fbid, 'big_name' => $big_name, 'i180' => $i180, 'i100' => $i100, 'i36' => $i36,
            'uid' => $uid));
        $ok = $this->db->affected_rows == 1;

        if (!$ok)
            return false;
    
        //mass-follow fb-friends
        $this->followFriends($uid);

        return true;
    }

    /*
        Mass-follows your facebook friends
    */
    public function followFriends($uid) {
        $info = $this->getInfoStraight();
        if (!$info)
            return false;
        
        $fbid = intval($info['id']);

        $friends = $this->getUserFriends($fbid, $info['access_token']);
        
        $fb_fids = array();
        foreach($friends as $x)
            $fb_fids[] = intval($x['id']);

        $query = "
            SELECT uid
              FROM login l
             WHERE fb_uid IN (#fb_fids#)";
        $result = $this->db->query($query, array('fb_fids' => $fb_fids));
        if ($result->num_rows == 0)
            return false;
        
        $fids = array();
        while ($res = $result->fetch_assoc())
            $fids[] = intval($res['uid']);

        $friends = new Friends();
        $ok = $friends->follow($uid, $fids);

        return $ok;
    }

    /*
        Post FB-status

        Status contains following fields:
        message, picture, link, name, caption, description, source
    */
    public function postStatus(array $status) {
        $info = $this->getInfoStraight();
        if (!$info)
            return false;
        
        $fbid = intval($info['id']);
        $access_token = $info['access_token'];

        $url = "https://graph.facebook.com/$fbid/feed";
        $post = array_merge($status, array('access_token' => $access_token));

        $curl = new Curl();
        var_dump($curl->open($url, $post));

        return true;
    }
};
