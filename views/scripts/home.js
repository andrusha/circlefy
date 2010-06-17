
/*
script: home.js
	Controls the main interface.
*/

// UNCOMMENT FOR PROD
// (function(){


/*
local: _template
	The templater object.
*/
var a='';
var _template = {

	templater: new Template(),
	prepared: {},
	
	/*
	prop: map
		a mapping of the type and the id in #templates
	*/
	map: {
		'taps': 'template-taps',
		'responses': 'template-responses',
		'list.convo': 'template-list-convo',
		'suggest.group': 'template-suggest-group'
	},
	
	/*
	method: parse()
		generates the html from the template using the
		data passed
		
		args:
		1. type (string) type of template corresponding to the map key
		2. data (object) data to use in parsing the template
		
		returns:
		- (string) html content of the template evaled with the data
	*/
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

/*
local: _dater
	Controls fancy date updating
*/

var _dater = _tap.register({

	init: function(){
		var self = this;
		this.changeDates();
		this.changeDates.periodical(60000, this);
		this.subscribe({
			'dates.update; stream.updated; responses.updated': this.changeDates.bind(this)
		});
	},

	/*
	method: changeDates
		Goes to each element with the data-attrib 'timestamp'
		and updates their dates.
	*/
	changeDates: function(){
		var now = new Date().getTime(),
			items = _body.getElements("[data-timestamp]");
		items.each(function(el){
			var timestamp = el.getData('timestamp') * 1,
				orig = new Date(timestamp * 1000),
				diff = ((now - orig) / 1000),
				day_diff = Math.floor(diff / 86400);
			if (isNaN(timestamp)) return el.set('text', orig.format('jS M Y'));
			if ($type(diff) == false || day_diff < 0 || day_diff >= 31) return el.set('text', orig.format('jS M Y'));
			el.set('text', day_diff == 0 && (
					diff < 120 && "Just Now" ||
					diff < 3600 && Math.floor( diff / 60 ) + "min ago" ||
					diff < 7200 && "An hour ago" ||
					diff < 86400 && Math.floor( diff / 3600 ) + " hours ago") ||
				day_diff == 1 && "Yesterday" ||
				day_diff < 7 && day_diff + " days ago" ||
				day_diff < 31 && Math.ceil( day_diff / 7 ) + " weeks ago");
		});
	}

});


/*
mixin: list
	A mixin for sidelist operations
*/
_tap.mixin({

	name: 'lists',

	setListVars: function(){
		var list = this.list = $('lists');
		this.header = $('tab-name');
		this.action = $('list-action');
		this.panels = list.getElements('ul');
		this.tabs = list.getElements('a.tab');
		this.items = list.getElements('li.panel-item');
	}

});

/*
local: _list
	Controls the side navigation list
*/
var _list = _tap.register({

	mixins: 'lists',

	init: function(){
		var self = this;
		this.setListVars();
		this.list.addEvents({
			'click:relay(span.action)': this.actionClick.toHandler(this),
			'click:relay(a.tab)': this.changeList.toHandler(this),
			'click:relay(li.panel-item)': this.itemClick.toHandler(this)
		});
		this.subscribe({
			'convos.updated': this.moveItem.bind(this),
			'convos.removed': this.removeItem.bind(this),
			'convos.new': function(cid, uid, data){
				self.addItem('convos', $extend(data, {cid: cid}));
			},
			'search.selected': function(id){
				self.itemClick($(id), {});
			}
		});
	},
	

	/*
	handler: changeList()
		event handler for when one of the tabs are clicked
	*/
	changeList: function(el, e){
		var name = el.getData('name'),
			all = $$(this.tabs, this.panels),
			panel = $('panel-' + name),
			action = panel.getData('action'),
			href = panel.getData('action-href');
		e.preventDefault();
		this.header.set('text', el.get('title'));
		all.removeClass('selected');
		el.addClass('selected');
		panel.addClass('selected');
		el.removeClass('notify').set('text', '');
		this.action.set((action) ? {'text': action, 'href': href} : {'text': '', 'href': ''});
		this.publish('list.change', name);
	},

	/*
	handler: itemClick()
		event handler for when one of the items are clicked
	*/
	itemClick: function(el, e){
		var type = el.getParent('ul').getData('name'),
			id = el.getData('id'),
			data = {};
		this.items.removeClass('selected');
		el.addClass('selected');
		switch (type){
			case 'groups':
				data = {
					name: el.getData('name'),
					online_count: el.getData('online_count'),
					total_count: el.getData('total_count'),
					symbol: el.getData('symbol'),
					topic: el.getData('topic'),
					admin: !!el.getData('admin')
				};
				break;
			case 'convos':
				data = {
					user: el.getData('user')
				};
				break;
		}
		this.publish('list.item', [type, id, data]);
	},

	/*
	method: addItem()
		adds an item to the list
		
		args:
		1. type (string) the type of list (eg, convos, groups, etc).
		2. data (object) data to use for the templater
	*/
	addItem: function(type, data){
		switch (type){
			case 'convos':
				Elements.from(_template.parse('list.convo', [data])).inject($('panel-convos'));
				break;
		}
		this.publish('list.item.added', [type, data]);
	},

	/*
	method: moveItem()
		moves an item on the list to a different position
		
		args:
		1. id (string, obj) list item to move
		2. where (string) position to move
	*/
	moveItem: function(id, where){
		var el = $(id);
		where = where || 'top';
	 	return (el) ? !!el.inject(el.getParent('ul'), where) : false;
	},

	/*
	method: removeItem()
		removes an item from the list
		
		args:
		1. id (string) the value of the id data-attrib of the object
		2. elID (string, obj) the element to remove
	*/
	removeItem: function(id, elId){
		var el = $(elId);
		if (el) el.destroy();
		this.publish('list.item.removed', id);
	},

	/*
	method: getItems()
		get items from the list via a selector
		
		args:
		1. selector (string) the selector to use to filter the items
		2. limit (number) the number of items to return
		
		returns:
		- (element, collection) the elements found
	*/
	getItems: function(selector, limit){
		var items = this.items.filter(selector);
		if (items.length === 0) return (limit == 1) ? null : items;
		return (limit == 1) ? items[0] : $$(items.slice(0, limit - 1));
	},

	/*
	handler: actionClick()
		fires when an .action element in the list is clicked
	*/
	actionClick: function(el, e){
		var action = el.getData('action'),
			parent = el.getParent('li');
		if (!action) return;
		this.publish('list.action.' + action, parent.getData('id'));
		return this;
	}

});

/*
mixin: streaming
	mixin for streaming/feedlist operations
*/
_tap.mixin({

	name: 'streaming',

	setStreamVars: function(){
		var main = this.main = $('tapstream');
		this.stream = $('taps');
		this.header = main.getElement('h2.header-title');
		this.title = main.getElement('span.stream-name');
		this.feedType = this.header.getElement('span.title');
		this.topic = main.getElement('div.description');
		this.streamType = 'all';
	},

	/*
	method: showLoader()
		shows the loading indicator on top of the feedlist
	*/
	showLoader: function(){
		this.header.addClass('loading');
		return this;
	},

	/*
	method: hideLoader()
		hides the loading indicator on top of the feedlist
	*/
	hideLoader: function(){
		this.header.removeClass('loading');
		return this;
	},

	/*
	method: setTitle()
		sets the feedlist's title.
		
		args:
		- options (object) see below
		
		options:
		- type (string) the text that'll be used for the 'type' (eg, "group", "convo")
		- title (string) the main title for the feed
		- url (string, opt) the url for the optional link
		- desc (string, opt) the description/topic text
		- admin (string, opt) the url to the management page
	*/
	setTitle: function(options){
		var self = this;
		if(options.type == 'convo')
			options.favicon = '/group_pics/default.ico';

		var title = options.title,
			url = options.url,
			favicon = options.favicon,
			type = options.type,
			desc = options.desc,
			admin = options.admin,
			online_count = options.online_count,
			total_count = options.total_count;
		this.feedType.set('text', type);
		title = (!url) 
					? title 
					: '<img class="favicon-stream-title" src="{fav}" /> {t} <span class="visitor_count" title="viewers online/total"><span class="viewers_online">{oc}</span> / {tc}</span> <a href="{u}">view profile</a>'
							.substitute({fav: favicon, t: title, u: url, oc: online_count, tc: total_count});
		if (!!admin) title = ['<span title="Moderator" class="moderator-title">&#10070;</span> ', title, '<a href="{u}">manage group</a>'.substitute({u: admin})].join('');
		this.title.set('html', title);

		if (desc){
			this.topic.set('html',desc.linkify() );
			this.main.addClass('description');
		} else {
			this.main.removeClass('description');
		}
		return this;
	},

	linkify: function(){
                var regexp = new RegExp("\
                        (?:(?:ht|f)tp(?:s?)\\:\\/\\/|~\\/|\\/){1}\
                        (?:\\w+:\\w+@)?\
                        (?:(?:[-\\w]+\\.)+\
                        (?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|[a-z]{2}))\
                        (?::[\\d]{1,5})?(?:(?:(?:\\/(?:[-\\w~!$+|.,=]|%[a-f\\d]{2})+)+|\\/)+|\\?|#)?\
                        (?:(?:\\?(?:[-\\w~!$+|.,*:]|%[a-f\\d{2}])+=(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)\
                        (?:&(?:[-\\w~!$+|.,*:]|%[a-f\\d{2}])+=(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)*)*\
                        (?:#(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)?".replace(/\(\?x\)|\s+#.*$|\s+/gim, ''), 'g');
                return this.replace(regexp, function(match){
                        return ['<a href="', match,'" target="_blank">', match,'</a>'].join('');
                });
        },


	/*
	method: parseFeed()
		parses the taps data in order to create the html for the feedlist
		
		args:
		1. resp (obj) the response object from the xhr.
		2. keep (bool) if true, taps already in the feed are not removed.
	*/
	parseFeed: function(resp, keep){
		var stream = this.stream;
		if (!keep) stream.empty();
		if (resp.results && resp.data){
			var items = Elements.from(_template.parse('taps', resp.data));
			items.each(function(item){
				var id = item.get('id'),
					el = $(id);
				if (el) el.destroy();
			});
			items = $$(items.reverse());

			if (items.length > 8 && !keep) ($('load-more').clone()).inject('taps','bottom');
			if(keep) publish_type = 'stream.more'; else publish_type = 'stream.new';
			this.publish(publish_type, [items]);
			stream.removeClass('empty');
		} else {
			this.publish('stream.empty', $('no-taps-yet').clone());
			stream.addClass('empty');
		}
	},


	/*
	method: addTaps()
		adds taps to the feedlist
		
		args:
		1. items (elements) the tap items to be added to the feedlist
	*/
	addTaps: function(items){
		items.setStyles({opacity:0});
		items.inject(this.stream, 'top');
		items.fade(1);
		this.publish('stream.updated', this.streamType);
		return this;
	},
	addTapsMore: function(items){
		items.inject(this.stream, 'bottom');
		this.publish('stream.updated', this.streamType);
		return this;
	}

});


/*
module: _stream
	Controls the main tapstream/feedlist
*/
var _stream = _tap.register({

	mixins: 'streaming',
	myTips: {},

	init: function(){
		this.enableLoadMore();
		//this.setLoadMore(id, feed, keyword);
		this.setLoadMore('all', {}, null);
		this.setStreamVars();
		this.subscribe({
			'list.item': this.setStream.bind(this),
			'stream.new; stream.empty; tapbox.sent; stream.more': this.addTaps.bind(this),
			'feed.changed': (function(type){ this.streamType = type; }).bind(this),
			'taps.pushed': this.parsePushed.bind(this),
			'taps.notify.click': this.getPushed.bind(this),
			'filter.search': this.changeFeed.bind(this)
		});
		this.addPeopleTips();
	},

	addPeopleTips: function() { 
		this.myTips = new Tips('.people-contact-list img',{fixed:true });

		this.myTips.addEvent('show', function(tip, el){
		    tip.fade('in');
		});
	},

	addTips: function() { 
		this.myTips = new Tips('.aggr-favicons',{fixed:true });

		this.myTips.addEvent('show', function(tip, el){
		    tip.fade('in');
		});
	},

	addMore: function(){
		this.myTips.attach('.aggr-favicons');	
	},

	/*
	method: setStream()
		Sets the current stream type
		
		args:
		1. type (string) the type of stream (eg, "groups", "convos")
		2. id (string) the id of the stream corresponding to a group
		3. info (obj) additional group/feedtype data
	*/
	setStream: function(type, id, info){
		if (type == 'groups') return this.changeFeed(id, info);
		return this;
	},


	/*
	method: changeFeed()
		changes the main tapstream
		
		args:
		1. id (string) the id of the group
		2. feed (object) additional data about the group
		3. keyword (string, opt) if present, performs a search rather than just loading taps
	*/
	changeFeed: function(id, feed, keyword, more){
		//console.log(id, feed, keyword, more);

		var self = this,
			data = {type: null};
		switch (id){
			case 'all': data.type = 11; break;
			case 'public': data.type = 100; break;
			default: data.type = 1; data.id = id;
		}

		if(id == 'public' || id == 'all'){
			$('taptext').disabled = true;
			$('taptext').style.background = 'gray';
		}else{
			$('taptext').disabled = false;
			$('taptext').style.background = 'white';
		}

		
		if(!more) {
			more=false;
			data.more = 0;
			self.loadmore_count = 10;
		} else { 
			data.more = self.loadmore_count;
		}	
	
		if (keyword) data.search = keyword;
		new Request({
			url: '/AJAX/filter_creator.php',
			data: data,
			onRequest: this.showLoader.bind(this),
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				self.setTitle({
					title: keyword ? ['"', keyword, '" in ', feed.name].join('') : feed.name,
					favicon: data.type == 1 ? $$('#gid_'+id+' img.favicon-img')[0].src : '',
					url: feed.symbol ? '/group/' + feed.symbol : null,
					type: keyword ? 'search' : 'feed',
					desc: feed.topic,
					admin: feed.admin ? '/group_edit?group=' + feed.symbol : null,
					online_count: feed.online_count,
					total_count: feed.total_count
				});
			
				if (response) self.parseFeed(response,more);
				self.hideLoader();
				self.publish('feed.changed', id.test(/^(public|all)$/) ? id : 'group_' + id);
				self.setLoadMore(id, feed, keyword);
				self.enableLoadMore();
			}
		}).send();
	},

	enableLoadMore: function(){
		var self = this;
		$$('.loadmore')[0].addEvent('click',function(){
				self.changeFeed(self.loadmore_gid,self.loadmore_feed,self.loadmore_keyword,self.loadmore_count);
				self.loadmore_count = self.loadmore_count + 10;
		})
	},

	setLoadMore: function( gid, feed, keyword) {
		var self = this;
		//console.log(gid, feed, keyword);
		self.loadmore_gid = gid;
		self.loadmore_feed = feed;
		self.loadmore_keyword = keyword;
	},

	/*
	method: parsePushed()
		parses pushed data from the server
		
		args:
		1. type (string) the type of push data recieved
		2. items (array) the items from the server
		3. stream (bool) if true, the pushed data would automatically be turned to taps and put on the tapstream
	*/
	parsePushed: function(type, items, stream){
		var self = this;
		if ((type == 'groups' && this.streamType == 'all') || this.streamType == type){
			items = items.filter(function(id){
				var item = self.stream.getElement('li[data-id="'+id+'"]');
				return !item;
			});
			if (items.length > 0) {
				if (!stream) this.publish('stream.reload', [items]);
				else this.getPushed(items);
			}
		}
	},

	/*
	method: getPushed()
		retrieves taps data from the server
		
		args:
		1. items (array) an array of tap ids to be fetched from the server
	*/
	getPushed: function(items){
		var self = this,
			data = {id_list: items.join(',')};
		new Request({
			url: '/AJAX/loader.php',
			data: data,
			onRequest: this.showLoader.bind(this),
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				if (response) self.parseFeed(response, true);
				self.hideLoader();
				self.publish('stream.loaded', self.streamType);
			}
		}).send();
	}

});

/*
module: _filter
	Controls the filter/search bar for the tapstream
*/
var _filter = _tap.register({

	init: function(){
		this.group = 'all';
		this.info = {name: 'All Your Groups'};
		this.box = $('filter');
		this.title = this.box.getElement('span.title');
		this.clearer = this.box.getElement('a.clear');
		this.filter = $('filterkey');
		this.filter.addEvents({
			'keydown': this.checkKeys.bind(this)
		});
		this.clearer.addEvent('click', this.clearSearch.bind(this));
		this.subscribe({
			'list.item': this.change.bind(this)
		});
	},

	/*
	method: change()
		changes the filter box depending on the list item clicked
		
		args:
		1. type (string) the type of the item clicked
		2. id (string) the id of the item click
		3. info (obj) additional info for the item
	*/
	change: function(type, id, info){
		var box = this.box;
		if (type == 'groups'){
			this.group = id;
			this.info = info;
			box.slide('in');
			this.setTitle(info.symbol || info.name);
		} else {
			this.group = null;
			this.info = null;
			box.slide('out');
		}
		this.filter.set('value', '');
	},

	/*
	method: setTitle()
		sets the filterbox's title
		
		args:
		1. title (string) the title to be displayed
	*/
	setTitle: function(title){
		this.title.set('text', title.toLowerCase());
		return this;
	},
	
	/*
	method: search()
		main control logic for searching
		
		args:
		1. keyword (string, opt) the keyword to use for searching; if null, the search is cleared
	*/
	search: function(keyword){
		if (!!keyword){
			this.active = true;
			this.clearer.addClass('active');
		} else {
			this.active = false;
			this.clearer.removeClass('active');
		}
		this.publish('filter.search', [this.group, this.info, keyword || null]);
		return this;
	},

	/*
	handler: checkKeys()
		checks whether the enter key is pressed and performs a search
	*/
	checkKeys: function(e){
		var keyword = $(e.target).get('value');
		if (this.group && e.key == 'enter') this.search(keyword);
	},

	/*
	method: clearSearch()
		resets the search data
	*/
	clearSearch: function(e){
		e.preventDefault();
		if (!this.active) return this;
		this.filter.set('value', '');
		this.search();
		return this;
	}

});

/*
module: _convos
	Controls active conversations for the stream
*/
var _convos = _tap.register({

	mixins: "lists; streaming",

	init: function(){
		this.setStreamVars();
		this.subscribe({
			'list.item': this.setStream.bind(this),
			'responses.sent': this.addConvo.bind(this),
			'list.action.remove': this.removeConvo.bind(this),
			'feed.changed': (function(type){ this.streamType = type; }).bind(this)
		});
	},

	setStream: function(type, id, info){
		if (type == 'convos') return this.changeFeed(id, info);
		return this;
	},

	/*
	method: changeFeed()
		loads a specific convo
		
		args:
		1. id (string) the id of the active conversation
		2. feed (object) additional data about the active conversation
	*/
	changeFeed: function(id, feed){
		var self = this,
			data = {id_list: id};
		new Request({
			url: '/AJAX/loader.php',
			data: data,
			onRequest: this.showLoader.bind(this),
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				self.setTitle({
					title: 'with '+ feed.user,
					url: '/user/' + feed.user,
					type: 'convo'
				});
				if (response) self.parseFeed(response);
				self.hideLoader();
				self.openConvo();
				self.publish('feed.changed', 'convos');
			}
		}).send();
	},

	/*
	method: openConvo()
		automatically opens the active convo's responses area
	*/
	openConvo: function(){
		var el = this.stream.getElement('a.tap-resp-count');
		if (el) this.publish('convos.loaded', el);
	},

	/*
	method: addConvo()
		tells the server that the user has responded to a convo
		
		args:
		1. cid (string) the active convo id
		2. uid (string) the user id of the original tapper
		3. data (obj) additional data about the active convo
	*/
	addConvo: function(cid, uid, data){
		var self = this,
			exists = this.publish('convos.updated', 'cid_' + cid).shift();
		if (exists) return;
		new Request({
			url: '/AJAX/add_active.php',
			data: {cid: cid},
			onSuccess: function(){
				self.publish('convos.new', [cid, uid, data]);
			}
		}).send();
	},

	/*
	method: removeConvo()
		tells the server that a user wants to be removed from an active convo
		
		args:
		1. id (string) the id of the active convo
	*/
	removeConvo: function(cid){
		var self = this;
		new Request({
			url: '/AJAX/remove_active.php',
			data: {cid: cid},
			onSuccess: function(){
				self.publish('convos.removed', [cid, 'cid_' + cid]);
			}
		}).send();
	}

});

