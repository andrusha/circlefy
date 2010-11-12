<?php

/*
    All things, related to taps list
*/
class TapsList extends Collection {

    /*
        @param str $type
            public        - public feed
            feed          - user feed with groups, convos & PMs
            aggr_groups   - taps from groups joined by user
            group         - from individual group
            aggr_friends  - aggregate feed of your friends
            friend        - feed of one user
            aggr_private  - personal messages feed
            private       - PMs with specific user
            aggr_convos   - aggregate list of conversations
            active_convos - only active conversations
            byId          - fetch one tap by ID

        @param array $params array of params related to that filter
            uid        - user id
            gid        - group id
            search     - if we searching something
            start_from - start from in LIMIT
            row_count  - rows count in LIMIT (default 10)
            from       - user who sent PM
            to         - user who recieve PM
            id         - tap id

        @param int $options
            T_LIMIT      - specify limit start from
            T_SEARCH     - search by msg text
            T_GROUP_INFO - fetch all group info
            T_USER_INFO  - fetch only sender info
            T_USER_RECV  - fetch reciever info
            T_MEDIA      - fetch media if avaliable
            T_INSIDE     - taps only from group members
            T_OUTSIDE    - taps only from not members of the group
            T_NEW_REPLIES- return unread replies count
            T_ANON       - returns only anonymous messages

        @return TapsList
    */
    public static function search($type, array $params = array(), $options = 0) {
        $db = DB::getInstance();

        // `m` index reserverd for message table
        $joins = array(
            'members'   => 'INNER JOIN group_members gm ON m.group_id  = gm.group_id',
            'members_l' => 'LEFT  JOIN group_members gm ON m.group_id  = gm.group_id',
            'group'     => 'INNER JOIN `group`       g  ON g.id        = m.group_id',
            'group_l'   => 'LEFT  JOIN `group`       g2 ON g2.id       = m.group_id',
            'user'      => 'INNER JOIN user          u  ON u.id        = m.sender_id',
            'user_l'    => 'LEFT  JOIN user          u2 ON u2.id       = m.reciever_id',
            'friends'   => 'INNER JOIN friends       f  ON m.sender_id = f.friend_id',
            'convo'     => 'INNER JOIN conversations c  ON m.id        = c.message_id',
            'convo_l'   => 'LEFT  JOIN conversations c  ON m.id        = c.message_id',
            'media'     => 'LEFT  JOIN media         md ON md.id       = m.media_id',
            'events_l'  => 'LEFT  JOIN events        e  ON m.id        = e.related_id AND e.user_id = #uid# AND e.type IN (0, 1)'
        );

        $distinct = false;
        $join = $where = array();
        $fields = FuncLib::addPrefix('m.', Tap::$fields);

        if ($options & T_INSIDE && $options & T_OUTSIDE)
            throw new LogicException('If you want to get inside & outside in the same time, just specify nothing');

        if ($type == 'public' && $options & T_INSIDE)
            throw new LogicException('You cant have public feed with private messages');

        switch ($type) {
            case 'public':
                $join[]  = 'group';
                $where[] = 'm.group_id IS NOT NULL';
                $where[] = 'g.secret = 0';
                $where[] = 'm.private = 0';
                break;

            case 'feed':
                $distinct = true;
                $join[]   = 'members_l';
                $join[]   = 'convo_l';
                $where[]  = "(gm.user_id = #uid# OR c.user_id = #uid# ".
                            '  OR ((m.sender_id = #uid# OR m.reciever_id = #uid#) AND '.
                            '       m.reciever_id IS NOT NULL))';
                break;

            case 'aggr_groups':
                $join[]  = 'members';
                $where[] = 'gm.user_id = #uid#';
                break;

            case 'group':
                $join[]  = 'members';
                $where[] = 'm.group_id = #gid#';
                $fields[] = 'gm.context';
                break;

            case 'aggr_friends':
                $join[]  = 'friends';
                $where[] = 'f.user_id = #uid#';
                $where[] = 'm.group_id IS NOT NULL';
                break;

            case 'friend':
                $where[] = 'm.sender_id = #uid#';
                $where[] = 'm.group_id IS NOT NULL';
                break;

            case 'aggr_private':
                $where[] = '((m.sender_id   = #uid# AND m.reciever_id IS NOT NULL) OR '.
                           '  (m.reciever_id = #uid#))';
                break; 

            case 'private':
                $where[] = '((m.sender_id = #from# AND m.reciever_id = #to#) OR '.
                           '  (m.sender_id = #to#   AND m.reciever_id = #from#))';
                break;

            case 'aggr_convos':
                $join[]  = 'convo';
                $where[] = 'c.user_id = #uid#';
                break;

            case 'active_convos':
                $join[]  = 'convo';
                $where[] = 'c.user_id = #uid#';
                $where[] = 'c.active  = 1';
                break;

            case 'byId':
                $where[] = 'm.id = #id#';
                break;
        }

        if ($options & T_SEARCH)
            $where[] = "m.text LIKE #search#";


        if ($options & T_USER_INFO) {
            $join[] = 'user';
            $fields = array_merge($fields, FuncLib::addPrefix('u.', User::$fields));
        }
        
        if ($options & T_USER_RECV) {
            $join[] = 'user_l';
            $fields = array_merge($fields, FuncLib::addPrefix('u2.', User::$fields));
        }

        if ($options & T_GROUP_INFO) {
            if (!in_array('group', $join)) {
                $join[] = 'group_l';
                $prefix = 'g2.'; 
            }
            $fields = array_merge($fields, FuncLib::addPrefix($prefix ?: 'g.', Group::$fields));
        }

        if ($options & T_MEDIA) {
            $join[] = 'media';
            $prefix = 'md.';
            $fields = array_merge($fields, FuncLib::addPrefix($prefix ?: 'md.', Media::$fields));
        }

        if ($options & T_NEW_REPLIES) {
            if (!in_array('events', $join))
                $join[] = 'events_l';
            $fields[] = 'e.new_replies';
        }

        if ($options & T_INSIDE)
            $where[] = 'm.private = 1';
        else if ($options & T_OUTSIDE)
            $where[] = 'm.private = 0';

        if ($options & T_ANON)
            $where[] = 'm.anonymous = 1';
    
        if (!isset($params['row_count']))
            $params['row_count'] = DEFAULT_ROW_COUNT;

        $limit = ($options & T_LIMIT ? $params['start_from'].', ' : '').$params['row_count'];

        //construct and execute query from supplied params
        $join   = implode("\n", array_intersect_key($joins, array_flip(array_unique($join))));
        $where  = implode(' AND ', array_unique($where));
        if ($where)
            $where = 'WHERE '.$where;
        $fields = implode(', ', array_unique($fields));

        $distinct = $distinct ? 'DISTINCT' : '';
        $query = "
            SELECT {$distinct} {$fields} 
              FROM message m
            {$join}
            {$where}
             ORDER
                BY m.modification_time DESC
             LIMIT {$limit}";
        
        $taps = array();
        $result = $db->query($query, $params);
        //separate group/users info from all stuff
        foreach (DB::getSeparator($result, array('g', 'g2', 'u', 'u2', 'md'), md5($query)) as $line) {
            $tap = $line['rest'];

            if (!empty($line['g']) || !empty($line['g2']))
                $tap['group'] = new Group($line['g'] ?: $line['g2']);

            if (!empty($line['u'])) {
                $tap['sender'] = new User($line['u']);
                // TODO: a better way to add context into sender info object
                $tap['sender_context'] = $line['rest']['context'];
            }

            if (!empty($line['u2']))
                $tap['reciever'] = new User($line['u2']);

            if (!empty($line['md']))
                $tap['media'] = new Media($line['md']);

            $taps[ intval($tap['id']) ] = new Tap($tap);
        }

        return new TapsList($taps);
    }

