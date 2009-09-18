<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class group_edit extends Base{

	protected $text;
	protected $top;

	function __default(){
	}

	public function __toString(){
		return "Homepage Object";
	}

	function __construct(){

		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->page_name = "new_group_edit";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();

		$uid = $_SESSION['uid'];
		$gid = $_GET['group'];
		
		$this->set($gid,'gid');

	
		$query_admins = <<<EOF
		SELECT t2.admin,t1.pic_36,t1.uname FROM login as t1
		JOIN group_members as t2 ON t1.uid = t2.uid
		WHERE t2.admin IN(1,2,3) AND t2.gid = {$gid}
		ORDER BY admin;
EOF;

                $get_group_info = <<<EOF
                SELECT picture_path, private, invite_priv, invite_only, descr, focus, gname, symbol, pic_100 FROM groups WHERE gid = {$gid}
EOF;


		$this->db_class_mysql->set_query($get_group_info,'get_group_info','This gets all of the basic information about the group ( picture, descr, focus, name, private / invite status )');
		$this->db_class_mysql->set_query($query_admins,'get_admins','This query gets a list of people who last chatted who are in the group ( however this might wnat to be modified tow/ some filters');

		//Execute Each query
		$get_info_result = $this->db_class_mysql->execute_query('get_group_info');
		$get_admins_result = $this->db_class_mysql->execute_query('get_admins');

		$res = $get_info_result->fetch_assoc();
		$group_info_result = array(
			'gname' => $res['gname'],
			'symbol' => $res['symbol'],
			'descr' => $res['descr'],
			'focus' => $res['focus'],
			'picture_path' => $res['picture_path'],
			'private' => $res['private'],
			'invite_priv' => $res['invite_priv'],
			'invite_only' => $res['invite_only'],
			'pic_100' => $res['pic_100']
		);
		
		
		$this->set($group_info_result,'group_info_result');

		if($get_admins_result->num_rows)
		while($res = $get_admins_result->fetch_assoc()){
                $status = $res['admin'];
		$uname = $res['uname'];
		$pic_36 = $res['pic_36'];
		
		
                switch($status){
                        case 1:
                                $status = "Group Owner";
                                break;
                        case 2:
                                $status = "Group Admin";
                                break;
                        default:
                                $status = "";
                                break;
                }

                $admin_html[] = array(
			'pic_36' => $res['pic_36'],
			'uname' => $res['uname'],
			'status' => $status
		);
                
        }
        $this->set($admin_html,'html_admin');
	
			
	}
}
?>
