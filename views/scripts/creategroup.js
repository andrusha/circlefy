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

var _create = _tap.register({

	init: function(){
		var main = $('create-group');
		var data = this.data = {
			name: main.getElement('input[name="name"]'),
			symbol: main.getElement('input[name="symbol"]'),
			focus: main.getElement('input[name="focus"]'),
			desc: main.getElement('input[name="desc"]')
		};
		for (var key in data){
			if (this[key + 'Check'] instanceof Function){
				data[key].addEvent('blur', this[key + 'Check'].toHandler(this));
			}
		}
		this.uploader = $('group-pic');
		this.uploader.addEvent('change', this.upload.toHandler(this));
		window.addEvent('uploaded', this.preview.bind(this));
		main.getElement('button.action').addEvent('click', this.submit.toHandler(this));
		Validator.addType('name', new RegExp("^[A-Za-z0-9:,\.\\\/'\\s]+$"));
	},

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
		}).contains(false) && !!this.pic;
	},

	fireErrors: function(){
		var e = {stop: $empty, preventDefault: $empty};
		$$($H(this.data).getValues().filter(function(item){
			return !item.retrieve('passed');
		})).fireEvent('blur', [e]);
		if (!this.pic) this.showError(this.uploader, 'you\'ll need a group picture');
		return this;
	},

	nameCheck: function(el){
		var self = this,
			name = el.get('value');
		if (el.isEmpty() || !el.ofLength(3, 128)){
			return this.showError(el, 'try something between 2 to 128 characters');
		} else if (!el.isName()){
			return this.showError(el, 'what\'s that symbol? names must only contain letters and numbers');
		} else {
			new Request({
				url: '/AJAX/group_check.php',
				data: {
					group_check: true,
					post: 'name',
					gname: name
				},
				onSuccess: function(){
					var response = JSON.decode(this.response.text);
					if (response.dupe) {
						return self.showError(el, 'this name is already taken! \
							you can join the group for <a href="/groups?search={name}">{name}</a> instead'.substitute({name:name}));
					} else {
						return self.removeError(el);
					}
				}
			}).send();
		}
		return this.removeError(el);
	},

	symbolCheck: function(el){
		var self = this,
			symbol = el.get('value');
		if (el.isEmpty() || !el.ofLength(2, 64)) {
			return this.showError(el, 'try something between 2 to 64 characters');
		} else if (!el.isAlphaNumStrict()) {
			return this.showError(el, 'what\'s that character? symbols must only contain letters and numbers');
		} else {
			new Request({
				url: '/AJAX/group_check.php',
				data: {
					group_check: true,
					post: 'symbol',
					symbol: symbol
				},
				onSuccess: function(){
					var response = JSON.decode(this.response.text);
					if (response.dupe) {
						return self.showError(el, 'this symbol is already taken! \
							you can join the group for <a href="/group/{symbol}">{symbol}</a> instead'.substitute({symbol:symbol}));
					} else {
						self.removeError(el);
					}
				}
			}).send();
		}
		return this.removeError(el);
	},

	focusCheck: function(el){
		var self = this;
		var focuses = el.get('value').trim().rtrim(',').split(',');
		if (focuses.length < 3 || focuses.map(function(f){ return f.isEmpty() && !f.ofLength(2, 100); }).contains(true)) {
			return this.showError(el, 'write at least 3 focuses, separated by commas');
		}
		return this.removeError(el);
	},

	descCheck: function(el){
		var self = this;
		if (el.isEmpty() || !el.ofLength(20, 240)) {
			return this.showError(el, 'write something that\'s around 20 to 240 characters.');
		} else if (!el.get('value').match(/\s/g) || el.get('value').match(/\s/g).length < 5) {
			return this.showError(el, 'yeah, right. that\'s not a real description');
		}
		return this.removeError(el);
	},

	upload: function(el){
		if (!el.get('value').test(/\.(gif|png|bmp|jpeg|jpg)$/i)){
			return this.showError(el, 'you can only upload gifs, pngs, bmps or jpegs.');
		}
		this.removeError(el);
		this.uploading = true;
		el.getParent('form').submit();
		return this;
	},

	preview: function(data){
		var pic = $('preview'),
			input = this.uploader;
		this.uploading = false;
		if (data.success){
			pic.removeClass('blank').set('src', ['/group_pics/', data.path].join(''));
			this.pic = data.path;
			this.removeError(input);
		} else {
			this.pic = null;
			this.showError(input, data.error);
		}
		return this;
	},

	submit: function submit(el, e){
		var data = this.data;
		this.symbol = data.symbol.get('value');
		if (this.sending) return false;
		if (this.uploading) return setTimeout(function(){ submit(el, e); }, 1000);
		if (!this.noErrors()){ $$('.error')[0].style.display = 'block'; return this.fireErrors(); } 
		this.sending = true;
		new Request({
			'url': '/AJAX/group_create.php',
			'data': {
				gname: data.name.get('value'),
				symbol: data.symbol.get('value'),
				focus: data.focus.get('value').trim().rtrim(','),
				descr: data.desc.get('value'),
				old_name: this.pic
			},
			onRequest: function(){
				$$(data.name, data.symbol, data.focus, data.desc).set('disabled', 'disabled');
			},
			onSuccess: function(){
			//	var response = JSON.decode(this.response.text);
				$$('.error')[0].style.display = 'none';
                                $$('.notify')[0].style.display = 'block';
				(function() { window.location = '/group/'+this.symbol}).delay(2000,this);
			}.bind(this)
		}).send();
	}

});

// })();
