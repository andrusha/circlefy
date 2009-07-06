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
		$this->page_name = "group_edit";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();

		$uid = $_SESSION['uid'];
		$gid = substr($_SERVER['REQUEST_URI'],-1);

		$this->set($gid,'gid');

	
	if(isset($_FILES['group_image'])){
			$root = ROOT;
			$upload_dir = ".".D_GROUP_PIC_REL; // Directory for file storing
			$web_upload_dir = "./"; // Directory for file storing
  	                 		          // Web-Server dir 
			// testing upload dir 
			// that your upload dir is really writable to PHP scripts
			$tf = $upload_dir.md5(rand()).".test";
			$f = @fopen($tf, "w");
			if ($f == false)
				die("Fatal error! {$upload_dir} is not writable. Set 'chmod 777 {$upload_dir}'or something like this");
			fclose($f);
			unlink($tf);
			// end up upload dir testing 


			if (isset($_POST['fileframe'])){
			    $result = 'ERROR';
			    $result_msg = 'No FILE field found';

			if (isset($_FILES['group_image'])){  // file was send from browser
			        if ($_FILES['group_image']['error'] == UPLOAD_ERR_OK){  // no error
			            $filename = $_FILES['group_image']['name']; // file name
			            move_uploaded_file($_FILES['group_image']['tmp_name'], $upload_dir.'/'.$filename);
					
				$full_file = $upload_dir.'/'.$filename;	
				$hash_filename = md5($gid.'CjaCXo39c0..$@)(c'.$filename);
				$image = $full_file;
				echo 1;
				$im = new imagick( $image );
				$normal = $im->clone();
				$crop = $im->clone();
				$ftype = ".gif";
	
				$pic_180 = '180h_'.$hash_filename.$ftype;
				$normal->thumbnailImage( 180,null );
				$normal->writeImage( $upload_dir.'/'.$pic_180 );

				$pic_100 = '100h_'.$hash_filename.$ftype;
                                //$normal->thumbnailImage( 100,null );
                                $crop->cropThumbnailImage( 60, 60 );
                                $crop->setImagePage(60, 60, 0, 0);
                                $crop->writeImage( $upload_dir.'/'.$pic_100 );

                                $pic_36 = '36wh_'.$hash_filename.$ftype;
                                $crop->cropThumbnailImage( 20, 20 );
                                $crop->writeImage( $upload_dir.'/'.$pic_36 );

				$update_pics_query = <<<EOF
                                        UPDATE groups AS t1
                                        SET
						t1.pic_full = "$filename",
						t1.pic_180 = "$pic_180",
						t1.pic_100 = "$pic_100",
						t1.pic_36 = "$pic_36"
                                        WHERE t1.gid ={$gid}
EOF;
echo $update_pics_query;
				$this->db_class_mysql->set_query($update_pics_query,'update_pics',"Updating the groups picture");
				$edit_profile_results = $this->db_class_mysql->execute_query('update_pics');

			            // main action -- move uploaded file to $upload_dir 
		        	    $result = 'OK';
        			}
       			elseif ($_FILES['group_image']['error'] == UPLOAD_ERR_INI_SIZE)
				$result_msg = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
		        else
				$result_msg = 'Unknown error';
			}
       			// you may add more error checking
		        // see http://www.php.net/manual/en/features.file-upload.errors.php

		    // This is a PHP code outputing Javascript code.
		    // Do not be so confused ;) 
		    echo '<html><head><title>-</title></head><body>test';
		    echo '<script language="JavaScript" type="text/javascript">'."\n";
		    echo 'var parDoc = window.parent.document;';
		    // this code is outputted to IFRAME (embedded frame)
		    // main page is a 'parent'

	        if ($result == 'OK'){
		$pic_path = D_GROUP_PIC_REL;
		$js_output = <<<EOF
	                parDoc.getElementById("upload_status").innerHTML = "picture successfully uploaded"
	                parDoc.getElementById("edit_profile_picture").innerHTML = "<img src='{$root}{$pic_path}{$pic_180}' alt='picture' />"
        	        parDoc.getElementById("pic_format_error").innerHTML = ""
        	        parDoc.getElementById("default_pic_msg").innerHTML = ""
EOF;
	        } else {
        	        $js_output =  'parDoc.getElementById("upload_status").value = "ERROR: '.$result_msg.'";';
	        }

	        echo $js_output;

		    echo "\n".'</script></body></html>';

		    exit(); // do not go futher 
		}
		// FILEFRAME section END
		$filename = $_POST['filename'];
	}
	//End of my if($something)


		$query_admins = <<<EOF
		SELECT t2.admin,t1.pic_36,t1.uname FROM login as t1
		JOIN group_members as t2 ON t1.uid = t2.uid
		WHERE t2.admin IN(1,2,3) AND t2.gid = {$gid}
		ORDER BY admin;
EOF;
		echo $query_admins;

                $get_group_info = <<<EOF
                SELECT picture_path, private, invite_priv, invite_only, descr, focus, gname, pic_180 FROM groups WHERE gid = {$gid}
EOF;
		echo $get_group_info;


		$this->db_class_mysql->set_query($get_group_info,'get_group_info','This gets all of the basic information about the group ( picture, descr, focus, name, private / invite status )');
		$this->db_class_mysql->set_query($query_admins,'get_admins','This query gets a list of people who last chatted who are in the group ( however this might wnat to be modified tow/ some filters');

//Execute Each query
		$get_info_result = $this->db_class_mysql->execute_query('get_group_info');
		$get_admins_result = $this->db_class_mysql->execute_query('get_admins');

		$this->set($get_info_result,'group_info_results');

		while($res = $get_admins_result->fetch_assoc()){
                $status = $res['admin'];
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

		$pic_path = PROFILE_PIC_REL;
                $admin_html[] = <<<EOF
                <li ><img id="group_admins" src="..{$pic_path}{$res['pic_36']}" alt='blank' /> <span class="admin_info">{$res['uname']} - {$status}</span></li>
EOF;
        }
        $this->set($admin_html,'html_admin');
	
			
	}
}
?>
