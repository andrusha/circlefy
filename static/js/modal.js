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
            'modal.show.group-edit': function() { self.show('modal-group-edit') },
            'modal.show.user-edit': function() { self.show('modal-user-edit') },
            'modal.show.image-display': function(embed, sizes) {
                _modal.image_preview.show(embed, sizes);
            },
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

        //close modal on Esc
        var a = new Keyboard({
            events: {
                'esc': function () {
                    this.publish('modal.hide', [false]);
                }.bind(this)
            }
        });

        Object.each({'button.signup-button': 'modal.show.signup', 
                     'button.login-button':  'modal.show.login',
                     'li.suggestions':       'modal.show.suggestions', 
                     'a#access':             'modal.show.sign-login', 
                     'span#edit_circle > a': 'modal.show.group-edit',
                     'li.settings':          'modal.show.user-edit'},
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

        var wsize = $(window).getSize();
        var msize = $(modalForm).getSize();
        var top = ( wsize.y - msize.y ) / 2;
        modalForm.set('styles', {
            'top': (top > 0 ? top : 0) + "px",
            'left': ( wsize.x - msize.x ) / 2 + "px",
            'margin': '0'
        });
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
        var form   = this.form   = $('signup-form'),
            fields = this.fields = {},
            inputs = form.getElements('input:not([type="submit"]), #fb-login-button');

       inputs.each( function (el) {
           fields[el.name] = el;
       });

       form.validator = new Form.Validator(form, {
            fieldSelectors: 'input,#fb-login-button',
            onFormValidate: this.submitForm.bind(this)
       });

        new CirTooltip({
            hovered:  inputs,
            template: 'error-tooltip',
            position: 'upperRight',
            sticky:   true
        });
    },

    submitForm: function(passed, form, e) {
        e.stop();
        if (!passed)
            return;

        var self = this;
        var indic = this.form.getElement('span.indicator');
        var indic_msg = indic.getElement('span.indic-msg');

        new Request({
            url: '/AJAX/user/facebook',
            data: {
                action: 'create',
                uname:  this.fields.uname.value,
                email:  this.fields.email.value,
                pass:   this.fields.pass.value
            },
            onRequest: function(){
                self.form.getElement('span.modal-actions').setStyle('display', 'none');
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

                list.getElements('li').addEvent('click', function (e) {
                    if (e.target != this)
                        return;
                    
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

/*
    Modal window for big image preview
*/
_modal.image_preview = _tap.register({
    show: function(embed, sizes) {
        var modalForm = $('modal-image-display'),
            curtain   = $('curtain');
        curtain.set('styles', {
            'opacity': '0.7',
            'display': 'block'
        });

        var container = modalForm.getElement('.img-container');
        container.innerHTML = embed;
        container.getElement('img').set('styles', {'width': sizes[0]+'px', 'height': sizes[1]+'px'});

        curtain.addClass('show');
        modalForm.set('styles', {
            'opacity': '0'
        });
        modalForm.addClass('show');

        var myEffects = new Fx.Morph(modalForm, {duration: 1000, transition: Fx.Transitions.Sine.easeOut});

        myEffects.start({
            'opacity': '1'
        });

        var wsize = $(window).getSize();
        var msize = $(modalForm).getSize();
        var top = ( wsize.y - msize.y ) / 2;
        modalForm.set('styles', {
            'top': (top > 0 ? top : 0) + "px",
            'left': ( wsize.x - msize.x ) / 2 + "px",
            'margin': '0'
        });
    }
});
