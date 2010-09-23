window._tap = new Observer();

/*
global: _body and _head
	Shortcuts for document.body and document.head
*/
_tap.register({
	init: function(){
		window._body = $(document.body);
		window._head = $(document.head);
		this.addGroupTips();
	},

	addGroupTips: function() {
                this.myTips = new Tips('.small-pics-logout',{fixed:true });

                this.myTips.addEvent('show', function(tip, el){
                    tip.fade('in');
                });
        }
});

var _template = {

	templater: new Template(),
	prepared: {},
	map: {
		'taps': 'template-taps',
		'responses': 'template-responses',
		'list.convo': 'template-list-convo',
		'suggest.group': 'template-suggest-group'
	},

	parse: function(type, data){
		var template = this.prepared[type];
		if (!template){
			template = this.map[type];
			if (!template) return '';
			template = this.prepared[type] = $(template).innerHTML.cleanup();
		}
		return this.templater.parse(template, data);
	}

};

window.addEvent('domready', function(){
	var form = $('tappassrecovery');
	if(form){
		email = form.getElement('input[name="email"]'),
		metaerror = $(document.head).getElement('meta[name="with-errors"]');
		form.addEvent('submit', function(e){
			e.stop();
			var submitbtn = this;
			if(email.value != ""){
				var login = _pwrecovery.sendEmail(email.value,{
					onComplete:function(status){
								var errors = false;
								switch (email.isEmpty()){
									case true: email.addClass('error'); errors = true; break;
									default: email.removeClass('error');
								}
								if (errors || status != 'login'){
									return submitbtn.getElement('.error').focus();
								}else{
									submitbtn.submit();
								}
							}
					});
			}else{
				var ls = $('pwrecovery-status');
				ls.set('html','Email cannot be empty');
				email.focus();
			}
		});

		_pwrecovery = {
			  sendEmail: function(email,options){
				    var options = options||{};
				    var successFn = options['onComplete']||$empty();
				    data = { 'email': email };
				    var regStatus;
				    var ls = $('pwrecovery-status');
				    ls.set('html','<img src="/images/flat/loader.gif" /> Processing...');
				    new Request({
						url: '/AJAX/user/password_recovery.php',
						data: data,
						onRequest: this.showPwrecLoad.bind(this),
						onSuccess: function(){
							ls.set('html','');
							var response = JSON.decode(this.response.text);

							if(response.status == '1'){
								ls.set('html','<img src="/images/icons/accept.png" /> We have sent you instructions to your email.');
								ls.removeClass('login-fail');
								ls.addClass('login-success');
								regStatus = 'login';
								successFn.delay(2000, this, regStatus);
							}

							if(response.status == '0'){
								ls.addClass('login-fail');
								ls.set('text','We havent found that address in our database');
							}
						}
					}).send();
			},
			showPwrecLoad: function(){
				//alert('Showing the loader...');
			}
		}
	}

	var formNewPass = $('tapnewpassform');
	if(formNewPass){
		pass = formNewPass.getElement('input[name="pass"]'),
		repass = formNewPass.getElement('input[name="repass"]'),
		hash = formNewPass.getElement('input[name="hash"]');

		_pwreset = {
			resetPassword: function(pass,repass, hash, options){
				  var options = options||{};
				  var successFn = options['onComplete']||$empty();
				  data = { 'pass': pass, 'repass': repass, 'hash': hash };
				  var regStatus;
				  var ls = $('pwrecovery-status');
				  ls.set('html','<img src="/images/flat/loader.gif" /> Processing...');
				  new Request({
					    url: '/AJAX/user/password_recovery.php',
					    data: data,
					    onRequest: this.showPwResLoad.bind(this),
					    onSuccess: function(){
						    ls.set('html','');
						    var response = JSON.decode(this.response.text);

						    if(response.status == '1'){
							    ls.set('html','<img src="/images/icons/accept.png" /> Your password has been reset, you can now login with your new password.');
							    ls.removeClass('login-fail');
							    ls.addClass('login-success');
							    regStatus = 'login';
							    successFn.delay(2000, this, regStatus);
						    }

						    if(response.status == '0'){
							    ls.addClass('login-fail');
							    ls.set('text','There was an error procesing the request');
						    }
					    }
				    }).send();
			},
			showPwResLoad: function(){
			    //alert('Showing the loader...');
			}
		}


		formNewPass.addEvent('submit', function(e){
			e.stop();
			var submitbtn = this;
			var ls = $('pwrecovery-status');
			if(pass.value == ""){
				ls.set('html','Password cannot be empty');
				pass.focus();
			}else{
				if(repass.value == ""){
					ls.set('html','Password confirmation cannot be empty');
					repass.focus();
				}else{
					var resetPassword = _pwreset.resetPassword(pass.value, repass.value, hash.value, {
						onComplete:function(status){
								var errors = false;
								if (errors || status != 'login'){
									return submitbtn.getElement('.error').focus();
								}else{
									submitbtn.submit();
								}
						}
					});
				}
			}
		});

		if (metaerror && metaerror.get('content') == 'y'){
			  $$(email).addClass('error');
			  email.focus();
		}
	}
});
