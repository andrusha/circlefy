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
        $media = (!empty($_POST['media']['mediatype'])?$_POST['media']:null);
        $private = intval($_POST['private']);
        $anon = intval($_POST['anon']);

        if (Tap::checkDuplicate($this->user, $msg))
            return $this->data = array('dupe' => true);

        switch ($type) {
            case 'group':
                $group = new Group($id);
                $tap = Tap::toGroup($group, $this->user, $msg, $media, $private, $anon);
                break;

            case 'friend':
                $user = new User($id);
                $tap = Tap::toUser($this->user, $user, $msg, $media, $anon);
                break;
        }

        $tap->makeActive($this->user);
        $tap->detectLanguage($this->user->ip);
        $this->data = array('success' => 1);
    }
};
