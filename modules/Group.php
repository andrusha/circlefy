<?php
/*
    All things related to groups (channels)
*/
class Group extends BaseModel {
    //a bunch of mappings

    //ENUM('group','school', 'company', 'event', 'location')
    public static $types = array(
        'group' => 1, 'school'   => 2, 'company' => 3,
        'event' => 4, 'location' => 5);

    //ENUM('open', 'manual', 'email', 'ip', 'geo')
    public static $auths = array(
        'open' => 1, 'manual' => 2, 'email' => 3,
        'ip'   => 4, 'geo'    => 5);

    //ENUM('public', 'private', 'official')
    public static $statuses = array(
        'public' => 1, 'private' => 2, 'official' => 3);
    
    //ENUM('blocked','pending','user', 'moderator', 'admin')
    public static $permissions = array(
        'blocked'   => 1, 'pending' => 2, 'user' => 3,
        'moderator' => 4, 'admin'   => 5, 'not_in' => -1);

    public static $fields = array('id', 'tags_group_id', 'fb_id',
        'symbol', 'name', 'descr', 'created_time', 'type', 'auth', 'status',
        'online_count', 'secret', 'auth_email');

    protected static $intFields = array('id', 'tags_group_id', 'fb_id',
        'created_time', 'type', 'auth', 'status', 'online_count', 'secret',
        'ancestor');

    protected static $addit = array('tags', 'members', 'messages_count',
        'members_count', 'responses_count', 'ancestor', 'childs');

    protected static $tableName = 'group';

    public function asArray($format = true) {
        if (!$format)
            return $this->data;

        $data = $this->data;
        if (isset($data['type'])) {
            $types = array_flip(self::$types);
            $data['type'] = $types[$data['type']];
        }
        
        if (isset($data['auth'])) {
            $auths = array_flip(self::$auths);
            $data['auth'] = $auths[$data['auth']];
        }

        if (isset($data['status'])) {
            $statuses = array_flip(self::$statuses);
            $data['status'] = $statuses[$data['status']];
        }
        
        if (isset($data['childs'])) {
            $data['childs'] = $data['childs']->asArrayAll();
        }

        return $data;
    }

    /*
        Kinda save changes
    */
    public function commit() {
        if ($this->tags->getGroupId() !== null) {
           $this->tags->commit();
           $tgid = $this->tags->getGroupId();
           $this->updateTagId($tgid);
        }

        if ($this->id)
           $this->update();
    }

    private function updateTagId($tgid) {
        $query = "
            UPDATE `group`
               SET tags_group_id = #tgid#
             WHERE id = #id#
             LIMIT 1";
        $this->db->query($query, 
            array('id' => $this->id, 'tgid' => $tgid));

        return $this->db->affected_rows == 1;
    }

    /*
        Creates new group from group symbol, fetching only id

        @return Group
    */
    public static function fromSymbol($symbol) {
        return GroupsList::search('bySymbol', array('symbol' => $symbol), G_JUST_ID)
                         ->lastOne();
    }

    public static function byId($id) {
        return GroupsList::search('byGroup', array('gid' => $id))->getFirst();
    }

    /*
        @param int|str $g select by gid|symbol

        @return Group
    */
    public static function init($g) {
        $type = is_int($g) ? 'byGroup' : 'bySymbol';
        $var  = is_int($g) ? 'gid'     : 'symbol';

        return  GroupsList::search($type, array($var => $g),
                              G_TAPS_COUNT | G_USERS_COUNT | G_RESPONSES_COUNT)
                          ->lastOne();
    } 

    /*
        Yeah, right, it simply creates a new group
        
        @returns Group
    */
    public static function create(User $creator, $name, $symbol, $descr, array $tags = null,
                                  $type = 'group', $auth = 'open', $status = 'public', $secret = 0, $auth_email = null) {
        
        $db = DB::getInstance();

        $db->startTransaction();

        if (!is_int($type)) $type = Group::$types[$type];
        if (!is_int($auth)) $auth = Group::$auths[$auth];

        $data = array('symbol' => $symbol, 'name' => $name,
            'descr' => $descr, 'type' => $type, 'auth' => $auth,
            'status' => Group::$statuses[$status], 'secret' => $secret, 'auth_email' => $auth_email);

        try {
            $id = $db->insert('group', $data);
            $db->insert('group_members',
                array('group_id' => $id, 'user_id' => $creator->id,
                      'permission' => Group::$permissions['admin']));

            $data['id'] = $id;
            $group = new Group($data);

            //nested transactions here
            GroupRelations::init($group);

            $db->commit();
        } catch (SQLException $e) {
            $db->rollback();
            throw $e;
        }
                
        if (!empty($tags)) {
            $group->tags->addTags($tags);
            $group->commit();
        }

        return $group;
    }


