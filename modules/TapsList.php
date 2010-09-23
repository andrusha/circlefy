<?php

/*
    All things, related to taps list
*/
class TapsList extends Collection {
    protected function __construct(array $taps) {
        parent::__construct($taps, 'TapsList');
    }

    /*
        Returns list of tap_id's (mids) that meets
        filter requirements, we can actually combine
        filters as we want, but it not implemented by now
        
        $filter selected filter type for query
        'aggr_groups'   | 'ind_group'     | 'public' 
        'personal'      | 'aggr_personal' | 'private'
        'aggr_private'  | 'aggr_all'      | 'convos_all'
        'active'

        $params array of params related to that filter
        array('#uid#'        => user id
              '#outside#'    => what type of groups to show
              '#gid#'        => group id
              '#search#'     => if we searching something
              '#start_from#' => start from in LIMIT
              '#anon#'       => filter registred/anonymous users
              '#from#'       => user who sent PM
              '#to#'         => user who recieve PM
    */
    private static function filterTapIds($filter, $params) {
        if (!function_exists('substitute')) {
            function substitute(&$item, $key, array $params) {
                $item = strtr($item, $params);
            }
        }

        $db = DB::getInstance()->Start_Connection('mysql');

        $distinct = false;
        $joins = array(
            'meta'         => 'INNER JOIN special_chat_meta scm ON scm.mid = sc.mid',
            'members'      => 'JOIN group_members gm ON gm.gid = scm.gid',
            'members_left' => 'LEFT JOIN group_members gm ON gm.gid = scm.gid',
            'logins'       => 'INNER JOIN login l ON l.uid = sc.uid',
            'friends'      => 'JOIN friends f ON sc.uid = f.fuid',
            'convo'        => 'JOIN active_convo ac ON sc.mid = ac.mid',
            'convo_left'   => 'LEFT JOIN active_convo ac ON sc.mid = ac.mid'
        );

        $toJoin = $where = array();
        switch ($filter) {
            case 'aggr_groups':
                $toJoin[] = 'meta';
                $toJoin[] = 'members';
                $where[]  = 'gm.uid = #uid#';
                break;

            case 'ind_group':
                $toJoin[] = 'meta';
                $where[]  = 'scm.gid = #gid#';
                break;

            case 'public':
                $toJoin[] = 'meta';
                $where[]  = 'scm.private = 0';
                break;

            case 'aggr_personal':
                $toJoin[] = 'friends';
                $toJoin[] = 'meta';
                $where[]  = 'f.uid = #uid#';
                $where[]  = 'scm.private = 0';
                break;

            case 'personal':
                $toJoin[] = 'meta';
                $where[]  = 'sc.uid = #uid#';
                $where[]  = 'scm.private = 0';
                break;

            case 'aggr_private':
                $toJoin[] = 'meta';
                $where[]  = '(sc.uid = #uid# OR scm.uid = #uid#) AND scm.private = 1';
                break; 

            case 'private':
                $toJoin[] = 'meta';
                $where[]  = '(
                                 (sc.uid = #from# AND scm.uid = #to#)
                                     OR
                                 (sc.uid = #to# AND scm.uid = #from#)
                             ) AND scm.private = 1';
                break;

            case 'aggr_all':
                $distinct = true;
                $toJoin[] = 'meta';
                $toJoin[] = 'members_left';
                $toJoin[] = 'convo_left';
                $where[]  = '(gm.uid = #uid# OR ac.uid = #uid#
                              OR ((sc.uid = #uid# OR scm.uid = #uid#) AND scm.private = 1))';
                break;

            case 'convos_all':
                $toJoin[] = 'convo';
                $where[]  = 'ac.uid = #uid#';
                break;

            case 'active':
                $toJoin[] = 'convo';
                $where[]  = 'ac.uid = #uid#';
                $where[]  = 'ac.active = 1';
                break;
        }

        if ($params['#search#'])
            $where[] = "sc.chat_text LIKE '%#search#%'";

        if (isset($params['#anon#'])) {
            $toJoin[] = 'logins';
            $where[] = 'l.anon = #anon#';
        }

        if ($params['#outside#']) {
            $toJoin[] = 'meta';
            $where[] = 'scm.connected IN (#outside#)';
        }

        $limit = '0, 10';
        if ($params['#start_from#'])
            $limit = $params['#start_from#'].', 10';

        //get all unique joins from $joins array
        $toJoin = array_intersect_key($joins,
            array_flip(array_unique($toJoin)));

        //replace all variables with real values
        $where = array_unique($where);
        array_walk($where, 'substitute', $params);

        $toJoin = ' '.implode(' ', $toJoin).' ';
        if ($where)
            $where = ' WHERE '.implode(' AND ', $where).' ';
        else
            $where = '';

        $distinct = $distinct ? 'DISTINCT' : '';
        $query = "
            SELECT {$distinct} sc.mid
              FROM special_chat sc
            {$toJoin}
            {$where}
             ORDER
                BY sc.mid DESC
             LIMIT {$limit}";
        
        $mids = array();
        $result = $db->query($query, array());
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $mids[] = intval($res['mid']);
        
        return $mids;
    }

    /*
        Returns tap with little formatting

        $tap_id int|array

        @return TapsList
    */
    public static function getTaps(array $tap_ids, $group_info = true, $user_info = false) {
        $join = $fields = '';

        $db = DB::getInstance()->Start_Connection('mysql');

        if ($group_info) {
            $join = '
              LEFT 
              JOIN groups g
                ON g.gid = scm.gid';
            $fields = 'g.gname, g.symbol, g.favicon,';
        }

        if ($user_info) {
            $join .= '
                LEFT
                JOIN login l2
                  ON l2.uid = scm.uid';
            $fields .= 'l2.uid AS to_uid, l2.uname AS to_uname,
                GET_REAL_NAME(l2.fname, l2.lname, l2.uname) AS to_real_name,
                l2.pic_100 AS to_pic_100, ';
        }

        //default responses info (count, last_resp, resp_uname) set to null
        $query = "
            SELECT sc.mid AS cid, sc.chat_text, UNIX_TIMESTAMP(sc.chat_timestamp) AS chat_timestamp_raw,
                   UNIX_TIMESTAMP(sc.chat_timestamp) AS chat_timestamp, sc.uid, l.uname,
                   GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name, l.pic_100,
                   tmo.online AS user_online, scm.gid, {$fields}
                   0 AS count, NULL AS last_resp, NULL AS resp_uname, scm.private
              FROM special_chat sc
             INNER
              JOIN login l
                ON l.uid = sc.uid
              LEFT
              JOIN TEMP_ONLINE tmo
                ON tmo.uid = sc.uid
             INNER
              JOIN special_chat_meta scm
                ON scm.mid = sc.mid
               {$join}
             WHERE sc.mid IN (#tap_ids#)";
        $taps = array();

        $result = $db->query($query, array('tap_ids' => $tap_ids));
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                array_unshift($taps, new Tap($res));

        return new TapsList($taps);
    }

    /*
        Returns filtered taps with last response,
        responses count, etc.

        $filter, $params described in $this->filterTapIds

        @returns
    */
    public function getFiltered($filter, array $params, $group_info = true, $user_info = false) {
        $idList = TapsList::filterTapIds($filter, $params);

        if (!count($idList))
            return new TapsList(array());

        return TapsList::getTaps($idList, $group_info, $user_info);
    }

    /*
        Returns last responses & overall responses count
        
        @return TapsList
    */
    public function lastResponses() {
        if (empty($this->data))
            return $this;

        $db = DB::getInstance()->Start_Connection('mysql');
        $tap_ids = $this->filter('id');

        $query = "
            SELECT c2.cid, c2.chat_text AS last_resp, c1.count, l.uname AS resp_uname,
                   GET_REAL_NAME(l.fname, l.lname, l.uname) AS resp_real_name
              FROM (
                    SELECT MAX(mid) AS mid, COUNT(mid) AS count
                      FROM chat c
                     WHERE cid IN (#ids#)
                     GROUP
                        BY cid
                   ) AS c1
             INNER
              JOIN chat c2
                ON c2.mid = c1.mid
             INNER
              JOIN login l
                ON l.uid = c2.uid";

        $responses = array();
        $result = $db->query($query, array('ids' => $tap_ids));
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $responses[ intval($res['cid']) ] = $res;

        foreach ($this->data as $tap) {
            if (array_key_exists($tap->id, $responses)) {
                $tap->last = $responses[$tap->id];
                $tap->count = $responses[$tap->id]['count'];
            } else
                $tap->last = array('count' => 0, 'resp_uname' => null, 'last_resp' => null);
        }

        return $this;
    }

    /*
        First tap in a list

        @return Tap
    */
    public function getFirst() {
        reset($this->data);
        return current($this->data);
    }

};
