<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class invite extends Base{

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
		$this->page_name = "invite";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();



		$uid = $_SESSION['uid'];

		$group_list_query = <<<EOF
                        SELECT
                        g.gname,
                        gm.gid
                        FROM group_members AS gm
                        JOIN groups AS g ON g.gid=gm.gid WHERE gm.uid={$uid}
EOF;

	$this->db_class_mysql->set_query($group_list_query,'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                                $groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');
		
			while($res = $groups_you_are_in->fetch_assoc()){

				$gname = $res['gname'];
				$gid = $res['gid'];
				$your_groups[] = array(
				'gid' => $gid,
				'gname' => $gname
				);
			}
			$this->set($your_groups,'groups');
			
				
	}
}
?>
