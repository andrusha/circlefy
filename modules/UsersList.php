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
            byUnames- returns a list of users with matching unames
            byId    - user with specific id
            byIds   - fetch a list of users with specified ids
            following - users whom you are following
            followers - your followers (they follow you)
            members   - group members
            youPM     - list of users whom you PMed
            toYouPM   - list of users who send something to you
            limit     - users to fetch count
            convo     - search users involved in conversation

        @param array $params
            uname  - username
            unames - usernames
            id     - user id
            ids    - array of user ids
            gid    - group id
            mid    - message id
            active - conversation status

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
            'friends'     => 'INNER JOIN friends       f ON u.id = f.friend_id',
            'friends2'    => 'INNER JOIN friends       f ON u.id = f.user_id',
            'last_chat'   => 'LEFT JOIN 
                                (SELECT m.sender_id AS uid, text FROM message ORDER BY id DESC)
                                AS lc ON lc.uid = u.id',
            'members'     => 'INNER JOIN group_members gm ON gm.user_id = u.id',
            'messageFrom' => 'INNER JOIN message       m ON u.id = m.sender_id',
            'messageTo'   => 'INNER JOIN message       m ON u.id = m.reciever_id',
            'convo'       => 'INNER JOIN conversations c ON u.id = c.user_id');

        if ($options & U_ONLY_ID)
            $fields = array('u.id');
        else
            $fields = FuncLib::addPrefix('u.', User::$fields);

        $join  = $where = $group = array();
        $limit = '';

        switch ($type) {
            case 'byUname':
                $where[] = 'u.uname = #uname#';
                break;

            case 'byUnames':
                $where[] = 'u.uname IN (#unames#)';
                break;

            case 'byId':
                $where[] = 'u.id = #id#';
                break;

            case 'byIds':
                $where[] = 'u.id IN (#ids#)';
                break;

            case 'following':
                $join[]  = 'friends';
                $where[] = 'f.user_id = #id#';
                break;

            case 'followers':
                $join[]  = 'friends2';
                $where[] = 'f.friend_id = #id#';
                break;

            case 'members':
                $join[]   = 'members';
                $where[]  = 'gm.group_id = #gid#';
                $fields[] = 'gm.permission';
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
                break;

            case 'convo':
                $join[]  = 'convo';
                $where[] = 'c.message_id = #mid#';
                $where[] = 'c.active = #active#';
                break;

            case 'like':
                $where[] = "(u.uname LIKE #search# OR CONCAT(u.fname, ' ', u.lname) LIKE #search#)";
                break;
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

        if (!($options & U_PENDING) && !($options & U_ADMINS) && $type == 'members')
            $where[] = 'gm.permission > '.Group::$permissions['pending'];

        if (isset($params['limit']))
            $limit    = 'LIMIT 0, #limit#';

        $join   = implode("\n", array_intersect_key($joins, array_flip(array_unique($join))));
        $where  = implode(' AND ', array_unique($where));
        $fields = implode(', ', array_unique($fields));
        $group  = array_unique($group);
        if (count($group) > 1)
            throw new LogicException("Don't know how to multiple group");
        else
            $group = !empty($group) ? 'GROUP BY '.$group[0] : '';

        $query = "
            SELECT {$fields}
              FROM user u
              {$join}
             WHERE {$where}
              {$group}
              {$limit}";

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
        Return ids of existing users from list

        @return array
    */
    public static function idExists(array $ids, $field = 'id') {
        $res = DB::getInstance()
              ->query("SELECT id FROM user WHERE {$field} IN (#ids#)",
                array('ids' => $ids));
        $ids = array();

        if ($res->num_rows)
            while($row = $res->fetch_assoc())
                $ids[] = $row['id'];

        return $ids;
    }

    /*
        Creates a list of users from uid's, checking if friend exists or not
 
        @return UsersList
    */
    public static function fromIds(array $uids, $facebook = false) {
        $field = !$facebook ? 'id' : 'fb_id';
        return new UsersList(
            array_map(
                function ($x) { return new User($x); },
                self::idExists($uids, $field)));
    }
};
