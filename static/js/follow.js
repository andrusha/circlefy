var _follow = _tap.register({
    init: function(){
        _body.addEvents({
                'click:relay(.follow)': this.follow.toHandler(this),
        });
    },

    follow: function(el, e) {
        var el    = e.target,
            data  = {
                id:    el.getData('id')*1,
                type:  el.getData('type'),
                state: (!(el.getData('followed')*1) ? 1 : 0)};

        new Request({
            url: '/AJAX/follow',
            data: data,
            onSuccess: function(){
                var response = JSON.decode(this.response.text);
                if (response.success) {
                    el.setData('followed', data.state);
                    el.toggleClass('active');
                }
            }
        }).send();
    }
});

