<?php
require_once('config.php');
//echo "These are the classes that are dynamically loaded:<br/>";

function __autoload($className){
	//echo '-'.$className.'<br/>';
	$file = __FILE__;
	$file = str_replace('/index.php','',$file);
	

	require_once($file.'/modules/'.$className.'.php');
}

$allowedPages = array(
		'profile'=>true,
		'invite'=>true,
		'groups'=>true,
		'help'=>true,
		'create_group'=>true,
		'group_edit'=>true,
		'what'=>true,
		'about'=>true,
		'devs'=>true,
		'contact'=>true,
		'group'=>true,
		'company'=>true,
		'school'=>true,
		'tap'=>true,
		'search_people'=>true,
		'people_search'=>true,
		'people'=>true,
		'settings'=>true,
		'confirm'=>true,
		'user'=>true
	);

if (isset($allowedPages[$_GET['page']]) && $allowedPages[$_GET['page']]) {
	// Valid page so allow it to be set
	$page = $_GET['page'];
	if ($page == 'user')
		$page = 'public_user';
	if ($page == 'group' || $page == 'school' || $page == 'company')
		$page = 'public_group';
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
