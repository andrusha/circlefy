<?php
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
        parent::__construct(null);

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

    public function bulkInfo(array $ids) {
        if (empty($ids))
            return array();

        $url = 'https://graph.facebook.com/?ids='.implode(',', $ids).'&access_token='.urlencode($this->access_token);
        return json_decode(file_get_contents($url), true);
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

    public function exists() {
       return User::existsField('fb_id', $this->fuid);
    }

    /*
        Fills user profile with info, we can fetch from
        facebook profile, e.g. gender, profile pic
    */
    public function createWithFacebook($uname, $pass, $email) {
        $info = $this->getUserInfo();
        if (!$info)
            return false;

        //download & resize userpics
        $big_name = USER_PIC_PATH.$this->fuid.'.jpg';
        $big_url = 'http://graph.facebook.com/'.$this->fuid.'/picture?type=large';
        file_put_contents($big_name, file_get_contents($big_url));

        list($big_name, $large, $medium, $small) = Images::makeUserpics($this->fuid, $big_name, USER_PIC_PATH);

        $user = User::create(User::$types['user'], $uname, $pass, $email, $info['first_name'],
                            $info['last_name'], $_SERVER['REMOTE_ADDR'], intval($this->fuid));

        Auth::setSession($user);
   
        $this->followFriends($user);

        return $user;
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
        
        //haha, I know, that's weird, but works, right?
        $user->follow(
            UsersList::fromIds(
                array_map( function ($x) { return intval($x['id']); },
                    $friends)));

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
