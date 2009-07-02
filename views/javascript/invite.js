function getXmlHttpRequestObject() {
        if (window.XMLHttpRequest) {
                return new XMLHttpRequest();
        } else if (window.ActiveXObject) {
                        return new ActiveXObject("Microsoft.XMLHTTP");
        } else {
                document.getElementById('errors_show').innerHTML = 'Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.';
        }
 }

function Loading(el){
	el.innerHTML = '<img src="images/loading3.gif" />Sending...';
}

function invite(email,type,el){
	Loading(el);
        var postText = getXmlHttpRequestObject();
        var param = 'init=init';
		if(type == 2){
			sel = document.getElementById("invite_groups_in")
			param += '&gname='+sel.options[sel.selectedIndex].innerHTML
		}
		if (postText.readyState == 4 || postText.readyState == 0){
                       postText.open("POST", 'AJAX/ind_invite.php', true);
                       postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
                       postText.onreadystatechange = function() { handleInvite(postText,el) };
                       param += '&type='+type;
                       param += '&email='+email;
			
                           postText.send(param);
		}
}

function handleInvite(inviteStatus,el) {
        if (inviteStatus.readyState == 4) {
                var json_result = eval('(' + inviteStatus.responseText + ')');
		s = 1000000
		while(s)
			--s
		if(json_result.good)
		el.innerHTML = '<span class="bold">Invite Sent!</span>';
	}
}

