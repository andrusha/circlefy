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

var _edit = _tap.register({

	init: function(){
		var main = this.main = $('create-group');
		var data = this.data = {
			focus: main.getElement('input[name="focus"]').store('passed', true),
			desc: main.getElement('input[name="desc"]').store('passed', true)
		};
		for (var key in data){
			if (this[key + 'Check'] instanceof Function){
				data[key].addEvent('blur', this[key + 'Check'].toHandler(this));
			}
		}
		this.uploader = $('group-pic');
		this.uploader_fav = $('group-pic-fav');

		this.uploader.addEvent('change', this.upload.toHandler(this));
		this.uploader_fav.addEvent('change', this.upload.toHandler(this));

		window.addEvent('uploaded', this.chosePreview.bind(this));

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
		}).contains(false);
	},

	fireErrors: function(){
		var e = {stop: $empty, preventDefault: $empty};
		$$($H(this.data).getValues().filter(function(item){
			return !item.retrieve('passed');
		})).fireEvent('blur', [e]);
		return this;
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
		if (el.isEmpty() || !el.ofLength(20, 340)) {
			return this.showError(el, 'write something that\'s around 20 to 340 characters.');
		} else if (!el.get('value').match(/\s/g) || el.get('value').match(/\s/g).length < 8) {
			return this.showError(el, 'yeah, right. that\'s not a real description');
		}
		return this.removeError(el);
	},

	upload: function(el){
		if (!el.get('value').test(/\.(gif|png|bmp|jpeg|ico|jpg)$/i)){
			return this.showError(el, 'You can only upload gifs, pngs, bmps or jpegs.');
		}
		this.removeError(el);
		this.uploading = true;
		el.getParent('form').submit();
		return this;
	},

	chosePreview: function(data){
	if(data.type == 'picture')
		this.preview(data)
	if(data.type == 'favicon')
		this.preview_fav(data)
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

	preview_fav: function(data){
		var pic = $('preview-fav'),
			input = this.uploader_fav;
		this.uploading = false;
		if (data.success){
			pic.removeClass('blank').set('src', ['/group_pics/', data.path].join(''));
			this.favicon = data.path;
			this.removeError(input);
		} else {
			this.favicon = null;
			this.showError(input, data.error);
		}
		return this;
	},

	submit: function submit(el, e){
		var data = this.data,
			gid = this.main.getData('id');
		if (this.sending) return false;
		if (this.uploading) return setTimeout(function(){ submit(el, e); }, 1000);
		if (!this.noErrors()) { $$('.error')[0].style.display = 'block'; return this.fireErrors(); } 
		this.sending = true;
		var request = new Request({
			url: '/AJAX/group_update.php',
			data: {
				gid: gid,
				focus: data.focus.get('value').trim().rtrim(','),
				descr: data.desc.get('value'),
                'private': ($('create-group').getElement('input[name="private"]').checked ? 1 : 0)
			},
			onRequest: function(){
				$$(data.name, data.symbol, data.focus, data.desc).set('disabled', 'disabled');
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				$$('.error')[0].style.display = 'none';
				$$('.notify')[0].style.display = 'block';
				(function() { window.location = '/groups'}).delay(2000,this);
			}
		});
		if (this.pic) $extend(request.options.data, { pic_hash_name: this.pic });
		if (this.favicon) $extend(request.options.data, { fav_hash_name: this.favicon });
		request.send();
	}

});

// })();
