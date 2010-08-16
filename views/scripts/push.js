/*
script: push.js
	Interface to the Orbited system.
*/

(function(){

var _push = _tap.register({

	init: function(){
		document.domain = document.domain;
		this.subscribe({'push.send': this.send.bind(this)});
		this.prepare();
	},

	prepare: function(){
		var socket = this.socket = new Orbited.TCPSocket();
		socket.onopen = this.handleOpen.bind(this);
		socket.onread = this.handleData.bind(this);
		socket.onclose = this.prepare.bind(this);
		this.connect();
	},

	connect: function(){
		this.socket.open('localhost', 2222);
	},

	send: function(data){
		data = JSON.encode(data);
		if (!data) return;
		this.socket.send(data + '\r\n');
		this.publish('push.sent', data);
		return this;
	},

	handleOpen: function(){
		var data = {
			uid: (_head.getElement('[name="uid"]').get('content') * 1),
			uname: _head.getElement('[name="uname"]').get('content')
		};
		this.publish('push.opened');
		if (data.uid && data.uname) {
			this.send(data);
			this.publish('push.connected', data);
		}
		return this;
	},

	handleData: function(raw){
		this.publish('push.data.raw', raw);
		var parsed, data, len, test;
		raw = raw.split('\n');
		len = raw.reverse().length;
		while (len--) {
			data = JSON.decode(raw[len]);
			if (!data) continue;
			this.publish('push.data', data);

            type = data.type
            module = data.module //'user' or 'admin'
            parsed = data.data

            //TODO: what the heck is 'notification'?
            switch (type) {
				case 'ping':
                    continue;
                    break;
				case 'refresh':
                    window.location = [window.location, '?', new Date().getTime()].join('');
                    break;
				case 'typing':
					this.publish('push.data.response.typing', [parsed.cid, parsed.uname]);
					break;
				case 'response':
					this.publish('push.data.response', [parsed.cid, parsed.uname, parsed.data, parsed.pic]);
					break;
				case 'convo':
					this.publish('push.data.convo', [parsed.cid, '']);
					break;
				case 'view_add':
					this.publish('push.data.view.add', [parsed.data, 1]);
					break;
				case 'view_minus':
					this.publish('push.data.view.minus', [parsed.data, -1]);
					break;
				case 'group_add':
					this.publish('push.data.group.add', [parsed.data, 1]);
					break;
				case 'group_minus':
					this.publish('push.data.group.minus', [parsed.data, -1]);
					break;
				case 'user_add':
					this.publish('push.data.user.add', [parsed.data, 1]);
					break;
				case 'user_minus':
					this.publish('push.data.user.minus', [parsed.data, -1]);
					break;
				case 'tap.new':
					this.publish('push.data.tap.new', [parsed, 0]);
					break;
                default:
                    this.publish('push.data.'+type, [parsed]);
            }
		}
	}

});

var _logger = _tap.register({
	
	init: function(){
		this.subscribe({
			'push.sent; push.data.raw': function(){
				console.log.apply(console, arguments);
			}
		});
	}
	
});

})();