    /*
        Attaches replies/last reply for each tap

        @param  bool $last    - attach only last reply
        @param  bool $asArray - attach them as arrays (+User class instead)

        @return TapsList
    */
    private function getReplies($last = false, $asArray = true) {
        if (empty($this->data))
            return $this;

        $db = DB::getInstance();
        $tap_ids = $this->filter('id');
        
        $fields   = FuncLib::addPrefix('r.', Tap::$replyFields);
        $fields   = array_merge($fields, FuncLib::addPrefix('u.', User::$fields));
        if ($last)
            $fields[] = 'r1.count';
        $fields   = implode(', ', array_unique($fields));

        if ($last) { 
            $from = '(
                    SELECT MAX(id) AS id, COUNT(id) AS count
                      FROM reply
                     WHERE message_id IN (#ids#)
                     GROUP
                        BY message_id
                   ) AS r1
             INNER
              JOIN reply r
                ON r.id = r1.id';
            $where = '';
        } else {
            $from  = 'reply r';
            $where = 'WHERE r.message_id IN (#ids#)';
        }

        $query = "
            SELECT {$fields}
              FROM {$from}
             INNER
              JOIN user u
                ON u.id = r.user_id
            {$where}
             ORDER BY r.id ASC";

        $replies = array();
        $result = $db->query($query, array('ids' => $tap_ids));
        foreach (DB::getSeparator($result, array('u'), md5($query)) as $line) {
            $repl = $line['rest'];
            $repl['user'] = $asArray ? $line['u'] : new User($line['u']);
            
            if ($last)
                $replies[ intval($repl['message_id']) ] = $repl;
            else    
                $replies[ intval($repl['message_id']) ][ intval($repl['id']) ] = $repl;
        }

        $this->joinDataById($replies, $last ? 'responses' : 'replies', array());

        return $this;
    }

