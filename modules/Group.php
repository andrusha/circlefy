<?php

/*
    All things related to groups (channels)
*/
class Group extends BaseModel {
    public function __construct() {
        parent::__construct();
    }

    /*
        Returns necessary information about group


    */
    public function getInfo($gid) {
        $query = "
            SELECT gname, symbol
              FROM groups
             WHERE gid = #gid#
             LIMIT 1";
        $info = array();
        $result = $this->db->query($query, array('gid' => $gid));
        if ($result->num_rows)
            $info = $result->fetch_assoc();

        return $info;
    }

    public function gidFromSymbol($symbol) {
        $query = "
            SELECT gid
              FROM groups
             WHERE symbol = #symbol#
             LIMIT 1";

        $gid = null;
        $result = $this->db->query($query, array('symbol' => $symbol));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $gid = intval($result['gid']);
        }

        return $gid;
    }

    /*
        Returns group members
        $online = true - online, false - offline, null - whatever
    */
    public function getMembers($gid, $online = null) {
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
        $result = $this->db->query($query, array('gid' => $gid));
        if ($result->num_rows)
            while($res = $result->fetch_assoc())
                $users[] = intval($res['uid']);

        return $users;
    }
};
