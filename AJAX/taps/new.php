<?php
/* CALLS:
    chat_window.js
*/

require('../../config.php');
require('../../api.php');

class new_message extends Base {
    public function __construct() {
        $this->need_db = false;
        $this->view_output = 'JSON';
        parent::__construct();

        $to     = $_POST['to'];
        $type   = $to['type'];
        $symbol = $to['symbol'];
        $id     = intval($to['id']);

        $msg = $_POST['msg'];
        switch ($type) {
            case 'channels':
                $group = $id ? new Group($id) : Group::fromSymbol($symbol);
                $this->data = $this->create_channel($group, $msg);
                break;

            case 'private':
                $user = new User($id);
                $this->data = $this->send_private($user, $msg);
                break;
        }
    }

    private function send_private(User $to, $msg) {
        $tap = Tap::toUser($this->user, $to, $msg);

        $msg = array(
            'channel_id'  => $cid,
            'time'        => time(),
            'new_channel' => 'true',
            'new_msg'     => array($tap->all),
            'your_first'  => false);

        $this->notifyPrivate($this->user, $to, $tap->all);
        
        return $msg;
    }

    private function create_channel(Group $group, $msg) {
        if (Tap::checkDuplicate($this->user, $msg))
            return array('dupe' => true);

        $tap = Tap::toGroup($group, $this->user, $msg);
        $tap->makeActive($this->user);
        
        $your_first = Tap::firstTapInGroup($group, $this->user);

        $msg = array(
            'channel_id'  => $tap->id,
            'time'        => time(),
            'new_channel' => 'true',
            'new_msg'     => array($tap->all),
            'your_first'  => $your_first);

        $this->notifyViewers($this->user, $group, $tap->all);
        $this->notifyMembers($this->user, $group, $tap->all);

        return $msg;
    }

    private function notifyPrivate(User $from, User $to, array $tap_array) {
        $message = array('action' => 'notify.private',
            'users' => array($to->uid), 'data' => $tap_array);
        Comet::send('message', $message);

        //yeah, it also throws event to display tap in feed
        $message = array('action' => 'tap.new',
            'users' => array($to->uid), 'data' => $tap_array);
        Comet::send('message', $message);
   }

    /*
        Notify all group viewers, that there is new tap
    */
    private function notifyViewers(User $tapper, Group $group, $tap_array) {
        $message = array('action' => 'tap.new', 'gid' => intval($group->gid),
            'exclude' => array(intval($tapper->uid)), 'data' => $tap_array);
        Comet::send('message', $message);
    }
    
    /*
        Notify all group members, that there is new tap
    */
    private function notifyMembers(User $tapper, Group $group, $tap_array) {
        $users = $group->getMembers(true);

        $text = FuncLib::makePreview($tapText);
        $data = array('gname' => $tap_array['gname'], 'greal_name' => $tap_array['symbol'], 
                      'uname' => $tap_array['uname'], 'ureal_name' => $tap_array['real_name'],
                      'text'  => $tap_array['chat_text']);
        $message = array('action' => 'notify.tap.new',
            'gid' => $group->gid, 'users' => $users,
            'exclude' => array($tapper->uid), 'data' => $data);
        Comet::send('message', $message);
    }
};

$something = new new_message();
