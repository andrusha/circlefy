function getXmlHttpRequestObject() {
        if (window.XMLHttpRequest) {
                return new XMLHttpRequest();
        } else if (window.ActiveXObject) {
                        return new ActiveXObject("Microsoft.XMLHTTP");
        } else {
                document.getElementById('errors_show').innerHTML = 'Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.';
        }
 }

function join_group(gid,el){
	el.innerHTML = '<img src="/rewrite/images/loading3.gif" />Joining...';
        var postText = getXmlHttpRequestObject();
        var param = 'init=init';
		if (postText.readyState == 4 || postText.readyState == 0){
                       postText.open("POST", '../AJAX/join_group.php', true);
                       postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
                       postText.onreadystatechange = function() { handleJoin(postText) };
                       param += '&gid='+gid;
                           postText.send(param);
		}
}

function handleJoin(imStatus) {
        if (imStatus.readyState == 4) {
                var json_result = eval('(' + imStatus.responseText + ')');
		setTimeout("history.go(0)",1500);
	}
}

