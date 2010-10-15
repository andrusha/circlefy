/*
script: push.js
	Interface to the Orbited system.
*/

var _push = _tap.register({

	init: function(){
		document.domain = document.domain;
		this.subscribe({'push.send': this.send.bind(this)});
        if (_vars.user.id && _vars.user.uname)
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
		this.socket.open('localhost', 2223);
	},

	send: function(data) {
		data = JSON.encode(data);
		if (!data) return;
		this.socket.send(data + '\r\n');
		this.publish('push.sent', data);
		return this;
	},

	handleOpen: function(){
		var data = {
			uid:   _vars.user.id,
			uname: _vars.user.uname 
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

            type = data.type;
            switch (type) {
				case 'ping':
                    continue;
                    break;
				case 'refresh':
                    window.location = [window.location, '?', new Date().getTime()].join('');
                    break;
                default:
                    this.publish('push.data.'+type, [data.data]);
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
