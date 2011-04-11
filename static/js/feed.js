/*global _tap, _template, Elements, _vars, window, Request, _body, $$, $, OverText, _notifications, _live, _push, _controls, Fx, document */

/*
mixin: streaming
    mixin for streaming/feedlist operations
*/
_tap.mixin({

    name: 'streaming',

    setStreamVars: function () {
        this.feed = $('feed');
    },

    /*
    method: parseFeed()
        parses the taps data in order to create the html for the feedlist
        
        args:
        1. resp (obj) the response object from the xhr.
        2. keep (bool) if true, taps already in the feed are not removed.
    */
    parseFeed: function (data, keep, scrollAndColor) {
        if (!keep) {
            this.feed.empty();
        }

        if (!data) {
            return;
        }

        if (_vars.feed.type == 'group') {
            data.hide_group_icon = true;
        }

        var items = Elements.from(_template.parse('messages', data));

        if (scrollAndColor) {
            var overallHeight = 0;
            items.each(function (item) {
                item.addClass('new');
                overallHeight += item.getSize().y;
            });

            var curScroll = window.getScroll();
            if (curScroll.y > this.feed.getPosition().y) {
                window.scrollTo(curScroll.x, curScroll.y + overallHeight);
            }
        }

        this.feed.getElements('div.feed-item').removeClass('last');
        items.setStyles({opacity: 0});

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

    init: function () {
        this.setStreamVars();
        this.pos = 0;

        this.subscribe({
            'feed.change': this.changeFeed.bind(this),
            'feed.search': function (keyword) {
                this.changeFeed(null, null, null, keyword, 0);
            }.bind(this)
        });

        this.enableLoadMore();
        
        // dispatch feed initialization
        this.publish('feed.init');
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
    changeFeed: function (type, id, info, keyword, more, inside, anon) {
        var self = this,
            data = {};

        info = info ? info : {};

        _vars.feed.type = data.type = type ? type : _vars.feed.type;
        _vars.feed.id   = data.id   = id ? id : _vars.feed.id;
        _vars.feed.inside = data.inside = (!!inside || inside === 0) ? inside : (_vars.feed.inside ? _vars.feed.inside : 0);
        _vars.feed.anon = data.anon = anon ? 1 : 0;

        _vars.feed.keyword = data.search = keyword ? keyword : (_vars.feed.keyword ? _vars.feed.keyword : '');
        data.more = this.pos = !!(more | more === 0) ? more : this.pos;

        new Request({
            url: '/AJAX/taps/filter',
            data: data,
            onSuccess: function () {
                var response = JSON.decode(this.response.text);

                if (response.success) {
                    self.parseFeed(response.data);
                } else {
                    self.feed.empty();
                }
            
                if (response.more) {
                    Elements.from($('template-loadmore').innerHTML).inject(self.feed, 'bottom');
                    self.enableLoadMore();
                }
                if (data.more) {
                    Elements.from($('template-loadless').innerHTML).inject(self.feed, 'top');
                    self.enableLoadLess();
                }
                
                if (info.feed) {
                    self.feed.getSiblings('h1.title>span')[0].innerHTML = info.feed;
                }

                self.publish('feed.changed', [data.type, data.id]);
            }
        }).send();
    },

    enableLoadLess: function () {
        var loadless = $('loadless');
        if (loadless) {
            loadless.addEvent('click', function () {
                this.pos -= 10;
                this.changeFeed();
            }.bind(this));
        }
    },

    enableLoadMore: function () {
        var loadmore = $('loadmore');
        if (loadmore) {
            loadmore.addEvent('click', function () {
                this.pos += 10; 
                this.changeFeed();
            }.bind(this));
        }
    }
});

/*
module: _convos
    Controls active conversations for the stream
*/
var _convos = _tap.register({

    mixins: "lists; streaming",

    init: function () {
        //this.setStreamVars();
        this.subscribe({
            'list.item': this.setStream.bind(this),
            'list.action.remove': this.removeConvo.bind(this),
            'feed.changed': function (type) {
                this.streamType = type; 
            }.bind(this),
            'responses.track': this.addConvo.bind(this),
            'responses.untrack': this.removeConvo.bind(this)
        });

        _body.addEvents({
            'mouseup:relay(.track-convo)': function (obj, e) {
                this.publish('responses.track', [e.get('cid'), false]);
                e.setStyles({opacity: 0});
                e.fade(1);
                e.innerHTML = 'You are following this convo';
                (function () {
                    e.className = 'untrack-convo follow_button';
                }).delay(1000);
            }.bind(this),
            'mouseup:relay(.untrack-convo)': function (obj, e) {
                this.publish('responses.untrack', [e.get('cid'), false]);
                e.setStyles({opacity: 0});
                e.fade(1);
                e.innerHTML = 'Follow this convo';
                (function () {
                    e.className = 'track-convo follow_button';
                }).delay(1000);
            }.bind(this)
        });
    },

    setStream: function (type, id, info) {
        if (type == 'convos') {
            return this.changeFeed(id, info);
        }
        return this;
    },

/*
    method: openConvo()
        automatically opens the active convo's responses area
    */
    openConvo: function () {
        var el = this.stream.getElement('a.tap-resp-count');
        if (el) {
            this.publish('convos.loaded', el);
        }
    },

    /*
    method: addConvo()
        tells the server that the user has responded to a convo
        
        args:
        1. cid (string) the active convo id
        2. uid (string) the user id of the original tapper
        3. data (obj) additional data about the active convo
    */
    addConvo: function (cid, uid, data) {
        var self = this;
        this.publish('convos.updated', 'cid_' + cid);
        new Request({
            url: '/AJAX/taps/active.php',
            data: {cid: cid, status: 1},
            onSuccess: function () {
                var response = JSON.decode(this.response.text);
                if (response.successful) { 
                    self.publish('convos.new', [cid, uid, response.data]);
                }
            }
        }).send();
    },

    /*
    method: removeConvo()
        tells the server that a user wants to be removed from an active convo
        
        args:
        1. id (string) the id of the active convo
    */
    removeConvo: function (cid) {
        var self = this;
        new Request({
            url: '/AJAX/taps/active.php',
            data: {cid: cid, status: 0},
            onSuccess: function () {
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
    init: function () {
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
                }
            }).show();

            this.extendResponse(chat, form);
            
            var parent = form.getParent('div#left'),
                list = parent.getElement('div#feed');

            window.scrollTo(0, window.getScrollSize().y);
        }
    
        _body.addEvents({
                'click:relay(a.reply)': this.setupResponse.toHandler(this),
                'click:relay(a.comments)': this.setupResponse.toHandler(this),
                'click:relay(div.feed-item > img.avatar-author)': this.setupResponse.toHandler(this),
                'click:relay(div.latest-reply > img)': this.setupResponse.toHandler(this),
                'click:relay(div.latest-reply > span)': this.setupResponse.toHandler(this),
                'click:relay(div.reply-item > div.thumb > img)': this.addUname.toHandler(this)
            });

        this.subscribe({
            'responses.new': this.addResponses.bind(this),
            'convos.loaded': this.setupResponse.bind(this)
        });
    },

    addUname: function (el, e) {
        e.stop();

        var textarea = el.getParent('div.replies').getElement('textarea');
        textarea.value += '@' + el.getData('uname') + ' ';
    }, 

    /*
    handler: setupResponse()
        adds event handlers to the taps' responses area
        NOTE: toggleNotifier pauses the stream
    */
    setupResponse: function (el, e) {
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

        if (!list.retrieve('loaded')) {
            this.loadResponse(parent.getData('id'), list);
        }

        if (!list.retrieve('extended') && !_vars.guest) {
            this.extendResponse(chat, parent.getElement('form'));
        }

        list.scrollTo(0, list.getScrollSize().y);

        if (chat) {
            chat.focus();
        }

        return this;
    },

    /*
    method: loadResponse()
        loads previous responses for the tap
        
        args:
        1. id (string) the id of the tap
        2. list (element) the tap's response area element
    */
    loadResponse: function (id, list) {
        var self = this;
        new Request({
            url: '/AJAX/taps/responses',
            data: {cid: id},
            onSuccess: function () {
                var data = JSON.decode(this.response.text);
                if (!data.responses) {
                    return;
                }
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
    addResponses: function (list, data) {
        var items = Elements.from(_template.parse('replies', data)),
            parent = list.getParent('div.text');

        //quit if there is no items
        if (!items.length) {
            this.publish('responses.updated');
            return this;
        }

        var elements = list.getElements('div.reply-item');
        if (elements.length) {
            elements.getLast().removeClass('last');
        }

        this.preprocess(items);
        items.getLast().addClass('last');

        items.setStyles({opacity: 0});
        items.inject(list);
        items.fade(1);
        list.scrollTo(0, list.getScrollSize().y);

        elements = list.getElements('div.reply-item');

        var count = null;
        if (_vars.feed.type == 'conversation') {
            window.scrollTo(0, window.getScrollSize().y);
            count = $$('span.stats > span')[0];
        } else {
            this.updateLast(data.getLast(), parent);

            var resizer = parent.getElement('div.resizer');
            if (elements.length <= 5 && resizer) {
                resizer.addClass('hidden');
            } else if (resizer) {
                resizer.removeClass('hidden');
            }

            parent = list.getParent('div.feed-item');
            if (parent && items.length) {
                parent.removeClass('empty');
            }

            count = parent.getElement('a.comments');
        }

        if (count) {
            count.innerHTML = elements.length;
        }

        this.publish('responses.updated');
    },

    preprocess: function (items) {
        items.each(function (el) {
            var text = el.getElement('span.reply-text');

            //bearification
            text.innerHTML = text.innerHTML.replace(/\(hug\)/ig, '<img src="/static/images/bear.gif" alt="hug">');

            if (_vars.user && _vars.user.uname) {
                var dogReg = new RegExp('@' + _vars.user.uname + '(?:\\s|$)', 'gim');
                if (dogReg.test(text.innerHTML)) {
                    el.addClass('targeted');
                }
            }
        });
    },

    updateLast: function (reply, parent) {
        var latest = parent.getElement('div.latest-reply');
        if (!latest) {
            return; 
        }

        var img = latest.getElement('img');
        img.src = '/static/user_pics/small_' + reply.user.id + '.jpg';
        img.alt = reply.user.fname + ' ' + reply.user.lname;
        var a   = latest.getElement('a');
        a.href  = '/user/' + reply.user.uname;
        a.innerHTML = reply.user.uname + ':';
        latest.getElement('span').innerHTML = reply.text.limit(40);
    },

    /*
    method: extendResponse()
        adds event handlers to the tap's response area textbox
        
        args:
        1. chatbox (element) the tap's response area element
        2. counter (element) the tap's counter element
        3. overlay (element) the tap's overlay element
    */
    extendResponse: function (chatbox, form) {
        var self = this,
            limit = 240,
            allowed = {'enter': 1, 'up': 1, 'down': 1, 'left': 1, 'right': 1, 'backspace': 1, 'delete': 1};

        chatbox.addEvents({
            'keypress': function (e) {
                var outOfLimit = this.value.length >= limit;
                if (outOfLimit && !allowed[e.key]) {
                    _notifications.alert('Error', 'Your message is too long', {color: 'darkred', once: 'too-long'});
                    return e.stop();
                }

                if ((e.code > 48) || !([8, 46].contains(e.code))) {
                    self.publish('responses.typing', chatbox);
                }
            },
            'keyup': function (e) {
                var length = this.get('value').length;
                if (e.key == 'enter' && !this.value.isEmpty()) {
                    return self.sendResponse(chatbox);
                }
            }
        });

        form.addEvent('submit', function (e) {
            e.stop();
            if (!chatbox.value.isEmpty()) {
                return self.sendResponse(chatbox);
            }
        }.bind(this));
        chatbox.store('extended', true);
    },

    /*
    method: sendResponse()
        sends the response data for a tap to the server
        
        args:
        1. chatbox (element) the tap's response area element
    */
    sendResponse: function (chatbox) {
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
            onRequest: function () {
                chatbox.value = '';
            }
        }).send();
    }
});

/*
module: _live.typing
    controls the typing indicator
*/
_live.typing = _tap.register({

    init: function () {
        this.subscribe({
            'responses.typing': this.sendTyping.bind(this),
            'push.data.response.typing': this.showTyping.bind(this)
        });
    },

    sendTyping: function (chatbox) {
        if (chatbox.retrieve('typing')) {
            return;
        }

        (function () {
            chatbox.store('typing', false);
        }).delay(1500);
        chatbox.store('typing', true);
        var id = _vars.feed.type != 'conversation' ? chatbox.getParent('div.feed-item').getData('id').toInt() : _vars.feed.id.toInt();
        _push.send({action: 'response.typing', data: {cid: id, uid: _vars.user.id.toInt(), uname: _vars.user.uname}});
    },

    showTyping: function (data) {
        var item = $('typing-' + data.cid + '-' + data.uid),
            founded = false;
        if (!item) {
            item = Elements.from(_template.parse('typing', data))[0];
        } else {
            item.removeClass('dim');
            clearTimeout(item.timeout);
            clearTimeout(item.timeout2);
            founded = true;
        }

        item.timeout = function () {
            item.addClass('dim');
        }.delay(2000);

        item.timeout2 = function () {
            item.destroy();
        }.delay(5000);

        if (founded) {
            return;
        }

        if (_vars.feed.type == 'conversation' && data.cid == _vars.feed.id) {
            item.inject($('sidebar').getElement('div.wrap'));
        } else {
            var parent = $('global-' + data.cid);
            if (parent) {
                item.inject(parent.getElement('div.typing'));
            }
        }
    }
});

/*
module: _live.responses
    parses the pushed responses from the server
*/
_live.responses = _tap.register({
    init: function () {
        this.subscribe({
            'push.data.response.new': this.setResponse.bind(this)
        });
    },

    setResponse: function (data) {
        var parent = null, 
            list = null;

        if (_vars.feed.type == 'conversation') {
            parent = $('left');
        } else {
            parent = $('global-' + data.message_id);
        }
        if (!parent) {
            return;
        }

        if (_vars.feed.type == 'conversation') {
            list = parent.getElement('div#feed');
        } else {
            list = parent.getElement('div.list');
        }

        this.publish('responses.new', [list, [data]]);
    }
});


/*
module: _tapbox
    controls the main tap sender
*/
var _tapbox = _tap.register({

    sendTo: _vars.sendTo,

    init: function () {
        var form = this.form = $$('div#left > form#reply')[0];
        if ((!_vars.user) || !this.form) { 
            return;
        }

        this.sendType  = _vars.feed.type;
        this.sendTo    = _vars.feed.id;

        var tapbox = this.tapbox = form.getElement('textarea');
        tapbox.overtext = new OverText(tapbox, {
            positionOptions: {
                offset: {x: 10, y: 7},
                relativeTo: tapbox,
                relFixedPosition: false,
                ignoreScroll: true,
                ignoreMargin: true
            }
        }).show();

        form.addEvent('submit', this.send.toHandler(this));
        this.setupTapBox();
    },

    /*
    method: setupTapBox()
        adds event handlers to the tapbox
    */
    setupTapBox: function () {
        var msg = this.tapbox,
            limit = 240,
            allowed = {'enter': 1, 'up': 1, 'down': 1, 'left': 1, 'right': 1, 'backspace': 1, 'delete': 1},
            self = this;

        msg.addEvents({
            'keypress': function (e) {
                if (e.control && e.key == 'enter') {
                    return self.form.fireEvent('submit', [e]);
                }

                if (msg.value.length >= limit && !allowed[e.key]) {
                    return e.stop();
                }
            }
        });
    },

    /*
    handler: send()
        sends the tap to the server when the send button is clicked
    */
    send: function (el, e) {
        e.stop();
        if (this.tapbox.value.isEmpty()) {
            return this.tapbox.focus();
        }

        var priv = _controls.selector.getInside(), 
            anon = _controls.anon.getAnon(),
            data = {
                msg: this.tapbox.value,
                type: this.sendType,
                id: this.sendTo,
                'private': priv,
                anon: anon
            };

        if (this.tapbox.getData('mediatype') && this.tapbox.getData('mediatype').length) {
            var media = {
                    'mediatype': this.tapbox.getData('mediatype'),
                    'link': this.tapbox.getData('link'),
                    'code': this.tapbox.getData('embed'),
                    'title': this.tapbox.getData('title'),
                    'description': this.tapbox.getData('description'),
                    'thumbnail_url': this.tapbox.getData('thumbnail')
                };

            if (this.tapbox.getData('fullimage')) {
                media.fullimage_url = this.tapbox.getData('fullimage');
            }
            data.media = media;
        }

        if (_vars.feed.first_tap) {
            this.publish('modal.show.first-tap', [data]);
        } else {
            new Request({
                url: '/AJAX/taps/new',
                data: data,
                onSuccess: this.parseSent.bind(this)
            }).send();
        }
    },

    /*
    method: parseSent()
        injects the recently sent tap to the top of the tapstream
    */
    parseSent: function (response) {
        var resp = JSON.decode(response);
        if (resp.success) {
            // reset tapbox
            this.tapbox.value = '';
            this.tapbox.setData('mediatype', '');
            this.tapbox.setData('link', '');
            this.tapbox.setData('code', '');
            this.tapbox.setData('title', '');
            this.tapbox.setData('description', '');
            this.tapbox.setData('thumbnail_url', '');
            this.tapbox.getParent('form#reply').getElement('div.media-preview').addClass("hidden");
        }
    }

});

/*
 * module: _resizer
 *
 * Makes responses area resizeable
 */
var _resizer = _tap.register({
    init: function () {
        this.makeResizeable();
        this.subscribe({
            'feed.updated': this.makeResizeable.bind(this)
        });
    },

    makeResizeable: function () {
        this.resizers = $$('div.resizer');
        this.resizers.each(function (div) {
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
                        'max-height': h
                    });
                },
                onComplete: function (el) {
                    el.scrollTo(0, el.getScrollSize().y);
                }
            });
        });
    }
});

