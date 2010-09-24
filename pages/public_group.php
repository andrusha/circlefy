<?php

class public_group extends Base {
    function __construct() {
        $this->view_output = "HTML";
        $this->need_db = 1;
        $this->page_name = "public_template_group";

        parent::__construct();

        $symbol = $_GET['symbol'];

        $g = Group::extended($symbol);
        if ($g === null)
            header('Location: http://tap.info?error=no_public_group');

        $this->set($g->info['private'], 'private');

        $status = $g->userStatus($this->user);
        $this->set($status == 0, 'requested');
        $this->set($status, 'status');

        $this->set($g->pic_100, 'group_pic_med');
        $this->set($g->favicon, 'favicon');
        $this->set($g->gname, 'gname');
        $this->set($g->symbol, 'symbol');
        $this->set($g->connected, 'type');
        $this->set($g->gid, 'gid');
        $this->set($g->topic, 'descr');
        $this->set($g->count, 'online_count');
        $this->set($g->members_count, 'member_count');
        $this->set($g->taps_count, 'taps_count');
        $this->set($g->responses_count, 'responses_count');


//START get admin list
        $admin_list_query = <<<EOF
SELECT gm.admin,l.uname,l.pic_36,gm.uid FROM group_members AS gm 
JOIN login AS l
ON l.uid = gm.uid
WHERE gm.gid = {$g->gid} AND admin <> 0;
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

        $this->set(
            TapsList::getFiltered('ind_group', array('#outside#' => '1, 2', '#gid#' => $g->gid))
                    ->lastResponses()
                    ->filter('all')
            , 'user_bits');
        
        Action::log($this->user, 'group', 'view', array('gid' => $gid));
    }
};
