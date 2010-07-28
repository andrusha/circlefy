<?php

class pending_members extends Base {

    protected $text;
    protected $top;

    private $mysqli;

    public function __default() {
    }

    public function __toString() {
        return "Example page Object";
    }

    public function __construct() {

        $this->view_output = "HTML";
        $this->db_type = "mysql";
        $this->page_name = "pending_members";
        $this->need_login = 1;
        $this->need_db = 1;

        parent::__construct();

        $this->mysqli = $this->db_class_mysql->db;

        $uid = $_SESSION['uid'];
        $foo_var = $_GET['foo_var'];

        //This is the way we assign variables to be visible in the template files
        $this->set($foo_var, 'foo_var');

        $this->set('Test', 'test');

        $this->set($this->get_requested_members($uid), 'members');
    }

    private function get_requested_members($uid){
        $q = "SELECT u.uid, u.uname, u.pic_36, m.gid, g.gname
            FROM group_members m
            INNER JOIN login u ON u.uid = m.uid
            INNER JOIN groups g ON m.gid = g.gid
            WHERE
                g.gid = (SELECT gm.gid FROM group_members gm WHERE gm.uid = {$uid})
                AND admin != 1
                AND m.status = 0
            LIMIT 0, 20";

        $result = $this->mysqli->query($q);

        $rows = array();
        while ($row = $result->fetch_assoc()){
                $rows[] = $row;
        }

        return $rows;
    }
}
