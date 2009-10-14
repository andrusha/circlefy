Tap = window.Tap || {};

var EasyOver = new Class({

	Implements: [Events, Options],

	options: {
		classname: 'easy-over-text'
	},

	initialize: function(el, options){
		this.setOptions(options);
		this.element = $(el);
		this.overlay = new Element('div', {
			'class': this.options.classname,
			'text': this.element.get('alt') || 'Tap these people..',
			'styles': {
				'display': 'none',
				'position': 'absolute',
				'z-index': '1',
				'padding': '4px 10px',
				'color': '#ccc'
			}
		}).addEvent('click', this.onOverlayClick.bind(this));
		this.overlay.inject(document.body);
		this.setup();
	},

	setup: function(){
		var position = this.element.getCoordinates();
		this.overlay.set('styles', {
			left: position.left,
			top: position.top
		});
	},

	hide: function(){
		this.overlay.set('styles', { display: 'none' });
		return this;
	},

	show: function(){
		this.overlay.set('styles', { display: 'block' });
		return this;
	},

	onOverlayClick: function(e){
		e.stop();
		this.hide();
		this.element.fireEvent('focus');
	}

});

Tap.Home = {

	feedView: 'gid_all',
	currentTap: Tap.Vars.currentTap,
	currentStream: Tap.Vars.currentStream,
	currentSearch: null,

	settings: {},

	pushed: {},
	typing: {},
	tapper: {
		msg: '',
		people: []
	},

	init: function(){
		this.changeDates();
		this.changeDates.periodical(60000, this);
		var self = this;
		var body = $(document.body);
		this.mainStream = $('main-stream');

		$('main').getElements('.tap-msg, .tap-respond-last').each(function(item){
			item.set('html', item.get('html').linkify());
		});
		body.addEvents({
			'click:relay(li.group a)': this.changeFeed.toHandler(this),
			'click:relay(a.reset-feed)': this.clearSearch.toHandler(this),
			'click:relay(a.tap-respond)': function(e){
				e.preventDefault();
				self.showResponseBox(this, e);
				self.initResponse(this);
			},
			'click:relay(a.tap-respond-cancel)': this.hideResponseBox.toHandler(this),
			'keypress:relay(input.tap-response)': function(e){
				if (e.key == 'enter') {
					self.sendResponse(this);
				} else {
					self.typing(this);
				}
			},
			'click:relay(a.add-tap-to)': function(){
				var id = this.getParent('li').get('id').remove(/gid_/);
				var link = this.getPrevious('a');
				var type = this.get('grtype');
				self.changeTapper(link.get('title'), link.get('grsymbol').toLowerCase(), id, type);
			}
		});

		var easy = new EasyOver('tap-box-people').show();
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
				},
				onFocus: function(){
					easy.hide();
				},
				onBlur: function(){
					if (this.getValues().length == 0
						&& this.list.getElement('input.textboxlist-bit-editable-input').get('value').isEmpty()) easy.show();
				}
			})
		};
		new OverText(tapper.msg, { positionOptions: { offset: {x: 6, y: 6}}}).show();
		$('tap-box-send').addEvent('click', this.sendTap.toHandler(this));

		$('tap-notify').slide('hide').addEvent('click', this.getPushed.toHandler(this));

		/*
			.set('slide', {
				onStart: function(){
					$('tap-notify').getParent('div').set('styles', { width: 548 });
				},
				onComplete: function(){
					if (this.wrapper['offset' + this.layout.capitalize()] == 0) {
						$('tap-notify').getParent('div').set('styles', { width: 0 });
					}
				}
			})
		*/

		this.tapSearch = $('tap-feed-search');
		new OverText(this.tapSearch, { positionOptions: { offset: {x: 6, y: 4}}}).show();
		this.tapSearch.addEvent('keypress', function(e){
			if (e.key == 'enter') self.searchFeed();
		});

		Tap.Push.addEvents({
			'connect': this.setChannels.bind(this),
			'typing': this.typingIndicator.bind(this),
			'response': this.parseResponse.bind(this),
			'notification': this.processPushed.bind(this)
		});

		var button = this.initOutside();
		button.addEvent('click', this.showOutside.toHandler(this));
		$('tap-feed-outside-more').addEvent('outerClick', function(e){
			if ($(e.target) !== button) self.hideOutside();
		});
		$('tap-feed-outside').addEvent('click', this.toggleOutside.toHandler(this));
	},

	// GEN FUNCS

	setChannels: function(){
		Tap.Push.sendCIDs([].combine(this.currentStream).combine(["" + this.currentTap]));
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
		var box = el.getParent('li').getElement('.tap-response-box');
		var input = box.getElement('input.tap-response');
		input.set('value', '');
		var overtext = input.retrieve('overtext') || new OverText(input, {
			positionOptions: {
				offset: {x: 6, y: 6}
			}
		});
		input.store('overtext', overtext);
		box.setStyle('display', 'block');
		overtext.show();
	},

	hideResponseBox: function(el){
		var box = el.getParent('li').getElement('.tap-response-box');
		var input = box.getElement('input.tap-response');
		if (input.retrieve('overtext')) input.retrieve('overtext').hide();
		box.setStyle('display', 'none');
	},

	// TYPING

	typing_timeout : '',
	indic_timeout : '',
	indic: '',
	typing: function(el){
		//EDIT BY taso
		var parent = el.getParent('li');
		if (parent.retrieve('typing')) return null;
		//This is the timer when the send will reset
		self.typing_timeout = (function(){ parent.store('typing',false); }).delay(1500);
		parent.store('typing', true);
		var id = parent.get('id').remove(/yid_/).remove(/tid_/);
		new Request({
			url: 'AJAX/typing.php',
			data: {
				cid: id,
				response: 1
			}
		}).send();
	},

	typingIndicator: function(cid){
		var typing = this.typing;
		var el = $('yid_' + cid) || $('tid_' + cid);
		if (el) {
			self.indic = el.getElement('span.tap-typing');
			$clear(self.indic_timeout);

			//This is the timer when the indicator will reset
			self.indic_timeout = (function(){ self.indic.set('html', ''); }).delay(2500);
			self.indic.set('html', '<span style="color:#518E3E; font-size:10px;">(Someone\'s typing)</span>');
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

	// RESPONSES

	initResponse: function(el){
		if (el.retrieve('loaded')) return null;
		var parent = el.getParent('li');
		var id = parent.get('id').remove(/yid_/).remove(/tid_/);
		var box = parent.getElement('.tap-chat');
		new Request({
			url: 'AJAX/load_responses.php',
			data: {
				cid: id
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				if (response.responses) {
					box.removeClass('noresp').empty();
					for (var x = response.responses.reverse().length; x--; ){
						var item = response.responses[x];
						new Element('li', {
							html: '<strong>{uname}:</strong> {chat_text}'.substitute(item).linkify()
						}).inject(box);
					}
					box.scrollTo(0, box.getScrollSize().y);
				}
				el.store('loaded', true);
			}
		}).send();
		this.fireEvent('loadResponses');
	},

	sendResponse: function(el){
		var parent = el.getParent('li');
		var id = parent.get('id').remove(/yid_/).remove(/tid_/);
		var msg = el.get('value');
		if (msg.isEmpty()) return null;
		new Request({
			url: 'AJAX/respond.php',
			data: {
				cid: id,
				response: msg
			},
			onRequest: function(){
				el.set('value', '');
				el.focus();
			},
			onSuccess: function(){
				// var response = JSON.decode(this.response.text);
				var response = this.response.text;
			}
		}).send();
		this.fireEvent('sendResponse');

/* EDIT BY taso
		new Request({
			url: 'AJAX/typing.php',
			data: {
				cid: id,
				response: 0
			},
			onComplete: function(){
				parent.store('typing', false);
			}
		}).send();
*/
	},

	handleResponse: function(cid, response){
		var parent = $try(function(){ return $('tid_' + cid).getElement('.tap-chat'); });
		if (parent) {
			new Element('li', {
				html: '<strong>{uname}:</strong> {chat_text}'.substitute(item)
			}).inject(parent);
			parent.scrollTo(0, parent.getScrollSize().y);
		}
	},

	parseResponse: function(id, user, msg){
		var item = new Element('li', {
			html: '<strong>{uname}:</strong> {chat_text}'.substitute({
				uname: user,
				chat_text: msg
			}).linkify()
		});
		var parent;
		parent = $('tid_' + id);
		if (parent) {
			var box = parent.getElement('ul.tap-chat');
			item.inject(box, 'bottom').set('tween', {duration:800}).highlight('#d5fbc9');
			box.removeClass('noresp');
			box.scrollTo(0, box.getScrollSize().y);
			parent.getElement('.tap-respond').removeClass('noresp');
			var counter = parent.getElement('span.tap-respond-count');
			var count = (function(){
				var c = counter.get('text').match(/\(([\d]+)\)/);
				return ($type(c) == 'array') ? (c[1] * 1) : 0;
			})();
			counter.set('text', ['(', count + 1, ')'].join(''));
			var last = parent.getElement('p.tap-respond-last');
			last.removeClass('noresp').set('html', ['<strong>', user, ':</strong> ', (msg || '').linkify()].join(''));
		}
		parent = $('yid_' + id);
		if (parent) {
			var newbox = parent.getElement('ul.tap-chat');
			item.clone().inject(newbox, 'bottom').set('tween', {duration:800}).highlight('#d5fbc9');
			newbox.scrollTo(0, newbox.getScrollSize().y);
			newbox.removeClass('noresp');
			parent.getElement('.tap-respond').removeClass('noresp');
			var counter = parent.getElement('span.tap-respond-count');
			var count = (function(){
				var c = counter.get('text').match(/\(([\d]+)\)/);
				return ($type(c) == 'array') ? (c[1] * 1) : 0;
			})();
			counter.set('text', ['(', count + 1, ')'].join(''));
			var last = parent.getElement('p.tap-respond-last');
			last.removeClass('noresp').set('html', ['<strong>', user, ':</strong> ', (msg || '').linkify()].join(''));
		}
	},

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
					var outside = self.getSettings();
					if (outside.outside) {
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
					self.changeDates();
				}
			}).send();
			this.fireEvent('searchFeed', keyword);
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
		var button = $('tap-feed-outside-drop');
		var coordinates = $('tap-feed-outside').getCoordinates();
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
			left: (coordinates.left),
			top: (coordinates.top + coordinates.height + 1 + (Browser.Engine.webkit ? 24 : 0))
		});

		save.addEvent('click', this.saveOutside.toHandler(this));
		return button;
	},

	getSettings: function(gid){
		gid = gid || this.feedView;
		if (!this.settings[gid]) {
			this.settings[gid] = {
				outside: false,
				people: []
			};
		}
		return this.settings[gid];
	},

	setSettings: function(gid){
		var settings = this.getSettings(gid || this.feedView);
		$('tap-feed-outside').set('text', 'Outside: ' + (settings.outside ? 'On' : 'Off'));
	},

	showOutside: function(){
		var settings = this.getSettings();
		var more = $('tap-feed-outside-more');
		var people = more.retrieve('from');
		people.empty();
		if ($type(settings.people) == 'array' && settings.people.length > 0) {
			people.setValues(settings.people);
		}
		more.set('styles', {display: 'block'});
	},

	hideOutside: function(){
		var more = $('tap-feed-outside-more');
		more.set('styles', {display: 'none'});
	},

	toggleOutside: function(el){
		var settings = this.getSettings();
		var value = el.get('text').remove(/Outside:\s/);
		if (value == 'On') {
			settings.outside = false;
			el.set('text', 'Outside: Off');
		} else {
			settings.outside = true;
			el.set('text', 'Outside: On');
		}
		if (this.currentSearch) return this.searchFeed();
		this.changeFeed(this.feedView, true);
	},

	saveOutside: function(){
		var settings = this.getSettings();
		var more = $('tap-feed-outside-more');
		var people = more.retrieve('from');
		settings.people = people.getValues();
		this.hideOutside();
		settings.outside = true;
		this.setSettings();
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
		var settings = this.getSettings(gid);
		new Request({
			url: 'AJAX/filter_creator.php',
			data: (function(){
				var data = (id === null) ? {type: 11} : {type:1, id: id};
				if (settings.outside) {
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
				if (!force) {
					$('tap-feed-name').set('text', el.getElement('a').get('title'));
					$('tap-feed-icon').set('src', el.getElement('img').get('src'));
				}
				self.feedView = gid;
				self.setSettings();
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
				self.changeDates();
				$('tap-notify').slide('hide');
			}
		}).send();
	},

	changeTapper: function(name, symbol, id, type){
		type = (type === '0') ? 'group' : (type === '2') ? 'building' : 'book_open';
		var val = [[name, [name, symbol, '0', id].join(':'), "<img src='images\/icons\/"+type+".png' \/> " + symbol, name + " <img src='images\/icons\/"+type+".png' \/><span class='online online'>online (218)<\/span>"]];
		var values = this.tapper.people.getValues().combine(val);
		this.tapper.people.empty();
		this.tapper.people.setValues(values);
		$('tap-box-people').fireEvent('focus');
		/*
		if (this.tapper.msg.get('value').isEmpty()) {
			this.tapper.people.empty();
			if (id == null) return;
			Tap.Home.tapper.people.setValues([]);
			$('tap-box-people').fireEvent('focus');
		}
		*/
	},

	// TAP SENDING

	sendTap: function(_, e){
		if (this.sending) return;
		this.sending = true;
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
					self.setChannels();
					var items = new Element('div', {
						html: self.parseTemplate('taps', response.new_msg)
					});
					$('your-stream').empty();
					var x = items.getElement('li');
					x.set('id', x.get('id').replace('tid', 'yid'));
					items.getElements('li').reverse().inject('your-stream', 'top');
					self.changeDates();
				}
				self.sending = false;
				data.people.empty();
				data.msg.set('value', '');
				data.msg.fireEvent('blur', e);
			}
		}).send();
		this.fireEvent('sendTap', to_box.length);
	},

	processPushed: function(data){
		var pushed = this.pushed;
		data = data.map(function(item){
			return item.link({
				x: Number.type,
				type: String.type,
				y: Boolean.type,
				cid: Number.type,
				z: String.type
			});
		});
		for (var x = data.reverse().length; x--;) {
			var item = data[x];
			if (!pushed[item.type]) pushed[item.type] = [];
			var type = pushed[item.type];
			type.include(item.cid);

			var key = (this.feedView === 'gid_all') ? 'groups' : this.feedView.replace('gid', 'group');
			/*if (key === item.type && type.length > 0 && item.cid !== this.currentTap) {*/


			if (key === item.type && type.length > 0 ) {
			if( item.cid !== this.currentTap ) { }
				/*var length = type.length - (type.contains(this.currentTap) ? 1 : 0);*/
				var length = type.length;
				var notify = ['You have', length, 'new', length == 1 ? 'tap,' : 'taps,', 'click here to show them.'].join(' ');
				$('tap-notify').set({
					text: notify
				}).store('type', item.type).slide('in');
			} else if (item.type.contains('group') && this.feedView !== 'gid_all') {
				$((item.type == 'groups')
				  ? 'gid_all'
				  : item.type.replace('group', 'gid')
				).getElement('span.counter').set('text', type.length);
			}
		}
	},

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
				el.slide('out');
				var response = JSON.decode(this.response.text);
				if (response.results) {
					self.mainStream.getElements('div.noresults').destroy();
					data.empty();
					response.data = response.data.filter(function(item){
						return !!item.cid;
					});
					var items = new Element('div', {
						html: self.parseTemplate('taps', response.data)
					});
					self.currentStream = self.currentStream.combine(response.data.map(function(item){
						return $type(item.cid) == 'string' ? item.cid : "" + item.cid;
					}));
					self.setChannels();
					items.getElements('li').reverse().inject('main-stream', 'top');
					self.changeDates();
				}
			}
		}).send();
	},

	changeDates: function(){
		var now = new Date().getTime();
		$$('.tap-time').each(function(el){
			var timestamp = el.className.remove(/tap-time\s/);
			var orig = new Date((timestamp * 1) * 1000);
			var diff = ((now - orig) / 1000);
			var day_diff = Math.floor(diff / 86400);
			if ($type(diff) == false || day_diff < 0 || day_diff >= 31) return false;
			el.set('text', day_diff == 0 && (
					// diff < 60 && "Just Now" ||
					diff < 120 && "Just Now" ||
					diff < 3600 && Math.floor( diff / 60 ) + " minutes ago" ||
					diff < 7200 && "An hour ago" ||
					diff < 86400 && Math.floor( diff / 3600 ) + " hours ago") ||
				day_diff == 1 && "Yesterday" ||
				day_diff < 7 && day_diff + " days ago" ||
				day_diff < 31 && Math.ceil( day_diff / 7 ) + " weeks ago");
		});
	}

};

$extend(Tap.Home, new Events);
window.addEvent('domready', Tap.Home.init.bind(Tap.Home));
