/*
 script: public.js
 Controls the main interface.
 */

// UNCOMMENT FOR PROD
// (function(){

var _lists = _tap.register({
    myTips: {},

    init: function() {
        _body.addEvents({
            'click:relay(li.panel-item)': this.doAction.toHandler(this)
        });
        this.addTips();
    },

    doAction: function(el, e) {
        var link = el.getElement('a');
        if (!link) return;
        window.location = link.get('href');
    },

    addTips: function() {
        ['.aggr-favicons', '.panel-item-public-admin', '.people-contact-list img'].each(function(id) {
            _lists.myTips[id] = new Tips(id, {fixed: true});
            _lists.myTips[id].addEvent('show', function(tip, el) {
                tip.fade('in');
            });
        });
    }
});

/*
mixin: streaming
	mixin for streaming/feedlist operations
*/
_tap.mixin({

    name: 'streaming',

    setStreamVars: function() {
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
    showLoader: function() {
        this.header.addClass('loading');
        return this;
    },

	/*
	method: hideLoader()
		hides the loading indicator on top of the feedlist
	*/
    hideLoader: function() {
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
    setTitle: function(options) {
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

        var template = (
            ' <img class="favicon-stream-title" src="{fav}" /> {t}' +
            (online_count !== null && total_count !== null ?
             '<span class="visitor_count" title="viewers online/total"><span class="viewers_online">{oc}</span> / {tc}</span>' :
             '') + 
            ' <a href="{u}">view profile</a>');

        this.feedType.set('text', type);
        title = (!url) 
            ? title 
            : template.substitute({fav: favicon, t: title, u: url, oc: online_count, tc: total_count});

        if (options.user)
            title += '<a href="'+url+'?pm" title="send private message">send pm</a>';

        if (!!admin) title = ['<span title="Moderator" class="moderator-title">&#10070;</span> ', title, '<a href="{u}">manage channel</a>'.substitute({u: admin})].join('');
        this.title.set('html', title);

        if (desc) {
            this.topic.set('html', desc.linkify() );
//            this.main.addClass('description');
        } else {
//            this.main.removeClass('description');
        }
        return this;
    },

    /*
    method: linkify
        Replace all text-lloks-like-link into links
    */
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
    parseFeed: function(resp, keep, scrollAndColor) {
        var stream = this.stream;
        if (!keep) stream.empty();
        resp.data = resp.data.reverse();
        if (resp.results && resp.data) {
            var items = Elements.from(_template.parse('taps', resp.data));
            items.each(function(item) {
                var id = item.get('id'),
                        el = $(id);
                if (el) el.destroy();
            });
			items = $$(items);

			if (items.length >= 10 && !keep) ($('loadmore-template').clone()).inject('taps','bottom').setProperty('id', 'loadmore');
			if(keep) publish_type = 'stream.more'; else publish_type = 'stream.new';
            this.publish(publish_type, [items]);
            
            if (scrollAndColor == true) {
                var overallHeight = 0;
                items.each( function (item) {
                    item.addClass('new');
                    item.setStyle('background-color', 'lightyellow');
                    overallHeight += item.getSize().y;
                });

                var curScroll = window.getScroll();
                if (curScroll.y > $('taps').getPosition().y)
                    window.scrollTo(curScroll.x, curScroll.y + overallHeight);
            }

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
    addTaps: function(items ) {
        items.setStyles({opacity:0});
        items.inject(this.stream, 'top');
        if (_stream.loadmore_count > 0) {
            ($('loadless-template').clone()).inject(this.stream,'top').setProperty('id', 'loadless');
        }
        items.fade(1);
        this.publish('stream.updated', this.streamType);
        return this;
    }, 

	addTapsMore: function(items) {
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

    init: function() {
		this.enableLoadMore();

        this.id = _vars.feed.id;
        this.type = _vars.feed.type;
        this.feed = {};
        this.keyword = null;
        this.loadmore_count = 0;

        //this.setStreamVars();
        this.subscribe({
            'list.item': this.setStream.bind(this),
            'stream.new; stream.empty; tapbox.sent; stream.more; stream.live.new': this.addTaps.bind(this),
            'feed.changed': (function(type) {
                this.streamType = type;
            }).bind(this),
            'filter.search': this.changeFeed.bind(this)
        });
    },

	/*
	method: setStream()
		Sets the current stream type
		
		args:
		1. type (string) the type of stream (eg, "groups", "convos")
		2. id (string) the id of the stream corresponding to a group
		3. info (obj) additional group/feedtype data
	*/
    setStream: function(type, id, info) {
        if (['channels', 'peoples', 'private'].contains(type))
            return this.changeFeed(type, id, info);

        return this;
    },

	/*
	method: changeFeed()
		changes the main tapstream
		
		args:
		1. id (string) the id of the group
		2. feed (object) additional data about the group
		3. keyword (string, opt) if present, performs a search rather than just loading taps
        4. more (int) if you want to load more
	*/
    changeFeed: function(type, id, feed, keyword, more, anon) {
        var self = this,
            data = {type: null};

        this.type = type;
        this.id = id;
        this.feed = feed;
        this.keyword = keyword;

        var prefix = ''; //urls prefix
        var user = false;
        if (type == 'channels') {
            prefix = 'channel';
            switch (id) {
                case 'all':
                    data.type = 11;
                    break;

                case 'public':
                    data.type = 100;
                    break;

                case 'feed':
                    data.type = 4;
                    break;

                case 'convos':
                    data.type = 5;
                    break;;

                default:
                    data.type = 1;
                    data.id = id;
            }
        } else if (type == 'peoples') {
            prefix = 'user';
            feed.admin = false;
            feed.online_count = null;
            feed.total_count = null;
            user = true;
            switch (id) {
                case 'all':
                    data.type = 22;
                    break;

                default:
                    data.type = 2;
                    data.id = id;
           }
        } else if (type == 'private') {
            prefix = 'user';
            feed.admin = false;
            feed.online_count = null;
            feed.total_count = null;
            switch (id) {
                case 'all':
                    data.type = 33;
                    break;

                default:
                    data.type = 3;
                    data.id = id;
            }
        }

        var tapbox = $('taptext');

        if (tapbox) {
            if (id == 'public' || id == 'all') {
                $('taptext').disabled = true;
                $('taptext').style.background = 'gray';
            } else {
                $('taptext').disabled = false;
                $('taptext').style.background = 'white';
            }
        }

		
		if (!more) {
			more = false;
			data.more = 0;
			self.loadmore_count = 0;
		} else { 
			data.more = self.loadmore_count;
		}

        if (anon)
            data.anon = 0; //1 for guests only, 0 for registred only
        else {
            var anon_elem = $('anon_filter');
            if (anon_elem && anon_elem.retrieve('state'))
                data.anon = 0;
        }
        
        if (keyword) data.search = keyword;
        new Request({
            url: '/AJAX/taps/filter.php',
            data: data,
            onRequest: this.showLoader.bind(this),
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (!feed.hide)
                    self.setTitle({
                        title: keyword ? ['"', keyword, '" in ', feed.name].join('') : feed.name,
    //					favicon: data.type == 1 ? $$('#gid_'+id+' img.favicon-img')[0].src : '',
                        url: feed.symbol ? '/'+prefix+'/' + feed.symbol : null,
                        type: keyword ? 'search' : 'feed',
                        desc: feed.topic,
                        admin: feed.admin ? '/group_edit?channel=' + feed.symbol : null,
                        online_count: feed.online_count,
                        total_count: feed.total_count,
                        user: user
                    });

                if (response) self.parseFeed(response);
                self.hideLoader();
                self.publish('feed.changed', ['public', 'all'].contains(id) ? id : 'group_' + id);
				self.enableLoadMore();
                self.enableLoadLess();
            }
        }).send();
    },

	enableLoadLess: function(){
		var self = this;
        var loadless = $('loadless');
        if (loadless) {
            loadless.addEvent('click',function(){
                    self.loadmore_count = self.loadmore_count - 10;
                    self.changeFeed(self.type, self.id, self.feed, self.keyword, self.loadmore_count);
            });
        }
	},

	enableLoadMore: function(){
		var self = this;
        var loadmore = $('loadmore');
        if (loadmore) {
            loadmore.addEvent('click',function(){
                    self.loadmore_count = self.loadmore_count + 10;
                    self.changeFeed(self.type, self.id, self.feed, self.keyword, self.loadmore_count);
            });
        }
	},
});

/*
module: _convos
	Controls active conversations for the stream
*/
var _convos = _tap.register({

	mixins: "lists; streaming",

    init: function() {
		//this.setStreamVars();
        this.subscribe({
			'list.item': this.setStream.bind(this),
            'list.action.remove': this.removeConvo.bind(this),
			'feed.changed': (function(type){ this.streamType = type; }).bind(this),
            'responses.track': this.addConvo.bind(this),
            'responses.untrack': this.removeConvo.bind(this)
        });

        _body.addEvents({
            'mouseup:relay(.track-convo)': function(obj, e) {
                this.publish('responses.track', [e.get('cid'),false]);
                e.setStyles({opacity:0});
                e.fade(1);
                e.innerHTML = 'You are following this convo';
                (function() {
                    e.className = 'untrack-convo follow_button'
                }).delay(1000);
            }.bind(this),
            'mouseup:relay(.untrack-convo)': function(obj, e) {
                this.publish('responses.untrack', [e.get('cid'),false])
                e.setStyles({opacity:0});
                e.fade(1);
                e.innerHTML = 'Follow this convo';
                (function() {
                    e.className = 'track-convo follow_button'
                }).delay(1000);
            }.bind(this)
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
			url: '/AJAX/taps/convo.php',
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
		var self = this;
		this.publish('convos.updated', 'cid_' + cid)
		new Request({
			url: '/AJAX/taps/active.php',
			data: {cid: cid, status: 1},
			onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (response.successful) 
    				self.publish('convos.new', [cid, uid, response.data]);
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
			url: '/AJAX/taps/active.php',
			data: {cid: cid, status: 0},
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
    init: function() {
        _body.addEvents({
            'click:relay(a.reply)': this.setupResponse.toHandler(this)
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
    setupResponse: function(el, e) {
        if (e) {
            e.preventDefault();
            el = e.target;
        }

        var parent  = el.getParent('div.feed-item'),
            replies = parent.getElement('div.replies'),
            list    = replies.getElement('div.list'),
            chat    = replies.getElement('textarea');

        replies.toggleClass('hidden');
        parent.getElement('a.comments').toggleClass('hidden');
        parent.getElement('div.latest-reply').toggleClass('hidden');
        parent.getElement('a.reply').toggleClass('hidden');

        if (!list.retrieve('loaded'))
            this.loadResponse(parent.getData('id'), list);

        if (!list.retrieve('extended') && !_vars.guest)
            this.extendResponse(chat, parent.getElement('form'));

        list.scrollTo(0, list.getScrollSize().y);

        if (chat)
            chat.focus();

        return this;
    },

	/*
	method: loadResponse()
		loads previous responses for the tap
		
		args:
		1. id (string) the id of the tap
		2. list (element) the tap's response area element
	*/
    loadResponse: function(id, list) {
        var self = this;
        new Request({
            url: '/AJAX/taps/responses',
            data: {cid: id},
            onSuccess: function() {
                var data = JSON.decode(this.response.text);
                if (!data.responses) return;
                self.addResponses(list.empty(), data.responses);
                self.publish('responses.loaded', id);
                list.store('loaded', true);
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
    addResponses: function(list, data) {
        var items = Elements.from(_template.parse('replies', data.reverse()));
        list.getElements('div.reply-item').removeClass('last');
        items.getLast().addClass('last');
        items.setStyles({opacity:0});
        items.fade(1);
        items.inject(list);
        list.scrollTo(0, list.getScrollSize().y);
        this.publish('responses.updated');
        return this;
    },

	/*
	method: extendResponse()
		adds event handlers to the tap's response area textbox
		
		args:
		1. chatbox (element) the tap's response area element
		2. counter (element) the tap's counter element
		3. overlay (element) the tap's overlay element
	*/
    extendResponse: function(chatbox, form) {
        var self = this,
            limit = 240,
            allowed = {'enter': 1,'up': 1,'down': 1,'left': 1, 'right': 1,'backspace': 1,'delete': 1};

        chatbox.addEvents({
            'keydown': function(e) {
                var outOfLimit = this.get('value').length >= limit;
                if (outOfLimit && !allowed[e.key]) {
                    _notifications.alert('Error', 'Your message is too long', {color: 'darkred'});
                    return e.stop();
                } else if (!outOfLimit) {
                    //all Ok guys, we can hide notification, but no need
                }
            },
            'keypress': function(e) {
                if ((e.code > 48) || !([8, 46].contains(e.code)))
                    self.publish('responses.typing', chatbox);
            },
            'keyup': function(e) {
                var length = this.get('value').length;
                if (e.key == 'enter' && !this.value.isEmpty())
                    return self.sendResponse(chatbox);
            }
        });

        form.addEvent('submit', function (e) {
            e.stop();
            if (!chatbox.value.isEmpty())
                return self.sendResponse(chatbox);
        }.bind(this));
        chatbox.store('extended', true);
    },

	/*
	method: sendResponse()
		sends the response data for a tap to the server
		
		args:
		1. chatbox (element) the tap's response area element
	*/
    sendResponse: function(chatbox) {
        var self = this;
            
        new Request({
            url: '/AJAX/taps/respond',
            data: {
                cid: chatbox.getParent('div.feed-item').getData('id'),
                response: chatbox.value
            },
            onRequest: function() {
                chatbox.value = '';
            },
            onSuccess: function() {
                var data = JSON.decode(this.response.text);
                if (data.success) ;
            }
        }).send();
    },
});

/*
module: _live.typing
	controls the typing indicator
*/
_live.typing = _tap.register({

    init: function() {
        this.subscribe({
            'responses.typing': this.sendTyping.bind(this),
            'push.data.response.typing': this.showTyping.bind(this)
        });
    },

    sendTyping: function(chatbox) {
        if (chatbox.retrieve('typing')) return;
        (function() {
            chatbox.store('typing', false);
        }).delay(1500);
        chatbox.store('typing', true);
        var id = chatbox.getParent('div.feed-item').getData('id');
        new Request({
            url: '/AJAX/user/typing',
            data: {
                cid:   id,
                uid:   _vars.user.id,
                uname: _vars.user.uname
            }
        }).send();
    },

    showTyping: function(tid, user) {
        var indicator, parent = $('tid_' + tid), self = this;
        if (!parent) return;

        indicator = parent.getElement('span.tap-resp');

        var users = indicator.retrieve('users') || new Hash();
        users.set(user, users.get(user) + 1);
        indicator.store('users', users);

        (function() {
            var users = indicator.retrieve('users');
            users.set(user, users.get(user) - 1);
            indicator.store('users', users);

            self.redrawIndicator(indicator);
        }).delay(2000);

        this.redrawIndicator(indicator);
    },

    redrawIndicator: function(indic) {
        var users = indic.retrieve('users');
        if (users.every(function (val) { return val == 0; })) { 
            indic.removeClass('typing');
            return;
        }

        var writers = users.filter(function (val) { return val > 0; }).getKeys();
        if (writers.length >= 2) {
            var last = writers.pop();
            writers = writers.join(', ') + ' & ' + last;
        } else
            writers = writers.pop();

        indic.getElement('span.indicator').set('text', writers + ' typing...');
        indic.addClass('typing');
    }

});

/*
module: _live.responses
	parses the pushed responses from the server
*/
_live.responses = _tap.register({
    init: function() {
        this.subscribe({
            'push.data.response': this.setResponse.bind(this)
        });
    },

    setResponse: function(data) {
        var parent = $('global-' + data.message_id);
        if (!parent) return;

        this.publish('responses.new', [parent.getElement('div.list'), [data]]);
    }
});


/*
module: _tapbox
	controls the main tap sender
*/
var _tapbox = _tap.register({

    sendTo: _vars.sendTo,

    init: function() {
        this.tapbox = $('tapbox');
        if ((!_vars.user) || !this.tapbox) return;
        this.overlayMsg = $('tapto');
        this.msg = $('taptext');

        this.counter = this.tapbox.getElement('span.counter');
        this.overlay = new TextOverlay('taptext', 'tapto');
        this.setupTapBox();
        this.tapbox.addEvent('submit', this.send.toHandler(this));
        this.tapbox.addEvent('click', function(el){
            var tt = $('tapto').innerHTML;
            var ngs = $('no-group-selected');
            if(tt == 'choose a channel to tap'){
                ngs.style.display = 'block';
                ngs.fade('hide');
                ngs.fade(1).fade.delay(4000,ngs,0);
                ngs.setStyles.delay(4700,ngs,{'display':'none'});
            }
        });

        this.subscribe({'list.item': this.handleTapBox.bind(this)});
    },

	/*
	method: setupTapBox()
		adds event handlers to the tapbox
	*/
    setupTapBox: function() {
        var msg = this.msg,
            counter = this.counter,
            limit = 240,
            allowed = {'enter': 1,'up': 1,'down': 1,'left': 1, 'right': 1,'backspace': 1,'delete': 1};

        msg.addEvents({
            'keydown': function(e) {
                if (this.get('value').length >= limit && !allowed[e.key]) return e.stop();
            },
            'keyup': function() {
                var count = limit - this.get('value').length;
                counter.set('text', count);
            }
        });
    },

	/*
	method: clear()
		resets the tapbox
	*/
    clear: function() {
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
    handleTapBox: function(type, id, data) {
        if (!(['channels', 'private'].contains(type))) return;
        this.changeOverlay(id, data.name);
        this.changeSendTo(data.name, data.symbol, id, type);
    },

	/*
	method: changeOverlay()
		changes the overlay text for the tap box
		
		args:
		1. id (string) the id of the group
		2. name (string) the name of the group
	*/
    changeOverlay: function(id, name) {
        var msg = "";
        switch (id) {
            case 'all':
            case 'public': msg = 'tap the public feed..'; break;
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
    changeSendTo: function(name, symbol, id, type) {
        type = type ? type : 'groups';
        this.sendTo = {name: name, symbol: symbol, type: type, id: id};
        return this;
    },

	/*
	handler: send()
		sends the tap to the server when the send button is clicked
	*/
    send: function(el, e) {
        e.stop();
        if (this.msg.isEmpty()) return this.msg.focus();
        new Request({
            url: '/AJAX/taps/new.php',
            data: {
                msg: this.msg.get('value'),
                to: this.sendTo
            },
            onSuccess: this.parseSent.bind(this)
        }).send();
    },

	/*
	method: parseSent()
		injects the recently sent tap to the top of the tapstream
	*/
    parseSent: function(response) {
        var resp = JSON.decode(response);
        if (resp.new_msg) {
            this.clear();
            var item = Elements.from(_template.parse('taps', resp.new_msg));
            this.publish('tapbox.sent', item);
        }
        if (resp.your_first && _vars.user.guest)
            this.publish('modal.show.sign-notify', []);
    }

});

/*
 * module: _resizer
 *
 * Makes responses area resizeable
 */
_resizer = _tap.register({
    init: function() {
        this.makeResizeable();
        this.subscribe({
            'stream.updated': this.makeResizeable.bind(this),
        });
    },

    makeResizeable: function() {
        this.resizers = $$('div.resizer');
        this.resizers.each( function(div) {
            var chat = div.parentNode.getElement('ul.chat');
            var drag = new Drag(chat, {
                snap: 0,
                handle: div,
                modifiers: {y: 'height'},
                onComplete: function(el) {
                    el.scrollTo(0, el.getScrollSize().y);
                }
            });
        });
    },
});

/*
module: _live.stream
	controls the automatic tap streaming

require: _vars
*/
_live.stream = _tap.register({

    init: function() {
        this.convos = this.groups = [];
        this.subscribe({
            'push.connected; stream.updated': this.refreshStream.bind(this)
        });
    },

	/*
	method: refreshStream()
		parses the page and gets user, group and tap ids for the push server
	*/
    refreshStream: function() {
        $$('div.feed-item').each((function(tap) {
            this.convos.push(tap.getData('id'));
            if (tap.getData('gid'))
                this.groups.push(tap.getData('gid'));
        }).bind(this));
        this.convos = this.convos.unique();
        this.groups = this.groups.unique();
        this.update();
    },

	/*
	method: update()
		sends changes to the push server
	*/
    update: function() {
        if (!(this.convos.length && this.groups.length) || _vars.guest)
            return;

        this.publish('push.send', {
            cids: this.convos.join(','),
            gids: this.groups.join(',')
        });
    }

});

/*
module: _live.viewers
	controls the view numbers for the tap stream
*/
_live.viewers = _tap.register({

    init: function() {
        this.subscribe({
            'push.data.view.add; push.data.view.minus': this.change.bind(this)
        });
    },

    change: function(taps, amount) {
        var len, span, parent;
        taps = $splat(taps);
        len = taps.length;
        while (len--) {
            parent = $('tid_' + taps[len]);
            if (!parent) continue;
            span = parent.getElement('span.tap-view-count');
            if (span)
                span.set('text', (span.get('text') * 1) + amount);
        }
        return this;
    }

});

/*
module: _live.users
	controls the offline/online mode for users

require: _body
*/
_live.users = _tap.register({

    init: function() {
        this.subscribe({
            'push.data.user.add': this.setOnline.bind(this),
            'push.data.user.minus': this.setOffline.bind(this)
        });
    },

    setOnline: function(ids) {
        var el, len = ids.length;
        while (len--) {
            el = _body.getElements('[data-uid="' + ids[len] + '"]');
            el.getElement('p.tap-from').removeClass('offline');
        }
    },

    setOffline: function(ids) {
        var el, len = ids.length;
        while (len--) {
            el = _body.getElements('[data-uid="' + ids[len] + '"]');
            el.getElement('p.tap-from').addClass('offline');
        }
    }

});

/*
module: _live.taps
	controls the new taps sent by the push server

require: _responses
*/
_live.taps = _tap.register({

    init: function() {
        var self = this;
        this.pushed = [];
        this.notifier = $('newtaps');
        this.streamer = $('streamer');
        this.stream = !!(this.streamer);
        this.subscribe({
            'push.data.tap.new': this.process.bind(this),
            'push.data.tap.delete': this.deleteTap.bind(this),
            'feed.changed': this.clearPushed.bind(this)
        });
        this.counter = $('taps-count');
    /*    this.notifier.addEvent('click', function(e) {
            e.stop();
            self.showPushed();
            self.hideNotifier();
            self.counter.addClass('hidden');
        });*/
        if (this.streamer) this.streamer.addEvent('click', this.toggleNotifier.toHandler(this));

        window.addEvent('scroll', function () {
            var newTaps = $$('li.container.new');
            if (newTaps.length == 0)
                return;

            var curScroll = window.getScroll();

            newTaps.each( function (tap) {
                if (tap.get('viewed'))
                    return;

                if (tap.getPosition().y < curScroll.y)
                    return;

                tap.set('viewed', true);
                (function () {
                    tap.removeClass('new');
                    var fx = new Fx.Tween(tap);
                    fx.start('background-color', 'lightyellow', 'white');
                }).delay(1500);
            });
        });
    },

    deleteTap: function(data) {
        gid = data['gid'];
        cid = data['cid'];

        var tap = $('tid_'+cid);
        if (!tap)
            return;
        
        var body = tap.getElement('p.tap-body');
        body.set('text', 'This tap deleted');
        tap.addClass('deleted');

        this.publish('tap.deleted', [cid]);
    },

    process: function(data) {
        this.pushed.combine([data]);

        if (this.stream) {
            this.showPushed();
        } else {
            this.showNotifier(this.pushed.length);
        }
    },

    showPushed: function() {
        _stream.parseFeed({results: true, data: this.pushed}, true, true);
        this.clearPushed();
    },

    clearPushed: function(type) {
        this.pushed = [];
        this.hideNotifier();
    },

    showNotifier: function(count) {
        var notifier = this.notifier;
        notifier.set('text', [
            count, 'new', count == 1 ? 'tap.' : 'taps.', 'Click here to load them.'
        ].join(' '));
        this.counter.set('text', count);
        notifier.addClass('notify');
    },

    hideNotifier: function() {
        this.notifier.set('text', '').removeClass('notify');
    },

    toggleNotifier: function(el) {
        if (el.hasClass('paused')) {
            this.stream = true;
            el.removeClass('paused').set({
                'alt': 'pause live streaming',
                'title': 'pause live streaming',
				'text' : ''	
            });
        } else {
            this.stream = false;
            el.addClass('paused').set({
                'alt': 'start live streaming',
                'title': 'start live streaming',
				'text':'paused'
            });
        }
    }

});

/*
 * module: _live.notifications
 *
 * Shows notifications on new response in your convos,
 * new tap in followed channels,
 * new followers & new private messages
*/
_live.notifications = _tap.register({
    init: function() {
       var self = this;
        this.subscribe({
            'push.data.notify.convo.response': this.newConvoResponse.bind(this),
            'push.data.notify.tap.new': this.newTap.bind(this),
            'push.data.notify.follower': this.newFollower.bind(this),
            'push.data.notify.private': this.newPrivate.bind(this)
        });
    },

    newConvoResponse: function(data) {
        cid = data['cid'];
        uname = data['uname'];
        ureal_name = data['ureal_name'] ? data['ureal_name'] : uname;
        text = data['text'];
        avatar = '/user_pics/'+data['avatar'];
        group_avatar = '/group_pics/'+data['group_avatar'];

        _notifications.alert('Response from:<br><a href="/user/'+uname+'">' + ureal_name + '</a>',
            '"' + text + '"',
            {avatar: avatar, group_avatar: group_avatar});
    	
        _notifications.items.getLast().addEvent('click', function() {
        	document.location.replace('http://'+document.domain+'/tap/'+cid);
    	});
    },

    newTap: function(data) {
        gname = data['gname'];
        greal_name = data['greal_name'];
        uname = data['uname'];
        ureal_name = data['ureal_name'] ? data['ureal_name'] : uname;
        text = data['text'];
        avatar = '/user_pics/'+data['avatar'];
        group_avatar = '/group_pics/'+data['group_avatar'];

        _notifications.alert('New tap:<br><a href="/user/'+uname+'">' + ureal_name +'</a>',
            '"' + text + '"',
            {avatar: avatar, group_acatar: group_avatar});

    	_notifications.items.getLast().addEvent('click', function() {
        	document.location.replace('http://'+document.domain+'/channel/'+gname);
    	});
    },

    newFollower: function(data) {
        status = data['status'];
        uname = data['uname'];
        ureal_name = data['ureal_name'] ? data['ureal_name'] : uname;
        avatar = '/user_pics/'+data['avatar'];

        var title = '';
        var message = '';
        if (status) {
            title = 'New follower:<br><a href="/user/'+uname+'">' + ureal_name + '</a>';
            message = 'Will follows you everywhere in your tap journey!';
        } else {
            title = 'Follower gone:<br><a href="/user/'+uname+'">' + ureal_name +'</a>';
            message = "doesn't follow you anymore :(";
        }
        _notifications.alert(title, message, {avatar:avatar});

    	_notifications.items.getLast().addEvent('click', function() {
        	document.location.replace('http://'+document.domain+'/user/'+uname);
    	});
    },

    newPrivate: function(data) {
        cid = data['cid'];
        uname = data['uname'];
        ureal_name = data['ureal_name'] ? data['ureal_name'] : uname;
        text = data['chat_text'];
        avatar = '/user_pics/'+data['pic_100'];

        _notifications.alert('New PM:<br><a href="/user/'+uname+'">' + ureal_name + '</a>',
            '"' + text + '"',
            {avatar: avatar});

    	_notifications.items.getLast().addEvent('click', function() {
        	document.location.replace('http://'+document.domain+'/tap/'+cid);
    	});

    }
});

// UNCOMMENT FOR PROD
// })();
