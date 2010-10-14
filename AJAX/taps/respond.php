<?php
/* CALLS:
    feed.js
*/

class ajax_respond extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $cid = intval($_POST['cid']);
        $msg = strip_tags(stripslashes($_POST['response']));

        $tap = new Tap(intval($cid));

        if ($tap->responseDupe($this->user, $msg)) {
            $this->data = array('dupe' => true);
            return;
        }

        $tap->addResponse($this->user, $msg)->makeActive($this->user);
        $this->notify($tap);

		$this->data = array('success' => 1);
    }

 	private function notify(Tap $tap) {
        $ids = UsersList::search('convo', array('mid' => $tap->id, 'active' => 1), U_ONLY_ID)->filter('id');

        $data = $tap->format()->asArray();
        end($data['replies']);

        Comet::send('message',
            array('action' => 'response',
                  'users'  => $ids,
                  'cid'    => $tap->id,
                  'data'   => current($data['replies'])));
	}
};
