<?php
/* CALLS:
    feed.js
*/

class ajax_delete extends Base {
    protected $view_output = 'JSON';

    public function __invoke() {
        $id = intval($_POST['id']);

        $tap = Tap::byId($id, false);
        if ($tap->sender_id != $this->user->id) {
            $this->set(false, 'success');
            return;
        }

        $tap->delete();
        $this->set(true, 'success');
    }
};