var _warning = _tap.register({
    init: function () {
        this.warning = $$('div.warning')[0];
        if (!this.warning) {
            return;
        }

        this.txt = this.warning.getElement('span');

        this.warning.getElement('a.close').addEvent('click', function (e) {
            this.hide();
        }.bind(this));
    },

    show: function (text, action) {
        this.txt.innerHTML = text;
        this.warning.removeClass('hidden');
        this.warning.addEvent('click', action);
    },

    hide: function () {
        this.warning.addClass('hidden');
    }
});

_controls.tabs = _tap.register({
    init: function () {
        this.controls = $('controls');
        if (!this.controls) {
            return;
        }

        this.tabs = this.controls.getElements('a.tab');

        this.subscribe('feed.changed', function (type, id) {
            if (['feed', 'aggr_groups', 'group', 'friend', 'private'].contains(type)) {
                this.show();
            } else {
                this.hide();
            }
            this.tabs.removeClass('active');

            if (_vars.feed.inside) {
                var tab = this.controls.getElement('a.tab[data-inside="' + _vars.feed.inside + '"]');
                if (tab) {
                    tab.addClass('active');
                }
            }
            if (_vars.feed.type) {
                var el = this.controls.getElement('a.tab[data-type="' + _vars.feed.type + '"]');
                if (el) {
                    el.addClass('active');
                }
            }
        }.bind(this));

        this.tabs.addEvent('click', this.toggle.toHandler(this));
    },

    show: function () {
        this.controls.removeClass('hidden');
    },

    hide: function () {
        this.controls.addClass('hidden');
    },

    toggle: function (el, e) {
        this.tabs.removeClass('active');
        el.addClass('active');
        _vars.feed.type = el.getData('type');
        this.publish('feed.change', [_vars.feed.type, null, null, null, 0, 0, 0]);
    }
});

