

function height_type( type ){

	var height_cm_li = document.getElementById('height_cm_li');
	var height_in_li = document.getElementById('height_in_li');
	var weight_lb = document.getElementById('weight_lb_li');
	var weight_kg = document.getElementById('weight_kg_li');
	var default_off = document.getElementById('no_unit_selected');
	
	
	if(type == 'metric'){
		
		height_cm_li.style.display = 'block';
		weight_kg.style.display = 'block';
		weight_lb.style.display = 'none';
		height_in_li.style.display = 'none';
		default_off.style.display = 'none';
	}
	
	if(type == 'standard'){
		height_in_li.style.display = 'block';
		height_cm_li.style.display = 'none';
		weight_kg.style.display = 'none';
		weight_lb.style.display = 'block';
		default_off.style.display = 'none';
	}

}

function show_edit(el){

	var active_div = document.getElementById('active_edit');
	if(active_div.innerHTML != ''){
		var hide_div = 'div_edit_'+active_div.innerHTML;
		document.getElementById(hide_div).style.display = 'none';
	}

	var show_div = 'div_edit_'+el;
	document.getElementById(show_div).style.display = 'block';
	active_div.innerHTML = el;
}

/* This function is called when user selects file in file dialog */
function jsUpload(upload_field){
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
            document.getElementById('upload_status').innerHTML = '<img src="images/loading3.gif" />Uploading file..';
            return false;
}

function getCheckedValue(radioObj) {
	if(!radioObj)
		return "";
	var radioLength = radioObj.length;
	if(radioLength == undefined)
		if(radioObj.checked)
			return radioObj.value;
		else
			return "";
	for(var i = 0; i < radioLength; i++) {
		if(radioObj[i].checked) {
			return radioObj[i].value;
		}
	}
	return "";
}

function send_update(type){
        var postText = getXmlHttpRequestObject();
        var param = 'init=init';

	if(type == 'you'){
		param += '&type='+type;
		param += '&fname='+document.getElementById('fname').value;
		param += '&lname='+document.getElementById('lname').value;
		param += '&email='+document.getElementById('email').value;
		param += '&zip='+document.getElementById('zip').value;
		param += '&username='+document.getElementById('username').value;
		param += '&state='+document.getElementById('state').value;
		country = document.getElementById('country');
			param += '&country='+country.options[country.selectedIndex].value;
		lang = document.getElementById('lang');
			param += '&lang='+lang.options[lang.selectedIndex].value;
		gender = getCheckedValue(document.forms['edit_you_form'].elements['gender']);
			param += '&gender='+gender;
	}
       if (postText.readyState == 4 || postText.readyState == 0)
        {
         postText.open("POST", 'AJAX/edit_profile.php', true);
         postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
         postText.onreadystatechange = function() { handleUpdate(postText,type) };

        postText.send(param);
        }
}

function handleUpdate(imStatus,type){
   if (imStatus.readyState == 4) {
		var json_result = eval('(' + imStatus.responseText + ')');
		if(json_result != ''){
			document.getElementById('you_updated').innerHTML = 'Your profile has been update';
			setTimeout('clearText("you_updated");',3000);
		}
                }
}

function clearText(type){
	document.getElementById(type).innerHTML = '';
}


	
function getXmlHttpRequestObject() {
        if (window.XMLHttpRequest) {
                return new XMLHttpRequest();
        } else if (window.ActiveXObject) {
                        return new ActiveXObject("Microsoft.XMLHTTP");
        } else {
                document.getElementById('errors_show').innerHTML = 'Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.';
        }
}

