var sTimer;
var enc_fa4 = 'signup_function();';

function vanish_text(classAppend,e){
e.value = '';
e.className = classAppend+' 1';
}

function group(){
        var postText = getXmlHttpRequestObject();
        var param = 'init=init';

        var email = document.getElementById('input_connected_email').value;
                if (postText.readyState == 4 || postText.readyState == 0)
                {
                 postText.open("POST", 'AJAX/connected_group_add.php', true);
                 postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
                 postText.onreadystatechange = function() { handlePending(postText) }
                        param += '&email='+email;
                        postText.send(param);
                }
}

function handlePending(imStatus) {
        if (imStatus.readyState == 4) {
                var json_result = eval('(' + imStatus.responseText + ')');
                if(json_result != null){
                	document.getElementById('connected_status').innerHTML = json_result.stat;
	                document.getElementById('input_connected_email').value = '';
        }
}
}


function set_cookie( name, value, expires, path, domain, secure ) 
{
	var today = new Date();
	today.setTime( today.getTime() );
	
	//expires = days
	if (expires){expires = expires * 1000 * 60 * 60 * 24;}
	var expires_date = new Date( today.getTime() + (expires) );
	
	document.cookie = name+"="+escape(value)+
	( (expires) ? ";expires=" + expires_date.toGMTString() : "" ) + 
	( (path) ? ";path=" + path : "" ) + 
	( (domain) ? ";domain=" + domain : "" ) +
	( (secure) ? ";secure" : "" );

}

function delete_cookie( name, path, domain ) {
	 document.cookie = name + "=" +
		( ( path ) ? ";path=" + path : "") +
		( ( domain ) ? ";domain=" + domain : "" ) +
	";expires=Thu, 01-Jan-1970 00:00:01 GMT";
}

