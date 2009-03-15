<?php
$upload_dir = "/htdocs/rewrite/AJAX/upload"; // Directory for file storing
                                            // filesystem path


$web_upload_dir = "./"; // Directory for file storing
                          // Web-Server dir 
// testing upload dir 
// that your upload dir is really writable to PHP scripts
$tf = $upload_dir.'/'.md5(rand()).".test";
$f = @fopen($tf, "w");
if ($f == false) 
    die("Fatal error! {$upload_dir} is not writable. Set 'chmod 777 {$upload_dir}'
        or something like this");
fclose($f);
unlink($tf);
// end up upload dir testing 


// FILEFRAME section of the script
if (isset($_POST['fileframe'])) 
{
    $result = 'ERROR';
    $result_msg = 'No FILE field found';

    if (isset($_FILES['file']))  // file was send from browser
    {
        if ($_FILES['file']['error'] == UPLOAD_ERR_OK)  // no error
        {
            $filename = $_FILES['file']['name']; // file name 
            move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir.'/'.$filename);
            // main action -- move uploaded file to $upload_dir 
            $result = 'OK';
        }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_INI_SIZE)
            $result_msg = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        else 
            $result_msg = 'Unknown error';

        // you may add more error checking
        // see http://www.php.net/manual/en/features.file-upload.errors.php
    }

    // outputing trivial html with javascript code 

    // This is a PHP code outputing Javascript code.
    // Do not be so confused ;) 
    echo '<html><head><title>-</title></head><body>test';
    echo '<script language="JavaScript" type="text/javascript">'."\n";
    echo 'var parDoc = window.parent.document;';
    // this code is outputted to IFRAME (embedded frame)
    // main page is a 'parent'

	if ($result == 'OK'){
		// Simply updating status of fields and submit button
$js_output = <<<EOF
		parDoc.getElementById("upload_status").innerHTML = "file successfully uploaded"
		parDoc.getElementById("filename").innerHTML = "$filename"
		parDoc.getElementById("filenamei").innerHTML = "$filename"
		parDoc.getElementById("picture").innerHTML = "<img src='/rewrite/AJAX/upload/{$filename}' alt='picture' />"
		document.getElementById("pic_format_error").innerHTML = ""
EOF;
	} else {
        	$js_output =  'parDoc.getElementById("upload_status").value = "ERROR: '.$result_msg.'";';
	}

	echo $js_output;

    echo "\n".'</script></body></html>';

    exit(); // do not go futher 
}
// FILEFRAME section END


if (isset($_POST['description']))
{
    $filename = $_POST['filename'];
    $size = filesize($upload_dir.'/'.$filename);
    $date = date('r', filemtime($upload_dir.'/'.$filename));
    $description = $_POST['description'];

} 
?>
<!-- Beginning of main page -->
<html><head>

<title>IFRAME Async file uploader example</title>

<script type="text/javascript">
	/* This function is called when user selects file in file dialog */
	function jsUpload(upload_field)
	{
	    var re_text = /\.png|\.bmp|\.jpg|\.gif/i;
	    var filename = upload_field.value;

	    /* Checking file type */
	    if (filename.search(re_text) == -1)
	    {
		document.getElementById("pic_format_error").innerHTML = ("File does not have picture extension ( .jpg/.gif/.png/.bmp )");
		upload_field.form.reset();
		return false;
	    }

	    upload_field.form.submit();
	    document.getElementById('upload_status').value = "uploading file...";
	    return true;
	}
</script>
</head>
<body>
	<?php 
	if (isset($msg)) // this is special section for outputing message 
		echo '<p style="font-weight: bold;">'.$msg.'</p>';
	?> 
<h1>Upload file:</h1>
<p>File will begin to upload just after selection. </p>
<p>You may write file description, while you file is being uploaded.</p>

<form action="<?=$PHP_SELF?>" target="upload_iframe" method="post" enctype="multipart/form-data">
	<input type="hidden" name="fileframe" value="true">
	<!-- Target of the form is set to hidden iframe -->
	<!-- From will send its post data to fileframe section of 
	     this PHP script (see above) -->

	<label for="file">text file uploader:</label><br>
	<!-- JavaScript is called by OnChange attribute -->
	<input type="file" name="file" id="file">
</form>

<iframe name="upload_iframe" style="width: 400px; height: 100px; display:none;"></iframe>

<br>
	Upload status:<br>
	<span id="upload_status">Using default file...</span>
	<span id="pic_format_error"></span>
	<span id="picture">test</span>
	<br/>
	File name:
	<span id="filenamei">Using default file...</span>

	<form action="<?=$PHP_SELF?>" method="POST">
	<span id="filename"></span>	
	
	<label for="photo">File description:</label><br>
	<textarea rows="5" cols="50" name="description"></textarea>

	<br><br>
	<input type="submit" id="upload_button" value="save file" onclick="jsUpload(document.getElementById('file')); return false;">
</form>

</body>
</html>
