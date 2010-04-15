var _search = _tap.register({

	init: function(){
		var self = this,
			search = this.search = $('search');
		this.suggest = $('searchsuggest');
		this.list = this.suggest.getElement('ul');
		this.selected = null;
		this.on = false;
		this.overlay = new TextOverlay(search, 'searchover');
		search.addEvents({
			'focus': this.start.toHandler(this),
			'blur': this.end.toHandler(this),
			'keyup': this.checkKeys.bind(this)
		});
		this.list.addEvents({
			'click:relay(li)': this.itemClicked.toHandler(this),
			'mouseover:relay(li)': function(e){
				var el = this;
				self.list.getElements('li:not(.notice)').each(function(item, index){
					if (item == el){
						item.addClass('selected');
						self.selected = index;
					} else {
						item.removeClass('selected');
					}
				});
			}
		});
	},

	start: function(){
		var els = this.suggest.getElements('li');
		this.on = true;
		this.suggest.addClass('on');
		if (els.length == 0) new Element('li', {'text': 'what are you looking for?', 'class': 'notice'}).inject(this.list);
	},

	end: function(el){
		var self = this,
			list = this.list;
		this.on = false;
		this.selected = null;
		(function(){ self.suggest.removeClass('on'); }).delay(300);
		if (el.isEmpty()) list.empty();
		else list.getElements('li').removeClass('selected');
	},

	checkKeys: function(e){
		var updown = ({'down': 1, 'up': -1})[e.key];
		if (updown) return this.navigate(updown);
		if (e.key == 'enter') return this.doAction();
		if (({'left': 37,'right': 39,'esc': 27,'tab': 9})[e.key]) return;
		this.goSearch(e);
	},

	navigate: function(updown){
		var current = (this.selected !== null) ? this.selected : -1,
			items = this.list.getElements('li:not(.notice)');
		var selected = this.selected = current + updown;
		if (items.length != 0 && !(selected < 0) && selected < items.length){
			items.removeClass('selected');
			items[selected].addClass('selected');
		} else {
			this.selected = (selected < 0) ? 0 : items.length - 1;
		}
	},

	doAction: function(){
		if (this.selected == null) return;
		this.itemClicked(this.list.getElements('li')[this.selected], {});
		this.search.set('value', '').blur();
	},

	itemClicked: function(el, e){
		var type = el.getData('type');
		if (type == 'group'){
			window.location = ['/group/', el.getData('symbol')].join('');
		}
	},

	goSearch: function(e){
		var el = $(e.target),
			keyword = el.get('value');
		if (!keyword.isEmpty() && keyword.length){
			if (!this.request) this.request = new Request({
				url: '/AJAX/search_assoc.php',
				link: 'cancel',
				onSuccess: this.parseResponse.bind(this)
			});
			this.request.send({data: {search: keyword}});
		} else {
			this.list.empty();
			new Element('li', {'text': 'searching..', 'class': 'notice'}).inject(this.list);
		}
	},

	parseResponse: function(txt){
		var resp = JSON.decode(txt),
			list = this.list.empty();
		this.selected = null;
		if (!resp){
			this.list.empty();
			new Element('li', {'text': 'hmm, nothing found..', 'class': 'notice'}).inject(this.list);
			return this;
		}
		var data = resp.map(function(item){
			var els = Elements.from(item[3]),
				info = item[1].split(':');
			var a = info[4],b = info[5];
			info.shift();
			return {
				id: info.pop(),
				symbol: info.shift(),
				name: item[0],
				online: a,
				total: b,
				img: els.filter('img').pop().get('src'),
				desc: els.filter('span').pop().get('text'),
				joined: item[4]
			};
		});
		$$(Elements.from(_template.parse('suggest.group', data)).slice(0, 5)).inject(list);
		this.suggest.addClass('on');
	}

});
