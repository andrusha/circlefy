<?php
require('../config.php');


$chat_obj = new chat_functions; //might want to shield this so if people hammer this object does not instancieate
if($_POST['responder']){
	$last_mid = $_POST['last_mid'];
	$json =  json_decode(stripslashes($_POST['json']));
	$channel_id_list = implode(',',$json);
	$results = $chat_obj->check_new_msg($last_mid,$channel_id_list,$new_mid);
}
$offsets =$_POST['offsets'];
if($_POST['bit_grabber']){
	$uid = $_COOKIE['uid'];
	$results = $chat_obj->check_new_bits($offsets,$uid);
}
		echo $results;

class chat_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function check_new_msg($last_mid,$channel_id_list,$new_mid){
		$check_msg_query = "SELECT mid FROM chat WHERE cid IN ({$channel_id_list}) and mid > {$last_mid}";
		$check_msg_results = $this->mysqli->query($check_msg_query);


		if($check_msg_results->num_rows >=1 ){
			while($res = $check_msg_results->fetch_assoc() ){
				$mids[] = $res['mid'];
				$new_mid = $res['mid'];
			}
			$mid_list = implode(',',$mids);
			$html_responses = $this->response_generator($mid_list);

			$results = array('results' => 'true','new_mid' => $new_mid, 'data' => $html_responses);
			//$results = array('uname' => $results['uname'],'msg' => $results['chat_text'],'mid' => $new_mid);
		} else { 
			$results = array('results' => 'false','new_mid' => $last_mid);
		}
		