/*
module: _responses
	Controls the responses in the main tapstream
*/
var _responses = _tap.register({

	init: function(){
		_body.addEvents({
			'click:relay(a.tap-resp-count)': this.setupResponse.toHandler(this)
		});
		this.subscribe({
			'responses.new': this.addResponses.bind(this),
			'convos.loaded': this.setupResponse.bind(this)
		});
	},

	/*
	handler: setupResponse()
		adds event handlers to the taps' responses area
		NOTE: toggleNotifier pauses the stream
	*/
	rCounter: 0,
	setupResponse: function(el, e){

	
		if(this.rCounter == 0 && _live.taps.stream ){
			_live.taps.toggleNotifier($('streamer'));
		}


		var parent = el.getParent('li'),
			responses = parent.getElement('div.responses'),
			box = responses.getElement('ul.chat'),
			chat = responses.getElement('input.chatbox'),
			counter = responses.getElement('.counter'),
			overlay = responses.getElement('div.overlay');
		if (e) e.preventDefault();

		if(!responses.hasClass('open')){
			this.rCounter++
		} else { 
			this.rCounter--;
		}

		responses.toggleClass('open');
		if (!box.retrieve('loaded')) this.loadResponse(parent.getData('id'), box);
		if (!chat.retrieve('extended')) this.extendResponse(chat, counter, overlay);
		box.scrollTo(0, box.getScrollSize().y);
		return this;
	},

	/*
	method: loadResponse()
		loads previous responses for the tap
		
		args:
		1. id (string) the id of the tap
		2. box (element) the tap's response area element
	*/
	loadResponse: function(id, box){
		var self = this;
		new Request({
			url: '/AJAX/load_responses.php',
			data: {cid: id},
			onSuccess: function(){
				var data = JSON.decode(this.response.text);
				if (!data.responses) return;
				self.addResponses(box.empty(), data.responses);
				self.publish('responses.loaded', id);
				box.store('loaded', true);
			}
		}).send();
	},

	/*
	method: addResponses()
		adds new responses to a tap's response area
		
		args:
		1. box (element) the tap's response area element
		2. data (object) data to use in parsing the template
	*/
	addResponses: function(box, data){
		var items = Elements.from(_template.parse('responses', data));
		items.setStyles({opacity:0});
		items.fade(1);
		items.inject(box);
		box.scrollTo(0, box.getScrollSize().y);
		this.publish('responses.updated');
		this.updateStatus(box);
		return this;
	},

	/*
	method: updateStatus()
		updates the tap's last response data and the response count
		
		args:
		1. box (element) the tap's response area element
	*/
	updateStatus: function(box){
		var items = box.getElements('li'),
			last = items.getLast(),
			parent = box.getParent('li'),
			lastresp = parent.getElement('span.last-resp'),
			username = lastresp.getElement('strong'),
			chattext = lastresp.getElement('span'),
			count = parent.getElement('a.tap-resp-count span.count');
			a= last;
		username.set('text', last.getElement('a').get('text'));
		chattext.set('text', last.getChildren('span').get('text'));
		count.set('text', items.length);
	},

	/*
	method: extendResponse()
		adds event handlers to the tap's response area textbox
		
		args:
		1. chatbox (element) the tap's response area element
		2. counter (element) the tap's counter element
		3. overlay (element) the tap's overlay element
	*/
	extendResponse: function(chatbox, counter, overlay){
		var self = this,
			limit = 240,
			allowed = {'enter': 1,'up': 1,'down': 1,'left': 1, 'right': 1,'backspace': 1,'delete': 1};
		chatbox.addEvents({
			'keydown': function(e){
				if (this.get('value').length >= limit && !allowed[e.key]) return e.stop();
			},
			'keypress': function(){
				self.publish('responses.typing', chatbox);
			},
			'keyup': function(e){
				var length = this.get('value').length,
					count = limit - length;
				if (e.key == 'enter' && !this.isEmpty()) return self.sendResponse(chatbox, counter);
				counter.set('text', count);
			}
		});
		chatbox.store('overlay', new TextOverlay(chatbox, overlay));
		chatbox.store('extended', true);
	},

	/*
	method: sendResponse()
		sends the response data for a tap to the server
		
		args:
		1. chatbox (element) the tap's response area element
		2. counter (element) the tap's counter element
	*/
	sendResponse: function(chatbox, counter){
		var self = this,
			parent = chatbox.getParent('li'),
			cid = parent.getData('id'),
			uid = parent.getData('uid'),
			pic = $$('#you img.avatar')[0].src.split('/')[4].split('_')[1],
			data = {
				user: parent.getData('user'),
				message: parent.getElement('p.tap-body').get('html')
			};
		new Request({
			url: '/AJAX/respond.php',
			data: {
				cid: cid,
				small_pic: pic,
				response: chatbox.get('value'),
				init_tapper: uid || 0,
				first: !chatbox.retrieve('first') ? 1 : 0
			},
			onRequest: function(){
				self.clearResponse(chatbox, counter);
			},
			onSuccess: function(){
				chatbox.store('first', true);
				self.publish('responses.sent', [cid, uid, data]);
			}
		}).send();
	},

	/*
	method: clearResponse()
		resets the tap's response area element
		
		args:
		1. chatbox (element) the tap's response area element
		2. counter (element) the tap's counter element
	*/
	clearResponse: function(chatbox, counter){
		chatbox.set('value', '');
		counter.set('text', '240');
		chatbox.focus();
		return this;
	}

});

