<?php
/* CALLS:
    homepage.phtml
*/
$usage = <<<EOF
    PARAMS
    
    cid: the channel id of the current "tap" that you're trying to get responses from
EOF;
session_start();
require('../config.php');
require('../api.php');
require('../modules/Taps.php');

if($cb_enable)
    $cid = $_GET['cid'];
else 
    $cid = $_POST['cid'];

if($cid){
$chat_obj = new chat_functions();
    $res = $chat_obj->load_response($cid);
    api_json_choose($res,$cb_enable);
} else { 
    api_usage($usage);
}

class chat_functions{

    private $mysqli;
    private $results;

    function __construct(){
        $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
    }

        
    function load_response($cid){
        function filter($elem) {
            return array_intersect_key($elem, array_flip(
                array('chat_time_raw', 'chat_time', 'uname',
                      'small_pic', 'chat_text')));
        }

        $taps = new Taps();
        $responses = $taps->getResponses($cid);
        $responses = array_map('filter', $responses);
        if (count($responses)) {
            return array('success' => 1,'responses' => $responses);
        } else {
            return array('success' => 0,'responses' => null);
        }
    }
};
