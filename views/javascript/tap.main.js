Tap = window.Tap || {};

Tap.Main = {

	init: function(){
		var self = this;

		$('login').addEvent('submit', this.onLogin.toHandler(this));

		var signUp = $('signup-form');
		var data = this.data = {
			user: signUp.getElement('input[name="user"]'),
			name: signUp.getElement('input[name="name"]'),
			email: signUp.getElement('input[name="email"]'),
			pass: signUp.getElement('input[name="pass"]'),
			passrepeat: signUp.getElement('input[name="passrepeat"]')
		};
		data.user.addEvent('blur', this.checkUser.toHandler(this));
		data.name.addEvent('blur', this.checkName.toHandler(this));
		data.email.addEvent('blur', this.checkEmail.toHandler(this));
		data.pass.addEvent('blur', this.checkPass.toHandler(this));
		data.passrepeat.addEvent('blur', this.checkPassRepeat.toHandler(this));
		$('signup-submit').addEvent('click', this.onSignup.toHandler(this));
	},

	onLogin: function(el, e){
		e.stop();
		var errors = false;
		var user = $('uname');
		var pass = $('pass');
		if (user.isEmpty()) {
			user.addClass('input-err');
			errors = true;
		} else {
			user.removeClass('input-err');
		}
		if (pass.isEmpty()) {
			pass.addClass('input-err');
			errors = true;
		} else {
			pass.removeClass('input-err');
		}
		if (errors) return;
		mpmetrics.track('login', {}, function(){
			el.submit();
		});
	},

	checkUser: function(el){
		var self = this;
		if (el.isEmpty() || !el.ofLength(4, 20)) {
			return this.showError(el, 'Username must be at least 4 characters');
		} else {
			new Request({
				url: 'AJAX/check_signup.php',
				data: {
					type: 1,
					val: el.get('value')
				},
				onSuccess: function(){
					var response = JSON.decode(this.response.text);
					if (!response.available) {
						self.showError(el, 'This username is already taken.');
					} else {
						self.removeError(el);
					}
				}
			}).send();
		}
		return this.removeError(el);
	},

	checkName: function(el){
		if (el.isEmpty()) {
			return this.showError(el, 'Please enter a name.');
		}
		return this.removeError(el);
	},

	checkEmail: function(el){
		var self = this;
		if (el.isEmpty() || !el.isEmail()) {
			return this.showError(el, 'Please enter a valid email.');
		} else {
			new Request({
				url: 'AJAX/check_signup.php',
				data: {
					type: 2,
					val: el.get('value')
				},
				onSuccess: function(){
					var response = JSON.decode(this.response.text);
					if (!response.available) {
						self.showError(el, 'This email is already used.');
					} else {
						self.removeError(el);
					}
				}
			}).send();
		}
		return this.removeError(el);
	},

	checkPass: function(el){
		if (el.isEmpty() || !el.ofLength(6, 20)) {
			return this.showError(el, 'Password must be at least 6 characters.');
		}
		return this.removeError(el);
	},

	checkPassRepeat: function(el){
		var data = this.data;
		if (el.isEmpty()) {
			return this.showError(el, 'Repeat your password.');
		} else if (el.get('value') !== data.pass.get('value')) {
			return this.showError(el, 'Your passwords don\'t match.');
		}
		return this.removeError(el);
	},

	showError: function(el, error){
		var msg = el.getNext('div.guide');
		msg.set('text', error);
		msg.setStyle('display', 'block');
		el.addClass('input-err');
		el.store('passed', false);
		return this;
	},

	removeError: function(el){
		var msg = el.getNext('div.guide');
		msg.setStyle('display', 'none');
		el.removeClass('input-err');
		el.store('passed', true);
		return this;
	},

	noErrors: function(){
		return !$H(this.data).getValues().map(function(item){
			return !!item.retrieve('passed');
		}).contains(false);
	},

	onSignup: function(el, e){
		var data = this.data;
		if (this.noErrors()) {
			new Request({
				url: 'AJAX/ajaz_sign_up.php',
				data: {
					uname: data.user.get('value'),
					fname: data.name.get('value'),
					email: data.email.get('value'),
					pass: data.pass.get('value'),
					fid: 0,
					signup_flag: 'signup_function();'
				},
				onSuccess: function(){
					mpmetrics.track('signup', {'success' : 'true'}, function(){
						window.location.reload();
					});
				}
			}).send();
		} else {
			$$($H(data).getValues().filter(function(item){
				return !item.retrieve('passed');
			})).fireEvent('blur', [e]);
		}
	}
};

window.addEvent('domready', Tap.Main.init.bind(Tap.Main));