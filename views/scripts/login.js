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

        registerIRC: function(user,pass,options) {
        /*
        REGISTERED = User is already registered
        NOT_REGISTERED = IRC does not exist
        */
            var options = options||{};
            var successFn = options['onComplete']||$empty();

            data = { 'user': user , 'pass': pass };
            var regStatus;
            var ls = $('login-status');
            ls.set('html','<img src="/images/flat/loader.gif" /> Processing...');
            new Request({
                url: '/AJAX/login.php',
                data: data,
                onRequest: this.showLoginLoad.bind(this),
                onSuccess: function() {
                    ls.set('html','');

                    var response = JSON.decode(this.response.text);

                    var el = $('login-button');
                    var position = [el.offsetLeft+63, el.offsetTop+25];

                    if(response.status == 'REGISTERED') {
                        _notifications.alert('Success', '<img src="/images/icons/accept.png" /> Welcome back.  Logging you in...',
                            {color: 'darkgreen', delay: 2000, position: position});
                        /*ls.set('html','<img src="/images/icons/accept.png" /> Welcome back.  Logging you in...');
                        ls.removeClass('login-fail');
                        ls.addClass('login-success');*/
                        regStatus = 'login';
                        successFn.delay(2000, this, regStatus);
                    }

                    if (response.status == 'NOT_REGISTERED') {
                        _notifications.alert('Error', 'Sorry, there is no user with this username and password, please try again',
                            {color: 'darkred', delay: 5000, position: position});
                        /*ls.addClass('login-fail');
                        ls.set('text','Sorry, there is no user with this username and password, please try again');*/
                    }

                }
            }).send();
        },

	showLoginLoad: function(){
		//alert('Showing the loader...');
	}

}
