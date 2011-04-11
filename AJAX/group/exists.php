<?php
/*
    CALLS:
        modal.js
*/
class ajax_exists extends Base {
    protected $view_output = 'JSON';
    protected $need_login = false;

    function __invoke() {
        $symbol = $_POST['symbol'];

        $this->data = array('exists' => Group::fromSymbol($symbol)->id !== null);
    }
};
