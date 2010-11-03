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

		$this->data = array('success' => 1);
    }
};
