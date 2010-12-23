/*global Class, _tap, $$, Keyboard, Fx, Form, Request, window, CirTooltip, _template, Elements, _notifications, document, _vars, _tapbox*/

/*
    Generic modal windows handler
*/
var _modal = _tap.register({
    init: function () {
        var self = this;

        var curtain = this.curtain = $('curtain');
        var container = this.container = $('modal-container');
        this.next = '';
        this.facebooked = false;

        $$('a.modal-cancel').addEvent('click', function (e) {
            e.stop();
            self.hide();
        });
        
        this.subscribe({
            'modal.show.signup': function () { self.show('modal-signup'); },
            'modal.show.login': function () { self.show('modal-login'); },
            'modal.show.sign-notify': function () { self.show('modal-sign-notify'); },
            'modal.show.sign-login': function () { self.show('modal-sign-login'); },
            'modal.show.group.create': function () { self.show('modal-group-create'); },
            'modal.show.group-edit': function () { self.show('modal-group-edit'); },
            'modal.show.edit-members': function () { self.show('modal-edit-members'); },
            'modal.show.user-edit': function () { self.show('modal-user-edit'); },
            'modal.show.post-tap': function () { self.show('modal-post-tap'); },

            'modal.show.image-display': function (embed, sizes) {
                _modal.image_preview.show(embed, sizes);
            },
            'modal.show.facebook-status': function (cid, symbol) {
                self.show('modal-facebook-status');
                _modal.facebook.show(cid, symbol);
            },
            'modal.show.suggestions': function (chain) {
                self.show('modal-channel-suggestion');
                _modal.suggestions.show(chain);
            },
            'modal.show.group-email-auth': function () {
                self.show('modal-email-auth');
                _modal.group_email_auth.show();
            },
            'modal.show.first-tap': function (data) {
                self.show('modal-first-tap'); 
                _modal.first_tap.show(data);
            },
            'modal.show.facebook': function () { 
                if (self.next && self.facebooked) {
                    self.publish(self.next);
                } else {
                    self.show('modal-facebook');
                    _modal.facebook.show();
                }
            },
            'modal.hide': this.hide.bind(this),
            'facebook.logged_in':  function () { 
                this.facebooked = true;
            }.bind(this), 
            'facebook.logged_out': function () {
                this.facebooked = false;
            }.bind(this)
        });

        //close modal on Esc
        var a = new Keyboard({
            events: {
                'esc': function () {
                    this.publish('modal.hide', [false]);
                }.bind(this)
            }
        });

        $$('button.signup-button').addEvent('click', function (e) {
            e.stop();
            self.next = 'modal.show.signup';
            self.publish('modal.show.facebook');
        });
        
        $$('button.login-button').addEvent('click', function (e) {
            e.stop();
            self.next = 'modal.show.login';
            self.publish('modal.show.facebook');
        });

        Object.each({'li.suggestions':       'modal.show.suggestions', 
                     'a#access':             'modal.show.sign-login',
                     'a.login-signup':       'modal.show.sign-login',
                     'button#edit_circle':   'modal.show.group-edit',
                     'li.settings':          'modal.show.user-edit',
                     'button#edit_members':  'modal.show.edit-members',
                     'button.main-post-button': 'modal.show.post-tap'},
           function (event, selector) {
                $$(selector).addEvent('click', function (e) {
                    e.stop();
                    this.publish(event, []);
                }.bind(this));
            }.bind(this));
    },
    
    /*
        Shows modal window
    */
    show: function (name, ignore) {
        var self = this;
        //this ugly thing is to chain modal windows together
        if ($$('div.modal-window.show').length && !ignore) {
            this.hide(true);
            (function () { 
                self.show(name, true);
            }).delay(510);
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

        window.scrollTo(0, 0);

        var wsize = $(window).getSize();
        var msize = $(modalForm).getSize();
        var top = (wsize.y - msize.y) / 2;
        modalForm.set('styles', {
            'top': (top > 0 ? top : 0) + "px",
            'left': (wsize.x - msize.x) / 2 + "px",
            'margin': '0'
        });
    },

    /*
        Hides any visible modal window
    */
    hide: function (keep_curtain) {
        var self = this,
            modalForm = $$('div.modal-window.show')[0];

        keep_curtain = !!keep_curtain;

        if (!modalForm) {
            return;
        }
        
        $$('.tooltip-error').tween('opacity', 0);
        
        var myEffects = new Fx.Morph(modalForm, {duration: 500, transition: Fx.Transitions.Sine.easeOut});
            
        myEffects.chain(function () {
            modalForm.removeClass('show');
            if (!keep_curtain) {
                self.curtain.removeClass('show');
                self.curtain.setStyle('display', 'none');
            }
        });

        myEffects.start({
            'opacity': '0'
        });
    }
});

_modal.facebook = _tap.register({
    init: function () {
        this.showed = false;

        this.subscribe({
            'facebook.logged_in':  function () { 
                if (this.showed) {
                    this.publish(_modal.next);
                }
            }.bind(this) 
        });
    },

    show: function () {
        this.showed = true;
    }
});

_modal.signup = _tap.register({

    init: function () {
        var form   = this.form   = $('signup-form'),
            fields = this.fields = {},
            inputs = form.getElements('input:not([type="submit"]), .fb-login-button');

        inputs.each(function (el) {
            fields[el.name] = el;
        });

        form.validator = new Form.Validator(form, {
            fieldSelectors: 'input,.fb-login-button',
            onFormValidate: this.submitForm.bind(this)
        });

        new CirTooltip({
            hovered:  inputs,
            template: 'error-tooltip',
            position: 'centerTop',
            sticky:   true
        });
    },

    submitForm: function (passed, form, e) {
        e.stop();
        if (!passed) {
            return;
        }

        var self = this,
            indic = this.form.getElement('span.indicator'),
            indic_msg = indic.getElement('span.indic-msg');

        new Request({
            url: '/AJAX/user/facebook',
            data: {
                action: 'create',
                uname:  this.fields.uname.value,
                pass:   this.fields.pass.value
            },
            onRequest: function () {
                self.form.getElement('span.modal-actions').setStyle('display', 'none');
                indic_msg.set('text', 'Signing you up..');
                indic.setStyle('display', 'block');
            },
            onSuccess: function () {
                var response = JSON.decode(this.response.text);
                if (response.success) {
                    indic_msg.set('text', 'Logging you in..');
                    self.publish('modal.show.suggestions', [ function () {
                        window.location.search = 'firsttime';
                    }]);
                } else {
                    indic_msg.text = 'Error durning account creation';
                }
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
        var indic = this.suggest.getElement('span.indicator'),
            list = this.suggest.getElement('ul'),
            fail = this.suggest.getElement('span.fail');

        if (chain) {
            this.chain = chain;
        }

        new Request.JSON({
            url: '/AJAX/group/suggest',
            data: {
                action: 'get'    
            },
            onRequest: function () {
                list.set('html', '');
                indic.setStyle('display', 'block');
                fail.setStyle('display', 'none');
            },
            onFailure: this.fail.bind(this),
            onException: this.fail.bind(this),
            onSuccess: this.success.bind(this)
        }).send();
    },

    unlock: function (cont) {
        var but = this.form.getElement('button');
        but.removeProperty('disabled');

        if (cont) {
            but.innerHTML = but.getData('alt');
        }
    },

    fail: function () {
        this.unlock(true);

        var fail = this.suggest.getElement('span.fail');
        fail.setStyle('display', 'block');
    },

    success: function (response) {
        this.unlock(); 

        var indic = this.suggest.getElement('span.indicator'),
            list = this.suggest.getElement('ul'),
            fail = this.suggest.getElement('span.fail');

        indic.setStyle('display', 'none');

        if (!response.success || !response.data.length) {
            return this.fail();
        }

        var items = _template.parse('suggestions', response.data);
        items = Elements.from(items);
        items.inject(list);

        if (list.getSize().y > 300) {
            this.suggest.setStyle('height', 300);
        }

        var allBox = list.getElement('input[name="suggest_all"]'),
            boxes  = list.getElements('input[name="suggest"]');

        list.getElements('li').addEvent('click', function (e) {
            if (e.target != this) {
                return;
            }
            
            var inp = this.getElement('input');
            inp.checked = !inp.checked;
            inp.fireEvent('change', e);
        });

        allBox.addEvent('change', function (e) {
            e.stop();

            var state = allBox.checked;
            this.getParent().toggleClass('selected');
            boxes.each(
                function (i) {
                    i.set('checked', state);
                });
        });

        boxes.addEvent('change', function (e) {
            e.stop();

            this.getParent().toggleClass('selected');

            allBox.checked = false;
            allBox.getParent().removeClass('selected');

            if (boxes.every(function (i) { 
                    return i.get('checked'); 
                })) {
                allBox.checked = true;
                allBox.getParent().addClass('selected');
            }
        });
    },

    send: function () {
        var indic = this.form.getElement('p.modal-menu').getElement('span.indicator'),
            list = this.suggest.getElement('ul'),
            gids = [],
            self = this;

        list.getElements('input[name="suggest"]:checked').each(function (i) {
            gids.push(i.value.toInt());
        });

        if (!gids) {
            this.publish('modal.hide', []);
        }
         
        new Request({
            url: '/AJAX/follow', 
            data: {
                type: 'bulk',
                id: gids,
                state: 1
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

    init: function () {
        var self = this,
            form = this.form = $('taplogin');

        form.user = form.getElement('input[name="uname"]');
        form.pass = form.getElement('input[name="pass"]');
        form.btn  = form.getElement('input[type="submit"]');

        this.subscribe({
            'facebook.logged_in':  function () { 
                $$('#fb-faces,#fb-signup-faces,#facebook-button').removeClass('hidden');
                $$('#fb-no-faces,#fb-signup-no-faces').addClass('hidden');
            }.bind(this), 
            'facebook.logged_out': function () {
                $$('#fb-faces,#fb-signup-faces,#facebook-button').addClass('hidden');
                $$('#fb-no-faces,#fb-signup-no-faces').removeClass('hidden');
            }.bind(this)
        });

        $$('#login-button').addEvent('click', function (e) {
            e.stop(); 

            if (form.user.value.isEmpty()) {
                form.user.addClass('error');
                return form.user.focus();
            } else {
                form.user.removeClass('error');
            }

            if (form.pass.value.isEmpty()) {
                form.pass.addClass('error');
                return form.pass.focus();
            } else {
                form.pass.removeClass('error');
            }
            
            self.auth(form.user.value, form.pass.value, 'user');
        });

        $$('#facebook-button').addEvent('click', function (e) {
            e.stop(); 

            self.auth(form.user.value, form.pass.value, 'facebook');
        });
    },

    auth: function (user, pass, type) {
        var el = $('login-button'),
            position = el.getPosition();

        position = [position.x + 63, position.y + 25];

        _notifications.alert('Please wait', "We are processing your request... <img src='static/spinner.gif'>",
            { color: 'black',  duration: 10000, position: position});
        var executing = _notifications.items.getLast();

        var data = {'user': user,
                    'pass': pass,
                    'type': type};

        new Request({
            url: '/AJAX/user/login',
            data: data,
            onSuccess: function () {
                var response = JSON.decode(this.response.text);
                _notifications.remove(executing);

                if (response.status == 'REGISTERED') {
                    _notifications.alert('Success', 'Welcome back.  Logging you in...',
                        {color: 'darkgreen', duration: 2000, position: position});

                    (function () { 
                        document.location.reload(); 
                    }).delay(2000, this, 'login');
                } else if (response.status == 'NOT_REGISTERED') {
                    _notifications.alert('Error', 'Sorry, there is no user with this username and password, please try again',
                        {color: 'darkred', duration: 5000, position: position});
                }

            }
        }).send();
    }
});

/*
    Modal window for big image preview
*/
_modal.image_preview = _tap.register({
    show: function (embed, sizes) {
        var modalForm = $('modal-image-display'),
            curtain   = $('curtain');
        curtain.set('styles', {
            'opacity': '0.7',
            'display': 'block'
        });

        var container = modalForm.getElement('.img-container');
        container.innerHTML = embed;
        container.getElement('img').set('styles', {'width': sizes[0] + 'px', 'height': sizes[1] + 'px'});

        curtain.addClass('show');
        modalForm.set('styles', {
            'opacity': '0'
        });
        modalForm.addClass('show');

        var myEffects = new Fx.Morph(modalForm, {duration: 1000, transition: Fx.Transitions.Sine.easeOut});

        myEffects.start({
            'opacity': '1'
        });

        var wsize = $(window).getSize(),
            msize = $(modalForm).getSize(),
            top = (wsize.y - msize.y) / 2;

        modalForm.set('styles', {
            'top': (top > 0 ? top : 0) + "px",
            'left': (wsize.x - msize.x) / 2 + "px",
            'margin': '0'
        });
    }
});

/*
    Group Email Auth
*/

_modal.group_email_auth = _tap.register({
    show: function () {
        var self   = this,
              form = this.form = $('email-auth-form'),
              resp = $('auth-response');
          form.gid = $('email-auth-gid');
        form.email = $('email-auth-email');

        if (!form || !resp) {
            return;
        }
        
        form.validator = new Form.Validator(form, {
            fieldSelectors: '#email-auth-email'
        });

        new CirTooltip({
            hovered:  form.email,
            template: 'error-tooltip',
            position: 'centerTop',
            sticky:   true
        });

        resp.addClass('hidden');
        form.removeClass('hidden');
        
        form.addEvent('submit', function (e) {
            e.stop();
            var data = {'gid': form.gid.get('value'), 
                        'email': form.email.get('value')};

            new Request.JSON({
                url: '/AJAX/group/join',
                onSuccess: function (response) {
                    if (response.success) {
                        form.addClass('hidden');
                        resp.removeClass('hidden');
                        resp.getElement('p').innerHTML = 'An email was sent to the address you provided, click on the link to join this circle.';
                        $('auth-email-close').addEvent('click', function () {
                            _modal.publish('modal.hide', [false]);
                        });
                        return;
                    } else {
                        form.email.addClass('validation-failed');
                        form.email.fireEvent('showCustomTip', [{content: response.reason}]);
                        
                    }
                    
                }.bind(this)
            }).post(data);
            
        });
    }
});


/*
    First Tap modal
*/
_modal.first_tap = _tap.register({
    show: function (data) {
        var self     = this,
            form     = this.form = $('firsttapform');

        form.ctx = $('circle-context');
        form.gid = $('first-tap-gid');
        
        form.validator = new Form.Validator(form, {
            fieldSelectors: '#circle-context'
        });
        
        // TODO: fix error tooltip
        new CirTooltip({
            hovered:  form.ctx,
            template: 'error-tooltip',
            position: 'centerTop',
            sticky:   true
        });
        
        form.removeEvents('submit');
        form.addEvent('submit', function (e) {
            e.stop();
            data = {'gid': form.gid.get('value'), 'context': form.ctx.get('value')};
            new Request.JSON({
                url: '/AJAX/group/context',
                onSuccess: function (response) {
                    if (response.success) {
                        _vars.feed.first_tap = 0;
                        _modal.publish('modal.hide', [false]);

                        _tapbox.form.fireEvent('submit', {'stop': function () {}, 'preventDefault': function () {} });
                    }
                }.bind(this)
            }).post(data);
            
        });
    }
});
