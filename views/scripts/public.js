/*
 script: home.js
 Controls the main interface.
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
        'list.member': 'template-list-member'
    },

    parse: function(type, data) {
        var template = this.prepared[type];
        if (!template) {
            template = this.map[type];
            if (!template) return '';
            template = this.prepared[type] = $(template).innerHTML.cleanup();
        }
        return this.templater.parse(template, data);
    }

};

var _dater = _tap.register({

    init: function() {
        var self = this;
        this.changeDates();
        this.changeDates.periodical(60000, this);
        this.subscribe({
            'dates.update; stream.updated; responses.updated': this.changeDates.bind(this)
        });
    },

    changeDates: function() {
        var items = _body.getElements("[data-timestamp]");
        items.each(function(el) {
            var timestamp = el.getData('timestamp') * 1;
            el.set('text', _dater.timestampToStr(timestamp));
        });
    },

    timestampToStr: function(timestamp) {
        var now = new Date().getTime(),
                orig = new Date(timestamp * 1000),
                diff = ((now - orig) / 1000),
                day_diff = Math.floor(diff / 86400);

        if (isNaN(timestamp) || $type(diff) == false || day_diff < 0 || day_diff >= 31)
            return orig.format('jS M Y');

        return day_diff == 0 && (
                diff < 120 && "Just Now" ||
                        diff < 3600 && Math.floor(diff / 60) + "min ago" ||
                        diff < 7200 && "An hour ago" ||
                        diff < 86400 && Math.floor(diff / 3600) + " hours ago") ||
                day_diff == 1 && "Yesterday" ||
                day_diff < 7 && day_diff + " days ago" ||
                day_diff < 31 && Math.ceil(day_diff / 7) + " weeks ago";
    }

});

var _lists = _tap.register({

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
        this.myTips = new Tips('.aggr-favicons', {fixed:true });

        this.myTips.addEvent('show', function(tip, el) {
            tip.fade('in');
        });

        this.myTips2 = new Tips('.panel-item-public-admin', {fixed:true });

        this.myTips2.addEvent('show', function(tip, el) {
            tip.fade('in');
        });
    }
});

_tap.mixin({

    name: 'streaming',

    setStreamVars: function() {
        var main = this.main = $('tapstream');
        this.stream = $('taps');
        this.header = main.getElement('h2.header-title');
        this.title = main.getElement('span.stream-name');
        this.feedType = this.header.getElement('span.title');
        this.streamType = 'all';
    },

    showLoader: function() {
        this.header.addClass('loading');
        return this;
    },

    hideLoader: function() {
        this.header.removeClass('loading');
        return this;
    },

    setTitle: function(options) {
        var title = options.title,
                url = options.url,
                type = options.type,
                desc = options.desc,
                admin = options.admin;
        this.feedType.set('text', type);
        title = (!url) ? title : '{t} <a href="{u}">view profile</a>'.substitute({t: title, u: url});
        if (!!admin) title = ['&#10070; ', title, '<a href="{u}">manage group</a>'.substitute({u: admin})].join('');
        this.title.set('html', title);
        if (desc) {
            this.topic.set('text', desc);
            this.main.addClass('description');
        } else {
            this.main.removeClass('description');
        }
        return this;
    },

    parseFeed: function(resp, keep) {
        var stream = this.stream;
        if (!keep) stream.empty();
        if (resp.results && resp.data) {
            var items = Elements.from(_template.parse('taps', resp.data));
            items.each(function(item) {
                var id = item.get('id'),
                        el = $(id);
                if (el) el.destroy();
            });
            this.publish('stream.new', [$$(items.reverse())]);
            stream.removeClass('empty');
        } else {
            this.publish('stream.empty', $('no-taps-yet').clone());
            stream.addClass('empty');
        }
    },

    addTaps: function(items) {
        items.setStyles({opacity:0});
        items.inject(this.stream, 'top');
        items.fade(1);
        this.publish('stream.updated', this.streamType);
        return this;
    }

});

var _stream = _tap.register({

    mixins: 'streaming',

    init: function() {
        this.setStreamVars();
        this.subscribe({
            'list.item': this.setStream.bind(this),
            'stream.new; stream.empty; tapbox.sent': this.addTaps.bind(this),
            'feed.changed': (function(type) {
                this.streamType = type;
            }).bind(this),
            'taps.pushed': this.parsePushed.bind(this),
            'taps.notify.click': this.getPushed.bind(this),
            'filter.search': this.changeFeed.bind(this)
        });
    },

    setStream: function(type, id, info) {
        if (type == 'groups') return this.changeFeed(id, info);
        return this;
    },

    changeFeed: function(id, feed, keyword) {
        var self = this,
                data = {type: null};
        switch (id) {
            case 'all': data.type = 11; break;
            case 'public': data.type = 100; break;
            default: data.type = 1; data.id = id;
        }
        if (keyword) data.search = keyword;
        new Request({
            url: '/AJAX/filter_creator.php',
            data: data,
            onRequest: this.showLoader.bind(this),
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                self.setTitle({
                    title: keyword ? ['"', keyword, '" in ', feed.name].join('') : feed.name,
                    url: feed.symbol ? '/group/' + feed.symbol : null,
                    type: keyword ? 'search' : 'feed',
                    desc: feed.topic,
                    admin: feed.admin ? '/group_edit?group=' + feed.symbol : null
                });
                if (response) self.parseFeed(response);
                self.hideLoader();
                self.publish('feed.changed', id.test(/^(public|all)$/) ? id : 'group_' + id);
            }
        }).send();
    },

    parsePushed: function(type, items, stream) {
        var self = this;
        if ((type == 'groups' && this.streamType == 'all') || this.streamType == type) {
            items = items.filter(function(id) {
                var item = self.stream.getElement('li[data-id="' + id + '"]');
                return !item;
            });
            if (items.length > 0) {
                if (!stream) this.publish('stream.reload', [items]);
                else this.getPushed(items);
            }
        }
    },

    getPushed: function(items) {
        var self = this,
                data = {id_list: items.join(',')};
        new Request({
            url: '/AJAX/loader.php',
            data: data,
            onRequest: this.showLoader.bind(this),
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (response) self.parseFeed(response, true);
                self.hideLoader();
            }
        }).send();
    }

});

var _filter = _tap.register({

    init: function() {
        this.box = $('filter');
        if (!this.box) return;
        this.group = _vars.filter.gid;
        this.info = _vars.filter.info;
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

    change: function(type, id, info) {
        var box = this.box;
        if (type == 'groups') {
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

    setTitle: function(title) {
        this.title.set('text', title.toLowerCase());
        return this;
    },

    search: function(keyword) {
        if (!!keyword) {
            this.active = true;
            this.clearer.addClass('active');
        } else {
            this.active = false;
            this.clearer.removeClass('active');
        }
        this.publish('filter.search', [this.group, this.info, keyword || null]);
        return this;
    },

    checkKeys: function(e) {
        var keyword = $(e.target).get('value');
        if (this.group && e.key == 'enter') this.search(keyword);
    },

    clearSearch: function(e) {
        e.preventDefault();
        if (!this.active) return this;
        this.filter.set('value', '');
        this.search();
        return this;
    }

});

var _members = _tap.register({

    init: function() {
        this.toggle = $('list-action');
        this.toggle2 = $('total-member-count');
        if (!this.toggle) return;
        this.list = $('member-panel');
        this.topList = this.list.getElements('li.panel-item');
        this.toggle.addEvent('click', this.toggleList.toHandler(this));
        this.toggle2.addEvent('click', this.toggleList.toHandler(this));
    },

    toggleList: function(el, e) {
        $('member-panel').empty();
        var el = $('list-action');
        if (el.hasClass('all')) this.showAll();
        else this.showTop();
    },


    /* showALl
     This gets the list of members
     depending on the type: 0 = list all , 1 = serach
     */
    showAll: function() {

        var self = this,
                list = this.allList;
        if (list) return this.changeList('all', list);

        var online_state = $('online-checked').checked;

        new Request({
            url: "/AJAX/group_userlist.php",
            data: {gid: _vars.filter.gid,type:0,online_only:online_state},
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (response.grouplist && $type(response.grouplist) == 'array') {
                    list = Elements.from(_template.parse('list.member', response.grouplist));
                } else {
                    list = [];
                }
                self.changeList('all', list);
            }
        }).send();
    },

    showTop: function() {
        this.changeList('top', this.topList);
    },

    changeList: function(type, list) {
        this.toggle.set({
            'text': type == 'all' ? 'top members' : 'all members'
        }).removeClass(type).addClass(type == 'all' ? 'top' : 'all');
        this.toggle.getNext('span').set({
            'text': type == 'all' ? 'all members' : 'top members'
        });
        this.list.getElements('li.panel-item').dispose();
        if (list.length > 0) {
            list.inject(this.list);
        } else {
            new Element('li', {
                'class': 'notify',
                'text': type == 'all' ? 'no members' : 'no top members'
            }).inject(this.list);
        }
    }

});

