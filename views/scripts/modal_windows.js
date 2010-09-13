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
            'modal.show.sign-notify': function() { self.show('modal-sign-notify') },
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
        var self = this;
        var modalForm = this.modalForm = $('modal-signup');
        this.fb_fname = modalForm.getElement('input[name="fb_fname"]');
        this.fb_lname = modalForm.getElement('input[name="fb_lname"]');
        
        var signupData = this.signupData = {
            name: modalForm.getElement('input[name="name"]'),
            uname: modalForm.getElement('input[name="uname"]'),
            email: modalForm.getElement('input[name="email"]'),
            pass: modalForm.getElement('input[name="pass"]'),
            fb: modalForm.getElement('input[name="fb_connect"]')
        };

        signupData.uname.addEvent('blur', this.checkUname.toHandler(this));
        signupData.email.addEvent('blur', this.checkEmail.toHandler(this));
        signupData.pass.addEvent('blur', this.checkPass.toHandler(this));
        signupData.name.addEvent('blur', this.checkName.toHandler(this));
        modalForm.getElement('button').addEvent('click', this.submitForm.toHandler(this)); 
        modalForm.getElement('input[name="fb_connect"]').addEvent('click', this.toggleFB.bind(this));

        this.subscribe({
            'facebook.logged_in': this.showFBCheckbox.bind(this),
            'facebook.logged_out': this.showFBButton.bind(this)
        });

        $$('a.signup-button', 'button.signup-button').addEvent('click', function () {
            self.publish('modal.show.signup', []);
        });
    },

    toggleFB: function(e) {
        if (this.modalForm.getElement('p#real_name').getStyle('display') == 'none') {
            this.modalForm.getElement('p#real_name').setStyle('display', 'block');
            this.signupData.name.addClass('passed').store('passed', false).removeClass('passed');
        } else {
            this.modalForm.getElement('p#real_name').setStyle('display', 'none');
            this.signupData.name.addClass('passed').store('passed', true);
        }

    },

    showFBCheckbox: function() {
        this.modalForm.getElement('input[name="facebook"]').set('value', 1);
        $('fb-login-button').setStyle('display', 'none');
        $('fb-checkbox').setStyle('display', 'inline-block');
        this.modalForm.getElement('p#real_name').setStyle('display', 'none');
        this.signupData.name.addClass('passed').store('passed', true);
        this.signupData.fb.set('checked', 1);
    },

    showFBButton: function() {
        this.modalForm.getElement('input[name="facebook"]').set('value', 0);
        $('fb-login-button').setStyle('display', 'inline-block');
        $('fb-checkbox').setStyle('display', 'none');
        this.modalForm.getElement('p#real_name').setStyle('display', 'block');
        this.signupData.name.addClass('passed').store('passed', false).removeClass('passed');
        this.signupData.fb.set('checked', 0);
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
        var self = this;

        if (el.isEmpty() || !el.ofLength(4, 20)) {
            return self.showError(el, 'Username must be at least 4 characters');
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
        var self = this;

        el.value = el.value.toLowerCase();
        if (el.isEmpty() || !el.isEmail()) {
            return self.showError(el, 'Please enter a valid email.');
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
        if (el.isEmpty() || !el.ofLength(6, 20)) {
                return this.showError(el, 'Password must be at least 6 characters.');
        }
        return this.removeError(el);
    },

    checkName: function(el){
         if (el.isEmpty() || !el.ofLength(2, 250)) {
                return this.showError(el, 'Please, enter your name');
        }
        return this.removeError(el);
    },

    checkFacebook: function() {
        var self = this;
        var el = this.signupData.fb;

        this.removeError(el);
        new Request({
            url: '/AJAX/facebook.php',
            method: 'POST',
            data: {'action': 'check'},
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (response.success){
                    self.fb_fname.set('value', response.data.fname);
                    self.fb_lname.set('value', response.data.lname);
                } else {
                    if (response.reason == 'already binded')  {
                        self.showError(el, 'your account already binded to facebook');
                    } else if (response.reason == 'binded by someone') {
                        self.showError(el, 'facebook account you logged in is already binded by someone else');
                    } else { 
                        self.showError(el, 'something went wrong durning account binding');
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
        var facebook = this.modalForm.getElement('input[name="facebook"]').get('value');

        if (this.signupData.fb.getProperty('checked'))
            this.checkFacebook();
        else
            this.signupData.fb.store('passed', true);
        if (this.noErrors()) {
            var facebook = facebook
                && this.signupData.fb.getProperty('checked');
            if (facebook) {
                var fname = this.fb_fname.get('value');
                var lname = this.fb_lname.get('value');
            } else {
                var name = this.signupData.name.get('value');
                var fname = name.substring(0, name.indexOf(' '));
                var lname = name.substring(name.indexOf(' ')+1, name.length);
            }

            new Request({
                url: '/AJAX/ajaz_new_sign_up.php',
                data: {
                    uname: this.signupData.uname.get('value'),
                    email: this.signupData.email.get('value'),
                    pass: this.signupData.pass.get('value'),
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
                    self.modalForm.getElement('span.modal-actions').setStyle('display', 'none');
                    indic_msg.set('text', 'Signing you up..');
                    indic.setStyle('display', 'block');
                },
                onSuccess: function(){
                    indic_msg.set('text', 'Logging you in..');
                    if (facebook) {
                        self.publish('modal.show.suggestions', [ function () {
                            window.location.reload();
                        }]);
                    } else 
                        window.location.reload();
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

        var name = $('you').getElement('strong').get('text');

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
            url: '/AJAX/facebook.php',
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
            url: '/AJAX/group_suggest.php',
            data: {
                action: 'get'    
            },
            onRequest: function() {
                list.set('html', '');
                indic.setStyle('display', 'block');
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

                var allBox = list.getElement('input#sugg_all'),
                    boxes = list.getElements('input[name="suggest"]');
                allBox.addEvent('click', function () {
                    var state = allBox.get('checked');
                    boxes.each(
                        function (i) {
                            i.set('checked', state);
                        });
                });

                boxes.addEvent('click', function (i) {
                     if (!i.target.get('checked'))
                        allBox.set('checked', false);
                     else if ( boxes.every(function (i) { return i.get('checked'); }) )
                        allBox.set('checked', true);
                });
            }
        }).send();
    },

    send: function () {
        var indic = this.form.getElement('p.modal-menu').getElement('span.indicator'),
            list = this.suggest.getElement('ul'),
            gids = [],
            self = this;

        list.getElements('input[name="suggest"]').each( function (i) {
            if (i.get('checked'))
                gids.push(i.get('value')*1);
        });

        if (!gids) {
            this.publish('modal.hide', []);
        }
         
        new Request({
           url: '/AJAX/join_group.php', 
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
