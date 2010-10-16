<?php
/* CALLS:
	homepage_tap_friend.js
	tap_friend.js
	invite.js
*/

class ajax_follow extends Base {
    public function __invoke() {
        $this->need_db = false;
        $this->view_output = 'JSON';

        $fid = intval($_POST['fid']);
        $state = intval($_POST['state']);

        $this->data = $this->follow_action($fid, $state);
    }

    private function follow_action($fid, $state){
        $friend = new User(intval($fid));

	if($state == 1)
            $this->user->follow($friend);
	else
            $this->user->unfollow($friend);

        $this->notifyFriend($friend, $state);

        return array('success' => 1);
	}

    private function notifyFriend(User $friend, $status) {
        $data = array('status' => $status, 'user' => $this->user->asArray());
        $message = array('action' => 'notify.follower', 'users' => array(intval($friend->id)), 'data' => $data);

        Comet::send('message', $message);
    }

};