var x;
var _convos = _tap.register({

    init: function() {
        this.subscribe({
            'responses.sent': this.addConvo.bind(this),
            'responses.track': this.addTrack.bind(this),
            'responses.untrack': this.removeTrack.bind(this)
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

    addConvo: function(cid, uid, data) {
        var self = this,
                parent = $('tid_' + cid);
        if (!parent) return;
        var users = parent.getElements('div.responses a.user').map(function(item) {
            return item.get('text').replace(/:/, '');
        });
        if (users.length > 0 && users.contains(_vars.uname)) return;
        self.publish('responses.track', [cid,false]);
    },

    addTrack: function(cid, addConvo) {
        new Request({
            url: '/AJAX/add_active.php',
            data: {cid: cid},
            onSuccess: function() {
                if (addConvo)
                    self.publish('convos.new', [cid, uid, data]);
            }
        }).send();
    },

    removeTrack: function(cid, addConvo) {
        new Request({
            url: '/AJAX/remove_active.php',
            data: {cid: cid},
            onSuccess: function() {
                if (addConvo)
                    self.publish('convos.new', [cid, uid, data]);
            }
        }).send();
    }
});

var _responses = _tap.register({

    init: function() {
        if (!_vars.uid && !_vars.uname) {
            _body.addEvent('click:relay(a.tap-resp-count)', function() {
                window.location = '/';
            });
            return;
        }
        _body.addEvents({
            'click:relay(a.tap-resp-count)': this.setupResponse.toHandler(this)
        });
        this.subscribe({
            'responses.new': this.addResponses.bind(this),
            'convos.loaded': this.setupResponse.bind(this)
        });
        this.openBoxes();
    },

    openBoxes: function() {
        var box = _body.getElement('div.perma');
        if (!box) return;
        var link = box.getParent('li').getElement('a.tap-resp-count');
        _body.fireEvent('click', {stop: $empty, preventDefault: $empty, target: link});
    },

    setupResponse: function(el, e) {
        var parent = el.getParent('li'),
                responses = parent.getElement('div.responses'),
                box = responses.getElement('ul.chat'),
                chat = responses.getElement('input.chatbox'),
                counter = responses.getElement('.counter'),
                overlay = responses.getElement('div.overlay');
        if (e) e.preventDefault();
        responses.toggleClass('open');
        if (!box.retrieve('loaded')) this.loadResponse(parent.getData('id'), box);
        if (!chat.retrieve('extended')) this.extendResponse(chat, counter, overlay);
        box.scrollTo(0, box.getScrollSize().y);
        return this;
    },

    loadResponse: function(id, box) {
        var self = this;
        new Request({
            url: '/AJAX/load_responses.php',
            data: {cid: id},
            onSuccess: function() {
                var data = JSON.decode(this.response.text);
                if (!data.responses) return;
                self.addResponses(box.empty(), data.responses);
                self.publish('responses.loaded', id);
                box.store('loaded', true);
            }
        }).send();
    },

    addResponses: function(box, data) {
        var items = Elements.from(_template.parse('responses', data));
        items.setStyles({opacity:0});
        items.fade(1);
        items.inject(box);
        this.updateStatus(box);
        box.scrollTo(0, box.getScrollSize().y);
        this.publish('responses.updated');
        return this;
    },

    updateStatus: function(box) {
        var items = box.getElements('li'),
                last = items.getLast(),
                parent = box.getParent('li'),
                lastresp = parent.getElement('span.last-resp'),
            //username = lastresp.getElement('strong'),
            //chattext = lastresp.getElement('span'),
                count = parent.getElement('a.tap-resp-count span.show-resp-count');
        //username.set('text', last.getElement('a').get('text'));
        //chattext.set('text', last.getChildren('span').get('text'));
        count.set('text', items.length);
    },

    extendResponse: function(chatbox, counter, overlay) {
        var self = this,
                limit = 240,
                allowed = {'enter': 1,'up': 1,'down': 1,'left': 1, 'right': 1,'backspace': 1,'delete': 1};
        chatbox.addEvents({
            'keydown': function(e) {
                if (this.get('value').length >= limit && !allowed[e.key]) return e.stop();
            },
            'keypress': function() {
                self.publish('responses.typing', chatbox);
            },
            'keyup': function(e) {
                var length = this.get('value').length,
                        count = limit - length;
                if (e.key == 'enter' && !this.isEmpty()) return self.sendResponse(chatbox, counter);
                counter.set('text', count);
            }
        });
        chatbox.store('overlay', new TextOverlay(chatbox, overlay));
        chatbox.store('extended', true);
    },

    sendResponse: function(chatbox, counter) {
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
            onRequest: function() {
                self.clearResponse(chatbox, counter);
            },
            onSuccess: function() {
                chatbox.store('first', true);
                self.publish('responses.sent', [cid, uid, data]);
            }
        }).send();
    },

    clearResponse: function(chatbox, counter) {
        chatbox.set('value', '');
        counter.set('text', '240');
        chatbox.focus();
        return this;
    }

});

var _tapbox = _tap.register({

    sendTo: _vars.sendTo,

    init: function() {
        this.tapbox = $('tapbox');
        if ((!_vars.uid && !_vars.uname) || !this.tapbox) return;
        this.overlayMsg = $('tapto');
        this.msg = $('taptext');
        this.counter = this.tapbox.getElement('span.counter');
        this.overlay = new TextOverlay('taptext', 'tapto');
        this.setupTapBox();
        this.tapbox.addEvent('submit', this.send.toHandler(this));
        this.subscribe({'list.item': this.handleTapBox.bind(this)});
    },

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

    clear: function() {
        this.msg.set('value', '').blur();
        this.counter.set('text', '240');
        this.overlay.show();
        return this;
    },

    handleTapBox: function(type, id, data) {
        if (type !== 'groups') return;
        this.changeOverlay(id, data.name);
        this.changeSendTo(data.name, data.symbol, id);
    },

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

    changeSendTo: function(name, symbol, id) {
        this.sendTo = [name, symbol, 0, id].join(':');
        return this;
    },

    send: function(el, e) {
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

    parseSent: function(response) {
        var resp = JSON.decode(response);
        if (resp.new_msg) {
            this.clear();
            var item = Elements.from(_template.parse('taps', resp.new_msg));
            this.publish('tapbox.sent', item);
        }
    }

});

var _infobox = _tap.register({

    init: function() {
        var box = this.box = $('infobox');
        var button = this.button = box.getElement('button');
        if (button && box.hasClass('pubgroup')) {
            button.addEvent('click', this.groupAction.toHandler(this));
        }
    },

    groupAction: function(el, e) {
        if (el.hasClass('login')) return window.location = '/';
        if (el.hasClass('join')) return this.joinGroup();
        if (el.hasClass('request')) return this.requestJoinGroup();
        if (el.hasClass('leave')) return this.leaveGroup();
        if (el.hasClass('track')) return this.track(true);
        if (el.hasClass('untrack')) return this.track(false);
    },

    requestJoinGroup: function() {
        var self = this,
                id = this.button.getData('id');
        new Request({
            url: '/AJAX/request_join_group.php',
            method: 'post',
            data: {gid: id},
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (!response.good) return;
                self.button.set({
                    'text': 'waiting',
                    'class': 'leave'
                });
                $$('.count-one').each(function(el) {
                    el.set('html', el.innerHTML.toInt() + 1).fade('hide').fade();
                });
                $$('.waiting-click')[0].fade('hide');
                $$('.waiting-click')[0].fade(1).fade.delay(2000, $$('.waiting-click')[0], 0);


            }
        }).send();
    },

    joinGroup: function() {
        var self = this,
                id = this.button.getData('id');
        new Request({
            url: '/AJAX/join_group.php',
            method: 'post',
            data: {gid: id},
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (!response.good) return;
                self.button.set({
                    'text': 'leave group',
                    'class': 'leave'
                });
                $$('.count-one').each(function(el) {
                    el.set('html', el.innerHTML.toInt() + 1).fade('hide').fade();
                });
                $$('.positive-click')[0].fade('hide');
                $$('.positive-click')[0].fade(1).fade.delay(2000, $$('.positive-click')[0], 0);


            }
        }).send();
    },

    leaveGroup: function() {
        var self = this,
                id = this.button.getData('id');
        new Request({
            url: '/AJAX/leave_group.php',
            data: {gid: id},
            onSuccess: function() {
                self.button.set({
                    'text': 'join group',
                    'class': 'join'
                });
                $$('.count-one').each(function(el) {
                    el.set('html', el.innerHTML.toInt() - 1).fade('hide').fade();
                });
                $$('.negative-click')[0].fade('hide');
                $$('.negative-click')[0].fade(1).fade.delay(2000, $$('.negative-click')[0], 0);
            }
        }).send();
    },

    track: function(type) {
        var self = this,
                id = this.button.getData('id');
        new Request({
            url: '/AJAX/track.php',
            data: {
                fid: id,
                state: (type) ? 1 : 0
            },
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (response.success) {
                    self.button.set({
                        'text': (type) ? 'unfollow' : 'follow',
                        'class': (type) ? 'untrack' : 'track'
                    });
                    $$('.count-one').each(function(el) {

                        if (type) {
                            $$('.count-one').each(function(el) {
                                el.set('html', el.innerHTML.toInt() + 1).fade('hide').fade();
                            });
                            $$('.positive-click')[0].fade('hide');
                            $$('.positive-click')[0].fade(1).fade.delay(2000, $$('.positive-click')[0], 0);
                        } else {
                            $$('.count-one').each(function(el) {
                                el.set('html', el.innerHTML.toInt() - 1).fade('hide').fade();
                            });
                            $$('.negative-click')[0].fade('hide');
                            $$('.negative-click')[0].fade(1).fade.delay(2000, $$('.negative-click')[0], 0);
                        }
                    });
                }
            }
        }).send();
    }

});

