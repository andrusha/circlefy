<?php
/* CALLS:
    feed.js
*/

class ajax_new extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $id   = intval($_POST['id']);
        $type = $_POST['type'];
        $msg  = strip_tags($_POST['msg']);

        if (Tap::checkDuplicate($this->user, $msg))
            return $this->data = array('dupe' => true);

        switch ($type) {
            case 'group':
                $group = new Group($id);
                $tap = Tap::toGroup($group, $this->user, $msg);
                $this->notify($this->user, $group, null, $tap);
                break;

            case 'friend':
                $user = new User($id);
                $tap = Tap::toUser($this->user, $user, $msg);
                $this->notify($this->user, null, $to, $tap);
                break;
        }

        $tap->makeActive($this->user);
        $this->data = array('success' => 1);
    }

    /*
        Notify all group members, that there is new tap
    */
    private function notify(User $tapper, $group, $to, Tap $tap){
        $message = array(
            'action' => 'tap.new',
            'data' => $tap->format()->asArray());

        if ($group instanceof Group) {
            $uids = UsersList::search('members', array('gid' => $group->id), U_ONLY_ID)->filter('id');
            $message['gid'] = $group->id;
        } elseif ($to instanceof User) {
            $uids = array($to->id, $tapper->id);
        }

        $message['users'] = $uids;

        Comet::send('message', $message);
    }
};
