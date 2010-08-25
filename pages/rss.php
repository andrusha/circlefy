<?php

class rss extends Base{

	protected $text;
	protected $top;
	
	function __default(){
	}
	
	public function __toString(){
		return "Public User Object";
	}
	
	function __construct(){
				
		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->need_login = 1;
		$this->need_db = 1;
		$this->need_filter = 1;
		$this->input_debug_flag = 0;
		$this->page_name = "rss";
	
		parent::__construct();
		
		
		$symbol = $_GET['symbol'];


//START get gid
$get_gid_query = <<<EOF
	SELECT GO.count,g.descr,g.gid,g.favicon,g.pic_100,g.gname,g.symbol,g.topbg,g.connected FROM groups AS g
	JOIN GROUP_ONLINE AS GO
	ON GO.gid = g.gid
	WHERE g.symbol = '{$symbol}' LIMIT 1;
EOF;
$this->db_class_mysql->set_query($get_gid_query,'get_gid',"This query gets a specific gid for the public group");
$gid_result = $this->db_class_mysql->execute_query('get_gid');

if(!$gid_result->num_rows)
	header( 'Location: http://tap.info?error=no_public_group' );

$res = $gid_result->fetch_assoc();
$gid = $res['gid'];
$gname = $res['gname'];
$type = $res['connected'];
$symbol = $res['symbol'];
$online_count = $res['count'];
$descr = $res['descr'];
$pic_100 = $res['pic_100'];
$favicon = $res['favicon'];
$topbg = $res['topbg'];

//Part of group?
$sc =  strpos('x,'.$_SESSION['gid'].',',','.$gid.',');
$joined = $sc ? True : False;
$descr = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]","<a href=\"\\0\" target=\"_blank\">\\0</a>", $descr);

$this->set($joined,'joined');
$this->set($pic_100,'group_pic_med');
$this->set($favicon,'favicon');
$this->set($gname,'gname');
$this->set($symbol,'symbol');
$this->set($type,'type');
$this->set($topbg,'topbg');
$this->set($gid,'gid');
$this->set($descr,'descr');
$this->set($online_count,'online_count');
if(!$gid)
	return False;
//END get gid

$get_member_count = <<<EOF
	SELECT COUNT(gm.uid) AS total_count FROM group_members AS gm WHERE gm.gid = {$gid}
EOF;


$this->db_class_mysql->set_query($get_member_count,'get_member_count',"This query gets a specific gid for the public group");
$count_results = $this->db_class_mysql->execute_query('get_member_count');
$res = $count_results->fetch_assoc();
$total_count = $res['total_count'];
$this->set($total_count,'total_count');

//START get admin list
$admin_list_query = <<<EOF
SELECT gm.admin,l.uname,l.pic_36,gm.uid FROM group_members AS gm 
JOIN login AS l
ON l.uid = gm.uid
WHERE gm.gid = {$gid} AND admin <> 0;
EOF;

$this->db_class_mysql->set_query($admin_list_query,'admin_list',"This gets the list of admins for a specific group");
$admin_list_results = $this->db_class_mysql->execute_query('admin_list');

$user_admin = false;
while($res = $admin_list_results->fetch_assoc() ){
	$uname = $res['uname'];
	$admin_uid = $res['uid'];
	$type = $res['admin'];
	$pic_36 = $res['pic_36'];

	switch($type){
		case 1:$type = 'Founder';break;
		case 2:$type = 'Admin';break;
	}
	
	$admin_data[] = array(
		'uname'	=> $uname,
		'small_pic' => $pic_36,
		'type' => $type
	);
	if($admin_uid == $_SESSION['uid'])
		$user_admin = true;
}
$this->set($admin_data,'admins');
$this->set($user_admin,'enable_admin');

$taps = new Taps();
if (!empty($symbol)) {
    $params = array('#outside#' => '1, 2', '#gid#' => $gid);
    $data_taps = $taps->getFiltered('ind_group', $params);
} else {
    $data_taps = $taps->getFiltered('public', array('#outside#' => '1, 2'));
}
$this->set($data_taps,'user_bits');

//START member count
$count_group_member_query = "SELECT COUNT(uid) AS member_count FROM group_members WHERE gid = {$gid}";
$this->db_class_mysql->set_query($count_group_member_query,'member_count',"This query gets a group member count");
$count_member_result = $this->db_class_mysql->execute_query('member_count');
$res = $count_member_result->fetch_assoc();
$member_count = $res['member_count'];
$this->set($member_count,'member_count');
//END member count

//START most popular members
$popular_members_query = <<<EOF
SELECT l.uname,l.pic_36,COUNT(sc.mid) as count FROM special_chat AS sc
JOIN special_chat_meta AS scm ON sc.mid = scm.mid
JOIN login AS l ON l.uid = sc.uid
WHERE scm.gid = {$gid}
GROUP BY sc.uid ORDER BY count DESC LIMIT 9;
EOF;
$this->db_class_mysql->set_query($popular_members_query,'popular_members',"This query gets a groups active members");
$popular_members_results = $this->db_class_mysql->execute_query('popular_members');
while($res = $popular_members_results->fetch_assoc()){
	$member = $res['uname'];
	$pic_36 = $res['pic_36'];
	$count = $res['count'];

	$popular_members_data[] = array(
	'member' => $member,
	'small_pic' => $pic_36,
	'count' => $count
	);
}
	$this->set($popular_members_data,'popular_members');
//END most popular members

//START get popular taps
$popular_taps_query = <<<EOF
SELECT sc.chat_text,COUNT(c.cid) AS count,scm.mid FROM chat AS c
JOIN special_chat_meta AS scm ON scm.mid = c.cid
JOIN special_chat AS sc ON sc.mid = scm.mid
WHERE scm.gid = {$gid}
GROUP BY c.cid ORDER BY count DESC LIMIT 5;
EOF;

$this->db_class_mysql->set_query($popular_taps_query,'popular_taps',"This query gets a groups popular taps");
$popular_taps_results = $this->db_class_mysql->execute_query('popular_taps');
if($popular_taps_results->num_rows)
while($res = $popular_taps_results->fetch_assoc()){
        $tap = $res['chat_text'];
	$cid = $res['mid'];
        $count = $res['count'];
	
	$tap = stripslashes($tap);

        $popular_taps_data[] = array(
        'tap' => $tap,
        'cid' => $cid,
        'count' => $count
        );
}
        $this->set($popular_taps_data,'popular_taps');
//END get popular taps

//START set the session uid for Orbited
$this->set($_SESSION['uid'],'pcid');
//END set the session uid for Orbited



}

}
?>
