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
            'media'     => 'LEFT  JOIN media         md ON md.id       = m.media_id'
        );

        $distinct = false;
        $join = $where = array();
        $fields = FuncLib::addPrefix('m.', Tap::$fields);
        
        switch ($type) {
            case 'public':
                $join[]  = 'group';
                $where[] = 'm.group_id IS NOT NULL';
                $where[] = 'g.secret = 0';
                break;

            case 'feed':
                $distinct = true;
                $join[]   = 'members_l';
                $join[]   = 'convo_l';
                $where[]  = '(gm.user_id = #uid# OR c.user_id = #uid# '.
                            '  OR ((m.sender_id = #uid# OR m.reciever_id = #uid#) AND '.
                            '       m.reciever_id IS NOT NULL))';
                break;

            case 'aggr_groups':
                $join[]  = 'members';
                $where[] = 'gm.user_id = #uid#';
                break;

            case 'group':
                $where[] = 'm.group_id = #gid#';
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
                $where[]  = 'm.id = #id#';
                break;
        }

        if ($options & T_SEARCH)
            $where[] = "m.text LIKE #search#";

        $limit = ($options & T_LIMIT) ? $params['start_from'].', 10' : '0, 10';

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
            $fields = array_merge($fields, FuncLib::addPrefix($prefix ?: 'md.', Tap::$mediaFields));
        }

        //construct and execute query from supplied params
        $join   = implode("\n", array_intersect_key($joins, array_flip(array_unique($join))));
        $where  = implode(' AND ', array_unique($where));
        $fields = implode(', ', array_unique($fields));

        $distinct = $distinct ? 'DISTINCT' : '';
        $query = "
            SELECT {$distinct} {$fields} 
              FROM message m
            {$join}
            WHERE {$where}
             ORDER
                BY m.id DESC
             LIMIT {$limit}";
        
        $taps = array();
        $result = $db->query($query, $params);
        //separate group/users info from all stuff
        foreach (DB::getSeparator($result, array('g', 'g2', 'u', 'u2', 'md')) as $line) {
            $tap = $line['rest'];

            if (!empty($line['g']) || !empty($line['g2']))
                $tap['group'] = new Group($line['g'] ?: $line['g2']);

            if (!empty($line['u']))
                $tap['sender'] = new User($line['u']);

            if (!empty($tap['u2']))
                $tap['reciever'] = new User($line['u2']);

            if (!empty($tap['md'])) {
                $line['md']['type'] = intval($line['md']['type']);
                $tap['media'] = $line['md'];
            }

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

        $from = $where = '';
        if ($last) 
            $from = '(
                    SELECT MAX(id) AS id, COUNT(id) AS count
                      FROM reply
                     WHERE message_id IN (#ids#)
                     GROUP
                        BY id
                   ) AS r1
             INNER
              JOIN reply r
                ON r.id = r1.id';
        else {
            $from  = 'reply r';
            $where = 'WHERE r.message_id IN (#ids#)';
        }

        $query = "
            SELECT {$fields}
              FROM {$from}
             INNER
              JOIN user u
                ON u.id = r.user_id
            {$where}";

        $replies = array();
        $result = $db->query($query, array('ids' => $tap_ids));
        foreach (DB::getSeparator($result, array('u')) as $line) {
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
        Formats time since & tap text 
    */
    public function format() {
        foreach ($this->data as &$tap) {
            $tap->time = FuncLib::timeSince(strtotime($tap->time));
            $tap->text = FuncLib::linkify($tap->text);

            //TODO: that's kinda ugly, should think about way
            // to make it _safe_ and usable
            if (isset($tap['responses'])) {
                $responses = $tap->responses;
                $responses['text'] = FuncLib::makePreview($responses['text'], 40);
                $tap->responses = $responses;
            }
            
            if (isset($tap['replies'])) {
                $replies = $tap->replies;
                foreach ($replies as &$r) {
                    $r['text'] = FuncLib::linkify($r['text']);
                    $r['time'] = FuncLib::timeSince(strtotime($r['time']));
                }
                $tap->replies = $replies;
            }

        }
        return $this;
    }
};
