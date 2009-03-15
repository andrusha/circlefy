<?php
echo "what's up";
echo "test";
require_once('config.php');
//echo "These are the classes that are dynamically loaded:<br/>";

function __autoload($className){
	//echo '-'.$className.'<br/>';
	$file = __FILE__;
	$file = str_replace('/index.php','',$file);
	

	require_once($file.'/modules/'.$className.'.php');
}

$page = $_GET['page'];
if(!$page){
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

$view_data = $newpage->get();

switch($view_data['output']){

	case 'HTML':
	//	var_dump($view_data);
		include_once($file.'/views/'.$newpage->page().'.phtml');	
	break;
	
	case 'JSON':
		echo json_encode($view_data);
	break;
	
	case 'XML':
		require_once($file.'/modules/XML_Serialize.php');
		$xml .= XML_serialize($view_data);
		$xml .= '</root>';
			echo $xml;
	break;

default:
	echo "<div id=\"errror\"><br/>You have not set a proper type of output, please chose either HTML/JSON/XML/REST in /Module/Base.PHP
		  or manually in your page --  <font color=\"red\">Error Message 22</div></font>";
break;
	
}
?>
