<?php
// !!!!!!!!!!!  MAKE SURE YOU CHANGE THE CLASS NAME FOR EACH NEW CLASS !!!!!!!
class profile_edit extends Base{

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
		$this->page_name = "profile_edit";
		$this->need_login = 1;
		$this->need_db = 1;

		parent::__construct();

		$uid = $_SESSION['uid'];

	
	if(isset($_FILES['image_of_you'])){

			$root = ROOT;
			$upload_dir 		= PROFILE_PIC_PATH;		// Real path
			$web_upload_dir   	= PROFILE_PIC_REL;		// Web Root relative path

			// testing upload dir 
			// that your upload dir is really writable to PHP scripts
			$tf = $upload_dir.'/'.md5(rand()).".test";
			$f = @fopen($tf, "w");
			if ($f == false)
				die("Fatal error! {$upload_dir} is not writable. Set 'chmod 777 {$upload_dir}'or something like this");
			fclose($f);
			unlink($tf);
			// end up upload dir testing 

			// FILEFRAME section of the script
			if (isset($_POST['fileframe'])){
			    $result = 'ERROR';
			    $result_msg = 'No FILE field found';

			if (isset($_FILES['image_of_you'])){  // file was send from browser
			        if ($_FILES['image_of_you']['error'] == UPLOAD_ERR_OK){  // no error
			            $filename = $_FILES['image_of_you']['name']; // file name
			            move_uploaded_file($_FILES['image_of_you']['tmp_name'], $upload_dir.'/'.$filename);
					
				$full_file = $upload_dir.'/'.$filename;	
				$hash_filename = md5($uid.'CjaCXo39c0..$@)(c'.$filename);
				$image = $full_file;
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
                                        UPDATE login AS t1
                                        SET
						t1.pic_full = "$filename",
						t1.pic_180 = "$pic_180",
						t1.pic_100 = "$pic_100",
						t1.pic_36 = "$pic_36"
                                        WHERE t1.uid ={$uid}
EOF;
//echo $update_pics_query;
				$this->db_class_mysql->set_query($update_pics_query,'update_pics',"Updating a users pictures");
				$edit_profile_results = $this->db_class_mysql->execute_query('update_pics');

			            // main action -- move uploaded file to $upload_dir 
		        	    $result = 'OK';
        			}
       			elseif ($_FILES['image_of_you']['error'] == UPLOAD_ERR_INI_SIZE)
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
		$js_output = <<<EOF
	                parDoc.getElementById("upload_status").innerHTML = "Uploaded!"
	                parDoc.getElementById("edit_profile_picture").innerHTML = "<img src='{$web_upload_dir}/{$filename}' alt='Your picture' />"
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
		
		//age combination
		$month = $_POST['dob_month'];
		$day = $_POST['dob_day'];
		$year = $_POST['dob_year'];
		$dob = $year.'-'.$month.'-'.$day;
		//end age combination
		
		if(isset($_POST['aim_enable']) && strlen($_POST['aim']) > 2){
			$aim_enabled = 'enabled';
		} else {
			$aim_enabled = 'disabled';
		}
		
		if(isset($_POST['msn_enable']) && strlen($_POST['msn']) > 2){
			$msn_enabled = 'enabled';
		} else {
			$ms_enabled = 'disabled';
		}

		if(isset($_POST['yahoo_enable']) && strlen($_POST['yahoo']) > 3){
			$yahoo_enabled = 'enabled';
		} else {
			$yahoo_enabled = 'disabled';
		}
		
		if(isset($_POST['gtalk_enable']) && strlen($_POST['gtalk']) > 3){
			$gtalk_enabled = 'enabled';
		} else {
			$gtalk_enabled = 'disabled';
		}
		
		if(isset($_POST['irc_enable']) && strlen($_POST['irc']) > 1){
			$irc_enabled = 'enabled';
		} else {
			$irc_enabled = 'disabled';
		}
		
		if(isset($_POST['icq_enable']) && strlen($_POST['icq']) > 3){
			$icq_enabled = 'enabled';
		} else {
			$icq_enabled = 'disabled';
		}
				
				if($_POST['edit_submit_alerts']) {
					$this->db_class_mysql->set_query('UPDATE screen_names AS t1
					 JOIN enabled_screen_names AS t2
					 ON t1.uid = t2.uid
					 SET
						 t1.yahoo="'.$_POST['yahoo'].'",
						 t1.msn="'.$_POST['msn'].'",
						 t1.aim="'.$_POST['aim'].'",
						 t1.icq="'.$_POST['icq'].'",
						 t1.gtalk="'.$_POST['gtalk'].'",
						 t1.irc="'.$_POST['irc'].'",
						 t2.eyahoo="'.$yahoo_enabled.'",
						 t2.emsn="'.$msn_enabled.'",
						 t2.eaim="'.$aim_enabled.'",
						 t2.eicq="'.$icq_enabled.'",
						 t2.egtalk="'.$gtalk_enabled.'",
						 t2.eirc="'.$irc_enabled.'"
					 WHERE t1.uid ='.$uid,
					 'update_profile',"Updating a users alert settings on his profile");
					$edit_profile_results = $this->db_class_mysql->execute_query('update_profile');	
				}
				
				
				if($_POST['edit_submit_you']){
					$this->db_class_mysql->set_query('
					UPDATE display_rel_profile AS t1
					JOIN login AS t2
					ON t1.uid = t2.uid
					JOIN profile AS t3
					ON t1.uid = t3.uid
					SET
						t3.fname="'.$_POST['fname'].'",
						t3.lname="'.$_POST['lname'].'",
						t1.zip="'.$_POST['zip'].'",
						t1.state="'.$_POST['state'].'",
						t1.gender="'.$_POST['gender'].'",
						t2.email="'.$_POST['email'].'"
						
					WHERE
						t1.uid ='.$uid
					,'update_you_profile',"Updating a users profile");
					
					$edit_profile_results = $this->db_class_mysql->execute_query('update_you_profile');	
				}
				
	if($_POST['edit_submit_personal']){
			$this->db_class_mysql->set_query('
					UPDATE display_rel_profile AS t1
					SET
						t1.dob="'.$dob.'",
						t1.rs_status="'.$_POST['relationship_status'].'",
						t1.metric="'.$_POST['height_choice'].'"
					WHERE t1.uid ='.$uid
					,'update_you_profile',"Updating a users profile");
					
					$edit_profile_results = $this->db_class_mysql->execute_query('update_you_profile');	
	}
	
	if($_POST['edit_submit_pic']){
			var_dump($_FILES);
				
			$file_name = $uid.'_'.$_FILES['image_of_you']['name'];
			
			//$new_path = '/home/infotutors/www/www.chatapist.com/html/pictures/'.$file_name;
			$new_path = '/home/infotutors/www/www.chatapist.com/html/pictures/'.$file_name;
			
			move_uploaded_file($_FILES['pic']['tmp_name'],$new_path);
			$new_path = addslashes($new_path);
	}
				
		
				$this->db_class_mysql->set_query('

				SELECT 
				t1.fname,t1.lname,t1.interest,t1.foods,t1.reasons,t1.strength,t1.weakness,t1.you_love,
				t2.metric,t2.rs_status,t2.dob,t2.gender,t2.country,t2.state,t2.education,t2.language,t2.zip,t2.occupation,
				t3.yahoo,t3.aim,t3.msn,t3.icq,t3.gtalk,t3.irc,
				t4.eaim,t4.eyahoo,t4.emsn,t4.eicq,t4.egtalk,t4.eirc,
				t5.email,t5.pic_180
				FROM profile AS t1
				JOIN display_rel_profile AS t2
				ON t1.uid = t2.uid
				JOIN screen_names AS t3
				ON t1.uid = t3.uid
				JOIN enabled_screen_names AS t4
				ON t1.uid = t4.uid
				JOIN login AS t5
				ON t1.uid = t5.uid
				WHERE t1.uid='.$uid.';',
				
				'get_edit_profile','This is getting the users profile contents');
							
				$edit_profile_results = $this->db_class_mysql->execute_query('get_edit_profile');
			
				if($edit_profile_results) {	
					$edit_profile_results = $edit_profile_results->fetch_assoc();
					$this->set($edit_profile_results,'edit_profile');
				}
				
	}
	
	
	function test(){

	}

}
?>
