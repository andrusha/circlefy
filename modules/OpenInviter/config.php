<?php
	$openinviter_settings=array(
		"username"=>"tasoduv",
		"private_key"=>"79a76cd1e32efcf10be13b0c6f443a0c",
		"cookie_path"=>'/tmp',
		"message_body"=>"tap allows you to do things in life that you cannot currently do without it.  \n\nit's pretty neat, check it out.  tap.info", // tap.info is the website on your account. If wrong, please update your account at OpenInviter.com
		"message_subject"=>" is inviting you to tap.info", // tap.info is the website on your account. If wrong, please update your account at OpenInviter.com
		"transport"=>"curl", //Replace "curl" with "wget" if you would like to use wget instead
		"local_debug"=>"on_error", //Available options: on_error => log only requests containing errors; always => log all requests; false => don`t log anything
		"remote_debug"=>FALSE //When set to TRUE OpenInviter sends debug information to our servers. Set it to FALSE to disable this feature
	);
	?>
