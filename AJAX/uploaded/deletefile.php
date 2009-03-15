<?php
/**
 * This file uploads a file in the back end, without refreshing the page
 *  
 */
$homepage="index.php";
if (isset($_GET['filename'])) {	
	if (unlink($_GET['filename'])) {
		header("Location: $homepage");
	}
	else {
		echo "<script type='text/javascript'> alert('Failed to delete: ".$_GET['filename'].". Please try again.');</script>";
	}
}
else {
	echo "File deleted";
}
?>