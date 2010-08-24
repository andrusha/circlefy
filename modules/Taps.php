<?php

/*
    It's pretty much function library
    with all about Taps & Responses to them
*/
class Taps {
    private $db;

    public function __construct() {
        $this->db = new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

    /*
        Returns list of tap_id's (mids) that meets
        filter requirements, we can actually combine
        filters as we want, but it not implemented by now
        
        $filter selected filter type for query
        'aggr_groups' | 'ind_group' | 'public'

        $params array of params related to that filter
        array('#uid#' => user id
              '#outside#' => what type of groups to show
              '#gid#' => group id
              '#search#' => if we searching something
              '#start_from#' => start from in LIMIT
              '#anon#' => filter registred/anonymous users
    */
    public function filterTapIds($filter, $params) {
        $joins = array(
            'meta' => 'JOIN special_chat_meta scm ON scm.mid = sc.mid',
            'members' => 'JOIN group_members gm ON gm.gid = scm.gid',
            'logins' => 'JOIN login l ON l.uid = sc.uid'
        );

        //default filter, may be altered, so move it inside switches
        $toJoin = $where = array();
        switch ($filter) {
            case 'aggr_groups':
                $toJoin[] = 'meta';
                $toJoin[] = 'members';
                $where[] = 'gm.uid = #uid#';
                break;

            case 'ind_group':
                $toJoin[] = 'meta';
                $where[] = 'scm.gid = #gid#';
                break;

            case 'public':
                //it's ok for public anyway
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
        //this ugly, I know, but no way you can use array_map
        for ($i = 0; $i < count($where); $i++)
            $where[$i] = strtr($where[$i], $params);

        $toJoin = ' '.implode(' ', $toJoin).' ';
        $where = ' WHERE '.implode(' AND ', $where).' ';

        $query = "
            SELECT sc.mid
              FROM special_chat sc
            {$toJoin}
            {$where}
             ORDER
                BY sc.mid DESC
             LIMIT {$limit}";
        
        $mids = array();
        $result = $this->db->query($query);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $mids[] = intval($res['mid']);
        
        return $mids;
    }

    /*
        Returns filtered taps with last response,
        responses count, etc.

        $filter, $params described in $this->filterTapIds
    */
    public function getFiltered($filter, $params) {
        $idList = $this->filterTapIds($filter, $params);

        if (!count($idList))
            return array();

        $taps = $this->getTaps($idList);
        $responses = $this->lastResponses($idList);
        
        //we need to join our last responses with taps by id
        foreach ($taps as $cid => $data) {
            if (empty($responses[$cid]))
                $resp = array('count' => 0, 'resp_uname' => null, 'last_resp' => null);
            else
                $resp = $responses[$cid];
            
            $taps[$cid] = array_merge($resp, $data);
        }
        
        //FIXME: since shitty js-code works only with reversed lists
        return array_reverse(array_values($taps));
    }

    /*
        Returns tap with little formatting

        $tap_id int|array
    */
    public function getTaps($tap_ids) {
        $where = '';
        if (is_array($tap_ids))
            $where = 'IN ('.implode(', ', $tap_ids).') ';
        else
            $where = " = {$tap_ids}";

        $query = "
            SELECT sc.mid AS cid, sc.chat_text, UNIX_TIMESTAMP(sc.chat_timestamp) AS chat_timestamp_raw,
                   UNIX_TIMESTAMP(sc.chat_timestamp) AS chat_timestamp, sc.uid, l.uname,
                   GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name, l.pic_100,
                   tmo.online AS user_online, scm.gid, g.gname, g.symbol, g.favicon
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
             INNER
              JOIN groups g
                ON g.gid = scm.gid
             WHERE sc.mid {$where}";
        $taps = array();
        $result = $this->db->query($query);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $taps[ intval($res['cid']) ] = $this->formatTap($res);

        return $taps;
    }

    /*
        Returns desiered tap (only one, if avaliable)

        $tap_id int
    */
    public function getTap($tap_id) {
        $taps = $this->getTaps($tap_id);
        if ($taps) {
            //first element of array,
            //there obiously should be only one
            reset($taps);
            $taps = current($taps);
        }

        return $taps;
    }

    /*
        Formats time since & tap text 
    */
    public function formatTap($tap) {
        $tap['chat_timestamp'] = $this->timeSince($tap['chat_timestamp']);
        $tap['chat_text'] = $this->linkify(stripslashes($tap['chat_text']));

        return $tap;
    }

    /*
        Replaces all looks-like links/emails
        in text with html-tags
    */
    public function linkify($str) {
        $str = preg_replace("/\\b([a-z\\.-_]*@[a-z\\.]*\\.[a-z]{1,6})\\b/i", "<a href='mailto:\\1' target='_blank'>\\1</a>", $str);
        $str = preg_replace("#\\b((?:[a-z]{1,5}://|www\\.)([a-z\\.]*\\.[a-z]{1,6}(?:[a-z_\-0-9/\\#]*)))\\b#i", "<a href='\\1' target='_blank'>\\2</a>", $str);
        return $str;
    }

    /*
        Returns well formatted time since
        from unix timestamp
    */
    public function timeSince($timestamp) {
        $now = time();
        $diff = $now - $timestamp;
        $days = floor($diff / (60*60*24));
        $date = date("jS M Y", $timestamp);
       
        if ($days == 0) {
            if ($diff < 120) {
                $date = "Just Now";
            } elseif ($diff < 60*60) {
                $mins = floor($diff / 60);
                $date = "$mins mins ago";
            } elseif ($diff < 60*60*2) {
                $date = "An hour ago";
            } elseif ($diff < 60*60*24) {
                $hours = floor($diff / (60*60));
                $date = "$hours hours ago";
            }
        } elseif ($days == 1) {
            $date = "Yesterday";
        } elseif ($days < 7) {
            $date = "$days days ago";
        } elseif ($days == 7) {
            $date = "A week ago";
        } elseif ($days < 31) {
            $weeks = ceil($days / 7);
            $date = "$weeks weeks ago";
        }

        return $date;
    }

    /*
        Returns responses to tap
    */
    public function getResponses($tap_id) {
        $query = "
            SELECT c.mid, c.uid, l.uname, l.pic_36 as small_pic,
                   GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name,
                   c.chat_text, UNIX_TIMESTAMP(c.chat_time) AS chat_time_raw,
                   UNIX_TIMESTAMP(c.chat_time) AS chat_time, c.anon
             FROM chat c
            INNER
             JOIN login l 
               ON l.uid = c.uid
            WHERE c.cid = {$tap_id}";
        $responses = array();
        $result = $this->db->query($query);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $responses[] = $this->formatTap($res);
        
        return $responses;
    }

    /*
        Returns last responses & overall responses count

        $tap_ids array
    */
    public function lastResponses($tap_ids) {
        $ids = implode(', ', $tap_ids);
        $query = "
            SELECT c2.cid, c2.chat_text AS last_resp, c1.count, l.uname AS resp_uname,
                   GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name 
              FROM (
                    SELECT MAX(mid) AS mid, COUNT(mid) AS count
                      FROM chat c
                     WHERE cid IN ({$ids})
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
        $result = $this->db->query($query);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $responses[ intval($res['cid']) ] = $res;

        return $responses;
    }

    /*
        didn't use anywhere, actually

        Returns responses count for taps like
        array(tap_id => count, ...) | int if one tap

        $tap_ids int|array
    */
    public function responsesCount($tap_ids) {
        if (is_array($tap_ids)) {
            $where = ' IN ('.implode(', ', $tap_ids).') ';
            $multiple = true;
        } else {
            $where = " = {$tap_id}";
            $multiple = false;
        }

        $query = "
            SELECT COUNT(mid), cid AS count
              FROM chat
             WHERE cid {$where}
             GROUP
                BY mid";

        $counts = array();
        $result = $this->db->query($query);
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $counts[ intval($res['cid']) ] = intval($res['count']);

        if (!$multiple)
            $counts = current($counts);

        return $counts;
    }
};
