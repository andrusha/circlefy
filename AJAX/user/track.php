<?php
/* CALLS:
	homepage_tap_friend.js
	tap_friend.js
	invite.js
*/
require('../../config.php');
require('../../api.php');

class friend extends Base {
    public function __construct() {
        $this->need_db = false;
        $this->view_output = 'JSON';
        parent::__construct();

        $fid = intval($_POST['fid']);
        $state = intval($_POST['state']);

        $this->data = $this->tap_friend($fid, $state);
    }

    private function tap_friend($fid, $state){
        $friend = new User(intval($fid));

        $info = $this->user->info;
        $finfo = $friend->info;

		if($state == 1 && !$finfo['private'])
            $this->user->follow($friend);
		else
            $this->user->unfollow($friend);

        $this->notifyFriend($info, $this->user->uid, $friend->uid, $state);

        return array('success' => 1);
	}

    private function notifyFriend($info, $uid, $fuid, $status) {
        $data = array('status' => $status, 'uname' => $info['uname'], 'ureal_name' => $info['real_name'], 'avatar' => $info['big_pic']);
        $message = array('action' => 'notify.follower', 'users' => array(intval($fuid)), 'data' => $data);

        Comet::send('message', $message);
    }

};

$f = new friend();
