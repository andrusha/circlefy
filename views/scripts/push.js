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
		while (len--){
			data = JSON.decode(raw[len]);
			if (!data) continue;
			this.publish('push.data', data);

			switch (data.msgData){
				case 'ping': continue; break;
				case 'refresh': window.location = [window.location, '?', new Date().getTime()].join(''); break;
			}
			test = $try(function(){
				return data.msgData.every(function(item){
					return $type(item) === 'array';
				});
			}) || false;
			if (!test) {
				if (data.msgData.add_count || data.msgData.minus_count) {
					parsed = {
						type: (data.msgData.add_count) ? 'view_add' : 'view_minus',
						data: (data.msgData.add_count || data.msgData.minus_count).split(',')
					};
				} else if (data.msgData.add_group || data.msgData.minus_group) {
					parsed = {
						type: (data.msgData.add_group) ? 'group_add' : 'group_minus',
						data: (data.msgData.add_group || data.msgData.minus_group).split(',')
					};
				} else if (data.msgData.add_user || data.msgData.minus_user) {
					parsed = {
						type: (data.msgData.add_user) ? 'user_add' : 'user_minus',
						data: (data.msgData.add_user || data.msgData.minus_user).split(',')
					};
				} else {
					parsed = {
						cid: data.msgData[0],
						msg: data.msgData[1],
						user: data.msgData[2],
						type: data.msgData[3] || data.msgData[1] || ''
					};
				}
			} else {
				parsed = {
					data: data.msgData,
					type: 'notification'
				};
			}
			switch (parsed.type) {
				case 'typing':
					this.publish('push.data.response.typing', [parsed.cid, parsed.user]);
					break;
				case 'response':
					this.publish('push.data.response', [parsed.cid, parsed.user, parsed.msg]);
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
				case 'notification':
					this.publish('push.data.notification', [parsed.data, 0]);
					break;
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