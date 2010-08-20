<?php

/*
Class for all things related to friends
*/
class Peoples {
    private $db;

    public function __construct() {
        $this->db = new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

    /*
        Returns number of friends

        $youFollowing = true - you following
        $youFollowing = false - follows you

        $uid - your uid
    */
    private function friendsCount($uid, $youFollowing = true) {
        $identifier = $youFollowing ? 'uid' : 'fuid';
        $query = "
            SELECT COUNT(*) AS count
              FROM friends AS f
             WHERE f.{$identifier} = {$uid}";
        $result = $this->db->query($query)->fetch_assoc();
        return intval($result['count']);
    }

    public function followingCount($uid) {
        return $this->friendsCount($uid, true);
    }

    public function followersCount($uid) {
        return $this->friendsCount($uid, false);
    }

    /*
        Returns dictionary of friends

        $youFollowing = true - you following
        $youFollowing = false - follows you

        $uid - your id

        array(uid => array(info), ...
    */
    private function filterFriends($uid, $youFollowing = true, $special_where = "") {
        $identifier = $youFollowing ? 'uid' : 'fuid';
        $reverse_ident = $youFollowing ? 'fuid' : 'uid';
        $query = "
            SELECT u.uid, u.uname, u.fname, u.lname, u.pic_100, sc.chat_text AS last_chat
              FROM friends AS f
              LEFT
              JOIN (SELECT uid, chat_text
                      FROM special_chat
                     ORDER
                        BY mid DESC
                   ) AS sc ON sc.uid = f.{$reverse_ident}
             JOIN login AS u
               ON  u.uid = f.{$reverse_ident}
            WHERE f.{$identifier} = {$uid}
            {$special_where}
            GROUP
               BY u.uid;";

        $users = array();
        $result = $this->db->query($query);
        while ($res = $result->fetch_assoc()) {
            $users[intval($res['uid'])] = $res;
        }

        return $users;
    }

    public function getFollowing($uid) {
        return $this->filterFriends($uid, true);
    }

    public function getFollowers($uid) {
        return $this->filterFriends($uid, false);
    }

    public function filterByUname($uid, $uname) {
        return $this->filterFriends($uid, true, " AND u.uname LIKE '%{$uname}%' ");
    }

    /*
        Returns user statistics
        taps, responses & following channels count

        $uid - user id
        
        array('taps' => , 'responses' => , 'groups' => )
    */
    public function userStats($uid) {
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
                      JOIN chat c
                        ON c.cid = s.mid
                     WHERE s.uid = {$uid}
                     GROUP
                        BY (s.mid)
                ) AS i";

        $result = $this->db->query($query)->fetch_assoc();
        return $result;
    }

};