_controls.filters = _tap.register({
    init: function () {
        var controls = this.controls = $('filters'),
            collaps = $('collapser');

        if (!collaps) {
            return;
        }

        var filters = this.filters = controls.getElements('.filter');

        collaps.addEvent('click', function (e) {
            e.stop();
            controls.toggleClass('collapsed');
        });

        filters.addEvent('click', this.toggle.toHandler(this));

        this.subscribe('feed.changed', function () {
            filters.removeClass('active');
            var inside = _vars.feed.inside,
                anon = _vars.feed.anon,
                type = 'all'; 

            if (inside) {
                type = inside == 1 ? 'inner' : 'outer'; 
            } else if (anon) {
                type = 'anon';
            }

            this.controls.getElements('.filter[data-type="' + type + '"]').addClass('active');
        }.bind(this));
    },

    toggle: function (el, e) {
        e.stop();
        var type = el.getData('type'),
            inside = 0,
            anon = 0;

        switch (type) {
        case 'inner':
            inside = 1;
            break;

        case 'outer':
            inside = 2;
            break;

        case 'anon':
            anon = 1;
            break;
        }
        _vars.feed.anon = anon;
        _vars.feed.inside = inside;

        this.filters.removeClass('active');
        el.addClass('active');

        this.publish('feed.change', [null, null, null, null, 0, inside, anon]);
    }
});