    /*
        Attaches last responses & overall responses count
        
        @return TapsList
    */
    public function lastResponses($asArray = true) {
        return $this->getReplies(true, $asArray);
    }

    /*
        Attaches replies for each tap

        @return TapsList
    */
    public function replies($asArray = true) {
        return $this->getReplies(false, $asArray);
    }

    /*
        Makes a list of unique users involved in each conversation

        @return TapsList
    */
    public function involved() {
        foreach ($this->data as &$tap) {
            if (!isset($tap['replies']))
                throw new LogicException('You should fetch replies first');

            $involved = array();
            foreach ($tap->replies as &$r)
                if (is_array($r['user']))
                    $involved[ $r['user']['id'] ] = $r['user'];
                elseif ($r['user'] instanceof User)
                    $involved[ $r['user']->id ] = $r['user'];
                else
                    throw new LogicException('Replies data is corrupted');

            $tap->involved = $involved;
        }
        
        return $this;
    }

    /*
        Formats time since & tap text 
    */
    public function format() {
        foreach ($this->data as $tap)
            $tap->format();
        return $this;
    }

    public static function deleteAllEvents(User $u) {
        $db = DB::getInstance();

        $query = "DELETE FROM events WHERE user_id = #uid#";
        $db->query($query, array('uid' => $u->id));
    }

    public static function fetchEvents(User $u, $page = 0) {
        $db = DB::getInstance();

        $limit = 'LIMIT '.($page*5).', 5';
        $query = "
            (SELECT e.type, m.id, m.anonymous, m.sender_id, m.text, m.time, m.group_id, m.reciever_id, m.media_id, m.modification_time, m.private, e.new_replies, u.id, u.uname, u.fname, u.lname, g2.id, g2.symbol, g2.name
               FROM message m 
               LEFT JOIN `group` g2 ON g2.id = m.group_id 
              INNER JOIN user u ON u.id = m.sender_id
              INNER JOIN events e ON m.id = e.related_id AND e.type IN (0, 1) AND e.user_id = #uid#)
            UNION ALL
            (SELECT e.type, null, null, u.id AS sender_id, null, null, null, null, null, f.time AS modifiction_time, null, null, u.id, u.uname, u.fname, u.lname, null, null, null
               FROM events e
              INNER JOIN friends f ON f.friend_id = e.user_id AND f.user_id = e.related_id
              INNER JOIN user u ON u.id = f.user_id
              WHERE e.type = 2 AND e.user_id = #uid#)
            ORDER BY modification_time DESC
            {$limit}";
        $events = array();
        $res = $db->query($query, array('uid' => $u->id));

        // !!! this shit doesn't work with unions anyway, so there is a _plain_ column array
        foreach (DB::getSeparator($res, array('g2', 'u'), md5($query)) as $line) {
            $tap = $line['rest'];
            $line['g2'] = array_intersect_key($line['rest'], array_flip(array('group_id', 'name', 'symbol')));
            $line['g2']['id'] = $line['g2']['group_id'];
            $line['u'] = array_intersect_key($line['rest'], array_flip(array('user_id', 'uname', 'fname', 'lname')));
            $line['u']['id'] = $line['u']['user_id'];

            $tap['group'] = new Group($line['g2']);
            $tap['sender'] = new User($line['u']);
            $events[ intval($tap['id']) ] = new Tap($tap);
        }

        return new TapsList($events);
    } 
};
