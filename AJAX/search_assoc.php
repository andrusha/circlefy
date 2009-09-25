<?php
session_start();
/* CALLS:
	homepage.phtml
*/
require('../config.php');

$search = $_POST['search'];

if(isset($_POST['search'])){
   	$assoc_function = new assoc_functions();
        $results = $assoc_function->assoc($search);

        echo $results;
}


class assoc_functions{

                private $mysqli;
                private $results;

        function __construct(){
		$this->mysqli =  new mysqli(D_ADDR, D_USER, D_PASS, D_DATABASE);
        }

        function assoc($search){

		$results = array();

		$uid = $_SESSION['uid'];
                $uid = $this->mysqli->real_escape_string($uid);
	
		//Get symbols and search-text seperated		
		$strlen = strlen($search);
		$symbol = substr($search, 0, 1);
		$symbol = '#';
//		$search = substr($search, 1, $strlen--);

		if($search != '' && $symbol == '#'){
			//remove min() to set precedence back for businesses
			$create_assoc_query = <<<EOF
			(
				SELECT t1.gid as id,min(t2.connected) as connected,t2.symbol as symbol,t1.gname AS name,COUNT(t3.uid) AS members,t4.online FROM 
				( SELECT * FROM groups WHERE gname LIKE '{$search}%' OR symbol LIKE '{$search}%' ) AS t1
				LEFT JOIN groups AS t2 ON t2.gid = t1.gid
				LEFT JOIN group_members AS t3 ON t3.gid=t2.gid
				LEFT JOIN GROUP_ONLINE AS t4 ON t4.gid=t2.gid
				GROUP BY t2.gid
			LIMIT 10
			) UNION ALL (
				SELECT uid as id,99 as connected,t2.uname AS symbol,CONCAT(t2.fname,' ',t2.lname) AS name,NULL as members,NULL as online FROM login as t2
                                WHERE t2.uname LIKE '{$search}%'
			LIMIT 5
			) ORDER BY connected ASC;
EOF;

		} 

		if($search != '' && $symbol == '@'){
			$create_assoc_query = <<<EOF
				SELECT t2.uname AS name,t2.fname,t2.lname,t2.pic_100,t2.pic_36,t2.uid,t1.fuid FROM friends as t1
				JOIN login as t2
				ON t2.uid = t1.fuid
				WHERE t1.uid = {$uid} AND t2.uname LIKE '{$search}%'
				GROUP BY t2.uname LIMIT 10;
EOF;

		}

		if($search != '' && $symbol == '*'){
                        $create_assoc_query = <<<EOF
                                SELECT tags AS name FROM rel_settings_query
                                WHERE tags LIKE '%{$search}%'
				GROUP BY tags
                                LIMIT 10;
EOF;

		$create_assoc_results = $this->mysqli->query($create_assoc_query);

                        if($create_assoc_results->num_rows > 0){
                                while($res = $create_assoc_results->fetch_assoc() ) {
					$tags = explode(',',$res['name']);

					$pattern = '/(\w|\s)*('.preg_quote($search).')(\w|\s)*/';
					$tag_final = preg_grep($pattern,$tags);

					foreach($tag_final as $v){
						//Some tags have white spaces in the beggining
						$v2 = trim($v," ");
		                                $results[] = $symbol.$v2;
					}
                                }
			} elseif($search != '') { 
			}
			$results2 = array_unique($results);

			//START This happens because array_unique() changes the array to an associative array which Autompleter does not like
			foreach($results2 as $v)
				$final_results[] .= $v;
			//END 

                        return json_encode($final_results);

                }

			if($create_assoc_query)	
			$create_assoc_results = $this->mysqli->query($create_assoc_query);
			if($create_assoc_results->num_rows > 0){
				while($res = $create_assoc_results->fetch_assoc() ) {
					$id = $res['id'];
					$name = $res['name'];
					$symbol = $res['symbol'];
					$type = $res['connected'];
					$online = $res['online'];
					if($online == 0){
						$online_class = 'offline';
						$online = 'offline';
						$online_count = '';
					} else { 
						$online_class = 'online';
						$online_count = "($online)";
						$online = "online ($online)";
					} 

					$bullet_name = $symbol;

					if($type == 99){
						$type_display = "<img src='images/icons/user_suit.png' /><span class='online $online_class'>".$online."</span>";
						$bullet_display = "<img src='images/icons/user_suit.png' /> $bullet_name $online_count"; 
						}
					if($type == 0){
						$type_display = "<img src='images/icons/group.png' /><span class='online $online_class'>".$online."</span>";
						$bullet_display = "<img src='images/icons/group.png' /> $bullet_name $online_count"; 
						}
					if($type == 2){
						$type_display = "<img src='images/icons/building.png' /><span class='online $online_class'>".$online."</span>";
						$bullet_display = "<img src='images/icons/building.png' /> $bullet_name $online_count"; 
						}
					if($type == 1){
						$type_display = "<img src='images/icons/book_open.png' /><span class='online $online_class'>".$online."</span>";
						$bullet_display = "<img src='images/icons/book_open.png' /> $bullet_name $online_count"; 
					}

				 	$response[] = array($name,"$name:$symbol:$type:$id",$bullet_display,"$name $type_display");
				}
			} elseif($search != '') { 
				$string = trim("<span id='no_results'><b>$symbol$search</b> returned no results
				You must specificy wither you're searching for a Group,Person or Popular Tag.
				Example, #nyu would search for NYU, @dave would search for your friend dave, and *Pizza would do x
				</span>");
				$results = array($string);
			}


			return json_encode($response);
	}

}
