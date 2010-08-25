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
        userpics, username, help, real name, email, private settings
    */
    public function getInfo($uid) {
        $query = "SELECT uname, GET_REAL_NAME(fname, lname, uname) AS real_name,
                         pic_100 AS big_pic, pic_36 AS small_pic, help,
                         email, private
                    FROM login
                   WHERE uid = {$uid}
                   LIMIT 1";
        $info = array();
        $result = $this->db->query($query);
        if ($result->num_rows)
            $info = $result->fetch_assoc();
        return $info;
    }

    /*
        User information from profile table too, like about, etc
    */
    public function getFullInfo($uid) {
        $query = "SELECT l.uname, GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name,
                         l.pic_100 AS big_pic, l.pic_36 AS small_pic, l.help,
                         p.about
                    FROM login l
                   INNER
                    JOIN profile p
                      ON p.rpid = l.uid
                   WHERE l.uid = {$uid}
                   LIMIT 1";
        $info = array();
        $result = $this->db->query($query);
        if ($result->num_rows)
            $info = $result->fetch_assoc();
        return $info;
    }

    /*
        Returns user statistics
        taps, responses & following channels count

        Taps & responses counts only for taps
        in public groups (connected 1, 2)

        $uid - user id
        
        array('taps' => , 'responses' => , 'groups' => )
    */
    public function getStats($uid) {
        $query = "
         SELECT COUNT(i.mid) AS taps,
                SUM(i.count) AS responses,
                (
                    SELECT COUNT(g.uid) AS groups
                      FROM group_members AS g
                     WHERE g.uid = {$uid}
                     GROUP
                        BY g.uid
                ) AS groups
           FROM (
                    SELECT s.mid AS mid,
                           COUNT(c.mid) AS count
                      FROM special_chat AS s
                     INNER
                      JOIN special_chat_meta scm
                        ON scm.mid = s.mid
                      LEFT
                      JOIN chat c
                        ON c.cid = s.mid
                     WHERE s.uid = {$uid}
                       AND scm.connected IN (1, 2)
                     GROUP
                        BY (s.mid)
                ) AS i";
        
        $stats = array();
        $result = $this->db->query($query);
        if ($result->num_rows)
            $stats = $result->fetch_assoc();
        return $stats;
    }
};