    /*
        Returns group members
        $online = true - online, false - offline, null - whatever
    */
    public function getMembers($online = null) {
        return UsersList::members($this, 'all', $online);
    }
    
    /*
        Moderate members
        $action = true - approve, false - reject
    */
    public function moderateMembers($users, $action, $perm = null) {
        $perm = $perm !== null ? $perm : Group::$permissions['user'];
        if (!empty($users)) {
            if ($action)
                $query = "
                    UPDATE `group_members`
                       SET `permission` = #perm#
                     WHERE `group_id` = #gid#
                       AND `user_id` IN (#users#)";
            else
                $query = "
                    DELETE
                      FROM `group_members`
                     WHERE `group_id` = #gid#
                       AND `user_id` IN (#users#)";

            $this->db->query($query, array('gid' => $this->id, 'users' => $users, 'perm' => $perm));
        }

        return $action ? $perm : null;
    }

    /*
        @return Tags
    */
    public function getTags() {
        return new Tags($this->data['tags_group_id'] ?: null);
    }

    public function userPermissions(User $u) {
        $query = "
            SELECT permission
              FROM group_members
             WHERE group_id = #gid#
               AND user_id = #uid#";
        $s = -1;
        $r = $this->db->query($query, array('gid' => $this->id, 'uid' => $u->id));
        if ($r->num_rows) {
            $r = $r->fetch_assoc();
            $s = array_flip(Group::$permissions);
            $s = $s[intval($r['permission'])];
        }

        return $s;
    }

    /*
        If user permitted to edit this group (admin, moderator or superadmin)
    */
    public function isPermitted(User $u) {
        return in_array($this->userPermissions($u), array('moderator', 'admin')) ||
               $u->type == User::$types['superadmin'];
    }
    
    /*
        Send activation email to user to confirm email address
    */
    public function sendActivation(User $u, $email) {
        // generate random hash code
        $token = "";
        for($p = 0; $p<3; $p++)
        	$token .= (string)mt_rand();
        $token = sha1('circl'.$token.'efy');
        
        try {
            $id = $this->db->insert('group_members', array(
                'group_id' => $this->id,
                 'user_id' => $u->id,
              'permission' => Group::$permissions['pending'],
               'join_code' => $token
            ));
            $added = true;
        } catch (SQLException $e) {
            $added = false;
            throw $e;
        }

        if ($added) {
            $link = 'http://'. DOMAIN . '/circle/'. $this->symbol . '?confirm='.$token;
            Mailer::joinConfirm($u, $email, $this, $link);
        }

        return $this;
    }
    
    /*
        Confirms user email to join to group.
    */
    public function confirmEmail($token) {
        $query = "SELECT * 
                  FROM `group_members` 
                  WHERE `join_code` = #code#";
        $db = DB::getInstance();
        $r = $db->query($query, array('code' => $token));
        if ($r->num_rows) {
            $query = "
                UPDATE `group_members`
                   SET `permission` = #perm#
                 WHERE `join_code` = #code#";
            $db->query($query, array('code' => $token, 'perm' => Group::$permissions['user']));
        }
        
        return $r;
    }

    /*
        Save user context in group
    */
    public function saveContext(User $user, $context) {
        $query = "
            UPDATE `group_members`
               SET `context` = #ctx#
             WHERE `group_id` = #gid#
               AND `user_id` = #uid#";
        $db = DB::getInstance();
        $r = $db->query($query, array('ctx' => $context, 'gid' => $this->id, 'uid' => $user->id));
        return $r;
    }

    public function setDefaultAvatar() {
        foreach (array('small', 'medium', 'large') as $size) {
            $img = GROUP_PIC_PATH."/{$size}_{$this->id}.jpg";
            @unlink($img);
            symlink(GROUP_PIC_PATH."/{$size}_group.png", $img);
        }
    }
};
