<?php

function getage($datestring, $dateformat){

	if($dateformat=="mdy"){

		$yearofbirth = substr($datestring, 4, 4);
		$monthofbirth = substr($datestring, 0, 2);
		$dayofbirth = substr($datestring, 2, 2);

	} else {

		$yearofbirth = substr($datestring, 4, 4);
		$monthofbirth = substr($datestring, 2, 2);
		$dayofbirth = substr($datestring, 0, 2);
	}	
	
	$currentyear = date(Y);
	$currentmonth = date(m);
	$currentday = date(j);
	
	if($monthofbirth>$currentmonth){
		$age = ($currentyear - $yearofbirth)-1;
	} else {
		$age = ($currentyear - $yearofbirth);
	}	
	
	if($monthofbirth==$currentmonth){
		if($dayofbirth<=$currentday){
			$age = ($currentyear - $yearofbirth);		
		} else {
			$age = ($currentyear - $yearofbirth)-1;		
		}
	}
	return $age;

}
?>