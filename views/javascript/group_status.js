function getXmlHttpRequestObject() {
        if (window.XMLHttpRequest) {
                return new XMLHttpRequest();
        } else if (window.ActiveXObject) {
                        return new ActiveXObject("Microsoft.XMLHTTP");
        } else {
                document.getElementById('errors_show').innerHTML = 'Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.';
        }
}

function update_enable_status(type,gid,state){
	var postText = getXmlHttpRequestObject();
        var param = 'init=init';

	//This transforms from what it is, to what it should be.
	if(state == 1){ state = 0; } else { state = 1;}

       if (postText.readyState == 4 || postText.readyState == 0)
        {
         postText.open("POST", '../AJAX/group_status.php', true);
         postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
         postText.onreadystatechange = function() { handleToggle_status(postText,gid,state,type) };

	param += '&state='+state;
	param += '&type='+type;
	param += '&update=1';
        param += '&gid='+gid;

        postText.send(param);
        }
}

function handleToggle_status(imStatus,gid,state,type){

if(type == 'tapd'){ type = 'tap' }
state_block = document.getElementById('group_'+type);

   if (imStatus.readyState == 4) {
                var json_result = eval('(' + imStatus.responseText + ')');
		if(state == 1){
			document.getElementById('group_'+type).style.background = 'Green';
                	document.getElementById('group_'+type).innerHTML = 'Yes';
			state_block.className += state;
		} else {
			document.getElementById('group_'+type).style.background = 'red';
                	document.getElementById('group_'+type).innerHTML = 'No';
			state_block.className += state;
                }
	}
}
