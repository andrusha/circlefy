<?php
/* CALLS:
    follow.js
*/

class ajax_follow extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $id = !is_array($_POST['id']) ? intval($_POST['id']) : $_POST['id'];
        $type = $_POST['type'];
        $state = intval($_POST['state']);

        if (in_array($type, array('user', 'group', 'convo', 'bulk')))
            $res = $this->$type($id, $state);

        $this->data = array('success' => $res ? 1 : 0);
    }

    private function group($gid, $state) {
        $group = new Group($gid);

        $perm = $group->userPermissions($this->user);
        if (($perm == Group::$permissions['not_in'] && !$state) ||
            ($perm != Group::$permissions['not_in'] && $state))
            return false;

        if ($state)
            $this->user->join($group);
        else
            $this->user->leave($group);

        return true;
    }

    private function bulk($gids, $state) {
        if (!empty($gids))
            GroupsList::fromIds(array_map('intval', $gids))->bulkJoin($this->user);
        return true; 
    }

    private function convo($cid, $state) {
        $tap = new Tap($cid);
        $tap->makeActive($this->user, $state);
        return true;
    }

    private function user($fid, $state){
        $friend = new User(intval($fid));

        if($state)
            $this->user->follow($friend);
        else
             $this->user->unfollow($friend);
        $this->notifyFriend($friend, $state);

        return true;
    }

    private function notifyFriend(User $friend, $status) {
        $data = array('status' => $status, 'user' => $this->user->asArray());
        $message = array('action' => 'notify.follower', 'users' => array(intval($friend->id)), 'data' => $data);

        Comet::send('message', $message);
    }

};
