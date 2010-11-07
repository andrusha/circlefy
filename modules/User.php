<?php
/*
    All user operations, e.g login in, information
*/
class User extends BaseModel {
    //ENUM('guest', 'user', 'banned', 'private')
    public static $types = array('guest' => 1, 'user' => 2, 'banned' => 3, 'private' => 4, 'superadmin' => 5);

    //ENUM('yes','no','new')
    public static $accepts = array('no' => 0, 'yes' => 1, 'new' => 2);

    public static $fields = array('id', 'fb_id', 'type', 'uname', 'pass', 'email', 'fname',
        'lname', 'ip', 'last_login', 'online');

    protected static $intFields = array('id', 'fb_id', 'type', 'ip', 'last_login', 'online');

    protected static $addit = array('stats', 'friend', 'last_chat', 'guest');

    protected static $tableName = 'user';

    public function __get($key) {
        if ($key == 'ip' && $this->id == $_SESSION['uid'])
            return $_SERVER['REMOTE_ADDR'];
        elseif ($key == 'guest')
            return $this->id === null ||
                   $this->data['type'] == self::$types['guest'];

        return parent::__get($key);
    }

    public function asArray($formatted = true) {
        //security issue
        $exclude = array('pass', 'ip', 'last_login');
        if ($formatted)
            $exclude[] = 'email' ;
        return array_diff_key($this->data, array_flip($exclude));
    }

    /*
        @return User
    */
    public static function fromUname($uname) {
        return UsersList::search('byUname', array('uname' => $uname), U_JUST_ID)
                        ->lastOne();
    }

    /*
        Returns user with full info

        @param int|str $u
        @return User
    */
    public static function init($u) {
        $type = is_int($u) ? 'byId' : 'byUname';
        $var  = is_int($u) ? 'id'   : 'uname';
        return UsersList::search($type, array($var => $u))
                        ->lastOne();
    }

    /*
        Creates new user, yep you should specify ID = Facebook ID

        @return User
    */
    public static function create($type, $uname, $pass, $email, $fname, $lname, $ip, $fb_id = null) {
        $db = DB::getInstance();
        $data = array('fb_id' => $fb_id, 'type' => $type, 'uname' => $uname, 'pass' => md5($pass),
            'email' => $email, 'fname' => $fname, 'lname' => $lname, 'ip' => $ip);
        $id = (int)$db->insert('user', $data);
        $data['id'] = $id;
        return new User($data);
    }

    /*
        Checks if field=val combination exists
    */
    public static function existsField($field, $val) {
        $db = DB::getInstance();
        $field = $db->real_escape_string($field);
        $query = "SELECT `$field` FROM user WHERE `$field` = #val# LIMIT 1";
        return $db->query($query, array('val' => $val))->num_rows == 1;
    }

    /*
        Checks if user exist (note id == facebook id)

        @param int $id
        @return bool
    */
    public static function exists($id) {
        return User::existsField('id', $id);
    }

    /*
        Returns user statistics
        taps, responses & following channels count

        array('messages' => , 'responses' => , 'groups' => )
    */
    public function getStats() {
        $query = "
         SELECT COUNT(i.mid) AS messages,
                SUM(i.count) AS responses,
                (
                    SELECT COUNT(gm.group_id) AS groups
                      FROM group_members AS gm
                     WHERE gm.user_id = #uid#
                     GROUP
                        BY gm.user_id
                ) AS groups
           FROM (
                    SELECT m.id AS mid,
                           COUNT(r.message_id) AS count
                      FROM message m
                      LEFT
                      JOIN reply r
                        ON r.message_id = m.id
                     WHERE m.sender_id = #uid#
                     GROUP
                        BY m.id
                ) AS i";

        $stats = array();
        $result = $this->db->query($query, array('uid' => $this->id));
        if ($result->num_rows)
            $stats = $result->fetch_assoc();

        $stats = array_map('intval', $stats);
        //$this->data = array_merge($this->data, $stats);

        return $stats;
    }

    /*
        Returns number of friends

        $youFollowing = true - you following
        $youFollowing = false - follows you
    */
    private function friendsCount($youFollowing = true) {
        $identifier = $youFollowing ? 'user_id' : 'friend_id';
        $query = "
            SELECT COUNT(user_id) AS count
              FROM friends
             WHERE {$identifier} = #uid#";
        $result = $this->db->query($query, array('uid' => $this->id))->fetch_assoc();
        return intval($result['count']);
    }

    public function followingCount() {
        return $this->friendsCount(true);
    }