/*
module: _tapbox
	controls the main tap sender
*/
var _tapbox = _tap.register({

	sendTo: 'public:public:0:0',
	gid: '0',

	init: function(){
		this.tapbox = $('tapbox');
		this.overlayMsg = $('tapto');
		this.msg = $('taptext');
		
		this.counter = this.tapbox.getElement('span.counter');
		this.overlay = new TextOverlay('taptext', 'tapto');
		this.setupTapBox();
		this.tapbox.addEvent('submit', this.send.toHandler(this));
		this.tapbox.addEvent('click', function(el){
			var tt = $('tapto').innerHTML;
			var ngs = $('no-group-selected');
			if(tt == 'choose a group to tap'){
				ngs.style.display = 'block';
				ngs.fade('hide');
                                ngs.fade(1).fade.delay(4000,ngs,0);
				ngs.setStyles.delay(4700,ngs,{'display':'none'});
			}
		});

		this.subscribe({
		'list.item': this.handleTapBox.bind(this)
		});
	},

	/*
	method: setupTapBox()
		adds event handlers to the tapbox
	*/
	setupTapBox: function(){
		var msg = this.msg,
			counter = this.counter,
			limit = 240,
			allowed = {'enter': 1,'up': 1,'down': 1,'left': 1, 'right': 1,'backspace': 1,'delete': 1};

		msg.addEvents({
			'keydown': function(e){
				if (this.get('value').length >= limit && !allowed[e.key]) return e.stop();
			},
			'keyup': function(){
				var count = limit - this.get('value').length;
				counter.set('text', count);
			}
		});
	},

	/*
	method: clear()
		resets the tapbox
	*/
	clear: function(){
		this.msg.set('value', '').blur();
		this.counter.set('text', '240');
		this.overlay.show();
		return this;
	},

	/*
	method: handleTapBox()
		changes the tap box when a new group from the list is clicked
		
		args:
		1. type (string) the type of item clicked
		2. id (string) the id of the item click
		3. data (obj) additional data for the item
	*/
	handleTapBox: function(type, id, data){
		if (type !== 'groups') return;
		this.changeOverlay(id, data.name);
		this.changeSendTo(data.name, data.symbol, id);
	},

	/*
	method: changeOverlay()
		changes the overlay text for the tap box
		
		args:
		1. id (string) the id of the group
		2. name (string) the name of the group
	*/
	changeOverlay: function(id, name){
		var msg = "";
		switch (id){
			case 'all':
			case 'public': msg = 'chose a group to tap'; break;
			default: msg = 'tap ' + name + '...';
		}
		this.overlayMsg.set('text', msg.toLowerCase());
		return this;
	},

	/*
	method: changeSendTo()
		changes the destination for the tap box
		
		args:
		1. name (string) the name of the group
		2. symbol (string) the symbol of the group
		3. id (string) the id of the group
	*/
	changeSendTo: function(name, symbol, id){
		this.sendTo = [name, symbol, 0, id].join(':');
		this.gid = id;
		return this;
	},

	/*
	handler: send()
		sends the tap to the server when the send button is clicked
	*/
	send: function(el, e){
		e.stop();
		if (this.msg.isEmpty()) return this.msg.focus();
		new Request({
			url: '/AJAX/new_message_handler.php',
			data: {
				msg: this.msg.get('value'),
				to_box: JSON.encode([this.sendTo])
			},
			onSuccess: this.parseSent.bind(this)
		}).send();
	},

	/*
	method: parseSent()
		injects the recently sent tap to the top of the tapstream
	*/
	parseSent: function(response){
		var resp = JSON.decode(response);
		if (resp.new_msg){
			this.clear();
			var item = Elements.from(_template.parse('taps', resp.new_msg));
			this.publish('tapbox.sent', [item, this.gid]);
		}
	}

});

