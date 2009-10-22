Tap = window.Tap || {};

Tap.Main = {

	init: function(){
		var self = this;
		this.changeDates();

		$('login').addEvent('submit', this.onLogin.toHandler(this));

		var signUp = $('signup-form');
		var data = this.data = {
			user: signUp.getElement('input[name="user"]'),
			name: signUp.getElement('input[name="name"]'),
			email: signUp.getElement('input[name="email"]'),
			pass: signUp.getElement('input[name="pass"]'),
			passrepeat: signUp.getElement('input[name="passrepeat"]'),
			lang: signUp.getElement('select[name="language"]').store('passed', true)
		};
		data.user.addEvent('blur', this.checkUser.toHandler(this));
		data.name.addEvent('blur', this.checkName.toHandler(this));
		data.email.addEvent('blur', this.checkEmail.toHandler(this));
		data.pass.addEvent('blur', this.checkPass.toHandler(this));
		data.passrepeat.addEvent('blur', this.checkPassRepeat.toHandler(this));
		// $('signup-submit').addEvent('click', this.onSignup.toHandler(this));
		signUp.addEvent('submit', this.onSignup.toHandler(this));

		// $('search-public-submit').addEvent('click', this.searchFeed.toHandler(this));
		$(document.body).addEvents({
			'click:relay(a.reset-feed)': this.clearSearch.bind(this),
			'click:relay(a.tap-respond)': this.showResponseBox.toHandler(this),
			'click:relay(a.tap-respond-cancel)': this.hideResponseBox.toHandler(this),
			'click:relay(li.trending)': function(){data.user.focus();}
		});
		new OverText('tap-feed-search', { positionOptions: { offset: {x: 6, y: 4}}}).show();
		$('tap-feed-search').addEvents({
			'keypress': function(e){
				if (e.key == 'enter') self.searchFeed(this, e);
			},
			'blur': function(e){
				if (!this.isEmpty()) self.searchFeed(this, e);
			}
		});
		$('signup-title').setStyle('cursor', 'pointer').addEvent('click', function(){
			if (!this.retrieve('metric')) {
				mpmetrics.track('signup-image-click', {});
				this.store('metric', true);
			}
			data.user.focus();
		});
	},
	
	showResponseBox: function(el){
		var box = el.getParent('li').getElement('.tap-response-box');
		var input = box.getElement('input.tap-response');
		input.set('value', 'Sign in to join the conversation!');
		box.setStyle('display', 'block');
		if (!el.retrieve('metric')) {
			mpmetrics.track('outside-response-box', {});
			el.store('metric', true);
		}
	},

	hideResponseBox: function(el){
		var box = el.getParent('li').getElement('.tap-response-box');
		var input = box.getElement('input.tap-response');
		if (input.retrieve('overtext')) input.retrieve('overtext').hide();
		box.setStyle('display', 'none');
	},

	searchFeed: function(el, e){
		var self = this;
		var keyword = $('tap-feed-search').get('value');
		new Request({
			url: 'AJAX/filter_creator.php',
			data: {
				type: 100,
				search: keyword
			},
			onRequest: function(){
				$('loading-indicator').setStyle('display', 'inline');
			},
			onComplete: function(){
				$('loading-indicator').setStyle('display', 'none');
			},
			onSuccess: function(){
				Tap.ResponseBot.stop();
				var response = JSON.decode(this.response.text);
				var items;
				if (response.results) {
					response.data = response.data.filter(function(item){
						return !!item.cid;
					});
					if (response.data.length > 0) {
						items = new Element('div', {
							html: self.parseTemplate('taps', response.data)
						});
					} else {
						items = new Element('div').adopt($('no-results').clone());
					}
				} else {
					items = new Element('div').adopt($('no-results').clone());
				}
				$('main-stream').empty();
				items.getElements('li').reverse().inject('main-stream', 'top');
				Tap.ResponseBot.init();
				self.changeDates();
				if (keyword) mpmetrics.track('outside-search', {'keyword': keyword});
			}
		}).send();
	},

	parseTemplate: function(type, data){
		var template = $(({
			taps: 'template-bit'
		})[type]).innerHTML.cleanup();
		if (!this.templater) this.templater = new Template();
		return this.templater.parse(template, data);
	},

	clearSearch: function(){
		$('tap-feed-search').set('value', '');
		this.searchFeed();
	},

	onLogin: function(el, e){
		e.stop();
		var errors = false;
		var user = $('uname');
		var pass = $('pass');
		if (user.isEmpty()) {
			user.addClass('input-err');
			errors = true;
		} else {
			user.removeClass('input-err');
		}
		if (pass.isEmpty()) {
			pass.addClass('input-err');
			errors = true;
		} else {
			pass.removeClass('input-err');
		}
		if (errors) return;
		mpmetrics.track('login', {}, function(){
			el.submit();
		});
	},

	checkUser: function(el){
		var self = this;
		if (el.isEmpty() || !el.ofLength(4, 20)) {
			return this.showError(el, 'Username must be at least 4 characters');
		} else {
			new Request({
				url: 'AJAX/check_signup.php',
				data: {
					type: 1,
					val: el.get('value')
				},
				onSuccess: function(){
					var response = JSON.decode(this.response.text);
					if (!response.available) {
						self.showError(el, 'This username is already taken.');
					} else {
						self.removeError(el);
					}
				}
			}).send();
		}
		mpmetrics.track('username-complete', {});
		return this.removeError(el);
	},

	checkName: function(el){
		if (el.isEmpty()) {
			return this.showError(el, 'Please enter a name.');
		}
		return this.removeError(el);
	},

	checkEmail: function(el){
		var self = this;
		if (el.isEmpty() || !el.isEmail()) {
			return this.showError(el, 'Please enter a valid email.');
		} else {
			new Request({
				url: 'AJAX/check_signup.php',
				data: {
					type: 2,
					val: el.get('value')
				},
				onSuccess: function(){
					var response = JSON.decode(this.response.text);
					if (!response.available) {
						self.showError(el, 'This email is already used.');
					} else {
						self.removeError(el);
					}
				}
			}).send();
		}
		return this.removeError(el);
	},

	checkPass: function(el){
		if (el.isEmpty() || !el.ofLength(6, 20)) {
			return this.showError(el, 'Password must be at least 6 characters.');
		}
		return this.removeError(el);
	},

	checkPassRepeat: function(el){
		var data = this.data;
		if (el.isEmpty()) {
			return this.showError(el, 'Repeat your password.');
		} else if (el.get('value') !== data.pass.get('value')) {
			return this.showError(el, 'Your passwords don\'t match.');
		}
		mpmetrics.track('password-complete', {});
		return this.removeError(el);
	},

	showError: function(el, error){
		var msg = el.getNext('div.guide');
		msg.set('text', error);
		msg.setStyle('display', 'block');
		el.addClass('input-err');
		el.store('passed', false);
		return this;
	},

	removeError: function(el){
		var msg = el.getNext('div.guide');
		msg.setStyle('display', 'none');
		el.removeClass('input-err');
		el.store('passed', true);
		return this;
	},

	noErrors: function(){
		return !$H(this.data).getValues().map(function(item){
			return !!item.retrieve('passed');
		}).contains(false);
	},

	onSignup: function(el, e){
		var data = this.data;
		if (this.noErrors()) {
			new Request({
				url: 'AJAX/ajaz_sign_up.php',
				data: {
					uname: data.user.get('value'),
					fname: data.name.get('value'),
					email: data.email.get('value'),
					pass: data.pass.get('value'),
					lang: data.lang.get('value'),
					fid: 0,
					signup_flag: 'signup_function();'
				},
				onRequest: function(){
					$('signup-submit').setStyle('display', 'none');
					$('signup-guide').set('text', 'Signing you up..').setStyle('display', 'block');
				},
				onSuccess: function(){
					$('signup-guide').set('text', 'Logging you in...').setStyle('display', 'block');
					mpmetrics.track('signup', {'success' : 'true'}, function(){
						window.location = window.location.toString().replace('?logout=true', '');
					});
				}
			}).send();
		} else {
			$$($H(data).getValues().filter(function(item){
				return !item.retrieve('passed');
			})).fireEvent('blur', [e]);
		}
	},

	changeDates: function(){
		var now = new Date().getTime();
		$$('.tap-time').each(function(el){
			var timestamp = el.className.remove(/tap-time\s/);
			var orig = new Date(timestamp.toInt() * 1000);
			var diff = ((now - orig) / 1000);
			var day_diff = Math.floor(diff / 86400);
			if ($type(diff) == false || day_diff < 0 || day_diff >= 31) return false;
			el.set('text', day_diff == 0 && (
					// diff < 60 && "Just Now" ||
					diff < 120 && "Just Now" ||
					diff < 3600 && Math.floor( diff / 60 ) + " minutes ago" ||
					diff < 7200 && "An hour ago" ||
					diff < 86400 && Math.floor( diff / 3600 ) + " hours ago") ||
				day_diff == 1 && "Yesterday" ||
				day_diff < 7 && day_diff + " days ago" ||
				day_diff < 31 && Math.ceil( day_diff / 7 ) + " weeks ago");
		});
	}
};

