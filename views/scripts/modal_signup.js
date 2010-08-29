(function(){

var curtain = $('curtain');
var modalForm = $('modal-signup');
var indic = modalForm.getElement('span.indicator');
var indic_msg = indic.getElement('span.indic-msg');
$('modal_signup-cancel').addEvent('click', function(e){
    e.stop();
	var myEffects = new Fx.Morph('modal-signup', {duration: 500, transition: Fx.Transitions.Sine.easeOut});
	
	myEffects.chain( function() {
    	$$(curtain, modalForm).setStyle('display', 'none')
	});

	myEffects.start({
		'opacity': '0'
	});
});

var signupData = {
        name: modalForm.getElement('input[name="name"]'),
        uname: modalForm.getElement('input[name="uname"]'),
        email: modalForm.getElement('input[name="email"]'),
        pass: modalForm.getElement('input[name="pass"]'),
        facebook: modalForm.getElement('input[name="facebook"]'),
        fb_fname: modalForm.getElement('input[name="fb_fname"]'),
        fb_lname: modalForm.getElement('input[name="fb_lname"]')
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
	el.value = el.value.toLowerCase();
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

modalForm.getElement('button').addEvent('click', function(e){
        var self = this;
        if (noErrors()) {
				/*
                if (!modalForm.metricked) {
                        //mpmetrics.track('signup-forms-finished', {'type': Tap.Vars.ref ? 'referral' : 'direct'});
                        modalForm.metricked = true;
                }
				*/
                var facebook = signupData.facebook.get('value');
                if (facebook) {
                    var fname = signupData.fb_fname.get('value');
                    var lname = signupData.fb_lname.get('value');
                } else {
                    var name = signupData.name.get('value');
                    var fname = name.substring(0, name.indexOf(' '));
                    var lname = name.substring(name.indexOf(' '), name.length);
                }

                new Request({
                        url: '/AJAX/ajaz_new_sign_up.php',
                        data: {
                                uname: signupData.uname.get('value'),
                                email: signupData.email.get('value'),
                                pass: signupData.pass.get('value'),
                                fname: fname,
                                lname: lname,
                                facebook: facebook, 
								joinType : join_type,
								joinValue: join_value,
                                lang: "English",
                                fid: 0,
                                signup_flag: 'signup_function();'
                        },
                        onRequest: function(){
                                $('modal-signup-actions').setStyle('display', 'none');
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

this.show_signup = function(e){

	/*
	if (true) signupData.uname.set('value',"TESTING").fireEvent('blur', {
			stop: $empty,
			preventDefault: $empty,
			stopPropagation: $empty
	});
	*/

	curtain.set('styles', {
			'opacity': '0.7',
			'display': 'block',
			'background-color': 'black',
			'height': '100%',
			'width': '100%' 
	});
	modalForm.set('styles', {
			'opacity': '0',
			'border': '7px solid black',
			'-moz-border-radius': '5px 5px 5px 5px',
			'display': 'block'
	});
	var myEffects = new Fx.Morph('modal-signup', {duration: 1000, transition: Fx.Transitions.Sine.easeOut});
	myEffects.start({
		'opacity': '1'
	});

	signupData.uname.focus();
};

this.click_connect = function(){
        //mpmetrics.track('fbmodal-click', {'type': Tap.Vars.ref ? 'referral' : 'direct'});
		alert("asd");
};

})();