/*
module: _search
	controls the global searchbox
*/
var _search = _tap.register({

	init: function(){
		var self = this,
			search = this.search = $('search');
		this.suggest = $('searchsuggest');
		this.list = this.suggest.getElement('ul');
		this.selected = null;
		this.on = false;
		this.overlay = new TextOverlay(search, 'searchover');
		search.addEvents({
			'focus': this.start.toHandler(this),
			'blur': this.end.toHandler(this),
			'keyup': this.checkKeys.bind(this)
		});
		this.list.addEvents({
			'click:relay(li)': this.itemClicked.toHandler(this),
			'mouseover:relay(li)': function(e){
				var el = this;
				self.list.getElements('li:not(.notice)').each(function(item, index){
					if (item == el){
						item.addClass('selected');
						self.selected = index;
					} else {
						item.removeClass('selected');
					}
				});
			}
		});
	},

	/*
	handler: start()
		fired when the search input is focused
	*/
	start: function(){
		var els = this.suggest.getElements('li');
		this.on = true;
		this.suggest.addClass('on');
		if (els.length == 0) new Element('li', {'text': 'Start or Join a conversation', 'class': 'notice'}).inject(this.list);
	},

	/*
	handler: end()
		fired when the search input is blurred
	*/
	end: function(el){
		var self = this,
			list = this.list;
		this.on = false;
		this.selected = null;
		(function(){ self.suggest.removeClass('on'); }).delay(300);
		if (el.isEmpty()) list.empty();
		else list.getElements('li').removeClass('selected');
	},

	/*
	handler: checkKeys()
		checks whether the search keys are valid or when enter is pressed
	*/
	checkKeys: function(e){
		var updown = ({'down': 1, 'up': -1})[e.key];
		if (updown) return this.navigate(updown);
		if (e.key == 'enter') return this.doAction();
		if (({'left': 37,'right': 39,'esc': 27,'tab': 9})[e.key]) return;
		this.goSearch(e);
	},

	/*
	method: navigate()
		controls the up/down keys for the search results
		
		args:
		1. updown (number) the number of movements to do (up: positive, down: negative)
	*/
	navigate: function(updown){
		var current = (this.selected !== null) ? this.selected : -1,
			items = this.list.getElements('li:not(.notice)');
		var selected = this.selected = current + updown;
		if (items.length != 0 && !(selected < 0) && selected < items.length){
			items.removeClass('selected');
			items[selected].addClass('selected');
		} else {
			this.selected = (selected < 0) ? 0 : items.length - 1;
		}
	},

	/*
	method: doAction()
		performs an action for an item
	*/
	doAction: function(){
		if (this.selected == null) return;
		this.itemClicked(this.list.getElements('li')[this.selected], {});
		this.search.set('value', '').blur();
	},

	/*
	handler: itemClick()
		fired when a suggestion item is clicked
	*/
	itemClicked: function(el, e){
		var type = el.getData('type');
		if (type == 'group'){
			if (el.getData('joined') == "1") this.publish('search.selected', 'gid_' + el.getData('id'));
			else window.location = ['/group/', el.getData('symbol')].join('');
		}
	},

	/*
	handler: goSearch()
		performs the search
	*/
	goSearch: function(e){
		var el = $(e.target),
			keyword = el.get('value');
		if (!keyword.isEmpty() && keyword.length){
			if (!this.request) this.request = new Request({
				url: '/AJAX/search_assoc.php',
				link: 'cancel',
				onSuccess: this.parseResponse.bind(this)
			});
			this.request.send({data: {search: keyword}});
		} else {
			this.list.empty();
			new Element('li', {'text': 'searching..', 'class': 'notice'}).inject(this.list);
		}
	},

	/*
	method: parseResponse()
		parses the response from the server and inject it in the suggestion box
	*/
	parseResponse: function(txt){
		var resp = JSON.decode(txt),
			list = this.list.empty();
		this.selected = null;
		if (!resp){
			this.list.empty();
			new Element('li', {'text': 'hmm, nothing found..', 'class': 'notice'}).inject(this.list);
			return this;
		}
		var data = resp.map(function(item){
			var els = Elements.from(item[3]),
				info = item[1].split(':');
			var a = info[4],b = info[5];
                        info.shift();
                        return {
                                id: info.pop(),
                                symbol: info.shift(),
                                name: item[0],
                                online: a,
                                total: b,
                                img: els.filter('img').pop().get('src'),
                                desc: els.filter('span').pop().get('text'),
                                joined: item[4]
                        };
		});
		$$(Elements.from(_template.parse('suggest.group', data)).slice(0, 5)).inject(list);
		this.suggest.addClass('on');
	}

});

