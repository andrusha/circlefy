<?php

//get the session and the api module call
$session = $_SESSION['uid'];
$find_api_module = $_SERVER['REQUEST_URI'];
$find_api_module = explode('/', $find_api_module);
$find_api_module = explode('.', $find_api_module[2]);
$api_module = $find_api_module[0];
//$api_module = substr($find_api_module[2],0,-4);

$allowedModules = array(
    //These are modules which can be accessed without a session
    'group_status' => false,
    'irc' => false,
    'ajaz_sign_up' => false,
    'response_poller' => false,
    'group_userlist' => false,
    'group_search' => false,
    'loader' => false,
    'filter_creator' => false,
    'load_responses' => false,
    'query_assoc' => false,
    'check_signup' => false,
    'search_assoc' => false,
    'group_create' => false,
    'password_recovery' => false,
    'new_message_handler' => false,

	//These are modules which must have a session to be accessed
	'ajaz_new_sign_up' => true,
    'connected_group_add' => true,
    'group_check' => true,
    'add_active' => true,
    'group_update' => true,
    'profile' => true,
    'join_group' => true,
    'request_join_group' => true,
    'accept_member' => true,
    'remove_active' => true,
    'admin_change' => true,
    'leave_group' => true,
    'respond' => true,
    'edit_group' => true,
    'group_favicon_upload' => true,
    'import_contacts' => true,
    'link-irc' => true,
    'password_profile' => true,
    'block_group' => true,
    'edit_profile' => true,
    'group_picture_upload' => true,
    'ind_invite' => true,
    'profile_picture_upload' => true,
    'block_user' => true,
	'group_rel' => true,
	'group_mod' => true,
    'invite_endpoint' => true,
    'track' => true,
    'geo' => true,
    'irc-link' => true,
    'message_handler' => true,
    'typing' => true,
    'good' => true,
    'rel_settings' => true,
    'add_catagory' => true,
	'remove_catagory' => true
);

//Check if user has a session
if ($session)
    $session = true;
else
    $session = false;

if ($allowedModules[$api_module] && isset($allowedModules[$api_module])) {
    //Make sure if the page requires a session, he's logged in
    if ($session) {
        $module_access_status = true;
    } else {
        $module_access_status = false;
        $access_error_msg = "Module Access: YOU NEED A SESSION TO ACCESS THIS PAGE";
    }
} else {
    //If the page doesn't require login, atleast make sure it's a valid api request
    if (isset($allowedModules[$api_module])) {
        $module_access_status = true;
    } else {
        $module_access_status = false;
        $access_error_msg = "Module Access: INVALID MODULE";
    }
}

$_GET['api'] = 'true';
if ($_GET['api'] == 'true') {
    //Get core information about the client using the API
    $domain = $_SERVER['HTTP_HOST'];

    //NEED TO REMOVE THIS COMPLETELY
    $domain = 'tap.info';
    //List of allowed domains that can access the tap api
    $allowedDomains = array(
        'tap.info' => true
    );

    if ($allowedDomains[$domain] && isset($allowedDomains[$domain])) {
        $api_access_status = true;
    } else {
        $api_access_status = false;
        $access_error_msg = 'API Access: YOU ARE NOT AUTHORIZED TO USE THE TAP API';
    }


    //If callback is set, set the JSONP callback
    $cb = $_GET['cb'];
    if ($cb && isset($cb)) {
        $cb_enable = true;
    } else {
        $cb_enable = false;
    }
}

if (!$module_access_status || !$api_access_status) {
    echo json_encode(array('msg' => $access_error_msg));
    exit();
}

function api_usage($usage) {
    $usage = json_encode($usage);
    echo $usage;
}

function api_json_choose($res, $cb_enable) {
    $cb = $_GET['cb'];
    $cb_S = $cb . '(';
    $cb_E = ');';

    if ($cb_enable)
        echo $cb_S . json_encode($res) . $cb_E;
    else
        echo json_encode($res);
}


?>
