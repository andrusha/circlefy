<?php

/*
All user operations, e.g login in
*/
class User {
    private $db;
    private $loggedIn = false;

    public function __construct() {
        $this->db = new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

    public function logIn($username, $password) {
        $query = "SELECT uid FROM login WHERE uname = '$username' AND pass = MD5('$password') LIMIT 1";
        $exists = $this->db->query($query)->num_rows > 0;
        $this->loggedIn = $exists;
        return $exists;
    }
};
