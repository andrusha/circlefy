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
        var form = $('taplogin'),
                user = form.getElement('input[name="uname"]'),
                pass = form.getElement('input[name="pass"]'),
                metaerror = $(document.head).getElement('meta[name="with-errors"]');

        form.addEvent('submit', function(e){
                e.stop();
                var submitbtn = this;

                var login = _login.registerIRC(user.value,pass.value, {onComplete:function(status){
                     var errors = false;
                     switch (user.isEmpty()){
                        case true: user.addClass('error'); errors = true; break;
                        default: user.removeClass('error');
                     }
                     switch (pass.isEmpty()){
                        case true: pass.addClass('error'); errors = true; break;
                        default: pass.removeClass('error');
                     }

                     //alert(status);
                     if (errors || status != 'login') return submitbtn.getElement('.error').focus();
                     else submitbtn.submit();
                } });
                
        });

        if (metaerror && metaerror.get('content') == 'y'){
                $$(user, pass).addClass('error');
                user.focus();
        }
});

_login = {

        registerIRC: function(user,pass,options){
        /*
        ADDED_ADDED = Registered IRC USER w/ channels
        ADDED_NONE = Registered IRC USER w/ no channels
        REGISTERED = User is already registered
        LOGIN_FAILED  = IRC User exists but failed pass
        NOT_REGISTERED = IRC does not exist
        */
                var options = options||{};
                var successFn = options['onComplete']||$empty();

                data = { 'user': user , 'pass': pass };
                var regStatus;
		var ls = $('login-status');
		ls.set('html','<img src="/images/flat/loader.gif" /> Processing...');
                new Request({
                        url: '/AJAX/irc.php',
                        data: data,
                        onRequest: this.showLoginLoad.bind(this),
                        onSuccess: function(){
				ls.set('html','');
	
                                var response = JSON.decode(this.response.text);
	
                                if(response.status == 'REGISTERED'){
					ls.set('html','<img src="/images/icons/accept.png" /> Welcome back.  Logging you in...');
					ls.removeClass('login-fail');
					ls.addClass('login-success');
                                        regStatus = 'login';
                                        successFn.delay(2000, this, regStatus);
                                }

                                if(response.status == 'NOT_REGISTERED'){
					ls.addClass('login-fail');
					ls.set('text','Sorry, there is no IRC user with this username, please try again');
                                }

                                if(response.status == 'SORRY'){
					ls.addClass('login-fail');
					ls.set('text','Wrong username or password!');
                                }

                                if(response.status == 'ADDED_NONE'){
					ls.removeClass('login-fail');
					ls.addClass('login-success');
					ls.set('html','<img src="/images/icons/accept.png" /> Welcome!  You are now a tap user!  Enjoy Tap!');
                                        regStatus = 'login';
                                        successFn.delay(5000, this, regStatus);
                                }

                                if(response.status == 'ADDED_ADDED'){
					ls.removeClass('login-fail');
					ls.addClass('login-success');
					ls.set('html','<img src="/images/icons/accept.png" /> Welcome!  You are now a tap user AND we have added you to all of your communities you moderate!  Enjoy Tap!');
                                        regStatus = 'login';
                                        successFn.delay(5000, this, regStatus);
                                }
		
                                if(response.status == 'LOGIN_FAILED'){
					ls.addClass('login-fail');
					ls.set('text','Well it seems you are apart of freenode but your password is incorrect!  Retry!');
				}
				

                        }
                        }).send();
        },

	showLoginLoad: function(){
		//alert('Showing the loader...');
	}

}
