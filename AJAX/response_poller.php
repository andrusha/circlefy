<?php
session_start();
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
	$uid = $_SESSION['uid'];
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


	function response_generator($mid){
	$pic_path = PROFILE_PIC_REL;

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
                                        <li class="responses"><img class="response_img" src="{$pic_path}{$pic_36}" /><span class="response_text">{$uname}: {$chat_text}</span></li>
EOF;
                                }
		return $resp_html;	
		}			

}
//END of class

?>
