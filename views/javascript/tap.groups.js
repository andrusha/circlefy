/* Main Tap Object */
Tap = window.Tap || {};

Tap.Groups = {

	init: function(){
		var self = this;
		var body = $(document.body);

		/* Set delegated listeners... */
		this.list = $('groups-list');
		this.list.store('groups', this.list.get('html'));
		this.list.getElements('.more-invite').setStyle('display', 'list-item').slide('hide');
		this.list.getElements('.description').setStyle('display', 'block').slide('hide');
		this.list.addEvents({
			'click:relay(a.more-btn3)': this.onMore.toHandler(this),
			'click:relay(a.gr-invite-btn)': this.onInvite.toHandler(this),
			'click:relay(a.cancel-invite)': this.onInviteCancel.toHandler(this),
			'click:relay(button.invite-send)': this.onInviteSend.toHandler(this),
			'click:relay(a.invite-send)': this.onInviteSend.toHandler(this),
			// 'click:relay(a.send-invite)': this.onInviteSend.toHandler(this),
			'click:relay(a.send-join)': this.onJoinSend.toHandler(this),
			'click:relay(a.gr-join-btn)': this.onJoin.toHandler(this),
			'click:relay(a.leave-group)': this.onLeave.toHandler(this)
		});

		body.addEvents({
			'click:relay(a.remove-filter)': function(e){
				var filter = body.getElement('input[name="keywords"]');
				filter.set('value', '').fireEvent('blur', e);
			},
			'click:relay(a.remove-search)': function(e){
				e.stop();
				$('search-noresults').inject('listing-templates');
				self.list.set('html', self.list.retrieve('groups'));
				self.list.getElements('.more-invite').setStyle('display', 'list-item').slide('hide');
				self.list.getElements('.description').setStyle('display', 'block').slide('hide');
				$('gr-search-header').setStyle('display', 'none');
				$('gr-header').setStyle('display', 'block');
			},
			'click:relay(a.set-filter)': function(e){
				self.onFilter(body.getElement('input[name="keywords"]'));
			},
			'click:relay(a.suggestion-item)': function(e){
				/*e.stop();
				var key = this.get('title');
				$('search-field').set('value', key);
				self.onSearch(key);
				*/
				mpmetrics.track('group-suggestion', {'group': key});
			}
		});
		body.getElement('input[name="keywords"]').addEvents({
			'blur': this.onFilter.toHandler(this),
			'keypress': function(e){
				if (e.key == 'enter') {
					self.onFilter(this);
				}
			}
		});

		new OverText('gr-filter', { positionOptions: { offset: {x: 6, y: 4}}}).show();

		/* Search */
		var searchmore = $('gr-search-more').setStyle('display', 'block').slide('hide');
		var searchmorebtn = $('search-btn-more');
		$('search-btn').addEvent('click', function(){
			self.onSearch($('search-field').get('value'));
		});
		$$('#search-field, #search-location, #search-focus').addEvent('keypress', function(e){
			if (e.key == 'enter') {
				self.onSearch($('search-field').get('value'));
			}
		});
		searchmorebtn.addEvent('click', function(e){
			e.stop();
			var text = this.get('text');
			if (text.contains('more')) {
				this.set('html', '&laquo; less');
				searchmore.slide('in');
			} else {
				this.set('html', 'more &raquo;');
				searchmore.slide('out');
			}
		});

		if (searchkey) {
			$('search-field').set('value', searchkey);
			this.onSearch(searchkey);
		}

	},

	/*
		Event Handlers
	*/
	onSearch: function(keyword, offset, req){
		var top = this;
		offset = offset || 0;
		var body = $(document.body);
		var listing = body.getElement('ul.gr-listing');
		var template = $('search-template').innerHTML.cleanup();
		var html = "";
		var loading = $('search-loading').clone();
		$('search-noresults').inject('listing-templates');
		var request = new Request({
			url: 'AJAX/group_search.php',
			method: 'post',
			data: {
				gname: keyword,
				offset: offset
			},
			onRequest: function(){
				if (req && req.running) {
					req.cancel();
				}
				listing.empty();
				$('gr-header').setStyle('display', 'none');
				$('gr-search-header').setStyle('display', 'block');
				loading.inject(listing);
			},
			onComplete: function(){
				loading.destroy();
			},
			onSuccess: function(){
				var self = this;
				var count = $('gr-search-count');
				var next = $('gr-search-pages-next');
				var back = $('gr-search-pages-back');
				var sepcount = 0;
				var response = $try(function(){ return JSON.decode(self.response.text); });
				if (response && $type(response.group_results) == 'object') {
					response.group_results = (function(){
						var result = [];
						for (var i in response.group_results) result.push(response.group_results[i]);
						return result;
					})();
				}
				if (response && $type(response.group_results) == 'array') {
					html = new Template().parse(template, response.group_results);
					listing.fade('hide').set('html', html);
					var descs = listing.getElements('.description');
					descs.setStyle('display', 'block').slide('hide');
					var slidables = listing.getElements('.more-invite');
					slidables.setStyle('display', 'list-item').slide('hide');
					count.set('text', [
						response.row_count,
						'matching',
						(response.row_count.toInt() > 1) ? 'groups' : 'group',
						'found'
					].join(' '));
					listing.fade('in');
					if (response.group_results.length < 10) {
						next.setStyle('display', 'none');
					} else {
						sepcount++;
						next.setStyle('display', 'inline');
						next.removeEvents('click');
						next.addEvent('click', function(){
							top.onSearch(keyword, offset + 10, self);
						});
					}

					if (offset == 0) {
						back.setStyle('display', 'none');
					} else {
						sepcount++;
						back.setStyle('display', 'inline');
						back.removeEvents('click');
						back.addEvent('click', function(){
							top.onSearch(keyword, offset - 10, self);
						});
					}
					top.fireEvent('searchSuccess', keyword);
					$('gr-search-sep').setStyle('display', (sepcount == 2) ? 'inline' : 'none');
				} else {
					listing.empty();
					$('search-noresults').inject(listing);
					count.set('text', 'No matching groups found.');
					top.fireEvent('searchFail', keyword);
				}
			}
		});
		if ($('search-btn-more').get('text').contains('less')) $extend(request.options.data, {
			location: $('search-location').get('value'),
			focus: $('search-focus').get('value')
		});
		request.send();
	},

	onJoin: function(el){
		var parent = el.getParent('li');
		var id = parent.get('id').replace('gr-id_', '');
		if (parent.getElement("img.gr-offic")) {
			return this.onInvite(el);
		}
		new Request({
			url: '/AJAX/join_group.php',
			method: 'post',
			data: {
				gid: id
			},
			onRequest: function(){
				var status = el.getNext('p.status').setStyle('display', 'block');
				el.setStyle('display', 'none');
			},
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				var status = el.getNext('p.status').setStyle('display', 'block');
				if (response.good) {
					status.set('html', '<img src="images/icons/accept.png" alt="Joined" /><br/>Joined!');
				}
			}
		}).send();
		mpmetrics.track('group-join', {'type': 'user-created'});
	},

	onJoinSend: function(el){
		var self = this;
		var parent = el.getParent('li');
		var invitees = parent.getElement('input.invitees');
		var status = parent.getElement('p.status');
		var previous = parent.getParent('div').getPrevious('li');
		var btn = previous.getElement('.gr-join-btn');
		var ok = previous.getElement('.join-ok');

		// Inform user of state..
		status.set('text', 'Sending Request...');
		invitees.set('disabled', 'disabled');

		new Request({
			url: '/AJAX/connected_group_add.php',
			method: 'post',
			data: {
				email: invitees.get('value') + parent.getElement('span.gr-domain').get('text')
			},
			onSuccess: function(){
				var success = JSON.decode(this.response.text);
				if (success.stat) {
					btn.setStyle('display', 'none');
					ok.setStyle('display', 'block');
					status.set('text', 'Please check your inbox for a confirmation email.');
					(function(){ parent.slide('out'); }).delay(5000);
				}
			},
			onFailure: function(){},
			onComplete: function(){}
		}).send();
		mpmetrics.track('group-join', {'type': 'official'});
	},

	onMore: function(el){
		var top = el.getParent('li');
		var top_height = top.getStyle('height').toInt();
		console.log(top);
		if (!top.retrieve('before')) top.store('before', top_height);
		var desc = top.getElement('.description');

		if (el.retrieve('out') !== true) {
			var focus = top.getElement('.focus');
			var adjust = top.retrieve('adjust');
			if (!adjust) {
				var temp = new Element('div', {
					styles: {
						opacity: 0,
						overflow: 'auto',
						fontSize: 30
					}
				}).inject(document.body).adopt(desc.clone()).adopt(focus.clone());
				temp.getElements('p').setStyles({'width': '260px', 'font-size': '16px'})
				adjust = (temp.getCoordinates().height * 1);
				// adjust = (adjust > 100) ? 220 : adjust;
				top.store('adjust', adjust);
				temp.destroy();
			}
			desc.slide('in');
			top.morph({
				height: top_height + adjust
			});
			el.store('out', true);
			el.set('html', '&laquo; less');
		} else {
			top.morph({
				height: [(top.retrieve('adjust') + top.retrieve('before')), top.retrieve('before')]
			});
			desc.slide('out');
			el.set('html', 'more &raquo;');
			el.store('out', false);
		}

	},

	onLeave: function(el){
		var remove = confirm('Are you sure you want to leave this group?');
		if (remove) {
			var parent = el.getParent('li');
			new Request({
				url: 'AJAX/leave_group.php',
				data: {
					gid: parent.get('id').remove(/gr-id_/)
				},
				onSuccess: function(){
					parent.destroy();
				}
			}).send();
		}
	},

	onInvite: function(el){
		var top = el.getParent('li');
		var parent = top.getNext('div > li').getElement('li'),
			status = parent.getElement('p.status'),
			email = parent.getElement('input.invite-email'),
			password = parent.getElement('input.invite-pass');
		status.setStyle('display', 'none');
		$$(email, password).removeClass('input-err').set({
			'value': '',
			'disabled': null
		});
		parent.slide('in');
	},

	onInviteSend: function(el){
		var self = this, errors = false,
			parent = el.getParent('li'),
			status = parent.getElement('p.status'),
			email = parent.getElement('input.invite-email'),
			password = parent.getElement('input.invite-pass'),
			provider = parent.getElement('select.invite-provider'),
			step = (el.get('tag') == 'button') ? 'get_contacts' : 'send_invites';

		if (email.isEmpty()){
			email.addClass('input-err');
			errors = true;
		} else { email.removeClass('input-err'); }
		if (password.isEmpty()) {
			password.addClass('input-err');
			errors = true;
		} else { password.removeClass('input-err'); }
		if (errors) return;
		new Request({
			url: '/AJAX/invite_endpoint.php',
			data: (function(){
				var data = {
					email_box: email.get('value'),
					provider_box: provider.get('value'),
					step: step
				};
				if (step == 'get_contacts') data.password_box = password.get('value');
				else data.oi_session = '';
				return data;
			})(),
			onRequest: function(){
				status.set({
					'styles': {'display': 'block'},
					'html': 'Please wait...'
				});
			},
			onSuccess: function(){
				console.log(this.response.text);
				return;
				var resp = $try(JSON.decode.pass(this.response.text));
				if (step == 'get_contacts'){
					if (resp){
						status.set('html', 'Contacts imported. <a class="invite-send">Send Invites.</a>');
						$$(email, password).set('disabled', 'disabled');
					} else {
						status.set('html', 'There\'s a problem importing your contacts. Please check your login information.');
						$$(email, password).addClass('input-err');
					}
				} else {
					if (resp){
						status.set('html', 'Invites sent!');
					}
					parent.slide.delay(2000, parent, 'out');
				}
			}
		}).send();
	},

	onInviteX: function(el){
		var self = this;
		var parent = el.getParent('li');
		var invitees = parent.getElement('input.invitees');
		var status = parent.getElement('p.status');

		var invited = invitees.get('value').split(',').filter(function(item){
			return item.isEmail();
		});

		if (invited.length > 0) {
			// Inform user of state..
			status.set('text', 'Sending Request...');
			invitees.set('disabled', 'disabled');
			invitees.setStyle('background-color', '#fff');
			new Request({
				url: '/AJAX/ind_invite.php',
				method: 'post',
				data: {
					gname: invitees.retrieve('grname'),
					email:  invited.join(','), //invitees.get('value'),
					type: 2
				},
				onSuccess: function(){
					var success = JSON.decode(this.response.text);
					if (success) {
						status.set('text', 'Done!');
						parent.highlight();
						self.onInviteCancel(status);
					}
				},
				onFailure: function(){},
				onComplete: function(){}
			}).send();
			mpmetrics.track('send-invites', {'group': invitees.retrieve('grname')});
		} else {
			invitees.setStyle('background-color', '#FBC9CB');
		}
	},

	onInviteCancel: function(el){
		var parent = el.getParent('li');
		parent.slide('out');
	},

	onFilter: function(el){
		this.list.set('html', this.list.retrieve('groups'));
		this.list.getElements('.more-invite').slide('hide');
		this.list.getElements('.description').slide('hide');
		var filter = el.get('value').toLowerCase();
		var listings = $(document.body).getElements('li.listing');
		var count = 0;
		if (filter !== '') {
			for (var x = listings.reverse().length; x--;){
				var item = listings[x];
				var name = item.getElement('strong.gr-name').get('text').toLowerCase();
				var desc = item.getElement('p.description').get('text').toLowerCase();
				var focus = item.getElement('p.focus').get('text').toLowerCase().replace(/more »|« less/g, '');

				// $try(function(){ item.getNext('div > li').getElement('li').slide('hide'); });

				if (!name.contains(filter) && !desc.contains(filter) && !focus.contains(filter)) {
					item.setStyle('display', 'none');
				} else {
					count++;
					item.setStyle('display', 'list-item');
				};
			}
			if (count == 0) $('filter-noresults').clone().inject('groups-list');
		} else {
			listings.setStyle('display', 'list-item');
		}
		mpmetrics.track('filter-groups', {keyword: filter});
	}

};

$extend(Tap.Groups, new Events);
window.addEvent('domready', Tap.Groups.init.bind(Tap.Groups));