/*
module: _live
	namespace for live push events
*/
var _live = {};

/*
module: _live.stream
	controls the automatic tap streaming
*/
_live.stream = _tap.register({

	init: function(){
		var self = this;
		this.stream = _vars.stream;
		this.convos = _vars.convos;
		this.groups = _vars.groups;
		this.people = _vars.people;
		this.tapstream = $('taps');
		this.convolist = $('panel-convos');
		this.subscribe({
			'push.connected': this.update.bind(this),
			'stream.updated': this.refreshStream.bind(this),
			'list.item.added': function(type){
				if (type == 'convos') self.refreshConvos();
			},
			'list.item.removed': this.refreshConvos.bind(this)
		});
	},

	/*
	method: refreshStream()
		parses the page and gets user, group and tap ids for the push server
	*/
	refreshStream: function(){
		var stream = [], people = [];
		this.tapstream.getElements('li[data-id]').each(function(tap){
			stream.push(tap.getData('id'));
			people.push(tap.getData('uid'));
		});
		this.stream = stream;
		this.people = people;
		this.update();
	},

	/*
	method: refreshConvos()
		parses the page and gets the convo ids for the push server
		
		args:
		1. type (string) the type of item clicked
		2. id (string) the id of the item click
		3. data (obj) additional data for the item
	*/
	refreshConvos: function(){
		this.convos = this.convolist.getElements('li[data-id]').map(function(item){
			return item.getData('id');
		});
		this.update();
	},

	/*
	method: update()
		sends changes to the push server
	*/
	update: function(){
		var cids = [].combine($splat(this.stream)).combine($splat(this.convos)),
			uids = [].combine($splat(this.people || [])),
			gids = [].combine($splat(this.groups || []));
		this.publish('push.send', {
			cids: cids.join(','),
			uids: uids.join(','),
			gids: gids.join(',')
		});
	}

});

