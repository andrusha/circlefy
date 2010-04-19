(function(){

var curtain = $('curtain');
var connectForm = $('connect-signup');
var indic = connectForm.getElement('span.indicator');
var indic_msg = indic.getElement('span.indic-msg');
$('connect-cancel').addEvent('click', function(e){
        e.stop();
        $$(curtain, connectForm).setStyle('display', 'none')
});
var signupData = {
        name: connectForm.getElement('input[name="name"]').store('passed', true),
        uname: connectForm.getElement('input[name="uname"]'),
        email: connectForm.getElement('input[name="email"]'),
        pass: connectForm.getElement('input[name="pass"]')
};

var showError = function(el, msg){
        el.store('passed', false).addClass('error');
        el.getParent('p').getNext('p.guide').set('text', msg).setStyle('display', 'block');
};

var removeError = function(el){
        el.store('passed', true).removeClass('err').addClass('passed');
        el.getParent('p').getNext('p.guide').setStyle('display', 'none');
};

var noErrors = function(){
        return !$H(signupData).getValues().map(function(item){
                return !!item.retrieve('passed');
        }).contains(false);
};

var checkUname = function(el){
        if (el.isEmpty() || !el.ofLength(4, 20)) {
                return showError(el, 'Username must be at least 4 characters');
        } else {
                new Request({
                        url: '/AJAX/check_signup.php',
                        data: {
                                type: 1,
                                val: el.get('value')
                        },
                        onSuccess: function(){
                                var response = JSON.decode(this.response.text);
                                if (!response.available) {
                                        showError(el, 'This username is already taken.');
                                } else {
                                        removeError(el);
                                }
                        }
                }).send();
        }
        //mpmetrics.track('username-complete', {'type': Tap.Vars.ref ? 'referral' : 'direct'});
        return removeError(el);
};

var checkEmail = function(el){
        if (el.isEmpty() || !el.isEmail()) {
                return showError(el, 'Please enter a valid email.');
        } else {
                new Request({
                        url: '/AJAX/check_signup.php',
                        data: {
                                type: 2,
                                val: el.get('value')
                        },
                        onSuccess: function(){
                                var response = JSON.decode(this.response.text);
                                if (!response.available) {
                                        showError(el, 'This email is already used.');
                                } else {
                                        removeError(el);
                                }
                        }
                }).send();
        }
        return removeError(el);
};

var checkPass = function(el){
        if (el.isEmpty() || !el.ofLength(6, 20)) {
                return showError(el, 'Password must be at least 6 characters.');
        }
        //mpmetrics.track('password-complete', {'type': Tap.Vars.ref ? 'referral' : 'direct'});
        return removeError(el);
};

signupData.uname.addEvent('blur', checkUname.toHandler(this));
signupData.email.addEvent('blur', checkEmail.toHandler(this));
signupData.pass.addEvent('blur', checkPass.toHandler(this));

connectForm.getElement('button').addEvent('click', function(e){
        var self = this;
        if (noErrors()) {
                if (!connectForm.metricked) {
                        //mpmetrics.track('signup-forms-finished', {'type': Tap.Vars.ref ? 'referral' : 'direct'});
                        connectForm.metricked = true;
                }
                new Request({
                        url: '/AJAX/ajaz_sign_up.php',
                        data: {
                                uname: signupData.uname.get('value'),
                                fname: signupData.name.get('value'),
                                email: signupData.email.get('value'),
                                pass: signupData.pass.get('value'),
				joinType : join_type,
				joinValue: join_value,
                                lang: "English",
                                fid: 0,
                                signup_flag: 'signup_function();'
                        },
                        onRequest: function(){
                                $('connect-signup-actions').setStyle('display', 'none');
                                indic_msg.set('text', 'Signing you up..');
                                indic.setStyle('display', 'block');
                        },
                        onSuccess: function(){
                                indic_msg.set('text', 'Logging you in..');
                                window.location = window.location.toString().replace(server_uri, '');
/*                                mpmetrics.track('signup', {'success' : 'true', 'type': Tap.Vars.ref ? 'referral' : 'direct'}, function(){
                                        window.location = window.location.toString().replace('?logout=true', '');
                                });
*/
                    }
                }).send();
        } else {
                $$($H(signupData).getValues().filter(function(item){
                        return !item.retrieve('passed');
                })).fireEvent('blur', [e]);
        }
});

this.on_connect = function(e){
        var api = FB.Facebook.apiClient;
        var uid = api.get_session().uid;
        api.users_getInfo(uid, 'username, name, pic_square', function(data){
                if (!data) return null;
                data = data.pop();
		

                $('connect-signup-name').set('text', data.name);
                signupData.name.set('value', data.name);
		$('connect-signup').adopt(new Element('img', { 'src': data.pic_square } ));
		$('connect-signup').adopt(new Element('p', { 'html': data.name + ', you will soon be on tap!' , styles:  { 'color' : 'black' , 'font-weight' : 'bold' } } ));

                if (data.username) signupData.uname.set('value', data.username).fireEvent('blur', {
                        stop: $empty,
                        preventDefault: $empty,
                        stopPropagation: $empty
                });

                curtain.set('styles', {
                        'opacity': '.3',
                        'display': 'block',
                        'height': window.getSize().x,
                        'width': window.getWidth()
                });
                connectForm.set('styles', {
                        left: (window.getSize().x / 2) - 265,
                        'display': 'block'
                });

                signupData.uname.focus();

                /*
                var form = $('signup-form');
                if (data.username) form.getElement('input[name="user"]').set('value', data.username);
                form.getElement('input[name="name"]').set('value', data.name);
                form.getElement('input[name="email"]').focus();
                form.fireEvent('submit');
                form.highlight();
                $('connect-guide').setStyle('display', 'block');
                */
                //mpmetrics.track('fbconnect-complete', {'type': Tap.Vars.ref ? 'referral' : 'direct'});
                // window.fbconnected = true;
        });
};

this.click_connect = function(){
        //mpmetrics.track('fbconnect-click', {'type': Tap.Vars.ref ? 'referral' : 'direct'});
};

FB.init("e31fd60bbbc576ac7fd96f69215268d0", "/xd_receiver.htm");

})();
