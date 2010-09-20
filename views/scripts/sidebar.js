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
		var list = this.list = sidebar.getElements('#lists');
		this.header = sidebar.getElements('#tab-name');
		this.action = sidebar.getElements('#list-action');
		this.panels = list.getElements('ul')[0];
		this.tabs = list.getElements('a.tab')[0];
		this.items = list.getElements('li.panel-item')[0];
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
		var self = this;
		this.setListVars();
		this.list.addEvents({
			'click:relay(span.action)': this.actionClick.toHandler(this),
			'click:relay(a.tab)': this.changeList.toHandler(this),
			'click:relay(li.panel-item)': this.itemClick.toHandler(this),
            'click:relay(li.people-contact-list)': this.itemClick.toHandler(this),
		});
		this.subscribe({
			'convos.updated': this.moveItem.bind(this),
			'convos.removed': this.removeItem.bind(this),
			'convos.new': function(cid, uid, data){
				self.addItem('convos', data);
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
        if (this.items)
    		this.items.removeClass('selected');
		el.addClass('selected');
		switch (type){
			case 'channels':
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

            case 'private':
            case 'peoples':
                data = {
                    symbol: el.getData('uname'),
                    name: el.getData('name'),
                    topic: el.getData('topic'),
                    uid: el.getData('uid')
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
	addItem: function(type, data) {
        if (type == 'convos') 
            var item = $$('li.panel-item#cid_'+data['mid']);
            if (item.length == 0)
                Elements.from(_template.parse('list.convo', [data])).inject($('panel-convos'));
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

