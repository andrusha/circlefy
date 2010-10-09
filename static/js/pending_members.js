var _pending = _tap.register({

    init: function() {

        $$('button.button').addEvent('click', this.groupAction.toHandler(this));
    },

    groupAction: function(el, e) {
        if (el.hasClass('accept')) return this.acceptRequest(el, 'accept');
        if (el.hasClass('decline')) return this.acceptRequest(el, 'decline');
    },

    acceptRequest: function(el, action) {
        var self = this;
        new Request({
            url: '/AJAX/accept_member.php',
            method: 'post',
            data: {
                uid: el.getData('uid'),
                gid: el.getData('gid'),
                action: action
            },
            onSuccess: function() {
                var response = JSON.decode(this.response.text);
                if (!response.good) return;

                if (action == 'accept'){
                    el.getParent('.buttons').set('html', 'Accepted');
                } else {
                    el.getParent('.buttons').set('html', 'Declined');
                }
            }
        }).send();
    }
});