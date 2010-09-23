/*
script: creategroups.js
	Controls the group creation interface.
*/

// UNCOMMENT FOR PROD
// (function(){

var _settings = {};

_settings.menus = _tap.register({

	init: function(){
		_body.addEvents({
			'click:relay(a.tab)': this.doAction.toHandler(this)
		});
	},

	doAction: function(el, e){
        e.stop();

        $$('div.form.item.selected')[0].removeClass('selected');
        $(el.get('data-name')).addClass('selected');

        $$('a.tab.selected')[0].removeClass('selected');
        el.addClass('selected');
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

_settings.account = _tap.register({

	mixins: 'errors',

	init: function(){
		var main = this.main = $('user-account');
		var data = this.data = {
			email: main.getElement('input[name="email"]').store('passed', true),
			private: main.getElement('input[name="private"]').store('passed', true),
			about: main.getElement('input[name="about"]').store('passed', true),
			fname: main.getElement('input[name="firstname"]').store('passed', true),
			lname: main.getElement('input[name="lastname"]').store('passed', true),
			lang: main.getElement('select[name="language"]').store('passed', true),
			uname: main.getElement('input[name="username"]').store('passed', true),
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
		main.getElement('button#action-user').addEvent('click', this.save.toHandler(this));
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
			'url': '/AJAX/user/profile/edit.php',
			'data': {
				about: data.about.get('value'),
				fname: data.fname.get('value'),
				lname: data.lname.get('value'),
				uname: data.uname.get('value'),
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

_settings.picture = _tap.register({

	mixins: 'errors',

	init: function(){
		var main = this.main = $('user-picture');
		this.uploader = $('pic-uploader');
		this.uploader.addEvent('change', this.check.toHandler(this));
		window.addEvent('uploaded', this.set.bind(this));
		main.getElement('button#action-picture').addEvent('click', this.save.toHandler(this));
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
			'url': '/AJAX/user/profile/edit.php',
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

_settings.password = _tap.register({

	mixins: 'errors',

	init: function(){
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
		main.getElement('button#action-pass').addEvent('click', this.save.toHandler(this));
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
			'url': '/AJAX/user/profile/password.php',
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

_settings.notifications = _tap.register({

	init: function(){
		var main = this.main = $('user-notifications');
/*		this.data = {
			respond: main.getElement('input[name="respond"]'),
			track: main.getElement('input[name="track"]'),
			group: main.getElement('input[name="group"]')
		};
		main.getElement('button#action-notify').addEvent('click', this.save.toHandler(this));
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
				autotrack: data.autotrack.get('checked') ? 1 :  0,
				join_group: data.group.get('checked') ? 1 : 0,
				submit: 'submit'
			},
			onSuccess: function(){
				window.location = '/settings?type=notifications&saved=yes';
			}
		}).send();
	}

});

_settings.facebook = _tap.register({
    mixins: 'errors',

    init: function() {
		var main = this.main = $('user-facebook');
        this.fbelem = $('fb-button');
        main.getElement('button#action-facebook').addEvent('click', this.save.toHandler(this));
        
        this.subscribe({
            'facebook.logged_in': this.onLoggedIn.bind(this),
        });
    },

    onLoggedIn: function() {
        this.removeError(this.fbelem);
    },

    save: function() {
         var self = this;
         
         FB.getLoginStatus(function(response) {
            if (response.session) {
                self.bindToFB();
            } else {
                self.showError(self.fbelem, 'you should login into your facebook account first');
            }
        });
    },

    bindToFB: function() {
        var self = this,
            data = {'action': 'bind'};
        new Request({
            url: '/AJAX/user/facebook.php',
            method: 'POST',
            data: data,
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (response.success) {
                    window.location = ('/settings?type=facebook&saved=yes');
                } else {
                    if (response.reason == 'already binded') 
                        self.showError(self.fbelem, 'your account already binded to facebook');
                    else if (response.reason == 'binded by someone')
                        self.showError(self.fbelem, 'facebook account you logged in is already binded by someone else');
                    else
                        self.showError(self.fbelem, 'something went wrong durning account binding');
                }
            }
        }).send();
    }
});

// })();
