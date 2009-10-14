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
		'groups'=>true,
		'help'=>true,
		'create_group'=>true,
		'group_edit'=>true,
		'what'=>true,
		'about'=>true,
		'devs'=>true,
		'contact'=>true,
	);

if (isset($allowedPages[$_GET['page']]) && $allowedPages[$_GET['page']]) {

	// Valid page so allow it to be set
	$page = $_GET['page'];

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
		include_once($file.'/views/'.$newpage->page().'.phtml');	
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
