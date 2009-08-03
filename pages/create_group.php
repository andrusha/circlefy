<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class create_group extends Base{

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
		$this->page_name = "group_add";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();

		$uid = $_SESSION['uid'];

		//Match all user IDs with a specific ID, so you will have join the group_members table with the groups table
                //matching the groups_members table with the groups table based off gid, you need to return the name
                        $this->db_class_mysql->set_query('SELECT t2.gname,t1.gid FROM group_members AS t1 JOIN groups AS t2 ON t2.gid=t1.gid WHERE t1.uid='.$uid,
                                        'get_users_groups',"This gets the initial lists of users groups so he can search within his groups");
                                        $groups_you_are_in = $this->db_class_mysql->execute_query('get_users_groups');
					if($groups_you_are_in->num_rows > 0){
                                        $this->set($groups_you_are_in,'your_groups');
					} else {
					$this->set('YOU ARE IN NO GROUPS','your_groups');
					}
	
		if(isset($_POST['submit_create'])){
			//All form settings	
			$gadmin = $uid;
			$gname = addslashes($_POST['gname']);
			$symbol = addslashes($_POST['symbol']);
			$descr = addslashes($_POST['descr']);
			$focus = addslashes($_POST['focus']);
		
			if($_POST['email_suffix'] != ''){
			$email_suffix = addslashes($_POST['email_suffix']);
			} else {
			$email_suffix = 0;
			}
			
			if(isset($_POST['private'])){
			$private = addslashes($_POST['private']);
			} else {
			$private = 0;
			}
			
			if(isset($_POST['invite'])){
			$invite_only = addslashes($_POST['invite']);
			} else { 
			$invite_only = 0;
			}
				
			$invite_priv = $_POST['invite_priv'];

			//Invite Users from another group
			$group_invite = $_POST['group_invite'];

			//The pather where the users picture is stored
			if($_FILES['picture_path'] != ''){
                        $file_name = $gname.'_'.$_FILES['picture_path']['name'];
			$root = ROOT;
                        $new_path = '/htdocs'.$root.'pictures/'.$file_name;

                        move_uploaded_file($_FILES['picture_path']['tmp_name'],$new_path);
                        $new_path = addslashes($new_path);
			echo $new_path;
			} else {
			$picture_path = D_GROUP_PIC_PATH;
			}

			//print_r($_POST);

			$this->db_class_mysql->set_query('
			
			INSERT INTO groups(gname,symbol,gadmin,descr,focus,private,invite_only,email_suffix,picture_path,invite_priv) values("'.$gname.'","'.$symbol.'",'.$gadmin.',"'.$descr.'","'.$focus.'",'.$private.','.$invite_only.','.$email_suffix.',"'.$new_path.'",'.$invite_priv.')
			
			','insert_query','This query insert the new group into the GROUPS table');

			$result = $this->db_class_mysql->execute_query('insert_query');

				//The following lines get lad gid and make association between UID<->GID
				$last_id = "SELECT LAST_INSERT_ID() AS last_id;";
				$this->db_class_mysql->set_query($last_id,'last_gid','This gets the last gid to be inserted into group_members for UID<->GID association');
				$last_id = $this->db_class_mysql->execute_query('last_gid');
				$res = $last_id->fetch_assoc();
				$last_id = $res['last_id'];
				$group_members_query = "INSERT INTO group_members(gid,uid,admin) values({$last_id},{$uid},1);";
				$this->db_class_mysql->set_query($group_members_query,'insert_relation','Inserts into group_members for UID<->GID association');
				$this->db_class_mysql->execute_query('insert_relation');
				$this->page_name = "create_group_finished";
				$this->set($last_id,'group_id');
		}
		$this->set($result,'users');
	}

	function test(){

	}

}
?>
