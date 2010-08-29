var _login = _tap.register({

    init: function() {
        var self = this;

        var form = $('taplogin'),
            user = form.getElement('input[name="uname"]'),
            pass = form.getElement('input[name="pass"]');

        this.form = form;

        this.subscribe({
            'facebook.logged_in': this.fb_login.bind(this),
            'facebook.logged_out': this.fb_logout.bind(this),
        });

        form.addEvent('submit', function(e) {
            e.stop();
            var submitbtn = this;
            
            if (user.isEmpty()) {
                user.addClass('error');
                return user.focus();
            } else
                user.removeClass('error');

            if (pass.isEmpty()) {
                pass.addClass('error');
                return pass.focus();
            } else
                pass.removeClass('error');

            self.auth(user.value, pass.value,
                function(status) {
                    self.publish('user.logged_in', []);

                    if (status == 'login') 
                        submitbtn.submit();
                }
            );
        });
    },

    auth: function(user, pass, callback) {
        var callback = callback || $empty();

        var el = $('login-button');
        var position = el.getPosition();
        position = [position.x+63, position.y+25];

        _notifications.alert('Please wait', "We are processing your request... <img src='/images/ajax_loading.gif'>",
            { color: 'black',  duration: 10000, position: position});
        var executing = _notifications.items.getLast();

        data = {'user': user,
                'pass': pass,
                'type': 'user'};
        new Request({
            url: '/AJAX/login.php',
            data: data,
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                _notifications.remove(executing);

                if(response.status == 'REGISTERED') {
                    _notifications.alert('Success', '<img src="/images/icons/accept.png" /> Welcome back.  Logging you in...',
                        {color: 'darkgreen', duration: 2000, position: position});

                    callback.delay(2000, this, 'login');
                } else if (response.status == 'NOT_REGISTERED') {
                    _notifications.alert('Error', 'Sorry, there is no user with this username and password, please try again',
                        {color: 'darkred', duration: 5000, position: position});
                }

            }
        }).send();
    },

    fb_login: function() {
        var fb_login = this.form.getElement('input[name="fb_login"]'),
            form = this.form;

        new Request({
            url: '/AJAX/login.php',
            data: {'type': 'facebook'},
            onSuccess: function() {
                var response = JSON.decode(this.response.text);

                if (response.status == 'REGISTERED') {
                    //if registred, proceed to auth on homepage
                    fb_login.set('value', '1');

                    var el = $('login-button');
                    var position = el.getPosition();
                    position = [position.x+63, position.y+25];

                    _notifications.alert('Success', '<img src="/images/icons/accept.png" /> Welcome back.  Logging you in...',
                        {color: 'darkgreen', duration: 2000, position: position});
            
                    callback = function() { form.submit(); };
                    callback.delay(2000, this, 'login');
                } else if (response.status == 'NOT_REGISTERED') {
                    //if none, then get fb info and show signup form
                    var data = response.data;

                    var signup_form = $('modal-signup');
                    signup_form.getElement('span#modal-signup-name').set('text', data.fname);
                    signup_form.getElement('p#real_name').setStyle('display', 'none');
                    signup_form.getElement('input[name="name"]').addClass('passed').store('passed', 1);
                    signup_form.getElement('input[name="facebook"]').set('value', 1).store('passed', 1);
                    signup_form.getElement('input[name="fb_fname"]').set('value', data.fname).store('passed', 1);
                    signup_form.getElement('input[name="fb_lname"]').set('value', data.lname).store('passed', 1);

                    show_signup();
                }
            }
        }).send();
    },

    fb_logout: function() {
        //simply redirect to general logout, or do nothing
        //document.location.rewrite('/?logout=true');
    }
});
