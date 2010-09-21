/*
 script: public.js
 Controls the main interface.
 */

// UNCOMMENT FOR PROD
// (function(){

var _lists = _tap.register({
    myTips: {},

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
        ['.aggr-favicons', '.panel-item-public-admin', '.people-contact-list img'].each(function(id) {
            _lists.myTips[id] = new Tips(id, {fixed: true});
            _lists.myTips[id].addEvent('show', function(tip, el) {
                tip.fade('in');
            });
        });
    }
});

/*
mixin: streaming
	mixin for streaming/feedlist operations
*/
_tap.mixin({

    name: 'streaming',

    setStreamVars: function() {
        var main = this.main = $('tapstream');
        this.stream = $('taps');
        this.header = main.getElement('h2.header-title');
        this.title = main.getElement('span.stream-name');
        this.feedType = this.header.getElement('span.title');
		this.topic = main.getElement('div.description');
        this.streamType = 'all';
    },

	/*
	method: showLoader()
		shows the loading indicator on top of the feedlist
	*/
    showLoader: function() {
        this.header.addClass('loading');
        return this;
    },

	/*
	method: hideLoader()
		hides the loading indicator on top of the feedlist
	*/
    hideLoader: function() {
        this.header.removeClass('loading');
        return this;
    },

	/*
	method: setTitle()
		sets the feedlist's title.
		
		args:
		- options (object) see below
		
		options:
		- type (string) the text that'll be used for the 'type' (eg, "group", "convo")
		- title (string) the main title for the feed
		- url (string, opt) the url for the optional link
		- desc (string, opt) the description/topic text
		- admin (string, opt) the url to the management page
	*/
    setTitle: function(options) {
		var self = this;
		if(options.type == 'convo')
			options.favicon = '/group_pics/default.ico';

        var title = options.title,
            url = options.url,
			favicon = options.favicon,
            type = options.type,
            desc = options.desc,
            admin = options.admin,
			online_count = options.online_count,
			total_count = options.total_count;

        var template = (
            ' <img class="favicon-stream-title" src="{fav}" /> {t}' +
            (online_count !== null && total_count !== null ?
             '<span class="visitor_count" title="viewers online/total"><span class="viewers_online">{oc}</span> / {tc}</span>' :
             '') + 
            ' <a href="{u}">view profile</a>');

        this.feedType.set('text', type);
        title = (!url) 
            ? title 
            : template.substitute({fav: favicon, t: title, u: url, oc: online_count, tc: total_count});

        if (options.user)
            title += '<a href="'+url+'?pm" title="send private message">send pm</a>';

        if (!!admin) title = ['<span title="Moderator" class="moderator-title">&#10070;</span> ', title, '<a href="{u}">manage channel</a>'.substitute({u: admin})].join('');
        this.title.set('html', title);

        if (desc) {
            this.topic.set('html', desc.linkify() );
//            this.main.addClass('description');
        } else {
//            this.main.removeClass('description');
        }
        return this;
    },

    /*
    method: linkify
        Replace all text-lloks-like-link into links
    */
    linkify: function(){
        var regexp = new RegExp("\
            (?:(?:ht|f)tp(?:s?)\\:\\/\\/|~\\/|\\/){1}\
            (?:\\w+:\\w+@)?\
            (?:(?:[-\\w]+\\.)+\
            (?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|[a-z]{2}))\
            (?::[\\d]{1,5})?(?:(?:(?:\\/(?:[-\\w~!$+|.,=]|%[a-f\\d]{2})+)+|\\/)+|\\?|#)?\
            (?:(?:\\?(?:[-\\w~!$+|.,*:]|%[a-f\\d{2}])+=(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)\
            (?:&(?:[-\\w~!$+|.,*:]|%[a-f\\d{2}])+=(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)*)*\
            (?:#(?:[-\\w~!$+|.,*:=]|%[a-f\\d]{2})*)?".replace(/\(\?x\)|\s+#.*$|\s+/gim, ''), 'g');

        return this.replace(regexp, function(match){
            return ['<a href="', match,'" target="_blank">', match,'</a>'].join('');
        });
    },

	/*
	method: parseFeed()
		parses the taps data in order to create the html for the feedlist
		
		args:
		1. resp (obj) the response object from the xhr.
		2. keep (bool) if true, taps already in the feed are not removed.
	*/
    parseFeed: function(resp, keep, scrollAndColor) {
        var stream = this.stream;
        if (!keep) stream.empty();
        if (resp.results && resp.data) {
            var items = Elements.from(_template.parse('taps', resp.data));
            items.each(function(item) {
                var id = item.get('id'),
                        el = $(id);
                if (el) el.destroy();
            });
			items = $$(items.reverse());

			if (items.length >= 10 && !keep) ($('loadmore-template').clone()).inject('taps','bottom').setProperty('id', 'loadmore');
			if(keep) publish_type = 'stream.more'; else publish_type = 'stream.new';
            this.publish(publish_type, [items]);
            
            if (scrollAndColor == true) {
                var overallHeight = 0;
                items.each( function (item) {
                    item.addClass('new');
                    item.setStyle('background-color', 'lightyellow');
                    overallHeight += item.getSize().y;
                });

                var curScroll = window.getScroll();
                if (curScroll.y > $('taps').getPosition().y)
                    window.scrollTo(curScroll.x, curScroll.y + overallHeight);
            }

            stream.removeClass('empty');
        } else {
            this.publish('stream.empty', $('no-taps-yet').clone());
            stream.addClass('empty');
        }
    },

	/*
	method: addTaps()
		adds taps to the feedlist
		
		args:
		1. items (elements) the tap items to be added to the feedlist
	*/
    addTaps: function(items ) {
        items.setStyles({opacity:0});
        items.inject(this.stream, 'top');
        if (_stream.loadmore_count > 0) {
            ($('loadless-template').clone()).inject(this.stream,'top').setProperty('id', 'loadless');
        }
        items.fade(1);
        this.publish('stream.updated', this.streamType);
        return this;
    }, 

	addTapsMore: function(items) {
		items.inject(this.stream, 'bottom');
		this.publish('stream.updated', this.streamType);
		return this;
	}

});

/*
module: _stream
	Controls the main tapstream/feedlist
*/
var _stream = _tap.register({

    mixins: 'streaming',

    init: function() {
		this.enableLoadMore();

        this.id = _vars.filter.gid;
        this.type = 'channels';
        this.feed = {};
        this.keyword = null;
        this.loadmore_count = 0;

        this.setStreamVars();
        this.subscribe({
            'list.item': this.setStream.bind(this),
            'stream.new; stream.empty; tapbox.sent; stream.more; stream.live.new': this.addTaps.bind(this),
            'feed.changed': (function(type) {
                this.streamType = type;
            }).bind(this),
            'filter.search': this.changeFeed.bind(this)
        });
    },

	/*
	method: setStream()
		Sets the current stream type
		
		args:
		1. type (string) the type of stream (eg, "groups", "convos")
		2. id (string) the id of the stream corresponding to a group
		3. info (obj) additional group/feedtype data
	*/
    setStream: function(type, id, info) {
        if (['channels', 'peoples', 'private'].contains(type))
            return this.changeFeed(type, id, info);

        return this;
    },

	/*
	method: changeFeed()
		changes the main tapstream
		
		args:
		1. id (string) the id of the group
		2. feed (object) additional data about the group
		3. keyword (string, opt) if present, performs a search rather than just loading taps
        4. more (int) if you want to load more
	*/
    changeFeed: function(type, id, feed, keyword, more, anon) {
        var self = this,
            data = {type: null};

        this.type = type;
        this.id = id;
        this.feed = feed;
        this.keyword = keyword;

        var prefix = ''; //urls prefix
        var user = false;
        if (type == 'channels') {
            prefix = 'channel';
            switch (id) {
                case 'all':
                    data.type = 11;
                    break;

                case 'public':
                    data.type = 100;
                    break;

                default:
                    data.type = 1;
                    data.id = id;
            }
        } else if (type == 'peoples') {
            prefix = 'user';
            feed.admin = false;
            feed.online_count = null;
            feed.total_count = null;
            user = true;
            switch (id) {
                case 'all':
                    data.type = 22;
                    break;

                default:
                    data.type = 2;
                    data.id = id;
           }
        } else if (type == 'private') {
            prefix = 'user';
            feed.admin = false;
            feed.online_count = null;
            feed.total_count = null;
            switch (id) {
                case 'all':
                    data.type = 33;
                    break;

                default:
                    data.type = 3;
                    data.id = id;
            }
        }

        var tapbox = $('taptext');

        if (tapbox) {
            if (id == 'public' || id == 'all') {
                $('taptext').disabled = true;
                $('taptext').style.background = 'gray';
            } else {
                $('taptext').disabled = false;
                $('taptext').style.background = 'white';
            }
        }

		
		if (!more) {
			more = false;
			data.more = 0;
			self.loadmore_count = 0;
		} else { 
			data.more = self.loadmore_count;
		}

        if (anon)
            data.anon = 0; //1 for guests only, 0 for registred only
        else {
            var anon_elem = $('anon_filter');
            if (anon_elem && anon_elem.retrieve('state'))
                data.anon = 0;
        }
        
        if (keyword) data.search = keyword;
        new Request({
            url: '/AJAX/filter_creator.php',
            data: data,
            onRequest: this.showLoader.bind(this),
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (!feed.hide)
                    self.setTitle({
                        title: keyword ? ['"', keyword, '" in ', feed.name].join('') : feed.name,
    //					favicon: data.type == 1 ? $$('#gid_'+id+' img.favicon-img')[0].src : '',
                        url: feed.symbol ? '/'+prefix+'/' + feed.symbol : null,
                        type: keyword ? 'search' : 'feed',
                        desc: feed.topic,
                        admin: feed.admin ? '/group_edit?channel=' + feed.symbol : null,
                        online_count: feed.online_count,
                        total_count: feed.total_count,
                        user: user
                    });

                if (response) self.parseFeed(response);
                self.hideLoader();
                self.publish('feed.changed', ['public', 'all'].contains(id) ? id : 'group_' + id);
				self.enableLoadMore();
                self.enableLoadLess();
            }
        }).send();
    },

	enableLoadLess: function(){
		var self = this;
        var loadless = $('loadless');
        if (loadless) {
            loadless.addEvent('click',function(){
                    self.loadmore_count = self.loadmore_count - 10;
                    self.changeFeed(self.type, self.id, self.feed, self.keyword, self.loadmore_count);
            });
        }
	},

	enableLoadMore: function(){
		var self = this;
        var loadmore = $('loadmore');
        if (loadmore) {
            loadmore.addEvent('click',function(){
                    self.loadmore_count = self.loadmore_count + 10;
                    self.changeFeed(self.type, self.id, self.feed, self.keyword, self.loadmore_count);
            });
        }
	},
});

/*
module: _convos
	Controls active conversations for the stream
*/
var _convos = _tap.register({

	mixins: "lists; streaming",

    init: function() {
		this.setStreamVars();
        this.subscribe({
			'list.item': this.setStream.bind(this),
			'responses.sent': this.addConvo.bind(this),
			'list.action.remove': this.removeConvo.bind(this),
			'feed.changed': (function(type){ this.streamType = type; }).bind(this),
            'responses.track': this.addConvo.bind(this),
            'responses.untrack': this.removeConvo.bind(this)
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

	setStream: function(type, id, info){
		if (type == 'convos') return this.changeFeed(id, info);
		return this;
	},

	/*
	method: changeFeed()
		loads a specific convo
		
		args:
		1. id (string) the id of the active conversation
		2. feed (object) additional data about the active conversation
	*/
	changeFeed: function(id, feed){
		var self = this,
			data = {id_list: id};
		new Request({
			url: '/AJAX/loader.php',
			data: data,
			onRequest: this.showLoader.bind(this),
			onSuccess: function(){
				var response = JSON.decode(this.response.text);
				self.setTitle({
					title: 'with '+ feed.user,
					url: '/user/' + feed.user,
					type: 'convo'
				});
				if (response) self.parseFeed(response);
				self.hideLoader();
				self.openConvo();
				self.publish('feed.changed', 'convos');
			}
		}).send();
	},


	/*
	method: openConvo()
		automatically opens the active convo's responses area
	*/
	openConvo: function(){
		var el = this.stream.getElement('a.tap-resp-count');
		if (el) this.publish('convos.loaded', el);
	},

	/*
	method: addConvo()
		tells the server that the user has responded to a convo
		
		args:
		1. cid (string) the active convo id
		2. uid (string) the user id of the original tapper
		3. data (obj) additional data about the active convo
	*/
	addConvo: function(cid, uid, data){
		var self = this;
		this.publish('convos.updated', 'cid_' + cid)
		new Request({
			url: '/AJAX/add_active.php',
			data: {cid: cid},
			onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (response.successful) 
    				self.publish('convos.new', [cid, uid, response.data]);
			}
		}).send();
	},

	/*
	method: removeConvo()
		tells the server that a user wants to be removed from an active convo
		
		args:
		1. id (string) the id of the active convo
	*/
	removeConvo: function(cid){
		var self = this;
		new Request({
			url: '/AJAX/remove_active.php',
			data: {cid: cid},
			onSuccess: function(){
				self.publish('convos.removed', [cid, 'cid_' + cid]);
			}
		}).send();
	}

});

/*
module: _responses
	Controls the responses in the main tapstream
*/
var _responses = _tap.register({
	rCounter: 0,

    init: function() {
        if (!_vars.user) {
            _body.addEvent('click:relay(a.tap-resp-count)', function() {
                window.location = '/';
            });
            return;
        }

        _body.addEvents({
            'click:relay(a.tap-resp-count)': this.setupResponse.toHandler(this),
			'click:relay(img.aggr-favicons)': this.showChannelActions.toHandler(this),
			'click:relay(a.mod-action)': this.modAction.toHandler(this),
			'click:relay(a.mod-delete-tap)': this.deleteTap.toHandler(this),
            'click:relay(a.mod-facebook-status)': this.facebookStatus.toHandler(this)
        });

        this.subscribe({
            'responses.new': this.addResponses.bind(this),
            'convos.loaded': this.setupResponse.bind(this)
        });

        this.openBoxes();
    },

    facebookStatus: function(el, e) {
        var cid = el.getData('cid');
        var symbol = el.getData('symbol');

        this.publish('modal.show.facebook-status', [cid, symbol]);
    },

    openBoxes: function() {
        var box = _body.getElement('div.perma');
        if (!box) return;
        var link = box.getParent('li').getElement('a.tap-resp-count');
        _body.fireEvent('click', {stop: $empty, preventDefault: $empty, target: link});
    },

	/*
	method: deleteTap()
		tells the server that a tap must be deleted (by an admin or its owner)
		
		args:
		1. el (object): the DELETE link. It needs data-cid set.
	*/
	deleteTap: function(el, e) {
		var delete_cid 		= el.getData('cid');
		var executingPopup;

		new Request({
			url: '/AJAX/delete_tap.php',
			data: {
				cid: delete_cid
			},
			onRequest: function() {
				_notifications.alert("Please wait :)", "We are processing your request... <img src='/images/ajax_loading.gif'>",
					{ position: 'bottomRight', 
					  color: 'black',
					  duration: 5000});
				executingPopup = _notifications.items.getLast();
			},
			onSuccess: function(){
                var data = JSON.decode(this.response.text);
				if (!data) return;
				var success = data.successful;
				var msgSuccess = "ERROR: Tap could not be deleted!";

				_notifications.remove(executingPopup);
				if (success) {
					msgSuccess = "Tap deleted successfully!"
				}
				_notifications.alert("Deleting Tap", msgSuccess, 
					{ position: 'bottomRight', 
					  color: 'black',
					  duration: 5000});
		}
		}).send();
		
	},

	/*
	method: modAction()
		tells the server that we want to execute a Moderation Action
		
		args:
		1. el (object): the link. Needs data-action set. Also, must be inside a UL with these data settings set: data-target_uid, data-gid
	 		- data-action		: must be "ban", "unban", "promote" or "unpromote"
			- data-target_uid	: is the user we're banning or promoting
			- data-gid	 		: is the group we're on
	*/
	modAction: function(el, e) {
		var param_target_uid 	= el.getParent('ul').getData('target_uid');	// UID (admin options UL)
		var param_gid 			= el.getParent('ul').getData('gid');		// GID (admin options UL)
		var param_action 		= el.getData('action');						// Action (link)
		var executingPopup;

		new Request({
			url: '/AJAX/group_mod.php',
			data: {
				gid: param_gid,
				target_uid: param_target_uid,
				action: param_action
			},
			onRequest: function() {
				_notifications.alert("Please wait :)", "We are processing your request... <img src='/images/ajax_loading.gif'>",
					{ position: 'bottomRight', 
					  color: 'black',
					  duration: 5000});
				executingPopup = _notifications.items.getLast();
			},
			onSuccess: function(){
				_notifications.remove(executingPopup);

				_notifications.alert("OK!", "test 123", 
					{ position: 'bottomRight', 
					  color: 'black',
					  duration: 5000});
		}
		}).send();
		
   },


	/*
	handler: showChannelActions()
		shows a popup with channel actions:
		- Go to channel
		- Ban/Promote user (only if you're admin in that channel)
	*/
	showChannelActions: function(el, e) {
		var parent = el.getParent('li');
		var data_cid = parent.getData('id'),
			data_uid = parent.getData('uid'),
			data_uname = parent.getData('user');
		
		_notifications.alert("Please wait :)", "Loading tap options... <img src='/images/ajax_loading.gif'>",
			{ position: [el.offsetLeft-272, el.offsetTop-4], 
			  color: 'black',
			  duration: 5000});
		var loadingPopup = _notifications.items.getLast();

//		alert("cid: " + data_cid + " // uid: " + data_uid + " // uname: " + data_uname);
//
        var self = this;
        var elem = el;
        new Request({
            url: '/AJAX/group_mod.php',
            data: {
					cid: data_cid,
					target_uid: data_uid,
					gid: "",
					action: "get_channel_actions"
					},
            onSuccess: function() {
                var data = JSON.decode(this.response.text);
				if (!data.public) return;
				var t_uname = data.public.uname;
				var t_gname = data.public.gname;
				var t_gid	= data.public.gid;
				var t_symbol = data.public.chansymbol;
				var t_delete_permission = data.options.deletepermission;
				var t_ban_action = data.admin.ban_action;
				var t_prom_action = data.admin.prom_action;

				// Default promote & Ban Options
				var banText		= "Ban";
				var promText 	= "Promote";
				var banClass 	= "mod-" + t_ban_action + "-user";
				var promClass 	= "mod-" + t_prom_action + "-user";
				
				var html_user_add = "", html_admin_add = "";
				if (t_delete_permission=="owner") {
					html_user_add = "<br /><a href='#'>Delete this tap</a>";
				} else if (t_delete_permission=="admin") {
					//html_admin_add = "<br />Delete tap";
				}

				/* * * * * * * * * * * * * * ** * * NOTIFICATION * * * * * * * * * * * * * * * * * * * * */

				var txtPopup = "<ul class='modOptions'>";
				txtPopup += "<li><a href='/user/"+t_uname+"' class='modlink mod-go-profile'>View <b>"+t_uname+"</b> profile</a></li>";
				txtPopup += "<li><a href='/user/"+t_uname+"?pm' class='modlink mod-send-pm'>Send <b>" + t_uname + "</b> a Private Message</a></li>";
                if (t_symbol)
    				txtPopup += "<li><a href='/channel/" + t_symbol  + "' class='modlink mod-go-channel'>Go to channel <b>" + t_gname + "</b></a></li>";
				txtPopup += "<li><a href='/tap/" + data_cid  + "' class='modlink mod-go-tap'>View this discussion</b></a></li>";
				if (t_delete_permission=="owner") {
					txtPopup = txtPopup + "<li><a href='#' class='modlink mod-delete-tap' data-cid='"+data_cid+"'>Delete this tap</a></li>";
				}
                if (data.options.owner && _vars.user.fb_uid)
                    txtPopup += "<li><a href='#' class='modlink mod-facebook-status' data-cid='"+data_cid+"' data-symbol='"+t_symbol+"'>Share on Facebook</a></li>";
				txtPopup = txtPopup + "</ul>";
				var txtTitle = "Tap #" + data_cid;


				// I have admin rights here:
				if (data.admin.moderator) {
					if (t_ban_action == "unban") banText = "Unban";
					if (t_prom_action == "unpromote") promText = "Unpromote";

					var admOpt = "<ul class='modOptions' data-target_uid='" + data_uid + "' data-gid='"+t_gid+"'>";
					
						// BAN / UNBAN LINK. (Class mod-action binds this link to the function modAction)
						if (t_ban_action) {
						admOpt = admOpt + "<li><a href='#' class='mod-action modlink "+banClass+"' data-action='"+t_ban_action+"'>";
						admOpt = admOpt + "" + banText + " <b>" + t_uname + "</b> from <b>"+t_gname+"</b></a></li>";
						}

						if (t_prom_action) {
						// PROMOTE / UNPROMOTE LINK  (Class mod-action binds this link to the function modAction)
						admOpt = admOpt + "<li><a href='#' class='mod-action modlink "+promClass+"' data-action='"+t_prom_action+"'>";
						admOpt = admOpt + "" + promText + " <b>"+t_uname+"</b></li>";
						}
						
						// TAP DELETION LINK
						admOpt = admOpt + "<li><a href='#' class='modlink mod-delete-tap' data-cid='"+data_cid+"' >Delete this tap</a></li>";

					admOpt = admOpt + "</ul>";

					txtPopup = "<b>Admin Options</b>" + admOpt + "<hr> " + txtPopup;
				}

				_notifications.remove(loadingPopup);

				_notifications.alert(txtTitle, txtPopup, 
					{ position: [el.offsetLeft-272, el.offsetTop-4], 
					  color: 'black',
					  duration: 10000});
				/* * * * * * * * * * * * * * ** * * NOTIFICATION * * * * * * * * * * * * * * * * * * * * */
            }
        }).send();
		return this;
	},

	/*
	handler: setupResponse()
		adds event handlers to the taps' responses area
		NOTE: toggleNotifier pauses the stream
	*/
    setupResponse: function(el, e) {
		if (this.rCounter == 0 && _live.taps.stream ) {
			_live.taps.toggleNotifier($('streamer'));
		}

        var parent = el.getParent('li'),
            responses = parent.getElement('div.responses'),
            box = responses.getElement('ul.chat'),
            chat = responses.getElement('input.chatbox'),
            counter = responses.getElement('.counter'),
            overlay = responses.getElement('div.overlay');
        if (e) e.preventDefault();

		if (!responses.hasClass('open')) {
			this.rCounter++
		} else { 
			this.rCounter--;
		}

        responses.toggleClass('open');
        if (!box.retrieve('loaded')) this.loadResponse(parent.getData('id'), box);
        if (!chat.retrieve('extended')) this.extendResponse(chat, counter, overlay);
        box.scrollTo(0, box.getScrollSize().y);
        return this;
    },

	/*
	method: loadResponse()
		loads previous responses for the tap
		
		args:
		1. id (string) the id of the tap
		2. box (element) the tap's response area element
	*/
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

	/*
	method: addResponses()
		adds new responses to a tap's response area
		
		args:
		1. box (element) the tap's response area element
		2. data (object) data to use in parsing the template
	*/
    addResponses: function(box, data) {
        data = data.map(this.processResponses, this);
        var items = Elements.from(_template.parse('responses', data));
        items.setStyles({opacity:0});
        items.fade(1);
        items.inject(box);
        this.updateStatus(box);
        box.scrollTo(0, box.getScrollSize().y);
        this.publish('responses.updated');
        return this;
    },
    
    /*
        Response processing, uses for linkify, smilify, etc
    */
    processResponses: function(response) {
        response.chat_text = this.smilify(response.chat_text);
        return response;
    },

    /*
        Add smiles ^_^"
    */
    smilify: function(msg) {
        var smiles = {'hug': 'bearhug.gif'};
        
        return msg.replace(
            new RegExp('\\(([a-z]*)\\)', 'gi'),
            function (full, name) {
                if (!smiles[name])
                    return full;

                return '<img src="/images/'+smiles[name]+'" title="'+full+'">';
            });
    },

	/*
	method: updateStatus()
		updates the tap's last response data and the response count

        note: response data omitted, just count
		
		args:
		1. box (element) the tap's response area element
	*/
    updateStatus: function(box) {
        var items = box.getElements('li'),
            last = items.getLast(),
            parent = box.getParent('li'),
            lastresp = parent.getElement('span.last-resp'),
            username = lastresp.getElement('strong'),
            chattext = lastresp.getElement('span'),
            count = parent.getElement('a.tap-resp-count span.show-resp-count');
        username.set('text', last.getElement('a').get('text'));
        chattext.set('text', last.getChildren('span').get('text'));
        count.set('text', items.length);
    },

	/*
	method: extendResponse()
		adds event handlers to the tap's response area textbox
		
		args:
		1. chatbox (element) the tap's response area element
		2. counter (element) the tap's counter element
		3. overlay (element) the tap's overlay element
	*/
    extendResponse: function(chatbox, counter, overlay) {
        var self = this,
            limit = 240,
            allowed = {'enter': 1,'up': 1,'down': 1,'left': 1, 'right': 1,'backspace': 1,'delete': 1};
        chatbox.addEvents({
            'keydown': function(e) {
                var outOfLimit = this.get('value').length >= limit;
                if (outOfLimit && !allowed[e.key]) {
                    _notifications.alert('Error', 'Your message is too long', {color: 'darkred'});
                    return e.stop();
                } else if (!outOfLimit) {
                    //all Ok guys, we can hide notification, but no need
                }
            },
            'keypress': function() {
                self.publish('responses.typing', chatbox);
            },
            'keyup': function(e) {
                var length = this.get('value').length,
                        count = limit - length;
                if (e.key == 'enter' && !this.isEmpty()) {
                    return self.sendResponse(chatbox, counter);
                }
                counter.set('text', count);
            }
        });
        chatbox.store('overlay', new TextOverlay(chatbox, overlay));
        chatbox.store('extended', true);
    },

	/*
	method: sendResponse()
		sends the response data for a tap to the server
		
		args:
		1. chatbox (element) the tap's response area element
		2. counter (element) the tap's counter element
	*/
    sendResponse: function(chatbox, counter) {
        var self = this,
                parent = chatbox.getParent('li'),
                cid = parent.getData('id'),
                uid = parent.getData('uid'),
                pic = _vars.user.small_pic, 
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

	/*
	method: clearResponse()
		resets the tap's response area element
		
		args:
		1. chatbox (element) the tap's response area element
		2. counter (element) the tap's counter element
	*/
    clearResponse: function(chatbox, counter) {
        chatbox.set('value', '');
        counter.set('text', '240');
        chatbox.focus();
        return this;
    }

});

/*
module: _tapbox
	controls the main tap sender
*/
var _tapbox = _tap.register({

    sendTo: _vars.sendTo,

    init: function() {
        this.tapbox = $('tapbox');
        if ((!_vars.user) || !this.tapbox) return;
        this.overlayMsg = $('tapto');
        this.msg = $('taptext');

        this.counter = this.tapbox.getElement('span.counter');
        this.overlay = new TextOverlay('taptext', 'tapto');
        this.setupTapBox();
        this.tapbox.addEvent('submit', this.send.toHandler(this));
        this.tapbox.addEvent('click', function(el){
            var tt = $('tapto').innerHTML;
            var ngs = $('no-group-selected');
            if(tt == 'choose a channel to tap'){
                ngs.style.display = 'block';
                ngs.fade('hide');
                ngs.fade(1).fade.delay(4000,ngs,0);
                ngs.setStyles.delay(4700,ngs,{'display':'none'});
            }
        });

        this.subscribe({'list.item': this.handleTapBox.bind(this)});
    },

	/*
	method: setupTapBox()
		adds event handlers to the tapbox
	*/
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

	/*
	method: clear()
		resets the tapbox
	*/
    clear: function() {
        this.msg.set('value', '').blur();
        this.counter.set('text', '240');
        this.overlay.show();
        return this;
    },

    /*
	method: handleTapBox()
		changes the tap box when a new group from the list is clicked
		
		args:
		1. type (string) the type of item clicked
		2. id (string) the id of the item click
		3. data (obj) additional data for the item
	*/
    handleTapBox: function(type, id, data) {
        if (!(['channels', 'private'].contains(type))) return;
        this.changeOverlay(id, data.name);
        this.changeSendTo(data.name, data.symbol, id, type);
    },

	/*
	method: changeOverlay()
		changes the overlay text for the tap box
		
		args:
		1. id (string) the id of the group
		2. name (string) the name of the group
	*/
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

	/*
	method: changeSendTo()
		changes the destination for the tap box
		
		args:
		1. name (string) the name of the group
		2. symbol (string) the symbol of the group
		3. id (string) the id of the group
	*/
    changeSendTo: function(name, symbol, id, type) {
        type = type ? type : 'groups';
        this.sendTo = {name: name, symbol: symbol, type: type, id: id};
        return this;
    },

	/*
	handler: send()
		sends the tap to the server when the send button is clicked
	*/
    send: function(el, e) {
        e.stop();
        if (this.msg.isEmpty()) return this.msg.focus();
        new Request({
            url: '/AJAX/new_message_handler.php',
            data: {
                msg: this.msg.get('value'),
                to: this.sendTo
            },
            onSuccess: this.parseSent.bind(this)
        }).send();
    },

	/*
	method: parseSent()
		injects the recently sent tap to the top of the tapstream
	*/
    parseSent: function(response) {
        var resp = JSON.decode(response);
        if (resp.new_msg) {
            this.clear();
            var item = Elements.from(_template.parse('taps', resp.new_msg));
            this.publish('tapbox.sent', item);
        }
        if (resp.your_first && _vars.user.guest)
            this.publish('modal.show.sign-notify', []);
    }

});

/*
 * module: _resizer
 *
 * Makes responses area resizeable
 */
_resizer = _tap.register({
    init: function() {
        this.makeResizeable();
        this.subscribe({
            'stream.updated': this.makeResizeable.bind(this),
        });
    },

    makeResizeable: function() {
        this.resizers = $$('div.resizer');
        this.resizers.each( function(div) {
            var chat = div.parentNode.getElement('ul.chat');
            var drag = new Drag(chat, {
                snap: 0,
                handle: div,
                modifiers: {y: 'height'},
                onComplete: function(el) {
                    el.scrollTo(0, el.getScrollSize().y);
                }
            });
        });
    },
});

/*
module: _live.stream
	controls the automatic tap streaming

require: _vars
*/
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

	/*
	method: refreshStream()
		parses the page and gets user, group and tap ids for the push server
	*/
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

	/*
	method: refreshConvos()
		parses the page and gets the convo ids for the push server
		
		args:
		1. type (string) the type of item clicked
		2. id (string) the id of the item click
		3. data (obj) additional data for the item
	*/
    refreshConvos: function() {
        this.convos = this.convolist.getElements('li[data-id]').map(function(item) {
            return item.getData('id');
        });
        this.update();
    },

	/*
	method: update()
		sends changes to the push server
	*/
    update: function() {
        var cids = [].combine($splat(this.stream)).combine($splat(this.convos)),
                uids = [].combine($splat(this.people || [])),
                gids = [].combine($splat(this.groups || []));
        if (!(cids.length || uids.length || gids.length))
            return;

        this.publish('push.send', {
            cids: cids.join(','),
            uids: uids.join(','),
            gids: gids.join(',')
        });
    }

});

/*
module: _live.viewers
	controls the view numbers for the tap stream
*/
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
            if (span)
                span.set('text', (span.get('text') * 1) + amount);
        }
        return this;
    }

});

