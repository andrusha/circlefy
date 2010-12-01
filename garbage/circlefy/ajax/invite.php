<?php

$email = $_POST['email'];

$db = new mysqli('localhost', 'root', 'root', 'circlefy');
$query = "INSERT INTO invites (email) VALUES ('".$db->real_escape_string($email)."')";
$db->query($query);

if ($db->affected_rows == 1)
    echo 1;
else
    echo 0;
