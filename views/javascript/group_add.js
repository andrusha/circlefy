function getXmlHttpRequestObject() {
        if (window.XMLHttpRequest) {
                return new XMLHttpRequest();
        } else if (window.ActiveXObject) {
                        return new ActiveXObject("Microsoft.XMLHTTP");
        } else {
                document.getElementById('errors_show').innerHTML = 'Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.';
        }
}

function check_descr(text){
	if(text.length < 20){
		document.getElementById('descr_error').innerHTML = "Descriptoin must be atleast 20 characters!";
		document.getElementById('descr_error').style.display = 'block';
		document.getElementById('create_group_errors').innerHTML = 1;
	} else {
		document.getElementById('descr_error').style.display = 'none';
		document.getElementById('create_group_errors').innerHTML = 0;
	}	
}

function check_all(){
	errors = document.getElementById('create_group_errors').innerHTML;
	if(errors != 0){
		document.getElementById('all_errors').innerHTML = "You have errors you need to fix, please review your groups information";
		return false;
	} else { 
		return true;
	}
}
	
function group(group_name){
        var postText = getXmlHttpRequestObject();
        var param = 'init=init';

	if(group_name != ('' && NULL)){
		if (postText.readyState == 4 || postText.readyState == 0)
		{
		 postText.open("POST", 'AJAX/group_check.php', true);
		 postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		 postText.onreadystatechange = function() { handlePending(postText,group_name) }
			param += '&group_check=1';
			
			param += '&gname='+group_name;
			postText.send(param);
		}
	}
}

function handlePending(imStatus,group_name) {
        if (imStatus.readyState == 4) {
                var json_result = eval('(' + imStatus.responseText + ')');
                if(json_result != null){
                        if(typeof json_result != 'undefined' && json_result.no_results != 'null' && typeof json_result.dup == 'undefined') {
				document.getElementById('ajax_rel_groups').style.display = 'block';
				document.getElementById('ajax_rel_list').innerHTML = '';
				document.getElementById('create_group_desc').focus();
				var array_length = json_result.length;
				for(x = 0; x < array_length;x++){
					document.getElementById('ajax_rel_list').innerHTML += json_result[x][0];
				}
			}
			
			if(typeof json_result != 'undefined' && json_result.dup_init == "true"){
				document.getElementById('ajax_rel_groups').style.display = 'block';
				document.getElementById('ajax_rel_list').innerHTML = json_result.dup;
				document.getElementById('create_group_errors').innerHTML = 1;
			} else {
				document.getElementById('create_group_errors').innerHTML = 0;
		}
	}
}
}