/*
module: _live.viewers
	controls the view numbers for the tap stream
*/
_live.viewers = _tap.register({

	init: function(){
		this.subscribe({
			'push.data.view.add; push.data.view.minus': this.change.bind(this)
		});
	},

	change: function(taps, amount){
		var len, span, parent;
		taps = $splat(taps);
		len = taps.length;
		while (len--){
			parent = $('tid_' + taps[len]);
			if (!parent) continue;
			span = parent.getElement('span.tap-view-count');
			span.set('text', (span.get('text') * 1) + amount);
		}
		return this;
	}

});

/*
module: _live.typing
	controls the typing indicator
*/
_live.typing = _tap.register({

	init: function(){
		this.subscribe({
			'responses.typing': this.sendTyping.bind(this),
			'push.data.response.typing': this.showTyping.bind(this)
		});
	},

	sendTyping: function(chatbox){
		var id = chatbox.getParent('li').getData('id');
		if (chatbox.retrieve('typing')) return;
		(function(){ chatbox.store('typing', false); }).delay(1500);
		chatbox.store('typing', true);
		new Request({url: '/AJAX/typing.php', data: {cid: id, response: 1}}).send();
	},

	showTyping: function(tid, user){
		var timeout, indicator, parent = $('tid_' + tid);
		if (!parent) return;
		indicator = parent.getElement('span.tap-resp');
		timeout = indicator.retrieve('timeout');
		if (timeout) $clear(timeout);
		indicator.addClass('typing');
		timeout = (function(){ indicator.removeClass('typing'); }).delay(2000);
		indicator.store('timeout', timeout);
	}

});

