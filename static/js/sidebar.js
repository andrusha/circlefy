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

	setListVars: function() {
        var sidebar = this.sidebar = $('sidebar');
        this.menu = sidebar.getElements('ul#navigation>li');
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
		this.menu.addEvent('click:relay(a)', this.changeFeed.toHandler(this));
    },
	
	changeFeed: function(el, e){
        e.stop();
		var type = el.getData('type'),
            id = _vars.user.id,
            data = {'feed': el.getElement('a').text },
            inside = el.getData('inside');

        el.getSiblings('li').removeClass('active');
        el.addClass('active');

		this.publish('feed.change', [type, id, data, null, null, inside]);
	},
});

/*
module: _live.list
	controls the count numbers for the sidelist

require: _live
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
		var key = (type == 'channels') ? 'gid_all' : type.replace(/channel/, 'gid');
		this.setCount(key, items.length);
	},
	
	addCount: function(_, type){
		if (type == 'public') return;
		var key = (type == 'all') ? 'gid_all' : type.replace(/channel/, 'gid');
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
		var key = $((type == 'all') ? 'gid_all' : type.replace(/channel/, 'gid'));
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