/*
module: _live.typing
	controls the typing indicator
*/
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
        var indicator, parent = $('tid_' + tid), self = this;
        if (!parent) return;

        indicator = parent.getElement('span.tap-resp');

        var users = indicator.retrieve('users') || new Hash();
        users.set(user, users.get(user) + 1);
        indicator.store('users', users);

        (function() {
            var users = indicator.retrieve('users');
            users.set(user, users.get(user) - 1);
            indicator.store('users', users);

            self.redrawIndicator(indicator);
        }).delay(2000);

        this.redrawIndicator(indicator);
    },

    redrawIndicator: function(indic) {
        var users = indic.retrieve('users');
        if (users.every(function (val) { return val == 0; })) { 
            indic.removeClass('typing');
            return;
        }

        var writers = users.filter(function (val) { return val > 0; }).getKeys();
        if (writers.length >= 2) {
            var last = writers.pop();
            writers = writers.join(', ') + ' & ' + last;
        } else
            writers = writers.pop();

        indic.getElement('span.indicator').set('text', writers + ' typing...');
        indic.addClass('typing');
    }

});

/*
module: _live.responses
	parses the pushed responses from the server
*/
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
        var time_raw = new Date().getTime().toString().substring(0, 10);
        if (box) this.publish('responses.new', [box, [
            {
                uname: user,
                small_pic: pic,
                chat_text: msg.stripTags(),
                chat_time_raw: time_raw,
                chat_time: _dater.timestampToStr(time_raw*1)
            }
        ]]);
    },


});

