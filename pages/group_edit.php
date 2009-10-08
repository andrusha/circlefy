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
		
		$this->set($gid,'gid');
		
		$group_random_pick = <<<EOF
                        SELECT t1.gname,t1.gid,t1.focus,t1.descr,t1.pic_36 FROM groups AS t1 WHERE t1.connected = 0 ORDER BY gid DESC LIMIT 5;
EOF;

                        $this->db_class_mysql->set_query($group_random_pick,'get_random_groups',"Getting random 'relevanct' groups");
                        $rand_group_results = $this->db_class_mysql->execute_query('get_random_groups');

                        while($res = $rand_group_results->fetch_assoc()){
                                $gid = $res['gid'];
                                $descr = $res['descr'];
                                $focus = $res['focus'];
                                $pic = $res['pic_36'];
                                $gname = $res['gname'];

                                $random_groups[] = array(
                                        'gid' => $gid,
                                        'gname' => $gname,
                                        'pic' => $pic,
                                        'focus' => $focus,
                                        'descr' => $descr,
                                );
                        }
                        $this->set($random_groups,'random_group_results');
	
		$geo_list_query = <<<EOF
                        SELECT cn.abbr2,cn.name AS Country FROM country_translate AS cn WHERE cn.abbr2 != '-' LIMIT 249;
EOF;


                $this->db_class_mysql->set_query($geo_list_query,'geo_list_query','Populates Geo List');
                $geo_list_results = $this->db_class_mysql->execute_query('geo_list_query');
                while($res = $geo_list_results->fetch_assoc()){
                        $abbr2 = strtolower($res['abbr2']);
                        $name = $res['Country'];

                        $init_geo_data[] = array(
                                'abbr2' =>      $abbr2,
                                'name' =>       $name
                        );
                }
                $this->set($init_geo_data,'init_geo_data');

	
		$gid = $_GET['group'];
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
			'country'=>$res['country'],
			'region'=>$res['region'],
			'state'=>$res['state'],
			'town'=>$res['town'],
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
