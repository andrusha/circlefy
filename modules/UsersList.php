<?php

/*
    Some generic collection of methods
    to create and user users collections
*/
class UsersList extends Collection {
    /*
        You should use static init-methods,
        not construct it by yourself
    */
    protected function __construct($users) {
        parent::__construct($users, 'UsersList');
    }
    
    /*
        Get stats for every user in list

        @return UsersList
    */
    public function getStats() {
         if (empty($this->data))
            return $this;

         $db = DB::getInstance()->Start_Connection('mysql');

         $uids = $this->filter('uid');

         $query = '
            SELECT i.uid, COUNT(i.mid) AS taps,
                   SUM(i.count) AS responses, g.groups
              FROM (
                    SELECT s.mid AS MID, s.uid AS uid,
                           COUNT(c.mid) AS COUNT
                      FROM special_chat AS s
                     INNER
                      JOIN special_chat_meta scm
                        ON scm.mid = s.mid
                      LEFT
                      JOIN chat c
                        ON c.cid = s.mid
                     WHERE s.uid IN (#uids#)
                       AND scm.private = 0
                       AND scm.connected IN (1, 2)
                     GROUP
                        BY (s.mid)
                   ) AS i
               LEFT
               JOIN (
                    SELECT COUNT(g.uid) AS groups, g.uid AS uid
                      FROM group_members AS g
                     WHERE g.uid IN (#uids#)
                     GROUP
                        BY g.uid
                     ) AS g
                 ON g.uid = i.uid
              GROUP
                 BY i.uid';

         $stats = array();
         $result = $db->query($query, array('uids' => $uids));
         if ($result->num_rows)
             while ($res = $result->fetch_assoc())
                 $stats[ intval($res['uid']) ] = array_map('intval', $res);
 
         foreach ($this->data as $user) {
            if (array_key_exists($user->uid, $stats))
                $user->setStats($stats[$user->uid]);
            else
                $user->setStats(array('taps' => 0, 'responses' => 0, 'groups' => 0));
         }

         return $this;
    }

    /*
        Get relations from specified user to every user in list
    */
    public function getRelations(User $from) {
         if (empty($this->data))
            return $this;

         $db = DB::getInstance()->Start_Connection('mysql');

         $uids = $this->filter('uid');

         $query = '
            SELECT fuid
              FROM friends
             WHERE uid = #uid#
               AND fuid IN (#uids#)';
        $result = $db->query($query, array('uid' => $from->uid, 'uids' => $uids));

        $friends = array();
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $friends[ intval($res['fuid'])  ] = true;

        foreach ($this->data as $user) {
            if (array_key_exists($user->uid, $friends))
                $user->setInfo('friend', 1);
            else
                $user->setInfo('friend', 0);
        }

        return $this;
    }
    
    /*
        Creates a list of users from uid's

        @return UsersList
    */
    public static function fromUids(array $uids) {
        $users = array();
        foreach($uids as $i)
            $users[$i] = new User($i);

        return new UsersList($users);
    }

    /*
        Generic method, generates a list of users based on friends information
        
        @param int $options U_FOLLOWING | U_FOLLOWERS | U_LAST_CHAT | U_BY_UNAME

        @return UsersList
    */
    private static function filterFriends(User $user, $options, array $special_params = array()) {
        $db = DB::getInstance()->Start_Connection('mysql');
        
        $identifier = $options & U_FOLLOWING ? 'uid' : 'fuid';
        $reverse_ident = $options & U_FOLLOWING ? 'fuid' : 'uid';

        $join = $fields = '';

        if ($options & U_BY_UNAME) {
            $special_where .= " AND u.uname LIKE #uname# ";
        }


        if ($options & U_LAST_CHAT) {
            $fields .= ', sc.chat_text AS last_chat';
            $join .= "
                  LEFT
                  JOIN (SELECT uid, chat_text
                          FROM special_chat
                         ORDER
                            BY mid DESC
                       ) AS sc ON sc.uid = f.{$reverse_ident}";
            $special_where .= ' GROUP BY u.uid';
        }

        $query = "
            SELECT u.uid, u.uname, GET_REAL_NAME(u.fname, u.lname, u.uname) AS real_name,
                   u.fname, u.lname, u.pic_100 AS big_pic, u.pic_36 AS small_pic, u.help,
                   u.email, u.private, u.ip, u.anon AS guest{$fields}
              FROM friends AS f
             JOIN login AS u
               ON  u.uid = f.{$reverse_ident}
               {$join}
            WHERE f.{$identifier} = #uid#
            {$special_where}";
        
        $users = array();
        $result = $db->query($query, array_merge($special_params, array('uid' => $user->uid)));
        if ($result->num_rows)
            while ($res = $result->fetch_assoc()) {
                $users[intval($res['uid'])] = new User(array('info' => $res, 'uid' => intval($res['uid'])));
            }

        return new UsersList($users);
    }

    /*
        Returns a list of user you are following

        @return UsersList
    */
    public static function getFollowing(User $user, $chat = false) {
        return UsersList::filterFriends($user, U_FOLLOWING | ($chat ? U_LAST_CHAT : 0));
    }

    /*
        Returns a list of users following you
        
        @return UsersList
    */
    public static function getFollowers(User $user, $chat = false) {
        return UsersList::filterFriends($user, U_FOLLOWERS | ($chat ? U_LAST_CHAT : 0));
    }

    /*
        Filter your following list by username
    */
    public static function friendsByUname(User $user, $uname, $chat = false) {
        $uname = "%$uname%";
        return UsersList::filterFriends($user, U_FOLLOWING | ($chat ? U_LAST_CHAT : 0) | U_BY_UNAME, array('uname' => $uname));
    }

    /*
        Returns a list of users, who sends or recieves PM's
        from specified user

        @return UsersList
    */
    public static function withPM(User $user) {
        $db = DB::getInstance()->Start_Connection('mysql');

        $head = '
             SELECT u.uid, u.uname, GET_REAL_NAME(u.fname, u.lname, u.uname) AS real_name,
                   u.fname, u.lname, u.pic_100 AS big_pic, u.pic_36 AS small_pic, u.help,
                   u.email, u.private, u.ip
              FROM login u';

        // 0 => users who wrote to you
        // 1 => users you wrote to
        $tails = array('
             INNER JOIN special_chat      sc  ON u.uid   = sc.uid
             INNER JOIN special_chat_meta scm ON sc.mid  = scm.mid
             WHERE (scm.uid = #uid# AND scm.private = 1)
             GROUP BY u.uid','
             INNER JOIN special_chat_meta scm ON u.uid   = scm.uid  
             INNER JOIN special_chat      sc  ON scm.mid = sc.mid
             WHERE (sc.uid = #uid# AND scm.private = 1 AND scm.uid IS NOT NULL)
             GROUP BY u.uid');

        $users = array();
        foreach ($tails as $tail) {
            $result = $db->query($head.$tail, array('uid' => $user->uid));
            if ($result->num_rows)
                while ($res = $result->fetch_assoc()) {
                    $uid = intval($res['uid']);
                    if (array_key_exists($uid, $users))
                        continue;

                    $users[$uid] = new User(array('uid' => $uid, 'info' => $res));
                }
        }

        return new UsersList($users);
    } 
};