/*
module: _live.users
	controls the offline/online mode for users

require: _body
*/
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

/*
module: _live.taps
	controls the new taps sent by the push server

require: _responses
*/
_live.taps = _tap.register({

    init: function() {
        var self = this;
        this.pushed = [];
        this.notifier = $('newtaps');
        this.streamer = $('streamer');
        this.stream = !!(this.streamer);
        this.subscribe({
            'push.data.tap.new': this.process.bind(this),
            'push.data.tap.delete': this.deleteTap.bind(this),
            'feed.changed': this.clearPushed.bind(this)
        });
        this.counter = $('taps-count');
        this.notifier.addEvent('click', function(e) {
            e.stop();
            self.showPushed();
            self.hideNotifier();
            self.counter.addClass('hidden');
        });
        if (this.streamer) this.streamer.addEvent('click', this.toggleNotifier.toHandler(this));

        window.addEvent('scroll', function () {
            var newTaps = $$('li.container.new');
            if (newTaps.length == 0)
                return;

            var curScroll = window.getScroll();

            newTaps.each( function (tap) {
                if (tap.get('viewed'))
                    return;

                if (tap.getPosition().y < curScroll.y)
                    return;

                tap.set('viewed', true);
                (function () {
                    tap.removeClass('new');
                    var fx = new Fx.Tween(tap);
                    fx.start('background-color', 'lightyellow', 'white');
                }).delay(1500);
            });
        });
    },

    deleteTap: function(data) {
        gid = data['gid'];
        cid = data['cid'];

        var tap = $('tid_'+cid);
        if (!tap)
            return;
        
        var body = tap.getElement('p.tap-body');
        body.set('text', 'This tap deleted');
        tap.addClass('deleted');

        this.publish('tap.deleted', [cid]);
    },

    process: function(data) {
        this.pushed.combine([data]);

        if (this.stream) {
            this.showPushed();
        } else {
            this.showNotifier(this.pushed.length);
        }
    },

    showPushed: function() {
        _stream.parseFeed({results: true, data: this.pushed.reverse()}, true, true);
        this.clearPushed();
    },

    clearPushed: function(type) {
        this.pushed = [];
        this.hideNotifier();
    },

    showNotifier: function(count) {
        var notifier = this.notifier;
        notifier.set('text', [
            count, 'new', count == 1 ? 'tap.' : 'taps.', 'Click here to load them.'
        ].join(' '));
        this.counter.set('text', count);
        notifier.addClass('notify');
    },

    hideNotifier: function() {
        this.notifier.set('text', '').removeClass('notify');
    },

    toggleNotifier: function(el) {
        if (el.hasClass('paused')) {
            this.stream = true;
            el.removeClass('paused').set({
                'alt': 'pause live streaming',
                'title': 'pause live streaming',
				'text' : ''	
            });
        } else {
			_responses.rCounter = 0;	
            this.stream = false;
            el.addClass('paused').set({
                'alt': 'start live streaming',
                'title': 'start live streaming',
				'text':'paused'
            });
        }
    }

});

