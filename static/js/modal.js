/*
    Generic modal windows handler
*/
var _modal = _tap.register({
    init: function() {
        var self = this;

        var curtain = this.curtain = $('curtain');
        var container = this.container = $('modal-container');

        $$('a.modal-cancel').addEvent('click', function(e) {
            e.stop();
            self.hide();
        });

        this.subscribe({
            'modal.show.signup': function() { self.show('modal-signup') },
            'modal.show.login': function() { self.show('modal-login') },
            'modal.show.sign-notify': function() { self.show('modal-sign-notify') },
            'modal.show.sign-login': function() { self.show('modal-sign-login') },
            'modal.show.facebook-status': function(cid, symbol) {
                self.show('modal-facebook-status');
                _modal.facebook.show(cid, symbol);
            },
            'modal.show.suggestions': function (chain) {
                self.show('modal-channel-suggestion');
                _modal.suggestions.show(chain);
            },
            'modal.hide': this.hide.bind(this)
        });

        var a = new Keyboard({
            events: {
                'esc': function () {
                    this.hide(false);
                }.bind(this)
            }
        });
    },
    
    /*
        Shows modal window
    */
    show: function(name, ignore) {
        var self = this;
        //this ugly thing is to chain modal windows together
        if ($$('div.modal-window.show').length && !ignore) {
            this.hide(true);
            (function () { self.show(name, true)}).delay(510);
            return;
        }

        var modalForm = $(name);
        this.curtain.set('styles', {
            'opacity': '0.7',
            'display': 'block'
        });

        this.curtain.addClass('show');
        modalForm.set('styles', {
            'opacity': '0'
        });
        modalForm.addClass('show');

        var myEffects = new Fx.Morph(modalForm, {duration: 1000, transition: Fx.Transitions.Sine.easeOut});

        myEffects.start({
            'opacity': '1'
        });

        window.scrollTo(0,0);
    },

    /*
        Hides any visible modal window
    */
    hide: function(keep_curtain) {
        var self = this;

        if (!keep_curtain)
            var keep_curtain = false;

        var modalForm = $$('div.modal-window.show')[0];
        if (!modalForm)
            return;

        var myEffects = new Fx.Morph(modalForm, {duration: 500, transition: Fx.Transitions.Sine.easeOut});
            
        myEffects.chain( function() {
            modalForm.removeClass('show');
            if (!keep_curtain) {
                self.curtain.removeClass('show');
                self.curtain.setStyle('display', 'none')
            }
        });

        myEffects.start({
            'opacity': '0'
        });
    }
});

