<?php
/* CALLS:
    public.js
*/
$usage = <<<EOF
cid: channel id

first: is this the first time a user has responded?

init_tapper: the person who intially tapped

response: the text of the response
EOF;

require('../../config.php');
require('../../api.php');

class respond extends Base {

    public function __construct() {
        $this->need_db = true;
        $this->view_output = 'JSON';
        parent::__construct();

        $response = stripslashes($_POST['response']);
        $cid = $_POST['cid'];
        $first = $_POST['first'];
        $init_tapper = $_POST['init_tapper'];

        $this->data = $this->send_response($response, $cid, $init_tapper, $first);
    }

	private function send_response($msg,$cid,$init_tapper,$first){
        $tap = new Tap(intval($cid));
        $tapper = new User(intval($init_tapper));

        if ($tap->responseDupe($tapper, $msg))
            return array('dupe' => true);


        $msg = strip_tags($msg);
        $tap->addResponse($this->user, $msg);
        
        $tap->makeActive($this->user, 1);
        $tap->makeActive($tapper, 1);

        $this->pushResponse($tap, $tapper, $_POST['pic'], $msg);
        $this->notify_all($tap, $tapper, $msg, $_POST['big_pic']);

		return array('success' => 1);
	}

    private function pushResponse(Tap $tap, User $tapper, $small_pic, $msg) {
        $message = array('cid' => $tap->id, 'action' => 'response',
            'response' => $msg, 'uname' => $this->user->uname,
            'init_tapper' => $tapper->uid, 'pic_small' => $small_pic);
        Comet::send('message', $message);
    }

	private function notify_all(Tap $tap, User $tapper, $text, $avatar) {
        $query = "
            SELECT a.uid
              FROM active_convo a 
             INNER
              JOIN TEMP_ONLINE tmo
                ON tmo.uid = a.uid
             WHERE a.mid = #cid#
               AND tmo.online = 1
               AND a.active = 1
               AND a.uid <> #uid#";

        $users = array();
        $result = $this->db->query($query,
            array('cid' => $tap->id, 'uid' => $tapper->uid), true);

        if ($result->num_rows)
            while ($res = $result->fetch_assoc())
                $users[] = intval($res['uid']);

        $text = FuncLib::makePreview($text);
        $data = array('cid' => $tap->id, 'uname' => $this->user->uname,
            'ureal_name' => $this->user->real_name, 'text' => $text, 'avatar' => $avatar);

        Comet::send('message', array('action' => 'notify.convo.response', 'users' => $users, 
            'exclude' => array($this->user->uid), 'data' => $data));
	}
};

$something = new respond();
