<?php
/*
    A little workaround above comet-server
    to unify interface, error-handling, etc
*/
class Comet {
    public static function send($action, $data) {
        //send message to event server
        $fp = fsockopen("localhost", 3334, $errno, $errstr, 30);
        if (!$fp)
            throw new SocketException("Socket error $errno: $errstr");

        fwrite($fp, json_encode(array('action' => $action, 'data' => $data))."\n");
        fclose($fp);
    }
};
