<?php
/* CALLS:
    feed.js
*/
$cid   = intval($_POST['cid']);
$uid   = intval($_POST['uid']);
$uname = $_POST['uname']; 

Comet::send('message', array('cid' => $cid, 'action' => 'response.typing', 'data' => array('uid' => $uid, 'uname' => $uname)));
echo 1;
