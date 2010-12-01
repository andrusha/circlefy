var Main = new Class({
    initialize: function() {
        var self = this;
        $$('form').addEvent('submit', function(e) {
            e.stop();
            self.send(this);
        });

        var inputs = $$('input[type="text"]');
        var def    = inputs[0].value;

        inputs.addEvent('focus', function() {
            if (!self.check(this.value))
                this.value = '';
        });

        inputs.addEvent('blur', function() {
            if (!self.check(this.value))
                this.value = def;
        });
    },

    send: function (form) {
        var email = form.getElement('input[type="text"]').value;
        $$('p.error', 'p.okay').addClass('hidden');

        if (!this.check(email)) {
            $('error-wrong').removeClass('hidden');
            return;
        }

        new Request({
            url: '/ajax/invite.php',
            data: {email: email},
            onSuccess: function() {
                var res = this.response.text;
                if (res == '1') {
                    $('okay').removeClass('hidden');
                } else {
                    $('error-dupe').removeClass('hidden');
                }
            }
        }).send();
    },

    check: function(mail) {
        return mail.clean().test(/^\w+@\w+\.\w+$/);
    }
});

window.addEvent('domready', function () {
    var start = new Main();    
});

