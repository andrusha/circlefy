<?php
/*
    A little workaround above comet-server
    to unify interface, error-handling, etc
*/
class Comet {
    public static function send($to, array $message) {
        //server name <-> port mapping
        $servers = array('message' => 3333, 
            'user' => 2222, 'admin' => 4444);

        if (!array_key_exists($to, $servers))
            throw new UnknownServerException("There is no server named '$to'");

        if ($to == 'message') {
           if (!array_key_exists('action', $message))
                throw new PacketFormatException("You should specify action in your packet");
        }


        $fp = fsockopen("localhost", $servers[$to], $errno, $errstr, 30);
        if (!$fp) {
            throw new SocketException("Socket error $errno: $errstr");
        }

        fwrite($fp, json_encode($message)."\r\n");
        fclose($fp);
    }
};
