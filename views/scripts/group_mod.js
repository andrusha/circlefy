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
			'click:relay(a.link_ban)': this.blockGroupUser.toHandler(this)
		});
	},

	promoteGroupUser: function(el, e){
		var parent = el.getParent('li');
		e.preventDefault();
			var tmpgid = _vars.filter.gid;
			var data_uid = parent.getData('uid');
			var confirmPromote = confirm('Are you sure you want to promote this user to Admin? [gid=' + tmpgid + "] [uid=" + data_uid + "]");
		if (confirmPromote) {
			new Request({
				url: '/AJAX/group_mod.php',
				data: {
					gid: _vars.filter.gid,
					target_uid: parent.getData('uid'),
					action: "promote"
				},
				onRequest: function() {
					el.set('html','<img src="/images/icons/accept.png" /> Promoting user...');
					el.removeClass('login-fail');
					el.addClass('login-success');
					
				},
				onSuccess: function(){
					el.removeClass('login-success');
					el.set('html','unpromote');
					/*
					alert("Obteniendo..");
					var ms = parent.retrieve('mod-status')
					alert(ms);

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
