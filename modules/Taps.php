<?php

/*
    It's pretty much function library
    with all about Taps & Responses to them
*/
class Taps extends BaseModel {
    public function __construct() {
        parent::__construct();
    }

    /*
        Returns list of tap_id's (mids) that meets
        filter requirements, we can actually combine
        filters as we want, but it not implemented by now
        
        $filter selected filter type for query
        'aggr_groups' | 'ind_group' | 'public' | 'personal'

        $params array of params related to that filter
        array('#uid#' => user id
              '#outside#' => what type of groups to show
              '#gid#' => group id
              '#search#' => if we searching something
              '#start_from#' => start from in LIMIT
              '#anon#' => filter registred/anonymous users
    */
    public function filterTapIds($filter, $params) {
        if (!function_exists('substitute')) {
            function substitute(&$item, $key, array $params) {
                $item = strtr($item, $params);
            }
        }
        $joins = array(
            'meta' => 'JOIN special_chat_meta scm ON scm.mid = sc.mid',
            'members' => 'JOIN group_members gm ON gm.gid = scm.gid',
            'logins' => 'JOIN login l ON l.uid = sc.uid'
        );

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

            case 'personal':
                $where[] = 'sc.uid = #uid#';
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
            
            $taps[$cid] = array_merge($data, $resp);
        }
        
        //FIXME: since shitty js-code works only with reversed lists
        return array_reverse(array_values($taps));
    }

    /*
        Returns tap with little formatting

        $tap_id int|array
    */
    public function getTaps($tap_ids) {
        //default responses info (count, last_resp, resp_uname) set to null
        $query = "
            SELECT sc.mid AS cid, sc.chat_text, UNIX_TIMESTAMP(sc.chat_timestamp) AS chat_timestamp_raw,
                   UNIX_TIMESTAMP(sc.chat_timestamp) AS chat_timestamp, sc.uid, l.uname,
                   GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name, l.pic_100,
                   tmo.online AS user_online, scm.gid, g.gname, g.symbol, g.favicon,
                   0 AS count, NULL AS last_resp, NULL AS resp_uname
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
             WHERE sc.mid IN (#tap_ids#)";
        $taps = array();

        $result = $this->db->query($query, array('tap_ids' => $tap_ids));
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
        Creates new tap, return tap_id (cid/mid)
    */
    public function add($uid, $uname, $addr, $gid, $text) {
        $this->db->startTransaction();
        $ok = true;

        //requires to get proper cid (OBSOLETE)
        $query = "INSERT
                    INTO channel (uid)
                  VALUES (#uid#)";
        $ok = $ok && $this->db->query($query, array('uid' => $uid));

        $cid = $this->db->insert_id;

        //makes tap appears online (OBSOLETE)
        $query = "INSERT
                    INTO TAP_ONLINE (cid)
                  VALUES (#cid#)";
        $ok = $ok && $this->db->query($query, array('cid' => $cid));

        $query = "INSERT
                    INTO special_chat (cid, uid, uname, chat_text, ip)
                  VALUES (#cid#, #uid#, #uname#, #chat_text#, INET_ATON(#ip#))";
        $ok = $ok && $this->db->query($query, array('cid' => $cid, 'uid' => $uid, 'uname' => $uname,
            'chat_text' => $text, 'ip' => $addr));

        //fulltext table duplicates everything
        $ok = $ok && $this->db->query(str_replace('special_chat', 'special_chat_fulltext', $query),
            array('cid' => $cid, 'uid' => $uid, 'uname' => $uname, 'chat_text' => $text,
                'ip' => $addr));

        $query = "INSERT
                    INTO special_chat_meta (mid, gid, connected, uid)
                   VALUE (#cid#, #gid#, 1, #uid#)";     
        $ok = $ok && $this->db->query($query, array('cid' => $cid, 'gid' => $gid, 'uid' => $uid));

        if ($ok)
            $this->db->commit();
        else {
            $this->db->rollback();
            throw new Exception('We have some DB problem out there');
        }

        return $cid;
    }

    /*
        Returns bool if tap is duplicate or not
    */
    public function checkDuplicate($text, $uid) {
        $query = "SELECT chat_text
                    FROM special_chat
                   WHERE uid = #uid#
                   ORDER
                      BY mid DESC
                   LIMIT 1";
        $result = $this->db->query($query, array('uid' => $uid));

        $dupe = false;
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $dupe = $result['chat_text'] == $text;
        }

        return $dupe;
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
            WHERE c.cid = #tap_id#";
        $responses = array();
        $result = $this->db->query($query, array('tap_id' => $tap_id));
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
        $query = "
            SELECT c2.cid, c2.chat_text AS last_resp, c1.count, l.uname AS resp_uname,
                   GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name 
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
        $result = $this->db->query($query, array('ids' => $tap_ids));
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
        $query = "
            SELECT COUNT(mid), cid AS count
              FROM chat
             WHERE cid IN (#cids#) 
             GROUP
                BY mid";

        $counts = array();
        $result = $this->db->query($query, array('cids' => $tap_ids));
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $counts[ intval($res['cid']) ] = intval($res['count']);

        if (!is_array($tap_ids))
            $counts = current($counts);

        return $counts;
    }

    /*
        Checks if user left any taps in group
    */
    public function firstTapInGroup($gid, $uid) {
        $query = "SELECT sc.metaid
                    FROM special_chat_meta sc
                   INNER
                    JOIN special_chat s
                      ON s.mid = sc.mid
                   WHERE sc.gid = #gid#
                     AND s.uid = #uid#
                   LIMIT 1";
        $result = $this->db->query($query, array('gid' => intval($gid), 'uid' => intval($uid)));
        $check = $result->num_rows == 0;
        return $check;
    }

    /*
        Makes text shorter. If it exceeds limit, adds '...'
    */
    public static function makePreview($text, $limit = 50) {
        if (!function_exists('clean')) {
            //cleans all mess around text, before make it shorter
            function clean($text) {
                $text = trim($text);
                $text = preg_replace('/\s{2,}/ism', ' ', $text);
                return $text;    
            }
        }

        if (!function_exists('byExplode')) {
            //tries to combine as much parts of string
            //exploded by delimeter not to exceed limit
            function byExplode($text, $delimeter, $limit) {
                $parts = explode($delimeter, $text);
                $result = '';
                foreach ($parts as $part) {
                    if (strlen($result) + strlen($delimeter) + strlen($part) <= $limit)
                        $result .= $delimeter.$part;
                }
                $result = substr($result, 1);

                return $result;
            }
        }

        $text = clean($text);

        //okay, text is good enough anyway
        if (strlen($text) <= $limit)
            return $text;
        
        $limit -= 3; //for '...'
        $byPoint = byExplode($text, '.', $limit);
        $byWord = byExplode($text, ' ', $limit);

        $result = '';

        //select best match, if fails, just
        //cut text up to the limit
        if ($byPoint || $byWord) {
            if (strlen($byPoint) > strlen($byWord))
                $result = $byPoint;
            else
                $result = $byWord;
        } else {
            $result = substr($text, 0, $limit-1);
        }

        return $result.'...';
    }
};
