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
			uname: "Taso"
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

	onData: function(data){
		data = JSON.decode(data);
		this.fireEvent('data', data);
		var parsed = {
			cid: data.msgData[0],
			xxx: data.msgData[1],
			user: data.msgData[2],
			type: data.msgData[3]
		};
		switch (parsed.type) {
			case 'typing':
				this.fireEvent('typing', [parsed.cid, parsed.user]);
				break;
		}
	}

};

$extend(Tap.Push, new Events);
window.addEvent('domready', Tap.Push.init.bind(Tap.Push));