var _live = {};

_live.stream = _tap.register({

    init: function() {
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
            'list.item.added': function(type) {
                if (type == 'convos') self.refreshConvos();
            },
            'list.item.removed': this.refreshConvos.bind(this)
        });
    },

    refreshStream: function() {
        var stream = [], people = [];
        this.tapstream.getElements('li[data-id]').each(function(tap) {
            stream.push(tap.getData('id'));
            people.push(tap.getData('uid'));
        });
        this.stream = stream;
        this.people = people;
        this.update();
    },

    refreshConvos: function() {
        this.convos = this.convolist.getElements('li[data-id]').map(function(item) {
            return item.getData('id');
        });
        this.update();
    },

    update: function() {
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
            span.set('text', (span.get('text') * 1) + amount);
        }
        return this;
    }

});

_live.typing = _tap.register({

    init: function() {
        this.subscribe({
            'responses.typing': this.sendTyping.bind(this),
            'push.data.response.typing': this.showTyping.bind(this)
        });
    },

    sendTyping: function(chatbox) {
        var id = chatbox.getParent('li').getData('id');
        if (chatbox.retrieve('typing')) return;
        (function() {
            chatbox.store('typing', false);
        }).delay(1500);
        chatbox.store('typing', true);
        new Request({url: '/AJAX/typing.php', data: {cid: id, response: 1}}).send();
    },

    showTyping: function(tid, user) {
        var timeout, indicator, parent = $('tid_' + tid);
        if (!parent) return;
        indicator = parent.getElement('span.tap-resp');
        timeout = indicator.retrieve('timeout');
        if (timeout) $clear(timeout);
        indicator.addClass('typing');
        timeout = (function() {
            indicator.removeClass('typing');
        }).delay(2000);
        indicator.store('timeout', timeout);
    }

});

