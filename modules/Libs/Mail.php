<?php

/*
    Some basic mail-notification workaround,
    collections of functions actually
*/
abstract class Mail {/*
TODO:

			$to = $friend_info['email'];
            $subject = "{$uname} now has you on tap.";
            $from = "From: tap.info\r\n";
            $body = <<<EOF
{$uname} now has you on tap and will receive anyting you say!  Say something awesome!

-Team Tap
http://tap.info
EOF;

			//Checks the person getting tracked settings before sending them an email
			$email_check_query = "SELECT uid FROM settings WHERE track = 1 AND uid = {$fid}";

            if($this->mysqli->query($email_check_query)->num_rows)	
                mail($to,$subject,$body,$from);
*/


    function send_welcome_mail(){
		$subject = "Welcome to tap!";
		$from = "From: tap.info\r\n";
		$body = <<<EOF
     Welcome to tap.info , with tap you'll be able to stay connected with people and information
you're interested in.  tap also allows you to 'tap' into specific channels of people by sending a message
to that channel.  For example, if you want to send a message to everyone at Python, simply find the Python
channel via the autocompleter and people at Python will see that show up in their outside messages
tab.  There's many applications and uses for tap, espcially when it comes to community management, so
feel free to go wild using it!  Happy tapping!

-Team Tap
http://tap.info
EOF;
		$mail_val = mail($this->email,$subject,$body,$from);
	}
	private function notify_user($init_tapper,$msg,$uname,$cid,$email){
		if($first || true){
			//This query can be moved to client-side once real-time presence of users is taken care of
			$user_online_query = <<<EOF
			SELECT uid FROM TEMP_ONLINE WHERE uid = {$init_tapper} AND online = 1
EOF;
			$user_online_reults = $this->mysqli->query($user_online_query);
			if(!$user_online_reults->num_rows || true){
				$user_settings_query = <<<EOF
				SELECT s.email_on_response,l.email FROM settings AS s 
				JOIN login AS l ON s.uid = l.uid 
				WHERE s.uid = {$init_tapper} AND s.email_on_response = 1
EOF;
				$user_settings_results = $this->mysqli->query($user_settings_query);
				if($user_settings_results->num_rows){
					$res = $user_settings_results->fetch_assoc();
					$email = $res['email'];
					$this->notify_user($init_tapper,$msg,$uname,$cid,$email);	
				}
			}
		}

		$update_noftify_query = "UPDATE notifications SET email_on_response = NOW() WHERE email_on_response < SUBTIME(NOW(),'0:01:00') AND uid = {$init_tapper}";
		$this->mysqli->query($update_noftify_query);
		if($this->mysqli->affected_rows){
				$to = $email;
				$subject = "{$uname} has replied to your tap.";
					$from = "From: tap.info\r\n";
					$body = <<<EOF
{$uname} has responded to your tap with the following:

{$uname}: {$msg}

You can respond back in real-time at http://tap.info/tap/{$cid}

-Team Tap
http://tap.info
EOF;
					mail($to,$subject,$body,$from);
			return True;
		} else { 
			return False;
		}
	}
    
};
