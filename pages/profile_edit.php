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

				echo $update_pics_query;
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
	                parDoc.getElementById("edit_profile_picture").innerHTML = "<img src='{$web_upload_dir}/{$pic_180}' alt='Your picture' />"
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
		
	if($_POST['edit_submit_pic']){
			var_dump($_FILES);
				
			$file_name = $uid.'_'.$_FILES['image_of_you']['name'];
			
			//$new_path = '/home/infotutors/www/www.chatapist.com/html/pictures/'.$file_name;
			$new_path = '/home/infotutors/www/www.chatapist.com/html/pictures/'.$file_name;
			
			move_uploaded_file($_FILES['pic']['tmp_name'],$new_path);
			$new_path = addslashes($new_path);
	}
		
			$get_profile_query = <<<EOF
				SELECT 
				t1.metric,t1.rs_status,t1.dob,t1.gender,t1.country,t1.state,t1.education,t1.language,t1.zip,t1.occupation,
				t5.email,t5.pic_180,t5.fname,t5.lname
				FROM display_rel_profile AS t1
				JOIN login AS t5
				ON t1.uid = t5.uid
				WHERE t1.uid={$uid}
EOF;
				$this->db_class_mysql->set_query($get_profile_query,'get_edit_profile','This is getting the users profile contents');

											
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
