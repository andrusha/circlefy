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

$to_box = stripslashes($_POST['to_box']);
$to_list = json_decode($to_box);

$to_string = explode(":",$to_list[0]);
//symbol type, 0,1,2 = group, 99=user, null=keyword
$type = $to_string[2];
//actual symbol    
$symbol = trim($to_string[1]);
//meta data about symbol
$name = $to_string[0];

$msg = $_POST['msg'];

if($msg){
    $chat_obj = new chat_functions();
    $chat_id = $chat_obj->create_channel($msg, $symbol);
    echo json_encode($chat_id);
}

class chat_functions{
    function create_channel($msg, $symbol) {
        $uid = $_SESSION['uid'];
        $uname = $_SESSION['uname'];
        $addr = $_SERVER['REMOTE_ADDR'];
        $time = time();
        
        $tap = new Taps();
        if($tap->checkDuplicate($msg, $uid))
            return array('dupe' => true);

        $gid = Group::fromSymbol($symbol)->gid;

        $cid = $tap->add($uid, $uname, $addr, $gid, $msg);
        $tap_array = $tap->getTap($cid);

        $convos = new Convos();
        $convos->makeActive($uid, $cid);
        
        $your_first = $tap->firstTapInGroup($gid, $uid);

        //Return information to user in JSON
        $msg_and_channel_id  = array(
            'channel_id' => $cid,
            'time' => $time,
            'new_channel' => 'true',
            'new_msg' => array($tap_array),
            'your_first' => $your_first);

        $this->notifyViewers($uid, $gid, $tap_array);
        $this->notifyMembers($uid, $gid, $tap_array);

        return $msg_and_channel_id;
    }

    /*
        Notify all group viewers, that there is new tap
    */
    private function notifyViewers($tapper_uid, $gid, $tap_array) {
        $message = array('action' => 'tap.new', 'gid' => intval($gid),
            'exclude' => array(intval($tapper_uid)), 'data' => $tap_array);
        Comet::send('message', $message);
    }
    
    /*
        Notify all group members, that there is new tap
    */
    private function notifyMembers($tapper_uid, $gid, $tap_array) {
        $group = new Group($gid);
        $users = $group->getMembers(true);

        $text = Taps::makePreview($tapText);
        $data = array('gname' => $tap_array['gname'], 'greal_name' => $tap_array['symbol'], 
                      'uname' => $tap_array['uname'], 'ureal_name' => $tap_array['real_name'],
                      'text'  => $tap_array['chat_text']);
        $message = array('action' => 'notify.tap.new',
            'gid' => intval($gid), 'users' => $users,
            'exclude' => array(intval($tapper_uid)), 'data' => $data);
        Comet::send('message', $message);
    }
};
