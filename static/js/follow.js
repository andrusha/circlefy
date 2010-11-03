var _follow = _tap.register({
    init: function(){
        _body.addEvent('click:relay(.follow)', this.follow.toHandler(this));
    },

    follow: function(el, e) {
        var el    = e.target,
            data  = {
                id:    el.getData('id')*1,
                type:  el.getData('type'),
                state: (!(el.getData('followed')*1) ? 1 : 0)},
            auth  = el.getData('auth'),
            self  = this;
                

        new Request({
            url: '/AJAX/follow',
            data: data,
            onSuccess: function(){
                var response = JSON.decode(this.response.text);
                if (response.success) {
                    if (auth == 'manual') 
                        el.set({'def': 'Join Circle', 'alt': 'Waiting Approval'});
                    el.setData('followed', data.state);
                    el.toggleClass('active');
                } else {
                    if (auth == 'email') {
                        self.publish('modal.show.group-email-auth');
                    }
                }
            }
        }).send();
    }
});

