var sTimer;

function ifEnter(field,event) {
	var theCode = event.keyCode ? event.keyCode : event.which ? event.which : event.charCode;
	if (theCode == 13){
		open_window(document.getElementById('question').value);
		return false;
	} else {
		return true;
	}
}  

/*
function ifEnterChat(field,event,msg,channel_id,msg_id) {
	var theCode = event.keyCode ? event.keyCode : event.which ? event.which : event.charCode;
	if (theCode == 13){
		send_msg(msg,channel_id,msg_id);
		document.getElementById('typingspace').value = '';
		return false;
	} else {
		return true;
	}
}
*/

function getXmlHttpRequestObject() {
        if (window.XMLHttpRequest) {
                return new XMLHttpRequest();
        } else if (window.ActiveXObject) {
                        return new ActiveXObject("Microsoft.XMLHTTP");
        } else {
                document.getElementById('errors_show').innerHTML = 'Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.';
        }
}


function open_window( msg ){
	user = <?=$_SESSION['uname']?>
	//This initiates the AJAX polling and receives the channel_id
	send_msg(msg,0,0);

document.getElementById('question').value = '';
setTimeout("document.getElementById('question').value = '';",200);
//$('mode_text').innerHTML = '<img src="images/icons/bullet_add.png" />Targeting, press ! to enter message mode';

}

function send_msg(msg,time,channel_id){
	var postText = getXmlHttpRequestObject();
	var param = 'init=init';

	if (postText.readyState == 4 || postText.readyState == 0)
	{
	 postText.open("POST", 'AJAX/message_handler.php', true);
         postText.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
	 postText.onreadystatechange = function() { handlePending(postText,time,channel_id); } 

	//time the last msg sent
	param += '&time='+time;

	//channel_id is the channel you are in
	if(channel_id != 0) { param += '&channel_id='+channel_id; }

	//msg is the message
	if(msg != ''){param += '&msg='+msg;}

	postText.send(param);
	}
}

function handlePending(imStatus,time,channel_id) {
//       clearInterval(sTimer);
	
        if (imStatus.readyState == 4) {
                var json_result = eval('(' + imStatus.responseText + ')');
		if(json_result != null){
			if(typeof json_result.new_channel != 'undefined'){
				var newel = new Element('div', {'html':json_result.new_msg, 'style':'visibility: hidden','id': 'newbit_'+json_result.channel_id });
				$('chat_windows').innerHTML = '';
				newel.inject($('chat_windows'),'top');
				newel.tween('opacity', 0, 1);
				new_id = json_result.channel_id;
				grabber_obj.add_bit(new_id);
				response_event_obj.remove_action();
				response_event_obj.add_action();
				active_convo.add_active(newel,'bit');	
				//setTimeout("toggle_show_response('responses_'+new_id+'_self',$('self_toggle_res_'+new_id),1)",1000);
				time = json_result.time;
				counter_data = json_result.counter_data;
				$('tap_status_descr').innerHTML = '';
				$('tap_status_table_body').innerHTML = '';
				$('tap_status_table_body').tween('opacity', 0, 0);
				$each(counter_data,function(x,key){
					if(key == 'groups')
						$each(x,function(count,group){
							$('tap_status_table_body').innerHTML += "<tr><td class='lt'>"+group+" </td><td class='rt'>"+count+"</td></tr>";
						});
					if(key == 'direct')
					$('tap_status_table_body').innerHTML += "<tr><td class='lt'>Direct </td><td class='rt'>"+counter_data.direct+"</td></tr>";
					if(key == 'friends')
							$('tap_status_table_body').innerHTML += "<tr><td class='lt'>On tap </td><td class='rt'>"+counter_data.friends+"</td></tr>";
					if(key == 'other')
					$('tap_status_table_body').innerHTML += "<tr><td class='lt'>Other </td><td class='rt'>"+counter_data.other+"</td></tr>";
				});
				$('tap_status_table_body').tween('opacity', 0, 1);
				$('to_output').tween('opacity', 1, 0);
				$('to_output').empty();
			} else if(json_result.results != 'false') {

			}
		}
//	        sTimer =  setTimeout("send_msg('','"+time+"','"+channel_id+"');",2000);
	}
}
