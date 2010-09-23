<?php

require_once('config.php');
//echo "These are the classes that are dynamically loaded:<br/>";

$allowedPages = array(
		'catagory_add'=>true,
		'profile'=>true,
		'invite'=>true,
		'channels'=>true,
		'pending_members'=>true,
		'help'=>true,
		'rss'=>true,
		'create_channel'=>true,
		'channel_edit'=>true,
		'what'=>true,
		'login'=>true,
		'password_recovery'=>true,
		'signup'=>true,
		'about'=>true,
		'devs'=>true,
		'contact'=>true,
		'channel'=>true,
		'company'=>true,
		'school'=>true,
		'tap'=>true,
		'search_people'=>true,
		'people_search'=>true,
		'people'=>true,
		'settings'=>true,
		'confirm'=>true,
		'user'=>true,
        'fb'=>true
	);

if (isset($allowedPages[$_GET['page']]) && $allowedPages[$_GET['page']]) {
	// Valid page so allow it to be set
	$page = $_GET['page'];
	if ($page == 'user')
		$page = 'public_user';
	if ($page == 'channel' || $page == 'school' || $page == 'company')
		$page = 'public_group';
    if ($page == 'channels')
		$page = 'groups';
    if ($page == 'create_channel')
		$page = 'create_group';
    if ($page == 'channel_edit')
		$page = 'group_edit';
	if ($page == 'tap')
		$page = 'public_tap';
	if ($page == 'settings')
		$page = $_GET['type'] == 'notifications' ? 'settings' : 'profile';
} else {

	// Invalid page so default to homepage
	$page = 'homepage';

}

require_once(BASE_PATH.'/pages/'.$page.'.php');
$newpage = new $page();
