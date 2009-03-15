  function min(window){
	window.style.visibility = "hidden";
	chat_window_id = window.id.substring(15);
	min_chat_window = "chat_min_id_"+chat_window_id;	
 	document.getElementById(min_chat_window).style.visibility = "visible";
 }
 
  function max(window){
 	window.style.visibility = "hidden";
 	chat_window_id = window.id.substring(12);
	chat_window = "chat_window_id_"+chat_window_id;	
 	document.getElementById(chat_window).style.visibility = "visible";
 }