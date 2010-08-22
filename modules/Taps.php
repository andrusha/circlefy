<?php

class Taps {
    private $db;

    public function __construct() {
        $this->db = new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

    /*
        Returns tap with little formatting
    */
    public function getTap($tap_id) {
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
             WHERE sc.mid = {$tap_id}
             LIMIT 1";
        $tap = array();
        $result = $this->db->query($query);
        if ($result->num_rows)
            $tap = $this->formatTap($result->fetch_assoc());

        return $tap;
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
        Returns responses count for tap
    */
    public function responsesCount($tap_id) {
        $query = "
            SELECT COUNT(mid) AS count
              FROM chat
             WHERE cid = {$tap_id}
             GROUP
                BY mid";
        $count = 0;
        $result = $this->db->query($query);
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $count = intval($result['count']);
        }

        return $count;
    }
};
