/*global _tap, $$, document, Request, _template, Elements, _vars, _body, Chain, Fx, _live*/

var _notification = _tap.register({
    init: function () {
        var parent = this.parent = $('notifications');
        if (!parent) {
            return;
        }


        this.page = 0;
        this.perPage = 5;
        this.online = true;
        this.queue = _vars.events || []; //notifications queue
        this.queue.each(function (elem) {
            elem.persistant = true;
        });

        parent.addEvents({
            'click:relay(a.toggle)': this.toggle.toHandler(this),
            'click:relay(button.close)': this.close.toHandler(this),
            'click:relay(button.paginate)': this.paginate.toHandler(this),
            'click:relay(div.event)': function () {
                var link = this.getElement('h3 > a');
                document.location = link.href;
            }
        });

        this.subscribe({
            'user.active': function () {
                    this.online = true;
                }.bind(this),
            'user.inactive': function () {
                    this.online = false;
                }.bind(this),

            'push.data.event.delete': this['delete'].bind(this),
            'push.data.event.delete.all': function () {
                parent.addClass('hidden');
            },

            'push.data.tap.new': function (data) {
                this.event(0, data);
            }.bind(this),
            'push.data.response.new': function (data) {
                this.event(1, data);
            }.bind(this),
            'push.data.user.follow': function (data) {
                this.event(2, data);
            }.bind(this),
            'push.data.member.new': function (data) {
                //this.event(3, data);
            }.bind(this)
        });

    },

    event: function (type, data) {
        var event = null;

        switch (type) {
        case 0:
            event = {
                type:        type,
                id:          data.id,
                sender_id:   data.sender_id,
                group_id:    data.group_id,
                reciever_id: data.reciever_id,
                'private':   data['private'],
                anonymous:   data.anonymous,
                text:        data.text,
                
                //here comes objects
                sender:      Object.clone(data.sender),
                group:       Object.clone(data.group)
            };
            break;
        case 1:
            event = {
                type:       type,
                id:         data.message_id,
                sender_id:  data.user_id,
                group_id:   data.group_id,
                text:       data.text,
                new_replies: 1,

                sender:     Object.clone(data.user)
            };
            break;
        case 2:
            event = {
                type:       type,
                user_id:    data.user.id,

                sender:     Object.clone(data.user)
            };
            break;
        case 3:
            event = {
                type:       type,
                user_id:    data.user_id,
                group_id:   data.group_id
            };
            break;
        }
        event.persistent = !this.online;

        if (_vars.user.id == event.sender_id) {
            return;
        }

        this.add_event(event);
        this.show(this.queue.slice(0, this.perPage), true);

        this.buttonsUpdate();
    },

    add_event: function (event) {
        var found = false;

        //if reply event occured, increase replies count
        this.queue.each(function (elem) {
                if (elem.type == event.type && elem.id == event.id && elem.sender_id == event.sender_id) {
                    found = true;
                    if (elem.type == 1) {
                        elem.new_replies = elem.new_replies ? elem.new_replies + event.new_replies : event.new_replies;
                    }
                }
            });

        if (!found) {
            this.queue.unshift(event);
        }
    },

    toggle: function (el, e) {
        e.stop();
        el.getSiblings('div.list').toggleClass('hidden');
        el.toggleClass('toggled');
    },

    forceShow: function () {
        $$('#notifications > .list').removeClass('hidden');
        $$('#notifications > .toggle').removeClass('toggled');
        $$('#notifications').removeClass('hidden');
    },

    close: function (el, e) {
        e.stop();
        new Request.JSON({
            url: '/AJAX/taps/events',
            onSuccess: function (response) {
                if (response.success) {
                    this.parent.addClass('hidden');
                }
            }.bind(this)
        }).post({action: 'all_read'});
    },

    paginate: function (el, e) {
        e.stop();
        var type = el.getData('type');

        if (type == 'next') {
            this.page++;
        } else if (this.page > 0) {
            this.page--;
        }

        if (this.queue.length <= this.page * this.perPage) {
            this.getPage(this.page, function (events) {
                this.queue.append(events);
                this.show(events);
            }.bind(this));
        } else {
            this.show(this.queue.slice(this.page * this.perPage, (this.page + 1) * this.perPage));
        }

        this.buttonsUpdate();
    },

    buttonsUpdate: function () {
        if (this.queue.length <= (this.page + 1) * this.perPage) {
            this.parent.getElements('button[data-type="next"]').setProperty('disabled', true);
        } else {
            this.parent.getElements('button[data-type="next"]').removeProperty('disabled');
        }

        if (!this.page) {
            this.parent.getElements('button[data-type="prev"]').setProperty('disabled', true);
        } else {
            this.parent.getElements('button[data-type="prev"]').removeProperty('disabled');
        }
    },

    show: function (events, animate) {
        var items = Elements.from(_template.parse('notifications', events)),
            list  = this.parent.getElement('div.events'),
            first = items && items.length ? items[0] : null;


        if (animate && first) {
            first.setStyles({
                'opacity': 0,
                'margin-bottom': '-80px'
            });
        }
        
        if (first && events && events.length && !events[0].persistent) {
            first.getElement('.event-background').setStyle('background-color', 'darkgreen');
            (function () {
                first.getElement('.event-background').setStyle('background-color', 'black');
            }.delay(8000));
        }

        list.empty();
        items.inject(list);

        if (animate && first) {
            first.set('morph', {
                unit: 'px',
                link: 'cancel',
                onStart: Chain.prototype.clearChain,
                transition: Fx.Transitions.Back.easeOut
            }).morph({opacity: 1, 'margin-bottom': '6px'});
        }

        this.forceShow();
    },

    getPage: function (page, callback) {
        new Request.JSON({
            url: '/AJAX/taps/events',
            onSuccess: function (response) {
                if (!response.success) {
                    return;
                }
                
                callback(response.events);
            }.bind(this)
        }).post({action: 'fetch', page: page});
    },

    'delete': function (data) {
        if (data.user_id != _vars.user.id) {
            return;
        }

        var id = '#event-' + (data.type == 2 ? 2 : 1) + '-' + data.event_id;
        $$(id).addClass('hidden');

        if (data.type != 2) {
            $$('#global-' + data.event_id).removeClass('new');
        }
    }
});

var _activity = _tap.register({
    init: function () {
        this.status = false;
        this.timeout = null;
        
        _body.addEvents({
            'mousemove':  this.online.toHandler(this),
            'mouseclick': this.online.toHandler(this),
            'keyup':      this.online.toHandler(this)  
        });

        this.setupTimeout();
    },

    online: function () {
        this.status = true;

        this.setupTimeout();

        this.publish('user.active');
    },

    setupTimeout: function () {
        clearTimeout(this.timeout);
        this.timeout = this.offline.delay(15000, this);
    },

    offline: function () {
        this.status = false;

        this.publish('user.inactive');
    }
});

