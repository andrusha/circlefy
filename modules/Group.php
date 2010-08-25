<?php

/*
    All things related to groups (channels)
*/
class Group {
    private $db;

    public function __construct() {
        $this->db = new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

    /*
        Returns necessary information about group


    */
    public function getInfo($gid) {
        $query = "
            SELECT gname, symbol
              FROM groups
             WHERE gid = {$gid} 
             LIMIT 1";
        $info = array();
        $result = $this->db->query($query);
        if ($result->num_rows)
            $info = $result->fetch_assoc();

        return $info;
    }

    public function gidFromSymbol($symbol) {
        $query = "
            SELECT gid
              FROM groups
             WHERE symbol = '{$symbol}'
             LIMIT 1";

        $gid = null;
        $result = $this->db->query($query);
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $gid = intval($result['gid']);
        }

        return $gid;
    }
};
