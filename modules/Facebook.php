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

    private $curl = null;

    public function __construct() {
        parent::__construct(null);
        $this->curl = new Curl();

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

        try {
            $raw = $this->curl->get('https://graph.facebook.com/', 
                array('ids' => implode(',', $ids), 'metadata' => 1, 
                      'locale' => 'en_US', 'access_token' => $this->access_token));
        } catch (NetworkException $e) {
            return array();
        }

        $result = json_decode($raw, true); 
        if (DEBUG)
            FirePHP::getInstance(true)->log($result, 'FB-groups');
        return $result;
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

        try {
            $raw = $this->curl->get('https://graph.facebook.com/'.$id.$add,
                array('metadata' => 1, 'locale' => 'en_US', 'access_token' => $this->access_token));
        } catch (NetworkException $e) {
            return array();
        }

        $info = json_decode($raw, true);
        if (isset($selector))
            $info = $info[$selector];

        $this->fetched_info[$uid][$type] = $info;
        if (DEBUG)
            FirePHP::getInstance(true)->log($info, $type.' for '.$id);

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
    public function createWithFacebook($uname, $pass) {
        $info = $this->getUserInfo();
        if (!$info)
            return false;

        $user = User::create(User::$types['user'], $uname, $pass, $info['email'], $info['first_name'],
                            $info['last_name'], $_SERVER['REMOTE_ADDR'], intval($this->fuid));

        try {
            //download & resize userpics
            $big_name = USER_PIC_PATH.$this->fuid.'.jpg';
            $this->curl->saveFile('http://graph.facebook.com/'.$this->fuid.'/picture', $big_name, array('type' => 'large'));
            list($big_name, $large, $medium, $small) = Images::makeUserpics($user->id, $big_name, USER_PIC_PATH);
        } catch (NetworkEeception $e) {
            // if we can't fetch user image for some reason - link default ones
            $user->setDefaultAvatar();
        } catch (ImagickException $e) {
            if (DEBUG)
                FirePHP::getInstance(true)->trace($e);

            $user->setDefaultAvatar();
        }


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
        if (DEBUG)
            FirePHP::getInstance(true)->log($friends);

        if (empty($friends))
            return true;
        
        $friends = UsersList::fromIds(
                array_map( function ($x) { return intval($x['id']); },
                    $friends), true);
        if (DEBUG)
            FirePHP::getInstance(true)->log($friends);
        //haha, I know, that's weird, but works, right?
        $user->follow($friends);

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

        try {
            $this->curl->open($url, $post);
        } catch (NetworkException $e) {
            return false;
        }

        return true;
    }
};