/*
module: _live.responses
	parses the pushed responses from the server
*/
_live.responses = _tap.register({

	init: function(){
		this.subscribe({
			'push.data.response': this.setResponse.bind(this)
		});
	},

	setResponse: function(id, user, msg, pic){
		var parent = $('tid_' + id);
		if (!parent) return;
		var box = parent.getElement('ul.chat');

		if (box) this.publish('responses.new', [box, [{
			uname: user,
			pic_small: pic,
			chat_text: msg,
			chat_time: new Date().getTime().toString().substring(0, 10)
		}]]);
	}

});

/*
module: _live.users
	controls the offline/online mode for users
*/
_live.users = _tap.register({

	init: function(){
		this.subscribe({
			'push.data.user.add': this.setOnline.bind(this),
			'push.data.user.minus': this.setOffline.bind(this)
		});
	},

	setOnline: function(ids){
		var el, len = ids.length;
		while (len--){
			el = _body.getElements('[data-uid="'+ ids[len] + '"]');
			el.getElement('p.tap-from').removeClass('offline');
		}
	},

	setOffline: function(ids){
		var el, len = ids.length;
		while (len--){
			el = _body.getElements('[data-uid="'+ ids[len] + '"]');
			el.getElement('p.tap-from').addClass('offline');
		}
	}

});

