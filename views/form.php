<script type='text/javascript'>
        function toggleAll(element)
        {
        var form = document.forms.openinviter, z = 0;
        for(z=0; z<form.length;z++)
                {
                if(form[z].type == 'checkbox')
                        form[z].checked = element.checked;
                }
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

	function import_contacts(step){
		var postText = getXmlHttpRequestObject();
		var provider = document.getElementById('provider_box');

			if (postText.readyState == 4 || postText.readyState == 0)
			{
			 postText.open("POST", 'example.php', true);
			 postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
			 postText.onreadystatechange = function() { handlePending(postText) }
				param = 'email_box='+document.getElementById('email_box').value+'&';
				param += 'provider_box='+provider.options[provider.selectedIndex].value+'&';
				param += 'step='+step+'&';
				if(step == 'send_invites'){
					var session = document.getElementById('oi_session').innerHTML;
					param += 'oi_session='+session+'&';

						var elem = document.getElementById('openinviter').elements;
						for (var i = 0; i < elem.length; i++) {
							if ((elem[i].type == "hidden") || (elem[i].type == "text") || (elem[i].type == "textarea")) { // Text field
							   param += elem[i].name + "=" + elem[i].value + "&";
							} else if (elem[i].type == "checkbox") { // Check box
							   if (elem[i].checked) {
								  param += elem[i].name + '=' + elem[i].name.substring(6) +"&";
							   }
						}
						}
				} else {
				param += 'password_box='+document.getElementById('password_box').value+'&';
				}
						
				param = param.substring(0,param.length-1);
				postText.send(param);
			}
	}

	function handlePending(imStatus) {
		if (imStatus.readyState == 4) {
			var json_result = eval('(' + imStatus.responseText + ')');
			if(json_result != null){
				if(json_result.type == 'get_contacts'){
					document.getElementById('import').value = "Good to go";
					document.getElementById('import').onclick = function(){ import_contacts('send_invites'); } ;
					document.getElementById('openinviter').innerHTML = json_result.message;
				}
		}
	}
	}

	
	
</script>

<?
include('invite_list.html');
?>


<div id="contact_list">
<form id="openinviter">

</div>

</div>
