Tap = window.Tap || {};

Tap.Public = {
	
	feed: Tap.Vars.feed,
	people: Tap.Vars.people,
	group: Tap.Vars.group,

	init: function(){
		var self = this;
		self.changeDates();
		var search = this.search = $('tap-feed-search');
		this.mainStream = $('main-stream');

		if (search) {
			new OverText(search, { positionOptions: { offset: {x: 6, y: 4}}}).show();
				search.addEvents({
				'keypress': function(e){
					if (e.key == 'enter') self.onSearch(this, e);
				}
				// 'blur': self.onSearch.toHandler(this)
			});
			$(document.body).addEvents({
				'click:relay(a.reset-feed)': function(e){
					e.stop();
					search.set('value', '');
					self.onSearch(search, e);
				},
				'click:relay(a.track)': this.track.toHandler(this),
				'click:relay(a.untrack)': this.track.toHandler(this)
			});
			if (Tap.Vars.feed) {
				Tap.Push.addEvents({
					'connect': this.setChannels.bind(this),
					'viewAdd': this.addViews.bind(this),
					'viewRemove': this.removeViews.bind(this),
					'notification': this.loadNew.bind(this)
				});
			}
		}

		var chat = this.chatbox = $('tap-chat-perma');
		if (chat) {
			Tap.Push.addEvents({
				'connect': function(){
					Tap.Push.sendCIDs([Tap.Vars.tap]);
				},
				'typing': this.typingIndicator.bind(this),
				'response': this.parseResponse.bind(this)
			});
			var chatText = this.chatText = chat.getElement('input.tap-response');
			new OverText(chatText, {positionOptions: {offset: {x: 6, y: 6}}});
			var uid = this.uid = $('tapper-info').getElement('p.tap').get('rel');
			var chatBox = this.chatBox = chat.getElement('ul.tap-chat');
			chatBox.scrollTo(0, chatBox.getScrollSize().y);
			var char_indic = chat.getElement('div.tap-response-counter');
			chatText.addEvents({
				'keypress': function(e){
					if (e.key == 'enter') {
						char_indic.set('text', 240);
						self.sendResponse();
					} else {
						char_indic.set('text', 240 - this.get('value').length);
						self.typing();
					}
				},
				'focus': function(){
					char_indic.set('text', 240 - this.get('value').length);
					char_indic.setStyle('display', 'block');
				},
				'change': function(){
					char_indic.set('text', 240 - this.get('value').length);
				},
				'blur': function(){
					char_indic.set('text', 240 - this.get('value').length);
					if (this.get('value').isEmpty()) char_indic.setStyle('display', 'none');
				}
			});
		}
		
		var msg = $('tap-box-msg');
		if (msg){
			new OverText(msg, { positionOptions: { offset: {x: 6, y: 6}}}).show();
			var char_indic = $('tap-box-counter');
			msg.addEvents({
				'focus': function(){
					char_indic.set('text', 240 - this.get('value').length);
					char_indic.setStyle('display', 'block');
				},
				'keypress': function(){
					char_indic.set('text', 240 - this.get('value').length);
				},
				'change': function(){
					char_indic.set('text', 240 - this.get('value').length);
				},
				'blur': function(){
					char_indic.set('text', 240 - this.get('value').length);
					if (this.get('value').isEmpty()) char_indic.setStyle('display', 'none');
				}
			});
			$('tap-box-send').addEvent('click', this.sendTap.toHandler(this));
		}
		
		(function() {
			var type, data;
			if (Tap.Vars.user) {
				type = 'public-user';
				data = {'user': Tap.Vars.username, 'logged': Tap.Vars.logged ? 'yes' : 'no'};
			} else if (Tap.Vars.group) {
				type = 'public-group';
				data = {'group': Tap.Vars.groupname, 'logged': Tap.Vars.logged ? 'yes' : 'no'};
			} else {
				type = 'public-tap';
				data = {'logged': Tap.Vars.logged ? 'yes' : 'no'};
			}
			self.metrics = {type: type, data: data};
			mpmetrics.track(type, data);
		})();
	},
	
	setChannels: function(){
		Tap.Push.sendCIDs(this.feed, this.people, this.group);
	},
	
	typing: function(){
		var self = this;
		if (this.isTyping) return null;
		(function(){ self.isTyping = false; }).delay(1500);
		this.isTyping = true;
		new Request({
			url: '/AJAX/typing.php',
			data: {
				cid: Tap.Vars.tap,
				response: 1
			}
		}).send();
	},

	typingIndicator: function(cid){
		var self = this;
		if (this.timeout) this.timeout = $clear(this.timeout);
		var indic = $('tapper-info').getElement('span.tap-typing');
		indic.set('html', '(Someone\'s typing) <img src="/images/icons/comment.png" />');
		this.timeout = (function(){ indic.set('html', ''); }).delay(2500);
	},
	
	// VIEWS

	addViews: function(ids){
		for (var x = ids.reverse().length; x--;) {
			var id = ids[x];
			var els = $$('#tid_' + id + ', #yid_' + id).getElement('span.tap-views strong');
			els.each(function(item){
				if (!item) return;
				item.set('text', (item.get('text') * 1) + 1);
			});
		}
	},

	removeViews: function(ids){
		for (var x = ids.reverse().length; x--;) {
			var id = ids[x];
			var els = $$('#tid_' + id + ', #yid_' + id).getElement('span.tap-views strong');
			els.each(function(item){
				var count = (item.get('text') * 1);
				if (count > 0) item.set('text', count - 1);
			});
		}
	},
	
	track: function(el){
		var uid = el.get('id').remove(/uid_/);
		var track = el.hasClass('track');
		new Request({
			url: '/AJAX/track.php',
			data: {
				fid: uid,
				state: (track) ? 1 : 0
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				if (response.success) {
					el.set({
						'text': track ? 'Untrack' : 'Track'
					}).removeClass(track ? 'track' : 'untrack').addClass(track ? 'untrack' : 'track');
				}
			}
		}).send();
	},
	
	loadNew: function(data){
		var self = this, key = 'group_' + Tap.Vars.group, pushed = [];
		data = data.map(function(item){
			return item.link({
				x: Number.type,
				type: String.type,
				y: Boolean.type,
				cid: Number.type,
				perm: Number.type
			});
		});
		for (var x = data.reverse().length; x--;) {
			var item = data[x];
			if (item.type == key) pushed.include(item.cid);
		}
		if (pushed.length == 0) return;
		new Request({
			url: '/AJAX/loader.php',
			data: {
				id_list: pushed.join(',')
			},
			onRequest: function(){
				$('loading-indicator').setStyle('display', 'inline');
			},
			onComplete: function(){
				$('loading-indicator').setStyle('display', 'none');
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				if (response.results) {
					self.mainStream.getElements('div.noresults').destroy();
					response.data = response.data.filter(function(item){
						return !!item.cid;
					});
					var items = new Element('div', {
						html: self.parseTemplate('taps', response.data)
					});
					self.feed = self.feed.combine(response.data.map(function(item){
						return $type(item.cid) == 'string' ? item.cid : "" + item.cid;
					}));
					self.people = self.people.combine(response.data.map(function(item){
						return $type(item.uid) == 'string' ? item.uid : "" + item.uid;
					}));
					self.setChannels();
					items.getElements('li').reverse().inject('main-stream', 'top');
					self.changeDates();
				}
			}
		}).send();
	},
	
	sendTap: function(_, e){
		if (this.sending) return;
		this.sending = true;
		var self = this, msg = $('tap-box-msg');
		new Request({
			url: '/AJAX/new_message_handler.php',
			data: {
				msg: msg.get('value'),
				to_box: Tap.Vars.bit
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				self.sending = false;
				msg.set('value', '');
				msg.fireEvent('blur', e);
			}
		}).send();
		// this.fireEvent('sendTap', to_box.length);
	},

	sendResponse: function(){
		if (!Tap.Vars.logged) return null;
		var self = this;
		var id = Tap.Vars.tap;
		var msg = this.chatText.get('value');
		if (msg.isEmpty()) return null;
		new Request({
			url: '/AJAX/respond.php',
			data: {
				cid: id,
				response: msg,
				init_tapper: this.uid,
				first: (!this.firstResp) ? 1 : 0
			},
			onRequest: function(){
				self.chatText.set('value', '');
				self.chatText.focus();
			},
			onSuccess: function(){
				var response = this.response.text;
				this.firstResp = true;
				self.addConvo();
				mpmetrics.track('public-respond', self.metrics.data);
			}
		}).send();
		// this.fireEvent('sendResponse');
	},

	parseResponse: function(id, user, msg){
		var self = this;
		var timestamp = new Date().getTime();
		var item = new Element('li', {
			html: '<span class="time {time_stamp}">{time}</span><strong>{uname}:</strong> {chat_text}'.substitute({
				uname: user,
				chat_text: msg.linkify(),
				time: "Just Now",
				time_stamp: timestamp
			})
		});
		item.inject(this.chatBox, 'bottom').set('tween', {duration:800}).highlight('#d5fbc9');
		this.chatBox.removeClass('noresp').scrollTo(0, this.chatBox.getScrollSize().y);
		
		// Convo Box
		var list = $('people-list');
		var person = $('person_' + user.replace(' ', ''));
		if (!person) {
			person = new Element('li', {
				'id': 'person_' + user.replace(' ', ''),
				'html': '<a href="/user/'+ user +'">'+ user +'</a> \
				<span class="floater">0<span>'
			}).inject(list);
		}
		list.getElements('.no-convos').destroy();
		var counter = person.getElement('span.floater');
		counter.does(function(){
			this.set('text', this.get('text').toInt() + 1);
		});
		self.changeDates();
	},
	
	addConvo: function(){
		if (this.addedConvo) return null;
		var list = $('people-list');
		var person = $('person_' + (Tap.Vars.uname || "").replace(" ", ""));
		if (!person) {
			new Request({
				url: 'AJAX/add_active.php',
				data: {
					cid: Tap.Vars.tap
				},
				onSuccess: function(){
					var response = this.response.text;
				}
			}).send();
		}
	},

	onSearch: function(el, e){
		var self = this;
		var keyword = el.get('value');
		// if (!keyword.isEmpty()){
			new Request({
				url: '/AJAX/filter_creator.php',
				data: (function(){
					var data = {search: keyword};
					if (Tap.Vars.user) $extend(data, {
						type: 99,
						id: Tap.Vars.user
					});
					if (Tap.Vars.group) $extend(data, {
						type: 1,
						id: Tap.Vars.group
					});
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
						response.data = response.data.filter(function(item){
							return !!item.cid;
						});
						items = new Element('div', {
							html: self.parseTemplate('taps', response.data)
						});
						self.currentStream = response.data.map(function(item){
							return item.cid;
						});
					} else {
						items = new Element('div').adopt($('no-results').clone());
					}
					self.mainStream.empty();
					items.getElements('li').reverse().inject('main-stream', 'top');
					self.changeDates();
					mpmetrics.track('public-search', $merge(self.metrics.data, {keyword: keyword}));
				}
			}).send();
	},

	parseTemplate: function(type, data){
		var template = $(({
			taps: 'template-bit'
		})[type]).innerHTML.cleanup();
		if (!this.templater) this.templater = new Template();
		return this.templater.parse(template, data);
	},
	
	changeDates: function(){
		var now = new Date().getTime();
		$$('.tap-time, span.time').each(function(el){
			var timestamp = el.className.remove(/tap-time\s/).remove(/time\s/);
			var orig = new Date(timestamp.toInt() * 1000);
			var diff = ((now - orig) / 1000);
			var day_diff = Math.floor(diff / 86400);
			if ($type(diff) == false || day_diff < 0 || day_diff >= 31) return false;
			el.set('text', day_diff == 0 && (
					// diff < 60 && "Just Now" ||
					diff < 120 && "Just Now" ||
					diff < 3600 && Math.floor( diff / 60 ) + "min ago" ||
					diff < 7200 && "An hour ago" ||
					diff < 86400 && Math.floor( diff / 3600 ) + " hours ago") ||
				day_diff == 1 && "Yesterday" ||
				day_diff < 7 && day_diff + " days ago" ||
				day_diff < 31 && Math.ceil( day_diff / 7 ) + " weeks ago");
		});
	}

};

window.addEvent('domready', Tap.Public.init.bind(Tap.Public));