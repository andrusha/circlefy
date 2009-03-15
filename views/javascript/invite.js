function getXmlHttpRequestObject() {
        if (window.XMLHttpRequest) {
                return new XMLHttpRequest();
        } else if (window.ActiveXObject) {
                        return new ActiveXObject("Microsoft.XMLHTTP");
        } else {
                document.getElementById('errors_show').innerHTML = 'Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.';
        }
 }

function something(el){
	el.parentNode.innerHTML = '<img src="images/loading3.gif" />Sending...';
}

function tap(fid,state){
        var postText = getXmlHttpRequestObject();
        var param = 'init=init';
        if(state == 1){ state = 0; } else { state = 1;}
        
		if (postText.readyState == 4 || postText.readyState == 0){
                       postText.open("POST", 'AJAX/friend_tap.php', true);
                       postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
                       postText.onreadystatechange = function() { handleTap(postText,fid,state) };
        
                       param += '&state='+state;
                       param += '&friend=1';
                       param += '&fid='+fid;
                           postText.send(param);
		}
}

function handleTap(imStatus,fid,state) {
        if (imStatus.readyState == 4) {
                var json_result = eval('(' + imStatus.responseText + ')');
		if(state == 1){
                document.getElementById('tap_'+fid).innerHTML = 'Untap <img src="images/icons/connect.png" />';
		document.getElementById('tap_'+fid).className += state;
                } else {
		document.getElementById('tap_'+fid).innerHTML = 'Tap  <img src="images/icons/disconnect.png" />';
                document.getElementById('tap_'+fid).className += state;
		}
	}
}