/*
 * module: _live.notifications
 *
 * Shows notifications on new response in your convos,
 * new tap in followed channels,
 * new followers & new private messages
*/
_live.notifications = _tap.register({
    init: function() {
       var self = this;
        this.subscribe({
            'push.data.notify.convo.response': this.newConvoResponse.bind(this),
            'push.data.notify.tap.new': this.newTap.bind(this),
            'push.data.notify.follower': this.newFollower.bind(this),
            'push.data.notify.private': this.newPrivate.bind(this)
        });
    },

    newConvoResponse: function(data) {
        cid = data['cid'];
        uname = data['uname'];
        ureal_name = data['ureal_name'] ? data['ureal_name'] : uname;
        text = data['text'];

        _notifications.alert('<span class="notification-title icon-response">New response</span>',
            '<a href="/user/'+uname+'">' + ureal_name + 
            '</a> says "' + text + '" in <a href="/tap/'+cid+'">conversation</a>!');
    	
        _notifications.items.getLast().addEvent('click', function() {
        	document.location.replace('http://tap.info/tap/'+cid);
    	});
    },

    newTap: function(data) {
        gname = data['gname'];
        greal_name = data['greal_name'];
        uname = data['uname'];
        ureal_name = data['ureal_name'] ? data['ureal_name'] : uname;
        text = data['chat_text'];

        _notifications.alert('<span class="notification-title icon-tap">New tap</span>',
            '<a href="/user/'+uname+'">' + ureal_name +
            '</a> starts discuss about "' + text + '" in <a href="/channel/'+gname+'">'+
            greal_name + '</a> channel!');

    	_notifications.items.getLast().addEvent('click', function() {
        	document.location.replace('http://tap.info/channel/'+gname);
    	});
    },

    newFollower: function(data) {
        status = data['status'];
        uname = data['uname'];
        ureal_name = data['ureal_name'] ? data['ureal_name'] : uname;

        var title = '';
        var message = '';
        if (status) {
            title = '<span class="notification-title icon-user">New follower</span>';
            message = 'Now <a href="/user/'+uname+'">' + ureal_name +
                '</a> follows you everywhere in your tap journey!';
        } else {
            title = 'Follower gone';
            message = '<a href="/user/'+uname+'">' + ureal_name +
                '</a> doesn\'t follow you anymore :(';
        }
        _notifications.alert(title, message);

    	_notifications.items.getLast().addEvent('click', function() {
        	document.location.replace('http://tap.info/user/'+uname);
    	});
    },

    newPrivate: function(data) {
        cid = data['cid'];
        uname = data['uname'];
        ureal_name = data['ureal_name'] ? data['ureal_name'] : uname;
        text = data['chat_text'];

        _notifications.alert('<span class="notification-title icon-private">New PM</span>',
            '<a href="/user/'+uname+'">' + ureal_name +
            '</a> send\'s you a private message "' + text + '"!');

    	_notifications.items.getLast().addEvent('click', function() {
        	document.location.replace('http://tap.info/tap/'+cid);
    	});

    }
});

// UNCOMMENT FOR PROD
// })();
