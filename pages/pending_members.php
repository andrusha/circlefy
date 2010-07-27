<?php

class pending_members extends Base {

    protected $text;
    protected $top;

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

        $uid = $_SESSION['uid'];
        $foo_var = $_GET['foo_var'];

        //This is the way we assign variables to be visible in the template files
        $this->set($foo_var, 'foo_var');

        $this->set('Test', 'test');

    }


}
