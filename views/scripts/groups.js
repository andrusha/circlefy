/*
script: groups.js
	Controls the groups interface.
*/

// UNCOMMENT FOR PROD
// (function(){

var _template = {

	templater: new Template(),
	prepared: {},
	map: {
		'taps': 'template-taps',
		'responses': 'template-responses',
		'list.convo': 'template-list-convo',
		'suggest.group': 'template-suggest-group'
	},

	parse: function(type, data){
		var template = this.prepared[type];
		if (!template){
			template = this.map[type];
			if (!template) return '';
			template = this.prepared[type] = $(template).innerHTML.cleanup();
		}
		return this.templater.parse(template, data);
	}

};

var _sidelists = _tap.register({

	init: function(){
		_body.addEvents({
			'click:relay(li.panel-item)': this.doAction.toHandler(this)
		});
	},

	doAction: function(el, e){
		var link = el.getElement('a');
		if (!link) return;
		window.location = link.get('href');
	}

});

var _list = _tap.register({

	init: function(){
		_body.addEvents({
			'click:relay(a.leave)': this.leaveGroup.toHandler(this),
			'click:relay(button.invite)': this.inviter.toHandler(this)
		});
	},

	leaveGroup: function(el, e){
		var parent = el.getParent('li');
		e.preventDefault();
		var remove = confirm('Are you sure you want to leave this channel?');
		if (remove) {
			new Request({
				url: 'AJAX/leave_group.php',
				data: {
					gid: parent.getData('id')
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
	},

	inviter: function(el){
		var symbol = el.getParent('li').getData('symbol');
		window.location = '/invite?channel=' + symbol;
	}

});

// })();