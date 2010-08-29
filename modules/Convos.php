<?php

/*
    Conversations, and everything related to them
    add/get active conversations, etc
*/
class Convos extends BaseModel {
    public function __construct() {
        parent::__construct();
    }

    /*
        Returns active conversations
    */
    public function getActive($uid, $special_where = "", $special_params = array()) {
        $query = "
		    SELECT ac.mid, ac.uid, l.uname, l.pic_36 AS small_pic,
                   GET_REAL_NAME(l.fname, l.lname, l.uname) AS real_name,
                   sc.chat_text AS message
              FROM active_convo AS ac
              JOIN special_chat AS sc
        		ON sc.mid = ac.mid
              JOIN login AS l
                ON l.uid = sc.uid
             WHERE ac.uid = #uid#
               AND ac.active = 1
             {$special_where}
             ORDER
                BY ac.mid ASC";
        $result = $this->db->query($query, array_merge($special_params, array('uid' => $uid)));
        $activeConvos = array();
        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
               $activeConvos[ intval($res['mid']) ] = $res;

        return $activeConvos;
    }

    /*
        Returns specific active conversation

        $mid - conversation id, sometimes called cid
    */
    public function getActiveOne($uid, $mid) {
        $result = $this->getActive($uid, " AND ac.mid = #mid# ", array('mid' => $mid));
        return $result[ intval($mid) ];
    }

    /*
        Set convo active, if it already exists

        Returns true|false depending on existence of convo
    */
    private function setActive($uid, $mid) {
        $query = "UPDATE active_convo
                     SET active = 1
                   WHERE uid = #uid#
                     AND mid = #mid#
                   LIMIT 1";
        $this->db->query($query, array('uid' => $uid, 'mid' => $mid));
        return $this->db->affected_rows == 1;
    }

    /*
        Add active convo to specified user
    */
    private function addActive($uid, $mid) {
        $query = "INSERT 
                    INTO active_convo
                        (mid, uid, active)
                 VALUES (#mid#, #uid#, 1)";
        $this->db->query($query, array('uid' => $uid, 'mid' => $mid));
    }

    /*
        Make conversation active for specified user
    */
    public function makeActive($uid, $mid) {
        if (!$this->setActive($uid, $mid))
            $this->addActive($uid, $mid);
    }

    /*
        Returns if convo active or not
        (1 | 0)
    */
    public function getStatus($uid, $mid) {
        $query = "
            SELECT active
              FROM active_convo
             WHERE uid = #uid#
               AND mid = #mid#
             LIMIT 1";
        $active = 0;
        $result = $this->db->query($query, array('uid' => $uid, 'mid' => $mid));
        if ($result->num_rows) {
            $result = $result->fetch_assoc();
            $active = $result['active'];
        }

        return $active;
    }

};
