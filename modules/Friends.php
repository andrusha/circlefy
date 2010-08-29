<?php

/*
Class for all things related to friends
*/
class Friends extends BaseModel {
    public function __construct() {
        parent::__construct();
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
             WHERE f.{$identifier} = #uid#";
        $result = $this->db->query($query, array('uid' => $uid))->fetch_assoc();
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
    private function filterFriends($uid, $youFollowing = true, $special_where = "", $special_params = "") {
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
            WHERE f.{$identifier} = #uid#
            {$special_where}
            GROUP
               BY u.uid;";
        
        $users = array();
        $result = $this->db->query($query, array_merge($special_params, array('uid' => $uid)));
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
        $uname = "%$uname%";
        return $this->filterFriends($uid, true, " AND u.uname LIKE #uname# ", array('uname' => $uname));
    }

    /*
        Follows user

        $you int - your uid
        $friend int|array - friend uid, or list of uids
    */    
    public function follow($you, $friend) {
        $you = intval($you);
        if (is_array($friend)) {
            $values = '';
            foreach($friend as $fuid) {
                $fuid = intval($fuid);
                $values .= "({$you}, {$fuid}, NOW())";
            }
            $values = substr($values, 1);
        } else {
            $friend = intval($friend);
            $values = "({$you}, {$friend}, NOW())"; 
        }

        $query = "INSERT
                    INTO friends (uid, fuid, time_stamp)
                  VALUES {$values}";

        $okay = $this->db->query($query)->affected_rows >= 1;
        return $okay;
    }

    /*
        Unfollows user
    */
    public function unfollow($you, $friend) {
        $query = "DELETE
                    FROM friends
                   WHERE fuid = #friend#
                     AND uid = #you#
                   LIMIT 1";
        $okay = $this->db->query($query, array('you' => $you, 'friend' => $friend))->affected_rows == 1;
        return $okay;
    }
    
    /*
        Check if user following you or not
    */
    public function following($you, $friend) {
        $query = "SELECT fuid
                    FROM friends
                   WHERE fuid = #friend#
                     AND uid = #you#
                   LIMIT 1";
        $okay = $this->db->query($query, array('you' => $you, 'friend' => $friend))->num_rows == 1;
        return $okay;
    }
};

