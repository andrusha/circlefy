<?php
/*
    CALLS:
        modal.js
*/
class ajax_context extends Base {
    protected $view_output = 'JSON';
    protected $need_login = true;

    function __invoke() {
        $gid  = $_POST['gid'];
        $ctx  = $_POST['context'];

        if (!empty($gid) && !empty($ctx)) {
            $g = Group::byId($gid);
            $a = $g->saveContext($this->user, $ctx);
            $success = 1;
        }
        $this->data = array('success' => $success ? 1:0, 'a' => $a);
    }
};
