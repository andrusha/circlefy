<?php

/*
    All things, related to taps list
*/
class TapsList extends Collection {

    /*
        @param str $type
        'aggr_groups'   | 'ind_group'     | 'public' 
        'personal'      | 'aggr_personal' | 'private'
        'aggr_private'  | 'aggr_all'      | 'convos_all'
        'active'

        @param array $params array of params related to that filter
        array(uid        => user id
              gid        => group id
              search     => if we searching something
              start_from => start from in LIMIT
              from       => user who sent PM
              to         => user who recieve PM
              id         => tap id

        @param int $options
            T_LIMIT      - specify limit start from
            T_SEARCH     - search by msg text
            T_GROUP_INFO - fetch all group info
            T_USER_INFO  - fetch only sender info
            T_USER_RECV  - fetch reciever info

        @return TapsList
    */
    private static function search($type, array $params, $options = 0) {
        $db = DB::getInstance();

        $joins = array(
            'members'   => 'INNER JOIN group_members gm ON m.group_id  = gm.group_id',
            'members_l' => 'LEFT  JOIN group_members gm ON m.group_id  = gm.group_id',
            'group'     => 'INNER JOIN `group`       g  ON g.id        = m.group_id',
            'group_l'   => 'LEFT  JOIN `group`       g2 ON g2.id       = m.group_id',
            'user'      => 'INNER JOIN user          u  ON u.id        = m.sender_id',
            'user_l'    => 'LEFT  JOIN user          u2 ON u2.id       = m.reciever_id',
            'friends'   => 'INNER JOIN friends       f  ON m.sender_id = f.friend_id',
            'convo'     => 'INNER JOIN conversations c  ON m.id        = c.message_id',
            'convo_l'   => 'LEFT  JOIN conversations c  ON m.id        = c.message_id'
        );

        $distinct = false;
        $join = $where = array();
        $fields = FuncLib::addPrefix('m.', Tap::$fields);
        
        switch ($type) {
            case 'aggr_groups':
                $join[]  = 'members';
                $where[] = 'gm.user_id = #uid#';
                break;

            case 'ind_group':
                $where[] = 'm.group_id = #gid#';
                break;

            case 'public':
                $join[]  = 'group';
                $where[] = 'm.group_id IS NOT NULL';
                $where[] = 'g.secret = 0';
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

            case 'convos_all':
                $join[]  = 'convo';
                $where[] = 'c.user_id = #uid#';
                break;

            case 'active':
                $join[]  = 'convo';
                $where[] = 'c.user_id = #uid#';
                $where[] = 'c.active  = 1';
                break;

            case 'aggr_all':
                $distinct = true;
                $join[]   = 'members_l';
                $join[]   = 'convo_l';
                $where[]  = '(gm.user_id = #uid# OR c.user_id = #uid# '.
                            '  OR ((m.sender_id = #uid# OR m.reciever_id = #uid#) AND '.
                            '       m.reciever_id IS NOT NULL))';
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
        foreach (DB::getSeparator($result, array('g', 'g2', 'u', 'u2')) as $line) {
            $tap = $line['rest'];

            if (!empty($line['g']) || !empty($line['g2']))
                $tap['group'] = new Group($line['g'] ?: $line['g2']);

            if (!empty($line['u']))
                $tap['sender'] = new User($line['u']);

            if (!empty($tap['u2']))
                $tap['reciever'] = new User($line['u2']);

            $taps[ intval($tap['id']) ] = new Tap($tap);
        }
        
        return new TapsList($taps);
    }

    /*
        Returns last responses & overall responses count
        
        @return TapsList
    */
    public function lastResponses() {
        if (empty($this->data))
            return $this;

        $db = DB::getInstance();
        $tap_ids = $this->filter('id');
        
        $fields   = FuncLib::addPrefix('r.', Tap::$replyFields);
        $fields   = array_merge($fields, FuncLib::addPrefix('u.', User::$fields));
        $fields[] = 'r1.count';
        $fields   = implode(', ', array_unique($fields));

        $query = "
            SELECT {$fields}
              FROM (
                    SELECT MAX(id) AS id, COUNT(id) AS count
                      FROM reply
                     WHERE message_id IN (#ids#)
                     GROUP
                        BY id
                   ) AS r1
             INNER
              JOIN reply r
                ON r.id = r1.id
             INNER
              JOIN user u
                ON u.id = r.user_id";

        $responses = array();
        $result = $db->query($query, array('ids' => $tap_ids));
        foreach (DB::getSeparator($result, array('u')) as $line) {
            $resp = $line['rest'];
            $resp['user'] = new User($line['u']);

            $responses[ intval($resp['message_id']) ] = $resp;
        }

        $this->joinDataById($responses, 'responses', array());

        return $this;
    }
};
