<?php
abstract class BaseModel {
    protected $db;

    public function __construct() {
        $this->db = DB::getInstance();
        $this->db->Start_Connection('mysql');
    }
};
