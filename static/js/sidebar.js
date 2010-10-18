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
var x;
_tap.mixin({

	name: 'lists',

	setListVars: function() {
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

	init: function(){
		this.setListVars();
        this.reply = $('reply');
		this.menu.addEvent('click:relay(a)', this.changeFeed.toHandler(this));
		this.sidebar.addEvent('click:relay(#sendPMButton)', function () {
            this.publish('feed.change', ['private']);
        }.bind(this));
        this.subscribe('feed.changed', function (type) {
            if (!this.reply) return;
            if (!['group', 'private'].contains(type))
                this.reply.addClass('hidden');
            else
                this.reply.removeClass('hidden');
        }.bind(this));
    },
	
	changeFeed: function(el, e){
        e.stop();
		var type = el.getData('type'),
            id = _vars.user.id,
            data = {'feed': el.getElement('a').text },
            inside = el.getData('inside');

        if (_vars.guest && type != 'public')
            return;

        el.getSiblings('li').removeClass('active');
        el.addClass('active');

		this.publish('feed.change', [type, id, data, null, null, inside]);
	},
});

var _view_all = _tap.register({
    init: function () {
        $$('a.view-all').addEvent('click', this.getMore.toHandler(this));
    },

    getMore: function (el, e) {
        e.stop();
        var type = el.getData('type'),
            id = el.getData('id').toInt();

        if (type == 'groups') {
            var list = el.getParent('div#left').getElement('div.user-circles');
            el.addClass('hidden');
            this.getGroups(id, list);
        }
    },

    getGroups: function (id, list) {
        new Request({
            url: '/AJAX/group/get',
            data: {
                type: 'byUser',
                id: id
            },
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (!response.success)
                    return;

                var items = Elements.from(_template.parse('circles', response.data));
                list.empty();
                items.inject(list);
            }
        }).send();
    }
});
