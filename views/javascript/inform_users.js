function window_swapper(current_window){
	var currently_showing = document.getElementById('currently_showing').innerHTML;
	var li_clicked = current_window.id.substring(6);
	document.getElementById('currently_showing').innerHTML = li_clicked;
	currently_showing = document.getElementById(currently_showing);
	currently_showing.style.display = 'none';
	document.getElementById(li_clicked).style.display = 'block';
}

function mouse_over_inform(current_mouse_over){
	current_mouse_over.style.background = '#0F87FF';
	current_mouse_over.style.color = 'white';
	current_mouse_over.style.cursor = 'pointer';
}

function mouse_out_inform(current_mouse_over){
	current_mouse_over.style.background = 'white';
	current_mouse_over.style.color = '#0F87FF';
}