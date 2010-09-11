<?php
abstract class GroupException extends Exception {};
class GroupInitializeException extends GroupException {};
class GroupDataException extends GroupException {};

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
            $this->gid = $gid['gid'];
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
                $tagId = intval($this->info['tag_group_id']);
                $this->taglist = new Tags($tagId);
            }

            return $this->taglist;
        } else if ($key == 'gid') {
            return $this->gid;
        }

        throw new GroupDataException("Can not find any group info related to '$key'");
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
        Returns a list of groups, sorted by relevancy,
        after keyword search
    */
    public static function listByKeywords(array $keywords) {
        if (empty($keywords))
            return array();

        $db = DB::getInstance()->Start_Connection('mysql');
        
        $tagGroups = array_keys(Tags::filterGroupsByTags($keywords));
        if (empty($tagGroups))
            return array();
        
        $query = '
            SELECT gid, gname, symbol
              FROM groups
             WHERE tag_group_id IN (#tagidlist#)
                OR symbol IN (#tagidlist#)
             LIMIT 25';

        $groups = array();
        $result = $db->query($query, array('tagidlist' => $tagGroups));
        if ($result->num_rows)
            while ($res = $result->fetch_assoc()) {
                $data = array('info' => $res, 'gid' => intval($res['gid']));
                $groups[] = new Group($data);
            }

        return $groups;
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
