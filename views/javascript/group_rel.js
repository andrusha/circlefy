function getXmlHttpRequestObject() {
        if (window.XMLHttpRequest) {
                return new XMLHttpRequest();
        } else if (window.ActiveXObject) {
                        return new ActiveXObject("Microsoft.XMLHTTP");
        } else {
                document.getElementById('errors_show').innerHTML = 'Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.';
        }
}


function check_name(){

	if(document.getElementById('tags').value.length > 2){ var tag_check = 1; }
	if(document.getElementById('zipcode').value.length == 5){ var zip_check = 1; }
	
        if(document.getElementById('name').value.length < 1){
 	       document.getElementById('name_error').style.display = 'block';
        	document.getElementById('rel_complete').style.display = 'none';
	 	return 0;
        } else { 
		var name_check = 1;
	}

	if((tag_check == 1 || zip_check == 1) && name_check == 1){
		document.getElementById('tag_error').style.display = 'none';
		document.getElementById('name_error').style.display = 'none';
		return 1;
	} else {
        	document.getElementById('rel_complete').style.display = 'none';
		document.getElementById('tag_error').style.display = 'block';
	}
}

function add_rel(gid){
        var postText = getXmlHttpRequestObject();
        var param = 'init=init';

	var name_status = check_name();
	if(name_status != 1){ return false; }

        if (postText.readyState == 4 || postText.readyState == 0)
        {
         postText.open("POST", '../AJAX/group_rel.php', true);
         postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
         postText.onreadystatechange = function() { handleOutput(postText) };

	text11 = 0;

	param += '&gid='+gid;
	param += '&name='+document.getElementById('name').value;		
	param += '&tags='+document.getElementById('tags').value;		
	param += '&zipcode='+document.getElementById('zipcode').value;
	
	postText.send(param);
	}
}
	
function del_rel(rid){
	var postText = getXmlHttpRequestObject();
        var param = 'init=init';

       if (postText.readyState == 4 || postText.readyState == 0)
        {
         postText.open("POST", '../AJAX/group_rel.php', true);
         postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
         postText.onreadystatechange = function() { handleDel(postText,rid) };
	param += '&delete=1';
        param += '&rid='+rid;

        postText.send(param);
	}
	
}


function update_enable(rid,state){
	var postText = getXmlHttpRequestObject();
        var param = 'init=init';

	//This transforms from what it is, to what it should be.
	if(state == 1){ state = 0; } else { state = 1;}

       if (postText.readyState == 4 || postText.readyState == 0)
        {
         postText.open("POST", '../AJAX/group_rel.php', true);
         postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
         postText.onreadystatechange = function() { handleToggle(postText,rid,state) };

	param += '&state='+state;
	param += '&update=1';
        param += '&rid='+rid;

        postText.send(param);
        }
}

function handleToggle(imStatus,rid,state){
	state_block = document.getElementById('state_'+rid);
   if (imStatus.readyState == 4) {
                var json_result = eval('(' + imStatus.responseText + ')');
		if(state == 1){
                	document.getElementById('state_'+rid).style.background = 'green';
                	document.getElementById('state_'+rid).innerHTML = 'Enabled';
			state_block.className += state;
		} else {
			document.getElementById('state_'+rid).style.background = 'red';
                	document.getElementById('state_'+rid).innerHTML = 'Disabled';
			state_block.className += state;
                }
	}
}

function handleDel(imStatus,rid) {
        if (imStatus.readyState == 4) {
                var json_result = eval('(' + imStatus.responseText + ')');
		document.getElementById('rel_'+rid).style.display = 'none';
                }
}

function handleOutput(imStatus) {
        if (imStatus.readyState == 4) {
                var json_result = eval('(' + imStatus.responseText + ')');
	document.getElementById('name').value = '';
        document.getElementById('tags').value = '';
	document.getElementById('zipcode').value = '';
        document.getElementById('rel_complete').style.display = 'block';

		document.getElementById('rel_table_body').innerHTML += json_result.html;
                }
}