_modal.signup = _tap.register({

    init: function() {
        var modalForm = this.modalForm = $('modal-signup');
        this.fb_fname = modalForm.getElement('input[name="fb_fname"]');
        this.fb_lname = modalForm.getElement('input[name="fb_lname"]');
        
        var signupData = this.signupData = {
            uname: modalForm.getElement('input[name="uname"]'),
            email: modalForm.getElement('input[name="email"]'),
            pass: modalForm.getElement('input[name="pass"]'),
        };

        signupData.uname.addEvent('blur', this.checkUname.toHandler(this));
        signupData.email.addEvent('blur', this.checkEmail.toHandler(this));
        signupData.pass.addEvent('blur', this.checkPass.toHandler(this));
        modalForm.getElement('button').addEvent('click', this.submitForm.toHandler(this)); 

        $$('button.signup-button').addEvent('click', function () {
            this.publish('modal.show.signup', []);
        }.bind(this));

        $$('button.login-button').addEvent('click', function () {
            this.publish('modal.show.login', []);
        }.bind(this));

        $$('li.suggestions').addEvent('click', function () {
            this.publish('modal.show.suggestions', []);
        }.bind(this));

        $$('a#access').addEvent('click', function () {
            this.publish('modal.show.sign-login', []);
        }.bind(this));
    },

    showError: function(el, msg){
        el.store('passed', false).removeClass('passed').addClass('error');
        el.getParents('p')[0].getNext('p.guide').set('text', msg).setStyle('display', 'block');
    },

    removeError: function(el){
        el.store('passed', true).removeClass('error').addClass('passed');
        el.getParents('p')[0].getNext('p.guide').setStyle('display', 'none');
    },

    noErrors: function(){
        return !$H(this.signupData).getValues().map(function(item){
            return !!item.retrieve('passed');
        }).contains(false);
    },

    checkUname: function(el){
        var self = this,
            name = el.value;
        if (this.oldUname == name)
            return;
        this.oldUname = name;
        
        if (name.isEmpty() || !(name.length >= 4 && name.length < 20)) {
            return self.showError(el, 'Username must be at least 4 characters');
        } else {
            new Request({
                url: '/AJAX/user/check',
                data: {
                    type: 'uname',
                    val:   name
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
        return self.removeError(el);
    },

    checkEmail: function(el){
        var self = this,
            email = el.value;

        if (this.oldEmail == email)
            return;
        this.oldEmail = email;

        el.value = email.toLowerCase();
        if (email.isEmpty() || !(/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i).test(email)) {
            return self.showError(el, 'Please enter a valid email.');
        } else {
            new Request({
                url: '/AJAX/user/check',
                data: {
                    type: 'email',
                    val:   email 
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
        return self.removeError(el);
    },

    checkPass: function(el){
        if (el.value.isEmpty() || !(el.value.length >= 6 && el.value.length < 40)) {
                return this.showError(el, 'Password must be at least 6 characters.');
        }
        return this.removeError(el);
    },

    checkFacebook: function() {
        var self = this;
        var el = $('fb-login-button');

        this.removeError(el);
        new Request({
            url: '/AJAX/user/facebook',
            method: 'POST',
            data: {action: 'check'},
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (response.success){
                    $('modal-signup-name').text = response.data.fname;
                } else {
                    if (response.reason == 'no_fb')  {
                        self.showError(el, 'you must login into facebook before proceed');
                    } else if (response.reason == 'exists') {
                        self.showError(el, 'user with this facebook account already exists');
                    } else { 
                        self.showError(el, 'something went wrong durning account checking');
                    }
                }
            },
            async: false
        }).send();
    },

    submitForm: function(e) {
        var self = this;
        var indic = this.modalForm.getElement('span.indicator');
        var indic_msg = indic.getElement('span.indic-msg');
        var uname = this.signupData.uname.get('value');

        this.checkFacebook();

        if (this.noErrors()) {
            new Request({
                url: '/AJAX/user/facebook',
                data: {
                    action: 'create',
                    uname:  uname,
                    email:  this.signupData.email.get('value'),
                    pass:   this.signupData.pass.get('value')
                },
                onRequest: function(){
                    self.modalForm.getElement('span.modal-actions').setStyle('display', 'none');
                    indic_msg.set('text', 'Signing you up..');
                    indic.setStyle('display', 'block');
                },
                onSuccess: function(){
                    var response = JSON.decode(this.response.text);
                    if (response.success) {
                        indic_msg.set('text', 'Logging you in..');
                        self.publish('modal.show.suggestions', [ function () {
                            window.location.reload();
                        }]);
                    } else {
                        indic_msg.text = 'Error durning account creation';
                    }
                }
            }).send();
        } else {
            $$($H(this.signupData).getValues().filter(function(item){
                    return !item.retrieve('passed');
            })).fireEvent('blur', [e]);
        }
    },
});

/*
 * Facebook account integration
*/
_modal.facebook = _tap.register({
    init: function() {
        var form = this.form = $('modal-facebook-status'),
            status = this.status = form.getElement('input[name="status"]'),
            message = this.message = form.getElement('input[name="message"]');
        this.link = form.getElement('input[name="link"]');

        var preview = this.preview = $('fb_preview'),
            status_preview = this.status_preview = preview.getElement('span.fb_message'),
            message_preview = this.message_preview = preview.getElement('div.caption');

        var name = _vars.user.real_name; 

        preview.getElement('a.fb_name').set('text', name);

        form.getElement('button').addEvent('click', this.send.toHandler(this));
        status.addEvent('keyup', function() {
            status_preview.set('text', status.get('value'));
        });
        message.addEvent('keyup', function() {
            message_preview.set('text', message.get('value'));
        });
    },

    show: function(cid, symbol) {
        this.cid = cid;
        this.symbol = symbol;

        var status = ' leave new tap at '+symbol+' channel';
        this.status.set('value', status);
        this.status_preview.set('text', status);

        var msg = $('tid_'+cid).getElement('p.tap-body').get('text').strip();
        this.message.set('value', msg);
        this.message_preview.set('text', msg);
    },

    send: function() {
        var self = this;
        var indic = this.form.getElement('span.indicator');

        var message = this.status.get('value'),
            link = 'http://andrew.tap.info/tap/'+this.cid,
            name = 'Join discussion',
            caption = this.message.get('value');

        new Request({
            url: '/AJAX/user/facebook',
            data: {
                action: 'share',
                message: message,
                caption: caption,
                link: link,
                name: name,
            },
            onRequest: function() {
                self.form.getElement('span.modal-actions').setStyle('display', 'none');
                indic.setStyle('display', 'block');
            },
            onSuccess: function() {
                self.form.getElement('span.modal-actions').setStyle('display', 'block');
                indic.setStyle('display', 'none');
                self.publish('modal.hide', []);
            }
        }).send();
    }
});

/*
    Channels suggestion
*/
_modal.suggestions = _tap.register({
    init: function () {
        var self = this;

        this.suggest = $('suggestions');
        this.form = $('modal-channel-suggestion');

        //this is a callback function, called after all shit
        this.chain = function () {
            self.publish('modal.hide');
        };

        $$('a.suggestions').addEvent('click', function () {
            self.publish('modal.show.suggestions', []);
        });

        this.form.getElement('button').addEvent('click', this.send.toHandler(this));
    },

    show: function (chain) {
        var self = this,
            indic = self.suggest.getElement('span.indicator'),
            list = self.suggest.getElement('ul'),
            fail = self.suggest.getElement('span.fail');

        if (chain)
            this.chain = chain;

        new Request({
            url: '/AJAX/group/suggest',
            data: {
                action: 'get'    
            },
            onRequest: function() {
                list.set('html', '');
                indic.setStyle('display', 'block');
                fail.setStyle('display', 'none');
            },
            onSuccess: function() {
                indic.setStyle('display', 'none');

                var response = JSON.decode(this.response.text);
                if (response.success == 0 || response.data.length == 0) {
                    //we have no suggestions
                    fail.setStyle('display', 'block');
                    return;
                }

                var items = _template.parse('suggestions', response.data);
                items = Elements.from(items);
                items.inject(list);

                if (list.getSize().y > 300)
                    self.suggest.setStyle('height', 300);

                var allBox = list.getElement('input[name="suggest_all"]'),
                    boxes  = list.getElements('input[name="suggest"]');

                list.getElements('li').addEvent('click', function (i) {
                    this.getElement('input').fireEvent('click');
                });

                allBox.addEvent('click', function () {
                    var state = !allBox.checked;
                    allBox.checked = state;
                    this.getParent().toggleClass('selected');
                    boxes.each(
                        function (i) {
                            i.set('checked', state);
                        });
                });

                boxes.addEvent('click', function () {
                    this.checked = !this.checked;
                    this.getParent().toggleClass('selected');

                    allBox.checked = false;
                    allBox.getParent().removeClass('selected');

                    if ( boxes.every(function (i) { return i.get('checked'); }) ) {
                        allBox.checked = true;
                        allBox.getParent().addClass('selected');
                    }
                });
            }
        }).send();
    },

    send: function () {
        var indic = this.form.getElement('p.modal-menu').getElement('span.indicator'),
            list = this.suggest.getElement('ul'),
            gids = [],
            self = this;

        list.getElements('input[name="suggest"]:checked').each( function (i) {
            gids.push(i.value*1);
        });

        if (!gids) {
            this.publish('modal.hide', []);
        }
         
        new Request({
           url: '/AJAX/group/join', 
           data: {
               action: 'bulk',
               gids: gids
           },
           onRequest: function () {
               indic.setStyle('display', 'block');
           },
           onSuccess: function () {
               indic.setStyle('display', 'none');
               var response = JSON.decode(this.response.text);
               self.chain();
           }
        }).send();
    }
});

/*
    Modal window for login-logout
*/
_modal.login = _tap.register({

    init: function() {
        var self = this,
            form = this.form = $('taplogin');
        this.facebook = false;

        form.user = form.getElement('input[name="uname"]');
        form.pass = form.getElement('input[name="pass"]');
        form.fb   = form.getElement('input[name="facebook"]');
        form.btn  = form.getElement('input[type="submit"]');

        this.subscribe({
            'facebook.logged_in':  function () { 
                this.facebook = true; 
                this.form.fb.checked = true;
                this.fbToggle();
                this.form.fb.getParent().removeClass('hidden');
            }.bind(this), 
            'facebook.logged_out': function () {
                this.facebook = false;
                this.form.fb.checked = false;
                this.fbToggle();
                this.form.fb.getParent().addClass('hidden');
            }.bind(this)
        });

        form.addEvent('submit', function(e) {
            e.stop();
            var type = 'user';
            if (self.facebook && self.form.fb.checked) {
                type = 'facebook';
            } else {
                if (form.user.value.isEmpty()) {
                    form.user.addClass('error');
                    return form.user.focus();
                } else
                    form.user.removeClass('error');

                if (form.pass.value.isEmpty()) {
                    form.pass.addClass('error');
                    return form.pass.focus();
                } else
                    form.pass.removeClass('error');
            }
            self.auth(form.user.value, form.pass.value, type);
        });

        form.fb.addEvent('click', this.fbToggle.bind(this));
    },

    fbToggle: function (e) {
        var state = this.form.fb.checked;
        this.form.fb.checked = state;
        this.form.user.disabled = state;
        this.form.pass.disabled = state;
    },

    auth: function(user, pass, type) {
        var el = $('login-button');
        var position = el.getPosition();
        position = [position.x+63, position.y+25];

        _notifications.alert('Please wait', "We are processing your request... <img src='/images/ajax_loading.gif'>",
            { color: 'black',  duration: 10000, position: position});
        var executing = _notifications.items.getLast();

        data = {'user': user,
                'pass': pass,
                'type': type};
        new Request({
            url: '/AJAX/user/login',
            data: data,
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                _notifications.remove(executing);

                if(response.status == 'REGISTERED') {
                    _notifications.alert('Success', 'Welcome back.  Logging you in...',
                        {color: 'darkgreen', duration: 2000, position: position});

                    (function () { document.location.reload() }).delay(2000, this, 'login');
                } else if (response.status == 'NOT_REGISTERED') {
                    _notifications.alert('Error', 'Sorry, there is no user with this username and password, please try again',
                        {color: 'darkred', duration: 5000, position: position});
                }

            }
        }).send();
    }
});