    public function followersCount() {
        return $this->friendsCount(false);
    }

    /*
        Follows user or list of users

        @param User|UsersList $friend 
    */    
    public function follow($friends) {
        if ($friends instanceof UsersList) 
            foreach ($friends as $f)
                $values[] = array($this->id, $f->id);
        elseif ($friends instanceof User)
            $values = array(array($this->id, $friends->id));
       
        if (empty($values))
            return $this;

        $query = "INSERT
                    INTO friends (user_id, friend_id)
                  VALUES #values#
                      ON DUPLICATE KEY
                  UPDATE accept = VALUES(accept)";
        $this->db->listInsert($query, $values);

        foreach ($values as $v)
            Comet::send('user.follow', array('who' => $this->id, 'whom' => $v[1], 'status' => 1, 'user' =>
                $this->asArray()));

        return $this;
    }

    /*
        Unfollows user
    */
    public function unfollow(User $friend) {
        $query = "DELETE
                    FROM friends
                   WHERE friend_id = #friend#
                     AND user_id = #you#
                   LIMIT 1";
        $result = $this->db->query($query, array('you' => $this->id, 'friend' => $friend->id))->affected_rows == 1;
        Comet::send('user.follow', array('who' => $this->id, 'whom' => $friend->id, 'status' => 0, 'user' =>
            $this->asArray()));
        return $result;
    }
    
    /*
        Check if user following you or not
    */
    public function following(User $friend) {
        $query = "SELECT friend_id
                    FROM friends
                   WHERE friend_id = #friend#
                     AND user_id = #you#
                   LIMIT 1";
        return $this->db->query($query, array('you' => $this->id, 'friend' => $friend->id))->num_rows == 1;
    }

    /*
        Make user a group member

        TODO: Auth
    */
    public function join(Group $group) {
         $perm = Group::$permissions['user'];
        $group = Group::byId($group->id);
        
        // Group is moderated
        if ($group->auth == Group::$auths['manual']) {
            $manual = true;
            $perm = Group::$permissions['pending'];
        }
        
        // Group is email based auth
        // TODO
        
        if ($this->inGroup($group)) {
            $query = "
                UPDATE `group_members`
                   SET `permission` = #perm#
                 WHERE `group_id` = #gid# AND `user_id` = #uid#";
        } else {
           $query = "
                INSERT
                  INTO `group_members` (`group_id`, `user_id`, `permission`)
                VALUES (#gid#, #uid#, #perm#)";
        }
        $result = $this->db->query($query, array('gid' => $group->id, 'uid' => $this->id, 'perm' => $perm))->affected_rows == 1;
        if ($result)
            Comet::send('group.follow', array('group_id' => $group->id, 'user_id' => $this->id, 'status' => 1));
        return $result;
    }

    public function leave(Group $g) {
        $query = "DELETE
                    FROM group_members
                   WHERE group_id = #gid#
                     AND user_id  = #uid#";
        $result = $this->db->query($query, array('gid' => $g->id, 'uid' => $this->id))->affected_rows == 1;
        Comet::send('group.follow', array('group_id' => $g->id, 'user_id' => $this->id, 'status' => 0));
        return $result;
    }
    

    /*
        Is user member of group?
    */
    public function inGroup(Group $g) {
        $query = "
            SELECT * 
              FROM group_members
             WHERE group_id = #gid# AND user_id = #uid#";
        return $this->db->query($query, array('gid' => $g->id, 'uid' => $this->id))->num_rows == 1;
    }

    public function delete() {
        $queries = array(
            'delete from conversations where user_id = #id# or message_id in (select id from message where sender_id = #id# or reciever_id = #id#);',
            'delete from reply where user_id = #id# or message_id in (select id from message where sender_id = #id# or reciever_id = #id#);',
            'delete from message where sender_id = #id# or reciever_id = #id#;',
            'delete from group_members where user_id = #id#;',
            'delete from friends where user_id = #id# or friend_id = #id#;',
            'delete from notification_settings where user_id = #id#;',
            'delete from user where id = #id#;'
        );        
        
        $this->db->startTransaction();
        try {
            foreach ($queries as $q)
                $this->db->query($q, array('id' => $this->id));
        } catch (SQLException $e) {
            $this->db->rollback();
            throw $e;
        }

        $this->db->commit();
    }
    
    public function deleteEvent(User $u) {
        $query = 'DELETE FROM events WHERE user_id = #uid# AND type = 2 AND related_id = #fid#';
        $this->db->query($query, array('uid' => $u->id, 'fid' => $this->id));
    }
};
