<?php
$debug_redifine = false;
require_once('../config.php');

list($users, $events) = Events::forLastDay();

foreach ($users as $u) {
    $digest = $events[$u->id];
    $digest = $digest->map(function (Tap $tap) { 
        $tap->text = FuncLib::makePreview($tap->text);
        return $tap;
    });

    $messages  = $digest->filterData(function (Tap $tap) { return $tap->type == 0; }); 
    $replies   = $digest->filterData(function (Tap $tap) { return $tap->type == 1; });
    $followers = $digest->filterData(function (Tap $tap) { return $tap->type == 2; });
    
    echo "Sending to {$u->email}\n";
    Mailer::digest($u, $messages, $replies, $followers);
}
