<?php
/* CALLS:
    feed.js
*/
$cid   = intval($_POST['cid']);
$uid   = intval($_POST['uid']);
$uname = $_POST['uname']; 

Comet::send('action' => 'response.typing', 'data' => 
    array('cid' => $cid, 'uid' => $uid, 'uname' => $uname)));
echo 1;
