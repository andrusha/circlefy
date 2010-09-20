<?php
/* CALLS:
    chat_window.js

format:
['x:x:1:1']
['name:symbol:type']
*/

session_start();
require('../config.php');
require('../api.php');

$to = $_POST['to'];

$type = $to['type'];
$symbol = $to['symbol'];
$id = intval($to['id']);

$msg = $_POST['msg'];

$obj = new chat_functions();
switch ($type) {
    case 'channels':
        $gid = $id ? new Group($id) : Group::fromSymbol($symbol)->gid;
        $result = $obj->create_channel($msg, $gid);
        break;

    case 'private':
        $uid = $id;
        $result = $obj->send_private($msg, $uid);
        break;
}

echo json_encode($result);

class chat_functions{
    private $user = null;

    public function __construct() {
        $this->user = new User(intval($_SESSION['uid']));
    }

    function send_private($msg, $uid) {
        $tap = new Taps();

        $to = new User($uid);
        $cid = $tap->toUser($this->user, $to, $msg);

        $tap_array = $tap->getTap($cid, false, true);

        $msg_and_channel_id  = array(
            'channel_id' => $cid,
            'time' => time(),
            'new_channel' => 'true',
            'new_msg' => array($tap_array),
            'your_first' => false);

        $this->notifyPrivate($this->user, $to, $tap_array);
        
        return $msg_and_channel_id;
    }

    function create_channel($msg, Group $group) {
        $time = time();
        
        $tap = new Taps();
        if($tap->checkDuplicate($msg, $this->user->uid))
            return array('dupe' => true);

        $cid = $tap->toGroup($group, $this->user, $msg);
        $tap_array = $tap->getTap($cid);

        $convos = new Convos();
        $convos->makeActive($this->user->uid, $cid);
        
        $your_first = $tap->firstTapInGroup($group->gid, $this->user->uid);

        //Return information to user in JSON
        $msg_and_channel_id  = array(
            'channel_id' => $cid,
            'time' => $time,
            'new_channel' => 'true',
            'new_msg' => array($tap_array),
            'your_first' => $your_first);

        $this->notifyViewers($this->user, $group, $tap_array);
        $this->notifyMembers($this->user, $group, $tap_array);

        return $msg_and_channel_id;
    }

    private function notifyPrivate(User $from, User $to, array $tap_array) {
        $message = array('action' => 'notify.private',
            'users' => array($to->uid), 'data' => $tap_array);
        Comet::send('message', $message);

        //yeah, it also throws event to display tap in feed
/*        $message = array('action' => 'tap.new',
            'users' => array($to->uid), 'data' => $tap_array);
        Comet::send('message', $message);*/
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

        $text = Taps::makePreview($tapText);
        $data = array('gname' => $tap_array['gname'], 'greal_name' => $tap_array['symbol'], 
                      'uname' => $tap_array['uname'], 'ureal_name' => $tap_array['real_name'],
                      'text'  => $tap_array['chat_text']);
        $message = array('action' => 'notify.tap.new',
            'gid' => $group->gid, 'users' => $users,
            'exclude' => array($tapper->uid), 'data' => $data);
        Comet::send('message', $message);
    }
};
