<?php

class public_group extends Base {
    function __construct() {
        $this->view_output = "HTML";
        $this->need_db = 1;
        $this->page_name = "public_template_group";

        parent::__construct();

        $symbol = $_GET['symbol'];

        $group = Group::fromSymbol($symbol);
        if ($group === null)
            header('Location: http://tap.info?error=no_public_group');

//START get gid
        $get_gid_query = <<<EOF
	SELECT GO.count,g.descr,g.gid,g.favicon,g.pic_100,g.gname,g.symbol,g.topbg,g.connected,g.private FROM groups AS g
	JOIN GROUP_ONLINE AS GO
	ON GO.gid = g.gid
	WHERE g.symbol = '{$symbol}' LIMIT 1;
EOF;
        $gid_result = $this->db->query($get_gid_query);

        if (!$gid_result->num_rows)

        $res = $gid_result->fetch_assoc();
        $mapping = array('gid' => 'gid', 'gname' => 'gname', 'type' => 'connected',
            'symbol' => 'symbol', 'online_count' => 'count', 'descr' => 'descr',
            'pic_100' => 'pic_100', 'favicon' => 'favicon', 'topbg' => 'topbg');
        foreach($mapping as $var => $id)
            $$var = $res[$id];

        // is private group
        $this->set($res['private'], 'private');

        //is requested
        $requested = false;
        $q = "SELECT status FROM group_members WHERE gid = '{$gid}' AND uid = '{$_SESSION['uid']}'";
        $request_results = $this->db->query($q);
        $res = $request_results->fetch_assoc();

        //-1 - empty
        //0 - requested
        //1 - joined
        $status = -1;
        if ($res){
            $status = $res['status'];
            if ($res['status'] == '0'){
                $requested = true;
            }
        }


        $this->set($requested, 'requested');
        $this->set($status, 'status');


//Part of group?
        $sc = strpos('x,' . $_SESSION['gid'] . ',', ',' . $gid . ',');
        $joined = $sc ? True : False;
        $descr = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]", "<a href=\"\\0\" target=\"_blank\">\\0</a>", $descr);

        $this->set($joined, 'joined');
        $this->set($pic_100, 'group_pic_med');
        $this->set($favicon, 'favicon');
        $this->set($gname, 'gname');
        $this->set($symbol, 'symbol');
        $this->set($type, 'type');
        $this->set($topbg, 'topbg');
        $this->set($gid, 'gid');
        $this->set($descr, 'descr');
        $this->set($online_count, 'online_count');
        echo 1;
        if (!$gid)
            return False;
        echo 2;
//END get gid

        $get_member_count = <<<EOF
	SELECT COUNT(gm.uid) AS total_count FROM group_members AS gm WHERE gm.gid = {$gid}
EOF;


        $count_results = $this->db->query($get_member_count);
        $res = $count_results->fetch_assoc();
        $total_count = $res['total_count'];
        $this->set($total_count, 'total_count');

//START get admin list
        $admin_list_query = <<<EOF
SELECT gm.admin,l.uname,l.pic_36,gm.uid FROM group_members AS gm 
JOIN login AS l
ON l.uid = gm.uid
WHERE gm.gid = {$gid} AND admin <> 0;
EOF;

        $admin_list_results = $this->db->query($admin_list_query);

        $user_admin = false;
        while ($res = $admin_list_results->fetch_assoc()) {
            $uname = $res['uname'];
            $admin_uid = $res['uid'];
            $type = $res['admin'];
            $pic_36 = $res['pic_36'];

            switch ($type) {
                case 1:
                    $type = 'Founder';
                    break;
                case 2:
                    $type = 'Admin';
                    break;
            }

            $admin_data[] = array(
                'uname' => $uname,
                'small_pic' => $pic_36,
                'type' => $type
            );
            if ($admin_uid == $_SESSION['uid'])
                $user_admin = true;
        }
        $this->set($admin_data, 'admins');
        $this->set($user_admin, 'enable_admin');


        if ($_SESSION['uid'])
            $logged_in_id = $_SESSION['uid'];
        else
            $logged_in_id = 0;

        var_dump($group->gid);
        $this->set(
            TapsList::getFiltered('ind_group', array('#outside#' => '1, 2', '#gid#' => $group->gid))
                    ->lastResponses()
                    ->filter('all')
            , 'user_bits');

//START member count
        $count_group_member_query = "SELECT COUNT(uid) AS member_count FROM group_members WHERE gid = {$gid}";
        $count_member_result = $this->db->query($count_group_member_query);
        $res = $count_member_result->fetch_assoc();
        $member_count = $res['member_count'];
        $this->set($member_count, 'member_count');
//END member count


        $this->set($this->get_popular_members($gid), 'popular_members');

//START taps count
        $taps_count_sql = "
            SELECT COUNT(sm.gid) AS taps_count
            FROM special_chat_meta AS sm 
            WHERE sm.gid = {$gid}";
        $res = $this->db->query($taps_count_sql)->fetch_assoc();
        $this->set($res['taps_count'], 'taps_count');
//END taps count

//START responses count
        $responses_count_sql = "
            SELECT COUNT(c.mid) AS responses_count
            FROM special_chat_meta AS sm 
            INNER JOIN special_chat AS sc
                    ON sc.mid = sm.mid
            INNER JOIN chat AS c
                    ON c.cid = sc.cid
            WHERE sm.gid = {$gid} ";
        $res = $this->db->query($responses_count_sql)->fetch_assoc();
        $this->set($res['responses_count'], 'responses_count');
//END responses count
        
        Action::log($this->user, 'group', 'view', array('gid' => $gid));
    }

    private function get_popular_members($gid){
        //START most popular members
        $popular_members_query = <<<EOF
SELECT l.uname,l.pic_36,COUNT(sc.mid) as count FROM special_chat AS sc
JOIN special_chat_meta AS scm ON sc.mid = scm.mid
JOIN login AS l ON l.uid = sc.uid
WHERE scm.gid = {$gid}
GROUP BY sc.uid ORDER BY count DESC LIMIT 9;
EOF;
        $popular_members_results = $this->db->query($popular_members_query);
        while ($res = $popular_members_results->fetch_assoc()) {
            $member = $res['uname'];
            $pic_36 = $res['pic_36'];
            $count = $res['count'];

            $popular_members_data[] = array(
                'member' => $member,
                'small_pic' => $pic_36,
                'count' => $count
            );
        }
//        $this->set($popular_members_data, 'popular_members');
//END most popular members
        return $popular_members_data;
    }

};
