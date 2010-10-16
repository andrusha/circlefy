/*
mixin: streaming
	mixin for streaming/feedlist operations
*/
_tap.mixin({

    name: 'streaming',

    setStreamVars: function() {
        this.feed = $('feed');
    },

	/*
	method: parseFeed()
		parses the taps data in order to create the html for the feedlist
		
		args:
		1. resp (obj) the response object from the xhr.
		2. keep (bool) if true, taps already in the feed are not removed.
	*/
    parseFeed: function(data, keep, scrollAndColor) {
         if (!keep)
             this.feed.empty();

         if (!data)
             return;

         if (_vars.feed.type == 'group')
             data.hide_group_icon = true;

         var items = Elements.from(_template.parse('messages', data));
        
         if (scrollAndColor) {
             var overallHeight = 0;
             items.each( function (item) {
                 item.addClass('new');
                 overallHeight += item.getSize().y;
             });
 
             var curScroll = window.getScroll();
             if (curScroll.y > this.feed.getPosition().y)
                 window.scrollTo(curScroll.x, curScroll.y + overallHeight);
        }

        this.feed.getElements('div.feed-item').removeClass('last');
        items.setStyles({opacity:0});

        items.inject(this.feed, scrollAndColor ? 'top' : 'bottom');
        this.feed.getElements('div.feed-item').getLast().addClass('last');
        items.fade(1);

        this.publish('feed.updated', []);
    }
});

