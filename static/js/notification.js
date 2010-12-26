/*global _tap, $$, document, Request, _template, Elements, _vars, _body*/

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
            }.bind(this)/*,
            'push.data.user.follow': function (data) {
                this.event(2, data);
            }.bind(this)*/
        });

    },

    event: function (type, data) {
        //add event only if user offline
        if (this.online) {
            return;
        }

        data.id = data.message_id || data.friend_id || data.id;
        data.type = type;
        data.user_id = _vars.user.id;
        data.new_replies = 1;
        data.sender = data.sender || {id: data.user_id || data.sender_id || null};
        data.sender_id = data.sender_id || data.user_id || null;
        data.group = data.group || {id: data.group_id || null};

        if (data.user_id == data.sender_id) {
            return;
        }

        this.add_event(data);
        this.show(this.queue.slice(0, this.perPage));
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
            this.parent.getElements('button[data-type="prev"]').removeProperty('disabled');
        } else if (this.page > 0) {
            this.page--;
            this.parent.getElements('button[data-type="next"]').removeProperty('disabled');
        }

        if (this.queue.length <= this.page * this.perPage) {
            this.getPage(this.page, function (events) {
                this.queue.append(events);
                this.show(events);
            }.bind(this));
        } else {
            this.show(this.queue.slice(this.page * this.perPage, (this.page + 1) * this.perPage));
        }

        if (this.queue.length < (this.page + 1) * this.perPage) {
            this.parent.getElements('button[data-type="next"]').setProperty('disabled', true);
        }

        if (!this.page) {
            this.parent.getElements('button[data-type="prev"]').setProperty('disabled', true);
        }
    },

    show: function (events) {
        var items = Elements.from(_template.parse('notifications', events)),
            list  = this.parent.getElement('div.events');

        list.empty();
        items.inject(list);
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
