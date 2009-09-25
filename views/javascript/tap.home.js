Tap = window.Tap || {};

Tap.Home = {

	feedView: 'gid_all',
	currentTap: Tap.Vars.currentTap,
	currentStream: Tap.Vars.currentStream,
	currentSearch: null,

	settings: new Hash.Cookie('tap-options'),

	pushed: {},
	typing: {},
	tapper: {
		msg: '',
		people: []
	},

	init: function(){
		var self = this;
		var body = $(document.body);
		this.mainStream = $('main-stream');
		this.settings.load();
		this.settings.empty();

		body.addEvents({
			'click:relay(li.group a)': this.changeFeed.toHandler(this),
			'click:relay(a.reset-feed)': this.clearSearch.toHandler(this),
			'click:relay(a.tap-respond)': this.showResponseBox.toHandler(this),
			'click:relay(a.tap-respond-cancel)': this.hideResponseBox.toHandler(this),
			'keypress:relay(textarea.tap-response)': this.typing.toListener(this)
		});

		var tapper = this.tapper = {
			msg: $('tap-box-msg'),
			more: $('tap-box-more'),
			people: new TextboxList('tap-box-people', {
				unique: true,
				plugins: {
					autocomplete: {
						minLength: 3, maxResults: 5, queryRemote: true,
						remote: { url: 'AJAX/search_assoc.php' }
					}
				}
			})
		};
		new OverText(tapper.msg, { positionOptions: { offset: {x: 6, y: 6}}}).show();
		$('tap-box-send').addEvent('click', this.sendTap.toHandler(this));

		$('tap-notify').addEvent('click', this.getPushed.toHandler(this));

		this.tapSearch = $('tap-feed-search');
		new OverText(this.tapSearch, { positionOptions: { offset: {x: 6, y: 4}}}).show();
		this.tapSearch.addEvent('keypress', function(e){
			if (e.key == 'enter') self.searchFeed();
		});

		Tap.Push.addEvents({
			'connect': this.setChannels.bind(this),
			'typing': this.typingIndicator.bind(this)
		});

		var button = this.initOutside();
		button.addEvent('click', this.showOutside.toHandler(this));
		$('tap-feed-outside-more').addEvent('outerClick', function(e){
			if ($(e.target) !== button) self.hideOutside();
		});
	},

	// GEN FUNCS

	setChannels: function(){
		Tap.Push.sendCIDs([].combine(this.currentStream).combine("" + this.currentTap));
	},

	parseTemplate: function(type, data){
		var template = $(({
			taps: 'template-bit'
		})[type]).innerHTML.cleanup();
		if (!this.templater) this.templater = new Template();
		return this.templater.parse(template, data);
	},

	// RESPONSES

	showResponseBox: function(el){
		el.getParent('li').getElement('.tap-response-box').setStyle('display', 'block');
	},

	hideResponseBox: function(el){
		el.getParent('li').getElement('.tap-response-box').setStyle('display', 'none');
	},

	// TYPING

	typing: function(el){
		var parent = el.getParent('li');
		var id = parent.get('id').remove(/tid_/);
		new Request({
			url: 'AJAX/typing.php',
			data: {
				cid: id
			}
		}).send();
	},

	typingIndicator: function(cid){
		var typing = this.typing;
		var el = $('tid_' + cid);
		if (el) {
			var indic = el.getElement('span.tap-typing');
			indic.set('text', '(Someone\'s typing)');
			(function(){ indic.set('text', ''); }).delay(500);
		}
	},

	// SLIDING TAP BOX
	/*

	showTapMore: function(){
		var tapper = this.tapper;
		tapper.tapping = true;
		tapper.more.slide('in');
	},

	hideTapMore: function(){
		var tapper = this.tapper;
		(function(){
			if (!tapper.tapping) {
				tapper.more.slide(tapper.msg.get('value').isEmpty()
					&& tapper.people.getValues().length == 0 ? 'out' : 'in');
			}
			tapper.tapping = false;
		}).delay(500);
	},

	*/

	// SEARCH

	searchFeed: function(){
		var self = this;
		var keyword = this.tapSearch.get('value');
		if (!keyword.isEmpty()) {
			this.currentSearch = keyword;
			new Request({
				url: 'AJAX/filter_creator.php',
				data: (function(){
					var data = $extend((self.feedView == 'gid_all')
						? {type: 11}
						: {type: 1, id: self.feedView.remove(/gid_/)}
						, {search: keyword});
					var outside = self.settings.get(self.feedView);
					if (outside && outside.outside) {
						data.outside = 1;
						data.o_filter = JSON.encode(outside.people.length > 0 ? outside.people.map(function(item){
							return item[1];
						}) : null);
					}
					return data;
				})(),
				onRequest: function(){
					$('loading-indicator').setStyle('display', 'inline');
				},
				onComplete: function(){
					$('loading-indicator').setStyle('display', 'none');
				},
				onSuccess: function(){
					var response = JSON.decode(this.response.text);
					var items;
					if (response.results) {
						items = new Element('div', {
							html: self.parseTemplate('taps', response.data)
						});
						self.currentStream = response.data.map(function(item){
							return item.cid;
						});
						self.setChannels();
					} else {
						items = new Element('div').adopt($('no-results').clone());
					}
					self.mainStream.empty();
					items.getElements('li').reverse().inject('main-stream', 'top');
				}
			}).send();
		} else {
			this.clearSearch();
		}
	},

	clearSearch: function(){
		this.currentSearch = null;
		this.tapSearch.set('value', '').fireEvent('blur');
		this.changeFeed(this.feedView, true);
		this.tapSearch.blur();
	},

	// OUTSIDES

	initOutside: function(){
		var button = $('tap-feed-outside');
		var coordinates = button.getCoordinates();
		var more = $('tap-feed-outside-more');
		more.store('from', new TextboxList(more.getElement('[name="outside-from"]'), {
			unique: true,
			max: 1,
			plugins: {
				autocomplete: {
					minLength: 3, maxResults: 5, queryRemote: true,
					remote: { url: 'AJAX/search_assoc.php' }
				}
			}
		}));
		var save = $('tap-feed-outside-set');
		more.set('styles', {
			left: (coordinates.left - 100),
			top: (coordinates.top + coordinates.height)
		});

		save.addEvent('click', this.saveOutside.toHandler(this));
		return button;
	},

	showOutside: function(){
		console.log(this.settings);
		var settings = this.settings.get(this.feedView);
		var more = $('tap-feed-outside-more');
		var out = more.getElement('[name="outside-show"]');
		var people = more.retrieve('from');
		out.set('checked', null);
		people.empty();
		if (settings) {
			out.set('checked', (settings.outside) ? 'checked' : null);
			if ($type(settings.people) == 'array' && settings.people.length > 0) {
				people.setValues(settings.people);
			}
		}
		more.set('styles', {display: 'block'});
	},

	hideOutside: function(){
		var more = $('tap-feed-outside-more');
		more.set('styles', {display: 'none'});
	},

	saveOutside: function(){
		var settings = this.settings;
		var more = $('tap-feed-outside-more');
		var out = more.getElement('[name="outside-show"]');
		var people = more.retrieve('from');
		settings.set(this.feedView, {
			outside: out.checked,
			people: people.getValues()
		});
		this.hideOutside();
		if (this.currentSearch) return this.searchFeed();
		this.changeFeed(this.feedView, true);
	},

	// FEED CHANGING

	changeFeed: function(el, force){
		force = (!force || force.stop) ? false : force;
		var self = this;
		var gid = $try(function(){ return el.get('id'); }) || el;
		if (this.feedView === gid && !force) return;
		var id = (gid !== 'gid_all') ? gid.remove(/gid_/) : null;
		var settings = this.settings.get(gid);
		new Request({
			url: 'AJAX/filter_creator.php',
			data: (function(){
				var data = (id === null) ? {type: 11} : {type:1, id: id};
				if (settings && settings.outside && settings.people.length > 0) {
					data.outside = 1;
					data.o_filter = JSON.encode(settings.people.length > 0 ? settings.people.map(function(item){
						return item[1];
					}) : null);
				}
				return data;
			})(),
			onRequest: function(){
				$('loading-indicator').setStyle('display', 'inline');
			},
			onComplete: function(){
				$('loading-indicator').setStyle('display', 'none');
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				if (!force) $('tap-feed-name').set('text', el.getElement('a').get('title'));
				self.feedView = gid;
				var key = (gid === 'gid_all') ? 'groups' : gid.replace('gid', 'group');
				if ($type(self.pushed[key]) == 'array') self.pushed[key].empty();
				if (response.results) {
					var items = new Element('div', {
						html: self.parseTemplate('taps', response.data)
					});
					self.currentStream = response.data.map(function(item){
						return item.cid;
					});
					self.setChannels();
				} else {
					items = new Element('div').adopt($('no-taps').clone());
				}
				self.mainStream.empty();
				items.getElements('li').reverse().inject('main-stream', 'top');
				$('tap-notify').setStyle('display', 'none');
			}
		}).send();
	},

	// TAP SENDING

	sendTap: function(_, e){
		var self = this;
		var data = this.tapper;
		var to_box = data.people.getValues().map(function(item){
			return item[1];
		});
		new Request({
			url: 'AJAX/new_message_handler.php',
			data: {
				msg: data.msg.get('value'),
				to_box: JSON.encode(to_box.length !== 0 ? to_box : null)
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				if (response.new_msg) {
					self.currentTap = (response.new_msg[0].cid * 1);
					var items = new Element('div', {
						html: self.parseTemplate('taps', response.new_msg)
					});
					Tap.Push.sendCIDs(response.new_msg[0].cid);
					$('your-stream').empty();
					items.getElements('li').reverse().inject('your-stream', 'top');
				}
				data.people.empty();
				data.msg.set('value', '');
				data.msg.fireEvent('blur', e);
			}
		}).send();
	},

	/*

	processPushed: function(data){
		var pushed = this.pushed;
		data = JSON.decode(data);
		if (!pushed[data.type]) pushed[data.type] = [];
		var type = pushed[data.type];
		type.include(data.cid);

		var key = (this.feedView === 'gid_all') ? 'groups' : this.feedView.replace('gid', 'group');
		if (key === data.type && type.length > 0 && data.cid !== this.currentTap) {
			var length = type.length - (type.contains(this.currentTap) ? 1 : 0);
			var notify = ['You have', length, 'new', length == 1 ? 'tap' : 'taps'].join(' ');
			$('tap-notify').set({
				text: notify,
				styles: {display: 'inline'}
			}).store('type', data.type).highlight('#5AB2FF');
		} else if (data.type.contains('group') && this.feedView !== 'gid_all') {
			$((data.type == 'groups')
			  ? 'gid_all'
			  : data.type.replace('group', 'gid')
			).getElement('span.counter').set('text', type.length);
		}
	},

	*/

	getPushed: function(el){
		var self = this;
		var type = el.retrieve('type');
		var data = this.pushed[type];
		var template = $('template-bit').innerHTML.cleanup();
		new Request({
			url: 'AJAX/loader.php',
			data: {
				id_list: data.join(',')
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				if (response.results) {
					data.empty();
					var items = new Element('div', {
						html: self.parseTemplate('taps', response.data)
					});
					Tap.Push.sendCIDs(response.data.map(function(item){
						return item.cid;
					}));
					items.getElements('li').reverse().inject('main-stream', 'top');
					items.getParent('div').destroy();
					el.set('styles', {display:'none'});
				}
			}
		}).send();
	}

};

window.addEvent('domready', Tap.Home.init.bind(Tap.Home));