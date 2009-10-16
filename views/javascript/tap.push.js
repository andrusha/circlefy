Tap = window.Tap || {};

Tap.Push = {

	init: function(){
		document.domain = document.domain;
		var socket = this.socket = new Orbited.TCPSocket();
		socket.onopen = this.onOpen.bind(this);
		socket.onread = this.onData.bind(this);
		socket.open('localhost', 2222);
		this.connect.delay(3000, this);

	},

	connect: function(){
		this.send({
			uid: Tap.Vars.pcid,
			uname: Tap.Vars.uname
		});
		this.fireEvent('connect');
	},

	send: function(data){
		this.socket.send(JSON.encode(data) + '\r\n');
		this.fireEvent('send', data);
		return this;
	},

	sendCIDs: function(data){
		data = [].combine($splat(data));
		this.send({ cids: data.join(',') });
	},

	onOpen: function(){
		this.fireEvent('open');
	},

	onData: function(raw){
		console.log(raw);
		raw = raw.split('\n');
		for (var x = raw.reverse().length; x--;){
			data = raw[x];
			data = JSON.decode(data);
			if (!data) continue;
			this.fireEvent('data', data);
			if (data.msgData == 'ping') return null;
			var test = false;
			try {
				test = data.msgData.every(function(item){
					return $type(item) === 'array';
				});
			} catch(e) {}
			var parsed;
			if (!test) {
				parsed = {
					cid: data.msgData[0],
					msg: data.msgData[1],
					user: data.msgData[2],
					type: data.msgData[3] || data.msgData[1] || ''
				};
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
				case 'notification':
					this.fireEvent('notification', [parsed.data, 0]);
					break;
			}
		}
	}

};

$extend(Tap.Push, new Events);
window.addEvent('domready', Tap.Push.init.bind(Tap.Push));
