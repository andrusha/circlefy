/*
module: _search
	controls the global searchbox
*/
var _search = _tap.register({

	init: function(){
		var self = this,
			search = this.search = $('search');
		this.suggest = $('searchsuggest');
		this.list = this.suggest.getElement('ul');
		this.selected = null;
		this.keyword = null;
		this.new_keyword = null;
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
		search.focus();	
	},

	/*
	handler: start()
		fired when the search input is focused
	*/
	start: function(){
		var els = this.suggest.getElements('li');
		this.on = true;
		this.suggest.addClass('on');
		if (els.length == 0) new Element('li', {'text': 'Start or Join a conversation', 'class': 'notice'}).inject(this.list);
	},

	/*
	handler: end()
		fired when the search input is blurred
	*/
	end: function(el){
		var self = this,
			list = this.list;
		this.on = false;
		this.selected = null;
		(function(){ self.suggest.removeClass('on'); }).delay(300);
		if (el.isEmpty()) list.empty();
		else list.getElements('li').removeClass('selected');
	},

	/*
	handler: checkKeys()
		checks whether the search keys are valid or when enter is pressed
	*/
	checkKeys: function(e){
		var updown = ({'down': 1, 'up': -1})[e.key];
		if (updown) return this.navigate(updown);
		if (e.key == 'enter') return this.doAction();
		if (({'left': 37,'right': 39,'esc': 27,'tab': 9})[e.key]) return;
		this.goSearch(e);
	},

	/*
	method: navigate()
		controls the up/down keys for the search results
		
		args:
		1. updown (number) the number of movements to do (up: positive, down: negative)
	*/
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

	/*
	method: doAction()
		performs an action for an item
	*/
	doAction: function(){
		if (this.selected == null) return;
		this.itemClicked(this.list.getElements('li')[this.selected], {});
		this.search.set('value', '').blur();
	},

	/*
	handler: itemClick()
		fired when a suggestion item is clicked
	*/
	itemClicked: function(el, e){
		var type = el.getData('type');
		var id = el.getData('id');

		if (type == 'channel' && id != 0){
			window.location = ['/channel/', el.getData('symbol')].join('');
		}

		if(id==0){
			this.request = new Request({
					url: '/AJAX/group_create.php',
					link: 'cancel',
					onSuccess: function() {
						window.location = '/channel/'+this.new_keyword;
					}.bind(this)
				});
				this.request.send({data: 
					{
					gname: this.keyword,
					symbol: this.keyword
					}
				}
				);
		}
	},

	/*
	handler: goSearch()
		performs the search
	*/
	goSearch: function(e){
		var el = $(e.target),
			keyword = el.get('value');
			this.keyword = keyword;
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

	/*
	method: parseResponse()
		parses the response from the server and inject it in the suggestion box
	*/
	parseResponse: function(txt){
		var resp = JSON.decode(txt),
			list = this.list.empty();
		this.selected = null;
		var exact_match = false;
		var keyword = this.keyword;
		if (!resp){
			this.list.empty();
			var no_res_el = new Element('li', {'text': 'hmm, nothing found..', 'class': 'notice'}).inject(this.list);
			this.new_keyword = this.keyword.replace(/ /g,'-');
			var data = [
				{
					id: 0,
					symbol: 'search',
					name: 'Create New Conversation Channel <span style="color:blue;">'+this.new_keyword+'</span>',
					online: '',
					total: '',
					img: false,
					desc: 'Click here to great this channel on the fly!',
					joined: 'no'
				}
			      ];
			$$(Elements.from(_template.parse('suggest.group', data)).slice(0, 6)).inject(list);
			return this;
		}
		var data = resp.map(function(item){
			var els = Elements.from(item[3]),
				info = item[1].split(':');
			var a = info[4],b = info[5];
			info.shift();
	
			if(item[0].rtrim(' ').toLowerCase() == keyword.rtrim(' ').toLowerCase() || exact_match != false ){
				exact_match = true;
			}

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

		

		this.new_keyword = this.keyword.replace(/ /g,'-');
		if(!exact_match){
			data.push(
				{
					id: 0,
					symbol: 'search',
					name: 'Create New Conversation Channel <span style="color:blue;">'+this.new_keyword+'</span>',
					online: '',
					total: '',
					img: false,
					desc: 'Click here to great this channel on the fly!',
					joined: 'no'
				}
			);
		}
		
		$$(Elements.from(_template.parse('suggest.group', data)).slice(0, 6)).inject(list);
		this.suggest.addClass('on');
	}

});