		$results = json_encode($results);
		return $results;
	}

	function check_new_bits($offsets,$uid){
		//START This will allow us to dynamically determine which file is in use and delelete the most used file via batch job most likely
		//This is why sliced flat tables are great
		$active_slices = count($offsets);
		//END

/*		foreach($slices as $slice){
			if($slice > 0)
				$active_slices[] .= $slice;
		}

*/
		for($i = 0;$i < $active_slices;$i++){
			$file = "/var/data/flat/flat_$i";
			$filesizes[$i] = filesize($file);
			$fp = fopen($file, "r");
			flock($fp,LOCK_NB|LOCK_EX);
			$pos = $offsets[$i];//echo $pos.'XXXX';
				if($pos > 0){
					fseek($fp,$pos);
					while (!feof($fp)) {
					        $contents .= fgets($fp, 4096);
					}fclose($fp);
//					echo $contents;
				} else { 
					$contents=null;continue;
				}
				$exp_content = explode("\n",$contents);
				foreach($exp_content as $v){
					$v2=explode(' ',$v);
					if($v2[0] == $uid)
						$matches[] .= $v;
				}
		}
//			print_r($matches);	
			//Process all matches, get their association and run queries against them
			/*
			FORMAT FOR TABLES ARE AS FOLLOWS:
			uid     cid      gid    rid	fuid	type

			types: group = 0 , friends = 1, direct = 2, filters = 3, fuid = 4, type = 5

			NOTE: fuid is the person sending the message, uid is the person recieving it
			*/
			$groups = 'groups';	
			$friends = 'friends';	
			$rel = 'rel';	
			$group_hit=0;$friend_hit=0;$rel_hit=0;
			if(is_array($matches)){
				foreach($matches as $row){
					$row_count++;
					$col = explode(' ',$row);
						$uid = $col[0];
						$cid = $col[1];
						$gid = $col[2];
						$rid = $col[3];
						$fuid =$col[4]; 
						$type =$col[5];
		
					//Group Processing
					if($col[5] == 0){
						if($group_hit != 0){ $groups=false;}
						$type = array("$groups","group_{$gid}");$group_hit++;}
						
					//Friend Processing
					if($col[5] == 1){
						if($friend_hit != 0) $friends=false;
						$type = array("$friends","friend_{$fuid}");$friend_hit++;}

					//Direct Processing
					if($col[5] == 2){
						$type = array("direct");}

					//Filter Processing
					if($col[5] == 3){
						if($rel_hit != 0) $rel=false;	
						$type = array("$rel","tab_rel_{$rid}");$rel_hit++;}

					$res[$row_count]  = $this->bit_generator($uid,$gid,$cid,$rid,$fuid,$type);
					
				}
//					print_r($res);
					return json_encode(array( 'new_offsets' => $filesizes, 'data' => $res,'results'=>true));
					
			} else {
					return json_encode(array('new_offsets' => $filesizes,'results'=>false)); //This is used to demark the last point checked so you can resume a tyour last checked point
			}
		}


	private  function bit_generator($uid,$gid=0,$cid,$rid=0,$fuid=0,$types){


				//START Retriev MySQL Object that is in memory
				$memcache = new Memcache;
                		$memcache->connect('127.0.0.1', 11211) or die ("Could not connect");
				$res = $memcache->get($cid);
					$rand = rand(1,999);
					$uname = $res['uname'];
					$fname = $res['fname'];
					$lname = $res['lname'];
					$pic_100 = $res['pic_100'];
					$chat_text = $res['chat_text'];
				//END
                                $chat_timestamp = "Now!";
                                $pic_36 = md5($uid);
                                $chat_text = stripslashes($chat_text);
                                $color_class = "blue";
				$good = <<<EOF
				<li class="0" id="good_{$cid}_{$type}" onclick="good(this,'{$cid}','{$uid}','{$_SESSION['uid']}','{$type}');"><img src="images/icons/thumb_up.png" /> <span class="bits_lists_options_text"> Good </span></li>
EOF;

				foreach($types as $type) {
				if($type == false)continue;

				$final_html[$type] .= <<<EOF
				<div id="super_bit_{$cid}_{$type}_{$rand}">
				<div class="bit {$color_class} {$cid}_bit" id="bit_{$cid}_{$type}_{$rand}">

					<span class="bit_img_container"><img class="bit_img" src="pictures/{$pic_100}" /></span>
					<span class="bit_text">
						<a href="profile">{$uname}</a>: {$chat_text}
					</span>
					<span class="bit_timestamp"><i>{$chat_timestamp}</i></span>
					<ul class="bits_lists_options">
						{$good}
						<li id="toggle_show_response_button" class="0" onclick="toggle_show_response('responses_{$cid}_{$type}_{$rand}',this,1)"><img src="images/icons/text_align_left.png" /> <span class="bits_lists_options_text">View Replies </span></li>
						<li class="0" onclick="toggle_show_response('respond_{$cid}_{$type}_{$rand}',this,0); toggle_show_response('responses_{$cid}_{$type}_{$rand}',document.getElementById('toggle_show_response_button'),0);"><img src="images/icons/comment.png" /> <span class="bits_lists_options_text">Respond </span></li>
					</ul>

				</div>

				<div class="respond_text_area_div" id="respond_{$cid}_{$type}_{$rand}">
				<ul>
					<li><textarea class="textarea_response gray_text" id="textarea_response_{$cid}" onfocus="if (this.className[this.className.length-1] != '1') vanish_text('textarea_response',this);">Response..</textarea></li>
					<li><button>Send</button></li>
				</ul>

				</div>

					<ul class="bit_responses {$cid}_resp" id="responses_{$cid}_{$type}_{$rand}">
EOF;

				}

        return $final_html;
                                }



	function response_generator($mid){

	//This should actually be grabbed from a cache via self caching
	$query = <<<EOF
			SELECT t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
                        JOIN chat as t3
                        ON t3.uid = t2.uid
                        WHERE t3.mid IN (  {$mid} )
			LIMIT 20;
EOF;

        $bit_gen_results = $this->mysqli->query($query);

                        $num_rows = $bit_gen_results->num_rows;
                        while($res = $bit_gen_results->fetch_assoc() ){
                             //   $chat_midstamp = $this->mid_since($res['chat_midstamp']);
                             // $chat_midstamp = ($chat_midstamp == "0 minutes") ? "Seconds ago" : $chat_midstamp." ago";
                                $pic_36 = $res['pic_36'];
                                $uid = $res['uid'];
                                $chat_text = stripslashes($res['chat_text']);
                                $cid = $res['cid'];
                                $uname = $res['uname'];
                                $fname = $res['fname'];
                                $lname = $res['lname'];
                                $pic_100 = $res['pic_100'];

                                        $resp_html[$cid][] .= <<<EOF
                                        <li class="responses"><img class="response_img" src="pictures/{$pic_36}" /><span class="response_text">{$uname}: {$chat_text}</span></li>
EOF;
                                }
		return $resp_html;	
		}			

}
//END of class

?>
