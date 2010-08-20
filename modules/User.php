<?php

/*
All user operations, e.g login in, information
*/
class User {
    private $db;
    private $loggedIn = false;

    public function __construct() {
        $this->db = new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

    /*
        Check if username & password is right
    */
    public function logIn($username, $password) {
        $query = "SELECT uid
                    FROM login
                   WHERE uname = '$username' AND pass = MD5('$password')
                   LIMIT 1";
        $exists = $this->db->query($query)->num_rows > 0;
        $this->loggedIn = $exists;
        return $exists;
    }

    /*
        Returns info about user
        userpics, username, help, real name
    */
    public function getInfo($uid) {
        $query = "SELECT uname, GET_REAL_NAME(fname, lname, uname) AS real_name,
                         pic_100 AS big_pic, pic_36 AS small_pic, help
                    FROM login
                   WHERE uid = {$uid}
                   LIMIT 1";
        $info = $this->db->query($query)->fetch_assoc();
        return $info;
    }
};
