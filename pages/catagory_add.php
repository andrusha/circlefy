<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class catagory_add extends Base{

	protected $text;
	protected $top;

	function __default(){
	}

	public function __toString(){
		return "Add Category Object";
	}

	function __construct(){

		$this->view_output = "HTML";
		$this->db_type = "mysql";
		$this->page_name = "catagory_add";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();

		$uid = $_SESSION['uid'];
        $symbol = $_GET['channel'];
        
        
        $get_categories_by_group = <<<EOF
        select sc.catagory, g.symbol, sc.scid, g.gid 
        from sub_catagories as sc 
        left join groups as g 
        on sc.gid = g.gid
        where g.symbol='{$symbol}';
EOF;
    
        //echo $get_categories_by_group;
        //$get_categories_by_groupexit;
    
        $get_categories_result = $this->db_class_mysql->db->query($get_categories_by_group);
        $catagory_list = array();
        while ($row = $get_categories_result->fetch_assoc()) {
            $catagory_list[] = array( 
                'catagory' => $row['catagory'], 
                'scid' => $row['scid'],
                'gid' => $row['gid']);
        }
        $this->set($catagory_list ,'my_categories');
        
		//Takes awayfist settings flag
		//setcookie('profile_edit','',time()-360000);
				
	}
	
	
}
?>
