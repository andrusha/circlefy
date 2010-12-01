<?php
$debug_redifine = false;
require_once('../config.php');

Mailer::sendQueue();
