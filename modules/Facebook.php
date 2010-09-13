<?php
abstract class FacebookException extends Exception {};
class FacebookDataException extends FacebookException {};

/*
    All facebook operations, login, create user,
    check user for already exists, etc
*/
class Facebook extends BaseModel {
    private $parsed_cookie = null;
    private $fetched_info = array();

    private $fuid = null;
    private $access_token = null;

    public function __construct() {
        parent::__construct();

        if ($info = $this->infoFromCookies()) {
            $this->fuid = intval($info['uid']);
            $this->access_token = $info['access_token'];
        }
    }
    
    /*
        Basic interface
    */
    public function __get($key) {
        $mname = 'getUser'.ucfirst($key);
        if (method_exists($this, $mname)) {
            return $this->$mname();
        } else if ($key == 'fuid') {
            return $this->fuid;
        }

        throw new FacebookDataException("Can not find any facebook info related to '$key'");
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
    public function exists() {
        if (!$this->fuid)
            return false;

        $query = "
            SELECT uid
              FROM login
             WHERE fb_uid = #fb_uid#
             LIMIT 1";
        
        $result = $this->db->query($query, array('fb_uid' => $this->fuid));
        $exists = $result->num_rows == 1;

        return $exists;
    }

    /*
        Checks if account is already binded
    */
    public function isUserBinded(User $user) {
        $query = "
            SELECT fb_uid
              FROM login
             WHERE uid = #uid#
             LIMIT 1";
        
        $binded = false;
        $result = $this->db->query($query, array('uid' => $user->uid));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $binded = $result['fb_uid'] != 0;
        }

        return $binded;
    }

    /*
        Returns and cache some information fetched from facebook
    */
    private function getSomething($type, $id) {
         if (isset($this->fetched_info[$id][$type]))
            return $this->fetched_info[$id][$type];

        //Primary info, works without additional specificator
        $primary = array('user', 'group');

        if (in_array($type, $primary)) {
            $add = '';
        } else {
            $add = "/$type";
            $selector = 'data';
        }

        $url = 'https://graph.facebook.com/'.$id.$add.'?access_token=' . urlencode($this->access_token);
        $info = json_decode(file_get_contents($url), true);
        if (isset($selector))
            $info = $info[$selector];

        $this->fetched_info[$uid][$type] = $info;
        return $info;
   }
    
    /*
        Returns all user information provided to us
        by facenook oauth
        e.g. id, name, first_name, last_name, link, gender, locale
    */
    public function getUserInfo() {
        return $this->getSomething('user', $this->fuid);
    }

    /*
        Returns a list of user friend,
        each friend is assoc array (name, id)
    */
    public function getUserFriends() {
        return $this->getSomething('friends', $this->fuid);
    }

    /*
        Returns a assoc array of user likes
        (name, category, id, creation_time)
    */
    public function getUserLikes() {
        return $this->getSomething('likes', $this->fuid);
    }

    /*
        Returns a list of user books
        (name, category, id, creation_time)
    */
    public function getUserBooks() {
        return $this->getSomething('books', $this->fuid);
    }

    /*
        Returns a list of user movies
        (name, category, id, creation_time)
    */
    public function getUserMovies() {
        return $this->getSomething('movies', $this->fuid);
    }

    /*
        User groups
    */
    public function getUserGroups() {
        return $this->getSomething('groups', $this->fuid);
    }

    /*
        Get group info
    */
    public function getGroupInfo($gid) {
        return $this->getSomething('group', $gid);
    }

    /*
        Fills user profile with info, we can fetch from
        facebook profile, e.g. gender, profile pic
    */
    public function bindToFacebook(User $user) {
        $info = $this->getUserInfo();
        if (!$info)
            return false;

        //download & resize userpics
        $pics_dir = PROFILE_PIC_PATH;
        $big_name = $pics_dir.$this->fuid.'.jpg';
        $big_url = 'http://graph.facebook.com/'.$this->fuid.'/picture?type=large';
        file_put_contents($big_name, file_get_contents($big_url));

        list($big_name, $i180, $i100, $i36) = Images::makeUserpics($this->fuid, $big_name, $pics_dir);

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
            'fb_uid' => $this->fuid, 'big_name' => $big_name, 'i180' => $i180, 'i100' => $i100, 'i36' => $i36,
            'uid' => $user->uid));
        $ok = $this->db->affected_rows == 1;

        if (!$ok)
            return false;
    
        //mass-follow fb-friends
        $this->followFriends($user);

        return true;
    }

    /*
        Mass-follows your facebook friends
    */
    public function followFriends(User $user) {
        if (!$this->fuid)
            return false;
        
        $friends = $this->getUserFriends();
        if (empty($friends))
            return true;
        
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

        $uid = $user->uid;
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
        if (!$this->fuid)
            return false;
        
        $url = "https://graph.facebook.com/{$this->fuid}/feed";
        $post = array_merge($status, array('access_token' => $this->access_token));

        $curl = new Curl();
        $curl->open($url, $post);

        return true;
    }
};
