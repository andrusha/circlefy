<?php

class Events {
    /*
        Fetch latest events for last day or events by user

        @param User|null $user
        @param int $page

        @return array (UsersList, TapsList)
    */
    private static function search(User $user = null, $page = null) {
        $db = DB::getInstance();

        $byUser = $user instanceof User;
        $params = array();
        
        if ($byUser) {
            $where1 = $where2 = ' AND e.user_id = #uid#';
            $params = array('uid' => $user->id);
        } else {
            $where1 = 'WHERE m.modification_time > DATE_SUB(NOW(), INTERVAL 1 DAY)';
            $where2 = ' AND f.time > DATE_SUB(NOW(), INTERVAL 1 DAY)';
        }
        
        if ($page !== null)
            $limit = 'LIMIT '.($page*5).', 5';
        else
            $limit = '';

        $query = "
            (SELECT e.type, e.user_id AS event_reciever, m.id, m.anonymous, m.sender_id, m.text, m.time, m.group_id, m.reciever_id, m.media_id, m.modification_time, m.private, e.new_replies, u.uname, u.fname, u.lname, g2.symbol, g2.name
               FROM message m 
               LEFT JOIN `group` g2 ON g2.id = m.group_id 
              INNER JOIN user u ON u.id = m.sender_id
              INNER JOIN events e ON m.id = e.related_id AND e.type IN (0, 1) {$where1})
            UNION ALL
            (SELECT e.type, e.user_id AS event_reciever, null, null AS anonymous, u.id AS sender_id, '', null, null, null, null, f.time AS modifiction_time, null, null, u.uname, u.fname, u.lname, null, null
               FROM events e
              INNER JOIN friends f ON f.friend_id = e.user_id AND f.user_id = e.related_id
              INNER JOIN user u ON u.id = f.user_id
              WHERE e.type = 2 {$where2})
            ORDER BY modification_time DESC
            {$limit}";

        $events = array();
        $uids = array();
        $res = $db->query($query, $params);
        
        if ($res->num_rows)
            while ($line = $res->fetch_assoc()) {
                foreach (array('anonymous', 'group_id', 'user_id', 'media_id', 'type', 'id', 'private') as $key)
                    if ($line[$key])
                        $line[$key] = intval($line[$key]);

                $group = array_intersect_key($line, array_flip(array('group_id', 'name', 'symbol')));
                $gorup['id'] = $line['group_id'];

                $sender = array_intersect_key($line, array_flip(array('user_id', 'uname', 'fname', 'lname')));
                $sender['id'] = $line['user_id'];

                $line['group']  = new Group($group);
                $line['sender'] = new User($sender);

                $uid = intval($line['event_reciever']);
                $uids[] = $uid;
                $events[$uid][ intval($line['id']) ] = new Tap($line);
            }

        if (empty($events))
            return array(UsersList::makeEmpty(), array(TapsList::makeEmpty()));

        foreach ($events as $uid => $val)
            $events[$uid] = new TapsList($val);

        if (!$byUser)
            $users = UsersList::search('byIds', array('ids' => $uids));
        else
            $users = UsersList::makeEmpty();

        return array($users, $events);
    }

    public static function exists(User $u, $type, $id) {
        $query = 'SELECT user_id FROM events 
                   WHERE user_id = #uid# AND type = #type# AND related_id = #id# 
                   LIMIT 1';
        return DB::getInstance()->query($query, 
                array('uid' => $u->id, 'type' => $type, 'id' => $id))->num_rows == 1;
    }

    /*
        @return TapsList
    */
    public static function forUser(User $u, $page = 0) {
        list($users, $events) = self::search($u, $page);
        return current($events);
    }

    public static function forLastDay() {
        return self::search();
    }

    public static function readUserEvent(User $who, User $whom) {
        Comet::send('event.delete', array('type' => 2, 'event_id' => $whom->id, 'user_id' => $who->id));
    }

    public static function readMessageEvent(User $who, Tap $tap) {
        Comet::send('event.delete', array('type' => 1, 'event_id' => $tap->id, 'user_id' => $who->id));
    }

    public static function readAllEvents(User $u) {
        Comet::send('event.delete.all', array('user_id' => $u->id));
    }

};
