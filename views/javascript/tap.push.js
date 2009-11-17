var Tap = window.Tap || {};

Tap.Push = {

	init: function(){
		document.domain = document.domain;
		this.prepare();
	},
	
	prepare: function(){
		var socket = this.socket = new Orbited.TCPSocket();
		socket.onopen = this.onOpen.bind(this);
		socket.onread = this.onData.bind(this);
		socket.onclose = this.prepare.bind(this);
		this.connect();
	},
	
	connect: function(){
		this.socket.open('localhost', 2222);
	},

	onConnect: function(){
		this.send({
			uid: Tap.Vars.pcid,
			uname: Tap.Vars.uname
		});
		this.fireEvent('connect');
	},

	send: function(data){
		console.log(JSON.encode(data));
		this.socket.send(JSON.encode(data) + '\r\n');
		this.fireEvent('send', data);
		return this;
	},

	sendCIDs: function(cids, uids, gids){
		cids = [].combine($splat(cids));
		uids = [].combine($splat(uids || []));
		gids = [].combine($splat(gids || []));
		this.send({ cids: cids.join(','), uids: uids.join(','), gids: gids.join(',') });
	},

	onOpen: function(){
		this.onConnect();
		this.fireEvent('open');
	},

	onData: function(raw){
		console.log(raw);
		raw = raw.split('\n');
		for (var x = raw.reverse().length; x--;){
			data = raw[x];
			data = JSON.decode(data);
			if (!data) continue;
			var parsed;
			this.fireEvent('data', data);
			if (data.msgData == 'ping') return null;
			if (data.msgData == 'refresh') window.location = window.location + '?' + new Date().getTime();
			var test = false;
			try {
				test = data.msgData.every(function(item){
					return $type(item) === 'array';
				});
			} catch(e) {}
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
					this.fireEvent('typing', [parsed.cid, parsed.user]);
					break;
				case 'response':
					this.fireEvent('response', [parsed.cid, parsed.user, parsed.msg]);
					break;
				case 'convo':
					this.fireEvent('convo', [parsed.cid, '']);
					break;
				case 'view_add':
					this.fireEvent('viewAdd', [parsed.data, 0]);
					break;
				case 'view_minus':
					this.fireEvent('viewRemove', [parsed.data, 0]);
					break;
				case 'group_add':
					this.fireEvent('groupAdd', [parsed.data, 0]);
					break;
				case 'group_minus':
					this.fireEvent('groupRemove', [parsed.data, 0]);
					break;
				case 'user_add':
					this.fireEvent('userAdd', [parsed.data, 0]);
					break;
				case 'user_minus':
					this.fireEvent('userRemove', [parsed.data, 0]);
					break;
				case 'notification':
					this.fireEvent('notification', [parsed.data, 0]);
					break;
			}
		}
	}

};

$extend(Tap.Push, new Events);
window.addEvent('domready', Tap.Push.init.bind(Tap.Push));
