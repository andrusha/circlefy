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
        $result = $this->db->query($query)->fetch_assoc();
        return $result;
    }
};
