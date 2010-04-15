/*
script: tap.js
	Main script; Needed for the entire site.
*/

/*
global: _tap
	The global observer object.
*/
window._tap = new Observer();

/*
global: _body and _head
	Shortcuts for document.body and document.head
*/
_tap.register({
	init: function(){
		window._body = $(document.body);
		window._head = $(document.head);
	}

});


