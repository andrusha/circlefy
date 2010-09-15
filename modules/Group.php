<?php
/*
    All things related to groups (channels)
*/
class Group extends BaseModel {
    /*
        A list of tags, on what current group belongs

        @instance Tags
    */
    private $taglist = null;

    /*
        array with some useful group data
    */
    private $data = array();

    private $gid = null;

    /*
        gid =
            null for new group creating
            int to get info from existing group
            array to auto-initialize group
    */
    public function __construct($gid = null) {
        parent::__construct();
    
        if (is_array($gid)) {
            $this->data = $gid;
            $this->gid = intval($gid['gid']);
        } else {
            $this->gid = $gid;
        }
    }

    /*
        Fancy interface for groups, you should set or
        group id, before fetching any info

        $object->info    = returns group info
               ->members = returns all group members
               ->tags    = returns group tags
    */
    public function __get($key) {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        } else if (method_exists($this, 'get'.ucfirst($key))) {
            if ($this->gid === null)
                throw new GroupInitializeException('You should set group id or create new group before fetching data');

            $name = 'get'.ucfirst($key);
            $this->data[$key] = $this->$name();

            return $this->data[$key];
        } else if ($key == 'tags') {
            if ($this->taglist === null) {
                $tagId = null;
                if (!empty($this->data['info']['tag_group_id']))
                    $tagId = intval($this->data['info']['tag_group_id']);
                $this->taglist = new Tags($tagId);
            }

            return $this->taglist;
        } else if ($key == 'gid') {
            return $this->gid;
        }

        throw new GroupDataException("Can not find any group info related to '$key'");
    }

    /*
        Set's group->info, but NOT update table
    */
    public function set($key, $value) {
        $this->data['info'][$key] = $value;
    }

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
            UPDATE groups
               SET tag_group_id = #tgid#
             WHERE gid = #gid#
             LIMIT 1";
        $this->db->query($query, 
            array('gid' => $this->gid, 'tgid' => $tgid));

        return $this->db->affected_rows == 1;
    }

    /*
        Creates new group from group symbol
    */
    public static function fromSymbol($symbol) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $query = "
            SELECT gid
              FROM groups
             WHERE symbol = #symbol#
             LIMIT 1";

        $result = $db->query($query, array('symbol' => $symbol));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $gid = intval($result['gid']);

            return new Group($gid);
        }

        return null;
    }

    /*
        Yeah, right, it simply creates a new group
        
        @returns Group | bool
    */
    public static function create(User $creator, $gname, $symbol, $descr, array $tags, array $images = array(), $favicon = null) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $db->startTransaction();
        $ok = true;

        $data = array('gname' => $gname, 'symbol' => $symbol, 'descr' => $descr,
            'uid' => $creator->uid);
        $addFields = $addVals = '';
        if (count($images) == 4) {
            $addFields = ', pic_full, pic_180, pic_100, pic_36';
            $addVals = ', #pfull#, #p180#, #p100#, #p36#';
            $data = array_merge($data, array('pfull' => $images[0],
                'p180' => $images[1], 'p100' => $images[2], 'p36' => $images[3]));
        }

        if ($favicon !== null) {
            $addFields .= ', favicon';
            $addVals .= ', #fav#';
            $data['fav'] = $favicon;
        }

        //insert group info into groups
        $query = "
            INSERT
              INTO groups (gname, symbol, gadmin, descr, created{$addFields})
            VALUES (#gname#, #symbol#, #uid#, #descr#, NOW(){$addVals})";
        $ok = $ok && $db->query($query, $data);

        $gid = $db->insert_id;

        //make group online
        $query = "
            INSERT
              INTO GROUP_ONLINE (gid)
             VALUES (#gid#)";
        $ok = $ok && $db->query($query, array('gid' => $gid));

        //make creator a group member & admin
        $query = "
            INSERT
              INTO group_members (uid, gid, admin, status)
            VALUES (#uid#, #gid#, 1, 1)";
        $ok = $ok && $db->query($query, array('uid' => $creator->uid, 'gid' => $gid));

        if (!$ok) {
            $db->rollback();
            return false;
        }

        $db->commit();

        //create Group object & add tags to it
        $data = array('info' => array('gid' => $gid, 'gname' => $gname,
            'symbol' => $symbol, 'descr' => $descr),
            'gid' => $gid);
        $group = new Group($data);
        $group->tags->addTags($tags);
        $group->commit();

        return $group;
    }

    /*
        Returns necessary information about group
    */
    public function getInfo() {
        $query = "
            SELECT gname, symbol
              FROM groups
             WHERE gid = #gid#
             LIMIT 1";
        $info = array();
        $result = $this->db->query($query, array('gid' => $this->gid));
        if ($result->num_rows)
            $info = $result->fetch_assoc();

        return $info;
    }

    /*
        Returns group members
        $online = true - online, false - offline, null - whatever
    */
    public function getMembers($online = null) {
        if ($online !== null) {
            $join = "
                INNER JOIN TEMP_ONLINE tmo
                        ON tmo.uid = g.uid";
            $where = " AND tmo.online = ".($online ? 1 : 0)." ";
        }

        $query = "
            SELECT g.uid
              FROM group_members g
                {$join}
             WHERE g.gid = #gid#
                {$where}";

        $users = array();
        $result = $this->db->query($query, array('gid' => $this->gid));
        if ($result->num_rows)
            while($res = $result->fetch_assoc())
                $users[] = intval($res['uid']);

        return $users;
    }

    /*
        Make specified user a group member
    */
    public function join(User $user) {
        $query = "
            INSERT
              INTO group_members (gid, uid, admin, status)
            SELECT #gid# AS gid, #uid# AS uid, (gadmin = #uid#) AS admin, 1 AS status
              FROM groups g
             WHERE g.gid = #gid#
                ON DUPLICATE KEY
            UPDATE status = 1,
                   admin = VALUES(admin)";
        $this->db->query($query, array('gid' => $this->gid, 'uid' => $user->uid));
        $status = $this->db->affected_rows == 1;

        return $status;
    }

    /*
        Make user member of list of groups

        array(Group, Group, ...)
    */
    public static function bulkJoin(User $user, array $groups) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $list = array();
        foreach($groups as $g)
            $list[] = array($g->gid, $user->uid, 1, 0);

        $query = "
            INSERT
              INTO group_members (gid, uid, status, admin)
            VALUES  #values#
                ON DUPLICATE KEY
            UPDATE status = 1";
        $status = $db->listInsert($query, $list);

        return $status;
    }

};
