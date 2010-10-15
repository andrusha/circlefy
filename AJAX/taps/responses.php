<?php
/* CALLS:
    feed.js
*/

class ajax_responses extends Base {
    protected $view_output = 'JSON';
    protected $need_login = false;

    function __invoke() {
        $id = intval($_POST['cid']);
        
        $tap = new Tap($id);
        $tap = $tap->getReplies()->format()->asArray();
        $responses = $tap['replies'];
        $this->data = array('success' => count($responses) > 0,'responses' => $responses);
    }
};
