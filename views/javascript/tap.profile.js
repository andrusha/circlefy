Tap.Profile = {

	init: function(){
		var self = this;
		var body = $(document.body);
		$('gr-create-success').set('tween', {duration: 3000}).fade('hide');
		$('flagger').setStyle('display', 'none').addEvent('load', function(){
			this.setStyle('display', 'block');
		});

		var account_data = {
			fname: body.getElement('input[name="fname"]').store('passed', true),
			lname: body.getElement('input[name="lname"]').store('passed', true),
			email: body.getElement('input[name="email"]').store('passed', true),
			lang: body.getElement('select[name="language"]').store('passed', true),
			country: body.getElement('select[name="country"]').store('passed', true)
			/*
			state: body.getElement('select[name="state"]').store('passed', true),
			region: body.getElement('select[name="region"]').store('passed', true),
			town: body.getElement('select[name="town"]').store('passed', true)
			*/
		};

		account_data.fname.addEvent('blur', this.checkFname.toHandler(this));
		account_data.lname.addEvent('blur', this.checkLname.toHandler(this));
		account_data.email.addEvent('blur', this.checkEmail.toHandler(this));
		// account_data.zip.addEvent('blur', this.checkZip.toHandler(this));

		var pass_data = {
			current: body.getElement('input[name="current"]'),
			newpass: body.getElement('input[name="newpass"]'),
			repeat: body.getElement('input[name="newpass_repeat"]')
		};

		pass_data.current.addEvent('blur', this.checkCurrent.toHandler(this));
		pass_data.newpass.addEvent('blur', this.checkNew.toHandler(this));
		pass_data.repeat.addEvent('blur', this.checkRepeat.toHandler(this));

		var tabs = body.getElements('.tab');
		var panels = body.getElements('.panel');
		tabs.addEvent('click', function(){
			panels.filter('.selected').removeClass('selected');
			tabs.filter('.on').removeClass('on');
			this.addClass('on');

			if (this.get('id') === 'account') {
				$('youraccount').addClass('selected');
				self.data = account_data;
			} else {
				$('passchange').addClass('selected');
				self.data = pass_data;
			}
		});
		tabs.getLast().fireEvent('click');

		$('saveprofile').addEvent('click', this.onProfileSave.toHandler(this));
		$('savepassword').addEvent('click', this.onPasswordSave.toHandler(this));
		$('pic-uploader').addEvent('change', this.uploadPic.toHandler(this));
		window.addEvent('uploaded', this.displayPic.bind(this));

		var country = account_data.country;
		/*
			state = account_data.state,
			region = account_data.region,
			town = account_data.town;
			state.getParent('h2').hide(); region.getParent('h2').hide(); town.getParent('h2').hide();
		*/
		country.addEvent('change', function(a, e){
			$('flagger').set('src', 'images/geo_pics/gif/' + this.get('value') + '.gif');
			/*
			state.empty().getParent('h2').hide();
			region.empty().getParent('h2').hide();
			town.empty().getParent('h2').hide();
			self.geo({
				type: 'state',
				key: this.get('value'),
				callback: (!e) ? null : function(select){
					if (Tap.Vars.state) {
						select.getElement('option[value="'+Tap.Vars.state+'"]').set('selected', 'selected');
						select.fireEvent('change', [{}, true]);
					}
				}
			});
			*/
		});
		/*
		state.addEvent('change', function(a, e){
			region.empty().getParent('h2').hide();
			town.empty().getParent('h2').hide();
			self.geo({
				type: 'region',
				key: this.get('value'),
				callback: (!e) ? null : function(select){
					if (Tap.Vars.region) {
						select.getElement('option[value="'+Tap.Vars.region+'"]').set('selected', 'selected');
						select.fireEvent('change', [{}, true]);
					}
				}
			});
		});
		region.addEvent('change', function(a, e){
			town.empty().getParent('h2').hide();
			self.geo({
				type: 'town',
				key: this.get('value'),
				state: state.get('value'),
				callback: (!e) ? null : function(select){
					if (Tap.Vars.region) {
						select.getElement('option[value="'+Tap.Vars.region+'"]').set('selected', 'selected');
						select.fireEvent('change', [{}, true]);
					}
				}
			});
		});
		*/

		if (Tap.Vars.country) {
			country.getElement('option[value="'+Tap.Vars.country+'"]').set('selected', 'selected');
		} else {
			country.getElement('option[value="us"]').set('selected', 'selected');
		}
		country.fireEvent('change', [{}, true]);

		if (!Tap.Vars.lang.isEmpty()) account_data.lang.getElement('option[value="'+Tap.Vars.lang+'"]').set('selected', 'selected');
	},

	geo: function(options){
		var self = this;
		var loader = $('geo-loader');
		var type_code = ({
			'state': 1,
			'region': 2,
			'town': 3
		})[options.type];
		new Request({
			url: 'AJAX/geo.php',
			data: (function(){
				return $extend({
					type: type_code,
					code: options.key
				}, options.state ? { state: options.state } : {});
			})(),
			onRequest: function(){
				$('geo-loader').set('text', 'Loading ' + options.type.capitalize() + 's').setStyle('display', 'block');
			},
			onSuccess: function(){
				$('geo-loader').setStyle('display', 'none');
				var response = JSON.decode(this.response.text);
				var select = $(document.body).getElement('select[name="' + options.type + '"]');
				select.empty();
				if (response.geo) {
					select.getParent('h2').show();
					for (var x = response.geo.reverse().length; x--; ) {
						var item = response.geo[x];
						new Element('option', {
							value: item.region,
							text: item.city
						}).inject(select);
					}
					if ($type(options.callback) == 'function') options.callback.apply(self, [select]);
				} else {
					$('geo-loader').set('text', 'Location Complete!').setStyle('display', 'block');
				}
			}
		}).send();
	},

	uploadPic: function(el){
		if (!el.get('value').test(/\.(gif|png|bmp|jpeg|jpg)$/i)) {
			return this.showError(el, 'You can only upload GIFs, PNGs, BMPs or JPEGs.');
		}
		this.removeError(el);
		this.uploading = true;
		el.getParent('form').submit();
		mpmetrics.track('update-pic', {});
	},

	displayPic: function(data){
		this.uploading = false;
		var pic = $('pic-uploader');
		if (data.success) {
			$('pic-preview').empty().adopt(new Element('img', {
				'src': ['/user_pics/', data.path].join('')
			}));
			this.pic = data.path;
			this.removeError(pic);
		} else {
			this.pic = null;
			this.showError(pic, data.error);
		}
	},

	checkFname: function(el){
		if (el.isEmpty() || !el.isAlpha()) {
			return this.showError(el, 'Please enter a proper firstname');
		}
		return this.removeError(el);
	},

	checkLname: function(el){
		if (el.isEmpty() || !el.isAlpha()) {
			return this.showError(el, 'Please enter a proper lastname');
		}
		return this.removeError(el);
	},

	checkEmail: function(el){
		if (el.isEmpty() || !el.isEmail()) {
			return this.showError(el, 'Please enter a valid email address.');
		}
		return this.removeError(el);
	},

	checkZip: function(el){
		if (el.isEmpty() || !el.isNum()) {
			return this.showError(el, 'Please enter a valid zip code.');
		}
		return this.removeError(el);
	},

	checkCurrent: function(el){
		if (el.isEmpty()) {
			return this.showError(el, 'Please enter your current password.');
		}
		return this.removeError(el);
	},

	checkNew: function(el){
		if (el.isEmpty() || !el.ofLength(6, 40)) {
			return this.showError(el, 'Passwords should be from 6 to 40 characters.');
		} else if (!el.isAlphaNum()) {
			return this.showError(el, 'You have invalid characters in your password.');
		}
		return this.removeError(el);
	},

	checkRepeat: function(el){
		if (el.isEmpty() || el.get('value') !== this.data.newpass.get('value')) {
			return this.showError(el, 'Please repeat your new password.')
		}
		return this.removeError(el);
	},

	showError: function(el, msg){
		var parent = el.getParent('h2');
		el.addClass('input-err').store('passed', false);
		parent.getElement('div.error-msg').set({
			text: msg,
			style: 'display: inline;'
		});
	},

	removeError: function(el){
		var parent = el.getParent('h2');
		el.removeClass('input-err').store('passed', true);
		parent.getElement('div.error-msg').set({
			text: '',
			style: 'display: none;'
		});
	},

	noErrors: function(){
		return !$H(this.data).getValues().map(function(item){
			return !!item.retrieve('passed');
		}).contains(false);
	},

	onProfileSave: function(_, e){
		var data = this.data;
		if (this.uploading) {
			arguments.callee.delay(3000, this, [_, e]);
			return null;
		}
		var send = {
			fname: data.fname.get('value'),
			lname: data.lname.get('value'),
			email: data.email.get('value'),
			lang: data.lang.get('value')
		};
		if (this.pic) $extend(send, { old_name: this.pic });
		if (!data.country.get('value').isEmpty()) send.country = data.country.get('value');
		// if (!data.state.get('value').isEmpty()) send.state = data.state.get('value');
		// if (!data.region.get('value').isEmpty()) send.region = data.region.get('value');
		// if (!data.town.get('value').isEmpty()) send.town = data.town.get('value');
		this.onSave(send, 'edit_profile', e);
		mpmetrics.track('update-profile', {});
	},

	onPasswordSave: function(_, e){
		var data = this.data;
		var send = {
			old_pass: data.current.get('value'),
			new_pass: data.newpass.get('value')
		};
		this.onSave(send, 'password_profile', e, function(){
			$$(data.current, data.newpass, data.newpass_repeat).set('value', '');
		});
		mpmetrics.track('update-password', {});
	},

	onSave: function(send, url, e, callback){
		var self = this;
		var data = this.data;
		if (this.noErrors()) {
			var req = new Request({
				'url': 'AJAX/' + url + '.php',
				'async': false,
				'data': send,
				onSuccess: function(){
					var response = JSON.decode(this.response.text);
					if (response && response.success) {
						$('gr-create-success').set('text', 'Your changes have been saved!').fade('show').highlight().fade('out');
						if ($type(callback) == 'function') callback.apply(self);
					} else {
						$('gr-create-success').set('text', 'There was an error with your request, please try again.').fade('show').highlight('#fdebeb').fade('out');
					}
				}
			});
			req.send();
		} else {
			$$($H(this.data).getValues().filter(function(item){
				return !item.retrieve('passed');
			})).fireEvent('blur', [e]);
		}
	}
};

window.addEvent('domready', Tap.Profile.init.bind(Tap.Profile));