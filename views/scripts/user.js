/*
 * This script is all about user page
 * user menu, display following/followers,
 * send private tap
 */

var _user = {};

_user.menu = _tap.register({

	init: function(){
		_body.addEvents({
			'click:relay(a.tab)': this.doAction.toHandler(this)
		});

	},

	doAction: function(el, e){
        e.stop();

        $$('div.item.selected')[0].removeClass('selected');

        $$('a.tab.selected')[0].removeClass('selected');
        el.addClass('selected');

        switch (el.get('data-name')) {
            case 'tapstream':
                $('user-tapstream').addClass('selected');
                $('tapbox').addClass('hidden');
                this.publish('list.item', ['peoples', _vars.filter.uid,
                    {name: _vars.filter.uname, symbol: _vars.filter.uname, hide: true}]);
                break;

            case 'pm':
                $('user-tapstream').addClass('selected');
                $('tapbox').removeClass('hidden');
                this.publish('list.item', ['private', _vars.filter.uid, 
                    {name: _vars.filter.uname, symbol: _vars.filter.uname, hide: true}]);
                break;

            case 'following':
                $('user-following').addClass('selected');
                $('tapbox').addClass('hidden');
                break;

            case 'followers':
                $('user-followers').addClass('selected');
                $('tapbox').addClass('hidden');
                break;
        }
    }

});
