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
			/*(
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
			) ORDER BY connected ASC;*/
                	$search = $this->mysqli->real_escape_string($search);

		
			$full_search = $search;	
			$search = explode(' ',$search);	

			$discard_list = array(
				'help','discussion','chat','support', 'assistance','channel','chatroom','room','topic','description','talk','chatting','chatter',
				'suppor','channe','roo','discuss','discussi','discussio','discus','discu','suppo','supp','cha','chatroo','chatro','chatr','chan',
				'helpchat','helpsupport','helpchannel',
				'-',',','*','!','@','#','##','###','^','(',')','.','?'
			);

			foreach($search as $term){
				if(in_array($term,$discard_list,true) )
					continue;
				$keyterms .= "$term%";
			}
			$terms = "gname LIKE '$keyterms' OR ";
			$search = $search[0];

			$keyword_search = $search;
			$create_assoc_query = <<<EOF
				SELECT t1.favicon as pic_36,t1.descr,t1.gid as id,min(t2.connected) as connected,t2.symbol as symbol,t1.gname AS name,COUNT(t3.uid) AS members,t4.count FROM 
				( SELECT * FROM groups WHERE (gname LIKE '{$full_search}' OR {$terms} symbol LIKE '{$keyterms}%' ) AND connected != 2) AS t1
				LEFT JOIN groups AS t2 ON t2.gid = t1.gid
				LEFT JOIN group_members AS t3 ON t3.gid=t2.gid
				LEFT JOIN GROUP_ONLINE AS t4 ON t4.gid=t2.gid
				GROUP BY t2.gid
				ORDER BY name ASC
			LIMIT 10
EOF;
	//echo $create_assoc_query;
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

			$my_groups = $_SESSION['gid'];
			if($create_assoc_query)	
			$create_assoc_results = $this->mysqli->query($create_assoc_query);
			if($create_assoc_results->num_rows > 0){
				while($res = $create_assoc_results->fetch_assoc() ) {
					$id = $res['id'];
					$name = $res['name'];
					$symbol = $res['symbol'];
					$type = $res['connected'];
					$online = $res['count'];
					$total = $res['members'];
					$descr = substr($res['descr'],0,45);
					$group_img = $res['pic_36'];
					/*if($online == 0){
						$online_class = 'offline';
#						$online = 'offline';
						$online = '';
						$online_count = '';
					} else { 
						$online_class = 'online';
						$online_count = "($online)";
						$online = "online ($online)";
					} */
					$sc = strpos('x,'.$my_groups.',',','.$id.',');	
					if($sc)
						$my_group = 1;
					else
						$my_group = 0;

					$bullet_name = $symbol;

					if($type == 99){
						$type_display = "<img src='images/icons/user_suit.png' /><span class='online $online_class'>".$online."</span>";
						$bullet_display = "<img src='images/icons/user_suit.png' /> $bullet_name"; 
						}
					if($type == 0 || $type == 3){
						$type_display = "<img class='auto-img' src='group_pics/$group_img' /><span class='online $online_class'></span><span class='auto-descr'>$descr...</span>";
						}
					if($type == 2){
						$type_display = "<img src='images/icons/building.png' /><span class='online $online_class'>".$online."</span>";
						$bullet_display = "<img src='images/icons/building.png' /> $bullet_name"; 
						}
					if($type == 1){
						$type_display = "<img src='images/icons/book_open.png' /><span class='online $online_class'>".$online."</span>";
						$type_display = "<img class='auto-img' src='group_pics/$group_img' /><span class='online $online_class'>".$online."</span><span class='auto-descr'>$descr...</span>";
						$bullet_display = "<img src='images/icons/book_open.png' /> $bullet_name"; 
					}

				 	$response[] = array($name,"$name:$symbol:$type:$id:$online:$total:$id",$bullet_display,"$type_display",$my_group);
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
