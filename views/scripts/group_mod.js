/*
script: group_mod.js
	Ban and Promote group members
*/

// UNCOMMENT FOR PROD
// (function(){

var _groupmod = _tap.register({

	init: function(){
		_body.addEvents({
			'click:relay(a.link_promote)': this.promoteGroupUser.toHandler(this),
			'click:relay(a.link_ban)': this.promoteGroupUser.toHandler(this)
		});
	},

	promoteGroupUser: function(el, e){
		var myAction = el.text;
		
		var processingText;
		var newText;

		switch(myAction) {
			case "promote":
				newText = "unpromote";
				processingText = "Promoting user to admin...";
				break;

			case "unpromote":
				newText = "promote";
				processingText = "Unpromoting user...";
				break;

			case "ban":
				newText = "unban";
				processingText = "Banning user...";
				break;

			case "unban":
				newText = "ban";
				processingText = "Removing ban...";
				break;
		}

		var parent = el.getParent('li');
		e.preventDefault();
		var block_mod_actions = el.getParent('span');

			var tmpgid = _vars.filter.gid;
			var data_uid = parent.getData('uid');
			var data_cid = parent.getData('id');
			var confirmPromote = true; // confirm('Are you sure you want to ' + myAction + ' this user? [gid=' + tmpgid + "] [uid=" + data_uid + "]");
			var myStatus = document.getElementById('mod-status_' + data_cid);

		if (confirmPromote) {
			new Request({
				url: '/AJAX/group_mod.php',
				data: {
					gid: _vars.filter.gid,
					target_uid: parent.getData('uid'),
					action: myAction
				},
				onRequest: function() {
					myStatus.set('html',processingText + " | ");
					// el.removeClass('login-fail');
					// el.addClass('login-success');
					
				},
				onSuccess: function(){
					// el.removeClass('login-success');
					el.set('html',newText);
					myStatus.set('html','');

/*
					ms.set('html','<img src="/images/icons/accept.png" /> Promoting user...');
					ms.removeClass('login-fail');
					ms.addClass('login-success');
					*/
			}
			}).send();
		}
	},

	blockGroupUser: function(el, e){
		var parent = el.getParent('li');
		e.preventDefault();
		var remove = confirm('Are you sure you want to ban this user?');
		if (remove) {
			new Request({
				url: '/AJAX/group_mod.php',
				data: {
					gid: _vars.filter.gid,
					target_uid: parent.getData('uid'),
					action: "ban"
				},
				onSuccess: function(){
					parent.set('slide', {
						onComplete: function(){
							parent.dispose();
							parent.retrieve('wrapper').destroy();
							parent.destroy();
						}
					}).slide('out');
				}
			}).send();
		}
	}

});

// })();
