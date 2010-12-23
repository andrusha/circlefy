/*global _tap, _vars, $$, Request, Elements, _template*/

/*
 * script: sidebar.js
 * Everything related sidebar.
 *
 * require: _template, _live
*/

/*
mixin: list
	A mixin for sidelist operations
*/

_tap.mixin({

	name: 'lists',

	setListVars: function () {
        var sidebar = this.sidebar = $('sidebar');
        this.menu = sidebar.getElements('ul#navigation>li');
        this.pmButton = $('sendPMButton');
	}

});

/*
local: _list
	Controls the side navigation list

require: _template
*/
var _list = _tap.register({

	mixins: 'lists',

	init: function () {
		this.setListVars();
        this.reply = $('reply');
		this.menu.addEvent('click:relay(a)', this.changeFeed.toHandler(this));
		this.sidebar.addEvent('click:relay(#sendPMButton)', function () {
            this.publish('feed.change', ['private']);
        }.bind(this));
        this.subscribe('feed.changed', function (type) {
            if (!this.reply) {
                return;
            }

            if (!['group', 'private'].contains(type)) {
                this.reply.addClass('hidden');
            } else {
                this.reply.removeClass('hidden');
            }
        }.bind(this));
    },
	
	changeFeed: function (el, e) {
        e.stop();
		var parent = el.getParent('li'),
            type = parent.getData('type'),
            id = _vars.user.id,
            data = {'feed': el.text },
            inside = parent.getData('inside') || 0;

        if (_vars.guest && type != 'public') {
            return;
        }

        parent.getSiblings('li').removeClass('active');
        parent.addClass('active');

		this.publish('feed.change', [type, id, data, null, null, inside, 0]);
	}
});

var _view_all = _tap.register({
    init: function () {
        $$('a.view-all').addEvent('click', this.getMore.toHandler(this));
    },

    getMore: function (el, e) {
        e.stop();
        var type = el.getData('type'),
            id = el.getData('id').toInt(),
            list = null;

        if (type == 'groups') {
            var parent = el.getParent('div#left');
            if (parent) {
                list = parent.getElement('div.user-circles');
            } else {
                parent = el.getParent('div.box');
                if (parent) {
                    list = parent.getElement('div.circles');
                }
            }

            this.getGroups(id, list);
        } else if (['following', 'followers', 'members'].contains(type)) {
            list = el.getParent('div.box').getElement('div.followers');
            this.getUsers(type, id, list);
        } else if (type == 'involved') {
            list = el.getParent('div.box');
            list.getElements('.follower-thumb').removeClass('hidden');
        }

        el.addClass('hidden');
    },

    getGroups: function (id, list) {
        new Request.JSON({
            url: '/AJAX/group/get',
            onSuccess: function (response) {
                if (!response.success) {
                    return;
                }

                var items = Elements.from(_template.parse('circles', response.data));
                list.empty();
                items.inject(list);
                
                this.publish('groups.get', [items]);
            }.bind(this)
        }).post({type: 'byUser', id: id});
    },

    getUsers: function (type, id, list) {
        new Request.JSON({
            url: '/AJAX/user/get',
            onSuccess: function (response) {
                if (!response.success) {
                    return;
                }

                var items = Elements.from(_template.parse('followers', response.data));
                items.inject(list.empty());

                this.publish('users.get', [items]);
            }.bind(this)
        }).post({type: type, id: id});
    }
});

var _counters = _tap.register({
    init: function () {
        this.homepage = $('homepage-stats');

        this.subscribe({
            'push.data.tap.new': this.newTap.bind(this),
            'push.data.member.new': this.newMember.bind(this)
        });
    },

    newTap: function (data) {
        if (data.sender_id == _vars.user.id && this.homepage) {
            var cnt = this.homepage.getElement('span.stats.messages > span.value');
            cnt.innerHTML = cnt.innerHTML.toInt() + 1;
        }
    },

    newMember: function (data) {
        if (data.user_id == _vars.user.id && this.homepage) {
            var cnt = this.homepage.getElement('span.stats.circles > span.value');
            cnt.innerHTML = cnt.innerHTML.toInt() + 1;
        }
    }
});
