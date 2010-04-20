/*
script: creategroups.js
	Controls the group creation interface.
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

var _lists = _tap.register({

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

_tap.mixin({

	name: 'errors',

	showError: function(input, msg){
		var label = input.getPrevious('label'),
			errtext = label.getElement('span.error');

		input.addClass('error').store('passed', false);
		errtext.set('html', msg);
		label.addClass('error');
		return this;
	},

	removeError: function(input){
		var label = input.getPrevious('label');
		input.removeClass('error').store('passed', true);
		label.removeClass('error');
		return this;
	},

	noErrors: function(){
		return !$H(this.data).getValues().map(function(item){
			return !!item.retrieve('passed');
		}).contains(false);
	},

	fireErrors: function(){
		var e = {stop: $empty, preventDefault: $empty};
		$$($H(this.data).getValues().filter(function(item){
			return !item.retrieve('passed');
		})).fireEvent('blur', [e]);
		return this;
	}

});

var _account = _tap.register({

	mixins: 'errors',

	init: function(){
		if (_vars.type !== 'account') return;
		var main = this.main = $('user-account');
		var data = this.data = {
			email: main.getElement('input[name="email"]').store('passed', true),
			private: main.getElement('input[name="private"]').store('passed', true),
			about: main.getElement('input[name="about"]').store('passed', true),
			fname: main.getElement('input[name="firstname"]').store('passed', true),
			lname: main.getElement('input[name="lastname"]').store('passed', true),
			lang: main.getElement('select[name="language"]').store('passed', true),
			country: main.getElement('select[name="country"]').store('passed', true)
		};
		for (var key in data){
			if (this[key + 'Check'] instanceof Function){
				data[key].addEvent('blur', this[key + 'Check'].toHandler(this));
			}
		}
		if (_vars.country) data.country.getElement('option[value="'+_vars.country+'"]').set('selected', 'selected');
		else data.country.getElement('option[value="us"]').set('selected', 'selected');
		if (_vars.lang) data.lang.getElement('option[value="'+_vars.lang+'"]').set('selected', 'selected');
		main.getElement('button.action').addEvent('click', this.save.toHandler(this));
	},

	emailCheck: function(el, e){
		el.value = el.value.toLowerCase();
		if (el.isEmpty() || !el.isEmail()) {
			return this.showError(el, 'you\'ll need to enter a valid email address');
		}
		return this.removeError(el);
	},

	aboutCheck: function(el, e){
		if (el.isEmpty() || !el.ofLength(5, 240)) {
			return this.showError(el, 'write something about yourself');
		}
		return this.removeError(el);
	},

	fnameCheck: function(el){
		if (el.isEmpty() || !el.isAlpha()) {
			return this.showError(el, 'what\'s your proper first name?');
		}
		return this.removeError(el);
	},

	lnameCheck: function(el){
		if (el.isEmpty() || !el.isAlpha()) {
			return this.showError(el, 'what\'s your proper last name?');
		}
		return this.removeError(el);
	},

	save: function(el){
		var data = this.data;
		if (this.sending) return false;
		if (!this.noErrors()) return this.fireErrors();
		this.sending = true;
		var private = data.private.get('checked');

		var privateValue=0;
		if(private)
			privateValue  = 1;
	
		new Request({
			'url': '/AJAX/edit_profile.php',
			'data': {
				about: data.about.get('value'),
				fname: data.fname.get('value'),
				lname: data.lname.get('value'),
				email: data.email.get('value'),
				private: privateValue,
				lang: data.lang.get('value'),
				country: data.country.get('value')
			},
			onRequest: function(){
				$$(data.about, data.fname, data.lname, data.email, data.lang, data.country).set('disabled', 'disabled');
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				window.location = ('/settings?type=account&saved=' + ((response && response.success) ? 'yes' : 'no'));
			}
		}).send();
	}

});

var _picture = _tap.register({

	mixins: 'errors',

	init: function(){
		if (_vars.type !== 'picture') return;
		var main = this.main = $('user-picture');
		this.uploader = $('pic-uploader');
		this.uploader.addEvent('change', this.check.toHandler(this));
		window.addEvent('uploaded', this.set.bind(this));
		main.getElement('button.action').addEvent('click', this.save.toHandler(this));
	},

	check: function(el){
		if (!el.get('value').test(/\.(gif|png|bmp|jpeg|jpg)$/i)) {
			this.error = true;
			return this.showError(el, 'you can only upload gifs, pngs, bmps or jpegs.');
		}
		this.error = false;
		this.removeError(el);
		this.uploading = true;
		el.getParent('form').submit();
	},

	set: function(data){
		this.uploading = false;
		if (data.success) {
			this.pic = data.hash;
			$('preview').src = '/user_pics/med_'+data.hash+'.gif';
			this.removeError(this.uploader);
		} else {
			this.pic = null;
			this.showError(this.uploader, data.error);
		}
	},

	save: function(el, e){
		var self = this, data = this.data;
		if (this.sending) return false;
		if (this.uploading) return setTimeout(function(){ self.save(el, e); }, 1000);
		if (!this.pic) return this.showError(this.uploader, 'where\'s your new picture?');
		this.sending = true;
		new Request({
			'url': '/AJAX/edit_profile.php',
			'data': {
				about: _vars.about,
				fname: _vars.fname,
				lname: _vars.lname,
				email: _vars.email,
				lang: _vars.lang,
				country: _vars.country,
				hash_name: this.pic
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				window.location = ('/settings?type=picture&saved=' + ((response && response.success) ? 'yes' : 'no'));
			}
		}).send();
	}

});

var _password = _tap.register({

	mixins: 'errors',

	init: function(){
		if (_vars.type !== 'password') return;
		var main = this.main = $('user-password');
		var data = this.data = {
			current: main.getElement('input[name="current"]'),
			newpass: main.getElement('input[name="new"]'),
			repeat: main.getElement('input[name="repeat"]')
		};
		for (var key in data){
			if (this[key + 'Check'] instanceof Function){
				data[key].addEvent('blur', this[key + 'Check'].toHandler(this));
			}
		}
		main.getElement('button.action').addEvent('click', this.save.toHandler(this));
	},

	currentCheck: function(el){
		if (el.isEmpty()) {
			return this.showError(el, 'you\'ll have to enter your current password');
		}
		return this.removeError(el);
	},

	newpassCheck: function(el){
		if (el.isEmpty()) {
			return this.showError(el, 'what\'s your new password?');
		}
		return this.removeError(el);
	},

	repeatCheck: function(el){
		if (el.isEmpty()) {
			return this.showError(el, 'repeat your new password please!');
		} else if (el.get('value') !== this.data.newpass.get('value')){
			return this.showError(el, 'uh oh! your passwords don\'t match!');
		}
		return this.removeError(el);
	},

	save: function(){
		var data = this.data;
		if (this.sending) return false;
		if (!this.noErrors()) return this.fireErrors();
		this.sending = true;
		new Request({
			'url': '/AJAX/password_profile.php',
			'data': {
				old_pass: data.current.get('value'),
				new_pass: data.newpass.get('value')
			},
			onRequest: function(){
				$$(data.current, data.newpass, data.repeat).set('disabled', 'disabled');
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				window.location = ('/settings?type=password&saved=' + ((response && response.success) ? 'yes' : 'no'));
			}
		}).send();
	}
});

var _notifications = _tap.register({

	init: function(){
		var main = this.main = $('user-notifications');
/*		this.data = {
			respond: main.getElement('input[name="respond"]'),
			track: main.getElement('input[name="track"]'),
			group: main.getElement('input[name="group"]')
		};
		main.getElement('button.action').addEvent('click', this.save.toHandler(this));
*/
	},

	save: function(){
		var data = this.data;
		new Request({
			url: '/settings?type=notifications',
			method: 'POST',
			data: {
				respond: data.respond.get('checked') ? 1 : 0,
				track: data.track.get('checked') ? 1 :  0,
				join_group: data.group.get('checked') ? 1 : 0,
				submit: 'submit'
			},
			onSuccess: function(){
				window.location = '/settings?type=notifications&saved=yes';
			}
		}).send();
	}

});

// })();