_controls.anon = _tap.register({
    init: function () {
        var anon = this.anon = $$('a.anonym')[0];
        if (!anon) {
            return;
        }
    
        this.img = anon.getSiblings('img.avatar');

        anon.addEvent('click', this.toggle.toHandler(this));
    },

    toggle: function (el, e) {
        e.stop();
        this.img.toggleClass('avatar').toggleClass('anonym');
        this.anon.toggleClass('anonym').toggleClass('avatar');
        //_vars.feed.anon = !!_vars.feed.anon ? 0 : 1;
    },

    getAnon: function () {
        return this.anon.hasClass('avatar') ? 1 : 0;
    }
});

_controls.selector = _tap.register({
    init: function () {
        var selector = this.selector = $('feed-selector');
        if (!selector) {
            return;
        }

        var list = this.list = selector.getElement('ul.dropdown');
        list.getElements('a').addEvent('click', this.toggle.toHandler(this));
    },

    toggle: function (el, e) {
        e.stop();
        this.list.getElements('li').removeClass('selected');
        el.getParent('li').addClass('selected');
        this.selector.getElement('span.feed-type').innerHTML = el.getData('name');
    },

    getInside: function () {
        return this.list.getElement('li.selected').getData('inside');
    }
});

_controls.tap_actions = _tap.register({
    init: function () {
        this.setup();
        this.subscribe('feed.changed', this.setup.bind(this));
    },

    setup: function () {
        $$('button.message-remove').addEvent('click', this.remove.toHandler(this));
    },

    remove: function (el, e) {
        e.stop();
        new Request.JSON({
            url: '/AJAX/taps/delete'
        }).post({id: el.getData('id')});
    }
});

