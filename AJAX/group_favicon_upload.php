<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');

$file = $_FILES['gr-pic-fav'];

if(isset($file)){
   	$group_function = new group_functions();
        $results = $group_function->pic_upload($file);
        echo $results;
}


class group_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function pic_upload($file){
                        $root = ROOT;
                        $upload_dir = "..".D_GROUP_PIC_REL; // Directory for file storing
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


                        if (isset($file)){  // file was send from browser
                                if ($file['error'] == UPLOAD_ERR_OK){  // no error
                                    $filename = $file['name']; // file name
                                    move_uploaded_file($file['tmp_name'], $upload_dir.'/'.$filename);

                                $full_file = $upload_dir.'/'.$filename;
				$randnum = rand(1,9999293);
                                $hash_filename = md5($randnum.'CjaCXo39c0..$@)(c'.$filename);
                                $image = $full_file;
                                $im = new imagick( $image );
                                $normal = $im->clone();
                                $ftype = ".gif";

                                $favicon = 'fav_'.$hash_filename.$ftype;
				$normal->thumbnailImage( 16,null );
                                $normal->writeImage( $upload_dir.'/'.$favicon );
	
				$result = 'OK';
			}
                        elseif ($file['error'] == UPLOAD_ERR_INI_SIZE)
                                $result_msg = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
                        else
                                $result_msg = 'Unknown error';
                        }
			// echo $result_msg;
			// echo $result;
                        // you may add more error checking
                        // see http://www.php.net/manual/en/features.file-upload.errors.php

                $pic_path = D_GROUP_PIC_REL;
		$image =  $favicon;
                    // This is a PHP code outputing Javascript code.
                    // Do not be so confused ;) 
                    $output = <<<EOF
			<!DOCTYPE html>
			<head>
			<title></title>
			<script type="text/javascript">
				window.top.fireEvent('uploaded', {
				 success: true,
				 error: null,
				 path: "$image",
				 type: "favicon",
				 hash: "$hash_filename"
				});
			</script>
			</head>
			<body></body>
			</html>
EOF;
		return $output;

	}
}