_live.responses = _tap.register({

    init: function() {
        this.subscribe({
            'push.data.response': this.setResponse.bind(this)
        });
    },

    setResponse: function(id, user, msg, pic) {
        var parent = $('tid_' + id);
        if (!parent) return;
        var box = parent.getElement('ul.chat');
        if (box) this.publish('responses.new', [box, [
            {
                uname: user,
                pic_small: pic,
                chat_text: msg,
                chat_time: new Date().getTime().toString().substring(0, 10)
            }
        ]]);
    }

});

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

_live.taps = _tap.register({

    init: function() {
        var self = this;
        this.pushed = {};
        this.notifier = $('newtaps');
        this.streamer = $('streamer');
        this.stream = !!(this.streamer);
        this.subscribe({
            'push.data.notification': this.process.bind(this),
            'feed.changed': this.hideNotifier.bind(this),
            'stream.reload': this.showNotifier.bind(this),
            'stream.updated': this.clearPushed.bind(this)
        });
        this.notifier.addEvent('click', function(e) {
            e.stop();
            var items = this.retrieve('items');
            if (!items) return;
            self.publish('taps.notify.click', [items]);
            self.hideNotifier();
        });
        if (this.streamer) this.streamer.addEvent('click', this.toggleNotifier.toHandler(this));
    },

    process: function(data) {
        var len, item, ids,
                pushed = this.pushed;
        data = data.map(function(item) {
            return item.link({
                type: String.type,
                _: Number.type,
                cid: Number.type,
                $: Boolean.type,
                perm: Number.type
            });
        });

        len = data.reverse().length;
        while (len--) {
            item = data[len];
            if (!item.type.test(/^group/)) continue;
            if (!pushed[item.type]) pushed[item.type] = [];
            ids = pushed[item.type];
            ids.include(item.cid);
        }
        for (var i in pushed) this.publish('taps.pushed', [i, pushed[i], this.stream]);
    },

    clearPushed: function(type) {
        if (!type) return;
        switch (type) {
            case 'all': this.pushed['groups'] = []; break;
            default: this.pushed[type] = [];
        }
    },

    showNotifier: function(items) {
        var notifier = this.notifier,
                length = items.length;
        notifier.set('text', [
            length.toString(), 'new', length == 1 ? 'tap.' : 'taps.', 'Click here to load them.'
        ].join(' '));
        notifier.store('items', items).addClass('notify');
    },

    hideNotifier: function() {
        var notifier = this.notifier;
        notifier.set('text', '').store('items', null).removeClass('notify');
    },

    toggleNotifier: function(el) {
        if (el.hasClass('paused')) {
            this.stream = true;
            el.removeClass('paused').set({
                'alt': 'pause live streaming',
                'title': 'pause live streaming'
            });
        } else {
            this.stream = false;
            el.addClass('paused').set({
                'alt': 'start live streaming',
                'title': 'start live streaming'
            });
        }
    }

});

// UNCOMMENT FOR PROD
// })();