/*
module: _stream
	Controls the main tapstream/feedlist
*/
var _stream = _tap.register({

    mixins: 'streaming',

    init: function() {
        this.setStreamVars();
        this.pos = 0;

        this.subscribe({
            'feed.change': this.changeFeed.bind(this),
            'feed.search': (function (keyword) {
                this.changeFeed(null, null, null, keyword, 0);
            }).bind(this)
        });

		this.enableLoadMore();
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
    changeFeed: function(type, id, info, keyword, more, inside) {
        var self = this,
            data = {},
            info = info ? info : {};

        _vars.feed.type = data.type = type ? type : _vars.feed.type;
        _vars.feed.id   = data.id   = id ? id : _vars.feed.id;
        _vars.feed.inside = data.inside = inside ? inside : 0;

        _vars.feed.keyword = data.search = keyword ? keyword : (_vars.feed.keyword ? _vars.feed.keyword : '');
        data.more = this.pos = more ? more : this.pos;

        new Request({
            url: '/AJAX/taps/filter',
            data: data,
            onSuccess: function() {
                var response = JSON.decode(this.response.text);

                if (response.success) 
                    self.parseFeed(response.data);
                else
                    self.feed.empty();
            
                if (response.data.length == 10) {
                    Elements.from($('template-loadmore').innerHTML).inject(self.feed, 'bottom');
	    			self.enableLoadMore();
                }
                if (data.more) {
                    Elements.from($('template-loadless').innerHTML).inject(self.feed, 'top');
                    self.enableLoadLess();
                }
                
                if (info.feed)
                    self.feed.getSiblings('h1.title>span')[0].innerHTML = info.feed;

                self.publish('feed.changed', [data.type, data.id]);
            }
        }).send();
    },

	enableLoadLess: function(){
        var loadless = $('loadless');
        if (loadless) {
            loadless.addEvent('click',function(){
                this.pos -= 10;
                this.changeFeed();
            }.bind(this));
        }
	},

	enableLoadMore: function(){
        var loadmore = $('loadmore');
        if (loadmore) {
            loadmore.addEvent('click',function(){
                this.pos += 10; 
                this.changeFeed();
            }.bind(this));
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
        var form = $$('div.forced-bottom > form#reply')[0];
        if (form) {
            var chat = form.getElement('textarea');
            chat.overlay = new OverText(chat, {
            positionOptions: {
                offset: {x: 10, y: 7},
                relativeTo: chat,
                relFixedPosition: false,
                ignoreScroll: true,
                ignoreMargin: true
            }}).show();

            this.extendResponse(chat, form);
            
            var parent = form.getParent('div#left'),
                list = parent.getElement('div#feed');

            window.scrollTo(0, window.getScrollSize().y);
        }

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

        this.count  = parent.getElement('a.comments');
        this.latest = parent.getElement('div.latest-reply');

        parent.toggleClass('opened');

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
        var items = Elements.from(_template.parse('replies', data)),
            elems = list.getElements('div.reply-item');

        elems.removeClass('last');
        if (items.length) {
            var last = items.getLast();
            last.addClass('last');

            var parent = list.getParent('div.text');
            //already have an reply and add one
            if (parent)
                this.updateLast(data.getLast(), parent);
        }
        items.setStyles({opacity:0});
        items.fade(1);
        items.inject(list);
        list.scrollTo(0, list.getScrollSize().y);
        if (_vars.feed.type == 'conversation')
            window.scrollTo(0, window.getScrollSize().y);
        list.getParent('div.feed-item').removeClass('empty');
        this.publish('responses.updated');
        return this;
    },

    updateLast: function (reply, parent) {
        var count = parent.getElement('a.comments');
        if (count)
            count.innerHTML = count.innerHTML*1 + 1;

        var latest = parent.getElement('div.latest-reply');
        if (latest) {
            var img = latest.getElement('img');
            img.src = '/static/user_pics/small_'+reply.user.id+'.jpg';
            img.alt = reply.user.fname + ' ' + reply.user.lname;
            var a   = latest.getElement('a');
            a.href  = '/user/'+reply.user.uname;
            a.innerHTML = reply.user.uname+':';
            latest.getElement('span').innerHTML = reply.text;
        }
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
            'keypress': function(e) {
               var outOfLimit = this.value.length >= limit;
                if (outOfLimit && !allowed[e.key]) {
                    _notifications.alert('Error', 'Your message is too long', {color: 'darkred', once: 'too-long'});
                    return e.stop();
                }

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
        if (chatbox.value.length > 240) {
            _notifications.alert('Error', 'Your message is too long', {color: 'darkred'});
            return;
        }
        var id = _vars.feed.type != 'conversation' ? chatbox.getParent('div.feed-item').getData('id') : _vars.feed.id;

        new Request({
            url: '/AJAX/taps/respond',
            data: {
                cid: id,
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
        var id = _vars.feed.type != 'conversation' ? chatbox.getParent('div.feed-item').getData('id')*1 : _vars.feed.id*1;
        _push.send({action: 'response.typing', cid: id, data: {cid: id, uid: _vars.user.id*1, uname: _vars.user.uname}});
    },

    showTyping: function(data) {
        //if (_vars.feed.type != 'conversation' && data.cid != _vars.feed.id)
        //    return;

        var item = $('typing-'+data.uid);
        if (!item)
            item = Elements.from(_template.parse('typing', data))[0];

        clearTimeout(item.timeout);

        item.timeout = (function() {
            item.destroy();
        }).delay(2000);

        if (_vars.feed.type == 'conversation' && data.cid == _vars.feed.id)
            item.inject($('sidebar').getElement('div.wrap'));
        else {
            var parent = $('global-'+data.cid);
            if (parent)
                item.inject(parent.getElement('div.resizer'));
        }
    },
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
        if (_vars.feed.type == 'conversation')
            var parent = $('left');
        else
            var parent = $('global-' + data.message_id);
        if (!parent) return;

        if (_vars.feed.type == 'conversation')
            var list = parent.getElement('div#feed');
        else
            var list = parent.getElement('div.list');

        this.publish('responses.new', [list, [data]]);
    }
});


/*
module: _tapbox
	controls the main tap sender
*/
var _tapbox = _tap.register({

    sendTo: _vars.sendTo,

    init: function() {
        var form = this.form = $$('div#left > form#reply')[0];
        if ((!_vars.user) || !this.form) return;

        this.sendType = 'group';
        this.sendTo   = _vars.feed.id;

        var tapbox = this.tapbox = form.getElement('textarea');
        tapbox.overtext = new OverText(tapbox, {
            positionOptions: {
                offset: {x: 10, y: 7},
                relativeTo: tapbox,
                relFixedPosition: false,
                ignoreScroll: true,
                ignoreMargin: true
            }}).show();

        form.addEvent('submit', this.send.toHandler(this));
    },

	/*
	method: setupTapBox()
		adds event handlers to the tapbox
	*/
    setupTapBox: function() {
        var msg = this.tapbox,
            limit = 240,
            allowed = {'enter': 1,'up': 1,'down': 1,'left': 1, 'right': 1,'backspace': 1,'delete': 1};

        msg.addEvents({
            'keypress': function(e) {
                if (msg.value.length >= limit && !allowed[e.key]) return e.stop();
            }
        });
    },

	/*
	handler: send()
		sends the tap to the server when the send button is clicked
	*/
    send: function(el, e) {
        e.stop();
        if (this.tapbox.value.isEmpty())
            return this.tapbox.focus();

        new Request({
            url: '/AJAX/taps/new',
            data: {
                msg:  this.tapbox.value,
                type: this.sendType,
                id:   this.sendTo
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
        if (resp.success) {
            //yay
        }
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
            'feed.updated': this.makeResizeable.bind(this),
        });
    },

    makeResizeable: function() {
        this.resizers = $$('div.resizer');
        this.resizers.each( function(div) {
            div.getSiblings('div.replies > div.list')[0].makeResizable({
                handle: div,
                snap: 0,
                modifiers: {y: 'height', x: null},
                limit: {y: [250, 650]},
                onStart: function (el) {
                    var h = el.getScrollSize().y,
                        c = el.getStyle('height');
                    el.setStyles({
                        height: (el.getStyle('height') > h ? h : c),
                        'max-height': h,
                    });
                },
                onComplete: function(el) {
                    el.scrollTo(0, el.getScrollSize().y);
                }
            });
        });
    },
});

_warning = _tap.register({
    init: function() {
        this.warning = $$('div.warning')[0];
        if (!this.warning)
            return;

        this.txt = this.warning.getElement('span');

        this.warning.getElement('a.close').addEvent('click', (function (e) {
            this.hide();
        }).bind(this));
    },

    show: function(text, action) {
        this.txt.innerHTML = text;
        this.warning.removeClass('hidden');
        this.warning.addEvent('click', action);
    },

    hide: function() {
        this.warning.addClass('hidden');
    }
});

_controls = _tap.register({
    init: function() {
        this.controls = $('controls');
        if (!this.controls)
            return;
        this.tabs = this.controls.getElements('a.tab');

        this.subscribe('feed.changed', function (type, id) {
            if (['feed', 'aggr_groups', 'group'].contains(type))
                this.show();
            else
                this.hide();
            this.tabs.removeClass('active');
            if (_vars.feed.inside)
                this.controls.getElement('a.tab[data-inside="'+_vars.feed.inside+'"]').addClass('active');
        }.bind(this));

        this.tabs.addEvent('click', this.toggle.toHandler(this));
    },

    show: function() {
        this.controls.removeClass('hidden');
    },

    hide: function() {
        this.controls.addClass('hidden');
    },

    toggle: function(el, e) {
        this.tabs.removeClass('active');
        el.addClass('active');
        _vars.feed.inside = el.getData('inside');
        this.publish('feed.change', [null, null, null, null, null, _vars.feed.inside]);
    }
});

/*
module: _live.stream
	controls the automatic tap streaming

require: _vars
*/
_live.stream = _tap.register({

    init: function() {
        this.convos = [];
        this.groups = [];
        this.subscribe({
            'push.connected; feed.updated': this.refreshStream.bind(this)
        });
    },

	/*
	method: refreshStream()
		parses the page and gets user, group and tap ids for the push server
	*/
    refreshStream: function() {
        $$('div#feed > div.feed-item[data-id]').each((function(tap) {
            this.convos.push(tap.getData('id'));
            if (tap.getData('gid'))
                this.groups.push(tap.getData('gid'));
        }).bind(this));

        if (_vars.feed.type == 'conversation')
            this.convos.push(_vars.feed.id*1);

        this.convos = this.convos.unique();
        this.groups = this.groups.unique();
        this.update();
    },

	/*
	method: update()
		sends changes to the push server
	*/
    update: function() {
        if (!(this.convos.length || this.groups.length) || _vars.guest)
            return;

        this.publish('push.send', {
            cids: this.convos.join(','),
            gids: this.groups.join(',')
        });
    }

});

/*
module: _live.taps
	controls the new taps sent by the push server

require: _responses
*/
_live.taps = _tap.register({

    init: function() {
        this.pushed = [];
        this.pause = $('pause');
        this.stream = true;
        this.subscribe({
            'push.data.tap.new': this.process.bind(this),
            'push.data.tap.delete': this.deleteTap.bind(this),
            'feed.changed': this.clearPushed.bind(this)
        });

        if (this.pause)
            this.pause.addEvent('click', (function () {
                this.stream = !this.stream;
                this.pause.toggleClass('active');
            }).bind(this));

        window.addEvent('scroll', function () {
            var newTaps = $$('div.feed-item.new');
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
                    var color = tap.getStyle('background-color');
                    var fx = new Fx.Tween(tap);
                    fx.chain(function () {tap.removeClass('new');});
                    fx.start('background-color', color, 'transparent');
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
            _warning.show('There  is '+this.pushed.length+' new taps.\n Click on warning to load them.', 
                (function() {
                    this.pause.fireEvent('click');
                    this.showPushed();
                }).bind(this));
        }
    },

    showPushed: function() {
        _stream.parseFeed(this.pushed, true, true);
        this.clearPushed();
        window.fireEvent('scroll');
    },

    clearPushed: function(type) {
        this.pushed = [];
        _warning.hide();
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
        this.subscribe({
            'push.data.response': this.newConvoResponse.bind(this),
            'push.data.tap.new': this.newTap.bind(this),
            'push.data.notify.follower': this.newFollower.bind(this),
        });
    },

    newConvoResponse: function(data) {
        if (data['user']['id'] == _vars.user.id)
            return;

        cid = data['message_id'];
        uname = data['user']['uname'];
        ureal_name = data['user']['fname'] + ' ' + data['user']['lname'];
        text = data['text'];
        avatar = '/static/user_pics/small_'+data['user']['id']+'.jpg';
        group_avatar = '/static/group_pics/small_'+data['group_id']+'.jpg';

        _notifications.alert('Response from:<br><a href="/user/'+uname+'">' + ureal_name + '</a>',
            '"' + text + '"',
            {avatar: avatar, group_avatar: group_avatar});
    	
        _notifications.items.getLast().addEvent('click', function() {
        	document.location.href = 'http://'+document.domain+'/convo/'+cid;
    	});
    },

    newTap: function(data) {
        if (data.sender.id == _vars.user.id)
            return;

        var sender = data.sender,
            avatar = '/static/user_pics/small_'+sender.id+'.jpg';

        if (data.group && data.group.id) {
            _notifications.alert('New tap:<br><a href="/user/'+sender.uname+'">' + sender.fname + ' ' + sender.lname +'</a>',
                '"' + data.text + '"',
                {avatar: avatar, group_avatar: '/static/group_pics/small_'+data.group.id+'.jpg'});

            _notifications.items.getLast().addEvent('click', function() {
                document.location.href = 'http://'+document.domain+'/circle/'+data.group.symbol;
            });
        } else if (data.reciever && data.reciever.id) {
            _notifications.alert('New PM:<br><a href="/user/'+sender.uname+'">' + sender.fname + ' ' + sender.lname + '</a>',
                '"' + data.text + '"',
                {avatar: avatar});

            _notifications.items.getLast().addEvent('click', function() {
                document.location.href = 'http://'+document.domain+'/convo/'+data.id;
            });
        }
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
        	document.location.gref = 'http://'+document.domain+'/user/'+uname;
    	});
    },
});

/*
module: _filter
	Controls the filter/search bar for the tapstream
*/
var _filter = _tap.register({

    init: function() {
        var box = this.box = $('search');
        if (!this.box) return;
        box.over = new OverText(box, {
            positionOptions: {
                offset: {x: 10, y: 8},
                relativeTo: box,
                relFixedPosition: false,
                ignoreScroll: true,
                ignoreMargin: true
            }}).show();

        box.getParents('form').addEvent('submit',
            (function (e) {
                e.stop();
                var keyword = box.value;
                if (!keyword.isEmpty())
                    this.search(keyword);
            }).bind(this));

        this.subscribe('feed.updated', (function() {
            box.value = '';
        }).bind(this));
    },
	
    /*
	method: search()
		main control logic for searching
		
		args:
		1. keyword (string, opt) the keyword to use for searching; if null, the search is cleared
	*/
    search: function(keyword) {
        this.publish('feed.search', [keyword]);
        return this;
    }
});

