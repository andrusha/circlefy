var x;
var _followPerson = _tap.register({

        init: function(){
                var self = this;
                _body.addEvents({
                        'click:relay(.follow)': this.follow.toHandler(this),
                });
        },

        follow: function(el, e){
		var el = e.target;
                var self = this,
                        id = el.getData('userId'),
                        type = Number(el.getData('followed'));

                new Request({
                        url: '/AJAX/user/follow',
                        data: {
                                fid: id,
                                state: (type) ? 0 : 1
                        },
                        onSuccess: function(){
                                var response = JSON.decode(this.response.text);
                                if (response.success) {
                                        el.set({
                                                'text': (type) ? 'Follow' : 'Unfollow',
                                                'data-followed': (type) ? 0 : 1 
                                        });
                                }
                        }
                }).send();
        }

});