function sign_up(){
 	var sign_up = document.getElementById('sign_up');
 	sign_up.style.display = "block";
 	show_first_step();
 }
 
 function show_first_step(){
 	var step_1 = document.getElementById('step_1');
 	var step_2 = document.getElementById('step_2');
 	var step_3 = document.getElementById('step_3');
 	step_1.style.display = "none";
 	step_2.style.display = "none";
 	step_3.style.display = "none";
 	step_<?php echo ($_COOKIE['wasp_attack']) ? 2:1;  ?>.style.display = "block";
 	if(document.getElementById('uname_signup').disabled == false){
 	document.getElementById('uname_signup').focus();
 	} else {
 	}
 }
 
 function show_next_step(step){
	setTimeout('window.location.reload()',2000);
	current_step = "step_"+step.id.substring(5);
 	current_step = document.getElementById(current_step);
 	current_step.style.display = "none";
 	next_step = step.id.substring(5);
 	next_step++;
 	next_step = "step_"+next_step;
 	next_step = document.getElementById(next_step);
 	next_step.style.display = "block";
 }
 
  function show_last_step(step){
	current_step = "step_"+step.id.substring(5);
 	current_step = document.getElementById(current_step);
 	current_step.style.display = "none";
 	next_step = step.id.substring(5);
 	next_step--;
 	next_step = "step_"+next_step;
 	next_step = document.getElementById(next_step);
 	next_step.style.display = "block";
 }
 
 function close_sign_up(){
 	 var sign_up = document.getElementById('sign_up');
 	sign_up.style.display = "none";
 }
 
 function check_uname(text_box){
	 alert("asdasd");
 	var uname_insert = document.getElementById('uname_insert');	
 	var errors = document.getElementById('uname_error_count');
 	if(text_box.value.length < 4){
 		uname_insert.innerHTML = 'Username must be atleast 4 characters';
 		errors.innerHTML = 1;
 	} else {
		var checker = getXmlHttpRequestObject();
		checker.open('POST', 'AJAX/check_signup.php', true);
		checker.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		checker.onreadystatechange = function(){
			if (checker.readyState == 4) {
				if (checker.responseText == '{"available":false}') {
					uname_insert.innerHTML = 'Username already taken!';
			 		errors.innerHTML = 1;
				} else {
					uname_insert.innerHTML = 'Username OK!';
			 		errors.innerHTML = 0;
				}
			}
		};
		checker.send('type=1&val='+encodeURIComponent(text_box.value));

 	}
 	check_all(0,0);
 }
 
 function check_email(text_box){
 	var email_insert = document.getElementById('email_insert');
 	var errors = document.getElementById('email_error_count');
 	
  var reg1 = /^.+\@(\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,3}|[0-9]{1,3})(\]?)$/; // valid
 	
 	if(text_box.value.length < 4){		
 		email_insert.innerHTML = 'Email not valid!';
 		errors.innerHTML = 1;
 	} else if(!reg1.test(text_box.value)){
 		email_insert.innerHTML = 'You\'ve type an invalid e-mail address!';
 		errors.innerHTML = 1;
 	} else {
		var checker = getXmlHttpRequestObject();
		checker.open('POST', 'AJAX/check_signup.php', true);
		checker.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		checker.onreadystatechange = function(){
			if (checker.readyState == 4) {
				if (checker.responseText == '{"available":false}') {
					email_insert.innerHTML = 'Email Address already taken!';
			 		errors.innerHTML = 1;
				} else {
					email_insert.innerHTML = 'Email address OK!';
			 		errors.innerHTML = 0;
				}
			}
		};
		checker.send('type=2&val='+encodeURIComponent(text_box.value));
 	}
 	check_all(0,0);
 }
 
 function check_repass(text_box){	
 	var original_pass = document.getElementById('pass_signup');
 	var repass_insert = document.getElementById('repass_insert');
 	var errors = document.getElementById('pass_error_count');
 	
 	if(text_box.value != original_pass.value ){
 		repass_insert.innerHTML = 'Passwords do not match!';
 		errors.innerHTML = 1;
 	} else if(text_box.value.length < 6){
 		repass_insert.innerHTML = 'Password must be 6 chars or more!';
 		errors.innerHTML = 1;
 	} else {
 		repass_insert.innerHTML = 'Password OK!';
 		errors.innerHTML = 0;
 	}
 	check_all(0,2);
 }
 
 function check_all(current_step, click){
 	uname_errors = document.getElementById('uname_error_count');
 	email_errors = document.getElementById('email_error_count');
 	pass_errors = document.getElementById('pass_error_count');
	show_errors = document.getElementById('signup_errors');

 	if(email_errors.innerHTML == 1 || uname_errors.innerHTML == 1 || pass_errors.innerHTML == 1){
 		if(click != 0){
 		show_errors.innerHTML = 'You have error(s)!  Fix to proceed <img src="images/icons/exclamation.png" />';
 		}
 	} else {
 	
	 	if(document.getElementById('next_1')){
	 		
	 		document.getElementById('next_1').innerHTML = 'Next >>';
	 		show_errors.innerHTML = 'Form OK, Proceed <img src="images/icons/tick.png" />';
	 		
	 		if(click == 1){
				var inputs = document.getElementById('sign_up_form').elements;
	 			 for (var i = 0; i < inputs.length - 1; i++) { 
	 			 	var input = inputs[i];
	 			 	input.disabled = true;
	 			 }
	 			ajax_signup(current_step);
	 		}
	 	} else {
	 		show_next_step(current_step);
	 	}
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
 

 function ajax_signup(current_step){	
 	var submitSignup = getXmlHttpRequestObject();
 	var input_values = new Array(5);
	current_step.id = 'done_1';
	
	if (submitSignup.readyState == 4 || submitSignup.readyState == 0) {
		submitSignup.open("POST", 'AJAX/ajaz_sign_up.php', true);
		submitSignup.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		submitSignup.onreadystatechange = function(){
			if (submitSignup.readyState == 4) {
				window.location.reload();
			}
		};
	
		var inputs = document.getElementsByTagName('input');
		for (var i = 4; i < inputs.length; i++) { 
		 	var input = inputs[i];
		 	input_values[i] = input.value;
		}
		var param = 'uname='+input_values[4];
		 param += '&fname='+ input_values[5];
		 param += '&email='+ input_values[6];
		 param += '&pass='+  input_values[7];
		 param += '&fid='+ <?if($_GET['fid']){ echo $_GET['fid']; } else { echo 0; }?>;
		 param += '&signup_flag=' + enc_fa4;
		 
		submitSignup.send(param);
	}			
 }

        function toggleAll(element)
        {
        var form = document.forms.openinviter, z = 0;
        for(z=0; z<form.length;z++)
                {
                if(form[z].type == 'checkbox')
                        form[z].checked = element.checked;
                }
        }

        function import_contacts(step){
                var postText = getXmlHttpRequestObject();
                var provider = document.getElementById('provider_box');

                        if (postText.readyState == 4 || postText.readyState == 0)
                        {
                         postText.open("POST", 'AJAX/import_contacts.php', true);
                         postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
                         postText.onreadystatechange = function() { inviteHandle(postText) }
                                var param = 'email_box='+document.getElementById('email_box').value+'&';
                                param += 'provider_box='+provider.options[provider.selectedIndex].value+'&';
                                param += 'step='+step+'&';
                                if(step == 'send_invites'){
					param += 'fname='+document.getElementById('fname_signup').value+'&';
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
				document.getElementById('openinviter').innerHTML = '<img src="images/loading3.gif" />';
                        }
        }

        function inviteHandle(imStatus) {
                if (imStatus.readyState == 4) {
                        var json_result = eval('(' + imStatus.responseText + ')');
                        if(json_result != null){
                                if(json_result.type == 'get_contacts'){
                                        document.getElementById('import').value = "Send!";
                                        document.getElementById('import').onclick = function(){ import_contacts('send_invites'); } ;
                                        document.getElementById('openinviter').innerHTML = json_result.message;
                                }
                }
        }
        }

