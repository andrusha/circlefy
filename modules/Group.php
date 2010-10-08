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
        'moderator' => 4, 'admin'   => 5);

    public static $fields = array('id', 'parent_id', 'tags_group_id', 'symbol',
        'name', 'descr', 'created_time', 'type', 'auth', 'status', 'online_count',
        'secret');

    protected static $intFields = array('id', 'parent_id', 'tags_group_id', 'created_time',
        'type', 'auth', 'status', 'online_count', 'secret');

    protected static $addit = array('tags', 'members', 'taps_count',
        'members_count', 'responses_count');

    /*
        Kinda save changes
    */
    public function commit() {
        if ($this->taglist !== null) {
           $this->taglist->commit();
           $tgid = $this->taglist->getGroupId();
           $this->updateTagId($tgid);
        }
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

    /*
        @param int|str $g select by gid|symbol

        @return Group
    */
    public static function init($g) {
        $type = is_int($g) ? 'byGroup' : 'bySymbol';
        $var  = is_int($g) ? 'id'      : 'symbol';

        $group =  GroupsList::search($type, array($var => $g),
                              G_TAPS_COUNT | G_USERS_COUNT | G_RESPONSES_COUNT)
                            ->lastOne();
        $group->set('topic', FuncLib::linkify($group->topic));
        return $group;
    }

    /*
        Yeah, right, it simply creates a new group
        
        @returns Group
    */
    public static function create(User $creator, Group $parent, $name, $symbol, $descr, array $tags,
                                  $type = 'group', $auth = 'open', $status = 'public', $secret = false) {
        $db = DB::getInstance();

        $db->startTransaction();

        $data = array('parent_id' => $parent->id, 'symbol' => $symbol, 'name' => $name,
            'descr' => $descr, 'type' => Group::$types[$type], 'auth' => Group::$auths[$auth],
            'status' => Group::$statuses[$status], 'secret' => $secret);

        try {
            $id = $db->insert('group', $data);
            $db->insert('group_members',
                array('group_id' => $id, 'user_id' => $creator->id,
                      'permission' => Group::$permissions['admin']));

        } catch (SQLException $e) {
            $db->rollback();
            throw $e;
        }

        $db->commit();

        $data['id'] = $id;
        $group = new Group($data);
        $group->tags->addTags($tags);
        $group->commit();

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
};
