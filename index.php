<?php

require_once('config.php');
require_once('modules/autoCreateUser.php');
//echo "These are the classes that are dynamically loaded:<br/>";


function __autoload($className){
	$file = __FILE__;
	$file = str_replace('/index.php','',$file);
	require_once($file.'/modules/'.$className.'.php');
}

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

$event = $_GET['event'];
if(!$event){

	$event = '__default';

}

//echo $file.'/pages/'.$page.'.php';

	
$file = __FILE__;
$file = str_replace('/index.php','/',$file);

require_once($file.'/pages/'.$page.'.php');
$newpage = new $page();
$newpage->$event();

$_t = $newpage->get();

switch($_t['output']){
	case 'HTML':
	//	var_dump($_t);
		$view_page = $newpage->page();
		if(!$view_page){
			print 'You forgot to set $this->page_name in your pages/xxxx.php file, please do this in order to set the proper view template';
			exit();
		}
        echo "<!-- View Page: " . $view_page . "-->";
        
        //echo "<!--" . print_r($_SESSION, true)  . "-->";
		include_once($file.'/views/'.$view_page.'.phtml');	
	break;
	
	case 'JSON':
		echo json_encode($_t);
	break;
	
	case 'XML':
		require_once($file.'/modules/XML_Serialize.php');
		$xml .= XML_serialize($_t);
		$xml .= '</root>';
			echo $xml;
	break;
default:
	echo "<div id=\"errror\"><br/>You have not set a proper type of output, please chose either HTML/JSON/XML/REST in /Module/Base.PHP
		  or manually in your page --  <font color=\"red\">Error Message 22</div></font>";
break;
	
}
?>