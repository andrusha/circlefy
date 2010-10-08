<?php

/*
    Some generic collection of methods
    to create and user users collections
*/
class UsersList extends Collection {

    /*
        Get stats for every user in list

        @return UsersList
    */
    public function getStats() {
         if (empty($this->data))
            return $this;

         $db = DB::getInstance();

         $uids = $this->filter('id');

         $query = '
            SELECT i.id, COUNT(i.mid) AS messages,
                   SUM(i.count) AS responses, g.groups
              FROM (
                    SELECT m.id AS mid, m.sender_id AS id,
                           COUNT(m.id) AS count
                      FROM messages m
                      LEFT
                      JOIN responses r 
                        ON r.message_id = m.id
                     WHERE m.sender_id IN (#uids#)
                     GROUP
                        BY (m.id)
                   ) AS i
               LEFT
               JOIN (
                    SELECT COUNT(gm.user_id) AS groups, gm.user_id AS id
                      FROM group_members gm
                     WHERE gm.user_id IN (#uids#)
                     GROUP
                        BY gm.user_id
                     ) AS g
                 ON g.id = i.id
              GROUP
                 BY i.id';

         $stats = array();
         $result = $db->query($query, array('uids' => $uids));
         if ($result->num_rows)
             while ($res = $result->fetch_assoc())
                 $stats[ intval($res['uid']) ] = array_map('intval', $res);

         $this->joinDataById($stats, 'stats', array('messages' => 0, 'responses' => 0, 'groups' => 0));

         return $this;
    }

    /*
        Get relations from specified user to every user in list
    */
    public function getRelations(User $from) {
         if (empty($this->data))
            return $this;

         $db = DB::getInstance();

         $uids = $this->filter('id');

         $query = '
            SELECT friend_id
              FROM friends
             WHERE user_id = #uid#
               AND friend_id IN (#uids#)';
        $result = $db->query($query, array('uid' => $from->id, 'uids' => $uids));

        $friends = array();
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $friends[ intval($res['friend_id'])  ] = true;

        $this->joinDataById($friends, 'friend', 0);

        return $this;
    }

    /*
        @param str   $type
            byUname - user with specific uname
            byId    - user with specific id
            following - users whom you are following
            followers - your followers (they follow you)
            members   - group members
            youPM     - list of users whom you PMed
            toYouPM   - list of users who send something to you

        @param array $params
            #uname# | #id# | #gid#

        @param int   $options
            U_ONLY_ID   - selects only id's
            U_BY_UNAME  - search by LIKE #uname#
            U_LAST_CHAT - adds last message to result
            U_PENDING   - members whom requested auth
            U_ADMINS    - members with status >= moderator

        @return UsersList
    */
    public static function search($type, array $params, $options = 0) {
        $db = DB::getInstance();

        $joins = array(
            'friends'     => 'INNER JOIN friends f ON f.user_id = u.id',
            'friends2'    => 'INNER JOIN friends f ON u.id = f.user_id',
            'last_chat'   => 'LEFT JOIN 
                                (SELECT m.sender_id AS uid, text FROM message ORDER BY id DESC)
                                AS lc ON lc.uid = u.id',
            'members'     => 'INNER JOIN group_members gm ON gm.user_id = u.id',
            'messageFrom' => 'INNER JOIN message m ON u.id = m.sender_id',
            'messageTo'   => 'INNER JOIN message m ON u.id = m.reciever_id');

        if ($options & U_ONLY_ID)
            $fields = 'u.id';
        else
            $fields = FuncLib::addPrefix('u.', User::$fields);

        $join = $where = $group = array();

        switch ($type) {
            case 'byUname':
                $where[] = 'u.uname = #uname#';
                break;

            case 'byId':
                $where[] = 'u.id = #id#';
                break;

            case 'following':
                $join[]  = 'friends';
                $where[] = 'u.id = #id#';
                break;

            case 'followers':
                $join[]  = 'friends2';
                $where[] = 'f.friend_id = #id#';
                break;

            case 'members':
                $join[]  = 'members';
                $where[] = 'gm.group_id = #gid#';
                break;

            case 'youPM':
                $join[]  = 'messageTo';
                $where[] = 'm.reciever_id IS NOT NULL';
                $where[] = 'm.sender_id = #id#';
                break;

            case 'toYouPM':
                $join[]  = 'messageFrom';
                $where[] = 'm.reciever_id = #id#';
                $where[] = 'm.sender_id IS NOT NULL';
        }

        if ($options & U_BY_UNAME)
            $where[] = 'u.uname LIKE #uname#';

        if ($options & U_LAST_CHAT) {
            $group[]  = 'u.id';
            $join[]   = 'last_chat';
            $fields[] = 'lc.text AS last_chat';
        }

        if ($options & U_PENDING) 
            $where[]  = 'gm.permission = '.Group::$permissions['pending'];

        if ($options & U_ADMINS)
            $where[]  = 'gm.permission >= '.Group::$permissions['moderator'];

        $join  = implode("\n", array_intersect_key($joins, array_flip(array_unique($join))));
        $where = implode(' AND ', array_unique($where));
        $group = array_unique($group);
        if (count($group) > 1)
            throw new LogicException("Don't know how to multiple group");
        else
            $group = !empty($group) ? 'GROUP BY '.$group[0] : '';

        $query = "
            SELECT {$fields}
              FROM user u
              {$join}
             WHERE {$where}
              {$group}";

        $users = array();
        $result = $db->query($query, $params);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $users[ intval($res['id']) ] = new User($res);

        $users = new UsersList($users);
        return $users;
    }

    /*
        Returns a list of user you are following

        @return UsersList
    */
    public static function getFollowing(User $user, $chat = false) {
        return UsersList::search('following', array('id' => $user->id), ($chat ? U_LAST_CHAT : 0));
    }

    /*
        Returns a list of users following you
        
        @return UsersList
    */
    public static function getFollowers(User $user, $chat = false) {
        return UsersList::search('followers', array('id' => $user->id), ($chat ? U_LAST_CHAT : 0));
    }

    /*
        Filter your following list by username
    */
    public static function friendsByUname(User $user, $uname, $chat = false) {
        $uname = "%$uname%";
        return UsersList::search('following', array('id' => $user->id, 'uname' => $uname), ($chat ? U_LAST_CHAT : 0) | U_BY_UNAME);
    }

    /*
        Creates a list of users from uid's
 
         foreach ($this->data as $user) {
            if (array_key_exists($user->uid, $stats))
                $user->setStats($stats[$user->uid]);
            else

        @return UsersList
    */
    public static function fromUids(array $uids) {
        $users = array();
        foreach($uids as $i)
            $users[$i] = new User($i);

        return new UsersList($users);
    }
};
