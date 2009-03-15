<?php
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); 
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" ); 
header("Cache-Control: no-cache, must-revalidate" ); 
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");

	$number = rand(0,28);
	echo '<?xml version="1.0" ?><root>';
	echo '<status>'.$number.'</status>';
	echo '</root>';

?>
