<?php
/*
    CALLS:
        modal.js
*/
class ajax_check extends Base {
    protected $view_output = 'JSON';

    function __invoke() {
        $type = $_POST['type'];
        $val  = $_POST['val'];
        $aval = false;

        if (in_array($type, array('uname', 'email')))
            $aval = !User::existsField($type, $val);

        $this->data = array('available' => $aval);
    }
};