/*
module: _live.stream
    controls the automatic tap streaming

require: _vars
*/
_live.stream = _tap.register({

    init: function () {
        this.convos = [];
        this.groups = [];
        if (_vars.comet) {
            if (_vars.comet.groups) {
                this.groups = _vars.comet.groups;
            }
        }

        this.subscribe({
            'push.connected; feed.updated': this.refreshStream.bind(this)
        });
    },

    /*
    method: refreshStream()
        parses the page and gets user, group and tap ids for the push server
    */
    refreshStream: function () {
        $$('div#feed > div.feed-item[data-id]').each(function (tap) {
            this.convos.push(tap.getData('id'));
        }.bind(this));

        if (_vars.feed.type == 'conversation') {
            this.convos.push(_vars.feed.id.toInt());
        }

        this.convos = this.convos.unique();
        this.update();
    },

    /*
    method: update()
        sends changes to the push server
    */
    update: function () {
        if (!(this.convos.length || this.groups.length) || _vars.guest) {
            return;
        }

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

    init: function () {
        this.pushed = [];
        this.pause = $('pause');
        this.stream = true;
        this.subscribe({
            'push.data.tap.new': this.process.bind(this),
            'push.data.tap.delete': this.deleteTap.bind(this),
            'feed.changed': this.clearPushed.bind(this)
        });

        if (this.pause) {
            this.pause.addEvent('click', function (e) {
                e.stop();
                this.stream = !this.stream;
                this.pause.toggleClass('active');
            }.bind(this));
        }

        window.addEvent('scroll', function () {
            var newTaps = $$('div.feed-item.new');
            if (!newTaps.length) {
                return;
            }

            var curScroll = window.getScroll();

            newTaps.each(function (tap) {
                if (tap.get('viewed') || tap.getPosition().y < curScroll.y) {
                    return;
                }

                tap.set('viewed', true);
                (function () {
                    var color = tap.getStyle('background-color'),
                        fx = new Fx.Tween(tap);

                    fx.chain(function () {
                        tap.removeClass('new');
                    });
                    fx.start('background-color', color, 'transparent');
                }).delay(1500);
            });
        });
    },

    deleteTap: function (data) {
        var cid = data.id,
            tap = $('global-' + cid);

        if (!tap) {
            return;
        }
        
        tap.addClass('deleted').addClass('hidden');

        this.publish('tap.deleted', [cid]);
    },

    process: function (data) {
        if (_vars.feed.type == 'conversation' || 
            (data['private'] != _vars.feed.inside && data.sender_id != _vars.user.id)) {
            return;
        }

        this.pushed.combine([data]);

        if (this.stream) {
            this.showPushed();
        } else {
            _warning.show('There  is ' + this.pushed.length + ' new taps.\n Click on warning to load them.', 
                function () {
                    this.pause.fireEvent('click');
                    this.showPushed();
                }.bind(this));
        }
    },

    showPushed: function () {
        _stream.parseFeed(this.pushed, true, true);
        this.clearPushed();
        window.fireEvent('scroll');
    },

    clearPushed: function (type) {
        this.pushed = [];
        _warning.hide();
    }
});

/*
module: _filter
    Controls the filter/search bar for the tapstream
*/
var _filter = _tap.register({

    init: function () {
        var box = this.box = $('search');
        if (!this.box) {
            return;
        }

        box.over = new OverText(box, {
            positionOptions: {
                offset: {x: 10, y: 8},
                relativeTo: box
            }
        }).show();

        box.getParents('form').addEvent('submit',
            function (e) {
                e.stop();
                var keyword = box.value;
                if (!keyword.isEmpty() && _vars.feed.type != 'conversation') {
                    this.search(keyword);
                } else if (_vars.feed.type == 'conversation') {
                    this.convoFilter(keyword);
                }
            }.bind(this));

        this.subscribe('feed.updated', function () {
            box.value = '';
        }.bind(this));

        if (_vars.feed.type == 'conversation') {
            box.addEvent('keyup', function () {
                this.convoFilter(box.value);
            }.bind(this));
        }
    },
    
    /*
    method: search()
        main control logic for searching
        
        args:
        1. keyword (string, opt) the keyword to use for searching; if null, the search is cleared
    */
    search: function (keyword) {
        this.publish('feed.search', [keyword]);
        return this;
    },

    convoFilter: function (keyword) {
        var reg = new RegExp('(' + keyword.escapeRegExp().replace(/\s+/, '.*?') + ')', 'i');

        $$('div.reply-item').each(function (reply) {
            reply.removeClass('hidden');
            var el = reply.getElement('span.reply-text'),
                text = el.innerHTML;

            text = text.replace(/<span class="highlight">(.*?)<\/span>/igm, '$1');
            if (text.test(reg)) {
                text = text.replace(reg, '<span class="highlight">$1<\/span>');
            } else {
                reply.addClass('hidden');
            }

            el.innerHTML = text;
        });

        window.scrollTo(0, window.getScrollSize().y);
    }
});

