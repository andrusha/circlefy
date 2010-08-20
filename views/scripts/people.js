/*
script: people.js
	Controls the people interface.
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
		'suggest.group': 'template-suggest-group',
		'list.people': 'template-list-people'
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

var _infobox = _tap.register({

	init: function(){
		var self = this;
		_body.addEvents({
			'click:relay(button.track)': this.track.toHandler(this),
			'click:relay(button.untrack)': this.track.toHandler(this)
		});
	},

	track: function(el, e){
		var self = this,
			id = el.getParent('li').getData('id'),
			type = el.hasClass('track');
		new Request({
			url: '/AJAX/track.php',
			data: {
				fid: id,
				state: (type) ? 1 : 0
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				if (response.success) {
					el.set({
						'text': (type) ? 'untrack' : 'track',
						'class': (type) ? 'untrack' : 'track'
					});
				}
			}
		}).send();
	}

});

var _filter = _tap.register({

	init: function(){
		var main = $('people-main');
		this.box = $('filter');
		this.clearer = this.box.getElement('a.clear');
		this.type = main.getElement('h2.header-title span.title');
		this.title = main.getElement('span.stream-name');
		this.title.store('original', this.title.get('text'));
		this.feed = main.getElement('ul.bodylist');
		this.feed.store('original', this.feed.getElements('li.list-item'));
		this.filter = $('filterkey');
		this.filter.addEvents({
			'keydown': this.checkKeys.bind(this)
		});
		this.clearer.addEvent('click', this.clearSearch.bind(this));
	},

	checkKeys: function(e){
		var keyword = $(e.target).get('value');
		if (e.key == 'enter') this.prepare(keyword);
	},

	prepare: function(keyword){
		if (!!keyword){
			this.active = true;
			this.clearer.addClass('active');
			this.type.set('text', 'search');
			this.title.set('text', 'Search Results');
			this.search(keyword);
		} else {
			this.active = false;
			this.clearer.removeClass('active');
			this.type.set('text', 'people');
			this.title.set('text', this.title.retrieve('original'));
			this.feed.getElements('li').dispose();
			this.feed.retrieve('original').inject(this.feed);
		}
		return this;
	},
	
	search: function(keyword){
		var self = this;
		new Request({
			url: '/AJAX/search_people.php',
			method: 'POST',
			data: {
				uname: keyword
			},
			onSuccess: function(){
				var resp = JSON.decode(this.response.text);
				self.feed.getElements('li').dispose();
				var items = [];
				if (resp.success){
					items = Elements.from(_template.parse('list.people', resp.results));
				} else {
					items = $('no-results').clone();
					self.feed.addClass('empty');
				}
				items.inject(self.feed);
			}
		}).send();
	},

	clearSearch: function(e){
		e.preventDefault();
		if (!this.active) return this;
		this.filter.set('value', '');
		this.prepare();
		return this;
	}

});

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

// UNCOMMENT FOR PROD
// })();
