<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');
$id_list = $_POST['id_list'];


if(isset($id_list)){
   	$loader_function = new loader_functions();
        $json = $loader_function->loader($id_list);
        echo $json;
}


class loader_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

	function loader($id_list){
		$data = $this->create_loader($id_list);	
		if($data)	
			return json_encode(array('results' => True,'data'=> $data ));
		else
			return json_encode(array('results' => False,'data' => False));
	}



	function create_loader($mid_list){
		$uid = $_SESSION['uid'];
		$group_query_bits_info = <<<EOF
		(SELECT t4.mid,t3.special,UNIX_TIMESTAMP(t3.chat_timestamp) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
		JOIN special_chat as t3
		ON t3.uid = t2.uid
		LEFT JOIN (
		SELECT t4_inner.mid,t4_inner.fuid FROM good AS t4_inner WHERE t4_inner.fuid = {$uid}
		) AS t4
		ON t4.mid = t3.cid
		WHERE t3.mid IN ( {$mid_list} ) ORDER BY t3.cid DESC LIMIT 10)
		UNION ALL
		(SELECT null as mid,t3.special,UNIX_TIMESTAMP(t3.chat_time) AS chat_timestamp,t3.cid,t3.chat_text,t2.uname,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid FROM login AS t2
		JOIN chat as t3 ON t3.uid = t2.uid WHERE t3.cid IN ( {$mid_list} ) ORDER BY t3.cid DESC) ;
EOF;

		$m_results = $this->mysqli->query($group_query_bits_info);
		/* Going to have to think about how to handle responses */
		if($m_results->num_rows)
			while($res = $m_results->fetch_assoc()){
				//Setup
				$mid = $res['mid'];
				$special = $res['special'];
				$chat_timestamp = $res['chat_timestamp'];
				$cid = $res['cid'];    
				$chat_text = $res['chat_text'];
				$uname = $res['uname'];
				$fname = $res['fname'];
				$lname  = $res['lname'];
				$pic_100 = $res['pic_100'];
				$pic_36 = $res['pic_36'];
				$uid = $res['uid'];

				//Process
				$chat_timestamp = $this->time_since($chat_timestamp);
				$chat_timestamp = ($chat_timestamp == "0 minutes") ? "Seconds ago" : $chat_timestamp." ago";
				$chat_text = stripslashes($chat_text);
			
				//Additional
				$rand = rand(1,999);

	
				//Store
				$messages[] = array(
				'mid' => 	  $mid,
				'special'=>       $special,
				'chat_timestamp'=>$chat_timestamp,
				'cid'=>           $cid,
				'chat_text'=>     $chat_text,
				'uname'=>         $uname,
				'fname'=>         $fname,
				'lname'=>         $lname,
				'pic_100'=>       $pic_100,
				'pic_36'=>        $pic_36,
				'uid'=>           $uid
				);
		

			} else { 
				$messages = json_encode("There was no data and there certainly should be.");
			}
		return $messages;
}

	private function time_since($original) {
	    // array of time period chunks
	    $chunks = array(
		array(60 * 60 * 24 * 365 , 'year'),
		array(60 * 60 * 24 * 30 , 'month'),
		array(60 * 60 * 24 * 7, 'week'),
		array(60 * 60 * 24 , 'day'),
		array(60 * 60 , 'hour'),
		array(60 , 'minute'),
	    );

	    $today = time(); /* Current unix time  */
	    $since = $today - $original;

	    // $j saves performing the count function each time around the loop
	    for ($i = 0, $j = count($chunks); $i < $j; $i++) {

		$seconds = $chunks[$i][0];
		$name = $chunks[$i][1];

		// finding the biggest chunk (if the chunk fits, break)
		if (($count = floor($since / $seconds)) != 0) {
		    // DEBUG print "<!-- It's $name -->\n";
		    break;
		}
	    }

	    $print = ($count == 1) ? '1 '.$name : "$count {$name}s";

	    if ($i + 1 < $j) {
		// now getting the second item
		$seconds2 = $chunks[$i + 1][0];
		$name2 = $chunks[$i + 1][1];

		// add second item if it's greater than 0
		if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
		    $print .= ($count2 == 1) ? ', 1 '.$name2 : ", $count2 {$name2}s";
		}
	    }
	    return $print;
	}
}