/*
module: _live.list
	controls the count numbers for the sidelist
*/
_live.list = _tap.register({

	init: function(){
		var self = this;
		this.groups = $('panel-groups');
		this.subscribe({
			'taps.pushed': this.parsePushed.bind(this),
			'stream.loaded': this.clearCount.bind(this),
			'list.item': function(type, id){
				if (type == 'groups' && id != 'public'){
					return self.clearCount(id == 'all' ? id : 'group_' + id);
				}
			}
		});
	},

	parsePushed: function(type, items, stream){
		var key = (type == 'groups') ? 'gid_all' : type.replace(/group/, 'gid');
		this.setCount(key, items.length);
	},
	
	addCount: function(_, type){
		if (type == 'public') return;
		var key = (type == 'all') ? 'gid_all' : type.replace(/group/, 'gid');
		var item = $(key);
		if (!item) return;
		var counter = item.getElement('span.count');
		counter.set('text', (counter.get('text') * 1) - 1);
	},

	setCount: function(type, count){
		var item = $(type);
		if (!item || !count) return;
		var counter = item.getElement('span.count');
		counter.style.display = 'block';
		counter.set('text', count);
	},

	clearCount: function(type){
		var key = $((type == 'all') ? 'gid_all' : type.replace(/group/, 'gid'));
		if (!key) return;
		if (type == 'all'){
			this.groups.getElements('li.panel-item').each(function(item){
				item.getElement('span.count').set('text', '');
				item.getElement('span.count').setStyle('display', 'none');
				
			});
		} else {
			var current = key.getElement('span.count'),
				total = $('gid_all').getElement('span.count'),
				count = (total.get('text') * 1) - (current.get('text') * 1);
			current.setStyle('display', 'none');
			total.setStyle('display', !count || count < 0 ? 'none' : 'block');
			total.set('text', !count || count < 0 ? '' : count);
			current.set('text', '');
		}
	}

});

/*
module: _live.taps
	controls the new taps sent by the push server
*/
_live.taps = _tap.register({

	init: function(){
		var self = this;
		this.pushed = {};
		this.notifier = $('newtaps');
		this.streamer = $('streamer');
		this.stream = true;
		this.subscribe({
			'push.data.notification': this.process.bind(this),
			'feed.changed': this.hideNotifier.bind(this),
			'stream.reload': this.showNotifier.bind(this),
			'stream.updated': this.clearPushed.bind(this)
		});
		this.notifier.addEvent('click', function(e){
			e.stop();
			var items = this.retrieve('items');
			if (!items) return;
			self.publish('taps.notify.click', [items]);
			self.hideNotifier();
		});
		this.streamer.addEvent('click', this.toggleNotifier.toHandler(this));
	},

	process: function(data){
		var len, item, ids,
			pushed = this.pushed;
		data = data.map(function(item){
			return item.link({
				type: String.type,
				_: Number.type,
				cid: Number.type,
				$: Boolean.type,
				perm: Number.type
			});
		});

		len = data.reverse().length;
		while (len--){
			item = data[len];
			if (!item.type.test(/^group/)) continue;
			if (!pushed[item.type]) pushed[item.type] = [];
			ids = pushed[item.type];
			ids.include(item.cid);
		}
		for (var type in pushed) this.publish('taps.pushed', [type, pushed[type], this.stream]);
	},

	clearPushed: function(type){
		if (!type) return;
		switch (type){
			case 'all': this.pushed = {}; break;
			default: this.pushed[type] = [];
		}
	},

	showNotifier: function(items){
		var notifier = this.notifier,
			length = items.length;
		notifier.set('text', [
			length.toString(), 'new', length == 1 ? 'tap.' : 'taps.', 'Click here to load them.'
		].join(' '));
		notifier.store('items', items).addClass('notify');
	},

	hideNotifier: function(){
		var notifier = this.notifier;
		notifier.set('text', '').store('items', null).removeClass('notify');
	},

	toggleNotifier: function(el){
		if (el.hasClass('paused')){
			this.stream = true;
			el.removeClass('paused').set({
				'alt': 'pause live streaming',
				'title': 'pause live streaming',
				'text' : ''	
			});
		} else {
			_responses.rCounter = 0;	
			this.stream = false;
			el.addClass('paused').set({
				'alt': 'start live streaming',
				'title': 'start live streaming',
				'text':'paused'
			});
		}
	}

});

// UNCOMMENT FOR PROD
// })();
