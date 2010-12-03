<?php
$debug_redifine = false;
require_once(dirname(__FILE__).'/../config.php');

Mailer::sendQueue();