Tap.ResponseBot = {

	resps: [
		"Yea this tap thing really is amazing, responses in real-time!",
		"How did you get in touch with me?  Oh, by targeting a tap group!",
		"This is so cool.",
		"Hey did you know tap has 3,000 Univeristies that you can join? I'm in college and just joined a new tap group, the communicaiton is amazing!",
		"Oh really?  Are you using tap as the main way to manage your community?",
		"Hey did you just ask a question?  tap is the perfect place to get answers!",
		"Hey is your University on tap?",
		"Really?  Did that just happen at NYU?  Let me message the tap NYU group and we'll see",
		"So I just message the tap group tapsupport if I need help?",
		"tap is better then the swine flu!  no, it really is!",
		"hey I heard ETN.FM is on tap, sweet, glad they finally formed a real-time community :)",
		"I didn't know .info was even a TLD, well I guess I know now!",
		"Wow this is like a more simplistic Google Wave!  Awesome!",
		"Oh hey I know the answer to that question",
		"Oh really?  You heard the news on tap?",
		"That's pretty cool, I guess Harvard, Yale, and all those other schools are using tap now too",
		"tap that ass!",
		"nice, tap it",
		"Wait, could you rephrase that...",
		"Pretty interesting!  I never knew a website could be so real-time and yet so simple!",
		"Oh no kidding?  tap has offices on University Ave in Palo Alto?  that's pretty wild, I thought it was just a side project, cool!",
		"Taso grew up next to Mark Zuckerberg?  wtf?",
		"No way?  tap is made up of world class developers who love beautiful women? that's wild!",
		"OMG IT'S .INFO, IT'S NOT .COM . OMG. OM NOM NOM",
		"Wait......waaiiiiit......waiiiiit for it......... TAP!",
		"Oh I tapped it.  Are you tapping?  Did you check your taps?  tap!",
		"Did you know that most people in LA and NYC are on tap?",
		"Where are you from?",
		"What time is there?",
		"You told me you're already sleeping! You're addicted! :P",
		"I'm gonna delete my Twitter account!",
		"Hah! Easier than wave, right?",
		"That's nice man!",
		"So cool! You need to post more stuff!",
		"You're here too? Wow, tap's really cool!",
		"Wait until your girlfriend sees this--she'll go nuts!",
		"I'd tap that! x)",
		"I get you.. That's how I feel at times too...",
		"Hey, what's your new number? I lost my phone yesterday. :/",
		"Are we still on for tonight?",
		"Send me a message will yah? I'm testing something out.",
		"Damn, this is gonna be BIG!!!"
	],

	init: function(){
		this.taps = $('main-stream').getElements('li');
		this.periodA = this.cycle.periodical(3000, this);
		this.periodB = this.cycle.periodical(5000, this);
		this.cycle();
	},

	cycle: function(){
		try {
			var el = this.taps[Math.floor(Math.random()*this.taps.length)];
			var resp = this.resps[Math.floor(Math.random()*this.resps.length)];
			el.getElement('.tap-typing').set('html', '<span style="color:#518E3E; font-size:10px;">(Someone\'s typing)</span>');
			(function(){
				try {
					el.getElement('.tap-typing').set('html', '');
					var counter = el.getElement('span.tap-respond-count');
					var count = (function(){
						var c = counter.get('text').match(/\(([\d]+)\)/);
						return ($type(c) == 'array') ? c[1].toInt() : 0;
					})();
					counter.set('text', ['(', count + 1, ')'].join(''));
					el.getElement('.tap-respond-last').set('text', resp);
				} catch(e) {}
			}).delay(2000);
		} catch(e) {}
	},

	stop: function(){
		$clear(this.periodA);
		$clear(this.periodB);
	}

};
window.addEvent('domready', Tap.ResponseBot.init.bind(Tap.ResponseBot));
window.addEvent('domready', Tap.Main.init.bind(Tap.Main));